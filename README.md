# 📋 Sistem Payroll & HR Management

Aplikasi web berbasis PHP untuk mengelola data karyawan, absensi, penggajian (payroll), dan produktivitas perusahaan.

## Tech Stack
- **Backend**: PHP 8.x (Native)
- **Database**: MySQL 8.0
- **Frontend**: AdminLTE 3, Bootstrap 4, jQuery, DataTables
- **Server**: Laragon (Apache)

---

## 🔄 Alur Kerja Sistem (Web Flow)

Berikut adalah ringkasan alur penggunaan aplikasi dari awal setup hingga proses penggajian:

### 1. Setup Awal (Master Data)
- **Admin** login ke sistem.
- Menambahkan **Bagian (Departemen)** dan **Sub Bagian**.
- Mendaftarkan **Karyawan**, serta mengatur **Shift Kerja** dan **Upah Harian** masing-masing.
- Mengatur aturan perusahaan seperti **Jadwal Kerja** (Jam masuk/istirahat/pulang), serta nominal **Denda** keterlambatan.
- Melakukan konfigurasi koneksi ke mesin absensi fisik di menu **Setting Device**.

### 2. Pengelolaan Kehadiran (Harian)
- Karyawan melakukan absensi hadir/pulang melalui mesin *fingerprint*.
- Admin melakukan **Tarik Data** untuk mengimpor log dari mesin absensi ke dalam database.
- Admin mengecek **Absensi Karyawan**. Jika ada yang lupa absen atau mesin bermasalah, Admin dapat menambahkannya via *Absen Manual*.
- Jika ada karyawan yang tidak hadir, Admin menginput data **SIA** (Sakit, Ijin, Cuti). Karyawan yang absen tanpa keterangan otomatis dihitung **Alfa**.
- Admin/Bagian produksi mengisi **Rencana Upah (RKK)** untuk merencanakan target kegiatan produktivitas harian.

### 3. Kalkulasi Penggajian (Payroll) & Laporan
- Pada masa cut-off penggajian, Admin masuk ke menu **Payroll**.
- Sistem **otomatis mengkalkulasi upah** secara rinci berdasarkan rentang tanggal:
  - **Pendapatan**: Upah dasar (Shift) × jumlah kehadiran, serta tambahan lembur (jika jam pulang aktual > jam pulang jadwal).
  - **Potongan/Denda**: Denda telat masuk dan denda jam istirahat berlebih (dihitung otomatis dari selisih menit absensi).
- Admin mencatat **Realisasi Upah** sebagai validasi.
- Terakhir, Admin mencetak **Slip Gaji** per karyawan (berformat PDF).
- Seluruh rekap kehadiran, kalkulasi payroll, dan RKK dapat diekspor (Export to **Excel/PDF**) untuk laporan kepada *Owner*.

---

## 🧩 Fitur Aplikasi

### 1. Dashboard
- Menampilkan jumlah total karyawan terdaftar

### 2. Master Data
| Fitur | Keterangan |
|-------|-----------|
| **Karyawan** | CRUD data karyawan, setting shift & upah per karyawan |
| **Bagian (Departemen)** | CRUD data departemen/bagian |
| **Sub Bagian** | CRUD sub-departemen di bawah bagian |
| **Jasa** | CRUD data jasa |
| **User** | CRUD akun login, ubah password |

### 3. SIA (Sistem Informasi Absensi)
| Fitur | Keterangan |
|-------|-----------|
| **Data Karyawan SIA** | Lihat data kehadiran per karyawan (sakit, ijin, alfa, cuti) |
| **Sakit** | Catat & kelola data karyawan sakit |
| **Ijin** | Catat & kelola data karyawan ijin |
| **Cuti** | Catat & kelola data karyawan cuti |
| **Alfa** | Catat & kelola data karyawan alfa (tanpa keterangan) |

### 4. Jadwal Kerja
- CRUD jadwal shift kerja (jam masuk, jam pulang, jam istirahat)

### 5. Absensi Karyawan
- Lihat rekap absensi berdasarkan **rentang tanggal** dan **departemen**
- Menampilkan jam masuk, jam istirahat, jam pulang
- Export data ke **Excel**
- Tambah absen manual

### 6. Payroll (Penggajian)
- Hitung gaji berdasarkan **absensi**, **shift**, dan **departemen**
- Hitung otomatis: keterlambatan, lembur, pulang lebih awal
- Hitung **denda** (terlambat & istirahat lebih)
- Hitung **upah dibayar** (upah harian - potongan)
- Export ke **Excel** dan **PDF**

