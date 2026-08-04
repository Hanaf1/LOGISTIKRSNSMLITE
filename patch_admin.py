import re

with open('plugins/logistik_non_medis/Admin.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Add bendahara to roles_to_check
roles_target = """      $roles_to_check = [
          'kepala_unit' => 'manage,distribusisppb,distribusiverifikasi,distribusikuota',
          'kepala_sie' => 'manage,distribusisppb,distribusiverifikasi,distribusikuota',
          'kepala_bidang' => 'manage,distribusisppb,distribusiverifikasi,distribusikuota'
      ];"""
roles_replacement = """      $roles_to_check = [
          'kepala_unit' => 'manage,distribusisppb,distribusiverifikasi,distribusikuota',
          'kepala_sie' => 'manage,distribusisppb,distribusiverifikasi,distribusikuota',
          'kepala_bidang' => 'manage,distribusisppb,distribusiverifikasi,distribusikuota',
          'bendahara' => 'manage,distribusisppb,distribusinonrutin'
      ];"""
if roles_target in content:
    content = content.replace(roles_target, roles_replacement)
else:
    print("Failed to replace roles_to_check")

# 2. Add bendahara to anyDisplaySppb filter
filter_target = """      // Tab filtering untuk role approver
      if (in_array($role, ['kepala_unit', 'kepala_sie', 'kepala_bidang']) && !empty($approval_tab)) {
          if ($approval_tab === 'pending') {
              if ($role === 'kepala_unit') {
                  $sql .= " AND 1=0 ";
              } elseif ($role === 'kepala_sie') {
                  $sql .= " AND s.jenis_permintaan = 'Non Rutin' AND s.status = 'Diajukan' ";
              } elseif ($role === 'kepala_bidang') {
                  $sql .= " AND s.jenis_permintaan = 'Non Rutin' AND s.status = 'Disetujui Ka. Sie' ";
              }
          } elseif ($approval_tab === 'history') {
              if ($role === 'kepala_unit') {
                  $sql .= " AND s.user_approve_ka_unit = ? ";
                  $params[] = $username;
              } elseif ($role === 'kepala_sie') {
                  $sql .= " AND s.user_approve_ka_sie = ? ";
                  $params[] = $username;
              } elseif ($role === 'kepala_bidang') {
                  $sql .= " AND s.user_approve_ka_bidang = ? ";
                  $params[] = $username;
              }
          }
      }"""
filter_replacement = """      // Tab filtering untuk role approver
      if (in_array($role, ['kepala_unit', 'kepala_sie', 'kepala_bidang', 'bendahara']) && !empty($approval_tab)) {
          if ($approval_tab === 'pending') {
              if ($role === 'kepala_unit') {
                  $sql .= " AND 1=0 ";
              } elseif ($role === 'kepala_sie') {
                  $sql .= " AND s.jenis_permintaan = 'Non Rutin' AND s.status = 'Diajukan' ";
              } elseif ($role === 'kepala_bidang') {
                  $sql .= " AND s.jenis_permintaan = 'Non Rutin' AND s.status = 'Disetujui Ka. Sie' ";
              } elseif ($role === 'bendahara') {
                  $sql .= " AND s.status = 'Pengajuan Dana ke Bendahara' ";
              }
          } elseif ($approval_tab === 'history') {
              if ($role === 'kepala_unit') {
                  $sql .= " AND s.user_approve_ka_unit = ? ";
                  $params[] = $username;
              } elseif ($role === 'kepala_sie') {
                  $sql .= " AND s.user_approve_ka_sie = ? ";
                  $params[] = $username;
              } elseif ($role === 'kepala_bidang') {
                  $sql .= " AND s.user_approve_ka_bidang = ? ";
                  $params[] = $username;
              } elseif ($role === 'bendahara') {
                  $sql .= " AND s.status IN ('Proses Pengadaan', 'Siap Ambil', 'Siap Diserahkan', 'Picking', 'Packing', 'Ready', 'Dikirim', 'Diterima', 'Selesai') ";
              }
          }
      }"""
if filter_target in content:
    content = content.replace(filter_target, filter_replacement)
else:
    print("Failed to replace anyDisplaySppb filter")

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
else:
    print("Failed to replace next_status for Routine")

# 4. Add bendahara approval to can_siap_ambil / UI logic in anyDisplaySppb
# Also allow bendahara to view history properly
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
else:
    print("Failed to replace can_siap_ambil logic")

with open('plugins/logistik_non_medis/Admin.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Patch applied to Admin.php successfully.")
