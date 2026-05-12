<?php
// ============================================================
//  STUDEX — Student Index
//  modules/dashboard/dashboard_data.php — AJAX Data Endpoint
//  Menyediakan data JSON untuk chart & widget dashboard
//  GET param: type = stats | attendance | binjas | calendar
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Router.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$db     = db();
$type   = sanitize(get('type', 'stats'));
$period = sanitize(get('period', 'month')); // month | week | year
$angkatanId = sanitizeInt(get('angkatan_id', 0));

// ============================================================
// HELPER — Angkatan filter
// ============================================================
$angkatanWhere = $angkatanId > 0 ? " AND angkatan_id = $angkatanId " : '';

try {
    switch ($type) {

        // ============================================================
        // STATS — Statistik ringkasan untuk stat cards
        // ============================================================
        case 'stats':
            $data = [
                'siswa_aktif'    => (int)$db->query("SELECT COUNT(*) FROM siswa WHERE status='aktif'")->fetchColumn(),
                'total_angkatan' => (int)$db->query("SELECT COUNT(*) FROM angkatan WHERE is_aktif=1")->fetchColumn(),
                'rabuan_bulan'   => (int)$db->query("SELECT COUNT(*) FROM rabuan WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn(),
                'mentoring_bulan'=> (int)$db->query("SELECT COUNT(*) FROM mentoring_sesi WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn(),
                'ops_aktif'      => (int)$db->query("SELECT COUNT(*) FROM operasional WHERE status IN('draft','aktif')")->fetchColumn(),
                'binjas_bulan'   => (int)$db->query("SELECT COUNT(*) FROM binjas_sesi WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn(),
            ];
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ============================================================
        // ATTENDANCE — Data kehadiran per modul untuk chart
        // ============================================================
        case 'attendance':
            $modul  = sanitize(get('modul', 'rabuan'));
            $limit  = min(sanitizeInt(get('limit', 8)), 20);

            // Map modul ke tabel & kolom
            $modulMap = [
                'rabuan'    => ['table' => 'rabuan',        'title' => 'judul',        'date' => 'tanggal'],
                'mentoring' => ['table' => 'mentoring_sesi','title' => 'judul_materi', 'date' => 'tanggal'],
                'binjas'    => ['table' => 'binjas_sesi',   'title' => 'nama_sesi',    'date' => 'tanggal'],
            ];

            if (!isset($modulMap[$modul])) {
                echo json_encode(['success' => false, 'message' => 'Modul tidak valid']);
                break;
            }

            $m    = $modulMap[$modul];
            $tbl  = $m['table'];
            $col  = $m['title'];
            $date = $m['date'];

            $rows = $db->query("
                SELECT t.id,
                       t.{$col} as label,
                       t.{$date} as tanggal,
                       COALESCE(SUM(CASE WHEN p.status='hadir' THEN 1 END), 0) as hadir,
                       COALESCE(SUM(CASE WHEN p.status='izin'  THEN 1 END), 0) as izin,
                       COALESCE(SUM(CASE WHEN p.status='sakit' THEN 1 END), 0) as sakit,
                       COALESCE(SUM(CASE WHEN p.status='alpha' THEN 1 END), 0) as alpha
                FROM {$tbl} t
                LEFT JOIN presensi p ON p.modul = '{$modul}' AND p.referensi_id = t.id
                WHERE t.status = 'selesai' {$angkatanWhere}
                GROUP BY t.id
                ORDER BY t.{$date} DESC
                LIMIT {$limit}
            ")->fetchAll();

            $rows = array_reverse($rows);

            echo json_encode([
                'success' => true,
                'data'    => [
                    'labels' => array_map(fn($r) => date('d M', strtotime($r['tanggal'])), $rows),
                    'hadir'  => array_map(fn($r) => (int)$r['hadir'],  $rows),
                    'izin'   => array_map(fn($r) => (int)$r['izin'],   $rows),
                    'sakit'  => array_map(fn($r) => (int)$r['sakit'],  $rows),
                    'alpha'  => array_map(fn($r) => (int)$r['alpha'],  $rows),
                ],
            ]);
            break;

        // ============================================================
        // BINJAS — Radar chart data per siswa
        // ============================================================
        case 'binjas':
            $siswaId = sanitizeInt(get('siswa_id', 0));
            $sesiId  = sanitizeInt(get('sesi_id', 0));

            if (!$siswaId) {
                echo json_encode(['success' => false, 'message' => 'siswa_id wajib diisi']);
                break;
            }

            // Ambil item binjas aktif
            $items = $db->query("SELECT id, nama_item, satuan FROM binjas_item WHERE is_aktif=1 ORDER BY urutan")->fetchAll();

            if (empty($items)) {
                echo json_encode(['success' => false, 'message' => 'Tidak ada item Binjas']);
                break;
            }

            $itemIds = array_column($items, 'id');

            // Ambil skor siswa (sesi terbaru kalau tidak dispesifikkan)
            if ($sesiId) {
                $skorQuery = $db->prepare("
                    SELECT item_id, nilai FROM binjas_skor
                    WHERE siswa_id = ? AND sesi_id = ?
                ");
                $skorQuery->execute([$siswaId, $sesiId]);
            } else {
                $skorQuery = $db->prepare("
                    SELECT bs.item_id, bs.nilai
                    FROM binjas_skor bs
                    INNER JOIN binjas_sesi s ON s.id = bs.sesi_id
                    WHERE bs.siswa_id = ?
                    ORDER BY s.tanggal DESC
                    LIMIT ?
                ");
                $skorQuery->execute([$siswaId, count($itemIds)]);
            }

            $skorRaw  = $skorQuery->fetchAll();
            $skorMap  = array_column($skorRaw, 'nilai', 'item_id');

            // Ambil standarisasi
            $stdQuery = $db->prepare("
                SELECT item_id, nilai_standar FROM binjas_standarisasi
                WHERE (angkatan_id IS NULL OR angkatan_id = (
                    SELECT angkatan_id FROM siswa WHERE id = ?
                ))
                AND (berlaku_sampai IS NULL OR berlaku_sampai >= CURDATE())
                ORDER BY angkatan_id DESC
            ");
            $stdQuery->execute([$siswaId]);
            $stdRaw = $stdQuery->fetchAll();
            $stdMap = array_column($stdRaw, 'nilai_standar', 'item_id');

            $labels   = [];
            $nilaiSiswa  = [];
            $nilaiStandar = [];

            foreach ($items as $item) {
                $labels[]       = $item['nama_item'];
                $nilaiSiswa[]   = isset($skorMap[$item['id']]) ? (float)$skorMap[$item['id']] : 0;
                $nilaiStandar[] = isset($stdMap[$item['id']])  ? (float)$stdMap[$item['id']]  : 0;
            }

            // Hitung rata-rata & persentase
            $avg = count($nilaiSiswa) > 0 ? array_sum($nilaiSiswa) / count($nilaiSiswa) : 0;
            $avgStd = count($nilaiStandar) > 0 ? array_sum($nilaiStandar) / count($nilaiStandar) : 0;
            $pct = $avgStd > 0 ? round(($avg / $avgStd) * 100) : 0;

            echo json_encode([
                'success' => true,
                'data'    => [
                    'labels'       => $labels,
                    'nilai_siswa'  => $nilaiSiswa,
                    'nilai_standar'=> $nilaiStandar,
                    'rata_rata'    => round($avg, 1),
                    'persen'       => $pct,
                ],
            ]);
            break;

        // ============================================================
        // MONTHLY TREND — Tren kegiatan per bulan (line chart)
        // ============================================================
        case 'monthly_trend':
            $year  = sanitizeInt(get('year', date('Y')));
            $rows  = $db->prepare("
                SELECT
                    MONTH(tanggal) as bulan,
                    COUNT(*) as total
                FROM rabuan
                WHERE YEAR(tanggal) = ?
                GROUP BY MONTH(tanggal)
                ORDER BY bulan
            ");
            $rows->execute([$year]);
            $rabuanMonthly = $rows->fetchAll(\PDO::FETCH_KEY_PAIR);

            $rows2 = $db->prepare("
                SELECT MONTH(tanggal) as bulan, COUNT(*) as total
                FROM mentoring_sesi WHERE YEAR(tanggal) = ?
                GROUP BY MONTH(tanggal)
            ");
            $rows2->execute([$year]);
            $mentoringMonthly = $rows2->fetchAll(\PDO::FETCH_KEY_PAIR);

            $rows3 = $db->prepare("
                SELECT MONTH(tanggal) as bulan, COUNT(*) as total
                FROM binjas_sesi WHERE YEAR(tanggal) = ?
                GROUP BY MONTH(tanggal)
            ");
            $rows3->execute([$year]);
            $binjasMonthly = $rows3->fetchAll(\PDO::FETCH_KEY_PAIR);

            $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
            $rabuan = $mentoring = $binjas = [];

            for ($i = 1; $i <= 12; $i++) {
                $rabuan[]    = (int)($rabuanMonthly[$i]   ?? 0);
                $mentoring[] = (int)($mentoringMonthly[$i] ?? 0);
                $binjas[]    = (int)($binjasMonthly[$i]    ?? 0);
            }

            echo json_encode([
                'success' => true,
                'data'    => [
                    'labels'    => $months,
                    'rabuan'    => $rabuan,
                    'mentoring' => $mentoring,
                    'binjas'    => $binjas,
                ],
            ]);
            break;

        // ============================================================
        // PRESENSI SUMMARY — Donut chart per modul bulan ini
        // ============================================================
        case 'presensi_summary':
            $modul = sanitize(get('modul', 'rabuan'));
            $rows  = $db->prepare("
                SELECT status, COUNT(*) as total
                FROM presensi
                WHERE modul = ?
                  AND MONTH(dicatat_pada) = MONTH(CURDATE())
                  AND YEAR(dicatat_pada)  = YEAR(CURDATE())
                GROUP BY status
            ");
            $rows->execute([$modul]);
            $raw    = $rows->fetchAll(\PDO::FETCH_KEY_PAIR);
            $hadir  = (int)($raw['hadir'] ?? 0);
            $izin   = (int)($raw['izin']  ?? 0);
            $sakit  = (int)($raw['sakit'] ?? 0);
            $alpha  = (int)($raw['alpha'] ?? 0);
            $total  = $hadir + $izin + $sakit + $alpha;

            echo json_encode([
                'success' => true,
                'data'    => [
                    'labels' => ['Hadir', 'Izin', 'Sakit', 'Alpha'],
                    'values' => [$hadir, $izin, $sakit, $alpha],
                    'total'  => $total,
                    'pct_hadir' => $total > 0 ? round(($hadir / $total) * 100) : 0,
                ],
            ]);
            break;

        // ============================================================
        // DEFAULT
        // ============================================================
        default:
            echo json_encode(['success' => false, 'message' => 'Type tidak dikenali: ' . $type]);
            break;
    }

} catch (\Exception $e) {
    error_log('STUDEX dashboard_data error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
}