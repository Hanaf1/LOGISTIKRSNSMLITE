# LAPORAN KHUSUS KARYAWAN
## USULAN DIGITALISASI PROSES LOGISTIK & GUDANG (DETAIL SISTEM)
**RSU Nurusyifa**

**Disusun Oleh:** Admin Gudang

---

### 1. PENDAHULUAN
**1.1. Latar Belakang**
Seiring dengan perkembangan teknologi dan meningkatnya kebutuhan pelayanan kesehatan di RSU Nurusyifa, pengelolaan gudang secara manual dirasa kurang efektif. Sering terjadi keterlambatan dalam pemrosesan permintaan barang (rutin maupun non-rutin), ketidaksesuaian stok fisik dengan catatan, serta proses pengadaan yang memakan waktu lama. Oleh karena itu, diperlukan suatu sistem digitalisasi untuk mengotomatisasi dan memonitor seluruh proses logistik gudang.

**1.2. Tujuan**
* Mempercepat proses permintaan barang dari tiap unit/ruangan.
* Memastikan keakuratan data stok barang secara *real-time*.
* Mempermudah pelacakan (tracking) status permintaan dan pengadaan barang.
* Mengurangi penggunaan kertas (paperless) dan meminimalisir *human error*.

### 2. DETAIL FITUR SISTEM LOGISTIK & GUDANG
Sistem logistik yang baru akan dirancang agar mudah digunakan (user-friendly) dengan alur yang jelas. Berikut adalah penjabaran detail fitur pada sistem:

**2.1. Dashboard Utama (Gudang)**
Dashboard ini akan menjadi halaman pertama yang dilihat oleh Admin Gudang. Fitur utamanya meliputi:
* **Alert Stok Kritis (Defecta):** Sistem otomatis memberi peringatan jika stok barang mencapai batas minimum.
* **Ringkasan Permintaan:** Menampilkan jumlah permintaan dari unit yang menunggu diproses (Pending, On-Progress, Selesai).
* **Grafik Pemakaian:** Memvisualisasikan barang mana saja yang pergerakannya paling cepat (Fast Moving).

![Mockup Desain Dashboard Logistik Gudang](C:/Users/ASUS/.gemini/antigravity-ide/brain/4e43103c-513c-43c9-83bf-d5fea4699698/dashboard_logistik_1785577433907.png)

**2.2. Modul Permintaan Barang (Unit ke Gudang)**
Setiap unit ruangan akan diberikan akses untuk melakukan permintaan (baik rutin maupun non-rutin) secara digital. Fitur utamanya meliputi:
* **E-Katalog Barang:** Unit dapat mencari barang yang dibutuhkan lengkap dengan informasi stok yang masih tersedia di gudang utama.
* **Form Input Keranjang (Cart):** Memudahkan unit memilih banyak barang sekaligus seperti berbelanja online.
* **Keterangan Urgensi (Insidental):** Field khusus untuk mencantumkan alasan jika ada permintaan mendesak / non-rutin.

![Mockup Form Permintaan Barang dari Unit](C:/Users/ASUS/.gemini/antigravity-ide/brain/4e43103c-513c-43c9-83bf-d5fea4699698/form_permintaan_1785577447077.png)

**2.3. Modul Approval dan Pengadaan (Purchase Order)**
Sistem ini memastikan tidak ada barang keluar atau dipesan ke supplier tanpa otorisasi yang sah. Fitur utamanya meliputi:
* **Approval Berjenjang:** Pihak berwenang (Manajer/Direktur) cukup klik "Setuju" atau "Tolak" dari perangkat mereka.
* **Auto-Generate PR/PO:** Jika stok habis dan pengajuan disetujui, sistem akan otomatis mencetak Surat Pemesanan (Purchase Order) yang bisa langsung dikirim via sistem atau email ke supplier.

![Mockup Halaman Approval dan PO](C:/Users/ASUS/.gemini/antigravity-ide/brain/4e43103c-513c-43c9-83bf-d5fea4699698/pengadaan_approval_1785577462216.png)

### 3. ALUR OPERASIONAL HARIAN (SOP BARU)
Berikut adalah alur operasional singkat jika sistem di atas diterapkan:
1. **Permintaan:** Ruangan membuka sistem -> Cari barang di katalog -> Input jumlah -> Submit.
2. **Validasi & Distribusi:** Gudang mengecek dashboard -> Menyetujui permintaan (jika stok ada) -> Menyiapkan fisik barang -> Ruangan mengambil/menerima -> Klik "Barang Diterima" di sistem -> Stok terpotong.
3. **Pengadaan (Jika Stok Habis):** Gudang membuat PR -> Manajemen Approve -> Sistem membuat PO -> Barang datang -> Gudang input Penerimaan Barang -> Stok bertambah.

### 4. KESIMPULAN
Dengan pendetailan fitur ini (Dashboard, E-Katalog Permintaan, dan Approval Pengadaan Otomatis), diharapkan digitalisasi logistik RSU Nurusyifa dapat berjalan secara komprehensif, transparan, dan mempercepat layanan.
