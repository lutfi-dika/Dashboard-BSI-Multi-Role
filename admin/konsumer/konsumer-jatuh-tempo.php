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

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_konsumer LIKE 'tanggal_jatuh_tempo'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

$terlambat = [];
$segera    = []; // <= 7 hari
$mendatang = []; // 8-30 hari
$total_outstanding = 0;

if ($skemaLengkap) {
    $sql = "SELECT id, nama_lengkap, produk_diajukan, plafon_pengajuan, tanggal_jatuh_tempo,
                   DATEDIFF(tanggal_jatuh_tempo, CURDATE()) AS sisa_hari
            FROM nasabah_konsumer
            WHERE status = 'disetujui' AND tanggal_jatuh_tempo IS NOT NULL
            ORDER BY tanggal_jatuh_tempo ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $sisa = (int) $row['sisa_hari'];
            $total_outstanding += (float) $row['plafon_pengajuan'];
            if ($sisa < 0) {
                $terlambat[] = $row;
            } elseif ($sisa <= 7) {
                $segera[] = $row;
            } elseif ($sisa <= 30) {
                $mendatang[] = $row;
            }
        }
    }
}

function badge_sisa(int $sisa): string
{
    if ($sisa < 0) return '<span class="pm-badge bg-rose-50 text-rose-700">Terlambat ' . abs($sisa) . ' hari</span>';
    if ($sisa === 0) return '<span class="pm-badge bg-rose-50 text-rose-700">Jatuh tempo hari ini</span>';
    if ($sisa <= 7) return '<span class="pm-badge bg-amber-50 text-amber-700">H-' . $sisa . '</span>';
    return '<span class="pm-badge bg-sky-50 text-sky-700">H-' . $sisa . '</span>';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Jatuh Tempo | BSI Konsumer', 'konsumer'); ?>
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
            'title'    => 'Jatuh Tempo',
            'subtitle' => 'Pemantauan pembiayaan konsumer yang mendekati atau melewati jatuh tempo.',
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/005_konsumer_tahap2_lanjutan.sql</code> agar tanggal jatuh tempo tersedia.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d1">
                    <p class="text-[11px] text-slate-500">Terlambat</p>
                    <p class="text-xl sm:text-2xl font-bold text-rose-600 mt-1"><?= count($terlambat); ?></p>
                </div>
                <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d2">
                    <p class="text-[11px] text-slate-500">Segera (&le; 7 hari)</p>
                    <p class="text-xl sm:text-2xl font-bold text-amber-600 mt-1"><?= count($segera); ?></p>
                </div>
                <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d3">
                    <p class="text-[11px] text-slate-500">Mendatang (8-30 hari)</p>
                    <p class="text-xl sm:text-2xl font-bold text-sky-600 mt-1"><?= count($mendatang); ?></p>
                </div>
                <div class="pm-card pm-card-hover p-4 sm:p-5 pm-fade pm-d4">
                    <p class="text-[11px] text-slate-500">Total Plafon Aktif</p>
                    <p class="text-lg sm:text-xl font-bold text-slate-800 mt-1">Rp<?= number_format($total_outstanding, 0, ',', '.'); ?></p>
                </div>
            </div>

            <?php
            $seksi = [
                ['Terlambat', $terlambat, 'text-rose-600'],
                ['Segera Jatuh Tempo (&le; 7 hari)', $segera, 'text-amber-600'],
                ['Jatuh Tempo dalam 30 Hari', $mendatang, 'text-sky-600'],
            ];
            foreach ($seksi as [$judul, $list, $warna]):
            ?>
                <div class="pm-card overflow-hidden mb-6 pm-fade pm-d2">
                    <div class="px-4 sm:px-6 pt-5 pb-3 flex items-center justify-between">
                        <h4 class="text-sm font-bold <?= $warna; ?>"><?= $judul; ?></h4>
                        <span class="pm-badge bg-slate-100 text-slate-600"><?= count($list); ?> nasabah</span>
                    </div>
                    <div class="pm-table-wrap pm-scroll">
                        <table class="pm-table min-w-[680px]">
                            <thead>
                                <tr>
                                    <th>Nasabah</th>
                                    <th>Produk</th>
                                    <th>Plafon</th>
                                    <th>Tgl. Jatuh Tempo</th>
                                    <th class="!text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-xs text-slate-500 italic">Tidak ada data pada kategori ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($list as $row): ?>
                                        <tr>
                                            <td class="font-semibold text-slate-800"><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                                            <td class="text-slate-600"><?= htmlspecialchars($row['produk_diajukan'] ?: '-'); ?></td>
                                            <td class="text-slate-600">Rp<?= number_format((float) $row['plafon_pengajuan'], 0, ',', '.'); ?></td>
                                            <td class="text-slate-600"><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal_jatuh_tempo']))); ?></td>
                                            <td class="!text-right"><?= badge_sisa((int) $row['sisa_hari']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
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