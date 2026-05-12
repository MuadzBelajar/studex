<?php
// ============================================================
//  STUDEX — Student Index
//  modules/mentoring/delete.php — Hapus Sesi Mentoring
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Router.php';
require_once __DIR__ . '/../../core/GoogleDrive.php';

requireLogin();
Router::requirePost();

$db = db();
$id = sanitizeInt(post('id'));

if (!$id) {
    setFlash('error', 'ID tidak valid.');
    redirect(url('modules/mentoring/index.php'));
}

// Cek exists
$stmt = $db->prepare("SELECT * FROM mentoring_sesi WHERE id = ?");
$stmt->execute([$id]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Data sesi mentoring tidak ditemukan.');
    redirect(url('modules/mentoring/index.php'));
}

// Ambil materi untuk hapus file
$materiStmt = $db->prepare("SELECT * FROM mentoring_materi WHERE sesi_id = ?");
$materiStmt->execute([$id]);
$materiList = $materiStmt->fetchAll();

// Hapus dari Google Drive jika aktif
if (GoogleDrive::isEnabled()) {
    foreach ($materiList as $m) {
        if (!empty($m['drive_file_id'])) {
            try {
                GoogleDrive::delete($m['drive_file_id']);
            } catch (Exception $e) {
                // Lanjutkan meski gagal hapus dari Drive
            }
        }
    }
}

// Hapus file lokal
foreach ($materiList as $m) {
    if (!empty($m['path_lokal']) && file_exists($m['path_lokal'])) {
        @unlink($m['path_lokal']);
    }
}

// Hapus dari DB (presensi & materi ikut terhapus via ON DELETE CASCADE)
$db->prepare("DELETE FROM mentoring_sesi WHERE id = ?")->execute([$id]);

setFlash('success', 'Sesi mentoring "' . $sesi['judul'] . '" berhasil dihapus.');
redirect(url('modules/mentoring/index.php'));