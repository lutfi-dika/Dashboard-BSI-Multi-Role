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
$current_page = 'jatuh-tempo';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'tanggal_jatuh_tempo'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($skemaLengkap && $_SERVER['REQUEST_METHOD'] === 'POST' && $role_user !== 'atasan') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $tgl = $_POST['tanggal_jatuh_tempo'] ?? '';
    if ($id > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
        $stmt = mysqli_prepare($conn, "UPDATE nasabah_mikro SET tanggal_jatuh_tempo = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $tgl, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: mikro-jatuh-tempo.php');
    exit();
}

$daftar = [];
if ($skemaLengkap) {
    $q = mysqli_query($conn, "
        SELECT id, nama_pemilik, nama_usaha, outstanding, nama_ao, tanggal_jatuh_tempo,
               DATEDIFF(tanggal_jatuh_tempo, CURDATE()) AS sisa_hari
        FROM nasabah_mikro
        WHERE status_pengajuan = 'Aktif' AND tanggal_jatuh_tempo IS NOT NULL
        ORDER BY tanggal_jatuh_tempo ASC
    ");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $daftar[] = $r;
        }
    }
}
$jatuhTempo30 = array_filter($daftar, fn($d) => $d['sisa_hari'] !== null && $d['sisa_hari'] <= 30);
$lewatTempo = array_filter($daftar, fn($d) => $d['sisa_hari'] !== null && $d['sisa_hari'] < 0);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Jatuh Tempo Pembiayaan | BSI Mikro', 'mikro'); ?>
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
            'title'    => 'Jatuh Tempo Pembiayaan',
            'subtitle' => 'Pemantauan tanggal jatuh tempo debitur aktif.',
            'stats'    => [
                ['label' => 'Jatuh Tempo &le; 30 Hari', 'value' => count($jatuhTempo30)],
                ['label' => 'Lewat Tempo', 'value' => count($lewatTempo)],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/003_mikro_dokumen.sql</code> agar kolom tanggal jatuh tempo tersedia.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <?php premium_kpi_card(['tone' => 'amber', 'delay' => 1, 'label' => 'Jatuh Tempo &le; 30 Hari', 'value' => count($jatuhTempo30), 'sub' => 'debitur', 'value_class' => '!text-amber-600']); ?>
                <?php premium_kpi_card(['tone' => 'rose', 'delay' => 2, 'label' => 'Sudah Lewat Tempo', 'value' => count($lewatTempo), 'sub' => 'debitur', 'value_class' => '!text-rose-600']); ?>
            </div>

            <div class="pm-card overflow-hidden pm-fade pm-d3">
                <div class="px-4 sm:px-6 pt-6 pb-4">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Daftar Jatuh Tempo</h4>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[820px]">
                        <thead>
                            <tr>
                                <th>Debitur</th>
                                <th>Outstanding</th>
                                <th>AO</th>
                                <th>Tanggal Jatuh Tempo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($daftar) > 0): ?>
                                <?php foreach ($daftar as $d):
                                    $sisa = (int) $d['sisa_hari'];
                                    if ($sisa < 0) { $status = 'Lewat Tempo'; $badge = 'bg-rose-50 text-rose-600 border-rose-100'; }
                                    elseif ($sisa <= 7) { $status = "H-{$sisa}"; $badge = 'bg-rose-50 text-rose-600 border-rose-100'; }
                                    elseif ($sisa <= 30) { $status = "H-{$sisa}"; $badge = 'bg-amber-50 text-amber-600 border-amber-100'; }
                                    else { $status = 'Aman'; $badge = 'bg-emerald-50 text-emerald-600 border-emerald-100'; }
                                ?>
                                    <tr>
                                        <td>
                                            <p class="font-semibold text-slate-700"><?= htmlspecialchars($d['nama_pemilik']); ?></p>
                                            <p class="text-xs text-slate-400"><?= htmlspecialchars($d['nama_usaha']); ?></p>
                                        </td>
                                        <td>Rp <?= number_format($d['outstanding'] ?? 0, 0, ',', '.'); ?></td>
                                        <td><?= htmlspecialchars($d['nama_ao'] ?? '—'); ?></td>
                                        <td>
                                            <?php if ($role_user !== 'atasan'): ?>
                                                <form method="POST" class="flex items-center gap-1.5">
                                                    <input type="hidden" name="id" value="<?= (int) $d['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                    <input type="date" name="tanggal_jatuh_tempo" value="<?= htmlspecialchars($d['tanggal_jatuh_tempo']); ?>" class="text-xs px-2 py-1.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                                    <button type="submit" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700 px-2 py-1.5">Simpan</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-500"><?= htmlspecialchars($d['tanggal_jatuh_tempo']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="pm-badge <?= $badge; ?>"><?= $status; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="!text-center italic">Belum ada data jatuh tempo.</td></tr>
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
