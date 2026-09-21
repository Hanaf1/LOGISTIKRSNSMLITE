<?php
define('BASE_DIR', dirname(__DIR__, 3));
require_once(__DIR__.'/../../../config.php');

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
}

function tahun_perolehan(array $aset)
{
    $tahun = (int)($aset['tahun_beli'] ?? 0);
    if ($tahun >= 1900 && $tahun <= 2100) return (string)$tahun;
    $tanggal = (string)($aset['tanggal_perolehan'] ?? '');
    if (preg_match('/^(19|20)\d{2}-\d{2}-\d{2}$/', $tanggal)) return substr($tanggal, 0, 4);
    return '-';
}

$kode = trim($_GET['kode'] ?? '');
$id = max(0, (int)($_GET['id'] ?? 0));
$aset = null;
$error = '';

try {
    $pdo = new PDO('mysql:host='.DBHOST.';port='.DBPORT.';dbname='.DBNAME.';charset=utf8', DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($kode === '' && $id === 0) {
        $error = 'Kode aset tidak ditemukan pada QR.';
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, u.nama_unit, b.nama_barang,
                   COALESCE(iu.nama, u.nama_unit) nama_unit,
                   im.kode_inventaris kode_inventaris_master,
                   im.nama nama_master_inventaris,
                   im.nama_kelompok, im.nama_jenis
            FROM rsns_custom_logistik_non_medis_aset a
            LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
              ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
            LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = a.kode_unit
            LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = a.kode_item
            LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master im
              ON im.jenis_master = 'BARANG'
             AND (im.kode = a.kode_item OR im.nama = a.nama_aset OR im.nama = b.nama_barang)
            WHERE " . ($id > 0 ? 'a.id = ?' : 'a.kode_aset = ?') . "
            ORDER BY im.id IS NULL ASC, im.id ASC
            LIMIT 1
        ");
        $stmt->execute([$id > 0 ? $id : $kode]);
        $aset = $stmt->fetch();
        if (!$aset) {
            $error = 'Data aset tidak ditemukan.';
        } else {
            // Tautan turunan tetap memakai kode aset terkini dari database.
            $kode = (string)$aset['kode_aset'];
        }
    }
} catch (Throwable $e) {
    $error = 'Koneksi data aset belum tersedia.';
}

$namaAset = $aset['nama_aset'] ?? 'Informasi Aset';
$halamanMaintenance = 'aset-maintenance.php?kode='.rawurlencode($kode);
$halamanSensus = 'aset-sensus.php?kode='.rawurlencode($kode);
$halamanLapor = 'aset-lapor.php?kode='.rawurlencode($kode);

/**
 * Bila alamat helpdesk diatur di config.php, tombol Lapor & Buku Maintenance membuka
 * helpdesk dengan data barang di URL (deep link). Helpdesk mengambil detailnya dari
 * api-inventaris.php / api-lapor.php. Placeholder yang tidak dikenal dibiarkan apa adanya.
 */
function tautan_helpdesk($konstanta, array $aset)
{
    $template = defined($konstanta) ? trim((string) constant($konstanta)) : '';
    if ($template === '' || !preg_match('~^https?://~i', $template)) {
        return '';
    }
    $nilai = [
        '{kode_aset}' => $aset['kode_aset'] ?? '',
        '{nomor_inventaris}' => $aset['nomor_inventaris'] ?? '',
        '{kode_unit}' => $aset['kode_unit'] ?? '',
        '{nama_unit}' => $aset['nama_unit'] ?? '',
        '{kode_area}' => $aset['kode_area'] ?? '',
        '{nama_barang}' => $aset['nama_aset'] ?? '',
    ];
    return strtr($template, array_map('rawurlencode', array_map('strval', $nilai)));
}

if (!empty($aset)) {
    try {
        $area = $pdo->prepare("SELECT kode_area FROM rsns_custom_logistik_non_medis_inventaris_master WHERE jenis_master = 'UNIT' AND kode = ? LIMIT 1");
        $area->execute([$aset['kode_unit']]);
        $aset['kode_area'] = (string) $area->fetchColumn();
    } catch (Throwable $e) {
        $aset['kode_area'] = ''; // kolom area belum dibuat
    }
    $halamanLapor = tautan_helpdesk('LOGISTIK_NON_MEDIS_HELPDESK_URL_LAPOR', $aset) ?: $halamanLapor;
    $halamanMaintenance = tautan_helpdesk('LOGISTIK_NON_MEDIS_HELPDESK_URL_MAINTENANCE', $aset) ?: $halamanMaintenance;
}

$laporanBerjalan = [];
if ($aset && empty($error)) {
    try {
        $stmt = $pdo->prepare("SELECT kode_pemeliharaan, nama_kegiatan, status, tgl_input
            FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
            WHERE kode_aset = ? AND status IN ('Menunggu', 'Diproses')
            ORDER BY tgl_input DESC");
        $stmt->execute([$aset['kode_aset']]);
        $laporanBerjalan = $stmt->fetchAll();
    } catch (Throwable $ex) {
        $laporanBerjalan = [];
    }
}
$kodeLabelInventaris = '';
if ($aset) {
    $kodeLabelInventaris = !empty($aset['nomor_inventaris'])
        ? $aset['nomor_inventaris']
        : preg_replace('/^AST-?/i', '', $aset['kode_aset']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($namaAset) ?></title>
  <style>
    * { box-sizing: border-box; }
    body { margin:0; font-family: Arial, Helvetica, sans-serif; color:#2f4050; background:#f4f6f8; }
    .page { max-width: 920px; margin:0 auto; padding:24px 14px 34px; }
    .brand { display:flex; align-items:center; gap:12px; margin-bottom:16px; color:#44515f; }
    .brand-logo { width:46px; height:46px; object-fit:contain; display:block; flex-shrink:0; }
    .brand-name { font-size:17px; font-weight:800; color:#2f4050; line-height:1.25; }
    .brand-sub { font-size:13px; color:#6f7d8a; }
    .card { background:#fff; border:1px solid #dde3e8; border-radius:8px; overflow:hidden; box-shadow:0 8px 24px rgba(31,45,61,.08); }
    .summary { padding:24px; }
    .status { display:inline-block; padding:6px 10px; border-radius:4px; font-size:12px; font-weight:700; background:#e8f5ed; color:#2f7d46; margin-bottom:12px; }
    .status.bad { background:#fdeaea; color:#b74444; }
    h1 { margin:0 0 8px; font-size:26px; line-height:1.25; letter-spacing:0; }
    .code { font-family:"Courier New", monospace; font-weight:700; color:#556575; margin-bottom:10px; }
    .meta { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px; padding:20px 24px 24px; border-top:1px solid #e7ebef; }
    .item { border:1px solid #e7ebef; border-radius:6px; padding:12px; background:#fbfcfd; min-height:66px; }
    .label { font-size:11px; color:#7b8794; text-transform:uppercase; font-weight:700; margin-bottom:5px; }
    .value { font-size:15px; font-weight:700; color:#2f4050; overflow-wrap:anywhere; }
    .lapor-berjalan { margin:0 24px 16px; padding:13px 15px; border-radius:6px; background:#fdf4e7; border:1px solid #f0d9b5; color:#8a5a20; font-size:13px; line-height:1.55; }
    .lapor-berjalan strong { color:#6f4718; }
    .lapor-berjalan ul { margin:8px 0 0; padding-left:18px; }
    .actions { padding:0 24px 20px; display:flex; gap:10px; flex-wrap:wrap; }
    .btn { display:inline-block; padding:13px 20px; border-radius:6px; font-size:15px; font-weight:700; text-decoration:none; background:#4f8f7b; color:#fff; }
    .btn:hover { background:#437a69; }
    .btn-sekunder { background:#eef1f4; color:#44515f; }
    .btn-sekunder:hover { background:#e2e7ec; }
    .btn-lapor { background:#b9534d; }
    .btn-lapor:hover { background:#a04641; }
    .note { padding:14px 24px; border-top:1px solid #e7ebef; color:#6f7d8a; font-size:13px; background:#fbfcfd; }
    .empty { padding:34px 24px; text-align:center; }
    .empty h1 { color:#b74444; }
    @media (max-width: 700px) {
      .meta { grid-template-columns:1fr; padding:14px; gap:10px; }
      .summary { padding:18px; }
      .actions { padding:0 14px 16px; }
      .btn { display:block; text-align:center; }
      h1 { font-size:22px; }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="brand">
      <img class="brand-logo" src="rsunurussyifa-logo.png" alt="Logo RSU Nurusyifa">
      <div>
        <div class="brand-name">RSU Nurusyifa</div>
        <div class="brand-sub">Informasi Inventaris Non-Medis</div>
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
          <span class="status <?= ($aset['status'] ?? '') === 'Aktif' ? '' : 'bad' ?>"><?= e($aset['status']) ?></span>
          <h1><?= e($aset['nama_aset']) ?></h1>
          <div class="code">Kode Inventaris: <?= e($kodeLabelInventaris) ?></div>
          <p style="margin:0;color:#657381;line-height:1.55;">
            <?= e($aset['nama_master_inventaris'] ?: $aset['nama_barang'] ?: 'Belum terhubung master barang inventaris') ?>
          </p>
        </div>

        <div class="meta">
          <div class="item"><div class="label">Nomor Inventaris</div><div class="value"><?= e($aset['nomor_inventaris'] ?: '-') ?></div></div>
          <div class="item"><div class="label">Unit</div><div class="value"><?= e($aset['nama_unit'] ?: '-') ?></div></div>
          <div class="item"><div class="label">Kelompok / Jenis</div><div class="value"><?= e(trim(($aset['nama_kelompok'] ?: 'Belum terhubung').' / '.($aset['nama_jenis'] ?: '-'), ' /')) ?></div></div>
          <div class="item"><div class="label">Kondisi</div><div class="value"><?= e($aset['status_kondisi']) ?></div></div>
          <div class="item"><div class="label">Tahun Beli / Perolehan</div><div class="value"><?= e(tahun_perolehan($aset)) ?></div></div>
          <div class="item"><div class="label">Nilai Perolehan</div><div class="value"><?= e(rupiah($aset['harga_beli'])) ?></div></div>
          <div class="item"><div class="label">PIC</div><div class="value"><?= e($aset['pic'] ?: '-') ?></div></div>
          <div class="item"><div class="label">Serial Number</div><div class="value"><?= e($aset['serial_number'] ?: '-') ?></div></div>
        </div>
        <?php if ($laporanBerjalan): ?>
          <div class="lapor-berjalan">
            <strong>Sedang ada laporan kerusakan yang belum selesai untuk aset ini.</strong>
            <ul>
              <?php foreach ($laporanBerjalan as $satu): ?>
                <li>
                  <?= e($satu['nama_kegiatan']) ?> &middot; <?= e($satu['kode_pemeliharaan']) ?>
                  &middot; <?= e(date('d/m/Y', strtotime($satu['tgl_input']))) ?>
                  &middot; status <?= e($satu['status']) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="actions">
          <a class="btn btn-lapor" href="<?= e($halamanLapor) ?>">Lapor Kerusakan</a>
          <a class="btn" href="<?= e($halamanMaintenance) ?>">Isi Buku Maintenance</a>
          <a class="btn btn-sekunder" href="<?= e($halamanSensus) ?>">Pencatatan &amp; Riwayat Sensus</a>
        </div>
        <div class="note">Halaman ini dibuat otomatis dari QR inventaris. Jika data tidak sesuai, perbarui melalui modul Logistik Non-Medis.</div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
