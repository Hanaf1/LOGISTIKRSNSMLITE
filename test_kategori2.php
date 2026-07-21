<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require 'index.php'; // Boot mLITE

$module = new \Plugins\Logistik_non_medis\Admin();
$module->anydisplaymasterkategoriaset();
