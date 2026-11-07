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

// 1. Pengajuan yang masih "pending" lebih dari 2 hari -> butuh perhatian.
$pendingLama = [];
$sqlPending = "SELECT id, nama_lengkap, produk_diajukan, created_at, DATEDIFF(NOW(), created_at) AS umur_hari
               FROM nasabah_konsumer
               WHERE status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL 2 DAY)
               ORDER BY created_at ASC
               LIMIT 20";
$resPending = mysqli_query($conn, $sqlPending);
if ($resPending) {
    while ($row = mysqli_fetch_assoc($resPending)) {
        $pendingLama[] = $row;
    }
}

// 2. Pengajuan yang baru saja diproses (disetujui/ditolak) dalam 7 hari terakhir.
$diprosesBaru = [];
$sqlProses = "SELECT k.id, k.nama_lengkap, k.produk_diajukan, k.status, k.updated_at, u.nama_lengkap AS nama_petugas
              FROM nasabah_konsumer k
              LEFT JOIN users u ON k.diproses_oleh = u.id
              WHERE k.status IN ('disetujui', 'ditolak')
                AND k.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              ORDER BY k.updated_at DESC
              LIMIT 20";
$resProses = mysqli_query($conn, $sqlProses);
if ($resProses) {
    while ($row = mysqli_fetch_assoc($resProses)) {
        $diprosesBaru[] = $row;
    }
}

// 3. Ringkasan jumlah pending saat ini (untuk badge)
$totalPending = 0;
$resTotalPending = mysqli_query($conn, "SELECT COUNT(*) AS total FROM nasabah_konsumer WHERE status = 'pending'");
if ($resTotalPending) {
    $totalPending = (int) (mysqli_fetch_assoc($resTotalPending)['total'] ?? 0);
}

function waktu_relatif(string $datetime): string
{
    $selisih = time() - strtotime($datetime);
    if ($selisih < 60) return 'Baru saja';
    if ($selisih < 3600) return floor($selisih / 60) . ' menit lalu';
    if ($selisih < 86400) return floor($selisih / 3600) . ' jam lalu';
    return floor($selisih / 86400) . ' hari lalu';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Notifikasi | BSI Konsumer', 'konsumer'); ?>
</head>

<body class="text-slate-800 min-h-screen flex">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
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
            'title'    => 'Notifikasi',
            'subtitle' => 'Pusat notifikasi terkait pengajuan, approval, dan pengajuan yang butuh perhatian.',
            'stats'    => [
                ['label' => 'Total Pending',     'value' => number_format($totalPending)],
                ['label' => 'Butuh Perhatian',   'value' => number_format(count($pendingLama))],
                ['label' => 'Update 7 Hari',     'value' => number_format(count($diprosesBaru))],
            ],
        ]); ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="pm-card p-6 pm-fade pm-d1">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-600">
                            <path d="M12 9v4" />
                            <path d="M12 17h.01" />
                            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L14.71 3.86a2 2 0 0 0-3.42 0Z" />
                        </svg>
                        Butuh Perhatian
                    </h4>
                    <span class="pm-badge bg-amber-100 text-amber-700"><?= count($pendingLama); ?> pengajuan</span>
                </div>
                <p class="text-xs text-slate-600 mb-4">Pengajuan berstatus <em>pending</em> lebih dari 2 hari (total pending saat ini: <?= $totalPending; ?>).</p>

                <?php if (empty($pendingLama)): ?>
                    <div class="text-center py-10 text-xs text-slate-500">Tidak ada pengajuan yang tertunda lama. Semua terkendali 👍</div>
                <?php else: ?>
                    <div class="space-y-2 max-h-[420px] overflow-y-auto pr-1">
                        <?php foreach ($pendingLama as $item): ?>
                            <a href="konsumer-data.php?cari=<?= urlencode($item['nama_lengkap']); ?>" class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 transition">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-900 truncate"><?= htmlspecialchars($item['nama_lengkap']); ?></p>
                                    <p class="text-[11px] text-slate-600 truncate"><?= htmlspecialchars($item['produk_diajukan'] ?: '-'); ?></p>
                                </div>
                                <span class="pm-badge bg-amber-100 text-amber-700"><?= (int) $item['umur_hari']; ?> hari</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pm-card p-6 pm-fade pm-d2">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600">
                            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                        </svg>
                        Aktivitas Terbaru
                    </h4>
                    <span class="pm-badge bg-blue-100 text-blue-700"><?= count($diprosesBaru); ?> update</span>
                </div>
                <p class="text-xs text-slate-600 mb-4">Pengajuan yang baru disetujui/ditolak dalam 7 hari terakhir.</p>

                <?php if (empty($diprosesBaru)): ?>
                    <div class="text-center py-10 text-xs text-slate-500">Belum ada aktivitas approval terbaru.</div>
                <?php else: ?>
                    <div class="space-y-2 max-h-[420px] overflow-y-auto pr-1">
                        <?php foreach ($diprosesBaru as $item): ?>
                            <?php $disetujui = $item['status'] === 'disetujui'; ?>
                            <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-900 truncate">
                                        <?= htmlspecialchars($item['nama_lengkap']); ?>
                                        <span class="<?= $disetujui ? 'text-green-600' : 'text-red-600'; ?> font-bold"><?= $disetujui ? 'disetujui' : 'ditolak'; ?></span>
                                    </p>
                                    <p class="text-[11px] text-slate-600 truncate">oleh <?= htmlspecialchars($item['nama_petugas'] ?? 'Sistem'); ?> &middot; <?= waktu_relatif($item['updated_at']); ?></p>
                                </div>
                                <span class="shrink-0 w-2.5 h-2.5 rounded-full <?= $disetujui ? 'bg-green-600' : 'bg-red-600'; ?>"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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