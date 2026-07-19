<?php
$c = file_get_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/view/admin/master.barang.html');

$buttons = <<<'EOD'
                    <div class="col col-md-6">
                        <button type="button" class="btn btn-primary" id="btn-tambah-barang"><i class="fa fa-plus"></i> Tambah Barang Baru</button>
                        <a href="{?=url([ADMIN, 'logistik_non_medis', 'exportmasterbarang'])?}" target="_blank" class="btn btn-success"><i class="fa fa-download"></i> Export</a>
                        <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#modal-import-barang"><i class="fa fa-upload"></i> Import</button>
                    </div>
EOD;

$c = preg_replace('/<div class="col col-md-6">\s*<button type="button" class="btn btn-primary" id="btn-tambah-barang"><i class="fa fa-plus"><\/i> Tambah Barang Baru<\/button>\s*<\/div>/s', $buttons, $c);

// Add the import modal at the end before closing article/div
$modal = <<<'EOD'
<!-- Modal Import -->
<div class="modal fade" id="modal-import-barang" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Import Data Barang (CSV)</h4>
            </div>
            <div class="modal-body">
                <p>Silakan upload file CSV yang sesuai dengan format template.</p>
                <a href="{?=url([ADMIN, 'logistik_non_medis', 'templatemasterbarang'])?}" target="_blank" class="btn btn-info btn-sm" style="margin-bottom: 15px;"><i class="fa fa-download"></i> Download Template CSV</a>
                
                <form id="form-import-barang" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Pilih File (.csv)</label>
                        <input type="file" name="file" class="form-control" accept=".csv" required>
                    </div>
                </form>
                <div id="import-loading" style="display:none;" class="text-center text-warning">
                    <i class="fa fa-spinner fa-spin fa-2x"></i><br>
                    <p>Sedang mengimport data, harap tunggu...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btn-proses-import">Proses Import</button>
            </div>
        </div>
    </div>
</div>
EOD;

if (strpos($c, 'modal-import-barang') === false) {
    $c .= "\n" . $modal;
}

file_put_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/view/admin/master.barang.html', $c);
echo "HTML fixed!";
