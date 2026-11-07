-- ============================================================
-- 010_bsi_produk.sql
-- Menambahkan skema produk Bank Syariah Indonesia (BSI) pada
-- segmen Mikro, Konsumer, dan Gadai (Pawning).
-- ------------------------------------------------------------
-- Produk BSI Mikro  : KUR Super Mikro, KUR Mikro, KUR Kecil, BSI Mikro iB
-- Produk BSI Konsumer: BSI Griya iB, BSI OTO iB, BSI Pensiun iB,
--                      BSI Umroh iB, BSI Multiguna iB, BSI Fleksi iB
-- Produk BSI Gadai  : Gadai Emas iB, Gadai Non Emas, Fleksi Emas iB
-- ============================================================

-- 1. Kolom produk pada tabel nasabah_mikro
ALTER TABLE nasabah_mikro
    ADD COLUMN produk ENUM('KUR Super Mikro', 'KUR Mikro', 'KUR Kecil', 'BSI Mikro iB')
    NOT NULL DEFAULT 'BSI Mikro iB' AFTER jenis_sektor;

-- 2. Kolom jenis_gadai pada tabel pawning
ALTER TABLE pawning
    ADD COLUMN jenis_gadai ENUM('Gadai Emas iB', 'Gadai Non Emas', 'Fleksi Emas iB')
    NOT NULL DEFAULT 'Gadai Emas iB' AFTER barang_jaminan;

-- 3. Standardisasi nama produk Konsumer ke merek produk BSI
UPDATE nasabah_konsumer SET produk_diajukan = 'BSI Griya iB'   WHERE produk_diajukan = 'KPR Konsumer';
UPDATE nasabah_konsumer SET produk_diajukan = 'BSI OTO iB'     WHERE produk_diajukan = 'KKB Konsumer';
UPDATE nasabah_konsumer SET produk_diajukan = 'BSI Multiguna iB' WHERE produk_diajukan = 'Multiguna Konsumer';

-- 4. Klasifikasi produk Mikro berdasarkan plafon (skema KUR BSI)
UPDATE nasabah_mikro SET produk = 'KUR Super Mikro' WHERE plafon_pembiayaan > 0 AND plafon_pembiayaan <= 10000000;
UPDATE nasabah_mikro SET produk = 'KUR Mikro'       WHERE plafon_pembiayaan > 10000000 AND plafon_pembiayaan <= 100000000;
UPDATE nasabah_mikro SET produk = 'KUR Kecil'       WHERE plafon_pembiayaan > 100000000 AND plafon_pembiayaan <= 500000000;
UPDATE nasabah_mikro SET produk = 'BSI Mikro iB'    WHERE produk = 'BSI Mikro iB' AND (plafon_pembiayaan > 500000000 OR plafon_pembiayaan <= 0);

-- 5. Klasifikasi jenis gadai berdasarkan barang jaminan
UPDATE pawning SET jenis_gadai = 'Gadai Non Emas'
    WHERE barang_jaminan LIKE '%BPKB%' OR barang_jaminan LIKE '%Laptop%'
       OR barang_jaminan LIKE '%laptop%' OR barang_jaminan LIKE '%Motor%'
       OR barang_jaminan LIKE '%Sepeda%' OR barang_jaminan LIKE '%sepeda%';

-- ============================================================
-- DATA CONTOH PRODUK BSI (agar seluruh fitur dashboard terisi)
-- ============================================================

