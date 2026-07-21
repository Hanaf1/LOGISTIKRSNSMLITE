import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master_kategori_aset.html'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('url([ADMIN, "logistik_non_medis", "displayMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "displaymasterkategoriaset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "formMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "formmasterkategoriaset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "saveMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "savemasterkategoriaset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "hapusMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "hapusmasterkategoriaset"])')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print("URLs changed to lowercase in master_kategori_aset.html")
