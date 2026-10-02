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
$results = [];
$scan_results = [];
$zk_results = [];
$var_results = [];

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
