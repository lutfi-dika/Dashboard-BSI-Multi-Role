<?php
$conn = mysqli_connect('127.0.0.1', 'root', '');
$db = mysqli_select_db($conn, 'database_pendataan-bsi');
$result = mysqli_query($conn, 'SHOW TABLES');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Tables_in_database_pendataan-bsi'] . "\n";
}
?>