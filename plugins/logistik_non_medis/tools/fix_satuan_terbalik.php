<?php
/**
 * Perbaiki barang yang konversi satuannya terbalik: satuan dasar = satuan besar
 * (Box/Pack) padahal satuan kecil (Biji/Lembar) diberi faktor > 1. Setelah
 * diperbaiki, satuan dasar = satuan terkecil dan seluruh stok/harga dikonversi
 * sehingga nilai persediaan tidak berubah.
 *
 *   php plugins/logistik_non_medis/tools/fix_satuan_terbalik.php           # uji coba (rollback)
 *   php plugins/logistik_non_medis/tools/fix_satuan_terbalik.php --commit  # simpan
 */
if (PHP_SAPI !== 'cli') {
    exit('Jalankan dari command line.');
}
define('BASE_DIR', dirname(__DIR__, 3));
require BASE_DIR . '/config.php';

// kode_item => [satuan kecil (dasar baru), satuan besar, faktor: 1 besar = F kecil]
$perbaikan = [
    'BRG0720260334' => ['Lembar', 'Pack', 10],
    'BRG0720260258' => ['Biji', 'Box', 12],
    'BRG0720260252' => ['Biji', 'Box', 12],
];
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
$nilaiPersediaan = fn (string $kode) => (float)$q("SELECT COALESCE(SUM(stok * harga_beli), 0) FROM {$T}stok_batch WHERE kode_item = ?", [$kode])->fetchColumn();

