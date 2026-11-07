<?php
// config/database.php
require_once __DIR__ . '/env.php';

// PHP 8.1+ default: mysqli melempar exception saat connect gagal, bukan return false.
// Matikan mode itu supaya kompatibel dengan pola "if (!$conn)" / "if (!$stmt->execute())"
// yang dipakai di seluruh project ini, dan supaya kita bisa kasih pesan error yang rapi.
mysqli_report(MYSQLI_REPORT_OFF);

$host = env('DB_HOST', '127.0.0.1');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');
$db   = env('DB_NAME', 'database_pendataan-bsi'); 
$port = env('DB_PORT', '3306');

$conn = @mysqli_connect($host, $user, $pass, $db, (int) $port);

if (!$conn) {
    // Jangan bocorkan detail koneksi ke publik pada production
    error_log('Koneksi database gagal: ' . mysqli_connect_error());
    if (env('APP_ENV', 'production') !== 'production') {
        die("Koneksi database gagal: " . mysqli_connect_error());
    }
    die("Terjadi kesalahan pada server. Silakan coba lagi nanti.");
}

mysqli_set_charset($conn, 'utf8mb4');
