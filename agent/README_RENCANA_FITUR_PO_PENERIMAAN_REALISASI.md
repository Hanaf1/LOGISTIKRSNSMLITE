# Rencana Fitur Digitalisasi Realisasi Belanja Logistik

## 1. Ringkasan Proyek

Fitur ini digunakan untuk mendigitalkan proses realisasi belanja logistik yang sebelumnya dicatat melalui file Excel bulanan.

Sistem harus menghubungkan proses berikut:

```text
Permintaan Unit
    ↓
Persetujuan
    ↓
Purchase Order (PO)
    ↓
Penerimaan Barang
    ↓
Stok Bertambah
    ↓
Realisasi Belanja Bulanan Terbentuk Otomatis
```

Tujuan utama fitur ini adalah agar petugas tidak perlu memasukkan ulang data realisasi belanja setelah barang diterima dari supplier.

Data realisasi harus diambil otomatis dari:

- Purchase Order
- Detail Purchase Order
- Penerimaan Barang
- Detail Penerimaan Barang
- Master Supplier
- Master Barang
- Master Kategori
- Master Satuan

---

## 2. Prinsip Utama Sistem

### 2.1 PO bukan realisasi

Purchase Order hanya dianggap sebagai komitmen pembelian.

Contoh:

```text
PO dibuat       : 30 September 2026
Barang diterima : 2 Oktober 2026
```

Maka pencatatannya:

```text
Komitmen anggaran : September 2026
Realisasi belanja : Oktober 2026
```

Realisasi harus mengikuti tanggal penerimaan barang, bukan tanggal PO.

### 2.2 Realisasi hanya berasal dari penerimaan yang diposting

Penerimaan dengan status `draft` belum boleh:

- Menambah stok
- Menambah realisasi
- Mengubah status PO
- Mengubah status permintaan

Hanya penerimaan dengan status `diposting` yang dihitung sebagai realisasi resmi.

### 2.3 Tidak membuat tabel baru setiap bulan

Semua transaksi disimpan dalam satu tabel.

Bulan dan tahun realisasi ditentukan dari:

```text
tanggal_penerimaan
```

Laporan bulanan ditampilkan menggunakan filter bulan dan tahun.

---

## 3. Ruang Lingkup Fitur

Fitur yang harus dibuat:

1. Master Supplier
2. Master Barang
3. Master Kategori Barang
4. Master Satuan
5. Purchase Order
6. Detail Purchase Order
7. Penerimaan Barang dari PO
8. Detail Penerimaan Barang
9. Posting Penerimaan
10. Pembaruan Stok Otomatis
11. Pembaruan Status PO
12. Pembaruan Status Permintaan
13. Realisasi Belanja Bulanan Otomatis
14. Rekap Realisasi per Kategori
15. Rekap Anggaran vs Realisasi
16. Export Excel
17. Riwayat dan audit transaksi

---

## 4. Struktur Menu

```text
Pengadaan
├── Permintaan Unit
├── Daftar Kebutuhan Pembelian
├── Purchase Order
├── Penerimaan Barang
└── Realisasi Belanja

Realisasi Belanja
├── Detail Transaksi
├── Rekap Bulanan
├── Rekap per Kategori
├── Anggaran vs Realisasi
└── Export Excel
```

---

## 5. Alur Bisnis Utama

### 5.1 Pembuatan PO

Petugas membuat PO berdasarkan permintaan unit yang telah disetujui.

Data header PO:

```text
Nomor PO
Tanggal PO
Supplier
Estimasi Tanggal Datang
Metode Pembayaran
Catatan
Status
```

Data detail PO:

```text
Barang
Satuan
Qty Pesan
Harga Satuan
Subtotal
```

Rumus:

```text
Subtotal = Qty Pesan × Harga Satuan
Total PO = Jumlah seluruh subtotal detail
```

Status PO:

```text
draft
dikirim
diproses_supplier
diterima_sebagian
diterima_lengkap
selesai
dibatalkan
```

### 5.2 Penerimaan dari PO

Petugas membuka menu:

```text
Penerimaan Barang
→ Tambah Penerimaan
→ Pilih Nomor PO
```

Setelah PO dipilih, sistem otomatis mengambil:

