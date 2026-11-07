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
$current_page = 'kolektibilitas';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'kolektibilitas'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

$kolLabels = [1 => 'Lancar', 2 => 'Dalam Perhatian Khusus', 3 => 'Kurang Lancar', 4 => 'Diragukan', 5 => 'Macet'];
$rekap = [];
foreach ($kolLabels as $k => $label) {
    $rekap[$k] = ['label' => $label, 'jumlah' => 0, 'outstanding' => 0.0];
}
$totalOutstanding = 0.0;
$npfOutstanding = 0.0;

if ($skemaLengkap) {
    $q = mysqli_query($conn, "SELECT kolektibilitas, COUNT(*) AS jml, SUM(outstanding) AS total_os FROM nasabah_mikro WHERE status_pengajuan = 'Aktif' GROUP BY kolektibilitas");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $k = (int) $r['kolektibilitas'];
            if (!isset($rekap[$k])) continue;
            $rekap[$k]['jumlah'] = (int) $r['jml'];
            $rekap[$k]['outstanding'] = (float) $r['total_os'];
            $totalOutstanding += (float) $r['total_os'];
            if ($k >= 3) $npfOutstanding += (float) $r['total_os'];
        }
    }
}
$npfRatio = $totalOutstanding > 0 ? ($npfOutstanding / $totalOutstanding) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Kolektibilitas Portofolio | BSI Mikro', 'mikro'); ?>
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
            'title'    => 'Kolektibilitas Portofolio',
            'subtitle' => 'Sebaran kualitas pembiayaan aktif berdasarkan kolektibilitas (Kol 1-5).',
            'stats'    => [
                ['label' => 'Outstanding Aktif', 'value' => 'Rp ' . number_format($totalOutstanding, 0, ',', '.')],
                ['label' => 'Rasio NPF', 'value' => number_format($npfRatio, 2) . '%'],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/002_mikro_dashboard_kpi.sql</code> agar data kolektibilitas tersedia.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <?php premium_kpi_card(['tone' => 'emerald', 'delay' => 1, 'label' => 'Total Outstanding Aktif', 'value' => 'Rp ' . number_format($totalOutstanding, 0, ',', '.')]); ?>
                <?php premium_kpi_card(['tone' => 'rose', 'delay' => 2, 'label' => 'Outstanding NPF (Kol 3-5)', 'value' => 'Rp ' . number_format($npfOutstanding, 0, ',', '.'), 'value_class' => '!text-rose-600']); ?>
                <?php premium_kpi_card(['tone' => 'amber', 'delay' => 3, 'label' => 'Rasio NPF', 'value' => number_format($npfRatio, 2) . '%', 'value_class' => $npfRatio > 5 ? '!text-rose-600' : '!text-emerald-600']); ?>
            </div>

            <div class="pm-card overflow-hidden pm-fade pm-d4">
                <div class="px-4 sm:px-6 pt-6 pb-4">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Rincian per Kolektibilitas</h4>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[640px]">
                        <thead>
                            <tr>
                                <th>Kolektibilitas</th>
                                <th>Jumlah Debitur</th>
                                <th>Outstanding</th>
                                <th>% Porsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap as $k => $r):
                                $pct = $totalOutstanding > 0 ? ($r['outstanding'] / $totalOutstanding) * 100 : 0;
                                $color = $k === 1 ? 'text-emerald-600' : ($k === 2 ? 'text-amber-600' : 'text-rose-600');
                            ?>
                                <tr>
                                    <td class="font-semibold <?= $color; ?>">Kol-<?= $k; ?> · <?= $r['label']; ?></td>
                                    <td><?= $r['jumlah']; ?> debitur</td>
                                    <td class="font-medium">Rp <?= number_format($r['outstanding'], 0, ',', '.'); ?></td>
                                    <td>
                                        <div class="pm-progress max-w-[160px]" style="height:6px;">
                                            <div class="pm-progress-fill" style="width: <?= min($pct, 100); ?>%;background:<?= $k === 1 ? '#10b981' : ($k === 2 ? '#f59e0b' : '#f43f5e'); ?>"></div>
                                        </div>
                                        <span class="text-[11px] text-slate-400"><?= number_format($pct, 1); ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
