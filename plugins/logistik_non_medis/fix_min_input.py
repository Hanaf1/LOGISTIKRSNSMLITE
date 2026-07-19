import sys
import re

def fix_min_input(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        c = f.read()

    # Replace minimumInputLength: 0 with 1
    c = c.replace('minimumInputLength: 0,', 'minimumInputLength: 1,')

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(c)

paths = [
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.terima_rutin.form.html',
    r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_nonrutin.form.html'
]

for p in paths:
    fix_min_input(p)
