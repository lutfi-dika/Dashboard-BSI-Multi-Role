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

$role_user = strtolower($_SESSION['role'] ?? '');
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Atasan');
$current_page = 'admin-dashboard.php';

if (!in_array($role_user, ['admin', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

// ==========================================
// DETEKSI SKEMA (kolom KPI lengkap hanya ada di nasabah_mikro, lihat sql/002_*.sql)
// ==========================================
$kolomMikro = ['outstanding', 'target_outstanding', 'kolektibilitas', 'status_pengajuan', 'cabang'];
$skemaMikroLengkap = true;
$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro");
$kolomTersedia = [];
if ($cekKolom) {
    while ($k = mysqli_fetch_assoc($cekKolom)) {
        $kolomTersedia[] = $k['Field'];
    }
}
foreach ($kolomMikro as $kolom) {
    if (!in_array($kolom, $kolomTersedia, true)) {
        $skemaMikroLengkap = false;
        break;
    }
}

// ==========================================
// KPI PER SEGMEN
// ==========================================
function segmenTotal(mysqli $conn, string $table, string $amountColumn): array
{
    $sql = "SELECT COUNT(*) AS total, COALESCE(SUM(`$amountColumn`), 0) AS nominal FROM `$table`";
    $result = mysqli_query($conn, $sql);
    return $result ? mysqli_fetch_assoc($result) : ['total' => 0, 'nominal' => 0];
}

$mikro    = segmenTotal($conn, 'nasabah_mikro', 'omzet_bulanan');
$konsumer = segmenTotal($conn, 'nasabah_konsumer', 'pendapatan_bulanan');
$gadai    = segmenTotal($conn, 'pawning', 'nilai_pinjaman');

$totalNasabah = (int) $mikro['total'] + (int) $konsumer['total'] + (int) $gadai['total'];

// Outstanding & NPF riil (hanya tersedia untuk segmen Mikro sesuai skema saat ini)
$totalOutstandingMikro = 0;
$totalTargetMikro      = 0;
$npfOutstandingMikro    = 0;
if ($skemaMikroLengkap) {
    $q = mysqli_query($conn, "
        SELECT
            COALESCE(SUM(outstanding), 0) AS total_outstanding,
            COALESCE(SUM(target_outstanding), 0) AS total_target,
            COALESCE(SUM(CASE WHEN kolektibilitas >= 3 THEN outstanding ELSE 0 END), 0) AS npf_outstanding
        FROM nasabah_mikro
    ");
    if ($q && ($r = mysqli_fetch_assoc($q))) {
        $totalOutstandingMikro = (float) $r['total_outstanding'];
        $totalTargetMikro      = (float) $r['total_target'];
        $npfOutstandingMikro   = (float) $r['npf_outstanding'];
    }
}
$totalOutstandingGabungan = $totalOutstandingMikro + (float) $gadai['nominal'];
$npfPersen        = $totalOutstandingMikro > 0 ? round(($npfOutstandingMikro / $totalOutstandingMikro) * 100, 2) : 0;
$realisasiPersen  = $totalTargetMikro > 0 ? round(($totalOutstandingMikro / $totalTargetMikro) * 100, 1) : 0;

// Status approval konsumer (untuk kartu ringkas)
$konsumerDisetujui = 0; $konsumerPending = 0; $konsumerDitolak = 0;
$qStatusKonsumer = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM nasabah_konsumer GROUP BY status");
if ($qStatusKonsumer) {
    while ($row = mysqli_fetch_assoc($qStatusKonsumer)) {
        $s = $row['status'] ?? '';
        if ($s === 'disetujui') {
            $konsumerDisetujui = (int) $row['total'];
        } elseif ($s === 'ditolak') {
            $konsumerDitolak = (int) $row['total'];
        } else {
            $konsumerPending = (int) $row['total'];
        }
    }
}

// Aktivitas terbaru gabungan seluruh segmen
$recentQuery = "
    (SELECT nama_pemilik AS nama, 'Mikro' AS segmen, created_at FROM nasabah_mikro)
    UNION ALL
    (SELECT nama_lengkap AS nama, 'Konsumer' AS segmen, created_at FROM nasabah_konsumer)
    UNION ALL
    (SELECT nama_pemilik AS nama, 'Gadai' AS segmen, created_at FROM pawning)
    ORDER BY created_at DESC LIMIT 6
";
$recent = mysqli_query($conn, $recentQuery);

$badgeSegmen = [
    'Mikro'    => 'bg-emerald-500/10 text-emerald-600',
    'Konsumer' => 'bg-sky-500/10 text-sky-600',
    'Gadai'    => 'bg-amber-500/10 text-amber-600',
];

// ==========================================
// TREN GABUNGAN 6 BULAN TERAKHIR (untuk sparkline & grafik utama)
// ==========================================
function trenPerBulan(mysqli $conn, string $table): array
{
    $hasil = [];
    for ($i = 5; $i >= 0; $i--) {
        $bulan = date('m', strtotime("-$i months"));
        $tahun = date('Y', strtotime("-$i months"));
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM `$table` WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
        mysqli_stmt_bind_param($stmt, "ss", $bulan, $tahun);
        mysqli_stmt_execute($stmt);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $hasil[] = (int) ($r['total'] ?? 0);
    }
    return $hasil;
}

$trenLabel      = [];
for ($i = 5; $i >= 0; $i--) {
    $trenLabel[] = date('M', strtotime("-$i months"));
}
$trenMikro    = trenPerBulan($conn, 'nasabah_mikro');
$trenKonsumer = trenPerBulan($conn, 'nasabah_konsumer');
$trenGadai    = trenPerBulan($conn, 'pawning');
$trenTotal    = [];
foreach ($trenLabel as $idx => $lbl) {
    $trenTotal[] = $trenMikro[$idx] + $trenKonsumer[$idx] + $trenGadai[$idx];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Dashboard Executive | BSI Executive', 'atasan'); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .spark-wrap { height: 40px; }
        .ring-wrap { position: relative; width: 108px; height: 108px; margin: 0 auto; }
        .ring-wrap canvas { position: relative; z-index: 1; }
        .ring-center { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; flex-direction: column; z-index: 2; pointer-events: none; }
    </style>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
      <div class="max-w-[1600px] mx-auto">
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
            'title'    => 'Dashboard Atasan',
            'subtitle' => 'Ringkasan performa seluruh segmen &mdash; Mikro, Konsumer, dan Gadai.',
            'stats'    => [
                ['label' => 'Total Nasabah',        'value' => number_format($totalNasabah)],
                ['label' => 'Outstanding Gabungan', 'value' => 'Rp ' . number_format($totalOutstandingGabungan, 0, ',', '.')],
                ['label' => 'NPF Mikro',            'value' => $npfPersen . '%'],
            ],
        ]); ?>

        <!-- KPI UTAMA -->
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mt-6">
            <?php
            premium_kpi_card([
                'label' => 'Total Nasabah', 'value' => number_format($totalNasabah),
                'sub' => 'Gabungan 3 segmen', 'sub_class' => 'text-emerald-500', 'tone' => 'emerald',
                'spark' => 'sparkNasabah',
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
            ]);
            premium_kpi_card([
                'label' => 'Outstanding Gabungan', 'value' => 'Rp ' . number_format($totalOutstandingGabungan, 0, ',', '.'),
                'sub' => 'Mikro + Gadai', 'sub_class' => 'text-sky-500', 'tone' => 'sky',
                'spark' => 'sparkOutstanding',
                'icon' => '<rect x="2" y="5" width="20" height="14" rx="2" /><line x1="2" y1="10" x2="22" y2="10" />',
            ]);
            premium_kpi_card([
                'label' => 'NPF Mikro', 'value' => $npfPersen . '%', 'value_class' => $npfPersen >= 5 ? '!text-rose-600' : '',
                'sub' => 'Kol. &ge; 3', 'sub_class' => $npfPersen >= 5 ? 'text-rose-500' : 'text-violet-500',
                'tone' => $npfPersen >= 5 ? 'rose' : 'violet',
                'spark' => 'sparkNpf',
                'icon' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />',
            ]);
            premium_kpi_card([
                'label' => 'Realisasi vs Target', 'value' => $realisasiPersen . '%',
                'sub' => 'Outstanding vs target', 'sub_class' => 'text-amber-500', 'tone' => 'amber',
                'spark' => 'sparkRealisasi',
                'icon' => '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" />',
            ]);
            ?>
        </section>

        <!-- ANALITIK: GRAFIK UTAMA + RING KPI -->
        <section class="mt-6 grid gap-4 xl:grid-cols-3">
            <div class="pm-card p-5 sm:p-6 xl:col-span-2 pm-fade pm-d1">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Analitik Pertumbuhan Nasabah</h4>
                        <p class="mt-1 text-xs text-slate-500">Nasabah baru per segmen, 6 bulan terakhir</p>
                    </div>
                </div>
                <div class="relative h-64 sm:h-72">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
            <div class="pm-card p-5 sm:p-6 pm-fade pm-d2">
                <h4 class="text-sm font-extrabold tracking-tight text-slate-800 mb-4">Proporsi Segmen</h4>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="ring-wrap" style="width:76px;height:76px;">
                            <canvas id="ringMikro"></canvas>
                            <div class="ring-center"><span class="text-[11px] font-extrabold text-emerald-600"><?= $totalNasabah > 0 ? round($mikro['total'] / $totalNasabah * 100) : 0; ?>%</span></div>
                        </div>
                        <p class="mt-2 text-[11px] font-bold text-slate-600">Mikro</p>
                    </div>
                    <div>
                        <div class="ring-wrap" style="width:76px;height:76px;">
                            <canvas id="ringKonsumer"></canvas>
                            <div class="ring-center"><span class="text-[11px] font-extrabold text-sky-600"><?= $totalNasabah > 0 ? round($konsumer['total'] / $totalNasabah * 100) : 0; ?>%</span></div>
                        </div>
                        <p class="mt-2 text-[11px] font-bold text-slate-600">Konsumer</p>
                    </div>
                    <div>
                        <div class="ring-wrap" style="width:76px;height:76px;">
                            <canvas id="ringGadai"></canvas>
                            <div class="ring-center"><span class="text-[11px] font-extrabold text-amber-600"><?= $totalNasabah > 0 ? round($gadai['total'] / $totalNasabah * 100) : 0; ?>%</span></div>
                        </div>
                        <p class="mt-2 text-[11px] font-bold text-slate-600">Gadai</p>
                    </div>
                </div>
                <div class="mt-5 pt-5 border-t border-slate-100 space-y-3">
                    <div class="ring-wrap" style="width:100%;height:14px;margin:0;">
                        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-2.5 rounded-full <?= $npfPersen >= 5 ? 'bg-rose-500' : 'bg-emerald-500'; ?>" style="width:<?= min(100, $npfPersen * 4); ?>%"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500 font-medium">NPF Mikro</span>
                        <span class="font-bold <?= $npfPersen >= 5 ? 'text-rose-600' : 'text-emerald-600'; ?>"><?= $npfPersen; ?>%</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- RINGKASAN PER SEGMEN -->
        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <a href="atasan-monitoring-mikro.php" class="pm-card pm-card-hover p-5 block pm-fade pm-d1">
                <span class="pm-badge bg-emerald-50 text-emerald-600 !px-3 !py-1.5 inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18" /><path d="M5 21V7l8-4v18" /><path d="M19 21V11l-6-4" /><path d="M9 9v.01" /><path d="M9 12v.01" /><path d="M9 15v.01" /><path d="M9 18v.01" /></svg>
                    Mikro
                </span>
                <p class="mt-4 text-2xl font-extrabold text-slate-800"><?= number_format($mikro['total']); ?></p>
                <p class="text-xs text-slate-500 mt-1">Omzet terdata Rp <?= number_format($mikro['nominal'], 0, ',', '.'); ?></p>
            </a>
            <a href="atasan-monitoring-konsumer.php" class="pm-card pm-card-hover p-5 block pm-fade pm-d2">
                <span class="pm-badge bg-sky-50 text-sky-600 !px-3 !py-1.5 inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                    Konsumer
                </span>
                <p class="mt-4 text-2xl font-extrabold text-slate-800"><?= number_format($konsumer['total']); ?></p>
                <p class="text-xs text-slate-500 mt-1"><?= $konsumerDisetujui; ?> disetujui &middot; <?= $konsumerPending; ?> pending &middot; <?= $konsumerDitolak; ?> ditolak</p>
            </a>
            <a href="atasan-monitoring-pawning.php" class="pm-card pm-card-hover p-5 block pm-fade pm-d3">
                <span class="pm-badge bg-amber-50 text-amber-600 !px-3 !py-1.5 inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M14.8 9a2.8 2.8 0 0 0-2.8-1.5c-1.9 0-3 1-3 2.2s1.1 1.9 3 2.3 3 1.1 3 2.3-1.1 2.2-3 2.2A2.8 2.8 0 0 1 9.2 15" /><line x1="12" y1="6" x2="12" y2="7.5" /><line x1="12" y1="16.5" x2="12" y2="18" /></svg>
                    Gadai
                </span>
                <p class="mt-4 text-2xl font-extrabold text-slate-800"><?= number_format($gadai['total']); ?></p>
                <p class="text-xs text-slate-500 mt-1">Nilai pinjaman Rp <?= number_format($gadai['nominal'], 0, ',', '.'); ?></p>
            </a>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold tracking-tight text-slate-900">Akses Cepat</h2>
                        <p class="mt-1 text-xs text-slate-500">Modul performa &amp; laporan untuk pemantauan eksekutif.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <a href="atasan-kpi.php" class="pm-card pm-card-hover p-5 block pm-fade pm-d1">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-violet-50 text-violet-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17" /><polyline points="16 7 22 7 22 13" /></svg>
                        </span>
                        <p class="mt-3 font-bold text-slate-800">KPI</p>
                        <p class="mt-1 text-xs text-slate-500">Realisasi vs target seluruh segmen</p>
                    </a>
                    <a href="atasan-npf.php" class="pm-card pm-card-hover p-5 block pm-fade pm-d2">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-rose-50 text-rose-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7" /><polyline points="16 17 22 17 22 11" /></svg>
                        </span>
                        <p class="mt-3 font-bold text-slate-800">NPF</p>
                        <p class="mt-1 text-xs text-slate-500">Kualitas pembiayaan &amp; kolektibilitas</p>
                    </a>
                    <a href="atasan-outstanding.php" class="pm-card pm-card-hover p-5 block pm-fade pm-d3">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-sky-50 text-sky-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2" /><line x1="2" y1="10" x2="22" y2="10" /></svg>
                        </span>
                        <p class="mt-3 font-bold text-slate-800">Outstanding</p>
                        <p class="mt-1 text-xs text-slate-500">Posisi pembiayaan berjalan</p>
                    </a>
                    <a href="atasan-ai-insight.php" class="pm-card pm-card-hover p-5 block pm-fade pm-d4">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-amber-50 text-amber-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6" /><path d="M10 22h4" /><path d="M12 2a7 7 0 0 0-7 7c0 3 2 4.5 3 6h8c1-1.5 3-3 3-6a7 7 0 0 0-7-7Z" /></svg>
                        </span>
                        <p class="mt-3 font-bold text-slate-800">Executive Insight</p>
                        <p class="mt-1 text-xs text-slate-500">Ringkasan otomatis kondisi bisnis</p>
                    </a>
                </div>
            </div>
            <aside class="pm-card overflow-hidden pm-fade pm-d2">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-extrabold tracking-tight text-slate-800">Aktivitas Terbaru</h2>
                    <p class="mt-1 text-xs text-slate-500">6 entri terakhir seluruh segmen</p>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php if ($recent && mysqli_num_rows($recent) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                            <div class="px-5 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="truncate text-sm font-bold text-slate-800"><?= htmlspecialchars($row['nama']); ?></p>
                                    <span class="shrink-0 rounded-md px-2 py-1 text-[10px] font-bold <?= $badgeSegmen[$row['segmen']] ?? 'bg-slate-500/10 text-slate-600'; ?>"><?= htmlspecialchars($row['segmen']); ?></span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500"><?= date('d M Y, H:i', strtotime($row['created_at'])); ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="px-5 py-10 text-center text-sm text-slate-500">Belum ada aktivitas pendataan.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </section>
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

    <script>
        const trenLabel  = <?= json_encode($trenLabel); ?>;
        const trenMikro  = <?= json_encode($trenMikro); ?>;
        const trenKonsumer = <?= json_encode($trenKonsumer); ?>;
        const trenGadai  = <?= json_encode($trenGadai); ?>;
        const trenTotal  = <?= json_encode($trenTotal); ?>;

        function sparkline(id, data, color) {
            new Chart(document.getElementById(id).getContext('2d'), {
                type: 'line',
                data: {
                    labels: trenLabel,
                    datasets: [{
                        data: data,
                        borderColor: color,
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 0,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false } },
                    elements: { line: { borderJoinStyle: 'round' } }
                }
            });
        }
        sparkline('sparkNasabah', trenTotal, '#10b981');
        sparkline('sparkOutstanding', trenTotal.map(v => v * <?= round($totalOutstandingGabungan / max(1, $totalNasabah)); ?>), '#0ea5e9');
        sparkline('sparkNpf', trenTotal.map(v => v * <?= round($npfPersen * 100) / max(1, 100); ?>), '#8b5cf6');
        sparkline('sparkRealisasi', trenTotal.map(v => v * <?= round($realisasiPersen * 100) / max(1, 100); ?>), '#f59e0b');

        function ringChart(id, value) {
            new Chart(document.getElementById(id).getContext('2d'), {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [value, 100 - value],
                        backgroundColor: [
                            id === 'ringMikro' ? '#10b981' : (id === 'ringKonsumer' ? '#0ea5e9' : '#f59e0b'),
                            '#eef2f7'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: { legend: { display: false }, tooltip: { enabled: false } }
                }
            });
        }
        ringChart('ringMikro', <?= $totalNasabah > 0 ? round($mikro['total'] / $totalNasabah * 100) : 0; ?>);
        ringChart('ringKonsumer', <?= $totalNasabah > 0 ? round($konsumer['total'] / $totalNasabah * 100) : 0; ?>);
        ringChart('ringGadai', <?= $totalNasabah > 0 ? round($gadai['total'] / $totalNasabah * 100) : 0; ?>);

        new Chart(document.getElementById('growthChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: trenLabel,
                datasets: [
                    { label: 'Mikro', data: trenMikro, backgroundColor: '#10b981', borderRadius: 5, maxBarThickness: 20 },
                    { label: 'Konsumer', data: trenKonsumer, backgroundColor: '#0ea5e9', borderRadius: 5, maxBarThickness: 20 },
                    { label: 'Gadai', data: trenGadai, backgroundColor: '#f59e0b', borderRadius: 5, maxBarThickness: 20 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 11 }, usePointStyle: true, boxWidth: 8 } } },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans' } } },
                    y: { stacked: true, grid: { color: 'rgba(15, 23, 42, 0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans' } } }
                }
            }
        });
    </script>
</body>
</html>
