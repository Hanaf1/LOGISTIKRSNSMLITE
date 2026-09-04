# mLITE - Sistem Informasi Logistik Non-Medis

Aplikasi manajemen logistik non-medis rumah sakit berbasis PHP dengan arsitektur mLITE Framework. Modul ini disesuaikan untuk mengelola alur permintaan barang, persediaan, inventaris aset, pengadaan, distribusi, dan pelaporan logistik secara terintegrasi.

## Fitur Modul Logistik Non-Medis

### Permintaan rutin dan nonrutin

- **Permintaan rutin** untuk kebutuhan barang yang sudah tersedia di master barang dan mengikuti alur distribusi logistik.
- **Permintaan nonrutin** untuk barang baru atau kebutuhan khusus, dilengkapi jenis/sifat permintaan, spesifikasi, latar belakang, tujuan penggunaan, estimasi harga, dan lampiran foto.
- **Alur persetujuan bertahap** berdasarkan unit, kepala unit, kepala seksi, kepala bidang, dan logistik/manajemen.
- **Draft, verifikasi, pengadaan, penerimaan, pengambilan, serah terima, penolakan, dan riwayat permintaan**.
- **Cetak dokumen SPPB dan surat pengadaan nonrutin**.

### Master barang dan inventaris

- **Master Data Barang**: kode barang, nama, kategori, subkategori, jenis item, satuan dasar/konversi, harga referensi, status, batas stok minimum/maksimum, safety stock, dan lokasi default.
- **Master klasifikasi inventaris**: kategori, kelompok, jenis aset, unit, lokasi, satuan, vendor/rekanan, dan COA.
- **Penyesuaian master data barang inventaris**: tambah, ubah, hapus, pembaruan kategori secara massal, pencarian, filter, ekspor, dan sinkronisasi data.
- **Registrasi aset/KIB**: nomor inventaris, kode aset, spesifikasi, nilai aset, kondisi, unit/lokasi, serial number, dan pengelompokan aset.
- **Label dan QR Code inventaris** untuk identifikasi serta akses informasi aset.

### Gudang, stok, dan distribusi

- **Stok gudang dan kartu stok** dengan satuan dasar, konversi, batch, tanggal kedaluwarsa, harga, dan nilai persediaan.
- **Penyesuaian data stok** untuk koreksi stok awal/akhir dan pembaruan data master yang terhubung dengan persediaan.
- **Stock opname** dengan pencatatan stok sistem, stok fisik, selisih, dan tindak lanjut koreksi.
- **Penerimaan barang, retur, barang rusak, produksi, mutasi antar gudang, packing, dan serah terima unit**.
- **Kuota pengeluaran unit**, pemantauan stok minimum, dan peringatan barang mendekati kedaluwarsa.

### Cost unit dan pelaporan

- **Laporan Cost Unit** untuk melihat penggunaan barang dan nilai biaya per unit.
- **Periode laporan mingguan dan bulanan**, dengan filter tanggal, unit, barang, kategori, dan status transaksi.
- **Laporan stok, mutasi, pengadaan, distribusi, inventaris, nilai aset, KPI, serta ekspor/cetak laporan**.

### Manajemen aset dan akses

- **Mutasi aset**, pemeliharaan/perbaikan, jadwal pemeliharaan, work order, penyusutan metode garis lurus, sensus fisik, dan penghapusan/disposal aset.
- **Role-Based Access Control (RBAC)** untuk administrator, logistik, kepala unit/seksi/bidang, dan pengguna unit.
- **Notifikasi real-time** melalui antrean Workerman untuk pembaruan status permintaan dan proses logistik.

---

## Persyaratan Sistem

- **PHP**: Versi 7.0 s/d 8.1 (Sangat direkomendasikan menggunakan PHP 7.3)
- **Database**: MySQL / MariaDB
- **Web Server**: Apache dengan modul `mod_rewrite` aktif (untuk *pretty URL*) atau Nginx
- **Dependency Manager**: Composer

---

## Cara Instalasi & Konfigurasi

### 1. Kloning / Unduh Proyek
Unduh zip proyek ini dari GitHub atau kloning menggunakan Git:
```bash
git clone https://github.com/Hanaf1/LOGISTIKRSNSMLITE.git
```
Pindahkan folder proyek ke direktori web root Anda (misalnya `C:/xampp/htdocs/mlite-5.2.0`).

### 2. Instal Dependensi Composer
Karena folder `vendor` diabaikan dalam repositori ini, Anda harus menginstalnya terlebih dahulu dengan menjalankan perintah berikut di direktori proyek:
```bash
composer install
```

### 3. Impor Database
1. Buka **phpMyAdmin** atau GUI Database client favorit Anda.
2. Buat database baru bernama `mlite`.
3. Impor berkas database yang sesuai:
   - Gunakan **`mlite_db.sql`** jika Anda melakukan instalasi baru dari awal (termasuk skema dasar SIMKES Khanza).
   - Gunakan **`mlite_only.sql`** jika Anda hanya ingin menambahkan tabel mLITE ke database SIMKES Khanza yang sudah ada.

### 4. Konfigurasi Koneksi Database
Buka berkas konfigurasi di `config.php` pada root direktori proyek dan sesuaikan dengan setelan server lokal Anda:
```php
define('DBHOST', 'localhost');
define('DBPORT', '3306');
define('DBUSER', 'root');
define('DBPASS', ''); // Isi password mysql Anda jika ada
define('DBNAME', 'mlite');
```

---

## Kredensial Login Default (Default Users)

Setelah mengimpor database, Anda dapat masuk ke dalam sistem menggunakan akun default berikut:

| Peran (Role) | Username | Password | Deskripsi |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin` | Akses penuh ke seluruh menu administrasi, pengaturan, dan manajemen modul. |

> [!WARNING]
> Sangat disarankan untuk segera mengubah kata sandi default setelah berhasil masuk untuk pertama kalinya demi keamanan sistem Anda.
