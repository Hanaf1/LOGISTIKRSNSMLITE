-- Data contoh khusus tampilan laporan Logistik Non Medis.
-- Seluruh referensi menggunakan penanda DUMMY-LAPORAN agar mudah dikenali.
START TRANSACTION;

-- Idempotent: bersihkan hanya data contoh dari script ini.
DELETE FROM rsns_custom_logistik_non_medis_serah_terima
WHERE no_serah_terima LIKE 'BAST-DUMMY-LAPORAN-%'
   OR no_sppb LIKE 'SPPB/DUMMY-LAPORAN/%';

DELETE FROM rsns_custom_logistik_non_medis_sppb
WHERE no_sppb LIKE 'SPPB/DUMMY-LAPORAN/%';

DELETE FROM rsns_custom_logistik_non_medis_kartu_stok
WHERE no_referensi LIKE 'DUMMY-LAPORAN-%'
   OR no_referensi LIKE 'SPPB/DUMMY-LAPORAN/%';

DELETE FROM rsns_custom_logistik_non_medis_mutasi_detail
WHERE no_mutasi LIKE 'DUMMY-LAPORAN-%';

DELETE FROM rsns_custom_logistik_non_medis_mutasi
WHERE no_mutasi LIKE 'DUMMY-LAPORAN-%';

-- Unit khusus menjaga data contoh agar tidak memakai kuota mingguan unit operasional.
INSERT IGNORE INTO rsns_custom_logistik_non_medis_unit
    (kode_unit, nama_unit, parent_id, pj_unit, gedung, lokasi_detail, status)
VALUES
    ('DUMMY-LAP-001', 'DUMMY LAPORAN - UNIT A', 0, 'PJ Dummy A', 'DUMMY', 'Khusus data contoh laporan', 'Aktif'),
    ('DUMMY-LAP-002', 'DUMMY LAPORAN - UNIT B', 0, 'PJ Dummy B', 'DUMMY', 'Khusus data contoh laporan', 'Aktif'),
    ('DUMMY-LAP-003', 'DUMMY LAPORAN - UNIT C', 0, 'PJ Dummy C', 'DUMMY', 'Khusus data contoh laporan', 'Aktif');

-- SPPB selesai/diterima untuk KPI dan laporan distribusi.
INSERT INTO rsns_custom_logistik_non_medis_sppb
    (no_sppb, tgl_sppb, minggu_ke, kode_unit, jenis_permintaan, jenis_keluar,
     kode_item, jumlah, jumlah_disetujui, harga_satuan_cost, subtotal_cost,
     satuan, status, sifat_permintaan, diajukan_oleh, penanggung_jawab_1,
     ka_unit, keterangan, keterangan_item, user_input, tgl_input,
     user_verifikasi, tgl_verifikasi, diambil_oleh, tgl_diambil)
