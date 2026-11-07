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

$periode = trim($_GET['periode'] ?? '30'); // 30 hari, 90 hari, atau 'all'
if ($periode === '90') {
    $whereWaktu = 'k.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
} elseif ($periode === 'all') {
    $whereWaktu = '1=1';
} else {
    $whereWaktu = 'k.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}

$cekKolomPlafon = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_konsumer LIKE 'plafon_pengajuan'");
$adaKolomPlafon = $cekKolomPlafon && mysqli_num_rows($cekKolomPlafon) > 0;
$kolomNominal   = $adaKolomPlafon ? 'k.plafon_pengajuan' : 'k.pendapatan_bulanan';

$sql = "SELECT u.id AS user_id, u.nama_lengkap AS nama_petugas,
               COUNT(k.id) AS total_input,
               SUM(CASE WHEN k.status = 'disetujui' THEN 1 ELSE 0 END) AS total_disetujui,
               SUM(CASE WHEN k.status = 'ditolak' THEN 1 ELSE 0 END) AS total_ditolak,
               SUM(CASE WHEN k.status = 'pending' THEN 1 ELSE 0 END) AS total_pending,
               SUM(CASE WHEN k.status = 'disetujui' THEN $kolomNominal ELSE 0 END) AS total_nominal_disetujui
        FROM users u
        LEFT JOIN nasabah_konsumer k ON k.user_id = u.id AND $whereWaktu
        WHERE u.role IN ('konsumer', 'admin')
        GROUP BY u.id
        ORDER BY total_nominal_disetujui DESC, total_disetujui DESC";
$res = mysqli_query($conn, $sql);

$performa = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $diputuskan = (int) $row['total_disetujui'] + (int) $row['total_ditolak'];
        $row['tingkat_approval'] = $diputuskan > 0 ? round(((int) $row['total_disetujui'] / $diputuskan) * 100) : 0;
        $performa[] = $row;
    }
}

$totalNominalKeseluruhan = array_sum(array_column($performa, 'total_nominal_disetujui'));
$topPerforma = $performa[0] ?? null;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Performa AO | BSI Konsumer', 'konsumer'); ?>
</head>

<body class="text-slate-700 min-h-screen flex">

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
            'title'    => 'Performa AO',
            'subtitle' => 'Ranking petugas berdasarkan hasil input & persetujuan pengajuan konsumer.',
            'stats'    => [
                ['label' => 'Petugas Aktif', 'value' => number_format(count($performa))],
                ['label' => 'Nominal Disetujui', 'value' => 'Rp' . number_format($totalNominalKeseluruhan, 0, ',', '.')],
            ],
        ]); ?>

        <?php if (!$adaKolomPlafon): ?>
            <div class="pm-card border-sky-200 bg-sky-50 px-5 py-3.5 mb-5 text-xs text-sky-700">
                Kolom plafon pengajuan belum tersedia (jalankan <code class="bg-sky-100 px-1 rounded font-mono">sql/005_konsumer_tahap2_lanjutan.sql</code>), nominal sementara memakai jumlah pendapatan pemohon.
            </div>
        <?php endif; ?>

        <form method="GET" class="pm-card p-4 mb-6 flex flex-wrap items-center gap-2 pm-fade pm-d1">
            <span class="text-xs text-slate-500 mr-1">Periode:</span>
            <?php foreach (['30' => '30 Hari', '90' => '90 Hari', 'all' => 'Semua'] as $val => $label): ?>
                <a href="?periode=<?= $val; ?>" class="text-xs font-semibold py-1.5 px-3 rounded-lg transition <?= $periode === $val ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>"><?= $label; ?></a>
            <?php endforeach; ?>
        </form>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="pm-card pm-card-hover p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-500">Petugas Teratas</p>
                <p class="text-lg font-bold text-slate-900 mt-1"><?= $topPerforma ? htmlspecialchars($topPerforma['nama_petugas']) : '-'; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-500">Total Petugas Aktif</p>
                <p class="text-xl font-bold text-slate-900 mt-1"><?= count($performa); ?></p>
            </div>
            <div class="pm-card pm-card-hover p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-500">Total Nominal Disetujui</p>
                <p class="text-lg font-bold text-emerald-500 mt-1">Rp<?= number_format($totalNominalKeseluruhan, 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d2">
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Petugas</th>
                            <th>Input</th>
                            <th>Disetujui</th>
                            <th>Ditolak</th>
                            <th>Pending</th>
                            <th>Tingkat Approval</th>
                            <th class="!text-right">Nominal Disetujui</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($performa)): ?>
                            <tr><td colspan="8" class="text-center text-xs text-slate-500 italic">Belum ada data petugas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($performa as $i => $p):
                                $warnaApp = $p['tingkat_approval'] >= 80 ? 'text-emerald-500' : ($p['tingkat_approval'] >= 50 ? 'text-amber-500' : 'text-rose-500');
                            ?>
                                <tr>
                                    <td class="text-slate-400 font-semibold"><?= $i + 1; ?></td>
                                    <td class="font-semibold text-slate-900"><?= htmlspecialchars($p['nama_petugas']); ?></td>
                                    <td class="text-slate-600"><?= (int) $p['total_input']; ?></td>
                                    <td class="text-emerald-500 font-semibold"><?= (int) $p['total_disetujui']; ?></td>
                                    <td class="text-rose-500 font-semibold"><?= (int) $p['total_ditolak']; ?></td>
                                    <td class="text-amber-500 font-semibold"><?= (int) $p['total_pending']; ?></td>
                                    <td class="font-bold <?= $warnaApp; ?>"><?= $p['tingkat_approval']; ?>%</td>
                                    <td class="!text-right text-slate-700">Rp<?= number_format((float) $p['total_nominal_disetujui'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    </script>
</body>
</html>