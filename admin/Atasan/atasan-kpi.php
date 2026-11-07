<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../auth/login.php"); exit(); }
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) { header("Location: ../../pilih-karyawan.php"); exit(); }

$role_user = strtolower($_SESSION['role'] ?? '');
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Atasan');
$current_page = 'atasan-kpi.php';
if (!in_array($role_user, ['admin', 'atasan'], true)) { header('Location: ../../index.php'); exit(); }

// Skema mikro
$kolomTambahan = ['status_pengajuan', 'outstanding', 'target_outstanding', 'cabang'];
$skemaLengkap = true;
$cek = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro");
$kolomTersedia = [];
if ($cek) { while ($k = mysqli_fetch_assoc($cek)) { $kolomTersedia[] = $k['Field']; } }
foreach ($kolomTambahan as $kolom) { if (!in_array($kolom, $kolomTersedia, true)) { $skemaLengkap = false; break; } }

// KPI Mikro: realisasi outstanding vs target
$mikroOutstanding = 0; $mikroTarget = 0; $mikroAktif = 0; $mikroTotal = 0;
if ($skemaLengkap) {
    $q = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN status_pengajuan='Aktif' THEN 1 ELSE 0 END) AS aktif, COALESCE(SUM(outstanding),0) AS outstanding, COALESCE(SUM(target_outstanding),0) AS target FROM nasabah_mikro");
    if ($q && ($r = mysqli_fetch_assoc($q))) {
        $mikroTotal = (int) $r['total']; $mikroAktif = (int) $r['aktif'];
        $mikroOutstanding = (float) $r['outstanding']; $mikroTarget = (float) $r['target'];
    }
}
$mikroRealisasi = $mikroTarget > 0 ? round(($mikroOutstanding / $mikroTarget) * 100, 1) : 0;

// KPI Konsumer: approval rate
$q = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN status='disetujui' THEN 1 ELSE 0 END) AS disetujui FROM nasabah_konsumer");
$rk = $q ? mysqli_fetch_assoc($q) : ['total'=>0,'disetujui'=>0];
$konsumerTotal = (int) ($rk['total'] ?? 0);
$konsumerDisetujui = (int) ($rk['disetujui'] ?? 0);
$konsumerRate = $konsumerTotal > 0 ? round(($konsumerDisetujui / $konsumerTotal) * 100, 1) : 0;

// KPI Pawning: rasio aktif vs lelang (indikator kesehatan portofolio gadai)
$q = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(CASE WHEN status='Aktif' THEN 1 ELSE 0 END) AS aktif, SUM(CASE WHEN status='Lelang' THEN 1 ELSE 0 END) AS lelang, COALESCE(SUM(nilai_pinjaman),0) AS nilai FROM pawning");
$rp = $q ? mysqli_fetch_assoc($q) : ['total'=>0,'aktif'=>0,'lelang'=>0,'nilai'=>0];
$pawningTotal = (int) ($rp['total'] ?? 0);
$pawningAktif = (int) ($rp['aktif'] ?? 0);
$pawningLelang = (int) ($rp['lelang'] ?? 0);
$pawningNilai = (float) ($rp['nilai'] ?? 0);
$pawningSehat = $pawningTotal > 0 ? round(($pawningAktif / $pawningTotal) * 100, 1) : 0;

