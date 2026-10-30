<?php
// Gate server Approve/Unapprove Realisasi (2026-10-26, dibalik atas permintaan user):
// hanya Owner + Admin Master + Kepala Pabrik. Admin HR TIDAK bisa Approve.
$role_app = strtolower($_SESSION['role'] ?? '');
if ($role_app !== "owner" && $role_app !== "admin master" && $role_app !== "kepala pabrik") {
    echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><script>
        Swal.fire({ icon: "error", title: "Akses Ditolak", text: "Hanya Owner, Admin Master, dan Kepala Pabrik yang bisa Approve/Unapprove Realisasi.", confirmButtonColor: "#2563eb", confirmButtonText: "OK" }).then(() => { window.location.href = "?page=realisasi"; });
    </script></body></html>';
    exit;
}
$id = $_GET['id'];
$status = $_GET['iddetail'] ?? '';
$tgl = date("Y-m-d H:i:s");

if (file_exists(__DIR__ . '/../../telegram_helper.php')) {
    include_once __DIR__ . '/../../telegram_helper.php';
}

// Direct Approval system with numeric status
if ($status == "unapp" || $status == "unpro") {
    $sql = $koneksi->query("UPDATE tb_realisasi SET status_realisasi = 0, tgl_status = '$tgl' WHERE id_realisasi = '$id'");
} elseif ($status == "pro") {
    $sql = $koneksi->query("UPDATE tb_realisasi SET status_realisasi = 1, tgl_status = '$tgl' WHERE id_realisasi = '$id'");
} elseif ($status == "app") {
    $sql = $koneksi->query("UPDATE tb_realisasi SET status_realisasi = 2, tgl_status = '$tgl' WHERE id_realisasi = '$id'");
} else {
    // Default fallback to Approve (2)
    $sql = $koneksi->query("UPDATE tb_realisasi SET status_realisasi = 2, tgl_status = '$tgl' WHERE id_realisasi = '$id'");
}

if ($sql) {
    if ($status === 'pro' && function_exists('tg_notify_proposal')) {
        try { tg_notify_proposal($koneksi, 'realisasi', (int)$id); } catch (Throwable $e) { error_log('Telegram notify realisasi: ' . $e->getMessage()); }
    }
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
                title: 'Berhasil',
                text: 'Status realisasi upah berhasil diperbarui.',
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
