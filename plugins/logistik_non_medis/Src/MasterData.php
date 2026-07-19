<?php

namespace Plugins\Logistik_non_medis\Src;

use Systems\Lib\QueryWrapper;

class MasterData
{
    protected $core;

    public function __construct($core = null)
    {
        $this->core = $core;
    }

    protected function db($table = null)
    {
        return $this->core->db($table);
    }

    public function initDataBarang()
    {
        $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_master_barang` (
        `kode_item` varchar(50) NOT NULL,
        `barcode` varchar(100) DEFAULT NULL,
        `nama_barang` varchar(200) NOT NULL,
        `deskripsi` text DEFAULT NULL,
        `spesifikasi` text DEFAULT NULL,
        `kategori` varchar(100) DEFAULT NULL,
        `sub_kategori` varchar(100) DEFAULT NULL,
        `satuan_dasar` varchar(50) NOT NULL,
        `satuan_konversi` varchar(50) DEFAULT NULL,
        `harga_referensi` double NOT NULL DEFAULT 0,
        `stok_min` double NOT NULL DEFAULT 0,
        `stok_max` double NOT NULL DEFAULT 0,
        `safety_stock` double NOT NULL DEFAULT 0,
        `foto` varchar(255) DEFAULT NULL,
        `dokumen` varchar(255) DEFAULT NULL,
        `default_kode_lokasi` varchar(50) DEFAULT NULL,
        `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
        PRIMARY KEY (`kode_item`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $check_query = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_master_barang` LIKE 'stok_min'");
      $check = $check_query ? $check_query->fetch() : false;
      if (!$check) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `stok_min` double NOT NULL DEFAULT 0 AFTER `harga_referensi` ");
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `stok_max` double NOT NULL DEFAULT 0 AFTER `stok_min` ");
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `safety_stock` double NOT NULL DEFAULT 0 AFTER `stok_max` ");
      }
      
      $check_loc_query = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_master_barang` LIKE 'default_kode_lokasi'");
      $check_loc = $check_loc_query ? $check_loc_query->fetch() : false;
      if (!$check_loc) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `default_kode_lokasi` varchar(50) DEFAULT NULL AFTER `dokumen` ");
      }

      $upload_dir = UPLOADS . '/logistik_non_medis';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      if (!is_dir($upload_dir . '/foto')) mkdir($upload_dir . '/foto', 0777, true);
      if (!is_dir($upload_dir . '/dokumen')) mkdir($upload_dir . '/dokumen', 0777, true);
    }

    public function generateKodeBarang()
    {
        $prefix = 'BRG' . date('mY');
        $last = $this->db('rsns_custom_logistik_non_medis_master_barang')
                     ->where('kode_item', 'LIKE', $prefix.'%')
                     ->desc('kode_item')
                     ->limit(1)
                     ->oneArray();
        
        if ($last) {
            $last_num = (int) substr($last['kode_item'], -4);
            $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $next_num = '0001';
        }
        
        return $prefix . $next_num;
    }

    public function anyDisplay()
    {
        $this->initDataBarang();
        $perpage = 10;
        $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
        $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
        
        $_offset = ($halaman - 1) * $perpage;
        
        $query = $this->db('rsns_custom_logistik_non_medis_master_barang');
        if(!empty($cari)) {
            $query->where('kode_item', 'LIKE', '%'.$cari.'%')
                  ->orLike('nama_barang', '%'.$cari.'%')
                  ->orLike('barcode', '%'.$cari.'%');
        }
        
        $all_data = $query->toArray();
        $jumlah_data = count($all_data);
        $jml_halaman = ceil($jumlah_data / $perpage);
        
        $rows = $this->db('rsns_custom_logistik_non_medis_master_barang');
        if(!empty($cari)) {
            $rows->where('kode_item', 'LIKE', '%'.$cari.'%')
                  ->orLike('nama_barang', '%'.$cari.'%')
                  ->orLike('barcode', '%'.$cari.'%');
        }
        $rows = $rows->desc('kode_item')
                      ->offset($_offset)
                      ->limit($perpage)
                      ->toArray();

        return [
            'barang' => $rows,
            'halaman' => $halaman,
            'jumlah_data' => $jumlah_data,
            'jml_halaman' => $jml_halaman
        ];
    }

