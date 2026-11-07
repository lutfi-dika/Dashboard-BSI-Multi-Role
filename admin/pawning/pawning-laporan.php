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

$dari   = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
    $dari = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
    $sampai = date('Y-m-d');
}

$dariEs = mysqli_real_escape_string($conn, $dari);
$sampaiEs = mysqli_real_escape_string($conn, $sampai);
$where = "WHERE created_at >= '$dariEs' AND created_at <= '$sampaiEs 23:59:59'";
if (!empty($status)) {
    $st = mysqli_real_escape_string($conn, $status);
    $where .= " AND status = '$st'";
}

$daftar = mysqli_query($conn, "SELECT * FROM pawning $where ORDER BY created_at DESC");
$ringkasan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) jumlah, COALESCE(SUM(taksiran_harga),0) taks, COALESCE(SUM(nilai_pinjaman),0) pinj FROM pawning $where"));

$cetak = isset($_GET['cetak']) && $_GET['cetak'] === '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Laporan Gadai | BSI Pawning', 'pawning'); ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            main { max-height: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/pawning-sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
      <div class="max-w-[1600px] mx-auto">

        <div class="flex items-center justify-between gap-3 mb-5 no-print">
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
            'title'    => 'Laporan Gadai',
            'subtitle' => 'Buat laporan gadai berkala berdasarkan rentang tanggal dan status.',
            'stats'    => [
                ['label' => 'Jumlah Transaksi', 'value' => number_format($ringkasan['jumlah'] ?? 0)],
                ['label' => 'Total Taksiran',   'value' => 'Rp ' . number_format($ringkasan['taks'] ?? 0, 0, ',', '.')],
                ['label' => 'Total Pinjaman',   'value' => 'Rp ' . number_format($ringkasan['pinj'] ?? 0, 0, ',', '.')],
            ],
            'actions'  => [['href' => 'pawning-export.php', 'label' => 'Export']],
        ]); ?>

        <div class="pm-card p-4 mb-5 no-print pm-fade pm-d1">
            <form method="GET" class="flex flex-col md:flex-row gap-3 items-end">
                <div>
                    <label class="text-xs font-semibold text-slate-500 mb-1 block">Dari Tanggal</label>
                    <input type="date" name="dari" value="<?= $dari; ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 mb-1 block">Sampai Tanggal</label>
                    <input type="date" name="sampai" value="<?= $sampai; ?>" class="px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 mb-1 block">Status</label>
                    <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                        <option value="">Semua Status</option>
                        <?php foreach (['Aktif', 'Lunas', 'Lelang'] as $s): ?>
                            <option value="<?= $s; ?>" <?= $status === $s ? 'selected' : ''; ?>><?= $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="pm-btn pm-btn-primary">Tampilkan</button>
            </form>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-5">
            <div class="pm-card p-4 pm-fade pm-d2">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Jumlah Transaksi</p>
                <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($ringkasan['jumlah'] ?? 0); ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d3">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Taksiran</p>
                <p class="text-2xl font-extrabold text-slate-900 mt-1">Rp <?= number_format($ringkasan['taks'] ?? 0, 0, ',', '.'); ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d4">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Pinjaman</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1">Rp <?= number_format($ringkasan['pinj'] ?? 0, 0, ',', '.'); ?></p>
            </div>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d3">
            <div class="px-5 py-4 flex items-center justify-between gap-3">
                <h4 class="text-sm font-bold text-slate-800">Rincian Laporan <?= date('d M Y', strtotime($dari)); ?> s/d <?= date('d M Y', strtotime($sampai)); ?></h4>
                <button onclick="window.print()" class="pm-btn pm-btn-ghost no-print">Cetak</button>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[820px]">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pemilik</th>
                            <th>Barang</th>
                            <th>Taksiran</th>
                            <th>Pinjaman</th>
                            <th>Status</th>
                            <th>Tanggal Input</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daftar && mysqli_num_rows($daftar) > 0): $no = 1; ?>
                            <?php while ($p = mysqli_fetch_assoc($daftar)): ?>
                                <tr>
                                    <td class="text-slate-500"><?= $no++; ?></td>
                                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($p['nama_pemilik']); ?></td>
                                    <td><?= htmlspecialchars($p['barang_jaminan']); ?></td>
                                    <td>Rp <?= number_format($p['taksiran_harga'], 0, ',', '.'); ?></td>
                                    <td class="font-semibold text-slate-800 whitespace-nowrap">Rp <?= number_format($p['nilai_pinjaman'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="pm-badge <?= $p['status'] === 'Aktif' ? 'bg-emerald-50 text-emerald-600' : ($p['status'] === 'Lunas' ? 'bg-sky-50 text-sky-600' : 'bg-rose-50 text-rose-600'); ?>"><?= htmlspecialchars($p['status']); ?></span>
                                    </td>
                                    <td class="text-xs text-slate-400 whitespace-nowrap"><?= date('d M Y', strtotime($p['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="py-10 text-center text-xs text-slate-400 italic">Tidak ada data pada periode tersebut.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </main>
</body>
</html>
