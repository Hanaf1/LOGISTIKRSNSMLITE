<?php
// CLI only: use explicit environment credentials, never load the application's production config.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Membulatkan harga_beli aset yang bernilai pecahan akibat pembagian harga per unit
 * saat impor. Sisa pembulatan dibagi +Rp1 ke beberapa baris pertama dalam satu
 * kelompok (largest remainder), sisanya dibulatkan turun, sehingga total per
 * kelompok tetap sama persis dengan sebelum pembulatan.
 */
try {
    $options = getopt('', ['preview', 'apply', 'all']);
    if (!getenv('INVENTARIS_DSN') || count(array_intersect(array_keys($options), ['preview', 'apply'])) !== 1) {
        throw new RuntimeException('Set INVENTARIS_DSN, INVENTARIS_DB_USER, INVENTARIS_DB_PASSWORD. Pilih --preview atau --apply, tambahkan --all untuk ikut membulatkan inventaris non aset.');
    }
    $apply = array_key_exists('apply', $options);
    $semua = array_key_exists('all', $options);

    $pdo = new PDO(getenv('INVENTARIS_DSN'), getenv('INVENTARIS_DB_USER'), getenv('INVENTARIS_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Kelompok yang punya minimal satu harga pecahan. Seluruh baris kelompok ikut
    // diambil agar total kelompok dihitung utuh, bukan hanya baris pecahannya.
    $filter = $semua ? '' : " AND a.klasifikasi_pencatatan = 'ASET'";
    $stmt = $pdo->query("SELECT a.id, a.asset_group_id, a.kode_aset, a.nama_aset, a.harga_beli, a.akumulasi_penyusutan
        FROM rsns_custom_logistik_non_medis_aset a
        WHERE a.asset_group_id IN (
            SELECT asset_group_id FROM (
                SELECT asset_group_id FROM rsns_custom_logistik_non_medis_aset
                WHERE asset_group_id IS NOT NULL AND asset_group_id > 0 AND harga_beli <> ROUND(harga_beli)
                GROUP BY asset_group_id
            ) g
        )" . $filter . "
        ORDER BY a.asset_group_id, a.id");

    $kelompok = [];
    foreach ($stmt as $row) {
        $kelompok[(int)$row['asset_group_id']][] = $row;
    }

    $rencana = [];
    $selisih_kelompok = 0;
    foreach ($kelompok as $group_id => $baris) {
        $total = 0.0;
        foreach ($baris as $b) {
            $total += (float)$b['harga_beli'];
        }
        $total_bulat = (int)round($total);
        if (abs($total - $total_bulat) > 0.01) {
            // Total kelompok sendiri bukan bilangan bulat: laporkan, jangan diam-diam digeser.
            $selisih_kelompok++;
        }

        $jumlah = count($baris);
        $dasar = intdiv($total_bulat, $jumlah);          // nilai dibulatkan turun
        $sisa = $total_bulat - ($dasar * $jumlah);       // jumlah baris yang dapat +Rp1

        $i = 0;
        foreach ($baris as $b) {
            $baru = $dasar + ($i < $sisa ? 1 : 0);
            $i++;
            if (abs((float)$b['harga_beli'] - $baru) < 0.0000001) {
                continue;
            }
            $rencana[] = [
                'id' => (int)$b['id'],
                'group' => $group_id,
                'kode_aset' => $b['kode_aset'],
                'nama_aset' => $b['nama_aset'],
                'lama' => (float)$b['harga_beli'],
                'baru' => $baru,
                'nilai_buku' => $baru - (float)$b['akumulasi_penyusutan'],
            ];
        }
    }

    if ($selisih_kelompok > 0) {
        fwrite(STDERR, "Peringatan: {$selisih_kelompok} kelompok punya total bukan bilangan bulat; total dibulatkan ke rupiah terdekat.\n");
    }

    printf("Kelompok terdampak: %d, baris diubah: %d%s\n", count($kelompok), count($rencana), $semua ? '' : ' (hanya klasifikasi ASET)');
    foreach ($rencana as $r) {
        printf("  #%d grp %d %s %-24s %s -> %s\n", $r['id'], $r['group'], $r['kode_aset'], $r['nama_aset'], rtrim(rtrim(number_format($r['lama'], 4, '.', ''), '0'), '.'), $r['baru']);
    }

    if (!$apply) {
        echo "Preview saja; tidak ada data yang diubah.\n";
        exit(0);
    }

    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE rsns_custom_logistik_non_medis_aset SET harga_beli = ?, nilai_buku = ? WHERE id = ?');
    foreach ($rencana as $r) {
        $upd->execute([$r['baru'], $r['nilai_buku'], $r['id']]);
    }
    // Header kelompok menyimpan harga satuan nominal; isi dengan nilai dibulatkan
    // turun karena sisa +Rp1 sudah melekat pada baris detail.
    $updGroup = $pdo->prepare('UPDATE rsns_custom_logistik_non_medis_asset_groups SET harga_satuan = FLOOR(harga_satuan) WHERE id = ? AND harga_satuan <> FLOOR(harga_satuan)');
    foreach (array_keys($kelompok) as $group_id) {
        $updGroup->execute([$group_id]);
    }
    $pdo->commit();

    printf("Selesai: %d baris aset dibulatkan, total per kelompok tidak berubah.\n", count($rencana));
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
