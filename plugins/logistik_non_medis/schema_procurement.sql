CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_rencana_rutin` (
  `no_rencana` varchar(50) NOT NULL,
  `tahun` int(4) NOT NULL,
  `bulan` varchar(2) NOT NULL,
  `tanggal_buat` date NOT NULL,
  `status` enum('Draft','Disetujui','Selesai') NOT NULL DEFAULT 'Draft',
  `keterangan` text DEFAULT NULL,
  `kode_vendor` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`no_rencana`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_rencana_rutin_detail` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `no_rencana` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `qty_rencana` double NOT NULL,
  `estimasi_harga` double NOT NULL,
  `qty_realisasi` double NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_detail`),
  KEY `no_rencana` (`no_rencana`),
  KEY `kode_item` (`kode_item`),
  CONSTRAINT `fk_rencana_rutin_detail` FOREIGN KEY (`no_rencana`) REFERENCES `rsns_custom_logistik_non_medis_rencana_rutin` (`no_rencana`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_terima_rutin` (
  `no_terima` varchar(50) NOT NULL,
  `no_rencana` varchar(50) DEFAULT NULL,
  `tanggal_terima` date NOT NULL,
  `no_faktur` varchar(50) DEFAULT NULL,
  `kode_vendor` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`no_terima`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_terima_rutin_detail` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `no_terima` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `qty_terima` double NOT NULL,
  `harga_beli` double NOT NULL,
  `total` double NOT NULL,
  PRIMARY KEY (`id_detail`),
  KEY `no_terima` (`no_terima`),
  KEY `kode_item` (`kode_item`),
  CONSTRAINT `fk_terima_rutin_detail` FOREIGN KEY (`no_terima`) REFERENCES `rsns_custom_logistik_non_medis_terima_rutin` (`no_terima`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_rencana_nonrutin` (
  `no_rencana` varchar(50) NOT NULL,
  `tahun` int(4) NOT NULL,
  `tanggal_buat` date NOT NULL,
  `status` enum('Draft','Disetujui','Selesai') NOT NULL DEFAULT 'Draft',
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`no_rencana`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_rencana_nonrutin_detail` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `no_rencana` varchar(50) NOT NULL,
  `nama_barang` varchar(255) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `qty_rencana` double NOT NULL,
  `estimasi_harga` double NOT NULL,
  `qty_realisasi` double NOT NULL DEFAULT 0,
  `alasan` text DEFAULT NULL,
  PRIMARY KEY (`id_detail`),
  KEY `no_rencana` (`no_rencana`),
  CONSTRAINT `fk_rencana_nonrutin_detail` FOREIGN KEY (`no_rencana`) REFERENCES `rsns_custom_logistik_non_medis_rencana_nonrutin` (`no_rencana`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
