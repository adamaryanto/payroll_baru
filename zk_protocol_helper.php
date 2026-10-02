<?php
/**
 * ZK Binary Protocol Helper (TCP 4370) — untuk mesin yang TIDAK bicara SOAP,
 * mis. Solution X902 (port kebuka tapi RST saat dikirimi HTTP/SOAP).
 *
 * Basis: protokol biner ZK ( утверждённый dari implementasi referensi pyzk):
 * - Paket TCP = top 8B [0x5050, 0x7282, len] + paket
 * - Paket = header 8B [cmd, checksum, session, reply] + data
 * - Checksum = one's-complement sum 16-bit LE (chip sama, reply_id+1)
 *
 * STATUS: EXPERIMENTAL. Fungsi tulis hanya dipakai dari halaman debug
 * (user test PIN 9999 + hapus lagi). Jangan dipakai sync massal
 * sebelum handshake terbukti OK di mesin target.
 */

define('ZK_CMD_CONNECT', 1000);
define('ZK_CMD_EXIT', 1001);
define('ZK_CMD_ENABLEDEVICE', 1002);
define('ZK_CMD_DISABLEDEVICE', 1003);
define('ZK_CMD_SET_USER', 8);
define('ZK_CMD_DELETE_USER', 18);
define('ZK_CMD_ACK_OK', 2000);
define('ZK_CMD_ACK_ERROR', 2001);
define('ZK_CMD_ACK_DATA', 2002);
define('ZK_CMD_ACK_UNAUTH', 2005);
define('ZK_CMD_REFRESHDATA', 1013);
define('ZK_CMD_AUTH', 1102);
define('ZK_USHRT_MAX', 65535);

function zkb_hex($bin, $max = 64){
    $s = substr($bin, 0, $max);
    $o = '';
    for($i = 0; $i < strlen($s); $i++) $o .= sprintf('%02X ', ord($s[$i]));
    return trim($o) . (strlen($bin) > $max ? ' ...' : '');
}

function zkb_checksum($p){
    $l = strlen($p);
    $checksum = 0;
    $o = 0;
    while($l > 1){
        $checksum += (ord($p[$o]) | (ord($p[$o+1]) << 8));
        if($checksum > ZK_USHRT_MAX) $checksum -= ZK_USHRT_MAX;
        $o += 2; $l -= 2;
    }
    if($l) $checksum += ord($p[$o]);
    while($checksum > ZK_USHRT_MAX) $checksum -= ZK_USHRT_MAX;
    $checksum = ~$checksum;
    while($checksum < 0) $checksum += ZK_USHRT_MAX;
    return $checksum & 0xFFFF;
}

function zkb_build_packet($command, $data, $session, &$reply){
    $buf = pack('v4', $command, 0, $session, $reply) . $data;
    $cs = zkb_checksum($buf);
    $reply++;
    if($reply >= ZK_USHRT_MAX) $reply -= ZK_USHRT_MAX;
    $packet = pack('v4', $command, $cs, $session, $reply) . $data;
    return pack('vvV', 0x5050, 0x7282, strlen($packet)) . $packet;
}

function zkb_packet($command, $data, $session, &$reply){
    return zkb_build_packet($command, $data, $session, $reply);
}

/** UDP probe: sebagian firmware hanya melayani biner via UDP. */
function zkb_udp_probe($ip, $port = 4370){
    $reply = ZK_USHRT_MAX - 1;
    $topPkt = zkb_build_packet(ZK_CMD_CONNECT, '', 0, $reply);
    // UDP tanpa top 8B
    $pkt = substr($topPkt, 8);
    $s = @fsockopen('udp://'.$ip, $port, $errno, $errstr, 3);
    if(!$s) return ['ok'=>false, 'msg'=>'UDP tidak bisa dibuka: '.$errstr];
    @stream_set_timeout($s, 3);
    @fwrite($s, $pkt);
    $resp = @fread($s, 2048);
    @fclose($s);
    if($resp === false || $resp === '') return ['ok'=>false, 'msg'=>'UDP tanpa balasan (timeout)'];
    if(strlen($resp) < 8) return ['ok'=>true, 'msg'=>'UDP dibalas '.strlen($resp).'B: '.zkb_hex($resp)];
    $h = @unpack('vcmd/vcs/vsess/vreply', substr($resp, 0, 8));
    if(!$h) return ['ok'=>true, 'msg'=>'UDP dibalas (bukan format ZK): '.zkb_hex($resp, 48)];
    return ['ok'=>true, 'msg'=>'UDP dibalas cmd='.$h['cmd'].' sess='.$h['sess'].' hex: '.zkb_hex($resp, 48)];
}

