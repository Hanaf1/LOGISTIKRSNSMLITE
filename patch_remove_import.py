import re

with open('plugins/logistik_non_medis/view/admin/distribusi.sppb.html', 'r', encoding='utf-8') as f:
    html = f.read()

# Remove the button
html = re.sub(
    r'\{if: !empty\(\$can_manage_distribusi_controls\)\}\s*\{if: empty\(\$jenis_page\) \|\| \$jenis_page == \'Rutin\'\}\s*<button class="btn btn-warning" data-toggle="modal" data-target="#modal-import-sppb".*?<\/button>\s*\{\/if\}\s*\{\/if\}\s*',
    '', html
)

# Remove the modal
modal_pattern = r'<!-- Modal Import SPPB Mingguan -->.*?</div>\s*</div>\s*</div>\s*\{/if\}'
html = re.sub(modal_pattern, '{/if}', html, flags=re.DOTALL)

with open('plugins/logistik_non_medis/view/admin/distribusi.sppb.html', 'w', encoding='utf-8') as f:
    f.write(html)
