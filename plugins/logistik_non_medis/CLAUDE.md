
# CLAUDE.md — Modul Logistik Non Medis

Panduan kerja untuk sesi Claude Code di plugin ini. Status modul: **fungsional tapi belum diuji manual**.
Banyak fitur sudah ada di kode namun belum pernah dibuka di browser per role. Prioritas utama sekarang
adalah **verifikasi**, bukan menambah fitur baru.

---

## 1. Fakta dasar lingkungan

| Hal | Nilai |
|---|---|
| Root aplikasi | `C:\laragon\www\mlite_rsns` |
| Plugin ini | `plugins/logistik_non_medis` |
| Database | `mlite_rsns` (MySQL, user `root` tanpa password) |
| Prefix tabel | `rsns_custom_logistik_non_medis_` |
| URL admin | `http://localhost:7000/mlite_rsns/admin/logistik_non_medis/<route>?t=<token>` |
| Branch kerja | `experiment` (branch utama: `main`) |
| Berkas utama | `Admin.php` (±32.600 baris), `view/admin/*.html` (±211 berkas) |

Perintah MySQL dari Bash: `mysql -u root mlite_rsns -e "..."`. Jangan pakai nama database `rsns` (tidak ada).

---

## 2. Arsitektur yang wajib dipahami sebelum mengubah apa pun

### Routing
Nama method di `Admin.php` **adalah** nama route. `getGudangstok()` → `/logistik_non_medis/gudangstok`.
Prefix `get` / `post` / `any` menentukan HTTP method. Tidak ada tabel route terpisah — rename method = rename URL.

### Hak akses (ini sumber bug paling sering)
Izin tidak diambil dari nama method secara langsung, melainkan dicocokkan lewat rantai `if` di
`_getPermissionKeyForMethod()` (Admin.php ±430–630) memakai `strpos($method, '<slug>')`.

**Konsekuensi yang harus selalu dicek saat menambah method baru:**
- Method baru yang namanya tidak mengandung slug mana pun → **tidak terlindungi / salah izin**.
- Pernah terjadi: `getExportRealisasirkbubelanja` tidak cocok dengan `strpos($method,'realisasibelanja')`,
  sehingga harus di-rename jadi `getExportRkburealisasibelanja`. Cek pencocokan substring, bukan hanya "mirip".

Tiga tempat yang harus konsisten saat menambah/menghapus menu:
1. `_getPermissionKeyForMethod()` — blok `if` pemetaan izin.
2. Array `$menus` (±baris 644) — label menu → slug.
3. Tabel `rsns_custom_logistik_non_medis_role_permissions` (kolom `permissions`, CSV) dan
   `..._role_permission_item` (baris per izin). **Keduanya** harus diperbarui.

Menu tidak muncul padahal kode sudah benar? Hampir selalu karena slug-nya belum ada di
`role_permissions` role tersebut. Cek dulu sebelum menyalahkan tampilan.

### Migrasi skema & data
Dilakukan idempoten di dalam method `_init*()` (`_initStok`, `_initGudangProduksi`, `_initSppb`, dst.),
dijaga oleh flag di tabel `mlite_settings`. Flag yang sudah dipakai:
`migrasi_izin_paket_ecer`, `migrasi_jenis_komposisi`, `migrasi_harga_komposisi`, `migrasi_keuangan_nonrutin`.

> **PENTING:** DDL MySQL memicu **implicit COMMIT**. Simulasi "dalam transaksi lalu rollback" akan **gagal**
> kalau memanggil `_init*()` yang berisi `CREATE TABLE`. Ini pernah menyebabkan data dummy ikut tersimpan
> permanen. Kalau butuh simulasi aman: panggil `_init*()` **sebelum** `beginTransaction()`.

### Template engine (`systems/lib/Templates.php`)
Sintaks yang valid: `{if: ...}` `{else}` `{/if}`, `{loop: $a as $k => $v}` `{/loop}`, `{$a.b}` (→ `$a['b']`),
`{?= php ?}`.

Jebakan yang sudah terbukti memakan waktu:
- **`{count: ...}` BUKAN tag yang valid** — hasilnya kosong. Hitung di controller, kirim sebagai variabel.
- Isi `{?= ... ?}` **tidak boleh mengandung `}`** (regex-nya `[^}]*`).
- **Tidak ada auto-escaping.** Escape manual data yang berasal dari input pengguna.

