<?php

namespace Plugins\Logistik_non_medis;

/** Kebijakan internal RS; harga adalah perolehan per unit yang sudah valid. */
final class InventarisClassification
{
    public const ASSET = 'ASET';
    public const NON_ASSET = 'INVENTARIS_NON_ASET';
    public const UNKNOWN = 'BELUM_DITENTUKAN';

    public static function fromPrice($price): string
    {
        if (!is_numeric($price) || !is_finite((float)$price) || $price <= 0) {
            return self::UNKNOWN;
        }
        if ((float)$price === 1000000.0) {
            // Keputusan kebijakan untuk nilai tepat batas belum ditetapkan.
            return self::UNKNOWN;
        }
        return $price < 1000000 ? self::NON_ASSET : self::ASSET;
    }

    public static function label(?string $value): string
    {
        return [self::ASSET => 'Aset', self::NON_ASSET => 'Inventaris Non-Aset',
            self::UNKNOWN => 'Belum Ditentukan', 'CAMPURAN' => 'Campuran'][$value ?? ''] ?? 'Belum Ditentukan';
    }

    public static function validFilter($value): string
    {
        return in_array($value, [self::ASSET, self::NON_ASSET, self::UNKNOWN], true) ? $value : '';
    }

    public static function ensureSchema(\PDO $pdo): void
    {
        $column = $pdo->query("SHOW COLUMNS FROM rsns_custom_logistik_non_medis_aset LIKE 'klasifikasi_pencatatan'")->fetch();
        if (!$column) {
            // Data lama sengaja belum diklasifikasikan. Gunakan preview migrasi terpisah.
            $pdo->exec("ALTER TABLE rsns_custom_logistik_non_medis_aset
                ADD klasifikasi_pencatatan ENUM('ASET','INVENTARIS_NON_ASET','BELUM_DITENTUKAN') NOT NULL DEFAULT 'BELUM_DITENTUKAN',
                ADD INDEX idx_klasifikasi_status (klasifikasi_pencatatan, status)");
        }
    }
}
