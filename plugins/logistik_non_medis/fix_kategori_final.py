import sys

# Fix Admin.php
admin_path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(admin_path, 'r', encoding='utf-8') as f:
    admin_content = f.read()

admin_content = admin_content.replace(
    """        if(!empty($kategori)) {
            $where .= " AND b.kode_kategori = '$kategori'";
        }""",
    """        if(!empty($kategori)) {
            $where .= " AND b.kategori = '$kategori'";
        }"""
)

with open(admin_path, 'w', encoding='utf-8') as f:
    f.write(admin_content)


# Fix gudang.stok.html
html_path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\gudang.stok.html'
with open(html_path, 'r', encoding='utf-8') as f:
    html_content = f.read()

html_content = html_content.replace(
    """                                {loop: $kategori}
                                <option value="{$value.kode_kategori}">{$value.nama_kategori}</option>
                                {/loop}""",
    """                                {loop: $kategori}
                                <option value="{$value.nama_kategori}">{$value.nama_kategori}</option>
                                {/loop}"""
)

with open(html_path, 'w', encoding='utf-8') as f:
    f.write(html_content)

print("Fixed kategori filter")
