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
$aksi  = sanitize(post('aksi'));

if (!$opsId) {
    setFlash('error', 'ID kegiatan tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

$stmt = $db->prepare("SELECT * FROM operasional WHERE id = ?");
$stmt->execute([$opsId]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

$redirectUrl = url('modules/operasional/detail.php?id=' . $opsId);

switch ($aksi) {

    // ── Mulai fase Operasional (dari Pra) ────────────────────────────────
    case 'mulai_ops':
        if ($ops['fase'] !== 'pra') {
            setFlash('error', 'Kegiatan tidak berada di fase Pra-Operasional.');
            break;
        }
        if ($ops['status'] === 'dibatalkan') {
            setFlash('error', 'Kegiatan yang dibatalkan tidak dapat dilanjutkan.');
            break;
        }

        $db->prepare("
            UPDATE operasional
            SET fase       = 'operasional',
                status     = 'aktif',
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$opsId]);

        setFlash('success', 'Kegiatan berhasil masuk ke fase Operasional.');
        $redirectUrl = url('modules/operasional/ops/index.php?ops_id=' . $opsId);
        break;

    // ── Selesai Operasional → masuk Pasca ────────────────────────────────
    case 'mulai_pasca':
        if ($ops['fase'] !== 'operasional') {
            setFlash('error', 'Kegiatan tidak berada di fase Operasional.');
            break;
        }

        $db->prepare("
            UPDATE operasional
            SET fase       = 'pasca',
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$opsId]);

        setFlash('success', 'Kegiatan berhasil masuk ke fase Pasca-Operasional.');
        $redirectUrl = url('modules/operasional/pasca/index.php?ops_id=' . $opsId);
        break;

    // ── Tandai Selesai (dari Pasca) ───────────────────────────────────────
    case 'selesai':
        if ($ops['fase'] !== 'pasca') {
            setFlash('error', 'Kegiatan harus berada di fase Pasca-Operasional untuk diselesaikan.');
            break;
        }

        $db->prepare("
            UPDATE operasional
            SET status     = 'selesai',
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$opsId]);

        setFlash('success', 'Kegiatan berhasil ditandai sebagai Selesai.');
        break;

    // ── Batalkan kegiatan ─────────────────────────────────────────────────
    case 'batalkan':
        if ($ops['status'] === 'selesai') {
            setFlash('error', 'Kegiatan yang sudah selesai tidak dapat dibatalkan.');
            break;
        }
        if ($ops['status'] === 'dibatalkan') {
            setFlash('info', 'Kegiatan sudah dibatalkan sebelumnya.');
            break;
        }

        $db->prepare("
            UPDATE operasional
            SET status     = 'dibatalkan',
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$opsId]);

        setFlash('success', 'Kegiatan berhasil dibatalkan.');
        break;

    // ── Aktifkan kembali (dari draft) ─────────────────────────────────────
    case 'aktifkan':
        if ($ops['status'] !== 'draft') {
            setFlash('error', 'Hanya kegiatan berstatus Draft yang dapat diaktifkan.');
            break;
        }

        $db->prepare("
            UPDATE operasional
            SET status     = 'aktif',
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$opsId]);

        setFlash('success', 'Kegiatan berhasil diaktifkan.');
        break;

    default:
        setFlash('error', 'Aksi tidak dikenal: ' . e($aksi));
        break;
}

redirect($redirectUrl);