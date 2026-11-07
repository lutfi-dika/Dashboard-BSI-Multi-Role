<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/otp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit();
}

csrf_require_or_fail_json();

$identitas = trim($_POST['identitas'] ?? '');
if ($identitas === '') {
    echo json_encode(['success' => false, 'message' => 'Username atau email wajib diisi.']);
    exit();
}

$stmt = $conn->prepare("SELECT id, email FROM users WHERE username = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $identitas, $identitas);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Akun tidak ditemukan.']);
    exit();
}
if (empty($user['email'])) {
    echo json_encode(['success' => false, 'message' => 'Akun ini belum memiliki email terdaftar. Hubungi administrator.']);
    exit();
}

$result = otp_generate_and_send($conn, (int) $user['id'], 'lupa_password', 'email');

if ($result['success']) {
    $_SESSION['otp_user_id'] = (int) $user['id'];
    $_SESSION['otp_purpose'] = 'lupa_password';
    $_SESSION['otp_channel'] = 'email';
}

echo json_encode($result);
