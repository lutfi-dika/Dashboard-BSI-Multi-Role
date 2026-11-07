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
$current_page = 'debitur';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'status_pengajuan'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

$cari = trim($_GET['cari'] ?? '');
$daftar = [];
$total_outstanding = 0;

if ($skemaLengkap) {
    $sql = "SELECT m.*, u.nama_lengkap AS nama_petugas
            FROM nasabah_mikro m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.status_pengajuan = 'Aktif'";
    if ($cari !== '') {
        $sql .= " AND (m.nama_pemilik LIKE ? OR m.nama_usaha LIKE ?)";
    }
    $sql .= " ORDER BY m.outstanding DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($cari !== '') {
        $like = "%{$cari}%";
        mysqli_stmt_bind_param($stmt, "ss", $like, $like);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $daftar[] = $row;
        $total_outstanding += (float) $row['outstanding'];
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Data Debitur Mikro | BSI Mikro', 'mikro'); ?>
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
            'title'    => 'Data Debitur Mikro',
            'subtitle' => 'Debitur aktif (pengajuan disetujui) beserta posisi outstanding.',
            'stats'    => [
                ['label' => 'Debitur Aktif', 'value' => count($daftar)],
                ['label' => 'Total Outstanding', 'value' => 'Rp ' . number_format($total_outstanding, 0, ',', '.')],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/002_mikro_dashboard_kpi.sql</code> agar halaman ini bisa membaca status debitur.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <?php premium_kpi_card(['tone' => 'emerald', 'delay' => 1, 'label' => 'Total Debitur Aktif', 'value' => count($daftar)]); ?>
                <?php premium_kpi_card(['tone' => 'sky', 'delay' => 2, 'label' => 'Total Outstanding', 'value' => 'Rp ' . number_format($total_outstanding, 0, ',', '.'), 'value_class' => '!text-emerald-600']); ?>
            </div>

            <div class="pm-card overflow-hidden pm-fade pm-d3">
                <div class="px-4 sm:px-6 pt-6 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Daftar Debitur</h4>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="text" name="cari" value="<?= htmlspecialchars($cari); ?>" placeholder="Cari nama / usaha..." class="text-xs px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 w-48">
                        <button type="submit" class="pm-btn pm-btn-primary">Cari</button>
                    </form>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[820px]">
                        <thead>
                            <tr>
                                <th>Pemilik</th>
                                <th>Usaha</th>
                                <th>Plafon</th>
                                <th>Outstanding</th>
                                <th>Kolektibilitas</th>
                                <th>AO</th>
                                <th>Petugas Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($daftar) > 0): ?>
                                <?php foreach ($daftar as $d):
                                    $kol = (int) ($d['kolektibilitas'] ?? 1);
                                    $kolLabel = ['1' => 'Lancar', '2' => 'DPK', '3' => 'Kurang Lancar', '4' => 'Diragukan', '5' => 'Macet'][$kol] ?? 'Lancar';
                                    $kolColor = $kol === 1 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : ($kol === 2 ? 'bg-amber-50 text-amber-600 border-amber-100' : 'bg-rose-50 text-rose-600 border-rose-100');
                                ?>
                                    <tr>
                                        <td class="font-semibold !text-slate-800"><?= htmlspecialchars($d['nama_pemilik']); ?></td>
                                        <td><?= htmlspecialchars($d['nama_usaha']); ?></td>
                                        <td>Rp <?= number_format($d['plafon_pembiayaan'] ?? 0, 0, ',', '.'); ?></td>
                                        <td class="font-semibold !text-indigo-600">Rp <?= number_format($d['outstanding'] ?? 0, 0, ',', '.'); ?></td>
                                        <td>
                                            <span class="pm-badge <?= $kolColor; ?>">Kol-<?= $kol; ?> · <?= $kolLabel; ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($d['nama_ao'] ?? '—'); ?></td>
                                        <td class="text-xs"><?= htmlspecialchars($d['nama_petugas'] ?? 'Sistem'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="!text-center italic">Belum ada debitur aktif yang cocok.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
