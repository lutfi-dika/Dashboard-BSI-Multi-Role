<?php
require 'config/env.php';
$c = mysqli_connect(env('DB_HOST', '127.0.0.1'), env('DB_USER','root'), env('DB_PASS',''), env('DB_NAME','database_pendataan-bsi'));
$result = mysqli_query($c, "SELECT COUNT(*) as cnt FROM angsuran_mikro");
$row = mysqli_fetch_assoc($result);
echo "angsuran_mikro rows: " . $row['cnt'] . "\n";
mysqli_close($c);
?>