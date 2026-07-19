import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

new_methods = '''
  // =========================================================
  // MODUL RENCANA PEMBELIAN & REALISASI RUTIN
  // =========================================================
  public function anyRencanaRutin()
  {
      $bulan = $_GET['bulan'] ?? date('m');
      $tahun = $_GET['tahun'] ?? date('Y');
      
      $query = "SELECT r.*, 
                (SELECT SUM(d.qty_rencana * d.estimasi_harga) FROM rsns_custom_logistik_non_medis_rencana_rutin_detail d WHERE d.no_rencana = r.no_rencana) as total_estimasi
                FROM rsns_custom_logistik_non_medis_rencana_rutin r 
                WHERE r.bulan = '$bulan' AND r.tahun = '$tahun' 
                ORDER BY r.tanggal_buat DESC";
                
      $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
      $rencana_rutin = [];
      foreach ($rows as $i => $row) {
          $row['no'] = $i + 1;
          $row['total_estimasi'] = number_format($row['total_estimasi'] ?? 0, 0, ',', '.');
          $rencana_rutin[] = $row;
      }
      
      $this->core->addJS(url('assets/jscripts/select2.min.js'));
      $this->core->addCSS(url('assets/css/select2.min.css'));
      $this->tpl->set('rencana_rutin', $rencana_rutin);
      $this->tpl->set('bulan', $bulan);
      $this->tpl->set('tahun', $tahun);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_rutin.html', true);
      exit();
  }

  public function anyFormRencanaRutin()
  {
      $id = $_POST['id'] ?? '';
      $rencana = [
          'no_rencana' => 'RTR-'.date('YmdHis'),
          'bulan' => date('m'),
          'tahun' => date('Y'),
          'tanggal_buat' => date('Y-m-d'),
          'keterangan' => ''
      ];
      $details = [];
      
      if ($id) {
          $rencana = $this->db('rsns_custom_logistik_non_medis_rencana_rutin')->where('no_rencana', $id)->oneArray();
          
          $sql_det = "SELECT d.*, b.nama_barang 
                      FROM rsns_custom_logistik_non_medis_rencana_rutin_detail d
                      LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = d.kode_item
                      WHERE d.no_rencana = '$id'";
          $details = $this->db()->pdo()->query($sql_det)->fetchAll(\PDO::FETCH_ASSOC);
      }
      
      $this->tpl->set('id', $id);
      $this->tpl->set('rencana', $rencana);
      $this->tpl->set('details', $details);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_rutin.form.html', true);
      exit();
  }

  public function postSaveRencanaRutin()
  {
      $no_rencana = $_POST['no_rencana'] ?? '';
      $bulan = $_POST['bulan'] ?? '';
      $tahun = $_POST['tahun'] ?? '';
      $tanggal_buat = $_POST['tanggal_buat'] ?? '';
      $keterangan = $_POST['keterangan'] ?? '';
      $items = $_POST['items'] ?? [];
      
      if (empty($items)) {
          echo json_encode(['status' => 'error', 'message' => 'Barang tidak boleh kosong!']);
          exit();
      }
      
      try {
          $cek = $this->db('rsns_custom_logistik_non_medis_rencana_rutin')->where('no_rencana', $no_rencana)->oneArray();
          if ($cek) {
              $this->db()->pdo()->exec("UPDATE rsns_custom_logistik_non_medis_rencana_rutin SET bulan='$bulan', tahun='$tahun', tanggal_buat='$tanggal_buat', keterangan='$keterangan' WHERE no_rencana='$no_rencana'");
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_rencana_rutin_detail WHERE no_rencana='$no_rencana'");
          } else {
              $this->db()->pdo()->exec("INSERT INTO rsns_custom_logistik_non_medis_rencana_rutin (no_rencana, tahun, bulan, tanggal_buat, keterangan, status) VALUES ('$no_rencana', '$tahun', '$bulan', '$tanggal_buat', '$keterangan', 'Draft')");
          }
          
          $stmt = $this->db()->pdo()->prepare("INSERT INTO rsns_custom_logistik_non_medis_rencana_rutin_detail (no_rencana, kode_item, qty_rencana, estimasi_harga) VALUES (?, ?, ?, ?)");
          foreach ($items as $item) {
              $kode = $item['kode_item'];
              $qty = floatval($item['qty_rencana']);
              $harga = floatval($item['estimasi_harga']);
              if($qty > 0 && !empty($kode)) {
                  $stmt->execute([$no_rencana, $kode, $qty, $harga]);
              }
          }
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function anyDetailRencanaRutin()
  {
      $id = $_POST['id'] ?? '';
      $rencana = $this->db('rsns_custom_logistik_non_medis_rencana_rutin')->where('no_rencana', $id)->oneArray();
      
      $sql_det = "SELECT d.*, b.nama_barang, b.satuan_dasar as satuan
                  FROM rsns_custom_logistik_non_medis_rencana_rutin_detail d
                  LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = d.kode_item
                  WHERE d.no_rencana = '$id'";
      $details = $this->db()->pdo()->query($sql_det)->fetchAll(\PDO::FETCH_ASSOC);
      
      $total = 0;
      foreach ($details as $d) {
          $total += ($d['qty_rencana'] * $d['estimasi_harga']);
      }
      
      $this->tpl->set('rencana', $rencana);
      $this->tpl->set('details', $details);
      $this->tpl->set('total_estimasi', $total);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_rutin.detail.html', true);
      exit();
  }

  public function postHapusRencanaRutin()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          try {
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_rencana_rutin_detail WHERE no_rencana = '$id'");
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_rencana_rutin WHERE no_rencana = '$id'");
          } catch(\Exception $e) {}
      }
      exit();
  }

  public function postApproveRencanaRutin()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          try {
              $this->db()->pdo()->exec("UPDATE rsns_custom_logistik_non_medis_rencana_rutin SET status = 'Disetujui' WHERE no_rencana = '$id'");
          } catch(\Exception $e) {}
      }
      exit();
  }
'''

# Find the end of Admin.php class
idx = c.rfind('}')
c = c[:idx] + new_methods + "\n}"

with open(path, 'w', encoding='utf-8') as f: f.write(c)
