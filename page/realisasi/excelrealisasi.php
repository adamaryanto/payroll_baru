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

// Rincian borongan: kolom kerja terpisah (PISAH) di kanan, per grup bagian
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_realisasi_borongan (
  id_borongan INT AUTO_INCREMENT PRIMARY KEY,
  id_realisasi_detail INT NOT NULL,
  nama_pekerjaan VARCHAR(100) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  tarif INT NOT NULL DEFAULT 0,
  KEY idx_detail (id_realisasi_detail)
) ENGINE=InnoDB");
$bor_map = [];
$qb = $koneksi->query("SELECT b.* FROM tb_realisasi_borongan b JOIN tb_realisasi_detail d ON b.id_realisasi_detail = d.id_realisasi_detail WHERE d.id_realisasi = '$id' ORDER BY b.id_borongan ASC");
while ($qb && ($rb = $qb->fetch_assoc())) {
    $nm = strtoupper(trim($rb['nama_pekerjaan']));
    if ($nm === '') continue;
    $bor_map[$rb['id_realisasi_detail']][] = ['nama' => $nm, 'qty' => (float)$rb['qty'], 'tarif' => (int)$rb['tarif']];
}
$cBor = "background:#EDE9FE;";
$total_cols = 16;
function ex_qty($v) {
    $v = (float)$v;
    if (abs($v - round($v)) < 1e-9) return (string)(int)round($v);
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
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
        $r['_lembur'] = $lembur;
        $rows[] = $r;
    }
    // Pekerjaan borongan khas grup ini (maks 5 pasang kolom, seluruhnya huruf tunggal P..Y)
    $sec_jobs = [];
    foreach ($rows as $rr) {
        foreach ($bor_map[$rr['id_realisasi_detail']] ?? [] as $bln) {
            if (!in_array($bln['nama'], $sec_jobs)) $sec_jobs[] = $bln['nama'];
        }
    }
    $sec_jobs = array_slice($sec_jobs, 0, 5);
    // Upah ikut total borongan (hanya pekerjaan yang tampil)
    foreach ($rows as &$rr) {
        $bt = 0;
        foreach ($bor_map[$rr['id_realisasi_detail']] ?? [] as $bln) {
            if (in_array($bln['nama'], $sec_jobs)) $bt += (int)round((float)$bln['qty'] * (int)$bln['tarif']);
        }
        if ($bt > 0) { $rr['_upah'] = $bt; $rr['_lembur'] = 0; $rr['_pot'] = 0; }
    }
    unset($rr);
    $rows_per_dept[] = ['dept' => $dept, 'rows' => $rows, 'jobs' => $sec_jobs];
}
?>
<table border="1" style="border-collapse:collapse;">
<?php
// Lebar kolom ikut file contoh (satuan px)
$col_w = [67,358,168,87,138,164,215,148,120,143,260,131,170,131,116,166];
echo '<colgroup>';
foreach ($col_w as $w) echo "<col width=\"$w\">";
echo '</colgroup>';
?>
<?php $erow++; ?>
    <tr><th colspan="<?php echo $total_cols; ?>" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px; height:23px;">REALISASI ABSENSI KARYAWAN HARI <?php echo $judul_hari . ' ' . $judul_tgl; ?></th></tr>
<?php $erow++; ?>
    <tr><td colspan="<?php echo $total_cols; ?>" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px;">JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000</td></tr>
<?php $erow++; ?>
    <tr><td colspan="<?php echo $total_cols; ?>" style="<?php echo $fBase.$th.$cWhite.$bd; ?> font-size:18px;">MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.</td></tr>
<?php foreach ($rows_per_dept as $grp):
    $dept = $grp['dept'];
    $rows = $grp['rows'];
    $sec_jobs = $grp['jobs'];
    $erow++;
