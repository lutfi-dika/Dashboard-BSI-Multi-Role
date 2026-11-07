<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/premium-ui.php';

// Proteksi Hak Akses
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'mikro' && $_SESSION['role'] !== 'atasan')) {
    header("Location: ../../index.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'atasan') {
    header("Location: mikro-tambah.php");
    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$notifikasi = "";
$sektorValid = ['Perdagangan', 'Pertanian', 'Jasa', 'Manufaktur', 'Lainnya'];

// Deteksi apakah migrasi sql/002_mikro_dashboard_kpi.sql sudah dijalankan.
// Kalau belum, form tetap bisa dipakai (fallback ke kolom lama) supaya tidak error.
$cekKolomAo = mysqli_query($conn, "SHOW COLUMNS FROM nasabah_mikro LIKE 'nama_ao'");
$skemaLengkapTambah = $cekKolomAo && mysqli_num_rows($cekKolomAo) > 0;
$form = ['nama_pemilik' => '', 'nama_usaha' => '', 'jenis_sektor' => '', 'produk' => '', 'omzet_bulanan' => '', 'plafon_pembiayaan' => '', 'lama_usaha_tahun' => '', 'alamat_usaha' => '', 'no_telepon' => ''];

$produkValid = ['KUR Super Mikro', 'KUR Mikro', 'KUR Kecil', 'BSI Mikro iB'];
$produkPreselect = $_GET['p'] ?? '';
if (!in_array($produkPreselect, $produkValid, true)) {
    $produkPreselect = 'KUR Mikro';
}

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

    $form['nama_pemilik']     = trim($_POST['nama_pemilik'] ?? '');
    $form['nama_usaha']       = trim($_POST['nama_usaha'] ?? '');
    $form['jenis_sektor']     = trim($_POST['jenis_sektor'] ?? '');
    $form['produk']           = trim($_POST['produk'] ?? '');
    $form['omzet_bulanan']    = trim($_POST['omzet_asli'] ?? ''); // Mengambil dari input hidden
    $form['plafon_pembiayaan'] = trim($_POST['plafon_asli'] ?? ''); // Mengambil dari input hidden
    $form['lama_usaha_tahun'] = trim($_POST['lama_usaha_tahun'] ?? '');
    $form['alamat_usaha']     = trim($_POST['alamat_usaha'] ?? '');
    $form['no_telepon']       = trim($_POST['no_telepon'] ?? '');

    $errors = [];
    if (empty($form['nama_pemilik'])) $errors[] = "Nama pemilik wajib diisi.";
    if (!in_array($form['jenis_sektor'], $sektorValid)) $errors[] = "Sektor tidak valid.";
    if (!in_array($form['produk'], $produkValid)) $errors[] = "Produk pembiayaan tidak valid.";
    if (!is_numeric($form['omzet_bulanan'])) $errors[] = "Omzet harus berupa angka.";
    if (!is_numeric($form['plafon_pembiayaan']) || (float) $form['plafon_pembiayaan'] <= 0) $errors[] = "Plafon pembiayaan yang diajukan harus diisi dengan angka lebih dari 0.";

    if (empty($errors)) {
        if ($skemaLengkapTambah) {
            // Kolom cabang, nama_ao, status_pengajuan, plafon_pembiayaan, dst membutuhkan
            // migrasi sql/002_mikro_dashboard_kpi.sql. AO diisi otomatis dari nama petugas yang login.
            $namaAo = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Belum Diisi');
            $cabang = 'BSI KCP Tambun';
            $statusAwal = 'Approval'; // pengajuan baru selalu masuk antrean approval dulu

            $stmt = mysqli_prepare($conn, "INSERT INTO nasabah_mikro (nama_pemilik, nama_usaha, jenis_sektor, produk, omzet_bulanan, plafon_pembiayaan, lama_usaha_tahun, alamat_usaha, alamat, cabang, nama_ao, status_pengajuan, no_telepon, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $userId = (int)$_SESSION['user_id'];
            mysqli_stmt_bind_param(
                $stmt,
                "ssssddissssssi",
                $form['nama_pemilik'],
                $form['nama_usaha'],
                $form['jenis_sektor'],
                $form['produk'],
                $form['omzet_bulanan'],
                $form['plafon_pembiayaan'],
                $form['lama_usaha_tahun'],
                $form['alamat_usaha'],
                $form['alamat_usaha'],
                $cabang,
                $namaAo,
                $statusAwal,
                $form['no_telepon'],
                $userId
            );
        } else {
            // Fallback skema lama (migrasi 002 belum dijalankan)
            $stmt = mysqli_prepare($conn, "INSERT INTO nasabah_mikro (nama_pemilik, nama_usaha, jenis_sektor, produk, omzet_bulanan, lama_usaha_tahun, alamat_usaha, no_telepon, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $userId = (int)$_SESSION['user_id'];
            mysqli_stmt_bind_param($stmt, "ssssdissi", $form['nama_pemilik'], $form['nama_usaha'], $form['jenis_sektor'], $form['produk'], $form['omzet_bulanan'], $form['lama_usaha_tahun'], $form['alamat_usaha'], $form['no_telepon'], $userId);
        }

        if (mysqli_stmt_execute($stmt)) {
            $notifikasi = renderAlert('success', 'Berhasil', 'Data nasabah tersimpan ke sistem.');
            $form = array_map(fn() => '', $form);
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
    <?php premium_head('Formulir Pendataan Mikro | BSI Mikro', 'mikro'); ?>
    <style>
        :root {
            --surface: #FFFFFF;
            --border: #E4E7EC;
            --border-strong: #D0D5DD;
            --text: #101828;
            --muted: #667085;
            --accent: #059669;
            --accent-hover: #047857;
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
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
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

        .submit-btn {
            background: var(--accent);
            transition: background .12s ease;
        }

        .submit-btn:hover {
            background: var(--accent-hover);
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
            <a href="mikro-dashboard.php" class="inline-flex items-center gap-1.5 text-[13px] font-medium mb-3" style="color: var(--muted);" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Kembali ke Dashboard
            </a>
            <h1 class="text-xl font-bold text-slate-900 leading-tight">Formulir Pendataan Mikro</h1>
            <p class="text-sm text-slate-500 mt-1">Lengkapi data nasabah usaha mikro secara akurat</p>
        </div>

        <!-- Card -->
        <div class="rounded-xl p-6 sm:p-8" style="background: var(--surface); border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(16,24,40,.04);">

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

            <form action="" method="POST" id="formMikro" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Section: Pemilik & Usaha -->
                <div class="section-title">Data Pemilik &amp; Usaha</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                    <div>
                        <label class="field-label block mb-1.5">Nama Pemilik</label>
                        <input type="text" name="nama_pemilik" data-required value="<?= htmlspecialchars($form['nama_pemilik']) ?>" placeholder="cth. Siti Amelia" required class="field-input">
                    </div>
                    <div>
                        <label class="field-label block mb-1.5">Nama Usaha</label>
                        <input type="text" name="nama_usaha" data-required value="<?= htmlspecialchars($form['nama_usaha']) ?>" placeholder="cth. Warung Berkah" required class="field-input">
                    </div>
                </div>

                <!-- Section: Profil Usaha -->
                <div class="section-title">Profil Usaha</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="field-label block mb-1.5">Sektor Usaha</label>
                        <div class="select-wrap">
                            <select name="jenis_sektor" data-required required class="field-input">
                                <option value="" disabled selected hidden>Pilih sektor</option>
                                <?php foreach ($sektorValid as $s) {
                                    $sel = $form['jenis_sektor'] === $s ? 'selected' : '';
                                    echo "<option value=\"$s\" $sel>$s</option>";
                                } ?>
                            </select>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label class="field-label block mb-1.5">Lama Usaha (Tahun)</label>
                        <input type="number" name="lama_usaha_tahun" data-required min="0" value="<?= htmlspecialchars($form['lama_usaha_tahun']) ?>" placeholder="cth. 3" required class="field-input">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="field-label block mb-1.5">Omzet Bulanan</label>
                    <div class="relative">
                        <span class="rp-prefix">Rp</span>
                        <input type="text" inputmode="numeric" id="omzetDisplay" data-required required class="field-input" style="padding-left:36px" placeholder="10.000.000">
                        <input type="hidden" name="omzet_asli" id="omzetAsli">
                    </div>
                </div>
                <div class="mb-8">
                    <label class="field-label block mb-1.5">Plafon Pembiayaan Diajukan</label>
                    <div class="relative">
                        <span class="rp-prefix">Rp</span>
                        <input type="text" inputmode="numeric" id="plafonDisplay" data-required required class="field-input" style="padding-left:36px" placeholder="25.000.000">
                        <input type="hidden" name="plafon_asli" id="plafonAsli">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1.5">Nominal pembiayaan yang diajukan nasabah, dipakai untuk kalkulasi Outstanding &amp; NPF setelah disetujui.</p>
                </div>
                <div class="mb-8">
                    <label class="field-label block mb-1.5">Produk Pembiayaan BSI</label>
                    <div class="select-wrap">
                        <select name="produk" data-required required class="field-input">
                            <?php foreach ($produkValid as $pv) {
                                $sel = ($form['produk'] === '' && $pv === $produkPreselect) ? 'selected' : ($form['produk'] === $pv ? 'selected' : '');
                                echo "<option value=\"$pv\" $sel>$pv</option>";
                            } ?>
                        </select>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1.5">KUR Super Mikro (s/d Rp 10 jt), KUR Mikro (Rp 10-100 jt), KUR Kecil (Rp 100-500 jt), BSI Mikro iB (non-KUR).</p>
                </div>

                <!-- Section: Kontak & Lokasi -->
                <div class="section-title">Kontak &amp; Lokasi</div>
                <div class="mb-4">
                    <label class="field-label block mb-1.5">Alamat Usaha</label>
                    <textarea name="alamat_usaha" data-required rows="3" required placeholder="Jalan, nomor, kelurahan, kecamatan" class="field-input resize-none"><?= htmlspecialchars($form['alamat_usaha']) ?></textarea>
                </div>
                <div class="mb-8">
                    <label class="field-label block mb-1.5">No. Telepon</label>
                    <input type="text" name="no_telepon" data-required inputmode="tel" value="<?= htmlspecialchars($form['no_telepon']) ?>" placeholder="08xx-xxxx-xxxx" required class="field-input">
                </div>

                <button type="submit" class="submit-btn w-full text-white font-semibold text-sm py-2.5 rounded-lg">Simpan Data</button>
            </form>
        </div>

        <p class="text-center text-[12px] text-slate-400 mt-5">Data yang dikirim terenkripsi dan hanya dapat diakses oleh petugas berwenang.</p>
    </div>

    <script>
        const omzetDisplay = document.getElementById('omzetDisplay');
        const omzetAsli = document.getElementById('omzetAsli');
        omzetDisplay.addEventListener('input', (e) => {
            let val = e.target.value.replace(/\D/g, '');
            omzetAsli.value = val;
            e.target.value = val ? new Intl.NumberFormat('id-ID').format(val) : '';
        });

        const plafonDisplay = document.getElementById('plafonDisplay');
        const plafonAsli = document.getElementById('plafonAsli');
        plafonDisplay.addEventListener('input', (e) => {
            let val = e.target.value.replace(/\D/g, '');
            plafonAsli.value = val;
            e.target.value = val ? new Intl.NumberFormat('id-ID').format(val) : '';
        });

        const form = document.getElementById('formMikro');
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
        omzetDisplay.addEventListener('input', updateProgress);
        plafonDisplay.addEventListener('input', updateProgress);
        updateProgress();
    </script>
</body>

</html>