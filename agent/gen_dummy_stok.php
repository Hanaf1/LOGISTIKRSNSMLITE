<?php
$mysqli = new mysqli("localhost", "root", "", "mlite_rsns");
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);

// Clean up existing stock data
$mysqli->query("TRUNCATE TABLE rsns_custom_logistik_non_medis_kartu_stok");
$mysqli->query("TRUNCATE TABLE rsns_custom_logistik_non_medis_stok");

$kode_lokasi = 'GUDANG-LOGISTIK';

// Fetch items
$result = $mysqli->query("SELECT kode_item, harga_referensi FROM rsns_custom_logistik_non_medis_master_barang");
$count = 0;

while ($row = $result->fetch_assoc()) {
    $kode = $row['kode_item'];
    $harga = floatval($row['harga_referensi']);
    if ($harga == 0) {
        $hargas = [5000, 10000, 25000, 50000];
        $harga = $hargas[array_rand($hargas)];
    }
    
    // Simulate stock
    $initial_stock = rand(100, 500);
    $keluar_stock = rand(10, 80);
    $stok_akhir = $initial_stock - $keluar_stock;
    
    // 1. Masuk
    $tgl_masuk = "2026-07-01 08:00:00";
    $stmt1 = $mysqli->prepare("INSERT INTO rsns_custom_logistik_non_medis_kartu_stok (tgl_transaksi, kode_item, kode_lokasi, batch_no, tipe_transaksi, no_referensi, qty_masuk, qty_keluar, stok_akhir, harga, user_input) VALUES (?, ?, ?, '-', 'Masuk', 'SA-001', ?, 0, ?, ?, 'admin')");
    $stmt1->bind_param("sssiid", $tgl_masuk, $kode, $kode_lokasi, $initial_stock, $initial_stock, $harga);
    $stmt1->execute();
    
    // 2. Keluar
    $tgl_keluar = "2026-07-15 10:00:00";
    $stmt2 = $mysqli->prepare("INSERT INTO rsns_custom_logistik_non_medis_kartu_stok (tgl_transaksi, kode_item, kode_lokasi, batch_no, tipe_transaksi, no_referensi, qty_masuk, qty_keluar, stok_akhir, harga, user_input) VALUES (?, ?, ?, '-', 'Keluar', 'OUT-001', 0, ?, ?, ?, 'admin')");
    $stmt2->bind_param("sssiid", $tgl_keluar, $kode, $kode_lokasi, $keluar_stock, $stok_akhir, $harga);
    $stmt2->execute();
    
    // 3. Update stok
    $stmt3 = $mysqli->prepare("INSERT INTO rsns_custom_logistik_non_medis_stok (kode_item, kode_lokasi, stok_akhir) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stok_akhir = ?");
    $stmt3->bind_param("ssii", $kode, $kode_lokasi, $stok_akhir, $stok_akhir);
    $stmt3->execute();
    
    $count++;
}

echo "Successfully generated dummy stock & mutations for $count items.\n";
$mysqli->close();
