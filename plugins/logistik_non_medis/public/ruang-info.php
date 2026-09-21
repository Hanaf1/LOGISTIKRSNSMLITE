<?php
define('BASE_DIR', dirname(__DIR__, 3));
require_once __DIR__.'/../../../config.php';

function ruang_e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$id = max(0, (int)($_GET['id'] ?? 0));
$kodeLama = trim((string)($_GET['kode'] ?? ''));
$aksesDitolak = isset($_GET['akses']) && $_GET['akses'] === 'ditolak';
$ruang = null;
$items = [];
$error = '';

try {
    $pdo = new PDO('mysql:host='.DBHOST.';port='.DBPORT.';dbname='.DBNAME.';charset=utf8mb4', DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($id < 1 && $kodeLama === '') {
        $error = 'Identitas ruangan tidak ditemukan pada QR.';
    } else {
        $stmt = $pdo->prepare("SELECT m.id, m.kode, m.nama, COALESCE(m.kode_area,'') kode_area,
                COALESCE(u.nama_unit,'') nama_area
            FROM rsns_custom_logistik_non_medis_inventaris_master m
            LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = m.kode_area
            WHERE m.jenis_master='UNIT' AND m.status='Aktif' AND " . ($id > 0 ? 'm.id=?' : 'm.kode=?') . " LIMIT 1");
        $stmt->execute([$id > 0 ? $id : $kodeLama]);
        $ruang = $stmt->fetch();
        if (!$ruang) {
            $error = 'Ruangan tidak ditemukan atau sudah tidak aktif.';
        } else {
            $id = (int)$ruang['id'];
            $stmtItems = $pdo->prepare("SELECT a.nomor_inventaris, a.kode_aset, a.nama_aset,
                    COALESCE(a.merk_type,'') merk_type, COALESCE(a.lokasi_fisik,'') lokasi_fisik,
                    COALESCE(a.status_kondisi,'Belum dicatat') status_kondisi
                FROM rsns_custom_logistik_non_medis_aset a
                WHERE a.kode_unit=? AND a.status='Aktif'
                ORDER BY a.nama_aset, a.nomor_inventaris, a.id");
            $stmtItems->execute([$ruang['kode']]);
            $items = $stmtItems->fetchAll();
        }
    }
} catch (Throwable $e) {
    $error = 'Informasi inventaris ruangan belum dapat dimuat.';
}

$jumlahBaik = 0;
$jumlahPerluPerhatian = 0;
foreach ($items as &$item) {
    $item['kode_label'] = $item['nomor_inventaris'] !== ''
        ? $item['nomor_inventaris'] : preg_replace('/^AST-?/i', '', $item['kode_aset']);
    if (strcasecmp(trim($item['status_kondisi']), 'Baik') === 0) $jumlahBaik++;
    else $jumlahPerluPerhatian++;
}
unset($item);
$checkUrl = 'ruang-check.php?id='.$id;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= ruang_e($ruang ? 'Inventaris '.$ruang['nama'] : 'Inventaris Ruangan') ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f3;color:#203c34;font-family:Arial,Helvetica,sans-serif}.page{max-width:1040px;margin:auto;padding:22px 14px 40px}.brand{display:flex;align-items:center;gap:12px;margin-bottom:17px}.brand img{width:54px;height:54px;object-fit:contain}.brand strong{display:block;font-size:19px;color:#172b25}.brand span{font-size:13px;color:#678078;letter-spacing:.5px;text-transform:uppercase}.card{overflow:hidden;background:#fff;border:1px solid #d9e4e0;border-radius:12px;box-shadow:0 8px 28px rgba(24,58,48,.08)}.accent{height:8px;background:linear-gradient(90deg,#23735f,#94bf50)}.head{padding:24px 26px;border-bottom:1px solid #e2eae7}.eyebrow{color:#287a65;font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase}.head h1{margin:6px 0 4px;font-size:30px;color:#16362d}.area{color:#667d76}.stats{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}.stat{padding:7px 11px;border-radius:20px;background:#edf7f3;color:#276c5a;font-size:12px;font-weight:700}.stat.warn{background:#fff3dd;color:#946019}.alert{margin:18px 26px 0;padding:14px 16px;border-radius:7px;background:#fdebea;border:1px solid #efc8c5;color:#9d3f39;font-weight:700}.actions{padding:20px 26px;display:flex;align-items:center;justify-content:space-between;gap:14px;background:#fbfcfc}.actions p{margin:0;color:#60756f;font-size:13px;line-height:1.45}.btn{display:inline-block;flex:0 0 auto;padding:12px 18px;border-radius:6px;background:#287a65;color:#fff;text-decoration:none;font-weight:800}.btn:hover{background:#216653}.table-wrap{overflow-x:auto}.inventory{width:100%;border-collapse:collapse}.inventory th{padding:11px 13px;background:#274b41;color:#fff;font-size:12px;text-align:left;white-space:nowrap}.inventory td{padding:12px 13px;border-bottom:1px solid #e5ece9;font-size:13px;vertical-align:top}.inventory tr:nth-child(even) td{background:#fafcfb}.code{font-family:"Courier New",monospace;font-weight:700;color:#25705d;white-space:nowrap}.condition{display:inline-block;padding:4px 8px;border-radius:12px;background:#e8f5ed;color:#2f7d46;font-size:11px;font-weight:800}.condition.attn{background:#fff0df;color:#a46019}.empty{padding:45px 25px;text-align:center;color:#6d817a}.error{padding:45px 25px;text-align:center}.error h1{color:#a94442;font-size:23px}.foot{padding:13px 20px;background:#f6f9f8;color:#71847e;font-size:11px;text-align:center}@media(max-width:700px){.page{padding:12px 8px 28px}.head{padding:19px 16px}.head h1{font-size:25px}.actions{padding:16px;display:block}.actions .btn{display:block;margin-top:13px;text-align:center}.inventory th,.inventory td{padding:10px 9px}.hide-mobile{display:none}}
</style>
</head>
<body>
<main class="page">
  <header class="brand"><img src="rsunurussyifa-logo.png" alt="Logo RSU Nurusyifa"><div><strong>RSU NURUSYIFA</strong><span>Informasi Inventaris Non Medis</span></div></header>
  <section class="card">
    <div class="accent"></div>
    <?php if ($error): ?>
      <div class="error"><h1><?= ruang_e($error) ?></h1><p>Pastikan QR berasal dari stiker ruangan yang dibuat melalui sistem.</p></div>
    <?php else: ?>
      <div class="head">
        <div class="eyebrow">Inventaris Ruangan</div>
        <h1><?= ruang_e($ruang['nama']) ?></h1>
        <?php if ($ruang['nama_area'] !== ''): ?><div class="area">Area <?= ruang_e($ruang['nama_area']) ?></div><?php endif; ?>
        <div class="stats">
          <span class="stat"><?= count($items) ?> item tercatat</span>
          <span class="stat"><?= $jumlahBaik ?> kondisi baik</span>
          <?php if ($jumlahPerluPerhatian): ?><span class="stat warn"><?= $jumlahPerluPerhatian ?> perlu perhatian</span><?php endif; ?>
        </div>
      </div>
      <?php if ($aksesDitolak): ?><div class="alert">Akun Anda tidak memiliki akses untuk melakukan check inventaris ruangan. Gunakan akun Admin, Logistik, atau petugas Aset yang telah diberi izin.</div><?php endif; ?>
      <div class="actions"><p>Daftar ini dapat dilihat tanpa login. Perubahan hasil pengecekan hanya dapat dilakukan petugas berwenang dan akan tercatat sebagai riwayat pemeriksaan.</p><a class="btn" href="<?= ruang_e($checkUrl) ?>">Check Inventaris</a></div>
      <?php if ($items): ?>
      <div class="table-wrap"><table class="inventory"><thead><tr><th>No.</th><th>Kode Inventaris</th><th>Barang</th><th class="hide-mobile">Merk / Tipe</th><th class="hide-mobile">Lokasi</th><th>Kondisi</th></tr></thead><tbody>
      <?php foreach ($items as $nomor => $item): ?><tr>
        <td><?= $nomor + 1 ?></td><td class="code"><?= ruang_e($item['kode_label']) ?></td><td><strong><?= ruang_e($item['nama_aset']) ?></strong></td>
        <td class="hide-mobile"><?= ruang_e($item['merk_type'] ?: '-') ?></td><td class="hide-mobile"><?= ruang_e($item['lokasi_fisik'] ?: '-') ?></td>
        <td><span class="condition <?= strcasecmp(trim($item['status_kondisi']), 'Baik') === 0 ? '' : 'attn' ?>"><?= ruang_e($item['status_kondisi']) ?></span></td>
      </tr><?php endforeach; ?>
      </tbody></table></div>
      <?php else: ?><div class="empty">Belum ada inventaris aktif yang tercatat pada ruangan ini.</div><?php endif; ?>
      <div class="foot">Data ditampilkan langsung dari register inventaris RSU Nurusyifa. Harga, PIC, dan informasi sensitif tidak ditampilkan pada halaman publik.</div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
