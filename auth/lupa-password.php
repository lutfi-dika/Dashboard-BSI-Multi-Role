<?php
session_start();
require_once __DIR__ . '/../helpers/csrf.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-slate-50 text-slate-700 min-h-screen flex flex-col items-center justify-center p-4">

    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2"></div>

    <div class="w-full max-w-sm">
        <div class="bg-white shadow-sm rounded-2xl px-8 py-9 border border-slate-200">
            <h2 class="text-xl font-semibold text-slate-900 text-center mb-1">Lupa Password</h2>
            <p class="text-sm text-slate-500 text-center mb-7">Masukkan username atau email akun Anda</p>

            <form id="formLupa" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Username / Email</label>
                    <input type="text" name="identitas" required class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500">
                </div>
                <button type="submit" id="btnSubmit" class="w-full bg-black hover:bg-gray-900 text-white font-semibold py-2.5 px-4 rounded-lg transition-all text-sm disabled:opacity-50">
                    Kirim Kode OTP
                </button>
            </form>

            <p class="text-center text-xs text-slate-400 mt-6"><a href="login.php" class="text-indigo-600 hover:underline">Kembali ke Login</a></p>
        </div>
    </div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `${type === 'success' ? 'bg-emerald-600' : 'bg-red-600'} text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        document.getElementById('formLupa').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.textContent = 'Memproses...';

            try {
                const res = await fetch('../ajax/lupa-password-proses.php', { method: 'POST', body: new FormData(this) });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => window.location.href = '../input-otp.php', 900);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Kirim Kode OTP';
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan.', 'error');
                btn.disabled = false;
                btn.textContent = 'Kirim Kode OTP';
            }
        });
    </script>
</body>

</html>
