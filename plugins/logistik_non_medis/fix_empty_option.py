import sys

def fix_empty_option(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        c = f.read()
    
    # Fix first row (empty details)
    c = c.replace(
        '<select name="items[0][kode_item]" class="form-control select2-barang" required style="width:100%;"></select>',
        '<select name="items[0][kode_item]" class="form-control select2-barang" required style="width:100%;"><option value=""></option></select>'
    )
    
    # Fix addItemRow
    c = c.replace(
        '<select name="items[${rowIdxRutin}][kode_item]" class="form-control select2-barang" required style="width:100%;"></select>',
        '<select name="items[${rowIdxRutin}][kode_item]" class="form-control select2-barang" required style="width:100%;"><option value=""></option></select>'
    )
    c = c.replace(
        '<select name="items[${rowIdx}][kode_item]" class="form-control select2-barang" required style="width:100%;"></select>',
        '<select name="items[${rowIdx}][kode_item]" class="form-control select2-barang" required style="width:100%;"><option value=""></option></select>'
    )
    c = c.replace(
        '<select name="items[${rowIdxNonRutin}][kode_item]" class="form-control select2-barang" required style="width:100%;"></select>',
        '<select name="items[${rowIdxNonRutin}][kode_item]" class="form-control select2-barang" required style="width:100%;"><option value=""></option></select>'
    )
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(c)

paths = [
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_nonrutin.form.html'
]

for p in paths:
    fix_empty_option(p)
