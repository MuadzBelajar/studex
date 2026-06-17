<?php
define('STUDEX', true);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db           = db();
$angkatanList = $db->query("SELECT id, nama FROM angkatan ORDER BY tahun DESC")->fetchAll();


// ── Filter params ──────────────────────────────────────────────
$modul       = in_array($_GET['modul'] ?? '', ['rabuan','mentoring','binjas'])
               ? $_GET['modul'] : '';
$angkatanId  = sanitizeInt($_GET['angkatan_id'] ?? 0);
$referensiId = sanitizeInt($_GET['referensi_id'] ?? 0);
$search      = sanitize($_GET['search'] ?? '');

// ── Daftar sesi berdasarkan modul + angkatan ───────────────────
$sesiList = [];
if ($modul && $angkatanId) {
    switch ($modul) {
        case 'rabuan':
            $s = $db->prepare("SELECT id, judul AS nama, tanggal FROM rabuan WHERE angkatan_id=? ORDER BY tanggal DESC");
            $s->execute([$angkatanId]);
            $sesiList = $s->fetchAll();
            break;
        case 'mentoring':
            $s = $db->prepare("SELECT id, judul AS nama, tanggal FROM mentoring_sesi WHERE angkatan_id=? ORDER BY tanggal DESC");
            $s->execute([$angkatanId]);
            $sesiList = $s->fetchAll();
            break;
        case 'binjas':
            $s = $db->prepare("SELECT id, nama_sesi AS nama, tanggal FROM binjas_sesi WHERE angkatan_id=? ORDER BY tanggal DESC");
            $s->execute([$angkatanId]);
            $sesiList = $s->fetchAll();
            break;
    }
}

// ── Handle POST: simpan presensi ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    verifyCsrf();

    $postModul      = sanitize(post('modul'));
    $postAngkatan   = sanitizeInt(post('angkatan_id'));
    $postReferensi  = sanitizeInt(post('referensi_id'));
    $presensiInput  = $_POST['presensi']    ?? [];
    $keteranganInput= $_POST['keterangan']  ?? [];

    if (!in_array($postModul, ['rabuan','mentoring','binjas'])) {
        setFlash('error', 'Modul tidak valid.');
        redirect(url('modules/presensi/index.php'));
    }

    $userId = $_SESSION['user_id'];
    $saved  = 0;

    foreach ($presensiInput as $siswaId => $status) {
        $siswaId = (int) $siswaId;
        $status  = in_array($status, ['hadir','izin','sakit','alpha']) ? $status : 'alpha';
        $ket     = sanitize($keteranganInput[$siswaId] ?? '');

        // Upsert: update jika sudah ada, insert jika belum
        $cek = $db->prepare("SELECT id FROM presensi WHERE siswa_id=? AND modul=? AND referensi_id=?");
        $cek->execute([$siswaId, $postModul, $postReferensi]);

        if ($cek->fetchColumn()) {
            $db->prepare("UPDATE presensi
                          SET status=?, keterangan=?, updated_by=?, updated_at=NOW()
                          WHERE siswa_id=? AND modul=? AND referensi_id=?")
               ->execute([$status, $ket, $userId, $siswaId, $postModul, $postReferensi]);
        } else {
            $db->prepare("INSERT INTO presensi
                              (siswa_id, modul, referensi_id, status, keterangan, created_by)
                          VALUES (?,?,?,?,?,?)")
               ->execute([$siswaId, $postModul, $postReferensi, $status, $ket, $userId]);
        }
        $saved++;
    }

    setFlash('success', "Presensi berhasil disimpan untuk {$saved} siswa.");
    redirect(url("modules/presensi/index.php?modul={$postModul}&angkatan_id={$postAngkatan}&referensi_id={$postReferensi}"));
}

// ── Ambil data siswa + status presensi jika filter lengkap ─────
$siswaList = [];
$totalH = $totalI = $totalS = $totalA = 0;

if ($modul && $angkatanId && $referensiId) {
    $sqlSiswa  = "SELECT s.id, s.nama, s.nim,
                         COALESCE(p.status,'alpha')  AS status,
                         COALESCE(p.keterangan,'')   AS keterangan,
                         p.id AS presensi_id
                  FROM siswa s
                  LEFT JOIN presensi p
                         ON p.siswa_id = s.id
                        AND p.modul = ?
                        AND p.referensi_id = ?
                  WHERE s.angkatan_id = ? AND s.status = 'aktif'";
    $parSiswa  = [$modul, $referensiId, $angkatanId];

    if ($search) {
        $sqlSiswa .= " AND (s.nama LIKE ? OR s.nim LIKE ?)";
        $parSiswa[] = "%{$search}%";
        $parSiswa[] = "%{$search}%";
    }
    $sqlSiswa .= " ORDER BY s.nama ASC";

    $stmtSiswa = $db->prepare($sqlSiswa);
    $stmtSiswa->execute($parSiswa);
    $siswaList = $stmtSiswa->fetchAll();

    foreach ($siswaList as $r) {
        if ($r['status'] === 'hadir') $totalH++;
        elseif ($r['status'] === 'izin')  $totalI++;
        elseif ($r['status'] === 'sakit') $totalS++;
        else                              $totalA++;
    }
}

// ── Page meta ──────────────────────────────────────────────────
$pageTitle    = 'Input Presensi';
$pageSubtitle = 'Catat kehadiran siswa per sesi kegiatan';
$activePage   = 'presensi';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Presensi'],
];

