<?php
/**
 * Uji lengkap api-inventaris.php: GET, filter, referensi, error, POST/PATCH (satu & batch).
 * Kondisi barang uji dikembalikan dan riwayat uji (petugas "uji-api") dihapus di akhir.
 *
 *   php plugins/logistik_non_medis/tests/api_inventaris.php [base_url]
 *   base_url bawaan: http://localhost:7000/mlite_rsns
 */
define('BASE_DIR', dirname(__DIR__, 3));
require BASE_DIR . '/config.php';
$B = rtrim($argv[1] ?? 'http://localhost:7000/mlite_rsns', '/') . '/plugins/logistik_non_medis/public/api-inventaris.php';
$K = LOGISTIK_NON_MEDIS_API_KEY;
$lulus = 0; $gagal = 0;

function panggil($metode, $url, $body = null, $kunci = null)
{
    $ch = curl_init($url);
    $header = ['Content-Type: application/json'];
    if ($kunci) $header[] = 'X-API-Key: ' . $kunci;
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metode, CURLOPT_HTTPHEADER => $header]);
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
    printf("%-4s %-55s %s\n", $syarat ? 'OK' : 'GAGAL', $nama, $info);
}

echo "== GET ==\n";
[$c, $d] = panggil('GET', "$B?page=1&per_page=5");
cek('Daftar halaman 1 (per_page=5)', $c == 200 && count($d['data']) == 5, "total={$d['total']} halaman={$d['total_pages']}");
$total = $d['total'];
[$c, $d2] = panggil('GET', "$B?page=2&per_page=5");
cek('Halaman 2 beda isi dengan halaman 1', $d2['data'][0]['kode_aset'] !== $d['data'][0]['kode_aset'], $d2['data'][0]['kode_aset']);
[$c, $d] = panggil('GET', "$B?limit=3&offset=3");
cek('Gaya lama limit/offset', $c == 200 && count($d['data']) == 3 && $d['page'] == 2, "page={$d['page']}");
[$c, $d] = panggil('GET', "$B?area=UNT-2026070016&per_page=1");
cek('Filter area Marwa', $c == 200 && $d['total'] > 0 && $d['data'][0]['nama_area'] === 'MARWA', "total={$d['total']}");
[$c, $d] = panggil('GET', "$B?unit=44,49&per_page=1");
cek('Filter beberapa ruangan (44,49)', $c == 200 && $d['total'] > 0, "total={$d['total']}");
[$c, $d] = panggil('GET', "$B?kondisi=Baik&per_page=1");
cek('Filter kondisi Baik', $c == 200 && $d['data'][0]['kondisi'] === 'Baik', "total={$d['total']}");
[$c, $d] = panggil('GET', "$B?q=kipas&per_page=1");
cek('Pencarian q=kipas', $c == 200 && $d['total'] > 0, "total={$d['total']}");
[$c, $d] = panggil('GET', "$B?daftar=area");
cek('Referensi daftar area', $c == 200 && $d['total'] > 0, "area={$d['total']}");
[$c, $d] = panggil('GET', "$B?daftar=ruangan&area=UNT-2026070016");
cek('Referensi ruangan di Marwa', $c == 200 && $d['total'] > 0, "ruangan={$d['total']}");

// Barang uji: ambil satu barang berkondisi Baik.
[$c, $d] = panggil('GET', "$B?kondisi=Baik&per_page=2");
$A = $d['data'][0]['kode_aset']; $A2 = $d['data'][1]['kode_aset'];
[$c, $d] = panggil('GET', "$B?kode=$A&riwayat=1");
cek('Detail satu barang + riwayat', $c == 200 && $d['data']['kode_aset'] === $A && isset($d['riwayat_kondisi']), $A);

