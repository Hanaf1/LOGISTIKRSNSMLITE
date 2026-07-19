import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

new_methods = '''
  // =========================================================
  // MODUL PENERIMAAN BARANG RUTIN
  // =========================================================
  public function anyTerimaRutin()
  {
      $cari = $_GET['cari'] ?? '';
      $where = "";
      if($cari) {
          $where = "WHERE t.no_terima LIKE '%$cari%' OR t.no_faktur LIKE '%$cari%' OR t.no_rencana LIKE '%$cari%'";
      }
      
      $query = "SELECT t.*, 
                v.nama_rekanan as nama_vendor,
                (SELECT SUM(d.total) FROM rsns_custom_logistik_non_medis_terima_rutin_detail d WHERE d.no_terima = t.no_terima) as total_pembelian
                FROM rsns_custom_logistik_non_medis_terima_rutin t
                LEFT JOIN rsns_custom_logistik_non_medis_master_rekanan_jasa v ON v.kode_rekanan = t.kode_vendor
                $where
                ORDER BY t.tanggal_terima DESC";
                
      $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
      $terima_rutin = [];
      foreach ($rows as $i => $row) {
          $row['no'] = $i + 1;
          $row['total_pembelian'] = number_format($row['total_pembelian'] ?? 0, 0, ',', '.');
          $terima_rutin[] = $row;
      }
      
      $this->tpl->set('terima_rutin', $terima_rutin);
      $this->tpl->set('cari', $cari);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.terima_rutin.html', true);
      exit();
  }

  public function anyFormTerimaRutin()
  {
      // Kita butuh list Rencana Rutin yang statusnya Disetujui
      $sql_rencana = "SELECT no_rencana, bulan, tahun FROM rsns_custom_logistik_non_medis_rencana_rutin WHERE status = 'Disetujui' ORDER BY no_rencana DESC";
      $list_rencana = $this->db()->pdo()->query($sql_rencana)->fetchAll(\PDO::FETCH_ASSOC);
      
      $sql_vendor = "SELECT kode_rekanan, nama_rekanan FROM rsns_custom_logistik_non_medis_master_rekanan_jasa ORDER BY nama_rekanan ASC";
      $list_vendor = $this->db()->pdo()->query($sql_vendor)->fetchAll(\PDO::FETCH_ASSOC);
      
      $this->core->addJS(url('assets/jscripts/select2.min.js'));
      $this->core->addCSS(url('assets/css/select2.min.css'));
      $this->tpl->set('list_rencana', $list_rencana);
      $this->tpl->set('list_vendor', $list_vendor);
      $this->tpl->set('no_terima_auto', 'TRM-'.date('YmdHis'));
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.terima_rutin.form.html', true);
      exit();
  }
  
  public function postAjaxGetRencanaRutin()
  {
      $no_rencana = $_POST['no_rencana'] ?? '';
      $sql = "SELECT d.kode_item, b.nama_barang, b.satuan_dasar as satuan, d.qty_rencana, d.qty_realisasi, d.estimasi_harga
              FROM rsns_custom_logistik_non_medis_rencana_rutin_detail d
              JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = d.kode_item
              WHERE d.no_rencana = '$no_rencana' AND d.qty_rencana > d.qty_realisasi";
      $data = $this->db()->pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
      echo json_encode($data);
      exit();
  }

  public function postSaveTerimaRutin()
  {
      $no_terima = $_POST['no_terima'] ?? '';
      $no_rencana = $_POST['no_rencana'] ?? '';
      $tanggal_terima = $_POST['tanggal_terima'] ?? '';
      $no_faktur = $_POST['no_faktur'] ?? '';
      $kode_vendor = $_POST['kode_vendor'] ?? '';
      $keterangan = $_POST['keterangan'] ?? '';
      $items = $_POST['items'] ?? [];
      
      if(empty($items)) {
          echo json_encode(['status' => 'error', 'message' => 'Detail barang kosong!']);
          exit();
      }
      
      try {
          $this->db()->pdo()->beginTransaction();
          
          $this->db()->pdo()->exec("INSERT INTO rsns_custom_logistik_non_medis_terima_rutin (no_terima, no_rencana, tanggal_terima, no_faktur, kode_vendor, keterangan) VALUES ('$no_terima', '$no_rencana', '$tanggal_terima', '$no_faktur', '$kode_vendor', '$keterangan')");
          
          $stmt_det = $this->db()->pdo()->prepare("INSERT INTO rsns_custom_logistik_non_medis_terima_rutin_detail (no_terima, kode_item, qty_terima, harga_beli, total) VALUES (?, ?, ?, ?, ?)");
          
          $stmt_stock = $this->db()->pdo()->prepare("UPDATE rsns_custom_logistik_non_medis_master_barang SET stok = stok + ? WHERE kode_item = ?");
          
          $stmt_realisasi = $this->db()->pdo()->prepare("UPDATE rsns_custom_logistik_non_medis_rencana_rutin_detail SET qty_realisasi = qty_realisasi + ? WHERE no_rencana = ? AND kode_item = ?");
          
          foreach($items as $item) {
              $kode = $item['kode_item'];
              $qty = floatval($item['qty_terima']);
              $harga = floatval($item['harga_beli']);
              $total = $qty * $harga;
              
              if($qty > 0) {
                  // 1. Simpan detail terima
                  $stmt_det->execute([$no_terima, $kode, $qty, $harga, $total]);
                  // 2. Tambah Stok Gudang
                  $stmt_stock->execute([$qty, $kode]);
                  // 3. Update Realisasi di Rencana (jika ada no_rencana)
                  if(!empty($no_rencana)) {
                      $stmt_realisasi->execute([$qty, $no_rencana, $kode]);
                  }
              }
          }
          
          $this->db()->pdo()->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $this->db()->pdo()->rollBack();
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function postHapusTerimaRutin()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          try {
              $this->db()->pdo()->beginTransaction();
              
              $terima = $this->db('rsns_custom_logistik_non_medis_terima_rutin')->where('no_terima', $id)->oneArray();
              $details = $this->db('rsns_custom_logistik_non_medis_terima_rutin_detail')->where('no_terima', $id)->toArray();
              
              $stmt_stock = $this->db()->pdo()->prepare("UPDATE rsns_custom_logistik_non_medis_master_barang SET stok = stok - ? WHERE kode_item = ?");
              $stmt_realisasi = $this->db()->pdo()->prepare("UPDATE rsns_custom_logistik_non_medis_rencana_rutin_detail SET qty_realisasi = qty_realisasi - ? WHERE no_rencana = ? AND kode_item = ?");
              
              foreach($details as $d) {
                  // Rollback Stock
                  $stmt_stock->execute([$d['qty_terima'], $d['kode_item']]);
                  // Rollback Realisasi
                  if(!empty($terima['no_rencana'])) {
                      $stmt_realisasi->execute([$d['qty_terima'], $terima['no_rencana'], $d['kode_item']]);
                  }
              }
              
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_terima_rutin_detail WHERE no_terima = '$id'");
              $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_terima_rutin WHERE no_terima = '$id'");
              
              $this->db()->pdo()->commit();
          } catch(\Exception $e) {
              $this->db()->pdo()->rollBack();
          }
      }
      exit();
  }

  public function anyDetailTerimaRutin()
  {
      $id = $_POST['id'] ?? '';
      $terima = $this->db('rsns_custom_logistik_non_medis_terima_rutin')->where('no_terima', $id)->oneArray();
      
      if($terima['kode_vendor']) {
          $vendor = $this->db('rsns_custom_logistik_non_medis_master_rekanan_jasa')->where('kode_rekanan', $terima['kode_vendor'])->oneArray();
          $terima['nama_vendor'] = $vendor['nama_rekanan'] ?? '-';
      }
      
      $sql_det = "SELECT d.*, b.nama_barang, b.satuan_dasar as satuan
                  FROM rsns_custom_logistik_non_medis_terima_rutin_detail d
                  LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = d.kode_item
                  WHERE d.no_terima = '$id'";
      $details = $this->db()->pdo()->query($sql_det)->fetchAll(\PDO::FETCH_ASSOC);
      
      $total = 0;
      foreach ($details as $d) {
          $total += $d['total'];
      }
      
      $this->tpl->set('terima', $terima);
      $this->tpl->set('details', $details);
      $this->tpl->set('total_pembelian', $total);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.terima_rutin.detail.html', true);
      exit();
  }
'''

idx = c.rfind('}')
c = c[:idx] + new_methods + "\n}"

with open(path, 'w', encoding='utf-8') as f: f.write(c)
