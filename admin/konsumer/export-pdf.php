<?php
// admin/konsumer/export-pdf.php
// Export PDF memakai FPDF (libs/FPDF/fpdf.php) — pure PHP, tanpa Composer/dependency lain,
// konsisten dengan pola vendoring manual yang sudah dipakai project ini (lihat libs/PHPMailer).
// Menghormati filter pencarian & status yang sedang aktif di konsumer-data.php.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/konsumer-filter-helper.php';
require_once __DIR__ . '/../../libs/FPDF/fpdf.php';

if (!isset($_SESSION['login_terverifikasi']) || $_SESSION['login_terverifikasi'] !== true) {
    header("Location: ../../pilih-karyawan.php");
    exit();
}
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'konsumer' && $_SESSION['role'] !== 'atasan')) {
    header("Location: ../../index.php");
    exit();
}

$filter = konsumer_build_filter($_GET);

$sql = "SELECT k.*, u.nama_lengkap AS nama_petugas
        FROM nasabah_konsumer k
        LEFT JOIN users u ON k.user_id = u.id
        WHERE {$filter['where_sql']}
        ORDER BY k.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
konsumer_bind_params($stmt, $filter['types'], $filter['params']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// FPDF hanya mendukung encoding Latin-1 (ISO-8859-1) secara native, jadi teks
// UTF-8 dari database perlu dikonversi supaya karakter non-ASCII tidak rusak.
function pdf_txt(?string $s): string
{
    $s = $s ?? '';
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return $converted !== false ? $converted : $s;
}

class KonsumerPDF extends FPDF
{
    public string $subtitle = '';

    public function Header(): void
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 7, 'Bank Syariah Indonesia - Data Segmen Konsumer', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100);
        $this->Cell(0, 5, $this->subtitle, 0, 1, 'C');
        $this->SetTextColor(0);
        $this->Ln(3);

        $this->SetFillColor(15, 23, 42);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 8);
        $widths = [38, 24, 30, 28, 28, 20, 26, 40];
        $headers = ['Nama Lengkap', 'NIK', 'Pekerjaan', 'Pendapatan', 'Produk', 'Status', 'No. Telp', 'Petugas'];
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0);
        $this->SetFont('Arial', '', 8);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(120);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb} - Dicetak ' . date('d-m-Y H:i'), 0, 0, 'C');
    }
}

$pdf = new KonsumerPDF('L', 'mm', 'A4');
$keterangan_filter = [];
if ($filter['cari'] !== '') {
    $keterangan_filter[] = 'Pencarian: "' . $filter['cari'] . '"';
}
if ($filter['status'] !== '') {
    $keterangan_filter[] = 'Status: ' . ucfirst($filter['status']);
}
$pdf->subtitle = pdf_txt($keterangan_filter ? implode(' | ', $keterangan_filter) : 'Seluruh Data');
$pdf->AliasNbPages();
$pdf->AddPage();

$widths = [38, 24, 30, 28, 28, 20, 26, 40];

while ($row = mysqli_fetch_assoc($result)) {
    $y_before = $pdf->GetY();
    if ($y_before > 180) { // guard tambahan, walau FPDF auto page-break aktif
        $pdf->AddPage();
    }

    $pdf->Cell($widths[0], 6, pdf_txt(mb_substr($row['nama_lengkap'], 0, 24)), 1, 0, 'L');
    $pdf->Cell($widths[1], 6, pdf_txt($row['nik']), 1, 0, 'L');
    $pdf->Cell($widths[2], 6, pdf_txt(mb_substr($row['pekerjaan'], 0, 18)), 1, 0, 'L');
    $pdf->Cell($widths[3], 6, 'Rp ' . number_format((float) $row['pendapatan_bulanan'], 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell($widths[4], 6, pdf_txt(mb_substr($row['produk_diajukan'], 0, 16)), 1, 0, 'L');
    $pdf->Cell($widths[5], 6, pdf_txt(ucfirst($row['status'] ?? 'pending')), 1, 0, 'C');
    $pdf->Cell($widths[6], 6, pdf_txt($row['no_telepon']), 1, 0, 'L');
    $pdf->Cell($widths[7], 6, pdf_txt(mb_substr($row['nama_petugas'] ?? 'Sistem/Admin', 0, 22)), 1, 0, 'L');
    $pdf->Ln();
}

$pdf->Output('D', 'data-konsumer-' . date('Y-m-d_His') . '.pdf');
