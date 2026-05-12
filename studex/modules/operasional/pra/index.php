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

$stmt = $db->prepare("SELECT * FROM operasional WHERE id = ?");
$stmt->execute([$opsId]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

// Ambil data pra jika sudah ada
$pra = $db->prepare("SELECT * FROM operasional_pra WHERE operasional_id = ?");
$pra->execute([$opsId]);
$pra = $pra->fetch();

// Perlengkapan
$perlengkapan = $db->prepare("
    SELECT * FROM operasional_perlengkapan
    WHERE operasional_id = ?
    ORDER BY jenis, nama_item
");
$perlengkapan->execute([$opsId]);
$perlengkapan = $perlengkapan->fetchAll();

// Jumlah peserta
$jmlPeserta = $db->prepare("SELECT COUNT(*) FROM operasional_peserta WHERE operasional_id = ?");
$jmlPeserta->execute([$opsId]);
$jmlPeserta = $jmlPeserta->fetchColumn();

$pageTitle    = 'Pra-Operasional';
$pageSubtitle = e($ops['nama_kegiatan']);
$activePage   = 'operasional';
$breadcrumbs  = [
    ['label' => 'Dashboard',        'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional',      'url' => url('modules/operasional/index.php')],
    ['label' => e($ops['nama_kegiatan']), 'url' => url('modules/operasional/detail.php?id=' . $opsId)],
    ['label' => 'Pra-Operasional'],
];

ob_start();
?>

<style>
.stepper-mini { display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap; }
.stepper-mini__step {
    padding:6px 16px; border-radius:20px; font-size:13px; font-weight:600;
    background:#f0f0ee; color:var(--grey); text-decoration:none;
    transition:background .15s;
}
.stepper-mini__step:hover { background:var(--primary-light); color:var(--primary); }
.stepper-mini__step--active { background:var(--primary); color:#fff; }
.stepper-mini__arrow { color:var(--grey); font-size:14px; }
</style>

<!-- Stepper Mini -->
<div class="stepper-mini mb-4">
    <span class="stepper-mini__step stepper-mini__step--active">1. Pra-Operasional</span>
    <span class="stepper-mini__arrow">→</span>
    <a href="<?= url('modules/operasional/ops/index.php?ops_id=' . $opsId) ?>" class="stepper-mini__step">2. Operasional</a>
    <span class="stepper-mini__arrow">→</span>
    <a href="<?= url('modules/operasional/pasca/index.php?ops_id=' . $opsId) ?>" class="stepper-mini__step">3. Pasca-Operasional</a>
</div>

<div class="grid grid-2 gap-4" style="align-items:start;">

    <!-- ── Form Perencanaan ── -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Perencanaan & Briefing</h3>
            <?php if ($pra): ?>
                <span class="badge badge-success">Tersimpan</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= url('modules/operasional/pra/save.php') ?>">
                <?= csrfField() ?>
                <input type="hidden" name="ops_id" value="<?= $opsId ?>">

                <div class="form-group">
                    <label class="form-label">Tujuan Kegiatan</label>
                    <textarea name="tujuan" class="form-control" rows="3"
                              placeholder="Tujuan dan sasaran yang ingin dicapai…"><?= e($pra['tujuan'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Rencana Rute / Agenda</label>
                    <textarea name="rencana_rute" class="form-control" rows="3"
                              placeholder="Titik kumpul, rute perjalanan, waktu berangkat…"><?= e($pra['rencana_rute'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jumlah Personel Rencana</label>
                        <input type="number" name="jumlah_personel" class="form-control" min="0"
                               value="<?= e($pra['jumlah_personel'] ?? '') ?>"
                               placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Anggaran (Rp)</label>
                        <input type="number" name="anggaran" class="form-control" min="0"
                               value="<?= e($pra['anggaran'] ?? '') ?>"
                               placeholder="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Briefing</label>
                    <textarea name="catatan_briefing" class="form-control" rows="3"
                              placeholder="Poin-poin penting yang disampaikan saat briefing…"><?= e($pra['catatan_briefing'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Kontak Darurat</label>
                    <input type="text" name="kontak_darurat" class="form-control"
                           value="<?= e($pra['kontak_darurat'] ?? '') ?>"
                           placeholder="Nama & nomor HP kontak darurat">
                </div>

                <div class="form-actions">
                    <a href="<?= url('modules/operasional/detail.php?id=' . $opsId) ?>"
                       class="btn btn-secondary">Kembali</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Perencanaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Panel Kanan ── -->
    <div>

        <!-- Peserta -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Peserta</h3>
                <div class="card-actions">
                    <span class="badge badge-info"><?= $jmlPeserta ?> siswa</span>
                    <a href="<?= url('modules/operasional/pra/peserta.php?ops_id=' . $opsId) ?>"
                       class="btn btn-sm btn-primary">Kelola Peserta</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($jmlPeserta === 0): ?>
                    <div class="empty-state empty-state--sm">
                        <p class="empty-desc">Belum ada peserta ditambahkan.</p>
                        <a href="<?= url('modules/operasional/pra/peserta.php?ops_id=' . $opsId) ?>"
                           class="btn btn-sm btn-primary mt-2">+ Tambah Peserta</a>
                    </div>
                <?php else: ?>
                    <p style="font-size:14px;color:var(--grey);">
                        <?= $jmlPeserta ?> peserta telah terdaftar.
                        <a href="<?= url('modules/operasional/pra/peserta.php?ops_id=' . $opsId) ?>">Lihat & kelola →</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Perlengkapan -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Perlengkapan</h3>
                <span class="badge badge-info"><?= count($perlengkapan) ?> item</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($perlengkapan)): ?>
                    <div class="empty-state empty-state--sm">
                        <p class="empty-desc">Belum ada perlengkapan dicatat.</p>
                    </div>

                    <!-- Form tambah perlengkapan inline -->
                    <div class="p-4" style="border-top:1px solid var(--border);">
                        <p class="text-sm font-medium mb-3">Tambah Item Perlengkapan</p>
                        <form method="POST" action="<?= url('modules/operasional/pra/save.php') ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="ops_id"   value="<?= $opsId ?>">
                            <input type="hidden" name="action"   value="add_perlengkapan">
                            <div class="form-row">
                                <div class="form-group" style="flex:2;">
                                    <input type="text" name="nama_item" class="form-control"
                                           placeholder="Nama item" required>
                                </div>
                                <div class="form-group">
                                    <select name="jenis" class="form-control">
                                        <option value="pribadi">Pribadi</option>
                                        <option value="regu">Regu</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex:0 0 80px;">
                                    <input type="number" name="jumlah" class="form-control"
                                           placeholder="Jml" min="1" value="1" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">+ Tambah</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Item</th><th>Jenis</th><th>Jml</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perlengkapan as $p): ?>
                                    <tr>
                                        <td><?= e($p['nama_item']) ?></td>
                                        <td>
                                            <span class="badge <?= $p['jenis'] === 'pribadi' ? 'badge-info' : 'badge-warning' ?>">
                                                <?= ucfirst(e($p['jenis'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e($p['jumlah']) ?></td>
                                        <td>
                                            <form method="POST" action="<?= url('modules/operasional/pra/save.php') ?>" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="ops_id"          value="<?= $opsId ?>">
                                                <input type="hidden" name="action"          value="delete_perlengkapan">
                                                <input type="hidden" name="perlengkapan_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn-icon btn-icon--delete"
                                                        onclick="return confirm('Hapus item ini?')"
                                                        title="Hapus">
                                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Form tambah perlengkapan -->
                    <div class="p-4" style="border-top:1px solid var(--border);">
                        <form method="POST" action="<?= url('modules/operasional/pra/save.php') ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="ops_id" value="<?= $opsId ?>">
                            <input type="hidden" name="action" value="add_perlengkapan">
                            <div class="form-row" style="align-items:flex-end;">
                                <div class="form-group" style="flex:2;">
                                    <input type="text" name="nama_item" class="form-control"
                                           placeholder="Tambah item baru…" required>
                                </div>
                                <div class="form-group">
                                    <select name="jenis" class="form-control">
                                        <option value="pribadi">Pribadi</option>
                                        <option value="regu">Regu</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex:0 0 80px;">
                                    <input type="number" name="jumlah" class="form-control"
                                           placeholder="Jml" min="1" value="1" required>
                                </div>
                                <div class="form-group" style="flex:0 0 auto;">
                                    <button type="submit" class="btn btn-sm btn-primary">+ Tambah</button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- end panel kanan -->
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';