-- 5a. Data contoh Mikro per produk KUR
INSERT INTO nasabah_mikro
(nama_pemilik, nama_usaha, jenis_sektor, produk, omzet_bulanan, plafon_pembiayaan, tenor_bulan, outstanding, target_outstanding, kolektibilitas, lama_usaha_tahun, alamat_usaha, alamat, cabang, nama_ao, status_pengajuan, no_telepon, user_id, survey_status, tanggal_jatuh_tempo, created_at)
VALUES
('Rina Kusuma',  'Warung Sembako Rina',  'Perdagangan', 'KUR Super Mikro', 8500000.00,  8000000.00,  12, 4800000.00, 100000000.00, 1, 2, 'Jl. Raya Tambun No. 12, Bekasi',     'Jl. Raya Tambun No. 12, Bekasi',     'BSI KCP Tambun',   'Ahmad Fauzi', 'Aktif',  '081234567101', 1, 'selesai', DATE_ADD(CURDATE(), INTERVAL 8 MONTH),  DATE_SUB(CURDATE(), INTERVAL 4 MONTH)),
('Budi Hartono', 'Kedai Kopi Nusantara', 'Jasa',        'KUR Super Mikro', 12500000.00,  9000000.00,  12, 6200000.00, 100000000.00, 1, 3, 'Jl. Melati No. 5, Cibitung',         'Jl. Melati No. 5, Cibitung',         'BSI KCP Tambun',   'Siti Nurhaliza', 'Aktif', '081234567102', 1, 'selesai', DATE_ADD(CURDATE(), INTERVAL 9 MONTH),  DATE_SUB(CURDATE(), INTERVAL 3 MONTH)),
('Dewi Anggraini','Toko Bangunan Dwi',   'Perdagangan', 'KUR Mikro',       45000000.00,  50000000.00, 36, 38500000.00, 200000000.00, 1, 5, 'Jl. Imam Bonjol No. 88, Bekasi',     'Jl. Imam Bonjol No. 88, Bekasi',     'BSI KCP Tambun',   'Ahmad Fauzi', 'Aktif',  '081234567103', 1, 'selesai', DATE_ADD(CURDATE(), INTERVAL 20 MONTH), DATE_SUB(CURDATE(), INTERVAL 16 MONTH)),
('Joko Santoso', 'Peternakan Jaya',      'Pertanian',   'KUR Mikro',       38000000.00,  40000000.00, 24, 31000000.00, 200000000.00, 2, 6, 'Jl. Rawa Bebek No. 21, Karawang',    'Jl. Rawa Bebek No. 21, Karawang',    'BSI KCP Karawang', 'Rizky Ramadhan', 'Aktif','081234567104', 1, 'selesai', DATE_ADD(CURDATE(), INTERVAL 15 MONTH), DATE_SUB(CURDATE(), INTERVAL 9 MONTH)),
('Sri Wahyuni',  'Pabrik Roti Sri',      'Manufaktur',  'KUR Kecil',       95000000.00, 150000000.00, 48, 118000000.00, 300000000.00, 1, 8, 'Kawasan Industri Jababeka Cikarang', 'Kawasan Industri Jababeka Cikarang', 'BSI KCP Cikarang', 'Deni Firmansyah', 'Aktif','081234567105', 1, 'selesai', DATE_ADD(CURDATE(), INTERVAL 30 MONTH), DATE_SUB(CURDATE(), INTERVAL 18 MONTH)),
('Agus Salim',   'Distributor Beras',    'Perdagangan', 'KUR Kecil',       82000000.00, 120000000.00, 36, 102000000.00, 300000000.00, 2, 7, 'Jl. Cikarang Baru No. 44, Bekasi',   'Jl. Cikarang Baru No. 44, Bekasi',   'BSI KCP Cikarang', 'Deni Firmansyah', 'Aktif','081234567106', 1, 'selesai', DATE_ADD(CURDATE(), INTERVAL 26 MONTH), DATE_SUB(CURDATE(), INTERVAL 14 MONTH)),
('Lina Marlina', 'Studio Bridal Lina',   'Jasa',        'BSI Mikro iB',    60000000.00,  75000000.00, 24, 68000000.00, 150000000.00, 1, 4, 'Jl. Ahmad Yani No. 3, Tambun',       'Jl. Ahmad Yani No. 3, Tambun',       'BSI KCP Tambun',   'Siti Nurhaliza', 'Aktif','081234567107', 1, 'selesai', DATE_ADD(CURDATE(), INTERVAL 18 MONTH), DATE_SUB(CURDATE(), INTERVAL 12 MONTH)),
('Rahmat Hidayat','Bengkel Motor Baru',  'Jasa',        'BSI Mikro iB',    55000000.00, 60000000.00, 36, 52000000.00, 150000000.00, 2, 5, 'Jl. Kaliabang No. 9, Bekasi Utara',  'Jl. Kaliabang No. 9, Bekasi Utara',  'BSI KCP Tambun',   'Ahmad Fauzi', 'Aktif',  '081234567108', 1, 'terjadwal', DATE_ADD(CURDATE(), INTERVAL 22 MONTH), DATE_SUB(CURDATE(), INTERVAL 10 MONTH));

