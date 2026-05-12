-- ============================================================
--  STUDEX — Student Index
--  Seeder v1.0 — Data Awal
--  Jalankan SETELAH schema.sql
-- ============================================================

USE studex;

-- ============================================================
-- 1. SUPER ADMIN DEFAULT
-- Password: studex@admin123 (bcrypt hash)
-- WAJIB diganti setelah login pertama!
-- ============================================================
INSERT INTO users (nama, username, email, password, role, is_active) VALUES
(
    'Super Administrator',
    'superadmin',
    'superadmin@studex.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: studex@admin123
    'super_admin',
    1
);

-- ============================================================
-- 2. ANGKATAN CONTOH
-- ============================================================
INSERT INTO angkatan (nama, kode, tahun, deskripsi, is_aktif) VALUES
('Angkatan 2023', 'ANG-2023', 2023, 'Batch pertama STUDEX', 0),
('Angkatan 2024', 'ANG-2024', 2024, 'Batch aktif tahun 2024', 1),
('Angkatan 2025', 'ANG-2025', 2025, 'Batch aktif tahun 2025', 1);

-- ============================================================
-- 3. SISWA CONTOH (Angkatan 2024 & 2025)
-- ============================================================
INSERT INTO siswa (angkatan_id, nis, nama, jenis_kelamin, status) VALUES
-- Angkatan 2024 (id=2)
(2, '2024001', 'Ahmad Fauzi Ramadhan',   'L', 'aktif'),
(2, '2024002', 'Siti Nurhaliza',          'P', 'aktif'),
(2, '2024003', 'Budi Santoso',            'L', 'aktif'),
(2, '2024004', 'Dewi Rahayu',             'P', 'aktif'),
(2, '2024005', 'Eko Prasetyo',            'L', 'aktif'),
(2, '2024006', 'Fitri Handayani',         'P', 'aktif'),
(2, '2024007', 'Galih Kusuma',            'L', 'aktif'),
(2, '2024008', 'Hani Safitri',            'P', 'aktif'),
(2, '2024009', 'Irwan Setiawan',          'L', 'aktif'),
(2, '2024010', 'Jeni Puspitasari',        'P', 'aktif'),
-- Angkatan 2025 (id=3)
(3, '2025001', 'Kevin Alfarizi',          'L', 'aktif'),
(3, '2025002', 'Laila Mardiyah',          'P', 'aktif'),
(3, '2025003', 'Muhammad Rizki',          'L', 'aktif'),
(3, '2025004', 'Nanda Permata',           'P', 'aktif'),
(3, '2025005', 'Oscar Firmansyah',        'L', 'aktif');

-- ============================================================
-- 4. ITEM PENILAIAN BINJAS
-- ============================================================
INSERT INTO binjas_item (nama_item, satuan, deskripsi, urutan) VALUES
('Push Up',         'repetisi',    'Jumlah push up dalam 1 menit',          1),
('Sit Up',          'repetisi',    'Jumlah sit up dalam 1 menit',           2),
('Pull Up',         'repetisi',    'Jumlah pull up maksimal',               3),
('Lari 2.4 km',     'menit:detik', 'Waktu tempuh lari 2.4 km',             4),
('Shuttle Run',     'detik',       'Waktu tempuh shuttle run 10x5m',        5),
('Standing Broad Jump', 'cm',      'Jarak lompatan jauh tanpa awalan',      6);

-- ============================================================
-- 5. NILAI STANDARISASI BINJAS (berlaku untuk semua angkatan)
-- ============================================================
INSERT INTO binjas_standarisasi (item_id, angkatan_id, nilai_minimum, nilai_standar, nilai_maksimum, berlaku_dari, created_by) VALUES
(1, NULL, 15,  35,  60,  '2024-01-01', 1),   -- Push Up
(2, NULL, 20,  40,  70,  '2024-01-01', 1),   -- Sit Up
(3, NULL, 3,   8,   20,  '2024-01-01', 1),   -- Pull Up
(4, NULL, 9,   12,  18,  '2024-01-01', 1),   -- Lari 2.4km (menit, lower=better)
(5, NULL, 18,  14,  11,  '2024-01-01', 1),   -- Shuttle Run (detik, lower=better)
(6, NULL, 150, 200, 250, '2024-01-01', 1);   -- Standing Broad Jump (cm)

-- ============================================================
-- 6. GOOGLE DRIVE CONFIG (placeholder — diisi oleh Super Admin)
-- ============================================================
INSERT INTO drive_config (modul, folder_id, folder_name, is_aktif) VALUES
('rabuan',       '', 'STUDEX/Notulensi Rabuan',   0),
('mentoring',    '', 'STUDEX/Materi Mentoring',   0),
('operasional',  '', 'STUDEX/Laporan Operasional',0),
('binjas',       '', 'STUDEX/Dokumen Binjas',     0),
('umum',         '', 'STUDEX/Umum',               0);

-- ============================================================
-- 7. SETTINGS APLIKASI
-- ============================================================
INSERT INTO settings (kunci, nilai, label, deskripsi, tipe) VALUES
('app_name',            'STUDEX',                   'Nama Aplikasi',          'Nama yang ditampilkan di header & title', 'text'),
('app_tagline',         'Student Index',            'Tagline Aplikasi',       'Sub-judul aplikasi',                     'text'),
('app_logo',            '',                         'Logo Aplikasi',          'Path file logo',                         'file'),
('max_upload_size',     '10',                       'Maks. Upload (MB)',      'Ukuran maksimal file upload dalam MB',   'number'),
('allowed_extensions',  'pdf,docx,pptx,xlsx,jpg,png','Ekstensi File Diizinkan','Ekstensi yang boleh diupload',          'text'),
('google_drive_enabled','0',                        'Google Drive Aktif',     'Status integrasi Google Drive',          'boolean'),
('google_credentials_path', '',                     'Path Credentials Drive', 'Path ke file credentials.json Google',   'text'),
('session_lifetime',    '480',                      'Session Lifetime (menit)','Durasi sesi login aktif',               'number'),
('timezone',            'Asia/Makassar',            'Timezone',               'Timezone aplikasi (WIB/WITA/WIT)',       'text'),
('date_format',         'd M Y',                    'Format Tanggal',         'Format tampilan tanggal',                 'text');