/** Varian HTTP: 1.1 + Host, path alternatif. */
function zkb_http_probe($ip, $port){
    $out = [];
    $tests = [
        'GET / HTTP/1.1' => "GET / HTTP/1.1\r\nHost: {$ip}\r\nConnection: close\r\n\r\n",
        'GET /iWsService HTTP/1.1' => "GET /iWsService HTTP/1.1\r\nHost: {$ip}\r\nConnection: close\r\n\r\n",
        'POST / HTTP/1.1 (SOAP GetAllUserInfo)' => null, // diisi di bawah
    ];
    $soapBody = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">0</ArgComKey></GetAllUserInfo>";
    $tests['POST / HTTP/1.1 (SOAP GetAllUserInfo)'] = "POST / HTTP/1.1\r\nHost: {$ip}\r\nContent-Type: text/xml\r\nContent-Length: ".strlen($soapBody)."\r\nConnection: close\r\n\r\n".$soapBody;
    foreach($tests as $label => $req){
        $s = @fsockopen($ip, $port, $errno, $errstr, 3);
        if(!$s){ $out[$label] = ['ok'=>false, 'msg'=>'TCP gagal: '.$errstr]; continue; }
        @stream_set_timeout($s, 4);
        @fputs($s, $req);
        $buf = '';
        $t0 = time();
        while(!@feof($s)){
            if(time() - $t0 > 4) break;
            $ln = @fgets($s, 1024);
            if($ln === false) break;
            $buf .= $ln;
            if(strlen($buf) > 1500) break;
        }
        @fclose($s);
        $out[$label] = trim($buf) === ''
            ? ['ok'=>false, 'msg'=>'(ditutup tanpa balasan)']
            : ['ok'=>true, 'msg'=>substr($buf, 0, 400)];
    }
    return $out;
}

function zkb_recv($sock, $timeout = 5){
    @stream_set_timeout($sock, $timeout);
    $top = '';
    $t0 = time();
    while(strlen($top) < 8){
        if(time() - $t0 > $timeout) return [false, 'timeout baca header'];
        $c = @fread($sock, 8 - strlen($top));
        if($c === false || $c === ''){ if(@feof($sock)) return [false, 'koneksi ditutup mesin (RST/EOF)']; continue; }
        $top .= $c;
    }
    $h = @unpack('vmagic1/vmagic2/Vlen', $top);
    if(!$h || $h['magic1'] != 0x5050 || $h['magic2'] != 0x7282) return [false, 'header bukan paket ZK (hex: '.zkb_hex($top, 8).')'];
    $len = $h['len'];
    if($len <= 0 || $len > 65535) return [false, 'panjang paket aneh: '.$len];
    $body = '';
    $t0 = time();
    while(strlen($body) < $len){
        if(time() - $t0 > $timeout) return [false, 'timeout baca body'];
        $c = @fread($sock, $len - strlen($body));
        if($c === false || $c === ''){ if(@feof($sock)) break; continue; }
        $body .= $c;
    }
    if(strlen($body) < 8) return [false, 'body terlalu pendek ('.strlen($body).'B)'];
    $hh = @unpack('vcmd/vcs/vsess/vreply', substr($body, 0, 8));
    return [true, ['cmd'=>$hh['cmd'],'session'=>$hh['sess'],'reply'=>$hh['reply'],'data'=>substr($body, 8),'raw'=>$top.$body]];
}

