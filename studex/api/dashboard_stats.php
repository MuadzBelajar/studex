<?php
define('STUDEX', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/google_drive.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';

requireLogin();
header('Content-Type: application/json');

$db         = db();
$angkatanId = sanitizeInt($_GET['angkatan_id'] ?? 0);
$periode    = sanitize($_GET['periode'] ?? 'bulan_ini'); // bulan_ini | tahun_ini | semua

// ── Rentang waktu ──────────────────────────────────────────────
switch ($periode) {
    case 'tahun_ini':
        $dateStart = date('Y-01-01');
        $dateEnd   = date('Y-12-31');
        break;
    case 'semua':
        $dateStart = '2000-01-01';
        $dateEnd   = '2099-12-31';
        break;
    default: // bulan_ini
        $dateStart = date('Y-m-01');
        $dateEnd   = date('Y-m-t');
        break;
}

// ── Helper: kondisi angkatan ───────────────────────────────────
$angkatanCond  = $angkatanId ? " AND angkatan_id = {$angkatanId}" : '';

// ══════════════════════════════════════════════════════════════
// 1. RINGKASAN UTAMA
// ══════════════════════════════════════════════════════════════

// Total siswa aktif
$q = $db->prepare("SELECT COUNT(*) FROM siswa WHERE status='aktif'" . ($angkatanId ? " AND angkatan_id=?" : ""));
$q->execute($angkatanId ? [$angkatanId] : []);
$totalSiswa = (int) $q->fetchColumn();

// Total angkatan
$totalAngkatan = (int) $db->query("SELECT COUNT(*) FROM angkatan")->fetchColumn();

// Total kegiatan bulan ini (gabungan semua modul)
$kegiatanSql = "SELECT
    (SELECT COUNT(*) FROM rabuan       WHERE tanggal        BETWEEN ? AND ? {$angkatanCond}) +
    (SELECT COUNT(*) FROM mentoring_sesi WHERE tanggal     BETWEEN ? AND ? {$angkatanCond}) +
    (SELECT COUNT(*) FROM binjas_sesi    WHERE tanggal     BETWEEN ? AND ? {$angkatanCond}) +
    (SELECT COUNT(*) FROM operasional    WHERE tanggal_mulai BETWEEN ? AND ?)
    AS total";
$q = $db->prepare($kegiatanSql);
$q->execute([$dateStart,$dateEnd, $dateStart,$dateEnd, $dateStart,$dateEnd, $dateStart,$dateEnd]);
$totalKegiatan = (int) $q->fetchColumn();

// Rata-rata kehadiran periode ini (semua modul)
$presensiSql = "SELECT
    COUNT(*)                                             AS total,
    SUM(CASE WHEN status='hadir' THEN 1 ELSE 0 END)    AS hadir
FROM presensi p
JOIN siswa s ON s.id = p.siswa_id
WHERE p.created_at BETWEEN ? AND ?
" . ($angkatanId ? " AND s.angkatan_id = {$angkatanId}" : "");
$q = $db->prepare($presensiSql);
$q->execute([$dateStart . ' 00:00:00', $dateEnd . ' 23:59:59']);
$presensiRow  = $q->fetch();
$rataKehadiran = $presensiRow['total'] > 0
    ? round($presensiRow['hadir'] / $presensiRow['total'] * 100, 1)
    : 0;

// ══════════════════════════════════════════════════════════════
// 2. TREND KEGIATAN PER BULAN (12 bulan terakhir)
// ══════════════════════════════════════════════════════════════
$trendMonths  = [];
$trendRabuan  = [];
$trendMentor  = [];
$trendBinjas  = [];

for ($i = 11; $i >= 0; $i--) {
    $mStart = date('Y-m-01', strtotime("-{$i} months"));
    $mEnd   = date('Y-m-t',  strtotime("-{$i} months"));
    $mLabel = date('M Y',    strtotime($mStart));

    $trendMonths[] = $mLabel;

    $q = $db->prepare("SELECT COUNT(*) FROM rabuan WHERE tanggal BETWEEN ? AND ? {$angkatanCond}");
    $q->execute([$mStart, $mEnd]);
    $trendRabuan[] = (int) $q->fetchColumn();

    $q = $db->prepare("SELECT COUNT(*) FROM mentoring_sesi WHERE tanggal BETWEEN ? AND ? {$angkatanCond}");
    $q->execute([$mStart, $mEnd]);
    $trendMentor[] = (int) $q->fetchColumn();

    $q = $db->prepare("SELECT COUNT(*) FROM binjas_sesi WHERE tanggal BETWEEN ? AND ? {$angkatanCond}");
    $q->execute([$mStart, $mEnd]);
    $trendBinjas[] = (int) $q->fetchColumn();
}

// ══════════════════════════════════════════════════════════════
// 3. DISTRIBUSI KEHADIRAN PER MODUL (periode dipilih)
// ══════════════════════════════════════════════════════════════
$modulStats = [];
foreach (['rabuan','mentoring','binjas'] as $modul) {
    $q = $db->prepare(
        "SELECT
            COUNT(*)                                          AS total,
            SUM(CASE WHEN p.status='hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN p.status='izin'  THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN p.status='sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN p.status='alpha' THEN 1 ELSE 0 END) AS alpha
         FROM presensi p
         JOIN siswa s ON s.id = p.siswa_id
         WHERE p.modul = ?
           AND p.created_at BETWEEN ? AND ?
         " . ($angkatanId ? " AND s.angkatan_id = {$angkatanId}" : "")
    );
    $q->execute([$modul, $dateStart . ' 00:00:00', $dateEnd . ' 23:59:59']);
    $row = $q->fetch();

    $modulStats[$modul] = [
        'total'  => (int)($row['total']  ?? 0),
        'hadir'  => (int)($row['hadir']  ?? 0),
        'izin'   => (int)($row['izin']   ?? 0),
        'sakit'  => (int)($row['sakit']  ?? 0),
        'alpha'  => (int)($row['alpha']  ?? 0),
        'pct'    => $row['total'] > 0
                    ? round($row['hadir'] / $row['total'] * 100, 1)
                    : 0,
    ];
}

// ══════════════════════════════════════════════════════════════
// 4. SISWA DENGAN KEHADIRAN TERENDAH (top 5 alpha terbanyak)
// ══════════════════════════════════════════════════════════════
$lowSql = "SELECT s.id, s.nama, s.nim, a.nama_angkatan,
                  COUNT(p.id)                                         AS total_presensi,
                  SUM(CASE WHEN p.status='alpha' THEN 1 ELSE 0 END)  AS total_alpha,
                  ROUND(
                      SUM(CASE WHEN p.status='hadir' THEN 1 ELSE 0 END)
                      / NULLIF(COUNT(p.id),0) * 100
                  , 1)                                                AS pct_hadir
           FROM siswa s
           LEFT JOIN angkatan a    ON a.id = s.angkatan_id
           LEFT JOIN presensi p    ON p.siswa_id = s.id
                                  AND p.created_at BETWEEN ? AND ?
           WHERE s.status = 'aktif'
           " . ($angkatanId ? " AND s.angkatan_id = {$angkatanId}" : "") . "
           GROUP BY s.id
           HAVING total_presensi > 0
           ORDER BY pct_hadir ASC, total_alpha DESC
           LIMIT 5";
$q = $db->prepare($lowSql);
$q->execute([$dateStart . ' 00:00:00', $dateEnd . ' 23:59:59']);
$lowAttendance = $q->fetchAll();

// ══════════════════════════════════════════════════════════════
// 5. KEGIATAN MENDATANG (7 hari ke depan)
// ══════════════════════════════════════════════════════════════
$upcomingStart = date('Y-m-d');
$upcomingEnd   = date('Y-m-d', strtotime('+7 days'));
$upcoming      = [];

// Rabuan
$q = $db->prepare("SELECT 'rabuan' AS modul, id, judul AS nama, tanggal, lokasi
                   FROM rabuan WHERE tanggal BETWEEN ? AND ? {$angkatanCond} ORDER BY tanggal ASC LIMIT 3");
$q->execute([$upcomingStart, $upcomingEnd]);
foreach ($q->fetchAll() as $r) { $upcoming[] = $r; }

// Mentoring
$q = $db->prepare("SELECT 'mentoring' AS modul, id, judul AS nama, tanggal, lokasi
                   FROM mentoring_sesi WHERE tanggal BETWEEN ? AND ? {$angkatanCond} ORDER BY tanggal ASC LIMIT 3");
$q->execute([$upcomingStart, $upcomingEnd]);
foreach ($q->fetchAll() as $r) { $upcoming[] = $r; }

// Binjas
$q = $db->prepare("SELECT 'binjas' AS modul, id, nama_sesi AS nama, tanggal, lokasi
                   FROM binjas_sesi WHERE tanggal BETWEEN ? AND ? {$angkatanCond} ORDER BY tanggal ASC LIMIT 3");
$q->execute([$upcomingStart, $upcomingEnd]);
foreach ($q->fetchAll() as $r) { $upcoming[] = $r; }

// Sort by tanggal
usort($upcoming, fn($a, $b) => strcmp($a['tanggal'], $b['tanggal']));
$upcoming = array_slice($upcoming, 0, 5);

// ══════════════════════════════════════════════════════════════
// OUTPUT
// ══════════════════════════════════════════════════════════════
echo json_encode([
    'success' => true,
    'periode' => $periode,
    'filter'  => [
        'angkatan_id' => $angkatanId,
        'date_start'  => $dateStart,
        'date_end'    => $dateEnd,
    ],
    'summary' => [
        'total_siswa'     => $totalSiswa,
        'total_angkatan'  => $totalAngkatan,
        'total_kegiatan'  => $totalKegiatan,
        'rata_kehadiran'  => $rataKehadiran,
    ],
    'trend' => [
        'labels'   => $trendMonths,
        'rabuan'   => $trendRabuan,
        'mentoring'=> $trendMentor,
        'binjas'   => $trendBinjas,
    ],
    'modul_stats'     => $modulStats,
    'low_attendance'  => $lowAttendance,
    'upcoming'        => $upcoming,
]);