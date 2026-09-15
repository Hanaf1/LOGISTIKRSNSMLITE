# Inventaris Non-Aset — branch experiment

## Perilaku

- Harga perolehan per unit valid > 0 dan < Rp1.000.000: Inventaris Non-Aset.
- Harga perolehan per unit > Rp1.000.000: Aset.
- Harga belum valid/nol atau tepat Rp1.000.000: Belum Ditentukan.
  Batas tepat Rp1.000.000 menunggu keputusan pengguna.
- Harga referensi dan nilai buku tidak menjadi dasar klasifikasi.
- Registrasi beberapa unit memakai harga per unit, bukan total kelompok.
- BHP tetap menggunakan persediaan/distribusi. Tidak ada pemindahan data
  inventaris ke BHP atau perubahan ID, QR, mutasi, maupun sensus.

Kolom `klasifikasi_pencatatan` berada di tabel
`rsns_custom_logistik_non_medis_aset`. Inisialisasi modul menambahkan kolom
secara idempoten. Semua data lama mula-mula **Belum Ditentukan**; tidak ada
klasifikasi massal otomatis saat halaman dibuka.

Daftar registrasi dan laporan inventaris unit menampilkan semua klasifikasi
dengan filter. KIB, rekap KIB, KPI aset, laporan kondisi/masa manfaat aset,
dan perhitungan penyusutan hanya mengambil `ASET`. Riwayat penyusutan lama
tetap tersimpan. Daftar pemeliharaan dan penghapusan tetap dapat digunakan
untuk semua barang tahan lama.

