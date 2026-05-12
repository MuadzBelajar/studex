<?php
// ============================================================
//  STUDEX — Student Index
//  modules/mentoring/index.php — Daftar Sesi Mentoring
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
    $where[]  = '(m.judul LIKE ? OR m.lokasi LIKE ? OR m.mentor LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterAngk) {
    $where[]  = 'm.angkatan_id = ?';
    $params[] = $filterAngk;
}
if ($filterStatus) {
    $where[]  = 'm.status = ?';
    $params[] = $filterStatus;
}

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM mentoring_sesi m WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pag    = paginate($total);
$offset = $pag['offset'];
$limit  = $pag['per_page'];

$stmt = $db->prepare("
    SELECT m.*,
           a.nama AS nama_angkatan, a.kode AS kode_angkatan,
           u.nama AS nama_pembuat,
           (SELECT COUNT(*) FROM presensi p
            WHERE p.referensi_id = m.id AND p.modul = 'mentoring') AS jumlah_presensi,
           (SELECT COUNT(*) FROM mentoring_materi mm
            WHERE mm.sesi_id = m.id) AS jumlah_materi
    FROM mentoring_sesi m
    JOIN angkatan a ON a.id = m.angkatan_id
    JOIN users    u ON u.id = m.created_by
    WHERE $whereStr
    ORDER BY m.tanggal DESC, m.id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$sesiList = $stmt->fetchAll();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Mentoring';
$pageSubtitle = 'Manajemen Sesi Mentoring Siswa';
$activePage   = 'mentoring';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Mentoring'],
];

ob_start();
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="page-title">Sesi Mentoring</h2>
        <p class="page-subtitle">Total <strong><?= $total ?></strong> sesi ditemukan</p>
    </div>
    <a href="<?= url('modules/mentoring/create.php') ?>" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Tambah Sesi
    </a>
</div>

<!-- Filter -->
<div class="card mb-5">
    <form method="GET" action="" class="flex flex-wrap items-end gap-4">
        <div class="form-group" style="flex:1;min-width:200px;">
            <label class="form-label">Cari</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Judul / Mentor / Lokasi..." value="<?= e($search) ?>">
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
        <div class="form-group" style="min-width:160px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Semua Status</option>
                <option value="terjadwal"   <?= $filterStatus === 'terjadwal'   ? 'selected' : '' ?>>Terjadwal</option>
                <option value="berlangsung" <?= $filterStatus === 'berlangsung' ? 'selected' : '' ?>>Berlangsung</option>
                <option value="selesai"     <?= $filterStatus === 'selesai'     ? 'selected' : '' ?>>Selesai</option>
                <option value="dibatalkan"  <?= $filterStatus === 'dibatalkan'  ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>
        <div class="flex gap-2" style="padding-bottom:1px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= url('modules/mentoring/index.php') ?>" class="btn btn-secondary">Reset</a>
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
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="empty-state-title">Belum Ada Sesi Mentoring</div>
            <div class="empty-state-desc">
                <?= ($search || $filterAngk || $filterStatus)
                    ? 'Tidak ada sesi yang sesuai filter. Coba ubah kriteria pencarian.'
                    : 'Mulai dengan menambahkan sesi mentoring pertama.' ?>
            </div>
            <?php if (!$search && !$filterAngk && !$filterStatus): ?>
                <a href="<?= url('modules/mentoring/create.php') ?>" class="btn btn-primary mt-4">
                    Tambah Sesi Pertama
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Judul Sesi</th>
                        <th>Angkatan</th>
                        <th>Tanggal</th>
                        <th>Mentor</th>
                        <th style="text-align:center;">Presensi</th>
                        <th style="text-align:center;">Materi</th>
                        <th style="text-align:center;">Status</th>
                        <th style="width:130px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sesiList as $i => $m): ?>
                    <tr>
                        <td class="text-muted"><?= $pag['offset'] + $i + 1 ?></td>
                        <td>
                            <div class="fw-medium"><?= e($m['judul']) ?></div>
                            <?php if ($m['waktu_mulai']): ?>
                                <small class="text-muted" style="font-size:11px;">
                                    <?= formatWaktu($m['waktu_mulai']) ?>
                                    <?= $m['waktu_selesai'] ? '– ' . formatWaktu($m['waktu_selesai']) : '' ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-secondary"><?= e($m['kode_angkatan']) ?></span></td>
                        <td><?= formatTanggal($m['tanggal']) ?></td>
                        <td><?= $m['mentor'] ? e(truncate($m['mentor'], 22)) : '<span class="text-muted">—</span>' ?></td>
                        <td style="text-align:center;">
                            <?php if ($m['jumlah_presensi'] > 0): ?>
                                <span class="badge badge-success"><?= $m['jumlah_presensi'] ?> siswa</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($m['jumlah_materi'] > 0): ?>
                                <span class="badge badge-info">📎 <?= $m['jumlah_materi'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= statusBadge($m['status']) ?></td>
                        <td style="text-align:center;">
                            <div class="flex gap-2 justify-center">
                                <a href="<?= url('modules/mentoring/detail.php?id=' . $m['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>
                                <a href="<?= url('modules/mentoring/edit.php?id=' . $m['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger-outline" title="Hapus"
                                        onclick="confirmDelete(<?= $m['id'] ?>, '<?= e(addslashes($m['judul'])) ?>')">
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
        <?php require_once ROOT_PATH . '/view/partials/pagination.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Form Delete -->
<form id="deleteForm" method="POST" action="<?= url('modules/mentoring/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id, judul) {
    if (confirm('Hapus sesi "' + judul + '"?\n\nSemua presensi dan materi terkait akan ikut terhapus.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>