import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

new_methods = '''
  // =========================================================
  // MODUL RENCANA PEMBELIAN ASET & NON RUTIN
  // =========================================================
  public function anyRencanaNonRutin()
  {
      $tahun = $_GET['tahun'] ?? date('Y');
      
      $query = "SELECT r.*, 
                (SELECT SUM(d.qty_rencana * d.estimasi_harga) FROM rsns_custom_logistik_non_medis_rencana_nonrutin_detail d WHERE d.no_rencana = r.no_rencana) as total_estimasi
                FROM rsns_custom_logistik_non_medis_rencana_nonrutin r 
                WHERE r.tahun = '$tahun' 
                ORDER BY r.tanggal_buat DESC";
                
      $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
      $rencana_nonrutin = [];
      foreach ($rows as $i => $row) {
          $row['no'] = $i + 1;
          $row['total_estimasi'] = number_format($row['total_estimasi'] ?? 0, 0, ',', '.');
          $rencana_nonrutin[] = $row;
      }
      
      $this->tpl->set('rencana_nonrutin', $rencana_nonrutin);
      $this->tpl->set('tahun', $tahun);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_nonrutin.html', true);
      exit();
  }

  public function anyFormRencanaNonRutin()
  {
      $id = $_POST['id'] ?? '';
      $rencana = [
          'no_rencana' => 'RNR-'.date('YmdHis'),
          'tahun' => date('Y'),
          'tanggal_buat' => date('Y-m-d'),
          'keterangan' => ''
      ];
      $details = [];
      
      if ($id) {
          $rencana = $this->db('rsns_custom_logistik_non_medis_rencana_nonrutin')->where('no_rencana', $id)->oneArray();
          
          $sql_det = "SELECT * FROM rsns_custom_logistik_non_medis_rencana_nonrutin_detail WHERE no_rencana = '$id'";
          $details = $this->db()->pdo()->query($sql_det)->fetchAll(\PDO::FETCH_ASSOC);
      }
      
      $this->tpl->set('id', $id);
      $this->tpl->set('rencana', $rencana);
      $this->tpl->set('details', $details);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_nonrutin.form.html', true);
      exit();
  }

  public function postSaveRencanaNonRutin()
  {
      $no_rencana = $_POST['no_rencana'] ?? '';
      $tahun = $_POST['tahun'] ?? '';
      $tanggal_buat = $_POST['tanggal_buat'] ?? '';
      $keterangan = $_POST['keterangan'] ?? '';
      $items = $_POST['items'] ?? [];
      
      if (empty($items)) {
          echo json_encode(['status' => 'error', 'message' => 'Barang tidak boleh kosong!']);
          exit();
      }
      
      try {
          $cek = $this->db('rsns_custom_logistik_non_medis_rencana_nonrutin')->where('no_rencana', $no_rencana)->oneArray();
          if ($cek) {
              $this->db()->pdo()->exec("UPDATE rsns_custom_logistik_non_medis_rencana_nonrutin SET tahun='$tahun', tanggal_buat='$tanggal_buat', keterangan='$keterangan' WHERE no_rencana='$no_rencana'");
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_rencana_nonrutin_detail WHERE no_rencana='$no_rencana'");
          } else {
              $this->db()->pdo()->exec("INSERT INTO rsns_custom_logistik_non_medis_rencana_nonrutin (no_rencana, tahun, tanggal_buat, keterangan, status) VALUES ('$no_rencana', '$tahun', '$tanggal_buat', '$keterangan', 'Draft')");
          }
          
          $stmt = $this->db()->pdo()->prepare("INSERT INTO rsns_custom_logistik_non_medis_rencana_nonrutin_detail (no_rencana, nama_barang, kategori, qty_rencana, estimasi_harga, alasan) VALUES (?, ?, ?, ?, ?, ?)");
          foreach ($items as $item) {
              $nama = $item['nama_barang'];
              $kat = $item['kategori'];
              $qty = floatval($item['qty_rencana']);
              $harga = floatval($item['estimasi_harga']);
              $alasan = $item['alasan'];
              if($qty > 0 && !empty($nama)) {
                  $stmt->execute([$no_rencana, $nama, $kat, $qty, $harga, $alasan]);
              }
          }
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function anyDetailRencanaNonRutin()
  {
      $id = $_POST['id'] ?? '';
      $rencana = $this->db('rsns_custom_logistik_non_medis_rencana_nonrutin')->where('no_rencana', $id)->oneArray();
      
      $sql_det = "SELECT * FROM rsns_custom_logistik_non_medis_rencana_nonrutin_detail WHERE no_rencana = '$id'";
      $details = $this->db()->pdo()->query($sql_det)->fetchAll(\PDO::FETCH_ASSOC);
      
      $total = 0;
      foreach ($details as $d) {
          $total += ($d['qty_rencana'] * $d['estimasi_harga']);
      }
      
      $this->tpl->set('rencana', $rencana);
      $this->tpl->set('details', $details);
      $this->tpl->set('total_estimasi', $total);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_nonrutin.detail.html', true);
      exit();
  }

  public function postHapusRencanaNonRutin()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          try {
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_rencana_nonrutin_detail WHERE no_rencana = '$id'");
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_rencana_nonrutin WHERE no_rencana = '$id'");
          } catch(\Exception $e) {}
      }
      exit();
  }

  public function postApproveRencanaNonRutin()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          try {
              $this->db()->pdo()->exec("UPDATE rsns_custom_logistik_non_medis_rencana_nonrutin SET status = 'Disetujui' WHERE no_rencana = '$id'");
          } catch(\Exception $e) {}
      }
      exit();
  }
'''

idx = c.rfind('}')
c = c[:idx] + new_methods + "\n}"

with open(path, 'w', encoding='utf-8') as f: f.write(c)
