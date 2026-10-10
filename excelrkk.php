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

$is_approved = ($status_rkk >= 2 || strtolower((string)$status_rkk) === 'approved' || strtolower((string)$status_rkk) === 'approve');
$stamp_text = $is_approved ? "APPROVED" : "UNAPPROVED";
$stamp_color = $is_approved ? "#008000" : "#FF0000";

$hari_arr = ['MINGGU','SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU'];
$bulan_arr = ['','JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
$ts = $tanggal_sql ? strtotime($tanggal_sql) : time();
$judul_hari = $hari_arr[(int)date('w', $ts)];
$judul_tgl = date('d', $ts) . ' ' . $bulan_arr[(int)date('n', $ts)] . ' ' . date('Y', $ts);

// ---- palet persis contoh ----
$bd = "border:1px solid #000;";
$fBase = "font-family:Calibri,sans-serif; font-size:18px;";
$th = "font-weight:bold; text-align:center; vertical-align:middle;";
$cWhite = "background:#FFFFFF;";
$cSection = "background:#B2A1C6;";
$cHdr = "background:#D9D9D9;";
$cHdrL = "background:#EFEFEF;";
$cRekap = "background:#B4C7E7;";
$cTotal = "background:#C6D9F0;";
$cRed = "background:#FF0000; color:#FFFFFF;";
$cYellow = "background:#FFFF00;";

// Data Boneless
$queryBoneless = $koneksi->query("
    SELECT b.*, bm.biaya_mobil
    FROM tb_boneless b
    LEFT JOIN tb_biayamobil bm ON b.id_biayamobil = bm.id_biayamobil
    WHERE b.tgl = '$tanggal_sql'
");
$bonelessHeader = $queryBoneless->fetch_assoc();
$id_boneless = $bonelessHeader['id_boneless'] ?? 0;
$potong = (float)($bonelessHeader['jumlah_mobil'] ?? 0);

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

$erow = 0; // penghitung baris excel untuk rumus
$Lrows = [];
$Orows = [];
?>
<table border="1" style="border-collapse:collapse;">
<?php $erow++; ?>
    <tr><th colspan="15" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px; height:23px;">FORMAT ABSENSI KARYAWAN HARI <?php echo $judul_hari . ' ' . $judul_tgl; ?></th></tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px;">JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000</td></tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px;">MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.</td></tr>
<?php foreach ($data_per_bagian as $nama_bagian => $karyawans): $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cSection.$bd; ?> font-size:18px;"><?php echo $nama_bagian; ?></td></tr>
    <?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?>">
        <?php
        $hdrs = ['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK','ISTIRAHAT MASUK','JAM PULANG'];
        foreach ($hdrs as $h) echo "<th style=\"$fBase$th$cHdr$bd\">$h</th>";
        $hdrs2 = ['HASIL KERJA','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'];
        foreach ($hdrs2 as $h) echo "<th style=\"$fBase$th$cHdrL$bd\">$h</th>";
        ?>
    </tr>
    <?php
    $no = 1;
    foreach ($karyawans as $k):
        $erow++;
        $Lrows[] = "L$erow";
        $Orows[] = "O$erow";
        $upah = (float)($k['upah'] ?? 0);
    ?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $no++; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo strtoupper($k['nama_karyawan'] ?? ''); ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $k['label_os'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $k['label_gol'] ?? ''; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"><?php echo $k['posisi'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $k['j_masuk'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo number_format($upah, 0, ',', '.'); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;">=L<?php echo $erow; ?>-M<?php echo $erow; ?>+N<?php echo $erow; ?></td>
    </tr>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php
$erow++;
$total_row = $erow;
$sumL = $Lrows ? '=SUM(' . implode(',', $Lrows) . ')' : '0';
$sumO = $Orows ? '=SUM(' . implode(',', $Orows) . ')' : '0';
?>
    <tr style="<?php echo $fBase; ?> font-weight:bold;">
        <td colspan="11" align="center" style="<?php echo $cWhite.$bd; ?>">TOTAL</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $sumL; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td><td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $sumO; ?></td>
    </tr>
<?php
$erow++;
$rekap_row = $erow;
?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cRekap.$bd; ?> font-size:18px;">REALISASI TOTAL REKAP BIAYA PABRIK CIKUPA <?php echo $judul_tgl; ?></td></tr>
<?php $erow++; $biaya_row = $erow; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <td colspan="7" align="center" style="<?php echo $cRekap.$bd; ?>">Biaya <?php echo (int)$potong; ?> mobil</td>
        <td colspan="8" align="center" style="<?php echo $cRekap.$bd; ?>">=O<?php echo $total_row; ?></td>
    </tr>
<?php $erow++; $permobil_row = $erow; $sum_row_fwd = $permobil_row + count($boneless_details) + 5; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <td colspan="7" align="center" style="<?php echo $cRekap.$bd; ?>">Biaya permobil</td>
        <td colspan="8" align="center" style="<?php echo $cRekap.$bd; ?>">=E<?php echo $sum_row_fwd; ?></td>
    </tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cRekap.$bd; ?> font-size:18px;">BAYARAN TIM BONLESS</td></tr>
<?php
$Jplus = [];
$Jminus = [];
$Jrows_all = [];
foreach ($boneless_details as $item):
    $erow++;
    $qty = (float)($item['qty'] ?? 0);
    $hrg = abs((float)($item['harga'] ?? 0));
    $is_minus = (($item['jenis'] ?? '') == 'minus');
    $Jrows_all[] = "F$erow";
    if ($is_minus) $Jminus[] = "F$erow"; else $Jplus[] = "F$erow";
    $qty_disp = $qty ? number_format($qty, 0, ',', '.') : '';
?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo count($Jrows_all); ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $is_minus ? 'PENGURANGAN' : ''; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo strtoupper($item['nama_item'] ?? ''); ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $qty_disp; ?></td>
        <td align="left" style="<?php echo $cWhite.$bd; ?>">Rp<?php echo number_format($hrg, 0, ',', '.'); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">=D<?php echo $erow; ?>*E<?php echo $erow; ?></td>
        <td colspan="9" style="<?php echo $cWhite; ?> border:0;"></td>
    </tr>
<?php endforeach; ?>
<?php
$erow++;
$bon_total_row = $erow;
$parts = array_merge($Jplus, array_map(function($x){ return "-$x"; }, $Jminus));
$bon_total_f = $parts ? '=' . implode('', $parts) : '0';
$bon_total_f = ltrim($bon_total_f, '=+');
$bon_total_f = '=' . $bon_total_f;
?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <td colspan="5" align="center" style="<?php echo $cTotal.$bd; ?>">TOTAL</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $bon_total_f; ?></td>
        <td colspan="9" style="<?php echo $cWhite; ?> border:0;"></td>
    </tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cRed.$bd; ?> font-size:18px;">DENGAN MESIN BONLES</td></tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?> background:#FFFF00; font-size:16px;">
        <th style="<?php echo $cYellow.$bd; ?>">BIAYA PABRIK</th><th style="<?php echo $cYellow.$bd; ?>">BONLESS</th><th style="<?php echo $cYellow.$bd; ?>">POTONG</th><th style="<?php echo $cYellow.$bd; ?>">TOTAL</th><th style="<?php echo $cYellow.$bd; ?>">Biaya Per mobil</th>
        <th colspan="10" style="<?php echo $cWhite; ?> border:0;"></th>
    </tr>
<?php
$erow++;
$sum_row = $erow;
// isi permobil forward-ref seperti contoh (=I313)
?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px; text-align:center;">
        <td align="right" style="<?php echo $cWhite.$bd; ?>">=H<?php echo $biaya_row; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">=F<?php echo $bon_total_row; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo (int)$potong; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">=A<?php echo $sum_row; ?>+B<?php echo $sum_row; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">=D<?php echo $sum_row; ?>/C<?php echo $sum_row; ?></td>
        <td colspan="10" style="<?php echo $cWhite; ?> border:0;"></td>
    </tr>
</table>
<table border="0"><tr><td>Tabel di atas memakai rumus Excel (=L-M+N, =SUM, =D*E, =A+B, =D/C) seperti file contoh, jadi ikut hitung otomatis kalau Qty/Harga diedit.</td></tr></table>
<br><br>
<table border="0">
    <tr>
        <td width="30"></td>
        <td style="padding: 20px;">
            <table border="1" style="border-collapse:collapse; border: 4px solid <?php echo $stamp_color; ?>;">
                <tr><td align="center" style="font-size:24px; font-weight:bold; color:<?php echo $stamp_color; ?>; padding:5px 30px;"><?php echo $stamp_text; ?></td></tr>
            </table>
        </td>
    </tr>
</table>
