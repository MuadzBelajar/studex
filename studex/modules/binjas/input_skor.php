<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireAdmin();

$db     = db();
$sesiId = sanitizeInt(get('sesi_id') ?: post('sesi_id'));

if (!$sesiId) {
    setFlash('error', 'ID sesi tidak valid.');
    redirect(url('modules/binjas/index.php'));
}

$stmt = $db->prepare("
    SELECT b.*, a.nama AS nama_angkatan
    FROM binjas_sesi b
    LEFT JOIN angkatan a ON a.id = b.angkatan_id
    WHERE b.id = ?
");
$stmt->execute([$sesiId]);
$sesi = $stmt->fetch();

if (!$sesi) {
    setFlash('error', 'Sesi tidak ditemukan.');
    redirect(url('modules/binjas/index.php'));
}

// Item binjas
$items = $db->query("SELECT * FROM binjas_item ORDER BY urutan, nama_item")->fetchAll();

if (empty($items)) {
    setFlash('error', 'Belum ada item Binjas. Tambahkan dulu di halaman Standarisasi.');
    redirect(url('modules/binjas/standarisasi.php'));
}

// Siswa dari angkatan sesi
$siswaList = $db->prepare("
    SELECT s.id, s.nama, s.nomor_induk
    FROM siswa s
    WHERE s.angkatan_id = ? AND s.status = 'aktif'
    ORDER BY s.nama
");
$siswaList->execute([$sesi['angkatan_id']]);
$siswaList = $siswaList->fetchAll();

// Skor yang sudah ada
$existingSkor = $db->prepare("
    SELECT siswa_id, item_id, nilai
    FROM binjas_skor
    WHERE sesi_id = ?
");
$existingSkor->execute([$sesiId]);
$skorMap = []; // [siswa_id][item_id] = nilai
foreach ($existingSkor->fetchAll() as $row) {
    $skorMap[$row['siswa_id']][$row['item_id']] = $row['nilai'];
}

// Handle POST — simpan skor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $skorInput = post('skor', []); // skor[siswa_id][item_id] = nilai

    if (!is_array($skorInput)) {
        setFlash('error', 'Format data tidak valid.');
        redirect(url('modules/binjas/input_skor.php?sesi_id=' . $sesiId));
    }

    $stmtUpsert = $db->prepare("
        INSERT INTO binjas_skor (sesi_id, siswa_id, item_id, nilai, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), updated_at = NOW()
    ");

    $saved = 0;
    foreach ($skorInput as $siswaId => $itemSkor) {
        $siswaId = sanitizeInt($siswaId);
        if (!$siswaId || !is_array($itemSkor)) continue;

        foreach ($itemSkor as $itemId => $nilai) {
            $itemId = sanitizeInt($itemId);
            $nilai  = trim($nilai);

            if ($itemId && $nilai !== '') {
                $nilai = (float)$nilai;
                if ($nilai < 0) $nilai = 0;
                $stmtUpsert->execute([$sesiId, $siswaId, $itemId, $nilai]);
                $saved++;
            }
        }
    }

    setFlash('success', "$saved skor berhasil disimpan.");
    redirect(url('modules/binjas/detail.php?id=' . $sesiId));
}

$pageTitle    = 'Input Skor Binjas';
$pageSubtitle = e($sesi['nama_sesi']);
$activePage   = 'binjas';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Binjas',    'url' => url('modules/binjas/index.php')],
    ['label' => e($sesi['nama_sesi']), 'url' => url('modules/binjas/detail.php?id=' . $sesiId)],
    ['label' => 'Input Skor'],
];

ob_start();
?>

<style>
.skor-table th, .skor-table td { white-space: nowrap; }
.skor-table input[type="number"] {
    width: 72px; text-align: center;
    padding: 4px 6px; font-size: 13px;
    border: 1px solid var(--border);
    border-radius: 6px; background: #fff;
    transition: border-color .15s;
}
.skor-table input[type="number"]:focus {
    outline: none; border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(57,89,23,.12);
}
.skor-table input[type="number"].has-value { background: var(--primary-light); }
.sticky-col { position: sticky; left: 0; background: #fff; z-index: 2; }
.sticky-col-2 { position: sticky; left: 120px; background: #fff; z-index: 2; }
</style>

<!-- Info sesi -->
<div class="card mb-4">
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div>
                <h2 style="font-size:18px;font-weight:700;margin-bottom:4px;"><?= e($sesi['nama_sesi']) ?></h2>
                <div style="font-size:13px;color:var(--grey);display:flex;gap:16px;flex-wrap:wrap;">
                    <span><strong>Angkatan:</strong> <?= e($sesi['nama_angkatan'] ?? '-') ?></span>
                    <span><strong>Tanggal:</strong> <?= formatTanggal($sesi['tanggal']) ?></span>
                    <span><strong>Siswa:</strong> <?= count($siswaList) ?> orang</span>
                    <span><strong>Item:</strong> <?= count($items) ?> item</span>
                </div>
            </div>
            <a href="<?= url('modules/binjas/detail.php?id=' . $sesiId) ?>"
               class="btn btn-secondary btn-sm">← Kembali ke Detail</a>
        </div>
    </div>
</div>

<?php if (empty($siswaList)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <p class="empty-title">Tidak ada siswa aktif</p>
                <p class="empty-desc">Belum ada siswa aktif pada angkatan ini.</p>
            </div>
        </div>
    </div>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Form Input Skor</h3>
        <div class="card-actions">
            <button type="button" class="btn btn-secondary btn-sm" onclick="isiSemua()">
                Isi Semua Kolom Kosong dengan 0
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <form method="POST" id="formSkor">
            <?= csrfField() ?>
            <input type="hidden" name="sesi_id" value="<?= $sesiId ?>">

            <div class="table-responsive">
                <table class="table skor-table">
                    <thead>
                        <tr>
                            <th class="sticky-col" style="min-width:32px;">#</th>
                            <th class="sticky-col-2" style="min-width:160px;">Nama Siswa</th>
                            <?php foreach ($items as $item): ?>
                                <th style="text-align:center;">
                                    <?= e($item['nama_item']) ?>
                                    <span style="font-size:10px;color:var(--grey);display:block;font-weight:400;">
                                        <?= e($item['satuan']) ?>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($siswaList as $i => $siswa): ?>
                            <tr>
                                <td class="sticky-col"><?= $i + 1 ?></td>
                                <td class="sticky-col-2">
                                    <div style="font-weight:600;font-size:13px;"><?= e($siswa['nama']) ?></div>
                                    <div style="font-size:11px;color:var(--grey);"><?= e($siswa['nomor_induk']) ?></div>
                                </td>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $existingVal = $skorMap[$siswa['id']][$item['id']] ?? '';
                                    $hasVal      = $existingVal !== '';
                                    ?>
                                    <td style="text-align:center;">
                                        <input type="number"
                                               name="skor[<?= $siswa['id'] ?>][<?= $item['id'] ?>]"
                                               value="<?= $hasVal ? e($existingVal) : '' ?>"
                                               min="0" step="0.01"
                                               placeholder="—"
                                               class="<?= $hasVal ? 'has-value' : '' ?>"
                                               onchange="this.classList.toggle('has-value', this.value !== '')">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-4" style="display:flex;justify-content:flex-end;gap:12px;border-top:1px solid var(--border);">
                <a href="<?= url('modules/binjas/detail.php?id=' . $sesiId) ?>"
                   class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Semua Skor
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function isiSemua() {
    if (!confirm('Isi semua kolom yang masih kosong dengan nilai 0?')) return;
    document.querySelectorAll('.skor-table input[type="number"]').forEach(function(inp) {
        if (inp.value === '') {
            inp.value = '0';
            inp.classList.add('has-value');
        }
    });
}

// Navigasi keyboard antar sel (Tab = kanan, Enter = bawah)
document.querySelectorAll('.skor-table input[type="number"]').forEach(function(inp, idx, all) {
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const cols = <?= count($items) ?>;
            const nextIdx = idx + cols;
            if (nextIdx < all.length) all[nextIdx].focus();
        }
    });
});
</script>

<?php endif; ?>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';