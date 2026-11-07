-- =====================================================================
-- 008_pawning_fitur.sql
-- Melengkapi skema tabel `pawning` + tabel pendukung agar semua fitur
-- halaman admin/pawning/* (perpanjangan, pelunasan, penebusan, lelang,
-- foto, taksiran, jatuh tempo, outstanding, ujrah, laporan, export,
-- notifikasi, pengaturan) berjalan dengan data yang sesungguhnya.
--
-- CARA PAKAI: jalankan file ini SEKALI ke database `database_pendataan-bsi`
-- (aman dijalankan berkali-kali, memakai IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
-- =====================================================================

USE `database_pendataan-bsi`;

-- ---------------------------------------------------------------------
-- 1. Perluas tabel pawning (informasi gadai + jatuh tempo + ujrah)
-- ---------------------------------------------------------------------
ALTER TABLE `pawning`
    ADD COLUMN IF NOT EXISTS `tanggal_gadai` DATE NULL AFTER `no_telepon`,
    ADD COLUMN IF NOT EXISTS `masa_gadai_hari` INT NOT NULL DEFAULT 120 AFTER `tanggal_gadai`,
    ADD COLUMN IF NOT EXISTS `tanggal_jatuh_tempo` DATE NULL AFTER `masa_gadai_hari`,
    ADD COLUMN IF NOT EXISTS `nilai_ujrah` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `tanggal_jatuh_tempo`,
    ADD COLUMN IF NOT EXISTS `keterangan` TEXT NULL AFTER `nilai_ujrah`;

-- Isi data lama supaya jatuh tempo langsung tersedia
UPDATE `pawning` SET `tanggal_gadai` = DATE(`created_at`) WHERE `tanggal_gadai` IS NULL;
UPDATE `pawning` SET `tanggal_jatuh_tempo` = DATE_ADD(`tanggal_gadai`, INTERVAL `masa_gadai_hari` DAY) WHERE `tanggal_jatuh_tempo` IS NULL;
UPDATE `pawning` SET `nilai_ujrah` = ROUND(`nilai_pinjaman` * 0.015, 2) WHERE `nilai_ujrah` = 0;

-- ---------------------------------------------------------------------
-- 2. Tabel foto barang gadai
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pawning_foto` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pawning_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    keterangan VARCHAR(255) NULL,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_foto_pawning FOREIGN KEY (pawning_id) REFERENCES pawning(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Tabel riwayat perpanjangan masa gadai
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 4. Tabel riwayat pelunasan / penebusan
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 5. Tabel lelang barang gadai
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 6. Tabel riwayat perubahan nilai taksiran
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pawning_taksiran` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pawning_id INT NOT NULL,
    taksiran_lama DECIMAL(15,2) NOT NULL DEFAULT 0,
    taksiran_baru DECIMAL(15,2) NOT NULL DEFAULT 0,
    user_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_taksiran_pawning FOREIGN KEY (pawning_id) REFERENCES pawning(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Tabel pengaturan sistem gadai (key-value)
-- ---------------------------------------------------------------------
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
