import sys

def fix_select2_url(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        c = f.read()
    
    # Replace URL
    c = c.replace(
        "url: baseURL + '/ajaxbarangselect2?t=' + mlite.token,",
        "url: '{?=url([ADMIN, \"logistik_non_medis\", \"ajaxbarangselect2\"])?}',"
    )
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(c)

paths = [
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.terima_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_nonrutin.form.html'
]

for p in paths:
    fix_select2_url(p)
