<?php
// ============================================================
//  STUDEX — Student Index
//  modules/settings/save.php — Handler Simpan Pengaturan
//  POST only — dipanggil dari settings/index.php
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Router.php';

requireSuperAdmin();
Router::requirePost(); // method POST + verifyCsrf() sekaligus

$db     = db();
$action = sanitize(post('action'));
$userId = (int)$_SESSION['user_id'];

// ============================================================
// Router aksi
// ============================================================
switch ($action) {

    // ── 1. Simpan semua settings (bulk) ───────────────────────
    case 'save_general':
    default:
        $updates = $_POST['settings'] ?? [];

        if (!is_array($updates) || empty($updates)) {
            setFlash('error', 'Tidak ada data yang dikirim.');
            redirect(url('modules/settings/index.php'));
        }

        $saved  = 0;
        $errors = [];

        foreach ($updates as $key => $value) {
            $key = sanitize($key);

            // Whitelist: hanya update key yang benar-benar ada di DB
            $cek = $db->prepare("SELECT id, tipe FROM settings WHERE setting_key = ?");
            $cek->execute([$key]);
            $existing = $cek->fetch();

            if (!$existing) continue; // skip key asing / injection attempt

            // Normalisasi nilai per tipe
            $tipe = $existing['tipe'] ?? 'text';
            switch ($tipe) {
                case 'boolean':
                    // Checkbox: value '1' atau '0'
                    $value = in_array($value, ['1', 1, true], true) ? '1' : '0';
                    break;
                case 'number':
                    $value = is_numeric($value) ? (string)(float)$value : '0';
                    break;
                case 'json':
                    // Pastikan valid JSON sebelum simpan
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = "Setting '{$key}' bukan JSON valid, dilewati.";
                        continue 2;
                    }
                    $value = json_encode($decoded); // re-encode untuk normalisasi
                    break;
                default:
                    $value = sanitize($value);
                    break;
            }

            $db->prepare("UPDATE settings
                          SET setting_value = ?, updated_by = ?, updated_at = NOW()
                          WHERE setting_key = ?")
               ->execute([$value, $userId, $key]);

            $saved++;
        }

        if (!empty($errors)) {
            setFlash('warning', implode('<br>', $errors));
        } else {
            setFlash('success', "{$saved} pengaturan berhasil disimpan.");
        }

        redirect(url('modules/settings/index.php'));
        break;

    // ── 2. Reset satu setting ke default ──────────────────────
    case 'reset_key':
        $key = sanitize(post('key'));

        if (!$key) {
            setFlash('error', 'Key tidak valid.');
            redirect(url('modules/settings/index.php'));
        }

        $cek = $db->prepare("SELECT id, default_value FROM settings WHERE setting_key = ?");
        $cek->execute([$key]);
        $row = $cek->fetch();

        if (!$row) {
            setFlash('error', "Setting '{$key}' tidak ditemukan.");
            redirect(url('modules/settings/index.php'));
        }

        $db->prepare("UPDATE settings
                      SET setting_value = default_value, updated_by = ?, updated_at = NOW()
                      WHERE setting_key = ?")
           ->execute([$userId, $key]);

        setFlash('success', "Setting '{$key}' berhasil direset ke nilai default.");
        redirect(url('modules/settings/index.php'));
        break;

    // ── 3. Reset SEMUA settings ke default ────────────────────
    case 'reset_all':
        $db->prepare("UPDATE settings
                      SET setting_value = default_value, updated_by = ?, updated_at = NOW()")
           ->execute([$userId]);

        setFlash('success', 'Semua pengaturan berhasil direset ke nilai default.');
        redirect(url('modules/settings/index.php'));
        break;

    // ── 4. Simpan konfigurasi Drive (dari drive_config.php) ───
    case 'save_drive':
        $modulList = ['rabuan', 'mentoring', 'operasional', 'binjas', 'umum'];
        $saved     = 0;

        foreach ($modulList as $modulKey) {
            $folderId   = sanitize(post("folder_id_{$modulKey}"));
            $folderName = sanitize(post("folder_name_{$modulKey}"));
            $folderUrl  = $folderId
                ? "https://drive.google.com/drive/folders/{$folderId}"
                : null;
            $isAktif    = post("aktif_{$modulKey}") ? 1 : 0;

            $cek = $db->prepare("SELECT id FROM drive_config WHERE modul = ?");
            $cek->execute([$modulKey]);

            if ($cek->fetchColumn()) {
                $db->prepare("UPDATE drive_config
                              SET folder_id = ?, folder_name = ?, folder_url = ?,
                                  is_aktif = ?, updated_by = ?, updated_at = NOW()
                              WHERE modul = ?")
                   ->execute([
                       $folderId, $folderName ?: null, $folderUrl,
                       $isAktif, $userId, $modulKey,
                   ]);
            } else {
                $db->prepare("INSERT INTO drive_config
                                  (modul, folder_id, folder_name, folder_url, is_aktif, updated_by)
                              VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([
                       $modulKey, $folderId, $folderName ?: null,
                       $folderUrl, $isAktif, $userId,
                   ]);
            }
            $saved++;
        }

        setFlash('success', "Konfigurasi Drive berhasil disimpan untuk {$saved} modul.");
        redirect(url('modules/settings/drive_config.php'));
        break;
}