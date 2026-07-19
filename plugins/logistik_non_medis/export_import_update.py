import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# Update Export templates
c = c.replace("'Kategori', 'Satuan Dasar'", "'Kode Kategori', 'Satuan Dasar'")
c = c.replace("$row['kategori'],", "$row['kode_kategori'],")
c = c.replace("'kategori' => $data[1],", "'kode_kategori' => $data[1],")

with open(path, 'w', encoding='utf-8') as f: f.write(c)
print('Done Export/Import replace')
