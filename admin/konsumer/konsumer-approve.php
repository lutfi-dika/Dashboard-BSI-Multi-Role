<?php
// admin/konsumer/konsumer-approve.php
// Endpoint AJAX (fetch) untuk memproses persetujuan/penolakan pengajuan segmen Konsumer.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/csrf.php';

// Set header JSON di paling atas
header('Content-Type: application/json');

/**
 * Helper function untuk mengirim respon JSON dan menghentikan eksekusi skrip.
 */
function respond(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit();
}

// 1. Validasi Autentikasi: Harus login & lolos OTP
if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    http_response_code(401);
    respond(false, 'Sesi tidak valid atau telah berakhir. Silakan login ulang.');
}

// 2. Validasi Otorisasi: admin, petugas konsumer, dan atasan
$role_user = $_SESSION['role'] ?? '';
if ($role_user !== 'admin' && $role_user !== 'konsumer' && $role_user !== 'atasan') {
    http_response_code(403);
    respond(false, 'Anda tidak memiliki hak akses untuk melakukan aksi ini.');
}

// Support parsing data dari raw JSON body (jika fetch menggunakan JSON.stringify)
$inputRaw = file_get_contents('php://input');
$jsonInput = json_decode($inputRaw, true) ?? [];

if (!empty($jsonInput)) {
    $_POST = array_merge($_POST, $jsonInput);
}

// 3. Validasi Request Method & Token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    respond(false, 'Sesi tidak valid, silakan muat ulang halaman.');
}

// 4. Parsing Input Parameter
$id      = (int) ($_POST['id'] ?? 0);
$aksi    = $_POST['aksi'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');

if ($id <= 0 || !in_array($aksi, ['setuju', 'tolak'], true)) {
    http_response_code(400);
    respond(false, 'Permintaan tidak valid. ID atau aksi tidak sesuai.');
}

$status_baru = ($aksi === 'setuju') ? 'disetujui' : 'ditolak';
$user_id     = (int) $_SESSION['user_id'];

// 5. Cek apakah data nasabah ada dan statusnya masih "pending"
$stmtCek = mysqli_prepare($conn, "SELECT status, nama_lengkap FROM nasabah_konsumer WHERE id = ?");
if (!$stmtCek) {
    error_log('konsumer-approve prepare error: ' . mysqli_error($conn));
    http_response_code(500);
    respond(false, 'Terjadi kesalahan server. Silakan coba lagi nanti.');
}

mysqli_stmt_bind_param($stmtCek, "i", $id);
mysqli_stmt_execute($stmtCek);
$result = mysqli_stmt_get_result($stmtCek);
$row    = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmtCek);

if (!$row) {
    http_response_code(404);
    respond(false, 'Data pengajuan tidak ditemukan.');
}

if ($row['status'] !== 'pending') {
    http_response_code(409);
    respond(false, 'Pengajuan ini sudah pernah diproses sebelumnya (status saat ini: ' . $row['status'] . ').');
}

// 6. Update Status Approval
$stmtUpdate = mysqli_prepare(
    $conn,
    "UPDATE nasabah_konsumer SET status = ?, catatan_approval = ?, diproses_oleh = ?, updated_at = NOW() WHERE id = ?"
);

if (!$stmtUpdate) {
    error_log('konsumer-approve update prepare error: ' . mysqli_error($conn));
    http_response_code(500);
    respond(false, 'Terjadi kesalahan server. Silakan coba lagi nanti.');
}

mysqli_stmt_bind_param($stmtUpdate, "ssii", $status_baru, $catatan, $user_id, $id);

if (mysqli_stmt_execute($stmtUpdate)) {
    mysqli_stmt_close($stmtUpdate);
    $label = ($aksi === 'setuju') ? 'disetujui' : 'ditolak';

    respond(true, "Pengajuan atas nama \"{$row['nama_lengkap']}\" berhasil {$label}.", [
        'status' => $status_baru,
        'id'     => $id
    ]);
} else {
    error_log('konsumer-approve execute error: ' . mysqli_stmt_error($stmtUpdate));
    mysqli_stmt_close($stmtUpdate);

    http_response_code(500);
    respond(false, 'Gagal memperbarui data. Silakan coba lagi nanti.');
}
