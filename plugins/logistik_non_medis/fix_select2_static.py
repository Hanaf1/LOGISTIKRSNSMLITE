import re

# ========================================================
# Fix 1: Admin.php - add $list_barang to anyFormRencanaRutin
# ========================================================
path_admin = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\Admin.php'
with open(path_admin, 'r', encoding='utf-8') as f:
    content = f.read()

old_form_fn = """      $this->tpl->set('id', $id);
      $this->tpl->set('rencana', $rencana);
      $this->tpl->set('details', $details);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_rutin.form.html', true);
      exit();
  }

  public function postSaveRencanaRutin()"""

new_form_fn = """      $list_barang = $this->db()->pdo()->query("SELECT kode_item, nama_barang FROM rsns_custom_logistik_non_medis_master_barang WHERE status='Aktif' ORDER BY nama_barang ASC")->fetchAll(\\PDO::FETCH_ASSOC);
      
      $this->tpl->set('id', $id);
      $this->tpl->set('rencana', $rencana);
      $this->tpl->set('details', $details);
      $this->tpl->set('list_barang', $list_barang);
      echo $this->tpl->draw(MODULES.'/logistik_non_medis/view/admin/pengadaan.rencana_rutin.form.html', true);
      exit();
  }

  public function postSaveRencanaRutin()"""

if old_form_fn in content:
    content = content.replace(old_form_fn, new_form_fn)
    print("[OK] Admin.php - anyFormRencanaRutin patched")
else:
    print("[FAIL] Admin.php - anyFormRencanaRutin NOT found, check manually")

with open(path_admin, 'w', encoding='utf-8') as f:
    f.write(content)

# ========================================================
# Fix 2: Rewrite pengadaan.rencana_rutin.form.html
# Replace AJAX Select2 with static Select2 using PHP data
# ========================================================
path_form = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\pengadaan.rencana_rutin.form.html'
with open(path_form, 'r', encoding='utf-8') as f:
    form_content = f.read()

old_script = """<script>
var rowIdxRutin = {?=count($details) > 0 ? count($details) : 1?};

function initSelect2BarangRutin(selector) {
    $(selector).select2({
        ajax: {
            url: '{?=url(ADMIN."/logistik_non_medis/ajaxbarangselect2")?}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term, page: params.page || 1 };
            },
            processResults: function (data) {
                return { results: data.results, pagination: data.pagination };
            },
            cache: true
        },
        placeholder: 'Ketik nama / kode barang...',
        minimumInputLength: 1,
        dropdownParent: $('#modalFormRutin')
    });
}"""

new_script = """<script>
var rowIdxRutin = {?=count($details) > 0 ? count($details) : 1?};

// Daftar barang dari master (sudah diload dari PHP)
var listBarangRutin = {?=json_encode($list_barang)?};

function buildBarangOptions(selectedKode) {
    var opts = '<option value=""></option>';
    for (var i = 0; i < listBarangRutin.length; i++) {
        var b = listBarangRutin[i];
        var sel = (b.kode_item === selectedKode) ? ' selected' : '';
        opts += '<option value="' + b.kode_item + '"' + sel + '>' + b.kode_item + ' - ' + b.nama_barang + '</option>';
    }
    return opts;
}

function initSelect2BarangRutin(el) {
    $(el).html(buildBarangOptions($(el).val()));
    $(el).select2({
        placeholder: 'Ketik nama / kode barang...',
        dropdownParent: $('#modalFormRutin'),
        width: '100%'
    });
}"""

if old_script in form_content:
    form_content = form_content.replace(old_script, new_script)
    print("[OK] form.html - Script replaced OK")
else:
    print("[FAIL] form.html - old script NOT found, check manually")
    # Try partial match debug
    if "function initSelect2BarangRutin" in form_content:
        print("  (function exists but pattern mismatch)")

# Also fix the static initial row - no need for <option value=""></option> as we'll init via JS
old_static_select = '<select name="items[0][kode_item]" class="form-control select2-barang" required style="width:100%;"><option value=""></option></select>'
new_static_select = '<select name="items[0][kode_item]" class="form-control select2-barang" required style="width:100%;"></select>'
form_content = form_content.replace(old_static_select, new_static_select)

# Fix addItemRowRutin - remove the <option value=""> from template literal
old_add_row_select = '<select name="items[${rowIdxRutin}][kode_item]" class="form-control select2-barang" required style="width:100%;"><option value=""></option></select>'
new_add_row_select = '<select name="items[${rowIdxRutin}][kode_item]" class="form-control select2-barang" required style="width:100%;"></select>'
form_content = form_content.replace(old_add_row_select, new_add_row_select)

with open(path_form, 'w', encoding='utf-8') as f:
    f.write(form_content)

print("[OK] form.html saved")
