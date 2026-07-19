import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

ajax_code = '''
  public function anyAjaxBarangSelect2()
  {
      $q = $_GET['q'] ?? '';
      $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
      $per_page = 20;
      $offset = ($page - 1) * $per_page;
      
      $where = "WHERE status = 'Aktif'";
      if (!empty($q)) {
          $where .= " AND (nama_barang LIKE '%$q%' OR kode_item LIKE '%$q%')";
      }
      
      $query_count = "SELECT COUNT(*) as total FROM rsns_custom_logistik_non_medis_master_barang $where";
      $total_count = $this->db()->pdo()->query($query_count)->fetchColumn();
      
      $query = "SELECT kode_item as id, CONCAT(kode_item, ' - ', nama_barang) as text 
                FROM rsns_custom_logistik_non_medis_master_barang 
                $where 
                ORDER BY nama_barang ASC 
                LIMIT $offset, $per_page";
      $items = $this->db()->pdo()->query($query)->fetchAll(\\PDO::FETCH_ASSOC);
      
      echo json_encode([
          'results' => $items,
          'pagination' => [
              'more' => ($page * $per_page) < $total_count
          ]
      ]);
      exit();
  }
'''

# Insert it before anyAjaxMasterBarang
if 'public function anyAjaxBarangSelect2()' not in c:
    c = c.replace('  public function anyAjaxMasterBarang()', ajax_code + '\n  public function anyAjaxMasterBarang()')
    with open(path, 'w', encoding='utf-8') as f: f.write(c)
