import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.perencanaan.html'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

c = c.replace("viewRKBU('{$row.id}')", "viewRKBU('{$row.kode_perencanaan}')")
c = c.replace("editRKBU('{$row.id}')", "editRKBU('{$row.kode_perencanaan}')")
c = c.replace("hapusRKBU('{$row.id}')", "hapusRKBU('{$row.kode_perencanaan}')")

with open(path, 'w', encoding='utf-8') as f: f.write(c)
