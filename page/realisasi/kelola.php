<?php
// Self-heal master grup + kolom id_grup (penting di DB lama sebelum query utama jalan)
include_once __DIR__ . '/../grup_borongan/_setup.php';
// Kolom pengganti (tukar karyawan: nama tetap, tercatat "Digantikan oleh ...")
$checkGantiColK = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_pengganti'");
if ($checkGantiColK->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_detail ADD COLUMN id_pengganti INT NULL DEFAULT NULL");
}
// Helpers for cross-midnight time comparisons
if (!function_exists('is_time_late')) {
    function is_time_late($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return false;
        $diff = strtotime($actual) - strtotime($expected);
        if ($diff > 43200) $diff -= 86400;
        elseif ($diff < -43200) $diff += 86400;
        return $diff > 0;
    }
}
if (!function_exists('is_time_early')) {
    function is_time_early($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return false;
        $diff = strtotime($actual) - strtotime($expected);
        if ($diff > 43200) $diff -= 86400;
        elseif ($diff < -43200) $diff += 86400;
        return $diff < 0;
    }
}

if (isset($_GET['id'])) {
    $idrealisasi = $_GET['id'];

    $tampildetail = $koneksi->query("SELECT * FROM tb_realisasi WHERE id_realisasi = '$idrealisasi'");
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
                    text: "Data Realisasi tidak ditemukan!",
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

    $datatglrealisasi    = $datadetail['tgl_realisasi'];
    $dataketerangan      = $datadetail['keterangan'];
    $datadetailrealisasi = $datadetail['detail_realisasi'];
    $datajamkerja        = $datadetail['jam_kerja'];
    $datastatusrealisasi = (int)$datadetail['status_realisasi'];
    $idrkk               = $datadetail['id_rkk'];

    $tampil = $koneksi->query("SELECT 
        A.*, 
        A.r_upah as upahkaryawan, 
        -- Jika id_karyawan 0, tampilkan '-', jika tidak tampilkan no_absen asli
        IF(A.id_karyawan = 0, '-', B.no_absen) as no_absen_tampil, 
        -- Jika id_karyawan 0, ambil dari kolom manual, jika tidak ambil dari master
        IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_tampil,
        -- Prioritaskan departmen dari detail (penting untuk manual employee)
        COALESCE(D.nama_departmen, D_Manual.nama_departmen) as nama_departmen,
        COALESCE(BB.nama_sub_department, BB_Manual.nama_sub_department) as nama_sub_department,
        C.tgl_realisasi, 
        O.OS_DHK as label_os,
        G.golongan as label_gol,
        RD.status_rkk,
        GB.nama_grup as label_grup,
        GA.is_borongan as is_borongan_orang,
        GB.is_borongan as is_borongan_grup,
        GA_PG.is_borongan as is_borongan_pengganti,
        RD.upah as upah_rkk,
        J.jam_masuk as shift_masuk,
        J.jam_keluar as shift_keluar,
        J.istirahat_masuk as shift_istirahat_masuk,
        J.istirahat_keluar as shift_istirahat_keluar,
        J.keterangan as shift_ket,
        (SELECT K3.nama_karyawan 
         FROM tb_rkk_update U1 
         JOIN tb_rkk_update U2 ON U1.id_rkk_detail = U2.id_rkk_detail 
         JOIN ms_karyawan K3 ON U2.id_karyawan = K3.id_karyawan 
         JOIN tb_rkk_detail RD2 ON U1.id_rkk_detail = RD2.id_rkk_detail
         WHERE U1.id_karyawan = A.id_karyawan 
         AND U1.status = 'Pengganti' 
         AND U2.status = 'Digantikan' 
         AND RD2.id_rkk = A.id_rkk
         LIMIT 1) as menggantikan,
        (SELECT K4.nama_karyawan 
         FROM tb_rkk_update U3 
         JOIN tb_rkk_update U4 ON U3.id_rkk_detail = U4.id_rkk_detail 
         JOIN ms_karyawan K4 ON U4.id_karyawan = K4.id_karyawan 
         WHERE U3.id_rkk_detail = A.id_rkk_detail 
         AND U3.status = 'Digantikan' 
         AND U4.status = 'Pengganti' 
         LIMIT 1) as digantikan_oleh,
        PG.nama_karyawan as pengganti_langsung
        FROM tb_realisasi_detail A 
        LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
        LEFT JOIN ms_karyawan PG ON A.id_pengganti = PG.id_karyawan
        LEFT JOIN tb_realisasi C ON A.id_realisasi = C.id_realisasi
        -- Join ke master departmen dari karyawan master
        LEFT JOIN ms_departmen D ON B.id_departmen = D.id_departmen
        LEFT JOIN ms_sub_department BB ON B.id_sub_department = BB.id_sub_department
        -- Join ke master departmen dari kolom manual di detail (untuk manual employee)
        LEFT JOIN ms_departmen D_Manual ON A.id_departmen = D_Manual.id_departmen
        LEFT JOIN ms_sub_department BB_Manual ON A.id_sub_department = BB_Manual.id_sub_department
        LEFT JOIN ms_os_dhk O ON B.id_os_dhk = O.id_os_dhk
        LEFT JOIN ms_golongan G ON B.id_golongan = G.id_golongan
        LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
        LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal
        LEFT JOIN ms_grup_borongan GB ON A.id_grup = GB.id_grup
        LEFT JOIN ms_grup_borongan_anggota GA ON GA.id_grup = A.id_grup AND GA.id_karyawan = A.id_karyawan AND A.id_karyawan <> 0
        LEFT JOIN ms_grup_borongan_anggota GA_PG ON GA_PG.id_karyawan = A.id_pengganti AND A.id_pengganti <> 0
        LEFT JOIN (SELECT id_grup, MIN(id_realisasi_detail) AS g_ord FROM tb_realisasi_detail WHERE id_realisasi = '$idrealisasi' AND id_grup IS NOT NULL GROUP BY id_grup) GO ON GO.id_grup = A.id_grup
        WHERE A.id_realisasi = '$idrealisasi'
        ORDER BY (GB.nama_grup IS NULL), GO.g_ord ASC, A.id_realisasi_detail ASC
    ");

    $tampilrkk = $koneksi->query("SELECT * FROM tb_rkk WHERE id_rkk = '$idrkk'");
    $datarkk = $tampilrkk->fetch_assoc();

    if (!$datarkk) {
        $datatglrkk = "";
        $dataketeranganrkk = "";
        $datajamkerjarkk = "";
    } else {
        $datatglrkk = $datarkk['tgl_rkk'];
        $dataketeranganrkk = $datarkk['keterangan'];
        $datajamkerjarkk = $datarkk['jam_kerja'];
    }

    // Ambil Data Denda Global
    $queryDenda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $dataDenda = $queryDenda->fetch_assoc();
    $globalDendaMasuk = $dataDenda['denda_masuk'] ?? 0;
    $globalDendaIstirahatKeluar = $dataDenda['denda_istirahat_keluar'] ?? 0;
    $globalDendaIstirahatMasuk = $dataDenda['denda_istirahat_masuk'] ?? 0;
    $globalDendaPulang = $dataDenda['denda_pulang'] ?? 0;
    $globalDendaTidakLengkap = $dataDenda['denda_tidak_lengkap'] ?? 0;
} else {
    $datatglrealisasi    = "";
    $dataketerangan      = "";
    $datadetailrealisasi = "";
    $datajamkerja        = "";
    $datastatusrealisasi = 0;
}

if ($datastatusrealisasi >= 2) {
    if ($_SESSION['role'] != "owner") {
        $status = "hidden";
    } else {
        $status = "";
    }
} else {
    $status = "";
}

$simpan = @$_POST['simpan'];
if ($simpan) {
    $tketerangan = @$_POST['tketerangan'];

    // 1. Update Keterangan
    $sql = $koneksi->query("UPDATE tb_realisasi SET keterangan = '$tketerangan' WHERE id_realisasi = '$idrealisasi'");

    // 2. Bulk Save Penalties (Calculate & Persist)
    $count_denda = bulkSavePenalties($koneksi, $idrealisasi);

    if ($sql) {
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
                    text: "Keterangan tersimpan dan denda untuk ' . $count_denda . ' karyawan telah dikalkulasi.",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                }).then((result) => {
                    window.location.href = "?page=realisasi&aksi=kelola&id=' . $idrealisasi . '";
                });
            </script>
        </body>
        </html>';
        exit;
    }
}

$cleanup = @$_POST['cleanup'];
if ($cleanup) {
    // 0. Tarik dulu dari semua mesin (SISTEM SAMA dgn halaman tarik data) -> masuk tb_record,
    //    jadi halaman tarik data ikut kebagian tanpa kerja 2x. Pull ini idempotent (dobel diabaikan).
    if (!function_exists('pull_attendance_from_all_devices')) {
        include_once __DIR__ . '/../../device_sync_helper.php';
    }
    $pull_info = '';
    if (function_exists('pull_attendance_from_all_devices')) {
        $qr = $koneksi->query("SELECT tgl_realisasi FROM tb_realisasi WHERE id_realisasi = '$idrealisasi'");
        $dr = $qr ? $qr->fetch_assoc() : null;
        if ($dr && !empty($dr['tgl_realisasi'])) {
            $t0 = $dr['tgl_realisasi'];
            $t1 = date('Y-m-d', strtotime('+1 day', strtotime($t0))); // shift malam lewat tengah malam
            $pr = pull_attendance_from_all_devices($koneksi, $t0, $t1);
            if (function_exists('log_sync_activity')) {
                foreach (($pr['per_device'] ?? []) as $dev_id => $rs) {
                    if (!empty($rs['online'])) {
                        log_sync_activity($koneksi, 'attendance_pull', $dev_id, null, 'success', "Pulled {$rs['records_pulled']} records, saved {$rs['records_saved']} (via realisasi)");
                    }
                }
            }
            if ((int)($pr['online_devices'] ?? 0) === 0) {
                $pull_info = 'Tarik mesin: semua mesin offline, pakai data record yang sudah ada. ';
            } else {
                $pull_info = 'Tarik mesin: ' . (int)($pr['records_saved'] ?? 0) . ' baru dari ' . (int)$pr['online_devices'] . '/' . (int)$pr['total_devices'] . ' mesin. ';
            }
        }
    }
    $count = syncRealisasiData($koneksi, $idrealisasi);
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
                    text: "' . addslashes($pull_info) . 'Berhasil menarik ' . $count . ' data dari record mesin.",
                confirmButtonColor: "#2563eb",
                confirmButtonText: "OK"
            }).then((result) => {
                window.location.href = "?page=realisasi&aksi=kelola&id=' . $idrealisasi . '";
            });
        </script>
    </body>
    </html>';
    exit;
}

