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
$user_id   = (int) ($_SESSION['user_id'] ?? 0);

if (!in_array($role_user, ['admin', 'konsumer', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekTabel = mysqli_query($conn, "SHOW TABLES LIKE 'target_konsumer'");
$skemaLengkap = $cekTabel && mysqli_num_rows($cekTabel) > 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$bulan = (int) ($_GET['bulan'] ?? date('n'));
$tahun = (int) ($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int) date('n');

$pesan = '';

// Hanya admin yang boleh menetapkan target petugas
if ($skemaLengkap && $_SERVER['REQUEST_METHOD'] === 'POST' && $role_user === 'admin') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }
    $targetUser    = (int) ($_POST['user_id'] ?? 0);
    $targetBulan   = (int) ($_POST['bulan'] ?? $bulan);
    $targetTahun   = (int) ($_POST['tahun'] ?? $tahun);
    $targetJumlah  = (int) ($_POST['target_pengajuan'] ?? 0);
    $targetNominal = (float) str_replace(['.', ','], ['', '.'], $_POST['target_nominal'] ?? '0');

    if ($targetUser > 0 && $targetBulan >= 1 && $targetBulan <= 12) {
        $stmt = mysqli_prepare($conn, "INSERT INTO target_konsumer (user_id, bulan, tahun, target_pengajuan, target_nominal)
                                        VALUES (?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE target_pengajuan = VALUES(target_pengajuan), target_nominal = VALUES(target_nominal)");
        mysqli_stmt_bind_param($stmt, "iiiid", $targetUser, $targetBulan, $targetTahun, $targetJumlah, $targetNominal);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $pesan = 'Target berhasil disimpan.';
        $bulan = $targetBulan;
        $tahun = $targetTahun;
    }
}

$cekKolomPlafon = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_konsumer LIKE 'plafon_pengajuan'");
$adaKolomPlafon = $cekKolomPlafon && mysqli_num_rows($cekKolomPlafon) > 0;
$kolomNominal   = $adaKolomPlafon ? 'k.plafon_pengajuan' : 'k.pendapatan_bulanan';

$dataPerPetugas = [];
if ($skemaLengkap) {
    $sql = "SELECT u.id AS user_id, u.nama_lengkap AS nama_petugas,
                   COALESCE(t.target_pengajuan, 0) AS target_pengajuan,
                   COALESCE(t.target_nominal, 0) AS target_nominal,
                   COALESCE(r.realisasi_jumlah, 0) AS realisasi_jumlah,
                   COALESCE(r.realisasi_nominal, 0) AS realisasi_nominal
            FROM users u
            LEFT JOIN target_konsumer t ON t.user_id = u.id AND t.bulan = ? AND t.tahun = ?
            LEFT JOIN (
                SELECT k.user_id, COUNT(*) AS realisasi_jumlah, SUM($kolomNominal) AS realisasi_nominal
                FROM nasabah_konsumer k
                WHERE k.status = 'disetujui' AND MONTH(k.created_at) = ? AND YEAR(k.created_at) = ?
                GROUP BY k.user_id
            ) r ON r.user_id = u.id
            WHERE u.role IN ('konsumer', 'admin')
            ORDER BY realisasi_nominal DESC, u.nama_lengkap ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $bulan, $tahun, $bulan, $tahun);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $dataPerPetugas[] = $row;
    }
}

$daftarUser = [];
if ($role_user === 'admin') {
    $resUser = mysqli_query($conn, "SELECT id, nama_lengkap FROM users WHERE role IN ('konsumer','admin') ORDER BY nama_lengkap ASC");
    if ($resUser) {
        while ($row = mysqli_fetch_assoc($resUser)) {
            $daftarUser[] = $row;
        }
    }
}

$namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Target vs Realisasi | BSI Konsumer', 'konsumer'); ?>
</head>

<body class="text-slate-700 min-h-screen flex">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
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
            'title'    => 'Target vs Realisasi',
            'subtitle' => 'Pencapaian target pengajuan & nominal pembiayaan per petugas.',
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-700">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/005_konsumer_tahap2_lanjutan.sql</code> agar modul target tersedia.
            </div>
        <?php else: ?>

            <?php if ($pesan !== ''): ?>
                <div class="mb-5 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-medium"><?= htmlspecialchars($pesan); ?></div>
            <?php endif; ?>

            <?php if (!$adaKolomPlafon): ?>
                <div class="pm-card border-sky-200 bg-sky-50 px-5 py-3.5 mb-5 text-xs text-sky-700">
                    Kolom plafon pengajuan belum tersedia, realisasi nominal sementara memakai jumlah pendapatan pemohon.
                </div>
            <?php endif; ?>

            <form method="GET" class="pm-card p-4 mb-6 flex flex-wrap items-center gap-3 pm-fade pm-d1">
                <select name="bulan" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <?php for ($b = 1; $b <= 12; $b++): ?>
                        <option value="<?= $b; ?>" <?= $b === $bulan ? 'selected' : ''; ?>><?= $namaBulan[$b]; ?></option>
                    <?php endfor; ?>
                </select>
                <select name="tahun" class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400">
                    <?php for ($t = (int) date('Y'); $t >= (int) date('Y') - 3; $t--): ?>
                        <option value="<?= $t; ?>" <?= $t === $tahun ? 'selected' : ''; ?>><?= $t; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="pm-btn pm-btn-primary">Tampilkan</button>
            </form>

            <?php if ($role_user === 'admin'): ?>
                <div class="pm-card p-5 mb-6 pm-fade pm-d2">
                    <h4 class="text-sm font-bold text-slate-900 mb-3">Tetapkan Target &mdash; <?= $namaBulan[$bulan]; ?> <?= $tahun; ?></h4>
                    <form method="POST" class="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="bulan" value="<?= $bulan; ?>">
                        <input type="hidden" name="tahun" value="<?= $tahun; ?>">
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1">Petugas</label>
                            <select name="user_id" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-400 min-w-[180px]">
                                <?php foreach ($daftarUser as $u): ?>
                                    <option value="<?= (int) $u['id']; ?>"><?= htmlspecialchars($u['nama_lengkap']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1">Target Pengajuan</label>
                            <input type="number" name="target_pengajuan" min="0" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 w-32 focus:outline-none focus:ring-2 focus:ring-sky-400">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-500 mb-1">Target Nominal (Rp)</label>
                            <input type="number" name="target_nominal" min="0" step="1000" required class="bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-700 w-40 focus:outline-none focus:ring-2 focus:ring-sky-400">
                        </div>
                        <button type="submit" class="pm-btn pm-btn-primary">Simpan Target</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="pm-card overflow-hidden pm-fade pm-d3">
                <div class="px-4 sm:px-6 pt-5 pb-3">
                    <h4 class="text-sm font-bold text-slate-900">Pencapaian &mdash; <?= $namaBulan[$bulan]; ?> <?= $tahun; ?></h4>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[760px]">
                        <thead>
                            <tr>
                                <th>Petugas</th>
                                <th>Target Pengajuan</th>
                                <th>Realisasi</th>
                                <th>Target Nominal</th>
                                <th>Realisasi Nominal</th>
                                <th class="!text-right">Capaian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dataPerPetugas)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-xs text-slate-500 italic">Belum ada data petugas.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dataPerPetugas as $d):
                                    $capaian = ((float) $d['target_nominal'] > 0)
                                        ? round(((float) $d['realisasi_nominal'] / (float) $d['target_nominal']) * 100)
                                        : (((int) $d['realisasi_jumlah'] > 0) ? 100 : 0);
                                    $warnaCapaian = $capaian >= 100 ? 'text-emerald-500' : ($capaian >= 60 ? 'text-amber-500' : 'text-rose-500');
                                ?>
                                    <tr <?= $d['user_id'] === $user_id ? 'class="bg-sky-50/60"' : ''; ?>>
                                        <td class="font-semibold text-slate-900"><?= htmlspecialchars($d['nama_petugas']); ?></td>
                                        <td class="text-slate-600"><?= (int) $d['target_pengajuan']; ?></td>
                                        <td class="text-slate-600"><?= (int) $d['realisasi_jumlah']; ?></td>
                                        <td class="text-slate-600">Rp<?= number_format((float) $d['target_nominal'], 0, ',', '.'); ?></td>
                                        <td class="text-slate-600">Rp<?= number_format((float) $d['realisasi_nominal'], 0, ',', '.'); ?></td>
                                        <td class="!text-right font-bold <?= $warnaCapaian; ?>"><?= $capaian; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
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