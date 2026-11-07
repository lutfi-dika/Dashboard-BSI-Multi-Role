<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/dashboard-redirect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// Jika sudah login penuh (sudah lolos OTP sebelumnya), langsung ke dashboard divisinya
redirect_to_own_dashboard('');

$id_petugas = (int) $_SESSION['user_id'];
$stmtPetugas = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ?");
if (!$stmtPetugas) {
    die("Query gagal disiapkan. Pastikan migration sql/001_otp_migration.sql sudah dijalankan. Detail: " . htmlspecialchars(mysqli_error($conn)));
}
mysqli_stmt_bind_param($stmtPetugas, "i", $id_petugas);
mysqli_stmt_execute($stmtPetugas);
$petugas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtPetugas));
$role_petugas = $petugas['role'] ?? '';

// Perlu hati-hati: beberapa skema database lama tidak punya kolom `divisi`.
// Karenanya kita query field yang benar-benar ada di semua skema: nama_lengkap, username, role, email, no_hp.
$stmtKaryawan = mysqli_prepare($conn, "SELECT id, nama_lengkap, username, role, email, no_hp FROM users WHERE role = ? ORDER BY nama_lengkap ASC");
if (!$stmtKaryawan) {
    die("Query gagal disiapkan. Pastikan tabel users sudah dibuat dengan struktur yang benar. Detail: " . htmlspecialchars(mysqli_error($conn)));
}
mysqli_stmt_bind_param($stmtKaryawan, "s", $role_petugas);
mysqli_stmt_execute($stmtKaryawan);
$query_karyawan = mysqli_stmt_get_result($stmtKaryawan);
$daftar_karyawan = mysqli_fetch_all($query_karyawan, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Verifikasi</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-slate-50 font-sans">

    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col gap-2"></div>

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-slate-100">

            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 text-blue-600 rounded-full mb-4">
                    <i class="fas fa-user-check text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800">Verifikasi Karyawan BSI KCP Tambun</h2>
                <p class="text-slate-500 text-sm mt-1">Pilih karyawan &amp; channel untuk mengirim kode OTP</p>
            </div>

            <form id="formKirimOtp" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="purpose" value="login">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Daftar Karyawan <?= htmlspecialchars(ucfirst($role_petugas)) ?></label>
                    <select name="user_id" id="user_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 p-4 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition-all cursor-pointer appearance-none" required>
                        <option value="">-- Pilih Nama --</option>
                        <?php foreach ($daftar_karyawan as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" data-email="<?= $row['email'] ? '1' : '' ?>" data-hp="<?= $row['no_hp'] ? '1' : '' ?>">
                                <?= htmlspecialchars($row['nama_lengkap']) ?>
                                <?php if (!empty($row['username'])): ?>
                                    — <?= htmlspecialchars($row['username']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Kirim OTP Via</label>
                    <div class="flex gap-3">
                        <label class="flex-1 flex items-center justify-center gap-2 border border-slate-200 rounded-xl p-3 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <input type="radio" name="channel" value="email" checked class="accent-blue-600"> <i class="fas fa-envelope text-slate-500"></i> Email
                        </label>
                        <label class="flex-1 flex items-center justify-center gap-2 border border-slate-200 rounded-xl p-3 cursor-pointer has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                            <input type="radio" name="channel" value="whatsapp" class="accent-blue-600"> <i class="fab fa-whatsapp text-slate-500"></i> WhatsApp
                        </label>
                    </div>
                </div>

                <button type="submit" id="btnKirim" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition duration-300 transform hover:scale-[1.02] shadow-lg shadow-blue-200 disabled:opacity-50 disabled:cursor-not-allowed">
                    Kirim OTP Sekarang
                </button>
            </form>

            <div class="mt-8 text-center border-t border-slate-100 pt-6">
                <a href="auth/logout.php" class="text-sm text-slate-400 hover:text-red-500 transition font-medium">
                    <i class="fas fa-sign-out-alt mr-2"></i>Keluar dari Sistem
                </a>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            const color = type === 'success' ? 'bg-emerald-600' : 'bg-red-600';
            toast.className = `${color} text-white px-4 py-3 rounded-lg shadow-lg text-sm font-medium animate-pulse`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        document.getElementById('formKirimOtp').addEventListener('submit', async function(e) {
            e.preventDefault();

            const select = document.getElementById('user_id');
            const selected = select.options[select.selectedIndex];
            const channel = this.querySelector('input[name="channel"]:checked')?.value || 'email';

            if (channel === 'whatsapp' && selected && !selected.dataset.hp) {
                showToast('Karyawan ini belum memiliki nomor WhatsApp terdaftar.', 'error');
                return;
            }
            if (channel === 'email' && selected && !selected.dataset.email) {
                showToast('Karyawan ini belum memiliki email terdaftar.', 'error');
                return;
            }

            const btn = document.getElementById('btnKirim');
            btn.disabled = true;
            btn.innerHTML = 'Mengirim OTP...';

            try {
                const res = await fetch('ajax/kirim-otp.php', {
                    method: 'POST',
                    body: new FormData(this),
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.href = 'input-otp.php', 800);
                } else {
                    showToast(data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Kirim OTP Sekarang';
                }
            } catch (err) {
                showToast('Terjadi kesalahan jaringan. Coba lagi.', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Kirim OTP Sekarang';
            }
        });
    </script>
    <?php include __DIR__ . '/components/clock.php'; ?>
</body>

</html>