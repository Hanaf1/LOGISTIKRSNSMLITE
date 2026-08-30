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
$aset = null;
$error = '';

try {
    $pdo = new PDO('mysql:host='.DBHOST.';port='.DBPORT.';dbname='.DBNAME.';charset=utf8', DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($kode === '') {
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
            WHERE a.kode_aset = ?
            ORDER BY im.id IS NULL ASC, im.id ASC
            LIMIT 1
        ");
        $stmt->execute([$kode]);
        $aset = $stmt->fetch();
        if (!$aset) {
            $error = 'Data aset tidak ditemukan.';
        }
    }
} catch (Throwable $e) {
    $error = 'Koneksi data aset belum tersedia.';
}

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$rootBase = preg_replace('#/plugins/logistik_non_medis/public$#', '', $basePath);
$uploadBase = $rootBase.'/uploads/logistik_non_medis/aset/';
$namaAset = $aset['nama_aset'] ?? 'Informasi Aset';
$kodeLabelInventaris = '';
if ($aset) {
    $kodeLabelInventaris = !empty($aset['nomor_inventaris'])
        ? $aset['nomor_inventaris']
        : preg_replace('/^AST-?/i', '', $aset['kode_aset']);
}
$foto = '';
if (!empty($aset['foto_depan'])) {
    $foto = $uploadBase.rawurlencode($aset['foto_depan']);
} elseif (!empty($aset['foto_detail'])) {
    $foto = $uploadBase.rawurlencode($aset['foto_detail']);
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
    .brand { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:#44515f; font-weight:700; }
    .brand-mark { width:34px; height:34px; border-radius:6px; background:#4f8f7b; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; }
    .card { background:#fff; border:1px solid #dde3e8; border-radius:8px; overflow:hidden; box-shadow:0 8px 24px rgba(31,45,61,.08); }
    .hero { display:grid; grid-template-columns: 260px 1fr; gap:0; min-height:220px; }
    .photo { background:#e8ecef; display:flex; align-items:center; justify-content:center; color:#8a96a3; min-height:220px; }
    .photo img { width:100%; height:100%; object-fit:cover; display:block; }
    .summary { padding:24px; }
    .status { display:inline-block; padding:6px 10px; border-radius:4px; font-size:12px; font-weight:700; background:#e8f5ed; color:#2f7d46; margin-bottom:12px; }
    .status.bad { background:#fdeaea; color:#b74444; }
    h1 { margin:0 0 8px; font-size:26px; line-height:1.25; letter-spacing:0; }
    .code { font-family:"Courier New", monospace; font-weight:700; color:#556575; margin-bottom:10px; }
    .meta { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px; padding:20px 24px 24px; border-top:1px solid #e7ebef; }
    .item { border:1px solid #e7ebef; border-radius:6px; padding:12px; background:#fbfcfd; min-height:66px; }
    .label { font-size:11px; color:#7b8794; text-transform:uppercase; font-weight:700; margin-bottom:5px; }
    .value { font-size:15px; font-weight:700; color:#2f4050; overflow-wrap:anywhere; }
    .note { padding:14px 24px; border-top:1px solid #e7ebef; color:#6f7d8a; font-size:13px; background:#fbfcfd; }
    .empty { padding:34px 24px; text-align:center; }
    .empty h1 { color:#b74444; }
    @media (max-width: 700px) {
      .hero { grid-template-columns:1fr; }
      .photo { min-height:190px; }
      .meta { grid-template-columns:1fr; padding:14px; gap:10px; }
      .summary { padding:18px; }
      h1 { font-size:22px; }
    }
  </style>
</head>
<body>
  <main class="page">
    <div class="brand">
      <div class="brand-mark">RS</div>
      <div>Informasi Inventaris Non-Medis</div>
    </div>

    <section class="card">
      <?php if ($error): ?>
        <div class="empty">
          <h1><?= e($error) ?></h1>
          <p>Pastikan QR berasal dari label aset yang dicetak melalui sistem.</p>
        </div>
      <?php else: ?>
        <div class="hero">
          <div class="photo">
            <?php if ($foto): ?>
              <img src="<?= e($foto) ?>" alt="<?= e($aset['nama_aset']) ?>">
            <?php else: ?>
              <div>Foto aset belum tersedia</div>
            <?php endif; ?>
          </div>
          <div class="summary">
            <span class="status <?= ($aset['status'] ?? '') === 'Aktif' ? '' : 'bad' ?>"><?= e($aset['status']) ?></span>
            <h1><?= e($aset['nama_aset']) ?></h1>
            <div class="code">Kode Inventaris: <?= e($kodeLabelInventaris) ?></div>
            <p style="margin:0;color:#657381;line-height:1.55;">
              <?= e($aset['nama_master_inventaris'] ?: $aset['nama_barang'] ?: 'Belum terhubung master barang inventaris') ?>
            </p>
          </div>
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
        <div class="note">Halaman ini dibuat otomatis dari QR inventaris. Jika data tidak sesuai, perbarui melalui modul Logistik Non-Medis.</div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
