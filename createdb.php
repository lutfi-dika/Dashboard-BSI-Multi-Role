<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'database_pendataan-bsi';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Create users table
$sql = "CREATE TABLE IF NOT EXISTS `users` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    no_hp VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'mikro',
    otp_code VARCHAR(255) NULL,
    otp_expiry DATETIME NULL,
    otp_created_at DATETIME NULL,
    otp_purpose VARCHAR(30) NULL,
    otp_channel VARCHAR(10) NULL,
    otp_attempts INT NOT NULL DEFAULT 0,
    status ENUM('unverified', 'verified') NOT NULL DEFAULT 'unverified',
    notif_email TINYINT(1) NOT NULL DEFAULT 1,
    notif_wa TINYINT(1) NOT NULL DEFAULT 0,
    notif_jatuh_tempo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_username (username),
    UNIQUE KEY uniq_users_email (email)
);";

if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully\n";
} else {
    echo "Error creating users: " . $conn->error . "\n";
}

// 2. Create nasabah_mikro table
$sql = "CREATE TABLE IF NOT EXISTS `nasabah_mikro` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    nama_pemilik VARCHAR(150) NOT NULL,
    nama_usaha VARCHAR(150) NOT NULL,
    jenis_sektor VARCHAR(100) NULL,
    omzet_bulanan DECIMAL(15,2) NOT NULL DEFAULT 0,
    lama_usaha_tahun INT NOT NULL DEFAULT 0,
    alamat_usaha VARCHAR(255) NULL,
    alamat VARCHAR(255) NULL,
    no_telepon VARCHAR(20) NULL,
    cabang VARCHAR(100) NOT NULL DEFAULT 'BSI KCP Tambun',
    nama_ao VARCHAR(150) NULL,
    status_pengajuan ENUM('Approval', 'Aktif', 'Ditolak') NOT NULL DEFAULT 'Approval',
    plafon_pembiayaan DECIMAL(15,2) NOT NULL DEFAULT 0,
    outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
    target_outstanding DECIMAL(15,2) NOT NULL DEFAULT 0,
    kolektibilitas TINYINT NOT NULL DEFAULT 1,
    dok_ktp TINYINT(1) NOT NULL DEFAULT 0,
    dok_kk TINYINT(1) NOT NULL DEFAULT 0,
    dok_izin_usaha TINYINT(1) NOT NULL DEFAULT 0,
    dok_foto_usaha TINYINT(1) NOT NULL DEFAULT 0,
    tanggal_jatuh_tempo DATE NULL,
    survey_status ENUM('belum', 'terjadwal', 'selesai') NOT NULL DEFAULT 'belum',
    survey_tanggal DATE NULL,
    survey_petugas VARCHAR(150) NULL,
    survey_skor TINYINT NULL,
    survey_catatan TEXT NULL,
    tenor_bulan INT NOT NULL DEFAULT 12,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_nasabah_mikro_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_mikro_status_pengajuan (status_pengajuan),
    INDEX idx_mikro_cabang (cabang),
    INDEX idx_mikro_survey_status (survey_status)
);";

if ($conn->query($sql) === TRUE) {
    echo "Table 'nasabah_mikro' created successfully\n";
} else {
    echo "Error creating nasabah_mikro: " . $conn->error . "\n";
}

// 3. Create angsuran_mikro table
$sql = "CREATE TABLE IF NOT EXISTS `angsuran_mikro` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mikro_id INT NOT NULL,
    angsuran_ke INT NOT NULL,
    tanggal_jatuh_tempo DATE NOT NULL,
    nominal DECIMAL(15,2) NOT NULL,
    status ENUM('belum_bayar', 'lunas', 'terlambat') NOT NULL DEFAULT 'belum_bayar',
    tanggal_bayar DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_angsuran_mikro_nasabah FOREIGN KEY (mikro_id) REFERENCES nasabah_mikro(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_angsuran_mikro_ke (mikro_id, angsuran_ke),
    INDEX idx_angsuran_mikro_status (status),
    INDEX idx_angsuran_mikro_tempo (tanggal_jatuh_tempo)
);";

