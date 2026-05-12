<?php
// ============================================================
//  STUDEX — Student Index
//  modules/rabuan/index.php — Daftar Rapat Rabuan
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
    $where[]  = '(r.judul LIKE ? OR r.lokasi LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterAngk) {
    $where[]  = 'r.angkatan_id = ?';
    $params[] = $filterAngk;
}
if ($filterStatus) {
    $where[]  = 'r.status = ?';
    $params[] = $filterStatus;
}

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM rabuan r WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pag    = paginate($total);
$offset = $pag['offset'];
$limit  = $pag['per_page'];

$stmt = $db->prepare("
    SELECT r.*,
           a.nama AS nama_angkatan, a.kode AS kode_angkatan,
           u.nama AS nama_pembuat,
           (SELECT COUNT(*) FROM presensi p WHERE p.referensi_id = r.id AND p.modul = 'rabuan') AS jumlah_presensi,
           (SELECT COUNT(*) FROM rabuan_notulensi n WHERE n.rabuan_id = r.id) AS jumlah_notulensi
    FROM rabuan r
    JOIN angkatan a ON a.id = r.angkatan_id
    JOIN users    u ON u.id = r.created_by
    WHERE $whereStr
    ORDER BY r.tanggal DESC, r.id DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$rabuanList = $stmt->fetchAll();

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Rapat Rabuan';
$pageSubtitle = 'Manajemen Rapat Rutin Siswa';
$activePage   = 'rabuan';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Rapat Rabuan'],
];

ob_start();
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="page-title">Rapat Rabuan</h2>
        <p class="page-subtitle">Total <strong><?= $total ?></strong> rapat ditemukan</p>
    </div>
    <a href="<?= url('modules/rabuan/create.php') ?>" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Tambah Rapat
    </a>
</div>

<!-- Filter -->
<div class="card mb-5">
    <form method="GET" action="" class="flex flex-wrap items-end gap-4">
        <div class="form-group" style="flex:1;min-width:200px;">
            <label class="form-label">Cari</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Judul / Lokasi..." value="<?= e($search) ?>">
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
            <a href="<?= url('modules/rabuan/index.php') ?>" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Tabel -->
<div class="card">
    <?php if (empty($rabuanList)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div class="empty-state-title">Belum Ada Rapat Rabuan</div>
            <div class="empty-state-desc">
                <?= ($search || $filterAngk || $filterStatus)
                    ? 'Tidak ada rapat yang sesuai filter. Coba ubah kriteria pencarian.'
                    : 'Mulai dengan menambahkan rapat rabuan pertama.' ?>
            </div>
            <?php if (!$search && !$filterAngk && !$filterStatus): ?>
                <a href="<?= url('modules/rabuan/create.php') ?>" class="btn btn-primary mt-4">
                    Tambah Rapat Pertama
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Judul Rapat</th>
                        <th>Angkatan</th>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th style="text-align:center;">Presensi</th>
                        <th style="text-align:center;">Status</th>
                        <th style="width:130px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rabuanList as $i => $r): ?>
                    <tr>
                        <td class="text-muted"><?= $pag['offset'] + $i + 1 ?></td>
                        <td>
                            <div class="fw-medium"><?= e($r['judul']) ?></div>
                            <?php if ($r['waktu_mulai']): ?>
                                <small class="text-muted" style="font-size:11px;">
                                    <?= formatWaktu($r['waktu_mulai']) ?>
                                    <?= $r['waktu_selesai'] ? '– ' . formatWaktu($r['waktu_selesai']) : '' ?>
                                </small>
                            <?php endif; ?>
                            <?php if ($r['jumlah_notulensi'] > 0): ?>
                                <small class="text-success" style="font-size:11px; display:block;">
                                    📄 <?= $r['jumlah_notulensi'] ?> notulensi
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-secondary"><?= e($r['kode_angkatan']) ?></span></td>
                        <td><?= formatTanggal($r['tanggal']) ?></td>
                        <td><?= $r['lokasi'] ? e(truncate($r['lokasi'], 28)) : '<span class="text-muted">—</span>' ?></td>
                        <td style="text-align:center;">
                            <?php if ($r['jumlah_presensi'] > 0): ?>
                                <span class="badge badge-success"><?= $r['jumlah_presensi'] ?> siswa</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?= statusBadge($r['status']) ?></td>
                        <td style="text-align:center;">
                            <div class="flex gap-2 justify-center">
                                <a href="<?= url('modules/rabuan/detail.php?id=' . $r['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>
                                <a href="<?= url('modules/rabuan/edit.php?id=' . $r['id']) ?>"
                                   class="btn btn-sm btn-outline" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger-outline" title="Hapus"
                                        onclick="confirmDelete(<?= $r['id'] ?>, '<?= e(addslashes($r['judul'])) ?>')">
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

<!-- Form Delete Hidden -->
<form id="deleteForm" method="POST" action="<?= url('modules/rabuan/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function confirmDelete(id, judul) {
    if (confirm('Hapus rapat "' + judul + '"?\n\nSemua presensi dan notulensi terkait akan ikut terhapus.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>