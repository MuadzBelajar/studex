<?php
// ============================================================
//  STUDEX — Student Index
//  core/FileUpload.php — File Upload Handler
//  Validasi · Rename · Simpan lokal · Integrasi GoogleDrive
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

class FileUpload {

    // Konfigurasi default
    private array $allowedExtensions = ['pdf', 'docx', 'pptx', 'xlsx', 'jpg', 'jpeg', 'png'];
    private int   $maxBytes          = 10 * 1024 * 1024; // 10 MB
    private string $uploadDir        = '';
    private array  $errors           = [];

    // ============================================================
    // CONSTRUCTOR
    // ============================================================
    public function __construct(string $subDir = 'uploads') {
        $this->uploadDir = UPLOAD_PATH . '/' . trim($subDir, '/');
        $this->ensureDir($this->uploadDir);
    }

    // ============================================================
    // KONFIGURASI FLUENT
    // ============================================================
    public function allowExtensions(array $exts): static {
        $this->allowedExtensions = array_map('strtolower', $exts);
        return $this;
    }

    public function maxMb(float $mb): static {
        $this->maxBytes = (int)($mb * 1024 * 1024);
        return $this;
    }

    public function setDir(string $subDir): static {
        $this->uploadDir = UPLOAD_PATH . '/' . trim($subDir, '/');
        $this->ensureDir($this->uploadDir);
        return $this;
    }

    // ============================================================
    // UPLOAD DARI $_FILES
    // ============================================================
    /**
     * Proses upload file dari form HTML
     *
     * @param string $inputName   Nama input field: <input type="file" name="...">
     * @param string $prefix      Prefix nama file (opsional): e.g. 'notulensi'
     *
     * @return array [
     *   'success'      => bool,
     *   'message'      => string,
     *   'path'         => string,   // path lokal lengkap
     *   'filename'     => string,   // nama file tersimpan
     *   'original_name'=> string,   // nama file asli
     *   'extension'    => string,
     *   'size'         => int,      // bytes
     *   'mime'         => string,
     * ]
     */
    public function handle(string $inputName, string $prefix = ''): array {
        $this->errors = [];

        // Cek apakah ada file
        if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'])) {
            return $this->fail('Tidak ada file yang dipilih.');
        }

        $file = $_FILES[$inputName];

