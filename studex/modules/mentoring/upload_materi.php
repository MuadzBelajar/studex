<?php
// ============================================================
//  STUDEX — Student Index
//  modules/mentoring/upload_materi.php — Upload Materi Sesi
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/FileUpload.php';
require_once __DIR__ . '/../../core/GoogleDrive.php';

requireLogin();

$db   = db();
$user = currentUser();
$id   = sanitizeInt(get('id') ?: post('id'));

// Validasi sesi
$stmt = $db->prepare("
    SELECT m.*, a.nama AS nama_angkatan
    FROM mentoring_sesi m
    JOIN angkatan a ON a.id = m.angkatan_id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Data sesi mentoring tidak ditemukan.');
    redirect(url('modules/mentoring/index.php'));
}

$errors = [];

// ============================================================
// PROSES UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Materi bisa berbagai format: pdf, ppt, doc, video, dll
    $uploader = (new FileUpload('materi'))
        ->allowExtensions(['pdf','doc','docx','ppt','pptx','xls','xlsx','zip','rar','mp4','avi','mp3','jpg','jpeg','png'])
        ->maxMb(50);

    $result = $uploader->handleWithDrive(
        'file_materi',
        'mentoring',
        'materi',
        function ($local, $drive) use ($db, $id, $user) {
            $db->prepare("
                INSERT INTO mentoring_materi
                    (sesi_id, nama_file, path_lokal, drive_file_id,
                     drive_link, drive_folder_id, ukuran_file, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $id,
                $local['original_name'],
                $local['path'],
                $drive['file_id']         ?? null,
                $drive['link']            ?? null,
                $drive['drive_folder_id'] ?? null,
                $local['size'],
                $user['id'],
            ]);
        }
    );

    if ($result['success']) {
        setFlash('success', 'Materi berhasil diupload.');
        redirect(url('modules/mentoring/detail.php?id=' . $id));
    } else {
        $errors['file_materi'] = $result['message'];
    }
}

// Materi yang sudah ada
$existingStmt = $db->prepare("
    SELECT mm.*, u.nama AS nama_uploader
    FROM mentoring_materi mm
    JOIN users u ON u.id = mm.uploaded_by
    WHERE mm.sesi_id = ?
    ORDER BY mm.uploaded_at DESC
");
$existingStmt->execute([$id]);
$existingList = $existingStmt->fetchAll();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Upload Materi';
$activePage  = 'mentoring';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Mentoring',  'url' => url('modules/mentoring/index.php')],
    ['label' => truncate($sesi['judul'], 30), 'url' => url('modules/mentoring/detail.php?id=' . $id)],
    ['label' => 'Upload Materi'],
];

// Helper icon
function getFileIcon(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf'  => '📄',
        'doc'  => '📝', 'docx' => '📝',
        'ppt'  => '📊', 'pptx' => '📊',
        'xls'  => '📋', 'xlsx' => '📋',
        'zip'  => '🗜️', 'rar'  => '🗜️',
        'mp4'  => '🎬', 'avi'  => '🎬',
        'mp3'  => '🎵',
        'jpg'  => '🖼️', 'jpeg' => '🖼️', 'png'  => '🖼️',
    ];
    return $icons[$ext] ?? '📁';
}

ob_start();
?>

