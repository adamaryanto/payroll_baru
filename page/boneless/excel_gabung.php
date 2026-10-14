<?php
// Export gabungan 1 file .xlsx 2 SHEETS beneran (MiniXlsx), layout ikut file acuan:
//   Sheet 1 "BONELESS"      : rekap pabrik + bayaran tim bonless + ringkasan (kaki file PERENCANAAN)
//   Sheet 2 "BONELESS BARU" : tabel karyawan satuan
include "../../koneksi.php";
require_once __DIR__ . '/../realisasi/xlsx_mini.php';

$id = isset($_GET['id']) ? $_GET['id'] : '';

$queryHeader = $koneksi->query("SELECT * FROM tb_boneless WHERE id_boneless = '$id'");
$header = $queryHeader ? $queryHeader->fetch_assoc() : null;
if (!$header) { echo "Data boneless tidak ditemukan."; exit; }
$tgl = $header['tgl'] ?? date('Y-m-d');
$tgl_file = $tgl ? date('d-m-Y', strtotime($tgl)) : 'TanpaTanggal';
$jumlah_mobil = (int)($header['jumlah_mobil'] ?? 0);
$id_rkk = (int)($header['id_rkk'] ?? 0);

$hari_arr = ['MINGGU','SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU'];
$bulan_arr = ['','JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
$ts = $tgl ? strtotime($tgl) : time();
$judul_tgl = date('d', $ts) . ' ' . $bulan_arr[(int)date('n', $ts)] . ' ' . date('Y', $ts);

// Total upah pabrik dari RKK (sama kayak list RKK)
$val_pabrik = 0;
if ($id_rkk > 0) {
    $qp = $koneksi->query("SELECT SUM(upah) AS t FROM tb_rkk_detail WHERE id_rkk = '$id_rkk' AND status_rkk != 'Digantikan'");
    if ($qp && ($rp = $qp->fetch_assoc())) $val_pabrik = (float)($rp['t'] ?? 0);
}

$queryDetail = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '$id' ORDER BY id_boneless_detail ASC");
$details = [];
while ($r = $queryDetail->fetch_assoc()) $details[] = $r;

$q_sat = $koneksi->query("SELECT * FROM tb_boneless_satuan WHERE id_boneless = '$id' ORDER BY id_satuan ASC");
$satuan = [];
if ($q_sat) while ($r = $q_sat->fetch_assoc()) $satuan[] = $r;
$hrg_sat_saved = isset($header['harga_satuan']) ? $header['harga_satuan'] : null;

$val_bon_net = 0;
foreach ($details as $d0) {
    $ln0 = (float)($d0['qty'] ?? 0) * abs((float)($d0['harga'] ?? 0));
    $val_bon_net += (($d0['jenis'] ?? '') == 'minus') ? -$ln0 : $ln0;
}
$val_grand = $val_pabrik + $val_bon_net;
$val_permobil = ($jumlah_mobil > 0) ? $val_grand / $jumlah_mobil : 0;

function bl_num($v) {
    $v = (float)$v;
    if (abs($v - round($v)) < 1e-9) return (int)round($v);
    return round($v, 2);
}

$x = new MiniXlsx();
$stT   = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stTH  = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stSEC = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'B2A1C6', 'border' => true]);
$stC   = $x->style(['sz' => 18, 'fill' => 'FFFFFF', 'border' => true]);
$stCB  = $x->style(['b' => true, 'sz' => 18, 'fill' => 'FFFFFF', 'border' => true]);
$stCC  = $x->style(['sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stCCB = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stCR  = $x->style(['sz' => 18, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'int']);
$stCRB = $x->style(['b' => true, 'sz' => 18, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'int']);
$stRK  = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'B4C7E7', 'border' => true]);
$stRKV = $x->style(['b' => true, 'sz' => 16, 'align' => 'right', 'fill' => 'B4C7E7', 'border' => true, 'num' => 'dec2']);
$stTOT = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'C6D9F0', 'border' => true]);
$stRED = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'color' => 'FFFFFF', 'fill' => 'FF0000', 'border' => true]);
$stYEL = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'FFFF00', 'border' => true]);
$stRp2 = $x->style(['sz' => 16, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'rp2']);