VALUES
    ('SPPB/DUMMY-LAPORAN/202608/001', '2026-08-01', 31, 'DUMMY-LAP-001', 'Rutin', 'Rutin',
     'BRG0720260001', 6, 5, 40000, 200000, 'pcs', 'Selesai', 'Biasa', 'dummy_laporan',
     'PJ Arofah', 'Kanit Arofah', 'Contoh distribusi rutin untuk laporan.', 'Alat kebersihan unit',
     'dummy_laporan', '2026-08-01 08:15:00', 'logistik_dummy', '2026-08-01 09:00:00',
     'Penerima Arofah', '2026-08-01 10:00:00'),
    ('SPPB/DUMMY-LAPORAN/202608/001', '2026-08-01', 31, 'DUMMY-LAP-001', 'Rutin', 'Rutin',
     'BRG0720260004', 10, 8, 1500, 12000, 'box', 'Selesai', 'Biasa', 'dummy_laporan',
     'PJ Arofah', 'Kanit Arofah', 'Contoh distribusi rutin untuk laporan.', 'Kebutuhan administrasi',
     'dummy_laporan', '2026-08-01 08:15:00', 'logistik_dummy', '2026-08-01 09:00:00',
     'Penerima Arofah', '2026-08-01 10:00:00'),

    ('SPPB/DUMMY-LAPORAN/202608/002', '2026-08-01', 31, 'DUMMY-LAP-002', 'Rutin', 'Rutin',
     'BRG0720260005', 7, 6, 8000, 48000, 'box', 'Selesai', 'Biasa', 'dummy_laporan',
     'PJ Aula', 'Kanit Aula', 'Contoh distribusi rutin untuk laporan.', 'Kebutuhan dokumen',
     'dummy_laporan', '2026-08-01 08:30:00', 'logistik_dummy', '2026-08-01 09:10:00',
     'Penerima Aula', '2026-08-01 10:15:00'),
    ('SPPB/DUMMY-LAPORAN/202608/002', '2026-08-01', 31, 'DUMMY-LAP-002', 'Rutin', 'Rutin',
     'BRG0720260008', 15, 12, 350, 4200, 'Pcs', 'Selesai', 'Biasa', 'dummy_laporan',
     'PJ Aula', 'Kanit Aula', 'Contoh distribusi rutin untuk laporan.', 'Amplop kegiatan',
     'dummy_laporan', '2026-08-01 08:30:00', 'logistik_dummy', '2026-08-01 09:10:00',
     'Penerima Aula', '2026-08-01 10:15:00'),

    ('SPPB/DUMMY-LAPORAN/202608/003', '2026-08-01', 31, 'DUMMY-LAP-003', 'Rutin', 'Rutin',
     'BRG0720260009', 8, 7, 5500, 38500, 'box', 'Diterima', 'Biasa', 'dummy_laporan',
     'PJ Bendahara', 'Kanit Bendahara', 'Contoh distribusi diterima untuk laporan.', 'Dokumen keuangan',
     'dummy_laporan', '2026-08-01 09:00:00', 'logistik_dummy', '2026-08-01 09:30:00',
     'Penerima Bendahara', '2026-08-01 11:00:00');

-- SPPB aktif untuk memperlihatkan variasi status pada laporan periode.
INSERT INTO rsns_custom_logistik_non_medis_sppb
    (no_sppb, tgl_sppb, minggu_ke, kode_unit, jenis_permintaan, jenis_keluar,
     kode_item, jumlah, jumlah_disetujui, satuan, status, sifat_permintaan,
     diajukan_oleh, penanggung_jawab_1, ka_unit, keterangan, user_input, tgl_input,
     user_verifikasi, tgl_verifikasi, user_approve_ka_sie, tgl_approve_ka_sie)
