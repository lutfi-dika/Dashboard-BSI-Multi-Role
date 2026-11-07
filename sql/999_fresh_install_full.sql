-- =====================================================================
-- 999_fresh_install_full.sql
-- Install lengkap dari NOL untuk seluruh Dashboard Pendataan BSI
-- (Mikro + Konsumer + Gadai/Pawning + Atasan).
--
-- Gabungan seluruh migrasi 000-010 + base schema nasabah_konsumer.
-- Cukup jalankan SATU file ini saja ke MySQL/MariaDB kosong.
--
-- Password 3 akun contoh: "password123" (WAJIB ganti di production).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `database_pendataan-bsi`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `database_pendataan-bsi`;

-- =====================================================================
-- 1. TABEL USERS (login, OTP, role, preferensi notifikasi)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `users` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'mikro',

    -- OTP (helpers/otp.php)
    otp_code VARCHAR(255) NULL,
    otp_expiry DATETIME NULL,
    otp_created_at DATETIME NULL,
    otp_purpose VARCHAR(30) NULL,
    otp_channel VARCHAR(10) NULL,
    otp_attempts INT NOT NULL DEFAULT 0,
    status ENUM('unverified', 'verified') NOT NULL DEFAULT 'unverified',

    -- Preferensi notifikasi (pengaturan)
    notif_email TINYINT(1) NOT NULL DEFAULT 1,
    notif_wa TINYINT(1) NOT NULL DEFAULT 0,
    notif_jatuh_tempo TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_users_username (username),
    UNIQUE KEY uniq_users_email (email)
);

-- =====================================================================
-- 2. TABEL NASABAH KONSUMER (base + kolom dari migrasi 004, 005, 010)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `nasabah_konsumer` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,

    -- Data pemohon
    nama_lengkap VARCHAR(150) NOT NULL,
    nik VARCHAR(20) NOT NULL,
    pekerjaan VARCHAR(100) NOT NULL,
    pendapatan_bulanan DECIMAL(15,2) NOT NULL DEFAULT 0,
    alamat_rumah TEXT NULL,
    no_telepon VARCHAR(20) NULL,

    -- Produk & status (005, 010)
    produk_diajukan VARCHAR(50) NOT NULL DEFAULT 'BSI Multiguna iB',
    plafon_pengajuan DECIMAL(15,2) NOT NULL DEFAULT 0,
    tenor_bulan INT NOT NULL DEFAULT 12,
    tanggal_pencairan DATE NULL,
    tanggal_jatuh_tempo DATE NULL,

    -- Dokumen (005)
    dok_ktp TINYINT(1) NOT NULL DEFAULT 0,
    dok_kk TINYINT(1) NOT NULL DEFAULT 0,
    dok_slip_gaji TINYINT(1) NOT NULL DEFAULT 0,
    dok_jaminan TINYINT(1) NOT NULL DEFAULT 0,

    -- Approval
    status ENUM('pending', 'disetujui', 'ditolak') NOT NULL DEFAULT 'pending',
    catatan_approval TEXT NULL,
    diproses_oleh INT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_konsumer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_konsumer_status (status),
    INDEX idx_konsumer_jatuh_tempo (tanggal_jatuh_tempo)
);

-- =====================================================================
-- 3. TABEL NASABAH MIKRO (lengkap dari 000 + 002 + 003 + 006 + 010)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `nasabah_mikro` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,

    -- Data pemilik & usaha
    nama_pemilik VARCHAR(150) NOT NULL,
    nama_usaha VARCHAR(150) NOT NULL,
    jenis_sektor VARCHAR(100) NULL,
    produk ENUM('KUR Super Mikro', 'KUR Mikro', 'KUR Kecil', 'BSI Mikro iB') NOT NULL DEFAULT 'BSI Mikro iB',
    omzet_bulanan DECIMAL(15,2) NOT NULL DEFAULT 0,
    lama_usaha_tahun INT NOT NULL DEFAULT 0,
    alamat_usaha VARCHAR(255) NULL,
    alamat VARCHAR(255) NULL,
    no_telepon VARCHAR(20) NULL,

    -- KPI / cabang / AO / status / plafon
    cabang VARCHAR(100) NOT NULL DEFAULT 'BSI KCP Tambun',
    nama_ao VARCHAR(150) NULL,
    status_pengajuan ENUM('Approval', 'Aktif', 'Ditolak') NOT NULL DEFAULT 'Approval',
    plafon_pembiayaan DECIMAL(15,2) NOT NULL DEFAULT 0,
    tenor_bulan INT NOT NULL DEFAULT 12,
    outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
    target_outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
    kolektibilitas TINYINT NOT NULL DEFAULT 1,

    -- Dokumen & jatuh tempo
    dok_ktp TINYINT(1) NOT NULL DEFAULT 0,
    dok_kk TINYINT(1) NOT NULL DEFAULT 0,
    dok_izin_usaha TINYINT(1) NOT NULL DEFAULT 0,
    dok_foto_usaha TINYINT(1) NOT NULL DEFAULT 0,
    tanggal_jatuh_tempo DATE NULL,

    -- Survey lapangan
    survey_status ENUM('belum', 'terjadwal', 'selesai') NOT NULL DEFAULT 'belum',
    survey_tanggal DATE NULL,
    survey_petugas VARCHAR(150) NULL,
    survey_skor TINYINT NULL,
    survey_catatan TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_nasabah_mikro_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_mikro_status_pengajuan (status_pengajuan),
    INDEX idx_mikro_cabang (cabang),
    INDEX idx_mikro_survey_status (survey_status)
);

