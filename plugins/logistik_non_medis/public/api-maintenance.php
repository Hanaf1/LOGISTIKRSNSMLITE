<?php
/**
 * REST buku maintenance aset — catatan pekerjaan yang SUDAH dikerjakan.
 *
 *   GET  api-maintenance.php                       daftar catatan terbaru
 *   GET  api-maintenance.php?kode_aset=AST-...     buku satu aset
 *   GET  api-maintenance.php?nomor=LOG-202609-0001 satu catatan
 *   GET  ?sejak=2026-09-01&limit=200&offset=0      saring tanggal + halaman
 *   POST api-maintenance.php                       tambah catatan ke buku
 *
 * Isian POST (JSON atau form):
 *   kode_aset   wajib
 *   keterangan  wajib, pekerjaan yang dilakukan
 *   teknisi     wajib, nama pengerja
 *   tanggal     opsional, bawaan hari ini, tidak boleh melewati hari ini
 *
 * Catatan masuk dengan status 'Selesai', seri nomor LOG- yang sama dengan
 * pengisian dari menu admin maupun dari halaman QR, supaya bukunya tetap satu.
 */

require_once(__DIR__.'/api-bersama.php');

tolak_bila_bukan_lokal();
$pdo = koneksi();

$kolom = "p.kode_pemeliharaan, p.kode_aset, a.nama_aset, a.nomor_inventaris,
          a.kode_unit, COALESCE(iu.nama, u.nama_unit, a.kode_unit) AS nama_unit,
          p.jenis_pemeliharaan, p.tanggal_direncanakan, p.tanggal_pelaksanaan,
          p.nama_kegiatan, p.tindakan_perbaikan, p.nama_teknisi, p.prioritas,
          p.status, p.status_kondisi_akhir, p.total_biaya, p.user_input, p.tgl_input";
$gabung = "FROM rsns_custom_logistik_non_medis_aset_pemeliharaan p
           LEFT JOIN rsns_custom_logistik_non_medis_aset a ON a.kode_aset = p.kode_aset
           LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
             ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
           LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = a.kode_unit";

$metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---------------------------------------------------------------------------
// POST: tambah catatan ke buku maintenance.
// ---------------------------------------------------------------------------
if ($metode === 'POST') {
    $data = masukan();
    $kode_aset = isian($data, 'kode_aset');
    $keterangan = isian($data, 'keterangan');
    $teknisi = isian($data, 'teknisi');
    $tanggal = isian($data, 'tanggal') ?: date('Y-m-d');

    $salah = [];
    if ($kode_aset === '') {
        $salah['kode_aset'] = 'Wajib diisi.';
    }
    if (mb_strlen($keterangan) < 3 || mb_strlen($keterangan) > 2000) {
        $salah['keterangan'] = 'Wajib diisi, 3 sampai 2000 karakter.';
    }
    if (mb_strlen($teknisi) < 2 || mb_strlen($teknisi) > 40) {
        $salah['teknisi'] = 'Wajib diisi, 2 sampai 40 karakter.';
    }
    $sah = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$sah || $sah->format('Y-m-d') !== $tanggal) {
        $salah['tanggal'] = 'Format harus YYYY-MM-DD.';
    } elseif ($tanggal > date('Y-m-d')) {
        $salah['tanggal'] = 'Tidak boleh melewati hari ini.';
    } elseif ($tanggal < '2000-01-01') {
        $salah['tanggal'] = 'Terlalu lampau.';
    }
    if ($salah) {
        balas(['error' => 'Isian tidak lengkap atau tidak sah.', 'rincian' => $salah], 422);
    }

    $aset = ambil_aset($pdo, $kode_aset);
    if (!$aset) {
        balas(['error' => 'Aset tidak ditemukan.'], 404);
    }
    if ($aset['status'] !== 'Aktif') {
        balas(['error' => 'Aset berstatus '.$aset['status'].', buku tidak bisa diisi.'], 409);
    }

    // Cegah catatan dobel karena permintaan diulang saat jaringan putus.
    $cek = $pdo->prepare("SELECT kode_pemeliharaan FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
        WHERE kode_aset = ? AND tindakan_perbaikan = ? AND tgl_input >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1");
    $cek->execute([$kode_aset, $keterangan]);
    if ($kembar = $cek->fetchColumn()) {
        balas(['status' => 'kembar', 'pesan' => 'Catatan serupa baru saja masuk.', 'nomor' => $kembar], 200);
    }

    // Kolom diisi sama persis seperti postSaveBukuMaintenance di modul admin.
    $simpan = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_aset_pemeliharaan
        (kode_pemeliharaan, kode_aset, jenis_pemeliharaan, tanggal_direncanakan, tanggal_pelaksanaan,
         nama_kegiatan, tindakan_perbaikan, nama_teknisi, frekuensi, status, user_input, tgl_input)
        VALUES (?, ?, 'Corrective', ?, CONCAT(?, ' ', CURTIME()), 'Perbaikan Cepat / Harian',
                ?, ?, 'Sekali Saja', 'Selesai', ?, NOW())");

    // Nomor urut bisa bentrok kalau dua pemanggil menulis bersamaan; ulangi
    // dengan nomor berikutnya, bukan menolak catatannya.
    $nomor = '';
    for ($percobaan = 1; $percobaan <= 5; $percobaan++) {
        $calon = nomor_berikutnya($pdo, 'LOG-'.date('Ym').'-');
        try {
            $simpan->execute([
                $calon, $kode_aset, $tanggal, $tanggal,
                $keterangan, $teknisi, mb_substr('API: '.$teknisi, 0, 50),
            ]);
            $nomor = $calon;
            break;
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000' || $percobaan === 5) {
                throw $ex;
            }
        }
    }
    if ($nomor === '') {
        balas(['error' => 'Catatan gagal disimpan.'], 500);
    }

    $stmt = $pdo->prepare("SELECT {$kolom} {$gabung} WHERE p.kode_pemeliharaan = ? LIMIT 1");
    $stmt->execute([$nomor]);
    balas(['status' => 'tersimpan', 'nomor' => $nomor, 'data' => $stmt->fetch()], 201);
}

