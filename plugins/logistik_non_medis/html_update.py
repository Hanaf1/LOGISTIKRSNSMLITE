import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.barang.html'
with open(path, 'r', encoding='utf-8') as f: c = f.read()
c = c.replace('{$value.kategori}', '{$value.nama_kategori}')
with open(path, 'w', encoding='utf-8') as f: f.write(c)

path2 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.barang.form.html'
with open(path2, 'r', encoding='utf-8') as f: c2 = f.read()
c2 = c2.replace('name="kategori"', 'name="kode_kategori"')
c2 = c2.replace('{$barang.kategori}', '{$barang.kode_kategori}')
c2 = c2.replace('{$value.nama_kategori}</option>', '{$value.nama_kategori} ({$value.kode_kategori})</option>')
c2 = c2.replace('value="{$value.nama_kategori}"', 'value="{$value.kode_kategori}"')
with open(path2, 'w', encoding='utf-8') as f: f.write(c2)

path3 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.kategori.html'
with open(path3, 'r', encoding='utf-8') as f: c3 = f.read()
c3 = re.sub(r'<th>Nama Kategori</th>', '<th>Kode</th>\n<th>Nama Kategori</th>', c3)
c3 = re.sub(r'<td>\{\$value\.nama_kategori\}</td>', '<td>{$value.kode_kategori}</td>\n<td>{$value.nama_kategori}</td>', c3)
c3 = c3.replace('data-id="{$value.id}"', 'data-id="{$value.kode_kategori}"')
with open(path3, 'w', encoding='utf-8') as f: f.write(c3)

path4 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\master.kategori.form.html'
with open(path4, 'r', encoding='utf-8') as f: c4 = f.read()
c4 = c4.replace('<input type="hidden" name="id" value="{$kategori.id}">', '<div class="form-group"><label>Kode Kategori</label><input type="text" name="kode_kategori" class="form-control" value="{$kategori.kode_kategori}" readonly placeholder="Otomatis oleh sistem"></div>')
with open(path4, 'w', encoding='utf-8') as f: f.write(c4)

path5 = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\gudang.stok.html'
with open(path5, 'r', encoding='utf-8') as f: c5 = f.read()
c5 = c5.replace('value="{$value.nama_kategori}"', 'value="{$value.kode_kategori}"')
with open(path5, 'w', encoding='utf-8') as f: f.write(c5)

print('Done HTML updates')