function zkb_write(&$st, $command, $data = ''){
    // Tulis 1 paket (tanpa menunggu balasan khusus). Return bool.
    if(!empty($st['udp'])){
        if(empty($st['sock'])) return false;
        $reply = $st['reply'];
        $buf = pack('v4', $command, 0, $st['session'], $reply) . $data;
        $cs = zkb_checksum($buf);
        $reply++;
        if($reply >= ZK_USHRT_MAX) $reply -= ZK_USHRT_MAX;
        $packet = pack('v4', $command, $cs, $st['session'], $reply) . $data;
        @stream_set_timeout($st['sock'], 4);
        $w = @fwrite($st['sock'], $packet);
        return $w !== false;
    }
    return false;
}

function zkb_recv_one($sock, $timeout = 4){
    // Baca 1 datagram UDP + parse header. Return [ok, response|msg].
    @stream_set_timeout($sock, $timeout);
    $resp = @fread($sock, 4096);
    if($resp === false || $resp === '' || strlen($resp) < 8) return [false, 'tanpa balasan'];
    $h = @unpack('vcmd/vcs/vsess/vreply', substr($resp, 0, 8));
    if(!$h) return [false, 'bukan paket ZK: '.zkb_hex($resp, 16)];
    return [true, ['cmd'=>$h['cmd'],'session'=>$h['sess'],'reply'=>$h['reply'],'data'=>substr($resp, 8),'raw'=>$resp]];
}

function zkb_send(&$st, $command, $data = ''){
    // Mode UDP: 1 socket untuk 1 sesi (seperti referensi), tanpa top 8B.
    if(!empty($st['udp'])){
        if(!zkb_write($st, $command, $data)) return [false, 'UDP gagal tulis'];
        usleep(100000);
        list($ok, $res) = zkb_recv_one($st['sock']);
        if(!$ok) return [false, 'UDP '.$res];
        $st['reply'] = $res['reply'];
        return [true, $res];
    }
    $pkt = zkb_packet($command, $data, $st['session'], $st['reply']);
    $st['_last_tx'] = $pkt;
    $w = @fwrite($st['sock'], $pkt);
    if($w === false) return [false, 'gagal tulis ke socket'];
    list($ok, $res) = zkb_recv($st['sock']);
    if(!$ok) return [false, $res];
    $st['reply'] = $res['reply'];
    $st['_last_rx'] = $res['raw'];
    return [true, $res];
}

function zkb_udp_connect($ip, $port = 4370, $comm_key = 0, &$log = []){
    $st = ['ip'=>$ip,'port'=>$port,'udp'=>true,'session'=>0,'reply'=>ZK_USHRT_MAX-1,'sock'=>null];
    $s = @fsockopen('udp://'.$ip, $port, $errno, $errstr, 3);
    if(!$s){ $log[] = 'socket UDP gagal: '.$errstr; return [false, $st, 'socket UDP gagal']; }
    $st['sock'] = $s;
    list($ok, $res) = zkb_send($st, ZK_CMD_CONNECT, '');
    if(!$ok){ $log[] = 'CONNECT UDP tanpa balasan'; return [false, $st, 'CONNECT UDP tanpa balasan']; }
    $st['session'] = $res['session'];
    $log[] = 'CONNECT UDP balas cmd='.$res['cmd'].' session='.$res['session'];
    if($res['cmd'] == ZK_CMD_ACK_UNAUTH){
        $auth = zkb_commkey($comm_key, $st['session']);
        $log[] = 'AUTH UDP kirim key='.intval($comm_key).' authhex='.zkb_hex($auth, 8);
        list($ok2, $res2) = zkb_send($st, ZK_CMD_AUTH, $auth);
        if(!$ok2){ $log[] = 'AUTH UDP tanpa balasan'; @fclose($st['sock']); $st['sock'] = null; return [false, $st, 'AUTH tanpa balasan | '.implode(' | ', $log)]; }
        $log[] = 'AUTH UDP balas cmd='.$res2['cmd'];
        if($res2['cmd'] != ZK_CMD_ACK_OK){ @fclose($st['sock']); $st['sock'] = null; return [false, $st, 'AUTH ditolak code='.$res2['cmd'].' (comm key salah?) | '.implode(' | ', $log)]; }
        $log[] = 'AUTH UDP OK';
    }
    if($res['cmd'] != ZK_CMD_ACK_OK && $res['cmd'] != ZK_CMD_ACK_UNAUTH){
        @fclose($st['sock']); $st['sock'] = null;
        return [false, $st, 'CONNECT ditolak, code='.$res['cmd']];
    }
    return [true, $st, 'connected UDP, session='.$st['session']];
}

