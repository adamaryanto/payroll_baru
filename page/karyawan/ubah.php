<?php
// Check and create target columns in ms_karyawan if they don't exist
$checkWeekly = $koneksi->query("SHOW COLUMNS FROM ms_karyawan LIKE 'target_mingguan'");
if ($checkWeekly->num_rows == 0) {
    $koneksi->query("ALTER TABLE ms_karyawan ADD COLUMN target_mingguan DECIMAL(10,2) DEFAULT 20.00");
}
$checkMonthly = $koneksi->query("SHOW COLUMNS FROM ms_karyawan LIKE 'target_bulanan'");
if ($checkMonthly->num_rows == 0) {
    $koneksi->query("ALTER TABLE ms_karyawan ADD COLUMN target_bulanan DECIMAL(10,2) DEFAULT 40.00");
}

// Check and create WhatsApp column if it doesn't exist
$checkWhatsApp = $koneksi->query("SHOW COLUMNS FROM ms_karyawan LIKE 'whatsapp'");
if ($checkWhatsApp->num_rows == 0) {
    $koneksi->query("ALTER TABLE ms_karyawan ADD COLUMN whatsapp VARCHAR(20) DEFAULT '' AFTER no_telp");
}

// Check and create No Telp column if it doesn't exist
$checkTelp = $koneksi->query("SHOW COLUMNS FROM ms_karyawan LIKE 'no_telp'");
if ($checkTelp->num_rows == 0) {
    $koneksi->query("ALTER TABLE ms_karyawan ADD COLUMN no_telp VARCHAR(20) NULL DEFAULT NULL AFTER no_bpjs");
}

if (isset($_GET['id'])) {
    $idu = $koneksi->real_escape_string($_GET['id']);
    $tampil = $koneksi->query("SELECT * FROM ms_karyawan WHERE id_karyawan = '$idu'");
    if(!$tampil || $tampil->num_rows==0){
        echo "<div class='p-5 text-center text-red-600'>Data karyawan ID $idu tidak ditemukan. <a href='?page=karyawan' class='text-indigo-600 underline'>Kembali</a></div>"; 
        return;
    }
    $data = $tampil->fetch_assoc();

    $idkaryawan = $data['id_karyawan'];
    $iddepartmen = $data['id_departmen'];
    $idjabatan = $data['id_jabatan'];
    $idsubdept = $data['id_sub_department'];
    $idjadwal = $data['id_jadwal'];
    $noabsen = $data['no_absen'];
    $nama = $data['nama_karyawan'];
    $nobpjs = $data['no_bpjs'];
    $notelp = $data['no_telp'] ?? '';
    $whatsapp = $data['whatsapp'] ?? '';
    $tempatlahir = $data['tempat_lahir'];
    $tgllahir = $data['tgl_lahir'];
    $noktp = $data['no_ktp'];
    $idos = $data['id_os_dhk'];
    $idgolongan = $data['id_golongan'];
    $alamatktp = $data['alamat_ktp'];
    $alamattinggal = $data['alamat_tinggal'];
    $statuskaryawan = $data['status_karyawan'];
    $tglaktif = $data['tgl_aktif'];
    $tglnonaktif = $data['tgl_nonaktif'];
    $jeniskelamin = $data['jenis_kelamin'];
    $agama = $data['agama'];
    $statuskawin = $data['status_kawin'];
    $upah = $data['upah'];
    $target_mingguan = $data['target_mingguan'] ?? 20.00;
    $target_bulanan = $data['target_bulanan'] ?? 40.00;
}