        // Cek error dari PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->fail($this->phpUploadError($file['error']));
        }

        $originalName = basename($file['name']);
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $size         = $file['size'];
        $tmpPath      = $file['tmp_name'];

        // Validasi ekstensi
        if (!in_array($ext, $this->allowedExtensions)) {
            return $this->fail(
                'Format file tidak diizinkan. Hanya: ' . implode(', ', $this->allowedExtensions)
            );
        }

        // Validasi ukuran
        if ($size > $this->maxBytes) {
            return $this->fail(
                'Ukuran file terlalu besar. Maksimal: ' . $this->formatSize($this->maxBytes)
            );
        }

        // Validasi MIME type (double check)
        $mime = mime_content_type($tmpPath) ?: 'application/octet-stream';
        if (!$this->isValidMime($mime, $ext)) {
            return $this->fail('Tipe file tidak valid atau tidak sesuai dengan ekstensi.');
        }

        // Generate nama file unik
        $newFilename = $this->generateName($prefix, $ext);
        $destPath    = $this->uploadDir . '/' . $newFilename;

        // Pindahkan file
        if (!move_uploaded_file($tmpPath, $destPath)) {
            return $this->fail('Gagal menyimpan file. Periksa permission folder storage.');
        }

        return [
            'success'       => true,
            'message'       => 'File berhasil diupload.',
            'path'          => $destPath,
            'filename'      => $newFilename,
            'original_name' => $originalName,
            'extension'     => $ext,
            'size'          => $size,
            'mime'          => $mime,
        ];
    }

    // ============================================================
    // UPLOAD + GOOGLE DRIVE (all-in-one)
    // ============================================================
    /**
     * Upload ke lokal kemudian otomatis ke Google Drive
     *
     * @param string   $inputName  Nama input file
     * @param string   $modul      Modul Drive: 'rabuan' | 'mentoring' | 'operasional' | 'binjas'
     * @param string   $prefix     Prefix nama file
     * @param callable $saveToDb   Callback untuk simpan ke DB: function(array $localResult, array $driveResult)
     *
     * @return array
     */
    public function handleWithDrive(
        string $inputName,
        string $modul,
        string $prefix = '',
        ?callable $saveToDb = null
    ): array {
        // 1. Upload lokal dulu
        $local = $this->handle($inputName, $prefix);
        if (!$local['success']) return $local;

        $driveResult = ['success' => false, 'message' => 'Google Drive tidak aktif.', 'file_id' => null, 'link' => null];

        // 2. Upload ke Drive kalau aktif
        if (GoogleDrive::isEnabled()) {
            $driveResult = GoogleDrive::getInstance()->upload(
                $local['path'],
                $local['original_name'],
                $modul
            );

            // Hapus file lokal setelah berhasil ke Drive (opsional, hemat storage)
            // if ($driveResult['success']) @unlink($local['path']);
        }

        // 3. Callback simpan ke DB
        if ($saveToDb) {
            try {
                $saveToDb($local, $driveResult);
            } catch (\Exception $e) {
                error_log('STUDEX FileUpload saveToDb error: ' . $e->getMessage());
            }
        }

        return [
            'success'        => true,
            'message'        => $driveResult['success']
                                ? 'File berhasil diupload dan disimpan ke Google Drive.'
                                : 'File berhasil diupload (lokal). ' . $driveResult['message'],
            'local'          => $local,
            'drive'          => $driveResult,
            // Shortcut fields yang sering dipakai
            'filename'       => $local['filename'],
            'original_name'  => $local['original_name'],
            'path'           => $local['path'],
            'size'           => $local['size'],
            'drive_file_id'  => $driveResult['file_id']  ?? null,
            'drive_link'     => $driveResult['link']      ?? null,
            'drive_folder_id'=> $this->getFolderIdFromDb($modul),
        ];
    }

    // ============================================================
    // HAPUS FILE LOKAL
    // ============================================================
    public static function deleteLocal(string $path): bool {
        if (empty($path) || !file_exists($path)) return true;
        // Keamanan: pastikan file ada di dalam UPLOAD_PATH
        $realPath = realpath($path);
        $realBase = realpath(UPLOAD_PATH);
        if (!$realPath || !$realBase || strpos($realPath, $realBase) !== 0) {
            error_log('STUDEX FileUpload: Percobaan hapus file di luar upload dir — ' . $path);
            return false;
        }
        return @unlink($realPath);
    }

    // ============================================================
    // VALIDASI MIME TYPE
    // ============================================================
    private function isValidMime(string $mime, string $ext): bool {
        $allowed = [
            'pdf'  => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'],
            'doc'  => ['application/msword'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
        ];

        if (!isset($allowed[$ext])) return true; // Ekstensi tidak dikenal, lewati cek MIME
        return in_array($mime, $allowed[$ext]);
    }

    // ============================================================
    // GENERATE NAMA FILE UNIK
    // ============================================================
    private function generateName(string $prefix, string $ext): string {
        $prefix  = $prefix ? preg_replace('/[^a-z0-9_-]/i', '', $prefix) . '_' : '';
        $date    = date('Ymd_His');
        $random  = bin2hex(random_bytes(4));
        return strtolower($prefix . $date . '_' . $random . '.' . $ext);
    }

    // ============================================================
    // PASTIKAN DIREKTORI ADA
    // ============================================================
    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // ============================================================
    // HELPER — Format ukuran file
    // ============================================================
    private function formatSize(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    // ============================================================
    // HELPER — PHP upload error messages
    // ============================================================
    private function phpUploadError(int $code): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload_max_filesize di php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File melebihi batas MAX_FILE_SIZE di form.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diupload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP.',
        ];
        return $messages[$code] ?? 'Upload error tidak diketahui (kode: ' . $code . ').';
    }

    // ============================================================
    // HELPER — Ambil folder_id modul dari DB
    // ============================================================
    private function getFolderIdFromDb(string $modul): string {
        try {
            $stmt = db()->prepare("SELECT folder_id FROM drive_config WHERE modul = ? LIMIT 1");
            $stmt->execute([$modul]);
            $row = $stmt->fetch();
            return $row['folder_id'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }

    // ============================================================
    // HELPER — Return error
    // ============================================================
    private function fail(string $message): array {
        $this->errors[] = $message;
        return [
            'success'       => false,
            'message'       => $message,
            'path'          => '',
            'filename'      => '',
            'original_name' => '',
            'extension'     => '',
            'size'          => 0,
            'mime'          => '',
        ];
    }

    public function getErrors(): array {
        return $this->errors;
    }
}