### 7. Tarik Data
- Import/generate data absensi dari mesin fingerprint ke database

### 8. Denda
- Setting nominal denda keterlambatan masuk dan denda istirahat

### 9. Productivity
| Fitur | Keterangan |
|-------|-----------|
| **Rencana Upah (RKK)** | Buat rencana kerja & anggaran upah, kelola detail, history, approval |
| **Realisasi Upah** | Catat realisasi upah, kelola detail, approval |
| **Cetak Slip** | Cetak slip gaji per karyawan |

### 10. Setting Device (Mesin Absen)
- Konfigurasi koneksi ke mesin absensi fingerprint

---

## 📄 Export & Laporan
- **Excel**: Absensi, Payroll, Realisasi Upah, RKK
- **PDF**: Payroll, Slip Gaji

---

## 🔐 Akun Login

| Username   | Password | Level       |
|------------|----------|-------------|
| admin      | 123      | superadmin  |
| mahmudin   | 123      | superadmin  |
| owner      | 123      | OWNER       |

---

## ⚙️ Instalasi Lokal (Laragon)

1. Pastikan **Laragon** sudah running (Apache + MySQL)
2. Clone/copy folder ini ke `C:\laragon\www\payrol`
3. Buat database & import SQL:
   ```
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS db_hr;"
   cmd /c "mysql -u root db_hr < database\db_hr.sql"
   ```
4. Buka `http://localhost/payrol/login.php`
5. Login dengan salah satu akun di atas

---

## 🐛 Bug Fix Log

### 4 Maret 2026
1. **Migrasi Shift & Departemen (Bagian) ke Rencana Upah (RKK)** 
   - Konsep database diubah: Pengelolaan **Bagian (Departemen)**, **Sub Bagian**, dan **Shift** tidak lagi terikat statis pada data pegawai (`ms_karyawan`), melainkan dicatat murni pada setiap periode Rencana Upah (`tb_rkk_detail`).
   - Menambahkan kolom `id_departmen` dan `id_sub_department` pada tabel `tb_rkk_detail` untuk rekam jejak historis yang akurat.
   - Form input dan tabel list pada seluruh **Modul Data Karyawan** (`karyawan.php`, `tambah.php`, `ubah.php`, `upah.php`, `view.php`, dan `shift.php`) telah dihapus secara menyeluruh dari atribut Shift & Bagian.
   - Tabel list pada **Modul SIAC Employee** telah dibersihkan dari kolom Shift & Bagian.
   - Tabel list list pada **Modul RKK Utama** (`page/rkk/rkk.php`) saat ini menampilkan rangkuman **Bagian** dan **Shift** secara otomatis.

### 3 Maret 2026
1. **Error `Unknown database 'db_hr'`** — Database belum dibuat. Solusi: CREATE DATABASE + import SQL.
2. **Error `Incorrect DATE value: ''`** di Absensi & Payroll — Default tanggal kosong diubah ke `date('Y-m-d')`.
   - File: `page/absen/absen.php`, `page/payroll/payroll.php`
3. **Error `Incorrect integer value: ''` di Tarik Data** — Data kosong dari mesin fingerprint di-skip sebelum insert.
   - File: `page/generate/tarik-data.php`
4. **Error `Incorrect DATE value: ''` di PDF & Excel export** — Sama seperti #2, default tanggal kosong diubah ke `date('Y-m-d')`.
   - File: `pdf.php`, `excel.php`, `page/payroll - Copy/payroll.php`

---

## 📝 Status Kebutuhan Fitur & Pengembangan (Roadmap)

Sistem saat ini telah melalui **Modernisasi UI (AdminLTE 3 + Tailwind CSS)**. Berikut adalah rincian fungsionalitas dan status dari pengembangan *logic backend* berdasarkan permintaan (Request) terbaru:

### 1. Master Data
- [x] **Karyawan:** (`page/karyawan/tambah.php`, `page/karyawan/ubah.php`)
  - [x] Form No. Absen hitung otomatis dari karyawan terakhir (tidak ketik manual).
  - [x] Sub Bagian dihapus dari form.
  - [x] OS/DHK diubah menjadi *dropdown* yang mengambil data dari tabel master (tidak ketik manual).
  - [x] Form Shift dihilangkan dari input.
- [ ] **Sub Bagian:** (`index.php`, `page/subbagian/`)
  - [ ] Menu & Master Sub Bagian dihilangkan.
- [ ] **Jasa:** (`page/jasa/jasa.php`)
  - [ ] *Catatan:* Perlu dirundingkan fungsinya untuk *ngelink* ke modul mana (saat ini modul berdiri sendiri).
