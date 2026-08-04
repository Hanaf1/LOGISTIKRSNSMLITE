# Panduan Penyesuaian Modul Logistik Non Medis

Dokumen ini berisi rangkuman penyesuaian terbaru pada struktur database, penyesuaian menu/tampilan, serta pengaturan notifikasi *real-time* menggunakan Workerman berdasarkan perubahan terbaru (termasuk *commit* dari Rifangga).

---

## 1. PENYESUAIAN TABEL DATABASE (DB)

Modul Logistik Non Medis kini menggunakan serangkaian tabel kustom dengan awalan `rsns_custom_logistik_non_medis_` untuk memisahkan data spesifik logistik non-medis dari core mLITE.

*   **Tabel Master Aset & Inventaris:** 
    *   `inventaris_kategori`, `inventaris_kelompok`, `inventaris_jenis` — digunakan untuk hierarki klasifikasi master aset.
    *   `inventaris_master` — tabel utama master barang (Non-Medis) dan Unit.
*   **Tabel Transaksi Aset:** 
    *   `aset` — menyimpan data registrasi aset/KIB (Kartu Inventaris Barang).
    *   `aset_mutasi`, `aset_pemeliharaan`, `aset_penyusutan`, `aset_penghapusan`, `aset_sensus`.
*   **Tabel Pengadaan & Distribusi:**
    *   `sppb` & `sppb_detail` — Surat Permintaan Pengadaan Barang.
    *   `po`, `pr`, `penerimaan`, `mutasi`, `opname`.
*   **Keamanan & Akses:**
    *   `user_roles` — menyimpan pemetaan peran (Admin, Logistik, Kepala Unit/Sie/Bidang, Unit) yang mengatur menu apa saja yang bisa diakses oleh setiap user.

Semua tabel ini dapat digenerate secara otomatis melalui fungsi sinkronisasi struktur yang sudah ditanam di dalam `Admin.php`.

---

## 2. PENYESUAIAN MENU & FITUR

Berdasarkan *update* terakhir, ada beberapa perubahan pada menu manajemen dan antarmuka pengguna:

1.  **Role-Based Access Control (RBAC):**
    *   **Administrator & Logistik:** Memiliki akses ke seluruh tab manajemen, konfigurasi aset, master barang, verifikasi dokumen, dan proses pengadaan.
    *   **Unit (Staff/Kepala):** Akses dibatasi secara ketat ke menu yang menjadi hak mereka saja (misal: hanya melihat SPPB/Distribusi untuk unit mereka). *Routing* keamanan telah diaktifkan agar user non-admin langsung diarahkan ke *dashboard* unit.
2.  **Manajemen Aset (Baru):**
    *   Telah ditambahkan fitur manajemen Kategori, Kelompok, dan Jenis untuk Aset KIB. Form registrasi Aset telah diperbarui agar menampilkan hierarki dropdown dari klasifikasi master.
3.  **Pembaruan UI SPPB (Distribusi):**
    *   Tab "Aktif" dan "Riwayat" telah diperbaiki sehingga bisa berfungsi memfilter *list* SPPB (Aktif: belum selesai/batal, Riwayat: sudah selesai/batal) untuk semua user (termasuk Admin & Logistik).
    *   **Penghapusan Fitur:** Tombol dan form "Import SPPB Mingguan" telah ditiadakan sesuai permintaan (dihilangkan dari antarmuka).
    *   *Bug* halaman (*Pagination* & Syntax Error) pada registrasi aset dan SPPB telah di-*fix*.

---

## 3. PENGATURAN NOTIF WORKERMAN (Berdasarkan Commit Terakhir - rifangga)

Fitur notifikasi instan sekarang sudah mendukung *push notification* menggunakan pendekatan **Workerman Queue** tanpa perlu koneksi langsung dari skrip PHP (CURL) ke port Workerman. Hal ini mengatasi masalah asinkron saat pembuatan "Permintaan" dan pembaruan status menjadi "Siap Diambil".

**Cara Kerjanya:**
1.  **Queue File System:**
    Ketika ada notifikasi baru dari sistem mLITE (via PHP), data *event* notifikasi tidak dikirim langsung ke *socket*, melainkan ditulis *(append)* ke dalam *file log/queue*:  
    `tmp/logistik_notifications.queue` (direktori tmp di dalam modul).
2.  **Workerman Timer:**
    Skrip `workerman.php` kini dilengkapi *Timer* (berjalan setiap 0.5 detik) yang terus membaca baris baru dari *file queue* tersebut.
3.  **Broadcast:**
    Jika ada baris *event* baru (dengan tipe `logistik_notification`), Workerman otomatis membaca JSON tersebut dan mem-*broadcast*-nya ke semua koneksi *WebSocket* aktif di *browser* (*client*).

**Langkah Menjalankan / Mengatur:**
1.  Pastikan dependensi *Workerman* sudah ter-*install* di *vendor*.
2.  Jalankan skrip *Workerman* dari terminal/CMD:
    ```bash
    php workerman.php start
    ```
3.  Konfigurasi *Port*: Secara *default* *server* berjalan pada `websocket://0.0.0.0:3892`. Pastikan *port* 3892 terbuka dan tidak diblokir *firewall*. Konfigurasi di sisi *frontend* (Javascript klien) harus mengarah ke IP *server* dan *port* `3892`.
4.  Pastikan *folder* `tmp/` pada direktori memiliki *permission* baca/tulis yang memadai (*writeable* oleh *web server*), agar skrip PHP utama bisa mencatat *queue event* di `logistik_notifications.queue`.

*(File Workerman telah direfaktor agar notifikasi dari backend web berjalan mulus tanpa mengganggu response time user)*.
