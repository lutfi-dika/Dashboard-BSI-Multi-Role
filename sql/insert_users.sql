-- Install database lengkap
USE database_pendataan-bsi;

-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
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
);

-- Insert seed accounts
INSERT INTO `users` (nama_lengkap, username, password, email, role, status) VALUES
('Admin Utama',      'admin',         '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'admin@bsi.local',       'admin',    'verified'),
('Petugas Mikro',    'petugas.mikro', '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'petugas.mikro@bsi.local','mikro',   'verified'),
('Atasan Cabang',    'atasan',        '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'atasan@bsi.local',      'atasan',   'verified'),
('Petugas Konsumer', 'petugas.konsumer','$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS','petugas.konsumer@bsi.local','konsumer','verified'),
('Petugas Gadai',    'petugas.gadai', '$2y$10$O0ln4NhYmDm7IyQ2ffu2beE.Q7MHiN/n5.GKgwIsQxlUOk.dQIvnS', 'petugas.gadai@bsi.local','pawning',  'verified');