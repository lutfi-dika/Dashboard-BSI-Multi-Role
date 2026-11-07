<?php
// admin/konsumer/konsumer-filter-helper.php
// Membangun kondisi WHERE + parameter bind berdasarkan input pencarian & filter status
// dari $_GET, supaya logikanya konsisten dipakai di konsumer-data.php, export-pdf.php,
// dan export-excel.php (tidak duplikasi & tidak gampang beda hasil).

function konsumer_build_filter(array $get): array
{
    $cari   = trim($get['cari'] ?? '');
    $status = trim($get['status'] ?? '');
    $valid_status = ['pending', 'disetujui', 'ditolak'];

    $where  = ['1=1'];
    $types  = '';
    $params = [];

    if ($cari !== '') {
        $where[]  = '(k.nama_lengkap LIKE ? OR k.nik LIKE ? OR k.produk_diajukan LIKE ? OR k.pekerjaan LIKE ?)';
        $like     = '%' . $cari . '%';
        $types   .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (in_array($status, $valid_status, true)) {
        $where[]  = 'k.status = ?';
        $types   .= 's';
        $params[] = $status;
    }

    return [
        'where_sql' => implode(' AND ', $where),
        'types'     => $types,
        'params'    => $params,
        'cari'      => $cari,
        'status'    => $status,
    ];
}

/**
 * Bind parameter dinamis ke mysqli statement (jumlah parameter bisa berubah-ubah
 * tergantung filter yang aktif, jadi tidak bisa pakai bind_param biasa).
 */
function konsumer_bind_params(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }
    $refs = [];
    $refs[] = $types;
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