function zkb_commkey($key, $session_id, $ticks = 50){
    $key = (int)$key; $session_id = (int)$session_id;
    $k = 0;
    for($i = 0; $i < 32; $i++){ $k = ($key & (1 << $i)) ? (($k << 1) | 1) : ($k << 1); }
    $k += $session_id;
    $b = pack('V', $k & 0xFFFFFFFF);
    $a = array_values(unpack('C4', $b));
    $b = chr($a[0]^ord('Z')).chr($a[1]^ord('K')).chr($a[2]^ord('S')).chr($a[3]^ord('O'));
    $h = array_values(unpack('v2', $b));
    $b = pack('v2', $h[1], $h[0]);
    $B = 0xff & $ticks;
    $a = array_values(unpack('C4', $b));
    return chr($a[0]^$B).chr($a[1]^$B).chr($B).chr($a[3]^$B);
}

function zkb_connect($ip, $port = 4370, $comm_key = 0, &$log = []){
    $st = ['ip'=>$ip,'port'=>$port,'session'=>0,'reply'=>ZK_USHRT_MAX-1,'sock'=>null];
    $sock = @fsockopen($ip, $port, $errno, $errstr, 3);
    if(!$sock){ $log[] = 'TCP gagal: '.$errstr; return [false, $st, 'TCP gagal']; }
    $st['sock'] = $sock;
    list($ok, $res) = zkb_send($st, ZK_CMD_CONNECT, '');
    if(!$ok){ @fclose($sock); $log[] = 'CONNECT tanpa balasan: '.$res; return [false, $st, $res]; }
    $st['session'] = $res['session'];
    $log[] = 'CONNECT balas cmd='.$res['cmd'].' session='.$res['session'].' ('.zkb_hex($res['raw'], 24).')';
    if($res['cmd'] == ZK_CMD_ACK_UNAUTH){
        $auth = zkb_commkey($comm_key, $st['session']);
        list($ok2, $res2) = zkb_send($st, ZK_CMD_AUTH, $auth);
        if(!$ok2 || $res2['cmd'] != ZK_CMD_ACK_OK){ @fclose($sock); return [false, $st, 'AUTH ditolak (comm key salah?)']; }
        $log[] = 'AUTH OK';
    }
    if($res['cmd'] != ZK_CMD_ACK_OK && $res['cmd'] != ZK_CMD_ACK_UNAUTH){
        @fclose($sock);
        return [false, $st, 'CONNECT ditolak, code='.$res['cmd']];
    }
    return [true, $st, 'connected, session='.$st['session']];
}

function zkb_cmd(&$st, $cmd, $data = ''){
    list($ok, $res) = zkb_send($st, $cmd, $data);
    if(!$ok) return [false, $res];
    return [($res['cmd'] == ZK_CMD_ACK_OK), $res];
}

function zkb_disconnect(&$st){
    if(!empty($st['udp'])){
        if(!empty($st['sock'])){ @zkb_send($st, ZK_CMD_EXIT, ''); @fclose($st['sock']); $st['sock'] = null; }
        return;
    }
    if(!empty($st['sock'])){ @zkb_send($st, ZK_CMD_EXIT, ''); @fclose($st['sock']); }
}

