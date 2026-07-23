-- Migrasi aman fitur Hak Akses Logistik Non-Medis.
-- Jalankan pada database mlite yang sudah dipakai aplikasi.

CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'unit',
  `kode_unit` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_role_permissions` (
  `role` varchar(50) NOT NULL,
  `permissions` text DEFAULT NULL,
  PRIMARY KEY (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT IGNORE INTO `rsns_custom_logistik_non_medis_role_permissions` (`role`, `permissions`) VALUES
('admin', 'manage,hakakses'),
('logistik', 'manage'),
('gudang', 'manage'),
('aset', 'manage'),
('unit', 'manage,distribusisppb,distribusiretur,distribusikuota'),
('kepala_unit', 'manage,distribusisppb,distribusikuota'),
('kepala_sie', 'manage,distribusisppb,distribusikuota'),
('kepala_bidang', 'manage,distribusisppb,distribusikuota');

UPDATE `rsns_custom_logistik_non_medis_role_permissions`
SET `permissions` = CONCAT(TRIM(BOTH ',' FROM `permissions`), ',hakakses')
WHERE `role` = 'admin'
  AND CONCAT(',', COALESCE(`permissions`, ''), ',') NOT LIKE '%,hakakses,%';
