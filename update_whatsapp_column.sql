-- =============================================
-- Script SQL untuk Menambah Kolom WhatsApp
-- Tanggal: 03 Oktober 2026
-- =============================================

-- 1. Tambah kolom whatsapp ke tabel ms_karyawan
ALTER TABLE `ms_karyawan` 
ADD COLUMN `whatsapp` VARCHAR(20) DEFAULT '' AFTER `no_telp`;

-- 2. Update struktur tabel (opsional untuk dokumentasi)
ALTER TABLE `ms_karyawan` 
COMMENT = 'Tabel master karyawan - Updated 2026-10-03: Added whatsapp column';

-- 3. Cek apakah kolom berhasil ditambahkan
-- (Jalankan query ini untuk verify)
-- SHOW COLUMNS FROM `ms_karyawan` LIKE '%telp%';

-- =============================================
-- INFORMASI STRUKTUR KOLOM:
-- =============================================
-- Kolom no_telp: VARCHAR(20) DEFAULT '' (sudah ada)
-- Kolom whatsapp: VARCHAR(20) DEFAULT '' (baru ditambah)
-- 
-- Posisi kolom:
-- ... no_bpjs, no_telp, whatsapp, alamat_ktp ...
-- =============================================