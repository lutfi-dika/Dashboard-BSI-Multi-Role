<?php
// admin/Mikro/mikro-export-excel.php
// Export Excel tanpa library eksternal: Excel bisa membuka file HTML-table
// yang disajikan dengan header Content-Type "application/vnd.ms-excel".

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit();
}
if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'mikro', 'atasan'], true)) {
    header("Location: ../../index.php");
    exit();
}

$result = mysqli_query($conn, "
    SELECT m.*, u.nama_lengkap AS nama_petugas
    FROM nasabah_mikro m
    LEFT JOIN users u ON m.user_id = u.id
    ORDER BY m.created_at DESC
");

$nama_file = 'data-mikro-' . date('Y-m-d_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM supaya karakter UTF-8 (Rp, dsb) terbaca benar di Excel
?>
<table border="1">
    <thead>
        <tr>
            <th>Nama Pemilik</th>
            <th>Nama Usaha</th>
            <th>Sektor</th>
            <th>Omzet Bulanan</th>
            <th>Plafon Pembiayaan</th>
            <th>Outstanding</th>
            <th>Lama Usaha (Tahun)</th>
            <th>Alamat Usaha</th>
            <th>No. Telepon</th>
            <th>Cabang</th>
            <th>AO</th>
            <th>Status</th>
            <th>Kolektibilitas</th>
            <th>Karyawan Penginput</th>
            <th>Tanggal Input</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?= htmlspecialchars($row['nama_pemilik']); ?></td>
                <td><?= htmlspecialchars($row['nama_usaha']); ?></td>
                <td><?= htmlspecialchars($row['jenis_sektor']); ?></td>
                <td><?= (float) $row['omzet_bulanan']; ?></td>
                <td><?= (float) ($row['plafon_pembiayaan'] ?? 0); ?></td>
                <td><?= (float) ($row['outstanding'] ?? 0); ?></td>
                <td><?= (int) $row['lama_usaha_tahun']; ?></td>
                <td><?= htmlspecialchars($row['alamat_usaha']); ?></td>
                <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['no_telepon']); ?></td>
                <td><?= htmlspecialchars($row['cabang'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($row['nama_ao'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($row['status_pengajuan'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($row['kolektibilitas'] ?? '-'); ?></td>
                <td><?= htmlspecialchars($row['nama_petugas'] ?? 'Sistem / Admin'); ?></td>
                <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($row['created_at']))); ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
