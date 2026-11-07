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
$current_page = 'dokumen';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'dok_ktp'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($skemaLengkap && $_SERVER['REQUEST_METHOD'] === 'POST' && $role_user !== 'atasan') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $allowedFields = ['dok_ktp', 'dok_kk', 'dok_izin_usaha', 'dok_foto_usaha'];
    if ($id > 0 && in_array($field, $allowedFields, true)) {
        $stmt = mysqli_prepare($conn, "UPDATE nasabah_mikro SET {$field} = NOT {$field} WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: mikro-dokumen.php');
    exit();
}

$daftar = [];
if ($skemaLengkap) {
    $q = mysqli_query($conn, "SELECT id, nama_pemilik, nama_usaha, dok_ktp, dok_kk, dok_izin_usaha, dok_foto_usaha FROM nasabah_mikro ORDER BY created_at DESC");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $daftar[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Dokumen Nasabah | BSI Mikro', 'mikro'); ?>
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
            'title'    => 'Dokumen Nasabah',
            'subtitle' => 'Checklist kelengkapan dokumen persyaratan pembiayaan mikro.',
            'stats'    => [['label' => 'Nasabah', 'value' => count($daftar)]],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/003_mikro_dokumen.sql</code> agar checklist dokumen tersedia.
            </div>
        <?php else: ?>
            <div class="pm-card overflow-hidden pm-fade pm-d1">
                <div class="px-4 sm:px-6 pt-6 pb-4">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Checklist Dokumen (<?= count($daftar); ?> nasabah)</h4>
                    <p class="text-xs text-slate-400 mt-0.5">Klik status untuk menandai dokumen sudah/belum diterima.</p>
                </div>
                <div class="pm-table-wrap pm-scroll">
                    <table class="pm-table min-w-[760px]">
                        <thead>
                            <tr>
                                <th>Nasabah</th>
                                <th class="!text-center">KTP</th>
                                <th class="!text-center">KK</th>
                                <th class="!text-center">Izin Usaha</th>
                                <th class="!text-center">Foto Usaha</th>
                                <th class="!text-right">Kelengkapan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($daftar) > 0): ?>
                                <?php foreach ($daftar as $d):
                                    $fields = ['dok_ktp' => 'KTP', 'dok_kk' => 'KK', 'dok_izin_usaha' => 'Izin Usaha', 'dok_foto_usaha' => 'Foto Usaha'];
                                    $lengkap = 0;
                                    foreach ($fields as $fk => $fl) { if ((int) $d[$fk] === 1) $lengkap++; }
                                    $persen = round(($lengkap / count($fields)) * 100);
                                ?>
                                    <tr>
                                        <td>
                                            <p class="font-semibold text-slate-700"><?= htmlspecialchars($d['nama_pemilik']); ?></p>
                                            <p class="text-xs text-slate-400"><?= htmlspecialchars($d['nama_usaha']); ?></p>
                                        </td>
                                        <?php foreach ($fields as $fk => $fl): ?>
                                            <td class="!text-center">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="id" value="<?= (int) $d['id']; ?>">
                                                    <input type="hidden" name="field" value="<?= $fk; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                                    <?php if ($role_user !== 'atasan'): ?>
                                                        <button type="submit" class="w-7 h-7 rounded-lg flex items-center justify-center border transition <?= $d[$fk] ? 'bg-emerald-50 border-emerald-200 text-emerald-600' : 'bg-slate-50 border-slate-200 text-slate-300'; ?>">
                                                            <?= $d[$fk] ? '✓' : '—'; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="w-7 h-7 rounded-lg flex items-center justify-center border mx-auto <?= $d[$fk] ? 'bg-emerald-50 border-emerald-200 text-emerald-600' : 'bg-slate-50 border-slate-200 text-slate-300'; ?>">
                                                            <?= $d[$fk] ? '✓' : '—'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="!text-right">
                                            <span class="text-xs font-bold <?= $persen === 100 ? 'text-emerald-600' : 'text-amber-600'; ?>"><?= $persen; ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="!text-center italic">Belum ada data nasabah mikro.</td></tr>
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
