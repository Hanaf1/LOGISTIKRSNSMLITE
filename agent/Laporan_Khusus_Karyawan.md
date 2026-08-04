# LAPORAN KHUSUS KARYAWAN
## USULAN DIGITALISASI PROSES LOGISTIK & GUDANG
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

### 2. PANDUAN ALUR DIGITALISASI PROSES GUDANG
Berikut adalah panduan alur baru yang diusulkan dalam sistem digitalisasi logistik RSU Nurusyifa:

**2.1. Proses Permintaan Rutin (Unit/Ruangan ke Gudang)**
1. **Input Permintaan:** User/Kepala Ruangan melakukan input permintaan barang rutin melalui sistem (Aplikasi mLite/Sistem Informasi RS) dengan memilih item yang tersedia beserta jumlahnya.
2. **Validasi & Verifikasi:** Admin Gudang menerima notifikasi permintaan dan melakukan verifikasi ketersediaan stok di sistem.
3. **Approval:** Sistem akan meneruskan permintaan ke pihak berwenang (Manajer Logistik/Direktur) untuk persetujuan (approval) secara digital jika diperlukan.
4. **Distribusi:** Setelah disetujui, Admin Gudang menyiapkan barang dan mengubah status di sistem menjadi "Siap Diambil" atau "Sedang Didistribusikan".
5. **Serah Terima:** Pihak ruangan menerima barang dan melakukan konfirmasi penerimaan di sistem. Stok otomatis berkurang.

**2.2. Proses Permintaan Non-Rutin (Insidental)**
1. **Pengajuan:** Ruangan mengisi form permintaan non-rutin di sistem beserta alasan kebutuhan (urgensi).
2. **Review Admin Gudang:** Admin mengecek apakah barang bisa dipenuhi dari stok atau perlu pengadaan baru.
3. **Eskalasi Pengadaan:** Jika barang tidak tersedia, sistem otomatis membuat *draft* pengajuan pengadaan (Purchase Request).

**2.3. Proses Pengadaan Barang (Procurement)**
1. **Purchase Request (PR):** Berdasarkan rekap permintaan atau peringatan stok minimum (*reorder point*), Admin Gudang men-*generate* PR di sistem.
2. **Approval PR & PO:** PR disetujui oleh Manajemen, dilanjutkan dengan pembuatan *Purchase Order* (PO) digital yang dikirim ke Supplier via email/sistem.
3. **Penerimaan Barang:** Saat barang datang, Admin Gudang melakukan pengecekan fisik vs PO. Jika sesuai, dilakukan "Penerimaan Barang" di sistem (stok otomatis bertambah).

### 3. IMPLEMENTASI DAN EVALUASI
**3.1. Kebutuhan Sistem**
* Modul Logistik Non-Medis pada sistem mLite RSNS yang terintegrasi.
* Perangkat keras (Komputer/Tablet di tiap ruangan untuk input permintaan).
* Jaringan intranet/internet yang stabil.

**3.2. Tahapan Evaluasi**
* **Bulan 1:** Sosialisasi SOP baru dan uji coba (pilot project) di beberapa ruangan.
* **Bulan 2:** Evaluasi kendala sistem dan *user error*, serta perbaikan *bug*.
* **Bulan 3:** Implementasi penuh di seluruh lingkungan RSU Nurusyifa.

### 4. PENUTUP
Dengan adanya digitalisasi proses permintaan dan pengadaan di gudang RSU Nurusyifa, diharapkan tercipta efisiensi waktu, transparansi alur barang, dan akurasi data inventaris yang lebih baik. Panduan ini dapat menjadi acuan awal dalam pengembangan sistem logistik ke depannya.
