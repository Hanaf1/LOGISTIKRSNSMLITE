import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master_kategori_aset.html'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Remove from table header
content = re.sub(r'<th style="width: 15%">COA</th>\s*', '', content)

# Remove from AJAX JS rendering
content = re.sub(r'html \+= \'<td>\' \+ \(val\.kode_coa \? val\.kode_coa : \'-\'\) \+ \'</td>\';\s*', '', content)

# Remove from Form
form_coa = r'''<div class="form-group">\s*<label>Kode COA \(Opsional\)</label>\s*<input type="text" name="kode_coa" class="form-control" placeholder="Maks\. 50 karakter">\s*</div>'''
content = re.sub(form_coa, '', content)

# Remove from JS populate form
content = re.sub(r"\$\('#form-kategori-aset input\[name=\"kode_coa\"\]'\)\.val\(data\.kode_coa\);\s*", '', content)

# Change colspan for empty state
content = re.sub(r'colspan="7"', 'colspan="6"', content)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("COA removed from master_kategori_aset.html")
