<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireAdmin();

$db = db();

$angkatanList = $db->query("SELECT id, nama FROM angkatan ORDER BY tahun DESC")->fetchAll();

$errors = [];
$input  = [
    'nama_sesi'   => '',
    'angkatan_id' => '',
    'tanggal'     => '',
    'lokasi'      => '',
    'deskripsi'   => '',
    'status'      => 'draft',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'nama_sesi'   => sanitize(post('nama_sesi')),
        'angkatan_id' => sanitizeInt(post('angkatan_id')),
        'tanggal'     => sanitize(post('tanggal')),
        'lokasi'      => sanitize(post('lokasi')),
        'deskripsi'   => sanitize(post('deskripsi')),
        'status'      => sanitize(post('status', 'draft')),
    ];

    if (!$input['nama_sesi'])   $errors['nama_sesi']   = 'Nama sesi wajib diisi.';
    if (!$input['angkatan_id']) $errors['angkatan_id'] = 'Angkatan wajib dipilih.';
    if (!$input['tanggal'])     $errors['tanggal']     = 'Tanggal wajib diisi.';

    if (!in_array($input['status'], ['draft', 'aktif', 'selesai'])) $input['status'] = 'draft';


    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO binjas_sesi
                (nama_sesi, angkatan_id, tanggal, lokasi, deskripsi, status, created_by, created_at)
            VALUES (:nama_sesi, :angkatan_id, :tanggal, :lokasi, :deskripsi, :status, :created_by, NOW())
        ");
        $stmt->execute([
            ':nama_sesi'   => $input['nama_sesi'],
            ':angkatan_id' => $input['angkatan_id'],
            ':tanggal'     => $input['tanggal'],
            ':lokasi'      => $input['lokasi'],
            ':deskripsi'   => $input['deskripsi'],
            ':status'      => $input['status'],
            ':created_by'  => currentUser()['id'],
        ]);
        $newId = $db->lastInsertId();

        setFlash('success', 'Sesi Binjas berhasil dibuat.');
        redirect(url('modules/binjas/detail.php?id=' . $newId));
    }
}

$pageTitle    = 'Tambah Sesi Binjas';
$pageSubtitle = 'Buat sesi pembinaan jasmani baru';
$activePage   = 'binjas';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Binjas',    'url' => url('modules/binjas/index.php')],
    ['label' => 'Tambah Sesi'],
];

ob_start();
?>

<div class="card" style="max-width:720px;margin:0 auto;">
    <div class="card-header">
        <h3 class="card-title">Form Sesi Binjas</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Nama Sesi <span class="required">*</span></label>
                <input type="text" name="nama_sesi"
                       class="form-control <?= isset($errors['nama_sesi']) ? 'is-invalid' : '' ?>"
                       placeholder="Contoh: Binjas Semester Ganjil 2025"
                       value="<?= e($input['nama_sesi']) ?>">
                <?php if (isset($errors['nama_sesi'])): ?>
                    <div class="invalid-feedback"><?= e($errors['nama_sesi']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Angkatan <span class="required">*</span></label>
                <select name="angkatan_id"
                        class="form-control <?= isset($errors['angkatan_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— Pilih Angkatan —</option>
                    <?php foreach ($angkatanList as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $input['angkatan_id'] == $a['id'] ? 'selected' : '' ?>>
                            <?= e($a['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['angkatan_id'])): ?>
                    <div class="invalid-feedback"><?= e($errors['angkatan_id']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tanggal <span class="required">*</span></label>
                    <input type="date" name="tanggal"
                           class="form-control <?= isset($errors['tanggal']) ? 'is-invalid' : '' ?>"
                           value="<?= e($input['tanggal']) ?>">
                    <?php if (isset($errors['tanggal'])): ?>
                        <div class="invalid-feedback"><?= e($errors['tanggal']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="draft"   <?= $input['status'] === 'draft'   ? 'selected' : '' ?>>Draft</option>
                        <option value="aktif"   <?= $input['status'] === 'aktif'   ? 'selected' : '' ?>>Aktif</option>
                        <option value="selesai" <?= $input['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Lokasi</label>
                <input type="text" name="lokasi" class="form-control"
                       placeholder="Contoh: Lapangan Makorem, Makassar"
                       value="<?= e($input['lokasi']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3"
                          placeholder="Gambaran umum sesi binjas…"><?= e($input['deskripsi']) ?></textarea>
            </div>

            <div class="form-actions">
                <a href="<?= url('modules/binjas/index.php') ?>" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Buat Sesi
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';