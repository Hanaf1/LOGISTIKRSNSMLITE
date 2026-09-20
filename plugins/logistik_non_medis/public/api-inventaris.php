<?php
/**
 * REST inventaris: GET daftar/referensi, POST atau PATCH untuk mengubah kondisi.
 *
 * GET — daftar inventaris aktif (dipaginasi)
 *   api-inventaris.php                              halaman 1, 100 barang
 *   api-inventaris.php?page=2&per_page=50           halaman berikutnya (per_page maks 500)
 *   api-inventaris.php?limit=500&offset=500         gaya lama, tetap didukung
 *   api-inventaris.php?area=UNT-2026070016          semua ruangan dalam area (mis. Marwa)
 *   api-inventaris.php?unit=72   (alias ?ruangan=72) satu ruangan; boleh beberapa: ?unit=72,73
 *   api-inventaris.php?kondisi=Rusak Ringan         saring kondisi
 *   api-inventaris.php?klasifikasi=ASET             ASET / INVENTARIS_NON_ASET
 *   api-inventaris.php?q=kipas                      cari nama / kode / nomor inventaris / merk
 *
 * GET — satu barang & referensi
 *   api-inventaris.php?kode=AST-722021300001[&riwayat=1]   satu barang (+ riwayat kondisi)
 *   api-inventaris.php?daftar=area                          daftar area + jumlah ruangan & barang
 *   api-inventaris.php?daftar=ruangan[&area=UNT-...]        daftar ruangan + jumlah per kondisi
 *
 * POST / PATCH — ubah kondisi (wajib kunci API)
 *   Satu barang : {"kode_aset":"AST-...", "kondisi":"Rusak Ringan", "catatan":"...", "petugas":"budi"}
 *                 (boleh nomor_inventaris sebagai ganti kode_aset)
 *   Banyak      : {"items":[{"kode_aset":"AST-...","kondisi":"Baik","catatan":"..."}, ...], "petugas":"budi"}
 *                 maks 200 barang, satu transaksi; hasil per barang: diubah / tidak_berubah / tidak_ditemukan / tidak_valid
 *   Setiap perubahan dicatat di rsns_custom_logistik_non_medis_aset_riwayat_kondisi (sumber "api").
 *
 * Akses:
 *   GET  dari jaringan lokal rumah sakit, atau dari mana saja dengan kunci API.
 *   POST/PATCH selalu wajib kunci API (header X-API-Key atau Authorization: Bearer).
 */

require_once(__DIR__.'/api-bersama.php');

const KONDISI_SAH = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
const BATCH_MAKS = 200;

$metode = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$kolom = "a.kode_aset, a.nomor_inventaris, a.nama_aset, a.merk_type,
          a.kode_unit, COALESCE(iu.nama, u.nama_unit, a.kode_unit) AS nama_unit,
          NULLIF(iu.kode_area, '') AS kode_area, ua.nama_unit AS nama_area,
          a.lokasi_fisik, a.status_kondisi AS kondisi, a.tahun_beli, a.klasifikasi_pencatatan";
$gabung = "FROM rsns_custom_logistik_non_medis_aset a
           LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
             ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
           LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = a.kode_unit
           LEFT JOIN rsns_custom_logistik_non_medis_unit ua ON ua.kode_unit = iu.kode_area";