$backup = [];
$laporan = [];
$pdo->beginTransaction();
try {
    foreach ($perbaikan as $kode => [$kecil, $besar, $F]) {
        $barang = $q("SELECT * FROM {$T}master_barang WHERE kode_item = ?", [$kode])->fetch();
        if (!$barang) {
            $laporan[] = "$kode: tidak ditemukan, dilewati";
            continue;
        }
        if (strcasecmp($barang['satuan_dasar'], $kecil) === 0) {
            $laporan[] = "$kode {$barang['nama_barang']}: satuan dasar sudah $kecil, dilewati";
            continue;
        }

        $backup[$kode] = [
            'master_barang' => $barang,
            'barang_satuan' => $q("SELECT * FROM {$T}barang_satuan WHERE kode_item = ?", [$kode])->fetchAll(),
            'stok' => $q("SELECT * FROM {$T}stok WHERE kode_item = ?", [$kode])->fetchAll(),
            'stok_batch' => $q("SELECT * FROM {$T}stok_batch WHERE kode_item = ?", [$kode])->fetchAll(),
            'kartu_stok' => $q("SELECT * FROM {$T}kartu_stok WHERE kode_item = ?", [$kode])->fetchAll(),
            'sppb' => $q("SELECT id, no_sppb, jumlah, jumlah_disetujui, satuan FROM {$T}sppb WHERE kode_item = ?", [$kode])->fetchAll(),
            'sppb_item_meta' => $q("SELECT m.* FROM {$T}sppb_item_meta m JOIN {$T}sppb s ON s.id = m.sppb_item_id WHERE s.kode_item = ?", [$kode])->fetchAll(),
        ];
        $nilaiAwal = $nilaiPersediaan($kode);
        $stokAwal = $q("SELECT COALESCE(SUM(stok_akhir), 0) FROM {$T}stok WHERE kode_item = ?", [$kode])->fetchColumn();

        // master barang
        $q("UPDATE {$T}master_barang SET satuan_dasar = ?, satuan_konversi = ?, harga_referensi = harga_referensi / ?,
                stok_min = stok_min * ?, stok_max = stok_max * ?, safety_stock = safety_stock * ? WHERE kode_item = ?",
            [$kecil, "1 $besar = $F $kecil", $F, $F, $F, $F, $kode]);

        // daftar satuan: kecil = dasar & default permintaan, besar = F
        $q("UPDATE {$T}barang_satuan SET faktor_ke_dasar = 1, is_default_permintaan = 1, updated_at = NOW() WHERE kode_item = ? AND satuan = ?", [$kode, $kecil]);
        $q("UPDATE {$T}barang_satuan SET faktor_ke_dasar = ?, is_default_permintaan = 0, updated_at = NOW() WHERE kode_item = ? AND satuan = ?", [$F, $kode, $besar]);

        // stok & batch (nilai persediaan tetap)
        $q("UPDATE {$T}stok SET stok_akhir = stok_akhir * ? WHERE kode_item = ?", [$F, $kode]);
        $q("UPDATE {$T}stok_batch SET stok = stok * ?, harga_beli = harga_beli / ? WHERE kode_item = ?", [$F, $F, $kode]);

        // kartu stok: hanya baris yang dicatat dalam satuan besar yang dikonversi. Saldo
        // tiap baris ikut dikalikan (bukan dihitung ulang) karena baris opname menetapkan
        // saldo, bukan menambah; baris lama yang sudah bersatuan kecil dibiarkan.
        $q("UPDATE {$T}kartu_stok SET qty_masuk = qty_masuk * ?, qty_keluar = qty_keluar * ?, stok_akhir = stok_akhir * ?,
                harga = harga / ?, satuan_snapshot = ?
             WHERE kode_item = ? AND (satuan_snapshot = ? OR satuan_snapshot IS NULL OR satuan_snapshot = '')",
            [$F, $F, $F, $F, $kecil, $kode, $besar]);

        // permintaan: qty & satuan yang diminta unit tetap, jumlah dalam satuan dasar ikut dikonversi
        $q("UPDATE {$T}sppb_item_meta m JOIN {$T}sppb s ON s.id = m.sppb_item_id
            SET m.faktor_konversi = IF(s.satuan = ? OR s.satuan IS NULL OR s.satuan = '', ?, m.faktor_konversi),
                m.jumlah_dasar = m.jumlah_dasar * ?, m.jumlah_disetujui_dasar = m.jumlah_disetujui_dasar * ?,
                m.satuan_dasar_snapshot = ?
            WHERE s.kode_item = ?", [$besar, $F, $F, $F, $kecil, $kode]);
        // satuan kosong berarti "satuan dasar lama" -> tulis eksplisit agar 8 tetap dibaca 8 Pack
        $q("UPDATE {$T}sppb SET satuan = ? WHERE kode_item = ? AND (satuan IS NULL OR satuan = '')", [$besar, $kode]);
        $nSppb = (int)$q("SELECT COUNT(*) FROM {$T}sppb WHERE kode_item = ?", [$kode])->fetchColumn();

        // pemeriksaan
        $nilaiAkhir = $nilaiPersediaan($kode);
        if (abs($nilaiAkhir - $nilaiAwal) > 0.01) {
            throw new RuntimeException("$kode: nilai persediaan berubah ($nilaiAwal -> $nilaiAkhir)");
        }
        $stokAkhir = (float)$q("SELECT COALESCE(SUM(stok_akhir), 0) FROM {$T}stok WHERE kode_item = ?", [$kode])->fetchColumn();
        $stokBatch = (float)$q("SELECT COALESCE(SUM(stok), 0) FROM {$T}stok_batch WHERE kode_item = ?", [$kode])->fetchColumn();
        $saldoKartu = (float)$q("SELECT COALESCE(SUM(k.stok_akhir), 0) FROM {$T}kartu_stok k
            WHERE k.kode_item = ? AND k.id = (SELECT k2.id FROM {$T}kartu_stok k2 WHERE k2.kode_item = k.kode_item AND k2.kode_lokasi = k.kode_lokasi ORDER BY k2.tgl_transaksi DESC, k2.id DESC LIMIT 1)", [$kode])->fetchColumn();
        if (abs($stokAkhir - $stokBatch) > 0.001 || abs($stokAkhir - $saldoKartu) > 0.001) {
            throw new RuntimeException("$kode: stok tidak konsisten (stok=$stokAkhir, batch=$stokBatch, kartu=$saldoKartu)");
        }
        $harga = (float)$q("SELECT harga_referensi FROM {$T}master_barang WHERE kode_item = ?", [$kode])->fetchColumn();
        $laporan[] = sprintf("%s %s: %s %s -> %s %s | harga ref Rp %s/%s | nilai persediaan Rp %s (tetap) | %d baris SPPB disesuaikan",
            $kode, $barang['nama_barang'], rtrim(rtrim(number_format((float)$stokAwal, 2, ',', '.'), '0'), ','), $besar,
            rtrim(rtrim(number_format($stokAkhir, 2, ',', '.'), '0'), ','), $kecil, number_format($harga, 2, ',', '.'), $kecil,
            number_format($nilaiAkhir, 0, ',', '.'), $nSppb);
    }

    if ($commit) {
        if ($backup) {
            $file = __DIR__ . '/backup_fix_satuan_' . date('Ymd_His') . '.json';
            file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $laporan[] = 'Backup data asli: ' . $file;
        }
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'GAGAL, tidak ada yang diubah: ' . $e->getMessage() . "\n");
    exit(1);
}

echo ($commit ? "DISIMPAN\n" : "UJI COBA (rollback, belum disimpan). Tambahkan --commit untuk menyimpan.\n");
foreach ($laporan as $l) {
    echo "- $l\n";
}
