<?php
require 'config/env.php';
$conn = mysqli_connect(env('DB_HOST', '127.0.0.1'), env('DB_USER', 'root'), env('DB_PASS', ''), env('DB_NAME', 'database_pendataan-bsi'));
$sql = 'SELECT id, username, role FROM users';
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['id'] . ' - ' . $row['username'] . ' - ' . $row['role'] . "\n";
}
?>