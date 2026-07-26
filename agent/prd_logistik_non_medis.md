# Product Requirements Document (PRD): Sistem Logistik Non-Medis

## 1. Pendahuluan
Dokumen ini mendefinisikan spesifikasi dan kebutuhan sistem untuk modul **Logistik Non-Medis** pada Sistem Informasi Manajemen Rumah Sakit (SIMRS) mLite. Modul ini bertujuan untuk mendigitalisasi, memusatkan, dan mengotomatiskan seluruh alur pengelolaan barang non-medis (seperti ATK, peralatan kebersihan, inventaris umum, dan aset tetap) mulai dari perencanaan, pengadaan, pergudangan, distribusi, hingga pelacakan aset dan pelaporan.

## 2. Tujuan Bisnis
- **Meningkatkan Efisiensi**: Mengurangi proses manual dan *paper-based* dalam permintaan barang (SPPB) dan pengadaan.
- **Visibilitas Stok Real-time**: Mengetahui jumlah stok dan lokasi barang secara aktual untuk mencegah kehabisan stok (*stockout*) atau kelebihan stok (*overstock*).
- **Akuntabilitas & Audit**: Memastikan setiap pengeluaran, penerimaan, dan pergerakan aset tercatat dengan baik, valid, dan dapat dilacak (audit trail).
- **Manajemen Aset Terpadu**: Mengelola siklus hidup aset (KIB) mulai dari registrasi, penyusutan, pemeliharaan, hingga penghapusan.

## 3. Pengguna Sistem (User Personas)
1. **Admin Logistik / Kepala Gudang**: Memiliki akses penuh (Master Data, Pengadaan, Gudang, Distribusi, Aset, Laporan, dan Hak Akses).
2. **Staf Gudang (Warehouse Staff)**: Fokus pada penerimaan barang, stock opname, alokasi letak/rak, dan mutasi.
3. **Staf Pengadaan (Purchasing)**: Mengelola master Vendor, PO (Purchase Order), komparasi E-Katalog, dan pencatatan kontrak.
4. **Staf Distribusi**: Menangani verifikasi SPPB dari unit, *packing* pesanan, dan validasi serah terima barang.
5. **Unit Umum (End-User)**: Memiliki hak akses terbatas; hanya dapat membuat permintaan barang (SPPB), melihat riwayat permintaan, dan mengelola stok minimal di unitnya sendiri.

## 4. Ruang Lingkup Fitur (Feature Scope)

### 4.1 Master Data
Sistem harus mampu mengelola data acuan utama (CRUD) dengan dukungan *Import massal via CSV*:
- **Master Barang**: Pengelolaan data induk barang, stok min/max, dan parameter peringatan.
- **Vendor & Rekanan**: Basis data *supplier* dan pihak ketiga untuk jasa servis.
- **Unit & Lokasi Gudang**: Hierarki unit peminta dan manajemen blok/rak/bin gudang pusat.
- **Kategori & Satuan**: Kategori barang umum dan konversi satuan dasar (misal: Box -> Pcs).
- **Kategori Aset & CoA**: Klasifikasi KIB (A, B, C, D, E, F), parameter umur manfaat default, dan pemetaan kode akun keuangan.

### 4.2 Manajemen Pengadaan (Procurement)
- **Perencanaan Pembelian**: Generate rekomendasi belanja (rutin/non-rutin) berdasarkan perhitungan rata-rata pakai dan *safety stock*.
- **PO & Penerimaan**: Pembuatan *Purchase Order* dan verifikasi penerimaan barang fisik vs surat jalan/PO.
- **Manajemen Kontrak & E-Katalog**: Pendataan kontrak kerja dengan vendor (SLA) dan harga E-Katalog pemerintah.

### 4.3 Manajemen Gudang (Warehouse Management)
- **Stock Opname**: Penyesuaian stok sistem dengan jumlah fisik.
- **Manajemen Lokasi (Binning)**: Pelacakan letak pasti (lorong/rak) barang di dalam gudang besar.
- **Mutasi & Barang Rusak**: Pemindahan stok antar gudang cabang dan pendataan pemusnahan barang *waste/expired*.

### 4.4 Distribusi & Pelayanan Unit
- **SPPB (Surat Permintaan Pengeluaran Barang)**: Portal pemesanan *online* bagi unit.
- **Alur Persetujuan & Packing**: Verifikasi oleh admin, pencetakan daftar *packing*, hingga siap dikirim.
- **Serah Terima**: Bukti digital (timestamp) bahwa unit telah menerima barang yang diminta.

### 4.5 Manajemen Aset (Aset Tetap / KIB)
- **Registrasi Aset**: Pendaftaran inventaris baru menjadi entitas unik lengkap dengan *barcode* KIB.
- **Penyusutan Nilai (Depreciation)**: Kalkulasi otomatis depresiasi harga buku aset seiring waktu.
- **Pemeliharaan**: Pencadangan jadwal dan riwayat pemeliharaan/servis mesin.
- **Sensus & Penghapusan**: Pemindaian aset berkala (sensus) dan prosedur pencoretan (*scrapping*) aset hilang/tua.

### 4.6 Laporan & Dashboard
- **Dashboard Analitik Eksekutif**: Menampilkan KPI *real-time* seperti Total Aset, Cost Unit Bulanan, dan status PO/SPPB tertunda.
- **Cetak & Ekspor Data**: Menyediakan format PDF dan Excel untuk semua laporan stok mutasi, distribusi, nilai aset, dan pengadaan.

### 4.7 Keamanan & Hak Akses (RBAC)
- **Role-Based Access Control**: Pengaturan visibilitas menu/fitur yang dinamis berdasarkan *Role* pengguna (ditangani via `rsns_custom_logistik_non_medis_role_permissions`).

## 5. Non-Functional Requirements (NFR)
- **Integrasi**: Sistem merupakan plugin terpadu (*native*) di dalam sistem mLite SIMRS.
- **Usability (UI/UX)**: Antarmuka harus rapi, intuitif, dan responsif. Memiliki notifikasi visual (*badges*) untuk pesanan tertunda, *loading state* saat submit AJAX, dan validasi *form* interaktif.
- **Reliability & Security**: Mencegah *Double-Submit* (pengurangan stok ganda), validasi tipe file yang di-*upload* (hanya CSV), dan filter injeksi SQL pada fitur pencarian.

## 6. Target Tahapan Implementasi
- **Fase 1 (Selesai)**: RBAC & Dashboard, Struktur UI Modul, CRUD & Integrasi Import/Export Master Data.
- **Fase 2 (Segera)**: Siklus Distribusi (SPPB dari unit, Verifikasi, hingga Serah Terima).
- **Fase 3**: Sistem Pengadaan (Perencanaan -> PO -> Penerimaan).
- **Fase 4**: Manajemen Aset Tetap, Depresiasi, Stock Opname, dan Laporan Lengkap.
