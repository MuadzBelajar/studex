<?php
define('STUDEX', true);
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/google_drive.php';
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../core/Helpers.php';

requireAdmin();

$db    = db();
$opsId = sanitizeInt(get('ops_id'));

if (!$opsId) {
    setFlash('error', 'ID kegiatan tidak valid.');
    redirect(url('modules/operasional/index.php'));
}

$stmt = $db->prepare("SELECT o.*, a.nama AS nama_angkatan FROM operasional o LEFT JOIN angkatan a ON a.id = o.angkatan_id WHERE o.id = ?");
$stmt->execute([$opsId]);
$ops = $stmt->fetch();

if (!$ops) {
    setFlash('error', 'Kegiatan tidak ditemukan.');
    redirect(url('modules/operasional/index.php'));
}

// Handle POST: tambah / hapus peserta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = sanitize(post('action'));

    if ($action === 'tambah') {
        $siswaIds = post('siswa_ids', []);
        if (!is_array($siswaIds) || empty($siswaIds)) {
            setFlash('error', 'Pilih minimal satu siswa.');
        } else {
            $added = 0;
            $stmt  = $db->prepare("
                INSERT IGNORE INTO operasional_peserta (operasional_id, siswa_id, created_at)
                VALUES (?, ?, NOW())
            ");
            foreach ($siswaIds as $sid) {
                $sid = sanitizeInt($sid);
                if ($sid) {
                    $stmt->execute([$opsId, $sid]);
                    $added += $stmt->rowCount();
                }
            }
            setFlash('success', $added . ' peserta berhasil ditambahkan.');
        }
    }

    if ($action === 'hapus') {
        $siswaId = sanitizeInt(post('siswa_id'));
        if ($siswaId) {
            $db->prepare("DELETE FROM operasional_peserta WHERE operasional_id = ? AND siswa_id = ?")
               ->execute([$opsId, $siswaId]);
            setFlash('success', 'Peserta berhasil dihapus.');
        }
    }

    if ($action === 'tambah_semua_angkatan') {
        $angkatanId = sanitizeInt(post('angkatan_id'));
        if ($angkatanId) {
            $siswas = $db->prepare("SELECT id FROM siswa WHERE angkatan_id = ? AND status = 'aktif'");
            $siswas->execute([$angkatanId]);
            $siswas = $siswas->fetchAll();
            $stmt   = $db->prepare("INSERT IGNORE INTO operasional_peserta (operasional_id, siswa_id, created_at) VALUES (?, ?, NOW())");
            $added  = 0;
            foreach ($siswas as $s) {
                $stmt->execute([$opsId, $s['id']]);
                $added += $stmt->rowCount();
            }
            setFlash('success', $added . ' siswa dari angkatan berhasil ditambahkan.');
        }
    }

    redirect(url('modules/operasional/pra/peserta.php?ops_id=' . $opsId));
}

