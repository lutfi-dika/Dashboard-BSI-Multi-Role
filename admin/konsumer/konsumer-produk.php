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
$current_page = 'konsumer-produk.php';

if (!in_array($role_user, ['admin', 'konsumer', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$produkDefinisi = [
    'BSI Griya iB'     => ['warna' => '#0ea5e9', 'bg' => 'bg-sky-50',   'text' => 'text-sky-700',   'ket' => 'Pembiayaan Rumah (KPR Syariah)', 'plafon' => 's/d 20 tahun'],
    'BSI OTO iB'       => ['warna' => '#8b5cf6', 'bg' => 'bg-violet-50', 'text' => 'text-violet-700', 'ket' => 'Pembiayaan Kendaraan', 'plafon' => 's/d 8 tahun'],
    'BSI Pensiun iB'   => ['warna' => '#ec4899', 'bg' => 'bg-pink-50',   'text' => 'text-pink-700',   'ket' => 'Pembiayaan Pensiunan', 'plafon' => 's/d 15 tahun'],
    'BSI Umroh iB'     => ['warna' => '#10b981', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ket' => 'Pembiayaan Umroh', 'plafon' => 's/d 5 tahun'],
    'BSI Multiguna iB' => ['warna' => '#f59e0b', 'bg' => 'bg-amber-50',  'text' => 'text-amber-700',  'ket' => 'Pembiayaan Multiguna', 'plafon' => 's/d 5 tahun'],
    'BSI Fleksi iB'    => ['warna' => '#6366f1', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'ket' => 'Pembiayaan Fleksibel', 'plafon' => 's/d 5 tahun'],
];

$produk = $_GET['p'] ?? '';
if (!isset($produkDefinisi[$produk])) {
    $produk = 'BSI Multiguna iB';
}
$def = $produkDefinisi[$produk];

// Deteksi kolom yang tersedia untuk menghindari error pada database parsial
$kolomTersediaProduk = [];
$cekKolomProduk = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_konsumer");
if ($cekKolomProduk) {
    while ($k = mysqli_fetch_assoc($cekKolomProduk)) {
        $kolomTersediaProduk[] = $k['Field'];
    }
}
$hasPlafon = in_array('plafon_pengajuan', $kolomTersediaProduk, true);
$hasTenor = in_array('tenor_bulan', $kolomTersediaProduk, true);
$hasJatuhTempo = in_array('tanggal_jatuh_tempo', $kolomTersediaProduk, true);

$selectCols = 'nama_lengkap, pekerjaan, pendapatan_bulanan, status';
if ($hasPlafon) $selectCols .= ', plafon_pengajuan';
if ($hasTenor) $selectCols .= ', tenor_bulan';
if ($hasJatuhTempo) $selectCols .= ', tanggal_jatuh_tempo';

$kpiDefaults = ['jml' => 0, 'plafon_total' => 0, 'disetujui' => 0, 'pending' => 0];
if ($hasPlafon) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS jml, SUM(plafon_pengajuan) AS plafon_total, SUM(CASE WHEN status='disetujui' THEN 1 ELSE 0 END) AS disetujui, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending FROM nasabah_konsumer WHERE produk_diajukan = ?");
} else {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS jml, 0 AS plafon_total, SUM(CASE WHEN status='disetujui' THEN 1 ELSE 0 END) AS disetujui, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending FROM nasabah_konsumer WHERE produk_diajukan = ?");
}
mysqli_stmt_bind_param($stmt, "s", $produk);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $kpiDefaults;
mysqli_stmt_close($stmt);

$stmtList = mysqli_prepare($conn, "SELECT $selectCols FROM nasabah_konsumer WHERE produk_diajukan = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmtList, "s", $produk);
mysqli_stmt_execute($stmtList);
$qList = mysqli_stmt_get_result($stmtList);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head(htmlspecialchars($produk) . ' | BSI Konsumer', 'konsumer'); ?>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen relative w-full min-w-0">

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
            'title'    => htmlspecialchars($produk),
            'subtitle' => htmlspecialchars($def['ket']),
            'stats'    => [
                ['label' => 'Total Nasabah',   'value' => number_format((int) $kpi['jml'])],
                ['label' => 'Disetujui',       'value' => number_format((int) $kpi['disetujui'])],
                ['label' => 'Menunggu Review', 'value' => number_format((int) $kpi['pending'])],
            ],
            'actions'  => [
                ['href' => 'konsumer-dashboard.php', 'label' => '&larr; Kembali ke Dashboard'],
            ],
        ]); ?>

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 mb-5">
            <div class="pm-card pm-card-hover p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Nasabah</p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1"><?= number_format($kpi['jml']); ?></h3>
                <p class="text-xs <?= $def['text']; ?> font-medium mt-1">Pengajuan <?= htmlspecialchars($produk); ?></p>
            </div>
            <div class="pm-card pm-card-hover p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Plafon</p>
                <h3 class="text-lg sm:text-2xl font-extrabold text-slate-900 mt-1 truncate">Rp <?= number_format($kpi['plafon_total'], 0, ',', '.'); ?></h3>
                <p class="text-xs text-sky-500 font-medium mt-1">Total pengajuan pembiayaan</p>
            </div>
            <div class="pm-card pm-card-hover p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Disetujui</p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-emerald-600 mt-1"><?= number_format($kpi['disetujui']); ?></h3>
                <p class="text-xs text-emerald-500 font-medium mt-1">Pembiayaan aktif</p>
            </div>
            <div class="pm-card pm-card-hover p-5 pm-fade pm-d4">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Menunggu Review</p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-amber-600 mt-1"><?= number_format($kpi['pending']); ?></h3>
                <p class="text-xs text-amber-500 font-medium mt-1">Pengajuan pending</p>
            </div>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d1">
            <div class="flex justify-between items-center px-4 sm:px-6 pt-6 pb-4">
                <h4 class="text-sm font-bold tracking-wide text-slate-800">Daftar Nasabah <?= htmlspecialchars($produk); ?></h4>
                <a href="konsumer-tambah.php?p=<?= urlencode($produk); ?>" class="text-xs font-semibold <?= $def['text']; ?> hover:underline">+ Tambah Pengajuan</a>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table w-full">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Pekerjaan</th>
                            <th>Pendapatan</th>
                            <?php if ($hasPlafon): ?><th>Plafon</th><?php endif; ?>
                            <?php if ($hasTenor): ?><th>Tenor</th><?php endif; ?>
                            <?php if ($hasJatuhTempo): ?><th>Jatuh Tempo</th><?php endif; ?>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($qList && mysqli_num_rows($qList) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($qList)): ?>
                                <?php
                                $st = $r['status'] ?? '';
                                if ($st === 'disetujui') {
                                    $badge = 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                                    $labelStatus = 'Disetujui / Aktif';
                                } elseif ($st === 'pending') {
                                    $badge = 'bg-amber-50 text-amber-600 border border-amber-100';
                                    $labelStatus = 'Pending';
                                } elseif ($st === 'ditolak') {
                                    $badge = 'bg-rose-50 text-rose-600 border border-rose-100';
                                    $labelStatus = 'Ditolak';
                                } else {
                                    $badge = 'bg-slate-50 text-slate-500 border border-slate-100';
                                    $labelStatus = ucfirst($st);
                                }
                                ?>
                                <tr>
                                    <td class="font-semibold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($r['nama_lengkap']); ?></td>
                                    <td class="text-slate-500"><?= htmlspecialchars($r['pekerjaan'] ?? '—'); ?></td>
                                    <td class="text-slate-600 whitespace-nowrap">Rp <?= number_format($r['pendapatan_bulanan'], 0, ',', '.'); ?></td>
                                    <?php if ($hasPlafon): ?><td class="text-slate-600 whitespace-nowrap">Rp <?= number_format($r['plafon_pengajuan'] ?? 0, 0, ',', '.'); ?></td><?php endif; ?>
                                    <?php if ($hasTenor): ?><td class="text-slate-500"><?= (int)($r['tenor_bulan'] ?? 0); ?> bln</td><?php endif; ?>
                                    <?php if ($hasJatuhTempo): ?><td class="text-slate-500 whitespace-nowrap"><?= !empty($r['tanggal_jatuh_tempo']) ? date('d M Y', strtotime($r['tanggal_jatuh_tempo'])) : '—'; ?></td><?php endif; ?>
                                    <td><span class="pm-badge <?= $badge; ?>"><?= $labelStatus; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= 3 + ($hasPlafon ? 1 : 0) + ($hasTenor ? 1 : 0) + ($hasJatuhTempo ? 1 : 0) + 1; ?>" class="text-center text-xs text-slate-400 italic">Belum ada nasabah pada produk ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>

</html>
