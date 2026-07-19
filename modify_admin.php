<?php
$c = file_get_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/Admin.php');

$new_methods = <<<'EOD'
  public function anyDataTablesMasterBarang()
  {
      $items = $this->db('rsns_custom_logistik_non_medis_master_barang')
                    ->desc('kode_item')
                    ->toArray();
      
      $data = [];
      foreach($items as $row) {
          $aksi = '<button type="button" class="btn btn-xs btn-primary btn-edit-barang" data-id="'.$row['kode_item'].'"><i class="fa fa-edit"></i> Edit</button> ';
          $aksi .= '<button type="button" class="btn btn-xs btn-danger btn-hapus-barang" data-id="'.$row['kode_item'].'"><i class="fa fa-trash"></i> Hapus</button>';
          
          $data[] = [
              $row['kode_item'],
              $row['barcode'],
              $row['nama_barang'],
              $row['kategori'],
              $row['satuan_dasar'],
              'Rp. ' . number_format((float)$row['harga_referensi'], 0, ',', '.'),
              $row['status'],
              $aksi
          ];
      }
      
      echo json_encode(['data' => $data]);
      exit();
  }

  public function anyExportMasterBarang()
  {
      $items = $this->db('rsns_custom_logistik_non_medis_master_barang')->desc('kode_item')->toArray();
      
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=Data_Barang_Logistik_'.date('Y-m-d').'.csv');
      $output = fopen('php://output', 'w');
      
      // Header CSV
      fputcsv($output, ['Kode Item', 'Barcode', 'Nama Barang', 'Deskripsi', 'Kategori', 'Sub Kategori', 'Satuan Dasar', 'Satuan Konversi', 'Isi', 'Kapasitas', 'Harga Referensi', 'Status', 'Tipe Barang']);
      
      foreach ($items as $row) {
          fputcsv($output, [
              $row['kode_item'],
              $row['barcode'],
              $row['nama_barang'],
              $row['deskripsi'],
              $row['kategori'],
              $row['sub_kategori'],
              $row['satuan_dasar'],
              $row['satuan_konversi'],
              $row['isi'],
              $row['kapasitas'],
              $row['harga_referensi'],
              $row['status'],
              $row['tipe_barang']
          ]);
      }
      fclose($output);
      exit();
  }

  public function anyTemplateMasterBarang()
  {
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=Template_Import_Barang.csv');
      $output = fopen('php://output', 'w');
      
      // Header CSV
      fputcsv($output, ['Kode Item', 'Barcode', 'Nama Barang', 'Deskripsi', 'Kategori', 'Sub Kategori', 'Satuan Dasar', 'Satuan Konversi', 'Isi', 'Kapasitas', 'Harga Referensi (Angka)', 'Status (Aktif/Nonaktif)', 'Tipe Barang (Aset/Bahan/Barang/dll)']);
      
      // Contoh Data (Dummy)
      fputcsv($output, ['BRG0120260001', '123456789', 'Contoh Kertas A4', 'Kertas HVS A4 80gsm', 'ATK', 'Kertas', 'RIM', 'PCS', '500', '1', '45000', 'Aktif', 'Barang']);
      
      fclose($output);
      exit();
  }

  public function postImportMasterBarang()
  {
      $return = ['status' => 'error', 'pesan' => 'Terjadi kesalahan sistem'];
      
      if(isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
          $filename = $_FILES['file']['name'];
          $ext = pathinfo($filename, PATHINFO_EXTENSION);
          
          if(strtolower($ext) != 'csv') {
              echo json_encode(['status' => 'error', 'pesan' => 'File harus berupa CSV!']);
              exit();
          }
          
          $file = fopen($_FILES['file']['tmp_name'], "r");
          $is_header = true;
          $sukses = 0;
          $gagal = 0;
          
          while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
              if($is_header) {
                  $is_header = false;
                  continue; // Skip header
              }
              
              if(count($data) < 13) continue; // Pastikan kolom cukup
              
              $kode = trim($data[0]);
              $nama = trim($data[2]);
              
              if(empty($kode) || empty($nama)) {
                  $gagal++;
                  continue;
              }
              
              // Cek apakah kode sudah ada
              $cek = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode)->oneArray();
              
              $record = [
                  'barcode' => trim($data[1]),
                  'nama_barang' => $nama,
                  'deskripsi' => trim($data[3]),
                  'kategori' => trim($data[4]),
                  'sub_kategori' => trim($data[5]),
                  'satuan_dasar' => trim($data[6]),
                  'satuan_konversi' => trim($data[7]),
                  'isi' => trim($data[8]),
                  'kapasitas' => trim($data[9]),
                  'harga_referensi' => trim($data[10]),
                  'status' => trim($data[11]),
                  'tipe_barang' => trim($data[12])
              ];
              
              if($cek) {
                  // Update
                  $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode)->save($record);
                  $sukses++;
              } else {
                  // Insert
                  $record['kode_item'] = $kode;
                  $this->db('rsns_custom_logistik_non_medis_master_barang')->save($record);
                  $sukses++;
              }
          }
          fclose($file);
          
          $return = ['status' => 'success', 'pesan' => "Import selesai! Berhasil: $sukses, Gagal: $gagal"];
      } else {
          $return = ['status' => 'error', 'pesan' => 'Gagal mengupload file!'];
      }
      
      echo json_encode($return);
      exit();
  }
EOD;

$c = preg_replace('/public function postHapusMasterBarang\(\)\s*\{.*?\n  \}/s', "public function postHapusMasterBarang()\n  {\n      \$result = \$this->master_data->postHapus();\n      echo json_encode(\$result);\n      exit();\n  }\n\n" . $new_methods, $c);

file_put_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/Admin.php', $c);
echo "Admin.php modified successfully!";
