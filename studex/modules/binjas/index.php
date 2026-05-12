<?php
// ============================================================
//  STUDEX — Student Index
//  modules/binjas/index.php — Daftar Sesi Pembinaan Jasmani
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db   = db();
$user = currentUser();

// ============================================================
// FILTER & SEARCH
// ============================================================
$search       = sanitize(get('search'));
$filterAngk   = sanitizeInt(get('angkatan_id'));
$filterStatus = sanitize(get('status'));

$angkatanList = $db->query("SELECT id, nama, kode FROM angkatan ORDER BY tahun DESC")->fetchAll();

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(b.nama_sesi LIKE ? OR b.lokasi LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterAngk) {
    $where[]  = 'b.angkatan_id = ?';
    $params[] = $filterAngk;
}
if ($filterStatus) {
    $where[]  = 'b.status = ?';
    $params[] = $filterStatus;
}

$whereStr = implode(' AND ', $where);

// Hitung total
$countStmt = $db->prepare("SELECT COUNT(*) FROM binjas_sesi b WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Pagination
$pag    = paginate($total);
$offset = $pag['offset'];
$limit  = $pag['per_page'];

// Fetch data
$stmt = $db->prepare("
    SELECT b.*,
           a.nama AS nama_angkatan, a.kode AS kode_angkatan,
           u.nama AS nama_pembuat,
           (SELECT COUNT(DISTINCT bs.siswa_id)
            FROM binjas_skor bs WHERE bs.sesi_id = b.id) AS jumlah_peserta,
           (SELECT COUNT(DISTINCT bs.item_id)
            FROM binjas_skor bs WHERE bs.sesi_id = b.id) AS jumlah_item,
           (SELECT COUNT(*) FROM presensi p
            WHERE p.referensi_id = b.id AND p.modul = 'binjas') AS jumlah_presensi
    FROM binjas_sesi b
    LEFT JOIN angkatan a ON a.id = b.angkatan_id
    LEFT JOIN users    u ON u.id = b.created_by
    WHERE $whereStr
    ORDER BY b.tanggal DESC, b.id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$sesiList = $stmt->fetchAll();

// Statistik ringkas
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'draft'   THEN 1 ELSE 0 END) AS draft,
        SUM(CASE WHEN status = 'aktif'   THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) AS selesai
    FROM binjas_sesi
")->fetch();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Binjas';
$pageSubtitle = 'Pembinaan Jasmani Siswa';
$activePage   = 'binjas';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Binjas'],
];

ob_start();
?>

<!-- Stat Cards -->
<div class="stats-row mb-6">
    <div class="stat-card">
        <div class="stat-icon">🏋️</div>
        <div class="stat-card-num"><?= $stats['total'] ?></div>
        <div class="stat-card-label">Total Sesi</div>
    </div>
    <div class="stat-card stat-card-warning">
        <div class="stat-icon">📝</div>
        <div class="stat-card-num"><?= $stats['draft'] ?></div>
        <div class="stat-card-label">Draft</div>
    </div>
    <div class="stat-card stat-card-success">
        <div class="stat-icon">▶️</div>
        <div class="stat-card-num"><?= $stats['aktif'] ?></div>
        <div class="stat-card-label">Aktif</div>
    </div>
    <div class="stat-card stat-card-secondary">
        <div class="stat-icon">✅</div>
        <div class="stat-card-num"><?= $stats['selesai'] ?></div>
        <div class="stat-card-label">Selesai</div>
    </div>
