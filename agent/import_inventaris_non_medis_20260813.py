"""Replace non-Asuransi KIB assets from the supplied inventory workbook.

The workbook is intentionally treated as the source of truth. Existing assets
belonging to XLS-004 (Asuransi) and their related history are preserved.
"""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import re
from collections import Counter
from pathlib import Path

import openpyxl
import pymysql


ASURANSI_UNIT = "XLS-004"
IGNORED_SHEETS = {"Asuransi", "Sheet1", "Sheet4"}
SHEET_DEFAULT_UNITS = {"Server": 85}
MISSING_UNIT_NAMES = {
    129: "Madinah 2 - Ruang 1",
    130: "Madinah 2 - Ruang 2",
    131: "Madinah 2 - Ruang 3",
    132: "Madinah 2 - Ruang 4",
    133: "Madinah 2 - Ruang 5",
    134: "Madinah 2 - Ruang 6",
    136: "Madinah 3 - Ruang 1",
    137: "Madinah 3 - Ruang 2",
    138: "Madinah 3 - Ruang 3",
    139: "Madinah 3 - Ruang 4",
    140: "Madinah 3 - Ruang 5",
    141: "Madinah 3 - Ruang 6",
}


def text(value) -> str:
    return "" if value is None else str(value).strip()


def number(value, default=0.0) -> float:
    if isinstance(value, (int, float)):
        return float(value)
    raw = re.sub(r"[^0-9,.-]", "", text(value))
    if not raw:
        return float(default)
    if "," in raw and "." not in raw:
        raw = raw.replace(",", ".")
    else:
        raw = raw.replace(",", "")
    try:
        return float(raw)
    except ValueError:
        return float(default)


def inventory_digits(value) -> str:
    # A suffix such as " - 7" represents quantity, not part of the number.
    raw = text(value)
    base = re.split(r"\s+-\s+", raw, maxsplit=1)[0]
    return re.sub(r"\D", "", base)


def unit_number_from_inventory(digits: str) -> int | None:
    if len(digits) == 11:
        candidate = int(digits[:2])
    elif len(digits) == 12:
        # Some legacy two-digit-unit numbers contain one surplus digit in the
        # item segment (for example 874202030001 for unit 87).
        candidate = int(digits[:3])
        if candidate > 142:
            candidate = int(digits[:2])
    else:
        return None
    return candidate if 1 <= candidate <= 142 else None


def item_code_from_inventory(digits: str) -> str:
    if len(digits) == 11:
        return digits[3:9]
    if len(digits) == 12:
        return digits[4:10] if int(digits[:3]) <= 142 else digits[3:9]
    digest = hashlib.sha1(digits.encode("utf-8")).hexdigest()[:8].upper()
    return f"IMP{digest}"


def normalize_condition(raw: str) -> tuple[str, str]:
    value = text(raw)
    lowered = value.casefold()
    if lowered in {"", "baik", "biak"}:
        return "Baik", ""
    if any(word in lowered for word in ("hilang", "rusak berat")):
        return "Rusak Berat", value
    if any(word in lowered for word in ("rusak", "pecah", "bocor", "seret", "mati", "kemresek")):
        return "Rusak Ringan", value
    return "Baik", value


def normalize_source(raw: str) -> str:
    value = text(raw).casefold()
    if "hibah" in value:
        return "Hibah"
    if "apbd" in value:
        return "APBD"
    if value == "beli" or "beli" in value:
        return "Beli"
    return "Lainnya" if value else "Beli"


def header_map(normalized: list[str]) -> dict[str, int] | None:
    if "NO" not in normalized or not any("NAMA BARANG" in value for value in normalized):
        return None
    result: dict[str, int] = {}
    for index, value in enumerate(normalized):
        if value == "NO":
            result["no"] = index
        elif "NAMA BARANG" in value:
            result["name"] = index
        elif "NO INVENTARIS" in value:
            result["inventory"] = index
        elif value in {"JUMLAH", "JML"}:
            result["quantity"] = index
        elif value.startswith("HARGA"):
            result["price"] = index
        elif value in {"KONDISI*", "STATUS*", "KONDISI", "STATUS"}:
            result["condition"] = index
        elif "MERK" in value:
            result["brand"] = index
        elif "THN BELI" in value:
            result["year"] = index
        elif value.startswith("ASAL"):
            result["source"] = index
    return result if {"no", "name"}.issubset(result) else None


