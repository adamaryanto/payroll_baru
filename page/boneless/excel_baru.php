<?php
// Download Excel KHUSUS Boneless Baru (tabel satuan saja, terpisah dari boneless lain).
// Layout menyamai sheet BONELESS BARU file referensi:
//   Judul | No | NAMA SESUAI KTP | HASIL KERJA | UPAH DIBAYAR | TOTAL
include "../../koneksi.php";

$id = isset($_GET['id']) ? $_GET['id'] : '';

$queryHeader = $koneksi->query("SELECT * FROM tb_boneless WHERE id_boneless = '$id'");
$header = $queryHeader ? $queryHeader->fetch_assoc() : null;
if (!$header) { echo "Data boneless tidak ditemukan."; exit; }
$tgl = $header['tgl'] ?? date('Y-m-d');
$tgl_file = $tgl ? date('d-m-Y', strtotime($tgl)) : 'TanpaTanggal';

$bd = "border:1px solid #000;";
$fBase = "font-family:Calibri,sans-serif; font-size:18px;";
$th = "font-weight:bold; text-align:center; vertical-align:middle;";
$cWhite = "background:#FFFFFF;";
$cSection = "background:#B2A1C6;";
$cHdr = "background:#D9D9D9;";
$cHdrL = "background:#EFEFEF;";

// Karyawan satuan yang sudah disimpan
$q_sat = $koneksi->query("SELECT * FROM tb_boneless_satuan WHERE id_boneless = '$id' ORDER BY id_satuan ASC");
$satuan = [];
if ($q_sat) while ($r = $q_sat->fetch_assoc()) $satuan[] = $r;
$hrg_sat_saved = isset($header['harga_satuan']) ? $header['harga_satuan'] : null;

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Boneless_Baru_$tgl_file.xls");
echo "<meta charset='UTF-8'>";
?>
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
