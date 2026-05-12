<?php
// ============================================================
//  STUDEX — Student Index
//  modules/auth/logout.php — Proses Logout
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

// Hanya proses kalau sudah login
if (isLoggedIn()) {
    Auth::logout();
}

// Redirect ke login dengan flash message
setFlash('success', 'Anda berhasil keluar. Sampai jumpa!');
redirect(url('modules/auth/login.php'));