- Supplier
- Nomor PO
- Daftar barang
- Satuan
- Qty pesan
- Harga satuan
- Qty yang sudah diterima
- Sisa qty yang belum diterima

Petugas hanya mengisi:

```text
Tanggal Penerimaan
Nomor Surat Jalan
Nomor Faktur
Qty Diterima Sekarang
Qty Rusak
Qty Ditolak
Harga Realisasi jika berbeda
Alasan Selisih Harga jika ada
Catatan
Lampiran
```

### 5.3 Penerimaan sebagian

Sistem harus mendukung penerimaan sebagian.

Contoh:

```text
Qty PO             : 40 rim
Diterima September : 30 rim
Sisa                : 10 rim
```

Realisasi September:

```text
30 × harga realisasi
```

Jika sisa 10 rim diterima pada Oktober, maka nilai tersebut masuk ke realisasi Oktober.

### 5.4 Posting penerimaan

Saat tombol `Posting` ditekan, sistem harus menjalankan satu transaksi database.

Proses posting:

1. Validasi status masih draft.
2. Validasi qty diterima tidak melebihi sisa PO.
3. Validasi semua detail penerimaan.
4. Simpan detail final.
5. Tambahkan stok.
6. Buat mutasi stok masuk.
7. Hitung realisasi.
8. Perbarui qty diterima pada PO.
9. Perbarui status PO.
10. Perbarui pemenuhan permintaan unit.
11. Ubah status penerimaan menjadi `diposting`.
12. Simpan audit log.

Jika salah satu proses gagal, seluruh transaksi harus dibatalkan menggunakan database transaction rollback.

---

## 6. Aturan Realisasi Belanja

### 6.1 Sumber data realisasi

Laporan realisasi membaca langsung dari penerimaan barang yang sudah diposting.

Syarat:

```text
penerimaan_barang.status = 'diposting'
```

Nilai realisasi per detail:

```text
subtotal_realisasi = qty_diterima_baik × harga_realisasi
```

Dengan:

```text
qty_diterima_baik = qty_diterima - qty_rusak - qty_ditolak
```

### 6.2 Harga realisasi

Default harga realisasi diambil dari harga satuan PO.

```text
harga_realisasi = harga_po
```

Jika harga faktur berbeda dari PO:

```text
harga_po
harga_realisasi
selisih_harga
alasan_selisih
```

Rumus:

```text
selisih_harga = harga_realisasi - harga_po
```

Perubahan harga harus:

- Memerlukan alasan
- Dicatat dalam audit log
- Hanya dapat dilakukan oleh role berwenang

### 6.3 Diskon, pajak, dan ongkir

Biaya tambahan disimpan pada header penerimaan atau invoice:

```text
subtotal_barang
diskon
pajak
ongkir
biaya_lain
total_realisasi
```

Rumus:

```text
total_realisasi =
subtotal_barang
- diskon
+ pajak
+ ongkir
+ biaya_lain
```

Untuk tahap awal, distribusi ongkir ke kategori tidak wajib. Ongkir dapat ditampilkan sebagai biaya tambahan transaksi.

---

## 7. Struktur Database

> Sesuaikan nama tabel dengan struktur aplikasi yang sudah ada.

### 7.1 Tabel `purchase_orders`

```sql
CREATE TABLE purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_po VARCHAR(50) NOT NULL UNIQUE,
    tanggal_po DATE NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    estimasi_tanggal_datang DATE NULL,
    metode_pembayaran VARCHAR(50) NULL,
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    diskon DECIMAL(18,2) NOT NULL DEFAULT 0,
    pajak DECIMAL(18,2) NOT NULL DEFAULT 0,
    ongkir DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_po DECIMAL(18,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    catatan TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_po_supplier (supplier_id),
    INDEX idx_po_tanggal (tanggal_po),
    INDEX idx_po_status (status)
);
```

### 7.2 Tabel `purchase_order_details`

```sql
CREATE TABLE purchase_order_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    barang_id BIGINT UNSIGNED NOT NULL,
    satuan_id BIGINT UNSIGNED NOT NULL,
    qty_pesan DECIMAL(18,2) NOT NULL DEFAULT 0,
    qty_diterima DECIMAL(18,2) NOT NULL DEFAULT 0,
    harga_satuan DECIMAL(18,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    catatan TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_pod_po (purchase_order_id),
    INDEX idx_pod_barang (barang_id)
);
```

