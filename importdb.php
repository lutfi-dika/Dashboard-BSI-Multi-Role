<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'database_pendataan-bsi';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sqlFile = 'sql/000_base_ready.sql';
$sqlContent = file_get_contents($sqlFile);

$statements = explode(';', $sqlContent);
foreach ($statements as $sql) {
    $sql = trim($sql);
    if (empty($sql) || strpos($sql, '--') === 0) {
        continue;
    }
    if ($conn->query($sql) === TRUE) {
    } else {
        echo "ERROR: " . substr($sql, 0, 100) . "... - " . $conn->error . "\n";
    }
}

// Insert sample data for nasabah_mikro
$sampleSql = "INSERT INTO nasabah_mikro (user_id, nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, lama_usaha_tahun, alamat_usaha, alamat, no_telepon, cabang, nama_ao, status_pengajuan, plafon_pembiayaan, outstanding, target_outstanding, kolektibilitas, dok_ktp, dok_kk, dok_izin_usaha, dok_foto_usaha, tanggal_jatuh_tempo, survey_status, survey_tanggal, survey_petugas, survey_skor, tenor_bulan, created_at) VALUES
(2, 'Suryani', 'Warung Sembako Berkah', 'Perdagangan', 15000000, 4, 'Jl. Mawar No. 12, Tambun', 'Jl. Mawar No. 12, Tambun', '081234567801', 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 30000000, 22000000, 30000000, 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'selesai', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Petugas Mikro', 82, 12, DATE_SUB(NOW(), INTERVAL 11 MONTH)),
(2, 'Ahmad Fauzi', 'Bengkel Motor Jaya', 'Jasa', 12000000, 2, 'Jl. Kenanga No. 5, Tambun', 'Jl. Kenanga No. 5, Tambun', '081234567802', 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 25000000, 25000000, 25000000, 2, 1, 0, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'selesai', DATE_SUB(CURDATE(), INTERVAL 200 DAY), 'Petugas Mikro', 68, 12, DATE_SUB(NOW(), INTERVAL 10 MONTH)),
(2, 'Rina Amelia', 'Katering Rina', 'Kuliner', 20000000, 6, 'Jl. Melati No. 8, Tambun', 'Jl. Melati No. 8, Tambun', '081234567803', 'BSI KCP Tambun', 'Petugas Mikro', 'Approval', 40000000, 0, 0, 1, 1, 0, 0, NULL, 'terjadwal', CURDATE(), 'Petugas Mikro', NULL, 12, NOW()),
(2, 'Budi Santoso', 'Konveksi Budi', 'Manufaktur', 8000000, 1, 'Jl. Anggrek No. 3, Tambun', 'Jl. Anggrek No. 3, Tambun', '081234567804', 'BSI KCP Tambun', 'Petugas Mikro', 'Ditolak', 15000000, 0, 0, 1, 0, 0, 0, NULL, 'belum', NULL, NULL, NULL, 12, NOW());";
    
if ($conn->query($sampleSql) === TRUE) {
    echo "Sample data inserted successfully\n";
} else {
    echo "Error inserting sample data: " . $conn->error . "\n";
}

$result = $conn->query("SHOW TABLES");
echo "Tables created: \n";
while ($row = $result->fetch_assoc()) {
    echo " - " . $row['Tables_in_database_pendataan-bsi'] . "\n";
}

$conn->close();
?>