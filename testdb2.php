<?php
mysqli_report(MYSQLI_REPORT_ERROR);
$link = mysqli_connect('127.0.0.1', 'root', '');
if (!$link) {
    die('Could not connect: ' . mysqli_connect_error());
}
echo 'Connected successfully';
$db = mysqli_select_db($link, 'database_pendataan-bsi');
if ($db) {
    echo 'Database selected successfully';
} else {
    die('Failed to select database');
}