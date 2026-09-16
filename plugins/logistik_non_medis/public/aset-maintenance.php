<?php
define('BASE_DIR', dirname(__DIR__, 3));
require_once(__DIR__.'/../../../config.php');

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Nomor catatan mengikuti seri buku maintenance yang dipakai menu admin
 * (postSaveBukuMaintenance), supaya catatan dari QR dan dari komputer logistik
 * berada dalam satu buku yang sama, tidak terpecah dua seri.
 */
function kode_catatan_berikutnya(PDO $pdo)
{
    $prefix = 'LOG-'.date('Ym').'-';
    $stmt = $pdo->prepare("SELECT kode_pemeliharaan FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
        WHERE kode_pemeliharaan LIKE ? ORDER BY kode_pemeliharaan DESC LIMIT 1");
    $stmt->execute([$prefix.'%']);
    $terakhir = $stmt->fetchColumn();
    $urut = $terakhir ? (int) substr($terakhir, -4) : 0;
    return $prefix.str_pad($urut + 1, 4, '0', STR_PAD_LEFT);
}

$kode = trim($_GET['kode'] ?? $_POST['kode'] ?? '');
$aset = null;
$buku = [];
$error = '';
$kesalahanForm = [];
$sukses = '';
$isian = [
    'tanggal' => $_POST['tanggal'] ?? date('Y-m-d'),
    'keterangan' => trim($_POST['keterangan'] ?? ''),
    'teknisi' => trim($_POST['teknisi'] ?? ''),
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
            $error = 'Aset ini berstatus '.$aset['status'].', buku maintenance tidak bisa diisi.';
        }
    }

    if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Kolom umpan: diisi bot, disembunyikan dari orang. Diamkan saja
        // seolah berhasil supaya pengirim otomatis tidak mencoba ulang.
        if (trim($_POST['alamat_surel'] ?? '') !== '') {
            $sukses = 'Catatan diterima.';
        } else {
            $tanggalSah = \DateTime::createFromFormat('Y-m-d', $isian['tanggal']);
            if (!$tanggalSah || $tanggalSah->format('Y-m-d') !== $isian['tanggal']) {
                $kesalahanForm['tanggal'] = 'Tanggal tidak sah.';
            } elseif ($isian['tanggal'] > date('Y-m-d')) {
                $kesalahanForm['tanggal'] = 'Tanggal tidak boleh melewati hari ini.';
            } elseif ($isian['tanggal'] < '2000-01-01') {
                $kesalahanForm['tanggal'] = 'Tanggal terlalu lampau.';
            }
            if (mb_strlen($isian['keterangan']) < 3 || mb_strlen($isian['keterangan']) > 2000) {
                $kesalahanForm['keterangan'] = 'Tulis keterangan pekerjaan, 3 sampai 2000 karakter.';
            }
            if (mb_strlen($isian['teknisi']) < 2 || mb_strlen($isian['teknisi']) > 40) {
                $kesalahanForm['teknisi'] = 'Tulis nama teknisi, 2 sampai 40 karakter.';
            }

            // Cegah catatan dobel karena tombol tertekan dua kali atau QR discan ulang.
            if (!$kesalahanForm) {
                $cek = $pdo->prepare("SELECT kode_pemeliharaan FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
                    WHERE kode_aset = ? AND tindakan_perbaikan = ? AND tgl_input >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1");
                $cek->execute([$aset['kode_aset'], $isian['keterangan']]);
                $kembar = $cek->fetchColumn();
                if ($kembar) {
                    $sukses = 'Catatan serupa baru saja masuk dengan nomor '.$kembar.'. Tidak perlu mengisi ulang.';
                }
            }

            if (!$kesalahanForm && !$sukses) {
                // Kolom diisi sama persis seperti postSaveBukuMaintenance di modul admin.
                $simpan = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_aset_pemeliharaan
                    (kode_pemeliharaan, kode_aset, jenis_pemeliharaan, tanggal_direncanakan, tanggal_pelaksanaan,
                     nama_kegiatan, tindakan_perbaikan, nama_teknisi, frekuensi, status, user_input, tgl_input)
                    VALUES (?, ?, 'Corrective', ?, CONCAT(?, ' ', CURTIME()), 'Perbaikan Cepat / Harian', ?, ?, 'Sekali Saja', 'Selesai', ?, NOW())");

                // Nomor urut bisa bentrok kalau dua orang mengisi bersamaan;
                // ulangi dengan nomor berikutnya, bukan menolak catatannya.
                $kodeCatatan = '';
                for ($percobaan = 1; $percobaan <= 5; $percobaan++) {
                    $calon = kode_catatan_berikutnya($pdo);
                    try {
                        $simpan->execute([
                            $calon,
                            $aset['kode_aset'],
                            $isian['tanggal'],
                            $isian['tanggal'],
                            $isian['keterangan'],
                            $isian['teknisi'],
                            mb_substr('QR: '.$isian['teknisi'], 0, 50),
                        ]);
                        $kodeCatatan = $calon;
                        break;
                    } catch (PDOException $ex) {
                        if ($ex->getCode() !== '23000' || $percobaan === 5) {
                            throw $ex;
                        }
                    }
                }

                if ($kodeCatatan === '') {
                    $kesalahanForm['umum'] = 'Catatan belum tersimpan, silakan coba lagi.';
                } else {
                    $sukses = 'Catatan tercatat dengan nomor '.$kodeCatatan.'.';
                    $isian['keterangan'] = '';
                }
            }
        }
    }

    if ($aset && !$error) {
        $riwayat = $pdo->prepare("SELECT kode_pemeliharaan, tanggal_pelaksanaan, nama_kegiatan,
                   tindakan_perbaikan, nama_teknisi
            FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
            WHERE kode_aset = ? AND status = 'Selesai'
            ORDER BY tanggal_pelaksanaan DESC, id DESC LIMIT 50");
        $riwayat->execute([$aset['kode_aset']]);
        $buku = $riwayat->fetchAll();
    }
} catch (Throwable $e) {
    $error = $error ?: 'Koneksi data aset belum tersedia.';
}

