<?php
session_start();
require_once __DIR__ . '/../helpers/csrf.php';

if (!isset($_SESSION['reset_password_user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
            <h2 class="text-xl font-semibold text-slate-900 text-center mb-1">Buat Password Baru</h2>
            <p class="text-sm text-slate-500 text-center mb-7">Identitas Anda telah terverifikasi via OTP</p>

            <form id="formReset" class="space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Password Baru</label>
                    <input type="password" name="password" required minlength="8" class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500">
                    <p class="text-xs text-slate-500 mt-1">Minimal 8 karakter: huruf besar, kecil, angka, & simbol</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Konfirmasi Password</label>
                    <input type="password" name="password_confirm" required minlength="8" class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500">
                </div>
                <button type="submit" id="btnSubmit" class="w-full bg-black hover:bg-gray-900 text-white font-semibold py-2.5 px-4 rounded-lg transition-all text-sm disabled:opacity-50">
                    Simpan Password Baru
                </button>
            </form>
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

        document.getElementById('formReset').addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            const password = fd.get('password');
            if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/\d/.test(password) || !/[^A-Za-z0-9]/.test(password)) {
                showToast('Password harus mengandung huruf besar, kecil, angka, dan simbol.', 'error');
                return;
            }
            if (fd.get('password') !== fd.get('password_confirm')) {
                showToast('Konfirmasi password tidak cocok.', 'error');
                return;
            }
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            try {
                const res = await fetch('../ajax/reset-password-proses.php', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => window.location.href = 'login.php', 900);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Simpan Password Baru';
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan.', 'error');
                btn.disabled = false;
                btn.textContent = 'Simpan Password Baru';
            }
        });
    </script>
</body>

</html>