$pull_unknown = @$_POST['pull_unknown'];
if ($pull_unknown && !empty($idrealisasi)) {
    // 0. Tarik dulu dari semua mesin agar tap terbaru ikut (sama kayak Tarik Data)
    if (!function_exists('pull_attendance_from_all_devices')) {
        include_once __DIR__ . '/../../device_sync_helper.php';
    }
    $qr0 = $koneksi->query("SELECT tgl_realisasi, id_rkk FROM tb_realisasi WHERE id_realisasi = '$idrealisasi'");
    $dr0 = $qr0 ? $qr0->fetch_assoc() : null;
    $tgl_real = $dr0['tgl_realisasi'] ?? '';
    if ($tgl_real !== '' && function_exists('pull_attendance_from_all_devices')) {
        $tgl_next = date('Y-m-d', strtotime('+1 day', strtotime($tgl_real)));
        pull_attendance_from_all_devices($koneksi, $tgl_real, $tgl_next);
    } else {
        $tgl_next = $tgl_real;
    }
    // 1. Pastikan grup penampung ada
    $gid_tdr = 0;
    $qg0 = $koneksi->query("SELECT id_grup FROM ms_grup_borongan WHERE UPPER(TRIM(nama_grup)) = 'TIDAK DALAM RENCANA' LIMIT 1");
    if ($qg0 && ($gg0 = $qg0->fetch_assoc())) $gid_tdr = (int)$gg0['id_grup'];
    if ($gid_tdr <= 0 && $koneksi->query("INSERT INTO ms_grup_borongan (nama_grup, keterangan, tgl_updt) VALUES ('TIDAK DALAM RENCANA', 'Otomatis: penampung tap tanpa master', NOW())")) $gid_tdr = (int)$koneksi->insert_id;
    // 2. Nama dari mesin
    $devmap = [];
    if (function_exists('get_device_user_names')) $devmap = get_device_user_names($koneksi);
    // 3. PIN tak terdaftar pada tanggal ini
    $pins = [];
    if ($tgl_real !== '' && $gid_tdr > 0) {
        $te = $koneksi->real_escape_string($tgl_real);
        $qp = $koneksi->query("SELECT DISTINCT TRIM(userid) AS pin FROM tb_record WHERE tgl = '$te' AND TRIM(userid) <> '' AND NOT EXISTS (SELECT 1 FROM ms_karyawan k WHERE k.no_absen IS NOT NULL AND TRIM(k.no_absen) <> '' AND TRIM(k.no_absen) = TRIM(tb_record.userid))");
        while ($qp && ($rp = $qp->fetch_assoc())) $pins[] = $rp['pin'];
    }
    // 4. Nama yang sudah jadi baris tak-terdaftar (jangan dobel bila tombol ditekan ulang)
    $exist_names = [];
    if ($gid_tdr > 0) {
        $qe = $koneksi->query("SELECT nama_karyawan_manual FROM tb_realisasi_detail WHERE id_realisasi = '$idrealisasi' AND id_karyawan = 0 AND id_grup = '$gid_tdr'");
        while ($qe && ($re = $qe->fetch_assoc())) $exist_names[trim((string)$re['nama_karyawan_manual'])] = true;
    }
    $added = 0;
    $now_tu = date('Y-m-d H:i:s');
    $idrkk_ref = (int)($dr0['id_rkk'] ?? 0);
    foreach ($pins as $pin) {
        $pin_esc = $koneksi->real_escape_string($pin);
        $disp = trim((string)($devmap[$pin] ?? ''));
        if ($disp === '') $disp = 'PIN ' . $pin;
        if (isset($exist_names[$disp])) continue;
        // Ambil jam tap: masuk = scan pertama, keluar = scan terakhir dalam 16 jam
        $ra_masuk = '00:00:00'; $ra_keluar = '00:00:00'; $ra_ik = '00:00:00'; $ra_im = '00:00:00';
        $ql = $koneksi->query("SELECT status, detail_waktu FROM tb_record WHERE TRIM(userid) = '$pin_esc' AND tgl IN ('$tgl_real', '$tgl_next') ORDER BY detail_waktu ASC");
        $logs = [];
        while ($ql && ($rl = $ql->fetch_assoc())) $logs[] = $rl;
        if (!empty($logs)) {
            $masuk_time = '';
            foreach ($logs as $l) {
                if (substr($l['detail_waktu'], 0, 10) == $tgl_real && (int)$l['status'] === 0) { $masuk_time = substr($l['detail_waktu'], 11, 8); break; }
            }
            if ($masuk_time === '') $masuk_time = substr($logs[0]['detail_waktu'], 11, 8);
            $in_ts = strtotime($tgl_real . ' ' . $masuk_time);
            foreach ($logs as $l) {
                $ct = substr($l['detail_waktu'], 11, 8);
                $cts = strtotime(substr($l['detail_waktu'], 0, 10) . ' ' . $ct);
                $dh = ($cts - $in_ts) / 3600;
                if ($dh > 0 && $dh <= 16) {
                    if ((int)$l['status'] === 1) $ra_keluar = $ct;
                    elseif ((int)$l['status'] === 2 && $ra_ik === '00:00:00') $ra_ik = $ct;
                    elseif ((int)$l['status'] === 3 && $ra_im === '00:00:00') $ra_im = $ct;
                }
            }
            if ($ra_keluar === '00:00:00') {
                for ($ix = count($logs) - 1; $ix >= 0; $ix--) {
                    $ct = substr($logs[$ix]['detail_waktu'], 11, 8);
                    if (substr($logs[$ix]['detail_waktu'], 0, 10) == $tgl_real && $ct == $masuk_time) continue;
                    $cts = strtotime($logs[$ix]['detail_waktu']);
                    $dh = ($cts - $in_ts) / 3600;
                    if ($dh > 0 && $dh <= 16) { $ra_keluar = $ct; break; }
                }
            }
            $ra_masuk = $masuk_time;
        }
        $nm_esc = $koneksi->real_escape_string($disp);
        $ok = $koneksi->query("INSERT INTO tb_realisasi_detail (id_realisasi, id_rkk_detail, id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, r_upah, r_potongan_telat, r_potongan_istirahat_awal, r_potongan_istirahat_telat, r_potongan_lainnya, r_jam_masuk, r_jam_keluar, r_istirahat_masuk, r_istirahat_keluar, r_status, r_update, ra_masuk, ra_keluar, ra_istirahat_masuk, ra_istirahat_keluar, id_jadwal, status_realisasi_detail, hasil_kerja, lembur, tgl_realisasi_detail, r_potongan_pulang, r_potongan_tidak_lengkap, lembur_jam, id_grup, id_pengganti) VALUES ('$idrealisasi', 0, '$idrkk_ref', 0, '$nm_esc', NULL, NULL, 0, 0, 0, 0, 0, '', '', '', '', 'Hadir', '$now_tu', '$ra_masuk', '$ra_keluar', '$ra_im', '$ra_ik', 0, 2, '-', 0, '$tgl_real', 0, 0, 0, '$gid_tdr', NULL)");
        if ($ok) { $added++; $exist_names[$disp] = true; }
    }
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
                text: "' . $added . ' baris tak terdaftar masuk grup TIDAK DALAM RENCANA (upah 0).",
                confirmButtonColor: "#2563eb",
                confirmButtonText: "OK"
            }).then((result) => {
                window.location.href = "?page=realisasi&aksi=kelola&id=' . $idrealisasi . '";
            });
        </script>
    </body>
    </html>';
    exit;
}


if (!function_exists('rupiah')) {
    function rupiah($angka)
    {
        if ($angka === null || $angka === "") $angka = 0;
        return "Rp " . number_format($angka, 0, ',', '.');
    }
}

/**
 * Function to synchronize realization detail data with machine records (tb_record)
 * Specifically for a given id_realisasi
 */
