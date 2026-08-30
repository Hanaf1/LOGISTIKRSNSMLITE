
-- -----------------------------------------------------------------------------
-- 3. Table structure for `rsns_custom_logistik_non_medis_aset_pemeliharaan`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_pemeliharaan`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_pemeliharaan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_pemeliharaan` varchar(50) NOT NULL,
  `kode_aset` varchar(50) NOT NULL,
  `jenis_pemeliharaan` enum('Preventive','Corrective') NOT NULL,
  `tanggal_direncanakan` date NOT NULL,
  `tanggal_pelaksanaan` datetime DEFAULT NULL,
  `nama_kegiatan` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `frekuensi` enum('Sekali Saja','1 Bulan','3 Bulan','6 Bulan','1 Tahun','Kustom') DEFAULT 'Sekali Saja',
  `hari_kustom` int(11) DEFAULT '0',
  `prioritas` enum('Rendah','Sedang','Tinggi','Darurat') DEFAULT 'Sedang',
  `kode_rekanan` varchar(50) DEFAULT NULL,
  `nama_teknisi` varchar(150) DEFAULT NULL,
  `tindakan_perbaikan` text DEFAULT NULL,
  `status_kondisi_akhir` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
  `biaya_jasa` double DEFAULT '0',
  `biaya_sparepart` double DEFAULT '0',
  `detail_sparepart` text DEFAULT NULL,
  `total_biaya` double DEFAULT '0',
  `status` enum('Jadwal','Menunggu','Diproses','Selesai','Dibatalkan') DEFAULT 'Jadwal',
  `user_input` varchar(50) NOT NULL,
  `tgl_input` datetime NOT NULL,
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `kode_pemeliharaan` (`kode_pemeliharaan`),
  KEY `kode_aset` (`kode_aset`),
  KEY `status` (`status`),
  KEY `tanggal_direncanakan` (`tanggal_direncanakan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 4. Table structure for `rsns_custom_logistik_non_medis_aset_penghapusan`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_penghapusan`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_penghapusan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_pengajuan` varchar(50) NOT NULL,
  `kode_aset` varchar(100) NOT NULL,
  `tanggal_pengajuan` date NOT NULL,
  `alasan_penghapusan` text NOT NULL,
  `pic_pengusul` varchar(100) NOT NULL,
  `status_kondisi_terakhir` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
  `nilai_buku_terakhir` double DEFAULT '0',
  `nilai_taksiran` double DEFAULT '0',
  `catatan_penilaian` text DEFAULT NULL,
  `tanggal_penilaian` date DEFAULT NULL,
  `petugas_penilai` varchar(100) DEFAULT NULL,
  `metode_penghapusan` enum('Lelang','Hibah','Musnah') DEFAULT NULL,
  `detail_metode` text DEFAULT NULL,
  `no_sk` varchar(100) DEFAULT NULL,
  `tgl_sk` date DEFAULT NULL,
  `file_sk` varchar(255) DEFAULT NULL,
  `no_ba` varchar(100) DEFAULT NULL,
  `tgl_ba` date DEFAULT NULL,
  `file_ba` varchar(255) DEFAULT NULL,
  `keterangan_eksekusi` text DEFAULT NULL,
  `status` enum('Draft','Pengajuan','Dinilai','Disetujui','Selesai','Ditolak') DEFAULT 'Draft',
  `user_input` varchar(100) NOT NULL,
  `tgl_input` datetime NOT NULL,
  `tgl_update` datetime DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `no_pengajuan` (`no_pengajuan`),
  KEY `kode_aset` (`kode_aset`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 5. Table structure for `rsns_custom_logistik_non_medis_aset_penyusutan`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_penyusutan`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_penyusutan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `aset_periode` (`kode_aset`,`periode`),
  KEY `periode` (`periode`),
  KEY `no_jurnal` (`no_jurnal`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 6. Table structure for `rsns_custom_logistik_non_medis_aset_sensus`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_sensus`;
CREATE TABLE `rsns_custom_logistik_non_medis_aset_sensus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_sensus` varchar(200) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `keterangan_sensus` text DEFAULT NULL,
  `status_sensus_periode` enum('Draft','Aktif','Selesai','Dibatalkan') NOT NULL DEFAULT 'Draft',
  `kode_aset` varchar(100) NOT NULL,
  `sistem_kode_unit` varchar(50) NOT NULL,
  `sistem_kode_lokasi` varchar(50) DEFAULT NULL,
  `sistem_status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik',
  `fisik_kode_unit` varchar(50) DEFAULT NULL,
  `fisik_kode_lokasi` varchar(50) DEFAULT NULL,
  `fisik_status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
  `foto_fisik` varchar(255) DEFAULT NULL,
  `catatan_temuan` text DEFAULT NULL,
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
  PRIMARY KEY (`kode_kategori`),
  KEY `kode_aset` (`kode_aset`),
  KEY `nama_sensus` (`nama_sensus`),
  KEY `status_sensus_item` (`status_sensus_item`),
  KEY `sistem_kode_unit` (`sistem_kode_unit`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 7. Table structure for `rsns_custom_logistik_non_medis_barang_rusak`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_barang_rusak`;
CREATE TABLE `rsns_custom_logistik_non_medis_barang_rusak` (
  `no_transaksi` varchar(50) NOT NULL,
  `tgl_transaksi` date NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `batch` varchar(50) DEFAULT NULL,
  `kode_lokasi` varchar(50) DEFAULT NULL,
  `jumlah` double NOT NULL DEFAULT '0',
  `kategori_kerusakan` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tindak_lanjut` enum('Retur','Pemusnahan') DEFAULT NULL,
  `status` enum('Karantina','Selesai') NOT NULL DEFAULT 'Karantina',
  `kode_vendor` varchar(50) DEFAULT NULL,
  `tgl_retur` date DEFAULT NULL,
  `status_retur` varchar(50) DEFAULT NULL,
  `tgl_pemusnahan` date DEFAULT NULL,
  `metode_pemusnahan` varchar(100) DEFAULT NULL,
  `saksi_1` varchar(100) DEFAULT NULL,
  `saksi_2` varchar(100) DEFAULT NULL,
  `catatan_logistik` text DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`no_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 8. Table structure for `rsns_custom_logistik_non_medis_batch`
-- -----------------------------------------------------------------------------
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
  PRIMARY KEY (`kode_kategori`),
  KEY `kode_item` (`kode_item`),
  KEY `kode_lokasi` (`kode_lokasi`),
  KEY `no_batch` (`no_batch`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 9. Table structure for `rsns_custom_logistik_non_medis_ekatalog`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_ekatalog`;
CREATE TABLE `rsns_custom_logistik_non_medis_ekatalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_po` varchar(50) DEFAULT NULL,
  `kode_produk_lkpp` varchar(100) DEFAULT NULL,
  `nama_produk` varchar(255) NOT NULL,
  `merk` varchar(100) DEFAULT NULL,
  `penyedia` varchar(255) DEFAULT NULL,
  `harga_katalog` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `foto_barang` longtext DEFAULT NULL,
  `no_paket_lkpp` varchar(100) DEFAULT NULL,
  `tgl_order` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Master',
  `link_produk` text DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  KEY `no_po` (`no_po`),
  KEY `kode_produk_lkpp` (`kode_produk_lkpp`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 10. Table structure for `rsns_custom_logistik_non_medis_kartu_stok`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kartu_stok`;
CREATE TABLE `rsns_custom_logistik_non_medis_kartu_stok` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 11. Table structure for `rsns_custom_logistik_non_medis_kategori`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kategori`;
CREATE TABLE `rsns_custom_logistik_non_medis_kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT UNIQUE,
  `kode_kategori` varchar(50) NOT NULL,
  `nama_kategori` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 12. Table structure for `rsns_custom_logistik_non_medis_kuota`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_kuota`;
CREATE TABLE `rsns_custom_logistik_non_medis_kuota` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_unit` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `periode_tipe` enum('Bulanan','Triwulan') NOT NULL DEFAULT 'Bulanan',
  `tahun` year(4) NOT NULL,
  `bulan` int(2) DEFAULT NULL,
  `triwulan` int(1) DEFAULT NULL,
  `jumlah` double NOT NULL DEFAULT '0',
  `jenis` enum('Utama','Tambahan') NOT NULL DEFAULT 'Utama',
  `status` enum('Draft','Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft',
  `keterangan` text DEFAULT NULL,
  `keterangan_item` text DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `user_approve` varchar(100) DEFAULT NULL,
  `tgl_approve` datetime DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  KEY `kode_unit` (`kode_unit`),
  KEY `kode_item` (`kode_item`),
  KEY `periode` (`tahun`,`bulan`,`triwulan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 13. Table structure for `rsns_custom_logistik_non_medis_log_signatures`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_log_signatures`;
CREATE TABLE `rsns_custom_logistik_non_medis_log_signatures` (
  `log_id` int(11) NOT NULL,
  `row_hash` varchar(64) NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 14. Table structure for `rsns_custom_logistik_non_medis_lokasi_gudang`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_lokasi_gudang`;
CREATE TABLE `rsns_custom_logistik_non_medis_lokasi_gudang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `kode_lokasi` (`kode_lokasi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 15. Table structure for `rsns_custom_logistik_non_medis_master_barang`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_master_barang`;
CREATE TABLE `rsns_custom_logistik_non_medis_master_barang` (
  `kode_item` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `nama_barang` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `spesifikasi` text DEFAULT NULL,
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
  PRIMARY KEY (`kode_item`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 16. Table structure for `rsns_custom_logistik_non_medis_mutasi`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_mutasi`;
CREATE TABLE `rsns_custom_logistik_non_medis_mutasi` (
  `no_mutasi` varchar(50) NOT NULL,
  `tgl_mutasi` date NOT NULL,
  `kode_lokasi_asal` varchar(50) DEFAULT NULL,
  `kode_lokasi_tujuan` varchar(50) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status` enum('Draft','Dikirim','Diterima','Batal') NOT NULL DEFAULT 'Draft',
  `user_input` varchar(100) DEFAULT NULL,
  `user_terima` varchar(100) DEFAULT NULL,
  `tgl_terima` datetime DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  PRIMARY KEY (`no_mutasi`),
  KEY `kode_lokasi_asal` (`kode_lokasi_asal`),
  KEY `kode_lokasi_tujuan` (`kode_lokasi_tujuan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 17. Table structure for `rsns_custom_logistik_non_medis_mutasi_detail`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_mutasi_detail`;
CREATE TABLE `rsns_custom_logistik_non_medis_mutasi_detail` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `no_mutasi` varchar(50) NOT NULL,
    `kode_item` varchar(50) NOT NULL,
    `jenis_mutasi` enum('Masuk','Keluar','Penyesuaian') NOT NULL DEFAULT 'Penyesuaian',
    `batch_no` varchar(100) DEFAULT '-',
    `qty` double NOT NULL DEFAULT '0',
    `satuan` varchar(50) DEFAULT NULL,
    `keterangan` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `no_mutasi` (`no_mutasi`),
    KEY `kode_item` (`kode_item`)
  ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 18. Table structure for `rsns_custom_logistik_non_medis_opname`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_opname`;
CREATE TABLE `rsns_custom_logistik_non_medis_opname` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_opname` varchar(50) NOT NULL,
  `tgl_opname` date DEFAULT NULL,
  `tgl_jadwal` date DEFAULT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `kode_item` varchar(50) DEFAULT NULL,
  `stok_sistem` double NOT NULL DEFAULT '0',
  `stok_fisik` double NOT NULL DEFAULT '0',
  `selisih` double NOT NULL DEFAULT '0',
  `keterangan` text DEFAULT NULL,
  `status` enum('Jadwal','Draft','Selesai') NOT NULL DEFAULT 'Jadwal',
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  KEY `no_opname` (`no_opname`),
  KEY `kode_lokasi` (`kode_lokasi`),
  KEY `kode_item` (`kode_item`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 19. Table structure for `rsns_custom_logistik_non_medis_packing`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_packing`;
CREATE TABLE `rsns_custom_logistik_non_medis_packing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_packing` varchar(50) NOT NULL,
  `no_sppb` varchar(50) NOT NULL,
  `tgl_packing` datetime NOT NULL,
  `petugas_packing` varchar(100) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `qty_picked` double NOT NULL,
  `koli_ke` int(11) DEFAULT '1',
  `total_berat_koli` double DEFAULT '0',
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  KEY `no_packing` (`no_packing`),
  KEY `no_sppb` (`no_sppb`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 20. Table structure for `rsns_custom_logistik_non_medis_penerimaan`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_penerimaan`;
CREATE TABLE `rsns_custom_logistik_non_medis_penerimaan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_penerimaan` varchar(50) NOT NULL,
  `tgl_penerimaan` date NOT NULL,
  `no_po` varchar(50) NOT NULL,
  `kode_vendor` varchar(50) NOT NULL,
  `no_faktur` varchar(100) DEFAULT NULL,
  `no_surat_jalan` varchar(100) DEFAULT NULL,
  `file_faktur` varchar(255) DEFAULT NULL,
  `file_surat_jalan` varchar(255) DEFAULT NULL,
  `kode_item` varchar(50) NOT NULL,
  `qty_terima` double NOT NULL DEFAULT '0',
  `qty_tolak` double NOT NULL DEFAULT '0',
  `batch_no` varchar(100) DEFAULT NULL,
  `tgl_expired` date DEFAULT NULL,
  `harga` double NOT NULL DEFAULT '0',
  `keterangan` text DEFAULT NULL,
  `kode_lokasi` varchar(50) DEFAULT NULL,
  `tipe_penerimaan` varchar(20) NOT NULL DEFAULT 'PO',
  `sumber_manual` varchar(150) DEFAULT NULL,
  `alasan_tanpa_po` text DEFAULT NULL,
  `status` enum('Draft','Menunggu Verifikasi','Selesai','Ditolak') NOT NULL DEFAULT 'Draft',
  `stok_diposting` tinyint(1) NOT NULL DEFAULT '0',
  `diverifikasi_oleh` varchar(100) DEFAULT NULL,
  `tgl_verifikasi` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 21. Table structure for `rsns_custom_logistik_non_medis_pengaturan`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_pengaturan`;
CREATE TABLE `rsns_custom_logistik_non_medis_pengaturan` (
  `nama_pengaturan` varchar(100) NOT NULL,
  `nilai` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`nama_pengaturan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 22. Table structure for `rsns_custom_logistik_non_medis_pengiriman`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_pengiriman`;
CREATE TABLE `rsns_custom_logistik_non_medis_pengiriman` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_sppb` varchar(50) NOT NULL,
  `no_manifest` varchar(100) DEFAULT NULL,
  `kurir` varchar(100) DEFAULT NULL,
  `kendaraan` varchar(100) DEFAULT NULL,
  `status` enum('Proses','Dikirim','Diterima') NOT NULL DEFAULT 'Proses',
  `waktu_packing` datetime DEFAULT NULL,
  `waktu_kirim` datetime DEFAULT NULL,
  `waktu_terima` datetime DEFAULT NULL,
  `penerima` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  KEY `no_sppb` (`no_sppb`),
  KEY `no_manifest` (`no_manifest`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 23. Table structure for `rsns_custom_logistik_non_medis_perencanaan`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_perencanaan`;
CREATE TABLE `rsns_custom_logistik_non_medis_perencanaan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_perencanaan` varchar(50) NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `tahun` year(4) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
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
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 24. Table structure for `rsns_custom_logistik_non_medis_po`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_po`;
CREATE TABLE `rsns_custom_logistik_non_medis_po` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_po` varchar(60) NOT NULL,
  `no_pr` varchar(50) DEFAULT NULL,
  `kode_vendor` varchar(50) NOT NULL,
  `total_nilai` double NOT NULL DEFAULT '0',
  `kode_unit` varchar(50) NOT NULL,
  `tgl_po` date NOT NULL,
  `tgl_kirim_target` date DEFAULT NULL,
  `tgl_kirim_aktual` date DEFAULT NULL,
  `termin_pembayaran` varchar(100) DEFAULT NULL,
  `kode_item` varchar(50) NOT NULL,
  `satuan` varchar(50) NOT NULL,
  `qty_pesan` double NOT NULL DEFAULT '0',
  `qty_diterima` double NOT NULL DEFAULT '0',
  `harga_satuan` double NOT NULL DEFAULT '0',
  `diskon` double NOT NULL DEFAULT '0',
  `ppn` double NOT NULL DEFAULT '0',
  `grand_total` double NOT NULL DEFAULT '0',
  `detail_items` longtext NOT NULL,
  `catatan` text DEFAULT NULL,
  `subtotal` double NOT NULL DEFAULT '0',
  `ppn_persen` double NOT NULL DEFAULT '11',
  `catatan_item` text DEFAULT NULL,
  `catatan_po` text DEFAULT NULL,
  `status` enum('Draft','Terkirim','Sebagian Diterima','Selesai','Dibatalkan','Diamandemen') NOT NULL DEFAULT 'Draft',
  `tgl_kirim` datetime DEFAULT NULL,
  `alasan_batal` text DEFAULT NULL,
  `versi` int(11) NOT NULL DEFAULT '1',
  `no_po_asal` varchar(60) DEFAULT NULL,
  `file_po` varchar(255) DEFAULT NULL,
  `tgl_dikirim` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 25. Table structure for `rsns_custom_logistik_non_medis_pr`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_pr`;
CREATE TABLE `rsns_custom_logistik_non_medis_pr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_pr` varchar(50) NOT NULL,
  `tgl_pr` date NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `jumlah` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) NOT NULL,
  `justifikasi` text DEFAULT NULL,
  `file_justifikasi` varchar(255) DEFAULT NULL,
  `status` enum('Draft','Diajukan','Disetujui','Ditolak','Selesai') NOT NULL DEFAULT 'Draft',
  `petugas_logistik` varchar(100) DEFAULT NULL,
  `tgl_acc` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 26. Table structure for `rsns_custom_logistik_non_medis_rekanan_jasa`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_rekanan_jasa`;
CREATE TABLE `rsns_custom_logistik_non_medis_rekanan_jasa` (
  `kode_rekanan` varchar(50) NOT NULL,
  `nama_rekanan` varchar(200) NOT NULL,
  `kategori` enum('Vendor Servis','Kontraktor') DEFAULT 'Vendor Servis',
  `alamat` text DEFAULT NULL,
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

-- -----------------------------------------------------------------------------
-- 27. Table structure for `rsns_custom_logistik_non_medis_report_schedules`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_report_schedules`;
CREATE TABLE `rsns_custom_logistik_non_medis_report_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_name` varchar(100) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `sub_report_type` varchar(50) NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `send_time` time NOT NULL DEFAULT '07:00:00',
  `send_day` int(2) DEFAULT NULL,
  `email_recipients` text NOT NULL,
  `filters_json` text DEFAULT NULL,
  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `last_run` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 28. Table structure for `rsns_custom_logistik_non_medis_report_verifications`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_report_verifications`;
CREATE TABLE `rsns_custom_logistik_non_medis_report_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verification_hash` varchar(64) NOT NULL,
  `report_name` varchar(100) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `generated_by` varchar(100) NOT NULL,
  `generated_at` datetime NOT NULL,
  `checksum_data` text NOT NULL,
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `verification_hash` (`verification_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 29. Table structure for `rsns_custom_logistik_non_medis_retur_unit`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_retur_unit`;
CREATE TABLE `rsns_custom_logistik_non_medis_retur_unit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_retur` varchar(50) NOT NULL,
  `tgl_retur` date NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `no_sppb` varchar(50) NOT NULL,
  `kode_item` varchar(50) NOT NULL,
  `batch_no` varchar(50) DEFAULT NULL,
  `qty` double NOT NULL DEFAULT '0',
  `alasan` enum('Salah Kirim','Sisa','Rusak') NOT NULL DEFAULT 'Sisa',
  `kondisi_fisik` text DEFAULT NULL,
  `inspeksi` text DEFAULT NULL,
  `status` enum('Pending','Disetujui','Ditolak') NOT NULL DEFAULT 'Pending',
  `petugas` varchar(100) DEFAULT NULL,
  `tgl_approval` datetime DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  KEY `no_retur` (`no_retur`),
  KEY `kode_unit` (`kode_unit`),
  KEY `no_sppb` (`no_sppb`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 30. Table structure for `rsns_custom_logistik_non_medis_satuan`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_satuan`;
CREATE TABLE `rsns_custom_logistik_non_medis_satuan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_satuan` varchar(50) NOT NULL,
  `nama_satuan` varchar(100) NOT NULL,
  `satuan_dasar` varchar(50) DEFAULT NULL,
  `nilai_konversi` double NOT NULL DEFAULT '1',
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `kode_satuan` (`kode_satuan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 31. Table structure for `rsns_custom_logistik_non_medis_serah_terima`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_serah_terima`;
CREATE TABLE `rsns_custom_logistik_non_medis_serah_terima` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_serah_terima` varchar(50) NOT NULL,
  `no_sppb` varchar(50) NOT NULL,
  `tanggal_serah` datetime NOT NULL,
  `petugas_pengirim` varchar(100) NOT NULL,
  `penerima_nama` varchar(100) NOT NULL,
  `penerima_nip` varchar(50) DEFAULT NULL,
  `foto_kondisi` varchar(255) DEFAULT NULL,
  `tanda_terima` longtext DEFAULT NULL,
  `tanda_terima_format` varchar(20) NOT NULL DEFAULT 'STROKES_V1',
  `tanda_terima_hash` char(64) DEFAULT NULL,
  `tanda_terima_ip` varchar(45) DEFAULT NULL,
  `tanda_terima_user_agent` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `arsip_bast` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_serah_terima` (`no_serah_terima`),
  KEY `no_sppb` (`no_sppb`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 32. Table structure for `rsns_custom_logistik_non_medis_settings`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_settings`;
CREATE TABLE `rsns_custom_logistik_non_medis_settings` (
  `nama` varchar(100) NOT NULL,
  `nilai` text DEFAULT NULL,
  PRIMARY KEY (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 33. Table structure for `rsns_custom_logistik_non_medis_sppb`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_sppb`;
CREATE TABLE `rsns_custom_logistik_non_medis_sppb` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_sppb` varchar(50) NOT NULL,
  `tgl_sppb` date NOT NULL,
  `kode_unit` varchar(50) NOT NULL,
  `jenis_permintaan` varchar(50) NOT NULL DEFAULT 'Rutin',
  `sumber_pemenuhan` varchar(30) DEFAULT NULL,
  `referensi_pemenuhan` text DEFAULT NULL,
  `catatan_pemenuhan` text DEFAULT NULL,
  `user_tindak_lanjut` varchar(100) DEFAULT NULL,
  `tgl_tindak_lanjut` datetime DEFAULT NULL,
  `kode_item` varchar(50) NOT NULL,
  `jumlah` double NOT NULL DEFAULT '0',
  `jumlah_disetujui` double NOT NULL DEFAULT '0',
  `satuan` varchar(50) DEFAULT NULL,
  `status` enum('Draft','Diajukan','Disetujui Unit','Terverifikasi','Picking','Packing','Ready','Dikirim','Diterima','Selesai','Ditolak') NOT NULL DEFAULT 'Draft',
  `keterangan` text DEFAULT NULL,
  `alasan_penolakan` text DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  `tgl_input` datetime DEFAULT NULL,
  `user_approve_unit` varchar(100) DEFAULT NULL,
  `tgl_approve_unit` datetime DEFAULT NULL,
  `user_verifikasi` varchar(100) DEFAULT NULL,
  `tgl_verifikasi` datetime DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`),
  KEY `no_sppb` (`no_sppb`),
  KEY `kode_unit` (`kode_unit`),
  KEY `kode_item` (`kode_item`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 34. Table structure for `rsns_custom_logistik_non_medis_stok`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_stok`;
CREATE TABLE `rsns_custom_logistik_non_medis_stok` (
  `kode_item` varchar(50) NOT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `stok_akhir` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`kode_item`,`kode_lokasi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 35. Table structure for `rsns_custom_logistik_non_medis_stok_batch`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_stok_batch`;
CREATE TABLE `rsns_custom_logistik_non_medis_stok_batch` (
  `kode_item` varchar(50) NOT NULL,
  `kode_lokasi` varchar(50) NOT NULL,
  `batch_no` varchar(100) NOT NULL,
  `tgl_expired` date DEFAULT NULL,
  `tgl_terima` date DEFAULT NULL,
  `harga_beli` double NOT NULL DEFAULT '0',
  `stok` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`kode_item`,`kode_lokasi`,`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 36. Table structure for `rsns_custom_logistik_non_medis_unit`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_unit`;
CREATE TABLE `rsns_custom_logistik_non_medis_unit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_unit` varchar(50) NOT NULL,
  `nama_unit` varchar(200) NOT NULL,
  `parent_id` int(11) DEFAULT '0',
  `pj_unit` varchar(100) DEFAULT NULL,
  `gedung` varchar(100) DEFAULT NULL,
  `lantai` varchar(50) DEFAULT NULL,
  `lokasi_detail` text DEFAULT NULL,

  `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (`kode_kategori`),
  UNIQUE KEY `kode_unit` (`kode_unit`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 37. Table structure for `rsns_custom_logistik_non_medis_vendor`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_vendor`;
CREATE TABLE `rsns_custom_logistik_non_medis_vendor` (
  `kode_vendor` varchar(50) NOT NULL,
  `nama_vendor` varchar(200) NOT NULL,
  `alamat` text DEFAULT NULL,
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
  `rating` int(1) NOT NULL DEFAULT '0',
  `evaluasi` text DEFAULT NULL,
  `status` enum('Whitelist','Blacklist') NOT NULL DEFAULT 'Whitelist',
  `file_npwp` varchar(255) DEFAULT NULL,
  `file_siup` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`kode_vendor`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- -----------------------------------------------------------------------------
-- 38. Table structure for `rsns_custom_logistik_non_medis_vendor_evaluasi`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_vendor_evaluasi`;
CREATE TABLE `rsns_custom_logistik_non_medis_vendor_evaluasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_vendor` varchar(50) NOT NULL,
  `jenis_record` enum('Evaluasi','Kontrak','Seleksi') NOT NULL DEFAULT 'Evaluasi',
  `tgl_record` date NOT NULL,
  `nomor_dokumen` varchar(100) DEFAULT NULL,
  `tgl_mulai` date DEFAULT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `nilai_nominal` double DEFAULT '0',
  `skor_kualitas` int(3) DEFAULT '0',
  `skor_waktu` int(3) DEFAULT '0',
  `skor_harga` int(3) DEFAULT '0',
  `skor_respon` int(3) DEFAULT '0',
  `total_skor` decimal(5,2) DEFAULT '0.00',
  `data_dph` longtext DEFAULT NULL,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `user_input` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`kode_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

SET FOREIGN_KEY_CHECKS = 1;
