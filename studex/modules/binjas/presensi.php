<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireAdmin();

$db     = db();
$sesiId = sanitizeInt(get('sesi_id') ?: post('sesi_id'));

if (!$sesiId) {
    setFlash('error', 'ID sesi tidak valid.');
    redirect(url('modules/binjas/index.php'));
}

$stmt = $db->prepare("
    SELECT b.*, a.nama AS nama_angkatan
    FROM binjas_sesi b
    LEFT JOIN angkatan a ON a.id = b.angkatan_id
    WHERE b.id = ?
");
$stmt->execute([$sesiId]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Sesi tidak ditemukan.');
    redirect(url('modules/binjas/index.php'));
}

// Handle POST — simpan presensi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $presensiInput = post('presensi', []); // presensi[siswa_id] = status
    $catatanInput  = post('catatan', []);  // catatan[siswa_id]  = catatan

    if (!is_array($presensiInput)) {
        setFlash('error', 'Format data tidak valid.');
        redirect(url('modules/binjas/presensi.php?sesi_id=' . $sesiId));
    }

    $validStatus = ['hadir', 'izin', 'sakit', 'alpha'];

    $stmtUpsert = $db->prepare("
        INSERT INTO presensi
            (modul, referensi_id, siswa_id, status, catatan, dicatat_oleh, created_at)
        VALUES ('binjas', ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status      = VALUES(status),
            catatan     = VALUES(catatan),
            dicatat_oleh= VALUES(dicatat_oleh),
            updated_at  = NOW()
    ");

    $saved  = 0;
    $userId = currentUserId();

    foreach ($presensiInput as $siswaId => $status) {
        $siswaId = sanitizeInt($siswaId);
        $status  = sanitize($status);
        $catatan = sanitize($catatanInput[$siswaId] ?? '');

        if (!$siswaId) continue;
        if (!in_array($status, $validStatus)) $status = 'hadir';

        $stmtUpsert->execute([$sesiId, $siswaId, $status, $catatan, $userId]);
        $saved++;
    }

    setFlash('success', "Presensi $saved siswa berhasil disimpan.");
    redirect(url('modules/binjas/detail.php?id=' . $sesiId));
}

// Siswa dari angkatan sesi
$siswaList = $db->prepare("
    SELECT s.id, s.nama, s.nis

    FROM siswa s
    WHERE s.angkatan_id = ? AND s.status = 'aktif'
    ORDER BY s.nama
");
$siswaList->execute([$sesi['angkatan_id']]);
$siswaList = $siswaList->fetchAll();

// Presensi yang sudah ada
$existingPresensi = $db->prepare("
    SELECT siswa_id, status, catatan
    FROM presensi
    WHERE modul = 'binjas' AND referensi_id = ?
");
$existingPresensi->execute([$sesiId]);
$presensiMap = [];
$catatanMap  = [];
foreach ($existingPresensi->fetchAll() as $p) {
    $presensiMap[$p['siswa_id']] = $p['status'];
    $catatanMap[$p['siswa_id']]  = $p['catatan'];
}

// Hitung ringkasan
$ringkasan = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0];
foreach ($presensiMap as $status) {
    if (isset($ringkasan[$status])) $ringkasan[$status]++;
}

$pageTitle    = 'Presensi Binjas';
$pageSubtitle = e($sesi['nama_sesi']);
$activePage   = 'binjas';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Binjas',    'url' => url('modules/binjas/index.php')],
    ['label' => e($sesi['nama_sesi']), 'url' => url('modules/binjas/detail.php?id=' . $sesiId)],
    ['label' => 'Presensi'],
];

ob_start();
?>

<style>
.presensi-radio { display:flex; gap:4px; }
.presensi-radio label {
    display:flex; align-items:center; justify-content:center;
    width:68px; padding:5px 8px; border-radius:8px; cursor:pointer;
    font-size:12px; font-weight:600; border:2px solid transparent;
    background:#f0f0ee; color:var(--grey); transition:all .15s;
    user-select:none;
}
.presensi-radio input[type="radio"] { display:none; }
.presensi-radio input[value="hadir"]:checked + label { background:#eaf3ec; color:var(--secondary); border-color:var(--secondary); }
.presensi-radio input[value="izin"]:checked  + label { background:#fff8e7; color:var(--warning);   border-color:var(--warning); }
.presensi-radio input[value="sakit"]:checked + label { background:#e8f4fd; color:#3b82f6;          border-color:#3b82f6; }
.presensi-radio input[value="alpha"]:checked + label { background:#fdecea; color:var(--danger);    border-color:var(--danger); }
.presensi-radio label:hover { opacity:.85; }
</style>

<!-- Info Sesi -->
<div class="card mb-4">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-size:18px;font-weight:700;margin-bottom:4px;"><?= e($sesi['nama_sesi']) ?></h2>
                <div style="font-size:13px;color:var(--grey);display:flex;gap:16px;flex-wrap:wrap;">
                    <span><strong>Angkatan:</strong> <?= e($sesi['nama_angkatan'] ?? '-') ?></span>
                    <span><strong>Tanggal:</strong> <?= formatTanggal($sesi['tanggal']) ?></span>
                    <span><strong>Lokasi:</strong> <?= e($sesi['lokasi'] ?? '-') ?></span>
                </div>
            </div>
            <a href="<?= url('modules/binjas/detail.php?id=' . $sesiId) ?>"
               class="btn btn-secondary btn-sm">← Kembali ke Detail</a>
        </div>
    </div>
</div>

<!-- Ringkasan -->
<?php if (!empty($presensiMap)): ?>
<div class="grid grid-4 mb-4">
    <div class="card stat-card">
        <div class="stat-icon" style="background:#eaf3ec;color:var(--secondary);">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Hadir</span>
            <span class="stat-value"><?= $ringkasan['hadir'] ?></span>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background:#fff8e7;color:var(--warning);">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Izin</span>
            <span class="stat-value"><?= $ringkasan['izin'] ?></span>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background:#e8f4fd;color:#3b82f6;">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Sakit</span>
            <span class="stat-value"><?= $ringkasan['sakit'] ?></span>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background:#fdecea;color:var(--danger);">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Alpha</span>
            <span class="stat-value"><?= $ringkasan['alpha'] ?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Form Presensi -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Form Presensi</h3>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-secondary" onclick="setAll('hadir')">Semua Hadir</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setAll('alpha')">Semua Alpha</button>
            <span class="badge badge-info"><?= count($siswaList) ?> siswa</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($siswaList)): ?>
            <div class="empty-state">
                <p class="empty-title">Tidak ada siswa aktif</p>
                <p class="empty-desc">Belum ada siswa aktif pada angkatan ini.</p>
            </div>
        <?php else: ?>
            <form method="POST" id="formPresensi">
                <?= csrfField() ?>
                <input type="hidden" name="sesi_id" value="<?= $sesiId ?>">

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Nama Siswa</th>
                                <th>No. Induk</th>
                                <th>Status Kehadiran</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswaList as $i => $siswa): ?>
                                <?php $currentStatus = $presensiMap[$siswa['id']] ?? 'hadir'; ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <div style="font-weight:600;font-size:13px;"><?= e($siswa['nama']) ?></div>
                                    </td>
                                    <td style="font-size:12px;color:var(--grey);"><?= e($siswa['nis']) ?></td>

                                    <td>
                                        <div class="presensi-radio">
                                            <?php foreach (['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha'] as $val => $label): ?>
                                                <input type="radio"
                                                       id="prs_<?= $siswa['id'] ?>_<?= $val ?>"
                                                       name="presensi[<?= $siswa['id'] ?>]"
                                                       value="<?= $val ?>"
                                                       <?= $currentStatus === $val ? 'checked' : '' ?>>
                                                <label for="prs_<?= $siswa['id'] ?>_<?= $val ?>"><?= $label ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="catatan[<?= $siswa['id'] ?>]"
                                               value="<?= e($catatanMap[$siswa['id']] ?? '') ?>"
                                               class="form-control form-control--sm"
                                               placeholder="Keterangan…"
                                               style="min-width:140px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4" style="display:flex;justify-content:flex-end;gap:12px;border-top:1px solid var(--border);">
                    <a href="<?= url('modules/binjas/detail.php?id=' . $sesiId) ?>"
                       class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Presensi
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
.form-control--sm { padding:4px 8px; font-size:13px; height:auto; }
</style>

<script>
function setAll(status) {
    const label = { hadir:'Hadir', izin:'Izin', sakit:'Sakit', alpha:'Alpha' }[status] || status;
    if (!confirm('Set semua siswa menjadi "' + label + '"?')) return;
    document.querySelectorAll('input[type="radio"][value="' + status + '"]').forEach(function(r) {
        r.checked = true;
    });
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';