<?php
$mysqli = new mysqli("localhost", "root", "", "mlite_rsns");
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);

$json = file_get_contents('c:/laragon/www/mlite_rsns/agent/excel_data.json');
$items = json_decode($json, true);

// Cache master items
$master_items = [];
$res = $mysqli->query("SELECT kode_item, nama_barang, satuan_dasar FROM rsns_custom_logistik_non_medis_master_barang");
while ($row = $res->fetch_assoc()) {
    $master_items[strtolower(trim($row['nama_barang']))] = $row;
}

// Generate SPPB number
$current_month = date('Ym');
$res = $mysqli->query("SELECT MAX(CAST(SUBSTRING_INDEX(no_sppb, '/', -1) AS UNSIGNED)) as max_no FROM rsns_custom_logistik_non_medis_sppb WHERE no_sppb LIKE 'SPPB/IMPORT/$current_month/%'");
$row = $res->fetch_assoc();
$next_no = ($row['max_no'] ? intval($row['max_no']) : 0);

$imported = 0;
$current_sppb = '';
$last_unit = '';
$last_tanggal = '';

foreach ($items as $idx => $item) {
    if ($last_unit != $item['kode_unit'] || $last_tanggal != $item['tanggal']) {
        $next_no++;
        $current_sppb = "SPPB/IMPORT/$current_month/" . str_pad($next_no, 3, '0', STR_PAD_LEFT);
        $last_unit = $item['kode_unit'];
        $last_tanggal = $item['tanggal'];
    }
    
    $nama_barang = strtolower(trim($item['barang']));
    $kode_item = '';
    $item_sumber = 'manual';
    $nama_barang_manual = $item['barang'];
    $satuan = 'pcs';
    
    if (isset($master_items[$nama_barang])) {
        $kode_item = $master_items[$nama_barang]['kode_item'];
        $item_sumber = 'master';
        $nama_barang_manual = '';
        $satuan = $master_items[$nama_barang]['satuan_dasar'];
    } else {
        // Try fuzzy
        foreach ($master_items as $mnama => $mdata) {
            if (strpos($mnama, $nama_barang) !== false || strpos($nama_barang, $mnama) !== false) {
                $kode_item = $mdata['kode_item'];
                $item_sumber = 'master';
                $nama_barang_manual = '';
                $satuan = $mdata['satuan_dasar'];
                break;
            }
        }
    }
    
    if (empty($kode_item)) {
        $kode_item = 'BRG-BARU'; // Use the dummy code we saw earlier
    }
    
    $stmt = $mysqli->prepare("INSERT INTO rsns_custom_logistik_non_medis_sppb 
        (no_sppb, tgl_sppb, kode_unit, jenis_permintaan, jenis_keluar, kode_item, item_sumber, nama_barang_manual, 
        jumlah, jumlah_disetujui, harga_satuan_cost, subtotal_cost, satuan, status, sifat_permintaan, tgl_input) 
        VALUES (?, ?, ?, 'Rutin', 'Rutin', ?, ?, ?, ?, ?, ?, ?, ?, 'Selesai', 'Biasa', NOW())");
        
    $stmt->bind_param("ssssssdddds", 
        $current_sppb, 
        $item['tanggal'], 
        $item['kode_unit'], 
        $kode_item, 
        $item_sumber, 
        $nama_barang_manual, 
        $item['jumlah'], 
        $item['jumlah'], 
        $item['harga'], 
        $item['total'], 
        $satuan
    );
    
    if ($stmt->execute()) {
        $imported++;
    }
}

echo "Successfully imported $imported rows into SPPB table.\n";
$mysqli->close();
