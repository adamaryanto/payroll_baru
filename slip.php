<?php
include "koneksi.php";  // sesuaikan koneksi

$id_karyawan = $_GET['id'];
$tanggal_mulai = $_GET['ttgl1'];
$tanggal_akhir = $_GET['ttgl2'];

$bulanIndo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$bulan_angka = date("m", strtotime($tanggal_mulai));
$bulan = $bulanIndo[$bulan_angka];

// 1. Ambil Nama Karyawan untuk filename
$q_kar = $koneksi->query("SELECT nama_karyawan FROM ms_karyawan WHERE id_karyawan = '$id_karyawan'");
$d_kar = $q_kar->fetch_assoc();
$nama_file = $d_kar ? str_replace(' ', '_', $d_kar['nama_karyawan']) : "Karyawan_$id_karyawan";
$tgl_file = date('d-m-Y', strtotime($tanggal_mulai));

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Slip_Upah_".$nama_file."_".$tgl_file.".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "<meta charset='UTF-8'>";

function rupiah($angka){
    return "Rp " . number_format($angka, 0, ',', '.');
}

// 2. Ambil denda global
$q_denda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
$d_denda = $q_denda->fetch_assoc();

$globalDendaMasuk = $d_denda['denda_masuk'] ?? 0;
$globalDendaIstirahatKeluar = $d_denda['denda_istirahat_keluar'] ?? 0;
$globalDendaIstirahatMasuk = $d_denda['denda_istirahat_masuk'] ?? 0;
$globalDendaPulang = $d_denda['denda_pulang'] ?? 0;
$globalDendaTidakLengkap = $d_denda['denda_tidak_lengkap'] ?? 0;

// 3. Query join lengkap (PENTING: Gunakan LEFT JOIN agar data tidak hilang jika jabatan/dept kosong)
$sql = "SELECT
    r.tgl_realisasi_detail,
    r.r_upah,
    r.r_jam_masuk,
    r.r_jam_keluar,
    r.r_istirahat_masuk,
    r.r_istirahat_keluar,
    r.ra_masuk,
    r.ra_keluar,
    r.ra_istirahat_masuk,
    r.ra_istirahat_keluar,
    r.r_potongan_lainnya,
    r.lembur,
    j.jabatan,
    d.nama_departmen,
    k.nama_karyawan,
    jd.jam_masuk,
    jd.jam_keluar,
    jd.istirahat_masuk,
    jd.istirahat_keluar,
    rd.status_rkk,
    os.OS_DHK
FROM tb_realisasi_detail r
JOIN ms_karyawan k ON r.id_karyawan = k.id_karyawan
LEFT JOIN ms_jabatan j ON k.id_jabatan = j.id_jabatan
LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen
LEFT JOIN tb_jadwal jd ON r.id_jadwal = jd.id_jadwal
LEFT JOIN tb_rkk_detail rd ON r.id_rkk_detail = rd.id_rkk_detail
LEFT JOIN ms_os_dhk os ON k.id_os_dhk = os.id_os_dhk
WHERE r.id_karyawan = '$id_karyawan'
  AND r.tgl_realisasi_detail BETWEEN '$tanggal_mulai' AND '$tanggal_akhir'
ORDER BY r.tgl_realisasi_detail ASC
";

$result = $koneksi->query($sql);

// MULAI OUTPUT EXCEL (format tabel HTML)
$row1 = $result->fetch_assoc();
$namaKaryawan = $row1 ? $row1['nama_karyawan'] : 'TIDAK ADA DATA';

echo "<table border='1'>";
echo '
<tr>
    <td rowspan="2" style="width:80px; font-weight:bold; text-align:center; vertical-align:middle;">Bulan ' . $bulan . '</td>

    <td colspan="8" style="font-weight:bold; text-align:center; font-size:14pt;">SLIP UPAH</td>

    <td colspan="3" rowspan="2" style="font-weight:bold; text-align:center; vertical-align:middle;">OUTSOURCING : ' . ($row1['OS_DHK'] ?? '-') . '</td>
</tr>

<tr>
    <td colspan="8" style="font-weight:bold; text-align:left;">NAMA : ' . $namaKaryawan . '</td>
</tr>

<tr>
    <th>Tanggal</th>
    <th>Upah</th>
    <th>Jam Masuk</th>
    <th>Jam Pulang</th>
    <th>Pot Telat</th>
    <th>Pot Istirahat</th>
    <th>Pot Pulang</th>
    <th>Pot Tidak Absen</th>
    <th>Pot Lainnya</th>
    <th>Lembur</th>
    <th>Total</th>

    <th>Karyawan Setuju</th>
    <th>OS</th>
    <th>HRD ' . ($row1['OS_DHK'] ?? 'DHK') . '</th>