function syncRealisasiData($koneksi, $id_realisasi)
{
    // 1. Get the realization date and global denda
    $query_real = $koneksi->query("SELECT tgl_realisasi FROM tb_realisasi WHERE id_realisasi = '$id_realisasi'");
    $data_real = $query_real->fetch_assoc();
    if (!$data_real) return 0;
    $tgl_real = $data_real['tgl_realisasi'];

    $query_denda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $denda = $query_denda->fetch_assoc();
    $gd_masuk = $denda['denda_masuk'] ?? 0;
    $gd_istirahat_awal = $denda['denda_istirahat_keluar'] ?? 0;
    $gd_istirahat_telat = $denda['denda_istirahat_masuk'] ?? 0;
    $gd_pulang = $denda['denda_pulang'] ?? 0;
    $gd_tidak_lengkap = $denda['denda_tidak_lengkap'] ?? 0;

    // ID grup TIDAK DALAM RENCANA (baris tap tanpa master): jangan ditimpa sync
    $gid_tdr = 0;
    $qg0 = $koneksi->query("SELECT id_grup FROM ms_grup_borongan WHERE UPPER(TRIM(nama_grup)) = 'TIDAK DALAM RENCANA' LIMIT 1");
    if ($qg0 && ($gg0 = $qg0->fetch_assoc())) $gid_tdr = (int)$gg0['id_grup'];

    // 2. Get all employees in this realization (using LEFT JOIN to include manual employees and RKK fallback)
    $query_details = $koneksi->query("SELECT RD.id_realisasi_detail, RD.id_grup AS grup_id, K.no_absen, K.upah as upah_master, RD.id_jadwal as detail_jadwal, RKKD.id_jadwal as rkk_jadwal, RD.status_realisasi_detail, 
                                      COALESCE(J.keterangan, JR.keterangan) as shift_ket,
                                      COALESCE(J.jam_masuk, JR.jam_masuk) as s_masuk, 
                                      COALESCE(J.jam_keluar, JR.jam_keluar) as s_keluar, 
                                      COALESCE(J.istirahat_masuk, JR.istirahat_masuk) as s_ist_masuk, 
                                      COALESCE(J.istirahat_keluar, JR.istirahat_keluar) as s_ist_keluar
                                      FROM tb_realisasi_detail RD
                                      LEFT JOIN ms_karyawan K ON RD.id_karyawan = K.id_karyawan
                                      LEFT JOIN tb_rkk_detail RKKD ON RD.id_rkk_detail = RKKD.id_rkk_detail
                                      LEFT JOIN tb_jadwal J ON RD.id_jadwal = J.id_jadwal
                                      LEFT JOIN tb_jadwal JR ON RKKD.id_jadwal = JR.id_jadwal
                                      WHERE RD.id_realisasi = '$id_realisasi'");

    $count = 0;
    while ($row = $query_details->fetch_assoc()) {
        $id_rd = $row['id_realisasi_detail'];
        // Baris TIDAK DALAM RENCANA dilewati (datanya dari tap langsung, jangan ditimpa jadi kosong)
        if ($gid_tdr > 0 && (int)($row['grup_id'] ?? -1) === $gid_tdr) continue;
        $no_absen = $row['no_absen'];
        $status_rd = $row['status_realisasi_detail'];
        
        // Include manual employees (Previously skipped)
        $shift_ket = $row['shift_ket'] ?? '';
        $is_flexible = (strpos(strtoupper($shift_ket), 'FLEXI') !== false);

        $s_masuk = $row['s_masuk'] ?? '00:00:00';
        $s_keluar = $row['s_keluar'] ?? '00:00:00';
        $s_ist_masuk = $row['s_ist_masuk'] ?? '00:00:00';
        $s_ist_keluar = $row['s_ist_keluar'] ?? '00:00:00';

        if (empty($no_absen)) {
            $is_total_missing = true;
            $ra_masuk = "'00:00:00'";
            $ra_keluar = "'00:00:00'";
            $ra_ist_keluar = "'00:00:00'";
            $ra_ist_masuk = "'00:00:00'";
        } else {
            // Get logs for today and tomorrow to support cross-midnight night-shift merge
            $tgl_next = date('Y-m-d', strtotime('+1 day', strtotime($tgl_real)));
            $q_logs = $koneksi->query("SELECT * FROM tb_record WHERE userid = '$no_absen' AND tgl IN ('$tgl_real', '$tgl_next') ORDER BY tgl ASC, detail_waktu ASC");
            
            $log_masuk = null;
            $log_keluar = null;
            $log_ist_keluar = null;
            $log_ist_masuk = null;
            
            $raw_logs = [];
            while ($r_log = $q_logs->fetch_assoc()) {
                $raw_logs[] = $r_log;
            }
            
            // Find earliest check-in (status 0) on $tgl_real
            foreach ($raw_logs as $l) {
                if ($l['tgl'] == $tgl_real && $l['status'] == 0) {
                    $log_masuk = substr($l['detail_waktu'], 11, 8);
                    break;
                }
            }
            
            // Fallback if no status 0 on $tgl_real: use first scan of the day
            if (!$log_masuk) {
                foreach ($raw_logs as $l) {
                    if ($l['tgl'] == $tgl_real) {
                        $log_masuk = substr($l['detail_waktu'], 11, 8);
                        break;
                    }
                }
            }
            
            if ($log_masuk) {
                $in_timestamp = strtotime($tgl_real . ' ' . $log_masuk);
                
                // Find check-out (status 1) within 16 hours (check backwards for the latest valid checkout)
                for ($i = count($raw_logs) - 1; $i >= 0; $i--) {
                    $l = $raw_logs[$i];
                    if ($l['status'] == 1) {
                        $curr_time = substr($l['detail_waktu'], 11, 8);
                        $curr_timestamp = strtotime($l['tgl'] . ' ' . $curr_time);
                        $diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
                        if ($diff_hours > 0 && $diff_hours <= 16) {
                            $log_keluar = $curr_time;
                            break;
                        }
                    }
                }
                
                // Fallback: if no check-out with status 1 is found, use the latest scan within 16 hours of check-in
                if (!$log_keluar) {
                    for ($i = count($raw_logs) - 1; $i >= 0; $i--) {
                        $l = $raw_logs[$i];
                        $curr_time = substr($l['detail_waktu'], 11, 8);
                        if ($l['tgl'] == $tgl_real && $curr_time == $log_masuk) {
                            continue;
                        }
                        $curr_timestamp = strtotime($l['tgl'] . ' ' . $curr_time);
                        $diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
                        if ($diff_hours > 0 && $diff_hours <= 16) {
                            $log_keluar = $curr_time;
                            break;
                        }
                    }
                }
                
                // Find break-out (status 2) and break-in (status 3) within 16 hours
                foreach ($raw_logs as $l) {
                    $curr_time = substr($l['detail_waktu'], 11, 8);
                    $curr_timestamp = strtotime($l['tgl'] . ' ' . $curr_time);
                    $diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
                    if ($diff_hours > 0 && $diff_hours <= 16) {
                        if ($l['status'] == 2 && !$log_ist_keluar) {
                            $log_ist_keluar = $curr_time;
                        }
                        if ($l['status'] == 3 && !$log_ist_masuk) {
                            $log_ist_masuk = $curr_time;
                        }
                    }
                }

                // Fallback: mesin tidak mengirim status 2/3, tebak istirahat dari tap tengah.
                // Hari normal 4 tap (masuk, ist-keluar, ist-masuk, pulang): ambil tap ke-2 & tengah-terakhir.
                // Pengaman: abaikan tap ganda (menit sama) + tap yang menempel masuk/pulang (<30 mnt).
                if (!$log_ist_keluar || !$log_ist_masuk) {
                    $pool = []; $seen_min = [];
                    foreach ($raw_logs as $l) {
                        if (in_array((int)$l['status'], [4, 5], true)) continue; // lembur, bukan istirahat
                        $ct = substr($l['detail_waktu'], 11, 8);
                        $mk = substr($ct, 0, 5);
                        if (isset($seen_min[$mk])) continue;
                        $seen_min[$mk] = true;
                        $cts = strtotime($l['tgl'] . ' ' . $ct);
                        $dh = ($cts - $in_timestamp) / 3600;
                        if ($dh >= 0 && $dh <= 16) $pool[] = ['t' => $ct, 'ts' => $cts];
                    }
                    usort($pool, function($a, $b) { return $a['ts'] - $b['ts']; });
                    $m = count($pool);
                    $pul_end = $m > 0 ? $pool[$m - 1]['ts'] : 0;
                    $is_mid = function($ts) use ($in_timestamp, $pul_end) {
                        return ($ts - $in_timestamp) >= 1800 && ($pul_end <= 0 || ($pul_end - $ts) >= 1800);
                    };
                    if ($m >= 4) {
                        if (!$log_ist_keluar && $is_mid($pool[1]['ts'])) $log_ist_keluar = $pool[1]['t'];
                        if (!$log_ist_masuk && $is_mid($pool[$m - 2]['ts'])) $log_ist_masuk = $pool[$m - 2]['t'];
                    } elseif ($m == 3) {
                        // 3 tap: tentukan sisi dari titik tengah jam istirahat shift
                        $bk = trim((string)$s_ist_keluar); $bm = trim((string)$s_ist_masuk);
                        if ($bk !== '' && $bk !== '00:00:00' && $bm !== '' && $bm !== '00:00:00' && $is_mid($pool[1]['ts'])) {
                            $mid = (strtotime($bk) + strtotime($bm)) / 2;
                            $mid_t = date('H:i:s', (int)$mid);
                            if ($pool[1]['t'] < $mid_t) { if (!$log_ist_keluar) $log_ist_keluar = $pool[1]['t']; }
                            else { if (!$log_ist_masuk) $log_ist_masuk = $pool[1]['t']; }
                        }
                    }
                }
            }
            
            $logs = [
                'log_masuk' => $log_masuk,
                'log_keluar' => $log_keluar,
                'log_ist_keluar' => $log_ist_keluar,
                'log_ist_masuk' => $log_ist_masuk
            ];
            
            $ra_masuk = $log_masuk ? "'" . $log_masuk . "'" : "'00:00:00'";
            $ra_keluar = $log_keluar ? "'" . $log_keluar . "'" : "'00:00:00'";
            $ra_ist_keluar = $log_ist_keluar ? "'" . $log_ist_keluar . "'" : "'00:00:00'";
            $ra_ist_masuk = $log_ist_masuk ? "'" . $log_ist_masuk . "'" : "'00:00:00'";
            
            $has_masuk = !empty($log_masuk);
            $has_keluar = !empty($log_keluar);
            $has_ist_keluar = !empty($log_ist_keluar);
            $has_ist_masuk = !empty($log_ist_masuk);
            
            $is_total_missing = (!$has_masuk && !$has_keluar);
        }

        // 4. Calculate Penalties and Handle Absence
        $p_telat = 0;
        $p_pulang = 0;
        $p_ist_awal = 0;
        $p_ist_telat = 0;
        $p_tidak_lengkap = 0;

        // If no_absen was empty, these are already set correctly in the if block.
        // If no_absen was NOT empty, logs were fetched. 
        // We ensure $has_ variables exist for the penalty logic below.
        if (!empty($no_absen)) {
            $has_masuk = !empty($logs['log_masuk']);
            $has_keluar = !empty($logs['log_keluar']);
            $has_ist_keluar = !empty($logs['log_ist_keluar']);
            $has_ist_masuk = !empty($logs['log_ist_masuk']);
            $is_total_missing = (!$has_masuk && !$has_keluar);
        } else {
            $has_masuk = false;
            $has_keluar = false;
            $has_ist_keluar = false;
            $has_ist_masuk = false;
            $is_total_missing = true;
        }

        if (!$is_total_missing) {
            // Late Entrance
            if ($has_masuk && is_time_late($logs['log_masuk'], $s_masuk)) {
                $p_telat = $gd_masuk;
            }
            // Early Departure
            if ($has_keluar && is_time_early($logs['log_keluar'], $s_keluar)) {
                $p_pulang = $gd_pulang;
            }
            // Early Break
            if ($has_ist_keluar && is_time_early($logs['log_ist_keluar'], $s_ist_keluar)) {
                $p_ist_awal = $gd_istirahat_awal;
            }
            // Late Break Return
            if ($has_ist_masuk && is_time_late($logs['log_ist_masuk'], $s_ist_masuk)) {
                $p_ist_telat = $gd_istirahat_telat;
            }

            // Incomplete Log (Missing any expected punch)
            $hasIncompleteMain = ($has_masuk xor $has_keluar);
            $isRestExpected = (!$is_flexible && !empty($s_ist_keluar) && $s_ist_keluar != '00:00:00');
            $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));

            if ($hasIncompleteMain || $hasIncompleteBreak) {
                $p_tidak_lengkap = $gd_tidak_lengkap;
            }
        }

        // Determine Wage adjustment for total absence
        $upah_master = $row['upah_master'] ?? 0;
        $wage_update_sql = "";
        $status_enum = "Hadir";
        if ($is_total_missing) {
            $wage_update_sql = "r_upah = 0,";
            $status_enum = "Tidak Hadir";
        } else {
            $wage_update_sql = "r_upah = '$upah_master',";
        }

        // 5. Update the detail record and SET STATUS TO 2 (Synced)
        $update = $koneksi->query("UPDATE tb_realisasi_detail SET 
            r_jam_masuk = '$s_masuk',
            r_jam_keluar = '$s_keluar',
            r_istirahat_masuk = '$s_ist_masuk',
            r_istirahat_keluar = '$s_ist_keluar',
            ra_masuk = $ra_masuk,
            ra_keluar = $ra_keluar,
            ra_istirahat_masuk = $ra_ist_masuk,
            ra_istirahat_keluar = $ra_ist_keluar,
            r_potongan_telat = '$p_telat',
            r_potongan_istirahat_awal = '$p_ist_awal',
            r_potongan_istirahat_telat = '$p_ist_telat',
            r_potongan_pulang = '$p_pulang',
            r_potongan_tidak_lengkap = '$p_tidak_lengkap',
            $wage_update_sql
            r_status = '$status_enum',
            status_realisasi_detail = 2
            WHERE id_realisasi_detail = '$id_rd'");

        if ($update) $count++;
    }

    return $count;
}

/**
 * Function to calculate and save penalties for all current logs in realization
 * without re-syncing from machine logs.
 */
