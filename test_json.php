<?php
$pdo = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$query = "SELECT kode_item as id, CONCAT(kode_item, ' - ', nama_barang) as text 
          FROM rsns_custom_logistik_non_medis_master_barang 
          WHERE status = 'Aktif' LIMIT 2";
$items = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['results' => $items]);
