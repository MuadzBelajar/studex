# STUDEX — Student Index

> Sistem web monitoring aktivitas siswa berbasis PHP Native + MySQL

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-v4-FF6384?style=flat-square&logo=chartdotjs&logoColor=white)
![License](https://img.shields.io/badge/License-Private-red?style=flat-square)

</div>

---

**STUDEX (Student Index)** adalah sistem internal berbasis web untuk memantau, mengelola, dan memvisualisasikan seluruh aktivitas pembinaan siswa secara terpadu — mulai dari rapat rutin, sesi mentoring, kegiatan lapangan, hingga pembinaan jasmani. Sistem ini bersifat **closed/internal**: hanya dapat diakses oleh Super Admin dan Admin yang ditunjuk, tanpa fitur registrasi publik. Tujuan pengembangannya adalah mendigitalisasi pencatatan pembinaan yang sebelumnya manual, sekaligus mengimplementasikan sistem web full-stack dengan manajemen dokumen terpusat via Google Drive, visualisasi data analitik, dan kontrol akses berbasis peran.

<!-- FOTO: Screenshot dashboard utama STUDEX — tampilkan statistik ringkasan (total siswa, kegiatan bulan ini) dan chart kehadiran -->

---

## 🛠️ Tech Stack

| Lapisan | Teknologi |
|---|---|
| **Backend** | PHP 8.1+ Native (tanpa framework) |
| **Frontend** | HTML5 Semantik, CSS3 (Variables/Flexbox/Grid), Vanilla JavaScript ES6 |
| **Database** | MySQL 8.0+ (19 tabel, Foreign Key, View) |
| **Library** | Chart.js v4 (CDN), FullCalendar v6 (CDN) |
| **File Storage** | Local `storage/uploads/` + Google Drive API v3 (Service Account) |
| **Dependency** | `google/apiclient ^2.0` via Composer |
| **Tools** | XAMPP / Laragon, Composer, Git, VS Code |

---

## 🚀 Panduan Instalasi & Menjalankan Aplikasi

Ikuti langkah berikut secara berurutan. Siapa pun yang belum pernah melihat kode ini dapat langsung menjalankannya.

### 1. Prasyarat Sistem

Pastikan sudah terinstal:
- **XAMPP** atau **Laragon** (PHP 8.1+ & MySQL sudah tercakup)
- **Composer** (untuk menginstal dependency PHP)
- **Git**

### 2. Clone Repository

Masuk ke direktori `htdocs` (XAMPP) atau `www` (Laragon), lalu jalankan:

```bash
git clone https://github.com/username/studex.git studex
cd studex
```

### 3. Install Dependency Composer

```bash
composer install
```

Ini akan menginstal `google/apiclient` ke folder `vendor/`. Pastikan Composer sudah terinstal sebelum menjalankan perintah ini.

### 4. Setup Database

Pastikan MySQL sudah berjalan, lalu import dua file SQL secara berurutan:

```bash
# Import schema (membuat database + 19 tabel)
mysql -u root -p < database/migrations/schema.sql

# Import data awal (akun default, angkatan, item binjas, dll.)
mysql -u root -p studex < database/migrations/seeder.sql
```

Atau lewat **phpMyAdmin**: buat database `studex`, lalu import kedua file di atas melalui tab **SQL**.

### 5. Konfigurasi Aplikasi

**Edit `config/app.php`** — sesuaikan BASE_URL dengan environment lokal:

```php
define('BASE_URL',  'http://localhost/studex'); // sesuaikan nama folder
define('TIMEZONE',  'Asia/Makassar');            // WITA — ubah jika perlu
```

**Edit `config/database.php`** — sesuaikan kredensial database:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'studex');
define('DB_USER', 'root');
define('DB_PASS', ''); // kosongkan jika default XAMPP/Laragon
```

### 6. Jalankan Aplikasi

Buka browser dan akses:

```
http://localhost/studex
```

### 7. Akun Login Default

| Role | Username | Password |
|---|---|---|
| Super Admin | `superadmin` | `studex@admin123` |

> ⚠️ **Segera ganti password** setelah login pertama melalui halaman **Profil**. Password default hanya untuk keperluan setup awal.

<!-- FOTO: Screenshot halaman login STUDEX -->

---

## 🔗 Setup Google Drive (Opsional)

Fitur upload dokumen ke Google Drive menggunakan **Service Account** — tidak memerlukan login manual pengguna. Jika tidak dikonfigurasi, file tetap tersimpan secara lokal di `storage/uploads/`.

### Langkah Setup

1. Buka [Google Cloud Console](https://console.cloud.google.com)
2. Buat project baru → aktifkan **Google Drive API**
3. Buat **Service Account** → download key dalam format JSON
4. Rename file key menjadi `service-account.json`, taruh di `storage/credentials/`
5. Di **Google Drive**, buat folder dengan struktur berikut lalu share ke email Service Account (role: **Editor**):

```
📁 STUDEX/
├── 📁 Rabuan/        ← folder notulensi rapat rutin
├── 📁 Mentoring/     ← folder materi mentoring
├── 📁 Operasional/   ← folder laporan kegiatan lapangan
└── 📁 Binjas/        ← folder dokumen binjas
```

6. Salin **Folder ID** masing-masing dari URL Drive (contoh: `https://drive.google.com/drive/folders/**INI_FOLDER_ID**`)
7. Login STUDEX sebagai Super Admin → **Pengaturan → Konfigurasi Drive** → paste Folder ID per modul
8. Klik **Test Koneksi Drive** untuk memverifikasi

