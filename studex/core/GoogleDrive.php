<?php
// ============================================================
//  STUDEX — Student Index
//  core/GoogleDrive.php — Google Drive Integration
//  Metode  : Service Account (JSON Key)
//  Support : Shared Drive (Google Workspace) & My Drive
//  Require : composer require google/apiclient:^2.0
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

class GoogleDrive {

    private static ?GoogleDrive $instance = null;
    private ?\Google\Service\Drive $service = null;
    private bool $initialized = false;
    private string $lastError  = '';

    // ============================================================
    // SINGLETON
    // ============================================================
    public static function getInstance(): static {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}

    // ============================================================
    // INISIALISASI CLIENT
    // ============================================================
    private function init(): bool {
        if ($this->initialized) return $this->service !== null;

        $this->initialized = true;

        // Cek apakah Composer vendor ada
        $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
        if (!file_exists($vendorAutoload)) {
            $this->lastError = 'Google API Client belum diinstall. Jalankan: composer require google/apiclient:^2.0';
            error_log('STUDEX GoogleDrive: ' . $this->lastError);
            return false;
        }

        require_once $vendorAutoload;

        // Ambil path credentials dari settings DB
        $credPath = $this->getCredentialsPath();

        if (!$credPath || !file_exists($credPath)) {
            $this->lastError = 'File credentials Service Account tidak ditemukan di: ' . ($credPath ?: 'path belum dikonfigurasi');
            error_log('STUDEX GoogleDrive: ' . $this->lastError);
            return false;
        }

        try {
            $client = new \Google\Client();
            $client->setApplicationName(GOOGLE_APP_NAME);
            $client->setScopes([\Google\Service\Drive::DRIVE]);
            $client->setAuthConfig($credPath);

            $this->service    = new \Google\Service\Drive($client);
            $this->lastError  = '';
            return true;

        } catch (\Exception $e) {
            $this->lastError = 'Gagal inisialisasi Google Client: ' . $e->getMessage();
            error_log('STUDEX GoogleDrive: ' . $this->lastError);
            return false;
        }
    }

    // ============================================================
    // UPLOAD FILE KE GOOGLE DRIVE
    // ============================================================
    /**
     * Upload file ke folder Google Drive berdasarkan modul
     *
     * @param string $localPath   Path file lokal yang akan diupload
     * @param string $fileName    Nama file yang ditampilkan di Drive
     * @param string $modul       Modul: 'rabuan' | 'mentoring' | 'operasional' | 'binjas' | 'umum'
     * @param string|null $mimeType  MIME type file (null = auto-detect)
     *
     * @return array ['success' => bool, 'file_id' => string, 'link' => string, 'message' => string]
     */
    public function upload(
        string $localPath,
        string $fileName,
        string $modul = 'umum',
        ?string $mimeType = null
    ): array {
        if (!$this->init()) {
            return $this->errorResult('Google Drive tidak tersedia: ' . $this->lastError);
        }

        if (!file_exists($localPath)) {
            return $this->errorResult('File tidak ditemukan: ' . $localPath);
        }

        // Ambil Folder ID dari DB
        $folderId = $this->getFolderId($modul);
        if (!$folderId) {
            return $this->errorResult('Folder ID untuk modul "' . $modul . '" belum dikonfigurasi. Atur di Pengaturan → Google Drive.');
        }

        // Detect MIME type
        if (!$mimeType) {
            $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';
        }

        try {
            // Metadata file
            $fileMetadata = new \Google\Service\Drive\DriveFile([
                'name'    => $fileName,
                'parents' => [$folderId],
            ]);

            // Upload dengan support Shared Drive
            $file = $this->service->files->create(
                $fileMetadata,
                [
                    'data'               => file_get_contents($localPath),
                    'mimeType'           => $mimeType,
                    'uploadType'         => 'multipart',
                    'fields'             => 'id, name, webViewLink, webContentLink',
                    'supportsAllDrives'  => true,   // WAJIB untuk Shared Drive
                ]
            );

            // Set permission agar bisa diakses via link (anyone with link)
            $this->setPublicPermission($file->getId());

            return [
                'success'  => true,
                'file_id'  => $file->getId(),
                'link'     => $file->getWebViewLink(),
                'download' => $file->getWebContentLink(),
                'name'     => $file->getName(),
                'message'  => 'File berhasil diupload ke Google Drive.',
            ];

        } catch (\Google\Service\Exception $e) {
            $msg = $this->parseGoogleError($e);
            error_log('STUDEX GoogleDrive Upload Error: ' . $msg);
            return $this->errorResult($msg);

        } catch (\Exception $e) {
            error_log('STUDEX GoogleDrive Upload Error: ' . $e->getMessage());
            return $this->errorResult('Upload gagal: ' . $e->getMessage());
        }
    }

