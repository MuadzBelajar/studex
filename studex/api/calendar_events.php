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

// ── Params ─────────────────────────────────────────────────────
$start      = $_GET['start']       ?? date('Y-m-01');
$end        = $_GET['end']         ?? date('Y-m-t');
$angkatanId = sanitizeInt($_GET['angkatan_id'] ?? 0);
$modul      = sanitize($_GET['modul'] ?? '');          // filter opsional per modul
$format     = sanitize($_GET['format'] ?? 'fullcalendar'); // fullcalendar | list

// Sanitasi tanggal
$start = date('Y-m-d', strtotime($start));
$end   = date('Y-m-d', strtotime($end));

// Validasi format
if (!in_array($format, ['fullcalendar', 'list'])) {
    $format = 'fullcalendar';
}

$db     = db();
$events = [];

// Helper: apakah modul ini perlu di-include?
$include = fn(string $m): bool => !$modul || $modul === $m;

// ── 1. RABUAN ─────────────────────────────────────────────────
if ($include('rabuan')) {
    $sql  = "SELECT r.id, r.judul, r.tanggal, r.waktu_mulai, r.waktu_selesai,
                    r.lokasi, r.status, a.nama_angkatan, a.id AS angkatan_id
             FROM rabuan r
             LEFT JOIN angkatan a ON a.id = r.angkatan_id
             WHERE r.tanggal BETWEEN ? AND ?";
    $par  = [$start, $end];
    if ($angkatanId) { $sql .= " AND r.angkatan_id = ?"; $par[] = $angkatanId; }
    $sql .= " ORDER BY r.tanggal ASC, r.waktu_mulai ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($par);

    foreach ($stmt->fetchAll() as $row) {
        $startDt = $row['tanggal'] . ($row['waktu_mulai']  ? 'T' . $row['waktu_mulai']  : '');
        $endDt   = $row['waktu_selesai'] ? $row['tanggal'] . 'T' . $row['waktu_selesai'] : null;

        $events[] = buildEvent(
            id:         'rabuan_' . $row['id'],
            title:      $row['judul'],
            start:      $startDt,
            end:        $endDt,
            color:      '#395917',
            modul:      'Rabuan',
            angkatan:   $row['nama_angkatan'],
            tanggal:    formatTanggal($row['tanggal']),
            lokasi:     $row['lokasi'],
            status:     ucfirst($row['status'] ?? '-'),
            detailUrl:  url('modules/rabuan/detail.php') . '?id=' . $row['id'],
            allDay:     false,
        );
    }
}

// ── 2. MENTORING ──────────────────────────────────────────────
if ($include('mentoring')) {
    $sql  = "SELECT m.id, m.judul, m.tanggal, m.waktu_mulai, m.waktu_selesai,
                    m.lokasi, m.status, a.nama_angkatan
             FROM mentoring_sesi m
             LEFT JOIN angkatan a ON a.id = m.angkatan_id
             WHERE m.tanggal BETWEEN ? AND ?";
    $par  = [$start, $end];
    if ($angkatanId) { $sql .= " AND m.angkatan_id = ?"; $par[] = $angkatanId; }
    $sql .= " ORDER BY m.tanggal ASC, m.waktu_mulai ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($par);

    foreach ($stmt->fetchAll() as $row) {
        $startDt = $row['tanggal'] . ($row['waktu_mulai']  ? 'T' . $row['waktu_mulai']  : '');
        $endDt   = $row['waktu_selesai'] ? $row['tanggal'] . 'T' . $row['waktu_selesai'] : null;

        $events[] = buildEvent(
            id:        'mentoring_' . $row['id'],
            title:     $row['judul'],
            start:     $startDt,
            end:       $endDt,
            color:     '#4C8C6A',
            modul:     'Mentoring',
            angkatan:  $row['nama_angkatan'],
            tanggal:   formatTanggal($row['tanggal']),
            lokasi:    $row['lokasi'],
            status:    ucfirst($row['status'] ?? '-'),
            detailUrl: url('modules/mentoring/detail.php') . '?id=' . $row['id'],
            allDay:    false,
        );
    }
}

