<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../auth/login.php"); exit(); }
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) { header("Location: ../../pilih-karyawan.php"); exit(); }

$role_user = strtolower($_SESSION['role'] ?? '');
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Atasan');
$current_page = 'atasan-laporan.php';
if (!in_array($role_user, ['admin', 'atasan'], true)) { header('Location: ../../index.php'); exit(); }

$periode_awal = $_GET['dari'] ?? date('Y-m-01');
$periode_akhir = $_GET['sampai'] ?? date('Y-m-d');

function periodeTotal(mysqli $conn, string $table, string $amountColumn, string $awal, string $akhir): array
{
    $sql = "SELECT COUNT(*) total, COALESCE(SUM(`$amountColumn`),0) nominal FROM `$table` WHERE DATE(created_at) BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $awal, $akhir);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return $res ? mysqli_fetch_assoc($res) : ['total'=>0,'nominal'=>0];
}

$mikro = periodeTotal($conn, 'nasabah_mikro', 'omzet_bulanan', $periode_awal, $periode_akhir);
$konsumer = periodeTotal($conn, 'nasabah_konsumer', 'pendapatan_bulanan', $periode_awal, $periode_akhir);
$gadai = periodeTotal($conn, 'pawning', 'nilai_pinjaman', $periode_awal, $periode_akhir);
$totalData = (int)$mikro['total'] + (int)$konsumer['total'] + (int)$gadai['total'];
$totalNilai = (float)$mikro['nominal'] + (float)$konsumer['nominal'] + (float)$gadai['nominal'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Laporan Executive | BSI Executive', 'atasan'); ?>
    <style>
        @media print {
            aside, header button, .no-print { display: none !important; }
            body { background: #fff !important; color: #000 !important; }
            .pm-card { background: #fff !important; border: 1px solid #ddd !important; color: #000 !important; }
        }
    </style>
</head>
<body class="text-slate-800 min-h-screen flex flex-col md:flex-row">
    <?php include __DIR__ . '/../../components/sidebar.php'; ?>
    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
        <!-- TOPBAR: hamburger mobile + status koneksi + profil -->
        <div class="flex items-center justify-between gap-3 mb-5 no-print">
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

        <div class="no-print">
        <?php premium_hero([
            'accent'   => 'atasan',
            'eyebrow'  => 'BSI Executive',
            'title'    => 'Laporan Konsolidasi',
            'subtitle' => 'Ringkasan seluruh segmen dalam periode yang dipilih.',
            'stats'    => [
                ['label' => 'Total Data Baru', 'value' => number_format($totalData)],
                ['label' => 'Nilai Total',     'value' => 'Rp ' . number_format($totalNilai, 0, ',', '.')],
            ],
        ]); ?>
        </div>

        <form method="get" class="pm-card p-4 mb-6 flex flex-wrap items-end gap-3 no-print pm-fade pm-d1">
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Dari Tanggal</label>
                <input type="date" name="dari" value="<?= htmlspecialchars($periode_awal); ?>" class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-violet-400/50">
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Sampai Tanggal</label>
                <input type="date" name="sampai" value="<?= htmlspecialchars($periode_akhir); ?>" class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:outline-none focus:border-violet-400/50">
            </div>
            <button class="pm-btn pm-btn-primary">Terapkan</button>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 no-print">
            <p class="text-xs text-slate-500">Periode laporan: <span class="font-bold text-slate-800"><?= date('d M Y', strtotime($periode_awal)); ?></span> &mdash; <span class="font-bold text-slate-800"><?= date('d M Y', strtotime($periode_akhir)); ?></span></p>
            <button onclick="window.print()" class="pm-btn pm-btn-primary">🖨️ Cetak / Simpan PDF</button>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            <div class="pm-card p-5 pm-fade pm-d2"><p class="text-[11px] font-bold uppercase text-slate-500">Total Data Baru</p><p class="mt-2 text-2xl font-extrabold text-slate-800"><?= number_format($totalData); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d3"><p class="text-[11px] font-bold uppercase text-slate-500">Nilai Total</p><p class="mt-2 text-xl font-extrabold text-slate-800">Rp <?= number_format($totalNilai,0,',','.'); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d4"><p class="text-[11px] font-bold uppercase text-slate-500">Mikro</p><p class="mt-2 text-2xl font-extrabold text-emerald-600"><?= number_format($mikro['total']); ?></p></div>
            <div class="pm-card p-5 pm-fade pm-d5"><p class="text-[11px] font-bold uppercase text-slate-500">Konsumer / Gadai</p><p class="mt-2 text-2xl font-extrabold text-sky-600"><?= number_format($konsumer['total']); ?> <span class="text-amber-600">/ <?= number_format($gadai['total']); ?></span></p></div>
        </section>

        <section class="pm-card overflow-hidden pm-fade pm-d6">
            <div class="pm-table-wrap pm-scroll">
            <table class="pm-table">
                <thead>
                    <tr><th>Segmen</th><th>Jumlah Data Baru</th><th>Nilai Terkumpul</th></tr>
                </thead>
                <tbody>
                    <tr><td class="font-semibold text-slate-800">🏪 Mikro</td><td><?= number_format($mikro['total']); ?></td><td>Rp <?= number_format($mikro['nominal'],0,',','.'); ?></td></tr>
                    <tr><td class="font-semibold text-slate-800">👥 Konsumer</td><td><?= number_format($konsumer['total']); ?></td><td>Rp <?= number_format($konsumer['nominal'],0,',','.'); ?></td></tr>
                    <tr><td class="font-semibold text-slate-800">🪙 Pawning</td><td><?= number_format($gadai['total']); ?></td><td>Rp <?= number_format($gadai['nominal'],0,',','.'); ?></td></tr>
                </tbody>
            </table>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-3 no-print">
            <a href="atasan-monitoring-mikro.php" class="pm-card pm-card-hover p-4 text-center text-sm font-bold text-slate-800 pm-fade pm-d6">Detail Data Mikro →</a>
            <a href="atasan-monitoring-konsumer.php" class="pm-card pm-card-hover p-4 text-center text-sm font-bold text-slate-800 pm-fade pm-d7">Detail Data Konsumer →</a>
            <a href="atasan-monitoring-pawning.php" class="pm-card pm-card-hover p-4 text-center text-sm font-bold text-slate-800 pm-fade pm-d8">Detail Data Pawning →</a>
        </section>
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