$modulLabel   = ['rabuan' => 'Rabuan', 'mentoring' => 'Mentoring', 'binjas' => 'Binjas'];

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <h2 class="page-title"><?= e($pageTitle) ?></h2>
        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
    </div>
    <div class="page-header-right">
        <a href="<?= url('modules/presensi/rekap.php') ?>" class="btn btn-outline">
            📊 Rekap Kehadiran
        </a>
    </div>
</div>

<!-- ── Filter ──────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header"><h4 class="card-title">Filter Presensi</h4></div>
    <div class="card-body">
        <form method="GET" action="" id="filterForm">
            <div class="row g-3 align-items-end">

                <div class="col-md-3">
                    <label class="form-label">Modul <span class="text-danger">*</span></label>
                    <select name="modul" class="form-control" onchange="document.getElementById('filterForm').submit()">
                        <option value="">-- Pilih Modul --</option>
                        <option value="rabuan"    <?= $modul==='rabuan'    ? 'selected':'' ?>>Rabuan</option>
                        <option value="mentoring" <?= $modul==='mentoring' ? 'selected':'' ?>>Mentoring</option>
                        <option value="binjas"    <?= $modul==='binjas'    ? 'selected':'' ?>>Binjas</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Angkatan <span class="text-danger">*</span></label>
                    <select name="angkatan_id" class="form-control" onchange="document.getElementById('filterForm').submit()">
                        <option value="">-- Pilih Angkatan --</option>
                        <?php foreach ($angkatanList as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $angkatanId==$a['id'] ? 'selected':'' ?>>
                                <?= e($a['nama_angkatan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($sesiList): ?>
                <div class="col-md-3">
                    <label class="form-label">Sesi / Kegiatan <span class="text-danger">*</span></label>
                    <select name="referensi_id" class="form-control" onchange="document.getElementById('filterForm').submit()">
                        <option value="">-- Pilih Sesi --</option>
                        <?php foreach ($sesiList as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $referensiId==$s['id'] ? 'selected':'' ?>>
                                <?= e($s['nama']) ?> — <?= formatTanggal($s['tanggal']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($referensiId): ?>
                <div class="col-md-3">
                    <label class="form-label">Cari Siswa</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                               value="<?= e($search) ?>" placeholder="Nama / NIM...">
                        <button type="submit" class="btn btn-primary">Cari</button>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- .row -->
        </form>
    </div>
</div>


<!-- ── Summary Cards ───────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php

    $total = count($siswaList);
    $cards = [
        ['label'=>'Total Siswa', 'value'=>$total,  'icon'=>'👥', 'color'=>''],
        ['label'=>'Hadir',       'value'=>$totalH,  'icon'=>'✅', 'color'=>'success'],
        ['label'=>'Izin',        'value'=>$totalI,  'icon'=>'🔵', 'color'=>'info'],
        ['label'=>'Sakit',       'value'=>$totalS,  'icon'=>'🟡', 'color'=>'warning'],
        ['label'=>'Alpha',       'value'=>$totalA,  'icon'=>'❌', 'color'=>'danger'],
    ];
    foreach ($cards as $c): ?>
    <div class="col mb-3">
        <div class="stat-card">
            <div class="stat-icon"><?= $c['icon'] ?></div>
            <div class="stat-label"><?= $c['label'] ?></div>
            <div class="stat-value <?= $c['color'] ? 'text-'.$c['color'] : '' ?>"><?= $c['value'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Input Form ──────────────────────────────────────────── -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="card-title mb-0">
            Input Presensi
            <span class="badge badge-primary ms-2"><?= e($modulLabel[$modul] ?? $modul) ?></span>
        </h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline" onclick="setAllStatus('hadir')">
                ✅ Semua Hadir
            </button>
            <button type="button" class="btn btn-sm btn-outline" onclick="setAllStatus('alpha')"
                    style="border-color:#8B1408;color:#8B1408">
                ❌ Semua Alpha
            </button>
        </div>
    </div>

    <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="modul"         value="<?= e($modul) ?>">
        <input type="hidden" name="angkatan_id"   value="<?= $angkatanId ?>">
        <input type="hidden" name="referensi_id"  value="<?= $referensiId ?>">

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th width="40">#</th>
                        <th width="120">NIM</th>
                        <th>Nama Siswa</th>
                        <th width="280">Status Kehadiran</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswaList as $i => $siswa): ?>
                    <tr>
                        <td class="text-secondary"><?= $i + 1 ?></td>
                        <td class="text-monospace text-sm"><?= e($siswa['nim']) ?></td>
                        <td class="fw-medium"><?= e($siswa['nama']) ?></td>
                        <td>
                            <div class="status-group" data-id="<?= $siswa['id'] ?>">
                                <?php foreach (['hadir','izin','sakit','alpha'] as $st): ?>
                                <label class="status-btn status-<?= $st ?> <?= $siswa['status']===$st ? 'active':'' ?>">
                                    <input type="radio"
                                           name="presensi[<?= $siswa['id'] ?>]"
                                           value="<?= $st ?>"
                                           <?= $siswa['status']===$st ? 'checked':'' ?>
                                           onchange="activateStatus(this)">
                                    <?= ucfirst($st) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <input type="text"
                                   name="keterangan[<?= $siswa['id'] ?>]"
                                   class="form-control form-control-sm"
                                   value="<?= e($siswa['keterangan']) ?>"
                                   placeholder="Opsional...">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex gap-2 align-items-center">
            <button type="submit" class="btn btn-primary">
                💾 Simpan Presensi
            </button>
            <a href="<?= url("modules/presensi/rekap.php?modul={$modul}&angkatan_id={$angkatanId}") ?>"
               class="btn btn-outline">
                📊 Lihat Rekap
            </a>
            <span class="text-secondary text-sm ms-auto">
                <?= count($siswaList) ?> siswa
            </span>
        </div>
    </form>
</div>

<div class="empty-state">
    <div class="empty-state-icon">👥</div>
    <h3>Tidak Ada Siswa Aktif</h3>
    <p>Belum ada siswa aktif yang terdaftar pada angkatan ini.</p>
</div>

<div class="empty-state">
    <div class="empty-state-icon">📅</div>
    <h3>Belum Ada Sesi</h3>
    <p>Belum ada sesi <?= e($modulLabel[$modul] ?? '') ?> untuk angkatan ini.</p>
    <?php if ($modul === 'rabuan'): ?>
        <a href="<?= url('modules/rabuan/create.php') ?>" class="btn btn-primary mt-3">+ Buat Rabuan</a>
    <?php elseif ($modul === 'mentoring'): ?>
        <a href="<?= url('modules/mentoring/create.php') ?>" class="btn btn-primary mt-3">+ Buat Mentoring</a>
    <?php elseif ($modul === 'binjas'): ?>
        <a href="<?= url('modules/binjas/create.php') ?>" class="btn btn-primary mt-3">+ Buat Binjas</a>
    <?php endif; ?>
</div>


<div class="empty-state">
    <div class="empty-state-icon">📋</div>
    <h3>Pilih Filter</h3>
    <p>Pilih modul, angkatan, dan sesi untuk mulai input presensi.</p>
</div>


<!-- ── Styles ──────────────────────────────────────────────── -->
<style>
.status-group {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.status-btn {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1.5px solid #ddd;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all .15s ease;
    user-select: none;
}
.status-btn input[type=radio] { display: none; }

.status-btn.status-hadir.active { background:#d4edda; border-color:#28a745; color:#155724; }
.status-btn.status-izin.active  { background:#cce5ff; border-color:#004085; color:#004085; }
.status-btn.status-sakit.active { background:#fff3cd; border-color:#856404; color:#856404; }
.status-btn.status-alpha.active { background:#f8d7da; border-color:#8B1408; color:#721c24; }

.status-btn:not(.active):hover  { background: #E6EFEA; border-color: #395917; color: #395917; }
</style>

<!-- ── Scripts ─────────────────────────────────────────────── -->
<script>
function activateStatus(radio) {
    const group = radio.closest('.status-group');
    group.querySelectorAll('.status-btn').forEach(btn => btn.classList.remove('active'));
    radio.closest('.status-btn').classList.add('active');
}

function setAllStatus(status) {
    document.querySelectorAll(`.status-group input[value="${status}"]`).forEach(radio => {
        radio.checked = true;
        activateStatus(radio);
    });
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';