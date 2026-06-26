<?php

    $id = $_GET ['id'];


 $sql =   $koneksi->query("delete from ms_karyawan where id_karyawan = '$id' ");
    if($sql) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data Berhasil Dihapus',
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href="?page=karyawan";
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

?>

