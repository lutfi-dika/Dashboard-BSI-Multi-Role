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

$notif = [];
$tgl   = date('Y-m-d');

// Gadai baru (7 hari terakhir)
$q = mysqli_query($conn, "SELECT nama_pemilik, barang_jaminan, created_at FROM pawning WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 10");
while ($r = mysqli_fetch_assoc($q)) {
    $notif[] = ['type' => 'info', 'title' => 'Gadai baru: ' . $r['nama_pemilik'], 'desc' => 'Barang ' . $r['barang_jaminan'] . ' baru diinput.', 'time' => $r['created_at']];
}

// Jatuh tempo H-1 s/d 30
$q = mysqli_query($conn, "SELECT nama_pemilik, barang_jaminan, tanggal_jatuh_tempo, DATEDIFF(tanggal_jatuh_tempo, '$tgl') sisa FROM pawning WHERE status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND DATEDIFF(tanggal_jatuh_tempo, '$tgl') BETWEEN 1 AND 30 ORDER BY tanggal_jatuh_tempo ASC LIMIT 15");
while ($r = mysqli_fetch_assoc($q)) {
    $notif[] = ['type' => 'warning', 'title' => 'Jatuh tempo H-' . $r['sisa'] . ': ' . $r['nama_pemilik'], 'desc' => 'Barang ' . $r['barang_jaminan'] . ' jatuh tempo ' . date('d M Y', strtotime($r['tanggal_jatuh_tempo'])) . '.', 'time' => null];
}

// Lewat tempo
$q = mysqli_query($conn, "SELECT nama_pemilik, barang_jaminan, tanggal_jatuh_tempo, DATEDIFF('$tgl', tanggal_jatuh_tempo) terlambat FROM pawning WHERE status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND tanggal_jatuh_tempo < '$tgl' ORDER BY tanggal_jatuh_tempo ASC LIMIT 15");
while ($r = mysqli_fetch_assoc($q)) {
    $notif[] = ['type' => 'danger', 'title' => 'Lewat tempo ' . $r['terlambat'] . ' hari: ' . $r['nama_pemilik'], 'desc' => 'Barang ' . $r['barang_jaminan'] . ' perlu tindak lanjut (perpanjangan/lelang).', 'time' => null];
}

$typeStyle = [
    'info' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-600'],
    'warning' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600'],
    'danger' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-600'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Notifikasi Gadai | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Notifikasi',
            'subtitle' => 'Peringatan otomatis dari kondisi data gadai terkini (input baru, jatuh tempo, lewat tempo).',
            'stats'    => [
                ['label' => 'Notifikasi', 'value' => number_format(count($notif))],
            ],
        ]); ?>

        <?php if (count($notif) === 0): ?>
            <div class="pm-card p-10 text-center text-xs text-slate-400 italic pm-fade pm-d1">Tidak ada notifikasi saat ini. Semua kondisi portofolio aman.</div>
        <?php else: ?>
            <div class="pm-card overflow-hidden divide-y divide-slate-50 pm-fade pm-d1">
                <?php foreach ($notif as $n): $s = $typeStyle[$n['type']]; ?>
                    <div class="p-4 sm:p-5 flex items-start gap-3.5 hover:bg-slate-50/60 transition">
                        <div class="w-9 h-9 rounded-xl <?= $s['bg']; ?> <?= $s['text']; ?> flex items-center justify-center shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($n['title']); ?></h4>
                            <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($n['desc']); ?></p>
                            <?php if ($n['time']): ?>
                                <p class="text-[10px] text-slate-400 mt-1"><?= htmlspecialchars(date('d M Y H:i', strtotime($n['time']))); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
    </main>
</body>
</html>