// KPI per AO (Mikro) - top 5
$topAo = [];
if ($skemaLengkap) {
    $qAo = mysqli_query($conn, "SELECT COALESCE(NULLIF(nama_ao,''),'Belum Diisi') AS ao, COUNT(*) AS total, COALESCE(SUM(outstanding),0) AS outstanding FROM nasabah_mikro GROUP BY ao ORDER BY outstanding DESC LIMIT 5");
    if ($qAo) { while ($row = mysqli_fetch_assoc($qAo)) { $topAo[] = $row; } }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('KPI Executive | BSI Executive', 'atasan'); ?>
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
            'title'    => 'Key Performance Indicator',
            'subtitle' => 'Realisasi kinerja per segmen dibandingkan target/acuan operasional.',
            'stats'    => [
                ['label' => 'Realisasi Mikro',   'value' => $mikroRealisasi . '%'],
                ['label' => 'Approval Konsumer', 'value' => $konsumerRate . '%'],
                ['label' => 'Pawning Sehat',     'value' => $pawningSehat . '%'],
            ],
        ]); ?>

        <section class="grid gap-4 lg:grid-cols-3 mb-6">
            <div class="pm-card p-5 pm-fade pm-d1">
                <span class="inline-flex rounded-xl bg-emerald-500/10 px-3 py-1.5 text-xs font-extrabold text-emerald-600">🏪 Mikro &mdash; Realisasi Outstanding</span>
                <p class="mt-4 text-3xl font-extrabold text-slate-800"><?= $mikroRealisasi; ?>%</p>
                <div class="pm-progress mt-3" style="height:8px;"><span class="pm-progress-fill" style="width: <?= min($mikroRealisasi, 100); ?>%;background:#10b981"></span></div>
                <p class="mt-2 text-xs text-slate-500">Rp <?= number_format($mikroOutstanding,0,',','.'); ?> dari target Rp <?= number_format($mikroTarget,0,',','.'); ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= $mikroAktif; ?> dari <?= $mikroTotal; ?> unit usaha berstatus aktif</p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d2">
                <span class="inline-flex rounded-xl bg-sky-500/10 px-3 py-1.5 text-xs font-extrabold text-sky-600">👥 Konsumer &mdash; Approval Rate</span>
                <p class="mt-4 text-3xl font-extrabold text-slate-800"><?= $konsumerRate; ?>%</p>
                <div class="pm-progress mt-3" style="height:8px;"><span class="pm-progress-fill" style="width: <?= min($konsumerRate, 100); ?>%;background:#0ea5e9"></span></div>
                <p class="mt-2 text-xs text-slate-500"><?= $konsumerDisetujui; ?> disetujui dari <?= $konsumerTotal; ?> pengajuan</p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d3">
                <span class="inline-flex rounded-xl bg-amber-500/10 px-3 py-1.5 text-xs font-extrabold text-amber-600">🪙 Pawning &mdash; Portofolio Sehat</span>
                <p class="mt-4 text-3xl font-extrabold text-slate-800"><?= $pawningSehat; ?>%</p>
                <div class="pm-progress mt-3" style="height:8px;"><span class="pm-progress-fill" style="width: <?= min($pawningSehat, 100); ?>%;background:#f59e0b"></span></div>
                <p class="mt-2 text-xs text-slate-500"><?= $pawningAktif; ?> aktif &middot; <?= $pawningLelang; ?> lelang dari <?= $pawningTotal; ?> transaksi</p>
            </div>
        </section>

        <?php if ($topAo): ?>
        <section class="pm-card p-5 pm-fade pm-d4">
            <h2 class="font-extrabold text-slate-800 mb-1">Top 5 Account Officer Mikro</h2>
            <p class="text-xs text-slate-500 mb-4">Berdasarkan total outstanding yang dikelola.</p>
            <div class="space-y-3">
                <?php $maxOs = max(1, max(array_column($topAo, 'outstanding'))); ?>
                <?php foreach ($topAo as $ao): ?>
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-semibold text-slate-800"><?= htmlspecialchars($ao['ao']); ?> <span class="text-slate-500">(<?= $ao['total']; ?> unit)</span></span>
                            <span class="text-slate-500">Rp <?= number_format($ao['outstanding'],0,',','.'); ?></span>
                        </div>
                        <div class="pm-progress" style="height:8px;"><span class="pm-progress-fill" style="width: <?= round(($ao['outstanding']/$maxOs)*100,1); ?>%;background:#8b5cf6"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php else: ?>
        <section class="pm-card p-10 text-center text-slate-500 text-sm pm-fade pm-d4">
            Data Account Officer belum tersedia. Jalankan migrasi <code class="text-slate-600">sql/002_mikro_dashboard_kpi.sql</code> untuk mengaktifkan KPI per AO.
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
