<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../auth/login.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');

if (!in_array($role_user, ['admin', 'pawning', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

// Total ujrah dari pelunasan/penebusan + perpanjangan
$totalPelunasan = (float) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(ujrah),0) t FROM pawning_pelunasan"))['t'] ?? 0);
$totalPerpanj   = (float) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(ujrah_bayar),0) t FROM pawning_perpanjangan"))['t'] ?? 0);
$totalUjrah     = $totalPelunasan + $totalPerpanj;

// Tren 6 bulan terakhir
$trenLabel = [];
$trenData  = [];
for ($i = 5; $i >= 0; $i--) {
    $bln = date('m', strtotime("-$i months"));
    $thn = date('Y', strtotime("-$i months"));
    $p = (float) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(ujrah),0) t FROM pawning_pelunasan WHERE MONTH(created_at) = '$bln' AND YEAR(created_at) = '$thn'"))['t'] ?? 0);
    $pp = (float) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(ujrah_bayar),0) t FROM pawning_perpanjangan WHERE MONTH(created_at) = '$bln' AND YEAR(created_at) = '$thn'"))['t'] ?? 0);
    $trenLabel[] = date('M', strtotime("-$i months"));
    $trenData[]  = round($p + $pp);
}

// Transaksi terakhir (gabungan)
$transaksi = mysqli_query($conn, "
    (SELECT 'Pelunasan' AS jenis, l.ujrah AS nominal, l.created_at, p.nama_pemilik FROM pawning_pelunasan l JOIN pawning p ON p.id = l.pawning_id)
    UNION ALL
    (SELECT 'Perpanjangan', pp.ujrah_bayar, pp.created_at, p.nama_pemilik FROM pawning_perpanjangan pp JOIN pawning p ON p.id = pp.pawning_id)
    ORDER BY created_at DESC LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Pendapatan Ujrah | BSI Pawning', 'pawning'); ?>
</head>
<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/pawning-sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
      <div class="max-w-[1600px] mx-auto">

        <div class="flex items-center justify-between gap-3 mb-5">
            <button onclick="toggleSidebar()" class="md:hidden shrink-0 p-2 rounded-xl bg-white border border-slate-200 text-slate-500 shadow-xs" aria-label="Buka menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="3" y1="12" x2="21" y2="12" />
                    <line x1="3" y1="18" x2="21" y2="18" />
                </svg>
            </button>

            <div class="flex items-center gap-2 sm:gap-3 relative shrink-0 ml-auto">
                <div class="pm-card px-2.5 sm:px-4 py-2 rounded-xl text-[10px] sm:text-xs font-bold items-center gap-2 text-slate-600 hidden sm:flex">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Koneksi Database Aktif
                </div>

                <div onclick="toggleProfilePopup(event)" class="flex items-center gap-2.5 pl-3 border-l border-slate-200 cursor-pointer hover:opacity-85 transition select-none">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=d97706&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-amber-500/20">
                    <span class="text-sm font-bold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-slate-400 hidden sm:block">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>

                <div id="profilePopup" class="hidden absolute right-0 top-12 w-64 sm:w-72 max-w-[90vw] bg-white border border-slate-100 rounded-2xl shadow-xl z-50 p-5">
                    <div class="text-center border-b pb-3 mb-3">
                        <h4 class="font-bold text-sm"><?= htmlspecialchars($nama_user); ?></h4>
                        <span class="text-[10px] uppercase font-bold text-amber-600"><?= htmlspecialchars($role_user); ?></span>
                    </div>

                    <?php if ($role_user === 'admin' || $role_user === 'atasan'): ?>
                        <div class="space-y-1 mb-4">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Beralih Dashboard</p>
                            <a href="../Mikro/mikro-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-emerald-50 rounded-lg">Dashboard Mikro</a>
                            <a href="../konsumer/konsumer-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-sky-50 rounded-lg">Dashboard Konsumer</a>
                            <?php if ($role_user === 'atasan'): ?><a href="../Atasan/admin-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-violet-50 rounded-lg">Pusat Dashboard Atasan</a><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-slate-400 italic text-center py-2">Anda tidak memiliki akses untuk berpindah ke dashboard segmen lain.</div>
                    <?php endif; ?>

                    <button data-logout class="block w-full text-center text-xs text-red-600 bg-red-50 py-2 rounded-lg font-semibold cursor-pointer hover:bg-red-100 transition">Keluar</button>
                </div>
            </div>
        </div>

        <?php premium_hero([
            'accent'   => 'pawning',
            'eyebrow'  => 'BSI Pawning',
            'title'    => 'Pendapatan Ujrah',
            'subtitle' => 'Monitor pendapatan ujrah dari pelunasan dan perpanjangan gadai.',
        ]); ?>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <?php premium_kpi_card(['tone' => 'amber', 'delay' => 1, 'label' => 'Total Ujrah', 'value' => 'Rp ' . number_format($totalUjrah, 0, ',', '.'), 'value_class' => '!text-amber-600']); ?>
            <?php premium_kpi_card(['tone' => 'sky', 'delay' => 2, 'label' => 'Dari Pelunasan', 'value' => 'Rp ' . number_format($totalPelunasan, 0, ',', '.')]); ?>
            <?php premium_kpi_card(['tone' => 'emerald', 'delay' => 3, 'label' => 'Dari Perpanjangan', 'value' => 'Rp ' . number_format($totalPerpanj, 0, ',', '.'), 'value_class' => '!text-emerald-600']); ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="pm-card p-5 lg:col-span-2 pm-fade pm-d4">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Tren Ujrah 6 Bulan Terakhir</h4>
                <div class="relative h-56">
                    <canvas id="ujrahChart"></canvas>
                </div>
            </div>

            <div class="pm-card p-5 lg:col-span-1 pm-fade pm-d5">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Transaksi Ujrah Terakhir</h4>
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    <?php if ($transaksi && mysqli_num_rows($transaksi) > 0): ?>
                        <?php while ($t = mysqli_fetch_assoc($transaksi)): ?>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/70 border border-slate-100">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-700 truncate"><?= htmlspecialchars($t['nama_pemilik']); ?></p>
                                    <p class="text-[10px] text-slate-400"><?= htmlspecialchars($t['jenis']); ?> • <?= date('d M Y', strtotime($t['created_at'])); ?></p>
                                </div>
                                <span class="text-xs font-bold text-amber-600 shrink-0">Rp <?= number_format($t['nominal'], 0, ',', '.'); ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 italic py-8 text-center">Belum ada transaksi ujrah.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        new Chart(document.getElementById('ujrahChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($trenLabel); ?>,
                datasets: [{
                    label: 'Ujrah',
                    data: <?= json_encode($trenData); ?>,
                    backgroundColor: 'rgba(217, 119, 6, 0.25)',
                    borderColor: '#d97706',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8', font: { size: 10 } },
                        grid: { color: 'rgba(15, 23, 42, 0.05)' }
                    },
                    x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>
