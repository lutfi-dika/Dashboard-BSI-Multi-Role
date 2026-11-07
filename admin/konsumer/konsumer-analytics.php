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

// ==========================================
// 1. TREN PENGAJUAN 12 BULAN TERAKHIR
// ==========================================
$tren_label   = [];
$tren_total   = [];
$tren_setuju  = [];
$tren_tolak   = [];

for ($i = 11; $i >= 0; $i--) {
    $bulan_angka = date('m', strtotime("-$i months"));
    $tahun       = date('Y', strtotime("-$i months"));

    $stmt = mysqli_prepare($conn, "SELECT status, COUNT(*) AS total FROM nasabah_konsumer WHERE MONTH(created_at) = ? AND YEAR(created_at) = ? GROUP BY status");
    mysqli_stmt_bind_param($stmt, "ss", $bulan_angka, $tahun);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $t = 0;
    $s = 0;
    $tl = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $t += (int) $row['total'];
        if ($row['status'] === 'disetujui') $s = (int) $row['total'];
        if ($row['status'] === 'ditolak') $tl = (int) $row['total'];
    }
    mysqli_stmt_close($stmt);

    $tren_label[]  = date('M Y', strtotime("-$i months"));
    $tren_total[]  = $t;
    $tren_setuju[] = $s;
    $tren_tolak[]  = $tl;
}

// ==========================================
// 2. KOMPOSISI STATUS KESELURUHAN
// ==========================================
$komposisi = ['pending' => 0, 'disetujui' => 0, 'ditolak' => 0];
$resKomp = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM nasabah_konsumer GROUP BY status");
if ($resKomp) {
    while ($row = mysqli_fetch_assoc($resKomp)) {
        $s = $row['status'] ?: 'pending';
        if (isset($komposisi[$s])) $komposisi[$s] = (int) $row['total'];
    }
}
$total_semua = array_sum($komposisi);

// ==========================================
// 3. TOP 5 PRODUK DIAJUKAN
// ==========================================
$produk_label = [];
$produk_data  = [];
$resProduk = mysqli_query($conn, "SELECT COALESCE(NULLIF(TRIM(produk_diajukan), ''), 'Tidak diisi') AS produk, COUNT(*) AS total
                                   FROM nasabah_konsumer GROUP BY produk ORDER BY total DESC LIMIT 5");
if ($resProduk) {
    while ($row = mysqli_fetch_assoc($resProduk)) {
        $produk_label[] = $row['produk'];
        $produk_data[]  = (int) $row['total'];
    }
}

// ==========================================
// 4. RATA-RATA WAKTU PROSES (hari) untuk pengajuan yang sudah diputuskan
// ==========================================
$rata_proses = 0;
$resProses = mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) AS rata_jam
                                   FROM nasabah_konsumer WHERE status IN ('disetujui','ditolak') AND updated_at IS NOT NULL");
if ($resProses) {
    $rj = mysqli_fetch_assoc($resProses)['rata_jam'] ?? null;
    $rata_proses = $rj !== null ? round(((float) $rj) / 24, 1) : 0;
}

// ==========================================
// 5. RATA-RATA PENDAPATAN PEMOHON per status
// ==========================================
$rata_pendapatan = 0;
$resPendapatan = mysqli_query($conn, "SELECT AVG(pendapatan_bulanan) AS rata FROM nasabah_konsumer WHERE pendapatan_bulanan > 0");
if ($resPendapatan) {
    $rata_pendapatan = (float) (mysqli_fetch_assoc($resPendapatan)['rata'] ?? 0);
}

$tingkat_approval = ($komposisi['disetujui'] + $komposisi['ditolak']) > 0
    ? round(($komposisi['disetujui'] / ($komposisi['disetujui'] + $komposisi['ditolak'])) * 100, 1)
    : 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Dashboard Analytics | BSI Konsumer', 'konsumer'); ?>
</head>

