<?php
mysqli_report(MYSQLI_REPORT_ERROR);
$link = mysqli_connect('127.0.0.1', 'root', '');
$db = mysqli_select_db($link, 'database_pendataan-bsi');
$result = mysqli_query($link, 'SHOW TABLES');
while ($row = mysqli_fetch_assoc($result)) {
    echo $row['Tables_in_database_pendataan-bsi'] . "\n";
}
?>