// Peserta yang sudah terdaftar
$pesertaTerdaftar = $db->prepare("
    SELECT s.id, s.nama, s.nomor_induk, a.nama AS nama_angkatan
    FROM operasional_peserta op
    JOIN siswa s ON s.id = op.siswa_id
    LEFT JOIN angkatan a ON a.id = s.angkatan_id
    WHERE op.operasional_id = ?
    ORDER BY s.nama
");
$pesertaTerdaftar->execute([$opsId]);
$pesertaTerdaftar = $pesertaTerdaftar->fetchAll();

$terdaftarIds = array_column($pesertaTerdaftar, 'id');

// Siswa yang belum terdaftar (untuk dropdown tambah)
$cariSiswa   = sanitize(get('cari_siswa', ''));
$filterAngk  = sanitizeInt(get('filter_angkatan', 0));

$whereAvail  = ['s.status = "aktif"'];
$paramsAvail = [];

if ($terdaftarIds) {
    $placeholders  = implode(',', array_fill(0, count($terdaftarIds), '?'));
    $whereAvail[]  = "s.id NOT IN ($placeholders)";
    $paramsAvail   = array_merge($paramsAvail, $terdaftarIds);
}
if ($cariSiswa) {
    $whereAvail[]  = '(s.nama LIKE ? OR s.nomor_induk LIKE ?)';
    $paramsAvail[] = "%$cariSiswa%";
    $paramsAvail[] = "%$cariSiswa%";
}
if ($filterAngk) {
    $whereAvail[]  = 's.angkatan_id = ?';
    $paramsAvail[] = $filterAngk;
}

$whereAvailStr = implode(' AND ', $whereAvail);
$siswaAvail    = $db->prepare("
    SELECT s.id, s.nama, s.nomor_induk, a.nama AS nama_angkatan
    FROM siswa s
    LEFT JOIN angkatan a ON a.id = s.angkatan_id
    WHERE $whereAvailStr
    ORDER BY s.nama
    LIMIT 100
");
$siswaAvail->execute($paramsAvail);
$siswaAvail = $siswaAvail->fetchAll();

// List angkatan untuk filter & tambah semua
$angkatanList = $db->query("SELECT id, nama FROM angkatan ORDER BY tahun DESC")->fetchAll();

$pageTitle    = 'Kelola Peserta';
$pageSubtitle = e($ops['nama_kegiatan']);
$activePage   = 'operasional';
$breadcrumbs  = [
    ['label' => 'Dashboard',        'url' => url('modules/dashboard/index.php')],
    ['label' => 'Operasional',      'url' => url('modules/operasional/index.php')],
    ['label' => e($ops['nama_kegiatan']), 'url' => url('modules/operasional/detail.php?id=' . $opsId)],
    ['label' => 'Pra-Operasional',  'url' => url('modules/operasional/pra/index.php?ops_id=' . $opsId)],
    ['label' => 'Kelola Peserta'],
];

ob_start();
?>

<div class="grid grid-2 gap-4" style="align-items:start;">

    <!-- ── Peserta Terdaftar ── -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Peserta Terdaftar</h3>
            <span class="badge badge-primary"><?= count($pesertaTerdaftar) ?> siswa</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pesertaTerdaftar)): ?>
                <div class="empty-state empty-state--sm">
                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                         style="color:var(--grey);margin-bottom:8px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="empty-desc">Belum ada peserta ditambahkan.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama</th>
                                <th>No. Induk</th>
                                <th>Angkatan</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesertaTerdaftar as $i => $p): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= e($p['nama']) ?></td>
                                    <td><?= e($p['nomor_induk']) ?></td>
                                    <td><?= e($p['nama_angkatan'] ?? '-') ?></td>
                                    <td>
                                        <form method="POST">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action"   value="hapus">
                                            <input type="hidden" name="siswa_id" value="<?= $p['id'] ?>">
                                            <button type="submit"
                                                    class="btn-icon btn-icon--delete"
                                                    onclick="return confirm('Hapus <?= e($p['nama']) ?> dari peserta?')"
                                                    title="Hapus">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <a href="<?= url('modules/operasional/pra/index.php?ops_id=' . $opsId) ?>"
               class="btn btn-secondary btn-sm">← Kembali ke Pra-Ops</a>
        </div>
    </div>

    <!-- ── Panel Tambah Peserta ── -->
    <div>

        <!-- Tambah semua dari angkatan -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Tambah Semua dari Angkatan</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="tambah_semua_angkatan">
                    <div class="form-row" style="align-items:flex-end;">
                        <div class="form-group" style="flex:1;">
                            <label class="form-label">Pilih Angkatan</label>
                            <select name="angkatan_id" class="form-control" required>
                                <option value="">— Pilih Angkatan —</option>
                                <?php foreach ($angkatanList as $a): ?>
                                    <option value="<?= $a['id'] ?>"><?= e($a['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:0 0 auto;">
                            <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Tambahkan semua siswa aktif dari angkatan ini?')">
                                + Tambah Semua
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cari & tambah individu -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Tambah Individu</h3>
            </div>
            <div class="card-body">
                <!-- Filter pencarian -->
                <form method="GET" class="mb-4">
                    <input type="hidden" name="ops_id" value="<?= $opsId ?>">
                    <div class="form-row" style="align-items:flex-end;">
                        <div class="form-group" style="flex:1;">
                            <input type="text" name="cari_siswa" class="form-control"
                                   placeholder="Cari nama atau NIS…"
                                   value="<?= e($cariSiswa) ?>">
                        </div>
                        <div class="form-group">
                            <select name="filter_angkatan" class="form-control">
                                <option value="">Semua Angkatan</option>
                                <?php foreach ($angkatanList as $a): ?>
                                    <option value="<?= $a['id'] ?>" <?= $filterAngk == $a['id'] ? 'selected' : '' ?>>
                                        <?= e($a['nama']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex:0 0 auto;">
                            <button type="submit" class="btn btn-secondary">Cari</button>
                        </div>
                    </div>
                </form>

                <?php if (empty($siswaAvail)): ?>
                    <div class="empty-state empty-state--sm">
                        <p class="empty-desc">
                            <?= $cariSiswa || $filterAngk
                                ? 'Tidak ada siswa yang cocok dengan filter.'
                                : 'Semua siswa aktif sudah terdaftar sebagai peserta.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="tambah">

                        <div class="table-responsive" style="max-height:340px;overflow-y:auto;">
                            <table class="table table-sm">
                                <thead style="position:sticky;top:0;background:#fff;">
                                    <tr>
                                        <th style="width:36px;">
                                            <input type="checkbox" id="checkAll"
                                                   onchange="document.querySelectorAll('.chk-siswa').forEach(c=>c.checked=this.checked)">
                                        </th>
                                        <th>Nama</th>
                                        <th>No. Induk</th>
                                        <th>Angkatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($siswaAvail as $s): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox"
                                                       class="chk-siswa"
                                                       name="siswa_ids[]"
                                                       value="<?= $s['id'] ?>">
                                            </td>
                                            <td><?= e($s['nama']) ?></td>
                                            <td><?= e($s['nomor_induk']) ?></td>
                                            <td><?= e($s['nama_angkatan'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                + Tambah yang Dipilih
                            </button>
                            <span class="text-muted ml-2" style="font-size:13px;">
                                Menampilkan <?= count($siswaAvail) ?> siswa
                            </span>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- end panel kanan -->
</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';