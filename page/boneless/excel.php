<?php
include "../../koneksi.php";

$id = isset($_GET['id']) ? $_GET['id'] : '';

// 1. Ambil data Header Boneless
$queryHeader = $koneksi->query("SELECT * FROM tb_boneless WHERE id_boneless = '$id'");
$header = $queryHeader->fetch_assoc();
$tgl = $header['tgl'];

// 2. Ambil data Detail Boneless
$queryDetail = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '$id'");

// 3. Ambil Biaya Pabrik (Total Upah Realisasi pada tanggal yang sama)
$queryUpah = $koneksi->query("
        SUM(RD.r_upah + RD.lembur - RD.r_potongan_lainnya - 
            IF(RD.r_jam_masuk > J.shift_masuk AND RD.r_jam_masuk != '00:00:00' AND RD.ra_masuk != '00:00:00', (SELECT denda_masuk FROM tb_denda LIMIT 1), 0) -
            IF(RD.r_istirahat_masuk > J.shift_istirahat_masuk AND RD.r_istirahat_masuk != '00:00:00' AND RD.ra_istirahat_masuk != '00:00:00', (SELECT denda_istirahat FROM tb_denda LIMIT 1), 0)
        ) as total_upah 
    FROM tb_realisasi_detail RD
    LEFT JOIN tb_jadwal J ON RD.id_jadwal = J.id_jadwal
    WHERE RD.tgl_realisasi_detail = '$tgl'
");
$rowUpah = $queryUpah->fetch_assoc();
$biaya_pabrik = $rowUpah['total_upah'] ?? 0;

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Boneless_$tgl.xls");
echo "<meta charset='UTF-8'>";
?>

<table border="1" style="border-collapse:collapse;">
    <thead>
        <tr>
            <th colspan="9" style="background-color:#4f81bd; color:white; text-align:center; font-weight:bold; height:30px; font-size:14px;">
                BAYARAN TIM BONLESS
            </th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        $total_boneless = 0;
        while ($item = $queryDetail->fetch_assoc()) {
            $total_boneless += $item['total'];
            ?>
            <tr>
                <td style="text-align:center; width:40px; font-weight:bold;"><?= $no++ ?></td>
                <td style="width:100px;"></td>
                <td style="width:100px;"></td>
                <td style="width:100px;"></td>
                <td style="width:100px;"></td>
                <td style="width:200px; font-weight:bold;"><?= strtoupper($item['nama_item']) ?></td>
                <td style="text-align:right; width:100px;"><?= number_format($item['qty'], 1, ',', '.') ?></td>
                <td style="text-align:left; width:100px; border-left:none;">Rp<?= number_format($item['harga'], 0, ',', '.') ?></td>
                <td style="text-align:right; width:150px; font-weight:bold;">Rp <?= number_format($item['total'], 0, ',', '.') ?></td>
            </tr>
            <?php
        }
        
        // Ensure at least 7 rows as in the image for padding if needed, but here we just follow the data
        ?>
        <tr style="font-weight:bold;">
            <td colspan="5" style="text-align:center; height:25px;">TOTAL</td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align:right;">Rp <?= number_format($total_boneless, 0, ',', '.') ?></td>
        </tr>
    </tbody>
</table>

<br>

<table border="1" style="border-collapse:collapse;">
    <thead>
        <tr style="background-color:yellow; font-weight:bold; text-align:center;">
            <th style="width:250px; height:25px;">BIAYA PABRIK</th>
            <th style="width:100px;"></th>
            <th style="width:100px;"></th>
            <th style="width:150px;">BONELESS</th>
            <th style="width:150px;">POTONG</th>
            <th style="width:200px;">TOTAL</th>
            <th style="width:250px;">Biaya Per mobil</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $potong = $header['jumlah_mobil'];
        $grand_total = $biaya_pabrik + $total_boneless;
        $biaya_per_mobil = ($potong > 0) ? ($grand_total / $potong) : 0;
        ?>
        <tr style="font-weight:bold; text-align:center; height:30px; font-size:14px;">
            <td style="text-align:right;"><?= number_format($biaya_pabrik, 0, ',', '.') ?></td>
            <td></td>
            <td></td>
            <td style="text-align:right;"><?= number_format($total_boneless, 0, ',', '.') ?></td>
            <td><?= $potong ?></td>
            <td style="text-align:right;"><?= number_format($grand_total, 0, ',', '.') ?></td>
            <td style="text-align:right; background-color:white;">Rp<?= number_format($biaya_per_mobil, 2, ',', '.') ?></td>
        </tr>
    </tbody>
</table>
