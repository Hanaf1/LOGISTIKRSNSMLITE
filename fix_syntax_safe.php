<?php
$lines = file('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/js/admin/logistik.js');
foreach($lines as $i => $line) {
    if (trim($line) === '}') {
        // Let's check if the previous lines are empty and next lines are the if length > 0
        if (strpos($lines[$i+2], "$('#table-master-barang').length > 0") !== false) {
            unset($lines[$i]);
            break;
        }
    }
}
file_put_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/js/admin/logistik.js', implode("", $lines));
echo "Syntax fixed safely!\n";
