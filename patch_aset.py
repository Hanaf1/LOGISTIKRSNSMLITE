import re

with open('plugins/logistik_non_medis/view/admin/aset.registrasi.html', 'r', encoding='utf-8') as f:
    html_content = f.read()

target = '<ul class="pagination pagination-sm pagination-aset" style="margin: 0;">'
replacement = '<ul class="pagination pagination-sm pagination-aset" id="pagination-aset" style="margin: 0;">'

if target in html_content:
    html_content = html_content.replace(target, replacement)
    print("Replaced successfully.")
else:
    print("Target not found.")

with open('plugins/logistik_non_medis/view/admin/aset.registrasi.html', 'w', encoding='utf-8') as f:
    f.write(html_content)
