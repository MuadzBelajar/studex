<?php
// ============================================================
//  STUDEX — Student Index
//  modules/angkatan/create.php — Tambah Angkatan Baru
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireSuperAdmin(); // Hanya Super Admin

$db     = db();
$errors = [];
$input  = [
    'nama'      => '',
    'kode'      => '',
    'tahun'     => date('Y'),
    'deskripsi' => '',
    'is_aktif'  => 1,
];

// ============================================================
// PROSES FORM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'nama'      => sanitize(post('nama')),
        'kode'      => strtoupper(sanitize(post('kode'))),
        'tahun'     => sanitizeInt(post('tahun')),
        'deskripsi' => sanitize(post('deskripsi')),
        'is_aktif'  => post('is_aktif') === '1' ? 1 : 0,
    ];

    // Validasi
    if (!$input['nama'])  $errors['nama']  = 'Nama angkatan wajib diisi.';
    if (!$input['kode'])  $errors['kode']  = 'Kode angkatan wajib diisi.';
    if (!$input['tahun'] || $input['tahun'] < 2000 || $input['tahun'] > 2100) {
        $errors['tahun'] = 'Tahun tidak valid.';
    }

    // Cek kode unik
    if (!isset($errors['kode'])) {
        $cek = $db->prepare("SELECT id FROM angkatan WHERE kode = ?");
        $cek->execute([$input['kode']]);
        if ($cek->fetch()) $errors['kode'] = 'Kode angkatan sudah digunakan.';
    }

    if (empty($errors)) {
        $db->prepare("
            INSERT INTO angkatan (nama, kode, tahun, deskripsi, is_aktif)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $input['nama'],
            $input['kode'],
            $input['tahun'],
            $input['deskripsi'] ?: null,
            $input['is_aktif'],
        ]);

        setFlash('success', 'Angkatan ' . $input['nama'] . ' berhasil ditambahkan.');
        redirect(url('modules/angkatan/index.php'));
    }
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Tambah Angkatan';
$activePage  = 'siswa';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Angkatan',  'url' => url('modules/angkatan/index.php')],
    ['label' => 'Tambah Angkatan'],
];

ob_start();
?>

<div class="card" style="max-width:560px;">
    <div class="card-header">
        <div class="card-title">Form Tambah Angkatan</div>
    </div>

    <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <!-- Nama Angkatan -->
        <div class="form-group mb-5">
            <label class="form-label">
                Nama Angkatan <span class="required">*</span>
            </label>
            <input type="text" name="nama"
                   class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                   value="<?= e($input['nama']) ?>"
                   placeholder="cth: Angkatan 2025"
                   autofocus>
            <?php if (isset($errors['nama'])): ?>
                <div class="form-feedback invalid"><?= e($errors['nama']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Kode + Tahun -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">
                    Kode Angkatan <span class="required">*</span>
                </label>
                <input type="text" name="kode"
                       class="form-control <?= isset($errors['kode']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['kode']) ?>"
                       placeholder="cth: ANG-2025"
                       style="text-transform:uppercase;">
                <?php if (isset($errors['kode'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['kode']) ?></div>
                <?php endif; ?>
                <div class="form-hint">Kode unik, otomatis kapital.</div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Tahun <span class="required">*</span>
                </label>
                <input type="number" name="tahun"
                       class="form-control <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['tahun']) ?>"
                       min="2000" max="2100"
                       placeholder="<?= date('Y') ?>">
                <?php if (isset($errors['tahun'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['tahun']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deskripsi -->
        <div class="form-group mb-5">
            <label class="form-label">Deskripsi</label>
            <textarea name="deskripsi" class="form-control" rows="3"
                      placeholder="Deskripsi singkat angkatan (opsional)"><?= e($input['deskripsi']) ?></textarea>
        </div>

        <!-- Status Aktif -->
        <div class="form-group mb-6">
            <label class="form-label">Status</label>
            <div class="flex gap-4 mt-1">
                <label class="form-check">
                    <input type="radio" class="form-check-input" name="is_aktif"
                           value="1" <?= $input['is_aktif'] ? 'checked' : '' ?>>
                    <span class="form-check-label">Aktif</span>
                </label>
                <label class="form-check">
                    <input type="radio" class="form-check-input" name="is_aktif"
                           value="0" <?= !$input['is_aktif'] ? 'checked' : '' ?>>
                    <span class="form-check-label">Tidak Aktif</span>
                </label>
            </div>
            <div class="form-hint">Angkatan aktif akan muncul di semua dropdown pemilihan.</div>
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
                Simpan Angkatan
            </button>
            <a href="<?= url('modules/angkatan/index.php') ?>" class="btn btn-secondary">
                Batal
            </a>
        </div>
    </form>
</div>

<script>
// Auto-generate kode dari nama
document.querySelector('[name="nama"]').addEventListener('input', function () {
    var kodeField = document.querySelector('[name="kode"]');
    if (kodeField.dataset.manual) return; // jangan override kalau sudah diisi manual

    var nama  = this.value.trim();
    var tahun = document.querySelector('[name="tahun"]').value;
    if (!nama) return;

    // Ambil kata-kata bermakna, buat singkatan
    var kata  = nama.replace(/[^a-zA-Z0-9\s]/g, '').split(/\s+/).filter(Boolean);
    var kode  = '';

    if (kata.length === 1) {
        kode = kata[0].substring(0, 3).toUpperCase();
    } else {
        kode = kata.map(k => k[0]).join('').toUpperCase();
    }

    if (tahun) kode += '-' + tahun;
    kodeField.value = kode;
});

document.querySelector('[name="kode"]').addEventListener('input', function () {
    this.dataset.manual = '1'; // tandai sudah diisi manual
    this.value = this.value.toUpperCase();
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>