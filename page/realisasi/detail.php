<?php
// Auto-create lembur_jam column if not exists
$checkLemburJamCol = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'lembur_jam'");
if ($checkLemburJamCol->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_detail ADD COLUMN lembur_jam DECIMAL(10,2) DEFAULT 0.00");
}
// Kolom pengganti (tukar karyawan: nama tetap, tercatat "Digantikan oleh ...")
$checkGantiCol = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_pengganti'");
if ($checkGantiCol->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_detail ADD COLUMN id_pengganti INT NULL DEFAULT NULL");
}
// Kolom jabatan per baris (Posisi di Detail): 0 = ikut jabatan master karyawan.
// Dibuat di sini supaya Detail lama tanpa kolom ini tetap jalan.
$checkJabCol = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_jabatan'");
if ($checkJabCol && $checkJabCol->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_detail ADD COLUMN id_jabatan INT NOT NULL DEFAULT 0");
}

// Helper waktu + toleransi terpusat (SAMA kayak tarik data & kelola)
require_once __DIR__ . '/sync_lib.php';
$toleransi_d = function_exists('load_toleransi') ? load_toleransi($koneksi) : ['masuk' => 10, 'pulang' => 5, 'ist_keluar' => 5, 'ist_masuk' => 10];

// Helpers for cross-midnight time comparisons
if (!function_exists('is_time_late')) {
    function is_time_late($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00') return false;
        $diff = strtotime($actual) - strtotime($expected);
        if ($diff > 43200) $diff -= 86400;
        elseif ($diff < -43200) $diff += 86400;
        return $diff > 0;
    }
}
if (!function_exists('is_time_early')) {
    function is_time_early($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00') return false;
        $diff = strtotime($actual) - strtotime($expected);
        if ($diff > 43200) $diff -= 86400;
        elseif ($diff < -43200) $diff += 86400;
        return $diff < 0;
    }
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);

