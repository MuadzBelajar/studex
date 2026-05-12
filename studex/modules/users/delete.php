<?php
// ============================================================
//  STUDEX — Student Index
//  modules/users/delete.php — Hapus User
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Router.php';

requireSuperAdmin();
Router::requirePost(); // validasi POST + verifyCsrf() sekaligus

$db = db();
$id = sanitizeInt(post('id'));

if (!$id) {
    setFlash('error', 'ID user tidak valid.');
    redirect(url('modules/users/index.php'));
}

// Ambil data user
$stmt = $db->prepare("SELECT id, nama, username, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'User tidak ditemukan.');
    redirect(url('modules/users/index.php'));
}

// ── Proteksi: tidak boleh hapus diri sendiri ──────────────────
if ((int)$user['id'] === (int)$_SESSION['user_id']) {
    setFlash('error', 'Tidak dapat menghapus akun yang sedang digunakan.');
    redirect(url('modules/users/index.php'));
}

// ── Proteksi: minimal harus ada 1 super_admin aktif ──────────
if ($user['role'] === 'super_admin') {
    $cekSA = $db->query("SELECT COUNT(*) FROM users WHERE role='super_admin' AND is_active=1");
    if ((int)$cekSA->fetchColumn() <= 1) {
        setFlash('error', 'Tidak dapat menghapus Super Admin terakhir yang aktif.');
        redirect(url('modules/users/index.php'));
    }
}

// ── Eksekusi hapus ────────────────────────────────────────────
try {
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    setFlash('success', 'User ' . $user['nama'] . ' (@' . $user['username'] . ') berhasil dihapus.');
} catch (\PDOException $e) {
    error_log('STUDEX delete user error: ' . $e->getMessage());
    setFlash('error', 'Gagal menghapus user. Terjadi kesalahan server.');
}

redirect(url('modules/users/index.php'));