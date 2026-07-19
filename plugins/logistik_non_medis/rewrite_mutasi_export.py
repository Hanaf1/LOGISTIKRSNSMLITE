import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

start_marker = "  public function anyExportMutasi()"
end_marker = "  public function anyDisplayMutasi()"

start_idx = c.find(start_marker)
end_idx = c.find(end_marker, start_idx)

old_str = c[start_idx:end_idx]

new_str = '''  public function anyExportMutasi()
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

      $query = "SELECT m.no_mutasi, m.tgl_mutasi, b.nama_barang, d.jenis_mutasi, d.qty, d.keterangan 
                FROM rsns_custom_logistik_non_medis_mutasi m
                JOIN rsns_custom_logistik_non_medis_mutasi_detail d ON m.no_mutasi = d.no_mutasi
                LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON d.kode_item = b.kode_item
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
          $user = $this->core->getUserInfo('username', null, true);
          
          // Group by date to create header mutasi
          // However, for simplicity, we can create 1 no_mutasi per row
          
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
                  'keterangan' => 'Import massal',
                  'status' => 'Diterima',
                  'user_input' => $user,
                  'tgl_input' => date('Y-m-d H:i:s')
              ];
              $this->db('rsns_custom_logistik_non_medis_mutasi')->save($data);
              
              $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->save([
                  'no_mutasi' => $no_mutasi,
                  'kode_item' => $kode_item,
                  'jenis_mutasi' => $jenis_mutasi,
                  'batch_no' => '-',
                  'qty' => $qty,
                  'satuan' => '',
                  'keterangan' => $keterangan
              ]);
              
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
                  $query_insert = "INSERT INTO rsns_custom_logistik_non_medis_stok_batch (kode_item, kode_lokasi, batch_no, stok) VALUES ('$kode_item', '-', '-', $new_stok)";
                  $this->db()->pdo()->exec($query_insert);
              }
              $count++;
          }
          echo json_encode(['status' => 'success', 'message' => "$count baris data mutasi berhasil diimport!"]);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'File tidak ditemukan!']);
      }
      exit();
  }

'''

c = c.replace(old_str, new_str)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
