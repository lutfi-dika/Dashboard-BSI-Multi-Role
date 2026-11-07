<?php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

if (!csrf_verify()) {
    $_SESSION['error'] = "Sesi tidak valid, silakan coba lagi.";
    header("Location: login.php");
    exit();
}

// Rate limiting sederhana berbasis session untuk mencegah brute force
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['login_last_attempt'] = $_SESSION['login_last_attempt'] ?? 0;

if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['login_last_attempt']) < 60) {
    $_SESSION['error'] = "Terlalu banyak percobaan login. Coba lagi dalam 1 menit.";
    header("Location: login.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['error'] = "Username dan password wajib diisi.";
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['login_attempts']++;
    $_SESSION['login_last_attempt'] = time();
    $_SESSION['error'] = "Username atau password salah.";
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$passwordValid = password_verify($password, $user['password']);

// Migrasi transparan: jika password lama masih plaintext, verifikasi lalu re-hash otomatis.
if (!$passwordValid && hash_equals((string) $user['password'], $password)) {
    $passwordValid = true;
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param("si", $newHash, $user['id']);
    $upd->execute();
}

if ($passwordValid) {
    // Akun yang belum diverifikasi (mis. hasil registrasi mandiri) tidak boleh login
    if (($user['status'] ?? 'verified') !== 'verified') {
        $_SESSION['error'] = "Akun Anda belum terverifikasi. Silakan selesaikan verifikasi email terlebih dahulu.";
        header("Location: login.php");
        exit();
    }

    // Reset percobaan gagal
    $_SESSION['login_attempts'] = 0;

    // Regenerasi session ID untuk mencegah session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama']     = $user['nama_lengkap'] ?? $user['username'];
    $_SESSION['role']     = $user['role'];

    unset($_SESSION['login_terverifikasi']);

    header("Location: ../pilih-karyawan.php");
    exit();
}

$_SESSION['login_attempts']++;
$_SESSION['login_last_attempt'] = time();
$_SESSION['error'] = "Username atau password salah.";
header("Location: login.php");
exit();
