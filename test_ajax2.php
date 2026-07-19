<?php
require 'config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$query = "SELECT kode_item as id, CONCAT(kode_item, ' - ', nama_barang) as text 
          FROM rsns_custom_logistik_non_medis_master_barang 
          WHERE status = 'Aktif' 
          ORDER BY nama_barang ASC 
          ";
$items = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['results' => $items]);
