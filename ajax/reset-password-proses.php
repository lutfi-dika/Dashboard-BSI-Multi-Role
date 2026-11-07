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

if (!isset($_SESSION['reset_password_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi reset password tidak valid. Ulangi proses lupa password.']);
    exit();
}

$password = (string) ($_POST['password'] ?? '');
$confirm  = (string) ($_POST['password_confirm'] ?? '');

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password minimal 8 karakter.']);
    exit();
}
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password harus mengandung huruf besar, huruf kecil, angka, dan simbol.']);
    exit();
}
if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Konfirmasi password tidak cocok.']);
    exit();
}

$userId = (int) $_SESSION['reset_password_user_id'];
$hash   = password_hash($password, PASSWORD_DEFAULT);

$upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$upd->bind_param("si", $hash, $userId);

if (!$upd->execute()) {
    error_log('Reset Password DB Error: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan password baru.']);
    exit();
}

unset($_SESSION['reset_password_user_id']);

echo json_encode(['success' => true, 'message' => 'Password berhasil diperbarui. Silakan login.']);
