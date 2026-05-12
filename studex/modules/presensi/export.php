<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db = db();

// ── Validasi params ────────────────────────────────────────────
$modul      = in_array($_GET['modul'] ?? '', ['rabuan','mentoring','binjas'])
              ? $_GET['modul'] : '';
$angkatanId = sanitizeInt($_GET['angkatan_id'] ?? 0);

if (!$modul || !$angkatanId) {
    setFlash('error', 'Parameter tidak lengkap.');
    redirect(url('modules/presensi/rekap.php'));
}

// ── Nama angkatan ──────────────────────────────────────────────
$stmtA  = $db->prepare("SELECT nama_angkatan FROM angkatan WHERE id=?");
$stmtA->execute([$angkatanId]);
$angNama = $stmtA->fetchColumn() ?: 'Angkatan';

// ── Daftar sesi ────────────────────────────────────────────────
$sesiList = [];
switch ($modul) {
    case 'rabuan':
        $s = $db->prepare("SELECT id, judul AS nama, tanggal
                            FROM rabuan WHERE angkatan_id=? ORDER BY tanggal ASC");
        $s->execute([$angkatanId]);
        $sesiList = $s->fetchAll();
        break;
    case 'mentoring':
        $s = $db->prepare("SELECT id, judul AS nama, tanggal
                            FROM mentoring_sesi WHERE angkatan_id=? ORDER BY tanggal ASC");
        $s->execute([$angkatanId]);
        $sesiList = $s->fetchAll();
        break;
    case 'binjas':
        $s = $db->prepare("SELECT id, nama_sesi AS nama, tanggal
                            FROM binjas_sesi WHERE angkatan_id=? ORDER BY tanggal ASC");
        $s->execute([$angkatanId]);
        $sesiList = $s->fetchAll();
        break;
}

if (empty($sesiList)) {
    setFlash('error', 'Tidak ada sesi ditemukan untuk diekspor.');
    redirect(url("modules/presensi/rekap.php?modul={$modul}&angkatan_id={$angkatanId}"));
}

// ── Semua siswa aktif ──────────────────────────────────────────
$stmtS = $db->prepare("SELECT id, nama, nim FROM siswa
                        WHERE angkatan_id=? AND status='aktif' ORDER BY nama ASC");
$stmtS->execute([$angkatanId]);
$allSiswa = $stmtS->fetchAll();

if (empty($allSiswa)) {
    setFlash('error', 'Tidak ada siswa aktif pada angkatan ini.');
    redirect(url("modules/presensi/rekap.php?modul={$modul}&angkatan_id={$angkatanId}"));
}

// ── Semua presensi (1 query) ───────────────────────────────────
$sesiIds      = array_column($sesiList, 'id');
$placeholders = implode(',', array_fill(0, count($sesiIds), '?'));
$stmtP = $db->prepare(
    "SELECT siswa_id, referensi_id, status
     FROM presensi
     WHERE modul=? AND referensi_id IN ({$placeholders})"
);
$stmtP->execute(array_merge([$modul], $sesiIds));

$presensiMap = [];
foreach ($stmtP->fetchAll() as $p) {
    $presensiMap[$p['siswa_id']][$p['referensi_id']] = $p['status'];
}

// ── Label modul ───────────────────────────────────────────────
$modulLabel = ['rabuan' => 'Rabuan', 'mentoring' => 'Mentoring', 'binjas' => 'Binjas'];

// ── Nama file ─────────────────────────────────────────────────
$safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $angNama);
$filename = "rekap_presensi_{$modul}_{$safeName}_" . date('Ymd') . '.csv';

// ── Headers HTTP ──────────────────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// BOM agar Excel baca UTF-8 dengan benar
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// ── Baris info ────────────────────────────────────────────────
fputcsv($out, ['STUDEX — Rekap Presensi']);
fputcsv($out, ['Modul',    $modulLabel[$modul] ?? $modul]);
fputcsv($out, ['Angkatan', $angNama]);
fputcsv($out, ['Diekspor', date('d/m/Y H:i')]);
fputcsv($out, []); // baris kosong

// ── Header tabel ──────────────────────────────────────────────
$headerRow = ['#', 'NIM', 'Nama Siswa'];
foreach ($sesiList as $sesi) {
    // Kolom per sesi: label "dd/mm/yy - Nama Sesi"
    $headerRow[] = date('d/m/y', strtotime($sesi['tanggal'])) . ' - ' . $sesi['nama'];
}
$headerRow[] = 'Total Hadir';
$headerRow[] = 'Total Izin';
$headerRow[] = 'Total Sakit';
$headerRow[] = 'Total Alpha';
$headerRow[] = '% Hadir';
fputcsv($out, $headerRow);

// ── Baris data ────────────────────────────────────────────────
$totalSesi = count($sesiList);

foreach ($allSiswa as $idx => $siswa) {
    $row = [
        $idx + 1,
        $siswa['nim'],
        $siswa['nama'],
    ];

    $h = $i = $s = $a = 0;
    foreach ($sesiList as $sesi) {
        $st = $presensiMap[$siswa['id']][$sesi['id']] ?? 'alpha';
        // Teks pendek agar terbaca di spreadsheet
        $row[] = match ($st) {
            'hadir' => 'H',
            'izin'  => 'I',
            'sakit' => 'S',
            default => 'A',
        };
        if ($st === 'hadir')      $h++;
        elseif ($st === 'izin')   $i++;
        elseif ($st === 'sakit')  $s++;
        else                      $a++;
    }

    $pct   = $totalSesi > 0 ? round($h / $totalSesi * 100) : 0;
    $row[] = $h;
    $row[] = $i;
    $row[] = $s;
    $row[] = $a;
    $row[] = $pct . '%';

    fputcsv($out, $row);
}

// ── Baris total per sesi (footer) ─────────────────────────────
fputcsv($out, []); // baris kosong
$footerRow = ['', '', 'Total Hadir per Sesi'];
foreach ($sesiList as $sesi) {
    $hadirCount = 0;
    foreach ($allSiswa as $siswa) {
        if (($presensiMap[$siswa['id']][$sesi['id']] ?? 'alpha') === 'hadir') {
            $hadirCount++;
        }
    }
    $footerRow[] = $hadirCount;
}
// Isi kolom summary footer (kosong)
$footerRow[] = '';
$footerRow[] = '';
$footerRow[] = '';
$footerRow[] = '';
$footerRow[] = '';
fputcsv($out, $footerRow);

// ── Keterangan ────────────────────────────────────────────────
fputcsv($out, []);
fputcsv($out, ['Keterangan: H = Hadir | I = Izin | S = Sakit | A = Alpha']);

fclose($out);
exit;