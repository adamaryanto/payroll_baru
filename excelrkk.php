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
$sum_upah_all = 0;
while ($row = $result->fetch_assoc()) {
    $key = $row['bagian'] ? strtoupper($row['bagian']) : 'LAINNYA';
    $data_per_bagian[$key][] = $row;
    $sum_upah_all += (float)($row['upah'] ?? 0);
}

$erow = 0;
?>
<table border="1" style="border-collapse:collapse;">
<?php $erow++; // R1 judul ?>
    <tr><td colspan="10" align="center" style="<?= $fB ?> <?= $thC ?> <?= $cWhite ?><?= $bd ?> height:23px;">FORMAT ABSENSI KARYAWAN HARI <?= $judul_hari . ' ' . $judul_tgl ?></td></tr>
<?php $erow++; // R2 aturan ?>
    <tr><td colspan="10" align="center" style="<?= $fB ?> <?= $thC ?> <?= $cWhite ?><?= $bd ?>">JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000</td></tr>
<?php $erow++; // R3 jam ?>
    <tr><td colspan="10" align="center" style="<?= $fB ?> <?= $thC ?> <?= $cWhite ?><?= $bd ?>">MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.</td></tr>
<?php
$hdr_gray = "$fB$thC$cHdr$bd";
$hdr_light = "$fB$thC$cHdrL$bd";
foreach ($data_per_bagian as $nama_bagian => $karyawans):
    $erow++; // bar ungu
