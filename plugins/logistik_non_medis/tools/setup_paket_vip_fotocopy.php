<?php
/**
 * Setup paket & fotocopy:
 * 1) Buat barang isi paket (dilewati bila nama sudah ada) + satuan yang belum ada di master.
 * 2) Tiga paket (barang hasil):
 *    - Paket Welcome Pack (BRG0720260302, nama lama "Paket welcome pack vip") = isi bersama + Handuk besar
 *    - Paket VIP Pack  = isi bersama + Handuk VIP (hanya handuknya yang beda)
 *    - Paket Fisio Pack = dibuat tanpa isi; komposisinya diisi dari menu Paket & Barang Ecer
 * 3) Resep semua barang kategori Fotocopy yang belum punya resep: 1 hasil = 1 lembar Kertas F4.
 *
 *   php plugins/logistik_non_medis/tools/setup_paket_vip_fotocopy.php           # uji coba (rollback)
 *   php plugins/logistik_non_medis/tools/setup_paket_vip_fotocopy.php --commit  # simpan
 *
 * Aman diulang: barang dan resep yang sudah ada tidak diubah. Qty tetap bisa diubah dari
 * menu Paket & Barang Ecer (mis. formulir 2 lembar = 2).
 */
if (PHP_SAPI !== 'cli') {
    exit('Jalankan dari command line.');
}
define('BASE_DIR', dirname(__DIR__, 3));
require BASE_DIR . '/config.php';

const WELCOME_PACK = 'BRG0720260302';  // dulu "Paket welcome pack vip"
const HANDUK_BESAR = 'BRG0720260210';  // handuk untuk Welcome Pack
const KERTAS_F4 = 'BRG0720260241';     // Kertas F4 (Lembar)
const KATEGORI = ['KAT-017', 'Linen & Perlengkapan Pasien'];

// Isi yang sama di Welcome Pack dan VIP Pack: [nama barang, satuan dasar]
$isiBersama = [
    ['Tas Pack VIP', 'Pcs'],
    ['Sandal Hotel', 'Pasang'],
    ['Odol (Pasta Gigi)', 'Pcs'],
    ['Sikat Gigi', 'Pcs'],
    ['Tisu Plastik (kemasan)', 'Pcs'],
    ['Air Mineral', 'Botol'],
    ['Handscoon', 'Pasang'],
];
$handukVip = ['Handuk VIP', 'Pcs'];

$commit = in_array('--commit', $argv, true);
$T = 'rsns_custom_logistik_non_medis_';
$pdo = new PDO('mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$q = function (string $sql, array $p = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s;
};
$laporan = [];

