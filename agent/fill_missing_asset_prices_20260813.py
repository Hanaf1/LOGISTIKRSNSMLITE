"""Fill zero asset prices only from defensible same-item historical prices."""

from __future__ import annotations

import argparse
import collections
import statistics

import pymysql


def normalized(value) -> str:
    return " ".join(str(value or "").casefold().split())


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()

    connection = pymysql.connect(
        host="localhost",
        user="root",
        password="",
        database="mlite_rsns",
        charset="latin1",
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        with connection.cursor() as cursor:
            cursor.execute(
                "SELECT id,kode_item,nama_aset,merk_type,jumlah,harga_beli "
                "FROM rsns_custom_logistik_non_medis_aset WHERE status='Aktif'"
            )
            rows = cursor.fetchall()

        by_name = collections.defaultdict(list)
        by_brand = collections.defaultdict(list)
        for row in rows:
            quantity = max(1, int(row["jumlah"] or 1))
            price = float(row["harga_beli"] or 0)
            if price <= 0:
                continue
            unit_price = price / quantity
            code = str(row["kode_item"] or "")
            name = normalized(row["nama_aset"])
            brand = normalized(row["merk_type"])
            by_name[(code, name)].append(unit_price)
            if brand:
                by_brand[(code, name, brand)].append(unit_price)

        updates = []
        source_counts = collections.Counter()
        for row in rows:
            if float(row["harga_beli"] or 0) > 0:
                continue
            code = str(row["kode_item"] or "")
            name = normalized(row["nama_aset"])
            brand = normalized(row["merk_type"])
            prices = by_brand.get((code, name, brand)) if brand else None
            source = "kode, nama, dan merk yang sama"
            if not prices:
                prices = by_name.get((code, name))
                source = "kode dan nama yang sama"
            if not prices:
                source_counts["belum ditemukan"] += 1
                continue

            quantity = max(1, int(row["jumlah"] or 1))
            total_price = int(round(statistics.median(prices) * quantity))
            if total_price <= 0:
                source_counts["belum ditemukan"] += 1
                continue
            updates.append((total_price, source, row["id"]))
            source_counts[source] += 1

        print(f"Harga dapat dilengkapi: {len(updates)}")
        print(f"Harga tetap kosong: {source_counts['belum ditemukan']}")
        print(f"Tambahan nilai: {sum(row[0] for row in updates)}")
        print(dict(source_counts))
        if not args.apply:
            print("Dry-run; database tidak berubah.")
            return

        with connection.cursor() as cursor:
            for total_price, source, asset_id in updates:
                note = "Harga dilengkapi otomatis dari median harga per unit aset dengan " + source + "."
                cursor.execute(
                    "UPDATE rsns_custom_logistik_non_medis_aset "
                    "SET harga_beli=%s,nilai_buku=%s,keterangan_inventaris=CONCAT_WS('\\n',NULLIF(keterangan_inventaris,''),%s) "
                    "WHERE id=%s AND harga_beli<=0",
                    (total_price, total_price, note, asset_id),
                )
        connection.commit()
        print(f"Harga diperbarui: {len(updates)}")
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


if __name__ == "__main__":
    main()
