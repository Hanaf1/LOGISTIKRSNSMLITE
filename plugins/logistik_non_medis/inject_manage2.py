import sys

path = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin\manage.html'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

target = """            <h4 style="display:none;"><i class="fa fa-database"></i> Master Data</h4>
            <hr style="margin-top: 5px; margin-bottom: 15px;">
            <ul class="modules" style="padding:0;margin:0;">"""

insertion = """            <h4 style="display:none;"><i class="fa fa-database"></i> Master Data</h4>
            <hr style="margin-top: 5px; margin-bottom: 15px;">
            <ul class="modules" style="padding:0;margin:0;">
                <li class="module">
                    <div class="panel panel-default">
                        <a href="{?=url([ADMIN,'logistik_non_medis','masterkategoriaset'])?}" class="panel-body text-center" style="background-color: #fcfcfc;">
                            <div class="panel-thumb text-primary">
                                <i class="fa fa-tags"></i>
                                <div class="desc">Master Kategori Aset</div>
                            </div>
                            <h4 class="text-primary"><b>Kategori Aset</b></h4>
                        </a>
                    </div>
                </li>"""

if target in content:
    content = content.replace(target, insertion)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("manage.html updated for Master Data")
else:
    print("Target not found in manage.html")
