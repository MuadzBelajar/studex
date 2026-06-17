<?php
// ===================================================================
// Fallback: define flash() if missing (fix "undefined function flash()")
// Place at VERY TOP of modules/settings/index.php
// ===================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('flash')) {
    function flash(): void {
        // Ambil flash dari session (mengacu struktur di config/session.php)
        // Jika getFlash() sudah ada, pakai. Kalau tidak, fallback manual.
        if (function_exists('getFlash')) {
            $flash = getFlash();
        } else {
            $flash = $_SESSION['flash'] ?? null;
            unset($_SESSION['flash']);
        }

        if (empty($flash)) return;

        $type    = $flash['type'] ?? 'info';
        $message = $flash['message'] ?? '';

        $map = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
        ];

        $cls = $map[$type] ?? 'alert-info';

        // Gunakan htmlspecialchars langsung agar aman walau helpers belum loaded
        $safeMsg = htmlspecialchars((string)$message, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        echo '<div class="alert ' . $cls . ' fade-in mb-5" role="alert" id="flashMessage">';
        echo '<span>' . $safeMsg . '</span>';
        echo '</div>';
    }
}

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireSuperAdmin();

$db = db();

// ── Ambil semua settings ───────────────────────────────────────
$stmtAll  = $db->query("SELECT kunci, nilai, label, tipe, deskripsi
                         FROM settings ORDER BY id ASC");
$rawSettings = $stmtAll->fetchAll();


// Kelompokkan per group
$settingGroups = [];
foreach ($rawSettings as $row) {
    // schema.sql v1: settings belum punya kolom group; tampilkan dalam 1 group default
    $settingGroups['general'][] = $row;
}


// ── Handle POST: simpan settings ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

$updates = $_POST['settings'] ?? [];
    $saved   = 0;

    foreach ($updates as $key => $value) {
        $key   = sanitize($key);
        $value = sanitize($value);

        // Cek apakah key ada di DB (keamanan: jangan bisa inject key baru)
        $cek = $db->prepare("SELECT id FROM settings WHERE kunci=?");
        $cek->execute([$key]);
        if (!$cek->fetchColumn()) continue;

        $db->prepare("UPDATE settings SET nilai=?, updated_at=NOW() WHERE kunci=?")
           ->execute([$value, $key]);
        $saved++;
    }

    setFlash('success', "{$saved} pengaturan berhasil disimpan.");
    redirect(url('modules/settings/index.php'));
}

// ── Page meta ──────────────────────────────────────────────────
$pageTitle    = 'Pengaturan Sistem';
$pageSubtitle = 'Konfigurasi global aplikasi STUDEX';
$activePage   = 'settings';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Pengaturan'],
];

$groupLabel = [
    'general'  => '⚙️ Umum',
    'academic' => '🎓 Akademik',
    'drive'    => '☁️ Google Drive',
    'notif'    => '🔔 Notifikasi',
    'security' => '🔒 Keamanan',
];

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <h2 class="page-title"><?= e($pageTitle) ?></h2>
        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
    </div>
    <div class="page-header-right">
        <a href="<?= url('modules/settings/drive_config.php') ?>" class="btn btn-outline">
            ☁️ Konfigurasi Drive
        </a>
    </div>
</div>

<?php flash() ?>

<?php if (empty($settingGroups)): ?>
<!-- Empty state: tabel settings belum diisi seeder -->
<div class="empty-state">
    <div class="empty-state-icon">⚙️</div>
    <h3>Belum Ada Pengaturan</h3>
    <p>Jalankan seeder untuk mengisi data pengaturan default.</p>
    <code class="text-sm">mysql -u root studex &lt; database/seeder.sql</code>
</div>

<?php else: ?>

<form method="POST" action="" id="settingsForm">
    <?= csrfField() ?>

    <?php foreach ($settingGroups as $group => $items): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title"><?= e($groupLabel[$group] ?? ucfirst($group)) ?></h4>
        </div>
        <div class="card-body">
            <?php foreach ($items as $item): ?>
            <div class="form-group row mb-3 align-items-center">
                <label class="col-md-4 col-form-label fw-medium">
