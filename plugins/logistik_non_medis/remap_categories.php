<?php

$host = 'localhost';
$db   = 'mlite_rsns';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $rules = [
        'KAT-02' => ['printer', 'komputer', 'cpu', 'mouse', 'keyboard', 'laptop', 'monitor', 'switch', 'router', 'hub', 'telepon', 'kabel'],
        'KAT-03' => ['ac', 'kipas', 'jam', 'lampu', 'tv', 'televisi', 'dispenser', 'kulkas', 'kabel', 'stop kontak', 'exhaust'],
        'KAT-04' => ['ordner', 'staples', 'perforator', 'whiteboard', 'kalkulator', 'gunting', 'kertas', 'pulpen', 'pensil', 'spidol', 'lakban', 'lem'],
        'KAT-05' => ['tempat sampah', 'wastafel', 'cermin', 'ember', 'gayung', 'sapu', 'pel', 'kemoceng', 'keset', 'sikat', 'spill kit'],
        'KAT-06' => ['keranjang', 'kotak tisu', 'bantal', 'gelas', 'piring', 'sendok', 'garpu', 'guling', 'sprei'],
        'KAT-07' => ['gorden', 'wallpaper', 'signage', 'akrilik', 'karpet', 'pigura', 'lukisan', 'poster'],
        'KAT-01' => ['meja', 'kursi', 'lemari', 'rak', 'laci', 'sofa', 'bangku', 'bed', 'etalase'],
    ];

    $stmt = $pdo->query("SELECT kode_aset, nama_aset FROM rsns_custom_logistik_non_medis_aset");
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updates = 0;
    foreach ($assets as $asset) {
        $nama = strtolower($asset['nama_aset']);
        $kategori = 'KAT-08'; // Default
        
        foreach ($rules as $kat => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($nama, $kw) !== false) {
                    $kategori = $kat;
                    break 2;
                }
            }
        }
        
        $upd = $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset SET kode_kategori_aset = ? WHERE kode_aset = ?");
        $upd->execute([$kategori, $asset['kode_aset']]);
        $updates++;
    }

    echo "Updated $updates assets based on name matching.\n";

} catch (\PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

