<?php
// Script to migrate kategori to kode_kategori
$host = '127.0.0.1';
$db   = 'mlite_rsns';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Adding kode_kategori to kategori table...\n";
    $pdo->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kategori` ADD COLUMN `kode_kategori` VARCHAR(50) DEFAULT NULL AFTER `id`");

    echo "Generating codes...\n";
    $stmt = $pdo->query("SELECT * FROM `rsns_custom_logistik_non_medis_kategori` ORDER BY `id` ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counter = 1;
    foreach ($categories as $cat) {
        $kode = 'KAT-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
        $pdo->exec("UPDATE `rsns_custom_logistik_non_medis_kategori` SET `kode_kategori` = '$kode' WHERE `id` = " . $cat['id']);
        $counter++;
    }

    echo "Adding kode_kategori to master_barang table...\n";
    $pdo->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD COLUMN `kode_kategori` VARCHAR(50) DEFAULT NULL AFTER `spesifikasi`");

    echo "Mapping master_barang to new codes...\n";
    foreach ($categories as $cat) {
        $kode = 'KAT-' . str_pad(array_search($cat, $categories) + 1, 3, '0', STR_PAD_LEFT);
        $nama = $pdo->quote($cat['nama_kategori']);
        $pdo->exec("UPDATE `rsns_custom_logistik_non_medis_master_barang` SET `kode_kategori` = '$kode' WHERE `kategori` = $nama");
    }

    echo "Dropping old PK and setting new PK in kategori...\n";
    $pdo->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kategori` MODIFY COLUMN `id` INT NOT NULL"); // Remove auto_increment
    $pdo->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kategori` DROP PRIMARY KEY");
    $pdo->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kategori` DROP COLUMN `id`");
    $pdo->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kategori` MODIFY COLUMN `kode_kategori` VARCHAR(50) NOT NULL PRIMARY KEY");

    echo "Dropping old kategori from master_barang...\n";
    $pdo->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` DROP COLUMN `kategori`");

    echo "Migration Complete!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
