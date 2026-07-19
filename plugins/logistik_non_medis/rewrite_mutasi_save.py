import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

start_marker = "  public function anyFormMutasi()"
end_marker = "  public function anyExportMutasi()"

start_idx = c.find(start_marker)
end_idx = c.find(end_marker, start_idx)

old_str = c[start_idx:end_idx]

new_str = '''  public function anyFormMutasi()
  {
      $this->_initMutasi();
      $mode = $_POST['mode'] ?? 'add';
      
      if ($mode == 'edit' && isset($_POST['no_mutasi'])){
          $no_mutasi = $_POST['no_mutasi'];
          $mutasi = $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
          
          $details = $this->db('rsns_custom_logistik_non_medis_mutasi_detail')
                          ->leftJoin('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_mutasi_detail.kode_item = rsns_custom_logistik_non_medis_master_barang.kode_item')
                          ->select('rsns_custom_logistik_non_medis_mutasi_detail.*')
                          ->select('rsns_custom_logistik_non_medis_master_barang.nama_barang')
                          ->where('no_mutasi', $no_mutasi)
                          ->toArray();
          $mutasi['details'] = $details;
          echo $this->draw('gudang.mutasi.form.html', ['mutasi' => $mutasi, 'mode' => 'edit']);
      } else {
          $mutasi = [
              'no_mutasi' => $this->_generateNoMutasi(),
              'tgl_mutasi' => date('Y-m-d'),
              'keterangan' => '',
              'status' => 'Draft',
              'details' => []
          ];
          echo $this->draw('gudang.mutasi.form.html', ['mutasi' => $mutasi, 'mode' => 'add']);
      }
      exit();
  }

  public function anyLoadItemsForMutasi()
  {
      // Deprecated, using select2 ajax
      exit();
  }

  public function postSaveMutasi()
  {
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      $user = $this->core->getUserInfo('username', null, true);
      
      $cek = $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if($cek && $cek['status'] != 'Draft') {
          echo json_encode(['status' => 'error', 'message' => 'Data sudah diproses dan tidak dapat diubah!']);
          exit();
      }
      
      $data = [
          'tgl_mutasi' => $_POST['tgl_mutasi'] ?? date('Y-m-d'),
          'keterangan' => $_POST['keterangan'] ?? ''
      ];

      if(!$cek) {
          $data['no_mutasi'] = $no_mutasi;
          $data['status'] = 'Diterima'; // Langsung terapkan stok
          $data['user_input'] = $user;
          $data['tgl_input'] = date('Y-m-d H:i:s');
          $this->db('rsns_custom_logistik_non_medis_mutasi')->save($data);
      } else {
          $data['status'] = 'Diterima';
          $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->update($data);
      }
      
      // Save Details and Update Stock
      $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->where('no_mutasi', $no_mutasi)->delete();
      
      if(isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
          foreach($_POST['kode_item'] as $key => $kode_item) {
              $qty = (float)($_POST['qty'][$key] ?? 0);
              $jenis_mutasi = $_POST['jenis_mutasi'][$key] ?? 'Penyesuaian';
              $ket = $_POST['keterangan_item'][$key] ?? '';
              
              if(empty($kode_item)) continue;
              
              $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->save([
                  'no_mutasi' => $no_mutasi,
                  'kode_item' => $kode_item,
                  'jenis_mutasi' => $jenis_mutasi,
                  'batch_no' => '-',
                  'qty' => $qty,
                  'satuan' => '',
                  'keterangan' => $ket
              ]);
              
              // Auto-adjust stock
              $batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')->where('kode_item', $kode_item)->where('kode_lokasi', '-')->oneArray();
              if ($batch) {
                  $current_stok = (float)$batch['stok'];
                  $new_stok = $current_stok;
                  if ($jenis_mutasi == 'Masuk') $new_stok += $qty;
                  elseif ($jenis_mutasi == 'Keluar') $new_stok -= $qty;
                  elseif ($jenis_mutasi == 'Penyesuaian') $new_stok = $qty;
                  
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')->where('kode_item', $kode_item)->where('kode_lokasi', '-')->update(['stok' => $new_stok]);
              } else {
                  $new_stok = $qty;
                  if ($jenis_mutasi == 'Keluar') $new_stok = 0 - $qty;
                  
                  // NOTE: using raw query to insert into stok_batch to avoid MLITE save() bug if batch_no is missing in mapping
                  $query_insert = "INSERT INTO rsns_custom_logistik_non_medis_stok_batch (kode_item, kode_lokasi, batch_no, stok) VALUES ('$kode_item', '-', '-', $new_stok)";
                  $this->db()->pdo()->exec($query_insert);
              }
          }
      }

      echo json_encode(['status' => 'success', 'message' => 'Mutasi stok berhasil disimpan dan stok otomatis disesuaikan!']);
      exit();
  }

'''

c = c.replace(old_str, new_str)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
