<?php
/**
 * REST pelaporan kerusakan aset — pekerjaan yang BELUM dikerjakan.
 *
 *   GET   api-lapor.php                         daftar laporan
 *   GET   api-lapor.php?status=Menunggu         saring status
 *   GET   api-lapor.php?kode_aset=AST-...       laporan satu aset
 *   GET   api-lapor.php?nomor=QR-202609-0001    satu laporan
 *   POST  api-lapor.php                         buat laporan baru
 *   PATCH api-lapor.php                         perbarui status laporan
 *
 * Isian POST (JSON atau form):
 *   kode_aset   wajib
 *   keluhan     wajib, ringkasan kerusakan
 *   pelapor     wajib
 *   deskripsi   opsional
 *   prioritas   opsional: Rendah, Sedang (bawaan), Tinggi, Darurat
 *   teruskan_ke_helpdesk  opsional, bawaan false
 *
 * Isian PATCH (JSON atau form):
 *   nomor           wajib, nomor laporan di sistem ini
 *   status          wajib: Menunggu, Diproses, Selesai, Dibatalkan
 *   tiket_helpdesk  opsional, nomor tiket di sistem helpdesk
 *   teknisi         opsional
 *   tindakan        opsional, wajib bila status Selesai
 *   kondisi_akhir   opsional: Baik, Rusak Ringan, Rusak Berat
 *
 * PATCH inilah yang menutup putaran: IPSRS menutup tiket di helpdesk, lalu
 * helpdesk memanggil endpoint ini sehingga laporan di sini ikut tertutup,
 * kondisi aset diperbarui, dan pekerjaannya masuk buku maintenance.
 */

require_once(__DIR__.'/api-bersama.php');

const STATUS_SAH = ['Menunggu', 'Diproses', 'Selesai', 'Dibatalkan'];
const PRIORITAS_SAH = ['Rendah', 'Sedang', 'Tinggi', 'Darurat'];
const KONDISI_SAH = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

tolak_bila_bukan_lokal();
$pdo = koneksi();

$kolom = "p.kode_pemeliharaan, p.kode_aset, a.nama_aset, a.nomor_inventaris,
          a.kode_unit, COALESCE(iu.nama, u.nama_unit, a.kode_unit) AS nama_unit,
          a.lokasi_fisik, a.status_kondisi AS kondisi_aset_sekarang,
          p.nama_kegiatan AS keluhan, p.deskripsi, p.prioritas, p.status,
          p.tanggal_direncanakan, p.tanggal_pelaksanaan, p.nama_teknisi,
          p.tindakan_perbaikan, p.status_kondisi_akhir, p.user_input, p.tgl_input,
          o.tiket_helpdesk, o.status_helpdesk, o.status AS status_antrean, o.tgl_sinkron";
$gabung = "FROM rsns_custom_logistik_non_medis_aset_pemeliharaan p
           LEFT JOIN rsns_custom_logistik_non_medis_aset a ON a.kode_aset = p.kode_aset
           LEFT JOIN rsns_custom_logistik_non_medis_inventaris_master iu
             ON iu.jenis_master = 'UNIT' AND iu.kode = a.kode_unit
           LEFT JOIN rsns_custom_logistik_non_medis_unit u ON u.kode_unit = a.kode_unit
           LEFT JOIN rsns_custom_logistik_non_medis_helpdesk_outbox o
             ON o.kunci_idempoten = p.kode_pemeliharaan";

// Laporan = pekerjaan korektif yang lahir sebagai keluhan, bukan catatan buku
// maintenance. Keduanya berbagi tabel, jadi dibedakan lewat awalan nomornya.
$hanya_laporan = "(p.kode_pemeliharaan LIKE 'QR-%' OR p.kode_pemeliharaan LIKE 'API-%' OR p.kode_pemeliharaan LIKE 'WO-%')";

$metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function satu_laporan(PDO $pdo, $kolom, $gabung, $nomor)
{
    $stmt = $pdo->prepare("SELECT {$kolom} {$gabung} WHERE p.kode_pemeliharaan = ? LIMIT 1");
    $stmt->execute([$nomor]);
    $baris = $stmt->fetch();
    if ($baris) {
        $baris['tautan_qr'] = basis_url().'/aset-info.php?kode='.rawurlencode($baris['kode_aset']);
    }
    return $baris ?: null;
}

