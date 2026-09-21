<?php
define('BASE_DIR', dirname(__DIR__, 3));
require_once __DIR__.'/../../../config.php';

$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = preg_replace('~/plugins/logistik_non_medis/public/[^/]+$~', '', $script);
$id = max(0, (int)($_GET['id'] ?? 0));
if ($id < 1) {
    http_response_code(400);
    exit('Ruangan tidak valid.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    session_name('mlite');
    session_set_cookie_params(0, rtrim((string)$basePath, '/').'/');
    session_start();
}

$target = rtrim((string)$basePath, '/')
    . '/'.trim(ADMIN, '/').'/logistik_non_medis/cekasetruang/'.$id.'/check';
if (!empty($_SESSION['token'])) {
    $target .= '?t='.rawurlencode((string)$_SESSION['token']);
}

header('Cache-Control: no-store, private');
header('Location: '.$target, true, 302);
exit();
