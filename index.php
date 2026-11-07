<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi ke database
// Sesuaikan path ini dengan lokasi file koneksi Anda yang sebenarnya
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/dashboard-redirect.php';
require_once __DIR__ . '/helpers/delete-activity.php';

// 1. Cek apakah sudah login (password)
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// 2. Cek apakah sudah lulus verifikasi OTP
// Jika tidak ada session 'login_terverifikasi', paksa ke halaman konfirmasi
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: pilih-karyawan.php");
    exit();
}

// 3. Sudah login penuh -> tampilkan overview dashboard (index.php)
    // Role-specific dashboard bisa diakses melalui sidebar navigasi.
$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');

// CSRF Token (dipakai untuk aksi hapus data)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle delete activity using centralized helper
handle_delete_activity($conn, $role_user, 'index.php');

// ==========================================
// 1. QUERY AMBIL DATA DINAMIS DARI DATABASE
// ==========================================
$query_mikro = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(omzet_bulanan) as total_omzet FROM nasabah_mikro");
$data_mikro  = mysqli_fetch_assoc($query_mikro);
$total_mikro = $data_mikro['total'] ?? 0;
$omzet_mikro = $data_mikro['total_omzet'] ?? 0;

$query_konsumer = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(pendapatan_bulanan) as total_pendapatan FROM nasabah_konsumer");
$data_konsumer      = mysqli_fetch_assoc($query_konsumer);
$total_konsumer     = $data_konsumer['total'] ?? 0;
$pendapatan_konsumer = $data_konsumer['total_pendapatan'] ?? 0;

$total_semua_nasabah = $total_mikro + $total_konsumer;

$persen_mikro    = $total_semua_nasabah > 0 ? round(($total_mikro / $total_semua_nasabah) * 100) : 0;
$persen_konsumer = $total_semua_nasabah > 0 ? (100 - $persen_mikro) : 0;

// Catatan: kolom `id` disertakan agar setiap baris aktivitas bisa dihapus,
// dan label segmen disamakan jadi 'Mikro' agar cocok dengan pengecekan di bawah.
$query_recent = "
    (SELECT id, nama_pemilik AS nama, nama_usaha AS info, 'Mikro' AS segmen, created_at FROM nasabah_mikro)
    UNION ALL
    (SELECT id, nama_lengkap AS nama, produk_diajukan AS info, 'Konsumer' AS segmen, created_at FROM nasabah_konsumer)
    ORDER BY created_at DESC LIMIT 6
