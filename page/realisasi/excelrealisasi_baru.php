<?php
// Export .xlsx 2 sheet: REALISASI + KARYAWAN TIDAK MASUK.
// Sheet 2 = karyawan di rencana (RKK) yang tidak ada tap mesin (tb_record) di tanggal ini.
// Tampilan & alur SAMA PERSIS dengan excelrealisasi.php lama.
// Bedanya hanya writer: xlsx_writer.php (RealisasiXlsx, sharedStrings + sanitasi
// UTF-8 byte-safe + verifikasi zip) sehingga file tidak lagi minta repair di Excel.
error_reporting(0);
ini_set('display_errors', '0');
while (ob_get_level() > 0) { @ob_end_clean(); }
include "../../koneksi.php";
if (isset($koneksi) && $koneksi) { @$koneksi->set_charset('utf8mb4'); }
include_once __DIR__ . '/../grup_borongan/_setup.php'; // pastikan ms_grup_borongan + tb_realisasi_detail.id_grup ada
require_once __DIR__ . '/xlsx_writer.php';
// Self-heal kolom pengganti (dipakai query di bawah)
$ckG = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_pengganti'");
if ($ckG && $ckG->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_detail ADD COLUMN id_pengganti INT NULL DEFAULT NULL");
}

$id = isset($_GET['id']) ? $_GET['id'] : '';

$queryInfo = $koneksi->query("SELECT tgl_realisasi, status_realisasi, jam_kerja, id_rkk FROM tb_realisasi WHERE id_realisasi = '$id'");
$info = $queryInfo ? $queryInfo->fetch_assoc() : null;
$tanggal_raw = $info ? $info['tgl_realisasi'] : '';
$status_realisasi = $info['status_realisasi'] ?? 0;
$tanggal = $tanggal_raw ? date('d-m-Y', strtotime($tanggal_raw)) : 'TanpaTanggal';
$tanggal_sql = $tanggal_raw ? date('Y-m-d', strtotime($tanggal_raw)) : '';

$is_approved = ($status_realisasi >= 2 || strtolower((string)$status_realisasi) === 'approved' || strtolower((string)$status_realisasi) === 'approve');
$stamp_text = $is_approved ? "APPROVED" : "UNAPPROVED";
$stamp_color = $is_approved ? "008000" : "FF0000";

