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
$current_page = 'ai-insight';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'status_pengajuan'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

$insights = [];

if ($skemaLengkap) {
    // Insight 1: Rasio NPF
    $r = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT
            SUM(CASE WHEN kolektibilitas >= 3 THEN outstanding ELSE 0 END) AS npf,
            SUM(outstanding) AS total
        FROM nasabah_mikro WHERE status_pengajuan = 'Aktif'
    "));
    $npfRatio = ($r['total'] ?? 0) > 0 ? ($r['npf'] / $r['total']) * 100 : 0;
    if ($npfRatio > 5) {
        $insights[] = ['level' => 'danger', 'title' => 'Rasio NPF di atas ambang aman', 'desc' => 'Rasio NPF portofolio saat ini ' . number_format($npfRatio, 2) . '%, melebihi batas indikatif 5%. Disarankan percepat penanganan debitur kolektibilitas 3-5.'];
    } else {
        $insights[] = ['level' => 'good', 'title' => 'Rasio NPF terkendali', 'desc' => 'Rasio NPF portofolio saat ini ' . number_format($npfRatio, 2) . '%, masih dalam batas aman (<5%).'];
    }

    // Insight 2: sektor dominan
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT jenis_sektor, COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan='Aktif' GROUP BY jenis_sektor ORDER BY jml DESC LIMIT 1"));
    if ($r) {
        $insights[] = ['level' => 'info', 'title' => 'Sektor dominan: ' . $r['jenis_sektor'], 'desc' => 'Sektor ini menyumbang ' . $r['jml'] . ' debitur aktif, konsentrasi terbesar di portofolio. Pertimbangkan diversifikasi sektor untuk mitigasi risiko.'];
    }

    // Insight 3: pengajuan menumpuk
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan='Approval'"));
    $pending = (int) ($r['jml'] ?? 0);
    if ($pending > 0) {
        $insights[] = ['level' => $pending > 5 ? 'warning' : 'info', 'title' => "{$pending} pengajuan menunggu approval", 'desc' => 'Ada ' . $pending . ' pengajuan pembiayaan yang belum diputuskan. Percepat proses approval untuk menjaga kecepatan layanan.'];
    }

    // Insight 4: AO dengan outstanding tertinggi
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_ao, SUM(outstanding) AS os FROM nasabah_mikro WHERE status_pengajuan='Aktif' AND nama_ao IS NOT NULL AND nama_ao<>'' GROUP BY nama_ao ORDER BY os DESC LIMIT 1"));
    if ($r) {
        $insights[] = ['level' => 'good', 'title' => 'AO performa terbaik: ' . $r['nama_ao'], 'desc' => 'Mengelola outstanding tertinggi senilai Rp ' . number_format($r['os'], 0, ',', '.') . '. Pertimbangkan sebagai referensi best-practice untuk AO lain.'];
    }

    // Insight 5: jatuh tempo dekat
    $cekTgl = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'tanggal_jatuh_tempo'");
    if ($cekTgl && mysqli_num_rows($cekTgl) > 0) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan='Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND DATEDIFF(tanggal_jatuh_tempo, CURDATE()) BETWEEN 0 AND 30"));
        $jt = (int) ($r['jml'] ?? 0);
        if ($jt > 0) {
            $insights[] = ['level' => 'warning', 'title' => "{$jt} debitur jatuh tempo dalam 30 hari", 'desc' => 'Segera lakukan penagihan proaktif atau tawarkan perpanjangan untuk mencegah keterlambatan.'];
        }
    }
}
$levelStyle = [
    'good' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'border' => 'border-emerald-100', 'icon' => '✓'],
    'info' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'border' => 'border-sky-100', 'icon' => 'i'],
    'warning' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'border' => 'border-amber-100', 'icon' => '!'],
    'danger' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'border' => 'border-rose-100', 'icon' => '!!'],
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('AI Insight | BSI Mikro', 'mikro'); ?>
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
            'title'    => 'AI Insight',
            'subtitle' => 'Ringkasan otomatis kondisi portofolio berdasarkan data terkini.',
            'stats'    => [['label' => 'Insight Aktif', 'value' => count($insights)]],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/002_mikro_dashboard_kpi.sql</code> agar insight bisa dihitung.
            </div>
        <?php elseif (count($insights) === 0): ?>
            <div class="pm-card rounded-2xl p-10 text-center text-xs text-slate-400 italic">Belum cukup data untuk menghasilkan insight.</div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($insights as $ins):
                    $s = $levelStyle[$ins['level']];
                ?>
                    <div class="pm-card rounded-2xl p-5 flex items-start gap-4 border-l-4 <?= $s['border']; ?>">
                        <div class="w-9 h-9 rounded-xl <?= $s['bg']; ?> <?= $s['text']; ?> flex items-center justify-center shrink-0 font-bold text-xs"><?= $s['icon']; ?></div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800 mb-1"><?= htmlspecialchars($ins['title']); ?></h4>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($ins['desc']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
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
