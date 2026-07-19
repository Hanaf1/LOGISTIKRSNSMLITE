import sys

def fix_params_page(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        c = f.read()
    
    old_str = "return { q: params.term, page: params.page };"
    new_str = "return { q: params.term, page: params.page || 1 };"
    c = c.replace(old_str, new_str)
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(c)

paths = [
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.terima_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_nonrutin.form.html'
]

for p in paths:
    fix_params_page(p)
