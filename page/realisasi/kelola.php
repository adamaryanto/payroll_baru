<?php
// 2026-10-26: Admin HR view-only di Realisasi (lihat boleh, ubah tidak).
$is_admin_hr_ro = (strtolower($_SESSION['role'] ?? '') === 'admin hr');
// Self-heal master grup + kolom id_grup (penting di DB lama sebelum query utama jalan)
include_once __DIR__ . '/../grup_borongan/_setup.php';
// Kolom pengganti (tukar karyawan: nama tetap, tercatat "Digantikan oleh ...")
$checkGantiColK = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_pengganti'");
if ($checkGantiColK->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_detail ADD COLUMN id_pengganti INT NULL DEFAULT NULL");
}
// 2026-10-24: index yang sama seperti di list Realisasi (dibuat sekali saja,
// kalau sudah ada tidak diulang) — mempercepat semua query Kelola yang memfilter
// tb_realisasi_detail per id_realisasi.
$__idx_real_k = $koneksi->query("SHOW INDEX FROM tb_realisasi_detail WHERE Key_name = 'idx_realisasi'");
if ($__idx_real_k && $__idx_real_k->num_rows == 0) {
    @$koneksi->query("ALTER TABLE tb_realisasi_detail ADD INDEX idx_realisasi (id_realisasi)");
}
// Helpers for cross-midnight time comparisons
include_once __DIR__ . '/sync_lib.php';

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

    // UBAH UPAH TIDAK LAGI DI KELOLA (dihapus 2026-10-24 atas permintaan Reza):
    // kolom Upah di tabel Kelola kembali jadi teks saja. Ubah upah hanya lewat
    // halaman Detail (kolom UPAH (POKOK) di sana sudah bisa diedit).

    // OTOMATIS: semua karyawan yang tap tapi TIDAK ADA di rencana tanggal ini
    // dimasukkan ke grup "TIDAK DALAM RENCANA" (satu tabel, tanpa panel terpisah).
    include_once __DIR__ . '/../../device_sync_helper.php';
    if (function_exists('ensure_karyawan_pin_table')) ensure_karyawan_pin_table($koneksi);
    if (function_exists('refresh_karyawan_pin_map')) {
        $qpm = @$koneksi->query("SELECT COUNT(*) AS c, MIN(tgl_updt) AS mn FROM ms_karyawan_pin");
        $rpm = $qpm ? $qpm->fetch_assoc() : null;
        $n_pin = (int)($rpm['c'] ?? 0);
        $old_pin = !empty($rpm['mn']) ? strtotime($rpm['mn']) : 0;
        if ($n_pin === 0 || ($old_pin && $old_pin < time() - 43200)) refresh_karyawan_pin_map($koneksi);
    }
    if (function_exists('realisasi_proses_tdr')) {
        realisasi_proses_tdr($koneksi, (int)$idrealisasi, null, false);
    }

    // Preload peta ganti/pengganti SEKALI (pengganti 2 subquery korelasi di query utama
    // yang sebelumnya dieksekusi per baris). tb_rkk_update kecil, jadi preload penuh.
    $map_menggantikan = []; // key: id_karyawan|id_rkk
    $qmg = $koneksi->query("SELECT U1.id_karyawan AS kid, RD2.id_rkk AS rkk, K3.nama_karyawan AS nm FROM tb_rkk_update U1 JOIN tb_rkk_update U2 ON U1.id_rkk_detail = U2.id_rkk_detail JOIN ms_karyawan K3 ON U2.id_karyawan = K3.id_karyawan JOIN tb_rkk_detail RD2 ON U1.id_rkk_detail = RD2.id_rkk_detail WHERE U1.status = 'Pengganti' AND U2.status = 'Digantikan'");
    while ($qmg && ($mrow = $qmg->fetch_assoc())) {
        $mk = $mrow['kid'] . '|' . $mrow['rkk'];
        if (!isset($map_menggantikan[$mk])) $map_menggantikan[$mk] = $mrow['nm'];
    }
    $map_digantikan = []; // key: id_rkk_detail
    $qdg = $koneksi->query("SELECT U3.id_rkk_detail AS rkkd, K4.nama_karyawan AS nm FROM tb_rkk_update U3 JOIN tb_rkk_update U4 ON U3.id_rkk_detail = U4.id_rkk_detail JOIN ms_karyawan K4 ON U4.id_karyawan = K4.id_karyawan WHERE U3.status = 'Digantikan' AND U4.status = 'Pengganti'");
    while ($qdg && ($drow = $qdg->fetch_assoc())) {
        if (!isset($map_digantikan[$drow['rkkd']])) $map_digantikan[$drow['rkkd']] = $drow['nm'];
    }

    $tampil = $koneksi->query("SELECT 
        A.*, 
        A.r_upah as upahkaryawan, 
        -- Jika id_karyawan 0, tampilkan '-', jika tidak tampilkan no_absen asli
        IF(A.id_karyawan = 0, '-', B.no_absen) as no_absen_tampil, 
        -- Jika id_karyawan 0, ambil dari kolom manual, jika tidak ambil dari master
        IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_tampil,
        -- Prioritaskan departmen dari detail (penting untuk manual employee)
        COALESCE(D.nama_departmen, D_Manual.nama_departmen) as nama_departmen,
        COALESCE(JB.jabatan, JB_PG.jabatan) as nama_sub_department,
        C.tgl_realisasi, 
        COALESCE(O.OS_DHK, O_M.OS_DHK) as label_os,
        COALESCE(G_M.golongan, G.golongan) as label_gol,
        (UPPER(TRIM(COALESCE(G.golongan, ''))) = 'BORONGAN' OR UPPER(TRIM(COALESCE(G_M.golongan, ''))) = 'BORONGAN') as is_golongan_borongan,
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
        PG.nama_karyawan as pengganti_langsung
        FROM tb_realisasi_detail A 
        LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
        LEFT JOIN ms_karyawan PG ON A.id_pengganti = PG.id_karyawan
        LEFT JOIN tb_realisasi C ON A.id_realisasi = C.id_realisasi
        -- Join ke master departmen dari karyawan master
        LEFT JOIN ms_departmen D ON B.id_departmen = D.id_departmen
        LEFT JOIN ms_sub_department BB ON B.id_sub_department = BB.id_sub_department
        LEFT JOIN ms_jabatan JB ON B.id_jabatan = JB.id_jabatan
        LEFT JOIN ms_jabatan JB_PG ON PG.id_jabatan = JB_PG.id_jabatan
        -- Join ke master departmen dari kolom manual di detail (untuk manual employee)
        LEFT JOIN ms_departmen D_Manual ON A.id_departmen = D_Manual.id_departmen
        LEFT JOIN ms_sub_department BB_Manual ON A.id_sub_department = BB_Manual.id_sub_department
        LEFT JOIN ms_os_dhk O ON B.id_os_dhk = O.id_os_dhk
        LEFT JOIN ms_golongan G ON B.id_golongan = G.id_golongan
        -- Fallback OS/Golongan untuk baris TIDAK DALAM RENCANA (id_karyawan=0)
        LEFT JOIN ms_os_dhk O_M ON O_M.id_os_dhk = A.id_os_dhk
        LEFT JOIN ms_golongan G_M ON G_M.id_golongan = A.id_golongan
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
    // Toleransi keterlambatan (menit) — SAMA kayak halaman tarik data
    $tol = function_exists('load_toleransi') ? load_toleransi($koneksi) : ['masuk' => 10, 'pulang' => 5, 'ist_keluar' => 5, 'ist_masuk' => 10];
} else {
    $datatglrealisasi    = "";
    $dataketerangan      = "";
    $datadetailrealisasi = "";
    $datajamkerja        = "";
    $datastatusrealisasi = 0;
}

