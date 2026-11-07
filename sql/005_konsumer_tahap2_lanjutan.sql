-- Migration: melengkapi kolom & tabel yang dibutuhkan untuk memfungsikan sisa
-- halaman placeholder Tahap 2 (Dashboard Konsumer): Dokumen, Angsuran, Jatuh Tempo,
-- Target vs Realisasi, Performa AO.
-- Jalankan sekali di database `database_pendataan-bsi`.
-- Catatan: syntax ini SENGAJA tidak pakai "IF NOT EXISTS" pada ADD COLUMN karena
-- baru didukung MySQL 8.0.29+ — supaya kompatibel dengan versi MySQL/MariaDB bawaan XAMPP.
-- Kalau kolom/tabel sudah pernah ditambahkan sebelumnya dan Anda run ulang, error
-- "Duplicate column name" / "Table already exists" itu aman diabaikan.

-- 1. Kolom nominal pembiayaan, tenor, pencairan & jatuh tempo pada nasabah_konsumer.
--    Sebelumnya nasabah_konsumer hanya punya pendapatan_bulanan (data pemohon),
--    belum ada nilai plafon yang diajukan/disetujui -- padahal ini basis untuk
--    modul Angsuran, Jatuh Tempo, Target vs Realisasi, dan Performa AO.
ALTER TABLE nasabah_konsumer ADD COLUMN plafon_pengajuan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER pendapatan_bulanan;
ALTER TABLE nasabah_konsumer ADD COLUMN tenor_bulan INT NOT NULL DEFAULT 12 AFTER plafon_pengajuan;
ALTER TABLE nasabah_konsumer ADD COLUMN tanggal_pencairan DATE NULL AFTER tenor_bulan;
ALTER TABLE nasabah_konsumer ADD COLUMN tanggal_jatuh_tempo DATE NULL AFTER tanggal_pencairan;

-- 2. Kolom checklist kelengkapan dokumen (pola sama seperti nasabah_mikro di 003_mikro_dokumen.sql).
ALTER TABLE nasabah_konsumer ADD COLUMN dok_ktp TINYINT(1) NOT NULL DEFAULT 0 AFTER tanggal_jatuh_tempo;
ALTER TABLE nasabah_konsumer ADD COLUMN dok_kk TINYINT(1) NOT NULL DEFAULT 0 AFTER dok_ktp;
ALTER TABLE nasabah_konsumer ADD COLUMN dok_slip_gaji TINYINT(1) NOT NULL DEFAULT 0 AFTER dok_kk;
ALTER TABLE nasabah_konsumer ADD COLUMN dok_jaminan TINYINT(1) NOT NULL DEFAULT 0 AFTER dok_slip_gaji;

-- Data lama yang sudah "disetujui" sebelum migrasi ini dianggap cair saat itu juga,
-- dengan jatuh tempo dihitung dari tenor default (12 bulan), supaya modul Angsuran/
-- Jatuh Tempo tidak mendadak kosong untuk data yang sudah berjalan.
UPDATE nasabah_konsumer
   SET tanggal_pencairan = COALESCE(updated_at, created_at),
       tanggal_jatuh_tempo = DATE_ADD(COALESCE(updated_at, created_at), INTERVAL tenor_bulan MONTH)
 WHERE status = 'disetujui' AND tanggal_jatuh_tempo IS NULL;

CREATE INDEX idx_konsumer_jatuh_tempo ON nasabah_konsumer (tanggal_jatuh_tempo);

-- 3. Tabel jadwal angsuran konsumer (belum pernah ada -- skema kredit konsumer
--    sebelumnya tidak punya jadwal cicilan sama sekali).
CREATE TABLE IF NOT EXISTS angsuran_konsumer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    konsumer_id INT NOT NULL,
    angsuran_ke INT NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    status ENUM('belum_bayar', 'lunas', 'terlambat') NOT NULL DEFAULT 'belum_bayar',
    tanggal_bayar DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_angsuran_konsumer_nasabah FOREIGN KEY (konsumer_id) REFERENCES nasabah_konsumer(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_angsuran_konsumer_ke (konsumer_id, angsuran_ke)
);
CREATE INDEX idx_angsuran_konsumer_status ON angsuran_konsumer (status);
CREATE INDEX idx_angsuran_konsumer_tempo ON angsuran_konsumer (tanggal_jatuh_tempo);

-- 4. Tabel target bulanan per petugas (AO), dipakai halaman Target vs Realisasi
--    (mirip konsep nasabah_mikro.target_outstanding tapi per-petugas per-bulan
--    karena segmen Konsumer tidak (belum) mengenal target per nasabah).
CREATE TABLE IF NOT EXISTS target_konsumer (
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
