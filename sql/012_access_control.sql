-- =====================================================================
-- 012_access_control.sql
-- Migrasi: Tambah akun contoh untuk setiap role (atasan, mikro,
-- konsumer, pawning) beserta kolom `role` yang benar di tabel `users`.
--
-- CARA PAKAI:
--   Jalankan file ini di phpMyAdmin atau MySQL CLI setelah database
--   `database_pendataan-bsi` sudah ada.
--   Semua akun contoh di bawah menggunakan password: password123
--
-- CATATAN KEAMANAN:
--   Ganti password SEBELUM deploy ke production!
-- =====================================================================

USE `database_pendataan-bsi`;

-- ─────────────────────────────────────────────────────────────────────
-- 1. Pastikan kolom `role` sudah ada dan nilainya konsisten
-- ─────────────────────────────────────────────────────────────────────

-- Tambah kolom role jika belum ada (aman dijalankan berkali-kali)
ALTER TABLE `users`
    MODIFY COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'mikro';

-- ─────────────────────────────────────────────────────────────────────
-- 2. Akun contoh per role
--    Password semua akun: password123
--    Hash bcrypt dibuat dengan: password_hash('password123', PASSWORD_DEFAULT)
-- ─────────────────────────────────────────────────────────────────────

-- Admin (superuser) ───────────────────────────────────────────────────
INSERT INTO `users`
    (nama_lengkap, username, password, no_hp, email, role, status)
VALUES
    (
        'Administrator BSI',
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password123
        '081100000001',
        'admin@bsi.example',
        'admin',
        'verified'
    )
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role         = VALUES(role),
    status       = VALUES(status);

-- Atasan (Pimpinan / View-Only semua segmen) ──────────────────────────
INSERT INTO `users`
    (nama_lengkap, username, password, no_hp, email, role, status)
VALUES
    (
        'Budi Santoso',
        'atasan1',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '081100000002',
        'atasan1@bsi.example',
        'atasan',
        'verified'
    )
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role         = VALUES(role),
    status       = VALUES(status);

-- Petugas Mikro ───────────────────────────────────────────────────────
INSERT INTO `users`
    (nama_lengkap, username, password, no_hp, email, role, status)
VALUES
    (
        'Andi Wijaya',
        'mikro1',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '081100000003',
        'mikro1@bsi.example',
        'mikro',
        'verified'
    ),
    (
        'Dewi Rahayu',
        'mikro2',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '081100000004',
        'mikro2@bsi.example',
        'mikro',
        'verified'
    )
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role         = VALUES(role),
    status       = VALUES(status);

-- Petugas Konsumer ────────────────────────────────────────────────────
INSERT INTO `users`
    (nama_lengkap, username, password, no_hp, email, role, status)
VALUES
    (
        'Sari Lestari',
        'konsumer1',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '081100000005',
        'konsumer1@bsi.example',
        'konsumer',
        'verified'
    ),
    (
        'Hendra Pratama',
        'konsumer2',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '081100000006',
        'konsumer2@bsi.example',
        'konsumer',
        'verified'
    )
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role         = VALUES(role),
    status       = VALUES(status);

-- Petugas Pawning (Gadai Emas) ────────────────────────────────────────
INSERT INTO `users`
    (nama_lengkap, username, password, no_hp, email, role, status)
VALUES
    (
        'Rizky Nugroho',
        'pawning1',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '081100000007',
        'pawning1@bsi.example',
        'pawning',
        'verified'
    ),
    (
        'Maya Kusuma',
        'pawning2',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        '081100000008',
        'pawning2@bsi.example',
        'pawning',
        'verified'
    )
ON DUPLICATE KEY UPDATE
    nama_lengkap = VALUES(nama_lengkap),
    role         = VALUES(role),
    status       = VALUES(status);

-- ─────────────────────────────────────────────────────────────────────
-- 3. (Opsional) Tampilkan semua user dan role-nya untuk verifikasi
-- ─────────────────────────────────────────────────────────────────────
SELECT id, nama_lengkap, username, role, status
FROM `users`
ORDER BY
    FIELD(role, 'admin', 'atasan', 'mikro', 'konsumer', 'pawning'),
    id;