function zkb_set_user(&$st, $uid, $pin_str, $name, $privilege = 0){
    // Format user 72B: uid(H) priv(B) pass(8s) nama(24s) card(4s) pad group(7s) pad userid(24s)
    $uid = (int)$uid;
    $nameB = substr($name, 0, 24);
    $pinB = substr((string)$pin_str, 0, 24);
    $data = pack('vC', $uid, (int)$privilege)
        . str_pad('', 8, "\0")
        . str_pad($nameB, 24, "\0")
        . pack('V', 0) . "\0"
        . str_pad('', 7, "\0") . "\0"
        . str_pad($pinB, 24, "\0");
    list($ok, $res) = zkb_send($st, ZK_CMD_SET_USER, $data);
    if(!$ok) return [false, 'tanpa balasan: '.$res, ''];
    zkb_send($st, ZK_CMD_REFRESHDATA, '');
    return [($res['cmd'] == ZK_CMD_ACK_OK), 'code='.$res['cmd'], zkb_hex($res['raw'], 32)];
}

function zkb_delete_user(&$st, $uid){
    $data = pack('v', (int)$uid);
    list($ok, $res) = zkb_send($st, ZK_CMD_DELETE_USER, $data);
    if(!$ok) return [false, 'tanpa balasan: '.$res];
    zkb_send($st, ZK_CMD_REFRESHDATA, '');
    return [($res['cmd'] == ZK_CMD_ACK_OK), 'code='.$res['cmd']];
}

/**
 * Push 1 user (PIN+nama) via UDP. Return ['success'=>bool,'message'=>string].
 */
/**
 * Baca 1 chunk buffer (1504 + start/size). Kumpulkan datagram UDP sampai ACK_OK.
 * Return [ok, data|msg].
 */
function zkb_read_chunk(&$st, $start, $size){
    for($a = 0; $a < 3; $a++){
        if(!zkb_write($st, 1504, pack('V2', (int)$start, (int)$size))) continue;
        $data = '';
        for($i = 0; $i < 60; $i++){
            list($ok, $r) = zkb_recv_one($st['sock'], 3);
            if(!$ok) break;
            $st['reply'] = $r['reply'];
            if($r['cmd'] == 1501){ $data .= $r['data']; }
            elseif($r['cmd'] == 2000){ return [true, $data]; }
            else { break; }
        }
        if($data !== '') return [true, $data];
    }
    return [false, 'chunk tidak terbaca'];
}

/**
 * Baca buffer biner (1503 + chunk 1504). Return [ok, data|msg].
 */
function zkb_read_buffer(&$st, $command, $fct = 0, $ext = 0){
    $MAX = 16 * 1024;
    $payload = pack('c', 1).pack('v', (int)$command).pack('V', (int)$fct).pack('V', (int)$ext);
    list($ok, $res) = zkb_send($st, 1503, $payload);
    if(!$ok) return [false, 'PREPARE_BUFFER tanpa balasan'];
    if($res['cmd'] == 1501) return [true, $res['data']];
    if($res['cmd'] != 1500) return [false, 'RWB tidak didukung (code='.$res['cmd'].')'];
    $arr = @unpack('Vsize', substr($res['data'], 1, 4));
    $size = $arr ? (int)$arr['size'] : 0;
    if($size <= 0) return [false, 'ukuran buffer 0'];
    $data = '';
    $start = 0;
    while($start < $size){
        $chunk = min($MAX, $size - $start);
        list($cok, $cdata) = zkb_read_chunk($st, $start, $chunk);
        if(!$cok) return [false, $cdata];
        if(strlen($cdata) == 0) break;
        $data .= $cdata;
        $start += strlen($cdata);
    }
    zkb_send($st, 1502, '');
    return [true, $data];
}

