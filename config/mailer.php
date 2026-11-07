<?php
/**
 * config/mailer.php
 * Wrapper pengiriman email OTP menggunakan PHPMailer via SMTP.
 * Kredensial SMTP diambil dari .env — lihat .env.example.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Kirim email OTP.
 *
 * @return array{success: bool, message: string}
 */
function kirim_otp_email(string $toEmail, string $toName, string $otp, string $tujuan = 'login'): array
{
    $labelTujuan = [
        'login'            => 'Verifikasi Login',
        'registrasi'       => 'Verifikasi Registrasi Akun',
        'verifikasi_email' => 'Verifikasi Email',
        'lupa_password'    => 'Reset Password',
    ][$tujuan] ?? 'Verifikasi';

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', '');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USERNAME', '');
        $mail->Password   = env('SMTP_PASSWORD', '');
        $mail->SMTPSecure = env('SMTP_SECURE', 'tls');
        $mail->Port       = (int) env('SMTP_PORT', 587);
        $mail->CharSet    = 'UTF-8';

        if (!$mail->Host || !$mail->Username || !$mail->Password) {
            $missing = [];
            if (!$mail->Host)     $missing[] = 'SMTP_HOST';
            if (!$mail->Username) $missing[] = 'SMTP_USERNAME';
            if (!$mail->Password) $missing[] = 'SMTP_PASSWORD';
            return ['success' => false, 'message' => 'Konfigurasi SMTP belum lengkap: ' . implode(', ', $missing) . ' belum diisi di file .env.'];
        }
        if (strpos($mail->Password, 'your-gmail-app-password-here') !== false) {
            return ['success' => false, 'message' => 'Email OTP belum bisa dikirim: SMTP_PASSWORD di .env masih placeholder. Ganti dengan App Password Gmail asli (aktifkan 2-Step Verification di akun pengirim), atau pilih channel WhatsApp.'];
        }

        $mail->setFrom(env('SMTP_FROM_EMAIL', $mail->Username), env('SMTP_FROM_NAME', 'BSI Data Center'));
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = "Kode OTP - $labelTujuan";
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px;'>
                <h2 style='color:#2563EB;margin-bottom:4px;'>$labelTujuan</h2>
                <p>Halo <b>" . htmlspecialchars($toName) . "</b>,</p>
                <p>Kode OTP Anda adalah:</p>
                <div style='font-size:32px;font-weight:bold;letter-spacing:8px;background:#f3f4f6;padding:16px;text-align:center;border-radius:8px;margin:16px 0;'>$otp</div>
                <p>Kode ini berlaku selama <b>5 menit</b> dan hanya bisa digunakan satu kali.</p>
                <p style='color:#DC2626;'>Jangan pernah membagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari BSI.</p>
                <hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0;'>
                <p style='font-size:12px;color:#9CA3AF;'>Email ini dikirim otomatis, mohon tidak membalas.</p>
            </div>
        ";
        $mail->AltBody = "Kode OTP Anda: $otp (berlaku 5 menit, satu kali pakai). Jangan bagikan kode ini kepada siapa pun.";

        $mail->send();
        return ['success' => true, 'message' => 'OTP berhasil dikirim ke email.'];
    } catch (PHPMailerException $e) {
        $info = $mail->ErrorInfo;
        error_log('SMTP Error: ' . $info);

        if (stripos($info, 'authenticate') !== false || stripos($info, '535') !== false) {
            return ['success' => false, 'message' => 'Email OTP gagal: kredensial SMTP ditolak server Gmail. Cek SMTP_USERNAME & SMTP_PASSWORD (pastikan App Password, bukan password biasa) di file .env.'];
        }
        if (stripos($info, 'connect') !== false || stripos($info, 'timed out') !== false || stripos($info, 'refused') !== false || stripos($info, 'failed to connect') !== false) {
            return ['success' => false, 'message' => 'Email OTP gagal: tidak dapat terhubung ke server SMTP. Periksa koneksi jaringan, SMTP_HOST, dan porte SMTP di .env.'];
        }
        if (stripos($info, 'from address') !== false || stripos($info, 'Unknown') !== false) {
            return ['success' => false, 'message' => 'Email OTP gagal: alamat pengirim (SMTP_FROM_EMAIL) ditolak server. Pastikan sama dengan akun SMTP_USERNAME.'];
        }
        return ['success' => false, 'message' => 'Gagal mengirim email OTP: ' . $info . '. Gunakan channel WhatsApp atau coba kirim ulang.'];
    }
}
