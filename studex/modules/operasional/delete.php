<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/GoogleDrive.php';

requireAdmin();
Router::requirePost();

$id = sanitizeInt(post('id'));

if (!$id) {
    setFlash('error', 'ID tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

$db = db();

// Ambil data kegiatan
$stmt = $db->prepare("SELECT * FROM operasional WHERE id = ?");
$stmt->execute([$id]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

// Hapus file laporan dari Drive jika ada
$laporanList = $db->prepare("SELECT * FROM operasional_laporan WHERE operasional_id = ?");
$laporanList->execute([$id]);
$laporanList = $laporanList->fetchAll();

if (GoogleDrive::isEnabled()) {
    foreach ($laporanList as $lap) {
        if (!empty($lap['drive_file_id'])) {
            try {
                GoogleDrive::delete($lap['drive_file_id']);
            } catch (Exception $e) {
                // Lanjut meski gagal hapus di Drive
            }
        }
    }
}

// Hapus file lokal laporan
foreach ($laporanList as $lap) {
    if (!empty($lap['path_lokal'])) {
        $fullPath = ROOT_PATH . '/' . ltrim($lap['path_lokal'], '/');
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}

// Hapus semua relasi (cascade manual)
$relatedTables = [
    'operasional_checklist',
    'operasional_laporan',
    'operasional_perlengkapan',
    'operasional_peserta',
    'operasional_pra',
];

foreach ($relatedTables as $tbl) {
    $db->prepare("DELETE FROM $tbl WHERE operasional_id = ?")->execute([$id]);
}

// Hapus kegiatan utama
$db->prepare("DELETE FROM operasional WHERE id = ?")->execute([$id]);

setFlash('success', 'Kegiatan "' . $ops['nama_kegiatan'] . '" berhasil dihapus.');
redirect(url('modules/operasional/index.php'));