# Dashboard Gadai Emas - BSI

## 📋 Daftar File & Halaman

Paket ini berisi **16 halaman lengkap** untuk sistem manajemen gadai emas BSI:

### 1. **HALAMAN UTAMA**
- `pawning-dashboard.php` ✓ (sudah ada, diupdate)

### 2. **NASABAH** 👥
- `pawning-data.php` - Kelola data nasabah gadai (dengan pencarian, filter, pagination)

### 3. **GADAI EMAS** 💍
- `pawning-tambah.php` ✓ - Input gadai baru (sudah ada)
- `pawning-perpanjangan.php` - Perpanjang masa gadai
- `pawning-pelunasan.php` - Proses pelunasan gadai
- `pawning-penebusan.php` - Tebus gadai sebelum jatuh tempo
- `pawning-lelang.php` - Kelola lelang barang gadai

### 4. **DATA BARANG** 📦
- `pawning-barang.php` - Kelola data barang gadai
- `pawning-foto.php` - Upload & kelola foto barang
- `pawning-taksiran.php` - Kelola nilai taksiran barang

### 5. **MONITORING** 📊
- `pawning-jatuh-tempo.php` - Monitor jatuh tempo gadai
- `pawning-outstanding.php` - Analisis outstanding gadai
- `pawning-ujrah.php` - Monitor pendapatan ujrah

### 6. **LAPORAN** 📄
- `pawning-laporan.php` - Buat laporan gadai berkala
- `pawning-export.php` - Export ke Excel/PDF

### 7. **PENGATURAN** ⚙️
- `pawning-notifikasi.php` - Kelola notifikasi sistem
- `pawning-profil.php` - Edit profil pengguna
- `pawning-pengaturan.php` - Konfigurasi sistem

### 8. **KOMPONEN BERSAMA** 🔧
- `pawning-sidebar.php` - Sidebar reusable untuk semua halaman

---

## 🚀 Cara Instalasi

1. **Backup file lama**
   ```bash
   cd admin/pawning/
   cp -r . ../../backup-gadai-$(date +%Y%m%d)/
   ```

2. **Copy semua file baru**
   ```bash
   cp pawning-*.php admin/pawning/
   ```

3. **Pastikan database sudah ada tabel `pawning`**
   ```sql
   CREATE TABLE IF NOT EXISTS pawning (
       id INT PRIMARY KEY AUTO_INCREMENT,
       nama_pemilik VARCHAR(255) NOT NULL,
       barang_jaminan VARCHAR(255) NOT NULL,
       taksiran_harga DECIMAL(15,2),
       nilai_pinjaman DECIMAL(15,2),
       status ENUM('Aktif', 'Lunas', 'Lelang') DEFAULT 'Aktif',
       no_telepon VARCHAR(20),
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       user_id INT
   );
   ```

4. **Akses melalui sidebar**
   - Semua halaman sudah terintegrasi dengan sidebar yang ada
   - Navigasi otomatis highlight halaman aktif

---

## ✨ Fitur Utama

### Data Nasabah
- ✓ Daftar lengkap semua nasabah
- ✓ Pencarian berdasarkan nama, telepon, atau barang
- ✓ Filter berdasarkan status (Aktif/Lunas/Lelang)
- ✓ Pagination (15 data per halaman)
- ✓ Import dari CSV
- ✓ Statistik quick view
- ✓ Hapus data (dengan konfirmasi)

### Halaman Pengembangan (Template Ready)
Halaman-halaman berikut sudah siap di-develop:
- Perpanjangan: Workflow perpanjang gadai otomatis
- Pelunasan: Proses pelunasan dengan perhitungan ujrah
- Penebusan: Tebus gadai dengan diskon/bonus
- Lelang: Manajemen lelang barang jatuh tempo
- Data Barang: Master barang dengan kategori & deskripsi
- Foto Barang: Upload & gallery foto barang
- Nilai Taksiran: History perubahan nilai taksiran
- Jatuh Tempo: Alert & monitoring jatuh tempo
- Outstanding: Dashboard analisis outstanding
- Ujrah: Perhitungan & laporan pendapatan ujrah
- Laporan: Generate laporan PDF/Excel
- Export: Bulk export data
- Notifikasi: Setting reminder & alert
- Profil: Management profil user
- Pengaturan: Konfigurasi tarif & aturan bisnis

---

## 🎨 Tema & Styling

- Menggunakan **Tailwind CSS v4** dengan browser runtime
- Font: **Plus Jakarta Sans** (Google Fonts)
- Responsive design untuk mobile & desktop
- Consistent card design & color scheme
- Dark mode ready dengan CSS variables

---

## 🔐 Keamanan

- ✓ Session-based authentication
- ✓ CSRF token protection
- ✓ Role-based access control (admin, gadai, pawning, atasan)
- ✓ Input sanitization dengan htmlspecialchars()
- ✓ Prepared statements untuk SQL injection prevention

---

## 📱 Responsif

Semua halaman:
- ✓ Mobile-first design
- ✓ Sidebar auto-collapse di mobile
- ✓ Touch-friendly buttons
- ✓ Optimized table scroll

---

## 📊 Development Priorities

Rekomendasi urutan pengembangan fitur:
1. **Priority HIGH**: Data Barang, Jatuh Tempo, Ujrah
2. **Priority MEDIUM**: Perpanjangan, Pelunasan, Laporan
3. **Priority LOW**: Foto, Taksiran, Outstanding, Lelang

---

## 💡 Tips Implementasi

1. **Database Query** - Update query sesuai struktur tabel Anda
2. **Chart.js** - Gunakan untuk visualisasi outstanding & ujrah
3. **DataTables** - Pertimbangkan untuk tabel besar
4. **Modal Dialogs** - Gunakan untuk konfirmasi action
5. **Form Validation** - Add JavaScript validation untuk UX lebih baik

---

## 📞 Support

Jika ada pertanyaan atau butuh modifikasi:
- Cek struktur database di `config/database.php`
- Review access control di setiap halaman
- Test dengan berbagai user roles

---

**Terakhir diupdate:** August 10, 2026  
**Version:** 1.0
