<?php
/**
 * config/fonnte.php
 * Wrapper pengiriman OTP via WhatsApp (Fonnte) — channel opsional/sekunder.
 * Token diambil dari .env (FONNTE_TOKEN), tidak boleh hardcode di source.
 */

require_once __DIR__ . '/env.php';

/**
 * Normalisasi nomor HP Indonesia untuk Fonnte (target harus string).
 */
function fonnte_normalize_target(string $noHp): string
{
    $no = preg_replace('/[\s\-\(\)\+]/', '', trim($noHp));
    if ($no === '') {
        return '';
    }

    // 628xxx -> biarkan; Fonnte countryCode 62 tidak menambah prefix ganda
    if (preg_match('/^62\d{8,13}$/', $no)) {
        return $no;
    }

    // 08xxx / 8xxx -> Fonnte akan ganti 0 pertama dengan 62 via countryCode
    if (preg_match('/^0\d{8,12}$/', $no)) {
        return $no;
    }
    if (preg_match('/^8\d{8,11}$/', $no)) {
        return '0' . $no;
    }

    return $no;
}

/**
 * @param array<string, mixed>|null $data
 */
function fonnte_response_ok(?array $data): bool
{
    if (!$data) {
        return false;
    }

    $status = $data['status'] ?? $data['Status'] ?? null;

    return $status === true || $status === 'true' || $status === 1 || $status === '1';
}

/**
 * @return array{success: bool, message: string}
 */
function kirim_otp_whatsapp(string $noHp, string $nama, string $otp): array
{
    $token = trim((string) env('FONNTE_TOKEN', ''));
    if ($token === '') {
        return ['success' => false, 'message' => 'Channel WhatsApp belum dikonfigurasi. Isi FONNTE_TOKEN di file .env.'];
    }

    $target = fonnte_normalize_target($noHp);
    if ($target === '') {
        return ['success' => false, 'message' => 'Nomor WhatsApp tidak tersedia untuk akun ini.'];
    }

    if (!preg_match('/^(0\d{8,12}|62\d{8,13})$/', $target)) {
        return ['success' => false, 'message' => 'Format nomor WhatsApp tidak valid. Gunakan 08xx atau 628xx.'];
    }

    $pesan = "🔐 *VERIFIKASI OTP BSI DATA CENTER*\n\n"
        . "Halo {$nama},\n\n"
        . "Kode OTP Anda adalah:\n\n*{$otp}*\n\n"
        . "Kode ini berlaku selama 5 menit dan hanya bisa digunakan satu kali.\n"
        . "Jangan pernah membagikan kode ini kepada siapa pun.\n\nTerima kasih.";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => [
            'target'      => $target,
            'message'     => $pesan,
            'countryCode' => '62',
        ],
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . $token,
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error    = curl_error($curl);
    curl_close($curl);

    if ($error) {
        error_log('Fonnte cURL Error: ' . $error);
        return ['success' => false, 'message' => 'Gagal mengirim OTP via WhatsApp (koneksi).'];
    }

    $data = json_decode((string) $response, true);
    if (!fonnte_response_ok($data)) {
        $reason = is_array($data)
            ? ($data['reason'] ?? $data['detail'] ?? $data['message'] ?? null)
            : null;
        error_log('Fonnte API Error (HTTP ' . $httpCode . '): ' . $response);
        return [
            'success' => false,
            'message' => 'Gagal mengirim OTP via WhatsApp' . ($reason ? ': ' . $reason : '.'),
        ];
    }

    return ['success' => true, 'message' => 'OTP berhasil dikirim via WhatsApp.'];
}
