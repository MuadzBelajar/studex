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
    'nama_kegiatan'   => '',
    'angkatan_id'     => '',
    'tanggal_mulai'   => '',
    'tanggal_selesai' => '',
    'lokasi'          => '',
    'deskripsi'       => '',
    'status'          => 'draft',
];

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

    if (!in_array($input['status'], ['draft', 'aktif'])) $input['status'] = 'draft';

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO operasional
                (nama_kegiatan, angkatan_id, tanggal_mulai, tanggal_selesai,
                 lokasi, deskripsi, fase, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pra', ?, ?, NOW())
        ");
        $stmt->execute([
            $input['nama_kegiatan'],
            $input['angkatan_id'],
            $input['tanggal_mulai'],
            $input['tanggal_selesai'] ?: null,
            $input['lokasi'],
            $input['deskripsi'],
            $input['status'],
            currentUserId(),
        ]);
        $newId = $db->lastInsertId();

        setFlash('success', 'Kegiatan operasional berhasil dibuat. Lanjutkan ke tahap Pra-Operasional.');
        redirect(url('modules/operasional/detail.php?id=' . $newId));
    }
}

$pageTitle    = 'Tambah Kegiatan Operasional';
$pageSubtitle = 'Buat kegiatan lapangan baru';
$activePage   = 'operasional';
$breadcrumbs  = [
    ['label' => 'Dashboard',   'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional', 'url' => url('modules/operasional/index.php')],
    ['label' => 'Tambah Kegiatan'],
];

ob_start();
?>

<div class="card" style="max-width:760px;margin:0 auto;">
    <div class="card-header">
        <h3 class="card-title">Form Kegiatan Operasional</h3>
    </div>
    <div class="card-body">

        <!-- Info alur fase -->
        <div class="alert alert-info mb-5">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                Setelah membuat kegiatan, sistem akan membimbing Anda melalui tiga fase:
                <strong>Pra-Operasional → Operasional → Pasca-Operasional</strong>.
            </div>
        </div>

        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label">Nama Kegiatan <span class="required">*</span></label>
                <input type="text" name="nama_kegiatan"
                       class="form-control <?= isset($errors['nama_kegiatan']) ? 'is-invalid' : '' ?>"
                       placeholder="Contoh: Operasi Hutan Pinus 2025"
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
                           value="<?= e($input['tanggal_selesai']) ?>">
                    <?php if (isset($errors['tanggal_selesai'])): ?>
                        <div class="invalid-feedback"><?= e($errors['tanggal_selesai']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Lokasi <span class="required">*</span></label>
                <input type="text" name="lokasi"
                       class="form-control <?= isset($errors['lokasi']) ? 'is-invalid' : '' ?>"
                       placeholder="Contoh: Gunung Lompobattang, Sulawesi Selatan"
                       value="<?= e($input['lokasi']) ?>">
                <?php if (isset($errors['lokasi'])): ?>
                    <div class="invalid-feedback"><?= e($errors['lokasi']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="4"
                          placeholder="Tujuan dan gambaran umum kegiatan…"><?= e($input['deskripsi']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Status Awal</label>
                <select name="status" class="form-control">
                    <option value="draft" <?= $input['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="aktif" <?= $input['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                </select>
            </div>

            <div class="form-actions">
                <a href="<?= url('modules/operasional/index.php') ?>" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Buat Kegiatan
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';