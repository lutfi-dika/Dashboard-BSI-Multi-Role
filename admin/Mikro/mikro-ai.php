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
$current_page = 'ai-assistant';

if (!in_array($role_user, ['admin', 'mikro', 'atasan'], true)) {
    header('Location: ../../index.php');
    exit();
}

$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'status_pengajuan'");
$skemaLengkap = $cekKolom && mysqli_num_rows($cekKolom) > 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['mikro_ai_chat']) || !is_array($_SESSION['mikro_ai_chat'])) {
    $_SESSION['mikro_ai_chat'] = [];
}

/**
 * Asisten berbasis aturan (rule-based) yang menjawab pertanyaan seputar
 * portofolio Mikro langsung dari database -- tanpa memanggil API pihak ketiga,
 * supaya jawaban selalu akurat sesuai data terkini dan tidak butuh API key.
 */
function jawabPertanyaanMikro(mysqli $conn, bool $skemaLengkap, string $q): string
{
    $qq = mb_strtolower($q);

    if (!$skemaLengkap) {
        return 'Skema database belum lengkap. Jalankan migrasi sql/002_mikro_dashboard_kpi.sql terlebih dahulu agar saya bisa membaca data portofolio.';
    }

    // NPF / kolektibilitas
    if (str_contains($qq, 'npf') || str_contains($qq, 'kolektibilitas') || str_contains($qq, 'macet')) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(CASE WHEN kolektibilitas >= 3 THEN outstanding ELSE 0 END) AS npf, SUM(outstanding) AS total FROM nasabah_mikro WHERE status_pengajuan='Aktif'"));
        $total = (float) ($r['total'] ?? 0);
        $npf = (float) ($r['npf'] ?? 0);
        $ratio = $total > 0 ? ($npf / $total) * 100 : 0;
        return "Rasio NPF portofolio Mikro saat ini <b>" . number_format($ratio, 2) . "%</b> (outstanding NPF Rp " . number_format($npf, 0, ',', '.') . " dari total Rp " . number_format($total, 0, ',', '.') . "). " . ($ratio > 5 ? 'Ini di atas ambang aman 5%, perlu tindak lanjut penagihan.' : 'Masih dalam batas aman (di bawah 5%).');
    }

    // Outstanding
    if (str_contains($qq, 'outstanding')) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml, SUM(outstanding) AS total FROM nasabah_mikro WHERE status_pengajuan='Aktif'"));
        return "Total outstanding aktif saat ini <b>Rp " . number_format((float) ($r['total'] ?? 0), 0, ',', '.') . "</b> dari " . (int) ($r['jml'] ?? 0) . " nasabah Aktif.";
    }

    // Approval / pengajuan menunggu
    if (str_contains($qq, 'approval') || str_contains($qq, 'menunggu') || str_contains($qq, 'antrean') || str_contains($qq, 'pending')) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan='Approval'"));
        $jml = (int) ($r['jml'] ?? 0);
        return $jml > 0
            ? "Ada <b>{$jml} pengajuan</b> yang masih menunggu approval. Cek halaman Approval untuk memprosesnya."
            : 'Tidak ada pengajuan yang menunggu approval saat ini. Antrean bersih.';
    }

    // Jatuh tempo
    if (str_contains($qq, 'jatuh tempo') || str_contains($qq, 'tempo')) {
        $cekTgl = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'tanggal_jatuh_tempo'");
        if (!$cekTgl || mysqli_num_rows($cekTgl) === 0) return 'Data jatuh tempo belum tersedia (jalankan migrasi sql/003_mikro_dokumen.sql).';
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan='Aktif' AND tanggal_jatuh_tempo IS NOT NULL AND DATEDIFF(tanggal_jatuh_tempo, CURDATE()) BETWEEN 0 AND 30"));
        $jml = (int) ($r['jml'] ?? 0);
        return "<b>{$jml} nasabah</b> akan jatuh tempo dalam 30 hari ke depan. " . ($jml > 0 ? 'Segera lakukan penagihan proaktif.' : '');
    }

    // Sektor dominan
    if (str_contains($qq, 'sektor')) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT jenis_sektor, COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan='Aktif' GROUP BY jenis_sektor ORDER BY jml DESC LIMIT 1"));
        if (!$r) return 'Belum ada data sektor usaha nasabah aktif.';
        return "Sektor usaha dominan di portofolio Mikro adalah <b>" . htmlspecialchars($r['jenis_sektor']) . "</b> dengan {$r['jml']} debitur aktif.";
    }

    // AO terbaik / performa AO
    if (str_contains($qq, 'ao') && (str_contains($qq, 'terbaik') || str_contains($qq, 'performa') || str_contains($qq, 'top'))) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_ao, COUNT(*) AS jml, SUM(outstanding) AS os FROM nasabah_mikro WHERE status_pengajuan='Aktif' AND nama_ao IS NOT NULL AND nama_ao<>'' GROUP BY nama_ao ORDER BY os DESC LIMIT 1"));
        if (!$r) return 'Belum ada data AO yang bisa dibandingkan.';
        return "AO dengan outstanding tertinggi adalah <b>" . htmlspecialchars($r['nama_ao']) . "</b>, mengelola {$r['jml']} nasabah dengan total outstanding Rp " . number_format((float) $r['os'], 0, ',', '.') . ".";
    }

    // Total nasabah / debitur
    if (str_contains($qq, 'jumlah nasabah') || str_contains($qq, 'jumlah debitur') || str_contains($qq, 'total nasabah') || str_contains($qq, 'berapa nasabah')) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status_pengajuan, COUNT(*) AS jml FROM nasabah_mikro GROUP BY status_pengajuan"));
        $res = mysqli_query($conn, "SELECT status_pengajuan, COUNT(*) AS jml FROM nasabah_mikro GROUP BY status_pengajuan");
        $parts = [];
        while ($row = mysqli_fetch_assoc($res)) $parts[] = $row['jml'] . ' ' . $row['status_pengajuan'];
        return 'Rincian jumlah nasabah Mikro: ' . (empty($parts) ? 'belum ada data.' : implode(', ', $parts) . '.');
    }

    // Dokumen
    if (str_contains($qq, 'dokumen')) {
        $cek = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'dok_ktp'");
        if (!$cek || mysqli_num_rows($cek) === 0) return 'Data kelengkapan dokumen belum tersedia (jalankan migrasi sql/003_mikro_dokumen.sql).';
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS jml FROM nasabah_mikro WHERE status_pengajuan != 'Ditolak' AND (dok_ktp=0 OR dok_kk=0 OR dok_izin_usaha=0 OR dok_foto_usaha=0)"));
        $jml = (int) ($r['jml'] ?? 0);
        return "<b>{$jml} nasabah</b> masih memiliki dokumen yang belum lengkap. Cek halaman Dokumen untuk detailnya.";
    }

    // Target
    if (str_contains($qq, 'target')) {
        $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(outstanding) AS os, SUM(target_outstanding) AS target FROM nasabah_mikro WHERE status_pengajuan='Aktif'"));
        $os = (float) ($r['os'] ?? 0);
        $target = (float) ($r['target'] ?? 0);
        $pct = $target > 0 ? ($os / $target) * 100 : 0;
        return "Realisasi outstanding terhadap target saat ini <b>" . number_format($pct, 1) . "%</b> (Rp " . number_format($os, 0, ',', '.') . " dari target Rp " . number_format($target, 0, ',', '.') . ").";
    }

    return 'Maaf, saya belum punya jawaban spesifik untuk itu. Coba tanyakan tentang: rasio NPF, outstanding, pengajuan approval, jatuh tempo, sektor dominan, performa AO, jumlah nasabah, kelengkapan dokumen, atau realisasi target.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF Token Invalid');
    }
    if (isset($_POST['reset'])) {
        $_SESSION['mikro_ai_chat'] = [];
    } elseif (isset($_POST['pertanyaan']) && trim($_POST['pertanyaan']) !== '') {
        $pertanyaan = trim($_POST['pertanyaan']);
        $jawaban = jawabPertanyaanMikro($conn, $skemaLengkap, $pertanyaan);
        $_SESSION['mikro_ai_chat'][] = ['q' => $pertanyaan, 'a' => $jawaban, 't' => date('H:i')];
        // Batasi riwayat maksimal 20 percakapan terakhir supaya session tidak membengkak
        if (count($_SESSION['mikro_ai_chat']) > 20) {
            $_SESSION['mikro_ai_chat'] = array_slice($_SESSION['mikro_ai_chat'], -20);
        }
    }
    header('Location: mikro-ai.php');
    exit();
}

