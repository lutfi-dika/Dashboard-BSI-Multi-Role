<?php
// admin/konsumer/export-excel.php
// Export Excel tanpa library eksternal: Excel bisa membuka file HTML-table
// yang disajikan dengan header Content-Type "application/vnd.ms-excel".
// Menghormati filter pencarian & status yang sedang aktif di konsumer-data.php.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/konsumer-filter-helper.php';

if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'konsumer' && $_SESSION['role'] !== 'atasan')) {
    header("Location: ../../index.php");
    exit();
}

$filter = konsumer_build_filter($_GET);

$sql = "SELECT k.*, u.nama_lengkap AS nama_petugas
        FROM nasabah_konsumer k
        LEFT JOIN users u ON k.user_id = u.id
        WHERE {$filter['where_sql']}
        ORDER BY k.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
konsumer_bind_params($stmt, $filter['types'], $filter['params']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$nama_file = 'data-konsumer-' . date('Y-m-d_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM supaya karakter UTF-8 (Rp, dsb) terbaca benar di Excel
?>
<table border="1">
    <thead>
        <tr>
            <th>Nama Lengkap</th>
            <th>NIK</th>
            <th>Pekerjaan</th>
            <th>Pendapatan Bulanan</th>
            <th>Produk Diajukan</th>
            <th>Status</th>
            <th>No. Telepon</th>
            <th>Alamat</th>
            <th>Karyawan Penginput</th>
            <th>Tanggal Input</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= htmlspecialchars($row['nama_lengkap']); ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['nik']); ?></td>
                <td><?= htmlspecialchars($row['pekerjaan']); ?></td>
                <td><?= (float) $row['pendapatan_bulanan']; ?></td>
                <td><?= htmlspecialchars($row['produk_diajukan']); ?></td>
                <td><?= htmlspecialchars(ucfirst($row['status'] ?? 'pending')); ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['no_telepon']); ?></td>
                <td><?= htmlspecialchars($row['alamat_rumah']); ?></td>
                <td><?= htmlspecialchars($row['nama_petugas'] ?? 'Sistem / Admin'); ?></td>
                <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['created_at']))); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
