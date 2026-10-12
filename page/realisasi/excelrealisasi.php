<?php
include "../../koneksi.php";
include_once __DIR__ . '/../grup_borongan/_setup.php'; // pastikan ms_grup_borongan + tb_realisasi_detail.id_grup ada

$id = isset($_GET['id']) ? $_GET['id'] : '';

$queryInfo = $koneksi->query("SELECT tgl_realisasi, status_realisasi, jam_kerja, id_rkk FROM tb_realisasi WHERE id_realisasi = '$id'");
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

// Rincian borongan per orang (masuk ke HASIL KERJA / UPAH, tanpa kolom tambahan)
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
$total_cols = 15;
$erow = 0;
function ex_qty($v) {
    $v = (float)$v;
    if (abs($v - round($v)) < 1e-9) return (string)(int)round($v);
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

// Karyawan per GRUP BORONGAN (konek ke page=grup_borongan via tb_realisasi_detail.id_grup)
$tampil = $koneksi->query("SELECT A.*, IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_karyawan,
    O.OS_DHK as label_os, G.golongan as label_gol, S.nama_sub_department as posisi, J.jam_masuk as j_masuk,
    GB.nama_grup as grup,
    PG.nama_karyawan as pengganti_nama
    FROM tb_realisasi_detail A
    LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
    LEFT JOIN ms_karyawan PG ON A.id_pengganti = PG.id_karyawan
    LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
    LEFT JOIN ms_os_dhk O ON COALESCE(NULLIF(A.id_os_dhk, 0), B.id_os_dhk) = O.id_os_dhk
    LEFT JOIN ms_golongan G ON COALESCE(NULLIF(A.id_golongan, 0), B.id_golongan) = G.id_golongan
    LEFT JOIN ms_sub_department S ON COALESCE(NULLIF(A.id_sub_department, 0), RD.id_sub_department) = S.id_sub_department
    LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal
    LEFT JOIN ms_grup_borongan GB ON A.id_grup = GB.id_grup
    WHERE A.id_realisasi = '$id'
    ORDER BY (GB.nama_grup IS NULL), GB.nama_grup ASC, nama_karyawan ASC");

$rows_per_grup = [];
while ($r = $tampil->fetch_assoc()) {
    $pot = ($r['r_potongan_telat'] ?? 0) + ($r['r_potongan_istirahat_awal'] ?? 0) + ($r['r_potongan_istirahat_telat'] ?? 0)
         + ($r['r_potongan_pulang'] ?? 0) + ($r['r_potongan_tidak_lengkap'] ?? 0) + ($r['r_potongan_lainnya'] ?? 0);
    $upah = (float)($r['r_upah'] ?? 0);
    $lembur = (float)($r['lembur'] ?? 0);
    if (($r['r_status'] ?? '') == 'Tidak Hadir') { $upah = 0; $lembur = 0; $pot = 0; }
    // Upah ikut total borongan orang ini (muncul di HASIL KERJA / UPAH)
    $bt = 0; $k_sum = 0;
    foreach ($bor_map[$r['id_realisasi_detail']] ?? [] as $bln) {
        $bt += (int)round((float)$bln['qty'] * (int)$bln['tarif']);
        $k_sum += (float)$bln['qty'];
    }
    if ($bt > 0) { $upah = $bt; $lembur = 0; $pot = 0; $r['_bor_kg'] = $k_sum; }
    $r['_pot'] = $pot;
    $r['_upah'] = $upah;
    $r['_lembur'] = $lembur;
    $key = !empty($r['grup']) ? strtoupper(trim($r['grup'])) : 'TANPA GRUP';
    if (!isset($rows_per_grup[$key])) $rows_per_grup[$key] = [];
    $rows_per_grup[$key][] = $r;
}

// ===== KARYAWAN RENCANA TIDAK MASUK (rencana RKK vs tap mesin) =====
// Cara tahu (mirip panel "tidak absen" di tarik-data): karyawan ada di rencana (RKK)
// tapi tidak ada tap-nya di tb_record pada tanggal ini + belum ada di realisasi ini.
$tidak_masuk = [];
$id_rkk_link = (int)($info['id_rkk'] ?? 0);
if ($tanggal_sql !== '' && $id_rkk_link > 0) {
    $taps = [];
    $qt = $koneksi->query("SELECT DISTINCT TRIM(userid) AS pin FROM tb_record WHERE tgl='$tanggal_sql' AND TRIM(userid)<>''");
    while ($qt && ($rt = $qt->fetch_assoc())) $taps[trim((string)$rt['pin'])] = true;
    // PIN yang sudah ada di realisasi ini (biar tidak dobel dengan tabel utama)
    $sudah = [];
    $qs = $koneksi->query("SELECT DISTINCT TRIM(K.no_absen) AS pin FROM tb_realisasi_detail RD JOIN ms_karyawan K ON K.id_karyawan=RD.id_karyawan WHERE RD.id_realisasi='$id' AND RD.id_karyawan<>0");
    while ($qs && ($rs = $qs->fetch_assoc())) { $p = trim((string)$rs['pin']); if ($p !== '') $sudah[$p] = true; }
    $qr = $koneksi->query("SELECT TRIM(K.no_absen) AS pin, K.nama_karyawan, O.OS_DHK AS oslbl, G.golongan AS gollbl, S.nama_sub_department AS posisi, J.jam_masuk AS sm FROM tb_rkk_detail RD JOIN ms_karyawan K ON K.id_karyawan=RD.id_karyawan LEFT JOIN ms_os_dhk O ON O.id_os_dhk=K.id_os_dhk LEFT JOIN ms_golongan G ON G.id_golongan=K.id_golongan LEFT JOIN ms_sub_department S ON S.id_sub_department=K.id_sub_department LEFT JOIN tb_jadwal J ON J.id_jadwal=RD.id_jadwal WHERE RD.id_rkk='$id_rkk_link' AND RD.id_karyawan<>0 ORDER BY K.nama_karyawan ASC");
    while ($qr && ($rr = $qr->fetch_assoc())) {
        $p = trim((string)($rr['pin'] ?? ''));
        if ($p === '' || isset($taps[$p]) || isset($sudah[$p])) continue;
        $tidak_masuk[] = $rr;
    }
}
?>
<table border="1" style="border-collapse:collapse;">
<?php
// Lebar kolom ikut file contoh (satuan px)
$col_w = [67,358,168,87,138,164,215,148,120,143,131,140,170,131,116,166];
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
<?php foreach ($rows_per_grup as $nama_grup => $rows):
    $erow++;
?>
    <tr><td colspan="<?php echo $total_cols; ?>" style="<?php echo $fBase.$th.$cSection.$bd; ?> font-size:18px;"><?php echo $nama_grup; ?></td></tr>
    <?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?>">
        <?php
        foreach (['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK'] as $h) echo "<th rowspan=\"2\" style=\"$fBase$th$cWhite$bd\">$h</th>";
        echo "<th style=\"$fBase$th$cWhite$bd\">ISTIRAHAT</th>";
        echo "<th rowspan=\"2\" style=\"$fBase$th$cWhite$bd\">JAM PULANG</th>";
        foreach (['HASIL KERJA (kg)','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'] as $h) echo "<th rowspan=\"2\" style=\"$fBase$th$cHdrL$bd\">$h</th>";
        ?>
    </tr>
    <?php $erow++; ?>
    <tr><th style="<?php echo $fBase.$th.$cWhite.$bd; ?>">MASUK</th></tr>
    <?php
    $no = 1;
    foreach ($rows as $data):
        $erow++;
        $ra_masuk = ($data['ra_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_masuk'] : '';
        $ra_ist = ($data['ra_istirahat_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_istirahat_masuk'] : '';
        $ra_pulang = ($data['ra_keluar'] ?? '00:00:00') != '00:00:00' ? $data['ra_keluar'] : '';
        $hasil = isset($data['_bor_kg']) ? ex_qty($data['_bor_kg']) : ($data['hasil_kerja'] ?? '');
    ?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $no++; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo strtoupper($data['nama_karyawan'] ?? '') . (!empty($data['pengganti_nama']) ? ' (Digantikan oleh ' . $data['pengganti_nama'] . ')' : ''); ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $data['label_os'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $data['label_gol'] ?? ''; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"><?php echo $data['posisi'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $data['j_masuk'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $ra_masuk; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $ra_ist; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $ra_pulang; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $hasil ? $hasil . ' kg' : ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo number_format($data['_upah'], 0, ',', '.'); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $data['_pot'] ? number_format($data['_pot'], 0, ',', '.') : ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo $data['_lembur'] ? number_format($data['_lembur'], 0, ',', '.') : ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo number_format($data['_upah'] - $data['_pot'] + $data['_lembur'], 0, ',', '.'); ?></td>
    </tr>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php
$erow++;
// Total paten (angka, bukan rumus) biar langsung kebaca — posisi persis contoh (kolom UPAH & UPAH DIBAYAR)
$tot_upah = 0;
$tot_dibayar = 0;
foreach ($rows_per_grup as $gt) {
    foreach ($gt as $rr) {
        $tot_upah += $rr['_upah'];
        $tot_dibayar += ($rr['_upah'] - $rr['_pot'] + $rr['_lembur']);
    }
}
?>
    <tr style="<?php echo $fBase; ?>">
        <?php echo str_repeat("<td style=\"$cWhite$bd\"></td>", 11); ?>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo number_format($tot_upah, 0, ',', '.'); ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td><td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo number_format($tot_dibayar, 0, ',', '.'); ?></td>
    </tr>
<?php if (!empty($tidak_masuk)): $erow++; ?>
    <tr><td colspan="<?php echo $total_cols; ?>" style="<?php echo $fBase.$th.$cRed.$bd; ?> font-size:18px;">KARYAWAN RENCANA TIDAK MASUK (TIDAK ADA TAP MESIN TGL <?php echo $tanggal; ?>)</td></tr>
    <?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?>">
        <?php
        foreach (['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK'] as $h) echo "<th rowspan=\"2\" style=\"$fBase$th$cWhite$bd\">$h</th>";
        echo "<th style=\"$fBase$th$cWhite$bd\">ISTIRAHAT</th>";
        echo "<th rowspan=\"2\" style=\"$fBase$th$cWhite$bd\">JAM PULANG</th>";
        foreach (['HASIL KERJA (kg)','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'] as $h) echo "<th rowspan=\"2\" style=\"$fBase$th$cHdrL$bd\">$h</th>";
        ?>
    </tr>
    <?php $erow++; ?>
    <tr><th style="<?php echo $fBase.$th.$cWhite.$bd; ?>">MASUK</th></tr>
    <?php $ntm = 1; foreach ($tidak_masuk as $tm): $erow++; ?>
    <tr style="<?php echo $fBase; ?>">
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $ntm++; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo strtoupper($tm['nama_karyawan'] ?? '') . ' (TIDAK MASUK)'; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $tm['oslbl'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $tm['gollbl'] ?? ''; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"><?php echo $tm['posisi'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $tm['sm'] ?? ''; ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;">0</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?> font-weight:bold;">0</td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
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
function ex_blank($n) { return str_repeat("<td style='border:0;'></td>", $n); }
$erow++;
?>
    <tr><?php echo ex_blank(2); ?><td colspan="7" align="center" style="<?php echo $fBase.$th.$cRekap.$bd; ?> font-size:18px;">REALISASI TOTAL REKAP BIAYA PABRIK CIKUPA <?php echo $judul_tgl; ?></td><?php echo ex_blank(6); ?></tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <?php echo ex_blank(2); ?>
        <td colspan="5" align="center" style="<?php echo $cRekap.$bd; ?>">Biaya <?php echo (int)$potong; ?> mobil</td>
        <td colspan="2" align="right" style="<?php echo $cRekap.$bd; ?>"><?php echo ex_rp2($val_pabrik); ?></td>
        <?php echo ex_blank(6); ?>
    </tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <?php echo ex_blank(2); ?>
        <td colspan="5" align="center" style="<?php echo $cRekap.$bd; ?>">Biaya permobil</td>
        <td colspan="2" align="right" style="<?php echo $cRekap.$bd; ?>"><?php echo ex_rp2($val_permobil); ?></td>
        <?php echo ex_blank(6); ?>
    </tr>
<?php $erow++; ?>
    <tr><?php echo ex_blank(15); ?></tr>
<?php $erow++; ?>
    <tr><?php echo ex_blank(2); ?><td colspan="8" align="center" style="<?php echo $fBase.$th.$cRekap.$bd; ?> font-size:18px;">BAYARAN TIM BONLESS</td><?php echo ex_blank(5); ?></tr>
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
        <?php echo ex_blank(2); ?>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $nb; ?></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td style="<?php echo $cWhite.$bd; ?>"></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo $is_minus ? 'PENGURANGAN' : ''; ?></td>
        <td style="<?php echo $cWhite.$bd; ?> font-weight:bold;"><?php echo strtoupper($item['nama_item'] ?? ''); ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo $qty ? number_format($qty, 0, ',', '.') : ''; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp<?php echo number_format($hrg, 0, ',', '.'); ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp <?php echo number_format($line, 0, ',', '.'); ?></td>
        <?php echo ex_blank(5); ?>
    </tr>
<?php endforeach; ?>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <?php echo ex_blank(2); ?>
        <td colspan="7" align="center" style="<?php echo $cTotal.$bd; ?>">TOTAL</td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>">Rp <?php echo number_format($val_bon_net, 0, ',', '.'); ?></td>
        <?php echo ex_blank(5); ?>
    </tr>
<?php $erow++; ?>
    <tr><?php echo ex_blank(2); ?><td colspan="8" align="center" style="<?php echo $fBase.$th.$cRed.$bd; ?> font-size:18px;">DENGAN MESIN BONLES</td><?php echo ex_blank(5); ?></tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase.$th; ?> font-size:16px;">
        <?php echo ex_blank(2); ?>
        <td style="<?php echo $cYellow.$bd; ?>">BIAYA PABRIK</td>
        <td style="<?php echo $cWhite; ?> border:0;"></td>
        <td style="<?php echo $cWhite; ?> border:0;"></td>
        <td style="<?php echo $cYellow.$bd; ?>">BONLESS</td>
        <td style="<?php echo $cYellow.$bd; ?>">POTONG</td>
        <td style="<?php echo $cYellow.$bd; ?>">TOTAL</td>
        <td colspan="2" style="<?php echo $cYellow.$bd; ?>">Biaya Per mobil</td>
        <?php echo ex_blank(5); ?>
    </tr>
<?php $erow++; ?>
    <tr style="<?php echo $fBase; ?> font-weight:bold; font-size:16px;">
        <?php echo ex_blank(2); ?>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo ex_rp2($val_pabrik); ?></td>
        <td style="<?php echo $cWhite; ?> border:0;"></td>
        <td style="<?php echo $cWhite; ?> border:0;"></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo ex_rp2($val_bon_net); ?></td>
        <td align="center" style="<?php echo $cWhite.$bd; ?>"><?php echo (int)$potong; ?></td>
        <td align="right" style="<?php echo $cWhite.$bd; ?>"><?php echo ex_rp2($val_grand); ?></td>
        <td colspan="2" align="right" style="<?php echo $cWhite.$bd; ?>">Rp<?php echo ex_rp2($val_permobil); ?></td>
        <?php echo ex_blank(5); ?>
    </tr>
</table>
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
