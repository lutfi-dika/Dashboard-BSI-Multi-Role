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
$current_page = 'atasan-monitoring-mikro.php';

if (!in_array($role_user, ['admin', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$kolomTambahan = ['cabang', 'nama_ao', 'status_pengajuan', 'outstanding', 'target_outstanding', 'kolektibilitas'];
$skemaLengkap  = true;
$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro");
$kolomTersedia = [];
if ($cekKolom) {
    while ($k = mysqli_fetch_assoc($cekKolom)) { $kolomTersedia[] = $k['Field']; }
}
foreach ($kolomTambahan as $kolom) {
    if (!in_array($kolom, $kolomTersedia, true)) { $skemaLengkap = false; break; }
}

$cari = trim($_GET['q'] ?? '');

if ($skemaLengkap) {
    $q = mysqli_query($conn, "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(omzet_bulanan), 0) AS total_omzet,
            COALESCE(SUM(outstanding), 0) AS total_outstanding,
            COALESCE(SUM(target_outstanding), 0) AS total_target,
            SUM(CASE WHEN status_pengajuan = 'Aktif' THEN 1 ELSE 0 END) AS total_aktif,
            SUM(CASE WHEN status_pengajuan = 'Approval' THEN 1 ELSE 0 END) AS total_approval,
            SUM(CASE WHEN kolektibilitas >= 3 THEN 1 ELSE 0 END) AS total_npf
        FROM nasabah_mikro
    ");
    $stat = $q ? mysqli_fetch_assoc($q) : [];
} else {
    $q = mysqli_query($conn, "SELECT COUNT(*) AS total, COALESCE(SUM(omzet_bulanan),0) AS total_omzet FROM nasabah_mikro");
    $stat = $q ? mysqli_fetch_assoc($q) : [];
}

$where = '';
$params = [];
$types = '';
if ($cari !== '') {
    $where = " WHERE nama_pemilik LIKE ? OR nama_usaha LIKE ?" . ($skemaLengkap ? " OR cabang LIKE ? OR nama_ao LIKE ?" : '');
    $like = "%$cari%";
    $params = $skemaLengkap ? [$like, $like, $like, $like] : [$like, $like];
    $types = str_repeat('s', count($params));
}

$kolomSelect = $skemaLengkap
    ? "nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, outstanding, target_outstanding, kolektibilitas, cabang, nama_ao, status_pengajuan, created_at"
    : "nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, created_at";
$sql = "SELECT $kolomSelect FROM nasabah_mikro" . $where . " ORDER BY created_at DESC LIMIT 100";
$stmt = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Monitoring Mikro | BSI Executive', 'atasan'); ?>
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
            'title'    => 'Segmen Mikro',
            'subtitle' => 'Pantauan read-only seluruh unit usaha mikro yang terdata.',
            'stats'    => [
                ['label' => 'Total Unit',    'value' => number_format($stat['total'] ?? 0)],
                ['label' => 'Omzet Terdata', 'value' => 'Rp ' . number_format($stat['total_omzet'] ?? 0, 0, ',', '.')],
            ],
        ]); ?>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            <div class="pm-card p-5 pm-fade pm-d1"><p class="text-[11px] font-bold uppercase text-slate-500">Total Unit Usaha</p><p class="mt-2 text-2xl font-extrabold text-slate-800"><?= number_format($stat['total'] ?? 0); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d2"><p class="text-[11px] font-bold uppercase text-slate-500">Omzet Terdata</p><p class="mt-2 text-xl font-extrabold text-slate-800">Rp <?= number_format($stat['total_omzet'] ?? 0, 0, ',', '.'); ?></p></div>
            <?php if ($skemaLengkap): ?>
            <div class="pm-card p-5 pm-fade pm-d3"><p class="text-[11px] font-bold uppercase text-slate-500">Outstanding</p><p class="mt-2 text-xl font-extrabold text-emerald-600">Rp <?= number_format($stat['total_outstanding'] ?? 0, 0, ',', '.'); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d4"><p class="text-[11px] font-bold uppercase text-slate-500">Indikasi NPF</p><p class="mt-2 text-2xl font-extrabold text-rose-600"><?= number_format($stat['total_npf'] ?? 0); ?> unit</p></div>
            <?php endif; ?>
        </section>

        <form method="get" class="mb-4 flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($cari); ?>" placeholder="Cari nama pemilik, usaha, cabang, atau AO..." class="flex-1 rounded-xl bg-slate-50 border border-slate-200 px-4 py-2.5 text-sm text-slate-800 placeholder-slate-500 focus:outline-none focus:border-emerald-400/50">
            <button class="pm-btn pm-btn-primary">Cari</button>
        </form>

        <div class="pm-card overflow-hidden pm-fade pm-d5">
            <div class="pm-table-wrap pm-scroll">
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>Nama Pemilik</th>
                        <th>Usaha</th>
                        <th>Omzet</th>
                        <?php if ($skemaLengkap): ?>
                        <th>Outstanding</th>
                        <th>Cabang</th>
                        <th>AO</th>
                        <th>Status</th>
                        <th>Kol.</th>
                        <?php endif; ?>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($r = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="font-semibold text-slate-800"><?= htmlspecialchars($r['nama_pemilik']); ?></td>
                                <td><?= htmlspecialchars($r['nama_usaha']); ?><br><span class="text-[11px] text-slate-500"><?= htmlspecialchars($r['jenis_sektor'] ?? ''); ?></span></td>
                                <td>Rp <?= number_format($r['omzet_bulanan'], 0, ',', '.'); ?></td>
                                <?php if ($skemaLengkap): ?>
                                <td>Rp <?= number_format($r['outstanding'], 0, ',', '.'); ?></td>
                                <td><?= htmlspecialchars($r['cabang']); ?></td>
                                <td><?= htmlspecialchars($r['nama_ao']); ?></td>
                                <td>
                                    <?php $st = $r['status_pengajuan']; $stCls = $st === 'Aktif' ? 'bg-emerald-500/10 text-emerald-600' : ($st === 'Ditolak' ? 'bg-rose-500/10 text-rose-600' : 'bg-amber-500/10 text-amber-600'); ?>
                                    <span class="pm-badge <?= $stCls; ?>"><?= htmlspecialchars($st); ?></span>
                                </td>
                                <td><span class="pm-badge <?= $r['kolektibilitas'] >= 3 ? 'bg-rose-500/10 text-rose-600' : 'bg-emerald-500/10 text-emerald-600'; ?>">Kol <?= (int) $r['kolektibilitas']; ?></span></td>
                                <?php endif; ?>
                                <td class="text-slate-500 text-xs"><?= date('d M Y', strtotime($r['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= $skemaLengkap ? 9 : 4 ?>" class="!py-10 text-center text-slate-500">Tidak ada data ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Menampilkan maksimal 100 data terbaru. Akses atasan bersifat pemantauan (read-only).</p>
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