### 7.3 Tabel `penerimaan_barang`

```sql
CREATE TABLE penerimaan_barang (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_penerimaan VARCHAR(50) NOT NULL UNIQUE,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    tanggal_penerimaan DATE NOT NULL,
    nomor_surat_jalan VARCHAR(100) NULL,
    nomor_faktur VARCHAR(100) NULL,
    subtotal_barang DECIMAL(18,2) NOT NULL DEFAULT 0,
    diskon DECIMAL(18,2) NOT NULL DEFAULT 0,
    pajak DECIMAL(18,2) NOT NULL DEFAULT 0,
    ongkir DECIMAL(18,2) NOT NULL DEFAULT 0,
    biaya_lain DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_realisasi DECIMAL(18,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    catatan TEXT NULL,
    lampiran VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    posted_by BIGINT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_pb_po (purchase_order_id),
    INDEX idx_pb_tanggal (tanggal_penerimaan),
    INDEX idx_pb_status (status)
);
```

### 7.4 Tabel `penerimaan_barang_detail`

```sql
CREATE TABLE penerimaan_barang_detail (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    penerimaan_id BIGINT UNSIGNED NOT NULL,
    purchase_order_detail_id BIGINT UNSIGNED NOT NULL,
    qty_diterima DECIMAL(18,2) NOT NULL DEFAULT 0,
    qty_rusak DECIMAL(18,2) NOT NULL DEFAULT 0,
    qty_ditolak DECIMAL(18,2) NOT NULL DEFAULT 0,
    qty_diterima_baik DECIMAL(18,2) NOT NULL DEFAULT 0,
    harga_po DECIMAL(18,2) NOT NULL DEFAULT 0,
    harga_realisasi DECIMAL(18,2) NOT NULL DEFAULT 0,
    selisih_harga DECIMAL(18,2) NOT NULL DEFAULT 0,
    alasan_selisih TEXT NULL,
    subtotal_realisasi DECIMAL(18,2) NOT NULL DEFAULT 0,
    catatan TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_pbd_penerimaan (penerimaan_id),
    INDEX idx_pbd_po_detail (purchase_order_detail_id)
);
```

### 7.5 Tabel `mutasi_stok`

```sql
CREATE TABLE mutasi_stok (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    barang_id BIGINT UNSIGNED NOT NULL,
    jenis_mutasi VARCHAR(30) NOT NULL,
    qty_masuk DECIMAL(18,2) NOT NULL DEFAULT 0,
    qty_keluar DECIMAL(18,2) NOT NULL DEFAULT 0,
    saldo_sebelum DECIMAL(18,2) NOT NULL DEFAULT 0,
    saldo_sesudah DECIMAL(18,2) NOT NULL DEFAULT 0,
    referensi_tipe VARCHAR(50) NOT NULL,
    referensi_id BIGINT UNSIGNED NOT NULL,
    keterangan TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_mutasi_barang (barang_id),
    INDEX idx_mutasi_tanggal (tanggal),
    INDEX idx_mutasi_referensi (referensi_tipe, referensi_id)
);
```

### 7.6 Tabel `anggaran_bulanan`

```sql
CREATE TABLE anggaran_bulanan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun SMALLINT UNSIGNED NOT NULL,
    bulan TINYINT UNSIGNED NOT NULL,
    kategori_id BIGINT UNSIGNED NOT NULL,
    jumlah_anggaran DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY unique_anggaran_bulan (
        tahun,
        bulan,
        kategori_id
    )
);
```

### 7.7 Tabel penghubung PO dengan permintaan

```sql
CREATE TABLE po_detail_sumber_permintaan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_detail_id BIGINT UNSIGNED NOT NULL,
    permintaan_detail_id BIGINT UNSIGNED NOT NULL,
    qty_dialokasikan DECIMAL(18,2) NOT NULL DEFAULT 0,
    qty_terpenuhi DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_pdsp_po_detail (purchase_order_detail_id),
    INDEX idx_pdsp_permintaan_detail (permintaan_detail_id)
);
```

---

## 8. Relasi Data

