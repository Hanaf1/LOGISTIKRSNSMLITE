#!/bin/bash
# Regenerasi full.sql & schemanew.sql plugin Logistik Non Medis, kompatibel MySQL 5.7.
#
# Pemakaian:
#   bash regen_sql.sh                 # generate + uji impor (default)
#   bash regen_sql.sh --no-verify     # generate saja, tanpa uji impor
#   bash regen_sql.sh --check-only    # tidak generate; hanya periksa berkas yang ada
#   bash regen_sql.sh --db=nama_db    # sumber database lain (default: mlite_rsns)
#   bash regen_sql.sh --out=/path     # folder tujuan (default: deteksi otomatis)
set -e

DB=mlite_rsns
OUT=""
VERIFY=1
CHECK_ONLY=0
MYSQL_USER=root

for arg in "$@"; do
  case "$arg" in
    --no-verify)  VERIFY=0 ;;
    --check-only) CHECK_ONLY=1 ;;
    --db=*)       DB="${arg#*=}" ;;
    --out=*)      OUT="${arg#*=}" ;;
    --user=*)     MYSQL_USER="${arg#*=}" ;;
    *) echo "Argumen tidak dikenal: $arg" >&2; exit 2 ;;
  esac
done

# Deteksi folder plugin bila --out tidak diberikan.
if [ -z "$OUT" ]; then
  for c in \
    "$(pwd)/plugins/logistik_non_medis" \
    "$(pwd)" \
    "/c/laragon/www/mlite_rsns/plugins/logistik_non_medis"; do
    if [ -d "$c" ] && [ -f "$c/Admin.php" ]; then OUT="$c"; break; fi
  done
fi
if [ -z "$OUT" ] || [ ! -d "$OUT" ]; then
  echo "GAGAL: folder plugin tidak ditemukan. Pakai --out=/path/ke/plugins/logistik_non_medis" >&2
  exit 1
fi

P=rsns_custom_logistik_non_medis_
MY="mysql -u $MYSQL_USER"
DUMP="mysqldump -u $MYSQL_USER --default-character-set=utf8mb4 --no-tablespaces --column-statistics=0 --set-gtid-purged=OFF --single-transaction --quick"
q() { $MY "$DB" -N -e "$1" | tr -d '\015'; }

# ---------------------------------------------------------------- periksa saja
periksa_berkas() {
  local f="$1" nama; nama=$(basename "$f")
  local gagal=0
  [ -f "$f" ] || { echo "  $nama : TIDAK ADA"; return 1; }
  for pola in "utf8mb4_0900_ai_ci|collation MySQL 8, ERROR 1273 di 5.7" \
              "DEFINER=|klausa DEFINER, ERROR 1227 bila user server beda" \
              "utf8mb3|charset khusus MySQL 8"; do
    local pat="${pola%%|*}" pesan="${pola#*|}"
    local n; n=$(grep -c "$pat" "$f" || true)
    if [ "$n" -gt 0 ]; then echo "  $nama : MASALAH ($n) -> $pesan"; gagal=1; fi
  done
  # role_permission_item tidak boleh punya data (diisi trigger, kalau di-dump -> ERROR 1062)
  local n; n=$(grep -c "INSERT INTO \`${P}role_permission_item\`" "$f" || true)
  if [ "$n" -gt 0 ]; then echo "  $nama : MASALAH -> data role_permission_item ikut ter-dump (ERROR 1062)"; gagal=1; fi
  # stored procedure wajib ada dan berada sebelum INSERT pertama
  local baris_proc baris_insert
  baris_proc=$(grep -n "CREATE.*PROCEDURE" "$f" | head -1 | cut -d: -f1 || true)
  baris_insert=$(grep -n "^INSERT INTO" "$f" | head -1 | cut -d: -f1 || true)
  if [ -z "$baris_proc" ]; then
    echo "  $nama : MASALAH -> stored procedure tidak ada (role_permissions akan kosong)"; gagal=1
  elif [ -n "$baris_insert" ] && [ "$baris_proc" -gt "$baris_insert" ]; then
    echo "  $nama : MASALAH -> procedure ada SETELAH data (PROCEDURE does not exist)"; gagal=1
  fi
  [ "$gagal" -eq 0 ] && echo "  $nama : BERSIH (siap untuk MySQL 5.7)"
  return $gagal
}

if [ "$CHECK_ONLY" -eq 1 ]; then
  echo ">> Periksa berkas SQL yang ada"
  rc=0
  periksa_berkas "$OUT/full.sql" || rc=1
  periksa_berkas "$OUT/schemanew.sql" || rc=1
  exit $rc
fi

# ------------------------------------------------------------------- generate
# Riwayat transaksi: struktur ikut, DATA tidak ikut di kedua berkas.
TRANSAKSI="sppb sppb_approval sppb_fulfillment sppb_item_attachment sppb_item_ditolak sppb_item_meta
sppb_konsul_draft sppb_request_meta sppb_ttd penerimaan terima_rutin terima_rutin_detail po pr
perencanaan rkbu_batch rkbu_batch_approval rencana_nonrutin rencana_nonrutin_detail rencana_rutin
rencana_rutin_detail mutasi mutasi_detail opname packing pengiriman serah_terima retur_unit
barang_rusak kartu_stok cost_unit_audit notifikasi notifier_event push_subscription aset_mutasi
aset_pemeliharaan aset_penghapusan aset_penyusutan aset_riwayat_kondisi aset_sensus cek_ruang
cek_ruang_item helpdesk_outbox report_verifications report_schedules vendor_evaluasi waha_send_log
ppi_barang"

