-- =====================================================================
-- 000_base_mikro_schema.sql
-- Skema dasar database untuk menjalankan Dashboard Mikro dari nol.
--
-- Kenapa file ini ada: proyek ini sebelumnya hanya berisi file migrasi
-- INCREMENTAL (001-006, ALTER TABLE) yang mengasumsikan tabel `users`
-- dan `nasabah_mikro` SUDAH ada sebelumnya. Tidak ada file yang benar-benar
-- membuat database dari kosong. File ini mengisi kekosongan itu: membuat
-- database + tabel `users` + tabel `nasabah_mikro` (sudah termasuk semua
-- kolom dari 002/003/006) + tabel `angsuran_mikro`, plus 3 akun contoh
-- (admin/mikro/atasan) supaya Anda langsung bisa login dan mencoba semua
-- fitur Mikro.
--
-- CARA PAKAI:
-- 1. Kalau database `database_pendataan-bsi` BELUM ada sama sekali:
--      jalankan file ini SAJA (tidak perlu 001-006 lagi, kecuali Anda
--      juga memakai dashboard Konsumer -> lihat catatan di bagian bawah).
-- 2. Kalau database SUDAH ada (misalnya sudah pernah menjalankan versi
--    lama proyek ini): JANGAN jalankan file ini. Pakai 001-006 seperti
--    biasa (sesuai urutan nomor), karena file ini akan bentrok dengan
--    tabel yang sudah ada (CREATE TABLE tanpa IF NOT EXISTS untuk users
--    & nasabah_mikro, supaya tidak diam-diam menimpa struktur berbeda).
-- 3. Password 3 akun contoh di bawah semuanya: "password123"
--    (sudah di-hash dengan bcrypt/password_hash PHP -- WAJIB diganti
--    sebelum dipakai di production).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `database_pendataan-bsi`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `database_pendataan-bsi`;

-- ---------------------------------------------------------------------
-- Tabel users
-- Dipakai lintas dashboard (login, OTP, role, preferensi notifikasi).
-- Kolom otp_* & notif_* sudah termasuk hasil migrasi 001 & 004 supaya
-- login + OTP + halaman Pengaturan langsung berfungsi tanpa migrasi
-- tambahan.
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20) NULL,
    email VARCHAR(150) NULL,

    -- Role menentukan dashboard mana yang bisa diakses (lihat
    -- helpers/dashboard-redirect.php): admin, atasan, konsumer, mikro,
    -- pawning/gadai.
    role VARCHAR(20) NOT NULL DEFAULT 'mikro',

    -- OTP login/registrasi/lupa-password (helpers/otp.php)
    otp_code VARCHAR(255) NULL,
    otp_expiry DATETIME NULL,
    otp_created_at DATETIME NULL,
    otp_purpose VARCHAR(30) NULL,
    otp_channel VARCHAR(10) NULL,
    otp_attempts INT NOT NULL DEFAULT 0,

    status ENUM('unverified', 'verified') NOT NULL DEFAULT 'unverified',

    -- Preferensi notifikasi (mikro-pengaturan.php / konsumer-pengaturan.php)
    notif_email TINYINT(1) NOT NULL DEFAULT 1,
    notif_wa TINYINT(1) NOT NULL DEFAULT 0,
    notif_jatuh_tempo TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_users_username (username),
    UNIQUE KEY uniq_users_email (email)
);

-- ---------------------------------------------------------------------
-- Tabel nasabah_mikro
-- Sudah menggabungkan struktur dasar + sql/002 (KPI/cabang/AO/status) +
-- sql/003 (dokumen) + sql/006 (survey + tenor) supaya semua halaman
-- Mikro (dashboard, data, tambah, dokumen, survey, monitoring, analisa,
-- laporan, kolektibilitas, outstanding, jatuh tempo, target AO, sebaran
-- wilayah, export) langsung berfungsi tanpa migrasi tambahan.
-- ---------------------------------------------------------------------
CREATE TABLE `nasabah_mikro` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- petugas yang menginput (FK ke users.id)

    -- Data pemilik & usaha
    nama_pemilik VARCHAR(150) NOT NULL,
    nama_usaha VARCHAR(150) NOT NULL,
    jenis_sektor VARCHAR(100) NULL,
    omzet_bulanan DECIMAL(15,2) NOT NULL DEFAULT 0,
    lama_usaha_tahun INT NOT NULL DEFAULT 0,
    alamat_usaha VARCHAR(255) NULL,
    alamat VARCHAR(255) NULL,
    no_telepon VARCHAR(20) NULL,

    -- sql/002: KPI / cabang / AO / status / plafon / outstanding / kolektibilitas
    cabang VARCHAR(100) NOT NULL DEFAULT 'BSI KCP Tambun',
    nama_ao VARCHAR(150) NULL,
    status_pengajuan ENUM('Approval', 'Aktif', 'Ditolak') NOT NULL DEFAULT 'Approval',
    plafon_pembiayaan DECIMAL(15,2) NOT NULL DEFAULT 0,
    outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
    target_outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
    kolektibilitas TINYINT NOT NULL DEFAULT 1,

    -- sql/003: kelengkapan dokumen & jatuh tempo
    dok_ktp TINYINT(1) NOT NULL DEFAULT 0,
    dok_kk TINYINT(1) NOT NULL DEFAULT 0,
    dok_izin_usaha TINYINT(1) NOT NULL DEFAULT 0,
    dok_foto_usaha TINYINT(1) NOT NULL DEFAULT 0,
    tanggal_jatuh_tempo DATE NULL,

    -- sql/006: survey lapangan & tenor (untuk generate jadwal angsuran)
    survey_status ENUM('belum', 'terjadwal', 'selesai') NOT NULL DEFAULT 'belum',
    survey_tanggal DATE NULL,
    survey_petugas VARCHAR(150) NULL,
    survey_skor TINYINT NULL,
    survey_catatan TEXT NULL,
    tenor_bulan INT NOT NULL DEFAULT 12,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_nasabah_mikro_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_mikro_status_pengajuan ON nasabah_mikro (status_pengajuan);
