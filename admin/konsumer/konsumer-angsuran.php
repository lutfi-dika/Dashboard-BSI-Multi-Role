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

if (!in_array($role_user, ['admin', 'konsumer', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekTabel = mysqli_query($conn, "SHOW TABLES LIKE 'angsuran_konsumer'");
$skemaLengkap = $cekTabel && mysqli_num_rows($cekTabel) > 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';

if ($skemaLengkap && $_SERVER['REQUEST_METHOD'] === 'POST' && $role_user !== 'atasan') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    // Generate jadwal angsuran untuk sebuah pengajuan yang sudah disetujui
    if (isset($_POST['generate_id'])) {
        $konsumerId = (int) $_POST['generate_id'];

        $stmtCek = mysqli_prepare($conn, "SELECT id, plafon_pengajuan, tenor_bulan, tanggal_pencairan, status
                                           FROM nasabah_konsumer WHERE id = ?");
        mysqli_stmt_bind_param($stmtCek, "i", $konsumerId);
        mysqli_stmt_execute($stmtCek);
        $nasabah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCek));
        mysqli_stmt_close($stmtCek);

        if (!$nasabah || $nasabah['status'] !== 'disetujui') {
            $pesan = 'Pengajuan tidak ditemukan atau belum berstatus disetujui.';
        } elseif ((float) $nasabah['plafon_pengajuan'] <= 0 || (int) $nasabah['tenor_bulan'] <= 0) {
            $pesan = 'Plafon pengajuan / tenor belum diisi untuk nasabah ini.';
        } else {
            $stmtAda = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM angsuran_konsumer WHERE konsumer_id = ?");
            mysqli_stmt_bind_param($stmtAda, "i", $konsumerId);
            mysqli_stmt_execute($stmtAda);
            $sudahAda = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAda))['total'] ?? 0);
            mysqli_stmt_close($stmtAda);

            if ($sudahAda > 0) {
                $pesan = 'Jadwal angsuran untuk nasabah ini sudah pernah dibuat.';
            } else {
                $tenor = (int) $nasabah['tenor_bulan'];
                $nominalPerBulan = round(((float) $nasabah['plafon_pengajuan']) / $tenor, 2);
                $mulai = $nasabah['tanggal_pencairan'] ?: date('Y-m-d');

                $stmtIns = mysqli_prepare($conn, "INSERT INTO angsuran_konsumer (konsumer_id, angsuran_ke, tanggal_jatuh_tempo, nominal) VALUES (?, ?, ?, ?)");
                for ($ke = 1; $ke <= $tenor; $ke++) {
                    $tgl = date('Y-m-d', strtotime($mulai . " +{$ke} months"));
                    mysqli_stmt_bind_param($stmtIns, "iisd", $konsumerId, $ke, $tgl, $nominalPerBulan);
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
        $stmt = mysqli_prepare($conn, "UPDATE angsuran_konsumer SET status = 'lunas', tanggal_bayar = CURDATE() WHERE id = ? AND status != 'lunas'");
        mysqli_stmt_bind_param($stmt, "i", $angsuranId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $pesan = 'Angsuran ditandai lunas.';
    }
}

// Perbarui otomatis angsuran yang lewat jatuh tempo tapi belum dibayar -> terlambat
if ($skemaLengkap) {
    mysqli_query($conn, "UPDATE angsuran_konsumer SET status = 'terlambat' WHERE status = 'belum_bayar' AND tanggal_jatuh_tempo < CURDATE()");
}

$filterStatus = trim($_GET['status'] ?? '');
$daftarAngsuran = [];
$belumPunyaJadwal = [];

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
                   k.id AS konsumer_id, k.nama_lengkap
            FROM angsuran_konsumer a
            JOIN nasabah_konsumer k ON k.id = a.konsumer_id
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

    $sqlBelum = "SELECT k.id, k.nama_lengkap, k.plafon_pengajuan, k.tenor_bulan
                 FROM nasabah_konsumer k
                 LEFT JOIN angsuran_konsumer a ON a.konsumer_id = k.id
                 WHERE k.status = 'disetujui' AND a.id IS NULL
                 GROUP BY k.id
                 ORDER BY k.updated_at DESC
                 LIMIT 20";
    $resBelum = mysqli_query($conn, $sqlBelum);
    if ($resBelum) {
        while ($row = mysqli_fetch_assoc($resBelum)) {
            $belumPunyaJadwal[] = $row;
        }
    }

    $ringkasan = ['belum_bayar' => 0, 'lunas' => 0, 'terlambat' => 0];
    $resRingkasan = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM angsuran_konsumer GROUP BY status");
    if ($resRingkasan) {
        while ($row = mysqli_fetch_assoc($resRingkasan)) {
            if (isset($ringkasan[$row['status']])) $ringkasan[$row['status']] = (int) $row['total'];
        }
    }
} else {
    $ringkasan = ['belum_bayar' => 0, 'lunas' => 0, 'terlambat' => 0];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Angsuran | BSI Konsumer', 'konsumer'); ?>
    <style>
        .row-fade-in {
            animation: fadeIn .25s ease both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="text-slate-700 min-h-screen flex bg-slate-50">

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
            'title'    => 'Angsuran',
            'subtitle' => 'Jadwal dan status pembayaran cicilan pembiayaan konsumer.',
            'stats'    => [
                ['label' => 'Belum Bayar', 'value' => number_format($ringkasan['belum_bayar'])],
                ['label' => 'Terlambat',   'value' => number_format($ringkasan['terlambat'])],
                ['label' => 'Lunas',       'value' => number_format($ringkasan['lunas'])],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-700 flex items-start gap-2.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span>Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/005_konsumer_tahap2_lanjutan.sql</code> agar modul angsuran tersedia.</span>
            </div>
        <?php else: ?>

            <?php if ($pesan !== ''): ?>
                <div class="mb-5 p-3 bg-sky-50 border border-sky-200 text-sky-700 rounded-xl text-xs font-medium flex items-center gap-2 row-fade-in">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                    <?= htmlspecialchars($pesan); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-6">
                <a href="?status=belum_bayar" class="pm-card pm-card-hover p-3 sm:p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 <?= $filterStatus === 'belum_bayar' ? 'ring-1 ring-amber-400 bg-amber-50' : ''; ?> pm-fade pm-d1">
                    <p class="text-[10px] sm:text-[11px] text-slate-500 truncate">Belum Bayar</p>
                    <p class="text-lg sm:text-2xl font-bold text-amber-500 mt-1"><?= $ringkasan['belum_bayar']; ?></p>
                </a>
                <a href="?status=terlambat" class="pm-card pm-card-hover p-3 sm:p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 <?= $filterStatus === 'terlambat' ? 'ring-1 ring-rose-400 bg-rose-50' : ''; ?> pm-fade pm-d2">
                    <p class="text-[10px] sm:text-[11px] text-slate-500 truncate">Terlambat</p>
                    <p class="text-lg sm:text-2xl font-bold text-rose-500 mt-1"><?= $ringkasan['terlambat']; ?></p>
                </a>
                <a href="?status=lunas" class="pm-card pm-card-hover p-3 sm:p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 <?= $filterStatus === 'lunas' ? 'ring-1 ring-emerald-400 bg-emerald-50' : ''; ?> pm-fade pm-d3">
                    <p class="text-[10px] sm:text-[11px] text-slate-500 truncate">Lunas</p>
                    <p class="text-lg sm:text-2xl font-bold text-emerald-500 mt-1"><?= $ringkasan['lunas']; ?></p>
                </a>
            </div>

            <?php if ($filterStatus !== ''): ?>
                <div class="mb-4 -mt-3">
                    <a href="?" class="inline-flex items-center gap-1.5 text-[11px] text-slate-500 hover:text-slate-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        Hapus filter "<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $filterStatus))); ?>"
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!empty($belumPunyaJadwal) && $role_user !== 'atasan'): ?>
                <div class="pm-card p-4 sm:p-5 mb-6 pm-fade pm-d1">
                    <h4 class="text-sm font-bold text-slate-900 mb-1 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-sky-500">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 8v4l3 3" />
                        </svg>
                        Belum Punya Jadwal Angsuran
                    </h4>
                    <p class="text-xs text-slate-500 mb-4">Pengajuan berstatus disetujui yang belum digenerate jadwal cicilannya.</p>
                    <div class="space-y-2">
                        <?php foreach ($belumPunyaJadwal as $b):
                            $plafonKosong = (float) $b['plafon_pengajuan'] <= 0;
                        ?>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100 hover:border-slate-200 transition">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-900 truncate"><?= htmlspecialchars($b['nama_lengkap']); ?></p>
                                    <p class="text-[11px] text-slate-500">Plafon Rp<?= number_format((float) $b['plafon_pengajuan'], 0, ',', '.'); ?> &middot; Tenor <?= (int) $b['tenor_bulan']; ?> bulan</p>
                                </div>
                                <form method="POST" class="shrink-0">
                                    <input type="hidden" name="generate_id" value="<?= (int) $b['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit"
                                        class="pm-btn pm-btn-primary w-full sm:w-auto !py-1.5 !px-3 !text-[11px] disabled:opacity-40 disabled:cursor-not-allowed"
                                        <?= $plafonKosong ? 'disabled title="Isi plafon pengajuan terlebih dahulu"' : ''; ?>>
                                        Generate Jadwal
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="pm-card overflow-hidden pm-fade pm-d2">
                <div class="px-4 sm:px-6 pt-5 pb-3">
                    <h4 class="text-sm font-bold text-slate-900">Jadwal Angsuran<?= $filterStatus !== '' ? ' &middot; ' . htmlspecialchars(ucfirst(str_replace('_', ' ', $filterStatus))) : ''; ?></h4>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table w-full">
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
                            <?php if (empty($daftarAngsuran)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="flex flex-col items-center gap-2 text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="opacity-60">
                                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                                <line x1="16" y1="2" x2="16" y2="6" />
                                                <line x1="8" y1="2" x2="8" y2="6" />
                                                <line x1="3" y1="10" x2="21" y2="10" />
                                            </svg>
                                            <span class="text-xs italic">Belum ada jadwal angsuran.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftarAngsuran as $a):
                                    $ast = $a['status'] ?? '';
                                    if ($ast === 'lunas') {
                                        $warna = 'bg-emerald-50 text-emerald-600';
                                    } elseif ($ast === 'terlambat') {
                                        $warna = 'bg-rose-50 text-rose-600';
                                    } else {
                                        $warna = 'bg-amber-50 text-amber-600';
                                    }
                                ?>
                                    <tr>
                                        <td class="font-semibold text-slate-900"><?= htmlspecialchars($a['nama_lengkap']); ?></td>
                                        <td class="text-slate-600">ke-<?= (int) $a['angsuran_ke']; ?></td>
                                        <td class="text-slate-600"><?= htmlspecialchars(date('d-m-Y', strtotime($a['tanggal_jatuh_tempo']))); ?></td>
                                        <td class="text-slate-600 whitespace-nowrap">Rp<?= number_format((float) $a['nominal'], 0, ',', '.'); ?></td>
                                        <td><span class="pm-badge <?= $warna; ?>"><?= ucfirst(str_replace('_', ' ', $a['status'])); ?></span></td>
                                        <td class="!text-right">
                                            <?php if ($a['status'] !== 'lunas' && $role_user !== 'atasan'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="bayar_id" value="<?= (int) $a['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <button type="submit" class="text-[11px] font-semibold text-emerald-400 hover:text-emerald-300 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 rounded px-1">Tandai Lunas</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-[11px] text-slate-300">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
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