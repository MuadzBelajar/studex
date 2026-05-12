<?php
// ============================================================
//  STUDEX — Student Index
//  modules/settings/drive_config.php — Konfigurasi Google Drive
// ============================================================

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

// ── Daftar modul ───────────────────────────────────────────────
$modulList = [
    'rabuan'      => ['label' => 'Rabuan',      'icon' => '📋', 'desc' => 'Folder penyimpanan notulensi PDF Rabuan'],
    'mentoring'   => ['label' => 'Mentoring',   'icon' => '📚', 'desc' => 'Folder penyimpanan materi file Mentoring'],
    'operasional' => ['label' => 'Operasional', 'icon' => '🗂️',  'desc' => 'Folder penyimpanan laporan PDF Operasional'],
    'binjas'      => ['label' => 'Binjas',      'icon' => '💪', 'desc' => 'Folder penyimpanan dokumen Binjas'],
    'umum'        => ['label' => 'Umum',        'icon' => '📁', 'desc' => 'Folder umum / fallback jika modul tidak dikonfigurasi'],
];

// ── Ambil konfigurasi Drive saat ini ──────────────────────────
$stmt    = $db->query("SELECT * FROM drive_config ORDER BY FIELD(modul,'rabuan','mentoring','operasional','binjas','umum')");
$driveMap = [];
foreach ($stmt->fetchAll() as $r) {
    $driveMap[$r['modul']] = $r;
}

