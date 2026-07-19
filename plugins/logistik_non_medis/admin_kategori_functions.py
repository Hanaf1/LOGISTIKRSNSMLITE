import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# anyDisplayMasterKategori
c = c.replace("->desc('id')", "->desc('kode_kategori')")

# anyFormMasterKategori
old_form = """    public function anyFormMasterKategori()
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
    }"""
new_form = """    public function anyFormMasterKategori()
    {
        if (isset($_POST['kode_kategori']) && !empty($_POST['kode_kategori'])){
            $kategori = $this->db('rsns_custom_logistik_non_medis_kategori')->where('kode_kategori', $_POST['kode_kategori'])->oneArray();
            echo $this->draw('master.kategori.form.html', ['kategori' => $kategori]);
        } else {
            $kategori = [
                'kode_kategori' => '',
                'nama_kategori' => '',
                'deskripsi' => ''
            ];
            echo $this->draw('master.kategori.form.html', ['kategori' => $kategori]);
        }
        exit();
    }"""
c = c.replace(old_form, new_form)

# postSaveMasterKategori
old_save = """    public function postSaveMasterKategori()
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
            'log_status' => 'I',
            'log_username' => $user
        ]);
        
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $query = $this->db('rsns_custom_logistik_non_medis_kategori')->where('id', $_POST['id'])->update($data);
        } else {
            $query = $this->db('rsns_custom_logistik_non_medis_kategori')->save($data);
        }"""
        
new_save = """    public function postSaveMasterKategori()
    {
        $nama_kategori = $_POST['nama_kategori'] ?? '';
        $kode_kategori = $_POST['kode_kategori'] ?? '';
        
        if(empty($nama_kategori)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama Kategori wajib diisi!']);
            exit();
        }
        
        if (empty($kode_kategori)) {
            $last = $this->db('rsns_custom_logistik_non_medis_kategori')->desc('kode_kategori')->oneArray();
            if ($last) {
                $num = (int)substr($last['kode_kategori'], 4);
                $kode_kategori = 'KAT-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $kode_kategori = 'KAT-001';
            }
            $is_new = true;
        } else {
            $is_new = false;
        }
  
        $data = [
            'kode_kategori' => $kode_kategori,
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
        $logdata = ''.$data['kode_kategori'].' | '.$data['nama_kategori'].' | '.$data['deskripsi'].' | '.$user.'';
  
        $this->db('mlite_tracksql')->save([
            'log_id' => NULL,
            'log_modul' => 'logistik_non_medis_kategori',
            'log_waktu' => $tanggal_log,
            'log_location' => $log_lokasi,
            'log_data' => $logdata,
            'log_status' => $is_new ? 'I' : 'U',
            'log_username' => $user
        ]);
        
        if (!$is_new) {
            unset($data['kode_kategori']); // Don't update PK
            $query = $this->db('rsns_custom_logistik_non_medis_kategori')->where('kode_kategori', $kode_kategori)->update($data);
        } else {
            $query = $this->db('rsns_custom_logistik_non_medis_kategori')->save($data);
        }"""
c = c.replace(old_save, new_save)

# postHapusMasterKategori
c = c.replace(
    "$id = $_POST['id'] ?? '';",
    "$kode_kategori = $_POST['kode_kategori'] ?? '';"
)
c = c.replace(
    "if($id) {",
    "if($kode_kategori) {"
)
c = c.replace(
    "->where('id', $id)->oneArray();",
    "->where('kode_kategori', $kode_kategori)->oneArray();"
)
c = c.replace(
    "->where('id', $id)->delete();",
    "->where('kode_kategori', $kode_kategori)->delete();"
)


with open(path, 'w', encoding='utf-8') as f: f.write(c)
print('Done Kategori Admin functions')