Menu **Non-Aset Unit** tersedia di navigasi dan bagian Aset pada dashboard.
Hak aksesnya mengikuti Registrasi Aset (ditampilkan sebagai "Registrasi Aset
& Non-Aset Unit" di pengaturan hak akses). Daftar dan ekspor XLSX khusus menu
ini dikunci di server ke Inventaris Non-Aset, dengan filter unit, pencarian,
kelompok, jenis, dan ketersediaan harga. Filter unit adalah penyaring daftar,
bukan pemberian hak akses baru kepada role unit.

Penyusutan tetap mengecualikan tanah dan konstruksi dalam pengerjaan serta
memakai syarat masa manfaat/residu yang sudah berlaku. Pemrosesan memeriksa
ulang dan mengunci data sebelum membentuk jurnal.

## Validasi harga melalui formulir

Harga yang diisi pada registrasi baru digunakan sebagai harga perolehan.
Pada data lama, perubahan metadata tanpa perubahan harga mempertahankan
klasifikasi. Untuk memvalidasi harga lama yang tidak berubah, centang
validasi harga perolehan dan isi alasan. Perubahan harga/klasifikasi wajib
disertai alasan dan dicatat dalam log yang sudah ada.

Jika barang memiliki riwayat/akumulasi penyusutan, perubahan tersebut
ditolak sampai rekonsiliasi ditangani. Implementasi ini tidak membatalkan
jurnal atau menghapus riwayat untuk meloloskan klasifikasi ulang.

## Migrasi data lama

Sebelum dipakai untuk laporan operasional, tinjau dan migrasikan data lama.
Selama masih Belum Ditentukan, barang tidak masuk total aset atau penyusutan.
Lakukan pada salinan database terlebih dahulu dan hentikan sementara
penulisan inventaris/penyusutan ketika menerapkan migrasi.

Script CLI tidak membaca `config.php`; database tujuan harus eksplisit lewat
environment `INVENTARIS_DSN`, `INVENTARIS_DB_USER`, dan
`INVENTARIS_DB_PASSWORD`. Contoh DSN: `mysql:host=127.0.0.1;dbname=database_uji;charset=utf8mb4`.
Simpan berkas preview/cadangan di luar direktori yang dilayani web.

```powershell
php plugins/logistik_non_medis/tools/classify_inventory.php --schema
php plugins/logistik_non_medis/tools/classify_inventory.php --preview=C:/backup/inventaris-preview.json
# Tinjau summary dan setiap item/reason sebelum langkah berikutnya.
php plugins/logistik_non_medis/tools/classify_inventory.php --apply=C:/backup/inventaris-preview.json --backup=C:/backup/inventaris-sebelum.json
```

- `--schema` hanya menambahkan struktur.
- `--preview` membaca data dan menghasilkan usulan, jumlah, nilai perolehan,
  serta alasan barang yang belum dapat diklasifikasikan.
- `--apply` menolak snapshot yang berubah dan menyimpan salinan seluruh
  register sebelum memperbarui klasifikasi dalam transaksi. Tidak menulis
  transaksi penyusutan. File cadangan tidak boleh sudah ada.
- Data dengan riwayat penyusutan, harga tidak valid, harga tepat pada batas,
  atau `user_input` berisi `import` tetap menunggu validasi/rekonsiliasi.
- Penanda `user_input` adalah bantuan identifikasi, bukan bukti validitas
  dokumen perolehan. Tinjau usulan terhadap sumber sebelum menerapkannya.
- Impor eksternal yang tidak menyertakan klasifikasi akan mendapat default
  Belum Ditentukan. Harga referensi impor tidak otomatis dikapitalisasi.

Cadangan memuat `rows` sebelum migrasi dan `plan.items` yang menjelaskan
perubahan/alasan. Untuk pemulihan segera sebelum transaksi lain berjalan,
cocokkan ID/kode dan kembalikan **hanya kolom klasifikasi** dari cadangan di
dalam transaksi. Jangan menimpa seluruh register atau memulihkan klasifikasi
secara otomatis jika sudah ada penyusutan/perubahan baru; rekonsiliasi dulu.
Ambil pula cadangan database standar sebelum deployment.

## Gudang BHP, aset, VIP Pack, dan realisasi belanja

Sistem menambahkan dua lokasi aktif saat menu lokasi atau penerimaan dibuka:
`GUDANG-BHP-RUTIN` (**Gudang BHP Rutin**) dan `GUDANG-ASET` (**Gudang Aset**).
Stok lama di `GUDANG-LOGISTIK` tidak dipindahkan otomatis. Pindahkan dengan
Mutasi Antar Gudang agar kartu stok tetap mencatat asal, tujuan, dan waktu.
Pada penerimaan baru, petugas wajib memilih lokasi: BHP rutin ke Gudang BHP
Rutin dan barang inventaris/aset ke Gudang Aset.

Menu **Komposisi VIP Pack** menggunakan resep komposisi yang sama dengan
produksi. Buat dahulu barang hasil, misalnya `VIP-PACK`, pada master barang;
kemudian masukkan isi per satu paket: tas, handuk, tisu, sikat gigi, dan
seterusnya. Pengeluaran `n` VIP Pack melalui SPPB mengurangi setiap isi
sebesar `n × komposisi`. VIP Pack tidak dibuat sebagai stok jadi dan tidak
memiliki retur pasien karena seluruh isinya dibawa pulang.

**Realisasi Belanja BHP** dapat dibuka dari tombol di Perencanaan Kebutuhan,
dashboard Pengadaan, atau Barang Masuk Gudang. Laporan dan ekspor hanya
mengambil baris penerimaan berstatus Selesai yang sudah memposting stok,
menampilkan nomor penerimaan, vendor, nomor PO jika ada, qty/harga satuan
dasar, total, dan rekap kategori. Draft, penolakan, dan penerimaan yang belum
diposting tidak dihitung.

## Verifikasi

```powershell
php plugins/logistik_non_medis/tests/classification.php
```

Tes memakai MySQL lokal (`127.0.0.1:3306`, akun `root`, password kosong secara
default), membuat database acak `codex_inventory_test_*`, memuat **struktur
saja**, dan menghapus database uji di akhir. Dapat diubah lewat
`INVENTARIS_TEST_DSN` (tanpa dbname), `INVENTARIS_TEST_USER`, dan
`INVENTARIS_TEST_PASSWORD`. Tidak membaca kredensial/database aplikasi.

Cakupan: batas harga, batch 10 kursi, migrasi beserta cadangan dan snapshot
usang, perlindungan riwayat, filter inventaris, KPI aset, perhitungan dan
posting penyusutan, pengisian harga, serta konfirmasi harga impor melalui
formulir. Tes memanggil metode aplikasi dengan query database sebenarnya;
render HTML dan identitas pengguna diganti fixture.
