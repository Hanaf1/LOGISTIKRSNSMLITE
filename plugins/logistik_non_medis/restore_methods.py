import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('public function anydisplaymasterkategoriaset()', 'public function anyDisplayMasterKategoriAset()')
content = content.replace('public function anyformmasterkategoriaset()', 'public function anyFormMasterKategoriAset()')
content = content.replace('public function postsavemasterkategoriaset()', 'public function postSaveMasterKategoriAset()')
content = content.replace('public function posthapusmasterkategoriaset()', 'public function postHapusMasterKategoriAset()')
content = content.replace('public function getmasterkategoriaset()', 'public function getMasterKategoriAset()')

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Restored method names to CamelCase")
