<?php
session_start();
require_once __DIR__ . '/../helpers/csrf.php';
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
    <title>Akses Terbatas - Internal Data Center</title>
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
            <h2 class="text-xl font-semibold text-slate-900 text-center mb-1">Akses Hanya untuk Karyawan</h2>
            <p class="text-sm text-slate-500 text-center mb-7">Registrasi dinonaktifkan. Silakan login menggunakan akun karyawan.</p>

            <p class="text-center text-xs text-slate-400 mt-6">Sudah punya akun? <a href="login.php" class="text-indigo-600 hover:underline">Masuk</a></p>
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
    </script>

</body>

</html>
