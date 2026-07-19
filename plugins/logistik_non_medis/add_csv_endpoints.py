import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

endpoints = '''
  public function anyExportMutasiCSV()
  {
      $kategori = $_GET['kategori'] ?? '';
      $bulan = $_GET['bulan'] ?? date('m');
      $tahun = $_GET['tahun'] ?? date('Y');
      
      $where = "WHERE MONTH(m.tgl_mutasi) = '$bulan' AND YEAR(m.tgl_mutasi) = '$tahun'";
      if (!empty($kategori)) {
          $where .= " AND b.kode_kategori = '$kategori'";
      }
      
      $query = "SELECT m.no_mutasi, m.tgl_mutasi, d.kode_item, b.nama_barang, d.jenis_mutasi, d.qty, d.keterangan 
                FROM rsns_custom_logistik_non_medis_mutasi m 
                JOIN rsns_custom_logistik_non_medis_mutasi_detail d ON m.no_mutasi = d.no_mutasi 
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON d.kode_item = b.kode_item 
                $where 
                ORDER BY m.tgl_mutasi ASC";
      $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
      
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=Laporan_Mutasi_' . $bulan . '_' . $tahun . '.csv');
      $output = fopen('php://output', 'w');
      fputcsv($output, ['No Transaksi', 'Tanggal', 'Kode Barang', 'Nama Barang', 'Jenis Mutasi', 'Qty', 'Keterangan']);
      foreach ($rows as $row) {
          fputcsv($output, $row);
      }
      fclose($output);
      exit();
  }

  public function anyDownloadTemplateMutasiCSV()
  {
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=Template_Import_Mutasi.csv');
      $output = fopen('php://output', 'w');
      fputcsv($output, ['No Transaksi', 'Tanggal (YYYY-MM-DD)', 'Catatan Umum', 'Kode Barang', 'Jenis Mutasi (Masuk/Keluar/Penyesuaian)', 'Jumlah', 'Catatan Item']);
      fputcsv($output, ['MUT/2026/001', '2026-07-19', 'Stok awal bulan', 'BRG0001', 'Masuk', '100', '']);
      fputcsv($output, ['MUT/2026/001', '2026-07-19', 'Stok awal bulan', 'BRG0002', 'Masuk', '50', 'Dus rusak']);
      fclose($output);
      exit();
  }

  public function postImportMutasiCSV()
  {
      if (!isset($_FILES['file_csv']) || $_FILES['file_csv']['error'] != UPLOAD_ERR_OK) {
          echo json_encode(['status' => 'error', 'message' => 'Gagal mengupload file CSV']);
          exit();
      }
      
      $file = $_FILES['file_csv']['tmp_name'];
      $handle = fopen($file, "r");
      if ($handle !== FALSE) {
          fgetcsv($handle, 1000, ","); // Skip header
          
          $mutasi_data = [];
          
          while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
              if (count($data) < 6 || empty($data[0]) || empty($data[3])) continue;
              
              $no_mutasi = $data[0];
              $tgl = $data[1];
              $catatan_umum = $data[2];
              
              $kode_item = $data[3];
              $jenis = ucfirst(strtolower(trim($data[4])));
              if (!in_array($jenis, ['Masuk', 'Keluar', 'Penyesuaian'])) $jenis = 'Penyesuaian';
              $qty = (float)$data[5];
              $catatan_item = $data[6] ?? '';
              
              if (!isset($mutasi_data[$no_mutasi])) {
                  $mutasi_data[$no_mutasi] = [
                      'tgl' => $tgl,
                      'catatan' => $catatan_umum,
                      'items' => []
                  ];
              }
              
              $mutasi_data[$no_mutasi]['items'][] = [
                  'kode_item' => $kode_item,
                  'jenis' => $jenis,
                  'qty' => $qty,
                  'ket' => $catatan_item
              ];
          }
          fclose($handle);
          
          // Save to DB
          foreach ($mutasi_data as $no_mutasi => $mut) {
              // Delete existing if any
              $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->where('no_mutasi', $no_mutasi)->delete();
              $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->delete();
              
              $this->db('rsns_custom_logistik_non_medis_mutasi')->save([
                  'no_mutasi' => $no_mutasi,
                  'tgl_mutasi' => $mut['tgl'],
                  'keterangan' => $mut['catatan'],
                  'status' => 'Diterima'
              ]);
              
              foreach ($mut['items'] as $item) {
                  $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->save([
                      'no_mutasi' => $no_mutasi,
                      'kode_item' => $item['kode_item'],
                      'jenis_mutasi' => $item['jenis'],
                      'qty' => $item['qty'],
                      'keterangan' => $item['ket']
                  ]);
                  
                  // Auto-adjust stock
                  $batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')->where('kode_item', $item['kode_item'])->where('kode_lokasi', '-')->oneArray();
                  if ($batch) {
                      $current_stok = (float)$batch['stok'];
                      $new_stok = $current_stok;
                      if ($item['jenis'] == 'Masuk') $new_stok += $item['qty'];
                      elseif ($item['jenis'] == 'Keluar') $new_stok -= $item['qty'];
                      elseif ($item['jenis'] == 'Penyesuaian') $new_stok = $item['qty'];
                      $this->db('rsns_custom_logistik_non_medis_stok_batch')->where('kode_item', $item['kode_item'])->where('kode_lokasi', '-')->update(['stok' => $new_stok]);
                  } else {
                      $new_stok = $item['qty'];
                      if ($item['jenis'] == 'Keluar') $new_stok = 0 - $item['qty'];
                      $query_insert = "INSERT INTO rsns_custom_logistik_non_medis_stok_batch (kode_item, kode_lokasi, batch_no, stok) VALUES ('".$item['kode_item']."', '-', '-', $new_stok)";
                      $this->db()->pdo()->exec($query_insert);
                  }
              }
          }
          
          echo json_encode(['status' => 'success', 'message' => 'Data mutasi berhasil diimport dari CSV']);
          exit();
      }
      echo json_encode(['status' => 'error', 'message' => 'Gagal membaca file CSV']);
      exit();
  }
'''

# Find the position right before the last closing brace in the class
if 'public function anyExportMutasiCSV()' not in c:
    idx = c.rfind('}')
    c = c[:idx] + endpoints + '\n' + c[idx:]
    with open(path, 'w', encoding='utf-8') as f: f.write(c)

