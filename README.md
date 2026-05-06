# STUDEX — Student Index

> Sistem web monitoring aktivitas siswa berbasis PHP Native + MySQL

<br>

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-v4-FF6384?style=flat-square&logo=chartdotjs&logoColor=white)
![License](https://img.shields.io/badge/License-Private-red?style=flat-square)

</div>

<br>

## Tentang STUDEX

**STUDEX (Student Index)** adalah sistem internal berbasis web untuk memantau, mengelola, dan memvisualisasikan seluruh aktivitas siswa. Sistem ini bersifat **closed/internal** — hanya dapat diakses oleh Super Admin dan Admin yang ditunjuk. Tidak ada registrasi publik.

### Aktivitas yang Dikelola

| Modul | Keterangan |
|---|---|
| 📋 **Rabuan** | Rapat rutin mingguan + upload notulensi PDF ke Google Drive |
| 📚 **Mentoring** | Sesi bimbingan + upload materi ke Google Drive |
| 🗂️ **Operasional** | Kegiatan lapangan dengan 3 fase (Pra → Ops → Pasca) |
| 💪 **Binjas** | Pembinaan fisik + penilaian skor individu + radar chart |
| 📅 **Jadwal** | Kalender terpadu semua kegiatan (FullCalendar) |
| ✅ **Presensi** | Rekap kehadiran terpusat + export CSV |

<br>

## Tech Stack

| Layer | Teknologi |
|---|---|
| Frontend | HTML + CSS Native + JavaScript Vanilla |
| Backend | PHP 8.1+ (Native, tanpa framework) |
| Database | MySQL 8.0+ |
| Local Dev | XAMPP / Laragon |
| Charts | Chart.js v4 (CDN) |
| Calendar | FullCalendar v6 (CDN) |
| File Storage | Local + Google Drive API (Service Account) |
| Dependency | `google/apiclient:^2.0` via Composer |

<br>

## Instalasi

### 1. Clone / Extract

```bash
git clone https://github.com/username/studex.git
# atau extract ZIP ke htdocs/studex/
```

### 2. Import Database

```bash
# Buat database
mysql -u root -p -e "CREATE DATABASE studex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema (19 tabel)
mysql -u root -p studex < database/schema.sql

# Import data awal + akun default
mysql -u root -p studex < database/seeder.sql
```

### 3. Konfigurasi Aplikasi

Edit `config/app.php`:

```php
define('BASE_URL', 'http://localhost/studex');  // sesuaikan URL lokal
define('APP_NAME', 'STUDEX');
define('TIMEZONE', 'Asia/Makassar');             // WITA
```

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'studex');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Install Composer Dependencies

```bash
cd studex/
composer install
```

### 5. Akses Aplikasi

```
URL   : http://localhost/studex
User  : superadmin
Pass  : studex@admin123
```

> ⚠️ **Segera ganti password** setelah login pertama melalui halaman Profil.

<br>

## Setup Google Drive (Opsional)

Fitur upload file ke Google Drive menggunakan Service Account.

### Langkah Setup

