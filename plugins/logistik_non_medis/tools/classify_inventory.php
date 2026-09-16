<?php
// CLI only: use explicit environment credentials, never load the application's production config.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/InventarisClassificationMigration.php';

use Plugins\Logistik_non_medis\InventarisClassification;
use Plugins\Logistik_non_medis\InventarisClassificationMigration;

try {
    $options = getopt('', ['schema', 'preview:', 'apply:', 'backup:', 'reconcile-history']);
    if (!getenv('INVENTARIS_DSN') || count(array_intersect(array_keys($options), ['schema', 'preview', 'apply'])) !== 1) {
        throw new RuntimeException('Set INVENTARIS_DSN, INVENTARIS_DB_USER, INVENTARIS_DB_PASSWORD. Pilih --schema, --preview=file.json, atau --apply=file.json --backup=file-baru.json.');
    }
    $pdo = new PDO(getenv('INVENTARIS_DSN'), getenv('INVENTARIS_DB_USER'), getenv('INVENTARIS_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if (isset($options['schema'])) {
        InventarisClassification::ensureSchema($pdo);
        echo "Struktur klasifikasi siap; data lama belum diklasifikasikan.\n";
    } elseif (isset($options['preview'])) {
        $plan = InventarisClassificationMigration::propose(InventarisClassificationMigration::snapshot($pdo), array_key_exists('reconcile-history', $options));
        $fh = @fopen($options['preview'], 'x');
        if (!$fh) throw new RuntimeException('Gunakan nama file preview baru.');
        $json = json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $written = fwrite($fh, $json);
        fclose($fh);
        if ($written !== strlen($json)) throw new RuntimeException('Gagal menulis preview lengkap.');
        echo json_encode($plan['summary'], JSON_PRETTY_PRINT) . "\nPreview dibuat; tidak ada data yang diubah.\n";
    } else {
        if (empty($options['backup'])) throw new RuntimeException('--backup wajib diisi.');
        $reviewed = json_decode(file_get_contents($options['apply']), true, 512, JSON_THROW_ON_ERROR);
        $count = InventarisClassificationMigration::apply($pdo, $reviewed, $options['backup'], array_key_exists('reconcile-history', $options));
        echo "Klasifikasi diperbarui: {$count} barang. Riwayat penyusutan tidak diubah.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
