<?php
/**
 * Seed pegawai + akun login pejabat SK Direktur 063/SK/DIR/VI/2026
 * (Kabid, Kasie, Kepala Unit, Penanggung Jawab).
 *
 * Akun dibuat TANPA akses Logistik (access = 'dashboard'). Akses Logistik
 * diberikan lewat Import Excel di menu Hak Akses (Import_Hak_Akses_SK063_2026.xlsx).
 *
 *   php plugins/logistik_non_medis/tools/seed_pegawai_sk063.php          # buat
 *   php plugins/logistik_non_medis/tools/seed_pegawai_sk063.php --hapus  # hapus hasil seed
 *
 * NIK/username yang sudah ada tidak ditimpa. Password awal: 12345678.
 */
if (PHP_SAPI !== 'cli') {
    exit('Jalankan dari command line.');
}

define('BASE_DIR', dirname(__DIR__, 3));
require BASE_DIR . '/config.php';

const PENANDA = 'SEED SK063/2026';
const PASSWORD_AWAL = '12345678';

// [No SK, NIK, Nama, Jabatan (maks 25 karakter untuk pegawai.jbtn)]
$pejabat = [
    [12, '07271520251998', 'dr. Sadam Ulfin', 'Kabid Pelayanan'],
    [13, '00020119991970', 'dr. Fatima Khiarun Nisa', 'Kabid Umum dan Keuangan'],
    [15, '02450120191996', 'Nanda Ayudya Okta Muria, Amd. Kep', 'Kasie Keperawatan'],
    [16, '07900120252000', 'dr. Dida Oktadivan Putra', 'Kasie Pelayanan Medis'],
    [17, '00070720061981', 'Erva Swesti Restiani, ST', 'Kasie Penunjang Medis'],
    [18, '06841520241998', 'Apt. Jihaan Maila Shofa, S.Farm', 'Kasie Pelayanan Farmasi'],
    [19, '00561520161994', 'Minakhul Fikriah, SKM', 'Kasie Umum dan Admin'],
    [20, '05510120232000', 'Rahmat Nurul Ikhsan, S.Kom', 'Kasie PSDM'],
    [21, '02040620191996', 'Lutfatul Ummah, SE', 'Kasie Keuangan'],
    [26, '04481520221989', 'Siti Fatimah CA, A.Md Kep', 'Ka. Unit Ruang Intensif'],
    [27, '00142520111987', 'Heny Noor Chayati, AMK', 'Ka. Ruang ROI PICU NICU'],
    [28, '05252220231997', 'Ayu Safitri, S.Kep.Ners', 'Ka. Unit RI Arofah'],
    [29, '00660620171992', 'Lisa Kurniawati, Amd. Keb', 'Ka. Unit RI Multazam'],
    [30, '05341520232001', 'Muhimatul Ifadah, A.Md.Kep', 'Ka. Unit RI Marwa'],
    [31, '04722020222000', 'Tiyas Asaroh, A.Md.Kep', 'Ka. Unit RI Madinah 2'],
    [32, '06501120241991', 'Kurnia Wisma Faidatul Khusna, A.Md., Keb', 'Ka. Unit RI Madinah 3'],
    [33, '00690220181990', 'Tutik Nafiarti, A.Md Keb', 'Ka. Unit Ruang Bersalin'],
    [34, '06191320231999', 'Kholis Nazali, A.Md., Kep', 'Ka. Unit Rawat Jalan'],
    [35, '04491520221988', 'Sofi Irmawati, S.Kep Ns', 'Ka. Unit IGD'],
    [36, '00112620101986', 'Muhammad Amin, AMK', 'Ka. Unit Kamar Operasi'],
    [37, '00420720161982', 'Dwi Heru Haryanto, AMK', 'Ka. Unit CSSD dan Laundry'],
    [38, '07561620251998', 'Erna Setiyani, S.Tr., Kes', 'Ka. Unit Laboratorium'],
    [39, '00300420151990', 'Miftahul Huda, Amd Rad', 'Ka. Unit Radiologi'],
    [40, '05720720232002', 'Septiana Putri Permadani, Amd. Farm', 'Ka. Unit Farmasi RJ'],
    [40, '06640120241999', 'Apt. Adha Qudsiya Dewi Lutfiani, S.Farm', 'Ka. Unit Farmasi RI'],
    [41, '03222820201999', 'Frisca Devi Prabandi', 'Ka. Unit Gudang Farmasi'],
    [42, '07110220242001', 'Ardania Putri Rahmawati, A.Md., Kes', 'Ka. Unit Fisioterapi'],
    [43, '02621120191993', 'Mustafiah, A.Md. RMIK', 'Ka. Unit RM Pendaftaran'],
    [44, '05461720232001', 'Fina Zahrotun Ni\'mah, S.Gz', 'Ka. Unit Gizi'],
    [45, '0350220191994', 'Eka Lutfiana, S.Kep', 'Ka. Unit Asuransi'],
    [46, '03850320211998', 'Alni Budiarti, S.E', 'Ka. Unit Kasir Akuntansi'],
    [47, '08041920252002', 'Maulida Nabilatul Muna, S.I.Kom', 'Ka. Unit PKRS'],
    [48, '03881720211995', 'Ivanofiq Adami Aji, S.Kom', 'Ka. Unit IT'],
    [49, '00100920091989', 'Noor Indah', 'PJ Customer Service'],
    [51, '01470120181983', 'Suharyanti, SKM', 'Ka. Unit Logistik Umum'],
    [52, '05390019002000', 'Khumaedi', 'Ka. Unit HK'],
    [53, '08080620261999', 'M. Bagas Auliya, ST', 'Ka. Unit IPSRS'],
    [54, '03830120211995', 'Aimmatur Rochmah, A.Md., Keb', 'PJ Kebidanan'],
    [55, '07312420252002', 'Putri Izzatul Aulia, ST', 'PJ TU'],
    [56, '05730019001985', 'Anwar Zaenufi', 'PJ Security'],
    [57, '00050120041982', 'Masrukin', 'PJ Driver'],
];

