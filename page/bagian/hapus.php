<?php

    $id = $_GET ['id'];


 $sql = $koneksi->query("DELETE FROM ms_departmen WHERE id_departmen = '$id'");
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
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href="?page=bagian";
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

?>

