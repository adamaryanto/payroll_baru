<?php
// clean_dup.php - Hapus duplikat absen <10 menit ANY device (termasuk mesin yang sama)
// Backup otomatis: tb_record_backup_YYYYMMDD_HHMMSS
// Buka: http://localhost/payroll/clean_dup.php  atau  http://119.18.154.118:8080/absen/clean_dup.php
// Mode: ?dry=1 = preview saja, ?exec=1 = eksekusi hapus
include "koneksi.php";

$dry = isset($_GET['dry']) || !isset($_GET['exec']);
$backupDone = false;
$backupTable = "tb_record_backup_" . date("Ymd_His");

// Backup otomatis kalau exec
if (!$dry) {
    $koneksi->query("CREATE TABLE IF NOT EXISTS `$backupTable` LIKE tb_record");
    $koneksi->query("INSERT INTO `$backupTable` SELECT * FROM tb_record");
    $backupDone = $koneksi->query("SELECT COUNT(*) as c FROM `$backupTable`") ? true : false;
}

$tgl_from = $_GET['from'] ?? '2026-09-01';
$tgl_from_esc = $koneksi->real_escape_string($tgl_from);
$q = $koneksi->query("SELECT id_record, userid, tgl, detail_waktu FROM tb_record WHERE userid!='0' AND userid!='' AND tgl!='0000-00-00' AND tgl!='' AND detail_waktu!='' AND tgl >= '$tgl_from_esc' ORDER BY userid, tgl, detail_waktu ASC");
if (!$q) die("Query gagal: " . $koneksi->error);

$last = [];
$toDelete = [];
$preview = [];

while ($r = $q->fetch_assoc()) {
    $k = $r['userid'] . '_' . $r['tgl'];
    $ts = strtotime($r['detail_waktu']);
    if (isset($last[$k])) {
        $diff = ($ts - $last[$k]) / 60;
        if ($diff >= 0 && $diff <= 10) {
            $toDelete[] = $r['id_record'];
            $preview[] = $r['userid'] . " | " . $r['tgl'] . " | " . $r['detail_waktu'] . " (diff " . round($diff,1) . "m)";
            continue;
        }
    }
    $last[$k] = $ts;
}

if ($dry) {
    echo "<h3>PREVIEW - " . count($toDelete) . " duplikat <10 menit akan dihapus:</h3>";
    echo "<pre>" . implode("\n", array_slice($preview, 0, 200)) . "</pre>";
    if (count($preview) > 200) echo "<p>... dan " . (count($preview)-200) . " lagi</p>";
    echo '<p><a href="?exec=1" style="background:#ef4444;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;" onclick="return confirm(\'Yakin hapus '.count($toDelete).' duplikat? Backup otomatis dibuat.\')">Eksekusi Hapus Sekarang</a></p>';
    echo '<p style="color:#666;font-size:13px;">Backup tidak dibuat di mode preview. Klik eksekusi untuk backup otomatis ke tabel <b>'.$backupTable.'</b></p>';
} else {
    $del = 0;
    foreach (array_chunk($toDelete, 500) as $chunk) {
        $ids = implode(",", $chunk);
        if ($koneksi->query("DELETE FROM tb_record WHERE id_record IN ($ids)")) $del += count($chunk);
    }
    echo "<h3>Selesai hapus $del duplikat <10 menit</h3>";
    echo $backupDone ? "<p>Backup tersimpan di <b>$backupTable</b></p>" : "<p style='color:red'>Backup gagal!</p>";
    echo "<pre>" . implode("\n", array_slice($preview, 0, 200)) . "</pre>";
    echo '<p><a href="../index.php?page=generate&aksi=tarik">Kembali ke Tarik Data</a></p>';
}
?>