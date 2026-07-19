import sys, re
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\gudang.mutasi.html'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# Replace the top button row
old_buttons = '''                        <div class="row">
                            <div class="col-md-8">
                                <button class="btn btn-primary" onclick="tambahMutasi()"><i class="fa fa-plus"></i> Tambah Mutasi</button>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" id="cari-mutasi" class="form-control" placeholder="Cari no mutasi / lokasi...">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" id="btn-cari-mutasi"><i class="fa fa-search"></i></button>
                                    </span>
                                </div>
                            </div>
                        </div>'''

new_buttons = '''                        <div class="row">
                            <div class="col-md-6">
                                <button class="btn btn-primary" onclick="tambahMutasi()"><i class="fa fa-plus"></i> Tambah Mutasi</button>
                                <button class="btn btn-info" onclick="$('#modalImport').modal('show')"><i class="fa fa-upload"></i> Import</button>
                                <button class="btn btn-success" onclick="$('#modalExport').modal('show')"><i class="fa fa-download"></i> Export</button>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group pull-right" style="width: 300px;">
                                    <input type="text" id="cari-mutasi" class="form-control" placeholder="Cari mutasi / barang...">
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" id="btn-cari-mutasi"><i class="fa fa-search"></i></button>
                                    </span>
                                </div>
                            </div>
                        </div>'''

c = c.replace(old_buttons, new_buttons)

# Add Export/Import modals before <script>
modals_html = '''
<!-- Modal Import -->
<div class="modal fade" id="modalImport" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form id="form-import-mutasi" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Import Data Mutasi / Penyesuaian Stok</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-warning"></i> Pastikan format file sesuai dengan template!
                    </div>
                    <div class="form-group">
                        <label>File Excel (.xls / .xlsx)</label>
                        <input type="file" name="file" class="form-control" accept=".xls,.xlsx" required>
                    </div>
                    <a href="{?=url(ADMIN.'/logistik_non_medis/downloadtemplatemutasi')?}" class="btn btn-default btn-sm"><i class="fa fa-download"></i> Download Template</a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info"><i class="fa fa-upload"></i> Import Data</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Export -->
<div class="modal fade" id="modalExport" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form action="{?=url(ADMIN.'/logistik_non_medis/exportmutasi')?}" method="GET" target="_blank">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Export Data Mutasi</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Bulan</label>
                        <select name="bulan" class="form-control">
                            <option value="">-- Semua Bulan --</option>
                            {for: $i=1; $i<=12; $i++}
                            <option value="{?=str_pad($i, 2, '0', STR_PAD_LEFT)?}">{?=date('F', mktime(0, 0, 0, $i, 10))?}</option>
                            {/for}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tahun</label>
                        <select name="tahun" class="form-control">
                            <option value="">-- Semua Tahun --</option>
                            {for: $i=date('Y'); $i>=date('Y')-5; $i--}
                            <option value="{$i}">{$i}</option>
                            {/for}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategori Barang</label>
                        <select name="kategori" class="form-control">
                            <option value="">-- Semua Kategori --</option>
                            {loop: $kategori}
                            <option value="{$value.kode_kategori}">{$value.nama_kategori}</option>
                            {/loop}
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" onclick="$('#modalExport').modal('hide')"><i class="fa fa-download"></i> Download Excel</button>
                </div>
            </div>
        </form>
    </div>
</div>

'''

c = c.replace('<script>', modals_html + '\n<script>')

# Add JS handler for form-import-mutasi
import_js = '''
    $('#form-import-mutasi').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Mengimport...');
        
        $.ajax({
            url: '{?=url(ADMIN."/logistik_non_medis/importmutasi")?}',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                var res = JSON.parse(response);
                alert(res.message);
                if(res.status == 'success') {
                    $('#modalImport').modal('hide');
                    loadMutasi();
                }
                btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import Data');
            },
            error: function() {
                alert('Terjadi kesalahan sistem.');
                btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import Data');
            }
        });
    });

    // Form Mutasi AJAX handler inside modal
    $(document).on('submit', '#form-mutasi', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
        
        $.ajax({
            url: '{?=url(ADMIN."/logistik_non_medis/savemutasi")?}',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                var res = JSON.parse(response);
                alert(res.message);
                if(res.status == 'success') {
                    $('#modalMutasi').modal('hide');
                    loadMutasi();
                }
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Simpan Mutasi');
            },
            error: function() {
                alert('Terjadi kesalahan sistem.');
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Simpan Mutasi');
            }
        });
    });
'''
c = c.replace('loadMutasi();', import_js + '\n    loadMutasi();')

with open(path, 'w', encoding='utf-8') as f: f.write(c)
