import re

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update getAsetKib
target_getAsetKib = """  public function getAsetKib()
  {
      $this->_initAset();
      $this->_initUnit();
      $this->_addHeaderFiles();
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->where('status', 'Aktif')->toArray();
      return $this->draw('aset.kib.html', ['units' => $units]);
  }"""
replace_getAsetKib = """  public function getAsetKib()
  {
      $this->_initAset();
      $this->_initUnit();
      $this->_addHeaderFiles();
      $units = $this->db('rsns_custom_logistik_non_medis_unit')->where('status', 'Aktif')->toArray();
      $kategori = $this->db('rsns_custom_logistik_non_medis_kategori_aset')->where('status_aktif', 'Aktif')->toArray();
      return $this->draw('aset.kib.html', ['units' => $units, 'kategori' => $kategori]);
  }"""
content = content.replace(target_getAsetKib, replace_getAsetKib)

# 2. Update anyDisplayKib query from kib_jenis to kode_kategori_aset
target_displayKib = """      $rows_query = $this->db('rsns_custom_logistik_non_medis_aset')
                         ->where('kib_jenis', $kib)
                         ->where('status', 'Aktif');"""
replace_displayKib = """      $rows_query = $this->db('rsns_custom_logistik_non_medis_aset')
                         ->where('kode_kategori_aset', $kib)
                         ->where('status', 'Aktif');"""
content = content.replace(target_displayKib, replace_displayKib)

# 3. Update anyDisplayRekapKib
target_rekap = """  public function anyDisplayRekapKib()
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
  }"""

replace_rekap = """  public function anyDisplayRekapKib()
  {
      $this->_initAset();
      
      $kategori_aset = $this->db('rsns_custom_logistik_non_medis_kategori_aset')->where('status_aktif', 'Aktif')->toArray();
      
      $rekap_data = [];
      $kpi = [];
      
      $grand_total_barang = 0;
      $grand_total_baik = 0;
      $grand_total_ringan = 0;
      $grand_total_berat = 0;
      $grand_total_nilai = 0.0;
      
      foreach ($kategori_aset as $kat) {
          $jenis = $kat['kode_kategori'];
          $assets_in_cat = $this->db('rsns_custom_logistik_non_medis_aset')
                                ->where('kode_kategori_aset', $jenis)
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
              'nama' => $kat['nama_kategori'],
              'nama_singkat' => $kat['nama_kategori'],
              'jumlah' => $total_count,
              'kondisi_baik' => $baik,
              'kondisi_rusak_ringan' => $ringan,
              'kondisi_rusak_berat' => $berat,
              'total_nilai' => $total_nilai
          ];
          
          $kpi[$jenis] = [
              'jumlah' => $total_count,
              'total_nilai' => $total_nilai,
              'nama_kategori' => $kat['nama_kategori']
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
  }"""
content = content.replace(target_rekap, replace_rekap)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print("Admin.php KIB logic updated")
