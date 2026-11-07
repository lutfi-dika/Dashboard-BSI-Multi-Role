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

if (!in_array($role_user, ['admin', 'konsumer', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$tanggal = trim($_GET['tanggal'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

$stmt = mysqli_prepare($conn, "SELECT k.*, u.nama_lengkap AS nama_petugas
                                FROM nasabah_konsumer k
                                LEFT JOIN users u ON k.user_id = u.id
                                WHERE DATE(k.created_at) = ?
                                ORDER BY k.created_at ASC");
mysqli_stmt_bind_param($stmt, "s", $tanggal);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$daftar = [];
$ringkasan = ['pending' => 0, 'disetujui' => 0, 'ditolak' => 0];
$totalPendapatan = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $daftar[] = $row;
    $s = $row['status'] ?: 'pending';
    if (isset($ringkasan[$s])) $ringkasan[$s]++;
    $totalPendapatan += (float) $row['pendapatan_bulanan'];
}
$totalHariIni = count($daftar);

// Tren 14 hari terakhir untuk konteks
$tren_label = [];
$tren_data  = [];
for ($i = 13; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $stmtTren = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM nasabah_konsumer WHERE DATE(created_at) = ?");
    mysqli_stmt_bind_param($stmtTren, "s", $tgl);
    mysqli_stmt_execute($stmtTren);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtTren));
    mysqli_stmt_close($stmtTren);
    $tren_label[] = date('d/m', strtotime($tgl));
    $tren_data[]  = (int) ($r['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Laporan Harian | BSI Konsumer', 'konsumer'); ?>
    <style>
        @media print {

            #sidebar,
            .no-print {
                display: none !important;
            }

            main {
                max-height: none !important;
                overflow: visible !important;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .pm-card {
                background: white !important;
                border: 1px solid #ddd !important;
                color: black !important;
            }
        }
    </style>
</head>

<body class="text-slate-700 min-h-screen flex">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
        <div class="flex items-center justify-between gap-3 mb-5 no-print">
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
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=0284c7&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-sky-500/20">
                    <span class="text-sm font-bold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-slate-400 hidden sm:block">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>

                <div id="profilePopup" class="hidden absolute right-0 top-12 w-64 sm:w-72 max-w-[90vw] bg-white border border-slate-100 rounded-2xl shadow-xl z-50 p-5">
                    <div class="text-center border-b pb-3 mb-3">
                        <h4 class="font-bold text-sm"><?= htmlspecialchars($nama_user); ?></h4>
                        <span class="text-[10px] uppercase font-bold text-sky-600"><?= htmlspecialchars($role_user); ?></span>
                    </div>

                    <?php if ($role_user === 'admin' || $role_user === 'atasan'): ?>
                        <div class="space-y-1 mb-4">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Beralih Dashboard</p>
                            <a href="../Mikro/mikro-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-emerald-50 rounded-lg">Dashboard Mikro</a>
                            <a href="../pawning/pawning-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-amber-50 rounded-lg">Dashboard Gadai</a>
                            <?php if ($role_user === 'atasan'): ?><a href="../Atasan/admin-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-violet-50 rounded-lg">Pusat Dashboard Atasan</a><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-slate-400 italic text-center py-2">Anda tidak memiliki akses untuk berpindah ke dashboard segmen lain.</div>
                    <?php endif; ?>

                    <button data-logout class="block w-full text-center text-xs text-red-600 bg-red-50 py-2 rounded-lg font-semibold cursor-pointer hover:bg-red-100 transition">Keluar</button>
                </div>
            </div>
        </div>

        <div class="no-print">
        <?php premium_hero([
            'accent'   => 'konsumer',
            'eyebrow'  => 'BSI Konsumer',
            'title'    => 'Laporan Harian',
            'subtitle' => 'Rekap pengajuan konsumer per tanggal.',
            'stats'    => [
                ['label' => 'Total Pengajuan', 'value' => number_format($totalHariIni)],
                ['label' => 'Disetujui',       'value' => number_format($ringkasan['disetujui'])],
                ['label' => 'Pending',         'value' => number_format($ringkasan['pending'])],
            ],
        ]); ?>
        </div>

        <form method="GET" class="pm-card p-4 mb-6 flex flex-wrap items-center gap-3 no-print pm-fade pm-d1">
            <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal); ?>" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
            <button type="submit" class="pm-btn pm-btn-primary">Tampilkan</button>
            <a href="?tanggal=<?= date('Y-m-d'); ?>" class="text-xs text-slate-500 hover:text-slate-900">Hari Ini</a>
            <button type="button" onclick="window.print()" class="ml-auto pm-btn pm-btn-ghost">Cetak</button>
        </form>

        <h2 class="text-sm font-bold text-slate-600 mb-3">Rekap tanggal <?= htmlspecialchars(date('d-m-Y', strtotime($tanggal))); ?></h2>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-500">Total Pengajuan</p>
                <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1"><?= $totalHariIni; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-500">Pending</p>
                <p class="text-xl sm:text-2xl font-bold text-amber-500 mt-1"><?= $ringkasan['pending']; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-500">Disetujui</p>
                <p class="text-xl sm:text-2xl font-bold text-emerald-500 mt-1"><?= $ringkasan['disetujui']; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d4">
                <p class="text-[11px] text-slate-500">Ditolak</p>
                <p class="text-xl sm:text-2xl font-bold text-rose-500 mt-1"><?= $ringkasan['ditolak']; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 col-span-2 lg:col-span-1 pm-fade pm-d5">
                <p class="text-[11px] text-slate-500">Total Pendapatan Pemohon</p>
                <p class="text-base sm:text-lg font-bold text-slate-900 mt-1">Rp<?= number_format($totalPendapatan, 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="pm-card p-6 mb-6 no-print pm-fade pm-d2">
            <h4 class="text-sm font-bold text-slate-900 mb-1">Tren 14 Hari Terakhir</h4>
            <p class="text-xs text-slate-500 mb-4">Jumlah pengajuan masuk per hari.</p>
            <div class="h-48"><canvas id="trenHarianChart"></canvas></div>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d3">
            <div class="px-4 sm:px-6 pt-5 pb-3">
                <h4 class="text-sm font-bold text-slate-900">Daftar Pengajuan (<?= $totalHariIni; ?>)</h4>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Nasabah</th>
                            <th>Produk</th>
                            <th>Status</th>
                            <th class="!text-right">Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftar)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-xs text-slate-500 italic">Tidak ada pengajuan pada tanggal ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar as $d):
                                $st = $d['status'] ?? '';
                                if ($st === 'disetujui') {
                                    $warna = 'bg-emerald-50 text-emerald-600';
                                } elseif ($st === 'ditolak') {
                                    $warna = 'bg-rose-50 text-rose-600';
                                } else {
                                    $warna = 'bg-amber-50 text-amber-600';
                                }
                            ?>
                                <tr>
                                    <td class="text-slate-600"><?= htmlspecialchars(date('H:i', strtotime($d['created_at']))); ?></td>
                                    <td class="font-semibold text-slate-900"><?= htmlspecialchars($d['nama_lengkap']); ?></td>
                                    <td class="text-slate-600"><?= htmlspecialchars($d['produk_diajukan'] ?: '-'); ?></td>
                                    <td><span class="pm-badge <?= $warna; ?>"><?= ucfirst($d['status'] ?: 'pending'); ?></span></td>
                                    <td class="!text-right text-slate-600"><?= htmlspecialchars($d['nama_petugas'] ?? 'Sistem'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        Chart.defaults.font.family = 'Plus Jakarta Sans';
        Chart.defaults.color = '#64748b';
        new Chart(document.getElementById('trenHarianChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($tren_label); ?>,
                datasets: [{
                    label: 'Pengajuan',
                    data: <?= json_encode($tren_data); ?>,
                    backgroundColor: '#0ea5e9',
                    borderRadius: 5,
                    maxBarThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(15,23,42,0.06)'
                        },
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>