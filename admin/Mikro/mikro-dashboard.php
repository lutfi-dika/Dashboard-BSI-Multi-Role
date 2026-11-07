<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

// 1. Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// 2. Cek verifikasi OTP
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');
$current_page = 'dashboard';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

// ==========================================
// 0. DETEKSI SKEMA DATABASE
// Dashboard ini butuh kolom tambahan dari sql/002_mikro_dashboard_kpi.sql.
// Kalau migrasi belum dijalankan, dashboard tetap tampil dengan KPI yang
// tersedia dan menampilkan notifikasi supaya migrasi dijalankan.
// ==========================================
$kolomTambahan = ['alamat', 'cabang', 'nama_ao', 'status_pengajuan', 'outstanding', 'target_outstanding', 'kolektibilitas'];
$skemaLengkap  = true;
$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro");
$kolomTersedia = [];
if ($cekKolom) {
    while ($k = mysqli_fetch_assoc($cekKolom)) {
        $kolomTersedia[] = $k['Field'];
    }
}
foreach ($kolomTambahan as $kolom) {
    if (!in_array($kolom, $kolomTersedia, true)) {
        $skemaLengkap = false;
        break;
    }
}
// Catatan: sebelumnya kode ini mengecek tabel terpisah `survey_lapangan` yang
// tidak pernah dibuat oleh migrasi manapun (data survey sebenarnya disimpan
// sebagai kolom survey_* langsung di nasabah_mikro, lihat sql/006). Diperbaiki
// supaya konsisten dengan mikro-survey.php.
$cekKolomSurvey = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'survey_tanggal'");
$tabelSurveyAda = $cekKolomSurvey && mysqli_num_rows($cekKolomSurvey) > 0;

// ==========================================
// 1. KPI UTAMA
// ==========================================
$total_debitur     = 0; // debitur berstatus aktif
$total_pengajuan    = 0; // seluruh baris (pipeline)
$total_approval     = 0; // menunggu approval
$total_outstanding  = 0;
$total_target       = 0;
$outstanding_npf    = 0;
$survey_hari_ini    = 0;

