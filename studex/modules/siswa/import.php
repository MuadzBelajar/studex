<?php
// ============================================================
//  STUDEX — Student Index
//  modules/siswa/import.php — Import Siswa via CSV/Excel
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

// ============================================================
// DOWNLOAD TEMPLATE (handle sebelum output HTML)
// ============================================================
if (isset($_GET['download_template'])) {
    $template  = "nis,nama,jenis_kelamin,tempat_lahir,tanggal_lahir,no_hp,email,alamat\n";
    $template .= "2024001,Ahmad Fauzi Ramadhan,L,Makassar,2005-03-14,081234567890,ahmad@email.com,Jl. Merdeka No. 1\n";
    $template .= "2024002,Siti Nurhaliza,P,Makassar,2005-07-22,082345678901,siti@email.com,Jl. Sudirman No. 5\n";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_import_siswa.csv"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF"; // BOM untuk Excel
    echo $template;
    exit;
}

$db           = db();
$errors       = [];
$importResult = null;
$angkatanList = $db->query("SELECT id, nama, kode FROM angkatan WHERE is_aktif=1 ORDER BY tahun DESC")->fetchAll();

// ============================================================
// PROSES UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $angkatanId = sanitizeInt(post('angkatan_id'));
    if (!$angkatanId) $errors[] = 'Angkatan wajib dipilih.';

    if (empty($_FILES['file_import']['name'])) {
        $errors[] = 'File CSV/Excel wajib diunggah.';
    }

    if (empty($errors)) {
        $file = $_FILES['file_import'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            $errors[] = 'Format tidak didukung. Gunakan CSV, XLSX, atau XLS.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Ukuran file maksimal 5 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Gagal mengupload file.';
        } else {
            $rows = ($ext === 'csv') ? parseCsv($file['tmp_name']) : parseExcel($file['tmp_name']);
            if (empty($rows)) {
                $errors[] = 'File kosong atau tidak dapat dibaca. Pastikan format sesuai template.';
            } else {
                $importResult = processImport($db, $rows, $angkatanId);
            }
        }
    }
}

// ============================================================
// PARSE CSV
// ============================================================
function parseCsv(string $path): array {
    $rows = [];
    if (($handle = fopen($path, 'r')) === false) return $rows;
    // Skip BOM jika ada
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $header = null;
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if ($header === null) {
            $header = array_map(fn($h) => strtolower(trim($h)), $row);
            continue;
        }
        if (count(array_filter($row)) === 0) continue;
        $rows[] = array_combine($header, array_pad($row, count($header), ''));
    }
    fclose($handle);
    return $rows;
}

// ============================================================
// PARSE EXCEL (pakai PhpSpreadsheet jika ada)
// ============================================================
function parseExcel(string $path): array {
    $autoload = ROOT_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload)) return [];
    require_once $autoload;
    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) return [];

    try {
        $sheet  = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        $rows   = [];
        $header = null;

        foreach ($sheet->getRowIterator() as $row) {
            $iter = $row->getCellIterator();
            $iter->setIterateOnlyExistingCells(false);
            $cells = [];
            foreach ($iter as $cell) {
                $cells[] = trim((string)$cell->getFormattedValue());
            }
            // Trim trailing empties
            while (end($cells) === '') array_pop($cells);
            if (empty($cells)) continue;

            if ($header === null) {
                $header = array_map('strtolower', $cells);
                continue;
            }
            $rows[] = array_combine($header, array_pad($cells, count($header), ''));
        }
        return $rows;
    } catch (\Exception $e) {
        error_log('STUDEX Excel parse error: ' . $e->getMessage());
        return [];
    }
}

