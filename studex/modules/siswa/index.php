<?php
// ============================================================
//  STUDEX — Student Index
//  modules/siswa/index.php — Daftar Data Siswa
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
$filterJk     = sanitize(get('jenis_kelamin'));

$angkatanList = $db->query("SELECT id, nama, kode FROM angkatan ORDER BY tahun DESC")->fetchAll();

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(s.nama LIKE ? OR s.nis LIKE ? OR s.email LIKE ? OR s.no_hp LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterAngk) {
    $where[]  = 's.angkatan_id = ?';
    $params[] = $filterAngk;
}
if ($filterStatus) {
    $where[]  = 's.status = ?';
    $params[] = $filterStatus;
}
if ($filterJk) {
    $where[]  = 's.jenis_kelamin = ?';
    $params[] = $filterJk;
}

$whereStr = implode(' AND ', $where);

// Hitung total
$countStmt = $db->prepare("SELECT COUNT(*) FROM siswa s WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Pagination
$pag    = paginate($total);
$offset = $pag['offset'];
// tampilkan semua data tanpa pagination (supaya tidak hanya 15 baris)
$limit  = max($total, 1);


// Fetch data
$stmt = $db->prepare("
    SELECT s.*, a.nama AS nama_angkatan, a.kode AS kode_angkatan
    FROM siswa s
    LEFT JOIN angkatan a ON a.id = s.angkatan_id
    WHERE $whereStr
    ORDER BY a.tahun DESC, s.nama ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

// Statistik ringkas
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'aktif'       THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN status = 'tidak_aktif' THEN 1 ELSE 0 END) AS tidak_aktif,
        SUM(CASE WHEN status = 'alumni'      THEN 1 ELSE 0 END) AS alumni,
        SUM(CASE WHEN jenis_kelamin = 'L'    THEN 1 ELSE 0 END) AS laki,
        SUM(CASE WHEN jenis_kelamin = 'P'    THEN 1 ELSE 0 END) AS perempuan
    FROM siswa
")->fetch();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Data Siswa';
$pageSubtitle = 'Manajemen Data Seluruh Siswa';
$activePage   = 'siswa';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Data Siswa'],
];

ob_start();
?>

<!-- Stat Cards -->
<div class="stats-row mb-6">
    <div class="stat-card">
        <div class="stat-card-num"><?= $stats['total'] ?></div>
        <div class="stat-card-label">Total Siswa</div>
    </div>
    <div class="stat-card stat-card-success">
        <div class="stat-card-num"><?= $stats['aktif'] ?></div>
        <div class="stat-card-label">Aktif</div>
    </div>
    <div class="stat-card stat-card-warning">
        <div class="stat-card-num"><?= $stats['tidak_aktif'] ?></div>
        <div class="stat-card-label">Tidak Aktif</div>
    </div>
    <div class="stat-card stat-card-secondary">
        <div class="stat-card-num"><?= $stats['alumni'] ?></div>
        <div class="stat-card-label">Alumni</div>
    </div>
    <div class="stat-card stat-card-info">
        <div class="stat-card-num"><?= $stats['laki'] ?> / <?= $stats['perempuan'] ?></div>
        <div class="stat-card-label">L / P</div>
    </div>
</div>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="page-title">Data Siswa</h2>
        <p class="page-subtitle">Menampilkan <strong><?= $total ?></strong> siswa</p>
    </div>
    <div class="flex gap-2">
        <a href="<?= url('modules/siswa/export.php?' . http_build_query(array_filter([
            'search'       => $search,
            'angkatan_id'  => $filterAngk ?: null,
            'status'       => $filterStatus,
            'jenis_kelamin'=> $filterJk,
        ]))) ?>" class="btn btn-outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export
        </a>
        <a href="<?= url('modules/siswa/import.php') ?>" class="btn btn-outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            Import
        </a>
        <a href="<?= url('modules/siswa/create.php') ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Siswa
        </a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-5">
    <form method="GET" action="" class="flex flex-wrap items-end gap-4">
        <div class="form-group" style="flex:1;min-width:200px;">
            <label class="form-label">Cari</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Nama / NIS / Email / No HP..."
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
        <div class="form-group" style="min-width:140px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua Status</option>
                <option value="aktif"       <?= $filterStatus === 'aktif'       ? 'selected' : '' ?>>Aktif</option>
                <option value="tidak_aktif" <?= $filterStatus === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                <option value="alumni"      <?= $filterStatus === 'alumni'      ? 'selected' : '' ?>>Alumni</option>
            </select>
        </div>
        <div class="form-group" style="min-width:130px;">
            <label class="form-label">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-control">
                <option value="">Semua</option>
                <option value="L" <?= $filterJk === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= $filterJk === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
        </div>
        <div class="flex gap-2" style="padding-bottom:1px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= url('modules/siswa/index.php') ?>" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel -->
