<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

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
$current_page = 'notifikasi';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$notif = [];

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'status_pengajuan'");
if ($cekKolom && mysqli_num_rows($cekKolom) > 0) {
    // Pengajuan baru menunggu approval
    $q = mysqli_query($conn, "SELECT nama_pemilik, nama_usaha, created_at FROM nasabah_mikro WHERE status_pengajuan='Approval' ORDER BY created_at DESC LIMIT 10");
    while ($r = mysqli_fetch_assoc($q)) {
        $notif[] = ['type' => 'info', 'title' => 'Pengajuan baru: ' . $r['nama_pemilik'], 'desc' => 'Usaha ' . $r['nama_usaha'] . ' menunggu approval.', 'time' => $r['created_at']];
    }

    // Kolektibilitas memburuk (Kol 3-5)
    $q = mysqli_query($conn, "SELECT nama_pemilik, kolektibilitas, outstanding FROM nasabah_mikro WHERE status_pengajuan='Aktif' AND kolektibilitas >= 3 ORDER BY kolektibilitas DESC LIMIT 10");
    while ($r = mysqli_fetch_assoc($q)) {
        $notif[] = ['type' => 'danger', 'title' => 'Kolektibilitas Kol-' . $r['kolektibilitas'] . ': ' . $r['nama_pemilik'], 'desc' => 'Outstanding Rp ' . number_format($r['outstanding'], 0, ',', '.') . ' perlu penanganan segera.', 'time' => null];
    }

    // Jatuh tempo dekat
    $cekTgl = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'tanggal_jatuh_tempo'");
    if ($cekTgl && mysqli_num_rows($cekTgl) > 0) {
        $q = mysqli_query($conn, "SELECT nama_pemilik, tanggal_jatuh_tempo, DATEDIFF(tanggal_jatuh_tempo, CURDATE()) AS sisa FROM nasabah_mikro WHERE status_pengajuan='Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND DATEDIFF(tanggal_jatuh_tempo, CURDATE()) BETWEEN 0 AND 30 ORDER BY tanggal_jatuh_tempo ASC LIMIT 10");
        while ($r = mysqli_fetch_assoc($q)) {
            $notif[] = ['type' => 'warning', 'title' => 'Jatuh tempo H-' . $r['sisa'] . ': ' . $r['nama_pemilik'], 'desc' => 'Jatuh tempo pada ' . date('d M Y', strtotime($r['tanggal_jatuh_tempo'])) . '.', 'time' => null];
        }
    }
}

$typeStyle = [
    'info' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600'],
    'warning' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600'],
    'danger' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600'],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Notifikasi | BSI Mikro', 'mikro'); ?>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
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
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=059669&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-emerald-500/20">
                    <span class="text-sm font-bold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-slate-400 hidden sm:block">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
                <div id="profilePopup" class="hidden absolute right-0 top-12 w-64 sm:w-72 max-w-[90vw] bg-white border border-slate-100 rounded-2xl shadow-xl z-50 p-5">
                    <div class="text-center border-b pb-3 mb-3">
                        <h4 class="font-bold text-sm"><?= htmlspecialchars($nama_user); ?></h4>
                        <span class="text-[10px] uppercase font-bold text-emerald-600"><?= htmlspecialchars($role_user); ?></span>
                    </div>
                    <?php if ($role_user === 'admin' || $role_user === 'atasan'): ?>
                        <div class="space-y-1 mb-4">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Beralih Dashboard</p>
                            <a href="../konsumer/konsumer-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-sky-50 rounded-lg">Dashboard Konsumer</a>
                            <a href="../pawning/pawning-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-amber-50 rounded-lg">Dashboard Gadai</a>
                            <?php if ($role_user === 'atasan'): ?><a href="../Atasan/admin-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-violet-50 rounded-lg">Pusat Dashboard Atasan</a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <button data-logout class="block w-full text-center text-xs text-red-600 bg-red-50 py-2 rounded-lg font-semibold cursor-pointer hover:bg-red-100 transition">Keluar</button>
                </div>
            </div>
        </div>

        <?php premium_hero([
            'accent'   => 'mikro',
            'eyebrow'  => 'BSI Mikro',
            'title'    => 'Notifikasi',
            'subtitle' => 'Peringatan otomatis dari kondisi data terkini (approval, kolektibilitas, jatuh tempo).',
            'stats'    => [['label' => 'Notifikasi', 'value' => count($notif)]],
        ]); ?>

        <?php if (count($notif) === 0): ?>
            <div class="pm-card rounded-2xl p-10 text-center text-xs text-slate-400 italic">Tidak ada notifikasi saat ini. Semua kondisi portofolio aman.</div>
        <?php else: ?>
            <div class="pm-card rounded-2xl shadow-xs overflow-hidden divide-y divide-slate-50">
                <?php foreach ($notif as $n): $s = $typeStyle[$n['type']]; ?>
                    <div class="p-4 sm:p-5 flex items-start gap-3.5 hover:bg-slate-50/60 transition">
                        <div class="w-9 h-9 rounded-xl <?= $s['bg']; ?> <?= $s['text']; ?> flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($n['title']); ?></h4>
                            <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($n['desc']); ?></p>
                            <?php if ($n['time']): ?>
                                <p class="text-[10px] text-slate-400 mt-1"><?= htmlspecialchars(date('d M Y H:i', strtotime($n['time']))); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
