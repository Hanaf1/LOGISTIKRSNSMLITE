<?php
$hashes = [
    'dummy_group'  => '$2y$10$lQBT/t77SaEb3VvciAma/eCRK3HI1xHbFppYXVhRUUJEEEvD/xOvi',
    'kabid_kasie_kaunit' => '$2y$10$jNWPS2Y4cy2FA5S0/P9TJuH.13z1zSAW.wRV2kt/4U1VEZrRHMDa2',
    'admin'        => '$2y$10$pgRnDiukCbiYVqsamMM3ROWViSRqbyCCL33N8.ykBKZx0dlplXe9i',
    'hanafi'       => '$2y$10$3beozJEXWo7GOKLKjoG..efAZS13wWquzi0UApkOcVHInWczIs7Ni',
    'dr001'        => '$2y$10$kuf2BxvViduBpUTn.6Nxsug3AskH/PGvXTSlfCfJqK8Ayb9a0.vqC',
];

$candidates = [
    'dummy123', 'dummy', 'Dummy123', 'Dummy',
    'password', 'password123', 'Password123',
    '123456', '12345678', '1234567890',
    'admin', 'admin123', 'Admin123',
    'test', 'test123', 'Test123',
    'logistik', 'logistik123', 'Logistik123',
    'rsns', 'rsns123', 'Rsns123',
    'hanafi', 'hanafi123',
    'kaunit', 'kasie', 'kabid',
    'kaunit123', 'kasie123', 'kabid123',
    'user123', 'user',
    'demo', 'demo123',
    'pj123', 'pj',
    '123', '1234',
];

echo "=== PASSWORD CHECK ===\n\n";
foreach ($hashes as $label => $hash) {
    echo "[ $label ]\n";
    $found = false;
    foreach ($candidates as $pwd) {
        if (password_verify($pwd, $hash)) {
            echo "  >> PASSWORD DITEMUKAN: $pwd\n";
            $found = true;
            break;
        }
    }
    if (!$found) echo "  -- Tidak ditemukan di daftar kandidat\n";
    echo "\n";
}