?>
    <tr><td colspan="<?php echo $total_cols; ?>" style="<?php echo $fBase.$th.$cSection.$bd; ?> font-size:18px;"><?php echo strtoupper($dept['nama_departmen'] ?? ''); ?></td></tr>
    <?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?>">
        <?php
        $hdr1 = [['No',67],['NAMA SESUAI KTP',358],['PARAF PEKERJA',168],['OS/DHK',87],['GOLONGAN',138],['POSISI',164],['JAM KERJA',215],['JAM MASUK',148],['ISTIRAHAT MASUK',120],['JAM PULANG',143]];
        foreach ($hdr1 as $h) echo "<th width=\"{$h[1]}\" style=\"$fBase$th$cHdr$bd\">{$h[0]}</th>";
        foreach ([['DETAIL PEKERJAAN',260]] as $h) echo "<th width=\"{$h[1]}\" style=\"$fBase$th$cBor$bd\">{$h[0]}</th>";
        $hdr2 = [['HASIL KERJA',131],['UPAH',170],['POTONGAN',131],['LEMBUR',116],['UPAH DIBAYAR',166]];
        foreach ($hdr2 as $h) echo "<th width=\"{$h[1]}\" style=\"$fBase$th$cHdrL$bd\">{$h[0]}</th>";
        foreach ($sec_jobs as $sj) echo "<th colspan=\"2\" style=\"$fBase$th$cBor$bd\">$sj</th>";
        ?>
    </tr>
    <?php
    $no = 1;
    foreach ($rows as $data):
        $erow++;
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
        <?php
        $my_lines = [];
        if (!empty($sec_jobs)) {
            foreach ($bor_map[$data['id_realisasi_detail']] ?? [] as $bln) {
                if (in_array($bln['nama'], $sec_jobs)) $my_lines[$bln['nama']] = $bln;
            }
        }
        $has_bor = !empty($sec_jobs) && !empty($my_lines);
        $k_sum = 0;
        $l_sum = 0;
        foreach ($my_lines as $ln) {
            $k_sum += (float)$ln['qty'];
            $l_sum += (int)round((float)$ln['qty'] * (int)$ln['tarif']);
        }
        $det_parts = [];
        foreach ($my_lines as $jn => $ln) {
            $lt0 = (int)round((float)$ln['qty'] * (int)$ln['tarif']);
            $det_parts[] = $jn . ' : ' . ex_qty($ln['qty']) . ' kg x ' . number_format($ln['tarif'], 0, ',', '.') . ' = Rp' . number_format($lt0, 0, ',', '.');
        }
        ?>
        <td style="<?php echo $cWhite.$bd; ?>"><?php echo implode('<br>', $det_parts); ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"><?php
            if ($has_bor) echo ex_qty($k_sum);
            elseif (empty($sec_jobs)) echo $data['hasil_kerja'] ?? '';
        ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php
            if ($has_bor) echo number_format($l_sum, 0, ',', '.');
            else echo number_format($data['_upah'], 0, ',', '.');
        ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $data['_pot'] ? number_format($data['_pot'], 0, ',', '.') : ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $data['_lembur'] ? number_format($data['_lembur'], 0, ',', '.') : ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo number_format($data['_upah'] - $data['_pot'] + $data['_lembur'], 0, ',', '.'); ?></td>
        <?php
        if (!empty($sec_jobs)) {
            foreach ($sec_jobs as $jn) {
                $ln = $my_lines[$jn] ?? null;
                if ($ln) {
                    echo '<td align="center" style="' . $cWhite . $bd . '">' . ex_qty($ln['qty']) . '</td>';
                    echo '<td align="right" style="' . $cWhite . $bd . '">' . number_format((int)round((float)$ln['qty'] * (int)$ln['tarif']), 0, ',', '.') . '</td>';
                } else {
                    echo '<td style="' . $cWhite . $bd . '"></td><td style="' . $cWhite . $bd . '"></td>';
                }
            }
        }
        ?>
    </tr>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php
$erow++;
$total_row = $erow;
// Total paten (angka, bukan rumus) biar langsung kebaca
$tot_upah = 0;
$tot_dibayar = 0;
foreach ($rows_per_dept as $gt) {
    foreach ($gt['rows'] as $rr) {
        $tot_upah += $rr['_upah'];
        $tot_dibayar += ($rr['_upah'] - $rr['_pot'] + $rr['_lembur']);
    }
}
?>
    <tr style="<?php echo $fBase; ?> font-weight:bold;">
        <td colspan="12" align="center" style="<?php echo $cWhite.$bd; ?>">TOTAL</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo number_format($tot_upah, 0, ',', '.'); ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td><td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo number_format($tot_dibayar, 0, ',', '.'); ?></td>
    </tr>