def parse_workbook(path: Path) -> list[dict]:
    workbook = openpyxl.load_workbook(path, data_only=True, read_only=True)
    parsed: list[dict] = []

    for sheet in workbook.worksheets:
        if sheet.title in IGNORED_SHEETS:
            continue
        headers = None
        current_unit = SHEET_DEFAULT_UNITS.get(sheet.title)
        current_location = sheet.title

        for row_number, row in enumerate(sheet.iter_rows(values_only=True), 1):
            values = list(row)
            normalized = [text(value).upper() for value in values]
            discovered = header_map(normalized)
            if discovered:
                headers = discovered
                continue
            if not headers:
                continue

            def get(key):
                index = headers.get(key, -1)
                return values[index] if 0 <= index < len(values) else None

            sequence = get("no")
            name = text(get("name"))
            if not isinstance(sequence, (int, float)) or sequence != int(sequence) or not name:
                joined = " ".join(text(value) for value in values[:4] if text(value))
                if joined and ("LOKASI" in joined.upper() or len([v for v in values if v not in (None, "")]) <= 2):
                    current_location = joined
                    matches = re.findall(r"(?<!\d)(\d{1,3})(?!\d)", joined)
                    if matches:
                        candidate = int(matches[-1])
                        if 1 <= candidate <= 142:
                            current_unit = candidate
                continue

            raw_inventory = text(get("inventory"))
            digits = inventory_digits(raw_inventory)
            inferred_unit = unit_number_from_inventory(digits)
            if inferred_unit is not None:
                current_unit = inferred_unit
            if current_unit is None:
                raise ValueError(f"Cannot determine unit at {sheet.title}!{row_number}: {name}")
            if current_unit == 4:
                # Protect Asuransi even if its data was copied into another sheet.
                continue

            quantity = max(1, int(round(number(get("quantity"), 1))))
            price = max(0.0, number(get("price"), 0))
            year_value = int(number(get("year"), 0))
            year = year_value if 1900 <= year_value <= dt.date.today().year else None
            condition, condition_note = normalize_condition(get("condition"))
            notes = [f"Impor Excel: {sheet.title}!{row_number}"]
            if raw_inventory:
                notes.append(f"Nomor Excel: {raw_inventory}")
            if condition_note:
                notes.append(f"Kondisi Excel: {condition_note}")

            parsed.append(
                {
                    "sheet": sheet.title,
                    "row": row_number,
                    "inventory": digits or None,
                    "raw_inventory": raw_inventory,
                    "name": name[:200],
                    "brand": text(get("brand"))[:150],
                    "quantity": quantity,
                    "price": price,
                    "year": year,
                    "source": normalize_source(get("source")),
                    "condition": condition,
                    "unit": f"XLS-{current_unit:03d}",
                    "location": current_location[:150],
                    "notes": "; ".join(notes),
                }
            )
    return parsed


def assign_codes(rows: list[dict]) -> None:
    seen = Counter()
    for row in rows:
        inventory = row["inventory"]
        if inventory:
            seen[inventory] += 1
            suffix = "" if seen[inventory] == 1 else f"-D{seen[inventory]}"
            row["asset_code"] = f"AST-{inventory}{suffix}"
            row["item_code"] = item_code_from_inventory(inventory)
        else:
            token = hashlib.sha1(f'{row["sheet"]}:{row["row"]}:{row["name"]}'.encode("utf-8")).hexdigest()[:12].upper()
            row["asset_code"] = f"AST-IMP-{token}"
            row["item_code"] = f"IMP{token[:8]}"


def validate(rows: list[dict], connection) -> None:
    if len(rows) < 3000:
        raise ValueError(f"Parsed only {len(rows)} rows; expected at least 3000")
    codes = [row["asset_code"] for row in rows]
    if len(codes) != len(set(codes)):
        raise ValueError("Generated asset codes are not unique")
    if any(row["unit"] == ASURANSI_UNIT for row in rows):
        raise ValueError("Asuransi rows leaked into the replacement set")
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT kode FROM rsns_custom_logistik_non_medis_inventaris_master "
            "WHERE jenis_master='UNIT' AND status='Aktif'"
        )
        valid_units = {record[0] for record in cursor.fetchall()}
    known_new_units = {f"XLS-{number:03d}" for number in MISSING_UNIT_NAMES}
    missing = sorted({row["unit"] for row in rows} - valid_units - known_new_units)
    if missing:
        raise ValueError(f"Workbook refers to missing unit masters: {', '.join(missing)}")


def import_rows(rows: list[dict], connection) -> tuple[int, int]:
    insert_sql = """
        INSERT INTO rsns_custom_logistik_non_medis_aset
        (kode_aset, nomor_inventaris, kode_item, nama_aset, merk_type,
         tahun_beli, satuan, jumlah, harga_beli, sumber_perolehan, kode_unit,
         lokasi_fisik, status_kondisi, keterangan_inventaris, status,
         nilai_buku, tgl_input, user_input)
        VALUES (%s,%s,%s,%s,%s,%s,'Unit',%s,%s,%s,%s,%s,%s,%s,'Aktif',%s,NOW(),'import_excel_20260813')
    """
    with connection.cursor() as cursor:
        for unit_number, unit_name in MISSING_UNIT_NAMES.items():
            cursor.execute(
                "INSERT IGNORE INTO rsns_custom_logistik_non_medis_inventaris_master "
                "(jenis_master,kode,kode_inventaris,kode_kategori,nama,status,tgl_input) "
                "VALUES ('UNIT',%s,%s,'',%s,'Aktif',NOW())",
                (f"XLS-{unit_number:03d}", f"{unit_number:03d}", unit_name),
            )
        cursor.execute(
            "SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_aset WHERE kode_unit=%s",
            (ASURANSI_UNIT,),
        )
        preserved = int(cursor.fetchone()[0])
        cursor.execute(
            "DELETE h FROM rsns_custom_logistik_non_medis_aset_mutasi h "
            "JOIN rsns_custom_logistik_non_medis_aset a ON a.kode_aset=h.kode_aset "
            "WHERE a.kode_unit<>%s OR a.kode_unit IS NULL",
            (ASURANSI_UNIT,),
        )
        cursor.execute(
            "DELETE FROM rsns_custom_logistik_non_medis_aset WHERE kode_unit<>%s OR kode_unit IS NULL",
            (ASURANSI_UNIT,),
        )
        for row in rows:
            cursor.execute(
                insert_sql,
                (
                    row["asset_code"], row["inventory"], row["item_code"], row["name"], row["brand"],
                    row["year"], row["quantity"], row["price"], row["source"], row["unit"],
                    row["location"], row["condition"], row["notes"], row["price"],
                ),
            )
    return preserved, len(rows)


