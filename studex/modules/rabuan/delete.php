<?php
// ============================================================
//  STUDEX — Student Index
//  modules/rabuan/detail.php — Detail Rapat Rabuan
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db   = db();
$user = currentUser();
$id   = sanitizeInt(get('id'));

// Ambil data rabuan
$stmt = $db->prepare("
    SELECT r.*,
           a.nama AS nama_angkatan, a.kode AS kode_angkatan,
           u.nama AS nama_pembuat
    FROM rabuan r
    JOIN angkatan a ON a.id = r.angkatan_id
    JOIN users    u ON u.id = r.created_by
    WHERE r.id = ?
");
$stmt->execute([$id]);
$rabuan = $stmt->fetch();

if (!$rabuan) {
    setFlash('error', 'Data rapat tidak ditemukan.');
    redirect(url('modules/rabuan/index.php'));
}

// Ambil notulensi
$notulensiStmt = $db->prepare("
    SELECT n.*, u.nama AS nama_uploader
    FROM rabuan_notulensi n
    JOIN users u ON u.id = n.uploaded_by
    WHERE n.rabuan_id = ?
    ORDER BY n.uploaded_at DESC
");
$notulensiStmt->execute([$id]);
$notulensiList = $notulensiStmt->fetchAll();

// Ambil presensi
$presensiStmt = $db->prepare("
    SELECT p.*, s.nama AS nama_siswa, s.nis
    FROM presensi p
    JOIN siswa s ON s.id = p.siswa_id
    WHERE p.referensi_id = ? AND p.modul = 'rabuan'
    ORDER BY s.nama ASC
");
$presensiStmt->execute([$id]);
$presensiList = $presensiStmt->fetchAll();

// Hitung statistik presensi
$statPresensi  = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0];
foreach ($presensiList as $p) {
    if (isset($statPresensi[$p['status']])) $statPresensi[$p['status']]++;
}
$totalPresensi = array_sum($statPresensi);

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Detail Rabuan';
$activePage  = 'rabuan';
$breadcrumbs = [
    ['label' => 'Dashboard',    'url' => url('modules/dashboard/index.php')],
    ['label' => 'Rapat Rabuan', 'url' => url('modules/rabuan/index.php')],
    ['label' => truncate($rabuan['judul'], 40)],
];

ob_start();
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <div class="flex items-center gap-3 mb-1">
            <h2 class="page-title" style="margin:0;"><?= e($rabuan['judul']) ?></h2>
            <?= statusBadge($rabuan['status']) ?>
        </div>
        <p class="page-subtitle">
            <?= e($rabuan['nama_angkatan']) ?> &bull;
            <?= formatTanggal($rabuan['tanggal']) ?>
            <?php if ($rabuan['waktu_mulai']): ?>
                &bull; <?= formatWaktu($rabuan['waktu_mulai']) ?>
                <?= $rabuan['waktu_selesai'] ? '&ndash; ' . formatWaktu($rabuan['waktu_selesai']) : '' ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/rabuan/presensi.php?id=' . $id) ?>" class="btn btn-outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Presensi
        </a>
        <a href="<?= url('modules/rabuan/edit.php?id=' . $id) ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Edit
        </a>
    </div>
</div>

<div class="grid grid-2 gap-5">

    <!-- KIRI: Info + Notulensi -->
    <div class="flex flex-col gap-5">

        <!-- Informasi Rapat -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Informasi Rapat</div>
            </div>
            <dl class="detail-list">
                <dt>Angkatan</dt>
                <dd>
                    <span class="badge badge-secondary"><?= e($rabuan['kode_angkatan']) ?></span>
                    <?= e($rabuan['nama_angkatan']) ?>
                </dd>
                <dt>Tanggal</dt>
                <dd><?= formatTanggal($rabuan['tanggal']) ?></dd>
                <dt>Waktu</dt>
                <dd>
                    <?php if ($rabuan['waktu_mulai']): ?>
                        <?= formatWaktu($rabuan['waktu_mulai']) ?>
                        <?= $rabuan['waktu_selesai'] ? ' &ndash; ' . formatWaktu($rabuan['waktu_selesai']) : '' ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </dd>
                <dt>Lokasi</dt>
                <dd><?= $rabuan['lokasi'] ? e($rabuan['lokasi']) : '<span class="text-muted">—</span>' ?></dd>
                <dt>Status</dt>
                <dd><?= statusBadge($rabuan['status']) ?></dd>
                <dt>Dibuat Oleh</dt>
                <dd><?= e($rabuan['nama_pembuat']) ?></dd>
                <dt>Dibuat Pada</dt>
                <dd class="text-muted" style="font-size:13px;"><?= formatTanggal($rabuan['created_at']) ?></dd>
            </dl>

            <?php if ($rabuan['agenda']): ?>
                <div style="padding: 0 24px 24px;">
                    <div class="form-label mb-2">Agenda</div>
                    <div class="text-block"><?= nl2br(e($rabuan['agenda'])) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Notulensi -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Notulensi</div>
                <a href="<?= url('modules/rabuan/upload_notulensi.php?id=' . $id) ?>"
                   class="btn btn-sm btn-outline">
                    + Upload
                </a>
            </div>

            <?php if (empty($notulensiList)): ?>
                <div class="empty-state" style="padding:32px 24px;">
                    <div class="empty-state-icon" style="font-size:32px;">📄</div>
                    <div class="empty-state-title" style="font-size:15px;">Belum Ada Notulensi</div>
                    <div class="empty-state-desc">Upload notulensi rapat dalam format PDF.</div>
                    <a href="<?= url('modules/rabuan/upload_notulensi.php?id=' . $id) ?>"
                       class="btn btn-sm btn-primary mt-3">Upload Sekarang</a>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notulensiList as $n): ?>
                    <div class="list-group-item flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="file-icon">📄</div>
                            <div>
                                <div class="fw-medium" style="font-size:13px;"><?= e($n['nama_file']) ?></div>
                                <div class="text-muted" style="font-size:11px;">
                                    <?= $n['ukuran_file'] ? formatFileSize($n['ukuran_file']) . ' &bull; ' : '' ?>
                                    Diupload <?= timeAgo($n['uploaded_at']) ?>
                                    oleh <?= e($n['nama_uploader']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($n['drive_link']): ?>
                                <a href="<?= e($n['drive_link']) ?>" target="_blank"
                                   class="btn btn-sm btn-outline" title="Lihat di Google Drive">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                        <polyline points="15 3 21 3 21 9"/>
                                        <line x1="10" y1="14" x2="21" y2="3"/>
                                    </svg>
                                    Drive
                                </a>
                            <?php elseif ($n['path_lokal']): ?>
                                <a href="<?= url('storage/uploads/notulensi/' . basename($n['path_lokal'])) ?>"
                                   target="_blank" class="btn btn-sm btn-outline" title="Download">
                                    Download
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- KANAN: Presensi -->
    <div class="flex flex-col gap-5">

        <div class="card">
            <div class="card-header">
                <div class="card-title">Rekap Presensi</div>
                <?php if ($totalPresensi > 0): ?>
                    <span class="badge badge-secondary"><?= $totalPresensi ?> siswa</span>
                <?php endif; ?>
            </div>

            <?php if ($totalPresensi === 0): ?>
                <div class="empty-state" style="padding:32px 24px;">
                    <div class="empty-state-icon" style="font-size:32px;">👥</div>
                    <div class="empty-state-title" style="font-size:15px;">Belum Ada Presensi</div>
                    <div class="empty-state-desc">Input presensi siswa untuk rapat ini.</div>
                    <a href="<?= url('modules/rabuan/presensi.php?id=' . $id) ?>"
                       class="btn btn-sm btn-primary mt-3">Input Presensi</a>
                </div>
            <?php else: ?>
                <!-- Stat pills -->
                <div class="stat-pills">
                    <div class="stat-pill stat-pill-success">
                        <span class="stat-pill-num"><?= $statPresensi['hadir'] ?></span>
                        <span class="stat-pill-label">Hadir</span>
                    </div>
                    <div class="stat-pill stat-pill-info">
                        <span class="stat-pill-num"><?= $statPresensi['izin'] ?></span>
                        <span class="stat-pill-label">Izin</span>
                    </div>
                    <div class="stat-pill stat-pill-warning">
                        <span class="stat-pill-num"><?= $statPresensi['sakit'] ?></span>
                        <span class="stat-pill-label">Sakit</span>
                    </div>
                    <div class="stat-pill stat-pill-danger">
                        <span class="stat-pill-num"><?= $statPresensi['alpha'] ?></span>
                        <span class="stat-pill-label">Alpha</span>
                    </div>
                </div>

                <!-- Progress bar kehadiran -->
                <?php $pctHadir = $totalPresensi > 0 ? round($statPresensi['hadir'] / $totalPresensi * 100) : 0; ?>
                <div style="padding: 0 24px 20px;">
                    <div class="flex justify-between mb-1" style="font-size:12px;">
                        <span class="text-muted">Tingkat Kehadiran</span>
                        <span class="fw-medium"><?= $pctHadir ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:<?= $pctHadir ?>%;"></div>
                    </div>
                </div>

                <!-- Tabel presensi -->
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th style="text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($presensiList as $p): ?>
                            <tr>
                                <td class="text-muted" style="font-size:12px;"><?= e($p['nis']) ?></td>
                                <td><?= e($p['nama_siswa']) ?></td>
                                <td style="text-align:center;"><?= statusBadge($p['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding:12px 24px;">
                    <a href="<?= url('modules/rabuan/presensi.php?id=' . $id) ?>"
                       class="btn btn-sm btn-outline">Edit Presensi</a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
.detail-list {
    display: grid;
    grid-template-columns: 130px 1fr;
    gap: 12px 16px;
    padding: 0 24px 24px;
}
.detail-list dt { color: var(--grey); font-size: 13px; padding-top: 2px; }
.detail-list dd { font-weight: 500; font-size: 14px; }
.text-block {
    background: var(--app-bg);
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    line-height: 1.6;
}
.list-group-item {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
}
.list-group-item:last-child { border-bottom: none; }
.file-icon {
    width: 36px; height: 36px;
    background: var(--primary-light);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.stat-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 0 24px 20px;
}
.stat-pill {
    display: flex; flex-direction: column;
    align-items: center;
    padding: 12px 18px;
    border-radius: 12px;
    min-width: 64px;
}
.stat-pill-num   { font-size: 22px; font-weight: 700; line-height: 1; }
.stat-pill-label { font-size: 11px; margin-top: 4px; }
.stat-pill-success { background: #E6EFEA; color: #395917; }
.stat-pill-info    { background: #E8F4F5; color: #3b7a7e; }
.stat-pill-warning { background: #FEF3E2; color: #C97C10; }
.stat-pill-danger  { background: #F9E8E7; color: #8B1408; }
.progress-bar {
    height: 6px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: var(--army-green);
    border-radius: 99px;
    transition: width 0.4s ease;
}
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>