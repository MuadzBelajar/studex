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
         PANEL KIRI — Branding
         ============================================================ -->
    <div class="auth-left">

        <!-- Dekorasi -->
        <div class="auth-left-deco"></div>

        <!-- Brand -->
        <div class="auth-brand">
            <div class="auth-brand-icon">STX</div>
            <div>
                <div class="auth-brand-name">STUDEX</div>
                <div class="auth-brand-tagline">Student Index</div>
            </div>
        </div>

        <!-- Headline -->
        <div class="auth-headline">
            <h1 class="auth-headline-title">
                <?= nl2br(e($authTitle)) ?>
            </h1>
            <p class="auth-headline-desc"><?= e($authDesc) ?></p>

            <!-- Feature list -->
            <div class="auth-features">
                <div class="auth-feature-item">
                    <div class="auth-feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8"  y1="2" x2="8"  y2="6"/>
                            <line x1="3"  y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <span class="auth-feature-text">Jadwal kegiatan terpadu dalam satu kalender</span>
                </div>

                <div class="auth-feature-item">
                    <div class="auth-feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <span class="auth-feature-text">Monitoring Bina Jasmani dengan visualisasi data</span>
                </div>

                <div class="auth-feature-item">
                    <div class="auth-feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.2 8.4c.5.38.8.97.8 1.6v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V10a2 2 0 0 1 .8-1.6l8-6a2 2 0 0 1 2.4 0l8 6z"/>
                            <polyline points="16 13 12 17 8 13"/>
                            <line x1="12" y1="17" x2="12" y2="7"/>
                        </svg>
                    </div>
                    <span class="auth-feature-text">Upload notulensi & laporan terintegrasi Google Drive</span>
                </div>

                <div class="auth-feature-item">
                    <div class="auth-feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <span class="auth-feature-text">Presensi terpusat Rabuan, Mentoring & Binjas</span>
                </div>
            </div>
        </div>

        <!-- Footer kiri -->
        <div class="auth-left-footer">
            &copy; <?= date('Y') ?> STUDEX — Student Index. All rights reserved.
        </div>

    </div><!-- /.auth-left -->

    <!-- ============================================================
         PANEL KANAN — Form
         ============================================================ -->
    <div class="auth-right">
        <div class="auth-card">
            <?= $content ?? '' ?>
        </div>
    </div><!-- /.auth-right -->

</div><!-- /.auth-wrapper -->

<!-- Scripts minimal untuk auth -->
<script>
    // Toggle password visibility
    document.querySelectorAll('.password-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = this.closest('.password-field').querySelector('input');
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            // Ganti icon
            this.innerHTML = isPassword
                ? `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                   </svg>`
                : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                   </svg>`;
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