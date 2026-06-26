<?php

$id = $_GET['id'];


$sql =   $koneksi->query("delete from tb_alfa where id_alfa = '$id' ");
if ($sql) {
?>
    <script type="text/javascript">
        alert("Data Berhasil Di Hapus");
        window.location.href = "?page=alfa";
    </script>
<?php
}

?>