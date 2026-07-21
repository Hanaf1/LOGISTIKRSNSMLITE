import re, os

view_dir = r'c:\laragon\www\mlite_rsns\plugins\logistik_non_medis\view\admin'

# Files that use $index inside {loop} template tags
files_with_index = [
    'pengadaan.po.cetak.html',
    'master.vendor.display.html',
    'gudang.opname.rekap.html',
    'gudang.opname.print.html',
    'gudang.opname.form.html',
]

for fname in files_with_index:
    fpath = os.path.join(view_dir, fname)
    if not os.path.exists(fpath):
        print(f"SKIP (not found): {fname}")
        continue

    with open(fpath, 'r', encoding='utf-8') as f:
        content = f.read()

    original = content

    # Strategy: Before the {loop: ... as $row} that contains $index,
    # inject a counter init, then replace $index with counter increment
    # Find all {loop: ... as $row} patterns that are followed by $index usage
    
    # 1. Add counter init after {loop: X as Y} if $index is used nearby
    def add_counter(match):
        return match.group(0) + '\n{?php $__i = 0; ?}'
    
    # Only inject if $index appears (already confirmed above)
    # Find loop tags
    content = re.sub(
        r'(\{loop:[^\}]+\})',
        add_counter,
        content,
        count=1  # only first loop per file (adjust if multiple loops)
    )
    
    # Replace {?= $index + 1 ...?} or {?= $index ...?} patterns
    content = re.sub(
        r'\$index\s*\+\s*1(\s*\+\s*[^?]+)?(?=\s*\?)',
        lambda m: '++$__i' + (m.group(1) if m.group(1) else ''),
        content
    )
    content = re.sub(
        r'\$index(?!\w)',
        '$__i',
        content
    )

    if content != original:
        with open(fpath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"FIXED: {fname}")
    else:
        print(f"NO CHANGE: {fname}")
