<?php
$db = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$stmt = $db->query("SHOW TABLES LIKE 'rsns_custom_logistik_non_medis_%'");
$tables = [];
foreach($stmt->fetchAll() as $r) {
    $tables[] = $r[0];
}
echo json_encode($tables, JSON_PRETTY_PRINT);
