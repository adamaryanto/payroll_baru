<?php
/**
 * local_listener.php
 * Jalankan di PC client sebagai background service
 * Denger request dari web server, update att2000.mdb lokal
 * 
 * Cara jalanin:
 * 1. Copy file ini ke C:\xampp\htdocs\absen\
 * 2. Buka browser: http://localhost/absen/local_listener.php?action=start
 * 3. Biarkan tab terbuka (jalan di background)
 * 
 * Web server POST ke http://PC_CLIENT_IP:8080/absen/local_listener.php
 */

// Mode: start (background) | sync (dipanggil dari web)
$action = $_GET['action'] ?? '';

if ($action === 'start') {
    // Start background listener (simple HTTP server)
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>ZK Local Listener</title>
        <style>
            body { font-family: monospace; background: #0a0a0a; color: #0f0; padding: 20px; }
            #log { height: 300px; overflow-y: auto; border: 1px solid #333; padding: 10px; background: #000; }
            .time { color: #888; }
            .msg { color: #0f0; }
            .err { color: #f00; }
        </style>
    </head>
    <body>
        <h1>ZK Local Listener - Running</h1>
        <p>Jangan tutup tab ini. Listener jalan di port 8080.</p>
        <div id="log"></div>
        <script>
        let logDiv = document.getElementById('log');
        function log(msg, type='msg') {
            let time = new Date().toLocaleTimeString();
            logDiv.innerHTML += `<div><span class="time">[${time}]</span> <span class="${type}">${msg}</span></div>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        // Polling untuk cek request (simulasi)
        setInterval(() => {
            fetch('?action=poll').then(r=>r.text()).then(t=>{
                if(t && t!=='idle') log('Received: ' + t);
            });
        }, 5000);
        log('Listener started on port 8080');
        </script>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'poll') {
    // Simulasi poll (nanti ganti dengan websocket/real HTTP server)
    echo "idle";
    exit;
}

// Handle sync request dari web server
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    $nama = $_POST['nama'] ?? '';
    $cmd = $_POST['cmd'] ?? ''; // add | update | delete
    
    if (empty($pin)) {
        http_response_code(400);
        echo json_encode(['error' => 'PIN required']);
        exit;
    }
    
    // Update att2000.mdb lokal
    $result = local_update_att2000($pin, $nama, $cmd);
    echo json_encode($result);
    exit;
}

// Default: info page
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZK Local Listener</title>
</head>
<body>
    <h1>ZK Local Listener</h1>
    <p>Background service untuk sync ke att2000.mdb lokal</p>
    
    <h2>Cara pakai:</h2>
    <ol>
        <li>Jalankan sebagai service: <a href="?action=start">Klik di sini</a> (biarkan tab terbuka)</li>
        <li>Web server POST ke endpoint ini dengan data karyawan</li>
    </ol>
    
    <h2>Test endpoint:</h2>
    <form method="POST">
        PIN: <input type="text" name="pin" value="999"><br>
        Nama: <input type="text" name="nama" value="TEST LOCAL"><br>
        Cmd: 
        <select name="cmd">
            <option value="add">Add</option>
            <option value="update">Update</option>
            <option value="delete">Delete</option>
        </select><br>
        <button type="submit">Test Sync</button>
    </form>
</body>
</html>

<?php
function local_update_att2000($pin, $nama, $cmd) {
    if (PHP_OS_FAMILY !== 'Windows' || !class_exists('COM')) {
        return ['error' => 'COM not available'];
    }
    
    $candidates = [
        "C:\\Program Files (x86)\\att2000.mdb",
        "C:\\Program Files\\att2000.mdb",
        "C:\\Att2000.mdb",
    ];
    $mdbPath = null;
    foreach ($candidates as $p) {
        if (@file_exists($p)) { $mdbPath = $p; break; }
    }
    if (!$mdbPath) return ['error' => 'att2000.mdb not found'];
    
    try {
        $conn = new COM("ADODB.Connection");
        try {
            $conn->Open("Provider=Microsoft.Jet.OLEDB.4.0;Data Source={$mdbPath};");
        } catch (Exception $e) {
            $conn->Open("Provider=Microsoft.ACE.OLEDB.12.0;Data Source={$mdbPath};");
        }
        
        // Deteksi kolom
        $foundCol = 'USERID';
        $nameCol = 'Name';
        try {
            $cat = new COM("ADOX.Catalog");
            $cat->ActiveConnection = $conn;
            foreach ($cat->Tables("USERINFO")->Columns as $c) {
                $n = strtoupper($c->Name);
                if ($n === 'BADGENUMBER') $foundCol = 'Badgenumber';
                if ($n === 'USERNAME') $nameCol = 'USERNAME';
            }
        } catch (Exception $e) {}
        
        $pinEsc = str_replace("'", "''", $pin);
        $namaEsc = str_replace("'", "''", $nama);
        
        if ($cmd === 'delete') {
            $conn->Execute("DELETE FROM USERINFO WHERE {$foundCol}='{$pinEsc}'");
            return ['success' => true, 'action' => 'delete'];
        }
        
        // Cek apakah sudah ada
        $rs = $conn->Execute("SELECT * FROM USERINFO WHERE {$foundCol}='{$pinEsc}'");
        if ($rs && !$rs->EOF) {
            // Update
            $conn->Execute("UPDATE USERINFO SET {$nameCol}='{$namaEsc}' WHERE {$foundCol}='{$pinEsc}'");
            return ['success' => true, 'action' => 'update'];
        } else {
            // Insert
            $conn->Execute("INSERT INTO USERINFO ({$foundCol}, {$nameCol}) VALUES ('{$pinEsc}', '{$namaEsc}')");
            return ['success' => true, 'action' => 'insert'];
        }
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}
?>