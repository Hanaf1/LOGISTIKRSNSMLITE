import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

start_marker = "  public function anyDisplayMutasi()"
end_marker = "  public function anyFormMutasi()"

start_idx = c.find(start_marker)
end_idx = c.find(end_marker, start_idx)

old_str = c[start_idx:end_idx]

new_str = '''  public function anyDisplayMutasi()
  {
      $this->_initMutasi();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $where = "";
      if(!empty($cari)) {
          $where = " WHERE no_mutasi LIKE '%$cari%' OR keterangan LIKE '%$cari%' ";
      }

      $query = "SELECT * 
                FROM rsns_custom_logistik_non_medis_mutasi 
                $where
                ORDER BY tgl_input DESC";
                
      $all_data = $this->db()->pdo()->query($query)->fetchAll();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $query .= " LIMIT $_offset, $perpage";
      $rows = $this->db()->pdo()->query($query)->fetchAll(\\PDO::FETCH_ASSOC);
      
      foreach ($rows as &$row) {
          $count_query = "SELECT COUNT(*) FROM rsns_custom_logistik_non_medis_mutasi_detail WHERE no_mutasi = '".$row['no_mutasi']."'";
          $row['jumlah_item'] = $this->db()->pdo()->query($count_query)->fetchColumn();
      }

      echo $this->draw('gudang.mutasi.display.html', [
          'mutasi' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'is_history' => false
      ]);
      exit();
  }

'''

c = c.replace(old_str, new_str)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
