<?php
/**
 * ONE-CLICK SETUP: Enable COM + Copy all files
 * Buka: http://localhost/absen/enable_com.php
 */

$log = [];
$all_ok = true;

// ============================================================
// STEP 1: Enable COM extension di php.ini
// ============================================================
$phpini = php_ini_loaded_file();

if (!$phpini) {
    $log[] = ['err', 'php.ini tidak ditemukan!'];
    $all_ok = false;
} else {
    $content = file_get_contents($phpini);
    if (preg_match('/^\s*extension\s*=\s*com_dotnet/mi', $content) && 
        !preg_match('/^;\s*extension\s*=\s*com_dotnet/mi', $content)) {
        $log[] = ['ok', 'COM extension sudah aktif'];
    } else {
        if (preg_match('/^;\s*extension\s*=\s*com_dotnet/mi', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $lineEnd = strpos($content, "\n", $pos);
            $oldLine = substr($content, $pos, $lineEnd - $pos);
            $newLine = ltrim($oldLine, "; \t");
            $content = substr_replace($content, $newLine, $pos, strlen($oldLine));
        } else {
            $content = rtrim($content) . "\n\n; Enable COM/DOTNET for ZK Attendance\nextension=com_dotnet\n";
        }
        if (file_put_contents($phpini, $content)) {
            $log[] = ['ok', 'php.ini di-edit: COM extension diaktifkan'];
        } else {
            $log[] = ['err', 'Gagal save php.ini'];
            $all_ok = false;
        }
    }
}

// ============================================================
// STEP 2: Auto-copy semua file yang dibutuhin
// ============================================================
$docRoot = $_SERVER['DOCUMENT_ROOT'];
$targetDir = $docRoot . '/absen';
$sourceDir = dirname(__FILE__); // lokasi enable_com.php = dalem /absen

// File-file yang wajib ada
$files = [
    'zk_access_helper.php' => "Helper COM buat akses att2000.mdb",
    'zk_sync_worker.php'   => "Background worker (opsional)",
    'device_sync_helper.php'=> "Helper sync ke mesin fingerprint",
    'enable_com.php'       => "Script ini (skip)",
];

// Source files dari development server (copy dari sini)
$sourceFiles = [
    'zk_access_helper.php' => __DIR__ . '/zk_access_helper.php',
    'zk_sync_worker.php'   => __DIR__ . '/zk_sync_worker.php',
    'device_sync_helper.php'=> __DIR__ . '/device_sync_helper.php',
];

foreach ($files as $fname => $desc) {
    if ($fname === 'enable_com.php') continue;
    
    $target = $targetDir . '/' . $fname;
    $source = $sourceFiles[$fname] ?? null;
    
    // Cek apakah file sudah ada & lengkap
    if (file_exists($target) && filesize($target) > 100) {
        // Cek apakah isinya udah bener (ada function zk_update_user)
        $fc = file_get_contents($target);
        if ($fname === 'zk_access_helper.php' && strpos($fc, 'function zk_update_user') !== false) {
            $log[] = ['ok', "$fname sudah ada & benar"];
            continue;
        } elseif ($fname !== 'zk_access_helper.php') {
            $log[] = ['ok', "$fname sudah ada"];
            continue;
        }
    }
    
    // Copy dari source kalau ada
    if ($source && file_exists($source)) {
        if (copy($source, $target)) {
            $log[] = ['ok', "$fname berhasil di-copy"];
        } else {
            $log[] = ['err', "$fname gagal di-copy! Copy manual dari server."];
            $all_ok = false;
        }
    } else {
        $log[] = ['warn', "$fname belum ada. Copy manual dari server development."];
        $all_ok = false;
    }
}

// ============================================================
// STEP 3: Restart Apache via batch (async)
// ============================================================
$apache_restarted = false;
if ($all_ok) {
    $batContent = '@echo off
timeout /t 2 /nobreak >nul
net stop "Apache2.4" >nul 2>&1
if errorlevel 1 (
    net stop "Apache" >nul 2>&1
    taskkill /f /im httpd.exe >nul 2>&1
)
timeout /t 3 /nobreak >nul
net start "Apache2.4" >nul 2>&1
if errorlevel 1 net start "Apache" >nul 2>&1
timeout /t 3 /nobreak >nul
del "%~f0" >nul 2>&1
';
    $batPath = sys_get_temp_dir() . '\\fix_com_restart.bat';
    file_put_contents($batPath, $batContent);
    try {
        $wsh = new COM("WScript.Shell");
        $wsh->Run('cmd /c "' . $batPath . '"', 0, false);
        $log[] = ['ok', 'Apache restart otomatis (~5 detik)'];
        $apache_restarted = true;
    } catch (Exception $e) {
        $log[] = ['warn', 'Restart otomatis gagal. Restart manual via XAMPP.'];
    }
}

// ============================================================
// STEP 4: Verifikasi COM
// ============================================================
$com_active = class_exists('COM');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup ZK Sync - One Click</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;display:flex;justify-content:center;padding:30px 16px;margin:0}
        .card{background:#fff;border-radius:16px;padding:28px 30px;max-width:500px;width:100%;box-shadow:0 8px 30px rgba(0,0,0,.07)}
        h2{color:#4f46e5;margin:0 0 6px;font-size:20px}
        .subtitle{color:#888;font-size:13px;margin-bottom:18px}
        .item{padding:10px 14px;margin:6px 0;border-radius:8px;font-size:13px;line-height:1.5}
        .ok{background:#f0fdf4;border-left:4px solid #22c55e;color:#166534}
        .err{background:#fef2f2;border-left:4px solid #ef4444;color:#991b1b}
        .warn{background:#fffbeb;border-left:4px solid #f59e0b;color:#92400e}
        code{background:#e5e7eb;padding:1px 5px;border-radius:3px;font-size:12px}
        .btn{display:inline-block;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;margin-top:14px;font-size:14px;border:none;cursor:pointer}
        .btn-primary{background:#4f46e5;color:#fff}.btn-primary:hover{background:#4338ca}
        .btn-success{background:#22c55e;color:#fff}
        .note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;margin-top:14px;font-size:13px;color:#1e40af;line-height:1.6}
        .done{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;margin-top:14px;font-size:13px;color:#166534}
    </style>
</head>
<body>
<div class="card">
    <h2>Setup ZK Attendance Sync</h2>
    <div class="subtitle">One-click: Aktifkan COM + Setup files + Restart Apache</div>
    
    <?php foreach ($log as $item): ?>
    <div class="item <?= $item[0] ?>"><?= $item[1] ?></div>
    <?php endforeach; ?>

    <?php if ($com_active): ?>
    <div class="done">
        <strong>COM sudah aktif!</strong> Semua siap.
    </div>
    <?php endif; ?>

    <?php if ($all_ok && $apache_restarted): ?>
    <div class="note">
        Tunggu ~10 detik biar Apache restart, lalu klik tombol di bawah.
    </div>
    <button class="btn btn-success" onclick="this.innerText='Loading...';location.href='?page=karyawan'">
        Test Tambah Karyawan
    </button>
    <?php elseif (!$all_ok): ?>
    <div class="note" style="border-color:#fca5a5;background:#fef2f2;color:#991b1b">
        Ada file yang belum ada. Copy manual dari server development ke <code>C:\xampp\htdocs\absen\</code>
    </div>
    <?php endif; ?>
</div>

<?php if ($com_active && $apache_restarted): ?>
<script>
setTimeout(()=>{ Swal.fire({icon:'success',title:'Siap!',text:'COM aktif & semua file sudah ada. Silakan test tambah karyawan.',confirmButtonColor:'#4f46e5'}) }, 8000);
</script>
<?php endif; ?>
</body>
</html>