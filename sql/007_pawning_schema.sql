-- =====================================================================
-- 007_pawning_schema.sql
-- Membuat tabel `pawning` yang SELAMA INI TIDAK PERNAH DIBUAT oleh
-- migration manapun (000-006), padahal sudah dipakai oleh:
--   - admin/pawning/pawning-tambah.php   (INSERT)
--   - admin/pawning/pawning-dashboard.php (SELECT/COUNT/SUM, UNION recent activity)
--   - admin/Atasan/atasan-monitoring-pawning.php (SELECT)
--
-- Akibatnya di fresh install: form "Gadai Baru" selalu gagal simpan
-- ("Terjadi kesalahan database") dan Dashboard Gadai selalu menampilkan
-- 0/kosong karena query ke tabel yang belum ada.
--
-- CARA PAKAI: jalankan file ini SEKALI ke database `database_pendataan-bsi`
-- (aman dijalankan berkali-kali, memakai IF NOT EXISTS).
-- =====================================================================

USE `database_pendataan-bsi`;

CREATE TABLE IF NOT EXISTS `pawning` (
    id INT AUTO_INCREMENT PRIMARY KEY,

    nama_pemilik    VARCHAR(150) NOT NULL,
    barang_jaminan  VARCHAR(255) NOT NULL,
    taksiran_harga  DECIMAL(15,2) NOT NULL DEFAULT 0,
    nilai_pinjaman  DECIMAL(15,2) NOT NULL DEFAULT 0,
    status          ENUM('Aktif', 'Lunas', 'Lelang') NOT NULL DEFAULT 'Aktif',
    no_telepon      VARCHAR(20) NULL,

    -- Petugas yang menginput data (FK ke users, dipakai untuk beban
    -- kerja per petugas / audit siapa yang input)
    user_id INT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_pawning_status (status),
    KEY idx_pawning_created_at (created_at),
    KEY idx_pawning_user_id (user_id),

    CONSTRAINT fk_pawning_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
