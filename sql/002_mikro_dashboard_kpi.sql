-- Migration: melengkapi kolom yang dibutuhkan admin/Mikro/mikro-dashboard.php
-- dan admin/Mikro/mikro-approval.php (KPI Outstanding, Target, NPF, Top AO, Approval).
-- Jalankan sekali di database `database_pendataan-bsi`.
-- Catatan: syntax ini SENGAJA tidak pakai "IF NOT EXISTS" pada ADD COLUMN karena
-- baru didukung MySQL 8.0.29+. Kalau kolom sudah pernah ditambahkan sebelumnya
-- dan Anda run ulang, error "Duplicate column name" itu aman diabaikan.

ALTER TABLE nasabah_mikro ADD COLUMN alamat VARCHAR(255) NULL AFTER alamat_usaha;
ALTER TABLE nasabah_mikro ADD COLUMN cabang VARCHAR(100) NOT NULL DEFAULT 'BSI KCP Tambun' AFTER alamat;
ALTER TABLE nasabah_mikro ADD COLUMN nama_ao VARCHAR(150) NULL AFTER cabang;
ALTER TABLE nasabah_mikro ADD COLUMN status_pengajuan ENUM('Approval','Aktif','Ditolak') NOT NULL DEFAULT 'Approval' AFTER nama_ao;
ALTER TABLE nasabah_mikro ADD COLUMN plafon_pembiayaan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER omzet_bulanan;
ALTER TABLE nasabah_mikro ADD COLUMN outstanding DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER plafon_pembiayaan;
ALTER TABLE nasabah_mikro ADD COLUMN target_outstanding DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER outstanding;
ALTER TABLE nasabah_mikro ADD COLUMN kolektibilitas TINYINT NOT NULL DEFAULT 1 AFTER target_outstanding;

-- Data lama (yang sudah tercatat sebelum migrasi ini) dianggap sudah berjalan normal,
-- supaya KPI tidak mendadak kosong / semua nyangkut di antrean approval.
UPDATE nasabah_mikro SET alamat = alamat_usaha WHERE alamat IS NULL OR alamat = '';
UPDATE nasabah_mikro SET status_pengajuan = 'Aktif' WHERE status_pengajuan = 'Approval' AND created_at < NOW();
UPDATE nasabah_mikro SET nama_ao = 'Belum Diisi' WHERE nama_ao IS NULL OR nama_ao = '';

CREATE INDEX idx_mikro_status_pengajuan ON nasabah_mikro (status_pengajuan);
CREATE INDEX idx_mikro_cabang ON nasabah_mikro (cabang);
