<?php

namespace Plugins\Logistik_non_medis;

use Systems\AdminModule;

class Admin extends AdminModule
{

  public function navigation()
  {
    return [
      'Dashboard'           => 'manage',
      'Data Barang'         => 'masterbarang',
      'Data Vendor'         => 'mastervendor',
      'Data Unit'           => 'masterunit',
      'Lokasi Gudang'       => 'masterlokasi',
      'Satuan Barang'       => 'mastersatuan',
      'Kategori & Klasifikasi' => 'masterkategori',
      'Data Rekanan Jasa'   => 'masterrekanan',
      'Kode Akun (COA)'     => 'mastercoa',
      'Perencanaan'         => 'pengadaanperencanaan',
      'Permintaan (PR)'     => 'pengadaanpr',
      'Manajemen Vendor'    => 'pengadaanvendor',
      'Purchase Order (PO)' => 'pengadaanpo',
      'E-Katalog'           => 'pengadaanekatalog',
      'Penerimaan'          => 'pengadaanpenerimaan',
      'Manajemen Kontrak'   => 'pengadaankontrak',
      '--- GUDANG ---'      => 'gudangmanage',
      'Barang Masuk'        => 'gudangpenerimaan',
      'Manajemen Lokasi'    => 'gudanglokasi',
      'Pengelolaan Stok'    => 'gudangstok',
      'Stock Opname'        => 'gudangopname',
      'Metode FIFO / FEFO'  => 'gudangmetode',
      'Barang Rusak'        => 'gudangrusak',
      'Mutasi Antar Gudang' => 'gudangmutasi',
      '--- DISTRIBUSI ---'  => '#',
      'Permintaan Barang (SPPB)' => 'distribusisppb',
      'Verifikasi & Approval' => 'distribusiverifikasi',
      'Picking & Packing'   => 'distribusipacking',
      'Serah Terima Barang' => 'distribusiserahterima',
      'Tracking Pengiriman' => 'distribusitracking',
      'Retur Barang dari Unit' => 'distribusiretur',
      'Kuota & Alokasi Unit' => 'distribusikuota',
      '--- ASET ---'        => '#',
      'Registrasi Aset'     => 'asetregistrasi',
      'Kartu Inventaris (KIB)' => 'asetkib',
      'Penyusutan Aset'     => 'asetpenyusutan',
      'Pemeliharaan Aset'   => 'asetpemeliharaan',
      'Mutasi Aset'         => 'asetmutasi',
      'Penghapusan Aset'    => 'asetpenghapusan',
      'Sensus & Verifikasi Aset' => 'asetsensus',
      '--- LAPORAN & AUDIT ---' => '#',
      'Laporan Stok & Mutasi' => 'laporanstokmutasi',
      'Laporan Pengadaan'    => 'laporanpengadaan',
      'Laporan Distribusi'  => 'laporandistribusi',
      'Laporan Aset'        => 'laporanaset',
      'Dashboard & KPI'     => 'laporandashboardkpi',
      'Ekspor & Cetak Laporan' => 'laporaneksporcetak',
    ];
  }

  public function getManage()
  {
    $this->_addHeaderFiles();
    $this->_initSppb();
    $this->_initPacking();
    $this->_initKuota();
    
    $count_verif = count($this->db('rsns_custom_logistik_non_medis_sppb')->where('status', 'Disetujui Unit')->group('no_sppb')->toArray());
    $count_packing = count($this->db('rsns_custom_logistik_non_medis_sppb')->where('status', 'Terverifikasi')->orWhere('status', 'Picking')->orWhere('status', 'Packing')->group('no_sppb')->toArray());

    return $this->draw('manage.html', [
        'count_verif' => $count_verif,
        'count_packing' => $count_packing
    ]);
  }

  // --- MASTER DATA ---

  private function _initDataBarang()
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

      // Migration for existing tables
      $check = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_master_barang` LIKE 'stok_min'")->fetch();
      if (!$check) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `stok_min` double NOT NULL DEFAULT 0 AFTER `harga_referensi` ");
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `stok_max` double NOT NULL DEFAULT 0 AFTER `stok_min` ");
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `safety_stock` double NOT NULL DEFAULT 0 AFTER `stok_max` ");
      }
      
      $check_loc = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_master_barang` LIKE 'default_kode_lokasi'")->fetch();
      if (!$check_loc) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_master_barang` ADD `default_kode_lokasi` varchar(50) DEFAULT NULL AFTER `dokumen` ");
      }

      $upload_dir = UPLOADS . '/logistik_non_medis';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      if (!is_dir($upload_dir . '/foto')) mkdir($upload_dir . '/foto', 0777, true);
      if (!is_dir($upload_dir . '/dokumen')) mkdir($upload_dir . '/dokumen', 0777, true);
  }

  private function _generateKodeBarang()
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

  public function getMasterBarang()
  {
    $this->_initDataBarang();
    $this->_addHeaderFiles();
    return $this->draw('master.barang.html');
  }

  public function anyDisplayMasterBarang()
  {
      $this->_initDataBarang();
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

      echo $this->draw('master.barang.display.html', [
          'barang' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyDetailMasterBarang()
  {
      if (isset($_POST['kode_item'])){
          $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $_POST['kode_item'])->oneArray();
          echo $this->draw('master.barang.detail.html', ['barang' => $barang]);
      }
      exit();
  }

  public function anyFormMasterBarang()
  {
      $this->_initKategori();
      $this->_initSatuan();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();
      $satuan = $this->db('rsns_custom_logistik_non_medis_satuan')->toArray();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      if (isset($_POST['kode_item'])){
          $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $_POST['kode_item'])->oneArray();
          echo $this->draw('master.barang.form.html', ['barang' => $barang, 'mode' => 'edit', 'kategori' => $kategori, 'satuan' => $satuan, 'lokasi' => $lokasi]);
      } else {
          $barang = [
              'kode_item' => $this->_generateKodeBarang(),
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
          echo $this->draw('master.barang.form.html', ['barang' => $barang, 'mode' => 'add', 'kategori' => $kategori, 'satuan' => $satuan, 'lokasi' => $lokasi]);
      }
      exit();
  }

  public function postSaveMasterBarang()
  {
      $kode_item = $_POST['kode_item'] ?? '';
      if(empty($kode_item)) {
          echo json_encode(['status' => 'error', 'message' => 'Kode Item wajib diisi!']);
          exit();
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
                  echo json_encode(['status' => 'error', 'message' => 'Gagal mengupload foto ke server.']);
                  exit();
              }
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Format Foto tidak didukung! Gunakan jpg, jpeg, png, atau gif.']);
              exit();
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
                  echo json_encode(['status' => 'error', 'message' => 'Gagal mengupload dokumen ke server.']);
                  exit();
              }
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Format Dokumen tidak didukung! Gunakan pdf, doc, docx, xls, dll.']);
              exit();
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
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data ke database']);
      }
      exit();
  }

  public function postHapusMasterBarang()
  {
      $kode_item = $_POST['kode_item'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->oneArray();
      if($cek) {
          // Logging
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

          $upload_dir = UPLOADS . '/logistik_non_medis';
          if(!empty($cek['foto']) && file_exists($upload_dir . '/foto/' . $cek['foto'])) {
              unlink($upload_dir . '/foto/' . $cek['foto']);
          }
          if(!empty($cek['dokumen']) && file_exists($upload_dir . '/dokumen/' . $cek['dokumen'])) {
              unlink($upload_dir . '/dokumen/' . $cek['dokumen']);
          }
          $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $kode_item)->delete();
      }
      exit();
  }

  private function _initVendor()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_vendor` (
        `kode_vendor` varchar(50) NOT NULL,
        `nama_vendor` varchar(200) NOT NULL,
        `alamat` text DEFAULT NULL,
        `no_telp` varchar(50) DEFAULT NULL,
        `email` varchar(100) DEFAULT NULL,
        `website` varchar(100) DEFAULT NULL,
        `npwp` varchar(50) DEFAULT NULL,
        `siup` varchar(50) DEFAULT NULL,
        `status_pkp` enum('PKP','Non PKP') NOT NULL DEFAULT 'Non PKP',
        `nama_bank` varchar(100) DEFAULT NULL,
        `no_rekening` varchar(50) DEFAULT NULL,
        `nama_rekening` varchar(100) DEFAULT NULL,
        `pic_nama` varchar(100) DEFAULT NULL,
        `pic_kontak` varchar(50) DEFAULT NULL,
        `kategori_vendor` varchar(255) DEFAULT NULL,
        `rating` int(1) NOT NULL DEFAULT 0,
        `evaluasi` text DEFAULT NULL,
        `status` enum('Whitelist','Blacklist') NOT NULL DEFAULT 'Whitelist',
        `file_npwp` varchar(255) DEFAULT NULL,
        `file_siup` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`kode_vendor`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $upload_dir = UPLOADS . '/logistik_non_medis/vendor';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  private function _generateKodeVendor()
  {
      $prefix = 'VND' . date('Y');
      $last = $this->db('rsns_custom_logistik_non_medis_vendor')
                   ->where('kode_vendor', 'LIKE', $prefix.'%')
                   ->desc('kode_vendor')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['kode_vendor'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getMasterVendor()
  {
    $this->_initVendor();
    $this->_addHeaderFiles();
    return $this->draw('master.vendor.html');
  }

  public function anyDisplayMasterVendor()
  {
      $this->_initVendor();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_vendor');
      if(!empty($cari)) {
          $query->where('kode_vendor', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_vendor', '%'.$cari.'%');
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_vendor');
      if(!empty($cari)) {
          $rows->where('kode_vendor', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_vendor', '%'.$cari.'%');
      }
      $rows = $rows->desc('kode_vendor')
                    ->offset($_offset)
                    ->limit($perpage)
                    ->toArray();

      echo $this->draw('master.vendor.display.html', [
          'vendor' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyDetailMasterVendor()
  {
      if (isset($_POST['kode_vendor'])){
          $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $_POST['kode_vendor'])->oneArray();
          echo $this->draw('master.vendor.detail.html', ['vendor' => $vendor]);
      }
      exit();
  }

  public function anyFormMasterVendor()
  {
      $this->_initKategori();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->toArray();

      if (isset($_POST['kode_vendor'])){
          $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $_POST['kode_vendor'])->oneArray();
          echo $this->draw('master.vendor.form.html', ['vendor' => $vendor, 'mode' => 'edit', 'kategori' => $kategori]);
      } else {
          $vendor = [
              'kode_vendor' => $this->_generateKodeVendor(),
              'nama_vendor' => '',
              'alamat' => '',
              'no_telp' => '',
              'email' => '',
              'website' => '',
              'npwp' => '',
              'siup' => '',
              'status_pkp' => 'Non PKP',
              'nama_bank' => '',
              'no_rekening' => '',
              'nama_rekening' => '',
              'pic_nama' => '',
              'pic_kontak' => '',
              'kategori_vendor' => '',
              'rating' => '0',
              'evaluasi' => '',
              'status' => 'Whitelist',
              'file_npwp' => '',
              'file_siup' => ''
          ];
          echo $this->draw('master.vendor.form.html', ['vendor' => $vendor, 'mode' => 'add', 'kategori' => $kategori]);
      }
      exit();
  }

  public function postSaveMasterVendor()
  {
      $kode_vendor = $_POST['kode_vendor'] ?? '';
      if(empty($kode_vendor)) {
          echo json_encode(['status' => 'error', 'message' => 'Kode Vendor wajib diisi!']);
          exit();
      }

      $data = [
          'kode_vendor' => $kode_vendor,
          'nama_vendor' => $_POST['nama_vendor'] ?? '',
          'alamat' => $_POST['alamat'] ?? '',
          'no_telp' => $_POST['no_telp'] ?? '',
          'email' => $_POST['email'] ?? '',
          'website' => $_POST['website'] ?? '',
          'npwp' => $_POST['npwp'] ?? '',
          'siup' => $_POST['siup'] ?? '',
          'status_pkp' => $_POST['status_pkp'] ?? 'Non PKP',
          'nama_bank' => $_POST['nama_bank'] ?? '',
          'no_rekening' => $_POST['no_rekening'] ?? '',
          'nama_rekening' => $_POST['nama_rekening'] ?? '',
          'pic_nama' => $_POST['pic_nama'] ?? '',
          'pic_kontak' => $_POST['pic_kontak'] ?? '',
          'kategori_vendor' => (isset($_POST['kategori_vendor']) && is_array($_POST['kategori_vendor'])) ? implode(',', $_POST['kategori_vendor']) : ($_POST['kategori_vendor'] ?? ''),
          'rating' => $_POST['rating'] ?? 0,
          'evaluasi' => $_POST['evaluasi'] ?? '',
          'status' => $_POST['status'] ?? 'Whitelist'
      ];

      // Logging
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_log = date('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'];
      $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
      $hostname = $cek_hostname['hostname'] ?? 'Unknown';
      $log_lokasi = ''.$hostname.' | '.$ip.'';
      $logdata = ''.$data['kode_vendor'].' | '.$data['nama_vendor'].' | '.$data['alamat'].' | '.$data['no_telp'].' | '.$data['email'].' | '.$data['website'].' | '.$data['npwp'].' | '.$data['siup'].' | '.$data['status_pkp'].' | '.$data['nama_bank'].' | '.$data['no_rekening'].' | '.$data['nama_rekening'].' | '.$data['pic_nama'].' | '.$data['pic_kontak'].' | '.$data['kategori_vendor'].' | '.$data['rating'].' | '.$data['evaluasi'].' | '.$data['status'].' | '.$user.'';

      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_master_vendor',
          'log_waktu' => $tanggal_log,
          'log_location' => $log_lokasi,
          'log_data' => $logdata,
          'log_status' => (isset($_POST['kode_vendor']) && $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $_POST['kode_vendor'])->oneArray()) ? 'U' : 'I',
          'log_username' => $user
      ]);

      $upload_dir = UPLOADS . '/logistik_non_medis/vendor';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

      // Handle File Uploads
      $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
      $upload_path = BASE_DIR . '/uploads/logistik_non_medis/vendor';
      if (!is_dir($upload_path)) mkdir($upload_path, 0777, true);
      
      if(isset($_FILES['file_npwp']) && $_FILES['file_npwp']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['file_npwp']['name'], PATHINFO_EXTENSION));
          if(in_array($ext, $allowed_ext)) {
              $filename = 'npwp_' . $kode_vendor . '_' . time() . '.' . $ext;
              if(move_uploaded_file($_FILES['file_npwp']['tmp_name'], $upload_path . '/' . $filename)) {
                  $data['file_npwp'] = $filename;
              } else {
                  echo json_encode(['status' => 'error', 'message' => 'Gagal memindahkan file NPWP ke folder tujuan. Periksa izin folder.']);
                  exit();
              }
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Format file NPWP tidak didukung!']);
              exit();
          }
      }

      if(isset($_FILES['file_siup']) && $_FILES['file_siup']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['file_siup']['name'], PATHINFO_EXTENSION));
          if(in_array($ext, $allowed_ext)) {
              $filename = 'siup_' . $kode_vendor . '_' . time() . '.' . $ext;
              if(move_uploaded_file($_FILES['file_siup']['tmp_name'], $upload_path . '/' . $filename)) {
                  $data['file_siup'] = $filename;
              } else {
                  echo json_encode(['status' => 'error', 'message' => 'Gagal memindahkan file SIUP ke folder tujuan.']);
                  exit();
              }
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Format file SIUP tidak didukung!']);
              exit();
          }
      }

      $cek = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $kode_vendor)->oneArray();
      
