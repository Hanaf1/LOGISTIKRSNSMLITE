<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require 'index.php'; // Boot mLITE

$admin = new \Plugins\Logistik_non_medis\Admin();
echo "Is displaymasterkategoriaset callable? " . (method_exists($admin, 'anydisplaymasterkategoriaset') ? 'YES' : 'NO') . "\n";
echo "Is getmasterkategoriaset callable? " . (method_exists($admin, 'getmasterkategoriaset') ? 'YES' : 'NO') . "\n";
