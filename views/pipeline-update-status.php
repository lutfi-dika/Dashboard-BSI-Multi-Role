<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Harus login & terverifikasi
if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Belum login']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$id     = isset($input['id']) ? (int)$input['id'] : 0;
$table  = $input['table'] ?? '';
$status = $input['status'] ?? '';
$csrfToken = $input['csrf_token'] ?? '';

// Verifikasi CSRF token
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token tidak valid']);
    exit();
}

// Whitelist ketat: hanya 2 tabel ini yang boleh diupdate
$tabel_diizinkan = ['nasabah_mikro', 'nasabah_konsumer'];
// Whitelist status yang valid
$status_diizinkan = ['Prospek', 'Diajukan', 'Verifikasi', 'Disetujui', 'Cair', 'Ditolak'];

if ($id <= 0 || !in_array($table, $tabel_diizinkan, true) || !in_array($status, $status_diizinkan, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Data tidak valid']);
    exit();
}

$stmt = mysqli_prepare($conn, "UPDATE `$table` SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $status, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => true]);
} else {
    error_log('Pipeline update error: ' . mysqli_error($conn));
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Gagal memperbarui status data.']);
}

mysqli_stmt_close($stmt);
