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

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT * FROM pawning WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$data) {
    header('Location: pawning-data.php');
    exit();
}

// Data terkait
$foto      = mysqli_query($conn, "SELECT * FROM pawning_foto WHERE pawning_id = $id ORDER BY created_at DESC");
$perpanj   = mysqli_query($conn, "SELECT * FROM pawning_perpanjangan WHERE pawning_id = $id ORDER BY created_at DESC");
$pelunasan = mysqli_query($conn, "SELECT * FROM pawning_pelunasan WHERE pawning_id = $id ORDER BY created_at DESC");
$lelang    = mysqli_query($conn, "SELECT * FROM pawning_lelang WHERE pawning_id = $id ORDER BY created_at DESC");
$taksiran  = mysqli_query($conn, "SELECT * FROM pawning_taksiran WHERE pawning_id = $id ORDER BY created_at DESC");

function badgeStatus(string $status): string
{
    if ($status === 'Aktif') return 'bg-emerald-50 text-emerald-600 border border-emerald-100';
    if ($status === 'Lunas') return 'bg-sky-50 text-sky-600 border border-sky-100';
    if ($status === 'Lelang') return 'bg-rose-50 text-rose-600 border border-rose-100';
    return 'bg-slate-50 text-slate-500 border border-slate-100';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Detail Gadai - ' . htmlspecialchars($data['nama_pemilik']) . ' | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Detail Data Gadai',
            'subtitle' => 'Informasi lengkap nasabah &amp; barang jaminan.',
            'stats'    => [
                ['label' => 'Status',          'value' => htmlspecialchars($data['status'])],
                ['label' => 'Nilai Pinjaman',  'value' => 'Rp ' . number_format($data['nilai_pinjaman'], 0, ',', '.')],
                ['label' => 'Taksiran Harga',  'value' => 'Rp ' . number_format($data['taksiran_harga'], 0, ',', '.')],
            ],
            'actions'  => [['href' => 'pawning-data.php', 'label' => '&larr; Kembali ke Data']],
        ]); ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
            <div class="pm-card p-5 lg:col-span-2 pm-fade pm-d1">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Data Pemilik &amp; Barang</h4>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Nama Pemilik</dt>
                        <dd class="font-semibold text-slate-800 mt-0.5"><?= htmlspecialchars($data['nama_pemilik']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">No. Telepon</dt>
                        <dd class="font-semibold text-slate-800 mt-0.5"><?= htmlspecialchars($data['no_telepon'] ?? '-'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Barang Jaminan</dt>
                        <dd class="font-semibold text-slate-800 mt-0.5"><?= htmlspecialchars($data['barang_jaminan']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Keterangan</dt>
                        <dd class="text-slate-600 mt-0.5"><?= htmlspecialchars($data['keterangan'] ?? '-'); ?></dd>
                    </div>
                </dl>
            </div>

            <div class="pm-card p-5 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Nilai</h4>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Taksiran Harga</dt>
                        <dd class="font-bold text-slate-800">Rp <?= number_format($data['taksiran_harga'], 0, ',', '.'); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Nilai Pinjaman</dt>
                        <dd class="font-bold text-amber-600">Rp <?= number_format($data['nilai_pinjaman'], 0, ',', '.'); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Ujrah / Bulan</dt>
                        <dd class="font-bold text-slate-800">Rp <?= number_format($data['nilai_ujrah'], 0, ',', '.'); ?></dd>
                    </div>
                </dl>
            </div>

            <div class="pm-card p-5 lg:col-span-3 pm-fade pm-d3">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Jadwal Gadai</h4>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tanggal Gadai</dt>
                        <dd class="font-semibold text-slate-800 mt-0.5"><?= isset($data['tanggal_gadai']) ? date('d M Y', strtotime($data['tanggal_gadai'])) : '-'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Masa Gadai</dt>
                        <dd class="font-semibold text-slate-800 mt-0.5"><?= (int) $data['masa_gadai_hari']; ?> hari</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Jatuh Tempo</dt>
                        <dd class="font-semibold <?= isset($data['tanggal_jatuh_tempo']) && strtotime($data['tanggal_jatuh_tempo']) < strtotime('today') ? 'text-rose-600' : 'text-slate-800'; ?> mt-0.5"><?= isset($data['tanggal_jatuh_tempo']) ? date('d M Y', strtotime($data['tanggal_jatuh_tempo'])) : '-'; ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tanggal Input</dt>
                        <dd class="font-semibold text-slate-800 mt-0.5"><?= date('d M Y H:i', strtotime($data['created_at'])); ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="pm-card overflow-hidden pm-fade pm-d1">
                <div class="px-5 pt-5 pb-3">
                    <h4 class="text-sm font-bold text-slate-800">Foto Barang</h4>
                </div>
                <div class="px-5 pb-5">
                    <?php if ($foto && mysqli_num_rows($foto) > 0): ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <?php while ($f = mysqli_fetch_assoc($foto)): ?>
                                <a href="../../uploads/pawning/<?= htmlspecialchars($f['nama_file']); ?>" target="_blank" class="block rounded-xl overflow-hidden border border-slate-100 hover:opacity-90 transition">
                                    <img src="../../uploads/pawning/<?= htmlspecialchars($f['nama_file']); ?>" alt="Foto barang" class="w-full h-24 object-cover" onerror="this.outerHTML='<div class=&quot;w-full h-24 bg-slate-100 flex items-center justify-center text-slate-300&quot;>Tidak ada foto</div>';">
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 italic">Belum ada foto. <a href="pawning-foto.php?id=<?= $id; ?>" class="text-amber-600 font-medium not-italic">Upload foto</a></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pm-card overflow-hidden pm-fade pm-d2">
                <div class="px-5 pt-5 pb-3 flex items-center justify-between">
                    <h4 class="text-sm font-bold text-slate-800">Riwayat Perpanjangan</h4>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table">
                        <thead>
                            <tr>
                                <th>+Hari</th>
                                <th>Jatuh Tempo Baru</th>
                                <th>Ujrah Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($perpanj && mysqli_num_rows($perpanj) > 0): ?>
                                <?php while ($p = mysqli_fetch_assoc($perpanj)): ?>
                                    <tr>
                                        <td>+<?= (int) $p['perpanjangan_hari']; ?> hari</td>
                                        <td class="font-semibold text-slate-800 whitespace-nowrap"><?= date('d M Y', strtotime($p['jatuh_tempo_baru'])); ?></td>
                                        <td>Rp <?= number_format($p['ujrah_bayar'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="py-6 text-center text-xs text-slate-400 italic">Tidak ada perpanjangan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pm-card overflow-hidden pm-fade pm-d3">
                <div class="px-5 pt-5 pb-3">
                    <h4 class="text-sm font-bold text-slate-800">Riwayat Pelunasan / Penebusan</h4>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table">
                        <thead>
                            <tr>
                                <th>Tipe</th>
                                <th>Pokok</th>
                                <th>Ujrah</th>
                                <th>Total</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pelunasan && mysqli_num_rows($pelunasan) > 0): ?>
                                <?php while ($l = mysqli_fetch_assoc($pelunasan)): ?>
                                    <tr>
                                        <td><span class="pm-badge bg-slate-100 text-slate-600"><?= htmlspecialchars($l['tipe']); ?></span></td>
                                        <td>Rp <?= number_format($l['pokok'], 0, ',', '.'); ?></td>
                                        <td>Rp <?= number_format($l['ujrah'], 0, ',', '.'); ?></td>
                                        <td class="font-bold text-emerald-600 whitespace-nowrap">Rp <?= number_format($l['total_bayar'], 0, ',', '.'); ?></td>
                                        <td class="text-xs text-slate-400 whitespace-nowrap"><?= date('d M Y', strtotime($l['created_at'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="py-6 text-center text-xs text-slate-400 italic">Belum ada pelunasan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pm-card overflow-hidden pm-fade pm-d4">
                <div class="px-5 pt-5 pb-3">
                    <h4 class="text-sm font-bold text-slate-800">Riwayat Lelang &amp; Taksiran</h4>
                </div>
                <div class="px-5 pb-5 space-y-3">
                    <?php if ($lelang && mysqli_num_rows($lelang) > 0): ?>
                        <?php while ($l = mysqli_fetch_assoc($lelang)): ?>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-rose-50/60 border border-rose-100 text-sm">
                                <span class="text-slate-600">Lelang <b class="text-slate-800"><?= htmlspecialchars($l['pemenang'] ?? '-'); ?></b></span>
                                <span class="font-bold text-rose-600">Rp <?= number_format($l['harga_terjual'], 0, ',', '.'); ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <?php if ($taksiran && mysqli_num_rows($taksiran) > 0): ?>
                        <?php while ($t = mysqli_fetch_assoc($taksiran)): ?>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50/60 border border-amber-100 text-sm">
                                <span class="text-slate-600">Taksiran baru</span>
                                <span class="font-bold text-amber-600">Rp <?= number_format($t['taksiran_baru'], 0, ',', '.'); ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    <?php if ((!$lelang || mysqli_num_rows($lelang) === 0) && (!$taksiran || mysqli_num_rows($taksiran) === 0)): ?>
                        <p class="text-xs text-slate-400 italic">Belum ada riwayat lelang maupun perubahan taksiran.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
      </div>
    </main>
</body>
</html>