function bulkSavePenalties($koneksi, $id_realisasi)
{
    // 1. Get global denda
    $query_denda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $denda = $query_denda->fetch_assoc();
    $gd_masuk = $denda['denda_masuk'] ?? 0;
    $gd_istirahat_awal = $denda['denda_istirahat_keluar'] ?? 0;
    $gd_istirahat_telat = $denda['denda_istirahat_masuk'] ?? 0;
    $gd_pulang = $denda['denda_pulang'] ?? 0;
    $gd_tidak_lengkap = $denda['denda_tidak_lengkap'] ?? 0;

    // 2. Get all details for this realization
    $query_details = $koneksi->query("SELECT RD.*, J.keterangan as shift_ket, J.jam_masuk as s_masuk, J.jam_keluar as s_keluar, J.istirahat_masuk as s_ist_masuk, J.istirahat_keluar as s_ist_keluar
                                      FROM tb_realisasi_detail RD
                                      LEFT JOIN tb_jadwal J ON RD.id_jadwal = J.id_jadwal
                                      WHERE RD.id_realisasi = '$id_realisasi'");

    $count = 0;
    while ($row = $query_details->fetch_assoc()) {
        $id_rd = $row['id_realisasi_detail'];
        $has_masuk = !empty($row['ra_masuk']) && $row['ra_masuk'] != '00:00:00';
        $has_keluar = !empty($row['ra_keluar']) && $row['ra_keluar'] != '00:00:00';
        $has_ist_masuk = !empty($row['ra_istirahat_masuk']) && $row['ra_istirahat_masuk'] != '00:00:00';
        $has_ist_keluar = !empty($row['ra_istirahat_keluar']) && $row['ra_istirahat_keluar'] != '00:00:00';

        $s_masuk = $row['s_masuk'] ?? '00:00:00';
        $s_keluar = $row['s_keluar'] ?? '00:00:00';
        $s_ist_masuk = $row['s_ist_masuk'] ?? '00:00:00';
        $s_ist_keluar = $row['s_ist_keluar'] ?? '00:00:00';

        // skip if status is 1 (already manually saved) unless we want to overwrite? 
        // usually bulk save is for pending/synced ones.
        if ($row['status_realisasi_detail'] == 1) continue;

        // 3. Calculate Penalties
        $p_telat = 0;
        $p_pulang = 0;
        $p_ist_awal = 0;
        $p_ist_telat = 0;
        $p_tidak_lengkap = 0;

        $is_total_missing = (!$has_masuk && !$has_keluar);

        if (!$is_total_missing) {
            // Late Entrance
            if ($has_masuk && $s_masuk != '00:00:00' && strtotime($row['ra_masuk']) > strtotime($s_masuk)) {
                $p_telat = $gd_masuk;
            }
            // Early Departure
            if ($has_keluar && $s_keluar != '00:00:00' && strtotime($row['ra_keluar']) < strtotime($s_keluar)) {
                $p_pulang = $gd_pulang;
            }
            // Early Break
            if ($has_ist_keluar && $s_ist_keluar != '00:00:00' && strtotime($row['ra_istirahat_keluar']) < strtotime($s_ist_keluar)) {
                $p_ist_awal = $gd_istirahat_awal;
            }
            // Late Break Return
            if ($has_ist_masuk && $s_ist_masuk != '00:00:00' && strtotime($row['ra_istirahat_masuk']) > strtotime($s_ist_masuk)) {
                $p_ist_telat = $gd_istirahat_telat;
            }

            $shift_ket = $row['shift_ket'] ?? '';
            $is_flexible = (strpos(strtoupper($shift_ket), 'FLEXI') !== false);
            $isRestExpected = (!$is_flexible && $s_ist_keluar != '00:00:00' && $s_ist_keluar != 'Bebas');
            $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));

            if ($hasIncompleteMain || $hasIncompleteBreak) {
                $p_tidak_lengkap = $gd_tidak_lengkap;
            }
        }

        // 4. Update the detail record
        $update = $koneksi->query("UPDATE tb_realisasi_detail SET 
            r_jam_masuk = '$s_masuk',
            r_jam_keluar = '$s_keluar',
            r_istirahat_masuk = '$s_ist_masuk',
            r_istirahat_keluar = '$s_ist_keluar',
            r_potongan_telat = '$p_telat',
            r_potongan_pulang = '$p_pulang',
            r_potongan_istirahat_awal = '$p_ist_awal',
            r_potongan_istirahat_telat = '$p_ist_telat',
            r_potongan_tidak_lengkap = '$p_tidak_lengkap',
            status_realisasi_detail = 2
            WHERE id_realisasi_detail = '$id_rd'");

        if ($update) $count++;
    }

    return $count;
}
?>