<?= e($item['label'] ?: $item['kunci']) ?>
<?php if ($item['tipe'] === 'password'): ?>
                        <span class="badge badge-warning ms-1" style="font-size:10px">sensitive</span>
                    <?php endif; ?>
                </label>
                <div class="col-md-8">
                    <?php
$inputName = 'settings[' . e($item['kunci']) . ']';
                    $inputVal  = e($item['nilai']);
                    switch ($item['tipe']):
                        case 'boolean': ?>
                            <div class="d-flex align-items-center gap-3">
                                <label class="toggle-switch">
                                    <input type="hidden"  name="<?= $inputName ?>" value="0">
                                    <input type="checkbox"
                                           name="<?= $inputName ?>"
                                           value="1"
<?= $item['nilai'] == '1' ? 'checked' : '' ?>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="text-sm text-secondary">
<?= $item['nilai'] == '1' ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </div>
                        <?php break;
                        case 'textarea': ?>
                            <textarea name="<?= $inputName ?>"
                                      class="form-control"
                                      rows="3"><?= $inputVal ?></textarea>
                        <?php break;
                        case 'password': ?>
                            <div class="input-group">
                                <input type="password"
id="pw_<?= e($item['kunci']) ?>"
                                       name="<?= $inputName ?>"
                                       class="form-control"
                                       value="<?= $inputVal ?>"
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-outline"
onclick="togglePw('pw_<?= e($item['kunci']) ?>')"
                                    👁
                                </button>
                            </div>
                        <?php break;
                        case 'select':
                            // Opsi disimpan di setting_options (JSON) — fallback ke text jika tidak ada
                            $optsRaw = $item['setting_options'] ?? '[]';
                            $opts    = json_decode($optsRaw, true) ?: [];
                            if ($opts): ?>
                                <select name="<?= $inputName ?>" class="form-control">
                                    <?php foreach ($opts as $optVal => $optLabel): ?>
                                        <option value="<?= e($optVal) ?>"
                                                <?= $item['setting_value'] == $optVal ? 'selected' : '' ?>>
                                            <?= e($optLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="<?= $inputName ?>" class="form-control" value="<?= $inputVal ?>">
                            <?php endif;
                            break;
                        default: // text, number, email, url, dll ?>
                            <input type="<?= e($item['setting_type'] ?: 'text') ?>"
                                   name="<?= $inputName ?>"
                                   class="form-control"
                                   value="<?= $inputVal ?>">
                        <?php break;
                    endswitch; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Sticky save bar -->
    <div class="sticky-save-bar">
        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                💾 Simpan Semua Pengaturan
            </button>
            <a href="<?= url('modules/dashboard/index.php') ?>" class="btn btn-outline">
                Batal
            </a>
            <span class="text-secondary text-sm ms-auto">
                Hanya Super Admin yang dapat mengubah pengaturan ini.
            </span>
        </div>
    </div>

</form>

<?php endif; ?>

<!-- ── Styles ──────────────────────────────────────────────── -->
<style>
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

/* Sticky save bar */
.sticky-save-bar {
    position: sticky;
    bottom: 0;
    background: #fff;
    border-top: 1px solid #e5e7eb;
    padding: 12px 20px;
    margin: 0 -20px;
    z-index: 10;
    box-shadow: 0 -2px 8px rgba(18,18,18,0.06);
}
</style>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<script>
function togglePw(id) {
    const el = document.getElementById(id);
    el.type  = el.type === 'password' ? 'text' : 'password';
}

// Update toggle label text secara live
document.querySelectorAll('.toggle-switch input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', function () {
        const label = this.closest('.d-flex').querySelector('.text-secondary');
        if (label) label.textContent = this.checked ? 'Aktif' : 'Nonaktif';
    });
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';