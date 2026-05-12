<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();
header('Content-Type: application/json');

$start      = $_GET['start']       ?? date('Y-m-01');
$end        = $_GET['end']         ?? date('Y-m-t');
$angkatanId = sanitizeInt($_GET['angkatan_id'] ?? 0);

// Sanitasi tanggal agar aman dipakai di query
$start = date('Y-m-d', strtotime($start));
$end   = date('Y-m-d', strtotime($end));

$db     = db();
$events = [];

// ── 1. RABUAN ─────────────────────────────────────────────────
$sqlR  = "SELECT r.id, r.judul, r.tanggal, r.waktu_mulai, r.waktu_selesai,
                 r.lokasi, r.status, a.nama_angkatan
          FROM rabuan r
          LEFT JOIN angkatan a ON a.id = r.angkatan_id
          WHERE r.tanggal BETWEEN ? AND ?";
$parR  = [$start, $end];
if ($angkatanId) { $sqlR .= " AND r.angkatan_id = ?"; $parR[] = $angkatanId; }
$stmt  = $db->prepare($sqlR);
$stmt->execute($parR);
foreach ($stmt->fetchAll() as $row) {
    $startDt = $row['tanggal'] . ($row['waktu_mulai']   ? 'T' . $row['waktu_mulai']   : '');
    $endDt   = $row['waktu_selesai'] ? $row['tanggal'] . 'T' . $row['waktu_selesai']  : null;
    $events[] = [
        'id'              => 'rabuan_' . $row['id'],
        'title'           => $row['judul'],
        'start'           => $startDt,
        'end'             => $endDt,
        'backgroundColor' => '#395917',
        'borderColor'     => '#395917',
        'extendedProps'   => [
            'modul'      => 'Rabuan',
            'angkatan'   => $row['nama_angkatan'],
            'tanggal'    => formatTanggal($row['tanggal']),
            'lokasi'     => $row['lokasi'],
            'status'     => ucfirst($row['status'] ?? '-'),
            'detail_url' => url('modules/rabuan/detail.php') . '?id=' . $row['id'],
        ],
    ];
}

// ── 2. MENTORING ──────────────────────────────────────────────
$sqlM  = "SELECT m.id, m.judul, m.tanggal, m.waktu_mulai, m.waktu_selesai,
                 m.lokasi, m.status, a.nama_angkatan
          FROM mentoring_sesi m
          LEFT JOIN angkatan a ON a.id = m.angkatan_id
          WHERE m.tanggal BETWEEN ? AND ?";
$parM  = [$start, $end];
if ($angkatanId) { $sqlM .= " AND m.angkatan_id = ?"; $parM[] = $angkatanId; }
$stmt  = $db->prepare($sqlM);
$stmt->execute($parM);
foreach ($stmt->fetchAll() as $row) {
    $startDt = $row['tanggal'] . ($row['waktu_mulai']   ? 'T' . $row['waktu_mulai']   : '');
    $endDt   = $row['waktu_selesai'] ? $row['tanggal'] . 'T' . $row['waktu_selesai']  : null;
    $events[] = [
        'id'              => 'mentoring_' . $row['id'],
        'title'           => $row['judul'],
        'start'           => $startDt,
        'end'             => $endDt,
        'backgroundColor' => '#4C8C6A',
        'borderColor'     => '#4C8C6A',
        'extendedProps'   => [
            'modul'      => 'Mentoring',
            'angkatan'   => $row['nama_angkatan'],
            'tanggal'    => formatTanggal($row['tanggal']),
            'lokasi'     => $row['lokasi'],
            'status'     => ucfirst($row['status'] ?? '-'),
            'detail_url' => url('modules/mentoring/detail.php') . '?id=' . $row['id'],
        ],
    ];
}

// ── 3. OPERASIONAL ────────────────────────────────────────────
// Operasional tidak punya angkatan_id langsung, filter lewat peserta jika perlu
$sqlO  = "SELECT o.id, o.nama_kegiatan, o.tanggal_mulai, o.tanggal_selesai,
                 o.lokasi, o.status, o.fase
          FROM operasional o
          WHERE (o.tanggal_mulai BETWEEN ? AND ?)
             OR (o.tanggal_selesai BETWEEN ? AND ?)";
$parO  = [$start, $end, $start, $end];
$stmt  = $db->prepare($sqlO);
$stmt->execute($parO);
foreach ($stmt->fetchAll() as $row) {
    // FullCalendar end untuk all-day event bersifat exclusive, tambah 1 hari
    $endDate = $row['tanggal_selesai']
        ? date('Y-m-d', strtotime($row['tanggal_selesai'] . ' +1 day'))
        : null;
    $events[] = [
        'id'              => 'ops_' . $row['id'],
        'title'           => $row['nama_kegiatan'],
        'start'           => $row['tanggal_mulai'],
        'end'             => $endDate,
        'allDay'          => true,
        'backgroundColor' => '#595D75',
        'borderColor'     => '#595D75',
        'extendedProps'   => [
            'modul'      => 'Operasional',
            'tanggal'    => formatTanggal($row['tanggal_mulai']) .
                            ($row['tanggal_selesai'] ? ' s/d ' . formatTanggal($row['tanggal_selesai']) : ''),
            'lokasi'     => $row['lokasi'],
            'status'     => ucfirst($row['status'] ?? '-'),
            'deskripsi'  => 'Fase: ' . ucfirst($row['fase'] ?? '-'),
            'detail_url' => url('modules/operasional/detail.php') . '?id=' . $row['id'],
        ],
    ];
}

// ── 4. BINJAS ─────────────────────────────────────────────────
$sqlB  = "SELECT b.id, b.nama_sesi, b.tanggal, b.waktu_mulai, b.waktu_selesai,
                 b.lokasi, b.status, a.nama_angkatan
          FROM binjas_sesi b
          LEFT JOIN angkatan a ON a.id = b.angkatan_id
          WHERE b.tanggal BETWEEN ? AND ?";
$parB  = [$start, $end];
if ($angkatanId) { $sqlB .= " AND b.angkatan_id = ?"; $parB[] = $angkatanId; }
$stmt  = $db->prepare($sqlB);
$stmt->execute($parB);
foreach ($stmt->fetchAll() as $row) {
    $startDt = $row['tanggal'] . ($row['waktu_mulai']  ? 'T' . $row['waktu_mulai']  : '');
    $endDt   = $row['waktu_selesai'] ? $row['tanggal'] . 'T' . $row['waktu_selesai'] : null;
    $events[] = [
        'id'              => 'binjas_' . $row['id'],
        'title'           => $row['nama_sesi'],
        'start'           => $startDt,
        'end'             => $endDt,
        'backgroundColor' => '#C97C10',
        'borderColor'     => '#C97C10',
        'extendedProps'   => [
            'modul'      => 'Binjas',
            'angkatan'   => $row['nama_angkatan'],
            'tanggal'    => formatTanggal($row['tanggal']),
            'lokasi'     => $row['lokasi'],
            'status'     => ucfirst($row['status'] ?? '-'),
            'detail_url' => url('modules/binjas/detail.php') . '?id=' . $row['id'],
        ],
    ];
}

echo json_encode(['success' => true, 'events' => $events]);