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
$angkatanList = $db->query("SELECT id, nama_angkatan FROM angkatan ORDER BY tahun_masuk DESC")->fetchAll();

// ── Filter params ──────────────────────────────────────────────
$modul      = in_array($_GET['modul'] ?? '', ['rabuan','mentoring','binjas'])
              ? $_GET['modul'] : '';
$angkatanId = sanitizeInt($_GET['angkatan_id'] ?? 0);

// ── Data ───────────────────────────────────────────────────────
$sesiList   = [];
$siswaRekap = [];
$angNama    = '';

if ($modul && $angkatanId) {
    // Nama angkatan untuk heading
    foreach ($angkatanList as $a) {
        if ($a['id'] == $angkatanId) { $angNama = $a['nama_angkatan']; break; }
    }

    // Daftar sesi (kolom tabel)
    switch ($modul) {
        case 'rabuan':
            $s = $db->prepare("SELECT id, judul AS nama, tanggal
                                FROM rabuan WHERE angkatan_id=? ORDER BY tanggal ASC");
            $s->execute([$angkatanId]);
            $sesiList = $s->fetchAll();
            break;
        case 'mentoring':
            $s = $db->prepare("SELECT id, judul AS nama, tanggal
                                FROM mentoring_sesi WHERE angkatan_id=? ORDER BY tanggal ASC");
            $s->execute([$angkatanId]);
            $sesiList = $s->fetchAll();
            break;
        case 'binjas':
            $s = $db->prepare("SELECT id, nama_sesi AS nama, tanggal
                                FROM binjas_sesi WHERE angkatan_id=? ORDER BY tanggal ASC");
            $s->execute([$angkatanId]);
            $sesiList = $s->fetchAll();
            break;
    }

    if ($sesiList) {
        // Semua siswa aktif
        $stmtS = $db->prepare("SELECT id, nama, nim FROM siswa
                                WHERE angkatan_id=? AND status='aktif' ORDER BY nama ASC");
        $stmtS->execute([$angkatanId]);
        $allSiswa = $stmtS->fetchAll();

        // Seluruh presensi sekaligus (1 query)
        $sesiIds      = array_column($sesiList, 'id');
        $placeholders = implode(',', array_fill(0, count($sesiIds), '?'));
        $stmtP = $db->prepare(
            "SELECT siswa_id, referensi_id, status
             FROM presensi
             WHERE modul=? AND referensi_id IN ({$placeholders})"
        );
        $stmtP->execute(array_merge([$modul], $sesiIds));
        $allPresensi = $stmtP->fetchAll();

        // Index [siswa_id][referensi_id] => status
        $presensiMap = [];
        foreach ($allPresensi as $p) {
            $presensiMap[$p['siswa_id']][$p['referensi_id']] = $p['status'];
        }

        $totalSesi = count($sesiList);

        foreach ($allSiswa as $siswa) {
            $row = [
                'id'   => $siswa['id'],
                'nama' => $siswa['nama'],
                'nim'  => $siswa['nim'],
            ];

            $h = $i = $s = $a = 0;
            foreach ($sesiList as $sesi) {
                $st = $presensiMap[$siswa['id']][$sesi['id']] ?? 'alpha';
                $row['sesi'][$sesi['id']] = $st;
                if ($st === 'hadir')      $h++;
                elseif ($st === 'izin')   $i++;
                elseif ($st === 'sakit')  $s++;
                else                      $a++;
            }

            $row['hadir']     = $h;
            $row['izin']      = $i;
            $row['sakit']     = $s;
            $row['alpha']     = $a;
            $row['pct']       = $totalSesi > 0 ? round($h / $totalSesi * 100) : 0;

            $siswaRekap[] = $row;
        }
    }
}

// ── Page meta ──────────────────────────────────────────────────
$pageTitle    = 'Rekap Presensi';
$pageSubtitle = 'Ringkasan kehadiran per siswa';
$activePage   = 'presensi';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Presensi',  'url' => url('modules/presensi/index.php')],
    ['label' => 'Rekap'],
];

$modulLabel = ['rabuan' => 'Rabuan', 'mentoring' => 'Mentoring', 'binjas' => 'Binjas'];

// Simbol per status — teks agar bisa di-export juga
$sym = ['hadir' => '✅', 'izin' => '🔵', 'sakit' => '🟡', 'alpha' => '❌'];

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <h2 class="page-title"><?= e($pageTitle) ?></h2>
        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
    </div>
    <div class="page-header-right d-flex gap-2">
        <a href="<?= url('modules/presensi/index.php') ?>" class="btn btn-outline">
            📋 Input Presensi
        </a>
        <?php if ($modul && $angkatanId && $siswaRekap): ?>
        <a href="<?= url("modules/presensi/export.php?modul={$modul}&angkatan_id={$angkatanId}") ?>"
           class="btn btn-primary">
            ⬇️ Export CSV
        </a>
        <?php endif; ?>
    </div>
</div>

<?php flash() ?>

<!-- ── Filter ──────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Modul</label>
                    <select name="modul" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Modul --</option>
                        <option value="rabuan"    <?= $modul==='rabuan'    ? 'selected':'' ?>>Rabuan</option>
                        <option value="mentoring" <?= $modul==='mentoring' ? 'selected':'' ?>>Mentoring</option>
                        <option value="binjas"    <?= $modul==='binjas'    ? 'selected':'' ?>>Binjas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Angkatan</label>
                    <select name="angkatan_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Angkatan --</option>
                        <?php foreach ($angkatanList as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $angkatanId==$a['id'] ? 'selected':'' ?>>
                                <?= e($a['nama_angkatan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($modul): ?>
                    <input type="hidden" name="modul" value="<?= e($modul) ?>">
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($modul && $angkatanId && $siswaRekap): ?>

<!-- ── Legend ─────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-4 align-items-center flex-wrap text-sm">
            <span class="text-secondary fw-medium">Keterangan:</span>
            <span>✅ Hadir</span>
            <span>🔵 Izin</span>
            <span>🟡 Sakit</span>
            <span>❌ Alpha / Tidak Hadir</span>
        </div>
    </div>
</div>

<!-- ── Rekap Table ─────────────────────────────────────────── -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h4 class="card-title mb-0">
                Rekap <?= e($modulLabel[$modul] ?? $modul) ?> — <?= e($angNama) ?>
            </h4>
            <p class="text-secondary text-sm mt-1 mb-0">
                <?= count($sesiList) ?> sesi &nbsp;·&nbsp; <?= count($siswaRekap) ?> siswa
            </p>
        </div>
    </div>

    <div style="overflow-x: auto">
        <table class="table table-sm" id="rekapTable" style="min-width:700px">
            <thead>
                <tr>
                    <th style="min-width:36px">#</th>
                    <th style="min-width:100px">NIM</th>
                    <th style="min-width:180px">Nama</th>

                    <?php foreach ($sesiList as $sesi): ?>
                    <th style="min-width:64px; text-align:center; font-size:11px"
                        title="<?= e($sesi['nama']) ?>">
                        <?= date('d/m', strtotime($sesi['tanggal'])) ?>
                    </th>
                    <?php endforeach; ?>

                    <th style="min-width:44px; text-align:center" title="Hadir">H</th>
                    <th style="min-width:44px; text-align:center" title="Izin">I</th>
                    <th style="min-width:44px; text-align:center" title="Sakit">S</th>
                    <th style="min-width:44px; text-align:center" title="Alpha">A</th>
                    <th style="min-width:80px; text-align:center">% Hadir</th>
                    <th style="min-width:100px; text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($siswaRekap as $idx => $siswa): ?>
                <tr>
                    <td class="text-secondary"><?= $idx + 1 ?></td>
                    <td class="text-monospace text-sm"><?= e($siswa['nim']) ?></td>
                    <td class="fw-medium"><?= e($siswa['nama']) ?></td>

                    <?php foreach ($sesiList as $sesi): ?>
                        <?php $st = $siswa['sesi'][$sesi['id']] ?? 'alpha'; ?>
                        <td style="text-align:center; font-size:14px"
                            title="<?= ucfirst($st) ?>">
                            <?= $sym[$st] ?? '❌' ?>
                        </td>
                    <?php endforeach; ?>

                    <td style="text-align:center" class="text-success fw-medium"><?= $siswa['hadir'] ?></td>
                    <td style="text-align:center" class="text-info"><?= $siswa['izin'] ?></td>
                    <td style="text-align:center" class="text-warning"><?= $siswa['sakit'] ?></td>
                    <td style="text-align:center" class="text-danger"><?= $siswa['alpha'] ?></td>

                    <td style="text-align:center">
                        <?php
                        $pct   = $siswa['pct'];
                        $cls   = $pct >= 80 ? 'badge-success' : ($pct >= 60 ? 'badge-warning' : 'badge-danger');
                        ?>
                        <span class="badge <?= $cls ?>"><?= $pct ?>%</span>
                    </td>

                    <td style="text-align:center">
                        <a href="<?= url("modules/siswa/detail.php?id={$siswa['id']}") ?>"
                           class="btn btn-xs btn-outline" title="Profil siswa">
                            👤
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>

            <!-- Footer: totals per sesi -->
            <tfoot>
                <tr style="background:#f9f9f9; font-weight:600">
                    <td colspan="3" class="text-secondary text-sm">Total Hadir per Sesi</td>
                    <?php foreach ($sesiList as $sesi): ?>
                        <?php
                        $hadirPerSesi = array_filter(
                            $siswaRekap,
                            fn($r) => ($r['sesi'][$sesi['id']] ?? 'alpha') === 'hadir'
                        );
                        ?>
                        <td style="text-align:center" class="text-success">
                            <?= count($hadirPerSesi) ?>
                        </td>
                    <?php endforeach; ?>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    </div><!-- overflow-x -->
</div>

<?php elseif ($modul && $angkatanId && empty($sesiList)): ?>
<div class="empty-state">
    <div class="empty-state-icon">📅</div>
    <h3>Belum Ada Sesi</h3>
    <p>Belum ada sesi <?= e($modulLabel[$modul] ?? '') ?> untuk angkatan yang dipilih.</p>
</div>

<?php elseif ($modul && $angkatanId && empty($siswaRekap)): ?>
<div class="empty-state">
    <div class="empty-state-icon">👥</div>
    <h3>Tidak Ada Siswa Aktif</h3>
    <p>Belum ada siswa aktif yang terdaftar pada angkatan ini.</p>
</div>

<?php else: ?>
<div class="empty-state">
    <div class="empty-state-icon">📊</div>
    <h3>Pilih Filter</h3>
    <p>Pilih modul dan angkatan untuk menampilkan rekap kehadiran.</p>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';