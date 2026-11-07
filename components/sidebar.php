<?php
// components/sidebar.php

// Pastikan session sudah berjalan untuk membaca role karyawan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil data session karyawan yang sedang aktif
$role_user = $_SESSION['role'] ?? '';
$nama_user = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'Karyawan');
$username_user = $_SESSION['username'] ?? 'petugas';

// Menentukan badge warna berdasarkan role (seragam abu-abu, atau opsional pakai BSI soft)
$badge_color = 'bg-[#E6F4ED] text-[#006B3F] border-[#3DAE7B]'; // menggunakan soft dan main BSI

// Tentukan base_url relatif berdasarkan lokasi script saat ini
$scriptPath = str_replace('\\', '/', $_SERVER['PHP_SELF']);
if (strpos($scriptPath, '/admin/') !== false) {
    $base_url = '../../';
} elseif (strpos($scriptPath, '/views/') !== false || strpos($scriptPath, '/auth/') !== false) {
    $base_url = '../';
} else {
    $base_url = '';
}
// Tentukan link target untuk halaman data tergantung konteks (admin vs views)
if (strpos($scriptPath, '/admin/') !== false) {
    // Jika di dalam admin, buat link relatif ke subfolder yang sesuai
    if (strpos($scriptPath, '/admin/Mikro/') !== false || strpos($scriptPath, '/admin/mikro/') !== false) {
        $mikro_href = 'mikro-data.php';
        $konsumer_href = '../konsumer/konsumer-data.php';
        $pawning_href = '../pawning/pawning-dashboard.php';
    } elseif (strpos($scriptPath, '/admin/konsumer/') !== false || strpos($scriptPath, '/admin/Konsumer/') !== false) {
        $mikro_href = '../Mikro/mikro-data.php';
        $konsumer_href = 'konsumer-data.php';
        $pawning_href = '../pawning/pawning-dashboard.php';
    } elseif (strpos($scriptPath, '/admin/pawning/') !== false) {
        $mikro_href = '../Mikro/mikro-data.php';
        $konsumer_href = '../konsumer/konsumer-data.php';
        $pawning_href = 'pawning-dashboard.php';
    } else {
        $mikro_href = 'Mikro/mikro-data.php';
        $konsumer_href = 'konsumer/konsumer-data.php';
        $pawning_href = 'pawning/pawning-dashboard.php';
    }
} else {
    $mikro_href = $base_url . 'views/mikro-data.php';
    $konsumer_href = $base_url . 'views/konsumer-data.php';
    $pawning_href = $base_url . 'admin/pawning/pawning-dashboard.php';
}

// Apakah kita sedang berada di dalam panel admin/konsumer (BSI Konsumer)?
$in_konsumer_admin = (bool) preg_match('#/admin/konsumer/#i', $scriptPath);
// Apakah kita sedang berada di dalam panel admin/Mikro (BSI Mikro)?
$in_mikro_admin = (bool) preg_match('#/admin/mikro/#i', $scriptPath);
// Apakah kita sedang berada di dalam panel admin/pawning (BSI Pawning)?
$in_pawning_admin = (bool) preg_match('#/admin/pawning/#i', $scriptPath);
// Apakah kita sedang berada di dalam panel admin/Atasan (BSI Executive)?
$in_atasan_admin = (bool) preg_match('#/admin/atasan/#i', $scriptPath);
$current_page = basename(parse_url($scriptPath, PHP_URL_PATH) ?: '');

