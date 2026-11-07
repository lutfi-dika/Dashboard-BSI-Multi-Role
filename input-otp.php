<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/csrf.php';

if (!isset($_SESSION['otp_user_id'], $_SESSION['otp_purpose'])) {
    header("Location: auth/login.php");
    exit();
}

$otpChannel = $_SESSION['otp_channel'] ?? 'email';
$otpChannelLabel = $otpChannel === 'whatsapp' ? 'WhatsApp' : 'email';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen px-4">

    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2"></div>

    <div class="bg-white shadow-lg rounded-2xl w-full max-w-md p-8">

        <div class="flex justify-center mb-5">
            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3-1.343 3-3S13.657 5 12 5 9 6.343 9 8s1.343 3 3 3zm0 2c-2.761 0-5 2.239-5 5h10c0-2.761-2.239-5-5-5z" />
                </svg>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-center text-gray-800">Verifikasi OTP</h2>
        <p class="text-center text-gray-500 text-sm mt-2">Masukkan 6 digit kode OTP yang telah dikirim ke <?= htmlspecialchars($otpChannelLabel) ?> Anda.</p>

        <form id="otpForm" class="mt-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <label class="text-sm font-medium text-gray-700">Kode OTP</label>
            <input
                type="text"
                name="otp"
                id="otpInput"
                inputmode="numeric"
                maxlength="6"
                required
                autocomplete="one-time-code"
                placeholder="000000"
                class="mt-2 w-full border rounded-xl px-4 py-3 text-center text-2xl tracking-[8px] font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">

            <button
                type="submit"
                id="btnSubmit"
                class="mt-6 w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-semibold transition disabled:opacity-50">
                Verifikasi
            </button>
        </form>

        <div class="mt-5 text-center">
            <button id="btnResend" disabled class="text-sm font-medium text-blue-600 hover:underline disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed">
                Kirim Ulang OTP <span id="countdown">(60s)</span>
            </button>
        </div>

        <div class="mt-6 text-center">
            <a href="auth/login.php" class="text-blue-600 hover:underline text-sm">Kembali ke Login</a>
        </div>

    </div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const color = type === 'success' ? 'bg-emerald-600' : 'bg-red-600';
            toast.className = `${color} text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        // --- Countdown resend 60 detik ---
        let cooldown = 60;
        const btnResend = document.getElementById('btnResend');
        const countdownEl = document.getElementById('countdown');

        function startCountdown(seconds) {
            cooldown = seconds;
            btnResend.disabled = true;
            const timer = setInterval(() => {
                cooldown--;
                countdownEl.textContent = `(${cooldown}s)`;
                if (cooldown <= 0) {
                    clearInterval(timer);
                    btnResend.disabled = false;
                    countdownEl.textContent = '';
                }
            }, 1000);
        }
        startCountdown(60);

        // --- Submit verifikasi OTP ---
        document.getElementById('otpForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = 'Memverifikasi...';

            try {
                const res = await fetch('ajax/verifikasi-otp.php', {
                    method: 'POST',
                    body: new FormData(this),
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.href = data.redirect || 'index.php', 700);
                } else {
                    showToast(data.message, 'error');
                    document.getElementById('otpInput').value = '';
                    btn.disabled = false;
                    btn.innerHTML = 'Verifikasi';
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan. Coba lagi.', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Verifikasi';
            }
        });

        // --- Resend OTP ---
        btnResend.addEventListener('click', async function () {
            btnResend.disabled = true;
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('input[name=csrf_token]').value);
            formData.append('resend', '1');
            formData.append('channel', <?= json_encode($otpChannel) ?>);

            try {
                const res = await fetch('ajax/kirim-otp.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    startCountdown(data.cooldown || 60);
                } else {
                    showToast(data.message, 'error');
                    startCountdown(data.cooldown || 10);
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan. Coba lagi.', 'error');
                btnResend.disabled = false;
            }
        });
    </script>
    <?php include __DIR__ . '/components/clock.php'; ?>
</body>

</html>
