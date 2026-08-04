<?php
$db = new PDO('mysql:host=localhost;dbname=mlite_rsns', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Test 1: Simple count
$stmt = $db->query("SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_aset WHERE status = 'Aktif'");
echo "Total Aktif: " . $stmt->fetchColumn() . "\n";

// Test 2: Simple SELECT LIMIT 10 without JOIN
$stmt = $db->query("SELECT id, kode_aset, nama_aset, kode_item FROM rsns_custom_logistik_non_medis_aset WHERE status = 'Aktif' ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Simple LIMIT 10 (no JOIN): " . count($rows) . " rows\n";
foreach ($rows as $r) {
    echo "  id={$r['id']} kode_item=[{$r['kode_item']}] nama=[{$r['nama_aset']}]\n";
}

// Test 3: With the LEFT JOIN we used in the new code
$stmt = $db->query("
    SELECT COUNT(DISTINCT a.id)
    FROM rsns_custom_logistik_non_medis_aset a
    LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
      ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
    LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master im
      ON im.jenis_master = 'BARANG' AND im.kode = a.kode_item
    WHERE a.status = 'Aktif'
");
echo "Count with LEFT JOIN: " . $stmt->fetchColumn() . "\n";

// Test 4: With JOIN and LIMIT
$stmt = $db->query("
    SELECT a.id, a.kode_aset, a.nama_aset, a.kode_item
    FROM rsns_custom_logistik_non_medis_aset a
    LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
      ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
    LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master im
      ON im.jenis_master = 'BARANG' AND im.kode = a.kode_item
    WHERE a.status = 'Aktif'
    GROUP BY a.id
    ORDER BY a.id DESC
    LIMIT 10 OFFSET 0
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "With JOIN + GROUP BY + LIMIT 10: " . count($rows) . " rows\n";
foreach ($rows as $r) {
    echo "  id={$r['id']} kode_item=[{$r['kode_item']}] nama=[{$r['nama_aset']}]\n";
}
