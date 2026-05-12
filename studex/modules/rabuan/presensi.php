<?php
// ============================================================
//  STUDEX — Student Index
//  modules/rabuan/presensi.php — Input Presensi Rabuan
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
$id   = sanitizeInt(get('id') ?: post('id'));

// Validasi rabuan
$stmt = $db->prepare("
    SELECT r.*, a.nama AS nama_angkatan, a.id AS angkatan_id_val
    FROM rabuan r
    JOIN angkatan a ON a.id = r.angkatan_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$rabuan = $stmt->fetch();

if (!$rabuan) {
    setFlash('error', 'Data rapat tidak ditemukan.');
    redirect(url('modules/rabuan/index.php'));
}

// Ambil semua siswa aktif dari angkatan ini
$siswas = $db->prepare("
    SELECT id, nis, nama, jenis_kelamin
    FROM siswa
    WHERE angkatan_id = ? AND status = 'aktif'
    ORDER BY nama ASC
");
$siswas->execute([$rabuan['angkatan_id']]);
$siswas = $siswas->fetchAll();

// Presensi yang sudah ada
$existingStmt = $db->prepare("
    SELECT siswa_id, status, keterangan
    FROM presensi
    WHERE referensi_id = ? AND modul = 'rabuan'
");
$existingStmt->execute([$id]);
$existing = [];
foreach ($existingStmt->fetchAll() as $p) {
    $existing[$p['siswa_id']] = $p;
}

// ============================================================
// PROSES SIMPAN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $presensiData   = post('presensi',   []);
    $keteranganData = post('keterangan', []);
    if (!is_array($presensiData))   $presensiData   = [];
    if (!is_array($keteranganData)) $keteranganData = [];

    $validStatus = ['hadir', 'izin', 'sakit', 'alpha'];

    $db->beginTransaction();
    try {
        // Hapus presensi lama lalu insert baru (upsert manual)
        $db->prepare("
            DELETE FROM presensi WHERE referensi_id = ? AND modul = 'rabuan'
        ")->execute([$id]);

        $insertStmt = $db->prepare("
            INSERT INTO presensi
                (modul, referensi_id, siswa_id, status, keterangan, dicatat_oleh)
            VALUES ('rabuan', ?, ?, ?, ?, ?)
        ");

        foreach ($siswas as $s) {
            $status = sanitize($presensiData[$s['id']] ?? 'alpha');
            if (!in_array($status, $validStatus)) $status = 'alpha';
            $ket = sanitize($keteranganData[$s['id']] ?? '');

            $insertStmt->execute([
                $id,
                $s['id'],
                $status,
                $ket ?: null,
                $user['id'],
            ]);
        }

        $db->commit();
        setFlash('success', 'Presensi berhasil disimpan untuk ' . count($siswas) . ' siswa.');
        redirect(url('modules/rabuan/detail.php?id=' . $id));

    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Gagal menyimpan presensi. Silakan coba lagi.');
        redirect(url('modules/rabuan/presensi.php?id=' . $id));
    }
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Presensi Rabuan';
$activePage  = 'rabuan';
$breadcrumbs = [
    ['label' => 'Dashboard',    'url' => url('modules/dashboard/index.php')],
    ['label' => 'Rapat Rabuan', 'url' => url('modules/rabuan/index.php')],
    ['label' => truncate($rabuan['judul'], 30), 'url' => url('modules/rabuan/detail.php?id=' . $id)],
    ['label' => 'Presensi'],
];

ob_start();
?>

<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="page-title">Input Presensi</h2>
        <p class="page-subtitle">
            <?= e($rabuan['judul']) ?> &bull;
            <?= e($rabuan['nama_angkatan']) ?> &bull;
            <?= formatTanggal($rabuan['tanggal']) ?>
        </p>
    </div>
    <div class="flex gap-2">
        <!-- Tandai semua hadir -->
        <button type="button" class="btn btn-outline" onclick="setAll('hadir')">✅ Semua Hadir</button>
        <button type="button" class="btn btn-outline" onclick="setAll('alpha')">❌ Reset Alpha</button>
    </div>
</div>

<?php if (empty($siswas)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon" style="font-size:40px;">👥</div>
            <div class="empty-state-title">Tidak Ada Siswa</div>
            <div class="empty-state-desc">
                Belum ada siswa aktif di angkatan <?= e($rabuan['nama_angkatan']) ?>.
                Tambahkan siswa terlebih dahulu.
            </div>
            <a href="<?= url('modules/siswa/create.php') ?>" class="btn btn-primary mt-4">
                Tambah Siswa
            </a>
        </div>
    </div>
<?php else: ?>

<form method="POST" action="?id=<?= $id ?>" id="presensiForm">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card">

        <!-- Summary bar -->
        <div class="presensi-summary" id="summary">
            <div class="summary-item summary-hadir">
                <span class="summary-num" id="cnt-hadir">0</span>
                <span class="summary-label">Hadir</span>
            </div>
            <div class="summary-item summary-izin">
                <span class="summary-num" id="cnt-izin">0</span>
                <span class="summary-label">Izin</span>
            </div>
            <div class="summary-item summary-sakit">
                <span class="summary-num" id="cnt-sakit">0</span>
                <span class="summary-label">Sakit</span>
            </div>
            <div class="summary-item summary-alpha">
                <span class="summary-num" id="cnt-alpha">0</span>
                <span class="summary-label">Alpha</span>
            </div>
            <div class="summary-item summary-total">
                <span class="summary-num"><?= count($siswas) ?></span>
                <span class="summary-label">Total</span>
            </div>
        </div>

        <!-- Tabel presensi -->
        <div class="table-responsive">
            <table class="table" id="presensiTable">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th style="text-align:center;width:340px;">Status Kehadiran</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswas as $i => $s):
                        $curStatus = $existing[$s['id']]['status']     ?? 'hadir';
                        $curKet    = $existing[$s['id']]['keterangan']  ?? '';
                    ?>
                    <tr class="presensi-row" data-status="<?= $curStatus ?>">
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="text-muted" style="font-size:12px;"><?= e($s['nis']) ?></td>
                        <td class="fw-medium"><?= e($s['nama']) ?></td>
                        <td>
                            <div class="status-group">
                                <?php foreach ([
                                    'hadir' => ['label' => 'Hadir',  'cls' => 'btn-status-hadir'],
                                    'izin'  => ['label' => 'Izin',   'cls' => 'btn-status-izin'],
                                    'sakit' => ['label' => 'Sakit',  'cls' => 'btn-status-sakit'],
                                    'alpha' => ['label' => 'Alpha',  'cls' => 'btn-status-alpha'],
                                ] as $val => $opt): ?>
                                    <label class="status-btn <?= $opt['cls'] ?> <?= $curStatus === $val ? 'active' : '' ?>">
                                        <input type="radio"
                                               name="presensi[<?= $s['id'] ?>]"
                                               value="<?= $val ?>"
                                               <?= $curStatus === $val ? 'checked' : '' ?>
                                               onchange="onStatusChange(this)">
                                        <?= $opt['label'] ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <input type="text"
                                   name="keterangan[<?= $s['id'] ?>]"
                                   class="form-control form-control-sm"
                                   value="<?= e($curKet) ?>"
                                   placeholder="Opsional...">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-between" style="padding:20px 24px;border-top:1px solid var(--border);">
            <a href="<?= url('modules/rabuan/detail.php?id=' . $id) ?>" class="btn btn-secondary">
                Batal
            </a>
            <button type="submit" class="btn btn-primary" id="saveBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Simpan Presensi
            </button>
        </div>
    </div>
</form>

<?php endif; ?>

<style>
/* Summary bar */
.presensi-summary {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--border);
}
.summary-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 12px;
    border-right: 1px solid var(--border);
}
.summary-item:last-child { border-right: none; }
.summary-num   { font-size: 24px; font-weight: 700; line-height: 1; }
.summary-label { font-size: 11px; color: var(--grey); margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }
.summary-hadir .summary-num { color: #395917; }
.summary-izin  .summary-num { color: #3b7a7e; }
.summary-sakit .summary-num { color: #C97C10; }
.summary-alpha .summary-num { color: #8B1408; }
.summary-total .summary-num { color: var(--text-primary); }

/* Status buttons */
.status-group {
    display: flex;
    gap: 6px;
    justify-content: center;
}
.status-btn {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--grey);
    transition: all 0.15s;
    user-select: none;
}
.status-btn input[type="radio"] { display: none; }
.status-btn:hover { border-color: var(--grey); }

.btn-status-hadir.active { background: #E6EFEA; border-color: #395917; color: #395917; }
.btn-status-izin.active  { background: #E8F4F5; border-color: #3b7a7e; color: #3b7a7e; }
.btn-status-sakit.active { background: #FEF3E2; border-color: #C97C10; color: #C97C10; }
.btn-status-alpha.active { background: #F9E8E7; border-color: #8B1408; color: #8B1408; }

/* Highlight row */
.presensi-row[data-status="alpha"] td:nth-child(3) { color: #8B1408; }
.presensi-row[data-status="hadir"] td:nth-child(3) { color: #395917; }

.form-control-sm { padding: 6px 10px; font-size: 13px; }
</style>

<script>
(function () {
    const counts = { hadir: 0, izin: 0, sakit: 0, alpha: 0 };

    // Hitung initial dari data yang sudah ada
    document.querySelectorAll('input[type="radio"]:checked').forEach(function (r) {
        if (counts[r.value] !== undefined) counts[r.value]++;
    });
    updateSummary();

    window.onStatusChange = function (radio) {
        const row     = radio.closest('tr');
        const oldStat = row.dataset.status;
        const newStat = radio.value;

        if (oldStat !== newStat) {
            if (counts[oldStat] !== undefined) counts[oldStat]--;
            if (counts[newStat] !== undefined) counts[newStat]++;
            row.dataset.status = newStat;
            updateSummary();
        }

        // Update active class pada tombol di baris ini
        row.querySelectorAll('.status-btn').forEach(function (btn) {
            btn.classList.remove('active');
        });
        radio.closest('.status-btn').classList.add('active');
    };

    window.setAll = function (status) {
        document.querySelectorAll('input[type="radio"][value="' + status + '"]').forEach(function (r) {
            r.checked = true;
            onStatusChange(r);
        });
    };

    function updateSummary() {
        document.getElementById('cnt-hadir').textContent = counts.hadir;
        document.getElementById('cnt-izin').textContent  = counts.izin;
        document.getElementById('cnt-sakit').textContent = counts.sakit;
        document.getElementById('cnt-alpha').textContent = counts.alpha;
    }

    // Disable tombol save saat submit
    document.getElementById('presensiForm').addEventListener('submit', function () {
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = 'Menyimpan...';
    });
})();
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>