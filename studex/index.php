<?php
// ============================================================
//  STUDEX — Student Index
//  index.php — Entry Point
// ============================================================

define('STUDEX', true);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/google_drive.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Helpers.php';

// Redirect ke dashboard jika sudah login, ke login jika belum
if (isLoggedIn()) {
    redirect(url('modules/dashboard/index.php'));
} else {
    redirect(url('modules/auth/login.php'));
}