CREATE INDEX idx_mikro_cabang ON nasabah_mikro (cabang);
CREATE INDEX idx_mikro_survey_status ON nasabah_mikro (survey_status);

-- ---------------------------------------------------------------------
-- Tabel angsuran_mikro (jadwal cicilan, dipakai mikro-monitoring.php)
-- ---------------------------------------------------------------------
CREATE TABLE `angsuran_mikro` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mikro_id INT NOT NULL,
    angsuran_ke INT NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    status ENUM('belum_bayar', 'lunas', 'terlambat') NOT NULL DEFAULT 'belum_bayar',
    tanggal_bayar DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_angsuran_mikro_nasabah FOREIGN KEY (mikro_id) REFERENCES nasabah_mikro(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_angsuran_mikro_ke (mikro_id, angsuran_ke)
);
CREATE INDEX idx_angsuran_mikro_status ON angsuran_mikro (status);
CREATE INDEX idx_angsuran_mikro_tempo ON angsuran_mikro (tanggal_jatuh_tempo);

-- ---------------------------------------------------------------------
-- Akun contoh untuk langsung bisa login & mencoba dashboard Mikro.
-- Password ketiganya: password123
-- Hash di bawah dihasilkan dengan: password_hash('password123', PASSWORD_DEFAULT)
-- GANTI PASSWORD INI sebelum dipakai di luar lingkungan development lokal.
-- ---------------------------------------------------------------------
INSERT INTO `users` (nama_lengkap, username, password, email, role, status) VALUES
('Admin Utama', 'admin', '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'admin@bsi.local', 'admin', 'verified'),
('Petugas Mikro', 'petugas.mikro', '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'petugas.mikro@bsi.local', 'mikro', 'verified'),
('Atasan Cabang', 'atasan', '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'atasan@bsi.local', 'atasan', 'verified');

-- ---------------------------------------------------------------------
-- Beberapa contoh data nasabah supaya dashboard tidak kosong saat pertama
-- kali dibuka (opsional -- hapus/skip blok ini kalau Anda mau mulai dari
-- data kosong).
-- ---------------------------------------------------------------------
INSERT INTO `nasabah_mikro`
    (user_id, nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, lama_usaha_tahun, alamat_usaha, alamat, no_telepon,
     cabang, nama_ao, status_pengajuan, plafon_pembiayaan, outstanding, target_outstanding, kolektibilitas,
     dok_ktp, dok_kk, dok_izin_usaha, dok_foto_usaha, tanggal_jatuh_tempo,
     survey_status, survey_tanggal, survey_petugas, survey_skor, tenor_bulan, created_at)
VALUES
    (2, 'Suryani', 'Warung Sembako Berkah', 'Perdagangan', 15000000, 4, 'Jl. Mawar No. 12, Tambun', 'Jl. Mawar No. 12, Tambun', '081234567801',
     'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 30000000, 22000000, 30000000, 1,
     1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY),
     'selesai', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Petugas Mikro', 82, 12, DATE_SUB(NOW(), INTERVAL 11 MONTH)),
    (2, 'Ahmad Fauzi', 'Bengkel Motor Jaya', 'Jasa', 12000000, 2, 'Jl. Kenanga No. 5, Tambun', 'Jl. Kenanga No. 5, Tambun', '081234567802',
     'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 25000000, 25000000, 25000000, 2,
     1, 1, 0, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY),
     'selesai', DATE_SUB(CURDATE(), INTERVAL 200 DAY), 'Petugas Mikro', 68, 12, DATE_SUB(NOW(), INTERVAL 10 MONTH)),
    (2, 'Rina Amelia', 'Katering Rina', 'Kuliner', 20000000, 6, 'Jl. Melati No. 8, Tambun', 'Jl. Melati No. 8, Tambun', '081234567803',
     'BSI KCP Tambun', 'Petugas Mikro', 'Approval', 40000000, 0, 0, 1,
     1, 0, 0, 0, NULL,
     'terjadwal', CURDATE(), 'Petugas Mikro', NULL, 12, NOW()),
    (2, 'Budi Santoso', 'Konveksi Budi', 'Manufaktur', 8000000, 1, 'Jl. Anggrek No. 3, Tambun', 'Jl. Anggrek No. 3, Tambun', '081234567804',
     'BSI KCP Tambun', 'Petugas Mikro', 'Ditolak', 15000000, 0, 0, 1,
     0, 0, 0, 0, NULL,
     'belum', NULL, NULL, NULL, 12, NOW());

-- =====================================================================
-- CATATAN kalau Anda JUGA memakai dashboard Konsumer / Pawning / Atasan:
-- file ini HANYA membuat `users` + tabel-tabel Mikro. Untuk dashboard
-- Konsumer, tabel `nasabah_konsumer` (+ migrasi 004 & 005) masih perlu
-- dibuat terpisah -- beri tahu saya kalau mau saya siapkan juga.
-- =====================================================================