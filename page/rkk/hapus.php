<?php

    $id = $_GET ['id'];
    $iddetail = $_GET ['iddetail'];

// Validasi: Gabisa hapus kalo status RKK >= 2
    $cek_rkk = $koneksi->query("SELECT status_rkk FROM tb_rkk WHERE id_rkk = '$id'");
    $data_rkk = $cek_rkk->fetch_assoc();
    if ($data_rkk['status_rkk'] >= 2) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Akses Ditolak",
                    text: "Data tidak bisa dihapus karena status RKK sudah Approved/Realized!",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "Kembali"
                }).then((result) => {
                    window.location.href="?page=rkk&aksi=kelola&id=' . $id . '";
                });
            </script>
        </body>
        </html>';
        exit;
    }

 $sql =   $koneksi->query("delete from tb_rkk_detail where id_rkk_detail = '$iddetail' ");
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
                    text: "Data Berhasil Di Hapus",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                }).then((result) => {
                    window.location.href="?page=rkk&aksi=kelola&id=' . $id . '";
                });
            </script>
        </body>
        </html>';
    }

?>