// ── Status credentials & Drive ────────────────────────────────
$credPath     = GOOGLE_CREDENTIALS_PATH;
$credExists   = file_exists($credPath);
$driveEnabled = GoogleDrive::isEnabled();

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = sanitize(post('action'));

    // ── Simpan folder config ──────────────────────────────────
    if ($action === 'save') {
        $userId = (int)$_SESSION['user_id'];
        $saved  = 0;

        foreach ($modulList as $modulKey => $_) {
            $folderId   = sanitize(post("folder_id_{$modulKey}"));
            $folderName = sanitize(post("folder_name_{$modulKey}"));
            $folderUrl  = $folderId ? "https://drive.google.com/drive/folders/{$folderId}" : null;
            $isAktif    = post("aktif_{$modulKey}") ? 1 : 0;

            $cek = $db->prepare("SELECT id FROM drive_config WHERE modul=?");
            $cek->execute([$modulKey]);

            if ($cek->fetchColumn()) {
                $db->prepare("UPDATE drive_config
                              SET folder_id=?, folder_name=?, folder_url=?, is_aktif=?,
                                  updated_by=?, updated_at=NOW()
                              WHERE modul=?")
                   ->execute([$folderId, $folderName ?: null, $folderUrl, $isAktif, $userId, $modulKey]);
            } else {
                $db->prepare("INSERT INTO drive_config
                                  (modul, folder_id, folder_name, folder_url, is_aktif, updated_by)
                              VALUES (?,?,?,?,?,?)")
                   ->execute([$modulKey, $folderId, $folderName ?: null, $folderUrl, $isAktif, $userId]);
            }
            $saved++;
        }

        setFlash('success', "Konfigurasi Drive berhasil disimpan untuk {$saved} modul.");
        redirect(url('modules/settings/drive_config.php'));
    }

    // ── Test koneksi Drive per modul ──────────────────────────
    if ($action === 'test') {
        $modulKey = sanitize(post('modul'));
        $folderId = sanitize(post('folder_id'));

        if (!$folderId) {
            setFlash('error', 'Folder ID tidak boleh kosong untuk melakukan test koneksi.');
            redirect(url('modules/settings/drive_config.php'));
        }

        if (!$driveEnabled) {
            setFlash('error', 'Google Drive tidak aktif. Pastikan credentials sudah terpasang dan Drive diaktifkan di Settings.');
            redirect(url('modules/settings/drive_config.php'));
        }

        $result = GoogleDrive::getInstance()->testConnection($folderId);

        if ($result['success']) {
            // Simpan folder_name hasil test otomatis
            $db->prepare("UPDATE drive_config SET folder_name=?, updated_at=NOW() WHERE modul=?")
               ->execute([$result['name'], $modulKey]);

            setFlash('success', "✅ Modul <strong>{$modulKey}</strong>: " . e($result['message']));
        } else {
            setFlash('error', "❌ Modul <strong>{$modulKey}</strong>: " . e($result['message']));
        }

        redirect(url('modules/settings/drive_config.php'));
    }
}

// ── Page meta ──────────────────────────────────────────────────
$pageTitle    = 'Konfigurasi Google Drive';
$pageSubtitle = 'Atur folder Drive per modul dan kelola koneksi Service Account';
$activePage   = 'settings';
$breadcrumbs  = [
    ['label' => 'Dashboard',  'url' => url('modules/dashboard/index.php')],
    ['label' => 'Pengaturan', 'url' => url('modules/settings/index.php')],
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

<!-- ── Status Bar ─────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-center">

            <div class="col-md-5">
                <div class="d-flex align-items-center gap-3">
                    <div class="status-dot <?= $credExists ? 'dot-success' : 'dot-danger' ?>"></div>
                    <div>
                        <div class="fw-medium text-sm">File Credentials</div>
                        <div class="text-muted text-xs">
                            <?= $credExists
                                ? 'service-account.json ditemukan ✅'
                                : 'File tidak ditemukan di storage/credentials/ ❌' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="status-dot <?= $driveEnabled ? 'dot-success' : 'dot-warning' ?>"></div>
                    <div>
                        <div class="fw-medium text-sm">Google Drive API</div>
                        <div class="text-muted text-xs">
                            <?= $driveEnabled ? 'Aktif & siap digunakan ✅' : 'Nonaktif — aktifkan di Pengaturan ⚠️' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 text-end">
                <a href="<?= url('modules/settings/index.php') ?>" class="btn btn-outline btn-sm">
                    ⚙️ Pengaturan Sistem
                </a>
            </div>

        </div>

        <?php if (!$credExists): ?>
        <div class="alert alert-warning mt-3 mb-0" style="font-size:13px">
            <strong>Cara pasang credentials:</strong>
            <ol class="mb-0 mt-1 ps-4">
                <li>Buat Service Account di <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a> → aktifkan Drive API</li>
                <li>Download JSON key → rename menjadi <code>service-account.json</code></li>
                <li>Upload ke folder <code>storage/credentials/</code> di server</li>
                <li>Share folder Google Drive target ke email Service Account sebagai <strong>Editor</strong></li>
            </ol>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Form Folder Config ─────────────────────────────────── -->
<form method="POST" action="" id="driveForm">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">

    <div class="card">
        <div class="card-header">
            <h4 class="card-title">Folder ID per Modul</h4>
            <p class="text-muted text-sm mt-1 mb-0">
                Salin Folder ID dari URL Google Drive:
                <code>drive.google.com/drive/folders/<strong style="color:var(--color-army)">[FOLDER_ID_DI_SINI]</strong></code>
            </p>
        </div>

        <div class="card-body">
            <?php foreach ($modulList as $modulKey => $info):
                $cfg        = $driveMap[$modulKey] ?? [];
                $folderId   = $cfg['folder_id']   ?? '';
                $folderName = $cfg['folder_name']  ?? '';
                $folderUrl  = $cfg['folder_url']   ?? '';
                $isAktif    = $cfg['is_aktif']     ?? 0;
            ?>
            <div class="drive-row" id="row_<?= $modulKey ?>">
                <!-- Row header -->
                <div class="drive-row-header">
                    <div class="d-flex align-items-center gap-3">
                        <span style="font-size:22px"><?= $info['icon'] ?></span>
                        <div>
                            <div class="fw-medium"><?= e($info['label']) ?></div>
                            <div class="text-muted text-xs"><?= e($info['desc']) ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($folderId && $isAktif): ?>
                        <span class="badge badge-success text-xs">Terkonfigurasi</span>
                        <?php elseif ($folderId): ?>
                        <span class="badge badge-secondary text-xs">Nonaktif</span>
                        <?php else: ?>
                        <span class="badge badge-warning text-xs">Belum diisi</span>
                        <?php endif; ?>

                        <!-- Toggle aktif -->
                        <label class="toggle-switch" title="Aktifkan modul ini">
                            <input type="hidden"   name="aktif_<?= $modulKey ?>" value="0">
                            <input type="checkbox" name="aktif_<?= $modulKey ?>" value="1"
                                   <?= $isAktif ? 'checked' : '' ?>
                                   onchange="toggleRow('<?= $modulKey ?>', this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Row fields -->
                <div class="drive-row-fields <?= !$isAktif ? 'fields-dim' : '' ?>"
                     id="fields_<?= $modulKey ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Folder ID</label>
                            <input type="text"
                                   name="folder_id_<?= $modulKey ?>"
                                   id="fid_<?= $modulKey ?>"
                                   class="form-control font-mono"
                                   value="<?= e($folderId) ?>"
                                   placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs..."
                                   <?= !$isAktif ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nama Folder <span class="text-muted">(opsional)</span></label>
                            <input type="text"
                                   name="folder_name_<?= $modulKey ?>"
                                   class="form-control"
                                   value="<?= e($folderName) ?>"
                                   placeholder="STUDEX - <?= e($info['label']) ?>"
                                   <?= !$isAktif ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-2">
                            <!-- Test koneksi per modul -->
                            <form method="POST" action="" style="margin:0" onsubmit="syncFolderId('<?= $modulKey ?>', this)">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="test">
                                <input type="hidden" name="modul"  value="<?= $modulKey ?>">
                                <input type="hidden" name="folder_id" id="test_fid_<?= $modulKey ?>" value="<?= e($folderId) ?>">
                                <button type="submit"
                                        class="btn btn-outline btn-sm w-100"
                                        <?= (!$isAktif || !$driveEnabled) ? 'disabled' : '' ?>
                                        title="<?= !$driveEnabled ? 'Drive tidak aktif' : 'Test koneksi folder ini' ?>">
                                    🔌 Test
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php if ($folderId): ?>
                    <div class="mt-2">
                        <a href="https://drive.google.com/drive/folders/<?= e($folderId) ?>"
                           target="_blank" class="btn btn-xs btn-outline text-sm">
                            🔗 Buka di Google Drive
                        </a>
                        <?php if ($folderName): ?>
                        <span class="text-muted text-xs ms-2">📁 <?= e($folderName) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                💾 Simpan Semua Konfigurasi
            </button>
            <a href="<?= url('modules/settings/index.php') ?>" class="btn btn-outline">
                Batal
            </a>
        </div>
    </div>
</form>

<style>
.drive-row {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    transition: border-color .2s;
}
.drive-row:last-child { margin-bottom: 0; }
.drive-row:hover { border-color: #A4C8AE; }

.drive-row-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.drive-row-fields { transition: opacity .2s; }
.fields-dim { opacity: .4; pointer-events: none; }

.font-mono { font-family: 'Courier New', monospace; font-size: 13px; letter-spacing: .3px; }

/* Toggle */
.toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; background:#ccc; border-radius:24px; cursor:pointer; transition:.2s; }
.toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.toggle-switch input:checked + .toggle-slider { background:#395917; }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }

/* Status dots */
.status-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
.dot-success { background:#28a745; box-shadow:0 0 0 3px rgba(40,167,69,.2); }
.dot-warning { background:#ffc107; box-shadow:0 0 0 3px rgba(255,193,7,.2); }
.dot-danger  { background:#dc3545; box-shadow:0 0 0 3px rgba(220,53,69,.2); }
</style>

<script>
function toggleRow(modul, isActive) {
    const fields = document.getElementById('fields_' + modul);
    const inputs = fields.querySelectorAll('input, button, select');
    if (isActive) {
        fields.classList.remove('fields-dim');
        inputs.forEach(el => el.removeAttribute('disabled'));
    } else {
        fields.classList.add('fields-dim');
        inputs.forEach(el => el.setAttribute('disabled', 'disabled'));
    }
}

// Sync folder_id ke hidden input form test sebelum submit
function syncFolderId(modul, form) {
    const fidVal = document.getElementById('fid_' + modul).value;
    document.getElementById('test_fid_' + modul).value = fidVal;
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';