```text
supplier
└── purchase_orders
    └── purchase_order_details
        ├── barang
        ├── satuan
        ├── po_detail_sumber_permintaan
        └── penerimaan_barang_detail
            └── penerimaan_barang
                └── mutasi_stok
```

Hubungan utama:

```text
purchase_orders.id
    → purchase_order_details.purchase_order_id

purchase_orders.id
    → penerimaan_barang.purchase_order_id

purchase_order_details.id
    → penerimaan_barang_detail.purchase_order_detail_id

penerimaan_barang.id
    → penerimaan_barang_detail.penerimaan_id
```

---

## 9. Validasi Wajib

### 9.1 Validasi penerimaan

- PO harus ditemukan.
- PO tidak boleh berstatus `dibatalkan`.
- Penerimaan hanya dapat dibuat dari PO yang belum diterima lengkap.
- Qty diterima harus lebih besar dari nol.
- Qty diterima tidak boleh melebihi sisa PO.
- Qty rusak dan ditolak tidak boleh negatif.
- Qty rusak + qty ditolak tidak boleh melebihi qty diterima.
- Harga realisasi harus lebih besar atau sama dengan nol.
- Jika harga realisasi berbeda dari harga PO, alasan wajib diisi.
- Penerimaan yang sudah diposting tidak dapat diedit langsung.
- Penerimaan yang sudah diposting tidak dapat diposting ulang.

### 9.2 Perhitungan sisa PO

```text
sudah_diterima =
SUM(qty_diterima_baik dari penerimaan berstatus diposting)

sisa_po =
qty_pesan - sudah_diterima
```

### 9.3 Keamanan harga

Harga PO dan harga dasar tidak boleh dipercaya dari nilai input browser.

Backend harus mengambil ulang data berikut dari database:

```text
harga_po
qty_pesan
qty_sudah_diterima
barang_id
satuan_id
```

---

## 10. Status Otomatis PO

Setelah penerimaan diposting:

```text
Jika total diterima = 0
→ status PO tetap dikirim/diproses_supplier

Jika total diterima > 0 dan masih ada sisa
→ diterima_sebagian

Jika seluruh qty sudah diterima
→ diterima_lengkap
```

Status `selesai` dapat digunakan setelah:

- Seluruh barang diterima
- Dokumen faktur lengkap
- Tidak ada proses lanjutan
- PO ditutup oleh petugas berwenang

---

## 11. Pembaruan Permintaan Unit

Jika PO berasal dari permintaan unit, sistem harus memperbarui qty terpenuhi.

Contoh:

```text
Permintaan AROFAH : 20 roll
Diterima pertama  : 15 roll
Diterima kedua    : 5 roll
```

Status permintaan:

```text
0 diterima
→ menunggu_pengadaan

sebagian diterima
→ dipenuhi_sebagian

seluruhnya diterima
→ siap_distribusi
```

Permintaan baru dianggap `selesai` setelah barang diserahkan kepada unit, bukan hanya setelah diterima dari supplier.

---

## 12. Query Dasar

### 12.1 Mengambil detail PO untuk form penerimaan

```sql
SELECT
    pod.id AS po_detail_id,
    pod.barang_id,
    b.nama_barang,
    pod.satuan_id,
    s.nama_satuan,
    pod.qty_pesan,
    pod.harga_satuan AS harga_po,
    COALESCE(SUM(
        CASE
            WHEN pb.status = 'diposting'
            THEN pbd.qty_diterima_baik
            ELSE 0
        END
    ), 0) AS sudah_diterima,
    pod.qty_pesan - COALESCE(SUM(
        CASE
            WHEN pb.status = 'diposting'
            THEN pbd.qty_diterima_baik
            ELSE 0
        END
    ), 0) AS sisa_qty
FROM purchase_order_details pod
JOIN barang b
    ON b.id = pod.barang_id
JOIN satuan s
    ON s.id = pod.satuan_id
LEFT JOIN penerimaan_barang_detail pbd
    ON pbd.purchase_order_detail_id = pod.id
LEFT JOIN penerimaan_barang pb
    ON pb.id = pbd.penerimaan_id
WHERE pod.purchase_order_id = ?
GROUP BY
    pod.id,
    pod.barang_id,
    b.nama_barang,
    pod.satuan_id,
    s.nama_satuan,
    pod.qty_pesan,
    pod.harga_satuan
ORDER BY b.nama_barang;
```

