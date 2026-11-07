<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../auth/login.php"); exit(); }
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) { header("Location: ../../pilih-karyawan.php"); exit(); }

$role_user = strtolower($_SESSION['role'] ?? '');
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Atasan');
$current_page = 'atasan-ai-insight.php';
if (!in_array($role_user, ['admin', 'atasan'], true)) { header('Location: ../../index.php'); exit(); }

$kolomTambahan = ['kolektibilitas', 'outstanding', 'target_outstanding', 'cabang', 'status_pengajuan'];
$skemaLengkap = true;
$cek = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro");
$kolomTersedia = [];
if ($cek) { while ($k = mysqli_fetch_assoc($cek)) { $kolomTersedia[] = $k['Field']; } }
foreach ($kolomTambahan as $kolom) { if (!in_array($kolom, $kolomTersedia, true)) { $skemaLengkap = false; break; } }

$insights = [];

if ($skemaLengkap) {
    $q = mysqli_query($conn, "SELECT COALESCE(SUM(outstanding),0) os, COALESCE(SUM(target_outstanding),0) tg, COALESCE(SUM(CASE WHEN kolektibilitas>=3 THEN outstanding ELSE 0 END),0) npf FROM nasabah_mikro");
    $r = $q ? mysqli_fetch_assoc($q) : ['os'=>0,'tg'=>0,'npf'=>0];
    $os = (float)$r['os']; $tg = (float)$r['tg']; $npf = (float)$r['npf'];
    $npfPersen = $os > 0 ? round(($npf/$os)*100, 2) : 0;
    $realisasi = $tg > 0 ? round(($os/$tg)*100, 1) : 0;

    if ($npfPersen >= 5) {
        $insights[] = ['level'=>'critical','icon'=>'⚠️','title'=>'Rasio NPF Mikro melewati ambang aman','text'=>"NPF segmen Mikro tercatat $npfPersen%, di atas batas aman 5%. Disarankan menelusuri unit dengan kolektibilitas ≥3 pada halaman NPF dan mempercepat tindak lanjut penagihan."];
    } elseif ($npfPersen >= 3) {
        $insights[] = ['level'=>'warning','icon'=>'🟡','title'=>'NPF Mikro mendekati ambang batas','text'=>"NPF berada di $npfPersen%, masih di bawah 5% namun perlu dipantau agar tidak terus meningkat."];
    } else {
        $insights[] = ['level'=>'good','icon'=>'✅','title'=>'Kualitas pembiayaan Mikro terjaga','text'=>"NPF segmen Mikro berada di $npfPersen%, tergolong sehat (di bawah 5%)."];
    }

    if ($tg > 0 && $realisasi < 70) {
        $insights[] = ['level'=>'warning','icon'=>'📉','title'=>'Realisasi outstanding di bawah target','text'=>"Realisasi outstanding Mikro baru mencapai $realisasi% dari target. Perlu dorongan akuisisi atau percepatan pencairan di cabang-cabang tertinggal."];
    } elseif ($tg > 0) {
        $insights[] = ['level'=>'good','icon'=>'🎯','title'=>'Realisasi outstanding on-track','text'=>"Realisasi outstanding Mikro mencapai $realisasi% dari target yang ditetapkan."];
    }

    $qCabang = mysqli_query($conn, "SELECT COALESCE(NULLIF(cabang,''),'Belum Diisi') cabang, COALESCE(SUM(outstanding),0) os, COALESCE(SUM(CASE WHEN kolektibilitas>=3 THEN outstanding ELSE 0 END),0) npf FROM nasabah_mikro GROUP BY cabang ORDER BY npf DESC LIMIT 1");
    if ($qCabang && ($rc = mysqli_fetch_assoc($qCabang)) && $rc['npf'] > 0) {
        $insights[] = ['level'=>'warning','icon'=>'📍','title'=>'Konsentrasi NPF tertinggi','text'=>"Cabang {$rc['cabang']} memiliki outstanding bermasalah terbesar, yaitu Rp " . number_format($rc['npf'],0,',','.') . ". Pertimbangkan supervisi tambahan untuk cabang ini."];
    }
} else {
    $insights[] = ['level'=>'warning','icon'=>'ℹ️','title'=>'Data KPI Mikro belum lengkap','text'=>"Jalankan migrasi sql/002_mikro_dashboard_kpi.sql agar insight NPF dan outstanding dapat dihitung otomatis."];
}

