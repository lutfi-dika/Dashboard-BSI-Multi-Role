<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../auth/login.php"); exit(); }
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) { header("Location: ../../pilih-karyawan.php"); exit(); }

$role_user = strtolower($_SESSION['role'] ?? '');
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Atasan');
$current_page = 'atasan-outstanding.php';
if (!in_array($role_user, ['admin', 'atasan'], true)) { header('Location: ../../index.php'); exit(); }

$kolomTambahan = ['outstanding', 'target_outstanding', 'cabang'];
$skemaLengkap = true;
$cek = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro");
$kolomTersedia = [];
if ($cek) { while ($k = mysqli_fetch_assoc($cek)) { $kolomTersedia[] = $k['Field']; } }
foreach ($kolomTambahan as $kolom) { if (!in_array($kolom, $kolomTersedia, true)) { $skemaLengkap = false; break; } }

$mikroOutstanding = 0; $mikroTarget = 0;
$cabangData = [];
if ($skemaLengkap) {
    $q = mysqli_query($conn, "SELECT COALESCE(SUM(outstanding),0) os, COALESCE(SUM(target_outstanding),0) tg FROM nasabah_mikro");
    if ($q && ($r = mysqli_fetch_assoc($q))) { $mikroOutstanding = (float)$r['os']; $mikroTarget = (float)$r['tg']; }
    $qCab = mysqli_query($conn, "SELECT COALESCE(NULLIF(cabang,''),'Belum Diisi') cabang, COALESCE(SUM(outstanding),0) os, COALESCE(SUM(target_outstanding),0) tg FROM nasabah_mikro GROUP BY cabang ORDER BY os DESC LIMIT 8");
    if ($qCab) { while ($row = mysqli_fetch_assoc($qCab)) { $cabangData[] = $row; } }
}

$q = mysqli_query($conn, "SELECT COALESCE(SUM(nilai_pinjaman),0) nilai, SUM(CASE WHEN status='Aktif' THEN 1 ELSE 0 END) aktif FROM pawning");
$rp = $q ? mysqli_fetch_assoc($q) : ['nilai'=>0,'aktif'=>0];
$pawningOutstanding = (float) ($rp['nilai'] ?? 0);

$totalOutstanding = $mikroOutstanding + $pawningOutstanding;
$realisasi = $mikroTarget > 0 ? round(($mikroOutstanding/$mikroTarget)*100,1) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Outstanding Executive | BSI Executive', 'atasan'); ?>
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
            'title'    => 'Outstanding',
            'subtitle' => 'Posisi pembiayaan berjalan segmen Mikro &amp; nilai pinjaman aktif Pawning.',
            'stats'    => [
                ['label' => 'Outstanding Gabungan', 'value' => 'Rp ' . number_format($totalOutstanding, 0, ',', '.')],
                ['label' => 'Mikro',                'value' => 'Rp ' . number_format($mikroOutstanding, 0, ',', '.')],
                ['label' => 'Pawning',              'value' => 'Rp ' . number_format($pawningOutstanding, 0, ',', '.')],
            ],
        ]); ?>

        <section class="grid gap-4 sm:grid-cols-3 mb-6">
            <div class="pm-card p-5 pm-fade pm-d1"><p class="text-[11px] font-bold uppercase text-slate-500">Outstanding Gabungan</p><p class="mt-2 text-2xl font-extrabold text-slate-800">Rp <?= number_format($totalOutstanding,0,',','.'); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d2"><p class="text-[11px] font-bold uppercase text-slate-500">Outstanding Mikro</p><p class="mt-2 text-2xl font-extrabold text-emerald-600">Rp <?= number_format($mikroOutstanding,0,',','.'); ?></p><p class="mt-1 text-xs text-slate-500">Target Rp <?= number_format($mikroTarget,0,',','.'); ?> (<?= $realisasi; ?>%)</p></div>
            <div class="pm-card p-5 pm-fade pm-d3"><p class="text-[11px] font-bold uppercase text-slate-500">Pinjaman Aktif Pawning</p><p class="mt-2 text-2xl font-extrabold text-amber-600">Rp <?= number_format($pawningOutstanding,0,',','.'); ?></p></div>
        </section>

        <?php if ($skemaLengkap && $cabangData): ?>
        <section class="pm-card p-5 pm-fade pm-d4">
            <h2 class="font-extrabold text-slate-800 mb-1">Outstanding vs Target per Cabang</h2>
            <p class="text-xs text-slate-500 mb-4">Segmen Mikro, diurutkan berdasarkan outstanding terbesar.</p>
            <div class="space-y-4">
                <?php $maxVal = max(1, max(array_merge(array_column($cabangData,'os'), array_column($cabangData,'tg')))); ?>
                <?php foreach ($cabangData as $c): ?>
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-semibold text-slate-800"><?= htmlspecialchars($c['cabang']); ?></span>
                            <span class="text-slate-500">Rp <?= number_format($c['os'],0,',','.'); ?> / target Rp <?= number_format($c['tg'],0,',','.'); ?></span>
                        </div>
                        <div class="pm-progress" style="height:10px;"><span class="pm-progress-fill" style="width: <?= round(($c['os']/$maxVal)*100,1); ?>%;background:#10b981"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php elseif (!$skemaLengkap): ?>
        <section class="pm-card p-10 text-center text-slate-500 text-sm pm-fade pm-d4">
            Data outstanding per cabang belum tersedia. Jalankan migrasi <code class="text-slate-600">sql/002_mikro_dashboard_kpi.sql</code>.
        </section>
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
