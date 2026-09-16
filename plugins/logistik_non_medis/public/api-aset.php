<?php
/**
 * Prototipe: daftar aset + kondisinya dalam bentuk JSON, untuk diambil sistem
 * helpdesk. Hanya baca, tidak ada yang bisa diubah lewat berkas ini.
 *
 * Sengaja dibuat sesederhana mungkin: satu berkas, tanpa tabel baru, tanpa
 * cron, tanpa antrean. Cukup untuk membuktikan sambungannya jalan lebih dulu.
 *
 * Pemakaian:
 *   api-aset.php                          -> 100 aset pertama
 *   api-aset.php?limit=500&offset=500     -> halaman berikutnya
 *   api-aset.php?unit=99                  -> satu unit saja
 *   api-aset.php?kondisi=Rusak Ringan     -> saring kondisi
 *   api-aset.php?q=sofa                   -> cari nama/kode
 *   api-aset.php?kode=AST-992060602201    -> satu aset, lengkap dengan riwayat
 */

require_once(__DIR__.'/api-bersama.php');

tolak_bila_bukan_lokal();
$pdo = koneksi();
$basis = basis_url();

$kolom = "a.kode_aset, a.nomor_inventaris, a.nama_aset, a.merk_type, a.serial_number,
          a.kode_unit, COALESCE(iu.nama, u.nama_unit, a.kode_unit) AS nama_unit,
          a.lokasi_fisik, a.status_kondisi, a.status, a.tahun_beli,
          a.klasifikasi_pencatatan, a.pic";
$gabung = "FROM rsns_custom_logistik_non_medis_aset a
           LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
             ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
           LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = a.kode_unit";

$kode = trim($_GET['kode'] ?? '');

// ---------------------------------------------------------------------------
// Satu aset: dilengkapi riwayat perawatan dan hasil sensus terakhir.
// ---------------------------------------------------------------------------
if ($kode !== '') {
    $stmt = $pdo->prepare("SELECT {$kolom} {$gabung} WHERE a.kode_aset = ? LIMIT 1");
    $stmt->execute([$kode]);
    $aset = $stmt->fetch();
    if (!$aset) {
        balas(['error' => 'Aset tidak ditemukan.'], 404);
    }

    $stmt = $pdo->prepare("SELECT kode_pemeliharaan, tanggal_pelaksanaan, nama_kegiatan,
               tindakan_perbaikan, nama_teknisi, status
        FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
        WHERE kode_aset = ? ORDER BY tanggal_pelaksanaan DESC, id DESC LIMIT 10");
    $stmt->execute([$kode]);
    $aset['riwayat_pemeliharaan'] = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT nama_sensus, status_sensus_item, fisik_status_kondisi,
               catatan_temuan, tanggal_scan, petugas_scan
        FROM rsns_custom_logistik_non_medis_aset_sensus
        WHERE kode_aset = ? ORDER BY tanggal_mulai DESC, id DESC LIMIT 10");
    $stmt->execute([$kode]);
    $aset['riwayat_sensus'] = $stmt->fetchAll();

    $aset['tautan_qr'] = $basis.'/aset-info.php?kode='.rawurlencode($kode);
    balas(['data' => $aset]);
}

// ---------------------------------------------------------------------------
// Daftar aset.
// ---------------------------------------------------------------------------
$syarat = ["a.status = 'Aktif'"];
$isi = [];

if (($unit = trim($_GET['unit'] ?? '')) !== '') {
    $syarat[] = 'a.kode_unit = ?';
    $isi[] = $unit;
}
if (($kondisi = trim($_GET['kondisi'] ?? '')) !== '') {
    if (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)) {
        balas(['error' => 'Kondisi tidak dikenal. Pilih: Baik, Rusak Ringan, Rusak Berat.'], 400);
    }
    $syarat[] = 'a.status_kondisi = ?';
    $isi[] = $kondisi;
}
if (($klasifikasi = trim($_GET['klasifikasi'] ?? '')) !== '') {
    if (!in_array($klasifikasi, ['ASET', 'INVENTARIS_NON_ASET'], true)) {
        balas(['error' => 'Klasifikasi tidak dikenal. Pilih: ASET, INVENTARIS_NON_ASET.'], 400);
    }
    $syarat[] = 'a.klasifikasi_pencatatan = ?';
    $isi[] = $klasifikasi;
}
if (($cari = trim($_GET['q'] ?? '')) !== '') {
    $syarat[] = '(a.nama_aset LIKE ? OR a.kode_aset LIKE ? OR a.nomor_inventaris LIKE ?)';
    $suka = '%'.$cari.'%';
    $isi[] = $suka;
    $isi[] = $suka;
    $isi[] = $suka;
}

$where = 'WHERE '.implode(' AND ', $syarat);

list($limit, $offset) = batas_halaman();

$hitung = $pdo->prepare("SELECT COUNT(*) {$gabung} {$where}");
$hitung->execute($isi);
$total = (int)$hitung->fetchColumn();

$stmt = $pdo->prepare("SELECT {$kolom} {$gabung} {$where}
    ORDER BY a.kode_unit, a.nama_aset, a.kode_aset LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($isi);
$baris = $stmt->fetchAll();

foreach ($baris as &$satu) {
    $satu['tautan_qr'] = $basis.'/aset-info.php?kode='.rawurlencode($satu['kode_aset']);
}

balas([
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'berikutnya' => tautan_berikutnya('api-aset.php', $limit, $offset, $total),
    'data' => $baris,
]);
