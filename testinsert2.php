<?php
$conn = mysqli_connect('127.0.0.1', 'root', '');
mysqli_select_db($conn, 'database_pendataan-bsi');

// Try without specifying columns - provide all values in order
$sql = "INSERT INTO nasabah_mikro VALUES
(NULL, 2, 'Suryani', 'Warung Sembako Berkah', 'Perdagangan', 15000000, 4, 'Jl. Mawar No. 12, Tambun', 'Jl. Mawar No. 12, Tambun', '081234567801', 'BSI KCP Tambun', 'Petugas Mikro', 'Aktif', 30000000, 22000000, 30000000, 1, 1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'selesai', DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Petugas Mikro', 82, NULL, 12, NOW())";

if (mysqli_query($conn, $sql) === TRUE) {
    echo "Insert succeeded\n";
} else {
    echo "Insert failed: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>