import re

# ========================================================
# Fix pengadaan.rencana_rutin.form.html 
# Replace AJAX Select2 with static Select2
# ========================================================
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html'
with open(path, 'r', encoding='utf-8') as f:
    c = f.read()

# Replace the whole script block
old_script = """<script>
var rowIdxRutin = {?=count($details) > 0 ? count($details) : 1?};

// Daftar barang dari master (sudah diload dari PHP)
var listBarangRutin = {?=json_encode($list_barang)?};

function buildBarangOptions(selectedKode) {
    var opts = '<option value=""></option>';
    for (var i = 0; i < listBarangRutin.length; i++) {
        var b = listBarangRutin[i];
        var sel = (b.kode_item === selectedKode) ? ' selected' : '';
        opts += '<option value="' + b.kode_item + '"' + sel + '>' + b.kode_item + ' - ' + b.nama_barang + '</option>';
    }
    return opts;
}

function initSelect2BarangRutin(el) {
    $(el).html(buildBarangOptions($(el).val()));
    $(el).select2({
        placeholder: 'Ketik nama / kode barang...',
        dropdownParent: $('#modalFormRutin'),
        width: '100%'
    });
}"""

if old_script in c:
    print("[OK] Script already in new format")
else:
    print("[INFO] Script not found in new format, trying to patch from original...")
    # Find and replace original AJAX script
    # Since we already ran fix_select2_static.py and it said OK, let's verify
    if "listBarangRutin" in c:
        print("[OK] listBarangRutin found - patch was applied")
    else:
        print("[FAIL] listBarangRutin NOT found - patch was NOT applied correctly")

print("=== CURRENT SCRIPT SECTION ===")
# Print lines 109-135
lines = c.split('\n')
for i, line in enumerate(lines[108:140], start=109):
    print(f"{i}: {line[:80]}")
