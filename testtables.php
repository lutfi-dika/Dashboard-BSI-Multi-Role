<?php
require 'config/env.php';
$conn = mysqli_connect('127.0.0.1', 'root', '', 'database_pendataan-bsi', 3306);
$result = mysqli_query($conn, 'SHOW TABLES');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Tables_in_database_pendataan-bsi'] . "\n";
}
?>