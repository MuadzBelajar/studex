<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/GoogleDrive.php';

requireSuperAdmin();

$db = db();

// ── Daftar modul yang punya folder Drive ──────────────────────
$modulList = [
    'rabuan'      => ['label' => 'Rabuan',      'icon' => '📋', 'desc' => 'Folder untuk notulensi PDF Rabuan'],
    'mentoring'   => ['label' => 'Mentoring',   'icon' => '📚', 'desc' => 'Folder untuk materi file Mentoring'],
    'operasional' => ['label' => 'Operasional', 'icon' => '🗂️', 'desc' => 'Folder untuk laporan PDF Operasional'],
    'binjas'      => ['label' => 'Binjas',      'icon' => '💪', 'desc' => 'Folder untuk dokumen Binjas'],
];

// ── Ambil config Drive saat ini ───────────────────────────────
$stmt       = $db->query("SELECT modul, folder_id, folder_name, is_active FROM drive_config ORDER BY modul");
$driveRows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$driveMap   = [];
foreach ($driveRows as $r) {
    $driveMap[$r['modul']] = $r;
}

// ── Handle POST: simpan folder IDs ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = post('action');

    // ── Simpan folder ID ──────────────────────────────────────
    if ($action === 'save_folders') {
        $saved = 0;
        foreach ($modulList as $modulKey => $_) {
            $folderId   = sanitize(post("folder_id_{$modulKey}"));
            $folderName = sanitize(post("folder_name_{$modulKey}"));
            $isActive   = post("active_{$modulKey}") ? 1 : 0;

            // Upsert
            $cek = $db->prepare("SELECT id FROM drive_config WHERE modul=?");
            $cek->execute([$modulKey]);
            if ($cek->fetchColumn()) {
                $db->prepare("UPDATE drive_config
                              SET folder_id=?, folder_name=?, is_active=?, updated_at=NOW()
                              WHERE modul=?")
                   ->execute([$folderId, $folderName, $isActive, $modulKey]);
            } else {
                $db->prepare("INSERT INTO drive_config (modul, folder_id, folder_name, is_active)
                              VALUES (?,?,?,?)")
                   ->execute([$modulKey, $folderId, $folderName, $isActive]);
            }
            $saved++;
        }
        setFlash('success', "Konfigurasi Drive berhasil disimpan untuk {$saved} modul.");
        redirect(url('modules/settings/drive_config.php'));
    }

    // ── Test koneksi Drive ────────────────────────────────────
    if ($action === 'test_connection') {
        if (!GoogleDrive::isEnabled()) {
            setFlash('error', 'Google Drive tidak aktif. Pastikan file credentials sudah terpasang.');
        } else {
            $result = GoogleDrive::testConnection();
            if ($result['success']) {
                setFlash('success', '✅ Koneksi Google Drive berhasil! Service account aktif.');
            } else {
                setFlash('error', '❌ Koneksi gagal: ' . $result['message']);
            }
        }
        redirect(url('modules/settings/drive_config.php'));
    }
}

// ── Status credentials ────────────────────────────────────────
$credPath       = ROOT_PATH . '/storage/credentials/service-account.json';
$credExists     = file_exists($credPath);
$driveEnabled   = GoogleDrive::isEnabled();

// ── Page meta ──────────────────────────────────────────────────
$pageTitle    = 'Konfigurasi Google Drive';
$pageSubtitle = 'Atur folder Drive per modul dan kelola koneksi service account';
$activePage   = 'settings';
$breadcrumbs  = [
    ['label' => 'Dashboard',   'url' => url('modules/dashboard/index.php')],
    ['label' => 'Pengaturan',  'url' => url('modules/settings/index.php')],
    ['label' => 'Google Drive'],
];

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <h2 class="page-title"><?= e($pageTitle) ?></h2>
        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
    </div>
    <div class="page-header-right">
        <a href="<?= url('modules/settings/index.php') ?>" class="btn btn-outline">
            ← Pengaturan
        </a>
    </div>
</div>

<?php flash() ?>

<!-- ── Status Bar ──────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-center">

            <!-- Status credentials -->
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="status-dot <?= $credExists ? 'dot-success' : 'dot-danger' ?>"></div>
                    <div>
                        <div class="fw-medium">File Credentials</div>
                        <div class="text-secondary text-sm">
                            <?= $credExists
                                ? 'service-account.json ditemukan ✅'
                                : 'File tidak ditemukan ❌' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Drive aktif -->
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="status-dot <?= $driveEnabled ? 'dot-success' : 'dot-warning' ?>"></div>
                    <div>
                        <div class="fw-medium">Google Drive API</div>
                        <div class="text-secondary text-sm">
                            <?= $driveEnabled ? 'Aktif & siap digunakan ✅' : 'Nonaktif / belum dikonfigurasi ⚠️' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tombol test -->
            <div class="col-md-4 text-end">
                <form method="POST" action="" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="test_connection">
                    <button type="submit" class="btn btn-outline <?= !$driveEnabled ? 'disabled' : '' ?>"
                            <?= !$driveEnabled ? 'disabled' : '' ?>>
                        🔌 Test Koneksi Drive
                    </button>
                </form>
            </div>

        </div>

        <?php if (!$credExists): ?>
        <div class="alert alert-warning mt-3 mb-0">
            <strong>Cara setup credentials:</strong>
            <ol class="mb-0 mt-1 ps-4" style="font-size:13px">
                <li>Buat Service Account di <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                <li>Download JSON key → rename menjadi <code>service-account.json</code></li>
                <li>Upload ke folder <code>storage/credentials/</code> di server</li>
                <li>Share folder Google Drive ke email service account</li>
            </ol>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Folder Configuration ────────────────────────────────── -->
<form method="POST" action="">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_folders">

    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Folder ID per Modul</h4>
            <p class="text-secondary text-sm mt-1 mb-0">
                Salin Folder ID dari URL Google Drive:
                <code>drive.google.com/drive/folders/<strong>[FOLDER_ID]</strong></code>
            </p>
        </div>
        <div class="card-body">

            <?php foreach ($modulList as $modulKey => $modulInfo):
                $cfg        = $driveMap[$modulKey] ?? [];
                $folderId   = $cfg['folder_id']   ?? '';
                $folderName = $cfg['folder_name']  ?? '';
                $isActive   = $cfg['is_active']    ?? 0;
            ?>
            <div class="drive-modul-row">
                <div class="drive-modul-header">
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size:20px"><?= $modulInfo['icon'] ?></span>
                        <div>
                            <div class="fw-medium"><?= e($modulInfo['label']) ?></div>
                            <div class="text-secondary text-sm"><?= e($modulInfo['desc']) ?></div>
                        </div>
                    </div>
                    <!-- Toggle aktif/nonaktif -->
                    <label class="toggle-switch" title="Aktifkan modul ini di Drive">
                        <input type="checkbox"
                               name="active_<?= $modulKey ?>"
                               value="1"
                               <?= $isActive ? 'checked' : '' ?>
                               onchange="toggleModulRow('<?= $modulKey ?>', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="drive-modul-fields <?= !$isActive ? 'fields-disabled' : '' ?>"
                     id="fields_<?= $modulKey ?>">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">Folder ID</label>
                            <input type="text"
                                   name="folder_id_<?= $modulKey ?>"
                                   class="form-control font-mono"
                                   value="<?= e($folderId) ?>"
                                   placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs..."
                                   <?= !$isActive ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nama Folder (opsional)</label>
                            <input type="text"
                                   name="folder_name_<?= $modulKey ?>"
                                   class="form-control"
                                   value="<?= e($folderName) ?>"
                                   placeholder="STUDEX - Rabuan"
                                   <?= !$isActive ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <?php if ($folderId): ?>
                    <div class="mt-2">
                        <a href="https://drive.google.com/drive/folders/<?= e($folderId) ?>"
                           target="_blank"
                           class="btn btn-xs btn-outline text-sm">
                            🔗 Buka di Google Drive
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                💾 Simpan Konfigurasi Drive
            </button>
        </div>
    </div>

</form>

<!-- ── Styles ──────────────────────────────────────────────── -->
<style>
.drive-modul-row {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}
.drive-modul-row:last-child { margin-bottom: 0; }

.drive-modul-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.drive-modul-fields { transition: opacity .2s; }
.fields-disabled { opacity: .45; pointer-events: none; }

.font-mono { font-family: 'Courier New', monospace; font-size: 13px; }

/* Toggle switch */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    inset: 0;
    background: #ccc;
    border-radius: 24px;
    cursor: pointer;
    transition: .2s;
}
.toggle-slider::before {
    content: '';
    position: absolute;
    width: 18px; height: 18px;
    left: 3px; bottom: 3px;
    background: #fff;
    border-radius: 50%;
    transition: .2s;
}
.toggle-switch input:checked + .toggle-slider { background: #395917; }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }

/* Status dots */
.status-dot {
    width: 12px; height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dot-success { background: #28a745; box-shadow: 0 0 0 3px rgba(40,167,69,.2); }
.dot-warning { background: #ffc107; box-shadow: 0 0 0 3px rgba(255,193,7,.2); }
.dot-danger  { background: #dc3545; box-shadow: 0 0 0 3px rgba(220,53,69,.2); }
</style>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<script>
function toggleModulRow(modul, isActive) {
    const fields = document.getElementById('fields_' + modul);
    const inputs = fields.querySelectorAll('input');
    if (isActive) {
        fields.classList.remove('fields-disabled');
        inputs.forEach(i => i.removeAttribute('disabled'));
    } else {
        fields.classList.add('fields-disabled');
        inputs.forEach(i => i.setAttribute('disabled', 'disabled'));
    }
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';