<?php
session_start();
include "../../koneksi.php";
include "../../device_sync_helper.php";
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if(!$id){ echo json_encode(['success'=>false,'msg'=>'ID kosong']); exit; }

$q=$koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1");
$row=$q?$q->fetch_assoc():null;
if(!$row || empty($row['no_absen'])){ echo json_encode(['success'=>false,'msg'=>'No absen kosong']); exit; }

$pin=$row['no_absen'];
$devices=get_all_devices($koneksi);
$results=[];

foreach($devices as $dev){
    if(empty($dev['ip_mesin'])) continue;
    if(!test_device_connection($dev['ip_mesin'],$dev['port'])) {
        $results[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'status'=>'error','message'=>'Device offline'];
        continue;
    }
    $comm=$dev['comm_key']??0;
    $portsToTry = array_unique([$dev['port']??80, 80, 4370]);
    $cmds=[
        "<StartEnroll><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID></Arg></StartEnroll>",
        "<EnrollUser><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN></Arg></EnrollUser>",
        "<StartEnrollEx><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID><RETRY xsi:type=\"xsd:integer\">3</RETRY></Arg></StartEnrollEx>",
        "<SSR_StartEnroll><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID></Arg></SSR_StartEnroll>",
    ];
    $resp=null; $success=false;
    foreach($portsToTry as $tryPort){
        if(!test_device_connection($dev['ip_mesin'],$tryPort)) continue;
        foreach($cmds as $soapTry){
            $resp = send_soap_request($dev['ip_mesin'],$tryPort,$soapTry);
            if($resp && (stripos($resp,'Success')!==false || stripos($resp,'Successfully')!==false)){ $success=true; break 2; }
        }
    }
    if($success){
        $results[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'status'=>'success','message'=>"Mesin masuk mode enroll PIN $pin - Silakan tempel jari 3x di X606-S"];
    } else {
        $results[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'status'=>'warning','message'=>"PIN $pin sudah siap di mesin, silakan Menu → User Manage → PIN $pin → Enroll di X606-S"];
    }
}

echo json_encode(['success'=>true,'pin'=>$pin,'nama'=>$row['nama_karyawan'],'results'=>$results]);