      if (!$cek) {
          $query = $this->db('rsns_custom_logistik_non_medis_vendor')->save($data);
      } else {
          // Cleanup old files if new ones uploaded
          if(isset($data['file_npwp']) && !empty($cek['file_npwp']) && file_exists($upload_dir . '/' . $cek['file_npwp'])) {
              unlink($upload_dir . '/' . $cek['file_npwp']);
          }
          if(isset($data['file_siup']) && !empty($cek['file_siup']) && file_exists($upload_dir . '/' . $cek['file_siup'])) {
              unlink($upload_dir . '/' . $cek['file_siup']);
          }
          $query = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $kode_vendor)->update($data);
      }

      if($query) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data ke database']);
      }
      exit();
  }

  public function postHapusMasterVendor()
  {
      $kode_vendor = $_POST['kode_vendor'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $kode_vendor)->oneArray();
      if($cek) {
          // Logging
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'];
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$cek['kode_vendor'].' | '.$cek['nama_vendor'].' | '.$cek['alamat'].' | '.$cek['no_telp'].' | '.$cek['email'].' | '.$cek['website'].' | '.$cek['npwp'].' | '.$cek['siup'].' | '.$cek['status_pkp'].' | '.$cek['nama_bank'].' | '.$cek['no_rekening'].' | '.$cek['nama_rekening'].' | '.$cek['pic_nama'].' | '.$cek['pic_kontak'].' | '.$cek['kategori_vendor'].' | '.$cek['rating'].' | '.$cek['evaluasi'].' | '.$cek['status'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_master_vendor',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          $upload_dir = UPLOADS . '/logistik_non_medis/vendor';
          if(!empty($cek['file_npwp']) && file_exists($upload_dir . '/' . $cek['file_npwp'])) {
              unlink($upload_dir . '/' . $cek['file_npwp']);
          }
          if(!empty($cek['file_siup']) && file_exists($upload_dir . '/' . $cek['file_siup'])) {
              unlink($upload_dir . '/' . $cek['file_siup']);
          }
          $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $kode_vendor)->delete();
      }
      exit();
  }

  private function _initPerencanaan()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_perencanaan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_perencanaan` varchar(50) NOT NULL,
        `kode_unit` varchar(50) NOT NULL,
        `tahun` year(4) NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `jan` double NOT NULL DEFAULT 0,
        `feb` double NOT NULL DEFAULT 0,
        `mar` double NOT NULL DEFAULT 0,
        `apr` double NOT NULL DEFAULT 0,
        `mei` double NOT NULL DEFAULT 0,
        `jun` double NOT NULL DEFAULT 0,
        `jul` double NOT NULL DEFAULT 0,
        `agu` double NOT NULL DEFAULT 0,
        `sep` double NOT NULL DEFAULT 0,
        `okt` double NOT NULL DEFAULT 0,
        `nov` double NOT NULL DEFAULT 0,
        `des` double NOT NULL DEFAULT 0,
        `total_qty` double NOT NULL DEFAULT 0,
        `harga_referensi` double NOT NULL DEFAULT 0,
        `prioritas` varchar(50) DEFAULT 'Desirable',
        `status` enum('Draft','Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft',
        `tgl_input` datetime DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _generateKodePerencanaan($kode_unit, $tahun)
  {
      return 'RKBU/' . $tahun . '/' . $kode_unit;
  }

  private function _initUnit()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_unit` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_unit` varchar(50) NOT NULL,
        `nama_unit` varchar(200) NOT NULL,
        `parent_id` int(11) DEFAULT 0,
        `pj_unit` varchar(100) DEFAULT NULL,
        `gedung` varchar(100) DEFAULT NULL,
        `lantai` varchar(50) DEFAULT NULL,
        `lokasi_detail` text DEFAULT NULL,
        `kuota_periode` double NOT NULL DEFAULT 0,
        `kode_cost_center` varchar(50) DEFAULT NULL,
        `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
        PRIMARY KEY (`id`),
        UNIQUE KEY `kode_unit` (`kode_unit`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _generateKodeUnit()
  {
      $prefix = 'UNT-' . date('Ym');
      $last = $this->db('rsns_custom_logistik_non_medis_unit')
                   ->where('kode_unit', 'LIKE', $prefix.'%')
                   ->desc('kode_unit')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['kode_unit'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getMasterUnit()
  {
    $this->_initUnit();
    $this->_addHeaderFiles();
    return $this->draw('master.unit.html');
  }

  public function anyDetailMasterUnit()
  {
      if (isset($_POST['id'])){
          $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('id', $_POST['id'])->oneArray();
          // Get parent name
          $parent = ['nama_unit' => '-'];
          if ($unit['parent_id'] > 0) {
              $parent = $this->db('rsns_custom_logistik_non_medis_unit')->where('id', $unit['parent_id'])->oneArray();
          }
          echo $this->draw('master.unit.detail.html', ['unit' => $unit, 'parent' => $parent]);
      }
      exit();
  }

  public function anyDisplayMasterUnit()
  {
      $this->_initUnit();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_unit');
      if(!empty($cari)) {
          $query->where('kode_unit', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_unit', '%'.$cari.'%')
                ->orLike('pj_unit', '%'.$cari.'%');
      }
      
      $rows = $query->desc('id')->toArray();
      $jumlah_data = count($rows);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows_paged = array_slice($rows, $_offset, $perpage);

      echo $this->draw('master.unit.display.html', [
          'unit' => $rows_paged,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormMasterUnit()
  {
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();

      if (isset($_POST['id'])){
          $unit = $this->db('rsns_custom_logistik_non_medis_unit')->where('id', $_POST['id'])->oneArray();
          echo $this->draw('master.unit.form.html', ['unit' => $unit, 'mode' => 'edit', 'units' => $units]);
      } else {
          $unit = [
              'id' => '',
              'kode_unit' => $this->_generateKodeUnit(),
              'nama_unit' => '',
              'parent_id' => '0',
              'pj_unit' => '',
              'gedung' => '',
              'lantai' => '',
              'lokasi_detail' => '',
              'kuota_periode' => '0',
              'kode_cost_center' => '',
              'status' => 'Aktif'
          ];
          echo $this->draw('master.unit.form.html', ['unit' => $unit, 'mode' => 'add', 'units' => $units]);
      }
      exit();
  }

  public function postSaveMasterUnit()
  {
      $kode_unit = $_POST['kode_unit'] ?? '';
      if(empty($kode_unit)) {
          echo json_encode(['status' => 'error', 'message' => 'Kode Unit wajib diisi!']);
          exit();
      }

      $data = [
          'kode_unit' => $kode_unit,
          'nama_unit' => $_POST['nama_unit'] ?? '',
          'parent_id' => $_POST['parent_id'] ?? 0,
          'pj_unit' => $_POST['pj_unit'] ?? '',
          'gedung' => $_POST['gedung'] ?? '',
          'lantai' => $_POST['lantai'] ?? '',
          'lokasi_detail' => $_POST['lokasi_detail'] ?? '',
          'kuota_periode' => $_POST['kuota_periode'] ?? 0,
          'kode_cost_center' => $_POST['kode_cost_center'] ?? '',
          'status' => $_POST['status'] ?? 'Aktif'
      ];

      // Logging
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_log = date('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'];
      $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
      $hostname = $cek_hostname['hostname'] ?? 'Unknown';
      $log_lokasi = ''.$hostname.' | '.$ip.'';
      $logdata = ''.$data['kode_unit'].' | '.$data['nama_unit'].' | '.$user.'';

      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_unit',
          'log_waktu' => $tanggal_log,
          'log_location' => $log_lokasi,
          'log_data' => $logdata,
          'log_status' => (isset($_POST['id']) && !empty($_POST['id'])) ? 'U' : 'I',
          'log_username' => $user
      ]);

      if (isset($_POST['id']) && !empty($_POST['id'])) {
          $query = $this->db('rsns_custom_logistik_non_medis_unit')->where('id', $_POST['id'])->update($data);
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_unit')->save($data);
      }

      if($query) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function postHapusMasterUnit()
  {
      $id = $_POST['id'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_unit')->where('id', $id)->oneArray();
      if($cek) {
          // Logging
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'];
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$cek['kode_unit'].' | '.$cek['nama_unit'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_unit',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          $this->db('rsns_custom_logistik_non_medis_unit')->where('id', $id)->delete();
      }
      exit();
  }

  private function _initLokasi()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_lokasi_gudang` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_lokasi` varchar(50) NOT NULL,
        `nama_lokasi` varchar(100) NOT NULL,
        `kode_zona` varchar(50) DEFAULT NULL,
        `rak` varchar(50) DEFAULT NULL,
        `bin` varchar(50) DEFAULT NULL,
        `slot` varchar(50) DEFAULT NULL,
        `kapasitas` double NOT NULL DEFAULT 0,
        `satuan_kapasitas` varchar(50) DEFAULT NULL,
        `tipe_penyimpanan` varchar(100) DEFAULT NULL,
        `suhu_min` double DEFAULT NULL,
        `suhu_max` double DEFAULT NULL,
        `berat_max` double DEFAULT NULL,
        `denah_digital` varchar(255) DEFAULT NULL,
        `is_fragile` tinyint(1) NOT NULL DEFAULT 0,
        `status` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
        PRIMARY KEY (`id`),
        UNIQUE KEY `kode_lokasi` (`kode_lokasi`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $check_fragile = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_lokasi_gudang` LIKE 'is_fragile'")->fetch();
      if (!$check_fragile) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_lokasi_gudang` ADD `is_fragile` tinyint(1) NOT NULL DEFAULT 0 AFTER `denah_digital` ");
      }

      $upload_dir = UPLOADS . '/logistik_non_medis/lokasi';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  private function _generateKodeLokasi()
  {
      $prefix = 'LOC-' . date('Y');
      $last = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')
                   ->where('kode_lokasi', 'LIKE', $prefix.'%')
                   ->desc('kode_lokasi')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['kode_lokasi'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getMasterLokasi()
  {
    $this->_initLokasi();
    $this->_addHeaderFiles();
    return $this->draw('master.lokasi.html');
  }

  public function anyDisplayMasterLokasi()
  {
      $this->_initLokasi();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang');
      if(!empty($cari)) {
          $query->where('kode_lokasi', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_lokasi', '%'.$cari.'%')
                ->orLike('kode_zona', '%'.$cari.'%');
      }
      
      $rows = $query->desc('id')->toArray();
      $jumlah_data = count($rows);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows_paged = array_slice($rows, $_offset, $perpage);

      echo $this->draw('master.lokasi.display.html', [
          'lokasi' => $rows_paged,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormMasterLokasi()
  {
      if (isset($_POST['id'])){
          $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('id', $_POST['id'])->oneArray();
          echo $this->draw('master.lokasi.form.html', ['lokasi' => $lokasi, 'mode' => 'edit']);
      } else {
          $lokasi = [
              'id' => '',
              'kode_lokasi' => $this->_generateKodeLokasi(),
              'nama_lokasi' => '',
              'kode_zona' => '',
              'rak' => '',
              'bin' => '',
              'slot' => '',
              'kapasitas' => '0',
              'satuan_kapasitas' => 'Unit',
              'tipe_penyimpanan' => 'Normal',
              'suhu_min' => '',
              'suhu_max' => '',
              'berat_max' => '',
              'denah_digital' => '',
              'status' => 'Aktif'
          ];
          echo $this->draw('master.lokasi.form.html', ['lokasi' => $lokasi, 'mode' => 'add']);
      }
      exit();
  }

  public function postSaveMasterLokasi()
  {
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      if(empty($kode_lokasi)) {
          echo json_encode(['status' => 'error', 'message' => 'Kode Lokasi wajib diisi!']);
          exit();
      }

      $data = [
          'kode_lokasi' => $kode_lokasi,
          'nama_lokasi' => $_POST['nama_lokasi'] ?? '',
          'kode_zona' => $_POST['kode_zona'] ?? '',
          'rak' => $_POST['rak'] ?? '',
          'bin' => $_POST['bin'] ?? '',
          'slot' => $_POST['slot'] ?? '',
          'kapasitas' => $_POST['kapasitas'] ?? 0,
          'satuan_kapasitas' => $_POST['satuan_kapasitas'] ?? '',
          'tipe_penyimpanan' => $_POST['tipe_penyimpanan'] ?? '',
          'suhu_min' => $_POST['suhu_min'] ?: NULL,
          'suhu_max' => $_POST['suhu_max'] ?: NULL,
          'berat_max' => $_POST['berat_max'] ?: NULL,
          'is_fragile' => isset($_POST['is_fragile']) ? 1 : 0,
          'status' => $_POST['status'] ?? 'Aktif'
      ];

      // Logging
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_log = date('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'];
      $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
      $hostname = $cek_hostname['hostname'] ?? 'Unknown';
      $log_lokasi = ''.$hostname.' | '.$ip.'';
      $logdata = ''.$data['kode_lokasi'].' | '.$data['nama_lokasi'].' | '.$user.'';

      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_lokasi_gudang',
          'log_waktu' => $tanggal_log,
          'log_location' => $log_lokasi,
          'log_data' => $logdata,
          'log_status' => (isset($_POST['id']) && !empty($_POST['id'])) ? 'U' : 'I',
          'log_username' => $user
      ]);

      $upload_dir = UPLOADS . '/logistik_non_medis/lokasi';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

      // Handle File Upload
      if(isset($_FILES['denah_digital']) && $_FILES['denah_digital']['error'] == 0) {
          $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
          $ext = strtolower(pathinfo($_FILES['denah_digital']['name'], PATHINFO_EXTENSION));
          if(in_array($ext, $allowed)) {
              $filename = 'denah_' . $kode_lokasi . '_' . time() . '.' . $ext;
              if(move_uploaded_file($_FILES['denah_digital']['tmp_name'], $upload_dir . '/' . $filename)) {
                  $data['denah_digital'] = $filename;
              }
          }
      }

      if (isset($_POST['id']) && !empty($_POST['id'])) {
          $cek = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('id', $_POST['id'])->oneArray();
          if(isset($data['denah_digital']) && !empty($cek['denah_digital']) && file_exists($upload_dir . '/' . $cek['denah_digital'])) {
              unlink($upload_dir . '/' . $cek['denah_digital']);
          }
          $query = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('id', $_POST['id'])->update($data);
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->save($data);
      }

      if($query) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function postHapusMasterLokasi()
  {
      $id = $_POST['id'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('id', $id)->oneArray();
      if($cek) {
          // Logging
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'];
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$cek['kode_lokasi'].' | '.$cek['nama_lokasi'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_lokasi_gudang',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          $upload_dir = UPLOADS . '/logistik_non_medis/lokasi';
          if(!empty($cek['denah_digital']) && file_exists($upload_dir . '/' . $cek['denah_digital'])) {
              unlink($upload_dir . '/' . $cek['denah_digital']);
          }
          $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('id', $id)->delete();
      }
      exit();
  }


  private function _initSatuan()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_satuan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_satuan` varchar(50) NOT NULL,
        `nama_satuan` varchar(100) NOT NULL,
        `satuan_dasar` varchar(50) DEFAULT NULL,
        `nilai_konversi` double NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `kode_satuan` (`kode_satuan`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  public function getMasterSatuan()
  {
    $this->_initSatuan();
    $this->_addHeaderFiles();
    return $this->draw('master.satuan.html');
  }

  public function anyDisplayMasterSatuan()
  {
      $this->_initSatuan();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_satuan');
      if(!empty($cari)) {
          $query->where('kode_satuan', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_satuan', '%'.$cari.'%');
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_satuan');
      if(!empty($cari)) {
          $rows->where('kode_satuan', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_satuan', '%'.$cari.'%');
      }
      $rows = $rows->desc('id')
                    ->offset($_offset)
                    ->limit($perpage)
                    ->toArray();

      echo $this->draw('master.satuan.display.html', [
          'satuan' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormMasterSatuan()
  {
      if (isset($_POST['id'])){
          $satuan = $this->db('rsns_custom_logistik_non_medis_satuan')->where('id', $_POST['id'])->oneArray();
          echo $this->draw('master.satuan.form.html', ['satuan' => $satuan]);
      } else {
          $satuan = [
              'id' => '',
              'kode_satuan' => '',
              'nama_satuan' => '',
              'satuan_dasar' => '',
              'nilai_konversi' => '1'
          ];
          echo $this->draw('master.satuan.form.html', ['satuan' => $satuan]);
      }
      exit();
  }

  public function postSaveMasterSatuan()
  {
      $kode_satuan = $_POST['kode_satuan'] ?? '';
      if(empty($kode_satuan)) {
          echo json_encode(['status' => 'error', 'message' => 'Kode Satuan wajib diisi!']);
          exit();
      }

      $data = [
          'kode_satuan' => $kode_satuan,
          'nama_satuan' => $_POST['nama_satuan'] ?? '',
          'satuan_dasar' => $_POST['satuan_dasar'] ?? '',
          'nilai_konversi' => $_POST['nilai_konversi'] ?? 1
      ];

      // Logging
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_log = date('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'];
      $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
      $hostname = $cek_hostname['hostname'] ?? 'Unknown';
      $log_lokasi = ''.$hostname.' | '.$ip.'';
      $logdata = ''.$data['kode_satuan'].' | '.$data['nama_satuan'].' | '.$data['satuan_dasar'].' | '.$data['nilai_konversi'].' | '.$user.'';

      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_satuan',
          'log_waktu' => $tanggal_log,
          'log_location' => $log_lokasi,
          'log_data' => $logdata,
          'log_status' => (isset($_POST['id']) && !empty($_POST['id'])) ? 'U' : 'I',
          'log_username' => $user
      ]);

      if (isset($_POST['id']) && !empty($_POST['id'])) {
          $query = $this->db('rsns_custom_logistik_non_medis_satuan')->where('id', $_POST['id'])->update($data);
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_satuan')->save($data);
      }

      if($query) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function postHapusMasterSatuan()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          $cek = $this->db('rsns_custom_logistik_non_medis_satuan')->where('id', $id)->oneArray();
          if($cek) {
              // Logging
              $user = $this->core->getUserInfo('username', null, true);
              $tanggal_log = date('Y-m-d H:i:s');
              $ip = $_SERVER['REMOTE_ADDR'];
              $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
              $hostname = $cek_hostname['hostname'] ?? 'Unknown';
              $log_lokasi = ''.$hostname.' | '.$ip.'';
              $logdata = ''.$cek['kode_satuan'].' | '.$cek['nama_satuan'].' | '.$user.'';

              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_satuan',
                  'log_waktu' => $tanggal_log,
                  'log_location' => $log_lokasi,
                  'log_data' => $logdata,
                  'log_status' => 'D',
                  'log_username' => $user
              ]);

              $this->db('rsns_custom_logistik_non_medis_satuan')->where('id', $id)->delete();
          }
      }
      exit();
  }


  private function _initKategori()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_kategori` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nama_kategori` varchar(200) NOT NULL,
        `deskripsi` text DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $default_categories = [
          ['nama_kategori' => 'ATK & Percetakan', 'deskripsi' => 'Kertas, buku, alat tulis, formulir rekam medis, dan kebutuhan cetak.'],
          ['nama_kategori' => 'Rumah Tangga & Kebersihan', 'deskripsi' => 'Sabun, deterjen, tissue, alat pel, dan perlengkapan sanitasi.'],
          ['nama_kategori' => 'Elektronik & IT', 'deskripsi' => 'Komputer, printer, tinta, perangkat jaringan, dan peralatan elektronik.'],
          ['nama_kategori' => 'Bahan Bakar & Pelumas', 'deskripsi' => 'Solar genset, bensin, oli mesin, dan pelumas peralatan.'],
          ['nama_kategori' => 'Kendaraan & Suku Cadang', 'deskripsi' => 'Ban, filter, aki, dan suku cadang armada ambulans/operasional.'],
          ['nama_kategori' => 'Perlengkapan Kantor', 'deskripsi' => 'Meja, kursi, lemari arsip, dan furnitur kantor lainnya.'],
          ['nama_kategori' => 'Linen & Perlengkapan Pasien', 'deskripsi' => 'Sprei, sarung bantal, selimut, handuk, dan kain gorden.'],
          ['nama_kategori' => 'Alat Listrik & Penerangan', 'deskripsi' => 'Lampu, kabel, saklar, baterai, dan komponen kelistrikan.'],
          ['nama_kategori' => 'Bahan Bangunan & Pemeliharaan', 'deskripsi' => 'Cat, semen, kunci pintu, kran air, dan material pemeliharaan gedung.'],
          ['nama_kategori' => 'Gas Medis & Oksigen', 'deskripsi' => 'Tabung O2, N2O, CO2, serta regulator dan aksesoris gas medis.'],
          ['nama_kategori' => 'Bahan Makanan & Gizi', 'deskripsi' => 'Beras, minyak goreng, bumbu dapur, dan bahan makanan kering.'],
          ['nama_kategori' => 'Seragam & Atribut Karyawan', 'deskripsi' => 'Baju dinas, sepatu kerja, name tag, dan atribut seragam lainnya.']
      ];

      foreach ($default_categories as $cat) {
          $cek = $this->db('rsns_custom_logistik_non_medis_kategori')->where('nama_kategori', $cat['nama_kategori'])->oneArray();
          if (!$cek) {
              $this->db('rsns_custom_logistik_non_medis_kategori')->save($cat);
          } else {
              $this->db('rsns_custom_logistik_non_medis_kategori')->where('nama_kategori', $cat['nama_kategori'])->update(['deskripsi' => $cat['deskripsi']]);
          }
      }
  }

  public function getMasterKategori()
  {
    $this->_initKategori();
    $this->_addHeaderFiles();
    return $this->draw('master.kategori.html');
  }

  public function anyDisplayMasterKategori()
  {
      $this->_initKategori();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_kategori');
      if(!empty($cari)) {
          $query->where('nama_kategori', 'LIKE', '%'.$cari.'%')
                ->orLike('deskripsi', '%'.$cari.'%');
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_kategori');
      if(!empty($cari)) {
          $rows->where('nama_kategori', 'LIKE', '%'.$cari.'%')
                ->orLike('deskripsi', '%'.$cari.'%');
      }
      $rows = $rows->desc('id')
                    ->offset($_offset)
                    ->limit($perpage)
                    ->toArray();

      echo $this->draw('master.kategori.display.html', [
          'kategori' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormMasterKategori()
  {
      if (isset($_POST['id'])){
          $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->where('id', $_POST['id'])->oneArray();
          echo $this->draw('master.kategori.form.html', ['kategori' => $kategori]);
      } else {
          $kategori = [
              'id' => '',
              'nama_kategori' => '',
              'deskripsi' => ''
          ];
          echo $this->draw('master.kategori.form.html', ['kategori' => $kategori]);
      }
      exit();
  }

  public function postSaveMasterKategori()
  {
      $nama_kategori = $_POST['nama_kategori'] ?? '';
      if(empty($nama_kategori)) {
          echo json_encode(['status' => 'error', 'message' => 'Nama Kategori wajib diisi!']);
          exit();
      }

      $data = [
          'nama_kategori' => $nama_kategori,
          'deskripsi' => $_POST['deskripsi'] ?? ''
      ];

      // Logging
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_log = date('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'];
      $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
      $hostname = $cek_hostname['hostname'] ?? 'Unknown';
      $log_lokasi = ''.$hostname.' | '.$ip.'';
      $logdata = ''.$data['nama_kategori'].' | '.$data['deskripsi'].' | '.$user.'';

      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_kategori',
          'log_waktu' => $tanggal_log,
          'log_location' => $log_lokasi,
          'log_data' => $logdata,
          'log_status' => (isset($_POST['id']) && !empty($_POST['id'])) ? 'U' : 'I',
          'log_username' => $user
      ]);

      if (isset($_POST['id']) && !empty($_POST['id'])) {
          $query = $this->db('rsns_custom_logistik_non_medis_kategori')->where('id', $_POST['id'])->update($data);
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_kategori')->save($data);
      }

      if($query) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function postHapusMasterKategori()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          $cek = $this->db('rsns_custom_logistik_non_medis_kategori')->where('id', $id)->oneArray();
          if($cek) {
              // Logging
              $user = $this->core->getUserInfo('username', null, true);
              $tanggal_log = date('Y-m-d H:i:s');
              $ip = $_SERVER['REMOTE_ADDR'];
              $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
              $hostname = $cek_hostname['hostname'] ?? 'Unknown';
              $log_lokasi = ''.$hostname.' | '.$ip.'';
              $logdata = ''.$cek['nama_kategori'].' | '.$user.'';

              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_kategori',
                  'log_waktu' => $tanggal_log,
                  'log_location' => $log_lokasi,
                  'log_data' => $logdata,
                  'log_status' => 'D',
                  'log_username' => $user
              ]);

              $this->db('rsns_custom_logistik_non_medis_kategori')->where('id', $id)->delete();
          }
      }
      exit();
  }

  private function _initRekananJasa()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_rekanan_jasa` (
        `kode_rekanan` varchar(50) NOT NULL,
        `nama_rekanan` varchar(200) NOT NULL,
        `kategori` enum('Vendor Servis','Kontraktor') DEFAULT 'Vendor Servis',
        `alamat` text,
        `no_telp` varchar(50),
        `pic` varchar(100),
        `jenis_layanan` varchar(255),
        `frekuensi` varchar(100),
        `tgl_servis_terakhir` date,
        `tgl_servis_berikutnya` date,
        `nomor_kontrak` varchar(100),
        `tgl_mulai_kontrak` date,
        `tgl_selesai_kontrak` date,
        `nilai_kontrak` double DEFAULT 0,
        `file_kontrak` varchar(255),
        `status` enum('Aktif','Non-Aktif') DEFAULT 'Aktif',
        PRIMARY KEY (`kode_rekanan`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      // Robust check for new columns
      $columns = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_rekanan_jasa`")->fetchAll(\PDO::FETCH_COLUMN);
      
      if (!in_array('email', $columns)) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_rekanan_jasa` ADD `email` varchar(100) AFTER `no_telp` ");
      }
      if (!in_array('website', $columns)) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_rekanan_jasa` ADD `website` varchar(150) AFTER `email` ");
      }
      if (!in_array('pic_kontak', $columns)) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_rekanan_jasa` ADD `pic_kontak` varchar(50) AFTER `pic` ");
      }

      $upload_dir = UPLOADS . '/logistik_non_medis/rekanan_jasa';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  private function _initPR()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_pr` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_pr` varchar(50) NOT NULL,
        `tgl_pr` date NOT NULL,
        `kode_unit` varchar(50) NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `jumlah` double NOT NULL DEFAULT 0,
        `satuan` varchar(50) NOT NULL,
        `justifikasi` text DEFAULT NULL,
        `file_justifikasi` varchar(255) DEFAULT NULL,
        `status` enum('Draft','Diajukan','Disetujui','Ditolak','Selesai') NOT NULL DEFAULT 'Draft',
        `petugas_logistik` varchar(100) DEFAULT NULL,
        `tgl_acc` datetime DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $upload_dir = UPLOADS . '/logistik_non_medis/pr';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  private function _initVendorManajemen()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_vendor_evaluasi` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `kode_vendor` varchar(50) NOT NULL,
        `jenis_record` enum('Evaluasi','Kontrak','Seleksi') NOT NULL DEFAULT 'Evaluasi',
        `tgl_record` date NOT NULL,
        `nomor_dokumen` varchar(100) DEFAULT NULL,
        `tgl_mulai` date DEFAULT NULL,
        `tgl_selesai` date DEFAULT NULL,
        `nilai_nominal` double DEFAULT 0,
        `skor_kualitas` int(3) DEFAULT 0,
        `skor_waktu` int(3) DEFAULT 0,
        `skor_harga` int(3) DEFAULT 0,
        `skor_respon` int(3) DEFAULT 0,
        `total_skor` decimal(5,2) DEFAULT 0,
        `data_dph` longtext DEFAULT NULL,
        `file_lampiran` varchar(255) DEFAULT NULL,
        `keterangan` text DEFAULT NULL,
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $upload_dir = UPLOADS . '/logistik_non_medis/vendor_docs';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  private function _generateNoPR($kode_unit)
  {
      $prefix = 'PR/' . date('Ym') . '/' . $kode_unit . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_pr')
                   ->where('no_pr', 'LIKE', $prefix.'%')
                   ->desc('no_pr')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $parts = explode('/', $last['no_pr']);
          $last_num = (int) end($parts);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }


  private function _generateKodeRekanan()
  {
      $prefix = 'RKJ' . date('Y');
      $last = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')
                   ->where('kode_rekanan', 'LIKE', $prefix.'%')
                   ->desc('kode_rekanan')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $last_num = (int) substr($last['kode_rekanan'], -4);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getMasterRekanan()
  {
    $this->_initRekananJasa();
    $this->_addHeaderFiles();
    return $this->draw('master.rekanan.html');
  }

  public function anyDisplayMasterRekanan()
  {
      $this->_initRekananJasa();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa');
      if(!empty($cari)) {
          $query->where('kode_rekanan', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_rekanan', '%'.$cari.'%')
                ->orLike('nomor_kontrak', '%'.$cari.'%');
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa');
      if(!empty($cari)) {
          $rows->where('kode_rekanan', 'LIKE', '%'.$cari.'%')
                ->orLike('nama_rekanan', '%'.$cari.'%')
                ->orLike('nomor_kontrak', '%'.$cari.'%');
      }
      $rows = $rows->desc('kode_rekanan')
                    ->offset($_offset)
                    ->limit($perpage)
                    ->toArray();

      echo $this->draw('master.rekanan.display.html', [
          'rekanan' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormMasterRekanan()
  {
      $view_only = isset($_POST['view_only']) && $_POST['view_only'] == 'true';
      if (!empty($_POST['kode_rekanan'])){
          $rekanan = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('kode_rekanan', $_POST['kode_rekanan'])->oneArray();
          echo $this->draw('master.rekanan.form.html', ['rekanan' => $rekanan, 'mode' => 'edit', 'view_only' => $view_only]);
      } else {
          $rekanan = [
              'kode_rekanan' => $this->_generateKodeRekanan(),
              'nama_rekanan' => '',
              'kategori' => 'Vendor Servis',
              'alamat' => '',
              'no_telp' => '',
              'email' => '',
              'website' => '',
              'pic' => '',
              'pic_kontak' => '',
              'jenis_layanan' => '',
              'frekuensi' => '',
              'tgl_servis_terakhir' => date('Y-m-d'),
              'tgl_servis_berikutnya' => date('Y-m-d', strtotime('+3 months')),
              'nomor_kontrak' => '',
              'tgl_mulai_kontrak' => date('Y-m-d'),
              'tgl_selesai_kontrak' => date('Y-m-d', strtotime('+1 year')),
              'nilai_kontrak' => '0',
              'file_kontrak' => '',
              'status' => 'Aktif'
          ];
          echo $this->draw('master.rekanan.form.html', ['rekanan' => $rekanan, 'mode' => 'add', 'view_only' => $view_only]);
      }
      exit();
  }

  public function postSaveMasterRekanan()
  {
      $this->_initRekananJasa();
      $kode_rekanan = $_POST['kode_rekanan'] ?? '';
      if(empty($kode_rekanan)) {
          echo json_encode(['status' => 'error', 'message' => 'Kode Rekanan wajib diisi!']);
          exit();
      }

      $data = [
          'kode_rekanan' => $kode_rekanan,
          'nama_rekanan' => $_POST['nama_rekanan'] ?? '',
          'kategori' => $_POST['kategori'] ?? 'Vendor Servis',
          'alamat' => $_POST['alamat'] ?? '',
          'no_telp' => $_POST['no_telp'] ?? '',
          'email' => $_POST['email'] ?? '',
          'website' => $_POST['website'] ?? '',
          'pic' => $_POST['pic'] ?? '',
          'pic_kontak' => $_POST['pic_kontak'] ?? '',
          'jenis_layanan' => $_POST['jenis_layanan'] ?? '',
          'frekuensi' => $_POST['frekuensi'] ?? '',
          'tgl_servis_terakhir' => $_POST['tgl_servis_terakhir'] ?? NULL,
          'tgl_servis_berikutnya' => $_POST['tgl_servis_berikutnya'] ?? NULL,
          'nomor_kontrak' => $_POST['nomor_kontrak'] ?? '',
          'tgl_mulai_kontrak' => $_POST['tgl_mulai_kontrak'] ?? NULL,
          'tgl_selesai_kontrak' => $_POST['tgl_selesai_kontrak'] ?? NULL,
          'nilai_kontrak' => str_replace(['Rp. ', 'Rp.', '.', ' '], '', $_POST['nilai_kontrak'] ?? 0) ?: 0,
          'status' => $_POST['status'] ?? 'Aktif'
      ];

      // Logging
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_log = date('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'];
      $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
      $hostname = $cek_hostname['hostname'] ?? 'Unknown';
      $log_lokasi = ''.$hostname.' | '.$ip.'';
      $logdata = ''.$data['kode_rekanan'].' | '.$data['nama_rekanan'].' | '.$user.'';

      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_rekanan_jasa',
          'log_waktu' => $tanggal_log,
          'log_location' => $log_lokasi,
          'log_data' => $logdata,
          'log_status' => (isset($_POST['kode_rekanan']) && $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('kode_rekanan', $_POST['kode_rekanan'])->oneArray()) ? 'U' : 'I',
          'log_username' => $user
      ]);

      $upload_dir = UPLOADS . '/logistik_non_medis/rekanan_jasa';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

      // Handle File Upload
      if(isset($_FILES['file_kontrak']) && $_FILES['file_kontrak']['error'] == 0) {
          $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
          $ext = strtolower(pathinfo($_FILES['file_kontrak']['name'], PATHINFO_EXTENSION));
          if(!in_array($ext, $allowed)) {
              echo json_encode(['status' => 'error', 'message' => 'Format file tidak diizinkan! (Gunakan PDF, JPG, PNG, ZIP, atau RAR)']);
              exit();
          }
          
          $filename = 'kontrak_' . $kode_rekanan . '_' . time() . '.' . $ext;
          if(move_uploaded_file($_FILES['file_kontrak']['tmp_name'], $upload_dir . '/' . $filename)) {
              $data['file_kontrak'] = $filename;
          }
      }

      $cek = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('kode_rekanan', $kode_rekanan)->oneArray();
      
      if (!$cek) {
          $query = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->save($data);
      } else {
          if(isset($data['file_kontrak']) && !empty($cek['file_kontrak']) && file_exists($upload_dir . '/' . $cek['file_kontrak'])) {
              unlink($upload_dir . '/' . $cek['file_kontrak']);
          }
          $query = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('kode_rekanan', $kode_rekanan)->update($data);
      }

      if($query !== false) {
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data ke database']);
      }
      exit();
  }

  public function postHapusMasterRekanan()
  {
      $kode_rekanan = $_POST['kode_rekanan'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('kode_rekanan', $kode_rekanan)->oneArray();
      if($cek) {
          // Logging
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal_log = date('Y-m-d H:i:s');
          $ip = $_SERVER['REMOTE_ADDR'];
          $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
          $hostname = $cek_hostname['hostname'] ?? 'Unknown';
          $log_lokasi = ''.$hostname.' | '.$ip.'';
          $logdata = ''.$cek['kode_rekanan'].' | '.$cek['nama_rekanan'].' | '.$user.'';

          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_rekanan_jasa',
              'log_waktu' => $tanggal_log,
              'log_location' => $log_lokasi,
              'log_data' => $logdata,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          $upload_dir = UPLOADS . '/logistik_non_medis/rekanan_jasa';
          if(!empty($cek['file_kontrak']) && file_exists($upload_dir . '/' . $cek['file_kontrak'])) {
              unlink($upload_dir . '/' . $cek['file_kontrak']);
          }
          $this->db('rsns_custom_logistik_non_medis_rekanan_jasa')->where('kode_rekanan', $kode_rekanan)->delete();
      }
      exit();
  }

  public function getMasterCoa()
  {
    $this->_addHeaderFiles();
    return $this->draw('master.coa.html');
  }

  // --- PENGADAAN ---

  public function getPengadaanPerencanaan()
  {
    $this->_initPerencanaan();
    $this->_addHeaderFiles();

    // Load data langsung tanpa AJAX
    $sql = "
        SELECT p.*,
               u.nama_unit,
               b.nama_barang,
               b.satuan_dasar
        FROM rsns_custom_logistik_non_medis_perencanaan p
        LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = p.kode_unit
        LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
        ORDER BY p.id DESC
    ";
    $stmt = $this->db()->pdo()->prepare($sql);
    $stmt->execute([]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Pre-kalkulasi agar tidak perlu ekspresi di template
    $perencanaan = [];
    foreach ($rows as $i => $row) {
        $row['no'] = $i + 1;
        $row['estimasi'] = number_format($row['total_qty'] * $row['harga_referensi'], 0, ',', '.');
        $perencanaan[] = $row;
    }

    // Data Konsolidasi (disetujui, dikelompokkan per barang)
    $sql_konsolidasi = "
        SELECT p.kode_item, b.nama_barang, b.satuan_dasar,
               SUM(p.total_qty) as total_qty,
               AVG(p.harga_referensi) as harga_avg
        FROM rsns_custom_logistik_non_medis_perencanaan p
        LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
        WHERE p.status = 'Disetujui'
        GROUP BY p.kode_item, b.nama_barang, b.satuan_dasar
        ORDER BY total_qty DESC
    ";
    $stmt2 = $this->db()->pdo()->prepare($sql_konsolidasi);
    $stmt2->execute([]);
    $konsolidasi_raw = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
    $konsolidasi = [];
    foreach ($konsolidasi_raw as $i => $row) {
        $row['no'] = $i + 1;
        $row['estimasi'] = number_format($row['total_qty'] * $row['harga_avg'], 0, ',', '.');
        $row['harga_avg_fmt'] = number_format($row['harga_avg'], 0, ',', '.');
        $konsolidasi[] = $row;
    }

    // Data Anggaran (dikelompokkan per kategori)
    $tahun_aktif = date('Y');
    $sql_anggaran = "
        SELECT b.kategori,
               SUM(p.total_qty * p.harga_referensi) as subtotal
        FROM rsns_custom_logistik_non_medis_perencanaan p
        LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
        WHERE p.tahun = ?
        GROUP BY b.kategori
        ORDER BY subtotal DESC
    ";
    $stmt3 = $this->db()->pdo()->prepare($sql_anggaran);
    $stmt3->execute([$tahun_aktif]);
    $anggaran_raw = $stmt3->fetchAll(\PDO::FETCH_ASSOC);
    $grand_total = 0;
    $anggaran = [];
    foreach ($anggaran_raw as $i => $row) {
        $grand_total += $row['subtotal'];
        $row['no'] = $i + 1;
        $row['subtotal_fmt'] = number_format($row['subtotal'], 0, ',', '.');
        $anggaran[] = $row;
    }
    $grand_total_fmt = number_format($grand_total, 0, ',', '.');

    return $this->draw('pengadaan.perencanaan.html', [
      'token'          => $_GET['t'] ?? '',
      'perencanaan'    => $perencanaan,
      'konsolidasi'    => $konsolidasi,
      'anggaran'       => $anggaran,
      'grand_total'    => $grand_total_fmt,
      'tahun'          => $tahun_aktif,
      'chart_labels'   => json_encode(array_column($anggaran, 'kategori')),
      'chart_data'     => json_encode(array_column($anggaran, 'subtotal')),
      'js' => '
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4"></script>
<script>
$(document).ready(function() {
    var baseURL = mlite.url + "/" + mlite.admin + "/logistik_non_medis";

    // Chart Struktur Anggaran
    var chartLabels = ' . json_encode(array_column($anggaran, 'kategori')) . ';
    var chartData   = ' . json_encode(array_column($anggaran, 'subtotal')) . ';
    
    if(chartLabels.length > 0 && document.getElementById("chartAnggaran")) {
        var ctx = document.getElementById("chartAnggaran").getContext("2d");
        new Chart(ctx, {
            type: "pie",
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: [
                        "#3498db","#e74c3c","#2ecc71","#f39c12","#9b59b6",
                        "#1abc9c","#e67e22","#34495e","#e91e63","#00bcd4"
                    ],
                    borderWidth: 2,
                    borderColor: "#fff"
                }]
            },
            options: {
                responsive: true,
                legend: { position: "bottom" },
                tooltips: {
                    callbacks: {
                        label: function(item, data) {
                            var val = data.datasets[0].data[item.index];
                            return data.labels[item.index] + ": Rp. " + val.toLocaleString("id-ID");
                        }
                    }
                }
            }
        });
    }

    // Global listener untuk form perencanaan
    $(document).on("submit", "#formPerencanaan", function(e) {
        e.preventDefault();
        var btn = $("#btnSimpanRKBU");
        var form = $(this);
        btn.prop("disabled", true).html(\'<i class="fa fa-spinner fa-spin"></i> Menyimpan...\');
        
        $.ajax({
            url: form.attr("action"),
            type: "POST",
            data: new FormData(this),
            cache: false,
            contentType: false,
            processData: false,
            success: function(res) {
                try {
                    var data = JSON.parse(res);
                    if(data.status == "success") {
                        $("#modalForm").modal("hide");
                        alert(data.message + " Silakan refresh halaman untuk melihat data terbaru.");
                    } else {
                        alert(data.message);
                        btn.prop("disabled", false).html("Simpan Seluruh Usulan");
                    }
                } catch(err) {
                    alert("Tersimpan. Silakan refresh halaman.");
                    $("#modalForm").modal("hide");
                }
            },
            error: function() {
                alert("Gagal menyimpan. Periksa koneksi Anda.");
                btn.prop("disabled", false).html("Simpan Seluruh Usulan");
            }
        });
    });

    window.tambahRKBU = function() {
        $.post(baseURL + "/formperencanaan?t=" + mlite.token, function(data) {
            $("#form_content").html(data);
            $("#modalForm").modal("show");
        }).fail(function() {
            alert("Gagal memuat form. Periksa koneksi atau sesi login Anda.");
        });
    };

    window.viewRKBU = function(id) {
        $.post(baseURL + "/detailperencanaan?t=" + mlite.token, {id: id}, function(data) {
            $("#form_content").html(data);
            $("#modalForm").modal("show");
        }).fail(function() {
            alert("Gagal memuat detail.");
        });
    };

    window.editRKBU = function(id) {
        $.post(baseURL + "/formperencanaan?t=" + mlite.token, {id: id}, function(data) {
            $("#form_content").html(data);
            $("#modalForm").modal("show");
        });
    };

    window.hapusRKBU = function(id) {
        if(confirm("Apakah Anda yakin ingin menghapus data ini?")) {
            $.post(baseURL + "/hapusperencanaan?t=" + mlite.token, {id: id}, function() {
                window.location.reload();
            });
        }
    };
});
</script>'
    ]);
  }

  public function anyDetailPerencanaan()
  {
      $id = $_POST['id'] ?? '';
      $sql = "
          SELECT p.*,
                 u.nama_unit,
                 b.nama_barang,
                 b.satuan_dasar,
                 b.kategori
          FROM rsns_custom_logistik_non_medis_perencanaan p
          LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = p.kode_unit
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
          WHERE p.id = ?
      ";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute([$id]);
      $row = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($row) {
          $row['estimasi'] = number_format($row['total_qty'] * $row['harga_referensi'], 0, ',', '.');
          $row['harga_referensi_fmt'] = number_format($row['harga_referensi'], 0, ',', '.');
      }

      echo $this->draw('perencanaan.detail.html', ['data' => $row]);
      exit();
  }

  public function anyDisplayPerencanaan()
  {
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $tahun = isset($_POST['tahun']) ? $_POST['tahun'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $sql = "
          SELECT p.*,
                 u.nama_unit,
                 b.nama_barang,
                 b.satuan_dasar
          FROM rsns_custom_logistik_non_medis_perencanaan p
          LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = p.kode_unit
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
          WHERE 1=1
      ";

      $params = [];

      if (!empty($tahun)) {
          $sql .= " AND p.tahun = ? ";
          $params[] = $tahun;
      }

      if (!empty($cari)) {
          $sql .= " AND (p.kode_perencanaan LIKE ? OR u.nama_unit LIKE ? OR b.nama_barang LIKE ?) ";
          $params[] = '%'.$cari.'%';
          $params[] = '%'.$cari.'%';
          $params[] = '%'.$cari.'%';
      }

      $sql .= " ORDER BY p.id DESC ";

      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute($params);
      $all_data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $jumlah_data = count($all_data);
      $jml_halaman = $jumlah_data > 0 ? ceil($jumlah_data / $perpage) : 1;
      $rows = array_slice($all_data, $_offset, $perpage);


      echo $this->draw('perencanaan.display.html', [
          'perencanaan' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormPerencanaan()
  {
      $this->_initPerencanaan();
      $this->_initUnit();
      $this->_initDataBarang();
      
      // Jika ada data POST, maka ini adalah proses SIMPAN
      if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['items'])) {
          $kode_unit = $_POST['kode_unit'] ?? '';
          $tahun = $_POST['tahun'] ?? date('Y');
          $status = $_POST['status'] ?? 'Draft';
          $items = $_POST['items'] ?? [];

          if (empty($kode_unit) || empty($items)) {
              echo json_encode(['status' => 'error', 'message' => 'Unit dan Barang minimal harus diisi satu!']);
              exit();
          }

          $kode_perencanaan = $this->_generateKodePerencanaan($kode_unit, $tahun);
          $user = $this->core->getUserInfo('username', null, true);
          $tanggal = date('Y-m-d H:i:s');

          $this->db('rsns_custom_logistik_non_medis_perencanaan')
               ->where('kode_unit', $kode_unit)
               ->where('tahun', $tahun)
               ->delete();

          $success_count = 0;
          foreach ($items as $item) {
              if (empty($item['kode_item'])) continue;

              $months = ['jan','feb','mar','apr','mei','jun','jul','agu','sep','okt','nov','des'];
              $total_qty = 0;
              foreach ($months as $m) {
                  $total_qty += (double)($item[$m] ?? 0);
              }

              $data = [
                  'kode_perencanaan' => $kode_perencanaan,
                  'kode_unit' => $kode_unit,
                  'tahun' => $tahun,
                  'kode_item' => $item['kode_item'],
                  'jan' => $item['jan'] ?? 0, 'feb' => $item['feb'] ?? 0, 'mar' => $item['mar'] ?? 0, 'apr' => $item['apr'] ?? 0,
                  'mei' => $item['mei'] ?? 0, 'jun' => $item['jun'] ?? 0, 'jul' => $item['jul'] ?? 0, 'agu' => $item['agu'] ?? 0,
                  'sep' => $item['sep'] ?? 0, 'okt' => $item['okt'] ?? 0, 'nov' => $item['nov'] ?? 0, 'des' => $item['des'] ?? 0,
                  'total_qty' => $total_qty,
                  'harga_referensi' => str_replace(['Rp. ', 'Rp.', '.', ' '], '', $item['harga_referensi'] ?? 0),
                  'prioritas' => $item['prioritas'] ?? 'Desirable',
                  'status' => $status,
                  'tgl_input' => $tanggal,
                  'user_input' => $user
              ];

              if ($this->db('rsns_custom_logistik_non_medis_perencanaan')->save($data)) {
                  $success_count++;
              }
          }

          if($success_count > 0) {
              // Logging
              $ip = $_SERVER['REMOTE_ADDR'];
              $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_perencanaan',
                  'log_waktu' => date('Y-m-d H:i:s'),
                  'log_location' => $hostname . ' | ' . $ip,
                  'log_data' => 'Save Perencanaan: ' . $kode_perencanaan . ' | Unit: ' . $kode_unit . ' | Tahun: ' . $tahun . ' | Status: ' . $status . ' | ' . $user,
                  'log_status' => 'I',
                  'log_username' => $user
              ]);
              echo json_encode(['status' => 'success', 'message' => $success_count . ' item berhasil disimpan.']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data barang.']);
          }
          exit();
      }

      $units = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $items = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();

      if (isset($_POST['id'])){
          $ref = $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('id', $_POST['id'])->oneArray();
          $perencanaan_list = [];
          if($ref) {
              $perencanaan_list = $this->db('rsns_custom_logistik_non_medis_perencanaan')
                                       ->where('kode_unit', $ref['kode_unit'])
                                       ->where('tahun', $ref['tahun'])
                                       ->toArray();
          }
          echo $this->draw('perencanaan.form.html', [
              'perencanaan' => $ref, 
              'perencanaan_list' => $perencanaan_list,
              'mode' => 'edit', 
              'units' => $units, 
              'items' => $items, 
              'token' => $_GET['t'] ?? ''
          ]);
      } else {
          $perencanaan = [
              'id' => '', 'kode_perencanaan' => '', 'kode_unit' => '', 'tahun' => date('Y'), 'kode_item' => '',
              'jan' => 0, 'feb' => 0, 'mar' => 0, 'apr' => 0, 'mei' => 0, 'jun' => 0,
              'jul' => 0, 'agu' => 0, 'sep' => 0, 'okt' => 0, 'nov' => 0, 'des' => 0,
              'total_qty' => 0, 'harga_referensi' => 0, 'prioritas' => 'Desirable', 'status' => 'Draft'
          ];
          echo $this->draw('perencanaan.form.html', [
              'perencanaan' => $perencanaan, 
              'mode' => 'add', 
              'units' => $units, 
              'items' => $items, 
              'token' => $_GET['t'] ?? ''
          ]);
      }
      exit();
  }


  public function postHapusPerencanaan()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          $cek = $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('id', $id)->oneArray();
          if($cek) {
              $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('id', $id)->delete();
              
              // Logging
              $user = $this->core->getUserInfo('username', null, true);
              $ip = $_SERVER['REMOTE_ADDR'];
              $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_perencanaan',
                  'log_waktu' => date('Y-m-d H:i:s'),
                  'log_location' => $hostname . ' | ' . $ip,
                  'log_data' => 'Delete Perencanaan: ' . $cek['kode_perencanaan'] . ' (ID: ' . $id . ')',
                  'log_status' => 'D',
                  'log_username' => $user
              ]);
          }
      }
      exit();
  }

  public function anyKonsolidasiData()
  {
      $tahun = $_POST['tahun'] ?? date('Y');
      $rows = $this->db('rsns_custom_logistik_non_medis_perencanaan')
                   ->join('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_perencanaan.kode_item')
                   ->select('rsns_custom_logistik_non_medis_perencanaan.kode_item')
                   ->select('rsns_custom_logistik_non_medis_master_barang.nama_barang')
                   ->select('rsns_custom_logistik_non_medis_master_barang.satuan_dasar')
                   ->select('SUM(rsns_custom_logistik_non_medis_perencanaan.total_qty)', 'total_qty')
                   ->select('AVG(rsns_custom_logistik_non_medis_perencanaan.harga_referensi)', 'harga_avg')
                   ->where('rsns_custom_logistik_non_medis_perencanaan.tahun', $tahun)
                   ->where('rsns_custom_logistik_non_medis_perencanaan.status', 'Disetujui')
                   ->group('rsns_custom_logistik_non_medis_perencanaan.kode_item')
                   ->toArray();

      echo $this->draw('perencanaan.konsolidasi.html', ['data' => $rows, 'tahun' => $tahun]);
      exit();
  }

  public function anyAnggaranData()
  {
      $tahun = $_POST['tahun'] ?? date('Y');
      $rows = $this->db('rsns_custom_logistik_non_medis_perencanaan')
                   ->join('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_perencanaan.kode_item')
                   ->select('rsns_custom_logistik_non_medis_master_barang.kategori')
                   ->select('SUM(rsns_custom_logistik_non_medis_perencanaan.total_qty * rsns_custom_logistik_non_medis_perencanaan.harga_referensi)', 'subtotal')
                   ->where('rsns_custom_logistik_non_medis_perencanaan.tahun', $tahun)
                   ->where('rsns_custom_logistik_non_medis_perencanaan.status', 'Disetujui')
                   ->group('rsns_custom_logistik_non_medis_master_barang.kategori')
                   ->toArray();

      $grand_total = 0;
      foreach($rows as $row) {
          $grand_total += (double)$row['subtotal'];
      }

      echo $this->draw('perencanaan.anggaran.html', ['data' => $rows, 'tahun' => $tahun, 'grand_total' => $grand_total]);
      exit();
  }

  public function anyGetItemPrice()
  {
      if (isset($_POST['kode_item'])) {
          $item = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $_POST['kode_item'])->oneArray();
          echo json_encode(['harga' => $item['harga_referensi'] ?? 0]);
      }
      exit();
  }

  public function getPengadaanPr()
  {
    $this->_initPR();
    $this->_addHeaderFiles();
    
    $js_auto = '';
    if(isset($_GET['action']) && $_GET['action'] == 'add_from_katalog') {
        $katalog_id = $_GET['katalog_id'] ?? '';
        $js_auto = '<script>$(document).ready(function(){ tambahPR("'.$katalog_id.'"); });</script>';
    }

    return $this->draw('pengadaan.pr.html', ['js_auto' => $js_auto]);
  }

  public function anyLoadRKBUItems()
  {
      $kode_unit = $_POST['kode_unit'] ?? '';
      if(empty($kode_unit)) {
          echo json_encode(['status' => 'error', 'message' => 'Unit belum dipilih.']);
          exit();
      }

      $sql = "
          SELECT p.*, b.nama_barang, b.satuan_dasar 
          FROM rsns_custom_logistik_non_medis_perencanaan p
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
          WHERE p.kode_unit = ? AND p.status = 'Disetujui' AND p.tahun = ?
      ";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute([$kode_unit, date('Y')]);
      $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      echo json_encode(['status' => 'success', 'items' => $items]);
      exit();
  }

  public function anyDisplaypr()
  {
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $sql = "
          SELECT p.no_pr, p.tgl_pr, p.kode_unit, u.nama_unit, p.status, p.justifikasi, p.file_justifikasi,
                 COUNT(p.kode_item) as jml_item,
                 GROUP_CONCAT(b.nama_barang SEPARATOR ', ') as daftar_barang
          FROM rsns_custom_logistik_non_medis_pr p
          LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = p.kode_unit
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
          WHERE 1=1
      ";

      $params = [];
      if (!empty($cari)) {
          $sql .= " AND (p.no_pr LIKE ? OR u.nama_unit LIKE ? OR b.nama_barang LIKE ?) ";
          $params[] = '%'.$cari.'%';
          $params[] = '%'.$cari.'%';
          $params[] = '%'.$cari.'%';
      }

      $sql .= " GROUP BY p.no_pr, p.tgl_pr, p.kode_unit, u.nama_unit, p.status, p.justifikasi, p.file_justifikasi 
                ORDER BY p.tgl_pr DESC, p.no_pr DESC ";

      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute($params);
      $all_data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $jumlah_data = count($all_data);
      $jml_halaman = $jumlah_data > 0 ? ceil($jumlah_data / $perpage) : 1;
      $rows = array_slice($all_data, $_offset, $perpage);
      
      foreach ($rows as $i => &$row) {
          $row['no'] = $i + 1 + $_offset;
          $row['tgl_pr'] = date('d/m/Y', strtotime($row['tgl_pr']));
      }

      echo $this->draw('pengadaan.pr.display.html', [
          'pr' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormPr()
  {
      $this->_initUnit();
      $this->_initSatuan();
      $this->_initDataBarang();
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->toArray();
      $items = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
      $satuans = $this->db('rsns_custom_logistik_non_medis_satuan')->toArray();

      $prefill_item = null;
      $matched_item = null;
      $katalog_id = $_POST['katalog_id'] ?? $_GET['katalog_id'] ?? '';
      if(!empty($katalog_id)) {
          $this->_initEKatalog();
          $prefill_item = $this->db('rsns_custom_logistik_non_medis_ekatalog')->where('id', $katalog_id)->oneArray();
          if($prefill_item) {
              // Coba cari barang yang namanya mirip di database lokal
              $matched_item = $this->db('rsns_custom_logistik_non_medis_master_barang')
                                   ->where('nama_barang', 'LIKE', '%'.$prefill_item['nama_produk'].'%')
                                   ->oneArray();
          }
      }

      if (isset($_POST['no_pr']) && !isset($_POST['katalog_id'])){
          $pr_items = $this->db('rsns_custom_logistik_non_medis_pr')->where('no_pr', $_POST['no_pr'])->toArray();
          $pr = $pr_items[0]; 
          echo $this->draw('pengadaan.pr.form.html', ['pr' => $pr, 'pr_items' => $pr_items, 'mode' => 'edit', 'units' => $units, 'items' => $items, 'satuans' => $satuans]);
      } else {
          $pr = [
              'no_pr' => '',
              'tgl_pr' => date('Y-m-d'),
              'kode_unit' => '',
              'status' => 'Draft',
              'justifikasi' => $prefill_item ? 'E-Purchasing Katalog: ' . $prefill_item['nama_produk'] . ' (Ref: ' . $prefill_item['kode_produk_lkpp'] . ')' : '',
              'file_justifikasi' => ''
          ];
          echo $this->draw('pengadaan.pr.form.html', [
              'pr' => $pr, 
              'mode' => 'add', 
              'units' => $units, 
              'items' => $items, 
              'satuans' => $satuans,
              'prefill' => $prefill_item,
              'matched_item' => $matched_item
          ]);
      }
      exit();
  }

  public function anySimpanPr()
  {
      file_put_contents(__DIR__ . '/debug_pr.txt', "MASUK POST SAVE PR: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
      if (ob_get_length()) ob_clean();
      header('Content-Type: application/json');
      try {
          if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
              throw new \Exception('Metode pengiriman data salah.');
          }
          
          if (!isset($_POST['items']) || empty($_POST['items'])) {
              throw new \Exception('Daftar barang tidak boleh kosong.');
          }

          $no_pr = $_POST['no_pr'] ?? '';
          $kode_unit = $_POST['kode_unit'] ?? '';
          $tgl_pr = $_POST['tgl_pr'] ?? date('Y-m-d');
          $justifikasi = $_POST['justifikasi'] ?? '';
          $status = $_POST['status'] ?? 'Diajukan';
          $items = $_POST['items'];

          if (empty($kode_unit)) {
              throw new \Exception('Unit peminta harus dipilih.');
          }

          if (empty($no_pr)) {
              $no_pr = $this->_generateNoPR($kode_unit);
          } else {
              $this->db('rsns_custom_logistik_non_medis_pr')->where('no_pr', $no_pr)->delete();
          }

          $user = $this->core->getUserInfo('username', null, true);
          $file_justifikasi = $_POST['old_file_justifikasi'] ?? '';

          if(isset($_FILES['file_justifikasi']) && $_FILES['file_justifikasi']['error'] == 0) {
              $upload_dir = UPLOADS . '/logistik_non_medis/pr';
              if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
              $ext = strtolower(pathinfo($_FILES['file_justifikasi']['name'], PATHINFO_EXTENSION));
              $filename = 'just_' . str_replace('/', '_', $no_pr) . '_' . time() . '.' . $ext;
              if(move_uploaded_file($_FILES['file_justifikasi']['tmp_name'], $upload_dir . '/' . $filename)) {
                  $file_justifikasi = $filename;
              }
          }

          $success_count = 0;
          foreach ($items as $item) {
              if (empty($item['kode_item'])) continue;

              $data = [
                  'no_pr' => $no_pr,
                  'tgl_pr' => $tgl_pr,
                  'kode_unit' => $kode_unit,
                  'kode_item' => $item['kode_item'],
                  'jumlah' => $item['jumlah'] ?? 0,
                  'satuan' => $item['satuan'] ?? '',
                  'justifikasi' => $justifikasi,
                  'file_justifikasi' => $file_justifikasi,
                  'status' => $status,
                  'user_input' => $user
              ];

              if ($this->db('rsns_custom_logistik_non_medis_pr')->save($data)) {
                  $success_count++;
              }
          }

          if ($success_count === 0) {
              throw new \Exception('Tidak ada item barang yang tersimpan.');
          }

          // Logging
          $ip = $_SERVER['REMOTE_ADDR'];
          $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_pr',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'Save PR: ' . $no_pr . ' | Unit: ' . $kode_unit . ' | Status: ' . $status . ' | ' . $user,
              'log_status' => (isset($_POST['no_pr']) && !empty($_POST['no_pr']) ? 'U' : 'I'),
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success', 'message' => 'Permintaan berhasil disimpan dengan nomor: ' . $no_pr]);
      } catch (\Throwable $e) {
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function anyDetailpr()
  {
      $no_pr = $_POST['no_pr'] ?? '';
      $sql = "
          SELECT p.*,
                 u.nama_unit,
                 b.nama_barang,
                 b.satuan_dasar
          FROM rsns_custom_logistik_non_medis_pr p
          LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = p.kode_unit
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item = p.kode_item
          WHERE p.no_pr = ?
      ";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute([$no_pr]);
      $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      if (!$items) {
          echo "Data tidak ditemukan.";
          exit();
      }

      foreach ($items as $k => $v) {
          $items[$k]['no'] = $k + 1;
      }
      
      $pr = $items[0];
      $pr['tgl_pr'] = date('d/m/Y', strtotime($pr['tgl_pr']));
      if (!empty($pr['tgl_acc'])) {
          $pr['tgl_acc'] = date('d/m/Y H:i', strtotime($pr['tgl_acc']));
      }

      echo $this->draw('pengadaan.pr.detail.html', ['items' => $items, 'pr' => $pr]);
      exit();
  }

  public function postAccpr()
  {
      $no_pr = $_POST['no_pr'] ?? '';
      if ($no_pr) {
          $user = $this->core->getUserInfo('username', null, true);
          $data = [
              'status' => 'Selesai',
              'petugas_logistik' => $user,
              'tgl_acc' => date('Y-m-d H:i:s')
          ];
          $query = $this->db('rsns_custom_logistik_non_medis_pr')->where('no_pr', $no_pr)->update($data);
          if ($query) {
              // Logging
              $ip = $_SERVER['REMOTE_ADDR'];
              $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_pr',
                  'log_waktu' => date('Y-m-d H:i:s'),
                  'log_location' => $hostname . ' | ' . $ip,
                  'log_data' => 'ACC PR: ' . $no_pr . ' | Barang diberikan oleh: ' . $user,
                  'log_status' => 'U',
                  'log_username' => $user
              ]);
              echo json_encode(['status' => 'success', 'message' => 'Permintaan berhasil di-ACC dan barang diberikan.']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Gagal memproses ACC.']);
          }
      }
      exit();
  }

  public function postHapuspr()
  {
      $no_pr = $_POST['no_pr'] ?? '';
      if ($no_pr) {
          $this->db('rsns_custom_logistik_non_medis_pr')->where('no_pr', $no_pr)->delete();
          
          // Logging
          $user = $this->core->getUserInfo('username', null, true);
          $ip = $_SERVER['REMOTE_ADDR'];
          $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_pr',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'Delete PR: ' . $no_pr,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      }
      exit();
  }

  public function getPengadaanVendor()
  {
    $this->_initVendorManajemen();
    $this->_addHeaderFiles();
    $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->toArray();
    return $this->draw('pengadaan.manajemen_vendor.html', ['vendor' => $vendor]);
  }

  public function anyDisplayVendorManajemen()
  {
      $this->_initVendorManajemen();
      $kode_vendor = $_POST['kode_vendor'] ?? '';
      
      $query = $this->db('rsns_custom_logistik_non_medis_vendor_evaluasi');
      if(!empty($kode_vendor)) {
          $query->where('kode_vendor', $kode_vendor);
      }
      
      $rows = $query->desc('tgl_record')->desc('id')->toArray();

      foreach($rows as &$row) {
          $row['nilai_nominal_formatted'] = number_format((float)$row['nilai_nominal'], 0, ',', '.');
      }

      echo $this->draw('pengadaan.manajemen_vendor.display.html', [
          'manajemen' => $rows,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormVendorManajemen()
  {
      $this->_initVendorManajemen();
      $vendors = $this->db('rsns_custom_logistik_non_medis_vendor')->toArray();
      $barang = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();

      if (isset($_POST['id']) && $_POST['id'] !== ''){
          $data = $this->db('rsns_custom_logistik_non_medis_vendor_evaluasi')->where('id', $_POST['id'])->oneArray();
          echo $this->draw('pengadaan.manajemen_vendor.form.html', [
              'data' => $data, 
              'mode' => 'edit', 
              'mode_title' => 'Edit',
              'nilai_nominal_formatted' => 'Rp. ' . number_format($data['nilai_nominal'], 0, ',', '.'),
              'vendors' => $vendors,
              'barang' => $barang
          ]);
      } else {
          $data = [
              'id' => '',
              'kode_vendor' => $_POST['kode_vendor'] ?? '',
              'jenis_record' => 'Evaluasi',
              'tgl_record' => date('Y-m-d'),
              'nomor_dokumen' => '',
              'tgl_mulai' => '',
              'tgl_selesai' => '',
              'nilai_nominal' => 0,
              'skor_kualitas' => 0,
              'skor_waktu' => 0,
              'skor_harga' => 0,
              'skor_respon' => 0,
              'total_skor' => 0,
              'data_dph' => '[]',
              'file_lampiran' => '',
              'keterangan' => ''
          ];
          echo $this->draw('pengadaan.manajemen_vendor.form.html', [
              'data' => $data, 
              'mode' => 'add', 
              'mode_title' => 'Tambah',
              'nilai_nominal_formatted' => 'Rp. 0',
              'vendors' => $vendors,
              'barang' => $barang
          ]);
      }
      exit();
  }

  public function postSaveVendorManajemen()
  {
      $kode_vendor = $_POST['kode_vendor'] ?? '';
      if(empty($kode_vendor)) {
          echo json_encode(['status' => 'error', 'message' => 'Vendor wajib dipilih!']);
          exit();
      }

      $jenis = $_POST['jenis_record'] ?? 'Evaluasi';
      $total_skor = 0;
      if($jenis == 'Evaluasi') {
          $sk_kualitas = (int)($_POST['skor_kualitas'] ?? 0);
          $sk_waktu = (int)($_POST['skor_waktu'] ?? 0);
          $sk_harga = (int)($_POST['skor_harga'] ?? 0);
          $sk_respon = (int)($_POST['skor_respon'] ?? 0);
          $total_skor = ($sk_kualitas + $sk_waktu + $sk_harga + $sk_respon) / 4;
      }

      $data = [
          'kode_vendor' => $kode_vendor,
          'jenis_record' => $jenis,
          'tgl_record' => $_POST['tgl_record'] ?? date('Y-m-d'),
          'nomor_dokumen' => $_POST['nomor_dokumen'] ?? '',
          'tgl_mulai' => !empty($_POST['tgl_mulai']) ? $_POST['tgl_mulai'] : NULL,
          'tgl_selesai' => !empty($_POST['tgl_selesai']) ? $_POST['tgl_selesai'] : NULL,
          'nilai_nominal' => (int)explode(',', str_replace(['Rp.', 'Rp', '.', ' '], '', $_POST['nilai_nominal'] ?? '0'))[0],
          'skor_kualitas' => $_POST['skor_kualitas'] ?? 0,
          'skor_waktu' => $_POST['skor_waktu'] ?? 0,
          'skor_harga' => $_POST['skor_harga'] ?? 0,
          'skor_respon' => $_POST['skor_respon'] ?? 0,
          'total_skor' => $total_skor,
          'data_dph' => $_POST['data_dph'] ?? '[]',
          'keterangan' => $_POST['keterangan'] ?? '',
          'user_input' => $this->core->getUserInfo('username', null, true)
      ];

      // Logging Feature
      $user = $this->core->getUserInfo('username', null, true);
      $tanggal_log = date('Y-m-d H:i:s');
      $ip = $_SERVER['REMOTE_ADDR'];
      $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
      $hostname = $cek_hostname['hostname'] ?? 'Unknown';
      $log_lokasi = ''.$hostname.' | '.$ip.'';
      $logdata = ''.$data['kode_vendor'].' | '.$data['jenis_record'].' | '.$data['tgl_record'].' | '.$data['nomor_dokumen'].' | '.$data['nilai_nominal'].' | '.$data['total_skor'].' | '.$user.'';

      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_vendor_evaluasi',
          'log_waktu' => $tanggal_log,
          'log_location' => $log_lokasi,
          'log_data' => $logdata,
          'log_status' => (isset($_POST['id']) && !empty($_POST['id'])) ? 'U' : 'I',
          'log_username' => $user
      ]);

      $upload_dir = UPLOADS . '/logistik_non_medis/vendor_docs';
      if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      if(isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['file_lampiran']['name'], PATHINFO_EXTENSION));
          $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
          if(!in_array($ext, $allowed_ext)) {
              echo json_encode(['status' => 'error', 'message' => 'Format file lampiran tidak valid. Hanya diperbolehkan JPG, PNG, atau PDF.']);
              exit();
          }
          $filename = 'doc_' . time() . '_' . rand(100,999) . '.' . $ext;
          if(move_uploaded_file($_FILES['file_lampiran']['tmp_name'], $upload_dir . '/' . $filename)) {
              $data['file_lampiran'] = $filename;
          }
      }

      if (isset($_POST['id']) && !empty($_POST['id'])) {
          $query = $this->db('rsns_custom_logistik_non_medis_vendor_evaluasi')->where('id', $_POST['id'])->update($data);
      } else {
          $query = $this->db('rsns_custom_logistik_non_medis_vendor_evaluasi')->save($data);
      }

      if($query) {
          // Update Vendor Rating if Evaluation
          if($jenis == 'Evaluasi') {
              $avg = $this->db('rsns_custom_logistik_non_medis_vendor_evaluasi')
                          ->where('kode_vendor', $kode_vendor)
                          ->where('jenis_record', 'Evaluasi')
                          ->select('AVG(total_skor) as rata')
                          ->oneArray();
              $star_rating = round(($avg['rata'] / 100) * 5);
              $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $kode_vendor)->update(['rating' => $star_rating]);
          }
          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function postHapusVendorManajemen()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          $data = $this->db('rsns_custom_logistik_non_medis_vendor_evaluasi')->where('id', $id)->oneArray();
          if($data) {
              $this->db('rsns_custom_logistik_non_medis_vendor_evaluasi')->where('id', $id)->delete();
              
              // Logging Feature
              $user = $this->core->getUserInfo('username', null, true);
              $tanggal_log = date('Y-m-d H:i:s');
              $ip = $_SERVER['REMOTE_ADDR'];
              $cek_hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray();
              $hostname = $cek_hostname['hostname'] ?? 'Unknown';
              $log_lokasi = ''.$hostname.' | '.$ip.'';
              $logdata = ''.$data['kode_vendor'].' | '.$data['jenis_record'].' | '.$data['tgl_record'].' | '.$data['nomor_dokumen'].' | '.$data['nilai_nominal'].' | '.$data['total_skor'].' | '.$user.'';

              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_vendor_evaluasi',
                  'log_waktu' => $tanggal_log,
                  'log_location' => $log_lokasi,
                  'log_data' => $logdata,
                  'log_status' => 'D',
                  'log_username' => $user
              ]);
              echo json_encode(['status' => 'success']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
          }
      }
      exit();
  }

  private function _initHostnameTable()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_hostsname_pc` (
        `ip` varchar(50) NOT NULL,
        `hostname` varchar(100) NOT NULL,
        PRIMARY KEY (`ip`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _initPo()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_po` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_po` varchar(50) NOT NULL,
        `tgl_po` date NOT NULL,
        `kode_vendor` varchar(50) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `no_po` (`no_po`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $columns = [
          'total_nilai' => "DOUBLE NOT NULL DEFAULT 0 AFTER kode_vendor",
          'diskon' => "DOUBLE NOT NULL DEFAULT 0 AFTER total_nilai",
          'ppn' => "DOUBLE NOT NULL DEFAULT 0 AFTER diskon",
          'grand_total' => "DOUBLE NOT NULL DEFAULT 0 AFTER ppn",
          'detail_items' => "LONGTEXT NOT NULL AFTER grand_total",
          'catatan' => "TEXT NULL AFTER detail_items",
          'status' => "ENUM('Draft','Terkirim','Sebagian Diterima','Selesai','Diamandemen','Dibatalkan') NOT NULL DEFAULT 'Draft' AFTER catatan",
          'tgl_kirim' => "DATETIME NULL AFTER status",
          'file_po' => "VARCHAR(255) NULL AFTER tgl_kirim",
          'user_input' => "VARCHAR(100) NULL AFTER file_po"
      ];

      foreach ($columns as $col => $def) {
          try {
              $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_po` ADD `$col` $def");
          } catch (\Exception $e) {
              // Column probably already exists
          }
      }
      
      $upload_dir = UPLOADS . '/logistik_non_medis/po';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  private function _generateNoPO($kode_vendor)
  {
      $prefix = 'PO/' . date('Ym') . '/' . $kode_vendor . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_po')
                   ->where('no_po', 'LIKE', $prefix.'%')
                   ->desc('no_po')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $parts = explode('/', $last['no_po']);
          $last_num = (int) end($parts);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function getPengadaanpo()
  {
    $this->_initPo();
    $this->_addHeaderFiles();
    return $this->draw('pengadaan.po.html');
  }

  public function anyDisplaypengadaanpo()
  {
      $this->_initPo();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_po');
      if(!empty($cari)) {
          $query->where('no_po', 'LIKE', '%'.$cari.'%')
                ->orLike('kode_vendor', 'LIKE', '%'.$cari.'%');
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_po');
      if(!empty($cari)) {
          $rows->where('no_po', 'LIKE', '%'.$cari.'%')
                ->orLike('kode_vendor', 'LIKE', '%'.$cari.'%');
      }
      $rows = $rows->desc('id')
                    ->offset($_offset)
                    ->limit($perpage)
                    ->toArray();

      $vendors = [];
      $v_query = $this->db('rsns_custom_logistik_non_medis_vendor')->toArray();
      foreach($v_query as $v) {
          $vendors[$v['kode_vendor']] = $v['nama_vendor'];
      }

      foreach($rows as &$row) {
          $row['nama_vendor'] = $vendors[$row['kode_vendor']] ?? $row['kode_vendor'];
          $row['grand_total_formatted'] = 'Rp. ' . number_format((float)$row['grand_total'], 0, ',', '.');
      }

      echo $this->draw('pengadaan.po.display.html', [
          'po' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyLoadprforpo()
  {
      $this->_initPR();
      $kode_vendor = $_POST['kode_vendor'] ?? '';
      
      // Mengambil PR yang Disetujui
      // Nanti mungkin tambahkan logika filter by 'Di-PO-kan'
      $prs = $this->db('rsns_custom_logistik_non_medis_pr')->where('status', 'Disetujui')->toArray();
      
      $barang = [];
      $b_query = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
      foreach($b_query as $b) {
          $barang[$b['kode_item']] = $b['nama_barang'];
          $barang_harga[$b['kode_item']] = $b['harga_referensi'];
      }

      foreach($prs as &$pr) {
          $pr['nama_barang'] = $barang[$pr['kode_item']] ?? $pr['kode_item'];
          $pr['harga_referensi'] = $barang_harga[$pr['kode_item']] ?? 0;
      }
      
      header('Content-Type: application/json');
      echo json_encode($prs);
      exit();
  }

  public function anyFormpengadaanpo()
  {
      $this->_initPo();
      $vendors = $this->db('rsns_custom_logistik_non_medis_vendor')->toArray();
      
      $barang = [];
      $b_query = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
      foreach($b_query as $b) {
          $barang[$b['kode_item']] = $b['nama_barang'];
      }

      if (isset($_POST['id']) && $_POST['id'] !== ''){
          $data = $this->db('rsns_custom_logistik_non_medis_po')->where('id', $_POST['id'])->oneArray();
          $data['items'] = json_decode($data['detail_items'], true) ?: [];
          
          echo $this->draw('pengadaan.po.form.html', [
              'data' => $data, 
              'mode' => 'edit', 
              'vendors' => $vendors,
              'barang_map' => $barang
          ]);
      } else {
          $data = [
              'id' => '',
              'no_po' => 'AUTO',
              'tgl_po' => date('Y-m-d'),
              'kode_vendor' => '',
              'total_nilai' => 0,
              'diskon' => 0,
              'ppn' => 0,
              'grand_total' => 0,
              'status' => 'Draft',
              'tgl_kirim' => '',
              'catatan' => '',
              'items' => []
          ];
          echo $this->draw('pengadaan.po.form.html', [
              'data' => $data, 
              'mode' => 'add', 
              'vendors' => $vendors,
              'barang_map' => $barang
          ]);
      }
      exit();
  }

  public function postSavepengadaanpo()
  {
      file_put_contents(BASE_DIR . '/tmp/debug_po_save.txt', print_r($_POST, true));
      ob_start();
      $this->_initPo();
      $this->_initHostnameTable();
      $this->db()->pdo()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      try {
          $kode_vendor = $_POST['kode_vendor'] ?? '';
          if(empty($kode_vendor)) {
              throw new \Exception('Vendor wajib dipilih!');
          }

          $items_json = $_POST['items_json'] ?? $_POST['items'] ?? '[]'; 
          $decoded_items = json_decode($items_json, true);
          
          if(empty($decoded_items)) {
              throw new \Exception('Detail PR/Item PO wajib ditambahkan!');
          }

          $id = $_POST['id'] ?? '';
          if(empty($id)) {
              $no_po = $this->_generateNoPO($kode_vendor);
          } else {
              $existing = $this->db('rsns_custom_logistik_non_medis_po')->where('id', $id)->oneArray();
              $no_po = $existing['no_po'];
          }

          $data = [
              'no_po' => $no_po,
              'tgl_po' => $_POST['tgl_po'] ?? date('Y-m-d'),
              'kode_vendor' => $kode_vendor,
              'total_nilai' => (float)($_POST['total_nilai'] ?? 0),
              'diskon' => (float)($_POST['diskon'] ?? 0),
              'ppn' => (float)($_POST['ppn'] ?? 0),
              'grand_total' => (float)($_POST['grand_total'] ?? 0),
              'catatan' => $_POST['catatan'] ?? '',
              'detail_items' => $items_json,
              'user_input' => $this->core->getUserInfo('username', null, true)
          ];

          if(empty($id)) {
              $data['status'] = 'Draft';
          } elseif (!empty($existing) && in_array($existing['status'], ['Terkirim', 'Sebagian Diterima', 'Diamandemen'])) {
              $data['status'] = 'Diamandemen';
          }

          if (!empty($id)) {
              $query = $this->db('rsns_custom_logistik_non_medis_po')->where('id', $id)->update($data);
          } else {
              $query = $this->db('rsns_custom_logistik_non_medis_po')->save($data);
              if($query) {
                  foreach($decoded_items as $item) {
                      if(!empty($item['id_pr'])) {
                          $this->db('rsns_custom_logistik_non_medis_pr')->where('id', $item['id_pr'])->update(['status' => 'Di-PO-kan']);
                      }
                  }
              }
          }

          if($query) {
              ob_clean();
              
              // Logging
              $ip = $_SERVER['REMOTE_ADDR'];
              $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_po',
                  'log_waktu' => date('Y-m-d H:i:s'),
                  'log_location' => $hostname . ' | ' . $ip,
                  'log_data' => 'Save PO: ' . $no_po . ' | Vendor: ' . $kode_vendor . ' | Total: ' . $data['grand_total'] . ' | ' . $data['user_input'],
                  'log_status' => (!empty($id) ? 'U' : 'I'),
                  'log_username' => $data['user_input']
              ]);

              echo json_encode(['status' => 'success', 'message' => 'PO berhasil disimpan dengan nomor: ' . $no_po, 'no_po' => $no_po]);
          } else {
              throw new \Exception('Gagal menyimpan ke database.');
          }
      } catch (\Exception $e) {
          ob_clean();
          echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
      }
      exit();
  }

  public function postUbahstatuspo()
  {
      $this->_initPo();
      $id = $_POST['id'] ?? '';
      $status = $_POST['status'] ?? ''; 
      
      if($id && $status) {
          $data = ['status' => $status];
          if($status == 'Terkirim') {
              $data['tgl_kirim'] = date('Y-m-d H:i:s');
          }
          $query = $this->db('rsns_custom_logistik_non_medis_po')->where('id', $id)->update($data);
          if($query) {
               $user = $this->core->getUserInfo('username', null, true);
               $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_po_status',
                  'log_waktu' => date('Y-m-d H:i:s'),
                  'log_location' => $_SERVER['REMOTE_ADDR'],
                  'log_data' => "PO ID $id ubah status ke $status",
                  'log_status' => 'U',
                  'log_username' => $user
              ]);
              
              // If cancelled, revert PR status
              if($status == 'Dibatalkan') {
                  $po = $this->db('rsns_custom_logistik_non_medis_po')->where('id', $id)->oneArray();
                  if($po) {
                      $items = json_decode($po['detail_items'], true) ?: [];
                      foreach($items as $item) {
                          if(!empty($item['id_pr'])) {
                              $this->db('rsns_custom_logistik_non_medis_pr')->where('id', $item['id_pr'])->update(['status' => 'Disetujui']);
                          }
                      }
                  }
              }

              echo json_encode(['status' => 'success']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status PO']);
          }
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
      }
      exit();
  }

  public function postHapusPengadaanPo()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          $data = $this->db('rsns_custom_logistik_non_medis_po')->where('id', $id)->oneArray();
          if($data) {
              $items = json_decode($data['detail_items'], true) ?: [];
              foreach($items as $item) {
                  if(!empty($item['id_pr'])) {
                      $this->db('rsns_custom_logistik_non_medis_pr')->where('id', $item['id_pr'])->update(['status' => 'Disetujui']);
                  }
              }

              $this->db('rsns_custom_logistik_non_medis_po')->where('id', $id)->delete();
              
              $user = $this->core->getUserInfo('username', null, true);
              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_po',
                  'log_waktu' => date('Y-m-d H:i:s'),
                  'log_location' => $_SERVER['REMOTE_ADDR'],
                  'log_data' => $data['no_po'],
                  'log_status' => 'D',
                  'log_username' => $user
              ]);
              echo json_encode(['status' => 'success']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Data PO tidak ditemukan']);
          }
      }
      exit();
  }

  public function anyCetakpo()
  {
      $id = $_GET['id'] ?? '';
      if($id) {
          $po = $this->db('rsns_custom_logistik_non_medis_po')->where('id', $id)->oneArray();
          if($po) {
              $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $po['kode_vendor'])->oneArray();
              $items = json_decode($po['detail_items'], true) ?: [];
              
              $barang = [];
              $b_query = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
              foreach($b_query as $b) {
                  $barang[$b['kode_item']] = $b['nama_barang'];
              }
              
              foreach($items as &$item) {
                  $item['nama_barang'] = $barang[$item['kode_item']] ?? $item['kode_item'];
              }
              
              echo $this->draw('pengadaan.po.cetak.html', [
                  'po' => $po,
                  'vendor' => $vendor,
                  'items' => $items
              ]);
          } else {
              echo "PO tidak ditemukan.";
          }
      }
      exit();
  }

  private function _initEKatalog()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_ekatalog` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_po` varchar(50) DEFAULT NULL,
        `kode_produk_lkpp` varchar(100) DEFAULT NULL,
        `nama_produk` varchar(255) NOT NULL,
        `merk` varchar(100) DEFAULT NULL,
        `penyedia` varchar(255) DEFAULT NULL,
        `harga_katalog` double NOT NULL DEFAULT 0,
        `satuan` varchar(50) DEFAULT NULL,
        `no_paket_lkpp` varchar(100) DEFAULT NULL,
        `tgl_order` date DEFAULT NULL,
        `status` varchar(50) DEFAULT 'Master',
        `link_produk` text DEFAULT NULL,
        `last_sync` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `no_po` (`no_po`),
        KEY `kode_produk_lkpp` (`kode_produk_lkpp`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  public function getPengadaanEkatalog()
  {
    $this->_initEKatalog();
    $this->_addHeaderFiles();
    return $this->draw('pengadaan.ekatalog.html');
  }

  public function anyDisplayEKatalog()
  {
      $this->_initEKatalog();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $mode = isset($_POST['mode']) ? $_POST['mode'] : 'master'; // master or history
      
      $_offset = ($halaman - 1) * $perpage;
      
      $params = [];
      $where = ($mode == 'master') ? "WHERE (no_po IS NULL OR no_po = '')" : "WHERE (no_po IS NOT NULL AND no_po != '')";

      if(!empty($cari)) {
          $where .= " AND (nama_produk LIKE ? OR penyedia LIKE ? OR kode_produk_lkpp LIKE ?)";
          $params[] = "%$cari%";
          $params[] = "%$cari%";
          $params[] = "%$cari%";
      }
      
      $sql = "SELECT * FROM rsns_custom_logistik_non_medis_ekatalog $where ORDER BY id DESC LIMIT $_offset, $perpage";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      $sql_count = "SELECT COUNT(*) as total FROM rsns_custom_logistik_non_medis_ekatalog $where";
      $stmt_count = $this->db()->pdo()->prepare($sql_count);
      $stmt_count->execute($params);
      $total_res = $stmt_count->fetch(\PDO::FETCH_ASSOC);
      
      $jumlah_data = $total_res['total'];
      $jml_halaman = ceil($jumlah_data / $perpage);

      echo $this->draw('pengadaan.ekatalog.display.html', [
          'ekatalog' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'mode' => $mode,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormImportEKatalog()
  {
      echo $this->draw('pengadaan.ekatalog.form_import.html');
      exit();
  }

  public function postImportEKatalog()
  {
      $this->_initEKatalog();
      $json_data = $_POST['data'] ?? '[]';
      $items = json_decode($json_data, true);
      
      if(empty($items)) {
          echo json_encode(['status' => 'error', 'message' => 'Tidak ada data untuk diimpor.']);
          exit();
      }

      $success = 0;
      foreach($items as $item) {
          $data = [
              'kode_produk_lkpp' => $item['kode'] ?? '',
              'nama_produk' => $item['nama'] ?? '',
              'merk' => $item['merk'] ?? '',
              'penyedia' => $item['penyedia'] ?? '',
              'harga_katalog' => (double)str_replace(['Rp', '.', ','], ['', '', ''], $item['harga'] ?? 0),
              'satuan' => $item['satuan'] ?? '',
              'link_produk' => $item['link'] ?? '',
              'status' => 'Master',
              'last_sync' => date('Y-m-d H:i:s')
          ];
          
          if($this->db('rsns_custom_logistik_non_medis_ekatalog')->save($data)) {
              $success++;
          }
      }

      // Logging
      $user = $this->core->getUserInfo('username', null, true);
      $ip = $_SERVER['REMOTE_ADDR'];
      $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_ekatalog',
          'log_waktu' => date('Y-m-d H:i:s'),
          'log_location' => $hostname . ' | ' . $ip,
          'log_data' => 'Import E-Katalog: ' . $success . ' items | ' . $user,
          'log_status' => 'I',
          'log_username' => $user
      ]);

      echo json_encode(['status' => 'success', 'message' => "$success data berhasil diimpor."]);
      exit();
  }

  public function anyBandingkanHarga()
  {
      $nama_produk = $_POST['nama_produk'] ?? '';
      if(empty($nama_produk)) exit();

      $sql = "SELECT * FROM rsns_custom_logistik_non_medis_ekatalog 
              WHERE (no_po IS NULL OR no_po = '') 
              AND nama_produk LIKE ? 
              ORDER BY harga_katalog ASC";
      
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute(['%'.$nama_produk.'%']);
      $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $min_price = 0;
      if(!empty($rows)) {
          $min_price = $rows[0]['harga_katalog'];
      }

      echo $this->draw('pengadaan.ekatalog.compare.html', [
          'produk' => $rows, 
          'nama_cari' => $nama_produk,
          'min_price' => $min_price
      ]);
      exit();
  }

  public function getPengadaanPenerimaan()
  {
    $this->_initPenerimaan();
    $this->_initStok();
    $this->_addHeaderFiles();
    return $this->draw('pengadaan.penerimaan.html');
  }

  private function _initPenerimaan()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_penerimaan` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_penerimaan` varchar(50) NOT NULL,
        `tgl_penerimaan` date NOT NULL,
        `no_po` varchar(50) NOT NULL,
        `kode_vendor` varchar(50) NOT NULL,
        `no_faktur` varchar(100) DEFAULT NULL,
        `no_surat_jalan` varchar(100) DEFAULT NULL,
        `file_faktur` varchar(255) DEFAULT NULL,
        `file_surat_jalan` varchar(255) DEFAULT NULL,
        `kode_item` varchar(50) NOT NULL,
        `qty_terima` double NOT NULL DEFAULT 0,
        `qty_tolak` double NOT NULL DEFAULT 0,
        `batch_no` varchar(100) DEFAULT NULL,
        `tgl_expired` date DEFAULT NULL,
        `harga` double NOT NULL DEFAULT 0,
        `keterangan` text DEFAULT NULL,
        `kode_lokasi` varchar(50) DEFAULT NULL,
        `status` enum('Draft','Selesai') NOT NULL DEFAULT 'Draft',
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $upload_dir = UPLOADS . '/logistik_non_medis/penerimaan';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
  }

  private function _initStok()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_stok_batch` (
        `kode_item` varchar(50) NOT NULL,
        `kode_lokasi` varchar(50) NOT NULL,
        `batch_no` varchar(100) NOT NULL,
        `tgl_expired` date DEFAULT NULL,
        `tgl_terima` date DEFAULT NULL,
        `harga_beli` double NOT NULL DEFAULT 0,
        `stok` double NOT NULL DEFAULT 0,
        PRIMARY KEY (`kode_item`,`kode_lokasi`,`batch_no`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_pengaturan` (
        `nama_pengaturan` varchar(100) NOT NULL,
        `nilai` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`nama_pengaturan`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      // Default setting for stock method if not exists
      $cek_metode = $this->db('rsns_custom_logistik_non_medis_pengaturan')->where('nama_pengaturan', 'metode_stok')->oneArray();
      if(!$cek_metode) {
          $this->db('rsns_custom_logistik_non_medis_pengaturan')->save(['nama_pengaturan' => 'metode_stok', 'nilai' => 'FIFO']);
      }

      // Migration: Move data from old stok table to stok_batch if stok_batch is empty but old stok has data
      $count_batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')->count();
      if($count_batch == 0) {
          $old_stok = $this->db('rsns_custom_logistik_non_medis_stok')->toArray();
          foreach($old_stok as $os) {
              if($os['stok_akhir'] > 0) {
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')->save([
                      'kode_item' => $os['kode_item'],
                      'kode_lokasi' => $os['kode_lokasi'],
                      'batch_no' => '-',
                      'tgl_expired' => NULL,
                      'tgl_terima' => date('Y-m-d'),
                      'stok' => $os['stok_akhir']
                  ]);
              }
          }
      }

      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_kartu_stok` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tgl_transaksi` datetime NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `kode_lokasi` varchar(50) NOT NULL,
        `batch_no` varchar(100) DEFAULT '-',
        `tipe_transaksi` enum('Masuk','Keluar','Retur','Opname','Mutasi Masuk','Mutasi Keluar') NOT NULL,
        `no_referensi` varchar(50) NOT NULL,
        `qty_masuk` double NOT NULL DEFAULT 0,
        `qty_keluar` double NOT NULL DEFAULT 0,
        `stok_akhir` double NOT NULL DEFAULT 0,
        `harga` double NOT NULL DEFAULT 0,
        `user_input` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      // Migration for existing tables
      $check = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_kartu_stok` LIKE 'harga'")->fetch();
      if (!$check) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kartu_stok` ADD `harga` double NOT NULL DEFAULT 0 AFTER `stok_akhir` ");
      }
      $check_batch = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_kartu_stok` LIKE 'batch_no'")->fetch();
      if (!$check_batch) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kartu_stok` ADD `batch_no` varchar(100) DEFAULT '-' AFTER `kode_lokasi` ");
      }
      $check_enum = $this->db()->pdo()->query("SHOW COLUMNS FROM `rsns_custom_logistik_non_medis_kartu_stok` LIKE 'tipe_transaksi'")->fetch();
      if ($check_enum && !strpos($check_enum['Type'], 'Mutasi Masuk')) {
          $this->db()->pdo()->exec("ALTER TABLE `rsns_custom_logistik_non_medis_kartu_stok` MODIFY `tipe_transaksi` enum('Masuk','Keluar','Retur','Opname','Mutasi Masuk','Mutasi Keluar') NOT NULL");
      }
  }

  private function _initMutasi()
  {
      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_mutasi` (
        `no_mutasi` varchar(50) NOT NULL,
        `tgl_mutasi` date NOT NULL,
        `kode_lokasi_asal` varchar(50) NOT NULL,
        `kode_lokasi_tujuan` varchar(50) NOT NULL,
        `keterangan` text DEFAULT NULL,
        `status` enum('Draft','Dikirim','Diterima','Batal') NOT NULL DEFAULT 'Draft',
        `user_input` varchar(100) DEFAULT NULL,
        `user_terima` varchar(100) DEFAULT NULL,
        `tgl_terima` datetime DEFAULT NULL,
        `tgl_input` datetime DEFAULT NULL,
        PRIMARY KEY (`no_mutasi`),
        KEY `kode_lokasi_asal` (`kode_lokasi_asal`),
        KEY `kode_lokasi_tujuan` (`kode_lokasi_tujuan`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");

      $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_mutasi_detail` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `no_mutasi` varchar(50) NOT NULL,
        `kode_item` varchar(50) NOT NULL,
        `batch_no` varchar(100) DEFAULT '-',
        `qty` double NOT NULL DEFAULT 0,
        `satuan` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `no_mutasi` (`no_mutasi`),
        KEY `kode_item` (`kode_item`)
      ) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
  }

  private function _generateNoMutasi()
  {
      $prefix = 'MUT/' . date('Ym') . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_mutasi')
                   ->where('no_mutasi', 'LIKE', $prefix.'%')
                   ->desc('no_mutasi')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $parts = explode('/', $last['no_mutasi']);
          $last_num = (int) end($parts);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }


  private function _generateNoPenerimaan()
  {
      $prefix = 'LP/' . date('Ym') . '/';
      $last = $this->db('rsns_custom_logistik_non_medis_penerimaan')
                   ->where('no_penerimaan', 'LIKE', $prefix.'%')
                   ->desc('no_penerimaan')
                   ->limit(1)
                   ->oneArray();
      
      if ($last) {
          $parts = explode('/', $last['no_penerimaan']);
          $last_num = (int) end($parts);
          $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
      } else {
          $next_num = '0001';
      }
      
      return $prefix . $next_num;
  }

  public function anyDisplayPenerimaan()
  {
      $this->_initPenerimaan();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $query = $this->db('rsns_custom_logistik_non_medis_penerimaan')->group('no_penerimaan');
      if(!empty($cari)) {
          $query->where('no_penerimaan', 'LIKE', '%'.$cari.'%')
                ->orLike('no_po', 'LIKE', '%'.$cari.'%')
                ->orLike('kode_vendor', 'LIKE', '%'.$cari.'%');
      }
      
      $all_data = $query->toArray();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $rows = $this->db('rsns_custom_logistik_non_medis_penerimaan')->group('no_penerimaan');
      if(!empty($cari)) {
          $rows->where('no_penerimaan', 'LIKE', '%'.$cari.'%')
                ->orLike('no_po', 'LIKE', '%'.$cari.'%')
                ->orLike('kode_vendor', 'LIKE', '%'.$cari.'%');
      }
      $rows = $rows->desc('no_penerimaan')
                    ->offset($_offset)
                    ->limit($perpage)
                    ->toArray();

      echo $this->draw('pengadaan.penerimaan.display.html', [
          'penerimaan' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman
      ]);
      exit();
  }

  public function anyFormPenerimaan()
  {
      $this->_initPenerimaan();
      $this->_initPo();
      $this->_initLokasi();
      
      $pos = $this->db('rsns_custom_logistik_non_medis_po')
                  ->where('status', 'Terkirim')
                  ->orWhere('status', 'Sebagian Diterima')
                  ->toArray();
      
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();

      if (isset($_POST['no_penerimaan'])){
          $penerimaan_rows = $this->db('rsns_custom_logistik_non_medis_penerimaan')->where('no_penerimaan', $_POST['no_penerimaan'])->toArray();
          $penerimaan = $penerimaan_rows[0]; // Header info from first row
          
          // Get vendor name
          $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $penerimaan['kode_vendor'])->oneArray();
          $penerimaan['nama_vendor'] = $vendor ? $vendor['nama_vendor'] : '';
          $penerimaan['vendor_display'] = $penerimaan['kode_vendor'] . ($penerimaan['nama_vendor'] ? ' - ' . $penerimaan['nama_vendor'] : '');

          // Get item names
          $barang_rows = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
          $barang_map = [];
          foreach($barang_rows as $b) { $barang_map[$b['kode_item']] = $b['nama_barang']; }

          $penerimaan['detail_items'] = [];
          foreach($penerimaan_rows as $row) {
              $row['nama_barang'] = $barang_map[$row['kode_item']] ?? $row['kode_item'];
              // Fetch qty_po from PO table for this item if possible
              $qty_po = 0;
              $po = $this->db('rsns_custom_logistik_non_medis_po')->where('no_po', $penerimaan['no_po'])->oneArray();
              if($po) {
                  $po_items = json_decode($po['detail_items'], true);
                  foreach($po_items as $pi) {
                      if($pi['kode_item'] == $row['kode_item']) {
                          $qty_po = $pi['qty_pesan'] ?? 0;
                          break;
                      }
                  }
              }

              // Map columns to template keys
              $penerimaan['detail_items'][] = [
                  'kode_item' => $row['kode_item'],
                  'nama_barang' => $row['nama_barang'],
                  'qty_po' => $qty_po,
                  'qty_terima' => $row['qty_terima'],
                  'qty_tolak' => $row['qty_tolak'],
                  'batch_no' => $row['batch_no'],
                  'tgl_expired' => $row['tgl_expired'],
                  'harga' => $row['harga'],
                  'keterangan' => $row['keterangan']
              ];
          }
          echo $this->draw('pengadaan.penerimaan.form.html', ['penerimaan' => $penerimaan, 'mode' => 'edit', 'pos' => $pos, 'lokasi' => $lokasi]);
      } else {
          $penerimaan = [
              'no_penerimaan' => $this->_generateNoPenerimaan(),
              'tgl_penerimaan' => date('Y-m-d'),
              'no_po' => '',
              'kode_vendor' => '',
              'no_faktur' => '',
              'no_surat_jalan' => '',
              'file_faktur' => '',
              'file_surat_jalan' => '',
              'detail_items' => [],
              'status' => 'Draft'
          ];
          echo $this->draw('pengadaan.penerimaan.form.html', ['penerimaan' => $penerimaan, 'mode' => 'add', 'pos' => $pos, 'lokasi' => $lokasi]);
      }
      exit();
  }

  public function anyLoadItemsFromPO()
  {
      $no_po = $_POST['no_po'] ?? '';
      $po = $this->db('rsns_custom_logistik_non_medis_po')->where('no_po', $no_po)->oneArray();
      if($po) {
          $items = json_decode($po['detail_items'], true);
          
          // Get all item details to display
          $barang_rows = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
          $barang_map = [];
          $loc_map = [];
          foreach($barang_rows as $b) {
              $barang_map[$b['kode_item']] = $b['nama_barang'];
              $loc_map[$b['kode_item']] = $b['default_kode_lokasi'];
          }

          foreach($items as &$item) {
              $item['nama_barang'] = $barang_map[$item['kode_item']] ?? $item['kode_item'];
              $item['default_kode_lokasi'] = $loc_map[$item['kode_item']] ?? '';
              $item['qty_po'] = $item['qty_pesan'] ?? 0;
              $item['qty_terima'] = $item['qty_po'];
              $item['qty_tolak'] = 0;
              $item['harga'] = $item['harga_satuan'] ?? 0;
              $item['batch_no'] = '';
              $item['tgl_expired'] = '';
              $item['keterangan'] = '';
          }
          $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $po['kode_vendor'])->oneArray();
          $nama_vendor = ($vendor) ? $vendor['nama_vendor'] : $po['kode_vendor'];

          echo json_encode(['status' => 'success', 'items' => $items, 'kode_vendor' => $po['kode_vendor'], 'nama_vendor' => $nama_vendor]);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'PO tidak ditemukan']);
      }
      exit();
  }

  public function anySearchItemByBarcode()
  {
      $barcode = $_POST['barcode'] ?? '';
      $no_po = $_POST['no_po'] ?? '';

      $item = $this->db('rsns_custom_logistik_non_medis_master_barang')
                   ->where('barcode', $barcode)
                   ->orWhere('kode_item', $barcode)
                   ->oneArray();

      if($item) {
          // Check if item is in PO if no_po is provided
          $in_po = true;
          $qty_po = 0;
          $harga = $item['harga_referensi'];

          if(!empty($no_po)) {
              $po = $this->db('rsns_custom_logistik_non_medis_po')->where('no_po', $no_po)->oneArray();
              if($po) {
                  $po_items = json_decode($po['detail_items'], true);
                  $found_in_po = false;
                  foreach($po_items as $pi) {
                      if($pi['kode_item'] == $item['kode_item']) {
                          $found_in_po = true;
                          $qty_po = $pi['qty_pesan'] ?? 0;
                          $harga = $pi['harga_satuan'] ?? $harga;
                          break;
                      }
                  }
                  if(!$found_in_po) $in_po = false;
              }
          }

          if($in_po) {
              // Get location name for better UX
              $loc_name = '-';
              if(!empty($item['default_kode_lokasi'])) {
                  $loc = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $item['default_kode_lokasi'])->oneArray();
                  $loc_name = $loc ? $loc['nama_lokasi'] : $item['default_kode_lokasi'];
              }

              echo json_encode([
                  'status' => 'success',
                  'kode_item' => $item['kode_item'],
                  'nama_barang' => $item['nama_barang'],
                  'qty_po' => $qty_po,
                  'harga' => $harga,
                  'default_kode_lokasi' => $item['default_kode_lokasi'],
                  'nama_lokasi' => $loc_name
              ]);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Barang tidak ada dalam PO yang dipilih.']);
          }
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Barang tidak ditemukan.']);
      }
      exit();
  }

  public function postSavePenerimaan()
  {
      $this->_initHostnameTable();
      $no_penerimaan = $_POST['no_penerimaan'] ?? '';
      $status = $_POST['status'] ?? 'Draft';
      $kode_lokasi = $_POST['kode_lokasi'] ?? 'Gudang Utama'; 
      
      $items = [];
      if(isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
          foreach($_POST['kode_item'] as $key => $kode_item) {
              $items[] = [
                  'kode_item' => $kode_item,
                  'nama_barang' => $_POST['nama_barang'][$key] ?? '',
                  'qty_po' => $_POST['qty_po'][$key] ?? 0,
                  'qty_terima' => $_POST['qty_terima'][$key] ?? 0,
                  'qty_tolak' => $_POST['qty_tolak'][$key] ?? 0,
                  'harga' => $_POST['harga'][$key] ?? 0,
                  'batch_no' => $_POST['batch_no'][$key] ?? '',
                  'tgl_expired' => $_POST['tgl_expired'][$key] ?? '',
                  'keterangan' => $_POST['keterangan_item'][$key] ?? ''
              ];
          }
      }

      // Prepare header data (to be repeated or used for all rows)
      $header = [
          'no_penerimaan' => $no_penerimaan,
          'tgl_penerimaan' => $_POST['tgl_penerimaan'] ?? date('Y-m-d'),
          'no_po' => $_POST['no_po'] ?? '',
          'kode_vendor' => $_POST['kode_vendor'] ?? '',
          'no_faktur' => $_POST['no_faktur'] ?? '',
          'no_surat_jalan' => $_POST['no_surat_jalan'] ?? '',
          'kode_lokasi' => $kode_lokasi,
          'status' => $status,
          'user_input' => $this->core->getUserInfo('username', null, true)
      ];

      $upload_dir = UPLOADS . '/logistik_non_medis/penerimaan';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

      // Handle existing files for header
      $cek_existing = $this->db('rsns_custom_logistik_non_medis_penerimaan')->where('no_penerimaan', $no_penerimaan)->oneArray();
      if($cek_existing) {
          $header['file_faktur'] = $cek_existing['file_faktur'];
          $header['file_surat_jalan'] = $cek_existing['file_surat_jalan'];
      }

      if(isset($_FILES['file_faktur']) && $_FILES['file_faktur']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['file_faktur']['name'], PATHINFO_EXTENSION));
          $filename = 'faktur_' . str_replace('/', '_', $no_penerimaan) . '_' . time() . '.' . $ext;
          if(move_uploaded_file($_FILES['file_faktur']['tmp_name'], $upload_dir . '/' . $filename)) {
              $header['file_faktur'] = $filename;
          }
      }

      if(isset($_FILES['file_surat_jalan']) && $_FILES['file_surat_jalan']['error'] == 0) {
          $ext = strtolower(pathinfo($_FILES['file_surat_jalan']['name'], PATHINFO_EXTENSION));
          $filename = 'sj_' . str_replace('/', '_', $no_penerimaan) . '_' . time() . '.' . $ext;
          if(move_uploaded_file($_FILES['file_surat_jalan']['tmp_name'], $upload_dir . '/' . $filename)) {
              $header['file_surat_jalan'] = $filename;
          }
      }

      // Start Transaction-like process (Delete then Re-insert)
      $this->db('rsns_custom_logistik_non_medis_penerimaan')->where('no_penerimaan', $no_penerimaan)->delete();

      $success_count = 0;
      if(isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
          foreach($_POST['kode_item'] as $key => $kode_item) {
              $data = array_merge($header, [
                  'kode_item' => $kode_item,
                  'qty_terima' => $_POST['qty_terima'][$key] ?? 0,
                  'qty_tolak' => $_POST['qty_tolak'][$key] ?? 0,
                  'batch_no' => $_POST['batch_no'][$key] ?? '',
                  'tgl_expired' => $_POST['tgl_expired'][$key] ?? NULL,
                  'harga' => $_POST['harga'][$key] ?? 0,
                  'keterangan' => $_POST['keterangan_item'][$key] ?? ''
              ]);
              if(empty($data['tgl_expired'])) $data['tgl_expired'] = NULL;
              
              if($this->db('rsns_custom_logistik_non_medis_penerimaan')->save($data)) {
                  $success_count++;
              }
          }
      }

      if($success_count > 0) {
          $query = true;
          if($status == 'Selesai') {
              if(isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
                  foreach($_POST['kode_item'] as $key => $kode_item) {
                      $qty_terima = $_POST['qty_terima'][$key] ?? 0;
                      if($qty_terima > 0) {
                          $harga_item = $_POST['harga'][$key] ?? 0;
                          $batch_no = $_POST['batch_no'][$key] ?? '-';
                          $tgl_expired = $_POST['tgl_expired'][$key] ?? NULL;
                          $this->_updateStok($kode_item, $kode_lokasi, $qty_terima, 'Masuk', $no_penerimaan, $harga_item, $batch_no, $tgl_expired);
                      }
                  }
              }
              $this->_updatePOStatus($header['no_po']);
          }
          
          // Logging
          $user = $this->core->getUserInfo('username', null, true);
          $ip = $_SERVER['REMOTE_ADDR'];
          $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_penerimaan',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'Save Penerimaan: ' . $no_penerimaan . ' | PO: ' . ($header['no_po'] ?? '-') . ' | Status: ' . $status . ' | ' . $user,
              'log_status' => 'I',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      } else {
          echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
      }
      exit();
  }

  public function postHapusPenerimaan()
  {
      $no_penerimaan = $_POST['no_penerimaan'] ?? '';
      $cek = $this->db('rsns_custom_logistik_non_medis_penerimaan')->where('no_penerimaan', $no_penerimaan)->oneArray();
      if($cek) {
          if($cek['status'] == 'Selesai') {
              echo json_encode(['status' => 'error', 'message' => 'Data yang sudah selesai tidak dapat dihapus']);
              exit();
          }
          $this->db('rsns_custom_logistik_non_medis_penerimaan')->where('no_penerimaan', $no_penerimaan)->delete();
          
          // Logging
          $user = $this->core->getUserInfo('username', null, true);
          $ip = $_SERVER['REMOTE_ADDR'];
          $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_penerimaan',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $hostname . ' | ' . $ip,
              'log_data' => 'Delete Penerimaan: ' . $no_penerimaan,
              'log_status' => 'D',
              'log_username' => $user
          ]);

          echo json_encode(['status' => 'success']);
      }
      exit();
  }

  private function _getMetodeStok()
  {
      $metode = $this->db('rsns_custom_logistik_non_medis_pengaturan')->where('nama_pengaturan', 'metode_stok')->oneArray();
      return $metode['nilai'] ?? 'FIFO';
  }

  private function _updateStok($kode_item, $kode_lokasi, $qty, $tipe, $no_ref, $harga = 0, $batch_no = '-', $tgl_expired = NULL)
  {
      $this->_initStok();
      
      if($tipe == 'Masuk' || $tipe == 'Retur' || $tipe == 'Opname') {
          if($tipe == 'Opname') {
              // For Opname, we reset the batch stock. 
              $current_batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                                    ->where('kode_item', $kode_item)
                                    ->where('kode_lokasi', $kode_lokasi)
                                    ->where('batch_no', $batch_no)
                                    ->oneArray();
              $stok_awal = $current_batch ? $current_batch['stok'] : 0;
              $qty_masuk = ($qty > $stok_awal) ? ($qty - $stok_awal) : 0;
              $qty_keluar = ($qty < $stok_awal) ? ($stok_awal - $qty) : 0;
              $stok_akhir = $qty;

              if($current_batch) {
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')
                       ->where('kode_item', $kode_item)
                       ->where('kode_lokasi', $kode_lokasi)
                       ->where('batch_no', $batch_no)
                       ->update(['stok' => $qty]);
              } else {
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')->save([
                      'kode_item' => $kode_item,
                      'kode_lokasi' => $kode_lokasi,
                      'batch_no' => $batch_no,
                      'tgl_expired' => $tgl_expired,
                      'tgl_terima' => date('Y-m-d'),
                      'stok' => $qty,
                      'harga_beli' => $harga
                  ]);
              }
              
              $this->_recordKartuStok($kode_item, $kode_lokasi, $batch_no, $tipe, $no_ref, $qty_masuk, $qty_keluar, $stok_akhir, $harga);

          } else {
              // Masuk or Retur
              $current_batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                                    ->where('kode_item', $kode_item)
                                    ->where('kode_lokasi', $kode_lokasi)
                                    ->where('batch_no', $batch_no)
                                    ->oneArray();
              
              if($current_batch) {
                  $stok_baru = $current_batch['stok'] + $qty;
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')
                       ->where('kode_item', $kode_item)
                       ->where('kode_lokasi', $kode_lokasi)
                       ->where('batch_no', $batch_no)
                       ->update(['stok' => $stok_baru]);
              } else {
                  $stok_baru = $qty;
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')->save([
                      'kode_item' => $kode_item,
                      'kode_lokasi' => $kode_lokasi,
                      'batch_no' => $batch_no,
                      'tgl_expired' => $tgl_expired,
                      'tgl_terima' => date('Y-m-d'),
                      'stok' => $qty,
                      'harga_beli' => $harga
                  ]);
              }
              $this->_recordKartuStok($kode_item, $kode_lokasi, $batch_no, $tipe, $no_ref, $qty, 0, $stok_baru, $harga);
          }
      } else {
          // Keluar
          if($batch_no == '-') {
              $picks = $this->_autoPickBatch($kode_item, $kode_lokasi, $qty);
              foreach($picks as $pick) {
                  $this->db('rsns_custom_logistik_non_medis_stok_batch')
                       ->where('kode_item', $kode_item)
                       ->where('kode_lokasi', $kode_lokasi)
                       ->where('batch_no', $pick['batch_no'])
                       ->update(['stok' => $pick['sisa_batch']]);
                  
                  $this->_recordKartuStok($kode_item, $kode_lokasi, $pick['batch_no'], $tipe, $no_ref, 0, $pick['qty_ambil'], $pick['sisa_batch'], $harga);
              }
          } else {
              // Specific batch issuance
              $current_batch = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                                    ->where('kode_item', $kode_item)
                                    ->where('kode_lokasi', $kode_lokasi)
                                    ->where('batch_no', $batch_no)
                                    ->oneArray();
              
              if($current_batch && !empty($current_batch['tgl_expired']) && $current_batch['tgl_expired'] < date('Y-m-d')) {
                  // Block expired batch issuance
                  // We might want to throw an error or just skip. For now, skip or log.
                  // Since this is a private method, we assume the caller handled validation, but we add safety.
                  return; 
              }

              $stok_baru = ($current_batch ? $current_batch['stok'] : 0) - $qty;
              $this->db('rsns_custom_logistik_non_medis_stok_batch')
                   ->where('kode_item', $kode_item)
                   ->where('kode_lokasi', $kode_lokasi)
                   ->where('batch_no', $batch_no)
                   ->update(['stok' => $stok_baru]);
              
              $this->_recordKartuStok($kode_item, $kode_lokasi, $batch_no, $tipe, $no_ref, 0, $qty, $stok_baru, $harga);
          }
      }
  }

  private function _autoPickBatch($kode_item, $kode_lokasi, $qty_needed)
  {
      $metode = $this->_getMetodeStok();
      $query = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                    ->where('kode_item', $kode_item)
                    ->where('kode_lokasi', $kode_lokasi)
                    ->where('stok', '>', 0);
      
      if($metode == 'FEFO') {
          $query->asc('tgl_expired')->asc('tgl_terima');
      } else {
          $query->asc('tgl_terima');
      }

      $batches = $query->toArray();
      $picks = [];
      $remaining = $qty_needed;

      foreach($batches as $b) {
          if($remaining <= 0) break;

          if(!empty($b['tgl_expired']) && $b['tgl_expired'] < date('Y-m-d')) {
              continue; 
          }

          $take = min($remaining, $b['stok']);
          $picks[] = [
              'batch_no' => $b['batch_no'],
              'qty_ambil' => $take,
              'sisa_batch' => $b['stok'] - $take
          ];
          $remaining -= $take;
      }

      return $picks;
  }

  private function _recordKartuStok($kode_item, $kode_lokasi, $batch_no, $tipe, $no_ref, $masuk, $keluar, $akhir, $harga)
  {
      $user = $this->core->getUserInfo('username', null, true);
      $this->db('rsns_custom_logistik_non_medis_kartu_stok')->save([
          'tgl_transaksi' => date('Y-m-d H:i:s'),
          'kode_item' => $kode_item,
          'kode_lokasi' => $kode_lokasi,
          'batch_no' => $batch_no,
          'tipe_transaksi' => $tipe,
          'no_referensi' => $no_ref,
          'qty_masuk' => $masuk,
          'qty_keluar' => $keluar,
          'stok_akhir' => $akhir,
          'harga' => $harga,
          'user_input' => $user
      ]);

      // Logging for Stock Movement
      $ip = $_SERVER['REMOTE_ADDR'];
      $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
      $log_data = "$kode_item | $kode_lokasi | $batch_no | $tipe | $no_ref | In:$masuk | Out:$keluar | End:$akhir | $user";
      $this->db('mlite_tracksql')->save([
          'log_id' => NULL,
          'log_modul' => 'logistik_non_medis_stok_batch',
          'log_waktu' => date('Y-m-d H:i:s'),
          'log_location' => $hostname . ' | ' . $ip,
          'log_data' => $log_data,
          'log_status' => ($tipe == 'Masuk' || $tipe == 'Retur') ? 'I' : 'U',
          'log_username' => $user
      ]);
  }



  private function _updatePOStatus($no_po)
  {
      $po = $this->db('rsns_custom_logistik_non_medis_po')->where('no_po', $no_po)->oneArray();
      if(!$po) return;

      $po_items = json_decode($po['detail_items'], true);
      $receipts = $this->db('rsns_custom_logistik_non_medis_penerimaan')
                       ->where('no_po', $no_po)
                       ->where('status', 'Selesai')
                       ->toArray();
      
      $received_qty = [];
      foreach($receipts as $rcp) {
          $items = json_decode($rcp['detail_items'], true);
          foreach($items as $item) {
              if(!isset($received_qty[$item['kode_item']])) $received_qty[$item['kode_item']] = 0;
              $received_qty[$item['kode_item']] += $item['qty_terima'];
          }
      }
      
      $all_received = true;
      $any_received = false;
      foreach($po_items as $item) {
          $target = $item['jumlah'];
          $actual = $received_qty[$item['kode_item']] ?? 0;
          if($actual < $target) $all_received = false;
          if($actual > 0) $any_received = true;
      }
      
      $new_status = 'Terkirim';
      if($all_received) {
          $new_status = 'Selesai';
      } elseif($any_received) {
          $new_status = 'Sebagian Diterima';
      }
      $this->db('rsns_custom_logistik_non_medis_po')->where('no_po', $no_po)->update(['status' => $new_status]);
  }

  public function anyCetakBAST($no_penerimaan = '')
  {
      if(empty($no_penerimaan)) $no_penerimaan = $_GET['no_penerimaan'] ?? $_POST['no_penerimaan'] ?? '';
      
      $penerimaan = $this->db('rsns_custom_logistik_non_medis_penerimaan')->where('no_penerimaan', $no_penerimaan)->oneArray();
      if(!$penerimaan) {
          echo "Data tidak ditemukan.";
          exit();
      }

      $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $penerimaan['kode_vendor'])->oneArray();
      
      // Fetch all items for this receipt number from the flattened table
      $items = $this->db('rsns_custom_logistik_non_medis_penerimaan')
                    ->join('rsns_custom_logistik_non_medis_master_barang', 'rsns_custom_logistik_non_medis_master_barang.kode_item = rsns_custom_logistik_non_medis_penerimaan.kode_item')
                    ->where('no_penerimaan', $no_penerimaan)
                    ->toArray();
      
      // Add index for loop
      foreach($items as $idx => &$item) {
          $item['index'] = $idx;
      }

      echo $this->draw('pengadaan.penerimaan.cetak.html', [
          'penerimaan' => $penerimaan,
          'vendor' => $vendor,
          'items' => $items
      ]);
      exit();
  }

  public function anyCetakLabelPenerimaan()
  {
      $item = [
          'kode_item' => $_GET['kode_item'] ?? '',
          'nama_barang' => $_GET['nama_barang'] ?? '',
          'batch_no' => $_GET['batch_no'] ?? '-',
          'tgl_expired' => $_GET['tgl_expired'] ?? '-',
          'tgl_penerimaan' => $_GET['tgl_penerimaan'] ?? date('d/m/Y'),
          'kode_lokasi' => $_GET['kode_lokasi'] ?? '-'
      ];

      echo $this->draw('pengadaan.penerimaan.label.html', ['item' => $item]);
      exit();
  }


  // --- GUDANG MODULE ---

  public function getGudangManage()
  {
      $this->_addHeaderFiles();
      return $this->draw('gudang.manage.html');
  }

  public function getGudangPenerimaan()
  {
      $this->_initPenerimaan();
      $this->_initStok();
      $this->_addHeaderFiles();
      return $this->draw('gudang.penerimaan.html');
  }

  public function anyDisplayGudangPenerimaan()
  {
      $this->_initPenerimaan();
      $this->_initPo();
      $this->_initStok();
      
      // 1. Get Pending POs (POs that are Sent or Partially Received)
      $pending_pos = $this->db('rsns_custom_logistik_non_medis_po')
                          ->where('status', 'Terkirim')
                          ->orWhere('status', 'Sebagian Diterima')
                          ->desc('tgl_po')
                          ->toArray();
      
      // 2. Get Recent Receipts (using raw SQL for compatibility)
      $sql = "SELECT no_penerimaan, MAX(tgl_penerimaan) as tgl_penerimaan, MAX(no_po) as no_po FROM rsns_custom_logistik_non_medis_penerimaan GROUP BY no_penerimaan ORDER BY tgl_penerimaan DESC LIMIT 10";
      $recent_receipts = $this->db()->pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

      echo $this->draw('gudang.penerimaan.display.html', [
          'pending_pos' => $pending_pos,
          'recent_receipts' => $recent_receipts,
          'admin_mode' => $this->settings->get('settings.admin_mode')
      ]);
      exit();
  }

  public function anyFormGudangPenerimaan()
  {
      $this->_initPenerimaan();
      $this->_initPo();
      $this->_initLokasi();
      
      $no_po = $_POST['no_po'] ?? '';
      $no_penerimaan = $_POST['no_penerimaan'] ?? '';
      
      $penerimaan = [
          'no_penerimaan' => $no_penerimaan ?: $this->_generateNoPenerimaan(),
          'tgl_penerimaan' => date('Y-m-d'),
          'no_po' => $no_po,
          'kode_vendor' => '',
          'no_faktur' => '',
          'no_surat_jalan' => '',
          'kode_lokasi' => '',
          'status' => 'Draft',
          'detail_items' => []
      ];

      if (!empty($no_penerimaan)) {
          $rows = $this->db('rsns_custom_logistik_non_medis_penerimaan')->where('no_penerimaan', $no_penerimaan)->toArray();
          if ($rows) {
              $penerimaan = array_merge($penerimaan, $rows[0]);
              $penerimaan['detail_items'] = $rows;
          }
      }

      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      $pos = $this->db('rsns_custom_logistik_non_medis_po')->where('status', 'Terkirim')->orWhere('status', 'Sebagian Diterima')->toArray();

      echo $this->draw('gudang.penerimaan.form.html', [
          'penerimaan' => $penerimaan,
          'lokasi' => $lokasi,
          'pos' => $pos,
          'mode' => empty($no_penerimaan) ? 'add' : 'edit'
      ]);
      exit();
  }

  public function getGudangLokasi()
  {
      $this->_initLokasi();
      $this->_addHeaderFiles();
      
      $query = "SELECT 
                    l.*,
                    COALESCE(SUM(sb.stok), 0) as stok_saat_ini,
                    (COALESCE(SUM(sb.stok), 0) / l.kapasitas * 100) as utilisasi
                FROM rsns_custom_logistik_non_medis_lokasi_gudang l
                LEFT JOIN rsns_custom_logistik_non_medis_stok_batch sb ON l.kode_lokasi = sb.kode_lokasi
                GROUP BY l.kode_lokasi";
      $lokasi = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

      // Grouping for digital map
      $map = [];
      foreach($lokasi as $l) {
          $rak = $l['rak'] ?: 'Tanpa Rak';
          if(!isset($map[$rak])) $map[$rak] = [];
          $map[$rak][] = $l;
      }

      return $this->draw('gudang.lokasi.html', ['lokasi' => $lokasi, 'map' => $map]);
  }

  public function anyRelokasiBin()
  {
      $this->_initLokasi();
      $this->_initStok();
      
      if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
          $kode_item = $_POST['kode_item'] ?? '';
          $batch_no = $_POST['batch_no'] ?? '';
          $asal = $_POST['kode_lokasi_asal'] ?? '';
          $tujuan = $_POST['kode_lokasi_tujuan'] ?? '';
          $qty = (double)($_POST['qty'] ?? 0);
          
          if(empty($kode_item) || empty($asal) || empty($tujuan) || $qty <= 0) {
              echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
              exit();
          }

          // Validation: Compatibility
          $loc_asal = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $asal)->oneArray();
          $loc_tujuan = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('kode_lokasi', $tujuan)->oneArray();
          
          if($loc_tujuan['tipe_penyimpanan'] == 'Hazardous' && $loc_asal['tipe_penyimpanan'] != 'Hazardous') {
              // Warning or block if moving normal to hazardous? Usually other way around is critical.
          }

          $batch_info = $this->db('rsns_custom_logistik_non_medis_stok_batch')
                             ->where('kode_item', $kode_item)
                             ->where('kode_lokasi', $asal)
                             ->where('batch_no', $batch_no)
                             ->oneArray();
          
          if(!$batch_info || $batch_info['stok'] < $qty) {
              echo json_encode(['status' => 'error', 'message' => 'Stok di lokasi asal tidak mencukupi']);
              exit();
          }

          $no_ref = 'REL-' . date('YmdHis');
          $this->_updateStok($kode_item, $asal, $qty, 'Keluar', $no_ref, $batch_info['harga_beli'], $batch_no);
          $this->_updateStok($kode_item, $tujuan, $qty, 'Masuk', $no_ref, $batch_info['harga_beli'], $batch_no, $batch_info['tgl_expired']);
          
          // Log movement
          $user = $this->core->getUserInfo('username', null, true);
          $this->db('mlite_tracksql')->save([
              'log_id' => NULL,
              'log_modul' => 'logistik_non_medis_relokasi',
              'log_waktu' => date('Y-m-d H:i:s'),
              'log_location' => $_SERVER['REMOTE_ADDR'],
              'log_data' => "Relokasi $kode_item ($batch_no) dari $asal ke $tujuan sejumlah $qty | $user",
              'log_status' => 'U',
              'log_username' => $user
          ]);
          
          echo json_encode(['status' => 'success']);
          exit();
      }

      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('status', 'Aktif')->toArray();
      echo $this->draw('gudang.lokasi.relokasi.html', ['lokasi' => $lokasi]);
      exit();
  }

  public function anyLoadStokLokasi()
  {
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      $sql = "SELECT sb.*, b.nama_barang, b.satuan_dasar
              FROM rsns_custom_logistik_non_medis_stok_batch sb
              JOIN rsns_custom_logistik_non_medis_master_barang b ON sb.kode_item = b.kode_item
              WHERE sb.kode_lokasi = ? AND sb.stok > 0";
      $stmt = $this->db()->pdo()->prepare($sql);
      $stmt->execute([$kode_lokasi]);
      $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      echo json_encode($items);
      exit();
  }

  public function getGudangStok()
  {
      $this->_initStok();
      $this->_addHeaderFiles();
      return $this->draw('gudang.stok.html');
  }

  public function anyDisplayGudangStok()
  {
      $this->_initStok();
      $perpage = 20;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      $lokasi = isset($_POST['lokasi']) ? $_POST['lokasi'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $where = "WHERE 1=1";
      if(!empty($cari)) {
          $where .= " AND (b.nama_barang LIKE '%$cari%' OR b.kode_item LIKE '%$cari%')";
      }
      if(!empty($lokasi)) {
          $where .= " AND sb.kode_lokasi = '$lokasi'";
      }

      // Main aggregate query
      $query = "SELECT 
                    b.kode_item, 
                    b.nama_barang, 
                    b.satuan_dasar, 
                    b.stok_min, 
                    b.stok_max, 
                    b.safety_stock,
                    SUM(sb.stok) as stok_akhir, 
                    COALESCE(sb.kode_lokasi, '-') as kode_lokasi,
                    COALESCE(l.nama_lokasi, 'Belum Ada Stok') as nama_lokasi,
                    GROUP_CONCAT(CONCAT('<b>Batch:</b> ', sb.batch_no, ' | <b>Stok:</b> ', sb.stok, ' <br><small>Exp: ', COALESCE(sb.tgl_expired, '-'), '</small>') SEPARATOR '<hr style=\"margin:5px 0; border-color:#455a64;\">') as batch_detail,
                    MIN(sb.tgl_expired) as near_expired
                FROM rsns_custom_logistik_non_medis_master_barang b
                LEFT JOIN rsns_custom_logistik_non_medis_stok_batch sb ON b.kode_item = sb.kode_item
                LEFT JOIN rsns_custom_logistik_non_medis_lokasi_gudang l ON sb.kode_lokasi = l.kode_lokasi
                $where
                GROUP BY b.kode_item, sb.kode_lokasi";
      
      $rows_all = $this->db()->pdo()->query($query)->fetchAll();
      $jumlah_data = count($rows_all);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $query .= " ORDER BY b.nama_barang ASC LIMIT $_offset, $perpage";
      $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

      foreach($rows as &$row) {
          $row['harga'] = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $row['kode_item'])->oneArray()['harga_referensi'] ?? 0;
          
          // Expiry status
          $row['exp_status'] = 'normal';
          if(!empty($row['near_expired'])) {
              $diff = (strtotime($row['near_expired']) - time()) / (60 * 60 * 24);
              if($diff < 0) $row['exp_status'] = 'expired';
              elseif($diff < 30) $row['exp_status'] = 'near';
          }
      }

      echo $this->draw('gudang.stok.display.html', [
          'stok' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman
      ]);
      exit();
  }


  public function getGudangMutasi()
  {
      $this->_initMutasi();
      $this->_addHeaderFiles();
      $items = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->toArray();
      $kode_item = $_GET['kode_item'] ?? '';
      return $this->draw('gudang.mutasi.html', [
          'items' => $items, 
          'lokasi' => $lokasi,
          'selected_item' => $kode_item
      ]);
  }

  public function anyDisplayMutasi()
  {
      $this->_initMutasi();
      $perpage = 10;
      $halaman = isset($_POST['halaman']) ? (int)$_POST['halaman'] : 1;
      $cari = isset($_POST['cari']) ? $_POST['cari'] : '';
      
      $_offset = ($halaman - 1) * $perpage;
      
      $where = "";
      if(!empty($cari)) {
          $where = " WHERE no_mutasi LIKE '%$cari%' OR kode_lokasi_asal LIKE '%$cari%' OR kode_lokasi_tujuan LIKE '%$cari%' ";
      }

      $query_all = "SELECT * FROM rsns_custom_logistik_non_medis_mutasi $where";
      $all_data = $this->db()->pdo()->query($query_all)->fetchAll();
      $jumlah_data = count($all_data);
      $jml_halaman = ceil($jumlah_data / $perpage);
      
      $query = "SELECT m.*, l1.nama_lokasi as asal, l2.nama_lokasi as tujuan 
                FROM rsns_custom_logistik_non_medis_mutasi m
                LEFT JOIN rsns_custom_logistik_non_medis_lokasi_gudang l1 ON m.kode_lokasi_asal = l1.kode_lokasi
                LEFT JOIN rsns_custom_logistik_non_medis_lokasi_gudang l2 ON m.kode_lokasi_tujuan = l2.kode_lokasi
                $where
                ORDER BY m.tgl_input DESC LIMIT $_offset, $perpage";
      $rows = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

      echo $this->draw('gudang.mutasi.display.html', [
          'mutasi' => $rows,
          'halaman' => $halaman,
          'jumlah_data' => $jumlah_data,
          'jml_halaman' => $jml_halaman,
          'is_history' => false
      ]);
      exit();
  }

  public function anyFormMutasi()
  {
      $this->_initMutasi();
      $this->_initLokasi();
      $lokasi = $this->db('rsns_custom_logistik_non_medis_lokasi_gudang')->where('status', 'Aktif')->toArray();
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
          echo $this->draw('gudang.mutasi.form.html', ['mutasi' => $mutasi, 'mode' => 'edit', 'lokasi' => $lokasi]);
      } else {
          $mutasi = [
              'no_mutasi' => $this->_generateNoMutasi(),
              'tgl_mutasi' => date('Y-m-d'),
              'kode_lokasi_asal' => '',
              'kode_lokasi_tujuan' => '',
              'keterangan' => '',
              'status' => 'Draft',
              'details' => []
          ];
          echo $this->draw('gudang.mutasi.form.html', ['mutasi' => $mutasi, 'mode' => 'add', 'lokasi' => $lokasi]);
      }
      exit();
  }

  public function anyLoadItemsForMutasi()
  {
      $kode_lokasi = $_POST['kode_lokasi'] ?? '';
      if(empty($kode_lokasi)) {
          echo json_encode(['status' => 'error', 'message' => 'Lokasi asal harus dipilih']);
          exit();
      }

      $query = "SELECT sb.*, b.nama_barang, b.satuan_dasar
                FROM rsns_custom_logistik_non_medis_stok_batch sb
                JOIN rsns_custom_logistik_non_medis_master_barang b ON sb.kode_item = b.kode_item
                WHERE sb.kode_lokasi = '$kode_lokasi' AND sb.stok > 0
                ORDER BY b.nama_barang ASC";
      $items = $this->db()->pdo()->query($query)->fetchAll(\PDO::FETCH_ASSOC);

      echo json_encode(['status' => 'success', 'items' => $items]);
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

      if(!$cek) {
          $data = [
              'no_mutasi' => $no_mutasi,
              'tgl_mutasi' => $_POST['tgl_mutasi'] ?? date('Y-m-d'),
              'kode_lokasi_asal' => $_POST['kode_lokasi_asal'] ?? '',
              'kode_lokasi_tujuan' => $_POST['kode_lokasi_tujuan'] ?? '',
              'keterangan' => $_POST['keterangan'] ?? '',
              'status' => 'Draft',
              'user_input' => $user,
              'tgl_input' => date('Y-m-d H:i:s')
          ];
          $this->db('rsns_custom_logistik_non_medis_mutasi')->save($data);
      } else {
          $data_update = [
              'tgl_mutasi' => $_POST['tgl_mutasi'] ?? date('Y-m-d'),
              'kode_lokasi_tujuan' => $_POST['kode_lokasi_tujuan'] ?? '',
              'keterangan' => $_POST['keterangan'] ?? ''
          ];
          // Only update origin if it's actually sent (not disabled)
          if(isset($_POST['kode_lokasi_asal']) && !empty($_POST['kode_lokasi_asal'])) {
              $data_update['kode_lokasi_asal'] = $_POST['kode_lokasi_asal'];
          }
          $this->db('rsns_custom_logistik_non_medis_mutasi')->where('no_mutasi', $no_mutasi)->update($data_update);
      }

      // Save Details
      $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->where('no_mutasi', $no_mutasi)->delete();
      if(isset($_POST['kode_item']) && is_array($_POST['kode_item'])) {
          foreach($_POST['kode_item'] as $key => $kode_item) {
              $this->db('rsns_custom_logistik_non_medis_mutasi_detail')->save([
                  'no_mutasi' => $no_mutasi,
                  'kode_item' => $kode_item,
                  'batch_no' => $_POST['batch_no'][$key] ?? '-',
                  'qty' => $_POST['qty'][$key] ?? 0,
                  'satuan' => $_POST['satuan'][$key] ?? ''
              ]);
          }
      }

      $this->_logAction('logistik_non_medis_mutasi', 'Simpan Mutasi: ' . $no_mutasi);
      echo json_encode(['status' => 'success']);
      exit();
  }

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
