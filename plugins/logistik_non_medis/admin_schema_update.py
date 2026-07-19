import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# Update Kategori table
c = re.sub(
    r'`id` int\(11\) NOT NULL AUTO_INCREMENT,\r?\n\s*`nama_kategori` varchar\(200\) NOT NULL,',
    '`kode_kategori` varchar(50) NOT NULL,\n          `nama_kategori` varchar(200) NOT NULL,',
    c
)
c = c.replace('PRIMARY KEY (`id`)', 'PRIMARY KEY (`kode_kategori`)')

# Update master_barang
c = c.replace('`kategori` varchar(100) DEFAULT NULL,', '`kode_kategori` varchar(50) DEFAULT NULL,')

with open(path, 'w', encoding='utf-8') as f: f.write(c)
print('Done Admin Schema')
