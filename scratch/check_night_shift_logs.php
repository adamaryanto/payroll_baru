<?php
include "koneksi.php";

echo "=== CHECKING RAW LOGS FOR MAMAM (101), RIFKI (108), TUTI (36), ADI (132) ===\n";
$userids = ['101', '108', '36', '132'];
foreach ($userids as $uid) {
    echo "\nUser ID: $uid\n";
    $q = $koneksi->query("SELECT * FROM tb_record WHERE userid = '$uid' AND tgl IN ('2026-07-29', '2026-07-30') ORDER BY detail_waktu ASC");
    if ($q && $q->num_rows > 0) {
        while ($r = $q->fetch_assoc()) {
            echo " - Date: {$r['tgl']}, Time: {$r['detail_waktu']}, Status: {$r['status']}\n";
        }
    } else {
        echo " - No records found in tb_record!\n";
    }
}
?>
