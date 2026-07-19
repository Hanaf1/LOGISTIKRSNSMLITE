<?php
require 'config.php';
// We need to bypass the router and manually call the function, 
// OR just include Admin.php and call it.
// Since Admin.php has class Plugins\Logistik_non_medis\Admin, we can instantiate it.
require 'plugins/logistik_non_medis/Admin.php';

// Stub out core components to prevent crash
class FakeCore {
    public function __construct() {}
}
class FakeDB {
    public $pdo;
    public function __construct() {
        $this->pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
    }
    public function pdo() { return $this->pdo; }
}

class FakeAdmin extends Plugins\Logistik_non_medis\Admin {
    public function __construct() {}
    public function db() { return new FakeDB(); }
}

$admin = new FakeAdmin();
$_GET['q'] = '';
$_GET['page'] = 1;

// Capture output
ob_start();
try {
    $admin->anyAjaxBarangSelect2();
} catch (Exception $e) {}
$out = ob_get_clean();

echo "JSON OUT:\n";
echo substr($out, 0, 500);
