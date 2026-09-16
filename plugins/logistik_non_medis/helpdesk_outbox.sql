-- ============================================================================
-- Skema antrean kirim (outbox) laporan aset ke sistem helpdesk.
--
-- Dua tabel saja, dan keduanya TIDAK bergantung pada helpdesk mana yang dipakai.
-- Bentuk pemanggilan (REST, e-mail, atau ditarik) baru menyusul setelah jelas
-- helpdesk-nya bicara dengan cara apa; skema ini tetap sama untuk ketiganya.
--
-- Pola ini meniru integrasi WhatsApp yang sudah jalan di modul ini
-- (waha_config + waha_send_log), supaya tidak ada gaya baru yang perlu dipelajari.
--
-- Prinsip yang dipegang:
--   1. Halaman QR tidak pernah memanggil helpdesk saat tombol Simpan ditekan.
--      Laporan masuk antrean, pengirimannya urusan proses terpisah. Kalau
--      helpdesk mati, pengisi tetap dapat konfirmasi dan datanya tidak hilang.
--   2. Setiap laporan punya kunci idempoten. Percobaan ulang tidak pernah
--      membuat tiket kedua di helpdesk.
--   3. Helpdesk memiliki siklus hidup tiket; sistem ini hanya menyalin status
--      terakhir yang diketahui. Tidak ada dua pemilik kebenaran.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Konfigurasi sambungan. Satu baris saja (id selalu 1).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_helpdesk_config` (
  `id` tinyint(1) NOT NULL DEFAULT 1,
  `aktif` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = laporan tetap diantrekan tapi belum dikirim ke mana pun',
  `metode` enum('REST','EMAIL') NOT NULL DEFAULT 'REST' COMMENT 'EMAIL dipakai bila helpdesk hanya bisa membuat tiket dari surel masuk',
  `base_url` varchar(255) DEFAULT NULL COMMENT 'contoh: https://helpdesk.rsnurusyifa.local',
  `endpoint_tiket` varchar(255) DEFAULT NULL COMMENT 'jalur pembuatan tiket, contoh: /api/v1/tickets',
  `nama_header_auth` varchar(100) DEFAULT 'Authorization' COMMENT 'Authorization, X-API-Key, atau sesuai dokumentasi helpdesk',
  `pola_nilai_auth` varchar(100) DEFAULT 'Bearer {kunci}' COMMENT '{kunci} diganti isi kolom api_key saat dikirim',
  `api_key` varchar(255) DEFAULT NULL,
  `email_tujuan` varchar(255) DEFAULT NULL COMMENT 'dipakai saat metode = EMAIL',
  `maks_percobaan` tinyint(2) NOT NULL DEFAULT 5,
  `jeda_percobaan_menit` smallint(4) NOT NULL DEFAULT 10 COMMENT 'jeda dasar; dikalikan jumlah percobaan agar makin renggang',
  `tgl_diperbarui` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `rsns_custom_logistik_non_medis_helpdesk_config`
  (`id`, `aktif`, `metode`, `tgl_diperbarui`) VALUES (1, 0, 'REST', NOW());

-- ---------------------------------------------------------------------------
-- 2. Antrean kirim. Satu baris = satu laporan yang harus sampai ke helpdesk.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_helpdesk_outbox` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,

  -- Kunci idempoten: dikirim ke helpdesk di setiap percobaan. Nilainya diambil
  -- dari nomor dokumen yang sudah ada supaya bisa ditelusuri dua arah, contoh
  -- 'LOG-202609-0001' untuk buku maintenance atau 'SNS-6065' untuk baris sensus.
  -- UNIQUE di sini yang mencegah satu laporan masuk antrean dua kali.
  `kunci_idempoten` varchar(100) NOT NULL,

  `jenis` enum('Maintenance','Sensus','Kerusakan') NOT NULL,
  `kode_aset` varchar(100) NOT NULL,

  -- Penunjuk balik ke baris asalnya, supaya dari outbox bisa dilacak sumbernya
  -- tanpa menebak, misalnya rsns_custom_logistik_non_medis_aset_pemeliharaan.
  `ref_tabel` varchar(100) NOT NULL,
  `ref_id` bigint(20) NOT NULL,

  -- Isi laporan apa adanya saat dibuat. Disimpan utuh supaya kiriman ulang
  -- mengirim isi yang sama persis walau data asalnya sudah berubah setelahnya.
  `muatan` json NOT NULL,

  `status` enum('Antri','Terkirim','Gagal','Batal') NOT NULL DEFAULT 'Antri',
  `percobaan` tinyint(2) NOT NULL DEFAULT 0,
  `percobaan_berikutnya` datetime DEFAULT NULL COMMENT 'NULL = boleh dikirim sekarang',

  -- Diisi dari balasan helpdesk. Inilah yang menghubungkan kedua sistem.
  `tiket_helpdesk` varchar(100) DEFAULT NULL,
  `status_helpdesk` varchar(50) DEFAULT NULL COMMENT 'status terakhir yang diketahui; helpdesk tetap pemiliknya',
  `tgl_sinkron` datetime DEFAULT NULL,

  `kode_respons` smallint(4) DEFAULT NULL COMMENT 'kode HTTP percobaan terakhir',
  `pesan_error` text DEFAULT NULL,

  `tgl_dibuat` datetime NOT NULL,
  `tgl_terkirim` datetime DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `kunci_idempoten` (`kunci_idempoten`),
  KEY `antrean` (`status`, `percobaan_berikutnya`),
  KEY `kode_aset` (`kode_aset`),
  KEY `tiket_helpdesk` (`tiket_helpdesk`),
  KEY `sumber` (`ref_tabel`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- Bentuk isi kolom `muatan`
--
-- Nama kolom di helpdesk hampir pasti berbeda. Pemetaan ke nama mereka
-- dilakukan di adapter saat mengirim, bukan di sini — supaya kalau helpdesk
-- diganti, isi tabel lama tetap terbaca.
--
-- {
--   "kunci": "LOG-202609-0001",
--   "jenis": "Maintenance",
--   "waktu_lapor": "2026-09-16 14:11:28",
--   "pelapor": "Budi, Multazam 9",
--   "judul": "Dudukan sofa robek dan busa keluar",
--   "keterangan": "Sejak minggu lalu, busa bagian kiri keluar.",
--   "prioritas": "Sedang",
--   "aset": {
--     "kode_aset": "AST-992060602201",
--     "nomor_inventaris": "992060602201",
--     "nama_aset": "Sofa Bed",
--     "kode_unit": "99",
--     "nama_unit": "Multazam 9",
--     "lokasi_fisik": "Multazam 9 (99)",
--     "kondisi_tercatat": "Baik",
--     "tautan_qr": "http://.../public/aset-info.php?kode=AST-992060602201"
--   }
-- }
--
-- Blok "aset" itu inti nilainya: helpdesk tidak tahu nomor inventaris, unit,
-- atau lokasi. Di sinilah sistem ini berperan sebagai perantara — bukan sekadar
-- meneruskan keluhan, tapi melengkapinya dengan data aset yang hanya ada di sini.
-- ============================================================================

-- ============================================================================
-- Alur pemakaian
--
--  Isi form di halaman QR
--      -> baris tersimpan di tabel asalnya (aset_pemeliharaan / aset_sensus)
--      -> baris outbox dibuat berstatus 'Antri'
--      -> pengisi langsung dapat konfirmasi, tidak menunggu helpdesk
--
--  Cron tiap beberapa menit:
--      SELECT * FROM ..._helpdesk_outbox
--       WHERE status = 'Antri'
--         AND (percobaan_berikutnya IS NULL OR percobaan_berikutnya <= NOW())
--       ORDER BY id LIMIT 20;
--
--      berhasil -> status='Terkirim', tiket_helpdesk diisi, tgl_terkirim=NOW()
--      gagal    -> percobaan+1, percobaan_berikutnya=NOW()+(jeda*percobaan),
--                  pesan_error diisi; setelah melewati maks_percobaan -> 'Gagal'
--
--  Status balik dari helpdesk (bila nanti dua arah):
--      UPDATE ..._helpdesk_outbox
--         SET status_helpdesk = ?, tgl_sinkron = NOW()
--       WHERE tiket_helpdesk = ?;
-- ============================================================================
