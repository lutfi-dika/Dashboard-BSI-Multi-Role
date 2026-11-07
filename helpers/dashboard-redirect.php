<?php
/**
 * helpers/dashboard-redirect.php
 * Utility terpusat: menentukan & mengarahkan user ke dashboard sesuai divisi (role) mereka.
 * Dipakai di auth/login.php, index.php, pilih-karyawan.php, dan ajax/verifikasi-otp.php
 * supaya mapping role -> dashboard hanya didefinisikan di satu tempat.
 */

/**
 * Mengembalikan path dashboard (relatif terhadap folder root dashboard-pendataan)
 * berdasarkan role/divisi user.
 */
function dashboard_path_for_role(string $role): string
{
    return match ($role) {
        'admin', 'atasan'  => 'admin/Atasan/admin-dashboard.php',
        'konsumer'         => 'admin/konsumer/konsumer-dashboard.php',
        'mikro'            => 'admin/Mikro/mikro-dashboard.php',
        'pawning' => 'admin/pawning/pawning-dashboard.php',
        default            => 'index.php',
    };
}

/**
 * Redirect user yang sudah login penuh (password + OTP) langsung ke dashboard divisinya.
 * $basePath adalah path relatif dari file pemanggil menuju folder root dashboard-pendataan
 * (contoh: '' jika dipanggil dari root, '../' jika dipanggil dari dalam folder auth/).
 */
function redirect_to_own_dashboard(string $basePath = ''): void
{
    if (
        isset($_SESSION['user_id']) &&
        isset($_SESSION['login_terverifikasi']) &&
        $_SESSION['login_terverifikasi'] === true
    ) {
        $role   = $_SESSION['role'] ?? '';
        $target = dashboard_path_for_role($role);
        header("Location: {$basePath}{$target}");
        exit();
    }
}
