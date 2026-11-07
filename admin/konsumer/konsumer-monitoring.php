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

// Catatan: status pengajuan Konsumer saat ini masih 3 tahap sederhana
// (pending / disetujui / ditolak). Pipeline multi-tahap yang lebih detail
// (prospek -> verifikasi -> ... ) direncanakan pada iterasi berikutnya
// (lihat ROADMAP.md), jadi monitoring di sini difokuskan pada kecepatan
// proses & antrean yang butuh perhatian dari data yang sudah ada.

$ringkasan = ['pending' => 0, 'disetujui' => 0, 'ditolak' => 0];
$resRingkasan = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM nasabah_konsumer GROUP BY status");
if ($resRingkasan) {
    while ($row = mysqli_fetch_assoc($resRingkasan)) {
        $s = $row['status'] ?: 'pending';
        if (isset($ringkasan[$s])) $ringkasan[$s] = (int) $row['total'];
    }
}

// Antrean pending, diurutkan dari yang paling lama menunggu
$antrean = [];
$resAntrean = mysqli_query($conn, "SELECT id, nama_lengkap, produk_diajukan, created_at, DATEDIFF(NOW(), created_at) AS umur_hari
                                    FROM nasabah_konsumer WHERE status = 'pending'
                                    ORDER BY created_at ASC LIMIT 50");
if ($resAntrean) {
    while ($row = mysqli_fetch_assoc($resAntrean)) {
        $antrean[] = $row;
    }
}

$rataTunggu = 0;
if (!empty($antrean)) {
    $rataTunggu = round(array_sum(array_column($antrean, 'umur_hari')) / count($antrean), 1);
}
$butuhPerhatian = count(array_filter($antrean, fn($a) => (int) $a['umur_hari'] >= 3));

// Breakdown per petugas input (proxy AO) untuk pengajuan yang masih pending
$perPetugas = [];
$resPetugas = mysqli_query($conn, "SELECT u.nama_lengkap AS nama_petugas, COUNT(*) AS total
                                    FROM nasabah_konsumer k
                                    LEFT JOIN users u ON k.user_id = u.id
                                    WHERE k.status = 'pending'
                                    GROUP BY k.user_id
                                    ORDER BY total DESC LIMIT 8");
if ($resPetugas) {
    while ($row = mysqli_fetch_assoc($resPetugas)) {
        $perPetugas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Monitoring Pembiayaan | BSI Konsumer', 'konsumer'); ?>
</head>

<body class="text-slate-700 min-h-screen flex">

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
            'title'    => 'Monitoring Pembiayaan',
            'subtitle' => 'Pantau antrean pengajuan dan kecepatan proses persetujuan.',
            'stats'    => [
                ['label' => 'Antrean Pending', 'value' => number_format($ringkasan['pending'])],
                ['label' => 'Butuh Perhatian', 'value' => number_format($butuhPerhatian)],
                ['label' => 'Rata Tunggu',     'value' => $rataTunggu . ' hari'],
            ],
        ]); ?>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-500">Antrean Pending</p>
                <p class="text-xl sm:text-2xl font-bold text-amber-600 mt-1"><?= $ringkasan['pending']; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-500">Butuh Perhatian (&ge;3 hari)</p>
                <p class="text-xl sm:text-2xl font-bold text-rose-600 mt-1"><?= $butuhPerhatian; ?></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-500">Rata-rata Waktu Tunggu</p>
                <p class="text-xl sm:text-2xl font-bold text-sky-600 mt-1"><?= $rataTunggu; ?> <span class="text-xs font-medium text-slate-500">hari</span></p>
            </div>
            <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d4">
                <p class="text-[11px] text-slate-500">Total Diproses</p>
                <p class="text-xl sm:text-2xl font-bold text-slate-800 mt-1"><?= $ringkasan['disetujui'] + $ringkasan['ditolak']; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="pm-card overflow-hidden lg:col-span-2 pm-fade pm-d1">
                <div class="px-4 sm:px-6 pt-5 pb-3 flex items-center justify-between">
                    <h4 class="text-sm font-bold text-slate-800">Antrean Menunggu Approval</h4>
                    <span class="pm-badge bg-slate-100 text-slate-600"><?= count($antrean); ?> pengajuan</span>
                </div>
                <div class="pm-table-wrap pm-scroll max-h-[520px] overflow-y-auto">
                    <table class="pm-table min-w-[560px]">
                        <thead>
                            <tr>
                                <th>Nasabah</th>
                                <th>Produk</th>
                                <th>Diajukan</th>
                                <th class="!text-right">Lama Tunggu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($antrean)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-xs text-slate-500 italic">Tidak ada antrean pending saat ini. 👍</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($antrean as $a):
                                    $hari = (int) $a['umur_hari'];
                                    $warna = $hari >= 3 ? 'bg-rose-50 text-rose-700' : ($hari >= 1 ? 'bg-amber-50 text-amber-700' : 'bg-sky-50 text-sky-700');
                                ?>
                                    <tr>
                                        <td class="font-semibold text-slate-800"><?= htmlspecialchars($a['nama_lengkap']); ?></td>
                                        <td class="text-slate-600"><?= htmlspecialchars($a['produk_diajukan'] ?: '-'); ?></td>
                                        <td class="text-slate-600"><?= htmlspecialchars(date('d-m-Y', strtotime($a['created_at']))); ?></td>
                                        <td class="!text-right"><span class="pm-badge <?= $warna; ?>"><?= $hari; ?> hari</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="pm-card p-6 pm-fade pm-d2">
                    <h4 class="text-sm font-bold text-slate-800 mb-1">Funnel Status</h4>
                    <p class="text-xs text-slate-500 mb-4">Distribusi seluruh pengajuan konsumer.</p>
                    <?php
                    $totalFunnel = array_sum($ringkasan);
                    $funnelItems = [
                        ['Pending', $ringkasan['pending'], '#f59e0b'],
                        ['Disetujui', $ringkasan['disetujui'], '#10b981'],
                        ['Ditolak', $ringkasan['ditolak'], '#f43f5e'],
                    ];
                    foreach ($funnelItems as [$label, $jumlah, $warna]):
                        $persen = $totalFunnel > 0 ? round(($jumlah / $totalFunnel) * 100) : 0;
                    ?>
                        <div class="mb-3">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-slate-600"><?= $label; ?></span>
                                <span class="font-semibold text-slate-800"><?= $jumlah; ?> (<?= $persen; ?>%)</span>
                            </div>
                            <div class="pm-progress" style="height:6px;">
                                <span class="pm-progress-fill" style="width: <?= $persen; ?>%;background:<?= $warna; ?>"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pm-card p-6 pm-fade pm-d3">
                    <h4 class="text-sm font-bold text-slate-800 mb-1">Beban Antrean per Petugas</h4>
                    <p class="text-xs text-slate-500 mb-4">Jumlah pengajuan pending yang diinput tiap petugas.</p>
                    <?php if (empty($perPetugas)): ?>
                        <div class="text-center py-6 text-xs text-slate-500">Tidak ada antrean.</div>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($perPetugas as $p): ?>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-600 truncate"><?= htmlspecialchars($p['nama_petugas'] ?? 'Sistem'); ?></span>
                                    <span class="font-semibold text-slate-800 shrink-0 ml-2"><?= (int) $p['total']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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