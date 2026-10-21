<?php
// Halaman diagnosa sementara: cek data realisasi + uji step tarik_ajax tanpa popup.
session_start();
if (empty($_SESSION['iduser'])) { die('Login dulu di aplikasi, lalu buka halaman ini.'); }
include "../../koneksi.php";
header('Content-Type: text/html; charset=utf-8');
$id = (int)($_GET['id'] ?? 0);

function db1($koneksi, $sql) {
    $r = $koneksi->query($sql);
    if (!$r) return 'QUERY ERROR: ' . $koneksi->error . ' | SQL: ' . $sql;
    return $r->fetch_row()[0];
}
?><!doctype html>
<html><head><meta charset="utf-8"><title>Diagnosa Realisasi</title>
<style>body{font-family:Consolas,monospace;font-size:13px;margin:16px}pre{background:#111;color:#0f0;padding:12px;white-space:pre-wrap}button{padding:8px 14px;margin:4px}table{border-collapse:collapse}td,th{border:1px solid #999;padding:4px 8px}</style>
</head><body>
<h3>Diagnosa Realisasi id=<?= $id ?></h3>
<?php
echo "<pre>";
echo "PHP: " . PHP_VERSION . "\n";
echo "DB: " . db1($koneksi, "SELECT DATABASE()") . "\n";
$q = $koneksi->query("SELECT id_realisasi, tgl_realisasi, keterangan, id_rkk FROM tb_realisasi WHERE id_realisasi = '$id'");
if (!$q) { echo "tb_realisasi ERROR: " . $koneksi->error . "\n"; }
elseif ($q->num_rows === 0) { echo "REALISASI id=$id TIDAK ADA\n"; }
else { $r = $q->fetch_assoc(); echo "Realisasi: " . json_encode($r) . "\n"; }
echo "\n-- Kolom tb_realisasi_detail --\n";
$qc = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail");
$cols = [];
while ($qc && ($c = $qc->fetch_assoc())) $cols[] = $c['Field'];
echo implode(", ", $cols) . "\n";
foreach (['pin_manual','id_grup','id_os_dhk','id_golongan'] as $need) {
    echo (in_array($need, $cols) ? "OK   " : "HILANG ") . $need . "\n";
}
echo "\n-- Hitungan --\n";
echo "detail id=$id           : " . db1($koneksi, "SELECT COUNT(*) FROM tb_realisasi_detail WHERE id_realisasi='$id'") . "\n";
echo "detail id_karyawan<>0   : " . db1($koneksi, "SELECT COUNT(*) FROM tb_realisasi_detail WHERE id_realisasi='$id' AND id_karyawan<>0") . "\n";
echo "detail id_karyawan=0    : " . db1($koneksi, "SELECT COUNT(*) FROM tb_realisasi_detail WHERE id_realisasi='$id' AND id_karyawan=0") . "\n";
$qt = $koneksi->query("SELECT tgl_realisasi FROM tb_realisasi WHERE id_realisasi='$id'");
$tgl = ($qt && ($rt = $qt->fetch_assoc())) ? $rt['tgl_realisasi'] : '';
if ($tgl !== '') {
    echo "tb_record tgl=$tgl     : " . db1($koneksi, "SELECT COUNT(*) FROM tb_record WHERE tgl='" . $koneksi->real_escape_string($tgl) . "'") . "\n";
    echo "tb_record PIN unik      : " . db1($koneksi, "SELECT COUNT(DISTINCT TRIM(userid)) FROM tb_record WHERE tgl='" . $koneksi->real_escape_string($tgl) . "' AND TRIM(userid)<>''") . "\n";
}
echo "\n-- 10 baris detail --\n";
$qd = $koneksi->query("SELECT id_realisasi_detail, id_karyawan, nama_karyawan_manual, r_upah, r_jam_masuk, ra_masuk, ra_keluar, status_realisasi_detail, id_grup FROM tb_realisasi_detail WHERE id_realisasi='$id' ORDER BY id_realisasi_detail ASC LIMIT 10");
if (!$qd) echo "ERROR: " . $koneksi->error . "\n";
else while ($d = $qd->fetch_assoc()) echo json_encode($d) . "\n";

echo "\n-- Hasil query utama kelola (persis) --\n";
if ($id > 0) {
    $tampil = $koneksi->query("SELECT A.*, A.r_upah as upahkaryawan,
        IF(A.id_karyawan = 0, '-', B.no_absen) as no_absen_tampil,
        IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_tampil,
        COALESCE(D.nama_departmen, D_Manual.nama_departmen) as nama_departmen,
        COALESCE(BB.nama_sub_department, BB_Manual.nama_sub_department) as nama_sub_department,
        C.tgl_realisasi, O.OS_DHK as label_os, G.golongan as label_gol, RD.status_rkk, GB.nama_grup as label_grup
        FROM tb_realisasi_detail A
        LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
        LEFT JOIN tb_realisasi C ON A.id_realisasi = C.id_realisasi
        LEFT JOIN ms_departmen D ON B.id_departmen = D.id_departmen
        LEFT JOIN ms_sub_department BB ON B.id_sub_department = BB.id_sub_department
        LEFT JOIN ms_departmen D_Manual ON A.id_departmen = D_Manual.id_departmen
        LEFT JOIN ms_sub_department BB_Manual ON A.id_sub_department = BB_Manual.id_sub_department
        LEFT JOIN ms_os_dhk O ON B.id_os_dhk = O.id_os_dhk
        LEFT JOIN ms_golongan G ON B.id_golongan = G.id_golongan
        LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
        LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal
        LEFT JOIN ms_grup_borongan GB ON A.id_grup = GB.id_grup
        WHERE A.id_realisasi = '$id'
        ORDER BY (GB.nama_grup IS NULL), A.id_realisasi_detail ASC");
    if (!$tampil) echo "QUERY UTAMA ERROR: " . $koneksi->error . "\n";
    else {
        echo "rows=" . $tampil->num_rows . "\n";
        $n = 0;
        while ($n < 10 && ($x = $tampil->fetch_assoc())) {
            echo json_encode(['id' => $x['id_realisasi_detail'], 'nama' => $x['nama_tampil'], 'grup' => $x['label_grup'], 'masuk' => $x['ra_masuk'], 'keluar' => $x['ra_keluar']]) . "\n";
            $n++;
        }
    }
}
echo "</pre>";

echo '<h4>Uji proses (hasil mentah)</h4>';
echo '<button onclick="runStep(\'sync\')">Jalankan SYNC</button>';
echo '<button onclick="runStep(\'tdr\')">Jalankan TDR</button>';
echo '<button onclick="runStep(\'pull\')">Jalankan PULL (mesin)</button>';
echo '<pre id="out">(belum ada)</pre>';
?>
<script>
function runStep(step) {
    var out = document.getElementById('out');
    out.textContent = 'Menjalankan ' + step + '...';
    fetch('../realisasi/tarik_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=<?= $id ?>&step=' + encodeURIComponent(step)
    }).then(function(r) {
        return r.text().then(function(t) {
            out.textContent = 'HTTP ' + r.status + '\n' + t;
        });
    }).catch(function(e) {
        out.textContent = 'FETCH ERROR: ' + e.message;
    });
}
</script>
</body></html>
