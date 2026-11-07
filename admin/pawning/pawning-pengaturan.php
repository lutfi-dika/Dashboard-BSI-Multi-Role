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
    header('Location: pawning-pengaturan.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ==========================================
// Baca semua pengaturan ke array
// ==========================================
function ambilSemuaSetting(mysqli $conn): array
{
    $settings = [];
    $q = mysqli_query($conn, "SELECT nama_setting, nilai_setting FROM pawning_pengaturan ORDER BY nama_setting ASC");
    while ($r = mysqli_fetch_assoc($q)) {
        $settings[$r['nama_setting']] = $r['nilai_setting'];
    }
    return $settings;
}

$defaults = [
    'masa_gadai_hari'       => '120',
    'tarif_ujrah_bulan'     => '1.5',
    'denda_terlambat_persen' => '0.1',
    'nama_cabang'           => 'BSI KCPS',
    'kode_cabang'           => 'KCPS',
    'alamat_cabang'         => '',
    'no_telp_cabang'        => '',
];

$settings = ambilSemuaSetting($conn);
$settings = array_merge($defaults, $settings);

// ==========================================
// Simpan pengaturan
// ==========================================
$pesan = '';
$pesanTipe = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    $errors = [];
    $input = [];
    foreach (array_keys($defaults) as $key) {
        $input[$key] = trim($_POST[$key] ?? '');
    }

    $masa  = (int) $input['masa_gadai_hari'];
    $tarif = (float) str_replace(',', '.', $input['tarif_ujrah_bulan']);
    $denda = (float) str_replace(',', '.', $input['denda_terlambat_persen']);

    if ($masa < 1 || $masa > 3650) $errors[] = 'Masa gadai harus antara 1 s/d 3650 hari.';
    if ($tarif < 0 || $tarif > 100) $errors[] = 'Tarif ujrah harus antara 0 s/d 100%.';
    if ($denda < 0 || $denda > 100) $errors[] = 'Denda terlambat harus antara 0 s/d 100%.';
    if ($input['nama_cabang'] === '') $errors[] = 'Nama cabang wajib diisi.';
    if ($input['kode_cabang'] === '') $errors[] = 'Kode cabang wajib diisi.';

    if (!empty($errors)) {
        $pesan = implode('<br>', $errors);
        $pesanTipe = 'error';
    } else {
        $values = [
            'masa_gadai_hari'       => (string) $masa,
            'tarif_ujrah_bulan'     => number_format($tarif, 2, '.', ''),
            'denda_terlambat_persen' => number_format($denda, 2, '.', ''),
            'nama_cabang'           => $input['nama_cabang'],
            'kode_cabang'           => $input['kode_cabang'],
            'alamat_cabang'         => $input['alamat_cabang'],
            'no_telp_cabang'        => $input['no_telp_cabang'],
        ];

        $ok = true;
        mysqli_begin_transaction($conn);
        foreach ($values as $key => $value) {
            $stmt = mysqli_prepare($conn, "INSERT INTO pawning_pengaturan (nama_setting, nilai_setting) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai_setting = VALUES(nilai_setting)");
            mysqli_stmt_bind_param($stmt, "ss", $key, $value);
            $ok = $ok && mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        if ($ok) {
            mysqli_commit($conn);
            $settings = array_merge($defaults, $values);
            $pesan = 'Pengaturan sistem gadai berhasil disimpan.';
            $pesanTipe = 'success';
        } else {
            mysqli_rollback($conn);
            $pesan = 'Terjadi kesalahan saat menyimpan pengaturan.';
            $pesanTipe = 'error';
        }
    }
}

// ==========================================
// Statistik ringkas untuk konteks
// ==========================================
$stat = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(status = 'Aktif'), 0) AS aktif,
        COALESCE(SUM(nilai_pinjaman), 0) AS pinjaman
    FROM pawning
"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Pengaturan Sistem Gadai | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Pengaturan Sistem Gadai',
            'subtitle' => 'Konfigurasi tarif, masa gadai, dan profil cabang yang dipakai seluruh fitur gadai.',
            'stats'    => [
                ['label' => 'Total Gadai',   'value' => number_format((int) ($stat['jumlah'] ?? 0))],
                ['label' => 'Gadai Aktif',   'value' => number_format((int) ($stat['aktif'] ?? 0))],
                ['label' => 'Total Pinjaman', 'value' => 'Rp ' . number_format((float) ($stat['pinjaman'] ?? 0), 0, ',', '.')],
            ],
        ]); ?>

        <?php if ($pesan): ?>
            <div class="pm-card px-5 py-3.5 mb-5 text-xs sm:text-sm <?= $pesanTipe === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?= $pesan; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="pm-card p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Gadai</p>
                <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format((int) ($stat['jumlah'] ?? 0)); ?></p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Gadai Aktif</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1"><?= number_format((int) ($stat['aktif'] ?? 0)); ?></p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d3">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Pinjaman</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1">Rp <?= number_format((float) ($stat['pinjaman'] ?? 0), 0, ',', '.'); ?></p>
            </div>
        </div>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="simpan_pengaturan" value="1">

            <div class="pm-card p-5 sm:p-6 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Tarif &amp; Masa Gadai</h4>
                <p class="text-xs text-slate-400 mb-4">Nilai berikut dipakai untuk perhitungan otomatis ujrah, denda, dan jatuh tempo di menu Perpanjangan, Pelunasan, Penebusan, dan Jatuh Tempo.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Masa Gadai (hari)</label>
                        <input type="number" name="masa_gadai_hari" min="1" max="3650" value="<?= htmlspecialchars($settings['masa_gadai_hari']); ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        <p class="text-[10px] text-slate-400 mt-1">Default periode gadai setiap barang.</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Tarif Ujrah / Bulan (%)</label>
                        <input type="text" name="tarif_ujrah_bulan" inputmode="decimal" value="<?= htmlspecialchars($settings['tarif_ujrah_bulan']); ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        <p class="text-[10px] text-slate-400 mt-1">Persentase biaya jasa per bulan dari pinjaman.</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Denda Terlambat (%)</label>
                        <input type="text" name="denda_terlambat_persen" inputmode="decimal" value="<?= htmlspecialchars($settings['denda_terlambat_persen']); ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        <p class="text-[10px] text-slate-400 mt-1">Persentase denda per bulan keterlambatan.</p>
                    </div>
                </div>
            </div>

            <div class="pm-card p-5 sm:p-6 pm-fade pm-d3">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Profil Cabang</h4>
                <p class="text-xs text-slate-400 mb-4">Informasi ini ditampilkan pada laporan dan dokumen gadai.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Nama Cabang</label>
                        <input type="text" name="nama_cabang" value="<?= htmlspecialchars($settings['nama_cabang']); ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Kode Cabang</label>
                        <input type="text" name="kode_cabang" value="<?= htmlspecialchars($settings['kode_cabang']); ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">No. Telepon Cabang</label>
                        <input type="text" name="no_telp_cabang" value="<?= htmlspecialchars($settings['no_telp_cabang']); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Alamat Cabang</label>
                        <input type="text" name="alamat_cabang" value="<?= htmlspecialchars($settings['alamat_cabang']); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="pm-btn pm-btn-primary">Simpan Pengaturan</button>
            </div>
        </form>
      </div>
    </main>
</body>
</html>