<div class="container-fluid" style="padding: 15px 0;">
    <div class="row">
        <div class="col-md-12">
            <div class="card-clean">
                <div class="border-b border-gray-200 py-4 px-4 md:px-5 bg-white flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <h3 class="text-xl font-bold text-blue-600 m-0">
                        <i class="fas fa-list-alt mr-2"></i> Daftar Realisasi Upah
                    </h3>

                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                        <a href="?page=realisasi&aksi=karyawan&id=<?= $idrealisasi; ?>" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white text-[14px] md:text-base font-bold py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto justify-center">
                            <i class="fas fa-user-plus mr-1.5"></i> Tambah Karyawan
                        </a>
                        <a href="?page=realisasi" class="inline-flex items-center bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 text-[14px] md:text-base font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto justify-center">
                            <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                        </a>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="bg-white">
                    <div class="p-4 md:p-5 bg-gray-50 border-b border-gray-100">
                        <div class="mb-6">
                            <div class="text-sm font-bold text-blue-600 uppercase tracking-wider mb-3 border-l-4 border-blue-600 pl-2">Rencana Upah</div>
                            <div class="row">
                                <div class="col-md-4 mb-3 md:mb-0">
                                    <label class="font-bold text-gray-700 text-sm">Tanggal</label>
                                    <input readonly type="text" value="<?php echo date('d/m/Y', strtotime($datatglrkk)); ?>" class="form-control text-base py-2" />
                                </div>
                                <div class="col-md-8 mb-3 md:mb-0">
                                    <label class="font-bold text-gray-700 text-sm">Keterangan</label>
                                    <input readonly type="text" value="<?php echo $dataketeranganrkk; ?>" class="form-control text-base py-2" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="text-sm font-bold text-emerald-600 uppercase tracking-wider mb-3 border-l-4 border-emerald-600 pl-2">Realisasi Upah</div>
                            <div class="row">
                                <div class="col-md-4 mb-3 md:mb-0">
                                    <label class="font-bold text-gray-700 text-sm">Tanggal</label>
                                    <input readonly type="text" name="ttgl1" value="<?php echo date('d/m/Y', strtotime($datatglrealisasi)); ?>" class="form-control text-base py-2" />
                                </div>
                                <div class="col-md-8 mb-3 md:mb-0">
                                    <label class="font-bold text-gray-700 text-sm">Keterangan</label>
                                    <input type="text" name="tketerangan" value="<?php echo $dataketerangan; ?>" placeholder="Masukkan keterangan..." class="form-control text-base py-2" autocomplete="off" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 md:px-5 bg-white border-t border-gray-200" <?php echo $status; ?>>
                        <button type="button" id="btn-save-all" class="btn btn-primary bg-blue-600 hover:bg-blue-700 border-none px-6 py-2">
                            <i class="fas fa-save mr-1.5"></i> Simpan Semua
                        </button>
                        <button type="button" id="btn-cleanup" class="btn btn-warning bg-amber-500 hover:bg-amber-600 border-none px-6 py-2 ml-2 text-white" title="Tarik dari mesin + sinkron ke tabel (sama seperti halaman tarik data)">
                            <i class="fas fa-sync-alt mr-1.5"></i> Tarik Data
                        </button>
                        <button type="button" id="btn-pull-unknown" class="btn btn-outline-danger bg-white hover:bg-red-50 border px-6 py-2 ml-2 text-red-600 font-bold" title="Masukkan tap PIN tak terdaftar jadi baris grup TIDAK DALAM RENCANA (upah 0)">
                            <i class="fas fa-user-plus mr-1.5"></i> Tarik Tak Terdaftar
                        </button>
                        <input type="hidden" name="simpan" id="simpan-input" value="">
                        <input type="hidden" name="cleanup" id="cleanup-input" value="">
                        <input type="hidden" name="pull_unknown" id="pull-unknown-input" value="">
                    </div>
                </form>

                <div class="text-sm font-bold text-blue-600 uppercase tracking-wider mb-3 ml-3 border-l-4 border-blue-600 pl-2"> <i class="fas fa-users mr-1.5"></i> List Karyawan</div>

                <div class="p-0">
                    <div class="px-3 md:px-4 py-4">
                        <?php
                        // Daftar grup (master) yang ada di realisasi ini + jumlah orang
                        $q_gfilter = $koneksi->query("SELECT G.nama_grup as nm, COUNT(*) as jml
                            FROM tb_realisasi_detail AD
                            LEFT JOIN ms_grup_borongan G ON AD.id_grup = G.id_grup
                            WHERE AD.id_realisasi = '$idrealisasi' GROUP BY nm ORDER BY nm ASC");
                        $grup_list = [];
                        if ($q_gfilter) while ($gr = $q_gfilter->fetch_assoc()) {
                            $dn = !empty($gr['nm']) ? strtoupper($gr['nm']) : 'TANPA GRUP';
                            $grup_list[$dn] = ($grup_list[$dn] ?? 0) + (int)$gr['jml'];
                        }
                        ksort($grup_list);
                        ?>
                        <div class="mb-3 flex flex-col md:flex-row gap-2">
                            <select id="filterGrup" class="w-full md:max-w-xs px-3 py-2 border border-gray-300 rounded-lg text-sm font-bold text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Semua Grup</option>
                                <?php foreach ($grup_list as $gn => $gj): ?>
                                    <option value="<?= htmlspecialchars($gn) ?>"><?= htmlspecialchars($gn) ?> (<?= $gj ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <input id="cariKaryawan" type="text" placeholder="Cari nama / posisi..." autocomplete="off"
                                class="w-full md:max-w-xs px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="table-responsive rounded-lg border border-gray-800">
                            <table class="table table-hover table-clean align-middle mb-0 table-modern w-full min-w-[1600px]" id="tabel-realisasi">
                              <tbody>
                                  <?php
                                $no = 1;
                                $total = 0;
                                $jml_active = 0;
                                // Peta rincian borongan per detail (satu query untuk semua baris)
                                $koneksi->query("CREATE TABLE IF NOT EXISTS tb_realisasi_borongan (
                                  id_borongan INT AUTO_INCREMENT PRIMARY KEY,
                                  id_realisasi_detail INT NOT NULL,
                                  nama_pekerjaan VARCHAR(100) NOT NULL,
                                  qty DECIMAL(12,2) NOT NULL DEFAULT 0,
                                  tarif INT NOT NULL DEFAULT 0,
                                  satuan VARCHAR(10) NOT NULL DEFAULT 'kg',
                                  KEY idx_detail (id_realisasi_detail)
                                ) ENGINE=InnoDB");
                                $ckSatK = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_borongan LIKE 'satuan'");
                                if ($ckSatK && $ckSatK->num_rows == 0) {
                                    $koneksi->query("ALTER TABLE tb_realisasi_borongan ADD COLUMN satuan VARCHAR(10) NOT NULL DEFAULT 'kg'");
                                }
                                $bor_map = [];
                                $qb_all = $koneksi->query("SELECT b.* FROM tb_realisasi_borongan b JOIN tb_realisasi_detail d ON b.id_realisasi_detail = d.id_realisasi_detail WHERE d.id_realisasi = '$idrealisasi' ORDER BY b.id_borongan ASC");
                                while ($qb_all && ($rb_map = $qb_all->fetch_assoc())) $bor_map[$rb_map['id_realisasi_detail']][] = $rb_map;
                                // Baris pertama per orang (MIN id) yang dihitung; baris rangkap (beda grup, nama sama) tampil tapi tidak dijumlah
                                $keep_ids = []; $cnt_person = [];
                                $qk = $koneksi->query("SELECT MIN(RD.id_realisasi_detail) AS k, COUNT(*) AS c, IF(RD.id_karyawan = 0, CONCAT('M:', IFNULL(RD.nama_karyawan_manual, '')), CONCAT('K:', RD.id_karyawan)) AS pk FROM tb_realisasi_detail RD LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail WHERE RD.id_realisasi = '$idrealisasi' AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan') GROUP BY pk");
                                while ($qk && ($rkk = $qk->fetch_assoc())) { $keep_ids[(int)$rkk['k']] = true; $cnt_person[$rkk['pk']] = (int)$rkk['c']; }
                                $jml_active = 0;
                                $last_dept = null;
                                $ncol = (strtolower($_SESSION['role']) != 'admin hr') ? 17 : 16;
                                $aksi_th = (strtolower($_SESSION['role']) != 'admin hr') ? '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Aksi</th>' : '';

                                while ($data = $tampil->fetch_assoc()) {
                                    if (trim((string)($data['nama_tampil'] ?? '')) === '') continue;
                                    $dept_now = !empty($data['label_grup']) ? strtoupper($data['label_grup']) : 'TANPA GRUP';
                                    if ($dept_now !== $last_dept) {
                                        $last_dept = $dept_now;
                                        $no = 1;
                                        $dept_esc = htmlspecialchars($dept_now, ENT_QUOTES);
                                        echo '<tr class="dept-separator" data-dept="' . $dept_esc . '"><td colspan="' . $ncol . '" data-label="Bagian" style="background:#B2A1C6; font-weight:bold; text-align:center; text-transform:uppercase; padding:8px 4px;">' . htmlspecialchars($dept_now) . '</td></tr>';
                                        echo '<tr class="grp-header d-none d-md-table-row" data-dept="' . $dept_esc . '">'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">No</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Nama Sesuai KTP</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Paraf Pekerja</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">OS/DHK</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Golongan</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Posisi</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Jam Kerja</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Jam Masuk</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Istirahat<br>Masuk</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#D9D9D9;">Jam Pulang</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right" style="background:#EFEFEF;">Detail Borongan</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle" style="background:#EFEFEF;">Hasil Kerja</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right" style="background:#EFEFEF;">Upah</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right" style="background:#EFEFEF;">Potongan</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right" style="background:#EFEFEF;">Lembur</th>'
                                            . '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right" style="background:#EFEFEF;">Upah Dibayar</th>'
                                            . $aksi_th . '</tr>';
                                    }
                                    $upah = $data['upahkaryawan'];

                                    // Total borongan tersimpan (dipakai override upah di bawah + sel harga)
                                    $bor_lines = $bor_map[$data['id_realisasi_detail']] ?? [];
                                    $bor_total = 0;
                                    foreach ($bor_lines as $ln0) $bor_total += (int)round((float)$ln0['qty'] * (int)$ln0['tarif']);

                                    $isFullMissing = (empty($data['r_jam_masuk']) || strtotime($data['r_jam_masuk']) == strtotime('00:00:00')) &&
                                        (empty($data['r_jam_keluar']) || strtotime($data['r_jam_keluar']) == strtotime('00:00:00'));

                                    $has_masuk = !empty($data['ra_masuk']) && $data['ra_masuk'] != '00:00:00';
                                    $has_keluar = !empty($data['ra_keluar']) && $data['ra_keluar'] != '00:00:00';
                                    $has_ist_masuk = !empty($data['ra_istirahat_masuk']) && $data['ra_istirahat_masuk'] != '00:00:00';
                                    $has_ist_keluar = !empty($data['ra_istirahat_keluar']) && $data['ra_istirahat_keluar'] != '00:00:00';

                                    $isLate = ($has_masuk && !empty($data['r_jam_masuk']) && is_time_late($data['ra_masuk'], $data['r_jam_masuk']));
                                    $isEarlyOut = ($has_keluar && !empty($data['r_jam_keluar']) && is_time_early($data['ra_keluar'], $data['r_jam_keluar']));
                                    $isLateBreak = ($has_ist_masuk && !empty($data['r_istirahat_masuk']) && is_time_late($data['ra_istirahat_masuk'], $data['r_istirahat_masuk']));
                                    $isEarlyBreak = ($has_ist_keluar && !empty($data['r_istirahat_keluar']) && is_time_early($data['ra_istirahat_keluar'], $data['r_istirahat_keluar']));

                                     $isTotalMissing = (!$has_masuk && !$has_keluar);
                                     $hasIncompleteMain = ($has_masuk xor $has_keluar);
                                     $shift_ket = $data['shift_ket'] ?? '';
                                     $is_flexible = (strpos(strtoupper($shift_ket), 'FLEXI') !== false);
                                     $isRestExpected = (!$is_flexible && !empty($data['r_istirahat_keluar']) && $data['r_istirahat_keluar'] != '00:00:00');
                                     $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));

                                     // Logic Status Kehadiran (Refined)
                                     // Attendance Status Logic (ENUM-based)
                                     $is_replaced = !empty($data['digantikan_oleh']) || !empty($data['pengganti_langsung']);
                                     if ($data['r_status'] == 'Tidak Hadir' && !$is_replaced) {
                                         $attendanceStatus = 'TIDAK HADIR';
                                         $statusBadgeClass = 'bg-red-100 text-red-800';
                                     } elseif ($is_replaced) {
                                         $attendanceStatus = 'DIGANTIKAN';
                                         $statusBadgeClass = 'bg-amber-100 text-amber-800';
                                     } else {
                                         // If r_status is 'Hadir', check if it's currently synced but has no logs
                                         if ($data['status_realisasi_detail'] == 2 && $isTotalMissing) {
                                             $attendanceStatus = 'TIDAK HADIR'; 
                                             $statusBadgeClass = 'bg-red-100 text-red-800';
                                         } else {
                                             $attendanceStatus = 'HADIR';
                                             $statusBadgeClass = 'bg-green-100 text-green-800';
                                         }
                                     }


                                    // Denda logic
                                    if ($data['status_realisasi_detail'] == 0) {
                                        // Status 0 (Draft) - Before sync, ALL penalties must be 0
                                        $potTelatValue = 0;
                                        $potIstirahatKeluarValue = 0;
                                        $potIstirahatMasukValue = 0;
                                        $potPulangValue = 0;
                                        $potTidakLengkapValue = 0;
                                        $potLainnyaValue = $data['r_potongan_lainnya'];
                                    } else {
                                        // Status 1 (Manual) or 2 (Synced)
                                        // Calculate dynamically based on real-time shift data
                                        $potPulangValue = ($isEarlyOut && !$isTotalMissing) ? $globalDendaPulang : 0;
                                        $potTidakLengkapValue = (!$isTotalMissing && ($hasIncompleteMain || $hasIncompleteBreak)) ? $globalDendaTidakLengkap : 0;
                                        $potTelatValue = ($isLate) ? $globalDendaMasuk : 0;
                                        $potIstirahatKeluarValue = ($isEarlyBreak) ? $globalDendaIstirahatKeluar : 0;
                                        $potIstirahatMasukValue = ($isLateBreak) ? $globalDendaIstirahatMasuk : 0;
                                        $potLainnyaValue = $data['r_potongan_lainnya'];
                                    }

                                    // Final wage calculation
                                    $finalUpah = $data['upahkaryawan'];
                                    $finalLembur = $data['lembur'];

                                     if (!empty($data['digantikan_oleh']) || $data['status_rkk'] == 'Tidak Hadir' || $attendanceStatus == 'TIDAK HADIR') {
                                         // Manual Employees are always 'HADIR' in the above logic unless the user manually changes something
                                         $finalUpah = 0;
                                         $finalLembur = 0;

                                            // Clear all penalties for absent
                                            $potTelatValue = 0;
                                            $potIstirahatKeluarValue = 0;
                                            $potIstirahatMasukValue = 0;
                                            $potPulangValue = 0;
                                            $potTidakLengkapValue = 0;
                                            $potLainnyaValue = 0;
                                        }

                                     if ($bor_total > 0) {
                                         // Borongan: upah & upah dibayar ikut total borongan, bukan gaji master
                                         $finalUpah = $bor_total;
                                         $finalLembur = 0;
                                         $potTelatValue = 0;
                                         $potIstirahatKeluarValue = 0;
                                         $potIstirahatMasukValue = 0;
                                         $potPulangValue = 0;
                                         $potTidakLengkapValue = 0;
                                         $potLainnyaValue = 0;
                                     }

                                    $upah_setelah_potongan = $finalUpah + $finalLembur - $potTelatValue - $potIstirahatKeluarValue - $potIstirahatMasukValue - $potLainnyaValue - $potPulangValue - $potTidakLengkapValue;
                                    $pkey_k = ((int)($data['id_karyawan'] ?? 0) !== 0) ? ('K:' . (int)$data['id_karyawan']) : ('M:' . trim((string)($data['nama_karyawan_manual'] ?? '')));
                                    $data['_rangkap'] = !isset($keep_ids[(int)$data['id_realisasi_detail']]) && (($cnt_person[$pkey_k] ?? 0) > 1);
                                ?>
                                    <tr class="data-row" data-dept="<?= htmlspecialchars($dept_now) ?>">
                                        <td data-label="No"><?php echo $no; ?></td>
                                        <td data-label="Nama Sesuai KTP">
                                            <strong>
                                                <?php echo $data['nama_tampil']; ?>
                                                <?php if (!empty($data['menggantikan'])) echo " <span class='text-blue-600 font-bold'>(Menggantikan " . $data['menggantikan'] . ")</span>"; ?>
                                                <?php if (!empty($data['menggantikan']) && !empty($data['digantikan_oleh'])) echo " &"; ?>
                                                <?php if (!empty($data['digantikan_oleh'])) echo " <span class='text-red-600 font-bold'>(Digantikan oleh " . $data['digantikan_oleh'] . ")</span>"; ?>
                                                <?php if (empty($data['digantikan_oleh']) && !empty($data['pengganti_langsung'])) echo " <span class='text-red-600 font-bold'>(Digantikan oleh " . $data['pengganti_langsung'] . ")</span>"; ?>
                                            </strong>

                                            <?php $show_badge = !(($data['status_rkk'] ?? '') == 'Hadir' && $attendanceStatus != 'TIDAK HADIR'); ?>
                                            <?php if ($show_badge) : ?>
                                            <div class="mt-1">
                                                <?php if ($attendanceStatus == 'TIDAK HADIR' || ($data['status_rkk'] ?? '') == 'Tidak Hadir') : ?>
                                                    <span class="bg-rose-100 text-rose-800 text-[10px] font-bold px-2 py-0.5 rounded-full">Tidak Hadir</span>
                                                <?php elseif (($data['status_rkk'] ?? '') == 'Digantikan') : ?>
                                                    <span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full">Digantikan</span>
                                                <?php elseif (($data['status_rkk'] ?? '') == 'Pengganti') : ?>
                                                    <span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded-full">Pengganti</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Paraf Pekerja" align="center"></td>
                                        <td data-label="OS/DHK" align="center"><?php echo $data['label_os'] ?: ($data['OS_DHK'] ?? 'MHS'); ?></td>
                                        <td data-label="Golongan" align="center"><?php echo $data['label_gol'] ?: ($data['golongan'] ?? 'Harian'); ?></td>
                                        <td data-label="Posisi">
                                            <?php echo $data['nama_sub_department']; ?>
                                            <div class="text-[11px] text-gray-500"><?php echo $data['nama_departmen']; ?></div>
                                        </td>
                                        <td data-label="Jam Kerja" align="center"><?php echo $data['r_jam_masuk'] ?: ''; ?></td>
                                        <td data-label="Jam Masuk" align="center" class="<?php
                                                                            $isEmptyLog = (empty($data['ra_masuk']) || $data['ra_masuk'] == '00:00:00');
                                                                            $hasSchedule = !empty($data['r_jam_masuk']);
                                                                            echo ($hasSchedule && $isEmptyLog) || $isLate ? 'bg-red-custom' : '';
                                                                            ?>"><?php echo (empty($data['ra_masuk']) || $data['ra_masuk'] == '00:00:00') ? '' : $data['ra_masuk']; ?></td>
                                        <td data-label="Istirahat Masuk" align="center" class="<?php
                                                                                        $isEmptyLogIstM = (empty($data['ra_istirahat_masuk']) || $data['ra_istirahat_masuk'] == '00:00:00');
                                                                                        $hasScheduleIstM = !empty($data['r_istirahat_masuk']) && $data['r_istirahat_masuk'] != '00:00:00';
                                                                                        echo ($hasScheduleIstM && $isEmptyLogIstM) || $isLateBreak ? 'bg-red-custom' : '';
                                                                                        ?>"><?php echo (empty($data['ra_istirahat_masuk']) || $data['ra_istirahat_masuk'] == '00:00:00') ? '' : $data['ra_istirahat_masuk']; ?></td>
                                        <td data-label="Jam Pulang" align="center" class="<?php
                                                                                $isEmptyLogOut = (empty($data['ra_keluar']) || $data['ra_keluar'] == '00:00:00');
                                                                                $hasScheduleOut = !empty($data['r_jam_keluar']);
                                                                                echo ($hasScheduleOut && $isEmptyLogOut) ? 'bg-red-custom' : ($isEarlyOut ? 'bg-yellow-custom' : '');
                                                                                ?>"><?php echo (empty($data['ra_keluar']) || $data['ra_keluar'] == '00:00:00') ? '' : $data['ra_keluar']; ?></td>
                                        <?php
                                        $bor_detail_html = '-';
                                        $bor_hasil_display = $data['hasil_kerja'];
                                        $bor_lines = $bor_map[$data['id_realisasi_detail']] ?? [];
                                        if (!empty($bor_lines)) {
                                            $parts_b = [];
                                            $sum_unit = [];
                                            foreach ($bor_lines as $ln_b) {
                                                $sat_b = strtolower(trim($ln_b['satuan'] ?? 'kg'));
                                                if ($sat_b !== 'ekor') $sat_b = 'kg';
                                                if (!isset($sum_unit[$sat_b])) $sum_unit[$sat_b] = 0;
                                                $sum_unit[$sat_b] += (float)$ln_b['qty'];
                                                $lt_b = (int)round((float)$ln_b['qty'] * (int)$ln_b['tarif']);
                                                $parts_b[] = htmlspecialchars(strtoupper($ln_b['nama_pekerjaan'])) . ' : ' . rtrim(rtrim(number_format((float)$ln_b['qty'], 2, '.', ''), '0'), '.') . ' ' . $sat_b . ' x ' . number_format($ln_b['tarif'], 0, ',', '.') . ' = Rp' . number_format($lt_b, 0, ',', '.');
                                            }
                                            $bor_detail_html = implode(', ', $parts_b);
                                            $hs_b = [];
                                            foreach ($sum_unit as $u_b => $q_b) $hs_b[] = rtrim(rtrim(number_format($q_b, 2, '.', ''), '0'), '.') . ' ' . $u_b;
                                            $bor_hasil_display = implode(', ', $hs_b);
                                        }
                                        ?>
                                        <td data-label="Detail Borongan" class="text-left"><?= $bor_detail_html ?></td>
                                        <td data-label="Hasil Kerja"><?php echo $bor_hasil_display; ?></td>
                                        <td data-label="Upah" class="text-right">
                                            <?= rupiah($finalUpah) ?>
                                        </td>
                                        <?php $potTotal = $potTelatValue + $potIstirahatKeluarValue + $potIstirahatMasukValue + $potPulangValue + $potTidakLengkapValue + $potLainnyaValue; ?>
                                        <td data-label="Potongan" class="text-right <?php echo ($potTotal > 0) ? 'bg-red-custom' : ''; ?>"><?= rupiah($potTotal) ?></td>
                                        <td data-label="Lembur" class="text-right">
                                            <?= rupiah($finalLembur) ?>
                                        </td>
                                        <?php if (empty($data['_rangkap']) && $data['status_rkk'] != 'Digantikan') $jml_active++; ?>
                                        <td data-label="Upah Dibayar" class="text-right font-black text-blue-700">
                                            <?= rupiah($upah_setelah_potongan) ?>
                                        </td>
                                        <?php if (empty($data['_rangkap'])) $total += $upah_setelah_potongan; ?>

                                        <?php if (strtolower($_SESSION['role']) != 'admin hr') { ?>
                                            <td data-label="Aksi">
                                                <div class="flex-action" style="display:flex; flex-direction:row; flex-wrap:nowrap; align-items:center;">
                                                    <?php if (strtolower($_SESSION['role']) != 'admin hr') { ?>
                                                        <a href="?page=realisasi&aksi=detail&id=<?php echo $data['id_realisasi_detail']; ?>"
                                                            class="btn btn-xs btn-info" style="background-color: #3498DB; border:none; border-radius:4px; padding:4px 10px;">
                                                            <i class="fa fa-eye"></i> Detail
                                                        </a>
                                                        <?php if (!empty($data['is_borongan_orang']) || !empty($data['is_borongan_pengganti'])) { ?>
                                                        <button type="button"
                                                            class="btn btn-xs btn-borongan" style="background-color: #8b5cf6; color: #fff; border:none; border-radius:4px; padding:4px 10px; margin-left:4px;"
                                                            data-id="<?php echo $data['id_realisasi_detail']; ?>">
                                                            <i class="fa fa-list"></i> Borongan
                                                        </button>
                                                        <?php } ?>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php
                                    $no++;
                                }
                                $jml_karyawan = $jml_active;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php
                // Tap mesin yang PIN-nya tidak terdaftar di master karyawan: grup + logs sendiri.
                // Tampil saja (info), tidak masuk hitungan/total realisasi.
                $ttm_rows = [];
                if (!empty($datatglrealisasi)) {
                    $tglesc = $koneksi->real_escape_string($datatglrealisasi);
                    $qtm = $koneksi->query("SELECT TRIM(userid) AS pin, COUNT(*) AS n_tap, MIN(detail_waktu) AS first_tap, MAX(detail_waktu) AS last_tap FROM tb_record WHERE tgl = '$tglesc' AND TRIM(userid) <> '' AND NOT EXISTS (SELECT 1 FROM ms_karyawan k WHERE k.no_absen IS NOT NULL AND TRIM(k.no_absen) <> '' AND TRIM(k.no_absen) = TRIM(tb_record.userid)) GROUP BY TRIM(userid) ORDER BY MIN(detail_waktu) ASC");
                    while ($qtm && ($rtm = $qtm->fetch_assoc())) $ttm_rows[] = $rtm;
                }
                // Nama dari mesin untuk PIN tak terdaftar (hanya bila ada yang tak terdaftar, biar tidak memperlambat halaman)
                $dev_names_kel = [];
                if (!empty($ttm_rows)) {
                    if (!function_exists('get_device_user_names')) include_once __DIR__ . '/../../device_sync_helper.php';
                    if (function_exists('get_device_user_names')) $dev_names_kel = get_device_user_names($koneksi);
                    // Sembunyikan PIN yang sudah ditarik jadi baris grup (biar tidak dobel info)
                    $exn = [];
                    $qx = $koneksi->query("SELECT RD.nama_karyawan_manual FROM tb_realisasi_detail RD JOIN ms_grup_borongan G ON G.id_grup = RD.id_grup WHERE RD.id_realisasi = '$idrealisasi' AND RD.id_karyawan = 0 AND UPPER(TRIM(G.nama_grup)) = 'TIDAK DALAM RENCANA'");
                    while ($qx && ($rx = $qx->fetch_assoc())) $exn[trim((string)$rx['nama_karyawan_manual'])] = true;
                    $ttm_rows = array_values(array_filter($ttm_rows, function($tm) use ($dev_names_kel, $exn) {
                        $d = trim((string)($dev_names_kel[trim((string)$tm['pin'])] ?? ''));
                        if ($d === '') $d = 'PIN ' . $tm['pin'];
                        return !isset($exn[$d]);
                    }));
                }
                ?>
                <?php if (!empty($ttm_rows)) : ?>
                <div class="px-3 md:px-4 pb-4">
                    <div class="text-sm font-bold text-red-600 uppercase tracking-wider mb-2 border-l-4 border-red-500 pl-2">
                        <i class="fas fa-exclamation-triangle mr-1.5"></i> Tidak Terdaftar (<?= count($ttm_rows) ?> PIN tap tanpa master)
                    </div>
                    <div class="table-responsive rounded-lg border border-red-200">
                        <table class="table table-hover align-middle mb-0 w-full">
                            <thead>
                                <tr style="background:#FEF2F2;">
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">No</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">Nama (dari mesin)</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">PIN / No Absen</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">Grup</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase text-center">Jumlah Tap</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">Tap Pertama</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">Tap Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $ntm = 1; foreach ($ttm_rows as $tm): ?>
                                <tr>
                                    <td class="py-2 px-2"><?= $ntm++ ?></td>
                                    <td class="py-2 px-2 font-bold"><?= htmlspecialchars($dev_names_kel[trim((string)$tm['pin'])] ?? '—') ?></td>
                                    <td class="py-2 px-2"><?= htmlspecialchars($tm['pin']) ?></td>
                                    <td class="py-2 px-2"><span class="px-2 py-0.5 rounded-full font-bold text-xs" style="background:#fee2e2;color:#b91c1c;">TIDAK TERDAFTAR</span></td>
                                    <td class="py-2 px-2 text-center"><?= (int)$tm['n_tap'] ?></td>
                                    <td class="py-2 px-2"><?= htmlspecialchars(substr($tm['first_tap'], 11, 5)) ?></td>
                                    <td class="py-2 px-2"><?= htmlspecialchars(substr($tm['last_tap'], 11, 5)) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 italic mt-2">PIN di atas tap di mesin tapi belum ada di master karyawan — daftarkan dulu di master agar masuk hitungan gaji. Tidak ikut total di atas.</p>
                </div>
                <?php endif; ?>

                <?php
                // Karyawan TERDAFTAR di master yang tap tapi tidak masuk rencana:
                // grup otomatis TIDAK DALAM RENCANA — tampil saja, tanpa baris DB & tanpa master grup.
                $tdr_rows = [];
                if (!empty($datatglrealisasi) && !empty($idrealisasi)) {
                    $te2 = $koneksi->real_escape_string($datatglrealisasi);
                    $qtdr = $koneksi->query("SELECT k.id_karyawan, k.nama_karyawan, TRIM(k.no_absen) AS pin, O.OS_DHK AS oslbl, S.nama_sub_department AS posisi, k.upah,
                        (SELECT MIN(r.detail_waktu) FROM tb_record r WHERE r.tgl = '$te2' AND TRIM(r.userid) = TRIM(k.no_absen)) AS first_tap,
                        (SELECT MAX(r.detail_waktu) FROM tb_record r WHERE r.tgl = '$te2' AND TRIM(r.userid) = TRIM(k.no_absen)) AS last_tap
                        FROM ms_karyawan k
                        LEFT JOIN ms_os_dhk O ON O.id_os_dhk = k.id_os_dhk
                        LEFT JOIN ms_sub_department S ON S.id_sub_department = k.id_sub_department
                        WHERE k.status_karyawan = 'Aktif' AND k.no_absen IS NOT NULL AND TRIM(k.no_absen) <> ''
                        AND EXISTS (SELECT 1 FROM tb_record r WHERE r.tgl = '$te2' AND TRIM(r.userid) = TRIM(k.no_absen))
                        AND NOT EXISTS (SELECT 1 FROM tb_realisasi_detail d WHERE d.id_realisasi = '$idrealisasi' AND d.id_karyawan = k.id_karyawan)
                        ORDER BY k.nama_karyawan ASC");
                    while ($qtdr && ($rtdr = $qtdr->fetch_assoc())) $tdr_rows[] = $rtdr;
                }
                ?>
                <?php if (!empty($tdr_rows)) : ?>
                <div class="px-3 md:px-4 pb-4">
                    <div class="text-sm font-bold text-amber-600 uppercase tracking-wider mb-2 border-l-4 border-amber-500 pl-2">
                        <i class="fas fa-users mr-1.5"></i> Tidak Dalam Rencana (<?= count($tdr_rows) ?> karyawan terdaftar yang tap tapi di luar rencana)
                    </div>
                    <div class="table-responsive rounded-lg border border-amber-200">
                        <table class="table table-hover align-middle mb-0 w-full">
                            <thead>
                                <tr style="background:#FFFBEB;">
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">No</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">Nama Sesuai KTP</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">OS/DHK</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase">Posisi</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase text-center">Tap Masuk</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase text-center">Tap Pulang</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase text-right">Upah (info)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $ntdr = 1; foreach ($tdr_rows as $td): ?>
                                <tr>
                                    <td class="py-2 px-2"><?= $ntdr++ ?></td>
                                    <td class="py-2 px-2 font-bold"><?= htmlspecialchars(strtoupper($td['nama_karyawan'])) ?></td>
                                    <td class="py-2 px-2"><?= htmlspecialchars($td['oslbl'] ?? '-') ?></td>
                                    <td class="py-2 px-2"><?= htmlspecialchars($td['posisi'] ?? '-') ?></td>
                                    <td class="py-2 px-2 text-center"><?= htmlspecialchars(substr($td['first_tap'] ?? '', 11, 5)) ?></td>
                                    <td class="py-2 px-2 text-center"><?= htmlspecialchars((($td['last_tap'] ?? '') !== ($td['first_tap'] ?? '')) ? substr($td['last_tap'], 11, 5) : '') ?></td>
                                    <td class="py-2 px-2 text-right"><?= number_format((float)($td['upah'] ?? 0), 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 italic mt-2">Terdaftar di master dan tap di mesin, tapi tidak masuk rencana tanggal ini — tampil sebagai info, tidak ikut total di atas dan tidak tersimpan ke database.</p>
                </div>
                <?php endif; ?>

                <div class="modal fade" id="modalBorongan" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header" style="background:#8b5cf6; color:#fff;">
                                <h5 class="modal-title font-bold" id="borJudul">Detail Borongan</h5>
                                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" id="borIdDetail" value="">
                                <div style="overflow-x:auto;">
                                    <div id="borRows"></div>
                                </div>
                                <button type="button" id="borModalAdd" class="btn btn-sm btn-success mt-2">+ Tambah Pekerjaan</button>
                                <div class="mt-2 text-right">Total borongan: <b id="borModalGrand">Rp 0</b></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                <button type="button" id="borModalSave" class="btn btn-primary" style="background:#8b5cf6; border:none;">Simpan</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-6 hidden-xs"></div>
                    <div class="col-md-6 col-xs-12 text-right" style="text-align: left !important;">
                        <label class="font-weight-bold text-muted" style="font-size: 13px; margin-bottom:5px; display:block;">
                            Total Realisasi Upah (<?php echo $jml_karyawan; ?> Karyawan)
                        </label>
                        <input readonly type="text" value="<?php echo "Rp " . number_format($total, 0, ',', '.'); ?>" class="form-control total-box" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* 1. Reset wrapper agar tidak menggunakan float bawaan DataTables */
    .dataTables_wrapper {
        display: block !important;
    }

    /* 2. Memaksa area atas (Length & Filter) menjadi satu baris sejajar */
    .dataTables_wrapper::before,
    .dataTables_wrapper::after {
        display: none !important;
    }

    /* 3. Membuat container fleksibel untuk Length (kiri) dan Filter (kanan) */
    #dataTables-example_wrapper .row:first-child {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-bottom: 20px !important;
        width: 100% !important;
    }

    /* 4. Styling Tampil _MENU_ (Kiri) */
    .dataTables_length {
        display: flex !important;
        align-items: center !important;
    }

    .dataTables_length label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_length select {
        padding: 5px 10px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
    }

    /* 5. Styling Cari: (Kanan) */
    .dataTables_filter {
        text-align: right !important;
        display: flex !important;
        justify-content: flex-end !important;
    }

    .dataTables_filter label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_filter input {
        padding: 6px 12px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
        width: 200px !important;
    }

    /* --- STYLING PAGINATE (PREV/NEXT) --- */
    .dataTables_wrapper .dataTables_paginate {
        display: flex !important;
        justify-content: flex-end !important;
        align-items: center !important;
        gap: 4px !important;
        padding-top: 15px !important;
    }

    .dataTables_paginate .paginate_button {
        border: 1px solid #e2e8f0 !important;
        background: white !important;
        border-radius: 6px !important;
        padding: 5px 12px !important;
        color: #475569 !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #f8fafc !important;
        color: #2563eb !important;
        border-color: #cbd5e1 !important;
    }

    .dataTables_paginate .paginate_button.current {
        background: #2563eb !important;
        border-color: #2563eb !important;
        color: white !important;
    }

    .dataTables_paginate .paginate_button.disabled {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
        cursor: not-allowed !important;
    }

    /* --- STYLING INFO --- */
    .dataTables_wrapper .dataTables_info {
        padding-top: 20px !important;
        color: #64748b !important;
        font-size: 13px !important;
    }

    .card-clean {
        background: #fff;
        border: 1px solid #E0E4E8;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.03);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .card-header-clean {
        background-color: #2C3E50;
        color: #ffffff;
        padding: 12px 15px;
        font-weight: 600;
        font-size: 15px;
    }

    .section-title {
        color: #34495E;
        border-left: 4px solid #3498DB;
        padding-left: 8px;
        font-weight: bold;
        margin-bottom: 10px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group {
        margin-bottom: 10px;
    }

    .form-control-clean {
        border-radius: 4px;
        border: 1px solid #CCD1D1;
        padding: 6px 10px;
        background-color: #FAFAFA;
        width: 100%;
        font-size: 13px;
    }

    .form-control-clean:focus {
        border-color: #3498DB;
        box-shadow: none;
        background-color: #FFFFFF;
    }

    .total-box {
        background-color: #EAF2F8;
        border: 1px solid #AED6F1;
        color: #1A5276;
        height: 45px;
        font-size: 18px;
        font-weight: bold;
        text-align: right;
        border-radius: 6px;
        padding-right: 15px;
    }

    /* Styling Dasar Table */
    #dataTables-example {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        width: 100%;
    }

    #dataTables-example thead th {
          padding: 8px 4px !important;
          font-size: 11px !important;
          white-space: nowrap !important;
          vertical-align: middle !important;
          text-align: center !important;
      }

      /* Grid seperti Excel di layar besar (satu tabel utuh, header ikut geser; di HP tetap mode kartu) */
      @media screen and (min-width: 769px) {
          #tabel-realisasi thead th,
          #tabel-realisasi tbody td {
              border: 1px solid #1f2937 !important;
          }
          #tabel-realisasi tbody td {
              padding: 6px 6px !important;
          }
      }

      /* Matikan ikon & highlight sorting DataTables agar header rata seperti Excel */
      #dataTables-example thead th.sorting,
      #dataTables-example thead th.sorting_asc,
      #dataTables-example thead th.sorting_desc {
          background-image: none !important;
      }

    .table-clean tbody td {
        vertical-align: middle;
        font-size: 11px;
        color: #000;
        font-weight: bold;
        border-top: 1px solid #E0E4E8;
        padding: 6px 4px !important;
    }

    .bg-red-custom {
        background-color: #e74c3c !important;
        color: #fff !important;
    }

    .bg-yellow-custom {
        background-color: #f1c40f !important;
        color: #000 !important;
    }

    /* Form Filter & Length Menu DataTables */
    .dataTables_wrapper .dataTables_length select {
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        padding: 2px 6px;
        margin: 0 5px;
    }

    .dataTables_wrapper .dataTables_filter input {
        border-radius: 6px !important;
        border: 1px solid #e5e7eb !important;
        padding: 6px 10px !important;
        outline: none;
        transition: all 0.2s;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #3498DB !important;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    /* PAGINATION STYLING DataTables */
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 2px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border: 1px solid #e5e7eb !important;
        background: white !important;
        border-radius: 6px !important;
        padding: 4px 10px !important;
        color: #4b5563 !important;
        font-weight: 500 !important;
        font-size: 12px;
        cursor: pointer;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #3498DB !important;
        border-color: #3498DB !important;
        color: white !important;
    }

    /* =========================================
       RESPONSIVE TABLE FIT SCREEN (Mobile View) 
       ========================================= */
    @media screen and (max-width: 768px) {
        .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .col-md-12 {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .card-clean {
            border-radius: 0 !important;
            border-left: none !important;
            border-right: none !important;
            margin-bottom: 0 !important;
        }

        .table-responsive {
            border: none !important;
            overflow-x: visible !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        #dataTables-example_wrapper {
            padding: 0 10px;
        }

        #dataTables-example_wrapper .row:first-child {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 15px;
            margin-bottom: 15px !important;
            width: 100% !important;
        }

        .dataTables_filter,
        .dataTables_length {
            display: flex !important;
            width: 100% !important;
            justify-content: flex-start !important;
        }

        .dataTables_filter input {
            width: 100% !important;
            max-width: 100% !important;
        }

        .dataTables_paginate {
            justify-content: center !important;
            flex-wrap: wrap;
        }

        #dataTables-example {
            width: 100% !important;
            margin: 0 !important;
        }

        /* --- STYLING MODERN CARD KARYAWAN --- */
        .table-modern tbody tr {
            display: block;
            margin: 0 5px 20px 5px;
            /* Jarak antar card */
            border: 1px solid #cbd5e1 !important;
            /* Border card */
            border-radius: 12px !important;
            /* Sudut melengkung halus */
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            /* Shadow elegan */
            overflow: hidden;
            padding: 0;
        }

        .table-modern thead {
            display: none !important;
        }

        .table-modern tbody td {
            display: flex;
            flex-direction: column;
            align-items: flex-start !important;
            text-align: left !important;
            /* Memaksa rata kiri */
            padding: 12px 16px !important;
            border: none !important;
            border-bottom: 1px solid #f1f5f9 !important;
            /* Garis tipis antar baris di dalam card */
            width: 100% !important;
            font-size: 14px;
            white-space: normal !important;
        }

        /* OVERRIDE CLASS TEXT-RIGHT AGAR TIDAK NUMPUK DI KANAN PADA MOBILE */
        .table-modern tbody td.text-right,
        .table-modern tbody td.text-center {
            text-align: left !important;
            align-items: flex-start !important;
        }

        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
            width: 100%;
        }

        .table-modern tbody td:last-child {
            border-bottom: none !important;
            background-color: #f8fafc;
            /* Highlight area tombol aksi */
            padding-top: 16px !important;
            padding-bottom: 16px !important;
        }

        .flex-action {
            display: flex;
            justify-content: flex-start;
            width: 100%;
            margin-top: 0;
        }

        .flex-action a {
            width: 100%;
            text-align: center;
            padding: 12px !important;
            font-size: 15px !important;
            border-radius: 8px;
        }

        .card-header-clean {
            font-size: 16px;
            padding: 15px;
        }

        .section-title {
            font-size: 15px;
            margin-top: 15px;
            padding-left: 15px;
        }

        .panel-body {
            padding: 15px 0 !important;
        }

        .panel-body .row,
        .panel-body .form-group {
            padding: 0 15px;
        }

        .panel-body hr {
            margin: 15px !important;
        }

        .form-control-clean {
            font-size: 15px;
            padding: 10px;
        }

        .form-group label {
            font-size: 14px;
        }

        .total-box {
            font-size: 16px;
            height: 45px;
            text-align: left;
            padding-left: 10px;
            margin-bottom: 15px;
        }

        .header-btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        .header-btn-group button,
        .header-btn-group a {
            width: 100%;
            text-align: center;
            padding: 12px !important;
            font-size: 15px;
        }
    }
