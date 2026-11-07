<?php
/**
 * Shared utility functions for all dashboards.
 * Include via: require_once __DIR__ . '/../../helpers/utils.php';
 */

/**
 * Get badge CSS class for a given status.
 */
function badgeStatusPawning(string $status): string
{
    if ($status === 'Aktif') return 'bg-emerald-50 text-emerald-600 border border-emerald-100';
    if ($status === 'Lunas') return 'bg-sky-50 text-sky-600 border border-sky-100';
    if ($status === 'Lelang') return 'bg-rose-50 text-rose-600 border border-rose-100';
    return 'bg-slate-50 text-slate-500 border border-slate-100';
}

function badgeStatusKonsumer(string $status): string
{
    if ($status === 'disetujui') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if ($status === 'ditolak') return 'bg-rose-50 text-rose-700 border-rose-200';
    return 'bg-amber-50 text-amber-700 border-amber-200';
}

function badgeSegmen(string $segmen): string
{
    if ($segmen === 'Mikro') return 'bg-indigo-50 text-indigo-600 border border-indigo-100';
    if ($segmen === 'Konsumer') return 'bg-sky-50 text-sky-600 border border-sky-100';
    if ($segmen === 'Gadai') return 'bg-amber-50 text-amber-600 border border-amber-100';
    return 'bg-slate-50 text-slate-500 border border-slate-100';
}

/**
 * Check if a column exists in a table.
 */
function kolomExists(mysqli $conn, string $table, string $column): bool
{
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

/**
 * Read a setting from pawning_pengaturan with a default fallback.
 */
function ambilSetting(mysqli $conn, string $key, string $default = ''): string
{
    $stmt = $conn->prepare("SELECT nilai_setting FROM pawning_pengaturan WHERE nama_setting = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? $r['nilai_setting'] : $default;
}

/**
 * Format currency to Indonesian Rupiah format.
 */
function formatRupiah(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
