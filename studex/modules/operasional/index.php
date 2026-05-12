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

// Filter
$filterFase   = sanitize(get('fase', ''));
$filterStatus = sanitize(get('status', ''));
$filterCari   = sanitize(get('cari', ''));
$page         = max(1, sanitizeInt(get('page', 1)));
$perPage      = 10;
$offset       = ($page - 1) * $perPage;

// Build WHERE
$where  = ['1=1'];
$params = [];

if ($filterFase) {
    $where[]  = 'o.fase = ?';
    $params[] = $filterFase;
}
if ($filterStatus) {
    $where[]  = 'o.status = ?';
    $params[] = $filterStatus;
}
if ($filterCari) {
    $where[]  = '(o.nama_kegiatan LIKE ? OR o.lokasi LIKE ?)';
    $params[] = "%$filterCari%";
    $params[] = "%$filterCari%";
}

$whereStr = implode(' AND ', $where);

// Count total
$stmtCount = $db->prepare("SELECT COUNT(*) FROM operasional o WHERE $whereStr");
$stmtCount->execute($params);
$total     = $stmtCount->fetchColumn();
$totalPage = ceil($total / $perPage);

// Data
$stmt = $db->prepare("
    SELECT o.*,
           a.nama AS nama_angkatan,
           u.nama AS created_by_nama,
           (SELECT COUNT(*) FROM operasional_peserta op WHERE op.operasional_id = o.id) AS jumlah_peserta
    FROM operasional o
    LEFT JOIN angkatan a ON a.id = o.angkatan_id
    LEFT JOIN users u ON u.id = o.created_by
    WHERE $whereStr
    ORDER BY o.tanggal_mulai DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$operasionals = $stmt->fetchAll();

// Stat ringkas
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='draft'      THEN 1 ELSE 0 END) AS draft,
        SUM(CASE WHEN status='aktif'      THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status='selesai'    THEN 1 ELSE 0 END) AS selesai,
        SUM(CASE WHEN status='dibatalkan' THEN 1 ELSE 0 END) AS dibatalkan
    FROM operasional
")->fetch();

$pageTitle    = 'Operasional';
$pageSubtitle = 'Manajemen kegiatan lapangan';
$activePage   = 'operasional';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional'],
];
$extraJs = ['table.js'];

ob_start();
?>

<!-- Stat Cards -->
<div class="grid grid-4 mb-6">
    <div class="card stat-card">
        <div class="stat-icon" style="background:var(--primary-light);color:var(--primary);">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total Kegiatan</span>
            <span class="stat-value"><?= $stats['total'] ?></span>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background:#fff8e7;color:var(--warning);">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Draft</span>
            <span class="stat-value"><?= $stats['draft'] ?></span>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background:#eaf3ec;color:var(--secondary);">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Aktif</span>
            <span class="stat-value"><?= $stats['aktif'] ?></span>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon" style="background:#eaf3ec;color:var(--primary);">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Selesai</span>
            <span class="stat-value"><?= $stats['selesai'] ?></span>
        </div>
    </div>
</div>

