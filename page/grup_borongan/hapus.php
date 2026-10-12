<?php
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "error",
                title: "Gagal",
                text: "ID Grup tidak valid.",
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

// Cegah hapus grup yang masih dipakai (FK ketat) — hitung pemakaian dulu
$pakai_rkk = 0; $pakai_real = 0; $pakai_agt = 0;
try {
    if (($q1 = $koneksi->query("SELECT COUNT(*) c FROM tb_rkk_detail WHERE id_grup = '$id'")) && ($r1 = $q1->fetch_assoc())) $pakai_rkk = (int)$r1['c'];
    if (($q2 = $koneksi->query("SELECT COUNT(*) c FROM tb_realisasi_detail WHERE id_grup = '$id'")) && ($r2 = $q2->fetch_assoc())) $pakai_real = (int)$r2['c'];
    if (($q3 = $koneksi->query("SELECT COUNT(*) c FROM ms_grup_borongan_anggota WHERE id_grup = '$id'")) && ($r3 = $q3->fetch_assoc())) $pakai_agt = (int)$r3['c'];
} catch (Throwable $e) { /* biarkan 0, DELETE di bawah tetap dilindungi */ }

if ($pakai_rkk > 0 || $pakai_real > 0 || $pakai_agt > 0) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "error",
                title: "Tidak Bisa Dihapus",
                text: "Grup dipakai di ' . $pakai_rkk . ' data RKK, ' . $pakai_real . ' data realisasi, dan ' . $pakai_agt . ' anggota. Kosongkan dulu dari sana.",
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

try {
    $koneksi->query("DELETE FROM ms_grup_borongan_item WHERE id_grup = '$id'");
    $sql = $koneksi->query("DELETE FROM ms_grup_borongan WHERE id_grup = '$id'");
} catch (Throwable $e) {
    $sql = false;
}

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
} else {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "error",
                title: "Gagal",
                text: "Grup gagal dihapus (mungkin masih dipakai).",
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
