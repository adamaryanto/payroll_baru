<?php
/**
 * MASTER SETTING: Toleransi Jam Telat & Pulang Cepat
 * Halaman: page=master_setting
 */

// Table untuk simpan parameter
$koneksi->query("CREATE TABLE IF NOT EXISTS ms_parameter (
    id_param INT AUTO_INCREMENT PRIMARY KEY,
    param_key VARCHAR(100) UNIQUE NOT NULL,
    param_value VARCHAR(255) NOT NULL,
    keterangan VARCHAR(255) DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Default values kalau belum ada
$defaults = [
    'toleransi_telat_masuk' => '10',
    'toleransi_pulang_cepat' => '10',
    'jam_kerja_per_hari' => '8',
    'jam_kerja_per_minggu' => '40',
    'jam_lembur_mulai' => '17:00',
    'toleransi_istirahat' => '60'
];

foreach ($defaults as $key => $val) {
    $cek = $koneksi->query("SELECT param_key FROM ms_parameter WHERE param_key='$key'");
    if ($cek->num_rows == 0) {
        $koneksi->query("INSERT INTO ms_parameter (param_key, param_value, keterangan) VALUES ('$key', '$val', 'Default value')");
    }
}

// Handle update
if (isset($_POST['simpan'])) {
    foreach ($_POST as $key => $val) {
        if (strpos($key, 'param_') === 0) {
            $paramKey = substr($key, 6);
            $valEsc = $koneksi->real_escape_string(trim($val));
            $koneksi->query("UPDATE ms_parameter SET param_value='$valEsc' WHERE param_key='$paramKey'");
        }
    }
    echo "<script>alert('Setting berhasil disimpan!');</script>";
}

// Ambil semua parameter
$params = [];
$q = $koneksi->query("SELECT * FROM ms_parameter ORDER BY param_key");
while ($r = $q->fetch_assoc()) $params[$r['param_key']] = $r;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Master Setting - Toleransi Jam</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; }
        .container { max-width: 800px; margin: 30px auto; }
        .card { background: white; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .header { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; padding: 24px 30px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; opacity: 0.9; font-size: 14px; }
        .body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 14px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 15px; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .help-text { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .unit { color: #4f46e5; font-weight: 600; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .btn { background: #4f46e5; color: white; border: none; padding: 14px 28px; border-radius: 10px; font-weight: 600; font-size: 15px; cursor: pointer; transition: all 0.2s; }
        .btn:hover { background: #4338ca; transform: translateY(-1px); }
        .btn-block { display: block; width: 100%; }
        .footer { border-top: 1px solid #e5e7eb; padding: 20px 30px; background: #f9fafb; text-align: right; }
        .info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 16px; margin-bottom: 24px; }
        .info-box h3 { color: #1e40af; margin-top: 0; font-size: 16px; }
        .info-box p { margin: 8px 0; color: #374151; font-size: 14px; }
        .param-row { display: flex; gap: 10px; align-items: center; }
        .param-row input { flex: 1; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <h1><i class="fas fa-cog"></i> Master Setting - Toleransi Jam</h1>
            <p>Atur parameter perhitungan absensi & penggajian</p>
        </div>
        
        <div class="body">
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Cara kerja toleransi:</h3>
                <p><strong>Toleransi Telat Masuk:</strong> Karyawan datang 08:10 (jadwal 08:00) → <span class="unit">tidak dihitung telat</span> selama ≤ toleransi.</p>
                <p><strong>Toleransi Pulang Cepat:</strong> Karyawan pulang 16:50 (jadwal 17:00) → <span class="unit">tidak dihitung pulang cepat</span> selama ≤ toleransi.</p>
                <p>Setelah melewati toleransi, selisih menit akan dihitung sebagai telat/pulang cepat.</p>
            </div>
            
            <form method="POST">
                <div class="grid">
                    <!-- Toleransi Telat -->
                    <div class="form-group">
                        <label>Toleransi Telat Masuk <span class="unit">(menit)</span></label>
                        <div class="param-row">
                            <input type="number" name="param_toleransi_telat_masuk" 
                                   value="<?= htmlspecialchars($params['toleransi_telat_masuk']['param_value'] ?? '10') ?>"
                                   class="form-control" min="0" max="120" step="1">
                            <span class="unit">menit</span>
                        </div>
                        <div class="help-text">Contoh: 10 = karyawan datang 08:10 (jadwal 08:00) tidak dihitung telat</div>
                    </div>
                    
                    <!-- Toleransi Pulang Cepat -->
                    <div class="form-group">
                        <label>Toleransi Pulang Cepat <span class="unit">(menit)</span></label>
                        <div class="param-row">
                            <input type="number" name="param_toleransi_pulang_cepat" 
                                   value="<?= htmlspecialchars($params['toleransi_pulang_cepat']['param_value'] ?? '10') ?>"
                                   class="form-control" min="0" max="120" step="1">
                            <span class="unit">menit</span>
                        </div>
                        <div class="help-text">Contoh: 10 = karyawan pulang 16:50 (jadwal 17:00) tidak dihitung pulang cepat</div>
                    </div>
                    
                    <!-- Jam Kerja Harian -->
                    <div class="form-group">
                        <label>Jam Kerja per Hari <span class="unit">(jam)</span></label>
                        <div class="param-row">
                            <input type="number" name="param_jam_kerja_per_hari" 
                                   value="<?= htmlspecialchars($params['jam_kerja_per_hari']['param_value'] ?? '8') ?>"
                                   class="form-control" min="1" max="24" step="0.5">
                            <span class="unit">jam</span>
                        </div>
                        <div class="help-text">Default 8 jam/hari untuk hitung kehadiran</div>
                    </div>
                    
                    <!-- Jam Kerja Mingguan -->
                    <div class="form-group">
                        <label>Jam Kerja per Minggu <span class="unit">(jam)</span></label>
                        <div class="param-row">
                            <input type="number" name="param_jam_kerja_per_minggu" 
                                   value="<?= htmlspecialchars($params['jam_kerja_per_minggu']['param_value'] ?? '40') ?>"
                                   class="form-control" min="1" max="168" step="1">
                            <span class="unit">jam</span>
                        </div>
                        <div class="help-text">Default 40 jam/minggu (5 hari × 8 jam)</div>
                    </div>
                    
                    <!-- Jam Mulai Lembur -->
                    <div class="form-group">
                        <label>Jam Mulai Hitung Lembur <span class="unit">(HH:MM)</span></label>
                        <div class="param-row">
                            <input type="time" name="param_jam_lembur_mulai" 
                                   value="<?= htmlspecialchars($params['jam_lembur_mulai']['param_value'] ?? '17:00') ?>"
                                   class="form-control">
                        </div>
                        <div class="help-text">Setelah jam ini dihitung sebagai lembur (normal shift)</div>
                    </div>
                    
                    <!-- Toleransi Istirahat -->
                    <div class="form-group">
                        <label>Toleransi Istirahat <span class="unit">(menit)</span></label>
                        <div class="param-row">
                            <input type="number" name="param_toleransi_istirahat" 
                                   value="<?= htmlspecialchars($params['toleransi_istirahat']['param_value'] ?? '60') ?>"
                                   class="form-control" min="0" max="180" step="1">
                            <span class="unit">menit</span>
                        </div>
                        <div class="help-text">Durasi istirahat yang diperbolehkan tanpa potongan</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="simpan" class="btn btn-block">
                        <i class="fas fa-save"></i> Simpan Semua Setting
                    </button>
                </div>
            </form>
        </div>
        
        <div class="footer">
            <a href="?page=home" class="btn" style="background:#6b7280;">
                <i class="fas fa-home"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-time preview
    const telatInput = document.querySelector('input[name="param_toleransi_telat_masuk"]');
    const cepatInput = document.querySelector('input[name="param_toleransi_pulang_cepat"]');
    
    function updatePreview() {
        const telatVal = telatInput.value || 10;
        const cepatVal = cepatInput.value || 10;
        
        // Update help text dengan contoh real-time
        const telatHelp = telatInput.closest('.form-group').querySelector('.help-text');
        const cepatHelp = cepatInput.closest('.form-group').querySelector('.help-text');
        
        telatHelp.innerHTML = `Contoh: ${telatVal} = karyawan datang 08:${telatVal.toString().padStart(2,'0')} (jadwal 08:00) tidak dihitung telat`;
        cepatHelp.innerHTML = `Contoh: ${cepatVal} = karyawan pulang 16:${(60-cepatVal).toString().padStart(2,'0')} (jadwal 17:00) tidak dihitung pulang cepat`;
    }
    
    telatInput.addEventListener('input', updatePreview);
    cepatInput.addEventListener('input', updatePreview);
    updatePreview();
});
</script>
</body>
</html>