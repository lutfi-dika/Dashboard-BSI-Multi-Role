<?php
$conn = mysqli_connect('127.0.0.1', 'root', '');
mysqli_select_db($conn, 'database_pendataan-bsi');

// Test simple insert
$sql = "INSERT INTO nasabah_mikro (user_id, nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, lama_usaha_tahun, alamat_usaha, alamat, no_telepon, cabang, nama_ao, status_pengajuan, plafon_pembiayaan, outstanding, target_outstanding, kolektibilitas, dok_ktp, dok_kk, dok_izin_usaha, dok_foto_usaha, tanggal_jatuh_tempo, survey_status, survey_tanggal, survey_petugas, survey_skor, survey_catatan, tenor_bulan, created_at) VALUES
(2, 'Test', 'Test Usaha', 'Perdagangan', 1000000, 1, 'Jl. Test', 'Jl. Test', '0812345678', 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 5000000, 4000000, 10000000, 1, 1, 1, 1, '2024-01-01', 'selesai', '2023-01-01', 'Petugas', 5, NULL, 12, NOW())";

if (mysqli_query($conn, $sql) === TRUE) {
    echo "Insert succeeded\n";
} else {
    echo "Insert failed: " . mysqli_error($conn) . "\n";
}

// Check row count
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM nasabah_mikro");
$row = mysqli_fetch_assoc($result);
echo "Total rows: " . $row['cnt'] . "\n";

mysqli_close($conn);
?>