// Susunan menu lengkap "BSI Konsumer" (sama seperti sebelumnya, tidak diubah)
$konsumer_menu = [
    'main' => [
        ['Dashboard', 'konsumer-dashboard.php', '<rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" />'],
    ],
    'NASABAH' => [
        ['Data Nasabah', 'konsumer-data.php', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />'],
        ['Pengajuan Pembiayaan', 'konsumer-tambah.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="12" y1="18" x2="12" y2="12" /><line x1="9" y1="15" x2="15" y2="15" />'],
        ['Dokumen Nasabah', 'konsumer-dokumen.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" />'],
    ],
    'PRODUK BSI' => [
        ['BSI Griya iB', 'konsumer-produk.php?p=BSI Griya iB', '<path d="M3 10.5 12 3l9 7.5" /><path d="M5 9.5V21h14V9.5" /><path d="M10 21v-6h4v6" />'],
        ['BSI OTO iB', 'konsumer-produk.php?p=BSI OTO iB', '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" /><circle cx="7" cy="17" r="2" /><path d="M9 17h6" /><circle cx="17" cy="17" r="2" />'],
        ['BSI Pensiun iB', 'konsumer-produk.php?p=BSI Pensiun iB', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />'],
        ['BSI Umroh iB', 'konsumer-produk.php?p=BSI Umroh iB', '<path d="M12 2v20" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />'],
        ['BSI Multiguna iB', 'konsumer-produk.php?p=BSI Multiguna iB', '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" /><path d="M3 5v14a2 2 0 0 0 2 2h16v-5" /><path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />'],
        ['BSI Fleksi iB', 'konsumer-produk.php?p=BSI Fleksi iB', '<path d="M6 3h12" /><path d="M6 21h12" /><path d="M6 6v12" /><path d="M18 6v12" />'],
    ],
    'PEMBIAYAAN' => [
        ['Approval Pembiayaan', 'konsumer-data.php?status=pending', '<rect width="20" height="14" x="2" y="5" rx="2" /><line x1="2" y1="10" x2="22" y2="10" />'],
        ['Monitoring Pembiayaan', 'konsumer-monitoring.php', '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />'],
        ['Angsuran', 'konsumer-angsuran.php', '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" /><path d="M3 5v14a2 2 0 0 0 2 2h16v-5" /><path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />'],
        ['Jatuh Tempo', 'konsumer-jatuh-tempo.php', '<rect width="18" height="18" x="3" y="4" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><circle cx="16" cy="17" r="0.5" fill="currentColor" />'],
    ],
    'ANALITIK' => [
        ['Dashboard Analytics', 'konsumer-analytics.php', '<line x1="12" y1="20" x2="12" y2="10" /><line x1="18" y1="20" x2="18" y2="4" /><line x1="6" y1="20" x2="6" y2="16" />'],
        ['Target vs Realisasi', 'konsumer-target-realisasi.php', '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" />'],
        ['Performa AO', 'konsumer-performa-ao.php', '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" /><circle cx="12" cy="10" r="3" />'],
    ],
    'LAPORAN' => [
        ['Laporan Harian', 'konsumer-laporan-harian.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="14" x2="15" y2="14" /><line x1="9" y1="17" x2="15" y2="17" />'],
        ['Laporan Bulanan', 'konsumer-laporan-bulanan.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><path d="M8 18v-2" /><path d="M12 18v-4" /><path d="M16 18v-6" />'],
        ['Export Data', 'konsumer-export.php', '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />'],
    ],
    'PENGATURAN' => [
        ['Notifikasi', 'konsumer-notifikasi.php', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />'],
        ['Profil', 'konsumer-profil.php', '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="10" r="3" /><path d="M7 20.7a7.5 7.5 0 0 1 10 0" />'],
        ['Pengaturan', 'konsumer-pengaturan.php', '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />'],
    ],
];

// Susunan menu lengkap "BSI Mikro" (tidak diubah)
$mikro_menu = [
    'main' => [
        ['Dashboard', 'mikro-dashboard.php', '<rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" />'],
    ],
    'DATA USAHA' => [
        ['Data UMKM', 'mikro-data.php', '<path d="M3 21h18" /><path d="M5 21V7l8-4v18" /><path d="M19 21V11l-6-4" /><path d="M9 9v.01" /><path d="M9 12v.01" /><path d="M9 15v.01" /><path d="M9 18v.01" />'],
        ['Data Debitur', 'mikro-debitur.php', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />'],
        ['Dokumen', 'mikro-dokumen.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" />'],
    ],
    'PRODUK BSI' => [
        ['KUR Super Mikro', 'mikro-produk.php?p=KUR Super Mikro', '<circle cx="12" cy="12" r="8" /><path d="M12 8v4l2.5 2.5" />'],
        ['KUR Mikro', 'mikro-produk.php?p=KUR Mikro', '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" /><circle cx="12" cy="10" r="3" />'],
        ['KUR Kecil', 'mikro-produk.php?p=KUR Kecil', '<line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />'],
        ['BSI Mikro iB', 'mikro-produk.php?p=BSI Mikro iB', '<path d="M3 3v18h18" /><path d="m19 9-5 5-4-4-3 3" />'],
    ],
    'PEMBIAYAAN' => [
        ['Pengajuan', 'mikro-tambah.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="12" y1="18" x2="12" y2="12" /><line x1="9" y1="15" x2="15" y2="15" />'],
        ['Survey Lapangan', 'mikro-survey.php', '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" /><circle cx="12" cy="10" r="3" />'],
        ['Analisa Pembiayaan', 'mikro-analisa.php', '<path d="M3 3v18h18" /><path d="m19 9-5 5-4-4-3 3" />'],
        ['Approval', 'mikro-approval.php', '<rect width="20" height="14" x="2" y="5" rx="2" /><line x1="2" y1="10" x2="22" y2="10" />'],
    ],
    'MONITORING' => [
        ['Monitoring Angsuran', 'mikro-monitoring.php', '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" /><path d="M3 5v14a2 2 0 0 0 2 2h16v-5" /><path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />'],
        ['Kolektibilitas', 'mikro-kolektibilitas.php', '<path d="M12 2v20" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />'],
        ['Jatuh Tempo', 'mikro-jatuh-tempo.php', '<rect width="18" height="18" x="3" y="4" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><circle cx="16" cy="17" r="0.5" fill="currentColor" />'],
    ],
    'ANALITIK' => [
        ['Outstanding', 'mikro-outstanding.php', '<line x1="12" y1="20" x2="12" y2="10" /><line x1="18" y1="20" x2="18" y2="4" /><line x1="6" y1="20" x2="6" y2="16" />'],
        ['Target AO', 'mikro-target-ao.php', '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" />'],
        ['Sebaran Wilayah', 'mikro-sebaran-wilayah.php', '<path d="M20.9 17.1c-.6 1.4-1.4 2.6-2.5 3.7-4.1 4.1-10.7 4.1-14.8 0" /><circle cx="12" cy="10" r="3" /><path d="M12 2a8 8 0 0 0-8 8c0 5.4 8 12 8 12s8-6.6 8-12a8 8 0 0 0-8-8Z" />'],
    ],
    'LAPORAN' => [
        ['Laporan', 'mikro-laporan.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="14" x2="15" y2="14" /><line x1="9" y1="17" x2="15" y2="17" />'],
        ['Export', 'mikro-export.php', '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />'],
    ],
    'AI' => [
        ['AI Assistant', 'mikro-ai.php', '<path d="m12 3-1.9 5.8L4 11l6.1 2.2L12 19l1.9-5.8L20 11l-6.1-2.2Z" />'],
        ['AI Insight', 'mikro-ai-insight.php', '<path d="M9 18h6" /><path d="M10 22h4" /><path d="M12 2a7 7 0 0 0-7 7c0 3 2 4.5 3 6h8c1-1.5 3-3 3-6a7 7 0 0 0-7-7Z" />'],
    ],
    'PENGATURAN' => [
        ['Notifikasi', 'mikro-notifikasi.php', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />'],
        ['Profil', 'mikro-profil.php', '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="10" r="3" /><path d="M7 20.7a7.5 7.5 0 0 1 10 0" />'],
        ['Pengaturan', 'mikro-pengaturan.php', '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />'],
    ],
];

// Susunan menu lengkap "BSI Pawning" (tidak diubah)
$pawning_menu = [
    'main' => [
        ['Dashboard', 'pawning-dashboard.php', '<rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" />'],
    ],
    'NASABAH' => [
        ['Data Nasabah', 'pawning-data.php', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />'],
    ],
    'PRODUK GADAI' => [
        ['Gadai Emas iB', 'pawning-produk.php?p=Gadai Emas iB', '<circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 2" />'],
        ['Gadai Non Emas', 'pawning-produk.php?p=Gadai Non Emas', '<rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />'],
        ['Fleksi Emas iB', 'pawning-produk.php?p=Fleksi Emas iB', '<path d="m9 12 2 2 4-4" /><path d="M12 3c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8" /><path d="M20 3v5h-5" />'],
    ],
    'GADAI EMAS' => [
        ['Gadai Baru', 'pawning-tambah.php', '<circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 2" />'],
        ['Perpanjangan', 'pawning-perpanjangan.php', '<path d="M21 12a9 9 0 1 1-2.64-6.36" /><polyline points="21 3 21 9 15 9" />'],
        ['Pelunasan', 'pawning-pelunasan.php', '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" /><path d="M3 5v14a2 2 0 0 0 2 2h16v-5" /><path d="M18 12a2 2 0 0 0 0 4h4v-4Z" />'],
        ['Penebusan', 'pawning-penebusan.php', '<path d="m9 12 2 2 4-4" /><path d="M12 3c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8" /><path d="M20 3v5h-5" />'],
        ['Lelang', 'pawning-lelang.php', '<path d="m14.5 12.5-8 8a2.1 2.1 0 1 1-3-3l8-8" /><path d="m16 16 6-6" /><path d="m8 8 6-6" /><path d="m9 7 8 8" /><path d="m21 11-8-8" />'],
    ],
    'DATA BARANG' => [
        ['Barang Gadai', 'pawning-barang.php', '<rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />'],
        ['Foto Barang', 'pawning-foto.php', '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3Z" /><circle cx="12" cy="13" r="3" />'],
        ['Nilai Taksiran', 'pawning-taksiran.php', '<path d="M12 2v20" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />'],
    ],
    'MONITORING' => [
        ['Jatuh Tempo', 'pawning-jatuh-tempo.php', '<rect width="18" height="18" x="3" y="4" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><circle cx="16" cy="17" r="0.5" fill="currentColor" />'],
        ['Outstanding', 'pawning-outstanding.php', '<line x1="12" y1="20" x2="12" y2="10" /><line x1="18" y1="20" x2="18" y2="4" /><line x1="6" y1="20" x2="6" y2="16" />'],
        ['Pendapatan Ujrah', 'pawning-ujrah.php', '<circle cx="12" cy="12" r="9" /><path d="M14.8 9a2.8 2.8 0 0 0-2.8-1.5c-1.9 0-3 1-3 2.2s1.1 1.9 3 2.3 3 1.1 3 2.3-1.1 2.2-3 2.2A2.8 2.8 0 0 1 9.2 15" /><line x1="12" y1="6" x2="12" y2="7.5" /><line x1="12" y1="16.5" x2="12" y2="18" />'],
    ],
    'LAPORAN' => [
        ['Laporan', 'pawning-laporan.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="14" x2="15" y2="14" /><line x1="9" y1="17" x2="15" y2="17" />'],
        ['Export', 'pawning-export.php', '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />'],
    ],
    'PENGATURAN' => [
        ['Notifikasi', 'pawning-notifikasi.php', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />'],
        ['Profil', 'pawning-profil.php', '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="10" r="3" /><path d="M7 20.7a7.5 7.5 0 0 1 10 0" />'],
        ['Pengaturan', 'pawning-pengaturan.php', '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" />'],
    ],
];

// Susunan menu lengkap "BSI Executive" (Atasan) (tidak diubah)
$atasan_menu = [
    'EXECUTIVE' => [
        ['Dashboard', 'admin-dashboard.php', '<rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" />'],
    ],
    'MONITORING' => [
        ['Konsumer', 'atasan-monitoring-konsumer.php', '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />'],
        ['Mikro', 'atasan-monitoring-mikro.php', '<path d="M3 21h18" /><path d="M5 21V7l8-4v18" /><path d="M19 21V11l-6-4" /><path d="M9 9v.01" /><path d="M9 12v.01" /><path d="M9 15v.01" /><path d="M9 18v.01" />'],
        ['Pawning', 'atasan-monitoring-pawning.php', '<circle cx="12" cy="12" r="9" /><path d="M14.8 9a2.8 2.8 0 0 0-2.8-1.5c-1.9 0-3 1-3 2.2s1.1 1.9 3 2.3 3 1.1 3 2.3-1.1 2.2-3 2.2A2.8 2.8 0 0 1 9.2 15" /><line x1="12" y1="6" x2="12" y2="7.5" /><line x1="12" y1="16.5" x2="12" y2="18" />'],
    ],
    'PERFORMANCE' => [
        ['KPI', 'atasan-kpi.php', '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17" /><polyline points="16 7 22 7 22 13" />'],
        ['NPF', 'atasan-npf.php', '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7" /><polyline points="16 17 22 17 22 11" />'],
        ['Outstanding', 'atasan-outstanding.php', '<line x1="12" y1="20" x2="12" y2="10" /><line x1="18" y1="20" x2="18" y2="4" /><line x1="6" y1="20" x2="6" y2="16" />'],
    ],
    'REPORT' => [
        ['Laporan', 'atasan-laporan.php', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="14" x2="15" y2="14" /><line x1="9" y1="17" x2="15" y2="17" />'],
    ],
    'AI INSIGHT' => [
        ['Executive Insight', 'atasan-ai-insight.php', '<path d="M9 18h6" /><path d="M10 22h4" /><path d="M12 2a7 7 0 0 0-7 7c0 3 2 4.5 3 6h8c1-1.5 3-3 3-6a7 7 0 0 0-7-7Z" />'],
    ],
    'SYSTEM' => [
        ['Profil', 'atasan-profil.php', '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="10" r="3" /><path d="M7 20.7a7.5 7.5 0 0 1 10 0" />'],
    ],
];

// Kumpulkan query key yang membedakan beberapa menu item yang menunjuk ke file dasar yang sama
function bsi_collect_menu_selector_keys(array $menus)
{
    $selectors = [];
    foreach ($menus as $menu) {
        foreach ($menu as $items) {
            foreach ($items as $item) {
                $file = $item[1];
                if (strpos($file, '?') === false) continue;
                [$base, $qs] = explode('?', $file, 2);
                parse_str($qs, $params);
                foreach (array_keys($params) as $key) {
                    $selectors[$base][$key] = true;
                }
            }
        }
    }
    return $selectors;
}
$GLOBALS['bsi_menu_selector_keys'] = bsi_collect_menu_selector_keys([$konsumer_menu, $mikro_menu, $pawning_menu, $atasan_menu]);

// Helper untuk merender satu link menu, lengkap dengan state aktif.
// Sekarang menggunakan warna BSI dengan kelas kustom via Tailwind arbitrary
function render_menu_item($label, $file, $icon_path, $current_page, $active_bg = 'bg-[#E6F4ED] text-[#006B3F]', $inactive_class = 'text-slate-500 hover:bg-[#E6F4ED] hover:text-[#006B3F]', $active_border = 'border-[#006B3F]')
{
    $parts = explode('?', $file, 2);
    $file_base = $parts[0];
    $is_active = false;

    if ($file_base === $current_page) {
        if (isset($parts[1])) {
            parse_str($parts[1], $item_params);
            $is_active = true;
            foreach ($item_params as $key => $value) {
                if (!isset($_GET[$key]) || (string) $_GET[$key] !== (string) $value) {
                    $is_active = false;
                    break;
                }
            }
        } else {
            $is_active = true;
            foreach ($GLOBALS['bsi_menu_selector_keys'][$file_base] ?? [] as $selector_key => $_unused) {
                if (isset($_GET[$selector_key]) && $_GET[$selector_key] !== '') {
                    $is_active = false;
                    break;
                }
            }
        }
    }

    $state_class = $is_active
        ? $active_bg . ' font-bold ' . $active_border . ' shadow-sm'
        : $inactive_class . ' font-medium border-transparent hover:border-[#3DAE7B] hover:translate-x-0.5';
    echo '<a href="' . htmlspecialchars($file) . '" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] transition-all duration-200 text-[13px] ' . $state_class . '">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">' . $icon_path . '</svg>';
    echo '<span class="truncate">' . htmlspecialchars($label) . '</span>';
    if ($is_active) {
        echo '<span class="ml-auto w-1.5 h-1.5 rounded-full bg-current opacity-70 flex-shrink-0"></span>';
    }
    echo '</a>';
}
?>

<?php
// Seluruh panel memakai tema terang yang seragam dengan aksen BSI
$sb_aside_class = 'bg-white border-r border-slate-100 shadow-sm';
$sb_logo_box_class = 'bg-slate-50 border border-slate-200';

// Warna aksen BSI (konsisten dengan premium-ui.php)
$sb_accent = [
    'text' => 'text-[#006B3F]',
    'logo_bg' => 'bg-[#006B3F]',
    'logo_grad' => 'from-[#004D2E] to-[#3DAE7B]',
    'active' => 'bg-[#E6F4ED] text-[#006B3F]',
    'border' => 'border-[#006B3F]',
    'ring' => 'focus:ring-[#006B3F]'
];
$sb_inactive_class = 'text-slate-500 hover:bg-[#E6F4ED] hover:text-[#006B3F]';

// Inisial 1-2 huruf untuk avatar karyawan
$sb_nama_parts = preg_split('/\s+/', trim($nama_user));
$sb_inisial = strtoupper(mb_substr($sb_nama_parts[0] ?? 'K', 0, 1) . mb_substr($sb_nama_parts[1] ?? '', 0, 1));
?>
<style>
    /* Scrollbar tipis khusus area navigasi sidebar */
    .sidebar-nav {
        scrollbar-width: thin;
        scrollbar-color: #e2e8f0 transparent;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 999px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }

    /* Tambahan untuk konsistensi warna BSI */
    .bsi-border {
        border-color: #006B3F;
    }

    .bsi-bg-soft {
        background-color: #E6F4ED;
    }

    .bsi-text-main {
        color: #006B3F;
    }

    .bsi-hover:hover {
        background-color: #E6F4ED;
        color: #006B3F;
    }
</style>

<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-[2px] z-40 hidden md:hidden"></div>
<aside id="sidebar" class="fixed md:static inset-y-0 left-0 z-50 w-72 max-w-[85%] md:w-64 md:max-w-none -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out <?= $sb_aside_class; ?> min-h-screen h-screen flex flex-col flex-shrink-0">

    <!-- HEADER: logo + kartu profil (tetap) -->
    <div class="flex-shrink-0 px-5 pt-5 pb-4">
        <div class="flex items-center gap-3 px-1 mb-5">
            <div class="w-11 h-11 rounded-xl flex-shrink-0 overflow-hidden bg-gradient-to-br <?= $sb_accent['logo_grad']; ?> shadow-sm shadow-slate-900/10 flex items-center justify-center">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Logo_Bank_Syariah_Indonesia.svg/512px-Logo_Bank_Syariah_Indonesia.svg.png"
                    alt="Logo BSI"
                    class="w-full h-full object-contain"
                    onerror="this.onerror=null; this.replaceWith(Object.assign(document.createElement('div'), {className:'w-full h-full flex items-center justify-center text-white font-extrabold text-sm tracking-tight', textContent:'BSI'}));">
            </div>
            <div class="min-w-0">
                <?php if ($in_atasan_admin): ?>
                    <h2 class="text-sm font-bold text-slate-800 tracking-wide truncate">BSI Executive</h2>
                    <p class="text-[10px] <?= $sb_accent['text']; ?> font-semibold uppercase tracking-wider truncate">Dashboard Atasan</p>
                <?php elseif ($in_mikro_admin): ?>
                    <h2 class="text-sm font-bold text-slate-800 tracking-wide truncate">BSI Mikro</h2>
                    <p class="text-[10px] <?= $sb_accent['text']; ?> font-semibold uppercase tracking-wider truncate">Dashboard Mikro</p>
                <?php elseif ($in_pawning_admin): ?>
                    <h2 class="text-sm font-bold text-slate-800 tracking-wide truncate">BSI Pawning</h2>
                    <p class="text-[10px] <?= $sb_accent['text']; ?> font-semibold uppercase tracking-wider truncate">Dashboard Gadai Emas</p>
                <?php elseif ($in_konsumer_admin): ?>
                    <h2 class="text-sm font-bold text-slate-800 tracking-wide truncate">BSI Konsumer</h2>
                    <p class="text-[10px] <?= $sb_accent['text']; ?> font-semibold uppercase tracking-wider truncate">Dashboard Nasabah</p>
                <?php else: ?>
                    <h2 class="text-sm font-bold text-slate-800 tracking-wide truncate">DATA CENTER</h2>
                    <p class="text-[10px] <?= $sb_accent['text']; ?> font-semibold uppercase tracking-wider truncate">Internal Kantor</p>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Badge role menggunakan warna BSI
        $sb_badge_color = 'bg-[#E6F4ED] text-[#006B3F] border-[#3DAE7B]';
        ?>
        <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
            <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center text-[11px] font-extrabold text-white bg-gradient-to-br <?= $sb_accent['logo_grad']; ?>">
                <?= htmlspecialchars($sb_inisial); ?>
            </div>
            <div class="min-w-0 flex-1">
                <h4 class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($nama_user); ?></h4>
                <span class="inline-block mt-1 px-2 py-0.5 rounded text-[9px] font-bold uppercase border tracking-wider <?= $sb_badge_color; ?>">
                    <?= htmlspecialchars($role_user); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- NAVIGASI: area scroll -->
    <div class="sidebar-nav flex-1 min-h-0 overflow-y-auto px-5">

        <?php if ($in_mikro_admin): ?>
            <nav class="space-y-1 text-sm">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-2 mb-2">Main Menu</p>
                <?php foreach ($mikro_menu['main'] as $item) render_menu_item($item[0], $item[1], $item[2], $current_page, $sb_accent['active'], $sb_inactive_class, $sb_accent['border']); ?>

                <?php foreach (['DATA USAHA', 'PRODUK BSI', 'PEMBIAYAAN', 'MONITORING', 'ANALITIK', 'LAPORAN', 'AI', 'PENGATURAN'] as $group): ?>
                    <?php if (!isset($mikro_menu[$group])) continue; ?>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 pt-4 mb-2"><?= $group; ?></p>
                    <?php foreach ($mikro_menu[$group] as $item) render_menu_item($item[0], $item[1], $item[2], $current_page, $sb_accent['active'], $sb_inactive_class, $sb_accent['border']); ?>
                <?php endforeach; ?>
            </nav>
        <?php elseif ($in_pawning_admin): ?>
            <nav class="space-y-1 text-sm">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-2 mb-2">Main Menu</p>
                <?php foreach ($pawning_menu['main'] as $item) render_menu_item($item[0], $item[1], $item[2], $current_page, $sb_accent['active'], $sb_inactive_class, $sb_accent['border']); ?>

                <?php foreach (['NASABAH', 'PRODUK GADAI', 'GADAI EMAS', 'DATA BARANG', 'MONITORING', 'LAPORAN', 'AI', 'PENGATURAN'] as $group): ?>
                    <?php if (!isset($pawning_menu[$group])) continue; ?>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 pt-4 mb-2"><?= $group; ?></p>
                    <?php foreach ($pawning_menu[$group] as $item) render_menu_item($item[0], $item[1], $item[2], $current_page, $sb_accent['active'], $sb_inactive_class, $sb_accent['border']); ?>
                <?php endforeach; ?>
            </nav>
        <?php elseif ($in_konsumer_admin): ?>
            <nav class="space-y-1 text-sm">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest px-2 mb-2">Main Menu</p>
                <?php foreach ($konsumer_menu['main'] as $item) render_menu_item($item[0], $item[1], $item[2], $current_page, $sb_accent['active'], $sb_inactive_class, $sb_accent['border']); ?>

                <?php foreach (['NASABAH', 'PRODUK BSI', 'PEMBIAYAAN', 'ANALITIK', 'LAPORAN', 'AI', 'PENGATURAN'] as $group): ?>
                    <?php if (!isset($konsumer_menu[$group])) continue; ?>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 pt-4 mb-2"><?= $group; ?></p>
                    <?php foreach ($konsumer_menu[$group] as $item) render_menu_item($item[0], $item[1], $item[2], $current_page, $sb_accent['active'], $sb_inactive_class, $sb_accent['border']); ?>
                <?php endforeach; ?>
            </nav>
        <?php elseif ($in_atasan_admin): ?>
            <nav class="space-y-1 text-sm">
                <?php foreach (['EXECUTIVE', 'MONITORING', 'PERFORMANCE', 'REPORT', 'AI INSIGHT', 'SYSTEM'] as $group): ?>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 pt-4 mb-2 first:pt-0"><?= $group; ?></p>
                    <?php foreach ($atasan_menu[$group] as $item) render_menu_item($item[0], $item[1], $item[2], $current_page, $sb_accent['active'], $sb_inactive_class, $sb_accent['border']); ?>
                <?php endforeach; ?>
            </nav>
        <?php else: ?>
            <nav class="space-y-1.5 text-sm">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 mb-2">Utama</p>

                <a href="<?= $base_url; ?>index.php" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent hover:border-[#3DAE7B] <?= $sb_inactive_class; ?> transition-all duration-200 font-medium hover:translate-x-0.5">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="9" x="3" y="3" rx="1" />
                        <rect width="7" height="5" x="14" y="3" rx="1" />
                        <rect width="7" height="9" x="14" y="12" rx="1" />
                        <rect width="7" height="5" x="3" y="16" rx="1" />
                    </svg>
                    Dashboard Overview
                </a>

                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 pt-4 mb-2">Manajemen Data</p>

                <?php if (in_array($role_user, ['admin', 'mikro', 'atasan'], true)): ?>
                    <a href="<?= htmlspecialchars($base_url . 'admin/Mikro/mikro-dashboard.php'); ?>" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent hover:border-[#3DAE7B] <?= $sb_inactive_class; ?> transition-all duration-200 font-medium hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m2 7 10-5 10 5v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" />
                            <path d="M12 22V12" />
                        </svg>
                        Segmen Mikro
                    </a>
                <?php endif; ?>

                <?php if (in_array($role_user, ['admin', 'konsumer', 'atasan'], true)): ?>
                    <a href="<?= htmlspecialchars($base_url . 'admin/konsumer/konsumer-dashboard.php'); ?>" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent hover:border-[#3DAE7B] <?= $sb_inactive_class; ?> transition-all duration-200 font-medium hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        Segmen Konsumer
                    </a>
                <?php endif; ?>

                <?php if (in_array($role_user, ['admin', 'pawning', 'atasan'], true)): ?>
                    <a href="<?= htmlspecialchars($base_url . 'admin/pawning/pawning-dashboard.php'); ?>" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent hover:border-[#3DAE7B] <?= $sb_inactive_class; ?> transition-all duration-200 font-medium hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" />
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                        </svg>
                        Segmen Gadai
                    </a>
                <?php endif; ?>

                <?php if ($role_user === 'atasan'): ?>
                    <a href="<?= htmlspecialchars($base_url . 'admin/Atasan/admin-dashboard.php'); ?>" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent hover:border-[#3DAE7B] <?= $sb_inactive_class; ?> transition-all duration-200 font-medium hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 21h18" />
                            <path d="M5 21V7l8-4v18" />
                            <path d="M19 21V11l-6-4" />
                            <path d="M9 9v.01" />
                            <path d="M9 12v.01" />
                            <path d="M9 15v.01" />
                            <path d="M9 18v.01" />
                        </svg>
                        Pusat Dashboard Atasan
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php if ($in_mikro_admin || $in_pawning_admin || $in_konsumer_admin || $in_atasan_admin): ?>
            <nav class="space-y-1 text-sm">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-2 pt-4 mb-2 border-t border-slate-100">Segmen Lain</p>
                <?php if (in_array($role_user, ['admin', 'mikro', 'atasan'], true)): ?>
                    <a href="../Mikro/mikro-dashboard.php" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent <?= $in_mikro_admin ? $sb_accent['active'] . ' border-' . $sb_accent['border'] : 'text-slate-500 hover:bg-[#E6F4ED] hover:text-[#006B3F] hover:border-[#3DAE7B]'; ?> font-medium transition-all duration-200 hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
                            <path d="m2 7 10-5 10 5v11a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" />
                            <path d="M12 22V12" />
                        </svg>
                        <span class="truncate">Sektor Mikro</span>
                    </a>
                <?php endif; ?>
                <?php if (in_array($role_user, ['admin', 'konsumer', 'atasan'], true)): ?>
                    <a href="../konsumer/konsumer-dashboard.php" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent <?= $in_konsumer_admin ? $sb_accent['active'] . ' border-' . $sb_accent['border'] : 'text-slate-500 hover:bg-[#E6F4ED] hover:text-[#006B3F] hover:border-[#3DAE7B]'; ?> font-medium transition-all duration-200 hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                        <span class="truncate">Sektor Konsumer</span>
                    </a>
                <?php endif; ?>
                <?php if (in_array($role_user, ['admin', 'pawning', 'atasan'], true)): ?>
                    <a href="../pawning/pawning-dashboard.php" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent <?= $in_pawning_admin ? $sb_accent['active'] . ' border-' . $sb_accent['border'] : 'text-slate-500 hover:bg-[#E6F4ED] hover:text-[#006B3F] hover:border-[#3DAE7B]'; ?> font-medium transition-all duration-200 hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
                            <rect x="2" y="7" width="20" height="14" rx="2" />
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                        </svg>
                        <span class="truncate">Sektor Gadai</span>
                    </a>
                <?php endif; ?>
                <?php if ($role_user === 'atasan'): ?>
                    <a href="../Atasan/admin-dashboard.php" class="flex items-center gap-3 pl-3 pr-2.5 py-2.5 rounded-r-lg rounded-l-sm border-l-[3px] border-transparent <?= $in_atasan_admin ? $sb_accent['active'] . ' border-' . $sb_accent['border'] : 'text-slate-500 hover:bg-[#E6F4ED] hover:text-[#006B3F] hover:border-[#3DAE7B]'; ?> font-medium transition-all duration-200 hover:translate-x-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0">
                            <path d="M3 21h18" />
                            <path d="M5 21V7l8-4v18" />
                            <path d="M19 21V11l-6-4" />
                            <path d="M9 9v.01" />
                            <path d="M9 12v.01" />
                            <path d="M9 15v.01" />
                            <path d="M9 18v.01" />
                        </svg>
                        <span class="truncate">Pusat Dashboard Atasan</span>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <div class="h-4"></div>
    </div>

    <!-- FOOTER: tombol keluar -->
    <div class="flex-shrink-0 px-5 pt-4 pb-5 border-t border-slate-100">
        <button data-logout class="flex items-center justify-center gap-2 w-full bg-[#E6F4ED] hover:bg-[#006B3F] text-[#006B3F] border border-[#3DAE7B] hover:text-white text-xs font-semibold py-2.5 px-4 rounded-xl transition-all cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
            Keluar Sistem
        </button>
    </div>

</aside>

<?php include __DIR__ . '/logout-modal.php'; ?>