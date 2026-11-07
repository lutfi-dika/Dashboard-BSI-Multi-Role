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
$current_page = 'survey';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'survey_status'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$pesanError = false;

// Simpan hasil survey
if ($skemaLengkap && $_SERVER['REQUEST_METHOD'] === 'POST' && $role_user !== 'atasan') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $statusBaru = $_POST['survey_status'] ?? 'belum';
    $tanggal = trim($_POST['survey_tanggal'] ?? '');
    $petugas = trim($_POST['survey_petugas'] ?? '');
    $skor = $_POST['survey_skor'] ?? '';
    $catatan = trim($_POST['survey_catatan'] ?? '');

    if (!in_array($statusBaru, ['belum', 'terjadwal', 'selesai'], true)) {
        $pesan = 'Status survey tidak valid.';
        $pesanError = true;
    } elseif ($id <= 0) {
        $pesan = 'Data nasabah tidak ditemukan.';
        $pesanError = true;
    } else {
        $tanggalDb = $tanggal !== '' ? $tanggal : null;
        $petugasDb = $petugas !== '' ? $petugas : ($nama_user ?: null);
        $skorDb = ($skor !== '' && is_numeric($skor)) ? max(0, min(100, (int) $skor)) : null;
        $catatanDb = $catatan !== '' ? $catatan : null;

        $stmt = mysqli_prepare($conn, "UPDATE nasabah_mikro SET survey_status = ?, survey_tanggal = ?, survey_petugas = ?, survey_skor = ?, survey_catatan = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssisi", $statusBaru, $tanggalDb, $petugasDb, $skorDb, $catatanDb, $id);
        if (mysqli_stmt_execute($stmt)) {
            $pesan = 'Hasil survey berhasil disimpan.';
        } else {
            $pesan = 'Gagal menyimpan hasil survey.';
            $pesanError = true;
        }
        mysqli_stmt_close($stmt);
    }
}

$filterStatus = trim($_GET['status'] ?? '');
$daftar = [];
$ringkasan = ['belum' => 0, 'terjadwal' => 0, 'selesai' => 0];

