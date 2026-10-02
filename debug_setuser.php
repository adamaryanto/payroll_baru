<?php
/**
 * DEBUG: Test semua kemungkinan SetUserInfo variants ke X606S
 * Coba kirim PIN + NAMA, terus GetAllUserInfo buat cek apakah nama ke-save
 * 
 * Buka: http://119.18.154.118:8080/absen/debug_setuser.php
 * Atau: http://localhost/absen/debug_setuser.php
 */

require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/device_sync_helper.php';

$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '999';
$nama = isset($_GET['nama']) ? trim($_GET['nama']) : 'TEST NAMA';
$device_id = isset($_GET['device']) ? intval($_GET['device']) : 0;

$devices = get_all_devices($koneksi);
$target = null;
foreach ($devices as $d) {
    if ($d['id_mesin'] == $device_id) { $target = $d; break; }
}
if (!$target && !empty($devices)) $target = $devices[0];

$results = [];

if ($target && !empty($target['ip_mesin']) && test_device_connection($target['ip_mesin'], $target['port'] ?? 80)) {
    $ip = $target['ip_mesin'];
    $port = $target['port'] ?? 80;
    $comm = $target['comm_key'] ?? 0;
    $namaEsc = htmlspecialchars($nama, ENT_XML1);

    // ============================================================
    // STEP 1: Coba berbagai format SetUserInfo
    // ============================================================
    $formats = [
        // Format 1: Standard (yang sekarang)
        'standard' => "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$namaEsc}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>",
        
        // Format 2: Tanpa type attributes
        'no_type' => "<SetUserInfo><ArgComKey>{$comm}</ArgComKey><Arg><PIN>{$pin}</PIN><Name>{$namaEsc}</Name><Password></Password><Group>1</Group><Privilege>0</Privilege></Arg></SetUserInfo>",
        
        // Format 3: Dengan CDATA
        'cdata' => "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\"><![CDATA[{$nama}]]></Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>",
        
        // Format 4: Dengan AccGroup (kadang dibutuhin)
        'accgroup' => "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$namaEsc}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege><AccGroup xsi:type=\"xsd:integer\">1</AccGroup><TimeZones xsi:type=\"xsd:integer\">0</TimeZones></Arg></SetUserInfo>",
        
        // Format 5: Dengan CardNo (kadang required)
        'cardno' => "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$namaEsc}</Name><Password xsi:type=\"xsd:string\"></Password><CardNo xsi:type=\"xsd:string\"></CardNo><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>",
        
        // Format 6: SSR_SetUserInfo (ZK short command)
        'ssr' => "<SSR_SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$namaEsc}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SSR_SetUserInfo>",
        
        // Format 7: SetUser (command lain)
        'setuser' => "<SetUser><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$namaEsc}</Name></Arg></SetUser>",
    ];

    foreach ($formats as $label => $soap) {
        $resp = send_soap_request($ip, $port, $soap);
        $hasSuccess = $resp && (stripos($resp, 'Success') !== false || stripos($resp, 'Successfully') !== false || strpos($resp, '<SetUserInfoResponse>') !== false || strpos($resp, '<SSR_SetUserInfoResponse>') !== false);
        $hasError = $resp && stripos($resp, 'Error') !== false;
        $results['send'][$label] = [
            'soap' => substr($soap, 0, 200),
            'response' => $resp ? substr($resp, 0, 500) : 'NULL',
            'success' => $hasSuccess,
            'error' => $hasError,
        ];
    }

    // ============================================================
    // STEP 2: GetAllUserInfo - cek apakah nama ke-save
    // ============================================================
    $soap_get = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
    $resp_get = send_soap_request($ip, $port, $soap_get);
    
    // Parse semua user
    $users = [];
    if ($resp_get) {
        preg_match_all('/<Row>(.*?)<\/Row>/s', $resp_get, $rows);
        foreach ($rows[1] as $row) {
            preg_match('/<PIN>(.*?)<\/PIN>/', $row, $mPin);
            preg_match('/<Name>(.*?)<\/Name>/', $row, $mName);
            $p = trim($mPin[1] ?? '');
            $n = trim($mName[1] ?? '');
            if ($p !== '') $users[$p] = $n;
        }
    }

    $cek_pin = $users[$pin] ?? null;
    $results['check'] = [
        'pin_searched' => $pin,
        'name_found' => $cek_pin,
        'has_name' => !empty($cek_pin),
        'total_users' => count($users),
    ];

    // Tampilkan 10 user terakhir buat debug
    $last_users = array_slice($users, -10, 10, true);
    $results['last_users'] = $last_users;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug SetUserInfo - X606S</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;padding:20px;margin:0}
        h1{color:#00d4ff;font-size:18px;margin-bottom:20px}
        .controls{background:#16213e;padding:16px;border-radius:8px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center}
        .controls label{color:#9ca3af;font-size:12px}
        .controls input,.controls select{background:#0f3460;border:1px solid #1a1a4e;color:#fff;padding:8px 12px;border-radius:6px;font-size:13px}
        .controls button{background:#00d4ff;color:#000;border:none;padding:8px 20px;border-radius:6px;font-weight:bold;cursor:pointer}
        .section{background:#16213e;border-radius:8px;padding:16px;margin-bottom:16px}
        .section h2{color:#00d4ff;font-size:14px;margin-bottom:12px;border-bottom:1px solid #1a1a4e;padding-bottom:8px}
        .row{display:flex;gap:12px;margin-bottom:8px;padding:8px;background:#0f3460;border-radius:6px;align-items:flex-start}
        .row .label{min-width:100px;color:#f59e0b;font-weight:bold;font-size:12px}
        .row .value{flex:1;font-size:12px;word-break:break-all}
        .ok{color:#22c55e}
        .err{color:#ef4444}
        .warn{color:#f59e0b}
        .dim{color:#6b7280}
        pre{background:#0a0a1a;padding:10px;border-radius:4px;font-size:11px;overflow-x:auto;margin:4px 0}
        table{width:100%;border-collapse:collapse;font-size:12px}
        th{background:#0f3460;padding:8px;text-align:left;color:#00d4ff}
        td{padding:6px 8px;border-bottom:1px solid #1a1a4e}
    </style>
</head>
<body>
<h1>Debug SetUserInfo - X606S Name Test</h1>

<div class="controls">
    <div>
        <label>PIN:</label><br>
        <input type="text" id="pin" value="<?= htmlspecialchars($pin) ?>">
    </div>
    <div>
        <label>Nama:</label><br>
        <input type="text" id="nama" value="<?= htmlspecialchars($nama) ?>">
    </div>
    <div>
        <label>Mesin:</label><br>
        <select id="device">
            <?php foreach ($devices as $d): ?>
            <option value="<?= $d['id_mesin'] ?>" <?= ($target && $d['id_mesin'] == $target['id_mesin']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['nama_mesin']) ?> (<?= $d['ip_mesin'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="align-self:flex-end">
        <button onclick="runTest()">Test Sekarang</button>
    </div>
</div>

<?php if (!empty($results)): ?>
<div class="section">
    <h2>Hasil Kirim ke Device</h2>
    <?php foreach ($results['send'] as $label => $r): ?>
    <div class="row">
        <div class="label"><?= $label ?></div>
        <div class="value">
            <span class="<?= $r['success'] ? 'ok' : ($r['error'] ? 'err' : 'dim') ?>">
                <?= $r['success'] ? 'SUCCESS' : ($r['error'] ? 'ERROR' : 'NO RESPONSE / EMPTY') ?>
            </span>
            <pre><?= htmlspecialchars($r['response']) ?></pre>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="section">
    <h2>Cek GetAllUserInfo - PIN <?= htmlspecialchars($pin) ?></h2>
    <div class="row">
        <div class="label">Result</div>
        <div class="value">
            <?php if ($results['check']['has_name']): ?>
                <span class="ok">NAMA DITEMUKAN: "<?= htmlspecialchars($results['check']['name_found']) ?>"</span>
            <?php else: ?>
                <span class="err">NAMA TIDAK DITEMUKAN untuk PIN <?= htmlspecialchars($pin) ?></span>
            <?php endif; ?>
            <br><span class="dim">Total users di mesin: <?= $results['check']['total_users'] ?></span>
        </div>
    </div>
</div>

<div class="section">
    <h2>10 User Terakhir di Device (buat debug)</h2>
    <table>
        <tr><th>PIN</th><th>Name</th><th>Status</th></tr>
        <?php foreach ($results['last_users'] as $p => $n): ?>
        <tr>
            <td><?= htmlspecialchars($p) ?></td>
            <td><?= htmlspecialchars($n) ?: '<span class="dim">(kosong)</span>' ?></td>
            <td><?= $p == $pin ? '<span class="ok">INI TARGET</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<script>
function runTest() {
    const pin = document.getElementById('pin').value;
    const nama = document.getElementById('nama').value;
    const device = document.getElementById('device').value;
    if (!pin || !nama) { Swal.fire('Error', 'PIN & Nama wajib diisi', 'error'); return; }
    window.location.href = `?pin=${encodeURIComponent(pin)}&nama=${encodeURIComponent(nama)}&device=${device}`;
}
</script>
</body>
</html>