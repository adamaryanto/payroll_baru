<?php

$id = $_GET['id'];
$status = $_GET['iddetail'];

if ($status == "pro") {
    // Validasi Boneless: Ambil tgl_rkk dari RKK
    $cek_rkk = $koneksi->query("SELECT tgl_rkk FROM tb_rkk WHERE id_rkk = '$id'");
    $data_rkk = $cek_rkk->fetch_assoc();
    $tgl_rkk = $data_rkk['tgl_rkk'];

    // Cek jumlah karyawan
    $cek_jml = $koneksi->query("SELECT COUNT(id_rkk_detail) as jml FROM tb_rkk_detail WHERE id_rkk = '$id' AND status_rkk != 'Digantikan'");
    $data_jml = $cek_jml->fetch_assoc();
    $jml = $data_jml['jml'];

    if ($jml == 0) {
        $pesan = "Tidak bisa propose data! Silakan tambahkan data karyawan terlebih dahulu.";
?>
        <!DOCTYPE html>
        <html>

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>

        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Data Kosong',
                    text: '<?= $pesan ?>',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    window.location.href = "?page=rkk";
                });
            </script>
        </body>

        </html>
    <?php
        exit;
    }

    // Cek apakah ada data Boneless untuk RKK ini (Specific by ID)
    $cek_boneless = $koneksi->query("SELECT id_boneless FROM tb_boneless WHERE id_rkk = '$id'");

    if ($cek_boneless->num_rows == 0) {
        $pesan = "Tidak bisa propose data! Silakan lengkapi data Boneless untuk tanggal " . date('d/m/Y', strtotime($tgl_rkk)) . " terlebih dahulu.";
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>

        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Akses Ditolak',
                    text: '<?= $pesan ?>',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    window.location.href = "?page=rkk";
                });
            </script>
        </body>

        </html>
    <?php
        exit;
    }

    $sql =   $koneksi->query("update tb_rkk set status_rkk = 1 where id_rkk = '$id' ");
    if ($sql) {
        $prev_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?page=rkk';
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
                    title: 'Berhasil Propose',
                    text: 'Rencana Upah berhasil diajukan ke Owner.',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK',
                    customClass: { popup: 'rounded-3xl' }
                }).then((result) => {
                    window.location.href = '<?= $prev_url ?>';
                });
            </script>
        </body>
        </html>
<?php
        exit;
    }
} elseif ($status == "app") {
    // Cek jumlah karyawan sebelum approve
    $cek_jml = $koneksi->query("SELECT COUNT(id_rkk_detail) as jml FROM tb_rkk_detail WHERE id_rkk = '$id' AND status_rkk != 'Digantikan'");
    $data_jml = $cek_jml->fetch_assoc();
    $jml = $data_jml['jml'];

    if ($jml == 0) {
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>

        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Data Kosong',
                    text: 'Tidak bisa approve data! Silakan tambahkan data karyawan terlebih dahulu.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    window.location.href = "?page=rkk";
                });
            </script>
        </body>

        </html>
    <?php
        exit;
    }

    // New: Check Boneless before Approve as well
    $cek_boneless = $koneksi->query("SELECT id_boneless FROM tb_boneless WHERE id_rkk = '$id'");
    if ($cek_boneless->num_rows == 0) {
        $cek_rkk = $koneksi->query("SELECT tgl_rkk FROM tb_rkk WHERE id_rkk = '$id'");
        $tgl_rkk = $cek_rkk->fetch_assoc()['tgl_rkk'];
    ?>
        <!DOCTYPE html>
        <html>

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>

        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Boneless Belum Ada',
                    text: 'Tidak bisa approve data! Silakan lengkapi data Boneless untuk Rencana ini terlebih dahulu.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    window.location.href = "?page=rkk";
                });
            </script>
        </body>

        </html>
<?php
        exit;
    }

    $sql = $koneksi->query("update tb_rkk set status_rkk = 2 where id_rkk = '$id' ");
    if ($sql) {
        $prev_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?page=rkk';
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
                    title: 'Berhasil Approve',
                    text: 'Rencana Upah berhasil disetujui.',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK',
                    customClass: { popup: 'rounded-3xl' }
                }).then((result) => {
                    window.location.href = '<?= $prev_url ?>';
                });
            </script>
        </body>
        </html>
<?php
        exit;
    }
} elseif ($status == "unpro") {
    $sql = $koneksi->query("update tb_rkk set status_rkk = 0 where id_rkk = '$id' ");
    if ($sql) {
        $prev_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?page=rkk';
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
                    title: 'Berhasil Unpropose',
                    text: 'Pengajuan Rencana Upah telah dibatalkan.',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK',
                    customClass: { popup: 'rounded-3xl' }
                }).then((result) => {
                    window.location.href = '<?= $prev_url ?>';
                });
            </script>
        </body>
        </html>
<?php
        exit;
    }
} elseif ($status == "unapp") {
    $sql =   $koneksi->query("update tb_rkk set status_rkk = 1 where id_rkk = '$id' ");
    if ($sql) {
        $prev_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?page=rkk';
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
                    title: 'Berhasil Unapprove',
                    text: 'Status persetujuan Rencana Upah telah dibatalkan.',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK',
                    customClass: { popup: 'rounded-3xl' }
                }).then((result) => {
                    window.location.href = '<?= $prev_url ?>';
                });
            </script>
        </body>
        </html>
<?php
        exit;
    }
}

?>