<?php
$db = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->query("SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_aset WHERE status = 'Aktif'");
echo "Total Active Assets (Aktif): " . $stmt->fetchColumn() . "\n";

$stmt2 = $db->query("SELECT status, COUNT(*) as n FROM rsns_custom_logistik_non_medis_aset GROUP BY status");
$rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "All status values:\n";
foreach($rows as $r) {
    echo "  " . $r['status'] . " => " . $r['n'] . "\n";
}
