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

$db     = db();
$action = sanitize($_GET['action'] ?? 'list');

// ══════════════════════════════════════════════════════════════
// ACTION: list — ambil notifikasi aktif
// ══════════════════════════════════════════════════════════════
if ($action === 'list') {
    $notifs = [];

    $today     = date('Y-m-d');
    $tomorrow  = date('Y-m-d', strtotime('+1 day'));
    $nextWeek  = date('Y-m-d', strtotime('+7 days'));
    $userId    = $_SESSION['user_id'];

    // ── 1. Kegiatan hari ini ───────────────────────────────────
    // Rabuan hari ini
    $q = $db->query("SELECT id, judul, tanggal, waktu_mulai
                     FROM rabuan WHERE tanggal = '{$today}'
                     ORDER BY waktu_mulai ASC LIMIT 5");
    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'today_rabuan_' . $r['id'],
            'type'    => 'today',
            'icon'    => '📋',
            'color'   => '#395917',
            'title'   => 'Rabuan Hari Ini',
            'message' => $r['judul'] . ($r['waktu_mulai'] ? ' pukul ' . substr($r['waktu_mulai'], 0, 5) : ''),
            'url'     => url('modules/rabuan/detail.php') . '?id=' . $r['id'],
            'time'    => 'Hari ini',
        ];
    }

    // Mentoring hari ini
    $q = $db->query("SELECT id, judul, tanggal, waktu_mulai
                     FROM mentoring_sesi WHERE tanggal = '{$today}'
                     ORDER BY waktu_mulai ASC LIMIT 5");
    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'today_mentoring_' . $r['id'],
            'type'    => 'today',
            'icon'    => '📚',
            'color'   => '#4C8C6A',
            'title'   => 'Mentoring Hari Ini',
            'message' => $r['judul'] . ($r['waktu_mulai'] ? ' pukul ' . substr($r['waktu_mulai'], 0, 5) : ''),
            'url'     => url('modules/mentoring/detail.php') . '?id=' . $r['id'],
            'time'    => 'Hari ini',
        ];
    }

    // Binjas hari ini
    $q = $db->query("SELECT id, nama_sesi, tanggal, waktu_mulai
                     FROM binjas_sesi WHERE tanggal = '{$today}'
                     ORDER BY waktu_mulai ASC LIMIT 5");
    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'today_binjas_' . $r['id'],
            'type'    => 'today',
            'icon'    => '💪',
            'color'   => '#C97C10',
            'title'   => 'Binjas Hari Ini',
            'message' => $r['nama_sesi'] . ($r['waktu_mulai'] ? ' pukul ' . substr($r['waktu_mulai'], 0, 5) : ''),
            'url'     => url('modules/binjas/detail.php') . '?id=' . $r['id'],
            'time'    => 'Hari ini',
        ];
    }

    // ── 2. Kegiatan besok ─────────────────────────────────────
    $q = $db->query("SELECT 'rabuan' AS modul, id, judul AS nama, tanggal, waktu_mulai
                     FROM rabuan WHERE tanggal = '{$tomorrow}'
                     UNION ALL
                     SELECT 'mentoring', id, judul, tanggal, waktu_mulai
                     FROM mentoring_sesi WHERE tanggal = '{$tomorrow}'
                     UNION ALL
                     SELECT 'binjas', id, nama_sesi, tanggal, waktu_mulai
                     FROM binjas_sesi WHERE tanggal = '{$tomorrow}'
                     ORDER BY waktu_mulai ASC LIMIT 5");

    $modulIcon  = ['rabuan' => '📋', 'mentoring' => '📚', 'binjas' => '💪'];
    $modulColor = ['rabuan' => '#395917', 'mentoring' => '#4C8C6A', 'binjas' => '#C97C10'];
    $modulLabel = ['rabuan' => 'Rabuan', 'mentoring' => 'Mentoring', 'binjas' => 'Binjas'];
    $modulUrl   = [
        'rabuan'    => url('modules/rabuan/detail.php'),
        'mentoring' => url('modules/mentoring/detail.php'),
        'binjas'    => url('modules/binjas/detail.php'),
    ];

    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'tomorrow_' . $r['modul'] . '_' . $r['id'],
            'type'    => 'tomorrow',
            'icon'    => $modulIcon[$r['modul']]  ?? '📅',
            'color'   => $modulColor[$r['modul']] ?? '#45515C',
            'title'   => ($modulLabel[$r['modul']] ?? '') . ' Besok',
            'message' => $r['nama'] . ($r['waktu_mulai'] ? ' pukul ' . substr($r['waktu_mulai'], 0, 5) : ''),
            'url'     => ($modulUrl[$r['modul']] ?? '#') . '?id=' . $r['id'],
            'time'    => 'Besok',
        ];
    }

    // ── 3. Presensi yang belum diisi (sesi sudah lewat) ───────
    // Cari sesi yang tanggalnya sudah lewat tapi belum ada presensinya sama sekali
    $q = $db->query(
        "SELECT 'rabuan' AS modul, r.id, r.judul AS nama, r.tanggal
         FROM rabuan r
         WHERE r.tanggal < '{$today}'
           AND NOT EXISTS (
               SELECT 1 FROM presensi p WHERE p.modul='rabuan' AND p.referensi_id = r.id
           )
         ORDER BY r.tanggal DESC LIMIT 3"
    );
    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'missing_presensi_rabuan_' . $r['id'],
            'type'    => 'warning',
            'icon'    => '⚠️',
            'color'   => '#C97C10',
            'title'   => 'Presensi Belum Diisi',
            'message' => 'Rabuan "' . mb_strimwidth($r['nama'], 0, 40, '...') . '" — ' . formatTanggal($r['tanggal']),
            'url'     => url('modules/presensi/index.php') . '?modul=rabuan&referensi_id=' . $r['id'],
            'time'    => formatTanggal($r['tanggal']),
        ];
    }

    $q = $db->query(
        "SELECT 'mentoring' AS modul, m.id, m.judul AS nama, m.tanggal
         FROM mentoring_sesi m
         WHERE m.tanggal < '{$today}'
           AND NOT EXISTS (
               SELECT 1 FROM presensi p WHERE p.modul='mentoring' AND p.referensi_id = m.id
           )
         ORDER BY m.tanggal DESC LIMIT 3"
    );
    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'missing_presensi_mentoring_' . $r['id'],
            'type'    => 'warning',
            'icon'    => '⚠️',
            'color'   => '#C97C10',
            'title'   => 'Presensi Belum Diisi',
            'message' => 'Mentoring "' . mb_strimwidth($r['nama'], 0, 40, '...') . '" — ' . formatTanggal($r['tanggal']),
            'url'     => url('modules/presensi/index.php') . '?modul=mentoring&referensi_id=' . $r['id'],
            'time'    => formatTanggal($r['tanggal']),
        ];
    }

    // ── 4. Operasional yang sedang berjalan ───────────────────
    $q = $db->query(
        "SELECT id, nama_kegiatan, tanggal_mulai, tanggal_selesai, fase, status
         FROM operasional
         WHERE tanggal_mulai <= '{$today}'
           AND (tanggal_selesai IS NULL OR tanggal_selesai >= '{$today}')
           AND status = 'aktif'
         ORDER BY tanggal_mulai ASC LIMIT 3"
    );
    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'ops_active_' . $r['id'],
            'type'    => 'info',
            'icon'    => '🗂️',
            'color'   => '#595D75',
            'title'   => 'Operasional Sedang Berjalan',
            'message' => $r['nama_kegiatan'] . ' — Fase ' . ucfirst($r['fase']),
            'url'     => url('modules/operasional/detail.php') . '?id=' . $r['id'],
            'time'    => formatTanggal($r['tanggal_mulai']),
        ];
    }

    // ── 5. Siswa dengan kehadiran di bawah 60% (bulan ini) ───
    $q = $db->query(
        "SELECT s.id, s.nama,
                COUNT(p.id) AS total,
                ROUND(SUM(CASE WHEN p.status='hadir' THEN 1 ELSE 0 END) / COUNT(p.id) * 100, 0) AS pct
         FROM siswa s
         JOIN presensi p ON p.siswa_id = s.id
         WHERE s.status = 'aktif'
           AND p.created_at >= '" . date('Y-m-01') . "'
         GROUP BY s.id
         HAVING pct < 60 AND total >= 3
         ORDER BY pct ASC
         LIMIT 3"
    );
    foreach ($q->fetchAll() as $r) {
        $notifs[] = [
            'id'      => 'low_attendance_' . $r['id'],
            'type'    => 'danger',
            'icon'    => '🔴',
            'color'   => '#8B1408',
            'title'   => 'Kehadiran Rendah',
            'message' => $r['nama'] . ' — kehadiran ' . $r['pct'] . '% bulan ini',
            'url'     => url('modules/siswa/detail.php') . '?id=' . $r['id'],
            'time'    => 'Bulan ini',
        ];
    }

    // ── Sort: today > tomorrow > warning > info > danger ──────
    $typeOrder = ['today' => 0, 'tomorrow' => 1, 'warning' => 2, 'info' => 3, 'danger' => 4];
    usort($notifs, fn($a, $b) =>
        ($typeOrder[$a['type']] ?? 9) <=> ($typeOrder[$b['type']] ?? 9)
    );

    // Batasi maksimal 15 notif
    $notifs = array_slice($notifs, 0, 15);

    echo json_encode([
        'success' => true,
        'count'   => count($notifs),
        'notifs'  => $notifs,
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION: count — hanya jumlah badge (polling ringan)
// ══════════════════════════════════════════════════════════════
if ($action === 'count') {
    $today   = date('Y-m-d');
    $count   = 0;

    // Kegiatan hari ini
    $q = $db->query("SELECT
        (SELECT COUNT(*) FROM rabuan       WHERE tanggal = '{$today}') +
        (SELECT COUNT(*) FROM mentoring_sesi WHERE tanggal = '{$today}') +
        (SELECT COUNT(*) FROM binjas_sesi    WHERE tanggal = '{$today}') AS c");
    $count += (int) $q->fetchColumn();

    // Presensi kosong (sesi lewat, belum ada presensi)
    $q = $db->query(
        "SELECT COUNT(*) FROM (
            SELECT id FROM rabuan WHERE tanggal < '{$today}'
              AND NOT EXISTS (SELECT 1 FROM presensi p WHERE p.modul='rabuan' AND p.referensi_id=rabuan.id)
            LIMIT 5
         ) AS sub"
    );
    $count += (int) $q->fetchColumn();

    echo json_encode(['success' => true, 'count' => min($count, 99)]);
    exit;
}

// Fallback
echo json_encode(['success' => false, 'message' => 'Action tidak dikenal.']);