import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# Fix Rencana Rutin
c = c.replace('public function anyRencanaRutin()', 'public function getRencanarutin()')
c = c.replace(
'''      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_rutin.html', true);
      exit();''',
'''      return $this->draw('pengadaan.rencana_rutin.html');'''
)

# Fix Terima Rutin
c = c.replace('public function anyTerimaRutin()', 'public function getTerimarutin()')
c = c.replace(
'''      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.terima_rutin.html', true);
      exit();''',
'''      return $this->draw('pengadaan.terima_rutin.html');'''
)

# Fix Rencana Non Rutin
c = c.replace('public function anyRencanaNonRutin()', 'public function getRencananonrutin()')
c = c.replace(
'''      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_nonrutin.html', true);
      exit();''',
'''      return $this->draw('pengadaan.rencana_nonrutin.html');'''
)

# Fix Vendor Table Issue in Terima Rutin
c = c.replace(
'''                LEFT JOIN rsns_custom_logistik_non_medis_master_rekanan_jasa v ON v.kode_rekanan = t.kode_vendor''',
'''                LEFT JOIN rsns_custom_logistik_non_medis_vendor v ON v.kode_vendor = t.kode_vendor'''
)
c = c.replace(
'''      $sql_vendor = "SELECT kode_rekanan, nama_rekanan FROM rsns_custom_logistik_non_medis_master_rekanan_jasa ORDER BY nama_rekanan ASC";''',
'''      $sql_vendor = "SELECT kode_vendor as kode_rekanan, nama_vendor as nama_rekanan FROM rsns_custom_logistik_non_medis_vendor ORDER BY nama_vendor ASC";'''
)
c = c.replace(
'''          $vendor = $this->db('rsns_custom_logistik_non_medis_master_rekanan_jasa')->where('kode_rekanan', $terima['kode_vendor'])->oneArray();''',
'''          $vendor = $this->db('rsns_custom_logistik_non_medis_vendor')->where('kode_vendor', $terima['kode_vendor'])->oneArray();'''
)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
