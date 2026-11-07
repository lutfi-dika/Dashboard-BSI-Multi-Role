<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Path config disesuaikan karena file ini ada di dalam folder views/
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../pilih-karyawan.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');

// ==========================================
// HELPER: cek apakah sebuah kolom ada di tabel
// ==========================================
function kolom_ada($conn, $table, $col)
{
    $table = mysqli_real_escape_string($conn, $table);
    $col   = mysqli_real_escape_string($conn, $col);
    $q = mysqli_query($conn, "
        SELECT COUNT(*) as jml FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col'
    ");
    $r = mysqli_fetch_assoc($q);
    return ($r['jml'] ?? 0) > 0;
}

// Kandidat nama kolom "siapa yang input" data (silakan sesuaikan jika beda)
$kandidat_kolom_marketing = ['dibuat_oleh', 'created_by', 'user_id', 'id_user', 'marketing', 'sales_id'];
$kolom_marketing_mikro = null;
$kolom_marketing_konsumer = null;
foreach ($kandidat_kolom_marketing as $k) {
    if (!$kolom_marketing_mikro && kolom_ada($conn, 'nasabah_mikro', $k)) $kolom_marketing_mikro = $k;
    if (!$kolom_marketing_konsumer && kolom_ada($conn, 'nasabah_konsumer', $k)) $kolom_marketing_konsumer = $k;
}

// ==========================================
// 1. TREN BULANAN (12 BULAN TERAKHIR) - MIKRO & KONSUMER
// ==========================================
$bulan_label = [];
$data_mikro_bulanan = [];
$data_konsumer_bulanan = [];

for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $bulan_label[] = date('M Y', strtotime("-$i months"));

    $q1 = mysqli_query($conn, "SELECT COUNT(*) as jml FROM nasabah_mikro WHERE DATE_FORMAT(created_at,'%Y-%m') = '$ym'");
    $data_mikro_bulanan[] = (int)(mysqli_fetch_assoc($q1)['jml'] ?? 0);

    $q2 = mysqli_query($conn, "SELECT COUNT(*) as jml FROM nasabah_konsumer WHERE DATE_FORMAT(created_at,'%Y-%m') = '$ym'");
    $data_konsumer_bulanan[] = (int)(mysqli_fetch_assoc($q2)['jml'] ?? 0);
}

// ==========================================
// 2. PENCAPAIAN BULAN INI vs TARGET
// ==========================================
// TARGET: ganti angka ini sesuai target bulanan tim kamu
$target_mikro_bulanan    = 30;
$target_konsumer_bulanan = 50;

$bulan_ini = date('Y-m');
$q_mikro_bulan_ini = mysqli_query($conn, "SELECT COUNT(*) as jml FROM nasabah_mikro WHERE DATE_FORMAT(created_at,'%Y-%m') = '$bulan_ini'");
$capaian_mikro = (int)(mysqli_fetch_assoc($q_mikro_bulan_ini)['jml'] ?? 0);

$q_konsumer_bulan_ini = mysqli_query($conn, "SELECT COUNT(*) as jml FROM nasabah_konsumer WHERE DATE_FORMAT(created_at,'%Y-%m') = '$bulan_ini'");
$capaian_konsumer = (int)(mysqli_fetch_assoc($q_konsumer_bulan_ini)['jml'] ?? 0);

$persen_capaian_mikro    = $target_mikro_bulanan > 0 ? min(100, round(($capaian_mikro / $target_mikro_bulanan) * 100)) : 0;
$persen_capaian_konsumer = $target_konsumer_bulanan > 0 ? min(100, round(($capaian_konsumer / $target_konsumer_bulanan) * 100)) : 0;

function warna_progress($persen)
{
    if ($persen >= 100) return 'bg-emerald-500';
    if ($persen >= 60) return 'bg-indigo-500';
    if ($persen >= 30) return 'bg-amber-500';
    return 'bg-red-500';
}

// ==========================================
// 3. PERFORMA PER MARKETING/USER (kalau kolomnya ada)
// ==========================================
$performa_mikro = [];
$performa_konsumer = [];

