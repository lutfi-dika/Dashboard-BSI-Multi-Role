<?php
$conn = mysqli_connect('127.0.0.1', 'root', '');
mysqli_select_db($conn, 'database_pendataan-bsi');

// Test with DATE_ADD
$sql = "INSERT INTO nasabah_mikro (user_id, nama_pemilik, nama_usaha, tanggal_jatuh_tempo) VALUES (2, 'Test', 'Test Usaha', DATE_ADD(CURDATE(), INTERVAL 20 DAY))";

if (mysqli_query($conn, $sql) === TRUE) {
    echo "DATE_ADD insert succeeded\n";
} else {
    echo "DATE_ADD insert failed: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>