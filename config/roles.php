<?php
/**
 * config/roles.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Pusat definisi hak akses (Role-Based Access Control) untuk seluruh dashboard.
 *
 * CARA PAKAI
 * ──────────
 * Di awal setiap halaman yang butuh proteksi, tambahkan:
 *
 *   require_once __DIR__ . '/../../config/roles.php';   // sesuaikan kedalaman
 *   require_role(ROLE_MIKRO);                           // atau konstanta lain
 *
 * Fungsi require_role() sudah menangani:
 *   - Cek session aktif (user_id + login_terverifikasi)
 *   - Cek role sesuai izin halaman
 *   - Redirect otomatis ke halaman yang tepat jika tidak punya akses
 *
 * HIERARKI ROLE (dari tertinggi ke terendah)
 * ───────────────────────────────────────────
 *  admin    → superuser, bisa akses semua halaman
 *  atasan   → view-only di semua segmen + dashboard eksekutif
 *  mikro    → operasional segmen Mikro saja
 *  konsumer → operasional segmen Konsumer saja
 *  pawning  → operasional segmen Pawning (Gadai Emas) saja
 * ─────────────────────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Konstanta role ──────────────────────────────────────────────────────────
const ROLE_ADMIN    = 'admin';
const ROLE_ATASAN   = 'atasan';
const ROLE_MIKRO    = 'mikro';
const ROLE_KONSUMER = 'konsumer';
const ROLE_PAWNING  = 'pawning';

// ── Peta izin per "zona" halaman ────────────────────────────────────────────
// Kunci   : nama zona (bebas, untuk keperluan dokumentasi)
// Nilai   : daftar role yang boleh masuk
const ZONE_PERMISSIONS = [
    // Dashboard eksekutif — atasan & admin membaca semua segmen
    'atasan'   => [ROLE_ADMIN, ROLE_ATASAN],

    // Operasional segmen Mikro — mikro menulis, atasan membaca, admin segalanya
    'mikro'    => [ROLE_ADMIN, ROLE_MIKRO, ROLE_ATASAN],

    // Operasional segmen Konsumer
    'konsumer' => [ROLE_ADMIN, ROLE_KONSUMER, ROLE_ATASAN],

    // Operasional segmen Pawning / Gadai Emas
    'pawning'  => [ROLE_ADMIN, ROLE_PAWNING, ROLE_ATASAN],
];

// ── Daftar peran (untuk tampilan UI / dropdown) ─────────────────────────────
const ALL_ROLES = [
    ROLE_ADMIN    => 'Administrator',
    ROLE_ATASAN   => 'Atasan / Pimpinan',
    ROLE_MIKRO    => 'Petugas Mikro',
    ROLE_KONSUMER => 'Petugas Konsumer',
    ROLE_PAWNING  => 'Petugas Pawning',
];

// ── Fungsi utilitas ──────────────────────────────────────────────────────────

/**
 * Mengembalikan role user yang sedang login (lowercase).
 * Mengembalikan string kosong jika belum login.
 */
function current_role(): string
{
    return strtolower($_SESSION['role'] ?? '');
}

/**
 * Memeriksa apakah user yang sedang login memiliki salah satu dari role
 * yang diberikan (case-insensitive).
 *
 * @param string|string[] $roles  Satu role atau array role yang diizinkan.
 */
function has_role(string|array $roles): bool
{
    $allowed = array_map('strtolower', (array) $roles);
    return in_array(current_role(), $allowed, true);
}

/**
 * Memeriksa apakah user memiliki izin masuk ke zona tertentu.
 *
 * @param string $zone  Salah satu kunci dari ZONE_PERMISSIONS.
 */
function can_access_zone(string $zone): bool
{
    $permitted = ZONE_PERMISSIONS[$zone] ?? [];
    return has_role($permitted);
}

/**
 * Melindungi halaman: pastikan user sudah login DAN memiliki salah satu role
 * yang diizinkan. Jika tidak, redirect ke halaman yang sesuai.
 *
 * @param string|string[] $allowedRoles  Role yang boleh mengakses halaman ini.
 * @param string          $basePath      Path relatif ke root proyek
 *                                       (mis. '../../' jika di dalam admin/Mikro/).
 */
function require_role(string|array $allowedRoles, string $basePath = '../../'): void
{
    // 1. Cek login (password)
    if (!isset($_SESSION['user_id'])) {
        header("Location: {$basePath}auth/login.php");
        exit();
    }

    // 2. Cek verifikasi OTP
    if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
        header("Location: {$basePath}pilih-karyawan.php");
        exit();
    }

    // 3. Cek role
    if (!has_role($allowedRoles)) {
        // Arahkan ke dashboard yang sesuai dengan role user, bukan ke halaman error
        $role = current_role();
        $dest = match ($role) {
            ROLE_ATASAN   => "{$basePath}admin/Atasan/admin-dashboard.php",
            ROLE_MIKRO    => "{$basePath}admin/Mikro/mikro-dashboard.php",
            ROLE_KONSUMER => "{$basePath}admin/konsumer/konsumer-dashboard.php",
            ROLE_PAWNING  => "{$basePath}admin/pawning/pawning-dashboard.php",
            default        => "{$basePath}index.php",
        };
        header("Location: $dest");
        exit();
    }
}

/**
 * Versi singkat: lindungi halaman zona Atasan.
 * @param string $basePath  Path relatif ke root proyek.
 */
function require_atasan(string $basePath = '../../'): void
{
    require_role(ZONE_PERMISSIONS['atasan'], $basePath);
}

/**
 * Versi singkat: lindungi halaman zona Mikro.
 */
function require_mikro(string $basePath = '../../'): void
{
    require_role(ZONE_PERMISSIONS['mikro'], $basePath);
}

/**
 * Versi singkat: lindungi halaman zona Konsumer.
 */
function require_konsumer(string $basePath = '../../'): void
{
    require_role(ZONE_PERMISSIONS['konsumer'], $basePath);
}

/**
 * Versi singkat: lindungi halaman zona Pawning.
 */
function require_pawning(string $basePath = '../../'): void
{
    require_role(ZONE_PERMISSIONS['pawning'], $basePath);
}

/**
 * Mengembalikan true jika user aktif adalah atasan atau admin
 * (biasanya dipakai untuk menampilkan/menyembunyikan tombol hapus / edit sensitif).
 */
function is_atasan_or_admin(): bool
{
    return has_role([ROLE_ADMIN, ROLE_ATASAN]);
}

/**
 * Mengembalikan true jika user aktif HANYA bisa membaca (atasan),
 * bukan menulis data operasional.
 * Admin selalu bisa menulis, petugas divisi masing-masing bisa menulis.
 */
function is_read_only(): bool
{
    return current_role() === ROLE_ATASAN;
}
