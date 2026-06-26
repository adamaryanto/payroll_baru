<?php

    $id = $_GET ['id'];


 $sql =   $koneksi->query("delete from tb_jadwal where id_jadwal = '$id' ");
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
                    text: 'Data Jadwal Berhasil Dihapus',
                    confirmButtonColor: '#e11d48',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href="?page=jadwal";
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
?>

