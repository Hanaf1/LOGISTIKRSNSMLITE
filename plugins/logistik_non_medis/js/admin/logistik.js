/* Logistik Non Medis Script */
$(document).ready(function () {
    var baseURL = mlite.url + '/' + mlite.admin;
    console.log("Logistik Non Medis module loaded.");

    function showLogistikToast(type, title, message, duration) {
        type = type === 'success' ? 'success' : 'error';
        duration = duration || 4000;

        if (!$('#logistik-toast-stack').length) {
            $('body').append('<div id="logistik-toast-stack" aria-live="polite" aria-atomic="true"></div>');
        }
        if (!$('#logistik-toast-style').length) {
            $('head').append(
                '<style id="logistik-toast-style">' +
                '#logistik-toast-stack{position:fixed;top:70px;right:18px;z-index:2147483646;width:min(380px,calc(100vw - 30px));pointer-events:none}' +
                '.logistik-toast{display:flex;align-items:flex-start;gap:11px;margin-bottom:10px;padding:13px 14px;background:#fff;border-radius:6px;border-left:5px solid #a94442;box-shadow:0 10px 30px rgba(0,0,0,.22);color:#333;pointer-events:auto;opacity:0;transform:translateY(-10px);transition:opacity .2s ease,transform .2s ease}' +
                '.logistik-toast.is-visible{opacity:1;transform:translateY(0)}.logistik-toast-success{border-left-color:#3c763d}.logistik-toast-error{border-left-color:#a94442}' +
                '.logistik-toast-icon{font-size:22px;line-height:1;margin-top:1px}.logistik-toast-success .logistik-toast-icon{color:#3c763d}.logistik-toast-error .logistik-toast-icon{color:#a94442}' +
                '.logistik-toast-body{min-width:0;flex:1}.logistik-toast-title{font-weight:700;margin-bottom:2px}.logistik-toast-message{color:#555;line-height:1.4;overflow-wrap:anywhere}.logistik-toast-close{border:0;background:transparent;color:#777;padding:0;font-size:18px;line-height:1}' +
                '</style>'
            );
        }

        var escapeHtml = function(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        };
        var icon = type === 'success' ? 'fa-check-circle' : 'fa-times-circle';
        var $toast = $(
            '<div class="logistik-toast logistik-toast-' + type + '" role="status">' +
                '<i class="fa ' + icon + ' logistik-toast-icon"></i>' +
                '<div class="logistik-toast-body"><div class="logistik-toast-title">' + escapeHtml(title) + '</div><div class="logistik-toast-message">' + escapeHtml(message) + '</div></div>' +
                '<button type="button" class="logistik-toast-close" aria-label="Tutup">&times;</button>' +
            '</div>'
        );
        $('#logistik-toast-stack').append($toast);
        setTimeout(function() { $toast.addClass('is-visible'); }, 20);

        var closeToast = function() {
            $toast.removeClass('is-visible');
            setTimeout(function() { $toast.remove(); }, 220);
        };
        $toast.find('.logistik-toast-close').on('click', closeToast);
        setTimeout(closeToast, duration);
    }

    // Arahkan breadcrumb kategori kembali ke tab kategori yang sesuai pada
    // dashboard Logistik Non Medis, bukan selalu ke tab pertama.
    var logistikBreadcrumbTabs = {
        'master': 'master-data',
        'master data': 'master-data',
        'pengadaan': 'pengadaan',
        'gudang': 'manajemen-gudang',
        'manajemen gudang': 'manajemen-gudang',
        'distribusi': 'distribusi',
        'aset': 'aset',
        'laporan': 'laporan-audit',
        'laporan & audit': 'laporan-audit'
    };
    var logistikManageUrl = baseURL + '/logistik_non_medis/manage?t=' + encodeURIComponent(mlite.token || '');

    $('.custom-breadcrumb a, .breadcrumb a, .bhp-breadcrumb a').each(function () {
        var label = $.trim($(this).text()).replace(/\s+/g, ' ').toLowerCase();
        var targetTab = logistikBreadcrumbTabs[label];
        if (targetTab) {
            $(this).attr('href', logistikManageUrl + '#' + targetTab);
        }
    });

    $(document).on('click.logistikBreadcrumb', '.custom-breadcrumb a, .breadcrumb a, .bhp-breadcrumb a', function (e) {
        var label = $.trim($(this).text()).replace(/\s+/g, ' ').toLowerCase();
        var targetTab = logistikBreadcrumbTabs[label];
        if (!targetTab) return;
        e.preventDefault();
        window.location.href = logistikManageUrl + '#' + targetTab;
    });

    var activeDashboardTab = String(window.location.hash || '').replace('#', '');
    if (activeDashboardTab && $('#' + activeDashboardTab).length) {
        var $dashboardTab = $('.nav-tabs a[href="#' + activeDashboardTab + '"]');
        if ($dashboardTab.length && $.fn.tab) {
            $dashboardTab.tab('show');
        }
    }

    $('.nav-tabs a[data-toggle="tab"]').on('shown.bs.tab.logistikBreadcrumb', function (e) {
        var target = $(e.target).attr('href');
        if (target && /^#[a-z0-9-]+$/i.test(target) && window.history && window.history.replaceState) {
            window.history.replaceState(null, document.title, window.location.pathname + window.location.search + target);
        }
    });

    // Penanda global khusus aksi yang mengubah data. Endpoint baca (form/list/detail)
    // tetap ringan agar indikator tidak berkedip ketika pengguna mencari data.
    var logistikBusyCount = 0;
    var logistikReadEndpoint = /(display|form|detail|ajax|search|lookup|preview|filter|load|generate)/i;

    function isLogistikMutationRequest(settings) {
        var method = String(settings.type || settings.method || 'GET').toUpperCase();
        var requestUrl = String(settings.url || '');
        if (method !== 'POST' || requestUrl.indexOf('/logistik_non_medis/') === -1) {
            return false;
        }
        return !logistikReadEndpoint.test(requestUrl);
    }

    function isLogistikRequest(settings) {
        return String(settings.url || '').indexOf('/logistik_non_medis/') !== -1;
    }

    function setLogistikBusy(show) {
        var $overlay = $('#logistik-busy-overlay');
        if (!$overlay.length) return;
        // Beberapa aksi dilanjutkan dengan reload halaman. Jangan hilangkan
        // indikator pada jeda setelah respons sukses sampai navigasi selesai.
        if (!show && $overlay.hasClass('keep-visible-until-navigation')) return;
        $overlay.toggleClass('is-visible', show).attr('aria-hidden', show ? 'false' : 'true');
        $('body').toggleClass('logistik-is-busy', show);
    }

    $('#logistik-busy-overlay').remove();
    $('body').append(
        '<div id="logistik-busy-overlay" class="logistik-busy-overlay" aria-hidden="true">' +
        '<div class="logistik-busy-dialog" role="status" aria-live="polite">' +
        '<i class="fa fa-spinner fa-spin fa-2x"></i>' +
        '<strong>Memproses data...</strong>' +
        '<span>Mohon tunggu, jangan klik tombol lagi.</span>' +
        '</div>' +
        '</div>'
    );

    $(document).off('ajaxSend.logistikBusy ajaxComplete.logistikBusy');
    $(document).on('ajaxSend.logistikBusy', function (event, xhr, settings) {
        // Bila aksi simpan/hapus memicu reload tabel, indikator baru ditutup
        // setelah reload tersebut selesai agar hasil perubahan langsung terlihat.
        var isMutation = isLogistikMutationRequest(settings);
        var isFollowUpLoad = logistikBusyCount > 0 && isLogistikRequest(settings);
        if (!isMutation && !isFollowUpLoad) return;
        xhr.logistikBusyRequest = true;
        logistikBusyCount++;
        setLogistikBusy(true);
    });

    $(document).on('ajaxComplete.logistikBusy', function (event, xhr) {
        if (!xhr.logistikBusyRequest) return;
        xhr.logistikBusyRequest = false;
        logistikBusyCount = Math.max(0, logistikBusyCount - 1);
        if (logistikBusyCount === 0) setLogistikBusy(false);
    });

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

    $(document).on('keyup', '.currency', function () {
        $(this).val(formatCurrency($(this).val(), 'Rp. '));
    });

    $(document).on('focus', '.currency', function () {
        if ($(this).val() == 'Rp. 0' || $(this).val() == '0') {
            $(this).val('');
        }
    });

    // ======== MASTER BARANG ========

    // Initialize DataTables for Master Barang


    // Refresh DataTables on save/delete instead of loadMasterBarang
    // We will hook this later or just reload

    // Handle Import Form Submission
    $('#form-import-barang').on('submit', function (e) {
        e.preventDefault();
        var form = this;
        var data = new FormData(form);
        var btn = $('#btn-submit-import-barang');

        var fileInput = $('input[name="file"]', form).val();
        if (!fileInput) {
            showLogistikToast('error', 'Gagal', 'Silakan pilih file CSV terlebih dahulu.');
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
                    if (response.status == 'success') {
                        var detail = response.errors && response.errors.length ? ' Baris dilewati: ' + response.errors.join('; ') : '';
                        showLogistikToast('success', 'Import berhasil', (response.pesan || 'Data berhasil diimpor.') + detail, 7000);
                        $('#modal-import-barang').modal('hide');
                        $('#form-import-barang')[0].reset();
                        if ($('#table-master-barang').length > 0) loadMasterBarang(1);
                    } else {
                        showLogistikToast('error', 'Import gagal', (response.pesan || 'Import gagal diproses.') + (response.errors && response.errors.length ? ' ' + response.errors.join('; ') : ''), 7000);
                    }
                } catch (e) {
                    showLogistikToast('error', 'Import gagal', 'Server tidak memberikan respons import yang valid. Silakan coba ulang atau periksa log PHP.');
                }
            },
            error: function (e) {
                $('#import-loading').hide();
                $('#btn-proses-import').prop('disabled', false);
                showLogistikToast('error', 'Import gagal', 'Terjadi kesalahan jaringan atau server.');
            }
        });
    });




    var masterBarangCurrentPage = 1;

    function loadMasterBarang(page = 1) {
        page = parseInt(page, 10) || 1;
        masterBarangCurrentPage = page;
        var cari = $('#cari-barang').val() || '';
        var kategori = $('#filter-kategori-barang').val() || '';
        $('#check-all-barang').prop('checked', false);
        $('#btn-bulk-category-barang').hide();
        $('#btn-bulk-delete-barang').hide();
        $('#master-barang-list').html('<tr><td colspan="9" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></td></tr>');
        return $.post(baseURL + '/logistik_non_medis/displaymasterbarang?t=' + mlite.token, { halaman: page, cari: cari, kategori: kategori }, function (data) {
            $('#master-barang-list').html(data);
        }).fail(function () {
            $('#master-barang-list').html('<tr><td colspan="9" class="text-center text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><p>Gagal memuat data dari server.</p></td></tr>');
        });
    }

    if ($('#table-master-barang').length > 0) {
        loadMasterBarang();
    }


    $('#form-filter-barang').on('submit', function (e) {
        e.preventDefault();
        loadMasterBarang(1);
    });

    $('#filter-kategori-barang').on('change', function () {
        loadMasterBarang(1);
    });

    $('#btn-reset-filter-barang').on('click', function () {
        $('#cari-barang').val('');
        $('#filter-kategori-barang').val('');
        loadMasterBarang(1);
    });

    $(document).on('click', '.pagination-master-barang a', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        loadMasterBarang(page);
    });

    $('#btn-tambah-barang').on('click', function () {
        $.post(baseURL + '/logistik_non_medis/formmasterbarang?t=' + mlite.token, function (data) {
            $('#form-barang-content').html(data);
            $('#modal-form-barang').modal('show');
        });
    });

    $(document).on('click', '.btn-edit-barang', function () {
        var kode_item = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterbarang?t=' + mlite.token, { kode_item: kode_item }, function (data) {
            $('#form-barang-content').html(data);
            $('#modal-form-barang').modal('show');
        });
    });

    $(document).on('click', '.btn-detail-barang', function () {
        var kode_item = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/detailmasterbarang?t=' + mlite.token, { kode_item: kode_item }, function (data) {
            $('#form-barang-content').html(data);
            $('#modal-form-barang').modal('show');
        });
    });

    $(document).on('submit', '#form-master-barang', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-barang');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterbarang?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function (response) {
                var res;
                try {
                    res = (typeof response === 'object') ? response : JSON.parse(response);
                } catch (e) {
                    console.error('Save master barang response:', response);
                    showLogistikToast('error', 'Gagal', 'Server mengembalikan respons yang tidak valid. Periksa log server.');
                    btn.prop('disabled', false).text('Simpan');
                    return;
                }
                if (res.status == 'success') {
                    $('#modal-form-barang').modal('hide');
                    loadMasterBarang(masterBarangCurrentPage)
                        .done(function () {
                            showLogistikToast('success', 'Berhasil', 'Data barang berhasil disimpan.');
                        });
                } else {
                    showLogistikToast('error', 'Gagal', res.message || 'Data barang gagal disimpan.');
                }
                btn.prop('disabled', false).text('Simpan');
            },
            error: function (xhr) {
                console.error('Save master barang error:', xhr.responseText);
                showLogistikToast('error', 'Gagal', 'Gagal menyimpan data barang. Periksa koneksi atau log server.');
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

    $(document).on('change', '#select-satuan-dasar', function () {
        var selected = $(this).find('option:selected');
        var nama_satuan = $(this).val();
        var satuan_dasar = selected.data('dasar');
        var nilai_konversi = selected.data('konversi');

        if (satuan_dasar && nilai_konversi && nilai_konversi > 1) {
            $('#satuan_konversi').val('1 ' + nama_satuan + ' = ' + nilai_konversi + ' ' + satuan_dasar);
        } else {
            $('#satuan_konversi').val('');
        }
    });

    $(document).off('click.masterBarangDelete', '.btn-hapus-barang')
        .on('click.masterBarangDelete', '.btn-hapus-barang', function (e) {
            e.preventDefault();
            var kodeItem = String($(this).data('id') || '');
            var namaBarang = $.trim($(this).closest('tr').find('td').eq(2).text());

            if (!kodeItem) {
                showLogistikToast('error', 'Gagal', 'Kode barang tidak ditemukan. Muat ulang halaman lalu coba kembali.');
                return;
            }

            var labelBarang = namaBarang ? namaBarang + ' (' + kodeItem + ')' : kodeItem;
            if (!confirm('Hapus barang ' + labelBarang + '?\n\nData yang sudah dihapus tidak dapat dikembalikan.')) {
                return;
            }

            $.ajax({
                url: baseURL + '/logistik_non_medis/hapusmasterbarang?t=' + mlite.token,
                type: 'POST',
                dataType: 'json',
                data: { kode_item: kodeItem },
                success: function (response) {
                    if (response && response.status === 'success') {
                        loadMasterBarang(1);
                        showLogistikToast('success', 'Berhasil', 'Barang berhasil dihapus.');
                        return;
                    }
                    showLogistikToast('error', 'Gagal', (response && response.message) || 'Barang gagal dihapus.');
                },
                error: function (xhr) {
                    var message = 'Server gagal memproses penghapusan barang.';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.message) message = response.message;
                    } catch (ignore) { }
                    showLogistikToast('error', 'Gagal', message);
                }
            });
        });


    // Bulk Delete Checkbox Handlers
    $(document).on('change', '#check-all-barang', function () {
        var isChecked = $(this).prop('checked');
        $('.check-barang').prop('checked', isChecked);
        updateBulkDeleteButton();
    });

    $(document).on('change', '.check-barang', function () {
        var totalCheckboxes = $('.check-barang').length;
        var checkedCheckboxes = $('.check-barang:checked').length;
        $('#check-all-barang').prop('checked', totalCheckboxes === checkedCheckboxes);
        updateBulkDeleteButton();
    });

    function updateBulkDeleteButton() {
        var checkedCount = $('.check-barang:checked').length;
        if (checkedCount > 0) {
            $('#bulk-category-count').text(checkedCount);
            $('#bulk-delete-count').text(checkedCount);
            $('#btn-bulk-category-barang').show();
            $('#btn-bulk-delete-barang').show();
        } else {
            $('#btn-bulk-category-barang').hide();
            $('#btn-bulk-delete-barang').hide();
        }
    }

    function selectedMasterBarangIds() {
        var selectedIds = [];
        $('.check-barang:checked').each(function () {
            selectedIds.push($(this).val());
        });
        return selectedIds;
    }

    $(document).on('click', '#btn-bulk-category-barang', function () {
        var selectedIds = selectedMasterBarangIds();
        if (!selectedIds.length) {
            showLogistikToast('error', 'Gagal', 'Pilih minimal satu barang terlebih dahulu.');
            return;
        }
        $('#bulk-category-modal-count').text(selectedIds.length);
        $('#bulk-kode-kategori').val('');
        if ($('#bulk-kode-kategori').data('selectator')) {
            $('#bulk-kode-kategori').selectator('refresh');
        }
        $('#modal-bulk-category-barang').modal('show');
    });

    $(document).on('submit', '#form-bulk-category-barang', function (e) {
        e.preventDefault();
        var selectedIds = selectedMasterBarangIds();
        var kodeKategori = $('#bulk-kode-kategori').val();
        var $button = $('#btn-save-bulk-category');

        if (!selectedIds.length) {
            $('#modal-bulk-category-barang').modal('hide');
            showLogistikToast('error', 'Gagal', 'Pilihan barang sudah tidak tersedia. Silakan pilih ulang.');
            return;
        }
        if (!kodeKategori) {
            showLogistikToast('error', 'Gagal', 'Pilih kategori tujuan terlebih dahulu.');
            return;
        }

        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');
        $.ajax({
            url: baseURL + '/logistik_non_medis/bulkupdatecategorymasterbarang?t=' + mlite.token,
            type: 'POST',
            dataType: 'json',
            data: {kode_items: selectedIds, kode_kategori: kodeKategori}
        }).done(function (response) {
            if (response && response.status === 'success') {
                $('#modal-bulk-category-barang').modal('hide');
                loadMasterBarang(masterBarangCurrentPage).done(function () {
                    showLogistikToast('success', 'Berhasil', response.message || 'Kategori barang berhasil diperbarui.');
                });
            } else {
                showLogistikToast('error', 'Gagal', (response && response.message) || 'Kategori barang gagal diperbarui.');
            }
        }).fail(function (xhr) {
            var message = 'Server gagal memperbarui kategori barang.';
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.message) message = response.message;
            } catch (ignore) { }
            showLogistikToast('error', 'Gagal', message);
        }).always(function () {
            $button.prop('disabled', false).html('<i class="fa fa-save"></i> Terapkan Kategori');
        });
    });

    $(document).on('click', '#btn-bulk-delete-barang', function () {
        var selectedIds = selectedMasterBarangIds();

        if (selectedIds.length === 0) return;

        if (confirm('Yakin ingin menghapus ' + selectedIds.length + ' data barang terpilih?')) {
            $.post(baseURL + '/logistik_non_medis/bulkdeletemasterbarang?t=' + mlite.token, { kode_items: selectedIds }, function (res) {
                try {
                    var response = (typeof res === 'object') ? res : JSON.parse(res);
                    if (response.status === 'success') {
                        loadMasterBarang();
                        showLogistikToast('success', 'Berhasil', response.message || 'Data berhasil dihapus.');
                    } else {
                        showLogistikToast('error', 'Gagal', response.message || 'Gagal menghapus data.');
                    }
                } catch (e) {
                    loadMasterBarang();
                    showLogistikToast('success', 'Berhasil', 'Data berhasil dihapus.');
                }
            });
        }
    });

    // ======== MASTER SATUAN ========

    function loadMasterSatuan(page = 1, cari = '') {
        $('#master-satuan-list').html('<tr><td colspan="5" class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Memuat data...</p></td></tr>');
        $.post(baseURL + '/logistik_non_medis/displaymastersatuan?t=' + mlite.token, { halaman: page, cari: cari }, function (data) {
            $('#master-satuan-list').html(data);
        });
    }

    if ($('#table-master-satuan').length > 0) {
        loadMasterSatuan();
    }

    $('.searchbox-mastersatuan').on('submit', function (e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterSatuan(1, cari);
    });

    $(document).on('click', '.pagination-master-satuan a', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-mastersatuan input[name="cari"]').val();
        loadMasterSatuan(page, cari);
    });

    $('#btn-tambah-satuan').on('click', function () {
        $.post(baseURL + '/logistik_non_medis/formmastersatuan?t=' + mlite.token, function (data) {
            $('#form-satuan-content').html(data);
            $('#modal-form-satuan').modal('show');
        });
    });

    $(document).off('click', '.btn-edit-satuan').on('click', '.btn-edit-satuan', function () {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmastersatuan?t=' + mlite.token, { id: id }, function (data) {
            $('#form-satuan-content').html(data);
            $('#modal-form-satuan').modal('show');
        });
    });

    $(document).off('submit', '#form-master-satuan').on('submit', '#form-master-satuan', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-satuan');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemastersatuan?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.status == 'success') {
                    $('#modal-form-satuan').modal('hide');
                    loadMasterSatuan();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + (res.message || 'Gagal menyimpan data.'));
                }
                btn.prop('disabled', false).text('Simpan');
            },
            error: function () {
                alert('Gagal terhubung ke server.');
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).off('click', '.btn-hapus-satuan').on('click', '.btn-hapus-satuan', function () {
        var id = $(this).data('id');
        if (confirm('Yakin ingin menghapus data ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmastersatuan?t=' + mlite.token, { id: id }, function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                loadMasterSatuan();
                if (res.status === 'success') {
                    alert('Data berhasil dihapus!');
                } else {
                    alert(res.message || 'Gagal menghapus data.');
                }
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
                    if (response.status == 'success') {
                        bootbox.alert(response.pesan);
                        $('#modal-import-satuan').modal('hide');
                        $('#form-import-satuan')[0].reset();
                        loadMasterSatuan();
                    } else {
                        bootbox.alert(response.pesan);
                    }
                } catch (e) {
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
        $.post(baseURL + '/logistik_non_medis/displaymasterkategori?t=' + mlite.token, { halaman: page, cari: cari }, function (data) {
            $('#master-kategori-list').html(data);
        });
    }

    if ($('#table-master-kategori').length > 0) {
        loadMasterKategori();
    }

    $('.searchbox-masterkategori').on('submit', function (e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterKategori(1, cari);
    });

    $(document).off('click', '.pagination-master-kategori a').on('click', '.pagination-master-kategori a', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-masterkategori input[name="cari"]').val();
        loadMasterKategori(page, cari);
    });

    $('#btn-tambah-kategori').on('click', function () {
        $.post(baseURL + '/logistik_non_medis/formmasterkategori?t=' + mlite.token, function (data) {
            $('#form-kategori-content').html(data);
            $('#modal-form-kategori').modal('show');
        });
    });

    $(document).off('click', '.edit-kategori').on('click', '.edit-kategori', function () {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterkategori?t=' + mlite.token, { id: id }, function (data) {
            $('#form-kategori-content').html(data);
            $('#modal-form-kategori').modal('show');
        });
    });

    $(document).off('submit', '#form-kategori').on('submit', '#form-kategori', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-simpan-kategori');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterkategori?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.status == 'success') {
                    $('#modal-form-kategori').modal('hide');
                    loadMasterKategori();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + (res.message || 'Gagal menyimpan data.'));
                }
                btn.prop('disabled', false).text('Simpan');
            },
            error: function () {
                alert('Gagal terhubung ke server.');
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).off('click', '.hapus-kategori').on('click', '.hapus-kategori', function () {
        var id = $(this).data('id');
        if (confirm('Yakin ingin menghapus data ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmasterkategori?t=' + mlite.token, { id: id }, function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                loadMasterKategori();
                if (res.status === 'success') {
                    alert('Data berhasil dihapus!');
                } else {
                    alert(res.message || 'Gagal menghapus data.');
                }
            });
        }
    });

    // ======== MASTER VENDOR ========

    function loadMasterVendor(page = 1, cari = '') {
        $('#display-vendor').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Memuat data...</p></div>');
        $.post(baseURL + '/logistik_non_medis/displaymastervendor?t=' + mlite.token, { halaman: page, cari: cari }, function (data) {
            $('#display-vendor').html(data);
        });
    }

    if ($('#display-vendor').length > 0) {
        loadMasterVendor();
    }

    $(document).on('click', '#btn-cari-vendor', function () {
        var cari = $('#cari-vendor').val();
        loadMasterVendor(1, cari);
    });

    $(document).on('keypress', '#cari-vendor', function (e) {
        if (e.which == 13) {
            var cari = $(this).val();
            loadMasterVendor(1, cari);
        }
    });

    $(document).on('click', '#btn-tambah-vendor', function () {
        $.post(baseURL + '/logistik_non_medis/formmastervendor?t=' + mlite.token, function (data) {
            $('#modal-vendor-title').text('Tambah Vendor Baru');
            $('#form-content-vendor').html(data);
            $('#modal-vendor').modal('show');
        });
    });

    window.editVendor = function (kode) {
        $.post(baseURL + '/logistik_non_medis/formmastervendor?t=' + mlite.token, { kode_vendor: kode }, function (data) {
            $('#modal-vendor-title').text('Edit Data Vendor');
            $('#form-content-vendor').html(data);
            $('#modal-vendor').modal('show');
        });
    };

    window.detailVendor = function (kode) {
        $.post(baseURL + '/logistik_non_medis/detailmastervendor?t=' + mlite.token, { kode_vendor: kode }, function (data) {
            $('#detail-content-vendor').html(data);
            $('#modal-detail-vendor').modal('show');
        });
    };

    window.hapusVendor = function (kode) {
        if (confirm('Apakah Anda yakin ingin menghapus vendor ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmastervendor?t=' + mlite.token, { kode_vendor: kode }, function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                loadMasterVendor();
                if (res.status === 'success') {
                    alert('Data vendor berhasil dihapus!');
                } else {
                    alert(res.message || 'Gagal menghapus data vendor.');
                }
            });
        }
    };

    window.loadVendor = function (halaman) {
        var cari = $('#cari-vendor').val();
        loadMasterVendor(halaman, cari);
    };

    $(document).off('submit', '#form-vendor').on('submit', '#form-vendor', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-simpan-vendor');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemastervendor?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.status == 'success') {
                    $('#modal-vendor').modal('hide');
                    loadMasterVendor();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + (res.message || 'Gagal menyimpan data vendor.'));
                }
                btn.prop('disabled', false).text('Simpan Data');
            },
            error: function () {
                alert('Gagal terhubung ke server.');
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

    window.previewVendorFile = function (filename) {
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
        $.post(baseURL + '/logistik_non_medis/displaymasterunit?t=' + mlite.token, { halaman: page, cari: cari }, function (data) {
            $('#master-unit-list').html(data);
        }).fail(function () {
            $('#master-unit-list').html('<tr><td colspan="8" class="text-center text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><p>Gagal memuat data dari server.</p></td></tr>');
        });
    }

    if ($('#table-master-unit').length > 0) {
        loadMasterUnit();
    }

    $('.searchbox-masterunit').on('submit', function (e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterUnit(1, cari);
    });

    $(document).off('click', '.pagination-master-unit a').on('click', '.pagination-master-unit a', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-masterunit input[name="cari"]').val();
        loadMasterUnit(page, cari);
    });

    $('#btn-tambah-unit').on('click', function () {
        $.post(baseURL + '/logistik_non_medis/formmasterunit?t=' + mlite.token, function (data) {
            $('#form-unit-content').html(data);
            $('#modal-form-unit').modal('show');
        });
    });

    $(document).off('click', '.btn-edit-unit').on('click', '.btn-edit-unit', function () {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterunit?t=' + mlite.token, { id: id }, function (data) {
            $('#form-unit-content').html(data);
            $('#modal-form-unit').modal('show');
        });
    });

    $(document).off('click', '.btn-detail-unit').on('click', '.btn-detail-unit', function () {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/detailmasterunit?t=' + mlite.token, { id: id }, function (data) {
            $('#form-unit-content').html(data);
            $('#modal-form-unit').modal('show');
        });
    });

    $(document).off('submit', '#form-master-unit').on('submit', '#form-master-unit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-unit');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterunit?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.status == 'success') {
                    $('#modal-form-unit').modal('hide');
                    loadMasterUnit();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + (res.message || 'Gagal menyimpan data unit.'));
                }
                btn.prop('disabled', false).text('Simpan');
            },
            error: function () {
                alert('Gagal terhubung ke server.');
                btn.prop('disabled', false).text('Simpan');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });

    $(document).on('shown.bs.modal', '#modal-form-unit', function () {
        var $modal = $(this);
        // Destroy existing Select2 instances first to avoid duplicate init
        $modal.find('.select2-unit').each(function () {
            if ($(this).data('select2')) {
                $(this).select2('destroy');
            }
        });
        // Init parent_id dulu agar filter PJ bisa berjalan
        $modal.find('#parent_id').select2({ width: '100%', dropdownParent: $modal });
        // Trigger change pada parent_id -> akan menjalankan filterPJOptions di form script
        // (pj_unit akan di-init ulang di dalam filterPJOptions)
        $modal.find('#parent_id').trigger('change');
        // Jika pj_unit belum di-init oleh filter (misal showAllPJ), init di sini
        if (!$modal.find('#pj_unit').data('select2')) {
            $modal.find('#pj_unit').select2({ width: '100%', dropdownParent: $modal });
        }
    });

    $(document).off('click', '.btn-hapus-unit').on('click', '.btn-hapus-unit', function () {
        var id = $(this).data('id');
        bootbox.confirm("Yakin ingin menghapus data unit ini?", function (result) {
            if (result) {
                $.post(baseURL + '/logistik_non_medis/hapusmasterunit?t=' + mlite.token, { id: id }, function (response) {
                    var res = (typeof response === 'object') ? response : JSON.parse(response);
                    loadMasterUnit();
                    if (res.status === 'success') {
                        bootbox.alert('Data berhasil dihapus!');
                    } else {
                        bootbox.alert(res.message || 'Gagal menghapus data unit.');
                    }
                });
            }
        });
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
                    if (response.status == 'success') {
                        bootbox.alert(response.pesan);
                        $('#modal-import-unit').modal('hide');
                        $('#form-import-unit')[0].reset();
                        loadMasterUnit();
                    } else {
                        bootbox.alert(response.pesan);
                    }
                } catch (e) {
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
        $.post(baseURL + '/logistik_non_medis/displaymasterlokasi?t=' + mlite.token, { halaman: page, cari: cari }, function (data) {
            $('#master-lokasi-list').html(data);
        }).fail(function () {
            $('#master-lokasi-list').html('<tr><td colspan="8" class="text-center text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><p>Gagal memuat data dari server.</p></td></tr>');
        });
    }

    if ($('#table-master-lokasi').length > 0) {
        loadMasterLokasi();
    }

    $('.searchbox-masterlokasi').on('submit', function (e) {
        e.preventDefault();
        var cari = $('input[name="cari"]', this).val();
        loadMasterLokasi(1, cari);
    });

    $(document).off('click', '.pagination-master-lokasi a').on('click', '.pagination-master-lokasi a', function (e) {
        e.preventDefault();
        var page = $(this).data('page');
        var cari = $('.searchbox-masterlokasi input[name="cari"]').val();
        loadMasterLokasi(page, cari);
    });

    $('#btn-tambah-lokasi').on('click', function () {
        $.post(baseURL + '/logistik_non_medis/formmasterlokasi?t=' + mlite.token, function (data) {
            $('#form-lokasi-content').html(data);
            $('#modal-form-lokasi').modal('show');
        });
    });

    $(document).off('click', '.btn-edit-lokasi').on('click', '.btn-edit-lokasi', function () {
        var id = $(this).data('id');
        $.post(baseURL + '/logistik_non_medis/formmasterlokasi?t=' + mlite.token, { id: id }, function (data) {
            $('#form-lokasi-content').html(data);
            $('#modal-form-lokasi').modal('show');
        });
    });

    $(document).off('submit', '#form-master-lokasi').on('submit', '#form-master-lokasi', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var btn = $('#btn-save-lokasi');
        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: baseURL + '/logistik_non_medis/savemasterlokasi?t=' + mlite.token,
            type: 'POST',
            data: formData,
            success: function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                if (res.status == 'success') {
                    $('#modal-form-lokasi').modal('hide');
                    loadMasterLokasi();
                    alert('Data berhasil disimpan!');
                } else {
                    alert('Error: ' + (res.message || 'Gagal menyimpan data lokasi.'));
                }
                btn.prop('disabled', false).text('Simpan');
            },
            error: function () {
                alert('Gagal terhubung ke server.');
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

    $(document).off('click', '.btn-hapus-lokasi').on('click', '.btn-hapus-lokasi', function () {
        var id = $(this).data('id');
        if (confirm('Yakin ingin menghapus data lokasi ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapusmasterlokasi?t=' + mlite.token, { id: id }, function (response) {
                var res = (typeof response === 'object') ? response : JSON.parse(response);
                loadMasterLokasi();
                if (res.status === 'success') {
                    alert('Data berhasil dihapus!');
                } else {
                    alert(res.message || 'Gagal menghapus data lokasi.');
                }
            });
        }
    });

    window.previewDenah = function (filename) {
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

    window.loadPR = function (page = 1) {
        var cari = $('#cariPR').val();
        $('#displayPR').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Memuat data...</p></div>');
        $.post(baseURL + '/logistik_non_medis/displaypr?t=' + mlite.token, { halaman: page, cari: cari }, function (data) {
            $('#displayPR').html(data);
        }).fail(function () {
            $('#displayPR').html('<div class="alert alert-danger text-center"><i class="fa fa-exclamation-triangle"></i> Gagal memuat data. Periksa koneksi atau database.</div>');
        });
    };

    window.tambahPR = function () {
        $.post(baseURL + '/logistik_non_medis/formpr?t=' + mlite.token, function (data) {
            $('#pr_modal_content').html(data);
            $('#modalPR').modal('show');
        });
    };

    window.editPR = function (no_pr) {
        $.post(baseURL + '/logistik_non_medis/formpr?t=' + mlite.token, { no_pr: no_pr }, function (data) {
            $('#pr_modal_content').html(data);
            $('#modalPR').modal('show');
        });
    };

    window.viewPR = function (no_pr) {
        $.post(baseURL + '/logistik_non_medis/detailpr?t=' + mlite.token, { no_pr: no_pr }, function (data) {
            $('#pr_modal_content').html(data);
            $('#modalPR').modal('show');
        });
    };

    window.hapusPR = function (no_pr) {
        if (confirm('Apakah Anda yakin ingin menghapus pengajuan PR ini?')) {
            $.post(baseURL + '/logistik_non_medis/hapuspr?t=' + mlite.token, { no_pr: no_pr }, function (res) {
                loadPR();
            });
        }
    };

    window.accPR = function (no_pr) {
        if (confirm('Apakah Anda yakin ingin menyetujui (ACC) dan memberikan barang untuk PR ini?')) {
            $.post(baseURL + '/logistik_non_medis/accpr?t=' + mlite.token, { no_pr: no_pr }, function (response) {
                var res = JSON.parse(response);
                if (res.status == 'success') {
                    loadPR();
                    alert(res.message);
                } else {
                    alert(res.message);
                }
            });
        }
    };

    $(document).on('submit', '#formPR', function (e) {
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
            success: function (response) {
                var res;
                try {
                    res = (typeof response === 'object') ? response : JSON.parse(response);
                    if (res.status == 'success') {
                        $('#modalPR').modal('hide');
                        loadPR();
                        alert(res.message);
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    console.error("Save PR Error:", e, response);
                    var snippet = (typeof response === 'string') ? response.substring(0, 100) : '';
                    alert('Terjadi kesalahan format data dari server. Potongan pesan: ' + snippet);
                }
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Simpan Pengajuan');
            }
        });
    });

    if ($('#displayPR').length > 0) {
        loadPR();
    }

});