<?php
// Nilai paten footer (angka, bukan rumus) — seperti file contoh
$val_pabrik = $tot_dibayar;
$val_bon_net = 0;
foreach ($boneless_details as $bd0) {
    $ln0 = (float)($bd0['qty'] ?? 0) * abs((float)($bd0['harga'] ?? 0));
    $val_bon_net += (($bd0['jenis'] ?? '') == 'minus') ? -$ln0 : $ln0;
}
$val_grand = $val_pabrik + $val_bon_net;
$val_permobil = ($potong > 0) ? $val_grand / $potong : 0;
function ex_rp2($v) { return number_format((float)$v, 2, ',', '.'); }
$erow++;
?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cRekap.$bd; ?> font-size:18px;">REALISASI TOTAL REKAP BIAYA PABRIK CIKUPA <?php echo $judul_tgl; ?></td></tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <td colspan="7" align="center" style="<?php echo $cRekap.$bd; ?>">Biaya <?php echo (int)$potong; ?> mobil</td>
        <td align="center" style="<?php echo $cRekap.$bd; ?>">Rp</td>
        <td colspan="7" align="right" style="<?php echo $cRekap.$bd; ?>"><?php echo ex_rp2($val_pabrik); ?></td>
    </tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <td colspan="7" align="center" style="<?php echo $cRekap.$bd; ?>">Biaya permobil</td>
        <td align="center" style="<?php echo $cRekap.$bd; ?>">Rp</td>
        <td colspan="7" align="right" style="<?php echo $cRekap.$bd; ?>"><?php echo ex_rp2($val_permobil); ?></td>
    </tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cRekap.$bd; ?> font-size:18px;">BAYARAN TIM BONLESS</td></tr>
<?php
$nb = 0;
foreach ($boneless_details as $item):
    $erow++; $nb++;
    $qty = (float)($item['qty'] ?? 0);
    $hrg = abs((float)($item['harga'] ?? 0));
    $is_minus = (($item['jenis'] ?? '') == 'minus');
    $line = $qty * $hrg;
?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $nb; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $is_minus ? 'PENGURANGAN' : ''; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo strtoupper($item['nama_item'] ?? ''); ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $qty ? number_format($qty, 0, ',', '.') : ''; ?></td>
        <td align="left" style="<?php echo $cWhite.$bd; ?>">Rp<?php echo number_format($hrg, 0, ',', '.'); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp <?php echo number_format($line, 0, ',', '.'); ?></td>
        <td colspan="7" style="<?php echo $cWhite; ?> border:0;"></td>
    </tr>
<?php endforeach; ?>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <td colspan="7" align="center" style="<?php echo $cTotal.$bd; ?>">TOTAL</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp <?php echo number_format($val_bon_net, 0, ',', '.'); ?></td>
        <td colspan="7" style="<?php echo $cWhite; ?> border:0;"></td>
    </tr>
<?php $erow++; ?>
    <tr><td colspan="15" style="<?php echo $fBase.$th.$cRed.$bd; ?> font-size:18px;">DENGAN MESIN BONLES</td></tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?> font-size:16px;">
        <th colspan="3" style="<?php echo $cYellow.$bd; ?>">BIAYA PABRIK</th><th style="<?php echo $cYellow.$bd; ?>">BONLESS</th><th style="<?php echo $cYellow.$bd; ?>">POTONG</th><th style="<?php echo $cYellow.$bd; ?>">TOTAL</th><th style="<?php echo $cYellow.$bd; ?>">Biaya Per mobil</th>
        <th colspan="8" style="<?php echo $cWhite; ?> border:0;"></th>
    </tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px; text-align:center;">
        <td colspan="3" align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo ex_rp2($val_pabrik); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo ex_rp2($val_bon_net); ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo (int)$potong; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo ex_rp2($val_grand); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp<?php echo ex_rp2($val_permobil); ?></td>
        <td colspan="8" style="<?php echo $cWhite; ?> border:0;"></td>
    </tr>
</table>
<table border="0"><tr><td>Kolom Upah Dibayar memakai rumus (upah-potongan+lembur) otomatis; rekap bawah angka paten.</td></tr></table>
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
