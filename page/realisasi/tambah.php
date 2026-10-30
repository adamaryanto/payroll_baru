

<?php
include_once __DIR__ . '/../grup_borongan/_setup.php';

 // 2026-10-26: Admin HR tetap bisa Tambah Realisasi, TAPI hanya dari RKK yang
 // sudah di-Approve (tb_rkk.status_rkk = 2, sama seperti daftar di rkk.php).
 // Dari RKK yang belum di-Approve (atau sudah dibuat Realisasinya, status jadi 3)
 // tetap ditolak di server, walau diakses lewat URL langsung.
 if (strtolower($_SESSION['role'] ?? '') === 'admin hr') {
    $id_cek = mysqli_real_escape_string($koneksi, $_GET['id'] ?? '');
    $cek_rkk = $koneksi->query("SELECT status_rkk FROM tb_rkk WHERE id_rkk = '$id_cek' LIMIT 1");
    $st_rkk = ($cek_rkk && ($r_cek = $cek_rkk->fetch_assoc())) ? (int)$r_cek['status_rkk'] : -1;
    if ($st_rkk !== 2) {
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
            Swal.fire({ icon: "error", title: "Akses Ditolak", text: "Admin HR hanya bisa membuat Realisasi dari RKK yang sudah di-Approve.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.location.href = "?page=realisasi"; });
        </script></body></html>';
        exit;
    }
 }
 $id = $_GET ['id'];
 // 2026-10-26: Anti-double — satu RKK hanya boleh punya SATU Realisasi.
 // Cek dulu sebelum INSERT (berlaku untuk semua role, bukan cuma Admin HR).
 // Kalau sudah ada, jangan INSERT lagi: tampilkan peringatan dan arahkan ke
 // Kelola Realisasi yang sudah ada itu. Ini menutup celah klik ganda, refresh,
 // back-forward, 2 tab, dan 2 user yang mengeksekusi URL tambah yang sama.
 $id_e = mysqli_real_escape_string($koneksi, $id);
 // 2026-10-26 (koreksi): guard juga cek TANGGAL, bukan cuma id_rkk. Kalau Realisasi
 // dengan tanggal yang sama sudah ada (walau dari RKK lain dengan id berbeda),
 // jangan buat lagi — arahkan ke Kelola Realisasi tanggal itu (yang APPROVED dulu,
 // kalau imbang yang tertua, sama seperti filter tampil di List Realisasi).
 $cek_tgl_rkk = $koneksi->query("SELECT tgl_rkk FROM tb_rkk WHERE id_rkk = '$id_e' LIMIT 1");
 $tgl_rkk_cek = ($cek_tgl_rkk && ($r_tgl_cek = $cek_tgl_rkk->fetch_assoc())) ? $r_tgl_cek['tgl_rkk'] : '';
 $tgl_e = mysqli_real_escape_string($koneksi, (string)$tgl_rkk_cek);
 $cek_dobel = $koneksi->query("SELECT id_realisasi FROM tb_realisasi WHERE id_rkk = '$id_e' OR (tgl_realisasi = '$tgl_e' AND '$tgl_e' <> '') ORDER BY status_realisasi DESC, id_realisasi ASC LIMIT 1");
 if ($cek_dobel && ($r_dobel = $cek_dobel->fetch_assoc())) {
    $id_real_ada = (int)$r_dobel['id_realisasi'];
    echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
        Swal.fire({ icon: "warning", title: "Sudah Ada", text: "Realisasi untuk RKK/tanggal ini sudah pernah dibuat, jadi tidak dibuat lagi supaya tidak double.", confirmButtonColor: "#2563eb", confirmButtonText: "Lihat Realisasi" }).then(() => { window.location.href = "?page=realisasi&aksi=kelola&id=' . $id_real_ada . '"; });
    </script></body></html>';
    exit;
 }
$ttgl1 = date("Y-m-d");
$ttgl2 = date("Y-m-d H:i:s");



    // Validasi backend: Pastikan RKK punya karyawan sebelum dipindah ke realisasi
    $cek_jml = $koneksi->query("SELECT COUNT(id_rkk_detail) as jml FROM tb_rkk_detail WHERE id_rkk = '$id'");
    $data_jml = $cek_jml->fetch_assoc();
    if ($data_jml['jml'] == 0) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Data Kosong",
                    text: "Tidak bisa membuat realisasi untuk RKK tanpa karyawan!",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "Kembali"
                }).then((result) => {
                    window.location.href = "?page=realisasi&aksi=rkk";
                });
            </script>
        </body>
        </html>';
        exit;
    }

   $koneksi->query("insert into tb_realisasi (id_rkk, tgl_realisasi, jam_kerja, detail_realisasi, keterangan, tgl_status, status_realisasi) 
                    select id_rkk, tgl_rkk, jam_kerja, '$ttgl2', '', CURDATE(), 0 
                    from tb_rkk where id_rkk = '$id' ");

$iddetail = $koneksi->insert_id;

$tampil_tgl = $koneksi->query("SELECT tgl_realisasi FROM tb_realisasi WHERE id_realisasi = '$iddetail'");
$data_tgl = $tampil_tgl->fetch_assoc();
$tglreal = $data_tgl['tgl_realisasi'];

    $sql =  $koneksi->query("insert into tb_realisasi_detail (
            id_realisasi, id_rkk_detail, id_rkk, id_karyawan, 
            nama_karyawan_manual, id_departmen, id_sub_department,
            r_upah, 
            r_jam_masuk, r_jam_keluar, r_istirahat_masuk, r_istirahat_keluar, 
            id_jadwal, id_grup, r_potongan_telat, r_potongan_istirahat_awal, 
            r_potongan_istirahat_telat, r_potongan_lainnya, r_potongan_pulang, 
            r_potongan_tidak_lengkap, r_status, r_update, ra_masuk, ra_keluar, 
            ra_istirahat_masuk, ra_istirahat_keluar, status_realisasi_detail, 
            hasil_kerja, lembur, tgl_realisasi_detail
        ) 
         select '$iddetail', A.id_rkk_detail, A.id_rkk, A.id_karyawan,
                A.nama_karyawan_manual, COALESCE(A.id_departmen, 0), COALESCE(A.id_sub_department, 0),
               A.upah, 
               COALESCE(B.jam_masuk, '00:00:00'), 
               COALESCE(B.jam_keluar, '00:00:00'), 
               COALESCE(B.istirahat_masuk, '00:00:00'), 
               COALESCE(B.istirahat_keluar, '00:00:00'), 
               A.id_jadwal, A.id_grup, 0, 0, 0, 0, 0, 0, 
               'Hadir', '', '00:00:00', '00:00:00', '00:00:00', '00:00:00', 
               0, '', 0, '$tglreal' 
        from tb_rkk_detail A
        LEFT JOIN tb_jadwal B ON A.id_jadwal = B.id_jadwal
        where A.id_rkk = '$id' ");
$koneksi->query("update tb_rkk set status_rkk = 3 , tgl_status = '$ttgl2' where id_rkk = '$id' ");


if($sql) {
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
                text: "Data Tersimpan",
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


?>

