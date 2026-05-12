<?php
define('STUDEX', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/google_drive.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Helpers.php';
require_once __DIR__ . '/../../../core/FileUpload.php';
require_once __DIR__ . '/../../../core/GoogleDrive.php';

requireAdmin();

$db    = db();
$opsId = sanitizeInt(get('ops_id') ?: post('ops_id'));

if (!$opsId) {
    setFlash('error', 'ID kegiatan tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

$stmt = $db->prepare("
    SELECT o.*, a.nama AS nama_angkatan
    FROM operasional o
    LEFT JOIN angkatan a ON a.id = o.angkatan_id
    WHERE o.id = ?
");
$stmt->execute([$opsId]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

// Hanya bisa upload saat fase operasional atau pasca
if (!in_array($ops['fase'], ['operasional', 'pasca'])) {
    setFlash('error', 'Laporan hanya dapat diunggah pada fase Operasional atau Pasca-Operasional.');
    redirect(url('modules/operasional/detail.php?id=' . $opsId));
}

$errors = [];
$input  = ['judul' => '', 'keterangan' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'judul'      => sanitize(post('judul')),
        'keterangan' => sanitize(post('keterangan')),
    ];

    if (!$input['judul']) $errors['judul'] = 'Judul laporan wajib diisi.';

    // Cek file diupload
    if (empty($_FILES['file_laporan']['name'])) {
        $errors['file_laporan'] = 'File laporan wajib diunggah.';
    }

    if (empty($errors)) {
        $userId   = currentUserId();
        $prefix   = 'laporan_' . $opsId . '_' . date('Ymd');

        $uploader = (new FileUpload('laporan'))
            ->allowExtensions(['pdf'])
            ->maxMb(20);

        $result = $uploader->handleWithDrive(
            'file_laporan',
            'operasional',
            $prefix,
            function ($local, $drive) use ($db, $opsId, $userId, $input) {
                $db->prepare("
                    INSERT INTO operasional_laporan
                        (operasional_id, nama_file, judul, keterangan,
                         path_lokal, drive_file_id, drive_link,
                         drive_folder_id, ukuran_file, uploaded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $opsId,
                    $local['original_name'],
                    $input['judul'],
                    $input['keterangan'],
                    $local['path'],
                    $drive['file_id']      ?? null,
                    $drive['link']         ?? null,
                    $drive['folder_id']    ?? null,
                    $local['size'],
                    $userId,
                ]);
            }
        );

        // Jika Drive tidak aktif, simpan lokal saja
        if (!$result['success'] && !GoogleDrive::isEnabled()) {
            // Upload lokal saja
            $uploader2 = (new FileUpload('laporan'))
                ->allowExtensions(['pdf'])
                ->maxMb(20);
            $localResult = $uploader2->handle('file_laporan', $prefix);

            if ($localResult['success']) {
                $local = $localResult['file'];
                $db->prepare("
                    INSERT INTO operasional_laporan
                        (operasional_id, nama_file, judul, keterangan,
                         path_lokal, ukuran_file, uploaded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $opsId,
                    $local['original_name'],
                    $input['judul'],
                    $input['keterangan'],
                    $local['path'],
                    $local['size'],
                    $userId,
                ]);
                setFlash('success', 'Laporan berhasil diunggah (lokal).');
                redirect(url('modules/operasional/ops/index.php?ops_id=' . $opsId));
            } else {
                $errors['file_laporan'] = $localResult['message'];
            }
        } elseif ($result['success']) {
            setFlash('success', 'Laporan berhasil diunggah' . (GoogleDrive::isEnabled() ? ' ke Google Drive.' : '.'));
            redirect(url('modules/operasional/ops/index.php?ops_id=' . $opsId));
        } else {
            $errors['file_laporan'] = $result['message'];
        }
    }
}

// Laporan yang sudah ada
$laporanList = $db->prepare("
    SELECT ol.*, u.nama AS uploaded_by_nama
    FROM operasional_laporan ol
    LEFT JOIN users u ON u.id = ol.uploaded_by
    WHERE ol.operasional_id = ?
    ORDER BY ol.created_at DESC
");
$laporanList->execute([$opsId]);
$laporanList = $laporanList->fetchAll();

$pageTitle    = 'Upload Laporan';
$pageSubtitle = e($ops['nama_kegiatan']);
$activePage   = 'operasional';
$breadcrumbs  = [
    ['label' => 'Dashboard',        'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional',      'url' => url('modules/operasional/index.php')],
    ['label' => e($ops['nama_kegiatan']), 'url' => url('modules/operasional/detail.php?id=' . $opsId)],
    ['label' => 'Upload Laporan'],
];

ob_start();
?>

<div class="grid grid-2 gap-4" style="align-items:start;">

    <!-- ── Form Upload ── -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Upload Laporan Kegiatan</h3>
            <?php if (GoogleDrive::isEnabled()): ?>
                <span class="badge badge-success">Drive Aktif</span>
            <?php else: ?>
                <span class="badge badge-secondary">Lokal</span>
            <?php endif; ?>
        </div>
        <div class="card-body">

            <?php if (GoogleDrive::isEnabled()): ?>
                <div class="alert alert-info mb-4">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>File PDF akan diunggah ke Google Drive dan link akan disimpan.</span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="ops_id" value="<?= $opsId ?>">

                <div class="form-group">
                    <label class="form-label">Judul Laporan <span class="required">*</span></label>
                    <input type="text" name="judul"
                           class="form-control <?= isset($errors['judul']) ? 'is-invalid' : '' ?>"
                           placeholder="Contoh: Laporan Akhir Operasi Hutan Pinus 2025"
                           value="<?= e($input['judul']) ?>">
                    <?php if (isset($errors['judul'])): ?>
                        <div class="invalid-feedback"><?= e($errors['judul']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="3"
                              placeholder="Deskripsi singkat isi laporan…"><?= e($input['keterangan']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">File Laporan (PDF) <span class="required">*</span></label>
                    <div class="file-upload-area <?= isset($errors['file_laporan']) ? 'is-invalid' : '' ?>"
                         onclick="document.getElementById('file_laporan').click()">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                             style="color:var(--grey);margin-bottom:8px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p id="fileName" style="font-size:13px;color:var(--grey);">
                            Klik untuk pilih file PDF (maks. 20 MB)
                        </p>
                    </div>
                    <input type="file" id="file_laporan" name="file_laporan"
                           accept=".pdf" style="display:none;"
                           onchange="document.getElementById('fileName').textContent = this.files[0]?.name || 'Klik untuk pilih file PDF'">
                    <?php if (isset($errors['file_laporan'])): ?>
                        <div class="invalid-feedback" style="display:block;"><?= e($errors['file_laporan']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="<?= url('modules/operasional/ops/index.php?ops_id=' . $opsId) ?>"
                       class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Upload Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Daftar Laporan ── -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Laporan Tersimpan</h3>
            <span class="badge badge-info"><?= count($laporanList) ?> file</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($laporanList)): ?>
                <div class="empty-state empty-state--sm">
                    <p class="empty-desc">Belum ada laporan diunggah.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Judul</th>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Diupload</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($laporanList as $i => $lap): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= e($lap['judul'] ?? '-') ?></td>
                                    <td style="font-size:12px;color:var(--grey);">
                                        <?= e($lap['nama_file'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <?= isset($lap['ukuran_file'])
                                            ? number_format($lap['ukuran_file'] / 1024, 1) . ' KB'
                                            : '-' ?>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?= e($lap['uploaded_by_nama'] ?? '-') ?><br>
                                        <span class="text-muted"><?= formatTanggal($lap['created_at'] ?? '') ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($lap['drive_link'])): ?>
                                            <a href="<?= e($lap['drive_link']) ?>" target="_blank"
                                               class="btn btn-xs btn-secondary">Drive ↗</a>
                                        <?php elseif (!empty($lap['path_lokal'])): ?>
                                            <a href="<?= url($lap['path_lokal']) ?>" target="_blank"
                                               class="btn btn-xs btn-secondary">Lihat ↗</a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.file-upload-area {
    border: 2px dashed var(--primary-light);
    border-radius: 12px;
    padding: 32px;
    text-align: center;
    cursor: pointer;
    transition: background .15s, border-color .15s;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.file-upload-area:hover { background: var(--primary-light); border-color: var(--primary); }
.file-upload-area.is-invalid { border-color: var(--danger); }
.btn-xs { font-size: 11px; padding: 3px 8px; border-radius: 6px; }
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';