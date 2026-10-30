<?php
// Endpoint popup Detail Borongan: GET = ambil baris, POST = simpan baris
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include "../../koneksi.php";

$koneksi->query("CREATE TABLE IF NOT EXISTS tb_realisasi_borongan (
  id_borongan INT AUTO_INCREMENT PRIMARY KEY,
  id_realisasi_detail INT NOT NULL,
  nama_pekerjaan VARCHAR(100) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  tarif INT NOT NULL DEFAULT 0,
  satuan VARCHAR(10) NOT NULL DEFAULT 'kg',
  KEY idx_detail (id_realisasi_detail)
) ENGINE=InnoDB");
$ckSat = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_borongan LIKE 'satuan'");
if ($ckSat && $ckSat->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_borongan ADD COLUMN satuan VARCHAR(10) NOT NULL DEFAULT 'kg'");
}

header('Content-Type: application/json');

function bor_fmt_qty($v) {
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

// Fragment: detail "NAMA(qty satuan)" + jumlah hasil (harga masuk upah, bukan di sini)
function bor_fragments($lines) {
    if (empty($lines)) return ['detail' => '-', 'hasil_sum' => '', 'total' => 0];
    $parts = [];
    $tot = 0;
    $sum_by_unit = [];
    foreach ($lines as $ln) {
        $sat = strtolower(trim($ln['satuan'] ?? 'kg'));
        if ($sat !== 'ekor') $sat = 'kg';
        $lt = (int)round((float)$ln['qty'] * (int)$ln['tarif']);
        $tot += $lt;
        if (!isset($sum_by_unit[$sat])) $sum_by_unit[$sat] = 0;
        $sum_by_unit[$sat] += (float)$ln['qty'];
        $parts[] = htmlspecialchars(strtoupper($ln['nama_pekerjaan'])) . ' : ' . bor_fmt_qty($ln['qty']) . ' ' . $sat . ' x ' . number_format($ln['tarif'], 0, ',', '.') . ' = Rp' . number_format($lt, 0, ',', '.');
    }
    $hs = [];
    foreach ($sum_by_unit as $u => $q) $hs[] = bor_fmt_qty($q) . ' ' . $u;
    return ['detail' => implode(', ', $parts), 'hasil_sum' => implode(', ', $hs), 'total' => $tot];
}

function bor_detail_info($koneksi, $id) {
    $id = (int)$id;
    $q = $koneksi->query("SELECT A.id_realisasi_detail, A.id_karyawan, A.id_grup, A.r_upah,
        IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama,
        GB.nama_grup as grup, GB.harga as harga_grup
        FROM tb_realisasi_detail A
        LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
        LEFT JOIN ms_grup_borongan GB ON A.id_grup = GB.id_grup
        WHERE A.id_realisasi_detail = '$id'");
    return ($q && $q->num_rows > 0) ? $q->fetch_assoc() : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id_detail']) ? (int)$_POST['id_detail'] : 0;
    $info = ($id > 0) ? bor_detail_info($koneksi, $id) : null;
    if (!$info) { echo json_encode(['ok' => false, 'msg' => 'Data tidak ditemukan']); exit; }

    $koneksi->query("DELETE FROM tb_realisasi_borongan WHERE id_realisasi_detail = '$id'");
    $lines = isset($_POST['lines']) && is_array($_POST['lines']) ? $_POST['lines'] : [];
    foreach ($lines as $ln) {
        $nm = trim($ln['nama'] ?? '');
        if ($nm === '') continue;
        $nm_esc = $koneksi->real_escape_string($nm);
        $qq = (float)($ln['qty'] ?? 0);
        $tt = (int)($ln['tarif'] ?? 0);
        $st = strtolower(trim($ln['satuan'] ?? 'kg'));
        if ($st !== 'ekor') $st = 'kg';
        $st_esc = $koneksi->real_escape_string($st);
        $koneksi->query("INSERT INTO tb_realisasi_borongan (id_realisasi_detail, nama_pekerjaan, qty, tarif, satuan) VALUES ('$id', '$nm_esc', '$qq', '$tt', '$st_esc')");
    }
    $saved = [];
    $qs = $koneksi->query("SELECT * FROM tb_realisasi_borongan WHERE id_realisasi_detail = '$id' ORDER BY id_borongan ASC");
    while ($qs && ($rs = $qs->fetch_assoc())) $saved[] = $rs;
    $fr = bor_fragments($saved);
    $upah_rp = ($fr['total'] > 0) ? 'Rp ' . number_format($fr['total'], 0, ',', '.') : 'Rp ' . number_format((float)($info['r_upah'] ?? 0), 0, ',', '.');
    echo json_encode(['ok' => true, 'html_detail' => $fr['detail'], 'hasil_sum' => $fr['hasil_sum'], 'upah_rp' => $upah_rp, 'total' => $fr['total'], 'n' => count($saved)]);
    exit;
}

$id = isset($_GET['id_detail']) ? (int)$_GET['id_detail'] : 0;
$info = ($id > 0) ? bor_detail_info($koneksi, $id) : null;
if (!$info) { echo json_encode(['ok' => false, 'msg' => 'Data tidak ditemukan']); exit; }

$lines = [];
$ql = $koneksi->query("SELECT * FROM tb_realisasi_borongan WHERE id_realisasi_detail = '$id' ORDER BY id_borongan ASC");
while ($ql && ($rl = $ql->fetch_assoc())) $lines[] = $rl;

$names = [];
$qn = $koneksi->query("SELECT DISTINCT nama_pekerjaan FROM tb_realisasi_borongan WHERE nama_pekerjaan <> '' ORDER BY nama_pekerjaan ASC");
while ($qn && ($rn = $qn->fetch_assoc())) $names[] = $rn['nama_pekerjaan'];

$rate = (int)($info['harga_grup'] ?? 0);

$grups = [];
$qg = $koneksi->query("SELECT nama_grup, harga FROM ms_grup_borongan ORDER BY nama_grup ASC");
while ($qg && ($rg2 = $qg->fetch_assoc())) $grups[] = ['nama' => $rg2['nama_grup'], 'harga' => $rg2['harga']];

echo json_encode([
    'ok' => true,
    'nama' => $info['nama'] ?? '-',
    'grup' => $info['grup'] ?? '',
    'default_tarif' => $rate,
    'lines' => $lines,
    'names' => $names,
    'grups' => $grups
]);
