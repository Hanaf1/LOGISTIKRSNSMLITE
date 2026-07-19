<?php
$c = file_get_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/view/admin/master.barang.html');

// Remove search box form
$c = preg_replace('/<div class="col col-md-6">\s*<form action="" class="searchbox-masterbarang">.*?<\/form>\s*<\/div>/s', '', $c);

// Remove pagination row
$c = preg_replace('/<div class="row clearfix" style="margin-top: 15px;">\s*<div class="col-md-12 text-right">\s*<ul class="pagination pagination-sm pagination-master-barang" style="margin:0;">\s*<!-- Pagination goes here -->\s*<\/ul>\s*<\/div>\s*<\/div>/s', '', $c);

// Clear tbody content
$c = preg_replace('/<tbody id="master-barang-list">.*?<\/tbody>/s', '<tbody id="master-barang-list"></tbody>', $c);

file_put_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/view/admin/master.barang.html', $c);
echo "HTML modified successfully!";
