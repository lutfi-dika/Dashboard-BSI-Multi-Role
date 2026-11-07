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
$current_page = 'mikro-produk.php';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$produkDefinisi = [
    'KUR Super Mikro' => ['plafon' => 's/d Rp 10 jt', 'warna' => '#10b981', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'deskripsi' => 'Pembiayaan usaha mikro hingga Rp 10 juta untuk pelaku usaha yang baru memulai, dengan agunan utama karakter usaha.'],
    'KUR Mikro'       => ['plafon' => 'Rp 10 - 100 jt', 'warna' => '#6366f1', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'deskripsi' => 'Pembiayaan modal kerja / investasi usaha mikro hingga Rp 100 juta.'],
    'KUR Kecil'       => ['plafon' => 'Rp 100 - 500 jt', 'warna' => '#0ea5e9', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'deskripsi' => 'Pembiayaan usaha kecil hingga Rp 500 juta untuk sektor produktif dan penyerap tenaga kerja.'],
    'BSI Mikro iB'    => ['plafon' => 'Non-KUR / Modal Kerja', 'warna' => '#f59e0b', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'deskripsi' => 'Pembiayaan mikro non-KUR untuk modal kerja & investasi dengan akad syariah.'],
];

$produk = $_GET['p'] ?? '';
if (!isset($produkDefinisi[$produk])) {
    $produk = 'BSI Mikro iB';
}
$def = $produkDefinisi[$produk];

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS jml, SUM(outstanding) AS out_total, SUM(plafon_pembiayaan) AS plafon_total, SUM(CASE WHEN kolektibilitas >= 3 THEN outstanding ELSE 0 END) AS npf_out FROM nasabah_mikro WHERE produk = ?");
mysqli_stmt_bind_param($stmt, "s", $produk);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['jml' => 0, 'out_total' => 0, 'plafon_total' => 0, 'npf_out' => 0];
mysqli_stmt_close($stmt);

