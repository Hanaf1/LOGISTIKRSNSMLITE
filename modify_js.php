<?php
$c = file_get_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/js/admin/logistik.js');

// Remove loadMasterBarang and its related bindings
$c = preg_replace('/function loadMasterBarang.*?\}\);\s*\n/s', '', $c);

$datatables_js = <<<'EOD'
    // Initialize DataTables for Master Barang
    if($('#table-master-barang').length > 0) {
        var tableMasterBarang = $('#table-master-barang').DataTable({
            "language": {
                "url": baseURL + "/../assets/jscripts/datatables/i18n/Indonesian.json",
                "emptyTable": "Tidak ada data yang tersedia pada tabel ini",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
                "infoEmpty": "Menampilkan 0 sampai 0 dari 0 entri",
                "infoFiltered": "(disaring dari _MAX_ entri keseluruhan)",
                "lengthMenu": "Tampilkan _MENU_ entri",
                "loadingRecords": "Sedang memuat...",
                "processing": "Sedang memproses...",
                "search": "Cari:",
                "zeroRecords": "Tidak ditemukan data yang sesuai",
                "paginate": {
                    "first": "Pertama",
                    "last": "Terakhir",
                    "next": "Selanjutnya",
                    "previous": "Sebelumnya"
                }
            },
            "ajax": {
                "url": baseURL + "/logistik_non_medis/datatablesmasterbarang?t=" + mlite.token,
                "type": "GET"
            },
            "deferRender": true,
            "order": [[0, "desc"]]
        });
    }

    // Refresh DataTables on save/delete instead of loadMasterBarang
    // We will hook this later or just reload
    
    // Handle Import Form Submission
    $('#btn-proses-import').on('click', function() {
        var form = $('#form-import-barang')[0];
        var data = new FormData(form);
        
        var fileInput = $('input[name="file"]', form).val();
        if(!fileInput) {
            bootbox.alert("Silakan pilih file CSV terlebih dahulu!");
            return;
        }

        $('#import-loading').show();
        $(this).prop('disabled', true);
        
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
                $('#btn-proses-import').prop('disabled', false);
                try {
                    var response = JSON.parse(res);
                    if(response.status == 'success') {
                        bootbox.alert(response.pesan);
                        $('#modal-import-barang').modal('hide');
                        $('#form-import-barang')[0].reset();
                        if(typeof tableMasterBarang !== 'undefined') {
                            tableMasterBarang.ajax.reload();
                        }
                    } else {
                        bootbox.alert(response.pesan);
                    }
                } catch(e) {
                    bootbox.alert("Terjadi kesalahan sistem saat parsing respon import.");
                }
            },
            error: function (e) {
                $('#import-loading').hide();
                $('#btn-proses-import').prop('disabled', false);
                bootbox.alert("Terjadi kesalahan jaringan atau server.");
            }
        });
    });

EOD;

$c = preg_replace('/\/\/ ======== MASTER BARANG ========/s', "// ======== MASTER BARANG ========\n\n" . $datatables_js, $c);

// Also need to find everywhere loadMasterBarang() is called after saving/deleting and replace with tableMasterBarang.ajax.reload(null, false);
$c = str_replace('loadMasterBarang();', 'if(typeof tableMasterBarang !== "undefined") tableMasterBarang.ajax.reload(null, false);', $c);
$c = str_replace('loadMasterBarang(1);', 'if(typeof tableMasterBarang !== "undefined") tableMasterBarang.ajax.reload(null, false);', $c);
// Some might have loadMasterBarang(page) or something. Just search and replace roughly.

file_put_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/js/admin/logistik.js', $c);
echo "logistik.js modified successfully!";
