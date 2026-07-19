import sys
import re

file_path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix anyExportMasterSatuan
old_export = """    public function anyExportMasterSatuan()
    {
        $satuans = $this->db('rsns_custom_logistik_non_medis_satuan')->desc('id')->toArray();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Data_Satuan_'.date('Y-m-d').'.csv');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Kode Satuan', 'Nama Satuan', 'Satuan Dasar', 'Nilai Konversi']);
        
        foreach ($satuans as $row) {
            fputcsv($output, [
                $row['kode_satuan'],
                $row['nama_satuan'],
                $row['satuan_dasar'],
                $row['nilai_konversi']
            ]);
        }
        fclose($output);
        exit();
    }"""

new_export = """    public function anyExportMasterSatuan()
    {
        $satuans = $this->db('rsns_custom_logistik_non_medis_satuan')->desc('id')->toArray();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Data_Satuan_'.date('Y-m-d').'.csv');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Kode Satuan', 'Nama Satuan', 'Satuan Dasar', 'Nilai Konversi'], ';');
        
        foreach ($satuans as $row) {
            fputcsv($output, [
                $row['kode_satuan'],
                $row['nama_satuan'],
                $row['satuan_dasar'],
                $row['nilai_konversi']
            ], ';');
        }
        fclose($output);
        exit();
    }"""

content = content.replace(old_export, new_export)

# Fix anyDownloadTemplateMasterSatuan
old_template = """    public function anyDownloadTemplateMasterSatuan()
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Template_Import_Satuan.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Kode Satuan', 'Nama Satuan', 'Satuan Dasar', 'Nilai Konversi']);
        fclose($output);
        exit();
    }"""

new_template = """    public function anyDownloadTemplateMasterSatuan()
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Template_Import_Satuan.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Kode Satuan', 'Nama Satuan', 'Satuan Dasar', 'Nilai Konversi'], ';');
        
        $examples = [
            ['KG', 'Kilogram', 'Kilogram', '1'],
            ['BOTOL', 'Botol', 'Botol', '1'],
            ['PACK', 'Pack', 'Pack', '1'],
            ['BOX', 'Box', 'Box', '1'],
            ['LBR', 'Lembar', 'Lembar', '1'],
            ['RIM', 'Rim', 'Lembar', '500'],
            ['PCS', 'Pcs', 'Pcs', '1']
        ];
        foreach ($examples as $ex) {
            fputcsv($output, $ex, ';');
        }
        
        fclose($output);
        exit();
    }"""

content = content.replace(old_template, new_template)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Replaced')
