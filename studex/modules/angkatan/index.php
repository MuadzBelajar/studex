<?php
// ============================================================
//  STUDEX — Student Index
//  modules/angkatan/index.php — Daftar Angkatan
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db = db();

// ============================================================
// AMBIL DATA ANGKATAN + STATISTIK
// ============================================================
$angkatanList = $db->query("
    SELECT
        a.*,
        COUNT(DISTINCT s.id)                                        AS total_siswa,
        COUNT(DISTINCT CASE WHEN s.status='aktif' THEN s.id END)    AS siswa_aktif,
        COUNT(DISTINCT r.id)                                        AS total_rabuan,
        COUNT(DISTINCT m.id)                                        AS total_mentoring,
        COUNT(DISTINCT b.id)                                        AS total_binjas
    FROM angkatan a
    LEFT JOIN siswa          s ON s.angkatan_id = a.id
    LEFT JOIN rabuan         r ON r.angkatan_id = a.id
    LEFT JOIN mentoring_sesi m ON m.angkatan_id = a.id
    LEFT JOIN binjas_sesi    b ON b.angkatan_id = a.id
    GROUP BY a.id
    ORDER BY a.tahun DESC, a.nama ASC
")->fetchAll();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Manajemen Angkatan';
$pageSubtitle = 'Kelola batch dan cohort siswa';
$activePage  = 'siswa';
$breadcrumbs = [
    ['label' => 'Dashboard',  'url' => url('modules/dashboard/index.php')],
    ['label' => 'Angkatan'],
];

ob_start();
?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div class="page-header-left"></div>
    <div class="page-header-actions">
        <?php if (isSuperAdmin()): ?>
        <a href="<?= url('modules/angkatan/create.php') ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" width="16" height="16"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Angkatan
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- GRID ANGKATAN -->
<?php if (empty($angkatanList)): ?>
<div class="card">
    <div class="empty-state">
        <svg class="empty-state-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        <div class="empty-state-title">Belum ada angkatan</div>
        <div class="empty-state-desc">Buat angkatan pertama untuk mulai mengelola data siswa.</div>
        <?php if (isSuperAdmin()): ?>
        <a href="<?= url('modules/angkatan/create.php') ?>" class="btn btn-primary">
            + Buat Angkatan
        </a>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<div class="grid grid-3 gap-5">
    <?php foreach ($angkatanList as $ang): ?>
    <div class="card" style="position:relative;">

        <!-- Badge aktif/tidak -->
        <div style="position:absolute;top:var(--space-5);right:var(--space-5);">
            <?= $ang['is_aktif']
                ? '<span class="badge badge-success">Aktif</span>'
                : '<span class="badge badge-secondary">Tidak Aktif</span>' ?>
        </div>

        <!-- Header card -->
        <div class="flex items-center gap-4 mb-5">
            <div style="width:52px;height:52px;border-radius:var(--border-radius-lg);
                        background:var(--primary-light);display:flex;align-items:center;
                        justify-content:center;flex-shrink:0;">
                <span style="font-family:var(--font-heading);font-size:var(--text-md);
                             font-weight:var(--fw-bold);color:var(--primary);">
                    <?= $ang['tahun'] ? substr($ang['tahun'], 2) : '?' ?>
                </span>
            </div>
            <div style="flex:1;min-width:0;padding-right:60px;">
                <div style="font-size:var(--text-base);font-weight:var(--fw-semibold);
                            color:var(--text-primary);line-height:1.3;">
                    <?= e($ang['nama']) ?>
                </div>
                <div style="font-size:var(--text-xs);color:var(--text-muted);margin-top:2px;">
                    <?= e($ang['kode']) ?> &middot; Tahun <?= $ang['tahun'] ?>
                </div>
            </div>
        </div>

        <?php if ($ang['deskripsi']): ?>
        <p style="font-size:var(--text-sm);color:var(--text-muted);
                  margin-bottom:var(--space-4);line-height:1.5;"
           class="text-clamp-2">
            <?= e($ang['deskripsi']) ?>
        </p>
        <?php endif; ?>

        <!-- Statistik -->
        <div class="grid grid-3 gap-2 mb-5">
            <?php foreach ([
                [$ang['total_siswa'],   'Siswa',    'var(--primary)'],
                [$ang['total_rabuan'],  'Rabuan',   'var(--color-green-dark-300)'],
                [$ang['total_mentoring'],'Mentoring','var(--color-purple-300)'],
            ] as [$val, $lbl, $clr]): ?>
            <div style="text-align:center;padding:var(--space-3);
                        background:var(--neutral-050);border-radius:var(--border-radius-md);">
                <div style="font-size:1.25rem;font-weight:var(--fw-bold);
                            color:<?= $clr ?>;line-height:1;">
                    <?= formatAngka($val) ?>
                </div>
                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">
                    <?= $lbl ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Siswa aktif bar -->
        <?php if ($ang['total_siswa'] > 0): ?>
        <div style="margin-bottom:var(--space-4);">
            <?php $pctAktif = round(($ang['siswa_aktif'] / $ang['total_siswa']) * 100); ?>
            <div class="flex items-center justify-between mb-1"
                 style="font-size:11px;color:var(--text-muted);">
                <span><?= $ang['siswa_aktif'] ?> siswa aktif</span>
                <span><?= $pctAktif ?>%</span>
            </div>
            <div style="height:4px;background:var(--neutral-100);border-radius:99px;overflow:hidden;">
                <div style="height:100%;width:<?= $pctAktif ?>%;
                            background:var(--primary);border-radius:99px;"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div class="flex items-center justify-between" style="border-top:1px solid var(--border-color);padding-top:var(--space-4);">
            <a href="<?= url('modules/siswa/index.php?angkatan_id=' . $ang['id']) ?>"
               class="btn btn-ghost btn-sm" style="font-size:var(--text-xs);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" width="13" height="13"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
                Lihat Siswa
            </a>

            <?php if (isSuperAdmin()): ?>
            <div class="flex gap-1">
                <a href="<?= url('modules/angkatan/edit.php?id=' . $ang['id']) ?>"
                   class="btn btn-icon btn-icon-sm btn-ghost" title="Edit Angkatan">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" width="14" height="14"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </a>

                <?php if ($ang['total_siswa'] == 0): ?>
                <button class="btn btn-icon btn-icon-sm btn-ghost"
                        title="Hapus Angkatan"
                        data-confirm
                        data-type="danger"
                        data-title="Hapus Angkatan"
                        data-message="Yakin ingin menghapus angkatan <?= e(addslashes($ang['nama'])) ?>?"
                        data-action="<?= url('modules/angkatan/delete.php') ?>"
                        data-id="<?= $ang['id'] ?>"
                        data-label="Ya, Hapus"
                        style="color:var(--color-danger);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" width="14" height="14"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                        <path d="M10 11v6"/><path d="M14 11v6"/>
                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                    </svg>
                </button>
                <?php else: ?>
                <button class="btn btn-icon btn-icon-sm btn-ghost"
                        title="Tidak bisa dihapus — masih ada <?= $ang['total_siswa'] ?> siswa"
                        disabled style="opacity:0.3;cursor:not-allowed;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" width="14" height="14"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>