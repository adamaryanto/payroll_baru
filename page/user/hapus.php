<?php

    $id = $_GET ['id'];


 $sql =   $koneksi->query("delete from ms_login where id_login = '$id' ");
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
                    confirmButtonColor: '#e11d48',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href="?page=user";
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

?>

