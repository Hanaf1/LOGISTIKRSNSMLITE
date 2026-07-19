import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# anyDisplayMasterBarang
old_query = """        $query = "SELECT b.*, s1.nama_satuan as satuan_dasar_nama, s2.nama_satuan as satuan_konversi_nama
                  FROM rsns_custom_logistik_non_medis_master_barang b
                  LEFT JOIN rsns_custom_logistik_non_medis_satuan s1 ON b.satuan_dasar = s1.kode_satuan
                  LEFT JOIN rsns_custom_logistik_non_medis_satuan s2 ON b.satuan_konversi = s2.kode_satuan
                  $where ORDER BY b.nama_barang ASC LIMIT $_offset, $perpage";"""
new_query = """        $query = "SELECT b.*, s1.nama_satuan as satuan_dasar_nama, s2.nama_satuan as satuan_konversi_nama, k.nama_kategori
                  FROM rsns_custom_logistik_non_medis_master_barang b
                  LEFT JOIN rsns_custom_logistik_non_medis_satuan s1 ON b.satuan_dasar = s1.kode_satuan
                  LEFT JOIN rsns_custom_logistik_non_medis_satuan s2 ON b.satuan_konversi = s2.kode_satuan
                  LEFT JOIN rsns_custom_logistik_non_medis_kategori k ON b.kode_kategori = k.kode_kategori
                  $where ORDER BY b.nama_barang ASC LIMIT $_offset, $perpage";"""
c = c.replace(old_query, new_query)

# postSaveMasterBarang mapping
c = c.replace(
    "'kategori' => $_POST['kategori'] ?? '',",
    "'kode_kategori' => $_POST['kode_kategori'] ?? '',"
)
c = c.replace(
    "' | '.$data['kategori'].' | '",
    "' | '.$data['kode_kategori'].' | '"
)

# anyFormMasterBarang array
c = c.replace(
    "'kategori' => '',",
    "'kode_kategori' => '',"
)

# getGudangStok / anyDisplayGudangStok
c = c.replace(
    "$where .= \" AND b.kategori = '$kategori'\";",
    "$where .= \" AND b.kode_kategori = '$kategori'\";"
)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
print('Done Master Barang logic')