if ($skemaLengkap) {
    $resRingkasan = mysqli_query($conn, "SELECT survey_status, COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan != 'Ditolak' GROUP BY survey_status");
    if ($resRingkasan) {
        while ($r = mysqli_fetch_assoc($resRingkasan)) {
            if (isset($ringkasan[$r['survey_status']])) $ringkasan[$r['survey_status']] = (int) $r['jml'];
        }
    }

    $where = "status_pengajuan != 'Ditolak'";
    $types = '';
    $params = [];
    if (in_array($filterStatus, ['belum', 'terjadwal', 'selesai'], true)) {
        $where .= " AND survey_status = ?";
        $types = 's';
        $params[] = $filterStatus;
    }
    $sql = "SELECT id, nama_pemilik, nama_usaha, jenis_sektor, alamat_usaha, no_telepon, status_pengajuan,
                   survey_status, survey_tanggal, survey_petugas, survey_skor, survey_catatan
            FROM nasabah_mikro
            WHERE $where
            ORDER BY FIELD(survey_status,'belum','terjadwal','selesai'), created_at DESC
            LIMIT 200";
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $daftar[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$statusLabel = ['belum' => 'Belum Survey', 'terjadwal' => 'Terjadwal', 'selesai' => 'Selesai'];
$statusColor = ['belum' => 'bg-slate-100 text-slate-600', 'terjadwal' => 'bg-amber-50 text-amber-600', 'selesai' => 'bg-emerald-50 text-emerald-600'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Survey Lapangan | BSI Mikro', 'mikro'); ?>
    <style>
        details > summary { list-style: none; cursor: pointer; }
        details > summary::-webkit-details-marker { display: none; }
        details[open] summary .chev { transform: rotate(180deg); }
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
            'title'    => 'Survey Lapangan',
            'subtitle' => 'Jadwal, catatan, dan skor kelayakan hasil kunjungan survey ke usaha nasabah.',
            'stats'    => [
                ['label' => 'Belum Survey', 'value' => $ringkasan['belum']],
                ['label' => 'Terjadwal', 'value' => $ringkasan['terjadwal']],
                ['label' => 'Selesai', 'value' => $ringkasan['selesai']],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/006_mikro_survey_angsuran.sql</code> agar modul survey tersedia.
            </div>
        <?php else: ?>

            <?php if ($pesan !== ''): ?>
                <div class="mb-5 p-3 rounded-xl text-xs font-medium border <?= $pesanError ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>"><?= htmlspecialchars($pesan); ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <a href="?status=belum" class="pm-card pm-card-hover p-5 <?= $filterStatus === 'belum' ? 'ring-2 ring-slate-300' : ''; ?>">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Belum Survey</p>
                    <p class="text-2xl font-bold text-slate-700"><?= $ringkasan['belum']; ?></p>
                </a>
                <a href="?status=terjadwal" class="pm-card pm-card-hover p-5 <?= $filterStatus === 'terjadwal' ? 'ring-2 ring-amber-300' : ''; ?>">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Terjadwal</p>
                    <p class="text-2xl font-bold text-amber-600"><?= $ringkasan['terjadwal']; ?></p>
                </a>
                <a href="?status=selesai" class="pm-card pm-card-hover p-5 <?= $filterStatus === 'selesai' ? 'ring-2 ring-emerald-300' : ''; ?>">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Selesai</p>
                    <p class="text-2xl font-bold text-emerald-600"><?= $ringkasan['selesai']; ?></p>
                </a>
            </div>

            <?php if ($filterStatus !== ''): ?>
                <a href="mikro-survey.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 mb-4">&larr; Tampilkan semua</a>
            <?php endif; ?>

            <div class="pm-card overflow-hidden pm-fade pm-d1">
                <div class="px-4 sm:px-6 pt-6 pb-4">
                    <h4 class="text-sm font-bold tracking-wide text-slate-800">Daftar Nasabah untuk Disurvey</h4>
                </div>
                <?php if (empty($daftar)): ?>
                    <div class="px-6 pb-8 text-xs text-slate-400 italic">Tidak ada data untuk filter ini.</div>
                <?php else: ?>
                <div class="divide-y divide-slate-50">
                    <?php foreach ($daftar as $d):
                        $sStat = $d['survey_status'] ?: 'belum';
                    ?>
                    <details class="group">
                        <summary class="flex items-center gap-3 px-4 sm:px-6 py-3.5 hover:bg-slate-50/60 transition">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center shrink-0 font-bold text-xs">
                                <?= strtoupper(substr($d['nama_pemilik'] ?: '?', 0, 1)); ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($d['nama_pemilik']); ?> &middot; <?= htmlspecialchars($d['nama_usaha']); ?></p>
                                <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($d['jenis_sektor'] ?: '-'); ?> &middot; <?= htmlspecialchars($d['alamat_usaha'] ?: '-'); ?></p>
                            </div>
                            <?php if ($d['survey_skor'] !== null): ?>
                                <span class="hidden sm:inline text-[11px] font-semibold text-slate-500">Skor <?= (int) $d['survey_skor']; ?></span>
                            <?php endif; ?>
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full <?= $statusColor[$sStat]; ?>"><?= $statusLabel[$sStat]; ?></span>
                            <svg class="chev w-4 h-4 text-slate-400 transition-transform shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </summary>
                        <div class="px-4 sm:px-6 pb-5 pt-1 bg-slate-50/40">
                            <?php if ($d['survey_catatan']): ?>
                                <p class="text-xs text-slate-500 italic mb-3">Catatan sebelumnya: "<?= htmlspecialchars($d['survey_catatan']); ?>"</p>
                            <?php endif; ?>
                            <?php if ($role_user === 'atasan'): ?>
                                <p class="text-xs text-slate-400">Hubungi petugas Mikro untuk memperbarui hasil survey.</p>
                            <?php else: ?>
                            <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="id" value="<?= (int) $d['id']; ?>">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Status</label>
                                    <select name="survey_status" class="w-full text-xs rounded-lg border border-slate-200 px-2.5 py-2">
                                        <?php foreach ($statusLabel as $k => $lbl): ?>
                                            <option value="<?= $k; ?>" <?= $sStat === $k ? 'selected' : ''; ?>><?= $lbl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Tanggal Survey</label>
                                    <input type="date" name="survey_tanggal" value="<?= htmlspecialchars($d['survey_tanggal'] ?? ''); ?>" class="w-full text-xs rounded-lg border border-slate-200 px-2.5 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Petugas</label>
                                    <input type="text" name="survey_petugas" value="<?= htmlspecialchars($d['survey_petugas'] ?? ''); ?>" placeholder="<?= htmlspecialchars($nama_user); ?>" class="w-full text-xs rounded-lg border border-slate-200 px-2.5 py-2">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Skor Kelayakan (0-100)</label>
                                    <input type="number" min="0" max="100" name="survey_skor" value="<?= htmlspecialchars($d['survey_skor'] ?? ''); ?>" class="w-full text-xs rounded-lg border border-slate-200 px-2.5 py-2">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="pm-btn pm-btn-primary w-full">Simpan</button>
                                </div>
                                <div class="sm:col-span-2 lg:col-span-5">
                                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Catatan Survey</label>
                                    <textarea name="survey_catatan" rows="2" placeholder="Kondisi usaha, omzet riil di lapangan, rekomendasi, dll." class="w-full text-xs rounded-lg border border-slate-200 px-2.5 py-2"><?= htmlspecialchars($d['survey_catatan'] ?? ''); ?></textarea>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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