if ($metode !== 'GET') {
    balas(['error' => 'Metode tidak didukung. Gunakan GET atau POST.'], 405);
}

balas_daftar_pilihan($pdo);

// ---------------------------------------------------------------------------
// GET satu catatan.
// ---------------------------------------------------------------------------
if (($nomor = trim($_GET['nomor'] ?? '')) !== '') {
    $stmt = $pdo->prepare("SELECT {$kolom} {$gabung} WHERE p.kode_pemeliharaan = ? LIMIT 1");
    $stmt->execute([$nomor]);
    $baris = $stmt->fetch();
    if (!$baris) {
        balas(['error' => 'Catatan tidak ditemukan.'], 404);
    }
    $baris['tautan_qr'] = basis_url().'/aset-info.php?kode='.rawurlencode($baris['kode_aset']);
    balas(['data' => $baris]);
}

// ---------------------------------------------------------------------------
// GET daftar. Hanya pekerjaan yang sudah selesai — itulah isi buku maintenance.
// ---------------------------------------------------------------------------
$syarat = ["p.status = 'Selesai'"];
$isi = [];

saring_unit_barang($pdo, $syarat, $isi);
if (($sejak = trim($_GET['sejak'] ?? '')) !== '') {
    $sah = DateTime::createFromFormat('Y-m-d', $sejak);
    if (!$sah || $sah->format('Y-m-d') !== $sejak) {
        balas(['error' => 'Parameter sejak harus YYYY-MM-DD.'], 400);
    }
    $syarat[] = 'p.tanggal_pelaksanaan >= ?';
    $isi[] = $sejak.' 00:00:00';
}

$where = 'WHERE '.implode(' AND ', $syarat);
list($limit, $offset) = batas_halaman();

$hitung = $pdo->prepare("SELECT COUNT(*) {$gabung} {$where}");
$hitung->execute($isi);
$total = (int)$hitung->fetchColumn();

$stmt = $pdo->prepare("SELECT {$kolom} {$gabung} {$where}
    ORDER BY p.tanggal_pelaksanaan DESC, p.id DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($isi);
$baris = $stmt->fetchAll();

foreach ($baris as &$satu) {
    $satu['tautan_qr'] = basis_url().'/aset-info.php?kode='.rawurlencode($satu['kode_aset']);
}

balas([
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'berikutnya' => tautan_berikutnya('api-maintenance.php', $limit, $offset, $total),
    'data' => $baris,
]);
