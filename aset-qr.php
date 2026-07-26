<?php
define('BASE_DIR', __DIR__);
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/systems/lib/Autoloader.php');

use Systems\Lib\QRCode;

$kode = trim($_GET['kode'] ?? '');
if ($kode === '') {
    http_response_code(400);
    exit('Kode aset kosong.');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443);
$protocol = $https ? 'https://' : 'http://';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$scanUrl = $protocol.$_SERVER['HTTP_HOST'].$basePath.'/aset-info.php?kode='.rawurlencode($kode);

$qr = QRCode::getMinimumQRCode($scanUrl, QR_ERROR_CORRECT_LEVEL_M);

if (function_exists('imagepng')) {
    $image = $qr->createImage(5, 12);
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    imagepng($image);
    imagedestroy($image);
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
ob_start();
$qr->printSVG(5);
echo ob_get_clean();
