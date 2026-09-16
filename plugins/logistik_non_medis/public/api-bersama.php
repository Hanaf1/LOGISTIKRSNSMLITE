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
