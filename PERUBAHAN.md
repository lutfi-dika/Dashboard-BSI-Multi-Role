# Ringkasan Perubahan — Tahap 1: OTP & Keamanan Kritis

## 1. Setup awal (WAJIB sebelum dipakai)
1. Copy `.env.example` → `.env`, isi kredensial DB, SMTP (email OTP), dan `FONNTE_TOKEN` (opsional, untuk WA).
2. Jalankan migration: `sql/001_otp_migration.sql` ke database `database_pendataan-bsi`.
3. Pastikan folder `config/`, `helpers/`, `libs/`, `sql/` tidak bisa diakses langsung dari browser (sudah ditambahkan `.htaccess` — pastikan `AllowOverride All` aktif di Apache, atau terapkan aturan setara di Nginx).

## 2. Sistem OTP (baru)
- **Channel ganda**: Email (PHPMailer/SMTP, utama) atau WhatsApp (Fonnte, opsional) — dipilih user saat kirim OTP.
- OTP 6 digit, **disimpan sebagai hash** (`password_hash`) — bukan plaintext.
- Berlaku 5 menit, otomatis expired, sekali pakai (dihapus setelah berhasil diverifikasi).
- Resend dengan cooldown 60 detik (dicek di server, bukan cuma di JS).
- Maksimal 5 kali percobaan salah, lalu harus resend.
- Semua proses (kirim, resend, verifikasi) via AJAX + toast notification, tanpa reload halaman.
- Dipakai untuk 3 alur: **Login karyawan**, **Registrasi** (`auth/register.php`), **Lupa Password** (`auth/lupa-password.php` → `auth/reset-password.php`).
- Setelah OTP registrasi/verifikasi email berhasil, akun otomatis `status = 'verified'`.

File baru: `helpers/otp.php`, `config/mailer.php`, `config/fonnte.php`, `ajax/kirim-otp.php`, `ajax/verifikasi-otp.php`, `ajax/register-proses.php`, `ajax/lupa-password-proses.php`, `ajax/reset-password-proses.php`, `auth/register.php`, `auth/lupa-password.php`, `auth/reset-password.php`.

## 3. Perbaikan Keamanan
- **`fix.php` dihapus** — script ini reset password admin ke `admin123` dan proteksinya sudah di-comment-out; sangat berbahaya jika tertinggal di production.
- **Password plaintext fallback** di `proses-login.php` dihapus dari alur normal; jika akun lama masih plaintext, sistem otomatis migrasi ke hash saat login berhasil (transparan, tanpa perlu reset manual).
- **CSRF token** ditambahkan ke `login.php`, `proses-login.php`, dan seluruh endpoint AJAX baru (`helpers/csrf.php`).
- **Rate limiting login** sederhana: 5 kali gagal → kunci 60 detik (berbasis session; untuk production sebaiknya tambah rate limit berbasis IP di level web server juga).
- **Session fixation**: `session_regenerate_id(true)` dipanggil setelah login berhasil.
- **Kredensial tidak lagi hardcode**: DB, SMTP, dan token Fonnte sekarang dari `.env` (lihat `config/env.php`).
- Pesan error login digeneralisasi ("Username atau password salah") agar tidak membocorkan username mana yang terdaftar.

## 4. Yang BELUM diubah (sengaja, sesuai instruksi "jangan hapus fitur")
- Struktur dashboard Konsumer/Mikro/Pawning/Atasan, CRUD, export PDF/Excel — semua tetap seperti semula. Sudah saya tinjau: pola RBAC (`$_SESSION['role']`) di file-file ini sudah cukup baik, jadi tidak saya sentuh untuk menghindari regresi.
- `pilih-karyawan.php` tetap konsep aslinya (atasan/petugas memilih karyawan untuk dikirim OTP), hanya ditambah pilihan channel + AJAX.

## 5. Tahap 2 — Dashboard Konsumer (fungsionalisasi sisa halaman placeholder)
Melengkapi 7 halaman yang sebelumnya masih "Sedang Dikembangkan": Dashboard Analytics,
Dokumen Nasabah, Angsuran, Jatuh Tempo, Target vs Realisasi, Performa AO, Laporan
Harian & Bulanan. Semua memakai tema glass-card gelap yang sama seperti halaman
Konsumer lain (Notifikasi/Pengaturan/Profil) dan pola proteksi role yang sudah ada.

**Migrasi baru**: `sql/005_konsumer_tahap2_lanjutan.sql` menambahkan ke `nasabah_konsumer`
kolom `plafon_pengajuan`, `tenor_bulan`, `tanggal_pencairan`, `tanggal_jatuh_tempo`,
serta checklist dokumen `dok_ktp`/`dok_kk`/`dok_slip_gaji`/`dok_jaminan`; dan membuat
tabel baru `angsuran_konsumer` (jadwal cicilan) dan `target_konsumer` (target bulanan
per petugas). **Wajib dijalankan** agar halaman-halaman baru berfungsi penuh — setiap
halaman mengecek skemanya sendiri lewat `SHOW COLUMNS`/`SHOW TABLES` dan menampilkan
pesan yang jelas (bukan error) kalau migrasi belum dijalankan.

- **Dashboard Analytics**: tren 12 bulan (total/disetujui/ditolak), komposisi status,
  top 5 produk, rata-rata waktu proses & pendapatan pemohon — semua dari kolom yang
  sudah ada (tidak butuh migrasi).
- **Dokumen Nasabah**: checklist 4 dokumen per nasabah, klik untuk toggle status
  (pola sama seperti `Mikro/mikro-dokumen.php`).
- **Angsuran**: tombol "Generate Jadwal" membuat cicilan bulanan otomatis dari
  `plafon_pengajuan` / `tenor_bulan` untuk nasabah yang sudah disetujui; tombol
  "Tandai Lunas" per angsuran; angsuran yang lewat jatuh tempo otomatis ditandai
  "terlambat" saat halaman dibuka.
- **Jatuh Tempo**: mengelompokkan pembiayaan aktif ke Terlambat / H-7 / 30 hari
  berdasarkan `tanggal_jatuh_tempo`.
- **Target vs Realisasi**: admin menetapkan target pengajuan & nominal per petugas
  per bulan (`target_konsumer`); realisasi dihitung otomatis dari pengajuan yang
  disetujui pada periode yang sama.
- **Performa AO**: ranking petugas (input, disetujui, ditolak, tingkat approval,
  nominal disetujui) dengan filter periode 30/90 hari/semua.
- **Laporan Harian & Bulanan**: rekap per tanggal/bulan lengkap dengan grafik tren
  & tombol cetak (mengikuti pola `@media print` yang sudah ada di `konsumer-data.php`).
- **Monitoring Pembiayaan**: antrean pending diurutkan dari yang paling lama
  menunggu, funnel status, dan beban antrean per petugas. Sengaja **tidak**
  mengganti alur status pending/disetujui/ditolak yang sudah berjalan di
  `konsumer-approve.php` — pipeline multi-tahap yang lebih detail tetap jadi
  pekerjaan terpisah di masa depan supaya tidak berisiko regresi pada alur approval.

## 6. Roadmap Tahap Berikutnya (lihat ROADMAP.md)
Karena scope 4 dashboard enterprise penuh terlalu besar untuk satu batch perubahan tanpa risiko regresi, tahap selanjutnya akan dikerjakan bertahap per dashboard — lanjut ke Mikro.