$pdo = new PDO('mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$cekUser = $pdo->prepare('SELECT username, description FROM mlite_users WHERE username = ?');
$cekPegawai = $pdo->prepare('SELECT nik FROM pegawai WHERE nik = ?');

if (in_array('--hapus', $argv, true)) {
    $hapusUser = $pdo->prepare('DELETE FROM mlite_users WHERE username = ? AND description = ?');
    $hapusPegawai = $pdo->prepare('DELETE FROM pegawai WHERE nik = ?');
    $nUser = 0;
    $nPegawai = 0;
    foreach ($pejabat as [, $nik]) {
        $cekUser->execute([$nik]);
        $u = $cekUser->fetch();
        if (!$u || $u['description'] !== PENANDA) {
            continue; // bukan akun hasil seed -> jangan disentuh
        }
        $hapusUser->execute([$nik, PENANDA]);
        $nUser += $hapusUser->rowCount();
        $hapusPegawai->execute([$nik]);
        $nPegawai += $hapusPegawai->rowCount();
    }
    echo "Dihapus: {$nUser} akun, {$nPegawai} pegawai (hanya yang berpenanda " . PENANDA . ").\n";
    echo "Catatan: role Logistik yang sudah di-import tidak ikut dihapus; cabut dari menu Hak Akses bila perlu.\n";
    exit(0);
}

$hariIni = date('Y-m-d');
$insPegawai = $pdo->prepare("INSERT INTO pegawai
    (nik, nama, jk, jbtn, jnj_jabatan, kode_kelompok, kode_resiko, kode_emergency, departemen, bidang,
     stts_wp, stts_kerja, npwp, pendidikan, gapok, tmp_lahir, tgl_lahir, alamat, kota, mulai_kerja,
     ms_kerja, indexins, bpd, rekening, stts_aktif, wajibmasuk, pengurang, indek, mulai_kontrak,
     cuti_diambil, dankes, photo, no_ktp)
    VALUES (?, ?, 'Pria', ?, '-', '-', '-', '-', '-', '-',
     '-', '-', '0', '-', 0, '-', ?, '', '', ?,
     '<1', '-', '-', '0', 'AKTIF', 0, 0, 0, ?,
     0, 0, NULL, '0')");
$insUser = $pdo->prepare("INSERT INTO mlite_users (username, fullname, description, password, avatar, email, role, cap, access)
    VALUES (?, ?, ?, ?, '', '', 'user', '', 'dashboard')");

$hash = password_hash(PASSWORD_AWAL, PASSWORD_DEFAULT);
$stat = ['pegawai_baru' => 0, 'pegawai_ada' => 0, 'akun_baru' => 0, 'akun_ada' => 0];

$pdo->beginTransaction();
try {
    foreach ($pejabat as [$no, $nik, $nama, $jabatan]) {
        $cekPegawai->execute([$nik]);
        if ($cekPegawai->fetch()) {
            $stat['pegawai_ada']++;
        } else {
            $insPegawai->execute([$nik, mb_substr($nama, 0, 50), mb_substr($jabatan, 0, 25), $hariIni, $hariIni, $hariIni]);
            $stat['pegawai_baru']++;
        }

        $cekUser->execute([$nik]);
        if ($cekUser->fetch()) {
            $stat['akun_ada']++;
        } else {
            $insUser->execute([$nik, $nama, PENANDA, $hash]);
            $stat['akun_baru']++;
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'GAGAL, tidak ada yang tersimpan: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Pejabat SK 063/2026: " . count($pejabat) . " orang\n";
echo "  pegawai : {$stat['pegawai_baru']} dibuat, {$stat['pegawai_ada']} sudah ada\n";
echo "  akun    : {$stat['akun_baru']} dibuat, {$stat['akun_ada']} sudah ada (password awal " . PASSWORD_AWAL . ", akses 'dashboard')\n";
echo "\nPerlu dicek HR:\n";
echo "  - Kolom jk semua pegawai hasil seed diisi 'Pria' sementara (tidak ada di SK). Koreksi di menu Kepegawaian.\n";
echo "  - NIK 00020119991970 di SK dipakai dr. Siti Khoiriyah (No.11) dan dr. Fatima (No.13); dibuat atas nama dr. Fatima.\n";
echo "  - NIK 0350220191994 (Eka Lutfiana) hanya 13 digit, kemungkinan salah ketik di SK.\n";
echo "\nLangkah berikut: import Import_Hak_Akses_SK063_2026.xlsx di menu Logistik > Hak Akses > Import Excel.\n";
