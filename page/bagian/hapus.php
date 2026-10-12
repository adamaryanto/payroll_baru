<?php

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
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
                    title: 'Gagal',
                    text: 'ID Department tidak valid.',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Kembali'
                }).then((result) => {
                    window.location.href="?page=bagian";
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    // Cegah hapus department yang masih dipakai (FK ketat) — hitung pemakaian dulu
    $pakai_rkk = 0; $pakai_kar = 0;
    try {
        if (($q1 = $koneksi->query("SELECT COUNT(*) c FROM tb_rkk_detail WHERE id_departmen = '$id'")) && ($r1 = $q1->fetch_assoc())) $pakai_rkk = (int)$r1['c'];
        if (($q2 = $koneksi->query("SELECT COUNT(*) c FROM ms_karyawan WHERE id_departmen = '$id'")) && ($r2 = $q2->fetch_assoc())) $pakai_kar = (int)$r2['c'];
    } catch (Throwable $e) { /* biarkan 0, DELETE di bawah tetap dilindungi */ }

    if ($pakai_rkk > 0 || $pakai_kar > 0) {
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
                    title: 'Tidak Bisa Dihapus',
                    text: 'Department dipakai di <?= $pakai_rkk ?> data RKK dan <?= $pakai_kar ?> karyawan. Kosongkan dulu dari sana.',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Kembali'
                }).then((result) => {
                    window.location.href="?page=bagian";
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    try {
        $sql = $koneksi->query("DELETE FROM ms_departmen WHERE id_departmen = '$id'");
    } catch (Throwable $e) {
        $sql = false;
    }
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
    } else {
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
                    title: 'Gagal',
                    text: 'Data gagal dihapus (mungkin masih dipakai).',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Kembali'
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
