-- =====================================================
-- Rekap perubahan DATABASE tanggal 13 Oktober 2026
-- Cara pakai di PC client: jalankan BAGIAN 1 di database aktif.
-- Bagian 2 hanya untuk pembuatan DB kosong baru (opsional).
-- =====================================================

-- ---------- BAGIAN 1: wajib di database aktif ----------
-- 1a. Kolom r_status boleh 'Digantikan', default jadi 'Tidak Hadir'
--     (sebelumnya: ENUM('Hadir','Tidak Hadir') DEFAULT 'Hadir')
ALTER TABLE tb_realisasi_detail
MODIFY r_status ENUM('Hadir','Tidak Hadir','Digantikan') NOT NULL DEFAULT 'Tidak Hadir';

-- 1b. Kolom satuan di rincian borongan (kg/ekor), data lama otomatis 'kg'
--     (cek dulu bila ragu: SHOW COLUMNS FROM tb_realisasi_borongan LIKE 'satuan';)
ALTER TABLE tb_realisasi_borongan
ADD COLUMN satuan VARCHAR(10) NOT NULL DEFAULT 'kg';

-- ---------- BAGIAN 2: DB kosong baru (sudah dikerjakan lokal) ----------
-- 2a. Dibuat database db_hr_new_kosong dari struktur db_hr_new tanpa data:
--     CREATE DATABASE IF NOT EXISTS db_hr_new_kosong CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--     (lalu import file database/db_hr_new_kosong.sql)
-- 2b. Agar bisa login, 4 user disalin (tanpa ini terkunci di halaman login):
--     INSERT INTO db_hr_new_kosong.ms_login (id_perusahaan, username, password, role)
--     SELECT id_perusahaan, username, password, role FROM db_hr_new.ms_login;
--     (admin/123, kepalapabrik/123, owner/123, adminmaster/123)
-- 2c. Master sempat di-seed lalu DIHAPUS lagi sesuai permintaan
--     (department/posisi/shift/OS/golongan/denda/dll kembali kosong;
--     sekarang auto-generate dari Excel saat import).
--     Hanya ms_login yang dipertahankan.

-- ---------- Verifikasi ----------
-- SHOW COLUMNS FROM tb_realisasi_detail LIKE 'r_status';
--   -> enum('Hadir','Tidak Hadir','Digantikan'), Default 'Tidak Hadir'
-- SHOW COLUMNS FROM tb_realisasi_borongan LIKE 'satuan';
--   -> varchar(10), Default 'kg'