### 12.2 Detail realisasi bulanan

```sql
SELECT
    pb.tanggal_penerimaan,
    pb.nomor_penerimaan,
    pb.nomor_faktur,
    po.nomor_po,
    supplier.nama_supplier,
    kategori.nama_kategori,
    barang.nama_barang,
    satuan.nama_satuan,
    pbd.qty_diterima_baik AS jumlah,
    pbd.harga_realisasi AS harga_satuan,
    pbd.subtotal_realisasi AS jumlah_harga
FROM penerimaan_barang pb
JOIN purchase_orders po
    ON po.id = pb.purchase_order_id
JOIN supplier
    ON supplier.id = po.supplier_id
JOIN penerimaan_barang_detail pbd
    ON pbd.penerimaan_id = pb.id
JOIN purchase_order_details pod
    ON pod.id = pbd.purchase_order_detail_id
JOIN barang
    ON barang.id = pod.barang_id
JOIN satuan
    ON satuan.id = pod.satuan_id
JOIN kategori
    ON kategori.id = barang.kategori_id
WHERE pb.status = 'diposting'
  AND YEAR(pb.tanggal_penerimaan) = ?
  AND MONTH(pb.tanggal_penerimaan) = ?
ORDER BY
    pb.tanggal_penerimaan,
    po.nomor_po,
    barang.nama_barang;
```

### 12.3 Rekap realisasi per kategori

```sql
SELECT
    kategori.id AS kategori_id,
    kategori.nama_kategori,
    SUM(pbd.subtotal_realisasi) AS total_realisasi
FROM penerimaan_barang pb
JOIN penerimaan_barang_detail pbd
    ON pbd.penerimaan_id = pb.id
JOIN purchase_order_details pod
    ON pod.id = pbd.purchase_order_detail_id
JOIN barang
    ON barang.id = pod.barang_id
JOIN kategori
    ON kategori.id = barang.kategori_id
WHERE pb.status = 'diposting'
  AND YEAR(pb.tanggal_penerimaan) = ?
  AND MONTH(pb.tanggal_penerimaan) = ?
GROUP BY
    kategori.id,
    kategori.nama_kategori
ORDER BY kategori.nama_kategori;
```

### 12.4 Anggaran vs realisasi

```sql
SELECT
    ab.kategori_id,
    k.nama_kategori,
    ab.jumlah_anggaran,
    COALESCE(r.total_realisasi, 0) AS total_realisasi,
    ab.jumlah_anggaran - COALESCE(r.total_realisasi, 0) AS sisa_anggaran,
    CASE
        WHEN ab.jumlah_anggaran > 0
        THEN (
            COALESCE(r.total_realisasi, 0)
            / ab.jumlah_anggaran
        ) * 100
        ELSE 0
    END AS persentase_penggunaan
FROM anggaran_bulanan ab
JOIN kategori k
    ON k.id = ab.kategori_id
LEFT JOIN (
    SELECT
        barang.kategori_id,
        SUM(pbd.subtotal_realisasi) AS total_realisasi
    FROM penerimaan_barang pb
    JOIN penerimaan_barang_detail pbd
        ON pbd.penerimaan_id = pb.id
    JOIN purchase_order_details pod
        ON pod.id = pbd.purchase_order_detail_id
    JOIN barang
        ON barang.id = pod.barang_id
    WHERE pb.status = 'diposting'
      AND YEAR(pb.tanggal_penerimaan) = ?
      AND MONTH(pb.tanggal_penerimaan) = ?
    GROUP BY barang.kategori_id
) r
    ON r.kategori_id = ab.kategori_id
WHERE ab.tahun = ?
  AND ab.bulan = ?
ORDER BY k.nama_kategori;
```

---

## 13. Desain Halaman

### 13.1 Daftar Purchase Order

Filter:

```text
Nomor PO
Supplier
Tanggal Awal
Tanggal Akhir
Status
```

Kolom:

```text
No
Nomor PO
Tanggal
Supplier
Jumlah Item
Total PO
Status
Aksi
```

Aksi:

```text
Lihat
Edit
Kirim
Buat Penerimaan
Batalkan
Cetak PO
```

### 13.2 Form Penerimaan Barang

