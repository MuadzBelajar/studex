<?php
// ============================================================
//  STUDEX — Student Index
//  view/partials/topbar.php
//  Variabel: $pageTitle, $pageSubtitle, $breadcrumbs, $user
// ============================================================
defined('STUDEX') or die('Direct access not permitted');

$user         = $user         ?? currentUser();
$pageTitle    = $pageTitle    ?? 'STUDEX';
$pageSubtitle = $pageSubtitle ?? '';
$breadcrumbs  = $breadcrumbs  ?? [];
?>

<header class="topbar" id="topbar" role="banner">

    <!-- KIRI -->
    <div class="topbar-left" style="display:flex; align-items:center; justify-content:flex-start; gap:12px; min-width:0;">

        <!-- Hamburger (mobile) -->
        <button class="topbar-menu-btn" id="menuToggle" aria-label="Buka/Tutup Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6"  x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <!-- Desktop: breadcrumb + title -->
        <div class="hide-mobile">
            <?php if (!empty($breadcrumbs)): ?>
                <?php include ROOT_PATH . '/view/partials/breadcrumb.php'; ?>
            <?php endif; ?>
            <h1 class="page-title"><?= e($pageTitle) ?></h1>
            <?php if ($pageSubtitle): ?>
                <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
            <?php endif; ?>
        </div>

        <!-- Mobile: title saja -->
        <div class="show-mobile" style="display:none;">
            <h1 class="page-title" style="font-size:var(--text-base);"><?= e($pageTitle) ?></h1>
        </div>

    </div>

    <!-- KANAN -->
    <div class="topbar-right">

        <!-- Notifikasi -->
        <button class="topbar-btn" id="notifBtn" aria-label="Notifikasi">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span class="notif-badge" id="notifBadge" style="display:none;"></span>
        </button>

        <!-- User Dropdown -->
        <div class="dropdown" id="userDropdown">
            <button class="topbar-btn" id="userDropdownBtn"
                    aria-label="Menu pengguna" aria-haspopup="true" aria-expanded="false">
                <div class="avatar avatar-sm" style="width:28px;height:28px;font-size:11px;">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="<?= e($user['nama']) ?>">
                    <?php else: ?>
                        <?= getInitials($user['nama']) ?>
                    <?php endif; ?>
                </div>
            </button>

            <div class="dropdown-menu" id="userDropdownMenu" role="menu">

                <!-- Info -->
                <div style="padding:var(--space-3) var(--space-4);
                            border-bottom:1px solid var(--border-color);
                            pointer-events:none;">
                    <div style="font-size:var(--text-sm);font-weight:var(--fw-semibold);
                                color:var(--text-primary);line-height:1.3;">
                        <?= e($user['nama']) ?>
                    </div>
                    <div style="font-size:var(--text-xs);color:var(--text-muted);margin-top:2px;">
                        <?= e($user['email']) ?>
                    </div>
                    <div style="margin-top:var(--space-2);">
                        <?= roleLabel($user['role']) ?>
                    </div>
                </div>

                <a href="<?= url('modules/users/profile.php') ?>" class="dropdown-item" role="menuitem">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" width="16" height="16">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Profil Saya
                </a>

                <?php if (isSuperAdmin()): ?>
                <a href="<?= url('modules/settings/index.php') ?>" class="dropdown-item" role="menuitem">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" width="16" height="16">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    Pengaturan Sistem
                </a>

                <a href="<?= url('modules/users/index.php') ?>" class="dropdown-item" role="menuitem">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" width="16" height="16">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                        <line x1="19" y1="8" x2="19" y2="14"/>
                        <line x1="22" y1="11" x2="16" y2="11"/>
                    </svg>
                    Manajemen User
                </a>
                <?php endif; ?>

                <div class="dropdown-divider"></div>

                <a href="<?= url('modules/auth/logout.php') ?>"
                   class="dropdown-item danger" role="menuitem" id="dropdownLogout">
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

