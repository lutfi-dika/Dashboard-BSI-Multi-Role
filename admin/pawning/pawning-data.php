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
    header('Location: pawning-data.php');
    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pencarian dan Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Query dasar
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, "%$search%");
    $where .= " AND (nama_pemilik LIKE '$search_escaped' OR no_telepon LIKE '$search_escaped' OR barang_jaminan LIKE '$search_escaped')";
}
if (!empty($status_filter)) {
    $status_escaped = mysqli_real_escape_string($conn, $status_filter);
    $where .= " AND status = '$status_escaped'";
}

// Total data
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM pawning $where");
$total_data = mysqli_fetch_assoc($total_query)['total'];
$total_pages = ceil($total_data / $per_page);

// Data nasabah
$query = "SELECT * FROM pawning $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$result = mysqli_query($conn, $query);

// Statistik
$stat_aktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pawning WHERE status = 'Aktif'"))['total'];
$stat_lunas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pawning WHERE status = 'Lunas'"))['total'];
$stat_lelang = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM pawning WHERE status = 'Lelang'"))['total'];
$total_nasabah = $stat_aktif + $stat_lunas + $stat_lelang;

// Hapus data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }
    $id = (int)$_POST['hapus_id'];
    mysqli_query($conn, "DELETE FROM pawning WHERE id = $id");
    header("Location: pawning-data.php");
    exit();
}

// Import data dari file
$import_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }
    
    $file = $_FILES['import_file']['tmp_name'];
    if ($file && is_uploaded_file($file)) {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $imported = 0;
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 5) {
                $nama = mysqli_real_escape_string($conn, $row[0]);
                $barang = mysqli_real_escape_string($conn, $row[1]);
                $taksir = (float)$row[2];
                $pinjam = (float)$row[3];
                $status = mysqli_real_escape_string($conn, $row[4]);
                $telepon = mysqli_real_escape_string($conn, $row[5] ?? '');
                
                mysqli_query($conn, "INSERT INTO pawning (nama_pemilik, barang_jaminan, taksiran_harga, nilai_pinjaman, status, no_telepon, jenis_gadai, tanggal_gadai) VALUES ('$nama', '$barang', $taksir, $pinjam, '$status', '$telepon', '" . mysqli_real_escape_string($conn, $row[6] ?? 'Gadai Emas iB') . "', '" . mysqli_real_escape_string($conn, $row[7] ?? date('Y-m-d')) . "')");
                $imported++;
            }
        }
        fclose($handle);
        $import_msg = "<div class='p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg'>✓ Import berhasil: $imported data nasabah ditambahkan</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Data Nasabah Gadai | BSI Pawning', 'pawning'); ?>
</head>
<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/pawning-sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto">
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
            'title'    => 'Data Nasabah Gadai',
            'subtitle' => 'Kelola dan pantau data semua nasabah gadai.',
            'stats'    => [
                ['label' => 'Total Nasabah', 'value' => number_format($total_nasabah)],
                ['label' => 'Status Aktif',  'value' => number_format($stat_aktif)],
                ['label' => 'Lelang',        'value' => number_format($stat_lelang)],
            ],
            'actions'  => [['href' => 'pawning-tambah.php', 'label' => '+ Tambah Nasabah']],
        ]); ?>

        <?php echo $import_msg; ?>

        <!-- Statistik -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="pm-card p-4 pm-fade pm-d1">
                <p class="text-xs text-slate-500 mb-1">Total Nasabah</p>
                <p class="text-2xl font-bold text-slate-900"><?= $total_nasabah; ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d2">
                <p class="text-xs text-slate-500 mb-1">Status Aktif</p>
                <p class="text-2xl font-bold text-emerald-600"><?= $stat_aktif; ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d3">
                <p class="text-xs text-slate-500 mb-1">Lunas</p>
                <p class="text-2xl font-bold text-blue-600"><?= $stat_lunas; ?></p>
            </div>
            <div class="pm-card p-4 pm-fade pm-d4">
                <p class="text-xs text-slate-500 mb-1">Lelang</p>
                <p class="text-2xl font-bold text-red-600"><?= $stat_lelang; ?></p>
            </div>
        </div>

        <!-- Pencarian & Filter -->
        <div class="pm-card p-4 mb-6 pm-fade pm-d2">
            <form method="GET" class="flex flex-col md:flex-row gap-3">
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari nama, nomor telepon, atau barang..." class="flex-1 px-3 py-2 border border-slate-200 rounded-lg text-sm">
                <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg text-sm">
                    <option value="">Semua Status</option>
                    <option value="Aktif" <?= $status_filter === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="Lunas" <?= $status_filter === 'Lunas' ? 'selected' : ''; ?>>Lunas</option>
                    <option value="Lelang" <?= $status_filter === 'Lelang' ? 'selected' : ''; ?>>Lelang</option>
                </select>
                <button type="submit" class="pm-btn pm-btn-primary">Cari</button>
            </form>
        </div>

        <!-- Tabel Data -->
        <div class="pm-card overflow-hidden pm-fade pm-d3">
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table w-full">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pemilik</th>
                            <th>Barang Jaminan</th>
                            <th>Jenis Gadai</th>
                            <th>Nilai Pinjaman</th>
                            <th>Status</th>
                            <th>Tgl Input</th>
                            <th class="!text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        while ($row = mysqli_fetch_assoc($result)): 
                        ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td class="font-medium"><?= htmlspecialchars($row['nama_pemilik']); ?></td>
                            <td><?= htmlspecialchars($row['barang_jaminan']); ?></td>
                            <td>
                                <span class="pm-badge bg-amber-50 text-amber-700 border border-amber-100">
                                    <?= htmlspecialchars($row['jenis_gadai'] ?? 'Gadai Emas iB'); ?>
                                </span>
                            </td>
                            <td class="font-medium whitespace-nowrap">Rp <?= number_format($row['nilai_pinjaman'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="pm-badge <?= 
                                    $row['status'] === 'Aktif' ? 'bg-emerald-100 text-emerald-700' : 
                                    ($row['status'] === 'Lunas' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700');
                                ?>">
                                    <?= htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td class="text-xs text-slate-500 whitespace-nowrap"><?= date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td class="!text-right whitespace-nowrap">
                                <a href="pawning-data-detail.php?id=<?= $row['id']; ?>" class="text-amber-600 hover:text-amber-700 font-medium text-xs mr-2">Lihat</a>
                                <form method="POST" class="inline" onsubmit="return confirm('Yakin hapus?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="hapus_id" value="<?= $row['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-700 font-medium text-xs">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center gap-2">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i; ?><?= !empty($search) ? "&search=" . urlencode($search) : ''; ?><?= !empty($status_filter) ? "&status=" . urlencode($status_filter) : ''; ?>" 
                   class="pm-btn <?= $i === $page ? 'pm-btn-primary' : 'pm-btn-ghost'; ?> !px-3 !py-1">
                    <?= $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>
</body>
</html>
