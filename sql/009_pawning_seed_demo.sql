-- =====================================================================
-- 009_pawning_seed_demo.sql
-- Seed data DEMO agar seluruh fitur admin/pawning/* langsung terisi
-- (pelunasan, penebusan, perpanjangan, lelang, taksiran, jatuh tempo,
-- notifikasi, ujrah, outstanding, laporan, export).
--
-- Data memakai referensi tanggal hari ini: 2026-08-10.
-- Aman dijalankan berkali-kali (INSERT IGNORE dengan id eksplisit).
-- =====================================================================

USE `database_pendataan-bsi`;

-- ---------------------------------------------------------------------
-- 1. Perkaya data nasabah gadai (id 5-15)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `pawning`
(id, nama_pemilik, barang_jaminan, taksiran_harga, nilai_pinjaman, status, no_telepon,
 tanggal_gadai, masa_gadai_hari, tanggal_jatuh_tempo, nilai_ujrah, keterangan, created_at, updated_at) VALUES
(5,  'Dewi Lestari',    'Emas 24 karat 5 gram',        7500000,  5500000,  'Aktif',  '081234569805', '2026-07-25', 120, '2026-11-22', 82500,  'Anting emas 24 karat kadar 99.9%',       '2026-07-25 09:10:00', NOW()),
(6,  'Bambang Priyanto','Emas 22 karat 25 gram',       22000000, 16000000, 'Aktif',  '081234569806', '2026-03-15', 120, '2026-07-13', 240000, 'Perhiasan kalung emas 22 karat 25 gram', '2026-03-15 10:25:00', NOW()),
(7,  'Siti Aminah',     'BPKB Toyota Avanza 2018',     90000000, 55000000, 'Aktif',  '081234569807', '2026-08-01', 120, '2026-11-29', 825000, 'BPKB asli atas nama sendiri',             '2026-08-01 13:40:00', NOW()),
(8,  'Hasan Basri',     'Emas 22 karat 10 gram',       9000000,  6800000,  'Lunas',  '081234569808', '2026-01-20', 120, '2026-05-20', 102000, 'Cincin emas 22 karat 10 gram',            '2026-01-20 08:05:00', NOW()),
(9,  'Putri Maharani',  'Emas 22 karat 7 gram',        6300000,  4500000,  'Aktif',  '081234569809', '2026-04-10', 120, '2026-08-08', 67500,  'Gelang emas 22 karat 7 gram',             '2026-04-10 11:15:00', NOW()),
(10, 'Joko Susilo',     'Emas 24 karat 3 gram',        4500000,  3200000,  'Aktif',  '081234569810', '2026-04-13', 120, '2026-08-11', 48000,  'Logam mulia batangan 3 gram',             '2026-04-13 09:55:00', NOW()),
(11, 'Maya Anggraini',  'Laptop MacBook Air 2023',     18000000, 11000000, 'Aktif',  '081234569811', '2026-04-24', 120, '2026-08-22', 165000, 'Laptop dalam kondisi baik',               '2026-04-24 14:30:00', NOW()),
(12, 'Ratna Dewi',      'Emas 22 karat 8 gram',        7200000,  5000000,  'Lunas',  '081234569812', '2026-05-05', 120, '2026-09-02', 75000,  'Liontin emas 22 karat 8 gram',            '2026-05-05 10:05:00', NOW()),
(13, 'Ahmad Fauzi',     'Emas 24 karat 12 gram',       13000000, 9500000,  'Aktif',  '081234569813', '2026-05-01', 150, '2026-09-28', 142500, 'Logam mulia batangan 12 gram',            '2026-05-01 09:20:00', NOW()),
(14, 'Tono Wijaya',     'Emas 22 karat 9 gram',        8100000,  6000000,  'Lelang', '081234569814', '2026-02-01', 120, '2026-05-31', 90000,  'Cincin pria emas 22 karat 9 gram',        '2026-02-01 15:10:00', NOW()),
(15, 'Farida Hanum',    'Emas 24 karat 6 gram',        6600000,  4800000,  'Aktif',  '081234569815', '2026-04-20', 150, '2026-10-17', 72000,  'Anting dan kalung emas 6 gram',           '2026-04-20 11:45:00', NOW());

-- ---------------------------------------------------------------------
-- 2. Riwayat pelunasan / penebusan (sesuai status Lunas)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `pawning_pelunasan`
(pawning_id, tipe, pokok, ujrah, denda, total_bayar, metode_bayar, user_id, created_at) VALUES
(3,  'Pelunasan', 8500000,  127500, 0,     8627500,  'Tunai',   1, '2026-07-01 10:00:00'),
(8,  'Pelunasan', 6800000,  204000, 0,     7004000,  'Transfer', 1, '2026-05-28 13:20:00'),
(12, 'Penebusan', 5000000,  45000,  0,     5045000,  'Debit',   1, '2026-07-30 09:15:00');

-- ---------------------------------------------------------------------
-- 3. Riwayat perpanjangan masa gadai
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `pawning_perpanjangan`
(pawning_id, perpanjangan_hari, jatuh_tempo_lama, jatuh_tempo_baru, ujrah_bayar, user_id, created_at) VALUES
(13, 30, '2026-08-29', '2026-09-28', 142500, 1, '2026-08-05 10:30:00'),
(15, 60, '2026-08-18', '2026-10-17', 144000, 1, '2026-08-01 14:00:00');

-- ---------------------------------------------------------------------
-- 4. Riwayat lelang (sesuai status Lelang)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `pawning_lelang`
(pawning_id, tanggal_lelang, harga_terjual, pemenang, keterangan, user_id, created_at) VALUES
(4,  '2026-04-25', 10500000, 'Budi Santoso',   'Lelang publik cabang',    1, '2026-04-25 10:00:00'),
(14, '2026-06-15', 7800000,  'Samsul Arifin',  'Lelang online BSI',       1, '2026-06-15 11:00:00');

-- ---------------------------------------------------------------------
-- 5. Riwayat perubahan nilai taksiran
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `pawning_taksiran`
(pawning_id, taksiran_lama, taksiran_baru, user_id, created_at) VALUES
(1, 12000000, 13500000, 1, '2026-07-15 09:00:00'),
(2, 8500000,  9000000,  1, '2026-07-30 13:30:00'),
(7, 95000000, 90000000, 1, '2026-08-02 10:10:00');

-- ---------------------------------------------------------------------
-- 6. Foto barang (file dibuat terpisah di uploads/pawning)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `pawning_foto` (pawning_id, nama_file, keterangan, user_id, created_at) VALUES
(1, 'pawning_1_foto_depan.png', 'Tampak depan emas 22 karat 15 gram', 1, '2026-06-10 12:50:00'),
(2, 'pawning_2_laptop.png',     'Laptop ASUS ROG lengkap dengan charger', 1, '2026-07-21 13:00:00'),
(7, 'pawning_7_bpkb.png',       'BPKB Toyota Avanza atas nama pemilik', 1, '2026-08-01 13:45:00');
