<?php
define('STUDEX', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/google_drive.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Helpers.php';

requireAdmin();

$db    = db();
$opsId = sanitizeInt(get('ops_id'));

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

// Checklist yang sudah ada
$checklist = $db->prepare("
    SELECT * FROM operasional_checklist
    WHERE operasional_id = ?
    ORDER BY nama_item
");
$checklist->execute([$opsId]);
$checklist = $checklist->fetchAll();

// Perlengkapan dari pra (sebagai referensi item)
$perlengkapan = $db->prepare("
    SELECT * FROM operasional_perlengkapan
    WHERE operasional_id = ?
    ORDER BY jenis, nama_item
");
$perlengkapan->execute([$opsId]);
$perlengkapan = $perlengkapan->fetchAll();

// Laporan
$laporan = $db->prepare("
    SELECT ol.*, u.nama AS uploaded_by_nama
    FROM operasional_laporan ol
    LEFT JOIN users u ON u.id = ol.uploaded_by
    WHERE ol.operasional_id = ?
    ORDER BY ol.created_at DESC
");
$laporan->execute([$opsId]);
$laporan = $laporan->fetchAll();

// Statistik checklist
$kondisiCount = ['layak' => 0, 'tidak_layak' => 0, 'butuh_perbaikan' => 0];
foreach ($checklist as $c) {
    $kondisiCount[$c['kondisi']] = ($kondisiCount[$c['kondisi']] ?? 0) + 1;
}
$totalChecklist = array_sum($kondisiCount);

$pageTitle    = 'Pasca-Operasional';
$pageSubtitle = e($ops['nama_kegiatan']);
$activePage   = 'operasional';
$breadcrumbs  = [
    ['label' => 'Dashboard',        'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional',      'url' => url('modules/operasional/index.php')],
    ['label' => e($ops['nama_kegiatan']), 'url' => url('modules/operasional/detail.php?id=' . $opsId)],
    ['label' => 'Pasca-Operasional'],
];

ob_start();
?>

<style>
.stepper-mini { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.stepper-mini__step {
    padding:6px 16px; border-radius:20px; font-size:13px; font-weight:600;
    background:#f0f0ee; color:var(--grey); text-decoration:none; transition:background .15s;
}
.stepper-mini__step:hover   { background:var(--primary-light); color:var(--primary); }
.stepper-mini__step--done   { background:var(--secondary); color:#fff; }
.stepper-mini__step--active { background:var(--primary); color:#fff; }
.stepper-mini__arrow { color:var(--grey); font-size:14px; }

.kondisi-bar { display:flex; height:10px; border-radius:8px; overflow:hidden; gap:2px; margin-top:8px; }
.kondisi-bar__seg { transition:width .3s; }
</style>

<!-- Stepper Mini -->
<div class="stepper-mini mb-4">
    <a href="<?= url('modules/operasional/pra/index.php?ops_id=' . $opsId) ?>"
       class="stepper-mini__step stepper-mini__step--done">✓ 1. Pra-Operasional</a>
    <span class="stepper-mini__arrow">→</span>
    <a href="<?= url('modules/operasional/ops/index.php?ops_id=' . $opsId) ?>"
       class="stepper-mini__step stepper-mini__step--done">✓ 2. Operasional</a>
    <span class="stepper-mini__arrow">→</span>
    <span class="stepper-mini__step stepper-mini__step--active">3. Pasca-Operasional</span>
</div>

<!-- Info kegiatan -->
<div class="card mb-4">
    <div class="card-body">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 style="font-size:20px;font-weight:700;margin-bottom:6px;"><?= e($ops['nama_kegiatan']) ?></h2>
                <div style="display:flex;flex-wrap:wrap;gap:8px 24px;font-size:13px;color:var(--grey);">
                    <span><strong>Angkatan:</strong> <?= e($ops['nama_angkatan'] ?? '-') ?></span>
                    <span><strong>Lokasi:</strong> <?= e($ops['lokasi'] ?? '-') ?></span>
                    <span><strong>Tanggal:</strong> <?= formatTanggal($ops['tanggal_mulai']) ?>
                        <?php if (!empty($ops['tanggal_selesai'])): ?>
                            – <?= formatTanggal($ops['tanggal_selesai']) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php
            $statusCls = ['draft'=>'badge-secondary','aktif'=>'badge-primary','selesai'=>'badge-success','dibatalkan'=>'badge-danger'];
            $sc = $statusCls[$ops['status']] ?? 'badge-secondary';
            ?>
            <span class="badge <?= $sc ?>" style="font-size:13px;padding:5px 14px;">
                <?= ucfirst(e($ops['status'])) ?>
            </span>
        </div>
    </div>
</div>

<div class="grid grid-2 gap-4" style="align-items:start;">

    <!-- ── Checklist Alat ── -->
    <div>

        <!-- Ringkasan kondisi -->
        <?php if ($totalChecklist > 0): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h4 style="font-size:15px;font-weight:600;margin-bottom:12px;">Ringkasan Kondisi Alat</h4>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                    <span class="badge badge-success">
                        Layak: <?= $kondisiCount['layak'] ?>
                    </span>
                    <span class="badge badge-warning">
                        Butuh Perbaikan: <?= $kondisiCount['butuh_perbaikan'] ?>
                    </span>
                    <span class="badge badge-danger">
                        Tidak Layak: <?= $kondisiCount['tidak_layak'] ?>
                    </span>
                </div>
                <?php if ($totalChecklist > 0): ?>
                    <div class="kondisi-bar">
                        <?php if ($kondisiCount['layak'] > 0): ?>
                            <div class="kondisi-bar__seg"
                                 style="width:<?= round($kondisiCount['layak'] / $totalChecklist * 100) ?>%;background:var(--secondary);"></div>
                        <?php endif; ?>
                        <?php if ($kondisiCount['butuh_perbaikan'] > 0): ?>
                            <div class="kondisi-bar__seg"
                                 style="width:<?= round($kondisiCount['butuh_perbaikan'] / $totalChecklist * 100) ?>%;background:var(--warning);"></div>
                        <?php endif; ?>
                        <?php if ($kondisiCount['tidak_layak'] > 0): ?>
                            <div class="kondisi-bar__seg"
                                 style="width:<?= round($kondisiCount['tidak_layak'] / $totalChecklist * 100) ?>%;background:var(--danger);"></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form tambah item checklist -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Tambah Item Checklist</h3>
                <?php if (!empty($perlengkapan)): ?>
                    <span class="badge badge-info"><?= count($perlengkapan) ?> item perlengkapan</span>
                <?php endif; ?>
            </div>
            <div class="card-body">

                <?php if (!empty($perlengkapan)): ?>
                    <!-- Impor dari daftar perlengkapan -->
                    <form method="POST" action="<?= url('modules/operasional/pasca/save_checklist.php') ?>" class="mb-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="ops_id" value="<?= $opsId ?>">
                        <input type="hidden" name="action" value="impor_perlengkapan">
                        <p style="font-size:13px;color:var(--grey);margin-bottom:8px;">
                            Import otomatis dari daftar perlengkapan:
                        </p>
                        <button type="submit" class="btn btn-secondary btn-sm"
                                onclick="return confirm('Import semua item perlengkapan sebagai checklist?')">
                            ⬇ Import dari Perlengkapan
                        </button>
                    </form>
                    <hr style="margin:16px 0;">
                <?php endif; ?>

                <!-- Form manual -->
                <form method="POST" action="<?= url('modules/operasional/pasca/save_checklist.php') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="ops_id" value="<?= $opsId ?>">
                    <input type="hidden" name="action" value="tambah_item">

                    <div class="form-group">
                        <label class="form-label">Nama Item <span class="required">*</span></label>
                        <input type="text" name="nama_item" class="form-control"
                               placeholder="Contoh: Tenda Dome, Kompas, P3K…" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Kondisi <span class="required">*</span></label>
                            <select name="kondisi" class="form-control" required>
                                <option value="layak">Layak</option>
                                <option value="butuh_perbaikan">Butuh Perbaikan</option>
                                <option value="tidak_layak">Tidak Layak</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jumlah</label>
                            <input type="number" name="jumlah" class="form-control" min="1" value="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="catatan" class="form-control"
                               placeholder="Keterangan kondisi…">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">+ Tambah Item</button>
                </form>
            </div>
        </div>

        <!-- Aksi selesai -->
        <?php if ($ops['status'] !== 'selesai' && $ops['status'] !== 'dibatalkan'): ?>
        <div class="card">
            <div class="card-body">
                <form method="POST" action="<?= url('modules/operasional/ops/update_status.php') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="ops_id" value="<?= $opsId ?>">
                    <input type="hidden" name="aksi"   value="selesai">
                    <p style="font-size:13px;color:var(--grey);margin-bottom:12px;">
                        Pastikan semua checklist alat sudah diisi sebelum menyelesaikan kegiatan.
                    </p>
                    <button type="submit" class="btn btn-success"
                            onclick="return confirm('Tandai kegiatan ini sebagai SELESAI?')">
                        ✓ Tandai Kegiatan Selesai
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Daftar Checklist ── -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Checklist Alat</h3>
            <span class="badge badge-info"><?= count($checklist) ?> item</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($checklist)): ?>
                <div class="empty-state empty-state--sm">
                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--grey);margin-bottom:8px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <p class="empty-desc">Belum ada item checklist.<br>Tambah manual atau import dari perlengkapan.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Item</th>
                                <th>Jumlah</th>
                                <th>Kondisi</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checklist as $i => $c): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= e($c['nama_item']) ?></td>
                                    <td><?= e($c['jumlah'] ?? 1) ?></td>
                                    <td>
                                        <?php
                                        $kCls = [
                                            'layak'           => 'badge-success',
                                            'tidak_layak'     => 'badge-danger',
                                            'butuh_perbaikan' => 'badge-warning',
                                        ];
                                        $kLabel = [
                                            'layak'           => 'Layak',
                                            'tidak_layak'     => 'Tidak Layak',
                                            'butuh_perbaikan' => 'Butuh Perbaikan',
                                        ];
                                        ?>
                                        <span class="badge <?= $kCls[$c['kondisi']] ?? 'badge-secondary' ?>">
                                            <?= $kLabel[$c['kondisi']] ?? e($c['kondisi']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:var(--grey);">
                                        <?= e($c['catatan'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:4px;">
                                            <!-- Edit kondisi inline -->
                                            <form method="POST" action="<?= url('modules/operasional/pasca/save_checklist.php') ?>">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="ops_id"      value="<?= $opsId ?>">
                                                <input type="hidden" name="action"      value="update_kondisi">
                                                <input type="hidden" name="checklist_id" value="<?= $c['id'] ?>">
                                                <select name="kondisi" class="form-control form-control--xs"
                                                        onchange="this.form.submit()">
                                                    <?php foreach (['layak' => 'Layak', 'butuh_perbaikan' => 'Butuh Perbaikan', 'tidak_layak' => 'Tidak Layak'] as $val => $lbl): ?>
                                                        <option value="<?= $val ?>" <?= $c['kondisi'] === $val ? 'selected' : '' ?>>
                                                            <?= $lbl ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <!-- Hapus -->
                                            <form method="POST" action="<?= url('modules/operasional/pasca/save_checklist.php') ?>">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="ops_id"       value="<?= $opsId ?>">
                                                <input type="hidden" name="action"       value="hapus_item">
                                                <input type="hidden" name="checklist_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn-icon btn-icon--delete"
                                                        onclick="return confirm('Hapus item ini?')" title="Hapus">
                                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Laporan -->
        <?php if (!empty($laporan)): ?>
        <div class="card-header" style="border-top:1px solid var(--border);">
            <h3 class="card-title">Laporan Tersimpan</h3>
            <a href="<?= url('modules/operasional/ops/upload_laporan.php?ops_id=' . $opsId) ?>"
               class="btn btn-sm btn-secondary">Upload Lagi</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Judul</th><th>Ukuran</th><th>Link</th></tr></thead>
                    <tbody>
                        <?php foreach ($laporan as $lap): ?>
                            <tr>
                                <td><?= e($lap['judul'] ?? $lap['nama_file'] ?? '-') ?></td>
                                <td><?= isset($lap['ukuran_file']) ? number_format($lap['ukuran_file']/1024, 1).' KB' : '-' ?></td>
                                <td>
                                    <?php if (!empty($lap['drive_link'])): ?>
                                        <a href="<?= e($lap['drive_link']) ?>" target="_blank"
                                           class="btn btn-xs btn-secondary">Drive ↗</a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<style>
.form-control--xs { font-size:12px; padding:3px 6px; height:28px; border-radius:6px; }
.btn-xs { font-size:11px; padding:3px 8px; border-radius:6px; }
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';