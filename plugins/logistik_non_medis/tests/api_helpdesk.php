<?php
/**
 * Uji alur helpdesk: pilih unit -> pilih barang -> lapor (POST) -> teknisi selesai (POST hasil)
 * -> kondisi aset berubah -> tercatat di buku maintenance, dengan saringan unit & barang.
 * Semua data uji (laporan, antrean, kondisi) dikembalikan di akhir.
 *
 *   php plugins/logistik_non_medis/tests/api_helpdesk.php [base_url]
 */
define('BASE_DIR', dirname(__DIR__, 3));
require BASE_DIR . '/config.php';
$base = rtrim($argv[1] ?? 'http://localhost:7000/mlite_rsns', '/') . '/plugins/logistik_non_medis/public';
$LAPOR = "$base/api-lapor.php";
$BUKU = "$base/api-maintenance.php";
$lulus = 0; $gagal = 0; $nomorUji = [];

function panggil($metode, $url, $body = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metode, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $isi = curl_exec($ch);
    $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$kode, json_decode($isi, true)];
}

function cek($nama, $syarat, $info = '')
{
    global $lulus, $gagal;
    $syarat ? $lulus++ : $gagal++;
    printf("%-5s %-52s %s\n", $syarat ? 'OK' : 'GAGAL', $nama, $info);
}

$pdo = new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "== 1. FORM LAPOR: pilih unit lalu barang ==\n";
[$c, $d] = panggil('GET', "$LAPOR?daftar=unit");
cek('Daftar unit', $c == 200 && $d['total'] > 0, "unit={$d['total']}");
[$c, $d] = panggil('GET', "$LAPOR?daftar=unit&area=UNT-2026070016");
cek('Daftar unit di area Marwa', $c == 200 && $d['total'] > 0, "unit={$d['total']}");
$unit = $d['data'][0]['kode_unit'];
[$c, $d] = panggil('GET', "$LAPOR?daftar=barang&unit=$unit");
cek("Daftar barang di unit $unit", $c == 200 && $d['total'] > 0, "barang={$d['total']}");
$A = $d['data'][0]['kode_aset']; $A2 = $d['data'][1]['kode_aset'];
$kondisiAwal = [$A => $d['data'][0]['kondisi'], $A2 => $d['data'][1]['kondisi']];
[$c] = panggil('GET', "$LAPOR?daftar=barang");
cek('Daftar barang tanpa unit ditolak', $c == 400, "HTTP $c");

echo "\n== 2. LAPOR KERUSAKAN (POST) ==\n";
[$c, $d] = panggil('POST', $LAPOR, ['kode_aset' => $A, 'keluhan' => 'UJI-API tidak menyala', 'pelapor' => 'Uji Helpdesk', 'prioritas' => 'Tinggi']);
cek('Laporan 1 dibuat', $c == 201 && !empty($d['nomor']), $d['nomor'] ?? json_encode($d));
$nomor1 = $d['nomor'] ?? ''; $nomorUji[] = $nomor1;
[$c, $d] = panggil('POST', $LAPOR, ['kode_aset' => $A2, 'keluhan' => 'UJI-API pecah', 'pelapor' => 'Uji Helpdesk']);
cek('Laporan 2 dibuat', $c == 201 && !empty($d['nomor']), $d['nomor'] ?? json_encode($d));
$nomor2 = $d['nomor'] ?? ''; $nomorUji[] = $nomor2;
[$c, $d] = panggil('GET', "$LAPOR?unit=$unit&status=Menunggu");
cek("Laporan menunggu di unit $unit terlihat", $c == 200 && in_array($nomor1, array_column($d['data'], 'kode_pemeliharaan')), "total={$d['total']}");
[$c, $d] = panggil('GET', "$LAPOR?kode_aset=$A");
cek('Saring laporan per barang', $c == 200 && $d['total'] >= 1 && $d['data'][0]['kode_aset'] === $A, "total={$d['total']}");

echo "\n== 3. TEKNISI SELESAI (POST hasil) ==\n";
[$c, $d] = panggil('POST', $LAPOR, ['nomor' => $nomor1, 'hasil' => 'salah', 'teknisi' => 'Andi']);
cek('Hasil tidak sah ditolak', $c == 422, "HTTP $c");
[$c, $d] = panggil('POST', $LAPOR, ['nomor' => $nomor1, 'hasil' => 'diperbaiki']);
cek('Tanpa nama teknisi ditolak', $c == 422, "HTTP $c");
[$c, $d] = panggil('POST', $LAPOR, ['nomor' => $nomor1, 'hasil' => 'diperbaiki', 'teknisi' => 'Andi IPSRS', 'tiket_helpdesk' => 'HD-UJI-1']);
cek('Laporan 1: diperbaiki', $c == 200 && $d['data']['status'] === 'Selesai' && $d['data']['kondisi_aset_sekarang'] === 'Baik', "kondisi={$d['data']['kondisi_aset_sekarang']}");
[$c, $d] = panggil('POST', $LAPOR, ['nomor' => $nomor2, 'hasil' => 'tidak_bisa_diperbaiki', 'teknisi' => 'Andi IPSRS', 'tindakan' => 'Mesin terbakar']);
cek('Laporan 2: tidak bisa diperbaiki', $c == 200 && $d['data']['kondisi_aset_sekarang'] === 'Rusak Berat', "kondisi={$d['data']['kondisi_aset_sekarang']}");

echo "\n== 4. BUKU MAINTENANCE (saring unit & barang) ==\n";
[$c, $d] = panggil('GET', "$BUKU?unit=$unit");
$nomorBuku = array_column($d['data'], 'kode_pemeliharaan');
cek("Buku unit $unit memuat kedua laporan", $c == 200 && in_array($nomor1, $nomorBuku) && in_array($nomor2, $nomorBuku), "total={$d['total']}");
[$c, $d] = panggil('GET', "$BUKU?kode_aset=$A2");
cek('Buku per barang (tidak bisa diperbaiki)', $c == 200 && $d['data'][0]['status_kondisi_akhir'] === 'Rusak Berat', $d['data'][0]['tindakan_perbaikan'] ?? '');
[$c, $d] = panggil('GET', "$BUKU?area=UNT-2026070016&barang=" . urlencode(explode(' ', $d['data'][0]['nama_aset'])[0]));
cek('Buku saring area + nama barang', $c == 200 && $d['total'] >= 1, "total={$d['total']}");
[$c, $d] = panggil('GET', "$BUKU?daftar=barang&unit=$unit");
cek('Pilihan barang juga tersedia di buku', $c == 200 && $d['total'] > 0, "barang={$d['total']}");

echo "\n== BERSIH-BERSIH ==\n";
$marks = implode(',', array_fill(0, count($nomorUji), '?'));
$pdo->prepare("DELETE FROM rsns_custom_logistik_non_medis_helpdesk_outbox WHERE kunci_idempoten IN ($marks)")->execute($nomorUji);
$hapus = $pdo->prepare("DELETE FROM rsns_custom_logistik_non_medis_aset_pemeliharaan WHERE kode_pemeliharaan IN ($marks)");
$hapus->execute($nomorUji);
foreach ($kondisiAwal as $kode => $kondisi) {
    $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset SET status_kondisi = ? WHERE kode_aset = ?")->execute([$kondisi, $kode]);
}
cek('Laporan uji dihapus & kondisi dikembalikan', $hapus->rowCount() === count($nomorUji), json_encode($kondisiAwal));

echo "\nHASIL: $lulus OK, $gagal GAGAL\n";