$hari_arr = ['MINGGU','SENIN','SELASA','RABU','KAMIS','JUMAT','SABTU'];
$bulan_arr = ['','JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
$ts = $tanggal_sql ? strtotime($tanggal_sql) : time();
$judul_hari = $hari_arr[(int)date('w', $ts)];
$judul_tgl = date('d', $ts) . ' ' . $bulan_arr[(int)date('n', $ts)] . ' ' . date('Y', $ts);

// Boneless
$q_b = ($tanggal_sql !== '') ? $koneksi->query("SELECT * FROM tb_boneless WHERE tgl = '$tanggal_sql'") : false;
$b_head = $q_b ? $q_b->fetch_assoc() : null;
$potong = (float)($b_head['jumlah_mobil'] ?? 0);
$boneless_details = [];
if ($b_head) {
    $q_bd = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '" . $b_head['id_boneless'] . "' ORDER BY id_boneless_detail ASC");
    while ($q_bd && ($bd_row = $q_bd->fetch_assoc())) $boneless_details[] = $bd_row;
}

// Rincian borongan per orang (masuk ke HASIL KERJA / UPAH, tanpa kolom tambahan)
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_realisasi_borongan (
  id_borongan INT AUTO_INCREMENT PRIMARY KEY,
  id_realisasi_detail INT NOT NULL,
  nama_pekerjaan VARCHAR(100) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  tarif INT NOT NULL DEFAULT 0,
  satuan VARCHAR(10) NOT NULL DEFAULT 'kg',
  KEY idx_detail (id_realisasi_detail)
) ENGINE=InnoDB");
$ckSatX = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_borongan LIKE 'satuan'");
if ($ckSatX && $ckSatX->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_borongan ADD COLUMN satuan VARCHAR(10) NOT NULL DEFAULT 'kg'");
}
$bor_map = [];
$qb = $koneksi->query("SELECT b.* FROM tb_realisasi_borongan b JOIN tb_realisasi_detail d ON b.id_realisasi_detail = d.id_realisasi_detail WHERE d.id_realisasi = '$id' ORDER BY b.id_borongan ASC");
while ($qb && ($rb = $qb->fetch_assoc())) {
    $nm = strtoupper(trim($rb['nama_pekerjaan']));
    if ($nm === '') continue;
    $sat = strtolower(trim($rb['satuan'] ?? 'kg'));
    if ($sat !== 'ekor') $sat = 'kg';
    $bor_map[$rb['id_realisasi_detail']][] = ['nama' => $nm, 'qty' => (float)$rb['qty'], 'tarif' => (int)$rb['tarif'], 'satuan' => $sat];
}
// Baris pertama per orang (MIN id) yang dijumlah; baris rangkap tetap tampil bertanda
$keep_ids = [];
$qk = $koneksi->query("SELECT MIN(id_realisasi_detail) AS k FROM tb_realisasi_detail WHERE id_realisasi = '$id' GROUP BY IF(id_karyawan = 0, CONCAT('M:', IFNULL(nama_karyawan_manual, '')), CONCAT('K:', id_karyawan))");
while ($qk && ($rkk = $qk->fetch_assoc())) $keep_ids[(int)$rkk['k']] = true;
function ex_qty($v) {
    $v = (float)$v;
    if (abs($v - round($v)) < 1e-9) return (string)(int)round($v);
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}
// Samakan logika telat/cepat dengan halaman kelola (tanpa toleransi)
function ex_is_late($actual, $expected) {
    if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return false;
    $diff = strtotime($actual) - strtotime($expected);
    if ($diff > 43200) $diff -= 86400;
    elseif ($diff < -43200) $diff += 86400;
    return $diff > 0;
}
function ex_is_early($actual, $expected) {
    if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return false;
    $diff = strtotime($actual) - strtotime($expected);
    if ($diff > 43200) $diff -= 86400;
    elseif ($diff < -43200) $diff += 86400;
    return $diff < 0;
}

// Karyawan per GRUP (urutan grup ikut kemunculan pertama, seperti di RKK kelola)
// Tap mesin per tanggal (dipakai sheet KARYAWAN TIDAK MASUK)
$taps_all = [];
if ($tanggal_sql !== '') {
    $qt0 = $koneksi->query("SELECT DISTINCT TRIM(userid) AS pin FROM tb_record WHERE tgl='$tanggal_sql' AND TRIM(userid)<>''");
    while ($qt0 && ($rt0 = $qt0->fetch_assoc())) $taps_all[trim((string)$rt0['pin'])] = true;
}
$tampil = $koneksi->query("SELECT A.*, IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_karyawan,
    TRIM(B.no_absen) as pin_kar,
    O.OS_DHK as label_os, G.golongan as label_gol, S.nama_sub_department as posisi, J.jam_masuk as j_masuk,
    GB.nama_grup as grup,
    PG.nama_karyawan as pengganti_nama
    FROM tb_realisasi_detail A
    LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
    LEFT JOIN ms_karyawan PG ON A.id_pengganti = PG.id_karyawan
    LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
    LEFT JOIN ms_os_dhk O ON COALESCE(NULLIF(A.id_os_dhk, 0), B.id_os_dhk) = O.id_os_dhk
    LEFT JOIN ms_golongan G ON COALESCE(NULLIF(A.id_golongan, 0), B.id_golongan) = G.id_golongan
    LEFT JOIN ms_sub_department S ON COALESCE(NULLIF(A.id_sub_department, 0), RD.id_sub_department) = S.id_sub_department
    LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal
    LEFT JOIN ms_grup_borongan GB ON A.id_grup = GB.id_grup
    LEFT JOIN (SELECT id_grup, MIN(id_realisasi_detail) AS g_ord FROM tb_realisasi_detail WHERE id_realisasi = '$id' AND id_grup IS NOT NULL GROUP BY id_grup) GO ON GO.id_grup = A.id_grup
    WHERE A.id_realisasi = '$id'
    ORDER BY (GB.nama_grup IS NULL), GO.g_ord ASC, A.id_realisasi_detail ASC");

$rows_per_grup = [];
while ($tampil && ($r = $tampil->fetch_assoc())) {
    // Sheet REALISASI tampil semua baris persis halaman kelola (tidak difilter);
    // baris rangkap tetap tampil, yang dijumlah cuma baris pertama per orang.
    $pot = ($r['r_potongan_telat'] ?? 0) + ($r['r_potongan_istirahat_awal'] ?? 0) + ($r['r_potongan_istirahat_telat'] ?? 0)
         + ($r['r_potongan_pulang'] ?? 0) + ($r['r_potongan_tidak_lengkap'] ?? 0) + ($r['r_potongan_lainnya'] ?? 0);
    $upah = (float)($r['r_upah'] ?? 0);
    $lembur = (float)($r['lembur'] ?? 0);
    if (($r['r_status'] ?? '') == 'Tidak Hadir') { $upah = 0; $lembur = 0; $pot = 0; }
    // Upah ikut total borongan orang ini (muncul di HASIL KERJA / UPAH)
    $bt = 0; $sum_unit = [];
    foreach ($bor_map[$r['id_realisasi_detail']] ?? [] as $bln) {
        $bt += (int)round((float)$bln['qty'] * (int)$bln['tarif']);
        $su = $bln['satuan'] ?? 'kg';
        if (!isset($sum_unit[$su])) $sum_unit[$su] = 0;
        $sum_unit[$su] += (float)$bln['qty'];
    }
    if ($bt > 0) {
        $upah = $bt; $lembur = 0; $pot = 0;
        $hs_parts = [];
        foreach ($sum_unit as $u => $q) $hs_parts[] = ex_qty($q) . ' ' . $u;
        $r['_bor_hasil'] = implode(', ', $hs_parts);
    }
    $r['_pot'] = $pot;
    $r['_upah'] = $upah;
    $r['_lembur'] = $lembur;
    $key = !empty($r['grup']) ? strtoupper(trim($r['grup'])) : 'TANPA GRUP';
    if (!isset($rows_per_grup[$key])) $rows_per_grup[$key] = [];
    $rows_per_grup[$key][] = $r;
}

// ===== KARYAWAN RENCANA TIDAK MASUK (rencana RKK vs tap mesin tb_record + status Tarik Data ra_*) =====
// Dianggap tidak masuk bila: tidak ada tap di tb_record ATAU belum Tarik Data (ra_* masih kosong di realisasi ini).
$tm_grup = []; // grup => rows (urutan kemunculan di RKK)
$id_rkk_link = (int)($info['id_rkk'] ?? 0);
if ($tanggal_sql !== '' && $id_rkk_link > 0) {
    $taps = $taps_all; // pakai hasil query yang sama dengan filter sheet REALISASI di atas
    // Peta ra_* per karyawan di realisasi ini (untuk deteksi sudah Tarik Data atau belum)
    $ra_map = [];
    $qra = $koneksi->query("SELECT id_karyawan, ra_masuk, ra_keluar, ra_istirahat_masuk, ra_istirahat_keluar FROM tb_realisasi_detail WHERE id_realisasi='$id' AND id_karyawan<>0");
    while ($qra && ($rar = $qra->fetch_assoc())) {
        $rm = trim((string)($rar['ra_masuk'] ?? ''));
        $rk = trim((string)($rar['ra_keluar'] ?? ''));
        $rim = trim((string)($rar['ra_istirahat_masuk'] ?? ''));
        $rik = trim((string)($rar['ra_istirahat_keluar'] ?? ''));
        $ra_map[(int)$rar['id_karyawan']] = (($rm !== '' && $rm !== '00:00:00') || ($rk !== '' && $rk !== '00:00:00') || ($rim !== '' && $rim !== '00:00:00') || ($rik !== '' && $rik !== '00:00:00'));
    }
    // Pengganti per karyawan di realisasi ini (untuk keterangan)
    $pgmap = [];
    $qp = $koneksi->query("SELECT RD.id_karyawan, PG.nama_karyawan AS nm FROM tb_realisasi_detail RD LEFT JOIN ms_karyawan PG ON PG.id_karyawan=RD.id_pengganti WHERE RD.id_realisasi='$id' AND RD.id_karyawan<>0 AND RD.id_pengganti IS NOT NULL AND RD.id_pengganti<>0");
    while ($qp && ($rp = $qp->fetch_assoc())) $pgmap[(int)$rp['id_karyawan']] = $rp['nm'];
    $qr = $koneksi->query("SELECT RD.id_rkk_detail, K.id_karyawan, TRIM(K.no_absen) AS pin, K.nama_karyawan, O.OS_DHK AS oslbl, G.golongan AS gollbl, S.nama_sub_department AS posisi, J.jam_masuk AS sm, GB.nama_grup AS grup FROM tb_rkk_detail RD JOIN ms_karyawan K ON K.id_karyawan=RD.id_karyawan LEFT JOIN ms_os_dhk O ON O.id_os_dhk=K.id_os_dhk LEFT JOIN ms_golongan G ON G.id_golongan=K.id_golongan LEFT JOIN ms_sub_department S ON S.id_sub_department=RD.id_sub_department LEFT JOIN tb_jadwal J ON J.id_jadwal=RD.id_jadwal LEFT JOIN ms_grup_borongan GB ON GB.id_grup=RD.id_grup WHERE RD.id_rkk='$id_rkk_link' AND RD.id_karyawan<>0 ORDER BY RD.id_rkk_detail ASC");
    while ($qr && ($rr = $qr->fetch_assoc())) {
        $p = trim((string)($rr['pin'] ?? ''));
        if ($p === '') continue;
        $kid = (int)$rr['id_karyawan'];
        $has_tap = isset($taps[$p]);
        $has_ra = !empty($ra_map[$kid]);
        if ($has_tap && $has_ra) continue; // sudah tap + sudah Tarik Data = hadir, jangan masuk sheet tidak masuk
        if (isset($pgmap[$kid])) {
            $rr['ket'] = 'Digantikan oleh ' . $pgmap[$kid];
        } elseif (!$has_tap) {
            $rr['ket'] = 'Tidak ada tap mesin';
        } else {
            $rr['ket'] = 'Sudah tap, belum Tarik Data';
        }
        $gk = !empty($rr['grup']) ? strtoupper(trim($rr['grup'])) : 'TANPA GRUP';
        if (!isset($tm_grup[$gk])) $tm_grup[$gk] = [];
        $tm_grup[$gk][] = $rr;
    }
}

// ===== Bangun workbook =====
$x = new RealisasiXlsx();
$stT    = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stTH   = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stTHG  = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'EFEFEF', 'border' => true]);
$stSEC  = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'B2A1C6', 'border' => true]);
$stC    = $x->style(['sz' => 18, 'fill' => 'FFFFFF', 'border' => true]);
$stCB   = $x->style(['b' => true, 'sz' => 18, 'fill' => 'FFFFFF', 'border' => true]);
$stCCB  = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stCC   = $x->style(['sz' => 18, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stCRB  = $x->style(['b' => true, 'sz' => 18, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'int']);
$stCR   = $x->style(['sz' => 18, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'int']);
$stRK   = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'B4C7E7', 'border' => true]);
$stRKV  = $x->style(['b' => true, 'sz' => 16, 'align' => 'right', 'fill' => 'B4C7E7', 'border' => true, 'num' => 'dec2']);
$stTOT  = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'C6D9F0', 'border' => true]);
$stRED  = $x->style(['b' => true, 'sz' => 18, 'align' => 'center', 'color' => 'FFFFFF', 'fill' => 'FF0000', 'border' => true]);
$stYEL  = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'FFFF00', 'border' => true]);
$stStamp = $x->style(['b' => true, 'sz' => 24, 'align' => 'center', 'color' => $stamp_color, 'fill' => 'FFFFFF', 'border' => true]);
$stRp   = $x->style(['sz' => 18, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'rp']);
$stRpB  = $x->style(['b' => true, 'sz' => 18, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'rp']);
$stRp2  = $x->style(['sz' => 16, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'rp2']);
$stWrap = $x->style(['b' => true, 'sz' => 18, 'fill' => 'FFFFFF', 'border' => true, 'wrap' => true]);
$stW = $x->style(['sz' => 18, 'fill' => 'FFFFFF', 'border' => true, 'wrap' => true]);
// Sel absen: kosong = merah (default), telat = merah, pulang cepat = kuning (ikut web)
$stCCRed = $x->style(['sz' => 18, 'align' => 'center', 'color' => 'FFFFFF', 'fill' => 'FF0000', 'border' => true]);
$stCCYellow = $x->style(['sz' => 18, 'align' => 'center', 'fill' => 'FFFF00', 'border' => true]);