// Sama dengan Admin::_generateKodeBarang(): BRG + mY + nomor urut 4 digit.
$kodeBaru = function () use ($q, $T): string {
    $prefix = 'BRG' . date('mY');
    $last = $q("SELECT kode_item FROM {$T}master_barang WHERE kode_item LIKE ? ORDER BY kode_item DESC LIMIT 1", [$prefix . '%'])->fetchColumn();
    return $prefix . str_pad((string)($last ? (int)substr($last, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);
};
// Cari barang aktif menurut nama; bila belum ada, buat. Mengembalikan kode_item.
$pastikanBarang = function (string $nama, string $satuan, string $deskripsi) use ($q, $T, $kodeBaru, &$laporan): string {
    $ada = $q("SELECT kode_item FROM {$T}master_barang WHERE LOWER(nama_barang) = LOWER(?) LIMIT 1", [$nama])->fetchColumn();
    if ($ada) {
        $laporan[] = "barang $ada $nama: sudah ada";
        return $ada;
    }
    if (!$q("SELECT 1 FROM {$T}satuan WHERE LOWER(nama_satuan) = LOWER(?) LIMIT 1", [$satuan])->fetchColumn()) {
        $q("INSERT INTO {$T}satuan (kode_satuan, nama_satuan, satuan_dasar, nilai_konversi) VALUES (?, ?, ?, 1)", [strtoupper($satuan), $satuan, $satuan]);
        $laporan[] = "satuan baru di master: $satuan";
    }
    $kode = $kodeBaru();
    $q("INSERT INTO {$T}master_barang (kode_item, barcode, nama_barang, deskripsi, kategori, jenis_item, tipe_barang, kode_kategori,
            satuan_dasar, satuan_konversi, harga_referensi, stok_min, stok_max, safety_stock, status)
        VALUES (?, '', ?, ?, ?, 'Rutin', 'Habis Pakai', ?, ?, '', 0, 0, 0, 0, 'Aktif')",
        [$kode, $nama, $deskripsi, KATEGORI[1], KATEGORI[0], $satuan]);
    $q("INSERT INTO {$T}barang_satuan (kode_item, satuan, faktor_ke_dasar, is_default_permintaan, status) VALUES (?, ?, 1, 1, 'Aktif')", [$kode, $satuan]);
    $laporan[] = "barang $kode $nama ($satuan): DIBUAT";
    return $kode;
};
$punyaResep = fn (string $kode) => (bool)$q("SELECT 1 FROM {$T}produksi_resep WHERE kode_item_hasil = ? AND status = 'Aktif' LIMIT 1", [$kode])->fetchColumn();
$simpanResep = fn (string $hasil, string $bahan, float $qty) => $q("INSERT INTO {$T}produksi_resep (kode_item_hasil, kode_item_bahan, qty_bahan_per_hasil, status, user_input)
    VALUES (?, ?, ?, 'Aktif', 'setup-paket-vip') ON DUPLICATE KEY UPDATE status = status", [$hasil, $bahan, $qty])->rowCount();

$pdo->beginTransaction();
try {
    foreach ([WELCOME_PACK, HANDUK_BESAR, KERTAS_F4] as $wajib) {
        if (!$q("SELECT 1 FROM {$T}master_barang WHERE kode_item = ?", [$wajib])->fetchColumn()) {
            throw new RuntimeException("Barang $wajib tidak ditemukan.");
        }
    }
    if ($punyaResep(KERTAS_F4)) {
        throw new RuntimeException('Kertas F4 sendiri punya resep; resep bertingkat tidak didukung.');
    }

    // 1) barang isi
    $kodeBersama = [];
    foreach ($isiBersama as [$nama, $satuan]) {
        $kodeBersama[] = $pastikanBarang($nama, $satuan, 'Isi Welcome/VIP Pack');
    }
    $kodeHandukVip = $pastikanBarang($handukVip[0], $handukVip[1], 'Isi VIP Pack');

    // 2) tiga paket
    $q("UPDATE {$T}master_barang SET nama_barang = 'Paket Welcome Pack' WHERE kode_item = ? AND nama_barang = 'Paket welcome pack vip'", [WELCOME_PACK]);
    $kodeVip = $pastikanBarang('Paket VIP Pack', 'Pcs', 'Paket untuk pasien VIP: isi sama dengan Welcome Pack, handuk VIP');
    $kodeFisio = $pastikanBarang('Paket Fisio Pack', 'Pcs', 'Paket fisioterapi: isi diatur di menu Paket & Barang Ecer');
    // Tandai jenis barang induk (kolom master_barang.jenis_komposisi dibuat oleh modul).
    $adaKolomJenis = (bool)$q("SHOW COLUMNS FROM {$T}master_barang LIKE 'jenis_komposisi'")->fetch();
    if ($adaKolomJenis) {
        $q("UPDATE {$T}master_barang SET jenis_komposisi = 'Paket' WHERE kode_item IN (?, ?, ?)", [WELCOME_PACK, $kodeVip, $kodeFisio]);
    }
    $paket = [
        'Paket Welcome Pack' => [WELCOME_PACK, array_merge($kodeBersama, [HANDUK_BESAR])],
        'Paket VIP Pack' => [$kodeVip, array_merge($kodeBersama, [$kodeHandukVip])],
    ];
    foreach ($paket as $nama => [$kodePaket, $isi]) {
        $baru = 0;
        foreach ($isi as $kodeIsi) {
            $baru += $simpanResep($kodePaket, $kodeIsi, 1) ? 1 : 0;
        }
        $laporan[] = "$nama ($kodePaket): " . count($isi) . " isi x1 ($baru baru)";
    }
    $laporan[] = "Paket Fisio Pack ($kodeFisio): " . ($punyaResep($kodeFisio) ? 'sudah punya isi' : 'BELUM ADA ISI, atur dari menu Paket & Barang Ecer');

    // 3) resep fotocopy -> Kertas F4
    $diatur = 0;
    $dilewati = 0;
    $fotocopy = $q("SELECT kode_item FROM {$T}master_barang WHERE kategori = 'Fotocopy' AND status = 'Aktif' AND kode_item <> ?", [KERTAS_F4])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($fotocopy as $kode) {
        if ($punyaResep($kode)) {
            $dilewati++;
            continue;
        }
        $simpanResep($kode, KERTAS_F4, 1);
        $diatur++;
    }
    if ($adaKolomJenis && $fotocopy) {
        $marks = implode(',', array_fill(0, count($fotocopy), '?'));
        $q("UPDATE {$T}master_barang SET jenis_komposisi = 'Olahan' WHERE jenis_komposisi IS NULL AND kode_item IN ($marks)", $fotocopy);
    }
    $laporan[] = "Resep fotocopy -> 1 lembar Kertas F4: $diatur barang diatur, $dilewati sudah punya resep (dilewati)";

    $commit ? $pdo->commit() : $pdo->rollBack();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'GAGAL, tidak ada yang diubah: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $commit ? "DISIMPAN\n" : "UJI COBA (rollback). Tambahkan --commit untuk menyimpan.\n";
foreach ($laporan as $l) {
    echo "- $l\n";
}
