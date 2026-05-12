<?php
// ============================================================
//  STUDEX — Student Index
//  modules/dashboard/index.php — Dasbor Utama
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

// ============================================================
// AMBIL DATA STATISTIK
// ============================================================

$totalSiswa     = (int)$db->query("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")->fetchColumn();
$totalAngkatan  = (int)$db->query("SELECT COUNT(*) FROM angkatan WHERE is_aktif = 1")->fetchColumn();
$totalRabuan    = (int)$db->query("SELECT COUNT(*) FROM rabuan WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetchColumn();

$totalMentoring = (int)(
    (function () use ($db) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM mentoring_sesi WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())");
        $stmt->execute();
        return $stmt->fetchColumn() ?: 0;
    })()
);

$totalOps = (int)$db->query("SELECT COUNT(*) FROM operasional WHERE status IN ('draft','aktif')")->fetchColumn();

// Binjas bulan ini
$totalBinjas = (int)(
    (function () use ($db) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM binjas_sesi WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())");
        $stmt->execute();
        return $stmt->fetchColumn() ?: 0;
    })()
);


// ============================================================
// KEHADIRAN 7 SESI TERAKHIR (Rabuan) — minim kolom untuk hemat memori
// ============================================================
$rabuanSesi = $db->query(
    "SELECT r.id, r.tanggal,
            SUM(CASE WHEN p.status='hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN p.status='alpha' THEN 1 ELSE 0 END) as alpha
     FROM rabuan r
     LEFT JOIN presensi p
       ON p.modul='rabuan' AND p.referensi_id=r.id
     WHERE r.status='selesai'
     GROUP BY r.id, r.tanggal
     ORDER BY r.tanggal DESC
     LIMIT 7"
)->fetchAll();


$rabuanLabels = array_reverse(array_map(function ($d) {
    return date('d M', strtotime($d));
}, array_column($rabuanSesi, 'tanggal')));

$rabuanHadir = array_reverse(array_column($rabuanSesi, 'hadir'));
$rabuanAlpha = array_reverse(array_column($rabuanSesi, 'alpha'));

// ============================================================
// KEGIATAN MENDATANG (7 hari ke depan) — batasi per modul
// ============================================================
$upcoming = $db->query(
    "(
        SELECT 'Rabuan' AS modul, judul AS judul, tanggal AS tanggal, waktu_mulai, status, id
        FROM rabuan
        WHERE tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND status IN ('terjadwal','berlangsung')
        ORDER BY tanggal ASC, waktu_mulai ASC
        LIMIT 2
    )
    UNION ALL
    (
        SELECT 'Mentoring' AS modul, judul_materi AS judul, tanggal, waktu_mulai, status, id
        FROM mentoring_sesi
        WHERE tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND status IN ('terjadwal','berlangsung')
        ORDER BY tanggal ASC, waktu_mulai ASC
        LIMIT 2
    )
    UNION ALL
    (
        SELECT 'Operasional' AS modul, nama_kegiatan AS judul, tanggal_mulai AS tanggal, NULL AS waktu_mulai, status, id
        FROM operasional
        WHERE tanggal_mulai BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND status IN ('draft','aktif')
        ORDER BY tanggal_mulai ASC
        LIMIT 2
    )
    UNION ALL
    (
        SELECT 'Binjas' AS modul, nama_sesi AS judul, tanggal, waktu_mulai, status, id
        FROM binjas_sesi
        WHERE tanggal BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND status IN ('terjadwal','berlangsung')
        ORDER BY tanggal ASC, waktu_mulai ASC
        LIMIT 2
    )
    ORDER BY tanggal ASC, waktu_mulai ASC
    LIMIT 8"
)->fetchAll();