-- 5b. Data contoh Konsumer per produk BSI
INSERT INTO nasabah_konsumer
(nama_lengkap, nik, pekerjaan, pendapatan_bulanan, plafon_pengajuan, tenor_bulan, produk_diajukan, alamat_rumah, no_telepon, status, user_id, created_at)
VALUES
('Rahma Anisa',      '3201012345670005', 'Karyawan Swasta', 25000000.00, 750000000.00, 240, 'BSI Griya iB',     'Perumahan Green Lake, Tambun',     '081234567201', 'disetujui', 1, DATE_SUB(CURDATE(), INTERVAL 20 MONTH)),
('Andi Prasetyo',    '3201012345670006', 'Wiraswasta',      18500000.00, 320000000.00,  60, 'BSI OTO iB',       'Jl. Hasanudin No. 7, Bekasi',      '081234567202', 'disetujui', 1, DATE_SUB(CURDATE(), INTERVAL 14 MONTH)),
('Hj. Maryam',       '3201012345670007', 'Pensiunan PNS',   12000000.00, 100000000.00,  36, 'BSI Pensiun iB',   'Jl. Pahlawan No. 15, Tambun',      '081234567203', 'disetujui', 1, DATE_SUB(CURDATE(), INTERVAL 8 MONTH)),
('Fatimah Azzahra',  '3201012345670008', 'Ibu Rumah Tangga',15000000.00,  35000000.00,  24, 'BSI Umroh iB',     'Jl. Kenanga No. 2, Cibitung',      '081234567204', 'pending',   1, DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
('Nur Aisyah',       '3201012345670009', 'Karyawan BUMN',   22000000.00,  85000000.00,  48, 'BSI Fleksi iB',    'Jl. Sukatani No. 11, Bekasi',      '081234567205', 'pending',   1, DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
('Herman Susanto',   '3201012345670010', 'Pengusaha',       18000000.00, 200000000.00,  60, 'BSI Multiguna iB', 'Jl. Bahagia No. 19, Karawang',     '081234567206', 'pending',   1, DATE_SUB(CURDATE(), INTERVAL 1 MONTH));

-- 5c. Data contoh Gadai per jenis produk BSI
INSERT INTO pawning
(nama_pemilik, barang_jaminan, jenis_gadai, taksiran_harga, nilai_pinjaman, status, no_telepon, tanggal_gadai, masa_gadai_hari, tanggal_jatuh_tempo, nilai_ujrah, user_id, created_at)
VALUES
('Siti Aminah',    'Emas batangan ANTAM 25 gram',       'Fleksi Emas iB', 42000000.00, 33000000.00, 'Aktif', '081234567301', DATE_SUB(CURDATE(), INTERVAL 2 MONTH),  120, DATE_ADD(CURDATE(), INTERVAL 2 MONTH),  1650000.00, 1, DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
('Rudi Hermawan',  'Emas 23 karat cincin 15 gram',       'Fleksi Emas iB', 23500000.00, 18000000.00, 'Aktif', '081234567302', DATE_SUB(CURDATE(), INTERVAL 3 MONTH),  120, DATE_ADD(CURDATE(), INTERVAL 1 MONTH),   900000.00, 1, DATE_SUB(CURDATE(), INTERVAL 3 MONTH)),
('Bambang Wibowo', 'BPKB Toyota Avanza 2020',            'Gadai Non Emas', 185000000.00, 110000000.00, 'Aktif', '081234567303', DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 120, DATE_ADD(CURDATE(), INTERVAL 3 MONTH),  5500000.00, 1, DATE_SUB(CURDATE(), INTERVAL 1 MONTH));