if ($kolom_marketing_mikro) {
    $q = mysqli_query($conn, "SELECT `$kolom_marketing_mikro` as nama, COUNT(*) as jml FROM nasabah_mikro GROUP BY `$kolom_marketing_mikro` ORDER BY jml DESC LIMIT 8");
    while ($row = mysqli_fetch_assoc($q)) $performa_mikro[] = $row;
}
if ($kolom_marketing_konsumer) {
    $q = mysqli_query($conn, "SELECT `$kolom_marketing_konsumer` as nama, COUNT(*) as jml FROM nasabah_konsumer GROUP BY `$kolom_marketing_konsumer` ORDER BY jml DESC LIMIT 8");
    while ($row = mysqli_fetch_assoc($q)) $performa_konsumer[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kinerja - Data Center</title>
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
    </style>
</head>

<body class="bg-white text-slate-700 min-h-screen flex">

    <aside class="hidden md:flex w-64 bg-white border-r border-slate-100 flex-col justify-between py-6 px-5">
        <div>
            <div class="flex items-center gap-2.5 px-2 mb-8">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">DC</div>
                <span class="font-bold text-slate-800">Data Center</span>
            </div>

            <nav class="space-y-1">
                <a href="../index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="9" rx="1" />
                        <rect x="14" y="3" width="7" height="5" rx="1" />
                        <rect x="14" y="12" width="7" height="9" rx="1" />
                        <rect x="3" y="16" width="7" height="5" rx="1" />
                    </svg>
                    Overview Dashboard
                </a>

                <a href="Kinerja.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-indigo-50 text-indigo-600 font-semibold text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="M18.7 8 12 14.7l-3.3-3.4L3 17" />
                    </svg>
                    Kinerja
                </a>

                <a href="pipeline.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">
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
                    <a href="../admin/Mikro/mikro-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">Sektor Mikro</a>
                <?php endif; ?>
                <?php if (in_array($role_user, ['admin', 'konsumer', 'atasan'], true)): ?>
                    <a href="../admin/konsumer/konsumer-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">Sektor Konsumer</a>
                <?php endif; ?>
                <?php if (in_array($role_user, ['admin', 'pawning', 'atasan'], true)): ?>
                    <a href="../admin/pawning/pawning-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-medium text-sm transition">Sektor Gadai</a>
                <?php endif; ?>
                <?php if ($role_user === 'atasan'): ?>
                    <a href="../admin/Atasan/admin-dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-violet-50 text-violet-700 font-semibold text-sm transition">Pusat Dashboard Atasan</a>
                <?php endif; ?>
            </nav>
        </div>
        <a href="../auth/logout.php" class="px-3 py-2 text-red-500 font-medium text-sm hover:bg-red-50 rounded-xl">Keluar Sistem</a>
    </aside>

    <main class="flex-1 p-6 md:p-8 overflow-y-auto max-h-screen relative">

        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Kinerja</h1>
                <p class="text-sm text-slate-500 mt-0.5">Pencapaian target, tren bulanan, dan performa tim.</p>
            </div>
            <div class="flex items-center gap-2.5">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=6366f1&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-indigo-500/10">
                <span class="text-sm font-semibold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
            </div>
        </header>

        <!-- TARGET VS PENCAPAIAN -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
            <div class="card p-6 rounded-2xl shadow-xs">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-bold text-slate-800">Target Sektor Mikro Bulan Ini</h4>
                    <span class="text-xs font-bold text-slate-500"><?= $capaian_mikro; ?> / <?= $target_mikro_bulanan; ?></span>
                </div>
                <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full <?= warna_progress($persen_capaian_mikro); ?> transition-all" style="width: <?= $persen_capaian_mikro; ?>%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-2"><?= $persen_capaian_mikro; ?>% dari target tercapai</p>
            </div>

            <div class="card p-6 rounded-2xl shadow-xs">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-bold text-slate-800">Target Sektor Konsumer Bulan Ini</h4>
                    <span class="text-xs font-bold text-slate-500"><?= $capaian_konsumer; ?> / <?= $target_konsumer_bulanan; ?></span>
                </div>
                <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full <?= warna_progress($persen_capaian_konsumer); ?> transition-all" style="width: <?= $persen_capaian_konsumer; ?>%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-2"><?= $persen_capaian_konsumer; ?>% dari target tercapai</p>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mb-6">* Angka target di atas masih hardcode di kode (<code>$target_mikro_bulanan</code> / <code>$target_konsumer_bulanan</code>). Buat tabel <code>target_bulanan</code> kalau mau ini diatur dari database.</p>

        <!-- TREN 12 BULAN -->
        <div class="card p-6 rounded-2xl min-h-[280px] sm:min-h-[350px] flex flex-col justify-between shadow-xs mb-6">
            <h4 class="text-sm font-bold tracking-wide text-slate-800 mb-4">Tren Pendataan 12 Bulan Terakhir</h4>
            <div class="relative flex-1 h-72">
                <canvas id="trenTahunan"></canvas>
            </div>
        </div>

        <!-- PERFORMA PER MARKETING -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="card rounded-2xl shadow-xs overflow-hidden">
                <div class="px-6 pt-6 pb-4">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Performa Input - Sektor Mikro</h4>
                </div>
                <?php if ($kolom_marketing_mikro): ?>
                    <div class="divide-y divide-slate-50">
                        <?php foreach ($performa_mikro as $p): ?>
                            <div class="flex justify-between items-center px-6 py-3 text-sm">
                                <span class="font-medium text-slate-700"><?= htmlspecialchars($p['nama'] ?: '(kosong)'); ?></span>
                                <span class="font-bold text-indigo-600"><?= $p['jml']; ?> data</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($performa_mikro)): ?>
                            <div class="px-6 py-8 text-center text-xs text-slate-400 italic">Belum ada data.</div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mx-6 mb-6 p-4 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-700">
                        Kolom penanggung jawab input belum ditemukan di tabel <code>nasabah_mikro</code>. Tambahkan salah satu kolom seperti <code>dibuat_oleh</code> agar data ini bisa muncul, contoh:<br>
                        <code class="block mt-2 bg-white/60 p-2 rounded">ALTER TABLE nasabah_mikro ADD dibuat_oleh VARCHAR(100);</code>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card rounded-2xl shadow-xs overflow-hidden">
                <div class="px-6 pt-6 pb-4">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Performa Input - Sektor Konsumer</h4>
                </div>
                <?php if ($kolom_marketing_konsumer): ?>
                    <div class="divide-y divide-slate-50">
                        <?php foreach ($performa_konsumer as $p): ?>
                            <div class="flex justify-between items-center px-6 py-3 text-sm">
                                <span class="font-medium text-slate-700"><?= htmlspecialchars($p['nama'] ?: '(kosong)'); ?></span>
                                <span class="font-bold text-sky-600"><?= $p['jml']; ?> data</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($performa_konsumer)): ?>
                            <div class="px-6 py-8 text-center text-xs text-slate-400 italic">Belum ada data.</div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mx-6 mb-6 p-4 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-700">
                        Kolom penanggung jawab input belum ditemukan di tabel <code>nasabah_konsumer</code>. Tambahkan salah satu kolom seperti <code>dibuat_oleh</code> agar data ini bisa muncul, contoh:<br>
                        <code class="block mt-2 bg-white/60 p-2 rounded">ALTER TABLE nasabah_konsumer ADD dibuat_oleh VARCHAR(100);</code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('trenTahunan').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($bulan_label); ?>,
                datasets: [{
                        label: 'Sektor Mikro',
                        data: <?= json_encode($data_mikro_bulanan); ?>,
                        backgroundColor: '#6366f1',
                        borderRadius: 6
                    },
                    {
                        label: 'Sektor Konsumer',
                        data: <?= json_encode($data_konsumer_bulanan); ?>,
                        backgroundColor: '#38bdf8',
                        borderRadius: 6
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
                            color: '#94a3b8'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(15,23,42,0.04)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>