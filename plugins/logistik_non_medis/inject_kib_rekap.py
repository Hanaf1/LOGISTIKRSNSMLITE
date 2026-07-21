import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\aset.kib.rekap.html'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace cards
target_cards = re.compile(r'<!-- KIB A -->.*?</div>\s*</div>\s*</div>', re.DOTALL)

replacement_cards = """    {if: !empty($kpi)}
    {loop: $kpi}
    <div class="col-lg-4 col-md-6 col-sm-6" style="margin-bottom: 15px;">
        <div class="kpi-card" style="background-color: #3c8dbc; color: #fff; padding: 15px; border-radius: 3px; position: relative; overflow: hidden; cursor: pointer; min-height: 95px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); transition: transform 0.15s;" onclick="$('.kib-nav-tabs a[data-kib=\\'{$key}\\']').tab('show');">
            <div style="font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9;">{$value.nama_kategori}</div>
            <div style="font-size: 20px; font-weight: bold; margin: 5px 0 2px 0;">Rp. {?=number_format($value.total_nilai, 0, ',', '.')?}</div>
            <div style="font-size: 12px; opacity: 0.9;"><i class="fa fa-tag"></i> <strong>{$value.jumlah}</strong> Unit Aset Terdaftar</div>
            <i class="fa fa-cubes" style="position: absolute; right: 15px; bottom: 8px; font-size: 45px; opacity: 0.25;"></i>
        </div>
    </div>
    {/loop}
    {/if}"""

content = target_cards.sub(replacement_cards, content)

content = content.replace("Atribut golongan KIB {$value.jenis}", "Kategori Aset {$value.nama}")
content = content.replace("Tabel Rekapitulasi Nilai Buku Aset Tetap per Golongan KIB", "Tabel Rekapitulasi Nilai Buku Aset Tetap per Kategori")
content = content.replace("<th>Golongan Inventaris Barang (KIB)</th>", "<th>Kategori Aset</th>")
content = content.replace("Gol", "Kode")

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print("aset.kib.rekap.html updated")
