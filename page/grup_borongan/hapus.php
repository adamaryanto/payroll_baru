<?php
$id = $_GET['id'] ?? 0;

$koneksi->query("DELETE FROM ms_grup_borongan_item WHERE id_grup = '$id'");
$sql = $koneksi->query("DELETE FROM ms_grup_borongan WHERE id_grup = '$id'");

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
                text: "Grup berhasil dihapus",
                confirmButtonColor: "#2563eb",
                confirmButtonText: "OK"
            }).then((result) => {
                window.location.href = "?page=grup_borongan";
            });
        </script>
    </body>
    </html>';
    exit;
}
?>
