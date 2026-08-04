<?php
// Script to generate dummy SPPB data for PPI in July 2026
define('BASE_DIR', __DIR__);
require_once __DIR__ . '/config.php';
$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Starting dummy data generation...\n";

// Get PPI items
$items = $db->query("SELECT kode_item, nama_barang, satuan FROM rsns_custom_logistik_non_medis_master_barang WHERE nama_barang LIKE '%plastik%' OR nama_barang LIKE '%kuning%' OR nama_barang LIKE '%hitam%' OR nama_barang LIKE '%tisu%'")->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    die("No PPI items found.\n");
}

// Get Units
$units = $db->query("SELECT kode_unit, nama_unit FROM rsns_custom_logistik_non_medis_unit")->fetchAll(PDO::FETCH_ASSOC);
if (empty($units)) {
    die("No units found.\n");
}

$start_date = strtotime('2026-07-01');
$end_date = strtotime('2026-07-31');

$inserted_sppb = 0;
$inserted_detail = 0;

for ($date = $start_date; $date <= $end_date; $date = strtotime('+1 day', $date)) {
    // Generate 1 to 3 SPPB per day
    $num_sppb = rand(1, 3);
    for ($i = 0; $i < $num_sppb; $i++) {
        $tgl_sppb = date('Y-m-d', $date);
        $no_sppb = "SPPB/PPI/DUMMY/" . date('Ymd', $date) . "/" . rand(100, 999);
        $unit = $units[array_rand($units)];
        
        $sql = "INSERT INTO rsns_custom_logistik_non_medis_sppb (no_sppb, tgl_sppb, kode_unit, jenis_permintaan, status, keterangan) 
                VALUES (?, ?, ?, 'Rutin', 'Selesai', 'Dummy PPI Data Juli')";
        $stmt = $db->prepare($sql);
        $stmt->execute([$no_sppb, $tgl_sppb, $unit['kode_unit']]);
        $inserted_sppb++;
        
        // Add 1 to 5 items to this SPPB
        $num_items = rand(1, 5);
        $used_items = [];
        for ($j = 0; $j < $num_items; $j++) {
            $item = $items[array_rand($items)];
            if (in_array($item['kode_item'], $used_items)) continue;
            $used_items[] = $item['kode_item'];
            
            $qty = rand(1, 20) * 10; // Qty from 10 to 200
            $sql_detail = "INSERT INTO rsns_custom_logistik_non_medis_sppb_detail (no_sppb, kode_item, qty_diminta, qty_disetujui, qty_keluar, status) 
                           VALUES (?, ?, ?, ?, ?, 'Selesai')";
            $stmt_detail = $db->prepare($sql_detail);
            $stmt_detail->execute([$no_sppb, $item['kode_item'], $qty, $qty, $qty]);
            $inserted_detail++;
        }
    }
}

echo "Successfully inserted $inserted_sppb SPPB records and $inserted_detail Detail records.\n";
