<?php
require_once __DIR__ . '/../../../init.php';
$db = new \Systems\MySQL();
$db->pdo()->exec('TRUNCATE TABLE `rsns_custom_logistik_non_medis_unit`');
echo "Data unit berhasil dikosongkan.";
