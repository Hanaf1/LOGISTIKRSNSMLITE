import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

old_code = """        $rows = $rows->desc('kode_item')
                      ->offset($_offset)
                      ->limit($perpage)
                      ->toArray();"""

new_code = """        $rows = $rows->desc('kode_item')
                      ->offset($_offset)
                      ->limit($perpage)
                      ->toArray();
                      
        $kategori_lookup = [];
        $kategori_data = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
        foreach ($kategori_data as $kat) {
            $kategori_lookup[$kat['kode_kategori']] = $kat['nama_kategori'];
        }
        foreach ($rows as &$r) {
            $r['nama_kategori'] = isset($kategori_lookup[$r['kode_kategori']]) ? $kategori_lookup[$r['kode_kategori']] : $r['kode_kategori'];
        }"""

c = c.replace(old_code, new_code)
with open(path, 'w', encoding='utf-8') as f: f.write(c)
print('Done patch')
