<?php
// Export .xlsx 2 sheet: REALISASI + KARYAWAN TIDAK MASUK.
// Sheet 2 = karyawan di rencana (RKK) yang tidak ada tap mesin (tb_record) di tanggal ini.
include "../../koneksi.php";
include_once __DIR__ . '/../grup_borongan/_setup.php'; // pastikan ms_grup_borongan + tb_realisasi_detail.id_grup ada
require_once __DIR__ . '/xlsx_mini.php';
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
  KEY idx_detail (id_realisasi_detail)
) ENGINE=InnoDB");
$bor_map = [];
$qb = $koneksi->query("SELECT b.* FROM tb_realisasi_borongan b JOIN tb_realisasi_detail d ON b.id_realisasi_detail = d.id_realisasi_detail WHERE d.id_realisasi = '$id' ORDER BY b.id_borongan ASC");
while ($qb && ($rb = $qb->fetch_assoc())) {
    $nm = strtoupper(trim($rb['nama_pekerjaan']));
    if ($nm === '') continue;
    $bor_map[$rb['id_realisasi_detail']][] = ['nama' => $nm, 'qty' => (float)$rb['qty'], 'tarif' => (int)$rb['tarif']];
}
function ex_qty($v) {
    $v = (float)$v;
    if (abs($v - round($v)) < 1e-9) return (string)(int)round($v);
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

// Karyawan per GRUP (urutan grup ikut kemunculan pertama, seperti di RKK kelola)
$tampil = $koneksi->query("SELECT A.*, IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_karyawan,
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
    $pot = ($r['r_potongan_telat'] ?? 0) + ($r['r_potongan_istirahat_awal'] ?? 0) + ($r['r_potongan_istirahat_telat'] ?? 0)
         + ($r['r_potongan_pulang'] ?? 0) + ($r['r_potongan_tidak_lengkap'] ?? 0) + ($r['r_potongan_lainnya'] ?? 0);
    $upah = (float)($r['r_upah'] ?? 0);
    $lembur = (float)($r['lembur'] ?? 0);
    if (($r['r_status'] ?? '') == 'Tidak Hadir') { $upah = 0; $lembur = 0; $pot = 0; }
    // Upah ikut total borongan orang ini (muncul di HASIL KERJA / UPAH)
    $bt = 0; $k_sum = 0;
    foreach ($bor_map[$r['id_realisasi_detail']] ?? [] as $bln) {
        $bt += (int)round((float)$bln['qty'] * (int)$bln['tarif']);
        $k_sum += (float)$bln['qty'];
    }
    if ($bt > 0) { $upah = $bt; $lembur = 0; $pot = 0; $r['_bor_kg'] = $k_sum; }
    $r['_pot'] = $pot;
    $r['_upah'] = $upah;
    $r['_lembur'] = $lembur;
    $key = !empty($r['grup']) ? strtoupper(trim($r['grup'])) : 'TANPA GRUP';
    if (!isset($rows_per_grup[$key])) $rows_per_grup[$key] = [];
    $rows_per_grup[$key][] = $r;
}

// ===== KARYAWAN RENCANA TIDAK MASUK (rencana RKK vs tap mesin tb_record) =====
$tm_grup = []; // grup => rows (urutan kemunculan di RKK)
$id_rkk_link = (int)($info['id_rkk'] ?? 0);
if ($tanggal_sql !== '' && $id_rkk_link > 0) {
    $taps = [];
    $qt = $koneksi->query("SELECT DISTINCT TRIM(userid) AS pin FROM tb_record WHERE tgl='$tanggal_sql' AND TRIM(userid)<>''");
    while ($qt && ($rt = $qt->fetch_assoc())) $taps[trim((string)$rt['pin'])] = true;
    // Pengganti per karyawan di realisasi ini (untuk keterangan)
    $pgmap = [];
    $qp = $koneksi->query("SELECT RD.id_karyawan, PG.nama_karyawan AS nm FROM tb_realisasi_detail RD LEFT JOIN ms_karyawan PG ON PG.id_karyawan=RD.id_pengganti WHERE RD.id_realisasi='$id' AND RD.id_karyawan<>0 AND RD.id_pengganti IS NOT NULL AND RD.id_pengganti<>0");
    while ($qp && ($rp = $qp->fetch_assoc())) $pgmap[(int)$rp['id_karyawan']] = $rp['nm'];
    $qr = $koneksi->query("SELECT RD.id_rkk_detail, K.id_karyawan, TRIM(K.no_absen) AS pin, K.nama_karyawan, O.OS_DHK AS oslbl, G.golongan AS gollbl, S.nama_sub_department AS posisi, J.jam_masuk AS sm, GB.nama_grup AS grup FROM tb_rkk_detail RD JOIN ms_karyawan K ON K.id_karyawan=RD.id_karyawan LEFT JOIN ms_os_dhk O ON O.id_os_dhk=K.id_os_dhk LEFT JOIN ms_golongan G ON G.id_golongan=K.id_golongan LEFT JOIN ms_sub_department S ON S.id_sub_department=COALESCE(NULLIF(RD.id_sub_department,0), K.id_sub_department) LEFT JOIN tb_jadwal J ON J.id_jadwal=RD.id_jadwal LEFT JOIN ms_grup_borongan GB ON GB.id_grup=RD.id_grup WHERE RD.id_rkk='$id_rkk_link' AND RD.id_karyawan<>0 ORDER BY RD.id_rkk_detail ASC");
    while ($qr && ($rr = $qr->fetch_assoc())) {
        $p = trim((string)($rr['pin'] ?? ''));
        if ($p === '' || isset($taps[$p])) continue;
        $kid = (int)$rr['id_karyawan'];
        $rr['ket'] = isset($pgmap[$kid]) ? ('Digantikan oleh ' . $pgmap[$kid]) : 'Tidak ada tap mesin';
        $gk = !empty($rr['grup']) ? strtoupper(trim($rr['grup'])) : 'TANPA GRUP';
        if (!isset($tm_grup[$gk])) $tm_grup[$gk] = [];
        $tm_grup[$gk][] = $rr;
    }
}

// ===== Bangun workbook =====
$x = new MiniXlsx();
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

// ---------- SHEET 1: REALISASI (kolom sama kayak halaman kelola + Detail Borongan) ----------
$s1 = $x->sheet('REALISASI');
$r = 1;
$x->cell($s1, $r, 1, 'REALISASI ABSENSI KARYAWAN HARI ' . $judul_hari . ' ' . $judul_tgl, $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$x->cell($s1, $r, 1, 'JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000', $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$x->cell($s1, $r, 1, 'MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.', $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$hdrL = ['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK','ISTIRAHAT','JAM PULANG'];
$hdrR = ['HASIL KERJA (kg)','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'];
foreach ($rows_per_grup as $nama_grup => $rows) {
    $x->cell($s1, $r, 1, $nama_grup, $stSEC); $x->merge($s1, $r, 1, $r, 16); $r++;
    foreach ($hdrL as $i => $h) $x->cell($s1, $r, $i + 1, $h, $stTH);
    $x->cell($s1, $r, 11, 'Detail Borongan', $stTH);
    foreach ($hdrR as $i => $h) $x->cell($s1, $r, 12 + $i, $h, $stTHG);
    $r++;
    $x->cell($s1, $r, 9, 'MASUK', $stTH); $r++;
    $no = 1;
    foreach ($rows as $data) {
        $ra_masuk = ($data['ra_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_masuk'] : '';
        $ra_ist = ($data['ra_istirahat_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_istirahat_masuk'] : '';
        $ra_pulang = ($data['ra_keluar'] ?? '00:00:00') != '00:00:00' ? $data['ra_keluar'] : '';
        $hasil = isset($data['_bor_kg']) ? ex_qty($data['_bor_kg']) : ($data['hasil_kerja'] ?? '');
        $parts = [];
        foreach ($bor_map[$data['id_realisasi_detail']] ?? [] as $ln) {
            $lt = (int)round((float)$ln['qty'] * (int)$ln['tarif']);
            $parts[] = $ln['nama'] . ' : ' . ex_qty($ln['qty']) . ' kg x ' . number_format($ln['tarif'], 0, ',', '.') . ' = Rp' . number_format($lt, 0, ',', '.');
        }
        $bor_detail = implode(', ', $parts);
        $nama = strtoupper($data['nama_karyawan'] ?? '') . (!empty($data['pengganti_nama']) ? ' (Digantikan oleh ' . $data['pengganti_nama'] . ')' : '');
        $x->cell($s1, $r, 1, $no++, $stCCB);
        $x->cell($s1, $r, 2, $nama, $stWrap);
        $x->cell($s1, $r, 3, '', $stC);
        $x->cell($s1, $r, 4, $data['label_os'] ?? '', $stCCB);
        $x->cell($s1, $r, 5, $data['label_gol'] ?? '', $stCCB);
        $x->cell($s1, $r, 6, $data['posisi'] ?? '', $stW);
        $x->cell($s1, $r, 7, $data['j_masuk'] ?? '', $stCCB);
        $x->cell($s1, $r, 8, $ra_masuk, $stCC);
        $x->cell($s1, $r, 9, $ra_ist, $stCC);
        $x->cell($s1, $r, 10, $ra_pulang, $stCC);
        $x->cell($s1, $r, 11, $bor_detail, $bor_detail !== '' ? $stW : $stC);
        $x->cell($s1, $r, 12, $hasil !== '' ? ($hasil . ' kg') : '', $stCC);
        $x->cell($s1, $r, 13, (int)round($data['_upah']), $stCRB);
        $x->cell($s1, $r, 14, $data['_pot'] ? (int)round($data['_pot']) : '', $data['_pot'] ? $stCR : $stC);
        $x->cell($s1, $r, 15, $data['_lembur'] ? (int)round($data['_lembur']) : '', $data['_lembur'] ? $stCR : $stC);
        $x->cell($s1, $r, 16, (int)round($data['_upah'] - $data['_pot'] + $data['_lembur']), $stCRB);
        $r++;
    }
}
// Total paten (angka, bukan rumus) — posisi persis contoh (kolom UPAH & UPAH DIBAYAR)
$tot_upah = 0; $tot_dibayar = 0;
foreach ($rows_per_grup as $gt) {
    foreach ($gt as $rr) {
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

// ---------- SHEET 2: KARYAWAN TIDAK MASUK ----------
$s2 = $x->sheet('KARYAWAN TIDAK MASUK');
$r2 = 1;
$x->cell($s2, $r2, 1, 'KARYAWAN RENCANA TIDAK MASUK (TIDAK ADA TAP MESIN) TGL ' . $judul_tgl, $stT); $x->merge($s2, $r2, 1, $r2, 16); $r2++;
$x->cell($s2, $r2, 1, 'Sumber rencana: RKK. Karyawan dinyatakan tidak masuk bila tidak ada tap mesin (tb_record) pada tanggal tersebut.', $stTH); $x->merge($s2, $r2, 1, $r2, 16); $r2++;
$hdr2L = ['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK','ISTIRAHAT','JAM PULANG'];
$hdr2R = ['HASIL KERJA (kg)','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'];
foreach ($hdr2L as $i => $h) $x->cell($s2, $r2, $i + 1, $h, $stTH);
foreach ($hdr2R as $i => $h) $x->cell($s2, $r2, 11 + $i, $h, $stTHG);
$x->cell($s2, $r2, 16, 'KETERANGAN', $stTH);
$r2++;
if (empty($tm_grup)) {
    $x->cell($s2, $r2, 1, 'Semua karyawan rencana ada tap mesin (tidak ada yang tidak masuk).', $stCB); $x->merge($s2, $r2, 1, $r2, 16); $r2++;
} else {
    foreach ($tm_grup as $gk => $trows) {
        $x->cell($s2, $r2, 1, $gk, $stSEC); $x->merge($s2, $r2, 1, $r2, 16); $r2++;
        $ntm = 1;
        foreach ($trows as $tm) {
            $x->cell($s2, $r2, 1, $ntm++, $stCCB);
            $x->cell($s2, $r2, 2, strtoupper($tm['nama_karyawan'] ?? ''), $stWrap);
            $x->cell($s2, $r2, 3, '', $stC);
            $x->cell($s2, $r2, 4, $tm['oslbl'] ?? '', $stCCB);
            $x->cell($s2, $r2, 5, $tm['gollbl'] ?? '', $stCCB);
            $x->cell($s2, $r2, 6, $tm['posisi'] ?? '', $stC);
            $x->cell($s2, $r2, 7, $tm['sm'] ?? '', $stCCB);
            for ($c = 8; $c <= 11; $c++) $x->cell($s2, $r2, $c, '', $stC);
            $x->cell($s2, $r2, 12, 0, $stCRB);
            $x->cell($s2, $r2, 13, '', $stC);
            $x->cell($s2, $r2, 14, '', $stC);
            $x->cell($s2, $r2, 15, 0, $stCRB);
            $x->cell($s2, $r2, 16, $tm['ket'] ?? '', $stC);
            $r2++;
        }
    }
}

$x->autofit($s1);
$x->autofit($s2);
$x->send("Laporan_Realisasi_Upah_$tanggal.xlsx");
