/* Logistik Non Medis Script */
$(document).ready(function() {
    var baseURL = mlite.url + '/' + mlite.admin;
    console.log("Logistik Non Medis module loaded.");

    // Helper function for currency formatting
    function formatCurrency(angka, prefix) {
        var number_string = angka.replace(/[^,\d]/g, '').toString(),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
    }

    $(document).on('keyup', '.currency', function() {
        $(this).val(formatCurrency($(this).val(), 'Rp. '));
    });

    $(document).on('focus', '.currency', function() {
        if($(this).val() == 'Rp. 0' || $(this).val() == '0') {
            $(this).val('');
        }
    });

    // ======== MASTER BARANG ========

    // Initialize DataTables for Master Barang


    // Refresh DataTables on save/delete instead of loadMasterBarang
    // We will hook this later or just reload
    
    // Handle Import Form Submission
    $('#form-import-barang').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        var data = new FormData(form);
        var btn = $('#btn-submit-import-barang');
        
        var fileInput = $('input[name="file"]', form).val();
        if(!fileInput) {
            bootbox.alert("Silakan pilih file CSV terlebih dahulu!");
            return;
        }

        $('#import-loading').show();
        btn.prop('disabled', true).text('Memproses...');
        
        $.ajax({
            type: "POST",
            enctype: 'multipart/form-data',
            url: baseURL + "/logistik_non_medis/importmasterbarang?t=" + mlite.token,
            data: data,
            processData: false,
            contentType: false,
            cache: false,
            timeout: 600000,
            success: function (res) {
                $('#import-loading').hide();
                btn.prop('disabled', false).text('Mulai Import');
                try {
                    var response = typeof res === 'string' ? JSON.parse(res) : res;
                    if(response.status == 'success') {
                        var detail = response.errors && response.errors.length ? '<br><br><strong>Baris dilewati:</strong><br>' + response.errors.join('<br>') : '';
                        bootbox.alert(response.pesan + detail);
                        $('#modal-import-barang').modal('hide');
                        $('#form-import-barang')[0].reset();
                        if($('#table-master-barang').length > 0) loadMasterBarang(1);
                    } else {
                        bootbox.alert((response.pesan || 'Import gagal diproses.') + (response.errors && response.errors.length ? '<br><br>' + response.errors.join('<br>') : ''));
                    }
                } catch(e) {
                    bootbox.alert("Server tidak memberikan respons import yang valid. Silakan coba ulang atau periksa log PHP.");
                }
            },
            error: function (e) {
                $('#import-loading').hide();
                $('#btn-proses-import').prop('disabled', false);
                bootbox.alert("Terjadi kesalahan jaringan atau server.");
            }
        });
    });




    function loadMasterBarang(page = 1) {
        var cari = $('#cari-barang').val() || '';
        var kategori = $('#filter-kategori-barang').val() || '';
        $('#check-all-barang').prop('checked', false);
        $('#btn-bulk-delete-barang').hide();
        $('#master-barang-list').html('<tr><td colspan="9" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></td></tr>');
        $.post(baseURL + '/logistik_non_medis/displaymasterbarang?t=' + mlite.token, {halaman: page, cari: cari, kategori: kategori}, function(data) {
            $('#master-barang-list').html(data);
        }).fail(function() {
            $('#master-barang-list').html('<tr><td colspan="9" class="text-center text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><p>Gagal memuat data dari server.</p></td></tr>');
        });
    }

    if($('#table-master-barang').length > 0) {
        loadMasterBarang();
    }


    $('#form-filter-barang').on('submit', function(e) {
        e.preventDefault();
        loadMasterBarang(1);
    });

    $('#filter-kategori-barang').on('change', function() {
        loadMasterBarang(1);
    });

    $('#btn-reset-filter-barang').on('click', function() {
        $('#cari-barang').val('');
        $('#filter-kategori-barang').val('');
        loadMasterBarang(1);
    });

    $(document).on('click', '.pagination-master-barang a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        loadMasterBarang(page);
    });

    $('#btn-tambah-barang').on('click', function() {
        $.post(baseURL + '/logistik_non_medis/formmasterbarang?t=' + mlite.token, function(data) {
            $('#form-barang-content').html(data);
            $('#modal-form-barang').modal('show');
        });
    });

    $(document).on('click', '.btn-edit-barang', function() {
        var kode_item = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterbarang?t=' + mlite.token, {kode_item: kode_item}, function(data) {
            $('#form-barang-content').html(data);
            $('#modal-form-barang').modal('show');
        });
    });

    $(document).on('click', '.btn-detail-barang', function() {
        var kode_item = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/detailmasterbarang?t=' + mlite.token, {kode_item: kode_item}, function(data) {
            $('#form-barang-content').html(data);
            $('#modal-form-barang').modal('show');
        });
    });

    $(document).on('submit', '#form-master-barang', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-barang');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterbarang?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function(response) {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    $('#modal-form-barang').modal('hide');
                    if(typeof tableMasterBarang !== "undefined") tableMasterBarang.ajax.reload(null, false);
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + res.message);
                }
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).on('shown.bs.modal', '#modal-form-barang', function () {
        $('.select2').select2({
            dropdownParent: $('#modal-form-barang')
        });
    });

    $(document).on('change', '#select-satuan-dasar', function() {
        var selected = $(this).find('option:selected');
        var nama_satuan = $(this).val();
        var satuan_dasar = selected.data('dasar');
        var nilai_konversi = selected.data('konversi');

        if(satuan_dasar && nilai_konversi && nilai_konversi > 1) {
            $('#satuan_konversi').val('1 ' + nama_satuan + ' = ' + nilai_konversi + ' ' + satuan_dasar);
        } else {
            $('#satuan_konversi').val('');
        }
    });

    $(document).on('click', '.btn-hapus-barang', function() {
        var kode_item = $(this).data('id');
        if(confirm('Yakin ingin menghapus data ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmasterbarang?t=' + mlite.token, {kode_item: kode_item}, function() {
                loadMasterBarang();
                alert('Data berhasil dihapus!');
            });
        }
    });

    // Bulk Delete Checkbox Handlers
    $(document).on('change', '#check-all-barang', function() {
        var isChecked = $(this).prop('checked');
        $('.check-barang').prop('checked', isChecked);
        updateBulkDeleteButton();
    });

    $(document).on('change', '.check-barang', function() {
        var totalCheckboxes = $('.check-barang').length;
        var checkedCheckboxes = $('.check-barang:checked').length;
        $('#check-all-barang').prop('checked', totalCheckboxes === checkedCheckboxes);
        updateBulkDeleteButton();
    });

    function updateBulkDeleteButton() {
        var checkedCount = $('.check-barang:checked').length;
        if (checkedCount > 0) {
            $('#bulk-delete-count').text(checkedCount);
            $('#btn-bulk-delete-barang').show();
        } else {
            $('#btn-bulk-delete-barang').hide();
        }
    }

    $(document).on('click', '#btn-bulk-delete-barang', function() {
        var selectedIds = [];
        $('.check-barang:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) return;

        if(confirm('Yakin ingin menghapus ' + selectedIds.length + ' data barang terpilih?')) {
            $.post(baseURL + '/logistik_non_medis/bulkdeletemasterbarang?t=' + mlite.token, {kode_items: selectedIds}, function(res) {
                loadMasterBarang();
                alert('Data berhasil dihapus!');
            });
        }
    });

    // ======== MASTER SATUAN ========

    function loadMasterSatuan(page = 1, cari = '') {
        $('#master-satuan-list').html('<tr><td colspan="5" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></td></tr>');
        $.post(baseURL + '/logistik_non_medis/displaymastersatuan?t=' + mlite.token, {halaman: page, cari: cari}, function(data) {
            $('#master-satuan-list').html(data);
        });
    }

    if($('#table-master-satuan').length > 0) {
        loadMasterSatuan();
    }

    $('.searchbox-mastersatuan').on('submit', function(e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterSatuan(1, cari);
    });

    $(document).on('click', '.pagination-master-satuan a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-mastersatuan input[name="cari"]').val();
        loadMasterSatuan(page, cari);
    });

    $('#btn-tambah-satuan').on('click', function() {
        $.post(baseURL + '/logistik_non_medis/formmastersatuan?t=' + mlite.token, function(data) {
            $('#form-satuan-content').html(data);
            $('#modal-form-satuan').modal('show');
        });
    });

    $(document).on('click', '.btn-edit-satuan', function() {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmastersatuan?t=' + mlite.token, {id: id}, function(data) {
            $('#form-satuan-content').html(data);
            $('#modal-form-satuan').modal('show');
        });
    });

    $(document).on('submit', '#form-master-satuan', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-satuan');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemastersatuan?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function(response) {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    $('#modal-form-satuan').modal('hide');
                    loadMasterSatuan();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + res.message);
                }
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).on('click', '.btn-hapus-satuan', function() {
        var id = $(this).data('id');
        if(confirm('Yakin ingin menghapus data ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmastersatuan?t=' + mlite.token, {id: id}, function() {
                loadMasterSatuan();
                alert('Data berhasil dihapus!');
            });
        }
    });

    // Import Satuan
    $(document).on('submit', '#form-import-satuan', function (e) {
        e.preventDefault();
        var data = new FormData(this);
        $('#btn-proses-import-satuan').prop('disabled', true).text('Memproses...');
        
        $.ajax({
            type: "POST",
            enctype: 'multipart/form-data',
            url: baseURL + "/logistik_non_medis/importmastersatuan?t=" + mlite.token,
            data: data,
            processData: false,
            contentType: false,
            cache: false,
            timeout: 600000,
            success: function (res) {
                $('#btn-proses-import-satuan').prop('disabled', false).text('Proses Import');
                try {
                    var response = JSON.parse(res);
                    if(response.status == 'success') {
                        bootbox.alert(response.pesan);
                        $('#modal-import-satuan').modal('hide');
                        $('#form-import-satuan')[0].reset();
                        loadMasterSatuan();
                    } else {
                        bootbox.alert(response.pesan);
                    }
                } catch(e) {
                    bootbox.alert("Terjadi kesalahan sistem saat parsing respon import.");
                }
            },
            error: function (e) {
                $('#btn-proses-import-satuan').prop('disabled', false).text('Proses Import');
                bootbox.alert("Terjadi kesalahan saat memproses data.");
            }
        });
    });

    // ======== MASTER KATEGORI ========

    function loadMasterKategori(page = 1, cari = '') {
        $('#master-kategori-list').html('<tr><td colspan="4" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></td></tr>');
        $.post(baseURL + '/logistik_non_medis/displaymasterkategori?t=' + mlite.token, {halaman: page, cari: cari}, function(data) {
            $('#master-kategori-list').html(data);
        });
    }

    if($('#table-master-kategori').length > 0) {
        loadMasterKategori();
    }

    $('.searchbox-masterkategori').on('submit', function(e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterKategori(1, cari);
    });

    $(document).on('click', '.pagination-master-kategori a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-masterkategori input[name="cari"]').val();
        loadMasterKategori(page, cari);
    });

    $('#btn-tambah-kategori').on('click', function() {
        $.post(baseURL + '/logistik_non_medis/formmasterkategori?t=' + mlite.token, function(data) {
            $('#form-kategori-content').html(data);
            $('#modal-form-kategori').modal('show');
        });
    });

    $(document).on('click', '.edit-kategori', function() {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterkategori?t=' + mlite.token, {id: id}, function(data) {
            $('#form-kategori-content').html(data);
            $('#modal-form-kategori').modal('show');
        });
    });

    $(document).on('submit', '#form-kategori', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-simpan-kategori');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterkategori?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function(response) {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    $('#modal-form-kategori').modal('hide');
                    loadMasterKategori();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + res.message);
                }
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).on('click', '.hapus-kategori', function() {
        var id = $(this).data('id');
        if(confirm('Yakin ingin menghapus data ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmasterkategori?t=' + mlite.token, {id: id}, function() {
                loadMasterKategori();
                alert('Data berhasil dihapus!');
            });
        }
    });

    // ======== MASTER VENDOR ========

    function loadMasterVendor(page = 1, cari = '') {
        $('#display-vendor').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Memuat data...</p></div>');
        $.post(baseURL + '/logistik_non_medis/displaymastervendor?t=' + mlite.token, {halaman: page, cari: cari}, function(data) {
            $('#display-vendor').html(data);
        });
    }

    if($('#display-vendor').length > 0) {
        loadMasterVendor();
    }

    $(document).on('click', '#btn-cari-vendor', function() {
        var cari = $('#cari-vendor').val();
        loadMasterVendor(1, cari);
    });

    $(document).on('keypress', '#cari-vendor', function(e) {
        if(e.which == 13) {
            var cari = $(this).val();
            loadMasterVendor(1, cari);
        }
    });

    $(document).on('click', '#btn-tambah-vendor', function() {
        $.post(baseURL + '/logistik_non_medis/formmastervendor?t=' + mlite.token, function(data) {
            $('#modal-vendor-title').text('Tambah Vendor Baru');
            $('#form-content-vendor').html(data);
            $('#modal-vendor').modal('show');
        });
    });

    window.editVendor = function(kode) {
        $.post(baseURL + '/logistik_non_medis/formmastervendor?t=' + mlite.token, {kode_vendor: kode}, function(data) {
            $('#modal-vendor-title').text('Edit Data Vendor');
            $('#form-content-vendor').html(data);
            $('#modal-vendor').modal('show');
        });
    };

    window.detailVendor = function(kode) {
        $.post(baseURL + '/logistik_non_medis/detailmastervendor?t=' + mlite.token, {kode_vendor: kode}, function(data) {
            $('#detail-content-vendor').html(data);
            $('#modal-detail-vendor').modal('show');
        });
    };

    window.hapusVendor = function(kode) {
        if(confirm('Apakah Anda yakin ingin menghapus vendor ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmastervendor?t=' + mlite.token, {kode_vendor: kode}, function() {
                loadMasterVendor();
                alert('Data berhasil dihapus!');
            });
        }
    };

    window.loadVendor = function(halaman) {
        var cari = $('#cari-vendor').val();
        loadMasterVendor(halaman, cari);
    };

    $(document).on('submit', '#form-vendor', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-simpan-vendor');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemastervendor?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function(response) {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    $('#modal-vendor').modal('hide');
                    loadMasterVendor();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + res.message);
                }
                btn.prop('disabled', false).text('Simpan Data');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).on('shown.bs.modal', '#modal-vendor', function () {
        $('.select2').select2({
            dropdownParent: $('#modal-vendor'),
            placeholder: 'Pilih Kategori',
            allowClear: true
        });
    });

    window.previewVendorFile = function(filename) {
        var url = mlite.url + '/uploads/logistik_non_medis/vendor/' + filename + '?t=' + new Date().getTime();
        var ext = filename.split('.').pop().toLowerCase();
        var html = '';
        
        if (ext == 'pdf') {
            html = '<iframe src="' + url + '" width="100%" height="600px" style="border:none;"></iframe>';
        } else {
            html = '<img src="' + url + '" class="img-responsive" style="margin: 0 auto; max-height: 80vh;">';
        }
        
        $('#preview-content-vendor').html(html);
        $('#modal-preview-vendor').modal('show');
    };

    // Fix scroll issue for multiple modals
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal:visible').length > 0) {
            $('body').addClass('modal-open');
        }
    });
    // ======== MASTER UNIT ========

    function loadMasterUnit(page = 1, cari = '') {
        console.log("Loading Master Unit... Page:", page, "Cari:", cari);
        $('#master-unit-list').html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></td></tr>');
        $.post(baseURL + '/logistik_non_medis/displaymasterunit?t=' + mlite.token, {halaman: page, cari: cari}, function(data) {
            $('#master-unit-list').html(data);
        }).fail(function() {
            $('#master-unit-list').html('<tr><td colspan="8" class="text-center text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><p>Gagal memuat data dari server.</p></td></tr>');
        });
    }

    if($('#table-master-unit').length > 0) {
        loadMasterUnit();
    }

    $('.searchbox-masterunit').on('submit', function(e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterUnit(1, cari);
    });

    $(document).on('click', '.pagination-master-unit a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-masterunit input[name="cari"]').val();
        loadMasterUnit(page, cari);
    });

    $('#btn-tambah-unit').on('click', function() {
        $.post(baseURL + '/logistik_non_medis/formmasterunit?t=' + mlite.token, function(data) {
            $('#form-unit-content').html(data);
            $('#modal-form-unit').modal('show');
        });
    });

    $(document).on('click', '.btn-edit-unit', function() {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterunit?t=' + mlite.token, {id: id}, function(data) {
            $('#form-unit-content').html(data);
            $('#modal-form-unit').modal('show');
        });
    });

    $(document).on('click', '.btn-detail-unit', function() {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/detailmasterunit?t=' + mlite.token, {id: id}, function(data) {
            $('#form-unit-content').html(data);
            $('#modal-form-unit').modal('show');
        });
    });

    $(document).on('submit', '#form-master-unit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-unit');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterunit?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function(response) {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    $('#modal-form-unit').modal('hide');
                    loadMasterUnit();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + res.message);
                }
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).on('shown.bs.modal', '#modal-form-unit', function () {
        $('.select2').select2({
            dropdownParent: $('#modal-form-unit')
        });
    });

    $(document).on('click', '.btn-hapus-unit', function() {
        var id = $(this).data('id');
        if(confirm('Yakin ingin menghapus data ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmasterunit?t=' + mlite.token, {id: id}, function() {
                loadMasterUnit();
                alert('Data berhasil dihapus!');
            });
        }
    });

    // Import Unit
    $(document).on('submit', '#form-import-unit', function (e) {
        e.preventDefault();
        var data = new FormData(this);
        $('#btn-proses-import-unit').prop('disabled', true).text('Memproses...');
        
        $.ajax({
            type: "POST",
            enctype: 'multipart/form-data',
            url: baseURL + "/logistik_non_medis/importmasterunit?t=" + mlite.token,
            data: data,
            processData: false,
            contentType: false,
            cache: false,
            timeout: 600000,
            success: function (res) {
                $('#btn-proses-import-unit').prop('disabled', false).text('Proses Import');
                try {
                    var response = JSON.parse(res);
                    if(response.status == 'success') {
                        bootbox.alert(response.pesan);
                        $('#modal-import-unit').modal('hide');
                        $('#form-import-unit')[0].reset();
                        loadMasterUnit();
                    } else {
                        bootbox.alert(response.pesan);
                    }
                } catch(e) {
                    bootbox.alert("Terjadi kesalahan sistem saat parsing respon import.");
                }
            },
            error: function (e) {
                $('#btn-proses-import-unit').prop('disabled', false).text('Proses Import');
                bootbox.alert("Terjadi kesalahan saat memproses data.");
            }
        });
    });

    // ======== MASTER LOKASI ========

    function loadMasterLokasi(page = 1, cari = '') {
        $('#master-lokasi-list').html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></td></tr>');
        $.post(baseURL + '/logistik_non_medis/displaymasterlokasi?t=' + mlite.token, {halaman: page, cari: cari}, function(data) {
            $('#master-lokasi-list').html(data);
        }).fail(function() {
            $('#master-lokasi-list').html('<tr><td colspan="8" class="text-center text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><p>Gagal memuat data dari server.</p></td></tr>');
        });
    }

    if($('#table-master-lokasi').length > 0) {
        loadMasterLokasi();
    }

    $('.searchbox-masterlokasi').on('submit', function(e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterLokasi(1, cari);
    });

    $(document).on('click', '.pagination-master-lokasi a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-masterlokasi input[name="cari"]').val();
        loadMasterLokasi(page, cari);
    });

    $('#btn-tambah-lokasi').on('click', function() {
        $.post(baseURL + '/logistik_non_medis/formmasterlokasi?t=' + mlite.token, function(data) {
            $('#form-lokasi-content').html(data);
            $('#modal-form-lokasi').modal('show');
        });
    });

    $(document).on('click', '.btn-edit-lokasi', function() {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterlokasi?t=' + mlite.token, {id: id}, function(data) {
            $('#form-lokasi-content').html(data);
            $('#modal-form-lokasi').modal('show');
        });
    });

    $(document).on('submit', '#form-master-lokasi', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-lokasi');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterlokasi?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function(response) {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    $('#modal-form-lokasi').modal('hide');
                    loadMasterLokasi();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + res.message);
                }
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).on('shown.bs.modal', '#modal-form-lokasi', function () {
        $('.select2').select2({
            dropdownParent: $('#modal-form-lokasi')
        });
    });

    $(document).on('click', '.btn-hapus-lokasi', function() {
        var id = $(this).data('id');
        if(confirm('Yakin ingin menghapus data lokasi ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmasterlokasi?t=' + mlite.token, {id: id}, function() {
                loadMasterLokasi();
                alert('Data berhasil dihapus!');
            });
        }
    });

    window.previewDenah = function(filename) {
        var url = mlite.url + '/uploads/logistik_non_medis/lokasi/' + filename + '?t=' + new Date().getTime();
        var ext = filename.split('.').pop().toLowerCase();
        var html = '';
        
        if (ext == 'pdf') {
            html = '<iframe src="' + url + '" width="100%" height="600px" style="border:none;"></iframe>';
        } else {
            html = '<img src="' + url + '" class="img-responsive" style="margin: 0 auto; max-height: 80vh;">';
        }
        
        $('#preview-content-lokasi').html(html);
        $('#modal-preview-lokasi').modal('show');
    };

    // ======== PERMINTAAN PEMBELIAN (PR) ========

    window.loadPR = function(page = 1) {
        var cari = $('#cariPR').val();
        $('#displayPR').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Memuat data...</p></div>');
        $.post(baseURL + '/logistik_non_medis/displaypr?t=' + mlite.token, {halaman: page, cari: cari}, function(data) {
            $('#displayPR').html(data);
        }).fail(function() {
            $('#displayPR').html('<div class="alert alert-danger text-center"><i class="fa fa-exclamation-triangle"></i> Gagal memuat data. Periksa koneksi atau database.</div>');
        });
    };

    window.tambahPR = function() {
        $.post(baseURL + '/logistik_non_medis/formpr?t=' + mlite.token, function(data) {
            $('#pr_modal_content').html(data);
            $('#modalPR').modal('show');
        });
    };

    window.editPR = function(no_pr) {
        $.post(baseURL + '/logistik_non_medis/formpr?t=' + mlite.token, {no_pr: no_pr}, function(data) {
            $('#pr_modal_content').html(data);
            $('#modalPR').modal('show');
        });
    };

    window.viewPR = function(no_pr) {
        $.post(baseURL + '/logistik_non_medis/detailpr?t=' + mlite.token, {no_pr: no_pr}, function(data) {
            $('#pr_modal_content').html(data);
            $('#modalPR').modal('show');
        });
    };

    window.hapusPR = function(no_pr) {
        if(confirm('Apakah Anda yakin ingin menghapus pengajuan PR ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapuspr?t=' + mlite.token, {no_pr: no_pr}, function(res) {
                loadPR();
            });
        }
    };

    window.accPR = function(no_pr) {
        if(confirm('Apakah Anda yakin ingin menyetujui (ACC) dan memberikan barang untuk PR ini?')) {
            $.post(baseURL + '/logistik_non_medis/accpr?t=' + mlite.token, {no_pr: no_pr}, function(response) {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    loadPR();
                    alert(res.message);
                } else {
                    alert(res.message);
                }
            });
        }
    };

    $(document).on('submit', '#formPR', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btnSimpanPR');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/simpanpr?t=' + mlite.token,
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function(response) {
                var res;
                try {
                    res = (typeof response === 'object') ? response : JSON.parse(response);
                    if(res.status == 'success') {
                        $('#modalPR').modal('hide');
                        loadPR();
                        alert(res.message);
                    } else {
                        alert(res.message);
                    }
                } catch(e) {
                    console.error("Save PR Error:", e, response);
                    var snippet = (typeof response === 'string') ? response.substring(0, 100) : '';
                    alert('Terjadi kesalahan format data dari server. Potongan pesan: ' + snippet);
                }
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Simpan Pengajuan');
            }
        });
    });

    if($('#displayPR').length > 0) {
        loadPR();
    }

});

