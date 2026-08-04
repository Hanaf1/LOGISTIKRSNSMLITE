-- ============================================================================
-- MIGRASI SCHEMA LOGISTIK NON MEDIS
-- Sumber : Rifangga99/mlite_rsns @ 027fcfd
-- Target : Schema RSNS terbaru per 02 Agustus 2026
-- Sifat  : Additive, idempotent, dan tidak menghapus data operasional
-- ============================================================================
--
-- WAJIB SEBELUM MENJALANKAN:
--   1. Backup database penuh.
--   2. Jalankan terlebih dahulu pada salinan database/staging.
--   3. Pastikan database aktif adalah database mLITE yang benar.
--
-- TIDAK ADA perintah DROP TABLE, DROP COLUMN, TRUNCATE, atau DELETE data.
-- Kolom KIB lama dipertahankan untuk kompatibilitas arsip.
--
-- Contoh CLI:
--   mysql -u USER -p NAMA_DATABASE \
--     < plugins/logistik_non_medis/migrate_rifangga_027fcfd_to_rsns_latest.sql

SET NAMES utf8mb4;
SET @RSNS_SCHEMA_VERSION = '2026.08.02-rifangga-027fcfd-to-rsns';

-- --------------------------------------------------------------------------
-- Helper migrasi. Semua ALTER memeriksa keberadaan tabel/kolom/index dahulu.
-- --------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS rsns_add_column_if_missing;
DROP PROCEDURE IF EXISTS rsns_modify_column_if_exists;
DROP PROCEDURE IF EXISTS rsns_add_index_if_missing;
DROP PROCEDURE IF EXISTS rsns_assert_sppb_status_safe;

DELIMITER $$

