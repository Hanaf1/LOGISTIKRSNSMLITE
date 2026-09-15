<?php
// Integration tests create/drop only a randomly named codex_inventory_test_* database.
if (PHP_SAPI !== 'cli') exit;
require_once dirname(__DIR__) . '/InventarisClassificationMigration.php';
require_once dirname(__DIR__, 3) . '/systems/BaseModule.php';
require_once dirname(__DIR__, 3) . '/systems/AdminModule.php';
require_once dirname(__DIR__, 3) . '/systems/lib/QueryWrapper.php';
require_once dirname(__DIR__) . '/Admin.php';

use Plugins\Logistik_non_medis\InventarisClassification as Policy;
use Plugins\Logistik_non_medis\InventarisClassificationMigration as Migration;
use Systems\Lib\QueryWrapper;

class ClassificationTestAdmin extends Plugins\Logistik_non_medis\Admin
{
    public function __construct()
    {
        $this->core = new class {
            public function getUserInfo(...$args) { return 'test'; }
            public function setNoJurnal() { return 'TEST-JOURNAL'; }
        };
    }
    protected function db($table = null) { return new QueryWrapper($table); }
    protected function draw($file, array $variables = []) { return json_encode($variables); }
}
function check($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
function invokePrivate($admin, $name, ...$args) {
    $method = new ReflectionMethod(Plugins\Logistik_non_medis\Admin::class, $name);
    $method->setAccessible(true);
    return $method->invokeArgs($admin, $args);
}
foreach ([[999999,Policy::NON_ASSET],[1000001,Policy::ASSET],[1000000,Policy::UNKNOWN],
    [0,Policy::UNKNOWN],[-1,Policy::UNKNOWN],[null,Policy::UNKNOWN],[INF,Policy::UNKNOWN],
    [350000,Policy::NON_ASSET]] as [$price,$expected]) {
    check(Policy::fromPrice($price) === $expected, 'Price boundary classification failed');
}

$serverDsn = getenv('INVENTARIS_TEST_DSN') ?: 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$user = getenv('INVENTARIS_TEST_USER') ?: 'root';
$password = getenv('INVENTARIS_TEST_PASSWORD') ?: '';
$child = $argv[1] ?? '';
$database = $child ? ($argv[2] ?? '') : 'codex_inventory_test_' . bin2hex(random_bytes(5));
check((bool)preg_match('/^codex_inventory_test_[a-f0-9]{10}$/D', $database), 'Invalid test database');
check(stripos($serverDsn, 'dbname=') === false, 'Test DSN must omit dbname');
define('UPLOADS', sys_get_temp_dir() . '/codex-inventory-tests');
if (!is_dir(UPLOADS)) mkdir(UPLOADS, 0700, true);

if ($child) {
    QueryWrapper::connect($serverDsn . ';dbname=' . $database, $user, $password, ['error_mode'=>PDO::ERRMODE_EXCEPTION]);
    $_POST = json_decode($argv[3] ?? '{}', true);
    $admin = new ClassificationTestAdmin();
    $admin->$child();
    exit;
}
$server = new PDO($serverDsn, $user, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4");
try {
    QueryWrapper::connect($serverDsn . ';dbname=' . $database, $user, $password, ['error_mode'=>PDO::ERRMODE_EXCEPTION]);
    $pdo = QueryWrapper::pdo();
    $schema = file_get_contents(dirname(__DIR__) . '/schemanew.sql');
    preg_match_all('/CREATE TABLE `rsns_custom_logistik_non_medis_[^`]+` \(.*?\n\) ENGINE[^;]+;/s', $schema, $matches);
    check(count($matches[0]) > 20, 'Fixture schema unavailable');
    foreach ($matches[0] as $ddl) $pdo->exec($ddl);
    $pdo->exec('CREATE TABLE mlite_settings (id INT AUTO_INCREMENT PRIMARY KEY, module VARCHAR(100), field VARCHAR(100), value TEXT)');
    $pdo->exec('CREATE TABLE mlite_rekening (kd_rek VARCHAR(50) PRIMARY KEY)');
    $pdo->exec('CREATE TABLE mlite_jurnal (no_jurnal VARCHAR(100), no_bukti VARCHAR(100), tgl_jurnal DATE, jenis VARCHAR(20), keterangan TEXT)');
    $pdo->exec('CREATE TABLE mlite_detailjurnal (no_jurnal VARCHAR(100), kd_rek VARCHAR(50), debet DOUBLE, kredit DOUBLE)');
    $pdo->exec('CREATE TABLE rsns_custom_hostsname_pc (ip VARCHAR(50), hostname VARCHAR(100))');
    $pdo->exec('CREATE TABLE mlite_tracksql (log_id INT AUTO_INCREMENT PRIMARY KEY, log_modul VARCHAR(100), log_waktu DATETIME, log_location TEXT, log_data TEXT, log_status VARCHAR(10), log_username VARCHAR(100))');
    $admin = new ClassificationTestAdmin();
    invokePrivate($admin, '_initPenyusutan');
    Policy::ensureSchema($pdo); // repeat migration must be safe
    $insert = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_aset
        (kode_aset,kode_item,nama_aset,harga_beli,nilai_buku,klasifikasi_pencatatan,kib_jenis,masa_manfaat_tahun,user_input,kode_unit)
        VALUES (?, '010100', ?, ?, ?, ?, ?, 5, ?, '01')");
    foreach ([['LOW',999999,Policy::UNKNOWN,'B','manual'],['HIGH',1200000,Policy::UNKNOWN,'B','manual'],
        ['EXACT',1000000,Policy::UNKNOWN,'B','manual'],['ZERO',0,Policy::UNKNOWN,'B','manual'],
        ['IMPORTED',2000000,Policy::UNKNOWN,'B','import_xlsx'],['LAND',3000000,Policy::ASSET,'A','manual'],
        ['DEPRECIATED',2000000,Policy::ASSET,'B','manual'],['LEGACY-HISTORY',500000,Policy::UNKNOWN,'B','manual']] as $r) {
        $insert->execute([$r[0],$r[0],$r[1],$r[1],$r[2],$r[3],$r[4]]);
    }
    $pdo->exec("UPDATE rsns_custom_logistik_non_medis_aset SET akumulasi_penyusutan=1500000,nilai_buku=500000 WHERE kode_aset='DEPRECIATED'");
    $pdo->exec("INSERT INTO rsns_custom_logistik_non_medis_aset_penyusutan (kode_aset,periode,tanggal_proses,user_proses) VALUES ('LEGACY-HISTORY','2025-01',NOW(),'test')");
    $plan = Migration::propose(Migration::snapshot($pdo));
    $backup = sys_get_temp_dir() . '/' . $database . '.json';
    check(Migration::apply($pdo, $plan, $backup) === 2, 'Migration should classify only two reviewed candidates');
    check(is_file($backup), 'Backup missing');
    $byCode = [];
    foreach (Migration::snapshot($pdo) as $r) $byCode[$r['kode_aset']] = $r;
    check($byCode['LOW']['klasifikasi_pencatatan'] === Policy::NON_ASSET, 'Low-price inventory lost');
    check($byCode['IMPORTED']['klasifikasi_pencatatan'] === Policy::UNKNOWN, 'Import must remain unreviewed');
    check($byCode['LEGACY-HISTORY']['klasifikasi_pencatatan'] === Policy::UNKNOWN, 'History must require reconciliation');
    $failed = false;
    try { Migration::apply($pdo, $plan, $backup . '.stale'); } catch (RuntimeException $e) { $failed = true; }
    check($failed, 'Stale migration accepted');
    $preview = invokePrivate($admin, '_hitungDataPenyusutan', '2026-09', '', '');
    check(count($preview['data']) === 2, 'Preview included non-asset, unknown, or land');
    check($byCode['DEPRECIATED']['klasifikasi_pencatatan'] === Policy::ASSET, 'Book value changed classification');
    $failed = false;
    try {
        invokePrivate($admin, '_updateInventarisClassified', $byCode['DEPRECIATED'], ['harga_beli'=>350000, 'klasifikasi_pencatatan'=>Policy::NON_ASSET]);
    } catch (RuntimeException $e) { $failed = true; }
    check($failed, 'Depreciated asset reclassified without reconciliation');

    function route($method, array $post = []) {
        global $database;
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, $method, $database, json_encode($post)], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $status = proc_close($process);
        check($status === 0 && $err === '', 'Route failed: ' . $method . ' ' . $err);
        $json = json_decode($out, true);
        check(is_array($json), 'Invalid JSON from ' . $method . ': ' . $out);
        return $json;
    }
    $kpi = route('anyGetLaporanAsetKpi');
    check($kpi['total_unit'] === 3 && (float)$kpi['total_nilai'] === 6200000.0, 'Capital KPI includes non-assets');
    $list = route('anyDisplayAsetRegistrasi', ['filter_klasifikasi'=>Policy::NON_ASSET]);
    check($list['jumlah'] === 1, 'Register filter failed');
    $inventory = route('anyDisplayLaporanInventaris', ['filter_klasifikasi'=>Policy::NON_ASSET]);
    check($inventory['jumlah_data'] === 1, 'Unit inventory filter failed');
    $pdo->exec("INSERT INTO mlite_rekening VALUES ('EXP'),('ACC')");
    $pdo->exec("UPDATE mlite_settings SET value='EXP' WHERE field='depr_rek_beban_B'");
    $pdo->exec("UPDATE mlite_settings SET value='ACC' WHERE field='depr_rek_akum_B'");
    $result = route('postProsesPenyusutan', ['bulan'=>'09','tahun'=>'2026']);
    check($result['status'] === 'success', 'Depreciation posting failed: ' . json_encode($result));
    $posted = $pdo->query("SELECT kode_aset FROM rsns_custom_logistik_non_medis_aset_penyusutan WHERE periode='2026-09' ORDER BY kode_aset")->fetchAll(PDO::FETCH_COLUMN);
    check($posted === ['DEPRECIATED','HIGH'], 'Posting included non-asset or unreviewed inventory');
    check((int)$pdo->query("SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_aset_penyusutan WHERE periode='2025-01'")->fetchColumn() === 1, 'Historical depreciation was modified');
    $pdo->exec("INSERT INTO rsns_custom_logistik_non_medis_inventaris_master (jenis_master,kode,kode_inventaris,kode_kategori,nama,kode_kelompok,kode_jenis,kode_barang) VALUES
        ('UNIT','01','01','','Test Unit',NULL,NULL,NULL), ('BARANG','010100',NULL,'2','Test Chair','01','01','00')");
    $save = route('postSaveAsetRegistrasi', ['kode_unit'=>'01','kode_item'=>'010100','kode_kategori_aset'=>'2',
        'nama_aset'=>'Test Chair','harga_beli'=>'350000','jumlah'=>10,'klasifikasi_pencatatan'=>'ASET']);
    check($save['status'] === 'success', 'Batch registration failed: ' . json_encode($save));
    check((int)$pdo->query("SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_aset WHERE nama_aset='Test Chair' AND klasifikasi_pencatatan='INVENTARIS_NON_ASET'")->fetchColumn() === 10, 'Batch uses total price or trusts posted classification');
    $fill = route('postUpdateHargaAset', ['id'=>$byCode['ZERO']['id'],'harga'=>'750000']);
    check($fill['status'] === 'success', 'Fill price failed: ' . json_encode($fill));
    check($pdo->query("SELECT klasifikasi_pencatatan FROM rsns_custom_logistik_non_medis_aset WHERE kode_aset='ZERO'")->fetchColumn() === Policy::NON_ASSET, 'Fill price did not classify');
    $edit = route('postSaveAsetRegistrasi', ['id'=>$byCode['IMPORTED']['id'], 'kode_unit'=>'01', 'kode_item'=>'010100',
        'kode_kategori_aset'=>'2', 'nama_aset'=>'IMPORTED', 'harga_beli'=>'2000000']);
    check($edit['status'] === 'success', 'Metadata edit failed: ' . json_encode($edit));
    check($pdo->query("SELECT klasifikasi_pencatatan FROM rsns_custom_logistik_non_medis_aset WHERE kode_aset='IMPORTED'")->fetchColumn() === Policy::UNKNOWN, 'Metadata edit classified legacy price');
    $confirm = route('postSaveAsetRegistrasi', ['id'=>$byCode['IMPORTED']['id'], 'kode_unit'=>'01', 'kode_item'=>'010100',
        'kode_kategori_aset'=>'2', 'nama_aset'=>'IMPORTED', 'harga_beli'=>'2000000',
        'konfirmasi_harga_perolehan'=>'1', 'alasan_klasifikasi'=>'Validated acquisition invoice']);
    check($confirm['status'] === 'success', 'Validated classification failed: ' . json_encode($confirm));
    check($pdo->query("SELECT klasifikasi_pencatatan FROM rsns_custom_logistik_non_medis_aset WHERE kode_aset='IMPORTED'")->fetchColumn() === Policy::ASSET, 'Confirmation did not classify');
    echo "PASS: boundaries, migration/backup/stale guard, history protection, inventory filter, capital KPI, preview/posting, batch registration, price completion and legacy price confirmation.\n";
} finally {
    QueryWrapper::close();
    $server->exec("DROP DATABASE `{$database}`");
    if (isset($backup) && is_file($backup)) unlink($backup);
}
