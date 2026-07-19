import sys

file_path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace getGudangStok
old_get = """    public function getGudangStok()
    {
        $this->_initStok();
        $this->_addHeaderFiles();
        return $this->draw('gudang.stok.html');
    }"""

new_get = """    public function getGudangStok()
    {
        $this->_initStok();
        $this->_addHeaderFiles();
        $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
        return $this->draw('gudang.stok.html', ['kategori' => $kategori]);
    }"""

content = content.replace(old_get, new_get)

# Replace anyDisplayGudangStok
old_display = """        $perpage = 20;
        $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
        $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
        $lokasi = isset($_POST['lokasi']) ? $_POST['lokasi'] : '';
        
        $_offset = ($halaman - 1) * $perpage;
        
        $where = "WHERE 1=1";
        if(!empty($cari)) {
            $where .= " AND (b.nama_barang LIKE '%$cari%' OR b.kode_item LIKE '%$cari%')";
        }
        if(!empty($lokasi)) {
            $where .= " AND sb.kode_lokasi = '$lokasi'";
        }"""

new_display = """        $perpage = 20;
        $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
        $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
        $kategori = isset($_POST['kategori']) ? $_POST['kategori'] : '';
        
        $_offset = ($halaman - 1) * $perpage;
        
        $where = "WHERE 1=1";
        if(!empty($cari)) {
            $where .= " AND (b.nama_barang LIKE '%$cari%' OR b.kode_item LIKE '%$cari%')";
        }
        if(!empty($kategori)) {
            $where .= " AND b.kode_kategori = '$kategori'";
        }"""

content = content.replace(old_display, new_display)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Replaced Admin.php')
