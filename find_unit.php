<?php
$c = file_get_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/Admin.php');
preg_match_all('/function\s+([a-zA-Z0-9_]+)/', $c, $m);
foreach($m[1] as $k) {
    if(stripos($k, 'unit') !== false) echo $k . "\n";
}
