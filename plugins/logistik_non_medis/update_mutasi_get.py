import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# Update getGudangMutasi
def replace_get_gudang_mutasi(match):
    return '''    public function getGudangMutasi()
    {
        $this->_initMutasi();
        $this->_addHeaderFiles();
        $items = $this->db('rsns_custom_logistik_non_medis_master_barang')->toArray();
        $kode_item = $_GET['kode_item'] ?? '';
        return $this->draw('gudang.mutasi.html', [
            'items' => $items, 
            'selected_item' => $kode_item
        ]);
    }'''
c = re.sub(r'    public function getGudangMutasi\(\).*?\}\s+public function anyDisplayMutasi', replace_get_gudang_mutasi(None) + '\n\n    public function anyDisplayMutasi', c, flags=re.DOTALL)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
