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
$current_page = 'analisa';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'kolektibilitas'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

/**
 * Skor kelayakan sederhana (0-100) berbasis rasio plafon terhadap omzet,
 * lama usaha, dan kolektibilitas berjalan -- dipakai untuk memberi sinyal cepat
 * ke petugas mana yang layak diprioritaskan / perlu direview ulang.
 */
function hitungSkorKelayakan(float $omzet, float $plafon, int $lamaUsaha, int $kol): array
{
    $skor = 50;

    // Rasio plafon terhadap omzet bulanan: makin kecil rasio makin sehat.
    $rasio = $omzet > 0 ? ($plafon / $omzet) : 99;
    if ($rasio <= 3) $skor += 25;
    elseif ($rasio <= 6) $skor += 10;
    elseif ($rasio <= 10) $skor -= 5;
    else $skor -= 20;

    // Lama usaha: makin lama makin stabil.
    if ($lamaUsaha >= 5) $skor += 15;
    elseif ($lamaUsaha >= 2) $skor += 7;
    else $skor -= 5;

    // Kolektibilitas berjalan.
    if ($kol === 1) $skor += 10;
    elseif ($kol === 2) $skor -= 5;
    else $skor -= 25;

    $skor = max(0, min(100, $skor));
    if ($skor >= 70) $label = ['Layak', 'text-emerald-600', 'bg-emerald-50'];
    elseif ($skor >= 45) $label = ['Perlu Review', 'text-amber-600', 'bg-amber-50'];
    else $label = ['Berisiko', 'text-rose-600', 'bg-rose-50'];

    return ['skor' => $skor, 'rasio' => $rasio, 'label' => $label[0], 'text' => $label[1], 'bg' => $label[2]];
}

$sektorAnalisa = [];
$trendBulanan = [];
$topLayak = [];
$topBerisiko = [];
$rataRasio = 0;

if ($skemaLengkap) {
    // Analisa per sektor: rata-rata omzet, rata-rata rasio plafon/omzet, jumlah debitur.
    $res = mysqli_query($conn, "SELECT jenis_sektor, COUNT(*) AS jml, AVG(omzet_bulanan) AS avg_omzet,
                                        AVG(CASE WHEN omzet_bulanan > 0 THEN plafon_pembiayaan/omzet_bulanan ELSE NULL END) AS avg_rasio,
                                        SUM(outstanding) AS total_os
                                 FROM nasabah_mikro
                                 WHERE status_pengajuan != 'Ditolak'
                                 GROUP BY jenis_sektor
                                 ORDER BY jml DESC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $sektorAnalisa[] = $r;
        }
    }

    // Tren jumlah pengajuan 6 bulan terakhir.
    $res = mysqli_query($conn, "SELECT DATE_FORMAT(created_at, '%Y-%m') AS bln, COUNT(*) AS jml
                                 FROM nasabah_mikro
                                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                                 GROUP BY bln ORDER BY bln ASC");
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $trendBulanan[$r['bln']] = (int) $r['jml'];
        }
    }
    // Pastikan 6 bulan terakhir tetap muncul walau datanya 0.
    $trendLengkap = [];
    for ($i = 5; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-$i months"));
        $trendLengkap[$key] = $trendBulanan[$key] ?? 0;
    }
    $trendBulanan = $trendLengkap;

    // Hitung skor kelayakan untuk seluruh nasabah aktif/approval, ambil top & bottom 5.
    $res = mysqli_query($conn, "SELECT id, nama_pemilik, nama_usaha, jenis_sektor, omzet_bulanan, plafon_pembiayaan, lama_usaha_tahun, kolektibilitas
                                 FROM nasabah_mikro WHERE status_pengajuan != 'Ditolak'");
    $semuaSkor = [];
    $totalRasio = 0;
    $nRasio = 0;
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $h = hitungSkorKelayakan((float) $r['omzet_bulanan'], (float) $r['plafon_pembiayaan'], (int) $r['lama_usaha_tahun'], (int) $r['kolektibilitas']);
            $r = array_merge($r, $h);
            $semuaSkor[] = $r;
            if ($h['rasio'] < 99) { $totalRasio += $h['rasio']; $nRasio++; }
        }
    }
    $rataRasio = $nRasio > 0 ? $totalRasio / $nRasio : 0;

    usort($semuaSkor, fn($a, $b) => $b['skor'] <=> $a['skor']);
    $topLayak = array_slice($semuaSkor, 0, 5);
    $sorted2 = $semuaSkor;
    usort($sorted2, fn($a, $b) => $a['skor'] <=> $b['skor']);
    $topBerisiko = array_slice($sorted2, 0, 5);
}

