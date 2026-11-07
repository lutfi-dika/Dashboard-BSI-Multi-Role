<?php
$conn = mysqli_connect('127.0.0.1', 'root', '');
mysqli_select_db($conn, 'database_pendataan-bsi');

// Very simple test - just a few columns
$sql = "INSERT INTO nasabah_mikro (nama_pemilik, nama_usaha) VALUES ('Test', 'Test Usaha')";

if (mysqli_query($conn, $sql) === TRUE) {
    echo "Simple insert succeeded\n";
} else {
    echo "Simple insert failed: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>