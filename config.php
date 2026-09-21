<?php

// Rahasia dan override khusus mesin disimpan di config.local.php (tidak masuk Git).
$localConfigFile = __DIR__.'/config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) {
    throw new RuntimeException('config.local.php harus mengembalikan array.');
}
$configValue = static function ($environment, $localKey, $default = '') use ($localConfig) {
    $value = getenv($environment);
    return $value !== false ? $value : ($localConfig[$localKey] ?? $default);
};

// Konfigurasi Database
define('DBHOST', $configValue('MLITE_DB_HOST', 'DBHOST', 'localhost'));
define('DBPORT', $configValue('MLITE_DB_PORT', 'DBPORT', '3306'));
define('DBUSER', $configValue('MLITE_DB_USER', 'DBUSER', 'root'));
define('DBPASS', $configValue('MLITE_DB_PASS', 'DBPASS', ''));

// Pilih database yang dipakai aplikasi: 'dump' atau 'empty'.
// Dapat dioverride tanpa edit file dengan environment MLITE_DB_USE.
define('DB_USE', $configValue('MLITE_DB_USE', 'DB_USE', 'dump'));
$databaseNames = [
    'dump' => 'mlite_rsns',
    'empty' => 'mlite_rsns_empty',
];
if (!isset($databaseNames[DB_USE])) {
    throw new RuntimeException('Pilihan database tidak dikenal: '.DB_USE);
}
define('DBNAME', $databaseNames[DB_USE]);
unset($databaseNames);

// Mode pengembangan default mati untuk keamanan production.
define('DEV_MODE', filter_var($configValue('MLITE_DEV_MODE', 'DEV_MODE', false), FILTER_VALIDATE_BOOLEAN));


// Kunci REST plugins/logistik_non_medis/public/api-inventaris.php (header X-API-Key).
define('LOGISTIK_NON_MEDIS_API_KEY', $configValue('LOGISTIK_NON_MEDIS_API_KEY', 'LOGISTIK_NON_MEDIS_API_KEY', ''));

// Contoh: 'https://helpdesk.rs/tiket/baru?kode_aset={kode_aset}&kode_unit={kode_unit}&sumber=qr'
define('LOGISTIK_NON_MEDIS_HELPDESK_URL_LAPOR', '');
define('LOGISTIK_NON_MEDIS_HELPDESK_URL_MAINTENANCE', '');

unset($configValue, $localConfig, $localConfigFile);

// Konfigurasi Path & Struktur Folder
define('ADMIN', 'admin');
define('MODULES', BASE_DIR . '/plugins');
define('THEMES', BASE_DIR . '/themes');
define('UPLOADS', BASE_DIR . '/uploads');
define('WEBAPPS_PATH', BASE_DIR.'/webapps');
define('MULTI_APP', false);
define('MULTI_APP_REDIRECT', '');

