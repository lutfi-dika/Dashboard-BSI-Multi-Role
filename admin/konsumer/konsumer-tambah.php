<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

// Proteksi Hak Akses Karyawan: admin, petugas konsumer (tulis) dan atasan (baca saja)
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'konsumer' && $_SESSION['role'] !== 'atasan')) {
    header("Location: ../../index.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'atasan') {
    header("Location: konsumer-tambah.php");
    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$notifikasi = "";

$produkValid = ['BSI Griya iB', 'BSI OTO iB', 'BSI Pensiun iB', 'BSI Umroh iB', 'BSI Multiguna iB', 'BSI Fleksi iB'];
$produkPreselect = $_POST['produk_diajukan'] ?? ($_GET['p'] ?? 'BSI Multiguna iB');
if (!in_array($produkPreselect, $produkValid, true)) {
    $produkPreselect = 'BSI Multiguna iB';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF Token Invalid");
    }

    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $nik          = trim($_POST['nik'] ?? '');
    $pekerjaan    = trim($_POST['pekerjaan'] ?? '');
    $pendapatan   = floatval($_POST['pendapatan_bulanan'] ?? 0);
    $produk       = trim($_POST['produk_diajukan'] ?? '');
    $alamat       = trim($_POST['alamat_rumah'] ?? '');
    $no_telp      = trim($_POST['no_telepon'] ?? '');

    // Audit Trail: Mengunci data berdasarkan siapa ID karyawan internal yang sedang login
    $user_id      = (int) $_SESSION['user_id'];

    $stmt = mysqli_prepare($conn, "INSERT INTO nasabah_konsumer (nama_lengkap, nik, pekerjaan, pendapatan_bulanan, produk_diajukan, alamat_rumah, no_telepon, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssdsssi", $nama_lengkap, $nik, $pekerjaan, $pendapatan, $produk, $alamat, $no_telp, $user_id);

    if (mysqli_stmt_execute($stmt)) {
        $notifikasi = "<div class='mb-5 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-medium flex items-center gap-2'>
                        ✨ Berkas Aplikasi Konsumer Berhasil Disimpan ke Database Kantor Pusat!
                      </div>";
    } else {
        $notifikasi = "<div class='mb-5 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-medium'>
                        ⚠️ Gagal memproses data pemohon: " . htmlspecialchars(mysqli_error($conn)) . "
                      </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Input Data Konsumer | BSI Konsumer', 'konsumer'); ?>
</head>

<body class="text-slate-700 min-h-screen flex">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-6 md:p-8 flex justify-center items-center overflow-y-auto max-h-screen">
        <div class="pm-card p-6 md:p-8 rounded-2xl w-full max-w-xl my-auto pm-fade pm-d1">
            <header class="mb-6">
                <a href="konsumer-data.php" class="text-xs text-sky-600 hover:underline flex items-center gap-1 mb-2">
                    ← Kembali ke Database Records
                </a>
                <h2 class="text-xl font-bold text-slate-800 tracking-wide">Form Aplikasi Segmen Konsumer</h2>
                <p class="text-xs text-slate-500">Input berkas pendaftaran finansial individu, pendanaan ritel, dan pembiayaan personal.</p>
            </header>

            <?= $notifikasi; ?>

            <form action="" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-500 mb-1.5 font-medium">Nama Lengkap Pemohon (Nasabah)</label>
                        <input type="text" name="nama_lengkap" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors">
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-1.5 font-medium">Nomor Induk Kependudukan (NIK)</label>
                        <input type="text" name="nik" maxlength="16" minlength="16" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors tracking-widest" placeholder="16 Digit Nomor KTP">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-500 mb-1.5 font-medium">Profesi / Pekerjaan Aktif</label>
                        <input type="text" name="pekerjaan" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors" placeholder="Contoh: Karyawan Swasta, PNS">
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-1.5 font-medium">Produk Yang Diajukan</label>
                        <select name="produk_diajukan" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors cursor-pointer">
                            <option value="BSI Griya iB" <?= $produkPreselect === 'BSI Griya iB' ? 'selected' : ''; ?>>BSI Griya iB — Pembiayaan Rumah (KPR Syariah)</option>
                            <option value="BSI OTO iB" <?= $produkPreselect === 'BSI OTO iB' ? 'selected' : ''; ?>>BSI OTO iB — Pembiayaan Kendaraan</option>
                            <option value="BSI Pensiun iB" <?= $produkPreselect === 'BSI Pensiun iB' ? 'selected' : ''; ?>>BSI Pensiun iB — Pembiayaan Pensiunan</option>
                            <option value="BSI Umroh iB" <?= $produkPreselect === 'BSI Umroh iB' ? 'selected' : ''; ?>>BSI Umroh iB — Pembiayaan Umroh</option>
                            <option value="BSI Multiguna iB" <?= $produkPreselect === 'BSI Multiguna iB' ? 'selected' : ''; ?>>BSI Multiguna iB — Pembiayaan Multiguna</option>
                            <option value="BSI Fleksi iB" <?= $produkPreselect === 'BSI Fleksi iB' ? 'selected' : ''; ?>>BSI Fleksi iB — Pembiayaan Fleksibel</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-500 mb-1.5 font-medium">Pendapatan Bersih Bulanan (Rp)</label>
                        <input type="number" name="pendapatan_bulanan" required min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors" placeholder="Contoh: 7500000">
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-1.5 font-medium">No. Telepon Kontak Aktif</label>
                        <input type="text" name="no_telepon" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors" placeholder="Contoh: 0896778899">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-500 mb-1.5 font-medium">Alamat Rumah Sesuai Domisili KTP</label>
                    <textarea name="alamat_rumah" required rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors resize-none" placeholder="Masukkan detail nama jalan, blok rumah, RT/RW, kelurahan, dan kecamatan domisili pemohon..."></textarea>
                </div>

                <button type="submit" class="pm-btn pm-btn-primary w-full mt-2">
                    Validasi & Simpan Aplikasi Konsumer
                </button>
            </form>
        </div>
    </main>
</body>

</html>