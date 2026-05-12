<?php
// ============================================================
//  STUDEX — Student Index
//  modules/users/index.php — Manajemen Pengguna (Super Admin)
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireSuperAdmin();

$db   = db();
$user = currentUser();

// ============================================================
// FILTER & SEARCH
// ============================================================
$search      = sanitize(get('search'));
$filterRole  = sanitize(get('role'));
$filterAktif = sanitize(get('is_active'));

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(u.nama LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterRole) {
    $where[]  = 'u.role = ?';
    $params[] = $filterRole;
}
if ($filterAktif !== '') {
    $where[]  = 'u.is_active = ?';
    $params[] = (int)$filterAktif;
}

$whereStr = implode(' AND ', $where);

// Hitung total
$countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Pagination
$pag    = paginate($total);
$offset = $pag['offset'];
$limit  = $pag['per_page'];

// Fetch data
$stmt = $db->prepare("
    SELECT u.*,
           c.nama AS nama_pembuat
    FROM users u
    LEFT JOIN users c ON c.id = u.created_by
    WHERE $whereStr
    ORDER BY u.role ASC, u.nama ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$userList = $stmt->fetchAll();

// Statistik
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) AS super_admin,
        SUM(CASE WHEN role = 'admin'       THEN 1 ELSE 0 END) AS admin,
        SUM(CASE WHEN is_active = 1        THEN 1 ELSE 0 END) AS aktif,
        SUM(CASE WHEN is_active = 0        THEN 1 ELSE 0 END) AS nonaktif
    FROM users
")->fetch();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Manajemen Pengguna';
$pageSubtitle = 'Kelola akun admin sistem';
$activePage   = 'users';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Pengguna'],
];

ob_start();
?>

<!-- Stat Cards -->
<div class="stats-row mb-6">
    <div class="stat-card">
        <div class="stat-card-num"><?= $stats['total'] ?></div>
        <div class="stat-card-label">Total Pengguna</div>
    </div>
    <div class="stat-card stat-card-primary">
        <div class="stat-card-num"><?= $stats['super_admin'] ?></div>
        <div class="stat-card-label">Super Admin</div>
    </div>
    <div class="stat-card stat-card-info">
        <div class="stat-card-num"><?= $stats['admin'] ?></div>
        <div class="stat-card-label">Admin</div>
    </div>
    <div class="stat-card stat-card-success">
        <div class="stat-card-num"><?= $stats['aktif'] ?></div>
        <div class="stat-card-label">Aktif</div>
    </div>
    <div class="stat-card stat-card-warning">
        <div class="stat-card-num"><?= $stats['nonaktif'] ?></div>
        <div class="stat-card-label">Nonaktif</div>
    </div>
</div>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="page-title">Manajemen Pengguna</h2>
        <p class="page-subtitle">Total <strong><?= $total ?></strong> pengguna ditemukan</p>
    </div>
    <a href="<?= url('modules/users/create.php') ?>" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
            <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
        </svg>
        Tambah Pengguna
    </a>
</div>

<!-- Filter -->
<div class="card mb-5">
    <form method="GET" action="" class="flex flex-wrap items-end gap-4">
        <div class="form-group" style="flex:1;min-width:200px;">
            <label class="form-label">Cari</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Nama / Username / Email..."
                   value="<?= e($search) ?>">
        </div>
        <div class="form-group" style="min-width:160px;">
            <label class="form-label">Role</label>
            <select name="role" class="form-control">
                <option value="">Semua Role</option>
                <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                <option value="admin"       <?= $filterRole === 'admin'       ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="form-group" style="min-width:140px;">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-control">
                <option value="">Semua Status</option>
                <option value="1" <?= $filterAktif === '1' ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= $filterAktif === '0' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="flex gap-2" style="padding-bottom:1px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= url('modules/users/index.php') ?>" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel -->
