<?php
// ============================================================
//  STUDEX — Student Index
//  modules/angkatan/edit.php — Edit Angkatan
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireSuperAdmin();

$db = db();
$id = sanitizeInt(get('id'));

if (!$id) {
    setFlash('error', 'ID angkatan tidak valid.');
    redirect(url('modules/angkatan/index.php'));
}

// Ambil data angkatan
$stmt = $db->prepare("SELECT * FROM angkatan WHERE id = ?");
$stmt->execute([$id]);
$angkatan = $stmt->fetch();

if (!$angkatan) {
    setFlash('error', 'Angkatan tidak ditemukan.');
    redirect(url('modules/angkatan/index.php'));
}

// Statistik — untuk ditampilkan di header
$stats = $db->prepare("
    SELECT
        COUNT(DISTINCT s.id) as total_siswa,
        COUNT(DISTINCT r.id) as total_rabuan,
        COUNT(DISTINCT m.id) as total_mentoring,
        COUNT(DISTINCT b.id) as total_binjas
    FROM angkatan a
    LEFT JOIN siswa          s ON s.angkatan_id = a.id
    LEFT JOIN rabuan         r ON r.angkatan_id = a.id
    LEFT JOIN mentoring_sesi m ON m.angkatan_id = a.id
    LEFT JOIN binjas_sesi    b ON b.angkatan_id = a.id
    WHERE a.id = ?
    GROUP BY a.id
");
$stats->execute([$id]);
$stats = $stats->fetch();

$errors = [];
$input  = $angkatan; // Default dari data existing

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

    // Cek kode unik (exclude diri sendiri)
    if (!isset($errors['kode'])) {
        $cek = $db->prepare("SELECT id FROM angkatan WHERE kode = ? AND id != ?");
        $cek->execute([$input['kode'], $id]);
        if ($cek->fetch()) $errors['kode'] = 'Kode angkatan sudah digunakan.';
    }

    if (empty($errors)) {
        $db->prepare("
            UPDATE angkatan
            SET nama = ?, kode = ?, tahun = ?, deskripsi = ?, is_aktif = ?
            WHERE id = ?
        ")->execute([
            $input['nama'],
            $input['kode'],
            $input['tahun'],
            $input['deskripsi'] ?: null,
            $input['is_aktif'],
            $id,
        ]);

        setFlash('success', 'Angkatan ' . $input['nama'] . ' berhasil diperbarui.');
        redirect(url('modules/angkatan/index.php'));
    }
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Edit Angkatan';
$activePage  = 'siswa';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Angkatan',  'url' => url('modules/angkatan/index.php')],
    ['label' => e($angkatan['nama'])],
    ['label' => 'Edit'],
];

ob_start();
?>

<div class="grid grid-2-1 gap-5" style="max-width:900px;">

    <!-- FORM -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-left">
                <div style="width:44px;height:44px;border-radius:var(--border-radius-lg);
                            background:var(--primary-light);display:flex;align-items:center;
                            justify-content:center;flex-shrink:0;">
                    <span style="font-family:var(--font-heading);font-size:var(--text-sm);
                                 font-weight:var(--fw-bold);color:var(--primary);">
                        <?= substr($angkatan['tahun'], 2) ?>
                    </span>
                </div>
                <div>
                    <div class="card-title"><?= e($angkatan['nama']) ?></div>
                    <div style="font-size:var(--text-xs);color:var(--text-muted);">
                        <?= e($angkatan['kode']) ?>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="" novalidate>
            <?= csrfField() ?>

            <!-- Nama -->
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
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Tahun <span class="required">*</span>
                    </label>
                    <input type="number" name="tahun"
                           class="form-control <?= isset($errors['tahun']) ? 'is-invalid' : '' ?>"
                           value="<?= e($input['tahun']) ?>"
                           min="2000" max="2100">
                    <?php if (isset($errors['tahun'])): ?>
                        <div class="form-feedback invalid"><?= e($errors['tahun']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deskripsi -->
            <div class="form-group mb-5">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" class="form-control" rows="3"
                          placeholder="Deskripsi singkat (opsional)"><?= e($input['deskripsi'] ?? '') ?></textarea>
            </div>

            <!-- Status -->
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
                <div class="form-hint">
                    Menonaktifkan angkatan akan menyembunyikannya dari dropdown pemilihan.
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" width="16" height="16"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Simpan Perubahan
                </button>
                <a href="<?= url('modules/angkatan/index.php') ?>" class="btn btn-secondary">
                    Batal
                </a>
            </div>
        </form>
    </div>

    <!-- INFO SIDEBAR -->
    <div>
        <!-- Statistik angkatan -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title" style="font-size:var(--text-sm);">Statistik Angkatan</div>
            </div>
            <div style="display:flex;flex-direction:column;gap:var(--space-3);">
                <?php foreach ([
                    ['Siswa',     $stats['total_siswa'],     url('modules/siswa/index.php?angkatan_id='.$id)],
                    ['Rabuan',    $stats['total_rabuan'],    url('modules/rabuan/index.php')],
                    ['Mentoring', $stats['total_mentoring'], url('modules/mentoring/index.php')],
                    ['Binjas',    $stats['total_binjas'],    url('modules/binjas/index.php')],
                ] as [$lbl, $val, $link]): ?>
                <div class="flex items-center justify-between">
                    <span style="font-size:var(--text-sm);color:var(--text-secondary);">
                        <?= $lbl ?>
                    </span>
                    <a href="<?= $link ?>"
                       style="font-size:var(--text-sm);font-weight:var(--fw-semibold);
                              color:var(--primary);text-decoration:none;">
                        <?= formatAngka($val ?? 0) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Peringatan hapus -->
        <?php if (($stats['total_siswa'] ?? 0) > 0): ?>
        <div class="alert alert-warning" style="font-size:var(--text-sm);">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" width="18" height="18"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <div>
                Angkatan ini memiliki <strong><?= $stats['total_siswa'] ?> siswa</strong>.
                Tidak dapat dihapus selama masih ada siswa terdaftar.
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title" style="font-size:var(--text-sm);color:var(--color-danger);">
                    Zona Berbahaya
                </div>
            </div>
            <p style="font-size:var(--text-sm);color:var(--text-muted);margin-bottom:var(--space-4);">
                Angkatan ini belum memiliki siswa dan dapat dihapus.
            </p>
            <button class="btn btn-danger-outline btn-sm"
                    data-confirm
                    data-type="danger"
                    data-title="Hapus Angkatan"
                    data-message="Yakin ingin menghapus angkatan <?= e(addslashes($angkatan['nama'])) ?>? Tindakan ini tidak dapat dibatalkan."
                    data-action="<?= url('modules/angkatan/delete.php') ?>"
                    data-id="<?= $id ?>"
                    data-label="Ya, Hapus Angkatan">
                Hapus Angkatan Ini
            </button>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
// Auto uppercase kode
document.querySelector('[name="kode"]').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>