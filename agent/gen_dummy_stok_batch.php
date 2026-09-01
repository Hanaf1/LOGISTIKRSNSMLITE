<?php
$mysqli = new mysqli("localhost", "root", "", "mlite_rsns");
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);

$kode_lokasi = 'GUDANG-LOGISTIK';

$result = $mysqli->query("SELECT kode_item, harga_referensi FROM rsns_custom_logistik_non_medis_master_barang");
$count = 0;

while ($row = $result->fetch_assoc()) {
    $kode = $row['kode_item'];
    $harga = floatval($row['harga_referensi']);
    if ($harga == 0) {
        $hargas = [5000, 10000, 25000, 50000];
        $harga = $hargas[array_rand($hargas)];
    }
    
    $stok_akhir = rand(50, 500);
    $batch = "BATCH-" . rand(1000, 9999);
    
    // Delete old
    $stmt_del = $mysqli->prepare("DELETE FROM rsns_custom_logistik_non_medis_stok_batch WHERE kode_item=? AND kode_lokasi=?");
    $stmt_del->bind_param("ss", $kode, $kode_lokasi);
    $stmt_del->execute();
    
    // Insert new batch
    $tgl_terima = "2026-07-01";
    $stmt_ins = $mysqli->prepare("INSERT INTO rsns_custom_logistik_non_medis_stok_batch (kode_item, kode_lokasi, batch_no, tgl_terima, harga_beli, stok) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_ins->bind_param("ssssdi", $kode, $kode_lokasi, $batch, $tgl_terima, $harga, $stok_akhir);
    $stmt_ins->execute();
    
    // Ensure stok matches
    $stmt_stok = $mysqli->prepare("INSERT INTO rsns_custom_logistik_non_medis_stok (kode_item, kode_lokasi, stok_akhir) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stok_akhir = ?");
    $stmt_stok->bind_param("ssii", $kode, $kode_lokasi, $stok_akhir, $stok_akhir);
    $stmt_stok->execute();
    
    $count++;
}

echo "Successfully generated dummy stok_batch for $count items.\n";
$mysqli->close();
