<?php
/**
 * helpers/delete-activity.php
 * Centralized delete logic for activities across all dashboards.
 * Usage: require_once __DIR__ . '/../../helpers/delete-activity.php';
 *        handle_delete_activity($conn, $role_user, $redirect_url);
 */

require_once __DIR__ . '/csrf.php';

function handle_delete_activity(mysqli $conn, string $role_user, string $redirect_url = ''): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['hapus_aktivitas'])) {
        return;
    }

    if (!csrf_verify()) {
        http_response_code(403);
        die("CSRF Token Invalid");
    }

    $id = (int) ($_POST['id'] ?? 0);
    $segmen = $_POST['segmen'] ?? '';
    $tabel = null;

    // Map segment to table with role authorization
    if ($segmen === 'Mikro' && in_array($role_user, ['admin', 'mikro'], true)) {
        $tabel = 'nasabah_mikro';
    } elseif ($segmen === 'Konsumer' && in_array($role_user, ['admin', 'konsumer'], true)) {
        $tabel = 'nasabah_konsumer';
    } elseif ($segmen === 'Gadai' && in_array($role_user, ['admin', 'pawning'], true)) {
        $tabel = 'pawning';
    }

    if ($tabel && $id > 0) {
        $stmtDel = $conn->prepare("DELETE FROM `$tabel` WHERE id = ?");
        $stmtDel->bind_param("i", $id);
        $stmtDel->execute();
        $stmtDel->close();
    }

    // Redirect to prevent form resubmission on refresh
    if ($redirect_url === '') {
        $redirect_url = $_SERVER['PHP_SELF'];
    }
    header("Location: $redirect_url");
    exit();
}