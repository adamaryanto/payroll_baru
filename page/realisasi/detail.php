<?php
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
                           SD.nama_sub_department,
                           D2.nama_departmen as bagian_manual_str,
                           SD2.nama_sub_department as sub_bagian_manual_str,
                           RD.upah as upah_master
                    FROM tb_realisasi_detail A 
                    LEFT JOIN tb_realisasi B ON A.id_realisasi = B.id_realisasi
                    LEFT JOIN tb_jadwal C ON A.id_jadwal = C.id_jadwal
                    LEFT JOIN ms_karyawan BB ON A.id_karyawan = BB.id_karyawan
                    LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
                    /* Mengambil data Departemen & Sub-Dept langsung dari master karyawan */
                    LEFT JOIN ms_departmen D ON BB.id_departmen = D.id_departmen
                    LEFT JOIN ms_sub_department SD ON BB.id_sub_department = SD.id_sub_department
                    /* Mengambil data Departemen & Sub-Dept untuk karyawan manual */
                    LEFT JOIN ms_departmen D2 ON A.id_departmen = D2.id_departmen
                    LEFT JOIN ms_sub_department SD2 ON A.id_sub_department = SD2.id_sub_department
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

    // Query Pengaturan Denda Global
    $queryDenda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $dataDenda = $queryDenda->fetch_assoc();
    $globalDendaMasuk = $dataDenda['denda_masuk'] ?? 0;
    $globalDendaIstirahatKeluar = $dataDenda['denda_istirahat_keluar'] ?? 0;
    $globalDendaIstirahatMasuk = $dataDenda['denda_istirahat_masuk'] ?? 0;
    $globalDendaPulang = $dataDenda['denda_pulang'] ?? 0;
    $globalDendaTidakLengkap = $dataDenda['denda_tidak_lengkap'] ?? 0;

    // Get Global Daily Wage as fallback for manual employees (Hardcoded fallback)
    $default_upah = 115000;

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

    $isTotalMissing = (!$has_masuk && !$has_keluar);
    $hasIncompleteMain = ($has_masuk XOR $has_keluar);
    $isRestExpected = (!empty($datadetail['shift_istirahat_keluar']));
    $hasIncompleteBreak = ($isRestExpected && ($has_ist_keluar XOR $has_ist_masuk));
    
    $isLate = ($has_masuk && !empty($datadetail['shift_masuk']) && is_time_late($jamMasukRealisasi, $datadetail['shift_masuk']));
    $isEarlyOut = ($has_keluar && !empty($datadetail['shift_keluar']) && is_time_early($jamKeluarRealisasi, $datadetail['shift_keluar'])); 
    $isLateBreak = ($has_ist_masuk && !empty($datadetail['shift_istirahat_masuk']) && is_time_late($jamIstirahatMasukRealisasi, $datadetail['shift_istirahat_masuk']));
    $isEarlyBreak = ($has_ist_keluar && !empty($datadetail['shift_istirahat_keluar']) && is_time_early($jamIstirahatKeluarRealisasi, $datadetail['shift_istirahat_keluar']));

    // Logic denda: Kalkulasi otomatis berdasarkan jam hadir/jadwal
    $hasilpotonganpulang = ($isEarlyOut && !$isTotalMissing) ? $globalDendaPulang : 0;
    $hasilpotongantidaklengkap = (!$isTotalMissing && ($hasIncompleteMain || $hasIncompleteBreak)) ? $globalDendaTidakLengkap : 0;
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
        $tlembur = 0;
    }

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
                        <input type="text" readonly value="<?= !empty($datadetail['nama_departmen']) ? $datadetail['nama_departmen'] : ($datadetail['bagian_manual_str'] ?? '') ?>" class="form-control" style="background: #f9fafb;">
                    </div>
                    <div class="form-group col-md-3">
                        <label>SUB BAGIAN</label>
                        <input type="text" readonly value="<?= !empty($datadetail['nama_sub_department']) ? $datadetail['nama_sub_department'] : ($datadetail['sub_bagian_manual_str'] ?? '') ?>" class="form-control" style="background: #f9fafb;">
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
                        <select class="form-control" name="tshift" required>
                            <option value="<?= $datadetail['id_jadwal'] ?>"><?= $datadetail['shift'] ?></option>
                            <?php 
                            $sqlJadwal = $koneksi->query("SELECT * FROM tb_jadwal");
                            while ($j = $sqlJadwal->fetch_assoc()) {
                                echo "<option value='".$j['id_jadwal']."'>".$j['keterangan']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-2"> <label>ABSEN MASUK</label> <input type="time" name="tjammasuk" value="<?= (empty($datadetail['ra_masuk']) || $datadetail['ra_masuk'] == '00:00:00') ? '' : $datadetail['ra_masuk'] ?>" class="form-control"> </div>
                    <div class="form-group col-md-2"> <label>ABSEN KELUAR</label> <input type="time" name="tjamkeluar" value="<?= (empty($datadetail['ra_keluar']) || $datadetail['ra_keluar'] == '00:00:00') ? '' : $datadetail['ra_keluar'] ?>" class="form-control"> </div>
                    <div class="form-group col-md-2"> <label>ISTIRAHAT MASUK</label> <input type="time" name="tistirahatmasuk" value="<?= (empty($datadetail['ra_istirahat_masuk']) || $datadetail['ra_istirahat_masuk'] == '00:00:00') ? '' : $datadetail['ra_istirahat_masuk'] ?>" class="form-control"> </div>
                    <div class="form-group col-md-2"> <label>ISTIRAHAT KELUAR</label> <input type="time" name="tistirahatkeluar" value="<?= (empty($datadetail['ra_istirahat_keluar']) || $datadetail['ra_istirahat_keluar'] == '00:00:00') ? '' : $datadetail['ra_istirahat_keluar'] ?>" class="form-control"> </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-2"> <label>UPAH (POKOK)</label> <input type="number" name="tupah" value="<?= $upahPokokTampil ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. TELAT</label> <input type="number" name="tpottelat" value="<?= $hasilpotongantelat ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. IST (AWAL)</label> <input type="number" name="tpotistirahatkeluar" value="<?= $hasilpotonganistirahatkeluar ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. IST (TELAT)</label> <input type="number" name="tpotistirahatmasuk" value="<?= $hasilpotonganistirahatmasuk ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. PULANG AWAL</label> <input type="number" name="tpotpulang" value="<?= $hasilpotonganpulang ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                    <div class="form-group col-md-2"> <label>POT. ABSEN TIDAK LENGKAP</label> <input type="number" name="tpotlog" value="<?= $hasilpotongantidaklengkap ?>" class="form-control" readonly style="background: #f9fafb;"> </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-2"> <label>POT. LAINNYA</label> <input type="number" name="tpotlainnya" value="<?= $datadetail['r_potongan_lainnya'] ?>" class="form-control" required> </div>
                    <div class="form-group col-md-2"> <label>LEMBUR</label> <input type="number" name="tlembur" value="<?= $datadetail['lembur'] ?>" class="form-control" required> </div>
                    <div class="form-group col-md-2"> 
                        <label>TOTAL UPAH AKHIR</label> 
                        <?php $totalAkhir = $upahPokokTampil + $datadetail['lembur'] - $hasilpotongantelat - $hasilpotonganistirahatkeluar - $hasilpotonganistirahatmasuk - $datadetail['r_potongan_lainnya'] - $hasilpotonganpulang - $hasilpotongantidaklengkap; ?>
                        <input type="text" value="<?= rupiah($totalAkhir) ?>" class="form-control font-bold text-blue-600" readonly style="background: #eff6ff;"> 
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-12">
                        <label>HASIL KERJA / KETERANGAN DETAIL</label>
                        <textarea name="thasilkerja" class="form-control" rows="3"><?= $datadetail['hasil_kerja'] ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f3f4f6;">
                    <a href="?page=realisasi&aksi=kelola&id=<?= $dataidrealisasi ?>" class="btn btn-warning-custom btn-custom">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>
                    <button type="submit" name="simpan" class="btn btn-primary-custom btn-custom">
                        <i class="fa fa-save"></i> Simpan Data Realisasi
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
if (isset($_POST['simpan'])) {
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
    $tlembur = (float)($_POST['tlembur'] ?? 0);
    // Logika Kehadiran & Upah Otomatis (Fix untuk Input Parsial)
    $is_total_missing_post = (empty($tjammasuk) || $tjammasuk == '00:00' || $tjammasuk == '00:00:00') &&
                             (empty($tjamkeluar) || $tjamkeluar == '00:00' || $tjamkeluar == '00:00:00');

    if ($is_total_missing_post) {
        $tupah = 0;
        $tstatus_hadir = 'Tidak Hadir';
    } else {
        $tstatus_hadir = 'Hadir';
        // Kembalikan ke upah awal jika upah POST adalah 0 (misal kena override JS saat initial load)
        if ($tupah <= 0) {
            // Hardcoded fallback inside the POST block too
            $def_upah_post = 115000;

            $upah_ref = ($datadetail['r_upah'] > 0) ? (float)$datadetail['r_upah'] : 
                       (!empty($datadetail['upah_master']) ? (float)$datadetail['upah_master'] : $def_upah_post);
            $tupah = $upah_ref;
        }
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

    if ($has_masuk || $has_keluar) {
        // Late Entrance
        if ($has_masuk && is_time_late($tjammasuk, $s_masuk)) {
            $p_telat = $gd_masuk;
        }
        // Early Departure
        if ($has_keluar && is_time_early($tjamkeluar, $s_keluar)) {
            $p_pulang = $gd_pulang;
        }
        // Early Break
        if ($has_ist_keluar && is_time_early($tistirahatkeluar, $s_ist_keluar)) {
            $p_ist_awal = $gd_istirahat_awal;
        }
        // Late Break Return
        if ($has_ist_masuk && is_time_late($tistirahatmasuk, $s_ist_masuk)) {
            $p_ist_telat = $gd_istirahat_telat;
        }

        // Incomplete Log
        $hasIncompleteMain = ($has_masuk XOR $has_keluar);
        $isRestExpected = (!empty($s_ist_keluar));
        $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));
        
        if ($hasIncompleteMain || $hasIncompleteBreak) {
            $p_tidak_lengkap = $gd_tidak_lengkap;
        }
    }

    $update = $koneksi->query("UPDATE tb_realisasi_detail SET 
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
    // 1. Data Jadwal & Denda Global dari PHP
    const shiftData = {
        <?php 
        $sqlJ = $koneksi->query("SELECT * FROM tb_jadwal");
        while ($j = $sqlJ->fetch_assoc()) {
            echo "'".$j['id_jadwal']."': {
                masuk: '".$j['jam_masuk']."',
                keluar: '".$j['jam_keluar']."',
                ist_masuk: '".$j['istirahat_masuk']."',
                ist_keluar: '".$j['istirahat_keluar']."'
            },";
        }
        ?>
    };

    const dendaGlobal = {
        masuk: <?= (int)($gd_masuk ?? 0) ?>,
        istAwal: <?= (int)($gd_istirahat_awal ?? 0) ?>,
        istTelat: <?= (int)($gd_istirahat_telat ?? 0) ?>,
        pulang: <?= (int)($gd_pulang ?? 0) ?>,
        tidakLengkap: <?= (int)($gd_tidak_lengkap ?? 0) ?>
    };

    const upahMaster = <?= (int)($datadetail['upah_master'] ?? 0) ?>;

    function timeToSeconds(time) {
        if (!time || time === '00:00:00') return 0;
        const parts = time.split(':');
        return parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + (parseInt(parts[2]) || 0);
    }

    function calculate() {
        const shiftId = $('[name="tshift"]').val();
        const jamMasuk = $('[name="tjammasuk"]').val();
        const jamKeluar = $('[name="tjamkeluar"]').val();
        const istMasuk = $('[name="tistirahatmasuk"]').val();
        const istKeluar = $('[name="tistirahatkeluar"]').val();
        const lembur = parseInt($('[name="tlembur"]').val()) || 0;
        const potLain = parseInt($('[name="tpotlainnya"]').val()) || 0;
        const recordStatus = <?= $datadetail['status_realisasi_detail'] ?>;

        let pTelat = 0, pPulang = 0, pIstAwal = 0, pIstTelat = 0, pTidakLengkap = 0;
        
        // Initial Wage Calculation logic (JS)
        const currentRUpah = <?= (float)($datadetail['r_upah'] ?? 0) ?>;
        let finalUpahPokok = (currentRUpah > 0) ? currentRUpah : upahMaster;

        const shift = shiftData[shiftId];
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
            if (isTotalMissing) {
                pTelat = 0; pPulang = 0; pIstAwal = 0; pIstTelat = 0; pTidakLengkap = 0;
                finalUpahPokok = upahMaster;
            } else {
                // Manual input in draft - show what will happen
                if (shift) {
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
        } else if (attStatus !== 'HADIR') { // This means TIDAK HADIR after sync
            finalUpahPokok = 0;
            pTelat = 0; pPulang = 0; pIstAwal = 0; pIstTelat = 0; pTidakLengkap = 0;
        } else {
            // Normal calculation for HADIR (Status > 0)
            if (shift) {
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


        // Update fields
        $('[name="tupah"]').val(finalUpahPokok);
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

    $('[name="tshift"], [name="tjammasuk"], [name="tjamkeluar"], [name="tistirahatmasuk"], [name="tistirahatkeluar"], [name="tlembur"], [name="tpotlainnya"]').on('change input', calculate);
});
</script>