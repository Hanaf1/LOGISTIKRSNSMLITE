import sys

def fix_url(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        c = f.read()
    
    old_str = "url: '{?=url([ADMIN, \"logistik_non_medis\", \"ajaxbarangselect2\"])?}',"
    new_str = "url: '{?=url(ADMIN.\"/logistik_non_medis/ajaxbarangselect2\")?}',"
    c = c.replace(old_str, new_str)
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(c)

paths = [
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.terima_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_nonrutin.form.html'
]

for p in paths:
    fix_url(p)
