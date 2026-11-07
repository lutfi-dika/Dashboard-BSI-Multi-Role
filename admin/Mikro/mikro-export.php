<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');
$current_page = 'export';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$totalRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml FROM nasabah_mikro")) ?: [];
$totalData = (int) ($totalRow['jml'] ?? 0);
$lastUpdateRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(created_at) AS terakhir FROM nasabah_mikro"));
$lastUpdate = $lastUpdateRow['terakhir'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - Dashboard Mikro</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { background: #ffffff; border: 1px solid #eef0f3; }
        body.sidebar-open { overflow: hidden; }
    </style>
</head>

<body class="bg-white text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
        <header class="flex items-center gap-3 mb-6 md:mb-8">
            <button onclick="toggleSidebar()" class="md:hidden shrink-0 p-2 rounded-xl bg-white border border-slate-100 text-slate-500 shadow-xs" aria-label="Buka menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="18" x2="21" y2="18" />
                </svg>
            </button>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">Export Data Mikro</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Unduh seluruh data nasabah mikro dalam format Excel.</p>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="card rounded-2xl p-5">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Data Tersedia</p>
                <p class="text-2xl font-bold text-slate-900"><?= $totalData; ?> record</p>
            </div>
            <div class="card rounded-2xl p-5">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Input Terbaru</p>
                <p class="text-2xl font-bold text-emerald-600"><?= $lastUpdate ? htmlspecialchars(date('d M Y H:i', strtotime($lastUpdate))) : '—'; ?></p>
            </div>
        </div>

        <div class="card rounded-2xl shadow-xs p-6 sm:p-8">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="12" y1="18" x2="12" y2="12" /><line x1="9" y1="15" x2="15" y2="15" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-slate-800 mb-1">Export Seluruh Data (Excel)</h4>
                    <p class="text-xs text-slate-500 mb-4">Berisi seluruh kolom: data pemilik, usaha, plafon, outstanding, status pengajuan, kolektibilitas, dan petugas penginput.</p>
                    <a href="mikro-export-excel.php" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold py-2.5 px-5 rounded-xl transition-all shadow-sm shadow-emerald-600/20">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        Download Excel (.xls)
                    </a>
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
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                document.getElementById('sidebar').classList.remove('-translate-x-full');
                document.getElementById('sidebarOverlay').classList.add('hidden');
                document.body.classList.remove('sidebar-open');
            } else {
                document.getElementById('sidebar').classList.add('-translate-x-full');
            }
        });
    </script>
</body>

</html>