// ============================================================
// REKAP PRESENSI BULAN INI (per modul) — agregasi ringan
// ============================================================
$presensiRaw = $db->query(
    "SELECT modul,
            COUNT(*) as total,
            SUM(CASE WHEN status='hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN status='alpha' THEN 1 ELSE 0 END) as alpha
     FROM presensi
     WHERE MONTH(dicatat_pada)=MONTH(CURDATE())
       AND YEAR(dicatat_pada)=YEAR(CURDATE())
     GROUP BY modul"
)->fetchAll();

$presensiByModul = [];
foreach ($presensiRaw as $row) {
    $presensiByModul[$row['modul']] = $row;
}

// ============================================================
// SISWA BARU (batasi)
// ============================================================
$siswaBaru = $db->query(
    "SELECT s.nama, s.nis, s.jenis_kelamin, a.nama as angkatan, s.created_at
     FROM siswa s
     JOIN angkatan a ON a.id = s.angkatan_id
     ORDER BY s.created_at DESC
     LIMIT 5"
)->fetchAll();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Dashboard';
$pageSubtitle = 'Selamat datang, ' . ($user['nama'] ?? '-') . '!';
$activePage   = 'dashboard';
$breadcrumbs  = [['label' => 'Dashboard']];
$extraJs      = ['charts.js', 'calendar.js'];

ob_start();
?>

<!-- BASE URL untuk JS -->
<script>window.STUDEX_BASE_URL = '<?= e(BASE_URL) ?>';</script>

<!-- ============================================================
     STAT CARDS
     ============================================================ -->
<div class="grid grid-4 gap-5 mb-6">

    <div class="stat-card">
        <div class="stat-card-top">
            <div>
                <div class="stat-card-label">Total Siswa Aktif</div>
                <div class="stat-card-value"><?= formatAngka($totalSiswa) ?></div>
            </div>
            <div class="card-icon card-icon-green"></div>
        </div>
        <div class="stat-card-meta">
            <span class="stat-trend-badge up"><?= $totalAngkatan ?> angkatan</span>
            <span class="stat-card-meta-text">aktif saat ini</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-top">
            <div>
                <div class="stat-card-label">Rabuan Bulan Ini</div>
                <div class="stat-card-value"><?= formatAngka($totalRabuan) ?></div>
            </div>
        </div>
        <div class="stat-card-meta"><span class="stat-card-meta-text"><?= date('F Y') ?></span></div>
    </div>

    <div class="stat-card">
        <div class="stat-card-top">
            <div>
                <div class="stat-card-label">Mentoring Bulan Ini</div>
                <div class="stat-card-value"><?= formatAngka($totalMentoring) ?></div>
            </div>
        </div>
        <div class="stat-card-meta"><span class="stat-card-meta-text"><?= date('F Y') ?></span></div>
    </div>

    <div class="stat-card">
        <div class="stat-card-top">
            <div>
                <div class="stat-card-label">Operasional Aktif</div>
                <div class="stat-card-value"><?= formatAngka($totalOps) ?></div>
            </div>
        </div>
        <div class="stat-card-meta">
            <?php if ($totalOps > 0): ?>
                <span class="stat-trend-badge up">Sedang berjalan</span>
            <?php else: ?>
                <span class="stat-card-meta-text">Tidak ada kegiatan aktif</span>
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="grid grid-2-1 gap-5 mb-6">

    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <div class="chart-card-title">Tren Kehadiran Rabuan</div>
                <div class="chart-card-subtitle">7 sesi terakhir</div>
            </div>
        </div>

        <?php if (!empty($rabuanSesi)): ?>
            <div class="chart-container chart-h-md">
                <canvas id="rabuanChart" data-chart="bar" data-chart-id="rabuanChart"></canvas>
            </div>
            <div class="chart-legend mt-3">
                <div class="chart-legend-item"><span class="chart-legend-dot legend-green"></span> Hadir</div>
                <div class="chart-legend-item"><span class="chart-legend-dot legend-red"></span> Alpha</div>
            </div>
        <?php else: ?>
            <div class="chart-empty"><p>Belum ada data kehadiran</p></div>
        <?php endif; ?>
    </div>

    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <div class="chart-card-title">Rekap Presensi</div>
                <div class="chart-card-subtitle"><?= date('F Y') ?></div>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            <?php
            $modulPresensi = [
                'rabuan'    => ['label' => 'Rabuan'],
                'mentoring' => ['label' => 'Mentoring'],
                'binjas'    => ['label' => 'Binjas'],
            ];
            foreach ($modulPresensi as $key => $info):
                $data  = $presensiByModul[$key] ?? ['total' => 0, 'hadir' => 0, 'alpha' => 0];
                $total = (int)$data['total'];
                $hadir = (int)$data['hadir'];
                $pct   = $total > 0 ? round(($hadir / $total) * 100) : 0;
            ?>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span style="font-size:var(--text-sm);font-weight:var(--fw-medium);color:var(--text-primary);">
                        <?= $info['label'] ?>
                    </span>
                    <span style="font-size:var(--text-sm);font-weight:var(--fw-semibold);color:var(--primary);"><?= $pct ?>%</span>
                </div>
                <div class="attendance-bar">
                    <div class="attendance-bar-segment hadir" style="width:<?= $pct ?>%"></div>
                    <?php if ($total > 0): ?>
                        <div class="attendance-bar-segment alpha" style="width:<?= 100 - $pct ?>%"></div>
                    <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= $hadir ?> hadir dari <?= $total ?> presensi</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
window.chartData_rabuanChart = {
    labels: <?= json_encode(array_values($rabuanLabels)) ?>,
    datasets: [
        { label: 'Hadir', data: <?= json_encode(array_values($rabuanHadir)) ?> },
        { label: 'Alpha', data: <?= json_encode(array_values($rabuanAlpha)) ?> }
    ]
};
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>

