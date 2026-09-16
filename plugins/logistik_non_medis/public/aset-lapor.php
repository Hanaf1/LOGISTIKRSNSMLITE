<?php
/**
 * Form lapor kerusakan dari QR aset, tanpa login.
 *
 * Berbeda dengan aset-maintenance.php yang mencatat pekerjaan SUDAH dilakukan,
 * berkas ini mencatat kerusakan yang BELUM dikerjakan. Hasilnya dua tempat:
 *
 *   1. Tabel pemeliharaan, berstatus 'Menunggu' -> langsung muncul di menu
 *      Pemeliharaan Aset sebagai pekerjaan yang perlu ditindaklanjuti.
 *   2. Tabel antrean helpdesk, berstatus 'Antri' -> menunggu dikirim ke sistem
 *      helpdesk oleh pengirim yang dibuat menyusul.
 *
 * Halaman ini sengaja TIDAK memanggil helpdesk saat tombol Simpan ditekan.
 * Pelapor mendapat konfirmasi seketika; kalau helpdesk mati, laporan tetap
 * tersimpan dan antreannya menunggu.
 */

define('BASE_DIR', dirname(__DIR__, 3));
require_once(__DIR__.'/../../../config.php');

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

const PRIORITAS_SAH = ['Rendah', 'Sedang', 'Tinggi', 'Darurat'];

/**
 * Nomor laporan dari QR diberi awalan sendiri supaya di menu Pemeliharaan Aset
 * kelihatan mana yang masuk dari scan ruangan dan mana yang dibuat petugas
 * logistik (WO-, PMJ-, LOG-). Nomor ini juga dipakai sebagai kunci idempoten
 * saat dikirim ke helpdesk, sehingga kiriman ulang tidak membuat tiket kedua.
 */