    public function anyDetail()
    {
        if (isset($_POST['kode_item'])){
            return $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $_POST['kode_item'])->oneArray();
        }
        return null;
    }

    public function anyForm()
    {
        $return = [];
        $return['kategori'] = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
        $return['satuan'] = $this->db('rsns_custom_logistik_non_medis_satuan')->toArray();
        // $return['lokasi'] = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();

        if (isset($_POST['kode_item'])){
            $return['barang'] = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $_POST['kode_item'])->oneArray();
            $return['mode'] = 'edit';
        } else {
            $return['barang'] = [
                'kode_item' => $this->generateKodeBarang(),
                'barcode' => '',
                'nama_barang' => '',
                'deskripsi' => '',
                'spesifikasi' => '',
                'kategori' => '',
                'sub_kategori' => '',
                'satuan_dasar' => '',
                'satuan_konversi' => '',
                'harga_referensi' => '',
                'stok_min' => 0,
                'stok_max' => 0,
                'safety_stock' => 0,
                'foto' => '',
                'dokumen' => '',
                'default_kode_lokasi' => '',
                'status' => 'Aktif'
            ];
            $return['mode'] = 'add';
        }
        return $return;
    }

    public function postSave()
    {
        $kode_item = $_POST['kode_item'] ?? '';
        if(empty($kode_item)) {
            return ['status' => 'error', 'message' => 'Kode Item wajib diisi!'];
        }

        $data = [
            'kode_item' => $kode_item,
            'barcode' => $_POST['barcode'] ?? '',
            'nama_barang' => $_POST['nama_barang'] ?? '',
            'deskripsi' => $_POST['deskripsi'] ?? '',
            'spesifikasi' => $_POST['spesifikasi'] ?? '',
            'kategori' => $_POST['kategori'] ?? '',
            'sub_kategori' => $_POST['sub_kategori'] ?? '',
            'satuan_dasar' => $_POST['satuan_dasar'] ?? '',
            'satuan_konversi' => $_POST['satuan_konversi'] ?? '',
            'harga_referensi' => str_replace(['Rp.', '.'], '', $_POST['harga_referensi'] ?? 0),
            'stok_min' => $_POST['stok_min'] ?? 0,
            'stok_max' => $_POST['stok_max'] ?? 0,
            'safety_stock' => $_POST['safety_stock'] ?? 0,
            'default_kode_lokasi' => $_POST['default_kode_lokasi'] ?? NULL,
            'status' => $_POST['status'] ?? 'Aktif'
        ];

        // Logging Feature
        if ($this->core) {
            $user = $this->core->getUserInfo('username', null, true);
            $tanggal_log = date('Y-m-d H:i:s');
            $ip = $_SERVER['REMOTE_ADDR'];
            $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
            $hostname = $cek_hostname['hostname'] ?? 'Unknown';
            $log_lokasi = ''.$hostname.' | '.$ip.'';
            $logdata = ''.$data['kode_item'].' | '.$data['barcode'].' | '.$data['nama_barang'].' | '.$data['deskripsi'].' | '.$data['spesifikasi'].' | '.$data['kategori'].' | '.$data['sub_kategori'].' | '.$data['satuan_dasar'].' | '.$data['satuan_konversi'].' | '.$data['harga_referensi'].' | '.$data['status'].' | '.$user.'';

            $this->db('mlite_tracksql')->save([
                'log_id' => NULL,
                'log_modul' => 'logistik_non_medis_master_barang',
                'log_waktu' => $tanggal_log,
                'log_location' => $log_lokasi,
                'log_data' => $logdata,
                'log_status' => (isset($_POST['kode_item']) && $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $_POST['kode_item'])->oneArray()) ? 'U' : 'I',
                'log_username' => $user
            ]);
        }

        $upload_dir = UPLOADS . '/logistik_non_medis';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        if (!is_dir($upload_dir . '/foto')) mkdir($upload_dir . '/foto', 0777, true);
        if (!is_dir($upload_dir . '/dokumen')) mkdir($upload_dir . '/dokumen', 0777, true);

        // Handle File Uploads
        if(isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $allowed_images = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if(in_array($ext, $allowed_images)) {
                $filename = 'foto_' . $kode_item . '_' . time() . '.' . $ext;
                if(move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . '/foto/' . $filename)) {
                    $data['foto'] = $filename;
                } else {
                    return ['status' => 'error', 'message' => 'Gagal mengupload foto ke server.'];
                }
            } else {
                return ['status' => 'error', 'message' => 'Format Foto tidak didukung! Gunakan jpg, jpeg, png, atau gif.'];
            }
        }

        if(isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] == 0) {
            $allowed_docs = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
            $ext = strtolower(pathinfo($_FILES['dokumen']['name'], PATHINFO_EXTENSION));
            if(in_array($ext, $allowed_docs)) {
                $filename = 'dok_' . $kode_item . '_' . time() . '.' . $ext;
                if(move_uploaded_file($_FILES['dokumen']['tmp_name'], $upload_dir . '/dokumen/' . $filename)) {
                    $data['dokumen'] = $filename;
                } else {
                    return ['status' => 'error', 'message' => 'Gagal mengupload dokumen ke server.'];
                }
            } else {
                return ['status' => 'error', 'message' => 'Format Dokumen tidak didukung! Gunakan pdf, doc, docx, xls, dll.'];
            }
        }

        $cek = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->oneArray();
        
        if (!$cek) {
            $query = $this->db('rsns_custom_logistik_non_medis_master_barang')->save($data);
        } else {
            if(isset($data['foto']) && !empty($cek['foto']) && file_exists($upload_dir . '/foto/' . $cek['foto'])) {
                unlink($upload_dir . '/foto/' . $cek['foto']);
            }
            if(isset($data['dokumen']) && !empty($cek['dokumen']) && file_exists($upload_dir . '/dokumen/' . $cek['dokumen'])) {
                unlink($upload_dir . '/dokumen/' . $cek['dokumen']);
            }
            $query = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->update($data);
        }

        if($query) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Gagal menyimpan data ke database'];
        }
    }

    public function postHapus()
    {
        $kode_item = $_POST['kode_item'] ?? '';
        $cek = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->oneArray();
        if($cek) {
            // Logging
            if ($this->core) {
                $user = $this->core->getUserInfo('username', null, true);
                $tanggal_log = date('Y-m-d H:i:s');
                $ip = $_SERVER['REMOTE_ADDR'];
                $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
                $hostname = $cek_hostname['hostname'] ?? 'Unknown';
                $log_lokasi = ''.$hostname.' | '.$ip.'';
                $logdata = ''.$cek['kode_item'].' | '.$cek['nama_barang'].' | '.$user.'';

                $this->db('mlite_tracksql')->save([
                    'log_id' => NULL,
                    'log_modul' => 'logistik_non_medis_master_barang',
                    'log_waktu' => $tanggal_log,
                    'log_location' => $log_lokasi,
                    'log_data' => $logdata,
                    'log_status' => 'D',
                    'log_username' => $user
                ]);
            }

            $upload_dir = UPLOADS . '/logistik_non_medis';
            if(!empty($cek['foto']) && file_exists($upload_dir . '/foto/' . $cek['foto'])) {
                unlink($upload_dir . '/foto/' . $cek['foto']);
            }
            if(!empty($cek['dokumen']) && file_exists($upload_dir . '/dokumen/' . $cek['dokumen'])) {
                unlink($upload_dir . '/dokumen/' . $cek['dokumen']);
            }
            $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->delete();
        }
        return ['status' => 'success'];
    }

    public function postHapusBulk()
    {
        $items = $_POST['items'] ?? [];
        if(!is_array($items)) {
            $items = json_decode($items, true);
        }
        
        if(!empty($items)) {
            $upload_dir = UPLOADS . '/logistik_non_medis';
            foreach($items as $kode_item) {
                $cek = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->oneArray();
                if($cek) {
                    if(!empty($cek['foto']) && file_exists($upload_dir . '/foto/' . $cek['foto'])) {
                        unlink($upload_dir . '/foto/' . $cek['foto']);
                    }
                    if(!empty($cek['dokumen']) && file_exists($upload_dir . '/dokumen/' . $cek['dokumen'])) {
                        unlink($upload_dir . '/dokumen/' . $cek['dokumen']);
                    }
                    $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->delete();
                }
            }
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Tidak ada item yang dipilih'];
        }
    }

    public function getExport()
    {
        $data = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
        $filename = "Data_Barang_Logistik_" . date('Ymd') . ".csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Kode Item', 'Barcode', 'Nama Barang', 'Deskripsi', 'Spesifikasi', 'Kategori', 'Sub Kategori', 'Satuan Dasar', 'Satuan Konversi', 'Harga Referensi', 'Stok Minimal', 'Stok Maksimal', 'Safety Stock', 'Status']);

        foreach ($data as $row) {
            fputcsv($output, [
                $row['kode_item'],
                $row['barcode'],
                $row['nama_barang'],
                $row['deskripsi'],
                $row['spesifikasi'],
                $row['kategori'],
                $row['sub_kategori'],
                $row['satuan_dasar'],
                $row['satuan_konversi'],
                $row['harga_referensi'],
                $row['stok_min'],
                $row['stok_max'],
                $row['safety_stock'],
                $row['status']
            ]);
        }
        fclose($output);
    }

    public function getTemplate()
    {
        $filename = "Template_Import_Barang_Logistik.csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Kode Item', 'Barcode', 'Nama Barang', 'Deskripsi', 'Spesifikasi', 'Kategori', 'Sub Kategori', 'Satuan Dasar', 'Satuan Konversi', 'Harga Referensi', 'Stok Minimal', 'Stok Maksimal', 'Safety Stock', 'Status']);
        
        // Sample row
        fputcsv($output, ['BRG0720260001', '899999912345', 'Kertas HVS A4', 'Kertas Printer', '70 gram', 'ATK', 'Kertas', 'RIM', 'PCS', '45000', '10', '100', '5', 'Aktif']);
        
        fclose($output);
    }

    public function postImport()
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
            return ['status' => 'error', 'message' => 'Gagal mengupload file.'];
        }

        $file = $_FILES['file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        if ($ext != 'csv') {
            return ['status' => 'error', 'message' => 'Format file tidak didukung. Harap gunakan format CSV.'];
        }

        $handle = fopen($file, "r");
        if ($handle !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            
            // Check basic structure
            if (count($header) < 14) {
                return ['status' => 'error', 'message' => 'Format kolom tidak sesuai dengan template.'];
            }

            $success = 0;
            $failed = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) < 14 || empty($data[0]) || empty($data[2])) {
                    $failed++;
                    continue;
                }

                $insert_data = [
                    'kode_item' => trim($data[0]),
                    'barcode' => trim($data[1]),
                    'nama_barang' => trim($data[2]),
                    'deskripsi' => trim($data[3]),
                    'spesifikasi' => trim($data[4]),
                    'kategori' => trim($data[5]),
                    'sub_kategori' => trim($data[6]),
                    'satuan_dasar' => trim($data[7]),
                    'satuan_konversi' => trim($data[8]),
                    'harga_referensi' => (double)trim($data[9]),
                    'stok_min' => (double)trim($data[10]),
                    'stok_max' => (double)trim($data[11]),
                    'safety_stock' => (double)trim($data[12]),
                    'status' => (trim($data[13]) == 'Tidak Aktif' ? 'Tidak Aktif' : 'Aktif')
                ];

                $cek = $this->db('rsns_custom_logistik_non_medis_master_barang')
                            ->where('kode_item', $insert_data['kode_item'])
                            ->oneArray();
                
                if ($cek) {
                    // Update
                    $q = $this->db('rsns_custom_logistik_non_medis_master_barang')
                              ->where('kode_item', $insert_data['kode_item'])
                              ->update($insert_data);
                } else {
                    // Insert
                    $q = $this->db('rsns_custom_logistik_non_medis_master_barang')->save($insert_data);
                }

                if ($q) {
                    $success++;
                } else {
                    $failed++;
                }
            }
            fclose($handle);
            return ['status' => 'success', 'message' => "Import selesai. Berhasil: $success, Gagal: $failed"];
        } else {
            return ['status' => 'error', 'message' => 'Tidak dapat membaca file CSV.'];
        }
    }
}
