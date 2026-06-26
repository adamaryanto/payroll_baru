<?php
$id = $_GET['id'];

// Hapus Detail Terlebih Dahulu
$koneksi->query("DELETE FROM tb_boneless_detail WHERE id_boneless = '$id'");

// Hapus Header
$sql = $koneksi->query("DELETE FROM tb_boneless WHERE id_boneless = '$id'");

    $ref = $_GET['ref'] ?? '';
    $view_param = isset($_GET['view']) ? '&view=1' : '';

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
                    text: "Data Berhasil Dihapus",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                }).then((result) => {
                    window.location.href = "?page=boneless&ref=' . $ref . $view_param . '";
                });
            </script>
        </body>
        </html>';
        exit;
    }
?>
