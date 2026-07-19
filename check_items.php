<?php
require 'systems/Config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->query("SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_master_barang WHERE status='Aktif'");
echo $stmt->fetchColumn();
