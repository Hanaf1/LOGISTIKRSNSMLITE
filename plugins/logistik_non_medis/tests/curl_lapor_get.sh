#!/usr/bin/env bash
# Uji GET alur form lapor helpdesk dengan curl (hanya baca, tidak mengubah data).
#   bash plugins/logistik_non_medis/tests/curl_lapor_get.sh [base_url] [kode_area]
# Contoh: bash plugins/logistik_non_medis/tests/curl_lapor_get.sh http://localhost:7000/mlite_rsns UNT-2026070016

BASE="${1:-http://localhost:7000/mlite_rsns}/plugins/logistik_non_medis/public"
AREA="${2:-UNT-2026070016}"

# Ringkas JSON dengan PHP supaya tidak butuh jq.
ringkas() { php -r "$1"; }

echo "=================================================================="
echo " 1. Daftar area (untuk pilihan pertama)"
echo "    curl \"$BASE/api-inventaris.php?daftar=area\""
echo "=================================================================="
curl -s "$BASE/api-inventaris.php?daftar=area" | ringkas '
$d = json_decode(stream_get_contents(STDIN), true);
foreach ($d["data"] as $a) printf("  %-16s %-22s %3d ruangan  %5d barang\n", $a["kode_area"] ?: "-", $a["nama_area"], $a["jumlah_ruangan"], $a["jumlah_barang"]);'

echo
echo "=================================================================="
echo " 2. Daftar unit di area $AREA"
echo "    curl \"$BASE/api-lapor.php?daftar=unit&area=$AREA\""
echo "=================================================================="
curl -s "$BASE/api-lapor.php?daftar=unit&area=$AREA" | ringkas '
$d = json_decode(stream_get_contents(STDIN), true);
echo "  total unit: {$d["total"]}\n";
foreach ($d["data"] as $u) printf("  kode %-5s %-35s %3d barang\n", $u["kode_unit"], $u["nama_unit"], $u["jumlah_barang"]);'
# unit pertama yang punya barang dipakai untuk langkah berikutnya
UNIT=$(curl -s "$BASE/api-lapor.php?daftar=unit&area=$AREA" | php -r '$d=json_decode(stream_get_contents(STDIN),true); foreach($d["data"] as $u){ if($u["jumlah_barang"]>0){ echo $u["kode_unit"]; break; } }')

echo
echo "=================================================================="
echo " 3. Daftar barang di unit $UNIT (pilihan barang saat tombol Lapor)"
echo "    curl \"$BASE/api-lapor.php?daftar=barang&unit=$UNIT\""
echo "=================================================================="
KODE=$(curl -s "$BASE/api-lapor.php?daftar=barang&unit=$UNIT" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
fwrite(STDERR, "  total barang: {$d["total"]}\n");
foreach ($d["data"] as $b) fwrite(STDERR, sprintf("  %-20s %-30s %-15s %s\n", $b["kode_aset"], mb_substr($b["nama_aset"], 0, 30), mb_substr((string)$b["merk_type"], 0, 15), $b["kondisi"]));
echo $d["data"][0]["kode_aset"] ?? "";')

NAMA=$(curl -s "$BASE/api-lapor.php?daftar=barang&unit=$UNIT" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo strtok($d["data"][0]["nama_aset"] ?? "", " ");')

echo
echo "=================================================================="
echo " 4. Cari barang \"$NAMA\" di unit $UNIT"
echo "    curl \"$BASE/api-lapor.php?daftar=barang&unit=$UNIT&barang=$NAMA\""
echo "=================================================================="
curl -s "$BASE/api-lapor.php?daftar=barang&unit=$UNIT&barang=$(php -r 'echo rawurlencode($argv[1]);' "$NAMA")" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
echo "  total: {$d["total"]}\n";
foreach ($d["data"] as $b) printf("  %-20s %-30s %s\n", $b["kode_aset"], $b["nama_aset"], $b["kondisi"]);'

echo
echo "=================================================================="
echo " 5. Informasi lengkap barang $KODE"
echo "    curl \"$BASE/api-inventaris.php?kode=$KODE&riwayat=1\""
echo "=================================================================="
curl -s "$BASE/api-inventaris.php?kode=$KODE&riwayat=1" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
foreach ($d["data"] as $k => $v) printf("  %-24s %s\n", $k, $v ?? "-");
echo "  riwayat_kondisi          ", count($d["riwayat_kondisi"] ?? []), " catatan\n";'
echo "  link hasil scan QR       $BASE/aset-info.php?kode=$KODE"
echo "  gambar QR                $BASE/aset-qr.php?kode=$KODE"

echo
echo "=================================================================="
echo " 6. Laporan kerusakan di unit $UNIT dan barang $KODE"
echo "    curl \"$BASE/api-lapor.php?unit=$UNIT\""
echo "    curl \"$BASE/api-lapor.php?kode_aset=$KODE\""
echo "=================================================================="
for Q in "unit=$UNIT" "kode_aset=$KODE"; do
curl -s "$BASE/api-lapor.php?$Q" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
echo "  [", $argv[1], "] total laporan: {$d["total"]}\n";
foreach ($d["data"] as $l) printf("    %-16s %-10s %-25s %s\n", $l["kode_pemeliharaan"], $l["status"], mb_substr($l["keluhan"], 0, 25), $l["nama_aset"]);' "$Q"
done

echo
echo "=================================================================="
echo " 7. Buku maintenance di unit $UNIT dan barang $KODE"
echo "    curl \"$BASE/api-maintenance.php?unit=$UNIT\""
echo "    curl \"$BASE/api-maintenance.php?kode_aset=$KODE\""
echo "=================================================================="
for Q in "unit=$UNIT" "kode_aset=$KODE"; do
curl -s "$BASE/api-maintenance.php?$Q" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
echo "  [", $argv[1], "] total catatan: {$d["total"]}\n";
foreach ($d["data"] as $l) printf("    %-16s %-19s %-25s %s\n", $l["kode_pemeliharaan"], $l["tanggal_pelaksanaan"], mb_substr((string)$l["tindakan_perbaikan"], 0, 25), $l["status_kondisi_akhir"] ?? "-");' "$Q"
done
