<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../auth/login.php"); exit(); }
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) { header("Location: ../../pilih-karyawan.php"); exit(); }

$role_user = strtolower($_SESSION['role'] ?? '');
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Atasan');
$current_page = 'atasan-npf.php';
if (!in_array($role_user, ['admin', 'atasan'], true)) { header('Location: ../../index.php'); exit(); }

$kolomTambahan = ['kolektibilitas', 'outstanding', 'cabang', 'status_pengajuan'];
$skemaLengkap = true;
$cek = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro");
$kolomTersedia = [];
if ($cek) { while ($k = mysqli_fetch_assoc($cek)) { $kolomTersedia[] = $k['Field']; } }
foreach ($kolomTambahan as $kolom) { if (!in_array($kolom, $kolomTersedia, true)) { $skemaLengkap = false; break; } }

$totalOutstanding = 0; $npfOutstanding = 0; $npfCount = 0; $totalCount = 0;
$distribusiKol = [1=>0,2=>0,3=>0,4=>0,5=>0];
$cabangNpf = [];
$daftarNpf = [];

if ($skemaLengkap) {
    $q = mysqli_query($conn, "SELECT COUNT(*) total, COALESCE(SUM(outstanding),0) total_os, SUM(CASE WHEN kolektibilitas>=3 THEN 1 ELSE 0 END) npf_count, COALESCE(SUM(CASE WHEN kolektibilitas>=3 THEN outstanding ELSE 0 END),0) npf_os FROM nasabah_mikro");
    if ($q && ($r = mysqli_fetch_assoc($q))) {
        $totalCount = (int)$r['total']; $totalOutstanding = (float)$r['total_os'];
        $npfCount = (int)$r['npf_count']; $npfOutstanding = (float)$r['npf_os'];
    }
    $qDist = mysqli_query($conn, "SELECT kolektibilitas, COUNT(*) total FROM nasabah_mikro GROUP BY kolektibilitas");
    if ($qDist) { while ($row = mysqli_fetch_assoc($qDist)) { $k=(int)$row['kolektibilitas']; if(isset($distribusiKol[$k])) $distribusiKol[$k]=(int)$row['total']; } }

    $qCabang = mysqli_query($conn, "SELECT COALESCE(NULLIF(cabang,''),'Belum Diisi') cabang, COALESCE(SUM(outstanding),0) total_os, COALESCE(SUM(CASE WHEN kolektibilitas>=3 THEN outstanding ELSE 0 END),0) npf_os FROM nasabah_mikro GROUP BY cabang ORDER BY npf_os DESC LIMIT 6");
    if ($qCabang) { while ($row = mysqli_fetch_assoc($qCabang)) { $cabangNpf[] = $row; } }

    $qList = mysqli_query($conn, "SELECT nama_pemilik, nama_usaha, cabang, nama_ao, outstanding, kolektibilitas FROM nasabah_mikro WHERE kolektibilitas >= 3 ORDER BY outstanding DESC LIMIT 25");
    if ($qList) { while ($row = mysqli_fetch_assoc($qList)) { $daftarNpf[] = $row; } }
}
$npfPersen = $totalOutstanding > 0 ? round(($npfOutstanding / $totalOutstanding) * 100, 2) : 0;
$labelKol = [1=>'Lancar',2=>'DPK',3=>'Kurang Lancar',4=>'Diragukan',5=>'Macet'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('NPF Executive | BSI Executive', 'atasan'); ?>
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
            'title'    => 'Non Performing Financing (NPF)',
            'subtitle' => 'Kualitas pembiayaan segmen Mikro berdasarkan kolektibilitas (Kol ≥ 3).',
            'stats'    => [
                ['label' => 'Rasio NPF',         'value' => $npfPersen . '%'],
                ['label' => 'Outstanding NPF',   'value' => 'Rp ' . number_format($npfOutstanding, 0, ',', '.')],
                ['label' => 'Total Outstanding', 'value' => 'Rp ' . number_format($totalOutstanding, 0, ',', '.')],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <section class="pm-card p-10 text-center text-slate-500 text-sm pm-fade pm-d1">
                Data kolektibilitas belum tersedia. Jalankan migrasi <code class="text-slate-600">sql/002_mikro_dashboard_kpi.sql</code> untuk mengaktifkan laporan NPF.
            </section>
        <?php else: ?>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            <div class="pm-card p-5 pm-fade pm-d1"><p class="text-[11px] font-bold uppercase text-slate-500">Rasio NPF</p><p class="mt-2 text-3xl font-extrabold <?= $npfPersen>=5?'text-rose-600':'text-emerald-600'; ?>"><?= $npfPersen; ?>%</p><p class="mt-1 text-xs text-slate-500">Threshold aman &lt; 5%</p></div>
            <div class="pm-card p-5 pm-fade pm-d2"><p class="text-[11px] font-bold uppercase text-slate-500">Outstanding NPF</p><p class="mt-2 text-xl font-extrabold text-rose-600">Rp <?= number_format($npfOutstanding,0,',','.'); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d3"><p class="text-[11px] font-bold uppercase text-slate-500">Total Outstanding</p><p class="mt-2 text-xl font-extrabold text-slate-800">Rp <?= number_format($totalOutstanding,0,',','.'); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d4"><p class="text-[11px] font-bold uppercase text-slate-500">Unit Bermasalah</p><p class="mt-2 text-3xl font-extrabold text-slate-800"><?= $npfCount; ?> <span class="text-sm text-slate-500 font-medium">/ <?= $totalCount; ?></span></p></div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2 mb-6">
            <div class="pm-card p-5 pm-fade pm-d5">
                <h2 class="font-extrabold text-slate-800 mb-4">Distribusi Kolektibilitas</h2>
                <div class="space-y-3">
                    <?php $maxDist = max(1, max($distribusiKol)); ?>
                    <?php foreach ($distribusiKol as $kol => $jml): ?>
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-semibold text-slate-800">Kol <?= $kol; ?> &mdash; <?= $labelKol[$kol]; ?></span>
                                <span class="text-slate-500"><?= $jml; ?> unit</span>
                            </div>
                            <div class="pm-progress" style="height:8px;"><span class="pm-progress-fill" style="width: <?= round(($jml/$maxDist)*100,1); ?>%;background:<?= $kol>=3?'#f43f5e':'#10b981'; ?>"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="pm-card p-5 pm-fade pm-d6">
                <h2 class="font-extrabold text-slate-800 mb-4">NPF per Cabang</h2>
                <div class="space-y-3">
                    <?php $maxCab = $cabangNpf ? max(1, max(array_column($cabangNpf,'npf_os'))) : 1; ?>
                    <?php foreach ($cabangNpf as $c): ?>
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($c['cabang']); ?></span>
                                <span class="text-slate-500">Rp <?= number_format($c['npf_os'],0,',','.'); ?></span>
                            </div>
                            <div class="pm-progress" style="height:8px;"><span class="pm-progress-fill" style="width: <?= round(($c['npf_os']/$maxCab)*100,1); ?>%;background:#f43f5e"></span></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$cabangNpf): ?><p class="text-sm text-slate-500">Belum ada data.</p><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="pm-card overflow-hidden pm-fade pm-d7">
            <div class="px-5 py-4 border-b border-slate-100"><h2 class="font-extrabold text-slate-800">Daftar Unit Bermasalah (Kol ≥ 3)</h2></div>
            <div class="pm-table-wrap pm-scroll">
            <table class="pm-table">
                <thead>
                    <tr><th>Pemilik</th><th>Usaha</th><th>Cabang</th><th>AO</th><th>Outstanding</th><th>Kol.</th></tr>
                </thead>
                <tbody>
                    <?php if ($daftarNpf): foreach ($daftarNpf as $r): ?>
                        <tr>
                            <td class="font-semibold text-slate-800"><?= htmlspecialchars($r['nama_pemilik']); ?></td>
                            <td><?= htmlspecialchars($r['nama_usaha']); ?></td>
                            <td><?= htmlspecialchars($r['cabang']); ?></td>
                            <td><?= htmlspecialchars($r['nama_ao']); ?></td>
                            <td>Rp <?= number_format($r['outstanding'],0,',','.'); ?></td>
                            <td><span class="pm-badge bg-rose-500/10 text-rose-600">Kol <?= (int)$r['kolektibilitas']; ?></span></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="!py-10 text-center text-slate-500">Tidak ada unit bermasalah saat ini. 🎉</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
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
