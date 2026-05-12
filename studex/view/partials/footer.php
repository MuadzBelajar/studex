<?php
// ============================================================
//  STUDEX — Student Index
//  view/partials/footer.php
// ============================================================

defined('STUDEX') or die('Direct access not permitted');
?>

<footer style="
    padding: var(--space-4) var(--space-8);
    border-top: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--space-2);
    background-color: var(--bg-app);
" role="contentinfo">

    <span style="font-size: var(--text-xs); color: var(--text-muted);">
        &copy; <?= date('Y') ?> <strong style="color: var(--text-secondary);">STUDEX</strong>
        — Student Index. All rights reserved.
    </span>

    <span style="font-size: var(--text-xs); color: var(--text-muted);">
        v<?= APP_VERSION ?> &nbsp;·&nbsp; <?= date('d M Y') ?>
    </span>

</footer>