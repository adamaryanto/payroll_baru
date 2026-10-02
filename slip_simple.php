<?php
include "koneksi.php";
$id_karyawan = $_GET['id'] ?? '';
$ttgl1 = $_GET['ttgl1'] ?? date('Y-m-d');
$ttgl2 = $_GET['ttgl2'] ?? date('Y-m-d');
$bulanIndo=['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan_angka=date("m", strtotime($ttgl1));
$bulan=$bulanIndo[$bulan_angka];
$q_kar=$koneksi->query("SELECT k.nama_karyawan, k.upah, k.no_absen, k.id_jadwal, d.nama_departmen, j.jam_masuk, j.jam_keluar, j.istirahat_masuk, j.istirahat_keluar, j.keterangan as shift_ket, os.OS_DHK FROM ms_karyawan k LEFT JOIN ms_departmen d ON k.id_departmen=d.id_departmen LEFT JOIN tb_jadwal j ON k.id_jadwal=j.id_jadwal LEFT JOIN ms_os_dhk os ON k.id_os_dhk=os.id_os_dhk WHERE k.id_karyawan='$id_karyawan' LIMIT 1");
$d_kar=$q_kar->fetch_assoc();
$namaKaryawan=$d_kar['nama_karyawan']??"Karyawan_$id_karyawan";
$nama_file=str_replace(' ', '_', $namaKaryawan);
$upahPokok=$d_kar['upah']??0;
$pin=$d_kar['no_absen']??'';
$osdhk=$d_kar['OS_DHK']??'DHK';
$shiftMasuk=$d_kar['jam_masuk']??'00:00:00';
$shiftPulang=$d_kar['jam_keluar']??'00:00:00';
$shiftIstKeluar=$d_kar['istirahat_keluar']??'00:00:00';
$shiftIstMasuk=$d_kar['istirahat_masuk']??'00:00:00';
$shiftKet=$d_kar['shift_ket']??'';
$isFlex=(strpos(strtoupper($shiftKet),'FLEXI')!==false);
$tgl_file=date('d-m-Y',strtotime($ttgl1));
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Slip_".$nama_file."_".$tgl_file.".xls");
header("Pragma: no-cache");
header("Expires: 0");
echo "<meta charset='UTF-8'>";
function rupiah($a){ return "Rp " . number_format($a, 0, ',', '.'); }
$q_denda=$koneksi->query("SELECT * FROM tb_denda LIMIT 1"); $d_denda=$q_denda->fetch_assoc();
$globalDendaMasuk=$d_denda['denda_masuk']??0;
$globalDendaIstKeluar=$d_denda['denda_istirahat_keluar']??0;
$globalDendaIstMasuk=$d_denda['denda_istirahat_masuk']??0;
$globalDendaPulang=$d_denda['denda_pulang']??0;
$globalDendaTidakLengkap=$d_denda['denda_tidak_lengkap']??0;
$q=$koneksi->query("SELECT * FROM tb_record WHERE userid='$pin' AND tgl BETWEEN '$ttgl1' AND '$ttgl2' ORDER BY tgl ASC, detail_waktu ASC");
$grouped=[];
while($r=$q->fetch_assoc()){
    $time=substr($r['detail_waktu'],11,5); $status=$r['status']; $tgl=$r['tgl'];
    if(!isset($grouped[$tgl])) $grouped[$tgl]=['masuk'=>'','pulang'=>'','ist_keluar'=>'','ist_masuk'=>'','raw'=>[]];
    $grouped[$tgl]['raw'][]=['time'=>$time,'status'=>$status];
    if($status==0 && (empty($grouped[$tgl]['masuk']) || $time < $grouped[$tgl]['masuk'])) $grouped[$tgl]['masuk']=$time;
    elseif($status==1 && (empty($grouped[$tgl]['pulang']) || $time > $grouped[$tgl]['pulang'])) $grouped[$tgl]['pulang']=$time;
    elseif($status==2 && (empty($grouped[$tgl]['ist_keluar']) || $time < $grouped[$tgl]['ist_keluar'])) $grouped[$tgl]['ist_keluar']=$time;
    elseif($status==3 && (empty($grouped[$tgl]['ist_masuk']) || $time < $grouped[$tgl]['ist_masuk'])) $grouped[$tgl]['ist_masuk']=$time;
}
foreach($grouped as $tgl=>&$row){
    if(empty($row['masuk']) && !empty($row['raw'])){ usort($row['raw'],fn($a,$b)=>strcmp($a['time'],$b['time'])); $row['masuk']=$row['raw'][0]['time']; if(count($row['raw'])>1) $row['pulang']=$row['raw'][count($row['raw'])-1]['time']; }
    if(!empty($row['masuk']) && $row['masuk']==$row['pulang']) $row['pulang']='';
}
unset($row);
// FIX single tap: tentukan masuk/pulang berdasarkan jam shift (07:48 dekat 07:00 → pulang)
foreach($grouped as $tgl=>&$row){
    if(count($row['raw'])==1 && !empty($row['masuk']) && empty($row['pulang']) && !$isFlex && $shiftMasuk!='00:00:00' && $shiftPulang!='00:00:00'){
        $t=$row['masuk'];
        $dm=abs(strtotime("1970-01-01 $t")-strtotime("1970-01-01 $shiftMasuk")); if($dm>43200) $dm=86400-$dm;
        $dp=abs(strtotime("1970-01-01 $t")-strtotime("1970-01-01 $shiftPulang")); if($dp>43200) $dp=86400-$dp;
        if($dp < $dm){ $row['pulang']=$row['masuk']; $row['masuk']=''; }
    }
}
unset($row);
// FIX shift malam: merge lintas hari tanpa peduli shift (06/09 18:04 + 07/09 06:09 → 06/09 18:04/06:09)
$keys=array_keys($grouped); sort($keys);
foreach($keys as $k){
    if(!isset($grouped[$k])) continue;
    if(count($grouped[$k]['raw'])!=1) continue;
    $next=date('Y-m-d',strtotime($k.' +1 day')); if(!isset($grouped[$next])) continue;
    if(count($grouped[$next]['raw'])!=1) continue;
    // ambil jam asli dari raw (biar gak tergantung sudah jadi masuk/pulang)
    $t1=$grouped[$k]['raw'][0]['time']; $t2=$grouped[$next]['raw'][0]['time'];
    $in_ts=strtotime($k.' '.$t1); $out_ts=strtotime($next.' '.$t2); $diff=($out_ts-$in_ts)/3600;
    if($diff>0 && $diff<=16){
        // t1 sore (>=12:00) + t2 pagi (<12:00) = 1 shift malam
        $isT1Sore = strtotime("1970-01-01 $t1") >= strtotime("1970-01-01 12:00:00");
        $isT2Pagi = strtotime("1970-01-01 $t2") < strtotime("1970-01-01 12:00:00");
        if($isT1Sore && $isT2Pagi){
            $grouped[$k]['masuk']=$t1; $grouped[$k]['pulang']=$t2;
            $grouped[$k]['raw']=array_merge($grouped[$k]['raw'],$grouped[$next]['raw']);
            unset($grouped[$next]);
        }
    }
}
echo "<table border='1'>";
echo '<tr><td rowspan="2" style="width:80px; font-weight:bold; text-align:center; vertical-align:middle;">Bulan '.$bulan.'</td><td colspan="8" style="font-weight:bold; text-align:center; font-size:14pt;">SLIP UPAH</td><td colspan="3" rowspan="2" style="font-weight:bold; text-align:center; vertical-align:middle;">OUTSOURCING : '.$osdhk.'</td></tr>';
echo '<tr><td colspan="8" style="font-weight:bold; text-align:left;">NAMA : '.$namaKaryawan.'</td></tr>';
echo "<tr><th style='width:90px;'>Tanggal</th><th>Upah</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Pot Telat</th><th>Pot Istirahat</th><th>Pot Pulang</th><th>Pot Tidak Absen</th><th>Pot Lainnya</th><th>Lembur</th><th>Total</th><th>Karyawan Setuju</th><th>OS</th><th>HRD ".$osdhk."</th></tr>";
$totalAll=0;
if(empty($grouped)){
    echo "<tr><td colspan='14' style='text-align:center'>Tidak ada absen di periode $ttgl1 s/d $ttgl2</td></tr>";
} else {
    foreach($grouped as $tgl=>$row){
        $raMasuk=$row['masuk']?:'00:00:00'; $raPulang=$row['pulang']?:'00:00:00';
        $isLate=(!$isFlex && $raMasuk!='00:00:00' && $shiftMasuk!='00:00:00' && strtotime($raMasuk) > strtotime($shiftMasuk));
        $isEarlyOut=(!$isFlex && $raPulang!='00:00:00' && $shiftPulang!='00:00:00' && strtotime($raPulang) < strtotime($shiftPulang));
        $hasIncompleteMain = ((!empty($row['masuk']) && empty($row['pulang'])) || (empty($row['masuk']) && !empty($row['pulang'])));
        $isRestExpected=(!$isFlex && $shiftIstKeluar!='00:00:00');
        $hasIncompleteBreak=($isRestExpected && (empty($row['ist_keluar']) || empty($row['ist_masuk'])));
        $isEarlyBreak=(!$isFlex && !empty($row['ist_keluar']) && $shiftIstKeluar!='00:00:00' && strtotime($row['ist_keluar']) < strtotime($shiftIstKeluar));
        $isLateBreak=(!$isFlex && !empty($row['ist_masuk']) && $shiftIstMasuk!='00:00:00' && strtotime($row['ist_masuk']) > strtotime($shiftIstMasuk));
        $potTelat=$isLate?$globalDendaMasuk:0;
        $potIst=($isEarlyBreak?$globalDendaIstKeluar:0)+($isLateBreak?$globalDendaIstMasuk:0);
        $potPulang=$isEarlyOut?$globalDendaPulang:0;
        $potTidak=($hasIncompleteMain||$hasIncompleteBreak)?$globalDendaTidakLengkap:0;
        $potLain=0; $lembur=0;
        // lembur simple: >8 jam
        if(!empty($row['masuk']) && !empty($row['pulang'])){
            $dur=strtotime($row['pulang'])-strtotime($row['masuk']); if($dur<0) $dur+=86400;
            if($dur/3600>8) $lembur=0; // bisa diisi dari tb_lembur kalau ada, sekarang 0
        }
        $totalRow=($upahPokok+$lembur)-($potTelat+$potIst+$potPulang+$potTidak+$potLain);
        $totalAll+=$totalRow;
        $styleTotal=$totalRow<0?'color:red;':($totalRow==1?'':'');
        echo "<tr><td style='mso-number-format:\"\\@\"; width:90px; white-space:nowrap;'>".date('d/m/Y',strtotime($tgl))."</td><td>".rupiah($upahPokok)."</td><td>".$row['masuk']."</td><td>".$row['pulang']."</td><td>".rupiah($potTelat)."</td><td>".rupiah($potIst)."</td><td>".rupiah($potPulang)."</td><td>".rupiah($potTidak)."</td><td>".rupiah($potLain)."</td><td>".rupiah($lembur)."</td><td style='font-weight:bold; $styleTotal'>".rupiah($totalRow)."</td><td></td><td></td><td></td></tr>";
    }
    echo "<tr style='font-weight:bold; background:#f8f9fa;'><td colspan='10' style='text-align:right'>SUBTOTAL GAJI</td><td style='text-align:right; background:#e0f2fe; color:#1e40af;'>".rupiah($totalAll)."</td><td colspan='3'></td></tr>";
}
echo "</table>";
?>