-- =====================================================================
-- 4. TABEL ANGSURAN KONSUMER
-- =====================================================================
CREATE TABLE IF NOT EXISTS `angsuran_konsumer` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    konsumer_id INT NOT NULL,
    angsuran_ke INT NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    status ENUM('belum_bayar', 'lunas', 'terlambat') NOT NULL DEFAULT 'belum_bayar',
    tanggal_bayar DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_angsuran_konsumer_nasabah FOREIGN KEY (konsumer_id) REFERENCES nasabah_konsumer(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_angsuran_konsumer_ke (konsumer_id, angsuran_ke),
    INDEX idx_angsuran_konsumer_status (status),
    INDEX idx_angsuran_konsumer_tempo (tanggal_jatuh_tempo)
);

-- =====================================================================
-- 5. TABEL TARGET KONSUMER (target bulanan per AO)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `target_konsumer` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bulan TINYINT NOT NULL,
    tahun SMALLINT NOT NULL,
    target_pengajuan INT NOT NULL DEFAULT 0,
    target_nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_target_konsumer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_target_konsumer_periode (user_id, bulan, tahun)
);

-- =====================================================================
-- 6. TABEL ANGSURAN MIKRO
-- =====================================================================
CREATE TABLE IF NOT EXISTS `angsuran_mikro` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mikro_id INT NOT NULL,
    angsuran_ke INT NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    status ENUM('belum_bayar', 'lunas', 'terlambat') NOT NULL DEFAULT 'belum_bayar',
    tanggal_bayar DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_angsuran_mikro_nasabah FOREIGN KEY (mikro_id) REFERENCES nasabah_mikro(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_angsuran_mikro_ke (mikro_id, angsuran_ke),
    INDEX idx_angsuran_mikro_status (status),
    INDEX idx_angsuran_mikro_tempo (tanggal_jatuh_tempo)
);

-- =====================================================================
-- 7. TABEL PAWNING (GADAI) + semua kolom dari 007, 008, 010
-- =====================================================================
CREATE TABLE IF NOT EXISTS `pawning` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pemilik VARCHAR(150) NOT NULL,
    barang_jaminan VARCHAR(255) NOT NULL,
    jenis_gadai ENUM('Gadai Emas iB', 'Gadai Non Emas', 'Fleksi Emas iB') NOT NULL DEFAULT 'Gadai Emas iB',
    taksiran_harga DECIMAL(15,2) NOT NULL DEFAULT 0,
    nilai_pinjaman DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('Aktif', 'Lunas', 'Lelang') NOT NULL DEFAULT 'Aktif',
    no_telepon VARCHAR(20) NULL,
    tanggal_gadai DATE NULL,
    masa_gadai_hari INT NOT NULL DEFAULT 120,
    tanggal_jatuh_tempo DATE NULL,
    nilai_ujrah DECIMAL(15,2) NOT NULL DEFAULT 0,
    keterangan TEXT NULL,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_pawning_status (status),
    INDEX idx_pawning_created_at (created_at),
    INDEX idx_pawning_user_id (user_id),
    CONSTRAINT fk_pawning_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
