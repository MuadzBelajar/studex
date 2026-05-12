<?php
define('STUDEX', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/google_drive.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Helpers.php';

requireAdmin();
Router::requirePost();

$db    = db();
$opsId = sanitizeInt(post('ops_id'));
$action = sanitize(post('action', 'save_pra'));

if (!$opsId) {
    setFlash('error', 'ID kegiatan tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

// Pastikan kegiatan ada
$stmt = $db->prepare("SELECT id FROM operasional WHERE id = ?");
$stmt->execute([$opsId]);
if (!$stmt->fetch()) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

// ── Aksi: Simpan data pra ──────────────────────────────────────────────────
if ($action === 'save_pra') {
    verifyCsrf();

    $tujuan           = sanitize(post('tujuan'));
    $rencana_rute     = sanitize(post('rencana_rute'));
    $jumlah_personel  = sanitizeInt(post('jumlah_personel')) ?: null;
    $anggaran         = sanitizeInt(post('anggaran')) ?: null;
    $catatan_briefing = sanitize(post('catatan_briefing'));
    $kontak_darurat   = sanitize(post('kontak_darurat'));

    // Cek apakah sudah ada record pra
    $existing = $db->prepare("SELECT id FROM operasional_pra WHERE operasional_id = ?");
    $existing->execute([$opsId]);
    $existing = $existing->fetch();

    if ($existing) {
        // Update
        $db->prepare("
            UPDATE operasional_pra
            SET tujuan           = ?,
                rencana_rute     = ?,
                jumlah_personel  = ?,
                anggaran         = ?,
                catatan_briefing = ?,
                kontak_darurat   = ?,
                updated_at       = NOW()
            WHERE operasional_id = ?
        ")->execute([
            $tujuan, $rencana_rute, $jumlah_personel,
            $anggaran, $catatan_briefing, $kontak_darurat,
            $opsId,
        ]);
    } else {
        // Insert
        $db->prepare("
            INSERT INTO operasional_pra
                (operasional_id, tujuan, rencana_rute, jumlah_personel,
                 anggaran, catatan_briefing, kontak_darurat, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $opsId, $tujuan, $rencana_rute, $jumlah_personel,
            $anggaran, $catatan_briefing, $kontak_darurat,
        ]);
    }

    setFlash('success', 'Data perencanaan berhasil disimpan.');
    redirect(url('modules/operasional/pra/index.php?ops_id=' . $opsId));
}

// ── Aksi: Tambah perlengkapan ─────────────────────────────────────────────
if ($action === 'add_perlengkapan') {
    verifyCsrf();

    $namaItem = sanitize(post('nama_item'));
    $jenis    = sanitize(post('jenis', 'pribadi'));
    $jumlah   = max(1, sanitizeInt(post('jumlah', 1)));

    if (!$namaItem) {
        setFlash('error', 'Nama item wajib diisi.');
        redirect(url('modules/operasional/pra/index.php?ops_id=' . $opsId));
    }

    if (!in_array($jenis, ['pribadi', 'regu'])) $jenis = 'pribadi';

    $db->prepare("
        INSERT INTO operasional_perlengkapan
            (operasional_id, nama_item, jenis, jumlah, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ")->execute([$opsId, $namaItem, $jenis, $jumlah]);

    setFlash('success', 'Item perlengkapan berhasil ditambahkan.');
    redirect(url('modules/operasional/pra/index.php?ops_id=' . $opsId));
}

// ── Aksi: Hapus perlengkapan ──────────────────────────────────────────────
if ($action === 'delete_perlengkapan') {
    verifyCsrf();

    $perlengkapanId = sanitizeInt(post('perlengkapan_id'));

    if (!$perlengkapanId) {
        setFlash('error', 'ID perlengkapan tidak valid.');
        redirect(url('modules/operasional/pra/index.php?ops_id=' . $opsId));
    }

    // Pastikan item milik kegiatan ini
    $check = $db->prepare("SELECT id FROM operasional_perlengkapan WHERE id = ? AND operasional_id = ?");
    $check->execute([$perlengkapanId, $opsId]);
    if (!$check->fetch()) {
        setFlash('error', 'Item tidak ditemukan.');
        redirect(url('modules/operasional/pra/index.php?ops_id=' . $opsId));
    }

    $db->prepare("DELETE FROM operasional_perlengkapan WHERE id = ?")->execute([$perlengkapanId]);

    setFlash('success', 'Item perlengkapan berhasil dihapus.');
    redirect(url('modules/operasional/pra/index.php?ops_id=' . $opsId));
}

// Fallback
setFlash('error', 'Aksi tidak dikenal.');
redirect(url('modules/operasional/pra/index.php?ops_id=' . $opsId));