// Roster mendatang karyawan ini (untuk hint Rencana Shift, 14 hari ke depan)
$rk_map = [];
$rk_id = isset($idkaryawan) ? (int)$idkaryawan : 0;
if ($rk_id > 0) {
    $qrk = $koneksi->query("SELECT h.tgl_rkk, d.id_jadwal, j.keterangan, j.jam_masuk, j.jam_keluar FROM tb_rkk_detail d JOIN tb_rkk h ON h.id_rkk=d.id_rkk LEFT JOIN tb_jadwal j ON j.id_jadwal=d.id_jadwal WHERE d.id_karyawan='$rk_id' AND h.tgl_rkk >= CURDATE() ORDER BY h.tgl_rkk ASC LIMIT 14");
    if ($qrk) {
        while ($rrk = $qrk->fetch_assoc()) {
            $rk_map[$rrk['tgl_rkk']] = ($rrk['keterangan'] ?: 'Shift ' . $rrk['id_jadwal']) . ' (' . substr($rrk['jam_masuk'] ?? '', 0, 5) . '-' . substr($rrk['jam_keluar'] ?? '', 0, 5) . ')';
        }
    }
}
// Default Rencana Shift = roster TERAKHIR DIUBAH (mis. via jadwal_excel),
// biar yang barusan diganti langsung kelihatan di sini. Ganti tanggal manual
// untuk lihat/tulis tanggal lain. Nilai default = isi DB, jadi simpan tanpa
// menyentuh Rencana Shift hasilnya tetap sama (di-skip).
$rk_def_date = '';
$rk_def_shift = 0;
$rk_def_txt = '';
if ($rk_id > 0) {
    $qdef = $koneksi->query("SELECT h.tgl_rkk, d.id_jadwal, j.keterangan, j.jam_masuk, j.jam_keluar FROM tb_rkk_detail d JOIN tb_rkk h ON h.id_rkk=d.id_rkk LEFT JOIN tb_jadwal j ON j.id_jadwal=d.id_jadwal WHERE d.id_karyawan='$rk_id' AND h.tgl_rkk >= CURDATE() ORDER BY d.tgl_updt DESC LIMIT 1");
    if ($qdef && $qdef->num_rows > 0) {
        $rdef = $qdef->fetch_assoc();
        $rk_def_date = $rdef['tgl_rkk'];
        $rk_def_shift = (int)$rdef['id_jadwal'];
        $rk_def_txt = ($rdef['keterangan'] ?: 'Shift ' . $rdef['id_jadwal']) . ' (' . substr($rdef['jam_masuk'] ?? '', 0, 5) . '-' . substr($rdef['jam_keluar'] ?? '', 0, 5) . ')';
    }
}
$rk_hint_init = $rk_def_date !== '' ? ('Saat ini: <b>' . htmlspecialchars($rk_def_txt) . '</b> — pilih shift lalu simpan untuk menimpa.') : 'Pilih tanggal untuk lihat roster saat ini.';

