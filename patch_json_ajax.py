import re

with open('plugins/logistik_non_medis/view/admin/aset.registrasi.html', 'r', encoding='utf-8') as f:
    html = f.read()

old_ajax = """        $.ajax({
            url: '{?=url([ADMIN, \"logistik_non_medis\", \"displayasetregistrasi\"])?}',
            type: 'POST',
            data: {
                halaman: page,
                cari: cari,
                filter_unit: filter_unit,
                filter_sumber: filter_sumber,
                filter_kondisi: filter_kondisi
            },
            beforeSend: function() {
                $('#display-aset').html('<tr><td colspan=\"9\" class=\"text-center\" style=\"padding: 40px 0;\"><i class=\"fa fa-spinner fa-spin fa-2x\"></i><p style=\"margin-top:10px;\">Segar data...</p></td></tr>');
            },
            success: function(response) {
                $('#display-aset').html(response);
                // Build pagination from data attributes injected in response
                var $dc = $('#aset-display-data');
                var totalItems = parseInt($dc.data('jumlah')) || 0;
                var currentPage = parseInt($dc.data('halaman')) || 1;
                var totalPages = parseInt($dc.data('jml-halaman')) || 1;
                $('#total-aset-heading').text(totalItems);

                var pag = '';
                if (currentPage > 1) {
                    pag += '<li><a href="javascript:void(0)" data-page="'+(currentPage-1)+'">&laquo; Prev</a></li>';
                } else {
                    pag += '<li class="disabled"><span>&laquo; Prev</span></li>';
                }
                var sp = Math.max(1, currentPage - 2);
                var ep = Math.min(totalPages, currentPage + 2);
                if (sp > 1) {
                    pag += '<li><a href="javascript:void(0)" data-page="1">1</a></li>';
                    if (sp > 2) pag += '<li class="disabled"><span>...</span></li>';
                }
                for (var i = sp; i <= ep; i++) {
                    if (i == currentPage) {
                        pag += '<li class="active"><span>'+i+'</span></li>';
                    } else {
                        pag += '<li><a href="javascript:void(0)" data-page="'+i+'">'+i+'</a></li>';
                    }
                }
                if (ep < totalPages) {
                    if (ep < totalPages - 1) pag += '<li class="disabled"><span>...</span></li>';
                    pag += '<li><a href="javascript:void(0)" data-page="'+totalPages+'">'+totalPages+'</a></li>';
                }
                if (currentPage < totalPages) {
                    pag += '<li><a href="javascript:void(0)" data-page="'+(currentPage+1)+'">Next &raquo;</a></li>';
                } else {
                    pag += '<li class="disabled"><span>Next &raquo;</span></li>';
                }
                $('#pagination-aset').html(pag);
            },
            error: function() {
                $('#display-aset').html('<tr><td colspan=\"9\" class=\"text-center text-danger\" style=\"padding: 40px 0;\"><i class=\"fa fa-exclamation-triangle fa-2x\"></i><p style=\"margin-top:10px;\">Gagal memuat data dari server.</p></td></tr>');
            }
        });"""

new_ajax = """        $.ajax({
            url: '{?=url([ADMIN, \"logistik_non_medis\", \"displayasetregistrasi\"])?}',
            type: 'POST',
            dataType: 'json',
            data: {
                halaman: page,
                cari: cari,
                filter_unit: filter_unit,
                filter_sumber: filter_sumber,
                filter_kondisi: filter_kondisi
            },
            beforeSend: function() {
                $('#display-aset').html('<tr><td colspan=\"9\" class=\"text-center\" style=\"padding: 40px 0;\"><i class=\"fa fa-spinner fa-spin fa-2x\"></i><p style=\"margin-top:10px;\">Memuat data...</p></td></tr>');
                $('#pagination-aset').html('');
            },
            success: function(res) {
                $('#display-aset').html(res.html);
                var totalItems = parseInt(res.jumlah) || 0;
                var currentPage = parseInt(res.halaman) || 1;
                var totalPages = parseInt(res.jml_halaman) || 1;
                $('#total-aset-heading').text(totalItems);

                var pag = '';
                if (currentPage > 1) {
                    pag += '<li><a href="javascript:void(0)" data-page="'+(currentPage-1)+'">&laquo; Prev</a></li>';
                } else {
                    pag += '<li class="disabled"><span>&laquo; Prev</span></li>';
                }
                var sp = Math.max(1, currentPage - 2);
                var ep = Math.min(totalPages, currentPage + 2);
                if (sp > 1) {
                    pag += '<li><a href="javascript:void(0)" data-page="1">1</a></li>';
                    if (sp > 2) pag += '<li class="disabled"><span>...</span></li>';
                }
                for (var i = sp; i <= ep; i++) {
                    if (i == currentPage) {
                        pag += '<li class="active"><span>'+i+'</span></li>';
                    } else {
                        pag += '<li><a href="javascript:void(0)" data-page="'+i+'">'+i+'</a></li>';
                    }
                }
                if (ep < totalPages) {
                    if (ep < totalPages - 1) pag += '<li class="disabled"><span>...</span></li>';
                    pag += '<li><a href="javascript:void(0)" data-page="'+totalPages+'">'+totalPages+'</a></li>';
                }
                if (currentPage < totalPages) {
                    pag += '<li><a href="javascript:void(0)" data-page="'+(currentPage+1)+'">Next &raquo;</a></li>';
                } else {
                    pag += '<li class="disabled"><span>Next &raquo;</span></li>';
                }
                $('#pagination-aset').html(pag);
            },
            error: function() {
                $('#display-aset').html('<tr><td colspan=\"9\" class=\"text-center text-danger\" style=\"padding: 40px 0;\"><i class=\"fa fa-exclamation-triangle fa-2x\"></i><p style=\"margin-top:10px;\">Gagal memuat data dari server.</p></td></tr>');
            }
        });"""

if old_ajax in html:
    html = html.replace(old_ajax, new_ajax)
    print("Updated AJAX call successfully.")
else:
    print("ERROR: could not find old AJAX block")

with open('plugins/logistik_non_medis/view/admin/aset.registrasi.html', 'w', encoding='utf-8') as f:
    f.write(html)