### Aturan domain: barang berkomposisi
Barang dengan komposisi aktif (`master_barang.jenis_komposisi` = `Paket` atau `Olahan`)
**tidak punya stok sendiri dan tidak pernah dibeli**. Yang distok/dibeli/dimutasi/diopname adalah **isinya**.
Relasi disimpan di `produksi_resep(kode_item_hasil, kode_item_bahan, qty_bahan_per_hasil)`.

Guard `_hentikanJikaBerkomposisi()` menolak barang semacam ini di: mutasi, opname, rusak, penyesuaian,
retur, RKBU, PO, dan penerimaan. Saat menambah alur baru yang menyentuh stok, **pasang guard ini**.

Harga paket dihitung ulang otomatis (`_hitungUlangHargaKomposisi`) **hanya** untuk `jenis_komposisi='Paket'`
dan **hanya** bila semua isinya sudah berharga ≠ 0. `Olahan` (fotocopy) harganya manual, karena sudah
termasuk jasa — jangan diotomatiskan, itu akan merusak data harga yang benar.

---

## 3. Cara kerja: tools, MCP, dan skill

### Urutan default untuk tiap tugas
1. **Baca dulu, jangan tebak.** `Grep` untuk simbol/string yang diketahui, `Read` untuk path yang diketahui.
2. **`Agent` dengan `subagent_type=Explore`** hanya kalau butuh >3 query menyapu banyak berkas
   (`Admin.php` sangat besar; jangan dibaca utuh ke context).
3. Edit dengan `Edit`. `Write` hanya untuk berkas baru.
4. Verifikasi (lihat §4).

### Tool yang cocok di repo ini
| Kebutuhan | Pakai |
|---|---|
| Cari method / slug izin / nama tabel | `Grep` |
| Query & cek data | `Bash` → `mysql -u root mlite_rsns -e "..."` |
| Skrip sekali pakai (seed, perbaikan data) | PHP di `tools/`, atau Python (openpyxl) untuk Excel |
| Berkas sementara / harness | **scratchpad**, bukan folder proyek |
| Uji render view & panggil method privat | CLI harness reflection (lihat §4) |

### MCP yang tersedia
Sebagian besar server MCP di sesi ini (**GitHub, Linear, Slack, Notion, Figma, Atlassian, dll.**)
**belum terautentikasi** dan tidak bisa dipakai tanpa login interaktif. Jangan rencanakan pekerjaan
yang bergantung padanya; kalau memang perlu, beri tahu pengguna agar mengotorisasi lewat `/mcp`.

Untuk modul ini, MCP **tidak dibutuhkan**. Semua pekerjaan cukup dengan Bash + MySQL + tool berkas.
Jangan memaksakan MCP hanya karena tersedia.

### Skill
Panggil skill lewat tool `Skill` hanya bila namanya benar-benar muncul di daftar skill sesi.
**Jangan menebak nama skill.** Kalau tidak ada skill yang cocok, kerjakan langsung.

### Subagent
Jangan spawn subagent kecuali diminta pengguna atau memang perlu sapuan luas (`Explore`).
Setiap subagent mulai tanpa context dan harus diberi briefing lengkap: tujuan, yang sudah diketahui,
path dan nomor baris yang relevan. Prompt pendek menghasilkan kerja dangkal.

---

## 4. Verifikasi — bagian yang paling penting di modul ini

Urutan wajib, dari murah ke mahal:

### a. Syntax
```bash
php -l plugins/logistik_non_medis/Admin.php
```
Jalankan **setiap kali** selesai mengedit `Admin.php`. Berkas ini besar; error sintaks mematikan seluruh modul.

### b. Harness CLI (untuk logika tanpa browser)
Pola yang sudah terbukti (simpan di scratchpad, jangan di repo): boot `QueryWrapper` + `Templates`
dengan core palsu, lalu pakai Reflection untuk memanggil method privat dan merender view.
Contoh referensi pola: `tools/setup_paket_vip_fotocopy.php` dan skrip simulasi di scratchpad.

Ingat batasan implicit COMMIT di §2 saat memakai transaksi.

### c. Cek data langsung
```bash
mysql -u root mlite_rsns -e "SELECT ... "
```
Untuk memastikan migrasi jalan, izin tersimpan, stok konsisten.

### d. Browser (satu-satunya bukti fitur benar)
`php -l` dan query SQL membuktikan **kode berjalan**, bukan **fitur benar**.
Kalau belum dibuka di browser, **katakan terus terang bahwa belum diverifikasi** — jangan klaim selesai.