?>
    <tr><td colspan="10" style="<?= $fB ?> <?= $thC ?> <?= $cSection ?><?= $bd ?>"><?= $nama_bagian ?></td></tr>
    <?php $erow++; // header baris 1 ?>
    <tr>
        <?php
        foreach (['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK'] as $h) echo "<th rowspan='2' style='$hdr_gray'>$h</th>";
        echo "<th rowspan='2' style='$hdr_gray'>ISTIRAHAT</th>";
        foreach (['JAM PULANG'] as $h) echo "<th rowspan='2' style='$hdr_gray'>$h</th>";
        ?>
    </tr>
    <?php $erow++; // header baris 2: MASUK di kolom I ?>
    <tr><?= str_repeat("<td style='$cWhite$bd'></td>", 2) ?><th style="<?= $hdr_gray ?>">MASUK</th><?= str_repeat("<td style='$cWhite$bd'></td>", 7) ?></tr>
    <?php $no_rkk = 1; ?>
    <?php foreach ($karyawans as $k):
        $erow++;
        $upah = (float)($k['upah'] ?? 0);
    ?>
    <tr style="<?= $fB ?>">
        <?= ex_cell($no_rkk++, "$cWhite$bd") ?>
        <td align="left" style="<?= $cWhite ?><?= $bd ?>"><?= strtoupper($k['nama_karyawan'] ?? '') ?></td>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell($k['label_os'] ?? '', "$cWhite$bd") ?>
        <?= ex_cell($k['label_gol'] ?? '', "$cWhite$bd") ?>
        <td align="left" style="<?= $cWhite ?><?= $bd ?>"><?= $k['posisi'] ?? '' ?></td>
        <?= ex_cell($k['j_masuk'] ?? '', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
        <?= ex_cell('', "$cWhite$bd") ?>
    </tr>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php // baris total tabel utama dibuang (kolom kanan tidak dipakai) ?>
<?php
// Nilai paten footer
$val_bon_net = 0;
foreach ($boneless_details as $bd0) {
    $ln0 = (float)($bd0['qty'] ?? 0) * abs((float)($bd0['harga'] ?? 0));
    $val_bon_net += (($bd0['jenis'] ?? '') == 'minus') ? -$ln0 : $ln0;
}
$val_grand_rkk = $sum_upah_all + $val_bon_net;
$val_permobil_rkk = ($potong > 0) ? $val_grand_rkk / $potong : 0;
function ex_rp2($v) {
    $v = (float)$v;
    return ($v == 0) ? '-' : number_format($v, 2, ',', '.');
}
$fB16 = "font-family:Calibri,sans-serif; font-size:16px; font-weight:bold;";
// cell pemisah tanpa border (biar box footer lepas/tidak nempel dgn tabel di atas)
function ex_blank($n, $h = 0) {
    $s = $h > 0 ? "height:{$h}px;" : "";
    return str_repeat("<td style='border:none;$s'></td>", $n);
}
$hTitle = "height:23px;";
$hRow = "height:21px;";
$hBig = "height:26px;";

// spacer: pisahkan dari tabel absensi di atas
echo "<tr>" . ex_blank(10, 14) . "</tr>";

$erow++; // rekap title (merge C-I)
echo "<tr>" . ex_blank(2)
    . "<td colspan='7' align='center' style='$fB$thC$cRekap{$bd}$hTitle'>REALISASI TOTAL REKAP BIAYA PABRIK CIKUPA $judul_tgl</td>"
    . ex_blank(2) . "</tr>";
$erow++; // biaya mobil
echo "<tr>" . ex_blank(2)
    . "<td colspan='5' align='center' style='$fB16$thC$cRekap{$bd}$hRow'>Biaya $potong mobil</td>"
    . "<td colspan='2' align='right' style='$fB16$thC$cRekap{$bd}$hRow'>Rp " . ex_rp2($sum_upah_all) . "</td>"
    . ex_blank(2) . "</tr>";
$erow++; // biaya permobil
echo "<tr>" . ex_blank(2)
    . "<td colspan='5' align='center' style='$fB16$thC$cRekap{$bd}$hRow'>Biaya permobil</td>"
    . "<td colspan='2' align='right' style='$fB16$thC$cRekap{$bd}$hRow'>Rp " . ex_rp2($val_permobil_rkk) . "</td>"
    . ex_blank(2) . "</tr>";

// spacer: pisahkan dari rekap di atas
echo "<tr>" . ex_blank(10, 14) . "</tr>";

$erow++; // bayaran tim bonless title (merge C-J)
echo "<tr>" . ex_blank(2)
    . "<td colspan='8' align='center' style='$fB$thC$cRekap{$bd}$hTitle'>BAYARAN TIM BONLESS</td>"
    . ex_blank(0) . "</tr>";

$Jplus = []; $Jminus = []; $nb = 0;
foreach ($boneless_details as $item):
    $erow++; $nb++;
    $qty = (float)($item['qty'] ?? 0);
    $hrg = abs((float)($item['harga'] ?? 0));
    $is_minus = (($item['jenis'] ?? '') == 'minus');
    $line_rkk = $qty * $hrg;
    $qty_disp = ($qty != 0) ? ex_num($qty) : '';
    $line_disp = ($line_rkk == 0) ? '-' : number_format($line_rkk, 0, ',', '.');
    $ch = "{$bd}{$hRow}";
?>
    <tr style="<?= $fB16 ?>">
        <?= ex_blank(2) ?>
        <?= ex_cell($nb, "$cWhite$ch") ?>
        <?= ex_cell('', "$cWhite$ch") ?>
        <?= ex_cell('', "$cWhite$ch") ?>
        <?= ex_cell($is_minus ? 'PENGURANGAN' : '', "$cWhite$ch") ?>
        <td align="left" style="<?= $cWhite ?><?= $ch ?>"><?= strtoupper($item['nama_item'] ?? '') ?></td>
        <?= ex_cell($qty_disp, "$cWhite$ch") ?>
        <?= ex_cell('Rp' . ex_num($hrg), "$cWhite$ch") ?>
        <td align="right" style="<?= $cWhite ?><?= $ch ?>">Rp <?= $line_disp ?></td>
        <?= ex_blank(0) ?>
    </tr>
<?php endforeach; ?>
<?php
$erow++; // total bonless (label merge C-I, nilai kolom J)
echo "<tr>" . ex_blank(2)
    . "<td colspan='7' align='center' style='$fB16$thC$cTotal{$bd}$hTitle'>TOTAL</td>"
    . "<td align='right' style='$cWhite{$bd}$hTitle'>Rp " . (($val_bon_net == 0) ? '-' : number_format($val_bon_net, 0, ',', '.')) . "</td>"
    . ex_blank(2) . "</tr>";
$erow++; // merah: dengan mesin bonles (merge C-J)
echo "<tr>" . ex_blank(2)
    . "<td colspan='8' align='center' style='$fB16$thC$cRed{$bd}$hBig'>DENGAN MESIN BONLES</td>"
    . ex_blank(0) . "</tr>";
$erow++; // kuning: header
echo "<tr>" . ex_blank(2)
    . "<td colspan='3' align='center' style='$fB16$thC$cYellow{$bd}$hBig'>BIAYA PABRIK</td>"
    . "<td align='center' style='$fB16$thC$cYellow{$bd}$hBig'>BONLESS</td>"
    . "<td align='center' style='$fB16$thC$cYellow{$bd}$hBig'>POTONG</td>"
    . "<td align='center' style='$fB16$thC$cYellow{$bd}$hBig'>TOTAL</td>"
    . "<td colspan='2' align='center' style='$fB16$thC$cYellow{$bd}$hBig'>Biaya Per mobil</td>"
    . ex_blank(0) . "</tr>";
$erow++; // kuning: nilai
echo "<tr>" . ex_blank(2)
    . "<td colspan='3' align='right' style='$cWhite{$bd}$hRow'>" . ex_rp2($sum_upah_all) . "</td>"
    . "<td align='right' style='$cWhite{$bd}$hRow'>" . ex_rp2($val_bon_net) . "</td>"
    . "<td align='center' style='$cWhite{$bd}$hRow'>$potong</td>"
    . "<td align='right' style='$cWhite{$bd}$hRow'>" . ex_rp2($val_grand_rkk) . "</td>"
    . "<td colspan='2' align='right' style='$cWhite{$bd}$hRow'>Rp" . ex_rp2($val_permobil_rkk) . "</td>"
    . ex_blank(0) . "</tr>";
?>
</table>