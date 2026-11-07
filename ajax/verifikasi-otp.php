<?php
/**
 * ajax/verifikasi-otp.php
 * Endpoint AJAX: verifikasi kode OTP yang diinput user. Selalu balas JSON.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/otp.php';
require_once __DIR__ . '/../helpers/dashboard-redirect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit();
}

csrf_require_or_fail_json();

if (!isset($_SESSION['otp_user_id'], $_SESSION['otp_purpose'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi OTP tidak ditemukan. Silakan mulai ulang.']);
    exit();
}

$userId  = (int) $_SESSION['otp_user_id'];
$purpose = $_SESSION['otp_purpose'];
$input   = trim($_POST['otp'] ?? '');

if (!preg_match('/^\d{6}$/', $input)) {
    echo json_encode(['success' => false, 'message' => 'Format OTP tidak valid. Masukkan 6 digit angka.']);
    exit();
}

$result = otp_verify($conn, $userId, $input, $purpose);

if ($result['success']) {
    // Bersihkan sesi OTP sementara
    unset($_SESSION['otp_purpose'], $_SESSION['otp_channel']);

    $redirect = 'index.php';

    if ($purpose === 'login') {
        $_SESSION['user_id']              = $userId;
        $_SESSION['login_terverifikasi']  = true;

        $stmt = $conn->prepare("SELECT role, username, nama_lengkap FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $_SESSION['role']     = $user['role'] ?? '';
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['nama']     = $user['nama_lengkap'] ?? '';

        $redirect = dashboard_path_for_role($user['role'] ?? '');
    } elseif ($purpose === 'registrasi' || $purpose === 'verifikasi_email') {
        $redirect = 'auth/login.php';
    } elseif ($purpose === 'lupa_password') {
        $_SESSION['reset_password_user_id'] = $userId;
        $redirect = 'auth/reset-password.php';
    }

    unset($_SESSION['otp_user_id']);
    $result['redirect'] = $redirect;
}

echo json_encode($result);
