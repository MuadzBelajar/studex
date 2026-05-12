<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db = db();
$id = sanitizeInt(get('id'));

if (!$id) {
    setFlash('error', 'ID sesi tidak valid.');
    redirect(url('modules/binjas/index.php'));
}

$stmt = $db->prepare("
    SELECT b.*, a.nama AS nama_angkatan, u.nama AS created_by_nama
    FROM binjas_sesi b
    LEFT JOIN angkatan a ON a.id = b.angkatan_id
    LEFT JOIN users u ON u.id = b.created_by
    WHERE b.id = ?
");
$stmt->execute([$id]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Sesi tidak ditemukan.');
    redirect(url('modules/binjas/index.php'));
}

// Item binjas
$items = $db->query("SELECT * FROM binjas_item ORDER BY urutan, nama_item")->fetchAll();

// Standarisasi — prioritaskan yang spesifik angkatan
$standarRows = $db->prepare("
    SELECT bs.item_id, bs.nilai_standar, bs.angkatan_id
    FROM binjas_standarisasi bs
    WHERE bs.angkatan_id = ? OR bs.angkatan_id IS NULL
    ORDER BY bs.angkatan_id DESC
");
$standarRows->execute([$sesi['angkatan_id']]);
$standarMap = [];
foreach ($standarRows->fetchAll() as $s) {
    if (!isset($standarMap[$s['item_id']])) {
        $standarMap[$s['item_id']] = (float)$s['nilai_standar'];
    }
}

// Skor
$skorRaw = $db->prepare("
    SELECT bs.siswa_id, bs.item_id, bs.nilai,
           s.nama AS nama_siswa, s.nomor_induk
    FROM binjas_skor bs
    JOIN siswa s ON s.id = bs.siswa_id
    WHERE bs.sesi_id = ?
    ORDER BY s.nama, bs.item_id
");
$skorRaw->execute([$id]);
$skorRaw = $skorRaw->fetchAll();

// Reorganisasi per siswa
$siswaMap = [];
foreach ($skorRaw as $row) {
    $sid = $row['siswa_id'];
    if (!isset($siswaMap[$sid])) {
        $siswaMap[$sid] = [
            'nama'        => $row['nama_siswa'],
            'nomor_induk' => $row['nomor_induk'],
            'skor'        => [],
        ];
    }
    $siswaMap[$sid]['skor'][$row['item_id']] = (float)$row['nilai'];
}

// Rata-rata per item
$avgPerItem = [];
foreach ($items as $item) {
    $vals = array_map(
        fn($r) => (float)$r['nilai'],
        array_filter($skorRaw, fn($r) => $r['item_id'] == $item['id'])
    );
    $avgPerItem[$item['id']] = count($vals)
        ? round(array_sum($vals) / count($vals), 1)
        : 0;
}

// Presensi
$presensiRows = $db->prepare("
    SELECT p.siswa_id, p.status
    FROM presensi p
    WHERE p.modul = 'binjas' AND p.referensi_id = ?
");
$presensiRows->execute([$id]);
$presensiMap = array_column($presensiRows->fetchAll(), 'status', 'siswa_id');

$pageTitle    = e($sesi['nama_sesi']);
$pageSubtitle = 'Detail Sesi Binjas';
$activePage   = 'binjas';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Binjas',    'url' => url('modules/binjas/index.php')],
    ['label' => e($sesi['nama_sesi'])],
];

ob_start();
?>

<!-- Header -->
<div class="card mb-4">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;"><?= e($sesi['nama_sesi']) ?></h2>
                <div style="display:flex;flex-wrap:wrap;gap:8px 24px;font-size:13px;color:var(--grey);">
                    <span><strong>Angkatan:</strong> <?= e($sesi['nama_angkatan'] ?? '-') ?></span>
                    <span><strong>Tanggal:</strong> <?= formatTanggal($sesi['tanggal']) ?></span>
                    <span><strong>Lokasi:</strong> <?= e($sesi['lokasi'] ?? '-') ?></span>
                    <span><strong>Dibuat oleh:</strong> <?= e($sesi['created_by_nama'] ?? '-') ?></span>
                </div>
                <?php if (!empty($sesi['deskripsi'])): ?>
                    <p style="margin-top:8px;font-size:13px;color:var(--grey);"><?= nl2br(e($sesi['deskripsi'])) ?></p>
                <?php endif; ?>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                <?php
                $statusCls = ['draft'=>'badge-secondary','aktif'=>'badge-primary','selesai'=>'badge-success'];
                $sc = $statusCls[$sesi['status']] ?? 'badge-secondary';
                ?>
                <span class="badge <?= $sc ?>" style="font-size:13px;padding:5px 14px;">
                    <?= ucfirst(e($sesi['status'])) ?>
                </span>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="<?= url('modules/binjas/edit.php?id=' . $id) ?>"
                       class="btn btn-secondary btn-sm">Edit</a>
                    <a href="<?= url('modules/binjas/input_skor.php?sesi_id=' . $id) ?>"
                       class="btn btn-primary btn-sm">Input Skor</a>
                    <a href="<?= url('modules/binjas/presensi.php?sesi_id=' . $id) ?>"
                       class="btn btn-secondary btn-sm">Presensi</a>
                    <button type="button" class="btn btn-danger btn-sm"
                            onclick="confirmDelete(<?= $id ?>, '<?= e($sesi['nama_sesi']) ?>')">Hapus</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart + Stat -->
<div class="grid grid-2 gap-4 mb-4" style="align-items:start;">

    <!-- Radar Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Radar Rata-rata Skor</h3>
            <span class="badge badge-info"><?= count($siswaMap) ?> peserta</span>
        </div>
        <div class="card-body" style="display:flex;align-items:center;justify-content:center;min-height:280px;">
            <?php if (empty($items) || empty($siswaMap)): ?>
                <div class="empty-state empty-state--sm">
                    <p class="empty-desc">Belum ada skor yang diinput.</p>
                    <a href="<?= url('modules/binjas/input_skor.php?sesi_id=' . $id) ?>"
                       class="btn btn-sm btn-primary mt-2">Input Skor</a>
                </div>
            <?php else: ?>
                <canvas id="radarChart" style="max-width:100%;max-height:300px;"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabel rata-rata item -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Rata-rata per Item</h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
                <div class="empty-state empty-state--sm">
                    <p class="empty-desc">Belum ada item. Tambah di
                        <a href="<?= url('modules/binjas/standarisasi.php') ?>">Standarisasi</a>.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Satuan</th>
                                <th>Rata-rata</th>
                                <th>Standar</th>
                                <th>Ket.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $avg     = $avgPerItem[$item['id']] ?? 0;
                                $standar = $standarMap[$item['id']] ?? null;
                                $lulus   = $standar !== null ? ($avg >= $standar) : null;
                                ?>
                                <tr>
                                    <td><?= e($item['nama_item']) ?></td>
                                    <td><span class="badge badge-secondary"><?= e($item['satuan']) ?></span></td>
                                    <td><strong><?= $avg ?: '—' ?></strong></td>
                                    <td><?= $standar ?? '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <?php if ($lulus === null): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif ($lulus): ?>
                                            <span class="badge badge-success">✓</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">✗</span>
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

<!-- Tabel Skor Siswa -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Skor Per Siswa</h3>
        <div class="card-actions">
            <span class="badge badge-info"><?= count($siswaMap) ?> siswa</span>
            <a href="<?= url('modules/binjas/input_skor.php?sesi_id=' . $id) ?>"
               class="btn btn-sm btn-primary">Input / Edit Skor</a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($siswaMap)): ?>
            <div class="empty-state">
                <p class="empty-title">Belum ada skor diinput</p>
                <p class="empty-desc">Gunakan tombol Input Skor untuk mulai mencatat.</p>
                <a href="<?= url('modules/binjas/input_skor.php?sesi_id=' . $id) ?>"
                   class="btn btn-primary mt-3">Input Skor</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>No. Induk</th>
                            <th>Presensi</th>
                            <?php foreach ($items as $item): ?>
                                <th>
                                    <?= e($item['nama_item']) ?>
                                    <span style="font-size:10px;color:var(--grey);display:block;font-weight:400;">
                                        <?= e($item['satuan']) ?>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                            <th>Total</th>
                            <th>Ket.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($siswaMap as $sid => $siswa): ?>
                            <?php
                            $totalSkor  = array_sum($siswa['skor']);
                            $lulusCount = 0;
                            $itemCount  = count($items);
                            foreach ($items as $item) {
                                $n = $siswa['skor'][$item['id']] ?? null;
                                $s = $standarMap[$item['id']] ?? null;
                                if ($n !== null && $s !== null && $n >= $s) $lulusCount++;
                            }
                            $allLulus  = $itemCount > 0 && $lulusCount === $itemCount;
                            $presStat  = $presensiMap[$sid] ?? null;
                            $presCls   = [
                                'hadir'=>'badge-success','izin'=>'badge-warning',
                                'sakit'=>'badge-info',   'alpha'=>'badge-danger',
                            ];
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($siswa['nama']) ?></td>
                                <td><?= e($siswa['nomor_induk']) ?></td>
                                <td>
                                    <?php if ($presStat): ?>
                                        <span class="badge <?= $presCls[$presStat] ?? 'badge-secondary' ?>">
                                            <?= ucfirst($presStat) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $n  = $siswa['skor'][$item['id']] ?? null;
                                    $s  = $standarMap[$item['id']] ?? null;
                                    $ok = $n !== null && $s !== null && $n >= $s;
                                    $no2= $n !== null && $s !== null && $n < $s;
                                    ?>
                                    <td style="<?= $ok ? 'color:var(--secondary);font-weight:600;' : ($no2 ? 'color:var(--danger);' : '') ?>">
                                        <?= $n !== null ? e($n) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><strong><?= $totalSkor ?></strong></td>
                                <td>
                                    <?php if ($itemCount === 0): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif ($allLulus): ?>
                                        <span class="badge badge-success">Lulus</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Tidak Lulus</span>
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

<!-- Delete form -->
<form id="deleteForm" method="POST" action="<?= url('modules/binjas/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<?php if (!empty($items) && !empty($siswaMap)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const labels  = <?= json_encode(array_column($items, 'nama_item')) ?>;
    const avgData = <?= json_encode(array_values($avgPerItem)) ?>;
    const stdData = <?= json_encode(array_values(array_map(fn($i) => $standarMap[$i['id']] ?? 0, $items))) ?>;

    new Chart(document.getElementById('radarChart'), {
        type: 'radar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Rata-rata Skor',
                    data: avgData,
                    backgroundColor: 'rgba(57,89,23,0.15)',
                    borderColor: '#395917',
                    borderWidth: 2,
                    pointBackgroundColor: '#395917',
                    pointRadius: 4,
                },
                {
                    label: 'Nilai Standar',
                    data: stdData,
                    backgroundColor: 'rgba(139,20,8,0.07)',
                    borderColor: '#8B1408',
                    borderWidth: 1.5,
                    borderDash: [5, 4],
                    pointBackgroundColor: '#8B1408',
                    pointRadius: 3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 } },
                    pointLabels: { font: { size: 11 } }
                }
            },
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 12 }, boxWidth: 14 } }
            }
        }
    });
})();
</script>
<?php endif; ?>

<script>
function confirmDelete(id, nama) {
    if (confirm('Hapus sesi "' + nama + '"?\nSemua skor dan presensi terkait juga akan dihapus.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';