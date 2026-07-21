import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master_kategori_aset.html'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('url([ADMIN, "logistik_non_medis", "masterkategoriaset", "displayMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "displayMasterKategoriAset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "masterkategoriaset", "formMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "formMasterKategoriAset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "masterkategoriaset", "saveMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "saveMasterKategoriAset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "masterkategoriaset", "hapusMasterKategoriAset"])', 'url([ADMIN, "logistik_non_medis", "hapusMasterKategoriAset"])')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print("master_kategori_aset.html AJAX URLs fixed")