</tr>
';
if($result->num_rows > 0) {
    $result->data_seek(0);
}
$no=1;
$totalKeseluruhan = 0;
while($row = $result->fetch_assoc()){
    // Logika Pelanggaran Dinamis (Sync with realisasi/kelola.php)
    $isLate = (!empty($row['ra_masuk']) && $row['ra_masuk'] != '00:00:00' && !empty($row['jam_masuk']) && $row['jam_masuk'] != '00:00:00' && strtotime($row['ra_masuk']) > strtotime($row['jam_masuk']));
    $isLateBreak = (!empty($row['ra_istirahat_masuk']) && $row['ra_istirahat_masuk'] != '00:00:00' && !empty($row['istirahat_masuk']) && $row['istirahat_masuk'] != '00:00:00' && strtotime($row['ra_istirahat_masuk']) > strtotime($row['istirahat_masuk']));
    $isEarlyOut = (!empty($row['ra_keluar']) && $row['ra_keluar'] != '00:00:00' && !empty($row['jam_keluar']) && $row['jam_keluar'] != '00:00:00' && strtotime($row['ra_keluar']) < strtotime($row['jam_keluar']));

    // Incomplete Logs
    $hasIncompleteMain = (
        (!empty($row['ra_masuk']) && $row['ra_masuk'] != '00:00:00' && (empty($row['ra_keluar']) || $row['ra_keluar'] == '00:00:00')) ||
        ((empty($row['ra_masuk']) || $row['ra_masuk'] == '00:00:00') && !empty($row['ra_keluar']) && $row['ra_keluar'] != '00:00:00')
    );
    $isRestExpected = (!empty($row['istirahat_keluar']) && $row['istirahat_keluar'] != '00:00:00');
    $hasIncompleteBreak = ($isRestExpected && (
        (empty($row['ra_istirahat_keluar']) || $row['ra_istirahat_keluar'] == '00:00:00') ||
        (empty($row['ra_istirahat_masuk']) || $row['ra_istirahat_masuk'] == '00:00:00')
    ));

    $isEarlyBreak = (!empty($row['ra_istirahat_keluar']) && $row['ra_istirahat_keluar'] != '00:00:00' && !empty($row['istirahat_keluar']) && $row['istirahat_keluar'] != '00:00:00' && strtotime($row['ra_istirahat_keluar']) < strtotime($row['istirahat_keluar']));

    // Skip Denda if wage is 0 or status is "Digantikan"
    if ($row['r_upah'] == 0 || $row['status_rkk'] == 'Digantikan') {
        $potTelatValue = 0;
        $potIstirahatValue = 0;
        $potPulangValue = 0;
        $potTidakLengkapValue = 0;
    } else {
        $potTelatValue = $isLate ? $globalDendaMasuk : 0;
        $potIstirahatValue = ($isEarlyBreak ? $globalDendaIstirahatKeluar : 0) + ($isLateBreak ? $globalDendaIstirahatMasuk : 0);
        $potPulangValue = $isEarlyOut ? $globalDendaPulang : 0;
        $potTidakLengkapValue = ($hasIncompleteMain || $hasIncompleteBreak) ? $globalDendaTidakLengkap : 0;
    }

    $totalRow = ($row['r_upah'] + $row['lembur']) - ($potTelatValue + $potIstirahatValue + $potPulangValue + $potTidakLengkapValue + $row['r_potongan_lainnya']);

    echo "
    <tr>
     <td style='mso-number-format:\@; font-size: 8pt; white-space: nowrap;'>".date('d/m/Y', strtotime($row['tgl_realisasi_detail']))."</td>
        <td>".rupiah($row['r_upah'])."</td>
         <td>".$row['ra_masuk']."</td>
        <td>".$row['ra_keluar']."</td>
         <td>".rupiah($potTelatValue)."</td>
        <td>".rupiah($potIstirahatValue)."</td>
        <td>".rupiah($potPulangValue)."</td>
        <td>".rupiah($potTidakLengkapValue)."</td>
        <td>".rupiah($row['r_potongan_lainnya'])."</td>
         <td>".rupiah($row['lembur'])."</td>
         <td style='font-weight:bold;'>".rupiah($totalRow)."</td>

        <td></td>
        <td></td>
         <td></td>
        
    </tr>
    ";

    $totalKeseluruhan += $totalRow;
    $no++;
}

// Tambahkan Baris Subtotal
echo "
<tr style='font-weight:bold; background-color:#f8f9fa;'>
    <td colspan='10' style='text-align:right; height:30px; vertical-align:middle;'>SUBTOTAL GAJI</td>
    <td style='text-align:right; vertical-align:middle; background-color:#e0f2fe; color:#1e40af;'>" . rupiah($totalKeseluruhan) . "</td>
    <td colspan='3'></td>
</tr>";

echo "</table>";
?>