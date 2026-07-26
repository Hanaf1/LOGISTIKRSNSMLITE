-- Jalankan sekali pada database yang sudah ada:
-- mysql -uroot mlite_rsns < plugins/logistik_non_medis/performance_indexes.sql
-- Indeks disusun dari pola filter, join, dan urutan pada menu logistik yang sering dibuka.

CREATE INDEX idx_barang_kategori_item ON rsns_custom_logistik_non_medis_master_barang (kode_kategori, kode_item);
CREATE INDEX idx_barang_status_nama ON rsns_custom_logistik_non_medis_master_barang (status, nama_barang);

CREATE INDEX idx_sppb_tanggal_nomor ON rsns_custom_logistik_non_medis_sppb (tgl_sppb, no_sppb);
CREATE INDEX idx_sppb_unit_status_tglinput ON rsns_custom_logistik_non_medis_sppb (kode_unit, status, tgl_input, no_sppb);
CREATE INDEX idx_sppb_status_tglinput ON rsns_custom_logistik_non_medis_sppb (status, tgl_input, no_sppb);

CREATE INDEX idx_po_tanggal_status ON rsns_custom_logistik_non_medis_po (tgl_po, status);
CREATE INDEX idx_perencanaan_tahun_kode ON rsns_custom_logistik_non_medis_perencanaan (tahun, kode_perencanaan);
CREATE INDEX idx_aset_status ON rsns_custom_logistik_non_medis_aset (status);

CREATE INDEX idx_mutasi_tanggal_status ON rsns_custom_logistik_non_medis_mutasi (tgl_mutasi, status);
CREATE INDEX idx_mutasi_detail_nomor_item ON rsns_custom_logistik_non_medis_mutasi_detail (no_mutasi, kode_item);
CREATE INDEX idx_penerimaan_tanggal_po ON rsns_custom_logistik_non_medis_penerimaan (tgl_penerimaan, no_po);
CREATE INDEX idx_kartu_stok_item_lokasi_tanggal ON rsns_custom_logistik_non_medis_kartu_stok (kode_item, kode_lokasi, tgl_transaksi, id);
CREATE INDEX idx_stok_batch_item_lokasi_terima ON rsns_custom_logistik_non_medis_stok_batch (kode_item, kode_lokasi, tgl_terima);
