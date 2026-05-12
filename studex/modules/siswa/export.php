<?php
// ============================================================
//  STUDEX — Student Index
//  modules/siswa/export.php — Export Data Siswa ke CSV
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db = db();

// ============================================================
// FILTER (sama seperti index.php)
// ============================================================
$search       = sanitize(get('search', ''));
$angkatanId   = sanitizeInt(get('angkatan_id', 0));
$status       = sanitize(get('status', ''));
$jenisKelamin = sanitize(get('jk', ''));

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(s.nama LIKE ? OR s.nis LIKE ? OR s.email LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($angkatanId) {
    $where[]  = 's.angkatan_id = ?';
    $params[] = $angkatanId;
}
if ($status) {
    $where[]  = 's.status = ?';
    $params[] = $status;
}
if ($jenisKelamin) {
    $where[]  = 's.jenis_kelamin = ?';
    $params[] = $jenisKelamin;
}

$whereStr = implode(' AND ', $where);

// ============================================================
// AMBIL DATA
// ============================================================
$stmt = $db->prepare("
    SELECT
        s.nis,
        s.nama,
        CASE s.jenis_kelamin WHEN 'L' THEN 'Laki-laki' ELSE 'Perempuan' END as jenis_kelamin,
        a.nama   as angkatan,
        a.kode   as kode_angkatan,
        s.tempat_lahir,
        s.tanggal_lahir,
        s.no_hp,
        s.email,
        s.alamat,
        s.status,
        s.created_at,
        -- Statistik presensi
        COALESCE(SUM(CASE WHEN p.status = 'hadir' THEN 1 END), 0) as total_hadir,
        COALESCE(SUM(CASE WHEN p.status = 'izin'  THEN 1 END), 0) as total_izin,
        COALESCE(SUM(CASE WHEN p.status = 'sakit' THEN 1 END), 0) as total_sakit,
        COALESCE(SUM(CASE WHEN p.status = 'alpha' THEN 1 END), 0) as total_alpha
    FROM siswa s
    JOIN angkatan a ON a.id = s.angkatan_id
    LEFT JOIN presensi p ON p.siswa_id = s.id
    WHERE $whereStr
    GROUP BY s.id
    ORDER BY a.tahun DESC, s.nama ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ============================================================
// GENERATE CSV
// ============================================================
$format   = sanitize(get('format', 'csv'));
$filename = 'siswa_studex_' . date('Ymd_His');

// Header CSV
$headers = [
    'NIS',
    'Nama Lengkap',
    'Jenis Kelamin',
    'Angkatan',
    'Kode Angkatan',
    'Tempat Lahir',
    'Tanggal Lahir',
    'No. HP',
    'Email',
    'Alamat',
    'Status',
    'Tgl Daftar',
    'Total Hadir',
    'Total Izin',
    'Total Sakit',
    'Total Alpha',
];

// Kirim header HTTP
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// BOM untuk Excel agar tidak salah encoding
echo "\xEF\xBB\xBF";

// Output CSV
$output = fopen('php://output', 'w');

// Tulis header
fputcsv($output, $headers);

// Tulis data
foreach ($rows as $row) {
    fputcsv($output, [
        $row['nis'],
        $row['nama'],
        $row['jenis_kelamin'],
        $row['angkatan'],
        $row['kode_angkatan'],
        $row['tempat_lahir']  ?? '',
        $row['tanggal_lahir'] ? date('d/m/Y', strtotime($row['tanggal_lahir'])) : '',
        $row['no_hp']   ?? '',
        $row['email']   ?? '',
        $row['alamat']  ?? '',
        ucfirst($row['status']),
        $row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '',
        $row['total_hadir'],
        $row['total_izin'],
        $row['total_sakit'],
        $row['total_alpha'],
    ]);
}

fclose($output);
exit;