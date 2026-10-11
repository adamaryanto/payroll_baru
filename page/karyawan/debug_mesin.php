<?php
// Debug Mesin: tes apakah web bisa "ngobrol" 2 arah dengan mesin.
// Tes per mesin: TCP connect -> SetUserInfo (PIN 9999/TES) -> GetAllUserInfo (verifikasi) -> DeleteUser (bersih-bersih).
// PIN 9999 hanya user test, otomatis dihapus lagi setelah tes.
include_once __DIR__ . '/../../device_sync_helper.php';
include_once __DIR__ . '/../../zk_protocol_helper.php';

$TEST_PIN = '9999';
$TEST_NAME = 'TESDEBUG';

$devices = get_all_devices($koneksi);
$sel_id = $_GET['id_mesin'] ?? '';
$run = isset($_GET['test']);
$runScan = isset($_GET['scan']);
$runZk = isset($_GET['zktest']);
$runRefresh = isset($_GET['refresh']);
$refresh_results = [];
$runVar = isset($_GET['variant']);
$runKeys = isset($_GET['trykeys']);
$runEnroll = isset($_GET['testenroll']);
$results = [];
$scan_results = [];
$zk_results = [];
$var_results = [];
$enroll_results = [];

// MODE TEBAK KUNCI (coba kunci umum pabrik via UDP, tanpa tulis data).
$key_results = [];
if($runKeys){
    set_time_limit(0);
    foreach($devices as $device){
        if($sel_id !== '' && (string)$device['id_mesin'] !== (string)$sel_id) continue;
        if(empty($device['ip_mesin'])) continue;
        $ip = $device['ip_mesin'];
        $port = isset($_GET['port_'.$device['id_mesin']]) && $_GET['port_'.$device['id_mesin']] !== '' ? intval($_GET['port_'.$device['id_mesin']]) : intval($device['port'] ?? 4370);
        $kr = zkb_try_common_keys($ip, $port);
        $kr['device'] = $device['nama_mesin'];
        $kr['ip'] = $ip;
        $key_results[] = $kr;
    }
}