def sync_missing_item_masters(connection) -> int:
    """Create BARANG masters for imported asset codes that have no master yet."""
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT kode_kelompok,nama_kelompok "
            "FROM rsns_custom_logistik_non_medis_inventaris_kelompok "
            "WHERE kode_kategori='2'"
        )
        groups = {row[0]: row[1] for row in cursor.fetchall()}
        cursor.execute(
            "SELECT kode_kelompok,kode_jenis,nama_jenis "
            "FROM rsns_custom_logistik_non_medis_inventaris_jenis "
            "WHERE kode_kategori='2'"
        )
        kinds = {(row[0], row[1]): row[2] for row in cursor.fetchall()}
        cursor.execute(
            "SELECT a.kode_item,a.nama_aset,COUNT(*) total "
            "FROM rsns_custom_logistik_non_medis_aset a "
            "LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master im "
            " ON im.jenis_master='BARANG' AND im.kode=a.kode_item "
            "WHERE a.status='Aktif' AND a.user_input='import_excel_20260813' AND im.id IS NULL "
            "GROUP BY a.kode_item,a.nama_aset "
            "ORDER BY a.kode_item,total DESC,a.nama_aset"
        )
        candidates = cursor.fetchall()

        # One master code can have spelling variants in Excel. Use the most
        # frequent name as its canonical master name; nama_aset remains intact.
        canonical = {}
        for code, name, count in candidates:
            if code not in canonical:
                canonical[code] = (name, count)

        created = 0
        for code, (name, _count) in canonical.items():
            if re.fullmatch(r"\d{6}", code or "") and code[:2] in groups:
                group = code[:2]
                requested_kind = code[2:4]
                kind = requested_kind if (group, requested_kind) in kinds else "99"
                if (group, kind) not in kinds:
                    kind = next((key[1] for key in kinds if key[0] == group), "99")
                item = code[4:6]
            else:
                group, kind = "06", "99"
                item = hashlib.sha1((code or name).encode("utf-8")).hexdigest()[:2].upper()

            cursor.execute(
                "INSERT IGNORE INTO rsns_custom_logistik_non_medis_inventaris_master "
                "(jenis_master,kode,kode_inventaris,kode_kategori,nama,kode_kelompok,kode_jenis,kode_barang,"
                " nama_kelompok,nama_jenis,kib_jenis,status,tgl_input) "
                "VALUES ('BARANG',%s,NULL,'2',%s,%s,%s,%s,%s,%s,'B','Aktif',NOW())",
                (
                    code,
                    name[:200],
                    group,
                    kind,
                    item,
                    groups.get(group, "APD dan Alat Rumah Tangga Lain-Lain"),
                    kinds.get((group, kind), "Lain-lain"),
                ),
            )
            created += cursor.rowcount
    return created


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("workbook", type=Path)
    parser.add_argument("--apply", action="store_true", help="commit replacement; default is dry-run")
    parser.add_argument("--sync-masters-only", action="store_true", help="only create missing item masters")
    args = parser.parse_args()

    connection = pymysql.connect(host="localhost", user="root", password="", database="mlite_rsns", charset="latin1")
    try:
        if args.sync_masters_only:
            created = sync_missing_item_masters(connection)
            connection.commit()
            print(f"Created missing item masters: {created}")
            return

        rows = parse_workbook(args.workbook)
        assign_codes(rows)
        validate(rows, connection)
        print(f"Parsed non-Asuransi rows: {len(rows)}")
        print(f"Units: {len(set(row['unit'] for row in rows))}")
        print(f"Rows without inventory number: {sum(row['inventory'] is None for row in rows)}")
        print(f"Duplicate inventory occurrences: {len(rows) - len(set(row['inventory'] for row in rows if row['inventory'])) - sum(row['inventory'] is None for row in rows)}")
        print(f"Conditions: {dict(Counter(row['condition'] for row in rows))}")
        if not args.apply:
            connection.rollback()
            print("Dry-run complete; database unchanged.")
            return
        preserved, inserted = import_rows(rows, connection)
        masters_created = sync_missing_item_masters(connection)
        connection.commit()
        print(f"Committed: preserved Asuransi={preserved}, inserted={inserted}, item masters={masters_created}")
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


if __name__ == "__main__":
    main()
