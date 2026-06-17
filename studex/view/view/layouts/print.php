<?php
// ============================================================
//  STUDEX — Student Index
//  view/layouts/print.php — Layout Cetak / Print
//  Cara pakai:
//    $pageTitle    = 'Rekap Presensi';
//    $printMeta    = ['Angkatan' => 'ANG-2024', 'Periode' => 'Juli 2025'];
//    $printBy      = $user['nama']; // opsional
//    ob_start();
//    // ... konten tabel/laporan ...
//    $content = ob_get_clean();
//    include ROOT_PATH . '/view/layouts/print.php';
// ============================================================

defined('STUDEX') or die('Direct access not permitted');
requireLogin();

$pageTitle  = $pageTitle  ?? 'Laporan';
$printMeta  = $printMeta  ?? [];   // array key => value untuk info header
$printBy    = $printBy    ?? currentUser()['nama'];
$printDate  = date('d F Y, H:i') . ' WIB';
$orientation = $orientation ?? 'portrait'; // portrait | landscape
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — STUDEX</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ======================================================
           PRINT RESET & BASE
           ====================================================== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            background: #fff;
            line-height: 1.5;
        }

        a { color: inherit; text-decoration: none; }
        ul, ol { list-style: none; }
        img { max-width: 100%; }

        /* ======================================================
           PAGE SIZE
           ====================================================== */
        @page {
            size: A4 <?= $orientation ?>;
            margin: 16mm 18mm 16mm 18mm;
        }

        @media print {
            html, body {
                width: 210mm;
                min-height: 297mm;
            }

            .no-print    { display: none !important; }
            .page-break  { page-break-after: always; break-after: page; }
            .avoid-break { page-break-inside: avoid; break-inside: avoid; }

            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }

        /* ======================================================
           PRINT WRAPPER
           ====================================================== */
        .print-wrapper {
            max-width: 794px; /* A4 width px */
            margin: 0 auto;
            padding: 24px 0;
        }

        /* ======================================================
           PRINT HEADER
           ====================================================== */
        .print-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 2.5px solid #1a1a1a;
            margin-bottom: 18px;
            gap: 16px;
        }

        .print-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .print-logo-box {
            width: 44px;
            height: 44px;
            background-color: #395917;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: -0.5px;
            flex-shrink: 0;
        }

        .print-app-name {
            font-size: 18pt;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.1;
            letter-spacing: -0.5px;
        }

        .print-app-tagline {
            font-size: 9pt;
            color: #666;
            margin-top: 2px;
        }

        .print-header-right {
            text-align: right;
        }

        .print-doc-title {
            font-size: 13pt;
            font-weight: 600;
            color: #1a1a1a;
            line-height: 1.3;
        }

        .print-doc-subtitle {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }

        /* ======================================================
           PRINT META INFO (key: value rows)
           ====================================================== */
        .print-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px 16px;
            background-color: #f5f5f5;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 9.5pt;
        }

        .print-meta-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .print-meta-label {
            font-weight: 600;
            color: #555;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .print-meta-value {
            color: #1a1a1a;
            font-weight: 500;
        }

        /* ======================================================
           PRINT SECTION TITLE
           ====================================================== */
        .print-section-title {
            font-size: 11pt;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ddd;
        }

        /* ======================================================
           PRINT TABLE
           ====================================================== */
        .print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 16px;
        }

        .print-table thead tr {
            background-color: #395917;
            color: #fff;
        }

        .print-table thead th {
            padding: 7px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 8.5pt;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        .print-table thead th.center { text-align: center; }
        .print-table thead th.right  { text-align: right; }

        .print-table tbody tr {
            border-bottom: 1px solid #e8e8e8;
        }

        .print-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .print-table tbody td {
            padding: 6px 10px;
            color: #1a1a1a;
            vertical-align: middle;
        }

        .print-table tbody td.center { text-align: center; }
        .print-table tbody td.right  { text-align: right; }

        .print-table tfoot tr {
            background-color: #eef3e8;
            border-top: 2px solid #395917;
        }

        .print-table tfoot td {
            padding: 7px 10px;
            font-weight: 600;
            font-size: 9.5pt;
        }

        /* Status badge cetak */
        .print-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .print-badge-success { background: #e8f5ee; color: #2d7a4f; border-color: #a8d5bc; }
        .print-badge-warning { background: #fff4e0; color: #c97c10; border-color: #f0c97a; }
        .print-badge-danger  { background: #fce8e6; color: #8b1408; border-color: #f0a9a0; }
        .print-badge-info    { background: #eaf4f5; color: #4c8c6a; border-color: #c1d8da; }
        .print-badge-grey    { background: #f0f0f0; color: #555;    border-color: #ccc; }

        /* ======================================================
           PRINT SUMMARY BOX
           ====================================================== */
        .print-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }

        .print-summary-item {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
        }

        .print-summary-value {
            font-size: 16pt;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.1;
        }

        .print-summary-label {
            font-size: 8pt;
            color: #777;
            margin-top: 3px;
        }

        .print-summary-item.green  .print-summary-value { color: #2d7a4f; }
        .print-summary-item.red    .print-summary-value { color: #8b1408; }
        .print-summary-item.orange .print-summary-value { color: #c97c10; }
        .print-summary-item.grey   .print-summary-value { color: #555;    }

        /* ======================================================
           PRINT SIGNATURE BLOCK
           ====================================================== */
        .print-signatures {
            display: flex;
            gap: 32px;
            margin-top: 32px;
            flex-wrap: wrap;
        }

        .print-sign-box {
            flex: 1;
            min-width: 150px;
            text-align: center;
        }

        .print-sign-title {
            font-size: 9pt;
            color: #555;
            margin-bottom: 48px; /* ruang TTD */
        }

        .print-sign-line {
            border-top: 1px solid #1a1a1a;
            padding-top: 4px;
        }

        .print-sign-name {
            font-size: 9.5pt;
            font-weight: 600;
            color: #1a1a1a;
        }

        .print-sign-role {
            font-size: 8.5pt;
            color: #666;
        }

        /* ======================================================
           PRINT FOOTER
           ====================================================== */
        .print-footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 8pt;
            color: #999;
        }

        .print-footer-left  { text-align: left; }
        .print-footer-right { text-align: right; }

        /* Page number via CSS counter */
        .print-footer-page::after {
            content: 'Halaman ' counter(page) ' dari ' counter(pages);
        }

        /* ======================================================
           NO-PRINT: Toolbar tombol cetak (hanya tampil di screen)
           ====================================================== */
        .print-toolbar {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            gap: 10px;
            z-index: 999;
        }

        .print-toolbar button {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-print {
            background-color: #395917;
            color: #fff;
            box-shadow: 0 4px 12px rgba(57,89,23,0.3);
        }

        .btn-print:hover { background-color: #2d4712; }

        .btn-back {
            background-color: #fff;
            color: #45515c;
            border: 1.5px solid #e0e3e6 !important;
        }

        .btn-back:hover { background-color: #f5f6f7; }

        .print-toolbar svg { width: 16px; height: 16px; }

        @media print {
            .print-toolbar { display: none; }
        }

        /* ======================================================
           UTILITY
           ====================================================== */
        .mt-4  { margin-top: 16px; }
        .mb-4  { margin-bottom: 16px; }
        .mt-6  { margin-top: 24px; }
        .mb-6  { margin-bottom: 24px; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .fw-bold     { font-weight: 700; }
        .fw-semibold { font-weight: 600; }
        .text-muted  { color: #777; }
        .text-green  { color: #395917; }

    </style>
</head>
<body>

<div class="print-wrapper">

    <!-- ============================================================
         PRINT HEADER
         ============================================================ -->
    <div class="print-header avoid-break">
        <div class="print-header-left">
            <div class="print-logo-box">STX</div>
            <div>
                <div class="print-app-name">STUDEX</div>
                <div class="print-app-tagline">Student Index — Sistem Monitoring Aktivitas Siswa</div>
            </div>
        </div>
        <div class="print-header-right">
            <div class="print-doc-title"><?= e($pageTitle) ?></div>
            <div class="print-doc-subtitle">Dicetak: <?= $printDate ?></div>
            <?php if ($printBy): ?>
                <div class="print-doc-subtitle">Oleh: <?= e($printBy) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================
         META INFO
         ============================================================ -->
    <?php if (!empty($printMeta)): ?>
    <div class="print-meta avoid-break">
        <?php foreach ($printMeta as $label => $value): ?>
        <div class="print-meta-item">
            <span class="print-meta-label"><?= e($label) ?></span>
            <span class="print-meta-value"><?= e($value) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         MAIN CONTENT
         ============================================================ -->
    <?= $content ?? '' ?>

    <!-- ============================================================
         PRINT FOOTER
         ============================================================ -->
    <div class="print-footer no-print-footer">
        <div class="print-footer-left">
            STUDEX — Student Index &copy; <?= date('Y') ?>
        </div>
        <div class="print-footer-right">
            <span class="print-footer-page"></span>
        </div>
    </div>

</div><!-- /.print-wrapper -->

<!-- ============================================================
     TOOLBAR (hanya di screen, hilang saat print)
     ============================================================ -->
<div class="print-toolbar no-print">
    <button class="btn-back" onclick="window.history.back()">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>
        Kembali
    </button>
    <button class="btn-print" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Cetak / Simpan PDF
    </button>
</div>

</body>
</html>