$stmtList = mysqli_prepare($conn, "SELECT nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, plafon_pembiayaan, outstanding, kolektibilitas, status_pengajuan, nama_ao, tanggal_jatuh_tempo FROM nasabah_mikro WHERE produk = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmtList, "s", $produk);
mysqli_stmt_execute($stmtList);
$qList = mysqli_stmt_get_result($stmtList);

$npf_persen = ($kpi['out_total'] > 0) ? round(($kpi['npf_out'] / $kpi['out_total']) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head(htmlspecialchars($produk) . ' | BSI Mikro', 'mikro'); ?>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen relative w-full min-w-0">

        <div class="flex items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-2 min-w-0">
                <button onclick="toggleSidebar()" class="md:hidden shrink-0 p-2 rounded-xl bg-white border border-slate-200 text-slate-500 shadow-xs" aria-label="Buka menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="18" x2="21" y2="18" />
                    </svg>
                </button>
                <a href="mikro-dashboard.php" class="shrink-0 p-2 rounded-xl bg-white border border-slate-200 text-slate-500 shadow-xs hover:text-emerald-600 transition" title="Kembali ke Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                </a>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 relative shrink-0 ml-auto">
                <span class="text-[10px] font-bold px-2.5 py-1 rounded-md <?= $def['bg']; ?> <?= $def['text']; ?> hidden sm:block">Plafon: <?= $def['plafon']; ?></span>
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
            'title'    => htmlspecialchars($produk),
            'subtitle' => htmlspecialchars($def['deskripsi']),
            'stats'    => [
                ['label' => 'Total Debitur', 'value' => number_format((int)$kpi['jml'])],
                ['label' => 'Outstanding', 'value' => 'Rp ' . number_format((float)$kpi['out_total'], 0, ',', '.')],
                ['label' => 'Akad Pembiayaan', 'value' => 'Rp ' . number_format((float)$kpi['plafon_total'], 0, ',', '.')],
                ['label' => 'NPF Produk', 'value' => number_format($npf_persen, 2) . '%'],
            ],
        ]); ?>

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 mb-5">
            <?php premium_kpi_card(['tone' => 'emerald', 'delay' => 1, 'label' => 'Total Debitur', 'value' => number_format((int)$kpi['jml']), 'sub' => 'Aktif &amp; berjalan', 'sub_class' => $def['text']]); ?>
            <?php premium_kpi_card(['tone' => 'sky', 'delay' => 2, 'label' => 'Outstanding', 'value' => 'Rp ' . number_format((float)$kpi['out_total'], 0, ',', '.'), 'sub' => 'Baki debet berjalan', 'sub_class' => 'text-emerald-500']); ?>
            <?php premium_kpi_card(['tone' => 'indigo', 'delay' => 3, 'label' => 'Akad Pembiayaan', 'value' => 'Rp ' . number_format((float)$kpi['plafon_total'], 0, ',', '.'), 'sub' => 'Total plafon disalurkan', 'sub_class' => 'text-sky-500']); ?>
            <?php premium_kpi_card(['tone' => 'rose', 'delay' => 4, 'label' => 'NPF Produk', 'value' => number_format($npf_persen, 2) . '%', 'value_class' => $npf_persen > 5 ? '!text-rose-600' : '', 'sub' => 'Kolektibilitas &ge; 3', 'sub_class' => 'text-rose-500']); ?>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d1">
            <div class="flex justify-between items-center px-4 sm:px-6 pt-6 pb-4">
                <h4 class="text-sm font-bold tracking-wide text-slate-800">Portofolio <?= htmlspecialchars($produk); ?></h4>
                <a href="mikro-tambah.php" class="text-xs font-semibold <?= $def['text']; ?> hover:underline">+ Tambah Pengajuan</a>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[840px]">
                    <thead>
                        <tr>
                            <th>Nama Pemilik</th>
                            <th>Usaha</th>
                            <th>Sektor</th>
                            <th>Plafon</th>
                            <th>Outstanding</th>
                            <th>Kolektibilitas</th>
                            <th>Status</th>
                            <th>AO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($qList && mysqli_num_rows($qList) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($qList)): ?>
                                <?php
                                $st = $r['status_pengajuan'] ?? '';
                                if ($st === 'Aktif') {
                                    $badge = 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                                } elseif ($st === 'Approval') {
                                    $badge = 'bg-amber-50 text-amber-600 border border-amber-100';
                                } elseif ($st === 'Ditolak') {
                                    $badge = 'bg-rose-50 text-rose-600 border border-rose-100';
                                } else {
                                    $badge = 'bg-slate-50 text-slate-500 border border-slate-100';
                                }
                                $kol = (int)($r['kolektibilitas'] ?? 1);
                                if ($kol === 1) {
                                    $kolBadge = 'bg-emerald-50 text-emerald-600';
                                } elseif ($kol === 2) {
                                    $kolBadge = 'bg-yellow-50 text-yellow-600';
                                } else {
                                    $kolBadge = 'bg-rose-50 text-rose-600';
                                }
                                ?>
                                <tr>
                                    <td class="font-semibold !text-slate-800 whitespace-nowrap"><?= htmlspecialchars($r['nama_pemilik']); ?></td>
                                    <td><?= htmlspecialchars($r['nama_usaha']); ?></td>
                                    <td><?= htmlspecialchars($r['jenis_sektor']); ?></td>
                                    <td class="whitespace-nowrap">Rp <?= number_format($r['plafon_pembiayaan'], 0, ',', '.'); ?></td>
                                    <td class="whitespace-nowrap">Rp <?= number_format($r['outstanding'], 0, ',', '.'); ?></td>
                                    <td><span class="pm-badge <?= $kolBadge; ?>">Kol <?= (int)$r['kolektibilitas']; ?></span></td>
                                    <td><span class="pm-badge <?= $badge; ?>"><?= htmlspecialchars($r['status_pengajuan']); ?></span></td>
                                    <td><?= htmlspecialchars($r['nama_ao'] ?? '—'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="!text-center italic">Belum ada debitur pada produk ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
