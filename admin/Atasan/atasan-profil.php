<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../auth/login.php"); exit(); }
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) { header("Location: ../../pilih-karyawan.php"); exit(); }

$role_user = strtolower($_SESSION['role'] ?? '');
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Atasan');
$current_page = 'atasan-profil.php';
if (!in_array($role_user, ['admin', 'atasan'], true)) { header('Location: ../../index.php'); exit(); }

$userId = (int) $_SESSION['user_id'];

// Cek apakah kolom 'status' ada di tabel users
$hasStatus = false;
$cekStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
if ($cekStatus && mysqli_num_rows($cekStatus) > 0) {
    $hasStatus = true;
}

$selectCols = "nama_lengkap, username, email, role";
if ($hasStatus) $selectCols .= ", status";

$stmt = mysqli_prepare($conn, "SELECT $selectCols FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Profil | BSI Executive', 'atasan'); ?>
</head>
<body class="text-slate-800 min-h-screen flex flex-col md:flex-row">
    <?php include __DIR__ . '/../../components/sidebar.php'; ?>
    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
        <!-- TOPBAR: hamburger mobile + status koneksi + profil -->
        <div class="flex items-center justify-between gap-3 mb-5">
            <button onclick="toggleSidebar()" class="md:hidden shrink-0 p-2 rounded-xl bg-white border border-slate-200 text-slate-500 shadow-xs" aria-label="Buka menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="18" x2="21" y2="18" />
                </svg>
            </button>

            <div class="flex items-center gap-2 sm:gap-3 relative shrink-0 ml-auto">
                <div class="pm-card px-2.5 sm:px-4 py-2 rounded-xl text-[10px] sm:text-xs font-bold items-center gap-2 text-slate-600 hidden sm:flex">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Koneksi Database Aktif
                </div>

                <div onclick="toggleProfilePopup(event)" class="flex items-center gap-2.5 pl-3 border-l border-slate-200 cursor-pointer hover:opacity-85 transition select-none">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=7c3aed&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-violet-500/20">
                    <span class="text-sm font-bold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-slate-400 hidden sm:block">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>

                <div id="profilePopup" class="hidden absolute right-0 top-12 w-64 sm:w-72 max-w-[90vw] bg-white border border-slate-100 rounded-2xl shadow-xl z-50 p-5">
                    <div class="text-center border-b pb-3 mb-3">
                        <h4 class="font-bold text-sm"><?= htmlspecialchars($nama_user); ?></h4>
                        <span class="text-[10px] uppercase font-bold text-violet-600"><?= htmlspecialchars($role_user); ?></span>
                    </div>

                    <div class="space-y-1 mb-4">
                        <p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Beralih Dashboard</p>
                        <a href="../Mikro/mikro-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-emerald-50 rounded-lg">Dashboard Mikro</a>
                        <a href="../konsumer/konsumer-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-sky-50 rounded-lg">Dashboard Konsumer</a>
                        <a href="../pawning/pawning-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-amber-50 rounded-lg">Dashboard Gadai</a>
                        <a href="admin-dashboard.php" class="block p-2 text-xs font-semibold bg-violet-50 text-violet-700 rounded-lg">Pusat Dashboard Atasan</a>
                    </div>

                    <a href="../../auth/logout.php" class="block w-full text-center text-xs text-red-600 bg-red-50 py-2 rounded-lg font-semibold">Keluar</a>
                </div>
            </div>
        </div>

        <?php premium_hero([
            'accent'   => 'atasan',
            'eyebrow'  => 'BSI Executive',
            'title'    => 'Profil',
            'subtitle' => 'Informasi akun dan hak akses Anda.',
        ]); ?>

        <div class="pm-card p-6 sm:p-8 max-w-2xl pm-fade pm-d1">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-slate-100">
                <div class="grid h-16 w-16 place-items-center rounded-2xl bg-violet-500/10 text-2xl font-extrabold text-violet-600"><?= strtoupper(substr($nama_user, 0, 1)); ?></div>
                <div>
                    <h2 class="text-lg font-extrabold text-slate-800"><?= htmlspecialchars(($user['nama_lengkap'] ?? null) ?: $nama_user); ?></h2>
                    <span class="pm-badge mt-1 uppercase bg-violet-500/10 text-violet-600 border border-violet-500/20">Role: <?= htmlspecialchars(($user['role'] ?? null) ?: $role_user); ?></span>
                </div>
            </div>

            <?php if ($user): ?>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-[11px] font-bold uppercase text-slate-500">Username</dt><dd class="mt-1 text-sm font-semibold text-slate-800"><?= htmlspecialchars($user['username']); ?></dd></div>
                <div><dt class="text-[11px] font-bold uppercase text-slate-500">Email</dt><dd class="mt-1 text-sm font-semibold text-slate-800"><?= htmlspecialchars($user['email'] ?? '-'); ?></dd></div>
                <div><dt class="text-[11px] font-bold uppercase text-slate-500">Status Akun</dt><dd class="mt-1 text-sm font-semibold text-slate-800"><?= htmlspecialchars(ucfirst($user['status'] ?? '-')); ?></dd></div>
                <div><dt class="text-[11px] font-bold uppercase text-slate-500">Cakupan Akses</dt><dd class="mt-1 text-sm font-semibold text-slate-800">Seluruh Segmen (Read-only)</dd></div>
            </dl>
            <?php else: ?>
                <p class="text-sm text-slate-500">Data profil tidak ditemukan.</p>
            <?php endif; ?>

            <div class="mt-8 pt-6 border-t border-slate-100 flex flex-wrap gap-3">
                <a href="admin-dashboard.php" class="pm-btn pm-btn-ghost">Kembali ke Dashboard</a>
                <button data-logout class="pm-btn bg-rose-50 hover:bg-rose-600 hover:text-white border border-rose-200 text-rose-600 cursor-pointer">Keluar Sistem</button>
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

        function toggleProfilePopup(event) {
            event.stopPropagation();
            const popup = document.getElementById('profilePopup');
            popup.classList.toggle('hidden');
        }

        document.addEventListener('click', function(event) {
            const popup = document.getElementById('profilePopup');
            if (popup && !popup.classList.contains('hidden')) {
                popup.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
