# Roadmap Pengembangan Bertahap

Status Tahap 1 (OTP + Security kritis): **Selesai** — lihat PERUBAHAN.md.
Status Tahap 2 (Dashboard Konsumer): **Selesai** — lihat PERUBAHAN.md.

## Tahap 2 — Dashboard Konsumer
- [x] Statistik & pagination + filter status (`konsumer-dashboard.php`, `konsumer-data.php` — sudah berjalan)
- [x] Export PDF & Excel pada `konsumer-data.php` (sudah berjalan)
- [x] `konsumer-profil.php` — edit profil & ganti password
- [x] `konsumer-notifikasi.php` — pengajuan pending lama + aktivitas approval terbaru
- [x] `konsumer-pengaturan.php` — preferensi notifikasi (email/WA/peringatan tertunda)
- [x] `konsumer-analytics.php` — Chart.js: tren 12 bulan, komposisi status, top produk, rata-rata waktu proses
- [x] `konsumer-dokumen.php` — checklist kelengkapan dokumen (KTP/KK/Slip Gaji/Jaminan), pola sama seperti Mikro
- [x] `konsumer-laporan-harian.php`, `konsumer-laporan-bulanan.php` — rekap agregat per periode + cetak
- [x] `konsumer-target-realisasi.php`, `konsumer-performa-ao.php` — tabel `target_konsumer` per petugas/bulan + ranking performa
- [x] `konsumer-angsuran.php` — tabel baru `angsuran_konsumer`, generate jadwal otomatis dari plafon/tenor, tandai lunas, auto-flag terlambat
- [x] `konsumer-jatuh-tempo.php` — kolom `tanggal_jatuh_tempo` di `nasabah_konsumer`, dikelompokkan terlambat/H-7/30 hari
- [x] `konsumer-monitoring.php` — antrean pending + funnel status + beban per petugas dari data status yang ada saat ini

Catatan: kelima fitur di atas butuh migrasi `sql/005_konsumer_tahap2_lanjutan.sql` (kolom plafon/tenor/jatuh tempo/dokumen di `nasabah_konsumer`, plus tabel `angsuran_konsumer` & `target_konsumer`). Semua halaman mendeteksi sendiri kalau migrasi belum dijalankan dan menampilkan pesan alih-alih error. `konsumer-monitoring.php` sengaja belum memakai pipeline multi-tahap (Prospek/Verifikasi/dst) karena itu perubahan skema besar di alur approval yang berisiko regresi — masih memakai status pending/disetujui/ditolak yang sudah ada.

## Tahap 3 — Dashboard Mikro
- [x] `mikro-pengaturan.php` — preferensi notifikasi (email/WA/jatuh tempo)
- [x] `mikro-survey.php` — Survey usaha & penilaian kelayakan (form + skor); pakai kolom `survey_*` dari `sql/006_mikro_survey_angsuran.sql`
- [x] `mikro-monitoring.php` — jadwal & status angsuran (belum bayar/lunas/terlambat) dari tabel `angsuran_mikro`, generate jadwal otomatis dari plafon/tenor
- [x] `mikro-analisa.php` — analisa rasio plafon/omzet per sektor, tren 6 bulan, skor kelayakan (top 5 layak & top 5 berisiko)
- [x] `mikro-laporan.php` — rekap bulanan (KPI, per AO, per sektor) + cetak, filter bulan/tahun
- [ ] `mikro-ai.php` / `mikro-ai-insight.php` — asisten berbasis aturan (bukan LLM), sengaja belum diperluas sesuai nama proyek ("tanpa fitur AI")

Catatan (diverifikasi ulang): keempat halaman di atas ternyata sudah diimplementasikan penuh di kode (bukan lagi placeholder) — daftar ini sebelumnya tidak sinkron dengan kode. Sudah diverifikasi: lolos `php -l` untuk seluruh proyek, jumlah tipe di setiap `mysqli_stmt_bind_param` cocok dengan jumlah parameter di seluruh proyek (tidak hanya Mikro), semua link sidebar Mikro mengarah ke file yang benar-benar ada, dan tidak ada teks placeholder ("sedang dikembangkan"/TODO) di keempatnya. Keempatnya juga sudah menangani kondisi migrasi belum dijalankan (cek `SHOW COLUMNS`/`SHOW TABLES` dulu, tampilkan pesan alih-alih error).

## Tahap 4 — Dashboard Pawning
Catatan penting: menu sidebar Pawning sudah mereferensikan 12 halaman yang **filenya belum ada sama sekali** (bukan placeholder, tapi 404 kalau diklik): `pawning-data.php`, `pawning-perpanjangan.php`, `pawning-pelunasan.php`, `pawning-penebusan.php`, `pawning-lelang.php`, `pawning-barang.php`, `pawning-foto.php`, `pawning-taksiran.php`, `pawning-jatuh-tempo.php`, `pawning-outstanding.php`, `pawning-ujrah.php`, `pawning-laporan.php`, `pawning-export.php`, `pawning-notifikasi.php`, `pawning-profil.php`, `pawning-pengaturan.php`. Hanya `pawning-dashboard.php` dan `pawning-tambah.php` yang sudah jalan.
- [ ] Input nilai taksiran + perhitungan pinjaman otomatis (rumus taksiran → plafon)
- [ ] Upload foto barang gadai (validasi tipe & ukuran file)
- [ ] Jadwal jatuh tempo + notifikasi H-3
- [ ] Sisanya (perpanjangan/pelunasan/penebusan/lelang/outstanding/ujrah/laporan) perlu desain skema tabel `pawning` yang lebih detail dulu sebelum dikerjakan

## Tahap 5 — Dashboard Atasan
- [ ] Approval pengajuan/kredit/limit (workflow status + audit log)
- [ ] Laporan harian/mingguan/bulanan/tahunan + export
- [ ] Manajemen User & Role Permission (CRUD `users`, ganti role)

## Tahap 6 — Polish UI/UX & Keamanan Lanjutan
- [ ] Dark mode (Tailwind `dark:` variant + toggle tersimpan di localStorage)
- [ ] Skeleton loading di semua tabel data
- [ ] Validasi upload file (mime-type whitelist, max size, rename acak agar tidak bisa ditebak)
- [ ] Rate limiting berbasis IP di level web server/reverse proxy
- [ ] Audit log terpusat (tabel `audit_log`: siapa, aksi apa, kapan, IP)

Catatan: setiap tahap akan dikerjakan sebagai perubahan terpisah agar mudah ditinjau dan di-rollback jika perlu, serta tidak menghapus fitur yang sudah berjalan.