$maxTrend = max(1, ...array_values($trendBulanan ?: [1]));
$bulanIndo = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Analisa Pembiayaan | BSI Mikro', 'mikro'); ?>
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
            'title'    => 'Analisa Pembiayaan',
            'subtitle' => 'Analisa mendalam performa portofolio, tren omzet nasabah, dan skor kelayakan.',
            'stats'    => [
                ['label' => 'Rasio Rata-rata', 'value' => number_format($rataRasio, 1) . 'x'],
                ['label' => 'Sektor', 'value' => count($sektorAnalisa)],
                ['label' => 'Pengajuan Bulan Ini', 'value' => end($trendBulanan)],
            ],
        ]); ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/002_mikro_dashboard_kpi.sql</code> agar data analisa tersedia.
            </div>
        <?php else: ?>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <?php premium_kpi_card(['tone' => 'emerald', 'delay' => 1, 'label' => 'Rata-rata Rasio Plafon/Omzet', 'value' => number_format($rataRasio, 1) . 'x', 'sub' => 'Idealnya &le; 3x omzet bulanan']); ?>
                <?php premium_kpi_card(['tone' => 'sky', 'delay' => 2, 'label' => 'Sektor Teranalisa', 'value' => count($sektorAnalisa), 'sub' => 'sektor']); ?>
                <?php premium_kpi_card(['tone' => 'indigo', 'delay' => 3, 'label' => 'Pengajuan Bulan Ini', 'value' => end($trendBulanan)]); ?>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <!-- Tren pengajuan -->
                <div class="pm-card p-5 pm-fade pm-d1">
                    <h4 class="text-sm font-bold text-slate-800 mb-4">Tren Pengajuan 6 Bulan Terakhir</h4>
                    <div class="flex items-end gap-3 h-40">
                        <?php foreach ($trendBulanan as $bln => $jml):
                            [$y, $m] = explode('-', $bln);
                            $h = $jml > 0 ? max(6, ($jml / $maxTrend) * 100) : 3;
                        ?>
                            <div class="flex-1 flex flex-col items-center justify-end h-full">
                                <span class="text-[11px] font-bold text-slate-600 mb-1"><?= $jml; ?></span>
                                <div class="w-full bg-indigo-500 rounded-t-md" style="height: <?= $h; ?>%"></div>
                                <span class="text-[10px] text-slate-400 mt-1.5"><?= $bulanIndo[$m] ?? $m; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Analisa per sektor -->
                <div class="pm-card p-5 pm-fade pm-d2">
                    <h4 class="text-sm font-bold text-slate-800 mb-4">Rasio Plafon/Omzet per Sektor</h4>
                    <div class="space-y-3">
                        <?php if (empty($sektorAnalisa)): ?>
                            <p class="text-xs text-slate-400 italic">Belum ada data.</p>
                        <?php endif; ?>
                        <?php foreach ($sektorAnalisa as $s):
                            $rasio = (float) ($s['avg_rasio'] ?? 0);
                            $pct = min(100, ($rasio / 10) * 100);
                            $warna = $rasio <= 3 ? '#10b981' : ($rasio <= 6 ? '#f59e0b' : '#f43f5e');
                        ?>
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($s['jenis_sektor'] ?: 'Lainnya'); ?> <span class="text-slate-400 font-normal">(<?= $s['jml']; ?> debitur)</span></span>
                                    <span class="font-semibold text-slate-600"><?= number_format($rasio, 1); ?>x</span>
                                </div>
                                <div class="pm-progress" style="height:6px;">
                                    <div class="pm-progress-fill" style="width: <?= $pct; ?>%;background:<?= $warna; ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Top layak -->
                <div class="pm-card overflow-hidden pm-fade pm-d3">
                    <div class="px-5 pt-5 pb-3">
                        <h4 class="text-sm font-bold text-slate-800">5 Debitur Skor Kelayakan Tertinggi</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Kandidat prioritas ekspansi plafon / referensi cross-sell.</p>
                    </div>
                    <div class="divide-y divide-slate-50">
                        <?php foreach ($topLayak as $t): ?>
                            <div class="flex items-center gap-3 px-5 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-slate-800 truncate"><?= htmlspecialchars($t['nama_pemilik']); ?> &middot; <?= htmlspecialchars($t['nama_usaha']); ?></p>
                                    <p class="text-[11px] text-slate-400"><?= htmlspecialchars($t['jenis_sektor'] ?: '-'); ?> &middot; Rasio <?= number_format($t['rasio'], 1); ?>x</p>
                                </div>
                                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full <?= $t['bg']; ?> <?= $t['text']; ?>"><?= $t['skor']; ?> &middot; <?= $t['label']; ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($topLayak)): ?><div class="px-5 py-6 text-xs text-slate-400 italic">Belum ada data.</div><?php endif; ?>
                    </div>
                </div>

                <!-- Top berisiko -->
                <div class="pm-card overflow-hidden pm-fade pm-d4">
                    <div class="px-5 pt-5 pb-3">
                        <h4 class="text-sm font-bold text-slate-800">5 Debitur Perlu Perhatian</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Skor kelayakan terendah -- prioritas review / pendampingan.</p>
                    </div>
                    <div class="divide-y divide-slate-50">
                        <?php foreach ($topBerisiko as $t): ?>
                            <div class="flex items-center gap-3 px-5 py-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-slate-800 truncate"><?= htmlspecialchars($t['nama_pemilik']); ?> &middot; <?= htmlspecialchars($t['nama_usaha']); ?></p>
                                    <p class="text-[11px] text-slate-400"><?= htmlspecialchars($t['jenis_sektor'] ?: '-'); ?> &middot; Rasio <?= number_format($t['rasio'], 1); ?>x</p>
                                </div>
                                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full <?= $t['bg']; ?> <?= $t['text']; ?>"><?= $t['skor']; ?> &middot; <?= $t['label']; ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($topBerisiko)): ?><div class="px-5 py-6 text-xs text-slate-400 italic">Belum ada data.</div><?php endif; ?>
                    </div>
                </div>
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
