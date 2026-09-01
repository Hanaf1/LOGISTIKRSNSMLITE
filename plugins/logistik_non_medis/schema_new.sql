-- Schema instalasi gabungan Logistik Non-Medis RSNS
-- DDL lengkap + seed master/hak akses. Tidak menyertakan data operasional besar.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_group_id` int DEFAULT NULL,
  `kode_aset` varchar(100) NOT NULL,
  `nomor_inventaris` varchar(50) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `nomor_dokumen` varchar(200) DEFAULT NULL,
  `kode_item` varchar(50) NOT NULL,
  `kode_kategori_aset` varchar(50) DEFAULT NULL,
  `nama_aset` varchar(200) NOT NULL,
  `merk_type` varchar(150) DEFAULT NULL,
  `spesifikasi` text,
  `foto_depan` varchar(255) DEFAULT NULL,
  `foto_detail` varchar(255) DEFAULT NULL,
  `tanggal_perolehan` date DEFAULT NULL,
  `tahun_beli` smallint DEFAULT NULL,
  `tahun_beli_referensi` tinyint(1) NOT NULL DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `jumlah` int NOT NULL DEFAULT '1',
  `harga_beli` double NOT NULL DEFAULT '0',
  `harga_referensi_import` double NOT NULL DEFAULT '0',
  `harga_referensi_keterangan` text,
  `sumber_perolehan` enum('Beli','Hibah','APBD','Lainnya') NOT NULL DEFAULT 'Beli',
  `kode_unit` varchar(50) DEFAULT NULL,
  `kode_lokasi` varchar(50) DEFAULT NULL,
  `lokasi_fisik` varchar(150) DEFAULT NULL,
  `bahan` varchar(100) DEFAULT NULL,
  `pic` varchar(100) DEFAULT NULL,
  `status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik',
  `keterangan_inventaris` text,
  `status` enum('Aktif','Dihapuskan') NOT NULL DEFAULT 'Aktif',
  `masa_manfaat_tahun` int DEFAULT '0',
  `nilai_residu` double DEFAULT '0',
  `akumulasi_penyusutan` double DEFAULT '0',
  `nilai_buku` double DEFAULT '0',
  `tgl_penyusutan_terakhir` date DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  `kib_jenis` enum('A','B','C','D','E','F') DEFAULT NULL,
  `kib_luas` double DEFAULT '0',
  `kib_alamat` text,
  `kib_hak` varchar(100) DEFAULT NULL,
  `kib_tgl_sertifikat` date DEFAULT NULL,
  `kib_no_sertifikat` varchar(100) DEFAULT NULL,
  `kib_penggunaan` varchar(255) DEFAULT NULL,
  `kib_merk` varchar(100) DEFAULT NULL,
  `kib_ukuran` varchar(100) DEFAULT NULL,
  `kib_bahan` varchar(100) DEFAULT NULL,
  `kib_no_pabrik` varchar(100) DEFAULT NULL,
  `kib_no_rangka` varchar(100) DEFAULT NULL,
  `kib_no_mesin` varchar(100) DEFAULT NULL,
  `kib_no_polisi` varchar(50) DEFAULT NULL,
  `kib_no_bpkb` varchar(50) DEFAULT NULL,
  `kib_bertingkat` enum('Ya','Tidak') DEFAULT 'Tidak',
  `kib_beton` enum('Ya','Tidak') DEFAULT 'Tidak',
  `kib_status_tanah` varchar(100) DEFAULT NULL,
  `kib_konstruksi` varchar(100) DEFAULT NULL,
  `kib_panjang` double DEFAULT '0',
  `kib_lebar` double DEFAULT '0',
  `kib_judul` varchar(255) DEFAULT NULL,
  `kib_pencipta` varchar(100) DEFAULT NULL,
  `kib_proyek_bangunan` varchar(100) DEFAULT NULL,
  `kib_tgl_mulai` date DEFAULT NULL,
  `kib_tgl_rencana_selesai` date DEFAULT NULL,
  `kib_progress_persen` double DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_aset` (`kode_aset`),
  KEY `kode_item` (`kode_item`),
  KEY `kode_unit` (`kode_unit`),
  KEY `idx_aset_status` (`status`),
  KEY `nomor_inventaris` (`nomor_inventaris`),
  KEY `asset_group_id` (`asset_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28923 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_mutasi`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_mutasi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_mutasi` varchar(50) DEFAULT NULL,
  `kode_aset` varchar(100) NOT NULL,
  `kode_unit_asal` varchar(50) DEFAULT NULL,
  `kode_unit_tujuan` varchar(50) DEFAULT NULL,
  `kode_lokasi_asal` varchar(50) DEFAULT NULL,
  `kode_lokasi_tujuan` varchar(50) DEFAULT NULL,
  `pic_asal` varchar(100) DEFAULT NULL,
  `pic_tujuan` varchar(100) DEFAULT NULL,
  `keterangan` text,
  `tanggal_mutasi` date DEFAULT NULL,
  `status` enum('Draft','Diajukan','Disetujui Asal','Selesai','Ditolak') NOT NULL DEFAULT 'Draft',
  `alasan_penolakan` text,
  `user_approval_asal` varchar(100) DEFAULT NULL,
  `tgl_approval_asal` datetime DEFAULT NULL,
  `user_approval_tujuan` varchar(100) DEFAULT NULL,
  `tgl_approval_tujuan` datetime DEFAULT NULL,
  `user_mutasi` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `tgl_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_mutasi` (`no_mutasi`),
  KEY `kode_aset` (`kode_aset`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_pemeliharaan`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_pemeliharaan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_pemeliharaan` varchar(50) NOT NULL,
  `kode_aset` varchar(50) NOT NULL,
  `jenis_pemeliharaan` enum('Preventive','Corrective') NOT NULL,
  `tanggal_direncanakan` date NOT NULL,
  `tanggal_pelaksanaan` datetime DEFAULT NULL,
  `nama_kegiatan` varchar(200) NOT NULL,
  `deskripsi` text,
  `frekuensi` enum('Sekali Saja','1 Bulan','3 Bulan','6 Bulan','1 Tahun','Kustom') DEFAULT 'Sekali Saja',
  `hari_kustom` int DEFAULT '0',
  `prioritas` enum('Rendah','Sedang','Tinggi','Darurat') DEFAULT 'Sedang',
  `kode_rekanan` varchar(50) DEFAULT NULL,
  `nama_teknisi` varchar(150) DEFAULT NULL,
  `tindakan_perbaikan` text,
  `status_kondisi_akhir` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
  `biaya_jasa` double DEFAULT '0',
  `biaya_sparepart` double DEFAULT '0',
  `detail_sparepart` text,
  `total_biaya` double DEFAULT '0',
  `status` enum('Jadwal','Menunggu','Diproses','Selesai','Dibatalkan') DEFAULT 'Jadwal',
  `user_input` varchar(50) NOT NULL,
  `tgl_input` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_pemeliharaan` (`kode_pemeliharaan`),
  KEY `kode_aset` (`kode_aset`),
  KEY `status` (`status`),
  KEY `tanggal_direncanakan` (`tanggal_direncanakan`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_penghapusan`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_penghapusan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_pengajuan` varchar(50) NOT NULL,
  `kode_aset` varchar(100) NOT NULL,
  `tanggal_pengajuan` date NOT NULL,
  `alasan_penghapusan` text NOT NULL,
  `pic_pengusul` varchar(100) NOT NULL,
  `status_kondisi_terakhir` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
  `nilai_buku_terakhir` double DEFAULT '0',
  `nilai_taksiran` double DEFAULT '0',
  `catatan_penilaian` text,
  `tanggal_penilaian` date DEFAULT NULL,
  `petugas_penilai` varchar(100) DEFAULT NULL,
  `metode_penghapusan` enum('Lelang','Hibah','Musnah') DEFAULT NULL,
  `detail_metode` text,
  `no_sk` varchar(100) DEFAULT NULL,
  `tgl_sk` date DEFAULT NULL,
  `file_sk` varchar(255) DEFAULT NULL,
  `no_ba` varchar(100) DEFAULT NULL,
  `tgl_ba` date DEFAULT NULL,
  `file_ba` varchar(255) DEFAULT NULL,
  `keterangan_eksekusi` text,
  `status` enum('Draft','Pengajuan','Dinilai','Disetujui','Selesai','Ditolak') DEFAULT 'Draft',
  `user_input` varchar(100) NOT NULL,
  `tgl_input` datetime NOT NULL,
  `tgl_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_pengajuan` (`no_pengajuan`),
  KEY `kode_aset` (`kode_aset`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_penyusutan`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_penyusutan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_aset` varchar(100) NOT NULL,
  `periode` varchar(7) NOT NULL,
  `tanggal_proses` datetime NOT NULL,
  `harga_perolehan` double NOT NULL DEFAULT '0',
  `nilai_residu` double NOT NULL DEFAULT '0',
  `biaya_penyusutan` double NOT NULL DEFAULT '0',
  `akumulasi_penyusutan` double NOT NULL DEFAULT '0',
  `nilai_buku` double NOT NULL DEFAULT '0',
  `no_jurnal` varchar(100) DEFAULT NULL,
  `user_proses` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aset_periode` (`kode_aset`,`periode`),
  KEY `periode` (`periode`),
  KEY `no_jurnal` (`no_jurnal`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_sensus`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_sensus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_sensus` varchar(200) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `keterangan_sensus` text,
  `kode_unit_sensus` varchar(50) DEFAULT NULL,
  `catatan_tambahan` text,
  `status_sensus_periode` enum('Draft','Aktif','Selesai','Dibatalkan') NOT NULL DEFAULT 'Draft',
  `kode_aset` varchar(100) NOT NULL,
  `sistem_kode_unit` varchar(50) NOT NULL,
  `sistem_kode_lokasi` varchar(50) DEFAULT NULL,
  `sistem_status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik',
  `fisik_kode_unit` varchar(50) DEFAULT NULL,
  `fisik_kode_lokasi` varchar(50) DEFAULT NULL,
  `fisik_status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
  `foto_fisik` varchar(255) DEFAULT NULL,
  `catatan_temuan` text,
  `status_sensus_item` enum('Belum Sensus','Sesuai','Selisih Lokasi','Selisih Kondisi','Tidak Ditemukan','Aset Baru') NOT NULL DEFAULT 'Belum Sensus',
  `tanggal_scan` datetime DEFAULT NULL,
  `petugas_scan` varchar(100) DEFAULT NULL,
  `status_penyesuaian` enum('Belum Disesuaikan','Sudah Disesuaikan') NOT NULL DEFAULT 'Belum Disesuaikan',
  `tgl_penyesuaian` datetime DEFAULT NULL,
  `user_penyesuaian` varchar(100) DEFAULT NULL,
  `no_sertifikat` varchar(100) DEFAULT NULL,
  `tanggal_sertifikat` date DEFAULT NULL,
  `ttd_petugas` varchar(100) DEFAULT NULL,
  `ttd_ka_unit` varchar(100) DEFAULT NULL,
  `ttd_ka_logistik` varchar(100) DEFAULT NULL,
  `status_sertifikasi` enum('Belum Sertifikasi','Disetujui Ka Unit','Sertifikasi Selesai') NOT NULL DEFAULT 'Belum Sertifikasi',
  `tgl_input` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kode_aset` (`kode_aset`),
  KEY `nama_sensus` (`nama_sensus`),
  KEY `status_sensus_item` (`status_sensus_item`),
  KEY `sistem_kode_unit` (`sistem_kode_unit`)
) ENGINE=InnoDB AUTO_INCREMENT=6027 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_asset_groups`;
CREATE TABLE `rsns_custom_logistik_non_medis_asset_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_group` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `kode_kategori` char(1) NOT NULL DEFAULT '2',
  `nama_group` varchar(200) NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `lokasi_fisik` varchar(150) DEFAULT NULL,
  `tanggal_perolehan` date DEFAULT NULL,
  `tahun_beli` smallint DEFAULT NULL,
  `sumber_perolehan` enum('Beli','Hibah','APBD','Lainnya') NOT NULL DEFAULT 'Beli',
  `satuan` varchar(50) DEFAULT NULL,
  `harga_satuan` double NOT NULL DEFAULT '0',
  `jumlah` int NOT NULL DEFAULT '1',
  `nomor_awal` varchar(50) DEFAULT NULL,
  `nomor_akhir` varchar(50) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `tgl_input` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_group` (`kode_group`),
  KEY `item_unit` (`kode_item`,`kode_unit`)
) ENGINE=InnoDB AUTO_INCREMENT=9283 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_barang_rusak`;
CREATE TABLE `rsns_custom_logistik_non_medis_barang_rusak` (
  `no_transaksi` varchar(50) NOT NULL,
  `tgl_transaksi` date NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `batch` varchar(50) DEFAULT NULL,
  `kode_lokasi` varchar(50) DEFAULT NULL,
  `jumlah` double NOT NULL DEFAULT '0',
  `kategori_kerusakan` varchar(100) DEFAULT NULL,
  `keterangan` text,
  `tindak_lanjut` enum('Retur','Pemusnahan') DEFAULT NULL,
  `status` enum('Karantina','Selesai') NOT NULL DEFAULT 'Karantina',
  `kode_vendor` varchar(50) DEFAULT NULL,
  `tgl_retur` date DEFAULT NULL,
  `status_retur` varchar(50) DEFAULT NULL,
  `tgl_pemusnahan` date DEFAULT NULL,
  `metode_pemusnahan` varchar(100) DEFAULT NULL,
  `saksi_1` varchar(100) DEFAULT NULL,
  `saksi_2` varchar(100) DEFAULT NULL,
  `catatan_logistik` text,
  `tgl_input` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_cost_unit_audit`;
CREATE TABLE `rsns_custom_logistik_non_medis_cost_unit_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_sppb` varchar(50) NOT NULL,
  `sppb_item_id` int NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `qty` double NOT NULL DEFAULT '0',
  `harga_lama` double NOT NULL DEFAULT '0',
  `harga_baru` double NOT NULL DEFAULT '0',
  `subtotal` double NOT NULL DEFAULT '0',
  `aksi` enum('Simpan','Selesaikan') NOT NULL DEFAULT 'Simpan',
  `keterangan` text,
  `user_proses` varchar(100) DEFAULT NULL,
  `tgl_proses` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `no_sppb` (`no_sppb`),
  KEY `kode_unit` (`kode_unit`),
  KEY `tgl_proses` (`tgl_proses`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_ekatalog`;
CREATE TABLE `rsns_custom_logistik_non_medis_ekatalog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_po` varchar(50) DEFAULT NULL,
  `kode_produk_lkpp` varchar(100) DEFAULT NULL,
  `nama_produk` varchar(255) NOT NULL,
  `merk` varchar(100) DEFAULT NULL,
  `penyedia` varchar(255) DEFAULT NULL,
  `harga_katalog` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `no_paket_lkpp` varchar(100) DEFAULT NULL,
  `tgl_order` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Master',
  `link_produk` text,
  `last_sync` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `no_po` (`no_po`),
  KEY `kode_produk_lkpp` (`kode_produk_lkpp`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_fonnte_config`;
CREATE TABLE `rsns_custom_logistik_non_medis_fonnte_config` (
  `id` tinyint(1) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '0',
  `token` varchar(255) DEFAULT NULL,
  `tgl_diperbarui` datetime NOT NULL,
  `duration` tinyint NOT NULL DEFAULT '1',
  `delay` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_fonnte_send_log`;
CREATE TABLE `rsns_custom_logistik_non_medis_fonnte_send_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `tipe` varchar(50) NOT NULL,
  `no_sppb` varchar(100) DEFAULT NULL,
  `nomor` varchar(30) NOT NULL,
  `tgl_kirim` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dedupe` (`username`,`tipe`,`no_sppb`,`tgl_kirim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_fonnte_template`;
CREATE TABLE `rsns_custom_logistik_non_medis_fonnte_template` (
  `tipe` varchar(50) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `pesan` text NOT NULL,
  `tgl_diperbarui` datetime NOT NULL,
  PRIMARY KEY (`tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_inventaris_jenis`;
CREATE TABLE `rsns_custom_logistik_non_medis_inventaris_jenis` (
  `kode_kategori` char(1) NOT NULL,
  `kode_kelompok` char(2) NOT NULL,
  `kode_jenis` char(2) NOT NULL,
  `nama_jenis` varchar(150) NOT NULL,
  PRIMARY KEY (`kode_kategori`,`kode_kelompok`,`kode_jenis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_inventaris_kategori`;
CREATE TABLE `rsns_custom_logistik_non_medis_inventaris_kategori` (
  `kode_kategori` char(1) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_inventaris_kelompok`;
CREATE TABLE `rsns_custom_logistik_non_medis_inventaris_kelompok` (
  `kode_kategori` char(1) NOT NULL,
  `kode_kelompok` char(2) NOT NULL,
  `nama_kelompok` varchar(150) NOT NULL,
  PRIMARY KEY (`kode_kategori`,`kode_kelompok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_inventaris_master`;
CREATE TABLE `rsns_custom_logistik_non_medis_inventaris_master` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jenis_master` enum('UNIT','BARANG') NOT NULL,
  `kode` varchar(50) NOT NULL,
  `kode_inventaris` char(3) DEFAULT NULL,
  `kode_kategori` char(1) NOT NULL DEFAULT '',
  `nama` varchar(200) NOT NULL,
  `kode_kelompok` char(2) DEFAULT NULL,
  `kode_jenis` char(2) DEFAULT NULL,
  `kode_barang` char(2) DEFAULT NULL,
  `nama_kelompok` varchar(150) DEFAULT NULL,
  `nama_jenis` varchar(150) DEFAULT NULL,
  `kib_jenis` enum('A','B','C','D','E','F') DEFAULT NULL,
  `harga_referensi` double NOT NULL DEFAULT '0',
  `tahun_harga_referensi` smallint DEFAULT NULL,
  `sumber_harga_referensi` text,
  `catatan_harga_referensi` text,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `tgl_input` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jenis_kode` (`jenis_master`,`kode_kategori`,`kode`),
  KEY `idx_inventaris_jenis_kode` (`jenis_master`,`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=3219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kartu_stok`;
CREATE TABLE `rsns_custom_logistik_non_medis_kartu_stok` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tgl_transaksi` datetime NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `batch_no` varchar(100) DEFAULT '-',
  `tipe_transaksi` enum('Masuk','Keluar','Retur','Opname','Mutasi Masuk','Mutasi Keluar') NOT NULL,
  `no_referensi` varchar(50) NOT NULL,
  `qty_masuk` double NOT NULL DEFAULT '0',
  `qty_keluar` double NOT NULL DEFAULT '0',
  `stok_akhir` double NOT NULL DEFAULT '0',
  `harga` double NOT NULL DEFAULT '0',
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kartu_stok_item_lokasi_tanggal` (`kode_item`,`kode_lokasi`,`tgl_transaksi`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=920 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kategori`;
CREATE TABLE `rsns_custom_logistik_non_medis_kategori` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_kategori` varchar(50) NOT NULL,
  `nama_kategori` varchar(200) NOT NULL,
  `deskripsi` text,
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kategori_aset`;
CREATE TABLE `rsns_custom_logistik_non_medis_kategori_aset` (
  `kode_kategori` varchar(50) NOT NULL,
  `nama_kategori` varchar(200) NOT NULL,
  `umur_manfaat_default` int DEFAULT '0',
  `status_aktif` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kuota`;
CREATE TABLE `rsns_custom_logistik_non_medis_kuota` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_unit` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `periode_tipe` enum('Bulanan','Triwulan') NOT NULL DEFAULT 'Bulanan',
  `tahun` year NOT NULL,
  `bulan` int DEFAULT NULL,
  `triwulan` int DEFAULT NULL,
  `jumlah` double NOT NULL DEFAULT '0',
  `jenis` enum('Utama','Tambahan') NOT NULL DEFAULT 'Utama',
  `status` enum('Draft','Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft',
  `keterangan` text,
  `user_input` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `user_approve` varchar(100) DEFAULT NULL,
  `tgl_approve` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kode_unit` (`kode_unit`),
  KEY `kode_item` (`kode_item`),
  KEY `periode` (`tahun`,`bulan`,`triwulan`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_lokasi_gudang`;
CREATE TABLE `rsns_custom_logistik_non_medis_lokasi_gudang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_lokasi` varchar(50) NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL,
  `kode_zona` varchar(50) DEFAULT NULL,
  `rak` varchar(50) DEFAULT NULL,
  `bin` varchar(50) DEFAULT NULL,
  `slot` varchar(50) DEFAULT NULL,
  `kapasitas` double NOT NULL DEFAULT '0',
  `satuan_kapasitas` varchar(50) DEFAULT NULL,
  `tipe_penyimpanan` varchar(100) DEFAULT NULL,
  `suhu_min` double DEFAULT NULL,
  `suhu_max` double DEFAULT NULL,
  `berat_max` double DEFAULT NULL,
  `denah_digital` varchar(255) DEFAULT NULL,
  `is_fragile` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_lokasi` (`kode_lokasi`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_master_barang`;
CREATE TABLE `rsns_custom_logistik_non_medis_master_barang` (
  `kode_item` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `nama_barang` varchar(200) NOT NULL,
  `deskripsi` text,
  `spesifikasi` text,
  `kategori` varchar(100) DEFAULT NULL,
  `jenis_item` enum('Rutin','Non Rutin') NOT NULL DEFAULT 'Rutin',
  `tipe_barang` enum('Habis Pakai','Aset') NOT NULL DEFAULT 'Habis Pakai',
  `kode_kategori` varchar(50) DEFAULT NULL,
  `sub_kategori` varchar(100) DEFAULT NULL,
  `satuan_dasar` varchar(50) NOT NULL,
  `satuan_konversi` varchar(50) DEFAULT NULL,
  `harga_referensi` double NOT NULL DEFAULT '0',
  `stok_min` double NOT NULL DEFAULT '0',
  `stok_max` double NOT NULL DEFAULT '0',
  `safety_stock` double NOT NULL DEFAULT '0',
  `foto` varchar(255) DEFAULT NULL,
  `dokumen` varchar(255) DEFAULT NULL,
  `default_kode_lokasi` varchar(50) DEFAULT NULL,
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`kode_item`),
  KEY `idx_barang_kategori_item` (`kode_kategori`,`kode_item`),
  KEY `idx_barang_status_nama` (`status`,`nama_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_mutasi`;
CREATE TABLE `rsns_custom_logistik_non_medis_mutasi` (
  `no_mutasi` varchar(50) NOT NULL,
  `tgl_mutasi` date NOT NULL,
  `kode_lokasi_asal` varchar(50) DEFAULT NULL,
  `kode_lokasi_tujuan` varchar(50) DEFAULT NULL,
  `keterangan` text,
  `status` enum('Draft','Dikirim','Diterima','Batal') NOT NULL DEFAULT 'Draft',
  `user_input` varchar(100) DEFAULT NULL,
  `user_terima` varchar(100) DEFAULT NULL,
  `tgl_terima` datetime DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  PRIMARY KEY (`no_mutasi`),
  KEY `kode_lokasi_asal` (`kode_lokasi_asal`),
  KEY `kode_lokasi_tujuan` (`kode_lokasi_tujuan`),
  KEY `idx_mutasi_tanggal_status` (`tgl_mutasi`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_mutasi_detail`;
CREATE TABLE `rsns_custom_logistik_non_medis_mutasi_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_mutasi` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `jenis_mutasi` enum('Masuk','Keluar','Penyesuaian') NOT NULL DEFAULT 'Penyesuaian',
  `batch_no` varchar(100) DEFAULT '-',
  `qty` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `no_mutasi` (`no_mutasi`),
  KEY `kode_item` (`kode_item`),
  KEY `idx_mutasi_detail_nomor_item` (`no_mutasi`,`kode_item`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_notifier_event`;
CREATE TABLE `rsns_custom_logistik_non_medis_notifier_event` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `no_sppb` varchar(100) NOT NULL,
  `kode_unit` varchar(100) DEFAULT NULL,
  `tgl_dibuat` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_sppb` (`no_sppb`),
  KEY `tgl_dibuat` (`tgl_dibuat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_notifikasi`;
CREATE TABLE `rsns_custom_logistik_non_medis_notifikasi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_target` varchar(100) NOT NULL,
  `pesan` text NOT NULL,
  `tipe` varchar(50) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `tgl_dibuat` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_target` (`user_target`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_opname`;
CREATE TABLE `rsns_custom_logistik_non_medis_opname` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_opname` varchar(50) NOT NULL,
  `tgl_opname` date DEFAULT NULL,
  `tgl_jadwal` date DEFAULT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `kode_item` varchar(50) DEFAULT NULL,
  `stok_sistem` double NOT NULL DEFAULT '0',
  `stok_fisik` double NOT NULL DEFAULT '0',
  `selisih` double NOT NULL DEFAULT '0',
  `keterangan` text,
  `status` enum('Jadwal','Draft','Selesai') NOT NULL DEFAULT 'Jadwal',
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `no_opname` (`no_opname`),
  KEY `kode_lokasi` (`kode_lokasi`),
  KEY `kode_item` (`kode_item`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_packing`;
CREATE TABLE `rsns_custom_logistik_non_medis_packing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_packing` varchar(50) NOT NULL,
  `no_sppb` varchar(50) NOT NULL,
  `tgl_packing` datetime NOT NULL,
  `petugas_packing` varchar(100) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `qty_picked` double NOT NULL,
  `koli_ke` int DEFAULT '1',
  `total_berat_koli` double DEFAULT '0',
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `no_packing` (`no_packing`),
  KEY `no_sppb` (`no_sppb`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_penerimaan`;
CREATE TABLE `rsns_custom_logistik_non_medis_penerimaan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_penerimaan` varchar(50) NOT NULL,
  `tgl_penerimaan` date NOT NULL,
  `no_po` varchar(50) NOT NULL,
  `kode_vendor` varchar(50) NOT NULL,
  `no_faktur` varchar(100) DEFAULT NULL,
  `no_surat_jalan` varchar(100) DEFAULT NULL,
  `file_faktur` varchar(255) DEFAULT NULL,
  `file_surat_jalan` varchar(255) DEFAULT NULL,
  `kode_item` varchar(50) NOT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `qty_po` double NOT NULL DEFAULT '0',
  `qty_terima` double NOT NULL DEFAULT '0',
  `qty_tolak` double NOT NULL DEFAULT '0',
  `batch_no` varchar(100) DEFAULT NULL,
  `tgl_expired` date DEFAULT NULL,
  `harga` double NOT NULL DEFAULT '0',
  `keterangan` text,
  `kode_lokasi` varchar(50) DEFAULT NULL,
  `tipe_penerimaan` varchar(20) NOT NULL DEFAULT 'PO',
  `sumber_manual` varchar(150) DEFAULT NULL,
  `alasan_tanpa_po` text,
  `status` enum('Draft','Menunggu Verifikasi','Selesai','Ditolak') NOT NULL DEFAULT 'Draft',
  `stok_diposting` tinyint(1) NOT NULL DEFAULT '0',
  `diverifikasi_oleh` varchar(100) DEFAULT NULL,
  `tgl_verifikasi` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_penerimaan_tanggal_po` (`tgl_penerimaan`,`no_po`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_pengaturan`;
CREATE TABLE `rsns_custom_logistik_non_medis_pengaturan` (
  `nama_pengaturan` varchar(100) NOT NULL,
  `nilai` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`nama_pengaturan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_pengiriman`;
CREATE TABLE `rsns_custom_logistik_non_medis_pengiriman` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_sppb` varchar(50) NOT NULL,
  `no_manifest` varchar(100) DEFAULT NULL,
  `kurir` varchar(100) DEFAULT NULL,
  `kendaraan` varchar(100) DEFAULT NULL,
  `status` enum('Proses','Dikirim','Diterima') NOT NULL DEFAULT 'Proses',
  `waktu_packing` datetime DEFAULT NULL,
  `waktu_kirim` datetime DEFAULT NULL,
  `waktu_terima` datetime DEFAULT NULL,
  `penerima` varchar(100) DEFAULT NULL,
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `no_sppb` (`no_sppb`),
  KEY `no_manifest` (`no_manifest`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_perencanaan`;
CREATE TABLE `rsns_custom_logistik_non_medis_perencanaan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_perencanaan` varchar(50) NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `kelompok_barang` varchar(100) DEFAULT NULL,
  `bulan` varchar(2) NOT NULL DEFAULT '01',
  `tahun` year NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `pemakaian_lalu` double NOT NULL DEFAULT '0',
  `jan` double NOT NULL DEFAULT '0',
  `feb` double NOT NULL DEFAULT '0',
  `mar` double NOT NULL DEFAULT '0',
  `apr` double NOT NULL DEFAULT '0',
  `mei` double NOT NULL DEFAULT '0',
  `jun` double NOT NULL DEFAULT '0',
  `jul` double NOT NULL DEFAULT '0',
  `agu` double NOT NULL DEFAULT '0',
  `sep` double NOT NULL DEFAULT '0',
  `okt` double NOT NULL DEFAULT '0',
  `nov` double NOT NULL DEFAULT '0',
  `des` double NOT NULL DEFAULT '0',
  `total_qty` double NOT NULL DEFAULT '0',
  `harga_referensi` double NOT NULL DEFAULT '0',
  `prioritas` varchar(50) DEFAULT 'Desirable',
  `status` enum('Draft','Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft',
  `tgl_input` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_perencanaan_tahun_kode` (`tahun`,`kode_perencanaan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_po`;
CREATE TABLE `rsns_custom_logistik_non_medis_po` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_po` varchar(50) NOT NULL,
  `tgl_po` date NOT NULL,
  `kode_vendor` varchar(50) NOT NULL,
  `sumber_tipe` varchar(20) DEFAULT NULL,
  `no_rencana` varchar(50) DEFAULT NULL,
  `total_nilai` double NOT NULL DEFAULT '0',
  `diskon` double NOT NULL DEFAULT '0',
  `ppn` double NOT NULL DEFAULT '0',
  `grand_total` double NOT NULL DEFAULT '0',
  `detail_items` longtext NOT NULL,
  `catatan` text,
  `status` enum('Draft','Terkirim','Sebagian Diterima','Selesai','Diamandemen','Dibatalkan') NOT NULL DEFAULT 'Draft',
  `tgl_kirim` datetime DEFAULT NULL,
  `file_po` varchar(255) DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_po` (`no_po`),
  KEY `idx_po_tanggal_status` (`tgl_po`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_pr`;
CREATE TABLE `rsns_custom_logistik_non_medis_pr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_pr` varchar(50) NOT NULL,
  `tgl_pr` date NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `jumlah` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) NOT NULL,
  `justifikasi` text,
  `file_justifikasi` varchar(255) DEFAULT NULL,
  `status` enum('Draft','Diajukan','Disetujui','Di-PO-kan','Ditolak','Selesai') NOT NULL DEFAULT 'Draft',
  `petugas_logistik` varchar(100) DEFAULT NULL,
  `tgl_acc` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_push_subscription`;
CREATE TABLE `rsns_custom_logistik_non_medis_push_subscription` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `p256dh` text NOT NULL,
  `auth_token` text NOT NULL,
  `content_encoding` varchar(30) NOT NULL DEFAULT 'aesgcm',
  `nama_perangkat` varchar(150) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `tgl_dibuat` datetime NOT NULL,
  `tgl_diperbarui` datetime NOT NULL,
  `terakhir_berhasil` datetime DEFAULT NULL,
  `terakhir_error` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_hash` (`endpoint_hash`),
  KEY `username_aktif` (`username`,`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_rekanan_jasa`;
CREATE TABLE `rsns_custom_logistik_non_medis_rekanan_jasa` (
  `kode_rekanan` varchar(50) NOT NULL,
  `nama_rekanan` varchar(200) NOT NULL,
  `kategori` enum('Vendor Servis','Kontraktor') DEFAULT 'Vendor Servis',
  `alamat` text,
  `no_telp` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `pic` varchar(100) DEFAULT NULL,
  `pic_kontak` varchar(50) DEFAULT NULL,
  `jenis_layanan` varchar(255) DEFAULT NULL,
  `frekuensi` varchar(100) DEFAULT NULL,
  `tgl_servis_terakhir` date DEFAULT NULL,
  `tgl_servis_berikutnya` date DEFAULT NULL,
  `nomor_kontrak` varchar(100) DEFAULT NULL,
  `tgl_mulai_kontrak` date DEFAULT NULL,
  `tgl_selesai_kontrak` date DEFAULT NULL,
  `nilai_kontrak` double DEFAULT '0',
  `file_kontrak` varchar(255) DEFAULT NULL,
  `status` enum('Aktif','Non-Aktif') DEFAULT 'Aktif',
  PRIMARY KEY (`kode_rekanan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_rencana_nonrutin`;
CREATE TABLE `rsns_custom_logistik_non_medis_rencana_nonrutin` (
  `no_rencana` varchar(50) NOT NULL,
  `kode_unit` varchar(50) DEFAULT NULL,
  `tahun` int NOT NULL,
  `tanggal_buat` date NOT NULL,
  `status` enum('Draft','Disetujui','Ditolak','Selesai') NOT NULL DEFAULT 'Draft',
  `alasan_penolakan` text,
  `keterangan` text,
  PRIMARY KEY (`no_rencana`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_rencana_nonrutin_detail`;
CREATE TABLE `rsns_custom_logistik_non_medis_rencana_nonrutin_detail` (
  `id_detail` int NOT NULL AUTO_INCREMENT,
  `no_rencana` varchar(50) NOT NULL,
  `nama_barang` varchar(255) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `qty_rencana` double NOT NULL,
  `estimasi_harga` double NOT NULL,
  `qty_realisasi` double NOT NULL DEFAULT '0',
  `alasan` text,
  PRIMARY KEY (`id_detail`),
  KEY `no_rencana` (`no_rencana`),
  CONSTRAINT `fk_rencana_nonrutin_detail` FOREIGN KEY (`no_rencana`) REFERENCES `rsns_custom_logistik_non_medis_rencana_nonrutin` (`no_rencana`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_rencana_rutin`;
CREATE TABLE `rsns_custom_logistik_non_medis_rencana_rutin` (
  `no_rencana` varchar(50) NOT NULL,
  `tahun` int NOT NULL,
  `bulan` varchar(2) NOT NULL,
  `tanggal_buat` date NOT NULL,
  `status` enum('Draft','Disetujui','Ditolak','Selesai') NOT NULL DEFAULT 'Draft',
  `alasan_penolakan` text,
  `keterangan` text,
  `kode_vendor` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`no_rencana`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_rencana_rutin_detail`;
CREATE TABLE `rsns_custom_logistik_non_medis_rencana_rutin_detail` (
  `id_detail` int NOT NULL AUTO_INCREMENT,
  `no_rencana` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `qty_rencana` double NOT NULL,
  `estimasi_harga` double NOT NULL,
  `qty_realisasi` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_detail`),
  KEY `no_rencana` (`no_rencana`),
  KEY `kode_item` (`kode_item`),
  CONSTRAINT `fk_rencana_rutin_detail` FOREIGN KEY (`no_rencana`) REFERENCES `rsns_custom_logistik_non_medis_rencana_rutin` (`no_rencana`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_report_schedules`;
CREATE TABLE `rsns_custom_logistik_non_medis_report_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_name` varchar(100) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `sub_report_type` varchar(50) NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `send_time` time NOT NULL DEFAULT '07:00:00',
  `send_day` int DEFAULT NULL,
  `email_recipients` text NOT NULL,
  `filters_json` text,
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `last_run` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_report_verifications`;
CREATE TABLE `rsns_custom_logistik_non_medis_report_verifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `verification_hash` varchar(64) NOT NULL,
  `report_name` varchar(100) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `generated_by` varchar(100) NOT NULL,
  `generated_at` datetime NOT NULL,
  `checksum_data` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_hash` (`verification_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_retur_unit`;
CREATE TABLE `rsns_custom_logistik_non_medis_retur_unit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_retur` varchar(50) NOT NULL,
  `tgl_retur` date NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `no_sppb` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `qty` double NOT NULL DEFAULT '0',
  `alasan` enum('Salah Kirim','Sisa','Rusak') NOT NULL DEFAULT 'Sisa',
  `kondisi_fisik` text,
  `inspeksi` text,
  `status` enum('Pending','Disetujui','Ditolak') NOT NULL DEFAULT 'Pending',
  `petugas` varchar(100) DEFAULT NULL,
  `tgl_approval` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `no_retur` (`no_retur`),
  KEY `kode_unit` (`kode_unit`),
  KEY `no_sppb` (`no_sppb`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_role_permissions`;
CREATE TABLE `rsns_custom_logistik_non_medis_role_permissions` (
  `role` varchar(50) NOT NULL,
  `permissions` text,
  PRIMARY KEY (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_satuan`;
CREATE TABLE `rsns_custom_logistik_non_medis_satuan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_satuan` varchar(50) NOT NULL,
  `nama_satuan` varchar(100) NOT NULL,
  `satuan_dasar` varchar(50) DEFAULT NULL,
  `nilai_konversi` double NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_satuan` (`kode_satuan`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_serah_terima`;
CREATE TABLE `rsns_custom_logistik_non_medis_serah_terima` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_serah_terima` varchar(50) NOT NULL,
  `no_sppb` varchar(50) NOT NULL,
  `tanggal_serah` datetime NOT NULL,
  `petugas_pengirim` varchar(100) NOT NULL,
  `penerima_nama` varchar(100) NOT NULL,
  `penerima_nip` varchar(50) DEFAULT NULL,
  `foto_kondisi` varchar(255) DEFAULT NULL,
  `tanda_terima` longtext,
  `tanda_terima_format` varchar(20) NOT NULL DEFAULT 'STROKES_V1',
  `tanda_terima_hash` char(64) DEFAULT NULL,
  `tanda_terima_ip` varchar(45) DEFAULT NULL,
  `tanda_terima_user_agent` varchar(255) DEFAULT NULL,
  `tanda_terima_file` varchar(255) DEFAULT NULL,
  `keterangan` text,
  `arsip_bast` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_serah_terima` (`no_serah_terima`),
  KEY `no_sppb` (`no_sppb`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_sppb`;
CREATE TABLE `rsns_custom_logistik_non_medis_sppb` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_sppb` varchar(50) NOT NULL,
  `tgl_sppb` date NOT NULL,
  `minggu_ke` tinyint(1) DEFAULT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `jenis_permintaan` enum('Rutin','Non Rutin') NOT NULL DEFAULT 'Rutin',
  `sumber_pemenuhan` varchar(30) DEFAULT NULL,
  `referensi_pemenuhan` text,
  `catatan_pemenuhan` text,
  `user_tindak_lanjut` varchar(100) DEFAULT NULL,
  `tgl_tindak_lanjut` datetime DEFAULT NULL,
  `jenis_keluar` varchar(30) NOT NULL DEFAULT 'Rutin',
  `kode_item` varchar(50) NOT NULL,
  `item_sumber` enum('master','manual') NOT NULL DEFAULT 'master',
  `nama_barang_manual` varchar(255) DEFAULT NULL,
  `spesifikasi_manual` text,
  `estimasi_harga` double NOT NULL DEFAULT '0',
  `foto_barang` longtext,
  `latar_belakang_tujuan` text,
  `sasaran_kegunaan` text,
  `rencana_digunakan` text,
  `jumlah` double NOT NULL DEFAULT '0',
  `jumlah_disetujui` double NOT NULL DEFAULT '0',
  `harga_satuan_cost` double NOT NULL DEFAULT '0',
  `subtotal_cost` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `status` enum('Draft','Diajukan','Disetujui Ka. Unit','Disetujui Ka. Sie','Disetujui Kabid','Disetujui Unit','Diserahkan ke Kasie Umum','Verifikasi Kasie Umum','Diteruskan ke Logistik Umum','Rekap Logistik','Logistik Umum & Rekap','Konsultasi Dana','Konsul Pengajuan ke Kabid Umum','Diserahkan ke Keuangan','Pengajuan Dana ke Bendahara','Tidak ACC','Proses','Terverifikasi','Proses Logistik','Proses Pengadaan','Siap Ambil','Siap Diserahkan','Picking','Packing','Ready','Dikirim','Diterima','Selesai','Ditolak','Dibatalkan') NOT NULL DEFAULT 'Draft',
  `sifat_permintaan` varchar(20) NOT NULL DEFAULT 'Biasa',
  `diajukan_oleh` varchar(150) DEFAULT NULL,
  `penanggung_jawab_1` varchar(150) DEFAULT NULL,
  `penanggung_jawab_2` varchar(150) DEFAULT NULL,
  `ka_unit` varchar(150) DEFAULT NULL,
  `keterangan` text,
  `keterangan_item` text,
  `alasan_penolakan` text,
  `ditolak_pada_status` varchar(100) DEFAULT NULL,
  `keterangan_verifikasi` text,
  `user_cost` varchar(100) DEFAULT NULL,
  `tgl_cost` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `user_approve_ka_unit` varchar(100) DEFAULT NULL,
  `tgl_approve_ka_unit` datetime DEFAULT NULL,
  `user_approve_ka_sie` varchar(100) DEFAULT NULL,
  `tgl_approve_ka_sie` datetime DEFAULT NULL,
  `user_approve_ka_bidang` varchar(100) DEFAULT NULL,
  `tgl_approve_ka_bidang` datetime DEFAULT NULL,
  `user_approve_unit` varchar(100) DEFAULT NULL,
  `tgl_approve_unit` datetime DEFAULT NULL,
  `user_verifikasi` varchar(100) DEFAULT NULL,
  `tgl_verifikasi` datetime DEFAULT NULL,
  `diambil_oleh` varchar(150) DEFAULT NULL,
  `tgl_diambil` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `no_sppb` (`no_sppb`),
  KEY `kode_unit` (`kode_unit`),
  KEY `kode_item` (`kode_item`),
  KEY `idx_sppb_tanggal_nomor` (`tgl_sppb`,`no_sppb`),
  KEY `idx_sppb_unit_status_tglinput` (`kode_unit`,`status`,`tgl_input`,`no_sppb`),
  KEY `idx_sppb_status_tglinput` (`status`,`tgl_input`,`no_sppb`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_stok`;
CREATE TABLE `rsns_custom_logistik_non_medis_stok` (
  `kode_item` varchar(50) NOT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `stok_akhir` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`kode_item`,`kode_lokasi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_stok_batch`;
CREATE TABLE `rsns_custom_logistik_non_medis_stok_batch` (
  `kode_item` varchar(50) NOT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `batch_no` varchar(100) NOT NULL,
  `tgl_expired` date DEFAULT NULL,
  `tgl_terima` date DEFAULT NULL,
  `harga_beli` double NOT NULL DEFAULT '0',
  `stok` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`kode_item`,`kode_lokasi`,`batch_no`),
  KEY `idx_stok_batch_item_lokasi_terima` (`kode_item`,`kode_lokasi`,`tgl_terima`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_terima_rutin`;
CREATE TABLE `rsns_custom_logistik_non_medis_terima_rutin` (
  `no_terima` varchar(50) NOT NULL,
  `no_rencana` varchar(50) DEFAULT NULL,
  `tanggal_terima` date NOT NULL,
  `no_faktur` varchar(50) DEFAULT NULL,
  `kode_vendor` varchar(50) DEFAULT NULL,
  `keterangan` text,
  PRIMARY KEY (`no_terima`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_terima_rutin_detail`;
CREATE TABLE `rsns_custom_logistik_non_medis_terima_rutin_detail` (
  `id_detail` int NOT NULL AUTO_INCREMENT,
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

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_unit`;
CREATE TABLE `rsns_custom_logistik_non_medis_unit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_unit` varchar(50) NOT NULL,
  `nama_unit` varchar(200) NOT NULL,
  `parent_id` int DEFAULT '0',
  `pj_nik` varchar(20) DEFAULT NULL,
  `pj_unit` varchar(100) DEFAULT NULL,
  `gedung` varchar(100) DEFAULT NULL,
  `lantai` varchar(50) DEFAULT NULL,
  `lokasi_detail` text,
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_unit` (`kode_unit`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_user_roles`;
CREATE TABLE `rsns_custom_logistik_non_medis_user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `kode_unit` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_user_unit`;
CREATE TABLE `rsns_custom_logistik_non_medis_user_unit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `kode_unit` (`kode_unit`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_vendor`;
CREATE TABLE `rsns_custom_logistik_non_medis_vendor` (
  `kode_vendor` varchar(50) NOT NULL,
  `nama_vendor` varchar(200) NOT NULL,
  `alamat` text,
  `no_telp` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `npwp` varchar(50) DEFAULT NULL,
  `siup` varchar(50) DEFAULT NULL,
  `status_pkp` enum('PKP','Non PKP') NOT NULL DEFAULT 'Non PKP',
  `nama_bank` varchar(100) DEFAULT NULL,
  `no_rekening` varchar(50) DEFAULT NULL,
  `nama_rekening` varchar(100) DEFAULT NULL,
  `pic_nama` varchar(100) DEFAULT NULL,
  `pic_kontak` varchar(50) DEFAULT NULL,
  `kategori_vendor` varchar(255) DEFAULT NULL,
  `rating` int NOT NULL DEFAULT '0',
  `evaluasi` text,
  `status` enum('Whitelist','Blacklist') NOT NULL DEFAULT 'Whitelist',
  `file_npwp` varchar(255) DEFAULT NULL,
  `file_siup` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`kode_vendor`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_vendor_evaluasi`;
CREATE TABLE `rsns_custom_logistik_non_medis_vendor_evaluasi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_vendor` varchar(50) NOT NULL,
  `jenis_record` enum('Evaluasi','Kontrak','Seleksi') NOT NULL DEFAULT 'Evaluasi',
  `tgl_record` date NOT NULL,
  `nomor_dokumen` varchar(100) DEFAULT NULL,
  `tgl_mulai` date DEFAULT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `nilai_nominal` double DEFAULT '0',
  `skor_kualitas` int DEFAULT '0',
  `skor_waktu` int DEFAULT '0',
  `skor_harga` int DEFAULT '0',
  `skor_respon` int DEFAULT '0',
  `total_skor` decimal(5,2) DEFAULT '0.00',
  `data_dph` longtext,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `keterangan` text,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_wa_contact`;
CREATE TABLE `rsns_custom_logistik_non_medis_wa_contact` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `nomor_wa` varchar(30) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '0',
  `tgl_diperbarui` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `aktif` (`aktif`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_waha_config`;
CREATE TABLE `rsns_custom_logistik_non_medis_waha_config` (
  `id` tinyint(1) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '0',
  `base_url` varchar(255) NOT NULL DEFAULT 'http://127.0.0.1:3000',
  `api_key` varchar(255) DEFAULT NULL,
  `session_name` varchar(100) NOT NULL DEFAULT 'default',
  `delay` tinyint NOT NULL DEFAULT '1',
  `tgl_diperbarui` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_waha_send_log`;
CREATE TABLE `rsns_custom_logistik_non_medis_waha_send_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `tipe` varchar(50) NOT NULL,
  `no_sppb` varchar(100) DEFAULT NULL,
  `tgl_kirim` datetime NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'success',
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `dedupe` (`username`,`tipe`,`no_sppb`,`tgl_kirim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_waha_template`;
CREATE TABLE `rsns_custom_logistik_non_medis_waha_template` (
  `tipe` varchar(50) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `pesan` text NOT NULL,
  `tgl_diperbarui` datetime NOT NULL,
  PRIMARY KEY (`tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_batch`;
CREATE TABLE `rsns_custom_logistik_non_medis_batch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_batch` varchar(100) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `tgl_masuk` datetime DEFAULT NULL,
  `tgl_expired` date DEFAULT NULL,
  `qty` double NOT NULL DEFAULT '0',
  `harga` double NOT NULL DEFAULT '0',
  `status` enum('Aktif','Expired','Blokir') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`id`),
  KEY `kode_item` (`kode_item`),
  KEY `kode_lokasi` (`kode_lokasi`),
  KEY `no_batch` (`no_batch`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_log_signatures`;
CREATE TABLE `rsns_custom_logistik_non_medis_log_signatures` (
  `log_id` int(11) NOT NULL,
  `row_hash` varchar(64) NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_settings`;
CREATE TABLE `rsns_custom_logistik_non_medis_settings` (
  `nama` varchar(100) NOT NULL,
  `nilai` text DEFAULT NULL,
  PRIMARY KEY (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Data awal master dan hak akses















SET FOREIGN_KEY_CHECKS=1;