<body class="text-slate-700 min-h-screen flex">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
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

        <?php premium_hero([
            'accent'   => 'konsumer',
            'eyebrow'  => 'BSI Konsumer',
            'title'    => 'Dashboard Analytics',
            'subtitle' => 'Visualisasi data pembiayaan dan performa segmen konsumer.',
            'stats'    => [
                ['label' => 'Total Pengajuan',    'value' => number_format($total_semua, 0, ',', '.')],
                ['label' => 'Tingkat Persetujuan', 'value' => $tingkat_approval . '%'],
                ['label' => 'Rata Proses',         'value' => $rata_proses . ' hari'],
            ],
        ]); ?>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-500">Total Pengajuan</p>
                <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1"><?= number_format($total_semua, 0, ',', '.'); ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-500">Tingkat Persetujuan</p>
                <p class="text-xl sm:text-2xl font-bold text-emerald-500 mt-1"><?= $tingkat_approval; ?>%</p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-500">Rata-rata Waktu Proses</p>
                <p class="text-xl sm:text-2xl font-bold text-sky-600 mt-1"><?= $rata_proses; ?> <span class="text-xs font-medium text-slate-500">hari</span></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d4">
                <p class="text-[11px] text-slate-500">Rata-rata Pendapatan Pemohon</p>
                <p class="text-lg sm:text-xl font-bold text-slate-900 mt-1">Rp<?= number_format($rata_pendapatan, 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="pm-card p-6 lg:col-span-2 pm-fade pm-d1">
                <h4 class="text-sm font-bold text-slate-900 mb-1">Tren Pengajuan 12 Bulan Terakhir</h4>
                <p class="text-xs text-slate-500 mb-4">Total pengajuan vs. yang disetujui vs. yang ditolak per bulan.</p>
                <div class="h-72"><canvas id="trenChart"></canvas></div>
            </div>
            <div class="pm-card p-6 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-900 mb-1">Komposisi Status</h4>
                <p class="text-xs text-slate-500 mb-4">Seluruh pengajuan yang tercatat.</p>
                <div class="h-56 flex items-center justify-center"><canvas id="statusChart"></canvas></div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs"><span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>Pending</span><span class="font-semibold text-slate-900"><?= $komposisi['pending']; ?></span></div>
                    <div class="flex items-center justify-between text-xs"><span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>Disetujui</span><span class="font-semibold text-slate-900"><?= $komposisi['disetujui']; ?></span></div>
                    <div class="flex items-center justify-between text-xs"><span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>Ditolak</span><span class="font-semibold text-slate-900"><?= $komposisi['ditolak']; ?></span></div>
                </div>
            </div>
        </div>

        <div class="pm-card p-6 pm-fade pm-d3">
            <h4 class="text-sm font-bold text-slate-900 mb-1">Produk Paling Diminati</h4>
            <p class="text-xs text-slate-500 mb-4">Top 5 produk pembiayaan berdasarkan jumlah pengajuan.</p>
            <?php if (empty($produk_label)): ?>
                <div class="text-center py-10 text-xs text-slate-500">Belum ada data pengajuan.</div>
            <?php else: ?>
                <div class="h-64"><canvas id="produkChart"></canvas></div>
            <?php endif; ?>
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

        const trenCtx = document.getElementById('trenChart').getContext('2d');
        const trenGrad = trenCtx.createLinearGradient(0, 0, 0, 280);
        trenGrad.addColorStop(0, 'rgba(14, 165, 233, 0.18)');
        trenGrad.addColorStop(1, 'rgba(14, 165, 233, 0)');

        new Chart(trenCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($tren_label); ?>,
                datasets: [{
                        label: 'Total Pengajuan',
                        data: <?= json_encode($tren_total); ?>,
                        borderColor: '#0ea5e9',
                        backgroundColor: trenGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 2
                    },
                    {
                        label: 'Disetujui',
                        data: <?= json_encode($tren_setuju); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 2
                    },
                    {
                        label: 'Ditolak',
                        data: <?= json_encode($tren_tolak); ?>,
                        borderColor: '#f43f5e',
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 2
                    }
                ]
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
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 6
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

        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Disetujui', 'Ditolak'],
                datasets: [{
                    data: [<?= $komposisi['pending']; ?>, <?= $komposisi['disetujui']; ?>, <?= $komposisi['ditolak']; ?>],
                    backgroundColor: ['#fbbf24', '#34d399', '#fb7185'],
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
                    }
                }
            }
        });

        <?php if (!empty($produk_label)): ?>
            new Chart(document.getElementById('produkChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($produk_label); ?>,
                    datasets: [{
                        label: 'Jumlah Pengajuan',
                        data: <?= json_encode($produk_data); ?>,
                        backgroundColor: '#6366f1',
                        borderRadius: 6,
                        maxBarThickness: 44
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(15,23,42,0.06)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>