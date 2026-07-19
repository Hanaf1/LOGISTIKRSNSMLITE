import sys
path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\manage.html'
with open(path, 'r', encoding='utf-8') as f: c = f.read()

# Hapus blok Penerimaan Rutin
c = c.replace(
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
                </li>''',
''
)

with open(path, 'w', encoding='utf-8') as f: f.write(c)
