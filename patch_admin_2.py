import re

with open('plugins/logistik_non_medis/Admin.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 3. Modify next_status for Routine in postProsesLogistikSppb
routine_target = """      } else {
          if ($cek['status'] !== 'Proses Logistik') {
              echo json_encode(['status' => 'error', 'message' => 'Status tidak valid untuk proses logistik.']);
              exit();
          }
          $next_status = 'Siap Ambil';
      }"""
routine_replacement = """      } else {
          if ($cek['status'] !== 'Proses Logistik') {
              echo json_encode(['status' => 'error', 'message' => 'Status tidak valid untuk proses logistik.']);
              exit();
          }
          $next_status = 'Pengajuan Dana ke Bendahara';
      }"""
if routine_target in content:
    content = content.replace(routine_target, routine_replacement)
    print("Replaced next_status for Routine")
else:
    print("Failed to replace next_status for Routine")

# 4. Add bendahara approval to can_siap_ambil / UI logic in anyDisplaySppb
can_siap_ambil_target = """          $row['can_siap_ambil'] = ($role === 'admin' || $role === 'logistik') && (
              $row_status === 'Proses Pengadaan'
              || ($row_jenis === 'Non Rutin' && in_array($row_status, ['Diserahkan ke Keuangan','Pengajuan Dana ke Bendahara'], true))
          );"""
can_siap_ambil_replacement = """          $row['can_siap_ambil'] = ($role === 'admin' || $role === 'logistik') && (
              $row_status === 'Proses Pengadaan'
          );
          $row['can_bendahara_approve'] = ($role === 'admin' || $role === 'bendahara') && in_array($row_status, ['Diserahkan ke Keuangan','Pengajuan Dana ke Bendahara'], true);"""
if can_siap_ambil_target in content:
    content = content.replace(can_siap_ambil_target, can_siap_ambil_replacement)
    print("Replaced can_siap_ambil logic")
else:
    print("Failed to replace can_siap_ambil logic")

with open('plugins/logistik_non_medis/Admin.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Patch applied to Admin.php successfully.")
