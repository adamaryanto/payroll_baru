<?php
session_start();
// 2026-10-24: endpoint ini tidak baca/tulis session sama sekali, jadi kunci
// session langsung dilepas. Sebelumnya kunci session dipegang selama semua
// panggilan SOAP ke mesin berjalan, sehingga request halaman lain dari user
// yang sama (misal pindah menu) ikut antre dan terasa lambat.
session_write_close();
include "../../koneksi.php";
include "../../device_sync_helper.php";
header('Content-Type: application/json');
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
$type = $_POST['type'] ?? $_GET['type'] ?? 'finger';
if(!$id){ echo json_encode(['success'=>false,'msg'=>'ID kosong']); exit; }
$q=$koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1");
$row=$q?$q->fetch_assoc():null;
if(!$row || empty($row['no_absen'])){ echo json_encode(['success'=>false,'msg'=>'No absen kosong']); exit; }
$pin=$row['no_absen'];
$devices=get_all_devices($koneksi);
if($type==='mesin'){
    $found=0; $online=0; $debugM=[]; $foundNames=[]; $offNames=[];
    foreach($devices as $dev){
        if(empty($dev['ip_mesin'])){ $offNames[]=$dev['nama_mesin'].' (IP kosong)'; continue; }
        if(!test_device_connection($dev['ip_mesin'],$dev['port'])){ $offNames[]=$dev['nama_mesin'].' (offline)'; continue; }
        $online++;
        $comm=$dev['comm_key']??0;
        $soap="<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
        // 2026-10-24: daftar user bisa besar; timeout 5 detik bawaan sering
        // memotong response di tengah XML (sudah terbukti di get_device_users_full),
        // sehingga PIN yang letaknya di belakang jadi "tidak ketemu" dan badge
        // jadi 3/4 padahal sebenernya ada. Beri waktu lebih, ulangi bila
        // response terpotong, dan kalau tetap terpotong tandai "belum bisa
        // dicek lengkap" — bukan "tidak ada" — supaya tooltip-nya jujur.
        $resp=send_soap_request($dev['ip_mesin'],$dev['port'],$soap,15);
        $complete = is_string($resp) && strpos($resp, '</GetAllUserInfoResponse>') !== false;
        if (is_string($resp) && trim($resp) !== '' && !$complete) {
            $resp=send_soap_request($dev['ip_mesin'],$dev['port'],$soap,30);
            $complete = is_string($resp) && strpos($resp, '</GetAllUserInfoResponse>') !== false;
        }
        // Cek EXACT di tag PIN/PIN2 saja (strpos longgar ">pin<" false-positive
        // untuk PIN kecil, mis. PIN 1 match <Group>1</Group> milik user lain)
        $hasPin = function_exists('soap_pin_exists')
            ? soap_pin_exists($resp, $pin)
            : ($resp && (strpos($resp,"<PIN>{$pin}</PIN>")!==false || strpos($resp,"<PIN2>{$pin}</PIN2>")!==false));
        // Kalau response terpotong DAN PIN ini tidak ketemu di potongannya,
        // jangan klaim "tidak ada" — tandai belum bisa dicek lengkap. Kalau
        // PIN ketemu di potongan, itu sudah bukti ada, jadi tetap dihitung.
        if ($resp && !$complete && !$hasPin) { $offNames[]=$dev['nama_mesin'].' (baca tidak lengkap)'; }
        if($hasPin){ $found++; $foundNames[]=$dev['nama_mesin']; }
        $debugM[]=['device'=>$dev['nama_mesin'],'hasPin'=>$hasPin?1:0];
        log_sync_activity($koneksi,'mesin_debug',$dev['id_mesin'],$id, $hasPin?'found':'not_found', substr($resp??'',0,500));
    }
    echo json_encode(['success'=>true,'found'=>$found,'online'=>$online,'total'=>count($devices),'debug'=>$debugM,'found_names'=>$foundNames,'offline_names'=>$offNames]);
} else {
    // 2026-10-24: untuk badge status cuma perlu tahu ADA/TIDAK sidik di mesin,
    // bukan semua templatenya. get_fingerprint_templates() lama menembak 10x SOAP
    // per mesin (jari 0-9) walaupun template pertama sudah ketemu — itu yang bikin
    // 1 karyawan = 40x SOAP untuk 4 mesin. Di sini berhenti di template pertama
    // yang ketemu (kriteria ADA sama persis: ada tag <Template>).
    $has=0; $online=0; $debug=[]; $hasNames=[]; $offNames=[];
    foreach($devices as $dev){
        if(empty($dev['ip_mesin'])){ $offNames[]=$dev['nama_mesin'].' (IP kosong)'; continue; }
        if(!test_device_connection($dev['ip_mesin'],$dev['port'])){ $offNames[]=$dev['nama_mesin'].' (offline)'; continue; }
        $online++;
        $hasOne=0;
        // 2026-10-24: cek sidik DESINTA (PIN 26) selalu 3/4 di badge padahal
        // copy-nya selalu terverifikasi di Mesin 2,3,4 (sumber = Mesin 1, jadi
        // menurut copy template ada di 4/4). Penyebab paling mungkin: cek badge
        // jalan bareng banyak karyawan sekaligus, mesin sibuk, dan satu
        // GetUserTemplate ke-timeout (5 detik bawaan) sehingga jari itu
        // dikira tidak ada. Jadi: timeout dinaikkan ke 15 detik, dan kalau
        // satu putaran penuh tidak ketemu template, ulangi sekali lagi —
        // kalau di putaran kedua ketemu, itu bukti putaran pertama cuma
        // timeout, bukan template hilang. Kalau setelah 2 putaran tetap
        // tidak ketemu DAN mesin tidak pernah merespons sama sekali, tandai
        // belum bisa dicek, bukan "tidak ada".
        for($pass=0; $pass<2 && !$hasOne; $pass++){
            $responded=0;
            for($fid=0; $fid<10; $fid++){
                $soapT="<GetUserTemplate><ArgComKey xsi:type=\"xsd:integer\">".($dev['comm_key']??0)."</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">{$fid}</FingerID></Arg></GetUserTemplate>";
                $respT=send_soap_request($dev['ip_mesin'],$dev['port'],$soapT,15);
                if($respT){ $responded++; }
                if($respT && strpos($respT,'<Template>')!==false){ $hasOne=1; break; }
            }
            // Ulangi hanya kalau ada panggilan yang tidak merespons sama
            // sekali (timeout) — kalau mesin merespons 10/10 dan tidak ada
            // template, itu memang tidak ada, tidak perlu diulang.
            if($responded>=10) break;
            if(!$hasOne && $responded==0 && $pass==1){ $offNames[]=$dev['nama_mesin'].' (tidak merespons saat cek sidik)'; }
        }
        if($hasOne){ $has++; $hasNames[]=$dev['nama_mesin']; }
        $debug[]=['device'=>$dev['nama_mesin'],'has'=>$hasOne,'raw_count'=>$hasOne];
        // log raw untuk debug Akbar dkk (raw_count di sini = 1/0, bukan jumlah
        // template penuh, karena pengecekan berhenti di template pertama)
        log_sync_activity($koneksi,'finger_debug',$dev['id_mesin'],$id, $hasOne?'found':'not_found', json_encode(['pin'=>$pin,'templates'=>$hasOne]));
    }
    echo json_encode(['success'=>true,'has'=>$has,'online'=>$online,'total'=>count($devices),'debug'=>$debug,'found_names'=>$hasNames,'offline_names'=>$offNames]);
}
?>