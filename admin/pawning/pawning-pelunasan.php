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
    header('Location: pawning-pelunasan.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function settingPawning2(mysqli $conn, string $key, string $default): string
{
    $stmt = $conn->prepare("SELECT nilai_setting FROM pawning_pengaturan WHERE nama_setting = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? $r['nilai_setting'] : $default;
}

$tarifUjrah   = (float) settingPawning2($conn, 'tarif_ujrah_bulan', '1.5');
$dendaPersen  = (float) settingPawning2($conn, 'denda_terlambat_persen', '0.1');

function hitungBiaya(float $pinjaman, float $tarif, float $dendaPersen, string $tglGadai, ?string $tglJT): array
{
    $days  = max(1, (int) floor((strtotime('today') - strtotime($tglGadai)) / 86400));
    $ujrah = round(($pinjaman * $tarif / 100) * ($days / 30), 2);
    $denda = 0.0;
    if ($tglJT && strtotime($tglJT) < strtotime('today')) {
        $overdue = (int) floor((strtotime('today') - strtotime($tglJT)) / 86400);
        $denda   = round(($pinjaman * $dendaPersen / 100) * ($overdue / 30), 2);
    }
    return ['days' => $days, 'ujrah' => $ujrah, 'denda' => $denda, 'total' => round($pinjaman + $ujrah + $denda, 2)];
}

$pesan = '';
$pesanTipe = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    $idGadai = (int) ($_POST['id_gadai'] ?? 0);
    $metode  = trim($_POST['metode_bayar'] ?? '');

    if ($idGadai <= 0) {
        $pesan = 'Pilih data gadai terlebih dahulu.';
        $pesanTipe = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM pawning WHERE id = ? AND status = 'Aktif'");
        mysqli_stmt_bind_param($stmt, "i", $idGadai);
        mysqli_stmt_execute($stmt);
        $gadai = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$gadai) {
            $pesan = 'Data gadai tidak ditemukan atau sudah tidak berstatus Aktif.';
            $pesanTipe = 'error';
        } else {
            $biaya = hitungBiaya((float) $gadai['nilai_pinjaman'], $tarifUjrah, $dendaPersen, $gadai['tanggal_gadai'], $gadai['tanggal_jatuh_tempo'] ?? null);
            $user  = (int) $_SESSION['user_id'];

            mysqli_begin_transaction($conn);
            $ins = mysqli_prepare($conn, "INSERT INTO pawning_pelunasan (pawning_id, tipe, pokok, ujrah, denda, total_bayar, metode_bayar, user_id) VALUES (?, 'Pelunasan', ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, "idddssi", $idGadai, $gadai['nilai_pinjaman'], $biaya['ujrah'], $biaya['denda'], $biaya['total'], $metode, $user);
            $ok1 = mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            $upd = mysqli_prepare($conn, "UPDATE pawning SET status = 'Lunas', updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, "i", $idGadai);
            $ok2 = mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            if ($ok1 && $ok2) {
                mysqli_commit($conn);
                $pesan = 'Pelunasan berhasil. Total pembayaran: Rp ' . number_format($biaya['total'], 0, ',', '.') . ' (pokok + ujrah + denda).';
                $pesanTipe = 'success';
            } else {
                mysqli_rollback($conn);
                $pesan = 'Terjadi kesalahan saat memproses pelunasan.';
                $pesanTipe = 'error';
            }
        }
    }
}

$daftarAktif = mysqli_query($conn, "SELECT * FROM pawning WHERE status = 'Aktif' ORDER BY nama_pemilik ASC");
$riwayat     = mysqli_query($conn, "SELECT l.*, p.nama_pemilik, p.barang_jaminan FROM pawning_pelunasan l JOIN pawning p ON p.id = l.pawning_id ORDER BY l.created_at DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Pelunasan Gadai | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Pelunasan Gadai',
            'subtitle' => 'Proses pelunasan gadai dengan perhitungan ujrah &amp; denda otomatis.',
            'stats'    => [
                ['label' => 'Tarif Ujrah/Bulan', 'value' => number_format($tarifUjrah, 1) . '%'],
                ['label' => 'Denda/Hari',        'value' => number_format($dendaPersen, 1) . '%'],
            ],
        ]); ?>

        <?php if ($pesan): ?>
            <div class="pm-card px-5 py-3.5 mb-5 text-xs sm:text-sm <?= $pesanTipe === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="pm-card p-5 pm-fade pm-d1">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Form Pelunasan</h4>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Data Gadai Aktif</label>
                        <select name="id_gadai" id="selectGadai" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                            <option value="">-- Pilih Nasabah --</option>
                            <?php if ($daftarAktif): while ($p = mysqli_fetch_assoc($daftarAktif)): ?>
                                <option value="<?= $p['id']; ?>"
                                    data-pinjaman="<?= (float) $p['nilai_pinjaman']; ?>"
                                    data-gadai="<?= htmlspecialchars($p['tanggal_gadai'] ?? ''); ?>"
                                    data-jatuh="<?= htmlspecialchars($p['tanggal_jatuh_tempo'] ?? ''); ?>">
                                    <?= htmlspecialchars($p['nama_pemilik']); ?> — <?= htmlspecialchars($p['barang_jaminan']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Metode Pembayaran</label>
                        <select name="metode_bayar" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                            <option value="">-- Pilih --</option>
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Debit">Debit</option>
                            <option value="Saldo">Saldo Rekening</option>
                        </select>
                    </div>

                    <div class="rounded-xl bg-amber-50/60 border border-amber-100 p-4 text-sm space-y-2">
                        <div class="flex justify-between"><span class="text-slate-500">Pokok Pinjaman</span><b id="prevPokok" class="text-slate-800">Rp 0</b></div>
                        <div class="flex justify-between"><span class="text-slate-500">Ujrah</span><b id="prevUjrah" class="text-slate-800">Rp 0</b></div>
                        <div class="flex justify-between"><span class="text-slate-500">Denda</span><b id="prevDenda" class="text-slate-800">Rp 0</b></div>
                        <div class="flex justify-between border-t border-amber-100 pt-2"><span class="text-slate-600 font-semibold">Total Bayar</span><b id="prevTotal" class="text-amber-700">Rp 0</b></div>
                    </div>

                    <button type="submit" class="pm-btn pm-btn-primary w-full">Proses Pelunasan</button>
                </form>
            </div>

            <div class="pm-card p-5 lg:col-span-2 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Gadai Aktif</h4>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[600px]">
                        <thead>
                            <tr>
                                <th>Nasabah</th>
                                <th>Barang</th>
                                <th>Pinjaman</th>
                                <th>Jatuh Tempo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($daftarAktif && mysqli_num_rows($daftarAktif) > 0): ?>
                                <?php while ($p = mysqli_fetch_assoc($daftarAktif)): ?>
                                    <tr>
                                        <td class="font-semibold text-slate-800"><?= htmlspecialchars($p['nama_pemilik']); ?></td>
                                        <td><?= htmlspecialchars($p['barang_jaminan']); ?></td>
                                        <td>Rp <?= number_format($p['nilai_pinjaman'], 0, ',', '.'); ?></td>
                                        <td class="text-xs text-slate-500 whitespace-nowrap"><?= isset($p['tanggal_jatuh_tempo']) ? date('d M Y', strtotime($p['tanggal_jatuh_tempo'])) : '-'; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="py-8 text-center text-xs text-slate-400 italic">Tidak ada gadai berstatus Aktif.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="pm-card mt-5 overflow-hidden pm-fade pm-d3">
            <div class="px-5 pt-5 pb-3">
                <h4 class="text-sm font-bold text-slate-800">Riwayat Pelunasan</h4>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>Nasabah</th>
                            <th>Tipe</th>
                            <th>Pokok</th>
                            <th>Ujrah</th>
                            <th>Denda</th>
                            <th>Total</th>
                            <th>Metode</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat && mysqli_num_rows($riwayat) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($riwayat)): ?>
                                <tr>
                                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($r['nama_pemilik']); ?></td>
                                    <td><span class="pm-badge bg-slate-100 text-slate-600"><?= htmlspecialchars($r['tipe']); ?></span></td>
                                    <td>Rp <?= number_format($r['pokok'], 0, ',', '.'); ?></td>
                                    <td>Rp <?= number_format($r['ujrah'], 0, ',', '.'); ?></td>
                                    <td>Rp <?= number_format($r['denda'], 0, ',', '.'); ?></td>
                                    <td class="font-bold text-emerald-600 whitespace-nowrap">Rp <?= number_format($r['total_bayar'], 0, ',', '.'); ?></td>
                                    <td class="text-slate-500"><?= htmlspecialchars($r['metode_bayar'] ?? '-'); ?></td>
                                    <td class="text-xs text-slate-400 whitespace-nowrap"><?= date('d M Y H:i', strtotime($r['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="py-8 text-center text-xs text-slate-400 italic">Belum ada riwayat pelunasan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
    </main>

    <script>
        const TARIF = <?= json_encode($tarifUjrah); ?>;
        const DENDA = <?= json_encode($dendaPersen); ?>;

        function fmtRp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

        document.getElementById('selectGadai').addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            const pokok = parseFloat(opt.dataset.pinjaman || 0);
            const tglGadai = opt.dataset.gadai;
            const tglJT = opt.dataset.jatuh;
            if (!opt.value) { reset(); return; }

            const days = Math.max(1, Math.floor((new Date() - new Date(tglGadai)) / 86400000));
            const ujrah = (pokok * TARIF / 100) * (days / 30);
            let denda = 0;
            if (tglJT && new Date(tglJT) < new Date()) {
                const overdue = Math.floor((new Date() - new Date(tglJT)) / 86400000);
                denda = (pokok * DENDA / 100) * (overdue / 30);
            }
            document.getElementById('prevPokok').textContent = fmtRp(pokok);
            document.getElementById('prevUjrah').textContent = fmtRp(ujrah);
            document.getElementById('prevDenda').textContent = fmtRp(denda);
            document.getElementById('prevTotal').textContent = fmtRp(pokok + ujrah + denda);
        });

        function reset() {
            ['prevPokok', 'prevUjrah', 'prevDenda', 'prevTotal'].forEach(id => document.getElementById(id).textContent = 'Rp 0');
        }
    </script>
</body>
</html>
