<?php
include "../../koneksi.php";

$id = isset($_GET['id']) ? $_GET['id'] : '';

$queryHeader = $koneksi->query("SELECT * FROM tb_boneless WHERE id_boneless = '$id'");
$header = $queryHeader->fetch_assoc();
$tgl = $header['tgl'] ?? date('Y-m-d');
$tgl_file = $tgl ? date('d-m-Y', strtotime($tgl)) : 'TanpaTanggal';

$queryDetail = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '$id' ORDER BY id_boneless_detail ASC");
$details = [];
while ($r = $queryDetail->fetch_assoc()) $details[] = $r;

$plus = array_values(array_filter($details, function($x){ return ($x['jenis'] ?? '') != 'minus'; }));
$item1 = $plus[0] ?? ['nama_item' => 'DADA SP GACOAN', 'qty' => 0, 'harga' => 300];
$item2 = $plus[1] ?? ['nama_item' => 'BONELESS', 'qty' => 1680, 'harga' => 800];

$f_name = strtoupper($item1['nama_item']);
$f_qty = (float)($item1['qty'] ?? 0);
$f_hrg = abs((float)($item1['harga'] ?? 0));
$h_name = strtoupper($item2['nama_item']);
$h_qty = (float)($item2['qty'] ?? 0);
$h_hrg = abs((float)($item2['harga'] ?? 0));

$fmt = function($v){ return $v ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '0'; };

$nama_default = ['MULYANTI','EDAH','YUSI HASTUTIK','RUSDIANA','HARNI','CICHA MARYANA','SITI PATONAH','RUTINAH','TIARA VETI FEBRIANA'];

$bd = "border:1px solid #000;";
$fBase = "font-family:Calibri,sans-serif; font-size:18px;";
$th = "font-weight:bold; text-align:center; vertical-align:middle;";
$cWhite = "background:#FFFFFF;";
$cSection = "background:#B2A1C6;";
$cHdr = "background:#D9D9D9;";
$cHdrL = "background:#EFEFEF;";

// Karyawan satuan yang sudah disimpan (kalau ada, itu yang ditampilkan)
$q_sat = $koneksi->query("SELECT * FROM tb_boneless_satuan WHERE id_boneless = '$id' ORDER BY id_satuan ASC");
$satuan = [];
if ($q_sat) while ($r = $q_sat->fetch_assoc()) $satuan[] = $r;
$hrg_sat_saved = isset($header['harga_satuan']) ? $header['harga_satuan'] : null;

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Boneless_Baru_$tgl_file.xls");
echo "<meta charset='UTF-8'>";
?>
<?php if (!empty($satuan)): ?>
<table border="1" style="border-collapse:collapse;">
    <tr><th colspan="4" style="<?php echo $fBase.$th.$cSection.$bd; ?> height:23px;">BONELESS BARU</th></tr>
    <tr style="<?php echo $fBase.$th; ?>">
        <th style="<?php echo $cHdr.$bd; ?>">No</th>
        <th style="<?php echo $cHdr.$bd; ?>">NAMA SESUAI KTP</th>
        <th style="<?php echo $cHdrL.$bd; ?>">HASIL KERJA</th>
        <th style="<?php echo $cHdrL.$bd; ?>">UPAH DIBAYAR</th>
    </tr>
    <?php $sn = 1; $sat_tot = 0; $sat_allkg = true; foreach ($satuan as $sr): $sat_tot += (int)$sr['upah']; $sr_u = strtoupper(trim($sr['satuan'] ?? 'KG')); if ($sr_u !== '' && $sr_u !== 'KG') $sat_allkg = false; ?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?= $sn++ ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"><?= htmlspecialchars(strtoupper($sr['nama'])) ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?= rtrim(rtrim(number_format((float)$sr['hasil'], 2, '.', ''), '0'), '.') . ' ' . ($sr_u !== '' ? ucfirst(strtolower($sr_u)) : 'Kg') ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp <?= number_format($sr['upah'], 0, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="<?php echo $fBase; ?>">
        <td colspan="3" align="center" style="<?php echo $cWhite.$bd; ?>">TOTAL (<?= count($satuan) ?> orang<?= ($sat_allkg && $hrg_sat_saved !== null && $hrg_sat_saved !== '') ? ', Rp ' . number_format($hrg_sat_saved, 0, ',', '.') . '/kg' : '' ?>)</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp <?= number_format($sat_tot, 0, ',', '.') ?></td>
    </tr>
</table>
<?php else: ?>
<table border="1" style="border-collapse:collapse;">
    <tr><th colspan="4" style="<?php echo $fBase.$th.$cSection.$bd; ?> font-size:18px; height:23px;">BONELESS BARU</th><td style="border:0;"></td><td style="border:0;"></td><td style="border:0;"></td><td style="border:0;"></td><td style="border:0;"></td></tr>
    <tr style="<?php echo $fBase.$th; ?>">
        <th style="<?php echo $cHdr.$bd; ?>">No</th>
        <th style="<?php echo $cHdr.$bd; ?>">NAMA SESUAI KTP</th>
        <th style="<?php echo $cHdrL.$bd; ?>">HASIL KERJA</th>
        <th style="<?php echo $cHdrL.$bd; ?>">UPAH DIBAYAR</th>
        <th style="border:0;"></th>
        <th style="border:0; font-weight:bold;"><?php echo $f_name; ?></th>
        <th style="border:0;"></th>
        <th style="border:0; font-weight:bold; text-align:center;"><?php echo $h_name; ?></th>
        <th style="border:0;"></th>
    </tr>
    <?php
    foreach ($nama_default as $idx => $nm):
        $is_first = ($idx === 0);
        $a_val = $is_first ? '1' : '=+A' . (5 + $idx) . '+1';
        $c_val = $is_first ? $fmt($h_qty) : '';
        $f_val = $is_first ? $fmt($f_qty) : '';
        $g_val = $is_first ? '=+F6*' . $fmt($f_hrg) . '/6' : '';
        $h_val = $is_first ? $fmt($h_qty) : '';
        $i_val = $is_first ? '=+H6*' . $fmt($h_hrg) . '/9' : '';
    ?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $a_val; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $nm; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $c_val; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;">=+$G$6+$I$6</td>
        <td style="border:0;"></td>
        <td align="center" style="border:0;"><?php echo $f_val; ?></td>
        <td align="right" style="border:0;"><?php echo $g_val; ?></td>
        <td align="center" style="border:0;"><?php echo $h_val; ?></td>
        <td align="right" style="border:0;"><?php echo $i_val; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