-- 8. TABEL FOTO BARANG GADAI
-- =====================================================================
CREATE TABLE IF NOT EXISTS `pawning_foto` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pawning_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    keterangan VARCHAR(255) NULL,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_foto_pawning FOREIGN KEY (pawning_id) REFERENCES pawning(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
-- 9. TABEL RIWAYAT PERPANJANGAN MASA GADAI
-- =====================================================================
CREATE TABLE IF NOT EXISTS `pawning_perpanjangan` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pawning_id INT NOT NULL,
    perpanjangan_hari INT NOT NULL,
    jatuh_tempo_lama DATE NOT NULL,
    jatuh_tempo_baru DATE NOT NULL,
    ujrah_bayar DECIMAL(15,2) NOT NULL DEFAULT 0,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_perpanjangan_pawning FOREIGN KEY (pawning_id) REFERENCES pawning(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
-- 10. TABEL RIWAYAT PELUNASAN / PENGEMBALIAN
-- =====================================================================
CREATE TABLE IF NOT EXISTS `pawning_pelunasan` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pawning_id INT NOT NULL,
    tipe ENUM('Pelunasan', 'Penebusan') NOT NULL DEFAULT 'Pelunasan',
    pokok DECIMAL(15,2) NOT NULL DEFAULT 0,
    ujrah DECIMAL(15,2) NOT NULL DEFAULT 0,
    denda DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_bayar DECIMAL(15,2) NOT NULL DEFAULT 0,
    metode_bayar VARCHAR(50) NULL,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pelunasan_pawning FOREIGN KEY (pawning_id) REFERENCES pawning(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
-- 11. TABEL LELANG BARANG GADAI
-- =====================================================================
CREATE TABLE IF NOT EXISTS `pawning_lelang` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pawning_id INT NOT NULL,
    tanggal_lelang DATE NULL,
    harga_terjual DECIMAL(15,2) NOT NULL DEFAULT 0,
    pemenang VARCHAR(150) NULL,
    keterangan TEXT NULL,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lelang_pawning FOREIGN KEY (pawning_id) REFERENCES pawning(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
-- 12. TABEL RIWAYAT PERUBAHAN NILAI TAKSIRAN
-- =====================================================================
CREATE TABLE IF NOT EXISTS `pawning_taksiran` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pawning_id INT NOT NULL,
    taksiran_lama DECIMAL(15,2) NOT NULL DEFAULT 0,
    taksiran_baru DECIMAL(15,2) NOT NULL DEFAULT 0,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_taksiran_pawning FOREIGN KEY (pawning_id) REFERENCES pawning(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
-- 13. TABEL PENGATURAN SISTEM GADAI (key-value)
-- =====================================================================
CREATE TABLE IF NOT EXISTS `pawning_pengaturan` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_setting VARCHAR(50) NOT NULL,
    nilai_setting VARCHAR(255) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pawning_setting (nama_setting)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO `pawning_pengaturan` (nama_setting, nilai_setting) VALUES
('masa_gadai_hari', '120'),
('tarif_ujrah_bulan', '1.5'),
('denda_terlambat_persen', '0.1'),
('nama_cabang', 'BSI KCPS'),
('kode_cabang', 'KCPS'),
('alamat_cabang', ''),
('no_telp_cabang', '');

-- =====================================================================
-- SEED DATA: AKUN CONTOH
-- Password: password123 (WAJIB ganti di production)
-- =====================================================================
INSERT INTO `users` (nama_lengkap, username, password, email, role, status) VALUES
('Admin Utama',      'admin',         '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'admin@bsi.local',       'admin',    'verified'),
('Petugas Mikro',    'petugas.mikro', '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'petugas.mikro@bsi.local','mikro',   'verified'),
('Atasan Cabang',    'atasan',        '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'atasan@bsi.local',      'atasan',   'verified'),
('Petugas Konsumer', 'petugas.konsumer','$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS','petugas.konsumer@bsi.local','konsumer','verified'),
('Petugas Gadai',    'petugas.gadai', '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'petugas.gadai@bsi.local','pawning',  'verified');

-- =====================================================================
-- SEED DATA: NASABAH MIKRO (contoh)
-- =====================================================================
INSERT INTO `nasabah_mikro`
(user_id, nama_pemilik, nama_usaha, jenis_sektor, produk, omzet_bulanan, lama_usaha_tahun, alamat_usaha, alamat, no_telepon,
 cabang, nama_ao, status_pengajuan, plafon_pembiayaan, tenor_bulan, outstanding, target_outstanding, kolektibilitas,
 dok_ktp, dok_kk, dok_izin_usaha, dok_foto_usaha, tanggal_jatuh_tempo,
 survey_status, survey_tanggal, survey_petugas, survey_skor, created_at)
VALUES
(2, 'Suryani',         'Warung Sembako Berkah', 'Perdagangan', 'KUR Super Mikro', 15000000,  4, 'Jl. Mawar No. 12, Tambun',    'Jl. Mawar No. 12, Tambun',    '081234567801',
 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 30000000,  12, 22000000, 30000000, 1,
 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Petugas Mikro', 82, DATE_SUB(NOW(), INTERVAL 11 MONTH)),

(2, 'Ahmad Fauzi',     'Bengkel Motor Jaya',     'Jasa',        'BSI Mikro iB', 12000000,  2, 'Jl. Kenanga No. 5, Tambun',   'Jl. Kenanga No. 5, Tambun',   '081234567802',
 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 25000000,  12, 25000000, 25000000, 2,
 1, 1, 0, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 200 DAY), 'Petugas Mikro', 68, DATE_SUB(NOW(), INTERVAL 10 MONTH)),

(2, 'Rina Amelia',     'Katering Rina',           'Kuliner',     'KUR Mikro', 20000000,  6, 'Jl. Melati No. 8, Tambun',    'Jl. Melati No. 8, Tambun',    '081234567803',
 'BSI KCP Tambun', 'Petugas Mikro', 'Approval', 40000000, 12, 0, 0, 1,
 1, 0, 0, 0, NULL,
 'terjadwal', CURDATE(), 'Petugas Mikro', NULL, NOW()),

(2, 'Budi Santoso',    'Konveksi Budi',           'Manufaktur',  'BSI Mikro iB', 8000000,   1, 'Jl. Anggrek No. 3, Tambun',   'Jl. Anggrek No. 3, Tambun',   '081234567804',
 'BSI KCP Tambun', 'Petugas Mikro', 'Ditolak', 15000000, 12, 0, 0, 1,
 0, 0, 0, 0, NULL,
 'belum', NULL, NULL, NULL, NOW()),

(2, 'Rina Kusuma',     'Warung Sembako Rina',    'Perdagangan', 'KUR Super Mikro', 8500000,   2, 'Jl. Raya Tambun No. 12, Bekasi',  'Jl. Raya Tambun No. 12, Bekasi',  '081234567101',
 'BSI KCP Tambun', 'Ahmad Fauzi',   'Aktif', 8000000,   12, 4800000,  100000000, 1,
 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 8 MONTH),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 'Ahmad Fauzi', 85, DATE_SUB(CURDATE(), INTERVAL 4 MONTH)),

(2, 'Budi Hartono',    'Kedai Kopi Nusantara',    'Jasa',        'KUR Super Mikro', 12500000,  3, 'Jl. Melati No. 5, Cibitung',   'Jl. Melati No. 5, Cibitung',   '081234567102',
 'BSI KCP Tambun', 'Siti Nurhaliza','Aktif', 9000000,   12, 6200000,  100000000, 1,
 1, 1, 0, 1, DATE_ADD(CURDATE(), INTERVAL 9 MONTH),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'Siti Nurhaliza', 80, DATE_SUB(CURDATE(), INTERVAL 3 MONTH)),

(2, 'Dewi Anggraini',  'Toko Bangunan Dwi',       'Perdagangan', 'KUR Mikro', 45000000,  5, 'Jl. Imam Bonjol No. 88, Bekasi',  'Jl. Imam Bonjol No. 88, Bekasi',  '081234567103',
 'BSI KCP Tambun', 'Ahmad Fauzi',   'Aktif', 50000000,  36, 38500000, 200000000, 1,
 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 20 MONTH),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 16 MONTH), 'Ahmad Fauzi', 90, DATE_SUB(CURDATE(), INTERVAL 16 MONTH)),

(2, 'Joko Santoso',    'Peternakan Jaya',         'Pertanian',   'KUR Mikro', 38000000,  6, 'Jl. Rawa Bebek No. 21, Karawang', 'Jl. Rawa Bebek No. 21, Karawang', '081234567104',
 'BSI KCP Karawang','Rizky Ramadhan','Aktif', 40000000,  24, 31000000, 200000000, 2,
 1, 1, 1, 0, DATE_ADD(CURDATE(), INTERVAL 15 MONTH),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 9 MONTH), 'Rizky Ramadhan', 75, DATE_SUB(CURDATE(), INTERVAL 9 MONTH)),

(2, 'Sri Wahyuni',     'Pabrik Roti Sri',         'Manufaktur',  'KUR Kecil', 95000000,  8, 'Kawasan Industri Jababeka Cikarang', 'Kawasan Industri Jababeka Cikarang', '081234567105',
 'BSI KCP Cikarang','Deni Firmansyah','Aktif',150000000, 48, 118000000,300000000, 1,
 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 30 MONTH),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 18 MONTH), 'Deni Firmansyah', 88, DATE_SUB(CURDATE(), INTERVAL 18 MONTH)),

(2, 'Agus Salim',      'Distributor Beras',       'Perdagangan', 'KUR Kecil', 82000000,  7, 'Jl. Cikarang Baru No. 44, Bekasi', 'Jl. Cikarang Baru No. 44, Bekasi', '081234567106',
 'BSI KCP Cikarang','Deni Firmansyah','Aktif',120000000, 36, 102000000,300000000, 2,
 1, 1, 0, 1, DATE_ADD(CURDATE(), INTERVAL 26 MONTH),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 14 MONTH), 'Deni Firmansyah', 72, DATE_SUB(CURDATE(), INTERVAL 14 MONTH)),

(2, 'Lina Marlina',    'Studio Bridal Lina',      'Jasa',        'BSI Mikro iB', 60000000,  4, 'Jl. Ahmad Yani No. 3, Tambun',  'Jl. Ahmad Yani No. 3, Tambun',  '081234567107',
 'BSI KCP Tambun', 'Siti Nurhaliza','Aktif', 75000000,  24, 68000000, 150000000, 1,
 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 18 MONTH),
 'selesai', DATE_SUB(CURDATE(), INTERVAL 12 MONTH), 'Siti Nurhaliza', 83, DATE_SUB(CURDATE(), INTERVAL 12 MONTH)),

(2, 'Rahmat Hidayat',  'Bengkel Motor Baru',      'Jasa',        'BSI Mikro iB', 55000000,  5, 'Jl. Kaliabang No. 9, Bekasi Utara', 'Jl. Kaliabang No. 9, Bekasi Utara', '081234567108',
 'BSI KCP Tambun', 'Ahmad Fauzi',   'Aktif', 60000000,  36, 52000000, 150000000, 2,
 1, 1, 0, 1, DATE_ADD(CURDATE(), INTERVAL 22 MONTH),
 'terjadwal', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Ahmad Fauzi', NULL, DATE_SUB(CURDATE(), INTERVAL 10 MONTH));

-- =====================================================================
-- SEED DATA: NASABAH KONSUMER (contoh)
-- =====================================================================
INSERT INTO `nasabah_konsumer`
(user_id, nama_lengkap, nik, pekerjaan, pendapatan_bulanan, alamat_rumah, no_telepon, produk_diajukan, plafon_pengajuan, tenor_bulan, status, created_at)
VALUES
(4, 'Rahma Anisa',     '3201012345670005', 'Karyawan Swasta',   25000000, 'Perumahan Green Lake, Tambun',    '081234567201', 'BSI Griya iB',      750000000, 240, 'disetujui', DATE_SUB(CURDATE(), INTERVAL 20 MONTH)),
(4, 'Andi Prasetyo',   '3201012345670006', 'Wiraswasta',        18500000, 'Jl. Hasanudin No. 7, Bekasi',     '081234567202', 'BSI OTO iB',        320000000,  60, 'disetujui', DATE_SUB(CURDATE(), INTERVAL 14 MONTH)),
(4, 'Hj. Maryam',      '3201012345670007', 'Pensiunan PNS',     12000000, 'Jl. Pahlawan No. 15, Tambun',     '081234567203', 'BSI Pensiun iB',    100000000,  36, 'disetujui', DATE_SUB(CURDATE(), INTERVAL 8 MONTH)),
(4, 'Fatimah Azzahra', '3201012345670008', 'Ibu Rumah Tangga',  15000000, 'Jl. Kenanga No. 2, Cibitung',     '081234567204', 'BSI Umroh iB',       35000000,  24, 'pending',   DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
(4, 'Nur Aisyah',      '3201012345670009', 'Karyawan BUMN',     22000000, 'Jl. Sukatani No. 11, Bekasi',     '081234567205', 'BSI Fleksi iB',      85000000,  48, 'pending',   DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
(4, 'Herman Susanto',  '3201012345670010', 'Pengusaha',         18000000, 'Jl. Bahagia No. 19, Karawang',    '081234567206', 'BSI Multiguna iB',  200000000,  60, 'pending',   DATE_SUB(CURDATE(), INTERVAL 1 MONTH));

-- =====================================================================
-- SEED DATA: NASABAH GADAI / PAWNING (contoh)
-- =====================================================================
INSERT INTO `pawning`
(id, nama_pemilik, barang_jaminan, jenis_gadai, taksiran_harga, nilai_pinjaman, status, no_telepon,
 tanggal_gadai, masa_gadai_hari, tanggal_jatuh_tempo, nilai_ujrah, keterangan, created_at)
VALUES
(1,  'Siti Aminah',     'Emas batangan ANTAM 15 gram',         'Gadai Emas iB',  22500000, 15000000, 'Aktif',  '081234569801', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 2 MONTH),  225000,  'Emas batangan ANTAM 24 karat',                    DATE_SUB(CURDATE(), INTERVAL 4 MONTH)),
(2, 'Rudi Hermawan',   'Laptop ASUS ROG 2024',                 'Gadai Non Emas', 18000000, 11000000, 'Aktif',  '081234569802', DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 1 MONTH),  165000,  'Laptop gaming lengkap dengan charger',            DATE_SUB(CURDATE(), INTERVAL 3 MONTH)),
(3, 'Dewi Lestari',    'Emas 24 karat 5 gram',                 'Gadai Emas iB',  7500000,  5500000,  'Aktif',  '081234569803', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 2 MONTH),   82500,  'Anting emas 24 karat kadar 99.9%',               DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
(4, 'Bambang Priyanto','Emas 22 karat 25 gram',                'Gadai Emas iB',  22000000, 16000000, 'Aktif',  '081234569804', DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 1 MONTH),  240000,  'Perhiasan kalung emas 22 karat 25 gram',         DATE_SUB(CURDATE(), INTERVAL 8 MONTH)),
(5, 'Putri Maharani',  'Emas 22 karat 7 gram',                 'Gadai Emas iB',  6300000,  4500000,  'Aktif',  '081234569805', DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 0 DAY),    67500,  'Gelang emas 22 karat 7 gram',                     DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),
(6, 'Joko Susilo',     'Emas 24 karat 3 gram',                 'Gadai Emas iB',  4500000,  3200000,  'Aktif',  '081234569806', DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 1 MONTH),   48000,  'Logam mulia batangan 3 gram',                     DATE_SUB(CURDATE(), INTERVAL 5 MONTH)),
(7, 'Maya Anggraini',  'Laptop MacBook Air 2023',              'Gadai Non Emas', 18000000, 11000000, 'Aktif',  '081234569807', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 2 MONTH),  165000,  'Laptop dalam kondisi baik',                       DATE_SUB(CURDATE(), INTERVAL 4 MONTH)),
(8, 'Hasan Basri',     'Emas 22 karat 10 gram',                'Gadai Emas iB',  9000000,  6800000,  'Lunas',  '081234569808', DATE_SUB(CURDATE(), INTERVAL 7 MONTH), 120, DATE_SUB(CURDATE(), INTERVAL 3 MONTH),  102000,  'Cincin emas 22 karat 10 gram',                    DATE_SUB(CURDATE(), INTERVAL 7 MONTH)),
(9, 'Ratna Dewi',      'Emas 22 karat 8 gram',                 'Gadai Emas iB',  7200000,  5000000,  'Lunas',  '081234569809', DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 120, DATE_SUB(CURDATE(), INTERVAL 2 MONTH),   75000,  'Liontin emas 22 karat 8 gram',                    DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),
(10, 'Tono Wijaya',     'Emas 22 karat 9 gram',                'Gadai Emas iB',  8100000,  6000000,  'Lelang', '081234569810', DATE_SUB(CURDATE(), INTERVAL 9 MONTH), 120, DATE_SUB(CURDATE(), INTERVAL 5 MONTH),   90000,  'Cincin pria emas 22 karat 9 gram',                DATE_SUB(CURDATE(), INTERVAL 9 MONTH)),
(11, 'Farida Hanum',    'Emas 24 karat 6 gram',                 'Gadai Emas iB',  6600000,  4800000,  'Aktif',  '081234569811', DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 150, DATE_ADD(CURDATE(), INTERVAL 1 MONTH),   72000,  'Anting dan kalung emas 6 gram',                   DATE_SUB(CURDATE(), INTERVAL 5 MONTH)),
(12, 'Ahmad Fauzi',     'Emas 24 karat 12 gram',               'Fleksi Emas iB', 13000000, 9500000,  'Aktif',  '081234569812', DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 150, DATE_ADD(CURDATE(), INTERVAL 2 MONTH),  142500,  'Logam mulia batangan 12 gram',                    DATE_SUB(CURDATE(), INTERVAL 4 MONTH)),
(13, 'Bambang Wibowo',  'BPKB Toyota Avanza 2020',              'Gadai Non Emas', 185000000,110000000, 'Aktif',  '081234569813', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 5500000,  'BPKB asli atas nama sendiri',                     DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
(14, 'Siti Aminah',     'Emas batangan ANTAM 25 gram',         'Fleksi Emas iB', 42000000, 33000000, 'Aktif',  '081234569814', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 2 MONTH), 1650000,  'Emas batangan ANTAM 25 gram',                     DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
(15, 'Rudi Hermawan',   'Emas 23 karat cincin 15 gram',        'Fleksi Emas iB', 23500000, 18000000, 'Aktif',  '081234569815', DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 1 MONTH),  900000,  'Cincin emas 23 karat 15 gram',                    DATE_SUB(CURDATE(), INTERVAL 3 MONTH));

