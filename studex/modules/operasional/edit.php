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
    setFlash('error', 'ID kegiatan tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

$stmt = $db->prepare("SELECT * FROM operasional WHERE id = ?");
$stmt->execute([$id]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

$angkatanList = $db->query("SELECT id, nama FROM angkatan ORDER BY tahun DESC")->fetchAll();

$errors = [];
$input  = $ops; // pre-fill dari DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'nama_kegiatan'   => sanitize(post('nama_kegiatan')),
        'angkatan_id'     => sanitizeInt(post('angkatan_id')),
        'tanggal_mulai'   => sanitize(post('tanggal_mulai')),
        'tanggal_selesai' => sanitize(post('tanggal_selesai')),
        'lokasi'          => sanitize(post('lokasi')),
        'deskripsi'       => sanitize(post('deskripsi')),
        'status'          => sanitize(post('status', 'draft')),
    ];

    // Validasi
    if (!$input['nama_kegiatan']) $errors['nama_kegiatan'] = 'Nama kegiatan wajib diisi.';
    if (!$input['angkatan_id'])   $errors['angkatan_id']   = 'Angkatan wajib dipilih.';
    if (!$input['tanggal_mulai']) $errors['tanggal_mulai'] = 'Tanggal mulai wajib diisi.';
    if (!$input['lokasi'])        $errors['lokasi']        = 'Lokasi wajib diisi.';

    if ($input['tanggal_selesai'] && $input['tanggal_selesai'] < $input['tanggal_mulai']) {
        $errors['tanggal_selesai'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
    }

    $validStatus = ['draft', 'aktif', 'selesai', 'dibatalkan'];
    if (!in_array($input['status'], $validStatus)) $input['status'] = 'draft';

    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE operasional
            SET nama_kegiatan  = ?,
                angkatan_id    = ?,
                tanggal_mulai  = ?,
                tanggal_selesai= ?,
                lokasi         = ?,
                deskripsi      = ?,
                status         = ?,
                updated_at     = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $input['nama_kegiatan'],
            $input['angkatan_id'],
            $input['tanggal_mulai'],
            $input['tanggal_selesai'] ?: null,
            $input['lokasi'],
            $input['deskripsi'],
            $input['status'],
            $id,
        ]);

        setFlash('success', 'Data kegiatan berhasil diperbarui.');
        redirect(url('modules/operasional/detail.php?id=' . $id));
    }
}

$pageTitle    = 'Edit Kegiatan Operasional';
$pageSubtitle = e($ops['nama_kegiatan']);
$activePage   = 'operasional';
$breadcrumbs  = [
    ['label' => 'Dashboard',   'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional', 'url' => url('modules/operasional/index.php')],
    ['label' => e($ops['nama_kegiatan']), 'url' => url('modules/operasional/detail.php?id=' . $id)],
    ['label' => 'Edit'],
];

ob_start();
?>

<div class="card" style="max-width:760px;margin:0 auto;">
    <div class="card-header">
        <h3 class="card-title">Edit Kegiatan Operasional</h3>
        <?php
        $faseBadge = [
            'pra'         => ['Pra-Ops',   'badge-warning'],
            'operasional' => ['Ops',       'badge-info'],
            'pasca'       => ['Pasca-Ops', 'badge-success'],
        ];
        [$fl, $fc] = $faseBadge[$ops['fase']] ?? [$ops['fase'], 'badge-secondary'];
        ?>
        <span class="badge <?= $fc ?>"><?= $fl ?></span>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Nama Kegiatan <span class="required">*</span></label>
                <input type="text" name="nama_kegiatan"
                       class="form-control <?= isset($errors['nama_kegiatan']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['nama_kegiatan']) ?>">
                <?php if (isset($errors['nama_kegiatan'])): ?>
                    <div class="invalid-feedback"><?= e($errors['nama_kegiatan']) ?></div>
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
                    <label class="form-label">Tanggal Mulai <span class="required">*</span></label>
                    <input type="date" name="tanggal_mulai"
                           class="form-control <?= isset($errors['tanggal_mulai']) ? 'is-invalid' : '' ?>"
                           value="<?= e($input['tanggal_mulai']) ?>">
                    <?php if (isset($errors['tanggal_mulai'])): ?>
                        <div class="invalid-feedback"><?= e($errors['tanggal_mulai']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="date" name="tanggal_selesai"
                           class="form-control <?= isset($errors['tanggal_selesai']) ? 'is-invalid' : '' ?>"
                           value="<?= e($input['tanggal_selesai'] ?? '') ?>">
                    <?php if (isset($errors['tanggal_selesai'])): ?>
                        <div class="invalid-feedback"><?= e($errors['tanggal_selesai']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Lokasi <span class="required">*</span></label>
                <input type="text" name="lokasi"
                       class="form-control <?= isset($errors['lokasi']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['lokasi']) ?>">
                <?php if (isset($errors['lokasi'])): ?>
                    <div class="invalid-feedback"><?= e($errors['lokasi']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="4"><?= e($input['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <?php foreach (['draft' => 'Draft', 'aktif' => 'Aktif', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $input['status'] === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <a href="<?= url('modules/operasional/detail.php?id=' . $id) ?>" class="btn btn-secondary">Batal</a>
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