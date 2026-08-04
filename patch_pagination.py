import re

# Fix 1: Remove pagination script from display.html (let the main page handle it)
with open('plugins/logistik_non_medis/view/admin/aset.registrasi.display.html', 'r', encoding='utf-8') as f:
    html = f.read()

# Remove the <script> block that tries to update pagination
script_start = html.find('\n<script>')
if script_start == -1:
    script_start = html.find('<script>')
script_end = html.find('</script>') + len('</script>')

if script_start != -1 and script_end > script_start:
    html = html[:script_start] + '\n' + html[script_end:]
    print("Removed script from display.html")
else:
    print("Script not found in display.html")

with open('plugins/logistik_non_medis/view/admin/aset.registrasi.display.html', 'w', encoding='utf-8') as f:
    f.write(html)

# Fix 2: Update loadAset() in aset.registrasi.html to build pagination from response data
with open('plugins/logistik_non_medis/view/admin/aset.registrasi.html', 'r', encoding='utf-8') as f:
    main_html = f.read()

old_success = '''            success: function(response) {
                $('#display-aset').html(response);
            },'''

new_success = '''            success: function(response) {
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
            },'''

if old_success in main_html:
    main_html = main_html.replace(old_success, new_success)
    print("Updated loadAset success callback")
else:
    print("ERROR: Could not find old success callback")

with open('plugins/logistik_non_medis/view/admin/aset.registrasi.html', 'w', encoding='utf-8') as f:
    f.write(main_html)

print("Done.")
