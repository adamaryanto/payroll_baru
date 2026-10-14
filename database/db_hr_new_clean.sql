-- DB HR NEW - full schema LIVE 42 tabel (by code, kompatibel MySQL 5.7 & 8.0)
-- Sumber: mysqldump --no-data db_hr_new 12 Okt 2026, tanpa data
-- Collation diseragamkan ke utf8mb4_unicode_ci (fix #1273 utf8mb4_0900_ai_ci)

CREATE DATABASE IF NOT EXISTS `db_hr_new` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_hr_new`;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `app_user` (
  `id_login` int NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `nama_user` varchar(100) NOT NULL,
  `tgl` varchar(30) NOT NULL,
  PRIMARY KEY (`id_login`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_departmen` (
  `id_departmen` int NOT NULL AUTO_INCREMENT,
  `id_perusahaan` int NOT NULL,
  `nama_departmen` varchar(255) NOT NULL,
  PRIMARY KEY (`id_departmen`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_golongan` (
  `id_golongan` int NOT NULL AUTO_INCREMENT,
  `golongan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_golongan`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_grup_borongan` (
  `id_grup` int NOT NULL AUTO_INCREMENT,
  `nama_grup` varchar(100) NOT NULL,
  `keterangan` text,
  `tgl_updt` datetime DEFAULT NULL,
  `harga` int DEFAULT NULL,
  `is_borongan` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_grup`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_grup_borongan_anggota` (
  `id_anggota` int NOT NULL AUTO_INCREMENT,
  `id_grup` int NOT NULL,
  `id_karyawan` int NOT NULL,
  `tarif` int DEFAULT NULL,
  `is_borongan` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_anggota`),
  UNIQUE KEY `uniq_grup_kar` (`id_grup`,`id_karyawan`),
  KEY `idx_grup` (`id_grup`),
  KEY `idx_karyawan` (`id_karyawan`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_grup_borongan_item` (
  `id_item` int NOT NULL AUTO_INCREMENT,
  `id_grup` int NOT NULL,
  `nama_item` varchar(100) NOT NULL,
  `harga` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_item`),
  KEY `idx_grup` (`id_grup`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_jabatan` (
  `id_jabatan` int NOT NULL AUTO_INCREMENT,
  `id_perusahaan` int NOT NULL,
  `jabatan` varchar(255) NOT NULL,
  PRIMARY KEY (`id_jabatan`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_karyawan` (
  `id_karyawan` int NOT NULL AUTO_INCREMENT,
  `id_departmen` int NOT NULL,
  `id_jabatan` int NOT NULL,
  `no_absen` int NOT NULL,
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
  `no_telp` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT '',
  `id_jadwal` int NOT NULL,
  `id_sub_department` int NOT NULL,
  `id_os_dhk` int DEFAULT '0',
  `id_golongan` int DEFAULT '0',
  `upah` double DEFAULT '0',
  `target_mingguan` decimal(10,2) DEFAULT '20.00',
  `target_bulanan` decimal(10,2) DEFAULT '40.00',
  `lembur_per_jam` int DEFAULT NULL COMMENT 'NULL=pakai global',
  PRIMARY KEY (`id_karyawan`)
) ENGINE=InnoDB AUTO_INCREMENT=656 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_login` (
  `id_login` int NOT NULL AUTO_INCREMENT,
  `id_perusahaan` int NOT NULL,
  `username` varchar(225) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Kepala Pabrik','Admin HR','Admin Master','Owner') DEFAULT NULL,
  PRIMARY KEY (`id_login`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_os_dhk` (
  `id_os_dhk` int NOT NULL AUTO_INCREMENT,
  `OS_DHK` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_os_dhk`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_perusahaan` (
  `id_perusahaan` int NOT NULL AUTO_INCREMENT,
  `nib` varchar(50) NOT NULL,
  `nama_perusahaan` varchar(255) NOT NULL,
  `alamat_perusahaan` varchar(255) NOT NULL,
  `telepon_perusahaan` varchar(255) NOT NULL,
  `email_perusahaan` varchar(255) NOT NULL,
  `direktur` varchar(255) NOT NULL,
  PRIMARY KEY (`id_perusahaan`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms_sub_department` (
  `id_sub_department` int NOT NULL AUTO_INCREMENT,
  `id_perusahaan` int NOT NULL,
  `nama_sub_department` varchar(255) NOT NULL,
  PRIMARY KEY (`id_sub_department`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_alfa` (
  `id_alfa` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` int NOT NULL,
  `tgl_pengajuan_alfa` date NOT NULL,
  `tgl_awal_alfa` date NOT NULL,
  `tgl_akhir_alfa` date NOT NULL,
  `lama` int NOT NULL,
  `keterangan_alfa` varchar(255) NOT NULL,
  PRIMARY KEY (`id_alfa`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_biayamobil` (
  `id_biayamobil` int NOT NULL AUTO_INCREMENT,
  `biaya_mobil` int NOT NULL,
  PRIMARY KEY (`id_biayamobil`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_boneless` (
  `id_boneless` int NOT NULL AUTO_INCREMENT,
  `id_rkk` int DEFAULT '0',
  `tgl` date NOT NULL,
  `jumlah_mobil` int NOT NULL,
  `id_biayamobil` int DEFAULT NULL,
  `keterangan` text,
  `tgl_updt` datetime DEFAULT CURRENT_TIMESTAMP,
  `harga_satuan` int DEFAULT NULL,
  PRIMARY KEY (`id_boneless`),
  KEY `fk_biayamobil_boneless` (`id_biayamobil`),
  CONSTRAINT `fk_biayamobil_boneless` FOREIGN KEY (`id_biayamobil`) REFERENCES `tb_biayamobil` (`id_biayamobil`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_boneless_detail` (
  `id_boneless_detail` int NOT NULL AUTO_INCREMENT,
  `id_boneless` int NOT NULL,
  `nama_item` varchar(255) NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `harga` int NOT NULL,
  `total` int NOT NULL,
  `jenis` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_boneless_detail`),
  KEY `id_boneless` (`id_boneless`),
  CONSTRAINT `tb_boneless_detail_ibfk_1` FOREIGN KEY (`id_boneless`) REFERENCES `tb_boneless` (`id_boneless`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=283 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_boneless_satuan` (
  `id_satuan` int NOT NULL AUTO_INCREMENT,
  `id_boneless` int NOT NULL,
  `nama` varchar(255) NOT NULL,
  `hasil` decimal(12,2) NOT NULL DEFAULT '0.00',
  `upah` int NOT NULL DEFAULT '0',
  `satuan` varchar(10) NOT NULL DEFAULT 'KG',
  `harga` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_satuan`),
  KEY `idx_boneless` (`id_boneless`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_cuti` (
  `id_cuti` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` int NOT NULL,
  `tgl_pengajuan_cuti` date NOT NULL,
  `tgl_awal_cuti` date NOT NULL,
  `tgl_akhir_cuti` date NOT NULL,
  `lama` int NOT NULL,
  `keterangan_cuti` varchar(255) NOT NULL,
  PRIMARY KEY (`id_cuti`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_denda` (
  `id_denda` int NOT NULL AUTO_INCREMENT,
  `denda_masuk` int NOT NULL,
  `denda_istirahat_keluar` int NOT NULL,
  `denda_istirahat_masuk` int NOT NULL,
  `denda_pulang` int NOT NULL,
  `denda_tidak_lengkap` int NOT NULL,
  `mangkir_menit` int NOT NULL DEFAULT '0',
  `mangkir_dari_tgl` date DEFAULT NULL,
  `mangkir_sampai_tgl` date DEFAULT NULL,
  PRIMARY KEY (`id_denda`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_fingerprint` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pin` varchar(50) NOT NULL,
  `finger_id` tinyint NOT NULL DEFAULT '0',
  `template` longtext NOT NULL,
  `size` int NOT NULL DEFAULT '0',
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  `id_mesin` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fp` (`pin`,`finger_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_fingerprint_dummy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pin` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `finger_id` int NOT NULL DEFAULT '0',
  `template_data` text COLLATE utf8mb4_general_ci NOT NULL,
  `size` int NOT NULL DEFAULT '0',
  `valid` tinyint(1) DEFAULT '1',
  `device_id` int DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_finger` (`pin`,`finger_id`,`device_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_ganti` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tgl` date NOT NULL,
  `id_karyawan_absen` int NOT NULL DEFAULT '0',
  `id_karyawan_ganti` int NOT NULL DEFAULT '0',
  `upah_bayar` double NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ganti` (`tgl`,`id_karyawan_absen`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_hasil_external` (
  `id_external` int NOT NULL AUTO_INCREMENT,
  `id_jasa` int NOT NULL,
  `tgl` date NOT NULL,
  `hasil` int NOT NULL,
  `biaya` int NOT NULL,
  `total_biaya` int NOT NULL,
  PRIMARY KEY (`id_external`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_hasil_produksi` (
  `id_hasil_produksi` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` int NOT NULL,
  `tgl` date NOT NULL,
  `target` int NOT NULL,
  `hasil` int NOT NULL,
  `upah` int NOT NULL,
  `potongan` int NOT NULL,
  `lembur` int NOT NULL,
  `id_jadwal` int NOT NULL,
  PRIMARY KEY (`id_hasil_produksi`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_ijin` (
  `id_ijin` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` int NOT NULL,
  `tgl_pengajuan_ijin` date NOT NULL,
  `tgl_ijin` date NOT NULL,
  `waktu_awal` varchar(255) NOT NULL,
  `waktu_akhir` varchar(255) NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `jenis_ijin` enum('Datang Terlambat','Pulang Lebih Awal','Meninggalkan Tempat Kerja','') NOT NULL,
  PRIMARY KEY (`id_ijin`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_jadwal` (
  `id_jadwal` int NOT NULL AUTO_INCREMENT,
  `keterangan` varchar(255) NOT NULL,
  `jam_masuk` varchar(20) NOT NULL,
  `jam_keluar` varchar(20) NOT NULL,
  `istirahat_masuk` varchar(20) NOT NULL,
  `istirahat_keluar` varchar(20) NOT NULL,
  PRIMARY KEY (`id_jadwal`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_lembur` (
  `id_lembur` int NOT NULL AUTO_INCREMENT,
  `lembur_per_jam` int DEFAULT '0',
  PRIMARY KEY (`id_lembur`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_mesin` (
  `id_mesin` int NOT NULL AUTO_INCREMENT,
  `no_mesin` int NOT NULL,
  `nama_mesin` varchar(50) NOT NULL,
  `ip_mesin` varchar(20) NOT NULL,
  `comm_key` varchar(20) NOT NULL,
  `port` varchar(5) NOT NULL,
  `protocol` varchar(10) NOT NULL DEFAULT 'soap',
  PRIMARY KEY (`id_mesin`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_realisasi` (
  `id_realisasi` int NOT NULL AUTO_INCREMENT,
  `id_rkk` int NOT NULL,
  `keterangan` text NOT NULL,
  `tgl_realisasi` date NOT NULL,
  `detail_realisasi` varchar(20) NOT NULL,
  `jam_kerja` int NOT NULL,
  `status_realisasi` int NOT NULL,
  `tgl_status` date NOT NULL,
  PRIMARY KEY (`id_realisasi`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_realisasi_borongan` (
  `id_borongan` int NOT NULL AUTO_INCREMENT,
  `id_realisasi_detail` int NOT NULL,
  `nama_pekerjaan` varchar(100) NOT NULL,
  `qty` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tarif` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_borongan`),
  KEY `idx_detail` (`id_realisasi_detail`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_realisasi_detail` (
  `id_realisasi_detail` int NOT NULL AUTO_INCREMENT,
  `id_realisasi` int NOT NULL DEFAULT '0',
  `id_rkk_detail` int NOT NULL DEFAULT '0',
  `id_rkk` int NOT NULL DEFAULT '0',
  `id_karyawan` int NOT NULL DEFAULT '0',
  `nama_karyawan_manual` varchar(255) DEFAULT NULL,
  `id_departmen` int NOT NULL DEFAULT '0',
  `id_sub_department` int NOT NULL DEFAULT '0',
  `id_os_dhk` int DEFAULT '0',
  `id_golongan` int DEFAULT '0',
  `r_upah` int NOT NULL DEFAULT '0',
  `r_potongan_telat` int NOT NULL DEFAULT '0',
  `r_potongan_istirahat_awal` int NOT NULL DEFAULT '0',
  `r_potongan_istirahat_telat` int NOT NULL DEFAULT '0',
  `r_potongan_lainnya` int NOT NULL DEFAULT '0',
  `r_jam_masuk` varchar(20) NOT NULL DEFAULT '',
  `r_jam_keluar` varchar(20) NOT NULL DEFAULT '',
  `r_istirahat_masuk` varchar(20) NOT NULL DEFAULT '',
  `r_istirahat_keluar` varchar(20) NOT NULL DEFAULT '',
  `r_status` enum('Hadir','Tidak Hadir','Digantikan') NOT NULL DEFAULT 'Tidak Hadir',
  `r_update` varchar(20) NOT NULL,
  `ra_masuk` varchar(20) NOT NULL DEFAULT '00:00:00',
  `ra_keluar` varchar(20) NOT NULL DEFAULT '00:00:00',
  `ra_istirahat_masuk` varchar(20) NOT NULL DEFAULT '00:00:00',
  `ra_istirahat_keluar` varchar(20) NOT NULL DEFAULT '00:00:00',
  `id_jadwal` int NOT NULL DEFAULT '0',
  `status_realisasi_detail` int NOT NULL DEFAULT '0',
  `hasil_kerja` varchar(255) NOT NULL DEFAULT '',
  `lembur` int NOT NULL DEFAULT '0',
  `tgl_realisasi_detail` date NOT NULL,
  `r_potongan_pulang` int NOT NULL DEFAULT '0',
  `r_potongan_tidak_lengkap` int NOT NULL DEFAULT '0',
  `tgl_updt` datetime DEFAULT CURRENT_TIMESTAMP,
  `lembur_jam` decimal(10,2) DEFAULT '0.00',
  `id_grup` int DEFAULT NULL,
  `id_pengganti` int DEFAULT NULL,
  PRIMARY KEY (`id_realisasi_detail`),
  KEY `idx_grup` (`id_grup`),
  CONSTRAINT `fk_realisasi_grup` FOREIGN KEY (`id_grup`) REFERENCES `ms_grup_borongan` (`id_grup`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2934 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_record` (
  `id_record` int NOT NULL AUTO_INCREMENT,
  `data` varchar(100) NOT NULL,
  `userid` int NOT NULL,
  `tgl` date NOT NULL,
  `verifikasi` int NOT NULL,
  `status` int NOT NULL,
  `detail_waktu` varchar(100) NOT NULL,
  `source_device_id` int DEFAULT NULL,
  PRIMARY KEY (`id_record`)
) ENGINE=InnoDB AUTO_INCREMENT=100700 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_record_lembur` (
  `userid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tgl` date NOT NULL,
  `lembur_jam` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`userid`,`tgl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_rkk` (
  `id_rkk` int NOT NULL AUTO_INCREMENT,
  `keterangan` text NOT NULL,
  `tgl_rkk` date NOT NULL,
  `detail_rkk` varchar(20) NOT NULL,
  `jam_kerja` int NOT NULL,
  `status_rkk` int NOT NULL,
  `tgl_status` varchar(20) NOT NULL,
  PRIMARY KEY (`id_rkk`)
) ENGINE=InnoDB AUTO_INCREMENT=126 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_rkk_detail` (
  `id_rkk_detail` int NOT NULL AUTO_INCREMENT,
  `id_rkk` int NOT NULL,
  `id_karyawan` int NOT NULL,
  `nama_karyawan_manual` varchar(255) DEFAULT NULL,
  `id_departmen` int DEFAULT NULL,
  `id_sub_department` int DEFAULT NULL,
  `upah` int NOT NULL,
  `status_rkk` enum('Hadir','Tidak Hadir','Digantikan','Pengganti') NOT NULL,
  `id_jadwal` int NOT NULL,
  `potongan_telat` int NOT NULL,
  `potongan_istirahat` int NOT NULL,
  `potongan_lainnya` int NOT NULL,
  `tgl_updt` varchar(20) NOT NULL,
  `id_grup` int DEFAULT NULL,
  PRIMARY KEY (`id_rkk_detail`),
  KEY `fk_rkk_departmen_new` (`id_departmen`),
  KEY `fk_rkk_sub_department_new` (`id_sub_department`),
  KEY `idx_grup` (`id_grup`),
  CONSTRAINT `fk_rkk_departmen` FOREIGN KEY (`id_departmen`) REFERENCES `ms_departmen` (`id_departmen`),
  CONSTRAINT `fk_rkk_departmen_new` FOREIGN KEY (`id_departmen`) REFERENCES `ms_departmen` (`id_departmen`),
  CONSTRAINT `fk_rkk_grup` FOREIGN KEY (`id_grup`) REFERENCES `ms_grup_borongan` (`id_grup`) ON DELETE SET NULL,
  CONSTRAINT `fk_rkk_sub_department` FOREIGN KEY (`id_sub_department`) REFERENCES `ms_sub_department` (`id_sub_department`),
  CONSTRAINT `fk_rkk_sub_department_new` FOREIGN KEY (`id_sub_department`) REFERENCES `ms_sub_department` (`id_sub_department`)
) ENGINE=InnoDB AUTO_INCREMENT=7055 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_rkk_update` (
  `id_rkk_update` int NOT NULL AUTO_INCREMENT,
  `id_rkk_detail` int NOT NULL,
  `id_karyawan` int NOT NULL,
  `status` enum('Digantikan','Pengganti','') NOT NULL,
  PRIMARY KEY (`id_rkk_update`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_sia` (
  `id_sia` int NOT NULL AUTO_INCREMENT,
  `id_karyawan` int NOT NULL,
  `tgl_pengajuan` date NOT NULL,
  `tgl_awal` date NOT NULL,
  `tgl_akhir` date NOT NULL,
  `lama` int NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  PRIMARY KEY (`id_sia`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_sweep_config` (
  `id` int NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `interval_jam` int NOT NULL DEFAULT '4',
  `master_id` varchar(20) NOT NULL DEFAULT 'auto',
  `last_run` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_sync_log` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `device_id` int NOT NULL,
  `karyawan_id` int DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `message` text,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id_log`)
) ENGINE=InnoDB AUTO_INCREMENT=662 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tb_toleransi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `toleransi_masuk` int DEFAULT '10',
  `toleransi_istirahat_keluar` int DEFAULT '5',
  `toleransi_istirahat_masuk` int DEFAULT '10',
  `toleransi_pulang` int DEFAULT '5',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `age` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zk_sync_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pin` varchar(50) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `action` varchar(20) NOT NULL DEFAULT 'upsert',
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
