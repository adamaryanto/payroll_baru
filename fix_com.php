<?php
/**
 * Auto-fix: Enable PHP COM extension (com_dotnet)
 * Buka dari browser: http://localhost/absen/fix_com.php
 * Script akan edit php.ini otomatis + kasih tau restart Apache
 */

$phpini = php_ini_loaded_file();
$found = false;
$lines = [];
$result = ['status' => '', 'message' => '', 'phpini' => $phpini];

if (!$phpini) {
    $result['status'] = 'error';
    $result['message'] = 'Tidak bisa temukan php.ini yang aktif.';
} else {
    $content = file_get_contents($phpini);
    $lines = explode("\n", $content);
    
    // Cek apakah sudah aktif
    $already_active = false;
    $found_line = -1;
    foreach ($lines as $i => $line) {
        $trim = strtolower(trim($line));
        if (preg_match('/^;?\s*extension\s*=\s*com_dotnet/', $trim)) {
            $found_line = $i;
            if (strpos($line, ';') === false || preg_match('/^\s*extension\s*=/i', $trim)) {
                $already_active = true;
            }
            break;
        }
    }
    
    if ($already_active) {
        $result['status'] = 'ok';
        $result['message'] = 'COM extension SUDAH aktif di php.ini.';
    } elseif ($found_line >= 0) {
        // Uncomment baris
        $lines[$found_line] = preg_replace('/^(\s*);(\s*extension\s*=\s*com_dotnet)/i', '$1$2', $lines[$found_line]);
        if (strpos($lines[$found_line], ';') === 0) {
            // Fallback: hapus ; di awal
            $lines[$found_line] = ltrim($lines[$found_line]);
            $lines[$found_line] = preg_replace('/^;\s*/', '', $lines[$found_line]);
        }
        file_put_contents($phpini, implode("\n", $lines));
        $result['status'] = 'fixed';
        $result['message'] = "COM extension berhasil diaktifkan di php.ini!\nBaris ke-" . ($found_line + 1) . ": " . trim($lines[$found_line]);
    } else {
        // Tambah baris extension=com_dotnet
        $lines[] = '';
        $lines[] = '; Enable COM/DOTNET extension for ZK Attendance sync';
        $lines[] = 'extension=com_dotnet';
        file_put_contents($phpini, implode("\n", $lines));
        $result['status'] = 'added';
        $result['message'] = "extension=com_dotnet ditambahkan ke php.ini!";
    }
}

// Cek apakah COM sudah bisa dipakai
$com_ok = class_exists('COM');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix COM Extension - ZK Sync</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial; background: #f5f5f5; display: flex; justify-content: center; padding-top: 50px; }
        .card { background: white; border-radius: 12px; padding: 30px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h2 { color: #4f46e5; margin-top: 0; }
        .step { background: #f0f0ff; border-left: 4px solid #4f46e5; padding: 12px 16px; margin: 10px 0; border-radius: 0 8px 8px 0; }
        .ok { border-left-color: #22c55e; background: #f0fdf4; }
        .warn { border-left-color: #f59e0b; background: #fffbeb; }
        .err { border-left-color: #ef4444; background: #fef2f2; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 15px; }
        .btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Fix COM Extension untuk ZK Sync</h2>
        
        <?php if ($com_ok): ?>
        <div class="step ok">
            <strong>Status: COM sudah aktif!</strong><br>
            Class COM bisa dipakai. Silakan test tambah karyawan lagi.
        </div>
        <?php else: ?>
        <div class="step err">
            <strong>Status: COM masih nonaktif</strong><br>
            Setelah edit php.ini, kamu HARUS <strong>Restart Apache</strong> di XAMPP Control Panel.
        </div>
        <?php endif; ?>

        <div class="step <?= $result['status'] === 'ok' ? 'ok' : ($result['status'] === 'error' ? 'err' : 'warn') ?>">
            <strong>php.ini:</strong> <code><?= htmlspecialchars($phpini) ?></code><br>
            <?= nl2br(htmlspecialchars($result['message'])) ?>
        </div>

        <h3 style="margin-top:20px;">Selanjutnya:</h3>
        <div class="step">
            <strong>1.</strong> Buka <strong>XAMPP Control Panel</strong><br>
            <strong>2.</strong> Klik <strong>Stop</strong> di Apache<br>
            <strong>3.</strong> Klik <strong>Start</strong> di Apache<br>
            <strong>4.</strong> Buka ulang <code>http://localhost/absen/page=karyawan&aksi=tambah</code>
        </div>

        <?php if (!$com_ok): ?>
        <a href="" class="btn" onclick="location.reload()">Refresh setelah Restart Apache</a>
        <?php else: ?>
        <a href="?page=karyawan" class="btn">Kembali ke Karyawan</a>
        <?php endif; ?>
    </div>
</body>
</html>