if (isset($_POST['update'])) {
    ini_set('display_errors',1); error_reporting(E_ALL);
    $tid = $koneksi->real_escape_string($_POST['tid']);
    $tnama = $koneksi->real_escape_string($_POST['tnama']);
    $tnoabsen = $koneksi->real_escape_string($_POST['tnoabsen']);
    $tnobpjs = $koneksi->real_escape_string($_POST['tnobpjs']);
    $tnotelp = isset($_POST['tnotelp']) ? $koneksi->real_escape_string($_POST['tnotelp']) : '';
    $twhatsapp = isset($_POST['twhatsapp']) ? $koneksi->real_escape_string($_POST['twhatsapp']) : '';
    $tos = isset($_POST['tos']) && $_POST['tos'] !== '' ? (int)$_POST['tos'] : 0;
    $tgolongan = isset($_POST['tgolongan']) && $_POST['tgolongan'] !== '' ? (int)$_POST['tgolongan'] : 0;
    $tjeniskelamin = (isset($_POST['tjeniskelamin']) && in_array($_POST['tjeniskelamin'], ['Laki-laki', 'Perempuan'], true)) ? "'" . $koneksi->real_escape_string($_POST['tjeniskelamin']) . "'" : "NULL";
    $tagama = $koneksi->real_escape_string($_POST['tagama']);
    $ttempatlahir = $koneksi->real_escape_string($_POST['ttempatlahir']);
    $ttanggallahir = isset($_POST['ttanggallahir']) && $_POST['ttanggallahir'] !== '' ? "'" . $koneksi->real_escape_string($_POST['ttanggallahir']) . "'" : "NULL";
    $tstatuskawin = (isset($_POST['tstatuskawin']) && in_array($_POST['tstatuskawin'], ['Kawin', 'Belum Kawin', 'Janda', 'Duda'], true)) ? "'" . $koneksi->real_escape_string($_POST['tstatuskawin']) . "'" : "NULL";
    $tnoktp = $koneksi->real_escape_string($_POST['tnoktp']);
    $tbpjs = $koneksi->real_escape_string($_POST['tnobpjs']);
    $tstatuskaryawan = (isset($_POST['tstatuskaryawan']) && in_array($_POST['tstatuskaryawan'], ['Aktif', 'Non Aktif'], true)) ? "'" . $koneksi->real_escape_string($_POST['tstatuskaryawan']) . "'" : "NULL";
    $talamatktp = isset($_POST['talamatktp']) ? $koneksi->real_escape_string($_POST['talamatktp']) : '';
    $talamattinggal = isset($_POST['talamattinggal']) ? $koneksi->real_escape_string($_POST['talamattinggal']) : '';
    $ttanggalbergabung = isset($_POST['ttanggalbergabung']) && $_POST['ttanggalbergabung'] !== '' ? $koneksi->real_escape_string($_POST['ttanggalbergabung']) : date('Y-m-d');
    $tupah = !empty($_POST['tupah']) ? floatval(str_replace(['Rp', '.', ' '], '', $_POST['tupah'])) : 0;

    $tdepartmen = isset($_POST['tdepartmen']) && $_POST['tdepartmen'] !== '' ? (int)$_POST['tdepartmen'] : 0;
    $tsubdept = isset($_POST['tsubdept']) && $_POST['tsubdept'] !== '' ? (int)$_POST['tsubdept'] : (int)($data['id_sub_department'] ?? 0);
    $tjabatan = isset($_POST['tjabatan']) && $_POST['tjabatan'] !== '' ? (int)$_POST['tjabatan'] : 0;
    $tjadwal = isset($_POST['tjadwal']) && $_POST['tjadwal'] !== '' ? (int)$_POST['tjadwal'] : 0;
    $ttarget_mingguan = !empty($_POST['ttarget_mingguan']) ? floatval($_POST['ttarget_mingguan']) : 20.00;
    $ttarget_bulanan = !empty($_POST['ttarget_bulanan']) ? floatval($_POST['ttarget_bulanan']) : 40.00;

    // --- Rencana Shift (roster per tanggal, opsional; tidak mengubah shift tetap di atas) ---
    // Aturan: hanya diproses bila Shift Rencana dipilih. Tanggal tanpa shift = mode lihat (diabaikan).
    $trenc_date = trim((string)($_POST['trencana_tgl'] ?? ''));
    $trenc_shift = isset($_POST['trencana_shift']) && $_POST['trencana_shift'] !== '' ? (int)$_POST['trencana_shift'] : 0;
    $roster_msg = '';
    $pre_err = '';
    if ($trenc_shift > 0) {
        if ($trenc_date === '') {
            $pre_err = 'Tanggal rencana wajib diisi bila Shift Rencana dipilih.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trenc_date) || $trenc_date < date('Y-m-d')) {
            $pre_err = 'Tanggal rencana tidak valid / sudah lewat.';
        } else {
            $qcs = $koneksi->query("SELECT keterangan, jam_masuk, jam_keluar FROM tb_jadwal WHERE id_jadwal='$trenc_shift' LIMIT 1");
            if (!$qcs || $qcs->num_rows == 0) {
                $pre_err = 'Shift rencana tidak ditemukan.';
            } else {
                $sh_row = $qcs->fetch_assoc();
                $roster_msg = $sh_row['keterangan'] . ' (' . substr($sh_row['jam_masuk'] ?? '', 0, 5) . '-' . substr($sh_row['jam_keluar'] ?? '', 0, 5) . ')';
            }
        }
    }
    if ($pre_err !== '') {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: " . json_encode($pre_err) . ",
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'Ok'
                });
            </script>
        </body>
        </html>";
        return;
    }

    $sql = $koneksi->query("UPDATE ms_karyawan SET 
        id_departmen = '$tdepartmen',
        id_jabatan = '$tjabatan',
        id_sub_department = '$tsubdept',
        nama_karyawan = '$tnama',
        tempat_lahir = '$ttempatlahir',
        tgl_lahir = $ttanggallahir,
        agama = '$tagama',
        status_kawin = $tstatuskawin,
        jenis_kelamin = $tjeniskelamin,
        no_ktp = '$tnoktp',
        no_bpjs = '$tbpjs',
        no_telp = '$tnotelp',
        whatsapp = '$twhatsapp',
        alamat_ktp = '$talamatktp',
        alamat_tinggal = '$talamattinggal',
        tgl_aktif = '$ttanggalbergabung',
        id_os_dhk = '$tos',
        id_golongan = '$tgolongan',
        id_jadwal = '$tjadwal',
        status_karyawan = $tstatuskaryawan,
        upah = '$tupah',
        target_mingguan = '$ttarget_mingguan',
        target_bulanan = '$ttarget_bulanan'
        WHERE id_karyawan = '$tid'
    ");

    if ($sql) {
        // Tulis roster tanggal tsb (tb_rkk + tb_rkk_detail) bila Shift Rencana dipilih
        $roster_info = '';
        if ($trenc_shift > 0) {
            $tid_int = (int)$tid;
            $qh = $koneksi->query("SELECT id_rkk FROM tb_rkk WHERE tgl_rkk='$trenc_date' LIMIT 1");
            if ($qh && $qh->num_rows > 0) { $rh = $qh->fetch_assoc(); $id_rkk = (int)$rh['id_rkk']; }
            else {
                $now = date('Y-m-d H:i:s');
                $ket = $koneksi->real_escape_string('Roster Ubah Karyawan ' . $trenc_date);
                $koneksi->query("INSERT INTO tb_rkk (tgl_rkk, keterangan, jam_kerja, detail_rkk, status_rkk, tgl_status) VALUES ('$trenc_date', '$ket', '12', '$now', '0', '$now')");
                $id_rkk = $koneksi->insert_id;
            }
            if ($id_rkk > 0) {
                // Roster sudah sama -> lewati (biar simpan tanpa menyentuh Rencana Shift tidak dihitung menulis)
                $qc0 = $koneksi->query("SELECT id_jadwal FROM tb_rkk_detail WHERE id_rkk='$id_rkk' AND id_karyawan='$tid_int' LIMIT 1");
                $rk_same = ($qc0 && $qc0->num_rows > 0) && (int)$qc0->fetch_assoc()['id_jadwal'] === $trenc_shift;
                if ($rk_same) {
                    $roster_info = "<br><small>Roster " . htmlspecialchars($trenc_date) . " sudah sama.</small>";
                } else {
                require_once __DIR__ . '/../rkk/import_lib.php';
                $rd_dept = rx_valid_dept($koneksi, $tdepartmen ?? 0);
                $rd_sub = rx_valid_sub($koneksi, $tsubdept ?? 0, $rd_dept);
                $rd_vdep = ($rd_dept > 0) ? "'$rd_dept'" : "NULL";
                $rd_vsub = ($rd_sub > 0) ? "'$rd_sub'" : "NULL";
                $qc2 = $koneksi->query("SELECT id_rkk_detail FROM tb_rkk_detail WHERE id_rkk='$id_rkk' AND id_karyawan='$tid_int' LIMIT 1");
                if ($qc2 && $qc2->num_rows > 0) {
                    $cr2 = $qc2->fetch_assoc();
                    $dq = $koneksi->query("UPDATE tb_rkk_detail SET id_jadwal='$trenc_shift' WHERE id_rkk_detail='" . (int)$cr2['id_rkk_detail'] . "'");
                } else {
                    $dq = $koneksi->query("INSERT INTO tb_rkk_detail (id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, upah, status_rkk, id_jadwal, potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) VALUES ('$id_rkk', '$tid_int', NULL, $rd_vdep, $rd_vsub, '0', 'Hadir', '$trenc_shift', '0', '0', '0', NOW())");
                }
                $roster_info = $dq ? ("<br><small>Roster " . htmlspecialchars($trenc_date) . ": " . htmlspecialchars($roster_msg) . "</small>") : ("<br><small class=\"text-red-600\">Roster " . htmlspecialchars($trenc_date) . " gagal: " . htmlspecialchars($koneksi->error) . "</small>");
                }
            } else {
                $roster_info = "<br><small class=\"text-red-600\">Roster gagal: header tanggal tidak bisa dibuat.</small>";
            }
        }
        // Sync ke mesin jangan bikin blank - timeout cepet & gak blokir save
        $sync_results = [];
        $sync_message = '';
        $sync_success = true;
        try {
            include_once __DIR__ . '/../../device_sync_helper.php';
            create_sync_log_table($koneksi);
            // Set timeout pendek biar gak loading lama
            $old_timeout = ini_get('max_execution_time');
            set_time_limit(15);
            $sync_results = sync_karyawan_to_devices($koneksi, $tid);
            set_time_limit($old_timeout ?: 30);
            if(file_exists(__DIR__.'/../../zk_access_helper.php')){
                include_once __DIR__.'/../../zk_access_helper.php';
                if(function_exists('zk_update_user')){
                    $zkResult = @zk_update_user($tnoabsen, $_POST['tnama']);
                    if(empty($zkResult['success'])){
                        if(function_exists('zk_queue_update')) @zk_queue_update($tnoabsen, $_POST['tnama']);
                    }
                }
            }
        } catch (Throwable $e) {
            $sync_results = [];
            $sync_success = false;
            $sync_message = "Sync skipped: ".$e->getMessage();
            error_log("Sync ubah karyawan $tid error: ".$e->getMessage());
        }
        
        // Prepare sync status message
        $sync_message = '';
        $sync_success = true;
        foreach($sync_results as $device_id => $result) {
            if(isset($result['status'])) {
                log_sync_activity($koneksi, 'karyawan_update', $device_id, $tid, 
                                 $result['status'], $result['message']);
                
                if($result['status'] == 'error') {
                    $sync_success = false;
                    $sync_message .= "\\n• " . $result['device'] . ": " . $result['message'];
                }
            }
        }
        
        $jsMsg2 = $sync_message ? json_encode("<br><br><strong>Sinkronisasi ke mesin:</strong><br><small>".nl2br(htmlspecialchars($sync_message))."</small>") : "''";
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    html: 'Data karyawan berhasil diperbarui." . $roster_info . "' + $jsMsg2,
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=karyawan';
                });
            </script>
        </body>
        </html>";
        exit;
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal Perbarui Data: " . $koneksi->error . "',
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'Ok'
                });
            </script>
        </body>
        </html>";
    }
}
?>