// ============================================================
// PROCESS IMPORT
// ============================================================
function processImport(\PDO $db, array $rows, int $angkatanId): array {
    $result = ['total' => count($rows), 'sukses' => 0, 'skip' => 0, 'gagal' => 0, 'log' => []];

    // Alias kolom yang diterima
    $map = [
        'nis'           => ['nis', 'nomor induk', 'no_induk', 'nisn'],
        'nama'          => ['nama', 'nama_lengkap', 'nama lengkap', 'name'],
        'jenis_kelamin' => ['jenis_kelamin', 'jk', 'gender', 'kelamin'],
        'tempat_lahir'  => ['tempat_lahir', 'tempat lahir'],
        'tanggal_lahir' => ['tanggal_lahir', 'tanggal lahir', 'tgl_lahir', 'dob'],
        'no_hp'         => ['no_hp', 'hp', 'telepon', 'phone', 'no hp'],
        'email'         => ['email', 'e-mail'],
        'alamat'        => ['alamat', 'address'],
    ];

    $stmtCek = $db->prepare("SELECT id FROM siswa WHERE nis = ?");
    $stmtIns = $db->prepare("
        INSERT INTO siswa (angkatan_id, nis, nama, jenis_kelamin, tempat_lahir,
                           tanggal_lahir, no_hp, email, alamat, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')
    ");

    foreach ($rows as $idx => $row) {
        $line = $idx + 2;
        $d    = [];

        foreach ($map as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($row[$alias]) && trim($row[$alias]) !== '') {
                    $d[$field] = trim($row[$alias]);
                    break;
                }
            }
            $d[$field] = $d[$field] ?? '';
        }

        if (empty($d['nis'])) {
            $result['gagal']++;
            $result['log'][] = ['baris' => $line, 'status' => 'gagal', 'pesan' => 'NIS kosong — dilewati.'];
            continue;
        }
        if (empty($d['nama'])) {
            $result['gagal']++;
            $result['log'][] = ['baris' => $line, 'status' => 'gagal', 'pesan' => 'Nama kosong — dilewati.'];
            continue;
        }

        // Normalisasi JK
        $jk = strtoupper(substr($d['jenis_kelamin'], 0, 1));
        if (!in_array($jk, ['L', 'P'])) $jk = 'L';

        // Normalisasi tanggal
        $tgl = null;
        if (!empty($d['tanggal_lahir'])) {
            $ts = strtotime($d['tanggal_lahir']);
            if ($ts) $tgl = date('Y-m-d', $ts);
        }

        // Cek duplikat NIS
        $stmtCek->execute([$d['nis']]);
        if ($stmtCek->fetch()) {
            $result['skip']++;
            $result['log'][] = ['baris' => $line, 'status' => 'skip', 'pesan' => 'NIS ' . $d['nis'] . ' sudah ada — dilewati.'];
            continue;
        }

        try {
            $stmtIns->execute([
                $angkatanId,
                $d['nis'],
                $d['nama'],
                $jk,
                $d['tempat_lahir'] ?: null,
                $tgl,
                $d['no_hp']   ?: null,
                $d['email']   ?: null,
                $d['alamat']  ?: null,
            ]);
            $result['sukses']++;
            $result['log'][] = ['baris' => $line, 'status' => 'sukses', 'pesan' => $d['nama'] . ' berhasil diimport.'];
        } catch (\PDOException $e) {
            $result['gagal']++;
            $result['log'][] = ['baris' => $line, 'status' => 'gagal', 'pesan' => 'Error: ' . $e->getMessage()];
        }
    }
    return $result;
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Import Siswa';
$activePage  = 'siswa';
$extraJs     = ['upload.js'];
$breadcrumbs = [
    ['label' => 'Dashboard',  'url' => url('modules/dashboard/index.php')],
    ['label' => 'Data Siswa', 'url' => url('modules/siswa/index.php')],
    ['label' => 'Import CSV/Excel'],
];

ob_start();
?>

<div class="grid grid-2-1 gap-5">

    <!-- KIRI: Form atau Hasil -->
    <div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-5">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" width="18" height="18"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <div><?php foreach ($errors as $e) echo '<div>' . e($e) . '</div>'; ?></div>
        </div>
        <?php endif; ?>

        <?php if ($importResult): ?>
        <!-- HASIL IMPORT -->
        <div class="card">
            <div class="card-header"><div class="card-title">Hasil Import</div></div>

            <div class="grid grid-4 gap-3 mb-5">
                <?php foreach ([
                    [$importResult['total'],  'Total',            'var(--neutral-100)',        'var(--text-primary)'],
                    [$importResult['sukses'], 'Berhasil',         'var(--color-success-light)', 'var(--color-success)'],
                    [$importResult['skip'],   'Lewati (duplikat)','var(--color-warning-light)', 'var(--color-warning)'],
                    [$importResult['gagal'],  'Gagal',            'var(--color-danger-light)',  'var(--color-danger)'],
                ] as [$val, $lbl, $bg, $clr]): ?>
                <div style="text-align:center;padding:var(--space-4);background:<?= $bg ?>;border-radius:var(--border-radius-md);">
                    <div style="font-size:1.5rem;font-weight:var(--fw-bold);color:<?= $clr ?>;line-height:1;">
                        <?= $val ?>
                    </div>
                    <div style="font-size:var(--text-xs);margin-top:4px;color:<?= $clr ?>;"><?= $lbl ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($importResult['log'])): ?>
            <div style="max-height:320px;overflow-y:auto;border:1px solid var(--border-color);border-radius:var(--border-radius-md);">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th style="width:60px;">Baris</th>
                            <th style="width:90px;">Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importResult['log'] as $log): ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?= $log['baris'] ?></td>
                            <td>
                                <?php if ($log['status'] === 'sukses'): ?>
                                    <span class="badge badge-success">Sukses</span>
                                <?php elseif ($log['status'] === 'skip'): ?>
                                    <span class="badge badge-warning">Skip</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Gagal</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:var(--text-xs);"><?= e($log['pesan']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="flex gap-3 mt-5">
                <a href="<?= url('modules/siswa/index.php') ?>" class="btn btn-primary">
                    Lihat Daftar Siswa
                </a>
                <a href="<?= url('modules/siswa/import.php') ?>" class="btn btn-secondary">
                    Import Lagi
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- FORM UPLOAD -->
        <div class="card">
            <div class="card-header"><div class="card-title">Upload File</div></div>

            <form method="POST" action="" enctype="multipart/form-data" novalidate>
                <?= csrfField() ?>

                <div class="form-group mb-5">
                    <label class="form-label">Angkatan <span class="required">*</span></label>
                    <select name="angkatan_id" class="form-control" required>
                        <option value="">-- Pilih Angkatan --</option>
                        <?php foreach ($angkatanList as $ang): ?>
                            <option value="<?= $ang['id'] ?>">
                                <?= e($ang['nama']) ?> (<?= e($ang['kode']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Semua siswa akan dimasukkan ke angkatan ini.</div>
                </div>

                <div class="form-group mb-6">
                    <label class="form-label">File CSV / Excel <span class="required">*</span></label>
                    <div class="upload-zone"
                         data-upload-zone
                         data-input="fileImport"
                         data-accept=".csv,.xlsx,.xls"
                         data-max-mb="5"
                         data-multiple="false"
                         data-preview-list="filePreviewList">
                        <div class="upload-zone-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.5" width="48" height="48"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="16 16 12 12 8 16"/>
                                <line x1="12" y1="12" x2="12" y2="21"/>
                                <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>
                            </svg>
                        </div>
                        <div class="upload-zone-title">Seret & lepas file di sini</div>
                        <div class="upload-zone-subtitle">
                            atau <span>pilih file</span> — CSV, XLSX, XLS (maks. 5 MB)
                        </div>
                    </div>
                    <input type="file" id="fileImport" name="file_import"
                           accept=".csv,.xlsx,.xls" style="display:none;">
                    <div id="filePreviewList"></div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" width="16" height="16"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        Proses Import
                    </button>
                    <a href="<?= url('modules/siswa/index.php') ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- KANAN: Panduan + Template -->
    <div>
        <div class="card mb-5">
            <div class="card-header"><div class="card-title">Format Kolom</div></div>
            <div style="font-size:var(--text-sm);">
                <table class="table table-compact">
                    <thead>
                        <tr><th>Nama Kolom</th><th>Wajib</th><th>Contoh</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ([
                            ['nis',           true,  '2024001'],
                            ['nama',          true,  'Ahmad Fauzi'],
                            ['jenis_kelamin', false, 'L atau P'],
                            ['tempat_lahir',  false, 'Makassar'],
                            ['tanggal_lahir', false, '2005-03-14'],
                            ['no_hp',         false, '081234567890'],
                            ['email',         false, 'siswa@mail.com'],
                            ['alamat',        false, 'Jl. Merdeka No. 1'],
                        ] as [$col, $req, $ex]): ?>
                        <tr>
                            <td><code><?= $col ?></code></td>
                            <td>
                                <?= $req
                                    ? '<span class="badge badge-danger">Wajib</span>'
                                    : '<span class="badge badge-secondary">Opsional</span>' ?>
                            </td>
                            <td style="color:var(--text-muted);font-size:11px;"><?= $ex ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-header"><div class="card-title">Catatan Penting</div></div>
            <ul style="font-size:var(--text-sm);color:var(--text-secondary);
                       list-style:disc;padding-left:var(--space-5);
                       display:flex;flex-direction:column;gap:var(--space-2);">
                <li>Baris pertama <strong>harus</strong> nama kolom (header)</li>
                <li>NIS yang sudah ada akan <strong>dilewati</strong> (tidak ditimpa)</li>
                <li>Status semua siswa import akan diset <strong>Aktif</strong></li>
                <li>Format tanggal: <code>YYYY-MM-DD</code></li>
                <li>JK: <code>L</code> = Laki-laki, <code>P</code> = Perempuan</li>
                <li>Untuk Excel, install: <code>composer require phpoffice/phpspreadsheet</code></li>
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Download Template</div></div>
            <p style="font-size:var(--text-sm);color:var(--text-muted);margin-bottom:var(--space-4);">
                Gunakan template ini untuk memastikan format kolom yang benar.
            </p>
            <a href="?download_template=1" class="btn btn-secondary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" width="14" height="14"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Download Template CSV
            </a>
        </div>
    </div>

</div>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>