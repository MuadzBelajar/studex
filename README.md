<h1>STUDEX — Student Index</h1>
Sistem web monitoring aktivitas siswa berbasis PHP Native + MySQL


# Tentang STUDEX
STUDEX (Student Index) adalah sistem internal berbasis web untuk memantau, mengelola, dan memvisualisasikan seluruh aktivitas siswa. 
Sistem ini bersifat closed/internal — hanya dapat diakses oleh Super Admin dan Admin yang ditunjuk. Tidak ada registrasi publik.
Aktivitas yang dikelola:
•	📋 Rabuan — Rapat rutin mingguan + notulensi (Google Drive)
•	📚 Mentoring — Sesi bimbingan + upload materi (Google Drive)
•	🗂️ Operasional — Kegiatan lapangan dengan 3 fase (Pra → Ops → Pasca)
•	💪 Binjas — Pembinaan fisik + penilaian skor + radar chart


🛠️ Tech Stack
Layer	Teknologi
Frontend	HTML + CSS Native + JavaScript Vanilla
Backend	PHP 8.1+ (Native, tanpa framework)
Database	MySQL 8.0+
Local Dev	XAMPP / Laragon
Charts	Chart.js v4 (CDN)
Calendar	FullCalendar v6 (CDN)
File Storage	Local + Google Drive API (Service Account)
Dependency	google/apiclient:^2.0 via Composer



🚀 Instalasi
1. Clone / Extract
git clone https://github.com/username/studex.git
# atau extract ZIP ke htdocs/studex/
2. Import Database
# Buat database
mysql -u root -p -e "CREATE DATABASE studex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema 
mysql -u root -p studex < database/schema.sql

# Import data awal + akun default
mysql -u root -p studex < database/seeder.sql
3. Konfigurasi Aplikasi
Edit file config/app.php:
define('BASE_URL', 'http://localhost/studex');   // sesuaikan URL lokal
define('APP_NAME', 'STUDEX');
define('TIMEZONE', 'Asia/Makassar');              // WITA
Edit file config/database.php:
define('DB_HOST', 'localhost');
define('DB_NAME', 'studex');
define('DB_USER', 'root');
define('DB_PASS', '');
4. Install Composer Dependencies
cd studex/
composer install
5. Akses Aplikasi
URL   : http://localhost/studex
User  : superadmin
Pass  : studex@admin123



Setup Google Drive (Opsional)
Fitur upload file ke Google Drive menggunakan Service Account.
Langkah Setup
1.	Buka Google Cloud Console
2.	Buat project baru → aktifkan Google Drive API
3.	Buat Service Account → download JSON key
4.	Rename file menjadi service-account.json
5.	Upload ke folder storage/credentials/
6.	Share folder Google Drive target ke email service account
7.	Masuk ke Pengaturan → Konfigurasi Drive → isi Folder ID per modul
8.	Klik Test Koneksi Drive untuk verifikasi

<pre><code>
📁 Struktur Direktori
studex/
├── .htaccess
├── index.php                    # Entry point, redirect ke dashboard
├── composer.json
│
├── config/
│   ├── app.php                  # BASE_URL, konstanta, timezone
│   ├── database.php             # PDO singleton + helper db()
│   ├── session.php              # Auth guards, CSRF, flash message
│   └── google_drive.php        # Path credentials + panduan setup
│
├── core/
│   ├── Auth.php                 # login(), logout(), hashPassword()
│   ├── Helpers.php              # 25+ fungsi bantu global
│   ├── GoogleDrive.php          # upload(), delete(), testConnection()
│   ├── FileUpload.php           # handle(), handleWithDrive()
│   └── Router.php               # dispatch(), json(), requirePost()
│
├── database/
│   ├── schema.sql               # 19 tabel lengkap
│   └── seeder.sql               # Default user + data contoh
│
├── assets/
│   ├── css/                     # variables, layout, components, ...
│   ├── js/                      # app.js, table.js, presensi.js, ...
│   ├── fonts/                   # General Sans (self-hosted)
│   └── img/
│
├── views/
│   ├── layouts/                 # main.php, auth.php, print.php
│   └── partials/                # sidebar, topbar, breadcrumb, ...
│
├── modules/
│   ├── auth/                    # login, logout, forgot_password
│   ├── dashboard/               # index, dashboard_data
│   ├── siswa/                   # CRUD + import + export
│   ├── angkatan/                # CRUD
│   ├── users/                   # CRUD (Super Admin only)
│   ├── rabuan/                  # CRUD + notulensi + presensi
│   ├── mentoring/               # CRUD + materi + presensi
│   ├── operasional/             # CRUD + 3 fase (pra/ops/pasca)
│   ├── binjas/                  # CRUD + skor + standarisasi
│   ├── jadwal/                  # FullCalendar + get_events
│   ├── presensi/                # input + rekap + export CSV
│   └── settings/                # index + drive_config + save
│
├── api/
│   ├── calendar_events.php      # Events JSON untuk kalender
│   ├── dashboard_stats.php      # Statistik dashboard
│   ├── notification.php         # Notifikasi topbar
│   └── search_siswa.php         # Live search siswa
│
└── storage/
    ├── credentials/             # service-account.json (tidak di-commit)
    └── uploads/                 # File upload lokal
