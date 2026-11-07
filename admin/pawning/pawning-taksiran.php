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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role_user === 'atasan') {
    header('Location: pawning-taksiran.php');
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

    $idGadai  = (int) ($_POST['id_gadai'] ?? 0);
    $taksiran = (float) ($_POST['taksiran_baru'] ?? 0);

    if ($idGadai <= 0 || $taksiran <= 0) {
        $pesan = 'Pilih data gadai dan masukkan nilai taksiran baru yang valid.';
        $pesanTipe = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT taksiran_harga FROM pawning WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $idGadai);
        mysqli_stmt_execute($stmt);
        $g = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$g) {
            $pesan = 'Data gadai tidak ditemukan.';
            $pesanTipe = 'error';
        } else {
            $lama  = (float) $g['taksiran_harga'];
            $user  = (int) $_SESSION['user_id'];

            mysqli_begin_transaction($conn);
            $ins = mysqli_prepare($conn, "INSERT INTO pawning_taksiran (pawning_id, taksiran_lama, taksiran_baru, user_id) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "iddi", $idGadai, $lama, $taksiran, $user);
            $ok1 = mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            $upd = mysqli_prepare($conn, "UPDATE pawning SET taksiran_harga = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, "di", $taksiran, $idGadai);
            $ok2 = mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            if ($ok1 && $ok2) {
                mysqli_commit($conn);
                $pesan = 'Nilai taksiran berhasil diperbarui dari Rp ' . number_format($lama, 0, ',', '.') . ' menjadi Rp ' . number_format($taksiran, 0, ',', '.') . '.';
                $pesanTipe = 'success';
            } else {
                mysqli_rollback($conn);
                $pesan = 'Terjadi kesalahan saat memperbarui taksiran.';
                $pesanTipe = 'error';
            }
        }
    }
}

$search = $_GET['search'] ?? '';
$where = "WHERE 1=1";
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, "%$search%");
    $where .= " AND (nama_pemilik LIKE '$s' OR barang_jaminan LIKE '$s')";
}

$daftar  = mysqli_query($conn, "SELECT id, nama_pemilik, barang_jaminan, taksiran_harga, nilai_pinjaman, status FROM pawning $where ORDER BY nama_pemilik ASC");
$riwayat = mysqli_query($conn, "SELECT t.*, p.nama_pemilik, p.barang_jaminan FROM pawning_taksiran t JOIN pawning p ON p.id = t.pawning_id ORDER BY t.created_at DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Nilai Taksiran Barang | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Nilai Taksiran Barang',
            'subtitle' => 'Kelola nilai taksiran barang gadai beserta riwayat perubahannya.',
        ]); ?>

        <?php if ($pesan): ?>
            <div class="pm-card px-5 py-3.5 mb-5 text-xs sm:text-sm <?= $pesanTipe === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="pm-card p-4 mb-5 pm-fade pm-d1">
            <form method="GET" class="flex flex-col md:flex-row gap-3">
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari nasabah atau barang..." class="flex-1 px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <button type="submit" class="pm-btn pm-btn-primary">Cari</button>
            </form>
        </div>

        <div class="pm-card overflow-hidden mb-5 pm-fade pm-d2">
            <div class="px-5 pt-5 pb-3">
                <h4 class="text-sm font-bold text-slate-800">Daftar Barang &amp; Taksiran Saat Ini</h4>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Nasabah</th>
                            <th>Barang</th>
                            <th>Taksiran</th>
                            <th>Pinjaman</th>
                            <th>Status</th>
                            <th>Update Taksiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($daftar && mysqli_num_rows($daftar) > 0): ?>
                            <?php while ($p = mysqli_fetch_assoc($daftar)): ?>
                                <tr>
                                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($p['nama_pemilik']); ?></td>
                                    <td><?= htmlspecialchars($p['barang_jaminan']); ?></td>
                                    <td class="font-semibold text-slate-800 whitespace-nowrap">Rp <?= number_format($p['taksiran_harga'], 0, ',', '.'); ?></td>
                                    <td>Rp <?= number_format($p['nilai_pinjaman'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="pm-badge <?= $p['status'] === 'Aktif' ? 'bg-emerald-50 text-emerald-600' : ($p['status'] === 'Lunas' ? 'bg-sky-50 text-sky-600' : 'bg-rose-50 text-rose-600'); ?>"><?= htmlspecialchars($p['status']); ?></span>
                                    </td>
                                    <td>
                                        <form method="POST" class="flex gap-2 items-center">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="id_gadai" value="<?= (int) $p['id']; ?>">
                                            <input type="number" name="taksiran_baru" min="1" step="1000" placeholder="Nilai baru" required class="w-32 px-2 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                            <button type="submit" class="pm-btn pm-btn-primary !px-3 !py-1.5 !text-[11px]">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="py-8 text-center text-xs text-slate-400 italic">Tidak ada data barang.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pm-card overflow-hidden pm-fade pm-d3">
            <div class="px-5 pt-5 pb-3">
                <h4 class="text-sm font-bold text-slate-800">Riwayat Perubahan Taksiran</h4>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Nasabah</th>
                            <th>Taksiran Lama</th>
                            <th>Taksiran Baru</th>
                            <th>Selisih</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat && mysqli_num_rows($riwayat) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($riwayat)): $selisih = $r['taksiran_baru'] - $r['taksiran_lama']; ?>
                                <tr>
                                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($r['nama_pemilik']); ?> <span class="text-slate-400 font-normal">— <?= htmlspecialchars($r['barang_jaminan']); ?></span></td>
                                    <td>Rp <?= number_format($r['taksiran_lama'], 0, ',', '.'); ?></td>
                                    <td class="font-semibold text-slate-800 whitespace-nowrap">Rp <?= number_format($r['taksiran_baru'], 0, ',', '.'); ?></td>
                                    <td class="font-semibold whitespace-nowrap <?= $selisih >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= $selisih >= 0 ? '+' : ''; ?>Rp <?= number_format($selisih, 0, ',', '.'); ?></td>
                                    <td class="text-xs text-slate-400 whitespace-nowrap"><?= date('d M Y H:i', strtotime($r['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="py-8 text-center text-xs text-slate-400 italic">Belum ada perubahan taksiran.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </main>
</body>
</html>