// ---------- SHEET 1: BONELESS (kaki file acuan) ----------
$s1 = $x->sheet('BONELESS');
$r = 1;
$x->cell($s1, $r, 1, 'REALISASI TOTAL REKAP BIAYA PABRIK CIKUPA ' . $judul_tgl, $stRK); $x->merge($s1, $r, 1, $r, 6); $r++;
$x->cell($s1, $r, 1, 'Biaya ' . $jumlah_mobil . ' mobil', $stRK); $x->merge($s1, $r, 1, $r, 4);
$x->cell($s1, $r, 5, round($val_pabrik, 2), $stRKV); $x->merge($s1, $r, 5, $r, 6); $r++;
$x->cell($s1, $r, 1, 'Biaya permobil', $stRK); $x->merge($s1, $r, 1, $r, 4);
$x->cell($s1, $r, 5, round($val_permobil, 2), $stRKV); $x->merge($s1, $r, 5, $r, 6); $r++;
$x->cell($s1, $r, 1, 'BAYARAN TIM BONLESS', $stRK); $x->merge($s1, $r, 1, $r, 6); $r++;
$nb = 0;
foreach ($details as $item) {
    $nb++;
    $qty = (float)($item['qty'] ?? 0);
    $hrg = abs((float)($item['harga'] ?? 0));
    $is_minus = (($item['jenis'] ?? '') == 'minus');
    $line = $qty * $hrg;
    $x->cell($s1, $r, 1, $nb, $stCCB);
    $x->cell($s1, $r, 2, $is_minus ? 'PENGURANGAN' : '', $stCCB);
    $x->cell($s1, $r, 3, strtoupper($item['nama_item'] ?? ''), $stCB);
    $x->cell($s1, $r, 4, $qty ? bl_num($qty) : '', $qty ? $stCC : $stC);
    $x->cell($s1, $r, 5, (int)round($hrg), $stCRB);
    $x->cell($s1, $r, 6, (int)round($line), $stCRB);
    $r++;
}
$x->cell($s1, $r, 1, 'TOTAL', $stTOT); $x->merge($s1, $r, 1, $r, 5);
$x->cell($s1, $r, 6, (int)round($val_bon_net), $stCRB); $r++;
$x->cell($s1, $r, 1, 'DENGAN MESIN BONLES', $stRED); $x->merge($s1, $r, 1, $r, 6); $r++;
$x->cell($s1, $r, 1, 'BIAYA PABRIK', $stYEL);
$x->cell($s1, $r, 2, 'BONLESS', $stYEL);
$x->cell($s1, $r, 3, 'POTONG', $stYEL);
$x->cell($s1, $r, 4, 'TOTAL', $stYEL);
$x->cell($s1, $r, 5, 'Biaya Per mobil', $stYEL); $x->merge($s1, $r, 5, $r, 6); $r++;
$x->cell($s1, $r, 1, round($val_pabrik, 2), $stRp2);
$x->cell($s1, $r, 2, round($val_bon_net, 2), $stRp2);
$x->cell($s1, $r, 3, $jumlah_mobil, $stCC);
$x->cell($s1, $r, 4, round($val_grand, 2), $stRp2);
$x->cell($s1, $r, 5, round($val_permobil, 2), $stRp2); $x->merge($s1, $r, 5, $r, 6); $r++;

// ---------- SHEET 2: BONELESS BARU ----------
$s2 = $x->sheet('BONELESS BARU');
$r2 = 1;
$x->cell($s2, $r2, 1, 'BONELESS BARU', $stT); $x->merge($s2, $r2, 1, $r2, 4); $r2++;
foreach (['No', 'NAMA SESUAI KTP', 'HASIL KERJA', 'UPAH DIBAYAR'] as $i => $h) $x->cell($s2, $r2, $i + 1, $h, $stTH);
$r2++;
$sn = 1; $sat_tot = 0; $sat_allkg = true;
foreach ($satuan as $sr) {
    $sat_tot += (int)$sr['upah'];
    $sr_u = strtoupper(trim($sr['satuan'] ?? 'KG'));
    if ($sr_u !== '' && $sr_u !== 'KG') $sat_allkg = false;
    $x->cell($s2, $r2, 1, $sn++, $stCCB);
    $x->cell($s2, $r2, 2, strtoupper($sr['nama'] ?? ''), $stCB);
    $x->cell($s2, $r2, 3, rtrim(rtrim(number_format((float)($sr['hasil'] ?? 0), 2, '.', ''), '0'), '.') . ' ' . ($sr_u !== '' ? ucfirst(strtolower($sr_u)) : 'Kg'), $stCC);
    $x->cell($s2, $r2, 4, (int)($sr['upah'] ?? 0), $stCR);
    $r2++;
}
$x->cell($s2, $r2, 1, 'TOTAL (' . count($satuan) . ' orang' . (($sat_allkg && $hrg_sat_saved !== null && $hrg_sat_saved !== '') ? ', Rp ' . number_format($hrg_sat_saved, 0, ',', '.') . '/kg' : '') . ')', $stCB); $x->merge($s2, $r2, 1, $r2, 3);
$x->cell($s2, $r2, 4, (int)round($sat_tot), $stCRB); $r2++;

$x->autofit($s1, 30);
$x->autofit($s2, 30);
$x->send("Boneless_$tgl_file.xlsx");
