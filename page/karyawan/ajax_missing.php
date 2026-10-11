<?php
// Cek karyawan aktif yang BELUM ada di mesin (READ-ONLY, untuk tampilan konfirmasi sync).
// Tidak mengubah apa pun di mesin maupun database.
session_start();
include "../../koneksi.php";
include "../../device_sync_helper.php";
header('Content-Type: application/json');

$devices = get_all_devices($koneksi);
$presentByDev = []; // id_mesin => [pin => true] (per mesin, bukan gabungan)
$onlineDevs = [];   // [['id'=>..,'nama'=>..]] mesin online yang berhasil dibaca
$online = [];
$offline = [];
$unverified = [];
foreach($devices as $dev){
    $did = $dev['id_mesin'];
    $dname = $dev['nama_mesin'] ?? ('Mesin '.$did);
    if(empty($dev['ip_mesin'])){ $offline[] = $dname.' (IP belum diisi)'; continue; }
    // Mesin biner (X902): baca daftar user via UDP
    if(($dev['protocol'] ?? 'soap') === 'zkudp'){
        $zr = zkb_get_users($dev['ip_mesin'], $dev['port'] ?? 4370, intval($dev['comm_key'] ?? 0));
        if($zr['success']){
            $online[] = $dname.' (biner)';
            $onlineDevs[] = ['id' => $did, 'nama' => $dname];
            foreach($zr['users'] as $u){
                $p = trim((string)($u['pin'] ?? ''));
                if($p !== '') $presentByDev[$did][$p] = true;
            }
        } else {
            $offline[] = $dname.' (biner offline: '.$zr['message'].')';
        }
        continue;
    }
    if(!test_device_connection($dev['ip_mesin'],$dev['port'])){ $offline[] = $dname.' (offline)'; continue; }
    $comm = $dev['comm_key'] ?? 0;
    $soap = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
    $resp = send_soap_request($dev['ip_mesin'],$dev['port'],$soap);
    if(!$resp){ $unverified[] = $dname.' (tidak bisa baca daftar user)'; continue; }
    $online[] = $dname;
    $onlineDevs[] = ['id' => $did, 'nama' => $dname];
    preg_match_all('/<PIN>(.*?)<\/PIN>/',$resp,$m1);
    foreach(($m1[1] ?? []) as $p){ $p=trim($p); if($p!=='') $presentByDev[$did][$p]=true; }
    preg_match_all('/<PIN2>(.*?)<\/PIN2>/',$resp,$m2);
    foreach(($m2[1] ?? []) as $p){ $p=trim($p); if($p!=='') $presentByDev[$did][$p]=true; }
}
$missing = [];
$q = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY CAST(no_absen AS UNSIGNED), no_absen");
if($q) while($r=$q->fetch_assoc()){
    $pin = trim($r['no_absen']);
    $lack = [];
    foreach($onlineDevs as $od){
        if(empty($presentByDev[$od['id']][$pin])) $lack[] = $od['nama'];
    }
    if(!empty($lack)) $missing[] = ['pin'=>$r['no_absen'],'nama'=>$r['nama_karyawan'],'kurang_di'=>$lack];
}
echo json_encode(['success'=>true,'missing'=>$missing,'online'=>$online,'offline'=>$offline,'unverified'=>$unverified]);
?>