<!-- Filter & Actions -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" name="cari" class="form-control"
                           placeholder="Cari kegiatan atau lokasi…"
                           value="<?= e($filterCari) ?>">
                </div>
                <div class="filter-group filter-group--sm">
                    <select name="fase" class="form-control">
                        <option value="">Semua Fase</option>
                        <option value="pra"         <?= $filterFase === 'pra'         ? 'selected' : '' ?>>Pra-Operasional</option>
                        <option value="operasional" <?= $filterFase === 'operasional' ? 'selected' : '' ?>>Operasional</option>
                        <option value="pasca"       <?= $filterFase === 'pasca'       ? 'selected' : '' ?>>Pasca-Operasional</option>
                    </select>
                </div>
                <div class="filter-group filter-group--sm">
                    <select name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="draft"      <?= $filterStatus === 'draft'      ? 'selected' : '' ?>>Draft</option>
                        <option value="aktif"      <?= $filterStatus === 'aktif'      ? 'selected' : '' ?>>Aktif</option>
                        <option value="selesai"    <?= $filterStatus === 'selesai'    ? 'selected' : '' ?>>Selesai</option>
                        <option value="dibatalkan" <?= $filterStatus === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Filter
                    </button>
                    <?php if ($filterFase || $filterStatus || $filterCari): ?>
                        <a href="<?= url('modules/operasional/index.php') ?>" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                    <a href="<?= url('modules/operasional/create.php') ?>" class="btn btn-primary ml-auto">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah Kegiatan
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabel -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Kegiatan Operasional</h3>
        <span class="badge badge-primary"><?= $total ?> kegiatan</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($operasionals)): ?>
            <div class="empty-state">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     style="color:var(--grey);margin-bottom:16px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="empty-title">Belum ada kegiatan operasional</p>
                <p class="empty-desc">Mulai buat kegiatan lapangan pertama.</p>
                <a href="<?= url('modules/operasional/create.php') ?>" class="btn btn-primary mt-3">+ Tambah Kegiatan</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Kegiatan</th>
                            <th>Angkatan</th>
                            <th>Tanggal</th>
                            <th>Lokasi</th>
                            <th>Peserta</th>
                            <th>Fase</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operasionals as $i => $row): ?>
                            <tr>
                                <td><?= $offset + $i + 1 ?></td>
                                <td>
                                    <a href="<?= url('modules/operasional/detail.php?id=' . $row['id']) ?>"
                                       class="text-primary font-medium">
                                        <?= e($row['nama_kegiatan']) ?>
                                    </a>
                                </td>
                                <td><?= e($row['nama_angkatan'] ?? '-') ?></td>
                                <td>
                                    <?= formatTanggal($row['tanggal_mulai']) ?>
                                    <?php if (!empty($row['tanggal_selesai']) && $row['tanggal_selesai'] !== $row['tanggal_mulai']): ?>
                                        <span class="text-muted"> – <?= formatTanggal($row['tanggal_selesai']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($row['lokasi'] ?? '-') ?></td>
                                <td><span class="badge badge-info"><?= $row['jumlah_peserta'] ?> siswa</span></td>
                                <td>
                                    <?php
                                    $faseBadge = [
                                        'pra'         => ['Pra-Ops',   'badge-warning'],
                                        'operasional' => ['Ops',       'badge-info'],
                                        'pasca'       => ['Pasca-Ops', 'badge-success'],
                                    ];
                                    [$fl, $fc] = $faseBadge[$row['fase']] ?? [$row['fase'], 'badge-secondary'];
                                    ?>
                                    <span class="badge <?= $fc ?>"><?= $fl ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusCls = [
                                        'draft'      => 'badge-secondary',
                                        'aktif'      => 'badge-primary',
                                        'selesai'    => 'badge-success',
                                        'dibatalkan' => 'badge-danger',
                                    ];
                                    $sc = $statusCls[$row['status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?= $sc ?>"><?= ucfirst(e($row['status'])) ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= url('modules/operasional/detail.php?id=' . $row['id']) ?>"
                                           class="btn-icon btn-icon--view" title="Detail">
                                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <?php if ($row['status'] !== 'dibatalkan'): ?>
                                            <a href="<?= url('modules/operasional/edit.php?id=' . $row['id']) ?>"
                                               class="btn-icon btn-icon--edit" title="Edit">
                                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn-icon btn-icon--delete" title="Hapus"
                                                onclick="confirmDelete(<?= $row['id'] ?>, '<?= e($row['nama_kegiatan']) ?>')">
                                            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPage > 1): ?>
        <div class="card-footer">
            <?php
            $q = http_build_query(array_filter(['fase' => $filterFase, 'status' => $filterStatus, 'cari' => $filterCari]));
            echo renderPagination($page, $totalPage, url('modules/operasional/index.php') . '?' . $q);
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden delete form -->
<form id="deleteForm" method="POST" action="<?= url('modules/operasional/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id, nama) {
    if (confirm('Hapus kegiatan "' + nama + '"?\nData peserta dan laporan terkait juga akan dihapus.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';