- [ ] **Jadwal Kerja:** (`page/jadwal/tambah.php`, `page/jadwal/ubah.php`)
  - [ ] Urutan input dibalik: "Istirahat Keluar" terlebih dahulu, baru "Istirahat Masuk".

### 2. Transaksi & Kehadiran (SIA)
- [ ] **Otomatisasi Karyawan (Sakit, Ijin, Alfa):** (`page/sakit/tambah.php`, `page/ijin/tambah.php`, `page/alfa/tambah.php`)
  - [ ] Input Nama Karyawan menggunakan relasi data (*dropdown/autocomplete*), bukan ketik manual.
  - [ ] No. Absen terisi otomatis saat nama karyawan dipilih.
- [ ] **Absensi Karyawan:** (`page/absen/absen.php`, `page/absen/tambah.php`, `page/absen/ubah.php`)
  - [ ] *Fungsi Halaman:* Halaman ini adalah **Log Viewer** rekap kehadiran harian karyawan (Jam Masuk, Pulang, Istirahat) hasil tarikan mesin *fingerprint*. Data ini menjadi dasar perhitungan Payroll. Nantinya halaman ini tidak dikunci (bebas edit manual untuk bukti kosong/override absensi).

### 3. Payroll & Master Denda
- [ ] **Data Payroll:** (`page/payroll/payroll.php`, `pdf.php`, `excel.php`)
  - [ ] Hilangkan filter "Shift" agar semua data muncul masal.
  - [ ] Perbaikan *format* angka pada Export (misal: 100000 menjadi 100.000) dan penambahan **Total Upah** di baris paling bawah.
  - [ ] Perbaikan layout/kerapian pada export Excel & PDF. 
- [ ] **Master Denda:** (`page/denda/denda.php`, `page/denda/tambah.php`, `page/denda/ubah.php`)
  - [ ] Denda keterlambatan/istirahat diotomatisasi untuk *nge-link* ke Rencana Upah/Payroll tanpa *input* manual.

### 4. Productivity (Rencana & Realisasi)
- [ ] **Rencana Upah (RKK):** (`page/rkk/rkk.php`, `page/rkk/tambah.php`, `excelrkk.php`)
  - [ ] Potongan telat & istirahat otomatis dikalkulasikan dari Master Denda vs Log Absensi. Hanya Potongan Lainnya yang manual.
  - [ ] Penambahan tabel Excel export: Kolom Jabatan, Total Upah, dan Jam Kerja.
- [ ] **Realisasi Upah:** (`page/realisasi/realisasi.php`, `excelrealisasi.php`)
  - [ ] Akses **Un-approve** jika data yang disetujui Owner butuh direvisi.
  - [ ] Perbaikan urutan dan presisi kolom export Excel (beserta Total di bawah).
  - [ ] Indikator Warna Data:
    - 🔴 **Merah:** Telat masuk / telat selesai istirahat.
    - 🟠 **Kuning/Orange:** Pulang cepat.
    - ⚫️ **Warna Lain:** Tidak absen sama sekali di salah satu pilar absen.
  - [ ] *Field* "Keterangan / Hasil Kerja" dibiarkan opsional (tidak kaku/terkunci).
  - [ ] Jam Masuk, Pulang, & Istirahat terisi otomatis di tampilan awal berdasarkan tarikan harian tanpa perlu masuk ke detail persetujuan.
- [ ] **Kwitansi / Slip Gaji:** (`slip.php`)
  - [ ] Perbaikan error array offset `slip.php` line 77.
  - [ ] Pembaruan format rancangan slip.

### 5. General Cases & UX Fixes
- [ ] **Prevent Reset Form / Reload UX:** Semua form aksi (`page/[modul]/tambah.php` & `page/[modul]/ubah.php`)
  - [ ] Saat *submit* data di Pagination 3, pastikan tidak `window.location.href="?page=..."` yang me-reset ke halaman 1 secara kaku.
- [ ] **Manajemen Penempatan & Pergeseran Aktivitas:** (Berdampak pada arsitektur `page/rkk/` dan `page/realisasi/`)
  - [ ] Mekanisme menangani karyawan yang berpindah *Departemen/Area* secara harian (dinamis).
  - [ ] Solusi jika karyawan diganti staf lain saat jam kerja aktif/sedang berjalan.
- [ ] **Database Cleanup:** (Akses Backend SQL / `database/db_hr.sql`)
  - [ ] Menghapus dan membersihkan tabel terbengkalai dengan hati-hati **tanpa merusak histori absensi** dan profil pegawai sejauh ini.
