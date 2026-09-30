<?php
namespace Systems {
    // Unit test tanpa bootstrap aplikasi, sesi, atau koneksi database.
    abstract class AdminModule {}
}
namespace {
require_once dirname(__DIR__) . '/Admin.php';
final class MonthlyWeeks {
    public static function ranges(string $period): array {
        $method = new \ReflectionMethod(\Plugins\Logistik_non_medis\Admin::class, '_monthlyWeekRanges');
        $method->setAccessible(true);
        return $method->invoke(null, $period);
    }
}

if (($argv[1] ?? '') === '--json') {
    $all = [];
    for ($year = 2025; $year <= 2028; $year++) {
        for ($month = 1; $month <= 12; $month++) {
            $period = sprintf('%04d-%02d', $year, $month);
            $all[$period] = array_values(MonthlyWeeks::ranges($period));
        }
    }
    echo json_encode($all);
    exit;
}

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$september = MonthlyWeeks::ranges('2026-09');
check(count($september) === 5, 'September harus memiliki lima minggu');
check($september[5] === ['start'=>'2026-09-27','end'=>'2026-10-03','date'=>'2026-09-27'], 'Minggu terakhir September');
check($september[1]['date'] === '2026-09-01', 'Tanggal impor minggu pertama harus di September');
check(count(MonthlyWeeks::ranges('2026-02')) === 4, 'Februari 2026 empat minggu');
check(count(MonthlyWeeks::ranges('2026-08')) === 6, 'Agustus 2026 enam minggu');
check(MonthlyWeeks::ranges('2028-02')[5]['end'] === '2028-03-04', 'Tahun kabisat');
foreach (['2026-00', '2026-13', '2026-2', '', '2026-09-01'] as $invalid) {
    try { MonthlyWeeks::ranges($invalid); throw new RuntimeException('Periode invalid diterima'); }
    catch (InvalidArgumentException $expected) {}
}
foreach (range(2025, 2028) as $year) {
    foreach (range(1, 12) as $month) {
        $period = sprintf('%04d-%02d', $year, $month);
        $ranges = MonthlyWeeks::ranges($period);
        foreach ($ranges as $number=>$range) {
            check(substr($range['date'],0,7) === $period, 'Tanggal impor keluar bulan');
            check(date('w',strtotime($range['start'])) === '0', 'Minggu harus mulai hari Minggu');
            check(date('w',strtotime($range['end'])) === '6', 'Minggu harus berakhir Sabtu');
        }
        for ($day=1; $day<=(int)date('t',strtotime($period.'-01')); $day++) {
            $date = sprintf('%s-%02d', $period, $day);
            $matches = array_filter($ranges, function($range) use($date) {return $range['start'] <= $date && $range['end'] >= $date;});
            check(count($matches) === 1, 'Tanggal harus tercakup tepat satu minggu');
        }
    }
}
echo "PASS: kalender 48 bulan, lintas bulan, 4/5/6 minggu, dan tahun kabisat\n";
}
