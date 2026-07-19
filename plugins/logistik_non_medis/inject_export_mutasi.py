import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

export_import_code = '''
  public function anyExportMutasi()
  {
      $kategori = $_GET['kategori'] ?? '';
      $bulan = $_GET['bulan'] ?? '';
      $tahun = $_GET['tahun'] ?? '';
      
      $where = "WHERE 1=1";
      if (!empty($kategori)) {
          $where .= " AND b.kode_kategori = '$kategori'";
      }
      if (!empty($bulan) && !empty($tahun)) {
          $where .= " AND MONTH(m.tgl_mutasi) = '$bulan' AND YEAR(m.tgl_mutasi) = '$tahun'";
      }

      $query = "SELECT m.no_mutasi, m.tgl_mutasi, b.nama_barang, m.jenis_mutasi, m.qty, m.keterangan 
                FROM rsns_custom_logistik_non_medis_mutasi m
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON m.kode_item = b.kode_item
                $where
                ORDER BY m.tgl_mutasi DESC";
                
      $rows = $this->db()->pdo()->query($query)->fetchAll(\\PDO::FETCH_ASSOC);

      header("Content-type: application/vnd-ms-excel");
      header("Content-Disposition: attachment; filename=Data_Mutasi_Stok.xls");

      echo "<table border='1'>";
      echo "<tr>";
      echo "<th>No. Mutasi</th>";
      echo "<th>Tanggal</th>";
      echo "<th>Barang</th>";
      echo "<th>Jenis Mutasi</th>";
      echo "<th>Qty</th>";
      echo "<th>Catatan</th>";
      echo "</tr>";

      foreach($rows as $row) {
          echo "<tr>";
          echo "<td>".$row['no_mutasi']."</td>";
          echo "<td>".$row['tgl_mutasi']."</td>";
          echo "<td>".$row['nama_barang']."</td>";
          echo "<td>".$row['jenis_mutasi']."</td>";
          echo "<td>".$row['qty']."</td>";
          echo "<td>".$row['keterangan']."</td>";
          echo "</tr>";
      }
      echo "</table>";
      exit();
  }

  public function anyDownloadTemplateMutasi()
  {
      header("Content-type: application/vnd-ms-excel");
      header("Content-Disposition: attachment; filename=Template_Import_Mutasi.xls");

      echo "<table border='1'>";
      echo "<tr>";
      echo "<th>Tanggal (YYYY-MM-DD)</th>";
      echo "<th>Kode Barang</th>";
      echo "<th>Jenis Mutasi (Masuk/Keluar/Penyesuaian)</th>";
      echo "<th>Qty</th>";
      echo "<th>Catatan</th>";
      echo "</tr>";
      echo "<tr>";
      echo "<td>2024-01-01</td>";
      echo "<td>BRG001</td>";
      echo "<td>Masuk</td>";
      echo "<td>10</td>";
      echo "<td>Stok awal</td>";
      echo "</tr>";
      echo "</table>";
      exit();
  }

  public function postImportMutasi()
  {
      if(isset($_FILES["file"]["name"])) {
          $path = $_FILES["file"]["tmp_name"];
          require_once BASE_DIR . '/systems/SpreadsheetReader.php';
          $reader = new \\SpreadsheetReader($path);
          
          $count = 0;
          foreach ($reader as $key => $row) {
              if($key == 0) continue; // Skip header
              
              $tgl_mutasi = $row[0] ?? date('Y-m-d');
              $kode_item = $row[1] ?? '';
              $jenis_mutasi = $row[2] ?? 'Penyesuaian';
              $qty = (float)($row[3] ?? 0);
              $keterangan = $row[4] ?? '';
              
              if(empty($kode_item)) continue;
              
              $no_mutasi = $this->_generateNoMutasi();
              
              $data = [
                  'no_mutasi' => $no_mutasi,
                  'tgl_mutasi' => $tgl_mutasi,
                  'jenis_mutasi' => $jenis_mutasi,
                  'kode_item' => $kode_item,
                  'qty' => $qty,
                  'keterangan' => $keterangan,
                  'status' => 'Diterima',
                  'user_input' => $this->core->getUserInfo('username', null, true),
                  'tgl_input' => date('Y-m-d H:i:s')
              ];
              
              $this->db('rsns_custom_logistik_non_medis_mutasi')->save($data);
              
              // Adjust stock
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
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')->save([
                      'kode_item' => $kode_item,
                      'kode_lokasi' => '-',
                      'batch_no' => '-',
                      'stok' => $new_stok,
                      'tgl_expired' => NULL
                  ]);
              }
              $count++;
          }
          echo json_encode(['status' => 'success', 'message' => "$count data mutasi berhasil diimport!"]);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'File tidak ditemukan!']);
      }
      exit();
  }
'''

# Insert right before anyDisplayMutasi
c = c.replace('  public function anyDisplayMutasi()', export_import_code + '\n  public function anyDisplayMutasi()')

with open(path, 'w', encoding='utf-8') as f: f.write(c)
