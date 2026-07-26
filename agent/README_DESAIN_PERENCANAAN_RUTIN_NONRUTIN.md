# Desain Sistem Perencanaan & Permintaan Logistik Non-Medis

Dokumen ini merangkum rancangan pemisahan alur kerja (workflow) untuk pengadaan logistik non-medis, khususnya membedakan antara kebutuhan rutin dan kebutuhan mendadak (insidentil).

## 1. Latar Belakang Masalah
Seringkali terjadi kebingungan saat unit mengajukan permintaan:
- Apakah permintaan ini harus masuk rencana tahunan (RKBU) dulu?
- Bagaimana jika AC rusak mendadak, apakah harus menunggu tahun depan?
- Bagaimana Logistik memilah permintaan berdasarkan Unit peminta vs Kategori Barang (ATK, Cetakan, dll)?

Oleh karena itu, sistem memisahkan **Perencanaan Kebutuhan Rutin** dan **Permintaan Non-Rutin**.

---

## 2. Perencanaan Kebutuhan Rutin (Barang Konsumsi/BHP)

Digunakan untuk barang yang pasti habis dipakai dan dibeli secara berkala (ATK, Alat Kebersihan, Cetakan, Kantong Plastik).

### Aturan Main:
1. **Wajib Direncanakan (Bulanan):** Unit wajib menginput rencana kebutuhan ini pada akhir bulan untuk kebutuhan bulan berikutnya ke dalam sistem (Menu: **Perencanaan**).
2. **Pengelompokan (Kategori vs Unit):**
   - Unit menginput berdasarkan kebutuhan unit mereka (Misal: Poli Gigi butuh Kertas HVS 5 rim).
   - Sistem **secara otomatis** membaca Master Barang dan melabeli HVS tersebut sebagai kategori "ATK".
   - Bagian Logistik bisa memfilter tampilan: *Ingin melihat total kebutuhan ATK se-Rumah Sakit?* atau *Ingin melihat seluruh rencana belanja Poli Gigi?*
3. **Persetujuan (Approval):** Daftar rencana ini akan disetujui oleh Direktur/Keuangan sebagai plafon (batas maksimal) anggaran belanja rutin bulanan.
4. **Referensi Pengeluaran:** Saat membuat perencanaan, sistem akan menampilkan data **Pengeluaran/Pemakaian Bulan Sebelumnya** sebagai acuan, agar permintaan bulan ini lebih akurat.
5. **Eksekusi PO:** Pembelian (PO) rutin didasarkan pada plafon Perencanaan bulanan ini.

---

## 3. Permintaan Non-Rutin / Insidentil (Aset & Mendadak)

Digunakan untuk barang yang tidak bisa diprediksi kapan rusaknya atau pengadaan barang baru (Aset, Lemari, Komputer rusak, Spanduk Event Dadakan).

### Aturan Main:
1. **Tanpa Rencana Bulanan:** Permintaan ini **TIDAK PERLU** masuk ke Perencanaan Bulanan (RKBU). 
2. **Jalur Cepat (Ad-hoc):** Unit langsung menginput permintaan di menu **Permintaan Non-Rutin**.
3. **Persetujuan Berjenjang:** Karena di luar rencana anggaran rutin, permintaan ini butuh persetujuan khusus (misal: Kepala Unit -> Logistik -> Keuangan -> Direktur) sebelum bisa diterbitkan PO.
4. **Sifat Belanja:** Biasanya pengadaan ini bersifat putus (sekali beli langsung selesai), bukan konsumsi rutin bulanan.

---

## 4. Struktur Menu di Sistem (mLite RSNS)

Untuk memudahkan user (tidak bingung), menu Logistik Non-Medis disusun menjadi:

### Pengadaan (Tahap Awal)
* **Perencanaan** -> Tempat Unit menginput Rencana Belanja Bulanan (Barang Rutin).
* **Permintaan Non-Rutin** -> Tempat Unit meminta barang insidentil/aset secara dadakan.

### Eksekusi & Realisasi (Tahap Lanjut)
* **Purchase Order (PO)** -> Pemesanan barang ke Supplier (baik dari data Rutin maupun Non-Rutin).
* **Penerimaan Barang** -> Mencatat barang yang tiba di Gudang.
* **Rencana & Realisasi Anggaran** -> Dasbor laporan untuk melihat apakah realisasi belanja bulan ini masih sesuai (atau over budget) dengan rencana anggaran awal.

---

## Kesimpulan Alur Kerja
- Jika **Poli Umum** kehabisan pulpen (ATK) -> Cek **Perencanaan Rutin**, lalu Logistik buatkan PO rutin bulanan.
- Jika **Poli Umum** komputernya tiba-tiba meledak/rusak -> Langsung buat **Permintaan Non-Rutin**, minta persetujuan Direktur, lalu Logistik buatkan PO.
