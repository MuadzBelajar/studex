<?php
// ============================================================
//  STUDEX — Student Index
//  config/google_drive.php — Konfigurasi Google Drive
//
//  Metode   : Service Account (JSON Key)
//  Support  : Shared Drive (Google Workspace) & My Drive
//  Require  : composer require google/apiclient:^2.0
//
//  PANDUAN SETUP SINGKAT:
//  1. Buka https://console.cloud.google.com
//  2. Buat project → aktifkan "Google Drive API"
//  3. IAM & Admin → Service Accounts → Buat SA baru
//  4. Download JSON key → rename: service-account.json
//  5. Upload ke: storage/credentials/service-account.json
//  6. Share folder Google Drive ke email SA sebagai Editor
//  7. Masuk ke Pengaturan → Konfigurasi Drive → isi Folder ID
//  8. Klik "Test Koneksi Drive" untuk verifikasi
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

// ── Path ke file JSON Service Account ─────────────────────────
// Nilai ini adalah FALLBACK jika belum dikonfigurasi via DB.
// Prioritas: DB settings (kunci: google_credentials_path) → konstanta ini.
define('GOOGLE_CREDENTIALS_PATH',
    ROOT_PATH . '/storage/credentials/service-account.json'
);

// ── Nama aplikasi yang dikirim ke Google API ──────────────────
define('GOOGLE_APP_NAME', APP_NAME . ' — ' . APP_TAGLINE);

// ── Pastikan folder storage/credentials/ ada ─────────────────
$_credDir = ROOT_PATH . '/storage/credentials';
if (!is_dir($_credDir)) {
    mkdir($_credDir, 0755, true);
}
unset($_credDir);