CREATE PROCEDURE rsns_add_column_if_missing(
    IN p_table VARCHAR(128),
    IN p_column VARCHAR(128),
    IN p_definition TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @rsns_sql = CONCAT(
            'ALTER TABLE `', REPLACE(p_table, '`', '``'),
            '` ADD COLUMN `', REPLACE(p_column, '`', '``'), '` ',
            p_definition
        );
        PREPARE rsns_stmt FROM @rsns_sql;
        EXECUTE rsns_stmt;
        DEALLOCATE PREPARE rsns_stmt;
    END IF;
END$$

CREATE PROCEDURE rsns_modify_column_if_exists(
    IN p_table VARCHAR(128),
    IN p_column VARCHAR(128),
    IN p_definition TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @rsns_sql = CONCAT(
            'ALTER TABLE `', REPLACE(p_table, '`', '``'),
            '` MODIFY COLUMN `', REPLACE(p_column, '`', '``'), '` ',
            p_definition
        );
        PREPARE rsns_stmt FROM @rsns_sql;
        EXECUTE rsns_stmt;
        DEALLOCATE PREPARE rsns_stmt;
    END IF;
END$$

CREATE PROCEDURE rsns_add_index_if_missing(
    IN p_table VARCHAR(128),
    IN p_index VARCHAR(128),
    IN p_columns TEXT,
    IN p_unique TINYINT
)
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @rsns_sql = CONCAT(
            'ALTER TABLE `', REPLACE(p_table, '`', '``'), '` ADD ',
            IF(p_unique = 1, 'UNIQUE ', ''),
            'INDEX `', REPLACE(p_index, '`', '``'), '` (', p_columns, ')'
        );
        PREPARE rsns_stmt FROM @rsns_sql;
        EXECUTE rsns_stmt;
        DEALLOCATE PREPARE rsns_stmt;
    END IF;
END$$

CREATE PROCEDURE rsns_assert_sppb_status_safe()
BEGIN
    DECLARE v_unknown INT DEFAULT 0;
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'rsns_custom_logistik_non_medis_sppb'
    ) THEN
        SELECT COUNT(*) INTO v_unknown
        FROM rsns_custom_logistik_non_medis_sppb
        WHERE status IS NOT NULL
          AND status NOT IN (
            'Draft','Diajukan','Disetujui Ka. Unit','Disetujui Ka. Sie',
            'Disetujui Kabid','Disetujui Unit','Diserahkan ke Kasie Umum',
            'Verifikasi Kasie Umum','Diteruskan ke Logistik Umum',
            'Rekap Logistik','Logistik Umum & Rekap','Konsultasi Dana',
            'Konsul Pengajuan ke Kabid Umum','Diserahkan ke Keuangan',
            'Pengajuan Dana ke Bendahara','Tidak ACC','Proses',
            'Terverifikasi','Proses Logistik','Proses Pengadaan','Siap Ambil',
            'Siap Diserahkan','Picking','Packing','Ready','Dikirim',
            'Diterima','Selesai','Ditolak','Dibatalkan'
          );
        IF v_unknown > 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Migrasi dihentikan: ada status SPPB lama yang belum dipetakan.';
        END IF;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------------------------
-- Riwayat versi schema
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_schema_migrations (
    version VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    applied_at DATETIME NOT NULL,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------------
-- Hak akses. Data role yang sudah ada tidak ditimpa.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_user_roles (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'unit',
    kode_unit TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_role_permissions (
    role VARCHAR(50) NOT NULL,
    permissions TEXT DEFAULT NULL,
    PRIMARY KEY (role)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CALL rsns_modify_column_if_exists(
    'rsns_custom_logistik_non_medis_user_roles',
    'kode_unit',
    'TEXT DEFAULT NULL'
);

INSERT IGNORE INTO rsns_custom_logistik_non_medis_role_permissions (role, permissions) VALUES
('admin', 'manage,hakakses'),
('logistik', 'manage'),
('gudang', 'manage'),
('aset', 'manage'),
('unit', 'manage,distribusisppb,distribusiretur,distribusikuota'),
('kepala_unit', 'manage,distribusisppb,distribusikuota'),
('kepala_sie', 'manage,distribusisppb,distribusikuota'),
('kepala_bidang', 'manage,distribusisppb,distribusikuota');

UPDATE rsns_custom_logistik_non_medis_role_permissions
SET permissions = CONCAT(TRIM(BOTH ',' FROM COALESCE(permissions, '')), ',hakakses')
WHERE role = 'admin'
  AND CONCAT(',', COALESCE(permissions, ''), ',') NOT LIKE '%,hakakses,%';

-- --------------------------------------------------------------------------
-- Master barang dan perencanaan
-- --------------------------------------------------------------------------
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'kategori', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'jenis_item', 'ENUM(''Rutin'',''Non Rutin'') NOT NULL DEFAULT ''Rutin''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'tipe_barang', 'ENUM(''Habis Pakai'',''Aset'') NOT NULL DEFAULT ''Habis Pakai''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'kode_kategori', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'stok_min', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'stok_max', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'safety_stock', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_master_barang', 'default_kode_lokasi', 'VARCHAR(50) DEFAULT NULL');

CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_perencanaan', 'kelompok_barang', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_perencanaan', 'bulan', 'VARCHAR(2) NOT NULL DEFAULT ''01''');

-- --------------------------------------------------------------------------
-- Purchase Order dan penerimaan
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_po (
    id INT NOT NULL AUTO_INCREMENT,
    no_po VARCHAR(50) NOT NULL,
    tgl_po DATE NOT NULL,
    kode_vendor VARCHAR(50) NOT NULL,
    sumber_tipe VARCHAR(20) DEFAULT NULL,
    no_rencana VARCHAR(50) DEFAULT NULL,
    total_nilai DOUBLE NOT NULL DEFAULT 0,
    diskon DOUBLE NOT NULL DEFAULT 0,
    ppn DOUBLE NOT NULL DEFAULT 0,
    grand_total DOUBLE NOT NULL DEFAULT 0,
    detail_items LONGTEXT NOT NULL,
    catatan TEXT DEFAULT NULL,
    status ENUM('Draft','Terkirim','Sebagian Diterima','Selesai','Diamandemen','Dibatalkan') NOT NULL DEFAULT 'Draft',
    tgl_kirim DATETIME DEFAULT NULL,
    file_po VARCHAR(255) DEFAULT NULL,
    user_input VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY no_po (no_po)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'sumber_tipe', 'VARCHAR(20) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'no_rencana', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'total_nilai', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'diskon', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'ppn', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'grand_total', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'detail_items', 'LONGTEXT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'catatan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'status', 'ENUM(''Draft'',''Terkirim'',''Sebagian Diterima'',''Selesai'',''Diamandemen'',''Dibatalkan'') NOT NULL DEFAULT ''Draft''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'tgl_kirim', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'file_po', 'VARCHAR(255) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_po', 'user_input', 'VARCHAR(100) DEFAULT NULL');

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_penerimaan (
    id INT NOT NULL AUTO_INCREMENT,
    no_penerimaan VARCHAR(50) NOT NULL,
    tgl_penerimaan DATE NOT NULL,
    no_po VARCHAR(50) NOT NULL,
    kode_vendor VARCHAR(50) NOT NULL,
    no_faktur VARCHAR(100) DEFAULT NULL,
    no_surat_jalan VARCHAR(100) DEFAULT NULL,
    file_faktur VARCHAR(255) DEFAULT NULL,
    file_surat_jalan VARCHAR(255) DEFAULT NULL,
    kode_item VARCHAR(50) NOT NULL,
    nama_barang VARCHAR(255) DEFAULT NULL,
    qty_po DOUBLE NOT NULL DEFAULT 0,
    qty_terima DOUBLE NOT NULL DEFAULT 0,
    qty_tolak DOUBLE NOT NULL DEFAULT 0,
    batch_no VARCHAR(100) DEFAULT NULL,
    tgl_expired DATE DEFAULT NULL,
    harga DOUBLE NOT NULL DEFAULT 0,
    keterangan TEXT DEFAULT NULL,
    kode_lokasi VARCHAR(50) DEFAULT NULL,
    status ENUM('Draft','Selesai') NOT NULL DEFAULT 'Draft',
    user_input VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'no_faktur', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'no_surat_jalan', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'file_faktur', 'VARCHAR(255) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'file_surat_jalan', 'VARCHAR(255) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'nama_barang', 'VARCHAR(255) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'qty_po', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'qty_terima', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'qty_tolak', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'batch_no', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'tgl_expired', 'DATE DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'harga', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'keterangan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'kode_lokasi', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'status', 'ENUM(''Draft'',''Selesai'') NOT NULL DEFAULT ''Draft''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'user_input', 'VARCHAR(100) DEFAULT NULL');

-- --------------------------------------------------------------------------
-- Gudang, batch, dan kartu stok
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_stok_batch (
    kode_item VARCHAR(50) NOT NULL,
    kode_lokasi VARCHAR(50) NOT NULL,
    batch_no VARCHAR(100) NOT NULL,
    tgl_expired DATE DEFAULT NULL,
    tgl_terima DATE DEFAULT NULL,
    harga_beli DOUBLE NOT NULL DEFAULT 0,
    stok DOUBLE NOT NULL DEFAULT 0,
    PRIMARY KEY (kode_item, kode_lokasi, batch_no)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_pengaturan (
    nama_pengaturan VARCHAR(100) NOT NULL,
    nilai VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (nama_pengaturan)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT IGNORE INTO rsns_custom_logistik_non_medis_pengaturan (nama_pengaturan, nilai)
VALUES ('metode_stok', 'FIFO');

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_kartu_stok (
    id INT NOT NULL AUTO_INCREMENT,
    tgl_transaksi DATETIME NOT NULL,
    kode_item VARCHAR(50) NOT NULL,
    kode_lokasi VARCHAR(50) NOT NULL,
    batch_no VARCHAR(100) DEFAULT '-',
    tipe_transaksi ENUM('Masuk','Keluar','Retur','Opname','Mutasi Masuk','Mutasi Keluar') NOT NULL,
    no_referensi VARCHAR(50) NOT NULL,
    qty_masuk DOUBLE NOT NULL DEFAULT 0,
    qty_keluar DOUBLE NOT NULL DEFAULT 0,
    stok_akhir DOUBLE NOT NULL DEFAULT 0,
    harga DOUBLE NOT NULL DEFAULT 0,
    user_input VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_kartu_stok', 'batch_no', 'VARCHAR(100) DEFAULT ''-''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_kartu_stok', 'harga', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_modify_column_if_exists(
    'rsns_custom_logistik_non_medis_kartu_stok',
    'tipe_transaksi',
    'ENUM(''Masuk'',''Keluar'',''Retur'',''Opname'',''Mutasi Masuk'',''Mutasi Keluar'') NOT NULL'
);

-- --------------------------------------------------------------------------
-- SPPB: rutin, tambahan, non rutin, cost, approval, dan pengambilan
-- --------------------------------------------------------------------------
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'minggu_ke', 'TINYINT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'jenis_permintaan', 'VARCHAR(50) NOT NULL DEFAULT ''Rutin''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'jenis_keluar', 'VARCHAR(30) NOT NULL DEFAULT ''Rutin''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'item_sumber', 'ENUM(''master'',''manual'') NOT NULL DEFAULT ''master''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'nama_barang_manual', 'VARCHAR(255) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'spesifikasi_manual', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'estimasi_harga', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'latar_belakang_tujuan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'sasaran_kegunaan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'rencana_digunakan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'jumlah_disetujui', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'harga_satuan_cost', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'subtotal_cost', 'DOUBLE NOT NULL DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'sifat_permintaan', 'VARCHAR(20) NOT NULL DEFAULT ''Biasa''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'diajukan_oleh', 'VARCHAR(150) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'penanggung_jawab_1', 'VARCHAR(150) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'penanggung_jawab_2', 'VARCHAR(150) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'ka_unit', 'VARCHAR(150) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'keterangan_item', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'alasan_penolakan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'ditolak_pada_status', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'keterangan_verifikasi', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'user_cost', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'tgl_cost', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'user_approve_ka_unit', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'tgl_approve_ka_unit', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'user_approve_ka_sie', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'tgl_approve_ka_sie', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'user_approve_ka_bidang', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'tgl_approve_ka_bidang', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'user_approve_unit', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'tgl_approve_unit', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'user_verifikasi', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'tgl_verifikasi', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'diambil_oleh', 'VARCHAR(150) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_sppb', 'tgl_diambil', 'DATETIME DEFAULT NULL');

CALL rsns_assert_sppb_status_safe();
CALL rsns_modify_column_if_exists(
    'rsns_custom_logistik_non_medis_sppb',
    'status',
    'ENUM(''Draft'',''Diajukan'',''Disetujui Ka. Unit'',''Disetujui Ka. Sie'',''Disetujui Kabid'',''Disetujui Unit'',''Diserahkan ke Kasie Umum'',''Verifikasi Kasie Umum'',''Diteruskan ke Logistik Umum'',''Rekap Logistik'',''Logistik Umum & Rekap'',''Konsultasi Dana'',''Konsul Pengajuan ke Kabid Umum'',''Diserahkan ke Keuangan'',''Pengajuan Dana ke Bendahara'',''Tidak ACC'',''Proses'',''Terverifikasi'',''Proses Logistik'',''Proses Pengadaan'',''Siap Ambil'',''Siap Diserahkan'',''Picking'',''Packing'',''Ready'',''Dikirim'',''Diterima'',''Selesai'',''Ditolak'',''Dibatalkan'') NOT NULL DEFAULT ''Draft'''
);

-- --------------------------------------------------------------------------
-- Aset dan Master Inventaris terbaru. KIB lama tidak dihapus.
-- --------------------------------------------------------------------------
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'nomor_inventaris', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'nomor_dokumen', 'VARCHAR(200) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kode_kategori_aset', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'merk_type', 'VARCHAR(150) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'tahun_beli', 'SMALLINT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'satuan', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'jumlah', 'INT NOT NULL DEFAULT 1');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kode_lokasi', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'lokasi_fisik', 'VARCHAR(150) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'bahan', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'keterangan_inventaris', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'masa_manfaat_tahun', 'INT DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'nilai_residu', 'DOUBLE DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'akumulasi_penyusutan', 'DOUBLE DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'nilai_buku', 'DOUBLE DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'tgl_penyusutan_terakhir', 'DATE DEFAULT NULL');

-- Kolom legacy hanya ditambahkan bila schema Rifangga belum memilikinya.
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_jenis', 'ENUM(''A'',''B'',''C'',''D'',''E'',''F'') DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_luas', 'DOUBLE DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_alamat', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_hak', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_tgl_sertifikat', 'DATE DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_no_sertifikat', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_penggunaan', 'VARCHAR(255) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_merk', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_ukuran', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_bahan', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_no_pabrik', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_no_rangka', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_no_mesin', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_no_polisi', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_no_bpkb', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_bertingkat', 'ENUM(''Ya'',''Tidak'') DEFAULT ''Tidak''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_beton', 'ENUM(''Ya'',''Tidak'') DEFAULT ''Tidak''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_status_tanah', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_konstruksi', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_panjang', 'DOUBLE DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_lebar', 'DOUBLE DEFAULT 0');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_judul', 'VARCHAR(255) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_pencipta', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_proyek_bangunan', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_tgl_mulai', 'DATE DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_tgl_rencana_selesai', 'DATE DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset', 'kib_progress_persen', 'DOUBLE DEFAULT 0');

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_inventaris_master (
    id INT NOT NULL AUTO_INCREMENT,
    jenis_master ENUM('UNIT','BARANG') NOT NULL,
    kode VARCHAR(50) NOT NULL,
    kode_inventaris CHAR(3) DEFAULT NULL,
    kode_kategori CHAR(1) NOT NULL DEFAULT '',
    nama VARCHAR(200) NOT NULL,
    kode_kelompok CHAR(2) DEFAULT NULL,
    kode_jenis CHAR(2) DEFAULT NULL,
    kode_barang CHAR(2) DEFAULT NULL,
    nama_kelompok VARCHAR(150) DEFAULT NULL,
    nama_jenis VARCHAR(150) DEFAULT NULL,
    kib_jenis ENUM('A','B','C','D','E','F') DEFAULT NULL,
    status ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
    tgl_input DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY jenis_kode (jenis_master, kode_kategori, kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_inventaris_kategori (
    kode_kategori CHAR(1) NOT NULL,
    nama_kategori VARCHAR(100) NOT NULL,
    PRIMARY KEY (kode_kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_inventaris_kelompok (
    kode_kategori CHAR(1) NOT NULL,
    kode_kelompok CHAR(2) NOT NULL,
    nama_kelompok VARCHAR(150) NOT NULL,
    PRIMARY KEY (kode_kategori, kode_kelompok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_inventaris_jenis (
    kode_kategori CHAR(1) NOT NULL,
    kode_kelompok CHAR(2) NOT NULL,
    kode_jenis CHAR(2) NOT NULL,
    nama_jenis VARCHAR(150) NOT NULL,
    PRIMARY KEY (kode_kategori, kode_kelompok, kode_jenis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO rsns_custom_logistik_non_medis_inventaris_kategori
    (kode_kategori, nama_kategori)
VALUES ('1', 'Medis'), ('2', 'Non Medis');

-- Unit lama dibawa ke Master Inventaris tanpa mengubah tabel unit asli.
-- kode_inventaris memakai ID lama bila berada pada rentang 001-999.
INSERT IGNORE INTO rsns_custom_logistik_non_medis_inventaris_master
    (jenis_master, kode, kode_inventaris, kode_kategori, nama, status, tgl_input)
SELECT
    'UNIT',
    u.kode_unit,
    CASE WHEN u.id BETWEEN 1 AND 999 THEN LPAD(u.id, 3, '0') ELSE NULL END,
    '',
    u.nama_unit,
    CASE WHEN u.status = 'Aktif' THEN 'Aktif' ELSE 'Nonaktif' END,
    NOW()
FROM rsns_custom_logistik_non_medis_unit u;

-- Barang tidak dipetakan otomatis karena schema Rifangga tidak memiliki kode
-- Kelompok/Jenis/Barang dua digit yang dapat dipercaya. Isi melalui menu
-- Master Inventaris atau impor terkontrol agar klasifikasi tidak keliru.

-- Aset mutasi: migrasi additive, tidak melakukan DROP TABLE.
CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_aset_mutasi (
    id INT NOT NULL AUTO_INCREMENT,
    no_mutasi VARCHAR(50) DEFAULT NULL,
    kode_aset VARCHAR(100) NOT NULL,
    kode_unit_asal VARCHAR(50) DEFAULT NULL,
    kode_unit_tujuan VARCHAR(50) DEFAULT NULL,
    kode_lokasi_asal VARCHAR(50) DEFAULT NULL,
    kode_lokasi_tujuan VARCHAR(50) DEFAULT NULL,
    pic_asal VARCHAR(100) DEFAULT NULL,
    pic_tujuan VARCHAR(100) DEFAULT NULL,
    keterangan TEXT DEFAULT NULL,
    tanggal_mutasi DATE DEFAULT NULL,
    status ENUM('Draft','Diajukan','Disetujui Asal','Selesai','Ditolak') NOT NULL DEFAULT 'Draft',
    alasan_penolakan TEXT DEFAULT NULL,
    user_approval_asal VARCHAR(100) DEFAULT NULL,
    tgl_approval_asal DATETIME DEFAULT NULL,
    user_approval_tujuan VARCHAR(100) DEFAULT NULL,
    tgl_approval_tujuan DATETIME DEFAULT NULL,
    user_mutasi VARCHAR(100) DEFAULT NULL,
    tgl_input DATETIME DEFAULT NULL,
    tgl_update DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY kode_aset (kode_aset)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'no_mutasi', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'kode_lokasi_asal', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'kode_lokasi_tujuan', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'status', 'ENUM(''Draft'',''Diajukan'',''Disetujui Asal'',''Selesai'',''Ditolak'') NOT NULL DEFAULT ''Draft''');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'alasan_penolakan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'user_approval_asal', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'tgl_approval_asal', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'user_approval_tujuan', 'VARCHAR(100) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'tgl_approval_tujuan', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'tgl_input', 'DATETIME DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_aset_mutasi', 'tgl_update', 'DATETIME DEFAULT NULL');

-- --------------------------------------------------------------------------
-- Perencanaan pengadaan baru
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_rencana_rutin (
    no_rencana VARCHAR(50) NOT NULL,
    tahun INT NOT NULL,
    bulan VARCHAR(2) NOT NULL,
    tanggal_buat DATE NOT NULL,
    status ENUM('Draft','Disetujui','Ditolak','Selesai') NOT NULL DEFAULT 'Draft',
    alasan_penolakan TEXT DEFAULT NULL,
    keterangan TEXT DEFAULT NULL,
    kode_vendor VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (no_rencana)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_rencana_rutin_detail (
    id_detail INT NOT NULL AUTO_INCREMENT,
    no_rencana VARCHAR(50) NOT NULL,
    kode_item VARCHAR(50) NOT NULL,
    qty_rencana DOUBLE NOT NULL,
    estimasi_harga DOUBLE NOT NULL,
    qty_realisasi DOUBLE NOT NULL DEFAULT 0,
    PRIMARY KEY (id_detail),
    KEY no_rencana (no_rencana),
    KEY kode_item (kode_item)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_terima_rutin (
    no_terima VARCHAR(50) NOT NULL,
    no_rencana VARCHAR(50) DEFAULT NULL,
    tanggal_terima DATE NOT NULL,
    no_faktur VARCHAR(50) DEFAULT NULL,
    kode_vendor VARCHAR(50) DEFAULT NULL,
    keterangan TEXT DEFAULT NULL,
    PRIMARY KEY (no_terima)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_terima_rutin_detail (
    id_detail INT NOT NULL AUTO_INCREMENT,
    no_terima VARCHAR(50) NOT NULL,
    kode_item VARCHAR(50) NOT NULL,
    qty_terima DOUBLE NOT NULL,
    harga_beli DOUBLE NOT NULL,
    total DOUBLE NOT NULL,
    PRIMARY KEY (id_detail),
    KEY no_terima (no_terima),
    KEY kode_item (kode_item)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_rencana_nonrutin (
    no_rencana VARCHAR(50) NOT NULL,
    kode_unit VARCHAR(50) DEFAULT NULL,
    tahun INT NOT NULL,
    tanggal_buat DATE NOT NULL,
    status ENUM('Draft','Disetujui','Ditolak','Selesai') NOT NULL DEFAULT 'Draft',
    alasan_penolakan TEXT DEFAULT NULL,
    keterangan TEXT DEFAULT NULL,
    PRIMARY KEY (no_rencana)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_rencana_nonrutin_detail (
    id_detail INT NOT NULL AUTO_INCREMENT,
    no_rencana VARCHAR(50) NOT NULL,
    nama_barang VARCHAR(255) NOT NULL,
    kategori VARCHAR(50) DEFAULT NULL,
    qty_rencana DOUBLE NOT NULL,
    estimasi_harga DOUBLE NOT NULL,
    qty_realisasi DOUBLE NOT NULL DEFAULT 0,
    alasan TEXT DEFAULT NULL,
    PRIMARY KEY (id_detail),
    KEY no_rencana (no_rencana)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_rencana_rutin', 'alasan_penolakan', 'TEXT DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_rencana_nonrutin', 'kode_unit', 'VARCHAR(50) DEFAULT NULL');
CALL rsns_add_column_if_missing('rsns_custom_logistik_non_medis_rencana_nonrutin', 'alasan_penolakan', 'TEXT DEFAULT NULL');

-- --------------------------------------------------------------------------
-- Notifikasi database + default Workerman/Pusher
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rsns_custom_logistik_non_medis_notifikasi (
    id INT NOT NULL AUTO_INCREMENT,
    user_target VARCHAR(100) NOT NULL,
    pesan TEXT NOT NULL,
    tipe VARCHAR(50) DEFAULT NULL,
    url VARCHAR(255) DEFAULT NULL,
    is_read TINYINT NOT NULL DEFAULT 0,
    tgl_dibuat DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY user_target (user_target),
    KEY is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default adalah Workerman. Credential Pusher tetap kosong dan tidak boleh
-- ditulis langsung ke repository.
INSERT INTO mlite_settings (module, field, value)
SELECT 'logistik_non_medis', 'notif_realtime', 'workerman'
WHERE NOT EXISTS (
    SELECT 1 FROM mlite_settings
    WHERE module = 'logistik_non_medis' AND field = 'notif_realtime'
);

INSERT INTO mlite_settings (module, field, value)
SELECT 'logistik_non_medis', 'pusher_app_id', ''
WHERE NOT EXISTS (
    SELECT 1 FROM mlite_settings
    WHERE module = 'logistik_non_medis' AND field = 'pusher_app_id'
);

INSERT INTO mlite_settings (module, field, value)
SELECT 'logistik_non_medis', 'pusher_key', ''
WHERE NOT EXISTS (
    SELECT 1 FROM mlite_settings
    WHERE module = 'logistik_non_medis' AND field = 'pusher_key'
);

INSERT INTO mlite_settings (module, field, value)
SELECT 'logistik_non_medis', 'pusher_secret', ''
WHERE NOT EXISTS (
    SELECT 1 FROM mlite_settings
    WHERE module = 'logistik_non_medis' AND field = 'pusher_secret'
);

INSERT INTO mlite_settings (module, field, value)
SELECT 'logistik_non_medis', 'pusher_cluster', 'ap1'
WHERE NOT EXISTS (
    SELECT 1 FROM mlite_settings
    WHERE module = 'logistik_non_medis' AND field = 'pusher_cluster'
);

-- --------------------------------------------------------------------------
-- Indeks performa. Aman dijalankan ulang.
-- --------------------------------------------------------------------------
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_master_barang', 'idx_barang_kategori_item', '`kode_kategori`,`kode_item`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_master_barang', 'idx_barang_status_nama', '`status`,`nama_barang`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_sppb', 'idx_sppb_tanggal_nomor', '`tgl_sppb`,`no_sppb`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_sppb', 'idx_sppb_unit_status_tglinput', '`kode_unit`,`status`,`tgl_input`,`no_sppb`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_sppb', 'idx_sppb_status_tglinput', '`status`,`tgl_input`,`no_sppb`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_po', 'idx_po_tanggal_status', '`tgl_po`,`status`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_perencanaan', 'idx_perencanaan_tahun_kode', '`tahun`,`kode_perencanaan`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_aset', 'idx_aset_status', '`status`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_penerimaan', 'idx_penerimaan_tanggal_po', '`tgl_penerimaan`,`no_po`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_kartu_stok', 'idx_kartu_stok_item_lokasi_tanggal', '`kode_item`,`kode_lokasi`,`tgl_transaksi`,`id`', 0);
CALL rsns_add_index_if_missing('rsns_custom_logistik_non_medis_stok_batch', 'idx_stok_batch_item_lokasi_terima', '`kode_item`,`kode_lokasi`,`tgl_terima`', 0);

-- --------------------------------------------------------------------------
-- Catat versi dan bersihkan helper
-- --------------------------------------------------------------------------
INSERT IGNORE INTO rsns_custom_logistik_non_medis_schema_migrations
    (version, description, applied_at)
VALUES
    (@RSNS_SCHEMA_VERSION, 'Migrasi additive dari Rifangga 027fcfd ke schema RSNS terbaru', NOW());

DROP PROCEDURE IF EXISTS rsns_add_column_if_missing;
DROP PROCEDURE IF EXISTS rsns_modify_column_if_exists;
DROP PROCEDURE IF EXISTS rsns_add_index_if_missing;
DROP PROCEDURE IF EXISTS rsns_assert_sppb_status_safe;

-- --------------------------------------------------------------------------
-- Ringkasan verifikasi. Hasil ini tidak mengubah data.
-- --------------------------------------------------------------------------
SELECT version, description, applied_at
FROM rsns_custom_logistik_non_medis_schema_migrations
WHERE version = @RSNS_SCHEMA_VERSION;

SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rsns_custom_logistik_non_medis_sppb') AS kolom_sppb,
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rsns_custom_logistik_non_medis_aset') AS kolom_aset,
    (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rsns_custom_logistik_non_medis_inventaris_master') AS master_inventaris_tersedia,
    (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rsns_custom_logistik_non_medis_notifikasi') AS notifikasi_tersedia;

SELECT 'MIGRASI SCHEMA RSNS SELESAI - periksa seluruh hasil verifikasi di atas.' AS hasil;