<div class="card">
    <?php if (empty($siswaList)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="empty-state-title">Belum Ada Data Siswa</div>
            <div class="empty-state-desc">
                <?= ($search || $filterAngk || $filterStatus || $filterJk)
                    ? 'Tidak ada siswa yang sesuai filter. Coba ubah kriteria pencarian.'
                    : 'Mulai dengan menambahkan data siswa pertama.' ?>
            </div>
            <?php if (!$search && !$filterAngk && !$filterStatus && !$filterJk): ?>
                <a href="<?= url('modules/siswa/create.php') ?>" class="btn btn-primary mt-4">
                    Tambah Siswa Pertama
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Angkatan</th>
                        <th style="text-align:center;">JK</th>
                        <th>Kontak</th>
                        <th style="text-align:center;">Status</th>
                        <th style="width:120px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswaList as $i => $s): ?>
                    <tr>
                        <td class="text-muted"><?= $pag['offset'] + $i + 1 ?></td>
                        <td>
                            <span class="nis-badge"><?= e($s['nis']) ?></span>
                        </td>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="avatar-circle <?= $s['jenis_kelamin'] === 'P' ? 'avatar-p' : 'avatar-l' ?>">
                                    <?= mb_substr($s['nama'], 0, 1) ?>
                                </div>
                                <div>
                                    <div class="fw-medium"><?= e($s['nama']) ?></div>
                                    <?php if ($s['tempat_lahir'] && $s['tanggal_lahir']): ?>
                                        <div class="text-muted" style="font-size:11px;">
                                            <?= e($s['tempat_lahir']) ?>, <?= formatTanggalPendek($s['tanggal_lahir']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($s['nama_angkatan']): ?>
                                <span class="badge badge-secondary"><?= e($s['kode_angkatan']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge <?= $s['jenis_kelamin'] === 'L' ? 'badge-info' : 'badge-purple' ?>">
                                <?= $s['jenis_kelamin'] === 'L' ? 'L' : 'P' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($s['no_hp']): ?>
                                <div style="font-size:13px;"><?= e($s['no_hp']) ?></div>
                            <?php endif; ?>
                            <?php if ($s['email']): ?>
                                <div class="text-muted" style="font-size:11px;"><?= e(truncate($s['email'], 28)) ?></div>
                            <?php endif; ?>
                            <?php if (!$s['no_hp'] && !$s['email']): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= statusBadge($s['status']) ?></td>
                        <td style="text-align:center;">
                            <div class="flex gap-2 justify-center">
                                <a href="<?= url('modules/siswa/detail.php?id=' . $s['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>
                                <a href="<?= url('modules/siswa/edit.php?id=' . $s['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger-outline" title="Hapus"
                                        onclick="confirmDelete(<?= $s['id'] ?>, '<?= e(addslashes($s['nama'])) ?>')">
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
                'search'        => $search,
                'angkatan_id'   => $filterAngk   ?: null,
                'status'        => $filterStatus,
                'jenis_kelamin' => $filterJk,
            ]);
        ?>
        <?php require_once ROOT_PATH . '/view/partials/pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Form Delete -->
<form id="deleteForm" method="POST" action="<?= url('modules/siswa/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id, nama) {
    if (confirm('Hapus siswa "' + nama + '"?\n\nSeluruh data presensi dan aktivitas terkait akan ikut terhapus.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
}
.stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px 24px;
    box-shadow: 0 2px 8px rgba(18,18,18,.06);
}
.stat-card-num   { font-size: 28px; font-weight: 700; color: var(--text-primary); line-height: 1; }
.stat-card-label { font-size: 12px; color: var(--grey); margin-top: 6px; }
.stat-card-success .stat-card-num { color: var(--army-green); }
.stat-card-warning .stat-card-num { color: var(--warning); }
.stat-card-secondary .stat-card-num { color: var(--grey); }
.stat-card-info .stat-card-num { color: #3b7a7e; }

.nis-badge {
    font-family: monospace;
    font-size: 12px;
    background: var(--app-bg);
    padding: 3px 8px;
    border-radius: 6px;
    color: var(--grey);
}
.avatar-circle {
    width: 34px; height: 34px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
    flex-shrink: 0;
    text-transform: uppercase;
}
.avatar-l { background: #E8F4F5; color: #3b7a7e; }
.avatar-p { background: #F3E8F9; color: #7b3b9e; }
.badge-purple { background: #F3E8F9; color: #7b3b9e; }

@media (max-width: 768px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>