<?php

namespace Plugins\logistik_non_medis;

use Systems\SiteModule;

/**
 * API minimal untuk aplikasi RSNS Notifier.
 *
 * Endpoint ini sengaja hanya read-only dan tidak pernah mengirim detail barang,
 * harga, data pasien, atau menyediakan aksi approval tanpa login SIMRS.
 */
class Site extends SiteModule
{
    public function routes()
    {
        $this->route('logistik-notifier/health', 'getNotifierHealth');
        $this->route('logistik-notifier/feed', 'getNotifierFeed');
    }

    public function getNotifierHealth()
    {
        $this->jsonResponse([
            'status' => 'success',
            'service' => 'RSNS Logistik Notifier',
            'server_time' => date('c')
        ]);
    }

    public function getNotifierFeed()
    {
        if (!$this->isAuthorized()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Kunci perangkat tidak valid.'
            ], 401);
        }

        $this->ensureNotifierEvents();
        $afterId = max(0, (int)($_GET['after_id'] ?? 0));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $sql = "SELECT e.id, s.no_sppb, MAX(s.tgl_sppb) tgl_sppb,
                       COALESCE(MAX(u.nama_unit), MAX(s.kode_unit), '-') nama_unit,
                       COUNT(*) jumlah_item, MAX(s.status) status
                FROM rsns_custom_logistik_non_medis_notifier_event e
                JOIN rsns_custom_logistik_non_medis_v_sppb_normalized s ON s.no_sppb = e.no_sppb
                LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = s.kode_unit
                WHERE e.id > ?
                  AND s.jenis_permintaan = 'Non Rutin'
                  AND s.status = 'Konsul Pengajuan ke Kabid Umum'
                GROUP BY e.id, s.no_sppb
                ORDER BY e.id ASC
                LIMIT " . $limit;
        $stmt = $this->db()->pdo()->prepare($sql);
        $stmt->execute([$afterId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $baseUrl = rtrim(url(), '/');
        $maxId = $afterId;
        $items = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $maxId = max($maxId, $id);
            $items[] = [
                'id' => $id,
                'title' => 'Permintaan menunggu persetujuan',
                'message' => 'Ada permintaan non rutin yang menunggu keputusan Kabid Umum. Buka SIMRS untuk melihat detail.',
                'action_url' => $baseUrl . '/' . ADMIN . '/logistik_non_medis/distribusisppb'
            ];
        }

        $this->jsonResponse([
            'status' => 'success',
            'server_time' => date('c'),
            'max_id' => $maxId,
            'count' => count($items),
            'data' => $items
        ]);
    }

    private function isAuthorized(): bool
    {
        $configured = trim((string)$this->settings->get('logistik_non_medis.notifier_api_key'));
        if ($configured === '') return false;
        $timestamp = trim((string)($_SERVER['HTTP_X_RSNS_TIMESTAMP'] ?? ''));
        $nonce = trim((string)($_SERVER['HTTP_X_RSNS_NONCE'] ?? ''));
        $signature = strtolower(trim((string)($_SERVER['HTTP_X_RSNS_SIGNATURE'] ?? '')));
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300) return false;
        if (!preg_match('/^[A-Za-z0-9-]{16,80}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $signature)) return false;
        $path = (string)($_SERVER['REQUEST_URI'] ?? '/logistik-notifier/feed');
        $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $path, $configured);
        return hash_equals($expected, $signature);
    }

    private function ensureNotifierEvents()
    {
        $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `rsns_custom_logistik_non_medis_notifier_event` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `no_sppb` varchar(100) NOT NULL,
            `kode_unit` varchar(100) DEFAULT NULL,
            `tgl_dibuat` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `no_sppb` (`no_sppb`),
            KEY `tgl_dibuat` (`tgl_dibuat`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db()->pdo()->exec("INSERT IGNORE INTO rsns_custom_logistik_non_medis_notifier_event (no_sppb,kode_unit,tgl_dibuat)
            SELECT s.no_sppb, MAX(s.kode_unit), NOW()
            FROM rsns_custom_logistik_non_medis_v_sppb_normalized s
            WHERE s.jenis_permintaan='Non Rutin' AND s.status='Konsul Pengajuan ke Kabid Umum'
            GROUP BY s.no_sppb");
    }

    private function jsonResponse(array $payload, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }
}
