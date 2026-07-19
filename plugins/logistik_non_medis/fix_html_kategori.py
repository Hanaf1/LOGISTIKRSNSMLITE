import sys

file_path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\gudang.stok.html'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace HTML part
old_html = """                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Filter Lokasi</label>
                            <select id="filter-lokasi" class="form-control select2">
                                <option value="">Semua Lokasi</option>
                                {loop: $lokasi}
                                <option value="{$value.kode_lokasi}">{$value.nama_lokasi}</option>
                                {/loop}
                            </select>
                        </div>
                    </div>"""

new_html = """                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Filter Kategori</label>
                            <select id="filter-kategori" class="form-control select2">
                                <option value="">Semua Kategori</option>
                                {loop: $kategori}
                                <option value="{$value.kode_kategori}">{$value.nama_kategori}</option>
                                {/loop}
                            </select>
                        </div>
                    </div>"""
content = content.replace(old_html, new_html)

# Replace JS onChange
content = content.replace(
    "$('#filter-lokasi').change(function() {",
    "$('#filter-kategori').change(function() {"
)

# Replace JS variable
old_load_vars = """        var cari = $('#cari-stok').val();
        var lokasi = $('#filter-lokasi').val();
        $.post("{?=url([ADMIN, 'logistik_non_medis', 'displaygudangstok'])?}", {
            halaman: halaman,
            cari: cari,
            lokasi: lokasi"""

new_load_vars = """        var cari = $('#cari-stok').val();
        var kategori = $('#filter-kategori').val();
        $.post("{?=url([ADMIN, 'logistik_non_medis', 'displaygudangstok'])?}", {
            halaman: halaman,
            cari: cari,
            kategori: kategori"""

content = content.replace(old_load_vars, new_load_vars)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Replaced gudang.stok.html')