$halamanInfo = 'aset-info.php?kode='.rawurlencode($kode);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buku Maintenance<?= $aset ? ' - '.e($aset['nama_aset']) : '' ?></title>
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
    form { padding:20px 24px 24px; border-top:1px solid #e7ebef; }
    .field { margin-bottom:16px; }
    .label { font-size:11px; color:#7b8794; text-transform:uppercase; font-weight:700; margin-bottom:6px; display:block; }
    input[type=text], input[type=date], textarea { width:100%; padding:11px 12px; border:1px solid #d6dde4; border-radius:6px; font-size:15px; font-family:inherit; color:#2f4050; background:#fff; }
    textarea { min-height:110px; resize:vertical; }
    input:focus, textarea:focus { outline:none; border-color:#4f8f7b; box-shadow:0 0 0 3px rgba(79,143,123,.15); }
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
    .head-buku { padding:16px 24px; border-bottom:1px solid #e7ebef; background:#fbfcfd; }
    .bungkus-tabel { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:14px; }
    th, td { padding:11px 12px; border-bottom:1px solid #eef1f4; text-align:left; vertical-align:top; }
    th { font-size:11px; color:#7b8794; text-transform:uppercase; letter-spacing:.4px; background:#fbfcfd; white-space:nowrap; }
    td.tgl { white-space:nowrap; font-weight:700; }
    tr:last-child td { border-bottom:0; }
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
        <div class="brand-sub">Buku Maintenance Inventaris Non-Medis</div>
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
          <h1>Isi Buku Maintenance</h1>
          <div class="code">Kode Inventaris: <?= e($aset['nomor_inventaris'] ?: $aset['kode_aset']) ?></div>
          <p class="target">
            <strong><?= e($aset['nama_aset']) ?></strong> &middot; <?= e($aset['nama_unit'] ?: '-') ?>
          </p>
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
            <label class="label" for="tanggal">Tanggal Pengerjaan</label>
            <input type="date" id="tanggal" name="tanggal" required
                   max="<?= e(date('Y-m-d')) ?>" value="<?= e($isian['tanggal']) ?>">
            <?php if (!empty($kesalahanForm['tanggal'])): ?>
              <div class="invalid"><?= e($kesalahanForm['tanggal']) ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="keterangan">Keterangan Perbaikan / Perawatan</label>
            <textarea id="keterangan" name="keterangan" maxlength="2000" required
                      placeholder="Contoh: Ganti busa dudukan dan jahit ulang sarung sofa"><?= e($isian['keterangan']) ?></textarea>
            <?php if (!empty($kesalahanForm['keterangan'])): ?>
              <div class="invalid"><?= e($kesalahanForm['keterangan']) ?></div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="teknisi">Nama Teknisi</label>
            <input type="text" id="teknisi" name="teknisi" maxlength="40" required
                   placeholder="Nama petugas yang mengerjakan" value="<?= e($isian['teknisi']) ?>">
            <?php if (!empty($kesalahanForm['teknisi'])): ?>
              <div class="invalid"><?= e($kesalahanForm['teknisi']) ?></div>
            <?php endif; ?>
          </div>

          <div class="actions">
            <button type="submit" class="btn btn-utama">Simpan ke Buku</button>
            <a class="btn btn-lain" href="<?= e($halamanInfo) ?>">Kembali ke Info Aset</a>
          </div>
        </form>
      </section>

      <section class="card">
        <div class="head-buku"><h2>Riwayat Buku Maintenance</h2></div>
        <?php if (!$buku): ?>
          <div class="kosong">Belum ada catatan perawatan untuk aset ini.</div>
        <?php else: ?>
          <div class="bungkus-tabel">
            <table>
              <thead>
                <tr>
                  <th style="width:46px;">No</th>
                  <th style="width:110px;">Tanggal</th>
                  <th>Keterangan Perbaikan</th>
                  <th style="width:150px;">Teknisi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($buku as $i => $baris): ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="tgl"><?= e(date('d/m/Y', strtotime($baris['tanggal_pelaksanaan']))) ?></td>
                    <td><?= e($baris['tindakan_perbaikan'] ?: $baris['nama_kegiatan']) ?></td>
                    <td><?= e($baris['nama_teknisi'] ?: '-') ?></td>
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