// ---------------------------------------------------------------------------
// POST: buat laporan baru.
// ---------------------------------------------------------------------------
if ($metode === 'POST') {
    $data = masukan();
    $kode_aset = isian($data, 'kode_aset');
    $keluhan = isian($data, 'keluhan');
    $deskripsi = isian($data, 'deskripsi');
    $pelapor = isian($data, 'pelapor');
    $prioritas = isian($data, 'prioritas') ?: 'Sedang';
    $teruskan = filter_var($data['teruskan_ke_helpdesk'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $salah = [];
    if ($kode_aset === '') {
        $salah['kode_aset'] = 'Wajib diisi.';
    }
    if (mb_strlen($keluhan) < 3 || mb_strlen($keluhan) > 200) {
        $salah['keluhan'] = 'Wajib diisi, 3 sampai 200 karakter.';
    }
    if (mb_strlen($pelapor) < 2 || mb_strlen($pelapor) > 40) {
        $salah['pelapor'] = 'Wajib diisi, 2 sampai 40 karakter.';
    }
    if (mb_strlen($deskripsi) > 2000) {
        $salah['deskripsi'] = 'Maksimal 2000 karakter.';
    }
    if (!in_array($prioritas, PRIORITAS_SAH, true)) {
        $salah['prioritas'] = 'Pilih: '.implode(', ', PRIORITAS_SAH).'.';
    }
    if ($salah) {
        balas(['error' => 'Isian tidak lengkap atau tidak sah.', 'rincian' => $salah], 422);
    }

    $aset = ambil_aset($pdo, $kode_aset);
    if (!$aset) {
        balas(['error' => 'Aset tidak ditemukan.'], 404);
    }
    if ($aset['status'] !== 'Aktif') {
        balas(['error' => 'Aset berstatus '.$aset['status'].', laporan tidak bisa dibuat.'], 409);
    }

    $cek = $pdo->prepare("SELECT kode_pemeliharaan FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
        WHERE kode_aset = ? AND nama_kegiatan = ? AND tgl_input >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1");
    $cek->execute([$kode_aset, $keluhan]);
    if ($kembar = $cek->fetchColumn()) {
        balas(['status' => 'kembar', 'pesan' => 'Laporan serupa baru saja masuk.', 'nomor' => $kembar], 200);
    }

    $catatan = $deskripsi !== '' ? $deskripsi : 'Tidak ada keterangan tambahan.';
    $catatan .= "\n\nDilaporkan lewat API oleh: ".$pelapor;
    $catatan .= "\nUnit: ".($aset['nama_unit'] ?: '-');
    if (!empty($aset['lokasi_fisik'])) {
        $catatan .= "\nLokasi: ".$aset['lokasi_fisik'];
    }

    $simpan = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_aset_pemeliharaan
        (kode_pemeliharaan, kode_aset, jenis_pemeliharaan, tanggal_direncanakan, nama_kegiatan,
         deskripsi, frekuensi, prioritas, status, user_input, tgl_input)
        VALUES (?, ?, 'Corrective', CURDATE(), ?, ?, 'Sekali Saja', ?, 'Menunggu', ?, NOW())");
    $antre = $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_helpdesk_outbox
        (kunci_idempoten, jenis, kode_aset, ref_tabel, ref_id, muatan, status, tgl_dibuat)
        VALUES (?, 'Kerusakan', ?, 'rsns_custom_logistik_non_medis_aset_pemeliharaan', ?, ?, 'Antri', NOW())");

    $nomor = '';
    for ($percobaan = 1; $percobaan <= 5; $percobaan++) {
        $calon = nomor_berikutnya($pdo, 'API-'.date('Ym').'-');
        try {
            $pdo->beginTransaction();
            $simpan->execute([
                $calon, $kode_aset, $keluhan, $catatan, $prioritas,
                mb_substr('API: '.$pelapor, 0, 50),
            ]);

            // Bawaannya TIDAK diantrekan: pemanggil API umumnya sistem helpdesk
            // itu sendiri, dan mengantre berarti mengirim balik laporan yang
            // sudah ada di sana. Beri teruskan_ke_helpdesk=true bila memang perlu.
            if ($teruskan) {
                $muatan = [
                    'kunci' => $calon,
                    'jenis' => 'Kerusakan',
                    'waktu_lapor' => $pdo->query('SELECT NOW()')->fetchColumn(),
                    'pelapor' => $pelapor,
                    'judul' => $keluhan,
                    'keterangan' => $deskripsi,
                    'prioritas' => $prioritas,
                    'aset' => [
                        'kode_aset' => $aset['kode_aset'],
                        'nomor_inventaris' => $aset['nomor_inventaris'],
                        'nama_aset' => $aset['nama_aset'],
                        'kode_unit' => $aset['kode_unit'],
                        'nama_unit' => $aset['nama_unit'],
                        'lokasi_fisik' => $aset['lokasi_fisik'],
                        'kondisi_tercatat' => $aset['status_kondisi'],
                        'tautan_qr' => basis_url().'/aset-info.php?kode='.rawurlencode($aset['kode_aset']),
                    ],
                ];
                $antre->execute([
                    $calon, $kode_aset, (int)$pdo->lastInsertId(),
                    json_encode($muatan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $pdo->commit();
            $nomor = $calon;
            break;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($ex->getCode() !== '23000' || $percobaan === 5) {
                throw $ex;
            }
        }
    }
    if ($nomor === '') {
        balas(['error' => 'Laporan gagal disimpan.'], 500);
    }

    balas([
        'status' => 'tersimpan',
        'nomor' => $nomor,
        'diantrekan' => $teruskan,
        'data' => satu_laporan($pdo, $kolom, $gabung, $nomor),
    ], 201);
}

// ---------------------------------------------------------------------------
// PATCH: perbarui status laporan, biasanya dipanggil helpdesk saat tiket
// berpindah status atau ditutup.
// ---------------------------------------------------------------------------
if ($metode === 'PATCH') {
    $data = masukan();
    $nomor = isian($data, 'nomor') ?: trim($_GET['nomor'] ?? '');
    $status = isian($data, 'status');
    $tiket = isian($data, 'tiket_helpdesk');
    $teknisi = isian($data, 'teknisi');
    $tindakan = isian($data, 'tindakan');
    $kondisi = isian($data, 'kondisi_akhir');

    $salah = [];
    if ($nomor === '') {
        $salah['nomor'] = 'Wajib diisi.';
    }
    if (!in_array($status, STATUS_SAH, true)) {
        $salah['status'] = 'Pilih: '.implode(', ', STATUS_SAH).'.';
    }
    if ($status === 'Selesai' && $tindakan === '') {
        $salah['tindakan'] = 'Wajib diisi bila status Selesai.';
    }
    if ($kondisi !== '' && !in_array($kondisi, KONDISI_SAH, true)) {
        $salah['kondisi_akhir'] = 'Pilih: '.implode(', ', KONDISI_SAH).'.';
    }
    if (mb_strlen($teknisi) > 150 || mb_strlen($tindakan) > 2000 || mb_strlen($tiket) > 100) {
        $salah['panjang'] = 'Teknisi maks 150, tindakan maks 2000, tiket maks 100 karakter.';
    }
    if ($salah) {
        balas(['error' => 'Isian tidak lengkap atau tidak sah.', 'rincian' => $salah], 422);
    }

    $stmt = $pdo->prepare("SELECT id, kode_aset, status FROM rsns_custom_logistik_non_medis_aset_pemeliharaan
        WHERE kode_pemeliharaan = ? LIMIT 1");
    $stmt->execute([$nomor]);
    $lama = $stmt->fetch();
    if (!$lama) {
        balas(['error' => 'Laporan tidak ditemukan.'], 404);
    }

    try {
        $pdo->beginTransaction();

        if ($status === 'Selesai') {
            $ubah = $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset_pemeliharaan
                SET status = 'Selesai', tanggal_pelaksanaan = NOW(),
                    nama_teknisi = COALESCE(NULLIF(?, ''), nama_teknisi),
                    tindakan_perbaikan = ?,
                    status_kondisi_akhir = NULLIF(?, '')
                WHERE id = ?");
            $ubah->execute([$teknisi, $tindakan, $kondisi, $lama['id']]);

            // Mengikuti penyelesaian WO di modul admin: kondisi aset ikut
            // diperbarui bila pengerjanya menyebutkan kondisi akhir.
            if ($kondisi !== '') {
                $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset
                    SET status_kondisi = ? WHERE kode_aset = ?")
                    ->execute([$kondisi, $lama['kode_aset']]);
            }
        } else {
            $ubah = $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_aset_pemeliharaan
                SET status = ?,
                    nama_teknisi = COALESCE(NULLIF(?, ''), nama_teknisi),
                    tindakan_perbaikan = COALESCE(NULLIF(?, ''), tindakan_perbaikan)
                WHERE id = ?");
            $ubah->execute([$status, $teknisi, $tindakan, $lama['id']]);
        }

        // Baris antrean menyimpan nomor tiket dan status terakhir yang diketahui.
        // Helpdesk tetap pemilik siklus hidup tiketnya; di sini cuma salinan.
        $sinkron = $pdo->prepare("UPDATE rsns_custom_logistik_non_medis_helpdesk_outbox
            SET tiket_helpdesk = COALESCE(NULLIF(?, ''), tiket_helpdesk),
                status_helpdesk = ?, tgl_sinkron = NOW()
            WHERE kunci_idempoten = ?");
        $sinkron->execute([$tiket, $status, $nomor]);

        // Laporan yang lahir di helpdesk tidak punya baris antrean, karena
        // memang tidak perlu dikirim ke mana pun. Barisnya dibuat sekarang
        // berstatus 'Batal' — bukan untuk dikirim, melainkan sebagai tempat
        // menautkan nomor tiket helpdesk dengan nomor laporan di sini.
        if ($sinkron->rowCount() === 0) {
            $pdo->prepare("INSERT INTO rsns_custom_logistik_non_medis_helpdesk_outbox
                (kunci_idempoten, jenis, kode_aset, ref_tabel, ref_id, muatan, status,
                 tiket_helpdesk, status_helpdesk, pesan_error, tgl_dibuat, tgl_sinkron)
                VALUES (?, 'Kerusakan', ?, 'rsns_custom_logistik_non_medis_aset_pemeliharaan', ?,
                        JSON_OBJECT('kunci', ?, 'asal', 'helpdesk'), 'Batal', NULLIF(?, ''), ?,
                        'Laporan dibuat langsung di helpdesk; baris ini hanya penaut nomor tiket.',
                        NOW(), NOW())")
                ->execute([$nomor, $lama['kode_aset'], $lama['id'], $nomor, $tiket, $status]);
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        balas(['error' => 'Status gagal diperbarui.'], 500);
    }

    balas([
        'status' => 'diperbarui',
        'nomor' => $nomor,
        'status_sebelumnya' => $lama['status'],
        'data' => satu_laporan($pdo, $kolom, $gabung, $nomor),
    ]);
}

if ($metode !== 'GET') {
    balas(['error' => 'Metode tidak didukung. Gunakan GET, POST, atau PATCH.'], 405);
}

// ---------------------------------------------------------------------------
// GET satu laporan.
// ---------------------------------------------------------------------------
if (($nomor = trim($_GET['nomor'] ?? '')) !== '') {
    $baris = satu_laporan($pdo, $kolom, $gabung, $nomor);
    if (!$baris) {
        balas(['error' => 'Laporan tidak ditemukan.'], 404);
    }
    balas(['data' => $baris]);
}

// ---------------------------------------------------------------------------
// GET daftar.
// ---------------------------------------------------------------------------
$syarat = [$hanya_laporan];
$isi = [];

if (($status = trim($_GET['status'] ?? '')) !== '') {
    if (!in_array($status, STATUS_SAH, true)) {
        balas(['error' => 'Status tidak dikenal. Pilih: '.implode(', ', STATUS_SAH).'.'], 400);
    }
    $syarat[] = 'p.status = ?';
    $isi[] = $status;
}
if (($kode_aset = trim($_GET['kode_aset'] ?? '')) !== '') {
    $syarat[] = 'p.kode_aset = ?';
    $isi[] = $kode_aset;
}
if (($unit = trim($_GET['unit'] ?? '')) !== '') {
    $syarat[] = 'a.kode_unit = ?';
    $isi[] = $unit;
}
if (($prioritas = trim($_GET['prioritas'] ?? '')) !== '') {
    if (!in_array($prioritas, PRIORITAS_SAH, true)) {
        balas(['error' => 'Prioritas tidak dikenal. Pilih: '.implode(', ', PRIORITAS_SAH).'.'], 400);
    }
    $syarat[] = 'p.prioritas = ?';
    $isi[] = $prioritas;
}
if (($sejak = trim($_GET['sejak'] ?? '')) !== '') {
    $sah = DateTime::createFromFormat('Y-m-d', $sejak);
    if (!$sah || $sah->format('Y-m-d') !== $sejak) {
        balas(['error' => 'Parameter sejak harus YYYY-MM-DD.'], 400);
    }
    $syarat[] = 'p.tgl_input >= ?';
    $isi[] = $sejak.' 00:00:00';
}

$where = 'WHERE '.implode(' AND ', $syarat);
list($limit, $offset) = batas_halaman();

$hitung = $pdo->prepare("SELECT COUNT(*) {$gabung} {$where}");
$hitung->execute($isi);
$total = (int)$hitung->fetchColumn();

$stmt = $pdo->prepare("SELECT {$kolom} {$gabung} {$where}
    ORDER BY p.tgl_input DESC, p.id DESC LIMIT {$limit} OFFSET {$offset}");
$stmt->execute($isi);
$baris = $stmt->fetchAll();

foreach ($baris as &$satu) {
    $satu['tautan_qr'] = basis_url().'/aset-info.php?kode='.rawurlencode($satu['kode_aset']);
}

balas([
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'berikutnya' => tautan_berikutnya('api-lapor.php', $limit, $offset, $total),
    'data' => $baris,
]);
