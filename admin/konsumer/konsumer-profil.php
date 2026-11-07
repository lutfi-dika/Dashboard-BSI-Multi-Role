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
$userId = (int) $_SESSION['user_id'];

if (!in_array($role_user, ['admin', 'konsumer', 'atasan'], true)) {
    header('Location: ../../index.php');
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

    if (isset($_POST['aksi']) && $_POST['aksi'] === 'update_profil') {
        $namaBaru = trim($_POST['nama_lengkap'] ?? '');
        $emailBaru = trim($_POST['email'] ?? '');
        if ($namaBaru === '' || !filter_var($emailBaru, FILTER_VALIDATE_EMAIL)) {
            $pesan = 'Nama lengkap dan email harus valid.';
            $pesanTipe = 'error';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $namaBaru, $emailBaru, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['nama'] = $namaBaru;
            $pesan = 'Profil berhasil diperbarui.';
            $pesanTipe = 'success';
        }
    } elseif (isset($_POST['aksi']) && $_POST['aksi'] === 'ganti_password') {
        $pwLama = $_POST['password_lama'] ?? '';
        $pwBaru = $_POST['password_baru'] ?? '';
        $pwKonfirmasi = $_POST['password_konfirmasi'] ?? '';

        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row || !password_verify($pwLama, $row['password'])) {
            $pesan = 'Password lama tidak sesuai.';
            $pesanTipe = 'error';
        } elseif (strlen($pwBaru) < 8) {
            $pesan = 'Password baru minimal 8 karakter.';
            $pesanTipe = 'error';
        } elseif ($pwBaru !== $pwKonfirmasi) {
            $pesan = 'Konfirmasi password baru tidak cocok.';
            $pesanTipe = 'error';
        } else {
            $hash = password_hash($pwBaru, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $hash, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $pesan = 'Password berhasil diubah.';
            $pesanTipe = 'success';
        }
    }
}

$profilDefaults = ['nama_lengkap' => '', 'username' => '', 'email' => '', 'role' => '', 'status' => '', 'created_at' => null];
$profilQuery = "SELECT nama_lengkap, username, email, role, created_at FROM users WHERE id = ?";
// Cek apakah kolom 'status' ada
$cekStatus = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
if ($cekStatus && mysqli_num_rows($cekStatus) > 0) {
    $profilQuery = "SELECT nama_lengkap, username, email, role, status, created_at FROM users WHERE id = ?";
}
$stmt = mysqli_prepare($conn, $profilQuery);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$profil = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $profilDefaults;
mysqli_stmt_close($stmt);

// Cek apakah kolom diproses_oleh ada di nasabah_konsumer
$hasDiproses = false;
$cekDiproses = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_konsumer LIKE 'diproses_oleh'");
if ($cekDiproses && mysqli_num_rows($cekDiproses) > 0) {
    $hasDiproses = true;
}

// Ringkasan aktivitas: berapa pengajuan yang sudah pernah diproses (approve/reject) oleh user ini.
$aktivitasDefaults = ['total_diproses' => 0, 'total_disetujui' => 0, 'total_ditolak' => 0];
if ($hasDiproses) {
    $stmtAktivitas = mysqli_prepare($conn, "SELECT
            COUNT(*) AS total_diproses,
            SUM(CASE WHEN status = 'disetujui' THEN 1 ELSE 0 END) AS total_disetujui,
            SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) AS total_ditolak
        FROM nasabah_konsumer WHERE diproses_oleh = ?");
    mysqli_stmt_bind_param($stmtAktivitas, "i", $userId);
    mysqli_stmt_execute($stmtAktivitas);
    $aktivitas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtAktivitas)) ?: $aktivitasDefaults;
    mysqli_stmt_close($stmtAktivitas);
} else {
    $aktivitas = $aktivitasDefaults;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Profil | BSI Konsumer', 'konsumer'); ?>
    <style>
        .field-input {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.12);
            background: rgba(255, 255, 255, 0.7);
            color: #1e293b;
            font-size: 0.8rem;
        }

        .field-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
            border-color: #0ea5e9;
        }

        .field-input:disabled {
            color: #64748b;
            background: rgba(248, 250, 252, 0.5);
        }
    </style>
</head>

<body class="text-slate-800 min-h-screen flex">

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
            'title'    => 'Profil Saya',
            'subtitle' => 'Kelola informasi akun dan keamanan login Anda.',
            'stats'    => [
                ['label' => 'Total Diproses', 'value' => number_format((int) $aktivitas['total_diproses'])],
                ['label' => 'Disetujui',      'value' => number_format((int) $aktivitas['total_disetujui'])],
                ['label' => 'Ditolak',        'value' => number_format((int) $aktivitas['total_ditolak'])],
            ],
        ]); ?>

        <?php if ($pesan): ?>
            <div class="rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm <?= $pesanTipe === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="pm-card p-6 lg:col-span-2 pm-fade pm-d1">
                <h4 class="text-sm font-bold text-slate-900 mb-4">Informasi Akun</h4>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="aksi" value="update_profil">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 mb-1 block">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($profil['nama_lengkap'] ?? ''); ?>" required class="field-input">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700 mb-1 block">Username</label>
                        <input type="text" value="<?= htmlspecialchars($profil['username'] ?? ''); ?>" disabled class="field-input">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700 mb-1 block">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($profil['email'] ?? ''); ?>" required class="field-input">
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="text-xs font-semibold text-slate-700 mb-1 block">Role</label>
                            <input type="text" value="<?= htmlspecialchars(ucfirst($profil['role'] ?? '')); ?>" disabled class="field-input">
                        </div>
                        <div class="flex-1">
                            <label class="text-xs font-semibold text-slate-700 mb-1 block">Bergabung Sejak</label>
                            <input type="text" value="<?= isset($profil['created_at']) ? htmlspecialchars(date('d M Y', strtotime($profil['created_at']))) : '-'; ?>" disabled class="field-input">
                        </div>
                    </div>
                    <button type="submit" class="pm-btn pm-btn-primary">Simpan Perubahan</button>
                </form>

                <hr class="border-slate-200 my-6">

                <h4 class="text-sm font-bold text-slate-900 mb-4">Ubah Password</h4>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="aksi" value="ganti_password">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-700 mb-1 block">Password Lama</label>
                            <input type="password" name="password_lama" required class="field-input">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-700 mb-1 block">Password Baru</label>
                            <input type="password" name="password_baru" minlength="8" required class="field-input">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-700 mb-1 block">Konfirmasi</label>
                            <input type="password" name="password_konfirmasi" minlength="8" required class="field-input">
                        </div>
                    </div>
                    <button type="submit" class="pm-btn pm-btn-ghost">Ubah Password</button>
                </form>
            </div>

            <div class="pm-card p-6 h-fit pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-900 mb-4">Ringkasan Aktivitas</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-600">Total Diproses</span>
                        <span class="text-sm font-bold text-slate-900"><?= (int) $aktivitas['total_diproses']; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-600">Disetujui</span>
                        <span class="text-sm font-bold text-green-600"><?= (int) $aktivitas['total_disetujui']; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-600">Ditolak</span>
                        <span class="text-sm font-bold text-red-600"><?= (int) $aktivitas['total_ditolak']; ?></span>
                    </div>
                </div>
                <a href="konsumer-data.php" class="pm-btn pm-btn-ghost w-full mt-5">Lihat Semua Data</a>
            </div>
        </div>
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