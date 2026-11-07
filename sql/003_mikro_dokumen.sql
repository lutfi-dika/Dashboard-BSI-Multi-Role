-- Migration: menambahkan kolom kelengkapan dokumen untuk admin/Mikro/mikro-dokumen.php
-- Jalankan sekali di database `database_pendataan-bsi`.
-- Catatan: syntax ini SENGAJA tidak pakai "IF NOT EXISTS" pada ADD COLUMN karena
-- baru didukung MySQL 8.0.29+. Kalau kolom sudah pernah ditambahkan sebelumnya
-- dan Anda run ulang, error "Duplicate column name" itu aman diabaikan.

ALTER TABLE nasabah_mikro ADD COLUMN dok_ktp TINYINT(1) NOT NULL DEFAULT 0 AFTER kolektibilitas;
ALTER TABLE nasabah_mikro ADD COLUMN dok_kk TINYINT(1) NOT NULL DEFAULT 0 AFTER dok_ktp;
ALTER TABLE nasabah_mikro ADD COLUMN dok_izin_usaha TINYINT(1) NOT NULL DEFAULT 0 AFTER dok_kk;
ALTER TABLE nasabah_mikro ADD COLUMN dok_foto_usaha TINYINT(1) NOT NULL DEFAULT 0 AFTER dok_izin_usaha;
ALTER TABLE nasabah_mikro ADD COLUMN tanggal_jatuh_tempo DATE NULL AFTER dok_foto_usaha;

-- Nasabah aktif lama yang belum punya tanggal jatuh tempo diberi default 12 bulan dari input.
UPDATE nasabah_mikro SET tanggal_jatuh_tempo = DATE_ADD(created_at, INTERVAL 12 MONTH)
WHERE status_pengajuan = 'Aktif' AND tanggal_jatuh_tempo IS NULL;
