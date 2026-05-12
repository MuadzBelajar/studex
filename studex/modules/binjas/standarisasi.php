<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireSuperAdmin();

$db = db();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = sanitize(post('action'));

    // ── Tambah item baru ─────────────────────────────────────────────────
    if ($action === 'tambah_item') {
        $namaItem = sanitize(post('nama_item'));
        $satuan   = sanitize(post('satuan'));
        $urutan   = sanitizeInt(post('urutan', 0));

        if (!$namaItem || !$satuan) {
            setFlash('error', 'Nama item dan satuan wajib diisi.');
        } else {
            // Cek duplikat
            $cek = $db->prepare("SELECT id FROM binjas_item WHERE nama_item = ?");
            $cek->execute([$namaItem]);
            if ($cek->fetch()) {
                setFlash('error', 'Item dengan nama tersebut sudah ada.');
            } else {
                $db->prepare("
                    INSERT INTO binjas_item (nama_item, satuan, urutan, created_at)
                    VALUES (?, ?, ?, NOW())
                ")->execute([$namaItem, $satuan, $urutan]);
                setFlash('success', 'Item "' . $namaItem . '" berhasil ditambahkan.');
            }
        }
        redirect(url('modules/binjas/standarisasi.php'));
    }

    // ── Hapus item ───────────────────────────────────────────────────────
    if ($action === 'hapus_item') {
        $itemId = sanitizeInt(post('item_id'));
        if (!$itemId) {
            setFlash('error', 'ID item tidak valid.');
        } else {
            // Cek apakah ada skor yang pakai item ini
            $cek = $db->prepare("SELECT COUNT(*) FROM binjas_skor WHERE item_id = ?");
            $cek->execute([$itemId]);
            if ($cek->fetchColumn() > 0) {
                setFlash('error', 'Item tidak dapat dihapus karena sudah memiliki data skor.');
            } else {
                $db->prepare("DELETE FROM binjas_standarisasi WHERE item_id = ?")->execute([$itemId]);
                $db->prepare("DELETE FROM binjas_item WHERE id = ?")->execute([$itemId]);
                setFlash('success', 'Item berhasil dihapus.');
            }
        }
        redirect(url('modules/binjas/standarisasi.php'));
    }

    // ── Simpan nilai standar ─────────────────────────────────────────────
    if ($action === 'simpan_standar') {
        $angkatanId  = sanitizeInt(post('angkatan_id')) ?: null; // null = global
        $standarInput = post('standar', []); // standar[item_id] = nilai

        if (!is_array($standarInput)) {
            setFlash('error', 'Data standar tidak valid.');
            redirect(url('modules/binjas/standarisasi.php'));
        }

        $stmtUpsert = $db->prepare("
            INSERT INTO binjas_standarisasi (item_id, angkatan_id, nilai_standar, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE nilai_standar = VALUES(nilai_standar), updated_at = NOW()
        ");

        $saved = 0;
        foreach ($standarInput as $itemId => $nilai) {
            $itemId = sanitizeInt($itemId);
            $nilai  = trim($nilai);
            if ($itemId && $nilai !== '') {
                $nilai = (float)$nilai;
                if ($nilai < 0) $nilai = 0;
                $stmtUpsert->execute([$itemId, $angkatanId, $nilai]);
                $saved++;
            }
        }

        $scope = $angkatanId ? 'per angkatan' : 'global';
        setFlash('success', "$saved nilai standar ($scope) berhasil disimpan.");
        redirect(url('modules/binjas/standarisasi.php'));
    }

    // ── Edit urutan item ─────────────────────────────────────────────────
    if ($action === 'edit_item') {
        $itemId   = sanitizeInt(post('item_id'));
        $namaItem = sanitize(post('nama_item'));
        $satuan   = sanitize(post('satuan'));
        $urutan   = sanitizeInt(post('urutan', 0));

        if (!$itemId || !$namaItem || !$satuan) {
            setFlash('error', 'Data tidak lengkap.');
        } else {
            $db->prepare("
                UPDATE binjas_item
                SET nama_item = ?, satuan = ?, urutan = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([$namaItem, $satuan, $urutan, $itemId]);
            setFlash('success', 'Item berhasil diperbarui.');
        }
        redirect(url('modules/binjas/standarisasi.php'));
    }
}

// Data items
$items = $db->query("SELECT * FROM binjas_item ORDER BY urutan, nama_item")->fetchAll();

// Data angkatan
$angkatanList = $db->query("SELECT id, nama FROM angkatan ORDER BY tahun DESC")->fetchAll();

// Standar global (angkatan_id IS NULL)
$standarGlobal = $db->query("
    SELECT item_id, nilai_standar
    FROM binjas_standarisasi
    WHERE angkatan_id IS NULL
")->fetchAll();
$standarGlobalMap = array_column($standarGlobal, 'nilai_standar', 'item_id');

// Standar per angkatan
$standarAngkatan = $db->query("
    SELECT bs.item_id, bs.angkatan_id, bs.nilai_standar, a.nama AS nama_angkatan
    FROM binjas_standarisasi bs
    JOIN angkatan a ON a.id = bs.angkatan_id
    WHERE bs.angkatan_id IS NOT NULL
    ORDER BY a.tahun DESC, bs.item_id
")->fetchAll();

// Group standar per angkatan
$standarAngkatanMap = [];
foreach ($standarAngkatan as $s) {
    $standarAngkatanMap[$s['angkatan_id']]['nama'] = $s['nama_angkatan'];
    $standarAngkatanMap[$s['angkatan_id']]['standar'][$s['item_id']] = $s['nilai_standar'];
}

$filterAngkatan = sanitizeInt(get('angkatan_id', 0));

$pageTitle    = 'Standarisasi Binjas';
$pageSubtitle = 'Kelola item & nilai standar pembinaan jasmani';
$activePage   = 'binjas';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Binjas',    'url' => url('modules/binjas/index.php')],
    ['label' => 'Standarisasi'],
];

ob_start();
?>

<div class="grid grid-2 gap-4" style="align-items:start;">

    <!-- ── Daftar Item ── -->
    <div>
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Item Binjas</h3>
                <span class="badge badge-primary"><?= count($items) ?> item</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                    <div class="empty-state empty-state--sm">
                        <p class="empty-desc">Belum ada item. Tambahkan di bawah.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Nama Item</th>
                                    <th>Satuan</th>
                                    <th>Urutan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $i => $item): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><strong><?= e($item['nama_item']) ?></strong></td>
                                        <td><span class="badge badge-secondary"><?= e($item['satuan']) ?></span></td>
                                        <td><?= e($item['urutan']) ?></td>
                                        <td>
                                            <div style="display:flex;gap:4px;">
                                                <button type="button"
                                                        class="btn btn-xs btn-secondary"
                                                        onclick="editItem(<?= $item['id'] ?>, '<?= e($item['nama_item']) ?>', '<?= e($item['satuan']) ?>', <?= $item['urutan'] ?>)">
                                                    Edit
                                                </button>
                                                <form method="POST" style="display:inline;">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action"  value="hapus_item">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-danger"
                                                            onclick="return confirm('Hapus item <?= e($item['nama_item']) ?>?')">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Tambah Item -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Tambah Item Baru</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="formTambahItem">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="tambah_item">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label class="form-label">Nama Item <span class="required">*</span></label>
                            <input type="text" name="nama_item" class="form-control"
                                   placeholder="Contoh: Push Up, Lari 2.4km…" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Satuan <span class="required">*</span></label>
                            <input type="text" name="satuan" class="form-control"
                                   placeholder="rep, menit, km…" required>
                        </div>
                        <div class="form-group" style="flex:0 0 80px;">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="urutan" class="form-control"
                                   value="<?= count($items) + 1 ?>" min="0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">+ Tambah Item</button>
                </form>
            </div>
        </div>

        <!-- Form Edit Item (hidden, muncul saat klik Edit) -->
        <div class="card" id="cardEditItem" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">Edit Item</h3>
                <button type="button" class="btn btn-sm btn-secondary" onclick="closeEditItem()">✕</button>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"  value="edit_item">
                    <input type="hidden" name="item_id" id="editItemId">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label class="form-label">Nama Item</label>
                            <input type="text" name="nama_item" id="editNamaItem" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="satuan" id="editSatuan" class="form-control" required>
                        </div>
                        <div class="form-group" style="flex:0 0 80px;">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="urutan" id="editUrutan" class="form-control" min="0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Nilai Standar ── -->
    <div>

        <!-- Standar Global -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Nilai Standar Global</h3>
                <span class="badge badge-secondary">Berlaku semua angkatan</span>
            </div>
            <div class="card-body">
                <?php if (empty($items)): ?>
                    <p class="text-muted" style="font-size:13px;">Tambah item dulu sebelum mengatur standar.</p>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"      value="simpan_standar">
                        <input type="hidden" name="angkatan_id" value="">

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Item</th><th>Satuan</th><th>Nilai Standar</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= e($item['nama_item']) ?></td>
                                            <td><span class="badge badge-secondary"><?= e($item['satuan']) ?></span></td>
                                            <td>
                                                <input type="number"
                                                       name="standar[<?= $item['id'] ?>]"
                                                       value="<?= e($standarGlobalMap[$item['id']] ?? '') ?>"
                                                       min="0" step="0.01"
                                                       style="width:90px;padding:4px 8px;font-size:13px;
                                                              border:1px solid var(--border);border-radius:6px;"
                                                       placeholder="—">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm">Simpan Standar Global</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Standar per Angkatan -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Nilai Standar per Angkatan</h3>
                <span class="badge badge-info">Override global</span>
            </div>
            <div class="card-body">
                <?php if (empty($items) || empty($angkatanList)): ?>
                    <p class="text-muted" style="font-size:13px;">Pastikan ada item dan angkatan terlebih dahulu.</p>
                <?php else: ?>
                    <!-- Tab angkatan -->
                    <div class="mb-3">
                        <label class="form-label">Pilih Angkatan</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <?php foreach ($angkatanList as $a): ?>
                                <a href="?angkatan_id=<?= $a['id'] ?>"
                                   class="btn btn-sm <?= $filterAngkatan == $a['id'] ? 'btn-primary' : 'btn-secondary' ?>">
                                    <?= e($a['nama']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($filterAngkatan): ?>
                        <?php
                        $namaAngkatan = '';
                        foreach ($angkatanList as $a) {
                            if ($a['id'] == $filterAngkatan) { $namaAngkatan = $a['nama']; break; }
                        }
                        $standarThisAngkatan = $standarAngkatanMap[$filterAngkatan]['standar'] ?? [];
                        ?>
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action"      value="simpan_standar">
                            <input type="hidden" name="angkatan_id" value="<?= $filterAngkatan ?>">

                            <p style="font-size:13px;color:var(--grey);margin-bottom:10px;">
                                Standar khusus untuk <strong><?= e($namaAngkatan) ?></strong>.
                                Kosongkan untuk menggunakan nilai global.
                            </p>

                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Satuan</th>
                                            <th>Global</th>
                                            <th>Standar Angkatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?= e($item['nama_item']) ?></td>
                                                <td><span class="badge badge-secondary"><?= e($item['satuan']) ?></span></td>
                                                <td style="color:var(--grey);font-size:12px;">
                                                    <?= $standarGlobalMap[$item['id']] ?? '—' ?>
                                                </td>
                                                <td>
                                                    <input type="number"
                                                           name="standar[<?= $item['id'] ?>]"
                                                           value="<?= e($standarThisAngkatan[$item['id']] ?? '') ?>"
                                                           min="0" step="0.01"
                                                           style="width:90px;padding:4px 8px;font-size:13px;
                                                                  border:1px solid var(--border);border-radius:6px;"
                                                           placeholder="—">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Simpan Standar <?= e($namaAngkatan) ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p style="font-size:13px;color:var(--grey);">
                            Pilih angkatan di atas untuk mengatur standar khusus.
                        </p>

                        <!-- Ringkasan standar yang sudah diset -->
                        <?php if (!empty($standarAngkatanMap)): ?>
                            <div class="mt-3">
                                <p style="font-size:13px;font-weight:600;margin-bottom:8px;">Standar yang sudah diatur:</p>
                                <?php foreach ($standarAngkatanMap as $aid => $data): ?>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                        <span class="badge badge-info"><?= e($data['nama']) ?></span>
                                        <span style="font-size:12px;color:var(--grey);">
                                            <?= count($data['standar']) ?> item diatur
                                        </span>
                                        <a href="?angkatan_id=<?= $aid ?>" class="btn btn-xs btn-secondary">Edit</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<style>
.btn-xs { font-size:11px; padding:3px 8px; border-radius:6px; }
</style>

<script>
function editItem(id, nama, satuan, urutan) {
    document.getElementById('editItemId').value   = id;
    document.getElementById('editNamaItem').value  = nama;
    document.getElementById('editSatuan').value    = satuan;
    document.getElementById('editUrutan').value    = urutan;
    document.getElementById('cardEditItem').style.display = 'block';
    document.getElementById('cardEditItem').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function closeEditItem() {
    document.getElementById('cardEditItem').style.display = 'none';
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';