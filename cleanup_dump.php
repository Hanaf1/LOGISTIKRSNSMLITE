<?php
$db = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables_to_truncate = [
    // SPPB
    'rsns_custom_logistik_non_medis_sppb',
    'rsns_custom_logistik_non_medis_sppb_detail',
    'rsns_custom_logistik_non_medis_sppb_log',
    'rsns_custom_logistik_non_medis_sppb_persetujuan_log',

    // Mutasi Aset & Barang
    'rsns_custom_logistik_non_medis_mutasi',
    'rsns_custom_logistik_non_medis_mutasi_detail',
    'rsns_custom_logistik_non_medis_mutasi_log',

    // Pengadaan / Penerimaan
    'rsns_custom_logistik_non_medis_penerimaan',
    'rsns_custom_logistik_non_medis_penerimaan_detail',
    'rsns_custom_logistik_non_medis_po',
    'rsns_custom_logistik_non_medis_po_detail',
    'rsns_custom_logistik_non_medis_pr',
    'rsns_custom_logistik_non_medis_pr_detail',

    // Stok / Opname
    'rsns_custom_logistik_non_medis_opname',
    'rsns_custom_logistik_non_medis_opname_detail',
    'rsns_custom_logistik_non_medis_stok_mutasi'
];

foreach ($tables_to_truncate as $table) {
    try {
        $db->exec("TRUNCATE TABLE `$table`");
        echo "Truncated $table\n";
    } catch (Exception $e) {
        echo "Error truncating $table (might not exist): " . $e->getMessage() . "\n";
    }
}

// Set stok ke 0, bukan menghapus master stok agar master barang aman
try {
    $db->exec("UPDATE rsns_custom_logistik_non_medis_stok SET stok = 0");
    echo "Stok di-reset ke 0\n";
} catch (Exception $e) {
    echo "Error resetting stok: " . $e->getMessage() . "\n";
}

echo "Proses pembersihan dump data transaksi (SPPB, Mutasi, dll) selesai. Master data aman.\n";
