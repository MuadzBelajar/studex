<?php
// ============================================================
//  STUDEX — Student Index
//  view/layouts/auth.php — Layout Halaman Autentikasi
//  Cara pakai di login.php:
//    $pageTitle = 'Login';
//    ob_start();
//    // ... konten form login ...
//    $content = ob_get_clean();
//    include ROOT_PATH . '/view/layouts/auth.php';
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

// Kalau sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect(url('modules/dashboard/index.php'));
}

$pageTitle = $pageTitle ?? 'Login';
$authTitle = $authTitle ?? 'Selamat Datang!'; // judul besar kiri
$authDesc  = $authDesc  ?? 'Sistem monitoring aktivitas siswa yang terintegrasi dan mudah digunakan.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="STUDEX — Student Index Login">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — STUDEX</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.ico') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/reset.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/typography.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
</head>
<body>

<div class="auth-wrapper">

    <!-- ============================================================
         Single centered card layout
         ============================================================ -->
    <div class="auth-card">
        <?= $content ?? '' ?>
    </div>

</div><!-- /.auth-wrapper -->

<!-- Scripts minimal untuk auth -->
<script>
    // Toggle password visibility (minimal vanilla JS)
    document.querySelectorAll('.password-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            // Cari input password terkait
            var parent = this.closest('.password-field') || this.parentElement;
            var input = parent ? parent.querySelector('input') : null;
            if (!input) return;

            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });

    // Auto dismiss alert setelah 5 detik
    var alerts = document.querySelectorAll('.auth-alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 400);
        }, 5000);
    });
</script>

</body>
</html>