

<?php
 $id = $_GET ['id'];
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
            id_jadwal, r_potongan_telat, r_potongan_istirahat_awal, 
            r_potongan_istirahat_telat, r_potongan_lainnya, r_potongan_pulang, 
            r_potongan_tidak_lengkap, r_status, r_update, ra_masuk, ra_keluar, 
            ra_istirahat_masuk, ra_istirahat_keluar, status_realisasi_detail, 
            hasil_kerja, lembur, tgl_realisasi_detail
        ) 
        select '$iddetail', A.id_rkk_detail, A.id_rkk, A.id_karyawan, 
               A.nama_karyawan_manual, A.id_departmen, A.id_sub_department,
               A.upah, 
               COALESCE(B.jam_masuk, '00:00:00'), 
               COALESCE(B.jam_keluar, '00:00:00'), 
               COALESCE(B.istirahat_masuk, '00:00:00'), 
               COALESCE(B.istirahat_keluar, '00:00:00'), 
               A.id_jadwal, 0, 0, 0, 0, 0, 0, 
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