// Query Detail Realisasi
    $queryDetail = "SELECT A.*, 
                           B.keterangan as keterangan_realisasi, 
                           B.tgl_realisasi, 
                           B.detail_realisasi, 
                           B.jam_kerja, 
                           C.keterangan as shift, 
                           C.jam_masuk as shift_masuk,
                           C.jam_keluar as shift_keluar,
                           C.istirahat_masuk as shift_istirahat_masuk,
                           C.istirahat_keluar as shift_istirahat_keluar,
                           BB.no_absen, 
                           BB.nama_karyawan, 
                           BB.jenis_kelamin,
                           D.nama_departmen, 
                           JB.jabatan as jabatan_tampil,
                           D2.nama_departmen as bagian_manual_str,
                           SD2.nama_sub_department as sub_bagian_manual_str,
                           BB.id_departmen as dept_master_id,
                           BB.id_jabatan as jabatan_master_id,
                           JB_D.jabatan as jabatan_detail_str,
                           RD.upah as upah_master
                    FROM tb_realisasi_detail A 
                    LEFT JOIN tb_realisasi B ON A.id_realisasi = B.id_realisasi
                    LEFT JOIN tb_jadwal C ON A.id_jadwal = C.id_jadwal
                    LEFT JOIN ms_karyawan BB ON A.id_karyawan = BB.id_karyawan
                    LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
                    /* Mengambil data Departemen & Sub-Dept langsung dari master karyawan */
                    LEFT JOIN ms_departmen D ON BB.id_departmen = D.id_departmen
                    LEFT JOIN ms_jabatan JB ON BB.id_jabatan = JB.id_jabatan
                    /* Mengambil data Departemen & Sub-Dept untuk karyawan manual */
                    LEFT JOIN ms_departmen D2 ON A.id_departmen = D2.id_departmen
                    LEFT JOIN ms_sub_department SD2 ON A.id_sub_department = SD2.id_sub_department
                    LEFT JOIN ms_jabatan JB_D ON A.id_jabatan = JB_D.id_jabatan
                    WHERE A.id_realisasi_detail = '$id'";
    
    $tampildetail = $koneksi->query($queryDetail);
    $datadetail = $tampildetail->fetch_assoc();

    if (!$datadetail) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Data Detail Realisasi tidak ditemukan!",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                }).then((result) => {
                    window.location.href = "?page=realisasi";
                });
            </script>
        </body>
        </html>';
        exit;
    }

    // Bagian & Posisi saat ini (untuk dropdown di bawah).
    // Bagian: baris ini punya id_departmen sendiri (dari RKK/manual) -> itu yang
    // dipakai; kalau 0 baru ikut master karyawan. Posisi: id_jabatan baris (0 =
    // belum pernah diubah di Detail) -> ikut master karyawan.
    $bagian_cur_id = (int)($datadetail['id_departmen'] ?? 0);
    if ($bagian_cur_id <= 0) $bagian_cur_id = (int)($datadetail['dept_master_id'] ?? 0);
    $bagian_cur_nama = $bagian_cur_id > 0 && (int)($datadetail['id_departmen'] ?? 0) > 0
        ? ($datadetail['bagian_manual_str'] ?? '')
        : ($datadetail['nama_departmen'] ?? '');
    $posisi_cur_id = (int)($datadetail['id_jabatan'] ?? 0);
    if ($posisi_cur_id <= 0) $posisi_cur_id = (int)($datadetail['jabatan_master_id'] ?? 0);
    $posisi_cur_nama = (int)($datadetail['id_jabatan'] ?? 0) > 0
        ? ($datadetail['jabatan_detail_str'] ?? '')
        : ($datadetail['jabatan_tampil'] ?? '');

    // 2026-10-26: Admin HR view-only di Realisasi, KECUALI Ganti Karyawan.
    // Semua input di bawah di-disable untuk Admin HR, KECUALI dropdown Ganti
    // Karyawan yang tetap aktif; tombol Simpan untuk Admin HR hanya menyimpan
    // Ganti Karyawan saja (cabang khusus di handler Simpan di bawah, field lain
    // diabaikan di server, jadi tidak bisa ditembus lewat POST langsung).
    $is_admin_hr_ro = (strtolower($_SESSION['role'] ?? '') === 'admin hr');
    $detail_ro = $is_admin_hr_ro ? ' disabled' : '';
    // 2026-10-26: Bagian & Posisi SELALU enabled untuk semua role, termasuk Admin HR.
    // $detail_ro tetap dipakai untuk field lain (shift, jam, upah, dll) yang masih
    // dikunci untuk Admin HR. Owner juga tetap enabled (bukan dikunci).

    // Mapping Data Detail
    $dataidrealisasi = $datadetail['id_realisasi'];
    $datatglrealisasi = $datadetail['tgl_realisasi'];
    $dataidrkkk = $datadetail['id_rkk_detail'];
    $status_realisasi_detail = $datadetail['status_realisasi_detail'];
    $datanoabsen = $datadetail['no_absen'];

    // Query Detail RKK
    $queryRKK = "SELECT A.*, B.keterangan as keterangan_rkk, B.tgl_rkk, C.keterangan as shift_rkk
                 FROM tb_rkk_detail A 
                 LEFT JOIN tb_rkk B ON A.id_rkk = B.id_rkk
                 LEFT JOIN tb_jadwal C ON A.id_jadwal = C.id_jadwal
                 WHERE A.id_rkk_detail = '$dataidrkkk'";
    
    $tampildetailrkk = $koneksi->query($queryRKK);
    $datadetailrkk = $tampildetailrkk->fetch_assoc();

    // Query Absensi dari Log Mesin (tb_record)
    $uid = $datanoabsen;
    $ttgl = $datatglrealisasi;
    $queryAbsen = "SELECT 
        (SELECT TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE userid = '$uid' AND tgl = '$ttgl' AND status = 0 ORDER BY detail_waktu ASC LIMIT 1) AS absen_masuk,
        (SELECT TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE userid = '$uid' AND tgl = '$ttgl' AND status = 2 ORDER BY detail_waktu ASC LIMIT 1) AS istirahat_keluar,
        (SELECT TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE userid = '$uid' AND tgl = '$ttgl' AND status = 3 ORDER BY detail_waktu ASC LIMIT 1) AS istirahat_masuk,
        (SELECT TIME_FORMAT(detail_waktu, '%H:%i') FROM tb_record WHERE userid = '$uid' AND tgl = '$ttgl' AND status = 1 ORDER BY detail_waktu DESC LIMIT 1) AS absen_keluar";
    
    $datadetailabsen = $koneksi->query($queryAbsen)->fetch_assoc();

    // Query Pengaturan Lembur & Denda Global
    $koneksi->query("CREATE TABLE IF NOT EXISTS tb_lembur (
        id_lembur INT AUTO_INCREMENT PRIMARY KEY,
        lembur_per_jam INT DEFAULT 0
    ) ENGINE=InnoDB");
    $checkLembur = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1");
    if ($checkLembur->num_rows == 0) {
        $koneksi->query("INSERT INTO tb_lembur (id_lembur, lembur_per_jam) VALUES (1, 0)");
    }
    $dataLembur = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1")->fetch_assoc();
    $globalLemburPerJam = $dataLembur['lembur_per_jam'] ?? 0;

    $queryDenda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $dataDenda = $queryDenda->fetch_assoc();
    $globalDendaMasuk = $dataDenda['denda_masuk'] ?? 0;
    $globalDendaIstirahatKeluar = $dataDenda['denda_istirahat_keluar'] ?? 0;
    $globalDendaIstirahatMasuk = $dataDenda['denda_istirahat_masuk'] ?? 0;
    $globalDendaPulang = $dataDenda['denda_pulang'] ?? 0;
    $globalDendaTidakLengkap = $dataDenda['denda_tidak_lengkap'] ?? 0;

    // Get Global Daily Wage as fallback for manual employees (Hardcoded fallback)
    $default_upah = 115000;

    // Baris borongan: upahnya ikut total borongan, jadi kolom UPAH di Detail
    // tetap readonly untuk baris ini (sama seperti aturan edit upah di Kelola dulu).
    $is_borongan_detail = false;
    $q_bor_detail = $koneksi->query("SHOW TABLES LIKE 'tb_realisasi_borongan'");
    if ($q_bor_detail && $q_bor_detail->num_rows > 0) {
        $q_bor_c = $koneksi->query("SELECT COUNT(*) c FROM tb_realisasi_borongan WHERE id_realisasi_detail = '$id'");
        $is_borongan_detail = $q_bor_c && (int)$q_bor_c->fetch_assoc()['c'] > 0;
    }

    // Tentukan data yang digunakan untuk kalkulasi potongan (Input ra_ atau Log Mesin)
    $jamMasukRealisasi = !empty($datadetail['ra_masuk']) ? $datadetail['ra_masuk'] : ($datadetailabsen['absen_masuk'] ?? '');
    $jamKeluarRealisasi = !empty($datadetail['ra_keluar']) ? $datadetail['ra_keluar'] : ($datadetailabsen['absen_keluar'] ?? '');
    $jamIstirahatKeluarRealisasi = !empty($datadetail['ra_istirahat_keluar']) ? $datadetail['ra_istirahat_keluar'] : ($datadetailabsen['istirahat_keluar'] ?? '');
    $jamIstirahatMasukRealisasi = !empty($datadetail['ra_istirahat_masuk']) ? $datadetail['ra_istirahat_masuk'] : ($datadetailabsen['istirahat_masuk'] ?? '');

    // Logika Perhitungan Potongan Otomatis
    $has_masuk = !empty($jamMasukRealisasi) && $jamMasukRealisasi != '00:00:00';
    $has_keluar = !empty($jamKeluarRealisasi) && $jamKeluarRealisasi != '00:00:00';
    $has_ist_masuk = !empty($jamIstirahatMasukRealisasi) && $jamIstirahatMasukRealisasi != '00:00:00';
    $has_ist_keluar = !empty($jamIstirahatKeluarRealisasi) && $jamIstirahatKeluarRealisasi != '00:00:00';

    $is_flexible = is_flexible_shift($datadetail['shift'] ?? '');
    // Flexi murni = bebas jam (tanpa target & tanpa denda waktu); lembur manual saja.

    $isTotalMissing = (!$has_masuk && !$has_keluar);
    $hasIncompleteMain = ($has_masuk XOR $has_keluar);
    
    if ($is_flexible) {
        // Flexi murni: bebas jam masuk/pulang/istirahat — tanpa denda waktu
        $isLate = false;
        $isLateBreak = false;
        $isEarlyBreak = false;
        $isEarlyOut = false;
        $hasIncompleteBreak = false;
    } else {
        $isRestExpected = (!empty($datadetail['shift_istirahat_keluar']) && $datadetail['shift_istirahat_keluar'] != '00:00:00');
        $hasIncompleteBreak = ($isRestExpected && ($has_ist_keluar XOR $has_ist_masuk));
        
        $isLate = ($has_masuk && !empty($datadetail['shift_masuk']) && late_minutes($jamMasukRealisasi, $datadetail['shift_masuk']) > $toleransi_d['masuk']);
        $isEarlyOut = ($has_keluar && !empty($datadetail['shift_keluar']) && early_minutes($jamKeluarRealisasi, $datadetail['shift_keluar']) > $toleransi_d['pulang']); 
        $isLateBreak = ($has_ist_masuk && !empty($datadetail['shift_istirahat_masuk']) && late_minutes($jamIstirahatMasukRealisasi, $datadetail['shift_istirahat_masuk']) > $toleransi_d['ist_masuk']);
        $isEarlyBreak = ($has_ist_keluar && !empty($datadetail['shift_istirahat_keluar']) && early_minutes($jamIstirahatKeluarRealisasi, $datadetail['shift_istirahat_keluar']) > $toleransi_d['ist_keluar']);
    }

    // Logic denda: Kalkulasi otomatis berdasarkan jam hadir/jadwal (flexi murni bebas semua denda waktu)
    $hasilpotonganpulang = ($isEarlyOut && !$isTotalMissing) ? $globalDendaPulang : 0;
    $hasilpotongantidaklengkap = (!$isTotalMissing && !$is_flexible && ($hasIncompleteMain || $hasIncompleteBreak)) ? $globalDendaTidakLengkap : 0;
    $hasilpotongantelat = ($isLate) ? $globalDendaMasuk : 0;
    $hasilpotonganistirahatkeluar = ($isEarlyBreak) ? $globalDendaIstirahatKeluar : 0;
    $hasilpotonganistirahatmasuk = ($isLateBreak) ? $globalDendaIstirahatMasuk : 0;

    // Logic Status Kehadiran (Refined)
    $isTotalMissing = (empty($jamMasukRealisasi) || $jamMasukRealisasi == '00:00:00') && (empty($jamKeluarRealisasi) || $jamKeluarRealisasi == '00:00:00');
    $attendanceStatus = 'HADIR';
    $statusBadgeClass = 'bg-green-100 text-green-800';

    if ($isTotalMissing) {
        if ($datadetail['status_realisasi_detail'] == 0) {
            // User wants "HADIR" for draft even if empty
            $attendanceStatus = 'HADIR';
            $statusBadgeClass = 'bg-green-100 text-green-800';
        } else {
            $attendanceStatus = 'TIDAK HADIR';
            $statusBadgeClass = 'bg-red-100 text-red-800';
        }
    }

    // Logika Upah Pokok: Utamakan r_upah (manual) jika ada, jika tidak gunakan upah_master
    // Jika manual (r_upah) kosong (misal akibat sync), gunakan default_upah
    $upahPokokAsli = ($datadetail['r_upah'] > 0) ? (float)$datadetail['r_upah'] : 
                     (!empty($datadetail['upah_master']) ? (float)$datadetail['upah_master'] : $default_upah);
    $upahPokokTampil = $upahPokokAsli;
    
    // Wage zeroed for TOTAL MISSING after sync
    if ($attendanceStatus == 'TIDAK HADIR') {
        $upahPokokTampil = 0;
    }

    // Logika Lembur: manual / tersimpan (flexi murni tanpa target jam otomatis)
    $lembur_jam_val = isset($datadetail['lembur_jam']) ? (float)$datadetail['lembur_jam'] : 0;
    $lemburPokokTampil = ($lembur_jam_val > 0) ? ($lembur_jam_val * $globalLemburPerJam) : (float)($datadetail['lembur'] ?? 0);

    // Additional Safeguard for Draft (Status 0): Force zero penalties
    if ($datadetail['status_realisasi_detail'] == 0) {
        $hasilpotongantelat = 0;
        $hasilpotonganistirahatkeluar = 0;
        $hasilpotonganistirahatmasuk = 0;
        $hasilpotonganpulang = 0;
        $hasilpotongantidaklengkap = 0;
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka) {
        if ($angka === null || $angka === "") $angka = 0;
        return "Rp " . number_format($angka, 0, ',', '.');
    }
}
?>

