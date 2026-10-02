<?php
session_start();
set_time_limit(0);
include "../../koneksi.php";
include "../../device_sync_helper.php";
header('Content-Type: application/json');
$id = intval($_POST['id'] ?? 0);
if(!$id){ echo json_encode(['success'=>false,'msg'=>'ID kosong']); exit; }
$q=$koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1");
$row=$q?$q->fetch_assoc():null;
if(!$row || empty($row['no_absen'])){ echo json_encode(['success'=>false,'msg'=>'No absen kosong']); exit; }
$pin=$row['no_absen'];
$pushRes=sync_karyawan_to_devices($koneksi,$id);
foreach($pushRes as $did=>$pr) log_sync_activity($koneksi,'regis_push',$did,$id,$pr['status'],$pr['message']);
$devices=get_all_devices($koneksi);
$sourceId=null;
foreach($devices as $dev){
    if(empty($dev['ip_mesin'])) continue;
    if(!test_device_connection($dev['ip_mesin'],$dev['port'])) continue;
    $templates=get_fingerprint_templates($dev['ip_mesin'],$dev['port'],$dev['comm_key']??0,$pin);
    if(!empty($templates)){ $sourceId=$dev['id_mesin']; break; }
}
if(!$sourceId){ echo json_encode(['success'=>false,'msg'=>'Belum ada sidik di mesin mana pun. Tempel dulu di salah satu mesin (Menu → PIN '.$pin.' → Finger → Enroll).']); exit; }
$targets=[];
foreach($devices as $d) if($d['id_mesin']!=$sourceId && !empty($d['ip_mesin']) && test_device_connection($d['ip_mesin'],$d['port'])) $targets[]=$d['id_mesin'];
if(empty($targets)){ echo json_encode(['success'=>false,'msg'=>'Tidak ada mesin target yang online.']); exit; }
$res=copy_fingerprint_template($koneksi,$pin,$sourceId,$targets);
if(isset($res['error'])){
    foreach($targets as $tid) log_sync_activity($koneksi,'regis_direct_copy',$tid,$id,'error',$res['error']);
    echo json_encode(['success'=>false,'msg'=>$res['error']]);
    exit;
}
foreach($targets as $tid) log_sync_activity($koneksi,'regis_direct_copy',$tid,$id,$res['results'][$tid]['status']??'error',$res['results'][$tid]['message']??'');
echo json_encode(['success'=>true,'results'=>$res['results'],'source'=>$sourceId]);
?>