<div class="card">
    <?php if (empty($userList)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="empty-state-title">Tidak Ada Pengguna</div>
            <div class="empty-state-desc">
                <?= ($search || $filterRole || $filterAktif !== '')
                    ? 'Tidak ada pengguna yang sesuai filter.'
                    : 'Belum ada pengguna terdaftar.' ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Pengguna</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th style="text-align:center;">Role</th>
                        <th style="text-align:center;">Status</th>
                        <th>Login Terakhir</th>
                        <th style="width:120px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userList as $i => $u): ?>
                    <tr class="<?= !$u['is_active'] ? 'row-nonaktif' : '' ?>">
                        <td class="text-muted"><?= $pag['offset'] + $i + 1 ?></td>
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="avatar-circle avatar-<?= strtolower($u['role']) ?>">
                                    <?= mb_substr($u['nama'], 0, 1) ?>
                                </div>
                                <div>
                                    <div class="fw-medium">
                                        <?= e($u['nama']) ?>
                                        <?php if ($u['id'] == $user['id']): ?>
                                            <span class="badge badge-success" style="font-size:10px;margin-left:4px;">Kamu</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($u['nama_pembuat']): ?>
                                        <div class="text-muted" style="font-size:11px;">
                                            Dibuat oleh <?= e($u['nama_pembuat']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="username-badge">@<?= e($u['username']) ?></span>
                        </td>
                        <td class="text-muted" style="font-size:13px;"><?= e($u['email']) ?></td>
                        <td style="text-align:center;">
                            <?php if ($u['role'] === 'super_admin'): ?>
                                <span class="badge badge-primary">Super Admin</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Admin</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($u['is_active']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="font-size:12px;">
                            <?= $u['last_login'] ? timeAgo($u['last_login']) : '<span class="text-muted">Belum pernah</span>' ?>
                        </td>
                        <td style="text-align:center;">
                            <div class="flex gap-2 justify-center">
                                <a href="<?= url('modules/users/edit.php?id=' . $u['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>
                                <?php if ($u['id'] != $user['id']): ?>
                                <button type="button" class="btn btn-sm btn-danger-outline" title="Hapus"
                                        onclick="confirmDelete(<?= $u['id'] ?>, '<?= e(addslashes($u['nama'])) ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                        <path d="M10 11v6"/><path d="M14 11v6"/>
                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                    </svg>
                                </button>
                                <?php else: ?>
                                <span class="btn btn-sm btn-outline" style="opacity:.3;cursor:not-allowed;" title="Tidak bisa hapus akun sendiri">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pag['total_pages'] > 1):
            $queryParams = array_filter([
                'search'    => $search,
                'role'      => $filterRole,
                'is_active' => $filterAktif,
            ]);
        ?>
        <?php require_once ROOT_PATH . '/views/partials/pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Form Delete -->
<form id="deleteForm" method="POST" action="<?= url('modules/users/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id, nama) {
    if (confirm('Hapus pengguna "' + nama + '"?\n\nAksi ini tidak dapat dibatalkan.')) {
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
.stat-card-primary .stat-card-num { color: var(--army-green); }
.stat-card-info    .stat-card-num { color: #3b7a7e; }
.stat-card-success .stat-card-num { color: #395917; }
.stat-card-warning .stat-card-num { color: var(--warning); }

.avatar-circle {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 700;
    flex-shrink: 0; text-transform: uppercase;
}
.avatar-super_admin { background: #E6EFEA; color: #395917; }
.avatar-admin       { background: #E8F4F5; color: #3b7a7e; }

.username-badge {
    font-family: monospace;
    font-size: 12px;
    background: var(--app-bg);
    padding: 3px 8px;
    border-radius: 6px;
    color: var(--grey);
}
.badge-primary { background: #E6EFEA; color: #395917; }
.badge-danger  { background: #F9E8E7; color: #8B1408; }

.row-nonaktif { opacity: 0.6; }

@media (max-width: 768px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>