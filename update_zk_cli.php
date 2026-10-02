<?php
/**
 * update_zk_cli.php
 * Jalankan via CLI: php update_zk_cli.php
 * Atau otomatis lewat setup_com.bat
 * 
 * Update att2000.mdb dari MySQL (semua karyawan aktif)
 */

echo "=== ZK Sync - Update att2000.mdb ===\n\n";

// Cek COM
if (PHP_OS_FAMILY !== 'Windows') {
    echo "[ERROR] Script ini cuma bisa jalan di Windows!\n";
    exit(1);
}
if (!class_exists('COM')) {
    echo "[ERROR] COM extension belum aktif!\n";
    echo "Jalankan setup_com.bat dulu, atau aktifkan extension=com_dotnet di php.ini\n";
    exit(1);
}

echo "[OK] COM extension aktif\n";

// Koneksi MySQL
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'db_hr_new';

$connMySQL = @mysqli_connect($host, $user, $pass, $dbname);
if (!$connMySQL) {
    echo "[ERROR] MySQL gagal connect: " . mysqli_connect_error() . "\n";
    echo "Pastikan Laragon/XAMPP MySQL jalan.\n";
    exit(1);
}
echo "[OK] MySQL connected\n";

// Cari att2000.mdb
$candidates = [
    "C:\\Program Files (x86)\\att2000.mdb",
    "C:\\Program Files\\att2000.mdb",
    "C:\\Att2000.mdb",
    "C:\\ZKAccess\\att2000.mdb",
    "C:\\Program Files\\ZKTeco\\att2000.mdb",
    "C:\\Program Files (x86)\\ZKTeco\\att2000.mdb",
];
$mdbPath = null;
foreach ($candidates as $p) {
    if (file_exists($p)) { $mdbPath = $p; break; }
}
if (!$mdbPath) {
    echo "[ERROR] att2000.mdb tidak ditemukan!\n";
    echo "Candidat path yang dicari:\n";
    foreach ($candidates as $p) echo "  - $p\n";
    exit(1);
}
echo "[OK] att2000.mdb: $mdbPath\n";

// Koneksi Access MDB
try {
    $connMDB = new COM("ADODB.Connection");
    $connMDB->CommandTimeout = 10;
    try {
        $connMDB->Open("Provider=Microsoft.Jet.OLEDB.4.0;Data Source={$mdbPath};");
    } catch (Exception $e) {
        $connMDB->Open("Provider=Microsoft.ACE.OLEDB.12.0;Data Source={$mdbPath};");
    }
} catch (Exception $e) {
    echo "[ERROR] Access MDB gagal connect: " . $e->getMessage() . "\n";
    echo "Pastikan att2000.mdb tidak sedang dibuka ZK Attendance.\n";
    exit(1);
}
echo "[OK] Access MDB connected\n";

// Detek kolom
$foundCol = 'USERID';
$nameCol = 'Name';
try {
    $cat = new COM("ADOX.Catalog");
    $cat->ActiveConnection = $connMDB;
    $cols = [];
    foreach ($cat->Tables("USERINFO")->Columns as $c) {
        $cols[strtoupper($c->Name)] = 1;
    }
    if (isset($cols['BADGENUMBER'])) $foundCol = 'Badgenumber';
    if (isset($cols['USERNAME'])) $nameCol = 'USERNAME';
} catch (Exception $e) {
    try { $connMDB->Execute("SELECT Badgenumber FROM USERINFO WHERE 1=0"); $foundCol = 'Badgenumber'; } catch (Exception $ex) {}
}
echo "[OK] Kolom: $foundCol / $nameCol\n\n";

// Ambil semua karyawan dari MySQL
$result = $connMySQL->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen IS NOT NULL AND no_absen != ''");

if (!$result || $result->num_rows == 0) {
    echo "[WARNING] Tidak ada karyawan aktif di MySQL!\n";
    exit(0);
}

$count = 0;
$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = 0;

echo "Processing...\n";

while ($row = $result->fetch_assoc()) {
    $pin = trim($row['no_absen']);
    $nama = trim($row['nama_karyawan']);
    
    if ($pin === '') continue;
    
    $pinEsc = str_replace("'", "''", $pin);
    $namaEsc = str_replace("'", "''", $nama);
    
    // Cek apakah sudah ada
    $rsCheck = null;
    try {
        $rsCheck = $connMDB->Execute("SELECT * FROM USERINFO WHERE {$foundCol}='{$pinEsc}'");
    } catch (Exception $e) {
        $errors++;
        continue;
    }
    
    if ($rsCheck && !$rsCheck->EOF) {
        // Sudah ada - cek nama
        $currentName = '';
        try { $currentName = trim((string)$rsCheck->Fields($nameCol)->Value); } catch (Exception $e) {}
        
        if ($currentName !== $nama) {
            // Update nama
            try {
                $connMDB->Execute("UPDATE USERINFO SET {$nameCol}='{$namaEsc}' WHERE {$foundCol}='{$pinEsc}'");
                $updated++;
            } catch (Exception $e) {
                $errors++;
            }
        } else {
            $skipped++;
        }
    } else {
        // Insert baru
        try {
            $connMDB->Execute("INSERT INTO USERINFO ({$foundCol}, {$nameCol}) VALUES ('{$pinEsc}', '{$namaEsc}')");
            $inserted++;
        } catch (Exception $e) {
            // Fallback
            try {
                $connMDB->Execute("INSERT INTO USERINFO (USERID, Name) VALUES ('{$pinEsc}', '{$namaEsc}')");
                $inserted++;
            } catch (Exception $ex) {
                $errors++;
            }
        }
    }
    
    $count++;
    
    // Progress
    if ($count % 50 == 0) {
        echo "  Processed: $count | Insert: $inserted | Update: $updated | Skip: $skipped | Error: $errors\n";
    }
}

echo "\n=== HASIL ===\n";
echo "Total dari MySQL: $count\n";
echo "Baru di-insert: $inserted\n";
echo "Di-update (nama berubah): $updated\n";
echo "Sudah sama (skip): $skipped\n";
echo "Error: $errors\n";
echo "\natt2000.mdb: $mdbPath\n";
echo "\nSelesai! Buka ZK Attendance Management\n";
echo "klik Browse Users in device\n";
echo "nama harusnya sudah muncul di kiri!\n";

$connMDB->Close();
$connMySQL->close();