Header:

```text
Nomor Penerimaan
Nomor PO
Supplier
Tanggal Penerimaan
Nomor Surat Jalan
Nomor Faktur
Catatan
Lampiran
```

Detail:

| Barang | Qty PO | Sudah Diterima | Sisa | Diterima Sekarang | Rusak | Ditolak | Harga PO | Harga Realisasi | Subtotal |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|

Tombol:

```text
Simpan Draft
Posting Penerimaan
Batal
```

### 13.3 Rekap Bulanan

Filter:

```text
Bulan
Tahun
Kategori
Supplier
```

Ringkasan:

```text
Total Anggaran
Total Realisasi
Sisa Anggaran
Persentase Penggunaan
Jumlah PO
Jumlah Penerimaan
```

Tabel detail:

| Tanggal | PO | Supplier | Kategori | Barang | Satuan | Qty | Harga | Total |
|---|---|---|---|---|---|---:|---:|---:|

### 13.4 Rekap per kategori

| Kategori | Anggaran | Realisasi | Sisa | Penggunaan |
|---|---:|---:|---:|---:|

Format nominal menggunakan format Indonesia:

```text
Rp17.149.000
Rp8.111.400
Rp9.037.600
```

---

## 14. Hak Akses

### Staf Logistik

- Melihat PO
- Membuat penerimaan draft
- Mengedit draft
- Melihat rekap

### Kepala Logistik atau Approver

- Posting penerimaan
- Menyetujui perubahan harga
- Membatalkan transaksi sesuai aturan
- Menutup PO

### Admin

- Mengelola master
- Melihat seluruh transaksi
- Melakukan koreksi melalui transaksi reversal
- Melihat audit log

### Auditor atau Manajemen

- Hanya melihat laporan
- Export Excel
- Melihat histori perubahan

---

## 15. Audit Log

Setiap aksi penting harus dicatat:

```text
Membuat PO
Mengubah PO
Mengirim PO
Membuat penerimaan
Mengubah penerimaan
Posting penerimaan
Perubahan harga
Membatalkan penerimaan
Menutup PO
```

Data audit:

```text
user_id
aksi
nama_tabel
record_id
data_sebelum
data_sesudah
ip_address
user_agent
created_at
```

---

## 16. Pembatalan dan Koreksi

Penerimaan yang sudah diposting tidak boleh dihapus langsung.

Gunakan mekanisme:

```text
Reversal / Pembatalan Posting
```

Saat reversal:

1. Buat mutasi stok keluar sebagai pembalik.
2. Kurangi qty diterima pada PO.
3. Kurangi realisasi.
4. Perbarui status PO.
5. Perbarui status permintaan.
6. Simpan alasan pembatalan.
7. Simpan audit log.

Status penerimaan dapat menjadi:

```text
draft
diposting
dibatalkan
```

---

## 17. Export Excel

Export harus mengikuti format laporan realisasi bulanan.

Kolom minimal:

```text
No
Tanggal
Nomor PO
Nomor Penerimaan
Nama Supplier
Kategori
Nama Barang
Satuan
Jumlah
Harga Satuan
Jumlah Harga
```

Tambahkan ringkasan:

```text
Kategori
Anggaran
Realisasi
Sisa
Persentase Penggunaan
```

Nama file:

```text
Realisasi_Belanja_Logistik_September_2026.xlsx
```

---

## 18. Format Nomor Dokumen

Contoh nomor PO:

```text
PO/LOG/2026/09/0001
```

Contoh nomor penerimaan:

```text
PB/LOG/2026/09/0001
```

Nomor dibuat otomatis berdasarkan tahun dan bulan transaksi.

Nomor harus unik dan dibuat dengan mekanisme yang aman terhadap transaksi bersamaan.

---

## 19. Struktur Kode yang Disarankan

Untuk PHP Native:

```text
app/
├── Controllers/
│   ├── PurchaseOrderController.php
│   ├── PenerimaanBarangController.php
│   └── RealisasiBelanjaController.php
├── Services/
│   ├── PurchaseOrderService.php
│   ├── PenerimaanBarangService.php
│   ├── PostingPenerimaanService.php
│   ├── StockService.php
│   └── RealisasiBelanjaService.php
├── Repositories/
│   ├── PurchaseOrderRepository.php
│   ├── PenerimaanBarangRepository.php
│   └── RealisasiBelanjaRepository.php
├── Validators/
│   ├── PurchaseOrderValidator.php
│   └── PenerimaanBarangValidator.php
├── Helpers/
│   ├── CurrencyHelper.php
│   ├── NumberGenerator.php
│   └── AuditLogger.php
└── Views/
    ├── purchase_orders/
    ├── penerimaan_barang/
    └── realisasi_belanja/
```

Controller tidak boleh berisi seluruh logika bisnis.

Logika posting harus berada dalam service khusus.

---

## 20. Pseudocode Posting Penerimaan

```php
function postPenerimaan(int $penerimaanId, int $userId): void
{
    beginTransaction();

    try {
        $penerimaan = lockPenerimaanForUpdate($penerimaanId);

        if (!$penerimaan) {
            throw new Exception('Penerimaan tidak ditemukan.');
        }

        if ($penerimaan['status'] !== 'draft') {
            throw new Exception('Penerimaan sudah diproses.');
        }

        $details = getPenerimaanDetails($penerimaanId);

        if (empty($details)) {
            throw new Exception('Detail penerimaan masih kosong.');
        }

        foreach ($details as $detail) {
            $poDetail = lockPoDetailForUpdate(
                $detail['purchase_order_detail_id']
            );

            $sisaQty = calculateRemainingQty($poDetail['id']);

            if ($detail['qty_diterima_baik'] > $sisaQty) {
                throw new Exception(
                    'Qty diterima melebihi sisa PO.'
                );
            }

            increaseStock(
                $poDetail['barang_id'],
                $detail['qty_diterima_baik']
            );

            createStockMutation([
                'jenis_mutasi' => 'penerimaan_po',
                'barang_id' => $poDetail['barang_id'],
                'qty_masuk' => $detail['qty_diterima_baik'],
                'referensi_tipe' => 'penerimaan_barang',
                'referensi_id' => $penerimaanId,
            ]);

            updatePoReceivedQty(
                $poDetail['id'],
                $detail['qty_diterima_baik']
            );

            updateRequestFulfillment(
                $poDetail['id'],
                $detail['qty_diterima_baik']
            );
        }

        recalculatePenerimaanTotal($penerimaanId);
        updatePoStatus($penerimaan['purchase_order_id']);

        markPenerimaanAsPosted(
            $penerimaanId,
            $userId,
            date('Y-m-d H:i:s')
        );

        writeAuditLog(
            $userId,
            'posting_penerimaan',
            'penerimaan_barang',
            $penerimaanId
        );

        commit();
    } catch (Throwable $e) {
        rollBack();
        throw $e;
    }
}
```

---

## 21. Tahapan Implementasi

### Tahap 1 — Database dan master

- Buat migrasi tabel
- Sesuaikan foreign key
- Buat master supplier
- Pastikan barang memiliki kategori dan satuan
- Tambahkan indeks database

### Tahap 2 — Purchase Order

- Daftar PO
- Form tambah PO
- Form detail barang
- Perhitungan subtotal
- Status PO
- Cetak PO

### Tahap 3 — Penerimaan

- Pilih PO
- Ambil detail otomatis
- Hitung sisa qty
- Simpan draft
- Validasi penerimaan sebagian
- Posting penerimaan

### Tahap 4 — Stok dan permintaan

- Mutasi stok masuk
- Update stok barang
- Update qty diterima PO
- Update alokasi permintaan
- Update status permintaan

### Tahap 5 — Laporan

- Detail realisasi bulanan
- Rekap kategori
- Anggaran vs realisasi
- Filter bulan dan tahun
- Export Excel

### Tahap 6 — Audit dan keamanan

- Role dan permission
- Audit log
- Reversal penerimaan
- Proteksi double posting
- Transaction dan row locking

---

## 22. Acceptance Criteria

Fitur dianggap selesai jika:

