<?php

return [
    'name'          => 'Permintaan Logistik',
    'description'   => 'Permintaan dan pelacakan barang logistik non-medis untuk setiap unit.',
    'category'      => 'manajemen',
    'author'        => 'Administrator',
    'version'       => '1.0',
    'compatibility' => '5.*.*',
    'icon'          => 'shopping-cart',
    'install'       => function () use ($core) {
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_user_unit` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(100) NOT NULL,
          `kode_unit` varchar(50) NOT NULL,
          `aktif` tinyint(1) NOT NULL DEFAULT 1,
          `created_at` datetime DEFAULT NULL,
          `created_by` varchar(100) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`),
          KEY `kode_unit` (`kode_unit`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    },
    'uninstall'     => function () use ($core) {
        // Pemetaan sengaja dipertahankan agar aman ketika modul dipasang kembali.
    }
];
