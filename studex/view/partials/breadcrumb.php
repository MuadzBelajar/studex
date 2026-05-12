<?php
// ============================================================
//  STUDEX — Student Index
//  view/partials/breadcrumb.php
//  Cara pakai — set $breadcrumbs sebelum include layout:
//
//  $breadcrumbs = [
//      ['label' => 'Dashboard',  'url' => url('modules/dashboard/index.php')],
//      ['label' => 'Rabuan',     'url' => url('modules/rabuan/index.php')],
//      ['label' => 'Detail Rapat'], // item terakhir tanpa url = active
//  ];
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

// Default: minimal "Dashboard"
if (empty($breadcrumbs)) {
    $breadcrumbs = [['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')]];
}

$total = count($breadcrumbs);
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <?php $isLast = ($i === $total - 1); ?>

        <div class="breadcrumb-item <?= $isLast ? 'active' : '' ?>">
            <?php if (!$isLast && !empty($crumb['url'])): ?>
                <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
            <?php else: ?>
                <span><?= e($crumb['label']) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$isLast): ?>
            <div class="breadcrumb-item">
                <!-- Chevron separator -->
                <svg class="breadcrumb-sep" xmlns="http://www.w3.org/2000/svg"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </div>
        <?php endif; ?>

    <?php endforeach; ?>
</nav>