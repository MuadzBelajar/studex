<?php
// ============================================================
//  STUDEX — Student Index
//  config/app.php — Konstanta Global Aplikasi
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

// --- Informasi Aplikasi ---
define('APP_NAME',      'STUDEX');
define('APP_TAGLINE',   'Student Index');
define('APP_VERSION',   '1.0.0');

// --- URL & Path ---
// Sesuaikan BASE_URL dengan environment lo
// Local XAMPP  : http://localhost/studex
// Local Laragon: http://studex.test
define('BASE_URL',  'http://localhost/studexcopy');
define('ROOT_PATH', dirname(__DIR__));
define('ASSET_URL', BASE_URL . '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/storage/uploads');

// --- Timezone ---
date_default_timezone_set('Asia/Makassar'); // WITA

// --- Session ---
define('SESSION_LIFETIME', 480); // menit

// --- Upload ---
define('MAX_UPLOAD_MB',   10);
define('MAX_UPLOAD_BYTES', MAX_UPLOAD_MB * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'docx', 'pptx', 'xlsx', 'jpg', 'jpeg', 'png']);
define('ALLOWED_DOC_EXTENSIONS', ['pdf', 'docx', 'pptx', 'xlsx']);
define('ALLOWED_IMG_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// --- Pagination ---
define('DEFAULT_PER_PAGE', 15);

// --- Role ---
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN',       'admin');

// --- Status Kegiatan ---
define('STATUS_TERJADWAL',   'terjadwal');
define('STATUS_BERLANGSUNG', 'berlangsung');
define('STATUS_SELESAI',     'selesai');
define('STATUS_DIBATALKAN',  'dibatalkan');

// --- Modul Presensi ---
define('MODUL_RABUAN',    'rabuan');
define('MODUL_MENTORING', 'mentoring');
define('MODUL_BINJAS',    'binjas');

// --- Error Reporting (ubah ke 0 di production) ---
// Saat troubleshooting, set display_errors=1. 
// Untuk menghindari error memenuhi output ("/////"), default dimatikan.
error_reporting(E_ALL);
// Hindari error memenuhi output "/////".
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

ini_set('memory_limit', '256M');
// Tambahan saat debugging: tampilkan log di file (pastikan folder writable).
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');
