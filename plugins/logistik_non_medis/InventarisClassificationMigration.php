<?php

namespace Plugins\Logistik_non_medis;

require_once __DIR__ . '/InventarisClassification.php';

/** Struktur dan klasifikasi data dipisahkan; apply hanya menerima snapshot yang ditinjau. */
final class InventarisClassificationMigration
{
    public static function snapshot(\PDO $pdo, bool $lock = false): array
    {
        $hasHistory = (bool)$pdo->query("SHOW TABLES LIKE 'rsns_custom_logistik_non_medis_aset_penyusutan'")->fetchColumn();
        $history = $hasHistory
            ? 'EXISTS (SELECT 1 FROM rsns_custom_logistik_non_medis_aset_penyusutan p WHERE p.kode_aset=a.kode_aset)'
            : '0';
        return $pdo->query("SELECT a.*, {$history} AS has_depreciation_history
            FROM rsns_custom_logistik_non_medis_aset a ORDER BY a.id" . ($lock ? ' FOR UPDATE' : ''))
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function propose(array $rows): array
    {
        $items = [];
        $summary = [];
        foreach ($rows as $row) {
            $before = $row['klasifikasi_pencatatan'] ?? InventarisClassification::UNKNOWN;
            $after = $before;
            $reason = 'Sudah diklasifikasikan';
            if ($before === InventarisClassification::UNKNOWN) {
                $candidate = InventarisClassification::fromPrice($row['harga_beli'] ?? null);
                if (!empty($row['has_depreciation_history']) || (float)($row['akumulasi_penyusutan'] ?? 0) != 0) {
                    $reason = 'Perlu rekonsiliasi riwayat penyusutan';
                } elseif (stripos($row['user_input'] ?? '', 'import') !== false) {
                    $reason = 'Validasi harga perolehan hasil impor melalui form registrasi';
                } elseif ($candidate === InventarisClassification::UNKNOWN) {
                    $reason = 'Harga belum valid atau tepat Rp1.000.000';
                } else {
                    $after = $candidate;
                    $reason = 'Harga perolehan per unit, tanpa riwayat penyusutan';
                }
            }
            $items[] = ['id' => $row['id'], 'kode_aset' => $row['kode_aset'],
                'harga_beli' => $row['harga_beli'], 'before' => $before, 'after' => $after, 'reason' => $reason];
            if (!isset($summary[$after])) $summary[$after] = ['jumlah' => 0, 'nilai_perolehan' => 0];
            $summary[$after]['jumlah']++;
            $summary[$after]['nilai_perolehan'] += (float)$row['harga_beli'];
        }
        return ['snapshot_hash' => hash('sha256', json_encode($rows)), 'summary' => $summary, 'items' => $items];
    }

    public static function apply(\PDO $pdo, array $reviewed, string $backupPath): int
    {
        // DDL must run before this method, outside the transaction.
        $pdo->beginTransaction();
        try {
            $rows = self::snapshot($pdo, true);
            $plan = self::propose($rows);
            if (($reviewed['snapshot_hash'] ?? '') !== $plan['snapshot_hash'] || ($reviewed['items'] ?? []) !== $plan['items']) {
                throw new \RuntimeException('Data/preview berubah. Buat dan tinjau preview baru.');
            }
            $backup = json_encode(['created_at' => date(DATE_ATOM), 'rows' => $rows, 'plan' => $plan], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $fh = @fopen($backupPath, 'x');
            if (!$fh) throw new \RuntimeException('Cadangan harus memakai file baru yang dapat ditulis.');
            try {
                if (fwrite($fh, $backup) !== strlen($backup) || !fflush($fh)) {
                    throw new \RuntimeException('Cadangan tidak lengkap; migrasi dibatalkan.');
                }
            } finally {
                fclose($fh);
            }
            $update = $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset SET klasifikasi_pencatatan=? WHERE id=? AND klasifikasi_pencatatan=?");
            $count = 0;
            foreach ($plan['items'] as $item) {
                if ($item['before'] === $item['after']) continue;
                $update->execute([$item['after'], $item['id'], $item['before']]);
                if ($update->rowCount() !== 1) throw new \RuntimeException('Konflik klasifikasi; migrasi dibatalkan.');
                $count++;
            }
            $pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
