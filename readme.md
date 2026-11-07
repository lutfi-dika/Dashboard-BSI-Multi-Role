# Dashboard Pendataan — BSI KCP Tambun Bekasi

Sistem dashboard internal untuk pendataan dan monitoring nasabah pembiayaan
Bank Syariah Indonesia KCP Tambun Bekasi. Mencakup tiga segmen pembiayaan
(Mikro, Konsumer, Pawning/Gadai) dan satu dashboard pengawasan untuk level
Admin/Atasan.

> **Stack:** PHP murni (tanpa framework) + MySQL (mysqli) + Tailwind CSS (CDN)
> + Chart.js (CDN) + PHPMailer (vendor) + FPDF (vendor). Tanpa Composer, tanpa
> tanpa Composer dan npm — langsung jalan di Apache Laragon.

---

## Daftar Isi

- [Ringkasan Fitur](#ringkasan-fitur)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi & Setup](#instalasi--setup)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Jalankan Aplikasi](#jalankan-aplikasi)
- [Akun Demo](#akun-demo)
- [Cara Login](#cara-login)
- [Verifikasi OTP](#verifikasi-otp-email--whatsapp)
- [Role & Hak Akses](#role--hak-akses)
- [Fitur Dashboard Mikro](#fitur-dashboard-mikro)
- [Fitur Dashboard Konsumer](#fitur-dashboard-konsumer)
- [Fitur Dashboard Pawning (Gadai)](#fitur-dashboard-pawning-gadai)
- [Fitur Dashboard Admin/Atasan](#fitur-dashboard-adminatasan)
- [Struktur Folder & File](#struktur-folder--file)
- [Arsitektur & Alur Sistem](#arsitektur--alur-sistem)
- [Tabel Database](#tabel-database)
- [Migrasi Database](#migrasi-database)
- [Keamanan](#keamanan)
- [Teknologi yang Digunakan](#teknologi-yang-digunakan)
- [Roadmap Pengembangan](#roadmap-pengembangan)
- [Troubleshooting](#troubleshooting)
- [Catatan Pengembang](#catatan-pengembang)

---

## Ringkasan Fitur

| Segmen | Halaman | Fitur Utama |
|--------|---------|-------------|
| **Mikro** | 23 | Data nasabah UMKM, approval, survey kelayakan, angsuran, analisa rasio, kolektibilitas, sebaran wilayah, laporan bulanan, AI insight |
| **Konsumer** | 18 | Data nasabah, approval, Chart.js analytics, angsuran, jatuh tempo, target vs realisasi, performa AO, monitoring, laporan harian/bulanan |
| **Pawning** | 18 | Data gadai, taksiran, foto barang jaminan, perpanjangan, pelunasan, penebusan, lelang, jatuh tempo, outstanding, ujrah |
| **Atasan** | 9 | KPI gabungan, monitoring lintas segmen, NPF analysis, outstanding gabungan, executive insight, laporan cross-segment |

**Total: ~100 file PHP, ~12 file migrasi SQL, 3 file konfigurasi.**

---

## Persyaratan Sistem

| Komponen | Versi Minimum | Keterangan |
|----------|---------------|------------|
| **PHP** | 8.1+ | `password_hash()`, prepared statements, type hints |
| **MySQL / MariaDB** | 5.7+ / 10.3+ | `utf8mb4`, `ALTER TABLE`, `IFNULL` |
| **Apache** | 2.4+ | `AllowOverride All` untuk `.htaccess` |
| **Laragon** | Apache 2.4+ | Apache port 80, MySQL port 3306 |

**Optional:**
- Gmail App Password (untuk OTP email)
- Fonnte API Token (untuk OTP WhatsApp)

---

## Instalasi & Setup

### 1. Clone / Copy Project

Letakkan project di `C:\laragon\www\<nama-project>`.

### 2. Konfigurasi Environment

Copy `.env.example` menjadi `.env`:

```bash
cp .env.example .env
```

Buka `.env` dan isi seluruh kredensial sesuai lingkungan Anda. Lihat
bagian [Konfigurasi Environment](#konfigurasi-environment) untuk detail.

### 3. Jalankan Migrasi Database

**Fresh install:**

```sql
SOURCE sql/000_base_mikro_schema.sql;
```

**Upgrade dari versi sebelumnya:**

```sql
SOURCE sql/001_otp_migration.sql;
SOURCE sql/002_mikro_dashboard_kpi.sql;
SOURCE sql/003_mikro_dokumen.sql;
SOURCE sql/004_notifikasi_preferensi.sql;
SOURCE sql/005_konsumer_tahap2_lanjutan.sql;
SOURCE sql/006_mikro_survey_angsuran.sql;
SOURCE sql/007_pawning_schema.sql;
SOURCE sql/008_pawning_fitur.sql;
SOURCE sql/009_pawning_seed_demo.sql;
SOURCE sql/010_bsi_produk.sql;
```

### 4. Konfigurasi Apache Laragon

Pastikan `AllowOverride All` aktif agar `.htaccess` berfungsi:

```apache
<Directory "C:/laragon/www">
    AllowOverride All
    Require all granted
</Directory>
```

### 5. Jalankan Aplikasi

Lihat bagian [Jalankan Aplikasi](#jalankan-aplikasi).

---

## Konfigurasi Environment

File `.env` mengatur seluruh kredensial aplikasi. Gunakan `.env.example`
sebagai template.

### Database

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=database_pendataan-bsi
```

> **Penting:** Project ini dikonfigurasi untuk MySQL Laragon pada
> `127.0.0.1:3306`.

### SMTP (Email OTP)

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=your-email@gmail.com
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-gmail-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME="BSI Data Center"
```

> Gunakan **Gmail App Password** (bukan password biasa). Buat di:
> https://myaccount.google.com/apppasswords

### Fonnte (WhatsApp OTP) — Optional

```env
FONNTE_TOKEN=your-fonnte-api-token
```

> Pastikan nomor HP karyawan sudah terdaftar di sistem Fonnte.

### Application

```env
APP_ENV=production
```

> Set ke `development` untuk menampilkan pesan error detail. Di `production`,
> error ditampilkan secara generik.

---

## Jalankan Aplikasi

### Menggunakan Laragon (Recommended)

1. Letakkan project di `C:\laragon\www\dashboard-pendataan` atau gunakan
   virtual host Laragon untuk subfolder project ini.
2. Jalankan **Start All** dari Laragon, lalu pilih **Reload**.
3. Akses: `http://dashboard-pendataan.test`

### Menggunakan PHP Built-in Server (Development)

```bash
php -S localhost:8000 router-test.php
```

Akses: `http://localhost:8000/auth/login.php`

> `router-test.php` hanya untuk development. Untuk production, gunakan
> Apache atau Nginx.

---

## Akun Demo

Setelah menjalankan migrasi `000_base_mikro_schema.sql`, tersedia akun demo:

| Username | Password | Role | Email |
|----------|----------|------|-------|
| `admin` | `password123` | admin | admin@bsi.local |
| `petugas.mikro` | `password123` | mikro | petugas.mikro@bsi.local |
| `atasan` | `password123` | atasan | atasan@bsi.local |

---

## Cara Login

1. Buka halaman `auth/login.php`, masukkan **username** dan **password**.
2. Sistem memverifikasi kredensial menggunakan `password_verify()`. Jika akun
   lama masih memakai password lama (belum di-hash), sistem otomatis
   meng-hash ulang secara transparan saat login berhasil — tidak perlu reset
   manual.
3. Setelah password benar, sesi login dianggap **belum penuh** — user akan
   diarahkan ke halaman `pilih-karyawan.php` untuk lanjut ke tahap verifikasi
   OTP sebelum bisa mengakses dashboard.
4. Login dilindungi **rate limiting**: 5 kali percobaan gagal berturut-turut
   akan mengunci login selama 60 detik.
5. Setiap request login juga divalidasi dengan **CSRF token** untuk mencegah
   pengiriman form dari luar sistem.

---

## Verifikasi OTP (Email & WhatsApp)

Setelah login dengan password berhasil, sistem meminta verifikasi OTP
sebagai lapisan keamanan kedua sebelum masuk ke dashboard.

**Langkah verifikasi:**

1. Di halaman `pilih-karyawan.php`, pilih nama karyawan yang akan menerima
   kode OTP, lalu pilih **channel pengiriman**:
   - **Email** — dikirim lewat SMTP (PHPMailer), jadi channel utama.
   - **WhatsApp** — dikirim lewat API Fonnte, sifatnya opsional (perlu
     `FONNTE_TOKEN` diisi di `.env` dan karyawan sudah punya nomor HP
     terdaftar).
2. Sistem generate kode OTP **6 digit acak**, lalu:
   - Disimpan di database dalam bentuk **hash** (`password_hash`), bukan
     plaintext.
   - Berlaku selama **5 menit**, otomatis kedaluwarsa setelahnya.
   - **Sekali pakai** — langsung dihapus setelah berhasil diverifikasi.
3. Masukkan kode OTP di halaman `input-otp.php`. Proses kirim, verifikasi,
   dan resend semuanya berjalan lewat **AJAX** (tanpa reload halaman),
   dengan notifikasi toast.
4. Jika OTP salah, maksimal **5 kali percobaan** sebelum harus meminta kode
   baru (resend).
5. Tombol **Resend OTP** punya cooldown **60 detik**, divalidasi di sisi
   server (bukan cuma di JavaScript) agar tidak bisa dilewati.
6. Setelah OTP benar, sesi ditandai `login_terverifikasi = true` dan user
   otomatis diarahkan ke dashboard sesuai role/divisinya.

Sistem OTP yang sama juga dipakai untuk alur **Registrasi akun baru**
(`auth/register.php`, verifikasi email) dan **Lupa Password**
(`auth/lupa-password.php` -> `auth/reset-password.php`).

---

## Role & Hak Akses

Setiap akun karyawan (`users`) punya satu `role`, yang menentukan dashboard
mana yang bisa diakses setelah login:

| Role               | Dashboard Tujuan                                  |
| ------------------ | ------------------------------------------------- |
| `admin`, `atasan`  | Dashboard Admin/Atasan (pengawasan lintas segmen) |
| `konsumer`         | Dashboard Konsumer                                |
| `mikro`            | Dashboard Mikro                                   |
| `pawning`, `gadai` | Dashboard Pawning (Gadai)                         |

Setiap halaman di masing-masing dashboard memvalidasi role di sisi server —
user tidak bisa mengakses dashboard segmen lain hanya dengan mengganti URL.

---

## Fitur Dashboard Mikro

Dashboard untuk petugas Account Officer (AO) segmen pembiayaan mikro/UMKM.

### Manajemen Nasabah

- **Input & Data Nasabah** — tambah, lihat, dan kelola data debitur mikro
- **Approval Pengajuan** — proses persetujuan pengajuan pembiayaan
- **Survey Usaha & Kelayakan** — form penilaian usaha nasabah dengan sistem
  skor kelayakan (0-100)

### Monitoring & Analisa

- **Monitoring Angsuran** — jadwal dan status cicilan (belum bayar / lunas /
  terlambat), dengan jadwal yang di-generate otomatis dari plafon & tenor
- **Analisa Rasio & Tren** — rasio plafon terhadap omzet per sektor usaha,
  tren 6 bulan terakhir, serta daftar top 5 nasabah paling layak dan top 5
  paling berisiko
- **Kolektibilitas & Outstanding** — pemantauan status kolektibilitas dan
  nilai outstanding pembiayaan
- **Sebaran Wilayah** — pemetaan distribusi nasabah berdasarkan wilayah

### Laporan & Export

- **Target AO** — target dan pencapaian per Account Officer
- **Jatuh Tempo** — daftar pembiayaan yang mendekati/melewati jatuh tempo
- **Laporan Bulanan** — rekap KPI, per AO, dan per sektor usaha, dilengkapi
  fitur cetak
- **Dokumen Nasabah** — checklist kelengkapan dokumen persyaratan
- **Export Data** — export ke Excel

### Produk BSI

- KUR Super Mikro
- KUR Mikro
- KUR Kecil
- BSI Mikro iB

### Lainnya

- **Asisten Analisa (rule-based)** — insight otomatis berbasis aturan
  (bukan AI generatif), membantu identifikasi pola risiko
- **Profil & Pengaturan** — edit profil, ganti password, preferensi
  notifikasi (email/WhatsApp/pengingat jatuh tempo)

---

## Fitur Dashboard Konsumer

Dashboard untuk petugas segmen pembiayaan konsumer (KPR, kendaraan, dll).

### Manajemen Nasabah

- **Data Nasabah & Statistik** — daftar pengajuan dengan pagination dan
  filter status (pending/disetujui/ditolak)
- **Approval Pengajuan** — proses persetujuan/penolakan pengajuan nasabah
- **Dokumen Nasabah** — checklist kelengkapan dokumen (KTP, KK, Slip Gaji,
  Jaminan) dengan status yang bisa di-toggle

### Monitoring & Analisa

- **Dashboard Analytics** — visualisasi (Chart.js): tren 12 bulan, komposisi
  status pengajuan, top 5 produk terlaris, dan rata-rata waktu proses
- **Angsuran** — generate jadwal cicilan otomatis dari plafon & tenor,
  tandai lunas per angsuran, dan penandaan otomatis untuk angsuran yang
  terlambat
- **Jatuh Tempo** — pembiayaan aktif dikelompokkan menjadi Terlambat, H-7,
  dan 30 hari ke depan
- **Monitoring Pembiayaan** — antrean pengajuan pending (diurutkan dari yang
  paling lama menunggu), funnel status, dan beban kerja per petugas

### Target & Performa

- **Target vs Realisasi** — target pengajuan & nominal per petugas per
  bulan, dibandingkan dengan realisasi aktual
- **Performa AO** — ranking petugas berdasarkan jumlah input, tingkat
  approval, dan nominal disetujui, dengan filter periode

### Laporan & Export

- **Laporan Harian & Bulanan** — rekap agregat per periode, lengkap dengan
  grafik tren dan opsi cetak
- **Export Data** — export ke PDF dan Excel

### Produk BSI

- BSI Griya iB (KPR)
- BSI OTO iB (Kendaraan)
- BSI Pensiun iB
- BSI Umroh iB
- BSI Multiguna iB
- BSI Fleksi iB

### Lainnya

- **Notifikasi** — pengajuan pending yang sudah lama menunggu dan aktivitas
  approval terbaru
- **Profil & Pengaturan** — edit profil, ganti password, preferensi
  notifikasi

---

## Fitur Dashboard Pawning (Gadai)

Dashboard untuk pengelolaan produk gadai (rahn).

### Transaksi Gadai

- **Input Data Gadai** — pendataan nasabah dan barang jaminan
- **Data & Detail** — daftar transaksi gadai dengan pencarian, filter, dan
  halaman detail
- **Foto Barang Jaminan** — upload foto (JPG/PNG/WebP) sebagai dokumentasi
  barang gadai, dengan validasi format file

### Penilaian & Pengelolaan

- **Taksiran** — input dan penyesuaian nilai taksiran barang jaminan
- **Perpanjangan** — proses perpanjangan masa gadai
- **Pelunasan** — proses pelunasan oleh nasabah
- **Penebusan** — proses penebusan barang jaminan
- **Lelang** — pencatatan barang yang dilelang, termasuk harga terjual dan
  data pemenang lelang

### Monitoring

- **Jatuh Tempo** — pemantauan masa jatuh tempo gadai
- **Outstanding** — nilai pinjaman gadai yang masih berjalan
- **Ujrah** — pencatatan biaya pemeliharaan/penitipan barang gadai

### Laporan & Export

- **Laporan & Export** — rekap dan export data gadai

### Produk BSI

- Gadai Emas iB
- Gadai Non Emas
- Fleksi Emas iB

### Lainnya

- **Notifikasi, Profil & Pengaturan** — sama seperti dashboard lain

---

## Fitur Dashboard Admin/Atasan

Dashboard pengawasan lintas segmen untuk role `admin` dan `atasan`.

### Ringkasan Eksekutif

- **Ringkasan KPI Gabungan** — total nasabah, outstanding, dan realisasi
  dari ketiga segmen (Mikro, Konsumer, Pawning) dalam satu tampilan

### Monitoring

- **Monitoring per Segmen** — pemantauan detail untuk masing-masing
  dashboard Mikro, Konsumer, dan Pawning

### Analisa

- **NPF (Non-Performing Financing)** — persentase dan distribusi
  pembiayaan bermasalah, termasuk breakdown per cabang
- **Outstanding Gabungan** — akumulasi nilai outstanding dari seluruh
  segmen

### Lainnya

- **Asisten Insight (rule-based)** — ringkasan otomatis kondisi portofolio
  berbasis aturan
- **Laporan** — rekap lintas segmen
- **Aktivitas Terbaru** — daftar aktivitas terbaru gabungan dari ketiga
  segmen

---

## Struktur Folder & File

```
dashboard-pendataan/
|-- .env                  # Kredensial (DB, SMTP, Fonnte) -- JANGAN di-commit
|-- .env.example          # Contoh format .env tanpa kredensial asli
|-- .htaccess             # Blokir akses langsung ke .env dari browser
|-- README.md             # Dokumen ini
|-- ROADMAP.md            # Rencana pengembangan per tahap
|-- PERUBAHAN.md          # Catatan riwayat perubahan tiap tahap
|-- index.php             # Entry point -- cek sesi, arahkan ke dashboard sesuai role
|-- pilih-karyawan.php    # Halaman pilih karyawan & channel OTP
|-- input-otp.php         # Halaman input kode OTP 6 digit
|-- router-test.php       # Router untuk PHP built-in server (dev only)
|
|-- auth/                 # Halaman-halaman autentikasi
|   |-- login.php              # Form login
|   |-- proses-login.php       # Proses verifikasi password + rate limiting
|   |-- logout.php             # Hapus sesi
|   |-- register.php           # Form registrasi akun baru
|   |-- lupa-password.php      # Form permintaan reset password
|   |-- reset-password.php     # Form input password baru setelah OTP
|
|-- ajax/                 # Endpoint AJAX (JSON response)
|   |-- kirim-otp.php              # Generate & kirim OTP (email/WA)
|   |-- verifikasi-otp.php         # Cek kode OTP yang diinput user
|   |-- register-proses.php        # Proses simpan akun baru
|   |-- lupa-password-proses.php   # Proses kirim OTP untuk reset password
|   |-- reset-password-proses.php  # Proses simpan password baru
|
|-- config/               # Konfigurasi & koneksi database
|   |-- env.php                # Loader file .env (tanpa dependency composer)
|   |-- database.php           # Koneksi ke MySQL (mysqli)
|   |-- mailer.php             # Konfigurasi SMTP untuk kirim OTP via email
|   |-- fonnte.php             # Konfigurasi API Fonnte untuk kirim OTP via WA
|   |-- authcheck.php          # Helper cek status login
|
|-- helpers/              # Fungsi bantu lintas halaman
|   |-- csrf.php                    # Generate & validasi CSRF token
|   |-- otp.php                     # Logika inti OTP (generate, hash, expiry)
|   |-- dashboard-redirect.php      # Mapping role -> path dashboard tujuan
|
|-- components/           # Komponen UI yang dipakai berulang
|   |-- sidebar.php               # Sidebar navigasi (menyesuaikan role)
|   |-- clock.php                 # Widget jam realtime
|
|-- views/                # Halaman tambahan lintas segmen
|   |-- pipeline.php              # Tampilan pipeline/tahapan pengajuan
|   |-- pipeline-update-status.php # Proses update status pipeline
|   |-- Kinerja.php               # Halaman kinerja
|
|-- libs/                 # Library pihak ketiga (vendor)
|   |-- PHPMailer/                # Library kirim email (OTP email)
|   |-- FPDF/                     # Library generate PDF (export laporan)
|
|-- sql/                  # Migrasi database (jalankan berurutan)
|   |-- 000_base_mikro_schema.sql        # Skema dasar + data contoh
|   |-- 001_otp_migration.sql            # Kolom & tabel untuk OTP
|   |-- 002_mikro_dashboard_kpi.sql      # Kolom KPI dashboard Mikro
|   |-- 003_mikro_dokumen.sql            # Kolom checklist dokumen Mikro
|   |-- 004_notifikasi_preferensi.sql    # Preferensi notifikasi user
|   |-- 005_konsumer_tahap2_lanjutan.sql # Kolom plafon/tenor + tabel angsuran & target Konsumer
|   |-- 006_mikro_survey_angsuran.sql    # Kolom survey & tabel angsuran Mikro
|   |-- 007_pawning_schema.sql           # Skema dasar tabel Pawning
|   |-- 008_pawning_fitur.sql            # Fitur tambahan Pawning
|   |-- 009_pawning_seed_demo.sql        # Data contoh Pawning
|   |-- 010_bsi_produk.sql               # Data master produk BSI
|
|-- uploads/              # File yang diunggah user
|   |-- pawning/                   # Foto barang jaminan gadai
|
|-- admin/                # Halaman dashboard per segmen/role
    |-- Mikro/                     # Dashboard segmen Mikro (23 halaman)
    |-- konsumer/                  # Dashboard segmen Konsumer (18 halaman)
    |-- pawning/                   # Dashboard segmen Pawning/Gadai (18 halaman)
    |-- Atasan/                    # Dashboard Admin/Atasan (9 halaman)
```

**Catatan penting soal struktur:**

- Folder `config/`, `helpers/`, `libs/`, dan `sql/` masing-masing punya file
  `.htaccess` sendiri (`Require all denied`) supaya tidak bisa diakses
  langsung lewat URL browser — hanya bisa di-include dari dalam kode PHP.
- Folder `uploads/` belum punya `.htaccess` pembatas — perlu ditambahkan
  agar file yang diunggah tidak bisa dieksekusi sebagai script.
- `router-test.php` hanya relevan kalau menjalankan lewat
  `php -S localhost:8000 router-test.php` (built-in server PHP untuk
  development). Tidak diperlukan di hosting Apache/Nginx production.

---

## Arsitektur & Alur Sistem

### Alur Login (3 Langkah)

```
auth/login.php               -- 1. Input username + password
        |
        v (password benar)
pilih-karyawan.php           -- 2. Pilih karyawan + channel OTP (Email/WA)
        |
        v (OTP terkirim)
input-otp.php                -- 3. Input kode OTP 6 digit
        |
        v (OTP terverifikasi)
index.php -> dashboard       -- Arahkan ke dashboard sesuai role
```

### Alur Autentikasi AJAX

```
Client (JavaScript fetch)
    |
    |-- POST ajax/kirim-otp.php        -> Generate OTP, kirim via Email/WA
    |-- POST ajax/verifikasi-otp.php   -> Verifikasi OTP, set session
    |-- POST ajax/register-proses.php  -> Proses registrasi baru
    |-- POST ajax/lupa-password-proses.php  -> Kirim OTP reset password
    |-- POST ajax/reset-password-proses.php -> Simpan password baru
    |
    v
JSON Response { success: true/false, message: "...", redirect: "..." }
```

### Alur Data Nasabah

```
Input (tambah.php)
    |
    v
Data List (data.php) --filter/search/pagination-->
    |
    v
Detail / Approval
    |
    v
Monitoring (angsuran, jatuh tempo, kolektibilitas)
    |
    v
Laporan & Export (PDF/Excel)
```

### Pola Proteksi Halaman

Setiap halaman dashboard menerapkan:

1. **Session check** — cek `$_SESSION['user_id']` dan `$_SESSION['login_terverifikasi']`
2. **Role validation** — cek `$_SESSION['role']` sesuai halaman yang diakses
3. **CSRF token** — divalidasi untuk semua request POST
4. **Input sanitization** — `htmlspecialchars()` pada semua output
5. **Prepared statements** — `mysqli_prepare()` untuk semua query database

---

## Tabel Database

### Tabel Utama

| Tabel | Keterangan |
|-------|------------|
| `users` | Akun karyawan (role: admin, atasan, mikro, konsumer, pawning). Kolom OTP, preferensi notifikasi, status. |
| `nasabah_mikro` | Nasabah pembiayaan mikro/UMKM (survey, dokumen, kolektibilitas, plafon, outstanding) |
| `nasabah_konsumer` | Nasabah pembiayaan konsumer (KPR, kendaraan, plafon, tenor, dokumen) |
| `pawning` | Transaksi gadai (barang jaminan, taksiran, status, ujrah) |
| `angsuran_mikro` | Jadwal angsuran pembiayaan mikro |
| `angsuran_konsumer` | Jadwal angsuran pembiayaan konsumer |
| `target_konsumer` | Target bulanan per petugas (konsumer) |

### Tabel Pendukung Pawning

| Tabel | Keterangan |
|-------|------------|
| `pawning_foto` | Foto barang jaminan |
| `pawning_perpanjangan` | Riwayat perpanjangan masa gadai |
| `pawning_pelunasan` | Riwayat pelunasan/penebusan |
| `pawning_lelang` | Catatan lelang barang |
| `pawning_taksiran` | Riwayat perubahan nilai taksiran |
| `pawning_pengaturan` | Pengaturan sistem gadai (key-value store) |

---

## Migrasi Database

File migrasi ada di folder `sql/`, nomor urut menentukan urutan eksekusi.

### Fresh Install

Jalankan **hanya** `000_base_mikro_schema.sql` — file ini membuat database,
seluruh tabel, dan data contoh sekaligus.

### Upgrade

Jalankan file `001` sampai `010` secara berurutan sesuai nomor.

### Catatan Penting

- File migrasi sengaja **tidak** menggunakan `IF NOT EXISTS` agar kompatibel
  dengan MySQL Laragon. Error duplikat kolom/tabel saat dijalankan ulang adalah
  normal dan bisa diabaikan.
- Setiap halaman dashboard mengecek skema yang dibutuhkan via `SHOW COLUMNS`
  / `SHOW TABLES` dan menampilkan pesan informatif (bukan error) jika
  migrasi belum dijalankan.

---

## Keamanan

### Fitur Keamanan yang Terimplementasi

| Fitur | Deskripsi |
|-------|-----------|
| **Password Hashing** | `password_hash(PASSWORD_DEFAULT)` (bcrypt). Migrasi transparan dari plaintext untuk akun lama. |
| **CSRF Token** | Token unik per session, divalidasi di semua form dan endpoint AJAX (`helpers/csrf.php`). |
| **Rate Limiting** | 5 kali gagal = kunci 60 detik (berbasis session). |
| **Session Fixation Prevention** | `session_regenerate_id(true)` setelah login berhasil. |
| **OTP Hashed** | Kode OTP disimpan sebagai `password_hash()`, bukan plaintext. |
| **Prepared Statements** | Seluruh query database menggunakan `mysqli_prepare()` — tidak ada SQL injection. |
| **Output Sanitization** | `htmlspecialchars()` pada seluruh output HTML. |
| **File Access Control** | `.htaccess` (`Require all denied`) di `config/`, `helpers/`, `libs/`, `sql/`, `.env`. |
| **Generic Error Messages** | Pesan error login digeneralisasi untuk mencegah username enumeration. |
| **Environment-based Errors** | `APP_ENV` mengontrol detail error yang ditampilkan. |

### Role-Based Access Control (RBAC)

- Setiap halaman memvalidasi `$_SESSION['role']` di sisi server
- User tidak bisa mengakses dashboard segmen lain dengan mengganti URL
- Sidebar navigasi menyesuaikan berdasarkan role

### Catatan Keamanan

- Folder `uploads/` belum punya `.htaccess` — sebaiknya tambahkan:
  ```
  # uploads/.htaccess
  RemoveHandler .php .phtml .php3 .php4 .php5 .php7
  AddType text/plain .php .phtml .php3 .php4 .php5 .php7
  ```
- Rate limiting saat ini berbasis session, belum berbasis IP. Untuk
  production, disarankan menambahkan rate limiting di level web server.

---

## Teknologi yang Digunakan

| Teknologi | Sumber | Kegunaan |
|-----------|--------|----------|
| **PHP 8.1+** | Laragon PHP | Server-side language |
| **MySQL / MariaDB** | Laragon (port 3306) | Database |
| **mysqli** | PHP built-in | Database driver (prepared statements) |
| **Tailwind CSS** | CDN (`@tailwindcss/browser@4`) | UI styling |
| **Plus Jakarta Sans** | Google Fonts CDN | Font utama |
| **Chart.js** | CDN | Visualisasi data (line, doughnut, bar charts) |
| **PHPMailer** | Vendored di `libs/PHPMailer/` | Kirim email OTP via SMTP |
| **FPDF** | Vendored di `libs/FPDF/` | Generate PDF untuk export laporan |
| **Fonnte API** | External API | Kirim OTP via WhatsApp (opsional) |
| **Font Awesome 6** | CDN | Ikon di halaman autentikasi |

**Tidak ada Composer, npm, atau package manager lain.** Library di-vendor
secara manual atau dimuat dari CDN.

---

## Roadmap Pengembangan

| Tahap | Status | Deskripsi |
|-------|--------|-----------|
| **Tahap 1** | Selesai | OTP & Keamanan Kritis |
| **Tahap 2** | Selesai | Dashboard Konsumer |
| **Tahap 3** | Selesai | Dashboard Mikro |
| **Tahap 4** | Dalam Progres | Dashboard Pawning |
| **Tahap 5** | Parsial | Dashboard Atasan |
| **Tahap 6** | Belum Mulai | Polish UI/UX & Keamanan Lanjutan |

### Rencana Tahap 6 (UI/UX & Security)

- Dark mode (Tailwind `dark:` variant + toggle di localStorage)
- Skeleton loading di semua tabel data
- Validasi upload file (mime-type whitelist, max size, rename acak)
- Rate limiting berbasis IP di level web server
- Audit log terpusat (tabel `audit_log`)

Lihat `ROADMAP.md` untuk detail lengkap dan `PERUBAHAN.md` untuk riwayat
perubahan per tahap.

---

## Troubleshooting

### Database Connection Failed

- Pastikan MySQL berjalan di Laragon
- Periksa `DB_HOST=127.0.0.1` dan `DB_PORT=3306` di `.env`
- Pastikan `DB_NAME` sesuai: `database_pendataan-bsi`

### Halaman Tidak Bisa Diakses (403 Forbidden)

- Pastikan `AllowOverride All` aktif di Apache
- Cek apakah file `.htaccess` ada di root project

### Error "Tabel Tidak Ada"

- Jalankan migrasi SQL secara berurutan dari `sql/001_otp_migration.sql`
  sampai `sql/010_bsi_produk.sql`
- Atau untuk fresh install, jalankan `sql/000_base_mikro_schema.sql`

### OTP Tidak Terkirim

- **Email:** Pastikan SMTP credentials benar di `.env`. Gunakan Gmail App
  Password, bukan password biasa.
- **WhatsApp:** Pastikan `FONNTE_TOKEN` terisi dan nomor HP terdaftar di
  Fonnte. Tanpa token, channel WhatsApp tidak tersedia.

### CSS / Styling Tidak Muncul

- Pastikan koneksi internet aktif (Tailwind CSS dimuat dari CDN)
- Jika menggunakan offline, CDN perlu diganti ke local copy

---

## Catatan Pengembang

- **Routing:** Tidak ada routing framework. Setiap halaman adalah file PHP
  langsung. URL mapping mengikuti struktur folder.
- **AJAX Pattern:** Semua AJAX endpoint (`ajax/`) menerima POST dan
  mengembalikan JSON `{ success: bool, message: string, redirect?: string }`.
- **Sidebar:** Komponen sidebar (`components/sidebar.php`) adalah single
  file 500+ baris yang menyesuaikan menu berdasarkan `$_SESSION['role']`.
- **Session:** Session PHP standar. Login 2FA ditandai dengan
  `$_SESSION['login_terverifikasi'] = true` setelah OTP berhasil.
- **File Upload:** Saat ini hanya untuk foto barang gadai di folder
  `uploads/pawning/`. Format: JPG, PNG, WebP.
- **Chart.js:** Dimuat dari CDN global. Setiap halaman analytics inisialisasi
  chart secara inline di `<script>` block.

---

## Lisensi

Proyek internal Bank Syariah Indonesia — KCP Tambun Bekasi.
Tidak untuk distribusi publik.
