import sys, re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f:
    c = f.read()

c = re.sub(r'^\s*`barcode` varchar\(100\) DEFAULT NULL,\r?\n', '', c, flags=re.MULTILINE)
c = re.sub(r'^\s*`sub_kategori` varchar\(100\) DEFAULT NULL,\r?\n', '', c, flags=re.MULTILINE)
c = re.sub(r'^\s*`default_kode_lokasi` varchar\(50\) DEFAULT NULL,\r?\n', '', c, flags=re.MULTILINE)
c = re.sub(r'\s*->orLike\(\'barcode\', \'%.*?\'\)', '', c)
c = re.sub(r'\s*\'barcode\' => \'\',', '', c)
c = re.sub(r'\s*\'sub_kategori\' => \'\',', '', c)
c = re.sub(r'\s*\'default_kode_lokasi\' => \'\',', '', c)
c = re.sub(r'\s*\'barcode\' => \$_POST\[\'barcode\'\] \?\? \'\',', '', c)
c = re.sub(r'\s*\'sub_kategori\' => \$_POST\[\'sub_kategori\'\] \?\? \'\',', '', c)
c = re.sub(r'\s*\'default_kode_lokasi\' => \$_POST\[\'default_kode_lokasi\'\] \?\? NULL,', '', c)

# Log string
c = c.replace("' | '.$data['barcode'].' | '", "' | '")
c = c.replace("' | '.$data['sub_kategori'].' | '", "' | '")

# anySearchItemByBarcode 
c = c.replace("->where('barcode', $barcode)", "->where('kode_item', $barcode)")
c = c.replace("->orWhere('kode_item', $barcode)", "") # Since we already where'd it

# loc mapping
c = re.sub(r'^\s*\$loc_map\[\$b\[\'kode_item\'\]\] = \$b\[\'default_kode_lokasi\'\];\r?\n', '', c, flags=re.MULTILINE)
c = re.sub(r'^\s*\$item\[\'default_kode_lokasi\'\] = \$loc_map\[\$item\[\'kode_item\'\]\] \?\? \'\';\r?\n', '', c, flags=re.MULTILINE)

# loc retrieval in search
c = re.sub(r'\s*if\(!empty\(\$item\[\'default_kode_lokasi\'\]\)\) \{[\s\S]*?\}', '', c)

# anySearchItemByBarcode return payload
c = re.sub(r'^\s*\'default_kode_lokasi\' => \$item\[\'default_kode_lokasi\'\],\r?\n', '', c, flags=re.MULTILINE)

# Remove ALTER TABLE ADD `default_kode_lokasi`
c = re.sub(r'\s*\$check_loc = \$this->db\(\)->pdo\(\)->query\("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_master_barang` LIKE \'default_kode_lokasi\'"\)->fetch\(\);\r?\n\s*if\(!\$check_loc\) \{\r?\n\s*\$this->db\(\)->pdo\(\)->exec\("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `default_kode_lokasi` varchar\(50\) DEFAULT NULL AFTER `dokumen` "\);\r?\n\s*\}', '', c)

with open(path, 'w', encoding='utf-8') as f:
    f.write(c)
print('Done Admin.php')
