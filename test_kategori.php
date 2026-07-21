<?php
$_SERVER['HTTP_HOST'] = 'localhost:7000';
$_SERVER['REQUEST_URI'] = '/mlite_rsns/admin/logistik_non_medis/displaymasterkategoriaset';
$_SERVER['SCRIPT_NAME'] = '/mlite_rsns/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PATH_INFO'] = '/admin/logistik_non_medis/displaymasterkategoriaset';
// Set session so we don't redirect to login!
$_SESSION['mlite_user'] = 'admin'; // just guess the session key

ob_start();
try {
    require 'index.php';
} catch (Throwable $e) {
    echo "ERROR CATCH: " . $e->getMessage();
}
$output = ob_get_clean();
echo "OUTPUT:\n" . $output;