    // ============================================================
    // HAPUS FILE DARI DRIVE
    // ============================================================
    public function delete(string $fileId): array {
        if (!$this->init()) {
            return $this->errorResult($this->lastError);
        }

        try {
            $this->service->files->delete($fileId, ['supportsAllDrives' => true]);
            return ['success' => true, 'message' => 'File berhasil dihapus dari Google Drive.'];

        } catch (\Google\Service\Exception $e) {
            $msg = $this->parseGoogleError($e);
            error_log('STUDEX GoogleDrive Delete Error: ' . $msg);
            return $this->errorResult($msg);
        }
    }

    // ============================================================
    // CEK KONEKSI & VALIDASI FOLDER ID
    // ============================================================
    public function testConnection(string $folderId): array {
        if (!$this->init()) {
            return $this->errorResult($this->lastError);
        }

        try {
            $folder = $this->service->files->get($folderId, [
                'fields'            => 'id, name, mimeType',
                'supportsAllDrives' => true,
            ]);

            if ($folder->getMimeType() !== 'application/vnd.google-apps.folder') {
                return $this->errorResult('ID yang dimasukkan bukan folder Google Drive.');
            }

            return [
                'success' => true,
                'name'    => $folder->getName(),
                'message' => 'Koneksi berhasil! Folder "' . $folder->getName() . '" ditemukan.',
            ];

        } catch (\Google\Service\Exception $e) {
            $msg = $this->parseGoogleError($e);
            return $this->errorResult($msg);
        }
    }

    // ============================================================
    // LIST FILE DALAM FOLDER
    // ============================================================
    public function listFiles(string $folderId, int $limit = 20): array {
        if (!$this->init()) {
            return $this->errorResult($this->lastError);
        }

        try {
            $results = $this->service->files->listFiles([
                'q'                         => "'" . $folderId . "' in parents and trashed=false",
                'fields'                    => 'files(id, name, webViewLink, createdTime, size, mimeType)',
                'pageSize'                  => $limit,
                'orderBy'                   => 'createdTime desc',
                'supportsAllDrives'         => true,
                'includeItemsFromAllDrives' => true,
            ]);

            $files = [];
            foreach ($results->getFiles() as $file) {
                $files[] = [
                    'id'          => $file->getId(),
                    'name'        => $file->getName(),
                    'link'        => $file->getWebViewLink(),
                    'created_at'  => $file->getCreatedTime(),
                    'size'        => $file->getSize(),
                    'mime_type'   => $file->getMimeType(),
                ];
            }

            return ['success' => true, 'files' => $files];

        } catch (\Google\Service\Exception $e) {
            return $this->errorResult($this->parseGoogleError($e));
        }
    }

