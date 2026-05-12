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

$db     = db();
$opsId  = sanitizeInt(post('ops_id'));
$action = sanitize(post('action'));

if (!$opsId) {
    setFlash('error', 'ID kegiatan tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

// Pastikan kegiatan ada & fase pasca
$stmt = $db->prepare("SELECT * FROM operasional WHERE id = ?");
$stmt->execute([$opsId]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

$redirectUrl = url('modules/operasional/pasca/index.php?ops_id=' . $opsId);

// ── Aksi: Tambah item checklist manual ───────────────────────────────────
if ($action === 'tambah_item') {
    verifyCsrf();

    $namaItem = sanitize(post('nama_item'));
    $kondisi  = sanitize(post('kondisi', 'layak'));
    $jumlah   = max(1, sanitizeInt(post('jumlah', 1)));
    $catatan  = sanitize(post('catatan'));

    if (!$namaItem) {
        setFlash('error', 'Nama item wajib diisi.');
        redirect($redirectUrl);
    }

    $validKondisi = ['layak', 'tidak_layak', 'butuh_perbaikan'];
    if (!in_array($kondisi, $validKondisi)) $kondisi = 'layak';

    $db->prepare("
        INSERT INTO operasional_checklist
            (operasional_id, nama_item, kondisi, jumlah, catatan, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([$opsId, $namaItem, $kondisi, $jumlah, $catatan]);

    setFlash('success', 'Item checklist berhasil ditambahkan.');
    redirect($redirectUrl);
}

// ── Aksi: Update kondisi item ─────────────────────────────────────────────
if ($action === 'update_kondisi') {
    verifyCsrf();

    $checklistId = sanitizeInt(post('checklist_id'));
    $kondisi     = sanitize(post('kondisi', 'layak'));

    if (!$checklistId) {
        setFlash('error', 'ID item tidak valid.');
        redirect($redirectUrl);
    }

    $validKondisi = ['layak', 'tidak_layak', 'butuh_perbaikan'];
    if (!in_array($kondisi, $validKondisi)) $kondisi = 'layak';

    // Pastikan item milik kegiatan ini
    $check = $db->prepare("SELECT id FROM operasional_checklist WHERE id = ? AND operasional_id = ?");
    $check->execute([$checklistId, $opsId]);
    if (!$check->fetch()) {
        setFlash('error', 'Item tidak ditemukan.');
        redirect($redirectUrl);
    }

    $db->prepare("
        UPDATE operasional_checklist
        SET kondisi = ?, updated_at = NOW()
        WHERE id = ?
    ")->execute([$kondisi, $checklistId]);

    // Tidak perlu flash untuk update inline, langsung redirect
    redirect($redirectUrl);
}

// ── Aksi: Hapus item checklist ────────────────────────────────────────────
if ($action === 'hapus_item') {
    verifyCsrf();

    $checklistId = sanitizeInt(post('checklist_id'));

    if (!$checklistId) {
        setFlash('error', 'ID item tidak valid.');
        redirect($redirectUrl);
    }

    // Pastikan item milik kegiatan ini
    $check = $db->prepare("SELECT id FROM operasional_checklist WHERE id = ? AND operasional_id = ?");
    $check->execute([$checklistId, $opsId]);
    if (!$check->fetch()) {
        setFlash('error', 'Item tidak ditemukan.');
        redirect($redirectUrl);
    }

    $db->prepare("DELETE FROM operasional_checklist WHERE id = ?")->execute([$checklistId]);

    setFlash('success', 'Item checklist berhasil dihapus.');
    redirect($redirectUrl);
}

// ── Aksi: Import dari daftar perlengkapan ────────────────────────────────
if ($action === 'impor_perlengkapan') {
    verifyCsrf();

    // Ambil semua perlengkapan kegiatan ini
    $perlengkapan = $db->prepare("
        SELECT * FROM operasional_perlengkapan WHERE operasional_id = ?
    ");
    $perlengkapan->execute([$opsId]);
    $perlengkapan = $perlengkapan->fetchAll();

    if (empty($perlengkapan)) {
        setFlash('info', 'Tidak ada perlengkapan untuk diimpor.');
        redirect($redirectUrl);
    }

    // Ambil item yang sudah ada di checklist (hindari duplikat)
    $existing = $db->prepare("
        SELECT nama_item FROM operasional_checklist WHERE operasional_id = ?
    ");
    $existing->execute([$opsId]);
    $existingNames = array_column($existing->fetchAll(), 'nama_item');

    $stmt  = $db->prepare("
        INSERT INTO operasional_checklist
            (operasional_id, nama_item, kondisi, jumlah, catatan, created_at)
        VALUES (?, ?, 'layak', ?, NULL, NOW())
    ");
    $added = 0;

    foreach ($perlengkapan as $p) {
        // Skip jika nama sudah ada
        if (in_array($p['nama_item'], $existingNames)) continue;
        $stmt->execute([$opsId, $p['nama_item'], $p['jumlah'] ?? 1]);
        $existingNames[] = $p['nama_item'];
        $added++;
    }

    if ($added > 0) {
        setFlash('success', "$added item berhasil diimpor dari daftar perlengkapan.");
    } else {
        setFlash('info', 'Semua item perlengkapan sudah ada di checklist.');
    }

    redirect($redirectUrl);
}

// ── Aksi: Simpan semua checklist sekaligus (bulk) ─────────────────────────
if ($action === 'bulk_update') {
    verifyCsrf();

    $ids      = post('ids', []);
    $kondisis = post('kondisis', []);
    $catatans = post('catatans', []);

    if (!is_array($ids) || empty($ids)) {
        setFlash('error', 'Tidak ada data untuk disimpan.');
        redirect($redirectUrl);
    }

    $stmt = $db->prepare("
        UPDATE operasional_checklist
        SET kondisi = ?, catatan = ?, updated_at = NOW()
        WHERE id = ? AND operasional_id = ?
    ");

    $validKondisi = ['layak', 'tidak_layak', 'butuh_perbaikan'];
    $updated = 0;

    foreach ($ids as $idx => $cid) {
        $cid     = sanitizeInt($cid);
        $kondisi = sanitize($kondisis[$idx] ?? 'layak');
        $catatan = sanitize($catatans[$idx] ?? '');

        if (!$cid) continue;
        if (!in_array($kondisi, $validKondisi)) $kondisi = 'layak';

        $stmt->execute([$kondisi, $catatan, $cid, $opsId]);
        $updated += $stmt->rowCount();
    }

    setFlash('success', "$updated item checklist berhasil diperbarui.");
    redirect($redirectUrl);
}

// Fallback
setFlash('error', 'Aksi tidak dikenal.');
redirect($redirectUrl);