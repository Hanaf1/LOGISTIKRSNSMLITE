<?php
require '../../systems/Main.php';

$db = Systems\Lib\QueryWrapper::connect("mysql:host=".DBHOST.";port=".DBPORT.";dbname=".DBNAME."", DBUSER, DBPASS);

// 1. Create table
$sql_create = "
CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_kategori_aset` (
  `kode_kategori` varchar(50) NOT NULL,
  `nama_kategori` varchar(200) NOT NULL,
  `kib_default` enum('A','B','C','D','E','F') DEFAULT NULL,
  `umur_manfaat_default` int(11) DEFAULT '0',
  `kode_coa` varchar(50) DEFAULT NULL,
  `status_aktif` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
";
$db->pdo()->exec($sql_create);
echo "Table rsns_custom_logistik_non_medis_kategori_aset created/exists.\n";

// 2. Alter aset table
try {
    $db->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_aset` ADD COLUMN `kode_kategori_aset` varchar(50) DEFAULT NULL AFTER `kode_item`");
    echo "Column kode_kategori_aset added to aset table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column kode_kategori_aset already exists.\n";
    } else {
        echo "Error adding column: " . $e->getMessage() . "\n";
    }
}

// 3. Insert default data
$defaults = [
    ['KAT-01', 'Furnitur & Perabot', 'B', 5],
    ['KAT-02', 'Peralatan TI & Komunikasi', 'B', 4],
    ['KAT-03', 'Elektronik & Kelistrikan', 'B', 5],
    ['KAT-04', 'Peralatan Kantor & Arsip', 'B', 5],
    ['KAT-05', 'Peralatan Kebersihan & Sanitasi', 'B', 3],
    ['KAT-06', 'Peralatan Rumah Tangga / Pantry', 'B', 3],
    ['KAT-07', 'Interior & Fasilitas Ruangan', 'B', 5],
    ['KAT-08', 'Peralatan Operasional Unit', 'B', 5],
    ['KAT-09', 'Bangunan & Prasarana', 'C', 20],
    ['KAT-10', 'Kendaraan', 'B', 10],
];

$stmt = $db->pdo()->prepare("INSERT IGNORE INTO `rsns_custom_logistik_non_medis_kategori_aset` (kode_kategori, nama_kategori, kib_default, umur_manfaat_default) VALUES (?, ?, ?, ?)");
foreach ($defaults as $d) {
    $stmt->execute($d);
}
echo "Default categories inserted.\n";

// 4. Update existing assets
// We will try to map existing KIB types to these defaults if possible. 
// For now, let's map KIB B -> KAT-01, KIB C -> KAT-09 as a basic fallback if it's NULL
$db->pdo()->exec("UPDATE `rsns_custom_logistik_non_medis_aset` SET kode_kategori_aset = 'KAT-01' WHERE kib_jenis = 'B' AND kode_kategori_aset IS NULL");
$db->pdo()->exec("UPDATE `rsns_custom_logistik_non_medis_aset` SET kode_kategori_aset = 'KAT-09' WHERE kib_jenis = 'C' AND kode_kategori_aset IS NULL");
$db->pdo()->exec("UPDATE `rsns_custom_logistik_non_medis_aset` SET kode_kategori_aset = 'KAT-01' WHERE kode_kategori_aset IS NULL");
echo "Existing assets updated with fallback categories.\n";

echo "Migration complete.\n";
