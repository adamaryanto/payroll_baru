<?php

$id = $_GET['id'];


$sql =   $koneksi->query("delete from tb_ijin where id_ijin = '$id' ");
if ($sql) {
?>
    <script type="text/javascript">
        alert("Data Berhasil Di Hapus");
        window.location.href = "?page=ijin";
    </script>
<?php
}

?>