function nomor_laporan_berikutnya(PDO $pdo)
{
    $prefix = 'QR-'.date('Ym').'-';
    $stmt = $pdo->prepare("SELECT kode_pemeliharaan FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
        WHERE kode_pemeliharaan LIKE ? ORDER BY kode_pemeliharaan DESC LIMIT 1");
    $stmt->execute([$prefix.'%']);
    $terakhir = $stmt->fetchColumn();
    $urut = $terakhir ? (int) substr($terakhir, -4) : 0;
    return $prefix.str_pad($urut + 1, 4, '0', STR_PAD_LEFT);
}

$kode = trim($_GET['kode'] ?? $_POST['kode'] ?? '');
$aset = null;
$berjalan = [];   // laporan yang belum selesai, supaya tidak dilapor berulang
$riwayat = [];
$error = '';
$kesalahanForm = [];
$sukses = '';
$isian = [
    'keluhan' => trim($_POST['keluhan'] ?? ''),
    'deskripsi' => trim($_POST['deskripsi'] ?? ''),
    'prioritas' => $_POST['prioritas'] ?? 'Sedang',
    'pelapor' => trim($_POST['pelapor'] ?? ''),
];

try {
    $pdo = new PDO('mysql:host='.DBHOST.';port='.DBPORT.';dbname='.DBNAME.';charset=utf8mb4', DBUSER, DBPASS, [
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
                   a.kode_unit, a.lokasi_fisik,
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
            $error = 'Aset ini berstatus '.$aset['status'].', laporan tidak bisa diajukan.';
        }
    }

    if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Kolom umpan: diisi bot, disembunyikan dari orang.
        if (trim($_POST['alamat_surel'] ?? '') !== '') {
            $sukses = 'Laporan diterima.';
        } else {
            if (mb_strlen($isian['keluhan']) < 3 || mb_strlen($isian['keluhan']) > 200) {
                $kesalahanForm['keluhan'] = 'Isi keluhan singkat, 3 sampai 200 karakter.';
            }
            if (mb_strlen($isian['deskripsi']) > 2000) {
                $kesalahanForm['deskripsi'] = 'Keterangan maksimal 2000 karakter.';
            }
            if (!in_array($isian['prioritas'], PRIORITAS_SAH, true)) {
                $kesalahanForm['prioritas'] = 'Pilih tingkat prioritas yang tersedia.';
            }
            if (mb_strlen($isian['pelapor']) < 2 || mb_strlen($isian['pelapor']) > 40) {
                $kesalahanForm['pelapor'] = 'Tulis nama pelapor, 2 sampai 40 karakter.';
            }

            // Cegah laporan dobel karena tombol tertekan dua kali atau QR discan ulang.
            if (!$kesalahanForm) {
                $cek = $pdo->prepare("SELECT kode_pemeliharaan FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
                    WHERE kode_aset = ? AND nama_kegiatan = ? AND tgl_input >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1");
                $cek->execute([$aset['kode_aset'], $isian['keluhan']]);
                $kembar = $cek->fetchColumn();
                if ($kembar) {
                    $sukses = 'Laporan serupa baru saja masuk dengan nomor '.$kembar.'. Tidak perlu mengirim ulang.';
                }
            }

            if (!$kesalahanForm && !$sukses) {
                $catatan = $isian['deskripsi'] !== '' ? $isian['deskripsi'] : 'Tidak ada keterangan tambahan.';
                $catatan .= "\n\nDilaporkan dari QR aset oleh: ".$isian['pelapor'];
                $catatan .= "\nUnit: ".($aset['nama_unit'] ?: '-');
                if (!empty($aset['lokasi_fisik'])) {
                    $catatan .= "\nLokasi: ".$aset['lokasi_fisik'];
                }

                $simpan = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_aset_pemeliharaan
                    (kode_pemeliharaan, kode_aset, jenis_pemeliharaan, tanggal_direncanakan, nama_kegiatan,
                     deskripsi, frekuensi, prioritas, status, user_input, tgl_input)
                    VALUES (?, ?, 'Corrective', CURDATE(), ?, ?, 'Sekali Saja', ?, 'Menunggu', ?, NOW())");

                $antre = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_helpdesk_outbox
                    (kunci_idempoten, jenis, kode_aset, ref_tabel, ref_id, muatan, status, tgl_dibuat)
                    VALUES (?, 'Kerusakan', ?, 'rsns_custom_logistik_non_medis_aset_pemeliharaan', ?, ?, 'Antri', NOW())");

                $basis = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    .'://'.($_SERVER['HTTP_HOST'] ?? 'localhost')
                    .rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

                // Nomor urut bisa bentrok kalau dua ruangan melapor bersamaan;
                // ulangi dengan nomor berikutnya, bukan menolak laporannya.
                $nomor = '';
                for ($percobaan = 1; $percobaan <= 5; $percobaan++) {
                    $calon = nomor_laporan_berikutnya($pdo);
                    try {
                        $pdo->beginTransaction();
                        $simpan->execute([
                            $calon,
                            $aset['kode_aset'],
                            $isian['keluhan'],
                            $catatan,
                            $isian['prioritas'],
                            mb_substr('QR: '.$isian['pelapor'], 0, 50),
                        ]);
                        $refId = (int)$pdo->lastInsertId();

                        // Isi laporan disimpan utuh supaya kiriman ulang ke helpdesk
                        // memuat data yang sama walau baris asalnya berubah kemudian.
                        $muatan = [
                            'kunci' => $calon,
                            'jenis' => 'Kerusakan',
                            'waktu_lapor' => $pdo->query('SELECT NOW()')->fetchColumn(),
                            'pelapor' => $isian['pelapor'],
                            'judul' => $isian['keluhan'],
                            'keterangan' => $isian['deskripsi'],
                            'prioritas' => $isian['prioritas'],
                            'aset' => [
                                'kode_aset' => $aset['kode_aset'],
                                'nomor_inventaris' => $aset['nomor_inventaris'],
                                'nama_aset' => $aset['nama_aset'],
                                'kode_unit' => $aset['kode_unit'],
                                'nama_unit' => $aset['nama_unit'],
                                'lokasi_fisik' => $aset['lokasi_fisik'],
                                'kondisi_tercatat' => $aset['status_kondisi'],
                                'tautan_qr' => $basis.'/aset-info.php?kode='.rawurlencode($aset['kode_aset']),
                            ],
                        ];
                        $antre->execute([
                            $calon,
                            $aset['kode_aset'],
                            $refId,
                            json_encode($muatan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);

                        $pdo->commit();
                        $nomor = $calon;
                        break;
                    } catch (PDOException $ex) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if ($ex->getCode() !== '23000' || $percobaan === 5) {
                            throw $ex;
                        }
                    }
                }

                if ($nomor === '') {
                    $kesalahanForm['umum'] = 'Laporan belum tersimpan, silakan coba lagi.';
                } else {
                    $sukses = 'Laporan tercatat dengan nomor '.$nomor.'.';
                }
            }
        }
    }
    if ($aset && !$error) {
        // 'Menunggu' dan 'Diproses' = masih ditangani. Inilah yang membuat
        // orang tahu kerusakannya sudah dilaporkan, tidak perlu lapor lagi.
        $stmt = $pdo->prepare("SELECT kode_pemeliharaan, nama_kegiatan, prioritas, status,
                   user_input, tgl_input, nama_teknisi
            FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
            WHERE kode_aset = ? AND status IN ('Menunggu', 'Diproses')
            ORDER BY tgl_input DESC");
        $stmt->execute([$aset['kode_aset']]);
        $berjalan = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT kode_pemeliharaan, nama_kegiatan, prioritas, status,
                   user_input, tgl_input, tanggal_pelaksanaan, tindakan_perbaikan, nama_teknisi
            FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
            WHERE kode_aset = ? ORDER BY tgl_input DESC LIMIT 10");
        $stmt->execute([$aset['kode_aset']]);
        $riwayat = $stmt->fetchAll();
    }
} catch (Throwable $ex) {
    $error = $error ?: 'Koneksi data aset belum tersedia.';
}

function warnaStatus($status)
{
    if ($status === 'Selesai') return 'ok';
    if ($status === 'Dibatalkan') return 'netral';
    if ($status === 'Diproses') return 'proses';
    return 'warn';
}

$halamanInfo = 'aset-info.php?kode='.rawurlencode($kode);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lapor Kerusakan<?= $aset ? ' - '.e($aset['nama_aset']) : '' ?></title>
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
    .code { font-family:"Courier New", monospace; font-weight:700; color:#556575; margin-bottom:10px; }
    .target { color:#657381; line-height:1.55; margin:0; }
    form { padding:20px 24px 24px; border-top:1px solid #e7ebef; }
    .field { margin-bottom:16px; }
    .label { font-size:11px; color:#7b8794; text-transform:uppercase; font-weight:700; margin-bottom:6px; display:block; }
    input[type=text], textarea, select { width:100%; padding:11px 12px; border:1px solid #d6dde4; border-radius:6px; font-size:15px; font-family:inherit; color:#2f4050; background:#fff; }
    textarea { min-height:110px; resize:vertical; }
    input:focus, textarea:focus, select:focus { outline:none; border-color:#b9534d; box-shadow:0 0 0 3px rgba(185,83,77,.15); }
    .hint { font-size:12px; color:#8a96a3; margin-top:5px; }
    .invalid { color:#b74444; font-size:12px; margin-top:5px; font-weight:700; }
    .umpan { position:absolute; left:-9999px; }
    .actions { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .btn { display:inline-block; padding:13px 20px; border-radius:6px; font-size:15px; font-weight:700; border:0; cursor:pointer; text-decoration:none; }
    .btn-utama { background:#b9534d; color:#fff; }
    .btn-utama:hover { background:#a04641; }
    .btn-lain { background:#eef1f4; color:#44515f; }
    .btn-lain:hover { background:#e2e7ec; }
    .kabar { padding:16px 24px; border-top:1px solid #e7ebef; line-height:1.6; }
    .kabar.ok { background:#e8f5ed; color:#2f7d46; font-weight:700; }
    .kabar.bad { background:#fdeaea; color:#b74444; font-weight:700; }
    .note { padding:14px 24px; border-top:1px solid #e7ebef; color:#6f7d8a; font-size:13px; background:#fbfcfd; }
    .peringatan { margin:0 24px 20px; padding:14px 16px; border-radius:6px; background:#fdf4e7; border:1px solid #f0d9b5; color:#8a5a20; line-height:1.55; }
    .peringatan strong { color:#6f4718; }
    .berjalan { margin-top:10px; padding-left:18px; }
    .berjalan li { margin-bottom:6px; }
    .head-riwayat { padding:16px 24px; border-bottom:1px solid #e7ebef; background:#fbfcfd; }
    .head-riwayat h2 { margin:0; font-size:15px; font-weight:700; }
    .bungkus-tabel { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:14px; }
    th, td { padding:11px 12px; border-bottom:1px solid #eef1f4; text-align:left; vertical-align:top; }
    th { font-size:11px; color:#7b8794; text-transform:uppercase; letter-spacing:.4px; background:#fbfcfd; white-space:nowrap; }
    td.tgl { white-space:nowrap; }
    tr:last-child td { border-bottom:0; }
    .tanda { display:inline-block; padding:3px 9px; border-radius:4px; font-size:12px; font-weight:700; white-space:nowrap; }
    .tanda.ok { background:#e8f5ed; color:#2f7d46; }
    .tanda.warn { background:#fdf4e7; color:#96632a; }
    .tanda.proses { background:#eef4ff; color:#2f4f8a; }
    .tanda.netral { background:#eef1f4; color:#6f7d8a; }
    .kosong { padding:26px 24px; text-align:center; color:#8a96a3; }
    .empty { padding:34px 24px; text-align:center; }
    .empty h1 { color:#b74444; }
    @media (max-width: 700px) {
      .summary { padding:18px; }
      form { padding:16px 18px 20px; }
      h1 { font-size:21px; }
      .btn { width:100%; text-align:center; }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="brand">
      <img class="brand-logo" src="rsunurussyifa-logo.png" alt="Logo RSU Nurusyifa">
      <div>
        <div class="brand-name">RSU Nurusyifa</div>
        <div class="brand-sub">Lapor Kerusakan Inventaris Non-Medis</div>
      </div>
    </div>

    <section class="card">
      <?php if ($error): ?>
        <div class="empty">
          <h1><?= e($error) ?></h1>
          <p>Pastikan QR berasal dari label aset yang dicetak melalui sistem.</p>
        </div>
      <?php else: ?>
        <div class="summary">
          <h1>Lapor Kerusakan</h1>
          <div class="code">Kode Inventaris: <?= e($aset['nomor_inventaris'] ?: $aset['kode_aset']) ?></div>
          <p class="target">
            <strong><?= e($aset['nama_aset']) ?></strong> &middot; <?= e($aset['nama_unit'] ?: '-') ?>
            &middot; Kondisi tercatat: <?= e($aset['status_kondisi']) ?>
          </p>
        </div>

        <?php if ($sukses): ?>
          <div class="kabar ok"><?= e($sukses) ?></div>
          <div class="note">
            Laporan masuk ke menu Pemeliharaan Aset dengan status <strong>Menunggu</strong>
            dan sudah diantrekan untuk dikirim ke helpdesk.
            Petugas logistik yang akan menjadwalkan tindak lanjutnya.
          </div>
          <form method="get" action="">
            <input type="hidden" name="kode" value="<?= e($kode) ?>">
            <div class="actions">
              <button type="submit" class="btn btn-utama">Lapor Kerusakan Lain</button>
              <a class="btn btn-lain" href="<?= e($halamanInfo) ?>">Kembali ke Info Aset</a>
            </div>
          </form>
        <?php else: ?>
          <?php if (!empty($kesalahanForm['umum'])): ?>
            <div class="kabar bad"><?= e($kesalahanForm['umum']) ?></div>
          <?php endif; ?>
          <?php if ($berjalan): ?>
            <div class="peringatan">
              <strong>Kerusakan aset ini sudah dilaporkan dan belum selesai.</strong>
              Tidak perlu melapor ulang untuk keluhan yang sama.
              <ul class="berjalan">
                <?php foreach ($berjalan as $satu): ?>
                  <li>
                    <strong><?= e($satu['nama_kegiatan']) ?></strong> &middot;
                    <?= e($satu['kode_pemeliharaan']) ?> &middot;
                    <?= e(date('d/m/Y', strtotime($satu['tgl_input']))) ?> &middot;
                    status <?= e($satu['status']) ?>
                    <?= $satu['nama_teknisi'] ? ' &middot; teknisi '.e($satu['nama_teknisi']) : '' ?>
                  </li>
                <?php endforeach; ?>
              </ul>
              Lanjutkan mengisi hanya bila kerusakannya <em>berbeda</em> dari yang di atas.
            </div>
          <?php endif; ?>

          <form method="post" action="">
            <input type="hidden" name="kode" value="<?= e($kode) ?>">
            <div class="umpan" aria-hidden="true">
              <label>Alamat surel <input type="text" name="alamat_surel" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="field">
              <label class="label" for="keluhan">Keluhan / Kerusakan</label>
              <input type="text" id="keluhan" name="keluhan" maxlength="200" required
                     placeholder="Contoh: Dudukan sofa robek dan busa keluar"
                     value="<?= e($isian['keluhan']) ?>">
              <?php if (!empty($kesalahanForm['keluhan'])): ?>
                <div class="invalid"><?= e($kesalahanForm['keluhan']) ?></div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label class="label" for="prioritas">Tingkat Prioritas</label>
              <select id="prioritas" name="prioritas">
                <?php foreach (PRIORITAS_SAH as $pilihan): ?>
                  <option value="<?= e($pilihan) ?>" <?= $isian['prioritas'] === $pilihan ? 'selected' : '' ?>><?= e($pilihan) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="hint">Pilih Darurat hanya bila mengganggu pelayanan pasien saat ini.</div>
              <?php if (!empty($kesalahanForm['prioritas'])): ?>
                <div class="invalid"><?= e($kesalahanForm['prioritas']) ?></div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label class="label" for="deskripsi">Keterangan Tambahan</label>
              <textarea id="deskripsi" name="deskripsi" maxlength="2000"
                        placeholder="Sejak kapan rusak, bagian mana, sudah dicoba apa saja"><?= e($isian['deskripsi']) ?></textarea>
              <?php if (!empty($kesalahanForm['deskripsi'])): ?>
                <div class="invalid"><?= e($kesalahanForm['deskripsi']) ?></div>
              <?php endif; ?>
            </div>

            <div class="field">
              <label class="label" for="pelapor">Nama Pelapor</label>
              <input type="text" id="pelapor" name="pelapor" maxlength="40" required
                     placeholder="Nama dan ruangan Anda" value="<?= e($isian['pelapor']) ?>">
              <?php if (!empty($kesalahanForm['pelapor'])): ?>
                <div class="invalid"><?= e($kesalahanForm['pelapor']) ?></div>
              <?php endif; ?>
            </div>

            <div class="actions">
              <button type="submit" class="btn btn-utama">Kirim Laporan</button>
              <a class="btn btn-lain" href="<?= e($halamanInfo) ?>">Batal</a>
            </div>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if (!$error): ?>
      <section class="card">
        <div class="head-riwayat"><h2>Riwayat Laporan &amp; Penanganan Aset Ini</h2></div>
        <?php if (!$riwayat): ?>
          <div class="kosong">Belum ada laporan maupun penanganan untuk aset ini.</div>
        <?php else: ?>
          <div class="bungkus-tabel">
            <table>
              <thead>
                <tr>
                  <th style="width:120px;">Nomor</th>
                  <th style="width:105px;">Tanggal</th>
                  <th>Keluhan / Pekerjaan</th>
                  <th style="width:110px;">Status</th>
                  <th style="width:140px;">Pelapor / Teknisi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($riwayat as $satu): ?>
                  <tr>
                    <td style="font-family:'Courier New',monospace;"><?= e($satu['kode_pemeliharaan']) ?></td>
                    <td class="tgl"><?= e(date('d/m/Y', strtotime($satu['tgl_input']))) ?></td>
                    <td>
                      <?= e($satu['nama_kegiatan']) ?>
                      <?php if (!empty($satu['tindakan_perbaikan'])): ?>
                        <br><span style="color:#8a96a3; font-size:13px;"><?= e($satu['tindakan_perbaikan']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><span class="tanda <?= e(warnaStatus($satu['status'])) ?>"><?= e($satu['status']) ?></span></td>
                    <td><?= e($satu['nama_teknisi'] ?: $satu['user_input'] ?: '-') ?></td>
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