function zkb_read_sizes(&$st){
    list($ok, $res) = zkb_send($st, 50, '');
    if(!$ok || ($res['cmd'] != 2000 && $res['cmd'] != 2002)) return [false, 'baca info memori gagal'];
    if(strlen($res['data']) < 80) return [false, 'info memori terlalu pendek'];
    $f = @unpack('V20', substr($res['data'], 0, 80));
    if(!$f) return [false, 'parse info memori gagal'];
    return [true, ['users'=>(int)$f[5]]];
}

/**
 * Baca daftar user via UDP. Return [ok, users|msg].
 * users = [['uid'=>int,'pin'=>str,'nama'=>str],...]. uid dipakai untuk update/delete aman.
 */
function zkb_read_users(&$st){
    list($ok, $sz) = zkb_read_sizes($st);
    if(!$ok) return [false, $sz];
    if($sz['users'] <= 0) return [true, []];
    list($ok, $data) = zkb_read_buffer($st, 9, 5, 0);
    if(!$ok) return [false, $data];
    if(strlen($data) < 4) return [false, 'data user kosong'];
    $t = @unpack('Vtotal', substr($data, 0, 4));
    $total = $t ? (int)$t['total'] : 0;
    $body = substr($data, 4);
    $users = [];
    if($total > 0 && $sz['users'] > 0){
        $ps = (int)($total / $sz['users']);
        if($ps == 72){
            for($o = 0; $o + 72 <= strlen($body); $o += 72){
                $uid = @unpack('v', substr($body, $o, 2));
                $uid = $uid ? (int)$uid[1] : 0;
                $nama = rtrim(substr($body, $o + 11, 24), "\0");
                $pin = rtrim(substr($body, $o + 48, 24), "\0");
                if($pin === '') $pin = (string)$uid;
                $users[] = ['uid'=>$uid, 'pin'=>$pin, 'nama'=>$nama];
            }
        } elseif($ps == 28){
            for($o = 0; $o + 28 <= strlen($body); $o += 28){
                $uid = @unpack('v', substr($body, $o, 2));
                $uid = (int)($uid ? $uid[1] : 0);
                $nama = rtrim(substr($body, $o + 8, 8), "\0");
                $up = @unpack('V', substr($body, $o + 24, 4));
                $pin = $up ? (string)$up[1] : (string)$uid;
                $users[] = ['uid'=>$uid, 'pin'=>$pin, 'nama'=>$nama];
            }
        } else {
            return [false, 'ukuran record user tak dikenal: '.$ps];
        }
    }
    return [true, $users];
}

function zkb_get_users($ip, $port, $key){
    $log = [];
    list($ok, $st, $msg) = zkb_udp_connect($ip, $port, $key, $log);
    if(!$ok) return ['success'=>false, 'message'=>$msg, 'users'=>[]];
    list($ok, $res) = zkb_read_users($st);
    zkb_disconnect($st);
    if(!$ok) return ['success'=>false, 'message'=>$res, 'users'=>[]];
    return ['success'=>true, 'message'=>'OK ('.count($res).' user)', 'users'=>$res];
}

/** Cari uid internal dari daftar user berdasarkan PIN string. Return uid|null. */
function zkb_find_uid($users, $pin){
    $pin = trim((string)$pin);
    foreach($users as $u){
        if(trim((string)($u['pin'] ?? '')) === $pin) return (int)$u['uid'];
    }
    return null;
}

