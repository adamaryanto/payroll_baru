<?php
session_start();
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
        $resp=send_soap_request($dev['ip_mesin'],$dev['port'],$soap);
        // Cek EXACT di tag PIN/PIN2 saja (strpos longgar ">pin<" false-positive
        // untuk PIN kecil, mis. PIN 1 match <Group>1</Group> milik user lain)
        $hasPin = function_exists('soap_pin_exists')
            ? soap_pin_exists($resp, $pin)
            : ($resp && (strpos($resp,"<PIN>{$pin}</PIN>")!==false || strpos($resp,"<PIN2>{$pin}</PIN2>")!==false));
        if($hasPin){ $found++; $foundNames[]=$dev['nama_mesin']; }
        $debugM[]=['device'=>$dev['nama_mesin'],'hasPin'=>$hasPin?1:0];
        log_sync_activity($koneksi,'mesin_debug',$dev['id_mesin'],$id, $hasPin?'found':'not_found', substr($resp??'',0,500));
    }
    echo json_encode(['success'=>true,'found'=>$found,'online'=>$online,'total'=>count($devices),'debug'=>$debugM,'found_names'=>$foundNames,'offline_names'=>$offNames]);
} else {
    $has=0; $online=0; $debug=[]; $hasNames=[]; $offNames=[];
    foreach($devices as $dev){
        if(empty($dev['ip_mesin'])){ $offNames[]=$dev['nama_mesin'].' (IP kosong)'; continue; }
        if(!test_device_connection($dev['ip_mesin'],$dev['port'])){ $offNames[]=$dev['nama_mesin'].' (offline)'; continue; }
        $online++;
        $templates=get_fingerprint_templates($dev['ip_mesin'],$dev['port'],$dev['comm_key']??0,$pin);
        $hasOne=!empty($templates)?1:0;
        if($hasOne){ $has++; $hasNames[]=$dev['nama_mesin']; }
        $debug[]=['device'=>$dev['nama_mesin'],'has'=>$hasOne,'raw_count'=>count($templates)];
        // log raw untuk debug Akbar dkk
        log_sync_activity($koneksi,'finger_debug',$dev['id_mesin'],$id, $hasOne?'found':'not_found', json_encode(['pin'=>$pin,'templates'=>count($templates)]));
    }
    echo json_encode(['success'=>true,'has'=>$has,'online'=>$online,'total'=>count($devices),'debug'=>$debug,'found_names'=>$hasNames,'offline_names'=>$offNames]);
}
?>