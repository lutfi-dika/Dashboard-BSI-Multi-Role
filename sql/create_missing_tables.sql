-- Create missing tables for BSI database
-- Based on 999_fresh_install_full.sql

USE `database_pendataan-bsi`;

-- =====================================================================
-- 2. TABEL NASABAH KONSUMER
-- =====================================================================
CREATE TABLE IF NOT EXISTS `nasabah_konsumer` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    nama_lengkap VARCHAR(150) NOT NULL,
    nik VARCHAR(20) NOT NULL,
    pekerjaan VARCHAR(100) NOT NULL,
    pendapatan_bulanan DECIMAL(15,2) NOT NULL DEFAULT 0,
    alamat_rumah TEXT NULL,
    no_telepon VARCHAR(20) NULL,
    produk_diajukan VARCHAR(50) NOT NULL DEFAULT 'BSI Multiguna iB',
    plafon_pengajuan DECIMAL(15,2) NOT NULL DEFAULT 0,
    tenor_bulan INT NOT NULL DEFAULT 12,
    tanggal_pencairan DATE NULL,
    tanggal_jatuh_tempo DATE NULL,
    dok_ktp TINYINT(1) NOT NULL DEFAULT 0,
    dok_kk TINYINT(1) NOT NULL DEFAULT 0,
    dok_slip_gaji TINYINT(1) NOT NULL DEFAULT 0,
    dok_jaminan TINYINT(1) NOT NULL DEFAULT 0,
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
-- 5. TABEL TARGET KONSUMER
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

-- Seed settings
INSERT IGNORE INTO `pawning_pengaturan` (nama_setting, nilai_setting) VALUES
('masa_gadai_hari', '120'),
('tarif_ujrah_bulan', '1.5'),
('denda_terlambat_persen', '0.1'),
('nama_cabang', 'BSI KCPS'),
('kode_cabang', 'KCPS'),
('alamat_cabang', ''),
('no_telp_cabang', '');