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
$current_page = 'pawning-produk.php';

if (!in_array($role_user, ['admin', 'pawning', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$produkGadaiDef = [
    'Gadai Emas iB'  => ['warna' => '#d4a017', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ket' => 'Jaminan emas batangan / perhiasan', 'plafon' => 's/d 95% nilai taksiran'],
    'Gadai Non Emas' => ['warna' => '#64748b', 'bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'ket' => 'Jaminan BPKB / barang bergerak', 'plafon' => 's/d 85% nilai taksiran'],
    'Fleksi Emas iB' => ['warna' => '#0369a1', 'bg' => 'bg-sky-50',   'text' => 'text-sky-700',   'ket' => 'Pinjaman berulang dengan jaminan emas', 'plafon' => 'Top-up fleksibel'],
];

$produk = $_GET['p'] ?? '';
if (!isset($produkGadaiDef[$produk])) {
    $produk = 'Gadai Emas iB';
}
$def = $produkGadaiDef[$produk];

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS jml, SUM(nilai_pinjaman) AS pinjam_total, SUM(CASE WHEN status='Aktif' THEN 1 ELSE 0 END) AS aktif, SUM(CASE WHEN status='Lunas' THEN 1 ELSE 0 END) AS lunas FROM pawning WHERE jenis_gadai = ?");
mysqli_stmt_bind_param($stmt, "s", $produk);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['jml' => 0, 'pinjam_total' => 0, 'aktif' => 0, 'lunas' => 0];
mysqli_stmt_close($stmt);

$stmtList = mysqli_prepare($conn, "SELECT nama_pemilik, barang_jaminan, taksiran_harga, nilai_pinjaman, tanggal_jatuh_tempo, status FROM pawning WHERE jenis_gadai = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmtList, "s", $produk);
mysqli_stmt_execute($stmtList);
$qList = mysqli_stmt_get_result($stmtList);
mysqli_stmt_close($stmtList);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head(htmlspecialchars($produk) . ' - Gadai BSI | BSI Pawning', 'pawning'); ?>
</head>
<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/pawning-sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto relative w-full min-w-0">
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
            'title'    => htmlspecialchars($produk),
            'subtitle' => htmlspecialchars($def['ket']),
            'stats'    => [
                ['label' => 'Barang Jaminan',  'value' => number_format($kpi['jml'])],
                ['label' => 'Nilai Pinjaman',  'value' => 'Rp ' . number_format($kpi['pinjam_total'], 0, ',', '.')],
                ['label' => 'Status Aktif',    'value' => number_format($kpi['aktif'])],
            ],
            'actions'  => [['href' => 'pawning-dashboard.php', 'label' => '&larr; Kembali ke Dashboard']],
        ]); ?>

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 mb-5">
            <div class="pm-card p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Barang Jaminan</p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1"><?= number_format($kpi['jml']); ?></h3>
                <p class="text-xs <?= $def['text']; ?> font-medium mt-1"><?= htmlspecialchars($produk); ?></p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Nilai Pinjaman</p>
                <h3 class="text-lg sm:text-2xl font-extrabold text-slate-900 mt-1 truncate">Rp <?= number_format($kpi['pinjam_total'], 0, ',', '.'); ?></h3>
                <p class="text-xs text-amber-500 font-medium mt-1">Baki debet berjalan</p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Aktif</p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-emerald-600 mt-1"><?= number_format($kpi['aktif']); ?></h3>
                <p class="text-xs text-emerald-500 font-medium mt-1">Belum jatuh tempo</p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d4">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Lunas</p>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1"><?= number_format($kpi['lunas']); ?></h3>
                <p class="text-xs text-slate-400 font-medium mt-1">Sudah ditebus</p>
            </div>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d2">
            <div class="flex justify-between items-center px-4 sm:px-6 pt-6 pb-4">
                <h4 class="text-sm font-bold tracking-wide text-slate-800">Daftar Jaminan <?= htmlspecialchars($produk); ?></h4>
                <div class="flex items-center gap-3">
                    <span class="pm-badge <?= $def['bg']; ?> <?= $def['text']; ?>"><?= $def['plafon']; ?></span>
                    <a href="pawning-tambah.php" class="text-xs font-semibold <?= $def['text']; ?> hover:underline">+ Tambah Gadai</a>
                </div>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[840px]">
                    <thead>
                        <tr>
                            <th>Nama Pemilik</th>
                            <th>Barang Jaminan</th>
                            <th>Taksiran</th>
                            <th>Nilai Pinjaman</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($qList && mysqli_num_rows($qList) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($qList)): ?>
                                <?php
                                $st = $r['status'] ?? '';
                                if ($st === 'Aktif') {
                                    $badge = 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                                } elseif ($st === 'Lunas') {
                                    $badge = 'bg-slate-50 text-slate-500 border border-slate-100';
                                } elseif ($st === 'Lelang') {
                                    $badge = 'bg-rose-50 text-rose-600 border border-rose-100';
                                } else {
                                    $badge = 'bg-amber-50 text-amber-600 border border-amber-100';
                                }
                                ?>
                                <tr>
                                    <td class="font-semibold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($r['nama_pemilik']); ?></td>
                                    <td><?= htmlspecialchars($r['barang_jaminan'] ?? '—'); ?></td>
                                    <td class="whitespace-nowrap">Rp <?= number_format($r['taksiran_harga'], 0, ',', '.'); ?></td>
                                    <td class="whitespace-nowrap">Rp <?= number_format($r['nilai_pinjaman'], 0, ',', '.'); ?></td>
                                    <td class="whitespace-nowrap"><?= $r['tanggal_jatuh_tempo'] ? date('d M Y', strtotime($r['tanggal_jatuh_tempo'])) : '—'; ?></td>
                                    <td><span class="pm-badge <?= $badge; ?>"><?= htmlspecialchars($r['status']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-10 text-center text-xs text-slate-400 italic">Belum ada barang jaminan pada produk ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </main>
</body>
</html>