---

## 5. Checklist uji manual per role

Modul ini belum pernah diuji menyeluruh per role. Gunakan tabel ini sebagai daftar kerja.
Login sebagai user dengan role bersangkutan, lalu telusuri alurnya dari awal sampai akhir.

### Role yang ada di database
| Role | Jumlah user | Status uji |
|---|---|---|
| `kepala_unit` | 32 | belum |
| `kepala_sie` | 15 | belum |
| `unit` | 7 | belum |
| `kepala_bidang` | 5 | belum |
| `admin` | 2 | belum |
| `logistik` | 1 | belum |
| `keuangan` | 1 | belum |
| `gudang` | **0** | izin sudah ada, **belum ada user** |
| `bendahara` | **0** | izin sudah ada, **belum ada user** |
| `aset` | **0** | izin sudah ada, **belum ada user** |

Tiga role terakhir punya baris izin di `role_permissions` tapi tidak ada user-nya. Sebelum menguji,
pastikan dulu ke pengguna: apakah role itu memang akan dipakai, atau sisa rancangan lama yang harus dihapus.

### Yang harus dicek untuk setiap role
1. **Menu yang tampil** cocok dengan `role_permissions` — tidak ada menu bocor, tidak ada menu hilang.
2. **Akses langsung lewat URL** ke route yang tidak berizin → harus ditolak dan diarahkan ke `manage`,
   bukan malah terbuka. Ini uji keamanan, bukan kosmetik.
3. **Aksi AJAX** tanpa izin → harus balas JSON error, bukan HTML redirect.
4. **Data terbatas pada unit sendiri** untuk `unit` / `kepala_unit` (`kode_unit` di `user_roles`).

### Alur lintas role yang belum diuji ujung ke ujung
- **SPPB rutin:** unit membuat → kepala_unit setuju → kepala_sie → kepala_bidang → gudang packing →
  serah terima → stok isi paket berkurang & Cost Unit tercatat.
- **Non Rutin:** permintaan → kepala_bidang umum setuju → keuangan cairkan dana → PO dibuat.
- **RKBU:** perencanaan → kasie umum → kabid umum → keuangan → muncul di Realisasi Belanja.
- **Rangkap keuangan:** user dengan `user_roles.rangkap_keuangan = 1` harus tetap punya hak role aslinya
  **dan** bisa menyetujui tahap keuangan. Periksa lewat `_isKeuanganUser()`.
  Belum diterapkan untuk Lutfatul Ummah (Kasie Keuangan, NIK `02040620191996`) — tanyakan dulu.
- **Paket & Barang Ecer:** paket/fotocopy/ecer, termasuk guard penolakan di mutasi/opname/PO.

---

## 6. ATURAN WAJIB: berkas SQL harus ikut diperbarui

**Setiap kali struktur atau data acuan database berubah — dan sebelum setiap commit yang menyentuh
database — `full.sql` dan `schemanew.sql` WAJIB diregenerasi.** Jangan pernah mengedit kedua berkas itu
dengan tangan; keduanya hasil dump.

```bash
bash plugins/logistik_non_medis/tools/regen_sql.sh
```

Tersedia juga skill `generate-sql-logistik` yang menjalankan hal sama **plus uji impor otomatis**
ke database kosong dan pemeriksaan pola MySQL 5.7 (`--check-only` untuk periksa saja). Skill itu
menyalin skripnya ke `tools/regen_sql.sh` supaya kedua salinan tidak pernah melenceng.

Termasuk saat: menambah/mengubah tabel atau kolom, menambah menu & izin baru, mengubah data master
(barang, unit, satuan, kategori), atau mengubah role.

### Isi kedua berkas
| Berkas | Struktur | Master + stok akhir | Riwayat transaksi | Hak akses |
|---|---|---|---|---|
| `full.sql` | 78 tabel + 3 view | ikut | **tidak ikut** | **semua role** (10) |
| `schemanew.sql` | 78 tabel + 3 view | ikut | **tidak ikut** | **admin saja** |

"Stok akhir" (`stok`, `stok_batch`) sengaja ikut karena itu data penting, sedangkan riwayat transaksi
(`sppb`, `penerimaan`, `po`, `perencanaan`, `kartu_stok`, `mutasi`, `opname`, sensus/mutasi aset, dst.)
sengaja dikosongkan.

