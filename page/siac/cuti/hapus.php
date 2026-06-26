<?php

$id = $_GET['id'];


$sql =   $koneksi->query("delete from tb_cuti where id_cuti = '$id' ");
if ($sql) {
?>
    <script type="text/javascript">
        alert("Data Berhasil Di Hapus");
        window.location.href = "?page=cuti";
    </script>
<?php
}

?>