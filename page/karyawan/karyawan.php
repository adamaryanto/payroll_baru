<?php
include_once __DIR__ . '/../../device_sync_helper.php';
create_sync_log_table($koneksi);
$bulk_sync_results = null;
if (isset($_POST['sync_all_karyawan'])) {
    $devices = get_all_devices($koneksi);
    $bulk_sync_results = [];
    // SYNC HANYA YANG BELUM: daftar PIN dari konfirmasi (hasil cek isi mesin).
    // Push per karyawan, save-only (SetUserInfo saja, tanpa baca mesin).
    $only_pins = [];
    if(!empty($_POST['sync_pins'])){
        $decPins = json_decode($_POST['sync_pins'], true);
        if(is_array($decPins)) foreach($decPins as $p){ $p=trim((string)$p); if($p!=='') $only_pins[$p]=true; }
    }
    if(!empty($only_pins)){
        $perDev = [];
        foreach(array_keys($only_pins) as $pin){
            $pinEsc = $koneksi->real_escape_string($pin);
            $qr = $koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$pinEsc' AND status_karyawan='Aktif' LIMIT 1");
            $erow = $qr ? $qr->fetch_assoc() : null;
            if(!$erow) continue;
            $pr = sync_karyawan_to_devices($koneksi, (int)$erow['id_karyawan']);
            foreach($pr as $did=>$r){
                if(!isset($perDev[$did])) $perDev[$did] = ['device_name'=>($r['device'] ?? ('Mesin '.$did)),'synced'=>0,'failed'=>0,'skipped'=>0,'total'=>0];
                $perDev[$did]['total']++;
                $st = $r['status'] ?? 'error';
                if($st=='success') $perDev[$did]['synced']++;
                elseif($st=='skipped') $perDev[$did]['skipped']++;
                else $perDev[$did]['failed']++;
                log_sync_activity($koneksi, 'bulk_sync', $did, (int)$erow['id_karyawan'], $st, ($r['message'] ?? ''));
            }
        }
        foreach($devices as $device){
            $did = $device['id_mesin'];
            $dip = !empty($device['ip_mesin']) ? ($device['ip_mesin'] . ':' . ($device['port'] ?? '4370')) : '-';
            if(!isset($perDev[$did])){
                $bulk_sync_results[$did] = [
                    'device_name'=>$device['nama_mesin'],
                    'device_ip'=>$dip,
                    'status'=>'skipped',
                    'message'=>'Tidak ada PIN yang perlu di-sync ke mesin ini.',
                    'total'=>0,'synced'=>0,'failed'=>0
                ];
                continue;
            }
            $pd = $perDev[$did];
            $bulk_sync_results[$did] = [
                'device_name'=>$pd['device_name'],
                'device_ip'=>$dip,
                'status'=> ($pd['failed']>0?'warning':($pd['synced']>0?'success':'skipped')),
                'message'=>"Berhasil sinkronisasi {$pd['synced']} dari {$pd['total']} karyawan".($pd['skipped']>0?" ({$pd['skipped']} skip)":"").($pd['failed']>0?" ({$pd['failed']} gagal)":""),
                'total'=>$pd['total'],'synced'=>$pd['synced'],'failed'=>$pd['failed'],'skipped'=>$pd['skipped']
            ];
        }
    } else {
    foreach ($devices as $device) {
        $did = $device['id_mesin'];
        $dip = !empty($device['ip_mesin']) ? ($device['ip_mesin'] . ':' . ($device['port'] ?? '4370')) : '-';
        if(empty($device['ip_mesin'])){
            $bulk_sync_results[$did] = [
                'device_name'=>$device['nama_mesin'],
                'device_ip'=>$dip,
                'status'=>'offline',
                'message'=>'IP belum diisi — sync dilewati. Isi IP di Setting Device.',
                'total'=>0,'synced'=>0,'failed'=>0
            ];
            log_sync_activity($koneksi, 'bulk_sync', $did, null, 'skipped', 'IP kosong, sync dilewati');
            continue;
        }
        if(!test_device_connection($device['ip_mesin'],$device['port'])){
            $bulk_sync_results[$did] = [
                'device_name'=>$device['nama_mesin'],
                'device_ip'=>$dip,
                'status'=>'offline',
                'message'=>'Mesin OFFLINE / tidak terjangkau (' . $dip . ') — sync dilewati.',
                'total'=>0,'synced'=>0,'failed'=>0
            ];
            log_sync_activity($koneksi, 'bulk_sync', $did, null, 'error', 'Mesin offline, sync dilewati');
            continue;
        }
        $res = sync_all_karyawan_to_device($koneksi, $did);
        $bulk_sync_results[$did] = $res;
        $bulk_sync_results[$did]['device_name'] = $device['nama_mesin'];
        $bulk_sync_results[$did]['device_ip'] = $dip;
        log_sync_activity($koneksi, 'bulk_sync', $did, null, $res['status'] ?? 'error', $res['message'] ?? '');
    }
    }
    if(empty($bulk_sync_results)){
        $bulk_sync_results['none'] = ['device_name'=>'Tidak ada mesin ONLINE','status'=>'error','message'=>'Tidak ada mesin yang terhubung. Cek IP & LAN di Setting Device.','total'=>0,'synced'=>0,'failed'=>0];
    }
}
$tarik_results = null;
if (isset($_POST['tarik_mesin'])) {
    $devices = get_all_devices($koneksi);
    $all_pins = [];
    foreach($devices as $dev){
        if(empty($dev['ip_mesin']) || !test_device_connection($dev['ip_mesin'],$dev['port']??80)) continue;
        $comm=$dev['comm_key']??0;
        $soap="<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
        $resp=send_soap_request($dev['ip_mesin'],$dev['port']??80,$soap);
        if(!$resp) continue;
        preg_match_all('/<Row>(.*?)<\/Row>/s',$resp,$rows);
        foreach($rows[1] as $row){
            preg_match('/<PIN>(.*?)<\/PIN>/',$row,$mPin);
            preg_match('/<Name>(.*?)<\/Name>/',$row,$mName);
            $p=trim($mPin[1]??''); $n=trim($mName[1]??'');
            if($p!=='' && !isset($all_pins[$p])) $all_pins[$p]=$n!==''?$n:"User PIN{$p}";
        }
    }
    $tarik_results=['created'=>[],'skipped'=>[],'duplikat'=>[],'total_di_mesin'=>count($all_pins)];
    $seen_names = []; // nama(lower) => PIN pertama di batch ini (cegah dobel nama)
    foreach($all_pins as $pin=>$name){
        $pin_esc=$koneksi->real_escape_string($pin);
        $cek=$koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$pin_esc' LIMIT 1");
        if($cek && $cek->num_rows>0){ $tarik_results['skipped'][]=$pin; continue; }
        $name_esc=$koneksi->real_escape_string($name);
        $ln=strtolower(trim($name));
        // Nama sudah ada di database dengan No. Absen beda? -> tolak, jangan bikin baris dobel
        $cekNama=$koneksi->query("SELECT no_absen FROM ms_karyawan WHERE LOWER(nama_karyawan)=LOWER('$name_esc') LIMIT 1");
        if($cekNama && $cekNama->num_rows>0){
            $ex=$cekNama->fetch_assoc();
            $tarik_results['duplikat'][]=['pin'=>$pin,'nama'=>$name,'alasan'=>"nama sudah ada di web (No. Absen {$ex['no_absen']})"];
            log_sync_activity($koneksi,'tarik_mesin',$pin,null,'skipped',"PIN $pin $name DITOLAK: nama sudah ada (No. Absen {$ex['no_absen']})");
            continue;
        }
        // Nama dobel di batch tarikan ini dengan PIN beda? -> ambil PIN pertama saja
        if(isset($seen_names[$ln])){
            $tarik_results['duplikat'][]=['pin'=>$pin,'nama'=>$name,'alasan'=>"nama sama dengan PIN {$seen_names[$ln]} (hasil tarik ini)"];
            log_sync_activity($koneksi,'tarik_mesin',$pin,null,'skipped',"PIN $pin $name DITOLAK: dobel dengan PIN {$seen_names[$ln]}");
            continue;
        }
        $seen_names[$ln]=$pin;
        if($koneksi->query("INSERT INTO ms_karyawan (no_absen, nama_karyawan, status_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, no_bpjs, id_jadwal, id_sub_department, tgl_aktif) VALUES ('$pin_esc','$name_esc','Aktif','1','0','','Islam','Laki-laki','','','','','1','1',CURDATE())")){
            $tarik_results['created'][]=['pin'=>$pin,'nama'=>$name,'id'=>$koneksi->insert_id];
            log_sync_activity($koneksi,'tarik_mesin',$pin,null,'success',"PIN $pin $name ditarik dari mesin");
        }
    }
}
$regis_check_results = null;
$regis_check_emp = null;
if (isset($_POST['regis_check'])) {
    $regis_check_id = intval($_POST['regis_check_id'] ?? 0);
    if ($regis_check_id) {
        $empRes = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$regis_check_id LIMIT 1");
        $regis_check_emp = $empRes ? $empRes->fetch_assoc() : null;
        if ($regis_check_emp && !empty($regis_check_emp['no_absen'])) {
            $pin = $regis_check_emp['no_absen'];
            $pushRes = sync_karyawan_to_devices($koneksi, $regis_check_id);
            foreach($pushRes as $did=>$pr) log_sync_activity($koneksi,'regis_push',$did,$regis_check_id,$pr['status'],$pr['message']);
            $devices = get_all_devices($koneksi);
            $regis_check_results = [];
            foreach($devices as $dev){
                if(empty($dev['ip_mesin'])){
                    $regis_check_results[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'has_template'=>false,'online'=>false,'msg'=>'IP kosong'];
                } else {
                    $online = test_device_connection($dev['ip_mesin'],$dev['port']);
                    if(!$online){
                        $regis_check_results[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'has_template'=>false,'online'=>false,'msg'=>'OFFLINE'];
                    } else {
                        $templates = get_fingerprint_templates($dev['ip_mesin'],$dev['port'],$dev['comm_key']??0,$pin);
                        $has = !empty($templates);
                        $regis_check_results[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'has_template'=>$has,'online'=>true,'msg'=>$has?'Ada sidik ('.count($templates).' jari)':'Belum ada sidik'];
                    }
                }
            }
        }
    }
}
$regis_copy_result = null;
if (isset($_POST['regis_copy'])) {
    $regis_copy_id = intval($_POST['regis_copy_id'] ?? 0);
    $source_id = $_POST['regis_source_id'] ?? '';
    if ($regis_copy_id && $source_id) {
        $empRes = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$regis_copy_id LIMIT 1");
        $regis_check_emp = $empRes ? $empRes->fetch_assoc() : null;
        if ($regis_check_emp) {
            $pin = $regis_check_emp['no_absen'];
            $devices = get_all_devices($koneksi);
            $targets=[];
            foreach($devices as $d) if($d['id_mesin']!=$source_id && !empty($d['ip_mesin'])) $targets[]=$d['id_mesin'];
            $regis_copy_result = copy_fingerprint_template($koneksi, $pin, $source_id, $targets);
            foreach($targets as $tid) log_sync_activity($koneksi,'regis_copy',$tid,$regis_copy_id,$regis_copy_result['results'][$tid]['status']??'error',$regis_copy_result['results'][$tid]['message']??'');
            $regis_check_results = null;
        }
    }
}
$regis_direct_copy_result = null;
$regis_direct_copy_emp = null;
if (isset($_POST['regis_direct_copy'])) {
    $did = intval($_POST['regis_direct_copy_id'] ?? 0);
    if ($did) {
        $empRes = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$did LIMIT 1");
        $regis_direct_copy_emp = $empRes ? $empRes->fetch_assoc() : null;
        if ($regis_direct_copy_emp && !empty($regis_direct_copy_emp['no_absen'])) {
            $pin = $regis_direct_copy_emp['no_absen'];
            // push PIN dulu
            $pushRes = sync_karyawan_to_devices($koneksi, $did);
            foreach($pushRes as $pdid=>$pr) log_sync_activity($koneksi,'regis_push',$pdid,$did,$pr['status'],$pr['message']);
            // cari sumber yang punya template
            $devices = get_all_devices($koneksi);
            $sourceId = null;
            foreach($devices as $dev){
                if(empty($dev['ip_mesin'])) continue;
                if(!test_device_connection($dev['ip_mesin'],$dev['port'])) continue;
                $templates = get_fingerprint_templates($dev['ip_mesin'],$dev['port'],$dev['comm_key']??0,$pin);
                if(!empty($templates)){ $sourceId=$dev['id_mesin']; break; }
            }
            if(!$sourceId){
                $regis_direct_copy_result = ['error'=>'Belum ada sidik di mesin mana pun. Tempel dulu di salah satu mesin (Menu → PIN '.$pin.' → Finger → Enroll).'];
            } else {
                $targets=[];
                foreach($devices as $d) if($d['id_mesin']!=$sourceId && !empty($d['ip_mesin']) && test_device_connection($d['ip_mesin'],$d['port'])) $targets[]=$d['id_mesin'];
                if(empty($targets)){
                    $regis_direct_copy_result = ['error'=>'Tidak ada mesin target yang online / sudah ada semua.'];
                } else {
                    $regis_direct_copy_result = copy_fingerprint_template($koneksi, $pin, $sourceId, $targets);
                    foreach($targets as $tid) log_sync_activity($koneksi,'regis_direct_copy',$tid,$did,$regis_direct_copy_result['results'][$tid]['status']??'error',$regis_direct_copy_result['results'][$tid]['message']??'');
                }
            }
        }
    }
}
$bulk_check_results = null;
$bulk_delete_results = null;
if (isset($_POST['bulk_delete_selected'])) {
    $ids = $_POST['selected_ids'] ?? [];
    $bulk_delete_results = ['success'=>0,'fail'=>0,'mesin_ok'=>0,'mesin_gagal'=>0,'details'=>[]];
    foreach($ids as $id){
        $id=intval($id);
        $emp=$koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1");
        $row=$emp?$emp->fetch_assoc():null;
        $no_absen=trim($row['no_absen'] ?? '');
        $nama_del=$row['nama_karyawan'] ?? '';
        $mesin_info = [];
        if(!empty($nama_del)){
            // REAL DELETE ke mesin BY NAMA (PIN tidak dipakai).
            // PIN-sharing tetap di-skip otomatis di dalam fungsi.
            $delRes = delete_karyawan_from_devices($koneksi, $nama_del, false);
            foreach($delRes as $did=>$dr){
                if($did==='_dry_run_mode' || $did==='_dry_run_info') continue;
                if(!isset($dr['status'])) continue;
                log_sync_activity($koneksi,'karyawan_bulk_delete',$did,$id,$dr['status'],"Hapus $nama_del (PIN:$no_absen): ".$dr['message']);
                $mesin_info[] = ($dr['device'] ?? $did) . ': ' . $dr['status'] . ' (' . $dr['message'] . ')';
                if($dr['status']=='success') $bulk_delete_results['mesin_ok']++;
                elseif($dr['status']=='error') $bulk_delete_results['mesin_gagal']++;
            }
        }
        if($koneksi->query("DELETE FROM ms_karyawan WHERE id_karyawan=$id")) { $bulk_delete_results['success']++; $bulk_delete_results['details'][] = ['nama'=>$nama_del,'pin'=>$no_absen,'mesin'=>$mesin_info]; } else $bulk_delete_results['fail']++;
    }
}
if (isset($_POST['bulk_check_selected'])) {
    $ids = $_POST['selected_ids'] ?? [];
    $bulk_check_results = [];
    foreach($ids as $id){
        $id=intval($id);
        $emp=$koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1");
        $row=$emp?$emp->fetch_assoc():null;
        if(!$row || empty($row['no_absen'])) continue;
        $pin=$row['no_absen'];
        $devices=get_all_devices($koneksi);
        $perDevice=[];
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])){ $perDevice[$dev['id_mesin']]=['device'=>$dev['nama_mesin'],'has'=>false,'online'=>false]; continue; }
            $online=test_device_connection($dev['ip_mesin'],$dev['port']);
            if(!$online){ $perDevice[$dev['id_mesin']]=['device'=>$dev['nama_mesin'],'has'=>false,'online'=>false]; continue; }
            $templates=get_fingerprint_templates($dev['ip_mesin'],$dev['port'],$dev['comm_key']??0,$pin);
            $perDevice[$dev['id_mesin']]=['device'=>$dev['nama_mesin'],'has'=>!empty($templates),'online'=>true];
        }
        $bulk_check_results[]=['karyawan'=>$row,'perDevice'=>$perDevice];
    }
}
$bulk_copy_selected_result = null;
if (isset($_POST['bulk_copy_selected'])) {
    $ids = $_POST['selected_ids'] ?? [];
    $bulk_copy_selected_result = ['success'=>0,'fail'=>0,'details'=>[]];
    foreach($ids as $id){
        $id=intval($id);
        $emp=$koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1");
        $row=$emp?$emp->fetch_assoc():null;
        if(!$row || empty($row['no_absen'])){ $bulk_copy_selected_result['fail']++; continue; }
        $pin=$row['no_absen'];
        $devices=get_all_devices($koneksi);
        $sourceId=null;
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            if(!test_device_connection($dev['ip_mesin'],$dev['port'])) continue;
            $templates=get_fingerprint_templates($dev['ip_mesin'],$dev['port'],$dev['comm_key']??0,$pin);
            if(!empty($templates)){ $sourceId=$dev['id_mesin']; break; }
        }
        if(!$sourceId){ $bulk_copy_selected_result['fail']++; $bulk_copy_selected_result['details'][]=['karyawan'=>$row,'msg'=>'Belum ada sidik di mesin mana pun']; continue; }
        $targets=[];
        foreach($devices as $d) if($d['id_mesin']!=$sourceId && !empty($d['ip_mesin']) && test_device_connection($d['ip_mesin'],$d['port'])) $targets[]=$d['id_mesin'];
        if(empty($targets)){ $bulk_copy_selected_result['fail']++; continue; }
        $res=copy_fingerprint_template($koneksi,$pin,$sourceId,$targets);
        $ok=true; foreach($targets as $tid) if(($res['results'][$tid]['status']??'error')!='success') $ok=false;
        if($ok) $bulk_copy_selected_result['success']++; else $bulk_copy_selected_result['fail']++;
        $bulk_copy_selected_result['details'][]=['karyawan'=>$row,'res'=>$res];
    }
}
?>
<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0">
                    <i class="fas fa-users mr-2"></i>Data Karyawan
                </h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Kelola informasi detail dan upah seluruh karyawan</p>
            </div>
            <div class="w-full md:w-auto mt-2 md:mt-0 flex flex-col md:flex-row gap-2 flex-wrap">
                 <form method="POST" id="formSyncAll">
                    <button type="button" id="btnSyncAll" class="flex md:inline-flex justify-center items-center bg-amber-500 hover:bg-amber-600 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                        <i class="fas fa-cloud-upload-alt mr-1.5"></i> Sync Semua ke Mesin
                    </button>
                </form>
                <script>
                (function(){
                    function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
                    var btn = document.getElementById('btnSyncAll');
                    if(!btn) return;
                    btn.addEventListener('click', function(){
                        Swal.fire({title:'Cek isi mesin...', allowOutsideClick:false, didOpen:function(){ Swal.showLoading(); }});
                        $.post('page/karyawan/ajax_missing.php', function(res){
                            if(!res || !res.success){
                                Swal.fire({title:'Gagal', text:'Tidak bisa cek isi mesin. Coba lagi.', icon:'error', confirmButtonColor:'#f59e0b'});
                                return;
                            }
                            var unver = (res.unverified||[]).map(function(d){ return '• ' + esc(d); }).join('<br>');
                            if(!res.missing || res.missing.length===0){
                                if(unver !== ''){
                                    Swal.fire({title:'Info', html:'<div style="text-align:left;font-size:14px;">Karyawan sudah ada di semua mesin SOAP yang dicek.<br><br><span style="color:#6b7280;">Belum bisa dicek:<br>' + unver + '</span></div>', icon:'success', confirmButtonColor:'#16a34a'});
                                } else {
                                    Swal.fire({title:'Info', text:'Karyawan sudah ada di mesin semua.', icon:'success', confirmButtonColor:'#16a34a'});
                                }
                                return;
                            }
                            var pins = res.missing.map(function(m){ return m.pin; });
                            var nm = res.missing.map(function(m){ return '• ' + esc(m.pin) + ' - ' + esc(m.nama) + ((m.kurang_di && m.kurang_di.length) ? ' <span style="color:#6b7280;">(kurang di: ' + esc(m.kurang_di.join(', ')) + ')</span>' : ''); }).join('<br>');
                            var on = (res.online||[]).map(function(d){ return '• ' + esc(d); }).join('<br>') || '-';
                            var off = (res.offline||[]).map(function(d){ return '• ' + esc(d); }).join('<br>');
                            Swal.fire({
                                title: 'Sync ke Mesin?',
                                html: '<div style="text-align:left;font-size:14px;">' +
                                       'Belum lengkap di mesin (kurang di sebagian/seluruh mesin online) (' + res.missing.length + '):<br>' +
                                      '<div style="max-height:180px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;padding:8px;">' + nm + '</div>' +
                                      '<br>Mesin online:<br>' + on +
                                      (off !== '-' ? '<br><br><span style="color:#6b7280;">Tidak dicek (offline/IP kosong):<br>' + off + '</span>' : '') +
                                      (unver !== '' ? '<br><br><span style="color:#6b7280;">Belum bisa dicek:<br>' + unver + '</span>' : '') +
                                      '</div>',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#f59e0b',
                                cancelButtonColor: '#6b7280',
                                confirmButtonText: 'Ya, Sync ' + res.missing.length + ' Ini',
                                cancelButtonText: 'Batal'
                            }).then(function(r){
                                if(r.isConfirmed){
                                    var f = document.getElementById('formSyncAll');
                                    var h = document.createElement('input');
                                    h.type = 'hidden'; h.name = 'sync_all_karyawan'; h.value = '1';
                                    f.appendChild(h);
                                    var h2 = document.createElement('input');
                                    h2.type = 'hidden'; h2.name = 'sync_pins'; h2.value = JSON.stringify(pins);
                                    f.appendChild(h2);
                                    f.submit();
                                }
                            });
                        }, 'json').fail(function(){
                            Swal.fire({title:'Gagal', text:'Tidak bisa cek isi mesin. Coba lagi.', icon:'error', confirmButtonColor:'#f59e0b'});
                        });
                    });
                })();
                </script>
                <form method="POST" onsubmit="return confirm('Tarik SEMUA PIN dari mesin X606S yang belum ada di web jadi karyawan baru?')">
                    <button type="submit" name="tarik_mesin" class="flex md:inline-flex justify-center items-center bg-emerald-600 hover:bg-emerald-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                        <i class="fas fa-download mr-1.5"></i> Tarik dari Mesin
                    </button>
                </form>

                <a href="?page=karyawan&aksi=tambah" class="flex md:inline-flex justify-center items-center bg-indigo-600 hover:bg-indigo-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-plus mr-1.5"></i> Tambah Karyawan
                </a>
                <!-- Tombol Debug Mesin disembunyikan (halaman tetap bisa dibuka via URL langsung) -->
                <?php if (strtolower($role ?? '') == 'admin master'): ?>
                <a href="?page=karyawan&aksi=cek_pin" class="flex md:inline-flex justify-center items-center bg-teal-600 hover:bg-teal-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-search mr-1.5"></i> Cek PIN Mesin
                </a>
                <?php endif; ?>
                <a href="?page=karyawan&aksi=jadwal_excel" class="flex md:inline-flex justify-center items-center bg-amber-600 hover:bg-amber-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-calendar-alt mr-1.5"></i> Ubah Jadwal Excel
                </a>
                <?php if (strtolower($role ?? '') == 'admin master'): ?>
                <a href="?page=karyawan&aksi=fingerprint_backup" class="flex md:inline-flex justify-center items-center bg-cyan-600 hover:bg-cyan-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-database mr-1.5"></i> Backup Sidik Jari
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php if(!empty($tarik_results)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border <?= !empty($tarik_results['created']) ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-gray-50' ?>">
            <h4 class="font-semibold mb-2 <?= !empty($tarik_results['created']) ? 'text-emerald-800' : 'text-gray-600' ?>"><i class="fas fa-download mr-2"></i>Hasil Tarik dari Mesin: <?= count($tarik_results['created']) ?> baru</h4>
            <p class="text-xs text-gray-600 mb-2">Total di mesin: <?= $tarik_results['total_di_mesin'] ?> | Sudah ada: <?= count($tarik_results['skipped']) ?><?= !empty($tarik_results['duplikat']) ? ' | <span class="text-amber-600 font-bold">Ditolak (dobel nama): ' . count($tarik_results['duplikat']) . '</span>' : '' ?></p>
            <?php if(!empty($tarik_results['created'])): ?>
            <div class="flex flex-wrap gap-2 mb-2">
                <?php foreach($tarik_results['created'] as $c): ?>
                <span class="px-2 py-1 bg-white border border-emerald-200 rounded text-xs"><i class="bi bi-check-circle-fill text-emerald-600"></i> PIN <?= htmlspecialchars($c['pin']) ?> - <?= htmlspecialchars($c['nama']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php else: ?><p class="text-xs text-gray-500">Tidak ada PIN baru di mesin</p><?php endif; ?>
            <?php if(!empty($tarik_results['duplikat'])): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach($tarik_results['duplikat'] as $d): ?>
                <span class="px-2 py-1 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700" title="<?= htmlspecialchars($d['alasan']) ?>"><i class="bi bi-slash-circle"></i> PIN <?= htmlspecialchars($d['pin']) ?> - <?= htmlspecialchars($d['nama']) ?> (<?= htmlspecialchars($d['alasan']) ?>)</span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($sync_both_results)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border border-violet-200 bg-violet-50">
            <h4 class="font-semibold mb-2 text-violet-800"><i class="fas fa-arrows-rotate mr-2"></i>Hasil Sync 2 Arah</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs font-bold text-violet-700 mb-1">Web → Mesin</p>
                    <?php foreach($sync_both_results['push'] as $did=>$r): ?>
                    <div class="p-2 bg-white rounded border text-xs mb-1"><b><?= htmlspecialchars($r['device_name'] ?? 'Device') ?></b>: <?= htmlspecialchars($r['message']) ?> (<?= $r['synced']??0 ?>/<?= $r['total']??0 ?>)</div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <p class="text-xs font-bold text-emerald-700 mb-1">Mesin → Web (<?= count($sync_both_results['pull']['created']) ?> baru)</p>
                    <?php if(!empty($sync_both_results['pull']['created'])): foreach($sync_both_results['pull']['created'] as $c): ?>
                    <div class="p-2 bg-white rounded border text-xs mb-1"><i class="bi bi-check-circle-fill text-emerald-600"></i> PIN <?= htmlspecialchars($c['pin']) ?> - <?= htmlspecialchars($c['nama']) ?></div>
                    <?php endforeach; else: ?><p class="text-xs text-gray-500">Tidak ada PIN baru</p><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if(!empty($bulk_sync_results)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border <?php echo array_reduce($bulk_sync_results, fn($c,$r)=>$c && $r['status']=='success', true) ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'; ?>">
            <h4 class="font-semibold mb-2 <?php echo array_reduce($bulk_sync_results, fn($c,$r)=>$c && $r['status']=='success', true) ? 'text-green-800' : 'text-amber-800'; ?>">
                <i class="fas fa-<?php echo array_reduce($bulk_sync_results, fn($c,$r)=>$c && $r['status']=='success', true) ? 'check-circle' : 'exclamation-triangle'; ?> mr-2"></i>Hasil Sync Semua ke Mesin
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php foreach($bulk_sync_results as $did=>$r):
                    $st = $r['status'] ?? 'error';
                    $badge='bg-red-100 text-red-800'; $label='GAGAL'; $border='border-red-200'; $txt='text-red-600';
                    if($st=='success'){ $badge='bg-green-100 text-green-800'; $label='BERHASIL'; $border='border-green-200'; $txt='text-green-600'; }
                    elseif($st=='warning'){ $badge='bg-amber-100 text-amber-800'; $label='SEBAGIAN'; $border='border-amber-200'; $txt='text-amber-600'; }
                    elseif($st=='offline'||$st=='skipped'){ $badge='bg-gray-100 text-gray-600'; $label=strtoupper($st); $border='border-gray-200'; $txt='text-gray-500'; }
                ?>
                <div class="p-3 rounded-lg border bg-white <?php echo $border; ?>">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($r['device_name'] ?? $r['device'] ?? 'Device'); ?></p>
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?php echo $badge; ?>"><?php echo $label; ?></span>
                    </div>
                    <?php if(!empty($r['device_ip'])): ?><p class="text-xs text-gray-400"><?php echo htmlspecialchars($r['device_ip']); ?></p><?php endif; ?>
                    <p class="text-xs <?php echo $txt; ?> mt-1"><?php echo htmlspecialchars($r['message']); ?></p>
                    <?php if(isset($r['total']) && $r['total']>0): ?><p class="text-xs text-gray-500 mt-1"><?php echo $r['synced']; ?>/<?php echo $r['total']; ?> berhasil<?php echo !empty($r['failed'])?' ('.$r['failed'].' gagal)':''; ?><?php echo !empty($r['skipped'])?' ('.$r['skipped'].' skip)':''; ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if(!empty($regis_direct_copy_result)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border <?= isset($regis_direct_copy_result['error']) ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' ?>">
            <h4 class="font-semibold mb-2 <?= isset($regis_direct_copy_result['error']) ? 'text-red-800' : 'text-green-800' ?>"><i class="fas fa-copy mr-2"></i>Hasil Copy Langsung <?= htmlspecialchars($regis_direct_copy_emp['nama_karyawan'] ?? '') ?> (PIN <?= htmlspecialchars($regis_direct_copy_emp['no_absen'] ?? '') ?>)</h4>
            <?php if(isset($regis_direct_copy_result['error'])): ?><p class="text-sm text-red-600"><?= htmlspecialchars($regis_direct_copy_result['error']) ?></p><?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php foreach($regis_direct_copy_result['results'] as $did=>$r): ?>
                <div class="p-3 rounded-lg border bg-white <?= $r['status']=='success'?'border-green-200':'border-red-200' ?>"><p class="font-semibold text-sm"><?= htmlspecialchars($r['device'] ?? 'Device') ?></p><p class="text-xs <?= $r['status']=='success'?'text-green-600':'text-red-600' ?>"><?= htmlspecialchars($r['message']) ?></p></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($regis_copy_result)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border <?= isset($regis_copy_result['error']) ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' ?>">
            <h4 class="font-semibold mb-2 <?= isset($regis_copy_result['error']) ? 'text-red-800' : 'text-green-800' ?>"><i class="fas fa-copy mr-2"></i>Hasil Copy</h4>
            <?php if(isset($regis_copy_result['error'])): ?><p class="text-sm text-red-600"><?= htmlspecialchars($regis_copy_result['error']) ?></p><?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <?php foreach($regis_copy_result['results'] as $did=>$r): ?>
                <div class="p-3 rounded-lg border bg-white <?= $r['status']=='success'?'border-green-200':'border-red-200' ?>"><p class="font-semibold text-sm"><?= htmlspecialchars($r['device'] ?? 'Device') ?></p><p class="text-xs <?= $r['status']=='success'?'text-green-600':'text-red-600' ?>"><?= htmlspecialchars($r['message']) ?></p></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($regis_check_results) && !empty($regis_check_emp)): 
            $hasAny=array_reduce($regis_check_results, fn($c,$r)=>$c || $r['has_template'], false);
            $missing=array_filter($regis_check_results, fn($r)=>$r['online'] && !$r['has_template']);
            $sourceId=null; foreach($regis_check_results as $did=>$r) if($r['has_template'] && $r['online']){ $sourceId=$did; break; }
        ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border <?php echo $hasAny && empty($missing) ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'; ?>">
            <h4 class="font-semibold text-indigo-800 mb-2"><i class="fas fa-fingerprint mr-2"></i>Registrasi <?= htmlspecialchars($regis_check_emp['nama_karyawan']) ?> (PIN <?= htmlspecialchars($regis_check_emp['no_absen']) ?>)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                <?php foreach($regis_check_results as $did=>$r): ?>
                <div class="p-3 rounded-lg border bg-white <?= $r['online'] ? ($r['has_template']?'border-green-200':'border-amber-200') : 'border-red-200' ?>">
                    <p class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($r['device']) ?></p>
                    <p class="text-xs <?= !$r['online']?'text-red-600':($r['has_template']?'text-green-600':'text-amber-600') ?>"><?= htmlspecialchars($r['msg']) ?></p>
                    <p class="text-xs text-gray-500"><?= $r['online']?'ONLINE':'OFFLINE' ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="p-3 bg-white rounded border text-sm text-gray-700">
                <?php if(!$hasAny): ?>
                    <span class="text-amber-600 font-semibold">Belum ada sidik di mesin mana pun.</span> Pergi ke <b>Mesin → Menu → User Manage → Cari PIN <?= htmlspecialchars($regis_check_emp['no_absen']) ?> → Finger/Face → Enroll</b> → Tempel 3x.
                <?php elseif(!empty($missing)): ?>
                    Sudah ada sidik di beberapa mesin, belum di: <b><?= implode(', ', array_map(fn($r)=>$r['device'], $missing)) ?></b>.
                    <?php if($sourceId): ?>
                    <form method="POST" class="mt-3 inline-flex">
                        <input type="hidden" name="regis_copy_id" value="<?= $regis_check_emp['id_karyawan'] ?>">
                        <input type="hidden" name="regis_source_id" value="<?= $sourceId ?>">
                        <button type="submit" name="regis_copy" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded-lg shadow-sm"><i class="fas fa-copy mr-1.5"></i> Copy ke Mesin yang Belum Ada</button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-green-600 font-semibold">Sudah ada sidik di semua mesin yang online.</span> Bisa langsung absen.
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!empty($bulk_delete_results)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border <?= $bulk_delete_results['fail']==0?'border-green-200 bg-green-50':'border-amber-200 bg-amber-50' ?>">
            <p class="font-semibold <?= $bulk_delete_results['fail']==0?'text-green-800':'text-amber-800' ?>"><i class="fas fa-trash mr-2"></i>Hapus Massal: <?= $bulk_delete_results['success'] ?> berhasil, <?= $bulk_delete_results['fail'] ?> gagal | Mesin: <?= $bulk_delete_results['mesin_ok'] ?? 0 ?> sukses, <?= $bulk_delete_results['mesin_gagal'] ?? 0 ?> gagal</p>
            <?php if(!empty($bulk_delete_results['details'])): ?>
            <div class="mt-2 space-y-1 max-h-48 overflow-y-auto text-xs">
                <?php foreach($bulk_delete_results['details'] as $d): ?>
                <div class="p-2 bg-white rounded border"><b><?= htmlspecialchars($d['nama']) ?> (PIN <?= htmlspecialchars($d['pin']) ?>)</b><br><span class="text-gray-500"><?= htmlspecialchars(implode(' | ', $d['mesin'])) ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($bulk_check_results)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border border-indigo-200 bg-indigo-50">
            <h4 class="font-semibold text-indigo-800 mb-3"><i class="fas fa-fingerprint mr-2"></i>Hasil Cek Sidik (<?= count($bulk_check_results) ?> karyawan)</h4>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php foreach($bulk_check_results as $bc): ?>
                <div class="p-3 bg-white rounded-lg border">
                    <p class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($bc['karyawan']['nama_karyawan']) ?> (PIN <?= htmlspecialchars($bc['karyawan']['no_absen']) ?>)</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                        <?php foreach($bc['perDevice'] as $did=>$pd): ?>
                        <div class="text-xs p-2 rounded border <?= $pd['online']?($pd['has']?'bg-green-50 border-green-200 text-green-700':'bg-amber-50 border-amber-200 text-amber-700'):'bg-red-50 border-red-200 text-red-700' ?>">
                            <?= htmlspecialchars($pd['device']) ?><br><span class="font-semibold"><?= $pd['online']?($pd['has']?'Ada sidik':'Belum ada'): 'OFFLINE' ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if(!empty($bulk_copy_selected_result)): ?>
        <div class="mx-4 mt-4 p-4 rounded-xl border <?= $bulk_copy_selected_result['fail']==0?'border-green-200 bg-green-50':'border-amber-200 bg-amber-50' ?>">
            <h4 class="font-semibold <?= $bulk_copy_selected_result['fail']==0?'text-green-800':'text-amber-800' ?>"><i class="fas fa-copy mr-2"></i>Hasil Copy Sidik Massal: <?= $bulk_copy_selected_result['success'] ?> berhasil, <?= $bulk_copy_selected_result['fail'] ?> gagal</h4>
            <div class="mt-2 space-y-2 max-h-64 overflow-y-auto text-xs">
                <?php foreach($bulk_copy_selected_result['details'] as $det): ?>
                <div class="p-2 bg-white rounded border flex justify-between"><span><?= htmlspecialchars($det['karyawan']['nama_karyawan']) ?> (<?= $det['karyawan']['no_absen'] ?>)</span><span><?= htmlspecialchars($det['msg'] ?? ($det['res']['error'] ?? 'OK')) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="mx-4 mt-2 p-3 bg-gray-50 rounded-lg border flex flex-col md:flex-row gap-3 items-center justify-between">
            <div class="flex items-center gap-3">
                <span id="bulkCount" class="text-sm font-semibold text-gray-600 px-3 py-1 bg-white rounded-full border">0 terpilih</span>
                <span class="text-xs text-gray-500">Centang untuk aksi massal</span>
            </div>
            <div class="flex gap-2">
                <button type="button" id="btnBulkCopy" class="hidden bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-4 rounded shadow-sm"><i class="fas fa-copy mr-1.5"></i> Copy Sidik Terpilih</button>
                <button type="button" id="btnBulkDelete" class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium py-2 px-4 rounded shadow-sm"><i class="fas fa-trash mr-1.5"></i> Hapus Terpilih</button>
                <span id="swMini" class="text-xs text-gray-500 self-center" title="Jadwal auto-sweep sidik jari">Sweep: …</span>
            </div>
        </div>
        <form method="POST" id="bulkForm">
        <?php
        $__enr = [];
        $__qdev = $koneksi->query("SELECT id_mesin, nama_mesin, ip_mesin FROM tb_mesin ORDER BY no_mesin");
        while ($__qdev && ($__dd = $__qdev->fetch_assoc())) { if (!empty($__dd['ip_mesin'])) $__enr[] = ['id' => $__dd['id_mesin'], 'nama' => $__dd['nama_mesin'], 'ip' => $__dd['ip_mesin']]; }
        if (!function_exists('sweep_get_config')) { @include_once __DIR__ . '/../../fingerprint_db_helper.php'; }
        $__swm = ['enabled' => false, 'label' => '-', 'remain' => 0];
        if (function_exists('sweep_get_config') && isset($koneksi)) {
            $__swc2 = sweep_get_config($koneksi);
            $__swm['enabled'] = !empty($__swc2['enabled']);
            $__iv = (int)$__swc2['interval_jam'];
            $__swm['label'] = $__iv === -1 ? '1 menit' : ($__iv === 0 ? '5 menit' : $__iv . ' jam');
            if ($__swm['enabled'] && !empty($__swc2['last_run'])) {
                $__swm['remain'] = max(0, strtotime($__swc2['last_run']) + sweep_interval_seconds($__iv) - time());
            }
        }
        ?>
        <script type="application/json" id="enroll-machines-data"><?= json_encode($__enr) ?></script>
        <script type="application/json" id="sweep-mini-data"><?= json_encode($__swm) ?></script>
        <script>
        // Indikator + pemicu sweep di halaman karyawan (mandiri, tanpa reload).
        // Hitung mundur habis -> panggil endpoint sweep via background XHR.
        // Server yang menilai due/lock/cooldown, jadi aman dobel dengan runner global.
        (function(){
          var D = null;
          try { D = JSON.parse(document.getElementById('sweep-mini-data').textContent); } catch(e){ D = null; }
          var el = document.getElementById('swMini');
          if (!el || !D) return;
          function pad(n){ return (n<10?'0':'')+n; }
          function fmt(sec){ var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=sec%60; return pad(h)+':'+pad(m)+':'+pad(s); }
          if (!D.enabled) { el.textContent = 'Sweep: Mati'; return; }
          var EP = 'page/karyawan/ajax_sweep_run.php';
          var cdTimer = null;
          function stopCountdown(){ try { if (cdTimer) clearInterval(cdTimer); } catch(e){} cdTimer = null; }
          function countdown(sec){
            stopCountdown();
            var total = Math.max(0, sec), t0 = performance.now(), label = D.label, fired = false;
            var fire = function(){
              if (fired) return; fired = true;
              stopCountdown();
              el.textContent = 'Sweep: Nyala • tiap ' + label + ' • waktunya tiba…';
              runSweep();
            };
            var tick = function(){
              var d = Math.max(0, total - Math.floor((performance.now() - t0)/1000));
              if (d <= 0) { fire(); return; }
              el.textContent = 'Sweep: Nyala • tiap ' + label + ' • jalan ' + fmt(d);
            };
            tick();
            if (total > 0) cdTimer = setInterval(tick, 1000);
          }
          function runSweep(){
            el.textContent = 'Sweep: jalan…';
            var xhr = new XMLHttpRequest();
            var done = false;
            function finish(ok, msg, nextIn){
              if (done) return; done = true;
              if (ok) {
                el.textContent = 'Sweep: selesai. Berikutnya ' + fmt(Math.max(0, nextIn)) + '.';
                countdown(Math.max(30, nextIn));
              } else {
                el.textContent = 'Sweep: ' + msg + '. Coba lagi 60 detik.';
                countdown(60);
              }
            }
            xhr.open('POST', EP, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function(){
              var m = xhr.responseText.match(/__RESULT__(\{.*\})/);
              var info = null;
              try { info = m ? JSON.parse(m[1]) : null; } catch(e){}
              if (info && info.ran) finish(true, '', info.next_in || 0);
              else if (info && info.reason) finish(false, 'dilewati (' + info.reason + ')');
              else finish(false, 'gagal');
            };
            xhr.onerror = function(){ finish(false, 'koneksi gagal'); };
            xhr.send('start=1');
          }
          if (!D.remain) countdown(3); else countdown(D.remain);
        })();
        </script>
        <!-- Auto-sweep global dipegang index.php (assets/js/sweep-auto.js): berlaku di semua halaman termasuk ini. -->
        <div class="p-0">
            <div class="table-responsive px-3 py-3">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2 px-2 text-center w-10"><input type="checkbox" id="bulk_select_all" class="w-4 h-4 text-indigo-600 rounded"></th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">No</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">No. Absen</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Nama Karyawan</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Gol</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Jenis Kelamin</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Kontak & Dokumen</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Sidik Jari</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Mesin Finger</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center" style="min-width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php
                        $no = 1;
                        $tampil = $koneksi->query("SELECT k.*, o.OS_DHK as label_os, g.golongan as label_gol 
                                                 FROM ms_karyawan k
                                                 LEFT JOIN ms_os_dhk o ON k.id_os_dhk = o.id_os_dhk
                                                 LEFT JOIN ms_golongan g ON k.id_golongan = g.id_golongan");
                        while ($datakaryawan = $tampil->fetch_assoc()) {
                            $status_class = ($datakaryawan['status_karyawan'] == 'Aktif') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                        ?>
                            <tr class="hover:bg-blue-50/50 transition-colors">
                                <td class="text-center"><input type="checkbox" name="selected_ids[]" value="<?= $datakaryawan['id_karyawan'] ?>" class="bulk-check w-4 h-4 text-indigo-600 rounded"></td>
                                <td data-label="No" class="md:text-center text-sm text-gray-600 font-medium"><?= $no++ ?></td>
                                <td data-label="No. Absen" class="text-sm font-semibold text-gray-700"><?= $datakaryawan['no_absen'] ?></td>
                                <td data-label="Nama Karyawan">
                                    <div class="font-bold text-gray-900"><?= $datakaryawan['nama_karyawan'] ?></div>
                                    <div class="text-xs text-gray-400 italic"><?= $datakaryawan['label_os'] ?></div>
                                </td>
                                <td data-label="Gol" class="md:text-center text-sm font-bold text-indigo-600"><?= $datakaryawan['label_gol'] ?></td>
                                <td data-label="Jenis Kelamin" class="md:text-center text-sm text-gray-600"><?= $datakaryawan['jenis_kelamin'] ?></td>
                                <td data-label="Kontak & Dokumen" class="text-xs py-2">
                                    <div class="block mb-1">
                                        <span class="inline-block w-12 text-gray-400 font-medium">KTP:</span>
                                        <span class="text-gray-700 font-semibold"><?= $datakaryawan['no_ktp'] ?></span>
                                    </div>

                                    <div class="block">
                                        <span class="inline-block w-12 text-gray-400 font-medium">BPJS:</span>
                                        <span class="text-gray-700 font-semibold"><?= $datakaryawan['no_bpjs'] ?></span>
                                    </div>

                                    <div class="block">
                                        <span class="inline-block w-12 text-gray-400 font-medium">Telp:</span>
                                        <span class="text-gray-700 font-semibold"><?= $datakaryawan['no_telp'] ?? '-' ?></span>
                                    </div>
                                    
                                    <div class="block">
                                        <span class="inline-block w-12 text-gray-400 font-medium">WA:</span>
                                        <span class="text-gray-700 font-semibold"><?= $datakaryawan['whatsapp'] ?? '-' ?></span>
                                    </div>
                                </td>
                                <td data-label="Finger" class="md:text-center finger-cell" data-id="<?= $datakaryawan['id_karyawan'] ?>"><span class="text-xs text-gray-400">-</span><button type="button" class="hidden btn-cek-finger" data-id="<?= $datakaryawan['id_karyawan'] ?>"></button></td>
                                <td data-label="Mesin" class="md:text-center mesin-cell" data-id="<?= $datakaryawan['id_karyawan'] ?>"><span class="text-xs text-gray-400">-</span><button type="button" class="hidden btn-cek-mesin" data-id="<?= $datakaryawan['id_karyawan'] ?>"></button></td>
                                <td data-label="Aksi" class="md:text-center">
                                    <div class="flex items-center md:justify-center gap-2 action-btn-group">
                                        <a href="?page=karyawan&aksi=view&id=<?= $datakaryawan['id_karyawan'] ?>" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white rounded-lg transition-all flex justify-center items-center" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?page=karyawan&aksi=ubah&id=<?= $datakaryawan['id_karyawan'] ?>" class="p-2 bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white rounded-lg transition-all flex justify-center items-center" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="p-2 bg-purple-50 text-purple-600 hover:bg-purple-600 hover:text-white rounded-lg transition-all flex justify-center items-center btn-regis-copy" data-id="<?= $datakaryawan['id_karyawan'] ?>" data-pin="<?= htmlspecialchars($datakaryawan['no_absen']) ?>" data-nama="<?= htmlspecialchars($datakaryawan['nama_karyawan']) ?>" title="Copy Finger">
                                            <i class="fas fa-fingerprint"></i>
                                        </button>
                                        <button type="button" class="text-rose-500 hover:text-rose-700 transition-colors btn-delete-karyawan bg-transparent border-0 p-1 flex justify-center items-center" 
                                                data-id="<?= $datakaryawan['id_karyawan'] ?>" 
                                                data-nama="<?= htmlspecialchars($datakaryawan['nama_karyawan']) ?>"
                                                title="Hapus">
                                            <i class="fas fa-trash-alt text-lg"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</form>

<style>
    /* Card Styling */
    .panel-primary {
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .box-header {
        padding: 15px 20px !important;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* Table Styling */
    .table thead th {
        background-color: #f8f9fa;
        color: #333;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 1px;
        border-bottom: 2px solid #dee2e6 !important;
        vertical-align: middle;
    }

    .table tbody td {
        vertical-align: middle !important;
        font-size: 13px;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(95, 158, 160, 0.1) !important;
        transition: 0.3s;
    }

    /* Custom Badges untuk Status */
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
    }

    /* Button Styling */
    .btn {
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
        transition: 0.2s;
    }

    .btn-info {
        background-color: #5bc0de;
        border: none;
    }

    .btn-info:hover {
        background-color: #31b0d5;
        transform: translateY(-1px);
    }

    .btn-warning {
        color: #fff !important;
    }

    /* Utility */
    .m-b-10 {
        margin-bottom: 10px;
    }

    .p-20 {
        padding: 20px;
    }

    /* 1. Reset wrapper agar tidak menggunakan float bawaan DataTables */
    .dataTables_wrapper {
        display: block !important;
    }

    /* 2. Memaksa area atas (Length & Filter) menjadi satu baris sejajar */
    .dataTables_wrapper::before,
    .dataTables_wrapper::after {
        display: none !important;
    }

    /* 3. Membuat container fleksibel untuk Length (kiri) dan Filter (kanan) */
    #dataTables-example_wrapper .row:first-child {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-bottom: 20px !important;
        width: 100% !important;
    }

    /* 4. Styling Tampil _MENU_ (Kiri) */
    .dataTables_length {
        display: flex !important;
        align-items: center !important;
    }

    .dataTables_length label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_length select {
        padding: 5px 10px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
    }

    /* 5. Styling Cari: (Kanan) */
    .dataTables_filter {
        text-align: right !important;
        display: flex !important;
        justify-content: flex-end !important;
    }

    .dataTables_filter label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_filter input {
        padding: 6px 12px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
        width: 200px !important;
    }

    /* --- STYLING PAGINATE (PREV/NEXT) --- */
    .dataTables_wrapper .dataTables_paginate {
        display: flex !important;
        justify-content: flex-end !important;
        align-items: center !important;
        gap: 4px !important;
        padding-top: 15px !important;
    }

    .dataTables_paginate .paginate_button {
        border: 1px solid #e2e8f0 !important;
        background: white !important;
        border-radius: 6px !important;
        padding: 5px 12px !important;
        color: #475569 !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #f8fafc !important;
        color: #2563eb !important;
        border-color: #cbd5e1 !important;
    }

    h3 {
        color: #2563eb !important;
    }

    .dataTables_paginate .paginate_button.current {
        background: #2563eb !important;
        border-color: #2563eb !important;
        color: white !important;
    }

    .dataTables_paginate .paginate_button.disabled {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
        cursor: not-allowed !important;
    }

    /* --- STYLING INFO --- */
    .dataTables_wrapper .dataTables_info {
        padding-top: 20px !important;
        color: #64748b !important;
        font-size: 13px !important;
    }

    /* --- FIX BOTTOM ROW OVERLAP --- */
    #dataTables-example_wrapper .row:last-child {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        flex-wrap: wrap !important;
        gap: 15px !important;
        margin-top: 15px !important;
        padding: 0 5px !important;
    }

    /* --- FIX TABEL KEPOTONG --- */
    .table-responsive{overflow-x:auto!important;-webkit-overflow-scrolling:touch!important}
    #dataTables-example{min-width:1100px!important}
    #dataTables-example th, #dataTables-example td{white-space:nowrap!important}
    #dataTables-example th:nth-child(4), #dataTables-example td:nth-child(4){min-width:160px}
    #dataTables-example th:nth-child(7), #dataTables-example td:nth-child(7){min-width:140px}
    /* =========================================
       KHUSUS TAMPILAN MOBILE DIPERBAIKI DI SINI
       ========================================= */
    @media screen and (max-width: 768px) {
        .table-responsive {
            padding: 12px !important;
        }

        /* Merapikan form cari dan baris dataTables di HP */
        #dataTables-example_wrapper .row:first-child {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 15px;
        }
        .dataTables_filter, .dataTables_length {
            width: 100% !important;
            justify-content: flex-start !important;
        }
        .dataTables_filter input {
            width: 100% !important;
            max-width: 100% !important;
        }
        .dataTables_paginate {
            justify-content: center !important;
            flex-wrap: wrap;
        }

        .table-modern thead {
            display: none !important;
        }

        .table-modern tbody tr {
            display: block;
            margin-bottom: 1.5rem; /* Jarak antar kotak dilebarkan */
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px; /* Jarak ke dalam kotak dilebarkan */
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .table-modern tbody td {
            display: flex;
            flex-direction: column; /* Label ada di atas data, BUKAN di samping */
            align-items: flex-start;
            padding: 12px 0 !important; /* Jarak atas-bawah per baris dilebarkan */
            border: none !important;
            border-bottom: 1px dashed #e2e8f0 !important;
            font-size: 14px; /* Huruf sedikit diperbesar */
        }
        .table-modern tbody td:first-child {
            padding-top: 0 !important;
        }
        .table-modern tbody td:last-child {
            border-bottom: none !important;
            padding-bottom: 0 !important;
        }

        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            margin-bottom: 6px; /* Memberi jarak ke datanya */
            display: block;
            width: 100%;
        }

        /* Memperbesar Tombol Aksi di Mobile */
        .action-btn-group {
            width: 100%;
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        .action-btn-group a {
            flex: 1; /* Lebar tombol menyesuaikan merata */
            padding: 12px !important; /* Area klik jadi besar */
            font-size: 16px; /* Ikon diperbesar sedikit */
        }

        h3 {
            color: #2563eb !important;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        const table=$('#dataTables-example').DataTable({
            pageLength: 25,
            autoWidth: false,
            responsive: false,
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "Semua"]
            ],
            language: {
                search: "Cari:",
                searchPlaceholder: "Cari data...",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            },
            columnDefs: [{targets: 0, orderable: false}],
            drawCallback: function(){
                // auto-render 25 yang tampil tanpa klik manual (100ms staggered)
                const nodes=this.api().rows({page:'current'}).nodes();
                $(nodes).find('.btn-cek-finger, .btn-cek-mesin').each(function(i){ const b=$(this); setTimeout(()=>b.trigger('click'), i*100); });
            }
        });

        // SweetAlert Delete Confirmation - Using delegation for better reliability with DataTables
        $(document).on('click', '.btn-delete-karyawan', function() {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            
            Swal.fire({
                title: 'Hapus karyawan?',
                html: 'Hapus <b>'+nama+'</b> dari <b>semua mesin + database</b>?<br><span class="text-xs text-gray-500">Mesin yang offline akan dilewati. Tidak bisa dibatalkan.</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;
                Swal.fire({title:'Menghapus...', allowOutsideClick:false, showConfirmButton:false, showCancelButton:false, didOpen:()=>Swal.showLoading()});
                $.post('page/karyawan/ajax_hapus.php',{action:'delete_direct',id:id},function(ex){
                    if(!ex.success){ Swal.fire({title:'Gagal',text:ex.msg,icon:'error'}); return; }
                    let h='<div class="text-left text-sm">'+ex.msg+'<div class="mt-2">';
                    for(const did in (ex.results||{})){ const x=ex.results[did]; if(!x.device) continue; h+='<div class="p-2 border rounded mb-1"><b>'+x.device+'</b>: '+x.message+'</div>'; }
                    h+='</div></div>';
                    Swal.fire({title:'Berhasil',html:h,icon:'success',confirmButtonColor:'#4f46e5'}).then(()=>location.reload());
                },'json');
            });
            return;
        });

        // Catatan: alur hapus lama (preview cek mesin per user) tidak dipakai lagi di tombol ini.
        // Kalau sesekali perlu hapus selektif per mesin / nama dobel, pakai Hapus Massal (masih pakai preview).
        $(document).on('click', '.btn-delete-karyawan-legacy', function() {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            Swal.fire({title:'Mengecek mesin...', allowOutsideClick:false, showConfirmButton:false, showCancelButton:false, didOpen:()=>Swal.showLoading()});
                    $.post('page/karyawan/ajax_hapus.php',{action:'preview',id:id},function(pv){
                        if(!pv.success){ Swal.fire({title:'Gagal',text:pv.msg,icon:'warning'}); return; }
                        let n=0, html='<div class="text-left text-sm">';
                        html+='<p class="mb-2">Hapus <b>'+pv.nama+'</b> (No. Absen '+pv.no_absen+'). Cocok di mesin: <b>'+pv.total+'</b> user.</p>';
                        for(const did in (pv.devices||{})){
                            const d=pv.devices[did];
                            html+='<div class="p-2 border rounded mb-1"><b>'+d.device+'</b> <span class="text-xs text-gray-500">'+d.ip+'</span><br>';
                            if(!d.online){ html+='<span class="text-xs text-gray-500">'+d.message+' — tidak bisa dicek/dihapus.</span>'; }
                            else if(!(d.matches||[]).length){ html+='<span class="text-xs text-gray-500">Tidak ada nama yang cocok.</span>'; }
                            else {
                                d.matches.forEach(m=>{
                                    n++;
                                    html+=`<label class="flex items-center gap-2 mt-1 text-sm cursor-pointer"><input type="checkbox" class="swDelTarget" value="${did}:${m.pin}:${m.pin2||''}" checked> <span><b>${m.name}</b> PIN ${m.pin}${m.pin2?' / PIN2 '+m.pin2:''}${m.priv&&m.priv!=='0'?' <span class="text-amber-600 font-bold">[ADMIN]</span>':''}</span></label>`;
                                });
                            }
                            html+='</div>';
                        }
                        html+='</div>';
                        Swal.fire({
                            title: pv.total > 1 ? 'Nama Dobel — Pilih yang Dihapus' : 'Konfirmasi Hapus',
                            html: html,
                            showDenyButton: true,
                            confirmButtonText: 'Hapus yang Dipilih',
                            denyButtonText: 'DB Saja',
                            cancelButtonText: 'Batal',
                            showCancelButton: true,
                            confirmButtonColor: '#e11d48',
                            preConfirm: () => {
                                const sel = [...document.querySelectorAll('.swDelTarget:checked')].map(c=>c.value);
                                return {sel: sel};
                            }
                        }).then((rr)=>{
                            if(rr.dismiss && rr.dismiss !== Swal.DismissReason.deny) return;
                            const isDbOnly = rr.isDenied;
                            const sel = rr.isDenied ? [] : ((rr.value && rr.value.sel) || []);
                            if(!isDbOnly && !sel.length){ Swal.fire({title:'Pilih Dulu',text:'Centang minimal 1 user mesin, atau pilih DB Saja.',icon:'warning'}); return; }
                            Swal.fire({title:'Menghapus...', allowOutsideClick:false, showConfirmButton:false, showCancelButton:false, didOpen:()=>Swal.showLoading()});
                            $.post('page/karyawan/ajax_hapus.php',{action:'exec',id:id,targets:sel,db_only:isDbOnly?1:0},function(ex){
                                if(!ex.success){ Swal.fire({title:'Gagal',text:ex.msg,icon:'error'}); return; }
                                let h='<div class="text-left text-sm">'+ex.msg;
                                if(!ex.db_only){ h+='<div class="mt-2">'; for(const did in (ex.results||{})){ const x=ex.results[did]; if(!x.device) continue; h+='<div class="p-2 border rounded mb-1"><b>'+x.device+'</b>: '+x.message+'</div>'; } h+='</div>'; }
                                else h+='<div class="text-xs text-gray-500 mt-1">Mesin tidak disentuh.</div>';
                                h+='</div>';
                                Swal.fire({title:'Berhasil',html:h,icon:'success',confirmButtonColor:'#4f46e5'}).then(()=>location.reload());
                            },'json');
                        });
                    },'json');
        });

        $(document).on('click', '.btn-regis-copy', function(){
            const id=$(this).data('id');
            const pin=$(this).data('pin');
            const nama=$(this).data('nama');
            const ENROLL_MACHINES = (()=>{ try { return JSON.parse(document.getElementById('enroll-machines-data').textContent); } catch(e){ return []; } })();
            Swal.fire({
                title: 'Enroll Sidik?',
                html: `PIN <b>${pin}</b> - ${nama}<br><small class="text-gray-500">Pilih aksi untuk sidik jari</small>`,
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: '#7c3aed',
                denyButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="bi bi-clipboard2-check"></i> Copy ke Mesin Lain',
                denyButtonText: '<i class="bi bi-fingerprint"></i> Enroll + Auto-sebar',
                cancelButtonText: 'Batal'
            }).then((r)=>{
                if(r.isConfirmed){
                    Swal.fire({title:'Memproses...', text:'Copy sidik ke 4 mesin...', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
                    $.post('page/karyawan/ajax_copy.php', {id:id}, function(res){
                        if(res.success){
                            let html='<div class="text-left text-sm">';
                            for(const did in res.results){
                                const rr=res.results[did];
                                html+=`<div class="p-2 border rounded mb-1 ${rr.status=='success'?'border-green-200 bg-green-50':'border-red-200 bg-red-50'}"><b>${rr.device}</b>: ${rr.message}</div>`;
                            }
                            html+='</div>';
                            Swal.fire({title:'Hasil Copy', html:html, icon:'success', confirmButtonColor:'#7c3aed'});
                        } else {
                            Swal.fire({title:'Gagal', text:res.msg, icon:'warning', confirmButtonColor:'#7c3aed'});
                        }
                    },'json');
                } else if(r.isDenied){
                    // Enroll + auto-sebar langsung di popup: pilih master -> mulai -> pantau -> sebar otomatis
                    let opts = '<option value="auto" style="font-weight:bold;">🔍 AUTO-DETECT (scan semua mesin)</option>';
                    opts += ENROLL_MACHINES.map(d=>`<option value="${d.id}">${d.nama} (${d.ip})</option>`).join('');
                    if(ENROLL_MACHINES.length === 0){ Swal.fire({title:'Gagal', text:'Tidak ada mesin ber-IP di Setting Device.', icon:'warning'}); return; }
                    Swal.fire({
                        title: 'Enroll + Auto-sebar',
                        html: `<div class="text-left text-sm">PIN <b>${pin}</b> - ${nama}<br>
                            <label class="block mt-2 mb-1 font-semibold">Mesin master (tempat tempel jari)</label>
                            <select id="swMaster" class="w-full border border-gray-300 rounded px-2 py-1.5">${opts}</select>
                            <div class="text-xs text-gray-500 mt-1">Pilih mesin spesifik atau <b>AUTO-DETECT</b>. Sesudah mulai, tempel jari 3x di mesin — sidik yang jadi otomatis disebar.</div></div>`,
                        showCancelButton: true,
                        confirmButtonText: 'Mulai Enroll',
                        cancelButtonText: 'Tutup',
                        confirmButtonColor: '#0d9488',
                        preConfirm: () => {
                            const mid = document.getElementById('swMaster').value;
                            if(!mid){ Swal.showValidationMessage('Pilih mesin master dulu'); return false; }
                            return {mid: mid};
                        }
                    }).then((rr)=>{
                        if(!rr.isConfirmed) return;
                        const mid = rr.value.mid;
                        let enrollCancelled = false;
                        Swal.fire({
                            title:'Menyiapkan mesin...',
                            html:'<div class="my-2"><div class="spinner-border text-info" role="status" style="width:2.5rem;height:2.5rem;"></div></div><div><button id="swPrepCancel" type="button" class="btn btn-secondary mt-1">Batal</button></div>',
                            allowOutsideClick:false, showConfirmButton:false, showCancelButton:false,
                            didOpen:()=>{ document.getElementById('swPrepCancel').addEventListener('click',()=>{ enrollCancelled = true; Swal.close(); }); }
                        }).then(()=>{ enrollCancelled = true; });
                        $.post('page/karyawan/ajax_enroll_auto.php',{action:'start',karyawan_id:id,master_id:mid},function(res){
                            if(enrollCancelled) return;
                            if(!res.success){ Swal.fire({title:'Gagal',text:res.msg,icon:'warning',confirmButtonColor:'#7c3aed'}); return; }
                            const isAutoMode = mid === 'auto';
                            const base = res.baseline||[];
                            const baselines = isAutoMode ? (res.baselines || {}) : null;
                            let n = 0;
                            Swal.fire({
                                title:'Tempel Jari Sekarang',
                                html:`<div class="text-sm text-left">PIN <b>${pin}</b> di <b>${res.master}</b><br><span class="text-gray-500">${res.msg}</span><div id="swPoll" class="mt-2 text-xs text-gray-500">memantau…</div><div id="swRes"></div></div>`,
                                allowOutsideClick:false, showConfirmButton:false, showCancelButton:true, cancelButtonText:'Stop'
                            }).then(()=>{ if(window._enrollTimer){ clearInterval(window._enrollTimer); window._enrollTimer=null; } });
                            window._enrollTimer=setInterval(()=>{
                                n++;
                                const el=document.getElementById('swPoll');
                                if(el) el.textContent='memantau… (cek ke-'+n+' / 36)';
                                if(n>36){ clearInterval(window._enrollTimer); window._enrollTimer=null; Swal.update({html:'<p class="text-sm text-amber-600">Waktu habis (3 menit). Pakai Copy ke Mesin Lain.</p>'}); return; }
                                const pollData = isAutoMode 
                                    ? {action:'poll', pin:res.pin, master_id:'auto', baselines_json:JSON.stringify(baselines)}
                                    : {action:'poll', pin:res.pin, master_id:mid, baseline:base};
                                $.post('page/karyawan/ajax_enroll_auto.php',pollData,function(pr){
                                    if(!pr.success){ clearInterval(window._enrollTimer); window._enrollTimer=null; Swal.fire({title:'Gagal',text:pr.msg,icon:'warning'}); return; }
                                    if(pr.done){
                                        clearInterval(window._enrollTimer); window._enrollTimer=null;
                                        const box=document.getElementById('swRes');
                                        if(box) box.innerHTML = isAutoMode 
                                            ? '<p class="text-sm text-green-700 font-semibold">✅ Sidik terdeteksi di <b>'+pr.detected_device_name+' ('+pr.detected_device_ip+')</b> (jari: '+pr.new.join(', ')+') — menyebar...</p>'
                                            : '<p class="text-sm text-green-700 font-semibold">Sidik terdeteksi (jari: '+pr.new.join(', ')+') — menyebar...</p>';
                                        const spreadMid = isAutoMode ? pr.detected_device_id : mid;
                                        $.post('page/karyawan/ajax_enroll_auto.php',{action:'spread',pin:res.pin,master_id:spreadMid},function(sr){
                                            let html='<div class="text-left text-sm mt-2">';
                                            if(sr.success){ for(const did in (sr.results||{})){ const x=sr.results[did]; html+='<div class="p-2 border rounded mb-1">'+x.device+': '+x.message+'</div>'; } }
                                            else html+='<p class="text-red-600">'+sr.msg+'</p>';
                                            html+='</div>';
                                            Swal.fire({title:'Selesai Sebar',html:html,icon:'success',confirmButtonColor:'#0d9488'});
                                        },'json');
                                    }
                                },'json');
                            },5000);
                        },'json');
                    });
                }
            });
        });

        function updateBulkCount(){
            const c=$('.bulk-check:checked').length;
            $('#bulkCount').text(c+' terpilih');
            if(c>0) $('#btnBulkCopy').removeClass('hidden');
            else $('#btnBulkCopy').addClass('hidden');
        }
        $(document).on('change', '#bulk_select_all', function(){ $('.bulk-check').prop('checked', this.checked); updateBulkCount(); });
        $(document).on('change', '.bulk-check', function(){ updateBulkCount(); });
        $(document).on('click','.btn-cek-finger',function(){
            const id=$(this).data('id'); const cell=$(this).closest('td'); const target=cell.find('span').first(); target.text('...');
            $.post('page/karyawan/ajax_cek.php',{id:id,type:'finger'},function(res){
                if(res.success){
                    const t=res.total||4, h=res.has||0;
                    const cls=h>=t?'bg-green-100 text-green-700':(h>0?'bg-amber-100 text-amber-700':'bg-red-100 text-red-700');
                    const esc=s=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                    let tip='Ada sidik di: '+(((res.found_names||[]).join(', '))||'-');
                    if((res.offline_names||[]).length) tip+=' | Belum dicek: '+(res.offline_names||[]).join(', ');
                    target.replaceWith(`<span title="${esc(tip)}" class="px-2 py-1 text-xs font-bold rounded-full ${cls}">${h}/${t}</span>`);
                }
                else target.text('Gagal');
            },'json');
        });
        $(document).on('click','.btn-cek-mesin',function(){
            const id=$(this).data('id'); const cell=$(this).closest('td'); const target=cell.find('span').first(); target.text('...');
            $.post('page/karyawan/ajax_cek.php',{id:id,type:'mesin'},function(res){
                if(res.success){
                    const t=res.total||4, f=res.found||0;
                    const cls=f>=t?'bg-green-100 text-green-700':(f>0?'bg-amber-100 text-amber-700':'bg-red-100 text-red-700');
                    const esc=s=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                    let tip='Terdaftar di: '+(((res.found_names||[]).join(', '))||'-');
                    if((res.offline_names||[]).length) tip+=' | Belum dicek: '+(res.offline_names||[]).join(', ');
                    target.replaceWith(`<span title="${esc(tip)}" class="px-2 py-1 text-xs font-bold rounded-full ${cls}">${f}/${t}</span>`);
                }
                else target.text('Gagal');
            },'json');
        });
        $('#btnBulkDelete').on('click', function(){
            const c=document.querySelectorAll('.bulk-check:checked').length;
            if(c===0){ Swal.fire({title:'Pilih Data!',text:'Centang karyawan dulu sebelum hapus.',icon:'warning',confirmButtonColor:'#3b82f6'}); return; }
            const ids=$('.bulk-check:checked').map(function(){return this.value;}).get();
            Swal.fire({title:'Mengecek mesin...',text:`Mencari user ${c} karyawan di mesin...`,allowOutsideClick:false,showConfirmButton:false,showCancelButton:false,didOpen:()=>Swal.showLoading()});
            $.post('page/karyawan/ajax_hapus.php',{action:'preview_bulk',ids:ids},function(pv){
                if(!pv.success){ Swal.fire({title:'Gagal',text:pv.msg,icon:'warning'}); return; }
                let html='<div class="text-left text-sm max-h-72 overflow-y-auto">';
                (pv.employees||[]).forEach(emp=>{
                    html+=`<div class="p-2 border rounded mb-2"><b>${emp.nama}</b> (No. Absen ${emp.no_absen}) — cocok: <b>${emp.total}</b><br>`;
                    for(const did in (emp.devices||{})){
                        const d=emp.devices[did];
                        if(!d.online || !(d.matches||[]).length) continue;
                        d.matches.forEach(m=>{
                            html+=`<label class="flex items-center gap-2 mt-1 text-sm cursor-pointer"><input type="checkbox" class="swBulkTarget" data-kid="${emp.id}" value="${did}:${m.pin}:${m.pin2||''}" checked> <span>${d.device}: <b>${m.name}</b> PIN ${m.pin}${m.pin2?' / PIN2 '+m.pin2:''}</span></label>`;
                        });
                    }
                    html+='</div>';
                });
                html+='</div>';
                Swal.fire({
                    title:'Pilih User Mesin yang Dihapus',
                    html:html, showDenyButton:true, showCancelButton:true,
                    confirmButtonText:'Hapus yang Dipilih', denyButtonText:'DB Saja', cancelButtonText:'Batal',
                    confirmButtonColor:'#e11d48', width:'42em',
                }).then((rr)=>{
                    if(rr.dismiss && rr.dismiss!==Swal.DismissReason.deny) return;
                    const isDbOnly = rr.isDenied;
                    const items={};
                    if(!isDbOnly){
                        document.querySelectorAll('.swBulkTarget:checked').forEach(cb=>{
                            const kid=cb.getAttribute('data-kid');
                            (items[kid]=items[kid]||[]).push(cb.value);
                        });
                        if(!Object.keys(items).length){ Swal.fire({title:'Pilih Dulu',text:'Centang minimal 1 user mesin, atau pilih DB Saja.',icon:'warning'}); return; }
                    } else {
                        (pv.employees||[]).forEach(emp=>{ items[emp.id]=[]; });
                    }
                    Swal.fire({title:'Menghapus...',allowOutsideClick:false,showConfirmButton:false,showCancelButton:false,didOpen:()=>Swal.showLoading()});
                    $.post('page/karyawan/ajax_hapus.php',{action:'exec_bulk',items:items,db_only:isDbOnly?1:0},function(ex){
                        let h='<div class="text-left text-sm max-h-72 overflow-y-auto">';
                        (ex.per||[]).forEach(p=>{
                            let ok=0, fail=0;
                            for(const did in (p.results||{})){ const x=p.results[did]; if(!x.device) continue; if(x.status==='success') ok++; else if(x.status==='error') fail++; }
                            h+=`<div class="p-2 border rounded mb-1"><b>${p.nama}</b> — DB: ${p.db_deleted?'terhapus':'GAGAL'} | mesin: ${ok} sukses${fail?`, <span class="text-red-600">${fail} gagal</span>`:''}</div>`;
                        });
                        h+='</div>';
                        Swal.fire({title:'Selesai Hapus Massal',html:h,icon:'success',confirmButtonColor:'#e11d48'}).then(()=>location.reload());
                    },'json');
                });
            },'json');
        });
        $('#btnBulkCopy').on('click', function(){
            const ids=$('.bulk-check:checked').map(function(){return this.value;}).get();
            if(ids.length===0){ Swal.fire({title:'Pilih Data!',text:'Centang karyawan dulu sebelum copy sidik.',icon:'warning',confirmButtonColor:'#3b82f6'}); return; }
            Swal.fire({title:'Copy Sidik Massal?',text:`Copy sidik ${ids.length} karyawan terpilih ke 4 mesin?`,icon:'question',showCancelButton:true,confirmButtonColor:'#16a34a',cancelButtonColor:'#6b7280',confirmButtonText:'Ya, Copy!',cancelButtonText:'Batal'}).then((r)=>{
                if(r.isConfirmed){
                    const form=$('#bulkForm');
                    form.find('input[name="bulk_delete_selected"]').remove();
                    form.find('input[name="bulk_copy_selected"]').remove();
                    form.append('<input type="hidden" name="bulk_copy_selected" value="1">');
                    form.off('submit').submit();
                }
            });
        });
        
        $('.dataTables_filter').addClass('mb-3');
        $('.dataTables_length').addClass('mb-3');
    });
</script>