import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master_kategori_aset.html'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('url([ADMIN, "logistik_non_medis", "displaymasterkategoriaset"])', 'url([ADMIN, "logistik_non_medis", "displayMasterKategoriAset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "formmasterkategoriaset"])', 'url([ADMIN, "logistik_non_medis", "formMasterKategoriAset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "savemasterkategoriaset"])', 'url([ADMIN, "logistik_non_medis", "saveMasterKategoriAset"])')
content = content.replace('url([ADMIN, "logistik_non_medis", "hapusmasterkategoriaset"])', 'url([ADMIN, "logistik_non_medis", "hapusMasterKategoriAset"])')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

path2 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\manage.html'
with open(path2, 'r', encoding='utf-8') as f:
    content2 = f.read()

content2 = content2.replace('url([ADMIN,\'logistik_non_medis\',\'masterkategoriaset\'])', 'url([ADMIN,\'logistik_non_medis\',\'masterKategoriAset\'])')

with open(path2, 'w', encoding='utf-8') as f:
    f.write(content2)

print("Restored URLs to CamelCase")
