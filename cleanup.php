<?php
require 'config/env.php';
$conn = mysqli_connect(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''), env('DB_NAME', 'database_pendataan-bsi'));
mysqli_query($conn, "DELETE FROM nasabah_mikro WHERE nama_pemilik = 'Test'");
echo "Cleaned up test data\n";
mysqli_close($conn);
?>