// ---------------------------------------------------------------------------
// POST / PATCH: ubah kondisi satu atau banyak barang.
// ---------------------------------------------------------------------------
if ($metode === 'POST' || $metode === 'PATCH') {
    tolak_bila_kunci_salah();
    $pdo = koneksi();
    siapkan_skema_inventaris($pdo);
    $data = masukan();
    $petugas = mb_substr((string) isian($data, 'petugas'), 0, 100) ?: 'api';

    $batch = isset($data['items']);
    $daftar = $batch ? $data['items'] : [$data];
    if (!is_array($daftar) || !$daftar) {
        balas(['error' => 'items harus berupa daftar barang.'], 422);
    }
    if (count($daftar) > BATCH_MAKS) {
        balas(['error' => 'Maksimal '.BATCH_MAKS.' barang per permintaan.'], 422);
    }

    $cari = $pdo->prepare("SELECT id, kode_aset, status_kondisi FROM rsns_custom_logistik_non_medis_aset
        WHERE status = 'Aktif' AND (kode_aset = ? OR (? = '' AND nomor_inventaris = ?)) LIMIT 1 FOR UPDATE");
    $ubah = $pdo->prepare('UPDATE rsns_custom_logistik_non_medis_aset SET status_kondisi = ? WHERE id = ?');
    $catat = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_aset_riwayat_kondisi
        (kode_aset, kondisi_lama, kondisi_baru, sumber, ref, catatan, username, waktu) VALUES (?,?,?,'api','api',?,?,NOW())");

    $hasil = [];
    $pdo->beginTransaction();
    try {
        foreach ($daftar as $i => $baris) {
            $baris = is_array($baris) ? $baris : [];
            $kode = (string) isian($baris, 'kode_aset');
            $nomor = (string) isian($baris, 'nomor_inventaris');
            $kondisi = (string) isian($baris, 'kondisi');
            $catatan = mb_substr((string) isian($baris, 'catatan'), 0, 1000);
            $ringkas = ['urutan' => $i, 'kode_aset' => $kode !== '' ? $kode : null, 'nomor_inventaris' => $nomor !== '' ? $nomor : null];

            if (($kode === '' && $nomor === '') || !in_array($kondisi, KONDISI_SAH, true)) {
                $hasil[] = $ringkas + ['status' => 'tidak_valid',
                    'error' => 'kode_aset/nomor_inventaris dan kondisi ('.implode(', ', KONDISI_SAH).') wajib diisi.'];
                continue;
            }
            $cari->execute([$kode, $kode, $nomor]);
            $aset = $cari->fetch();
            if (!$aset) {
                $hasil[] = $ringkas + ['status' => 'tidak_ditemukan'];
                continue;
            }
            $ringkas['kode_aset'] = $aset['kode_aset'];
            if ($aset['status_kondisi'] === $kondisi) {
                $hasil[] = $ringkas + ['status' => 'tidak_berubah', 'kondisi' => $kondisi];
                continue;
            }
            $ubah->execute([$kondisi, $aset['id']]);
            $catat->execute([$aset['kode_aset'], $aset['status_kondisi'], $kondisi, $catatan !== '' ? $catatan : null, $petugas]);
            $hasil[] = $ringkas + ['status' => 'diubah', 'kondisi_sebelumnya' => $aset['status_kondisi'], 'kondisi' => $kondisi];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        balas(['error' => 'Perubahan gagal disimpan, tidak ada yang diubah.'], 500);
    }

    if (!$batch) {
        // Bentuk balasan satu barang dipertahankan seperti versi sebelumnya.
        $h = $hasil[0];
        if ($h['status'] === 'tidak_valid') {
            balas(['error' => $h['error']], 422);
        }
        if ($h['status'] === 'tidak_ditemukan') {
            balas(['error' => 'Barang aktif tidak ditemukan.'], 404);
        }
        $stmt = $pdo->prepare("SELECT {$kolom} {$gabung} WHERE a.kode_aset = ?");
        $stmt->execute([$h['kode_aset']]);
        balas([
            'pesan' => $h['status'] === 'diubah' ? 'Kondisi diperbarui.' : 'Kondisi tidak berubah.',
            'kondisi_sebelumnya' => $h['kondisi_sebelumnya'] ?? $h['kondisi'],
            'data' => $stmt->fetch(),
        ]);
    }

    $jumlah = array_count_values(array_column($hasil, 'status'));
    balas(['ringkasan' => $jumlah + ['total' => count($hasil)], 'hasil' => $hasil]);
}

if ($metode !== 'GET') {
    header('Allow: GET, POST, PATCH');
    balas(['error' => 'Metode tidak didukung. Gunakan GET, POST, atau PATCH.'], 405);
}

// ---------------------------------------------------------------------------
// GET
// ---------------------------------------------------------------------------
if (!kunci_api_sah()) {
    tolak_bila_bukan_lokal();
}
$pdo = koneksi();
siapkan_skema_inventaris($pdo);

// Referensi area dan ruangan.
$daftarRef = trim($_GET['daftar'] ?? '');
if ($daftarRef === 'area') {
    $rows = $pdo->query("SELECT x.kode_area, COALESCE(u.nama_unit, 'Tanpa area') AS nama_area,
            COUNT(*) AS jumlah_ruangan, SUM(x.jumlah_barang) AS jumlah_barang
        FROM (SELECT COALESCE(m.kode_area, '') AS kode_area,
                (SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_aset a WHERE a.kode_unit = m.kode AND a.status = 'Aktif') AS jumlah_barang
              FROM rsns_custom_logistik_non_medis_inventaris_master m
              WHERE m.jenis_master = 'UNIT' AND m.status = 'Aktif') x
        LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = x.kode_area
        GROUP BY x.kode_area, u.nama_unit
        ORDER BY x.kode_area = '', u.nama_unit")->fetchAll();
    foreach ($rows as &$r) {
        $r['jumlah_ruangan'] = (int) $r['jumlah_ruangan'];
        $r['jumlah_barang'] = (int) $r['jumlah_barang'];
    }
    unset($r);
    balas(['total' => count($rows), 'data' => $rows]);
}
if ($daftarRef === 'ruangan') {
    $syaratRuang = "m.jenis_master = 'UNIT' AND m.status = 'Aktif'";
    $isiRuang = [];
    if (($area = trim($_GET['area'] ?? '')) !== '') {
        $syaratRuang .= $area === '-' ? " AND COALESCE(m.kode_area,'') = ''" : ' AND m.kode_area = ?';
        if ($area !== '-') {
            $isiRuang[] = $area;
        }
    }
    $stmt = $pdo->prepare("SELECT m.kode AS kode_unit, m.nama AS nama_unit, NULLIF(m.kode_area,'') AS kode_area, u.nama_unit AS nama_area,
            COUNT(a.id) AS jumlah_barang,
            SUM(a.status_kondisi = 'Baik') AS baik, SUM(a.status_kondisi = 'Rusak Ringan') AS rusak_ringan,
            SUM(a.status_kondisi = 'Rusak Berat') AS rusak_berat
        FROM rsns_custom_logistik_non_medis_inventaris_master m
        LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = m.kode_area
        LEFT JOIN rsns_custom_logistik_non_medis_aset a ON a.kode_unit = m.kode AND a.status = 'Aktif'
        WHERE {$syaratRuang}
        GROUP BY m.kode, m.nama, m.kode_area, u.nama_unit
        ORDER BY u.nama_unit IS NULL, u.nama_unit, LENGTH(m.nama), m.nama");
    $stmt->execute($isiRuang);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        foreach (['jumlah_barang', 'baik', 'rusak_ringan', 'rusak_berat'] as $k) {
            $r[$k] = (int) $r[$k];
        }
    }
    unset($r);
    balas(['total' => count($rows), 'data' => $rows]);
}
if ($daftarRef !== '') {
    balas(['error' => 'daftar hanya boleh "area" atau "ruangan".'], 400);
}

// Satu barang.
if (($kode = trim($_GET['kode'] ?? '')) !== '') {
    $stmt = $pdo->prepare("SELECT {$kolom} {$gabung} WHERE a.status = 'Aktif' AND a.kode_aset = ? LIMIT 1");
    $stmt->execute([$kode]);
    $aset = $stmt->fetch();
    if (!$aset) {
        balas(['error' => 'Barang aktif tidak ditemukan.'], 404);
    }
    $balasan = ['data' => $aset];
    if (!empty($_GET['riwayat'])) {
        $riwayat = $pdo->prepare("SELECT kondisi_lama, kondisi_baru, sumber, ref, catatan, username, waktu
            FROM rsns_custom_logistik_non_medis_aset_riwayat_kondisi WHERE kode_aset = ? ORDER BY waktu DESC, id DESC LIMIT 100");
        $riwayat->execute([$kode]);
        $balasan['riwayat_kondisi'] = $riwayat->fetchAll();
    }
    balas($balasan);
}

// Daftar.
$syarat = ["a.status = 'Aktif'"];
$isi = [];
$unit = trim($_GET['unit'] ?? ($_GET['ruangan'] ?? ''));
if ($unit !== '') {
    $kodeUnit = array_values(array_filter(array_map('trim', explode(',', $unit)), 'strlen'));
    $syarat[] = 'a.kode_unit IN ('.implode(',', array_fill(0, count($kodeUnit), '?')).')';
    array_push($isi, ...$kodeUnit);
}
if (($area = trim($_GET['area'] ?? '')) !== '') {
    if ($area === '-') {
        $syarat[] = "COALESCE(iu.kode_area, '') = ''";
    } else {
        $syarat[] = 'iu.kode_area = ?';
        $isi[] = $area;
    }
}
if (($kondisi = trim($_GET['kondisi'] ?? '')) !== '') {
    if (!in_array($kondisi, KONDISI_SAH, true)) {
        balas(['error' => 'Kondisi tidak dikenal. Pilih: '.implode(', ', KONDISI_SAH).'.'], 400);
    }
    $syarat[] = 'a.status_kondisi = ?';
    $isi[] = $kondisi;
}
if (($klasifikasi = trim($_GET['klasifikasi'] ?? '')) !== '') {
    $syarat[] = 'a.klasifikasi_pencatatan = ?';
    $isi[] = $klasifikasi;
}
if (($cari = trim($_GET['q'] ?? '')) !== '') {
    $syarat[] = '(a.nama_aset LIKE ? OR a.kode_aset LIKE ? OR a.nomor_inventaris LIKE ? OR a.merk_type LIKE ?)';
    array_push($isi, '%'.$cari.'%', '%'.$cari.'%', '%'.$cari.'%', '%'.$cari.'%');
}
$where = 'WHERE '.implode(' AND ', $syarat);

if (isset($_GET['page']) || isset($_GET['per_page'])) {
    $limit = max(1, min((int)($_GET['per_page'] ?? 100), 500));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;
} else {
    list($limit, $offset) = batas_halaman();
    $page = intdiv($offset, $limit) + 1;
}

$hitung = $pdo->prepare("SELECT COUNT(*) {$gabung} {$where}");
$hitung->execute($isi);
$total = (int) $hitung->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

$stmt = $pdo->prepare("SELECT {$kolom} {$gabung} {$where}
    ORDER BY a.kode_unit, a.nama_aset, a.kode_aset LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($isi);

$berikutnya = null;
if ($offset + $limit < $total) {
    $berikutnya = isset($_GET['page']) || isset($_GET['per_page'])
        ? basis_url().'/api-inventaris.php?'.http_build_query(array_merge($_GET, ['page' => $page + 1, 'per_page' => $limit]))
        : tautan_berikutnya('api-inventaris.php', $limit, $offset, $total);
}

balas([
    'total' => $total,
    'page' => $page,
    'per_page' => $limit,
    'total_pages' => $totalPages,
    'limit' => $limit,
    'offset' => $offset,
    'berikutnya' => $berikutnya,
    'data' => $stmt->fetchAll(),
]);
