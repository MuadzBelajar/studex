<?php
// ============================================================
//  STUDEX — Student Index
//  modules/siswa/create.php — Tambah Siswa Baru
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db     = db();
$errors = [];
$input  = [];

// Daftar angkatan
$angkatanList = $db->query("SELECT id, nama, kode FROM angkatan WHERE is_aktif = 1 ORDER BY tahun DESC")->fetchAll();

// ============================================================
// PROSES FORM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'angkatan_id'   => sanitizeInt(post('angkatan_id')),
        'nis'           => sanitize(post('nis')),
        'nama'          => sanitize(post('nama')),
        'jenis_kelamin' => sanitize(post('jenis_kelamin')),
        'tempat_lahir'  => sanitize(post('tempat_lahir')),
        'tanggal_lahir' => sanitize(post('tanggal_lahir')),
        'alamat'        => sanitize(post('alamat')),
        'no_hp'         => sanitize(post('no_hp')),
        'email'         => sanitize(post('email')),
        'status'        => sanitize(post('status', 'aktif')),
        'catatan'       => sanitize(post('catatan')),
    ];

    // Validasi
    if (!$input['angkatan_id'])   $errors['angkatan_id']   = 'Angkatan wajib dipilih.';
    if (!$input['nis'])           $errors['nis']           = 'NIS wajib diisi.';
    if (!$input['nama'])          $errors['nama']          = 'Nama wajib diisi.';
    if (!in_array($input['jenis_kelamin'], ['L','P'])) $errors['jenis_kelamin'] = 'Jenis kelamin tidak valid.';
    if ($input['email'] && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    // Cek NIS unik
    if (!isset($errors['nis']) && $input['nis']) {
        $cek = $db->prepare("SELECT id FROM siswa WHERE nis = ?");
        $cek->execute([$input['nis']]);
        if ($cek->fetch()) $errors['nis'] = 'NIS sudah digunakan.';
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO siswa
                (angkatan_id, nis, nama, jenis_kelamin, tempat_lahir, tanggal_lahir,
                 alamat, no_hp, email, status, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['angkatan_id'], $input['nis'], $input['nama'],
            $input['jenis_kelamin'],
            $input['tempat_lahir']  ?: null,
            $input['tanggal_lahir'] ?: null,
            $input['alamat']        ?: null,
            $input['no_hp']         ?: null,
            $input['email']         ?: null,
            $input['status'],
            $input['catatan']       ?: null,
        ]);

        setFlash('success', 'Siswa ' . $input['nama'] . ' berhasil ditambahkan.');
        redirect(url('modules/siswa/index.php'));
    }
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Tambah Siswa';
$activePage  = 'siswa';
$breadcrumbs = [
    ['label' => 'Dashboard',  'url' => url('modules/dashboard/index.php')],
    ['label' => 'Data Siswa', 'url' => url('modules/siswa/index.php')],
    ['label' => 'Tambah Siswa'],
];

ob_start();
?>

<div class="card" style="max-width:720px;">
    <div class="card-header">
        <div class="card-title">Form Tambah Siswa</div>
    </div>

    <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <!-- Baris 1: Angkatan + NIS -->
        <div class="form-row mb-5">
            <div class="form-group">
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

            <div class="form-group">
                <label class="form-label">NIS <span class="required">*</span></label>
                <input type="text" name="nis"
                       class="form-control <?= isset($errors['nis']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['nis'] ?? '') ?>"
                       placeholder="Nomor Induk Siswa">
                <?php if (isset($errors['nis'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['nis']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nama -->
        <div class="form-group mb-5">
            <label class="form-label">Nama Lengkap <span class="required">*</span></label>
            <input type="text" name="nama"
                   class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                   value="<?= e($input['nama'] ?? '') ?>"
                   placeholder="Masukkan nama lengkap siswa">
            <?php if (isset($errors['nama'])): ?>
                <div class="form-feedback invalid"><?= e($errors['nama']) ?></div>
            <?php endif; ?>
        </div>

        <!-- JK + Status -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
                <select name="jenis_kelamin"
                        class="form-control <?= isset($errors['jenis_kelamin']) ? 'is-invalid' : '' ?>">
                    <option value="">-- Pilih --</option>
                    <option value="L" <?= ($input['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>
                        Laki-laki
                    </option>
                    <option value="P" <?= ($input['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>
                        Perempuan
                    </option>
                </select>
                <?php if (isset($errors['jenis_kelamin'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['jenis_kelamin']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="aktif"       <?= ($input['status'] ?? 'aktif') === 'aktif'       ? 'selected' : '' ?>>Aktif</option>
                    <option value="tidak_aktif" <?= ($input['status'] ?? '') === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                    <option value="alumni"      <?= ($input['status'] ?? '') === 'alumni'      ? 'selected' : '' ?>>Alumni</option>
                </select>
            </div>
        </div>

        <!-- Tempat + Tanggal Lahir -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">Tempat Lahir</label>
                <input type="text" name="tempat_lahir" class="form-control"
                       value="<?= e($input['tempat_lahir'] ?? '') ?>"
                       placeholder="Kota/Kabupaten">
            </div>
            <div class="form-group">
                <label class="form-label">Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" class="form-control"
                       value="<?= e($input['tanggal_lahir'] ?? '') ?>">
            </div>
        </div>

        <!-- No HP + Email -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">No. HP</label>
                <input type="text" name="no_hp" class="form-control"
                       value="<?= e($input['no_hp'] ?? '') ?>"
                       placeholder="08xxxxxxxxxx">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email"
                       class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['email'] ?? '') ?>"
                       placeholder="email@domain.com">
                <?php if (isset($errors['email'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alamat -->
        <div class="form-group mb-5">
            <label class="form-label">Alamat</label>
            <textarea name="alamat" class="form-control" rows="3"
                      placeholder="Alamat lengkap"><?= e($input['alamat'] ?? '') ?></textarea>
        </div>

        <!-- Catatan -->
        <div class="form-group mb-6">
            <label class="form-label">Catatan</label>
            <textarea name="catatan" class="form-control" rows="2"
                      placeholder="Catatan tambahan (opsional)"><?= e($input['catatan'] ?? '') ?></textarea>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" width="16" height="16"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Simpan Siswa
            </button>
            <a href="<?= url('modules/siswa/index.php') ?>" class="btn btn-secondary">
                Batal
            </a>
        </div>

    </form>
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>