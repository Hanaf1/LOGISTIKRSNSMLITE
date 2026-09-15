# Panduan perubahan inventaris dan aset

## Ruang lingkup

Panduan ini berlaku untuk pengembangan proyek ini, khususnya modul
`plugins/logistik_non_medis`. Pembuatan panduan ini tidak berarti perubahan
kode aplikasi atau migrasi database sudah dilakukan.

## Istilah dan aturan bisnis

- Gunakan nama **Inventaris Non-Aset** untuk barang tahan lama yang tetap
  perlu dilacak tetapi tidak masuk pencatatan aset kapitalisasi. Istilah ini
  lebih spesifik daripada "barang operasional", yang dapat mencakup BHP juga.
- Barang inventaris dengan nilai perolehan per unit di bawah Rp1.000.000
  dicatat sebagai Inventaris Non-Aset.
- Barang inventaris di atas Rp1.000.000 masuk klasifikasi Aset sesuai
  kebijakan internal RS dan validitas data perolehannya.
- Perlakuan nilai tepat Rp1.000.000 belum ditetapkan oleh pengguna.
  Jangan mengasumsikan operator `>=` atau `>` sebagai aturan final;
  pastikan keputusan ini sebelum mengaktifkan klasifikasi otomatis untuk
  nilai tersebut. Pekerjaan lain yang tidak bergantung padanya dapat lanjut.
- Batas tersebut adalah kebijakan internal yang disampaikan pengguna,
  bukan pernyataan tentang ketentuan akuntansi yang berlaku universal.
- Harga rendah tidak otomatis menjadikan barang sebagai BHP. Kursi,
  tempat sampah, dan alat kerja yang dipakai berulang tetap dapat menjadi
  Inventaris Non-Aset. Tisu, sabun, dan kertas dikelola sebagai BHP.
- Dasar klasifikasi adalah nilai perolehan per unit, bukan total pembelian
  beberapa unit, nilai buku setelah penyusutan, atau harga referensi master.
- Harga kosong, nol yang belum tervalidasi, dan harga referensi impor yang
  belum dikonfirmasi tidak boleh otomatis dianggap sebagai Non-Aset.

## Rancangan data yang dituju

- Utamakan memakai tabel `rsns_custom_logistik_non_medis_aset` yang sudah
  ada sebagai register barang tahan lama, agar identitas barang, lokasi,
  kondisi, PIC, mutasi, sensus, dan riwayat tetap terhubung.
- Tambahkan kolom `klasifikasi_pencatatan` jika belum ada, dengan nilai:
  - `ASET`
  - `INVENTARIS_NON_ASET`
  - `BELUM_DITENTUKAN`
- Jangan membuat tabel inventaris non-aset duplikat hanya untuk memisahkan
  laporan atau tampilan. Periksa struktur aktual sebelum membuat migrasi.
- BHP tetap menggunakan alur persediaan, distribusi, dan pemakaian yang ada.
- Jangan menggunakan status kondisi atau status aktif sebagai pengganti
  klasifikasi pencatatan.

## Penyusutan dan laporan

- **Hanya barang dengan `klasifikasi_pencatatan = 'ASET'` yang boleh
  diproses dalam penyusutan.** Tetap terapkan syarat kelayakan penyusutan
  lain yang sudah berlaku; klasifikasi Aset bukan berarti semua aset
  otomatis wajib disusutkan.
- Inventaris Non-Aset, BHP, dan barang Belum Ditentukan tidak boleh ikut
  perhitungan, pembentukan transaksi, maupun penjumlahan penyusutan baru.
- Terapkan aturan di sisi server, termasuk proses batch dan pemanggilan
  langsung; filter tampilan saja tidak cukup.
- Laporan nilai aset kapitalisasi hanya menghitung klasifikasi Aset.
- Daftar Inventaris Unit tetap menampilkan barang tahan lama, termasuk
  Inventaris Non-Aset, dengan filter klasifikasi yang jelas.
- Inventaris Non-Aset tetap dapat dilacak lokasinya, dimutasi, disensus,
  diperiksa kondisinya, dan diproses penghapusannya sesuai alur yang berlaku.
- Jangan menghapus atau menghitung ulang riwayat penyusutan lama secara
  diam-diam saat klasifikasi berubah. Identifikasi dampaknya dan siapkan
  rekonsiliasi yang dapat ditinjau terlebih dahulu.

## Pelaksanaan perubahan

- Telusuri registrasi, impor, edit barang, dashboard, laporan, penyusutan,
  mutasi, dan sensus agar klasifikasi diterapkan konsisten.
- Pisahkan migrasi struktur dari klasifikasi ulang data lama. Jangan
  memberi semua data lama default Aset atau Non-Aset tanpa pemeriksaan.
- Pertahankan ID, kode inventaris/QR, relasi, dan riwayat barang yang ada.
- Sebelum migrasi data, siapkan cadangan, ringkasan jumlah dan nilai barang
  per klasifikasi, daftar data ambigu, serta langkah pemulihan.
- Jangan melakukan pembaruan massal hanya berdasarkan harga referensi.
- Jika nilai perolehan atau klasifikasi berubah, validasi dampak terhadap
  transaksi penyusutan yang telah ada dan catat alasan perubahan.

## Verifikasi implementasi

- Barang tahan lama Rp999.999 masuk Inventaris Non-Aset dan tidak disusutkan.
- Barang tahan lama Rp1.000.001 dengan data perolehan valid masuk Aset;
  penyusutan tetap mengikuti syarat kelayakan lainnya.
- Nilai tepat Rp1.000.000 mengikuti keputusan batas yang sudah dikonfirmasi.
- Pembelian 10 kursi seharga Rp350.000 per unit tidak menjadi Aset hanya
  karena totalnya Rp3.500.000.
- Aset yang nilai bukunya turun di bawah Rp1.000.000 tidak otomatis
  berubah menjadi Inventaris Non-Aset.
- Barang dengan harga belum valid tidak otomatis diklasifikasikan Non-Aset.
- BHP tetap masuk alur stok dan tidak ikut penyusutan.
- Inventaris Non-Aset tetap muncul pada inventaris unit dan dapat dimutasi
  serta disensus, tetapi tidak menambah total aset kapitalisasi.
- Permintaan langsung ke proses penyusutan tidak dapat memasukkan barang
  Non-Aset atau Belum Ditentukan.

## Pelaporan hasil kerja

Jelaskan perubahan yang benar-benar dilakukan, pemeriksaan yang dijalankan,
dan keputusan yang masih terbuka. Jangan menyatakan fitur selesai jika
baru menambahkan panduan ini.