// 2026-10-26: Tarik Data saat Realisasi sudah Approved: boleh untuk Owner + Admin Master
// (dulu Owner saja). Role lain (Admin HR, Kepala Pabrik) tetap disembunyikan saat Approved;
// saat belum Approved, Tarik Data tetap tampil untuk semua role seperti sebelumnya.
if ($datastatusrealisasi >= 2) {
    $status = in_array(strtolower($_SESSION['role'] ?? ''), ["owner", "admin master"], true) ? "" : "hidden";
} else {
    $status = "";
}

$simpan = @$_POST['simpan'];
// Server gate Admin HR view-only: tolak Simpan Keterangan saja.
// Tarik Data (cleanup) SENGAJA tidak diblokir — Admin HR tetap boleh Tarik Data.
if ($is_admin_hr_ro && !empty($simpan)) {
    echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
        Swal.fire({ icon: "error", title: "Akses Ditolak", text: "Admin HR tidak bisa menyimpan Keterangan Realisasi.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.history.back(); });
    </script></body></html>';
    exit;
}
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

// 2026-10-27: Hapus 1 baris karyawan di Kelola (tombol Hapus di kolom Aksi).
// Menghapus detail + rincian borongannya (urutan sama seperti hapus_massal).
// 2026-10-27 (koreksi terakhir): Hapus per baris di Kelola HANYA untuk
// Admin Master + Kepala Pabrik (sama seperti Hapus Terpilih di List), dan
// Realisasi yang sudah Approved (>=2) TIDAK BISA dihapus barisnya.
// Admin HR dan Owner tidak bisa hapus. Baris harus milik Realisasi ini
// (id_realisasi dicocokkan) supaya POST yang diutak-atik tidak bisa menghapus
// baris Realisasi lain.
$hapus_detail = @$_POST['hapus_detail'];
if (!empty($hapus_detail)) {
    $id_detail_hapus = (int)@$_POST['id_detail'];
    $id_real_hapus = (int)($idrealisasi ?? 0);
    $err_hapus = '';
    if (!in_array(strtolower($_SESSION['role'] ?? ''), ["admin master", "kepala pabrik"], true)) $err_hapus = 'Hapus karyawan hanya untuk Admin Master dan Kepala Pabrik.';
    elseif ($datastatusrealisasi >= 2) $err_hapus = 'Realisasi sudah Approved, tidak bisa menghapus karyawan.';
    elseif ($id_detail_hapus <= 0 || $id_real_hapus <= 0) $err_hapus = 'Baris yang mau dihapus tidak valid.';
    else {
        $cek_hapus = $koneksi->query("SELECT id_realisasi_detail FROM tb_realisasi_detail WHERE id_realisasi_detail = $id_detail_hapus AND id_realisasi = $id_real_hapus LIMIT 1");
        if (!$cek_hapus || $cek_hapus->num_rows == 0) $err_hapus = 'Baris tidak ditemukan di Realisasi ini.';
    }
    if ($err_hapus !== '') {
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>Swal.fire({ icon: "error", title: "Gagal", text: "' . $err_hapus . '", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.location.href = "?page=realisasi&aksi=kelola&id=' . $id_real_hapus . '"; });</script></body></html>';
        exit;
    }
    $koneksi->query("DELETE FROM tb_realisasi_borongan WHERE id_realisasi_detail = $id_detail_hapus");
    $ok_hapus = $koneksi->query("DELETE FROM tb_realisasi_detail WHERE id_realisasi_detail = $id_detail_hapus AND id_realisasi = $id_real_hapus");
    if ($ok_hapus) {
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>Swal.fire({ icon: "success", title: "Berhasil", text: "Karyawan berhasil dihapus dari Realisasi ini.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.location.href = "?page=realisasi&aksi=kelola&id=' . $id_real_hapus . '"; });</script></body></html>';
    } else {
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>Swal.fire({ icon: "error", title: "Gagal", text: "Gagal menghapus karyawan.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.location.href = "?page=realisasi&aksi=kelola&id=' . $id_real_hapus . '"; });</script></body></html>';
    }
    exit;
}

$cleanup = @$_POST['cleanup'];
if ($cleanup) {
    // Timer tiap tahap (dicatat ke tb_sync_log tipe tarik_timing untuk diagnosa lemot)
    $ph_start = microtime(true); $ph_pull = 0; $ph_sync = 0; $ph_tdr = 0;
    // 0. Tarik dulu dari semua mesin (SISTEM SAMA dgn halaman tarik data) -> masuk tb_record,
    //    jadi halaman tarik data ikut kebagian tanpa kerja 2x. Pull ini idempotent (dobel diabaikan).
    //    Dilewati bila user memilih "Pakai Data Lokal" (data masih segar, cek via check_pull.php).
    if (!function_exists('pull_attendance_from_all_devices')) {
        include_once __DIR__ . '/../../device_sync_helper.php';
    }
    $pull_info = '';
    $qr = $koneksi->query("SELECT tgl_realisasi, id_rkk FROM tb_realisasi WHERE id_realisasi = '$idrealisasi'");
    $dr = $qr ? $qr->fetch_assoc() : null;
    if ($dr && !empty($dr['tgl_realisasi'])) {
        $t0 = $dr['tgl_realisasi'];
        $t1 = date('Y-m-d', strtotime('+1 day', strtotime($t0))); // shift malam lewat tengah malam
    }
    // Tanggal manual (datepicker): timpa t0/t1 agar yg ditarik mengikuti pilihan
    $tgl_mode = ($_POST['tgl_mode'] ?? 'auto') === 'manual' ? 'manual' : 'auto';
    $tgl_manual = trim((string)($_POST['tgl_manual'] ?? ''));
    if ($tgl_mode === 'manual' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_manual)) {
        $t0 = $tgl_manual;
        $t1 = date('Y-m-d', strtotime('+1 day', strtotime($t0)));
    }
    $skip_pull = !empty($_POST['skip_pull']);
    if (!$skip_pull && function_exists('pull_attendance_from_all_devices') && isset($t0)) {
        $pp0 = microtime(true);
        $pr = pull_attendance_from_all_devices($koneksi, $t0, $t1);
        $ph_pull = microtime(true) - $pp0;
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
    } elseif ($skip_pull && isset($t0)) {
        $pull_info = 'Pakai data lokal (sudah ditarik segar, SOAP mesin dilewati). ';
    }
    $count = 0;
    // Preload log 2 hari SEKALI (dipakai sync + blok TDR di bawah).
    // Dibangun SETELAH pull mesin agar tap barusan ikut.
    $bulk_logs = []; $bulk_ok = false;
    if (isset($t0) && $t0 !== '' && isset($t1) && $t1 !== '') {
        $t0e = $koneksi->real_escape_string($t0); $t1e = $koneksi->real_escape_string($t1);
        $qbulk = $koneksi->query("SELECT userid, tgl, status, detail_waktu FROM tb_record WHERE tgl IN ('$t0e','$t1e') ORDER BY tgl ASC, detail_waktu ASC");
        if ($qbulk) {
            $bulk_ok = true;
            while ($qb2 = $qbulk->fetch_assoc()) $bulk_logs[$qb2['userid']][] = $qb2;
        }
    }
    // Mode tarik: rencana (pull+sync baris rencana saja) | tdr (pull+TDR saja) | semua (lengkap, fallback)
    $mode_cleanup = strtolower(trim((string)($_POST['cleanup_mode'] ?? 'semua')));
    if (!in_array($mode_cleanup, ['semua', 'rencana', 'tdr'], true)) $mode_cleanup = 'semua';
    $count = 0;
    $ps0 = microtime(true);
    if ($mode_cleanup !== 'tdr') $count = syncRealisasiData($koneksi, $idrealisasi, $bulk_logs, $bulk_ok, (isset($t0) ? $t0 : null));
    $ph_sync = microtime(true) - $ps0;
    // Lanjutan: PIN tak terdaftar jadi baris grup TIDAK DALAM RENCANA (upah 0, anti-dobel)
    $added = 0;
    $pt0 = microtime(true);
    if ($mode_cleanup !== 'rencana' && isset($t0) && $t0 !== '') {
        $rtdr = function_exists('realisasi_proses_tdr') ? realisasi_proses_tdr($koneksi, $idrealisasi, $t0) : ['added' => 0];
        $added = (int)($rtdr['added'] ?? 0);
    }
    if ($mode_cleanup === 'rencana') {
        $msg_ok = $pull_info . 'Berhasil sinkron ' . $count . ' data rencana dari record mesin.';
    } elseif ($mode_cleanup === 'tdr') {
        $msg_ok = $pull_info . ($added > 0 ? 'Berhasil memproses ' . $added . ' tap luar rencana (grup TIDAK DALAM RENCANA).' : 'Tidak ada tap luar rencana yang baru.');
    } else {
        $msg_ok = $pull_info . 'Berhasil menarik ' . $count . ' data dari record mesin'
            . ($added > 0 ? ' ditambah ' . $added . ' tak terdaftar (grup TIDAK DALAM RENCANA).' : '');
    }
    if ($tgl_mode === 'manual' && isset($t0)) $msg_ok .= ' [Tanggal tarik: ' . $t0 . ']';
    $ph_tdr = microtime(true) - $pt0;
    // Catat durasi tiap tahap untuk diagnosa lemot berikutnya
    if (function_exists('log_sync_activity')) {
        try {
            if (function_exists('create_sync_log_table')) create_sync_log_table($koneksi);
            $ph_total = round(microtime(true) - $ph_start, 2);
            log_sync_activity($koneksi, 'tarik_timing', 0, null, 'info', 'kelola id=' . $idrealisasi . ' tgl=' . (isset($t0) ? $t0 : '-') . ' mode=' . $mode_cleanup . ($skip_pull ? ' skip_pull' : '') . ' pull=' . round($ph_pull, 2) . 's sync=' . round($ph_sync, 2) . 's tdr=' . round($ph_tdr, 2) . 's total=' . $ph_total . 's');
        } catch (Throwable $e) {}
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
                    text: "' . addslashes($msg_ok) . '",
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
    // Toleransi (menit) — SAMA kayak halaman tarik data
    $tol_b = function_exists('load_toleransi') ? load_toleransi($koneksi) : ['masuk' => 10, 'pulang' => 5, 'ist_keluar' => 5, 'ist_masuk' => 10];

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
        // Flexi murni: bebas semua denda waktu
        $is_flex_b = is_flexible_shift($row['shift_ket'] ?? '');

        if (!$is_total_missing && !$is_flex_b) {
            // Late Entrance (lewat toleransi)
            if ($has_masuk && $s_masuk != '00:00:00' && late_minutes($row['ra_masuk'], $s_masuk) > $tol_b['masuk']) {
                $p_telat = $gd_masuk;
            }
            // Early Departure (lewat toleransi)
            if ($has_keluar && $s_keluar != '00:00:00' && early_minutes($row['ra_keluar'], $s_keluar) > $tol_b['pulang']) {
                $p_pulang = $gd_pulang;
            }
            // Early Break (lewat toleransi)
            if ($has_ist_keluar && $s_ist_keluar != '00:00:00' && early_minutes($row['ra_istirahat_keluar'], $s_ist_keluar) > $tol_b['ist_keluar']) {
                $p_ist_awal = $gd_istirahat_awal;
            }
            // Late Break Return (lewat toleransi)
            if ($has_ist_masuk && $s_ist_masuk != '00:00:00' && late_minutes($row['ra_istirahat_masuk'], $s_ist_masuk) > $tol_b['ist_masuk']) {
                $p_ist_telat = $gd_istirahat_telat;
            }

            $shift_ket = $row['shift_ket'] ?? '';
            $is_flexible = is_flexible_shift($shift_ket);
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
                                    <input type="text" name="tketerangan" value="<?php echo $dataketerangan; ?>" placeholder="Masukkan keterangan..." class="form-control text-base py-2" autocomplete="off"<?= $is_admin_hr_ro ? ' disabled' : '' ?> />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 md:px-5 bg-white border-t border-gray-200" <?php echo $status; ?>>
                        <div class="flex flex-wrap items-center" style="gap:10px;">
                        <div class="inline-flex flex-wrap items-center rounded-xl border border-gray-200 bg-gray-50 px-3 py-1.5" style="gap:8px;" title="Tanggal tarik: Otomatis = ikut tanggal realisasi di atas. Manual = ikut tanggal pilihan.">
                            <span class="text-sm text-gray-600 font-bold"><i class="fas fa-calendar-alt mr-1"></i>Tanggal tarik:</span>
                            <select id="tgl-mode-select" class="px-2 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="auto">Otomatis</option>
                                <option value="manual">Manual</option>
                            </select>
                            <input type="date" id="tgl-manual-input" value="<?php echo $datatglrealisasi; ?>" data-default="<?php echo $datatglrealisasi; ?>" disabled class="px-2 py-2 border border-gray-300 rounded-lg text-sm bg-gray-100 text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            <span class="text-gray-300 font-bold">|</span>
                            <button type="button" id="btn-cleanup" data-tgl="<?php echo $datatglrealisasi; ?>" data-id="<?php echo (int)$idrealisasi; ?>" class="btn btn-warning bg-amber-500 hover:bg-amber-600 border-none px-6 py-2 text-white" title="Tarik dari mesin + sinkron rencana + proses tap luar rencana.">
                                <i class="fas fa-sync-alt mr-1.5"></i> Tarik Data
                            </button>
                        </div>
                        </div>
                        <input type="hidden" name="simpan" id="simpan-input" value="">
                        <input type="hidden" name="cleanup" id="cleanup-input" value="">
                        <input type="hidden" name="cleanup_mode" id="cleanup-mode-input" value="semua">
                        <input type="hidden" name="skip_pull" id="skip-pull-input" value="">
                    </div>
                </form>

                <!-- Modal progress Tarik Data (persentase per tahap) -->
                <div class="modal fade" id="tarikProgressModal" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-body text-center p-4">
                                <h5 id="tprog-title" class="font-bold mb-3">Menarik Data...</h5>
                                <div class="progress" style="height:26px;">
                                    <div id="tprog-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%;background-color:#2563eb;">0%</div>
                                </div>
                                <p id="tprog-step" class="text-sm text-gray-600 mt-2 mb-0">Menyiapkan...</p>
                            </div>
                        </div>
                    </div>
                </div>

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
                        unset($grup_list['TIDAK DALAM RENCANA']);
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
                                // Peta grup master (fallback bila baris detail belum punya id_grup)
                                $grup_nama_k = [];
                                $qgn_k = $koneksi->query("SELECT id_grup, nama_grup FROM ms_grup_borongan");
                                while ($qgn_k && ($rgn_k = $qgn_k->fetch_assoc())) $grup_nama_k[(int)$rgn_k['id_grup']] = $rgn_k['nama_grup'];
                                $grup_member_k = []; $grup_byname_k = [];
                                $qgm_k = $koneksi->query("SELECT ga.id_karyawan, MIN(ga.id_grup) AS g, K.nama_karyawan AS nm FROM ms_grup_borongan_anggota ga LEFT JOIN ms_karyawan K ON K.id_karyawan = ga.id_karyawan GROUP BY ga.id_karyawan");
                                while ($qgm_k && ($rgm_k = $qgm_k->fetch_assoc())) {
                                    $grup_member_k[(int)$rgm_k['id_karyawan']] = (int)$rgm_k['g'];
                                    $nm_u = strtoupper(trim((string)($rgm_k['nm'] ?? '')));
                                    if ($nm_u !== '' && !isset($grup_byname_k[$nm_u])) $grup_byname_k[$nm_u] = (int)$rgm_k['g'];
                                }
                                // Baris pertama per orang (MIN id) yang dihitung; baris rangkap (beda grup, nama sama) tampil tapi tidak dijumlah
                                $keep_ids = []; $cnt_person = [];
                                $qk = $koneksi->query("SELECT MIN(RD.id_realisasi_detail) AS k, COUNT(*) AS c, IF(RD.id_karyawan = 0, CONCAT('M:', IFNULL(RD.nama_karyawan_manual, '')), CONCAT('K:', RD.id_karyawan)) AS pk FROM tb_realisasi_detail RD LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail WHERE RD.id_realisasi = '$idrealisasi' AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan') GROUP BY pk");
                                while ($qk && ($rkk = $qk->fetch_assoc())) { $keep_ids[(int)$rkk['k']] = true; $cnt_person[$rkk['pk']] = (int)$rkk['c']; }
                                $jml_active = 0;
                                $last_dept = null;
                                // Kolom Aksi (tombol Detail) untuk SEMUA role (2026-10-24):
                                // sebelumnya kolom ini disembunyikan untuk Admin HR, jadi
                                // Detail + ubah gaji di Detail praktis cuma bisa dijangkau
                                // role lain. Approve/Unapprove diatur di list Realisasi
                                // (realisasi.php + app.php), bukan di sini.
                                $ncol = 17;
                                $aksi_th = '<th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center" style="white-space:nowrap;">Aksi</th>';

                                // Kumpulkan baris + hitung grupnya dulu (fallback master), lalu
                                // kelompokkan per grup (urutan kemunculan pertama) agar 1 grup = 1 section.
                                // Tanpa ini, baris fallback (id_grup kosong) kepisah di bawah jadi section dobel.
                                $dept_of_row = function($row) use ($grup_member_k, $grup_byname_k, $grup_nama_k) {
                                    if (!empty($row['label_grup'])) return strtoupper(trim($row['label_grup']));
                                    $gid_fb = 0;
                                    $kid_fb = (int)($row['id_karyawan'] ?? 0);
                                    if ($kid_fb > 0 && isset($grup_member_k[$kid_fb])) $gid_fb = $grup_member_k[$kid_fb];
                                    if ($gid_fb <= 0) {
                                        $nm_fb = strtoupper(trim((string)($row['nama_tampil'] ?? '')));
                                        if ($nm_fb !== '' && isset($grup_byname_k[$nm_fb])) $gid_fb = $grup_byname_k[$nm_fb];
                                    }
                                    if ($gid_fb > 0 && isset($grup_nama_k[$gid_fb])) return strtoupper(trim($grup_nama_k[$gid_fb]));
                                    return 'TANPA GRUP';
                                };
                                $ordered_rows = [];
                                $bucket_order = [];
                                $buckets = [];
                                while ($tmp_row = $tampil->fetch_assoc()) {
                                    if (trim((string)($tmp_row['nama_tampil'] ?? '')) === '') continue;
                                    $tmp_row['_dept'] = $dept_of_row($tmp_row);
                                    $ordered_rows[] = $tmp_row;
                                }
                                foreach ($ordered_rows as $tmp_row) {
                                    $bd_tmp = $tmp_row['_dept'];
                                    if (!isset($buckets[$bd_tmp])) { $buckets[$bd_tmp] = []; $bucket_order[] = $bd_tmp; }
                                    $buckets[$bd_tmp][] = $tmp_row;
                                }
                                // TIDAK DALAM RENCANA selalu paling bawah, bukan section grup biasa.
                                $tdr_key = null;
                                foreach ($bucket_order as $bd_tmp) {
                                    if (strtoupper(trim((string)$bd_tmp)) === 'TIDAK DALAM RENCANA') { $tdr_key = $bd_tmp; break; }
                                }
                                if ($tdr_key !== null) {
                                    $bucket_order = array_values(array_filter($bucket_order, function ($v) use ($tdr_key) { return $v !== $tdr_key; }));
                                    $bucket_order[] = $tdr_key;
                                }
                                // KARYAWAN TAMBAHAN (input manual) selalu section paling bawah setelah grup rencana.
                                $kt_key = null;
                                foreach ($bucket_order as $bd_tmp) {
                                    if (strtoupper(trim((string)$bd_tmp)) === 'KARYAWAN TAMBAHAN') { $kt_key = $bd_tmp; break; }
                                }
                                if ($kt_key !== null) {
                                    $bucket_order = array_values(array_filter($bucket_order, function ($v) use ($kt_key) { return $v !== $kt_key; }));
                                    $bucket_order[] = $kt_key;
                                }
                                $ordered_rows = [];
                                foreach ($bucket_order as $bd_tmp) foreach ($buckets[$bd_tmp] as $tmp_row) $ordered_rows[] = $tmp_row;
                                unset($buckets, $bucket_order);
                                // TDR dipisah: tidak ikut tabel grup utama, ditampilkan
                                // sebagai tabel ringkas sendiri di paling bawah.
                                $tdr_rows = [];
                                $ordered_rows = array_values(array_filter($ordered_rows, function ($row) use (&$tdr_rows) {
                                    if (strtoupper(trim((string)($row['_dept'] ?? ''))) === 'TIDAK DALAM RENCANA') { $tdr_rows[] = $row; return false; }
                                    return true;
                                }));

                                foreach ($ordered_rows as $data) {
                                    $dept_now = $data['_dept'];
                                    // Ambil dari peta preload (pengganti subquery korelasi)
                                    if (!isset($data['menggantikan'])) $data['menggantikan'] = $map_menggantikan[$data['id_karyawan'] . '|' . $data['id_rkk']] ?? null;
                                    if (!isset($data['digantikan_oleh'])) $data['digantikan_oleh'] = $map_digantikan[$data['id_rkk_detail']] ?? null;
                                    if ($dept_now !== $last_dept) {
                                        $last_dept = $dept_now;
                                        $no = 1;
                                        $dept_esc = htmlspecialchars($dept_now, ENT_QUOTES);
                                        $is_tdr = (strtoupper(trim((string)$dept_now)) === 'TIDAK DALAM RENCANA');
                                        if ($is_tdr) {
                                            // Bukan header grup: baris biasa paling bawah.
                                            echo '<tr class="tdr-separator" data-dept="' . $dept_esc . '"><td colspan="' . $ncol . '" data-label="Bagian" style="background:#F3F4F6; font-weight:bold; text-align:center; text-transform:uppercase; padding:8px 4px; border-top:2px solid #9CA3AF;">' . htmlspecialchars($dept_now) . '</td></tr>';
                                        } else {
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

                                    $isLate = ($has_masuk && !empty($data['r_jam_masuk']) && late_minutes($data['ra_masuk'], $data['r_jam_masuk']) > $tol['masuk']);
                                    $isEarlyOut = ($has_keluar && !empty($data['r_jam_keluar']) && early_minutes($data['ra_keluar'], $data['r_jam_keluar']) > $tol['pulang']);
                                    $isLateBreak = ($has_ist_masuk && !empty($data['r_istirahat_masuk']) && late_minutes($data['ra_istirahat_masuk'], $data['r_istirahat_masuk']) > $tol['ist_masuk']);
                                    $isEarlyBreak = ($has_ist_keluar && !empty($data['r_istirahat_keluar']) && early_minutes($data['ra_istirahat_keluar'], $data['r_istirahat_keluar']) > $tol['ist_keluar']);

                                     $isTotalMissing = (!$has_masuk && !$has_keluar);
                                     $hasIncompleteMain = ($has_masuk xor $has_keluar);
                                     $shift_ket = $data['shift_ket'] ?? '';
                                     $is_flexible = is_flexible_shift($shift_ket);
                                     $isRestExpected = (!$is_flexible && !empty($data['r_istirahat_keluar']) && $data['r_istirahat_keluar'] != '00:00:00');
                                     $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));
                                     // Flexi murni: tanpa merah telat/pulang-cepat/istirahat
                                     if ($is_flexible) { $isLate = $isEarlyOut = $isLateBreak = $isEarlyBreak = false; }

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
                                        // Flexi murni: bebas semua denda waktu
                                        $potPulangValue = (!$is_flexible && $isEarlyOut && !$isTotalMissing) ? $globalDendaPulang : 0;
                                        $potTidakLengkapValue = (!$is_flexible && !$isTotalMissing && ($hasIncompleteMain || $hasIncompleteBreak)) ? $globalDendaTidakLengkap : 0;
                                        $potTelatValue = (!$is_flexible && $isLate) ? $globalDendaMasuk : 0;
                                        $potIstirahatKeluarValue = (!$is_flexible && $isEarlyBreak) ? $globalDendaIstirahatKeluar : 0;
                                        $potIstirahatMasukValue = (!$is_flexible && $isLateBreak) ? $globalDendaIstirahatMasuk : 0;
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
                                        <td data-label="Jam Kerja" align="center"><?php echo substr($data['r_jam_masuk'] ?: '', 0, 5); ?></td>
                                        <td data-label="Jam Masuk" align="center" title="<?php echo $data['ra_masuk'] ?? ''; ?>" class="<?php
                                                                            $isEmptyLog = (empty($data['ra_masuk']) || $data['ra_masuk'] == '00:00:00');
                                                                            // Kosong = merah (default), telat vs jadwal = merah
                                                                            echo $isEmptyLog || $isLate ? 'bg-red-custom' : '';
                                                                            ?>"><?php echo (empty($data['ra_masuk']) || $data['ra_masuk'] == '00:00:00') ? '' : substr($data['ra_masuk'], 0, 5); ?></td>
                                        <td data-label="Istirahat Masuk" align="center" title="<?php echo $data['ra_istirahat_masuk'] ?? ''; ?>" class="<?php
                                                                                        $isEmptyLogIstM = (empty($data['ra_istirahat_masuk']) || $data['ra_istirahat_masuk'] == '00:00:00');
                                                                                        // Kosong = merah HANYA bila istirahat memang dijadwalkan; telat = merah
                                                                                        echo ($isRestExpected && $isEmptyLogIstM) || $isLateBreak ? 'bg-red-custom' : '';
                                                                                        ?>"><?php echo (empty($data['ra_istirahat_masuk']) || $data['ra_istirahat_masuk'] == '00:00:00') ? '' : substr($data['ra_istirahat_masuk'], 0, 5); ?></td>
                                        <td data-label="Jam Pulang" align="center" title="<?php echo $data['ra_keluar'] ?? ''; ?>" class="<?php
                                                                                $isEmptyLogOut = (empty($data['ra_keluar']) || $data['ra_keluar'] == '00:00:00');
                                                                                // Kosong = merah (default), pulang cepat = kuning
                                                                                echo $isEmptyLogOut ? 'bg-red-custom' : ($isEarlyOut ? 'bg-yellow-custom' : '');
                                                                                ?>"><?php echo (empty($data['ra_keluar']) || $data['ra_keluar'] == '00:00:00') ? '' : substr($data['ra_keluar'], 0, 5); ?></td>
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

                                            <td data-label="Aksi">
                                                <div class="flex-action" style="display:flex; flex-direction:row; flex-wrap:nowrap; align-items:center; justify-content:center; gap:4px;">
                                                        <a href="?page=realisasi&aksi=detail&id=<?php echo $data['id_realisasi_detail']; ?>"
                                                            class="btn btn-xs btn-info" style="background-color: #3498DB; border:none; border-radius:4px; padding:5px 9px; font-size:12px; flex:0 0 auto; white-space:nowrap; text-align:center; box-sizing:border-box;">
                                                            <i class="fa fa-eye"></i> Detail
                                                        </a>
                                                        <?php if (!empty($data['is_borongan_orang']) || !empty($data['is_borongan_pengganti']) || !empty($data['is_golongan_borongan'])) { ?>
                                                        <button type="button"
                                                            class="btn btn-xs btn-borongan" style="background-color: #8b5cf6; color: #fff; border:none; border-radius:4px; padding:5px 9px; font-size:12px; flex:0 0 auto; white-space:nowrap; text-align:center; box-sizing:border-box;"
                                                            data-id="<?php echo $data['id_realisasi_detail']; ?>">
                                                            <i class="fa fa-list"></i> Borongan
                                                        </button>
                                                        <?php } ?>
                                                        <?php if (in_array(strtolower($_SESSION['role'] ?? ''), ["admin master", "kepala pabrik"], true) && $datastatusrealisasi < 2) { ?>
                                                        <button type="button"
                                                            class="btn btn-xs btn-hapus-detail" style="background-color: #e11d48; color: #fff; border:none; border-radius:4px; padding:5px 9px; font-size:12px; flex:0 0 auto; white-space:nowrap; text-align:center; box-sizing:border-box;"
                                                            onclick="hapusDetailReal(<?php echo (int)$data['id_realisasi_detail']; ?>)">
                                                            <i class="fa fa-trash"></i> Hapus
                                                        </button>
                                                        <?php } ?>
                                                </div>
                                            </td>
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

                <?php if (!empty($tdr_rows)): ?>
                <div class="mt-4">
                    <div class="table-responsive rounded-lg border border-amber-200">
                        <table class="table table-hover table-clean align-middle mb-0" id="tabel-tdr">
                            <thead>
                                <tr>
                                    <th colspan="7" class="text-left font-bold text-[13px] uppercase" style="background:#FEF3C7; color:#92400E; padding:10px 8px;">
                                        <div class="flex items-center justify-between gap-2">
                                            <span>TIDAK DALAM RENCANA (<?= count($tdr_rows) ?> KARYAWAN TERDAFTAR YANG TAP TAPI DI LUAR RENCANA)</span>
                                            <a href="page/realisasi/excelrealisasi_baru.php?id=<?= (int)$idrealisasi ?>&jenis=tdr"
                                                class="px-2 py-1 text-[12px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded border border-emerald-300 transition-colors flex items-center whitespace-nowrap" title="Download Excel TIDAK DALAM RENCANA">
                                                <i class="fas fa-file-excel mr-1"></i> Excel
                                            </a>
                                        </div>
                                    </th>
                                </tr>
                                <tr style="background:#FFFBEB;">
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">No</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Nama Sesuai KTP</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">OS/DHK</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Posisi</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Tap Masuk</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Tap Pulang</th>
                                    <th class="py-2 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Upah (Info)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $ntdr = 1; foreach ($tdr_rows as $td): ?>
                                <?php
                                    $tm_in = trim((string)($td['ra_masuk'] ?? ''));
                                    $tm_out = trim((string)($td['ra_keluar'] ?? ''));
                                    if ($tm_in === '00:00:00') $tm_in = '';
                                    if ($tm_out === '00:00:00') $tm_out = '';
                                    $tm_in = $tm_in !== '' ? substr($tm_in, 0, 5) : '';
                                    $tm_out = $tm_out !== '' ? substr($tm_out, 0, 5) : '';
                                    $info_upah = (int)($td['upah_rkk'] ?? 0);
                                    if ($info_upah <= 0) $info_upah = (int)($td['upahkaryawan'] ?? 0);
                                ?>
                                <tr class="data-row">
                                    <td data-label="No"><?= $ntdr++ ?></td>
                                    <td data-label="Nama Sesuai KTP"><strong><?= htmlspecialchars((string)($td['nama_tampil'] ?? '')) ?></strong></td>
                                    <td data-label="OS/DHK"><?= htmlspecialchars((string)($td['label_os'] ?? '')) ?></td>
                                    <td data-label="Posisi"><?= htmlspecialchars((string)($td['nama_sub_department'] ?? '')) ?></td>
                                    <td data-label="Tap Masuk"><?= htmlspecialchars($tm_in) ?></td>
                                    <td data-label="Tap Pulang"><?= htmlspecialchars($tm_out) ?></td>
                                    <td data-label="Upah (Info)" class="text-right"><?= number_format($info_upah, 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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

<script>
// 2026-10-27: Hapus 1 baris karyawan di Kelola — konfirmasi dulu, lalu POST ke
// halaman Kelola yang sama (GET id ikut terbawa lewat URL) dengan hapus_detail.
function hapusDetailReal(idDetail) {
    function doSubmit() {
        var f = document.createElement('form'); f.method = 'POST'; f.action = window.location.href;
        var h1 = document.createElement('input'); h1.type = 'hidden'; h1.name = 'hapus_detail'; h1.value = '1'; f.appendChild(h1);
        var h2 = document.createElement('input'); h2.type = 'hidden'; h2.name = 'id_detail'; h2.value = idDetail; f.appendChild(h2);
        document.body.appendChild(f); f.submit();
    }
    if (typeof Swal === 'undefined') { if (confirm('Hapus karyawan ini dari Realisasi ini?')) doSubmit(); return; }
    Swal.fire({ title: 'Hapus Karyawan?', text: 'Baris ini akan dihapus permanen dari Realisasi ini, beserta rincian borongannya.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48', cancelButtonColor: '#64748b', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' }).then(function(r) { if (r.isConfirmed) doSubmit(); });
}
</script>

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

        // Tarik bertahap via AJAX dgn progress bar + % jujur per tahap selesai.
        // Urutan tahap mengikuti mode: rencana = pull+sync, tdr = pull+tdr.
        // Selama tahap berjalan, bar merayap + detik berjalan agar terlihat hidup.
        var tarikTickTimer = null, tarikCreepTimer = null, tarikOrigTitle = null;
        function tarikSetProgress(pct, label) {
            pct = Math.max(0, Math.min(100, Math.round(pct)));
            var bar = document.getElementById('tprog-bar');
            bar.style.width = pct + '%';
            bar.textContent = pct + '%';
            document.getElementById('tprog-step').textContent = label;
            // Judul tab ikut nunjukin persen (fetch tidak memutar spinner tab browser)
            try { document.title = '(' + pct + '%) Tarik Data...'; } catch (e) {}
        }
        function tarikRestoreTitle() {
            try { if (tarikOrigTitle !== null) document.title = tarikOrigTitle; } catch (e) {}
        }
        function tarikStopTimers() {
            if (tarikTickTimer) { clearInterval(tarikTickTimer); tarikTickTimer = null; }
            if (tarikCreepTimer) { clearInterval(tarikCreepTimer); tarikCreepTimer = null; }
        }
        // Tanggal efektif: manual (datepicker) atau otomatis (ikut tgl realisasi).
        // Ganti isi datepicker otomatis pindah ke Manual; pilih Otomatis mengembalikan default.
        function tarikEffDate(btn) {
            var sel = document.getElementById('tgl-mode-select');
            var inp = document.getElementById('tgl-manual-input');
            if (sel && sel.value === 'manual' && inp && /^\d{4}-\d{2}-\d{2}$/.test(inp.value)) return inp.value;
            return (btn && btn.getAttribute('data-tgl')) || '';
        }
        function tarikNextDay(ymd) {
            var p = ymd.split('-');
            var d = new Date(+p[0], +p[1] - 1, +p[2]);
            d.setDate(d.getDate() + 1);
            return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
        }
        // Otomatis = datepicker mati (ngikutin tgl realisasi). Manual = datepicker hidup.
        function tarikSyncTglUI() {
            var sel = document.getElementById('tgl-mode-select');
            var inp = document.getElementById('tgl-manual-input');
            if (!sel || !inp) return 'auto';
            var manual = sel.value === 'manual';
            inp.disabled = !manual;
            inp.classList.toggle('bg-gray-100', !manual);
            inp.classList.toggle('text-gray-500', !manual);
            inp.classList.toggle('bg-white', manual);
            if (!manual && inp.getAttribute('data-default')) inp.value = inp.getAttribute('data-default');
            return manual ? 'manual' : 'auto';
        }
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'tgl-manual-input') {
                var sel = document.getElementById('tgl-mode-select');
                if (sel) sel.value = 'manual';
                tarikSyncTglUI();
            }
            if (e.target && e.target.id === 'tgl-mode-select') tarikSyncTglUI();
        });
        document.addEventListener('DOMContentLoaded', function() { tarikSyncTglUI(); });
        function tarikAjax(id, step, tgl) {
            var ctrl = ('AbortController' in window) ? new AbortController() : null;
            var timer = null;
            if (ctrl) timer = setTimeout(function() { try { ctrl.abort(); } catch (e) {} }, 12 * 60 * 1000);
            var body = 'id=' + encodeURIComponent(id) + '&step=' + encodeURIComponent(step);
            if (tgl) body += '&tgl=' + encodeURIComponent(tgl);
            return fetch('page/realisasi/tarik_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                signal: ctrl ? ctrl.signal : undefined
            }).then(function(r) {
                if (timer) clearTimeout(timer);
                return r.text().then(function(t) {
                    try { return JSON.parse(t); }
                    catch (e) { throw new Error('Server mengembalikan respon tak terduga (HTTP ' + r.status + '). Pastikan file tarik_ajax.php & sync_lib.php sudah ter-upload.'); }
                });
            }).catch(function(err) {
                if (timer) clearTimeout(timer);
                if (err && err.name === 'AbortError') throw new Error('Koneksi terputus (timeout 12 menit). Coba rentang tanggal lebih pendek.');
                throw err;
            });
        }
        function tarikRun(id, mode, skipPull, tgl) {
            var steps = [];
            if (!skipPull) steps.push({ key: 'pull', label: 'Menarik data dari mesin...' });
            if (mode !== 'tdr') steps.push({ key: 'sync', label: 'Sinkron baris rencana...' });
            if (mode !== 'rencana') steps.push({ key: 'tdr', label: 'Proses tap luar rencana...' });
            $('#tarikProgressModal').modal('show');
            try { tarikOrigTitle = document.title; } catch (e) { tarikOrigTitle = null; }
            document.getElementById('tprog-title').textContent = 'Menarik Data' + (tgl ? ' (' + tgl + ')' : '') + '...';
            tarikSetProgress(2, 'Mulai...');
            var results = {};
            var chain = Promise.resolve();
            steps.forEach(function(st, idx) {
                chain = chain.then(function() {
                    var p0 = Math.round(idx / steps.length * 100);
                    var p1 = Math.round((idx + 1) / steps.length * 100);
                    var t0 = Date.now();
                    tarikStopTimers();
                    tarikSetProgress(p0, st.label + ' (0 dtk)');
                    // Detik berjalan — bukti request hidup
                    tarikTickTimer = setInterval(function() {
                        var s = Math.floor((Date.now() - t0) / 1000);
                        document.getElementById('tprog-step').textContent = st.label + ' (' + s + ' dtk)';
                    }, 1000);
                    // Bar merayap menuju akhir rentang tahap selama menunggu
                    tarikCreepTimer = setInterval(function() {
                        var bar = document.getElementById('tprog-bar');
                        var cur = parseFloat(bar.style.width) || 0;
                        if (cur < p1 - 2) tarikSetProgress(cur + Math.max(0.4, (p1 - 2 - cur) * 0.06), st.label + ' (' + Math.floor((Date.now() - t0) / 1000) + ' dtk)');
                    }, 600);
                    return tarikAjax(id, st.key, tgl).then(function(j) {
                        tarikStopTimers();
                        if (!j || !j.ok) throw new Error((j && j.msg) || 'Tahap ' + st.key + ' gagal.');
                        results[st.key] = j;
                        tarikSetProgress(p1, st.label + ' selesai.');
                    });
                });
            });
            chain.then(function() {
                tarikStopTimers();
                tarikSetProgress(100, 'Selesai.');
                tarikRestoreTitle();
                var parts = [];
                if (results.pull) parts.push('Mesin: ' + results.pull.saved + ' baru (' + results.pull.secs + ' dtk)');
                if (results.sync) parts.push('Rencana: ' + results.sync.count + ' tersinkron (' + results.sync.secs + ' dtk)');
                if (results.tdr) parts.push('Luar rencana: ' + results.tdr.added + ' diproses (' + results.tdr.secs + ' dtk)');
                setTimeout(function() {
                    $('#tarikProgressModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: parts.join(' • ') || 'Selesai.', confirmButtonColor: '#2563eb', confirmButtonText: 'OK' })
                        .then(() => window.location.reload());
                }, 400);
            }).catch(function(err) {
                tarikStopTimers();
                tarikRestoreTitle();
                $('#tarikProgressModal').modal('hide');
                Swal.fire({ icon: 'error', title: 'Gagal', text: (err && err.message) || 'Tarik data gagal.', confirmButtonColor: '#e11d48' });
            });
        }
        // Tarik Data: tarik mesin + sinkron rencana + proses tap luar rencana sekaligus
        function submitCleanup(btn, mode, confirmTitle, confirmText, confirmBtn) {
            var tgl = tarikEffDate(btn);
            var id = btn.getAttribute('data-id') || '';
            Swal.fire({
                title: confirmTitle,
                text: confirmText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: confirmBtn,
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Tarik Data',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => { if (result.isConfirmed) tarikRun(id, mode, false, tgl); });
        }
        // SweetAlert Cleanup (Sync) Confirmation
        $('#btn-cleanup').on('click', function() {
            submitCleanup(this, 'semua', 'Konfirmasi', 'Tarik data dari mesin dan sinkron semuanya?', '#f59e0b');
        });

        // (Simpan Semua dihapus dari UI; simpan keterangan + hitung denda tetap bisa via POST langsung bila perlu)
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