### Server rumah sakit memakai MySQL 5.7 — ini pernah menggagalkan impor
Lokal memakai MySQL 8.0.30, server memakai **MySQL 5.7**. Dump mentah dari MySQL 8 **gagal** di sana.
Skrip di atas sudah menangani semuanya, tapi kalau membuat dump manual, empat hal ini wajib diperbaiki:

1. **Collation `utf8mb4_0900_ai_ci` tidak dikenal 5.7** → `ERROR 1273 Unknown collation`.
   Akibat berantai: `CREATE TABLE` gagal, lalu semua `INSERT` gagal dengan `Table ... doesn't exist`.
   Ganti ke `utf8mb4_unicode_ci`.
2. **`DEFINER=root@localhost`** pada view/procedure → `ERROR 1227` bila user MySQL server berbeda.
   Hapus klausanya dan pakai `SQL SECURITY INVOKER`.
3. **Stored procedure `sp_lnm_sync_role_permission_normal` harus dibuat SEBELUM data.**
   Tabel `role_permissions` punya 3 trigger yang memanggil procedure itu. mysqldump menaruh routine di
   akhir berkas, sehingga impor gagal: `PROCEDURE ... does not exist` dan `role_permissions` jadi kosong.
4. **Jangan dump data `role_permission_item`.** Tabel itu turunan, diisi otomatis oleh trigger dari
   `role_permissions`. Kalau ikut didump hasilnya `ERROR 1062 Duplicate entry` dan impor berhenti di tengah.

### Cara menguji berkas SQL sebelum commit
```bash
mysql -u root -e "DROP DATABASE IF EXISTS uji_full; CREATE DATABASE uji_full CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root uji_full < plugins/logistik_non_medis/full.sql    # tanpa output = sukses
mysql -u root uji_full -e "SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_role_permissions;"
mysql -u root -e "DROP DATABASE uji_full;"
```
Impor yang benar menghasilkan **nol pesan error**. Satu error saja membuat sisa berkas tidak dieksekusi,
jadi jangan abaikan error yang "kelihatannya kecil".

---

## 7. Aturan komunikasi & kehati-hatian

- **Bahasa Indonesia**, kecuali pengguna beralih ke bahasa lain.
- Pengguna berulang kali meminta **kesederhanaan** ("buat simple saja"). Halaman padat sudah pernah ditolak.
  Kalau UI mulai rumit, pecah jadi tab dengan instruksi 3 langkah, jangan tambah kolom dan panel.
- **Jangan klaim sudah diuji kalau belum dibuka di browser.** Sebut eksplisit apa yang belum diverifikasi.
- **Konfirmasi dulu sebelum menghapus data**: DROP TABLE, hapus baris, `git reset --hard`, force push.
  Cadangkan ke scratchpad terlebih dahulu dan tunjukkan isinya.
- Berkas cache template (`tmp/*.html`, `admin/tmp/*.html`) memang tergenerate ulang, tapi tetap
  **beri tahu** kalau menghapusnya.
- Commit: jangan pernah menambahkan trailer `Co-Authored-By: Claude`. Ini aturan tetap dari pengguna.

---

## 8. Catatan status terkini (perbarui bila berubah)

- **WAHA adalah satu-satunya gateway WhatsApp.** Fonnte sudah dihapus total: kode, view, izin,
  3 tabel `*_fonnte_*`, dan DDL di `schemanew.sql` / `full.sql`. Bila WAHA nonaktif, notifikasi in-app
  tetap jalan dan pengiriman WA dilewati.
- Modul "Permintaan Logistik" sudah dihapus.
- `schemanew.sql` dan `full.sql` adalah **snapshot mysqldump**, bukan migrasi yang dieksekusi aplikasi.
  Skema sebenarnya dikelola oleh `_init*()`. Kalau mengubah skema, ubah `_init*()` lalu **regenerasi
  snapshot** dengan `tools/regen_sql.sh` (lihat §6).
- Kedua berkas SQL sudah diregenerasi dan **diuji impor ke database kosong tanpa error**, serta sudah
  dibuat kompatibel MySQL 5.7 untuk server rumah sakit.
- Belum dikerjakan (pernah ditawarkan, belum disetujui): filter status stok pada ekspor Excel,
  pengisian isi Paket Fisio Pack & toiletris, harga/stok 8 isi VIP Pack.
- Deploy VPS: metode yang dipilih = commit + push lalu `git pull` di VPS, **setelah** diuji lokal.
  Sebelum deploy pastikan `DEV_MODE = false` di `config.php` VPS dan database produksi sudah dicadangkan.
