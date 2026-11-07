<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

// Proteksi Hak Akses
if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../auth/login.php");
    exit();
}

$role_user = $_SESSION['role'] ?? '';
if (!in_array($role_user, ['admin', 'pawning', 'atasan'], true)) {
    header("Location: ../../index.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role_user === 'atasan') {
    header("Location: pawning-tambah.php");
    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$notifikasi = "";
$statusValid = ['Aktif', 'Lunas', 'Lelang'];
$jenisGadaiValid = ['Gadai Emas iB', 'Gadai Non Emas', 'Fleksi Emas iB'];
$jenisGadaiPreselect = $_GET['p'] ?? '';
if (!in_array($jenisGadaiPreselect, $jenisGadaiValid, true)) {
    $jenisGadaiPreselect = 'Gadai Emas iB';
}
$form = ['nama_pemilik' => '', 'barang_jaminan' => '', 'jenis_gadai' => '', 'taksiran_harga' => '', 'nilai_pinjaman' => '', 'status' => 'Aktif', 'no_telepon' => ''];

function renderAlert(string $type, string $title, string $message): string
{
    $isSuccess = $type === 'success';
    $bg = $isSuccess
        ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
        : 'bg-rose-50 border-rose-200 text-rose-800';
    $icon = $isSuccess
        ? '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />'
        : '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
    return '<div class="mb-6 p-4 border rounded-lg text-sm flex items-start gap-3 ' . $bg . '">' .
        '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 shrink-0 mt-0.5">' . $icon . '</svg>' .
        '<div><span class="font-semibold block">' . htmlspecialchars($title) . '</span><span class="text-[13px] opacity-90">' . $message . '</span></div>' .
        '</div>';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF Token Invalid");
    }

    $form['nama_pemilik']    = trim($_POST['nama_pemilik'] ?? '');
    $form['barang_jaminan']  = trim($_POST['barang_jaminan'] ?? '');
    $form['jenis_gadai']     = trim($_POST['jenis_gadai'] ?? '');
    $form['taksiran_harga']  = trim($_POST['taksiran_harga_asli'] ?? ''); // dari input hidden
    $form['nilai_pinjaman']  = trim($_POST['nilai_pinjaman_asli'] ?? ''); // dari input hidden
    $form['status']          = trim($_POST['status'] ?? '');
    $form['no_telepon']      = trim($_POST['no_telepon'] ?? '');

    $errors = [];
    if (empty($form['nama_pemilik'])) $errors[] = "Nama pemilik wajib diisi.";
    if (empty($form['barang_jaminan'])) $errors[] = "Barang jaminan wajib diisi.";
    if (!in_array($form['jenis_gadai'], $jenisGadaiValid, true)) $errors[] = "Jenis gadai tidak valid.";
    if (!is_numeric($form['taksiran_harga'])) $errors[] = "Taksiran harga harus berupa angka.";
    if (!is_numeric($form['nilai_pinjaman'])) $errors[] = "Nilai pinjaman harus berupa angka.";
    if (!in_array($form['status'], $statusValid, true)) $errors[] = "Status tidak valid.";

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pawning (nama_pemilik, barang_jaminan, jenis_gadai, taksiran_harga, nilai_pinjaman, status, no_telepon, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $userId = (int)$_SESSION['user_id'];
        mysqli_stmt_bind_param(
            $stmt,
            "sssddssi",
            $form['nama_pemilik'],
            $form['barang_jaminan'],
            $form['jenis_gadai'],
            $form['taksiran_harga'],
            $form['nilai_pinjaman'],
            $form['status'],
            $form['no_telepon'],
            $userId
        );

        if (mysqli_stmt_execute($stmt)) {
            $notifikasi = renderAlert('success', 'Berhasil', 'Data gadai tersimpan ke sistem.');
            $form = ['nama_pemilik' => '', 'barang_jaminan' => '', 'jenis_gadai' => '', 'taksiran_harga' => '', 'nilai_pinjaman' => '', 'status' => 'Aktif', 'no_telepon' => ''];
        } else {
            $notifikasi = renderAlert('error', 'Gagal', 'Terjadi kesalahan database. Silakan coba lagi.');
        }
    } else {
        $notifikasi = renderAlert('error', 'Periksa kembali isian Anda', implode('<br>', $errors));
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php premium_head('Input Data Gadai | BSI Pawning', 'pawning'); ?>
    <style>
        :root {
            --bg: #FFFFFF;
            --surface: #FFFFFF;
            --border: #E4E7EC;
            --border-strong: #D0D5DD;
            --text: #101828;
            --muted: #667085;
            --accent: #D97706;
            --accent-hover: #B45309;
        }

        body {
            background: var(--bg);
            color: var(--text);
        }

        .field-label {
            font-size: 13px;
            font-weight: 600;
            color: #344054;
        }

        .field-input {
            width: 100%;
            background: #FFFFFF;
            border: 1px solid var(--border-strong);
            color: var(--text);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            transition: border-color .12s ease, box-shadow .12s ease;
        }

        .field-input::placeholder {
            color: #98A2B3;
        }

        .field-input:hover {
            border-color: #98A2B3;
        }

        .field-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.12);
        }

        select.field-input {
            appearance: none;
            -webkit-appearance: none;
        }

        .select-wrap {
            position: relative;
        }

        .select-wrap svg {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--muted);
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: #475467;
            padding-bottom: 8px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .progress-track {
            height: 6px;
            border-radius: 999px;
            background: #EAECF0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: var(--accent);
            transition: width .3s ease;
        }

        .rp-prefix {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            font-weight: 500;
            color: var(--muted);
        }
    </style>
</head>

<body class="min-h-screen px-4 py-10 sm:py-14">
    <div class="max-w-2xl mx-auto">

        <!-- Header -->
        <div class="mb-6 px-1">
            <a href="pawning-dashboard.php" class="inline-flex items-center gap-1.5 text-[13px] font-medium mb-3 text-slate-500 hover:text-amber-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Kembali ke Dashboard
            </a>
            <h1 class="text-xl font-bold text-slate-900 leading-tight">Formulir Pendataan Gadai</h1>
            <p class="text-sm text-slate-500 mt-1">Lengkapi data nasabah dan barang jaminan secara akurat</p>
        </div>

        <!-- Card -->
        <div class="pm-card p-6 sm:p-8 pm-fade pm-d1">

            <!-- Completeness -->
            <div class="mb-7">
                <div class="flex items-center justify-between mb-2">
                    <span class="field-label" style="color: var(--muted); font-weight: 500;">Kelengkapan data</span>
                    <span id="progressPct" class="text-[13px] font-semibold" style="color: var(--accent);">0%</span>
                </div>
                <div class="progress-track">
                    <div id="progressFill" class="progress-fill"></div>
                </div>
            </div>

            <?= $notifikasi; ?>

            <form action="" method="POST" id="formPawning" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Section: Pemilik & Barang -->
                <div class="section-title">Data Pemilik &amp; Barang Jaminan</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                    <div>
                        <label class="field-label block mb-1.5">Nama Pemilik</label>
                        <input type="text" name="nama_pemilik" data-required value="<?= htmlspecialchars($form['nama_pemilik']) ?>" placeholder="cth. Budi Santoso" required class="field-input">
                    </div>
                    <div>
                        <label class="field-label block mb-1.5">Barang Jaminan</label>
                        <input type="text" name="barang_jaminan" data-required value="<?= htmlspecialchars($form['barang_jaminan']) ?>" placeholder="cth. Emas 10 gram, BPKB Motor" required class="field-input">
                    </div>
                </div>
                <div class="mb-8">
                    <label class="field-label block mb-1.5">Jenis Gadai BSI</label>
                    <div class="select-wrap">
                        <select name="jenis_gadai" data-required required class="field-input">
                            <?php foreach ($jenisGadaiValid as $jg) {
                                $sel = ($form['jenis_gadai'] === '' && $jg === $jenisGadaiPreselect) ? 'selected' : ($form['jenis_gadai'] === $jg ? 'selected' : '');
                                echo "<option value=\"$jg\" $sel>$jg</option>";
                            } ?>
                        </select>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1.5">Gadai Emas iB (emas batangan/perhiasan), Gadai Non Emas (BPKB, elektronik, dll), Fleksi Emas iB (top-up pinjaman).</p>
                </div>

                <!-- Section: Nilai -->
                <div class="section-title">Nilai Taksiran &amp; Pinjaman</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                    <div>
                        <label class="field-label block mb-1.5">Taksiran Harga</label>
                        <div class="relative">
                            <span class="rp-prefix">Rp</span>
                            <input type="text" inputmode="numeric" id="taksiranDisplay" data-required required class="field-input" style="padding-left:36px" placeholder="5.000.000">
                            <input type="hidden" name="taksiran_harga_asli" id="taksiranAsli">
                        </div>
                    </div>
                    <div>
                        <label class="field-label block mb-1.5">Nilai Pinjaman</label>
                        <div class="relative">
                            <span class="rp-prefix">Rp</span>
                            <input type="text" inputmode="numeric" id="pinjamanDisplay" data-required required class="field-input" style="padding-left:36px" placeholder="3.500.000">
                            <input type="hidden" name="nilai_pinjaman_asli" id="pinjamanAsli">
                        </div>
                    </div>
                </div>

                <!-- Section: Status & Kontak -->
                <div class="section-title">Status &amp; Kontak</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                    <div>
                        <label class="field-label block mb-1.5">Status Gadai</label>
                        <div class="select-wrap">
                            <select name="status" data-required required class="field-input">
                                <?php foreach ($statusValid as $s) {
                                    $sel = $form['status'] === $s ? 'selected' : '';
                                    echo "<option value=\"$s\" $sel>$s</option>";
                                } ?>
                            </select>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label class="field-label block mb-1.5">No. Telepon</label>
                        <input type="text" name="no_telepon" data-required inputmode="tel" value="<?= htmlspecialchars($form['no_telepon']) ?>" placeholder="08xx-xxxx-xxxx" required class="field-input">
                    </div>
                </div>

                <button type="submit" class="pm-btn pm-btn-primary w-full">Simpan Data</button>
            </form>
        </div>

        <p class="text-center text-[12px] text-slate-400 mt-5">Data yang dikirim terenkripsi dan hanya dapat diakses oleh petugas berwenang.</p>
    </div>

    <script>
        function bindRupiah(displayId, hiddenId) {
            const display = document.getElementById(displayId);
            const hidden = document.getElementById(hiddenId);
            display.addEventListener('input', (e) => {
                let val = e.target.value.replace(/\D/g, '');
                hidden.value = val;
                e.target.value = val ? new Intl.NumberFormat('id-ID').format(val) : '';
            });
            return display;
        }
        const taksiranDisplay = bindRupiah('taksiranDisplay', 'taksiranAsli');
        const pinjamanDisplay = bindRupiah('pinjamanDisplay', 'pinjamanAsli');

        const form = document.getElementById('formPawning');
        const requiredFields = Array.from(form.querySelectorAll('[data-required]'));
        const progressFill = document.getElementById('progressFill');
        const progressPct = document.getElementById('progressPct');

        function updateProgress() {
            const filled = requiredFields.filter(f => f.value.trim() !== '').length;
            const pct = Math.round((filled / requiredFields.length) * 100);
            progressFill.style.width = pct + '%';
            progressPct.textContent = pct + '%';
        }

        requiredFields.forEach(f => {
            f.addEventListener('input', updateProgress);
            f.addEventListener('change', updateProgress);
        });
        taksiranDisplay.addEventListener('input', updateProgress);
        pinjamanDisplay.addEventListener('input', updateProgress);
        updateProgress();
    </script>
</body>

</html>
