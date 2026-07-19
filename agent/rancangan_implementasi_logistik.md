# Implementasi Sistem Logistik Rumah Sakit (Logistik Non-Medis)

Rancangan ini bertujuan untuk mengimplementasikan secara penuh alur logistik dari *Admin Sistem*, *Logistik Umum*, hingga *Unit RS* ke dalam plugin `logistik_non_medis`, sesuai dengan daftar fitur komprehensif yang telah disepakati.

## Perubahan yang Diusulkan

Perubahan difokuskan pada modul-modul di dalam direktori `plugins/logistik_non_medis/`.

### 1. Modul Master Data (Admin Sistem)
Fokus pada penyesuaian Master Data Barang untuk memastikan batas stok minimum (*Low Stock Limit*) berjalan dengan baik.
- **Admin.php:** Menambahkan validasi `stok_min` pada metode Master Barang.
- Menambahkan integrasi Master Data Lokasi dan Rekanan/Supplier.

### 2. Modul Distribusi & Permintaan (Unit RS & Logistik Umum)
Meliputi fitur Permintaan (SPPB), validasi parsial, serah terima, dan konfirmasi.
- **Admin.php:** Menyempurnakan metode `distribusiserahterima` agar menyimpan status konfirmasi "Telah Diterima oleh Unit", dan menyempurnakan metode validasi (Siap, Kosong, Parsial) di SPPB.
- **distribusi.serahterima.html:** Menambahkan UI tombol **Konfirmasi Terima** untuk otorisasi pihak Unit.
- **distribusi.sppb.form.html:** UI untuk menandai barang Rutin vs Cito (Urgent).

### 3. Modul Manajemen Stok & Gudang (Logistik Umum)
Penerimaan barang dari Supplier dan pergerakan mutasi (Kartu Stok).
- **schema.sql:** Memastikan tabel mutasi/kartu stok dapat mencatat tipe mutasi: Masuk, Keluar, Saldo.
- **Admin.php:** Logika otomatis pemotongan stok pada tabel barang saat status "Diserahkan", dan penambahan query Kartu Stok.
- **gudang.stok.html:** Menambahkan tampilan khusus/modal untuk melihat **Kartu Stok (Riwayat Pergerakan Real-time)**.

### 4. Modul Pengadaan (Purchasing)
Siklus pembuatan *Purchase Order (PO)* saat stok kosong/kritis.
- **pengadaan.po.form.html:** Pembenahan UI pembuatan *Purchase Order* agar otomatis menarik data barang yang kosong dari *Draft* Pengadaan.

---

*Catatan: Dokumen ini telah diajukan ke dalam sistem Artifact untuk proses review Anda.*


buatkan fitur import di semua data master di logistik non medis