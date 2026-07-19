<?php
$c = file_get_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/js/admin/logistik.js');
// replace the bad block precisely
$bad_block = "        });\n    });\n\n\n        }\n\n    if($('#table-master-barang').length > 0) {\n        if(typeof tableMasterBarang !== \"undefined\") tableMasterBarang.ajax.reload(null, false);\n    }";
$good_block = "        });\n    });\n\n    if($('#table-master-barang').length > 0) {\n        if(typeof tableMasterBarang !== \"undefined\") tableMasterBarang.ajax.reload(null, false);\n    }";
$c = str_replace($bad_block, $good_block, $c);

// Wait, the user also mentioned "kenapa pindah pindah page lama"
// This indicates that the DataTables script or something is causing slowness.
// Could it be that DataTables is fetching too much data? 
// Or maybe they mean clicking pagination in DataTables is slow?
// No, they said "pindah pindah page lama", maybe navigating between menus.
// Syntax errors in JS break the mLITE router which intercepts `a` tags, causing it to fall back to normal page load, which might be "lama" (slow) because it's a full page reload instead of AJAX.
// Fixing the syntax error will fix the router.

// Let's also check if there are other syntax errors.
// Looking at the IDE problems, line 829 was "Declaration or statement expected".
// That was caused by the extra closing brace.
// Are there any others? We'll see after fixing this.

file_put_contents('c:/laragon/www/mlite_rsns/plugins/logistik_non_medis/js/admin/logistik.js', $c);
echo "logistik.js syntax fixed!\n";
