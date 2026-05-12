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
$q          = sanitize($_GET['q']           ?? '');
$angkatanId = sanitizeInt($_GET['angkatan_id'] ?? 0);
$status     = sanitize($_GET['status']      ?? 'aktif');   // aktif | semua
$limit      = min((int)($_GET['limit']      ?? 10), 50);   // maks 50
$exclude    = sanitize($_GET['exclude']     ?? '');        // comma-sep IDs yang dikecualikan
$context    = sanitize($_GET['context']     ?? '');        // presensi | operasional | (kosong=umum)

// Minimal 2 karakter untuk search
if (strlen($q) < 2) {
    echo json_encode([
        'success' => true,
        'query'   => $q,
        'results' => [],
        'message' => 'Ketik minimal 2 karakter.',
    ]);
    exit;
}

$db = db();

// ── Build query ────────────────────────────────────────────────
$conditions = [];
$params     = [];

// Filter teks: nama atau NIM
$conditions[] = "(s.nama LIKE ? OR s.nim LIKE ?)";
$params[]     = "%{$q}%";
$params[]     = "%{$q}%";

// Filter status
if ($status === 'aktif') {
    $conditions[] = "s.status = 'aktif'";
}

// Filter angkatan
if ($angkatanId) {
    $conditions[] = "s.angkatan_id = ?";
    $params[]     = $angkatanId;
}

// Exclude ID tertentu (misal: sudah dipilih sebagai peserta)
$excludeIds = [];
if ($exclude) {
    $excludeIds = array_filter(array_map('intval', explode(',', $exclude)));
    if ($excludeIds) {
        $ph           = implode(',', array_fill(0, count($excludeIds), '?'));
        $conditions[] = "s.id NOT IN ({$ph})";
        $params       = array_merge($params, $excludeIds);
    }
}

$whereClause = implode(' AND ', $conditions);

$sql = "SELECT
            s.id,
            s.nama,
            s.nim,
            s.status,
            s.foto,
            a.nama_angkatan,
            a.id AS angkatan_id
        FROM siswa s
        LEFT JOIN angkatan a ON a.id = s.angkatan_id
        WHERE {$whereClause}
        ORDER BY
            CASE WHEN s.nama LIKE ? THEN 0 ELSE 1 END,  -- prioritaskan match awal nama
            s.nama ASC
        LIMIT {$limit}";

// Tambah param untuk ORDER BY prioritas
$params[] = "{$q}%";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ── Context: tambahkan data tambahan per konteks ───────────────
$results = [];
foreach ($rows as $row) {

    $item = [
        'id'          => (int) $row['id'],
        'nama'        => $row['nama'],
        'nim'         => $row['nim'],
        'status'      => $row['status'],
        'angkatan'    => $row['nama_angkatan'],
        'angkatan_id' => (int) $row['angkatan_id'],
        'foto_url'    => $row['foto']
                         ? url('storage/uploads/siswa/' . $row['foto'])
                         : url('assets/img/avatar-default.png'),
        'detail_url'  => url('modules/siswa/detail.php') . '?id=' . $row['id'],
        'label'       => $row['nama'] . ' (' . $row['nim'] . ')',  // untuk select2 / autocomplete
    ];

    // ── Context: presensi — tampilkan status kehadiran terakhir
    if ($context === 'presensi') {
        $lastP = $db->prepare(
            "SELECT modul, status, created_at
             FROM presensi
             WHERE siswa_id = ?
             ORDER BY created_at DESC
             LIMIT 1"
        );
        $lastP->execute([$row['id']]);
        $last = $lastP->fetch();

        $item['last_presensi'] = $last ? [
            'modul'  => $last['modul'],
            'status' => $last['status'],
            'tanggal'=> date('d/m/Y', strtotime($last['created_at'])),
        ] : null;

        // Hitung % kehadiran bulan ini
        $statP = $db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status='hadir' THEN 1 ELSE 0 END) AS hadir
             FROM presensi
             WHERE siswa_id = ?
               AND created_at >= ?"
        );
        $statP->execute([$row['id'], date('Y-m-01')]);
        $stat = $statP->fetch();
        $item['pct_hadir_bulan_ini'] = $stat['total'] > 0
            ? round($stat['hadir'] / $stat['total'] * 100)
            : null;
    }

    // ── Context: operasional — cek apakah sudah jadi peserta
    if ($context === 'operasional') {
        $opsId = sanitizeInt($_GET['operasional_id'] ?? 0);
        if ($opsId) {
            $cek = $db->prepare(
                "SELECT id FROM operasional_peserta
                 WHERE operasional_id = ? AND siswa_id = ?"
            );
            $cek->execute([$opsId, $row['id']]);
            $item['sudah_peserta'] = (bool) $cek->fetchColumn();
        }
    }

    $results[] = $item;
}

// ── Hitung total (tanpa LIMIT, untuk info) ────────────────────
$countSql = "SELECT COUNT(*) FROM siswa s WHERE {$whereClause}";
// Hapus param ORDER BY dari params untuk count
$countParams = array_slice($params, 0, count($params) - 1);
$countStmt   = $db->prepare($countSql);
$countStmt->execute($countParams);
$totalFound  = (int) $countStmt->fetchColumn();

// ── Response ───────────────────────────────────────────────────
echo json_encode([
    'success'     => true,
    'query'       => $q,
    'total_found' => $totalFound,
    'showing'     => count($results),
    'results'     => $results,
]);