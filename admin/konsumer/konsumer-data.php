<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/konsumer-filter-helper.php';
require_once __DIR__ . '/../../components/premium-ui.php';

// Proteksi Hak Akses Karyawan: admin, petugas konsumer (tulis) dan atasan (baca/approve)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'konsumer' && $_SESSION['role'] !== 'atasan')) {
    header("Location: ../../index.php");
    exit();
}
$role_user = $_SESSION['role'];

// ==========================================
// FILTER, SEARCH & PAGINATION
// ==========================================
$filter    = konsumer_build_filter($_GET);
$halaman   = max(1, (int) ($_GET['halaman'] ?? 1));
$per_hal   = 10;
$offset    = ($halaman - 1) * $per_hal;

// Hitung total baris (untuk pagination) sesuai filter aktif
$sqlCount = "SELECT COUNT(*) AS total FROM nasabah_konsumer k WHERE {$filter['where_sql']}";
$stmtCount = mysqli_prepare($conn, $sqlCount);
konsumer_bind_params($stmtCount, $filter['types'], $filter['params']);
mysqli_stmt_execute($stmtCount);
$total_baris = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCount))['total'] ?? 0);
mysqli_stmt_close($stmtCount);

$total_halaman = max(1, (int) ceil($total_baris / $per_hal));
$halaman       = min($halaman, $total_halaman);
$offset        = ($halaman - 1) * $per_hal;

// Ambil data nasabah konsumer beserta nama lengkap karyawan penginputnya
$sql = "SELECT k.*, u.nama_lengkap AS nama_petugas
        FROM nasabah_konsumer k
        LEFT JOIN users u ON k.user_id = u.id
        WHERE {$filter['where_sql']}
        ORDER BY k.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
$typesFull  = $filter['types'] . 'ii';
$paramsFull = array_merge($filter['params'], [$per_hal, $offset]);
konsumer_bind_params($stmt, $typesFull, $paramsFull);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Query string filter (tanpa "halaman") untuk dipakai ulang di link export/pagination
$query_filter = http_build_query(array_filter([
    'cari'   => $filter['cari'],
    'status' => $filter['status'],
]));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Data Segmen Konsumer | BSI Konsumer', 'konsumer'); ?>
    <style>
        @media print {

            .no-print,
            aside {
                display: none !important;
            }

            body {
                background: white !important;
                color: black !important;
            }

            .pm-card {
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }

            table {
                color: black !important;
            }
        }
    </style>
</head>