</div>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="page-title">Sesi Binjas</h2>
        <p class="page-subtitle">Total <strong><?= $total ?></strong> sesi ditemukan</p>
    </div>
    <div class="flex gap-2">
        <?php if (isSuperAdmin()): ?>
        <a href="<?= url('modules/binjas/standarisasi.php') ?>" class="btn btn-outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/>
                <line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/>
                <line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/>
                <line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/>
                <line x1="17" y1="16" x2="23" y2="16"/>
            </svg>
            Standarisasi
        </a>
        <?php endif; ?>
        <a href="<?= url('modules/binjas/create.php') ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Sesi
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-5">
    <form method="GET" action="" class="flex flex-wrap items-end gap-4">
        <div class="form-group" style="flex:1;min-width:200px;">
            <label class="form-label">Cari</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Nama sesi / Lokasi..."
                   value="<?= e($search) ?>">
        </div>
        <div class="form-group" style="min-width:180px;">
            <label class="form-label">Angkatan</label>
            <select name="angkatan_id" class="form-control">
                <option value="">Semua Angkatan</option>
                <?php foreach ($angkatanList as $ang): ?>
                    <option value="<?= $ang['id'] ?>" <?= $filterAngk == $ang['id'] ? 'selected' : '' ?>>
                        <?= e($ang['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:150px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua Status</option>
                <option value="draft"   <?= $filterStatus === 'draft'   ? 'selected' : '' ?>>Draft</option>
                <option value="aktif"   <?= $filterStatus === 'aktif'   ? 'selected' : '' ?>>Aktif</option>
                <option value="selesai" <?= $filterStatus === 'selesai' ? 'selected' : '' ?>>Selesai</option>
            </select>
        </div>
        <div class="flex gap-2" style="padding-bottom:1px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= url('modules/binjas/index.php') ?>" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel -->
<div class="card">
    <?php if (empty($sesiList)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div class="empty-state-title">Belum Ada Sesi Binjas</div>
            <div class="empty-state-desc">
                <?= ($search || $filterAngk || $filterStatus)
                    ? 'Tidak ada sesi yang sesuai filter. Coba ubah kriteria pencarian.'
                    : 'Mulai dengan membuat sesi pembinaan jasmani pertama.' ?>
            </div>
            <?php if (!$search && !$filterAngk && !$filterStatus): ?>
                <a href="<?= url('modules/binjas/create.php') ?>" class="btn btn-primary mt-4">
                    Buat Sesi Pertama
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Nama Sesi</th>
                        <th>Angkatan</th>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th style="text-align:center;">Peserta</th>
                        <th style="text-align:center;">Item</th>
                        <th style="text-align:center;">Status</th>
                        <th style="width:130px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sesiList as $i => $b): ?>
                    <tr>
                        <td class="text-muted"><?= $pag['offset'] + $i + 1 ?></td>
                        <td>
                            <div class="fw-medium"><?= e($b['nama_sesi']) ?></div>
                            <?php if ($b['nama_pembuat']): ?>
                                <small class="text-muted" style="font-size:11px;">
                                    oleh <?= e($b['nama_pembuat']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['nama_angkatan']): ?>
                                <span class="badge badge-secondary"><?= e($b['kode_angkatan']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatTanggal($b['tanggal']) ?></td>
                        <td><?= $b['lokasi'] ? e(truncate($b['lokasi'], 25)) : '<span class="text-muted">—</span>' ?></td>
                        <td style="text-align:center;">
                            <?php if ($b['jumlah_peserta'] > 0): ?>
                                <span class="badge badge-success"><?= $b['jumlah_peserta'] ?> siswa</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($b['jumlah_item'] > 0): ?>
                                <span class="badge badge-info"><?= $b['jumlah_item'] ?> item</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= statusBadge($b['status']) ?></td>
                        <td style="text-align:center;">
                            <div class="flex gap-2 justify-center">
                                <a href="<?= url('modules/binjas/detail.php?id=' . $b['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>
                                <a href="<?= url('modules/binjas/input_skor.php?sesi_id=' . $b['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Input Skor">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                    </svg>
                                </a>
                                <a href="<?= url('modules/binjas/edit.php?id=' . $b['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger-outline" title="Hapus"
                                        onclick="confirmDelete(<?= $b['id'] ?>, '<?= e(addslashes($b['nama_sesi'])) ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                        <path d="M10 11v6"/><path d="M14 11v6"/>
                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pag['total_pages'] > 1):
            $queryParams = array_filter([
                'search'      => $search,
                'angkatan_id' => $filterAngk ?: null,
                'status'      => $filterStatus,
            ]);
        ?>
        <?php require_once ROOT_PATH . '/views/partials/pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Form Delete -->
<form id="deleteForm" method="POST" action="<?= url('modules/binjas/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id, nama) {
    if (confirm('Hapus sesi "' + nama + '"?\n\nSeluruh skor dan presensi terkait akan ikut terhapus.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px 24px;
    box-shadow: 0 2px 8px rgba(18,18,18,.06);
    position: relative;
}
.stat-icon    { font-size: 22px; margin-bottom: 8px; }
.stat-card-num   { font-size: 28px; font-weight: 700; color: var(--text-primary); line-height: 1; }
.stat-card-label { font-size: 12px; color: var(--grey); margin-top: 6px; }
.stat-card-success .stat-card-num  { color: var(--army-green); }
.stat-card-warning .stat-card-num  { color: var(--warning); }
.stat-card-secondary .stat-card-num{ color: var(--grey); }

@media (max-width: 768px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>