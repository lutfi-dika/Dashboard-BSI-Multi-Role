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

// KPI
$kpi = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS jumlah, COALESCE(SUM(nilai_pinjaman),0) AS outstanding, COALESCE(SUM(taksiran_harga),0) AS taksiran
    FROM pawning WHERE status = 'Aktif'
"));

$jumlahAktif   = (int) ($kpi['jumlah'] ?? 0);
$outstanding   = (float) ($kpi['outstanding'] ?? 0);
$totalTaksiran = (float) ($kpi['taksiran'] ?? 0);
$ltv = $totalTaksiran > 0 ? ($outstanding / $totalTaksiran) * 100 : 0;

// Per kelompok barang
$perBarang = [];
$q = mysqli_query($conn, "
    SELECT barang_jaminan, COUNT(*) AS jumlah, COALESCE(SUM(nilai_pinjaman),0) AS outstanding, COALESCE(SUM(taksiran_harga),0) AS taksiran
    FROM pawning WHERE status = 'Aktif'
    GROUP BY barang_jaminan ORDER BY outstanding DESC
");
while ($r = mysqli_fetch_assoc($q)) {
    $perBarang[] = $r;
}

// Proporsi status
$statusQ = mysqli_query($conn, "SELECT status, COUNT(*) jumlah FROM pawning GROUP BY status");
$statusData = ['Aktif' => 0, 'Lunas' => 0, 'Lelang' => 0];
while ($s = mysqli_fetch_assoc($statusQ)) {
    $statusData[$s['status']] = (int) $s['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Outstanding Gadai | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Outstanding Gadai',
            'subtitle' => 'Posisi outstanding (pinjaman aktif) portofolio gadai emas.',
            'stats'    => [
                ['label' => 'Nasabah Aktif',     'value' => number_format($jumlahAktif)],
                ['label' => 'Total Outstanding', 'value' => 'Rp ' . number_format($outstanding, 0, ',', '.')],
                ['label' => 'Rasio LTV',         'value' => number_format($ltv, 1) . '%'],
            ],
        ]); ?>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="pm-card p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Nasabah Aktif</p>
                <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($jumlahAktif); ?></p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Outstanding</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1">Rp <?= number_format($outstanding, 0, ',', '.'); ?></p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Rasio LTV (Pinjaman/Taksiran)</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1"><?= number_format($ltv, 1); ?>%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="pm-card p-5 lg:col-span-2 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Outstanding per Jenis Barang</h4>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[600px]">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Unit</th>
                                <th>Taksiran</th>
                                <th>Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($perBarang) > 0): ?>
                                <?php foreach ($perBarang as $b): ?>
                                    <tr>
                                        <td class="font-semibold text-slate-800"><?= htmlspecialchars($b['barang_jaminan']); ?></td>
                                        <td class="text-slate-500"><?= (int) $b['jumlah']; ?></td>
                                        <td>Rp <?= number_format($b['taksiran'], 0, ',', '.'); ?></td>
                                        <td class="font-bold text-amber-600 whitespace-nowrap">Rp <?= number_format($b['outstanding'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="py-8 text-center text-xs text-slate-400 italic">Tidak ada portofolio aktif.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pm-card p-5 pm-fade pm-d3">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Proporsi Status</h4>
                <div class="relative h-48 flex items-center justify-center">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="space-y-2 mt-4 text-xs">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2 text-slate-500"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Aktif</span><b class="text-slate-800"><?= $statusData['Aktif']; ?></b></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2 text-slate-500"><span class="w-2 h-2 rounded-full bg-sky-400"></span>Lunas</span><b class="text-slate-800"><?= $statusData['Lunas']; ?></b></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2 text-slate-500"><span class="w-2 h-2 rounded-full bg-rose-400"></span>Lelang</span><b class="text-slate-800"><?= $statusData['Lelang']; ?></b></div>
                </div>
            </div>
        </div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Aktif', 'Lunas', 'Lelang'],
                datasets: [{
                    data: [<?= $statusData['Aktif']; ?>, <?= $statusData['Lunas']; ?>, <?= $statusData['Lelang']; ?>],
                    backgroundColor: ['#10b981', '#38bdf8', '#fb7185'],
                    borderWidth: 0,
                    cutout: '68%'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    </script>
</body>
</html>
