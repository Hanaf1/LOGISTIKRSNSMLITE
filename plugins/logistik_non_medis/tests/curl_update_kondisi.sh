#!/usr/bin/env bash
# PATCH update kondisi satu barang (hanya kode_aset + kondisi). MENGUBAH DATA ASLI.
#   bash plugins/logistik_non_medis/tests/curl_update_kondisi.sh AST-592021300001 "Rusak Berat"
#   bash plugins/logistik_non_medis/tests/curl_update_kondisi.sh AST-592021300001 Baik
# Jalankan dari folder mlite_rsns. Kunci API diambil dari config.php.

KODE="${1:?Isi kode_aset, contoh AST-592021300001}"
KONDISI="${2:?Isi kondisi: Baik / Rusak Ringan / Rusak Berat}"
BASE="${BASE:-http://localhost:7000/mlite_rsns/plugins/logistik_non_medis/public}"
K=$(php -r 'define("BASE_DIR", getcwd()); require "config.php"; echo LOGISTIK_NON_MEDIS_API_KEY;')

curl -s -w "\nHTTP %{http_code}\n" -X PATCH "$BASE/api-inventaris.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $K" \
  -d "{\"kode_aset\":\"$KODE\",\"kondisi\":\"$KONDISI\"}"