// --- MANAJEMEN VENDOR ---
window.mlite = window.mlite || {};
mlite.logistik_non_medis = {
    loadManajemen: function (kode_vendor = '') {
        var baseURL = mlite.url + '/' + mlite.admin;
        $('#display-vendor-manajemen').html('<div class="text-center p-20"><i class="fa fa-spinner fa-spin fa-3x"></i><p>Memuat data...</p></div>');
        $.post(baseURL + '/logistik_non_medis/displayvendormanajemen?t=' + mlite.token, { kode_vendor: kode_vendor }, function (data) {
            $('#display-vendor-manajemen').html(data);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#display-vendor-manajemen').html('<div class="alert alert-danger">Gagal memuat data: ' + textStatus + ' - ' + errorThrown + '</div>');
        });
    },
    formManajemen: function (id = '', kode_vendor = '') {
        var baseURL = mlite.url + '/' + mlite.admin;
        $.post(baseURL + '/logistik_non_medis/formvendormanajemen?t=' + mlite.token, { id: id, kode_vendor: kode_vendor }, function (data) {
            $('#form-vendor-manajemen').html(data);
            $('#modal-manajemen').modal('show');
        }).fail(function () {
            alert('Gagal memuat form.');
        });
    },
    saveManajemen: function (formData) {
        var baseURL = mlite.url + '/' + mlite.admin;
        $.ajax({
            url: baseURL + '/logistik_non_medis/savevendormanajemen?t=' + mlite.token,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                try {
                    var res = JSON.parse(response);
                    if (res.status == 'success') {
                        $('#modal-manajemen').modal('hide');
                        mlite.logistik_non_medis.loadManajemen($('#filter-vendor').val());
                    } else {
                        alert(res.message);
                    }
                } catch (e) {
                    alert('Error saving data: ' + response);
                }
            },
            error: function () {
                alert('Gagal mengirim data ke server.');
            }
        });
    },
    hapusManajemen: function (id) {
        var baseURL = mlite.url + '/' + mlite.admin;
        $.post(baseURL + '/logistik_non_medis/hapusvendormanajemen?t=' + mlite.token, { id: id }, function (response) {
            try {
                var res = JSON.parse(response);
                if (res.status == 'success') {
                    mlite.logistik_non_medis.loadManajemen($('#filter-vendor').val());
                } else {
                    alert(res.message);
                }
            } catch (e) {
                alert('Error deleting data.');
            }
        });
    }
};


