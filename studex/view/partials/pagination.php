<?php
// ============================================================
//  STUDEX — Student Index
//  view/partials/pagination.php
//  Cara pakai:
//    $paging = paginate($totalRows, 15); // dari Helpers.php
//    include ROOT_PATH . '/view/partials/pagination.php';
//
//  Otomatis baca $_GET untuk query string lain (filter, search, dll)
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

if (empty($paging) || $paging['total_pages'] <= 1) return;

$currentPage = $paging['page'];
$totalPages  = $paging['total_pages'];
$total       = $paging['total'];
$perPage     = $paging['per_page'];
$offset      = $paging['offset'];

// Pertahankan query string selain 'page'
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

// Fungsi bantu buat URL halaman
function pageUrl(int $page, string $qs): string {
    return '?' . 'page=' . $page . $qs;
}

// Hitung range halaman yang ditampilkan (max 5 nomor)
$range    = 2;
$start    = max(1, $currentPage - $range);
$end      = min($totalPages, $currentPage + $range);

// Pastikan selalu tampil 5 nomor kalau memungkinkan
if (($end - $start) < ($range * 2)) {
    if ($start === 1) {
        $end = min($totalPages, $start + ($range * 2));
    } elseif ($end === $totalPages) {
        $start = max(1, $end - ($range * 2));
    }
}

// Info range data yang ditampilkan
$from = $offset + 1;
$to   = min($offset + $perPage, $total);
?>

<div class="flex items-center justify-between flex-wrap gap-3 mt-5" style="padding-top: var(--space-4);">

    <!-- Info -->
    <div class="pagination-info" style="font-size: var(--text-sm); color: var(--text-muted);">
        Menampilkan <strong style="color: var(--text-primary);"><?= $from ?>–<?= $to ?></strong>
        dari <strong style="color: var(--text-primary);"><?= number_format($total) ?></strong> data
    </div>

    <!-- Pagination -->
    <nav class="pagination" aria-label="Navigasi halaman">

        <!-- Pertama -->
        <?php if ($currentPage > 2): ?>
            <div class="page-item">
                <a href="<?= pageUrl(1, $queryString) ?>" class="page-link" title="Halaman pertama" aria-label="Pertama">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="11 17 6 12 11 7"/>
                        <polyline points="18 17 13 12 18 7"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>

        <!-- Sebelumnya -->
        <div class="page-item">
            <a href="<?= $currentPage > 1 ? pageUrl($currentPage - 1, $queryString) : '#' ?>"
               class="page-link <?= $currentPage <= 1 ? 'disabled' : '' ?>"
               aria-label="Sebelumnya"
               <?= $currentPage <= 1 ? 'aria-disabled="true"' : '' ?>>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>
        </div>

        <!-- Elipsis kiri -->
        <?php if ($start > 1): ?>
            <div class="page-item">
                <span class="page-link disabled" style="letter-spacing:2px; font-size:10px;">···</span>
            </div>
        <?php endif; ?>

        <!-- Nomor halaman -->
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <div class="page-item">
                <a href="<?= pageUrl($i, $queryString) ?>"
                   class="page-link <?= $i === $currentPage ? 'active' : '' ?>"
                   aria-label="Halaman <?= $i ?>"
                   <?= $i === $currentPage ? 'aria-current="page"' : '' ?>>
                    <?= $i ?>
                </a>
            </div>
        <?php endfor; ?>

        <!-- Elipsis kanan -->
        <?php if ($end < $totalPages): ?>
            <div class="page-item">
                <span class="page-link disabled" style="letter-spacing:2px; font-size:10px;">···</span>
            </div>
        <?php endif; ?>

        <!-- Selanjutnya -->
        <div class="page-item">
            <a href="<?= $currentPage < $totalPages ? pageUrl($currentPage + 1, $queryString) : '#' ?>"
               class="page-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
               aria-label="Selanjutnya"
               <?= $currentPage >= $totalPages ? 'aria-disabled="true"' : '' ?>>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>

        <!-- Terakhir -->
        <?php if ($currentPage < $totalPages - 1): ?>
            <div class="page-item">
                <a href="<?= pageUrl($totalPages, $queryString) ?>" class="page-link" title="Halaman terakhir" aria-label="Terakhir">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="13 17 18 12 13 7"/>
                        <polyline points="6 17 11 12 6 7"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>

    </nav>

</div>