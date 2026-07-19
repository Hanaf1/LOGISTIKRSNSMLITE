import os, glob, re

target_dir = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin'
html_files = glob.glob(os.path.join(target_dir, '*.html'))

pattern_to_replace = re.compile(
    r'<div class="pull-right"[^>]*>.*?<a href="\{\?\=url\(\[ADMIN, \'logistik_non_medis\', \'manage\'\]\)\?\}".*?Kembali ke Menu.*?</a>.*?</div>',
    re.IGNORECASE | re.DOTALL
)

category_map = {
    'master': 'Data Master',
    'pengadaan': 'Pengadaan',
    'gudang': 'Gudang',
    'distribusi': 'Distribusi',
    'aset': 'Aset',
    'laporan': 'Laporan',
    'perencanaan': 'Perencanaan'
}

breadcrumb_style = '''<style>
                    .custom-breadcrumb {
                        display: inline-flex;
                        align-items: center;
                        background: #f8f9fa;
                        border: 1px solid #e1e4e8;
                        border-radius: 6px;
                        padding: 6px 15px;
                        font-size: 13px;
                        margin-bottom: 15px;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                    }
                    .custom-breadcrumb a {
                        color: #4a4a4a;
                        text-decoration: none;
                        display: inline-flex;
                        align-items: center;
                        font-weight: 500;
                    }
                    .custom-breadcrumb a:hover {
                        color: #337ab7;
                    }
                    .custom-breadcrumb .separator {
                        margin: 0 10px;
                        color: #a0a0a0;
                        font-size: 11px;
                    }
                    .custom-breadcrumb .active {
                        color: #337ab7;
                        font-weight: 600;
                    }
                </style>'''

for filepath in html_files:
    filename = os.path.basename(filepath)
    if filename == 'manage.html' or filename == 'master.barang.html':
        continue # skip manage dashboard and already processed file

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    if 'Kembali ke Menu' in content and 'pull-right' in content:
        # Determine breadcrumb from filename
        parts = filename.split('.')
        category = 'Menu Utama'
        page_name = ''
        if len(parts) >= 2:
            cat_key = parts[0]
            category = category_map.get(cat_key, cat_key.capitalize())
            page_name = parts[1].replace('_', ' ').title()
            if page_name.upper() in ['SPPB', 'PO', 'PR', 'COA', 'HPS']:
                page_name = page_name.upper()

        if page_name == '':
            page_name = filename.replace('.html', '').replace('_', ' ').title()

        breadcrumb_html = f'''{breadcrumb_style}
                <div class="custom-breadcrumb">
                    <a href="{{?=url([ADMIN, 'logistik_non_medis', 'manage'])?}}"><i class="fa fa-home"></i></a>
                    <i class="fa fa-chevron-right separator"></i>
                    <a href="{{?=url([ADMIN, 'logistik_non_medis', 'manage'])?}}">{category}</a>
                    <i class="fa fa-chevron-right separator"></i>
                    <span class="active">{page_name}</span>
                </div>'''

        new_content = pattern_to_replace.sub(breadcrumb_html, content)
        
        # fix the h3 margin if necessary
        new_content = new_content.replace('<h3 class="panel-title">', '<h3 class="panel-title" style="margin-top: 5px;">')

        if new_content != content:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f'Processed {filename}')
