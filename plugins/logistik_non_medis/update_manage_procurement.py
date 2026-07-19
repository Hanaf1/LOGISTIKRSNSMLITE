import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\manage.html'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# Replace Perencanaan with Rencana Rutin
c = c.replace(
'''                <li class="module">
                    <div class="panel panel-default">
                        <a href="{?=url([ADMIN,'logistik_non_medis','pengadaanperencanaan'])?}" class="panel-body text-center">
                            <div class="panel-thumb">
                                <i class="fa fa-calendar-check-o"></i>
                                <div class="desc">Perencanaan Kebutuhan</div>
                            </div>
                            <h4><b>Perencanaan</b></h4>
                        </a>
                    </div>
                </li>''', 
'''                <li class="module">
                    <div class="panel panel-default">
                        <a href="{?=url([ADMIN,'logistik_non_medis','rencanarutin'])?}" class="panel-body text-center">
                            <div class="panel-thumb">
                                <i class="fa fa-calendar-check-o"></i>
                                <div class="desc">Rencana Pembelian & Realisasi</div>
                            </div>
                            <h4><b>Rencana Rutin</b></h4>
                        </a>
                    </div>
                </li>
                <li class="module">
                    <div class="panel panel-default">
                        <a href="{?=url([ADMIN,'logistik_non_medis','rencananonrutin'])?}" class="panel-body text-center">
                            <div class="panel-thumb">
                                <i class="fa fa-calendar-plus-o"></i>
                                <div class="desc">Rencana Pembelian Aset/Lainnya</div>
                            </div>
                            <h4><b>Rencana Non-Rutin</b></h4>
                        </a>
                    </div>
                </li>'''
)

# Remove E-Katalog
c = c.replace(
'''                <li class="module">
                    <div class="panel panel-default">
                        <a href="{?=url([ADMIN,'logistik_non_medis','pengadaanekatalog'])?}" class="panel-body text-center">
                            <div class="panel-thumb">
                                <i class="fa fa-globe"></i>
                                <div class="desc">E-Katalog & E-Purchasing</div>
                            </div>
                            <h4><b>E-Katalog</b></h4>
                        </a>
                    </div>
                </li>''',
''
)

# Replace Penerimaan with Penerimaan Rutin
c = c.replace(
'''                <li class="module">
                    <div class="panel panel-default">
                        <a href="{?=url([ADMIN,'logistik_non_medis','pengadaanpenerimaan'])?}" class="panel-body text-center">
                            <div class="panel-thumb">
                                <i class="fa fa-check-square-o"></i>
                                <div class="desc">Penerimaan & Verifikasi</div>
                            </div>
                            <h4><b>Penerimaan</b></h4>
                        </a>
                    </div>
                </li>''',
'''                <li class="module">
                    <div class="panel panel-default">
                        <a href="{?=url([ADMIN,'logistik_non_medis','terimarutin'])?}" class="panel-body text-center">
                            <div class="panel-thumb">
                                <i class="fa fa-check-square-o"></i>
                                <div class="desc">Penerimaan & Tambah Stok</div>
                            </div>
                            <h4><b>Penerimaan Rutin</b></h4>
                        </a>
                    </div>
                </li>'''
)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
