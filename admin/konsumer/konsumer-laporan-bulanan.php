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

$bulan = (int) ($_GET['bulan'] ?? date('n'));
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int) date('n');

$stmt = mysqli_prepare($conn, "SELECT k.*, u.nama_lengkap AS nama_petugas
                                FROM nasabah_konsumer k
                                LEFT JOIN users u ON k.user_id = u.id
                                WHERE MONTH(k.created_at) = ? AND YEAR(k.created_at) = ?
                                ORDER BY k.created_at ASC");
mysqli_stmt_bind_param($stmt, "ii", $bulan, $tahun);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$daftar = [];
$ringkasan = ['pending' => 0, 'disetujui' => 0, 'ditolak' => 0];
$totalPendapatan = 0;
$perMinggu = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
while ($row = mysqli_fetch_assoc($res)) {
    $daftar[] = $row;
    $s = $row['status'] ?: 'pending';
    if (isset($ringkasan[$s])) $ringkasan[$s]++;
    $totalPendapatan += (float) $row['pendapatan_bulanan'];
    $mingguKe = (int) ceil(((int) date('j', strtotime($row['created_at']))) / 7);
    if ($mingguKe > 5) $mingguKe = 5;
    $perMinggu[$mingguKe]++;
}
$totalBulanIni = count($daftar);
$tingkatApproval = ($ringkasan['disetujui'] + $ringkasan['ditolak']) > 0
    ? round(($ringkasan['disetujui'] / ($ringkasan['disetujui'] + $ringkasan['ditolak'])) * 100)
    : 0;

// Perbandingan dengan bulan sebelumnya
$bulanSebelumnya = $bulan - 1;
$tahunSebelumnya = $tahun;
if ($bulanSebelumnya < 1) {
    $bulanSebelumnya = 12;
    $tahunSebelumnya--;
}
$stmtSblm = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM nasabah_konsumer WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
mysqli_stmt_bind_param($stmtSblm, "ii", $bulanSebelumnya, $tahunSebelumnya);
mysqli_stmt_execute($stmtSblm);
$totalSebelumnya = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSblm))['total'] ?? 0);
mysqli_stmt_close($stmtSblm);
$perubahan = $totalSebelumnya > 0 ? round((($totalBulanIni - $totalSebelumnya) / $totalSebelumnya) * 100) : ($totalBulanIni > 0 ? 100 : 0);

$namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Laporan Bulanan | BSI Konsumer', 'konsumer'); ?>
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
            'title'    => 'Laporan Bulanan',
            'subtitle' => 'Rekap agregat pengajuan konsumer per bulan.',
            'stats'    => [
                ['label' => 'Total Pengajuan',     'value' => number_format($totalBulanIni)],
                ['label' => 'Tingkat Persetujuan', 'value' => $tingkatApproval . '%'],
                ['label' => 'Bulan Lalu',          'value' => number_format($totalSebelumnya)],
            ],
        ]); ?>
        </div>

        <form method="GET" class="pm-card p-4 mb-6 flex flex-wrap items-center gap-3 no-print pm-fade pm-d1">
            <select name="bulan" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                <?php for ($b = 1; $b <= 12; $b++): ?>
                    <option value="<?= $b; ?>" <?= $b === $bulan ? 'selected' : ''; ?>><?= $namaBulan[$b]; ?></option>
                <?php endfor; ?>
            </select>
            <select name="tahun" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                <?php for ($t = (int) date('Y'); $t >= (int) date('Y') - 3; $t--): ?>
                    <option value="<?= $t; ?>" <?= $t === $tahun ? 'selected' : ''; ?>><?= $t; ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="pm-btn pm-btn-primary">Tampilkan</button>
            <button type="button" onclick="window.print()" class="ml-auto pm-btn pm-btn-ghost">Cetak</button>
        </form>

        <h2 class="text-sm font-bold text-slate-600 mb-3">Rekap <?= $namaBulan[$bulan]; ?> <?= $tahun; ?></h2>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-500">Total Pengajuan</p>
                <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1"><?= $totalBulanIni; ?></p>
                <p class="text-[10px] mt-1 <?= $perubahan >= 0 ? 'text-emerald-500' : 'text-rose-500'; ?>"><?= $perubahan >= 0 ? '+' : ''; ?><?= $perubahan; ?>% dari bulan lalu</p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-500">Tingkat Persetujuan</p>
                <p class="text-xl sm:text-2xl font-bold text-emerald-500 mt-1"><?= $tingkatApproval; ?>%</p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-500">Disetujui / Ditolak</p>
                <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1"><?= $ringkasan['disetujui']; ?> / <?= $ringkasan['ditolak']; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d4">
                <p class="text-[11px] text-slate-500">Total Pendapatan Pemohon</p>
                <p class="text-base sm:text-lg font-bold text-slate-900 mt-1">Rp<?= number_format($totalPendapatan, 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 no-print">
            <div class="pm-card p-6 lg:col-span-2 pm-fade pm-d1">
                <h4 class="text-sm font-bold text-slate-900 mb-1">Distribusi per Minggu</h4>
                <p class="text-xs text-slate-500 mb-4">Jumlah pengajuan masuk tiap minggu dalam bulan ini.</p>
                <div class="h-56"><canvas id="mingguChart"></canvas></div>
            </div>
            <div class="pm-card p-6 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-900 mb-1">Komposisi Status</h4>
                <div class="h-48 flex items-center justify-center"><canvas id="statusBulananChart"></canvas></div>
            </div>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d3">
            <div class="px-4 sm:px-6 pt-5 pb-3">
                <h4 class="text-sm font-bold text-slate-900">Daftar Pengajuan (<?= $totalBulanIni; ?>)</h4>
            </div>
            <div class="pm-table-wrap pm-scroll max-h-[480px] overflow-y-auto">
                <table class="pm-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Nasabah</th>
                            <th>Produk</th>
                            <th>Status</th>
                            <th class="!text-right">Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftar)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-xs text-slate-500 italic">Tidak ada pengajuan pada bulan ini.</td>
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
                                    <td class="text-slate-600"><?= htmlspecialchars(date('d-m-Y', strtotime($d['created_at']))); ?></td>
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

        new Chart(document.getElementById('mingguChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4', 'Minggu 5'],
                datasets: [{
                    label: 'Pengajuan',
                    data: <?= json_encode(array_values($perMinggu)); ?>,
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                    maxBarThickness: 44
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

        new Chart(document.getElementById('statusBulananChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Disetujui', 'Ditolak'],
                datasets: [{
                    data: [<?= $ringkasan['pending']; ?>, <?= $ringkasan['disetujui']; ?>, <?= $ringkasan['ditolak']; ?>],
                    backgroundColor: ['#fbbf24', '#34d399', '#fb7185'],
                    borderWidth: 0,
                    cutout: '68%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>