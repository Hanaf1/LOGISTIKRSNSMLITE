import re

with open('plugins/logistik_non_medis/Admin.php', 'r', encoding='utf-8') as f:
    admin_content = f.read()

target = """      if ($cek['status'] !== 'Proses Pengadaan') {
          echo json_encode(['status' => 'error', 'message' => 'Status belum masuk tahap proses pengadaan.']);
          exit();
      }"""

replacement = """      if ($cek['jenis_permintaan'] !== 'Non Rutin' && in_array($cek['status'], ['Diserahkan ke Keuangan','Pengajuan Dana ke Bendahara'], true)) {
          $update = $this->db('rsns_custom_logistik_non_medis_sppb')
                         ->where('no_sppb', $no_sppb)
                         ->update([
                             'status' => 'Siap Ambil',
                             'user_verifikasi' => $user,
                             'tgl_verifikasi' => date('Y-m-d H:i:s')
                         ]);
          if ($update) {
              $this->_logAction('logistik_non_medis_sppb', 'SPPB Siap Ambil (ACC Bendahara): ' . $no_sppb, 'U');
              $this->_buatNotifSiapAmbilSppb($no_sppb, $cek['kode_unit']);
              echo json_encode(['status' => 'success']);
          } else {
              echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status.']);
          }
          exit();
      }

      if ($cek['status'] !== 'Proses Pengadaan') {
          echo json_encode(['status' => 'error', 'message' => 'Status belum masuk tahap proses pengadaan.']);
          exit();
      }"""

if target in admin_content:
    admin_content = admin_content.replace(target, replacement)
    print("Replaced routine logic in postSiapAmbilSppb")
else:
    print("Failed to replace routine logic in postSiapAmbilSppb")

with open('plugins/logistik_non_medis/Admin.php', 'w', encoding='utf-8') as f:
    f.write(admin_content)