> ⚠️ File `storage/credentials/service-account.json` sudah masuk `.gitignore` dan tidak boleh di-commit ke repository.

---

## 📝 Pemetaan Rubrik Penilaian

### 1. HTML Semantik & Aksesibilitas

Seluruh layout menggunakan tag HTML5 semantik secara konsisten: `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, dan `<footer>` diimplementasikan di `view/layouts/main.php` dan `view/partials/`. Hierarki heading (`h1`–`h4`) diatur rapi, setiap gambar memiliki `alt`, dan elemen interaktif dilengkapi label ARIA untuk mendukung aksesibilitas dan SEO.

<!-- FOTO: Screenshot halaman daftar siswa atau detail modul — tunjukkan struktur layout yang rapi -->

### 2. CSS Responsif & Design System

Antarmuka dibangun di atas sistem desain terpusat di `assets/css/variables.css` dengan CSS Custom Properties untuk seluruh warna, spacing, dan tipografi. Layout menggunakan Flexbox dan CSS Grid, serta `assets/css/responsive.css` berisi media query agar tampilannya nyaman di ponsel, tablet, maupun desktop. Tipografi menggunakan font **General Sans** yang di-host sendiri (`assets/fonts/`) untuk konsistensi lintas perangkat.

**Palet Warna Utama:**

| Nama | Hex | Penggunaan |
|---|---|---|
| Army Green | `#395917` | Primary — tombol utama, sidebar aktif |
| Dark Green | `#4C8C6A` | Secondary |
| Black | `#121212` | Background sidebar |
| App BG | `#edecea` | Background halaman (warm cream) |
| Red | `#8B1408` | Danger / hapus |
| Warning | `#C97C10` | Peringatan |

### 3. Operasi CRUD

CRUD diimplementasikan penuh di setiap modul. Contoh paling lengkap ada di modul **Siswa** (`modules/siswa/`):

| Operasi | File | Keterangan |
|---|---|---|
| **Create** | `create.php` | Form tambah siswa baru dengan validasi server-side |
| **Read** | `index.php` | Daftar siswa dengan filter, search, dan pagination |
| **Update** | `edit.php` | Form edit dengan data terisi otomatis (*pre-filled*) |
| **Delete** | `delete.php` | Hapus dengan konfirmasi modal, cegah hapus jika ada relasi |
| **Detail** | `detail.php` | Halaman profil lengkap siswa + riwayat kegiatan |
| **Import** | `import.php` | Bulk insert siswa via upload file CSV |
| **Export** | `export.php` | Download data siswa ke format CSV |

Pola CRUD yang sama juga diterapkan di modul Angkatan, Users, Rabuan, Mentoring, Operasional, dan Binjas.

<!-- FOTO: Screenshot form tambah/edit siswa — tunjukkan form pre-filled saat edit -->

<!-- FOTO: Screenshot halaman daftar siswa dengan filter dan pagination -->

> **Mengapa CRUD Penting dalam Aplikasi Web?**
> CRUD (Create, Read, Update, Delete) adalah fondasi dari hampir semua aplikasi web dinamis. Tanpa CRUD, aplikasi hanya bisa menampilkan data statis — tidak bisa menerima, mengolah, atau memperbarui informasi baru. Dalam konteks STUDEX, CRUD memastikan data siswa, presensi, hasil Binjas, dan catatan kegiatan selalu akurat, bisa diperbarui sesuai kondisi terkini, dan dapat dihapus dengan aman jika terjadi kesalahan entri. Ini merepresentasikan interaksi sistem informasi pada dunia pembinaan nyata.

