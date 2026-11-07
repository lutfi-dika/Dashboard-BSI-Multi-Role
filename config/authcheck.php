<?php
/**
 * config/authcheck.php
 * Pengecekan dasar sesi (sudah login + sudah OTP).
 * Juga memuat config/roles.php sehingga fungsi require_role(),
 * has_role(), dll. tersedia di semua halaman yang meng-include file ini.
 *
 * Catatan: file ini dipakai di halaman yang path-nya TIDAK di dalam
 * subfolder (mis. index.php di root). Halaman di dalam admin/(segmen)/ memakai
 * require_role() / require_mikro() / dst. secara langsung.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat helper hak akses terpusat
require_once __DIR__ . '/roles.php';

// Jika belum login password, lempar ke login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

// Jika belum lulus OTP, lempar ke pilih-karyawan
if (!isset($_SESSION['login_terverifikasi'])) {
    header("Location: pilih-karyawan.php");
    exit();
}
