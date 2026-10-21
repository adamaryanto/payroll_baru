-- =====================================================================
-- DUMMY DATA untuk tes tampilan TIDAK DALAM RENCANA + KARYAWAN TIDAK MASUK
-- Jalankan di phpMyAdmin (database payroll) SATU KALI.
-- Tidak ada tombol dummy di aplikasi; data ini benar-benar masuk DB.
--
-- Bagian A: 2 karyawan dummy + 1 PIN tanpa nama yang "tap" di luar rencana
--           -> muncul di grup TIDAK DALAM RENCANA (halaman & Excel TDR)
-- Bagian B: 1 karyawan dummy masuk RENCANA tapi tidak tap
--           -> muncul di sheet KARYAWAN TIDAK MASUK (Excel), TIDAK muncul
--              sebagai baris di halaman realisasi karena tidak dibuatkan
--              baris tb_realisasi_detail.
-- =====================================================================

-- Realisasi yang dipakai: yang paling baru. Ganti manual kalau perlu.
SET @idrealisasi := (SELECT id_realisasi FROM tb_realisasi ORDER BY id_realisasi DESC LIMIT 1);
SET @idrkk       := (SELECT id_rkk FROM tb_realisasi WHERE id_realisasi = @idrealisasi);
SET @tgl         := (SELECT tgl_realisasi FROM tb_realisasi WHERE id_realisasi = @idrealisasi);
SET @did         := (SELECT id_mesin FROM tb_mesin ORDER BY id_mesin LIMIT 1);
SET @gid         := (SELECT id_grup FROM tb_realisasi_detail WHERE id_realisasi = @idrealisasi AND id_grup > 0 ORDER BY id_realisasi_detail LIMIT 1);
SET @dep         := (SELECT id_departmen FROM tb_rkk_detail WHERE id_rkk = @idrkk ORDER BY id_rkk_detail LIMIT 1);
SET @sub         := (SELECT id_sub_department FROM tb_rkk_detail WHERE id_rkk = @idrkk ORDER BY id_rkk_detail LIMIT 1);
SET @jad         := (SELECT id_jadwal FROM tb_rkk_detail WHERE id_rkk = @idrkk ORDER BY id_rkk_detail LIMIT 1);

-- ---------- A. Karyawan dummy (TDR) ----------
INSERT INTO ms_karyawan (no_absen, nama_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, status_karyawan, tgl_aktif, no_bpjs, id_jadwal, id_sub_department)
SELECT 9001, 'DUMMY TDR SATU', 1, 0, '', 'Islam', 'Laki-laki', '', '', '', 'Aktif', CURDATE(), '', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM ms_karyawan WHERE no_absen = 9001);

INSERT INTO ms_karyawan (no_absen, nama_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, status_karyawan, tgl_aktif, no_bpjs, id_jadwal, id_sub_department)
SELECT 9002, 'DUMMY TDR DUA', 1, 0, '', 'Islam', 'Laki-laki', '', '', '', 'Aktif', CURDATE(), '', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM ms_karyawan WHERE no_absen = 9002);

-- Tap masuk + pulang (status 0 = masuk, 1 = pulang)
INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu, source_device_id)
SELECT '<PIN>9001</PIN>',
       9001, @tgl, 1, 0, CONCAT(@tgl, ' 07:55:00'), @did
WHERE NOT EXISTS (SELECT 1 FROM tb_record WHERE userid = 9001 AND tgl = @tgl);

INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu, source_device_id)
SELECT '<PIN>9001</PIN>',
       9001, @tgl, 1, 1, CONCAT(@tgl, ' 17:05:00'), @did
WHERE NOT EXISTS (SELECT 1 FROM tb_record WHERE userid = 9001 AND tgl = @tgl AND status = 1);

INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu, source_device_id)
SELECT '<PIN>9002</PIN>',
       9002, @tgl, 1, 0, CONCAT(@tgl, ' 19:02:00'), @did
