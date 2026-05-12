<?php
// ============================================================
//  STUDEX — Student Index
//  core/Helpers.php — Fungsi Bantu Global
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

// ============================================================
// STRING & OUTPUT
// ============================================================

/** Escape HTML untuk output aman */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Truncate string dengan ellipsis */
function truncate(string $str, int $length = 50, string $suffix = '...'): string {
    return mb_strlen($str) > $length
        ? mb_substr($str, 0, $length) . $suffix
        : $str;
}

/** Kapitalisasi setiap kata */
function titleCase(string $str): string {
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

// ============================================================
// TANGGAL & WAKTU
// ============================================================

/** Format tanggal Indonesia: "Senin, 14 Juli 2025" */
function formatTanggal(string $date, string $format = 'd F Y'): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
        5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
        9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    $ts = strtotime($date);
    $result = date($format, $ts);
    // Ganti nama bulan bahasa Inggris ke Indonesia
    foreach ($bulan as $num => $name) {
        $result = str_replace(date('F', mktime(0,0,0,$num,1)), $name, $result);
    }
    return $result;
}

/** Format tanggal pendek: "14 Jul 2025" */
function formatTanggalPendek(string $date): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    return date('d M Y', strtotime($date));
}

/** Format waktu: "08:30" */
function formatWaktu(string $time): string {
    if (empty($time) || $time === '00:00:00') return '-';
    return date('H:i', strtotime($time));
}

/** Relative time: "2 hari lalu", "baru saja" */
function timeAgo(string $datetime): string {
    $now  = time();
    $diff = $now - strtotime($datetime);
    if ($diff < 60)           return 'baru saja';
    if ($diff < 3600)         return floor($diff/60) . ' menit lalu';
    if ($diff < 86400)        return floor($diff/3600) . ' jam lalu';
    if ($diff < 2592000)      return floor($diff/86400) . ' hari lalu';
    if ($diff < 31104000)     return floor($diff/2592000) . ' bulan lalu';
    return floor($diff/31104000) . ' tahun lalu';
}

/** Tanggal hari ini untuk default value form */
function today(string $format = 'Y-m-d'): string {
    return date($format);
}

// ============================================================
// ANGKA & FILE
// ============================================================

/** Format ukuran file: "1.5 MB", "320 KB" */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

/** Format angka dengan pemisah ribuan */
function formatAngka(float $num, int $decimals = 0): string {
    return number_format($num, $decimals, ',', '.');
}

/** Hitung persentase */
function persentase(float $nilai, float $total, int $decimals = 1): float {
    if ($total <= 0) return 0;
    return round(($nilai / $total) * 100, $decimals);
}

// ============================================================
// STATUS BADGE
// ============================================================

/** Return HTML badge berdasarkan status */
function statusBadge(string $status): string {
    $map = [
        'terjadwal'      => ['label' => 'Terjadwal',    'class' => 'badge-info'],
        'berlangsung'    => ['label' => 'Berlangsung',  'class' => 'badge-warning'],
        'selesai'        => ['label' => 'Selesai',      'class' => 'badge-success'],
        'dibatalkan'     => ['label' => 'Dibatalkan',   'class' => 'badge-danger'],
        'draft'          => ['label' => 'Draft',        'class' => 'badge-secondary'],
        'aktif'          => ['label' => 'Aktif',        'class' => 'badge-success'],
        'tidak_aktif'    => ['label' => 'Tidak Aktif',  'class' => 'badge-secondary'],
        'alumni'         => ['label' => 'Alumni',       'class' => 'badge-info'],
        'hadir'          => ['label' => 'Hadir',        'class' => 'badge-success'],
        'izin'           => ['label' => 'Izin',         'class' => 'badge-info'],
        'sakit'          => ['label' => 'Sakit',        'class' => 'badge-warning'],
        'alpha'          => ['label' => 'Alpha',        'class' => 'badge-danger'],
        'layak'          => ['label' => 'Layak',        'class' => 'badge-success'],
        'tidak_layak'    => ['label' => 'Tidak Layak',  'class' => 'badge-danger'],
        'butuh_perbaikan'=> ['label' => 'Butuh Perbaikan','class'=>'badge-warning'],
        'pra'            => ['label' => 'Pra-Ops',      'class' => 'badge-info'],
        'operasional'    => ['label' => 'Operasional',  'class' => 'badge-warning'],
        'pasca'          => ['label' => 'Pasca-Ops',    'class' => 'badge-success'],
    ];
    $item = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-secondary'];
    return '<span class="badge ' . $item['class'] . '">' . $item['label'] . '</span>';
}

/** Label role */
function roleLabel(string $role): string {
    return match($role) {
        'super_admin' => '<span class="badge badge-army">Super Admin</span>',
        'admin'       => '<span class="badge badge-info">Admin</span>',
        default       => '<span class="badge badge-secondary">' . e($role) . '</span>',
    };
}

// ============================================================
// URL & NAVIGATION
// ============================================================

/** Generate URL relatif ke BASE_URL */
function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Asset URL */
function asset(string $path): string {
    return ASSET_URL . '/' . ltrim($path, '/');
}

/** Cek apakah URL saat ini mengandung string tertentu (untuk active nav) */
function isActivePage(string $path): bool {
    return str_contains($_SERVER['REQUEST_URI'], $path);
}

// ============================================================
// INPUT & SECURITY
// ============================================================

/** Sanitize input string */
function sanitize(mixed $input): string {
    return trim(strip_tags((string)$input));
}

/** Sanitize integer */
function sanitizeInt(mixed $input): int {
    return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/** Ambil POST value dengan default */
function post(string $key, mixed $default = ''): mixed {
    return $_POST[$key] ?? $default;
}

/** Ambil GET value dengan default */
function get(string $key, mixed $default = ''): mixed {
    return $_GET[$key] ?? $default;
}

// ============================================================
// PAGINATION
// ============================================================

/**
 * Hitung data pagination
 * @return array ['offset', 'page', 'per_page', 'total_pages']
 */
function paginate(int $total, int $perPage = DEFAULT_PER_PAGE): array {
    $page       = max(1, sanitizeInt(get('page', 1)));
    $totalPages = max(1, ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

// ============================================================
// JSON RESPONSE (untuk AJAX endpoints)
// ============================================================

function jsonSuccess(mixed $data = null, string $message = 'Berhasil'): never {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

function jsonError(string $message = 'Terjadi kesalahan', int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ============================================================
// MISC
// ============================================================

/** Generate unique filename untuk upload */
function generateFilename(string $originalName): string {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
}

/** Ambil ekstensi file */
function getExtension(string $filename): string {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/** Validasi ekstensi file */
function isAllowedExtension(string $filename, array $allowed = ALLOWED_EXTENSIONS): bool {
    return in_array(getExtension($filename), $allowed);
}

/** Inisial nama (untuk avatar placeholder) */
function getInitials(string $nama): string {
    $words = explode(' ', trim($nama));
    if (count($words) >= 2) {
        return strtoupper($words[0][0] . $words[1][0]);
    }
    return strtoupper(substr($nama, 0, 2));
}