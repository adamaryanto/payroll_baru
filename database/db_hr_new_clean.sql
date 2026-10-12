-- DB HR NEW - clean schema (by code, kompatibel MySQL 5.7 & 8.0)
-- Sumber: db_hr_NEW.sql, tanpa data, collation diseragamkan ke utf8mb4_unicode_ci
-- Cara pakai: buat database dulu lalu run file ini, atau run langsung (akan create database db_hr)

CREATE DATABASE IF NOT EXISTS `db_hr_new` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_hr_new`;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE IF NOT EXISTS `app_user` (
  `id_login` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `nama_user` varchar(100) NOT NULL,
  `tgl` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_departmen` (
  `id_departmen` int(11) NOT NULL,
  `id_perusahaan` int(11) NOT NULL,
  `nama_departmen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_golongan` (
  `id_golongan` int(11) NOT NULL,
  `golongan` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_jabatan` (
  `id_jabatan` int(11) NOT NULL,
  `id_perusahaan` int(11) NOT NULL,
  `jabatan` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_karyawan` (
  `id_karyawan` int(11) NOT NULL,
  `id_departmen` int(11) NOT NULL,
  `id_jabatan` int(11) NOT NULL,
  `no_absen` int(11) NOT NULL,
  `nama_karyawan` varchar(255) NOT NULL,
  `tempat_lahir` varchar(255) NOT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `agama` varchar(50) NOT NULL,
  `status_kawin` enum('Kawin','Belum Kawin','Janda','Duda') DEFAULT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `no_ktp` varchar(20) NOT NULL,
  `alamat_ktp` varchar(255) NOT NULL,
  `alamat_tinggal` varchar(255) NOT NULL,
  `status_karyawan` enum('Aktif','Non Aktif') DEFAULT NULL,
  `tgl_aktif` date DEFAULT NULL,
  `tgl_nonaktif` date DEFAULT NULL,
  `no_bpjs` varchar(20) NOT NULL,
  `id_jadwal` int(11) NOT NULL,
  `id_sub_department` int(11) NOT NULL,
  `id_os_dhk` int(11) DEFAULT 0,
  `id_golongan` int(11) DEFAULT 0,
  `upah` double DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_login` (
  `id_login` int(11) NOT NULL,
  `id_perusahaan` int(11) NOT NULL,
  `username` varchar(225) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Kepala Pabrik','Admin HR','Admin Master','Owner') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_os_dhk` (
  `id_os_dhk` int(11) NOT NULL,
  `OS_DHK` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_perusahaan` (
  `id_perusahaan` int(11) NOT NULL,
  `nib` varchar(50) NOT NULL,
  `nama_perusahaan` varchar(255) NOT NULL,
  `alamat_perusahaan` varchar(255) NOT NULL,
  `telepon_perusahaan` varchar(255) NOT NULL,
  `email_perusahaan` varchar(255) NOT NULL,
  `direktur` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_sub_department` (
  `id_sub_department` int(11) NOT NULL,
  `id_perusahaan` int(11) NOT NULL,
  `nama_sub_department` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_alfa` (
  `id_alfa` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `tgl_pengajuan_alfa` date NOT NULL,
  `tgl_awal_alfa` date NOT NULL,
  `tgl_akhir_alfa` date NOT NULL,
  `lama` int(11) NOT NULL,
  `keterangan_alfa` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_biayamobil` (
  `id_biayamobil` int(11) NOT NULL,
  `biaya_mobil` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_boneless` (
  `id_boneless` int(11) NOT NULL,
  `id_rkk` int(11) DEFAULT 0,
  `tgl` date NOT NULL,
  `jumlah_mobil` int(11) NOT NULL,
  `id_biayamobil` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tgl_updt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_boneless_detail` (
  `id_boneless_detail` int(11) NOT NULL,
  `id_boneless` int(11) NOT NULL,
  `nama_item` varchar(255) NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `harga` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `jenis` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_cuti` (
  `id_cuti` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `tgl_pengajuan_cuti` date NOT NULL,
  `tgl_awal_cuti` date NOT NULL,
  `tgl_akhir_cuti` date NOT NULL,
  `lama` int(11) NOT NULL,
  `keterangan_cuti` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_denda` (
  `id_denda` int(11) NOT NULL,
  `denda_masuk` int(11) NOT NULL,
  `denda_istirahat_keluar` int(11) NOT NULL,
  `denda_istirahat_masuk` int(11) NOT NULL,
  `denda_pulang` int(11) NOT NULL,
  `denda_tidak_lengkap` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_hasil_external` (
  `id_external` int(11) NOT NULL,
  `id_jasa` int(11) NOT NULL,
  `tgl` date NOT NULL,
  `hasil` int(11) NOT NULL,
  `biaya` int(11) NOT NULL,
  `total_biaya` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_hasil_produksi` (
  `id_hasil_produksi` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `tgl` date NOT NULL,
  `target` int(11) NOT NULL,
  `hasil` int(11) NOT NULL,
  `upah` int(11) NOT NULL,
  `potongan` int(11) NOT NULL,
  `lembur` int(11) NOT NULL,
  `id_jadwal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_ijin` (
  `id_ijin` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `tgl_pengajuan_ijin` date NOT NULL,
  `tgl_ijin` date NOT NULL,
  `waktu_awal` varchar(255) NOT NULL,
  `waktu_akhir` varchar(255) NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jenis_ijin` enum('Datang Terlambat','Pulang Lebih Awal','Meninggalkan Tempat Kerja','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_jadwal` (
  `id_jadwal` int(11) NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jam_masuk` varchar(20) NOT NULL,
  `jam_keluar` varchar(20) NOT NULL,
  `istirahat_masuk` varchar(20) NOT NULL,
  `istirahat_keluar` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_mesin` (
  `id_mesin` int(11) NOT NULL,
  `no_mesin` int(11) NOT NULL,
  `nama_mesin` varchar(50) NOT NULL,
  `ip_mesin` varchar(20) NOT NULL,
  `comm_key` varchar(20) NOT NULL,
  `port` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_realisasi` (
  `id_realisasi` int(11) NOT NULL,
  `id_rkk` int(11) NOT NULL,
  `keterangan` text NOT NULL,
  `tgl_realisasi` date NOT NULL,
  `detail_realisasi` varchar(20) NOT NULL,
  `jam_kerja` int(11) NOT NULL,
  `status_realisasi` int(11) NOT NULL,
  `tgl_status` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_realisasi_detail` (
  `id_realisasi_detail` int(11) NOT NULL,
  `id_realisasi` int(11) NOT NULL DEFAULT 0,
  `id_rkk_detail` int(11) NOT NULL DEFAULT 0,
  `id_rkk` int(11) NOT NULL DEFAULT 0,
  `id_karyawan` int(11) NOT NULL DEFAULT 0,
  `nama_karyawan_manual` varchar(255) DEFAULT NULL,
  `id_departmen` int(11) NOT NULL DEFAULT 0,
  `id_sub_department` int(11) NOT NULL DEFAULT 0,
  `id_os_dhk` int(11) DEFAULT 0,
  `id_golongan` int(11) DEFAULT 0,
  `r_upah` int(11) NOT NULL DEFAULT 0,
  `r_potongan_telat` int(11) NOT NULL DEFAULT 0,
  `r_potongan_istirahat_awal` int(11) NOT NULL DEFAULT 0,
  `r_potongan_istirahat_telat` int(11) NOT NULL DEFAULT 0,
  `r_potongan_lainnya` int(11) NOT NULL DEFAULT 0,
  `r_jam_masuk` varchar(20) NOT NULL DEFAULT '',
  `r_jam_keluar` varchar(20) NOT NULL DEFAULT '',
  `r_istirahat_masuk` varchar(20) NOT NULL DEFAULT '',
  `r_istirahat_keluar` varchar(20) NOT NULL DEFAULT '',
  `r_status` enum('Hadir','Tidak Hadir') NOT NULL DEFAULT 'Hadir',
  `r_update` varchar(20) NOT NULL,
  `ra_masuk` varchar(20) NOT NULL DEFAULT '00:00:00',
  `ra_keluar` varchar(20) NOT NULL DEFAULT '00:00:00',
  `ra_istirahat_masuk` varchar(20) NOT NULL DEFAULT '00:00:00',
  `ra_istirahat_keluar` varchar(20) NOT NULL DEFAULT '00:00:00',
  `id_jadwal` int(11) NOT NULL DEFAULT 0,
  `status_realisasi_detail` int(11) NOT NULL DEFAULT 0,
  `hasil_kerja` varchar(255) NOT NULL DEFAULT '',
  `lembur` int(11) NOT NULL DEFAULT 0,
  `tgl_realisasi_detail` date NOT NULL,
  `r_potongan_pulang` int(11) NOT NULL DEFAULT 0,
  `r_potongan_tidak_lengkap` int(11) NOT NULL DEFAULT 0,
  `tgl_updt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_record` (
  `id_record` int(11) NOT NULL,
  `data` varchar(100) NOT NULL,
  `userid` int(11) NOT NULL,
  `tgl` date NOT NULL,
  `verifikasi` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `detail_waktu` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_rkk` (
  `id_rkk` int(11) NOT NULL,
  `keterangan` text NOT NULL,
  `tgl_rkk` date NOT NULL,
  `detail_rkk` varchar(20) NOT NULL,
  `jam_kerja` int(11) NOT NULL,
  `status_rkk` int(11) NOT NULL,
  `tgl_status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_rkk_detail` (
  `id_rkk_detail` int(11) NOT NULL,
  `id_rkk` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `nama_karyawan_manual` varchar(255) DEFAULT NULL,
  `id_departmen` int(11) DEFAULT NULL,
  `id_sub_department` int(11) DEFAULT NULL,
  `upah` int(11) NOT NULL,
  `status_rkk` enum('Hadir','Tidak Hadir','Digantikan','Pengganti') NOT NULL,
  `id_jadwal` int(11) NOT NULL,
  `potongan_telat` int(11) NOT NULL,
  `potongan_istirahat` int(11) NOT NULL,
  `potongan_lainnya` int(11) NOT NULL,
  `tgl_updt` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_rkk_update` (
  `id_rkk_update` int(11) NOT NULL,
  `id_rkk_detail` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `status` enum('Digantikan','Pengganti','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_sia` (
  `id_sia` int(11) NOT NULL,
  `id_karyawan` int(11) NOT NULL,
  `tgl_pengajuan` date NOT NULL,
  `tgl_awal` date NOT NULL,
  `tgl_akhir` date NOT NULL,
  `lama` int(11) NOT NULL,
  `keterangan` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `age` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_lembur` (
  `id_lembur` int(11) NOT NULL AUTO_INCREMENT,
  `lembur_per_jam` int(11) DEFAULT '0',
  PRIMARY KEY (`id_lembur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_record_lembur` (
  `userid` varchar(50) NOT NULL,
  `tgl` date NOT NULL,
  `lembur_jam` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`userid`, `tgl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primary keys & indexes
ALTER TABLE `app_user` ADD PRIMARY KEY (`id_login`);;
ALTER TABLE `ms_departmen` ADD PRIMARY KEY (`id_departmen`);;
ALTER TABLE `ms_golongan` ADD PRIMARY KEY (`id_golongan`);;
ALTER TABLE `ms_jabatan` ADD PRIMARY KEY (`id_jabatan`);;
ALTER TABLE `ms_karyawan` ADD PRIMARY KEY (`id_karyawan`);;
ALTER TABLE `ms_login` ADD PRIMARY KEY (`id_login`);;
ALTER TABLE `ms_os_dhk` ADD PRIMARY KEY (`id_os_dhk`);;
ALTER TABLE `ms_perusahaan` ADD PRIMARY KEY (`id_perusahaan`);;
ALTER TABLE `ms_sub_department` ADD PRIMARY KEY (`id_sub_department`);;
ALTER TABLE `tb_alfa` ADD PRIMARY KEY (`id_alfa`);;
ALTER TABLE `tb_biayamobil` ADD PRIMARY KEY (`id_biayamobil`);;
ALTER TABLE `tb_boneless` ADD PRIMARY KEY (`id_boneless`), ADD KEY `fk_biayamobil_boneless` (`id_biayamobil`);;
ALTER TABLE `tb_boneless_detail` ADD PRIMARY KEY (`id_boneless_detail`), ADD KEY `id_boneless` (`id_boneless`);;
ALTER TABLE `tb_cuti` ADD PRIMARY KEY (`id_cuti`);;
ALTER TABLE `tb_denda` ADD PRIMARY KEY (`id_denda`);;
ALTER TABLE `tb_hasil_external` ADD PRIMARY KEY (`id_external`);;
ALTER TABLE `tb_hasil_produksi` ADD PRIMARY KEY (`id_hasil_produksi`);;
ALTER TABLE `tb_ijin` ADD PRIMARY KEY (`id_ijin`);;
ALTER TABLE `tb_jadwal` ADD PRIMARY KEY (`id_jadwal`);;
ALTER TABLE `tb_mesin` ADD PRIMARY KEY (`id_mesin`);;
ALTER TABLE `tb_realisasi` ADD PRIMARY KEY (`id_realisasi`);;
ALTER TABLE `tb_realisasi_detail` ADD PRIMARY KEY (`id_realisasi_detail`);;
ALTER TABLE `tb_record` ADD PRIMARY KEY (`id_record`);;
ALTER TABLE `tb_rkk` ADD PRIMARY KEY (`id_rkk`);;
ALTER TABLE `tb_rkk_detail` ADD PRIMARY KEY (`id_rkk_detail`), ADD KEY `fk_rkk_departmen_new` (`id_departmen`), ADD KEY `fk_rkk_sub_department_new` (`id_sub_department`);;
ALTER TABLE `tb_rkk_update` ADD PRIMARY KEY (`id_rkk_update`);;
ALTER TABLE `tb_sia` ADD PRIMARY KEY (`id_sia`);;
ALTER TABLE `users` ADD PRIMARY KEY (`id`);;
ALTER TABLE `tb_boneless` ADD CONSTRAINT `fk_biayamobil_boneless` FOREIGN KEY (`id_biayamobil`) REFERENCES `tb_biayamobil` (`id_biayamobil`);;
ALTER TABLE `tb_boneless_detail` ADD CONSTRAINT `tb_boneless_detail_ibfk_1` FOREIGN KEY (`id_boneless`) REFERENCES `tb_boneless` (`id_boneless`) ON DELETE CASCADE;;
ALTER TABLE `tb_rkk_detail` ADD CONSTRAINT `fk_rkk_departmen` FOREIGN KEY (`id_departmen`) REFERENCES `ms_departmen` (`id_departmen`), ADD CONSTRAINT `fk_rkk_departmen_new` FOREIGN KEY (`id_departmen`) REFERENCES `ms_departmen` (`id_departmen`), ADD CONSTRAINT `fk_rkk_sub_department` FOREIGN KEY (`id_sub_department`) REFERENCES `ms_sub_department` (`id_sub_department`), ADD CONSTRAINT `fk_rkk_sub_department_new` FOREIGN KEY (`id_sub_department`) REFERENCES `ms_sub_department` (`id_sub_department`);;
ALTER TABLE `tb_realisasi_detail` ADD COLUMN `lembur_jam` DECIMAL(10,2) DEFAULT 0.00;;
ALTER TABLE `ms_karyawan` ADD COLUMN `target_mingguan` DECIMAL(10,2) DEFAULT 20.00;;
ALTER TABLE `ms_karyawan` ADD COLUMN `target_bulanan` DECIMAL(10,2) DEFAULT 40.00;;

-- Auto increment
ALTER TABLE `app_user` MODIFY `id_login` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;;
ALTER TABLE `ms_departmen` MODIFY `id_departmen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;;
ALTER TABLE `ms_golongan` MODIFY `id_golongan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;;
ALTER TABLE `ms_jabatan` MODIFY `id_jabatan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;;
ALTER TABLE `ms_karyawan` MODIFY `id_karyawan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=256;;
ALTER TABLE `ms_login` MODIFY `id_login` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;;
ALTER TABLE `ms_os_dhk` MODIFY `id_os_dhk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;;
ALTER TABLE `ms_perusahaan` MODIFY `id_perusahaan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;;
ALTER TABLE `ms_sub_department` MODIFY `id_sub_department` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;;
ALTER TABLE `tb_alfa` MODIFY `id_alfa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;;
ALTER TABLE `tb_biayamobil` MODIFY `id_biayamobil` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;;
ALTER TABLE `tb_boneless` MODIFY `id_boneless` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;;
ALTER TABLE `tb_boneless_detail` MODIFY `id_boneless_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=252;;
ALTER TABLE `tb_cuti` MODIFY `id_cuti` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;;
ALTER TABLE `tb_denda` MODIFY `id_denda` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;;
ALTER TABLE `tb_hasil_external` MODIFY `id_external` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;;
ALTER TABLE `tb_hasil_produksi` MODIFY `id_hasil_produksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;;
ALTER TABLE `tb_ijin` MODIFY `id_ijin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;;
ALTER TABLE `tb_jadwal` MODIFY `id_jadwal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;;
ALTER TABLE `tb_mesin` MODIFY `id_mesin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;;
ALTER TABLE `tb_realisasi` MODIFY `id_realisasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;;
ALTER TABLE `tb_realisasi_detail` MODIFY `id_realisasi_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1966;;
ALTER TABLE `tb_record` MODIFY `id_record` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98413;;
ALTER TABLE `tb_rkk` MODIFY `id_rkk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;;
ALTER TABLE `tb_rkk_detail` MODIFY `id_rkk_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4801;;
ALTER TABLE `tb_rkk_update` MODIFY `id_rkk_update` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;;
ALTER TABLE `tb_sia` MODIFY `id_sia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;;
SET FOREIGN_KEY_CHECKS=1;


