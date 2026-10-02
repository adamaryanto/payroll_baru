<?php
/**
 * SETUP PAGE - Client buka ini
 * http://119.18.154.118:8080/absen/setup_com.php
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup ZK Sync - Aktifkan COM</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .card{background:#fff;border-radius:20px;padding:40px;max-width:520px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
        .icon{width:80px;height:80px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px}
        h1{text-align:center;color:#1e1b4b;font-size:22px;margin-bottom:8px}
        .sub{text-align:center;color:#6b7280;font-size:14px;margin-bottom:28px}
        .step{display:flex;gap:14px;margin:14px 0;padding:14px 16px;background:#f9fafb;border-radius:12px;align-items:flex-start}
        .num{width:32px;height:32px;background:#4f46e5;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0}
        .step-text{font-size:14px;color:#374151;line-height:1.6}
        .step-text strong{color:#1e1b4b}
        .step-text code{background:#e5e7eb;padding:2px 8px;border-radius:4px;font-size:12px;word-break:break-all}
        .btn{display:block;width:100%;padding:16px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-align:center;border-radius:12px;text-decoration:none;font-weight:700;font-size:16px;margin-top:24px;border:none;cursor:pointer;transition:all .2s}
        .btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(79,70,229,.4)}
        .note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;margin-top:16px;font-size:12px;color:#1e40af;line-height:1.5}
        .success-box{display:none;background:#f0fdf4;border:2px solid #22c55e;border-radius:12px;padding:20px;margin-top:20px;text-align:center}
        .success-box h3{color:#166534;margin-bottom:8px}
        .success-box a{display:inline-block;margin-top:10px;padding:10px 24px;background:#22c55e;color:#fff;border-radius:8px;text-decoration:none;font-weight:600}
    </style>
</head>
<body>
<div class="card">
    <div class="icon">🔧</div>
    <h1>Setup ZK Attendance Sync</h1>
    <p class="sub">Aktifkan COM extension biar nama muncul di ZK Attendance</p>

    <div class="step">
        <div class="num">1</div>
        <div class="step-text">
            <strong>Klik tombol di bawah</strong> untuk download file setup<br>
            <small style="color:#9ca3af">File: <code>setup_com.bat</code> (~1KB)</small>
        </div>
    </div>

    <div class="step">
        <div class="num">2</div>
        <div class="step-text">
            <strong>Buka folder Downloads</strong>, dobel-klik file <code>setup_com.bat</code><br>
            <small style="color:#9ca3af">Kalau muncul warning, klik "Run anyway" atau "More info" → "Run anyway"</small>
        </div>
    </div>

    <div class="step">
        <div class="num">3</div>
        <div class="step-text">
            <strong>Tunggu ~10 detik</strong> sampai muncul tulisan <strong style="color:#22c55e">[OK] SETUP SELESAI!</strong><br>
            <small style="color:#9ca3af">Script otomatis edit php.ini + restart Apache</small>
        </div>
    </div>

    <div class="step">
        <div class="num">4</div>
        <div class="step-text">
            <strong>Test tambah karyawan</strong> → buka halaman karyawan, tambah 1 orang baru<br>
            <small style="color:#9ca3af">Cek ZK Attendance: nama harusnya sudah di <strong>kiri</strong></small>
        </div>
    </div>

    <a href="setup_com.php" class="btn" id="downloadBtn">
        Download Setup File
    </a>

    <div class="note">
        <strong>Apa yang dilakukan script ini?</strong><br>
        • Edit <code>php.ini</code> → aktifkan <code>extension=com_dotnet</code><br>
        • Restart Apache otomatis<br>
        • Backup php.ini asli (*.bak) kalau ada error<br>
        • Script otomatis terhapus setelah selesai
    </div>

    <div class="success-box" id="successBox">
        <h3>Setup Selesai!</h3>
        <p>Sekarang test tambah karyawan</p>
        <a href="index.php?page=karyawan">Buka Halaman Karyawan</a>
    </div>
</div>

<script>
document.getElementById('downloadBtn').addEventListener('click', function() {
    setTimeout(() => {
        Swal.fire({
            icon: 'question',
            title: 'Sudah dijalankan?',
            html: 'Kalau sudah dobel-klik <b>setup_com.bat</b> dan muncul <b style="color:green">[OK] SETUP SELESAI</b>, klik Ya',
            showCancelButton: true,
            confirmButtonColor: '#22c55e',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, sudah selesai!',
            cancelButtonText: 'Belum'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('successBox').style.display = 'block';
                document.getElementById('downloadBtn').style.display = 'none';
            }
        });
    }, 3000);
});
</script>
</body>
</html>