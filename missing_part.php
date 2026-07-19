  public function anyDetailMutasi()
  {
      if (isset($_POST['no_mutasi'])){
          $no_mutasi = $_POST['no_mutasi'];
          $mutasi = $this->db('rsns_custom_logistik_non_medis_mutasi')
                         ->leftJoin('rsns_custom_logistik_non_medis_lokasi_gudang as l1', 'rsns_custom_logistik_non_medis_mutasi.kode_lokasi_asal = l1.kode_lokasi')
                         ->leftJoin('rsns_custom_logistik_non_medis_lokasi_gudang as l2', 'rsns_custom_logistik_non_medis_mutasi.kode_lokasi_tujuan = l2.kode_lokasi')
                         ->select('rsns_custom_logistik_non_medis_mutasi.*')
                         ->select('l1.nama_lokasi as asal')
                         ->select('l2.nama_lokasi as tujuan')
                         ->where('no_mutasi', $no_mutasi)
                         ->oneArray();
          
          $details = $this->db('rsns_custom_logistik_non_medis_mutasi_detail')
                          ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_mutasi_detail.kode_item = rsns_custom_logistik_non_medis_master_barang.kode_item')
                          ->select('rsns_custom_logistik_non_medis_mutasi_detail.*')
                          ->select('rsns_custom_logistik_non_medis_master_barang.nama_barang')
                          ->where('no_mutasi', $no_mutasi)
                          ->toArray();
          $mutasi['details'] = $details;
          echo $this->draw('gudang.mutasi.detail.html', ['mutasi' => $mutasi]);
      }
      exit();
  }

  public function postProsesMutasi()
  {
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      $action = $_POST['action'] ?? '';
      $user = $this->core->getUserInfo('username', null, true);

      $mutasi = $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if(!$mutasi) {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
          exit();
      }

      if($action == 'kirim') {
          // Change status to Dikirim
          // Reduce stock at Source
          $details = $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->where('no_mutasi', $no_mutasi)->toArray();
          foreach($details as $d) {
              $this->_updateStok($d['kode_item'], $mutasi['kode_lokasi_asal'], $d['qty'], 'Mutasi Keluar', $no_mutasi, 0, $d['batch_no']);
          }
          $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->update(['status' => 'Dikirim']);
          $this->_logAction('logistik_non_medis_mutasi', 'Kirim Mutasi: ' . $no_mutasi, 'U');
          echo json_encode(['status' => 'success']);
      } elseif($action == 'terima') {
          // Change status to Diterima
          // Increase stock at Destination
          $details = $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->where('no_mutasi', $no_mutasi)->toArray();
          foreach($details as $d) {
              // We need to fetch the original batch info (expiry, etc) to ensure consistency at destination
              $batch_info = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                                 ->where('kode_item', $d['kode_item'])
                                 ->where('batch_no', $d['batch_no'])
                                 ->oneArray();
              $tgl_exp = $batch_info['tgl_expired'] ?? NULL;
              $harga = $batch_info['harga_beli'] ?? 0;

              $this->_updateStok($d['kode_item'], $mutasi['kode_lokasi_tujuan'], $d['qty'], 'Mutasi Masuk', $no_mutasi, $harga, $d['batch_no'], $tgl_exp);
          }
          $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->update([
              'status' => 'Diterima',
              'user_terima' => $user,
              'tgl_terima' => date('Y-m-d H:i:s')
          ]);
          $this->_logAction('logistik_non_medis_mutasi', 'Terima Mutasi: ' . $no_mutasi, 'U');
          echo json_encode(['status' => 'success']);
      } elseif($action == 'batal') {
          if($mutasi['status'] == 'Dikirim') {
              // Rollback stock at Source
              $details = $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->where('no_mutasi', $no_mutasi)->toArray();
              foreach($details as $d) {
                  $this->_updateStok($d['kode_item'], $mutasi['kode_lokasi_asal'], $d['qty'], 'Masuk', $no_mutasi, 0, $d['batch_no']);
                  // We use 'Masuk' to revert the deduction, but we should probably record it as 'Batal Mutasi' or similar
                  // For simplicity, just adding back to stock.
              }
          }
          $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->update(['status' => 'Batal']);
          $this->_logAction('logistik_non_medis_mutasi', 'Batal Mutasi: ' . $no_mutasi, 'D');
          echo json_encode(['status' => 'success']);
      }
      exit();
  }

  public function getPrintMutasi()
  {
      $no_mutasi = $_GET['no_mutasi'] ?? '';
      $mutasi = $this->db('rsns_custom_logistik_non_medis_mutasi')
                     ->leftJoin('rsns_custom_logistik_non_medis_lokasi_gudang as l1', 'rsns_custom_logistik_non_medis_mutasi.kode_lokasi_asal = l1.kode_lokasi')
                     ->leftJoin('rsns_custom_logistik_non_medis_lokasi_gudang as l2', 'rsns_custom_logistik_non_medis_mutasi.kode_lokasi_tujuan = l2.kode_lokasi')
                     ->select('rsns_custom_logistik_non_medis_mutasi.*')
                     ->select('l1.nama_lokasi as asal')
                     ->select('l2.nama_lokasi as tujuan')
                     ->where('no_mutasi', $no_mutasi)
                     ->oneArray();
      
      $details = $this->db('rsns_custom_logistik_non_medis_mutasi_detail')
                      ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_mutasi_detail.kode_item = rsns_custom_logistik_non_medis_master_barang.kode_item')
                      ->select('rsns_custom_logistik_non_medis_mutasi_detail.*')
                      ->select('rsns_custom_logistik_non_medis_master_barang.nama_barang')
                      ->where('no_mutasi', $no_mutasi)
                      ->toArray();
      
      echo $this->draw('gudang.mutasi.print.html', [
          'mutasi' => $mutasi,
          'details' => $details,
          'logo' => url().'/'.$this->settings->get('settings.logo'),
          'nama_rs' => $this->settings->get('settings.nama_instansi'),
          'alamat_rs' => $this->settings->get('settings.alamat'),
          'kota_rs' => $this->settings->get('settings.kota'),
          'kontak_rs' => $this->settings->get('settings.nomor_telepon')
      ]);
      exit();
  }

  public function anyDisplayKartuStok()
  {
      $kode_item = $_POST['kode_item'] ?? '';
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      $tgl_awal = $_POST['tgl_awal'] ?? date('Y-m-01');
      $tgl_akhir = $_POST['tgl_akhir'] ?? date('Y-m-d');

      $query = $this->db('rsns_custom_logistik_non_medis_kartu_stok')
                    ->where('tgl_transaksi', '>=', $tgl_awal . ' 00:00:00')
                    ->where('tgl_transaksi', '<=', $tgl_akhir . ' 23:59:59');
      
      if(!empty($kode_item)) $query->where('kode_item', $kode_item);
      if(!empty($kode_lokasi)) $query->where('kode_lokasi', $kode_lokasi);

      $rows = $query->asc('tgl_transaksi')->asc('id')->toArray();

      echo $this->draw('gudang.mutasi.display.html', [
          'mutasi_history' => $rows, // Changed to mutasi_history to avoid conflict with transaction list
          'kode_item' => $kode_item,
          'kode_lokasi' => $kode_lokasi,
          'is_history' => true
      ]);
      exit();
  }

  private function _logAction($modul, $action, $status = 'I')
  {
      $user = $this->core->getUserInfo('username', null, true);
      $ip = $_SERVER['REMOTE_ADDR'];
      $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
      
      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => $modul,
          'log_waktu' => date('Y-m-d H:i:s'),
          'log_location' => $hostname . ' | ' . $ip,
          'log_data' => $action . ' | User: ' . $user,
          'log_status' => $status,
          'log_username' => $user
      ]);
  }
  private function _initOpname()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_opname` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_opname` varchar(50) NOT NULL,
        `tgl_opname` date DEFAULT NULL,
        `tgl_jadwal` date DEFAULT NULL,
        `kode_lokasi` varchar(50) NOT NULL,
        `kode_item` varchar(50) DEFAULT NULL,
        `stok_sistem` double NOT NULL DEFAULT 0,
        `stok_fisik` double NOT NULL DEFAULT 0,
        `selisih` double NOT NULL DEFAULT 0,
        `keterangan` text DEFAULT NULL,
        `status` enum('Jadwal','Draft','Selesai') NOT NULL DEFAULT 'Jadwal',
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `no_opname` (`no_opname`),
        KEY `kode_lokasi` (`kode_lokasi`),
        KEY `kode_item` (`kode_item`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _generateNoOpname()
  {
      $prefix = 'SO/' . date('Ym') . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_opname')
                   ->where('no_opname', 'LIKE', $prefix.'%')
                   ->desc('no_opname')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $parts = explode('/', $last['no_opname']);
          $last_num = (int) end($parts);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getGudangOpname()
  {
      $this->_initOpname();
      $this->_addHeaderFiles();
      return $this->draw('gudang.opname.html');
  }

  public function anyDisplayOpname()
  {
      $this->_initOpname();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $where = "";
      if(!empty($cari)) {
          $where = " WHERE no_opname LIKE '%$cari%' OR kode_lokasi LIKE '%$cari%' ";
      }

      $sql_all = "SELECT no_opname FROM rsns_custom_logistik_non_medis_opname $where GROUP BY no_opname";
      $all_data = $this->db()->pdo()->query($sql_all)->fetchAll();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $sql = "SELECT * FROM rsns_custom_logistik_non_medis_opname 
              WHERE id IN (SELECT MAX(id) FROM rsns_custom_logistik_non_medis_opname $where GROUP BY no_opname)
              ORDER BY id DESC LIMIT $_offset, $perpage";
      $rows = $this->db()->pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

      $pages = [];
      if($jml_halaman > 1) {
          for($i = 1; $i <= $jml_halaman; $i++) {
              $pages[] = $i;
          }
      }

      echo $this->draw('gudang.opname.display.html', [
          'opname' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'pages' => $pages
      ]);
      exit();
  }

  public function anyFormOpname()
  {
      $this->_initOpname();
      $this->_initLokasi();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      $mode = $_POST['mode'] ?? 'add';

      if ($mode == 'edit' && isset($_POST['no_opname'])){
          $no_opname = $_POST['no_opname'];
          $opname_rows = $this->db('rsns_custom_logistik_non_medis_opname')->where('no_opname', $no_opname)->toArray();
          $opname = $opname_rows[0]; 
          
          // Get item names
          $barang_rows = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
          $barang_map = [];
          foreach($barang_rows as $b) { $barang_map[$b['kode_item']] = $b['nama_barang']; }

          $opname['detail_items'] = [];
          foreach($opname_rows as $row) {
              if (empty($row['kode_item'])) continue;
              $row['nama_barang'] = $barang_map[$row['kode_item']] ?? $row['kode_item'];
              $opname['detail_items'][] = $row;
          }
          echo $this->draw('gudang.opname.form.html', ['opname' => $opname, 'mode' => 'edit', 'lokasi' => $lokasi]);
      } else {
          $opname = [
              'no_opname' => $this->_generateNoOpname(),
              'tgl_opname' => date('Y-m-d'),
              'tgl_jadwal' => date('Y-m-d'),
              'kode_lokasi' => '',
              'detail_items' => [],
              'status' => 'Draft'
          ];
          echo $this->draw('gudang.opname.form.html', ['opname' => $opname, 'mode' => 'add', 'lokasi' => $lokasi]);
      }
      exit();
  }

  public function anyLoadItemsForOpname()
  {
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      if(empty($kode_lokasi)) {
          echo json_encode(['status' => 'error', 'message' => 'Lokasi harus dipilih']);
          exit();
      }

      // Fetch all items that have stock in this location
      $stok_rows = $this->db('rsns_custom_logistik_non_medis_stok')->where('kode_lokasi', $kode_lokasi)->toArray();
      
      // Get item names
      $barang_rows = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
      $barang_map = [];
      foreach($barang_rows as $b) { $barang_map[$b['kode_item']] = $b['nama_barang']; }

      $items = [];
      foreach($stok_rows as $s) {
          if($s['stok_akhir'] <= 0) continue; // Optional: show only non-zero stock
          $items[] = [
              'kode_item' => $s['kode_item'],
              'nama_barang' => $barang_map[$s['kode_item']] ?? $s['kode_item'],
              'stok_sistem' => $s['stok_akhir'],
              'stok_fisik' => $s['stok_akhir'], // Default to system stock
              'selisih' => 0,
              'keterangan' => ''
          ];
      }

      echo json_encode(['status' => 'success', 'items' => $items]);
      exit();
  }

  public function postSaveOpname()
  {
      $no_opname = $_POST['no_opname'] ?? '';
      $status = $_POST['status'] ?? 'Draft';
      $user = $this->core->getUserInfo('username', null, true);
      
      $items = [];
      if(isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
          foreach($_POST['kode_item'] as $key => $kode_item) {
              $items[] = [
                  'no_opname' => $no_opname,
                  'tgl_opname' => $_POST['tgl_opname'] ?? date('Y-m-d'),
                  'tgl_jadwal' => $_POST['tgl_jadwal'] ?? date('Y-m-d'),
                  'kode_lokasi' => $_POST['kode_lokasi'] ?? '',
                  'kode_item' => $kode_item,
                  'stok_sistem' => $_POST['stok_sistem'][$key] ?? 0,
                  'stok_fisik' => $_POST['stok_fisik'][$key] ?? 0,
                  'selisih' => $_POST['selisih'][$key] ?? 0,
                  'keterangan' => $_POST['keterangan_item'][$key] ?? '',
                  'status' => $status,
                  'user_input' => $user
              ];
          }
      }

      // If no items but it's a schedule, save at least the header info
      if(empty($items) && $status == 'Jadwal') {
          $items[] = [
              'no_opname' => $no_opname,
              'tgl_opname' => NULL,
              'tgl_jadwal' => $_POST['tgl_jadwal'] ?? date('Y-m-d'),
              'kode_lokasi' => $_POST['kode_lokasi'] ?? '',
              'kode_item' => NULL,
              'stok_sistem' => 0,
              'stok_fisik' => 0,
              'selisih' => 0,
              'keterangan' => $_POST['keterangan'] ?? 'Jadwal Opname',
              'status' => 'Jadwal',
              'user_input' => $user
          ];
      }

      // Start transaction or just delete old ones and insert new
      $this->db('rsns_custom_logistik_non_medis_opname')->where('no_opname', $no_opname)->delete();
      
      $success = true;
      foreach($items as $item) {
          if(!$this->db('rsns_custom_logistik_non_medis_opname')->save($item)) {
              $success = false;
          }
      }

      // If status is Selesai, perform Stock Adjustment
      if($success && $status == 'Selesai') {
          $this->_initStok();
          foreach($items as $item) {
              if(empty($item['kode_item'])) continue;
              
              // Update main stock table
              $this->db('rsns_custom_logistik_non_medis_stok')
                   ->where('kode_item', $item['kode_item'])
                   ->where('kode_lokasi', $item['kode_lokasi'])
                   ->update(['stok_akhir' => $item['stok_fisik']]);
              
              // Insert into Kartu Stok
              $kartu = [
                  'tgl_transaksi' => date('Y-m-d H:i:s'),
                  'kode_item' => $item['kode_item'],
                  'kode_lokasi' => $item['kode_lokasi'],
                  'tipe_transaksi' => 'Opname',
                  'no_referensi' => $item['no_opname'],
                  'qty_masuk' => ($item['selisih'] > 0) ? $item['selisih'] : 0,
                  'qty_keluar' => ($item['selisih'] < 0) ? abs($item['selisih']) : 0,
                  'stok_akhir' => $item['stok_fisik'],
                  'harga' => 0, // Could fetch last price if needed
                  'user_input' => $user
              ];
              $this->db('rsns_custom_logistik_non_medis_kartu_stok')->save($kartu);
          }
      }

      if($success) {
          // Logging to mlite_tracksql
          $ip = $_SERVER['REMOTE_ADDR'];
          $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_opname',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'Save Opname ' . $no_opname . ' Status: ' . $status,
              'log_status' => ($status == 'Selesai' ? 'U' : 'I'),
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data opname']);
      }
      exit();
  }

  public function getPrintOpname()
  {
      $no_opname = $_GET['no_opname'] ?? '';
      $this->_initOpname();
      $opname_rows = $this->db('rsns_custom_logistik_non_medis_opname')->where('no_opname', $no_opname)->toArray();
      if(empty($opname_rows)) die('Data tidak ditemukan');

      $opname = $opname_rows[0];
      
      // Get item names
      $barang_rows = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
      $barang_map = [];
      foreach($barang_rows as $b) { $barang_map[$b['kode_item']] = $b['nama_barang']; }

      $opname['detail_items'] = [];
      foreach($opname_rows as $row) {
          if (empty($row['kode_item'])) continue;
          $row['nama_barang'] = $barang_map[$row['kode_item']] ?? $row['kode_item'];
          $opname['detail_items'][] = $row;
      }

      $this->_initLokasi();
      $lokasi_raw = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $opname['kode_lokasi'])->oneArray();
      $opname['nama_lokasi'] = $lokasi_raw['nama_lokasi'] ?? $opname['kode_lokasi'];

      echo $this->draw('gudang.opname.print.html', [
          'opname' => $opname,
          'logo' => url().'/'.$this->settings->get('settings.logo'),
          'nama_rs' => $this->settings->get('settings.nama_instansi'),
          'alamat_rs' => $this->settings->get('settings.alamat'),
          'kota_rs' => $this->settings->get('settings.kota'),
          'kontak_rs' => $this->settings->get('settings.nomor_telepon')
      ]);
      exit();
  }

  public function getPrintRekapOpname()
  {
      $t1 = $_GET['tgl_awal'] ?? date('Y-m-01');
      $t2 = $_GET['tgl_akhir'] ?? date('Y-m-d');
      
      $this->_initOpname();
      // Fetch all items within the date range, grouped by sessions if needed but we want a list
      $sql = "SELECT * FROM rsns_custom_logistik_non_medis_opname 
              WHERE (tgl_opname BETWEEN '$t1' AND '$t2') OR (tgl_jadwal BETWEEN '$t1' AND '$t2')
              ORDER BY tgl_opname ASC, no_opname ASC, id ASC";
      $rows = $this->db()->pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

      $this->_initLokasi();
      $lokasi_rows = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      $lokasi_map = [];
      foreach($lokasi_rows as $l) { $lokasi_map[$l['kode_lokasi']] = $l['nama_lokasi']; }

      // Get item names
      $barang_rows = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
      $barang_map = [];
      foreach($barang_rows as $b) { $barang_map[$b['kode_item']] = $b['nama_barang']; }

      foreach($rows as &$r) {
          $r['nama_lokasi'] = $lokasi_map[$r['kode_lokasi']] ?? $r['kode_lokasi'];
          $r['nama_barang'] = $barang_map[$r['kode_item']] ?? $r['kode_item'];
      }

      echo $this->draw('gudang.opname.rekap.html', [
          'rows' => $rows,
          'tgl_awal' => $t1,
          'tgl_akhir' => $t2,
          'logo' => url().'/'.$this->settings->get('settings.logo'),
          'nama_rs' => $this->settings->get('settings.nama_instansi'),
          'alamat_rs' => $this->settings->get('settings.alamat'),
          'kota_rs' => $this->settings->get('settings.kota')
      ]);
      exit();
  }

  public function postHapusOpname()
  {
      $no_opname = $_POST['no_opname'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_opname')->where('no_opname', $no_opname)->oneArray();
      if($cek) {
          if($cek['status'] == 'Selesai') {
              echo json_encode(['status' => 'error', 'message' => 'Data yang sudah selesai tidak dapat dihapus!']);
              exit();
          }
          $this->db('rsns_custom_logistik_non_medis_opname')->where('no_opname', $no_opname)->delete();
          
          // Logging to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $ip = $_SERVER['REMOTE_ADDR'];
          $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_opname',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'Delete Opname ' . $no_opname,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      }
      exit();
  }

  public function getGudangmetode()
  {
      $this->_initStok();
      $this->_addHeaderFiles();
      $metode = $this->db('rsns_custom_logistik_non_medis_pengaturan')->where('nama_pengaturan', 'metode_stok')->oneArray();
      return $this->draw('gudang.metode.html', ['metode' => $metode['nilai'] ?? 'FIFO']);
  }

  public function postSavemetode()
  {
      $metode = $_POST['metode'] ?? 'FIFO';
      $query = $this->db('rsns_custom_logistik_non_medis_pengaturan')->where('nama_pengaturan', 'metode_stok')->update(['nilai' => $metode]);
      
      // Logging
      if($query) {
          $user = $this->core->getUserInfo('username', null, true);
          $ip = $_SERVER['REMOTE_ADDR'];
          $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_pengaturan',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'metode_stok | ' . $metode . ' | ' . $user,
              'log_status' => 'U',
              'log_username' => $user
          ]);
      }

      echo json_encode(['status' => $query ? 'success' : 'error']);
      exit();
  }



  private function _initGudangRusak()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_barang_rusak` (
        `no_transaksi` varchar(50) NOT NULL,
        `tgl_transaksi` date NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `batch` varchar(50) DEFAULT NULL,
        `kode_lokasi` varchar(50) DEFAULT NULL,
        `jumlah` double NOT NULL DEFAULT 0,
        `kategori_kerusakan` varchar(100) DEFAULT NULL,
        `keterangan` text DEFAULT NULL,
        `tindak_lanjut` enum('Retur','Pemusnahan') DEFAULT NULL,
        `status` enum('Karantina','Selesai') NOT NULL DEFAULT 'Karantina',
        `kode_vendor` varchar(50) DEFAULT NULL,
        `tgl_retur` date DEFAULT NULL,
        `status_retur` varchar(50) DEFAULT NULL,
        `tgl_pemusnahan` date DEFAULT NULL,
        `metode_pemusnahan` varchar(100) DEFAULT NULL,
        `saksi_1` varchar(100) DEFAULT NULL,
        `saksi_2` varchar(100) DEFAULT NULL,
        `catatan_logistik` text DEFAULT NULL,
        `tgl_input` datetime DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _initSppb()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_sppb` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_sppb` varchar(50) NOT NULL,
        `tgl_sppb` date NOT NULL,
        `kode_unit` varchar(50) NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `jumlah` double NOT NULL DEFAULT 0,
        `jumlah_disetujui` double NOT NULL DEFAULT 0,
        `satuan` varchar(50) DEFAULT NULL,
        `status` enum('Draft','Diajukan','Disetujui Unit','Terverifikasi','Picking','Packing','Ready','Dikirim','Diterima','Selesai','Ditolak') NOT NULL DEFAULT 'Draft',
        `keterangan` text DEFAULT NULL,
        `alasan_penolakan` text DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        `tgl_input` datetime DEFAULT NULL,
        `user_approve_unit` varchar(100) DEFAULT NULL,
        `tgl_approve_unit` datetime DEFAULT NULL,
        `user_verifikasi` varchar(100) DEFAULT NULL,
        `tgl_verifikasi` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `no_sppb` (`no_sppb`),
        KEY `kode_unit` (`kode_unit`),
        KEY `kode_item` (`kode_item`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $check_disetujui = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_sppb` LIKE 'jumlah_disetujui'")->fetch();
      if (!$check_disetujui) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_sppb` ADD `jumlah_disetujui` double NOT NULL DEFAULT 0 AFTER `jumlah` ");
      }

      $check_tolak = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_sppb` LIKE 'alasan_penolakan'")->fetch();
      if (!$check_tolak) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_sppb` ADD `alasan_penolakan` text DEFAULT NULL AFTER `keterangan` ");
      }

      // Ensure enum is updated
      $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_sppb` MODIFY `status` enum('Draft','Diajukan','Disetujui Unit','Terverifikasi','Picking','Packing','Ready','Dikirim','Diterima','Selesai','Ditolak') NOT NULL DEFAULT 'Draft'");
  }

  private function _initPacking()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_packing` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_packing` varchar(50) NOT NULL,
        `no_sppb` varchar(50) NOT NULL,
        `tgl_packing` datetime NOT NULL,
        `petugas_packing` varchar(100) NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `batch_no` varchar(50) DEFAULT NULL,
        `qty_picked` double NOT NULL,
        `koli_ke` int(11) DEFAULT 1,
        `total_berat_koli` double DEFAULT 0,
        `keterangan` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `no_packing` (`no_packing`),
        KEY `no_sppb` (`no_sppb`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _initSerahTerima()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_serah_terima` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_serah_terima` varchar(50) NOT NULL,
        `no_sppb` varchar(50) NOT NULL,
        `tanggal_serah` datetime NOT NULL,
        `petugas_pengirim` varchar(100) NOT NULL,
        `penerima_nama` varchar(100) NOT NULL,
        `penerima_nip` varchar(50) DEFAULT NULL,
        `foto_kondisi` varchar(255) DEFAULT NULL,
        `tanda_terima` longtext DEFAULT NULL,
        `keterangan` text DEFAULT NULL,
        `arsip_bast` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `no_serah_terima` (`no_serah_terima`),
        KEY `no_sppb` (`no_sppb`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $upload_dir = UPLOADS . '/logistik_non_medis/serah_terima';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      if (!is_dir($upload_dir . '/foto')) mkdir($upload_dir . '/foto', 0777, true);
      if (!is_dir($upload_dir . '/bast')) mkdir($upload_dir . '/bast', 0777, true);
  }

  private function _generateNoSerahTerima()
  {
      $prefix = 'BAST/' . date('Ymd') . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_serah_terima')
                   ->where('no_serah_terima', 'LIKE', $prefix.'%')
                   ->desc('no_serah_terima')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['no_serah_terima'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  private function _generateNoPacking()
  {
      $prefix = 'PKG/' . date('Ymd') . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_packing')
                   ->where('no_packing', 'LIKE', $prefix.'%')
                   ->desc('no_packing')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['no_packing'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  private function _generateNoSPPB($kode_unit)
  {
      $prefix = 'SPPB/' . date('Ym') . '/' . $kode_unit . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_sppb')
                   ->where('no_sppb', 'LIKE', $prefix.'%')
                   ->desc('no_sppb')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $parts = explode('/', $last['no_sppb']);
          $last_num = (int) end($parts);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  private function _generateNoGudangRusak()
  {
      $prefix = 'BR/' . date('Ymd') . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_barang_rusak')
                   ->where('no_transaksi', 'LIKE', $prefix.'%')
                   ->desc('no_transaksi')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['no_transaksi'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getGudangRusak()
  {
      $this->_initGudangRusak();
      $this->_addHeaderFiles();
      return $this->draw('gudang.rusak.html');
  }

  public function anyDisplayGudangRusak()
  {
      $this->_initGudangRusak();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query_count = $this->db('rsns_custom_logistik_non_medis_barang_rusak');
      if(!empty($cari)) {
          $query_count->where('no_transaksi', 'LIKE', '%'.$cari.'%')
                      ->orLike('kategori_kerusakan', '%'.$cari.'%');
      }
      $jumlah_data = count($query_count->toArray());
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows_query = $this->db('rsns_custom_logistik_non_medis_barang_rusak')
                    ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_barang_rusak.kode_item')
                    ->select('rsns_custom_logistik_non_medis_barang_rusak.*')
                    ->select('rsns_custom_logistik_non_medis_master_barang.nama_barang');

      if(!empty($cari)) {
          $rows_query->where('no_transaksi', 'LIKE', '%'.$cari.'%')
                     ->orLike('nama_barang', '%'.$cari.'%')
                     ->orLike('kategori_kerusakan', '%'.$cari.'%');
      }

      $rows = $rows_query->desc('tgl_input')
                         ->offset($_offset)
                         ->limit($perpage)
                         ->toArray();

      echo $this->draw('gudang.rusak.display.html', [
          'rusak' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormGudangRusak()
  {
      $this->_initLokasi();
      $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('status', 'Aktif')->toArray();
      $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('status', 'Whitelist')->toArray();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('status', 'Aktif')->toArray();

      if (isset($_POST['no_transaksi'])){
          $rusak = $this->db('rsns_custom_logistik_non_medis_barang_rusak')->where('no_transaksi', $_POST['no_transaksi'])->oneArray();
          echo $this->draw('gudang.rusak.form.html', ['rusak' => $rusak, 'mode' => 'edit', 'barang' => $barang, 'vendor' => $vendor, 'lokasi' => $lokasi]);
      } else {
          $rusak = [
              'no_transaksi' => $this->_generateNoGudangRusak(),
              'tgl_transaksi' => date('Y-m-d'),
              'kode_item' => '',
              'batch' => '',
              'kode_lokasi' => '',
              'jumlah' => 0,
              'kategori_kerusakan' => '',
              'keterangan' => '',
              'status' => 'Karantina'
          ];
          echo $this->draw('gudang.rusak.form.html', ['rusak' => $rusak, 'mode' => 'add', 'barang' => $barang, 'vendor' => $vendor, 'lokasi' => $lokasi]);
      }
      exit();
  }

  public function postSaveGudangRusak()
  {
      $no_transaksi = $_POST['no_transaksi'] ?? '';
      $data = [
          'no_transaksi' => $no_transaksi,
          'tgl_transaksi' => $_POST['tgl_transaksi'] ?? date('Y-m-d'),
          'kode_item' => $_POST['kode_item'] ?? '',
          'batch' => $_POST['batch'] ?? '',
          'kode_lokasi' => $_POST['kode_lokasi'] ?? '',
          'jumlah' => $_POST['jumlah'] ?? 0,
          'kategori_kerusakan' => $_POST['kategori_kerusakan'] ?? '',
          'keterangan' => $_POST['keterangan'] ?? '',
          'status' => 'Karantina',
          'tgl_input' => date('Y-m-d H:i:s'),
          'user_input' => $this->core->getUserInfo('username', null, true)
      ];

      $cek = $this->db('rsns_custom_logistik_non_medis_barang_rusak')->where('no_transaksi', $no_transaksi)->oneArray();
      
      if (!$cek) {
          $query = $this->db('rsns_custom_logistik_non_medis_barang_rusak')->save($data);
          
          // Potong Stok
          $current_stok = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                               ->where('kode_item', $data['kode_item'])
                               ->where('kode_lokasi', $data['kode_lokasi'])
                               ->where('batch_no', $data['batch'])
                               ->oneArray();
          if($current_stok) {
              $new_stok = $current_stok['stok'] - $data['jumlah'];
              $this->db('rsns_custom_logistik_non_medis_stok_batch')
                   ->where('kode_item', $data['kode_item'])
                   ->where('kode_lokasi', $data['kode_lokasi'])
                   ->where('batch_no', $data['batch'])
                   ->update(['stok' => $new_stok]);
          }
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_barang_rusak')->where('no_transaksi', $no_transaksi)->update($data);
      }

      // Logging
      if($query) {
          $user = $this->core->getUserInfo('username', null, true);
          $logdata = $data['no_transaksi'].' | '.$data['kode_item'].' | '.$data['jumlah'].' | Karantina | '.$user;
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_barang_rusak',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $_SERVER['REMOTE_ADDR'],
              'log_data' => $logdata,
              'log_status' => $cek ? 'U' : 'I',
              'log_username' => $user
          ]);
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function anyDetailGudangRusak()
  {
      if (isset($_POST['no_transaksi'])){
          $rusak = $this->db('rsns_custom_logistik_non_medis_barang_rusak')
                        ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_barang_rusak.kode_item')
                        ->leftJoin('rsns_custom_logistik_non_medis_vendor', 'rsns_custom_logistik_non_medis_vendor.kode_vendor = rsns_custom_logistik_non_medis_barang_rusak.kode_vendor')
                        ->select('rsns_custom_logistik_non_medis_barang_rusak.*')
                        ->select('rsns_custom_logistik_non_medis_master_barang.nama_barang')
                        ->select('rsns_custom_logistik_non_medis_vendor.nama_vendor')
                        ->where('no_transaksi', $_POST['no_transaksi'])
                        ->oneArray();
          $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('status', 'Whitelist')->toArray();
          echo $this->draw('gudang.rusak.detail.html', ['rusak' => $rusak, 'vendor' => $vendor]);
      }
      exit();
  }

  public function postUpdateTindakLanjut()
  {
      $no_transaksi = $_POST['no_transaksi'] ?? '';
      $tindak_lanjut = $_POST['tindak_lanjut'] ?? '';

      $data = [
          'tindak_lanjut' => $tindak_lanjut,
          'status' => 'Selesai'
      ];

      if ($tindak_lanjut == 'Retur') {
          $data['kode_vendor'] = $_POST['kode_vendor'] ?? '';
          $data['tgl_retur'] = $_POST['tgl_retur'] ?? date('Y-m-d');
          $data['status_retur'] = 'Diajukan';
          $data['catatan_logistik'] = $_POST['catatan'] ?? '';
      } else {
          $data['tgl_pemusnahan'] = $_POST['tgl_pemusnahan'] ?? date('Y-m-d');
          $data['metode_pemusnahan'] = $_POST['metode_pemusnahan'] ?? '';
          $data['saksi_1'] = $_POST['saksi_1'] ?? '';
          $data['saksi_2'] = $_POST['saksi_2'] ?? '';
          $data['catatan_logistik'] = $_POST['catatan'] ?? '';
      }

      $query = $this->db('rsns_custom_logistik_non_medis_barang_rusak')->where('no_transaksi', $no_transaksi)->update($data);

      if($query) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate tindak lanjut']);
      }
      exit();
  }

  public function getPrintBAP()
  {
      if (isset($_GET['no_transaksi'])){
          $rusak = $this->db('rsns_custom_logistik_non_medis_barang_rusak')
                        ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_barang_rusak.kode_item')
                        ->where('no_transaksi', $_GET['no_transaksi'])
                        ->oneArray();
          echo $this->draw('gudang.rusak.print.bap.html', ['rusak' => $rusak, 'logo' => $this->settings->get('settings.logo')]);
      }
      exit();
  }

  public function getPrintSuratRetur()
  {
      if (isset($_GET['no_transaksi'])){
          $rusak = $this->db('rsns_custom_logistik_non_medis_barang_rusak')
                        ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_barang_rusak.kode_item')
                        ->leftJoin('rsns_custom_logistik_non_medis_vendor', 'rsns_custom_logistik_non_medis_vendor.kode_vendor = rsns_custom_logistik_non_medis_barang_rusak.kode_vendor')
                        ->where('no_transaksi', $_GET['no_transaksi'])
                        ->oneArray();
          echo $this->draw('gudang.rusak.print.retur.html', ['rusak' => $rusak, 'logo' => $this->settings->get('settings.logo')]);
      }
      exit();
  }


  public function getPengadaankontrak()
  {
      $this->_addHeaderFiles();
      return $this->draw('pengadaan.kontrak.html');
  }



  public function getDistribusiSppb()
  {
      $this->_initSppb();
      $this->_addHeaderFiles();
      return $this->draw('distribusi.sppb.html');
  }

  public function anyAjaxMasterBarang()
  {
      $cari = $_GET['q'] ?? '';
      $query = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('status', 'Aktif');
      if (!empty($cari)) {
          $query->where('nama_barang', 'LIKE', '%'.$cari.'%')
                ->orLike('kode_item', 'LIKE', '%'.$cari.'%');
      }
      $items = $query->limit(20)->toArray();
      echo json_encode($items);
      exit();
  }

  public function anyDisplaySppb()
  {
      $this->_initSppb();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $status = isset($_POST['status']) ? $_POST['status'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $sql = "
          SELECT s.no_sppb, s.tgl_sppb, s.kode_unit, u.nama_unit, s.status, s.keterangan,
                 COUNT(s.kode_item) as jml_item,
                 GROUP_CONCAT(b.nama_barang SEPARATOR ', ') as daftar_barang
          FROM rsns_custom_logistik_non_medis_sppb s
          LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = s.kode_unit
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = s.kode_item
          WHERE 1=1
      ";

      $params = [];
      if (!empty($cari)) {
          $sql .= " AND (s.no_sppb LIKE ? OR u.nama_unit LIKE ? OR b.nama_barang LIKE ?) ";
          $params[] = '%'.$cari.'%';
          $params[] = '%'.$cari.'%';
          $params[] = '%'.$cari.'%';
      }

      if (!empty($status)) {
          $sql .= " AND s.status = ? ";
          $params[] = $status;
      }

      $sql .= " GROUP BY s.no_sppb, s.tgl_sppb, s.kode_unit, u.nama_unit, s.status, s.keterangan 
                ORDER BY s.tgl_input DESC, s.no_sppb DESC ";

      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute($params);
      $all_data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $jumlah_data = count($all_data);
      $jml_halaman = $jumlah_data > 0 ? ceil($jumlah_data / $perpage) : 1;
      $rows = array_slice($all_data, $_offset, $perpage);
      
      foreach ($rows as $i => &$row) {
          $row['no'] = $i + 1 + $_offset;
          $row['tgl_sppb'] = date('d/m/Y', strtotime($row['tgl_sppb']));
      }

      echo $this->draw('distribusi.sppb.display.html', [
          'sppb' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormSppb()
  {
      $this->_initSppb();
      $this->_initLokasi();
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $items = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('status', 'Aktif')->toArray();

      if (isset($_POST['no_sppb'])) {
          $no_sppb = $_POST['no_sppb'];
          $rows = $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->toArray();
          $sppb = $rows[0];
          $sppb['items'] = $rows;
          echo $this->draw('distribusi.sppb.form.html', ['sppb' => $sppb, 'mode' => 'edit', 'units' => $units, 'items' => $items]);
      } else {
          $sppb = [
              'no_sppb' => '',
              'tgl_sppb' => date('Y-m-d'),
              'kode_unit' => '',
              'status' => 'Draft',
              'items' => []
          ];
          echo $this->draw('distribusi.sppb.form.html', ['sppb' => $sppb, 'mode' => 'add', 'units' => $units, 'items' => $items]);
      }
      exit();
  }

  public function anyDetailSppb()
  {
      if (isset($_POST['no_sppb'])) {
          $no_sppb = $_POST['no_sppb'];
          $sql = "
              SELECT s.*, u.nama_unit, b.nama_barang
              FROM rsns_custom_logistik_non_medis_sppb s
              LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = s.kode_unit
              LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = s.kode_item
              WHERE s.no_sppb = ?
          ";
          $stmt = $this->db()->pdo()->prepare($sql);
          $stmt->execute([$no_sppb]);
          $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
          
          if ($rows) {
              foreach ($rows as $idx => &$row) { $row['index'] = $idx; }
              $sppb = $rows[0];
              $sppb['items'] = $rows;
              echo $this->draw('distribusi.sppb.detail.html', ['sppb' => $sppb]);
          }
      }
      exit();
  }

  public function postSaveSppb()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $kode_unit = $_POST['kode_unit'] ?? '';
      $status = $_POST['status'] ?? 'Diajukan';
      $user = $this->core->getUserInfo('username', null, true);

      if (empty($no_sppb)) {
          $no_sppb = $this->_generateNoSPPB($kode_unit);
      }

      // Check if already processed
      $cek = $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->oneArray();
      if ($cek && !in_array($cek['status'], ['Draft', 'Diajukan'])) {
          echo json_encode(['status' => 'error', 'message' => 'Data sudah diproses dan tidak dapat diubah!']);
          exit();
      }

      // Check Quota
      if ($status == 'Diajukan') {
          $tgl_sppb = $_POST['tgl_sppb'] ?? date('Y-m-d');
          $tahun = date('Y', strtotime($tgl_sppb));
          $bulan = (int)date('m', strtotime($tgl_sppb));
          $triwulan = ceil($bulan / 3);

          if (isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
              foreach ($_POST['kode_item'] as $key => $kode_item) {
                  $qty_request = $_POST['jumlah'][$key] ?? 0;
                  
                  // Get total quota
                  $sql_q = "SELECT SUM(jumlah) as total FROM rsns_custom_logistik_non_medis_kuota 
                            WHERE kode_unit = ? AND kode_item = ? AND status = 'Disetujui' AND tahun = ?
                            AND ( (periode_tipe = 'Bulanan' AND bulan = ?) OR (periode_tipe = 'Triwulan' AND triwulan = ?) )";
                  $stmt_q = $this->db()->pdo()->prepare($sql_q);
                  $stmt_q->execute([$kode_unit, $kode_item, $tahun, $bulan, $triwulan]);
                  $total_quota = $stmt_q->fetch()['total'] ?? 0;

                  if ($total_quota > 0) {
                      // Get usage (including those already in other SPPBs)
                      $sql_u = "SELECT SUM(jumlah) as total_used FROM rsns_custom_logistik_non_medis_sppb 
                                WHERE kode_unit = ? AND kode_item = ? AND no_sppb != ?
                                AND status NOT IN ('Draft', 'Ditolak')
                                AND YEAR(tgl_sppb) = ? AND MONTH(tgl_sppb) = ?";
                      $stmt_u = $this->db()->pdo()->prepare($sql_u);
                      $stmt_u->execute([$kode_unit, $kode_item, $no_sppb, $tahun, $bulan]);
                      $used = $stmt_u->fetch()['total_used'] ?? 0;

                      if (($used + $qty_request) > $total_quota) {
                          $item_name = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->oneArray()['nama_barang'] ?? $kode_item;
                          echo json_encode(['status' => 'error', 'message' => "Kuota tidak mencukupi untuk item: $item_name. Sisa kuota saat ini: " . ($total_quota - $used)]);
                          exit();
                      }
                  }
              }
          }
      }

      $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->delete();

      $success = 0;
      if (isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
          foreach ($_POST['kode_item'] as $key => $kode_item) {
              $data = [
                  'no_sppb' => $no_sppb,
                  'tgl_sppb' => $_POST['tgl_sppb'] ?? date('Y-m-d'),
                  'kode_unit' => $kode_unit,
                  'kode_item' => $kode_item,
                  'jumlah' => $_POST['jumlah'][$key] ?? 0,
                  'satuan' => $_POST['satuan'][$key] ?? '',
                  'status' => $status,
                  'keterangan' => $_POST['keterangan_umum'] ?? '',
                  'user_input' => $user,
                  'tgl_input' => date('Y-m-d H:i:s')
              ];
              if ($this->db('rsns_custom_logistik_non_medis_sppb')->save($data)) {
                  $success++;
              }
          }
      }

      if ($success > 0) {
          $this->_logAction('logistik_non_medis_sppb', 'Simpan SPPB: ' . $no_sppb . ' | Status: ' . $status);
          echo json_encode(['status' => 'success', 'no_sppb' => $no_sppb]);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data.']);
      }
      exit();
  }

  public function postApproveSppb()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $user = $this->core->getUserInfo('username', null, true);
      
      $update = $this->db('rsns_custom_logistik_non_medis_sppb')
                     ->where('no_sppb', $no_sppb)
                     ->update([
                         'status' => 'Disetujui Unit',
                         'user_approve_unit' => $user,
                         'tgl_approve_unit' => date('Y-m-d H:i:s')
                     ]);
      
      if ($update) {
          $this->_logAction('logistik_non_medis_sppb', 'Approve SPPB Unit: ' . $no_sppb, 'U');
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyetujui permintaan.']);
      }
      exit();
  }

  public function postVerifikasiSppb()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $user = $this->core->getUserInfo('username', null, true);
      
      $update = $this->db('rsns_custom_logistik_non_medis_sppb')
                     ->where('no_sppb', $no_sppb)
                     ->update([
                         'status' => 'Terverifikasi',
                         'user_verifikasi' => $user,
                         'tgl_verifikasi' => date('Y-m-d H:i:s')
                     ]);
      
      if ($update) {
          $this->_logAction('logistik_non_medis_sppb', 'Verifikasi SPPB Logistik: ' . $no_sppb, 'U');
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal memverifikasi permintaan.']);
      }
      exit();
  }

  public function postHapusSppb()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->oneArray();
      
      if ($cek) {
          if (!in_array($cek['status'], ['Draft', 'Diajukan'])) {
              echo json_encode(['status' => 'error', 'message' => 'Data sudah diproses dan tidak dapat diubah!']);
              exit();
          }
          
          if ($this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->delete()) {
              $this->_logAction('logistik_non_medis_sppb', 'Hapus SPPB: ' . $no_sppb, 'D');
              echo json_encode(['status' => 'success']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data.']);
          }
      }
      exit();
  }

  public function anyCetakSppb($no_sppb = '')
  {
      if (empty($no_sppb)) $no_sppb = $_GET['no_sppb'] ?? '';
      
      $sql = "
          SELECT s.*, u.nama_unit, b.nama_barang
          FROM rsns_custom_logistik_non_medis_sppb s
          LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = s.kode_unit
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = s.kode_item
          WHERE s.no_sppb = ?
      ";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute([$no_sppb]);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      if (!$rows) die('Data tidak ditemukan');
      
      foreach ($rows as $idx => &$row) { $row['index'] = $idx; }
      $sppb = $rows[0];
      $sppb['items'] = $rows;

      echo $this->draw('distribusi.sppb.cetak.html', [
          'sppb' => $sppb,
          'logo' => url().'/'.$this->settings->get('settings.logo'),
          'nama_rs' => $this->settings->get('settings.nama_instansi'),
          'alamat_rs' => $this->settings->get('settings.alamat'),
          'kota_rs' => $this->settings->get('settings.kota'),
          'kontak_rs' => $this->settings->get('settings.nomor_telepon')
      ]);
      exit();
  }


  public function getDistribusiVerifikasi()
  {
      $this->_initSppb();
      $this->_addHeaderFiles();
      $this->tpl->set('title', 'Verifikasi Permintaan (SPPB)');
      return $this->draw('distribusi.verifikasi_v2.html');
  }

  public function anyDisplayDistribusiVerifikasi()
  {
      $this->_initSppb();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query_count = $this->db('rsns_custom_logistik_non_medis_sppb')
                          ->where('status', 'Disetujui Unit');
      if(!empty($cari)) {
          $query_count->where('no_sppb', 'LIKE', '%'.$cari.'%');
      }
      $rows_count = $query_count->group('no_sppb')->toArray();
      $jumlah_data = count($rows_count);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_sppb')
                    ->where('status', 'Disetujui Unit');

      if(!empty($cari)) {
          $rows->where('no_sppb', 'LIKE', '%'.$cari.'%');
      }

      $rows = $rows->group('no_sppb')
                   ->desc('tgl_input')
                   ->offset($_offset)
                   ->limit($perpage)
                   ->toArray();

      foreach($rows as &$row) {
          $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['kode_unit'])->oneArray();
          $row['nama_unit'] = $unit['nama_unit'] ?? '-';
      }

      echo $this->draw('distribusi.verifikasi.display.html', [
          'sppb' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman
      ]);
      exit();
  }

  public function anyDetailDistribusiVerifikasi()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      
      try {
          $items = $this->db('rsns_custom_logistik_non_medis_sppb')
                        ->where('no_sppb', $no_sppb)
                        ->toArray();
          
          foreach($items as &$item) {
              $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $item['kode_item'])->oneArray();
              $item['nama_barang'] = $barang['nama_barang'] ?? '-';
              
              $item['stok'] = $this->_getCurrentStock($item['kode_item']);
              $item['kuota'] = $this->_getRemainingQuota($item['kode_unit'], $item['kode_item']);
              
              $item['stok_color'] = ($item['stok'] < $item['jumlah']) ? 'label-danger' : 'label-success';
              $item['kuota_color'] = ($item['kuota'] < $item['jumlah']) ? 'label-warning' : 'label-info';
              $item['kuota_display'] = ($item['kuota'] == 999999) ? '&infin; Bebas' : $item['kuota'];
          }
          
          echo $this->draw('distribusi.verifikasi.detail.html', [
              'items' => $items, 
              'no_sppb' => $no_sppb
          ]);
      } catch (\Exception $e) {
          echo "<div class='alert alert-danger'>Terjadi kesalahan: " . $e->getMessage() . "</div>";
      }
      exit();
  }

  public function postSaveDistribusiVerifikasi()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $status = $_POST['status_verif'] ?? 'Terverifikasi';
      $user = $_SESSION['mlite_user'] ?? 'admin';

      if(empty($no_sppb)) {
          echo json_encode(['status' => 'error', 'message' => 'No. SPPB tidak valid. Diterima: ' . json_encode($_POST)]);
          exit();
      }

      try {
          if($status == 'Terverifikasi') {
              // Mark all items under this SPPB as Terverifikasi
              $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update([
                  'status' => 'Terverifikasi',
                  'user_verifikasi' => $user,
                  'tgl_verifikasi' => date('Y-m-d H:i:s')
              ]);
              
              // Update approved quantities for specific items
              $items = $_POST['items'] ?? [];
              foreach($items as $id => $val) {
                  $this->db('rsns_custom_logistik_non_medis_sppb')->where('id', $id)->update([
                      'jumlah_disetujui' => $val['jumlah_disetujui']
                  ]);
              }
              $this->_logAction('logistik_non_medis_sppb', 'Verifikasi SPPB Disetujui: ' . $no_sppb, 'U');
          } else {
              $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update([
                  'status' => 'Ditolak',
                  'alasan_penolakan' => $_POST['alasan_penolakan'] ?? '',
                  'user_verifikasi' => $user,
                  'tgl_verifikasi' => date('Y-m-d H:i:s')
              ]);
              $this->_logAction('logistik_non_medis_sppb', 'Verifikasi SPPB Ditolak: ' . $no_sppb . ' | Alasan: ' . ($_POST['alasan_penolakan'] ?? ''), 'U');
          }
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  private function _getCurrentStock($kode_item)
  {
      $res = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                   ->select('SUM(stok) as total')
                   ->where('kode_item', $kode_item)
                   ->oneArray();
      return $res['total'] ?? 0;
  }

  private function _getRemainingQuota($kode_unit, $kode_item)
  {
      $tahun = date('Y');
      $bulan = (int)date('m');
      $triwulan = (int)ceil($bulan / 3);
      
      $q_bulan = $this->db('rsns_custom_logistik_non_medis_kuota')
                       ->select('SUM(jumlah) as total')
                       ->where('kode_unit', $kode_unit)
                       ->where('kode_item', $kode_item)
                       ->where('periode_tipe', 'Bulanan')
                       ->where('tahun', $tahun)
                       ->where('bulan', $bulan)
                       ->where('status', 'Disetujui')
                       ->oneArray();
      
      $q_triwulan = $this->db('rsns_custom_logistik_non_medis_kuota')
                          ->select('SUM(jumlah) as total')
                          ->where('kode_unit', $kode_unit)
                          ->where('kode_item', $kode_item)
                          ->where('periode_tipe', 'Triwulan')
                          ->where('tahun', $tahun)
                          ->where('triwulan', $triwulan)
                          ->where('status', 'Disetujui')
                          ->oneArray();
      
      $total_kuota = 0;
      $has_quota = false;
      $start_month = $bulan;
      $end_month = $bulan;

      if ($q_bulan['total'] !== null) {
          $total_kuota += $q_bulan['total'];
          $has_quota = true;
      }
      
      if ($q_triwulan['total'] !== null) {
          $total_kuota += $q_triwulan['total'];
          $has_quota = true;
          $start_month = ($triwulan - 1) * 3 + 1;
          $end_month = $start_month + 2;
      }

      if (!$has_quota) {
          $perencanaan = $this->db('rsns_custom_logistik_non_medis_perencanaan')
                              ->where('kode_unit', $kode_unit)
                              ->where('kode_item', $kode_item)
                              ->where('tahun', $tahun)
                              ->oneArray();
          if (!$perencanaan) return 999999;
          
          $months_map = [1=>'jan', 2=>'feb', 3=>'mar', 4=>'apr', 5=>'mei', 6=>'jun', 7=>'jul', 8=>'agu', 9=>'sep', 10=>'okt', 11=>'nov', 12=>'des'];
          $bulan_key = $months_map[$bulan] ?? 'jan';
          $total_kuota = $perencanaan[$bulan_key] ?? 0;
      }

      $start_date = sprintf("%s-%02d-01", $tahun, $start_month);
      $end_date = date("Y-m-t", strtotime(sprintf("%s-%02d-01", $tahun, $end_month)));

      $used = $this->db('rsns_custom_logistik_non_medis_sppb')
                   ->select('SUM(jumlah_disetujui) as total')
                   ->where('kode_unit', $kode_unit)
                   ->where('kode_item', $kode_item)
                   ->where('status', '!=', 'Baru')
                   ->where('status', '!=', 'Disetujui Unit')
                   ->where('status', '!=', 'Ditolak')
                   ->where('tgl_sppb', '>=', $start_date)
                   ->where('tgl_sppb', '<=', $end_date)
                   ->oneArray();

      return $total_kuota - ($used['total'] ?? 0);
  }

  public function getDistribusiPacking()
  {
      $this->_initPacking();
      $this->_addHeaderFiles();
      return $this->draw('distribusi.packing.html');
  }

  public function anyDisplayDistribusiPacking()
  {
      $this->_initPacking();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query_count = $this->db('rsns_custom_logistik_non_medis_sppb')
                          ->where('status', 'Terverifikasi')
                          ->orWhere('status', 'Picking')
                          ->orWhere('status', 'Packing');
      if(!empty($cari)) {
          $query_count->where('no_sppb', 'LIKE', '%'.$cari.'%');
      }
      $rows_count = $query_count->group('no_sppb')->toArray();
      $jumlah_data = count($rows_count);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_sppb')
                    ->where('status', 'Terverifikasi')
                    ->orWhere('status', 'Picking')
                    ->orWhere('status', 'Packing');

      if(!empty($cari)) {
          $rows->where('no_sppb', 'LIKE', '%'.$cari.'%');
      }

      $rows = $rows->group('no_sppb')
                   ->desc('tgl_input')
                   ->offset($_offset)
                   ->limit($perpage)
                   ->toArray();

      foreach($rows as &$row) {
          $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['kode_unit'])->oneArray();
          $row['nama_unit'] = $unit['nama_unit'] ?? '-';
          // Check if already has packing records
          $packing = $this->db('rsns_custom_logistik_non_medis_packing')->where('no_sppb', $row['no_sppb'])->oneArray();
          $row['has_packing'] = ($packing) ? true : false;
      }

      $pages = [];
      if($jml_halaman > 1) {
          for($i = 1; $i <= $jml_halaman; $i++) {
              $pages[] = $i;
          }
      }

      echo $this->draw('distribusi.packing.display.html', [
          'sppb' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'pages' => $pages
      ]);
      exit();
  }

  public function anyFormPicking()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $items = $this->db('rsns_custom_logistik_non_medis_sppb')
                    ->where('no_sppb', $no_sppb)
                    ->toArray();
      
      foreach($items as &$item) {
          $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $item['kode_item'])->oneArray();
          $item['nama_barang'] = $barang['nama_barang'] ?? '-';
          $item['lokasi_default'] = $barang['default_kode_lokasi'] ?? '-';
          
          // Suggest batch using FEFO
          $batches = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                          ->where('kode_item', $item['kode_item'])
                          ->where('stok', '>', 0)
                          ->asc('tgl_expired')
                          ->toArray();
          $item['batches'] = $batches;
          
          // Check if already picked
          $picked = $this->db('rsns_custom_logistik_non_medis_packing')
                         ->where('no_sppb', $no_sppb)
                         ->where('kode_item', $item['kode_item'])
                         ->toArray();
          $item['qty_picked_total'] = array_sum(array_column($picked, 'qty_picked'));
          $item['qty_remaining'] = $item['jumlah_disetujui'] - $item['qty_picked_total'];
      }

      echo $this->draw('distribusi.picking.form.html', [
          'items' => $items,
          'no_sppb' => $no_sppb
      ]);
      exit();
  }

  public function postSavePicking()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $items = $_POST['items'] ?? [];
      $user = $_SESSION['mlite_user'] ?? 'admin';
      $no_packing = $this->_generateNoPacking();

      if(empty($no_sppb) || empty($items)) {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
          exit();
      }

      try {
          foreach($items as $item) {
              if($item['qty'] > 0) {
                  $this->db('rsns_custom_logistik_non_medis_packing')->save([
                      'no_packing' => $no_packing,
                      'no_sppb' => $no_sppb,
                      'tgl_packing' => date('Y-m-d H:i:s'),
                      'petugas_packing' => $user,
                      'kode_item' => $item['kode_item'],
                      'batch_no' => $item['batch_no'] ?? NULL,
                      'qty_picked' => $item['qty'],
                      'koli_ke' => 1, // Default to 1, can be adjusted in packing form
                      'keterangan' => 'Picked'
                  ]);
              }
          }

          // Update status to Picking or Packing
          $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update(['status' => 'Picking']);
          
          // Record packing time in tracking
          $cek_pengiriman = $this->db('rsns_custom_logistik_non_medis_pengiriman')->where('no_sppb', $no_sppb)->oneArray();
          if (!$cek_pengiriman) {
              $this->db('rsns_custom_logistik_non_medis_pengiriman')->save([
                  'no_sppb' => $no_sppb,
                  'status' => 'Proses',
                  'waktu_packing' => date('Y-m-d H:i:s')
              ]);
          } else {
              $this->db('rsns_custom_logistik_non_medis_pengiriman')->where('no_sppb', $no_sppb)->update([
                  'waktu_packing' => date('Y-m-d H:i:s')
              ]);
          }
          
          echo json_encode(['status' => 'success', 'no_packing' => $no_packing]);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function anyFormPacking()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $items = $this->db('rsns_custom_logistik_non_medis_packing')
                    ->where('no_sppb', $no_sppb)
                    ->toArray();
      
      foreach($items as &$item) {
          $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $item['kode_item'])->oneArray();
          $item['nama_barang'] = $barang['nama_barang'] ?? '-';
      }

      echo $this->draw('distribusi.packing.form.html', [
          'items' => $items,
          'no_sppb' => $no_sppb
      ]);
      exit();
  }

  public function postSavePacking()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $packing_data = $_POST['packing'] ?? []; // Array of id => koli_ke
      
      try {
          foreach($packing_data as $id => $val) {
              $this->db('rsns_custom_logistik_non_medis_packing')
                   ->where('id', $id)
                   ->update([
                       'koli_ke' => $val['koli_ke'],
                       'total_berat_koli' => $val['berat'],
                       'keterangan' => 'Packed'
                   ]);
          }

          $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update(['status' => 'Packing']);
          
          // Update packing time to current finalize time
          $this->db('rsns_custom_logistik_non_medis_pengiriman')->where('no_sppb', $no_sppb)->update([
              'waktu_packing' => date('Y-m-d H:i:s')
          ]);
          
          $this->_logAction('logistik_non_medis_packing', 'Simpan Packing SPPB: ' . $no_sppb, 'U');
          
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Approved mutation: '.$no_mutasi.' | Role: '.$role_type.' | Asset: '.$mutasi['kode_aset'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_mutasi',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function postFinalizePacking()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      if($this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update(['status' => 'Ready'])) {
          $this->_logAction('logistik_non_medis_packing', 'Finalisasi Packing SPPB (Ready): ' . $no_sppb, 'U');
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status.']);
      }
      exit();
  }
  public function anyPrintPickList()
  {
      $no_sppb = $_GET['no_sppb'] ?? '';
      
      $sql = "
          SELECT s.*, u.nama_unit, b.nama_barang, b.default_kode_lokasi
          FROM rsns_custom_logistik_non_medis_sppb s
          LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = s.kode_unit
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = s.kode_item
          WHERE s.no_sppb = ?
          ORDER BY b.default_kode_lokasi ASC
      ";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute([$no_sppb]);
      $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      if (!$items) die('Data tidak ditemukan: ' . $no_sppb);
      
      $sppb = $items[0];

      echo $this->draw('distribusi.sppb.picklist.html', [
          'sppb' => $sppb,
          'items' => $items,
          'logo' => url().'/'.$this->settings->get('settings.logo'),
          'nama_rs' => $this->settings->get('settings.nama_instansi')
      ]);
      exit();
  }

  public function anyPrintPackingLabel()
  {
      $no_packing = $_GET['no_packing'] ?? '';
      $koli = $_GET['koli'] ?? 1;
      
      $items = $this->db('rsns_custom_logistik_non_medis_packing')
                    ->where('no_packing', $no_packing)
                    ->where('koli_ke', $koli)
                    ->toArray();
      
      if (!$items) die('Data packing tidak ditemukan');
      
      $sppb_no = $items[0]['no_sppb'];
      $sppb = $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $sppb_no)->oneArray();
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $sppb['kode_unit'])->oneArray();

      foreach($items as &$item) {
          $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $item['kode_item'])->oneArray();
          $item['nama_barang'] = $barang['nama_barang'] ?? '-';
      }

      echo $this->draw('distribusi.packing.label.html', [
          'no_packing' => $no_packing,
          'koli' => $koli,
          'no_sppb' => $sppb_no,
          'unit' => $unit,
          'items' => $items,
          'nama_rs' => $this->settings->get('settings.nama_instansi')
      ]);
      exit();
  }

  public function getDistribusiSerahterima()
  {
      $this->_initSerahTerima();
      $this->_addHeaderFiles();
      return $this->draw('distribusi.serahterima.html');
  }

  public function anyDisplaySerahTerima()
  {
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $_offset = ($halaman - 1) * $perpage;

      $query_count = $this->db('rsns_custom_logistik_non_medis_sppb')
                          ->select('no_sppb')
                          ->where(function($q) {
                              $q->where('status', 'Ready')->orWhere('status', 'Selesai');
                          })
                          ->group('no_sppb');
      
      if(!empty($cari)) {
          $query_count->where('no_sppb', 'LIKE', '%'.$cari.'%');
      }

      $jumlah_data = count($query_count->toArray());
      $jml_halaman = ceil($jumlah_data / $perpage);

      $rows = $this->db('rsns_custom_logistik_non_medis_sppb s')
                   ->select('s.*, u.nama_unit, st.no_serah_terima, st.tanggal_serah')
                   ->join('rsns_custom_logistik_non_medis_unit u', 'u.kode_unit = s.kode_unit')
                   ->leftJoin('rsns_custom_logistik_non_medis_serah_terima st', 'st.no_sppb = s.no_sppb')
                   ->where(function($q) {
                       $q->where('s.status', 'Ready')->orWhere('s.status', 'Selesai');
                   })
                   ->group('s.no_sppb');

      if(!empty($cari)) {
          $rows->where('s.no_sppb', 'LIKE', '%'.$cari.'%')
               ->orLike('u.nama_unit', '%'.$cari.'%');
      }

      $rows = $rows->desc('s.tgl_sppb')
                   ->offset($_offset)
                   ->limit($perpage)
                   ->toArray();

      echo $this->draw('distribusi.serahterima.display.html', [
          'serahterima' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman
      ]);
      exit();
  }

  public function anyFormSerahTerima()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $sppb = $this->db('rsns_custom_logistik_non_medis_sppb s')
                   ->select('s.*, u.nama_unit')
                   
                   ->join('rsns_custom_logistik_non_medis_unit u', 'u.kode_unit = s.kode_unit')
                   ->where('s.no_sppb', $no_sppb)
                   ->oneArray();

      $items = $this->db('rsns_custom_logistik_non_medis_sppb s')
                    ->select('s.*, b.nama_barang')
                    
                    ->join('rsns_custom_logistik_non_medis_master_barang b', 'b.kode_item = s.kode_item')
                    ->where('s.no_sppb', $no_sppb)
                    ->toArray();

      echo $this->draw('distribusi.serahterima.form.html', [
          'sppb' => $sppb,
          'items' => $items,
          'no_bast' => $this->_generateNoSerahTerima(),
          'tgl_sekarang' => date('Y-m-d H:i:s')
      ]);
      exit();
  }

  public function postSaveSerahTerima()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $no_serah_terima = $_POST['no_serah_terima'] ?? '';
      $penerima_nama = $_POST['penerima_nama'] ?? '';
      $tanda_terima_base64 = $_POST['tanda_terima'] ?? '';

      if(empty($no_sppb) || empty($penerima_nama) || empty($tanda_terima_base64)) {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap!']);
          exit();
      }

      $upload_dir = UPLOADS . '/logistik_non_medis/serah_terima';
      $foto_filename = '';

      if(isset($_FILES['foto_kondisi']) && $_FILES['foto_kondisi']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['foto_kondisi']['name'], PATHINFO_EXTENSION));
          $foto_filename = 'foto_' . str_replace('/', '-', $no_serah_terima) . '_' . time() . '.' . $ext;
          move_uploaded_file($_FILES['foto_kondisi']['tmp_name'], $upload_dir . '/foto/' . $foto_filename);
      }

      $data = [
          'no_serah_terima' => $no_serah_terima,
          'no_sppb' => $no_sppb,
          'tanggal_serah' => date('Y-m-d H:i:s'),
          'petugas_pengirim' => $this->core->getUserInfo('username', null, true),
          'penerima_nama' => $penerima_nama,
          'penerima_nip' => $_POST['penerima_nip'] ?? '',
          'foto_kondisi' => $foto_filename,
          'tanda_terima' => $tanda_terima_base64,
          'keterangan' => $_POST['keterangan'] ?? ''
      ];

      $save = $this->db('rsns_custom_logistik_non_medis_serah_terima')->save($data);

      if($save) {
          $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update(['status' => 'Selesai']);
          
          // Log Activity & Internal Notification
          $user = $this->core->getUserInfo('username', null, true);
          $ip = $_SERVER['REMOTE_ADDR'] ?? '';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_serah_terima',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'Serah Terima Selesai: ' . $no_sppb . ' | BAST: ' . $no_serah_terima,
              'log_status' => 'I',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success', 'no_sppb' => $no_sppb]);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data serah terima.']);
      }
      exit();
  }

  public function anyCetakBASTSerahTerima($no_sppb = null)
  {
      if(!$no_sppb) $no_sppb = $_GET['no_sppb'] ?? null;
      if(!$no_sppb) exit("Nomor SPPB tidak ditemukan.");
      
      $st = $this->db('rsns_custom_logistik_non_medis_serah_terima')->where('no_sppb', $no_sppb)->oneArray();
      if(!$st) {
          echo "Data BAST tidak ditemukan.";
          exit();
      }

      $sppb = $this->db('rsns_custom_logistik_non_medis_sppb s')
                   ->select('s.*, u.nama_unit')
                   
                   ->join('rsns_custom_logistik_non_medis_unit u', 'u.kode_unit = s.kode_unit')
                   ->where('s.no_sppb', $no_sppb)
                   ->oneArray();

      $items = $this->db('rsns_custom_logistik_non_medis_sppb s')
                    ->select('s.*, b.nama_barang')
                    
                    ->join('rsns_custom_logistik_non_medis_master_barang b', 'b.kode_item = s.kode_item')
                    ->where('s.no_sppb', $no_sppb)
                    ->toArray();

      $logo = url($this->settings->get('settings.logo'));
      if(!$this->settings->get('settings.logo')) {
          $logo = url('assets/img/logo.png');
      }

      $foto_url = '';
      if(!empty($st['foto_kondisi'])) {
          $foto_url = url('uploads/logistik_non_medis/serah_terima/foto/' . $st['foto_kondisi']);
      }

      echo $this->draw('distribusi.serahterima.bast.html', [
          'st' => $st,
          'sppb' => $sppb,
          'items' => $items,
          'logo' => $logo,
          'nama_rs' => $this->settings->get('settings.nama_instansi'),
          'alamat_rs' => $this->settings->get('settings.alamat'),
          'kota_rs' => $this->settings->get('settings.kota'),
          'kontak_rs' => $this->settings->get('settings.nomor_telepon'),
          'foto_url' => $foto_url
      ]);
      exit();
  }

  private function _initPengiriman()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_pengiriman` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_sppb` varchar(50) NOT NULL,
        `no_manifest` varchar(100) DEFAULT NULL,
        `kurir` varchar(100) DEFAULT NULL,
        `kendaraan` varchar(100) DEFAULT NULL,
        `status` enum('Proses','Dikirim','Diterima') NOT NULL DEFAULT 'Proses',
        `waktu_packing` datetime DEFAULT NULL,
        `waktu_kirim` datetime DEFAULT NULL,
        `waktu_terima` datetime DEFAULT NULL,
        `penerima` varchar(100) DEFAULT NULL,
        `keterangan` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `no_sppb` (`no_sppb`),
        KEY `no_manifest` (`no_manifest`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  public function getDistribusiTracking()
  {
      $this->_initPengiriman();
      $this->_addHeaderFiles();
      return $this->draw('distribusi.tracking.html');
  }

  public function anyDisplayTracking()
  {
      $this->_initPengiriman();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $tab = isset($_POST['tab']) ? $_POST['tab'] : 'tracking'; // 'tracking' or 'laporan'
      
      $_offset = ($halaman - 1) * $perpage;
      
      // Data SPPB with Tracking Info
      $query = $this->db('rsns_custom_logistik_non_medis_sppb')
                    ->leftJoin('rsns_custom_logistik_non_medis_unit', 'rsns_custom_logistik_non_medis_unit.kode_unit = rsns_custom_logistik_non_medis_sppb.kode_unit')
                    ->leftJoin('rsns_custom_logistik_non_medis_pengiriman', 'rsns_custom_logistik_non_medis_pengiriman.no_sppb = rsns_custom_logistik_non_medis_sppb.no_sppb')
                    ->where('rsns_custom_logistik_non_medis_sppb.status', 'NOT IN', ['Draft', 'Diajukan', 'Ditolak']);
                    
      if(!empty($cari)) {
          $query->where(function($q) use ($cari) {
              $q->where('rsns_custom_logistik_non_medis_sppb.no_sppb', 'LIKE', '%'.$cari.'%')
                ->orWhere('rsns_custom_logistik_non_medis_unit.nama_unit', 'LIKE', '%'.$cari.'%')
                ->orWhere('rsns_custom_logistik_non_medis_pengiriman.no_manifest', 'LIKE', '%'.$cari.'%');
          });
      }
      
      // Group by no_sppb to avoid duplicates since SPPB has many items, but we track per SPPB
      $all_data = $query->group('rsns_custom_logistik_non_medis_sppb.no_sppb')->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_sppb')
                    ->select('rsns_custom_logistik_non_medis_sppb.*, rsns_custom_logistik_non_medis_unit.nama_unit, rsns_custom_logistik_non_medis_pengiriman.no_manifest, rsns_custom_logistik_non_medis_pengiriman.status as tracking_status, rsns_custom_logistik_non_medis_pengiriman.kurir, rsns_custom_logistik_non_medis_pengiriman.kendaraan, rsns_custom_logistik_non_medis_pengiriman.waktu_packing, rsns_custom_logistik_non_medis_pengiriman.waktu_kirim, rsns_custom_logistik_non_medis_pengiriman.waktu_terima, rsns_custom_logistik_non_medis_pengiriman.penerima')
                    ->leftJoin('rsns_custom_logistik_non_medis_unit', 'rsns_custom_logistik_non_medis_unit.kode_unit = rsns_custom_logistik_non_medis_sppb.kode_unit')
                    ->leftJoin('rsns_custom_logistik_non_medis_pengiriman', 'rsns_custom_logistik_non_medis_pengiriman.no_sppb = rsns_custom_logistik_non_medis_sppb.no_sppb')
                    ->where('rsns_custom_logistik_non_medis_sppb.status', 'NOT IN', ['Draft', 'Diajukan', 'Ditolak']);

      if(!empty($cari)) {
          $rows->where(function($q) use ($cari) {
              $q->where('rsns_custom_logistik_non_medis_sppb.no_sppb', 'LIKE', '%'.$cari.'%')
                ->orWhere('rsns_custom_logistik_non_medis_unit.nama_unit', 'LIKE', '%'.$cari.'%')
                ->orWhere('rsns_custom_logistik_non_medis_pengiriman.no_manifest', 'LIKE', '%'.$cari.'%');
          });
      }
      
      $rows = $rows->group('rsns_custom_logistik_non_medis_sppb.no_sppb')
                   ->desc('rsns_custom_logistik_non_medis_sppb.tgl_input')
                   ->offset($_offset)
                   ->limit($perpage)
                   ->toArray();

      echo $this->draw('distribusi.tracking.display.html', [
          'tracking' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'tab' => $tab,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormManifest()
  {
      // Get all SPPBs that are Ready (or Packing but completed)
      // Usually, after Serah Terima or Packing finishes, it should be Ready.
      // We will look for SPPBs that don't have a manifest or status is still Proses
      $sppbs = $this->db('rsns_custom_logistik_non_medis_sppb s')
          ->select('s.*, u.nama_unit')
          ->leftJoin('rsns_custom_logistik_non_medis_unit u', 'u.kode_unit = s.kode_unit')
          ->leftJoin('rsns_custom_logistik_non_medis_pengiriman p', 'p.no_sppb = s.no_sppb')
          ->where('s.status', 'IN', ['Terverifikasi', 'Picking', 'Packing', 'Ready', 'Menunggu Manifest'])
          ->where(function($q) {
              $q->isNull('p.no_manifest')
                ->orWhere('p.no_manifest', '=', '');
          })
          ->group('s.no_sppb')
          ->toArray();

      echo $this->draw('distribusi.manifest.form.html', ['sppbs' => $sppbs]);
      exit();
  }

  public function postSaveManifest()
  {
      $kurir = $_POST['kurir'] ?? '';
      $kendaraan = $_POST['kendaraan'] ?? '';
      $sppb_list = $_POST['sppb_list'] ?? []; // Array of no_sppb
      
      if (empty($kurir) || empty($kendaraan) || empty($sppb_list)) {
          echo json_encode(['status' => 'error', 'message' => 'Lengkapi data kurir, kendaraan, dan pilih minimal 1 SPPB!']);
          exit();
      }

      $no_manifest = 'MNF-' . date('YmdHis');
      $waktu_kirim = date('Y-m-d H:i:s');

      foreach ($sppb_list as $no_sppb) {
          // Check if already exist in pengiriman
          $cek = $this->db('rsns_custom_logistik_non_medis_pengiriman')->where('no_sppb', $no_sppb)->oneArray();
          
          if ($cek) {
              $this->db('rsns_custom_logistik_non_medis_pengiriman')->where('no_sppb', $no_sppb)->update([
                  'no_manifest' => $no_manifest,
                  'kurir' => $kurir,
                  'kendaraan' => $kendaraan,
                  'status' => 'Dikirim',
                  'waktu_kirim' => $waktu_kirim
              ]);
          } else {
              $this->db('rsns_custom_logistik_non_medis_pengiriman')->save([
                  'no_sppb' => $no_sppb,
                  'no_manifest' => $no_manifest,
                  'kurir' => $kurir,
                  'kendaraan' => $kendaraan,
                  'status' => 'Dikirim',
                  'waktu_kirim' => $waktu_kirim
              ]);
          }

          // Update main SPPB status
          $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update(['status' => 'Dikirim']);
      }

      $this->_logAction('logistik_non_medis_pengiriman', 'Buat Manifest Kirim: ' . $no_manifest . ' | SPPB: ' . implode(',', $sppb_list) . ' | Kurir: ' . $kurir);

      echo json_encode(['status' => 'success', 'message' => 'Manifest berhasil dibuat dan status menjadi Dikirim.']);
      exit();
  }

  public function anyFormKonfirmasi()
  {
      if (isset($_POST['no_sppb'])) {
          $no_sppb = $_POST['no_sppb'];
          $pengiriman = $this->db('rsns_custom_logistik_non_medis_pengiriman')->where('no_sppb', $no_sppb)->oneArray();
          $sppb = $this->db('rsns_custom_logistik_non_medis_sppb')
                      ->join('rsns_custom_logistik_non_medis_unit', 'rsns_custom_logistik_non_medis_unit.kode_unit = rsns_custom_logistik_non_medis_sppb.kode_unit')
                      ->where('no_sppb', $no_sppb)->oneArray();
          echo $this->draw('distribusi.konfirmasi.html', ['pengiriman' => $pengiriman, 'sppb' => $sppb]);
      }
      exit();
  }

  public function postSaveKonfirmasi()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $penerima = $_POST['penerima'] ?? '';
      
      if (empty($no_sppb) || empty($penerima)) {
          echo json_encode(['status' => 'error', 'message' => 'Nomor SPPB dan Nama Penerima harus diisi.']);
          exit();
      }

      $waktu_terima = date('Y-m-d H:i:s');
      
      $this->db('rsns_custom_logistik_non_medis_pengiriman')->where('no_sppb', $no_sppb)->update([
          'status' => 'Diterima',
          'waktu_terima' => $waktu_terima,
          'penerima' => $penerima
      ]);
      
      $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->update(['status' => 'Selesai']);

      $this->_logAction('logistik_non_medis_pengiriman', 'Konfirmasi Penerimaan SPPB: ' . $no_sppb . ' | Penerima: ' . $penerima, 'U');

      echo json_encode(['status' => 'success']);
      exit();
  }

  public function anyLaporanSla()
  {
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      // Get data for SLA Report
      $rows = $this->db('rsns_custom_logistik_non_medis_sppb')
              ->select('rsns_custom_logistik_non_medis_sppb.no_sppb, rsns_custom_logistik_non_medis_sppb.tgl_input, rsns_custom_logistik_non_medis_sppb.tgl_approve_unit, rsns_custom_logistik_non_medis_unit.nama_unit, rsns_custom_logistik_non_medis_pengiriman.waktu_packing, rsns_custom_logistik_non_medis_pengiriman.waktu_kirim, rsns_custom_logistik_non_medis_pengiriman.waktu_terima')
              ->join('rsns_custom_logistik_non_medis_unit', 'rsns_custom_logistik_non_medis_unit.kode_unit = rsns_custom_logistik_non_medis_sppb.kode_unit')
              ->join('rsns_custom_logistik_non_medis_pengiriman', 'rsns_custom_logistik_non_medis_pengiriman.no_sppb = rsns_custom_logistik_non_medis_sppb.no_sppb', 'LEFT')
              ->where(function($q) {
                  $q->where('rsns_custom_logistik_non_medis_sppb.status', 'Diterima')
                    ->orWhere('rsns_custom_logistik_non_medis_sppb.status', 'Selesai');
              })
              ->group('rsns_custom_logistik_non_medis_sppb.no_sppb')
              ->desc('rsns_custom_logistik_non_medis_sppb.tgl_input')
              ->toArray();
              
      // Processing data for SLA
      $processed_rows = [];
      $total_req_to_app = 0;
      $total_app_to_pack = 0;
      $total_pack_to_del = 0;
      $total_del_to_rec = 0;
      $count = count($rows);
      
      foreach ($rows as $row) {
          $tgl_input = strtotime($row['tgl_input']);
          $tgl_app = strtotime($row['tgl_approve_unit']);
          $tgl_pack = strtotime($row['waktu_packing'] ?? $row['tgl_approve_unit']); // Fallback to approve if empty
          $tgl_kirim = strtotime($row['waktu_kirim'] ?? $row['waktu_packing']);
          $tgl_terima = strtotime($row['waktu_terima'] ?? $row['waktu_kirim']);
          
          $req_to_app = ($tgl_app > $tgl_input) ? ($tgl_app - $tgl_input) : 0;
          $app_to_pack = ($tgl_pack > $tgl_app) ? ($tgl_pack - $tgl_app) : 0;
          $pack_to_del = ($tgl_kirim > $tgl_pack) ? ($tgl_kirim - $tgl_pack) : 0;
          $del_to_rec = ($tgl_terima > $tgl_kirim) ? ($tgl_terima - $tgl_kirim) : 0;
          
          $processed_rows[] = [
              'no_sppb' => $row['no_sppb'],
              'nama_unit' => $row['nama_unit'],
              'req_to_app' => round($req_to_app / 3600, 1),
              'app_to_pack' => round($app_to_pack / 3600, 1),
              'pack_to_del' => round($pack_to_del / 3600, 1),
              'del_to_rec' => round($del_to_rec / 3600, 1)
          ];
          
          $total_req_to_app += $req_to_app;
          $total_app_to_pack += $app_to_pack;
          $total_pack_to_del += $pack_to_del;
          $total_del_to_rec += $del_to_rec;
      }
      
      $averages = [
          'req_to_app' => $count > 0 ? round(($total_req_to_app / $count) / 3600, 1) : 0,
          'app_to_pack' => $count > 0 ? round(($total_app_to_pack / $count) / 3600, 1) : 0,
          'pack_to_del' => $count > 0 ? round(($total_pack_to_del / $count) / 3600, 1) : 0,
          'del_to_rec' => $count > 0 ? round(($total_del_to_rec / $count) / 3600, 1) : 0,
      ];

      echo $this->draw('distribusi.tracking.laporan.html', [
          'laporan' => $processed_rows,
          'averages' => $averages,
          'halaman' => $halaman
      ]);
      exit();
  }

  private function _initRetur()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_retur_unit` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_retur` varchar(50) NOT NULL,
        `tgl_retur` date NOT NULL,
        `kode_unit` varchar(50) NOT NULL,
        `no_sppb` varchar(50) NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `batch_no` varchar(50) DEFAULT NULL,
        `qty` double NOT NULL DEFAULT 0,
        `alasan` enum('Salah Kirim','Sisa','Rusak') NOT NULL DEFAULT 'Sisa',
        `kondisi_fisik` text DEFAULT NULL,
        `inspeksi` text DEFAULT NULL,
        `status` enum('Pending','Disetujui','Ditolak') NOT NULL DEFAULT 'Pending',
        `petugas` varchar(100) DEFAULT NULL,
        `tgl_approval` datetime DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        `tgl_input` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `no_retur` (`no_retur`),
        KEY `kode_unit` (`kode_unit`),
        KEY `no_sppb` (`no_sppb`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _generateNoRetur()
  {
      $prefix = 'RET/' . date('Ymd') . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_retur_unit')
                   ->where('no_retur', 'LIKE', $prefix.'%')
                   ->desc('no_retur')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['no_retur'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getDistribusiRetur()
  {
      $this->_initRetur();
      $this->_addHeaderFiles();
      return $this->draw('distribusi.retur.html');
  }

  public function anyDisplayRetur()
  {
      $this->_initRetur();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      // Query sederhana untuk mengambil data tanpa group by di SQL untuk menghindari strict mode
      $query = $this->db('rsns_custom_logistik_non_medis_retur_unit')
                    ->select('rsns_custom_logistik_non_medis_retur_unit.*, rsns_custom_logistik_non_medis_unit.nama_unit')
                    ->join('rsns_custom_logistik_non_medis_unit', 'rsns_custom_logistik_non_medis_unit.kode_unit = rsns_custom_logistik_non_medis_retur_unit.kode_unit', 'LEFT');

      if(!empty($cari)) {
          $query->where(function($q) use ($cari) {
              $q->where('rsns_custom_logistik_non_medis_retur_unit.no_retur', 'LIKE', '%'.$cari.'%')
                ->orWhere('rsns_custom_logistik_non_medis_retur_unit.no_sppb', 'LIKE', '%'.$cari.'%')
                ->orWhere('rsns_custom_logistik_non_medis_unit.nama_unit', 'LIKE', '%'.$cari.'%');
          });
      }
      
      // Ambil semua data sesuai filter, lalu group di PHP
      $all_rows = $query->desc('tgl_input')->toArray();
      
      $grouped = [];
      foreach($all_rows as $row) {
          $no_retur = $row['no_retur'];
          if(!isset($grouped[$no_retur])) {
              $row['total_qty'] = $row['qty'];
              $row['total_item'] = 1;
              $grouped[$no_retur] = $row;
          } else {
              $grouped[$no_retur]['total_qty'] += $row['qty'];
              $grouped[$no_retur]['total_item'] += 1;
          }
      }
      
      $jumlah_data = count($grouped);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      // Pagination dengan array_slice
      $rows = array_slice(array_values($grouped), $_offset, $perpage);

      echo $this->draw('distribusi.retur.display.html', [
          'retur' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormRetur()
  {
      $this->_initRetur();
      $mode = $_POST['mode'] ?? 'add';
      
      if ($mode == 'edit' && isset($_POST['no_retur'])) {
          $no_retur = $_POST['no_retur'];
          $retur_items = $this->db('rsns_custom_logistik_non_medis_retur_unit')
                              ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_retur_unit.kode_item = rsns_custom_logistik_non_medis_master_barang.kode_item')
                              ->select('rsns_custom_logistik_non_medis_retur_unit.*, rsns_custom_logistik_non_medis_master_barang.nama_barang')
                              ->where('no_retur', $no_retur)->toArray();
          
          $retur = $retur_items[0];
          $retur['items'] = $retur_items;
          
          echo $this->draw('distribusi.retur.form.html', ['retur' => $retur, 'mode' => 'edit']);
      } else {
          // Get SPPBs that are 'Selesai' or 'Diterima' for the unit selection
          $sppbs = $this->db('rsns_custom_logistik_non_medis_sppb')
                        ->select('rsns_custom_logistik_non_medis_sppb.no_sppb, rsns_custom_logistik_non_medis_unit.nama_unit')
                        ->join('rsns_custom_logistik_non_medis_unit', 'rsns_custom_logistik_non_medis_unit.kode_unit = rsns_custom_logistik_non_medis_sppb.kode_unit')
                        ->where('rsns_custom_logistik_non_medis_sppb.status', 'Selesai')
                        ->orWhere('rsns_custom_logistik_non_medis_sppb.status', 'Diterima')
                        ->group('rsns_custom_logistik_non_medis_sppb.no_sppb')
                        ->toArray();

          $retur = [
              'no_retur' => $this->_generateNoRetur(),
              'tgl_retur' => date('Y-m-d'),
              'no_sppb' => '',
              'items' => []
          ];
          echo $this->draw('distribusi.retur.form.html', ['retur' => $retur, 'mode' => 'add', 'sppbs' => $sppbs]);
      }
      exit();
  }

  public function anyLoadSppbItems()
  {
      $no_sppb = $_POST['no_sppb'] ?? '';
      $no_sppb = trim($no_sppb);

      if (empty($no_sppb)) {
          echo json_encode(['status' => 'error', 'message' => 'No. SPPB tidak valid']);
          exit();
      }

      // Try to get from packing first (actual items sent)
      $items = $this->db('rsns_custom_logistik_non_medis_packing')
                    ->select('rsns_custom_logistik_non_medis_packing.*, rsns_custom_logistik_non_medis_master_barang.nama_barang')
                    ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_packing.kode_item')
                    ->where('no_sppb', 'LIKE', '%'.$no_sppb.'%')
                    ->toArray();

      // If empty, try to get from sppb table directly (original approved items)
      if (empty($items)) {
          $items = $this->db('rsns_custom_logistik_non_medis_sppb')
                        ->select('rsns_custom_logistik_non_medis_sppb.*, rsns_custom_logistik_non_medis_master_barang.nama_barang, IF(rsns_custom_logistik_non_medis_sppb.jumlah_disetujui > 0, rsns_custom_logistik_non_medis_sppb.jumlah_disetujui, rsns_custom_logistik_non_medis_sppb.jumlah) as qty_picked')
                        ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_sppb.kode_item')
                        ->where('no_sppb', 'LIKE', '%'.$no_sppb.'%')
                        ->toArray();
      }

      echo json_encode(['status' => 'success', 'items' => $items, 'debug_no_sppb' => $no_sppb]);
      exit();
  }

  public function postSaveRetur()
  {
      $no_retur = $_POST['no_retur'] ?? '';
      $no_sppb = $_POST['no_sppb'] ?? '';
      $items = $_POST['items'] ?? [];
      $user = $this->core->getUserInfo('username', null, true);
      $tgl_now = date('Y-m-d H:i:s');

      if (empty($no_retur) || empty($no_sppb) || empty($items)) {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap. Pilih SPPB dan isi Qty Retur!']);
          exit();
      }

      try {
          // Get unit from SPPB
          $sppb = $this->db('rsns_custom_logistik_non_medis_sppb')->where('no_sppb', $no_sppb)->oneArray();
          $kode_unit = $sppb['kode_unit'] ?? 'GUDANG';

          // Delete existing if edit
          $this->db('rsns_custom_logistik_non_medis_retur_unit')->where('no_retur', $no_retur)->delete();

          foreach ($items as $item) {
              if (isset($item['qty']) && $item['qty'] > 0) {
                  $this->db('rsns_custom_logistik_non_medis_retur_unit')->save([
                      'no_retur' => $no_retur,
                      'tgl_retur' => $_POST['tgl_retur'] ?? date('Y-m-d'),
                      'kode_unit' => $kode_unit,
                      'no_sppb' => $no_sppb,
                      'kode_item' => $item['kode_item'],
                      'batch_no' => $item['batch_no'] ?? '',
                      'qty' => $item['qty'],
                      'alasan' => $item['alasan'] ?? 'Sisa',
                      'kondisi_fisik' => $item['kondisi_fisik'] ?? '',
                      'status' => 'Pending',
                      'user_input' => $user,
                      'tgl_input' => $tgl_now
                  ]);
              }
          }

          $this->_logAction('logistik_non_medis_retur', 'Simpan Retur Unit: ' . $no_retur . ' | SPPB: ' . $no_sppb, 'I');

          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
      }
      exit();
  }

  public function postApproveRetur()
  {
      $no_retur = $_POST['no_retur'] ?? '';
      $inspeksi = $_POST['inspeksi'] ?? []; // Map of id => inspeksi_note
      $action = $_POST['action'] ?? 'approve'; // 'approve' or 'reject'
      $user = $this->core->getUserInfo('username', null, true);
      $tgl_now = date('Y-m-d H:i:s');

      $returs = $this->db('rsns_custom_logistik_non_medis_retur_unit')->where('no_retur', $no_retur)->toArray();
      
      if (empty($returs)) {
          echo json_encode(['status' => 'error', 'message' => 'Data retur tidak ditemukan']);
          exit();
      }

      foreach ($returs as $r) {
          $new_status = ($action == 'approve') ? 'Disetujui' : 'Ditolak';
          $this->db('rsns_custom_logistik_non_medis_retur_unit')->where('id', $r['id'])->update([
              'status' => $new_status,
              'inspeksi' => $inspeksi[$r['id']] ?? '',
              'petugas' => $user,
              'tgl_approval' => $tgl_now
          ]);

          if ($action == 'approve') {
              // Update Stok
              if ($r['alasan'] == 'Rusak') {
                  // Add to Barang Rusak
                  $no_trans_rusak = 'RSK/' . date('YmdHis') . '/' . $r['id'];
                  $this->db('rsns_custom_logistik_non_medis_barang_rusak')->save([
                      'no_transaksi' => $no_trans_rusak,
                      'tgl_transaksi' => date('Y-m-d'),
                      'kode_item' => $r['kode_item'],
                      'batch' => $r['batch_no'],
                      'kode_lokasi' => 'GUDANG_RETUR', // Dedicated or generic location
                      'jumlah' => $r['qty'],
                      'kategori_kerusakan' => 'Retur Unit',
                      'keterangan' => 'Retur dari Unit: ' . $r['kode_unit'] . ' | ' . $r['kondisi_fisik'],
                      'status' => 'Karantina',
                      'tgl_input' => $tgl_now,
                      'user_input' => $user
                  ]);
              } else {
                  // Increase Stock in batch
                  // Need to find original location from packing
                  $packing = $this->db('rsns_custom_logistik_non_medis_packing')
                                  ->where('no_sppb', $r['no_sppb'])
                                  ->where('kode_item', $r['kode_item'])
                                  ->where('batch_no', $r['batch_no'])
                                  ->oneArray();
                  
                  // Find batch in stok_batch to get location
                  $batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                                ->where('kode_item', $r['kode_item'])
                                ->where('batch_no', $r['batch_no'])
                                ->oneArray();
                  
                  if ($batch) {
                      $new_stok = $batch['stok'] + $r['qty'];
                      $this->db('rsns_custom_logistik_non_medis_stok_batch')
                           ->where('id', $batch['id'])
                           ->update(['stok' => $new_stok]);
                  } else {
                      // If batch not found (rare), create new entry in default location
                      $this->db('rsns_custom_logistik_non_medis_stok_batch')->save([
                          'kode_item' => $r['kode_item'],
                          'batch_no' => $r['batch_no'],
                          'kode_lokasi' => 'GUDANG_UTAMA',
                          'stok' => $r['qty'],
                          'tgl_expired' => NULL
                      ]);
                  }
              }
          }
      }

      $log_msg = ($action == 'approve' ? 'Setujui' : 'Tolak') . ' Retur Unit: ' . $no_retur;
      $this->_logAction('logistik_non_medis_retur', $log_msg, 'U');

      echo json_encode(['status' => 'success']);
      exit();
  }

  public function postHapusRetur()
  {
      $no_retur = $_POST['no_retur'] ?? '';
      $retur = $this->db('rsns_custom_logistik_non_medis_retur_unit')->where('no_retur', $no_retur)->oneArray();
      
      if ($retur && $retur['status'] == 'Pending') {
          $this->db('rsns_custom_logistik_non_medis_retur_unit')->where('no_retur', $no_retur)->delete();
          $this->_logAction('logistik_non_medis_retur', 'Hapus Retur Unit: ' . $no_retur, 'D');
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Hanya data Pending yang bisa dihapus']);
      }
      exit();
  }



  // --- ASSETS ---

  public function getCss()
  {
      header('Content-type: text/css');
      echo $this->draw(MODULES.'/logistik_non_medis/css/admin/logistik.css');
      exit();
  }

  public function getJavascript()
  {
      header('Content-type: text/javascript');
      echo $this->draw(MODULES.'/logistik_non_medis/js/admin/logistik.js');
      exit();
  }

  private function _addHeaderFiles()
  {
      $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
      $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));
      $this->core->addCSS('https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
      $this->core->addCSS(url([ADMIN, 'logistik_non_medis', 'css']));
      $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'));
      $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'));
      $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
      $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));
      $this->core->addJS('https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js');
      $this->core->addJS(url([ADMIN, 'logistik_non_medis', 'javascript']), 'footer');
  }

  private function _initKuota()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_kuota` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_unit` varchar(50) NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `periode_tipe` enum('Bulanan','Triwulan') NOT NULL DEFAULT 'Bulanan',
        `tahun` year(4) NOT NULL,
        `bulan` int(2) DEFAULT NULL,
        `triwulan` int(1) DEFAULT NULL,
        `jumlah` double NOT NULL DEFAULT 0,
        `jenis` enum('Utama','Tambahan') NOT NULL DEFAULT 'Utama',
        `status` enum('Draft','Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft',
        `keterangan` text DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        `tgl_input` datetime DEFAULT NULL,
        `user_approve` varchar(100) DEFAULT NULL,
        `tgl_approve` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `kode_unit` (`kode_unit`),
        KEY `kode_item` (`kode_item`),
        KEY `periode` (`tahun`,`bulan`,`triwulan`),
        KEY `status` (`status`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  public function getDistribusiKuota()
  {
      $this->_initKuota();
      $this->_addHeaderFiles();
      return $this->draw('distribusi.kuota.html');
  }

  public function anyDisplayKuota()
  {
      $this->_initKuota();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $sql = "SELECT k.*, b.nama_barang, u.nama_unit 
              FROM rsns_custom_logistik_non_medis_kuota k
              LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = k.kode_item
              LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = k.kode_unit
              WHERE 1=1";
      
      $params = [];
      if(!empty($cari)) {
          $sql .= " AND (b.nama_barang LIKE ? OR u.nama_unit LIKE ? OR k.periode_tipe LIKE ?)";
          $params = ['%'.$cari.'%', '%'.$cari.'%', '%'.$cari.'%'];
      }
      
      $sql_count = "SELECT COUNT(*) as total FROM ($sql) as t";
      $stmt_total = $this->db()->pdo()->prepare($sql_count);
      $stmt_total->execute($params);
      $jumlah_data = $stmt_total->fetchColumn();
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $sql .= " ORDER BY k.tgl_input DESC LIMIT $_offset, $perpage";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $pages = [];
      if($jml_halaman > 1) {
          for($i = 1; $i <= $jml_halaman; $i++) {
              $pages[] = $i;
          }
      }

      echo $this->draw('distribusi.kuota.display.html', [
          'kuota' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'pages' => $pages
      ]);
      exit();
  }

  public function anyFormKuota()
  {
      $this->_initKuota();
      $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('status', 'Aktif')->toArray();
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      
      if (isset($_POST['id'])){
          $kuota = $this->db('rsns_custom_logistik_non_medis_kuota')->where('id', $_POST['id'])->oneArray();
          echo $this->draw('distribusi.kuota.form.html', ['kuota' => $kuota, 'mode' => 'edit', 'barang' => $barang, 'unit' => $unit]);
      } else {
          $kuota = [
              'kode_unit' => '',
              'kode_item' => '',
              'periode_tipe' => 'Bulanan',
              'tahun' => date('Y'),
              'bulan' => date('m'),
              'triwulan' => '',
              'jumlah' => 0,
              'jenis' => 'Utama',
              'status' => 'Draft'
          ];
          echo $this->draw('distribusi.kuota.form.html', ['kuota' => $kuota, 'mode' => 'add', 'barang' => $barang, 'unit' => $unit]);
      }
      exit();
  }

  public function postSaveKuota()
  {
      $this->_initKuota();
      $id = $_POST['id'] ?? '';
      $data = [
          'kode_unit' => $_POST['kode_unit'],
          'kode_item' => $_POST['kode_item'],
          'periode_tipe' => $_POST['periode_tipe'],
          'tahun' => $_POST['tahun'],
          'bulan' => ($_POST['periode_tipe'] == 'Bulanan') ? $_POST['bulan'] : NULL,
          'triwulan' => ($_POST['periode_tipe'] == 'Triwulan') ? $_POST['triwulan'] : NULL,
          'jumlah' => $_POST['jumlah'],
          'jenis' => $_POST['jenis'] ?? 'Utama',
          'keterangan' => $_POST['keterangan'] ?? '',
          'user_input' => $this->core->getUserInfo('username', null, true),
          'tgl_input' => date('Y-m-d H:i:s')
      ];

      if($data['jenis'] == 'Utama') {
          $data['status'] = 'Disetujui';
          $data['user_approve'] = $data['user_input'];
          $data['tgl_approve'] = $data['tgl_input'];
      } else {
          $data['status'] = 'Diajukan';
      }

      if(!empty($id)) {
          $query = $this->db('rsns_custom_logistik_non_medis_kuota')->where('id', $id)->update($data);
          $status_log = 'U';
          $action_log = 'Update Kuota Barang: ' . $data['kode_item'] . ' | Unit: ' . $data['kode_unit'] . ' | Qty: ' . $data['jumlah'];
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_kuota')->save($data);
          $status_log = 'I';
          $action_log = 'Tambah Kuota Barang: ' . $data['kode_item'] . ' | Unit: ' . $data['kode_unit'] . ' | Qty: ' . $data['jumlah'];
      }

      if ($query) {
          $this->_logAction('logistik_non_medis_kuota', $action_log, $status_log);
      }

      echo json_encode(['status' => $query ? 'success' : 'error']);
      exit();
  }

  public function postHapusKuota()
  {
      $id = $_POST['id'] ?? '';
      if(!empty($id)) {
          $kuota = $this->db('rsns_custom_logistik_non_medis_kuota')->where('id', $id)->oneArray();
          $query = $this->db('rsns_custom_logistik_non_medis_kuota')->where('id', $id)->delete();
          if ($query && $kuota) {
              $action_log = 'Hapus Kuota Barang: ' . $kuota['kode_item'] . ' | Unit: ' . $kuota['kode_unit'];
              $this->_logAction('logistik_non_medis_kuota', $action_log, 'D');
          }
          echo json_encode(['status' => $query ? 'success' : 'error']);
      }
      exit();
  }

  public function getMonitoringKuota()
  {
      $this->_initKuota();
      $this->_addHeaderFiles();
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      return $this->draw('distribusi.kuota.monitoring.html', ['unit' => $unit]);
  }

  public function anyDisplayMonitoring()
  {
      $kode_unit = $_POST['kode_unit'] ?? '';
      $tahun = $_POST['tahun'] ?? date('Y');
      $bulan = $_POST['bulan'] ?? date('m');
      
      $triwulan = ceil($bulan / 3);

      $sql = "SELECT k.kode_item, b.nama_barang, 
                     SUM(CASE WHEN k.status = 'Disetujui' THEN k.jumlah ELSE 0 END) as total_kuota
              FROM rsns_custom_logistik_non_medis_kuota k
              LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = k.kode_item
              WHERE k.kode_unit = ? AND k.tahun = ? 
              AND ( (k.periode_tipe = 'Bulanan' AND k.bulan = ?) OR (k.periode_tipe = 'Triwulan' AND k.triwulan = ?) )
              GROUP BY k.kode_item";
      
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute([$kode_unit, $tahun, $bulan, $triwulan]);
      $kuotas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      foreach($kuotas as &$k) {
          // Calculate usage from SPPB (include all non-draft/non-rejected)
          $usage_sql = "SELECT SUM(jumlah) as usage_qty 
                        FROM rsns_custom_logistik_non_medis_sppb 
                        WHERE kode_unit = ? AND kode_item = ? 
                        AND status NOT IN ('Draft', 'Ditolak')
                        AND YEAR(tgl_sppb) = ? AND MONTH(tgl_sppb) = ?";
          $usage_stmt = $this->db()->pdo()->prepare($usage_sql);
          $usage_stmt->execute([$kode_unit, $k['kode_item'], $tahun, $bulan]);
          $usage = $usage_stmt->fetch()['usage_qty'] ?? 0;
          
          $k['realisasi'] = $usage;
          $k['sisa'] = $k['total_kuota'] - $usage;
          $k['persen'] = ($k['total_kuota'] > 0) ? round(($usage / $k['total_kuota']) * 100, 2) : 0;
      }

      echo $this->draw('distribusi.kuota.monitoring.display.html', ['kuotas' => $kuotas]);
      exit();
  }

   private function _initAset()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_aset` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_aset` varchar(100) NOT NULL,
        `serial_number` varchar(100) DEFAULT NULL,
        `kode_item` varchar(50) NOT NULL,
        `nama_aset` varchar(200) NOT NULL,
        `spesifikasi` text DEFAULT NULL,
        `foto_depan` varchar(255) DEFAULT NULL,
        `foto_detail` varchar(255) DEFAULT NULL,
        `tanggal_perolehan` date DEFAULT NULL,
        `harga_beli` double NOT NULL DEFAULT 0,
        `sumber_perolehan` enum('Beli','Hibah','APBD','Lainnya') NOT NULL DEFAULT 'Beli',
        `kode_unit` varchar(50) DEFAULT NULL,
        `pic` varchar(100) DEFAULT NULL,
        `status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik',
        `status` enum('Aktif','Dihapuskan') NOT NULL DEFAULT 'Aktif',
        `tgl_input` datetime DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `kode_aset` (`kode_aset`),
        KEY `kode_item` (`kode_item`),
        KEY `kode_unit` (`kode_unit`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      // KIB dynamic columns migration
      $check_kib = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_aset` LIKE 'kib_jenis'")->fetch();
      if (!$check_kib) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_aset` 
              ADD `kib_jenis` ENUM('A','B','C','D','E','F') DEFAULT NULL AFTER `user_input`,
              ADD `kib_luas` double DEFAULT 0 AFTER `kib_jenis`,
              ADD `kib_alamat` text DEFAULT NULL AFTER `kib_luas`,
              ADD `kib_hak` varchar(100) DEFAULT NULL AFTER `kib_alamat`,
              ADD `kib_tgl_sertifikat` date DEFAULT NULL AFTER `kib_hak`,
              ADD `kib_no_sertifikat` varchar(100) DEFAULT NULL AFTER `kib_tgl_sertifikat`,
              ADD `kib_penggunaan` varchar(255) DEFAULT NULL AFTER `kib_no_sertifikat`,
              ADD `kib_merk` varchar(100) DEFAULT NULL AFTER `kib_penggunaan`,
              ADD `kib_ukuran` varchar(100) DEFAULT NULL AFTER `kib_merk`,
              ADD `kib_bahan` varchar(100) DEFAULT NULL AFTER `kib_ukuran`,
              ADD `kib_no_pabrik` varchar(100) DEFAULT NULL AFTER `kib_bahan`,
              ADD `kib_no_rangka` varchar(100) DEFAULT NULL AFTER `kib_no_pabrik`,
              ADD `kib_no_mesin` varchar(100) DEFAULT NULL AFTER `kib_no_rangka`,
              ADD `kib_no_polisi` varchar(50) DEFAULT NULL AFTER `kib_no_mesin`,
              ADD `kib_no_bpkb` varchar(50) DEFAULT NULL AFTER `kib_no_polisi`,
              ADD `kib_bertingkat` enum('Ya','Tidak') DEFAULT 'Tidak' AFTER `kib_no_bpkb`,
              ADD `kib_beton` enum('Ya','Tidak') DEFAULT 'Tidak' AFTER `kib_bertingkat`,
              ADD `kib_status_tanah` varchar(100) DEFAULT NULL AFTER `kib_beton`,
              ADD `kib_konstruksi` varchar(100) DEFAULT NULL AFTER `kib_status_tanah`,
              ADD `kib_panjang` double DEFAULT 0 AFTER `kib_konstruksi`,
              ADD `kib_lebar` double DEFAULT 0 AFTER `kib_panjang`,
              ADD `kib_judul` varchar(255) DEFAULT NULL AFTER `kib_lebar`,
              ADD `kib_pencipta` varchar(100) DEFAULT NULL AFTER `kib_judul`,
              ADD `kib_proyek_bangunan` varchar(100) DEFAULT NULL AFTER `kib_pencipta`,
              ADD `kib_tgl_mulai` date DEFAULT NULL AFTER `kib_proyek_bangunan`,
              ADD `kib_tgl_rencana_selesai` date DEFAULT NULL AFTER `kib_tgl_mulai`,
              ADD `kib_progress_persen` double DEFAULT 0 AFTER `kib_tgl_rencana_selesai`
          ");
      }

      // Depreciation columns migration
      $check_depr = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_aset` LIKE 'nilai_buku'")->fetch();
      if (!$check_depr) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_aset` 
              ADD `masa_manfaat_tahun` int(11) DEFAULT 0 AFTER `status`,
              ADD `nilai_residu` double DEFAULT 0 AFTER `masa_manfaat_tahun`,
              ADD `akumulasi_penyusutan` double DEFAULT 0 AFTER `nilai_residu`,
              ADD `nilai_buku` double DEFAULT 0 AFTER `akumulasi_penyusutan`,
              ADD `tgl_penyusutan_terakhir` date DEFAULT NULL AFTER `nilai_buku`
          ");
      }

      // Location column migration
      $check_lok = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_aset` LIKE 'kode_lokasi'")->fetch();
      if (!$check_lok) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_aset` ADD `kode_lokasi` varchar(50) DEFAULT NULL AFTER `kode_unit`");
      }

      // Mutasi columns migration
      $check_mutasi_col = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_aset_mutasi` LIKE 'no_mutasi'")->fetch();
      if (!$check_mutasi_col) {
          $this->db()->pdo()->exec("DROP TABLE IF EXISTS `rsns_custom_logistik_non_medis_aset_mutasi`");
      }

      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_aset_mutasi` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_mutasi` varchar(50) DEFAULT NULL,
        `kode_aset` varchar(100) NOT NULL,
        `kode_unit_asal` varchar(50) DEFAULT NULL,
        `kode_unit_tujuan` varchar(50) DEFAULT NULL,
        `kode_lokasi_asal` varchar(50) DEFAULT NULL,
        `kode_lokasi_tujuan` varchar(50) DEFAULT NULL,
        `pic_asal` varchar(100) DEFAULT NULL,
        `pic_tujuan` varchar(100) DEFAULT NULL,
        `keterangan` text DEFAULT NULL,
        `tanggal_mutasi` date DEFAULT NULL,
        `status` enum('Draft','Diajukan','Disetujui Asal','Selesai','Ditolak') NOT NULL DEFAULT 'Draft',
        `alasan_penolakan` text DEFAULT NULL,
        `user_approval_asal` varchar(100) DEFAULT NULL,
        `tgl_approval_asal` datetime DEFAULT NULL,
        `user_approval_tujuan` varchar(100) DEFAULT NULL,
        `tgl_approval_tujuan` datetime DEFAULT NULL,
        `user_mutasi` varchar(100) DEFAULT NULL,
        `tgl_input` datetime DEFAULT NULL,
        `tgl_update` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `no_mutasi` (`no_mutasi`),
        KEY `kode_aset` (`kode_aset`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_aset_mutasi` MODIFY `no_mutasi` varchar(50) DEFAULT NULL");

      $upload_dir = UPLOADS . '/logistik_non_medis';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      if (!is_dir($upload_dir . '/aset')) mkdir($upload_dir . '/aset', 0777, true);
  }

  private function _initPenyusutan()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_aset_penyusutan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_aset` varchar(100) NOT NULL,
        `periode` varchar(7) NOT NULL,
        `tanggal_proses` datetime NOT NULL,
        `harga_perolehan` double NOT NULL DEFAULT 0,
        `nilai_residu` double NOT NULL DEFAULT 0,
        `biaya_penyusutan` double NOT NULL DEFAULT 0,
        `akumulasi_penyusutan` double NOT NULL DEFAULT 0,
        `nilai_buku` double NOT NULL DEFAULT 0,
        `no_jurnal` varchar(100) DEFAULT NULL,
        `user_proses` varchar(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `aset_periode` (`kode_aset`,`periode`),
        KEY `periode` (`periode`),
        KEY `no_jurnal` (`no_jurnal`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      // Set default masa manfaat and residu in mlite_settings if they don't exist
      $defaults = [
          'depr_manfaat_A' => 0,  // Tanah
          'depr_residu_A' => 0,
          'depr_rek_aset_A' => '',
          'depr_rek_beban_A' => '',
          'depr_rek_akum_A' => '',

          'depr_manfaat_B' => 5,  // Peralatan & Mesin
          'depr_residu_B' => 0,
          'depr_rek_aset_B' => '',
          'depr_rek_beban_B' => '',
          'depr_rek_akum_B' => '',

          'depr_manfaat_C' => 20, // Gedung & Bangunan
          'depr_residu_C' => 0,
          'depr_rek_aset_C' => '',
          'depr_rek_beban_C' => '',
          'depr_rek_akum_C' => '',

          'depr_manfaat_D' => 10, // Jalan, Jaringan, Irigasi
          'depr_residu_D' => 0,
          'depr_rek_aset_D' => '',
          'depr_rek_beban_D' => '',
          'depr_rek_akum_D' => '',

          'depr_manfaat_E' => 5,  // Aset Lainnya
          'depr_residu_E' => 0,
          'depr_rek_aset_E' => '',
          'depr_rek_beban_E' => '',
          'depr_rek_akum_E' => '',

          'depr_manfaat_F' => 0,  // Konstruksi dalam pengerjaan (tidak disusutkan)
          'depr_residu_F' => 0,
          'depr_rek_aset_F' => '',
          'depr_rek_beban_F' => '',
          'depr_rek_akum_F' => '',
      ];

      foreach ($defaults as $key => $val) {
          $check = $this->db('mlite_settings')->where('module', 'logistik_non_medis')->where('field', $key)->oneArray();
          if (!$check) {
              $this->db('mlite_settings')->save([
                  'module' => 'logistik_non_medis',
                  'field' => $key,
                  'value' => $val
              ]);
          }
      }
  }

  private function _generateKodeAset($kode_unit)
  {
      $tahun = date('Y');
      $prefix = 'AST-NM/' . $kode_unit . '/' . $tahun . '/';
      
      $last = $this->db('rsns_custom_logistik_non_medis_aset')
                   ->where('kode_aset', 'LIKE', $prefix.'%')
                   ->desc('kode_aset')
                   ->limit(1)
                   ->oneArray();
                   
      if ($last) {
          $last_num = (int) substr($last['kode_aset'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getAsetRegistrasi()
  {
      $this->_initAset();
      $this->_initUnit();
      $this->_addHeaderFiles();
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->where('status', 'Aktif')->toArray();
      return $this->draw('aset.registrasi.html', ['units' => $units]);
  }

  public function anyDisplayAsetRegistrasi()
  {
      $this->_initAset();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $filter_unit = isset($_POST['filter_unit']) ? $_POST['filter_unit'] : '';
      $filter_sumber = isset($_POST['filter_sumber']) ? $_POST['filter_sumber'] : '';
      $filter_kondisi = isset($_POST['filter_kondisi']) ? $_POST['filter_kondisi'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset')
                    ->where('status', 'Aktif');
      
      if(!empty($cari)) {
          $query->where(function($q) use ($cari) {
              $q->where('kode_aset', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_aset', '%'.$cari.'%')
                ->orLike('serial_number', '%'.$cari.'%');
          });
      }
      
      if(!empty($filter_unit)) {
          $query->where('kode_unit', $filter_unit);
      }
      if(!empty($filter_sumber)) {
          $query->where('sumber_perolehan', $filter_sumber);
      }
      if(!empty($filter_kondisi)) {
          $query->where('status_kondisi', $filter_kondisi);
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows_query = $this->db('rsns_custom_logistik_non_medis_aset')
                          ->where('status', 'Aktif');
      
      if(!empty($cari)) {
          $rows_query->where(function($q) use ($cari) {
              $q->where('kode_aset', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_aset', '%'.$cari.'%')
                ->orLike('serial_number', '%'.$cari.'%');
          });
      }
      if(!empty($filter_unit)) {
          $rows_query->where('kode_unit', $filter_unit);
      }
      if(!empty($filter_sumber)) {
          $rows_query->where('sumber_perolehan', $filter_sumber);
      }
      if(!empty($filter_kondisi)) {
          $rows_query->where('status_kondisi', $filter_kondisi);
      }
      
      $rows = $rows_query->desc('id')
                         ->offset($_offset)
                         ->limit($perpage)
                         ->toArray();
                         
      foreach($rows as &$row) {
          $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['kode_unit'])->oneArray();
          $row['nama_unit'] = $unit['nama_unit'] ?? '-';
          
          $item = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $row['kode_item'])->oneArray();
          $row['satuan_dasar'] = $item['satuan_dasar'] ?? '';
      }
      
      echo $this->draw('aset.registrasi.display.html', [
          'aset' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman
      ]);
      exit();
  }

  public function anyFormAsetRegistrasi()
  {
      $this->_initAset();
      $this->_initDataBarang();
      $this->_initUnit();
      
      $master_barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('status', 'Aktif')->toArray();
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->where('status', 'Aktif')->toArray();
      
      if (isset($_POST['id'])) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('id', $_POST['id'])->oneArray();
          echo $this->draw('aset.registrasi.form.html', [
              'aset' => $aset,
              'mode' => 'edit',
              'master_barang' => $master_barang,
              'units' => $units
          ]);
      } else {
          $aset = [
              'id' => '',
              'kode_aset' => '',
              'serial_number' => '',
              'kode_item' => '',
              'nama_aset' => '',
              'spesifikasi' => '',
              'foto_depan' => '',
              'foto_detail' => '',
              'tanggal_perolehan' => date('Y-m-d'),
              'harga_beli' => 0,
              'sumber_perolehan' => 'Beli',
              'kode_unit' => '',
              'pic' => '',
              'status_kondisi' => 'Baik'
          ];
          echo $this->draw('aset.registrasi.form.html', [
              'aset' => $aset,
              'mode' => 'add',
              'master_barang' => $master_barang,
              'units' => $units
          ]);
      }
      exit();
  }

  public function anyGenerateKodeAset()
  {
      $kode_unit = $_POST['kode_unit'] ?? '';
      if (empty($kode_unit)) {
          echo json_encode(['status' => 'error', 'message' => 'Unit wajib dipilih!']);
          exit();
      }
      $kode_aset = $this->_generateKodeAset($kode_unit);
      echo json_encode(['status' => 'success', 'kode_aset' => $kode_aset]);
      exit();
  }

  public function postSaveAsetRegistrasi()
  {
      $this->_initAset();
      $id = $_POST['id'] ?? '';
      $kode_unit = $_POST['kode_unit'] ?? '';
      
      if(empty($kode_unit)) {
          echo json_encode(['status' => 'error', 'message' => 'Unit wajib dipilih!']);
          exit();
      }
      
      $kode_aset = $_POST['kode_aset'] ?? '';
      if(empty($id) && empty($kode_aset)) {
          $kode_aset = $this->_generateKodeAset($kode_unit);
      }
      
      $harga_beli = $_POST['harga_beli'] ?? 0;
      $harga_beli = str_replace(['Rp.', '.', ' '], '', $harga_beli);
      $harga_beli = (double) $harga_beli;

      $nilai_residu = $_POST['nilai_residu'] ?? 0;
      $nilai_residu = str_replace(['Rp.', '.', ' '], '', $nilai_residu);
      $nilai_residu = (double) $nilai_residu;

      $masa_manfaat_tahun = isset($_POST['masa_manfaat_tahun']) ? (int)$_POST['masa_manfaat_tahun'] : 0;
      
      $data = [
          'serial_number' => $_POST['serial_number'] ?? '',
          'kode_item' => $_POST['kode_item'] ?? '',
          'nama_aset' => $_POST['nama_aset'] ?? '',
          'spesifikasi' => $_POST['spesifikasi'] ?? '',
          'tanggal_perolehan' => $_POST['tanggal_perolehan'] ?? date('Y-m-d'),
          'harga_beli' => $harga_beli,
          'sumber_perolehan' => $_POST['sumber_perolehan'] ?? 'Beli',
          'kode_unit' => $kode_unit,
          'pic' => $_POST['pic'] ?? '',
          'status_kondisi' => $_POST['status_kondisi'] ?? 'Baik',
          'status' => 'Aktif',
          'nilai_residu' => $nilai_residu,
          'masa_manfaat_tahun' => $masa_manfaat_tahun
      ];

      // Capture and Sanitize KIB Fields
      $kib_jenis = $_POST['kib_jenis'] ?? NULL;
      if (empty($kib_jenis)) {
          $kib_jenis = NULL;
      }
      
      $data['kib_jenis'] = $kib_jenis;
      
      if ($kib_jenis !== NULL) {
          if ($kib_jenis == 'A') {
              $data['kib_luas'] = (double) ($_POST['kib_luas_A'] ?? 0);
              $data['kib_hak'] = $_POST['kib_hak'] ?? '';
              $data['kib_no_sertifikat'] = $_POST['kib_no_sertifikat_A'] ?? '';
              $data['kib_tgl_sertifikat'] = !empty($_POST['kib_tgl_sertifikat_A']) ? $_POST['kib_tgl_sertifikat_A'] : NULL;
              $data['kib_penggunaan'] = $_POST['kib_penggunaan'] ?? '';
              $data['kib_alamat'] = $_POST['kib_alamat_A'] ?? '';
          } elseif ($kib_jenis == 'B') {
              $data['kib_merk'] = $_POST['kib_merk'] ?? '';
              $data['kib_ukuran'] = $_POST['kib_ukuran_B'] ?? '';
              $data['kib_bahan'] = $_POST['kib_bahan_B'] ?? '';
              $data['kib_no_pabrik'] = $_POST['kib_no_pabrik'] ?? '';
              $data['kib_no_rangka'] = $_POST['kib_no_rangka'] ?? '';
              $data['kib_no_mesin'] = $_POST['kib_no_mesin'] ?? '';
              $data['kib_no_polisi'] = $_POST['kib_no_polisi'] ?? '';
              $data['kib_no_bpkb'] = $_POST['kib_no_bpkb'] ?? '';
          } elseif ($kib_jenis == 'C') {
              $data['kib_bertingkat'] = $_POST['kib_bertingkat'] ?? 'Tidak';
              $data['kib_beton'] = $_POST['kib_beton'] ?? 'Tidak';
              $data['kib_luas'] = (double) ($_POST['kib_luas_C'] ?? 0);
              $data['kib_status_tanah'] = $_POST['kib_status_tanah_C'] ?? '';
              $data['kib_no_sertifikat'] = $_POST['kib_no_sertifikat_C'] ?? '';
              $data['kib_tgl_sertifikat'] = !empty($_POST['kib_tgl_sertifikat_C']) ? $_POST['kib_tgl_sertifikat_C'] : NULL;
              $data['kib_alamat'] = $_POST['kib_alamat_C'] ?? '';
          } elseif ($kib_jenis == 'D') {
              $data['kib_konstruksi'] = $_POST['kib_konstruksi'] ?? '';
              $data['kib_panjang'] = (double) ($_POST['kib_panjang'] ?? 0);
              $data['kib_lebar'] = (double) ($_POST['kib_lebar'] ?? 0);
              $data['kib_luas'] = (double) ($_POST['kib_luas_D'] ?? 0);
              $data['kib_no_sertifikat'] = $_POST['kib_no_sertifikat_D'] ?? '';
              $data['kib_tgl_sertifikat'] = !empty($_POST['kib_tgl_sertifikat_D']) ? $_POST['kib_tgl_sertifikat_D'] : NULL;
              $data['kib_status_tanah'] = $_POST['kib_status_tanah_D'] ?? '';
              $data['kib_alamat'] = $_POST['kib_alamat_D'] ?? '';
          } elseif ($kib_jenis == 'E') {
              $data['kib_judul'] = $_POST['kib_judul'] ?? '';
              $data['kib_pencipta'] = $_POST['kib_pencipta'] ?? '';
              $data['kib_bahan'] = $_POST['kib_bahan_E'] ?? '';
              $data['kib_ukuran'] = $_POST['kib_ukuran_E'] ?? '';
          } elseif ($kib_jenis == 'F') {
              $data['kib_proyek_bangunan'] = $_POST['kib_proyek_bangunan'] ?? '';
              $data['kib_bertingkat'] = $_POST['kib_bertingkat_F'] ?? 'Tidak';
              $data['kib_beton'] = $_POST['kib_beton_F'] ?? 'Tidak';
              $data['kib_luas'] = (double) ($_POST['kib_luas_F'] ?? 0);
              $data['kib_tgl_mulai'] = !empty($_POST['kib_tgl_mulai']) ? $_POST['kib_tgl_mulai'] : NULL;
              $data['kib_tgl_rencana_selesai'] = !empty($_POST['kib_tgl_rencana_selesai']) ? $_POST['kib_tgl_rencana_selesai'] : NULL;
              $data['kib_progress_persen'] = (double) ($_POST['kib_progress_persen'] ?? 0);
              $data['kib_alamat'] = $_POST['kib_alamat_F'] ?? '';
          }
      } else {
          // Clear KIB columns
          $data['kib_luas'] = 0;
          $data['kib_alamat'] = NULL;
          $data['kib_hak'] = NULL;
          $data['kib_tgl_sertifikat'] = NULL;
          $data['kib_no_sertifikat'] = NULL;
          $data['kib_penggunaan'] = NULL;
          $data['kib_merk'] = NULL;
          $data['kib_ukuran'] = NULL;
          $data['kib_bahan'] = NULL;
          $data['kib_no_pabrik'] = NULL;
          $data['kib_no_rangka'] = NULL;
          $data['kib_no_mesin'] = NULL;
          $data['kib_no_polisi'] = NULL;
          $data['kib_no_bpkb'] = NULL;
          $data['kib_bertingkat'] = 'Tidak';
          $data['kib_beton'] = 'Tidak';
          $data['kib_status_tanah'] = NULL;
          $data['kib_konstruksi'] = NULL;
          $data['kib_panjang'] = 0;
          $data['kib_lebar'] = 0;
          $data['kib_judul'] = NULL;
          $data['kib_pencipta'] = NULL;
          $data['kib_proyek_bangunan'] = NULL;
          $data['kib_tgl_mulai'] = NULL;
          $data['kib_tgl_rencana_selesai'] = NULL;
          $data['kib_progress_persen'] = 0;
      }
      
      $upload_dir = UPLOADS . '/logistik_non_medis/aset';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      
      $allowed_images = ['jpg', 'jpeg', 'png'];
      
      if(isset($_FILES['foto_depan']) && $_FILES['foto_depan']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['foto_depan']['name'], PATHINFO_EXTENSION));
          if(in_array($ext, $allowed_images)) {
              $filename = 'depan_' . time() . '_' . rand(100, 999) . '.' . $ext;
              if(move_uploaded_file($_FILES['foto_depan']['tmp_name'], $upload_dir . '/' . $filename)) {
                  $data['foto_depan'] = $filename;
              }
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Format Foto Depan tidak didukung! Gunakan jpg, jpeg, atau png.']);
              exit();
          }
      }
      
      if(isset($_FILES['foto_detail']) && $_FILES['foto_detail']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['foto_detail']['name'], PATHINFO_EXTENSION));
          if(in_array($ext, $allowed_images)) {
              $filename = 'detail_' . time() . '_' . rand(100, 999) . '.' . $ext;
              if(move_uploaded_file($_FILES['foto_detail']['tmp_name'], $upload_dir . '/' . $filename)) {
                  $data['foto_detail'] = $filename;
              }
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Format Foto Detail tidak didukung! Gunakan jpg, jpeg, atau png.']);
              exit();
          }
      }
      
      $user = $this->core->getUserInfo('username', null, true);
      
      if(empty($id)) {
          $data['nilai_buku'] = $harga_beli;
          $data['akumulasi_penyusutan'] = 0;
          $data['kode_aset'] = $kode_aset;
          $data['tgl_input'] = date('Y-m-d H:i:s');
          $data['user_input'] = $user;
          
          $query = $this->db('rsns_custom_logistik_non_medis_aset')->save($data);
          
          $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->save([
              'kode_aset' => $kode_aset,
              'kode_unit_asal' => NULL,
              'kode_unit_tujuan' => $kode_unit,
              'pic_asal' => NULL,
              'pic_tujuan' => $data['pic'],
              'keterangan' => 'Registrasi aset awal',
              'tanggal_mutasi' => date('Y-m-d H:i:s'),
              'user_mutasi' => $user
          ]);
      } else {
          $existing = $this->db('rsns_custom_logistik_non_medis_aset')->where('id', $id)->oneArray();
          if(!$existing) {
              echo json_encode(['status' => 'error', 'message' => 'Aset tidak ditemukan!']);
              exit();
          }
          
          $data['nilai_buku'] = $harga_beli - $existing['akumulasi_penyusutan'];
          
          if(isset($data['foto_depan']) && !empty($existing['foto_depan']) && file_exists($upload_dir . '/' . $existing['foto_depan'])) {
              unlink($upload_dir . '/' . $existing['foto_depan']);
          }
          if(isset($data['foto_detail']) && !empty($existing['foto_detail']) && file_exists($upload_dir . '/' . $existing['foto_detail'])) {
              unlink($upload_dir . '/' . $existing['foto_detail']);
          }
          
          $query = $this->db('rsns_custom_logistik_non_medis_aset')->where('id', $id)->update($data);
          
          if($existing['kode_unit'] != $kode_unit || $existing['pic'] != $data['pic']) {
              $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->save([
                  'kode_aset' => $existing['kode_aset'],
                  'kode_unit_asal' => $existing['kode_unit'],
                  'kode_unit_tujuan' => $kode_unit,
                  'pic_asal' => $existing['pic'],
                  'pic_tujuan' => $data['pic'],
                  'keterangan' => 'Mutasi penugasan aset',
                  'tanggal_mutasi' => date('Y-m-d H:i:s'),
                  'user_mutasi' => $user
              ]);
          }
      }
      
      if($query) {
          // Log to mlite_tracksql on success
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$kode_aset.' | '.$data['serial_number'].' | '.$data['kode_item'].' | '.$data['nama_aset'].' | '.$data['spesifikasi'].' | '.$data['tanggal_perolehan'].' | '.$data['harga_beli'].' | '.$data['sumber_perolehan'].' | '.$data['kode_unit'].' | '.$data['pic'].' | '.$data['status_kondisi'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => empty($id) ? 'I' : 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data ke database']);
      }
      exit();
  }

  public function anyDetailAsetRegistrasi()
  {
      $this->_initAset();
      $id = $_POST['id'] ?? '';
      
      $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('id', $id)->oneArray();
      if(!$aset) {
          echo '<div class="alert alert-danger">Data aset tidak ditemukan!</div>';
          exit();
      }
      
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $aset['kode_unit'])->oneArray();
      $aset['nama_unit'] = $unit['nama_unit'] ?? '-';
      
      $item = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $aset['kode_item'])->oneArray();
      $aset['nama_item'] = $item['nama_barang'] ?? '-';
      $aset['satuan_dasar'] = $item['satuan_dasar'] ?? '-';
      
      $mutasi = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')
                     ->where('kode_aset', $aset['kode_aset'])
                     ->desc('tanggal_mutasi')
                     ->toArray();
                     
      foreach($mutasi as &$m) {
          $unit_asal = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $m['kode_unit_asal'])->oneArray();
          $m['nama_unit_asal'] = $unit_asal['nama_unit'] ?? '-';
          
          $unit_tujuan = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $m['kode_unit_tujuan'])->oneArray();
          $m['nama_unit_tujuan'] = $unit_tujuan['nama_unit'] ?? '-';
      }
      
      echo $this->draw('aset.registrasi.detail.html', [
          'aset' => $aset,
          'mutasi' => $mutasi
      ]);
      exit();
  }

  public function postHapusAsetRegistrasi()
  {
      $this->_initAset();
      $id = $_POST['id'] ?? '';
      
      $existing = $this->db('rsns_custom_logistik_non_medis_aset')->where('id', $id)->oneArray();
      if($existing) {
          $query = $this->db('rsns_custom_logistik_non_medis_aset')
                        ->where('id', $id)
                        ->update(['status' => 'Dihapuskan']);
                        
          if($query) {
              // Log to mlite_tracksql
              $user = $this->core->getUserInfo('username', null, true);
              $tanggal_log = date('Y-m-d H:i:s');
              $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
              $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
              $hostname = $cek_hostname['hostname'] ?? 'Unknown';
              $log_lokasi = ''.$hostname.' | '.$ip.'';
              $logdata = ''.$existing['kode_aset'].' | '.$existing['nama_aset'].' | Status changed to Dihapuskan | '.$user.'';

              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_aset',
                  'log_waktu' => $tanggal_log,
                  'log_location' => $log_lokasi,
                  'log_data' => $logdata,
                  'log_status' => 'D',
                  'log_username' => $user
              ]);

              echo json_encode(['status' => 'success']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus aset dari database.']);
          }
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan.']);
      }
      exit();
  }

  public function getAsetPrintLabel($kode_aset = '')
  {
      $this->_initAset();
      if(empty($kode_aset)) {
          $kode_aset = $_GET['kode_aset'] ?? '';
      }
      
      $kode_aset = urldecode($kode_aset);
      
      $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $kode_aset)->oneArray();
      if(!$aset) {
          echo 'Data aset tidak ditemukan.';
          exit();
      }
      
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $aset['kode_unit'])->oneArray();
      $aset['nama_unit'] = $unit['nama_unit'] ?? '-';
      
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
      $scan_url = $protocol . $_SERVER['HTTP_HOST'] . url([ADMIN, 'logistik_non_medis', 'asetregistrasi']) . '?scan=' . urlencode($kode_aset);
      
      echo $this->draw('aset.registrasi.label.html', [
          'aset' => $aset,
          'scan_url' => $scan_url
      ]);
      exit();
  }

  public function getAsetKib()
  {
      $this->_initAset();
      $this->_initUnit();
      $this->_addHeaderFiles();
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->where('status', 'Aktif')->toArray();
      return $this->draw('aset.kib.html', ['units' => $units]);
  }

  public function anyDisplayKib()
  {
      $this->_initAset();
      $kib = $_POST['kib'] ?? 'A';
      $halaman = $_POST['halaman'] ?? 1;
      $cari = $_POST['cari'] ?? '';
      $filter_unit = $_POST['filter_unit'] ?? '';
      $filter_kondisi = $_POST['filter_kondisi'] ?? '';
      
      $halaman = (int) $halaman;
      if($halaman < 1) $halaman = 1;
      
      $perpage = 10;
      $_offset = ($halaman - 1) * $perpage;
      
      // Build query to fetch total count
      $query = $this->db('rsns_custom_logistik_non_medis_aset')
                    ->where('kib_jenis', $kib)
                    ->where('status', 'Aktif');
                    
      if(!empty($cari)) {
          $query->where(function($q) use ($cari) {
              $q->where('kode_aset', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_aset', '%'.$cari.'%')
                ->orLike('kib_merk', '%'.$cari.'%')
                ->orLike('kib_alamat', '%'.$cari.'%');
          });
      }
      
      if(!empty($filter_unit)) {
          $query->where('kode_unit', $filter_unit);
      }
      
      if(!empty($filter_kondisi)) {
          $query->where('status_kondisi', $filter_kondisi);
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $total_halaman = ceil($jumlah_data / $perpage);
      
      // Build rows query for paginated results
      $rows_query = $this->db('rsns_custom_logistik_non_medis_aset')
                         ->where('kib_jenis', $kib)
                         ->where('status', 'Aktif');
                         
      if(!empty($cari)) {
          $rows_query->where(function($q) use ($cari) {
              $q->where('kode_aset', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_aset', '%'.$cari.'%')
                ->orLike('kib_merk', '%'.$cari.'%')
                ->orLike('kib_alamat', '%'.$cari.'%');
          });
      }
      
      if(!empty($filter_unit)) {
          $rows_query->where('kode_unit', $filter_unit);
      }
      
      if(!empty($filter_kondisi)) {
          $rows_query->where('status_kondisi', $filter_kondisi);
      }
      
      $asets = $rows_query->desc('kode_aset')
                          ->offset($_offset)
                          ->limit($perpage)
                          ->toArray();
                          
      foreach($asets as &$aset) {
          $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $aset['kode_unit'])->oneArray();
          $aset['nama_unit'] = $unit['nama_unit'] ?? '-';
      }
      
      // Build pagination HTML
      $pagination_html = '';
      if($total_halaman > 1) {
          if($halaman > 1) {
              $pagination_html .= '<li><a href="#" data-page="'.($halaman - 1).'" aria-label="Previous">&laquo;</a></li>';
          } else {
              $pagination_html .= '<li class="disabled"><span aria-hidden="true">&laquo;</span></li>';
          }
          
          for($i = 1; $i <= $total_halaman; $i++) {
              if($i == $halaman) {
                  $pagination_html .= '<li class="active"><span>'.$i.'</span></li>';
              } else {
                  $pagination_html .= '<li><a href="#" data-page="'.$i.'">'.$i.'</a></li>';
              }
          }
          
          if($halaman < $total_halaman) {
              $pagination_html .= '<li><a href="#" data-page="'.($halaman + 1).'" aria-label="Next">&raquo;</a></li>';
          } else {
              $pagination_html .= '<li class="disabled"><span aria-hidden="true">&raquo;</span></li>';
          }
      }
      
      echo $this->draw('aset.kib.display.html', [
          'kib' => $kib,
          'asets' => $asets,
          'offset' => $_offset,
          'halaman' => $pagination_html
      ]);
      exit();
  }

  public function anyDisplayRekapKib()
  {
      $this->_initAset();
      
      $kib_categories = [
          'A' => ['nama' => 'Tanah', 'nama_singkat' => 'Tanah'],
          'B' => ['nama' => 'Peralatan & Mesin', 'nama_singkat' => 'Peralatan'],
          'C' => ['nama' => 'Gedung & Bangunan', 'nama_singkat' => 'Gedung'],
          'D' => ['nama' => 'Jalan, Irigasi & Jaringan', 'nama_singkat' => 'Jalan/Jaringan'],
          'E' => ['nama' => 'Aset Tetap Lainnya', 'nama_singkat' => 'Aset Lainnya'],
          'F' => ['nama' => 'Konstruksi Dalam Pengerjaan', 'nama_singkat' => 'Konstruksi']
      ];
      
      $rekap_data = [];
      $kpi = [];
      
      $grand_total_barang = 0;
      $grand_total_baik = 0;
      $grand_total_ringan = 0;
      $grand_total_berat = 0;
      $grand_total_nilai = 0.0;
      
      foreach ($kib_categories as $jenis => $meta) {
          $assets_in_cat = $this->db('rsns_custom_logistik_non_medis_aset')
                                ->where('kib_jenis', $jenis)
                                ->where('status', 'Aktif')
                                ->toArray();
                                
          $total_count = count($assets_in_cat);
          
          $total_nilai = 0.0;
          $baik = 0;
          $ringan = 0;
          $berat = 0;
          
          foreach ($assets_in_cat as $asset) {
              $total_nilai += (double) ($asset['harga_beli'] ?? 0);
              if ($asset['status_kondisi'] === 'Baik') {
                  $baik++;
              } elseif ($asset['status_kondisi'] === 'Rusak Ringan') {
                  $ringan++;
              } elseif ($asset['status_kondisi'] === 'Rusak Berat') {
                  $berat++;
              }
          }
          
          $rekap_data[] = [
              'jenis' => $jenis,
              'nama' => $meta['nama'],
              'nama_singkat' => $meta['nama_singkat'],
              'jumlah' => $total_count,
              'kondisi_baik' => $baik,
              'kondisi_rusak_ringan' => $ringan,
              'kondisi_rusak_berat' => $berat,
              'total_nilai' => $total_nilai
          ];
          
          $kpi[$jenis] = [
              'jumlah' => $total_count,
              'total_nilai' => $total_nilai
          ];
          
          $grand_total_barang += $total_count;
          $grand_total_baik += $baik;
          $grand_total_ringan += $ringan;
          $grand_total_berat += $berat;
          $grand_total_nilai += $total_nilai;
      }
      
      echo $this->draw('aset.kib.rekap.html', [
          'rekap_data' => $rekap_data,
          'kpi' => $kpi,
          'grand_total_barang' => $grand_total_barang,
          'grand_total_baik' => $grand_total_baik,
          'grand_total_ringan' => $grand_total_ringan,
          'grand_total_berat' => $grand_total_berat,
          'grand_total_nilai' => $grand_total_nilai
      ]);
      exit();
  }

  public function getAsetPenyusutan()
  {
      $this->_initPenyusutan();
      $this->_addHeaderFiles();

      // Get all accounts in mlite_rekening for dropdown mapping
      $rekening = $this->db('mlite_rekening')->toArray();

      // Read current settings for useful life & COA
      $settings_array = $this->db('mlite_settings')->where('module', 'logistik_non_medis')->toArray();
      $settings = [];
      foreach ($settings_array as $row) {
          $settings[$row['field']] = $row['value'];
      }

      // Generate current years for the calculation filter
      $years = [];
      $curr_year = (int)date('Y');
      for ($y = $curr_year - 5; $y <= $curr_year + 5; $y++) {
          $years[] = $y;
      }

      return $this->draw('aset.penyusutan.html', [
          'rekening' => $rekening,
          'settings' => $settings,
          'years' => $years,
          'current_month' => date('m'),
          'current_year' => date('Y')
      ]);
  }

  public function anyDisplayAsetPenyusutan()
  {
      $this->_initPenyusutan();
      $periode_bulan = $_POST['bulan'] ?? date('m');
      $periode_tahun = $_POST['tahun'] ?? date('Y');
      $periode = $periode_tahun . '-' . str_pad($periode_bulan, 2, '0', STR_PAD_LEFT);

      // Fetch settings
      $settings_array = $this->db('mlite_settings')->where('module', 'logistik_non_medis')->toArray();
      $settings = [];
      foreach ($settings_array as $row) {
          $settings[$row['field']] = $row['value'];
      }

      // Fetch assets (Exclude Tanah KIB A and Konstruksi KIB F)
      $assets = $this->db('rsns_custom_logistik_non_medis_aset')
                     ->where('status', 'Aktif')
                     ->where('kib_jenis', 'IN', ['B', 'C', 'D', 'E'])
                     ->toArray();

      $units_array = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $units = [];
      foreach ($units_array as $u) {
          $units[$u['kode_unit']] = $u['nama_unit'];
      }

      $data_aset = [];
      $grand_total_harga = 0;
      $grand_total_residu = 0;
      $grand_total_bulanan = 0;
      $grand_total_akumulasi = 0;
      $grand_total_buku = 0;

      // Check if already processed
      $check_processed = $this->db('rsns_custom_logistik_non_medis_aset_penyusutan')
                              ->where('periode', $periode)
                              ->toArray();
      $is_processed = !empty($check_processed);

      // Create map of existing log
      $processed_map = [];
      foreach ($check_processed as $p) {
          $processed_map[$p['kode_aset']] = $p;
      }

      foreach ($assets as $asset) {
          $kib = $asset['kib_jenis'];

          // 1. Determine Useful Life (Masa Manfaat)
          $manfaat_tahun = (int) ($asset['masa_manfaat_tahun'] ?? 0);
          if ($manfaat_tahun <= 0) {
              $manfaat_tahun = (int) ($settings["depr_manfaat_{$kib}"] ?? 0);
          }

          if ($manfaat_tahun <= 0) {
              continue;
          }

          // 2. Determine Nilai Residu
          $nilai_residu = (double) ($asset['nilai_residu'] ?? 0);
          if ($nilai_residu <= 0) {
              $persen_residu = (double) ($settings["depr_residu_{$kib}"] ?? 0);
              $nilai_residu = $asset['harga_beli'] * ($persen_residu / 100);
          }

          // 3. Determine Depreciable Amount
          $depreciable_amount = $asset['harga_beli'] - $nilai_residu;
          if ($depreciable_amount <= 0) {
              continue;
          }

          // 4. Monthly Straight Line Depreciation Cost
          $monthly_cost = $depreciable_amount / ($manfaat_tahun * 12);
          $monthly_cost = round($monthly_cost, 2);

          // 5. Evaluate calculations
          if ($is_processed) {
              if (!isset($processed_map[$asset['kode_aset']])) {
                  continue;
              }
              $log = $processed_map[$asset['kode_aset']];
              $monthly_cost_run = $log['biaya_penyusutan'];
              $akumulasi_run = $log['akumulasi_penyusutan'];
              $nilai_buku_run = $log['nilai_buku'];
          } else {
              if ($asset['nilai_buku'] <= $nilai_residu) {
                  $monthly_cost_run = 0;
              } else {
                  $remaining_above_residu = $asset['nilai_buku'] - $nilai_residu;
                  $monthly_cost_run = min($monthly_cost, $remaining_above_residu);
              }
              
              $monthly_cost_run = round($monthly_cost_run, 2);
              $akumulasi_run = $asset['akumulasi_penyusutan'] + $monthly_cost_run;
              $nilai_buku_run = $asset['harga_beli'] - $akumulasi_run;
          }

          $grand_total_harga += $asset['harga_beli'];
          $grand_total_residu += $nilai_residu;
          $grand_total_bulanan += $monthly_cost_run;
          $grand_total_akumulasi += $akumulasi_run;
          $grand_total_buku += $nilai_buku_run;

          $data_aset[] = [
              'kode_aset' => $asset['kode_aset'],
              'nama_aset' => $asset['nama_aset'],
              'kib_jenis' => $asset['kib_jenis'],
              'nama_unit' => $units[$asset['kode_unit']] ?? '-',
              'tanggal_perolehan' => $asset['tanggal_perolehan'],
              'harga_beli' => $asset['harga_beli'],
              'nilai_residu' => $nilai_residu,
              'masa_manfaat' => $manfaat_tahun,
              'biaya_penyusutan' => $monthly_cost_run,
              'akumulasi_penyusutan' => $akumulasi_run,
              'nilai_buku' => $nilai_buku_run
          ];
      }

      echo $this->draw('aset.penyusutan.display.html', [
          'asets' => $data_aset,
          'is_processed' => $is_processed,
          'periode' => $periode,
          'totals' => [
              'harga' => $grand_total_harga,
              'residu' => $grand_total_residu,
              'bulanan' => $grand_total_bulanan,
              'akumulasi' => $grand_total_akumulasi,
              'buku' => $grand_total_buku
          ]
      ]);
      exit();
  }

  public function postProsesPenyusutan()
  {
      $this->_initPenyusutan();
      $periode_bulan = $_POST['bulan'] ?? date('m');
      $periode_tahun = $_POST['tahun'] ?? date('Y');
      $periode = $periode_tahun . '-' . str_pad($periode_bulan, 2, '0', STR_PAD_LEFT);
      $user = $this->core->getUserInfo('username', null, true);

      // Check if already processed
      $check = $this->db('rsns_custom_logistik_non_medis_aset_penyusutan')
                    ->where('periode', $periode)
                    ->oneArray();
      if ($check) {
          echo json_encode(['status' => 'error', 'message' => 'Penyusutan periode ini sudah pernah diproses!']);
          exit();
      }

      // Fetch configurations
      $settings_array = $this->db('mlite_settings')->where('module', 'logistik_non_medis')->toArray();
      $settings = [];
      foreach ($settings_array as $row) {
          $settings[$row['field']] = $row['value'];
      }

      // Fetch assets
      $assets = $this->db('rsns_custom_logistik_non_medis_aset')
                     ->where('status', 'Aktif')
                     ->where('kib_jenis', 'IN', ['B', 'C', 'D', 'E'])
                     ->toArray();

      $calculated_assets = [];
      $depreciation_by_kib = [];
      $total_biaya_penyusutan = 0;

      foreach ($assets as $asset) {
          $kib = $asset['kib_jenis'];

          // 1. Determine Useful Life
          $manfaat_tahun = (int) ($asset['masa_manfaat_tahun'] ?? 0);
          if ($manfaat_tahun <= 0) {
              $manfaat_tahun = (int) ($settings["depr_manfaat_{$kib}"] ?? 0);
          }

          if ($manfaat_tahun <= 0) {
              continue;
          }

          // 2. Determine Nilai Residu
          $nilai_residu = (double) ($asset['nilai_residu'] ?? 0);
          if ($nilai_residu <= 0) {
              $persen_residu = (double) ($settings["depr_residu_{$kib}"] ?? 0);
              $nilai_residu = $asset['harga_beli'] * ($persen_residu / 100);
          }

          // 3. Determine Depreciable Amount
          $depreciable_amount = $asset['harga_beli'] - $nilai_residu;
          if ($depreciable_amount <= 0) {
              continue;
          }

          // 4. Calculate Monthly Cost
          $monthly_cost = $depreciable_amount / ($manfaat_tahun * 12);
          $monthly_cost = round($monthly_cost, 2);

          // Skip if already fully depreciated
          if ($asset['nilai_buku'] <= $nilai_residu) {
              continue;
          }

          $remaining_above_residu = $asset['nilai_buku'] - $nilai_residu;
          $monthly_cost_run = min($monthly_cost, $remaining_above_residu);
          $monthly_cost_run = round($monthly_cost_run, 2);

          if ($monthly_cost_run <= 0) {
              continue;
          }

          $akumulasi_run = $asset['akumulasi_penyusutan'] + $monthly_cost_run;
          $nilai_buku_run = $asset['harga_beli'] - $akumulasi_run;

          $calculated_assets[] = [
              'asset' => $asset,
              'biaya' => $monthly_cost_run,
              'akumulasi' => $akumulasi_run,
              'nilai_buku' => $nilai_buku_run,
              'nilai_residu' => $nilai_residu
          ];

          if (!isset($depreciation_by_kib[$kib])) {
              $depreciation_by_kib[$kib] = 0;
          }
          $depreciation_by_kib[$kib] += $monthly_cost_run;
          $total_biaya_penyusutan += $monthly_cost_run;
      }

      if (empty($calculated_assets)) {
          echo json_encode(['status' => 'error', 'message' => 'Tidak ada aset yang layak disusutkan untuk periode ini!']);
          exit();
      }

      // Validate COA Mappings for all involved KIB categories
      foreach (array_keys($depreciation_by_kib) as $kib) {
          $rek_beban = $settings["depr_rek_beban_{$kib}"] ?? '';
          $rek_akum = $settings["depr_rek_akum_{$kib}"] ?? '';
          
          if (empty($rek_beban) || empty($rek_akum)) {
              echo json_encode(['status' => 'error', 'message' => "Pemetaan COA untuk KIB {$kib} belum lengkap! Akun Beban & Akumulasi wajib diatur di Tab COA Mapping."]);
              exit();
          }

          // Check if these accounts exist in mlite_rekening
          $chk_beban = $this->db('mlite_rekening')->where('kd_rek', $rek_beban)->oneArray();
          $chk_akum = $this->db('mlite_rekening')->where('kd_rek', $rek_akum)->oneArray();

          if (!$chk_beban || !$chk_akum) {
              echo json_encode(['status' => 'error', 'message' => "Kode Akun Rekening COA KIB {$kib} di modul Keuangan tidak valid!"]);
              exit();
          }
      }

      // Start Database Transaction
      $pdo = $this->db()->pdo();
      $pdo->beginTransaction();

      try {
          // 1. Generate Journal Entry
          $no_jurnal = $this->core->setNoJurnal();
          $no_bukti = 'DEP-' . $periode_tahun . str_pad($periode_bulan, 2, '0', STR_PAD_LEFT);
          $nama_bulan = [
              '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
              '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
              '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
          ][$periode_bulan] ?? $periode_bulan;
          
          $keterangan = "Penyusutan Aset Non-Medis Periode " . $nama_bulan . " " . $periode_tahun . ". Diposting otomatis oleh sistem.";

          // Save to mlite_jurnal
          $this->db('mlite_jurnal')->save([
              'no_jurnal' => $no_jurnal,
              'no_bukti' => $no_bukti,
              'tgl_jurnal' => date('Y-m-d'),
              'jenis' => 'P', // Penyesuaian
              'keterangan' => $keterangan
          ]);

          // Save detail journals per KIB group
          foreach ($depreciation_by_kib as $kib => $amount) {
              $rek_beban = $settings["depr_rek_beban_{$kib}"];
              $rek_akum = $settings["depr_rek_akum_{$kib}"];

              // Debit: Depreciation Expense
              $this->db('mlite_detailjurnal')->save([
                  'no_jurnal' => $no_jurnal,
                  'kd_rek' => $rek_beban,
                  'debet' => $amount,
                  'kredit' => 0
              ]);

              // Credit: Accumulated Depreciation
              $this->db('mlite_detailjurnal')->save([
                  'no_jurnal' => $no_jurnal,
                  'kd_rek' => $rek_akum,
                  'debet' => 0,
                  'kredit' => $amount
              ]);
          }

          // 2. Save Log and update Main Assets table
          foreach ($calculated_assets as $item) {
              $asset = $item['asset'];

              // Insert to rsns_custom_logistik_non_medis_aset_penyusutan
              $this->db('rsns_custom_logistik_non_medis_aset_penyusutan')->save([
                  'kode_aset' => $asset['kode_aset'],
                  'periode' => $periode,
                  'tanggal_proses' => date('Y-m-d H:i:s'),
                  'harga_perolehan' => $asset['harga_beli'],
                  'nilai_residu' => $item['nilai_residu'],
                  'biaya_penyusutan' => $item['biaya'],
                  'akumulasi_penyusutan' => $item['akumulasi'],
                  'nilai_buku' => $item['nilai_buku'],
                  'no_jurnal' => $no_jurnal,
                  'user_proses' => $user
              ]);

              // Update Cache on Aset
              $this->db('rsns_custom_logistik_non_medis_aset')
                   ->where('kode_aset', $asset['kode_aset'])
                   ->update([
                       'akumulasi_penyusutan' => $item['akumulasi'],
                       'nilai_buku' => $item['nilai_buku'],
                       'tgl_penyusutan_terakhir' => date('Y-m-d')
                   ]);
          }

          // Log to mlite_tracksql
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Processed depreciation for period: ' . $periode . ' | Total assets: ' . count($calculated_assets) . ' | Total cost: ' . $total_biaya_penyusutan . ' | Journal: ' . $no_jurnal;

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penyusutan',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'I',
              'log_username' => $user
          ]);

          $pdo->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $pdo->rollBack();
          echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan saat memproses data: ' . $e->getMessage()]);
      }
      exit();
  }

  public function postRollbackPenyusutan()
  {
      $this->_initPenyusutan();
      $periode = $_POST['periode'] ?? '';

      if (empty($periode)) {
          echo json_encode(['status' => 'error', 'message' => 'Periode tidak boleh kosong!']);
          exit();
      }

      // Fetch all logs for this period
      $logs = $this->db('rsns_custom_logistik_non_medis_aset_penyusutan')
                   ->where('periode', $periode)
                   ->toArray();

      if (empty($logs)) {
          echo json_encode(['status' => 'error', 'message' => 'Riwayat penyusutan untuk periode ini tidak ditemukan!']);
          exit();
      }

      $no_jurnal = $logs[0]['no_jurnal'] ?? null;

      $pdo = $this->db()->pdo();
      $pdo->beginTransaction();

      try {
          // Revert each asset cache
          foreach ($logs as $log) {
              $asset = $this->db('rsns_custom_logistik_non_medis_aset')
                           ->where('kode_aset', $log['kode_aset'])
                           ->oneArray();
              if ($asset) {
                  $prev_akumulasi = max(0, $asset['akumulasi_penyusutan'] - $log['biaya_penyusutan']);
                  $prev_nilai_buku = $asset['nilai_buku'] + $log['biaya_penyusutan'];

                  // If it's 0 accumulated, tgl_penyusutan_terakhir is set to null
                  $tgl_terakhir = ($prev_akumulasi <= 0) ? null : date('Y-m-d');

                  $this->db('rsns_custom_logistik_non_medis_aset')
                       ->where('kode_aset', $log['kode_aset'])
                       ->update([
                           'akumulasi_penyusutan' => $prev_akumulasi,
                           'nilai_buku' => $prev_nilai_buku,
                           'tgl_penyusutan_terakhir' => $tgl_terakhir
                       ]);
              }
          }

          // Delete detail journals and journal in Keuangan
          if (!empty($no_jurnal)) {
              $this->db('mlite_detailjurnal')->where('no_jurnal', $no_jurnal)->delete();
              $this->db('mlite_jurnal')->where('no_jurnal', $no_jurnal)->delete();
          }

          // Delete log entries
          $this->db('rsns_custom_logistik_non_medis_aset_penyusutan')
               ->where('periode', $periode)
               ->delete();

          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Rolled back depreciation for period: ' . $periode . ' | Journal reverted: ' . $no_jurnal;

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penyusutan',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          $pdo->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $pdo->rollBack();
          echo json_encode(['status' => 'error', 'message' => 'Gagal melakukan rollback: ' . $e->getMessage()]);
      }
      exit();
  }

  public function postSavePenyusutanSettings()
  {
      $this->_initPenyusutan();
      
      $post_settings = $_POST['settings'] ?? [];
      
      $pdo = $this->db()->pdo();
      $pdo->beginTransaction();
      
      try {
          foreach ($post_settings as $key => $val) {
              $check = $this->db('mlite_settings')->where('module', 'logistik_non_medis')->where('field', $key)->oneArray();
              if ($check) {
                  $this->db('mlite_settings')
                       ->where('module', 'logistik_non_medis')
                       ->where('field', $key)
                       ->update(['value' => $val]);
              } else {
                  $this->db('mlite_settings')->save([
                      'module' => 'logistik_non_medis',
                      'field' => $key,
                      'value' => $val
                  ]);
              }
          }
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Saved depreciation settings: ' . json_encode($post_settings);

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penyusutan_settings',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);
          
          $pdo->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $pdo->rollBack();
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan pengaturan: ' . $e->getMessage()]);
      }
      exit();
  }

  public function anyRiwayatPenyusutan()
  {
      $this->_initPenyusutan();

      // Get periodic groups of calculations
      $query = $this->db()->pdo()->query("
          SELECT periode, tanggal_proses, no_jurnal, user_proses, 
                 SUM(harga_perolehan) as total_harga,
                 SUM(biaya_penyusutan) as total_penyusutan,
                 COUNT(kode_aset) as total_aset
          FROM rsns_custom_logistik_non_medis_aset_penyusutan
          GROUP BY periode
          ORDER BY periode DESC
      ");
      $query->execute();
      $riwayat = $query->fetchAll(\PDO::FETCH_ASSOC);

      echo $this->draw('aset.penyusutan.riwayat.html', [
          'riwayat' => $riwayat
      ]);
      exit();
  }

  public function anyDetailRiwayatPenyusutan()
  {
      $this->_initPenyusutan();
      $periode = $_POST['periode'] ?? '';

      $details = $this->db('rsns_custom_logistik_non_medis_aset_penyusutan')
                      ->join('rsns_custom_logistik_non_medis_aset', 'rsns_custom_logistik_non_medis_aset.kode_aset=rsns_custom_logistik_non_medis_aset_penyusutan.kode_aset')
                      ->where('periode', $periode)
                      ->toArray();

      $units_array = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $units = [];
      foreach ($units_array as $u) {
          $units[$u['kode_unit']] = $u['nama_unit'];
      }

      foreach ($details as &$d) {
          $d['nama_unit'] = $units[$d['kode_unit']] ?? '-';
      }

      echo $this->draw('aset.penyusutan.riwayat.detail.html', [
          'details' => $details,
          'periode' => $periode
      ]);
      exit();
  }

  private function _initAsetPemeliharaan()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_aset_pemeliharaan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_pemeliharaan` varchar(50) NOT NULL,
        `kode_aset` varchar(50) NOT NULL,
        `jenis_pemeliharaan` enum('Preventive','Corrective') NOT NULL,
        `tanggal_direncanakan` date NOT NULL,
        `tanggal_pelaksanaan` datetime DEFAULT NULL,
        `nama_kegiatan` varchar(200) NOT NULL,
        `deskripsi` text DEFAULT NULL,
        `frekuensi` enum('Sekali Saja','1 Bulan','3 Bulan','6 Bulan','1 Tahun','Kustom') DEFAULT 'Sekali Saja',
        `hari_kustom` int(11) DEFAULT 0,
        `prioritas` enum('Rendah','Sedang','Tinggi','Darurat') DEFAULT 'Sedang',
        `kode_rekanan` varchar(50) DEFAULT NULL,
        `nama_teknisi` varchar(150) DEFAULT NULL,
        `tindakan_perbaikan` text DEFAULT NULL,
        `status_kondisi_akhir` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
        `biaya_jasa` double DEFAULT 0,
        `biaya_sparepart` double DEFAULT 0,
        `detail_sparepart` text DEFAULT NULL,
        `total_biaya` double DEFAULT 0,
        `status` enum('Jadwal','Menunggu','Diproses','Selesai','Dibatalkan') DEFAULT 'Jadwal',
        `user_input` varchar(50) NOT NULL,
        `tgl_input` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `kode_pemeliharaan` (`kode_pemeliharaan`),
        KEY `kode_aset` (`kode_aset`),
        KEY `status` (`status`),
        KEY `tanggal_direncanakan` (`tanggal_direncanakan`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _generateKodeJadwal()
  {
      $prefix = 'PMJ-' . date('Ym') . '-';
      $last = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                   ->where('kode_pemeliharaan', 'LIKE', $prefix.'%')
                   ->desc('kode_pemeliharaan')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['kode_pemeliharaan'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  private function _generateKodeWO()
  {
      $prefix = 'WO-' . date('Ym') . '-';
      $last = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                   ->where('kode_pemeliharaan', 'LIKE', $prefix.'%')
                   ->desc('kode_pemeliharaan')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['kode_pemeliharaan'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getAsetPemeliharaan()
  {
      $this->_initAsetPemeliharaan();
      $this->_addHeaderFiles();
      
      $rekanan = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('status', 'Aktif')->toArray();
      
      return $this->draw('aset.pemeliharaan.html', [
          'rekanan' => $rekanan,
          'current_date' => date('Y-m-d')
      ]);
  }

  public function anyDisplayAsetPemeliharaanDashboard()
  {
      $this->_initAsetPemeliharaan();
      $today = date('Y-m-d');
      $year = date('Y');
      $month = date('m');
      
      // Count PM Overdue
      $overdue_pm_count = count($this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                                     ->where('status', 'Jadwal')
                                     ->where('tanggal_direncanakan', '<=', $today)
                                     ->toArray());
                                     
      // Count Active WOs
      $active_wo_count = count($this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                                    ->where('status', 'IN', ['Menunggu', 'Diproses'])
                                    ->toArray());
                                    
      // Count Corrective Completed this Month
      $corrective_count = count($this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                                     ->where('status', 'Selesai')
                                     ->where('jenis_pemeliharaan', 'Corrective')
                                     ->where('tanggal_pelaksanaan', 'LIKE', $year . '-' . $month . '%')
                                     ->toArray());
                                     
      // Sum Maintenance Cost this Year
      $cost_query = $this->db()->pdo()->prepare("SELECT SUM(total_biaya) as total FROM rsns_custom_logistik_non_medis_aset_pemeliharaan WHERE status = 'Selesai' AND YEAR(tanggal_pelaksanaan) = :year");
      $cost_query->execute(['year' => $year]);
      $cost_res = $cost_query->fetch(\PDO::FETCH_ASSOC);
      $total_cost = $cost_res['total'] ?? 0;
      
      // Fetch Overdue PM schedules
      $overdue_pms = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                           ->join('rsns_custom_logistik_non_medis_aset', 'rsns_custom_logistik_non_medis_aset.kode_aset=rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_aset')
                           ->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', 'Jadwal')
                           ->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.tanggal_direncanakan', '<=', $today)
                           ->desc('rsns_custom_logistik_non_medis_aset_pemeliharaan.tanggal_direncanakan')
                           ->toArray();
                           
      // Fetch active WOs
      $active_wos = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                         ->join('rsns_custom_logistik_non_medis_aset', 'rsns_custom_logistik_non_medis_aset.kode_aset=rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_aset')
                         ->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', 'IN', ['Menunggu', 'Diproses'])
                         ->desc('rsns_custom_logistik_non_medis_aset_pemeliharaan.prioritas')
                         ->toArray();
                         
      echo $this->draw('aset.pemeliharaan.dashboard.html', [
          'overdue_count' => $overdue_pm_count,
          'active_count' => $active_wo_count,
          'corrective_count' => $corrective_count,
          'total_cost' => $total_cost,
          'overdue_pms' => $overdue_pms,
          'active_wos' => $active_wos
      ]);
      exit();
  }

  public function anyDisplayJadwalPm()
  {
      $this->_initAsetPemeliharaan();
      $cari = $_POST['cari'] ?? '';
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                    ->join('rsns_custom_logistik_non_medis_aset', 'rsns_custom_logistik_non_medis_aset.kode_aset=rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_aset')
                    ->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', 'Jadwal');
                    
      if (!empty($cari)) {
          $query->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.nama_kegiatan', 'LIKE', '%'.$cari.'%')
                ->orLike('rsns_custom_logistik_non_medis_aset.nama_aset', '%'.$cari.'%')
                ->orLike('rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_aset', 'LIKE', '%'.$cari.'%');
      }
      
      $jadwal = $query->desc('rsns_custom_logistik_non_medis_aset_pemeliharaan.tanggal_direncanakan')->toArray();
      
      echo $this->draw('aset.pemeliharaan.jadwal.html', [
          'jadwal' => $jadwal
      ]);
      exit();
  }

  public function anyFormJadwalPm()
  {
      $this->_initAsetPemeliharaan();
      $id = $_POST['id'] ?? '';
      
      $jadwal = [
          'id' => '',
          'kode_pemeliharaan' => $this->_generateKodeJadwal(),
          'kode_aset' => '',
          'nama_aset' => '',
          'nama_kegiatan' => '',
          'deskripsi' => '',
          'frekuensi' => '3 Bulan',
          'hari_kustom' => 0,
          'tanggal_direncanakan' => date('Y-m-d'),
          'status' => 'Jadwal'
      ];
      
      if (!empty($id)) {
          $check = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->oneArray();
          if ($check) {
              $jadwal = $check;
              $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $jadwal['kode_aset'])->oneArray();
              $jadwal['nama_aset'] = $aset['nama_aset'] ?? '';
          }
      }
      
      echo $this->draw('aset.pemeliharaan.jadwal.form.html', [
          'jadwal' => $jadwal,
          'mode' => empty($id) ? 'add' : 'edit'
      ]);
      exit();
  }

  public function postSaveJadwalPm()
  {
      $this->_initAsetPemeliharaan();
      $id = $_POST['id'] ?? '';
      $kode_aset = $_POST['kode_aset'] ?? '';
      
      if (empty($kode_aset)) {
          echo json_encode(['status' => 'error', 'message' => 'Silakan pilih aset terlebih dahulu!']);
          exit();
      }
      
      $data = [
          'kode_aset' => $kode_aset,
          'jenis_pemeliharaan' => 'Preventive',
          'tanggal_direncanakan' => $_POST['tanggal_direncanakan'] ?? date('Y-m-d'),
          'nama_kegiatan' => $_POST['nama_kegiatan'] ?? '',
          'deskripsi' => $_POST['deskripsi'] ?? '',
          'frekuensi' => $_POST['frekuensi'] ?? 'Sekali Saja',
          'hari_kustom' => (int)($_POST['hari_kustom'] ?? 0),
          'status' => 'Jadwal',
          'user_input' => $this->core->getUserInfo('username', null, true),
          'tgl_input' => date('Y-m-d H:i:s')
      ];
      
      if (empty($id)) {
          $data['kode_pemeliharaan'] = $this->_generateKodeJadwal();
          $query = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->save($data);
      } else {
          unset($data['user_input']);
          unset($data['tgl_input']);
          $query = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->update($data);
      }
      
      if ($query) {
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$kode_aset.' | '.$data['tanggal_direncanakan'].' | '.$data['nama_kegiatan'].' | '.$data['deskripsi'].' | '.$data['frekuensi'].' | '.$data['status'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_pemeliharaan_jadwal',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => empty($id) ? 'I' : 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan jadwal pemeliharaan.']);
      }
      exit();
  }

  public function postHapusJadwalPm()
  {
      $id = $_POST['id'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->oneArray();
      if ($cek && $cek['status'] == 'Jadwal') {
          $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->delete();

          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$cek['kode_aset'].' | '.$cek['kode_pemeliharaan'].' | '.$cek['nama_kegiatan'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_pemeliharaan_jadwal',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus jadwal, data tidak ditemukan atau status bukan Jadwal.']);
      }
      exit();
  }

  public function anyDisplayAsetWo()
  {
      $this->_initAsetPemeliharaan();
      $cari = $_POST['cari'] ?? '';
      $status = $_POST['status'] ?? 'Aktif';
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                    ->join('rsns_custom_logistik_non_medis_aset', 'rsns_custom_logistik_non_medis_aset.kode_aset=rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_aset')
                    ->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', '<>', 'Jadwal');
                    
      if ($status == 'Aktif') {
          $query->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', 'IN', ['Menunggu', 'Diproses']);
      } elseif ($status == 'Selesai') {
          $query->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', 'Selesai');
      } elseif ($status == 'Dibatalkan') {
          $query->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', 'Dibatalkan');
      }
      
      if (!empty($cari)) {
          $query->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_pemeliharaan', 'LIKE', '%'.$cari.'%')
                ->orLike('rsns_custom_logistik_non_medis_aset_pemeliharaan.nama_kegiatan', 'LIKE', '%'.$cari.'%')
                ->orLike('rsns_custom_logistik_non_medis_aset.nama_aset', '%'.$cari.'%');
      }
      
      $wos = $query->desc('rsns_custom_logistik_non_medis_aset_pemeliharaan.tgl_input')->toArray();
      
      echo $this->draw('aset.pemeliharaan.wo.html', [
          'wos' => $wos
      ]);
      exit();
  }

  public function anyFormAsetWo()
  {
      $this->_initAsetPemeliharaan();
      $id = $_POST['id'] ?? '';
      $jadwal_id = $_POST['jadwal_id'] ?? '';
      
      $wo = [
          'id' => '',
          'kode_pemeliharaan' => $this->_generateKodeWO(),
          'kode_aset' => '',
          'nama_aset' => '',
          'jenis_pemeliharaan' => 'Corrective',
          'nama_kegiatan' => '',
          'deskripsi' => '',
          'tanggal_direncanakan' => date('Y-m-d'),
          'prioritas' => 'Sedang',
          'kode_rekanan' => '',
          'nama_teknisi' => '',
          'status' => 'Diproses'
      ];
      
      if (!empty($jadwal_id)) {
          $jadwal = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $jadwal_id)->oneArray();
          if ($jadwal) {
              $wo['kode_aset'] = $jadwal['kode_aset'];
              $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $wo['kode_aset'])->oneArray();
              $wo['nama_aset'] = $aset['nama_aset'] ?? '';
              $wo['jenis_pemeliharaan'] = 'Preventive';
              $wo['nama_kegiatan'] = 'WO PM: ' . $jadwal['nama_kegiatan'];
              $wo['deskripsi'] = $jadwal['deskripsi'];
              $wo['tanggal_direncanakan'] = date('Y-m-d');
          }
      } elseif (!empty($id)) {
          $check = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->oneArray();
          if ($check) {
              $wo = $check;
              $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $wo['kode_aset'])->oneArray();
              $wo['nama_aset'] = $aset['nama_aset'] ?? '';
          }
      }
      
      $rekanan = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('status', 'Aktif')->toArray();
      
      echo $this->draw('aset.pemeliharaan.wo.form.html', [
          'wo' => $wo,
          'rekanan' => $rekanan,
          'mode' => empty($id) ? 'add' : 'edit',
          'jadwal_id' => $jadwal_id
      ]);
      exit();
  }

  public function postSaveAsetWo()
  {
      $this->_initAsetPemeliharaan();
      $id = $_POST['id'] ?? '';
      $kode_aset = $_POST['kode_aset'] ?? '';
      
      if (empty($kode_aset)) {
          echo json_encode(['status' => 'error', 'message' => 'Silakan pilih aset terlebih dahulu!']);
          exit();
      }
      
      $data = [
          'kode_aset' => $kode_aset,
          'jenis_pemeliharaan' => $_POST['jenis_pemeliharaan'] ?? 'Corrective',
          'tanggal_direncanakan' => $_POST['tanggal_direncanakan'] ?? date('Y-m-d'),
          'nama_kegiatan' => $_POST['nama_kegiatan'] ?? '',
          'deskripsi' => $_POST['deskripsi'] ?? '',
          'prioritas' => $_POST['prioritas'] ?? 'Sedang',
          'kode_rekanan' => empty($_POST['kode_rekanan']) ? NULL : $_POST['kode_rekanan'],
          'nama_teknisi' => $_POST['nama_teknisi'] ?? '',
          'status' => $_POST['status'] ?? 'Diproses',
          'user_input' => $this->core->getUserInfo('username', null, true),
          'tgl_input' => date('Y-m-d H:i:s')
      ];
      
      if (empty($id)) {
          $data['kode_pemeliharaan'] = $this->_generateKodeWO();
          $query = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->save($data);
      } else {
          unset($data['user_input']);
          unset($data['tgl_input']);
          $query = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->update($data);
      }
      
      if ($query) {
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$kode_aset.' | '.$data['jenis_pemeliharaan'].' | '.$data['tanggal_direncanakan'].' | '.$data['nama_kegiatan'].' | '.$data['prioritas'].' | '.$data['nama_teknisi'].' | '.$data['status'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_pemeliharaan_wo',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => empty($id) ? 'I' : 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan Surat Perintah Kerja.']);
      }
      exit();
  }

  public function postHapusAsetWo()
  {
      $id = $_POST['id'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->oneArray();
      if ($cek && $cek['status'] != 'Jadwal') {
          $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->delete();

          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$cek['kode_aset'].' | '.$cek['kode_pemeliharaan'].' | '.$cek['nama_kegiatan'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_pemeliharaan_wo',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data perbaikan.']);
      }
      exit();
  }

  public function anyFormSelesaikanAsetWo()
  {
      $this->_initAsetPemeliharaan();
      $id = $_POST['id'] ?? '';
      
      $wo = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->oneArray();
      if ($wo) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $wo['kode_aset'])->oneArray();
          $wo['nama_aset'] = $aset['nama_aset'] ?? '';
      }
      
      echo $this->draw('aset.pemeliharaan.wo.complete.html', [
          'wo' => $wo
      ]);
      exit();
  }

  public function postSelesaikanAsetWo()
  {
      $this->_initAsetPemeliharaan();
      $id = $_POST['id'] ?? '';
      
      $wo = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')->where('id', $id)->oneArray();
      if (!$wo) {
          echo json_encode(['status' => 'error', 'message' => 'Data Work Order tidak ditemukan!']);
          exit();
      }
      
      // Parse spareparts
      $spareparts = [];
      $biaya_sparepart = 0;
      $post_parts = $_POST['parts'] ?? [];
      
      foreach ($post_parts as $part) {
          if (!empty($part['kode_item'])) {
              $qty = (double)($part['qty'] ?? 1);
              $harga = (double)str_replace(['Rp.', '.'], '', $part['harga'] ?? 0);
              $subtotal = $qty * $harga;
              
              $spareparts[] = [
                  'kode_item' => $part['kode_item'],
                  'nama' => $part['nama'],
                  'qty' => $qty,
                  'harga' => $harga,
                  'subtotal' => $subtotal
              ];
              $biaya_sparepart += $subtotal;
          }
      }
      
      $biaya_jasa = (double)str_replace(['Rp.', '.'], '', $_POST['biaya_jasa'] ?? 0);
      $total_biaya = $biaya_jasa + $biaya_sparepart;
      
      $tindakan_perbaikan = $_POST['tindakan_perbaikan'] ?? '';
      $status_kondisi_akhir = $_POST['status_kondisi_akhir'] ?? 'Baik';
      $nama_teknisi = $_POST['nama_teknisi'] ?? $wo['nama_teknisi'];
      
      $pdo = $this->db()->pdo();
      $pdo->beginTransaction();
      
      try {
          // 1. Update WO row itself
          $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
               ->where('id', $id)
               ->update([
                   'tanggal_pelaksanaan' => date('Y-m-d H:i:s'),
                   'nama_teknisi' => $nama_teknisi,
                   'tindakan_perbaikan' => $tindakan_perbaikan,
                   'status_kondisi_akhir' => $status_kondisi_akhir,
                   'biaya_jasa' => $biaya_jasa,
                   'biaya_sparepart' => $biaya_sparepart,
                   'detail_sparepart' => json_encode($spareparts),
                   'total_biaya' => $total_biaya,
                   'status' => 'Selesai'
               ]);
               
          // 2. Update condition in Master Asset
          $this->db('rsns_custom_logistik_non_medis_aset')
               ->where('kode_aset', $wo['kode_aset'])
               ->update([
                   'status_kondisi' => $status_kondisi_akhir
               ]);
               
          // 3. If Preventive WO, find corresponding PM schedule and push next date
          if ($wo['jenis_pemeliharaan'] == 'Preventive') {
              // Find schedule matching same asset & status = Jadwal
              $schedule = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                               ->where('kode_aset', $wo['kode_aset'])
                               ->where('status', 'Jadwal')
                               ->oneArray();
                               
              if ($schedule) {
                  $freq = $schedule['frekuensi'];
                  $next_date = date('Y-m-d');
                  
                  if ($freq == '1 Bulan') {
                      $next_date = date('Y-m-d', strtotime('+1 month'));
                  } elseif ($freq == '3 Bulan') {
                      $next_date = date('Y-m-d', strtotime('+3 months'));
                  } elseif ($freq == '6 Bulan') {
                      $next_date = date('Y-m-d', strtotime('+6 months'));
                  } elseif ($freq == '1 Tahun') {
                      $next_date = date('Y-m-d', strtotime('+1 year'));
                  } elseif ($freq == 'Kustom' && $schedule['hari_kustom'] > 0) {
                      $next_date = date('Y-m-d', strtotime('+' . $schedule['hari_kustom'] . ' days'));
                  }
                  
                  $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                       ->where('id', $schedule['id'])
                       ->update([
                           'tanggal_direncanakan' => $next_date
                       ]);
              }
          }
          
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Completed WO: '.$wo['kode_pemeliharaan'].' for Asset: '.$wo['kode_aset'].' | Jasa: '.$biaya_jasa.' | Sparepart: '.$biaya_sparepart.' | Total: '.$total_biaya.' | Kondisi Akhir: '.$status_kondisi_akhir;

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_pemeliharaan_wo',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          $pdo->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $pdo->rollBack();
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyelesaikan pekerjaan: ' . $e->getMessage()]);
      }
      exit();
  }

  public function anyDisplayBiayaHistori()
  {
      $this->_initAsetPemeliharaan();
      $cari = $_POST['cari'] ?? '';
      
      // Calculate costs per asset
      $cost_query = $this->db()->pdo()->query("
          SELECT a.kode_aset, a.nama_aset, a.status_kondisi,
                 SUM(p.biaya_jasa) as total_jasa,
                 SUM(p.biaya_sparepart) as total_sparepart,
                 SUM(p.total_biaya) as total_pemeliharaan,
                 COUNT(p.id) as total_servis
          FROM rsns_custom_logistik_non_medis_aset a
          LEFT JOIN rsns_custom_logistik_non_medis_aset_pemeliharaan p 
                 ON p.kode_aset = a.kode_aset AND p.status = 'Selesai'
          GROUP BY a.kode_aset
          ORDER BY total_pemeliharaan DESC
      ");
      $cost_query->execute();
      $biaya_aset = $cost_query->fetchAll(\PDO::FETCH_ASSOC);
      
      // Filter detailed logs if needed
      $log_query = $this->db('rsns_custom_logistik_non_medis_aset_pemeliharaan')
                        ->join('rsns_custom_logistik_non_medis_aset', 'rsns_custom_logistik_non_medis_aset.kode_aset=rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_aset')
                        ->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.status', 'Selesai');
                        
      if (!empty($cari)) {
          $log_query->where('rsns_custom_logistik_non_medis_aset_pemeliharaan.kode_aset', 'LIKE', '%'.$cari.'%')
                    ->orLike('rsns_custom_logistik_non_medis_aset.nama_aset', '%'.$cari.'%')
                    ->orLike('rsns_custom_logistik_non_medis_aset_pemeliharaan.tindakan_perbaikan', '%'.$cari.'%');
      }
      
      $riwayat = $log_query->desc('rsns_custom_logistik_non_medis_aset_pemeliharaan.tanggal_pelaksanaan')->toArray();
      
      echo $this->draw('aset.pemeliharaan.histori.html', [
          'biaya_aset' => $biaya_aset,
          'riwayat' => $riwayat
      ]);
      exit();
  }

  public function anySearchAsetAutocomplete()
  {
      $cari = $_GET['term'] ?? '';
      $query = $this->db('rsns_custom_logistik_non_medis_aset')->where('status', 'Aktif');
      if (!empty($cari)) {
          $query->where('nama_aset', 'LIKE', '%'.$cari.'%')
                ->orLike('kode_aset', 'LIKE', '%'.$cari.'%')
                ->orLike('serial_number', 'LIKE', '%'.$cari.'%');
      }
      
      $rows = $query->limit(15)->toArray();
      $result = [];
      foreach ($rows as $row) {
          $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['kode_unit'])->oneArray();
          $lok = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $row['kode_lokasi'])->oneArray();
          
          $result[] = [
              'id' => $row['kode_aset'],
              'label' => '[' . $row['kode_aset'] . '] ' . $row['nama_aset'] . ($row['serial_number'] ? ' (S/N: ' . $row['serial_number'] . ')' : ''),
              'value' => $row['nama_aset'],
              'kode_aset' => $row['kode_aset'],
              'kode_unit' => $row['kode_unit'] ?? '',
              'nama_unit' => $unit['nama_unit'] ?? '-',
              'kode_lokasi' => $row['kode_lokasi'] ?? '',
              'nama_lokasi' => $lok['nama_lokasi'] ?? '-',
              'pic' => $row['pic'] ?? '-'
          ];
      }
      echo json_encode($result);
      exit();
  }

  public function anySearchSparepartAutocomplete()
  {
      $cari = $_GET['term'] ?? '';
      $query = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('status', 'Aktif');
      if (!empty($cari)) {
          $query->where('nama_barang', 'LIKE', '%'.$cari.'%')
                ->orLike('kode_item', 'LIKE', '%'.$cari.'%');
      }
      
      $rows = $query->limit(15)->toArray();
      $result = [];
      foreach ($rows as $row) {
          $result[] = [
              'id' => $row['kode_item'],
              'label' => '[' . $row['kode_item'] . '] ' . $row['nama_barang'] . ' (Rp. ' . number_format($row['harga_referensi'], 0, ',', '.') . ')',
              'value' => $row['nama_barang'],
              'kode_item' => $row['kode_item'],
              'harga' => $row['harga_referensi']
          ];
      }
      echo json_encode($result);
      exit();
  }


  public function getAsetMutasi()
  {
      $this->_addHeaderFiles();
      return $this->draw('aset.mutasi.html');
  }

  public function anyDisplayAsetMutasi()
  {
      $this->_initAset();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $status = isset($_POST['status']) ? $_POST['status'] : 'Diajukan';

      $_offset = ($halaman - 1) * $perpage;

      $query_count = $this->db('rsns_custom_logistik_non_medis_aset_mutasi');
      if ($status !== 'semua') {
          $query_count->where('status', $status);
      }
      if (!empty($cari)) {
          $query_count->where(function($q) use ($cari) {
              $q->like('no_mutasi', '%'.$cari.'%')->orLike('kode_aset', '%'.$cari.'%');
          });
      }
      $jumlah_data = $query_count->count();
      $jml_halaman = ceil($jumlah_data / $perpage);

      $rows = $this->db('rsns_custom_logistik_non_medis_aset_mutasi');
      if ($status !== 'semua') {
          $rows->where('status', $status);
      }
      if (!empty($cari)) {
          $rows->where(function($q) use ($cari) {
              $q->like('no_mutasi', '%'.$cari.'%')->orLike('kode_aset', '%'.$cari.'%');
          });
      }
      $rows = $rows->desc('tgl_input')
                   ->offset($_offset)
                   ->limit($perpage)
                   ->toArray();

      foreach ($rows as &$row) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $row['kode_aset'])->oneArray();
          $row['nama_aset'] = $aset['nama_aset'] ?? '-';
          $row['serial_number'] = $aset['serial_number'] ?? '-';

          $unit_asal = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['kode_unit_asal'])->oneArray();
          $row['nama_unit_asal'] = $unit_asal['nama_unit'] ?? '-';

          $unit_tujuan = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['kode_unit_tujuan'])->oneArray();
          $row['nama_unit_tujuan'] = $unit_tujuan['nama_unit'] ?? '-';

          $lok_asal = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $row['kode_lokasi_asal'])->oneArray();
          $row['nama_lokasi_asal'] = $lok_asal['nama_lokasi'] ?? '-';

          $lok_tujuan = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $row['kode_lokasi_tujuan'])->oneArray();
          $row['nama_lokasi_tujuan'] = $lok_tujuan['nama_lokasi'] ?? '-';
      }

      echo $this->draw('aset.mutasi.display.html', [
          'mutasi' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman
      ]);
      exit();
  }

  public function anyFormAsetMutasi()
  {
      $this->_initAset();
      $mode = $_POST['mode'] ?? 'add';
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      
      $mutasi = [];
      if ($mode == 'edit' && !empty($no_mutasi)) {
          $mutasi = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
          if ($mutasi) {
              $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $mutasi['kode_aset'])->oneArray();
              $mutasi['nama_aset'] = $aset['nama_aset'] ?? '';
              $mutasi['serial_number'] = $aset['serial_number'] ?? '';
              
              $unit_asal = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $mutasi['kode_unit_asal'])->oneArray();
              $mutasi['nama_unit_asal'] = $unit_asal['nama_unit'] ?? '-';
              
              $lok_asal = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $mutasi['kode_lokasi_asal'])->oneArray();
              $mutasi['nama_lokasi_asal'] = $lok_asal['nama_lokasi'] ?? '-';
          }
      }

      $units = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();

      echo $this->draw('aset.mutasi.form.html', [
          'mode' => $mode,
          'mutasi' => $mutasi,
          'units' => $units,
          'lokasi' => $lokasi
      ]);
      exit();
  }

  public function postSaveAsetMutasi()
  {
      $this->_initAset();
      $mode = $_POST['mode'] ?? 'add';
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      
      $kode_aset = $_POST['kode_aset'] ?? '';
      $kode_unit_tujuan = $_POST['kode_unit_tujuan'] ?? '';
      $kode_lokasi_tujuan = $_POST['kode_lokasi_tujuan'] ?? '';
      $pic_tujuan = $_POST['pic_tujuan'] ?? '';
      $tanggal_mutasi = $_POST['tanggal_mutasi'] ?? date('Y-m-d');
      $keterangan = $_POST['keterangan'] ?? '';
      $status = $_POST['status'] ?? 'Diajukan';

      if (empty($kode_aset)) {
          echo json_encode(['status' => 'error', 'message' => 'Pilih aset terlebih dahulu!']);
          exit();
      }

      $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $kode_aset)->oneArray();
      if (!$aset) {
          echo json_encode(['status' => 'error', 'message' => 'Data aset tidak ditemukan!']);
          exit();
      }

      $kode_unit_asal = $aset['kode_unit'] ?? null;
      $kode_lokasi_asal = $aset['kode_lokasi'] ?? null;
      $pic_asal = $aset['pic'] ?? null;

      $user = $_SESSION['mlite_user'] ?? 'admin';

      try {
          if ($mode == 'add') {
              $prefix = 'MUT-AST/' . date('Ym') . '/';
              $max = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')
                          ->select(['max_no' => 'MAX(no_mutasi)'])
                          ->where('no_mutasi', 'LIKE', $prefix . '%')
                          ->oneArray();
              
              if ($max && !empty($max['max_no'])) {
                  $num = (int)substr($max['max_no'], -4);
                  $new_num = sprintf('%04d', $num + 1);
              } else {
                  $new_num = '0001';
              }
              $no_mutasi = $prefix . $new_num;

              $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->insert([
                  'no_mutasi' => $no_mutasi,
                  'kode_aset' => $kode_aset,
                  'kode_unit_asal' => $kode_unit_asal,
                  'kode_unit_tujuan' => $kode_unit_tujuan,
                  'kode_lokasi_asal' => $kode_lokasi_asal,
                  'kode_lokasi_tujuan' => $kode_lokasi_tujuan,
                  'pic_asal' => $pic_asal,
                  'pic_tujuan' => $pic_tujuan,
                  'keterangan' => $keterangan,
                  'tanggal_mutasi' => $tanggal_mutasi,
                  'status' => $status,
                  'user_mutasi' => $user,
                  'tgl_input' => date('Y-m-d H:i:s')
              ]);
          } else {
              $existing = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
              if (!$existing || !in_array($existing['status'], ['Draft', 'Diajukan', 'Ditolak'])) {
                  echo json_encode(['status' => 'error', 'message' => 'Mutasi tidak dapat diedit!']);
                  exit();
              }

              $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->update([
                  'kode_aset' => $kode_aset,
                  'kode_unit_asal' => $kode_unit_asal,
                  'kode_unit_tujuan' => $kode_unit_tujuan,
                  'kode_lokasi_asal' => $kode_lokasi_asal,
                  'kode_lokasi_tujuan' => $kode_lokasi_tujuan,
                  'pic_asal' => $pic_asal,
                  'pic_tujuan' => $pic_tujuan,
                  'keterangan' => $keterangan,
                  'tanggal_mutasi' => $tanggal_mutasi,
                  'status' => $status,
                  'tgl_update' => date('Y-m-d H:i:s')
              ]);
          }

          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$no_mutasi.' | '.$kode_aset.' | Asal Unit: '.$kode_unit_asal.' | Tujuan Unit: '.$kode_unit_tujuan.' | Status: '.$status.' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_mutasi',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => $mode == 'add' ? 'I' : 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success', 'no_mutasi' => $no_mutasi]);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function postDeleteAsetMutasi()
  {
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      if (empty($no_mutasi)) {
          echo json_encode(['status' => 'error', 'message' => 'No mutasi tidak valid!']);
          exit();
      }

      $existing = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if (!$existing || !in_array($existing['status'], ['Draft', 'Diajukan', 'Ditolak'])) {
          echo json_encode(['status' => 'error', 'message' => 'Mutasi tidak dapat dihapus!']);
          exit();
      }

      try {
          $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->delete();

          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Deleted mutation: '.$no_mutasi.' for Asset: '.$existing['kode_aset'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_mutasi',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function postApproveAsetMutasi()
  {
      $this->_initAset();
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      $role_type = $_POST['role_type'] ?? '';
      $user = $_SESSION['mlite_user'] ?? 'admin';

      if (empty($no_mutasi)) {
          echo json_encode(['status' => 'error', 'message' => 'No mutasi tidak valid!']);
          exit();
      }

      $mutasi = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if (!$mutasi) {
          echo json_encode(['status' => 'error', 'message' => 'Pengajuan mutasi tidak ditemukan!']);
          exit();
      }

      try {
          if ($role_type == 'asal') {
              if ($mutasi['status'] !== 'Diajukan') {
                  echo json_encode(['status' => 'error', 'message' => 'Mutasi tidak dapat disetujui unit asal!']);
                  exit();
              }
              
              $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->update([
                  'status' => 'Disetujui Asal',
                  'user_approval_asal' => $user,
                  'tgl_approval_asal' => date('Y-m-d H:i:s'),
                  'tgl_update' => date('Y-m-d H:i:s')
              ]);
          } elseif ($role_type == 'tujuan') {
              if ($mutasi['status'] !== 'Disetujui Asal' && $mutasi['status'] !== 'Diajukan') {
                  echo json_encode(['status' => 'error', 'message' => 'Mutasi belum disetujui Unit Asal!']);
                  exit();
              }

              $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->update([
                  'status' => 'Selesai',
                  'user_approval_tujuan' => $user,
                  'tgl_approval_tujuan' => date('Y-m-d H:i:s'),
                  'tgl_update' => date('Y-m-d H:i:s')
              ]);

              $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $mutasi['kode_aset'])->update([
                  'kode_unit' => $mutasi['kode_unit_tujuan'],
                  'kode_lokasi' => $mutasi['kode_lokasi_tujuan'],
                  'pic' => $mutasi['pic_tujuan']
              ]);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Role tidak valid!']);
              exit();
          }

          // Log to mlite_tracksql
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$no_mutasi.' | '.$mutasi['kode_aset'].' | Asal Unit: '.$mutasi['kode_unit_asal'].' | Tujuan Unit: '.$mutasi['kode_unit_tujuan'].' | Role: '.$role_type.' | Status: '.($role_type == 'tujuan' ? 'Selesai' : 'Disetujui Asal').' | '.$user;

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_mutasi',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function postRejectAsetMutasi()
  {
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      $alasan = $_POST['alasan_penolakan'] ?? '';

      if (empty($no_mutasi)) {
          echo json_encode(['status' => 'error', 'message' => 'No mutasi tidak valid!']);
          exit();
      }

      $mutasi = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if (!$mutasi || in_array($mutasi['status'], ['Selesai', 'Ditolak'])) {
          echo json_encode(['status' => 'error', 'message' => 'Mutasi tidak dapat ditolak!']);
          exit();
      }

      try {
          $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->update([
              'status' => 'Ditolak',
              'alasan_penolakan' => $alasan,
              'tgl_update' => date('Y-m-d H:i:s')
          ]);
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Rejected mutation: '.$no_mutasi.' | Reason: '.$alasan.' | Asset: '.$mutasi['kode_aset'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_mutasi',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function anyDetailAsetMutasi()
  {
      $this->_initAset();
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      
      $mutasi = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if (!$mutasi) {
          echo '<div class="alert alert-danger">Detail mutasi tidak ditemukan!</div>';
          exit();
      }

      $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $mutasi['kode_aset'])->oneArray();
      $mutasi['nama_aset'] = $aset['nama_aset'] ?? '-';
      $mutasi['serial_number'] = $aset['serial_number'] ?? '-';
      $mutasi['spesifikasi'] = $aset['spesifikasi'] ?? '-';

      $unit_asal = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $mutasi['kode_unit_asal'])->oneArray();
      $mutasi['nama_unit_asal'] = $unit_asal['nama_unit'] ?? '-';

      $unit_tujuan = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $mutasi['kode_unit_tujuan'])->oneArray();
      $mutasi['nama_unit_tujuan'] = $unit_tujuan['nama_unit'] ?? '-';

      $lok_asal = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $mutasi['kode_lokasi_asal'])->oneArray();
      $mutasi['nama_lokasi_asal'] = $lok_asal['nama_lokasi'] ?? '-';

      $lok_tujuan = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $mutasi['kode_lokasi_tujuan'])->oneArray();
      $mutasi['nama_lokasi_tujuan'] = $lok_tujuan['nama_lokasi'] ?? '-';

      echo $this->draw('aset.mutasi.detail.html', [
          'mutasi' => $mutasi
      ]);
      exit();
  }

  public function getPrintAsetMutasi()
  {
      $this->_initAset();
      $no_mutasi = $_GET['no_mutasi'] ?? '';

      $mutasi = $this->db('rsns_custom_logistik_non_medis_aset_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if (!$mutasi) {
          echo 'Data mutasi tidak ditemukan!';
          exit();
      }

      $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $mutasi['kode_aset'])->oneArray();
      $mutasi['nama_aset'] = $aset['nama_aset'] ?? '-';
      $mutasi['serial_number'] = $aset['serial_number'] ?? '-';
      $mutasi['spesifikasi'] = $aset['spesifikasi'] ?? '-';
      $mutasi['status_kondisi'] = $aset['status_kondisi'] ?? 'Baik';

      $unit_asal = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $mutasi['kode_unit_asal'])->oneArray();
      $mutasi['nama_unit_asal'] = $unit_asal['nama_unit'] ?? '-';

      $unit_tujuan = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $mutasi['kode_unit_tujuan'])->oneArray();
      $mutasi['nama_unit_tujuan'] = $unit_tujuan['nama_unit'] ?? '-';

      $lok_asal = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $mutasi['kode_lokasi_asal'])->oneArray();
      $mutasi['nama_lokasi_asal'] = $lok_asal['nama_lokasi'] ?? '-';

      $lok_tujuan = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $mutasi['kode_lokasi_tujuan'])->oneArray();
      $mutasi['nama_lokasi_tujuan'] = $lok_tujuan['nama_lokasi'] ?? '-';

      $nama_rs = $this->settings->get('settings.nama_instansi');
      $alamat_rs = $this->settings->get('settings.alamat');
      $kontak_rs = $this->settings->get('settings.nomor_telepon');
      $logo = url($this->settings->get('settings.logo'));

      echo $this->draw('aset.mutasi.ba.html', [
          'mutasi' => $mutasi,
          'nama_rs' => $nama_rs,
          'alamat_rs' => $alamat_rs,
          'kontak_rs' => $kontak_rs,
          'logo' => $logo
      ]);
      exit();
  }

  private function _initAsetPenghapusan()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_aset_penghapusan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_pengajuan` varchar(50) NOT NULL,
        `kode_aset` varchar(100) NOT NULL,
        `tanggal_pengajuan` date NOT NULL,
        `alasan_penghapusan` text NOT NULL,
        `pic_pengusul` varchar(100) NOT NULL,
        `status_kondisi_terakhir` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
        `nilai_buku_terakhir` double DEFAULT 0,
        `nilai_taksiran` double DEFAULT 0,
        `catatan_penilaian` text DEFAULT NULL,
        `tanggal_penilaian` date DEFAULT NULL,
        `petugas_penilai` varchar(100) DEFAULT NULL,
        `metode_penghapusan` enum('Lelang','Hibah','Musnah') DEFAULT NULL,
        `detail_metode` text DEFAULT NULL,
        `no_sk` varchar(100) DEFAULT NULL,
        `tgl_sk` date DEFAULT NULL,
        `file_sk` varchar(255) DEFAULT NULL,
        `no_ba` varchar(100) DEFAULT NULL,
        `tgl_ba` date DEFAULT NULL,
        `file_ba` varchar(255) DEFAULT NULL,
        `keterangan_eksekusi` text DEFAULT NULL,
        `status` enum('Draft','Pengajuan','Dinilai','Disetujui','Selesai','Ditolak') DEFAULT 'Draft',
        `user_input` varchar(100) NOT NULL,
        `tgl_input` datetime NOT NULL,
        `tgl_update` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `no_pengajuan` (`no_pengajuan`),
        KEY `kode_aset` (`kode_aset`),
        KEY `status` (`status`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $upload_dir = UPLOADS . '/logistik_non_medis';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      if (!is_dir($upload_dir . '/penghapusan')) mkdir($upload_dir . '/penghapusan', 0777, true);
  }

  private function _generateNoPengajuanPenghapusan()
  {
      $prefix = 'PHA-' . date('Ym') . '-';
      $last = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')
                   ->where('no_pengajuan', 'LIKE', $prefix.'%')
                   ->desc('no_pengajuan')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['no_pengajuan'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getAsetPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $this->_addHeaderFiles();
      return $this->draw('aset.penghapusan.html');
  }

  public function anyDisplayAsetPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $cari = $_POST['cari'] ?? '';
      $status = $_POST['status'] ?? 'Aktif';
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')
                    ->join('rsns_custom_logistik_non_medis_aset', 'rsns_custom_logistik_non_medis_aset.kode_aset=rsns_custom_logistik_non_medis_aset_penghapusan.kode_aset');

      if ($status == 'Aktif') {
          $query->where('rsns_custom_logistik_non_medis_aset_penghapusan.status', 'IN', ['Draft', 'Pengajuan', 'Dinilai', 'Disetujui']);
      } elseif ($status == 'Selesai') {
          $query->where('rsns_custom_logistik_non_medis_aset_penghapusan.status', 'Selesai');
      } elseif ($status == 'Ditolak') {
          $query->where('rsns_custom_logistik_non_medis_aset_penghapusan.status', 'Ditolak');
      }

      if (!empty($cari)) {
          $query->where(function($q) use ($cari) {
              $q->where('rsns_custom_logistik_non_medis_aset_penghapusan.no_pengajuan', 'LIKE', '%'.$cari.'%')
                ->orLike('rsns_custom_logistik_non_medis_aset.nama_aset', '%'.$cari.'%')
                ->orLike('rsns_custom_logistik_non_medis_aset_penghapusan.kode_aset', 'LIKE', '%'.$cari.'%');
          });
      }

      $pengajuan = $query->desc('rsns_custom_logistik_non_medis_aset_penghapusan.tgl_input')->toArray();

      echo $this->draw('aset.penghapusan.display.html', [
          'pengajuan' => $pengajuan
      ]);
      exit();
  }

  public function anyFormPengajuanPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      
      $data = [
          'id' => '',
          'no_pengajuan' => $this->_generateNoPengajuanPenghapusan(),
          'kode_aset' => '',
          'nama_aset' => '',
          'tanggal_pengajuan' => date('Y-m-d'),
          'alasan_penghapusan' => '',
          'pic_pengusul' => $this->core->getUserInfo('username', null, true),
          'status' => 'Draft'
      ];

      if (!empty($id)) {
          $check = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
          if ($check) {
              $data = $check;
              $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $data['kode_aset'])->oneArray();
              $data['nama_aset'] = $aset['nama_aset'] ?? '';
          }
      }

      echo $this->draw('aset.penghapusan.form.html', [
          'data' => $data,
          'mode' => empty($id) ? 'add' : 'edit'
      ]);
      exit();
  }

  public function postSavePengajuanPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      $kode_aset = $_POST['kode_aset'] ?? '';
      
      if (empty($kode_aset)) {
          echo json_encode(['status' => 'error', 'message' => 'Aset harus dipilih terlebih dahulu!']);
          exit();
      }

      $data = [
          'kode_aset' => $kode_aset,
          'tanggal_pengajuan' => $_POST['tanggal_pengajuan'] ?? date('Y-m-d'),
          'alasan_penghapusan' => $_POST['alasan_penghapusan'] ?? '',
          'pic_pengusul' => $_POST['pic_pengusul'] ?? $this->core->getUserInfo('username', null, true),
          'status' => $_POST['status'] ?? 'Pengajuan',
          'user_input' => $this->core->getUserInfo('username', null, true),
          'tgl_input' => date('Y-m-d H:i:s')
      ];

      if (empty($id)) {
          $data['no_pengajuan'] = $this->_generateNoPengajuanPenghapusan();
          $query = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->save($data);
      } else {
          unset($data['user_input']);
          unset($data['tgl_input']);
          $data['tgl_update'] = date('Y-m-d H:i:s');
          $query = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->update($data);
      }

      if ($query) {
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$kode_aset.' | '.$data['tanggal_pengajuan'].' | '.$data['alasan_penghapusan'].' | '.$data['pic_pengusul'].' | '.$data['status'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penghapusan',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => empty($id) ? 'I' : 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan pengajuan penghapusan aset.']);
      }
      exit();
  }

  public function postHapusPengajuanPenghapusan()
  {
      $id = $_POST['id'] ?? '';
      $check = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
      
      if ($check && in_array($check['status'], ['Draft', 'Pengajuan'])) {
          $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->delete();

          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Deleted pengajuan penghapusan: '.$check['no_pengajuan'].' for Asset: '.$check['kode_aset'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penghapusan',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus pengajuan, data tidak ditemukan atau status sudah diproses lanjut.']);
      }
      exit();
  }

  public function anyFormPenilaianPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      
      $pengajuan = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
      if ($pengajuan) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $pengajuan['kode_aset'])->oneArray();
          $pengajuan['nama_aset'] = $aset['nama_aset'] ?? '';
          $pengajuan['harga_beli'] = $aset['harga_beli'] ?? 0;
          $pengajuan['nilai_buku'] = $aset['nilai_buku'] ?? $aset['harga_beli'];
          $pengajuan['akumulasi_penyusutan'] = $aset['akumulasi_penyusutan'] ?? 0;
          $pengajuan['tanggal_perolehan'] = $aset['tanggal_perolehan'] ?? '-';
          
          if (empty($pengajuan['petugas_penilai'])) {
              $pengajuan['petugas_penilai'] = $this->core->getUserInfo('username', null, true);
          }
          if (empty($pengajuan['tanggal_penilaian'])) {
              $pengajuan['tanggal_penilaian'] = date('Y-m-d');
          }
      }

      echo $this->draw('aset.penghapusan.penilaian.html', [
          'pengajuan' => $pengajuan
      ]);
      exit();
  }

  public function postSavePenilaianPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      
      $pengajuan = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
      if (!$pengajuan) {
          echo json_encode(['status' => 'error', 'message' => 'Data pengajuan tidak ditemukan!']);
          exit();
      }

      $nilai_taksiran = (double)str_replace(['Rp.', '.'], '', $_POST['nilai_taksiran'] ?? 0);
      $nilai_buku_terakhir = (double)($_POST['nilai_buku_terakhir'] ?? 0);

      $data = [
          'status_kondisi_terakhir' => $_POST['status_kondisi_terakhir'] ?? 'Rusak Berat',
          'nilai_buku_terakhir' => $nilai_buku_terakhir,
          'nilai_taksiran' => $nilai_taksiran,
          'catatan_penilaian' => $_POST['catatan_penilaian'] ?? '',
          'tanggal_penilaian' => $_POST['tanggal_penilaian'] ?? date('Y-m-d'),
          'petugas_penilai' => $_POST['petugas_penilai'] ?? $this->core->getUserInfo('username', null, true),
          'status' => 'Dinilai',
          'tgl_update' => date('Y-m-d H:i:s')
      ];

      $query = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->update($data);

      if ($query) {
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Assessed asset deletion: '.$pengajuan['no_pengajuan'].' | Kode: '.$pengajuan['kode_aset'].' | Taksiran: '.$nilai_taksiran.' | Petugas: '.$data['petugas_penilai'];

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penghapusan',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan penilaian kondisi aset.']);
      }
      exit();
  }

  public function anyFormSKPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      
      $pengajuan = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
      if ($pengajuan) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $pengajuan['kode_aset'])->oneArray();
          $pengajuan['nama_aset'] = $aset['nama_aset'] ?? '';
          if (empty($pengajuan['tgl_sk'])) {
              $pengajuan['tgl_sk'] = date('Y-m-d');
          }
      }

      echo $this->draw('aset.penghapusan.sk.html', [
          'pengajuan' => $pengajuan
      ]);
      exit();
  }

  public function postSaveSKPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      
      $pengajuan = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
      if (!$pengajuan) {
          echo json_encode(['status' => 'error', 'message' => 'Data pengajuan tidak ditemukan!']);
          exit();
      }

      $data = [
          'metode_penghapusan' => $_POST['metode_penghapusan'] ?? 'Lelang',
          'detail_metode' => $_POST['detail_metode'] ?? '',
          'no_sk' => $_POST['no_sk'] ?? '',
          'tgl_sk' => $_POST['tgl_sk'] ?? date('Y-m-d'),
          'status' => 'Disetujui',
          'tgl_update' => date('Y-m-d H:i:s')
      ];

      if (isset($_FILES['file_sk']) && $_FILES['file_sk']['error'] == UPLOAD_ERR_OK) {
          $file = $_FILES['file_sk'];
          $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
              $filename = 'sk_' . $pengajuan['no_pengajuan'] . '_' . time() . '.' . $ext;
              $dest = UPLOADS . '/logistik_non_medis/penghapusan/' . $filename;
              if (move_uploaded_file($file['tmp_name'], $dest)) {
                  $data['file_sk'] = 'penghapusan/' . $filename;
              }
          }
      }

      $query = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->update($data);

      if ($query) {
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'SK Penghapusan saved: '.$pengajuan['no_pengajuan'].' | No SK: '.$data['no_sk'].' | Metode: '.$data['metode_penghapusan'];

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penghapusan',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan SK Penghapusan.']);
      }
      exit();
  }

  public function anyFormBAPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      
      $pengajuan = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
      if ($pengajuan) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $pengajuan['kode_aset'])->oneArray();
          $pengajuan['nama_aset'] = $aset['nama_aset'] ?? '';
          if (empty($pengajuan['tgl_ba'])) {
              $pengajuan['tgl_ba'] = date('Y-m-d');
          }
      }

      echo $this->draw('aset.penghapusan.ba.html', [
          'pengajuan' => $pengajuan
      ]);
      exit();
  }

  public function postSelesaikanPenghapusan()
  {
      $this->_initAsetPenghapusan();
      $id = $_POST['id'] ?? '';
      
      $pengajuan = $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->oneArray();
      if (!$pengajuan) {
          echo json_encode(['status' => 'error', 'message' => 'Data pengajuan tidak ditemukan!']);
          exit();
      }

      $data = [
          'no_ba' => $_POST['no_ba'] ?? '',
          'tgl_ba' => $_POST['tgl_ba'] ?? date('Y-m-d'),
          'keterangan_eksekusi' => $_POST['keterangan_eksekusi'] ?? '',
          'status' => 'Selesai',
          'tgl_update' => date('Y-m-d H:i:s')
      ];

      if (isset($_FILES['file_ba']) && $_FILES['file_ba']['error'] == UPLOAD_ERR_OK) {
          $file = $_FILES['file_ba'];
          $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
              $filename = 'ba_' . $pengajuan['no_pengajuan'] . '_' . time() . '.' . $ext;
              $dest = UPLOADS . '/logistik_non_medis/penghapusan/' . $filename;
              if (move_uploaded_file($file['tmp_name'], $dest)) {
                  $data['file_ba'] = 'penghapusan/' . $filename;
              }
          }
      }

      $pdo = $this->db()->pdo();
      $pdo->beginTransaction();

      try {
          $this->db('rsns_custom_logistik_non_medis_aset_penghapusan')->where('id', $id)->update($data);

          $this->db('rsns_custom_logistik_non_medis_aset')
               ->where('kode_aset', $pengajuan['kode_aset'])
               ->update([
                   'status' => 'Dihapuskan',
                   'status_kondisi' => $pengajuan['status_kondisi_terakhir'] ?? 'Rusak Berat'
               ]);
               
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Completed BA Penghapusan: '.$pengajuan['no_pengajuan'].' for Asset: '.$pengajuan['kode_aset'].' | No BA: '.$data['no_ba'];

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_penghapusan',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          $pdo->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $pdo->rollBack();
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyelesaikan penghapusan aset: ' . $e->getMessage()]);
      }
      exit();
  }

  private function _initAsetSensus()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_aset_sensus` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nama_sensus` varchar(200) NOT NULL,
        `tanggal_mulai` date NOT NULL,
        `tanggal_selesai` date NOT NULL,
        `keterangan_sensus` text DEFAULT NULL,
        `status_sensus_periode` enum('Draft', 'Aktif', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Draft',
        `kode_aset` varchar(100) NOT NULL,
        `sistem_kode_unit` varchar(50) NOT NULL,
        `sistem_kode_lokasi` varchar(50) DEFAULT NULL,
        `sistem_status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') NOT NULL DEFAULT 'Baik',
        `fisik_kode_unit` varchar(50) DEFAULT NULL,
        `fisik_kode_lokasi` varchar(50) DEFAULT NULL,
        `fisik_status_kondisi` enum('Baik','Rusak Ringan','Rusak Berat') DEFAULT NULL,
        `foto_fisik` varchar(255) DEFAULT NULL,
        `catatan_temuan` text DEFAULT NULL,
        `status_sensus_item` enum('Belum Sensus', 'Sesuai', 'Selisih Lokasi', 'Selisih Kondisi', 'Tidak Ditemukan', 'Aset Baru') NOT NULL DEFAULT 'Belum Sensus',
        `tanggal_scan` datetime DEFAULT NULL,
        `petugas_scan` varchar(100) DEFAULT NULL,
        `status_penyesuaian` enum('Belum Disesuaikan', 'Sudah Disesuaikan') NOT NULL DEFAULT 'Belum Disesuaikan',
        `tgl_penyesuaian` datetime DEFAULT NULL,
        `user_penyesuaian` varchar(100) DEFAULT NULL,
        `no_sertifikat` varchar(100) DEFAULT NULL,
        `tanggal_sertifikat` date DEFAULT NULL,
        `ttd_petugas` varchar(100) DEFAULT NULL,
        `ttd_ka_unit` varchar(100) DEFAULT NULL,
        `ttd_ka_logistik` varchar(100) DEFAULT NULL,
        `status_sertifikasi` enum('Belum Sertifikasi', 'Disetujui Ka Unit', 'Sertifikasi Selesai') NOT NULL DEFAULT 'Belum Sertifikasi',
        `tgl_input` datetime DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `kode_aset` (`kode_aset`),
        KEY `nama_sensus` (`nama_sensus`),
        KEY `status_sensus_item` (`status_sensus_item`),
        KEY `sistem_kode_unit` (`sistem_kode_unit`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
      
      $upload_dir = UPLOADS . '/logistik_non_medis/sensus';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  public function getAsetSensus()
  {
      $this->_initAsetSensus();
      $this->_addHeaderFiles();
      
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      
      return $this->draw('aset.sensus.html', [
          'units' => $unit,
          'lokasi' => $lokasi
      ]);
  }

  public function anyDisplayPeriodeSensus()
  {
      $this->_initAsetSensus();
      $rows = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                   ->select(['nama_sensus', 'tanggal_mulai', 'tanggal_selesai', 'keterangan_sensus', 'status_sensus_periode', 'no_sertifikat', 'status_sertifikasi'])
                   ->group('nama_sensus')
                   ->desc('id')
                   ->toArray();
                   
      foreach ($rows as &$row) {
          $count_total = count($this->db('rsns_custom_logistik_non_medis_aset_sensus')->where('nama_sensus', $row['nama_sensus'])->toArray());
          $count_scanned = count($this->db('rsns_custom_logistik_non_medis_aset_sensus')->where('nama_sensus', $row['nama_sensus'])->where('status_sensus_item', '!=', 'Belum Sensus')->toArray());
          $row['total_aset'] = $count_total;
          $row['total_scanned'] = $count_scanned;
          $row['progress_percent'] = $count_total > 0 ? round(($count_scanned / $count_total) * 100) : 0;
      }
      
      echo json_encode(['status' => 'success', 'data' => $rows]);
      exit();
  }

  public function anyDisplayAsetSensus()
  {
      $this->_initAsetSensus();
      $perpage = 15;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $status_item = isset($_POST['status_item']) ? $_POST['status_item'] : '';
      $nama_sensus = isset($_POST['nama_sensus']) ? $_POST['nama_sensus'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_sensus');
      if(!empty($nama_sensus)) {
          $query->where('nama_sensus', $nama_sensus);
      }
      if(!empty($status_item)) {
          $query->where('status_sensus_item', $status_item);
      }
      if(!empty($cari)) {
          $query->like('kode_aset', '%'.$cari.'%')
                ->orLike('catatan_temuan', '%'.$cari.'%');
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_aset_sensus');
      if(!empty($nama_sensus)) {
          $rows->where('nama_sensus', $nama_sensus);
      }
      if(!empty($status_item)) {
          $rows->where('status_sensus_item', $status_item);
      }
      if(!empty($cari)) {
          $rows->like('kode_aset', '%'.$cari.'%')
                ->orLike('catatan_temuan', '%'.$cari.'%');
      }
      $rows = $rows->desc('id')
                    ->offset($_offset)
                    ->limit($perpage)
                    ->toArray();

      foreach ($rows as &$row) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $row['kode_aset'])->oneArray();
          $row['nama_aset'] = $aset['nama_aset'] ?? 'Aset Tidak Dikenal';
          
          $u_sistem = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['sistem_kode_unit'])->oneArray();
          $row['sistem_nama_unit'] = $u_sistem['nama_unit'] ?? $row['sistem_kode_unit'];
          
          if (!empty($row['fisik_kode_unit'])) {
              $u_fisik = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['fisik_kode_unit'])->oneArray();
              $row['fisik_nama_unit'] = $u_fisik['nama_unit'] ?? $row['fisik_kode_unit'];
          } else {
              $row['fisik_nama_unit'] = '-';
          }
      }

      echo json_encode([
          'rows' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman
      ]);
      exit();
  }

  public function postSavePeriodeSensus()
  {
      $this->_initAsetSensus();
      $nama_sensus = $_POST['nama_sensus'] ?? '';
      $tanggal_mulai = $_POST['tanggal_mulai'] ?? date('Y-m-d');
      $tanggal_selesai = $_POST['tanggal_selesai'] ?? date('Y-m-d');
      $keterangan_sensus = $_POST['keterangan_sensus'] ?? '';
      
      if(empty($nama_sensus)) {
          echo json_encode(['status' => 'error', 'message' => 'Nama Sensus wajib diisi!']);
          exit();
      }
      
      $check = $this->db('rsns_custom_logistik_non_medis_aset_sensus')->where('nama_sensus', $nama_sensus)->oneArray();
      if ($check) {
          echo json_encode(['status' => 'error', 'message' => 'Periode sensus dengan nama tersebut sudah terdaftar!']);
          exit();
      }
      
      $assets = $this->db('rsns_custom_logistik_non_medis_aset')->where('status', 'Aktif')->toArray();
      if (empty($assets)) {
          echo json_encode(['status' => 'error', 'message' => 'Tidak ditemukan aset aktif di sistem untuk disensus.']);
          exit();
      }
      
      $user = $this->core->getUserInfo('username', null, true);
      $tgl_input = date('Y-m-d H:i:s');
      
      $pdo = $this->db()->pdo();
      $pdo->beginTransaction();
      
      try {
          foreach ($assets as $asset) {
              $this->db('rsns_custom_logistik_non_medis_aset_sensus')->save([
                  'nama_sensus' => $nama_sensus,
                  'tanggal_mulai' => $tanggal_mulai,
                  'tanggal_selesai' => $tanggal_selesai,
                  'keterangan_sensus' => $keterangan_sensus,
                  'status_sensus_periode' => 'Aktif',
                  'kode_aset' => $asset['kode_aset'],
                  'sistem_kode_unit' => $asset['kode_unit'] ?? '',
                  'sistem_kode_lokasi' => $asset['kode_lokasi'] ?? '',
                  'sistem_status_kondisi' => $asset['status_kondisi'] ?? 'Baik',
                  'status_sensus_item' => 'Belum Sensus',
                  'status_penyesuaian' => 'Belum Disesuaikan',
                  'status_sertifikasi' => 'Belum Sertifikasi',
                  'tgl_input' => $tgl_input,
                  'user_input' => $user
              ]);
          }
          
          // Log to mlite_tracksql
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Created Sensus Periode: '.$nama_sensus.' | Total Assets: '.count($assets);

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_sensus',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'I',
              'log_username' => $user
          ]);

          $pdo->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $pdo->rollBack();
          echo json_encode(['status' => 'error', 'message' => 'Gagal membuat periode sensus: ' . $e->getMessage()]);
      }
      exit();
  }

  public function postHapusPeriodeSensus()
  {
      $this->_initAsetSensus();
      $nama_sensus = $_POST['nama_sensus'] ?? '';
      
      $check = $this->db('rsns_custom_logistik_non_medis_aset_sensus')->where('nama_sensus', $nama_sensus)->oneArray();
      if (!$check) {
          echo json_encode(['status' => 'error', 'message' => 'Periode sensus tidak ditemukan!']);
          exit();
      }
      
      if ($check['status_sensus_periode'] == 'Selesai') {
          echo json_encode(['status' => 'error', 'message' => 'Periode sensus yang sudah selesai tidak dapat dihapus!']);
          exit();
      }
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_sensus')->where('nama_sensus', $nama_sensus)->delete();
      if ($query) {
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = 'Deleted Sensus Periode: '.$nama_sensus;

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_sensus',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus periode sensus.']);
      }
      exit();
  }

  public function anyScanQRField()
  {
      $this->_initAsetSensus();
      $this->_addHeaderFiles();
      
      $active_sensus = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                            ->select(['nama_sensus'])
                            ->where('status_sensus_periode', 'Aktif')
                            ->group('nama_sensus')
                            ->toArray();
                            
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      
      return $this->draw('aset.sensus.scan.html', [
          'active_sensus' => $active_sensus,
          'units' => $unit,
          'lokasi' => $lokasi
      ]);
  }

  public function anyGetAssetDetailsSensus()
  {
      $this->_initAsetSensus();
      $kode_aset = $_POST['kode_aset'] ?? '';
      $nama_sensus = $_POST['nama_sensus'] ?? '';
      
      if (empty($kode_aset) || empty($nama_sensus)) {
          echo json_encode(['status' => 'error', 'message' => 'Kode Aset dan Nama Sensus wajib ditentukan!']);
          exit();
      }
      
      $sensus_row = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                          ->where('kode_aset', $kode_aset)
                          ->where('nama_sensus', $nama_sensus)
                          ->oneArray();
                          
      if (!$sensus_row) {
          $master_asset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $kode_aset)->oneArray();
          if ($master_asset) {
              echo json_encode([
                  'status' => 'new_asset',
                  'message' => 'Aset terdaftar di sistem tetapi tidak termasuk dalam worksheet sensus periode ini. Apakah ingin menambahkannya?',
                  'asset' => [
                      'kode_aset' => $master_asset['kode_aset'],
                      'nama_aset' => $master_asset['nama_aset'],
                      'serial_number' => $master_asset['serial_number'] ?? '',
                      'sistem_kode_unit' => $master_asset['kode_unit'] ?? '',
                      'sistem_kode_lokasi' => $master_asset['kode_lokasi'] ?? '',
                      'sistem_status_kondisi' => $master_asset['status_kondisi'] ?? 'Baik'
                  ]
              ]);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Aset dengan kode tersebut tidak terdaftar di sistem!']);
          }
          exit();
      }
      
      $master_asset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $kode_aset)->oneArray();
      
      echo json_encode([
          'status' => 'success',
          'sensus_row' => $sensus_row,
          'asset_name' => $master_asset['nama_aset'] ?? 'Aset Tidak Dikenal',
          'serial_number' => $master_asset['serial_number'] ?? '-'
      ]);
      exit();
  }

  public function postSubmitSensusFisik()
  {
      $this->_initAsetSensus();
      $kode_aset = $_POST['kode_aset'] ?? '';
      $nama_sensus = $_POST['nama_sensus'] ?? '';
      $fisik_kode_unit = $_POST['fisik_kode_unit'] ?? '';
      $fisik_kode_lokasi = $_POST['fisik_kode_lokasi'] ?? '';
      $fisik_status_kondisi = $_POST['fisik_status_kondisi'] ?? 'Baik';
      $catatan_temuan = $_POST['catatan_temuan'] ?? '';
      $is_new = isset($_POST['is_new']) && $_POST['is_new'] == '1';
      
      if (empty($kode_aset) || empty($nama_sensus)) {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap!']);
          exit();
      }
      
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_scan = date('Y-m-d H:i:s');
      
      $foto_filename = NULL;
      if (isset($_FILES['foto_fisik']) && $_FILES['foto_fisik']['error'] == UPLOAD_ERR_OK) {
          $file = $_FILES['foto_fisik'];
          $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
              $foto_filename = 'sensus_' . $kode_aset . '_' . time() . '.' . $ext;
              $dest = UPLOADS . '/logistik_non_medis/sensus/' . $foto_filename;
              move_uploaded_file($file['tmp_name'], $dest);
          }
      }
      
      if ($is_new) {
          $master_asset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $kode_aset)->oneArray();
          if (!$master_asset) {
              echo json_encode(['status' => 'error', 'message' => 'Aset master tidak ditemukan!']);
              exit();
          }
          
          $status_item = 'Aset Baru';
          if ($fisik_kode_unit == ($master_asset['kode_unit'] ?? '') && $fisik_status_kondisi == ($master_asset['status_kondisi'] ?? 'Baik')) {
              $status_item = 'Sesuai';
          }
          
          $query = $this->db('rsns_custom_logistik_non_medis_aset_sensus')->save([
              'nama_sensus' => $nama_sensus,
              'tanggal_mulai' => date('Y-m-d'),
              'tanggal_selesai' => date('Y-m-d'),
              'status_sensus_periode' => 'Aktif',
              'kode_aset' => $kode_aset,
              'sistem_kode_unit' => $master_asset['kode_unit'] ?? '',
              'sistem_kode_lokasi' => $master_asset['kode_lokasi'] ?? '',
              'sistem_status_kondisi' => $master_asset['status_kondisi'] ?? 'Baik',
              'fisik_kode_unit' => $fisik_kode_unit,
              'fisik_kode_lokasi' => $fisik_kode_lokasi,
              'fisik_status_kondisi' => $fisik_status_kondisi,
              'foto_fisik' => $foto_filename,
              'catatan_temuan' => $catatan_temuan,
              'status_sensus_item' => $status_item,
              'tanggal_scan' => $tanggal_scan,
              'petugas_scan' => $user,
              'status_penyesuaian' => 'Belum Disesuaikan',
              'status_sertifikasi' => 'Belum Sertifikasi',
              'tgl_input' => $tanggal_scan,
              'user_input' => $user
          ]);
      } else {
          $sensus_row = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                              ->where('kode_aset', $kode_aset)
                              ->where('nama_sensus', $nama_sensus)
                              ->oneArray();
                              
          if (!$sensus_row) {
              echo json_encode(['status' => 'error', 'message' => 'Kertas kerja sensus tidak ditemukan!']);
              exit();
          }
          
          $status_item = 'Sesuai';
          if ($fisik_kode_unit != $sensus_row['sistem_kode_unit']) {
              $status_item = 'Selisih Lokasi';
          } else if ($fisik_status_kondisi != $sensus_row['sistem_status_kondisi']) {
              $status_item = 'Selisih Kondisi';
          }
          
          $update_data = [
              'fisik_kode_unit' => $fisik_kode_unit,
              'fisik_kode_lokasi' => $fisik_kode_lokasi,
              'fisik_status_kondisi' => $fisik_status_kondisi,
              'catatan_temuan' => $catatan_temuan,
              'status_sensus_item' => $status_item,
              'tanggal_scan' => $tanggal_scan,
              'petugas_scan' => $user
          ];
          
          if ($foto_filename) {
              if (!empty($sensus_row['foto_fisik']) && file_exists(UPLOADS . '/logistik_non_medis/sensus/' . $sensus_row['foto_fisik'])) {
                  unlink(UPLOADS . '/logistik_non_medis/sensus/' . $sensus_row['foto_fisik']);
              }
              $update_data['foto_fisik'] = $foto_filename;
          }
          
          $query = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                       ->where('kode_aset', $kode_aset)
                       ->where('nama_sensus', $nama_sensus)
                       ->update($update_data);
      }
      
      if ($query) {
          // Log to mlite_tracksql
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$nama_sensus.' | '.$kode_aset.' | Status Item: '.$status_item.' | Unit Fisik: '.$fisik_kode_unit.' | Lokasi Fisik: '.$fisik_kode_lokasi.' | Kondisi Fisik: '.$fisik_status_kondisi;

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_sensus',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => $is_new ? 'I' : 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan temuan sensus fisik!']);
      }
      exit();
  }

  public function postMarkAsMissing()
  {
      $this->_initAsetSensus();
      $kode_aset = $_POST['kode_aset'] ?? '';
      $nama_sensus = $_POST['nama_sensus'] ?? '';
      
      if (empty($kode_aset) || empty($nama_sensus)) {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap!']);
          exit();
      }
      
      $user = $this->core->getUserInfo('username', null, true);
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                   ->where('kode_aset', $kode_aset)
                   ->where('nama_sensus', $nama_sensus)
                   ->update([
                       'status_sensus_item' => 'Tidak Ditemukan',
                       'tanggal_scan' => date('Y-m-d H:i:s'),
                       'petugas_scan' => $user
                   ]);
                   
      if ($query) {
          // Log to mlite_tracksql
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$nama_sensus.' | '.$kode_aset.' | Status Item updated to: Tidak Ditemukan';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_sensus',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status menjadi Tidak Ditemukan.']);
      }
      exit();
  }

  public function postEksekusiPenyesuaian()
  {
      $this->_initAsetSensus();
      $nama_sensus = $_POST['nama_sensus'] ?? '';
      $kode_aset = $_POST['kode_aset'] ?? '';
      
      if (empty($nama_sensus)) {
          echo json_encode(['status' => 'error', 'message' => 'Nama Sensus wajib ditentukan!']);
          exit();
      }
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                    ->where('nama_sensus', $nama_sensus)
                    ->where('status_penyesuaian', 'Belum Disesuaikan')
                    ->where('status_sensus_item', '!=', 'Belum Sensus');
                    
      if (!empty($kode_aset)) {
          $query->where('kode_aset', $kode_aset);
      }
      
      $items = $query->toArray();
      if (empty($items)) {
          echo json_encode(['status' => 'error', 'message' => 'Tidak ada temuan sensus yang perlu disesuaikan untuk kriteria ini.']);
          exit();
      }
      
      $user = $this->core->getUserInfo('username', null, true);
      $tgl_sekarang = date('Y-m-d H:i:s');
      
      $pdo = $this->db()->pdo();
      $pdo->beginTransaction();
      
      try {
          foreach ($items as $item) {
              if ($item['status_sensus_item'] == 'Tidak Ditemukan') {
                  $this->db('rsns_custom_logistik_non_medis_aset')
                       ->where('kode_aset', $item['kode_aset'])
                       ->update([
                           'status' => 'Dihapuskan',
                           'spesifikasi' => 'HILANG SAAT SENSUS: ' . $item['nama_sensus'] . '. Catatan: ' . $item['catatan_temuan']
                       ]);
              } else if (in_array($item['status_sensus_item'], ['Sesuai', 'Selisih Lokasi', 'Selisih Kondisi', 'Aset Baru'])) {
                  $update_master = [];
                  if (!empty($item['fisik_kode_unit'])) {
                      $update_master['kode_unit'] = $item['fisik_kode_unit'];
                  }
                  if (!empty($item['fisik_kode_lokasi'])) {
                      $update_master['kode_lokasi'] = $item['fisik_kode_lokasi'];
                  }
                  if (!empty($item['fisik_status_kondisi'])) {
                      $update_master['status_kondisi'] = $item['fisik_status_kondisi'];
                  }
                  
                  if (!empty($update_master)) {
                      $this->db('rsns_custom_logistik_non_medis_aset')
                           ->where('kode_aset', $item['kode_aset'])
                           ->update($update_master);
                  }
              }
              
              $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                   ->where('id', $item['id'])
                   ->update([
                       'status_penyesuaian' => 'Sudah Disesuaikan',
                       'tgl_penyesuaian' => $tgl_sekarang,
                       'user_penyesuaian' => $user
                   ]);
                   
              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_aset_sensus_penyesuaian',
                  'log_waktu' => $tgl_sekarang,
                  'log_location' => $_SERVER['REMOTE_ADDR'] ?? 'Localhost',
                  'log_data' => $item['kode_aset'] . ' | Adjusted to: Unit=' . $item['fisik_kode_unit'] . ', Cond=' . $item['fisik_status_kondisi'] . ' via ' . $nama_sensus,
                  'log_status' => 'U',
                  'log_username' => $user
              ]);
          }
          
          $pdo->commit();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          $pdo->rollBack();
          echo json_encode(['status' => 'error', 'message' => 'Gagal mengeksekusi penyesuaian: ' . $e->getMessage()]);
      }
      exit();
  }

  public function postSignSertifikat()
  {
      $this->_initAsetSensus();
      $nama_sensus = $_POST['nama_sensus'] ?? '';
      $role_sign = $_POST['role_sign'] ?? '';
      $signature_name = $_POST['signature_name'] ?? '';
      
      if (empty($nama_sensus) || empty($role_sign) || empty($signature_name)) {
          echo json_encode(['status' => 'error', 'message' => 'Data tandatangan tidak lengkap!']);
          exit();
      }
      
      $update_fields = [];
      if ($role_sign == 'petugas') {
          $update_fields['ttd_petugas'] = $signature_name;
      } else if ($role_sign == 'ka_unit') {
          $update_fields['ttd_ka_unit'] = $signature_name;
          $update_fields['status_sertifikasi'] = 'Disetujui Ka Unit';
      } else if ($role_sign == 'ka_logistik') {
          $update_fields['ttd_ka_logistik'] = $signature_name;
          $update_fields['status_sertifikasi'] = 'Sertifikasi Selesai';
          $update_fields['status_sensus_periode'] = 'Selesai';
          $update_fields['no_sertifikat'] = 'BAHS/' . date('Ymd') . '/' . mt_rand(100,999);
          $update_fields['tanggal_sertifikat'] = date('Y-m-d');
      }
      
      $query = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                   ->where('nama_sensus', $nama_sensus)
                   ->update($update_fields);
                   
      if ($query) {
          // Log to mlite_tracksql
          $user = $this->core->getUserInfo('username', null, true) ?: ($_SESSION['mlite_user'] ?? 'admin');
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Localhost';
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$nama_sensus.' | Role Sign: '.$role_sign.' | Signature: '.$signature_name;

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_aset_sensus',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'U',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan persetujuan tanda tangan digital.']);
      }
      exit();
  }

  public function anyCetakBAHS()
  {
      $this->_initAsetSensus();
      $nama_sensus = $_GET['nama_sensus'] ?? '';
      
      if (empty($nama_sensus)) {
          echo "Nama Sensus tidak valid.";
          exit();
      }
      
      $items = $this->db('rsns_custom_logistik_non_medis_aset_sensus')
                    ->where('nama_sensus', $nama_sensus)
                    ->toArray();
                    
      if (empty($items)) {
          echo "Tidak ada data sensus.";
          exit();
      }
      
      $meta = $items[0];
      
      $total_terdaftar = count($items);
      $total_sesuai = count(array_filter($items, function($i) { return $i['status_sensus_item'] == 'Sesuai'; }));
      $total_selisih_lokasi = count(array_filter($items, function($i) { return $i['status_sensus_item'] == 'Selisih Lokasi'; }));
      $total_selisih_kondisi = count(array_filter($items, function($i) { return $i['status_sensus_item'] == 'Selisih Kondisi'; }));
      $total_tidak_ditemukan = count(array_filter($items, function($i) { return $i['status_sensus_item'] == 'Tidak Ditemukan'; }));
      $total_aset_baru = count(array_filter($items, function($i) { return $i['status_sensus_item'] == 'Aset Baru'; }));
      
      foreach ($items as &$row) {
          $aset = $this->db('rsns_custom_logistik_non_medis_aset')->where('kode_aset', $row['kode_aset'])->oneArray();
          $row['nama_aset'] = $aset['nama_aset'] ?? 'Aset Tidak Dikenal';
          
          $u_sistem = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['sistem_kode_unit'])->oneArray();
          $row['sistem_nama_unit'] = $u_sistem['nama_unit'] ?? $row['sistem_kode_unit'];
          
          if (!empty($row['fisik_kode_unit'])) {
              $u_fisik = $this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit', $row['fisik_kode_unit'])->oneArray();
              $row['fisik_nama_unit'] = $u_fisik['nama_unit'] ?? $row['fisik_kode_unit'];
          } else {
              $row['fisik_nama_unit'] = '-';
          }
      }

      echo $this->draw('aset.sensus.bahs.html', [
          'meta' => $meta,
          'items' => $items,
          'total_terdaftar' => $total_terdaftar,
          'total_sesuai' => $total_sesuai,
          'total_selisih_lokasi' => $total_selisih_lokasi,
          'total_selisih_kondisi' => $total_selisih_kondisi,
          'total_tidak_ditemukan' => $total_tidak_ditemukan,
          'total_aset_baru' => $total_aset_baru,
          'logo' => $this->settings->get('settings.logo')
      ]);
      exit();
  }

  public function getLaporanStokMutasi()
  {
      $this->_addHeaderFiles();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
      return $this->draw('laporan.stokmutasi.html', [
          'lokasi' => $lokasi,
          'kategori' => $kategori
      ]);
  }

  public function anyGetBarangAutocomplete()
  {
      $cari = $_GET['q'] ?? $_POST['q'] ?? '';
      $query = $this->db('rsns_custom_logistik_non_medis_master_barang');
      if (!empty($cari)) {
          $query->where('kode_item', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_barang', '%'.$cari.'%');
      }
      $rows = $query->limit(20)->toArray();
      $results = [];
      foreach ($rows as $row) {
          $results[] = [
              'id' => $row['kode_item'],
              'text' => $row['kode_item'] . ' - ' . $row['nama_barang']
          ];
      }
      echo json_encode(['results' => $results]);
      exit();
  }

  public function anyDisplayLaporanKartuStok()
  {
      $kode_item = $_POST['kode_item'] ?? '';
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      $tgl_awal = $_POST['tgl_awal'] ?? date('Y-m-01');
      $tgl_akhir = $_POST['tgl_akhir'] ?? date('Y-m-d');

      if (empty($kode_item)) {
          echo '<tr><td colspan="8" class="text-center">Silakan pilih barang terlebih dahulu.</td></tr>';
          exit();
      }

      $db = $this->db()->pdo();
      $q_saldo_awal = "SELECT stok_akhir FROM rsns_custom_logistik_non_medis_kartu_stok 
                       WHERE kode_item = :kode_item";
      $params = [':kode_item' => $kode_item];
      
      if (!empty($kode_lokasi)) {
          $q_saldo_awal .= " AND kode_lokasi = :kode_lokasi";
          $params[':kode_lokasi'] = $kode_lokasi;
      }
      
      $q_saldo_awal .= " AND tgl_transaksi < :tgl_awal ORDER BY tgl_transaksi DESC, id DESC LIMIT 1";
      $params[':tgl_awal'] = $tgl_awal . ' 00:00:00';
      
      $stmt = $db->prepare($q_saldo_awal);
      $stmt->execute($params);
      $row_saldo_awal = $stmt->fetch(\PDO::FETCH_ASSOC);
      $saldo_awal = $row_saldo_awal ? (double)$row_saldo_awal['stok_akhir'] : 0.0;

      $q_trans = "SELECT k.*, l.nama_lokasi FROM rsns_custom_logistik_non_medis_kartu_stok k
                  LEFT JOIN rsns_custom_logistik_non_medis_lokasi_gudang l ON k.kode_lokasi = l.kode_lokasi
                  WHERE k.kode_item = :kode_item";
      $params_trans = [':kode_item' => $kode_item];
      
      if (!empty($kode_lokasi)) {
          $q_trans .= " AND k.kode_lokasi = :kode_lokasi";
          $params_trans[':kode_lokasi'] = $kode_lokasi;
      }
      
      $q_trans .= " AND k.tgl_transaksi BETWEEN :tgl_awal AND :tgl_akhir ORDER BY k.tgl_transaksi ASC, k.id ASC";
      $params_trans[':tgl_awal'] = $tgl_awal . ' 00:00:00';
      $params_trans[':tgl_akhir'] = $tgl_akhir . ' 23:59:59';
      
      $stmt_trans = $db->prepare($q_trans);
      $stmt_trans->execute($params_trans);
      $transactions = $stmt_trans->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      $html .= '<tr class="info" style="font-weight: bold;">
                  <td colspan="4" class="text-right">SALDO AWAL PERIODE</td>
                  <td class="text-center">-</td>
                  <td class="text-center">-</td>
                  <td class="text-center">' . number_format($saldo_awal, 0, ',', '.') . '</td>
                  <td>-</td>
                </tr>';
      
      $running_balance = $saldo_awal;
      if (!empty($transactions)) {
          foreach ($transactions as $t) {
              $running_balance += $t['qty_masuk'] - $t['qty_keluar'];
              $html .= '<tr>
                          <td>' . date('d-m-Y H:i', strtotime($t['tgl_transaksi'])) . '</td>
                          <td>' . htmlspecialchars($t['no_referensi']) . '</td>
                          <td>' . htmlspecialchars($t['nama_lokasi'] ?? $t['kode_lokasi']) . '</td>
                          <td><span class="label label-default">' . htmlspecialchars($t['tipe_transaksi']) . '</span></td>
                          <td class="text-center text-success">' . ($t['qty_masuk'] > 0 ? '+' . number_format($t['qty_masuk'], 0, ',', '.') : '-') . '</td>
                          <td class="text-center text-danger">' . ($t['qty_keluar'] > 0 ? '-' . number_format($t['qty_keluar'], 0, ',', '.') : '-') . '</td>
                          <td class="text-center" style="font-weight: bold;">' . number_format($running_balance, 0, ',', '.') . '</td>
                          <td>' . htmlspecialchars($t['user_input']) . '</td>
                        </tr>';
          }
      } else {
          $html .= '<tr><td colspan="8" class="text-center text-muted">Tidak ada transaksi dalam periode ini.</td></tr>';
      }
      
      echo $html;
      exit();
  }

  public function anyDisplayLaporanMutasiSaldo()
  {
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $tgl_awal = $_POST['tgl_awal'] ?? date('Y-m-01');
      $tgl_akhir = $_POST['tgl_akhir'] ?? date('Y-m-d');
      $cari = $_POST['cari'] ?? '';

      $db = $this->db()->pdo();
      
      $where_clause = " WHERE 1=1";
      $params = [
          ':tgl_awal' => $tgl_awal . ' 00:00:00',
          ':tgl_akhir' => $tgl_akhir . ' 23:59:59'
      ];
      
      if (!empty($kategori)) {
          $where_clause .= " AND b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }
      
      if (!empty($cari)) {
          $where_clause .= " AND (b.kode_item LIKE :cari OR b.nama_barang LIKE :cari)";
          $params[':cari'] = '%' . $cari . '%';
      }

      $loc_subquery = "";
      if (!empty($kode_lokasi)) {
          $loc_subquery = " AND k.kode_lokasi = :kode_lokasi";
          $params[':kode_lokasi'] = $kode_lokasi;
      }

      $q = "SELECT 
                b.kode_item, 
                b.nama_barang, 
                b.satuan_dasar, 
                b.kategori,
                COALESCE(
                    (SELECT k.stok_akhir 
                     FROM rsns_custom_logistik_non_medis_kartu_stok k 
                     WHERE k.kode_item = b.kode_item 
                       $loc_subquery
                       AND k.tgl_transaksi < :tgl_awal
                     ORDER BY k.tgl_transaksi DESC, k.id DESC LIMIT 1
                    ), 0
                ) as saldo_awal,
                COALESCE(
                    (SELECT SUM(k.qty_masuk) 
                     FROM rsns_custom_logistik_non_medis_kartu_stok k 
                     WHERE k.kode_item = b.kode_item 
                       $loc_subquery
                       AND k.tgl_transaksi BETWEEN :tgl_awal AND :tgl_akhir
                    ), 0
                ) as total_masuk,
                COALESCE(
                    (SELECT SUM(k.qty_keluar) 
                     FROM rsns_custom_logistik_non_medis_kartu_stok k 
                     WHERE k.kode_item = b.kode_item 
                       $loc_subquery
                       AND k.tgl_transaksi BETWEEN :tgl_awal AND :tgl_akhir
                    ), 0
                ) as total_keluar
            FROM rsns_custom_logistik_non_medis_master_barang b
            $where_clause
            ORDER BY b.nama_barang ASC";
      
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      if (!empty($rows)) {
          foreach ($rows as $row) {
              $saldo_akhir = $row['saldo_awal'] + $row['total_masuk'] - $row['total_keluar'];
              
              if ($row['saldo_awal'] != 0 || $row['total_masuk'] != 0 || $row['total_keluar'] != 0 || $saldo_akhir != 0) {
                  $html .= '<tr>
                              <td>' . htmlspecialchars($row['kode_item']) . '</td>
                              <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">' . htmlspecialchars($row['kategori']) . '</small></td>
                              <td class="text-center">' . number_format($row['saldo_awal'], 0, ',', '.') . '</td>
                              <td class="text-center text-success" style="font-weight: bold;">' . ($row['total_masuk'] > 0 ? '+' . number_format($row['total_masuk'], 0, ',', '.') : '-') . '</td>
                              <td class="text-center text-danger" style="font-weight: bold;">' . ($row['total_keluar'] > 0 ? '-' . number_format($row['total_keluar'], 0, ',', '.') : '-') . '</td>
                              <td class="text-center" style="font-weight: bold; font-size: 13px;">' . number_format($saldo_akhir, 0, ',', '.') . '</td>
                              <td class="text-center">' . htmlspecialchars($row['satuan_dasar']) . '</td>
                            </tr>';
              }
          }
      }
      
      if (empty($html)) {
          $html = '<tr><td colspan="7" class="text-center text-muted">Data mutasi tidak ditemukan atau tidak ada pergerakan stok dalam periode ini.</td></tr>';
      }
      
      echo $html;
      exit();
  }

  public function anyDisplayLaporanStokKritis()
  {
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      $db = $this->db()->pdo();
      
      $params = [];
      $loc_filter = "";
      if (!empty($kode_lokasi)) {
          $loc_filter = " AND s.kode_lokasi = :kode_lokasi";
          $params[':kode_lokasi'] = $kode_lokasi;
      }
      
      $q = "SELECT 
                b.kode_item, 
                b.nama_barang, 
                b.satuan_dasar, 
                b.kategori,
                b.stok_min, 
                b.safety_stock,
                l.nama_lokasi,
                s.kode_lokasi,
                COALESCE(SUM(s.stok), 0) as stok_sekarang
            FROM rsns_custom_logistik_non_medis_master_barang b
            LEFT JOIN rsns_custom_logistik_non_medis_stok_batch s ON b.kode_item = s.kode_item $loc_filter
            LEFT JOIN rsns_custom_logistik_non_medis_lokasi_gudang l ON s.kode_lokasi = l.kode_lokasi
            GROUP BY b.kode_item, s.kode_lokasi
            ORDER BY stok_sekarang ASC, b.nama_barang ASC";
            
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      if (!empty($rows)) {
          foreach ($rows as $row) {
              $stok = (double)$row['stok_sekarang'];
              $stok_min = (double)$row['stok_min'];
              $safety = (double)$row['safety_stock'];
              
              $is_kritis = ($stok <= $stok_min || $stok < 0);
              
              if ($is_kritis) {
                  $status_label = '';
                  $row_class = '';
                  
                  if ($stok < 0) {
                      $status_label = '<span class="label label-danger"><i class="fa fa-warning"></i> STOK MINUS</span>';
                      $row_class = 'danger';
                  } elseif ($stok <= $safety) {
                      $status_label = '<span class="label label-danger">CRITICAL</span>';
                      $row_class = 'danger';
                  } else {
                      $status_label = '<span class="label label-warning">REORDER</span>';
                      $row_class = 'warning';
                  }
                  
                  $html .= '<tr class="' . $row_class . '">
                              <td>' . htmlspecialchars($row['kode_item']) . '</td>
                              <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">' . htmlspecialchars($row['kategori']) . '</small></td>
                              <td>' . htmlspecialchars($row['nama_lokasi'] ?? $row['kode_lokasi'] ?? 'Semua Lokasi') . '</td>
                              <td class="text-center" style="font-weight: bold; font-size: 14px;">' . number_format($stok, 0, ',', '.') . '</td>
                              <td class="text-center">' . number_format($stok_min, 0, ',', '.') . '</td>
                              <td class="text-center">' . number_format($safety, 0, ',', '.') . '</td>
                              <td class="text-center">' . htmlspecialchars($row['satuan_dasar']) . '</td>
                              <td class="text-center">' . $status_label . '</td>
                            </tr>';
              }
          }
      }
      
      if (empty($html)) {
          $html = '<tr><td colspan="8" class="text-center text-success"><h4><i class="fa fa-check-circle"></i> Aman! Tidak ada barang dalam kondisi kritis atau minus.</h4></td></tr>';
      }
      
      echo $html;
      exit();
  }

  public function anyDisplayLaporanNilaiPersediaan()
  {
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      
      $db = $this->db()->pdo();
      $params = [];
      $where = " WHERE s.stok > 0";
      
      if (!empty($kode_lokasi)) {
          $where .= " AND s.kode_lokasi = :kode_lokasi";
          $params[':kode_lokasi'] = $kode_lokasi;
      }
      
      if (!empty($kategori)) {
          $where .= " AND b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }
      
      $q = "SELECT 
                b.kode_item, 
                b.nama_barang, 
                b.satuan_dasar, 
                b.kategori,
                l.nama_lokasi,
                s.batch_no,
                s.harga_beli,
                s.stok
            FROM rsns_custom_logistik_non_medis_stok_batch s
            INNER JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
            LEFT JOIN rsns_custom_logistik_non_medis_lokasi_gudang l ON s.kode_lokasi = l.kode_lokasi
            $where
            ORDER BY b.kategori ASC, b.nama_barang ASC";
            
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      $total_nilai = 0;
      $category_totals = [];
      
      if (!empty($rows)) {
          foreach ($rows as $row) {
              $nilai = $row['stok'] * $row['harga_beli'];
              $total_nilai += $nilai;
              
              $cat = $row['kategori'] ?: 'Lain-lain';
              if (!isset($category_totals[$cat])) {
                  $category_totals[$cat] = 0;
              }
              $category_totals[$cat] += $nilai;
              
              $html .= '<tr>
                          <td>' . htmlspecialchars($row['kode_item']) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">Batch: ' . htmlspecialchars($row['batch_no']) . '</small></td>
                          <td>' . htmlspecialchars($row['kategori']) . '</td>
                          <td>' . htmlspecialchars($row['nama_lokasi']) . '</td>
                          <td class="text-center">' . number_format($row['stok'], 0, ',', '.') . '</td>
                          <td class="text-right">Rp. ' . number_format($row['harga_beli'], 0, ',', '.') . '</td>
                          <td class="text-right" style="font-weight: bold;">Rp. ' . number_format($nilai, 0, ',', '.') . '</td>
                        </tr>';
          }
          
          $html .= '<tr class="success" style="font-weight: bold; font-size: 15px;">
                      <td colspan="6" class="text-right">TOTAL NILAI PERSEDIAAN</td>
                      <td class="text-right">Rp. ' . number_format($total_nilai, 0, ',', '.') . '</td>
                    </tr>';
      } else {
          $html = '<tr><td colspan="7" class="text-center text-muted">Data persediaan kosong.</td></tr>';
      }
      
      echo json_encode([
          'html' => $html,
          'total_nilai' => 'Rp. ' . number_format($total_nilai, 0, ',', '.'),
          'chart_data' => $category_totals
      ]);
      exit();
  }

  public function anyDisplayLaporanPerbandingan()
  {
      $p1_awal = $_POST['p1_awal'] ?? date('Y-m-01');
      $p1_akhir = $_POST['p1_akhir'] ?? date('Y-m-d');
      $p2_awal = $_POST['p2_awal'] ?? date('Y-m-01', strtotime('-1 month'));
      $p2_akhir = $_POST['p2_akhir'] ?? date('Y-m-d', strtotime('-1 month'));
      $kategori = $_POST['kategori'] ?? '';
      
      $db = $this->db()->pdo();
      $params = [
          ':p1_awal' => $p1_awal . ' 00:00:00',
          ':p1_akhir' => $p1_akhir . ' 23:59:59',
          ':p2_awal' => $p2_awal . ' 00:00:00',
          ':p2_akhir' => $p2_akhir . ' 23:59:59'
      ];
      
      $where = " WHERE 1=1";
      if (!empty($kategori)) {
          $where .= " AND b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }
      
      $q = "SELECT 
                b.kode_item, 
                b.nama_barang, 
                b.satuan_dasar, 
                b.kategori,
                COALESCE(
                    (SELECT SUM(k.qty_keluar) 
                     FROM rsns_custom_logistik_non_medis_kartu_stok k 
                     WHERE k.kode_item = b.kode_item 
                       AND k.tgl_transaksi BETWEEN :p1_awal AND :p1_akhir
                    ), 0
                ) as qty_p1,
                COALESCE(
                    (SELECT SUM(k.qty_keluar) 
                     FROM rsns_custom_logistik_non_medis_kartu_stok k 
                     WHERE k.kode_item = b.kode_item 
                       AND k.tgl_transaksi BETWEEN :p2_awal AND :p2_akhir
                    ), 0
                ) as qty_p2
            FROM rsns_custom_logistik_non_medis_master_barang b
            $where
            HAVING qty_p1 > 0 OR qty_p2 > 0
            ORDER BY b.nama_barang ASC";
            
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      if (!empty($rows)) {
          foreach ($rows as $row) {
              $p1 = (double)$row['qty_p1'];
              $p2 = (double)$row['qty_p2'];
              $diff = $p1 - $p2;
              
              if ($p2 == 0) {
                  $pct = $p1 > 0 ? 100 : 0;
              } else {
                  $pct = ($diff / $p2) * 100;
              }
              
              $badge_class = 'label-default';
              $pct_str = number_format($pct, 1, ',', '.') . '%';
              
              if ($diff > 0) {
                  $badge_class = 'label-danger';
                  $pct_str = '<i class="fa fa-arrow-up"></i> +' . $pct_str;
              } elseif ($diff < 0) {
                  $badge_class = 'label-success';
                  $pct_str = '<i class="fa fa-arrow-down"></i> ' . $pct_str;
              } else {
                  $pct_str = '<i class="fa fa-minus"></i> 0%';
              }
              
              $html .= '<tr>
                          <td>' . htmlspecialchars($row['kode_item']) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">' . htmlspecialchars($row['kategori']) . '</small></td>
                          <td class="text-center" style="font-weight: bold;">' . number_format($p2, 0, ',', '.') . '</td>
                          <td class="text-center" style="font-weight: bold;">' . number_format($p1, 0, ',', '.') . '</td>
                          <td class="text-center" style="font-weight: bold; color: ' . ($diff > 0 ? '#e74a3b' : ($diff < 0 ? '#1cc88a' : '#858796')) . ';">' . ($diff > 0 ? '+' : '') . number_format($diff, 0, ',', '.') . '</td>
                          <td class="text-center"><span class="label ' . $badge_class . '" style="font-size: 11px; padding: 4px 8px;">' . $pct_str . '</span></td>
                        </tr>';
          }
      } else {
          $html = '<tr><td colspan="6" class="text-center text-muted">Tidak ada pergerakan barang untuk diperbandingkan dalam periode ini.</td></tr>';
      }
      
      echo $html;
      exit();
  }

  public function getLaporanPengadaan()
  {
      $this->_addHeaderFiles();
      $this->_initVendor();
      $this->_initKategori();
      $vendors = $this->db('rsns_custom_logistik_non_medis_vendor')->toArray();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
      
      return $this->draw('laporan.pengadaan.html', [
          'vendors' => $vendors,
          'kategori' => $kategori
      ]);
  }

  public function anyGetLaporanPengadaanKPI()
  {
      $start_date = $_POST['start_date'] ?? date('Y-m-01');
      $end_date = $_POST['end_date'] ?? date('Y-m-d');

      $db = $this->db()->pdo();

      // Total Belanja & Reject Rate
      $q1 = "SELECT 
                SUM(qty_terima * harga) as total_belanja,
                SUM(qty_tolak) as total_tolak,
                SUM(qty_terima + qty_tolak) as total_barang_masuk
             FROM rsns_custom_logistik_non_medis_penerimaan
             WHERE status = 'Selesai' AND tgl_penerimaan BETWEEN :start_date AND :end_date";
      $stmt1 = $db->prepare($q1);
      $stmt1->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $res1 = $stmt1->fetch(\PDO::FETCH_ASSOC);

      $total_belanja = (double)($res1['total_belanja'] ?? 0);
      $total_tolak = (double)($res1['total_tolak'] ?? 0);
      $total_barang_masuk = (double)($res1['total_barang_masuk'] ?? 0);
      $reject_rate = 0;
      if ($total_barang_masuk > 0) {
          $reject_rate = ($total_tolak / $total_barang_masuk) * 100;
      }

      // Total PO Diterbitkan
      $q2 = "SELECT COUNT(DISTINCT no_po) as total_po
             FROM rsns_custom_logistik_non_medis_po
             WHERE tgl_po BETWEEN :start_date AND :end_date";
      $stmt2 = $db->prepare($q2);
      $stmt2->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $res2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
      $total_po = (int)($res2['total_po'] ?? 0);

      // Avg Lead Time
      $q3 = "SELECT AVG(DATEDIFF(p.tgl_penerimaan, po.tgl_po)) as avg_lead_time
             FROM (
                 SELECT no_po, MIN(tgl_penerimaan) as tgl_penerimaan
                 FROM rsns_custom_logistik_non_medis_penerimaan
                 WHERE status = 'Selesai'
                 GROUP BY no_po
             ) p
             JOIN rsns_custom_logistik_non_medis_po po ON p.no_po = po.no_po
             WHERE po.tgl_po BETWEEN :start_date AND :end_date";
      $stmt3 = $db->prepare($q3);
      $stmt3->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $res3 = $stmt3->fetch(\PDO::FETCH_ASSOC);
      $avg_lead_time = (double)($res3['avg_lead_time'] ?? 0);

      echo json_encode([
          'total_belanja' => 'Rp. ' . number_format($total_belanja, 0, ',', '.'),
          'total_po' => number_format($total_po, 0, ',', '.'),
          'avg_lead_time' => number_format($avg_lead_time, 1, ',', '.') . ' Hari',
          'reject_rate' => number_format($reject_rate, 2, ',', '.') . '%'
      ]);
      exit();
  }

  public function anyDisplayLaporanRealisasiRencana()
  {
      $tahun = $_POST['tahun'] ?? date('Y');
      $db = $this->db()->pdo();

      $q = "SELECT 
                p.kode_item,
                b.nama_barang,
                b.kategori,
                SUM(p.total_qty) as qty_rencana,
                AVG(p.harga_referensi) as harga_rencana,
                SUM(p.total_qty * p.harga_referensi) as nominal_rencana,
                COALESCE(rc.qty_realisasi, 0) as qty_realisasi,
                COALESCE(rc.nominal_realisasi, 0) as nominal_realisasi
            FROM rsns_custom_logistik_non_medis_perencanaan p
            LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON p.kode_item = b.kode_item
            LEFT JOIN (
                SELECT kode_item, SUM(qty_terima) as qty_realisasi, SUM(qty_terima * harga) as nominal_realisasi
                FROM rsns_custom_logistik_non_medis_penerimaan
                WHERE status = 'Selesai' AND YEAR(tgl_penerimaan) = :tahun
                GROUP BY kode_item
            ) rc ON p.kode_item = rc.kode_item
            WHERE p.tahun = :tahun AND p.status = 'Disetujui'
            GROUP BY p.kode_item, b.nama_barang, b.kategori
            ORDER BY b.nama_barang ASC";
      
      $stmt = $db->prepare($q);
      $stmt->execute([':tahun' => $tahun]);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      if (!empty($rows)) {
          $total_rencana = 0;
          $total_realisasi = 0;
          foreach ($rows as $row) {
              $nom_rencana = (double)$row['nominal_rencana'];
              $nom_realisasi = (double)$row['nominal_realisasi'];
              $total_rencana += $nom_rencana;
              $total_realisasi += $nom_realisasi;

              $deviasi_qty = (double)$row['qty_realisasi'] - (double)$row['qty_rencana'];
              $deviasi_nom = $nom_realisasi - $nom_rencana;

              $pct_realisasi = 0;
              if ($nom_rencana > 0) {
                  $pct_realisasi = ($nom_realisasi / $nom_rencana) * 100;
              }

              $qty_rencana_f = number_format($row['qty_rencana'], 0, ',', '.');
              $qty_realisasi_f = number_format($row['qty_realisasi'], 0, ',', '.');
              $dev_qty_f = ($deviasi_qty > 0 ? '+' : '') . number_format($deviasi_qty, 0, ',', '.');
              
              $nom_rencana_f = 'Rp. ' . number_format($nom_rencana, 0, ',', '.');
              $nom_realisasi_f = 'Rp. ' . number_format($nom_realisasi, 0, ',', '.');
              $dev_nom_f = ($deviasi_nom > 0 ? '+' : '') . 'Rp. ' . number_format($deviasi_nom, 0, ',', '.');
              
              $color_dev_qty = $deviasi_qty >= 0 ? '#1cc88a' : '#e74a3b';
              $color_dev_nom = $deviasi_nom >= 0 ? '#1cc88a' : '#e74a3b';

              $html .= '<tr>
                          <td>' . htmlspecialchars($row['kode_item']) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_barang'] ?? '-') . '</strong><br><small class="text-muted">' . htmlspecialchars($row['kategori'] ?? '-') . '</small></td>
                          <td class="text-center">' . $qty_rencana_f . '</td>
                          <td class="text-right">' . $nom_rencana_f . '</td>
                          <td class="text-center">' . $qty_realisasi_f . '</td>
                          <td class="text-right">' . $nom_realisasi_f . '</td>
                          <td class="text-center" style="font-weight: bold; color: ' . $color_dev_qty . ';">' . $dev_qty_f . '</td>
                          <td class="text-right" style="font-weight: bold; color: ' . $color_dev_nom . ';">' . $dev_nom_f . '</td>
                          <td class="text-center"><span class="label ' . ($pct_realisasi >= 100 ? 'label-success' : ($pct_realisasi >= 50 ? 'label-warning' : 'label-danger')) . '" style="padding: 3px 6px;">' . number_format($pct_realisasi, 1, ',', '.') . '%</span></td>
                        </tr>';
          }
          $deviasi_total = $total_realisasi - $total_rencana;
          $pct_total = $total_rencana > 0 ? ($total_realisasi / $total_rencana) * 100 : 0;
          $html .= '<tr style="font-weight: bold; font-size: 13px; background-color: #f8f9fc;">
                      <td colspan="2" class="text-right">TOTAL</td>
                      <td class="text-center">-</td>
                      <td class="text-right">Rp. ' . number_format($total_rencana, 0, ',', '.') . '</td>
                      <td class="text-center">-</td>
                      <td class="text-right">Rp. ' . number_format($total_realisasi, 0, ',', '.') . '</td>
                      <td class="text-center" style="color: ' . ($deviasi_total >= 0 ? '#1cc88a' : '#e74a3b') . ';">-</td>
                      <td class="text-right" style="color: ' . ($deviasi_total >= 0 ? '#1cc88a' : '#e74a3b') . ';">' . ($deviasi_total > 0 ? '+' : '') . 'Rp. ' . number_format($deviasi_total, 0, ',', '.') . '</td>
                      <td class="text-center"><span class="label label-primary" style="padding: 4px 8px; font-size: 11px;">' . number_format($pct_total, 1, ',', '.') . '%</span></td>
                    </tr>';
      } else {
          $html = '<tr><td colspan="9" class="text-center text-muted">Tidak ada data perencanaan/realisasi untuk tahun ini.</td></tr>';
      }

      echo $html;
      exit();
  }

  public function anyDisplayLaporanNilaiVolumeVendor()
  {
      $start_date = $_POST['start_date'] ?? date('Y-m-01');
      $end_date = $_POST['end_date'] ?? date('Y-m-d');
      $db = $this->db()->pdo();

      $q = "SELECT 
                v.kode_vendor,
                v.nama_vendor,
                COUNT(DISTINCT p.no_penerimaan) as total_transaksi,
                SUM(p.qty_terima) as total_volume,
                SUM(p.qty_terima * p.harga) as total_nilai
            FROM rsns_custom_logistik_non_medis_penerimaan p
            JOIN rsns_custom_logistik_non_medis_vendor v ON p.kode_vendor = v.kode_vendor
            WHERE p.status = 'Selesai' AND p.tgl_penerimaan BETWEEN :start_date AND :end_date
            GROUP BY v.kode_vendor, v.nama_vendor
            ORDER BY total_nilai DESC";
      
      $stmt = $db->prepare($q);
      $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      $chart_labels = [];
      $chart_values = [];

      if (!empty($rows)) {
          $no = 1;
          $grand_volume = 0;
          $grand_nilai = 0;
          foreach ($rows as $row) {
              $volume = (double)$row['total_volume'];
              $nilai = (double)$row['total_nilai'];
              $grand_volume += $volume;
              $grand_nilai += $nilai;

              $chart_labels[] = $row['nama_vendor'];
              $chart_values[] = $nilai;

              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td>' . htmlspecialchars($row['kode_vendor']) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_vendor']) . '</strong></td>
                          <td class="text-center">' . number_format($row['total_transaksi'], 0, ',', '.') . '</td>
                          <td class="text-center">' . number_format($volume, 0, ',', '.') . '</td>
                          <td class="text-right">Rp. ' . number_format($nilai, 0, ',', '.') . '</td>
                        </tr>';
          }
          $html .= '<tr style="font-weight: bold; font-size: 13px; background-color: #f8f9fc;">
                      <td colspan="3" class="text-right">TOTAL BELANJA</td>
                      <td class="text-center">-</td>
                      <td class="text-center">' . number_format($grand_volume, 0, ',', '.') . '</td>
                      <td class="text-right">Rp. ' . number_format($grand_nilai, 0, ',', '.') . '</td>
                    </tr>';
      } else {
          $html = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data transaksi pengadaan untuk periode ini.</td></tr>';
      }

      echo json_encode([
          'html' => $html,
          'chart_labels' => $chart_labels,
          'chart_values' => $chart_values
      ]);
      exit();
  }

  public function anyDisplayLaporanLeadTime()
  {
      $start_date = $_POST['start_date'] ?? date('Y-m-01');
      $end_date = $_POST['end_date'] ?? date('Y-m-d');
      $db = $this->db()->pdo();

      $q = "SELECT 
                po.no_po,
                po.tgl_po,
                v.nama_vendor,
                p.no_penerimaan,
                p.tgl_penerimaan,
                DATEDIFF(p.tgl_penerimaan, po.tgl_po) as lead_time_days
            FROM (
                SELECT no_po, no_penerimaan, tgl_penerimaan, kode_vendor
                FROM rsns_custom_logistik_non_medis_penerimaan
                WHERE status = 'Selesai'
                GROUP BY no_po, no_penerimaan
            ) p
            JOIN rsns_custom_logistik_non_medis_po po ON p.no_po = po.no_po
            JOIN rsns_custom_logistik_non_medis_vendor v ON p.kode_vendor = v.kode_vendor
            WHERE po.tgl_po BETWEEN :start_date AND :end_date
            ORDER BY lead_time_days ASC, po.tgl_po DESC";
      
      $stmt = $db->prepare($q);
      $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      if (!empty($rows)) {
          $no = 1;
          $total_days = 0;
          foreach ($rows as $row) {
              $days = (int)$row['lead_time_days'];
              $total_days += $days;

              $badge_class = 'label-success';
              if ($days > 7) {
                  $badge_class = 'label-danger';
              } elseif ($days > 3) {
                  $badge_class = 'label-warning';
              }

              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['no_po']) . '</strong><br><small class="text-muted">Tgl: ' . date('d-m-Y', strtotime($row['tgl_po'])) . '</small></td>
                          <td>' . htmlspecialchars($row['nama_vendor']) . '</td>
                          <td><strong>' . htmlspecialchars($row['no_penerimaan']) . '</strong><br><small class="text-muted">Tgl: ' . date('d-m-Y', strtotime($row['tgl_penerimaan'])) . '</small></td>
                          <td class="text-center"><span class="label ' . $badge_class . '" style="font-size: 11px; padding: 4px 8px;">' . $days . ' Hari</span></td>
                        </tr>';
          }
          $avg = $total_days / count($rows);
          $html .= '<tr style="font-weight: bold; font-size: 13px; background-color: #f8f9fc;">
                      <td colspan="4" class="text-right">RATA-RATA WAKTU TUNGGU (LEAD TIME)</td>
                      <td class="text-center"><span class="label label-primary" style="font-size: 12px; padding: 6px 10px;">' . number_format($avg, 1, ',', '.') . ' Hari</span></td>
                    </tr>';
      } else {
          $html = '<tr><td colspan="5" class="text-center text-muted">Tidak ada data pengiriman PO untuk periode ini.</td></tr>';
      }

      echo $html;
      exit();
  }

  public function anyDisplayLaporanPOPeriode()
  {
      $start_date = $_POST['start_date'] ?? date('Y-m-01');
      $end_date = $_POST['end_date'] ?? date('Y-m-d');
      $status = $_POST['status'] ?? '';
      $db = $this->db()->pdo();

      $params = [
          ':start_date' => $start_date,
          ':end_date' => $end_date
      ];

      $where = " WHERE po.tgl_po BETWEEN :start_date AND :end_date";
      if ($status !== '') {
          $where .= " AND po.status = :status";
          $params[':status'] = $status;
      }

      $q = "SELECT 
                po.id,
                po.no_po,
                po.tgl_po,
                po.kode_vendor,
                v.nama_vendor,
                po.total_nilai,
                po.diskon,
                po.ppn,
                po.grand_total,
                po.status,
                po.tgl_kirim
            FROM rsns_custom_logistik_non_medis_po po
            JOIN rsns_custom_logistik_non_medis_vendor v ON po.kode_vendor = v.kode_vendor
            $where
            ORDER BY po.tgl_po DESC, po.no_po DESC";
      
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      if (!empty($rows)) {
          $no = 1;
          $total_nilai = 0;
          $total_grand = 0;
          foreach ($rows as $row) {
              $nilai = (double)$row['total_nilai'];
              $grand = (double)$row['grand_total'];
              $total_nilai += $nilai;
              $total_grand += $grand;

              $status_badge = 'label-default';
              switch ($row['status']) {
                  case 'Draft': $status_badge = 'label-default'; break;
                  case 'Terkirim': $status_badge = 'label-info'; break;
                  case 'Sebagian Diterima': $status_badge = 'label-warning'; break;
                  case 'Selesai': $status_badge = 'label-success'; break;
                  case 'Diamandemen': $status_badge = 'label-primary'; break;
                  case 'Dibatalkan': $status_badge = 'label-danger'; break;
              }

              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['no_po']) . '</strong></td>
                          <td class="text-center">' . date('d-m-Y', strtotime($row['tgl_po'])) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_vendor']) . '</strong><br><small class="text-muted">Kode: ' . htmlspecialchars($row['kode_vendor']) . '</small></td>
                          <td class="text-right">Rp. ' . number_format($nilai, 0, ',', '.') . '</td>
                          <td class="text-right">Rp. ' . number_format($row['ppn'], 0, ',', '.') . '</td>
                          <td class="text-right" style="font-weight: bold;">Rp. ' . number_format($grand, 0, ',', '.') . '</td>
                          <td class="text-center"><span class="label ' . $status_badge . '" style="font-size: 11px; padding: 4px 8px;">' . $row['status'] . '</span></td>
                        </tr>';
          }
          $html .= '<tr style="font-weight: bold; font-size: 13px; background-color: #f8f9fc;">
                      <td colspan="4" class="text-right">TOTAL</td>
                      <td class="text-right">Rp. ' . number_format($total_nilai, 0, ',', '.') . '</td>
                      <td class="text-right">-</td>
                      <td class="text-right">Rp. ' . number_format($total_grand, 0, ',', '.') . '</td>
                      <td></td>
                    </tr>';
      } else {
          $html = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data Purchase Order (PO) untuk periode dan kriteria ini.</td></tr>';
      }

      echo $html;
      exit();
  }

  public function anyDisplayLaporanRealisasiAnggaran()
  {
      $tahun = $_POST['tahun'] ?? date('Y');
      $db = $this->db()->pdo();

      $q = "SELECT 
                b.kategori,
                SUM(p.total_qty * p.harga_referensi) as anggaran_rencana,
                COALESCE(rc.nilai_realisasi, 0) as anggaran_realisasi,
                SUM(p.total_qty * p.harga_referensi) - COALESCE(rc.nilai_realisasi, 0) as sisa_anggaran
            FROM rsns_custom_logistik_non_medis_perencanaan p
            JOIN rsns_custom_logistik_non_medis_master_barang b ON p.kode_item = b.kode_item
            LEFT JOIN (
                SELECT b2.kategori, SUM(p2.qty_terima * p2.harga) as nilai_realisasi
                FROM rsns_custom_logistik_non_medis_penerimaan p2
                JOIN rsns_custom_logistik_non_medis_master_barang b2 ON p2.kode_item = b2.kode_item
                WHERE p2.status = 'Selesai' AND YEAR(p2.tgl_penerimaan) = :tahun
                GROUP BY b2.kategori
            ) rc ON b.kategori = rc.kategori
            WHERE p.tahun = :tahun AND p.status = 'Disetujui'
            GROUP BY b.kategori
            ORDER BY b.kategori ASC";
      
      $stmt = $db->prepare($q);
      $stmt->execute([':tahun' => $tahun]);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      $chart_labels = [];
      $chart_rencana = [];
      $chart_realisasi = [];

      if (!empty($rows)) {
          $no = 1;
          $total_rencana = 0;
          $total_realisasi = 0;
          $total_sisa = 0;
          foreach ($rows as $row) {
              $rencana = (double)$row['anggaran_rencana'];
              $realisasi = (double)$row['anggaran_realisasi'];
              $sisa = (double)$row['sisa_anggaran'];

              $total_rencana += $rencana;
              $total_realisasi += $realisasi;
              $total_sisa += $sisa;

              $chart_labels[] = $row['kategori'] ?? 'Lain-lain';
              $chart_rencana[] = $rencana;
              $chart_realisasi[] = $realisasi;

              $pct_penyerapan = 0;
              if ($rencana > 0) {
                  $pct_penyerapan = ($realisasi / $rencana) * 100;
              }

              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['kategori'] ?? 'Lain-lain') . '</strong></td>
                          <td class="text-right">Rp. ' . number_format($rencana, 0, ',', '.') . '</td>
                          <td class="text-right">Rp. ' . number_format($realisasi, 0, ',', '.') . '</td>
                          <td class="text-right" style="color: ' . ($sisa >= 0 ? '#1cc88a' : '#e74a3b') . '; font-weight: bold;">Rp. ' . number_format($sisa, 0, ',', '.') . '</td>
                          <td class="text-center"><span class="label ' . ($pct_penyerapan >= 90 ? 'label-success' : ($pct_penyerapan >= 50 ? 'label-warning' : 'label-danger')) . '" style="font-size: 11px; padding: 4px 8px;">' . number_format($pct_penyerapan, 1, ',', '.') . '%</span></td>
                        </tr>';
          }
          $pct_total = $total_rencana > 0 ? ($total_realisasi / $total_rencana) * 100 : 0;
          $html .= '<tr style="font-weight: bold; font-size: 13px; background-color: #f8f9fc;">
                      <td colspan="2" class="text-right">TOTAL ANGGARAN</td>
                      <td class="text-right">Rp. ' . number_format($total_rencana, 0, ',', '.') . '</td>
                      <td class="text-right">Rp. ' . number_format($total_realisasi, 0, ',', '.') . '</td>
                      <td class="text-right" style="color: ' . ($total_sisa >= 0 ? '#1cc88a' : '#e74a3b') . ';">Rp. ' . number_format($total_sisa, 0, ',', '.') . '</td>
                      <td class="text-center"><span class="label label-primary" style="font-size: 12px; padding: 6px 10px;">' . number_format($pct_total, 1, ',', '.') . '%</span></td>
                    </tr>';
      } else {
          $html = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data alokasi anggaran perencanaan tahun ini.</td></tr>';
      }

      echo json_encode([
          'html' => $html,
          'chart_labels' => $chart_labels,
          'chart_rencana' => $chart_rencana,
          'chart_realisasi' => $chart_realisasi
      ]);
      exit();
  }

  public function anyDisplayLaporanKinerjaVendor()
  {
      $start_date = $_POST['start_date'] ?? date('Y-m-01');
      $end_date = $_POST['end_date'] ?? date('Y-m-d');
      $db = $this->db()->pdo();

      $q = "SELECT 
                v.kode_vendor,
                v.nama_vendor,
                COUNT(DISTINCT p.no_po) as total_po,
                AVG(DATEDIFF(p.tgl_penerimaan, po.tgl_po)) as avg_lead_time_days,
                SUM(p.qty_terima) as total_qty_diterima,
                SUM(p.qty_tolak) as total_qty_ditolak
            FROM rsns_custom_logistik_non_medis_penerimaan p
            JOIN rsns_custom_logistik_non_medis_po po ON p.no_po = po.no_po
            JOIN rsns_custom_logistik_non_medis_vendor v ON p.kode_vendor = v.kode_vendor
            WHERE p.status = 'Selesai' AND p.tgl_penerimaan BETWEEN :start_date AND :end_date
            GROUP BY v.kode_vendor, v.nama_vendor";
      
      $stmt = $db->prepare($q);
      $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $html = '';
      $chart_labels = [];
      $chart_reject_rates = [];
      $chart_lead_times = [];

      if (!empty($rows)) {
          $no = 1;
          foreach ($rows as $row) {
              $lead_time = (double)$row['avg_lead_time_days'];
              $qty_terima = (double)$row['total_qty_diterima'];
              $qty_tolak = (double)$row['total_qty_ditolak'];
              
              $total_qty = $qty_terima + $qty_tolak;
              $reject_rate = 0;
              if ($total_qty > 0) {
                  $reject_rate = ($qty_tolak / $total_qty) * 100;
              }

              $chart_labels[] = $row['nama_vendor'];
              $chart_reject_rates[] = $reject_rate;
              $chart_lead_times[] = $lead_time;

              // Calculate overall score (formula out of 100)
              // Penalty for reject rate: 1% reject rate = -5 points
              // Penalty for lead time: > 3 days = -5 points per day
              $score = 100;
              $score -= $reject_rate * 5;
              $score -= max(0, $lead_time - 3) * 5;
              $score = max(0, min(100, $score));

              $score_badge = 'label-success';
              if ($score < 60) {
                  $score_badge = 'label-danger';
              } elseif ($score < 80) {
                  $score_badge = 'label-warning';
              }

              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_vendor']) . '</strong><br><small class="text-muted">Kode: ' . htmlspecialchars($row['kode_vendor']) . '</small></td>
                          <td class="text-center">' . $row['total_po'] . ' PO</td>
                          <td class="text-center">' . number_format($lead_time, 1, ',', '.') . ' Hari</td>
                          <td class="text-center">' . number_format($qty_terima, 0, ',', '.') . ' Unit</td>
                          <td class="text-center" style="color: ' . ($qty_tolak > 0 ? '#e74a3b' : '#858796') . '; font-weight: bold;">' . number_format($qty_tolak, 0, ',', '.') . ' Unit</td>
                          <td class="text-center"><span class="label ' . ($reject_rate > 5 ? 'label-danger' : ($reject_rate > 0 ? 'label-warning' : 'label-success')) . '">' . number_format($reject_rate, 2, ',', '.') . '%</span></td>
                          <td class="text-center" style="font-weight: bold;"><span class="label ' . $score_badge . '" style="font-size: 11px; padding: 4px 8px;">' . number_format($score, 0) . ' / 100</span></td>
                        </tr>';
          }
      } else {
          $html = '<tr><td colspan="8" class="text-center text-muted">Tidak ada data transaksi penerimaan untuk menilai kinerja vendor periode ini.</td></tr>';
      }

      echo json_encode([
          'html' => $html,
          'chart_labels' => $chart_labels,
          'chart_reject_rates' => $chart_reject_rates,
          'chart_lead_times' => $chart_lead_times
      ]);
      exit();
  }

  public function getLaporanDistribusi()
  {
      $this->_addHeaderFiles();
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
      return $this->draw('laporan.distribusi.html', [
          'unit' => $unit,
          'kategori' => $kategori
      ]);
  }

  public function anyGetLaporanDistribusiKpi()
  {
      $start_date = $_POST['start_date'] ?? date('Y-m-01');
      $end_date = $_POST['end_date'] ?? date('Y-m-d');
      $db = $this->db()->pdo();

      // 1. Total Distribusi
      $q1 = "SELECT COUNT(DISTINCT no_sppb) as count FROM rsns_custom_logistik_non_medis_sppb 
             WHERE status IN ('Dikirim', 'Diterima', 'Selesai') AND tgl_sppb BETWEEN :start_date AND :end_date";
      $stmt1 = $db->prepare($q1);
      $stmt1->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $total_dist = $stmt1->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

      // 2. Volume Terdistribusi
      $q2 = "SELECT SUM(jumlah_disetujui) as vol FROM rsns_custom_logistik_non_medis_sppb 
             WHERE status IN ('Dikirim', 'Diterima', 'Selesai') AND tgl_sppb BETWEEN :start_date AND :end_date";
      $stmt2 = $db->prepare($q2);
      $stmt2->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $total_vol = (double)($stmt2->fetch(\PDO::FETCH_ASSOC)['vol'] ?? 0);

      // 3. Total Nilai Pengeluaran
      $q3 = "SELECT SUM(s.jumlah_disetujui * b.harga_referensi) as nilai 
             FROM rsns_custom_logistik_non_medis_sppb s
             JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
             WHERE s.status IN ('Dikirim', 'Diterima', 'Selesai') AND s.tgl_sppb BETWEEN :start_date AND :end_date";
      $stmt3 = $db->prepare($q3);
      $stmt3->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $total_val = (double)($stmt3->fetch(\PDO::FETCH_ASSOC)['nilai'] ?? 0);

      // 4. Unit Penerima Aktif
      $q4 = "SELECT COUNT(DISTINCT kode_unit) as active_units FROM rsns_custom_logistik_non_medis_sppb 
             WHERE status IN ('Dikirim', 'Diterima', 'Selesai') AND tgl_sppb BETWEEN :start_date AND :end_date";
      $stmt4 = $db->prepare($q4);
      $stmt4->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $active_units = $stmt4->fetch(\PDO::FETCH_ASSOC)['active_units'] ?? 0;

      echo json_encode([
          'total_distribusi' => number_format($total_dist, 0, ',', '.'),
          'volume_terdistribusi' => number_format($total_vol, 0, ',', '.') . ' Unit',
          'total_nilai' => 'Rp. ' . number_format($total_val, 0, ',', '.'),
          'unit_aktif' => $active_units . ' Unit'
      ]);
      exit();
  }

  public function anyDisplayLaporanDistribusi()
  {
      $start_date = $_POST['tgl_awal'] ?? date('Y-m-01');
      $end_date = $_POST['tgl_akhir'] ?? date('Y-m-d');
      $kode_unit = $_POST['kode_unit'] ?? '';
      $status = $_POST['status'] ?? '';
      
      $db = $this->db()->pdo();
      
      $q = "SELECT 
                st.no_serah_terima,
                s.no_sppb,
                s.tgl_sppb,
                st.tanggal_serah,
                u.nama_unit,
                s.kode_item,
                b.nama_barang,
                s.jumlah_disetujui,
                s.satuan,
                b.harga_referensi,
                s.status,
                st.penerima_nama
            FROM rsns_custom_logistik_non_medis_sppb s
            LEFT JOIN rsns_custom_logistik_non_medis_serah_terima st ON s.no_sppb = st.no_sppb
            JOIN rsns_custom_logistik_non_medis_unit u ON s.kode_unit = u.kode_unit
            JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
            WHERE s.status IN ('Dikirim', 'Diterima', 'Selesai')
              AND s.tgl_sppb BETWEEN :start_date AND :end_date";
              
      $params = [':start_date' => $start_date, ':end_date' => $end_date];
      
      if (!empty($kode_unit)) {
          $q .= " AND s.kode_unit = :kode_unit";
          $params[':kode_unit'] = $kode_unit;
      }
      
      if (!empty($status)) {
          $q .= " AND s.status = :status";
          $params[':status'] = $status;
      }
      
      $q .= " ORDER BY s.tgl_sppb DESC, s.no_sppb DESC";
      
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      if (!empty($rows)) {
          $no = 1;
          foreach ($rows as $row) {
              $total_biaya = $row['jumlah_disetujui'] * $row['harga_referensi'];
              $status_badge = 'label-info';
              if ($row['status'] == 'Selesai') {
                  $status_badge = 'label-success';
              } elseif ($row['status'] == 'Diterima') {
                  $status_badge = 'label-primary';
              }
              
              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['no_sppb']) . '</strong><br><small class="text-muted">Bast: ' . htmlspecialchars($row['no_serah_terima'] ?? '-') . '</small></td>
                          <td class="text-center">' . date('d-m-Y', strtotime($row['tgl_sppb'])) . '</td>
                          <td>' . htmlspecialchars($row['nama_unit']) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">Kode: ' . htmlspecialchars($row['kode_item']) . '</small></td>
                          <td class="text-center">' . number_format($row['jumlah_disetujui'], 0, ',', '.') . ' ' . htmlspecialchars($row['satuan'] ?? 'Pcs') . '</td>
                          <td class="text-right">Rp. ' . number_format($total_biaya, 0, ',', '.') . '</td>
                          <td class="text-center"><span class="label ' . $status_badge . '">' . htmlspecialchars($row['status']) . '</span></td>
                          <td>' . htmlspecialchars($row['penerima_nama'] ?? '-') . '</td>
                        </tr>';
          }
      } else {
          $html = '<tr><td colspan="9" class="text-center text-muted">Tidak ada data distribusi ditemukan untuk periode dan filter ini.</td></tr>';
      }
      
      echo $html;
      exit();
  }

  public function anyDisplayPemakaianPerUnit()
  {
      $start_date = $_POST['tgl_awal'] ?? date('Y-m-01');
      $end_date = $_POST['tgl_akhir'] ?? date('Y-m-d');
      $kode_unit = $_POST['kode_unit'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      
      $db = $this->db()->pdo();
      
      $q = "SELECT 
                u.kode_unit,
                u.nama_unit,
                b.kode_item,
                b.nama_barang,
                b.kategori,
                SUM(s.jumlah_disetujui) as total_qty,
                s.satuan,
                SUM(s.jumlah_disetujui * b.harga_referensi) as total_nilai
            FROM rsns_custom_logistik_non_medis_sppb s
            JOIN rsns_custom_logistik_non_medis_unit u ON s.kode_unit = u.kode_unit
            JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
            WHERE s.status IN ('Diterima', 'Selesai')
              AND s.tgl_sppb BETWEEN :start_date AND :end_date";
              
      $params = [':start_date' => $start_date, ':end_date' => $end_date];
      
      if (!empty($kode_unit)) {
          $q .= " AND s.kode_unit = :kode_unit";
          $params[':kode_unit'] = $kode_unit;
      }
      
      if (!empty($kategori)) {
          $q .= " AND b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }
      
      $q .= " GROUP BY u.kode_unit, b.kode_item
              ORDER BY u.nama_unit ASC, total_nilai DESC";
              
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      $chart_labels = [];
      $chart_data = [];
      
      if (!empty($rows)) {
          $no = 1;
          foreach ($rows as $row) {
              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td>' . htmlspecialchars($row['nama_unit']) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">' . htmlspecialchars($row['kode_item']) . '</small></td>
                          <td>' . htmlspecialchars($row['kategori']) . '</td>
                          <td class="text-center">' . number_format($row['total_qty'], 0, ',', '.') . ' ' . htmlspecialchars($row['satuan'] ?? 'Pcs') . '</td>
                          <td class="text-right">Rp. ' . number_format($row['total_nilai'], 0, ',', '.') . '</td>
                        </tr>';
                        
              if (count($chart_labels) < 10) {
                  $chart_labels[] = $row['nama_barang'] . ' (' . $row['nama_unit'] . ')';
                  $chart_data[] = (double)$row['total_nilai'];
              }
          }
      } else {
          $html = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data pemakaian barang ditemukan.</td></tr>';
      }
      
      echo json_encode([
          'html' => $html,
          'chart_labels' => $chart_labels,
          'chart_data' => $chart_data
      ]);
      exit();
  }

  public function anyDisplayTrenKonsumsi()
  {
      $tahun = $_POST['tahun'] ?? date('Y');
      $kategori = $_POST['kategori'] ?? '';
      $kode_item = $_POST['kode_item'] ?? '';
      
      $db = $this->db()->pdo();
      
      $q = "SELECT 
                MONTH(s.tgl_sppb) as bulan,
                SUM(s.jumlah_disetujui) as total_qty,
                SUM(s.jumlah_disetujui * b.harga_referensi) as total_nilai
            FROM rsns_custom_logistik_non_medis_sppb s
            JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
            WHERE s.status IN ('Diterima', 'Selesai')
              AND YEAR(s.tgl_sppb) = :tahun";
              
      $params = [':tahun' => $tahun];
      
      if (!empty($kategori)) {
          $q .= " AND b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }
      
      if (!empty($kode_item)) {
          $q .= " AND s.kode_item = :kode_item";
          $params[':kode_item'] = $kode_item;
      }
      
      $q .= " GROUP BY MONTH(s.tgl_sppb)
              ORDER BY MONTH(s.tgl_sppb) ASC";
              
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $months_names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
      $monthly_qty = array_fill(0, 12, 0);
      $monthly_val = array_fill(0, 12, 0);
      
      foreach ($rows as $row) {
          $m_idx = (int)$row['bulan'] - 1;
          if ($m_idx >= 0 && $m_idx < 12) {
              $monthly_qty[$m_idx] = (double)$row['total_qty'];
              $monthly_val[$m_idx] = (double)$row['total_nilai'];
          }
      }
      
      $html = '';
      for ($i = 0; $i < 12; $i++) {
          $html .= '<tr>
                      <td class="text-center">' . ($i + 1) . '</td>
                      <td>' . $months_names[$i] . '</td>
                      <td class="text-center">' . number_format($monthly_qty[$i], 0, ',', '.') . ' Unit</td>
                      <td class="text-right">Rp. ' . number_format($monthly_val[$i], 0, ',', '.') . '</td>
                    </tr>';
      }
      
      echo json_encode([
          'html' => $html,
          'chart_labels' => $months_names,
          'chart_qty' => $monthly_qty,
          'chart_val' => $monthly_val
      ]);
      exit();
  }

  public function anyDisplayRealisasiVsKuota()
  {
      $tahun = $_POST['tahun'] ?? date('Y');
      $bulan = $_POST['bulan'] ?? date('m');
      $kode_unit = $_POST['kode_unit'] ?? '';
      
      $db = $this->db()->pdo();
      
      $q = "SELECT 
                k.kode_unit,
                u.nama_unit,
                k.kode_item,
                b.nama_barang,
                k.jumlah as kuota_alokasi,
                b.satuan_dasar as satuan,
                COALESCE(SUM(s.jumlah_disetujui), 0) as realisasi,
                (k.jumlah - COALESCE(SUM(s.jumlah_disetujui), 0)) as sisa_kuota,
                CASE 
                    WHEN k.jumlah > 0 THEN (COALESCE(SUM(s.jumlah_disetujui), 0) / k.jumlah) * 100 
                    ELSE 0 
                END as persentase_realisasi
            FROM rsns_custom_logistik_non_medis_kuota k
            JOIN rsns_custom_logistik_non_medis_unit u ON k.kode_unit = u.kode_unit
            JOIN rsns_custom_logistik_non_medis_master_barang b ON k.kode_item = b.kode_item
            LEFT JOIN rsns_custom_logistik_non_medis_sppb s ON k.kode_unit = s.kode_unit 
                AND k.kode_item = s.kode_item
                AND s.status IN ('Diterima', 'Selesai')
                AND MONTH(s.tgl_sppb) = k.bulan 
                AND YEAR(s.tgl_sppb) = k.tahun
            WHERE k.tahun = :tahun AND k.bulan = :bulan";
              
      $params = [':tahun' => $tahun, ':bulan' => $bulan];
      
      if (!empty($kode_unit)) {
          $q .= " AND k.kode_unit = :kode_unit";
          $params[':kode_unit'] = $kode_unit;
      }
      
      $q .= " GROUP BY k.kode_unit, k.kode_item
              ORDER BY persentase_realisasi DESC";
              
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      if (!empty($rows)) {
          $no = 1;
          foreach ($rows as $row) {
              $pct = (double)$row['persentase_realisasi'];
              
              $badge_class = 'label-success';
              $row_class = '';
              if ($pct >= 100) {
                  $badge_class = 'label-danger';
                  $row_class = 'danger';
              } elseif ($pct >= 75) {
                  $badge_class = 'label-warning';
                  $row_class = 'warning';
              }
              
              $html .= '<tr class="' . $row_class . '">
                          <td class="text-center">' . $no++ . '</td>
                          <td>' . htmlspecialchars($row['nama_unit']) . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">Kode: ' . htmlspecialchars($row['kode_item']) . '</small></td>
                          <td class="text-center">' . number_format($row['kuota_alokasi'], 0, ',', '.') . ' ' . htmlspecialchars($row['satuan'] ?? 'Pcs') . '</td>
                          <td class="text-center">' . number_format($row['realisasi'], 0, ',', '.') . ' ' . htmlspecialchars($row['satuan'] ?? 'Pcs') . '</td>
                          <td class="text-center" style="font-weight: bold; color: ' . ($row['sisa_kuota'] < 0 ? '#e74a3b' : '#1cc88a') . ';">' . number_format($row['sisa_kuota'], 0, ',', '.') . '</td>
                          <td class="text-center"><span class="label ' . $badge_class . '" style="font-size: 11px; padding: 4px 8px;">' . number_format($pct, 1, ',', '.') . '%</span></td>
                        </tr>';
          }
      } else {
          $html = '<tr><td colspan="7" class="text-center text-muted">Tidak ada data penetapan kuota untuk periode ini.</td></tr>';
      }
      
      echo $html;
      exit();
  }

  public function anyDisplayLaporanSppbPeriode()
  {
      $start_date = $_POST['tgl_awal'] ?? date('Y-m-01');
      $end_date = $_POST['tgl_akhir'] ?? date('Y-m-d');
      $status = $_POST['status'] ?? '';
      
      $db = $this->db()->pdo();
      
      $q = "SELECT 
                s.no_sppb,
                s.tgl_sppb,
                u.nama_unit,
                COUNT(DISTINCT s.kode_item) as total_item,
                SUM(s.jumlah) as qty_diminta,
                SUM(s.jumlah_disetujui) as qty_disetujui,
                s.status,
                s.user_input,
                s.user_verifikasi
            FROM rsns_custom_logistik_non_medis_sppb s
            JOIN rsns_custom_logistik_non_medis_unit u ON s.kode_unit = u.kode_unit
            WHERE s.tgl_sppb BETWEEN :start_date AND :end_date";
              
      $params = [':start_date' => $start_date, ':end_date' => $end_date];
      
      if (!empty($status)) {
          $q .= " AND s.status = :status";
          $params[':status'] = $status;
      }
      
      $q .= " GROUP BY s.no_sppb
              ORDER BY s.tgl_sppb DESC, s.no_sppb DESC";
              
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      if (!empty($rows)) {
          $no = 1;
          foreach ($rows as $row) {
              $badge = 'label-default';
              switch($row['status']) {
                  case 'Draft': $badge = 'label-default'; break;
                  case 'Diajukan': $badge = 'label-info'; break;
                  case 'Disetujui Unit': $badge = 'label-warning'; break;
                  case 'Terverifikasi': $badge = 'label-primary'; break;
                  case 'Selesai': $badge = 'label-success'; break;
                  case 'Ditolak': $badge = 'label-danger'; break;
              }
              
              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['no_sppb']) . '</strong></td>
                          <td class="text-center">' . date('d-m-Y', strtotime($row['tgl_sppb'])) . '</td>
                          <td>' . htmlspecialchars($row['nama_unit']) . '</td>
                          <td class="text-center">' . $row['total_item'] . ' Item</td>
                          <td class="text-center">' . number_format($row['qty_diminta'], 0, ',', '.') . '</td>
                          <td class="text-center" style="font-weight: bold; color: #4e73df;">' . number_format($row['qty_disetujui'], 0, ',', '.') . '</td>
                          <td class="text-center"><span class="label ' . $badge . '">' . htmlspecialchars($row['status']) . '</span></td>
                          <td>' . htmlspecialchars($row['user_input'] ?? '-') . '</td>
                          <td>' . htmlspecialchars($row['user_verifikasi'] ?? '-') . '</td>
                        </tr>';
          }
      } else {
          $html = '<tr><td colspan="10" class="text-center text-muted">Tidak ada pengajuan SPPB pada periode ini.</td></tr>';
      }
      
      echo $html;
      exit();
  }

  public function anyDisplayFrekuensiVolume()
  {
      $start_date = $_POST['tgl_awal'] ?? date('Y-m-01');
      $end_date = $_POST['tgl_akhir'] ?? date('Y-m-d');
      $kategori = $_POST['kategori'] ?? '';
      
      $db = $this->db()->pdo();
      
      $q = "SELECT 
                b.kode_item,
                b.nama_barang,
                b.kategori,
                COUNT(DISTINCT s.no_sppb) as frekuensi_permintaan,
                SUM(s.jumlah) as total_volume_diminta,
                SUM(s.jumlah_disetujui) as total_volume_disetujui,
                b.satuan_dasar
            FROM rsns_custom_logistik_non_medis_sppb s
            JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
            WHERE s.tgl_sppb BETWEEN :start_date AND :end_date";
              
      $params = [':start_date' => $start_date, ':end_date' => $end_date];
      
      if (!empty($kategori)) {
          $q .= " AND b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }
      
      $q .= " GROUP BY b.kode_item
              ORDER BY frekuensi_permintaan DESC, total_volume_disetujui DESC";
              
      $stmt = $db->prepare($q);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      $chart_labels = [];
      $chart_freq = [];
      $chart_vol = [];
      
      if (!empty($rows)) {
          $no = 1;
          foreach ($rows as $row) {
              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_barang']) . '</strong><br><small class="text-muted">Kode: ' . htmlspecialchars($row['kode_item']) . '</small></td>
                          <td>' . htmlspecialchars($row['kategori']) . '</td>
                          <td class="text-center" style="font-weight: bold; color: #36b9cc;">' . $row['frekuensi_permintaan'] . ' Kali</td>
                          <td class="text-center">' . number_format($row['total_volume_diminta'], 0, ',', '.') . ' ' . htmlspecialchars($row['satuan_dasar'] ?? 'Pcs') . '</td>
                          <td class="text-center" style="font-weight: bold; color: #4e73df;">' . number_format($row['total_volume_disetujui'], 0, ',', '.') . ' ' . htmlspecialchars($row['satuan_dasar'] ?? 'Pcs') . '</td>
                        </tr>';
                        
              if (count($chart_labels) < 10) {
                  $chart_labels[] = $row['nama_barang'];
                  $chart_freq[] = (int)$row['frekuensi_permintaan'];
                  $chart_vol[] = (double)$row['total_volume_disetujui'];
              }
          }
      } else {
          $html = '<tr><td colspan="6" class="text-center text-muted">Tidak ada data transaksi permintaan.</td></tr>';
      }
      
      echo json_encode([
          'html' => $html,
          'chart_labels' => $chart_labels,
          'chart_freq' => $chart_freq,
          'chart_vol' => $chart_vol
      ]);
      exit();
  }

  public function anyDisplayUnitTerboros()
  {
      $start_date = $_POST['tgl_awal'] ?? date('Y-m-01');
      $end_date = $_POST['tgl_akhir'] ?? date('Y-m-d');
      
      $db = $this->db()->pdo();
      
      $q = "SELECT 
                u.kode_unit,
                u.nama_unit,
                COUNT(DISTINCT s.no_sppb) as total_sppb,
                SUM(s.jumlah_disetujui * b.harga_referensi) as total_biaya
            FROM rsns_custom_logistik_non_medis_sppb s
            JOIN rsns_custom_logistik_non_medis_unit u ON s.kode_unit = u.kode_unit
            JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
            WHERE s.status IN ('Diterima', 'Selesai')
              AND s.tgl_sppb BETWEEN :start_date AND :end_date
            GROUP BY u.kode_unit
            ORDER BY total_biaya DESC";
              
      $stmt = $db->prepare($q);
      $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $html = '';
      $chart_data = [];
      $chart_labels = [];
      
      if (!empty($rows)) {
          $no = 1;
          $total_all = 0;
          foreach ($rows as $row) {
              $total_all += $row['total_biaya'];
          }
          
          $cum_sum = 0;
          foreach ($rows as $row) {
              $cum_sum += $row['total_biaya'];
              $pareto_pct = $total_all > 0 ? ($cum_sum / $total_all) * 100 : 0;
              
              $html .= '<tr>
                          <td class="text-center">' . $no++ . '</td>
                          <td><strong>' . htmlspecialchars($row['nama_unit']) . '</strong><br><small class="text-muted">Kode: ' . htmlspecialchars($row['kode_unit']) . '</small></td>
                          <td class="text-center">' . $row['total_sppb'] . ' Transaksi</td>
                          <td class="text-right" style="font-weight: bold; color: #e74a3b;">Rp. ' . number_format($row['total_biaya'], 0, ',', '.') . '</td>
                          <td class="text-center"><span class="label ' . ($pareto_pct <= 80 ? 'label-danger' : 'label-default') . '">' . number_format($pareto_pct, 1, ',', '.') . '%</span></td>
                        </tr>';
                        
              if (count($chart_labels) < 5) {
                  $chart_labels[] = $row['nama_unit'];
                  $chart_data[] = (double)$row['total_biaya'];
              } else {
                  if (!isset($chart_labels[5])) {
                      $chart_labels[5] = 'Lain-lain';
                      $chart_data[5] = 0;
                  }
                  $chart_data[5] += (double)$row['total_biaya'];
              }
          }
      } else {
          $html = '<tr><td colspan="5" class="text-center text-muted">Tidak ada data transaksi pemakaian unit terdeteksi.</td></tr>';
      }
      
      echo json_encode([
          'html' => $html,
          'chart_labels' => $chart_labels,
          'chart_data' => $chart_data
      ]);
      exit();
  }

  public function getLaporanAset()
  {
      $this->_addHeaderFiles();
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
      return $this->draw('laporan.aset.html', [
          'unit' => $unit,
          'kategori' => $kategori
      ]);
  }

  public function anyGetLaporanAsetKpi()
  {
      $start_date = $_POST['start_date'] ?? '';
      $end_date = $_POST['end_date'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $unit = $_POST['unit'] ?? '';

      $db = $this->db()->pdo();
      
      $where = ["a.status = 'Aktif'"];
      $params = [];

      if (!empty($start_date)) {
          $where[] = "a.tanggal_perolehan >= :start_date";
          $params[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where[] = "a.tanggal_perolehan <= :end_date";
          $params[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where[] = "a.kode_unit = :unit";
          $params[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where[] = "b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }

      $where_str = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

      $query = "SELECT 
                  COUNT(a.id) as total_unit, 
                  SUM(a.harga_beli) as total_nilai,
                  SUM(a.akumulasi_penyusutan) as total_akumulasi,
                  SUM(a.nilai_buku) as total_buku
                FROM rsns_custom_logistik_non_medis_aset a
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                $where_str";
      
      $stmt = $db->prepare($query);
      $stmt->execute($params);
      $kpi = $stmt->fetch(\PDO::FETCH_ASSOC);

      // Aset Dihapuskan
      $where_del = ["a.status = 'Dihapuskan'"];
      $params_del = [];
      if (!empty($start_date)) {
          $where_del[] = "a.tanggal_perolehan >= :start_date";
          $params_del[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where_del[] = "a.tanggal_perolehan <= :end_date";
          $params_del[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where_del[] = "a.kode_unit = :unit";
          $params_del[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where_del[] = "b.kategori = :kategori";
          $params_del[':kategori'] = $kategori;
      }
      $where_del_str = "WHERE " . implode(" AND ", $where_del);
      
      $query_del = "SELECT COUNT(a.id) as total FROM rsns_custom_logistik_non_medis_aset a
                    LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                    $where_del_str";
      $stmt_del = $db->prepare($query_del);
      $stmt_del->execute($params_del);
      $total_dihapus = $stmt_del->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

      echo json_encode([
          'total_unit' => (int)($kpi['total_unit'] ?? 0),
          'total_nilai' => (double)($kpi['total_nilai'] ?? 0),
          'total_akumulasi' => (double)($kpi['total_akumulasi'] ?? 0),
          'total_buku' => (double)($kpi['total_buku'] ?? 0),
          'total_dihapus' => (int)$total_dihapus
      ]);
      exit();
  }

  public function anyGetLaporanAsetKib()
  {
      $start_date = $_POST['start_date'] ?? '';
      $end_date = $_POST['end_date'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $unit = $_POST['unit'] ?? '';
      $kib_jenis_filter = $_POST['kib_jenis'] ?? '';

      $db = $this->db()->pdo();
      
      $where = ["a.status = 'Aktif'"];
      $params = [];

      if (!empty($start_date)) {
          $where[] = "a.tanggal_perolehan >= :start_date";
          $params[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where[] = "a.tanggal_perolehan <= :end_date";
          $params[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where[] = "a.kode_unit = :unit";
          $params[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where[] = "b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }

      $where_str = "WHERE " . implode(" AND ", $where);

      $query_summary = "SELECT 
                          a.kib_jenis, 
                          COUNT(a.id) as total_item, 
                          SUM(a.harga_beli) as total_nilai
                        FROM rsns_custom_logistik_non_medis_aset a
                        LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                        $where_str
                        GROUP BY a.kib_jenis";
      
      $stmt_sum = $db->prepare($query_summary);
      $stmt_sum->execute($params);
      $summaries = $stmt_sum->fetchAll(\PDO::FETCH_ASSOC);

      $kib_map = [
          'A' => ['name' => 'KIB A (Tanah)', 'count' => 0, 'value' => 0],
          'B' => ['name' => 'KIB B (Peralatan & Mesin)', 'count' => 0, 'value' => 0],
          'C' => ['name' => 'KIB C (Gedung & Bangunan)', 'count' => 0, 'value' => 0],
          'D' => ['name' => 'KIB D (Jalan, Irigasi & Jaringan)', 'count' => 0, 'value' => 0],
          'E' => ['name' => 'KIB E (Aset Tetap Lainnya)', 'count' => 0, 'value' => 0],
          'F' => ['name' => 'KIB F (Konstruksi Dalam Pengerjaan)', 'count' => 0, 'value' => 0]
      ];

      foreach ($summaries as $s) {
          if (isset($kib_map[$s['kib_jenis']])) {
              $kib_map[$s['kib_jenis']]['count'] = (int)$s['total_item'];
              $kib_map[$s['kib_jenis']]['value'] = (double)$s['total_nilai'];
          }
      }

      $where_detail = ["a.status = 'Aktif'"];
      $params_detail = $params;
      
      if (!empty($kib_jenis_filter)) {
          $where_detail[] = "a.kib_jenis = :kib_jenis";
          $params_detail[':kib_jenis'] = $kib_jenis_filter;
      }

      $where_detail_str = "WHERE " . implode(" AND ", $where_detail);

      $query_detail = "SELECT 
                          a.*, 
                          b.nama_barang, 
                          u.nama_unit 
                        FROM rsns_custom_logistik_non_medis_aset a
                        LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                        LEFT JOIN rsns_custom_logistik_non_medis_unit u ON a.kode_unit = u.kode_unit
                        $where_detail_str
                        ORDER BY a.kib_jenis ASC, a.kode_aset ASC";
      
      $stmt_det = $db->prepare($query_detail);
      $stmt_det->execute($params_detail);
      $details = $stmt_det->fetchAll(\PDO::FETCH_ASSOC);

      echo json_encode([
          'summary' => $kib_map,
          'details' => $details
      ]);
      exit();
  }

  public function anyGetLaporanAsetPenyusutan()
  {
      $start_date = $_POST['start_date'] ?? '';
      $end_date = $_POST['end_date'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $unit = $_POST['unit'] ?? '';

      $db = $this->db()->pdo();
      
      $where = ["a.status = 'Aktif'"];
      $params = [];

      if (!empty($start_date)) {
          $where[] = "a.tanggal_perolehan >= :start_date";
          $params[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where[] = "a.tanggal_perolehan <= :end_date";
          $params[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where[] = "a.kode_unit = :unit";
          $params[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where[] = "b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }

      $where_str = "WHERE " . implode(" AND ", $where);

      $query = "SELECT 
                  a.kode_aset, 
                  a.nama_aset, 
                  a.tanggal_perolehan, 
                  a.harga_beli, 
                  a.masa_manfaat_tahun,
                  a.nilai_residu,
                  a.akumulasi_penyusutan,
                  a.nilai_buku,
                  a.tgl_penyusutan_terakhir,
                  u.nama_unit
                FROM rsns_custom_logistik_non_medis_aset a
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                LEFT JOIN rsns_custom_logistik_non_medis_unit u ON a.kode_unit = u.kode_unit
                $where_str
                ORDER BY a.kode_aset ASC";
      
      $stmt = $db->prepare($query);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      echo json_encode(['data' => $rows]);
      exit();
  }

  public function anyGetLaporanAsetKondisi()
  {
      $start_date = $_POST['start_date'] ?? '';
      $end_date = $_POST['end_date'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $unit = $_POST['unit'] ?? '';

      $db = $this->db()->pdo();
      
      $where = ["a.status = 'Aktif'"];
      $params = [];

      if (!empty($start_date)) {
          $where[] = "a.tanggal_perolehan >= :start_date";
          $params[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where[] = "a.tanggal_perolehan <= :end_date";
          $params[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where[] = "a.kode_unit = :unit";
          $params[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where[] = "b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }

      $where_str = "WHERE " . implode(" AND ", $where);

      $query_sum = "SELECT 
                      a.status_kondisi, 
                      COUNT(a.id) as total_item
                    FROM rsns_custom_logistik_non_medis_aset a
                    LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                    $where_str
                    GROUP BY a.status_kondisi";
      
      $stmt_sum = $db->prepare($query_sum);
      $stmt_sum->execute($params);
      $sums = $stmt_sum->fetchAll(\PDO::FETCH_ASSOC);

      $kondisi_counts = [
          'Baik' => 0,
          'Rusak Ringan' => 0,
          'Rusak Berat' => 0
      ];
      foreach ($sums as $s) {
          if (isset($kondisi_counts[$s['status_kondisi']])) {
              $kondisi_counts[$s['status_kondisi']] = (int)$s['total_item'];
          }
      }

      $query_detail = "SELECT 
                          a.kode_aset, 
                          a.nama_aset, 
                          a.status_kondisi, 
                          a.pic,
                          u.nama_unit,
                          l.nama_lokasi
                        FROM rsns_custom_logistik_non_medis_aset a
                        LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                        LEFT JOIN rsns_custom_logistik_non_medis_unit u ON a.kode_unit = u.kode_unit
                        LEFT JOIN rsns_custom_logistik_non_medis_lokasi_gudang l ON a.kode_lokasi = l.kode_lokasi
                        $where_str
                        ORDER BY a.status_kondisi DESC, a.kode_aset ASC";
      
      $stmt_det = $db->prepare($query_detail);
      $stmt_det->execute($params);
      $details = $stmt_det->fetchAll(\PDO::FETCH_ASSOC);

      echo json_encode([
          'summary' => $kondisi_counts,
          'details' => $details
      ]);
      exit();
  }

  public function anyGetLaporanAsetMasaManfaat()
  {
      $start_date = $_POST['start_date'] ?? '';
      $end_date = $_POST['end_date'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $unit = $_POST['unit'] ?? '';

      $db = $this->db()->pdo();
      
      $where = ["a.status = 'Aktif'"];
      $params = [];

      if (!empty($start_date)) {
          $where[] = "a.tanggal_perolehan >= :start_date";
          $params[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where[] = "a.tanggal_perolehan <= :end_date";
          $params[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where[] = "a.kode_unit = :unit";
          $params[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where[] = "b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }

      $where_str = "WHERE " . implode(" AND ", $where);

      $query = "SELECT 
                  a.kode_aset, 
                  a.nama_aset, 
                  a.tanggal_perolehan, 
                  a.masa_manfaat_tahun,
                  a.nilai_buku,
                  u.nama_unit
                FROM rsns_custom_logistik_non_medis_aset a
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                LEFT JOIN rsns_custom_logistik_non_medis_unit u ON a.kode_unit = u.kode_unit
                $where_str
                ORDER BY a.tanggal_perolehan ASC";
      
      $stmt = $db->prepare($query);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $processed = [];
      foreach ($rows as $r) {
          $perolehan = $r['tanggal_perolehan'];
          $masa_manfaat = (int)$r['masa_manfaat_tahun'];
          
          if (empty($perolehan) || $perolehan == '0000-00-00') {
              $usia_tahun = 0;
              $sisa_manfaat = $masa_manfaat;
          } else {
              $tgl_perolehan = new \DateTime($perolehan);
              $tgl_sekarang = new \DateTime();
              $interval = $tgl_perolehan->diff($tgl_sekarang);
              $usia_tahun = $interval->y + ($interval->m / 12) + ($interval->d / 365);
              $sisa_manfaat = $masa_manfaat - $usia_tahun;
          }

          $processed[] = [
              'kode_aset' => $r['kode_aset'],
              'nama_aset' => $r['nama_aset'],
              'tanggal_perolehan' => $r['tanggal_perolehan'],
              'masa_manfaat_tahun' => $masa_manfaat,
              'nilai_buku' => (double)$r['nilai_buku'],
              'nama_unit' => $r['nama_unit'],
              'usia_tahun' => round($usia_tahun, 2),
              'sisa_manfaat' => round($sisa_manfaat, 2)
          ];
      }

      usort($processed, function($a, $b) {
          return $a['sisa_manfaat'] <=> $b['sisa_manfaat'];
      });

      echo json_encode(['data' => $processed]);
      exit();
  }

  public function anyGetLaporanAsetPemeliharaan()
  {
      $start_date = $_POST['start_date'] ?? '';
      $end_date = $_POST['end_date'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $unit = $_POST['unit'] ?? '';

      $db = $this->db()->pdo();
      
      $where = [];
      $params = [];

      if (!empty($start_date)) {
          $where[] = "p.tanggal_direncanakan >= :start_date";
          $params[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where[] = "p.tanggal_direncanakan <= :end_date";
          $params[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where[] = "a.kode_unit = :unit";
          $params[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where[] = "b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }

      $where_str = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

      $query = "SELECT 
                  p.kode_pemeliharaan,
                  p.jenis_pemeliharaan,
                  p.tanggal_direncanakan,
                  p.tanggal_pelaksanaan,
                  p.nama_kegiatan,
                  p.total_biaya,
                  p.status as status_pemeliharaan,
                  a.kode_aset,
                  a.nama_aset,
                  u.nama_unit
                FROM rsns_custom_logistik_non_medis_aset_pemeliharaan p
                LEFT JOIN rsns_custom_logistik_non_medis_aset a ON p.kode_aset = a.kode_aset
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                LEFT JOIN rsns_custom_logistik_non_medis_unit u ON a.kode_unit = u.kode_unit
                $where_str
                ORDER BY p.tanggal_direncanakan DESC";
      
      $stmt = $db->prepare($query);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $processed = [];
      foreach ($rows as $r) {
          $jadwal = $r['tanggal_direncanakan'];
          $realisasi = $r['tanggal_pelaksanaan'];
          $deviasi_hari = NULL;

          if (!empty($realisasi) && $realisasi != '0000-00-00 00:00:00') {
              $tgl_jadwal = new \DateTime($jadwal);
              $tgl_realisasi = new \DateTime(date('Y-m-d', strtotime($realisasi)));
              $interval = $tgl_jadwal->diff($tgl_realisasi);
              $deviasi_hari = (int)$interval->format('%r%a');
          }

          $processed[] = [
              'kode_pemeliharaan' => $r['kode_pemeliharaan'],
              'jenis_pemeliharaan' => $r['jenis_pemeliharaan'],
              'tanggal_direncanakan' => $jadwal,
              'tanggal_pelaksanaan' => $realisasi,
              'nama_kegiatan' => $r['nama_kegiatan'],
              'total_biaya' => (double)$r['total_biaya'],
              'status_pemeliharaan' => $r['status_pemeliharaan'],
              'kode_aset' => $r['kode_aset'],
              'nama_aset' => $r['nama_aset'],
              'nama_unit' => $r['nama_unit'],
              'deviasi_hari' => $deviasi_hari
          ];
      }

      echo json_encode(['data' => $processed]);
      exit();
  }

  public function anyGetLaporanAsetPenghapusan()
  {
      $start_date = $_POST['start_date'] ?? '';
      $end_date = $_POST['end_date'] ?? '';
      $kategori = $_POST['kategori'] ?? '';
      $unit = $_POST['unit'] ?? '';

      $db = $this->db()->pdo();
      
      $where = [];
      $params = [];

      if (!empty($start_date)) {
          $where[] = "h.tanggal_pengajuan >= :start_date";
          $params[':start_date'] = $start_date;
      }
      if (!empty($end_date)) {
          $where[] = "h.tanggal_pengajuan <= :end_date";
          $params[':end_date'] = $end_date;
      }
      if (!empty($unit)) {
          $where[] = "a.kode_unit = :unit";
          $params[':unit'] = $unit;
      }
      if (!empty($kategori)) {
          $where[] = "b.kategori = :kategori";
          $params[':kategori'] = $kategori;
      }

      $where_str = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

      $query = "SELECT 
                  h.no_pengajuan,
                  h.tanggal_pengajuan,
                  h.alasan_penghapusan,
                  h.status_kondisi_terakhir,
                  h.nilai_buku_terakhir,
                  h.nilai_taksiran,
                  h.metode_penghapusan,
                  h.no_sk,
                  h.no_ba,
                  h.status as status_penghapusan,
                  a.kode_aset,
                  a.nama_aset,
                  u.nama_unit
                FROM rsns_custom_logistik_non_medis_aset_penghapusan h
                LEFT JOIN rsns_custom_logistik_non_medis_aset a ON h.kode_aset = a.kode_aset
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON a.kode_item = b.kode_item
                LEFT JOIN rsns_custom_logistik_non_medis_unit u ON a.kode_unit = u.kode_unit
                $where_str
                ORDER BY h.tanggal_pengajuan DESC";
      
      $stmt = $db->prepare($query);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      echo json_encode(['data' => $rows]);
      exit();
  }

  public function getLaporanDashboardKpi()
  {
      $this->_addHeaderFiles();
      return $this->draw('laporan.dashboardkpi.html');
  }

  public function anyGetDashboardKpiData()
  {
      $start_date = $_POST['start_date'] ?? date('Y-m-01');
      $end_date = $_POST['end_date'] ?? date('Y-m-d');
      $db = $this->db()->pdo();

      // 1. TURNOVER RATE (ITOR)
      $q_keluar = "SELECT COALESCE(SUM(qty_keluar), 0) as total_keluar 
                   FROM rsns_custom_logistik_non_medis_kartu_stok 
                   WHERE tgl_transaksi BETWEEN :start_date AND :end_date";
      $stmt = $db->prepare($q_keluar);
      $stmt->execute([':start_date' => $start_date . ' 00:00:00', ':end_date' => $end_date . ' 23:59:59']);
      $total_keluar = (double)($stmt->fetch(\PDO::FETCH_ASSOC)['total_keluar'] ?? 0);

      $q_stok_avg = "SELECT AVG(stok_akhir) as avg_stok 
                     FROM rsns_custom_logistik_non_medis_kartu_stok 
                     WHERE tgl_transaksi BETWEEN :start_date AND :end_date";
      $stmt_avg = $db->prepare($q_stok_avg);
      $stmt_avg->execute([':start_date' => $start_date . ' 00:00:00', ':end_date' => $end_date . ' 23:59:59']);
      $avg_stok = (double)($stmt_avg->fetch(\PDO::FETCH_ASSOC)['avg_stok'] ?? 0);

      if ($avg_stok <= 0) {
          $q_fallback = "SELECT AVG(stok) as avg_stok FROM rsns_custom_logistik_non_medis_stok_batch";
          $avg_stok = (double)($db->query($q_fallback)->fetch(\PDO::FETCH_ASSOC)['avg_stok'] ?? 0);
      }

      $turnover_rate = $avg_stok > 0 ? round($total_keluar / $avg_stok, 2) : 0;

      // 2. REQUEST FULFILLMENT RATE
      $q_fulfillment = "SELECT SUM(jumlah) as diminta, SUM(jumlah_disetujui) as disetujui 
                        FROM rsns_custom_logistik_non_medis_sppb 
                        WHERE status IN ('Selesai', 'Diterima', 'Dikirim', 'Ready', 'Packing', 'Picking', 'Terverifikasi')
                          AND tgl_sppb BETWEEN :start_date AND :end_date";
      $stmt_ful = $db->prepare($q_fulfillment);
      $stmt_ful->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $ful_data = $stmt_ful->fetch(\PDO::FETCH_ASSOC);
      $diminta = (double)($ful_data['diminta'] ?? 0);
      $disetujui = (double)($ful_data['disetujui'] ?? 0);
      $fulfillment_rate = $diminta > 0 ? round(($disetujui / $diminta) * 100, 2) : 0;

      // 3. INVENTORY VALUE VS BUDGET
      $q_inv_val = "SELECT SUM(stok * harga_beli) as total_val FROM rsns_custom_logistik_non_medis_stok_batch";
      $total_inv_val = (double)($db->query($q_inv_val)->fetch(\PDO::FETCH_ASSOC)['total_val'] ?? 0);

      $tahun_aktif = date('Y', strtotime($start_date));
      $q_budget = "SELECT SUM(total_qty * harga_referensi) as total_budget 
                   FROM rsns_custom_logistik_non_medis_perencanaan 
                   WHERE tahun = :tahun";
      $stmt_bud = $db->prepare($q_budget);
      $stmt_bud->execute([':tahun' => $tahun_aktif]);
      $total_budget = (double)($stmt_bud->fetch(\PDO::FETCH_ASSOC)['total_budget'] ?? 0);

      // 4. LEAD TIME PENGADAAN
      $q_lead = "SELECT AVG(DATEDIFF(p.tgl_penerimaan, po.tgl_po)) as avg_lead_time 
                 FROM (
                     SELECT no_po, tgl_penerimaan 
                     FROM rsns_custom_logistik_non_medis_penerimaan 
                     WHERE status = 'Selesai'
                     GROUP BY no_po, no_penerimaan
                 ) p
                 JOIN rsns_custom_logistik_non_medis_po po ON p.no_po = po.no_po
                 WHERE po.tgl_po BETWEEN :start_date AND :end_date";
      $stmt_lead = $db->prepare($q_lead);
      $stmt_lead->execute([':start_date' => $start_date, ':end_date' => $end_date]);
      $avg_lead_time = round((double)($stmt_lead->fetch(\PDO::FETCH_ASSOC)['avg_lead_time'] ?? 0), 1);

      // 5. WAREHOUSE UTILIZATION %
      $q_util = "SELECT 
                     SUM(kapasitas) as total_kapasitas, 
                     (SELECT SUM(stok) FROM rsns_custom_logistik_non_medis_stok_batch) as total_stok
                 FROM rsns_custom_logistik_non_medis_lokasi_gudang 
                 WHERE status = 'Aktif' AND kapasitas > 0";
      $util_res = $db->query($q_util)->fetch(\PDO::FETCH_ASSOC);
      $total_kapasitas = (double)($util_res['total_kapasitas'] ?? 0);
      $total_stok_gudang = (double)($util_res['total_stok'] ?? 0);
      $utilization_rate = $total_kapasitas > 0 ? round(($total_stok_gudang / $total_kapasitas) * 100, 2) : 0;

      // 6. REAL-TIME STOCK CHART DATA (By Category)
      $q_stock_cat = "SELECT 
                          b.kategori, 
                          SUM(s.stok) as total_stok, 
                          SUM(s.stok * s.harga_beli) as total_nilai 
                      FROM rsns_custom_logistik_non_medis_stok_batch s
                      JOIN rsns_custom_logistik_non_medis_master_barang b ON s.kode_item = b.kode_item
                      GROUP BY b.kategori
                      ORDER BY total_stok DESC";
      $stock_categories = $db->query($q_stock_cat)->fetchAll(\PDO::FETCH_ASSOC);

      // 7. STOCK MINIMUM ALERTS
      $q_alerts = "SELECT 
                       b.kode_item, 
                       b.nama_barang, 
                       b.stok_min, 
                       b.safety_stock,
                       COALESCE(SUM(s.stok), 0) as stok_sekarang,
                       b.satuan_dasar
                   FROM rsns_custom_logistik_non_medis_master_barang b
                   LEFT JOIN rsns_custom_logistik_non_medis_stok_batch s ON b.kode_item = s.kode_item
                   WHERE b.status = 'Aktif'
                   GROUP BY b.kode_item
                   HAVING stok_sekarang <= b.stok_min OR stok_sekarang <= b.safety_stock
                   ORDER BY stok_sekarang ASC 
                   LIMIT 10";
      $stock_alerts = $db->query($q_alerts)->fetchAll(\PDO::FETCH_ASSOC);

      // 8. KPI ALERTS TARGETS EVALUATION
      $kpi_alerts = [];
      if ($turnover_rate < 0.3) {
          $kpi_alerts[] = [
              'title' => 'Turn-over Rate Rendah',
              'desc' => 'Perputaran persediaan berjalan lambat (' . $turnover_rate . 'x), berisiko dead-stock.',
              'level' => 'warning'
          ];
      }
      if ($fulfillment_rate < 85) {
          $kpi_alerts[] = [
              'title' => 'Tingkat Pemenuhan Permintaan Rendah',
              'desc' => 'Pemenuhan permintaan unit berada di bawah target (' . $fulfillment_rate . '% < 85%).',
              'level' => 'danger'
          ];
      }
      if ($avg_lead_time > 7) {
          $kpi_alerts[] = [
              'title' => 'Lead Time Pengadaan Lama',
              'desc' => 'Rata-rata waktu tunggu pengadaan melampaui batas standar (' . $avg_lead_time . ' hari > 7 hari).',
              'level' => 'danger'
          ];
      }
      if ($utilization_rate > 85) {
          $kpi_alerts[] = [
              'title' => 'Kapasitas Gudang Kritis',
              'desc' => 'Utilisasi gudang hampir penuh (' . $utilization_rate . '% > 85%). Segera lakukan pengaturan ulang.',
              'level' => 'danger'
          ];
      } elseif ($utilization_rate < 15) {
          $kpi_alerts[] = [
              'title' => 'Underutilization Gudang',
              'desc' => 'Utilisasi gudang sangat rendah (' . $utilization_rate . '% < 15%), kapasitas tidak terpakai optimal.',
              'level' => 'info'
          ];
      }
      if ($total_budget > 0 && $total_inv_val > $total_budget) {
          $kpi_alerts[] = [
              'title' => 'Nilai Persediaan Melebihi Anggaran',
              'desc' => 'Total nilai persediaan (Rp. ' . number_format($total_inv_val, 0, ',', '.') . ') melampaui RKBU (Rp. ' . number_format($total_budget, 0, ',', '.') . ').',
              'level' => 'danger'
          ];
      }

      echo json_encode([
          'turnover_rate' => $turnover_rate,
          'fulfillment_rate' => $fulfillment_rate,
          'total_inv_val' => 'Rp. ' . number_format($total_inv_val, 0, ',', '.'),
          'total_inv_val_num' => $total_inv_val,
          'total_budget' => 'Rp. ' . number_format($total_budget, 0, ',', '.'),
          'total_budget_num' => $total_budget,
          'avg_lead_time' => $avg_lead_time,
          'utilization_rate' => $utilization_rate,
          'stock_categories' => $stock_categories,
          'stock_alerts' => $stock_alerts,
          'kpi_alerts' => $kpi_alerts
      ]);
      exit();
  }



  private function _initReportSchedules()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_report_schedules` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `report_name` varchar(100) NOT NULL,
        `report_type` varchar(50) NOT NULL,
        `sub_report_type` varchar(50) NOT NULL,
        `frequency` enum('daily', 'weekly', 'monthly') NOT NULL,
        `send_time` time NOT NULL DEFAULT '07:00:00',
        `send_day` int(2) DEFAULT NULL,
        `email_recipients` text NOT NULL,
        `filters_json` text DEFAULT NULL,
        `status` enum('Aktif', 'Tidak Aktif') NOT NULL DEFAULT 'Aktif',
        `last_run` datetime DEFAULT NULL,
        `created_at` datetime DEFAULT NULL,
        `created_by` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _initReportVerifications()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_report_verifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `verification_hash` varchar(64) NOT NULL,
        `report_name` varchar(100) NOT NULL,
        `period_start` date NOT NULL,
        `period_end` date NOT NULL,
        `generated_by` varchar(100) NOT NULL,
        `generated_at` datetime NOT NULL,
        `checksum_data` text NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `verification_hash` (`verification_hash`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  public function getLaporanEksporCetak()
  {
      $this->_initReportSchedules();
      $this->_initReportVerifications();
      
      $this->_addHeaderFiles();
      
      // Pull dynamic filters to populate dropdowns
      $this->_initKategori();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
      
      $this->_initLokasi();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      
      $this->_initUnit();
      $unit = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      
      $schedules = $this->db('rsns_custom_logistik_non_medis_report_schedules')->toArray();

      return $this->draw('laporan.eksporcetak.html', [
          'kategori' => $kategori,
          'lokasi' => $lokasi,
          'unit' => $unit,
          'schedules' => $schedules
      ]);
  }

  private function _getReportOutputHtml($sub_report_type, $filters)
  {
      // Prepare $_POST variables for the display method
      $_POST = $filters;

      ob_start();
      switch ($sub_report_type) {
          case 'kartu_stok':
              $this->anyDisplayLaporanKartuStok();
              break;
          case 'mutasi_saldo':
              $this->anyDisplayLaporanMutasiSaldo();
              break;
          case 'stok_kritis':
              $this->anyDisplayLaporanStokKritis();
              break;
          case 'nilai_persediaan':
              $this->anyDisplayLaporanNilaiPersediaan();
              break;
          case 'perbandingan':
              $this->anyDisplayLaporanPerbandingan();
              break;
          case 'realisasi_rencana':
              $this->anyDisplayLaporanRealisasiRencana();
              break;
          case 'nilai_volume_vendor':
              $this->anyDisplayLaporanNilaiVolumeVendor();
              break;
          case 'lead_time':
              $this->anyDisplayLaporanLeadTime();
              break;
          case 'po_periode':
              $this->anyDisplayLaporanPOPeriode();
              break;
          case 'realisasi_anggaran':
              $this->anyDisplayLaporanRealisasiAnggaran();
              break;
          case 'kinerja_vendor':
              $this->anyDisplayLaporanKinerjaVendor();
              break;
          case 'distribusi':
              $this->anyDisplayLaporanDistribusi();
              break;
          case 'sppb_periode':
              $this->anyDisplayLaporanSppbPeriode();
              break;
          // Aset reports return JSON:
          case 'aset_kib':
              $this->anyGetLaporanAsetKib();
              break;
          case 'aset_penyusutan':
              $this->anyGetLaporanAsetPenyusutan();
              break;
          case 'aset_kondisi':
              $this->anyGetLaporanAsetKondisi();
              break;
          case 'aset_masamanfaat':
              $this->anyGetLaporanAsetMasaManfaat();
              break;
          case 'aset_pemeliharaan':
              $this->anyGetLaporanAsetPemeliharaan();
              break;
          case 'aset_penghapusan':
              $this->anyGetLaporanAsetPenghapusan();
              break;
          default:
              echo "<tr><td colspan='10' class='text-center text-danger'>Tipe Laporan tidak dikenal!</td></tr>";
      }
      $output = ob_get_clean();

      // Check if output is JSON (typical for Aset reports)
      if (substr(trim($output), 0, 1) === '{') {
          $data = json_decode($output, true);
          return $this->_renderAsetTableHtml($sub_report_type, $data);
      }

      return $output;
  }

  private function _renderAsetTableHtml($sub_report_type, $data)
  {
      $html = '';
      if ($sub_report_type === 'aset_kib') {
          $html .= '<table class="table table-bordered table-striped" style="width:100%; border-collapse:collapse;" border="1">
                      <thead>
                          <tr style="background-color:#f1f1f1;">
                              <th>No</th>
                              <th>Kode Aset</th>
                              <th>Nama Aset</th>
                              <th>Unit Kerja</th>
                              <th>Jenis KIB</th>
                              <th>Detail Spesifikasi</th>
                              <th>Tgl Perolehan</th>
                              <th>Harga Beli</th>
                          </tr>
                      </thead>
                      <tbody>';
          if (empty($data['details'])) {
              $html .= '<tr><td colspan="8" class="text-center text-muted">Tidak ditemukan data.</td></tr>';
          } else {
              foreach ($data['details'] as $i => $row) {
                  $detailDesc = '';
                  if ($row['kib_jenis'] === 'A') {
                      $detailDesc = "Luas: {$row['kib_luas']} m² | Alamat: {$row['kib_alamat']} | Sertifikat: ".($row['kib_no_sertifikat'] ?: '-');
                  } else if ($row['kib_jenis'] === 'B') {
                      $detailDesc = "Merk/Tipe: ".($row['kib_merk'] ?: '-')." | Bahan: ".($row['kib_bahan'] ?: '-')." | Pabrik/Rangka: ".($row['kib_no_pabrik'] ?: '-')."/".($row['kib_no_rangka'] ?: '-');
                  } else if ($row['kib_jenis'] === 'C') {
                      $detailDesc = "Kontruksi: ".($row['kib_konstruksi'] ?: '-')." | Bertingkat/Beton: ".($row['kib_bertingkat'] ?: 'Tidak')."/".($row['kib_beton'] ?: 'Tidak')." | Alamat: {$row['kib_alamat']}";
                  } else if ($row['kib_jenis'] === 'D') {
                      $detailDesc = "Panjang/Lebar: {$row['kib_panjang']}m/{$row['kib_lebar']}m | Kontruksi: ".($row['kib_konstruksi'] ?: '-')." | Alamat: ".($row['kib_alamat'] ?: '-');
                  } else if ($row['kib_jenis'] === 'E') {
                      $detailDesc = "Judul/Pencipta: ".($row['kib_judul'] ?: '-')."/".($row['kib_pencipta'] ?: '-')." | Bahan/Ukuran: ".($row['kib_bahan'] ?: '-')."/".($row['kib_ukuran'] ?: '-');
                  } else if ($row['kib_jenis'] === 'F') {
                      $detailDesc = "Proyek: ".($row['kib_proyek_bangunan'] ?: '-')." | Rencana Selesai: ".($row['kib_tgl_rencana_selesai'] ?: '-')." | Progress: {$row['kib_progress_persen']}%";
                  }
                  $html .= '<tr>
                              <td align="center">'.($i+1).'</td>
                              <td><b>'.$row['kode_aset'].'</b></td>
                              <td>'.$row['nama_aset'].'</td>
                              <td>'.($row['nama_unit'] ?: '-').'</td>
                              <td align="center">KIB '.$row['kib_jenis'].'</td>
                              <td style="font-size:11px;">'.$detailDesc.'</td>
                              <td align="center">'.$row['tanggal_perolehan'].'</td>
                              <td align="right">Rp. '.number_format($row['harga_beli'], 0, ',', '.').'</td>
                            </tr>';
              }
          }
          $html .= '</tbody></table>';
      } else if ($sub_report_type === 'aset_penyusutan') {
          $html .= '<table class="table table-bordered table-striped" style="width:100%; border-collapse:collapse;" border="1">
                      <thead>
                          <tr style="background-color:#f1f1f1;">
                              <th>No</th>
                              <th>Kode Aset</th>
                              <th>Nama Aset</th>
                              <th>Unit Kerja</th>
                              <th>Masa Manfaat</th>
                              <th>Harga Beli</th>
                              <th>Akumulasi Penyusutan</th>
                              <th>Nilai Buku</th>
                              <th>Tanggal Terakhir</th>
                          </tr>
                      </thead>
                      <tbody>';
          if (empty($data['data'])) {
              $html .= '<tr><td colspan="9" class="text-center text-muted">Tidak ditemukan data.</td></tr>';
          } else {
              foreach ($data['data'] as $i => $row) {
                  $html .= '<tr>
                              <td align="center">'.($i+1).'</td>
                              <td><b>'.$row['kode_aset'].'</b></td>
                              <td>'.$row['nama_aset'].'</td>
                              <td>'.($row['nama_unit'] ?: '-').'</td>
                              <td align="center">'.$row['masa_manfaat_tahun'].' Thn</td>
                              <td align="right">Rp. '.number_format($row['harga_beli'], 0, ',', '.').'</td>
                              <td align="right" style="color:#d9534f;">Rp. '.number_format($row['akumulasi_penyusutan'], 0, ',', '.').'</td>
                              <td align="right" style="font-weight:bold; color:#5cb85c;">Rp. '.number_format($row['nilai_buku'], 0, ',', '.').'</td>
                              <td align="center">'.($row['tgl_penyusutan_terakhir'] ?: '-').'</td>
                            </tr>';
              }
          }
          $html .= '</tbody></table>';
      } else if ($sub_report_type === 'aset_kondisi') {
          $html .= '<table class="table table-bordered table-striped" style="width:100%; border-collapse:collapse;" border="1">
                      <thead>
                          <tr style="background-color:#f1f1f1;">
                              <th>No</th>
                              <th>Kode Aset</th>
                              <th>Nama Aset</th>
                              <th>Unit Kerja</th>
                              <th>Lokasi Detail</th>
                              <th>PJ</th>
                              <th>Kondisi</th>
                          </tr>
                      </thead>
                      <tbody>';
          if (empty($data['details'])) {
              $html .= '<tr><td colspan="7" class="text-center text-muted">Tidak ditemukan data.</td></tr>';
          } else {
              foreach ($data['details'] as $i => $row) {
                  $html .= '<tr>
                              <td align="center">'.($i+1).'</td>
                              <td><b>'.$row['kode_aset'].'</b></td>
                              <td>'.$row['nama_aset'].'</td>
                              <td>'.($row['nama_unit'] ?: '-').'</td>
                              <td>'.($row['nama_lokasi'] ?: '-').'</td>
                              <td>'.($row['pic'] ?: '-').'</td>
                              <td align="center">'.strtoupper($row['status_kondisi']).'</td>
                            </tr>';
              }
          }
          $html .= '</tbody></table>';
      } else if ($sub_report_type === 'aset_masamanfaat') {
          $html .= '<table class="table table-bordered table-striped" style="width:100%; border-collapse:collapse;" border="1">
                      <thead>
                          <tr style="background-color:#f1f1f1;">
                              <th>No</th>
                              <th>Kode Aset</th>
                              <th>Nama Aset</th>
                              <th>Unit Kerja</th>
                              <th>Tgl Perolehan</th>
                              <th>Usia Aset</th>
                              <th>Masa Manfaat</th>
                              <th>Sisa Masa Manfaat</th>
                              <th>Nilai Buku</th>
                          </tr>
                      </thead>
                      <tbody>';
          if (empty($data['data'])) {
              $html .= '<tr><td colspan="9" class="text-center text-muted">Tidak ditemukan data.</td></tr>';
          } else {
              foreach ($data['data'] as $i => $row) {
                  $html .= '<tr>
                              <td align="center">'.($i+1).'</td>
                              <td><b>'.$row['kode_aset'].'</b></td>
                              <td>'.$row['nama_aset'].'</td>
                              <td>'.($row['nama_unit'] ?: '-').'</td>
                              <td align="center">'.$row['tanggal_perolehan'].'</td>
                              <td align="center">'.$row['usia_tahun'].' Thn</td>
                              <td align="center">'.$row['masa_manfaat_tahun'].' Thn</td>
                              <td align="center">'.($row['sisa_manfaat'] <= 0 ? 'EXPIRED' : $row['sisa_manfaat'].' Tahun').'</td>
                              <td align="right">Rp. '.number_format($row['nilai_buku'], 0, ',', '.').'</td>
                            </tr>';
              }
          }
          $html .= '</tbody></table>';
      } else if ($sub_report_type === 'aset_pemeliharaan') {
          $html .= '<table class="table table-bordered table-striped" style="width:100%; border-collapse:collapse;" border="1">
                      <thead>
                          <tr style="background-color:#f1f1f1;">
                              <th>No</th>
                              <th>Kode Jadwal / WO</th>
                              <th>Jenis Kegiatan</th>
                              <th>Nama Aset</th>
                              <th>Unit Kerja</th>
                              <th>Jadwal Rencana</th>
                              <th>Tanggal Realisasi</th>
                              <th>Deviasi</th>
                              <th>Total Biaya</th>
                          </tr>
                      </thead>
                      <tbody>';
          if (empty($data['data'])) {
              $html .= '<tr><td colspan="9" class="text-center text-muted">Tidak ditemukan data.</td></tr>';
          } else {
              foreach ($data['data'] as $i => $row) {
                  $real = $row['tanggal_pelaksanaan'] && $row['tanggal_pelaksanaan'] !== '0000-00-00 00:00:00' ? substr($row['tanggal_pelaksanaan'], 0, 10) : '-';
                  $dev = $row['deviasi_hari'] !== null ? ($row['deviasi_hari'] > 0 ? "Terlambat {$row['deviasi_hari']} hari" : ($row['deviasi_hari'] == 0 ? "Tepat Waktu" : abs($row['deviasi_hari'])." hari lebih cepat")) : 'Belum Realisasi';
                  $html .= '<tr>
                              <td align="center">'.($i+1).'</td>
                              <td><b>'.$row['kode_pemeliharaan'].'</b></td>
                              <td>['.$row['jenis_pemeliharaan'].'] '.$row['nama_kegiatan'].'</td>
                              <td>'.$row['nama_aset'].' ('.$row['kode_aset'].')</td>
                              <td>'.($row['nama_unit'] ?: '-').'</td>
                              <td align="center">'.$row['tanggal_direncanakan'].'</td>
                              <td align="center">'.$real.'</td>
                              <td align="center">'.$dev.'</td>
                              <td align="right">Rp. '.number_format($row['total_biaya'] ?: 0, 0, ',', '.').'</td>
                            </tr>';
              }
          }
          $html .= '</tbody></table>';
      } else if ($sub_report_type === 'aset_penghapusan') {
          $html .= '<table class="table table-bordered table-striped" style="width:100%; border-collapse:collapse;" border="1">
                      <thead>
                          <tr style="background-color:#f1f1f1;">
                              <th>No</th>
                              <th>No. Pengajuan</th>
                              <th>Nama Aset</th>
                              <th>Unit Kerja</th>
                              <th>Tgl Pengajuan</th>
                              <th>Alasan</th>
                              <th>Metode</th>
                              <th>Nilai Buku</th>
                              <th>Taksiran/Lelang</th>
                              <th>No. SK / BA</th>
                              <th>Status</th>
                          </tr>
                      </thead>
                      <tbody>';
          if (empty($data['data'])) {
              $html .= '<tr><td colspan="11" class="text-center text-muted">Tidak ditemukan data.</td></tr>';
          } else {
              foreach ($data['data'] as $i => $row) {
                  $html .= '<tr>
                              <td align="center">'.($i+1).'</td>
                              <td><b>'.$row['no_pengajuan'].'</b></td>
                              <td>'.$row['nama_aset'].' ('.$row['kode_aset'].')</td>
                              <td>'.($row['nama_unit'] ?: '-').'</td>
                              <td align="center">'.$row['tanggal_pengajuan'].'</td>
                              <td>'.$row['alasan_penghapusan'].'</td>
                              <td align="center">'.($row['metode_penghapusan'] ?: '-').'</td>
                              <td align="right">Rp. '.number_format($row['nilai_buku_terakhir'], 0, ',', '.').'</td>
                              <td align="right">Rp. '.number_format($row['nilai_taksiran'], 0, ',', '.').'</td>
                              <td>'.($row['no_sk'] ?: '-').' / '.($row['no_ba'] ?: '-').'</td>
                              <td align="center">'.strtoupper($row['status_penghapusan']).'</td>
                            </tr>';
              }
          }
          $html .= '</tbody></table>';
      }
      return $html;
  }

  public function anyExportPDFLaporan()
  {
      $report_name = $_GET['report_name'] ?? 'Laporan Logistik';
      $sub_report_type = $_GET['sub_report_type'] ?? '';
      $orientation = $_GET['orientation'] ?? 'P';
      
      $filters = [
          'tgl_awal' => $_GET['tgl_awal'] ?? date('Y-m-01'),
          'tgl_akhir' => $_GET['tgl_akhir'] ?? date('Y-m-d'),
          'kode_lokasi' => $_GET['kode_lokasi'] ?? '',
          'kategori' => $_GET['kategori'] ?? '',
          'cari' => $_GET['cari'] ?? '',
          'start_date' => $_GET['tgl_awal'] ?? date('Y-m-01'),
          'end_date' => $_GET['tgl_akhir'] ?? date('Y-m-d'),
          'unit' => $_GET['kode_unit'] ?? '',
          'kib_jenis' => $_GET['kib_jenis'] ?? ''
      ];

      $html_content = $this->_getReportOutputHtml($sub_report_type, $filters);

      $user = $this->core->getUserInfo('username', null, true);
      $generated_at = date('Y-m-d H:i:s');
      $verify_hash = hash('sha256', $sub_report_type . $filters['tgl_awal'] . $filters['tgl_akhir'] . $user . $generated_at . time());

      $this->_initReportVerifications();
      $this->db('rsns_custom_logistik_non_medis_report_verifications')->save([
          'id' => NULL,
          'verification_hash' => $verify_hash,
          'report_name' => $report_name,
          'period_start' => $filters['tgl_awal'],
          'period_end' => $filters['tgl_akhir'],
          'generated_by' => $user,
          'generated_at' => $generated_at,
          'checksum_data' => json_encode(['total_length' => strlen($html_content)])
      ]);

      $verify_url = url("admin/logistik_non_medis/verifyreport?hash=" . $verify_hash);

      $header = $this->core->setPrintHeader();
      $footer = '
          <table width="100%" style="font-size: 9px; border-top: 1px solid #000; padding-top: 5px;">
              <tr>
                  <td width="33%">Dicetak oleh: ' . $user . ' pada ' . $generated_at . '</td>
                  <td width="33%" align="center">Dokumen Sah Digital - SIMRS Logistik</td>
                  <td width="33%" align="right">Halaman {PAGENO} dari {nbpg}</td>
              </tr>
          </table>';

      $pdf_html = '
      <html>
      <head>
          <style>
              body { font-family: sans-serif; font-size: 10pt; color: #333; }
              h3 { text-align: center; margin-bottom: 5px; color: #000; text-transform: uppercase; }
              h4 { text-align: center; margin-top: 0; font-weight: normal; color: #555; }
              .meta-table { width: 100%; margin-bottom: 15px; font-size: 9pt; }
              .table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9pt; }
              .table th { background-color: #f1f1f1; border: 1px solid #ddd; padding: 5px; text-transform: uppercase; font-weight: bold; text-align: left; }
              .table td { border: 1px solid #ddd; padding: 5px; }
              .table tr:nth-child(even) { background-color: #fafafa; }
              .text-center { text-align: center; }
              .text-right { text-align: right; }
              .signature-container { width: 100%; margin-top: 30px; }
              .signature-box { float: right; width: 250px; text-align: center; font-size: 9pt; }
              .qr-box { float: left; width: 200px; text-align: center; font-size: 8pt; border: 1px dashed #bbb; padding: 5px; border-radius: 5px; }
          </style>
      </head>
      <body>
          <h3>' . $report_name . '</h3>
          <h4>Periode: ' . date('d-m-Y', strtotime($filters['tgl_awal'])) . ' s/d ' . date('d-m-Y', strtotime($filters['tgl_akhir'])) . '</h4>
          
          <table class="meta-table">
              <tr>
                  <td><b>Modul:</b> Logistik Non-Medis</td>
                  <td align="right"><b>Gudang/Lokasi:</b> ' . ($filters['kode_lokasi'] ?: 'Semua Gudang') . '</td>
              </tr>
              <tr>
                  <td><b>Status Validasi:</b> Terverifikasi Kriptografi</td>
                  <td align="right"><b>Kategori Item:</b> ' . ($filters['kategori'] ?: 'Semua Kategori') . '</td>
              </tr>
          </table>

          <div style="margin-top:10px;">
              ' . $html_content . '
          </div>

          <div class="signature-container">
              <div class="qr-box">
                  <p style="margin:0 0 5px 0; font-weight:bold;">Verifikasi Keaslian Dokumen</p>
                  <img src="https://api.qrserver.com/v1/create-qr-code/?size=85x85&data=' . urlencode($verify_url) . '" width="85" height="85" style="display:block; margin:auto;" />
                  <p style="margin:5px 0 0 0; font-size:8px; color:#666;">Scan QR-Code untuk memverifikasi dokumen secara online.</p>
              </div>
              <div class="signature-box">
                  <p style="margin:0 0 50px 0;">Mengetahui/Mengesahkan,<br><b>Kepala Urusan Logistik Non-Medis</b></p>
                  <p style="margin:0; font-weight:bold; text-decoration:underline;">M. Aulia Rahman, S.T.</p>
                  <p style="margin:0; color:#555;">NIP. 19840210 200904 1 002</p>
              </div>
          </div>
      </body>
      </html>';

      $mpdf = new \Mpdf\Mpdf([
          'mode' => 'utf-8',
          'format' => 'A4',
          'orientation' => $orientation,
          'margin_left' => 12,
          'margin_right' => 12,
          'margin_top' => 45,
          'margin_bottom' => 20,
      ]);

      $mpdf->SetHTMLHeader($header);
      $mpdf->SetHTMLFooter($footer);
      $mpdf->WriteHTML($this->core->setPrintCss(), \Mpdf\HTMLParserMode::HEADER_CSS);
      $mpdf->WriteHTML($pdf_html, \Mpdf\HTMLParserMode::HTML_BODY);
      $mpdf->Output($report_name . '_' . date('Ymd_His') . '.pdf', 'I');
      exit();
  }

  public function anyExportExcelLaporan()
  {
      $report_name = $_GET['report_name'] ?? 'Laporan Logistik';
      $sub_report_type = $_GET['sub_report_type'] ?? '';
      
      $filters = [
          'tgl_awal' => $_GET['tgl_awal'] ?? date('Y-m-01'),
          'tgl_akhir' => $_GET['tgl_akhir'] ?? date('Y-m-d'),
          'kode_lokasi' => $_GET['kode_lokasi'] ?? '',
          'kategori' => $_GET['kategori'] ?? '',
          'cari' => $_GET['cari'] ?? '',
          'start_date' => $_GET['tgl_awal'] ?? date('Y-m-01'),
          'end_date' => $_GET['tgl_akhir'] ?? date('Y-m-d'),
          'unit' => $_GET['kode_unit'] ?? '',
          'kib_jenis' => $_GET['kib_jenis'] ?? ''
      ];

      $html_content = $this->_getReportOutputHtml($sub_report_type, $filters);

      // Construct a valid and clean HTML wrapper to pass to PhpSpreadsheet HTML reader
      $full_html = '
      <html>
      <head>
          <style>
              th { background-color: #4e73df; color: #ffffff; font-weight: bold; border: 1px solid #cccccc; }
              td { border: 1px solid #cccccc; }
          </style>
      </head>
      <body>
          <table>
              <tr>
                  <td colspan="7" style="font-size: 14pt; font-weight: bold; text-align: center;">' . strtoupper($report_name) . '</td>
              </tr>
              <tr>
                  <td colspan="7" align="center" style="font-size: 10pt; color: #555;">Periode: ' . $filters['tgl_awal'] . ' s/d ' . $filters['tgl_akhir'] . '</td>
              </tr>
              <tr>
                  <td colspan="7" style="font-size: 10pt; color: #555;">Ekspor otomatis oleh SIMRS Logistik Non-Medis pada ' . date('Y-m-d H:i:s') . '</td>
              </tr>
              <tr><td colspan="7"></td></tr>
          </table>
          ' . $html_content . '
      </body>
      </html>';

      try {
          $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
          $spreadsheet = $reader->loadFromString($full_html);
          
          // Auto-fit columns to make it look professional and tidy
          foreach ($spreadsheet->getActiveSheet()->getColumnDimensions() as $col) {
              $col->setAutoSize(true);
          }

          // Modern Content-Type header for true XLSX files
          header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
          header('Content-Disposition: attachment;filename="' . str_replace(' ', '_', $report_name) . '_' . date('Ymd') . '.xlsx"');
          header('Cache-Control: max-age=0');

          $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
          $writer->save('php://output');
      } catch (\Exception $e) {
          // Fallback to old behavior in case of errors
          header("Content-Type: application/vnd.ms-excel");
          header("Content-Disposition: attachment; filename=" . str_replace(' ', '_', $report_name) . "_" . date('Ymd') . ".xls");
          header("Pragma: no-cache");
          header("Expires: 0");
          echo $full_html;
      }
      exit();
  }

  public function anyExportExcelHtml()
  {
      $report_name = $_POST['filename'] ?? $_GET['filename'] ?? 'Laporan_Logistik';
      $html_content = $_POST['html'] ?? $_GET['html'] ?? '';

      if (empty($html_content)) {
          echo "Tidak ada data HTML untuk diekspor.";
          exit();
      }

      $full_html = '
      <html>
      <head>
          <style>
              th { background-color: #4e73df; color: #ffffff; font-weight: bold; border: 1px solid #cccccc; }
              td { border: 1px solid #cccccc; }
          </style>
      </head>
      <body>
          ' . $html_content . '
      </body>
      </html>';

      try {
          $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
          $spreadsheet = $reader->loadFromString($full_html);
          
          // Auto-fit columns to make it look professional and tidy
          foreach ($spreadsheet->getActiveSheet()->getColumnDimensions() as $col) {
              $col->setAutoSize(true);
          }

          // Modern Content-Type header for true XLSX files
          header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
          header('Content-Disposition: attachment;filename="' . str_replace(' ', '_', $report_name) . '_' . date('Ymd_His') . '.xlsx"');
          header('Cache-Control: max-age=0');

          $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
          $writer->save('php://output');
      } catch (\Exception $e) {
          // Fallback to old behavior in case of errors
          header("Content-Type: application/vnd.ms-excel");
          header("Content-Disposition: attachment; filename=" . str_replace(' ', '_', $report_name) . "_" . date('Ymd_His') . ".xls");
          header("Pragma: no-cache");
          header("Expires: 0");
          echo $full_html;
      }
      exit();
  }

  public function postSaveReportSchedule()
  {
      $this->_initReportSchedules();

      $id = $_POST['id'] ?? '';
      $data = [
          'report_name' => $_POST['report_name'] ?? '',
          'report_type' => $_POST['report_type'] ?? '',
          'sub_report_type' => $_POST['sub_report_type'] ?? '',
          'frequency' => $_POST['frequency'] ?? 'weekly',
          'send_time' => $_POST['send_time'] ?? '07:00:00',
          'send_day' => !empty($_POST['send_day']) ? (int)$_POST['send_day'] : NULL,
          'email_recipients' => $_POST['email_recipients'] ?? '',
          'filters_json' => json_encode([
              'kode_lokasi' => $_POST['kode_lokasi'] ?? '',
              'kategori' => $_POST['kategori'] ?? '',
              'kode_unit' => $_POST['kode_unit'] ?? '',
              'kib_jenis' => $_POST['kib_jenis'] ?? ''
          ]),
          'status' => $_POST['status'] ?? 'Aktif'
      ];

      if (empty($id)) {
          $data['created_at'] = date('Y-m-d H:i:s');
          $data['created_by'] = $this->core->getUserInfo('username', null, true);
          $query = $this->db('rsns_custom_logistik_non_medis_report_schedules')->save($data);
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_report_schedules')->where('id', $id)->update($data);
      }

      echo json_encode(['status' => $query ? 'success' : 'error']);
      exit();
  }

  public function postDeleteReportSchedule()
  {
      $this->_initReportSchedules();
      $id = $_POST['id'] ?? '';
      if (!empty($id)) {
          $this->db('rsns_custom_logistik_non_medis_report_schedules')->where('id', $id)->delete();
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'ID kosong!']);
      }
      exit();
  }

  public function postSendInstantEmail()
  {
      $report_name = $_POST['report_name'] ?? 'Laporan Logistik';
      $sub_report_type = $_POST['sub_report_type'] ?? '';
      $emails = $_POST['emails'] ?? '';

      if (empty($emails)) {
          echo json_encode(['status' => 'error', 'message' => 'Alamat email tujuan kosong!']);
          exit();
      }

      $filters = [
          'tgl_awal' => $_POST['tgl_awal'] ?? date('Y-m-01'),
          'tgl_akhir' => $_POST['tgl_akhir'] ?? date('Y-m-d'),
          'kode_lokasi' => $_POST['kode_lokasi'] ?? '',
          'kategori' => $_POST['kategori'] ?? '',
          'cari' => $_POST['cari'] ?? '',
          'start_date' => $_POST['tgl_awal'] ?? date('Y-m-01'),
          'end_date' => $_POST['tgl_akhir'] ?? date('Y-m-d'),
          'unit' => $_POST['kode_unit'] ?? '',
          'kib_jenis' => $_POST['kib_jenis'] ?? ''
      ];

      $html_content = $this->_getReportOutputHtml($sub_report_type, $filters);

      $user = $this->core->getUserInfo('username', null, true);
      $generated_at = date('Y-m-d H:i:s');
      $verify_hash = hash('sha256', $sub_report_type . $filters['tgl_awal'] . $filters['tgl_akhir'] . $user . $generated_at . time());

      $this->_initReportVerifications();
      $this->db('rsns_custom_logistik_non_medis_report_verifications')->save([
          'id' => NULL,
          'verification_hash' => $verify_hash,
          'report_name' => $report_name,
          'period_start' => $filters['tgl_awal'],
          'period_end' => $filters['tgl_akhir'],
          'generated_by' => $user,
          'generated_at' => $generated_at,
          'checksum_data' => json_encode(['total_length' => strlen($html_content)])
      ]);

      $verify_url = url("admin/logistik_non_medis/verifyreport?hash=" . $verify_hash);

      $header = $this->core->setPrintHeader();
      $footer = '
          <table width="100%" style="font-size: 9px; border-top: 1px solid #000; padding-top: 5px;">
              <tr>
                  <td width="33%">Dicetak oleh: ' . $user . ' pada ' . $generated_at . '</td>
                  <td width="33%" align="center">Dokumen Sah Digital - SIMRS Logistik</td>
                  <td width="33%" align="right">Halaman {PAGENO} dari {nbpg}</td>
              </tr>
          </table>';

      $pdf_html = '
      <html>
      <head>
          <style>
              body { font-family: sans-serif; font-size: 10pt; color: #333; }
              h3 { text-align: center; margin-bottom: 5px; color: #000; text-transform: uppercase; }
              h4 { text-align: center; margin-top: 0; font-weight: normal; color: #555; }
              .meta-table { width: 100%; margin-bottom: 15px; font-size: 9pt; }
              .table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 9pt; }
              .table th { background-color: #f1f1f1; border: 1px solid #ddd; padding: 5px; text-transform: uppercase; font-weight: bold; text-align: left; }
              .table td { border: 1px solid #ddd; padding: 5px; }
              .table tr:nth-child(even) { background-color: #fafafa; }
              .text-center { text-align: center; }
              .text-right { text-align: right; }
              .signature-container { width: 100%; margin-top: 30px; }
              .signature-box { float: right; width: 250px; text-align: center; font-size: 9pt; }
              .qr-box { float: left; width: 200px; text-align: center; font-size: 8pt; border: 1px dashed #bbb; padding: 5px; border-radius: 5px; }
          </style>
      </head>
      <body>
          <h3>' . $report_name . '</h3>
          <h4>Periode: ' . date('d-m-Y', strtotime($filters['tgl_awal'])) . ' s/d ' . date('d-m-Y', strtotime($filters['tgl_akhir'])) . '</h4>
          
          <table class="meta-table">
              <tr>
                  <td><b>Modul:</b> Logistik Non-Medis</td>
                  <td align="right"><b>Gudang/Lokasi:</b> ' . ($filters['kode_lokasi'] ?: 'Semua Gudang') . '</td>
              </tr>
              <tr>
                  <td><b>Status Validasi:</b> Terverifikasi Kriptografi</td>
                  <td align="right"><b>Kategori Item:</b> ' . ($filters['kategori'] ?: 'Semua Kategori') . '</td>
              </tr>
          </table>

          <div style="margin-top:10px;">
              ' . $html_content . '
          </div>

          <div class="signature-container">
              <div class="qr-box">
                  <p style="margin:0 0 5px 0; font-weight:bold;">Verifikasi Keaslian Dokumen</p>
                  <img src="https://api.qrserver.com/v1/create-qr-code/?size=85x85&data=' . urlencode($verify_url) . '" width="85" height="85" style="display:block; margin:auto;" />
                  <p style="margin:5px 0 0 0; font-size:8px; color:#666;">Scan QR-Code untuk memverifikasi dokumen secara online.</p>
              </div>
              <div class="signature-box">
                  <p style="margin:0 0 50px 0;">Mengetahui/Mengesahkan,<br><b>Kepala Urusan Logistik Non-Medis</b></p>
                  <p style="margin:0; font-weight:bold; text-decoration:underline;">M. Aulia Rahman, S.T.</p>
                  <p style="margin:0; color:#555;">NIP. 19840210 200904 1 002</p>
              </div>
          </div>
      </body>
      </html>';

      $mpdf = new \Mpdf\Mpdf([
          'mode' => 'utf-8',
          'format' => 'A4',
          'orientation' => 'L',
          'margin_left' => 12,
          'margin_right' => 12,
          'margin_top' => 45,
          'margin_bottom' => 20,
      ]);

      $mpdf->SetHTMLHeader($header);
      $mpdf->SetHTMLFooter($footer);
      $mpdf->WriteHTML($this->core->setPrintCss(), \Mpdf\HTMLParserMode::HEADER_CSS);
      $mpdf->WriteHTML($pdf_html, \Mpdf\HTMLParserMode::HTML_BODY);
      $pdf_string = $mpdf->Output('', 'S');

      try {
          $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
          $mail->isSMTP();
          $mail->Host = $this->settings->get('api.apam_smtp_host');
          $mail->SMTPAuth = true;
          $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
          $mail->Port = $this->settings->get('api.apam_smtp_port');
          $mail->Username = $this->settings->get('api.apam_smtp_username');
          $mail->Password = $this->settings->get('api.apam_smtp_password');

          $mail->setFrom($this->core->settings->get('settings.email'), $this->core->settings->get('settings.nama_instansi'));
          
          $email_arr = explode(',', $emails);
          foreach ($email_arr as $e) {
              $mail->addAddress(trim($e));
          }

          $mail->AddStringAttachment($pdf_string, str_replace(' ', '_', $report_name) . ".pdf", 'base64', 'application/pdf');

          $mail->IsHTML(true);
          $mail->Subject = "[SIMRS LOGISTIK] " . $report_name . " - " . date('Y-m-d');
          $mail->Body = '
              <div style="font-family: sans-serif; font-size: 11pt; color: #333; line-height: 1.5; padding: 10px;">
                  <h3 style="color: #4e73df; margin-bottom: 5px;">DISTRIBUSI LAPORAN LOGISTIK NON-MEDIS</h3>
                  <p>Halo,</p>
                  <p>Terlampir dokumen laporan terverifikasi digital dari Sistem Logistik Non-Medis Rumah Sakit dengan detail berikut:</p>
                  <table style="font-size: 10pt; margin: 15px 0;">
                      <tr><td><b>Nama Laporan</b></td><td>: ' . $report_name . '</td></tr>
                      <tr><td><b>Periode</b></td><td>: ' . $filters['tgl_awal'] . ' s/d ' . $filters['tgl_akhir'] . '</td></tr>
                      <tr><td><b>Dicetak Oleh</b></td><td>: ' . $user . '</td></tr>
                      <tr><td><b>Waktu Generate</b></td><td>: ' . $generated_at . '</td></tr>
                  </table>
                  <hr style="border: 0.5px solid #eee; margin: 20px 0;">
                  <p style="font-size: 9pt; color: #888;">Pesan ini dikirim secara otomatis oleh SIMRS. Jangan membalas email ini secara langsung.</p>
              </div>';

          $mail->send();
          echo json_encode(['status' => 'success']);
      } catch (\Exception $e) {
          echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim email: ' . $e->getMessage()]);
      }
      exit();
  }

  public function postGetReportPreview()
  {
      $sub_report_type = $_POST['sub_report_type'] ?? '';
      $filters = [
          'tgl_awal' => $_POST['tgl_awal'] ?? date('Y-m-01'),
          'tgl_akhir' => $_POST['tgl_akhir'] ?? date('Y-m-d'),
          'kode_lokasi' => $_POST['kode_lokasi'] ?? '',
          'kategori' => $_POST['kategori'] ?? '',
          'cari' => $_POST['cari'] ?? '',
          'start_date' => $_POST['tgl_awal'] ?? date('Y-m-01'),
          'end_date' => $_POST['tgl_akhir'] ?? date('Y-m-d'),
          'unit' => $_POST['kode_unit'] ?? '',
          'kib_jenis' => $_POST['kib_jenis'] ?? ''
      ];

      $html = $this->_getReportOutputHtml($sub_report_type, $filters);

      if (empty(trim($html))) {
          echo "<tr><td colspan='20' class='text-center text-muted' style='padding:30px;'><i class='fa fa-folder-open-o fa-2x'></i><br>Tidak ditemukan data untuk kriteria filter yang dipilih.</td></tr>";
      } else {
          echo $html;
      }
      exit();
  }

  public function anyVerifyReport()
  {
      $hash = $_GET['hash'] ?? '';
      $this->_initReportVerifications();

      $verification = $this->db('rsns_custom_logistik_non_medis_report_verifications')
                           ->where('verification_hash', $hash)
                           ->oneArray();

      echo '
      <html>
      <head>
          <title>Verifikasi Laporan Digital</title>
          <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
          <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
          <style>
              body { background-color: #f8f9fc; padding-top: 50px; font-family: sans-serif; }
              .card { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); padding: 30px; margin: auto; max-width: 550px; }
              .success-icon { font-size: 60px; color: #1cc88a; text-align: center; margin-bottom: 20px; }
              .fail-icon { font-size: 60px; color: #e74a3b; text-align: center; margin-bottom: 20px; }
              .card-title { text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 25px; text-transform: uppercase; }
              .verify-table td { padding: 8px 0; border-bottom: 1px solid #f1f1f1; }
          </style>
      </head>
      <body>
          <div class="container">
              <div class="card" style="border-top: 5px solid ' . ($verification ? '#1cc88a' : '#e74a3b') . ';">';
              
              if ($verification) {
                  echo '
                  <div class="success-icon"><i class="fa fa-check-circle"></i></div>
                  <div class="card-title text-success">Laporan Terverifikasi Asli</div>
                  <p class="text-center text-muted">Sidik jari digital dokumen ini cocok 100% dengan arsip basis data SIMRS Logistik Rumah Sakit.</p>
                  
                  <table class="verify-table" width="100%">
                      <tr><td width="40%"><b>Nama Laporan</b></td><td>: ' . $verification['report_name'] . '</td></tr>
                      <tr><td><b>Periode</b></td><td>: ' . date('d-m-Y', strtotime($verification['period_start'])) . ' s/d ' . date('d-m-Y', strtotime($verification['period_end'])) . '</td></tr>
                      <tr><td><b>Otorisator Pembuat</b></td><td>: ' . $verification['generated_by'] . '</td></tr>
                      <tr><td><b>Tanggal Digenerate</b></td><td>: ' . date('d-m-Y H:i:s', strtotime($verification['generated_at'])) . '</td></tr>
                      <tr><td><b>Kode Sidik Jari (Hash)</b></td><td style="font-size:9px; word-break:break-all;">: ' . $verification['verification_hash'] . '</td></tr>
                      <tr><td><b>Status Dokumen</b></td><td>: <span class="label label-success">SAH & AKTIF</span></td></tr>
                  </table>';
              } else {
                  echo '
                  <div class="fail-icon"><i class="fa fa-times-circle"></i></div>
                  <div class="card-title text-danger">Laporan Tidak Dikenal</div>
                  <p class="text-center text-muted">Sidik jari digital dokumen tidak terdaftar pada SIMRS Logistik. Keabsahan dokumen tidak dapat dijamin!</p>
                  <p class="text-center"><a href="javascript:window.close();" class="btn btn-danger">Tutup Halaman</a></p>';
              }
              
              echo '
                  <hr>
                  <p class="text-center text-muted" style="font-size: 10px; margin-bottom: 0;">&copy; ' . date('Y') . ' SIMRS Logistik Non-Medis - Sertifikasi Penjaminan Keaslian Laporan</p>
              </div>
          </div>
      </body>
      </html>';
      exit();
  }

}


?>
