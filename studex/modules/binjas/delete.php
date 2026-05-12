<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireAdmin();
Router::requirePost();

$id = sanitizeInt(post('id'));

if (!$id) {
    setFlash('error', 'ID sesi tidak valid.');
    redirect(url('modules/binjas/index.php'));
}

$db = db();

$stmt = $db->prepare("SELECT * FROM binjas_sesi WHERE id = ?");
$stmt->execute([$id]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Sesi tidak ditemukan.');
    redirect(url('modules/binjas/index.php'));
}

// Hapus semua relasi
// 1. Skor
$db->prepare("DELETE FROM binjas_skor WHERE sesi_id = ?")->execute([$id]);

// 2. Presensi terkait sesi ini
$db->prepare("
    DELETE FROM presensi
    WHERE modul = 'binjas' AND referensi_id = ?
")->execute([$id]);

// 3. Sesi
$db->prepare("DELETE FROM binjas_sesi WHERE id = ?")->execute([$id]);

setFlash('success', 'Sesi "' . $sesi['nama_sesi'] . '" berhasil dihapus.');
redirect(url('modules/binjas/index.php'));