<div class="container-fluid px-4 mt-5 mb-5">
    <div class="card border-0 shadow-lg rounded-xl overflow-hidden">

        <div class="card-header bg-white border-b border-gray-100 py-5 px-6 flex flex-row items-center justify-between">
            <div class="flex-grow">
                <h3 class="text-2xl font-extrabold text-indigo-600 tracking-tight m-0"><i class="fas fa-user-tie mr-3 text-indigo-600"></i>Ubah Data Karyawan</h3>
                <p class="text-sm text-gray-500 mt-1">Perbarui informasi karyawan di bawah ini.</p>
            </div>
            <div class="flex-shrink-0 ml-4">
                <a href="?page=karyawan" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition duration-200 shadow-md">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
            </div>
        </div>

        <form method="POST" id="formUbahKaryawan" enctype="multipart/form-data">
            <div class="card-body p-6 bg-gray-50/30">

                <!-- Section 1: Personal & Position -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-user-circle mr-2 text-lg"></i> Informasi Pribadi & Posisi
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">

                        <input type="hidden" name="tid" value="<?= $idkaryawan; ?>">

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. Absen</label>
                            <input type="text" name="tnoabsen" value="<?= $noabsen; ?>" readonly class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-100 text-gray-500 font-bold cursor-not-allowed">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input autocomplete="off" type="text" name="tnama" value="<?= $nama; ?>" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all outline-none" placeholder="Masukkan nama lengkap">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Karyawan</label>
                            <select name="tstatuskaryawan" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="Aktif" <?= ($statuskaryawan == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Non Aktif" <?= ($statuskaryawan == 'Non Aktif') ? 'selected' : ''; ?>>Non Aktif</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Bagian</label>
                            <select name="tdepartmen" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih Bagian -" data-delete-route="bagian">
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Bagian Baru...</option>
                                <?php
                                $q_dept = $koneksi->query("SELECT * FROM ms_departmen");
                                while ($d = $q_dept->fetch_assoc()) {
                                    $sel = ($d['id_departmen'] == $iddepartmen || $d['nama_departmen'] == $iddepartmen) ? 'selected' : '';
                                    echo "<option value='" . $d['id_departmen'] . "' $sel>" . $d['nama_departmen'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Posisi</label>
                            <select name="tjabatan" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih Posisi -" data-delete-route="jabatan">
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Posisi Baru...</option>
                                <?php
                                $q_jab = $koneksi->query("SELECT * FROM ms_jabatan");
                                while ($d = $q_jab->fetch_assoc()) {
                                    $sel = ($d['id_jabatan'] == $idjabatan || $d['jabatan'] == $idjabatan) ? 'selected' : '';
                                    echo "<option value='" . $d['id_jabatan'] . "' $sel>" . $d['jabatan'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>



                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">OS / DHK <span class="text-red-500">*</span></label>
                            <select name="tos" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih -" data-delete-route="os_dhk" required>
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Baru...</option>
                                <?php
                                $q_os = $koneksi->query("SELECT * FROM ms_os_dhk");
                                while($d = $q_os->fetch_assoc()) {
                                    $sel = ($d['id_os_dhk'] == $idos) ? 'selected' : '';
                                    echo "<option value='".$d['id_os_dhk']."' $sel>".$d['OS_DHK']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Golongan</label>
                            <div>
                                <select name="tgolongan" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih -" data-delete-route="golongan">
                                    <option value=""></option>
                                    <option value="add_new" class="font-bold text-indigo-600">+ Tambah Baru...</option>
                                    <?php
                                    $q_gol = $koneksi->query("SELECT * FROM ms_golongan");
                                    while($d = $q_gol->fetch_assoc()) {
                                        $sel = ($d['id_golongan'] == $idgolongan) ? 'selected' : '';
                                        echo "<option value='".$d['id_golongan']."' $sel>".$d['golongan']."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Shift / Jadwal <span class="text-red-500">*</span></label>
                            <select name="tjadwal" required class="w-full select2 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="">- Pilih Shift -</option>
                                <?php
                                $q_shift = $koneksi->query("SELECT * FROM tb_jadwal");
                                while($d = $q_shift->fetch_assoc()) {
                                    $sel = ($d['id_jadwal'] == $idjadwal) ? 'selected' : '';
                                    echo "<option value='".$d['id_jadwal']."' $sel>".$d['keterangan']." (".$d['jam_masuk']." - ".$d['jam_keluar'].")</option>";
                                }
                                ?>
                            </select>
                        </div>

                         <div class="form-group target-fields" style="display: none;">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Target Mingguan (Jam)</label>
                            <input type="number" step="0.1" name="ttarget_mingguan" value="<?= htmlspecialchars($target_mingguan); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div class="form-group target-fields" style="display: none;">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Target Bulanan (Jam)</label>
                            <input type="number" step="0.1" name="ttarget_bulanan" value="<?= htmlspecialchars($target_bulanan); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Jenis Kelamin</label>
                            <select name="tjeniskelamin" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="Laki-laki" <?= ($jeniskelamin == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?= ($jeniskelamin == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Agama</label>
                            <select name="tagama" class="w-full select2 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="">- Pilih Agama -</option>
                                <?php
                                $agamas = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Budha', 'Khonghucu'];
                                foreach ($agamas as $a) {
                                    $sel = ($a == $agama) ? 'selected' : '';
                                    echo "<option value='$a' $sel>$a</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tempat Lahir</label>
                            <input type="text" name="ttempatlahir" value="<?= $tempatlahir; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Contoh: Jakarta">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Lahir</label>
                            <input type="date" name="ttanggallahir" value="<?= $tgllahir; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Kawin</label>
                            <select name="tstatuskawin" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="" <?= ($statuskawin == '') ? 'selected' : ''; ?>>- Pilih Status -</option>
                                <option value="Kawin" <?= ($statuskawin == 'Kawin') ? 'selected' : ''; ?>>Kawin</option>
                                <option value="Belum Kawin" <?= ($statuskawin == 'Belum Kawin') ? 'selected' : ''; ?>>Belum Kawin</option>
                                <option value="Janda" <?= ($statuskawin == 'Janda') ? 'selected' : ''; ?>>Janda</option>
                                <option value="Duda" <?= ($statuskawin == 'Duda') ? 'selected' : ''; ?>>Duda</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section Rencana Shift (roster per tanggal, opsional) -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-2 flex items-center">
                        <i class="fas fa-calendar-alt mr-2 text-lg"></i> Rencana Shift
                    </h4>
                    <p class="text-xs text-gray-500 mb-4">Menampilkan roster <b>terakhir diubah</b> (mis. via Ubah Jadwal Excel) — ganti tanggal untuk lihat/tulis roster tanggal lain (dipakai Tarik Data / Excel / PDF). Shift / Jadwal di atas (master) dipakai bila tanggal itu kosong.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Rencana</label>
                            <input type="date" name="trencana_tgl" id="trencana_tgl" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($rk_def_date); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                            <p id="rk_hint" class="text-xs text-gray-500 mt-1"><?= $rk_hint_init; ?></p>
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Shift Rencana</label>
                            <select name="trencana_shift" id="trencana_shift" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="">- Pilih Shift (opsional) -</option>
                                <?php
                                $q_rshift = $koneksi->query("SELECT * FROM tb_jadwal ORDER BY jam_masuk");
                                while($d = $q_rshift->fetch_assoc()) {
                                    $rsel = ($rk_def_shift > 0 && (int)$d['id_jadwal'] === $rk_def_shift) ? ' selected' : '';
                                    echo "<option value='".$d['id_jadwal']."'".$rsel.">".$d['keterangan']." (".$d['jam_masuk']." - ".$d['jam_keluar'].")</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Documents & Domisile -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-file-alt mr-2 text-lg"></i> Dokumen & Domisili
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. KTP</label>
                            <input type="text" name="tnoktp" value="<?= $noktp; ?>" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="16 Digit No. KTP">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. BPJS</label>
                            <input type="text" name="tnobpjs" value="<?= $nobpjs; ?>" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Masukkan No. BPJS">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. Telp</label>
                            <input type="text" name="tnotelp" value="<?= $notelp; ?>" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="cth: 08123456789">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">WhatsApp</label>
                            <input type="text" name="twhatsapp" value="<?= $whatsapp; ?>" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="cth: 08123456789">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Alamat Lengkap (KTP)</label>
                            <textarea name="talamatktp" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Jl. Contoh No. 123..."><?= $alamatktp; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Alamat Tinggal</label>
                            <textarea name="talamattinggal" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Jl. Contoh No. 123..."><?= $alamattinggal; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Payroll Info -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-money-bill-wave mr-2 text-lg"></i> Informasi Penggajian
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Bergabung</label>
                            <input type="date" name="ttanggalbergabung" value="<?= $tglaktif; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Upah Harian Baru (Rp) <span class="text-gray-400">(Boleh 0 untuk tim boneless)</span></label>
                            <input type="text" name="tupah" id="tupah_format" value="<?= number_format($upah, 0, ',', '.'); ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-indigo-700" placeholder="0">
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-footer bg-gray-50 px-8 py-5 flex items-center justify-between border-t border-gray-200">
                <p class="text-xs text-gray-400 italic">Tanda <span class="text-red-500 font-bold">*</span> wajib diisi.</p>

                <div class="flex gap-3">
                    <button type="reset" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 font-bold hover:bg-gray-100 transition-all cursor-pointer">
                        <i class="fas fa-undo mr-2"></i> Batal
                    </button>

                    <button type="submit" name="update" value="Update" class="px-8 py-2.5 border-0 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-0.5">
                        <i class="fas fa-save mr-2"></i> Perbarui Data
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    /* Menghilangkan panah pada input number agar lebih bersih */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
        function toggleTargetFields() {
            var selectVal = $('select[name="tjadwal"]').val();
            var selectedText = $('select[name="tjadwal"] option[value="' + selectVal + '"]').text() || '';
            if (selectedText.toUpperCase().indexOf('FLEXI') !== -1 || selectedText.toUpperCase().indexOf('FLEKSI') !== -1) {
                $('.target-fields').show();
            } else {
                $('.target-fields').hide();
            }
        }
        
        // Run on load, delayed load, and on change (including select2)
        toggleTargetFields();
        setTimeout(toggleTargetFields, 100);
        setTimeout(toggleTargetFields, 500);
        $('select[name="tjadwal"]').on('change change.select2', function() {
            toggleTargetFields();
        });

        $('#tupah_format').on('input', function() {
            let val = $(this).val().replace(/[^0-9]/g, '');
            if (val !== "") {
                $(this).val(new Intl.NumberFormat('id-ID').format(parseInt(val)));
            }
        });

        // 1. Logika untuk Select2 Manage
        // Hint roster saat tanggal rencana diganti (cuma info, dropdown tidak ikut berubah)
        var RK_MAP = <?= json_encode($rk_map ?: new stdClass); ?>;
        $('#trencana_tgl').on('change', function() {
            var v = $(this).val();
            if (v && RK_MAP[v]) {
                $('#rk_hint').html('Saat ini: <b>' + $('<div>').text(RK_MAP[v]).html() + '</b> — pilih shift lalu simpan untuk menimpa.');
            } else if (v) {
                $('#rk_hint').text('Belum ada roster tanggal ini — pilih shift lalu simpan untuk membuat.');
            } else {
                $('#rk_hint').text('Pilih tanggal untuk lihat roster saat ini.');
            }
        });
        $('.select2-manage').on('change', function() {
            var $select = $(this);
            var selectedVal = $select.val();
            var route = $select.data('delete-route');

            if (selectedVal === 'add_new') {
                var label = $select.closest('.form-group').find('label').text().replace('*', '').trim();
                var newData = prompt("Masukkan " + label + " Baru:");

                if (newData && newData.trim() !== "") {
                    $.post('page/ajax/tambah_master.php', {
                        value: newData.trim(),
                        route: route
                    }, function(res) {
                        if (res.success) {
                            var newOption = new Option(res.value, res.id, true, true);
                            $select.append(newOption).trigger('change');
                        } else {
                            alert('Gagal menambah data: ' + res.message);
                            $select.val('').trigger('change');
                        }
                    }, 'json');
                } else {
                    $select.val('').trigger('change');
                }
            }
        });

        // 2. Logika untuk Reset Form
        $('#formUbahKaryawan').on('reset', function() {
            setTimeout(function() {
                $('.select2-manage').trigger('change.select2');
                $('.select2').trigger('change.select2');
            }, 10);
        });

    });
</script>