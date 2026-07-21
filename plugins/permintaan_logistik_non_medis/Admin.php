<?php

namespace Plugins\permintaan_logistik_non_medis;

use Systems\AdminModule;

class Admin extends AdminModule
{
    private $table = 'rsns_custom_logistik_non_medis_sppb';
    private $mappingTable = 'rsns_custom_logistik_non_medis_user_unit';

    public function init()
    {
        $this->ensureTables();
    }

    public function navigation()
    {
        return ['Permintaan Saya' => 'manage'];
    }

    public function getManage()
    {
        $this->addAssets();
        $mapping = $this->currentMapping();
        $isAdmin = $this->isAdministrator();
        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'status' => trim($_GET['status'] ?? '')
        ];
        $documents = $mapping ? $this->documents($mapping['kode_unit'], $filters) : [];
        return $this->draw('manage.html', [
            'mapping' => $mapping ? htmlspecialchars_array($mapping) : null,
            'documents' => htmlspecialchars_array($documents),
            'filters' => htmlspecialchars_array($filters),
            'is_admin' => $isAdmin,
            'status_options' => $this->statusOptions()
        ]);
    }

    public function getPengaturan()
    {
        if (!$this->isAdministrator()) return $this->forbiddenPage();
        $this->addAssets();
        $sql = "SELECT u.id, u.username, u.fullname, u.access, m.kode_unit, lu.nama_unit
                FROM mlite_users u
                LEFT JOIN {$this->mappingTable} m ON m.username=u.username AND m.aktif=1
                LEFT JOIN rsns_custom_logistik_non_medis_unit lu ON lu.kode_unit=m.kode_unit
                ORDER BY u.fullname, u.username";
        $users = $this->db()->pdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $units = $this->db('rsns_custom_logistik_non_medis_unit')->where('status', 'Aktif')->asc('nama_unit')->toArray();
        return $this->draw('settings.html', ['users' => htmlspecialchars_array($users), 'units' => htmlspecialchars_array($units)]);
    }

    public function postSimpanPengaturan()
    {
        $this->jsonHeader();
        if (!$this->isAdministrator()) return $this->json(false, 'Akses ditolak.');
        $username = trim($_POST['username'] ?? '');
        $kodeUnit = trim($_POST['kode_unit'] ?? '');
        if (!$this->validUser($username) || !$this->validUnit($kodeUnit)) return $this->json(false, 'Pengguna atau unit tidak valid.');
        $stmt = $this->db()->pdo()->prepare("INSERT INTO {$this->mappingTable} (username,kode_unit,aktif,created_at,created_by)
            VALUES (?,?,1,NOW(),?) ON DUPLICATE KEY UPDATE kode_unit=VALUES(kode_unit), aktif=1, created_at=NOW(), created_by=VALUES(created_by)");
        $stmt->execute([$username, $kodeUnit, $this->username()]);
        return $this->json(true, 'Akun berhasil dihubungkan ke unit.');
    }

    public function anyForm()
    {
        $mapping = $this->requireMappingJson(false);
        if (!$mapping) return;
        $no = trim($_POST['no_sppb'] ?? '');
        $document = ['no_sppb'=>'', 'tgl_sppb'=>date('Y-m-d'), 'sifat_permintaan'=>'Rutin', 'keterangan'=>'', 'items'=>[]];
        $mode = 'add';
        if ($no !== '') {
            $rows = $this->ownedRows($no, $mapping['kode_unit']);
            if (!$rows || $rows[0]['status'] !== 'Draft') {
                echo '<div class="alert alert-danger">Permintaan tidak ditemukan atau sudah diajukan.</div>';
                exit;
            }
            $document = $rows[0];
            $document['items'] = $rows;
            $mode = 'edit';
        }
        echo $this->draw('form.html', ['mapping'=>htmlspecialchars_array($mapping), 'document'=>htmlspecialchars_array($document), 'mode'=>$mode]);
        exit;
    }

    public function anyDetail()
    {
        $mapping = $this->requireMappingJson(false);
        if (!$mapping) return;
        $no = trim($_POST['no_sppb'] ?? '');
        $rows = $this->ownedRows($no, $mapping['kode_unit'], true);
        if (!$rows) {
            echo '<div class="alert alert-danger">Permintaan tidak ditemukan.</div>';
            exit;
        }
        $document = $rows[0];
        $document['items'] = $rows;
        $document['display_status'] = $this->documentStatus($rows);
        echo $this->draw('detail.html', ['document'=>htmlspecialchars_array($document), 'steps'=>htmlspecialchars_array($this->progressSteps($rows))]);
        exit;
    }

    public function getBarang()
    {
        $this->jsonHeader();
        if (!$this->currentMapping()) return $this->json(false, 'Akun belum terhubung ke unit.');
        $q = trim($_GET['q'] ?? '');
        $stmt = $this->db()->pdo()->prepare("SELECT kode_item AS id, CONCAT(kode_item,' - ',nama_barang) AS text, nama_barang, satuan_dasar AS satuan
            FROM rsns_custom_logistik_non_medis_master_barang
            WHERE status='Aktif' AND (kode_item LIKE ? OR nama_barang LIKE ?) ORDER BY nama_barang LIMIT 30");
        $stmt->execute(['%'.$q.'%', '%'.$q.'%']);
        echo json_encode(['results'=>$stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        exit;
    }

    public function postSimpan()
    {
        $this->jsonHeader();
        $mapping = $this->requireMappingJson();
        if (!$mapping) return;
        $no = trim($_POST['no_sppb'] ?? '');
        $submit = ($_POST['aksi'] ?? 'draft') === 'ajukan';
        $status = $submit ? 'Diajukan' : 'Draft';
        $date = trim($_POST['tgl_sppb'] ?? date('Y-m-d'));
        $sifat = ($_POST['sifat_permintaan'] ?? '') === 'Cito' ? 'Cito' : 'Rutin';
        $note = trim($_POST['keterangan'] ?? '');
        $codes = $_POST['kode_item'] ?? [];
        $quantities = $_POST['jumlah'] ?? [];
        if (!is_array($codes) || !$codes) return $this->json(false, 'Tambahkan minimal satu barang.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $this->json(false, 'Tanggal permintaan tidak valid.');

        $items = [];
        $seen = [];
        foreach ($codes as $i => $code) {
            $code = trim($code);
            $qty = (float)($quantities[$i] ?? 0);
            $master = $this->db('rsns_custom_logistik_non_medis_master_barang')->where('kode_item', $code)->where('status', 'Aktif')->oneArray();
            if (!$master || $qty <= 0) return $this->json(false, 'Barang atau jumlah pada baris '.($i + 1).' tidak valid.');
            if (isset($seen[$code])) return $this->json(false, 'Barang yang sama tidak boleh dipilih dua kali.');
            $seen[$code] = true;
            $items[] = ['code'=>$code, 'qty'=>$qty, 'unit'=>$master['satuan_dasar'] ?? ''];
        }
        if ($no !== '') {
            $old = $this->ownedRows($no, $mapping['kode_unit']);
            if (!$old || $old[0]['status'] !== 'Draft') return $this->json(false, 'Hanya permintaan Draft yang dapat diubah.');
        } else {
            $no = $this->generateNumber($mapping['kode_unit']);
        }

        $pdo = $this->db()->pdo();
        try {
            $pdo->beginTransaction();
            $del = $pdo->prepare("DELETE FROM {$this->table} WHERE no_sppb=? AND kode_unit=? AND status='Draft'");
            $del->execute([$no, $mapping['kode_unit']]);
            $insert = $pdo->prepare("INSERT INTO {$this->table}
              (no_sppb,tgl_sppb,minggu_ke,kode_unit,kode_item,jumlah,jumlah_disetujui,satuan,status,sifat_permintaan,keterangan,user_input,tgl_input)
              VALUES (?,?,?,?,?,?,0,?,?,?,?,?,NOW())");
            $week = min(4, max(1, (int)ceil((int)date('d', strtotime($date)) / 7)));
            foreach ($items as $item) $insert->execute([$no,$date,$week,$mapping['kode_unit'],$item['code'],$item['qty'],$item['unit'],$status,$sifat,$note,$this->username()]);
            $pdo->commit();
            return $this->json(true, $submit ? 'Permintaan berhasil diajukan ke logistik.' : 'Draft berhasil disimpan.', ['no_sppb'=>$no]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return $this->json(false, 'Permintaan gagal disimpan.');
        }
    }

    public function postAjukan()
    {
        $this->jsonHeader();
        $mapping = $this->requireMappingJson();
        if (!$mapping) return;
        $no = trim($_POST['no_sppb'] ?? '');
        $stmt = $this->db()->pdo()->prepare("UPDATE {$this->table} SET status='Diajukan' WHERE no_sppb=? AND kode_unit=? AND status='Draft'");
        $stmt->execute([$no, $mapping['kode_unit']]);
        return $this->json($stmt->rowCount() > 0, $stmt->rowCount() ? 'Permintaan berhasil diajukan.' : 'Draft tidak ditemukan.');
    }

    public function postHapus()
    {
        $this->jsonHeader();
        $mapping = $this->requireMappingJson();
        if (!$mapping) return;
        $stmt = $this->db()->pdo()->prepare("DELETE FROM {$this->table} WHERE no_sppb=? AND kode_unit=? AND status='Draft'");
        $stmt->execute([trim($_POST['no_sppb'] ?? ''), $mapping['kode_unit']]);
        return $this->json($stmt->rowCount() > 0, $stmt->rowCount() ? 'Draft dihapus.' : 'Draft tidak ditemukan.');
    }

    private function documents($unit, $filters)
    {
        $where = ['s.kode_unit=?']; $params = [$unit];
        if ($filters['q'] !== '') { $where[] = '(s.no_sppb LIKE ? OR b.nama_barang LIKE ?)'; $params[]='%'.$filters['q'].'%'; $params[]='%'.$filters['q'].'%'; }
        if ($filters['status'] !== '') { $where[] = 's.status=?'; $params[]=$filters['status']; }
        $sql = "SELECT s.no_sppb, MIN(s.tgl_sppb) tgl_sppb, MAX(s.sifat_permintaan) sifat_permintaan,
                COUNT(*) total_item, SUM(s.jumlah) total_qty, GROUP_CONCAT(DISTINCT s.status ORDER BY s.status SEPARATOR '|') statuses,
                MAX(s.tgl_input) tgl_input, MAX(s.keterangan) keterangan
                FROM {$this->table} s LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item=s.kode_item
                WHERE ".implode(' AND ', $where)." GROUP BY s.no_sppb ORDER BY MAX(s.tgl_input) DESC, MIN(s.id) DESC LIMIT 200";
        $stmt=$this->db()->pdo()->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) { $row['display_status']=$this->statusFromString($row['statuses']); $row['can_edit']=$row['statuses']==='Draft'; }
        return $rows;
    }

    private function ownedRows($no, $unit, $withNames=false)
    {
        if ($withNames) {
            $sql="SELECT s.*, b.nama_barang, u.nama_unit FROM {$this->table} s
                  LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item=s.kode_item
                  LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit=s.kode_unit
                  WHERE s.no_sppb=? AND s.kode_unit=? ORDER BY s.id";
            $stmt=$this->db()->pdo()->prepare($sql); $stmt->execute([$no,$unit]); return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $stmt=$this->db()->pdo()->prepare("SELECT s.*, b.nama_barang FROM {$this->table} s
          LEFT JOIN rsns_custom_logistik_non_medis_master_barang b ON b.kode_item=s.kode_item
          WHERE s.no_sppb=? AND s.kode_unit=? ORDER BY s.id");
        $stmt->execute([$no,$unit]); return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function currentMapping()
    {
        $stmt=$this->db()->pdo()->prepare("SELECT m.kode_unit,u.nama_unit,u.pj_unit FROM {$this->mappingTable} m
          JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit=m.kode_unit AND u.status='Aktif'
          WHERE m.username=? AND m.aktif=1 LIMIT 1");
        $stmt->execute([$this->username()]); return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function requireMappingJson($json=true)
    {
        $mapping=$this->currentMapping();
        if (!$mapping) { if ($json) $this->json(false, 'Akun Anda belum dihubungkan ke unit. Hubungi administrator.'); else { echo '<div class="alert alert-warning">Akun belum dihubungkan ke unit.</div>'; exit; } }
        return $mapping;
    }

    private function ensureTables()
    {
        $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$this->mappingTable}` (
          `id` int(11) NOT NULL AUTO_INCREMENT, `username` varchar(100) NOT NULL, `kode_unit` varchar(50) NOT NULL,
          `aktif` tinyint(1) NOT NULL DEFAULT 1, `created_at` datetime DEFAULT NULL, `created_by` varchar(100) DEFAULT NULL,
          PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`), KEY `kode_unit` (`kode_unit`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    }

    private function generateNumber($unit)
    {
        $safe=preg_replace('/[^A-Za-z0-9_-]/','',strtoupper($unit));
        do { $no='SPPB/'.date('Ym').'/'.$safe.'/'.date('His').'-'.strtoupper(substr(bin2hex(random_bytes(2)),0,4));
            $stmt=$this->db()->pdo()->prepare("SELECT 1 FROM {$this->table} WHERE no_sppb=? LIMIT 1"); $stmt->execute([$no]);
        } while ($stmt->fetchColumn());
        return $no;
    }

    private function documentStatus($rows) { return $this->statusFromString(implode('|', array_unique(array_column($rows,'status')))); }
    private function statusFromString($statuses) { $parts=array_values(array_filter(array_unique(explode('|',$statuses)))); return count($parts)===1 ? $parts[0] : 'Diproses Sebagian'; }
    private function progressSteps($rows)
    {
        $current=$this->documentStatus($rows); $order=['Draft','Diajukan','Disetujui Unit','Terverifikasi','Picking','Packing','Ready','Dikirim','Diterima','Selesai'];
        $index=array_search($current,$order,true); if ($index===false) $index=-1;
        $steps=[]; foreach ($order as $i=>$name) $steps[]=['name'=>$name,'done'=>$i<=$index]; return $steps;
    }
    private function statusOptions() { return ['Draft','Diajukan','Disetujui Unit','Terverifikasi','Picking','Packing','Ready','Dikirim','Diterima','Selesai','Ditolak']; }
    private function username() { return (string)$this->core->getUserInfo('username', null, true); }
    private function isAdministrator() { return $this->core->getUserInfo('access') === 'all'; }
    private function validUser($value) { return (bool)$this->db('mlite_users')->where('username',$value)->oneArray(); }
    private function validUnit($value) { return (bool)$this->db('rsns_custom_logistik_non_medis_unit')->where('kode_unit',$value)->where('status','Aktif')->oneArray(); }
    private function jsonHeader() { header('Content-Type: application/json; charset=utf-8'); }
    private function json($ok,$message,$extra=[]) { echo json_encode(array_merge(['status'=>$ok?'success':'error','message'=>$message],$extra)); exit; }
    private function forbiddenPage() { http_response_code(403); return '<div class="alert alert-danger">Akses hanya untuk administrator.</div>'; }
    private function addAssets()
    {
        $this->core->addCSS('https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        $this->core->addJS('https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js');
    }
}