WHERE NOT EXISTS (SELECT 1 FROM tb_record WHERE userid = 9002 AND tgl = @tgl);

INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu, source_device_id)
SELECT '<PIN>9002</PIN>',
       9002, @tgl, 1, 1, CONCAT(@tgl, ' 23:30:00'), @did
WHERE NOT EXISTS (SELECT 1 FROM tb_record WHERE userid = 9002 AND tgl = @tgl AND status = 1);

-- Pin tanpa nama di master: tampil sebagai "PIN 9003"
INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu, source_device_id)
SELECT '<PIN>9003</PIN>',
       9003, @tgl, 1, 0, CONCAT(@tgl, ' 10:01:00'), @did
WHERE NOT EXISTS (SELECT 1 FROM tb_record WHERE userid = 9003 AND tgl = @tgl);

INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu, source_device_id)
SELECT '<PIN>9003</PIN>',
       9003, @tgl, 1, 1, CONCAT(@tgl, ' 15:30:00'), @did
WHERE NOT EXISTS (SELECT 1 FROM tb_record WHERE userid = 9003 AND tgl = @tgl AND status = 1);

-- ---------- B. Karyawan dummy RENCANA yang tidak masuk ----------
INSERT INTO ms_karyawan (no_absen, nama_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, status_karyawan, tgl_aktif, no_bpjs, id_jadwal, id_sub_department)
SELECT 9004, 'DUMMY TIDAK MASUK', 1, 0, '', 'Islam', 'Laki-laki', '', '', '', 'Aktif', CURDATE(), '', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM ms_karyawan WHERE no_absen = 9004);

INSERT INTO tb_rkk_detail (id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, upah, status_rkk, id_jadwal, potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt, id_grup)
SELECT @idrkk, K.id_karyawan, NULL, @dep, @sub, 0, 'Hadir', @jad, 0, 0, 0, NOW(), @gid
FROM ms_karyawan K
WHERE K.no_absen = 9004
  AND NOT EXISTS (SELECT 1 FROM tb_rkk_detail D WHERE D.id_rkk = @idrkk AND D.id_karyawan = K.id_karyawan);

-- ---------- CEK HASIL ----------
SELECT @idrealisasi AS id_realisasi, @tgl AS tanggal, @idrkk AS id_rkk, @did AS id_mesin, @gid AS id_grup;
SELECT userid, tgl, detail_waktu, status, source_device_id FROM tb_record WHERE userid IN (9001,9002,9003) AND tgl = @tgl ORDER BY userid, detail_waktu;
SELECT K.no_absen, K.nama_karyawan, R.id_rkk FROM ms_karyawan K LEFT JOIN tb_rkk_detail R ON R.id_karyawan = K.id_karyawan WHERE K.no_absen IN (9001,9002,9004);

-- =====================================================================
-- CARA HAPUS DUMMY (jalankan kalau sudah selesai tes):
-- SET @idrkk := (SELECT id_rkk FROM tb_realisasi ORDER BY id_realisasi DESC LIMIT 1);
-- SET @tgl   := (SELECT tgl_realisasi FROM tb_realisasi ORDER BY id_realisasi DESC LIMIT 1);
-- DELETE FROM tb_record WHERE userid IN (9001,9002,9003) AND tgl = @tgl;
-- DELETE FROM tb_rkk_detail WHERE id_karyawan IN (SELECT id_karyawan FROM ms_karyawan WHERE no_absen IN (9001,9002,9004));
-- DELETE FROM tb_realisasi_detail WHERE id_karyawan IN (SELECT id_karyawan FROM ms_karyawan WHERE no_absen IN (9001,9002,9004));
-- DELETE FROM ms_karyawan_pin WHERE pin IN ('9001','9002','9003');
-- DELETE FROM ms_karyawan WHERE no_absen IN (9001,9002,9004);
-- =====================================================================
