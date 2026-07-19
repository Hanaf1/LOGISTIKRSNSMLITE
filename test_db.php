<?php
$pdo = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$query = "SELECT kode_item as id, CONCAT(kode_item, ' - ', nama_barang) as text 
          FROM rsns_custom_logistik_non_medis_master_barang 
          WHERE status = 'Aktif'";
$items = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
$json = json_encode(['results' => $items]);
if ($json === false) {
    echo "JSON Encode Error: " . json_last_error_msg() . "\n";
} else {
    echo "JSON OK: length " . strlen($json);
}