<div class="grid grid-2 gap-5" style="align-items:start;">

    <!-- Form Upload -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Upload Materi</div>
            <div class="card-subtitle">Maks. 50 MB per file</div>
        </div>

        <!-- Info Sesi -->
        <div style="padding: 0 24px 20px;">
            <div class="info-banner">
                <div class="fw-medium" style="font-size:14px;"><?= e($sesi['judul']) ?></div>
                <div class="text-muted" style="font-size:12px; margin-top:2px;">
                    <?= e($sesi['nama_angkatan']) ?> &bull; <?= formatTanggal($sesi['tanggal']) ?>
                    <?= $sesi['mentor'] ? ' &bull; 👤 ' . e($sesi['mentor']) : '' ?>
                </div>
            </div>
        </div>

        <form method="POST" action="?id=<?= $id ?>" enctype="multipart/form-data" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-group mb-5" style="padding: 0 24px;">
                <label class="form-label">File Materi <span class="required">*</span></label>

                <!-- Drop Zone -->
                <div class="drop-zone <?= isset($errors['file_materi']) ? 'is-invalid' : '' ?>"
                     id="dropZone"
                     onclick="document.getElementById('fileInput').click()">
                    <input type="file" name="file_materi" id="fileInput"
                           accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.rar,.mp4,.avi,.mp3,.jpg,.jpeg,.png"
                           style="display:none;">
                    <div class="drop-zone-icon" id="dropIcon">📁</div>
                    <div class="drop-zone-text">Klik atau seret file ke sini</div>
                    <div class="drop-zone-hint" id="fileHint">Maks. 50 MB</div>
                    <button type="button" class="btn btn-sm btn-outline mt-3"
                            onclick="event.stopPropagation(); document.getElementById('fileInput').click()">
                        Pilih File
                    </button>
                </div>

                <?php if (isset($errors['file_materi'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['file_materi']) ?></div>
                <?php endif; ?>

                <!-- Format yang didukung -->
                <div class="format-tags mt-3">
                    <?php foreach ([
                        'PDF' => '📄', 'Word' => '📝', 'PowerPoint' => '📊',
                        'Excel' => '📋', 'Video' => '🎬', 'Audio' => '🎵',
                        'Gambar' => '🖼️', 'ZIP/RAR' => '🗜️',
                    ] as $label => $icon): ?>
                        <span class="format-tag"><?= $icon ?> <?= $label ?></span>
                    <?php endforeach; ?>
                </div>

                <?php if (GoogleDrive::isEnabled()): ?>
                    <div class="form-hint mt-2">☁️ File akan otomatis diupload ke Google Drive.</div>
                <?php else: ?>
                    <div class="form-hint mt-2">💾 File akan disimpan di server lokal.</div>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3" style="padding: 0 24px 24px;">
                <button type="submit" class="btn btn-primary" id="uploadBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 16 12 12 8 16"/>
                        <line x1="12" y1="12" x2="12" y2="21"/>
                        <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                    </svg>
                    Upload Materi
                </button>
                <a href="<?= url('modules/mentoring/detail.php?id=' . $id) ?>" class="btn btn-secondary">
                    Batal
                </a>
            </div>
        </form>
    </div>

    <!-- Daftar Materi -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Materi Tersimpan</div>
            <span class="badge badge-secondary"><?= count($existingList) ?> file</span>
        </div>

        <?php if (empty($existingList)): ?>
            <div class="empty-state" style="padding:32px 24px;">
                <div class="empty-state-icon" style="font-size:32px;">📭</div>
                <div class="empty-state-title" style="font-size:14px;">Belum ada materi</div>
                <div class="empty-state-desc">Upload file materi sesi pertama.</div>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($existingList as $m): ?>
                <div class="list-group-item">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="file-icon"><?= getFileIcon($m['nama_file']) ?></div>
                            <div>
                                <div class="fw-medium" style="font-size:13px;">
                                    <?= e(truncate($m['nama_file'], 35)) ?>
                                </div>
                                <div class="text-muted" style="font-size:11px;">
                                    <?= $m['ukuran_file'] ? formatFileSize($m['ukuran_file']) . ' &bull; ' : '' ?>
                                    <?= timeAgo($m['uploaded_at']) ?> &bull;
                                    <?= e($m['nama_uploader']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2" style="flex-shrink:0;">
                            <?php if ($m['drive_link']): ?>
                                <a href="<?= e($m['drive_link']) ?>" target="_blank"
                                   class="btn btn-sm btn-outline" title="Lihat di Drive">
                                    ↗ Drive
                                </a>
                            <?php elseif ($m['path_lokal']): ?>
                                <a href="<?= url('storage/uploads/materi/' . basename($m['path_lokal'])) ?>"
                                   target="_blank" class="btn btn-sm btn-outline">
                                    ↓ Download
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
.info-banner {
    background: var(--primary-light);
    border-left: 3px solid var(--army-green);
    border-radius: 8px;
    padding: 12px 16px;
}
.drop-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 32px 24px;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    user-select: none;
}
.drop-zone:hover, .drop-zone.drag-over {
    border-color: var(--army-green);
    background: var(--primary-light);
}
.drop-zone.is-invalid { border-color: var(--red); }
.drop-zone-icon  { font-size: 36px; margin-bottom: 8px; }
.drop-zone-text  { font-weight: 600; font-size: 14px; color: var(--text-primary); }
.drop-zone-hint  { font-size: 12px; color: var(--grey); margin-top: 4px; }
.format-tags     { display: flex; flex-wrap: wrap; gap: 6px; }
.format-tag {
    background: var(--app-bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 11px;
    color: var(--grey);
}
.file-icon {
    width: 36px; height: 36px;
    background: var(--primary-light);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.list-group-item {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
}
.list-group-item:last-child { border-bottom: none; }
</style>

<script>
(function () {
    const dropZone  = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileHint  = document.getElementById('fileHint');
    const dropIcon  = document.getElementById('dropIcon');

    const extIcons = {
        pdf:'📄', doc:'📝', docx:'📝', ppt:'📊', pptx:'📊',
        xls:'📋', xlsx:'📋', zip:'🗜️', rar:'🗜️',
        mp4:'🎬', avi:'🎬', mp3:'🎵',
        jpg:'🖼️', jpeg:'🖼️', png:'🖼️',
    };

    fileInput.addEventListener('change', function () {
        if (this.files.length > 0) updateUI(this.files[0]);
    });

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', function () {
        dropZone.classList.remove('drag-over');
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateUI(files[0]);
        }
    });

    function updateUI(file) {
        const ext    = file.name.split('.').pop().toLowerCase();
        const icon   = extIcons[ext] || '📁';
        const sizeKb = (file.size / 1024).toFixed(1);
        const sizeMb = (file.size / 1024 / 1024).toFixed(2);
        const size   = file.size > 1024 * 1024 ? sizeMb + ' MB' : sizeKb + ' KB';

        dropIcon.textContent  = icon;
        fileHint.textContent  = '✅ ' + file.name + ' (' + size + ')';
        fileHint.style.color  = 'var(--army-green)';
    }

    document.querySelector('form').addEventListener('submit', function () {
        const btn = document.getElementById('uploadBtn');
        btn.disabled    = true;
        btn.textContent = 'Mengupload...';
    });
})();
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>