<?php
include "koneksi.php";

$id = $_GET['id'];

$queryInfo = $koneksi->query("SELECT tgl_rkk, status_rkk FROM tb_rkk WHERE id_rkk = '$id'");
$info = $queryInfo->fetch_assoc();
$tanggal_sql = $info['tgl_rkk'] ?? '';
$tanggal_file = $tanggal_sql ? date('d-m-Y', strtotime($tanggal_sql)) : 'TanpaTanggal';
$status_rkk = $info['status_rkk'] ?? 0;

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Rencana_Upah_Cikupa_$tanggal_file.xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "<meta charset='UTF-8'>";

$hari_arr = ['MINGGU','SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU'];
$bulan_arr = ['','JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
$ts = $tanggal_sql ? strtotime($tanggal_sql) : time();
$judul_hari = $hari_arr[(int)date('w', $ts)];
$judul_tgl = date('d', $ts) . ' ' . $bulan_arr[(int)date('n', $ts)] . ' ' . date('Y', $ts);

// ---- palet persis contoh ----
$bd = "border:1px solid #000;";
$fB = "font-family:Calibri,sans-serif; font-size:18px; font-weight:bold;";
$thC = "text-align:center; vertical-align:middle;";
$cWhite = "background:#FFFFFF;";
$cSection = "background:#B2A1C6;";
$cHdr = "background:#D9D9D9;";
$cHdrL = "background:#EFEFEF;";
$cRekap = "background:#B4C7E7;";
$cTotal = "background:#C6D9F0;";
$cRed = "background:#FF0000;";
$cYellow = "background:#FFFF00;";

function ex_num($v) {
    $v = (float)$v;
    if (abs($v - round($v)) < 1e-9) return (string)(int)round($v);
    return rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.');
}
function ex_cell($v, $style, $align = 'center') {
    return "<td align='$align' style='$style'>$v</td>";
}

// Data Boneless (urut input, seperti contoh)
$queryBoneless = $koneksi->query("
    SELECT b.*, bm.biaya_mobil
    FROM tb_boneless b
    LEFT JOIN tb_biayamobil bm ON b.id_biayamobil = bm.id_biayamobil
    WHERE b.tgl = '$tanggal_sql'
");
$bonelessHeader = $queryBoneless->fetch_assoc();
$id_boneless = $bonelessHeader['id_boneless'] ?? 0;
$potong = (int)($bonelessHeader['jumlah_mobil'] ?? 0);

$boneless_details = [];
if ($id_boneless > 0) {
    $q_bd = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '$id_boneless' ORDER BY id_boneless_detail ASC");
    while ($bd_row = $q_bd->fetch_assoc()) $boneless_details[] = $bd_row;
}

// Data karyawan per bagian
$sql = "SELECT
            IF(RD.id_karyawan = 0, RD.nama_karyawan_manual, K.nama_karyawan) as nama_karyawan,
            O.OS_DHK as label_os,
            G.golongan as label_gol,
            D.nama_departmen as bagian,
            COALESCE(SD2.nama_sub_department, SD.nama_sub_department) as posisi,
            JD.jam_masuk as j_masuk,
            RD.upah
        FROM tb_rkk_detail RD
        LEFT JOIN tb_rkk R ON RD.id_rkk = R.id_rkk
        LEFT JOIN tb_jadwal JD ON RD.id_jadwal = JD.id_jadwal
        LEFT JOIN ms_karyawan K ON RD.id_karyawan = K.id_karyawan
        LEFT JOIN ms_departmen D ON RD.id_departmen = D.id_departmen
        LEFT JOIN ms_sub_department SD ON K.id_sub_department = SD.id_sub_department
        LEFT JOIN ms_sub_department SD2 ON RD.id_sub_department = SD2.id_sub_department
        LEFT JOIN ms_os_dhk O ON K.id_os_dhk = O.id_os_dhk
        LEFT JOIN ms_golongan G ON K.id_golongan = G.id_golongan
        WHERE R.id_rkk = '$id'
        ORDER BY D.nama_departmen ASC, nama_karyawan ASC";
$result = $koneksi->query($sql);

$data_per_bagian = [];
while ($row = $result->fetch_assoc()) {
    $key = $row['bagian'] ? strtoupper($row['bagian']) : 'LAINNYA';
    $data_per_bagian[$key][] = $row;
}

$erow = 0;
$empty15 = str_repeat("<td style='$cWhite$bd'></td>", 15);
?>
<table border="1" style="border-collapse:collapse;">
<?php $erow++; // R1 judul ?>
    <tr><td colspan="15" align="center" style="<?= $fB ?> <?= $thC ?> <?= $cWhite ?><?= $bd ?> height:23px;">FORMAT ABSENSI KARYAWAN HARI <?= $judul_hari . ' ' . $judul_tgl ?></td></tr>
<?php $erow++; // R2 aturan ?>
    <tr><td colspan="15" align="center" style="<?= $fB ?> <?= $thC ?> <?= $cWhite ?><?= $bd ?>">JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000</td></tr>
<?php $erow++; // R3 jam ?>
    <tr><td colspan="15" align="center" style="<?= $fB ?> <?= $thC ?> <?= $cWhite ?><?= $bd ?>">MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.</td></tr>
<?php
$hdr_gray = "$fB$thC$cHdr$bd";
$hdr_light = "$fB$thC$cHdrL$bd";
foreach ($data_per_bagian as $nama_bagian => $karyawans):
    $erow++; // bar ungu
?>
    <tr><td colspan="15" style="<?= $fB ?> <?= $thC ?> <?= $cSection ?><?= $bd ?>"><?= $nama_bagian ?></td></tr>
    <?php $erow++; // header baris 1 ?>
    <tr>
        <?php
        foreach (['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK'] as $h) echo "<th rowspan='2' style='$hdr_gray'>$h</th>";
        echo "<th style='$hdr_gray'>ISTIRAHAT</th>";
        foreach (['JAM PULANG'] as $h) echo "<th rowspan='2' style='$hdr_gray'>$h</th>";
        foreach (['HASIL KERJA','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'] as $h) echo "<th rowspan='2' style='$hdr_light'>$h</th>";
        ?>
    </tr>
    <?php $erow++; // header baris 2: MASUK di kolom I ?>
    <tr><?= str_repeat("<td style='$cWhite$bd'></td>", 8) ?><th style="<?= $hdr_gray ?>">MASUK</th><?= str_repeat("<td style='$cWhite$bd'></td>", 6) ?></tr>
    <?php foreach ($karyawans as $k):
        $erow++;
        $upah = (float)($k['upah'] ?? 0);
    ?>
    <tr style="<?= $fB ?>">
        <?= ex_cell('=+A' . ($erow - 1) . '+1', "$cWhite$bd") ?>
        <td align="left" style="<?= $cWhite ?><?= $bd ?>"><?= strtoupper($k['nama_karyawan'] ?? '') ?></td>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell($k['label_os'] ?? '', "$cWhite$bd") ?>
        <?= ex_cell($k['label_gol'] ?? '', "$cWhite$bd") ?>
        <td align="left" style="<?= $cWhite ?><?= $bd ?>"><?= $k['posisi'] ?? '' ?></td>
        <?= ex_cell($k['j_masuk'] ?? '', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <td align="right" style="<?= $cWhite ?><?= $bd ?>"><?= ex_num($upah) ?></td>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <td align="right" style="<?= $cWhite ?><?= $bd ?>">=L<?= $erow ?>-M<?= $erow ?>+N<?= $erow ?></td>
    </tr>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php
$erow++;
$total_row = $erow;
$first_data_row = 4;
$last_data_row = $total_row - 1;
?>
    <tr style="<?= $fB ?>">
        <?= str_repeat("<td style='$cWhite$bd'></td>", 11) ?>
        <td align="right" style="<?= $cWhite ?><?= $bd ?>">=SUM(L<?= $first_data_row ?>:L<?= $last_data_row ?>)</td>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <td align="right" style="<?= $cWhite ?><?= $bd ?>">=SUM(O<?= $first_data_row ?>:O<?= $last_data_row ?>)</td>
    </tr>
<?php
$erow++; // R300 rekap title
echo "<tr><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cRekap$bd'>REALISASI TOTAL REKAP BIAYA PABRIK CIKUPA $judul_tgl</td>" . str_repeat("<td style='$cWhite$bd'></td>", 12) . "</tr>";
$erow++; $biaya_row = $erow; // R301
echo "<tr><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cRekap$bd'>Biaya $potong mobil</td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cRekap$bd'>=O$total_row</td>" . str_repeat("<td style='$cWhite$bd'></td>", 7) . "</tr>";
$sum_row_fwd = 0; // diisi setelah tahu baris summary
$erow++; $permobil_row = $erow; // R302
$sum_row_fwd = $permobil_row + count($boneless_details) + 5; // R304(+1), item(n), R310, R311, R312, R313
echo "<tr><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cRekap$bd'>Biaya permobil</td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cRekap$bd'>=I$sum_row_fwd</td>" . str_repeat("<td style='$cWhite$bd'></td>", 7) . "</tr>";
$erow++; // R304
echo "<tr><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cRekap$bd'>BAYARAN TIM BONLESS</td>" . str_repeat("<td style='$cWhite$bd'></td>", 12) . "</tr>";

$Jplus = []; $Jminus = []; $nb = 0;
foreach ($boneless_details as $item):
    $erow++; $nb++;
    $qty = (float)($item['qty'] ?? 0);
    $hrg = abs((float)($item['harga'] ?? 0));
    $is_minus = (($item['jenis'] ?? '') == 'minus');
    $Jrows = "J$erow";
    if ($is_minus) $Jminus[] = $Jrows; else $Jplus[] = $Jrows;
    $qty_disp = ($qty != 0) ? ex_num($qty) : '';
?>
    <tr style="<?= $fB ?>">
        <td style="<?= $cWhite ?><?= $bd ?>"></td>
        <td style="<?= $cWhite ?><?= $bd ?>"></td>
        <?= ex_cell($nb, "$cWhite$bd") ?>
        <td style="<?= $cWhite ?><?= $bd ?>"></td>
        <td style="<?= $cWhite ?><?= $bd ?>"></td>
        <?= ex_cell($is_minus ? 'PENGURANGAN' : '', "$cWhite$bd") ?>
        <td align="left" style="<?= $cWhite ?><?= $bd ?>"><?= strtoupper($item['nama_item'] ?? '') ?></td>
        <?= ex_cell($qty_disp, "$cWhite$bd") ?>
        <?= ex_cell(ex_num($hrg), "$cWhite$bd") ?>
        <td align="right" style="<?= $cWhite ?><?= $bd ?>">=H<?= $erow ?>*I<?= $erow ?></td>
        <?= str_repeat("<td style='$cWhite$bd'></td>", 5) ?>
    </tr>
<?php endforeach; ?>
<?php
$erow++; $bontot_row = $erow; // R310 TOTAL
$parts = array_merge($Jplus, array_map(function($x){ return "-$x"; }, $Jminus));
$bon_f = $parts ? '=' . ltrim(implode('', $parts), '+') : '0';
echo "<tr style='$fB'><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td colspan='7' align='center' style='$cTotal$bd'>TOTAL</td><td align='right' style='$cWhite$bd'>$bon_f</td>" . str_repeat("<td style='$cWhite$bd'></td>", 5) . "</tr>";
$erow++; // R311 red title
echo "<tr><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td colspan='8' align='center' style='$fB$thC$cRed$bd'>DENGAN MESIN BONLES</td>" . str_repeat("<td style='$cWhite$bd'></td>", 5) . "</tr>";
$erow++; // R312 yellow header
echo "<tr><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cYellow$bd'>BIAYA PABRIK</td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='center' style='$fB$thC$cYellow$bd'>BONLESS</td><td align='center' style='$fB$thC$cYellow$bd'>POTONG</td><td align='center' style='$fB$thC$cYellow$bd'>TOTAL</td><td align='center' style='$fB$thC$cYellow$bd'>Biaya Per mobil</td>" . str_repeat("<td style='$cWhite$bd'></td>", 6) . "</tr>";
$erow++; $sum_row = $erow; // R313 values
echo "<tr style='$fB'><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='right' style='$cWhite$bd'>=H$biaya_row</td><td style='$cWhite$bd'></td><td style='$cWhite$bd'></td><td align='right' style='$cWhite$bd'>=J$bontot_row</td><td align='center' style='$cWhite$bd'>$potong</td><td align='right' style='$cWhite$bd'>=C$sum_row+F$sum_row</td><td align='right' style='$cWhite$bd'>=H$sum_row/G$sum_row</td>" . str_repeat("<td style='$cWhite$bd'></td>", 6) . "</tr>";
?>
</table>