-- =====================================================================
-- SEED DATA: RIWAYAT PELUNASAN / PENGEMBALIAN GADAI
-- =====================================================================
INSERT INTO `pawning_pelunasan`
(pawning_id, tipe, pokok, ujrah, denda, total_bayar, metode_bayar, user_id, created_at) VALUES
(8,  'Pelunasan', 6800000,  204000, 0, 7004000, 'Transfer', 1, DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(9,  'Penebusan', 5000000,   45000, 0, 5045000, 'Debit',    1, DATE_SUB(NOW(), INTERVAL 2 MONTH));

-- =====================================================================
-- SEED DATA: RIWAYAT PERPANJANGAN MASA GADAI
-- =====================================================================
INSERT INTO `pawning_perpanjangan`
(pawning_id, perpanjangan_hari, jatuh_tempo_lama, jatuh_tempo_baru, ujrah_bayar, user_id, created_at) VALUES
(4,  30, DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 1 MONTH),  240000, 1, DATE_SUB(NOW(), INTERVAL 1 MONTH)),
(11, 60, DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), INTERVAL 60 DAY), DATE_ADD(CURDATE(), INTERVAL 1 MONTH),  144000, 1, DATE_SUB(NOW(), INTERVAL 45 DAY));

-- =====================================================================
-- SEED DATA: RIWAYAT LELANG
-- =====================================================================
INSERT INTO `pawning_lelang`
(pawning_id, tanggal_lelang, harga_terjual, pemenang, keterangan, user_id, created_at) VALUES
(10, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 7800000, 'Samsul Arifin', 'Lelang online BSI', 1, DATE_SUB(NOW(), INTERVAL 5 MONTH));

