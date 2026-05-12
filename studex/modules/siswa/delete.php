<?php
// ============================================================
//  STUDEX — Student Index
//  modules/siswa/delete.php — Hapus Siswa
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Router.php';

requireLogin();
Router::requirePost();  // validasi POST + CSRF sekaligus

$db = db();
$id = sanitizeInt(post('id'));

if (!$id) {
    setFlash('error', 'ID siswa tidak valid.');
    redirect(url('modules/siswa/index.php'));
}

// Ambil data siswa dulu (untuk nama di flash message)
$stmt = $db->prepare("SELECT id, nama, nis FROM siswa WHERE id = ?");
$stmt->execute([$id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setFlash('error', 'Siswa tidak ditemukan.');
    redirect(url('modules/siswa/index.php'));
}

// ============================================================
// CEK RELASI SEBELUM HAPUS
// Cukup informasikan, karena foreign key CASCADE sudah handle
// delete otomatis untuk: presensi, binjas_skor,
// operasional_peserta
// ============================================================
$relasi = [];

$cekPresensi = $db->prepare("SELECT COUNT(*) FROM presensi WHERE siswa_id = ?");
$cekPresensi->execute([$id]);
$jumlahPresensi = (int)$cekPresensi->fetchColumn();
if ($jumlahPresensi > 0) {
    $relasi[] = $jumlahPresensi . ' data presensi';
}

$cekSkor = $db->prepare("SELECT COUNT(*) FROM binjas_skor WHERE siswa_id = ?");
$cekSkor->execute([$id]);
$jumlahSkor = (int)$cekSkor->fetchColumn();
if ($jumlahSkor > 0) {
    $relasi[] = $jumlahSkor . ' skor Binjas';
}

$cekOps = $db->prepare("SELECT COUNT(*) FROM operasional_peserta WHERE siswa_id = ?");
$cekOps->execute([$id]);
$jumlahOps = (int)$cekOps->fetchColumn();
if ($jumlahOps > 0) {
    $relasi[] = $jumlahOps . ' keikutsertaan operasional';
}

// ============================================================
// EKSEKUSI HAPUS
// Karena semua FK pakai ON DELETE CASCADE, cukup hapus siswa
// ============================================================
try {
    $db->prepare("DELETE FROM siswa WHERE id = ?")->execute([$id]);

    $pesanRelasi = !empty($relasi)
        ? ' Beserta ' . implode(', ', $relasi) . ' yang terkait.'
        : '';

    setFlash('success', 'Siswa ' . $siswa['nama'] . ' (NIS: ' . $siswa['nis'] . ') berhasil dihapus.' . $pesanRelasi);

} catch (\PDOException $e) {
    error_log('STUDEX delete siswa error: ' . $e->getMessage());
    setFlash('error', 'Gagal menghapus siswa. ' . ($e->getCode() == 23000
        ? 'Data masih digunakan oleh modul lain.'
        : 'Terjadi kesalahan server.'));
}

redirect(url('modules/siswa/index.php'));