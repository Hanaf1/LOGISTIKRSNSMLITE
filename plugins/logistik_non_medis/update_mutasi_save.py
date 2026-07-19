import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

def replace_post_save_mutasi(match):
    return '''  public function postSaveMutasi()
  {
      $no_mutasi = $_POST['no_mutasi'] ?? '';
      $user = $this->core->getUserInfo('username', null, true);
      
      $cek = $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->oneArray();
      if($cek && $cek['status'] != 'Draft') {
          echo json_encode(['status' => 'error', 'message' => 'Data sudah diproses dan tidak dapat diubah!']);
          exit();
      }

      $kode_item = $_POST['kode_item'] ?? '';
      $qty = (float)($_POST['qty'] ?? 0);
      $jenis_mutasi = $_POST['jenis_mutasi'] ?? 'Penyesuaian';
      
      $data = [
          'tgl_mutasi' => $_POST['tgl_mutasi'] ?? date('Y-m-d'),
          'jenis_mutasi' => $jenis_mutasi,
          'kode_item' => $kode_item,
          'qty' => $qty,
          'keterangan' => $_POST['keterangan'] ?? '',
          'user_input' => $user
      ];

      if(!$cek) {
          $data['no_mutasi'] = $no_mutasi;
          $data['status'] = 'Diterima'; // Langsung terapkan stok
          $data['tgl_input'] = date('Y-m-d H:i:s');
          $this->db('rsns_custom_logistik_non_medis_mutasi')->save($data);
      } else {
          $data['status'] = 'Diterima';
          $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->update($data);
      }
      
      // Update Stock (Auto-adjust based on Jenis Mutasi)
      // Lokasi is ignored, we just assume default location '-' for single warehouse logic
      if (!empty($kode_item)) {
          $batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')->where('kode_item', $kode_item)->where('kode_lokasi', '-')->oneArray();
          if ($batch) {
              $current_stok = (float)$batch['stok'];
              $new_stok = $current_stok;
              if ($jenis_mutasi == 'Masuk') {
                  $new_stok += $qty;
              } elseif ($jenis_mutasi == 'Keluar') {
                  $new_stok -= $qty;
              } elseif ($jenis_mutasi == 'Penyesuaian') {
                  $new_stok = $qty; // Override with exact quantity
              }
              $this->db('rsns_custom_logistik_non_medis_stok_batch')->where('kode_item', $kode_item)->where('kode_lokasi', '-')->update(['stok' => $new_stok]);
          } else {
              $new_stok = $qty;
              if ($jenis_mutasi == 'Keluar') $new_stok = 0 - $qty;
              
              $this->db('rsns_custom_logistik_non_medis_stok_batch')->save([
                  'kode_item' => $kode_item,
                  'kode_lokasi' => '-',
                  'batch_no' => '-',
                  'stok' => $new_stok,
                  'tgl_expired' => NULL
              ]);
          }
      }

      echo json_encode(['status' => 'success', 'message' => 'Mutasi stok berhasil disimpan dan stok otomatis disesuaikan!']);
      exit();
  }'''

c = re.sub(r'  public function postSaveMutasi\(\).*?echo json_encode\(\[\'status\' => \'success\'.*?exit\(\);\s*\}', replace_post_save_mutasi(None), c, flags=re.DOTALL)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
