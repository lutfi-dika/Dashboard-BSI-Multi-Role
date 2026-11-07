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
if (!in_array($role_user, ['admin', 'pawning', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role_user === 'atasan') {
    header('Location: pawning-lelang.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$pesanTipe = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    $idGadai     = (int) ($_POST['id_gadai'] ?? 0);
    $tanggal     = trim($_POST['tanggal_lelang'] ?? date('Y-m-d'));
    $harga       = (float) ($_POST['harga_terjual'] ?? 0);
    $pemenang    = trim($_POST['pemenang'] ?? '');
    $keterangan  = trim($_POST['keterangan'] ?? '');

    if ($idGadai <= 0 || $harga <= 0) {
        $pesan = 'Pilih data gadai dan masukkan harga terjual yang valid.';
        $pesanTipe = 'error';
    } else {
        $user = (int) $_SESSION['user_id'];
        mysqli_begin_transaction($conn);

        $ins = mysqli_prepare($conn, "INSERT INTO pawning_lelang (pawning_id, tanggal_lelang, harga_terjual, pemenang, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, "isdssi", $idGadai, $tanggal, $harga, $pemenang, $keterangan, $user);
        $ok1 = mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);

        $upd = mysqli_prepare($conn, "UPDATE pawning SET status = 'Lelang', updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($upd, "i", $idGadai);
        $ok2 = mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        if ($ok1 && $ok2) {
            mysqli_commit($conn);
            $pesan = 'Data lelang berhasil disimpan.';
            $pesanTipe = 'success';
        } else {
            mysqli_rollback($conn);
            $pesan = 'Terjadi kesalahan saat menyimpan lelang.';
            $pesanTipe = 'error';
        }
    }
}

// Kandidat lelang: sudah berstatus Lelang, atau aktif tapi lewat jatuh tempo
$kandidat = mysqli_query($conn, "
    SELECT * FROM pawning
    WHERE status = 'Lelang' OR (status = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND tanggal_jatuh_tempo < CURDATE())
    ORDER BY CASE status WHEN 'Lelang' THEN 1 ELSE 2 END, tanggal_jatuh_tempo ASC
");
// Simpan data kandidat ke array agar bisa digunakan dua kali (dropdown + tabel)
$kandidatList = [];
if ($kandidat) {
    while ($k = mysqli_fetch_assoc($kandidat)) {
        $kandidatList[] = $k;
    }
    mysqli_data_seek($kandidat, 0);
}
$riwayat  = mysqli_query($conn, "SELECT l.*, p.nama_pemilik, p.barang_jaminan, p.nilai_pinjaman, p.taksiran_harga FROM pawning_lelang l JOIN pawning p ON p.id = l.pawning_id ORDER BY l.created_at DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Lelang Barang Gadai | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Lelang Barang Gadai',
            'subtitle' => 'Kelola lelang barang gadai yang jatuh tempo / gagal bayar.',
            'stats'    => [
                ['label' => 'Kandidat Lelang', 'value' => number_format(count($kandidatList))],
            ],
        ]); ?>

        <?php if ($pesan): ?>
            <div class="pm-card px-5 py-3.5 mb-5 text-xs sm:text-sm <?= $pesanTipe === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="pm-card p-5 pm-fade pm-d1">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Form Lelang</h4>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Barang Gadai (Lelang / Lewat JT)</label>
                        <select name="id_gadai" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach ($kandidatList as $p): ?>
                                <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['nama_pemilik']); ?> — <?= htmlspecialchars($p['barang_jaminan']); ?> (Rp <?= number_format($p['nilai_pinjaman'], 0, ',', '.'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-slate-500 mb-1 block">Tanggal Lelang</label>
                            <input type="date" name="tanggal_lelang" value="<?= date('Y-m-d'); ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-500 mb-1 block">Harga Terjual (Rp)</label>
                            <input type="number" name="harga_terjual" min="1" step="1000" required placeholder="0" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Nama Pemenang</label>
                        <input type="text" name="pemenang" placeholder="cth. Budi (umum)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Keterangan</label>
                        <textarea name="keterangan" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"></textarea>
                    </div>
                    <button type="submit" class="pm-btn pm-btn-primary w-full">Simpan Hasil Lelang</button>
                </form>
            </div>

            <div class="pm-card p-5 lg:col-span-2 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Kandidat Lelang</h4>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[640px]">
                        <thead>
                            <tr>
                                <th>Nasabah</th>
                                <th>Barang</th>
                                <th>Pinjaman</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($kandidatList)): ?>
                                <?php foreach ($kandidatList as $p): ?>
                                    <tr>
                                        <td class="font-semibold text-slate-800"><?= htmlspecialchars($p['nama_pemilik']); ?></td>
                                        <td><?= htmlspecialchars($p['barang_jaminan']); ?></td>
                                        <td>Rp <?= number_format($p['nilai_pinjaman'], 0, ',', '.'); ?></td>
                                        <td class="text-xs text-slate-500 whitespace-nowrap"><?= isset($p['tanggal_jatuh_tempo']) ? date('d M Y', strtotime($p['tanggal_jatuh_tempo'])) : '-'; ?></td>
                                        <td>
                                            <span class="pm-badge <?= $p['status'] === 'Lelang' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-700'; ?>"><?= htmlspecialchars($p['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="py-8 text-center text-xs text-slate-400 italic">Tidak ada kandidat lelang.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="pm-card mt-5 overflow-hidden pm-fade pm-d3">
            <div class="px-5 pt-5 pb-3">
                <h4 class="text-sm font-bold text-slate-800">Riwayat Lelang</h4>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Nasabah</th>
                            <th>Barang</th>
                            <th>Tanggal</th>
                            <th>Pemenang</th>
                            <th>Harga Terjual</th>
                            <th>Pinjaman</th>
                            <th>Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat && mysqli_num_rows($riwayat) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($riwayat)): $selisih = $r['harga_terjual'] - $r['nilai_pinjaman']; ?>
                                <tr>
                                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($r['nama_pemilik']); ?></td>
                                    <td><?= htmlspecialchars($r['barang_jaminan']); ?></td>
                                    <td class="text-xs text-slate-500 whitespace-nowrap"><?= isset($r['tanggal_lelang']) ? date('d M Y', strtotime($r['tanggal_lelang'])) : '-'; ?></td>
                                    <td><?= htmlspecialchars($r['pemenang'] ?? '-'); ?></td>
                                    <td class="font-bold text-rose-600 whitespace-nowrap">Rp <?= number_format($r['harga_terjual'], 0, ',', '.'); ?></td>
                                    <td class="text-slate-500 whitespace-nowrap">Rp <?= number_format($r['nilai_pinjaman'], 0, ',', '.'); ?></td>
                                    <td class="font-semibold whitespace-nowrap <?= $selisih >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= $selisih >= 0 ? '+' : ''; ?>Rp <?= number_format($selisih, 0, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="py-8 text-center text-xs text-slate-400 italic">Belum ada riwayat lelang.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </main>
</body>
</html>
