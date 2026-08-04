<?php
$db = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("SET FOREIGN_KEY_CHECKS = 0;");

$tables_to_truncate = [
    'rsns_custom_logistik_non_medis_rencana_nonrutin',
    'rsns_custom_logistik_non_medis_rencana_rutin',
    'rsns_custom_logistik_non_medis_terima_rutin'
];

foreach ($tables_to_truncate as $table) {
    try {
        $db->exec("TRUNCATE TABLE `$table`");
        echo "Truncated $table\n";
    } catch (Exception $e) {
        echo "Error truncating $table: " . $e->getMessage() . "\n";
    }
}

$db->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "Pembersihan sisa tabel selesai.\n";
