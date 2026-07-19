import sys, re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.barang.form.html'
with open(path, 'r', encoding='utf-8') as f:
    c = f.read()

# Remove barcode
c = re.sub(r'\s*<div class=\"form-group\">\s*<label>Barcode / QR String</label>[\s\S]*?</div>', '', c)
c = re.sub(r'\s*<input type=\"hidden\" name=\"barcode\" class=\"form-control\" value=\"\{\$barang\.barcode\}\">', '', c)

# Remove sub_kategori
c = re.sub(r'\s*<div class=\"form-group\">\s*<label>Sub Kategori</label>[\s\S]*?</div>', '', c)
c = re.sub(r'\s*<input type=\"hidden\" name=\"sub_kategori\" class=\"form-control\" value=\"\{\$barang\.sub_kategori\}\">', '', c)

# Remove default_kode_lokasi
c = re.sub(r'\s*<div class=\"form-group\">\s*<label>Lokasi Default</label>[\s\S]*?</select>\s*</div>', '', c)
c = re.sub(r'\s*<div class=\"form-group\">\s*<label>Default Lokasi</label>[\s\S]*?</select>\s*</div>', '', c)

with open(path, 'w', encoding='utf-8') as f:
    f.write(c)

path2 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.barang.html'
try:
    with open(path2, 'r', encoding='utf-8') as f:
        c2 = f.read()
    c2 = re.sub(r'\s*<th>Lokasi Def\.?</th>', '', c2)
    c2 = re.sub(r'\s*<th>Barcode</th>', '', c2)
    c2 = re.sub(r'\s*<td>\{\$value\.lokasi_default\}</td>', '', c2)
    c2 = re.sub(r'\s*<td>\{\$value\.barcode\}</td>', '', c2)
    
    with open(path2, 'w', encoding='utf-8') as f:
        f.write(c2)
except Exception as e:
    pass

print('Done HTML')
