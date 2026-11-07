<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR);
$link = mysqli_connect('127.0.0.1', 'root', '');
if (!$link) {
    die('Connect Error: ' . mysqli_connect_error());
}
$db = mysqli_select_db($link, 'database_pendataan-bsi');
if (!$db) {
    die('Select DB Error: ' . mysqli_error($link));
}
$result = mysqli_query($link, 'SHOW TABLES');
if (!$result) {
    die('Query Error: ' . mysqli_error($link));
}
$tables = mysqli_fetch_all($result, MYSQLI_ASSOC);
echo "Tables count: " . count($tables) . "\n";
foreach ($tables as $row) {
    echo "Table: " . $row['Tables_in_database_pendataan-bsi'] . "\n";
}
?>