";
$result_recent = mysqli_query($conn, $query_recent);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Overview - Data Center</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .card {
            background: #ffffff;
            border: 1px solid #eef0f3;
        }

        /* Cegah body scroll di belakang saat drawer mobile terbuka */
        body.sidebar-open {
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-white text-slate-700 min-h-screen flex flex-col md:flex-row">

    <!-- Overlay gelap untuk mobile, muncul saat sidebar dibuka -->
    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

    <!-- Sidebar: drawer di mobile (fixed, geser dari kiri), statis di desktop -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-[90%] max-w-full md:w-64 md:max-w-none bg-white border-r border-slate-100 flex flex-col justify-between py-6 px-5 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
        <div>
            <div class="flex items-center justify-between px-2 mb-8">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">DC</div>
                    <span class="font-bold text-slate-800">Data Center</span>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:bg-slate-100" aria-label="Tutup menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <nav class="space-y-1">
                <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-indigo-50 text-indigo-600 font-semibold text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9" rx="1" />
                        <rect x="14" y="3" width="7" height="5" rx="1" />
                        <rect x="14" y="12" width="7" height="9" rx="1" />
                        <rect x="3" y="16" width="7" height="5" rx="1" />
                    </svg>
                    Overview Dashboard
                </a>

                <a href="views/Kinerja.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="M18.7 8 12 14.7l-3.3-3.4L3 17" />
                    </svg>
                    Kinerja
                </a>

                <a href="views/pipeline.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="6" r="3" />
                        <circle cx="6" cy="18" r="3" />
                        <path d="M6 9v6" />
                        <path d="M6 6h9a3 3 0 0 1 3 3v0a3 3 0 0 1-3 3h-3" />
                        <circle cx="18" cy="18" r="3" />
                    </svg>
                    Pipeline
                </a>

                <?php if (in_array($role_user, ['admin', 'mikro', 'atasan'], true)): ?>
                    <a href="admin/Mikro/mikro-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                        Sektor Mikro
                    </a>
                <?php endif; ?>

                <?php if (in_array($role_user, ['admin', 'konsumer', 'atasan'], true)): ?>
                    <a href="admin/konsumer/konsumer-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                        Sektor Konsumer
                    </a>
                <?php endif; ?>

                <?php if (in_array($role_user, ['admin', 'pawning', 'atasan'], true)): ?>
                    <a href="admin/pawning/pawning-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                        Sektor Gadai
                    </a>
                <?php endif; ?>

                <?php if ($role_user === 'atasan'): ?>
                    <a href="admin/Atasan/admin-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-violet-50 text-violet-700 font-semibold text-sm transition">
                        Pusat Dashboard Atasan
                    </a>
                <?php endif; ?>
            </nav>
        </div>
        <a href="auth/logout.php" class="px-3 py-2 text-red-500 font-medium text-sm hover:bg-red-50 rounded-xl">Keluar Sistem</a>
    </aside>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen relative w-full min-w-0">

        <header class="flex items-start sm:items-center justify-between gap-3 mb-6 md:mb-8">
            <div class="flex items-center gap-3 min-w-0">
                <!-- Tombol hamburger, hanya tampil di mobile -->
                <button onclick="toggleSidebar()" class="md:hidden shrink-0 p-2 rounded-xl bg-white border border-slate-100 text-slate-500 shadow-xs" aria-label="Buka menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <line x1="3" y1="12" x2="21" y2="12" />
                        <line x1="3" y1="18" x2="21" y2="18" />
                    </svg>
                </button>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 truncate">Overview Analisis</h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5 hidden sm:block">Ringkasan data performa segmen Mikro & Konsumer terintegrasi.</p>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 relative shrink-0">
                <div class="card px-2.5 sm:px-4 py-2 rounded-xl text-[10px] sm:text-xs font-semibold items-center gap-2 text-slate-600 shadow-xs hidden sm:flex">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Koneksi Database Aktif
                </div>

                <div onclick="toggleProfilePopup(event)" class="flex items-center gap-2 sm:gap-2.5 sm:pl-3 sm:border-l border-slate-200 cursor-pointer hover:opacity-85 transition select-none">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=6366f1&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-indigo-500/10">
                    <span class="text-sm font-semibold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-slate-400 hidden sm:block">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>

                <div id="profilePopup" class="hidden absolute right-0 top-12 w-64 sm:w-72 max-w-[90vw] bg-white border rounded-2xl shadow-xl z-50 p-5">
                    <div class="text-center border-b pb-3 mb-3">
                        <h4 class="font-bold text-sm"><?= htmlspecialchars($nama_user); ?></h4>
                        <span class="text-[10px] uppercase font-bold text-indigo-600"><?= htmlspecialchars($role_user); ?></span>
                    </div>

                    <?php if ($role_user === 'admin' || $role_user === 'atasan'): ?>
                        <div class="space-y-1 mb-4">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Beralih Dashboard</p>
                            <?php if ($role_user === 'atasan'): ?><a href="admin/Atasan/admin-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-violet-50 rounded-lg">Pusat Dashboard Atasan</a><?php endif; ?>
                            <a href="admin/Mikro/mikro-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-indigo-50 rounded-lg">Dashboard Mikro</a>
                            <a href="admin/konsumer/konsumer-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-sky-50 rounded-lg">Dashboard Konsumer</a>
                            <a href="admin/pawning/pawning-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-amber-50 rounded-lg">Dashboard Gadai</a>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-slate-400 italic text-center py-2">
                            Anda tidak memiliki akses untuk berpindah ke dashboard segmen lain.
                        </div>
                    <?php endif; ?>

                    <a href="auth/logout.php" class="block w-full text-center text-xs text-red-600 bg-red-50 py-2 rounded-lg font-semibold">Keluar</a>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 sm:gap-5 mb-5">
            <div class="card p-5 sm:p-6 rounded-2xl flex items-center justify-between shadow-xs hover:shadow-md transition-all duration-300">
                <div class="min-w-0">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Segmen Mikro</p>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1"><?= number_format($total_mikro); ?></h3>
                    <p class="text-xs text-indigo-500 font-medium mt-1">Total Unit Bisnis</p>
                </div>
                <div class="p-3.5 bg-indigo-50 text-indigo-600 rounded-xl shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m2 7 10-5 10 5v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" />
                        <path d="M12 22V12" />
                    </svg>
                </div>
            </div>

            <div class="card p-5 sm:p-6 rounded-2xl flex items-center justify-between shadow-xs hover:shadow-md transition-all duration-300">
                <div class="min-w-0">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Segmen Konsumer</p>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1"><?= number_format($total_konsumer); ?></h3>
                    <p class="text-xs text-sky-500 font-medium mt-1">Total Individu</p>
                </div>
                <div class="p-3.5 bg-sky-50 text-sky-600 rounded-xl shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                </div>
            </div>

            <div class="card p-5 sm:p-6 rounded-2xl flex items-center justify-between shadow-xs hover:shadow-md transition-all duration-300">
                <div class="min-w-0">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Gabungan</p>
                    <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1"><?= number_format($total_semua_nasabah); ?></h3>
                    <p class="text-xs text-emerald-500 font-medium mt-1">Database Records</p>
                </div>
                <div class="p-3.5 bg-emerald-50 text-emerald-600 rounded-xl shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
            </div>

            <div class="card p-5 sm:p-6 rounded-2xl flex items-center justify-between shadow-xs hover:shadow-md transition-all duration-300">
                <div class="min-w-0">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Omzet Mikro Terdata</p>
                    <h3 class="text-lg sm:text-xl font-bold text-slate-900 mt-2 truncate w-full max-w-[10rem]">Rp <?= number_format($omzet_mikro, 0, ',', '.'); ?></h3>
                    <p class="text-xs text-amber-500 font-medium mt-1">Finansial Sektor</p>
                </div>
                <div class="p-3.5 bg-amber-50 text-amber-600 rounded-xl shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23" />
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </div>
            </div>

            <div class="card p-5 sm:p-6 rounded-2xl flex items-center justify-between shadow-xs hover:shadow-md transition-all duration-300">
                <div class="min-w-0">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Pendapatan Konsumer Terdata</p>
                    <h3 class="text-lg sm:text-xl font-bold text-slate-900 mt-2 truncate w-full max-w-[10rem]">Rp <?= number_format($pendapatan_konsumer, 0, ',', '.'); ?></h3>
                    <p class="text-xs text-sky-500 font-medium mt-1">Finansial Sektor</p>
                </div>
                <div class="p-3.5 bg-sky-50 text-sky-600 rounded-xl shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23" />
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
            <div class="card p-5 sm:p-6 rounded-2xl sm:col-span-2 xl:col-span-3 min-h-[300px] sm:min-h-[350px] flex flex-col justify-between shadow-xs">
                <div class="flex justify-between items-center mb-4 gap-2">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Tren Pertumbuhan Pendataan</h4>
                    <span class="text-[10px] bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-md text-slate-500 font-medium whitespace-nowrap">Live Metric</span>
                </div>
                <div class="relative flex-1 h-56 sm:h-72">
                    <canvas id="trenChart"></canvas>
                </div>
            </div>

            <div class="card p-5 sm:p-6 rounded-2xl sm:col-span-2 xl:col-span-1 min-h-[300px] sm:min-h-[350px] flex flex-col shadow-xs">
                <h4 class="text-sm font-bold tracking-wide text-slate-800 mb-4">Proporsi Segmen</h4>
                <div class="relative flex-1 flex items-center justify-center">
                    <canvas id="proporsiChart" class="max-h-[160px] sm:max-h-[180px]"></canvas>
                </div>
                <div class="space-y-2.5 mt-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-indigo-500"></span>Mikro</span>
                        <span class="font-bold text-slate-800"><?= $persen_mikro; ?>%</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-sky-400"></span>Konsumer</span>
                        <span class="font-bold text-slate-800"><?= $persen_konsumer; ?>%</span>
                    </div>
                </div>
            </div>

            <div class="card p-5 sm:p-6 rounded-2xl xl:col-span-1 flex flex-col justify-start gap-3 shadow-xs bg-white">
                <h4 class="text-sm font-bold tracking-wide text-slate-800 mb-1">Aksi Cepat Panel</h4>

                <?php if ($role_user === 'admin' || $role_user === 'mikro'): ?>
                    <a href="admin/Mikro/mikro-tambah.php" class="w-full flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold py-3 px-4 rounded-xl transition-all shadow-sm shadow-indigo-600/20 cursor-pointer">
                        + Tambah Data Mikro
                    </a>
                <?php endif; ?>

                <?php if ($role_user === 'admin' || $role_user === 'konsumer'): ?>
                    <a href="admin/konsumer/konsumer-tambah.php" class="w-full flex justify-center items-center gap-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold py-3 px-4 rounded-xl border border-slate-200 transition-all cursor-pointer">
                        + Tambah Data Konsumer
                    </a>
                <?php endif; ?>

                <?php if ($role_user !== 'admin' && $role_user !== 'mikro' && $role_user !== 'konsumer'): ?>
                    <div class="text-xs text-slate-400 italic bg-slate-50 p-3 rounded-xl border border-dashed border-slate-200 text-center">
                        Akun Anda memiliki hak akses View-Only (Membaca).
                    </div>
                <?php endif; ?>
            </div>

            <div class="card rounded-2xl xl:col-span-3 shadow-xs overflow-hidden">
                <div class="flex justify-between items-center px-4 sm:px-6 pt-6 pb-4">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Aktivitas Terakhir (Gabungan Sektor)</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] text-slate-400 uppercase tracking-wider border-y border-slate-100">
                                <th class="px-4 sm:px-6 py-3 font-semibold">Nama Mitra/Nasabah</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Entitas / Produk</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Segmen</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold">Tanggal Masuk</th>
                                <th class="px-4 sm:px-6 py-3 font-semibold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if ($result_recent && mysqli_num_rows($result_recent) > 0): ?>
                                <?php while ($log = mysqli_fetch_assoc($result_recent)): ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-4 sm:px-6 py-3.5 font-semibold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($log['nama']); ?></td>
                                        <td class="px-4 sm:px-6 py-3.5 text-slate-500 truncate max-w-[200px]"><?= htmlspecialchars($log['info']); ?></td>
                                        <td class="px-4 sm:px-6 py-3.5">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $log['segmen'] == 'Mikro' ? 'bg-indigo-50 text-indigo-600 border border-indigo-100' : 'bg-sky-50 text-sky-600 border border-sky-100'; ?>">
                                                <?= $log['segmen']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 sm:px-6 py-3.5 text-slate-400 text-xs whitespace-nowrap"><?= date('d M Y', strtotime($log['created_at'])); ?></td>
                                        <td class="px-4 sm:px-6 py-3.5 text-right">
                                            <?php
                                            $bolehHapus = ($log['segmen'] === 'Mikro' && ($role_user === 'admin' || $role_user === 'mikro'))
                                                || ($log['segmen'] === 'Konsumer' && ($role_user === 'admin' || $role_user === 'konsumer'));
                                            ?>
                                            <?php if ($bolehHapus): ?>
                                                <form method="POST" action="index.php" class="inline" onsubmit="return confirm('Hapus data &quot;<?= htmlspecialchars(addslashes($log['nama'])); ?>&quot; secara permanen?');">
                                                    <input type="hidden" name="hapus_aktivitas" value="1">
                                                    <input type="hidden" name="id" value="<?= (int)$log['id']; ?>">
                                                    <input type="hidden" name="segmen" value="<?= htmlspecialchars($log['segmen']); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                    <button type="submit" title="Hapus data" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6" />
                                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                            <path d="M10 11v6" />
                                                            <path d="M14 11v6" />
                                                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-xs text-slate-400 italic">Belum ada aktivitas pendataan terbaru di database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const isOpen = !sidebar.classList.contains('-translate-x-full');

            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('sidebar-open');
            } else {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                document.body.classList.add('sidebar-open');
            }
        }

        // Tutup drawer otomatis saat layar melebar ke ukuran desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                document.getElementById('sidebar').classList.remove('-translate-x-full');
                document.getElementById('sidebarOverlay').classList.add('hidden');
                document.body.classList.remove('sidebar-open');
            } else {
                document.getElementById('sidebar').classList.add('-translate-x-full');
            }
        });

        function toggleProfilePopup(event) {
            event.stopPropagation(); // Mencegah penutupan langsung saat mengeklik diri sendiri
            const popup = document.getElementById('profilePopup');
            popup.classList.toggle('hidden');
        }

        // Menutup popup secara otomatis jika pengguna mengeklik di luar area kartu popup
        document.addEventListener('click', function(event) {
            const popup = document.getElementById('profilePopup');
            if (popup && !popup.classList.contains('hidden')) {
                popup.classList.add('hidden');
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('trenChart').getContext('2d');
        const indGrad = ctx.createLinearGradient(0, 0, 0, 300);
        indGrad.addColorStop(0, 'rgba(99, 102, 241, 0.18)');
        indGrad.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

        const skyGrad = ctx.createLinearGradient(0, 0, 0, 300);
        skyGrad.addColorStop(0, 'rgba(56, 189, 248, 0.18)');
        skyGrad.addColorStop(1, 'rgba(56, 189, 248, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                datasets: [{
                        label: 'Sektor Mikro',
                        data: [<?= ($total_mikro ?? 0) * 0.3; ?>, <?= ($total_mikro ?? 0) * 0.5; ?>, <?= ($total_mikro ?? 0) * 0.7; ?>, <?= ($total_mikro ?? 0) * 0.8; ?>, <?= ($total_mikro ?? 0) * 0.9; ?>, <?= ($total_mikro ?? 0); ?>],
                        borderColor: '#6366f1',
                        backgroundColor: skyGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#38bdf8'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#64748b',
                            font: {
                                family: 'Plus Jakarta Sans',
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                family: 'Plus Jakarta Sans'
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(15, 23, 42, 0.04)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                family: 'Plus Jakarta Sans'
                            }
                        }
                    }
                }
            }
        });

        const ctxDonut = document.getElementById('proporsiChart').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Mikro', 'Konsumer'],
                datasets: [{
                    data: [<?= $persen_mikro; ?>, <?= $persen_konsumer; ?>],
                    backgroundColor: ['#6366f1', '#7dd3fc'],
                    borderWidth: 0,
                    cutout: '72%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.label}: ${ctx.raw}%`
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>
