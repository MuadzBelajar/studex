<?php
// ============================================================
//  STUDEX — Student Index
//  config/session.php — Manajemen Session & Auth Guard
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

// Konfigurasi session sebelum start
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME * 60);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// AUTH GUARD FUNCTIONS
// ============================================================

/**
 * Cek apakah user sudah login
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Cek role user saat ini
 */
function currentRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Cek apakah user adalah Super Admin
 */
function isSuperAdmin(): bool {
    return currentRole() === ROLE_SUPER_ADMIN;
}

/**
 * Cek apakah user adalah Admin (termasuk Super Admin)
 */
function isAdmin(): bool {
    return in_array(currentRole(), [ROLE_SUPER_ADMIN, ROLE_ADMIN]);
}

/**
 * Data user yang sedang login
 */
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'nama'     => $_SESSION['user_nama'],
        'username' => $_SESSION['user_username'],
        'email'    => $_SESSION['user_email'],
        'role'     => $_SESSION['user_role'],
        'avatar'   => $_SESSION['user_avatar'] ?? null,
    ];
}

/**
 * Guard: wajib login — redirect ke login jika belum
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Silakan login terlebih dahulu.');
        redirect(BASE_URL . '/modules/auth/login.php');
    }
}

/**
 * Guard: wajib Super Admin
 */
function requireSuperAdmin(): void {
    requireLogin();
    if (!isSuperAdmin()) {
        setFlash('error', 'Akses ditolak. Hanya Super Admin yang dapat mengakses halaman ini.');
        redirect(BASE_URL . '/modules/dashboard/index.php');
    }
}

/**
 * Guard: wajib Admin atau Super Admin
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'Akses ditolak.');
        redirect(BASE_URL . '/modules/auth/login.php');
    }
}

// ============================================================
// FLASH MESSAGE
// ============================================================

/**
 * Set flash message (sekali tampil lalu hilang)
 * $type: 'success' | 'error' | 'warning' | 'info'
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Ambil & hapus flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================================
// REDIRECT
// ============================================================
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ============================================================
// CSRF PROTECTION
// ============================================================

/**
 * Generate CSRF token (simpan di session)
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token dari form
 */
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

/**
 * HTML input hidden untuk CSRF
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}