### 4. Integrasi Database

- Koneksi via **PDO** dengan singleton pattern di `config/database.php` — satu koneksi untuk seluruh request.
- `PDO::ATTR_EMULATE_PREPARES => false` diaktifkan untuk keamanan maksimal terhadap SQL Injection.
- Helper functions (`db()`, `fetchOne`, `fetchAll`, `execute`) tersentralisasi agar query konsisten di seluruh modul.
- **19 tabel** dengan **foreign key constraints** menjaga integritas relasi antar entitas (siswa ↔ angkatan, presensi ↔ sesi kegiatan, dll.).
- Setiap tabel memiliki index yang tepat (`idx_status`, `idx_angkatan_id`, dst.) untuk performa query.

### 5. Visualisasi Data & Dashboard

Dashboard (`modules/dashboard/`) menampilkan data analitik secara real-time menggunakan **Chart.js v4**:

- **Bar chart** tren kehadiran 7 sesi Rabuan terakhir (hadir vs. alpha)
- **Radar chart** profil skor Binjas individu siswa per item (Push Up, Sit Up, Pull Up, dll.)
- **Statistik ringkasan**: total siswa aktif, angkatan aktif, kegiatan bulan ini per modul
- Data chart dimuat via endpoint **AJAX** (`api/dashboard_stats.php`) tanpa reload halaman

<!-- FOTO: Screenshot dashboard dengan chart kehadiran dan statistik ringkasan -->

### 6. Kalender Terpadu

Modul Jadwal (`modules/jadwal/`) menggunakan **FullCalendar v6** untuk menampilkan semua kegiatan dari seluruh modul (Rabuan, Mentoring, Operasional, Binjas) dalam satu kalender interaktif. Data event dimuat via endpoint `api/calendar_events.php` dalam format JSON yang kompatibel dengan FullCalendar.

<!-- FOTO: Screenshot halaman kalender terpadu -->

### 7. Autentikasi & Role-Based Access Control (RBAC)

Autentikasi dikelola oleh class `core/Auth.php` dengan session management di `config/session.php`.

| Role | Guard | Akses |
|---|---|---|
| `super_admin` | `requireSuperAdmin()` | Full akses: CRUD users, pengaturan sistem, konfigurasi Google Drive |
| `admin` | `requireAdmin()` | Operasional harian: CRUD kegiatan, input presensi, upload dokumen |
| *(semua login)* | `requireLogin()` | Baca data, kalender, profil sendiri |

Setiap halaman memanggil guard function di baris pertama. Pengguna yang belum login atau mengakses halaman di luar haknya langsung diarahkan ke halaman login.

**Fitur keamanan session tambahan:**
- `session_regenerate_id(true)` saat login untuk mencegah *session fixation*
- Cookie session dikonfigurasi dengan `HttpOnly` dan `SameSite=Strict`
- Timeout sesi otomatis setelah 480 menit tidak aktif (dapat diubah di Pengaturan)

### 8. Keamanan Aplikasi

| Mekanisme | Implementasi |
|---|---|
| **SQL Injection** | PDO Prepared Statements di seluruh query |
| **XSS** | Fungsi `e()` (`htmlspecialchars`) wajib dipakai di setiap output HTML |
| **CSRF** | Token CSRF di setiap form (`csrfField()` + `verifyCsrf()`) |
| **Password** | `password_hash()` bcrypt cost 12 saat simpan, `password_verify()` saat login |
| **Upload** | Validasi ekstensi, ukuran (maks. 10MB), dan MIME type di `core/FileUpload.php` |
| **Credentials** | `storage/credentials/` masuk `.gitignore`, tidak pernah di-commit |

### 9. Validasi Input (Dua Lapis)

**Sisi klien:** Atribut HTML5 (`required`, `type`, `min`, `max`, `pattern`) mencegah pengiriman data kosong atau format salah.

**Sisi server:** Setiap input disanitasi via `sanitize()` dan `sanitizeInt()` dari `core/Helpers.php` sebelum diproses. File upload divalidasi ulang ekstensi dan ukurannya di sisi server. Semua nilai dari pengguna diikat ke prepared statement — tidak ada interpolasi langsung ke SQL.

### 10. Modul Lengkap yang Tersedia

