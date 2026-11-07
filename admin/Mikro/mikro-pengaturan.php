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

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
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

    $notifEmail = isset($_POST['notif_email']) ? 1 : 0;
    $notifWa = isset($_POST['notif_wa']) ? 1 : 0;
    $notifJatuhTempo = isset($_POST['notif_jatuh_tempo']) ? 1 : 0;

    $stmt = mysqli_prepare($conn, "UPDATE users SET notif_email = ?, notif_wa = ?, notif_jatuh_tempo = ? WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iiii", $notifEmail, $notifWa, $notifJatuhTempo, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $pesan = 'Preferensi berhasil disimpan.';
        $pesanTipe = 'success';
    } else {
        $pesan = 'Gagal menyimpan preferensi.';
        $pesanTipe = 'error';
    }
}

$pref = [];
$stmt = mysqli_prepare($conn, "SELECT notif_email, notif_wa, notif_jatuh_tempo FROM users WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $pref = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    mysqli_stmt_close($stmt);
}

$notif_email = $pref['notif_email'] ?? 1;
$notif_wa = $pref['notif_wa'] ?? 0;
$notif_jatuh_tempo = $pref['notif_jatuh_tempo'] ?? 1;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Pengaturan | BSI Mikro', 'mikro'); ?>
    <style>
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 0;
            border-bottom: 1px solid #eef0f3;
        }

        .toggle-row:last-child { border-bottom: none; }

        .switch { position: relative; display: inline-block; width: 42px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; inset: 0;
            background-color: #e2e8f0; transition: .2s; border-radius: 999px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: white; transition: .2s; border-radius: 50%;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        input:checked + .slider { background-color: #10b981; }
        input:checked + .slider:before { transform: translateX(18px); }
    </style>
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
            'title'    => 'Pengaturan',
            'subtitle' => 'Preferensi dashboard dan konfigurasi notifikasi.',
        ]); ?>

        <?php if ($pesan): ?>
            <div class="rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm bg-emerald-50 text-emerald-700 border border-emerald-200">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="max-w-2xl">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <div class="pm-card rounded-2xl p-6">
                    <h4 class="text-sm font-bold text-slate-800 mb-1">Preferensi Notifikasi</h4>
                    <p class="text-xs text-slate-500 mb-2">Atur channel mana yang aktif untuk menerima pemberitahuan sistem.</p>

                    <div class="toggle-row">
                        <div>
                            <p class="text-xs font-semibold text-slate-800">Notifikasi Email</p>
                            <p class="text-[11px] text-slate-500">Kirim ringkasan & alert penting ke email akun Anda.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_email" <?= $notif_email ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div>
                            <p class="text-xs font-semibold text-slate-800">Notifikasi WhatsApp</p>
                            <p class="text-[11px] text-slate-500">Kirim notifikasi via WhatsApp (Fonnte).</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_wa" <?= $notif_wa ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="toggle-row">
                        <div>
                            <p class="text-xs font-semibold text-slate-800">Peringatan Jatuh Tempo</p>
                            <p class="text-[11px] text-slate-500">Ingatkan bila ada nasabah mendekati jatuh tempo angsuran.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notif_jatuh_tempo" <?= $notif_jatuh_tempo ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <button type="submit" class="pm-btn pm-btn-primary mt-5">Simpan Preferensi</button>
                </div>
            </form>
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