if ($skemaLengkap) {
    $q = mysqli_query($conn, "
        SELECT
            COUNT(*) AS total_pengajuan,
            SUM(CASE WHEN status_pengajuan = 'Aktif' THEN 1 ELSE 0 END) AS total_debitur,
            SUM(CASE WHEN status_pengajuan = 'Approval' THEN 1 ELSE 0 END) AS total_approval,
            SUM(COALESCE(outstanding, 0)) AS total_outstanding,
            SUM(COALESCE(target_outstanding, 0)) AS total_target,
            SUM(CASE WHEN kolektibilitas >= 3 THEN COALESCE(outstanding, 0) ELSE 0 END) AS outstanding_npf
        FROM nasabah_mikro
    ");
    if ($q && ($row = mysqli_fetch_assoc($q))) {
        $total_pengajuan   = (int) $row['total_pengajuan'];
        $total_debitur     = (int) $row['total_debitur'];
        $total_approval    = (int) $row['total_approval'];
        $total_outstanding = (float) $row['total_outstanding'];
        $total_target      = (float) $row['total_target'];
        $outstanding_npf   = (float) $row['outstanding_npf'];
    }
} else {
    // Fallback: skema lama, hanya total baris yang tersedia
    $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM nasabah_mikro");
    $row = $q ? mysqli_fetch_assoc($q) : ['total' => 0];
    $total_pengajuan = (int) ($row['total'] ?? 0);
    $total_debitur   = $total_pengajuan; // tanpa kolom status, anggap semua dihitung sbg debitur
}

$realisasi_persen = $total_target > 0 ? round(($total_outstanding / $total_target) * 100, 1) : 0;
$npf_persen        = $total_outstanding > 0 ? round(($outstanding_npf / $total_outstanding) * 100, 2) : 0;

if ($tabelSurveyAda) {
    $qSurvey = mysqli_query($conn, "SELECT COUNT(*) AS total FROM nasabah_mikro WHERE survey_tanggal = CURDATE()");
    $rSurvey = $qSurvey ? mysqli_fetch_assoc($qSurvey) : ['total' => 0];
    $survey_hari_ini = (int) ($rSurvey['total'] ?? 0);
}

// ==========================================
// 2. GRAFIK: OUTSTANDING VS TARGET PER CABANG
// ==========================================
$cabang_label = [];
$cabang_outstanding = [];
$cabang_target = [];
if ($skemaLengkap) {
    $qCabang = mysqli_query($conn, "
        SELECT COALESCE(NULLIF(cabang, ''), 'Belum Diisi') AS cabang,
               SUM(COALESCE(outstanding, 0)) AS outstanding,
               SUM(COALESCE(target_outstanding, 0)) AS target
        FROM nasabah_mikro
        GROUP BY cabang
        ORDER BY outstanding DESC
        LIMIT 6
    ");
    if ($qCabang) {
        while ($r = mysqli_fetch_assoc($qCabang)) {
            $cabang_label[]       = $r['cabang'];
            $cabang_outstanding[] = (float) $r['outstanding'];
            $cabang_target[]      = (float) $r['target'];
        }
    }
}

// ==========================================
// 3. GRAFIK: PRODUKTIVITAS AO
// ==========================================
$ao_label = [];
$ao_jumlah = [];
if ($skemaLengkap) {
    $qAo = mysqli_query($conn, "
        SELECT COALESCE(NULLIF(nama_ao, ''), 'Belum Diisi') AS ao,
               COUNT(*) AS jumlah
        FROM nasabah_mikro
        GROUP BY nama_ao
        ORDER BY jumlah DESC
        LIMIT 8
    ");
    if ($qAo) {
        while ($r = mysqli_fetch_assoc($qAo)) {
            $ao_label[]  = $r['ao'];
            $ao_jumlah[] = (int) $r['jumlah'];
        }
    }
}

// ==========================================
// 4. GRAFIK: PERTUMBUHAN DEBITUR (6 BULAN TERAKHIR)
// ==========================================
$tren_label  = [];
$tren_jumlah = [];
for ($i = 5; $i >= 0; $i--) {
    $bulan_angka = date('m', strtotime("-$i months"));
    $tahun       = date('Y', strtotime("-$i months"));

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM nasabah_mikro WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    mysqli_stmt_bind_param($stmt, "ss", $bulan_angka, $tahun);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $tren_label[]  = date('M', strtotime("-$i months"));
    $tren_jumlah[] = (int) ($r['total'] ?? 0);
}

// ==========================================
// 5. PRODUK PEMBIAYAAN BSI MIKRO (KUR)
// ==========================================
$produkDefinisi = [
    'KUR Super Mikro' => ['plafon' => 's/d Rp 10 jt', 'warna' => '#10b981', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-700'],
    'KUR Mikro'       => ['plafon' => 'Rp 10 - 100 jt', 'warna' => '#6366f1', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700'],
    'KUR Kecil'       => ['plafon' => 'Rp 100 - 500 jt', 'warna' => '#0ea5e9', 'bg' => 'bg-sky-50', 'text' => 'text-sky-700'],
    'BSI Mikro iB'    => ['plafon' => 'Non-KUR / Modal Kerja', 'warna' => '#f59e0b', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700'],
];
$produkStat = [];
$produkOut  = [];
if ($skemaLengkap) {
    $qProduk = mysqli_query($conn, "
        SELECT COALESCE(produk, 'BSI Mikro iB') AS produk,
               COUNT(*) AS jml,
               SUM(COALESCE(outstanding, 0)) AS total_out,
               SUM(CASE WHEN status_pengajuan = 'Aktif' THEN 1 ELSE 0 END) AS aktif
        FROM nasabah_mikro
        GROUP BY produk
    ");
    if ($qProduk) {
        while ($r = mysqli_fetch_assoc($qProduk)) {
            $produkStat[$r['produk']] = ['jml' => (int)$r['jml'], 'out' => (float)$r['total_out'], 'aktif' => (int)$r['aktif']];
        }
    }
    foreach (array_keys($produkDefinisi) as $nama) {
        $produkOut[$nama] = $produkStat[$nama]['out'] ?? 0;
    }
}

// ==========================================
// 6. TABEL DATA UMKM TERBARU
// ==========================================
if ($skemaLengkap) {
    $qUmkm = mysqli_query($conn, "
        SELECT nama_usaha, jenis_sektor, COALESCE(produk, 'BSI Mikro iB') AS produk, alamat, omzet_bulanan, status_pengajuan, nama_ao, created_at
        FROM nasabah_mikro
        ORDER BY created_at DESC
        LIMIT 8
    ");
} else {
    $qUmkm = mysqli_query($conn, "
        SELECT nama_usaha, jenis_sektor, omzet_bulanan, created_at
        FROM nasabah_mikro
        ORDER BY created_at DESC
        LIMIT 8
    ");
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Dashboard Mikro | BSI Mikro', 'mikro'); ?>
    <style>
        .ring-wrap { position: relative; width: 84px; height: 84px; margin: 0 auto; }
        .ring-wrap canvas { position: relative; z-index: 1; }
        .ring-center { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 2; pointer-events: none; }
    </style>
</head>

<body class="text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/../../components/sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen relative w-full min-w-0">
      <div class="max-w-[1600px] mx-auto">

        <!-- TOPBAR: hamburger mobile + status koneksi + profil -->
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
            'title'    => 'Dashboard Sektor Mikro',
            'subtitle' => 'Kinerja pembiayaan UMKM, produktivitas AO, dan pipeline approval dalam satu pantauan terpadu.',
            'stats'    => [
                ['label' => 'Debitur Aktif', 'value' => number_format($total_debitur)],
                ['label' => 'Outstanding',   'value' => 'Rp ' . number_format($total_outstanding, 0, ',', '.')],
                ['label' => 'NPF Mikro',     'value' => number_format($npf_persen, 2) . '%'],
            ],
            'actions'  => ($role_user === 'admin' || $role_user === 'mikro')
                ? [['href' => 'mikro-tambah.php', 'label' => '+ Tambah Data UMKM']]
                : [],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="card border-amber-200 bg-amber-50 rounded-2xl px-4 sm:px-5 py-3.5 mb-6 flex items-start gap-3 text-amber-800">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" /><path d="M12 9v4" /><path d="M12 17h.01" />
                </svg>
                <p class="text-xs sm:text-sm leading-relaxed">
                    Skema database belum lengkap untuk sebagian KPI (Outstanding, Target AO, NPF, Cabang, dsb).
                    Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/002_mikro_dashboard_kpi.sql</code> agar seluruh KPI dan grafik tampil dengan data sebenarnya.
                </p>
            </div>
        <?php endif; ?>

        <!-- KPI ROW 1 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
            <?php
            premium_kpi_card([
                'label' => 'Total Debitur', 'value' => number_format($total_debitur),
                'sub' => 'dari ' . number_format($total_pengajuan) . ' pengajuan', 'tone' => 'indigo', 'delay' => 1,
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
            ]);
            premium_kpi_card([
                'label' => 'Outstanding', 'value' => 'Rp ' . number_format($total_outstanding, 0, ',', '.'),
                'sub' => 'Total baki debet aktif', 'tone' => 'emerald', 'delay' => 2,
                'icon' => '<rect x="2" y="5" width="20" height="14" rx="2" /><line x1="2" y1="10" x2="22" y2="10" />',
            ]);
            premium_kpi_card([
                'label' => 'Target AO', 'value' => 'Rp ' . number_format($total_target, 0, ',', '.'),
                'sub' => 'Akumulasi seluruh AO', 'tone' => 'sky', 'delay' => 3,
                'icon' => '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" />',
            ]);
            premium_kpi_card([
                'label' => 'Realisasi', 'value' => number_format($realisasi_persen, 1) . '%',
                'sub' => 'Outstanding vs Target',
                'sub_class' => $realisasi_persen >= 100 ? 'text-emerald-500' : 'text-amber-500',
                'tone' => $realisasi_persen >= 100 ? 'emerald' : 'amber', 'delay' => 4,
                'icon' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18" /><polyline points="17 6 23 6 23 12" />',
            ]);
            ?>
        </div>

        <!-- KPI ROW 2 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
            <?php
            premium_kpi_card([
                'label' => 'NPF Mikro', 'value' => number_format($npf_persen, 2) . '%',
                'value_class' => $npf_persen > 5 ? '!text-rose-600' : '',
                'sub' => 'Kolektibilitas 3-5 (kol ≥ 3)', 'tone' => 'rose', 'delay' => 1,
                'icon' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />',
            ]);
            premium_kpi_card([
                'label' => 'Survey Hari Ini', 'value' => number_format($survey_hari_ini),
                'sub' => 'Kunjungan lapangan AO', 'tone' => 'violet', 'delay' => 2,
                'icon' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z" /><circle cx="12" cy="10" r="3" />',
            ]);
            ?>
            <div class="pm-card pm-kpi pm-fade pm-d3 pm-card-hover col-span-1 sm:col-span-2 xl:col-span-1">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Approval</p>
                        <h3 class="mt-1.5 text-2xl sm:text-[26px] font-extrabold tracking-tight text-slate-900"><?= number_format($total_approval); ?></h3>
                        <a href="mikro-approval.php" class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-600 hover:underline">Menunggu persetujuan &rarr;</a>
                    </div>
                    <div class="bg-amber-50 text-amber-600 pm-kpi-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="m16 11 2 2 4-4" /></svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRODUK BSI MIKRO -->
        <div class="pm-card p-5 sm:p-6 mb-6 pm-fade pm-d2">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                <div>
                    <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Produk Pembiayaan BSI Mikro</h4>
                    <p class="text-[11px] text-slate-400 mt-0.5">Skema KUR &amp; BSI Mikro iB sesuai portofolio Bank Syariah Indonesia</p>
                </div>
                <a href="mikro-data.php" class="text-xs font-bold text-emerald-600 hover:underline">Kelola Data &rarr;</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
                <?php foreach ($produkDefinisi as $nama => $def): $st = $produkStat[$nama] ?? ['jml' => 0, 'out' => 0, 'aktif' => 0]; ?>
                    <a href="mikro-produk.php?p=<?= urlencode($nama); ?>" class="pm-card pm-card-hover !rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold <?= $def['bg']; ?> <?= $def['text']; ?>"><?= htmlspecialchars($nama); ?></span>
                            <span class="text-[10px] text-slate-400 font-medium"><?= $def['plafon']; ?></span>
                        </div>
                        <h3 class="text-lg sm:text-xl font-extrabold text-slate-900"><?= number_format($st['jml']); ?> <span class="text-[10px] font-semibold text-slate-400">debitur</span></h3>
                        <p class="text-[11px] text-slate-500 mt-1 truncate">Rp <?= number_format($st['out'], 0, ',', '.'); ?> <span class="text-slate-400">outstanding</span></p>
                        <div class="pm-progress mt-3" style="height:6px;">
                            <?php $pct = $total_outstanding > 0 ? round(($st['out'] / $total_outstanding) * 100) : 0; ?>
                            <span class="pm-progress-fill" style="width:<?= min(100, $pct); ?>%;background:<?= $def['warna']; ?>"></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RING KPI -->
        <div class="grid grid-cols-3 gap-5 mb-6">
            <div class="pm-card p-5 text-center pm-fade pm-d1">
                <div class="ring-wrap"><canvas id="ringRealisasi"></canvas><div class="ring-center"><span class="text-xs font-extrabold text-indigo-600"><?= number_format($realisasi_persen, 0); ?>%</span></div></div>
                <p class="mt-3 text-[11px] font-bold text-slate-500">Realisasi</p>
            </div>
            <div class="pm-card p-5 text-center pm-fade pm-d2">
                <div class="ring-wrap"><canvas id="ringNpf"></canvas><div class="ring-center"><span class="text-xs font-extrabold <?= $npf_persen > 5 ? 'text-rose-600' : 'text-emerald-600'; ?>"><?= number_format($npf_persen, 1); ?>%</span></div></div>
                <p class="mt-3 text-[11px] font-bold text-slate-500">NPF</p>
            </div>
            <div class="pm-card p-5 text-center pm-fade pm-d3">
                <div class="ring-wrap"><canvas id="ringDebitur"></canvas><div class="ring-center"><span class="text-xs font-extrabold text-sky-600"><?= $total_pengajuan > 0 ? round($total_debitur / $total_pengajuan * 100) : 0; ?>%</span></div></div>
                <p class="mt-3 text-[11px] font-bold text-slate-500">Debitur Aktif</p>
            </div>
        </div>

        <!-- GRAFIK -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-6">
            <div class="pm-card p-5 sm:p-6 min-h-[320px] flex flex-col pm-fade pm-d1">
                <div class="flex justify-between items-center mb-4 gap-2">
                    <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Outstanding vs Target per Cabang</h4>
                </div>
                <div class="relative flex-1 h-64 sm:h-72">
                    <canvas id="cabangChart"></canvas>
                </div>
            </div>

            <div class="pm-card p-5 sm:p-6 min-h-[320px] flex flex-col pm-fade pm-d2">
                <div class="flex justify-between items-center mb-4 gap-2">
                    <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Produktivitas AO</h4>
                    <span class="text-[10px] bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-md text-slate-500 font-bold whitespace-nowrap">Jumlah Debitur</span>
                </div>
                <div class="relative flex-1 h-64 sm:h-72">
                    <canvas id="aoChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-5 mb-6">
            <div class="pm-card p-5 sm:p-6 xl:col-span-3 min-h-[300px] flex flex-col justify-between pm-fade pm-d1">
                <div class="flex justify-between items-center mb-4 gap-2">
                    <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Pertumbuhan Debitur</h4>
                    <span class="text-[10px] bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-md text-slate-500 font-bold whitespace-nowrap">6 Bulan Terakhir</span>
                </div>
                <div class="relative flex-1 h-56 sm:h-72">
                    <canvas id="trenChart"></canvas>
                </div>
            </div>

            <div class="pm-card p-5 sm:p-6 xl:col-span-1 flex flex-col justify-start gap-3 pm-fade pm-d2">
                <h4 class="text-sm font-extrabold tracking-tight text-slate-800 mb-1">Aksi Cepat</h4>
                <?php if ($role_user === 'admin' || $role_user === 'mikro'): ?>
                    <a href="mikro-tambah.php" class="pm-btn pm-btn-primary w-full">+ Tambah Data UMKM</a>
                <?php endif; ?>
                <a href="mikro-survey.php" class="pm-btn pm-btn-ghost w-full">Survey Lapangan</a>
                <a href="mikro-approval.php" class="w-full inline-flex justify-center items-center gap-2 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold py-3 px-4 rounded-xl border border-amber-200 transition-all cursor-pointer">Cek Approval</a>
                <a href="mikro-laporan.php" class="pm-btn pm-btn-ghost w-full">Unduh Laporan</a>
            </div>
        </div>

        <!-- TABEL DATA UMKM -->
        <div class="pm-card overflow-hidden pm-fade pm-d2">
            <div class="flex justify-between items-center px-4 sm:px-6 pt-6 pb-4">
                <h4 class="text-sm font-extrabold tracking-tight text-slate-800">Data UMKM Terbaru</h4>
                <a href="mikro-data.php" class="text-xs font-bold text-emerald-600 hover:underline">Lihat Semua &rarr;</a>
            </div>
            <div class="pm-table-wrap pm-scroll">
                <table class="pm-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Nama Usaha</th>
                            <th>Produk BSI</th>
                            <th>Jenis Usaha</th>
                            <th>Alamat</th>
                            <th>Omzet</th>
                            <th>Status</th>
                            <th>AO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($qUmkm && mysqli_num_rows($qUmkm) > 0): ?>
                            <?php while ($u = mysqli_fetch_assoc($qUmkm)): ?>
                                <?php
                                $status = $u['status_pengajuan'] ?? '—';
                                if ($status === 'Aktif') {
                                    $badgeStatus = 'bg-emerald-50 text-emerald-600 border border-emerald-100';
                                } elseif ($status === 'Approval') {
                                    $badgeStatus = 'bg-amber-50 text-amber-600 border border-amber-100';
                                } elseif ($status === 'Ditolak') {
                                    $badgeStatus = 'bg-rose-50 text-rose-600 border border-rose-100';
                                } elseif ($status === 'Macet') {
                                    $badgeStatus = 'bg-slate-200 text-slate-600 border border-slate-300';
                                } else {
                                    $badgeStatus = 'bg-slate-50 text-slate-500 border border-slate-100';
                                }
                                ?>
                                <tr>
                                    <td class="font-bold text-slate-700 whitespace-nowrap"><?= htmlspecialchars($u['nama_usaha'] ?? '-'); ?></td>
                                    <td>
                                        <?php $prd = $u['produk'] ?? 'BSI Mikro iB'; $def = $produkDefinisi[$prd] ?? $produkDefinisi['BSI Mikro iB']; ?>
                                        <span class="pm-badge <?= $def['bg']; ?> <?= $def['text']; ?>"><?= htmlspecialchars($prd); ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($u['jenis_sektor'] ?? '-'); ?></td>
                                    <td class="truncate max-w-[220px]"><?= htmlspecialchars($u['alamat'] ?? '—'); ?></td>
                                    <td class="text-slate-700 font-semibold whitespace-nowrap">Rp <?= number_format($u['omzet_bulanan'] ?? 0, 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="pm-badge <?= $badgeStatus; ?>"><?= htmlspecialchars($status); ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($u['nama_ao'] ?? '—'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-xs text-slate-400 italic">Belum ada data UMKM di database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
        function toggleProfilePopup(event) {
            event.stopPropagation();
            document.getElementById('profilePopup').classList.toggle('hidden');
        }
        document.addEventListener('click', function(event) {
            const popup = document.getElementById('profilePopup');
            if (popup && !popup.classList.contains('hidden')) popup.classList.add('hidden');
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function ringChart(id, value, color) {
            new Chart(document.getElementById(id).getContext('2d'), {
                type: 'doughnut',
                data: { datasets: [{ data: [value, Math.max(0, 100 - value)], backgroundColor: [color, '#eef2f7'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
            });
        }
        ringChart('ringRealisasi', <?= min(100, $realisasi_persen); ?>, '#6366f1');
        ringChart('ringNpf', <?= min(100, $npf_persen); ?>, <?= $npf_persen > 5 ? "'#f43f5e'" : "'#10b981'"; ?>);
        ringChart('ringDebitur', <?= $total_pengajuan > 0 ? round($total_debitur / $total_pengajuan * 100) : 0; ?>, '#38bdf8');

        // Outstanding vs Target per Cabang
        new Chart(document.getElementById('cabangChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($cabang_label); ?>,
                datasets: [
                    { label: 'Outstanding', data: <?= json_encode($cabang_outstanding); ?>, backgroundColor: '#6366f1', borderRadius: 6, maxBarThickness: 28 },
                    { label: 'Target', data: <?= json_encode($cabang_target); ?>, backgroundColor: '#c7d2fe', borderRadius: 6, maxBarThickness: 28 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 11 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans' } } },
                    y: { grid: { color: 'rgba(15, 23, 42, 0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans' } } }
                }
            }
        });

        // Produktivitas AO
        new Chart(document.getElementById('aoChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($ao_label); ?>,
                datasets: [{ label: 'Jumlah Debitur', data: <?= json_encode($ao_jumlah); ?>, backgroundColor: '#38bdf8', borderRadius: 6, maxBarThickness: 22 }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(15, 23, 42, 0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans' } } },
                    y: { grid: { display: false }, ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 11 } } }
                }
            }
        });

        // Pertumbuhan Debitur
        const ctxTren = document.getElementById('trenChart').getContext('2d');
        const grad = ctxTren.createLinearGradient(0, 0, 0, 300);
        grad.addColorStop(0, 'rgba(99, 102, 241, 0.18)');
        grad.addColorStop(1, 'rgba(99, 102, 241, 0.0)');
        new Chart(ctxTren, {
            type: 'line',
            data: {
                labels: <?= json_encode($tren_label); ?>,
                datasets: [{
                    label: 'Debitur Baru',
                    data: <?= json_encode($tren_jumlah); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointBackgroundColor: '#6366f1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 11 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans' } } },
                    y: { grid: { color: 'rgba(15, 23, 42, 0.04)' }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans' } } }
                }
            }
        });
    </script>
</body>

</html>