$chatHistory = $_SESSION['mikro_ai_chat'];
$quickQuestions = [
    'Berapa rasio NPF saat ini?',
    'Total outstanding aktif berapa?',
    'Ada berapa pengajuan menunggu approval?',
    'Sektor usaha apa yang dominan?',
    'Siapa AO dengan performa terbaik?',
    'Berapa nasabah jatuh tempo 30 hari ke depan?',
    'Bagaimana kelengkapan dokumen nasabah?',
    'Berapa realisasi target outstanding?',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('AI Assistant | BSI Mikro', 'mikro'); ?>
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
            'title'    => 'AI Assistant',
            'subtitle' => 'Tanya jawab cepat seputar kondisi portofolio Mikro, dijawab langsung dari data terkini.',
            'stats'    => [['label' => 'Percakapan', 'value' => count($chatHistory)]],
        ]); ?>

        <?php if (!empty($chatHistory)): ?>
            <form method="POST" class="flex justify-end -mt-2 mb-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="reset" value="1">
                <button type="submit" class="pm-btn pm-btn-ghost">Bersihkan Obrolan</button>
            </form>
        <?php endif; ?>

        <?php if (!$skemaLengkap): ?>
            <div class="pm-card border-amber-200 bg-amber-50 rounded-2xl px-5 py-3.5 mb-5 text-xs sm:text-sm text-amber-800">
                Jalankan migrasi <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono">sql/002_mikro_dashboard_kpi.sql</code> agar asisten bisa membaca data portofolio.
            </div>
        <?php endif; ?>

        <div class="pm-card rounded-2xl shadow-xs flex flex-col h-[60vh] min-h-[420px]">
            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 text-xs font-bold">AI</div>
                    <div class="bg-indigo-50 text-indigo-900 text-xs sm:text-sm rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%]">
                        Halo <?= htmlspecialchars($nama_user); ?>! Saya bisa bantu ringkas kondisi portofolio Mikro: NPF, outstanding, approval, jatuh tempo, sektor, performa AO, dokumen, sampai realisasi target. Silakan pilih pertanyaan cepat atau ketik sendiri di bawah.
                    </div>
                </div>
                <?php foreach ($chatHistory as $c): ?>
                    <div class="flex items-start gap-3 justify-end">
                        <div class="bg-slate-800 text-white text-xs sm:text-sm rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[85%]"><?= htmlspecialchars($c['q']); ?></div>
                        <div class="w-8 h-8 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 text-xs font-bold"><?= strtoupper(substr($nama_user, 0, 1)); ?></div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 text-xs font-bold">AI</div>
                        <div class="bg-indigo-50 text-indigo-900 text-xs sm:text-sm rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[85%]"><?= $c['a']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="border-t border-slate-100 p-4">
                <div class="flex flex-wrap gap-2 mb-3">
                    <?php foreach ($quickQuestions as $qq): ?>
                        <button type="button" onclick="askQuick(this)" data-q="<?= htmlspecialchars($qq); ?>" class="text-[11px] font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-full px-3 py-1.5 transition"><?= htmlspecialchars($qq); ?></button>
                    <?php endforeach; ?>
                </div>
                <form method="POST" class="flex items-center gap-2" id="chatForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="text" name="pertanyaan" id="pertanyaanInput" required placeholder="Ketik pertanyaan seputar portofolio Mikro..." class="flex-1 text-sm rounded-xl border border-slate-200 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <button type="submit" class="pm-btn pm-btn-primary shrink-0">Kirim</button>
                </form>
            </div>
        </div>
    </main>

    <script>
        function askQuick(btn) {
            document.getElementById('pertanyaanInput').value = btn.getAttribute('data-q');
            document.getElementById('chatForm').submit();
        }
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
        // Auto-scroll ke bawah setiap kali halaman dimuat (setelah kirim pertanyaan)
        window.addEventListener('load', function() {
            const box = document.querySelector('main .overflow-y-auto');
            if (box) box.scrollTop = box.scrollHeight;
        });
    </script>
</body>

</html>
