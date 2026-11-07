<?php
/**
 * helpers/csrf.php
 * Utility CSRF token — dipakai di semua form POST & endpoint AJAX.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validasi token dari POST body maupun header X-CSRF-Token (untuk request AJAX/fetch JSON).
 */
function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hentikan request dengan JSON error jika CSRF tidak valid. Dipakai di endpoint AJAX.
 */
function csrf_require_or_fail_json(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sesi tidak valid, silakan muat ulang halaman.']);
        exit();
    }
}
