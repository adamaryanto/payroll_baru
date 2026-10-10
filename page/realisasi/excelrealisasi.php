<?php
include "../../koneksi.php";

$id = isset($_GET['id']) ? $_GET['id'] : '';

$queryInfo = $koneksi->query("SELECT tgl_realisasi, status_realisasi, jam_kerja FROM tb_realisasi WHERE id_realisasi = '$id'");
$info = $queryInfo->fetch_assoc();
$tanggal_raw = $info ? $info['tgl_realisasi'] : '';
$status_realisasi = $info['status_realisasi'] ?? 0;
$tanggal = $tanggal_raw ? date('d-m-Y', strtotime($tanggal_raw)) : 'TanpaTanggal';
$tanggal_sql = $tanggal_raw ? date('Y-m-d', strtotime($tanggal_raw)) : '';

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Realisasi_Upah_$tanggal.xls");
echo "<meta charset='UTF-8'>";

$is_approved = ($status_realisasi >= 2 || strtolower((string)$status_realisasi) === 'approved' || strtolower((string)$status_realisasi) === 'approve');
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

// Boneless
$q_b = $koneksi->query("SELECT * FROM tb_boneless WHERE tgl = '$tanggal_sql'");
$b_head = $q_b->fetch_assoc();
$potong = (float)($b_head['jumlah_mobil'] ?? 0);
$boneless_details = [];
if ($b_head) {
    $q_bd = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '" . $b_head['id_boneless'] . "' ORDER BY id_boneless_detail ASC");
    while ($bd_row = $q_bd->fetch_assoc()) $boneless_details[] = $bd_row;
}

// Karyawan per departemen
$sqlDept = $koneksi->query("SELECT DISTINCT D.* FROM ms_departmen D
    JOIN tb_realisasi_detail AD ON AD.id_realisasi = '$id'
    LEFT JOIN tb_rkk_detail RD ON AD.id_rkk_detail = RD.id_rkk_detail
    WHERE D.id_departmen = COALESCE(NULLIF(AD.id_departmen, 0), RD.id_departmen)
    ORDER BY D.nama_departmen ASC");

$dept_list = [];
while ($d = $sqlDept->fetch_assoc()) $dept_list[] = $d;

$erow = 0;
$Lrows = [];
$Orows = [];
$rows_per_dept = [];

foreach ($dept_list as $dept) {
    $id_dept = $dept['id_departmen'];
    $tampil = $koneksi->query("SELECT A.*, IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_karyawan,
        O.OS_DHK as label_os, G.golongan as label_gol, S.nama_sub_department as posisi, J.jam_masuk as j_masuk
        FROM tb_realisasi_detail A
        LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
        LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
        LEFT JOIN ms_os_dhk O ON COALESCE(NULLIF(A.id_os_dhk, 0), B.id_os_dhk) = O.id_os_dhk
        LEFT JOIN ms_golongan G ON COALESCE(NULLIF(A.id_golongan, 0), B.id_golongan) = G.id_golongan
        LEFT JOIN ms_sub_department S ON COALESCE(NULLIF(A.id_sub_department, 0), RD.id_sub_department) = S.id_sub_department
        LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal
        WHERE A.id_realisasi = '$id' AND COALESCE(NULLIF(A.id_departmen, 0), RD.id_departmen) = '$id_dept'
        ORDER BY nama_karyawan ASC");
    $rows = [];
    while ($r = $tampil->fetch_assoc()) {
        $pot = ($r['r_potongan_telat'] ?? 0) + ($r['r_potongan_istirahat_awal'] ?? 0) + ($r['r_potongan_istirahat_telat'] ?? 0)
             + ($r['r_potongan_pulang'] ?? 0) + ($r['r_potongan_tidak_lengkap'] ?? 0) + ($r['r_potongan_lainnya'] ?? 0);
        $upah = (float)($r['r_upah'] ?? 0);
        $lembur = (float)($r['lembur'] ?? 0);
        if (($r['r_status'] ?? '') == 'Tidak Hadir') { $upah = 0; $lembur = 0; $pot = 0; }
        $r['_pot'] = $pot;
        $r['_upah'] = $upah;
        $rows[] = $r;
    }
    $rows_per_dept[] = ['dept' => $dept, 'rows' => $rows];
}
?>
<table border="1" style="border-collapse:collapse;">
<?php $erow++; ?>
    <tr><th colspan="15" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px; height:23px;">REALISASI ABSENSI KARYAWAN HARI <?php echo $judul_hari . ' ' . $judul_tgl; ?></th></tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px;">JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000</td></tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px;">MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.</td></tr>
<?php foreach ($rows_per_dept as $grp):
    $dept = $grp['dept'];
    $rows = $grp['rows'];
    $erow++;
?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cSection.$bd; ?> font-size:18px;"><?php echo strtoupper($dept['nama_departmen'] ?? ''); ?></td></tr>
    <?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?>">
        <?php
        foreach (['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK','ISTIRAHAT MASUK','JAM PULANG'] as $h) echo "<th style=\"$fBase$th$cHdr$bd\">$h</th>";
        foreach (['HASIL KERJA','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'] as $h) echo "<th style=\"$fBase$th$cHdrL$bd\">$h</th>";
        ?>
    </tr>
    <?php
    $no = 1;
    foreach ($rows as $data):
        $erow++;
        $Lrows[] = "L$erow";
        $Orows[] = "O$erow";
        $ra_masuk = ($data['ra_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_masuk'] : '';
        $ra_ist = ($data['ra_istirahat_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_istirahat_masuk'] : '';
        $ra_pulang = ($data['ra_keluar'] ?? '00:00:00') != '00:00:00' ? $data['ra_keluar'] : '';
    ?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $no++; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo strtoupper($data['nama_karyawan'] ?? ''); ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $data['label_os'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $data['label_gol'] ?? ''; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"><?php echo $data['posisi'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $data['j_masuk'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $ra_masuk; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $ra_ist; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $ra_pulang; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"><?php echo $data['hasil_kerja'] ?? ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo number_format($data['_upah'], 0, ',', '.'); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $data['_pot'] ? number_format($data['_pot'], 0, ',', '.') : ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $data['lembur'] ? number_format($data['lembur'], 0, ',', '.') : ''; ?></td>
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
<?php $erow++; ?>
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
$bon_total_f = $parts ? '=' . ltrim(implode('', $parts), '+') : '0';
?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <td colspan="5" align="center" style="<?php echo $cTotal.$bd; ?>">TOTAL</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $bon_total_f; ?></td>
        <td colspan="9" style="<?php echo $cWhite; ?> border:0;"></td>
    </tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cRed.$bd; ?> font-size:18px;">DENGAN MESIN BONLES</td></tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?> font-size:16px;">
        <th style="<?php echo $cYellow.$bd; ?>">BIAYA PABRIK</th><th style="<?php echo $cYellow.$bd; ?>">BONLESS</th><th style="<?php echo $cYellow.$bd; ?>">POTONG</th><th style="<?php echo $cYellow.$bd; ?>">TOTAL</th><th style="<?php echo $cYellow.$bd; ?>">Biaya Per mobil</th>
        <th colspan="10" style="<?php echo $cWhite; ?> border:0;"></th>
    </tr>
<?php $erow++; $sum_row = $erow; ?>
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
        <td>
            <table border="1" style="border-collapse:collapse; border: 4px solid <?php echo $stamp_color; ?>;">
                <tr><td align="center" style="font-size:24px; font-weight:bold; color:<?php echo $stamp_color; ?>; padding:5px 30px;"><?php echo $stamp_text; ?></td></tr>
            </table>
        </td>
    </tr>
</table>