// --- MANAJEMEN VENDOR ---
window.mlite = window.mlite || {};
mlite.logistik_non_medis = {
    loadManajemen: function(kode_vendor = '') {
        var baseURL = mlite.url + '/' + mlite.admin;
        $('#display-vendor-manajemen').html('<div class="text-center p-20"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Memuat data...</p></div>');
        $.post(baseURL + '/logistik_non_medis/displayvendormanajemen?t=' + mlite.token, {kode_vendor: kode_vendor}, function(data) {
            $('#display-vendor-manajemen').html(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
            $('#display-vendor-manajemen').html('<div class="alert alert-danger">Gagal memuat data: ' + textStatus + ' - ' + errorThrown + '</div>');
        });
    },
    formManajemen: function(id = '', kode_vendor = '') {
        var baseURL = mlite.url + '/' + mlite.admin;
        $.post(baseURL + '/logistik_non_medis/formvendormanajemen?t=' + mlite.token, {id: id, kode_vendor: kode_vendor}, function(data) {
            $('#form-vendor-manajemen').html(data);
            $('#modal-manajemen').modal('show');
        }).fail(function() {
            alert('Gagal memuat form.');
        });
    },
    saveManajemen: function(formData) {
        var baseURL = mlite.url + '/' + mlite.admin;
        $.ajax({
            url: baseURL + '/logistik_non_medis/savevendormanajemen?t=' + mlite.token,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                try {
                    var res = JSON.parse(response);
                    if(res.status == 'success') {
                        $('#modal-manajemen').modal('hide');
                        mlite.logistik_non_medis.loadManajemen($('#filter-vendor').val());
                    } else {
                        alert(res.message);
                    }
                } catch(e) {
                    alert('Error saving data: ' + response);
                }
            },
            error: function() {
                alert('Gagal mengirim data ke server.');
            }
        });
    },
    hapusManajemen: function(id) {
        var baseURL = mlite.url + '/' + mlite.admin;
        $.post(baseURL + '/logistik_non_medis/hapusvendormanajemen?t=' + mlite.token, {id: id}, function(response) {
            try {
                var res = JSON.parse(response);
                if(res.status == 'success') {
                    mlite.logistik_non_medis.loadManajemen($('#filter-vendor').val());
                } else {
                    alert(res.message);
                }
            } catch(e) {
                alert('Error deleting data.');
            }
        });
    }
};


