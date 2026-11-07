<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../auth/login.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');

if (!in_array($role_user, ['admin', 'pawning', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$tglHariIni = date('Y-m-d');

// Statistik
$stat = [];
$q = mysqli_query($conn, "
    SELECT
        SUM(status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND tanggal_jatuh_tempo < '$tglHariIni') AS lewat,
        SUM(status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND DATEDIFF(tanggal_jatuh_tempo, '$tglHariIni') BETWEEN 1 AND 7) AS h7,
        SUM(status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND DATEDIFF(tanggal_jatuh_tempo, '$tglHariIni') BETWEEN 8 AND 30) AS h30,
        SUM(status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND DATEDIFF(tanggal_jatuh_tempo, '$tglHariIni') > 30) AS aman,
        SUM(status = 'Aktif' AND tanggal_jatuh_tempo IS NULL) AS tanpaJT
    FROM pawning
");
if ($q) {
    $stat = mysqli_fetch_assoc($q);
}

// Daftar monitoring
$filter = $_GET['filter'] ?? 'semua';
$where = "WHERE status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL";
switch ($filter) {
    case 'lewat':
        $where .= " AND tanggal_jatuh_tempo < '$tglHariIni'";
        break;
    case '7hari':
        $where .= " AND DATEDIFF(tanggal_jatuh_tempo, '$tglHariIni') BETWEEN 1 AND 7";
        break;
    case '30hari':
        $where .= " AND DATEDIFF(tanggal_jatuh_tempo, '$tglHariIni') BETWEEN 8 AND 30";
        break;
    case 'aman':
        $where .= " AND DATEDIFF(tanggal_jatuh_tempo, '$tglHariIni') > 30";
        break;
}

$daftar = mysqli_query($conn, "SELECT *, DATEDIFF(tanggal_jatuh_tempo, '$tglHariIni') AS sisa_hari FROM pawning $where ORDER BY sisa_hari ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Monitoring Jatuh Tempo | BSI Pawning', 'pawning'); ?>
</head>
<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/pawning-sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
      <div class="max-w-[1600px] mx-auto">

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
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user); ?>&background=d97706&color=fff&bold=true" alt="Foto profil" class="w-9 h-9 rounded-full object-cover ring-2 ring-amber-500/20">
                    <span class="text-sm font-bold text-slate-700 hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-slate-400 hidden sm:block">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>

                <div id="profilePopup" class="hidden absolute right-0 top-12 w-64 sm:w-72 max-w-[90vw] bg-white border border-slate-100 rounded-2xl shadow-xl z-50 p-5">
                    <div class="text-center border-b pb-3 mb-3">
                        <h4 class="font-bold text-sm"><?= htmlspecialchars($nama_user); ?></h4>
                        <span class="text-[10px] uppercase font-bold text-amber-600"><?= htmlspecialchars($role_user); ?></span>
                    </div>

                    <?php if ($role_user === 'admin' || $role_user === 'atasan'): ?>
                        <div class="space-y-1 mb-4">
                            <p class="text-[10px] text-slate-400 font-bold uppercase mb-2">Beralih Dashboard</p>
                            <a href="../Mikro/mikro-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-emerald-50 rounded-lg">Dashboard Mikro</a>
                            <a href="../konsumer/konsumer-dashboard.php" class="block p-2 text-xs font-semibold hover:bg-sky-50 rounded-lg">Dashboard Konsumer</a>
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
            'accent'   => 'pawning',
            'eyebrow'  => 'BSI Pawning',
            'title'    => 'Monitoring Jatuh Tempo',
            'subtitle' => 'Pantau barang gadai yang lewat atau akan jatuh tempo.',
            'stats'    => [
                ['label' => 'Lewat Tempo', 'value' => number_format($stat['lewat'] ?? 0)],
                ['label' => 'H-1 s/d 7',   'value' => number_format($stat['h7'] ?? 0)],
                ['label' => 'Aman (>30)',  'value' => number_format($stat['aman'] ?? 0)],
            ],
        ]); ?>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="pm-card p-4 pm-fade pm-d1">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Lewat Tempo</p>
                <p class="text-2xl font-extrabold text-rose-600 mt-1"><?= number_format($stat['lewat'] ?? 0); ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d2">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">H-1 s/d 7</p>
                <p class="text-2xl font-extrabold text-orange-500 mt-1"><?= number_format($stat['h7'] ?? 0); ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d3">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">H-8 s/d 30</p>
                <p class="text-2xl font-extrabold text-amber-500 mt-1"><?= number_format($stat['h30'] ?? 0); ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d4">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Aman (&gt;30)</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1"><?= number_format($stat['aman'] ?? 0); ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d5">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Belum Ada JT</p>
                <p class="text-2xl font-extrabold text-slate-500 mt-1"><?= number_format($stat['tanpaJT'] ?? 0); ?></p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-5">
            <?php
            $tabs = ['semua' => 'Semua', 'lewat' => 'Lewat Tempo', '7hari' => '1-7 Hari', '30hari' => '8-30 Hari', 'aman' => 'Aman'];
            foreach ($tabs as $k => $label) {
                $active = $filter === $k ? 'pm-btn-primary' : 'pm-btn-ghost';
                echo '<a href="pawning-jatuh-tempo.php?filter=' . $k . '" class="pm-btn ' . $active . ' !px-3 !py-1.5 !text-[11px]">' . $label . '</a>';
            }
            ?>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d3">
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Nasabah</th>
                            <th>Barang</th>
                            <th>Pinjaman</th>
                            <th>Tanggal Gadai</th>
                            <th>Jatuh Tempo</th>
                            <th>Sisa Hari</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daftar && mysqli_num_rows($daftar) > 0): ?>
                            <?php while ($p = mysqli_fetch_assoc($daftar)): ?>
                                <?php
                                $sisa = (int) $p['sisa_hari'];
                                $badge = $sisa < 0 ? 'bg-rose-50 text-rose-600' : ($sisa <= 7 ? 'bg-orange-50 text-orange-600' : ($sisa <= 30 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-600'));
                                ?>
                                <tr>
                                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($p['nama_pemilik']); ?></td>
                                    <td><?= htmlspecialchars($p['barang_jaminan']); ?></td>
                                    <td>Rp <?= number_format($p['nilai_pinjaman'], 0, ',', '.'); ?></td>
                                    <td class="text-xs text-slate-500 whitespace-nowrap"><?= isset($p['tanggal_gadai']) ? date('d M Y', strtotime($p['tanggal_gadai'])) : '-'; ?></td>
                                    <td class="text-xs text-slate-500 whitespace-nowrap"><?= date('d M Y', strtotime($p['tanggal_jatuh_tempo'])); ?></td>
                                    <td><span class="pm-badge <?= $badge; ?>"><?= $sisa < 0 ? 'Terlambat ' . abs($sisa) . ' hari' : 'H-' . $sisa; ?></span></td>
                                    <td>
                                        <a href="pawning-perpanjangan.php?id=<?= (int)$p['id']; ?>" class="text-amber-600 text-xs font-semibold hover:underline">Perpanjang</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="py-10 text-center text-xs text-slate-400 italic">Tidak ada data pada filter ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </main>
</body>
</html>
