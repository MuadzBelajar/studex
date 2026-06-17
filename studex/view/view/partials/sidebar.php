<?php
// ============================================================
//  STUDEX — Student Index
//  view/partials/sidebar.php
//  Variabel: $activePage, $user
// ============================================================
defined('STUDEX') or die('Direct access not permitted');

$user       = $user       ?? currentUser();
$activePage = $activePage ?? '';

function navActive(string $page, string $active): string {
    return $page === $active ? 'active' : '';
}
?>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu Utama">

    <!-- LOGO -->
    <div class="sidebar-logo">
        <div class="logo-icon">STX</div>
        <div class="logo-text">
            <div class="logo-name">STUDEX</div>
            <div class="logo-tagline">Student Index</div>
        </div>
    </div>

    <!-- NAV -->
    <nav class="sidebar-nav">

        <a href="<?= url('modules/dashboard/index.php') ?>" class="nav-item <?= navActive('dashboard', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
            </span>
            <span class="nav-label">Dashboard</span>
            <span class="nav-tooltip">Dashboard</span>
        </a>

        <a href="<?= url('modules/siswa/index.php') ?>" class="nav-item <?= navActive('siswa', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </span>
            <span class="nav-label">Data Siswa</span>
            <span class="nav-tooltip">Data Siswa</span>
        </a>

        <a href="<?= url('modules/jadwal/index.php') ?>" class="nav-item <?= navActive('jadwal', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </span>
            <span class="nav-label">Jadwal Terpadu</span>
            <span class="nav-tooltip">Jadwal Terpadu</span>
        </a>

        <div class="nav-divider"></div>
        <div class="nav-section-label">Kegiatan</div>

        <a href="<?= url('modules/rabuan/index.php') ?>" class="nav-item <?= navActive('rabuan', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </span>
            <span class="nav-label">Rabuan</span>
            <span class="nav-tooltip">Rabuan</span>
        </a>

        <a href="<?= url('modules/mentoring/index.php') ?>" class="nav-item <?= navActive('mentoring', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
            </span>
            <span class="nav-label">Mentoring</span>
            <span class="nav-tooltip">Mentoring</span>
        </a>

        <a href="<?= url('modules/operasional/index.php') ?>" class="nav-item <?= navActive('operasional', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                </svg>
            </span>
            <span class="nav-label">Operasional</span>
            <span class="nav-tooltip">Operasional</span>
        </a>

        <a href="<?= url('modules/binjas/index.php') ?>" class="nav-item <?= navActive('binjas', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
            </span>
            <span class="nav-label">Bina Jasmani</span>
            <span class="nav-tooltip">Bina Jasmani</span>
        </a>

        <div class="nav-divider"></div>
        <div class="nav-section-label">Rekap</div>

        <a href="<?= url('modules/presensi/index.php') ?>" class="nav-item <?= navActive('presensi', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
            </span>
            <span class="nav-label">Presensi</span>
            <span class="nav-tooltip">Presensi</span>
        </a>

        <div class="nav-divider"></div>

        <?php if (isSuperAdmin()): ?>
        <a href="<?= url('modules/settings/index.php') ?>" class="nav-item <?= navActive('settings', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
            </span>
            <span class="nav-label">Pengaturan</span>
            <span class="nav-tooltip">Pengaturan</span>
        </a>

        <a href="<?= url('modules/users/index.php') ?>" class="nav-item <?= navActive('users', $activePage) ?>">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            </span>
            <span class="nav-label">Manajemen User</span>
            <span class="nav-tooltip">Manajemen User</span>
        </a>
        <?php endif; ?>

        <a href="#" class="nav-item" id="helpBtn">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </span>
            <span class="nav-label">Bantuan</span>
            <span class="nav-tooltip">Bantuan</span>
        </a>

    </nav>

    <!-- BOTTOM — User + Logout -->
    <div class="sidebar-bottom">
        <a href="<?= url('modules/users/profile.php') ?>" class="sidebar-user">
            <div class="user-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['nama']) ?>">
                <?php else: ?>
                    <?= getInitials($user['nama']) ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= e($user['nama']) ?></div>
                <div class="user-role"><?= $user['role'] === 'super_admin' ? 'Super Admin' : 'Admin' ?></div>
            </div>
        </a>

        <a href="<?= url('modules/auth/logout.php') ?>" class="nav-item" id="logoutBtn" style="color:var(--color-red);">
            <span class="nav-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
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