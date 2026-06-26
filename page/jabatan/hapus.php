<?php
if(isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = $koneksi->query("DELETE FROM ms_jabatan WHERE id_jabatan = '$id'");
    if($sql) {
        echo "<script>alert('Data Berhasil Dihapus'); window.location='?page=jabatan';</script>";
    } else {
        echo "<script>alert('Gagal Hapus Data'); window.location='?page=jabatan';</script>";
    }
}
?>