function zkb_push_user($ip, $port, $key, $pin, $name){
    $log = [];
    list($ok, $st, $msg) = zkb_udp_connect($ip, $port, $key, $log);
    if(!$ok) return ['success'=>false, 'message'=>$msg];
    zkb_send($st, ZK_CMD_DISABLEDEVICE, '');
    // Resolve uid: pakai uid lama bila PIN sudah ada (update aman), else uid baru = max+1
    $uid = null;
    list($rok, $rusers) = zkb_read_users($st);
    if($rok){
        $uid = zkb_find_uid($rusers, $pin);
        if($uid === null){
            $mx = 0;
            foreach($rusers as $u) $mx = max($mx, (int)$u['uid']);
            $uid = $mx + 1;
            if($uid <= 0) $uid = 1;
        }
    } else {
        $uid = (int)preg_replace('/\D/', '', (string)$pin);
    }
    if($uid <= 0){ zkb_disconnect($st); return ['success'=>false, 'message'=>'PIN bukan numeric']; }
    list($okS, $msgS, $hex) = zkb_set_user($st, $uid, (string)$pin, (string)$name);
    zkb_send($st, ZK_CMD_ENABLEDEVICE, '');
    zkb_disconnect($st);
    return ['success'=>$okS, 'message'=>$msgS];
}

/**
 * Push banyak user dalam 1 sesi UDP. $employees = [['pin'=>..,'nama'=>..],...].
 * Return ['connected'=>bool,'message'=>string,'results'=>[['pin'=>..,'success'=>bool,'message'=>..],...]].
 */
function zkb_batch_push($ip, $port, $key, $employees){
    $log = [];
    list($ok, $st, $msg) = zkb_udp_connect($ip, $port, $key, $log);
    if(!$ok) return ['connected'=>false, 'message'=>$msg, 'results'=>[]];
    zkb_send($st, ZK_CMD_DISABLEDEVICE, '');
    // Petakan PIN -> uid internal sekali (update aman, tambah pakai max+1)
    $uidMap = [];
    $nextUid = 1;
    list($rok, $rusers) = zkb_read_users($st);
    if($rok){
        $mx = 0;
        foreach($rusers as $u){
            $uidMap[trim((string)($u['pin'] ?? ''))] = (int)$u['uid'];
            $mx = max($mx, (int)$u['uid']);
        }
        $nextUid = $mx + 1;
        if($nextUid <= 0) $nextUid = 1;
    }
    $results = [];
    foreach($employees as $e){
        $pin = (string)($e['pin'] ?? '');
        if(isset($uidMap[trim($pin)])){
            $uid = $uidMap[trim($pin)];
        } elseif($rok){
            $uid = $nextUid++;
        } else {
            // Baca user gagal: fallback uid=int(PIN) seperti dulu
            $uid = (int)preg_replace('/\D/', '', $pin);
        }
        if($uid <= 0){ $results[] = ['pin'=>$pin,'success'=>false,'message'=>'PIN bukan numeric']; continue; }
        list($okS, $msgS, $hex) = zkb_set_user($st, $uid, $pin, (string)($e['nama'] ?? ''));
        $results[] = ['pin'=>$pin,'success'=>$okS,'message'=>$msgS];
    }
    zkb_send($st, ZK_CMD_ENABLEDEVICE, '');
    zkb_disconnect($st);
    return ['connected'=>true, 'message'=>'OK', 'results'=>$results];
}

/**
 * Hapus 1 user via UDP. uid dicari dulu dari daftar user (exact-match PIN);
 * tidak ketemu = skip aman, tidak menebak.
 */
function zkb_remove_user($ip, $port, $key, $pin){
    $log = [];
    list($ok, $st, $msg) = zkb_udp_connect($ip, $port, $key, $log);
    if(!$ok) return ['success'=>false, 'message'=>$msg];
    zkb_send($st, ZK_CMD_DISABLEDEVICE, '');
    list($rok, $rusers) = zkb_read_users($st);
    $uid = null;
    if($rok) $uid = zkb_find_uid($rusers, $pin);
    if($uid === null || $uid <= 0){
        zkb_send($st, ZK_CMD_ENABLEDEVICE, '');
        zkb_disconnect($st);
        return ['success'=>false, 'message'=>'PIN tidak ditemukan di mesin (skip aman)'];
    }
    list($okD, $msgD) = zkb_delete_user($st, $uid);
    zkb_send($st, ZK_CMD_ENABLEDEVICE, '');
    zkb_disconnect($st);
    return ['success'=>$okD, 'message'=>$msgD];
}

