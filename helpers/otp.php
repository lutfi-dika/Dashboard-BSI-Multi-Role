<?php
/**
 * helpers/otp.php
 * Modul inti sistem OTP — dipakai untuk Login, Registrasi, Verifikasi Email, Lupa Password.
 *
 * Aturan:
 * - OTP 6 digit acak, berlaku 5 menit, sekali pakai.
 * - Disimpan sebagai HASH (bukan plaintext) di database.
 * - Maksimal 5 kali percobaan input yang salah sebelum diblok (harus resend).
 * - Resend OTP dibatasi cooldown 60 detik.
 * - Channel: email (utama, PHPMailer) atau whatsapp (opsional, Fonnte).
 */

require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/fonnte.php';

const OTP_EXPIRY_MINUTES  = 5;
const OTP_RESEND_COOLDOWN = 60; // detik
const OTP_MAX_ATTEMPTS    = 5;

/**
 * Generate OTP baru untuk user + kirim lewat channel yang diminta.
 * OTP lama otomatis tergantikan (overwrite kolom yang sama).
 *
 * @param string $channel 'email' | 'whatsapp'
 * @return array{success: bool, message: string, cooldown?: int}
 */
function otp_generate_and_send(mysqli $conn, int $userId, string $purpose, string $channel = 'email'): array
{
    $stmt = $conn->prepare("SELECT id, nama_lengkap, username, email, no_hp FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        return ['success' => false, 'message' => 'Akun tidak ditemukan.'];
    }

    // Cek cooldown resend berdasarkan waktu OTP dibuat sebelumnya
    $cooldownCheck = otp_cooldown_remaining($conn, $userId);
    if ($cooldownCheck > 0) {
        return ['success' => false, 'message' => "Tunggu $cooldownCheck detik sebelum meminta OTP baru.", 'cooldown' => $cooldownCheck];
    }

    $nama = $user['nama_lengkap'] ?: $user['username'];

    if ($channel === 'email' && empty($user['email'])) {
        return ['success' => false, 'message' => 'Akun ini belum memiliki alamat email terdaftar.'];
    }
    if ($channel === 'whatsapp' && empty($user['no_hp'])) {
        return ['success' => false, 'message' => 'Akun ini belum memiliki nomor WhatsApp terdaftar.'];
    }

    $otp        = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpHash    = password_hash($otp, PASSWORD_DEFAULT);
    $expiredAt  = date("Y-m-d H:i:s", strtotime("+" . OTP_EXPIRY_MINUTES . " minutes"));
    $createdAt  = date("Y-m-d H:i:s");

    $simpanOtp = function (string $ch) use ($conn, $otpHash, $expiredAt, $createdAt, $purpose, $userId): bool {
        $upd = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ?, otp_created_at = ?, otp_purpose = ?, otp_channel = ?, otp_attempts = 0 WHERE id = ?");
        $upd->bind_param("sssssi", $otpHash, $expiredAt, $createdAt, $purpose, $ch, $userId);
        return $upd->execute();
    };

    if (!$simpanOtp($channel)) {
        error_log('OTP DB Error: ' . $conn->error);
        return ['success' => false, 'message' => 'Gagal menyimpan OTP ke database.'];
    }

    if ($channel === 'whatsapp') {
        $result = kirim_otp_whatsapp($user['no_hp'], $nama, $otp);
    } else {
        $result = kirim_otp_email($user['email'], $nama, $otp, $purpose);

        // Fallback otomatis: bila email gagal, coba kirim via WhatsApp bila nomor tersedia.
        if (!$result['success'] && !empty($user['no_hp'])) {
            if ($simpanOtp('whatsapp')) {
                $wa = kirim_otp_whatsapp($user['no_hp'], $nama, $otp);
                if ($wa['success']) {
                    return [
                        'success'      => true,
                        'message'      => 'Pengiriman OTP via email gagal (' . $result['message'] . ') — OTP berhasil dikirim via WhatsApp.',
                        'channel_used' => 'whatsapp',
                        'cooldown'     => OTP_RESEND_COOLDOWN,
                    ];
                }
                // WhatsApp juga gagal — batalkan OTP agar user bisa coba lagi
                $rollback = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL, otp_created_at = NULL, otp_purpose = NULL, otp_channel = NULL, otp_attempts = 0 WHERE id = ?");
                $rollback->bind_param("i", $userId);
                $rollback->execute();
                return $wa;
            }
        }
    }

    if (!$result['success']) {
        // Kirim gagal — batalkan OTP agar user bisa coba lagi tanpa menunggu cooldown
        $rollback = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL, otp_created_at = NULL, otp_purpose = NULL, otp_channel = NULL, otp_attempts = 0 WHERE id = ?");
        $rollback->bind_param("i", $userId);
        $rollback->execute();

        return $result;
    }

    return ['success' => true, 'message' => $result['message'], 'channel_used' => $channel, 'cooldown' => OTP_RESEND_COOLDOWN];
}

/**
 * Sisa cooldown (detik) sebelum resend OTP diperbolehkan lagi. 0 = boleh resend sekarang.
 */
function otp_cooldown_remaining(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare("SELECT otp_created_at FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || empty($row['otp_created_at'])) {
        return 0;
    }

    $elapsed = time() - strtotime($row['otp_created_at']);
    $remaining = OTP_RESEND_COOLDOWN - $elapsed;

    return $remaining > 0 ? $remaining : 0;
}

/**
 * Verifikasi kode OTP yang diinput user.
 *
 * @return array{success: bool, message: string, attempts_left?: int}
 */
function otp_verify(mysqli $conn, int $userId, string $inputOtp, string $purpose): array
{
    $stmt = $conn->prepare("SELECT otp_code, otp_expiry, otp_purpose, otp_attempts FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || empty($row['otp_code'])) {
        return ['success' => false, 'message' => 'Tidak ada OTP aktif. Silakan minta kode baru.'];
    }

    if ((int) $row['otp_attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'message' => 'Batas percobaan tercapai. Silakan minta kode OTP baru.'];
    }

    if ($row['otp_purpose'] !== $purpose) {
        return ['success' => false, 'message' => 'Sesi OTP tidak valid untuk aksi ini.'];
    }

    if (strtotime($row['otp_expiry']) < time()) {
        return ['success' => false, 'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.'];
    }

    if (!password_verify($inputOtp, $row['otp_code'])) {
        $attemptsLeft = OTP_MAX_ATTEMPTS - ((int) $row['otp_attempts'] + 1);
        $upd = $conn->prepare("UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = ?");
        $upd->bind_param("i", $userId);
        $upd->execute();

        if ($attemptsLeft <= 0) {
            return ['success' => false, 'message' => 'Kode OTP salah. Batas percobaan tercapai, silakan minta kode baru.', 'attempts_left' => 0];
        }
        return ['success' => false, 'message' => "Kode OTP salah. Sisa percobaan: $attemptsLeft.", 'attempts_left' => $attemptsLeft];
    }

    // Sukses — OTP hanya bisa dipakai sekali: hapus & reset attempt
    $clear = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL, otp_purpose = NULL, otp_channel = NULL, otp_attempts = 0 WHERE id = ?");
    $clear->bind_param("i", $userId);
    $clear->execute();

    // Untuk registrasi & verifikasi email, akun otomatis jadi Verified
    if (in_array($purpose, ['registrasi', 'verifikasi_email'], true)) {
        $verify = $conn->prepare("UPDATE users SET status = 'verified' WHERE id = ?");
        $verify->bind_param("i", $userId);
        $verify->execute();
    }

    return ['success' => true, 'message' => 'Verifikasi OTP berhasil.'];
}
