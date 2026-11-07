<?php
require 'config/env.php';
$conn = mysqli_connect(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''), env('DB_NAME', 'database_pendataan-bsi'));
$sql = 'SELECT id, nama_pemilik, nama_usaha, status_pengajuan FROM nasabah_mikro';
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['id'] . ' - ' . $row['nama_pemilik'] . ' - ' . $row['nama_usaha'] . ' - ' . $row['status_pengajuan'] . "\n";
}
?>