<?php
require 'systems/Main.php';
$db = core::db();
$q = $db->pdo()->query("SHOW TABLES LIKE '%logistik_non_medis%'");
while($r = $q->fetch(PDO::FETCH_ASSOC)) {
    print_r($r);
}
