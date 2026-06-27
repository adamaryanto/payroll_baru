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
$koneksi->query("DELETE FROM ms_karyawan WHERE no_absen IN (99907, 99912)");

$koneksi->query("INSERT INTO ms_karyawan (
    id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, 
    status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, 
    status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah
) VALUES (
    '$first_dept', '$first_jab', 99907, 'Karyawan Flexi 7 Jam', 'Jakarta', '1995-01-01', 'Islam',
    'Belum Kawin', 'Laki-laki', '1234567890123456', '000123456789', 'Alamat KTP', 'Alamat Tinggal',
    'Aktif', '2026-01-01', '$first_sub', '$first_os', '$first_gol', '$id_shift_7', 150000
)");

$koneksi->query("INSERT INTO ms_karyawan (
    id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, 
    status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, 
    status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah
) VALUES (
    '$first_dept', '$first_jab', 99912, 'Karyawan Flexi 12 Jam', 'Jakarta', '1995-01-01', 'Islam',
    'Belum Kawin', 'Laki-laki', '1234567890123457', '000123456790', 'Alamat KTP', 'Alamat Tinggal',
    'Aktif', '2026-01-01', '$first_sub', '$first_os', '$first_gol', '$id_shift_12', 200000
)");

// 3. Create Scan Records (tb_record)
$date_today = '2026-06-28';
$koneksi->query("DELETE FROM tb_record WHERE userid IN ('99907', '99912') AND tgl = '$date_today'");

// Employee 7 Hours: worked 8.5 hours (08:00:00 to 16:30:00) -> Overtime 1.5 hours
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99907', '$date_today', '1', '0', '{$date_today} 08:00:00')");
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99907', '$date_today', '1', '1', '{$date_today} 16:30:00')");

// Employee 12 Hours: worked 10.5 hours (09:00:00 to 19:30:00) -> Early out (since 10.5 < 12)
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99912', '$date_today', '1', '0', '{$date_today} 09:00:00')");
$koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('dummy_log', '99912', '$date_today', '1', '1', '{$date_today} 19:30:00')");

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>";
echo "<h2 style='color:#10b981;'>Dummy Data Created Successfully!</h2>";
echo "<p style='color:#4b5563;'>Berikut data yang telah ditambahkan ke database:</p>";
echo "<ul style='color:#1f2937; line-height: 1.6;'>";
echo "<li><b>Jadwal Shift Baru</b>: 'FLEXIBLE 7 JAM' & 'FLEXIBLE 12 JAM'</li>";
echo "<li><b>Karyawan Baru (Dummy)</b>:";
echo "  <ul>";
echo "    <li>PIN/No Absen: <b>99907</b> - Karyawan Flexi 7 Jam (Shift: FLEXIBLE 7 JAM)</li>";
echo "    <li>PIN/No Absen: <b>99912</b> - Karyawan Flexi 12 Jam (Shift: FLEXIBLE 12 JAM)</li>";
echo "  </ul>";
echo "</li>";
echo "<li><b>Data Absen Mentah</b> (Tanggal: <b>$date_today</b>):";
echo "  <ul>";
echo "    <li>PIN 99907: Masuk 08:00, Pulang 16:30 (Total kerja 8.5 Jam &rarr; Lembur 1.5 Jam)</li>";
echo "    <li>PIN 99912: Masuk 09:00, Pulang 19:30 (Total kerja 10.5 Jam &rarr; Kena denda pulang awal karena &lt; 12 Jam)</li>";
echo "  </ul>";
echo "</li>";
echo "</ul>";
echo "<p style='margin-top:20px; padding:10px; background:#eff6ff; color:#1e40af; border-radius:6px;'>Silakan buka menu <b>Tarik Data</b>, pilih tanggal awal & akhir ke <b>28-06-2026</b>, lalu klik Tarik Data untuk melihat hasilnya.</p>";
echo "</div>";
?>