# Hak akses & data pengguna: ditangani terpisah (full = semua role, new = admin saja).
AKSES="role_permissions role_permission_item user_roles user_unit wa_contact"

ALL=$(q "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB' AND table_name LIKE '${P}%' AND table_type='BASE TABLE' ORDER BY table_name")
VIEWS=$(q "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB' AND table_name LIKE '${P}%' AND table_type='VIEW' ORDER BY table_name")
[ -n "$ALL" ] || { echo "GAGAL: tidak ada tabel berawalan $P di database $DB" >&2; exit 1; }

skip=""
for t in $TRANSAKSI $AKSES; do skip="$skip ${P}$t"; done
DATA_NEW=""
for t in $ALL; do
  case " $skip " in *" $t "*) ;; *) DATA_NEW="$DATA_NEW $t" ;; esac
done

TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT

# Stored procedure dipanggil oleh 3 trigger di role_permissions, jadi HARUS dibuat
# sebelum ada INSERT. mysqldump menaruhnya di akhir berkas, itu yang bikin gagal.
echo ">> stored procedure"
$DUMP --routines --no-create-info --no-data --no-create-db --skip-triggers "$DB" > "$TMP/routines.sql"

echo ">> struktur tabel + view"
$DUMP --no-data "$DB" $ALL $VIEWS > "$TMP/struct.sql"

echo ">> data master + stok akhir (tanpa riwayat transaksi)"
$DUMP --no-create-info --skip-triggers "$DB" $DATA_NEW > "$TMP/data.sql"

echo ">> hak akses: full = semua role"
: > "$TMP/akses_full.sql"
for t in role_permissions user_roles user_unit wa_contact; do
  $DUMP --no-create-info --skip-triggers "$DB" "${P}$t" >> "$TMP/akses_full.sql"
done

echo ">> hak akses: schemanew = admin saja"
: > "$TMP/akses_new.sql"
for t in role_permissions user_roles; do
  $DUMP --no-create-info --skip-triggers --where="role='admin'" "$DB" "${P}$t" >> "$TMP/akses_new.sql"
done
# CATATAN: role_permission_item sengaja TIDAK di-dump. Tabel turunan, diisi otomatis
# oleh trigger dari role_permissions. Kalau ikut -> ERROR 1062 dan impor berhenti.

cat "$TMP/struct.sql" "$TMP/routines.sql" "$TMP/data.sql" "$TMP/akses_full.sql" > "$OUT/full.sql"
cat "$TMP/struct.sql" "$TMP/routines.sql" "$TMP/data.sql" "$TMP/akses_new.sql"  > "$OUT/schemanew.sql"

echo ">> penyesuaian MySQL 5.7"
for f in "$OUT/full.sql" "$OUT/schemanew.sql"; do
  sed -i 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g; s/utf8mb3/utf8/g' "$f"
  sed -i 's/ DEFINER=`[^`]*`@`[^`]*`//g' "$f"
  sed -i 's/SQL SECURITY DEFINER/SQL SECURITY INVOKER/g' "$f"
done

# Jaga agar salinan di repo (dipakai tanpa Claude) selalu identik dengan skrip ini.
if [ -d "$OUT/tools" ] && [ "$(readlink -f "$0" 2>/dev/null)" != "$(readlink -f "$OUT/tools/regen_sql.sh" 2>/dev/null)" ]; then
  cp "$0" "$OUT/tools/regen_sql.sh"
fi

# --------------------------------------------------------------------- verify
if [ "$VERIFY" -eq 1 ]; then
  echo ""
  echo ">> UJI IMPOR ke database kosong"
  rc=0
  for nama in schemanew full; do
    db="uji_regen_$nama"
    $MY -e "DROP DATABASE IF EXISTS \`$db\`; CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    err=$($MY "$db" < "$OUT/$nama.sql" 2>&1 || true)
    if [ -n "$err" ]; then
      echo "   $nama.sql : GAGAL"
      echo "$err" | head -5 | sed 's/^/      /'
      rc=1
    else
      ringkas=$($MY "$db" -N -e "
        SELECT CONCAT('barang=', (SELECT COUNT(*) FROM \`$db\`.${P}master_barang),
               ' stok_batch=', (SELECT COUNT(*) FROM \`$db\`.${P}stok_batch),
               ' aset=', (SELECT COUNT(*) FROM \`$db\`.${P}aset),
               ' transaksi_sppb=', (SELECT COUNT(*) FROM \`$db\`.${P}sppb),
               ' role=', (SELECT COUNT(*) FROM \`$db\`.${P}role_permissions),
               ' izin_item=', (SELECT COUNT(*) FROM \`$db\`.${P}role_permission_item),
               ' user_roles=', (SELECT COUNT(*) FROM \`$db\`.${P}user_roles));" | tr -d '\015')
      echo "   $nama.sql : SUKSES tanpa error"
      echo "      $ringkas"
    fi
    $MY -e "DROP DATABASE IF EXISTS \`$db\`;"
  done
  echo ""
  echo ">> Pemeriksaan pola MySQL 5.7"
  periksa_berkas "$OUT/full.sql" || rc=1
  periksa_berkas "$OUT/schemanew.sql" || rc=1
  [ "$rc" -ne 0 ] && { echo ""; echo "ADA MASALAH — jangan commit sebelum diperbaiki." >&2; exit 1; }
fi

echo ""
echo ">> SELESAI. Berkas diperbarui:"
ls -la "$OUT/full.sql" "$OUT/schemanew.sql" | awk '{print "   " $9 "  " $5 " bytes"}'
