<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi ke database
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';
require_once __DIR__ . '/../../helpers/delete-activity.php';

// 1. Cek apakah sudah login (password)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// 2. Cek apakah sudah lulus verifikasi OTP
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');

if (!in_array($role_user, ['admin', 'pawning', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

// CSRF Token (dipakai untuk aksi hapus data)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle delete activity using centralized helper
handle_delete_activity($conn, $role_user, 'pawning-dashboard.php');

// ==========================================
// 1. QUERY AMBIL DATA DINAMIS DARI DATABASE
// ==========================================
$query_mikro = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(omzet_bulanan) as total_omzet FROM nasabah_mikro");
$data_mikro  = mysqli_fetch_assoc($query_mikro);
$total_mikro = $data_mikro['total'] ?? 0;
$omzet_mikro = $data_mikro['total_omzet'] ?? 0;

$query_konsumer = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(pendapatan_bulanan) as total_pendapatan FROM nasabah_konsumer");
$data_konsumer      = mysqli_fetch_assoc($query_konsumer);
$total_konsumer     = $data_konsumer['total'] ?? 0;
$pendapatan_konsumer = $data_konsumer['total_pendapatan'] ?? 0;

// Catatan: tabel "pawning" & kolomnya menyesuaikan gadai-dashboard.php
// (id, nama_pemilik, barang_jaminan, taksiran_harga, nilai_pinjaman, status, created_at)
$query_pawning = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(nilai_pinjaman) as total_pinjaman FROM pawning");
$data_pawning     = $query_pawning ? mysqli_fetch_assoc($query_pawning) : ['total' => 0, 'total_pinjaman' => 0];
$total_pawning    = $data_pawning['total'] ?? 0;
$pinjaman_pawning = $data_pawning['total_pinjaman'] ?? 0;

// Statistik status barang gadai (Aktif / Lunas / Lelang)
$gadai_aktif  = 0;
$gadai_lunas  = 0;
$gadai_lelang = 0;
$qStatusGadai = mysqli_query($conn, "SELECT status, COUNT(*) AS jml FROM pawning GROUP BY status");
if ($qStatusGadai) {
    while ($rsg = mysqli_fetch_assoc($qStatusGadai)) {
        $s = $rsg['status'] ?? '';
        if ($s === 'Aktif') {
            $gadai_aktif = (int) $rsg['jml'];
        } elseif ($s === 'Lunas') {
            $gadai_lunas = (int) $rsg['jml'];
        } elseif ($s === 'Lelang') {
            $gadai_lelang = (int) $rsg['jml'];
        }
    }
}

// Produk Gadai BSI (Gadai Emas iB, Gadai Non Emas, Fleksi Emas iB)
$produkGadaiDef = [
    'Gadai Emas iB'  => ['warna' => '#d4a017', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ket' => 'Jaminan emas batangan / perhiasan'],
    'Gadai Non Emas' => ['warna' => '#64748b', 'bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'ket' => 'Jaminan BPKB / barang bergerak'],
    'Fleksi Emas iB' => ['warna' => '#0369a1', 'bg' => 'bg-sky-50',   'text' => 'text-sky-700',   'ket' => 'Pinjaman berulang jaminan emas'],
];
$statGadai = [];
$qGadai = mysqli_query($conn, "SELECT COALESCE(jenis_gadai, 'Gadai Emas iB') AS jenis_gadai, COUNT(*) AS jml, SUM(nilai_pinjaman) AS total_pinjam, SUM(CASE WHEN status='Aktif' THEN 1 ELSE 0 END) AS aktif FROM pawning GROUP BY jenis_gadai");
if ($qGadai) {
    while ($rg = mysqli_fetch_assoc($qGadai)) {
        $statGadai[$rg['jenis_gadai']] = ['jml' => (int)$rg['jml'], 'pinjam' => (float)$rg['total_pinjam'], 'aktif' => (int)$rg['aktif']];
    }
}

$total_semua_nasabah = $total_mikro + $total_konsumer + $total_pawning;

$persen_mikro    = $total_semua_nasabah > 0 ? round(($total_mikro / $total_semua_nasabah) * 100) : 0;
$persen_konsumer = $total_semua_nasabah > 0 ? round(($total_konsumer / $total_semua_nasabah) * 100) : 0;
$persen_pawning  = $total_semua_nasabah > 0 ? max(0, 100 - $persen_mikro - $persen_konsumer) : 0;

// Catatan: kolom teks di-CONVERT + di-COLLATE eksplisit ke utf8mb4_unicode_ci
// untuk menghindari error "Illegal mix of collations" saat UNION antar tabel
// yang collation defaultnya berbeda-beda.
$query_recent = "
    (SELECT id,
            CONVERT(nama_pemilik USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama,
            CONVERT(nama_usaha USING utf8mb4) COLLATE utf8mb4_unicode_ci AS info,
            'Mikro' COLLATE utf8mb4_unicode_ci AS segmen,
            created_at
     FROM nasabah_mikro)
    UNION ALL
    (SELECT id,
            CONVERT(nama_lengkap USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama,
            CONVERT(produk_diajukan USING utf8mb4) COLLATE utf8mb4_unicode_ci AS info,
            'Konsumer' COLLATE utf8mb4_unicode_ci AS segmen,
            created_at
     FROM nasabah_konsumer)
    UNION ALL
    (SELECT id,
            CONVERT(nama_pemilik USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nama,
            CONVERT(barang_jaminan USING utf8mb4) COLLATE utf8mb4_unicode_ci AS info,
            'Gadai' COLLATE utf8mb4_unicode_ci AS segmen,
            created_at
     FROM pawning)
    ORDER BY created_at DESC LIMIT 6
";
$result_recent = mysqli_query($conn, $query_recent);

// ==========================================
// 2. TREN 6 BULAN TERAKHIR (DATA ASLI DARI DATABASE)
// ==========================================
$tren_label         = [];
$tren_data_mikro    = [];
$tren_data_konsumer = [];
$tren_data_pawning  = [];

for ($i = 5; $i >= 0; $i--) {
    $bulan_angka = date('m', strtotime("-$i months"));
    $tahun       = date('Y', strtotime("-$i months"));

    $stmtMikro = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM nasabah_mikro WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    mysqli_stmt_bind_param($stmtMikro, "ss", $bulan_angka, $tahun);
    mysqli_stmt_execute($stmtMikro);
    $rMikro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMikro));
    mysqli_stmt_close($stmtMikro);

    $stmtKons = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM nasabah_konsumer WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    mysqli_stmt_bind_param($stmtKons, "ss", $bulan_angka, $tahun);
    mysqli_stmt_execute($stmtKons);
    $rKons = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtKons));
    mysqli_stmt_close($stmtKons);

    $stmtPawn = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM pawning WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    if ($stmtPawn) {
        mysqli_stmt_bind_param($stmtPawn, "ss", $bulan_angka, $tahun);
        mysqli_stmt_execute($stmtPawn);
        $rPawn = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPawn));
        mysqli_stmt_close($stmtPawn);
    } else {
        $rPawn = ['total' => 0];
    }

    $tren_label[]         = date('M', strtotime("-$i months"));
    $tren_data_mikro[]    = (int) ($rMikro['total'] ?? 0);
    $tren_data_konsumer[] = (int) ($rKons['total'] ?? 0);
    $tren_data_pawning[]  = (int) ($rPawn['total'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Dashboard Gadai | BSI Pawning', 'pawning'); ?>
    <style>
        .spark-wrap { height: 32px; }
    </style>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/pawning-sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen relative w-full min-w-0">
      <div class="max-w-[1600px] mx-auto">

        <!-- TOPBAR: hamburger mobile + status koneksi + profil -->
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
            'title'    => 'Dashboard Gadai (Pawning)',
            'subtitle' => 'Kinerja gadai emas, nilai pinjaman, status barang jaminan, dan portofolio produk gadai syariah.',
            'stats'    => [
                ['label' => 'Barang Jaminan',   'value' => number_format($total_pawning)],
                ['label' => 'Nilai Pinjaman',   'value' => 'Rp ' . number_format($pinjaman_pawning, 0, ',', '.')],
                ['label' => 'Status Aktif',     'value' => number_format($gadai_aktif)],
            ],
            'actions'  => ($role_user === 'admin' || $role_user === 'pawning')
                ? [['href' => 'pawning-tambah.php', 'label' => '+ Tambah Data Gadai']]
                : [],
        ]); ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
            <?php
            premium_kpi_card([
                'label' => 'Segmen Mikro', 'value' => number_format($total_mikro),
                'sub' => 'Total Unit Bisnis', 'sub_class' => 'text-indigo-500', 'tone' => 'indigo',
                'spark' => 'sparkMikro', 'delay' => 1,
                'icon' => '<path d="m2 7 10-5 10 5v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" /><path d="M12 22V12" />',
            ]);
            premium_kpi_card([
                'label' => 'Segmen Konsumer', 'value' => number_format($total_konsumer),
                'sub' => 'Total Individu', 'sub_class' => 'text-sky-500', 'tone' => 'sky',
                'spark' => 'sparkKonsumer', 'delay' => 2,
                'icon' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />',
            ]);
            premium_kpi_card([
                'label' => 'Segmen Gadai', 'value' => number_format($total_pawning),
                'sub' => 'Total Barang Jaminan', 'sub_class' => 'text-amber-500', 'tone' => 'amber',
                'spark' => 'sparkGadai', 'delay' => 3,
                'icon' => '<rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />',
            ]);
            premium_kpi_card([
                'label' => 'Total Gabungan', 'value' => number_format($total_semua_nasabah),
                'sub' => 'Database Records', 'sub_class' => 'text-emerald-500', 'tone' => 'emerald',
                'spark' => 'sparkGabungan', 'delay' => 4,
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
            ]);
            ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
            <?php
            premium_kpi_card([
                'label' => 'Omzet Mikro Terdata', 'value' => 'Rp ' . number_format($omzet_mikro, 0, ',', '.'),
                'sub' => 'Finansial Sektor', 'sub_class' => 'text-indigo-500', 'tone' => 'indigo', 'delay' => 1,
                'icon' => '<line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />',
            ]);
            premium_kpi_card([
                'label' => 'Pendapatan Konsumer Terdata', 'value' => 'Rp ' . number_format($pendapatan_konsumer, 0, ',', '.'),
                'sub' => 'Finansial Sektor', 'sub_class' => 'text-sky-500', 'tone' => 'sky', 'delay' => 2,
                'icon' => '<line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />',
            ]);
            premium_kpi_card([
                'label' => 'Nilai Pinjaman Gadai Terdata', 'value' => 'Rp ' . number_format($pinjaman_pawning, 0, ',', '.'),
                'sub' => 'Finansial Sektor', 'sub_class' => 'text-amber-500', 'tone' => 'amber', 'delay' => 3,
                'icon' => '<line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />',
            ]);
            ?>
        </div>

        <div class="pm-card p-5 sm:p-6 mb-6 pm-fade pm-d2">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                <div>
                    <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Produk Gadai BSI</h4>
                    <p class="text-[11px] text-slate-400 mt-0.5">Jenis layanan gadai syariah sesuai Bank Syariah Indonesia</p>
                </div>
                <a href="pawning-data.php" class="text-xs font-bold text-amber-600 hover:underline">Kelola Data &rarr;</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                <?php foreach ($produkGadaiDef as $nama => $def): $st = $statGadai[$nama] ?? ['jml' => 0, 'pinjam' => 0, 'aktif' => 0]; ?>
                    <a href="pawning-produk.php?p=<?= urlencode($nama); ?>" class="pm-card pm-card-hover !rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="pm-badge <?= $def['bg']; ?> <?= $def['text']; ?>"><?= htmlspecialchars($nama); ?></span>
                            <span class="text-[10px] text-emerald-500 font-bold"><?= number_format($st['aktif']); ?> aktif</span>
                        </div>
                        <h3 class="text-lg sm:text-xl font-extrabold text-slate-900"><?= number_format($st['jml']); ?> <span class="text-[10px] font-semibold text-slate-400">barang jaminan</span></h3>
                        <p class="text-[11px] text-slate-500 mt-1 truncate">Rp <?= number_format($st['pinjam'], 0, ',', '.'); ?> <span class="text-slate-400">nilai pinjaman</span></p>
                        <div class="pm-progress mt-3" style="height:6px;">
                            <?php $maksPinjam = max(array_map(fn($v) => $v['pinjam'], $statGadai) ?: [1]); ?>
                            <span class="pm-progress-fill" style="width:<?= $maksPinjam > 0 ? max(4, round(($st['pinjam'] / $maksPinjam) * 100)) : 4; ?>%;background:<?= $def['warna']; ?>"></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
            <div class="pm-card p-5 sm:p-6 sm:col-span-2 xl:col-span-2 min-h-[300px] sm:min-h-[350px] flex flex-col justify-between pm-fade pm-d1">
                <div class="flex justify-between items-center mb-4 gap-2">
                    <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Tren Pertumbuhan Pendataan</h4>
                    <span class="text-[10px] bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-md text-slate-500 font-bold whitespace-nowrap">Live Metric</span>
                </div>
                <div class="relative flex-1 h-56 sm:h-72">
                    <canvas id="trenChart"></canvas>
                </div>
            </div>

            <div class="pm-card p-5 sm:p-6 min-h-[300px] sm:min-h-[350px] flex flex-col pm-fade pm-d2">
                <h4 class="text-sm font-extrabold tracking-tight text-slate-800 mb-4">Komposisi Status Barang Gadai</h4>
                <div class="relative flex-1 flex items-center justify-center">
                    <canvas id="statusGadaiChart" class="max-h-[140px] sm:max-h-[160px]"></canvas>
                </div>
                <div class="space-y-2.5 mt-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Aktif</span>
                        <span class="font-bold text-slate-800"><?= number_format($gadai_aktif); ?></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-sky-500"></span>Lunas</span>
                        <span class="font-bold text-slate-800"><?= number_format($gadai_lunas); ?></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-amber-500"></span>Lelang</span>
                        <span class="font-bold text-slate-800"><?= number_format($gadai_lelang); ?></span>
                    </div>
                </div>
            </div>

            <div class="pm-card p-5 sm:p-6 min-h-[300px] sm:min-h-[350px] flex flex-col pm-fade pm-d3">
                <h4 class="text-sm font-extrabold tracking-tight text-slate-800 mb-4">Proporsi Segmen</h4>
                <div class="relative flex-1 flex items-center justify-center">
                    <canvas id="proporsiChart" class="max-h-[160px] sm:max-h-[180px]"></canvas>
                </div>
                <div class="space-y-2.5 mt-4">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-indigo-500"></span>Mikro</span>
                        <span class="font-bold text-slate-800"><?= $persen_mikro; ?>%</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-sky-400"></span>Konsumer</span>
                        <span class="font-bold text-slate-800"><?= $persen_konsumer; ?>%</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-2 text-slate-500 font-medium"><span class="w-2 h-2 rounded-full bg-amber-400"></span>Gadai</span>
                        <span class="font-bold text-slate-800"><?= $persen_pawning; ?>%</span>
                    </div>
                </div>
            </div>

            <div class="pm-card p-5 sm:p-6 xl:col-span-1 flex flex-col justify-start gap-3 pm-fade pm-d4">
                <h4 class="text-sm font-extrabold tracking-tight text-slate-800 mb-1">Aksi Cepat Panel</h4>

                <?php if ($role_user === 'admin' || $role_user === 'mikro'): ?>
                    <a href="../Mikro/mikro-tambah.php" class="w-full inline-flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold py-3 px-4 rounded-xl transition-all shadow-sm shadow-indigo-600/20 cursor-pointer">+ Tambah Data Mikro</a>
                <?php endif; ?>

                <?php if ($role_user === 'admin' || $role_user === 'konsumer'): ?>
                    <a href="../konsumer/konsumer-tambah.php" class="pm-btn pm-btn-ghost w-full">+ Tambah Data Konsumer</a>
                <?php endif; ?>

                <?php if ($role_user === 'admin' || $role_user === 'pawning'): ?>
                    <a href="pawning-tambah.php" class="pm-btn pm-btn-primary w-full">+ Tambah Data Gadai</a>
                <?php endif; ?>

                <?php if ($role_user !== 'admin' && $role_user !== 'mikro' && $role_user !== 'konsumer' && $role_user !== 'pawning'): ?>
                    <div class="text-xs text-slate-400 italic bg-slate-50 p-3 rounded-xl border border-dashed border-slate-200 text-center">Akun Anda memiliki hak akses View-Only (Membaca).</div>
                <?php endif; ?>
            </div>

            <div class="pm-card xl:col-span-3 overflow-hidden pm-fade pm-d2">
                <div class="flex justify-between items-center px-4 sm:px-6 pt-6 pb-4">
                    <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Aktivitas Terakhir (Gabungan Sektor)</h4>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[560px]">
                        <thead>
                            <tr>
                                <th>Nama Mitra/Nasabah</th>
                                <th>Entitas / Produk</th>
                                <th>Segmen</th>
                                <th>Tanggal Masuk</th>
                                <th class="!text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_recent && mysqli_num_rows($result_recent) > 0): ?>
                                <?php while ($log = mysqli_fetch_assoc($result_recent)): ?>
                                    <?php
                                    $seg = $log['segmen'] ?? '';
                                    if ($seg === 'Mikro') {
                                        $badgeSegmen = 'bg-indigo-50 text-indigo-600 border border-indigo-100';
                                    } elseif ($seg === 'Konsumer') {
                                        $badgeSegmen = 'bg-sky-50 text-sky-600 border border-sky-100';
                                    } elseif ($seg === 'Gadai') {
                                        $badgeSegmen = 'bg-amber-50 text-amber-600 border border-amber-100';
                                    } else {
                                        $badgeSegmen = 'bg-slate-50 text-slate-500 border border-slate-100';
                                    }
                                    ?>
                                    <tr>
                                        <td class="font-bold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($log['nama']); ?></td>
                                        <td class="text-slate-500 truncate max-w-[200px]"><?= htmlspecialchars($log['info']); ?></td>
                                        <td>
                                            <span class="pm-badge <?= $badgeSegmen; ?>"><?= htmlspecialchars($log['segmen']); ?></span>
                                        </td>
                                        <td class="text-slate-400 text-xs whitespace-nowrap"><?= date('d M Y', strtotime($log['created_at'])); ?></td>
                                        <td class="!text-right">
                                            <?php
                                            $bolehHapus = ($log['segmen'] === 'Mikro' && ($role_user === 'admin' || $role_user === 'mikro'))
                                                || ($log['segmen'] === 'Konsumer' && ($role_user === 'admin' || $role_user === 'konsumer'))
                                                || ($log['segmen'] === 'Gadai' && ($role_user === 'admin' || $role_user === 'pawning'));
                                            ?>
                                            <?php if ($bolehHapus): ?>
                                                <form method="POST" action="pawning-dashboard.php" class="inline" onsubmit="return confirm('Hapus data &quot;<?= htmlspecialchars(addslashes($log['nama'])); ?>&quot; secara permanen?');">
                                                    <input type="hidden" name="hapus_aktivitas" value="1">
                                                    <input type="hidden" name="id" value="<?= (int)$log['id']; ?>">
                                                    <input type="hidden" name="segmen" value="<?= htmlspecialchars($log['segmen']); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                    <button type="submit" title="Hapus data" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6" />
                                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                            <path d="M10 11v6" />
                                                            <path d="M14 11v6" />
                                                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-xs text-slate-400 italic">Belum ada aktivitas pendataan terbaru di database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function sparkline(id, data, color) {
            new Chart(document.getElementById(id).getContext('2d'), {
                type: 'line',
                data: { labels: <?= json_encode($tren_label); ?>, datasets: [{ data: data, borderColor: color, backgroundColor: 'transparent', borderWidth: 2, tension: 0.4, pointRadius: 0, fill: false }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } } }
            });
        }
        const spMikro = <?= json_encode($tren_data_mikro); ?>;
        const spKonsumer = <?= json_encode($tren_data_konsumer); ?>;
        const spGadai = <?= json_encode($tren_data_pawning); ?>;
        sparkline('sparkMikro', spMikro, '#6366f1');
        sparkline('sparkKonsumer', spKonsumer, '#0ea5e9');
        sparkline('sparkGadai', spGadai, '#f59e0b');
        sparkline('sparkGabungan', spMikro.map((v, i) => v + spKonsumer[i] + spGadai[i]), '#10b981');

        const ctx = document.getElementById('trenChart').getContext('2d');
        const indGrad = ctx.createLinearGradient(0, 0, 0, 300);
        indGrad.addColorStop(0, 'rgba(99, 102, 241, 0.18)');
        indGrad.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

        const skyGrad = ctx.createLinearGradient(0, 0, 0, 300);
        skyGrad.addColorStop(0, 'rgba(56, 189, 248, 0.18)');
        skyGrad.addColorStop(1, 'rgba(56, 189, 248, 0.0)');

        const amberGrad = ctx.createLinearGradient(0, 0, 0, 300);
        amberGrad.addColorStop(0, 'rgba(217, 119, 6, 0.18)');
        amberGrad.addColorStop(1, 'rgba(217, 119, 6, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($tren_label); ?>,
                datasets: [{
                        label: 'Sektor Mikro',
                        data: <?= json_encode($tren_data_mikro); ?>,
                        borderColor: '#6366f1',
                        backgroundColor: indGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#6366f1'
                    },
                    {
                        label: 'Sektor Konsumer',
                        data: <?= json_encode($tren_data_konsumer); ?>,
                        borderColor: '#38bdf8',
                        backgroundColor: skyGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#38bdf8'
                    },
                    {
                        label: 'Sektor Gadai',
                        data: <?= json_encode($tren_data_pawning); ?>,
                        borderColor: '#d97706',
                        backgroundColor: amberGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: '#d97706'
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
                            color: '#94a3b8',
                            font: {
                                family: 'Plus Jakarta Sans'
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(15, 23, 42, 0.04)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: {
                                family: 'Plus Jakarta Sans'
                            }
                        }
                    }
                }
            }
        });

        const ctxStatusGadai = document.getElementById('statusGadaiChart').getContext('2d');
        new Chart(ctxStatusGadai, {
            type: 'doughnut',
            data: {
                labels: ['Aktif', 'Lunas', 'Lelang'],
                datasets: [{
                    data: [<?= $gadai_aktif; ?>, <?= $gadai_lunas; ?>, <?= $gadai_lelang; ?>],
                    backgroundColor: ['#10b981', '#0ea5e9', '#f59e0b'],
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
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.label}: ${ctx.raw} barang`
                        }
                    }
                }
            }
        });

        const ctxDonut = document.getElementById('proporsiChart').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Mikro', 'Konsumer', 'Gadai'],
                datasets: [{
                    data: [<?= $persen_mikro; ?>, <?= $persen_konsumer; ?>, <?= $persen_pawning; ?>],
                    backgroundColor: ['#6366f1', '#7dd3fc', '#f59e0b'],
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
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `${ctx.label}: ${ctx.raw}%`
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>
