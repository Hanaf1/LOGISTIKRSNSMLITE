<?php
define('BASE_DIR', dirname(__DIR__, 3));
require_once(__DIR__.'/../../../config.php');

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

const KONDISI_SAH = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

// Dipakai saat aset discan sementara tidak ada periode terjadwal yang berjalan.
// Satu periode per tahun, supaya hasilnya tetap muncul di Kertas Kerja dan
// Riwayat Sensus seperti periode biasa, bukan tersimpan di tempat terpisah.
define('PERIODE_LANGSUNG', 'Sensus Langsung '.date('Y'));

$kode = trim($_GET['kode'] ?? $_POST['kode'] ?? '');
$aset = null;
$kertas = null;   // baris kertas kerja pada periode yang masih terbuka
$riwayat = [];
$error = '';
$kesalahanForm = [];
$sukses = '';
$isian = [
    'keberadaan' => $_POST['keberadaan'] ?? '',
    'kondisi' => $_POST['kondisi'] ?? 'Baik',
    'catatan' => trim($_POST['catatan'] ?? ''),
    'petugas' => trim($_POST['petugas'] ?? ''),
];

try {
    $pdo = new PDO('mysql:host='.DBHOST.';port='.DBPORT.';dbname='.DBNAME.';charset=utf8', DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Halaman publik ini tidak melewati systems/Main.php yang memasang zona
    // waktu aplikasi, jadi dipasang sendiri. Tanpa ini PHP memakai UTC
    // sementara MySQL memakai jam server, dan jam pada data selisih jauh.
    $zona = $pdo->query("SELECT value FROM mlite_settings WHERE module='settings' AND field='timezone'")->fetchColumn();
    if ($zona) {
        date_default_timezone_set($zona);
    }

    if ($kode === '') {
        $error = 'Kode aset tidak ditemukan pada QR.';
    } else {
        $stmt = $pdo->prepare("
            SELECT a.kode_aset, a.nama_aset, a.nomor_inventaris, a.status, a.status_kondisi,
                   a.kode_unit, a.kode_lokasi,
                   COALESCE(iu.nama, u.nama_unit, a.kode_unit) nama_unit
            FROM rsns_custom_logistik_non_medis_aset a
            LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
              ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
            LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = a.kode_unit
            WHERE a.kode_aset = ? LIMIT 1
        ");
        $stmt->execute([$kode]);
        $aset = $stmt->fetch();

        if (!$aset) {
            $error = 'Data aset tidak ditemukan.';
        } elseif ($aset['status'] !== 'Aktif') {
            $error = 'Aset ini berstatus '.$aset['status'].', pencatatan sensus tidak bisa diisi.';
        }
    }

    // Baris kertas kerja yang masih boleh diisi. Periode berstatus Selesai atau
    // Dibatalkan sengaja dilewati supaya hasil yang sudah disertifikasi aman.
    $ambilKertas = function () use ($pdo, $aset) {
        $stmt = $pdo->prepare("SELECT * FROM rsns_custom_logistik_non_medis_aset_sensus
            WHERE kode_aset = ? AND status_sensus_periode IN ('Aktif', 'Draft')
            ORDER BY FIELD(status_sensus_periode, 'Aktif', 'Draft'), tanggal_mulai DESC, id DESC LIMIT 1");
        $stmt->execute([$aset['kode_aset']]);
        return $stmt->fetch() ?: null;
    };

    // Baris kertas kerja pada periode bawaan, dibuat saat aset pertama kali
    // discan di luar periode terjadwal.
    $buatKertasLangsung = function () use ($pdo, $aset) {
        $cari = $pdo->prepare("SELECT * FROM rsns_custom_logistik_non_medis_aset_sensus
            WHERE nama_sensus = ? AND kode_aset = ? LIMIT 1");
        $cari->execute([PERIODE_LANGSUNG, $aset['kode_aset']]);
        $ada = $cari->fetch();
        if ($ada) {
            return $ada;
        }
        $tahun = date('Y');
        $buat = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_aset_sensus
            (nama_sensus, tanggal_mulai, tanggal_selesai, keterangan_sensus, kode_unit_sensus,
             status_sensus_periode, kode_aset, sistem_kode_unit, sistem_kode_lokasi,
             sistem_status_kondisi, status_sensus_item, status_penyesuaian, status_sertifikasi,
             tgl_input, user_input)
            VALUES (?, ?, ?, 'Pencatatan langsung dari QR aset, di luar periode terjadwal.', ?,
                    'Aktif', ?, ?, ?, ?, 'Belum Sensus', 'Belum Disesuaikan', 'Belum Sertifikasi',
                    NOW(), 'QR')");
        $buat->execute([
            PERIODE_LANGSUNG,
            $tahun.'-01-01',
            $tahun.'-12-31',
            (string)($aset['kode_unit'] ?? ''),
            $aset['kode_aset'],
            (string)($aset['kode_unit'] ?? ''),
            (string)($aset['kode_lokasi'] ?? ''),
            $aset['status_kondisi'] ?: 'Baik',
        ]);
        $cari->execute([PERIODE_LANGSUNG, $aset['kode_aset']]);
        return $cari->fetch() ?: null;
    };

    if (!$error) {
        $kertas = $ambilKertas();
    }

    if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Kolom umpan: diisi bot, disembunyikan dari orang.
        if (trim($_POST['alamat_surel'] ?? '') !== '') {
            $sukses = 'Pencatatan diterima.';
        } else {
            // Tanpa periode terjadwal, barisnya dibuat sekarang juga.
            if (!$kertas) {
                $kertas = $buatKertasLangsung();
            }
            if (!$kertas) {
                $kesalahanForm['umum'] = 'Baris sensus gagal dibuat, silakan coba lagi.';
            }
            if (!in_array($isian['keberadaan'], ['Ada', 'Tidak Ada'], true)) {
                $kesalahanForm['keberadaan'] = 'Pilih keberadaan aset.';
            }
            if ($isian['keberadaan'] === 'Ada' && !in_array($isian['kondisi'], KONDISI_SAH, true)) {
                $kesalahanForm['kondisi'] = 'Pilih kondisi yang tersedia.';
            }
            if ($isian['keberadaan'] === 'Tidak Ada' && $isian['catatan'] === '') {
                $kesalahanForm['catatan'] = 'Catatan wajib diisi bila aset tidak ditemukan.';
            }
            if (mb_strlen($isian['catatan']) > 2000) {
                $kesalahanForm['catatan'] = 'Catatan maksimal 2000 karakter.';
            }
            if (mb_strlen($isian['petugas']) < 2 || mb_strlen($isian['petugas']) > 40) {
                $kesalahanForm['petugas'] = 'Tulis nama petugas, 2 sampai 40 karakter.';
            }

            if (!$kesalahanForm) {
                // Penentuan status mengikuti postSimpanHasilSensusUnit di modul
                // admin, supaya hasil dari QR dan dari komputer sama artinya.
                if ($isian['keberadaan'] === 'Tidak Ada') {
                    $ubah = $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset_sensus
                        SET status_sensus_item = 'Tidak Ditemukan', fisik_kode_unit = NULL,
                            fisik_kode_lokasi = NULL, fisik_status_kondisi = NULL,
                            catatan_temuan = ?, tanggal_scan = NOW(), petugas_scan = ?,
                            status_penyesuaian = 'Belum Disesuaikan'
                        WHERE id = ?");
                    $ubah->execute([$isian['catatan'], mb_substr('QR: '.$isian['petugas'], 0, 100), $kertas['id']]);
                    $hasil = 'Tidak Ditemukan';
                } else {
                    $sama = $isian['kondisi'] === ($kertas['sistem_status_kondisi'] ?: 'Baik');
                    $hasil = $sama ? 'Sesuai' : 'Selisih Kondisi';
                    $ubah = $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset_sensus
                        SET status_sensus_item = ?, fisik_kode_unit = ?, fisik_kode_lokasi = ?,
                            fisik_status_kondisi = ?, catatan_temuan = ?, tanggal_scan = NOW(),
                            petugas_scan = ?, status_penyesuaian = ?
                        WHERE id = ?");
                    $ubah->execute([
                        $hasil,
                        $kertas['sistem_kode_unit'],
                        $kertas['sistem_kode_lokasi'],
                        $isian['kondisi'],
                        $isian['catatan'],
                        mb_substr('QR: '.$isian['petugas'], 0, 100),
                        $sama ? 'Sudah Disesuaikan' : 'Belum Disesuaikan',
                        $kertas['id'],
                    ]);
                }

                $sukses = 'Hasil sensus tersimpan sebagai "'.$hasil.'" pada periode '.$kertas['nama_sensus'].'.';
                $kertas = $ambilKertas();
            }
        }
    }

    if ($aset && !$error) {
        $stmt = $pdo->prepare("SELECT nama_sensus, tanggal_mulai, tanggal_selesai, status_sensus_periode,
                   status_sensus_item, fisik_status_kondisi, catatan_temuan, tanggal_scan, petugas_scan
            FROM rsns_custom_logistik_non_medis_aset_sensus
            WHERE kode_aset = ? ORDER BY tanggal_mulai DESC, id DESC LIMIT 50");
        $stmt->execute([$aset['kode_aset']]);
        $riwayat = $stmt->fetchAll();
    }
} catch (Throwable $ex) {
    $error = $error ?: 'Koneksi data aset belum tersedia.';
}

$halamanInfo = 'aset-info.php?kode='.rawurlencode($kode);

function warnaStatus($status)
{
    if ($status === 'Sesuai') return 'ok';
    if ($status === 'Tidak Ditemukan') return 'bad';
    if ($status === 'Belum Sensus') return 'netral';
    return 'warn';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pencatatan Sensus<?= $aset ? ' - '.e($aset['nama_aset']) : '' ?></title>
  <style>
    * { box-sizing: border-box; }
    body { margin:0; font-family: Arial, Helvetica, sans-serif; color:#2f4050; background:#f4f6f8; }
    .page { max-width: 920px; margin:0 auto; padding:24px 14px 34px; }
    .brand { display:flex; align-items:center; gap:12px; margin-bottom:16px; color:#44515f; }
    .brand-logo { width:46px; height:46px; object-fit:contain; display:block; flex-shrink:0; }
    .brand-name { font-size:17px; font-weight:800; color:#2f4050; line-height:1.25; }
    .brand-sub { font-size:13px; color:#6f7d8a; }
    .card { background:#fff; border:1px solid #dde3e8; border-radius:8px; overflow:hidden; box-shadow:0 8px 24px rgba(31,45,61,.08); margin-bottom:18px; }
    .summary { padding:24px; }
    h1 { margin:0 0 8px; font-size:24px; line-height:1.25; }
    h2 { margin:0; font-size:15px; font-weight:700; }
    .code { font-family:"Courier New", monospace; font-weight:700; color:#556575; margin-bottom:10px; }
    .target { color:#657381; line-height:1.55; margin:0; }
    .periode { margin-top:12px; padding:10px 12px; border-radius:6px; background:#eef4ff; color:#2f4f8a; font-size:13px; font-weight:700; }
    .periode.tidak { background:#fdf4e7; color:#96632a; }
    form { padding:20px 24px 24px; border-top:1px solid #e7ebef; }
    .field { margin-bottom:16px; }
    .label { font-size:11px; color:#7b8794; text-transform:uppercase; font-weight:700; margin-bottom:6px; display:block; }
    input[type=text], textarea, select { width:100%; padding:11px 12px; border:1px solid #d6dde4; border-radius:6px; font-size:15px; font-family:inherit; color:#2f4050; background:#fff; }
    textarea { min-height:90px; resize:vertical; }
    input:focus, textarea:focus, select:focus { outline:none; border-color:#4f8f7b; box-shadow:0 0 0 3px rgba(79,143,123,.15); }
    .pilihan { display:flex; gap:10px; flex-wrap:wrap; }
    .pilihan label { flex:1 1 180px; display:flex; align-items:center; gap:9px; padding:13px 14px; border:1px solid #d6dde4; border-radius:6px; cursor:pointer; font-weight:700; background:#fff; }
    .pilihan label:hover { border-color:#4f8f7b; }
    .invalid { color:#b74444; font-size:12px; margin-top:5px; font-weight:700; }
    .umpan { position:absolute; left:-9999px; }
    .actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .btn { display:inline-block; padding:13px 20px; border-radius:6px; font-size:15px; font-weight:700; border:0; cursor:pointer; text-decoration:none; }
    .btn-utama { background:#4f8f7b; color:#fff; }
    .btn-utama:hover { background:#437a69; }
    .btn-lain { background:#eef1f4; color:#44515f; }
    .btn-lain:hover { background:#e2e7ec; }
    .kabar { padding:16px 24px; border-top:1px solid #e7ebef; line-height:1.6; }
    .kabar.ok { background:#e8f5ed; color:#2f7d46; font-weight:700; }
    .kabar.bad { background:#fdeaea; color:#b74444; font-weight:700; }
    .head-riwayat { padding:16px 24px; border-bottom:1px solid #e7ebef; background:#fbfcfd; }
    .bungkus-tabel { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:14px; }
    th, td { padding:11px 12px; border-bottom:1px solid #eef1f4; text-align:left; vertical-align:top; }
    th { font-size:11px; color:#7b8794; text-transform:uppercase; letter-spacing:.4px; background:#fbfcfd; white-space:nowrap; }
    td.tgl { white-space:nowrap; }
    tr:last-child td { border-bottom:0; }
    .tanda { display:inline-block; padding:3px 9px; border-radius:4px; font-size:12px; font-weight:700; white-space:nowrap; }
    .tanda.ok { background:#e8f5ed; color:#2f7d46; }
    .tanda.warn { background:#fdf4e7; color:#96632a; }
    .tanda.bad { background:#fdeaea; color:#b74444; }
    .tanda.netral { background:#eef1f4; color:#6f7d8a; }
    .kosong { padding:26px 24px; text-align:center; color:#8a96a3; }
    .empty { padding:34px 24px; text-align:center; }
    .empty h1 { color:#b74444; }
    @media (max-width: 700px) {
      .summary { padding:18px; }
      form { padding:16px 18px 20px; }
      h1 { font-size:21px; }
      .btn { width:100%; text-align:center; }
      th, td { padding:9px 10px; }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="brand">
      <img class="brand-logo" src="rsunurussyifa-logo.png" alt="Logo RSU Nurusyifa">
      <div>
        <div class="brand-name">RSU Nurusyifa</div>
        <div class="brand-sub">Pencatatan Sensus Inventaris Non-Medis</div>
      </div>
    </div>

    <?php if ($error): ?>
      <section class="card">
        <div class="empty">
          <h1><?= e($error) ?></h1>
          <p>Pastikan QR berasal dari label aset yang dicetak melalui sistem.</p>
        </div>
      </section>
    <?php else: ?>
      <section class="card">
        <div class="summary">
          <h1>Isi Pencatatan Sensus</h1>
          <div class="code">Kode Inventaris: <?= e($aset['nomor_inventaris'] ?: $aset['kode_aset']) ?></div>
          <p class="target">
            <strong><?= e($aset['nama_aset']) ?></strong> &middot; <?= e($aset['nama_unit'] ?: '-') ?>
            &middot; Kondisi tercatat: <?= e($aset['status_kondisi']) ?>
          </p>
          <?php if ($kertas): ?>
            <div class="periode">
              Periode berjalan: <?= e($kertas['nama_sensus']) ?>
              (<?= e($kertas['tanggal_mulai']) ?> s.d. <?= e($kertas['tanggal_selesai']) ?>)
              &middot; status saat ini: <?= e($kertas['status_sensus_item']) ?>
            </div>
          <?php else: ?>
            <div class="periode tidak">
              Tidak ada periode sensus terjadwal yang berjalan. Hasil pemeriksaan
              akan dicatat pada periode <strong><?= e(PERIODE_LANGSUNG) ?></strong>.
            </div>
          <?php endif; ?>
        </div>

        <?php if ($sukses): ?>
          <div class="kabar ok"><?= e($sukses) ?></div>
        <?php elseif (!empty($kesalahanForm['umum'])): ?>
          <div class="kabar bad"><?= e($kesalahanForm['umum']) ?></div>
        <?php endif; ?>

          <form method="post" action="">
            <input type="hidden" name="kode" value="<?= e($kode) ?>">
            <div class="umpan" aria-hidden="true">
              <label>Alamat surel <input type="text" name="alamat_surel" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="field">
              <span class="label">Keberadaan Aset</span>
              <div class="pilihan">
                <label><input type="radio" name="keberadaan" value="Ada" required
                       <?= $isian['keberadaan'] === 'Ada' ? 'checked' : '' ?>> Ada di ruangan</label>
                <label><input type="radio" name="keberadaan" value="Tidak Ada"
                       <?= $isian['keberadaan'] === 'Tidak Ada' ? 'checked' : '' ?>> Tidak ditemukan</label>
              </div>
              <?php if (!empty($kesalahanForm['keberadaan'])): ?>
                <div class="invalid"><?= e($kesalahanForm['keberadaan']) ?></div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label class="label" for="kondisi">Kondisi Saat Diperiksa</label>
              <select id="kondisi" name="kondisi">
                <?php foreach (KONDISI_SAH as $pilihan): ?>
                  <option value="<?= e($pilihan) ?>" <?= $isian['kondisi'] === $pilihan ? 'selected' : '' ?>><?= e($pilihan) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!empty($kesalahanForm['kondisi'])): ?>
                <div class="invalid"><?= e($kesalahanForm['kondisi']) ?></div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label class="label" for="catatan">Catatan / Temuan</label>
              <textarea id="catatan" name="catatan" maxlength="2000"
                        placeholder="Wajib diisi bila aset tidak ditemukan"><?= e($isian['catatan']) ?></textarea>
              <?php if (!empty($kesalahanForm['catatan'])): ?>
                <div class="invalid"><?= e($kesalahanForm['catatan']) ?></div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label class="label" for="petugas">Nama Petugas Sensus</label>
              <input type="text" id="petugas" name="petugas" maxlength="40" required
                     placeholder="Nama petugas yang memeriksa" value="<?= e($isian['petugas']) ?>">
              <?php if (!empty($kesalahanForm['petugas'])): ?>
                <div class="invalid"><?= e($kesalahanForm['petugas']) ?></div>
              <?php endif; ?>
            </div>

            <div class="actions">
              <button type="submit" class="btn btn-utama">Simpan Hasil Sensus</button>
              <a class="btn btn-lain" href="<?= e($halamanInfo) ?>">Kembali ke Info Aset</a>
            </div>
          </form>
      </section>

      <section class="card">
        <div class="head-riwayat"><h2>Riwayat Sensus Aset Ini</h2></div>
        <?php if (!$riwayat): ?>
          <div class="kosong">Aset ini belum pernah masuk periode sensus.</div>
        <?php else: ?>
          <div class="bungkus-tabel">
            <table>
              <thead>
                <tr>
                  <th>Periode</th>
                  <th style="width:130px;">Hasil</th>
                  <th style="width:110px;">Kondisi Fisik</th>
                  <th style="width:120px;">Tanggal Scan</th>
                  <th style="width:140px;">Petugas</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($riwayat as $baris): ?>
                  <tr>
                    <td>
                      <strong><?= e($baris['nama_sensus']) ?></strong><br>
                      <span style="color:#8a96a3; font-size:12px;"><?= e($baris['status_sensus_periode']) ?></span>
                    </td>
                    <td><span class="tanda <?= e(warnaStatus($baris['status_sensus_item'])) ?>"><?= e($baris['status_sensus_item']) ?></span></td>
                    <td><?= e($baris['fisik_status_kondisi'] ?: '-') ?></td>
                    <td class="tgl"><?= $baris['tanggal_scan'] ? e(date('d/m/Y H:i', strtotime($baris['tanggal_scan']))) : '-' ?></td>
                    <td><?= e($baris['petugas_scan'] ?: '-') ?></td>
                    <td><?= e($baris['catatan_temuan'] ?: '-') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
