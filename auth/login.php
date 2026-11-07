<?php
session_start();
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/dashboard-redirect.php';

// Jika sudah login penuh (password + OTP terverifikasi), langsung ke dashboard divisinya
redirect_to_own_dashboard('../');

// Jika baru login password tapi belum verifikasi OTP, arahkan ke pintu konfirmasi
if (isset($_SESSION['user_id'])) {
    header("Location: ../pilih-karyawan.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Internal Data Center</title>
    <link rel="icon" href="../assets/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-700 min-h-screen flex flex-col items-center justify-center p-4 antialiased selection:bg-indigo-100">

    <div class="w-full max-w-sm">

        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-center p-3">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Logo_Bank_Syariah_Indonesia.svg/512px-Logo_Bank_Syariah_Indonesia.svg.png"
                    alt="Logo BSI"
                    class="w-full h-full object-contain rounded-xl cursor-pointer hover:opacity-80 transition"
                    onclick="document.getElementById('logo-modal').classList.remove('hidden')"
                    onerror="this.onerror=null; this.replaceWith(Object.assign(document.createElement('div'), {className:'w-full h-full flex items-center justify-center text-emerald-700 font-extrabold text-2xl tracking-tight', textContent:'BSI'}));">
            </div>
        </div>

        <div id="logo-modal"
            class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
            onclick="if(event.target === this) this.classList.add('hidden')">
            <div class="relative max-w-lg w-full">
                <button type="button"
                    onclick="document.getElementById('logo-modal').classList.add('hidden')"
                    class="absolute -top-10 right-0 text-white/80 hover:text-white cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Logo_Bank_Syariah_Indonesia.svg/512px-Logo_Bank_Syariah_Indonesia.svg.png"
                    alt="Logo Perusahaan (perbesar)"
                    class="w-full h-auto rounded-xl shadow-2xl">
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-2xl px-8 py-9 border border-slate-200">

            <h2 class="text-xl font-semibold text-slate-900 text-center mb-1 tracking-tight">Sistem Pendataan</h2>
            <p class="text-sm text-slate-500 text-center mb-7">Masuk menggunakan akun internal Anda</p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-5 p-3.5 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm font-medium flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span><?= htmlspecialchars($_SESSION['error']);
                            unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <form action="proses-login.php" method="POST" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Username</label>
                    <input type="text"
                        name="username"
                        required
                        autocomplete="off"
                        class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all"
                        placeholder="Masukkan username">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <input type="password"
                        name="password"
                        required
                        class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition-all"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-slate-600 select-none cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/30 accent-indigo-600">
                        Ingat saya
                    </label>
                    <a href="lupa-password.php" class="text-sm text-indigo-600 hover:underline">Lupa password?</a>
                </div>

                <div class="pt-1">
                    <button type="submit"
                        class="w-full bg-black hover:bg-gray-900 text-white font-semibold py-2.5 px-4 rounded-lg transition-all duration-200 shadow-xs cursor-pointer text-sm text-center active:scale-[0.99]">
                        Masuk
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-xs text-slate-400 mt-4">Sistem hanya memungkinkan login karyawan.</p>
        <p class="text-center text-xs text-slate-400 mt-8">&copy; <?= date('Y'); ?> Internal Data Center. All rights reserved.</p>
    </div>

    <?php include __DIR__ . '/../components/clock.php'; ?>
</body>

</html>