// ── 3. OPERASIONAL ────────────────────────────────────────────
if ($include('operasional')) {
    $sql  = "SELECT o.id, o.nama_kegiatan, o.tanggal_mulai, o.tanggal_selesai,
                    o.lokasi, o.status, o.fase
             FROM operasional o
             WHERE (o.tanggal_mulai  BETWEEN ? AND ?)
                OR (o.tanggal_selesai BETWEEN ? AND ?)";
    $par  = [$start, $end, $start, $end];

    $stmt = $db->prepare($sql);
    $stmt->execute($par);

    foreach ($stmt->fetchAll() as $row) {
        // FullCalendar: end all-day bersifat exclusive → tambah 1 hari
        $endDate = $row['tanggal_selesai']
            ? date('Y-m-d', strtotime($row['tanggal_selesai'] . ' +1 day'))
            : null;

        $tanggalLabel = formatTanggal($row['tanggal_mulai']);
        if ($row['tanggal_selesai'] && $row['tanggal_selesai'] !== $row['tanggal_mulai']) {
            $tanggalLabel .= ' s/d ' . formatTanggal($row['tanggal_selesai']);
        }

        $events[] = buildEvent(
            id:        'ops_' . $row['id'],
            title:     $row['nama_kegiatan'],
            start:     $row['tanggal_mulai'],
            end:       $endDate,
            color:     '#595D75',
            modul:     'Operasional',
            angkatan:  null,
            tanggal:   $tanggalLabel,
            lokasi:    $row['lokasi'],
            status:    ucfirst($row['status'] ?? '-'),
            detailUrl: url('modules/operasional/detail.php') . '?id=' . $row['id'],
            allDay:    true,
            extra:     ['fase' => ucfirst($row['fase'] ?? '-')],
        );
    }
}

// ── 4. BINJAS ─────────────────────────────────────────────────
if ($include('binjas')) {
    $sql  = "SELECT b.id, b.nama_sesi, b.tanggal, b.waktu_mulai, b.waktu_selesai,
                    b.lokasi, b.status, a.nama_angkatan
             FROM binjas_sesi b
             LEFT JOIN angkatan a ON a.id = b.angkatan_id
             WHERE b.tanggal BETWEEN ? AND ?";
    $par  = [$start, $end];
    if ($angkatanId) { $sql .= " AND b.angkatan_id = ?"; $par[] = $angkatanId; }
    $sql .= " ORDER BY b.tanggal ASC, b.waktu_mulai ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($par);

    foreach ($stmt->fetchAll() as $row) {
        $startDt = $row['tanggal'] . ($row['waktu_mulai']  ? 'T' . $row['waktu_mulai']  : '');
        $endDt   = $row['waktu_selesai'] ? $row['tanggal'] . 'T' . $row['waktu_selesai'] : null;

        $events[] = buildEvent(
            id:        'binjas_' . $row['id'],
            title:     $row['nama_sesi'],
            start:     $startDt,
            end:       $endDt,
            color:     '#C97C10',
            modul:     'Binjas',
            angkatan:  $row['nama_angkatan'],
            tanggal:   formatTanggal($row['tanggal']),
            lokasi:    $row['lokasi'],
            status:    ucfirst($row['status'] ?? '-'),
            detailUrl: url('modules/binjas/detail.php') . '?id=' . $row['id'],
            allDay:    false,
        );
    }
}

// ── Sort semua event berdasarkan start ────────────────────────
usort($events, fn($a, $b) => strcmp($a['start'], $b['start']));

// ── Output ─────────────────────────────────────────────────────
if ($format === 'list') {
    // Format ringkas untuk widget / notifikasi / dashboard
    $list = array_map(fn($e) => [
        'id'       => $e['id'],
        'title'    => $e['title'],
        'modul'    => $e['extendedProps']['modul'],
        'tanggal'  => $e['extendedProps']['tanggal'],
        'lokasi'   => $e['extendedProps']['lokasi'],
        'status'   => $e['extendedProps']['status'],
        'color'    => $e['backgroundColor'],
        'url'      => $e['extendedProps']['detail_url'],
    ], $events);

    echo json_encode([
        'success' => true,
        'total'   => count($list),
        'events'  => $list,
    ]);
} else {
    // Format FullCalendar standar
    echo json_encode([
        'success' => true,
        'total'   => count($events),
        'events'  => $events,
    ]);
}

// ── Helper builder ─────────────────────────────────────────────
function buildEvent(
    string  $id,
    string  $title,
    string  $start,
    ?string $end,
    string  $color,
    string  $modul,
    ?string $angkatan,
    string  $tanggal,
    ?string $lokasi,
    string  $status,
    string  $detailUrl,
    bool    $allDay  = false,
    array   $extra   = [],
): array {
    $props = [
        'modul'      => $modul,
        'angkatan'   => $angkatan,
        'tanggal'    => $tanggal,
        'lokasi'     => $lokasi,
        'status'     => $status,
        'detail_url' => $detailUrl,
    ];

    // Merge extra props (mis. fase untuk operasional)
    if ($extra) {
        $props = array_merge($props, $extra);
    }

    $event = [
        'id'              => $id,
        'title'           => $title,
        'start'           => $start,
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'extendedProps'   => $props,
    ];

    if ($end)    $event['end']    = $end;
    if ($allDay) $event['allDay'] = true;

    return $event;
}