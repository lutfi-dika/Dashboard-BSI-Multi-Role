<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

// Proteksi Hak Akses Karyawan: admin, petugas mikro (tulis) dan atasan (baca saja)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'mikro' && $_SESSION['role'] !== 'atasan')) {
    header("Location: ../../index.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');

// Ambil data nasabah mikro beserta nama lengkap karyawan yang menginputnya
$query = "SELECT m.*, u.nama_lengkap AS nama_petugas 
          FROM nasabah_mikro m 
          LEFT JOIN users u ON m.user_id = u.id 
          ORDER BY m.created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Data Segmen Mikro | BSI Mikro', 'mikro'); ?>
</head>

<body class="text-slate-800 min-h-screen flex">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-6 md:p-8 overflow-y-auto max-h-screen">
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
            'title'    => 'Data Segmen Mikro',
            'subtitle' => 'Daftar rekapan data nasabah internal sektor usaha dan pelaku bisnis.',
            'action'   => ['href' => 'mikro-tambah.php', 'label' => '+ Tambah Pendataan'],
            'stats'    => [['label' => 'Total Record', 'value' => mysqli_num_rows($result)]],
        ]); ?>

        <div class="pm-card overflow-hidden pm-fade pm-d1">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Database Records (Sektor Mikro)</span>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[960px]">
                    <thead>
                        <tr>
                            <th>Nama Pemilik</th>
                            <th>Nama Usaha/Toko</th>
                            <th>Sektor Bisnis</th>
                            <th>Produk BSI</th>
                            <th>Omzet / Bulan</th>
                            <th>Lama Usaha</th>
                            <th>No. Telp</th>
                            <th>Karyawan Penginput</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="font-semibold !text-slate-800"><?= htmlspecialchars($row['nama_pemilik']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_usaha']); ?></td>
                                    <td><span class="pm-badge bg-indigo-50 text-indigo-600"><?= htmlspecialchars($row['jenis_sektor']); ?></span></td>
                                    <td><span class="pm-badge bg-emerald-50 text-emerald-600"><?= htmlspecialchars($row['produk'] ?? 'KUR Mikro'); ?></span></td>
                                    <td class="font-semibold !text-emerald-600 whitespace-nowrap">Rp <?= number_format($row['omzet_bulanan'], 0, ',', '.'); ?></td>
                                    <td><?= intval($row['lama_usaha_tahun']); ?> Tahun</td>
                                    <td><?= htmlspecialchars($row['no_telepon']); ?></td>
                                    <td class="text-xs font-medium"><?= htmlspecialchars($row['nama_petugas'] ?? 'Sistem / Admin'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="!text-center italic">Belum ada rekapan data segmen mikro di sistem ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>

</html>