</code></pre>

Database — 19 Tabel
Tabel	Keterangan
users	Akun admin (role: super_admin / admin)
angkatan	Data batch/angkatan
siswa	Data siswa, FK angkatan_id
rabuan	Sesi rapat rutin
rabuan_notulensi	File notulensi PDF (Drive)
mentoring_sesi	Sesi mentoring
mentoring_materi	File materi (Drive)
operasional	Kegiatan lapangan (3 fase)
operasional_pra	Data perencanaan (1:1)
operasional_peserta	Daftar peserta operasional
operasional_perlengkapan	Daftar perlengkapan
operasional_laporan	File laporan PDF (Drive)
operasional_checklist	Checklist pasca kegiatan
binjas_sesi	Sesi pembinaan fisik
binjas_item	Item penilaian binjas
binjas_standarisasi	Nilai standar per item
binjas_skor	Skor siswa per item per sesi
presensi	Kehadiran siswa (semua modul)
drive_config	Folder ID Drive per modul
settings	Konfigurasi global (key-value)

Role & Akses
Role	Guard	Akses
super_admin	requireSuperAdmin()	Full akses + manage users + settings
admin	requireAdmin()	Operasional harian (CRUD kegiatan, presensi)
(semua login)	requireLogin()	Baca data, kalender, profil

<pre><code>
🎨 Design System
Color Palette
Nama	Hex	Penggunaan
Army Green	#395917	Primary, sidebar active, tombol utama
Dark Green	#4C8C6A	Secondary
Soft Green	#A4C8AE	Accent
Soft Green Light	#E6EFEA	Hover bg, primary-light
Black	#121212	Sidebar background
Grey	#45515C	Text secondary
Purple	#595D75	Chart, badge Operasional
Tosca	#C1D8DA	Info color
Red	#8B1408	Danger
Warning	#C97C10	Warning
App BG	#edecea	Warm cream background
Typography
•	Heading: General Sans (self-hosted)
•	Body: General Sans / Inter / Plus Jakarta Sans
•	H1: 32px semibold | H2: 36px | H3: 24px | H4: 20px
</code></pre>

Keamanan
•	Semua form dilindungi CSRF token (csrfField() + verifyCsrf())
•	Output HTML selalu di-escape dengan fungsi e()
•	Query database menggunakan PDO Prepared Statements
•	Password di-hash dengan password_hash() (bcrypt)
•	File upload divalidasi ekstensi + ukuran sebelum disimpan
•	File storage/credentials/ tidak boleh di-commit ke Git



🔧 API Endpoints
Endpoint	Method	Keterangan
api/calendar_events.php	GET	Events kalender (FullCalendar + list format)
api/dashboard_stats.php	GET	Statistik dashboard (summary, trend, modul)
api/notification.php	GET	Notifikasi topbar (action=list|count)
api/search_siswa.php	GET	Live search siswa dengan context support

📋 .gitignore yang Disarankan
/vendor/
/storage/credentials/
/storage/uploads/
*.env
.DS_Store
Thumbs.db

📄 Lisensi
Proyek ini bersifat private/internal. Tidak untuk didistribusikan tanpa izin.
