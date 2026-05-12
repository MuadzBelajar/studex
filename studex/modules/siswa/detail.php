<?php
// ============================================================
//  STUDEX — Student Index
//  modules/siswa/detail.php — Detail Profil Siswa
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db = db();
$id = sanitizeInt(get('id'));

if (!$id) {
    setFlash('error', 'ID siswa tidak valid.');
    redirect(url('modules/siswa/index.php'));
}

$stmt = $db->prepare("
    SELECT s.*, a.nama as nama_angkatan, a.kode as kode_angkatan
    FROM siswa s JOIN angkatan a ON a.id = s.angkatan_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    setFlash('error', 'Siswa tidak ditemukan.');
    redirect(url('modules/siswa/index.php'));
}

// Presensi stats
$pStat = $db->prepare("SELECT status, COUNT(*) as total FROM presensi WHERE siswa_id=? GROUP BY status");
$pStat->execute([$id]);
$pMap  = array_column($pStat->fetchAll(), 'total', 'status');
$pTotal= array_sum($pMap);
$hadirPct = $pTotal > 0 ? round(($pMap['hadir'] ?? 0) / $pTotal * 100) : 0;

// Riwayat presensi
$pHistory = $db->prepare("
    SELECT p.modul, p.status, p.dicatat_pada,
        CASE p.modul
            WHEN 'rabuan'    THEN (SELECT judul FROM rabuan WHERE id = p.referensi_id)
            WHEN 'mentoring' THEN (SELECT judul_materi FROM mentoring_sesi WHERE id = p.referensi_id)
            WHEN 'binjas'    THEN (SELECT nama_sesi FROM binjas_sesi WHERE id = p.referensi_id)
        END as kegiatan_nama
    FROM presensi p WHERE p.siswa_id=? ORDER BY p.dicatat_pada DESC LIMIT 10
");
$pHistory->execute([$id]);
$pHistory = $pHistory->fetchAll();

// Binjas items
$binjasItems = $db->query("SELECT id, nama_item, satuan FROM binjas_item WHERE is_aktif=1 ORDER BY urutan")->fetchAll();

// Skor sesi terbaru untuk radar
$latestSesi = $db->prepare("SELECT DISTINCT sesi_id FROM binjas_skor WHERE siswa_id=? ORDER BY sesi_id DESC LIMIT 1");
$latestSesi->execute([$id]);
$latestSesiId = $latestSesi->fetchColumn();

$radarLabels  = array_column($binjasItems, 'nama_item');
$radarSiswa   = array_fill(0, count($binjasItems), 0);
$radarStandar = array_fill(0, count($binjasItems), 0);

if ($latestSesiId) {
    $lScores = $db->prepare("
        SELECT bs.item_id, bs.nilai, std.nilai_standar
        FROM binjas_skor bs
        LEFT JOIN binjas_standarisasi std ON std.item_id = bs.item_id
            AND (std.angkatan_id IS NULL OR std.angkatan_id = ?)
            AND (std.berlaku_sampai IS NULL OR std.berlaku_sampai >= CURDATE())
        WHERE bs.siswa_id=? AND bs.sesi_id=?
    ");
    $lScores->execute([$siswa['angkatan_id'], $id, $latestSesiId]);
    $sMap = array_column($lScores->fetchAll(), null, 'item_id');
    foreach ($binjasItems as $idx => $item) {
        if (isset($sMap[$item['id']])) {
            $radarSiswa[$idx]  = (float)$sMap[$item['id']]['nilai'];
            $radarStandar[$idx]= (float)($sMap[$item['id']]['nilai_standar'] ?? 0);
        }
    }
}

// Riwayat skor binjas
$binjasScores = $db->prepare("
    SELECT bs.item_id, bs.nilai, bs.catatan,
           s.nama_sesi, s.tanggal, bi.nama_item, bi.satuan, std.nilai_standar
    FROM binjas_skor bs
    JOIN binjas_sesi s  ON s.id  = bs.sesi_id
    JOIN binjas_item bi ON bi.id = bs.item_id
    LEFT JOIN binjas_standarisasi std ON std.item_id = bs.item_id
        AND (std.angkatan_id IS NULL OR std.angkatan_id = ?)
        AND (std.berlaku_sampai IS NULL OR std.berlaku_sampai >= CURDATE())
    WHERE bs.siswa_id=?
    ORDER BY s.tanggal DESC, bi.urutan ASC LIMIT 30
");
$binjasScores->execute([$siswa['angkatan_id'], $id]);
$binjasScores = $binjasScores->fetchAll();

// Riwayat operasional
$opsHistory = $db->prepare("
    SELECT o.nama_kegiatan, o.tanggal_mulai, o.tanggal_selesai, o.lokasi, o.status, op.peran
    FROM operasional_peserta op
    JOIN operasional o ON o.id = op.operasional_id
    WHERE op.siswa_id=? ORDER BY o.tanggal_mulai DESC LIMIT 5
");
$opsHistory->execute([$id]);
$opsHistory = $opsHistory->fetchAll();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = e($siswa['nama']);
$activePage  = 'siswa';
$extraJs     = ['charts.js'];
$breadcrumbs = [
    ['label' => 'Dashboard',  'url' => url('modules/dashboard/index.php')],
    ['label' => 'Data Siswa', 'url' => url('modules/siswa/index.php')],
    ['label' => e($siswa['nama'])],
];

ob_start();
?>

<script>
window.STUDEX_BASE_URL = '<?= BASE_URL ?>';
window.chartData_radarSiswa = {
    labels      : <?= json_encode($radarLabels) ?>,
    dataSiswa   : <?= json_encode($radarSiswa) ?>,
    dataStandar : <?= json_encode($radarStandar) ?>,
    labelSiswa  : '<?= e(addslashes($siswa['nama'])) ?>',
    labelStandar: 'Standarisasi',
    min: 0,
    max: <?= !empty($radarStandar) && max($radarStandar) > 0 ? ceil(max($radarStandar) * 1.3) : 100 ?>,
};
</script>

<!-- PROFIL HEADER -->
<div class="card mb-5">
    <div class="flex items-start gap-5 flex-wrap">
        <div class="avatar avatar-2xl"
             style="background-color:<?= $siswa['jenis_kelamin']==='L' ? 'var(--color-army)' : 'var(--color-green-dark-300)' ?>;font-size:var(--text-xl);">
            <?= getInitials($siswa['nama']) ?>
        </div>
        <div style="flex:1;min-width:200px;">
            <div class="flex items-center gap-3 flex-wrap mb-2">
                <h2 style="font-size:var(--text-lg);font-weight:var(--fw-bold);color:var(--text-primary);">
                    <?= e($siswa['nama']) ?>
                </h2>
                <?= statusBadge($siswa['status']) ?>
                <span class="badge badge-army"><?= e($siswa['kode_angkatan']) ?></span>
            </div>
            <div class="flex flex-wrap gap-4" style="font-size:var(--text-sm);color:var(--text-muted);">
                <span><strong style="color:var(--text-secondary);">NIS:</strong> <?= e($siswa['nis']) ?></span>
                <span><strong style="color:var(--text-secondary);">JK:</strong> <?= $siswa['jenis_kelamin']==='L' ? 'Laki-laki' : 'Perempuan' ?></span>
                <?php if ($siswa['tempat_lahir'] && $siswa['tanggal_lahir']): ?>
                <span><strong style="color:var(--text-secondary);">TTL:</strong> <?= e($siswa['tempat_lahir']) ?>, <?= formatTanggalPendek($siswa['tanggal_lahir']) ?></span>
                <?php endif; ?>
                <?php if ($siswa['no_hp']): ?>
                <span><strong style="color:var(--text-secondary);">HP:</strong> <?= e($siswa['no_hp']) ?></span>
                <?php endif; ?>
                <?php if ($siswa['email']): ?>
                <span><strong style="color:var(--text-secondary);">Email:</strong> <?= e($siswa['email']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($siswa['alamat']): ?>
            <div style="font-size:var(--text-sm);color:var(--text-muted);margin-top:var(--space-2);">
                <strong style="color:var(--text-secondary);">Alamat:</strong> <?= e($siswa['alamat']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <a href="<?= url('modules/siswa/edit.php?id='.$id) ?>" class="btn btn-secondary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit
            </a>
            <button class="btn btn-danger-outline btn-sm" data-confirm data-type="danger"
                    data-title="Hapus Siswa" data-message="Yakin hapus <?= e(addslashes($siswa['nama'])) ?>?"
                    data-action="<?= url('modules/siswa/delete.php') ?>" data-id="<?= $id ?>" data-label="Ya, Hapus">
                Hapus
            </button>
        </div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="grid grid-4 gap-4 mb-6">
    <?php
    foreach ([
        ['Hadir','hadir','var(--color-success)'],
        ['Izin','izin','var(--color-tosca)'],
        ['Sakit','sakit','var(--color-warning)'],
        ['Alpha','alpha','var(--color-danger)'],
    ] as [$lbl,$key,$clr]):
    ?>
    <div class="card card-sm" style="text-align:center;">
        <div style="font-size:1.75rem;font-weight:var(--fw-bold);color:<?= $clr ?>;line-height:1;">
            <?= $pMap[$key] ?? 0 ?>
        </div>
        <div style="font-size:var(--text-xs);color:var(--text-muted);margin-top:4px;"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- RADAR + PRESENSI -->
<div class="grid grid-2 gap-5 mb-5">

    <div class="chart-card">
        <div class="chart-card-header">
            <div><div class="chart-card-title">Profil Bina Jasmani</div>
            <div class="chart-card-subtitle">Nilai vs Standarisasi</div></div>
        </div>
        <?php if (array_sum($radarSiswa) > 0): ?>
        <div class="radar-chart-wrapper">
            <canvas id="radarSiswa" data-chart="radar" data-chart-id="radarSiswa" style="max-width:320px;max-height:320px;"></canvas>
        </div>
        <?php else: ?>
        <div class="chart-empty"><p>Belum ada data Bina Jasmani</p></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Riwayat Presensi</div>
            <div style="font-size:var(--text-sm);font-weight:var(--fw-semibold);color:var(--primary);"><?= $hadirPct ?>%</div>
        </div>
        <div class="attendance-bar mb-4" style="height:6px;">
            <div class="attendance-bar-segment hadir" style="width:<?= $hadirPct ?>%"></div>
            <div class="attendance-bar-segment alpha" style="width:<?= 100-$hadirPct ?>%"></div>
        </div>
        <?php if (!empty($pHistory)): ?>
        <div style="display:flex;flex-direction:column;gap:var(--space-2);max-height:280px;overflow-y:auto;">
            <?php foreach ($pHistory as $p): ?>
            <div style="display:flex;align-items:center;gap:var(--space-3);padding:var(--space-2) 0;border-bottom:1px solid var(--border-color);">
                <div style="flex:1;min-width:0;">
                    <div style="font-size:var(--text-sm);font-weight:var(--fw-medium);color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= e($p['kegiatan_nama'] ?? '-') ?>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);">
                        <?= ucfirst($p['modul']) ?> · <?= formatTanggalPendek($p['dicatat_pada']) ?>
                    </div>
                </div>
                <?= statusBadge($p['status']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:var(--space-6) 0;">
            <div class="empty-state-title" style="font-size:var(--text-sm);">Belum ada riwayat presensi</div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- RIWAYAT OPERASIONAL -->
<?php if (!empty($opsHistory)): ?>
<div class="card mb-5">
    <div class="card-header"><div class="card-title">Riwayat Operasional</div></div>
    <div class="table-wrapper">
        <table class="table table-compact">
            <thead>
                <tr><th>Kegiatan</th><th>Tanggal</th><th>Lokasi</th><th>Peran</th><th class="col-center">Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($opsHistory as $ops): ?>
                <tr>
                    <td style="font-weight:var(--fw-medium);"><?= e($ops['nama_kegiatan']) ?></td>
                    <td style="font-size:var(--text-xs);color:var(--text-muted);"><?= formatTanggalPendek($ops['tanggal_mulai']) ?></td>
                    <td><?= e($ops['lokasi'] ?? '-') ?></td>
                    <td><?= e($ops['peran'] ?? '-') ?></td>
                    <td class="col-center"><?= statusBadge($ops['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- SKOR BINJAS -->
<?php if (!empty($binjasScores)): ?>
<div class="card">
    <div class="card-header"><div class="card-title">Riwayat Skor Bina Jasmani</div></div>
    <div class="table-wrapper">
        <table class="table table-compact">
            <thead>
                <tr><th>Sesi</th><th>Item</th><th class="col-right">Nilai</th><th class="col-right">Standar</th><th class="col-center">Ket.</th></tr>
            </thead>
            <tbody>
                <?php foreach ($binjasScores as $sk):
                    $std  = (float)($sk['nilai_standar'] ?? 0);
                    $val  = (float)$sk['nilai'];
                    $pass = $std > 0 && $val >= $std;
                ?>
                <tr>
                    <td>
                        <div style="font-size:var(--text-sm);font-weight:var(--fw-medium);"><?= e($sk['nama_sesi']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= formatTanggalPendek($sk['tanggal']) ?></div>
                    </td>
                    <td><?= e($sk['nama_item']) ?></td>
                    <td class="col-right">
                        <span style="font-weight:var(--fw-semibold);color:<?= $std>0 ? ($pass ? 'var(--color-success)' : 'var(--color-danger)') : 'var(--text-primary)' ?>;">
                            <?= $val ?> <small><?= e($sk['satuan'] ?? '') ?></small>
                        </span>
                    </td>
                    <td class="col-right" style="color:var(--text-muted);"><?= $std > 0 ? $std : '-' ?></td>
                    <td class="col-center">
                        <?php if ($std > 0): ?>
                            <span class="badge <?= $pass ? 'badge-success' : 'badge-danger' ?>"><?= $pass ? 'Lulus' : 'Belum' ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>