</style>

<script>
    $(document).ready(function() {
        // Filter grup + cari karyawan
        function applyTabelFilter() {
            const q = $('#cariKaryawan').val().toLowerCase();
            const g = $('#filterGrup').val();
            const vis = {};
            $('#tabel-realisasi tbody tr.data-row').each(function() {
                const $tr = $(this);
                const okG = (g === '' || $tr.data('dept') === g);
                const okQ = ($tr.text().toLowerCase().indexOf(q) !== -1);
                const show = okG && okQ;
                $tr.toggle(show);
                if (show) vis[$tr.data('dept')] = true;
            });
            $('#tabel-realisasi tbody tr.dept-separator, #tabel-realisasi tbody tr.grp-header').each(function() {
                const d = $(this).data('dept');
                $(this).toggle(!!vis[d] && (g === '' || d === g));
            });
        }
        $(document).on('input', '#cariKaryawan', applyTabelFilter);
        $(document).on('change', '#filterGrup', applyTabelFilter);

        // SweetAlert Cleanup (Sync) Confirmation
        $('#btn-cleanup').on('click', function() {
            Swal.fire({
                title: 'Konfirmasi',
                text: 'Tarik data dari record mesin?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b', // Amber
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Tarik Data',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#cleanup-input').val('Cleanup');
                    $(this).closest('form').submit();
                }
            });
        });

        // SweetAlert Tarik Tak Terdaftar Confirmation
        $('#btn-pull-unknown').on('click', function() {
            Swal.fire({
                title: 'Tarik Tak Terdaftar?',
                text: 'Tap PIN yang tidak ada di master akan dimasukkan jadi baris grup TIDAK DALAM RENCANA (upah 0).',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Tarik',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#pull-unknown-input').val('1');
                    $(this).closest('form').submit();
                }
            });
        });
        // SweetAlert Simpan Semua (Keterangan + Denda) Confirmation
        $('#btn-save-all').on('click', function() {
            Swal.fire({
                title: 'Simpan Semua?',
                text: 'Sistem akan menyimpan keterangan dan menghitung denda untuk semua karyawan berdasarkan log saat ini.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Simpan Semua',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#simpan-input').val('Simpan');
                    $(this).closest('form').submit();
                }
            });
        });
    });
