<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit();
}

csrf_require_or_fail_json();

// Registrasi dinonaktifkan untuk sistem karyawan hanya login
echo json_encode(['success' => false, 'message' => 'Registrasi dinonaktifkan. Sistem hanya memungkinkan login karyawan.']);
exit();
