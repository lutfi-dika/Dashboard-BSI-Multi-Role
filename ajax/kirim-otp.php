<?php
/**
 * ajax/kirim-otp.php
 * Endpoint AJAX: mengirim OTP baru (dipanggil dari pilih-karyawan.php) atau resend (dari input-otp.php).
 * Selalu balas JSON.
 */

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

$isResend = isset($_POST['resend']) && $_POST['resend'] === '1';
$purpose  = $_POST['purpose'] ?? 'login';

if ($isResend) {
    // Resend: user_id, purpose, dan channel diambil dari sesi OTP yang sedang berjalan
    if (!isset($_SESSION['otp_user_id']) || !isset($_SESSION['otp_purpose'])) {
        echo json_encode(['success' => false, 'message' => 'Sesi OTP tidak ditemukan. Silakan mulai ulang.']);
        exit();
    }
    $userId  = (int) $_SESSION['otp_user_id'];
    $purpose = $_SESSION['otp_purpose'];
    $postedChannel = $_POST['channel'] ?? '';
    $channel = in_array($postedChannel, ['email', 'whatsapp'], true)
        ? $postedChannel
        : ($_SESSION['otp_channel'] ?? 'email');
} else {
    $channel = in_array($_POST['channel'] ?? 'email', ['email', 'whatsapp'], true) ? $_POST['channel'] : 'email';
    if (!isset($_POST['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID pengguna tidak ditemukan.']);
        exit();
    }
    $userId = (int) $_POST['user_id'];
}

$result = otp_generate_and_send($conn, $userId, $purpose, $channel);

if ($result['success']) {
    $_SESSION['otp_user_id'] = $userId;
    $_SESSION['otp_purpose'] = $purpose;
    $_SESSION['otp_channel'] = $result['channel_used'] ?? $channel;
    // Hanya untuk alur login karyawan (menjaga kompatibilitas authcheck.php)
    if ($purpose === 'login') {
        $_SESSION['user_id'] = $userId;
    }
}

echo json_encode($result);
