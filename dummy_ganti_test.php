<?php
// FILE TEST SEMENTARA — HAPUS setelah selesai ngetes fitur pengganti!
include "koneksi.php";

$today = date('Y-m-d');
$aksi = $_GET['aksi'] ?? '';
$msg = '';

if ($aksi === 'buat') {
    // Ambil 3 karyawan aktif pertama untuk dibuatkan tap dummy (masuk + pulang)
    $q = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY id_karyawan LIMIT 3");
    $n = 0;
    while ($r = $q->fetch_assoc()) {
        $pin = (int)$r['no_absen'];
        $koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('DUMMY_TEST', '$pin', '$today', 1, 0, '$today 07:00:00')");
        $koneksi->query("INSERT INTO tb_record (data, userid, tgl, verifikasi, status, detail_waktu) VALUES ('DUMMY_TEST', '$pin', '$today', 1, 1, '$today 16:00:00')");
        $n++;
    }
    $msg = "OK: $n karyawan dibuatkan tap dummy hari ini ($today). Sisanya jadi TIDAK ABSEN.";
} elseif ($aksi === 'hapus') {
    $koneksi->query("DELETE FROM tb_record WHERE data='DUMMY_TEST'");
    $msg = "OK: tap dummy dihapus (" . $koneksi->affected_rows . " baris).";
} elseif ($aksi === 'hapus_ganti') {
    $koneksi->query("DELETE FROM tb_ganti WHERE tgl='$today'");
    $msg = "OK: data pengganti hari ini dihapus (" . $koneksi->affected_rows . " baris).";
}

// Status hari ini
$tap_today = 0;
$dummy_today = 0;
$qr = $koneksi->query("SELECT COUNT(*) c FROM tb_record WHERE tgl='$today'");
if ($qr) $tap_today = (int)$qr->fetch_assoc()['c'];
$qr = $koneksi->query("SELECT COUNT(*) c FROM tb_record WHERE tgl='$today' AND data='DUMMY_TEST'");
if ($qr) $dummy_today = (int)$qr->fetch_assoc()['c'];
$qr = $koneksi->query("SELECT COUNT(*) c FROM tb_ganti WHERE tgl='$today'");
$ganti_today = $qr ? (int)$qr->fetch_assoc()['c'] : 0;

// Siapa yg tap / tidak tap hari ini (10 pertama)
$qt = $koneksi->query("SELECT DISTINCT userid FROM tb_record WHERE tgl='$today'");
$tapped = [];
if ($qt) while ($rt = $qt->fetch_assoc()) $tapped[] = trim((string)$rt['userid']);
$qk = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY id_karyawan LIMIT 10");
$karyawan = [];
if ($qk) while ($rk = $qk->fetch_assoc()) $karyawan[] = $rk;
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Dummy Test Pengganti</title>
<style>body{font-family:Arial,sans-serif;padding:20px;}table{border-collapse:collapse;margin:10px 0;}td,th{border:1px solid #ccc;padding:5px 10px;}.btn{display:inline-block;padding:8px 14px;margin:5px 5px 5px 0;border-radius:6px;text-decoration:none;color:#fff;font-weight:bold;}.green{background:#16a34a;}.red{background:#dc2626;}.blue{background:#2563eb;}.msg{background:#f0fdf4;border:1px solid #16a34a;padding:10px;border-radius:6px;margin-bottom:10px;}</style>
</head><body>
<h2>Dummy Test Fitur Pengganti (DATA HARI INI: <?= $today ?>)</h2>
<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<p>Tap hari ini: <b><?= $tap_today ?></b> (dummy: <b><?= $dummy_today ?></b>) | Data pengganti hari ini: <b><?= $ganti_today ?></b></p>
<p>
<a class="btn green" href="?aksi=buat" onclick="return confirm('Buat tap dummy untuk 3 karyawan pertama?')">1. Buat Tap Dummy</a>
<a class="btn blue" href="index.php?page=generate&aksi=tarik&tanggal_awal=<?= $today ?>&tanggal_akhir=<?= $today ?>">2. Buka Tarik Data & Test Ganti</a>
<a class="btn red" href="?aksi=hapus_ganti" onclick="return confirm('Hapus SEMUA data pengganti hari ini?')">3. Hapus Data Ganti Hari Ini</a>
<a class="btn red" href="?aksi=hapus" onclick="return confirm('Hapus SEMUA tap dummy?')">4. Hapus Tap Dummy</a>
</p>
<h3>Status 10 karyawan pertama hari ini</h3>
<table><tr><th>No Absen</th><th>Nama</th><th>Status</th></tr>
<?php foreach ($karyawan as $k): $ada = in_array(trim((string)$k['no_absen']), $tapped, true); ?>
<tr><td><?= htmlspecialchars($k['no_absen']) ?></td><td><?= htmlspecialchars($k['nama_karyawan']) ?></td><td><?= $ada ? 'HADIR (ada tap)' : 'TIDAK ABSEN' ?></td></tr>
<?php endforeach; ?>
</table>
<p><b>Cara test:</b> klik 1 → klik 2 → scroll ke panel <i>Tidak Absen</i> → pilih pengganti → Simpan → cek tabel masuk (baris biru PENGGANTI). Selesai: klik 3, klik 4, lalu <b>HAPUS file ini dari server</b>.</p>
</body></html>