</script>
<script>
$(document).ready(function() {
    let borDefaultTarif = 0, borGrupOpt = '', borNamesOpt = '', borCurId = null, borCurCell = null;

    function borEsc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function borSel2(sel) {
        if ($.fn.select2) $(sel).select2({ theme: 'bootstrap4', tags: true, width: '100%', dropdownParent: $('#modalBorongan'), placeholder: '-- Pilih / ketik baru --' });
    }
    function borModalCalc() {
        let grand = 0;
        $('#borRows .bor-row').each(function() {
            const q = parseFloat($(this).find('.bor-qty').val()) || 0;
            const t = parseFloat($(this).find('.bor-tarif').val()) || 0;
            const line = Math.round(q * t);
            grand += line;
            $(this).find('.bor-total').val(line ? 'Rp ' + line.toLocaleString('id-ID') : '');
        });
        $('#borModalGrand').text('Rp ' + grand.toLocaleString('id-ID'));
    }
    function borAllOpt() {
        return '<option value="">-- Pilih / ketik baru --</option>' + borGrupOpt + borNamesOpt;
    }
    function borRowHtml(nama, qty, tarif, satuan) {
        const sat = (String(satuan || 'kg').toLowerCase() === 'ekor') ? 'ekor' : 'kg';
        return '<div class="bor-row" style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap;margin-bottom:8px;">'
            + '<select class="form-control bor-nama" style="min-width:170px;flex:1 1 auto;">' + borAllOpt() + '</select>'
            + '<span style="white-space:nowrap;">:</span>'
            + '<input type="number" step="any" class="form-control text-center bor-qty" style="width:90px;flex:0 0 auto;" value="' + borEsc(qty) + '">'
            + '<select class="form-control bor-satuan" style="width:85px;flex:0 0 auto;">'
            + '<option value="kg"' + (sat === 'kg' ? ' selected' : '') + '>kg</option>'
            + '<option value="ekor"' + (sat === 'ekor' ? ' selected' : '') + '>ekor</option>'
            + '</select>'
            + '<span style="white-space:nowrap;">x</span>'
            + '<input type="number" class="form-control text-right bor-tarif" style="width:130px;flex:0 0 auto;" value="' + borEsc(tarif) + '">'
            + '<span>=</span>'
            + '<input type="text" class="form-control text-right bor-total" style="width:150px;flex:0 0 auto;" readonly>'
            + '<button type="button" class="btn btn-sm btn-danger bor-del" style="flex:0 0 auto;">X</button>'
            + '</div>';
    }

    $(document).on('click', '.btn-borongan', function() {
        borCurId = $(this).data('id');
        borCurCell = $(this).closest('tr').find('td[data-label="Detail Borongan"]');
        $.getJSON('page/realisasi/borongan_ajax.php', { id_detail: borCurId }, function(res) {
            if (!res.ok) {
                Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg || 'Tidak bisa memuat data', confirmButtonColor: '#8b5cf6' });
                return;
            }
            $('#borJudul').text('Detail Borongan — ' + res.nama + (res.grup ? ' (' + res.grup + ')' : ''));
            borDefaultTarif = res.default_tarif || 0;
            const seenBor = {};
            borGrupOpt = '';
            (res.grups || []).forEach(function(g) {
                const nm = g.nama || '';
                if (!nm || seenBor[nm]) return;
                seenBor[nm] = true;
                const rt = (g.harga !== null && g.harga !== '') ? g.harga : '';
                borGrupOpt += '<option value="' + borEsc(nm) + '" data-rate="' + borEsc(rt) + '">' + borEsc(nm) + (rt !== '' ? ' (Rp' + Number(rt).toLocaleString('id-ID') + '/kg)' : '') + '</option>';
            });
            borNamesOpt = '';
            (res.names || []).forEach(function(n) {
                if (!n || seenBor[n]) return;
                seenBor[n] = true;
                borNamesOpt += '<option>' + borEsc(n) + '</option>';
            });
            const tb = $('#borRows');
            tb.empty();
            (res.lines || []).forEach(function(l) {
                const tr = $(borRowHtml(l.nama_pekerjaan, l.qty, l.tarif, l.satuan));
                tr.find('.bor-nama').val(l.nama_pekerjaan);
                tb.append(tr);
            });
            tb.find('.bor-nama').each(function() { borSel2(this); });
            borModalCalc();
            $('#modalBorongan').modal('show');
        }).fail(function() {
            Swal.fire({ icon: 'error', title: 'Gagal', text: 'Tidak bisa menghubungi server', confirmButtonColor: '#8b5cf6' });
        });
    });

    $('#borModalAdd').on('click', function() {
        const tr = $(borRowHtml('', '', borDefaultTarif, 'kg'));
        $('#borRows').append(tr);
        borSel2(tr.find('.bor-nama'));
        borModalCalc();
    });

    // Pilih grup dari dropdown -> tarif otomatis ikut (masih bisa diedit)
    $(document).on('change', '.bor-nama', function() {
        const r = $(this).find(':selected').data('rate');
        if (r !== undefined && r !== null && String(r) !== '') $(this).closest('.bor-row').find('.bor-tarif').val(r);
    });

    $(document).on('click', '.bor-del', function() {
        $(this).closest('.bor-row').remove();
        borModalCalc();
    });
    $(document).on('input change', '.bor-qty, .bor-tarif, .bor-nama, .bor-satuan', borModalCalc);

    $('#borModalSave').on('click', function() {
        const lines = [];
        $('#borRows .bor-row').each(function() {
            const nm = $(this).find('.bor-nama').val();
            if (!nm || !String(nm).trim()) return;
            lines.push({ nama: String(nm).trim(), qty: $(this).find('.bor-qty').val() || 0, tarif: $(this).find('.bor-tarif').val() || 0, satuan: $(this).find('.bor-satuan').val() || 'kg' });
        });
        $.post('page/realisasi/borongan_ajax.php', { id_detail: borCurId, lines: lines }, function(res) {
            if (res.ok) {
                if (res.n > 0 && res.total > 0) {
                    const $tr = borCurCell.closest('tr');
                    borCurCell.html(res.html_detail);
                    $tr.find('td[data-label="Hasil Kerja"]').text(res.hasil_sum);
                    $tr.find('td[data-label="Upah"]').text(res.upah_rp);
                    $tr.find('td[data-label="Upah Dibayar"]').text(res.upah_rp);
                    $tr.find('td[data-label="Potongan"]').text('Rp 0');
                    $tr.find('td[data-label="Lembur"]').text('Rp 0');
                    $('#modalBorongan').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Tersimpan', text: res.n + ' baris rincian', confirmButtonColor: '#8b5cf6' });
                } else {
                    location.reload();
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal', text: res.msg || 'Gagal menyimpan', confirmButtonColor: '#8b5cf6' });
            }
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', title: 'Gagal', text: 'Tidak bisa menghubungi server', confirmButtonColor: '#8b5cf6' });
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>