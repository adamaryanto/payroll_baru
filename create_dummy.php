<?php
include "koneksi.php";

// 1. Create/Check Shifts
$koneksi->query("INSERT IGNORE INTO tb_jadwal (keterangan, jam_masuk, jam_keluar, istirahat_masuk, istirahat_keluar) 
                 VALUES ('FLEXIBLE 7 JAM', '00:00:00', '00:00:00', '00:00:00', '00:00:00')");
$id_shift_7 = $koneksi->insert_id;
if (!$id_shift_7) {
    $res = $koneksi->query("SELECT id_jadwal FROM tb_jadwal WHERE keterangan = 'FLEXIBLE 7 JAM'")->fetch_assoc();
    $id_shift_7 = $res['id_jadwal'];
}

$koneksi->query("INSERT IGNORE INTO tb_jadwal (keterangan, jam_masuk, jam_keluar, istirahat_masuk, istirahat_keluar) 
                 VALUES ('FLEXIBLE 12 JAM', '00:00:00', '00:00:00', '00:00:00', '00:00:00')");
$id_shift_12 = $koneksi->insert_id;
if (!$id_shift_12) {
    $res = $koneksi->query("SELECT id_jadwal FROM tb_jadwal WHERE keterangan = 'FLEXIBLE 12 JAM'")->fetch_assoc();
    $id_shift_12 = $res['id_jadwal'];
}

// Get fallback IDs to prevent foreign key issues
$first_dept = $koneksi->query("SELECT id_departmen FROM ms_departmen LIMIT 1")->fetch_assoc()['id_departmen'] ?? 1;
$first_jab = $koneksi->query("SELECT id_jabatan FROM ms_jabatan LIMIT 1")->fetch_assoc()['id_jabatan'] ?? 1;
$first_sub = $koneksi->query("SELECT id_sub_department FROM ms_sub_department LIMIT 1")->fetch_assoc()['id_sub_department'] ?? 1;
$first_os = $koneksi->query("SELECT id_os_dhk FROM ms_os_dhk LIMIT 1")->fetch_assoc()['id_os_dhk'] ?? 1;
$first_gol = $koneksi->query("SELECT id_golongan FROM ms_golongan LIMIT 1")->fetch_assoc()['id_golongan'] ?? 1;

// 2. Create Employees
$koneksi->query("DELETE FROM ms_karyawan WHERE no_absen IN (99907, 99912, 99908, 99913)");

// Employee 1: 7 Hours (Overtime 1.5h)
$koneksi->query("INSERT INTO ms_karyawan (
    id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, 
    status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, 
    status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah
) VALUES (
    '$first_dept', '$first_jab', 99907, 'Karyawan Flexi 7 Jam A', 'Jakarta', '1995-01-01', 'Islam',
    'Belum Kawin', 'Laki-laki', '1234567890123456', '000123456789', 'Alamat KTP', 'Alamat Tinggal',
    'Aktif', '2026-01-01', '$first_sub', '$first_os', '$first_gol', '$id_shift_7', 150000
)");

// Employee 2: 7 Hours (Overtime 2.5h)
$koneksi->query("INSERT INTO ms_karyawan (
    id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, 
    status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, 
    status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah
) VALUES (
    '$first_dept', '$first_jab', 99908, 'Karyawan Flexi 7 Jam B', 'Jakarta', '1995-01-01', 'Islam',
    'Belum Kawin', 'Laki-laki', '1234567890123458', '000123456791', 'Alamat KTP', 'Alamat Tinggal',
    'Aktif', '2026-01-01', '$first_sub', '$first_os', '$first_gol', '$id_shift_7', 150000
)");

// Employee 3: 12 Hours (Early out / No Overtime)
$koneksi->query("INSERT INTO ms_karyawan (
    id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, 
    status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, 
    status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah
) VALUES (
    '$first_dept', '$first_jab', 99912, 'Karyawan Flexi 12 Jam A', 'Jakarta', '1995-01-01', 'Islam',
    'Belum Kawin', 'Laki-laki', '1234567890123457', '000123456790', 'Alamat KTP', 'Alamat Tinggal',
    'Aktif', '2026-01-01', '$first_sub', '$first_os', '$first_gol', '$id_shift_12', 200000
)");

// Employee 4: 12 Hours (Overtime 2.0h)
$koneksi->query("INSERT INTO ms_karyawan (
    id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, 
    status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, 
    status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah
) VALUES (
    '$first_dept', '$first_jab', 99913, 'Karyawan Flexi 12 Jam B', 'Jakarta', '1995-01-01', 'Islam',
    'Belum Kawin', 'Laki-laki', '1234567890123459', '000123456792', 'Alamat KTP', 'Alamat Tinggal',
    'Aktif', '2026-01-01', '$first_sub', '$first_os', '$first_gol', '$id_shift_12', 200000
)");

// 3. Create Scan Records (tb_record)
$date_today = '2026-06-28';
$koneksi->query("DELETE FROM tb_record WHERE userid IN ('99907', '99912', '99908', '99913') AND tgl = '$date_today'");

// 99907: Masuk 08:00, Pulang 16:30 (Worked 8.5h -> expected 7h -> Overtime 1.5h)
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99907', '$date_today', '1', '0', '{$date_today} 08:00:00')");
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99907', '$date_today', '1', '1', '{$date_today} 16:30:00')");

// 99908: Masuk 08:30, Pulang 18:00 (Worked 9.5h -> expected 7h -> Overtime 2.5h)
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99908', '$date_today', '1', '0', '{$date_today} 08:30:00')");
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99908', '$date_today', '1', '1', '{$date_today} 18:00:00')");

// 99912: Masuk 09:00, Pulang 19:30 (Worked 10.5h -> expected 12h -> Early out)
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99912', '$date_today', '1', '0', '{$date_today} 09:00:00')");
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99912', '$date_today', '1', '1', '{$date_today} 19:30:00')");

// 99913: Masuk 07:00, Pulang 21:00 (Worked 14.0h -> expected 12h -> Overtime 2.0h)
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99913', '$date_today', '1', '0', '{$date_today} 07:00:00')");
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99913', '$date_today', '1', '1', '{$date_today} 21:00:00')");

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>";
echo "<h2 style='color:#10b981;'>4 Dummy Users & Records Created Successfully!</h2>";
echo "<p style='color:#4b5563;'>Berikut 4 data dummy yang telah siap diuji:</p>";
echo "<ul style='color:#1f2937; line-height: 1.6;'>";
echo "<li><b>PIN 99907</b> - Karyawan Flexi 7 Jam A";
echo "  <ul><li>Absen: 08:00 - 16:30 &rarr; <b>Lembur 1.5 Jam</b></li></ul>";
echo "</li>";
echo "<li><b>PIN 99908</b> - Karyawan Flexi 7 Jam B";
echo "  <ul><li>Absen: 08:30 - 18:00 &rarr; <b>Lembur 2.5 Jam</b></li></ul>";
echo "</li>";
echo "<li><b>PIN 99912</b> - Karyawan Flexi 12 Jam A";
echo "  <ul><li>Absen: 09:00 - 19:30 &rarr; <b>Kena Denda Pulang Awal (Kerja &lt; 12 Jam)</b></li></ul>";
echo "</li>";
echo "<li><b>PIN 99913</b> - Karyawan Flexi 12 Jam B";
echo "  <ul><li>Absen: 07:00 - 21:00 &rarr; <b>Lembur 2.0 Jam</b></li></ul>";
echo "</li>";
echo "</ul>";
echo "<p style='margin-top:20px; padding:10px; background:#eff6ff; color:#1e40af; border-radius:6px;'>Silakan buka menu <b>Tarik Data</b>, pilih tanggal awal & akhir ke <b>28-06-2026</b>, lalu klik Tarik Data untuk melihat hasilnya.</p>";
echo "</div>";
?>
