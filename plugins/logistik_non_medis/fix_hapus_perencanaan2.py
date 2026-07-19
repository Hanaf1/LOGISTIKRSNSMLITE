import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

old_func = '''  public function postHapusPerencanaan()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          $cek = $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('kode_perencanaan', $id)->oneArray();
          if($cek) {
              $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('kode_perencanaan', $id)->delete();
              
              // Logging
              $user = $this->core->getUserInfo('username', null, true);
              $ip = $_SERVER['REMOTE_ADDR'];
              $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
              $this->db('mlite_tracksql')->save([
                  'log_id' => NULL,
                  'log_modul' => 'logistik_non_medis_perencanaan',
                  'log_waktu' => date('Y-m-d H:i:s'),
                  'log_location' => $hostname . ' | ' . $ip,
                  'log_data' => 'Delete Perencanaan: ' . $cek['kode_perencanaan'],
                  'log_status' => 'D',
                  'log_username' => $user
              ]);
          }
      }
      exit();
  }'''

new_func = '''  public function postHapusPerencanaan()
  {
      $id = $_POST['id'] ?? '';
      if($id) {
          $cek = $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('kode_perencanaan', $id)->oneArray();
          if($cek) {
              try {
                  $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_perencanaan_detail WHERE kode_perencanaan = '$id'");
                  $this->db()->pdo()->exec("DELETE FROM rsns_custom_logistik_non_medis_perencanaan WHERE kode_perencanaan = '$id'");
                  
                  // Logging
                  $user = $this->core->getUserInfo('username', null, true);
                  $ip = $_SERVER['REMOTE_ADDR'];
                  $hostname = $this->db('rsns_custom_hostsname_pc')->where('ip', $ip)->oneArray()['hostname'] ?? 'Unknown';
                  $this->db('mlite_tracksql')->save([
                      'log_id' => NULL,
                      'log_modul' => 'logistik_non_medis_perencanaan',
                      'log_waktu' => date('Y-m-d H:i:s'),
                      'log_location' => $hostname . ' | ' . $ip,
                      'log_data' => 'Delete Perencanaan: ' . $cek['kode_perencanaan'],
                      'log_status' => 'D',
                      'log_username' => $user
                  ]);
              } catch (\Exception $e) {
                  // Do nothing or log
              }
          }
      }
      exit();
  }'''

if old_func in c:
    c = c.replace(old_func, new_func)
    with open(path, 'w', encoding='utf-8') as f: f.write(c)