- [ ] PO dapat dibuat dengan banyak barang.
- [ ] Detail PO menyimpan qty dan harga.
- [ ] Penerimaan dapat dibuat dengan memilih PO.
- [ ] Data supplier, barang, satuan, dan harga tampil otomatis.
- [ ] Sistem menampilkan qty pesan, sudah diterima, dan sisa.
- [ ] Penerimaan sebagian dapat dilakukan.
- [ ] Qty penerimaan tidak dapat melebihi sisa PO.
- [ ] Draft penerimaan belum mengubah stok.
- [ ] Posting penerimaan menambah stok.
- [ ] Posting penerimaan membuat mutasi stok.
- [ ] Posting penerimaan memperbarui qty diterima PO.
- [ ] Status PO berubah otomatis.
- [ ] Realisasi menggunakan tanggal penerimaan.
- [ ] Realisasi hanya menghitung penerimaan diposting.
- [ ] Harga realisasi otomatis mengikuti PO.
- [ ] Perubahan harga memerlukan alasan.
- [ ] Rekap bulanan tampil berdasarkan bulan dan tahun.
- [ ] Rekap kategori dapat dihitung otomatis.
- [ ] Anggaran vs realisasi dapat ditampilkan.
- [ ] Nominal tampil dalam format rupiah Indonesia.
- [ ] Export Excel dapat dibuat.
- [ ] Double posting dicegah.
- [ ] Audit log tersimpan.
- [ ] Penerimaan diposting tidak dapat dihapus langsung.

---

## 23. Instruksi untuk AI Agent Kiro

Implementasikan fitur secara bertahap dan jangan langsung mengubah seluruh modul.

Urutan kerja:

1. Analisis struktur proyek yang sudah ada.
2. Identifikasi tabel barang, supplier, satuan, kategori, stok, permintaan, dan user.
3. Jangan membuat tabel duplikat jika tabel serupa sudah tersedia.
4. Buat migration atau SQL perubahan database.
5. Buat backup sebelum mengubah schema.
6. Gunakan PDO prepared statement atau mekanisme database yang aman.
7. Gunakan database transaction untuk posting.
8. Gunakan row locking ketika menghitung sisa penerimaan.
9. Pisahkan controller, service, repository, dan view.
10. Validasi seluruh nilai pada backend.
11. Jangan percaya harga dan subtotal dari browser.
12. Jangan memasukkan penerimaan draft ke laporan.
13. Jangan membuat tabel realisasi bulanan terpisah.
14. Buat laporan berdasarkan tanggal penerimaan.
15. Pastikan fitur lama tetap berjalan.
16. Buat perubahan dalam commit kecil dan terpisah.
17. Sertakan daftar file yang dibuat dan diubah.
18. Sertakan SQL yang dijalankan.
19. Sertakan cara pengujian manual.
20. Jangan menghapus data lama tanpa instruksi eksplisit.

Prompt kerja yang dapat digunakan:

```text
Baca README rencana fitur ini secara penuh.

Analisis struktur aplikasi PHP Native yang sudah ada, terutama:
- master barang
- master supplier
- kategori
- satuan
- stok
- permintaan unit
- approval
- purchase order jika sudah ada
- penerimaan jika sudah ada
- struktur routing dan template

Jangan langsung menulis kode sebelum memberikan:
1. temuan struktur proyek,
2. tabel yang dapat digunakan kembali,
3. tabel yang perlu ditambah,
4. daftar file yang akan dibuat atau diubah,
5. risiko integrasi,
6. rencana implementasi bertahap.

Setelah rencana disetujui, implementasikan modul Purchase Order dan
Penerimaan Barang terlebih dahulu.

Realisasi belanja bulanan harus terbentuk otomatis dari penerimaan PO
berstatus diposting berdasarkan tanggal penerimaan.

Jangan membuat input ulang realisasi untuk transaksi yang sudah berasal
dari PO.
```

---

## 24. Hasil Akhir yang Diharapkan

Sebelum digitalisasi:

```text
PO dibuat
→ barang datang
→ petugas mengetik ulang data ke Excel
→ rekap dihitung manual
```

Setelah digitalisasi:

```text
PO dibuat
→ barang diterima
→ penerimaan diposting
→ stok bertambah otomatis
→ status PO berubah otomatis
→ permintaan diperbarui otomatis
→ realisasi bulanan muncul otomatis
→ laporan dapat diekspor ke Excel
```

Fokus utama fitur ini adalah menghilangkan input data ganda dan menjadikan penerimaan PO sebagai sumber resmi realisasi belanja logistik.