// MODE TES ENROLL (simulasi enroll + auto-sebar): pilih 1 mesin master, coba StartEnroll, cek template muncul, sebar ke mesin lain.
if($runEnroll){
    set_time_limit(0);
    $test_pin = $_GET['test_pin'] ?? '8888';
    $master_id = $_GET['enroll_master'] ?? '';
    if($master_id === ''){ $enroll_results[] = ['device'=>'?','status'=>'error','msg'=>'Pilih mesin master dulu']; }
    else if($master_id === 'auto'){
        // AUTO-DETECT MODE: scan all machines to detect where fingerprint is enrolled
        $er = ['device'=>'AUTO-DETECT','steps'=>[]];
        
        // 1. Get baseline from all online machines
        $baselines = [];
        $online_devices = [];
        foreach($devices as $d){
            if(empty($d['ip_mesin'])) continue;
            if(!test_device_connection($d['ip_mesin'], $d['port'])) continue;
            $templates = get_fingerprint_templates($d['ip_mesin'], $d['port'], $d['comm_key']??0, $test_pin);
            $baselines[$d['id_mesin']] = array_map(fn($t)=>(int)$t['finger_id'], $templates);
            $online_devices[$d['id_mesin']] = $d;
        }
        $er['steps']['scan'] = ['ok'=>true,'msg'=>'Scanning '.count($online_devices).' mesin online. Baseline diambil dari semua mesin.'];
        
        // 2. Wait instruction
        $er['steps']['wait'] = ['ok'=>true,'msg'=>'TEMPEL JARI di MESIN MANA SAJA (yang online). Sistem memantau SEMUA mesin selama 60 detik...'];
        
        // 3. Polling loop: check ALL machines every 5 seconds
        $maxPolls = 12; // 12x @ 5s = 60s
        $detected_machine = null;
        $new = [];
        $cur = [];
        
        for($i = 0; $i < $maxPolls; $i++){
            sleep(5);
            foreach($online_devices as $did => $d){
                $cur = get_fingerprint_templates($d['ip_mesin'], $d['port'], $d['comm_key']??0, $test_pin);
                $curIds = array_map(fn($t)=>(int)$t['finger_id'], $cur);
                $new = array_values(array_diff($curIds, $baselines[$did]));
                if(!empty($new)){
                    $detected_machine = $d;
                    break 2; // Break both loops - found new fingerprint!
                }
            }
        }
        
        $pollTime = ($i + 1) * 5;
        
        // 4. Show detection result
        if($detected_machine){
            $er['steps']['detect'] = ['ok'=>true,'msg'=>"✅ Jari BARU terdeteksi di {$detected_machine['nama_mesin']} ({$detected_machine['ip_mesin']}) setelah {$pollTime} detik. Jari baru: ".implode(',',$new).' (total '.count($cur).' jari)'];
            
            // 5. Auto-spread from detected machine to all others
            $spread = [];
            $nm = 'TES ENROLL '.$test_pin;
            $safeNm = htmlspecialchars($nm, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            
            foreach($devices as $dev){
                if((string)$dev['id_mesin'] === (string)$detected_machine['id_mesin']) continue; // Skip source machine
                if(empty($dev['ip_mesin']) || !test_device_connection($dev['ip_mesin'], $dev['port'])){ 
                    $spread[]=['device'=>$dev['nama_mesin'],'status'=>'skipped','msg'=>'Offline']; 
                    continue; 
                }
                if(($dev['protocol'] ?? 'soap') === 'zkudp'){ 
                    $spread[]=['device'=>$dev['nama_mesin'],'status'=>'skipped','msg'=>'Biner tidak support template']; 
                    continue; 
                }
                
                $tkey = $dev['comm_key'] ?? 0;
                send_soap_request($dev['ip_mesin'], $dev['port'], "<DisableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></DisableDevice>");
                send_soap_request($dev['ip_mesin'], $dev['port'], "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$test_pin}</PIN><Name xsi:type=\"xsd:string\">{$safeNm}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>");
                
                $ok = 0;
                foreach($cur as $t){ 
                    if(set_fingerprint_template($dev['ip_mesin'], $dev['port'], $tkey, $test_pin, (int)$t['finger_id'], $t['template'], (int)($t['size']??0), (int)($t['valid']??1))) $ok++; 
                }
                
                send_soap_request($dev['ip_mesin'], $dev['port'], "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></RefreshData>");
                send_soap_request($dev['ip_mesin'], $dev['port'], "<EnableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></EnableDevice>");
                
                $cek = get_fingerprint_templates($dev['ip_mesin'], $dev['port'], $tkey, $test_pin);
                $st = ($ok === count($cur) && count($cek) >= count($cur)) ? 'success' : ($ok > 0 ? 'warning' : 'error');
                $spread[]=['device'=>$dev['nama_mesin'],'status'=>$st,'msg'=>"Terkirim $ok/".count($cur)." jari, terverifikasi ".count($cek)." jari"];
            }
            
            $er['steps']['spread'] = ['ok'=>true,'msg'=>'Sebar dari '.$detected_machine['nama_mesin'].' ke '.count($spread).' mesin lain','detail'=>$spread];
            $er['status'] = 'success';
        } else {
            $er['steps']['detect'] = ['ok'=>false,'msg'=>"❌ Timeout setelah {$pollTime} detik — tidak ada jari baru terdeteksi di ".count($online_devices)." mesin online. Pastikan tempel jari tepat setelah klik Tes Enroll."];
            $er['status'] = 'waiting';
        }
        
        $enroll_results[] = $er;
    }
    else {
        $master = null;
        foreach($devices as $d) if((string)$d['id_mesin'] === (string)$master_id){ $master = $d; break; }
        if(!$master || empty($master['ip_mesin'])){ $enroll_results[] = ['device'=>'Master','status'=>'error','msg'=>'Mesin master tidak ketemu / IP kosong']; }
        else {
            $ip = $master['ip_mesin'];
            $port = $master['port'] ?? 4370;
            $comm = intval($master['comm_key'] ?? 0);
            $er = ['device'=>$master['nama_mesin'],'ip'=>$ip,'steps'=>[]];
            // 1. Cek koneksi
            if(!test_device_connection($ip, $port)){ $er['steps']['connect'] = ['ok'=>false,'msg'=>'Mesin offline']; $er['status']='error'; $enroll_results[] = $er; }
            else {
                $er['steps']['connect'] = ['ok'=>true,'msg'=>'Online'];
                // 2. Ambil baseline template (sebelum enroll)
                $base = get_fingerprint_templates($ip, $port, $comm, $test_pin);
                $baseIds = array_map(fn($t)=>(int)$t['finger_id'], $base);
                $er['steps']['baseline'] = ['ok'=>true,'msg'=>'Baseline: '.count($baseIds).' jari sudah ada ('.implode(',',$baseIds).')'];
                // 3. Kirim StartEnroll (mode enroll)
                $enrollCmd = "<StartEnroll><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$test_pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID></Arg></StartEnroll>";
                $respEnroll = send_soap_request($ip, $port, $enrollCmd);
                $enrollOk = $respEnroll && (stripos($respEnroll,'Success')!==false || stripos($respEnroll,'Successfully')!==false);
                $er['steps']['enroll_mode'] = ['ok'=>$enrollOk,'msg'=>$enrollOk ? 'Mesin masuk mode enroll (TEMPEL JARI SEKARANG di mesin!)' : 'Mesin tidak balas Success — mungkin sudah mode enroll manual atau tidak support StartEnroll'];
                // 4. Loop polling: tunggu sampai ada jari baru (max 60 detik)
                $er['steps']['wait'] = ['ok'=>true,'msg'=>'Setelah klik Tes Enroll, LANGSUNG tempel jari 3x di mesin. Sistem memantau selama 60 detik...'];
                $maxPolls = 12; // 12x polling @ 5 detik = 60 detik
                $new = [];
                $cur = [];
                $curIds = [];
                for($i = 0; $i < $maxPolls; $i++){
                    sleep(5); // Tunggu 5 detik
                    $cur = get_fingerprint_templates($ip, $port, $comm, $test_pin);
                    $curIds = array_map(fn($t)=>(int)$t['finger_id'], $cur);
                    $new = array_values(array_diff($curIds, $baseIds));
                    if(!empty($new)) break; // Ada jari baru, keluar dari loop
                }
                $pollTime = ($i + 1) * 5;
                $er['steps']['poll'] = ['ok'=>!empty($new),'msg'=>empty($new) ? "Timeout setelah {$pollTime} detik — belum ada jari baru. Pastikan tempel jari tepat setelah klik Tes Enroll." : "Jari BARU terdeteksi setelah {$pollTime} detik: ".implode(',',$new).' (total sekarang '.count($curIds).' jari)'];
                // 5. Auto-sebar (kalau ada jari baru)
                if(!empty($new)){
                    $spread = [];
                    $nm = 'TES ENROLL '.$test_pin;
                    $safeNm = htmlspecialchars($nm, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                    foreach($devices as $dev){
                        if((string)$dev['id_mesin'] === (string)$master_id) continue;
                        if(empty($dev['ip_mesin']) || !test_device_connection($dev['ip_mesin'], $dev['port'])){ $spread[]=['device'=>$dev['nama_mesin'],'status'=>'skipped','msg'=>'Offline']; continue; }
                        if(($dev['protocol'] ?? 'soap') === 'zkudp'){ $spread[]=['device'=>$dev['nama_mesin'],'status'=>'skipped','msg'=>'Biner tidak support template']; continue; }
                        $tkey = $dev['comm_key'] ?? 0;
                        send_soap_request($dev['ip_mesin'], $dev['port'], "<DisableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></DisableDevice>");
                        send_soap_request($dev['ip_mesin'], $dev['port'], "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$test_pin}</PIN><Name xsi:type=\"xsd:string\">{$safeNm}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>");
                        $ok = 0;
                        foreach($cur as $t){ if(set_fingerprint_template($dev['ip_mesin'], $dev['port'], $tkey, $test_pin, (int)$t['finger_id'], $t['template'], (int)($t['size']??0), (int)($t['valid']??1))) $ok++; }
                        send_soap_request($dev['ip_mesin'], $dev['port'], "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></RefreshData>");
                        send_soap_request($dev['ip_mesin'], $dev['port'], "<EnableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></EnableDevice>");
                        $cek = get_fingerprint_templates($dev['ip_mesin'], $dev['port'], $tkey, $test_pin);
                        $st = ($ok === count($cur) && count($cek) >= count($cur)) ? 'success' : ($ok > 0 ? 'warning' : 'error');
                        $spread[]=['device'=>$dev['nama_mesin'],'status'=>$st,'msg'=>"Terkirim $ok/".count($cur)." jari, terverifikasi ".count($cek)." jari"];
                    }
                    $er['steps']['spread'] = ['ok'=>true,'msg'=>'Sebar ke '.count($spread).' mesin lain','detail'=>$spread];
                } else {
                    $er['steps']['spread'] = ['ok'=>false,'msg'=>'Skip sebar (belum ada jari baru)'];
                }
                $er['status'] = !empty($new) ? 'success' : 'waiting';
                $enroll_results[] = $er;
            }
        }
    }
}

// MODE VARIAN (read-only + 1 tulis biner bila handshake OK): UDP, HTTP/1.1, path alternatif.
if($runVar){
    set_time_limit(0);
    foreach($devices as $device){
        if($sel_id !== '' && (string)$device['id_mesin'] !== (string)$sel_id) continue;
        if(empty($device['ip_mesin'])) continue;
        $ip = $device['ip_mesin'];
        $port = isset($_GET['port_'.$device['id_mesin']]) && $_GET['port_'.$device['id_mesin']] !== '' ? intval($_GET['port_'.$device['id_mesin']]) : intval($device['port'] ?? 4370);
        $vr = ['device'=>$device['nama_mesin'],'ip'=>$ip,'port'=>$port,'items'=>[]];
        $u = zkb_udp_probe($ip, $port);
        $vr['items']['UDP 4370 (connect biner)'] = $u;
        foreach(zkb_http_probe($ip, $port) as $label => $h) $vr['items'][$label] = $h;
        $var_results[] = $vr;
    }
}

// MODE REFRESH (read + commit ringan): kirim RefreshData agar user hasil
// sync/delete dimuat ulang dari flash dan muncul di layar mesin. Tidak mengubah data.
if($runRefresh){
    set_time_limit(0);
    if(function_exists('create_sync_log_table')) create_sync_log_table($koneksi);
    foreach($devices as $device){
        if($sel_id !== '' && (string)$device['id_mesin'] !== (string)$sel_id) continue;
        if(empty($device['ip_mesin'])){ $refresh_results[]=['device'=>$device['nama_mesin'],'ip'=>'-','ok'=>false,'msg'=>'IP kosong']; continue; }
        $ip = $device['ip_mesin'];
        $port = isset($_GET['port_'.$device['id_mesin']]) && $_GET['port_'.$device['id_mesin']] !== '' ? intval($_GET['port_'.$device['id_mesin']]) : intval($device['port'] ?? 4370);
        $comm = intval($device['comm_key'] ?? 0);
        $rr = function_exists('refresh_device') ? refresh_device($ip, $port, $comm) : ['success'=>false,'message'=>'Helper refresh tidak ada (update device_sync_helper.php)'];
        $refresh_results[] = ['device'=>$device['nama_mesin'],'ip'=>$ip,'ok'=>!empty($rr['success']),'msg'=>$rr['message']];
        if(function_exists('log_sync_activity')) log_sync_activity($koneksi,'mesin_refresh',$device['id_mesin'],null,$rr['success']?'success':'error',$rr['message']);
    }
}

// MODE BINER (untuk x902 dkk yang RST saat SOAP): handshake + tulis/hapus user test 9999 via protokol biner.
if($runZk){
    set_time_limit(0);
    foreach($devices as $device){
        if($sel_id !== '' && (string)$device['id_mesin'] !== (string)$sel_id) continue;
        if(empty($device['ip_mesin'])) continue;
        $ip = $device['ip_mesin'];
        $port = isset($_GET['port_'.$device['id_mesin']]) && $_GET['port_'.$device['id_mesin']] !== '' ? intval($_GET['port_'.$device['id_mesin']]) : intval($device['port'] ?? 4370);
        $comm = intval($device['comm_key'] ?? 0);
        $t = zkb_test_dialog($ip, $port, $comm);
        $t['device'] = $device['nama_mesin'];
        $t['ip'] = $ip;
        $t['port'] = $port;
        $zk_results[] = $t;
    }
}

// MODE SCAN (read-only): cari pintu/bahasa yang dijawab mesin.
// Per port kandidat: TCP connect -> HTTP GET / -> POST SOAP GetAllUserInfo. Tidak menulis apa pun.
if($runScan){
    set_time_limit(0);
    $candidates = [4370, 80, 8080, 8000, 443];
    foreach($devices as $device){
        if($sel_id !== '' && (string)$device['id_mesin'] !== (string)$sel_id) continue;
        if(empty($device['ip_mesin'])) continue;
        $ip = $device['ip_mesin'];
        $prows = [];
        foreach($candidates as $port){
            $t0 = microtime(true);
            $s = @fsockopen($ip, $port, $errno, $errstr, 1.5);
            $ms = round((microtime(true)-$t0)*1000);
            if(!$s){ $prows[] = ['port'=>$port,'tcp'=>false,'ms'=>$ms,'get'=>'-','soap'=>'-']; continue; }
            @stream_set_timeout($s, 3);
            @fputs($s, "GET / HTTP/1.0\r\nHost: {$ip}\r\n\r\n");
            $buf = '';
            while(!@feof($s)){ $ln=@fgets($s,1024); if($ln===false) break; $buf.=$ln; if(strlen($buf)>1500) break; $meta=stream_get_meta_data($s); if($meta['timed_out']) break; }
            @fclose($s);
            $get = trim($buf)==='' ? '(koneksi ditutup tanpa balasan)' : substr($buf,0,300);
            $soapGet = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">0</ArgComKey></GetAllUserInfo>";
            $resp = send_soap_request($ip, $port, $soapGet);
            $soap = $resp ? substr($resp,0,300) : '(RST/timeout — bukan SOAP di port ini)';
            $prows[] = ['port'=>$port,'tcp'=>true,'ms'=>$ms,'get'=>$get,'soap'=>$soap];
        }
        $scan_results[] = ['device'=>$device['nama_mesin'],'ip'=>$ip,'ports'=>$prows];
    }
}

if($run){
    set_time_limit(0);
    foreach($devices as $device){
        if($sel_id !== '' && (string)$device['id_mesin'] !== (string)$sel_id) continue;
        if(empty($device['ip_mesin'])) continue;
        $ip = $device['ip_mesin'];
        $port = isset($_GET['port_'.$device['id_mesin']]) && $_GET['port_'.$device['id_mesin']] !== '' ? intval($_GET['port_'.$device['id_mesin']]) : intval($device['port'] ?? 4370);
        $comm = intval($device['comm_key'] ?? 0);
        $r = ['device'=>$device['nama_mesin'],'ip'=>$ip,'port'=>$port,'steps'=>[]];

        // 1. TCP connect
        $t0 = microtime(true);
        $online = test_device_connection($ip, $port);
        $r['steps']['tcp'] = ['ok'=>$online, 'msg'=>$online ? ('Port kebuka ('.round((microtime(true)-$t0)*1000).' ms)') : 'Port tidak bisa dibuka (mesin mati / IP salah / firewall)'];

        // 2. Tulis user test
        $soapSet = "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$TEST_PIN}</PIN><Name xsi:type=\"xsd:string\">{$TEST_NAME}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>";
        $respSet = $online ? send_soap_request($ip, $port, $soapSet) : false;
        $okSet = $respSet && (stripos($respSet,'Success')!==false || stripos($respSet,'Successfully')!==false || strpos($respSet,'<SetUserInfoResponse>')!==false);
        $r['steps']['tulis'] = ['ok'=>(bool)$okSet, 'msg'=>$respSet ? substr($respSet,0,400) : 'TIDAK ADA BALASAN (koneksi di-reset/timeout = beda protokol/port)'];

        // 3. Baca balik user test
        $soapGet = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
        $respGet = ($online && $okSet) ? send_soap_request($ip, $port, $soapGet) : false;
        $found = $respGet && (strpos($respGet,"<PIN>{$TEST_PIN}</PIN>")!==false || strpos($respGet,">{$TEST_PIN}<")!==false);
        $foundName = '';
        if($found && preg_match('/<PIN>'.$TEST_PIN.'<\/PIN>\s*<Name>([^<]*)<\/Name>/', $respGet, $mN)) $foundName = trim($mN[1]);
        $r['steps']['baca'] = ['ok'=>(bool)$found, 'msg'=>$found ? ("PIN {$TEST_PIN} ketemu di mesin, nama='{$foundName}'") : 'PIN test tidak ketemu di mesin'];

        // 4. Hapus user test (bersih-bersih)
        $soapDel = "<DeleteUser><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$TEST_PIN}</PIN></Arg></DeleteUser>";
        $respDel = ($online && $found) ? send_soap_request($ip, $port, $soapDel) : false;
        $okDel = $respDel && (stripos($respDel,'Success')!==false || stripos($respDel,'Successfully')!==false);
        $r['steps']['hapus'] = ['ok'=>(bool)$okDel, 'msg'=>$respDel ? substr($respDel,0,200) : ($found ? 'Gagal / tidak dibalas' : 'Dilewati (user test tidak ada)')];

        // Vonis
        if(!$online) $r['verdict'] = ['label'=>'TIDAK BISA NGOBROL','color'=>'red','why'=>'Port tidak kebuka. Cek IP, kabel LAN, mesin nyala, firewall.'];
        elseif(!$okSet) $r['verdict'] = ['label'=>'TIDAK BISA NGOBROL','color'=>'red','why'=>'Port kebuka tapi mesin menutup koneksi saat dikirimi SOAP = beda protokol/port. Coba port lain (80/4370) atau cek manual mesin.'];
        elseif(!$found) $r['verdict'] = ['label'=>'SEBAGIAN (tulis ok, baca gagal)','color'=>'amber','why'=>'Mesin menerima tulis tapi user test tidak terbaca balik. Kemungkinan format GetAllUserInfo beda tipe.'];
        else $r['verdict'] = ['label'=>'BISA NGOBROL PENUH','color'=>'green','why'=>'Tulis, baca, dan hapus user test semuanya berhasil. Mesin kompatibel.'];

        $results[] = $r;
    }
}
?>
<div class="container-fluid px-4 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 bg-white flex flex-col md:flex-row justify-between md:items-center gap-2">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fas fa-stethoscope mr-2"></i>Debug Mesin (Tes Ngobrol)</h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Tes 2 arah per mesin: connect → tulis user test (PIN 9999) → baca balik → hapus lagi. User test otomatis dibersihkan.</p>
            </div>
            <a href="?page=karyawan" class="inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded text-sm"><i class="fas fa-arrow-left mr-2"></i>Kembali</a>
        </div>
        <div class="p-4 md:p-5">
            <form method="GET" class="flex flex-col md:flex-row gap-2 items-stretch md:items-end mb-4">
                <input type="hidden" name="page" value="karyawan">
                <input type="hidden" name="aksi" value="debug_mesin">
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase">Mesin</label><br>
                    <select name="id_mesin" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Semua mesin</option>
                        <?php foreach($devices as $d): ?>
                        <option value="<?= $d['id_mesin'] ?>" <?= ((string)$sel_id===(string)$d['id_mesin'])?'selected':'' ?>><?= htmlspecialchars($d['nama_mesin']) ?> (<?= htmlspecialchars($d['ip_mesin'] ?: '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach($devices as $d): ?>
                <div>
                    <label class="text-xs font-bold text-gray-500 uppercase">Port <?= htmlspecialchars($d['nama_mesin']) ?></label><br>
                    <input type="number" name="port_<?= $d['id_mesin'] ?>" value="<?= intval($d['port'] ?? 4370) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-28">
                </div>
                <?php endforeach; ?>
                <button type="submit" name="test" value="1" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-5 rounded-lg text-sm"><i class="fas fa-play mr-2"></i>Tes Ngobrol</button>
                <button type="submit" name="refresh" value="1" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-5 rounded-lg text-sm"><i class="fas fa-rotate mr-2"></i>Refresh Mesin</button>
                <button type="submit" name="scan" value="1" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-5 rounded-lg text-sm"><i class="fas fa-radar mr-2"></i>Scan Protokol (read-only)</button>
                <button type="submit" name="zktest" value="1" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-5 rounded-lg text-sm"><i class="fas fa-plug mr-2"></i>Tes Biner ZK</button>
                <button type="submit" name="variant" value="1" class="bg-violet-600 hover:bg-violet-700 text-white font-bold py-2 px-5 rounded-lg text-sm"><i class="fas fa-shuffle mr-2"></i>Tes Varian</button>
                <button type="submit" name="trykeys" value="1" class="bg-slate-600 hover:bg-slate-700 text-white font-bold py-2 px-5 rounded-lg text-sm"><i class="fas fa-key mr-2"></i>Tebak Kunci Umum</button>
            </form>
            
            <!-- Form Tes Enroll (terpisah karena butuh pilihan mesin master + PIN test) -->
            <?php if(!empty($devices)): ?>
            <form method="GET" class="flex flex-col md:flex-row gap-2 items-stretch md:items-end mb-4 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                <input type="hidden" name="page" value="karyawan">
                <input type="hidden" name="aksi" value="debug_mesin">
                <div>
                    <label class="text-xs font-bold text-purple-700 uppercase">Mesin Master (untuk enroll)</label><br>
                    <select name="enroll_master" required class="border border-purple-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">- Pilih Mesin -</option>
                        <option value="auto" style="font-weight:bold;background:#f3e8ff;">🔍 AUTO-DETECT (scan semua mesin)</option>
                        <?php foreach($devices as $d): ?>
                        <option value="<?= $d['id_mesin'] ?>"><?= htmlspecialchars($d['nama_mesin']) ?> (<?= htmlspecialchars($d['ip_mesin'] ?: '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-purple-700 uppercase">PIN Test (default 8888)</label><br>
                    <input type="text" name="test_pin" value="8888" class="border border-purple-300 rounded-lg px-3 py-2 text-sm w-28">
                </div>
                <button type="submit" name="testenroll" value="1" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-5 rounded-lg text-sm"><i class="fas fa-fingerprint mr-2"></i>Tes Enroll + Auto-sebar</button>
                <div class="text-xs text-purple-700 max-w-md">Setelah klik, langsung <b>tempel jari 3x</b> di mesin master. Web akan cek apakah template terdeteksi, lalu auto-sebar ke mesin lain.</div>
            </form>
            <?php endif; ?>

            <?php if($runEnroll): ?>
                <?php if(empty($enroll_results)): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 mb-4">Pilih mesin master dulu.</div>
                <?php else: ?>
                    <?php foreach($enroll_results as $er): ?>
                    <div class="border rounded-xl mb-4 overflow-hidden <?= ($er['status']??'')=='success'?'border-green-200':(($er['status']??'')=='waiting'?'border-amber-200':'border-red-200') ?>">
                        <div class="px-4 py-3 font-bold <?= ($er['status']??'')=='success'?'bg-green-50 text-green-800':(($er['status']??'')=='waiting'?'bg-amber-50 text-amber-800':'bg-red-50 text-red-800') ?>">
                            <i class="fas fa-fingerprint mr-2"></i>Tes Enroll: <?= htmlspecialchars($er['device']) ?> (<?= htmlspecialchars($er['ip']??'-') ?>)
                            <?php if(($er['status']??'')=='success'): ?>
                                — BERHASIL: Jari baru terdeteksi dan disebar
                            <?php elseif(($er['status']??'')=='waiting'): ?>
                                — MENUNGGU: Tempel jari di mesin, lalu klik Tes Enroll lagi
                            <?php else: ?>
                                — GAGAL
                            <?php endif; ?>
                        </div>
                        <div class="p-4 space-y-2 text-sm">
                            <?php foreach(($er['steps']??[]) as $key=>$step): ?>
                                <?php if($key === 'spread' && isset($step['detail'])): ?>
                                    <div class="flex gap-2 items-start">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold <?= $step['ok']?'bg-green-100 text-green-800':'bg-gray-100 text-gray-500' ?>"><?= $step['ok']?'OK':'SKIP' ?></span>
                                        <div class="flex-1">
                                            <b>Auto-sebar ke mesin lain</b>
                                            <div class="mt-2 space-y-1">
                                                <?php foreach($step['detail'] as $d): ?>
                                                <div class="p-2 rounded border <?= $d['status']=='success'?'border-green-200 bg-green-50':($d['status']=='warning'?'border-amber-200 bg-amber-50':($d['status']=='skipped'?'border-gray-200 bg-gray-50':'border-red-200 bg-red-50')) ?>">
                                                    <b><?= htmlspecialchars($d['device']) ?></b>: <?= htmlspecialchars($d['msg']) ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="flex gap-2 items-start">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold <?= $step['ok']?'bg-green-100 text-green-800':'bg-red-100 text-red-800' ?>"><?= $step['ok']?'OK':'GAGAL' ?></span>
                                        <div><b><?= ucfirst($key) ?></b><pre class="bg-gray-50 border rounded p-2 mt-1 text-xs overflow-x-auto whitespace-pre-wrap"><?= htmlspecialchars($step['msg']) ?></pre></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($runRefresh): ?>
            <div class="border rounded-xl mb-4 overflow-hidden border-cyan-200">
                <div class="px-4 py-3 font-bold bg-cyan-50 text-cyan-800"><i class="fas fa-rotate mr-2"></i>Hasil Refresh Mesin — habis ini cek layar mesin (Menu → User)</div>
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach($refresh_results as $rr): ?>
                    <div class="p-3 rounded-lg border bg-white <?= $rr['ok']?'border-green-200':'border-red-200' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($rr['device']) ?> <span class="text-xs text-gray-400 font-normal"><?= htmlspecialchars($rr['ip']) ?></span></p>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $rr['ok']?'bg-green-100 text-green-800':'bg-red-100 text-red-800' ?>"><?= $rr['ok']?'OK':'GAGAL' ?></span>
                        </div>
                        <p class="text-xs <?= $rr['ok']?'text-green-600':'text-red-600' ?>"><?= htmlspecialchars($rr['msg']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if($runKeys): ?>
                <?php foreach($key_results as $kr): ?>
                <div class="border rounded-xl mb-4 overflow-hidden <?= $kr['found']!==null?'border-green-200':'border-red-200' ?>">
                    <div class="px-4 py-3 font-bold <?= $kr['found']!==null?'bg-green-50 text-green-800':'bg-red-50 text-red-800' ?>">
                        Kunci <?= htmlspecialchars($kr['device']) ?> (<?= htmlspecialchars($kr['ip']) ?>) —
                        <?= $kr['found']!==null ? ('KETEMU: '.htmlspecialchars((string)$kr['found']).' — isi angka ini di Setting Device → Comm Key') : 'tidak ada kunci umum yang cocok' ?>
                        <?php if($kr['found']===null): ?><div class="font-normal text-xs mt-1">Minta kunci asli ke yang setting mesin, atau reset Comm Key di mesin jadi 0.</div><?php endif; ?>
                    </div>
                    <div class="p-4 text-sm space-y-1">
                        <?php foreach($kr['tried'] as $t): ?>
                        <div class="flex gap-2 items-center">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= $t['ok']?'bg-green-100 text-green-800':'bg-gray-100 text-gray-500' ?>"><?= $t['ok']?'COCOK':'gagal' ?></span>
                            <span class="font-mono">key=<?= htmlspecialchars((string)$t['key']) ?></span>
                            <span class="text-gray-500 text-xs"><?= htmlspecialchars($t['msg']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if($runVar): ?>
                <?php foreach($var_results as $vr): ?>
                <div class="border border-violet-200 rounded-xl mb-4 overflow-hidden">
                    <div class="px-4 py-3 font-bold bg-violet-50 text-violet-800">Varian <?= htmlspecialchars($vr['device']) ?> (<?= htmlspecialchars($vr['ip']) ?>:<?= (int)$vr['port'] ?>)</div>
                    <div class="p-4 space-y-2 text-sm">
                        <?php foreach($vr['items'] as $label=>$it): ?>
                        <div class="flex gap-2 items-start">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= $it['ok']?'bg-green-100 text-green-800':'bg-gray-100 text-gray-500' ?>"><?= $it['ok']?'BALAS':'DIAM' ?></span>
                            <div><b><?= htmlspecialchars($label) ?></b><pre class="bg-gray-50 border rounded p-2 mt-1 text-xs overflow-x-auto whitespace-pre-wrap"><?= htmlspecialchars($it['msg']) ?></pre></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if($runZk): ?>
                <?php if(empty($zk_results)): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">Tidak ada mesin dengan IP terisi.</div>
                <?php endif; ?>
                <?php foreach($zk_results as $zr): ?>
                <div class="border rounded-xl mb-4 overflow-hidden <?= $zr['verdict']['color']=='green'?'border-green-200':($zr['verdict']['color']=='amber'?'border-amber-200':'border-red-200') ?>">
                    <div class="px-4 py-3 font-bold <?= $zr['verdict']['color']=='green'?'bg-green-50 text-green-800':($zr['verdict']['color']=='amber'?'bg-amber-50 text-amber-800':'bg-red-50 text-red-800') ?>">
                        BINER <?= htmlspecialchars($zr['device']) ?> (<?= htmlspecialchars($zr['ip']) ?>:<?= (int)$zr['port'] ?>) — <?= $zr['verdict']['label'] ?>
                        <div class="font-normal text-xs mt-1"><?= htmlspecialchars($zr['verdict']['why']) ?></div>
                    </div>
                    <div class="p-4 space-y-2 text-sm">
                        <?php foreach(['connect'=>'1. Handshake','tulis'=>'2. Tulis user test 9999','hapus'=>'3. Hapus user test 9999'] as $k=>$label): $s=$zr['steps'][$k]; ?>
                        <div class="flex gap-2 items-start">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= $s['ok']?'bg-green-100 text-green-800':'bg-red-100 text-red-800' ?>"><?= $s['ok']?'OK':'GAGAL' ?></span>
                            <div><b><?= $label ?></b><pre class="bg-gray-50 border rounded p-2 mt-1 text-xs overflow-x-auto whitespace-pre-wrap"><?= htmlspecialchars($s['msg']) ?></pre></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if($runScan): ?>
                <?php foreach($scan_results as $sr): ?>
                <div class="border border-emerald-200 rounded-xl mb-4 overflow-hidden">
                    <div class="px-4 py-3 font-bold bg-emerald-50 text-emerald-800">Scan <?= htmlspecialchars($sr['device']) ?> (<?= htmlspecialchars($sr['ip']) ?>) — baca saja, tidak menulis</div>
                    <div class="p-4 overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead><tr class="bg-gray-50">
                            <th class="border px-2 py-1">Port</th><th class="border px-2 py-1">TCP</th>
                            <th class="border px-2 py-1">GET /</th><th class="border px-2 py-1">POST SOAP</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach($sr['ports'] as $p): ?>
                        <tr>
                            <td class="border px-2 py-1 font-bold"><?= (int)$p['port'] ?></td>
                            <td class="border px-2 py-1"><?= $p['tcp'] ? ('<span class="px-1 rounded bg-green-100 text-green-800 font-bold">BUKA ('.$p['ms'].' ms)</span>') : '<span class="px-1 rounded bg-gray-100 text-gray-500 font-bold">TUTUP</span>' ?></td>
                            <td class="border px-2 py-1"><pre class="whitespace-pre-wrap"><?= htmlspecialchars($p['get']) ?></pre></td>
                            <td class="border px-2 py-1"><pre class="whitespace-pre-wrap"><?= htmlspecialchars($p['soap']) ?></pre></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if($run): ?>
                <?php if(empty($results)): ?>
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">Tidak ada mesin dengan IP terisi. Isi IP dulu di Setting Device.</div>
                <?php endif; ?>
                <?php foreach($results as $r): ?>
                <div class="border rounded-xl mb-4 overflow-hidden <?= $r['verdict']['color']=='green'?'border-green-200':($r['verdict']['color']=='amber'?'border-amber-200':'border-red-200') ?>">
                    <div class="px-4 py-3 font-bold <?= $r['verdict']['color']=='green'?'bg-green-50 text-green-800':($r['verdict']['color']=='amber'?'bg-amber-50 text-amber-800':'bg-red-50 text-red-800') ?>">
                        <?= htmlspecialchars($r['device']) ?> (<?= htmlspecialchars($r['ip']) ?>:<?= (int)$r['port'] ?>) — <?= $r['verdict']['label'] ?>
                        <div class="font-normal text-xs mt-1"><?= htmlspecialchars($r['verdict']['why']) ?></div>
                    </div>
                    <div class="p-4 space-y-2 text-sm">
                        <?php foreach(['tcp'=>'1. Koneksi TCP','tulis'=>'2. Tulis (SetUserInfo PIN 9999)','baca'=>'3. Baca balik (GetAllUserInfo)','hapus'=>'4. Hapus user test (DeleteUser)'] as $k=>$label): $s=$r['steps'][$k]; ?>
                        <div class="flex gap-2 items-start">
                            <span class="px-2 py-0.5 rounded text-xs font-bold <?= $s['ok']?'bg-green-100 text-green-800':'bg-red-100 text-red-800' ?>"><?= $s['ok']?'OK':'GAGAL' ?></span>
                            <div><b><?= $label ?></b><pre class="bg-gray-50 border rounded p-2 mt-1 text-xs overflow-x-auto whitespace-pre-wrap"><?= htmlspecialchars($s['msg']) ?></pre></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">Pilih mesin + port, lalu klik <b>Tes Ngobrol</b>. Tes ini membuat user PIN 9999 sesaat lalu menghapusnya lagi — aman untuk data asli.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