1. Buka [Google Cloud Console](https://console.cloud.google.com)
2. Buat project baru → aktifkan **Google Drive API**
3. Buat **Service Account** → download JSON key
4. Rename file menjadi `service-account.json`
5. Taruh di folder `storage/credentials/`
6. **Share** folder Google Drive target ke email service account (role: Editor)
7. Buka **Pengaturan → Konfigurasi Drive** → isi Folder ID per modul
8. Klik **Test Koneksi Drive** untuk verifikasi

### Struktur Folder Drive yang Disarankan

```
📁 STUDEX/
├── 📁 Rabuan/        ← folder_id untuk modul rabuan
├── 📁 Mentoring/     ← folder_id untuk modul mentoring
├── 📁 Operasional/   ← folder_id untuk modul operasional
└── 📁 Binjas/        ← folder_id untuk modul binjas
```

> Jika Google Drive tidak dikonfigurasi, file tetap tersimpan di `storage/uploads/` lokal.

<br>

## Struktur Direktori

```
studex/
├── .htaccess
├── index.php                    # Entry point → redirect ke dashboard
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
│   ├── siswa/                   # CRUD + import CSV + export
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
```

<br>

## Database — 19 Tabel

| Tabel | Keterangan |
|---|---|
| `users` | Akun admin (`role`: super_admin / admin) |
| `angkatan` | Data batch/angkatan |
| `siswa` | Data siswa, FK `angkatan_id` |
| `rabuan` | Sesi rapat rutin |
| `rabuan_notulensi` | File notulensi PDF (Drive link) |
| `mentoring_sesi` | Sesi mentoring |
| `mentoring_materi` | File materi (Drive link) |
| `operasional` | Kegiatan lapangan (3 fase) |
| `operasional_pra` | Data perencanaan (1:1 dengan operasional) |
| `operasional_peserta` | Daftar peserta kegiatan |
| `operasional_perlengkapan` | Daftar perlengkapan pribadi & regu |
| `operasional_laporan` | File laporan PDF (Drive link) |
| `operasional_checklist` | Checklist kondisi alat pasca kegiatan |
| `binjas_sesi` | Sesi pembinaan fisik |
| `binjas_item` | Item penilaian (Push Up, Lari, dll) |
| `binjas_standarisasi` | Nilai standar per item |
| `binjas_skor` | Skor siswa per item per sesi |
| `presensi` | Kehadiran siswa semua modul |
| `drive_config` | Folder ID Google Drive per modul |
| `settings` | Konfigurasi global (key-value) |

<br>

## Role & Akses

| Role | Guard PHP | Akses |
|---|---|---|
| `super_admin` | `requireSuperAdmin()` | Full akses + manage users + settings + drive config |
| `admin` | `requireAdmin()` | Operasional harian — CRUD kegiatan, input presensi |
| *(semua login)* | `requireLogin()` | Baca data, kalender, profil |

<br>

## API Endpoints

| Endpoint | Method | Keterangan |
|---|---|---|
| `api/calendar_events.php` | GET | Events kalender (FullCalendar format) |
| `api/dashboard_stats.php` | GET | Statistik dashboard (summary, trend, modul) |
| `api/notification.php` | GET | Notifikasi topbar (`action=list\|count`) |
| `api/search_siswa.php` | GET | Live search siswa |

<br>

## Design System

### Color Palette

| Nama | Hex | Penggunaan |
|---|---|---|
| Army Green | `#395917` | Primary — tombol utama, sidebar active |
| Dark Green | `#4C8C6A` | Secondary |
| Soft Green | `#A4C8AE` | Accent |
| Soft Green Light | `#E6EFEA` | Hover background |
| Black | `#121212` | Sidebar background |
| Grey | `#45515C` | Text secondary |
| Purple | `#595D75` | Chart, badge |
| Tosca | `#C1D8DA` | Info color |
| Red | `#8B1408` | Danger |
| Warning | `#C97C10` | Warning |
| App BG | `#edecea` | Warm cream background |

### Typography

- **Heading:** General Sans *(self-hosted dari `assets/fonts/GeneralSans/`)*
- **Body:** General Sans / Inter / Plus Jakarta Sans
- H1: 32px semibold · H2: 36px · H3: 24px · H4: 20px

<br>

## Keamanan

-  Semua form dilindungi **CSRF token** (`csrfField()` + `verifyCsrf()`)
-  Output HTML selalu di-escape dengan `e()`
-  Query database menggunakan **PDO Prepared Statements**
-  Password di-hash dengan `password_hash()` (bcrypt cost 12)
-  File upload divalidasi ekstensi + ukuran + MIME type
-  `storage/credentials/` **tidak di-commit** ke Git

<br>

## .gitignore yang Disarankan

```gitignore
/vendor/
/storage/credentials/
/storage/uploads/
*.env
.DS_Store
Thumbs.db
```

<br>

## Lisensi

Proyek ini bersifat **private/internal**. Tidak untuk didistribusikan tanpa izin.

---

<div align="center">
  <sub>STUDEX — Student Index &copy; 2025</sub>
</div>