    // ============================================================
    // SET PUBLIC PERMISSION (anyone with link can view)
    // ============================================================
    private function setPublicPermission(string $fileId): void {
        try {
            $permission = new \Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]);
            $this->service->permissions->create($fileId, $permission, [
                'supportsAllDrives' => true,
            ]);
        } catch (\Exception $e) {
            // Non-fatal: file tetap terupload, hanya tidak bisa diakses publik
            error_log('STUDEX GoogleDrive: Gagal set permission — ' . $e->getMessage());
        }
    }

    // ============================================================
    // AMBIL FOLDER ID DARI DATABASE
    // ============================================================
    private function getFolderId(string $modul): string {
        try {
            $stmt = db()->prepare("SELECT folder_id FROM drive_config WHERE modul = ? AND is_aktif = 1 LIMIT 1");
            $stmt->execute([$modul]);
            $row = $stmt->fetch();
            return $row['folder_id'] ?? '';
        } catch (\Exception $e) {
            error_log('STUDEX GoogleDrive: Gagal ambil folder_id — ' . $e->getMessage());
            return '';
        }
    }

    // ============================================================
    // AMBIL PATH CREDENTIALS DARI DATABASE / KONSTANTA
    // ============================================================
    private function getCredentialsPath(): string {
        // Cek dari DB settings dulu
        try {
            $stmt = db()->prepare("SELECT nilai FROM settings WHERE kunci = 'google_credentials_path' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if (!empty($row['nilai'])) return $row['nilai'];
        } catch (\Exception $e) {
            // fallthrough
        }

        // Fallback ke konstanta di google_drive.php
        return defined('GOOGLE_CREDENTIALS_PATH') ? GOOGLE_CREDENTIALS_PATH : '';
    }

    // ============================================================
    // PARSE GOOGLE API ERROR
    // ============================================================
    private function parseGoogleError(\Google\Service\Exception $e): string {
        $errors = $e->getErrors();
        if (!empty($errors)) {
            $err    = $errors[0];
            $reason = $err['reason'] ?? '';
            $msg    = $err['message'] ?? $e->getMessage();

            // Pesan error yang lebih ramah
            $friendlyMessages = [
                'notFound'            => 'Folder ID tidak ditemukan. Pastikan Folder ID benar dan Service Account sudah di-share ke folder tersebut.',
                'forbidden'           => 'Akses ditolak. Pastikan Service Account sudah di-share sebagai Editor/Contributor ke folder Google Drive.',
                'storageQuotaExceeded'=> 'Kuota Google Drive penuh.',
                'invalidSharingRequest'=> 'Service Account tidak bisa di-share. Gunakan Shared Drive (Google Workspace).',
            ];

            return $friendlyMessages[$reason] ?? 'Google Drive Error: ' . $msg;
        }
        return 'Google Drive Error: ' . $e->getMessage();
    }

    // ============================================================
    // HELPER RESULT
    // ============================================================
    private function errorResult(string $message): array {
        return ['success' => false, 'message' => $message, 'file_id' => null, 'link' => null];
    }

    // ============================================================
    // CEK APAKAH GOOGLE DRIVE AKTIF
    // ============================================================
    public static function isEnabled(): bool {
        try {
            $stmt = db()->prepare("SELECT nilai FROM settings WHERE kunci = 'google_drive_enabled' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            return ($row['nilai'] ?? '0') === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    // ============================================================
    // SHORTCUT STATIC — pakai dari modul manapun
    // ============================================================

    /**
     * Upload + simpan ke DB (helper all-in-one)
     *
     * @param string $localPath
     * @param string $fileName
     * @param string $modul
     * @param callable|null $onSuccess  function(array $result) — simpan ke DB
     *
     * @return array
     */
    public static function uploadAndSave(
        string $localPath,
        string $fileName,
        string $modul,
        ?callable $onSuccess = null
    ): array {
        if (!self::isEnabled()) {
            return ['success' => false, 'message' => 'Integrasi Google Drive belum diaktifkan.'];
        }

        $result = self::getInstance()->upload($localPath, $fileName, $modul);

        if ($result['success'] && $onSuccess) {
            try {
                $onSuccess($result);
            } catch (\Exception $e) {
                error_log('STUDEX GoogleDrive onSuccess callback error: ' . $e->getMessage());
            }
        }

        return $result;
    }
}