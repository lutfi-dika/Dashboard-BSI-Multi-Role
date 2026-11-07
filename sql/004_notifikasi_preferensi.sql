-- Migration: kolom pendukung untuk fitur Notifikasi & Pengaturan
-- (admin/konsumer/konsumer-notifikasi.php, konsumer-pengaturan.php, mikro-pengaturan.php).
-- Jalankan sekali di database `database_pendataan-bsi`.
-- Catatan: syntax ini SENGAJA tidak pakai "IF NOT EXISTS" pada ADD COLUMN karena
-- baru didukung MySQL 8.0.29+. Kalau kolom sudah pernah ditambahkan sebelumnya
-- dan Anda run ulang, error "Duplicate column name" itu aman diabaikan.

-- updated_at dipakai untuk tahu kapan status pengajuan konsumer terakhir diubah
-- (dasar feed notifikasi "baru disetujui/ditolak").
ALTER TABLE nasabah_konsumer ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Preferensi notifikasi per user (dipakai di halaman Pengaturan Konsumer & Mikro).
ALTER TABLE users ADD COLUMN notif_email TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN notif_wa TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN notif_jatuh_tempo TINYINT(1) NOT NULL DEFAULT 1;
