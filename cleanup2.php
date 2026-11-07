<?php
require 'config/env.php';
$c = mysqli_connect(env('DB_HOST', '127.0.0.1'), env('DB_USER','root'), env('DB_PASS',''), env('DB_NAME','database_pendataan-bsi'));
mysqli_query($c, "DELETE FROM nasabah_mikro WHERE id > 3");
mysqli_query($c, "DELETE FROM angsuran_mikro");
echo "Cleaned up\n";
mysqli_close($c);
?>