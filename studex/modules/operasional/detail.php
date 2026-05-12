<?php
// ============================================================
//  STUDEX — Student Index
//  modules/operasional/detail.php — Detail Kegiatan Operasional
//  Stepper 3 fase: Pra → Operasional → Pasca
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

if (!$id) {
    setFlash('error', 'ID kegiatan tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

// ============================================================
// AMBIL DATA UTAMA
// ============================================================
$stmt = $db->prepare("
    SELECT o.*,
           a.nama AS nama_angkatan, a.kode AS kode_angkatan,
           u.nama AS nama_pembuat
    FROM operasional o
    LEFT JOIN angkatan a ON a.id = o.angkatan_id
    LEFT JOIN users    u ON u.id = o.created_by
    WHERE o.id = ?
");
$stmt->execute([$id]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

// ============================================================
// DATA TIAP FASE
// ============================================================

// --- PRA ---
$pra = $db->prepare("SELECT * FROM operasional_pra WHERE operasional_id = ?");
$pra->execute([$id]);
$pra = $pra->fetch();

$pesertaStmt = $db->prepare("
    SELECT op.*, s.nama AS nama_siswa, s.nis
    FROM operasional_peserta op
    JOIN siswa s ON s.id = op.siswa_id
    WHERE op.operasional_id = ?
    ORDER BY s.nama ASC
");
$pesertaStmt->execute([$id]);
$pesertaList = $pesertaStmt->fetchAll();

$perlengkapanStmt = $db->prepare("
    SELECT * FROM operasional_perlengkapan
    WHERE operasional_id = ?
    ORDER BY jenis, nama_item
");
$perlengkapanStmt->execute([$id]);
$perlengkapanList = $perlengkapanStmt->fetchAll();

$perlengkapanByJenis = ['pribadi' => [], 'regu' => []];
foreach ($perlengkapanList as $p) {
    $perlengkapanByJenis[$p['jenis']][] = $p;
}

// --- OPERASIONAL ---
$laporanStmt = $db->prepare("
    SELECT ol.*, u.nama AS nama_uploader
    FROM operasional_laporan ol
    LEFT JOIN users u ON u.id = ol.uploaded_by
    WHERE ol.operasional_id = ?
    ORDER BY ol.created_at DESC
");
$laporanStmt->execute([$id]);
$laporanList = $laporanStmt->fetchAll();

// --- PASCA ---
$checklistStmt = $db->prepare("
    SELECT oc.*, op.nama_item, op.jenis
    FROM operasional_checklist oc
    JOIN operasional_perlengkapan op ON op.id = oc.perlengkapan_id
    WHERE oc.operasional_id = ?
    ORDER BY op.jenis, op.nama_item
");
$checklistStmt->execute([$id]);
$checklistList = $checklistStmt->fetchAll();

$checklistStat = ['layak' => 0, 'tidak_layak' => 0, 'butuh_perbaikan' => 0];
foreach ($checklistList as $c) {
    if (isset($checklistStat[$c['kondisi']])) $checklistStat[$c['kondisi']]++;
}

// ============================================================
// STEPPER: tentukan fase & progress
// ============================================================
$faseOrder = ['pra' => 1, 'operasional' => 2, 'pasca' => 3];
$faseNow   = $faseOrder[$ops['fase']] ?? 1;

$steps = [
    ['key' => 'pra',         'label' => 'Pra-Operasional', 'icon' => '📋', 'url' => url('modules/operasional/pra/index.php?ops_id=' . $id)],
    ['key' => 'operasional', 'label' => 'Operasional',     'icon' => '🏕️', 'url' => url('modules/operasional/ops/index.php?ops_id=' . $id)],
    ['key' => 'pasca',       'label' => 'Pasca-Operasional','icon' => '✅', 'url' => url('modules/operasional/pasca/index.php?ops_id=' . $id)],
];

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Detail Operasional';
$activePage  = 'operasional';
$breadcrumbs = [
    ['label' => 'Dashboard',   'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional', 'url' => url('modules/operasional/index.php')],
    ['label' => truncate($ops['nama_kegiatan'], 40)],
];

ob_start();
?>

<!-- ============================================================
     HEADER
     ============================================================ -->
<div class="flex items-center justify-between mb-6">
    <div>
        <div class="flex items-center gap-3 mb-1">
            <h2 class="page-title" style="margin:0;"><?= e($ops['nama_kegiatan']) ?></h2>
            <?= statusBadge($ops['status']) ?>
        </div>
        <p class="page-subtitle">
            <?= $ops['nama_angkatan'] ? e($ops['nama_angkatan']) . ' &bull; ' : '' ?>
            <?= formatTanggal($ops['tanggal_mulai']) ?>
            <?= $ops['tanggal_selesai'] ? ' &ndash; ' . formatTanggal($ops['tanggal_selesai']) : '' ?>
            <?= $ops['lokasi'] ? ' &bull; 📍 ' . e($ops['lokasi']) : '' ?>
        </p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/operasional/edit.php?id=' . $id) ?>" class="btn btn-outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Edit
        </a>
        <?php
        // Tombol aksi sesuai fase aktif
        $aktifStep = $steps[$faseNow - 1];
        ?>
        <a href="<?= $aktifStep['url'] ?>" class="btn btn-primary">
            <?= $aktifStep['icon'] ?> Kelola <?= $aktifStep['label'] ?>
        </a>
    </div>
</div>

<!-- ============================================================
     STEPPER 3 FASE
     ============================================================ -->
<div class="card mb-5">
    <div class="stepper">
        <?php foreach ($steps as $i => $step):
            $stepNum  = $i + 1;
            $isDone   = $stepNum < $faseNow;
            $isActive = $stepNum === $faseNow;
            $cls      = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
        ?>
        <a href="<?= $step['url'] ?>" class="step step-<?= $cls ?>">
            <div class="step-circle">
                <?php if ($isDone): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                <?php else: ?>
                    <?= $stepNum ?>
                <?php endif; ?>
            </div>
            <div class="step-info">
                <div class="step-label"><?= $step['icon'] ?> <?= $step['label'] ?></div>
                <div class="step-state">
                    <?= $isDone ? 'Selesai' : ($isActive ? 'Sedang Berjalan' : 'Belum Dimulai') ?>
                </div>
            </div>
        </a>
        <?php if ($i < count($steps) - 1): ?>
            <div class="step-connector <?= $isDone ? 'done' : '' ?>"></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============================================================
     KONTEN 2 KOLOM
     ============================================================ -->
<div class="grid grid-2 gap-5">

    <!-- KIRI: Info Umum + Peserta -->
    <div class="flex flex-col gap-5">

        <!-- Info Kegiatan -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Informasi Kegiatan</div>
            </div>
            <dl class="detail-list">
                <dt>Angkatan</dt>
                <dd>
                    <?php if ($ops['nama_angkatan']): ?>
                        <span class="badge badge-secondary"><?= e($ops['kode_angkatan']) ?></span>
                        <?= e($ops['nama_angkatan']) ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </dd>
                <dt>Tanggal Mulai</dt>
                <dd><?= formatTanggal($ops['tanggal_mulai']) ?></dd>
                <dt>Tanggal Selesai</dt>
                <dd><?= $ops['tanggal_selesai'] ? formatTanggal($ops['tanggal_selesai']) : '<span class="text-muted">—</span>' ?></dd>
                <dt>Lokasi</dt>
                <dd><?= $ops['lokasi'] ? e($ops['lokasi']) : '<span class="text-muted">—</span>' ?></dd>
                <dt>Fase Saat Ini</dt>
                <dd><?= faseBadge($ops['fase']) ?></dd>
                <dt>Status</dt>
                <dd><?= statusBadge($ops['status']) ?></dd>
                <dt>Dibuat Oleh</dt>
                <dd><?= e($ops['nama_pembuat']) ?></dd>
                <dt>Dibuat Pada</dt>
                <dd class="text-muted" style="font-size:13px;"><?= formatTanggal($ops['created_at']) ?></dd>
            </dl>

            <?php if ($ops['deskripsi']): ?>
                <div style="padding: 0 24px 24px;">
                    <div class="form-label mb-2">Deskripsi</div>
                    <div class="text-block"><?= nl2br(e($ops['deskripsi'])) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Peserta -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Peserta</div>
                <div class="flex items-center gap-2">
                    <span class="badge badge-secondary"><?= count($pesertaList) ?> siswa</span>
                    <a href="<?= url('modules/operasional/pra/peserta.php?ops_id=' . $id) ?>"
                       class="btn btn-sm btn-outline">Kelola</a>
                </div>
            </div>

            <?php if (empty($pesertaList)): ?>
                <div class="empty-state" style="padding:28px 24px;">
                    <div class="empty-state-icon" style="font-size:28px;">👥</div>
                    <div class="empty-state-title" style="font-size:14px;">Belum Ada Peserta</div>
                    <div class="empty-state-desc">Tambahkan peserta di tahap Pra-Operasional.</div>
                    <a href="<?= url('modules/operasional/pra/peserta.php?ops_id=' . $id) ?>"
                       class="btn btn-sm btn-primary mt-3">Tambah Peserta</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($pesertaList, 0, 8) as $p): ?>
                            <tr>
                                <td class="text-muted" style="font-size:12px;"><?= e($p['nis']) ?></td>
                                <td><?= e($p['nama_siswa']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($pesertaList) > 8): ?>
                    <div style="padding:10px 20px; border-top:1px solid var(--border);">
                        <a href="<?= url('modules/operasional/pra/peserta.php?ops_id=' . $id) ?>"
                           class="text-muted" style="font-size:13px;">
                            + <?= count($pesertaList) - 8 ?> peserta lainnya →
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- KANAN: Perlengkapan + Laporan + Checklist Pasca -->
    <div class="flex flex-col gap-5">

        <!-- Pra: Data Pra-Operasional -->
        <?php if ($pra): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Pra-Operasional</div>
                <a href="<?= url('modules/operasional/pra/index.php?ops_id=' . $id) ?>"
                   class="btn btn-sm btn-outline">Detail</a>
            </div>
            <dl class="detail-list" style="padding-bottom:16px;">
                <?php if ($pra['tanggal_briefing']): ?>
                    <dt>Tgl Briefing</dt>
                    <dd><?= formatTanggal($pra['tanggal_briefing']) ?></dd>
                <?php endif; ?>
                <?php if ($pra['penanggung_jawab']): ?>
                    <dt>PJ Kegiatan</dt>
                    <dd><?= e($pra['penanggung_jawab']) ?></dd>
                <?php endif; ?>
                <dt>Perlengkapan</dt>
                <dd>
                    <span class="badge badge-secondary"><?= count($perlengkapanList) ?> item</span>
                    (<?= count($perlengkapanByJenis['pribadi']) ?> pribadi,
                     <?= count($perlengkapanByJenis['regu']) ?> regu)
                </dd>
            </dl>
        </div>
        <?php endif; ?>

        <!-- Laporan Operasional -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏕️ Laporan Operasional</div>
                <a href="<?= url('modules/operasional/ops/upload_laporan.php?ops_id=' . $id) ?>"
                   class="btn btn-sm btn-outline">+ Upload</a>
            </div>

            <?php if (empty($laporanList)): ?>
                <div class="empty-state" style="padding:24px;">
                    <div class="empty-state-icon" style="font-size:26px;">📄</div>
                    <div class="empty-state-title" style="font-size:13px;">Belum Ada Laporan</div>
                    <div class="empty-state-desc" style="font-size:12px;">Upload laporan di fase Operasional.</div>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($laporanList as $l): ?>
                    <div class="list-group-item flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="file-icon">📄</div>
                            <div>
                                <div class="fw-medium" style="font-size:13px;"><?= e($l['nama_file']) ?></div>
                                <div class="text-muted" style="font-size:11px;">
                                    <?= $l['ukuran_file'] ? formatFileSize($l['ukuran_file']) . ' &bull; ' : '' ?>
                                    <?= timeAgo($l['created_at']) ?>
                                    <?= $l['nama_uploader'] ? ' &bull; ' . e($l['nama_uploader']) : '' ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($l['drive_link']): ?>
                            <a href="<?= e($l['drive_link']) ?>" target="_blank"
                               class="btn btn-sm btn-outline" title="Lihat di Drive">↗ Drive</a>
                        <?php elseif ($l['path_lokal']): ?>
                            <a href="<?= url('storage/uploads/laporan/' . basename($l['path_lokal'])) ?>"
                               target="_blank" class="btn btn-sm btn-outline">↓</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Checklist Pasca -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">✅ Checklist Pasca</div>
                <a href="<?= url('modules/operasional/pasca/index.php?ops_id=' . $id) ?>"
                   class="btn btn-sm btn-outline">Kelola</a>
            </div>

            <?php if (empty($checklistList)): ?>
                <div class="empty-state" style="padding:24px;">
                    <div class="empty-state-icon" style="font-size:26px;">🔧</div>
                    <div class="empty-state-title" style="font-size:13px;">Belum Ada Checklist</div>
                    <div class="empty-state-desc" style="font-size:12px;">Isi checklist kondisi alat di fase Pasca.</div>
                </div>
            <?php else: ?>
                <!-- Ringkasan kondisi -->
                <div class="flex gap-3" style="padding: 0 20px 16px; flex-wrap:wrap;">
                    <div class="checklist-pill pill-layak">
                        <span class="pill-num"><?= $checklistStat['layak'] ?></span>
                        <span class="pill-lbl">Layak</span>
                    </div>
                    <div class="checklist-pill pill-perbaikan">
                        <span class="pill-num"><?= $checklistStat['butuh_perbaikan'] ?></span>
                        <span class="pill-lbl">Perlu Perbaikan</span>
                    </div>
                    <div class="checklist-pill pill-rusak">
                        <span class="pill-num"><?= $checklistStat['tidak_layak'] ?></span>
                        <span class="pill-lbl">Tidak Layak</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Jenis</th>
                                <th style="text-align:center;">Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($checklistList, 0, 6) as $c): ?>
                            <tr>
                                <td><?= e($c['nama_item']) ?></td>
                                <td><span class="badge badge-secondary"><?= ucfirst($c['jenis']) ?></span></td>
                                <td style="text-align:center;"><?= kondisiBadge($c['kondisi']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($checklistList) > 6): ?>
                    <div style="padding:10px 20px; border-top:1px solid var(--border);">
                        <a href="<?= url('modules/operasional/pasca/index.php?ops_id=' . $id) ?>"
                           class="text-muted" style="font-size:13px;">
                            + <?= count($checklistList) - 6 ?> item lainnya →
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
// Helper badge fase
function faseBadge(string $fase): string {
    $map = [
        'pra'         => ['label' => 'Pra-Ops',   'class' => 'badge-info'],
        'operasional' => ['label' => 'Operasional','class' => 'badge-warning'],
        'pasca'       => ['label' => 'Pasca-Ops',  'class' => 'badge-success'],
    ];
    $cfg = $map[$fase] ?? ['label' => $fase, 'class' => 'badge-secondary'];
    return '<span class="badge ' . $cfg['class'] . '">' . $cfg['label'] . '</span>';
}

// Helper badge kondisi checklist
function kondisiBadge(string $kondisi): string {
    $map = [
        'layak'           => ['label' => 'Layak',          'class' => 'badge-success'],
        'butuh_perbaikan' => ['label' => 'Perlu Perbaikan', 'class' => 'badge-warning'],
        'tidak_layak'     => ['label' => 'Tidak Layak',    'class' => 'badge-danger'],
    ];
    $cfg = $map[$kondisi] ?? ['label' => $kondisi, 'class' => 'badge-secondary'];
    return '<span class="badge ' . $cfg['class'] . '">' . $cfg['label'] . '</span>';
}
?>

<style>
/* ---- Stepper ---- */
.stepper {
    display: flex;
    align-items: center;
    padding: 24px 28px;
    gap: 0;
}
.step {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: inherit;
    flex-shrink: 0;
}
.step-circle {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
    flex-shrink: 0;
    transition: all 0.2s;
}
.step-pending .step-circle {
    background: var(--border);
    color: var(--grey);
    border: 2px solid var(--border);
}
.step-active .step-circle {
    background: var(--army-green);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(57,89,23,.15);
}
.step-done .step-circle {
    background: var(--soft-green);
    color: var(--army-green);
    border: 2px solid var(--army-green);
}
.step-label {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
}
.step-pending .step-label { color: var(--grey); }
.step-state {
    font-size: 11px;
    color: var(--grey);
    margin-top: 2px;
}
.step-active .step-state { color: var(--army-green); font-weight: 600; }
.step-connector {
    flex: 1;
    height: 2px;
    background: var(--border);
    margin: 0 16px;
    min-width: 40px;
}
.step-connector.done { background: var(--army-green); }

/* ---- Detail list ---- */
.detail-list {
    display: grid;
    grid-template-columns: 130px 1fr;
    gap: 12px 16px;
    padding: 0 24px 24px;
}
.detail-list dt { color: var(--grey); font-size: 13px; padding-top: 2px; }
.detail-list dd { font-weight: 500; font-size: 14px; }

/* ---- Text block ---- */
.text-block {
    background: var(--app-bg);
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    line-height: 1.6;
}

/* ---- List group ---- */
.list-group-item {
    padding: 13px 20px;
    border-bottom: 1px solid var(--border);
}
.list-group-item:last-child { border-bottom: none; }
.file-icon {
    width: 34px; height: 34px;
    background: var(--primary-light);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}

/* ---- Checklist pills ---- */
.checklist-pill {
    display: flex; flex-direction: column;
    align-items: center;
    padding: 10px 16px;
    border-radius: 10px;
    min-width: 60px;
}
.pill-num { font-size: 20px; font-weight: 700; line-height: 1; }
.pill-lbl { font-size: 10px; margin-top: 3px; }
.pill-layak    { background: #E6EFEA; color: #395917; }
.pill-perbaikan{ background: #FEF3E2; color: #C97C10; }
.pill-rusak    { background: #F9E8E7; color: #8B1408; }
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>