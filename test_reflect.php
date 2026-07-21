<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require 'index.php'; // Boot mLITE

require_once 'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php';
$ref = new ReflectionClass('\Plugins\Logistik_non_medis\Admin');
echo "Methods:\n";
foreach($ref->getMethods() as $method) {
    if (strpos($method->getName(), 'masterkategori') !== false) {
        echo "- " . $method->getName() . "\n";
    }
}
