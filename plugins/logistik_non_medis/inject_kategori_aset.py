import sys

code_to_insert = """
  // ==========================================
  // MASTER KATEGORI ASET
  // ==========================================
  public function getMasterKategoriAset()
  {
      $this->core->addCSS(url([MODULES, 'logistik_non_medis', 'css', 'style.css']));
      $this->core->addJS(url([MODULES, 'logistik_non_medis', 'js', 'script.js']));
      return $this->draw('master_kategori_aset.html', [
          'title' => 'Master Kategori Aset',
      ]);
  }

  public function anyDisplayMasterKategoriAset()
  {
      $cari = $_GET['cari'] ?? '';
      $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
      $limit = 10;
      
      $query = $this->db('rsns_custom_logistik_non_medis_kategori_aset');
      $query_count = $this->db('rsns_custom_logistik_non_medis_kategori_aset');
      
      if ($cari) {
          $query->where('kode_kategori', 'LIKE', '%'.$cari.'%')
                ->orWhere('nama_kategori', 'LIKE', '%'.$cari.'%');
          $query_count->where('kode_kategori', 'LIKE', '%'.$cari.'%')
                      ->orWhere('nama_kategori', 'LIKE', '%'.$cari.'%');
      }
      
      $total = $query_count->count();
      $rows = $query->offset($offset)->limit($limit)->toArray();
      
      echo json_encode([
          'total' => $total,
          'rows' => $rows
      ]);
      exit();
  }

  public function anyFormMasterKategoriAset()
  {
      $kode_kategori = $_GET['kode'] ?? '';
      $data = [];
      if ($kode_kategori) {
          $data = $this->db('rsns_custom_logistik_non_medis_kategori_aset')->where('kode_kategori', $kode_kategori)->oneArray();
      }
      
      echo json_encode($data);
      exit();
  }

  public function postSaveMasterKategoriAset()
  {
      $kode_kategori = $_POST['kode_kategori'] ?? '';
      $nama_kategori = $_POST['nama_kategori'] ?? '';
      $kib_default = $_POST['kib_default'] ?? '';
      $umur_manfaat_default = $_POST['umur_manfaat_default'] ?? 0;
      $kode_coa = $_POST['kode_coa'] ?? '';
      $status_aktif = $_POST['status_aktif'] ?? 'Aktif';
      
      if (!$kode_kategori || !$nama_kategori) {
          echo json_encode(['status' => 'error', 'message' => 'Kode dan Nama Kategori harus diisi']);
          exit();
      }
      
      $cek = $this->db('rsns_custom_logistik_non_medis_kategori_aset')->where('kode_kategori', $kode_kategori)->oneArray();
      
      $data = [
          'kode_kategori' => $kode_kategori,
          'nama_kategori' => $nama_kategori,
          'kib_default' => $kib_default ? $kib_default : NULL,
          'umur_manfaat_default' => $umur_manfaat_default,
          'kode_coa' => $kode_coa,
          'status_aktif' => $status_aktif
      ];
      
      if ($cek) {
          $query = $this->db('rsns_custom_logistik_non_medis_kategori_aset')->where('kode_kategori', $kode_kategori)->update($data);
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_kategori_aset')->save($data);
      }
      
      if ($query) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function postHapusMasterKategoriAset()
  {
      $kode_kategori = $_POST['kode_kategori'] ?? '';
      if ($kode_kategori) {
          $this->db('rsns_custom_logistik_non_medis_kategori_aset')->where('kode_kategori', $kode_kategori)->delete();
      }
      exit();
  }
"""

with open(r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = "      exit();\n  }\n\n  private function _initRekananJasa()"
if target in content:
    content = content.replace(target, "      exit();\n  }\n\n" + code_to_insert + "\n  private function _initRekananJasa()")
    with open(r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php', 'w', encoding='utf-8') as f:
        f.write(content)
    print("Success")
else:
    print("Target not found")
