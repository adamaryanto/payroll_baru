<?php
/**
 * ZK Access.mdb Sync Worker
 * Run via Windows Task Scheduler every 30s
 * Processes zk_sync_queue table and updates att2000.mdb
 */
require_once __DIR__ . '/koneksi.php';

if (PHP_OS_FAMILY !== 'Windows' || !class_exists('COM')) {
    exit(0);
}

$mdbPath = null;
$candidates = [
    "C:\\Program Files (x86)\\att2000.mdb",
    "C:\\Program Files\\att2000.mdb",
    "C:\\Att2000.mdb",
    "C:\\ZKAccess\\att2000.mdb",
    "C:\\Program Files\\ZKTeco\\att2000.mdb",
    "C:\\Program Files (x86)\\ZKTeco\\att2000.mdb",
];
foreach ($candidates as $p) {
    if (@file_exists($p)) { $mdbPath = $p; break; }
}
if (!$mdbPath) {
    error_log("[ZK Sync Worker] att2000.mdb not found");
    exit(1);
}

// Ensure table exists
$koneksi->query("CREATE TABLE IF NOT EXISTS zk_sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pin VARCHAR(50) NOT NULL,
    nama VARCHAR(255) NOT NULL,
    action VARCHAR(20) NOT NULL DEFAULT 'upsert',
    processed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    processed_at DATETIME DEFAULT NULL
)");

$q = $koneksi->query("SELECT * FROM zk_sync_queue WHERE processed=0 ORDER BY created_at LIMIT 50");
if (!$q || $q->num_rows === 0) exit(0);

try {
    $connStr = "Provider=Microsoft.Jet.OLEDB.4.0;Data Source={$mdbPath};";
    $conn = new COM("ADODB.Connection");
    $conn->CommandTimeout = 5;
    try { $conn->Open($mdbPath); } catch (Exception $e) {
        $conn->Open("Provider=Microsoft.ACE.OLEDB.12.0;Data Source={$mdbPath};");
    }
} catch (Exception $e) {
    error_log("[ZK Sync Worker] Cannot open MDB: " . $e->getMessage());
    exit(1);
}

// Detect columns
$foundCol = 'USERID';
$nameCol = 'Name';
try {
    $cat = new COM("ADOX.Catalog");
    $cat->ActiveConnection = $conn;
    $cols = [];
    foreach ($cat->Tables("USERINFO")->Columns as $c) $cols[strtoupper($c->Name)] = 1;
    if (isset($cols['BADGENUMBER'])) $foundCol = 'Badgenumber';
    if (isset($cols['USERNAME'])) $nameCol = 'USERNAME';
} catch (Exception $e) {
    try { $conn->Execute("SELECT Badgenumber FROM USERINFO WHERE 1=0"); $foundCol = 'Badgenumber'; } catch (Exception $ex) {}
}

$processed = 0;
$errors = 0;

while ($row = $q->fetch_assoc()) {
    $id = intval($row['id']);
    $pin = str_replace("'", "''", $row['pin']);
    $nama = str_replace("'", "''", $row['nama']);
    $action = $row['action'];

    try {
        if ($action === 'delete') {
            $conn->Execute("DELETE FROM USERINFO WHERE {$foundCol}='{$pin}'");
        } else {
            $rs = $conn->Execute("SELECT * FROM USERINFO WHERE {$foundCol}='{$pin}'");
            if ($rs && !$rs->EOF) {
                $conn->Execute("UPDATE USERINFO SET {$nameCol}='{$nama}' WHERE {$foundCol}='{$pin}'");
            } else {
                $conn->Execute("INSERT INTO USERINFO ({$foundCol}, {$nameCol}) VALUES ('{$pin}', '{$nama}')");
            }
        }
        $koneksi->query("UPDATE zk_sync_queue SET processed=1, processed_at=NOW() WHERE id={$id}");
        $processed++;
    } catch (Exception $e) {
        error_log("[ZK Sync Worker] Error pin={$row['pin']}: " . $e->getMessage());
        $koneksi->query("UPDATE zk_sync_queue SET processed=1, processed_at=NOW() WHERE id={$id}");
        $errors++;
    }
}

$conn->Close();
error_log("[ZK Sync Worker] Done: {$processed} processed, {$errors} errors");
exit(0);
