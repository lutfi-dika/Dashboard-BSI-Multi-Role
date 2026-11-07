<?php
require 'config/env.php';
$conn = @mysqli_connect('127.0.0.1', 'root', '', 'database_pendataan-bsi', 3306);
if ($conn) {
    echo "Connected successfully\n";
} else {
    echo "Connection failed: " . mysqli_connect_error() . "\n";
}