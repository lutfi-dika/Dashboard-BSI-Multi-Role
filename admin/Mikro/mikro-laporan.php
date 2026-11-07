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
$current_page = 'laporan';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'status_pengajuan'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

$bulan = (int) ($_GET['bulan'] ?? date('n'));
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int) date('n');
if ($tahun < 2000 || $tahun > 2100) $tahun = (int) date('Y');

$kpi = ['baru' => 0, 'aktif' => 0, 'ditolak' => 0, 'total_plafon' => 0.0, 'total_outstanding' => 0.0];
$perAo = [];
$perSektor = [];
$npfRatio = 0;

if ($skemaLengkap) {
    $stmt = mysqli_prepare($conn, "SELECT
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) AS baru,
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? AND status_pengajuan = 'Aktif' THEN 1 ELSE 0 END) AS aktif,
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? AND status_pengajuan = 'Ditolak' THEN 1 ELSE 0 END) AS ditolak,
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN plafon_pembiayaan ELSE 0 END) AS total_plafon
        FROM nasabah_mikro");
    mysqli_stmt_bind_param($stmt, "iiiiiiii", $bulan, $tahun, $bulan, $tahun, $bulan, $tahun, $bulan, $tahun);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $kpi['baru'] = (int) ($r['baru'] ?? 0);
    $kpi['aktif'] = (int) ($r['aktif'] ?? 0);
    $kpi['ditolak'] = (int) ($r['ditolak'] ?? 0);
    $kpi['total_plafon'] = (float) ($r['total_plafon'] ?? 0);

    // Outstanding & NPF berjalan (kondisi terkini, bukan hanya bulan filter -- karena outstanding sifatnya posisi saat ini)
    $r2 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(outstanding) AS total, SUM(CASE WHEN kolektibilitas >= 3 THEN outstanding ELSE 0 END) AS npf FROM nasabah_mikro WHERE status_pengajuan='Aktif'"));
    $kpi['total_outstanding'] = (float) ($r2['total'] ?? 0);
    $npfRatio = $kpi['total_outstanding'] > 0 ? (((float) ($r2['npf'] ?? 0)) / $kpi['total_outstanding']) * 100 : 0;

    // Rekap per AO untuk periode terpilih
    $stmt = mysqli_prepare($conn, "SELECT nama_ao, COUNT(*) AS jml, SUM(plafon_pembiayaan) AS total_plafon
                                    FROM nasabah_mikro
                                    WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
                                    GROUP BY nama_ao ORDER BY total_plafon DESC");
    mysqli_stmt_bind_param($stmt, "ii", $bulan, $tahun);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $perAo[] = $row;
    mysqli_stmt_close($stmt);

    // Rekap per sektor untuk periode terpilih
    $stmt = mysqli_prepare($conn, "SELECT jenis_sektor, COUNT(*) AS jml, SUM(plafon_pembiayaan) AS total_plafon
                                    FROM nasabah_mikro
                                    WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
                                    GROUP BY jenis_sektor ORDER BY total_plafon DESC");
    mysqli_stmt_bind_param($stmt, "ii", $bulan, $tahun);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $perSektor[] = $row;
    mysqli_stmt_close($stmt);
}

$namaBulan = ['1'=>'Januari','2'=>'Februari','3'=>'Maret','4'=>'April','5'=>'Mei','6'=>'Juni','7'=>'Juli','8'=>'Agustus','9'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Laporan | BSI Mikro', 'mikro'); ?>
    <style>
        @media print {
            #sidebar, #sidebarOverlay, .no-print { display: none !important; }
            main { padding: 0 !important; max-height: none !important; overflow: visible !important; }
        }
    </style>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
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

        <div class="no-print">
        <?php premium_hero([
            'accent'   => 'mikro',
            'eyebrow'  => 'BSI Mikro',
            'title'    => 'Laporan',
            'subtitle' => 'Rekap bulanan pengajuan, outstanding, dan performa AO segmen Mikro.',
            'stats'    => [
                ['label' => 'Pengajuan Baru', 'value' => $kpi['baru']],
                ['label' => 'Disetujui/Aktif', 'value' => $kpi['aktif']],
                ['label' => 'Outstanding Berjalan', 'value' => 'Rp ' . number_format($kpi['total_outstanding'], 0, ',', '.')],
            ],
        ]); ?>
        </div>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/002_mikro_dashboard_kpi.sql</code> agar laporan bisa dihitung.
            </div>
        <?php else: ?>

            <form method="GET" class="pm-card rounded-2xl p-4 mb-6 flex flex-wrap items-end gap-3 no-print">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Bulan</label>
                    <select name="bulan" class="text-xs rounded-lg border border-slate-200 px-2.5 py-2">
                        <?php foreach ($namaBulan as $k => $v): ?>
                            <option value="<?= $k; ?>" <?= $bulan == $k ? 'selected' : ''; ?>><?= $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Tahun</label>
                    <select name="tahun" class="text-xs rounded-lg border border-slate-200 px-2.5 py-2">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--): ?>
                            <option value="<?= $y; ?>" <?= $tahun == $y ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="pm-btn pm-btn-primary">Tampilkan</button>
                <div class="flex-1"></div>
                <button type="button" onclick="window.print()" class="pm-btn pm-btn-ghost">Cetak / PDF</button>
                <a href="mikro-export-excel.php" class="pm-btn pm-btn-ghost">Export Excel (semua data)</a>
            </form>

            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Laporan periode <?= $namaBulan[(string) $bulan]; ?> <?= $tahun; ?></p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <?php premium_kpi_card(['tone' => 'indigo', 'delay' => 1, 'label' => 'Pengajuan Baru', 'value' => $kpi['baru']]); ?>
                <?php premium_kpi_card(['tone' => 'emerald', 'delay' => 2, 'label' => 'Disetujui/Aktif', 'value' => $kpi['aktif'], 'value_class' => '!text-emerald-600']); ?>
                <?php premium_kpi_card(['tone' => 'rose', 'delay' => 3, 'label' => 'Ditolak', 'value' => $kpi['ditolak'], 'value_class' => '!text-rose-600']); ?>
                <?php premium_kpi_card(['tone' => 'sky', 'delay' => 4, 'label' => 'Plafon Diajukan', 'value' => 'Rp ' . number_format($kpi['total_plafon'], 0, ',', '.')]); ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <?php premium_kpi_card(['tone' => 'violet', 'delay' => 1, 'label' => 'Total Outstanding Berjalan', 'value' => 'Rp ' . number_format($kpi['total_outstanding'], 0, ',', '.'), 'sub' => 'Posisi terkini seluruh nasabah Aktif']); ?>
                <?php premium_kpi_card(['tone' => 'amber', 'delay' => 2, 'label' => 'Rasio NPF Berjalan', 'value' => number_format($npfRatio, 2) . '%', 'value_class' => $npfRatio > 5 ? '!text-rose-600' : '!text-emerald-600']); ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="pm-card overflow-hidden pm-fade pm-d3">
                    <div class="px-5 pt-5 pb-3"><h4 class="text-sm font-bold text-slate-800">Rekap per AO (periode terpilih)</h4></div>
                    <div class="pm-table-wrap pm-scroll">
                        <table class="pm-table">
                            <thead><tr>
                                <th>AO</th><th>Pengajuan</th><th>Plafon</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($perAo as $a): ?>
                                    <tr><td class="font-medium"><?= htmlspecialchars($a['nama_ao'] ?: 'Belum Diisi'); ?></td>
                                        <td><?= $a['jml']; ?></td>
                                        <td>Rp <?= number_format((float) $a['total_plafon'], 0, ',', '.'); ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (empty($perAo)): ?><tr><td colspan="3" class="!text-center italic">Tidak ada data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="pm-card overflow-hidden pm-fade pm-d4">
                    <div class="px-5 pt-5 pb-3"><h4 class="text-sm font-bold text-slate-800">Rekap per Sektor (periode terpilih)</h4></div>
                    <div class="pm-table-wrap pm-scroll">
                        <table class="pm-table">
                            <thead><tr>
                                <th>Sektor</th><th>Pengajuan</th><th>Plafon</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($perSektor as $s): ?>
                                    <tr><td class="font-medium"><?= htmlspecialchars($s['jenis_sektor'] ?: 'Lainnya'); ?></td>
                                        <td><?= $s['jml']; ?></td>
                                        <td>Rp <?= number_format((float) $s['total_plafon'], 0, ',', '.'); ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (empty($perSektor)): ?><tr><td colspan="3" class="!text-center italic">Tidak ada data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