// Insight Konsumer
$q = mysqli_query($conn, "SELECT COUNT(*) total, SUM(CASE WHEN status='disetujui' THEN 1 ELSE 0 END) disetujui, SUM(CASE WHEN status='ditolak' THEN 1 ELSE 0 END) ditolak FROM nasabah_konsumer");
$rk = $q ? mysqli_fetch_assoc($q) : ['total'=>0,'disetujui'=>0,'ditolak'=>0];
$totalK = (int)($rk['total'] ?? 0);
if ($totalK > 0) {
    $rateApprove = round(((int)$rk['disetujui'] / $totalK) * 100, 1);
    $rateReject = round(((int)$rk['ditolak'] / $totalK) * 100, 1);
    if ($rateReject >= 30) {
        $insights[] = ['level'=>'warning','icon'=>'👥','title'=>'Tingkat penolakan pengajuan Konsumer cukup tinggi','text'=>"Sebanyak $rateReject% pengajuan Konsumer ditolak. Perlu ditelusuri apakah karena kualitas pengajuan atau kriteria yang terlalu ketat."];
    } else {
        $insights[] = ['level'=>'good','icon'=>'👥','title'=>'Approval rate Konsumer sehat','text'=>"Tingkat persetujuan pengajuan Konsumer mencapai $rateApprove%."];
    }
}

// Insight Pawning
$q = mysqli_query($conn, "SELECT COUNT(*) total, SUM(CASE WHEN status='Lelang' THEN 1 ELSE 0 END) lelang FROM pawning");
$rp = $q ? mysqli_fetch_assoc($q) : ['total'=>0,'lelang'=>0];
$totalP = (int)($rp['total'] ?? 0);
if ($totalP > 0) {
    $rateLelang = round(((int)$rp['lelang'] / $totalP) * 100, 1);
    if ($rateLelang >= 10) {
        $insights[] = ['level'=>'warning','icon'=>'🪙','title'=>'Rasio lelang gadai meningkat','text'=>"Sebanyak $rateLelang% transaksi gadai berakhir lelang. Perlu evaluasi proses pengingat jatuh tempo kepada nasabah."];
    } else {
        $insights[] = ['level'=>'good','icon'=>'🪙','title'=>'Portofolio gadai terkendali','text'=>"Hanya $rateLelang% transaksi gadai yang berakhir lelang, portofolio tergolong sehat."];
    }
}

$levelStyle = [
    'critical' => 'border-rose-500/30 bg-rose-500/5',
    'warning'  => 'border-amber-500/30 bg-amber-500/5',
    'good'     => 'border-emerald-500/30 bg-emerald-500/5',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Executive Insight | BSI Executive', 'atasan'); ?>
</head>
<body class="text-slate-800 min-h-screen flex flex-col md:flex-row">
    <?php include __DIR__ . '/../../components/sidebar.php'; ?>
    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
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
            'title'    => 'Executive Insight',
            'subtitle' => 'Ringkasan otomatis kondisi bisnis, dihasilkan langsung dari data terbaru.',
            'stats'    => [
                ['label' => 'Insight Aktif', 'value' => count($insights)],
            ],
        ]); ?>

        <div class="space-y-4">
            <?php foreach ($insights as $ins): ?>
                <div class="pm-card p-5 border pm-fade <?= $levelStyle[$ins['level']]; ?>">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl"><?= $ins['icon']; ?></span>
                        <div>
                            <h3 class="font-bold text-slate-800"><?= htmlspecialchars($ins['title']); ?></h3>
                            <p class="mt-1 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($ins['text']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$insights): ?>
                <div class="pm-card p-10 text-center text-slate-500 text-sm pm-fade">Belum cukup data untuk menghasilkan insight.</div>
            <?php endif; ?>
        </div>

        <p class="mt-6 text-xs text-slate-500">Insight dihitung otomatis berdasarkan aturan bisnis (rasio NPF, realisasi target, approval rate) dari data real-time, bukan opini manual.</p>
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
</body>
</html>
