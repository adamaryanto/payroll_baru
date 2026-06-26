<?php
include "koneksi.php";

$id = $_GET['id'];

// 1. Ambil data Header RKK (Tambahkan status_rkk)
$queryInfo = $koneksi->query("SELECT tgl_rkk, status_rkk FROM tb_rkk WHERE id_rkk = '$id'");
$info = $queryInfo->fetch_assoc();
$tanggal_sql = $info['tgl_rkk'] ?? '';
$tanggal_file = $tanggal_sql ? date('d-m-Y', strtotime($tanggal_sql)) : 'TanpaTanggal';
$status_rkk = $info['status_rkk'] ?? 0;

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Rencana_Upah_Cikupa_$tanggal_file.xls");
header("Pragma: no-cache");
header("Expires: 0");

// LOGIKA STEMPEL STATUS
$is_approved = ($status_rkk >= 2 || strtolower((string)$status_rkk) === 'approved' || strtolower((string)$status_rkk) === 'approve');
$stamp_text = $is_approved ? "APPROVED" : "UNAPPROVED";
$stamp_color = $is_approved ? "#008000" : "#FF0000";

// 2. Data Boneless
$queryBoneless = $koneksi->query("
    SELECT b.*, bm.biaya_mobil 
    FROM tb_boneless b
    LEFT JOIN tb_biayamobil bm ON b.id_biayamobil = bm.id_biayamobil
    WHERE b.tgl = '$tanggal_sql'
");
$bonelessHeader = $queryBoneless->fetch_assoc();
$id_boneless = $bonelessHeader['id_boneless'] ?? 0;
$potong = (float)($bonelessHeader['jumlah_mobil'] ?? 0);
$harga_master_saat_itu = (float)($bonelessHeader['biaya_mobil'] ?? 0);

$total_boneless = 0;
$boneless_details = [];
if ($id_boneless > 0) {
    $q_bd = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '$id_boneless'");
    while ($bd = $q_bd->fetch_assoc()) {
        $qty_val_rkk = (float)($bd['qty'] ?? 0);
        $qty_mul_rkk = $qty_val_rkk; // Hanya hitung jika ada qty (0 jika kosong)
        $nilai_satuan = abs((float)$bd['harga']); 
        $nilai_total_item = $nilai_satuan * $qty_mul_rkk;

        if ($bd['jenis'] == 'minus') {
            $total_boneless -= $nilai_total_item;
        } else {
            $total_boneless += $nilai_total_item;
        }
        $boneless_details[] = $bd;
    }
}
$data_dengan_mesin = array_filter($boneless_details, function($item) { return $item['jenis'] == 'minus'; });
$data_tanpa_mesin = array_filter($boneless_details, function($item) { return $item['jenis'] == 'plus'; });

// 3. Query Karyawan
$sql = "SELECT 
            IF(RD.id_karyawan = 0, RD.nama_karyawan_manual, K.nama_karyawan) as nama_karyawan, 
            O.OS_DHK as label_os, 
            G.golongan as label_gol, 
            D.nama_departmen as bagian,
            SD.nama_sub_department as posisi,
            JD.jam_masuk,
            JD.jam_keluar,
            JD.istirahat_masuk,
            JD.istirahat_keluar,
            RD.upah
        FROM tb_rkk_detail RD 
        LEFT JOIN tb_rkk R ON RD.id_rkk = R.id_rkk 
        LEFT JOIN tb_jadwal JD ON RD.id_jadwal = JD.id_jadwal 
        LEFT JOIN ms_karyawan K ON RD.id_karyawan = K.id_karyawan 
        LEFT JOIN ms_departmen D ON RD.id_departmen = D.id_departmen 
        LEFT JOIN ms_sub_department SD ON K.id_sub_department = SD.id_sub_department
        LEFT JOIN ms_os_dhk O ON K.id_os_dhk = O.id_os_dhk 
        LEFT JOIN ms_golongan G ON K.id_golongan = G.id_golongan 
        WHERE R.id_rkk = '$id' 
        ORDER BY D.nama_departmen ASC, nama_karyawan ASC";

$result = $koneksi->query($sql);

$data_per_bagian = [];
$total_upah_pabrik = 0;
while ($row = $result->fetch_assoc()) {
    $data_per_bagian[$row['bagian'] ?? 'LAINNYA'][] = $row;
    $total_upah_pabrik += (float)($row['upah'] ?? 0);
}

// --- OUTPUT EXCEL ---
echo "<table><tr><td colspan='11' style='text-align:center; font-weight:bold;'>JIKALAU NAMA YANG TERTERA DIABSEN TELAT MAKA AKAN DIPOTONG <span style='color:red;'>RP 25.000</span> <br> MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.</td></tr></table>";

// Looping cetak tabel per bagian
foreach ($data_per_bagian as $nama_bagian => $karyawans) {
    echo "<table border='1'>
    <thead>
        <tr style='background-color: #1e3a8a; font-weight:bold;'>
            <th colspan='11' style='text-align:center; color: #fff;'>" . strtoupper($nama_bagian ?? '') . "</th>
        </tr>
        <tr style='background-color: #f2f2f2;'>
            <th rowspan='2'>No</th>
            <th rowspan='2'>NAMA SESUAI KTP</th>
            <th rowspan='2'>OS/DHK</th>
            <th rowspan='2'>GOLONGAN</th>
            <th rowspan='2'>POSISI</th>
            <th rowspan='2'>JAM KERJA</th>
            <th rowspan='2'>JAM MASUK</th>
            <th colspan='2'>ISTIRAHAT</th>
            <th rowspan='2'>JAM PULANG</th>
            <th rowspan='2'>UPAH</th>
        </tr>
        <tr style='background-color: #f2f2f2;'>
            <th>KELUAR</th>
            <th>MASUK</th>
        </tr>
    </thead>
    <tbody>";

    $no = 1;
    foreach ($karyawans as $k) {
        $jam_kerja = ($k['jam_masuk'] ?? '') . " - " . ($k['jam_keluar'] ?? '');
        echo "<tr>
            <td align='center'>$no</td>
            <td>" . strtoupper($k['nama_karyawan'] ?? '') . "</td>
            <td align='center'>" . ($k['label_os'] ?? '') . "</td>
            <td align='center'>" . ($k['label_gol'] ?? '') . "</td>
            <td>" . ($k['posisi'] ?? '-') . "</td>
            <td align='center'>$jam_kerja</td>
            <td align='center'>" . ($k['jam_masuk'] ?? '') . "</td>
            <td align='center'>" . ($k['istirahat_keluar'] ?? '') . "</td>
            <td align='center'>" . ($k['istirahat_masuk'] ?? '') . "</td>
            <td align='center'>" . ($k['jam_keluar'] ?? '') . "</td>
            <td align='right'>Rp " . number_format((float)($k['upah'] ?? 0), 2, '.', ',') . "</td>
        </tr>";
        $no++;
    }
    echo "</tbody></table>";
}

echo "<table border='1'>
    <tr style='background-color: #203764; color:#fff; font-weight:bold;'>
        <td colspan='10' align='center' style='width: 800px;'>GRAND TOTAL UPAH (BIAYA PABRIK)</td>
        <td align='right' style='width: 100px;'>Rp " . number_format($total_upah_pabrik, 2, '.', ',') . "</td>
    </tr>
</table><br>";

// A. Biaya Boneless Rencana (Murni dari Master + Penyesuaian Item)
// Di RKK, kita hitung: (Jumlah Mobil * Harga Master)
$biaya_mobil_pure = ($harga_master_saat_itu * $potong);
// Penyesuaian (Plus/Minus) tim boneless ditambahkan ke total akhir
$biaya_boneless_total = $biaya_mobil_pure + $total_boneless;

// B. Hitung Grand Total (Upah Pabrik + Boneless Rencana)
$grand_total_all = $total_upah_pabrik + $biaya_boneless_total;

// C. Hitung Biaya Per Mobil Final
$biaya_per_mobil_final = ($potong > 0) ? ($grand_total_all / $potong) : 0;

// D. Biaya X Mobil untuk display (Total akumulasi yang dibagi jumlah mobil)
$biaya_x_mobil_display = $grand_total_all;

// --- TABEL REKAP BIAYA ---
echo "<br><table border='1' style='border-collapse:collapse;'>
    <tr style='background-color:#B4C7E7; font-weight:bold;'>
        <th colspan='7'>RENCANA TOTAL REKAP BIAYA PABRIK CIKUPA " . date('d F Y', strtotime($tanggal_sql)) . "</th>
    </tr>
    <tr style='background-color:#B4C7E7; font-weight:bold;'>
        <td colspan='6' align='center'>Biaya " . (int)$potong . " mobil</td>
        <td align='right'>Rp " . number_format($biaya_x_mobil_display, 2, '.', ',') . "</td>
    </tr>
    <tr style='background-color:#B4C7E7; font-weight:bold;'>
        <td colspan='6' align='center'>Biaya permobil</td>
        <td align='right'>Rp " . number_format($biaya_per_mobil_final, 2, '.', ',') . "</td>
    </tr>
</table><br>";

// --- TABEL TIM BONELESS ---

$data_dengan_mesin = []; // Untuk jenis 'minus'
$data_tanpa_mesin = [];  // Untuk jenis 'plus'

foreach ($boneless_details as $item) {
    if ($item['jenis'] == 'minus') {
        $data_dengan_mesin[] = $item;
    } else {
        $data_tanpa_mesin[] = $item;
    }
}

// --- TABEL A: DENGAN MESIN (MINUS) ---
echo "<table border='1' style='border-collapse:collapse;'>
    <thead>
        <tr style='background-color:red; color:white; font-weight:bold;'>
            <th colspan='7'>ESTIMASI BIAYA BONELESS - DENGAN MESIN</th>
        </tr>
        <tr style='background-color:#f2f2f2; font-weight:bold;'>
            <th width='30'>No</th>
            <th colspan='3'>NAMA TIM</th>
            <th width='80'>QTY</th>
            <th width='120'>HARGA SATUAN</th>
            <th width='120'>TOTAL</th>
        </tr>
    </thead>
    <tbody>";

$no_m = 1;
$subtotal_mesin = 0;
if (!empty($data_dengan_mesin)) {
    foreach ($data_dengan_mesin as $item) {
        $qty_val = (float)($item['qty'] ?? 0);
        $qty_mul = $qty_val;
        $harga_satuan = abs((float)$item['harga']);
        $total_line = $harga_satuan * $qty_mul;
        
        $subtotal_mesin -= $total_line;

        echo "<tr>
            <td align='center'>$no_m</td>
            <td colspan='3' style='font-weight:bold;'>" . strtoupper($item['nama_item'] ?? '') . "</td>
            <td align='center'>" . ($qty_val > 0 ? (floor($qty_val) == $qty_val ? number_format($qty_val, 0, '.', ',') : number_format($qty_val, 1, '.', ',')) : '-') . "</td>
            <td align='right'>Rp " . number_format($harga_satuan, 2, '.', ',') . "</td>
            <td align='right'>Rp " . number_format($total_line, 2, '.', ',') . "</td>
        </tr>";
        $no_m++;
    }
    echo "<tr style='background-color:#f2f2f2; font-weight:bold;'>
        <td colspan='6' align='center'>SUBTOTAL DENGAN MESIN</td>
        <td align='right'>Rp " . number_format(abs($subtotal_mesin), 2, '.', ',') . "</td>
    </tr>";
} else {
    echo "<tr><td colspan='7' align='center'>Tidak ada data Dengan Mesin</td></tr>";
}
echo "</tbody></table>";

// YELLOW SUMMARY FOR DENGAN MESIN
echo "<br><table border='1' style='border-collapse:collapse; width:100%;'>
    <tr style='background-color:red; color:white; font-weight:bold; text-align:center;'>
        <th colspan='5'>DENGAN MESIN BONLES</th>
    </tr>
    <tr style='background-color:yellow; font-weight:bold; text-align:center;'>
        <th>BIAYA PABRIK</th>
        <th>BONELESS</th>
        <th>POTONG</th>
        <th>TOTAL</th>
        <th>Biaya Per mobil</th>
    </tr>
    <tr style='font-weight:bold; text-align:center;'>
        <td align='center'>Rp " . number_format($total_upah_pabrik, 2, '.', ',') . "</td>
        <td align='center' style='color:red;'>Rp " . number_format(abs($biaya_mobil_pure), 2, '.', ',') . "</td>
        <td align='center'>" . (int)$potong . "</td>
        <td align='center' style='color:red;'>
            Rp " . number_format($total_upah_pabrik + $subtotal_mesin, 2, '.', ',') . "
        </td>
        <td align='center' style='color:red;'>
            Rp " . (($potong > 0) ? number_format(($total_upah_pabrik + $subtotal_mesin) / $potong, 2, '.', ',') : '0.00') . "
        </td>
    </tr>
</table><br>";

// --- TABEL B: TANPA MESIN (PLUS) ---
echo "<table border='1' style='border-collapse:collapse;'>
    <thead>
        <tr style='background-color:#C6E0B4; font-weight:bold;'>
            <th colspan='7'>ESTIMASI BIAYA BONELESS - TANPA MESIN</th>
        </tr>
        <tr style='background-color:#f2f2f2; font-weight:bold;'>
            <th width='30'>No</th>
            <th colspan='3'>NAMA TIM</th>
            <th width='80'>QTY</th>
            <th width='120'>HARGA SATUAN</th>
            <th width='120'>TOTAL</th>
        </tr>
    </thead>
    <tbody>";

$no_p = 1;
$subtotal_tanpa_mesin = 0;
if (!empty($data_tanpa_mesin)) {
    foreach ($data_tanpa_mesin as $item) {
        $qty_val = (float)($item['qty'] ?? 0);
        $qty_mul = $qty_val;
        $harga_satuan = abs((float)$item['harga']);
        $total_line = $harga_satuan * $qty_mul;

        $subtotal_tanpa_mesin += $total_line;

        echo "<tr>
            <td align='center'>$no_p</td>
            <td colspan='3' style='font-weight:bold;'>" . strtoupper($item['nama_item'] ?? '') . "</td>
            <td align='center'>" . ($qty_val > 0 ? (floor($qty_val) == $qty_val ? number_format($qty_val, 0, '.', ',') : number_format($qty_val, 1, '.', ',')) : '-') . "</td>
            <td align='right'>Rp " . number_format($harga_satuan, 2, '.', ',') . "</td>
            <td align='right'>Rp " . number_format($total_line, 2, '.', ',') . "</td>
        </tr>";
        $no_p++;
    }
    echo "<tr style='background-color:#f2f2f2; font-weight:bold;'>
        <td colspan='6' align='center'>SUBTOTAL TANPA MESIN</td>
        <td align='right'>Rp " . number_format($subtotal_tanpa_mesin, 2, '.', ',') . "</td>
    </tr>";
} else {
    echo "<tr><td colspan='7' align='center'>Tidak ada data Tanpa Mesin</td></tr>";
}
echo "</tbody></table>";

// YELLOW SUMMARY FOR TANPA MESIN
echo "<br><table border='1' style='border-collapse:collapse; width:100%;'>
    <tr style='background-color:red; color:white; font-weight:bold; text-align:center;'>
        <th colspan='5'>TANPA MESIN BONLES</th>
    </tr>
    <tr style='background-color:yellow; font-weight:bold; text-align:center;'>
        <th>BIAYA PABRIK</th>
        <th>BONELESS</th>
        <th>POTONG</th>
        <th>TOTAL</th>
        <th>Biaya Per mobil</th>
    </tr>
    <tr style='font-weight:bold; text-align:center;'>
        <td align='center'>Rp " . number_format($total_upah_pabrik, 2, '.', ',') . "</td>
        <td align='center'>Rp " . number_format($biaya_mobil_pure, 2, '.', ',') . "</td>
        <td align='center'>" . (int)$potong . "</td>
        <td align='center'>
            Rp " . number_format($total_upah_pabrik + $subtotal_tanpa_mesin, 2, '.', ',') . "
        </td>
        <td align='center'>
            Rp " . (($potong > 0) ? number_format(($total_upah_pabrik + $subtotal_tanpa_mesin) / $potong, 2, '.', ',') : '0.00') . "
        </td>
    </tr>
</table><br>";

// --- TABEL KUNING (HASIL AKHIR) ---
$style_boneless_pure = ($biaya_mobil_pure < 0) ? "color:red;" : "";
$simbol_boneless_pure = ($biaya_mobil_pure < 0) ? "- " : "";

echo "<table border='1' style='border-collapse:collapse;'>
    <tr style='background-color:yellow; font-weight:bold; text-align:center;'>
        <th colspan='7'>HASIL AKHIR (GRAND TOTAL RENCANA)</th>
    </tr>
    <tr style='background-color:yellow; font-weight:bold; text-align:center;'>
        <th colspan='2'>BIAYA PABRIK</th><th colspan='2'>BONELESS (RENCANA)</th><th>POTONG</th><th>TOTAL</th><th>Biaya Per mobil</th>
    </tr>
    <tr style='font-weight:bold; text-align:right;'>
        <td align='center' colspan='2'>Rp " . number_format($total_upah_pabrik, 2, '.', ',') . "</td>
        <td align='center' colspan='2' style='$style_boneless_pure'>Rp {$simbol_boneless_pure}" . number_format(abs($biaya_mobil_pure), 2, '.', ',') . "</td>
        <td align='center'>" . (int)$potong . "</td>
        <td align='center'>Rp " . number_format($total_upah_pabrik + $total_boneless, 2, '.', ',') . "</td>
        <td align='center' style='background-color:white;'>Rp " . (($potong > 0) ? number_format(($total_upah_pabrik + $total_boneless) / $potong, 2, '.', ',') : '0.00') . "</td>
    </tr>
</table><br><br>";

// --- POSISI STEMPEL DI BAWAH KIRI ---
echo "<table border='0'>
    <tr>
        <td width='30'></td> <td style='padding: 20px;'>
            <table border='1' style='border-collapse:collapse; border: 4px solid $stamp_color; transform: rotate(-15deg); -webkit-transform: rotate(-15deg); -ms-transform: rotate(-15deg);'>
                <tr>
                    <td align='center' style='font-size:24px; font-weight:bold; color:$stamp_color; padding:5px 30px;'>
                        $stamp_text
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>";