<style>
    /* Styling Dasar Modern */
    .card-modern {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        padding: 25px;
        margin-bottom: 25px;
        border: none;
    }
    .section-divider {
        display: flex;
        align-items: center;
        color: #2563eb;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 30px 0 15px 0;
        font-size: 0.9rem;
    }
    .section-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e5e7eb;
        margin-left: 15px;
    }
    /* Input Styling */
    .form-control {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 10px 15px;
        height: auto;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }
    label { font-size: 0.75rem; color: #6b7280; margin-bottom: 8px; font-weight: 600; }
    
    /* Tombol Modern */
    .btn-custom { padding: 10px 20px; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
    .btn-primary-custom { background-color: #2563eb; color: white; border: none; }
    .btn-primary-custom:hover { background-color: #1d4ed8; color: white; transform: translateY(-1px); }
    .btn-warning-custom { background-color: #f39c12; color: white; border: none; }
    .btn-warning-custom:hover { background-color: #d68910; color: white; }
</style>

<div class="row">
    <div class="col-md-12">
        <form method="POST" enctype="multipart/form-data">
            <div class="card-modern">
                <h3 class="text-blue-600 font-bold"><i class="fas fa-clipboard-check mr-2"></i> Detail Realisasi Upah</h3>

                <div class="section-divider">Rencana Kerja (RKK)</div>
                <div class="row">
                    <div class="form-group col-md-3">
                        <label>TANGGAL RKK</label>
                        <input type="text" readonly value="<?= $datadetailrkk['tgl_rkk'] ?? $datadetail['tgl_realisasi'] ?>" class="form-control" style="background: #f9fafb;">
                    </div>
                    <div class="form-group col-md-6">
                        <label>KETERANGAN RKK</label>
                        <input type="text" readonly value="<?= $datadetailrkk['keterangan_rkk'] ?? $datadetail['keterangan_realisasi'] ?>" class="form-control" style="background: #f9fafb;">
                    </div>
                    <div class="form-group col-md-3">
                        <label>SHIFT RKK</label>
                        <input type="text" readonly value="<?= $datadetailrkk['shift_rkk'] ?? $datadetail['shift'] ?>" class="form-control" style="background: #f9fafb;">
                    </div>
                </div>

                <div class="section-divider">Data Karyawan</div>
                <div class="row">
                    <div class="form-group col-md-2">
                        <label>NO. ABSEN</label>
                        <input type="text" readonly value="<?= !empty($datadetail['no_absen']) ? $datadetail['no_absen'] : '-' ?>" class="form-control" style="background: #f9fafb;">
                    </div>
                    <div class="form-group col-md-4">
                        <label>NAMA KARYAWAN</label>
                        <input type="text" readonly value="<?= !empty($datadetail['nama_karyawan']) ? $datadetail['nama_karyawan'] : ($datadetail['nama_karyawan_manual'] ?? '') ?>" class="form-control" style="background: #f9fafb;">
                    </div>
                    <div class="form-group col-md-3">
                        <label>BAGIAN</label>
                        <select name="tbagian" id="tbagian" class="form-control">
                            <?php
                            // Dropdown Bagian untuk SEMUA role (Detail memang sudah terbuka
                            // untuk semua role, jadi tidak ada gate role di sini).
                            $q_bag = $koneksi->query("SELECT id_departmen, nama_departmen FROM ms_departmen ORDER BY nama_departmen ASC");
                            while ($q_bag && ($bg = $q_bag->fetch_assoc())) {
                                echo '<option value="' . (int)$bg['id_departmen'] . '"' . ((int)$bg['id_departmen'] === $bagian_cur_id ? ' selected' : '') . '>' . htmlspecialchars($bg['nama_departmen']) . '</option>';
                            }
                            if ($bagian_cur_id > 0 && $bagian_cur_nama === '') {
                                echo '<option value="' . $bagian_cur_id . '" selected>Bagian #' . $bagian_cur_id . ' (master sudah tidak ada)</option>';
                            }
                            ?>
                            <option value="__baru__">+ Tambah Bagian Baru...</option>
                        </select>
                        <input type="text" name="tbagian_baru" id="tbagian_baru" class="form-control mt-2" placeholder="Nama Bagian Baru, mis. PRODUKSI" style="display:none;" autocomplete="off">
                    </div>
                    <div class="form-group col-md-3">
                        <label>POSISI</label>
                        <select name="tposisi" id="tposisi" class="form-control">
                            <?php
                            $q_pos = $koneksi->query("SELECT id_jabatan, jabatan FROM ms_jabatan ORDER BY jabatan ASC");
                            while ($q_pos && ($ps = $q_pos->fetch_assoc())) {
                                echo '<option value="' . (int)$ps['id_jabatan'] . '"' . ((int)$ps['id_jabatan'] === $posisi_cur_id ? ' selected' : '') . '>' . htmlspecialchars($ps['jabatan']) . '</option>';
                            }
                            if ($posisi_cur_id > 0 && $posisi_cur_nama === '') {
                                echo '<option value="' . $posisi_cur_id . '" selected>Posisi #' . $posisi_cur_id . ' (master sudah tidak ada)</option>';
                            }
                            ?>
                            <option value="__baru__">+ Tambah Posisi Baru...</option>
                        </select>
                        <input type="text" name="tposisi_baru" id="tposisi_baru" class="form-control mt-2" placeholder="Nama Posisi Baru, mis. KEPALA PRODUKSI" style="display:none;" autocomplete="off">
                    </div>
                    <script>
                    (function() {
                        function toggleBaru(selId, inpId) {
                            var s = document.getElementById(selId), i = document.getElementById(inpId);
                            if (!s || !i) return;
                            var show = (s.value === '__baru__');
                            i.style.display = show ? '' : 'none';
                            if (show) i.focus();
                        }
                        var b = document.getElementById('tbagian'), pb = document.getElementById('tbagian_baru');
                        var j = document.getElementById('tposisi'), pj = document.getElementById('tposisi_baru');
                        if (b) b.addEventListener('change', function() { toggleBaru('tbagian', 'tbagian_baru'); });
                        if (j) j.addEventListener('change', function() { toggleBaru('tposisi', 'tposisi_baru'); });
                    })();
                    </script>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>GANTI KARYAWAN <span class="text-muted">(nama tetap — tercatat "Digantikan oleh ...", ikut tersimpan saat Simpan)</span></label>
                        <input type="text" id="tukar_search" class="form-control mb-2" placeholder="Ketik untuk cari karyawan..." autocomplete="off">
                        <select name="tukar_id_karyawan" id="tukar_select" class="form-control">
                            <!-- 2026-10-26: "Tidak Ganti" kini opsi nyata bernilai 0 (bukan cuma
                                 placeholder Select2), jadi kalau sudah terlanjur pilih pengganti,
                                 bisa dipilih kembali ke Tidak Ganti lalu Simpan = batal ganti.
                                 Memilih nama karyawan asli juga tetap = batal ganti. -->
                            <option value="0"<?= ((int)($datadetail['id_pengganti'] ?? 0) === 0) ? ' selected' : '' ?>>- Tidak Ganti -</option>
                            <?php
                            $tukar_cur = (int)($datadetail['id_pengganti'] ?? 0);
                            $tukar_owner = (int)($datadetail['id_karyawan'] ?? 0);
                            // Semua karyawan aktif tampil; yang sudah masuk rencana dikunci (disabled),
                            // hanya yang belum masuk rencana yang bisa dipilih jadi pengganti.
                            // PENGECUALIAN: karyawan asli baris ini TIDAK dikunci — memilih namanya
                            // artinya batal ganti (kembali ke karyawan asli), bukan error lagi.
                            $tukar_in = [];
                            $q_in = $koneksi->query("SELECT DISTINCT id_karyawan FROM tb_realisasi_detail WHERE id_realisasi = '" . (int)$dataidrealisasi . "' AND id_karyawan <> 0");
                            while ($q_in && ($rr = $q_in->fetch_assoc())) $tukar_in[(int)$rr['id_karyawan']] = true;
                            $q_tukar = $koneksi->query("SELECT K.id_karyawan, K.nama_karyawan, O.OS_DHK as os FROM ms_karyawan K LEFT JOIN ms_os_dhk O ON K.id_os_dhk = O.id_os_dhk WHERE K.status_karyawan = 'Aktif' AND K.nama_karyawan NOT LIKE 'User PIN%' ORDER BY K.nama_karyawan ASC");
                            while ($q_tukar && ($tk = $q_tukar->fetch_assoc())) {
                                $tid = (int)$tk['id_karyawan'];
                                $locked = ($tid !== $tukar_cur && $tid !== $tukar_owner && isset($tukar_in[$tid]));
                                $suffix = $locked ? ' (sudah di rencana)' : (($tid === $tukar_owner && $tukar_cur > 0) ? ' (karyawan asli — pilih untuk batal ganti)' : '');
                                $lbl = $tk['nama_karyawan'] . ' — ' . ($tk['os'] ?: '-') . $suffix;
                                echo "<option value=\"$tid\"" . ($tid === $tukar_cur ? ' selected' : '') . ($locked ? ' disabled' : '') . ">" . htmlspecialchars($lbl) . "</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">Untuk kembali ke karyawan asli, pilih "- Tidak ganti -" atau nama karyawan asli di atas, lalu Simpan.</small>
                        <script>
                        (function() {
                            var inp = document.getElementById('tukar_search');
                            var sel = document.getElementById('tukar_select');
                            if (!inp || !sel) return;
                            inp.addEventListener('input', function() {
                                var q = inp.value.toLowerCase().trim();
                                for (var i = 0; i < sel.options.length; i++) {
                                    var o = sel.options[i];
                                    if (o.selected || o.value === '' || o.value === '0') { o.hidden = false; continue; }
                                    o.hidden = (q !== '' && o.text.toLowerCase().indexOf(q) === -1);
                                }
                            });
                        })();
                        </script>
                    </div>
                </div>

                <div class="section-divider text-danger">
                    Input Realisasi Absensi & Upah
                    <span id="attendance-badge" class="ml-4 px-3 py-1 rounded-full text-[12px] font-bold <?= $statusBadgeClass ?>">
                        STATUS: <?= $attendanceStatus ?>
                    </span>
                </div>
                <div class="row">
                    <div class="form-group col-md-3">
                        <label>SHIFT REALISASI</label>
                        <select class="form-control" name="tshift"<?= $detail_ro ?> required>
                            <option value="<?= $datadetail['id_jadwal'] ?>"><?= $datadetail['shift'] ?></option>
                            <?php 
                            $sqlJadwal = $koneksi->query("SELECT * FROM tb_jadwal");
                            while ($j = $sqlJadwal->fetch_assoc()) {
                                echo "<option value='".$j['id_jadwal']."'>".$j['keterangan']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2"> <label>ABSEN MASUK</label> <input type="time" name="tjammasuk"<?= $detail_ro ?> value="<?= (empty($datadetail['ra_masuk']) || $datadetail['ra_masuk'] == '00:00:00') ? '' : $datadetail['ra_masuk'] ?>" class="form-control"> </div>
                    <div class="form-group col-md-2"> <label>ABSEN KELUAR</label> <input type="time" name="tjamkeluar"<?= $detail_ro ?> value="<?= (empty($datadetail['ra_keluar']) || $datadetail['ra_keluar'] == '00:00:00') ? '' : $datadetail['ra_keluar'] ?>" class="form-control"> </div>
                    <div class="form-group col-md-2"> <label>ISTIRAHAT MASUK</label> <input type="time" name="tistirahatmasuk"<?= $detail_ro ?> value="<?= (empty($datadetail['ra_istirahat_masuk']) || $datadetail['ra_istirahat_masuk'] == '00:00:00') ? '' : $datadetail['ra_istirahat_masuk'] ?>" class="form-control"> </div>
                    <div class="form-group col-md-2"> <label>ISTIRAHAT KELUAR</label> <input type="time" name="tistirahatkeluar"<?= $detail_ro ?> value="<?= (empty($datadetail['ra_istirahat_keluar']) || $datadetail['ra_istirahat_keluar'] == '00:00:00') ? '' : $datadetail['ra_istirahat_keluar'] ?>" class="form-control"> </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-2"> <label>UPAH (POKOK)</label> <input type="number" name="tupah"<?= $detail_ro ?> value="<?= $upahPokokTampil ?>" class="form-control" min="0" <?= !empty($is_borongan_detail) ? 'readonly style="background: #f9fafb;" title="Baris borongan: upah ikut total borongan, tidak bisa diubah manual"' : 'title="Bisa diedit manual di sini — nilai ini yang disimpan (ubah upah hanya di Detail, bukan di Kelola)"' ?>> </div>
                    <div class="form-group col-md-2"> <label>POT. TELAT</label> <input type="number" name="tpottelat" value="<?= $hasilpotongantelat ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. IST (AWAL)</label> <input type="number" name="tpotistirahatkeluar" value="<?= $hasilpotonganistirahatkeluar ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. IST (TELAT)</label> <input type="number" name="tpotistirahatmasuk" value="<?= $hasilpotonganistirahatmasuk ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. PULANG AWAL</label> <input type="number" name="tpotpulang" value="<?= $hasilpotonganpulang ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. ABSEN TIDAK LENGKAP</label> <input type="number" name="tpotlog" value="<?= $hasilpotongantidaklengkap ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-2"> <label>POT. LAINNYA</label> <input type="number" name="tpotlainnya"<?= $detail_ro ?> value="<?= $datadetail['r_potongan_lainnya'] ?>" class="form-control" required> </div>
                    <div class="form-group col-md-2"> 
                        <label>LEMBUR (JAM) <span class="text-indigo-600 font-bold">@<?= rupiah($globalLemburPerJam) ?>/Jam</span></label> 
                        <input type="number" step="0.1" name="tlembur_jam"<?= $detail_ro ?> value="<?= $lembur_jam_val ?>" class="form-control"> 
                    </div>
                    <div class="form-group col-md-2"> 
                        <label>TOTAL UPAH LEMBUR (Rp)</label> 
                        <input type="number" name="tlembur" value="<?= $lemburPokokTampil ?>" class="form-control" readonly style="background: #f9fafb;"> 
                    </div>
                    <div class="form-group col-md-2"> 
                        <label>TOTAL UPAH AKHIR</label> 
                        <?php $totalAkhir = $upahPokokTampil + $lemburPokokTampil - $hasilpotongantelat - $hasilpotonganistirahatkeluar - $hasilpotonganistirahatmasuk - $datadetail['r_potongan_lainnya'] - $hasilpotonganpulang - $hasilpotongantidaklengkap; ?>
                        <input type="text" value="<?= rupiah($totalAkhir) ?>" class="form-control font-bold text-blue-600" readonly style="background: #eff6ff;"> 
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-12">
                        <label>HASIL KERJA / KETERANGAN DETAIL</label>
                        <textarea name="thasilkerja" class="form-control" rows="3"<?= $detail_ro ?>><?= $datadetail['hasil_kerja'] ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f3f4f6;">
                    <a href="?page=realisasi&aksi=kelola&id=<?= $dataidrealisasi ?>" class="btn btn-warning-custom btn-custom">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>
                    <?php if (!empty($is_admin_hr_ro)): ?>
                    <button type="submit" name="simpan" class="btn btn-primary-custom btn-custom">
                        <i class="fa fa-save"></i> Simpan Ganti Karyawan & Bagian/Posisi
                    </button>
                    <?php else: ?>
                    <button type="submit" name="simpan" class="btn btn-primary-custom btn-custom">
                        <i class="fa fa-save"></i> Simpan Data Realisasi
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
if (isset($_POST['simpan'])) {
    // Admin HR: Simpan menyimpan Ganti Karyawan + Bagian/Posisi saja (2026-10-26).
    // Field lain (shift, jam, upah, potongan, lembur, hasil kerja) SENGAJA diabaikan
    // di sini, walau POST dikirim langsung — jadi tidak bisa ditembus untuk mengubah
    // field lain. Bagian/Posisi kini enabled di form untuk Admin HR, jadi nilainya
    // ikut terkirim dan disimpan per baris (sama seperti handler lengkap, termasuk
    // "+ Tambah Bagian/Posisi Baru..." yang di-INSERT ke master kalau belum ada).
    if (strtolower($_SESSION['role'] ?? '') === 'admin hr') {
        // --- Bagian & Posisi untuk Admin HR (validasi dulu, sebelum tukar) ---
        $bagian_id_hr = (int)($datadetail['id_departmen'] ?? 0);
        if ($bagian_id_hr <= 0) $bagian_id_hr = (int)($datadetail['dept_master_id'] ?? 0);
        $posisi_id_hr = (int)($datadetail['id_jabatan'] ?? 0);
        if ($posisi_id_hr <= 0) $posisi_id_hr = (int)($datadetail['jabatan_master_id'] ?? 0);
        $bp_err_hr = '';
        $bagian_post_hr = trim((string)($_POST['tbagian'] ?? ''));
        $posisi_post_hr = trim((string)($_POST['tposisi'] ?? ''));
        if ($bagian_post_hr === '__baru__') {
            $b_nama_hr = trim((string)($_POST['tbagian_baru'] ?? ''));
            if ($b_nama_hr === '') $bp_err_hr = 'Nama Bagian Baru belum diisi!';
            else {
                $esc_b_hr = $koneksi->real_escape_string($b_nama_hr);
                $q_b_hr = $koneksi->query("SELECT id_departmen FROM ms_departmen WHERE UPPER(TRIM(nama_departmen)) = UPPER(TRIM('$esc_b_hr')) LIMIT 1");
                if ($q_b_hr && ($rb_hr = $q_b_hr->fetch_assoc())) $bagian_id_hr = (int)$rb_hr['id_departmen'];
                elseif ($koneksi->query("INSERT INTO ms_departmen (nama_departmen, id_perusahaan) VALUES ('$esc_b_hr', '1')")) $bagian_id_hr = (int)$koneksi->insert_id;
                else $bp_err_hr = 'Gagal menambah Bagian baru: ' . $koneksi->error;
            }
        } elseif ($bagian_post_hr !== '' && is_numeric($bagian_post_hr)) $bagian_id_hr = (int)$bagian_post_hr;
        if ($bp_err_hr === '' && $posisi_post_hr === '__baru__') {
            $p_nama_hr = trim((string)($_POST['tposisi_baru'] ?? ''));
            if ($p_nama_hr === '') $bp_err_hr = 'Nama Posisi Baru belum diisi!';
            else {
                $esc_p_hr = $koneksi->real_escape_string($p_nama_hr);
                $q_p_hr = $koneksi->query("SELECT id_jabatan FROM ms_jabatan WHERE UPPER(TRIM(jabatan)) = UPPER(TRIM('$esc_p_hr')) LIMIT 1");
                if ($q_p_hr && ($rp_hr = $q_p_hr->fetch_assoc())) $posisi_id_hr = (int)$rp_hr['id_jabatan'];
                elseif ($koneksi->query("INSERT INTO ms_jabatan (jabatan, id_perusahaan) VALUES ('$esc_p_hr', '1')")) $posisi_id_hr = (int)$koneksi->insert_id;
                else $bp_err_hr = 'Gagal menambah Posisi baru: ' . $koneksi->error;
            }
        } elseif ($bp_err_hr === '' && $posisi_post_hr !== '' && is_numeric($posisi_post_hr)) $posisi_id_hr = (int)$posisi_post_hr;
        if ($bp_err_hr !== '') {
            echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
                Swal.fire({ icon: "error", title: "Gagal", text: "' . addslashes($bp_err_hr) . ' Data tidak disimpan.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.history.back(); });
            </script></body></html>';
            exit;
        }
        $now_tk = date("Y-m-d H:i:s");
        $tukar_new_tk = (int)($_POST['tukar_id_karyawan'] ?? 0);
        $tukar_cur_tk = (int)($datadetail['id_pengganti'] ?? 0);
        $tukar_owner_tk = (int)($datadetail['id_karyawan'] ?? 0);
        // Memilih nama karyawan asli = batal ganti (sama seperti handler lengkap).
        if ($tukar_new_tk === $tukar_owner_tk) $tukar_new_tk = 0;
        $tukar_err_tk = '';
        if ($tukar_new_tk !== $tukar_cur_tk) {
            if ($tukar_new_tk > 0) {
                $st_real_tk = $koneksi->query("SELECT status_realisasi FROM tb_realisasi WHERE id_realisasi = '" . (int)$datadetail['id_realisasi'] . "'")->fetch_assoc();
                if ($st_real_tk && (int)$st_real_tk['status_realisasi'] >= 2) {
                    $tukar_err_tk = 'Realisasi sudah Approved, tidak bisa tukar karyawan!';
                } else {
                    $mk_tk = $koneksi->query("SELECT upah FROM ms_karyawan WHERE id_karyawan = '$tukar_new_tk' AND status_karyawan = 'Aktif' LIMIT 1")->fetch_assoc();
                    if (!$mk_tk) {
                        $tukar_err_tk = 'Karyawan pengganti tidak valid / nonaktif!';
                    } else {
                        $dup_tk = $koneksi->query("SELECT id_realisasi_detail FROM tb_realisasi_detail WHERE id_realisasi = '" . (int)$datadetail['id_realisasi'] . "' AND id_karyawan = '$tukar_new_tk' AND id_realisasi_detail <> '$id' LIMIT 1");
                        if ($dup_tk && $dup_tk->num_rows > 0) $tukar_err_tk = 'Karyawan ini sudah masuk rencana (terkunci)!';
                    }
                }
            }
            if ($tukar_err_tk === '') {
                if ($tukar_new_tk > 0) {
                    // Rincian borongan milik orang lama ikut dibuang (sama seperti handler lengkap).
                    $ckBor_tk = $koneksi->query("SHOW TABLES LIKE 'tb_realisasi_borongan'");
                    if ($ckBor_tk && $ckBor_tk->num_rows > 0) {
                        $koneksi->query("DELETE FROM tb_realisasi_borongan WHERE id_realisasi_detail = '$id'");
                    }
                    $q_bor_tk = $koneksi->query("SELECT id_karyawan FROM ms_grup_borongan_anggota WHERE id_karyawan = '$tukar_new_tk' LIMIT 1");
                    $peng_bor_tk = ($q_bor_tk && $q_bor_tk->num_rows > 0);
                    $is_missing_tk = (empty($datadetail['ra_masuk']) || $datadetail['ra_masuk'] == '00:00:00') && (empty($datadetail['ra_keluar']) || $datadetail['ra_keluar'] == '00:00:00');
                    if ($peng_bor_tk) {
                        // Pengganti borongan: upah ikut rincian borongan (yang baru saja
                        // dihapus), jadi upah pokok di sini 0; status ikut ada/tidaknya jam.
                        $up_tk = 0; $st_tk = $is_missing_tk ? 'Tidak Hadir' : 'Hadir';
                        $koneksi->query("UPDATE tb_realisasi_detail SET id_pengganti = '$tukar_new_tk', r_upah = $up_tk, r_status = '$st_tk', status_realisasi_detail = 1, r_update = '$now_tk' WHERE id_realisasi_detail = '$id'");
                    } elseif ($is_missing_tk) {
                        // Jam kosong + pengganti non-borongan = dianggap hadir, upah
                        // pakai yang sudah ada di baris, kalau 0 pakai upah RKK, kalau
                        // masih 0 pakai upah master pengganti (urutan handler lengkap
                        // tanpa nilai form, karena form di-disable untuk Admin HR).
                        $up_tk = ($datadetail['r_upah'] > 0) ? (float)$datadetail['r_upah'] : (!empty($datadetail['upah_master']) ? (float)$datadetail['upah_master'] : 0);
                        if ($up_tk <= 0) {
                            $q_pu_tk = $koneksi->query("SELECT upah FROM ms_karyawan WHERE id_karyawan = '$tukar_new_tk' LIMIT 1");
                            $up_tk = ($q_pu_tk && ($rp_tk = $q_pu_tk->fetch_assoc())) ? (float)$rp_tk['upah'] : 0;
                        }
                        $koneksi->query("UPDATE tb_realisasi_detail SET id_pengganti = '$tukar_new_tk', r_upah = $up_tk, r_status = 'Hadir', status_realisasi_detail = 1, r_update = '$now_tk' WHERE id_realisasi_detail = '$id'");
                    } else {
                        // Sudah ada jam: upah & status lama dipertahankan, hanya pengganti berubah.
                        $koneksi->query("UPDATE tb_realisasi_detail SET id_pengganti = '$tukar_new_tk', status_realisasi_detail = 1, r_update = '$now_tk' WHERE id_realisasi_detail = '$id'");
                    }
                } else {
                    // Batal ganti: hanya id_pengganti yang di-NULL-kan, upah & status
                    // lama dipertahankan (tidak di-nol-kan).
                    $koneksi->query("UPDATE tb_realisasi_detail SET id_pengganti = NULL, status_realisasi_detail = 1, r_update = '$now_tk' WHERE id_realisasi_detail = '$id'");
                }
            }
        }
        if ($tukar_err_tk === '' && $tukar_new_tk !== $tukar_cur_tk && $koneksi->error !== '') $tukar_err_tk = 'Gagal menyimpan Ganti Karyawan.';
        // Simpan Bagian & Posisi untuk Admin HR (walau Ganti Karyawan tidak berubah,
        // Bagian/Posisi yang diubah tetap harus tersimpan; kolom lain tidak disentuh).
        if ($tukar_err_tk === '') {
            $koneksi->query("UPDATE tb_realisasi_detail SET id_departmen = '$bagian_id_hr', id_jabatan = '$posisi_id_hr', status_realisasi_detail = 1, r_update = '$now_tk' WHERE id_realisasi_detail = '$id'");
        }
        if ($tukar_err_tk !== '') {
            echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
                Swal.fire({ icon: "error", title: "Gagal Tukar", text: "' . addslashes($tukar_err_tk) . ' Data tidak disimpan.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.history.back(); });
            </script></body></html>';
            exit;
        }
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
            Swal.fire({ icon: "success", title: "Berhasil", text: "Ganti Karyawan & Bagian/Posisi berhasil disimpan.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.location.href = "?page=realisasi&aksi=detail&id=' . $id . '"; });
        </script></body></html>';
        exit;
    }
    $now = date("Y-m-d H:i:s");
    // Ambil data POST dan bersihkan
    $tupah = (float)($_POST['tupah'] ?? 0);
    $tshift = (int)($_POST['tshift'] ?? 0);
    $tpotlainnya = (float)($_POST['tpotlainnya'] ?? 0);
    $tpottelat = (float)($_POST['tpottelat'] ?? 0);
    $tpotistirahatawal = (float)($_POST['tpotistirahatkeluar'] ?? 0);
    $tpotistirahattelat = (float)($_POST['tpotistirahatmasuk'] ?? 0);
    $tpotpulang = (float)($_POST['tpotpulang'] ?? 0);
    $tpotlog = (float)($_POST['tpotlog'] ?? 0);
    $tjammasuk = $_POST['tjammasuk'] ?? '';
    $tjamkeluar = $_POST['tjamkeluar'] ?? '';
    $tistirahatmasuk = $_POST['tistirahatmasuk'] ?? '';
    $tistirahatkeluar = $_POST['tistirahatkeluar'] ?? '';
    $hasilkerjanya = mysqli_real_escape_string($koneksi, $_POST['thasilkerja'] ?? '');
    // Bagian & Posisi: disimpan PER BARIS realisasi ini saja (id_departmen /
    // id_jabatan di tb_realisasi_detail), TIDAK mengubah master ms_karyawan,
    // jadi tidak mengubah hari lain / karyawan lain. Semua role boleh mengubah
    // di Detail ini (tidak ada gate role, sama seperti Detail yang sudah
    // terbuka untuk semua role).
    $bagian_post = trim((string)($_POST['tbagian'] ?? ''));
    $posisi_post = trim((string)($_POST['tposisi'] ?? ''));
    $bagian_baru_nama = trim((string)($_POST['tbagian_baru'] ?? ''));
    $posisi_baru_nama = trim((string)($_POST['tposisi_baru'] ?? ''));
    $bagian_id_save = (int)($datadetail['id_departmen'] ?? 0);
    if ($bagian_id_save <= 0) $bagian_id_save = (int)($datadetail['dept_master_id'] ?? 0);
    $posisi_id_save = (int)($datadetail['id_jabatan'] ?? 0);
    if ($posisi_id_save <= 0) $posisi_id_save = (int)($datadetail['jabatan_master_id'] ?? 0);
    $master_err = '';
    if ($bagian_post === '__baru__') {
        if ($bagian_baru_nama === '') {
            $master_err = 'Nama Bagian Baru belum diisi!';
        } else {
            $esc_b = $koneksi->real_escape_string($bagian_baru_nama);
            $q_b = $koneksi->query("SELECT id_departmen FROM ms_departmen WHERE UPPER(TRIM(nama_departmen)) = UPPER(TRIM('$esc_b')) LIMIT 1");
            if ($q_b && ($rb = $q_b->fetch_assoc())) {
                $bagian_id_save = (int)$rb['id_departmen']; // sudah ada -> pakai yang lama
            } elseif ($koneksi->query("INSERT INTO ms_departmen (nama_departmen, id_perusahaan) VALUES ('$esc_b', '1')")) {
                $bagian_id_save = (int)$koneksi->insert_id;
            } else {
                $master_err = 'Gagal menambah Bagian baru: ' . $koneksi->error;
            }
        }
    } elseif ($bagian_post !== '' && is_numeric($bagian_post)) {
        $bagian_id_save = (int)$bagian_post;
    }
    if ($master_err === '' && $posisi_post === '__baru__') {
        if ($posisi_baru_nama === '') {
            $master_err = 'Nama Posisi Baru belum diisi!';
        } else {
            $esc_p = $koneksi->real_escape_string($posisi_baru_nama);
            $q_p = $koneksi->query("SELECT id_jabatan FROM ms_jabatan WHERE UPPER(TRIM(jabatan)) = UPPER(TRIM('$esc_p')) LIMIT 1");
            if ($q_p && ($rp = $q_p->fetch_assoc())) {
                $posisi_id_save = (int)$rp['id_jabatan']; // sudah ada -> pakai yang lama
            } elseif ($koneksi->query("INSERT INTO ms_jabatan (jabatan, id_perusahaan) VALUES ('$esc_p', '1')")) {
                $posisi_id_save = (int)$koneksi->insert_id;
            } else {
                $master_err = 'Gagal menambah Posisi baru: ' . $koneksi->error;
            }
        }
    } elseif ($master_err === '' && $posisi_post !== '' && is_numeric($posisi_post)) {
        $posisi_id_save = (int)$posisi_post;
    }
    if ($master_err !== '') {
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
            Swal.fire({ icon: "error", title: "Gagal", text: "' . addslashes($master_err) . ' Data tidak disimpan.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.history.back(); });
        </script></body></html>';
        exit;
    }
    $tlembur = (float)($_POST['tlembur'] ?? 0);
    $tlembur_jam = (float)($_POST['tlembur_jam'] ?? 0);
    // Pengganti (tukar karyawan): nama di baris ini TETAP, yang dicatat penggantinya.
    // Status & upah ikut normal (tidak jadi Tidak Hadir).
    $tukar_new = (int)($_POST['tukar_id_karyawan'] ?? 0);
    $tukar_cur = (int)($datadetail['id_pengganti'] ?? 0);
    $vganti = ($tukar_cur > 0) ? "'$tukar_cur'" : "NULL";
    if ($tukar_new !== $tukar_cur) {
        if ($tukar_new <= 0) {
            $vganti = "NULL"; // batal ganti
        } elseif ($tukar_new === (int)($datadetail['id_karyawan'] ?? 0)) {
            // Memilih nama karyawan asli = batal ganti juga. Sebelumnya cabang ini
            // error "Pengganti sama dengan karyawan baris ini!", makanya balik ke
            // karyawan asli (misal Dedi diganti Abdul, lalu mau balik ke Dedi) terasa gabisa.
            $vganti = "NULL";
            $tukar_new = 0; // downstream diperlakukan seperti tidak ganti
        } else {
            $tukar_err = '';
            $st_real = $koneksi->query("SELECT status_realisasi FROM tb_realisasi WHERE id_realisasi = '" . (int)$datadetail['id_realisasi'] . "'")->fetch_assoc();
            if ($st_real && (int)$st_real['status_realisasi'] >= 2) {
                $tukar_err = 'Realisasi sudah Approved, tidak bisa tukar karyawan!';
            } else {
                // Pengganti harus yang belum masuk rencana (pengaman server untuk opsi disabled di form).
                $mk = $koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE id_karyawan = '$tukar_new' AND status_karyawan = 'Aktif' LIMIT 1")->fetch_assoc();
                if (!$mk) {
                    $tukar_err = 'Karyawan pengganti tidak valid / nonaktif!';
                } else {
                    $dup = $koneksi->query("SELECT id_realisasi_detail FROM tb_realisasi_detail WHERE id_realisasi = '" . (int)$datadetail['id_realisasi'] . "' AND id_karyawan = '$tukar_new' AND id_realisasi_detail <> '$id' LIMIT 1");
                    if ($dup && $dup->num_rows > 0) $tukar_err = 'Karyawan ini sudah masuk rencana (terkunci)!';
                }
            }
            if ($tukar_err !== '') {
                echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
                    Swal.fire({ icon: "error", title: "Gagal Tukar", text: "' . addslashes($tukar_err) . ' Data tidak disimpan.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.history.back(); });
                </script></body></html>';
                exit;
            }
            $vganti = "'$tukar_new'";
            // Rincian borongan milik orang lama ikut dibuang
            $ckBor = $koneksi->query("SHOW TABLES LIKE 'tb_realisasi_borongan'");
            if ($ckBor && $ckBor->num_rows > 0) {
                $koneksi->query("DELETE FROM tb_realisasi_borongan WHERE id_realisasi_detail = '$id'");
            }
        }
    }
    // Logika Kehadiran & Upah Otomatis (Fix untuk Input Parsial)
    $is_total_missing_post = (empty($tjammasuk) || $tjammasuk == '00:00' || $tjammasuk == '00:00:00') &&
                             (empty($tjamkeluar) || $tjamkeluar == '00:00' || $tjamkeluar == '00:00:00');

    // Cek apakah pengganti adalah karyawan borongan
    $pengganti_borongan = false;
    if ($tukar_new > 0) {
        $q_bor = $koneksi->query("SELECT id_karyawan FROM ms_grup_borongan_anggota WHERE id_karyawan = '$tukar_new' LIMIT 1");
        if ($q_bor && $q_bor->num_rows > 0) $pengganti_borongan = true;
    }

    if ($is_total_missing_post) {
        if ($tukar_new > 0 && !$pengganti_borongan) {
            // Pengganti dikonfirmasi manual di Detail = dianggap hadir, jadi upah
            // JANGAN di-nol-kan. Sebelumnya cabang ini selalu $tupah = 0, makanya
            // "diganti kok upah kepotong" — apalagi di form ini semua jam masih
            // kosong (lihat screenshot: ABSEN MASUK/KELUAR --:--). Urutan patokan:
            // upah di form (biasanya upah RKK) > upah RKK > r_upah lama > upah master pengganti.
            // r_status tetap 'Hadir', bukan 'Digantikan': kolom r_status di DB yang jalan
            // hanya ENUM('Hadir','Tidak Hadir') — 'Digantikan' bikin fatal "Data truncated
            // for column 'r_status'". Label Digantikan di Kelola didapat dari id_pengganti.
            if ($tupah <= 0) {
                $tupah = !empty($datadetail['upah_master']) ? (float)$datadetail['upah_master'] : 0;
                if ($tupah <= 0 && $datadetail['r_upah'] > 0) $tupah = (float)$datadetail['r_upah'];
                if ($tupah <= 0) {
                    $q_peng_upah2 = $koneksi->query("SELECT upah FROM ms_karyawan WHERE id_karyawan = '$tukar_new' LIMIT 1");
                    $tupah = ($q_peng_upah2 && ($rp2 = $q_peng_upah2->fetch_assoc())) ? (float)$rp2['upah'] : 0;
                }
            }
            $tstatus_hadir = 'Hadir';
        } else {
            $tupah = 0;
            $tstatus_hadir = 'Tidak Hadir';
        }
    } else {
        $tstatus_hadir = 'Hadir';
        // Kembalikan ke upah awal jika upah POST adalah 0 (misal kena override JS saat initial load)
        if ($tupah <= 0) {
            // Hardcoded fallback inside the POST block too
            $def_upah_post = 115000;

            if ($pengganti_borongan) {
                $tupah = 0; // Borongan: upah dihitung dari qty x tarif, bukan gaji master
            } elseif ($tukar_new > 0) {
                // Pakai gaji master karyawan pengganti
                $q_peng_upah = $koneksi->query("SELECT upah FROM ms_karyawan WHERE id_karyawan = '$tukar_new' LIMIT 1");
                $tupah = ($q_peng_upah && ($rp = $q_peng_upah->fetch_assoc())) ? (float)$rp['upah'] : $def_upah_post;
            } else {
                $upah_ref = ($datadetail['r_upah'] > 0) ? (float)$datadetail['r_upah'] : 
                           (!empty($datadetail['upah_master']) ? (float)$datadetail['upah_master'] : $def_upah_post);
                $tupah = $upah_ref;
            }
        }
    }

    // Pengaman server untuk baris borongan (tanpa tukar): upah tetap 0 di sini,
    // berapapun nilai tupah yang dikirim, karena upah borongan ikut total borongan.
    // (Kalau ada tukar, rincian borongan sudah dihapus di atas, jadi bukan borongan lagi.)
    if ($tukar_new <= 0 && !empty($is_borongan_detail)) {
        $tupah = 0;
    }

    // 1. Ambil Pengaturan Denda Global
    $qGDenda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $dGDenda = $qGDenda->fetch_assoc();
    $gd_masuk = $dGDenda['denda_masuk'] ?? 0;
    $gd_istirahat_awal = $dGDenda['denda_istirahat_keluar'] ?? 0;
    $gd_istirahat_telat = $dGDenda['denda_istirahat_masuk'] ?? 0;
    $gd_pulang = $dGDenda['denda_pulang'] ?? 0;
    $gd_tidak_lengkap = $dGDenda['denda_tidak_lengkap'] ?? 0;

    // 2. Ambil data jadwal untuk snapshot & kalkulasi
    $qJadwal = $koneksi->query("SELECT * FROM tb_jadwal WHERE id_jadwal = '$tshift'");
    $dJadwal = $qJadwal->fetch_assoc();
    $s_masuk = $dJadwal['jam_masuk'] ?? '00:00:00';
    $s_keluar = $dJadwal['jam_keluar'] ?? '00:00:00';
    $s_ist_masuk = $dJadwal['istirahat_masuk'] ?? '00:00:00';
    $s_ist_keluar = $dJadwal['istirahat_keluar'] ?? '00:00:00';

    // 3. Rekalkulasi Denda Server-Side (Agar tidak stale dari form readonly)
    $p_telat = 0;
    $p_pulang = 0;
    $p_ist_awal = 0;
    $p_ist_telat = 0;
    $p_tidak_lengkap = 0;

    $has_masuk = !empty($tjammasuk) && $tjammasuk != '00:00:00';
    $has_keluar = !empty($tjamkeluar) && $tjamkeluar != '00:00:00';
    $has_ist_masuk = !empty($tistirahatmasuk) && $tistirahatmasuk != '00:00:00';
    $has_ist_keluar = !empty($tistirahatkeluar) && $tistirahatkeluar != '00:00:00';

    $is_flex_save = is_flexible_shift(($dJadwal['keterangan'] ?? '') !== '' ? $dJadwal['keterangan'] : ($datadetail['shift'] ?? ''));
    if ($has_masuk || $has_keluar) {
        // Late Entrance (lewat toleransi; flexi murni bebas)
        if (!$is_flex_save && $has_masuk && late_minutes($tjammasuk, $s_masuk) > $toleransi_d['masuk']) {
            $p_telat = $gd_masuk;
        }
        // Early Departure (lewat toleransi; flexi murni bebas)
        if (!$is_flex_save && $has_keluar && early_minutes($tjamkeluar, $s_keluar) > $toleransi_d['pulang']) {
            $p_pulang = $gd_pulang;
        }
        // Early Break (lewat toleransi; flexi murni bebas)
        if (!$is_flex_save && $has_ist_keluar && early_minutes($tistirahatkeluar, $s_ist_keluar) > $toleransi_d['ist_keluar']) {
            $p_ist_awal = $gd_istirahat_awal;
        }
        // Late Break Return (lewat toleransi; flexi murni bebas)
        if (!$is_flex_save && $has_ist_masuk && late_minutes($tistirahatmasuk, $s_ist_masuk) > $toleransi_d['ist_masuk']) {
            $p_ist_telat = $gd_istirahat_telat;
        }

        $shift_ket = $dJadwal['keterangan'] ?? '';
        $is_flexible = is_flexible_shift($shift_ket);
        $isRestExpected = (!$is_flexible && !empty($s_ist_keluar) && $s_ist_keluar != '00:00:00');
        $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));
        
        if ($hasIncompleteMain || $hasIncompleteBreak) {
            $p_tidak_lengkap = $gd_tidak_lengkap;
        }
    }

    $update = $koneksi->query("UPDATE tb_realisasi_detail SET 
        id_departmen = $bagian_id_save,
        id_jabatan = $posisi_id_save,
        id_pengganti = $vganti,
        r_upah = $tupah, 
        id_jadwal = $tshift,
        r_jam_masuk = '$s_masuk',
        r_jam_keluar = '$s_keluar',
        r_istirahat_masuk = '$s_ist_masuk',
        r_istirahat_keluar = '$s_ist_keluar',
        r_potongan_lainnya = $tpotlainnya,
        r_potongan_telat = $p_telat,
        r_potongan_istirahat_awal = $p_ist_awal,
        r_potongan_istirahat_telat = $p_ist_telat,
        r_potongan_pulang = $p_pulang,
        r_potongan_tidak_lengkap = $p_tidak_lengkap,
        ra_masuk = '$tjammasuk',
        ra_keluar = '$tjamkeluar',
        ra_istirahat_masuk = '$tistirahatmasuk',
        ra_istirahat_keluar = '$tistirahatkeluar',
        r_update = '$now',
        status_realisasi_detail = 1,
        hasil_kerja = '$hasilkerjanya',
        lembur = '$tlembur',
        lembur_jam = '$tlembur_jam',
        r_status = '$tstatus_hadir'
        WHERE id_realisasi_detail = '$id'");

    if ($update) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: "Data Berhasil Disimpan",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                }).then((result) => {
                    window.location.href = "?page=realisasi&aksi=detail&id=' . $id . '";
                });
            </script>
        </body>
        </html>';
        exit;
    }
}
?>

<!-- Dynamic Recalculation Script -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Dropdown Ganti Karyawan searchable (ketik di dalam dropdown). Fallback: input filter di atas bila select2 gagal dimuat.
    if ($.fn.select2) {
        $('#tukar_select').select2({ theme: 'bootstrap4', width: '100%', placeholder: '- Tidak ganti -', allowClear: true });
        $('#tukar_search').hide();
    }
    // 1. Data Jadwal & Denda Global dari PHP
    const shiftData = {
        <?php 
        $sqlJ = $koneksi->query("SELECT * FROM tb_jadwal");
        while ($j = $sqlJ->fetch_assoc()) {
            echo "'".$j['id_jadwal']."': {
                masuk: '".$j['jam_masuk']."',
                keluar: '".$j['jam_keluar']."',
                ist_masuk: '".$j['istirahat_masuk']."',
                ist_keluar: '".$j['istirahat_keluar']."',
                keterangan: '".$j['keterangan']."'
            },";
        }
        ?>
    };

    const dendaGlobal = {
        masuk: <?= (int)($globalDendaMasuk ?? 0) ?>,
        istAwal: <?= (int)($globalDendaIstirahatKeluar ?? 0) ?>,
        istTelat: <?= (int)($globalDendaIstirahatMasuk ?? 0) ?>,
        pulang: <?= (int)($globalDendaPulang ?? 0) ?>,
        tidakLengkap: <?= (int)($globalDendaTidakLengkap ?? 0) ?>
    };

    const lemburPerJam = <?= (int)($globalLemburPerJam ?? 0) ?>;
    const upahMaster = <?= (int)($datadetail['upah_master'] ?? 0) ?>;

    function timeToSeconds(time) {
        if (!time || time === '00:00:00') return 0;
        const parts = time.split(':');
        return parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + (parseInt(parts[2]) || 0);
    }

    // UPAH (POKOK) di Detail bisa diedit manual (2026-10-24): begitu user mengetik
    // di kolom tupah, nilai itu yang menang — calculate() tidak boleh menimpanya
    // lagi saat jam/shift/lembur diubah, supaya edit manual tidak hilang diam-diam.
    let upahManual = false;
    $('[name="tupah"]').on('input', function() { upahManual = true; calculate(); });

    function calculate() {
        const shiftId = $('[name="tshift"]').val();
        const jamMasuk = $('[name="tjammasuk"]').val();
        const jamKeluar = $('[name="tjamkeluar"]').val();
        const istMasuk = $('[name="tistirahatmasuk"]').val();
        const istKeluar = $('[name="tistirahatkeluar"]').val();
        const lemburJam = parseFloat($('[name="tlembur_jam"]').val()) || 0;
        const lembur = Math.round(lemburJam * lemburPerJam);
        $('[name="tlembur"]').val(lembur);

        const potLain = parseInt($('[name="tpotlainnya"]').val()) || 0;
        const recordStatus = <?= $datadetail['status_realisasi_detail'] ?>;

        let pTelat = 0, pPulang = 0, pIstAwal = 0, pIstTelat = 0, pTidakLengkap = 0;
        
        // Initial Wage Calculation logic (JS)
        const currentRUpah = <?= (float)($datadetail['r_upah'] ?? 0) ?>;
        let finalUpahPokok = (currentRUpah > 0) ? currentRUpah : upahMaster;
        if (upahManual) finalUpahPokok = parseFloat($('[name="tupah"]').val()) || 0;

        const shift = shiftData[shiftId];
        const isFlexible = (shift && (shift.keterangan.toUpperCase().indexOf('FLEXI') !== -1 || shift.keterangan.toUpperCase().indexOf('FLEKSI') !== -1));
        let expectedHours = 7;
        if (isFlexible) {
            let match = shift.keterangan.match(/(\d+)\s*JAM/i);
            expectedHours = match ? parseInt(match[1]) : 7;
        }

        const hasMasuk = (jamMasuk && jamMasuk !== '00:00:00');
        const hasKeluar = (jamKeluar && jamKeluar !== '00:00:00');
        const hasIstMasuk = (istMasuk && istMasuk !== '00:00:00');
        const hasIstKeluar = (istKeluar && istKeluar !== '00:00:00');
        const isTotalMissing = (!hasMasuk && !hasKeluar);

        let attStatus = 'HADIR';
        let badgeClass = 'bg-green-100 text-green-800';

        if (isTotalMissing) {
            if (recordStatus == 0) {
                attStatus = 'HADIR';
                badgeClass = 'bg-green-100 text-green-800';
            } else {
                attStatus = 'TIDAK HADIR';
                badgeClass = 'bg-red-100 text-red-800';
            }
        }

        // Update Header Badge
        $('#attendance-badge').text('STATUS: ' + attStatus).attr('class', 'ml-4 px-3 py-1 rounded-full text-[12px] font-bold ' + badgeClass);

        // Special case for Draft (Status 0): Dynamic penalties ONLY shown if jam is filled manually
        if (recordStatus == 0) {
            if (isTotalMissing && !upahManual) {
                pTelat = 0; pPulang = 0; pIstAwal = 0; pIstTelat = 0; pTidakLengkap = 0;
                finalUpahPokok = upahMaster;
            } else if (isTotalMissing) {
                pTelat = 0; pPulang = 0; pIstAwal = 0; pIstTelat = 0; pTidakLengkap = 0;
            } else {
                // Manual input in draft - show what will happen
                if (shift) {
                    if (isFlexible) {
                        // Flexi murni: bebas jam (hanya tidak-lengkap bila salah satu kosong)
                        if (hasMasuk !== hasKeluar) pTidakLengkap = dendaGlobal.tidakLengkap;
                    } else {
                        if (hasMasuk && shift.masuk !== '00:00:00' && timeToSeconds(jamMasuk) > timeToSeconds(shift.masuk)) pTelat = dendaGlobal.masuk;
                        if (hasKeluar && shift.keluar !== '00:00:00' && timeToSeconds(jamKeluar) < timeToSeconds(shift.keluar)) pPulang = dendaGlobal.pulang;
                        if (hasIstKeluar && shift.ist_keluar !== '00:00:00' && timeToSeconds(istKeluar) < timeToSeconds(shift.ist_keluar)) pIstAwal = dendaGlobal.istAwal;
                        if (hasIstMasuk && shift.ist_masuk !== '00:00:00' && timeToSeconds(istMasuk) > timeToSeconds(shift.ist_masuk)) pIstTelat = dendaGlobal.istTelat;
                        const hasIncompleteMain = (!hasMasuk || !hasKeluar);
                        const isRestExpected = (shift.ist_keluar !== '00:00:00');
                        const hasIncompleteBreak = (isRestExpected && (!hasIstMasuk || !hasIstKeluar));
                        if (hasIncompleteMain || hasIncompleteBreak) pTidakLengkap = dendaGlobal.tidakLengkap;
                    }
                }
            }
        } else if (attStatus !== 'HADIR' && !upahManual) { // This means TIDAK HADIR after sync
            finalUpahPokok = 0;
            pTelat = 0; pPulang = 0; pIstAwal = 0; pIstTelat = 0; pTidakLengkap = 0;
        } else if (attStatus !== 'HADIR') {
            pTelat = 0; pPulang = 0; pIstAwal = 0; pIstTelat = 0; pTidakLengkap = 0;
        } else {
            // Normal calculation for HADIR (Status > 0)
            if (shift) {
                if (isFlexible) {
                    // Flexi murni: bebas jam (hanya tidak-lengkap bila salah satu kosong)
                    if (hasMasuk !== hasKeluar) pTidakLengkap = dendaGlobal.tidakLengkap;
                } else {
                    if (hasMasuk && shift.masuk !== '00:00:00' && timeToSeconds(jamMasuk) > timeToSeconds(shift.masuk)) pTelat = dendaGlobal.masuk;
                    if (hasKeluar && shift.keluar !== '00:00:00' && timeToSeconds(jamKeluar) < timeToSeconds(shift.keluar)) pPulang = dendaGlobal.pulang;
                    if (hasIstKeluar && shift.ist_keluar !== '00:00:00' && timeToSeconds(istKeluar) < timeToSeconds(shift.ist_keluar)) pIstAwal = dendaGlobal.istAwal;
                    if (hasIstMasuk && shift.ist_masuk !== '00:00:00' && timeToSeconds(istMasuk) > timeToSeconds(shift.ist_masuk)) pIstTelat = dendaGlobal.istTelat;
                    const hasIncompleteMain = (!hasMasuk || !hasKeluar);
                    const isRestExpected = (shift.ist_keluar !== '00:00:00');
                    const hasIncompleteBreak = (isRestExpected && (!hasIstMasuk || !hasIstKeluar));
                    if (hasIncompleteMain || hasIncompleteBreak) pTidakLengkap = dendaGlobal.tidakLengkap;
                }
            }
        }


        // Update fields (tupah tidak ditimpa kalau sudah diedit manual)
        if (!upahManual) $('[name="tupah"]').val(finalUpahPokok);
        $('[name="tpottelat"]').val(pTelat);
        $('[name="tpotistirahatkeluar"]').val(pIstAwal);
        $('[name="tpotistirahatmasuk"]').val(pIstTelat);
        $('[name="tpotpulang"]').val(pPulang);
        $('[name="tpotlog"]').val(pTidakLengkap);

        const total = finalUpahPokok + lembur - pTelat - pIstAwal - pIstTelat - pPulang - pTidakLengkap - potLain;
        // Use PHP's rupiah format or similar in JS
        const totalFmt = "Rp " + total.toLocaleString('id-ID');
        $('[style*="background: #eff6ff"]').val(totalFmt);
    }

    // Lembur flexi diisi manual (tanpa target jam otomatis).

    $('[name="tshift"], [name="tjammasuk"], [name="tjamkeluar"], [name="tistirahatmasuk"], [name="tistirahatkeluar"], [name="tlembur_jam"], [name="tpotlainnya"]').on('change input', calculate);
});
</script>
</body>
</html>