VALUES
    ('SPPB/DUMMY-LAPORAN/202608/004', '2026-08-02', 31, 'DUMMY-LAP-001', 'Rutin', 'Rutin',
     'BRG0720260001', 4, 0, 'pcs', 'Diajukan', 'Biasa', 'dummy_laporan',
     'PJ CSSD', 'Kanit CSSD', 'Contoh SPPB menunggu proses.', 'dummy_laporan', '2026-08-02 08:00:00',
     NULL, NULL, NULL, NULL),
    ('SPPB/DUMMY-LAPORAN/202608/005', '2026-08-02', 31, 'DUMMY-LAP-002', 'Rutin', 'Rutin',
     'BRG0720260004', 12, 10, 'box', 'Proses Logistik', 'Biasa', 'dummy_laporan',
     'PJ Farmasi', 'Kanit Farmasi', 'Contoh SPPB sedang diproses logistik.', 'dummy_laporan', '2026-08-02 08:10:00',
     'logistik_dummy', '2026-08-02 08:30:00', NULL, NULL),
    ('SPPB/DUMMY-LAPORAN/202608/005', '2026-08-02', 31, 'DUMMY-LAP-002', 'Rutin', 'Tambahan',
     'BRG0720260008', 18, 15, 'Pcs', 'Proses Logistik', 'Biasa', 'dummy_laporan',
     'PJ Farmasi', 'Kanit Farmasi', 'Contoh barang tambahan rutin.', 'dummy_laporan', '2026-08-02 08:12:00',
     'logistik_dummy', '2026-08-02 08:30:00', NULL, NULL),
    ('SPPB/DUMMY-LAPORAN/202608/006', '2026-08-02', NULL, 'DUMMY-LAP-003', 'Non Rutin', 'Non Rutin',
     'BRG0720260005', 5, 0, 'box', 'Disetujui Ka. Sie', 'Biasa', 'dummy_laporan',
     'PJ Fisioterapi', 'Kanit Fisioterapi', 'Contoh Non Rutin menunggu persetujuan Kabid.', 'dummy_laporan', '2026-08-02 08:20:00',
     NULL, NULL, 'kasi_dummy', '2026-08-02 08:45:00'),
    ('SPPB/DUMMY-LAPORAN/202608/007', '2026-08-02', NULL, 'DUMMY-LAP-003', 'Non Rutin', 'Non Rutin',
     'BRG0720260009', 6, 6, 'box', 'Proses Pengadaan', 'Urgent', 'dummy_laporan',
     'PJ Bendahara', 'Kanit Bendahara', 'Contoh Non Rutin pada proses pengadaan.', 'dummy_laporan', '2026-08-02 08:25:00',
     'logistik_dummy', '2026-08-02 09:00:00', 'kasi_dummy', '2026-08-02 08:50:00');

INSERT INTO rsns_custom_logistik_non_medis_serah_terima
    (no_serah_terima, no_sppb, tanggal_serah, petugas_pengirim, penerima_nama, penerima_nip, keterangan)
VALUES
    ('BAST-DUMMY-LAPORAN-001', 'SPPB/DUMMY-LAPORAN/202608/001', '2026-08-01 10:00:00', 'logistik_dummy', 'Penerima Arofah', 'DUMMY-001', 'Data contoh laporan'),
    ('BAST-DUMMY-LAPORAN-002', 'SPPB/DUMMY-LAPORAN/202608/002', '2026-08-01 10:15:00', 'logistik_dummy', 'Penerima Aula', 'DUMMY-002', 'Data contoh laporan'),
    ('BAST-DUMMY-LAPORAN-003', 'SPPB/DUMMY-LAPORAN/202608/003', '2026-08-01 11:00:00', 'logistik_dummy', 'Penerima Bendahara', 'DUMMY-003', 'Data contoh laporan');

-- Dokumen mutasi gudang untuk laporan dan jejak transaksi.
INSERT INTO rsns_custom_logistik_non_medis_mutasi
    (no_mutasi, tgl_mutasi, kode_lokasi_asal, kode_lokasi_tujuan, keterangan,
     status, user_input, user_terima, tgl_terima, tgl_input)
VALUES
    ('DUMMY-LAPORAN-M001', '2026-08-01', NULL, 'FISIK-MANUAL', 'Stok masuk contoh awal periode', 'Diterima', 'dummy_laporan', 'logistik_dummy', '2026-08-01 08:00:00', '2026-08-01 07:55:00'),
    ('DUMMY-LAPORAN-M002', '2026-08-01', 'FISIK-MANUAL', NULL, 'Distribusi contoh ke unit', 'Diterima', 'dummy_laporan', 'logistik_dummy', '2026-08-01 11:00:00', '2026-08-01 09:45:00'),
    ('DUMMY-LAPORAN-M003', '2026-08-02', NULL, 'FISIK-MANUAL', 'Stok masuk contoh tambahan', 'Diterima', 'dummy_laporan', 'logistik_dummy', '2026-08-02 08:00:00', '2026-08-02 07:55:00'),
    ('DUMMY-LAPORAN-M004', '2026-08-02', 'FISIK-MANUAL', NULL, 'Pemakaian contoh untuk laporan', 'Diterima', 'dummy_laporan', 'logistik_dummy', '2026-08-02 10:00:00', '2026-08-02 09:55:00');

