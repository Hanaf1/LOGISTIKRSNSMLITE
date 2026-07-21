import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('anyDisplayMasterKategoriAset', 'anydisplaymasterkategoriaset')
content = content.replace('anyFormMasterKategoriAset', 'anyformmasterkategoriaset')
content = content.replace('postSaveMasterKategoriAset', 'postsavemasterkategoriaset')
content = content.replace('postHapusMasterKategoriAset', 'posthapusmasterkategoriaset')
content = content.replace('getMasterKategoriAset', 'getmasterkategoriaset')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Methods renamed to lowercase in Admin.php")
