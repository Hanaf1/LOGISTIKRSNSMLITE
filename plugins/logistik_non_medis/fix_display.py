import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\schema.sql'
with open(path, 'r', encoding='utf-8') as f: c = f.read()
c = c.replace('`kode_kategori` varchar(50) NOT NULL,', '`id` int(11) NOT NULL AUTO_INCREMENT UNIQUE,\n  `kode_kategori` varchar(50) NOT NULL,')
with open(path, 'w', encoding='utf-8') as f: f.write(c)

path2 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path2, 'r', encoding='utf-8') as f: c2 = f.read()
c2 = c2.replace('`kode_kategori` varchar(50) NOT NULL,', '`id` int(11) NOT NULL AUTO_INCREMENT UNIQUE,\n          `kode_kategori` varchar(50) NOT NULL,')
with open(path2, 'w', encoding='utf-8') as f: f.write(c2)

path3 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.kategori.display.html'
with open(path3, 'r', encoding='utf-8') as f: c3 = f.read()
c3 = c3.replace('data-id="{$value.id}"', 'data-id="{$value.kode_kategori}"')
with open(path3, 'w', encoding='utf-8') as f: f.write(c3)

path4 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.barang.display.html'
with open(path4, 'r', encoding='utf-8') as f: c4 = f.read()
c4 = c4.replace('{$value.kategori}', '{$value.nama_kategori}')
with open(path4, 'w', encoding='utf-8') as f: f.write(c4)

print('Done fixing display HTML and id schema')