/**
 * Dialog test lengkap untuk halaman debug: connect -> setUser(9999) -> deleteUser(9999) -> disconnect.
 * Hanya menyentuh PIN 9999. Return ['steps'=>[...], 'verdict'=>...].
 */
/**
 * Coba kunci umum via UDP (hanya default pabrik: 0, 123456, 888888, ...).
 * Read-only terhadap data (cuma handshake+auth, tidak tulis/hapus).
 * Return ['tried'=>[...], 'found'=>key|null].
 */
function zkb_try_common_keys($ip, $port){
    $keys = [0, 123456, 888888, 12345, 111111, 999999, 54321];
    $tried = [];
    $found = null;
    foreach($keys as $k){
        $log = [];
        list($ok, $st, $msg) = zkb_udp_connect($ip, $port, $k, $log);
        $tried[] = ['key'=>$k, 'ok'=>$ok, 'msg'=>$msg];
        if($ok){ $found = $k; zkb_disconnect($st); break; }
        if(count($tried) >= 7) break;
    }
    return ['tried'=>$tried, 'found'=>$found];
}

function zkb_test_dialog($ip, $port, $comm_key = 0){
    $steps = [];
    $log = [];
    $mode = 'TCP';
    list($ok, $st, $msg) = zkb_connect($ip, $port, $comm_key, $log);
    if(!$ok){
        // Fallback UDP (terbukti x902 menjawab UDP): sesi baru via UDP
        $log2 = [];
        list($ok2, $st2, $msg2) = zkb_udp_connect($ip, $port, $comm_key, $log2);
        $log = array_merge($log, $log2);
        if($ok2){ $ok = true; $st = $st2; $msg = $msg2; $mode = 'UDP'; }
    }
    $steps['connect'] = ['ok'=>$ok, 'msg'=>'['.$mode.'] '.$msg.' | '.implode(' | ', $log)];
    if(!$ok){
        $steps['tulis'] = ['ok'=>false, 'msg'=>'Dilewati (handshake gagal)'];
        $steps['hapus'] = ['ok'=>false, 'msg'=>'Dilewati (handshake gagal)'];
        return ['steps'=>$steps, 'verdict'=>['label'=>'GAGAL HANDSHAKE','color'=>'red','why'=>'Mesin tidak menjawab handshake biner. '.$msg]];
    }
    // tulis user test
    list($okS, $msgS, $hexS) = zkb_set_user($st, 9999, '9999', 'TESDEBUG');
    $steps['tulis'] = ['ok'=>$okS, 'msg'=>$msgS.' | balas: '.$hexS];
    // hapus user test (bersih-bersih, hanya jika tulis berhasil)
    if($okS){
        list($okD, $msgD) = zkb_delete_user($st, 9999);
        $steps['hapus'] = ['ok'=>$okD, 'msg'=>$msgD];
    } else {
        $steps['hapus'] = ['ok'=>false, 'msg'=>'Dilewati (tulis gagal)'];
    }
    zkb_disconnect($st);
    if($okS && $steps['hapus']['ok']){
        $verdict = ['label'=>'BISA NGOBROL BINER ('.$mode.')','color'=>'green','why'=>'Handshake + tulis + hapus user test 9999 semuanya ACK via '.$mode.'. Adapter biner cocok untuk mesin ini.'];
    } elseif($okS){
        $verdict = ['label'=>'SEBAGIAN (tulis ok, hapus gagal) ('.$mode.')','color'=>'amber','why'=>'User test masuk tapi hapus gagal. Cek di mesin apakah PIN 9999 nyangkut, hapus manual bila perlu.'];
    } else {
        $verdict = ['label'=>'HANDSHAKE OK, TULIS GAGAL','color'=>'amber','why'=>'Koneksi biner jalan tapi format user ditolak. Balasan hex di atas jadi bahan penyesuaian.'];
    }
    return ['steps'=>$steps, 'verdict'=>$verdict];
}
?>