if ($conn->query($sql) === TRUE) {
    echo "Table 'angsuran_mikro' created successfully\n";
} else {
    echo "Error creating angsuran_mikro: " . $conn->error . "\n";
}

// 4. Insert sample users (IGNORE duplicate)
$passwordHash = '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS';
$sql = "INSERT IGNORE INTO `users` (nama_lengkap, username, password, email, role, status) VALUES
('Admin Utama', 'admin', '$passwordHash', 'admin@bsi.local', 'admin', 'verified'),
('Petugas Mikro', 'petugas.mikro', '$passwordHash', 'petugas.mikro@bsi.local', 'mikro', 'verified'),
('Atasan Cabang', 'atasan', '$passwordHash', 'atasan@bsi.local', 'atasan', 'verified');";

if ($conn->query($sql) === TRUE) {
    echo "Sample users inserted (or already existed)\n";
} else {
    echo "Error inserting users: " . $conn->error . "\n";
}

// 5. Insert sample nasabah_mikro data - with explicit column list
$sql = "INSERT INTO `nasabah_mikro` (user_id, nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, lama_usaha_tahun, alamat_usaha, alamat, no_telepon, cabang, nama_ao, status_pengajuan, plafon_pembiayaan, outstanding, target_outstanding, kolektibilitas, dok_ktp, dok_kk, dok_izin_usaha, dok_foto_usaha, tanggal_jatuh_tempo, survey_status, survey_tanggal, survey_petugas, survey_skor, survey_catatan, tenor_bulan, created_at) VALUES
(2, 'Suryani', 'Warung Sembako Berkah', 'Perdagangan', 15000000, 4, 'Jl. Mawar No. 12, Tambun', 'Jl. Mawar No. 12, Tambun', '081234567801', 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 30000000, 22000000, 30000000, 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'selesai', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Petugas Mikro', 82, NULL, 12, NOW()),
(2, 'Ahmad Fauzi', 'Bengkel Motor Jaya', 'Jasa', 12000000, 2, 'Jl. Kenanga No. 5, Tambun', 'Jl. Kenanga No. 5, Tambun', '081234567802', 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 25000000, 25000000, 25000000, 2, 1, 0, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'selesai', DATE_SUB(CURDATE(), INTERVAL 200 DAY), 'Petugas Mikro', 68, NULL, 12, DATE_SUB(NOW(), INTERVAL 10 MONTH)),
(2, 'Rina Amelia', 'Katering Rina', 'Kuliner', 20000000, 6, 'Jl. Melati No. 8, Tambun', 'Jl. Melati No. 8, Tambun', '081234567803', 'BSI KCP Tambun', 'Petugas Mikro', 'Approval', 40000000, 0, 0, 1, 1, 0, 0, NULL, 'terjadwal', CURDATE(), 'Petugas Mikro', NULL, NULL, 12, NOW()),
(2, 'Budi Santoso', 'Konveksi Budi', 'Manufaktur', 8000000, 1, 'Jl. Anggrek No. 3, Tambun', 'Jl. Anggrek No. 3, Tambun', '081234567804', 'BSI KCP Tambun', 'Petugas Mikro', 'Ditolak', 15000000, 0, 0, 1, 0, 0, 0, NULL, 'belum', NULL, NULL, NULL, 12, NOW());";

if ($conn->query($sql) === TRUE) {
    echo "Sample nasabah_mikro data inserted successfully\n";
} else {
    echo "Error inserting nasabah_mikro: " . $conn->error . "\n";
}

// Verify
$result = $conn->query("SHOW TABLES");
echo "\nTables created:\n";
while ($row = $result->fetch_assoc()) {
    echo " - " . $row['Tables_in_database_pendataan-bsi'] . "\n";
}

// Count rows
$result2 = $conn->query("SELECT COUNT(*) as cnt FROM users");
$row2 = $result2->fetch_assoc();
echo "Users count: " . $row2['cnt'] . "\n";

$result3 = $conn->query("SELECT COUNT(*) as cnt FROM nasabah_mikro");
$row3 = $result3->fetch_assoc();
echo "Nasabah mikrok count: " . $row3['cnt'] . "\n";

$conn->close();
echo "\nDone!\n";
?>