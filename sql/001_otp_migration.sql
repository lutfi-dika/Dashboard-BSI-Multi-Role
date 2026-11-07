-- Migration: dukungan OTP via Email (utama) + WhatsApp (opsional)
-- Jalankan sekali di database `database_pendataan-bsi`.
-- Catatan: syntax ini SENGAJA tidak pakai "IF NOT EXISTS" pada ADD COLUMN karena
-- baru didukung MySQL 8.0.29+ — supaya kompatibel dengan versi MySQL/MariaDB bawaan XAMPP.
-- Kalau kolom sudah pernah ditambahkan sebelumnya dan Anda run ulang, error
-- "Duplicate column name" itu aman diabaikan (berarti kolomnya memang sudah ada).

ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL AFTER no_hp;
ALTER TABLE users ADD COLUMN otp_created_at DATETIME NULL AFTER otp_expiry;
ALTER TABLE users ADD COLUMN otp_purpose VARCHAR(30) NULL AFTER otp_created_at;
ALTER TABLE users ADD COLUMN otp_channel VARCHAR(10) NULL AFTER otp_purpose;
ALTER TABLE users ADD COLUMN otp_attempts INT NOT NULL DEFAULT 0 AFTER otp_channel;
ALTER TABLE users ADD COLUMN status ENUM('unverified','verified') NOT NULL DEFAULT 'unverified' AFTER otp_attempts;

-- otp_code sebaiknya cukup panjang untuk menyimpan hash bcrypt (60 karakter), bukan angka polos
ALTER TABLE users MODIFY COLUMN otp_code VARCHAR(255) NULL;

-- Index untuk mempercepat pencarian saat login (abaikan error jika index sudah ada)
CREATE INDEX idx_users_email ON users (email);