| Modul | Fitur Utama |
|---|---|
| **Rabuan** | CRUD sesi rapat rutin + upload notulensi PDF ke Google Drive + presensi |
| **Mentoring** | CRUD sesi bimbingan + upload materi ke Google Drive + presensi |
| **Operasional** | Kegiatan lapangan dengan 3 fase: Pra (perencanaan + peserta + perlengkapan) → Ops (update status + upload laporan) → Pasca (checklist kondisi alat) |
| **Binjas** | CRUD sesi pembinaan jasmani + input skor per item + standarisasi nilai + radar chart per siswa + presensi |
| **Jadwal** | Kalender terpadu semua kegiatan menggunakan FullCalendar v6 |
| **Presensi** | Rekap kehadiran terpusat per modul + angkatan + export CSV |
| **Siswa** | CRUD master data siswa + import bulk via CSV + export CSV |
| **Angkatan** | CRUD data batch/angkatan |
| **Users** | CRUD akun admin (Super Admin only) |
| **Pengaturan** | Konfigurasi global aplikasi + konfigurasi Google Drive per modul |

<!-- FOTO: Screenshot modul Operasional — tampilkan tampilan 3 fase (Pra/Ops/Pasca) -->

<!-- FOTO: Screenshot modul Binjas detail sesi — tampilkan radar chart skor siswa -->

---

## 📁 Struktur Direktori & Alur Data

```
studex/
├── .htaccess                        # URL rewrite & proteksi direktori
├── index.php                        # Entry point → redirect ke dashboard
├── composer.json
│
├── config/
│   ├── app.php                      # BASE_URL, konstanta, timezone, role
│   ├── database.php                 # PDO singleton + fungsi db()
│   ├── session.php                  # Auth guards, CSRF, flash message
│   └── google_drive.php             # Path credentials + panduan setup
│
├── core/
│   ├── Auth.php                     # login(), logout(), hashPassword()
│   ├── Helpers.php                  # 25+ fungsi bantu: e(), sanitize(), formatTanggal(), dll.
│   ├── GoogleDrive.php              # upload(), delete(), testConnection()
│   ├── FileUpload.php               # handle(), handleWithDrive()
│   └── Router.php                   # dispatch(), json(), requirePost()
│
├── database/
│   └── migrations/
│       ├── schema.sql               # 19 tabel lengkap dengan FK & index
│       └── seeder.sql               # Akun default + data contoh
│
├── assets/
│   ├── css/
│   │   ├── variables.css            # Design tokens (warna, spacing, tipografi)
│   │   ├── layout.css               # Grid & layout utama
│   │   ├── components.css           # Tombol, kartu, badge, tabel
│   │   ├── responsive.css           # Media queries semua breakpoint
│   │   └── ...                      # reset, auth, charts, modal
│   ├── js/
│   │   ├── app.js                   # Inisialisasi global
│   │   ├── charts.js                # Konfigurasi Chart.js
│   │   ├── calendar.js              # Konfigurasi FullCalendar
│   │   ├── table.js                 # Filter & search tabel dinamis
│   │   ├── presensi.js              # Input presensi interaktif
│   │   └── upload.js                # Drag-drop upload & progress
│   └── fonts/                       # General Sans (self-hosted)
│
├── view/
│   ├── layouts/
│   │   ├── main.php                 # Layout utama (sidebar + topbar + konten)
│   │   ├── auth.php                 # Layout halaman login
│   │   └── print.php                # Layout khusus cetak
│   └── partials/
│       ├── sidebar.php              # Navigasi sidebar dinamis per role
│       ├── topbar.php               # Header + notifikasi + profil
│       ├── breadcrumb.php           # Navigasi breadcrumb
│       ├── flash_message.php        # Toast notifikasi sukses/error
│       ├── modal_confirm.php        # Modal konfirmasi hapus
│       └── pagination.php           # Komponen paginasi
│
├── modules/                         # Satu folder per fitur
│   ├── auth/                        # login, logout, forgot_password
│   ├── dashboard/                   # Dasbor + endpoint statistik AJAX
│   ├── siswa/                       # CRUD + import CSV + export
│   ├── angkatan/                    # CRUD batch/angkatan
│   ├── users/                       # CRUD akun (Super Admin only)
│   ├── rabuan/                      # CRUD + notulensi + presensi
│   ├── mentoring/                   # CRUD + materi + presensi
│   ├── operasional/                 # CRUD + pra/ + ops/ + pasca/
│   ├── binjas/                      # CRUD + skor + standarisasi + radar chart
│   ├── jadwal/                      # Kalender FullCalendar
│   ├── presensi/                    # Input + rekap + export CSV
│   └── settings/                    # Pengaturan + konfigurasi Drive
│
├── api/
│   ├── calendar_events.php          # Events JSON untuk FullCalendar
│   ├── dashboard_stats.php          # Statistik AJAX untuk dashboard
│   ├── notification.php             # Notifikasi topbar (list & count)
│   └── search_siswa.php             # Live search siswa
│
└── storage/
    ├── credentials/                 # service-account.json (tidak di-commit)
    ├── uploads/                     # File upload lokal (fallback Google Drive)
    └── logs/                        # php-error.log
```

