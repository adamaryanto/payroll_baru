<?php
// Cek karyawan aktif yang BELUM ada di mesin (READ-ONLY, untuk tampilan konfirmasi sync).
// Tidak mengubah apa pun di mesin maupun database.
session_start();
include "../../koneksi.php";
include "../../device_sync_helper.php";
header('Content-Type: application/json');

$devices = get_all_devices($koneksi);
$presentByDev = []; // id_mesin => [pin => true] (per mesin, bukan gabungan)
$checkDevs = [];    // [['id'=>..,'nama'=>..,'status'=>'online'|'offline'|'unverified']]
                    // SEMUA mesin ber-IP, bukan cuma yang online.
$online = [];
$offline = [];
$unverified = [];
foreach($devices as $dev){
    $did = $dev['id_mesin'];
    $dname = $dev['nama_mesin'] ?? ('Mesin '.$did);
    if(empty($dev['ip_mesin'])){ $offline[] = $dname.' (IP belum diisi)'; continue; }
    // 2026-10-24: patokan sync = SEMUA mesin (misal 4), bukan cuma mesin yang
    // online saat cek. Sebelumnya karyawan yang sudah ada di 3 mesin tapi mesin
    // ke-4 sedang offline / gagal dibaca TIDAK masuk daftar missing sama sekali
    // (karena hanya mesin online yang dihitung), jadi dia "gabisa ke sync"
    // padahal belum 4/4. Sekarang mesin offline / belum bisa dicek tetap masuk
    // hitungan kurang, dengan keterangan di namanya — supaya karyawan yang
    // belum 4/4 tetap bisa dipilih untuk sync ke semua mesin. Push ke mesin
    // yang offline itu sendiri tentu tetap gagal di run ini, dan akan berhasil
    // di run berikutnya setelah mesinnya online lagi.
    $checkDevs[] = ['id' => $did, 'nama' => $dname, 'status' => 'unverified'];
    $checkIdx = count($checkDevs) - 1;
    // Mesin biner (X902): baca daftar user via UDP
    if(($dev['protocol'] ?? 'soap') === 'zkudp'){
        $zr = zkb_get_users($dev['ip_mesin'], $dev['port'] ?? 4370, intval($dev['comm_key'] ?? 0));
        if($zr['success']){
            $online[] = $dname.' (biner)';
            $checkDevs[$checkIdx]['status'] = 'online';
            foreach($zr['users'] as $u){
                $p = trim((string)($u['pin'] ?? ''));
                if($p !== '') $presentByDev[$did][$p] = true;
            }
        } else {
            $offline[] = $dname.' (biner offline: '.$zr['message'].')';
            $checkDevs[$checkIdx]['status'] = 'offline';
        }
        continue;
    }
    if(!test_device_connection($dev['ip_mesin'],$dev['port'])){
        $offline[] = $dname.' (offline)';
        $checkDevs[$checkIdx]['status'] = 'offline';
        continue;
    }
    $comm = $dev['comm_key'] ?? 0;
    $soap = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
    // 2026-10-24: sama seperti ajax_cek — daftar user bisa besar dan timeout
    // 5 detik sering memotong response, sehingga PIN di belakang jadi falsely
    // "kurang". Beri waktu lebih dan ulangi bila terpotong; kalau tetap
    // terpotong, perlakukan sebagai belum bisa dicek (bukan sebagai daftar
    // lengkap), supaya karyawan tidak salah masuk daftar kurang.
    $resp = send_soap_request($dev['ip_mesin'],$dev['port'],$soap,15);
    $completeU = is_string($resp) && strpos($resp, '</GetAllUserInfoResponse>') !== false;
    if (is_string($resp) && trim($resp) !== '' && !$completeU) {
        $resp = send_soap_request($dev['ip_mesin'],$dev['port'],$soap,30);
        $completeU = is_string($resp) && strpos($resp, '</GetAllUserInfoResponse>') !== false;
    }
    if(!$resp || !$completeU){
        $unverified[] = $dname.' (tidak bisa baca daftar user)';
        $checkDevs[$checkIdx]['status'] = 'unverified';
        continue;
    }
    $online[] = $dname;
    $checkDevs[$checkIdx]['status'] = 'online';
    preg_match_all('/<PIN>(.*?)<\/PIN>/',$resp,$m1);
    foreach(($m1[1] ?? []) as $p){ $p=trim($p); if($p!=='') $presentByDev[$did][$p]=true; }
    preg_match_all('/<PIN2>(.*?)<\/PIN2>/',$resp,$m2);
    foreach(($m2[1] ?? []) as $p){ $p=trim($p); if($p!=='') $presentByDev[$did][$p]=true; }
}
$missing = [];
$q = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY CAST(no_absen AS UNSIGNED), no_absen");
if($q) while($r=$q->fetch_assoc()){
    $pin = trim($r['no_absen']);
    $lack = []; $lackIds = [];
    foreach($checkDevs as $od){
        if(empty($presentByDev[$od['id']][$pin])) {
            $lack[] = $od['nama'] . ($od['status'] === 'offline' ? ' (offline)' : ($od['status'] === 'unverified' ? ' (belum bisa dicek)' : ''));
            $lackIds[] = $od['id'];
        }
    }
    // 2026-10-24: kurang_di_ids dipakai dialog sync supaya push-nya hanya
    // ke mesin yang kurang itu saja, bukan ke semua mesin (permintaan Reza:
    // yang kurang-kurang aja di-push ke mesin yang kurang).
    if(!empty($lack)) $missing[] = ['pin'=>$r['no_absen'],'nama'=>$r['nama_karyawan'],'kurang_di'=>$lack,'kurang_di_ids'=>$lackIds];
}
echo json_encode(['success'=>true,'missing'=>$missing,'online'=>$online,'offline'=>$offline,'unverified'=>$unverified]);
?>