<body class="text-slate-700 min-h-screen flex">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-6 md:p-8 overflow-y-auto max-h-screen">
        <div class="flex items-center justify-between gap-3 mb-5 no-print">
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
            'title'    => 'Data Segmen Konsumer',
            'subtitle' => 'Daftar rekapan profil individu dan pengajuan produk perorangan nasabah ritel.',
            'stats'    => [
                ['label' => 'Data Ditemukan', 'value' => number_format($total_baris)],
            ],
            'actions'  => [
                ['href' => 'konsumer-tambah.php', 'label' => '+ Tambah Pengajuan'],
            ],
        ]); ?>

        <div class="flex flex-wrap items-center gap-2 mb-5 no-print pm-fade pm-d1">
            <a href="export-excel.php?<?= htmlspecialchars($query_filter); ?>" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold py-2.5 px-4 rounded-xl transition-all cursor-pointer inline-flex items-center gap-1.5">
                Export Excel
            </a>
            <a href="export-pdf.php?<?= htmlspecialchars($query_filter); ?>" class="bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold py-2.5 px-4 rounded-xl transition-all cursor-pointer inline-flex items-center gap-1.5">
                Export PDF
            </a>
            <button onclick="window.print()" class="pm-btn pm-btn-ghost">
                Cetak
            </button>
        </div>

        <form method="GET" class="pm-card p-4 mb-5 flex flex-wrap gap-3 items-center no-print pm-fade pm-d2">
            <div class="relative flex-1 min-w-[220px]">
                <input type="text" name="cari" value="<?= htmlspecialchars($filter['cari']); ?>" placeholder="Cari nama, NIK, pekerjaan, atau produk..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-3.5 pr-3.5 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:border-sky-500 focus:bg-white transition-colors">
            </div>
            <select name="status" class="bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-700 focus:outline-none focus:border-sky-500 focus:bg-white cursor-pointer">
                <option value="">Semua Status</option>
                <option value="pending" <?= $filter['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="disetujui" <?= $filter['status'] === 'disetujui' ? 'selected' : ''; ?>>Disetujui</option>
                <option value="ditolak" <?= $filter['status'] === 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
            </select>
            <button type="submit" class="pm-btn pm-btn-primary">
                Terapkan
            </button>
            <?php if ($filter['cari'] !== '' || $filter['status'] !== ''): ?>
                <a href="konsumer-data.php" class="text-xs text-slate-500 hover:text-slate-800 underline">Reset</a>
            <?php endif; ?>
        </form>

        <div id="toastContainer" class="fixed top-5 right-5 z-[100] flex flex-col gap-2 no-print"></div>

        <div class="pm-card overflow-hidden pm-fade pm-d3">
            <div class="p-4 border-b border-slate-100 bg-slate-50/60 flex justify-between items-center flex-wrap gap-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Database Records (Sektor Ritel / Konsumer)</span>
                <span class="text-xs text-slate-500"><?= number_format($total_baris); ?> data ditemukan</span>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table w-full">
                    <thead>
                        <tr>
                            <th>Nama Lengkap</th>
                            <th>NIK KTP</th>
                            <th>Pekerjaan / Instansi</th>
                            <th>Pendapatan / Bulan</th>
                            <th>Produk Diajukan</th>
                            <th>Status</th>
                            <th>No. Telp</th>
                            <th>Karyawan Penginput</th>
                            <th class="no-print !text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                $status = $row['status'] ?? 'pending';
                                if ($status === 'disetujui') {
                                    $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                } elseif ($status === 'ditolak') {
                                    $badge = 'bg-rose-50 text-rose-700 border-rose-200';
                                } else {
                                    $badge = 'bg-amber-50 text-amber-700 border-amber-200';
                                }
                                ?>
                                <tr id="row-<?= (int) $row['id']; ?>">
                                    <td class="font-semibold text-slate-800"><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                                    <td class="text-xs tracking-wider text-slate-500"><?= htmlspecialchars($row['nik']); ?></td>
                                    <td class="text-slate-600"><?= htmlspecialchars($row['pekerjaan']); ?></td>
                                    <td class="text-emerald-600 font-semibold">Rp <?= number_format($row['pendapatan_bulanan'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="pm-badge bg-sky-50 text-sky-700 border border-sky-200">
                                            <?= htmlspecialchars($row['produk_diajukan']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-cell pm-badge border <?= $badge; ?>">
                                            <?= ucfirst(htmlspecialchars($status)); ?>
                                        </span>
                                    </td>
                                    <td class="text-slate-500"><?= htmlspecialchars($row['no_telepon']); ?></td>
                                    <td class="text-xs text-slate-500 font-medium">
                                        👩‍💼 <?= htmlspecialchars($row['nama_petugas'] ?? 'Sistem / Admin'); ?>
                                    </td>
                                    <td class="no-print !text-right">
                                        <?php if ($status === 'pending'): ?>
                                            <div class="flex gap-1.5">
                                                <button onclick="prosesPengajuan(<?= (int) $row['id']; ?>, 'setuju', this)" class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition cursor-pointer">Setujui</button>
                                                <button onclick="prosesPengajuan(<?= (int) $row['id']; ?>, 'tolak', this)" class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-rose-50 text-rose-700 hover:bg-rose-100 transition cursor-pointer">Tolak</button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-slate-500 italic">Tidak ada data yang cocok dengan pencarian/filter ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_halaman > 1): ?>
                <div class="p-4 border-t border-slate-100 flex justify-between items-center flex-wrap gap-2 no-print">
                    <span class="text-xs text-slate-500">Halaman <?= $halaman; ?> dari <?= $total_halaman; ?></span>
                    <div class="flex gap-1.5">
                        <?php for ($p = 1; $p <= $total_halaman; $p++): ?>
                            <a href="?<?= htmlspecialchars($query_filter ? $query_filter . '&' : ''); ?>halaman=<?= $p; ?>"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold transition <?= $p === $halaman ? 'bg-sky-600 text-white' : 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50'; ?>">
                                <?= $p; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const CSRF_TOKEN = <?= json_encode(csrf_token()); ?>;

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const colors = {
                success: 'bg-emerald-600',
                error: 'bg-rose-600',
            };
            const toast = document.createElement('div');
            toast.className = `${colors[type] || colors.success} text-white text-xs font-semibold px-4 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0 translate-x-4`;
            toast.textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.classList.remove('opacity-0', 'translate-x-4');
            });
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-4');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        async function prosesPengajuan(id, aksi, btnEl) {
            const label = aksi === 'setuju' ? 'menyetujui' : 'menolak';
            if (!confirm(`Yakin ingin ${label} pengajuan ini?`)) return;

            const row = document.getElementById(`row-${id}`);
            row.style.opacity = '0.5';
            row.style.pointerEvents = 'none';

            try {
                const res = await fetch('konsumer-approve.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        id,
                        aksi,
                        csrf_token: CSRF_TOKEN
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    showToast(data.message || 'Gagal memproses pengajuan.', 'error');
                    row.style.opacity = '1';
                    row.style.pointerEvents = 'auto';
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan.', 'error');
                row.style.opacity = '1';
                row.style.pointerEvents = 'auto';
            }
        }
    </script>
</body>

</html>