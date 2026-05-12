<?php
// ============================================================
//  STUDEX — Student Index
//  modules/mentoring/edit.php — Edit Sesi Mentoring
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db   = db();
$user = currentUser();
$id   = sanitizeInt(get('id'));

// Ambil data
$stmt = $db->prepare("SELECT * FROM mentoring_sesi WHERE id = ?");
$stmt->execute([$id]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Data sesi mentoring tidak ditemukan.');
    redirect(url('modules/mentoring/index.php'));
}

$errors = [];
$input  = $sesi; // default dari DB

$angkatanList = $db->query("SELECT id, nama, kode FROM angkatan ORDER BY tahun DESC")->fetchAll();

// ============================================================
// PROSES FORM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'angkatan_id'   => sanitizeInt(post('angkatan_id')),
        'judul'         => sanitize(post('judul')),
        'tanggal'       => sanitize(post('tanggal')),
        'waktu_mulai'   => sanitize(post('waktu_mulai')),
        'waktu_selesai' => sanitize(post('waktu_selesai')),
        'lokasi'        => sanitize(post('lokasi')),
        'mentor'        => sanitize(post('mentor')),
        'deskripsi'     => sanitize(post('deskripsi')),
        'status'        => sanitize(post('status', 'terjadwal')),
    ];

    // Validasi
    if (!$input['angkatan_id'])
        $errors['angkatan_id'] = 'Angkatan wajib dipilih.';
    if (!$input['judul'])
        $errors['judul'] = 'Judul sesi wajib diisi.';
    if (!$input['tanggal'])
        $errors['tanggal'] = 'Tanggal wajib diisi.';
    if (!in_array($input['status'], ['terjadwal','berlangsung','selesai','dibatalkan']))
        $errors['status'] = 'Status tidak valid.';
    if ($input['waktu_mulai'] && $input['waktu_selesai'] && $input['waktu_selesai'] <= $input['waktu_mulai'])
        $errors['waktu_selesai'] = 'Waktu selesai harus setelah waktu mulai.';

    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE mentoring_sesi SET
                angkatan_id   = ?,
                judul         = ?,
                tanggal       = ?,
                waktu_mulai   = ?,
                waktu_selesai = ?,
                lokasi        = ?,
                mentor        = ?,
                deskripsi     = ?,
                status        = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $input['angkatan_id'],
            $input['judul'],
            $input['tanggal'],
            $input['waktu_mulai']   ?: null,
            $input['waktu_selesai'] ?: null,
            $input['lokasi']        ?: null,
            $input['mentor']        ?: null,
            $input['deskripsi']     ?: null,
            $input['status'],
            $id,
        ]);

        setFlash('success', 'Sesi mentoring berhasil diperbarui.');
        redirect(url('modules/mentoring/detail.php?id=' . $id));
    }
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Edit Sesi Mentoring';
$activePage  = 'mentoring';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Mentoring',  'url' => url('modules/mentoring/index.php')],
    ['label' => truncate($sesi['judul'], 35), 'url' => url('modules/mentoring/detail.php?id=' . $id)],
    ['label' => 'Edit'],
];

ob_start();
?>

<div class="card" style="max-width:760px;">
    <div class="card-header">
        <div class="card-title">Edit Sesi Mentoring</div>
        <div class="card-subtitle">ID #<?= $id ?> &mdash; Dibuat <?= timeAgo($sesi['created_at']) ?></div>
    </div>

    <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <!-- Angkatan -->
        <div class="form-group mb-5">
            <label class="form-label">Angkatan <span class="required">*</span></label>
            <select name="angkatan_id"
                    class="form-control <?= isset($errors['angkatan_id']) ? 'is-invalid' : '' ?>">
                <option value="">-- Pilih Angkatan --</option>
                <?php foreach ($angkatanList as $ang): ?>
                    <option value="<?= $ang['id'] ?>"
                        <?= ($input['angkatan_id'] ?? 0) == $ang['id'] ? 'selected' : '' ?>>
                        <?= e($ang['nama']) ?> (<?= e($ang['kode']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['angkatan_id'])): ?>
                <div class="form-feedback invalid"><?= e($errors['angkatan_id']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Judul -->
        <div class="form-group mb-5">
            <label class="form-label">Judul Sesi <span class="required">*</span></label>
            <input type="text" name="judul"
                   class="form-control <?= isset($errors['judul']) ? 'is-invalid' : '' ?>"
                   value="<?= e($input['judul'] ?? '') ?>"
                   placeholder="Judul sesi mentoring">
            <?php if (isset($errors['judul'])): ?>
                <div class="form-feedback invalid"><?= e($errors['judul']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Mentor -->
        <div class="form-group mb-5">
            <label class="form-label">Nama Mentor</label>
            <input type="text" name="mentor" class="form-control"
                   value="<?= e($input['mentor'] ?? '') ?>"
                   placeholder="Nama mentor / pembicara">
        </div>

        <!-- Tanggal + Status -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">Tanggal <span class="required">*</span></label>
                <input type="date" name="tanggal"
                       class="form-control <?= isset($errors['tanggal']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['tanggal'] ?? '') ?>">
                <?php if (isset($errors['tanggal'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['tanggal']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <?php foreach (['terjadwal','berlangsung','selesai','dibatalkan'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($input['status'] ?? '') === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Waktu -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">Waktu Mulai</label>
                <input type="time" name="waktu_mulai" class="form-control"
                       value="<?= e(substr($input['waktu_mulai'] ?? '', 0, 5)) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Waktu Selesai</label>
                <input type="time" name="waktu_selesai"
                       class="form-control <?= isset($errors['waktu_selesai']) ? 'is-invalid' : '' ?>"
                       value="<?= e(substr($input['waktu_selesai'] ?? '', 0, 5)) ?>">
                <?php if (isset($errors['waktu_selesai'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['waktu_selesai']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lokasi -->
        <div class="form-group mb-5">
            <label class="form-label">Lokasi</label>
            <input type="text" name="lokasi" class="form-control"
                   value="<?= e($input['lokasi'] ?? '') ?>"
                   placeholder="Ruang, aula, online, dll.">
        </div>

        <!-- Deskripsi -->
        <div class="form-group mb-6">
            <label class="form-label">Deskripsi / Topik</label>
            <textarea name="deskripsi" class="form-control" rows="4"
                      placeholder="Tulis topik atau deskripsi sesi mentoring..."><?= e($input['deskripsi'] ?? '') ?></textarea>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Simpan Perubahan
            </button>
            <a href="<?= url('modules/mentoring/detail.php?id=' . $id) ?>" class="btn btn-secondary">Batal</a>
        </div>

    </form>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>