-- =====================================================================
-- SEED DATA: RIWAYAT PERUBAHAN TAKSIRAN
-- =====================================================================
INSERT INTO `pawning_taksiran`
(pawning_id, taksiran_lama, taksiran_baru, user_id, created_at) VALUES
(1, 20000000, 22500000, 1, DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(2, 16000000, 18000000, 1, DATE_SUB(NOW(), INTERVAL 1 MONTH)),
(4, 25000000, 22000000, 1, DATE_SUB(NOW(), INTERVAL 3 MONTH));

-- =====================================================================
-- SEED DATA: FOTO BARANG GADAI (referensi file di uploads/pawning/)
-- =====================================================================
INSERT INTO `pawning_foto`
(pawning_id, nama_file, keterangan, user_id, created_at) VALUES
(1,  'pawning_1_emas.png',         'Emas batangan ANTAM 15 gram',           1, DATE_SUB(NOW(), INTERVAL 4 MONTH)),
(2,  'pawning_2_laptop.png',       'Laptop ASUS ROG lengkap dengan charger', 1, DATE_SUB(NOW(), INTERVAL 3 MONTH)),
(13, 'pawning_13_bpkb.png',        'BPKB Toyota Avanza atas nama pemilik',  1, DATE_SUB(NOW(), INTERVAL 1 MONTH)),
(14, 'pawning_14_emas25gram.png',  'Emas batangan ANTAM 25 gram',           1, DATE_SUB(NOW(), INTERVAL 2 MONTH));

-- =====================================================================
-- SEED DATA: ANGSURAN KONSUMER (jadwal cicilan untuk nasabah disetujui)
-- =====================================================================
INSERT INTO `angsuran_konsumer` (konsumer_id, angsuran_ke, tanggal_jatuh_tempo, nominal, status, tanggal_bayar)
SELECT k.id, seq.n, DATE_ADD(k.tanggal_pencairan, INTERVAL seq.n MONTH), ROUND(k.plafon_pengajuan / k.tenor_bulan, 0),
       CASE WHEN seq.n <= 6 THEN 'lunas' ELSE 'belum_bayar' END,
       CASE WHEN seq.n <= 6 THEN DATE_ADD(k.tanggal_pencairan, INTERVAL seq.n MONTH) ELSE NULL END
FROM nasabah_konSUMER k
JOIN (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) seq
WHERE k.status = 'disetujui' AND seq.n <= LEAST(k.tenor_bulan, 12);

-- =====================================================================
-- SEED DATA: ANGSURAN MIKRO (jadwal cicilan untuk nasabah aktif)
-- =====================================================================
INSERT INTO `angsuran_mikro` (mikro_id, angsuran_ke, tanggal_jatuh_tempo, nominal, status, tanggal_bayar)
SELECT m.id, seq.n, DATE_ADD(m.created_at, INTERVAL seq.n MONTH), ROUND(m.plafon_pembiayaan / m.tenor_bulan, 0),
       CASE WHEN m.outstanding < m.plafon_pembiayaan AND seq.n <= GREATEST(1, ROUND((m.plafon_pembiayaan - m.outstanding) / (m.plafon_pembiayaan / m.tenor_bulan))) THEN 'lunas' ELSE 'belum_bayar' END,
       CASE WHEN m.outstanding < m.plafon_pembiayaan AND seq.n <= GREATEST(1, ROUND((m.plafon_pembiayaan - m.outstanding) / (m.plafon_pembiayaan / m.tenor_bulan))) THEN DATE_ADD(m.created_at, INTERVAL seq.n MONTH) ELSE NULL END
FROM nasabah_mikro m
JOIN (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) seq
WHERE m.status_pengajuan = 'Aktif' AND seq.n <= LEAST(m.tenor_bulan, 12);

-- =====================================================================
-- PERBAIKAN TANGGAL ANGSURAN KONSUMER
-- Seed konsumer tidak mengisi `tanggal_pencairan`, sehingga jadwal
-- angsuran konsumer berisi tanggal 0000-00-00. Backfill agar halaman
-- angsuran & jatuh tempo konsumer menampilkan tanggal yang benar.
-- =====================================================================
UPDATE `nasabah_konsumer`
SET `tanggal_pencairan` = DATE(`created_at`)
WHERE `status` = 'disetujui' AND (`tanggal_pencairan` IS NULL OR `tanggal_pencairan` = '0000-00-00');

UPDATE `nasabah_konsumer`
SET `tanggal_jatuh_tempo` = DATE_ADD(`tanggal_pencairan`, INTERVAL `tenor_bulan` MONTH)
WHERE `status` = 'disetujui' AND `tanggal_jatuh_tempo` IS NULL;

UPDATE `angsuran_konsumer` a
JOIN `nasabah_konsumer` k ON a.`konsumer_id` = k.`id`
SET a.`tanggal_jatuh_tempo` = DATE_ADD(k.`tanggal_pencairan`, INTERVAL a.`angsuran_ke` MONTH),
    a.`tanggal_bayar` = CASE WHEN a.`status` = 'lunas' THEN DATE_ADD(k.`tanggal_pencairan`, INTERVAL a.`angsuran_ke` MONTH) ELSE a.`tanggal_bayar` END
WHERE k.`status` = 'disetujui';

-- =====================================================================
-- SElesai! Database siap digunakan.
-- Akun login:
--   admin         / password123  (Admin)
--   petugas.mikro / password123  (Petugas Mikro)
--   atasan        / password123  (Atasan)
--   petugas.konsumer / password123 (Petugas Konsumer)
--   petugas.gadai / password123  (Petugas Gadai)
-- =====================================================================
