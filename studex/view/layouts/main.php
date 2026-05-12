<?php

defined('STUDEX') or die('Direct access not permitted');
requireLogin();

$user        = currentUser();
$pageTitle   = $pageTitle   ?? 'STUDEX';
$pageSubtitle= $pageSubtitle?? '';
$activePage  = $activePage  ?? '';
$flash       = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="STUDEX — Student Index, Sistem Monitoring Aktivitas Siswa">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — STUDEX</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.ico') ?>">

    <!-- Google Fonts Fallback (Inter + Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS — urutan penting -->
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/reset.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/typography.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/charts.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/modal.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>

    <?php if (isset($extraCss)): ?>
        <?php foreach ((array)$extraCss as $css): ?>
            <link rel="stylesheet" href="<?= asset('css/' . $css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<!-- ============================================================
     APP WRAPPER
     ============================================================ -->
<div class="app-wrapper">

    <!-- ========================================================
         SIDEBAR
         ======================================================== -->
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu Utama">

        <!-- Logo -->
        <div class="sidebar-logo">
            <div class="logo-icon">STX</div>
            <div class="logo-text">
                <div class="logo-name">STUDEX</div>
                <div class="logo-tagline">Student Index</div>
            </div>
        </div>

        <!-- Nav utama -->
        <nav class="sidebar-nav">

            <!-- Dashboard -->
            <a href="<?= url('modules/dashboard/index.php') ?>"
               class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </span>
                <span class="nav-label">Dashboard</span>
                <span class="nav-tooltip">Dashboard</span>
            </a>

            <!-- Data Siswa -->
            <a href="<?= url('modules/siswa/index.php') ?>"
               class="nav-item <?= $activePage === 'siswa' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <span class="nav-label">Data Siswa</span>
                <span class="nav-tooltip">Data Siswa</span>
            </a>

            <!-- Jadwal Terpadu -->
            <a href="<?= url('modules/jadwal/index.php') ?>"
               class="nav-item <?= $activePage === 'jadwal' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </span>
                <span class="nav-label">Jadwal Terpadu</span>
                <span class="nav-tooltip">Jadwal Terpadu</span>
            </a>

            <div class="nav-divider"></div>
            <div class="nav-section-label">Kegiatan</div>

            <!-- Rabuan -->
            <a href="<?= url('modules/rabuan/index.php') ?>"
               class="nav-item <?= $activePage === 'rabuan' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </span>
                <span class="nav-label">Rabuan</span>
                <span class="nav-tooltip">Rabuan</span>
            </a>

            <!-- Mentoring -->
            <a href="<?= url('modules/mentoring/index.php') ?>"
               class="nav-item <?= $activePage === 'mentoring' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                </span>
                <span class="nav-label">Mentoring</span>
                <span class="nav-tooltip">Mentoring</span>
            </a>

            <!-- Operasional -->
            <a href="<?= url('modules/operasional/index.php') ?>"
               class="nav-item <?= $activePage === 'operasional' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                    </svg>
                </span>
                <span class="nav-label">Operasional</span>
                <span class="nav-tooltip">Operasional</span>
            </a>

            <!-- Bina Jasmani -->
            <a href="<?= url('modules/binjas/index.php') ?>"
               class="nav-item <?= $activePage === 'binjas' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="18" cy="5" r="3"/>
                        <circle cx="6" cy="12" r="3"/>
                        <circle cx="18" cy="19" r="3"/>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                    </svg>
                </span>
                <span class="nav-label">Bina Jasmani</span>
                <span class="nav-tooltip">Bina Jasmani</span>
            </a>

            <div class="nav-divider"></div>
            <div class="nav-section-label">Rekap</div>

            <!-- Presensi -->
            <a href="<?= url('modules/presensi/index.php') ?>"
               class="nav-item <?= $activePage === 'presensi' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </span>
                <span class="nav-label">Presensi</span>
                <span class="nav-tooltip">Presensi</span>
            </a>

            <div class="nav-divider"></div>

            <!-- Settings — Super Admin only -->
            <?php if (isSuperAdmin()): ?>
            <a href="<?= url('modules/settings/index.php') ?>"
               class="nav-item <?= $activePage === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                </span>
                <span class="nav-label">Pengaturan</span>
                <span class="nav-tooltip">Pengaturan</span>
            </a>

            <!-- Manajemen User — Super Admin only -->
            <a href="<?= url('modules/users/index.php') ?>"
               class="nav-item <?= $activePage === 'users' ? 'active' : '' ?>">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                        <line x1="19" y1="8" x2="19" y2="14"/>
                        <line x1="22" y1="11" x2="16" y2="11"/>
                    </svg>
                </span>
                <span class="nav-label">Manajemen User</span>
                <span class="nav-tooltip">Manajemen User</span>
            </a>
            <?php endif; ?>

            <!-- Bantuan -->
            <a href="#" class="nav-item" id="helpBtn">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </span>
                <span class="nav-label">Bantuan</span>
                <span class="nav-tooltip">Bantuan</span>
            </a>

        </nav>

        <!-- User + Logout -->
        <div class="sidebar-bottom">
            <a href="<?= url('modules/users/profile.php') ?>" class="sidebar-user" style="text-decoration:none;">
                <div class="user-avatar">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['nama']) ?>">
                    <?php else: ?>
                        <?= getInitials($user['nama']) ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= e($user['nama']) ?></div>
                    <div class="user-role">
                        <?= $user['role'] === 'super_admin' ? 'Super Admin' : 'Admin' ?>
                    </div>
                </div>
            </a>

            <a href="<?= url('modules/auth/logout.php') ?>"
               class="nav-item"
               onclick="return confirm('Yakin ingin keluar?')"
               style="color: var(--color-red);">
                <span class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </span>
                <span class="nav-label">Keluar</span>
                <span class="nav-tooltip">Keluar</span>
            </a>
        </div>

    </aside>

    <!-- Mobile sidebar overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ========================================================
         MAIN AREA
         ======================================================== -->
    <div class="main-area">

        <!-- TOPBAR -->
        <header class="topbar" role="banner">

            <div class="topbar-left">
                <!-- Mobile hamburger -->
                <button class="topbar-menu-btn" id="menuToggle" aria-label="Toggle Menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6"  x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>

                <div class="hide-mobile">
                    <?php include ROOT_PATH . '/view/partials/breadcrumb.php'; ?>
                    <h1 class="page-title"><?= e($pageTitle) ?></h1>
                    <?php if ($pageSubtitle): ?>
                        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Mobile: hanya page title -->
                <div class="show-mobile" style="display:none;">
                    <h1 class="page-title" style="font-size:var(--text-base);"><?= e($pageTitle) ?></h1>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Search -->
                <div class="search-bar hide-mobile">
                    <input type="text" placeholder="Cari sesuatu..." id="globalSearch" autocomplete="off">
                    <button class="search-btn" aria-label="Cari">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </button>
                </div>

                <!-- Notifikasi (placeholder) -->
                <button class="topbar-btn" aria-label="Notifikasi" id="notifBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span class="notif-badge" id="notifBadge" style="display:none;"></span>
                </button>

                <!-- User avatar dropdown -->
                <div class="dropdown" id="userDropdown">
                    <button class="topbar-btn" aria-label="Menu User" id="userDropdownBtn">
                        <div class="avatar avatar-sm" style="width:28px;height:28px;font-size:11px;">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['nama']) ?>">
                            <?php else: ?>
                                <?= getInitials($user['nama']) ?>
                            <?php endif; ?>
                        </div>
                    </button>
                    <div class="dropdown-menu" id="userDropdownMenu">
                        <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-color);">
                            <div style="font-size:var(--text-sm);font-weight:var(--fw-semibold);color:var(--text-primary);">
                                <?= e($user['nama']) ?>
                            </div>
                            <div style="font-size:var(--text-xs);color:var(--text-muted); margin-top:2px;">
                                <?= e($user['email']) ?>
                            </div>
                        </div>
                        <a href="<?= url('modules/users/profile.php') ?>" class="dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Profil Saya
                        </a>
                        <?php if (isSuperAdmin()): ?>
                        <a href="<?= url('modules/settings/index.php') ?>" class="dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" width="16" height="16">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06-.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Pengaturan
                        </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="<?= url('modules/auth/logout.php') ?>"
                           class="dropdown-item danger"
                           onclick="return confirm('Yakin ingin keluar?')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" width="16" height="16">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Keluar
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- ======================================================
             MAIN CONTENT
             ====================================================== -->
        <main class="main-content" role="main">

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <?php include ROOT_PATH . '/view/partials/flash_message.php'; ?>
            <?php endif; ?>

            <!-- Page Content -->
            <?= $content ?? '' ?>

        </main>

        <!-- Footer -->
        <?php include ROOT_PATH . '/view/partials/footer.php'; ?>

    </div><!-- /.main-area -->

</div><!-- /.app-wrapper -->

<!-- ============================================================
     TOAST CONTAINER
     ============================================================ -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>

<!-- ============================================================
     MODAL KONFIRMASI HAPUS (Global)
     ============================================================ -->
<?php include ROOT_PATH . '/view/partials/modal_confirm.php'; ?>

<!-- ============================================================
     SCRIPTS
     ============================================================ -->
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/modal.js') ?>"></script>
<script src="<?= asset('js/notification.js') ?>"></script>

<?php if (isset($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
        <script src="<?= asset('js/' . $js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>