**Alur Data — Contoh: Upload Notulensi Rabuan**

```
Admin klik "Upload Notulensi" di detail sesi rabuan
    → POST ke modules/rabuan/upload_notulensi.php
        → requireAdmin(): cek login & role
        → verifyCsrf(): validasi token form
        → FileUpload::handle(): validasi ekstensi, ukuran, MIME
        → Jika Google Drive aktif:
            → GoogleDrive::upload(): kirim file ke folder Drive
            → Simpan drive_file_id + drive_link ke tabel rabuan_notulensi
        → Jika tidak aktif:
            → Simpan file ke storage/uploads/
            → Simpan path lokal ke tabel rabuan_notulensi
        → Flash message "Upload berhasil"
        → Redirect ke detail sesi rabuan
```

---

## 🗄️ Database — 19 Tabel

| Tabel | Keterangan |
|---|---|
| `users` | Akun admin (`role`: super_admin / admin) |
| `angkatan` | Data batch/angkatan siswa |
| `siswa` | Master data siswa, FK → `angkatan` |
| `rabuan` | Sesi rapat rutin |
| `rabuan_notulensi` | File notulensi PDF (path lokal / Drive link) |
| `mentoring_sesi` | Sesi bimbingan |
| `mentoring_materi` | File materi (path lokal / Drive link) |
| `operasional` | Kegiatan lapangan (header, 3 fase) |
| `operasional_pra` | Data perencanaan — 1:1 dengan operasional |
| `operasional_peserta` | Daftar peserta kegiatan |
| `operasional_perlengkapan` | Daftar perlengkapan pribadi & regu |
| `operasional_laporan` | File laporan PDF |
| `operasional_checklist` | Checklist kondisi alat pasca kegiatan |
| `binjas_sesi` | Sesi pembinaan jasmani |
| `binjas_item` | Item penilaian (Push Up, Sit Up, Lari, dll.) |
| `binjas_standarisasi` | Nilai standar per item (minimum / standar / maksimum) |
| `binjas_skor` | Skor siswa per item per sesi |
| `presensi` | Rekap kehadiran semua modul dalam satu tabel |
| `drive_config` | Folder ID Google Drive per modul |
| `settings` | Konfigurasi global (key-value) |

---

## ⚠️ Known Issues & Rencana Pengembangan

**Batasan yang diketahui:**

- **Upload Synchronous:** Proses upload PDF besar ke Google Drive memblokir UI sejenak — belum ada progress bar yang presisi saat transfer berlangsung.
- **Tidak Ada Registrasi Publik:** Akun baru hanya bisa dibuat oleh Super Admin. Ini disengaja sesuai sifat sistem yang closed/internal, namun belum ada alur onboarding admin baru yang terdokumentasi.
- **Rekap Presensi Lintas Modul:** Saat ini rekap presensi harus difilter per modul secara manual — belum ada tampilan rekap gabungan semua modul dalam satu view.

**Rencana pengembangan fase berikutnya:**

- Upload asinkron dengan progress bar menggunakan JavaScript Fetch API + chunked upload.
- Notifikasi otomatis via Email (PHPMailer) atau WhatsApp jika ada kegiatan mendadak atau evaluasi mingguan.
- Export laporan lengkap (presensi bulanan, rekap Binjas, jadwal) ke PDF (Dompdf) atau Excel (PhpSpreadsheet).
- Refactoring bertahap menuju arsitektur MVC yang lebih solid untuk kemudahan pengujian dan skalabilitas jangka panjang.

---

## 🔒 .gitignore yang Disarankan

```gitignore
/vendor/
/storage/credentials/
/storage/uploads/
/storage/logs/
*.env
.DS_Store
Thumbs.db
```

---

*STUDEX — Proyek internal untuk tujuan monitoring dan pembelajaran. Tidak untuk didistribusikan tanpa izin.*
