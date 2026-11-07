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
if (!in_array($role_user, ['admin', 'pawning', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role_user === 'atasan') {
    header('Location: pawning-foto.php');
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$uploadDir = __DIR__ . '/../../uploads/pawning';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$pesan = '';
$pesanTipe = '';
$idFilter = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    if (isset($_POST['hapus_foto'])) {
        $fotoId = (int) $_POST['hapus_foto'];
        $stmt = mysqli_prepare($conn, "SELECT nama_file FROM pawning_foto WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $fotoId);
        mysqli_stmt_execute($stmt);
        $f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($f) {
            $path = $uploadDir . '/' . $f['nama_file'];
            if (is_file($path)) {
                unlink($path);
            }
            $stmt = mysqli_prepare($conn, "DELETE FROM pawning_foto WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $fotoId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $pesan = 'Foto berhasil dihapus.';
            $pesanTipe = 'success';
        }
    } else {
        $idGadai    = (int) ($_POST['id_gadai'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');
        $file       = $_FILES['foto_barang'] ?? null;

        if ($idGadai <= 0 || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $pesan = 'Pilih data gadai dan unggah foto yang valid.';
            $pesanTipe = 'error';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $pesan = 'Format foto harus JPG, PNG, atau WebP.';
                $pesanTipe = 'error';
            } else {
                $namaFile = 'pawning_' . $idGadai . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $namaFile)) {
                    $user = (int) $_SESSION['user_id'];
                    $stmt = mysqli_prepare($conn, "INSERT INTO pawning_foto (pawning_id, nama_file, keterangan, user_id) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "issi", $idGadai, $namaFile, $keterangan, $user);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $pesan = $ok ? 'Foto berhasil diunggah.' : 'Gagal menyimpan foto ke database.';
                    $pesanTipe = $ok ? 'success' : 'error';
                } else {
                    $pesan = 'Gagal mengunggah foto.';
                    $pesanTipe = 'error';
                }
            }
        }
    }
}

$daftarGadai = mysqli_query($conn, "SELECT id, nama_pemilik, barang_jaminan FROM pawning ORDER BY nama_pemilik ASC");

if ($idFilter > 0) {
    $stmt = mysqli_prepare($conn, "SELECT f.*, p.nama_pemilik, p.barang_jaminan FROM pawning_foto f JOIN pawning p ON p.id = f.pawning_id WHERE f.pawning_id = ? ORDER BY f.created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $idFilter);
    mysqli_stmt_execute($stmt);
    $galeri = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    $galeri = mysqli_query($conn, "SELECT f.*, p.nama_pemilik, p.barang_jaminan FROM pawning_foto f JOIN pawning p ON p.id = f.pawning_id ORDER BY f.created_at DESC");
}

$totalFoto = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) t FROM pawning_foto"))['t'] ?? 0);
$totalBarangFoto = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT pawning_id) t FROM pawning_foto"))['t'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Foto Barang Gadai | BSI Pawning', 'pawning'); ?>
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
            'title'    => 'Foto Barang Gadai',
            'subtitle' => 'Unggah dan kelola dokumentasi foto barang jaminan.',
            'stats'    => [
                ['label' => 'Total Foto',      'value' => number_format($totalFoto)],
                ['label' => 'Barang Berfoto',  'value' => number_format($totalBarangFoto)],
            ],
        ]); ?>

        <?php if ($pesan): ?>
            <div class="pm-card px-5 py-3.5 mb-5 text-xs sm:text-sm <?= $pesanTipe === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="pm-card p-5 pm-fade pm-d1">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Foto</p>
                <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalFoto); ?></p>
            </div>
            <div class="pm-card p-5 pm-fade pm-d2">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Barang Berfoto</p>
                <p class="text-2xl font-extrabold text-amber-600 mt-1"><?= number_format($totalBarangFoto); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="pm-card p-5 h-fit pm-fade pm-d2">
                <h4 class="text-sm font-bold text-slate-800 mb-4">Upload Foto</h4>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Data Gadai</label>
                        <select name="id_gadai" required class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                            <option value="">-- Pilih Nasabah --</option>
                            <?php if ($daftarGadai): while ($p = mysqli_fetch_assoc($daftarGadai)): ?>
                                <option value="<?= $p['id']; ?>" <?= $idFilter === (int) $p['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($p['nama_pemilik']); ?> — <?= htmlspecialchars($p['barang_jaminan']); ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">File Foto (JPG/PNG/WebP)</label>
                        <input type="file" name="foto_barang" accept=".jpg,.jpeg,.png,.webp" required class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500 mb-1 block">Keterangan</label>
                        <input type="text" name="keterangan" placeholder="cth. Tampak depan emas 10gr" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                    </div>
                    <button type="submit" class="pm-btn pm-btn-primary w-full">Unggah Foto</button>
                </form>
            </div>

            <div class="pm-card p-5 lg:col-span-2 pm-fade pm-d3">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-bold text-slate-800">Galeri Foto</h4>
                    <a href="pawning-foto.php" class="text-xs text-amber-600 font-semibold hover:underline">Semua</a>
                </div>
                <?php if ($galeri && mysqli_num_rows($galeri) > 0): ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php while ($g = mysqli_fetch_assoc($galeri)): ?>
                            <div class="rounded-xl overflow-hidden border border-slate-100 bg-white">
                                <a href="../../uploads/pawning/<?= htmlspecialchars($g['nama_file']); ?>" target="_blank">
                                    <img src="../../uploads/pawning/<?= htmlspecialchars($g['nama_file']); ?>" alt="Foto barang" class="w-full h-32 object-cover" onerror="this.outerHTML='<div class=&quot;w-full h-32 bg-slate-100 flex items-center justify-center text-slate-300&quot;>No foto</div>';">
                                </a>
                                <div class="p-3">
                                    <p class="text-xs font-semibold text-slate-700 truncate"><?= htmlspecialchars($g['nama_pemilik']); ?></p>
                                    <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($g['barang_jaminan']); ?></p>
                                    <p class="text-[10px] text-slate-400 mt-1 truncate"><?= htmlspecialchars($g['keterangan'] ?? ''); ?></p>
                                    <form method="POST" class="mt-2" onsubmit="return confirm('Hapus foto ini?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="hapus_foto" value="<?= (int) $g['id']; ?>">
                                        <button type="submit" class="text-[10px] text-rose-500 font-semibold hover:text-rose-700">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-400 italic py-10 text-center">Belum ada foto yang diunggah.</p>
                <?php endif; ?>
            </div>
        </div>
      </div>
    </main>
</body>
</html>