INSERT INTO rsns_custom_logistik_non_medis_mutasi_detail
    (no_mutasi, kode_item, jenis_mutasi, batch_no, qty, satuan, keterangan)
VALUES
    ('DUMMY-LAPORAN-M001', 'BRG0720260001', 'Masuk', 'DUMMY-BATCH-01', 60, 'pcs', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M001', 'BRG0720260004', 'Masuk', 'DUMMY-BATCH-01', 100, 'box', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M001', 'BRG0720260005', 'Masuk', 'DUMMY-BATCH-01', 40, 'box', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M001', 'BRG0720260008', 'Masuk', 'DUMMY-BATCH-01', 150, 'Pcs', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M001', 'BRG0720260009', 'Masuk', 'DUMMY-BATCH-01', 50, 'box', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M002', 'BRG0720260001', 'Keluar', 'DUMMY-BATCH-01', 5, 'pcs', 'Distribusi SPPB contoh 001'),
    ('DUMMY-LAPORAN-M002', 'BRG0720260004', 'Keluar', 'DUMMY-BATCH-01', 8, 'box', 'Distribusi SPPB contoh 001'),
    ('DUMMY-LAPORAN-M002', 'BRG0720260005', 'Keluar', 'DUMMY-BATCH-01', 6, 'box', 'Distribusi SPPB contoh 002'),
    ('DUMMY-LAPORAN-M002', 'BRG0720260008', 'Keluar', 'DUMMY-BATCH-01', 12, 'Pcs', 'Distribusi SPPB contoh 002'),
    ('DUMMY-LAPORAN-M002', 'BRG0720260009', 'Keluar', 'DUMMY-BATCH-01', 7, 'box', 'Distribusi SPPB contoh 003'),
    ('DUMMY-LAPORAN-M003', 'BRG0720260001', 'Masuk', 'DUMMY-BATCH-02', 20, 'pcs', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M003', 'BRG0720260004', 'Masuk', 'DUMMY-BATCH-02', 40, 'box', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M003', 'BRG0720260005', 'Masuk', 'DUMMY-BATCH-02', 25, 'box', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M003', 'BRG0720260008', 'Masuk', 'DUMMY-BATCH-02', 50, 'Pcs', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M003', 'BRG0720260009', 'Masuk', 'DUMMY-BATCH-02', 20, 'box', 'Data contoh laporan'),
    ('DUMMY-LAPORAN-M004', 'BRG0720260001', 'Keluar', 'DUMMY-BATCH-02', 4, 'pcs', 'Pemakaian contoh'),
    ('DUMMY-LAPORAN-M004', 'BRG0720260004', 'Keluar', 'DUMMY-BATCH-02', 10, 'box', 'Pemakaian contoh'),
    ('DUMMY-LAPORAN-M004', 'BRG0720260005', 'Keluar', 'DUMMY-BATCH-02', 5, 'box', 'Pemakaian contoh'),
    ('DUMMY-LAPORAN-M004', 'BRG0720260008', 'Keluar', 'DUMMY-BATCH-02', 15, 'Pcs', 'Pemakaian contoh'),
    ('DUMMY-LAPORAN-M004', 'BRG0720260009', 'Keluar', 'DUMMY-BATCH-02', 6, 'box', 'Pemakaian contoh');

-- Kartu stok menjadi sumber Laporan Stok & Mutasi.
INSERT INTO rsns_custom_logistik_non_medis_kartu_stok
    (tgl_transaksi, kode_item, kode_lokasi, batch_no, tipe_transaksi,
     no_referensi, qty_masuk, qty_keluar, stok_akhir, harga, user_input)
VALUES
    ('2026-08-01 08:00:00', 'BRG0720260001', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Masuk', 'DUMMY-LAPORAN-M001', 60, 0, 60, 40000, 'dummy_laporan'),
    ('2026-08-01 10:00:00', 'BRG0720260001', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Keluar', 'SPPB/DUMMY-LAPORAN/202608/001', 0, 5, 55, 40000, 'dummy_laporan'),
    ('2026-08-02 08:00:00', 'BRG0720260001', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Masuk', 'DUMMY-LAPORAN-M003', 20, 0, 75, 40000, 'dummy_laporan'),
    ('2026-08-02 10:00:00', 'BRG0720260001', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Keluar', 'DUMMY-LAPORAN-M004', 0, 4, 71, 40000, 'dummy_laporan'),

    ('2026-08-01 08:01:00', 'BRG0720260004', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Masuk', 'DUMMY-LAPORAN-M001', 100, 0, 100, 1500, 'dummy_laporan'),
    ('2026-08-01 10:01:00', 'BRG0720260004', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Keluar', 'SPPB/DUMMY-LAPORAN/202608/001', 0, 8, 92, 1500, 'dummy_laporan'),
    ('2026-08-02 08:01:00', 'BRG0720260004', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Masuk', 'DUMMY-LAPORAN-M003', 40, 0, 132, 1500, 'dummy_laporan'),
    ('2026-08-02 10:01:00', 'BRG0720260004', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Keluar', 'DUMMY-LAPORAN-M004', 0, 10, 122, 1500, 'dummy_laporan'),

    ('2026-08-01 08:02:00', 'BRG0720260005', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Masuk', 'DUMMY-LAPORAN-M001', 40, 0, 40, 8000, 'dummy_laporan'),
    ('2026-08-01 10:02:00', 'BRG0720260005', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Keluar', 'SPPB/DUMMY-LAPORAN/202608/002', 0, 6, 34, 8000, 'dummy_laporan'),
    ('2026-08-02 08:02:00', 'BRG0720260005', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Masuk', 'DUMMY-LAPORAN-M003', 25, 0, 59, 8000, 'dummy_laporan'),
    ('2026-08-02 10:02:00', 'BRG0720260005', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Keluar', 'DUMMY-LAPORAN-M004', 0, 5, 54, 8000, 'dummy_laporan'),

    ('2026-08-01 08:03:00', 'BRG0720260008', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Masuk', 'DUMMY-LAPORAN-M001', 150, 0, 150, 350, 'dummy_laporan'),
    ('2026-08-01 10:03:00', 'BRG0720260008', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Keluar', 'SPPB/DUMMY-LAPORAN/202608/002', 0, 12, 138, 350, 'dummy_laporan'),
    ('2026-08-02 08:03:00', 'BRG0720260008', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Masuk', 'DUMMY-LAPORAN-M003', 50, 0, 188, 350, 'dummy_laporan'),
    ('2026-08-02 10:03:00', 'BRG0720260008', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Keluar', 'DUMMY-LAPORAN-M004', 0, 15, 173, 350, 'dummy_laporan'),

    ('2026-08-01 08:04:00', 'BRG0720260009', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Masuk', 'DUMMY-LAPORAN-M001', 50, 0, 50, 5500, 'dummy_laporan'),
    ('2026-08-01 11:00:00', 'BRG0720260009', 'FISIK-MANUAL', 'DUMMY-BATCH-01', 'Keluar', 'SPPB/DUMMY-LAPORAN/202608/003', 0, 7, 43, 5500, 'dummy_laporan'),
    ('2026-08-02 08:04:00', 'BRG0720260009', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Masuk', 'DUMMY-LAPORAN-M003', 20, 0, 63, 5500, 'dummy_laporan'),
    ('2026-08-02 10:04:00', 'BRG0720260009', 'FISIK-MANUAL', 'DUMMY-BATCH-02', 'Keluar', 'DUMMY-LAPORAN-M004', 0, 6, 57, 5500, 'dummy_laporan');

COMMIT;
