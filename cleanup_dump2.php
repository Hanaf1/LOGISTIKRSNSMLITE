<?php
$db = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables_to_truncate = [
    // Aset Transactions (Mutasi, Pemeliharaan, dll)
    'rsns_custom_logistik_non_medis_aset_mutasi',
    'rsns_custom_logistik_non_medis_aset_pemeliharaan',
    'rsns_custom_logistik_non_medis_aset_penghapusan',
    'rsns_custom_logistik_non_medis_aset_penyusutan',
    'rsns_custom_logistik_non_medis_aset_sensus',

    // Other Transaction logs
    'rsns_custom_logistik_non_medis_barang_rusak',
    'rsns_custom_logistik_non_medis_serah_terima',
    'rsns_custom_logistik_non_medis_kartu_stok',
    'rsns_custom_logistik_non_medis_packing',
    'rsns_custom_logistik_non_medis_pengiriman',
    'rsns_custom_logistik_non_medis_retur_unit',
    
    // Perencanaan
    'rsns_custom_logistik_non_medis_perencanaan',
    'rsns_custom_logistik_non_medis_rencana_nonrutin',
    'rsns_custom_logistik_non_medis_rencana_nonrutin_detail',
    'rsns_custom_logistik_non_medis_rencana_rutin',
    'rsns_custom_logistik_non_medis_rencana_rutin_detail',
    'rsns_custom_logistik_non_medis_terima_rutin',
    'rsns_custom_logistik_non_medis_terima_rutin_detail'
];

foreach ($tables_to_truncate as $table) {
    try {
        $db->exec("TRUNCATE TABLE `$table`");
        echo "Truncated $table\n";
    } catch (Exception $e) {
        echo "Error truncating $table: " . $e->getMessage() . "\n";
    }
}

echo "Proses pembersihan dump data transaksi Mutasi Aset, Perencanaan, dll selesai.\n";