// ---------- SHEET 1: REALISASI (kolom sama kayak halaman kelola + Detail Borongan) ----------
$s1 = $x->sheet('REALISASI');
$r = 1;
$x->cell($s1, $r, 1, 'REALISASI ABSENSI KARYAWAN HARI ' . $judul_hari . ' ' . $judul_tgl, $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$x->cell($s1, $r, 1, 'JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000', $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$x->cell($s1, $r, 1, 'MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.', $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$hdrL = ['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK','ISTIRAHAT','JAM PULANG'];
$hdrR = ['HASIL KERJA','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'];
if (empty($rows_per_grup)) {
    $x->cell($s1, $r, 1, 'Belum ada karyawan yang tap mesin / tarik data pada tanggal ini — lihat sheet KARYAWAN TIDAK MASUK. Setelah tap + Tarik Data, export ulang untuk memunculkan data.', $stCB); $x->merge($s1, $r, 1, $r, 16); $r++;
}
foreach ($rows_per_grup as $nama_grup => $rows) {
    $x->cell($s1, $r, 1, $nama_grup, $stSEC); $x->merge($s1, $r, 1, $r, 16); $r++;
    foreach ($hdrL as $i => $h) $x->cell($s1, $r, $i + 1, $h, $stTH);
    $x->cell($s1, $r, 11, 'Detail Borongan', $stTH);
    foreach ($hdrR as $i => $h) $x->cell($s1, $r, 12 + $i, $h, $stTHG);
    $r++;
    $x->cell($s1, $r, 9, 'MASUK', $stTH); $r++;
    $no = 1;
    foreach ($rows as $data) {
        $ra_masuk_raw = ($data['ra_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_masuk'] : '';
        $ra_ist_raw = ($data['ra_istirahat_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_istirahat_masuk'] : '';
        $ra_pulang_raw = ($data['ra_keluar'] ?? '00:00:00') != '00:00:00' ? $data['ra_keluar'] : '';
        // Tampil tanpa detik (HH:MM); logika telat tetap pakai nilai penuh di atas
        $ra_masuk = $ra_masuk_raw !== '' ? substr($ra_masuk_raw, 0, 5) : '';
        $ra_ist = $ra_ist_raw !== '' ? substr($ra_ist_raw, 0, 5) : '';
        $ra_pulang = $ra_pulang_raw !== '' ? substr($ra_pulang_raw, 0, 5) : '';
        $j_masuk_disp = ($data['j_masuk'] ?? '') !== '' ? substr($data['j_masuk'], 0, 5) : '';
        $hasil = isset($data['_bor_hasil']) ? $data['_bor_hasil'] : ($data['hasil_kerja'] ?? '');
        $parts = [];
        foreach ($bor_map[$data['id_realisasi_detail']] ?? [] as $ln) {
            $lt = (int)round((float)$ln['qty'] * (int)$ln['tarif']);
            $su = $ln['satuan'] ?? 'kg';
            $parts[] = $ln['nama'] . ' : ' . ex_qty($ln['qty']) . ' ' . $su . ' x ' . number_format($ln['tarif'], 0, ',', '.') . ' = Rp' . number_format($lt, 0, ',', '.');
        }
        $bor_detail = implode(', ', $parts);
        $nama = strtoupper($data['nama_karyawan'] ?? '') . (!empty($data['pengganti_nama']) ? ' (Digantikan oleh ' . $data['pengganti_nama'] . ')' : '');
        // Warna sel absen: kosong = merah, telat = merah, pulang cepat = kuning
        $cls_masuk = ($ra_masuk === '' || ex_is_late($data['ra_masuk'] ?? '', $data['r_jam_masuk'] ?? '')) ? $stCCRed : $stCC;
        $cls_ist = ($ra_ist === '' || ex_is_late($data['ra_istirahat_masuk'] ?? '', $data['r_istirahat_masuk'] ?? '')) ? $stCCRed : $stCC;
        $cls_pulang = ($ra_pulang === '') ? $stCCRed : (ex_is_early($data['ra_keluar'] ?? '', $data['r_jam_keluar'] ?? '') ? $stCCYellow : $stCC);
        $x->cell($s1, $r, 1, $no++, $stCCB);
        $x->cell($s1, $r, 2, $nama, $stWrap);
        $x->cell($s1, $r, 3, '', $stC);
        $x->cell($s1, $r, 4, $data['label_os'] ?? '', $stCCB);
        $x->cell($s1, $r, 5, $data['label_gol'] ?? '', $stCCB);
        $x->cell($s1, $r, 6, $data['posisi'] ?? '', $stW);
        $x->cell($s1, $r, 7, $j_masuk_disp, $stCCB);
        $x->cell($s1, $r, 8, $ra_masuk, $cls_masuk);
        $x->cell($s1, $r, 9, $ra_ist, $cls_ist);
        $x->cell($s1, $r, 10, $ra_pulang, $cls_pulang);
        $x->cell($s1, $r, 11, $bor_detail, $bor_detail !== '' ? $stW : $stC);
        $x->cell($s1, $r, 12, $hasil, $stCC);
        $x->cell($s1, $r, 13, (int)round($data['_upah']), $stCRB);
        $x->cell($s1, $r, 14, $data['_pot'] ? (int)round($data['_pot']) : '', $data['_pot'] ? $stCR : $stC);
        $x->cell($s1, $r, 15, $data['_lembur'] ? (int)round($data['_lembur']) : '', $data['_lembur'] ? $stCR : $stC);
        $x->cell($s1, $r, 16, (int)round($data['_upah'] - $data['_pot'] + $data['_lembur']), $stCRB);
        $r++;
    }
}
// Karyawan TERDAFTAR yang tap tapi di luar rencana: section otomatis (tampil saja, tidak dijumlah, tanpa DB)
// Dioptimasi: peta tap 1 query + peta detail 1 query (ganti 4 subquery per karyawan).
$tdr_rows = [];
if ($tanggal_sql !== '') {
    $tapmap = [];
    $qtm = $koneksi->query("SELECT TRIM(userid) AS pin, MIN(detail_waktu) AS ft, MAX(detail_waktu) AS lt FROM tb_record WHERE tgl = '$tanggal_sql' AND TRIM(userid) <> '' GROUP BY TRIM(userid)");
    while ($qtm && ($rm = $qtm->fetch_assoc())) $tapmap[$rm['pin']] = $rm;
    $inplan = [];
    $qpl = $koneksi->query("SELECT id_karyawan FROM tb_realisasi_detail WHERE id_realisasi = '$id' AND id_karyawan <> 0");
    while ($qpl && ($rpl = $qpl->fetch_assoc())) $inplan[(int)$rpl['id_karyawan']] = true;
    $qtdr = $koneksi->query("SELECT k.id_karyawan, k.nama_karyawan, TRIM(k.no_absen) AS pin, O.OS_DHK AS oslbl, G.golongan AS gollbl, S.nama_sub_department AS posisi, k.upah
        FROM ms_karyawan k
        LEFT JOIN ms_os_dhk O ON O.id_os_dhk = k.id_os_dhk
        LEFT JOIN ms_golongan G ON G.id_golongan = k.id_golongan
        LEFT JOIN ms_sub_department S ON S.id_sub_department = k.id_sub_department
        WHERE k.status_karyawan = 'Aktif' AND k.no_absen IS NOT NULL AND TRIM(k.no_absen) <> ''
        ORDER BY k.nama_karyawan ASC");
    while ($qtdr && ($rtdr = $qtdr->fetch_assoc())) {
        if (isset($inplan[(int)$rtdr['id_karyawan']])) continue;
        $pin = $rtdr['pin'];
        if (!isset($tapmap[$pin])) continue;
        $rtdr['first_tap'] = $tapmap[$pin]['ft'];
        $rtdr['last_tap'] = $tapmap[$pin]['lt'];
        $tdr_rows[] = $rtdr;
    }
}
if (!empty($tdr_rows)) {
    $x->cell($s1, $r, 1, 'TIDAK DALAM RENCANA (terdaftar, di luar rencana — tidak dijumlah)', $stSEC); $x->merge($s1, $r, 1, $r, 16); $r++;
    foreach ($hdrL as $i => $h) $x->cell($s1, $r, $i + 1, $h, $stTH);
    $x->cell($s1, $r, 11, 'Detail Borongan', $stTH);
    foreach ($hdrR as $i => $h) $x->cell($s1, $r, 12 + $i, $h, $stTHG);
    $r++;
    $x->cell($s1, $r, 9, 'MASUK', $stTH); $r++;
    $no = 1;
    foreach ($tdr_rows as $td) {
        $ft = $td['first_tap'] ?? ''; $lt = $td['last_tap'] ?? '';
        $tdr_masuk = $ft !== '' ? substr($ft, 11, 5) : '';
        $tdr_pulang = ($lt !== '' && $lt !== $ft) ? substr($lt, 11, 5) : '';
        $x->cell($s1, $r, 1, $no++, $stCCB);
        $x->cell($s1, $r, 2, strtoupper($td['nama_karyawan'] ?? ''), $stWrap);
        $x->cell($s1, $r, 3, '', $stC);
        $x->cell($s1, $r, 4, $td['oslbl'] ?? '', $stCCB);
        $x->cell($s1, $r, 5, $td['gollbl'] ?? '', $stCCB);
        $x->cell($s1, $r, 6, $td['posisi'] ?? '', $stW);
        $x->cell($s1, $r, 7, '', $stCCB);
        $x->cell($s1, $r, 8, $tdr_masuk, $tdr_masuk === '' ? $stCCRed : $stCC);
        $x->cell($s1, $r, 9, '', $stCCRed);
        $x->cell($s1, $r, 10, $tdr_pulang, $tdr_pulang === '' ? $stCCRed : $stCC);
        $x->cell($s1, $r, 11, '', $stC);
        $x->cell($s1, $r, 12, '', $stCC);
        $x->cell($s1, $r, 13, (int)round((float)($td['upah'] ?? 0)), $stCRB);
        $x->cell($s1, $r, 14, '', $stC);
        $x->cell($s1, $r, 15, '', $stC);
        $x->cell($s1, $r, 16, (int)round((float)($td['upah'] ?? 0)), $stCRB);
        $r++;
    }
}
// Total paten (angka, bukan rumus) — posisi persis contoh (kolom UPAH & UPAH DIBAYAR)
$tot_upah = 0; $tot_dibayar = 0;
foreach ($rows_per_grup as $gt) {
    foreach ($gt as $rr) {
        if (!isset($keep_ids[(int)$rr['id_realisasi_detail']])) continue; // rangkap: tidak dijumlah 2x
        $tot_upah += $rr['_upah'];
        $tot_dibayar += ($rr['_upah'] - $rr['_pot'] + $rr['_lembur']);
    }
}
for ($c = 1; $c <= 12; $c++) $x->cell($s1, $r, $c, '', $stC);
$x->cell($s1, $r, 13, (int)round($tot_upah), $stCRB);
$x->cell($s1, $r, 14, '', $stC); $x->cell($s1, $r, 15, '', $stC);
$x->cell($s1, $r, 16, (int)round($tot_dibayar), $stCRB);
$r++;
// Nilai paten footer (angka, bukan rumus) — seperti file contoh
$val_pabrik = $tot_dibayar;
$val_bon_net = 0;
foreach ($boneless_details as $bd0) {
    $ln0 = (float)($bd0['qty'] ?? 0) * abs((float)($bd0['harga'] ?? 0));
    $val_bon_net += (($bd0['jenis'] ?? '') == 'minus') ? -$ln0 : $ln0;
}
$val_grand = $val_pabrik + $val_bon_net;
$val_permobil = ($potong > 0) ? $val_grand / $potong : 0;
$x->cell($s1, $r, 3, 'REALISASI TOTAL REKAP BIAYA PABRIK CIKUPA ' . $judul_tgl, $stRK); $x->merge($s1, $r, 3, $r, 9); $r++;
$x->cell($s1, $r, 3, 'Biaya ' . (int)$potong . ' mobil', $stRK); $x->merge($s1, $r, 3, $r, 7);
$x->cell($s1, $r, 8, round($val_pabrik, 2), $stRKV); $x->merge($s1, $r, 8, $r, 9); $r++;
$x->cell($s1, $r, 3, 'Biaya permobil', $stRK); $x->merge($s1, $r, 3, $r, 7);
$x->cell($s1, $r, 8, round($val_permobil, 2), $stRKV); $x->merge($s1, $r, 8, $r, 9); $r++;
$r++; // spasi
$x->cell($s1, $r, 3, 'BAYARAN TIM BONLESS', $stRK); $x->merge($s1, $r, 3, $r, 10); $r++;
$nb = 0;
foreach ($boneless_details as $item) {
    $nb++;
    $qty = (float)($item['qty'] ?? 0);
    $hrg = abs((float)($item['harga'] ?? 0));
    $is_minus = (($item['jenis'] ?? '') == 'minus');
    $line = $qty * $hrg;
    $x->cell($s1, $r, 3, $nb, $stCCB);
    $x->cell($s1, $r, 4, '', $stC);
    $x->cell($s1, $r, 5, '', $stC);
    $x->cell($s1, $r, 6, $is_minus ? 'PENGURANGAN' : '', $stCCB);
    $x->cell($s1, $r, 7, strtoupper($item['nama_item'] ?? ''), $stCB);
    $x->cell($s1, $r, 8, $qty ? (int)round($qty) : '', $qty ? $stCC : $stC);
    $x->cell($s1, $r, 9, (int)round($hrg), $stRp);
    $x->cell($s1, $r, 10, (int)round($line), $stRp);
    $r++;
}
$x->cell($s1, $r, 3, 'TOTAL', $stTOT); $x->merge($s1, $r, 3, $r, 9);
$x->cell($s1, $r, 10, (int)round($val_bon_net), $stRpB); $r++;
$x->cell($s1, $r, 3, 'DENGAN MESIN BONLES', $stRED); $x->merge($s1, $r, 3, $r, 10); $r++;
$x->cell($s1, $r, 3, 'BIAYA PABRIK', $stYEL);
$x->cell($s1, $r, 6, 'BONLESS', $stYEL);
$x->cell($s1, $r, 7, 'POTONG', $stYEL);
$x->cell($s1, $r, 8, 'TOTAL', $stYEL);
$x->cell($s1, $r, 9, 'Biaya Per mobil', $stYEL); $x->merge($s1, $r, 9, $r, 10); $r++;
$x->cell($s1, $r, 3, round($val_pabrik, 2), $stRp2);
$x->cell($s1, $r, 6, round($val_bon_net, 2), $stRp2);
$x->cell($s1, $r, 7, (int)$potong, $stCC);
$x->cell($s1, $r, 8, round($val_grand, 2), $stRp2);
$x->cell($s1, $r, 9, round($val_permobil, 2), $stRp2); $x->merge($s1, $r, 9, $r, 10); $r++;
$r++; $r++;
$x->cell($s1, $r, 3, $stamp_text, $stStamp); $r++;

// ---------- SHEET 2: KARYAWAN TIDAK MASUK (simple) ----------
$s2 = $x->sheet('KARYAWAN TIDAK MASUK');
$r2 = 1;
$x->cell($s2, $r2, 1, 'KARYAWAN TIDAK MASUK HARI ' . $judul_hari . ' ' . $judul_tgl, $stT); $x->merge($s2, $r2, 1, $r2, 5); $r2++;
$hdr2 = ['No','NAMA SESUAI KTP','OS/DHK','POSISI','KETERANGAN'];
foreach ($hdr2 as $i => $h) $x->cell($s2, $r2, $i + 1, $h, $stTH);
$r2++;
$total_tm = 0;
foreach ($tm_grup as $gtm) $total_tm += count($gtm);
if (empty($tm_grup)) {
    $x->cell($s2, $r2, 1, 'Semua karyawan rencana sudah tap mesin / tarik data (tidak ada yang tidak masuk).', $stCB); $x->merge($s2, $r2, 1, $r2, 5); $r2++;
} else {
    $ntm = 1;
    foreach ($tm_grup as $gk => $trows) {
        $x->cell($s2, $r2, 1, $gk, $stSEC); $x->merge($s2, $r2, 1, $r2, 5); $r2++;
        foreach ($trows as $tm) {
            $x->cell($s2, $r2, 1, $ntm++, $stCCB);
            $x->cell($s2, $r2, 2, strtoupper($tm['nama_karyawan'] ?? ''), $stWrap);
            $x->cell($s2, $r2, 3, $tm['oslbl'] ?? '', $stCCB);
            $x->cell($s2, $r2, 4, $tm['posisi'] ?? '', $stW);
            $x->cell($s2, $r2, 5, $tm['ket'] ?? '', $stC);
            $r2++;
        }
    }
}
$x->cell($s2, $r2, 1, 'TOTAL KARYAWAN TIDAK MASUK', $stTOT); $x->merge($s2, $r2, 1, $r2, 4);
$x->cell($s2, $r2, 5, $total_tm . ' Orang', $stTOT); $r2++;

$x->autofit($s1);
$x->autofit($s2);
$x->send("Laporan_Realisasi_Upah_$tanggal.xlsx");
