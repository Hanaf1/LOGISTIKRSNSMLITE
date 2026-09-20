<?php
/**
 * Bagian yang dipakai bersama oleh api-aset.php, api-maintenance.php, dan
 * api-lapor.php. Berkas ini hanya mendefinisikan fungsi; dipanggil langsung
 * lewat browser tidak menghasilkan apa-apa.
 */

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__, 3));
}
require_once(__DIR__.'/../../../config.php');

header('Content-Type: application/json; charset=utf-8');

/**
 * Endpoint ini dibuka tanpa login, seperti halaman QR-nya. Supaya data seluruh
 * aset tidak ikut terbuka ke luar, pemanggilnya dibatasi jaringan lokal saja.
 * Kalau nanti helpdesk berada di luar jaringan ini, ganti pembatasan ini dengan
 * kunci API — jangan sekadar dilonggarkan.
 */
function dari_jaringan_lokal()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '::1' || $ip === '127.0.0.1') {
        return true;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE);
}

/**
 * Kunci API dari define('LOGISTIK_NON_MEDIS_API_KEY', '...') di config.php,
 * dikirim lewat header X-API-Key atau Authorization: Bearer. Bila kunci belum
 * diatur, endpoint yang memerlukannya tertutup — tidak pernah terbuka diam-diam.
 */
function kunci_api_sah()
{
    $kunci = defined('LOGISTIK_NON_MEDIS_API_KEY') ? (string) LOGISTIK_NON_MEDIS_API_KEY : '';
    $kiriman = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($kiriman === '' && preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '', $m)) {
        $kiriman = trim($m[1]);
    }
    return strlen($kunci) >= 32 && is_string($kiriman) && hash_equals($kunci, $kiriman);
}

function tolak_bila_kunci_salah()
{
    if (!defined('LOGISTIK_NON_MEDIS_API_KEY') || strlen((string) LOGISTIK_NON_MEDIS_API_KEY) < 32) {
        balas(['error' => 'Kunci API belum diatur di server.'], 503);
    }
    if (!kunci_api_sah()) {
        balas(['error' => 'Kunci API tidak valid.'], 401);
    }
}