echo "\n== ERROR ==\n";
[$c] = panggil('GET', "$B?kondisi=Hancur");
cek('Kondisi tidak valid ditolak', $c == 400, "HTTP $c");
[$c] = panggil('GET', "$B?daftar=salah");
cek('daftar tidak valid ditolak', $c == 400, "HTTP $c");
[$c] = panggil('GET', "$B?kode=TIDAK-ADA");
cek('Kode tidak ada', $c == 404, "HTTP $c");
[$c] = panggil('PATCH', $B, ['kode_aset' => $A, 'kondisi' => 'Rusak Ringan']);
cek('PATCH tanpa kunci API ditolak', $c == 401, "HTTP $c");
[$c] = panggil('PATCH', $B, ['kode_aset' => $A, 'kondisi' => 'Rusak Ringan'], 'kunci-salah');
cek('PATCH dengan kunci salah ditolak', $c == 401, "HTTP $c");
[$c] = panggil('PUT', $B, [], $K);
cek('Metode PUT tidak didukung', $c == 405, "HTTP $c");

echo "\n== UBAH KONDISI (kunci API) ==\n";
[$c, $d] = panggil('PATCH', $B, ['kode_aset' => $A, 'kondisi' => 'Rusak Ringan', 'catatan' => 'uji otomatis', 'petugas' => 'uji-api'], $K);
cek('PATCH satu barang: Baik -> Rusak Ringan', $c == 200 && $d['data']['kondisi'] === 'Rusak Ringan', ($d['pesan'] ?? '') . " sebelumnya={$d['kondisi_sebelumnya']}");
[$c, $d] = panggil('PATCH', $B, ['kode_aset' => $A, 'kondisi' => 'Rusak Ringan', 'petugas' => 'uji-api'], $K);
cek('PATCH kondisi sama -> tidak berubah', $c == 200 && $d['pesan'] === 'Kondisi tidak berubah.', $d['pesan']);
[$c, $d] = panggil('PATCH', $B, ['kode_aset' => $A, 'kondisi' => 'Hancur'], $K);
cek('PATCH kondisi tidak valid', $c == 422, "HTTP $c");
[$c, $d] = panggil('PATCH', $B, ['kode_aset' => 'TIDAK-ADA', 'kondisi' => 'Baik'], $K);
cek('PATCH barang tidak ada', $c == 404, "HTTP $c");
[$c, $d] = panggil('POST', $B, ['petugas' => 'uji-api', 'items' => [
    ['kode_aset' => $A, 'kondisi' => 'Baik'],
    ['kode_aset' => $A2, 'kondisi' => 'Rusak Berat', 'catatan' => 'uji batch'],
    ['kode_aset' => 'TIDAK-ADA', 'kondisi' => 'Baik'],
    ['kode_aset' => $A2, 'kondisi' => 'Hancur'],
]], $K);
$r = $d['ringkasan'] ?? [];
cek('POST batch 4 baris', $c == 200 && ($r['diubah'] ?? 0) == 2 && ($r['tidak_ditemukan'] ?? 0) == 1 && ($r['tidak_valid'] ?? 0) == 1, json_encode($r));
[$c, $d] = panggil('GET', "$B?kode=$A&riwayat=1");
cek('Riwayat kondisi tercatat', count($d['riwayat_kondisi']) >= 2, 'riwayat=' . count($d['riwayat_kondisi']));

echo "\n== BERSIH-BERSIH ==\n";
[$c, $d] = panggil('POST', $B, ['petugas' => 'uji-api', 'items' => [['kode_aset' => $A, 'kondisi' => 'Baik'], ['kode_aset' => $A2, 'kondisi' => 'Baik']]], $K);
[$c, $x] = panggil('GET', "$B?kode=$A2");
cek('Kondisi barang uji dikembalikan ke Baik', $x['data']['kondisi'] === 'Baik', "$A, $A2");
$pdo = new PDO('mysql:host=' . DBHOST . ';port=' . (defined('DBPORT') ? DBPORT : 3306) . ';dbname=' . DBNAME, DBUSER, DBPASS);
$hapus = $pdo->exec("DELETE FROM rsns_custom_logistik_non_medis_aset_riwayat_kondisi WHERE username = 'uji-api'");
[$c, $d] = panggil('GET', "$B?page=1&per_page=1");
cek('Total barang tidak berubah', $d['total'] == $total, "total=$total, riwayat uji dihapus=$hapus");

echo "\nHASIL: $lulus OK, $gagal GAGAL\n";
