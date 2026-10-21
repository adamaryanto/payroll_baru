<?php
$id = $_GET['id'] ?? '';
$ttgl1 = $_GET['ttgl1'] ?? date("Y-m-d", strtotime("-7 days"));
$ttgl2 = $_GET['ttgl2'] ?? date("Y-m-d");
$q_karyawan_info = $koneksi->query("SELECT A.nama_karyawan, B.nama_departmen FROM ms_karyawan A LEFT JOIN ms_departmen B ON A.id_departmen = B.id_departmen WHERE A.id_karyawan = '$id'");
$d_karyawan_info = $q_karyawan_info->fetch_assoc();
$namaKaryawan = $d_karyawan_info['nama_karyawan'] ?? 'Karyawan Tidak Ditemukan';
$deptKaryawan = $d_karyawan_info['nama_departmen'] ?? '-';
if(!isset($_GET['ttgl1']) || !isset($_GET['ttgl2'])){
    $qRange=$koneksi->query("SELECT MAX(tgl) as max_tgl FROM tb_record WHERE userid=(SELECT no_absen FROM ms_karyawan WHERE id_karyawan='$id' LIMIT 1)");
    $dRange=$qRange?$qRange->fetch_assoc():null;
    if(!empty($dRange['max_tgl'])){ $ttgl2=$dRange['max_tgl']; $ttgl1=date('Y-m-d', strtotime($ttgl2.' -7 days')); }
}
function rupiah($a){ return "Rp " . number_format($a, 0, ',', '.'); }
?>
<div class="container-fluid px-3 md:px-6 mt-6 mb-10">
    <div class="bg-white shadow-xl rounded-2xl border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 px-6 md:px-8 py-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center text-white border border-white/30">
                        <i class="fas fa-file-invoice-dollar text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl md:text-2xl font-bold text-white m-0">Preview Slip Gaji</h3>
                        <p class="text-blue-100 text-sm font-medium opacity-90"><?= $namaKaryawan ?> • <span class="bg-white/20 px-2 py-0.5 rounded text-[11px] uppercase"><?= $deptKaryawan ?></span></p>
                    </div>
                </div>
                <a href="index.php?page=realisasi&aksi=slipk" class="w-40 h-10 bg-white/10 hover:bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center text-white border border-white/20">
                    <i class="fas fa-arrow-left mr-2"></i>Kembali
                </a>
            </div>
        </div>
        <div class="p-4 md:p-8">
            <form method="GET" class="bg-blue-50/50 border border-blue-100 p-4 md:p-5 rounded-2xl mb-8">
                <input type="hidden" name="page" value="realisasi">
                <input type="hidden" name="aksi" value="slip_simple">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="flex flex-col md:flex-row items-center gap-4">
                    <div class="w-full md:flex-1 flex items-center bg-white px-4 h-[40px] rounded-xl border border-blue-100 shadow-sm">
                        <i class="fas fa-user-circle text-blue-500 mr-3"></i>
                        <span class="text-sm font-bold text-gray-700 truncate"><?= $namaKaryawan ?></span>
                    </div>
                    <div class="w-full md:w-40">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-xs"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" name="ttgl1" required value="<?= $ttgl1 ?>" class="w-full h-[40px] pl-9 pr-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        </div>
                    </div>
                    <div class="w-full md:w-40">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-xs"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" name="ttgl2" required value="<?= $ttgl2 ?>" class="w-full h-[40px] pl-9 pr-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        </div>
                    </div>
                    <button type="submit" name="cari" value="1" class="h-[40px] px-6 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-md w-full md:w-auto flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i>Cari
                    </button>
                </div>
            </form>

            <?php
            $qPin=$koneksi->query("SELECT no_absen, upah FROM ms_karyawan WHERE id_karyawan='$id' LIMIT 1");
            $dPin=$qPin->fetch_assoc(); $pin=$dPin['no_absen']??''; $upahPokok=$dPin['upah']??0;
            $q_denda=$koneksi->query("SELECT * FROM tb_denda LIMIT 1"); $d_denda=$q_denda->fetch_assoc();
            $globalDendaMasuk=$d_denda['denda_masuk']??0; $globalDendaPulang=$d_denda['denda_pulang']??0; $globalDendaTidakLengkap=$d_denda['denda_tidak_lengkap']??0;
            $q=$koneksi->query("SELECT * FROM tb_record WHERE userid='$pin' AND tgl BETWEEN '$ttgl1' AND '$ttgl2' ORDER BY tgl ASC, detail_waktu ASC");
            $grouped=[];
            while($r=$q->fetch_assoc()){
                $time=substr($r['detail_waktu'],11,5); $status=$r['status']; $tgl=$r['tgl'];
                if(!isset($grouped[$tgl])) $grouped[$tgl]=['masuk'=>'','pulang'=>'','raw'=>[]];
                $grouped[$tgl]['raw'][]=['time'=>$time,'status'=>$status];
                if($status==0 && (empty($grouped[$tgl]['masuk']) || $time < $grouped[$tgl]['masuk'])) $grouped[$tgl]['masuk']=$time;
                elseif($status==1 && (empty($grouped[$tgl]['pulang']) || $time > $grouped[$tgl]['pulang'])) $grouped[$tgl]['pulang']=$time;
            }
            foreach($grouped as $tgl=>&$row){
                if(empty($row['masuk']) && !empty($row['raw'])){ usort($row['raw'],fn($a,$b)=>strcmp($a['time'],$b['time'])); $row['masuk']=$row['raw'][0]['time']; if(count($row['raw'])>1) $row['pulang']=$row['raw'][count($row['raw'])-1]['time']; }
                if(!empty($row['masuk']) && $row['masuk']==$row['pulang']) $row['pulang']='';
            }
            unset($row);
            // FIX single tap: tentukan masuk/pulang berdasarkan jam shift
            $qShift=$koneksi->query("SELECT j.jam_masuk, j.jam_keluar, j.keterangan FROM ms_karyawan k LEFT JOIN tb_jadwal j ON k.id_jadwal=j.id_jadwal WHERE k.id_karyawan='$id' LIMIT 1");
            $dShift=$qShift?$qShift->fetch_assoc():null; $sMasuk=$dShift['jam_masuk']??'00:00:00'; $sPulang=$dShift['jam_keluar']??'00:00:00'; $isFlexS=(strpos(strtoupper($dShift['keterangan']??''),'FLEXI')!==false||strpos(strtoupper($dShift['keterangan']??''),'FLEKSI')!==false);
            foreach($grouped as $tgl=>&$row){
                if(count($row['raw'])==1 && !empty($row['masuk']) && empty($row['pulang']) && !$isFlexS && $sMasuk!='00:00:00' && $sPulang!='00:00:00'){
                    $t=$row['masuk'];
                    $dm=abs(strtotime("1970-01-01 $t")-strtotime("1970-01-01 $sMasuk")); if($dm>43200) $dm=86400-$dm;
                    $dp=abs(strtotime("1970-01-01 $t")-strtotime("1970-01-01 $sPulang")); if($dp>43200) $dp=86400-$dp;
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
                $t1=$grouped[$k]['raw'][0]['time']; $t2=$grouped[$next]['raw'][0]['time'];
                $in_ts=strtotime($k.' '.$t1); $out_ts=strtotime($next.' '.$t2); $diff=($out_ts-$in_ts)/3600;
                if($diff>0 && $diff<=16){
                    $isT1Sore = strtotime("1970-01-01 $t1") >= strtotime("1970-01-01 12:00:00");
                    $isT2Pagi = strtotime("1970-01-01 $t2") < strtotime("1970-01-01 12:00:00");
                    if($isT1Sore && $isT2Pagi){
                        $grouped[$k]['masuk']=$t1; $grouped[$k]['pulang']=$t2;
                        $grouped[$k]['raw']=array_merge($grouped[$k]['raw'],$grouped[$next]['raw']);
                        unset($grouped[$next]);
                    }
                }
            }
            ?>
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6 pt-4 border-t border-gray-100">
                <div>
                    <h4 class="text-lg font-bold text-gray-800 m-0">Hasil Pencarian</h4>
                    <p class="text-gray-500 text-xs mt-1">Menampilkan data slip gaji periode <?= date('d/m/Y',strtotime($ttgl1)) ?> - <?= date('d/m/Y',strtotime($ttgl2)) ?></p>
                </div>
                <?php if(!empty($grouped)): ?>
                <a target="_blank" href="slip_simple.php?id=<?= $id ?>&ttgl1=<?= $ttgl1 ?>&ttgl2=<?= $ttgl2 ?>" class="flex items-center justify-center px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md">
                    <i class="fas fa-file-excel mr-2"></i> Download Excel
                </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="w-full text-left border-collapse table-modern" id="slipSimpleTable">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Tanggal</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Upah Pokok</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Masuk/Pulang</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Pot. Telat</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Pot. Istirahat</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Pot. Pulang</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Pot. Tidak Absen</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Pot. Lain</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase">Lembur</th>
                            <th class="py-3 px-3 text-[11px] font-bold uppercase text-right">Total Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if(empty($grouped)): ?>
                        <tr><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm">-</td><td class="py-3 px-3 text-sm text-right">-</td></tr>
                        <tr><td colspan="10" class="py-4 text-center text-gray-400 text-sm">Tidak ada absen di periode ini — coba ganti tanggal</td></tr>
                        <?php else: foreach($grouped as $tgl=>$row):
                            $hasMasuk=!empty($row['masuk']); $hasPulang=!empty($row['pulang']);
                            $potTelat=0; $potPulang=0; $potTidak=0;
                            // Simple potongan: telat/pulang tidak lengkap pakai global, tanpa cek shift detail biar simple
                            if(!$hasMasuk || !$hasPulang) $potTidak=$globalDendaTidakLengkap;
                            $total=$upahPokok - $potTelat - $potPulang - $potTidak;
                            if($total<0) $total=0;
                        ?>
                        <tr>
                            <td class="py-3 px-3 text-sm font-bold"><?= date('d/m/Y',strtotime($tgl)) ?></td>
                            <td class="py-3 px-3 text-sm"><?= rupiah($upahPokok) ?></td>
                            <td class="py-3 px-3 text-sm"><span class="bg-gray-100 px-2 py-1 rounded text-xs font-bold"><?= ($row['masuk']?:'-').' - '.($row['pulang']?:'-') ?></span></td>
                            <td class="py-3 px-3 text-sm font-bold text-rose-600"><?= rupiah($potTelat) ?></td>
                            <td class="py-3 px-3 text-sm font-bold text-rose-600"><?= rupiah(0) ?></td>
                            <td class="py-3 px-3 text-sm font-bold text-rose-600"><?= rupiah($potPulang) ?></td>
                            <td class="py-3 px-3 text-sm font-bold text-rose-600"><?= rupiah($potTidak) ?></td>
                            <td class="py-3 px-3 text-sm font-bold text-orange-600"><?= rupiah(0) ?></td>
                            <td class="py-3 px-3 text-sm font-bold text-emerald-600"><?= rupiah(0) ?></td>
                            <td class="py-3 px-3 text-sm font-bold text-blue-700 text-right"><?= rupiah($total) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<style>
#slipSimpleTable_wrapper .row:last-child{display:flex!important;justify-content:space-between!important;align-items:center!important;flex-wrap:wrap!important;gap:15px!important;margin-top:15px!important;padding:0 5px!important}
#slipSimpleTable_wrapper .dataTables_paginate{display:flex!important;gap:4px!important;align-items:center!important}
#slipSimpleTable_wrapper .dataTables_paginate .paginate_button{border:1px solid #e2e8f0!important;background:white!important;border-radius:6px!important;padding:5px 12px!important;color:#475569!important}
#slipSimpleTable_wrapper .dataTables_paginate .paginate_button.current{background:#2563eb!important;color:white!important;border-color:#2563eb!important}
#slipSimpleTable_wrapper .dataTables_info{color:#64748b!important;font-size:13px!important}
</style>
<script>
$(document).ready(function(){ if($('#slipSimpleTable').length) $('#slipSimpleTable').DataTable({pageLength:25,language:{search:"Cari:",lengthMenu:"Tampilkan _MENU_ data",info:"Menampilkan _START_ s/d _END_ dari _TOTAL_ data",paginate:{previous:"Prev",next:"Next"}}}); });
</script>
