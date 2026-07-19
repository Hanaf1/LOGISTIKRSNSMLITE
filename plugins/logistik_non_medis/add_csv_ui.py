import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\gudang.mutasi.html'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

modals = '''
<!-- MODAL IMPORT CSV -->
<div class="modal fade" id="modal-import-csv" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <form id="form-import-csv">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Import Data Mutasi (CSV)</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih File CSV (.csv)</label>
                        <input type="file" name="file_csv" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-upload"></i> Proses Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXPORT CSV -->
<div class="modal fade" id="modal-export-csv" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <form id="form-export-csv" method="GET" action="{?=url(ADMIN."/logistik_non_medis/exportmutasicsv")?}">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Filter Export CSV</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Kategori Barang</label>
                        <select name="kategori" class="form-control">
                            <option value="">-- Semua Kategori --</option>
                            {loop: $kategori}
                            <option value="{$value.kode_kategori}">{$value.nama_kategori}</option>
                            {/loop}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bulan</label>
                        <select name="bulan" class="form-control">
                            <option value="01" {if: date('m') == '01'}selected{/if}>Januari</option>
                            <option value="02" {if: date('m') == '02'}selected{/if}>Februari</option>
                            <option value="03" {if: date('m') == '03'}selected{/if}>Maret</option>
                            <option value="04" {if: date('m') == '04'}selected{/if}>April</option>
                            <option value="05" {if: date('m') == '05'}selected{/if}>Mei</option>
                            <option value="06" {if: date('m') == '06'}selected{/if}>Juni</option>
                            <option value="07" {if: date('m') == '07'}selected{/if}>Juli</option>
                            <option value="08" {if: date('m') == '08'}selected{/if}>Agustus</option>
                            <option value="09" {if: date('m') == '09'}selected{/if}>September</option>
                            <option value="10" {if: date('m') == '10'}selected{/if}>Oktober</option>
                            <option value="11" {if: date('m') == '11'}selected{/if}>November</option>
                            <option value="12" {if: date('m') == '12'}selected{/if}>Desember</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tahun</label>
                        <input type="number" name="tahun" class="form-control" value="{?=date('Y')?}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" onclick="$('#modal-export-csv').modal('hide')"><i class="fa fa-download"></i> Download CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>
'''

js_handler = '''
<script>
$('#form-import-csv').submit(function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
        url: '{?=url(ADMIN."/logistik_non_medis/importmutasicsv")?}',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(res) {
            var r = JSON.parse(res);
            if (r.status == 'success') {
                $('#modal-import-csv').modal('hide');
                loadDaftarMutasi();
                alert(r.message);
            } else {
                alert(r.message);
            }
        }
    });
});
</script>
'''

if 'id="modal-import-csv"' not in c:
    c = c + '\n' + modals + '\n' + js_handler
    with open(path, 'w', encoding='utf-8') as f: f.write(c)
