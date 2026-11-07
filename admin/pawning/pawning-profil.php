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

$id   = (int) ($_SESSION['user_id'] ?? 0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$row = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    $nama    = trim($_POST['nama'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $passLama = $_POST['pass_lama'] ?? '';
    $passBaru = $_POST['pass_baru'] ?? '';
    $passUlang = $_POST['pass_ulang'] ?? '';

    if ($nama === '') {
        $err = 'Nama lengkap wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Email harus valid.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($stmt, "si", $email, $id);
        mysqli_stmt_execute($stmt);
        $check = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($check) {
            $err = 'Email sudah digunakan akun lain.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $nama, $email, $telepon, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['nama'] = $nama;

            if ($passBaru !== '') {
                if ($passLama === '') {
                    $err = 'Password lama wajib diisi untuk mengganti password.';
                } elseif (strlen($passBaru) < 6) {
                    $err = 'Password baru minimal 6 karakter.';
                } elseif ($passBaru !== $passUlang) {
                    $err = 'Konfirmasi password baru tidak cocok.';
                } else {
                    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $hashLama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    mysqli_stmt_close($stmt);

                    if (isset($hashLama['password']) && password_verify($passLama, $hashLama['password'])) {
                        $newHash = password_hash($passBaru, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "si", $newHash, $id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        $msg = 'Profil &amp; password berhasil diperbarui.';
                    } else {
                        $err = 'Password lama salah.';
                    }
                }
            } else {
                $msg = 'Profil berhasil diperbarui.';
            }

            $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Profil Saya | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Profil Saya',
            'subtitle' => 'Kelola informasi akun dan ganti password kamu.',
        ]); ?>

        <?php if ($msg): ?>
            <div class="pm-card mb-4 p-3 bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm"><?= $msg; ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="pm-card mb-4 p-3 bg-rose-50 border border-rose-100 text-rose-600 text-sm"><?= htmlspecialchars($err); ?></div>
        <?php endif; ?>

        <form method="POST" class="max-w-2xl space-y-5">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <div class="pm-card p-6 pm-fade pm-d1">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Informasi Akun</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Nama Lengkap</label>
                        <input type="text" name="nama" value="<?= htmlspecialchars($row['nama_lengkap'] ?? ''); ?>" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Username</label>
                        <input type="text" value="<?= htmlspecialchars($row['username'] ?? ''); ?>" disabled class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-50 text-slate-400 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($row['email'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">No. Telepon</label>
                        <input type="text" name="telepon" value="<?= htmlspecialchars($row['no_hp'] ?? ''); ?>" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Role</label>
                        <input type="text" value="<?= htmlspecialchars(ucfirst($row['role'] ?? '')); ?>" disabled class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-slate-50 text-slate-400 cursor-not-allowed">
                    </div>
                </div>
            </div>

            <div class="pm-card p-6 pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Ganti Password <span class="text-[10px] font-normal text-slate-400">(opsional)</span></h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Password Lama</label>
                        <input type="password" name="pass_lama" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Password Baru</label>
                        <input type="password" name="pass_baru" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Konfirmasi Password Baru</label>
                        <input type="password" name="pass_ulang" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                </div>
            </div>

            <button type="submit" class="pm-btn pm-btn-primary">Simpan Perubahan</button>
        </form>
      </div>
    </main>
</body>
</html>