function balas($data, $kode = 200)
{
    http_response_code($kode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function tolak_bila_bukan_lokal()
{
    if (!dari_jaringan_lokal()) {
        balas(['error' => 'Hanya dapat diakses dari jaringan lokal rumah sakit.'], 403);
    }
}

/**
 * Koneksi sekaligus memasang zona waktu aplikasi. Endpoint ini tidak melewati
 * systems/Main.php, jadi tanpa ini PHP memakai UTC sementara MySQL memakai jam
 * server, dan jam pada data jadi selisih jauh.
 */
function koneksi()
{
    try {
        $pdo = new PDO('mysql:host='.DBHOST.';port='.DBPORT.';dbname='.DBNAME.';charset=utf8mb4', DBUSER, DBPASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        balas(['error' => 'Koneksi data belum tersedia.'], 503);
    }
    $zona = $pdo->query("SELECT value FROM mlite_settings WHERE module='settings' AND field='timezone'")->fetchColumn();
    if ($zona) {
        date_default_timezone_set($zona);
    }
    return $pdo;
}

function basis_url()
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        .'://'.($_SERVER['HTTP_HOST'] ?? 'localhost')
        .rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}

/**
 * Isi permintaan POST/PATCH, menerima JSON maupun form biasa, supaya sisi sana
 * tidak dipaksa memakai salah satu.
 */
function masukan()
{
    $mentah = file_get_contents('php://input');
    if ($mentah !== '' && $mentah !== false) {
        $urai = json_decode($mentah, true);
        if (is_array($urai)) {
            return $urai;
        }
    }
    return $_POST ?: [];
}

function isian($data, $kunci, $bawaan = '')
{
    return is_string($data[$kunci] ?? null) ? trim($data[$kunci]) : ($data[$kunci] ?? $bawaan);
}

/** Batas halaman dipakai sama di semua endpoint daftar. */
function batas_halaman()
{
    $limit = (int)($_GET['limit'] ?? 100);
    return [max(1, min($limit, 500)), max(0, (int)($_GET['offset'] ?? 0))];
}

function tautan_berikutnya($berkas, $limit, $offset, $total)
{
    if (($offset + $limit) >= $total) {
        return null;
    }
    return basis_url().'/'.$berkas.'?'.http_build_query(array_merge($_GET, [
        'limit' => $limit,
        'offset' => $offset + $limit,
    ]));
}

/**
 * Nomor dokumen berikutnya untuk satu awalan, contoh 'QR-202609-' atau
 * 'LOG-202609-'. Dipakai bersama supaya penomoran dari API dan dari halaman QR
 * tidak pernah memakai aturan yang berbeda.
 */
function nomor_berikutnya(PDO $pdo, $awalan)
{
    $stmt = $pdo->prepare("SELECT kode_pemeliharaan FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
        WHERE kode_pemeliharaan LIKE ? ORDER BY kode_pemeliharaan DESC LIMIT 1");
    $stmt->execute([$awalan.'%']);
    $terakhir = $stmt->fetchColumn();
    $urut = $terakhir ? (int) substr($terakhir, -4) : 0;
    return $awalan.str_pad($urut + 1, 4, '0', STR_PAD_LEFT);
}

/**
 * Kolom area ruangan dan tabel riwayat kondisi. Biasanya sudah dibuat oleh
 * halaman Cek Aset Ruangan (Admin.php _initCekRuang); dibuat juga di sini
 * supaya API tetap jalan walau halaman itu belum pernah dibuka. Skema harus sama.
 */
function siapkan_skema_inventaris(PDO $pdo)
{
    if (!$pdo->query("SHOW COLUMNS FROM rsns_custom_logistik_non_medis_inventaris_master LIKE 'kode_area'")->fetch()) {
        $pdo->exec("ALTER TABLE rsns_custom_logistik_non_medis_inventaris_master ADD `kode_area` varchar(50) DEFAULT NULL AFTER `nama`, ADD KEY `idx_inventaris_area` (`kode_area`)");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_aset_riwayat_kondisi` (
      `id` int NOT NULL AUTO_INCREMENT,
      `kode_aset` varchar(100) NOT NULL,
      `kondisi_lama` varchar(20) DEFAULT NULL,
      `kondisi_baru` varchar(20) NOT NULL,
      `sumber` varchar(20) NOT NULL,
      `ref` varchar(50) DEFAULT NULL,
      `catatan` text,
      `username` varchar(100) NOT NULL,
      `waktu` datetime NOT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_riwayat_kondisi_aset` (`kode_aset`,`waktu`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Saringan unit & barang yang sama untuk daftar laporan dan buku maintenance.
 * Tabel aset harus beralias "a" dan master unit inventaris beralias "iu".
 *   area=UNT-...            semua ruangan dalam area ("-" = tanpa area)
 *   unit=44 atau 44,49      satu/beberapa ruangan
 *   kode_aset=AST-...       satu barang
 *   nomor_inventaris=...    satu barang
 *   barang=kipas            cari nama / merk barang
 */
function saring_unit_barang(PDO $pdo, array &$syarat, array &$isi)
{
    siapkan_skema_inventaris($pdo);
    if (($area = trim($_GET['area'] ?? '')) !== '') {
        if ($area === '-') {
            $syarat[] = "COALESCE(iu.kode_area, '') = ''";
        } else {
            $syarat[] = 'iu.kode_area = ?';
            $isi[] = $area;
        }
    }
    if (($unit = trim($_GET['unit'] ?? '')) !== '') {
        $kode = array_values(array_filter(array_map('trim', explode(',', $unit)), 'strlen'));
        $syarat[] = 'a.kode_unit IN ('.implode(',', array_fill(0, count($kode), '?')).')';
        array_push($isi, ...$kode);
    }
    if (($kode_aset = trim($_GET['kode_aset'] ?? '')) !== '') {
        $syarat[] = 'a.kode_aset = ?';
        $isi[] = $kode_aset;
    }
    if (($nomor = trim($_GET['nomor_inventaris'] ?? '')) !== '') {
        $syarat[] = 'a.nomor_inventaris = ?';
        $isi[] = $nomor;
    }
    if (($barang = trim($_GET['barang'] ?? '')) !== '') {
        $syarat[] = '(a.nama_aset LIKE ? OR a.merk_type LIKE ?)';
        array_push($isi, '%'.$barang.'%', '%'.$barang.'%');
    }
}

/**
 * Pilihan untuk form helpdesk: ?daftar=unit[&area=] lalu ?daftar=barang&unit=44[&barang=kipas].
 * Membalas dan berhenti bila parameter daftar dikirim; tidak melakukan apa-apa bila tidak.
 */
function balas_daftar_pilihan(PDO $pdo)
{
    $daftar = trim($_GET['daftar'] ?? '');
    if ($daftar === '') {
        return;
    }
    siapkan_skema_inventaris($pdo);
    if ($daftar === 'unit') {
        $syarat = "m.jenis_master = 'UNIT' AND m.status = 'Aktif'";
        $isi = [];
        if (($area = trim($_GET['area'] ?? '')) !== '') {
            $syarat .= $area === '-' ? " AND COALESCE(m.kode_area, '') = ''" : ' AND m.kode_area = ?';
            if ($area !== '-') {
                $isi[] = $area;
            }
        }
        $stmt = $pdo->prepare("SELECT m.kode AS kode_unit, m.nama AS nama_unit, NULLIF(m.kode_area, '') AS kode_area, u.nama_unit AS nama_area,
                (SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_aset a WHERE a.kode_unit = m.kode AND a.status = 'Aktif') AS jumlah_barang
            FROM rsns_custom_logistik_non_medis_inventaris_master m
            LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = m.kode_area
            WHERE {$syarat}
            ORDER BY u.nama_unit IS NULL, u.nama_unit, m.nama");
        $stmt->execute($isi);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['jumlah_barang'] = (int) $r['jumlah_barang'];
        }
        unset($r);
        balas(['total' => count($rows), 'data' => $rows]);
    }
    if ($daftar === 'barang') {
        $unit = trim($_GET['unit'] ?? '');
        if ($unit === '') {
            balas(['error' => 'Parameter unit wajib diisi untuk daftar barang.'], 400);
        }
        $syarat = ["a.status = 'Aktif'"];
        $isi = [];
        saring_unit_barang($pdo, $syarat, $isi);
        $stmt = $pdo->prepare("SELECT a.kode_aset, a.nomor_inventaris, a.nama_aset, a.merk_type, a.kode_unit,
                COALESCE(iu.nama, a.kode_unit) AS nama_unit, a.lokasi_fisik, a.status_kondisi AS kondisi
            FROM rsns_custom_logistik_non_medis_aset a
            LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
            WHERE ".implode(' AND ', $syarat)."
            ORDER BY a.nama_aset, a.nomor_inventaris LIMIT 1000");
        $stmt->execute($isi);
        $rows = $stmt->fetchAll();
        balas(['total' => count($rows), 'data' => $rows]);
    }
    balas(['error' => 'daftar hanya boleh "unit" atau "barang".'], 400);
}

/** Aset aktif beserta nama unitnya, atau null bila tidak ada. */
function ambil_aset(PDO $pdo, $kode_aset)
{
    $stmt = $pdo->prepare("SELECT a.kode_aset, a.nama_aset, a.nomor_inventaris, a.status,
               a.status_kondisi, a.kode_unit, a.lokasi_fisik,
               COALESCE(iu.nama, u.nama_unit, a.kode_unit) nama_unit
        FROM rsns_custom_logistik_non_medis_aset a
        LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
          ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
        LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = a.kode_unit
        WHERE a.kode_aset = ? LIMIT 1");
    $stmt->execute([$kode_aset]);
    return $stmt->fetch() ?: null;
}
