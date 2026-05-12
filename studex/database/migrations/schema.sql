-- ============================================================
--  STUDEX — Student Index
--  Database Schema v1.0
--  Engine: MySQL 8.0+
--  Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS studex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studex;

-- ============================================================
-- 1. USERS (Super Admin & Admin)
-- ============================================================
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(100) NOT NULL,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,              -- bcrypt hash
    role        ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    avatar      VARCHAR(255) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    last_login  DATETIME DEFAULT NULL,
    created_by  INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 2. ANGKATAN (Batch / Cohort)
-- ============================================================
CREATE TABLE angkatan (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(100) NOT NULL,              -- e.g. "Angkatan 2024"
    kode        VARCHAR(20)  NOT NULL UNIQUE,       -- e.g. "ANG-2024"
    tahun       YEAR         NOT NULL,
    deskripsi   TEXT DEFAULT NULL,
    is_aktif    TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3. SISWA (Students)
-- ============================================================
CREATE TABLE siswa (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    angkatan_id     INT UNSIGNED NOT NULL,
    nis             VARCHAR(30)  NOT NULL UNIQUE,   -- Nomor Induk Siswa
    nama            VARCHAR(150) NOT NULL,
    jenis_kelamin   ENUM('L', 'P') NOT NULL,
    tempat_lahir    VARCHAR(100) DEFAULT NULL,
    tanggal_lahir   DATE DEFAULT NULL,
    alamat          TEXT DEFAULT NULL,
    no_hp           VARCHAR(20)  DEFAULT NULL,
    email           VARCHAR(100) DEFAULT NULL,
    foto            VARCHAR(255) DEFAULT NULL,
    status          ENUM('aktif', 'tidak_aktif', 'alumni') NOT NULL DEFAULT 'aktif',
    catatan         TEXT DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (angkatan_id) REFERENCES angkatan(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 4. RABUAN (Rapat Rutin)
-- ============================================================
CREATE TABLE rabuan (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    angkatan_id     INT UNSIGNED NOT NULL,
    judul           VARCHAR(200) NOT NULL,
    tanggal         DATE NOT NULL,
    waktu_mulai     TIME DEFAULT NULL,
    waktu_selesai   TIME DEFAULT NULL,
    lokasi          VARCHAR(200) DEFAULT NULL,
    agenda          TEXT DEFAULT NULL,
    status          ENUM('terjadwal', 'berlangsung', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'terjadwal',
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (angkatan_id) REFERENCES angkatan(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE rabuan_notulensi (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rabuan_id       INT UNSIGNED NOT NULL,
    nama_file       VARCHAR(255) NOT NULL,
    path_lokal      VARCHAR(500) DEFAULT NULL,      -- path sementara di server
    drive_file_id   VARCHAR(200) DEFAULT NULL,      -- Google Drive file ID
    drive_link      VARCHAR(500) DEFAULT NULL,      -- Google Drive shareable link
    drive_folder_id VARCHAR(200) DEFAULT NULL,
    ukuran_file     INT UNSIGNED DEFAULT NULL,      -- bytes
    uploaded_by     INT UNSIGNED NOT NULL,
    uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rabuan_id) REFERENCES rabuan(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 5. MENTORING
-- ============================================================
CREATE TABLE mentoring_sesi (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    angkatan_id     INT UNSIGNED NOT NULL,
    judul_materi    VARCHAR(200) NOT NULL,
    nama_tutor      VARCHAR(150) NOT NULL,
    tanggal         DATE NOT NULL,
    waktu_mulai     TIME DEFAULT NULL,
    waktu_selesai   TIME DEFAULT NULL,
    lokasi          VARCHAR(200) DEFAULT NULL,
    catatan_logistik TEXT DEFAULT NULL,
    status          ENUM('terjadwal', 'berlangsung', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'terjadwal',
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (angkatan_id) REFERENCES angkatan(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE mentoring_materi (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sesi_id         INT UNSIGNED NOT NULL,
    nama_file       VARCHAR(255) NOT NULL,
    tipe_file       VARCHAR(50)  DEFAULT NULL,      -- pdf, pptx, docx, dll
    path_lokal      VARCHAR(500) DEFAULT NULL,
    drive_file_id   VARCHAR(200) DEFAULT NULL,
    drive_link      VARCHAR(500) DEFAULT NULL,
    drive_folder_id VARCHAR(200) DEFAULT NULL,
    ukuran_file     INT UNSIGNED DEFAULT NULL,
    uploaded_by     INT UNSIGNED NOT NULL,
    uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id) REFERENCES mentoring_sesi(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 6. OPERASIONAL (Kegiatan Lapangan — 3 Fase)
-- ============================================================
CREATE TABLE operasional (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    angkatan_id     INT UNSIGNED NOT NULL,
    nama_kegiatan   VARCHAR(200) NOT NULL,
    lokasi          VARCHAR(200) DEFAULT NULL,
    tanggal_mulai   DATE NOT NULL,
    tanggal_selesai DATE DEFAULT NULL,
    deskripsi       TEXT DEFAULT NULL,
    fase            ENUM('pra', 'operasional', 'pasca') NOT NULL DEFAULT 'pra',
    status          ENUM('draft', 'aktif', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'draft',
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (angkatan_id) REFERENCES angkatan(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Fase Pra-Operasional
CREATE TABLE operasional_pra (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operasional_id      INT UNSIGNED NOT NULL UNIQUE,
    jumlah_peserta      INT UNSIGNED DEFAULT 0,
    kesiapan_peserta    TEXT DEFAULT NULL,           -- catatan kesiapan
    perbekalan_regu     TEXT DEFAULT NULL,           -- JSON atau text
    catatan_tambahan    TEXT DEFAULT NULL,
    dibuat_oleh         INT UNSIGNED NOT NULL,
    dibuat_pada         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (operasional_id) REFERENCES operasional(id) ON DELETE CASCADE,
    FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Peserta Operasional
CREATE TABLE operasional_peserta (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operasional_id  INT UNSIGNED NOT NULL,
    siswa_id        INT UNSIGNED NOT NULL,
    peran           VARCHAR(100) DEFAULT NULL,       -- e.g. "Regu 1", "Penanggung Jawab"
    catatan         VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_ops_siswa (operasional_id, siswa_id),
    FOREIGN KEY (operasional_id) REFERENCES operasional(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Daftar Perlengkapan
CREATE TABLE operasional_perlengkapan (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operasional_id  INT UNSIGNED NOT NULL,
    nama_alat       VARCHAR(200) NOT NULL,
    jenis           ENUM('pribadi', 'regu') NOT NULL DEFAULT 'regu',
    jumlah          INT UNSIGNED DEFAULT 1,
    satuan          VARCHAR(50)  DEFAULT 'pcs',
    keterangan      VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (operasional_id) REFERENCES operasional(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Laporan Hasil Operasional
CREATE TABLE operasional_laporan (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operasional_id  INT UNSIGNED NOT NULL,
    nama_file       VARCHAR(255) NOT NULL,
    path_lokal      VARCHAR(500) DEFAULT NULL,
    drive_file_id   VARCHAR(200) DEFAULT NULL,
    drive_link      VARCHAR(500) DEFAULT NULL,
    drive_folder_id VARCHAR(200) DEFAULT NULL,
    ukuran_file     INT UNSIGNED DEFAULT NULL,
    uploaded_by     INT UNSIGNED NOT NULL,
    uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operasional_id) REFERENCES operasional(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Checklist Pasca-Operasional
CREATE TABLE operasional_checklist (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operasional_id      INT UNSIGNED NOT NULL,
    perlengkapan_id     INT UNSIGNED NOT NULL,
    kondisi             ENUM('layak', 'tidak_layak', 'butuh_perbaikan') NOT NULL DEFAULT 'layak',
    catatan             VARCHAR(255) DEFAULT NULL,
    diperiksa_oleh      INT UNSIGNED NOT NULL,
    diperiksa_pada      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_checklist (operasional_id, perlengkapan_id),
    FOREIGN KEY (operasional_id) REFERENCES operasional(id) ON DELETE CASCADE,
    FOREIGN KEY (perlengkapan_id) REFERENCES operasional_perlengkapan(id) ON DELETE CASCADE,
    FOREIGN KEY (diperiksa_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 7. BINA JASMANI (Binjas)
-- ============================================================
CREATE TABLE binjas_sesi (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    angkatan_id     INT UNSIGNED NOT NULL,
    nama_sesi       VARCHAR(200) NOT NULL,
    tanggal         DATE NOT NULL,
    waktu_mulai     TIME DEFAULT NULL,
    waktu_selesai   TIME DEFAULT NULL,
    lokasi          VARCHAR(200) DEFAULT NULL,
    deskripsi       TEXT DEFAULT NULL,
    status          ENUM('terjadwal', 'berlangsung', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'terjadwal',
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (angkatan_id) REFERENCES angkatan(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Item/Komponen Penilaian Binjas
CREATE TABLE binjas_item (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_item       VARCHAR(150) NOT NULL,           -- e.g. "Push Up", "Lari 2.4 km"
    satuan          VARCHAR(50)  DEFAULT NULL,       -- e.g. "repetisi", "menit:detik"
    deskripsi       VARCHAR(255) DEFAULT NULL,
    urutan          INT UNSIGNED DEFAULT 0,
    is_aktif        TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Nilai Standarisasi per Item
CREATE TABLE binjas_standarisasi (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id         INT UNSIGNED NOT NULL,
    angkatan_id     INT UNSIGNED DEFAULT NULL,       -- NULL = berlaku untuk semua angkatan
    nilai_minimum   DECIMAL(8,2) NOT NULL DEFAULT 0,
    nilai_standar   DECIMAL(8,2) NOT NULL,           -- baseline/target
    nilai_maksimum  DECIMAL(8,2) DEFAULT NULL,
    keterangan      VARCHAR(255) DEFAULT NULL,
    berlaku_dari    DATE NOT NULL,
    berlaku_sampai  DATE DEFAULT NULL,
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES binjas_item(id) ON DELETE CASCADE,
    FOREIGN KEY (angkatan_id) REFERENCES angkatan(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Skor Siswa per Sesi
CREATE TABLE binjas_skor (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sesi_id         INT UNSIGNED NOT NULL,
    siswa_id        INT UNSIGNED NOT NULL,
    item_id         INT UNSIGNED NOT NULL,
    nilai           DECIMAL(8,2) NOT NULL,
    catatan         VARCHAR(255) DEFAULT NULL,
    diinput_oleh    INT UNSIGNED NOT NULL,
    diinput_pada    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_skor (sesi_id, siswa_id, item_id),
    FOREIGN KEY (sesi_id) REFERENCES binjas_sesi(id) ON DELETE CASCADE,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES binjas_item(id) ON DELETE CASCADE,
    FOREIGN KEY (diinput_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 8. PRESENSI (Kehadiran Terpusat)
-- ============================================================
CREATE TABLE presensi (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modul           ENUM('rabuan', 'mentoring', 'binjas') NOT NULL,
    referensi_id    INT UNSIGNED NOT NULL,           -- ID dari tabel modul (rabuan.id, mentoring_sesi.id, binjas_sesi.id)
    siswa_id        INT UNSIGNED NOT NULL,
    status          ENUM('hadir', 'izin', 'sakit', 'alpha') NOT NULL DEFAULT 'alpha',
    keterangan      VARCHAR(255) DEFAULT NULL,
    waktu_presensi  DATETIME DEFAULT NULL,
    dicatat_oleh    INT UNSIGNED NOT NULL,
    dicatat_pada    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_presensi (modul, referensi_id, siswa_id),
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 9. GOOGLE DRIVE CONFIGURATION
-- ============================================================
CREATE TABLE drive_config (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modul           ENUM('rabuan', 'mentoring', 'operasional', 'binjas', 'umum') NOT NULL UNIQUE,
    folder_id       VARCHAR(200) NOT NULL,           -- Google Drive Folder ID
    folder_name     VARCHAR(200) DEFAULT NULL,
    folder_url      VARCHAR(500) DEFAULT NULL,
    is_aktif        TINYINT(1) NOT NULL DEFAULT 1,
    updated_by      INT UNSIGNED DEFAULT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 10. SETTINGS (Pengaturan Sistem Global)
-- ============================================================
CREATE TABLE settings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunci       VARCHAR(100) NOT NULL UNIQUE,        -- setting key
    nilai       TEXT DEFAULT NULL,                   -- setting value
    label       VARCHAR(150) DEFAULT NULL,           -- human readable label
    deskripsi   VARCHAR(255) DEFAULT NULL,
    tipe        ENUM('text', 'number', 'boolean', 'json', 'file') NOT NULL DEFAULT 'text',
    updated_by  INT UNSIGNED DEFAULT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- INDEXES (Performance)
-- ============================================================
CREATE INDEX idx_siswa_angkatan    ON siswa(angkatan_id);
CREATE INDEX idx_siswa_status      ON siswa(status);
CREATE INDEX idx_rabuan_tanggal    ON rabuan(tanggal);
CREATE INDEX idx_rabuan_angkatan   ON rabuan(angkatan_id);
CREATE INDEX idx_mentoring_tanggal ON mentoring_sesi(tanggal);
CREATE INDEX idx_ops_fase          ON operasional(fase);
CREATE INDEX idx_ops_tanggal       ON operasional(tanggal_mulai);
CREATE INDEX idx_binjas_tanggal    ON binjas_sesi(tanggal);
CREATE INDEX idx_presensi_modul    ON presensi(modul, referensi_id);
CREATE INDEX idx_presensi_siswa    ON presensi(siswa_id);
CREATE INDEX idx_binjas_skor_sesi  ON binjas_skor(sesi_id);