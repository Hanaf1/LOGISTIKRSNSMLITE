import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

old_detail = '''  public function anyDetailPerencanaan()
  {
      $kode_kategori = $_POST['kode_kategori'] ?? '';
      $sql = "
          SELECT p.*,
                 u.nama_unit,
                 (SELECT SUM(qty * estimasi_harga) FROM rsns_custom_logistik_non_medis_perencanaan_detail WHERE kode_perencanaan = p.kode_perencanaan) as estimasi_anggaran,
                 (SELECT SUM(qty) FROM rsns_custom_logistik_non_medis_perencanaan_detail WHERE kode_perencanaan = p.kode_perencanaan) as total_qty
          FROM rsns_custom_logistik_non_medis_perencanaan p
          LEFT JOIN rsns_custom_logistik_non_medis_master_unit u ON p.kode_unit = u.kode_unit
          WHERE p.kode_kategori = '$kode_kategori'
      ";'''

new_detail = '''  public function anyDetailPerencanaan()
  {
      $id = $_POST['id'] ?? '';
      $sql = "
          SELECT p.*,
                 u.nama_unit,
                 (SELECT SUM(qty * estimasi_harga) FROM rsns_custom_logistik_non_medis_perencanaan_detail WHERE kode_perencanaan = p.kode_perencanaan) as estimasi_anggaran,
                 (SELECT SUM(qty) FROM rsns_custom_logistik_non_medis_perencanaan_detail WHERE kode_perencanaan = p.kode_perencanaan) as total_qty
          FROM rsns_custom_logistik_non_medis_perencanaan p
          LEFT JOIN rsns_custom_logistik_non_medis_master_unit u ON p.kode_unit = u.kode_unit
          WHERE p.kode_perencanaan = '$id'
      ";'''

c = c.replace(old_detail, new_detail)

old_form = '''  public function anyFormPerencanaan()
  {
      $this->_initPerencanaan();
      $kode_kategori = $_POST['kode_kategori'] ?? '';
      $perencanaan = [];
      $details = [];
      
      if ($kode_kategori) {
          $perencanaan = $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('kode_kategori', $kode_kategori)->oneArray();'''

new_form = '''  public function anyFormPerencanaan()
  {
      $this->_initPerencanaan();
      $id = $_POST['id'] ?? '';
      $perencanaan = [];
      $details = [];
      
      if ($id) {
          $perencanaan = $this->db('rsns_custom_logistik_non_medis_perencanaan')->where('kode_perencanaan', $id)->oneArray();'''

c = c.replace(old_form, new_form)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
