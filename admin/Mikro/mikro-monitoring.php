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
$current_page = 'monitoring';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekTabel = mysqli_query($conn, "SHOW TABLES LIKE 'angsuran_mikro'");
$skemaLengkap = $cekTabel && mysqli_num_rows($cekTabel) > 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';

if ($skemaLengkap && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $role_user !== 'atasan') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    // Generate jadwal angsuran untuk sebuah pengajuan yang sudah Aktif
    if (isset($_POST['generate_id'])) {
        $mikroId = (int) $_POST['generate_id'];

        $stmtCek = mysqli_prepare($conn, "SELECT id, plafon_pembiayaan, tenor_bulan, created_at, status_pengajuan FROM nasabah_mikro WHERE id = ?");
        mysqli_stmt_bind_param($stmtCek, "i", $mikroId);
        mysqli_stmt_execute($stmtCek);
        $nasabah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCek));
        mysqli_stmt_close($stmtCek);

        if (!$nasabah || $nasabah['status_pengajuan'] !== 'Aktif') {
            $pesan = 'Nasabah tidak ditemukan atau belum berstatus Aktif.';
        } elseif ((float) $nasabah['plafon_pembiayaan'] <= 0 || (int) $nasabah['tenor_bulan'] <= 0) {
            $pesan = 'Plafon pembiayaan / tenor belum diisi untuk nasabah ini.';
        } else {
            $stmtAda = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM angsuran_mikro WHERE mikro_id = ?");
            mysqli_stmt_bind_param($stmtAda, "i", $mikroId);
            mysqli_stmt_execute($stmtAda);
            $sudahAda = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAda))['total'] ?? 0);
            mysqli_stmt_close($stmtAda);

            if ($sudahAda > 0) {
                $pesan = 'Jadwal angsuran untuk nasabah ini sudah pernah dibuat.';
            } else {
                $tenor = (int) $nasabah['tenor_bulan'];
                $nominalPerBulan = round(((float) $nasabah['plafon_pembiayaan']) / $tenor, 2);
                $mulai = $nasabah['created_at'] ?: date('Y-m-d');

                $stmtIns = mysqli_prepare($conn, "INSERT INTO angsuran_mikro (mikro_id, angsuran_ke, tanggal_jatuh_tempo, nominal) VALUES (?, ?, ?, ?)");
                for ($ke = 1; $ke <= $tenor; $ke++) {
                    $tgl = date('Y-m-d', strtotime($mulai . " +{$ke} months"));
                    mysqli_stmt_bind_param($stmtIns, "iisd", $mikroId, $ke, $tgl, $nominalPerBulan);
                    mysqli_stmt_execute($stmtIns);
                }
                mysqli_stmt_close($stmtIns);
                $pesan = "Jadwal {$tenor}x angsuran berhasil dibuat.";
            }
        }
    }

    // Tandai satu angsuran sebagai lunas
    if (isset($_POST['bayar_id'])) {
        $angsuranId = (int) $_POST['bayar_id'];
        $stmt = mysqli_prepare($conn, "UPDATE angsuran_mikro SET status = 'lunas', tanggal_bayar = CURDATE() WHERE id = ? AND status != 'lunas'");
        mysqli_stmt_bind_param($stmt, "i", $angsuranId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $pesan = 'Angsuran ditandai lunas.';
    }
}

// Perbarui otomatis angsuran yang lewat jatuh tempo tapi belum dibayar -> terlambat
if ($skemaLengkap) {
    mysqli_query($conn, "UPDATE angsuran_mikro SET status = 'terlambat' WHERE status = 'belum_bayar' AND tanggal_jatuh_tempo < CURDATE()");
}

$filterStatus = trim($_GET['status'] ?? '');
$daftarAngsuran = [];
$belumPunyaJadwal = [];
$ringkasan = ['belum_bayar' => 0, 'lunas' => 0, 'terlambat' => 0];

if ($skemaLengkap) {
    $where = '1=1';
    $types = '';
    $params = [];
    if (in_array($filterStatus, ['belum_bayar', 'lunas', 'terlambat'], true)) {
        $where = 'a.status = ?';
        $types = 's';
        $params[] = $filterStatus;
    }
    $sql = "SELECT a.id, a.angsuran_ke, a.tanggal_jatuh_tempo, a.nominal, a.status, a.tanggal_bayar,
                   m.id AS mikro_id, m.nama_pemilik, m.nama_usaha
            FROM angsuran_mikro a
            JOIN nasabah_mikro m ON m.id = a.mikro_id
            WHERE $where
            ORDER BY a.tanggal_jatuh_tempo ASC
            LIMIT 200";
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $daftarAngsuran[] = $row;
    }
    mysqli_stmt_close($stmt);

    $resBelum = mysqli_query($conn, "SELECT m.id, m.nama_pemilik, m.nama_usaha, m.plafon_pembiayaan, m.tenor_bulan
                                      FROM nasabah_mikro m
                                      LEFT JOIN angsuran_mikro a ON a.mikro_id = m.id
                                      WHERE m.status_pengajuan = 'Aktif' AND a.id IS NULL
                                      GROUP BY m.id
                                      ORDER BY m.created_at DESC
                                      LIMIT 20");
    if ($resBelum) {
        while ($row = mysqli_fetch_assoc($resBelum)) {
            $belumPunyaJadwal[] = $row;
        }
    }

    $resRingkasan = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM angsuran_mikro GROUP BY status");
    if ($resRingkasan) {
        while ($row = mysqli_fetch_assoc($resRingkasan)) {
            if (isset($ringkasan[$row['status']])) $ringkasan[$row['status']] = (int) $row['total'];
        }
    }
}

