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
$id = sanitizeInt(get('id'));

if (!$id) {
    setFlash('error', 'ID sesi tidak valid.');
    redirect(url('modules/binjas/index.php'));
}

$stmt = $db->prepare("SELECT * FROM binjas_sesi WHERE id = ?");
$stmt->execute([$id]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Sesi tidak ditemukan.');
    redirect(url('modules/binjas/index.php'));
}

$angkatanList = $db->query("SELECT id, nama FROM angkatan ORDER BY tahun DESC")->fetchAll();

$errors = [];
$input  = $sesi; // pre-fill dari DB

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
        $db->prepare("
            UPDATE binjas_sesi
            SET nama_sesi   = ?,
                angkatan_id = ?,
                tanggal     = ?,
                lokasi      = ?,
                deskripsi   = ?,
                status      = ?,
                updated_at  = NOW()
            WHERE id = ?
        ")->execute([
            $input['nama_sesi'],
            $input['angkatan_id'],
            $input['tanggal'],
            $input['lokasi'],
            $input['deskripsi'],
            $input['status'],
            $id,
        ]);

        setFlash('success', 'Sesi Binjas berhasil diperbarui.');
        redirect(url('modules/binjas/detail.php?id=' . $id));
    }
}

$pageTitle    = 'Edit Sesi Binjas';
$pageSubtitle = e($sesi['nama_sesi']);
$activePage   = 'binjas';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Binjas',    'url' => url('modules/binjas/index.php')],
    ['label' => e($sesi['nama_sesi']), 'url' => url('modules/binjas/detail.php?id=' . $id)],
    ['label' => 'Edit'],
];

ob_start();
?>

<div class="card" style="max-width:720px;margin:0 auto;">
    <div class="card-header">
        <h3 class="card-title">Edit Sesi Binjas</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Nama Sesi <span class="required">*</span></label>
                <input type="text" name="nama_sesi"
                       class="form-control <?= isset($errors['nama_sesi']) ? 'is-invalid' : '' ?>"
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
                        <?php foreach (['draft' => 'Draft', 'aktif' => 'Aktif', 'selesai' => 'Selesai'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $input['status'] === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Lokasi</label>
                <input type="text" name="lokasi" class="form-control"
                       value="<?= e($input['lokasi'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3"><?= e($input['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <a href="<?= url('modules/binjas/detail.php?id=' . $id) ?>" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';