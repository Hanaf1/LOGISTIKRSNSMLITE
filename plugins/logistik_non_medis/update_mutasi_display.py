import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

def replace_display_mutasi(match):
    return '''    public function anyDisplayMutasi()
    {
        $this->_initMutasi();
        $perpage = 10;
        $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
        $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
        
        $_offset = ($halaman - 1) * $perpage;
        
        $where = "";
        if(!empty($cari)) {
            $where = " WHERE no_mutasi LIKE '%$cari%' OR referensi_dokumen LIKE '%$cari%' OR kode_item IN (SELECT kode_item FROM rsns_custom_logistik_non_medis_master_barang WHERE nama_barang LIKE '%$cari%') ";
        }

        $query = "SELECT * FROM rsns_custom_logistik_non_medis_mutasi $where ORDER BY tgl_mutasi DESC, tgl_input DESC";
        $rows_all = $this->db()->pdo()->query($query)->fetchAll();
        $jumlah_data = count($rows_all);
        $jml_halaman = ceil($jumlah_data / $perpage);
        
        $query .= " LIMIT $_offset, $perpage";
        $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

        foreach($rows as &$row) {
            $item = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $row['kode_item'])->oneArray();
            $row['nama_barang'] = $item ? $item['nama_barang'] : '-';
        }

        return $this->draw('gudang.mutasi.display.html', [
            'mutasi' => $rows,
            'halaman' => $halaman,
            'jml_halaman' => $jml_halaman,
            'jumlah_data' => $jumlah_data,
            'halaman_array' => $this->core->getPagination($halaman, $jml_halaman)
        ]);
    }'''

c = re.sub(r'    public function anyDisplayMutasi\(\).*?\}\s+public function anyDisplayMutasiDetail\(\)', replace_display_mutasi(None) + '\n\n    public function anyDisplayMutasiDetail()', c, flags=re.DOTALL)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