$statusBadge = [
    'belum_bayar' => 'bg-slate-100 text-slate-600',
    'lunas' => 'bg-emerald-50 text-emerald-600',
    'terlambat' => 'bg-rose-50 text-rose-600',
];
$statusLabel = ['belum_bayar' => 'Belum Bayar', 'lunas' => 'Lunas', 'terlambat' => 'Terlambat'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Monitoring Angsuran | BSI Mikro', 'mikro'); ?>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
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
            'title'    => 'Monitoring Angsuran',
            'subtitle' => 'Jadwal dan status pembayaran cicilan pembiayaan mikro.',
            'stats'    => [
                ['label' => 'Belum Bayar', 'value' => $ringkasan['belum_bayar']],
                ['label' => 'Terlambat', 'value' => $ringkasan['terlambat']],
                ['label' => 'Lunas', 'value' => $ringkasan['lunas']],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/006_mikro_survey_angsuran.sql</code> agar modul angsuran tersedia.
            </div>
        <?php else: ?>

            <?php if ($pesan !== ''): ?>
                <div class="mb-5 p-3 bg-sky-50 border border-sky-200 text-sky-700 rounded-xl text-xs font-medium"><?= htmlspecialchars($pesan); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <a href="?status=belum_bayar" class="pm-card pm-card-hover p-5 <?= $filterStatus === 'belum_bayar' ? 'ring-2 ring-slate-300' : ''; ?>">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Belum Bayar</p>
                    <p class="text-2xl font-bold text-slate-700"><?= $ringkasan['belum_bayar']; ?></p>
                </a>
                <a href="?status=terlambat" class="pm-card pm-card-hover p-5 <?= $filterStatus === 'terlambat' ? 'ring-2 ring-rose-300' : ''; ?>">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Terlambat</p>
                    <p class="text-2xl font-bold text-rose-600"><?= $ringkasan['terlambat']; ?></p>
                </a>
                <a href="?status=lunas" class="pm-card pm-card-hover p-5 <?= $filterStatus === 'lunas' ? 'ring-2 ring-emerald-300' : ''; ?>">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Lunas</p>
                    <p class="text-2xl font-bold text-emerald-600"><?= $ringkasan['lunas']; ?></p>
                </a>
            </div>

            <?php if (!empty($belumPunyaJadwal) && $role_user !== 'atasan'): ?>
                <div class="pm-card p-5 mb-6 pm-fade pm-d1">
                    <h4 class="text-sm font-bold text-slate-800 mb-1">Belum Punya Jadwal Angsuran</h4>
                    <p class="text-xs text-slate-500 mb-4">Nasabah berstatus Aktif yang belum digenerate jadwal cicilannya.</p>
                    <div class="space-y-2">
                        <?php foreach ($belumPunyaJadwal as $b): ?>
                            <form method="POST" class="flex items-center justify-between gap-3 bg-slate-50/70 rounded-xl px-4 py-2.5">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="generate_id" value="<?= (int) $b['id']; ?>">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-800 truncate"><?= htmlspecialchars($b['nama_pemilik']); ?> &middot; <?= htmlspecialchars($b['nama_usaha']); ?></p>
                                    <p class="text-[11px] text-slate-400">Plafon Rp <?= number_format((float) $b['plafon_pembiayaan'], 0, ',', '.'); ?> &middot; Tenor <?= (int) $b['tenor_bulan']; ?> bln</p>
                                </div>
                                <button type="submit" class="pm-btn pm-btn-primary shrink-0">Buat Jadwal</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="pm-card overflow-hidden pm-fade pm-d2">
                <div class="px-4 sm:px-6 pt-6 pb-4 flex items-center justify-between">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Jadwal Angsuran <?= $filterStatus !== '' ? '&middot; ' . $statusLabel[$filterStatus] : ''; ?></h4>
                    <?php if ($filterStatus !== ''): ?>
                        <a href="mikro-monitoring.php" class="text-[11px] font-semibold text-indigo-600">Tampilkan semua</a>
                    <?php endif; ?>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[640px]">
                        <thead>
                            <tr>
                                <th>Nasabah</th>
                                <th>Angsuran Ke</th>
                                <th>Jatuh Tempo</th>
                                <th>Nominal</th>
                                <th>Status</th>
                                <th class="!text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftarAngsuran as $a): ?>
                                <tr>
                                    <td class="font-semibold !text-slate-800"><?= htmlspecialchars($a['nama_pemilik']); ?><br><span class="text-[11px] font-normal text-slate-400"><?= htmlspecialchars($a['nama_usaha']); ?></span></td>
                                    <td>Ke-<?= (int) $a['angsuran_ke']; ?></td>
                                    <td><?= date('d M Y', strtotime($a['tanggal_jatuh_tempo'])); ?></td>
                                    <td class="font-medium">Rp <?= number_format((float) $a['nominal'], 0, ',', '.'); ?></td>
                                    <td><span class="text-[11px] font-semibold px-2.5 py-1 rounded-full <?= $statusBadge[$a['status']]; ?>"><?= $statusLabel[$a['status']]; ?></span></td>
                                    <td class="!text-right">
                                        <?php if ($a['status'] !== 'lunas' && $role_user !== 'atasan'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="bayar_id" value="<?= (int) $a['id']; ?>">
                                                <button type="submit" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700">Tandai Lunas</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-300">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($daftarAngsuran)): ?>
                                <tr><td colspan="6" class="px-6 py-8 text-center text-xs text-slate-400 italic">Belum ada jadwal angsuran untuk filter ini.</td></tr>
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
