-- Script untuk menambah kolom no_telp dan whatsapp ke tabel ms_karyawan
-- Jalankan di phpMyAdmin atau MySQL command line

ALTER TABLE `ms_karyawan` 
ADD COLUMN `no_telp` VARCHAR(20) DEFAULT '' AFTER `no_bpjs`,
ADD COLUMN `whatsapp` VARCHAR(20) DEFAULT '' AFTER `no_telp`;

-- Update comment untuk dokumentasi
ALTER TABLE `ms_karyawan` 
COMMENT = 'Tabel master karyawan dengan kolom telp dan whatsapp (updated 2026-10-03)';