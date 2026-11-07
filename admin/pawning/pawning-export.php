<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';

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

// ====== EKSPORT CSV / EXCEL ======
if (isset($_GET['export'])) {
    $mode   = $_GET['export']; // csv | excel
    $status = $_GET['status'] ?? '';
    $dari   = $_GET['dari'] ?? '';
    $sampai = $_GET['sampai'] ?? '';

    $where = "WHERE 1=1";
    if ($status !== '') {
        $st = mysqli_real_escape_string($conn, $status);
        $where .= " AND status = '$st'";
    }
    if ($dari !== '') {
        $d = mysqli_real_escape_string($conn, $dari);
        $where .= " AND created_at >= '$d 00:00:00'";
    }
    if ($sampai !== '') {
        $s = mysqli_real_escape_string($conn, $sampai);
        $where .= " AND created_at <= '$s 23:59:59'";
    }

    $res = mysqli_query($conn, "SELECT * FROM pawning $where ORDER BY created_at DESC");

    $filename = 'laporan-gadai-' . date('Ymd-His') . ($mode === 'excel' ? '.xls' : '.csv');

    if ($mode === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM utk Excel
        fputcsv($out, ['No', 'Nama Pemilik', 'No. Telepon', 'Barang Jaminan', 'Taksiran', 'Pinjaman', 'Ujrah', 'Status', 'Tanggal Gadai', 'Jatuh Tempo', 'Tanggal Input']);
        $no = 1;
        while ($p = mysqli_fetch_assoc($res)) {
            fputcsv($out, [
                $no++,
                $p['nama_pemilik'],
                $p['no_telepon'],
                $p['barang_jaminan'],
                (float) $p['taksiran_harga'],
                (float) $p['nilai_pinjaman'],
                (float) $p['nilai_ujrah'],
                $p['status'],
                $p['tanggal_gadai'],
                $p['tanggal_jatuh_tempo'],
                $p['created_at'],
            ]);
        }
        fclose($out);
    } else {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        echo "<table border='1' cellpadding='5' style='font-family:sans-serif'>";
        echo "<tr><th>No</th><th>Nama Pemilik</th><th>No. Telepon</th><th>Barang Jaminan</th><th>Taksiran</th><th>Pinjaman</th><th>Ujrah</th><th>Status</th><th>Tanggal Gadai</th><th>Jatuh Tempo</th><th>Tanggal Input</th></tr>";
        $no = 1;
        while ($p = mysqli_fetch_assoc($res)) {
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . htmlspecialchars($p['nama_pemilik']) . "</td>";
            echo "<td>" . htmlspecialchars($p['no_telepon']) . "</td>";
            echo "<td>" . htmlspecialchars($p['barang_jaminan']) . "</td>";
            echo "<td>" . (float) $p['taksiran_harga'] . "</td>";
            echo "<td>" . (float) $p['nilai_pinjaman'] . "</td>";
            echo "<td>" . (float) $p['nilai_ujrah'] . "</td>";
            echo "<td>" . htmlspecialchars($p['status']) . "</td>";
            echo "<td>" . $p['tanggal_gadai'] . "</td>";
            echo "<td>" . $p['tanggal_jatuh_tempo'] . "</td>";
            echo "<td>" . $p['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    exit();
}

// ====== HALAMAN EXPORT ======
$totalData = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) t FROM pawning"))['t'] ?? 0);
$statExport = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(status='Aktif') aktif, SUM(status='Lunas') lunas, SUM(status='Lelang') lelang FROM pawning"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data Gadai</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .card { background: #ffffff; border: 1px solid #eef0f3; }
        body.sidebar-open { overflow: hidden; }
    </style>
</head>
<body class="bg-white text-slate-700 min-h-screen flex flex-col md:flex-row">

    <?php include __DIR__ . '/pawning-sidebar.php'; ?>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto max-h-screen w-full min-w-0">
        <header class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">Export Data Gadai</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Unduh data gadai ke format CSV atau Excel sesuai filter.</p>
        </header>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card rounded-2xl p-5">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Total Data</p>
                <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalData); ?></p>
            </div>
            <div class="card rounded-2xl p-5">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Aktif</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1"><?= number_format($statExport['aktif'] ?? 0); ?></p>
            </div>
            <div class="card rounded-2xl p-5">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Lunas</p>
                <p class="text-2xl font-extrabold text-sky-600 mt-1"><?= number_format($statExport['lunas'] ?? 0); ?></p>
            </div>
            <div class="card rounded-2xl p-5">
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Lelang</p>
                <p class="text-2xl font-extrabold text-rose-600 mt-1"><?= number_format($statExport['lelang'] ?? 0); ?></p>
            </div>
        </div>

        <div class="card rounded-2xl p-6">
            <h4 class="text-sm font-bold text-slate-800 mb-4">Pilih Opsi Export</h4>
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <div>
                    <label class="text-xs font-semibold text-slate-500 mb-1 block">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white">
                        <option value="">Semua Status</option>
                        <?php foreach (['Aktif', 'Lunas', 'Lelang'] as $s): ?>
                            <option value="<?= $s; ?>"><?= $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 mb-1 block">Dari Tanggal</label>
                    <input type="date" name="dari" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-500 mb-1 block">Sampai Tanggal</label>
                    <input type="date" name="sampai" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" name="export" value="csv" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl">Export CSV</button>
                    <button type="submit" name="export" value="excel" class="flex-1 px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-xs font-semibold rounded-xl">Export Excel</button>
                </div>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-800">Format CSV</p>
                        <p class="text-[11px] text-slate-400">Kompatibel dengan Microsoft Excel &amp; Google Sheets (UTF-8).</p>
                    </div>
                </div>
                <div class="rounded-xl bg-slate-50 border border-slate-100 p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-800">Format Excel (.xls)</p>
                        <p class="text-[11px] text-slate-400">File HTML table yang bisa dibuka langsung oleh Excel.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
