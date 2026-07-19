<?php
$c = file_get_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/Admin.php');
$p = strpos($c, 'private function _initUnit');
echo substr($c, $p, 800);
