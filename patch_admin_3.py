import re

# 1. Patch Admin.php
with open('plugins/logistik_non_medis/Admin.php', 'r', encoding='utf-8') as f:
    admin_content = f.read()

# Add can_bendahara_approve logic
can_siap_ambil_target = """          $row['can_siap_ambil'] = ($role === 'admin' || $role === 'logistik') && (
              $row_status === 'Proses Pengadaan'
              || ($row_jenis === 'Non Rutin' && in_array($row_status, ['Diserahkan ke Keuangan','Pengajuan Dana ke Bendahara'], true))
          );"""
can_siap_ambil_replacement = """          $row['can_siap_ambil'] = ($role === 'admin' || $role === 'logistik') && (
              $row_status === 'Proses Pengadaan'
          );
          $row['can_bendahara_approve'] = ($role === 'admin' || $role === 'bendahara') && in_array($row_status, ['Diserahkan ke Keuangan','Pengajuan Dana ke Bendahara'], true);"""

if can_siap_ambil_target in admin_content:
    admin_content = admin_content.replace(can_siap_ambil_target, can_siap_ambil_replacement)
    print("Replaced can_siap_ambil in Admin.php")
else:
    print("Failed to replace can_siap_ambil logic in Admin.php")

# Update postSiapAmbilSppb access role
siapambil_role_target = """      if (!in_array($role, ['admin', 'logistik'], true)) {
          echo json_encode(['status' => 'error', 'message' => 'Hanya admin/logistik yang dapat menandai siap ambil.']);
          exit();
      }"""
siapambil_role_replacement = """      if (!in_array($role, ['admin', 'logistik', 'bendahara'], true)) {
          echo json_encode(['status' => 'error', 'message' => 'Hanya admin/logistik/bendahara yang dapat menandai siap ambil.']);
          exit();
      }"""
if siapambil_role_target in admin_content:
    admin_content = admin_content.replace(siapambil_role_target, siapambil_role_replacement)
    print("Replaced siapambil role access in Admin.php")
else:
    print("Failed to replace siapambil role access in Admin.php")

with open('plugins/logistik_non_medis/Admin.php', 'w', encoding='utf-8') as f:
    f.write(admin_content)


# 2. Patch HTML
with open('plugins/logistik_non_medis/view/admin/distribusi.sppb.display.html', 'r', encoding='utf-8') as f:
    html_content = f.read()

html_target = """                                {if: $value.can_siap_ambil}
                                    <button class="btn btn-xs btn-success" onclick="siapAmbil('{$value.no_sppb}')" title="{if: $value.jenis_permintaan == 'Non Rutin'}Lanjutkan tahap pengadaan / siap diserahkan{else}Tandai Siap Ambil{/if}"><i class="fa fa-check-circle"></i></button>
                                {/if}"""
html_replacement = """                                {if: $value.can_siap_ambil}
                                    <button class="btn btn-xs btn-success" onclick="siapAmbil('{$value.no_sppb}')" title="{if: $value.jenis_permintaan == 'Non Rutin'}Lanjutkan tahap pengadaan / siap diserahkan{else}Tandai Siap Ambil{/if}"><i class="fa fa-check-circle"></i></button>
                                {/if}
                                {if: $value.can_bendahara_approve}
                                    <button class="btn btn-xs btn-success" onclick="siapAmbil('{$value.no_sppb}')" title="Setujui (Bendahara)"><i class="fa fa-check-circle"></i> ACC Bendahara</button>
                                {/if}"""

if html_target in html_content:
    html_content = html_content.replace(html_target, html_replacement)
    print("Replaced HTML logic")
else:
    print("Failed to replace HTML logic")

with open('plugins/logistik_non_medis/view/admin/distribusi.sppb.display.html', 'w', encoding='utf-8') as f:
    f.write(html_content)

print("Patching complete.")
