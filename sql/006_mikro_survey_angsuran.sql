-- Migration: melengkapi kolom & tabel yang dibutuhkan untuk memfungsikan sisa
-- halaman placeholder Dashboard Mikro: Survey Lapangan, Analisa Pembiayaan,
-- Monitoring Angsuran, Laporan, AI Assistant.
-- Jalankan sekali di database `database_pendataan-bsi`.
-- Catatan: syntax ini SENGAJA tidak pakai "IF NOT EXISTS" pada ADD COLUMN karena
-- baru didukung MySQL 8.0.29+ -- supaya kompatibel dengan versi MySQL/MariaDB bawaan XAMPP.
-- Kalau kolom/tabel sudah pernah ditambahkan sebelumnya dan Anda run ulang, error
-- "Duplicate column name" / "Table already exists" itu aman diabaikan.

-- 1. Kolom hasil survey lapangan (dipakai admin/Mikro/mikro-survey.php).
ALTER TABLE nasabah_mikro ADD COLUMN survey_status ENUM('belum','terjadwal','selesai') NOT NULL DEFAULT 'belum' AFTER tanggal_jatuh_tempo;
ALTER TABLE nasabah_mikro ADD COLUMN survey_tanggal DATE NULL AFTER survey_status;
ALTER TABLE nasabah_mikro ADD COLUMN survey_petugas VARCHAR(150) NULL AFTER survey_tanggal;
ALTER TABLE nasabah_mikro ADD COLUMN survey_skor TINYINT NULL AFTER survey_petugas;
ALTER TABLE nasabah_mikro ADD COLUMN survey_catatan TEXT NULL AFTER survey_skor;

-- Nasabah yang sudah Aktif sebelum migrasi ini dianggap sudah pernah disurvey.
UPDATE nasabah_mikro SET survey_status = 'selesai' WHERE status_pengajuan = 'Aktif' AND survey_status = 'belum';

CREATE INDEX idx_mikro_survey_status ON nasabah_mikro (survey_status);

-- 2. Tenor pembiayaan (dipakai admin/Mikro/mikro-monitoring.php untuk generate jadwal angsuran).
ALTER TABLE nasabah_mikro ADD COLUMN tenor_bulan INT NOT NULL DEFAULT 12 AFTER plafon_pembiayaan;

-- 3. Tabel jadwal angsuran mikro (pola sama seperti angsuran_konsumer di 005_konsumer_tahap2_lanjutan.sql).
CREATE TABLE IF NOT EXISTS angsuran_mikro (
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
