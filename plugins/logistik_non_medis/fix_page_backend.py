import sys

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f:
    c = f.read()

old_code = "$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;"
new_code = "$page = !empty($_GET['page']) ? (int)$_GET['page'] : 1;"
c = c.replace(old_code, new_code)

with open(path, 'w', encoding='utf-8') as f:
    f.write(c)
