<?php
// Enroll + Auto-sebar: admin mulai enroll dari web -> user tempel jari di mesin master ->
// web polling sampai template muncul -> otomatis sebar ke mesin lain.
session_start();
set_time_limit(0);
include "../../koneksi.php";
include "../../device_sync_helper.php";
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
if(!function_exists('enroll_cmds')){
function enroll_cmds($comm, $pin) {
    return [
        "<StartEnroll><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID></Arg></StartEnroll>",
        "<EnrollUser><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN></Arg></EnrollUser>",
        "<StartEnrollEx><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID><RETRY xsi:type=\"xsd:integer\">3</RETRY></Arg></StartEnrollEx>",
    ];
}
}
if(!function_exists('dev_by_id')){
function dev_by_id($koneksi, $id) {
    $r = $koneksi->query("SELECT * FROM tb_mesin WHERE id_mesin='" . $koneksi->real_escape_string($id) . "' LIMIT 1");
    return ($r && $r->num_rows) ? $r->fetch_assoc() : null;
}
}

if ($action === 'start') {
    $kid = intval($_POST['karyawan_id'] ?? 0);
    $mid = trim($_POST['master_id'] ?? '');
    $q = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$kid LIMIT 1");
    $emp = $q ? $q->fetch_assoc() : null;
    if (!$emp || empty($emp['no_absen'])) { echo json_encode(['success'=>false,'msg'=>'Karyawan / no absen tidak valid']); exit; }
    
    if ($mid === 'auto') {
        // AUTO-DETECT MODE: get baseline from all online devices
        $baselines = [];
        $devices_info = [];
        $pin = trim($emp['no_absen']);
        
        foreach (get_all_devices($koneksi) as $d) {
            if (empty($d['ip_mesin']) || !test_device_connection($d['ip_mesin'], $d['port'])) continue;
            if (($d['protocol'] ?? 'soap') === 'zkudp') continue; // Skip binary devices
            $templates = get_fingerprint_templates($d['ip_mesin'], $d['port'], $d['comm_key'] ?? 0, $pin);
            $baseIds = array_map(fn($t)=>(int)$t['finger_id'], $templates);
            $baselines[$d['id_mesin']] = $baseIds;
            $devices_info[] = ['id' => $d['id_mesin'], 'name' => $d['nama_mesin'], 'ip' => $d['ip_mesin']];
        }
        
        // Validate: at least one device must be online for auto-detect
        if (empty($baselines)) {
            echo json_encode(['success'=>false,'msg'=>'Tidak ada mesin SOAP yang online. Kemungkinan: (1) Semua mesin offline, (2) Tidak ada IP di Setting Device, atau (3) Semua mesin pakai protocol biner (zkudp). Gunakan pilihan mesin spesifik atau cek Debug Mesin.']);
            exit;
        }
        
        log_sync_activity($koneksi, 'enroll_auto_start', null, $kid, 'success', "Enroll AUTO-DETECT '{$emp['nama_karyawan']}' (PIN $pin) dimulai, scanning " . count($baselines) . " mesin");
        
        echo json_encode(['success'=>true,'pin'=>$pin,'nama'=>$emp['nama_karyawan'],
            'master'=>'AUTO-DETECT','master_id'=>'auto',
            'baselines'=>$baselines,'devices'=>$devices_info,
            'msg'=>"Mode AUTO-DETECT: Tempel jari 3x di MESIN MANA SAJA (dari ".count($baselines)." mesin online). Sistem akan auto-detect."]);
        exit;
    }
    
    $dev = dev_by_id($koneksi, $mid);
    if (!$dev || empty($dev['ip_mesin'])) { echo json_encode(['success'=>false,'msg'=>'Mesin master tidak valid']); exit; }
    if (!test_device_connection($dev['ip_mesin'], $dev['port'])) { echo json_encode(['success'=>false,'msg'=>'Mesin master offline ('.$dev['ip_mesin'].')']); exit; }
    $pin = trim($emp['no_absen']); $comm = $dev['comm_key'] ?? 0;
    // Pastikan akun ada di master
    $safeNm = htmlspecialchars($emp['nama_karyawan'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
    send_soap_request($dev['ip_mesin'], $dev['port'], "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$safeNm}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>");
    // Masuk mode enroll
    $enrollOk = false;
    foreach (enroll_cmds($comm, $pin) as $cmd) {
        $resp = send_soap_request($dev['ip_mesin'], $dev['port'], $cmd);
        if ($resp && (stripos($resp,'Success')!==false || stripos($resp,'Successfully')!==false)) { $enrollOk = true; break; }
    }
    $base = get_fingerprint_templates($dev['ip_mesin'], $dev['port'], $comm, $pin);
    $baseIds = array_map(fn($t)=>(int)$t['finger_id'], $base);
    log_sync_activity($koneksi, 'enroll_auto_start', $dev['id_mesin'], $kid, 'success', "Enroll '{$emp['nama_karyawan']}' (PIN $pin) dimulai, baseline " . count($baseIds) . " jari");
    echo json_encode(['success'=>true,'pin'=>$pin,'nama'=>$emp['nama_karyawan'],
        'master'=>$dev['nama_mesin'],'master_id'=>$dev['id_mesin'],
        'enroll_mode'=>$enrollOk,'baseline'=>$baseIds,
        'msg'=>$enrollOk ? "Mesin masuk mode enroll — tempel jari 3x di {$dev['nama_mesin']} sekarang" : "Mesin tidak masuk mode enroll otomatis — enroll manual via Menu → PIN $pin, web tetap memantau"]);
    exit;
}

if ($action === 'poll') {
    $pin = trim($_POST['pin'] ?? '');
    $mid = trim($_POST['master_id'] ?? '');
    if ($mid === 'auto') {
        // AUTO-DETECT POLL: check all devices for new fingerprints
        // NOTE: baselines dikirim sebagai JSON string (baselines_json) karena
        // jQuery $.post menghilangkan array kosong saat form-encode,
        // sehingga $_POST['baselines'] bisa hilang padahal baseline memang [] (belum ada sidik).
        $baselines = [];
        if (isset($_POST['baselines_json']) && $_POST['baselines_json'] !== '') {
            $decoded = json_decode($_POST['baselines_json'], true);
            if (is_array($decoded)) $baselines = $decoded;
        } elseif (isset($_POST['baselines']) && is_array($_POST['baselines'])) {
            $baselines = (array)$_POST['baselines'];
        }
        if ($pin === '') { echo json_encode(['success'=>false,'msg'=>'Param kurang (poll-auto): pin kosong']); exit; }
        if (empty($baselines)) {
            // Fallback: anggap baseline kosong untuk semua mesin SOAP online.
            // (Kasus umum: PIN baru, belum ada sidik di mesin mana pun.)
            foreach (get_all_devices($koneksi) as $d) {
                if (empty($d['ip_mesin']) || !test_device_connection($d['ip_mesin'], $d['port'])) continue;
                if (($d['protocol'] ?? 'soap') === 'zkudp') continue;
                $baselines[$d['id_mesin']] = [];
            }
            if (empty($baselines)) { echo json_encode(['success'=>false,'msg'=>'Param kurang (poll-auto): tidak ada mesin SOAP online untuk dipantau']); exit; }
        }
        
        $detected_device_id = null;
        $detected_device_name = '';
        $detected_device_ip = '';
        $new = [];
        $curIds = [];
        
        foreach ($baselines as $did => $base) {
            $d = dev_by_id($koneksi, $did);
            if (!$d || !test_device_connection($d['ip_mesin'] ?? '', $d['port'] ?? '')) continue;
            
            $cur = get_fingerprint_templates($d['ip_mesin'], $d['port'], $d['comm_key'] ?? 0, $pin);
            $curIds = array_map(fn($t)=>(int)$t['finger_id'], $cur);
            $new = array_values(array_diff($curIds, array_map('intval', (array)$base)));
            
            if (!empty($new)) {
                $detected_device_id = $did;
                $detected_device_name = $d['nama_mesin'];
                $detected_device_ip = $d['ip_mesin'];
                break; // Found new fingerprint on this device!
            }
        }
        
        if ($detected_device_id) {
            echo json_encode(['success'=>true,'done'=>true,'new'=>$new,'current'=>$curIds,
                'detected_device_id'=>$detected_device_id,
                'detected_device_name'=>$detected_device_name,
                'detected_device_ip'=>$detected_device_ip,
                'msg'=>"Jari baru terdeteksi di {$detected_device_name} ({$detected_device_ip})"]);
        } else {
            echo json_encode(['success'=>true,'done'=>false,'new'=>[],'msg'=>'Belum ada jari baru di semua mesin']);
        }
        exit;
    }
    
    $base = array_map('intval', (array)($_POST['baseline'] ?? []));
    if ($pin === '' || $mid === '') { echo json_encode(['success'=>false,'msg'=>'Param kurang (poll): pin='.($pin===''?'kosong':'ok').', master_id='.($mid===''?'kosong':'ok')]); exit; }
    $dev = dev_by_id($koneksi, $mid);
    if (!$dev || !test_device_connection($dev['ip_mesin'] ?? '', $dev['port'] ?? '')) { echo json_encode(['success'=>false,'msg'=>'Mesin master offline']); exit; }
    $cur = get_fingerprint_templates($dev['ip_mesin'], $dev['port'], $dev['comm_key'] ?? 0, $pin);
    $curIds = array_map(fn($t)=>(int)$t['finger_id'], $cur);
    $new = array_values(array_diff($curIds, $base));
    echo json_encode(['success'=>true,'current'=>$curIds,'new'=>$new,'done'=>!empty($new)]);
    exit;
}

if ($action === 'spread') {
    $pin = trim($_POST['pin'] ?? '');
    $mid = trim($_POST['master_id'] ?? '');
    if ($pin === '' || $mid === '') { echo json_encode(['success'=>false,'msg'=>'Param kurang (spread): pin='.($pin===''?'kosong':'ok').', master_id='.($mid===''?'kosong':'['.$mid.']')]); exit; }
    $master = dev_by_id($koneksi, $mid);
    if (!$master) { echo json_encode(['success'=>false,'msg'=>'Master tidak ketemu']); exit; }
    $tpls = get_fingerprint_templates($master['ip_mesin'], $master['port'], $master['comm_key'] ?? 0, $pin);
    if (empty($tpls)) { echo json_encode(['success'=>false,'msg'=>'Belum ada template di master']); exit; }
    $nm = '';
    $qr = $koneksi->query("SELECT nama_karyawan FROM ms_karyawan WHERE no_absen='" . $koneksi->real_escape_string($pin) . "' LIMIT 1");
    if ($qr && ($rw = $qr->fetch_assoc())) $nm = $rw['nama_karyawan'];
    if ($nm === '') $nm = 'User ' . $pin;
    $safeNm = htmlspecialchars($nm, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $out = [];
    foreach (get_all_devices($koneksi) as $dev) {
        if ((string)$dev['id_mesin'] === (string)$mid) continue;
        if (empty($dev['ip_mesin']) || !test_device_connection($dev['ip_mesin'], $dev['port'])) {
            $out[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'status'=>'skipped','message'=>'Offline / IP kosong'];
            continue;
        }
        if (($dev['protocol'] ?? 'soap') === 'zkudp') {
            $out[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'status'=>'error','message'=>'Target biner tidak support template'];
            continue;
        }
        $tkey = $dev['comm_key'] ?? 0;
        send_soap_request($dev['ip_mesin'], $dev['port'], "<DisableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></DisableDevice>");
        send_soap_request($dev['ip_mesin'], $dev['port'], "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$safeNm}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>");
        $ok = 0;
        foreach ($tpls as $t) {
            if (set_fingerprint_template($dev['ip_mesin'], $dev['port'], $tkey, $pin, (int)$t['finger_id'], $t['template'], (int)($t['size'] ?? 0), (int)($t['valid'] ?? 1))) $ok++;
        }
        send_soap_request($dev['ip_mesin'], $dev['port'], "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></RefreshData>");
        send_soap_request($dev['ip_mesin'], $dev['port'], "<EnableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></EnableDevice>");
        $cek = get_fingerprint_templates($dev['ip_mesin'], $dev['port'], $tkey, $pin);
        $st = ($ok === count($tpls) && count($cek) >= count($tpls)) ? 'success' : ($ok > 0 ? 'warning' : 'error');
        $out[$dev['id_mesin']] = ['device'=>$dev['nama_mesin'],'status'=>$st,'message'=>"Terkirim $ok/" . count($tpls) . " jari, terverifikasi " . count($cek) . " jari"];
        log_sync_activity($koneksi, 'enroll_auto_spread', $dev['id_mesin'], null, $st, "Auto-sebar PIN $pin: " . $out[$dev['id_mesin']]['message']);
    }
    echo json_encode(['success'=>true,'results'=>$out,'fingers'=>count($tpls)]);
    exit;
}

echo json_encode(['success'=>false,'msg'=>'Action tidak dikenal']);
