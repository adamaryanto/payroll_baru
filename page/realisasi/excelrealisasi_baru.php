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
require_once __DIR__ . '/sync_lib.php'; // late_minutes/early_minutes/load_toleransi (toleransi SAMA kayak tarik data)
$tol_x = load_toleransi($koneksi);
// Self-heal kolom pengganti (dipakai query di bawah)
$ckG = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_pengganti'");
if ($ckG && $ckG->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_realisasi_detail ADD COLUMN id_pengganti INT NULL DEFAULT NULL");
}

$id = isset($_GET['id']) ? $_GET['id'] : '';
$only_tdr = (strtolower(trim($_GET['jenis'] ?? '')) === 'tdr');

$queryInfo = $koneksi->query("SELECT tgl_realisasi, status_realisasi, jam_kerja, id_rkk FROM tb_realisasi WHERE id_realisasi = '$id'");
$info = $queryInfo ? $queryInfo->fetch_assoc() : null;
$tanggal_raw = $info ? $info['tgl_realisasi'] : '';
$status_realisasi = $info['status_realisasi'] ?? 0;
$tanggal = $tanggal_raw ? date('d-m-Y', strtotime($tanggal_raw)) : 'TanpaTanggal';
$tanggal_sql = $tanggal_raw ? date('Y-m-d', strtotime($tanggal_raw)) : '';

// SAMA seperti halaman kelola: tap di luar rencana otomatis digabung ke grup
// "TIDAK DALAM RENCANA" di tabel utama (bukan section terpisah, bukan baris PIN).
include_once __DIR__ . '/../../device_sync_helper.php';
if (function_exists('realisasi_proses_tdr') && (int)$id > 0) {
    realisasi_proses_tdr($koneksi, (int)$id, null, true);
}

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
// Telat/cepat memakai late_minutes/early_minutes + toleransi dari sync_lib.php (SAMA kayak tarik data)

// Karyawan per GRUP (urutan grup ikut kemunculan pertama, seperti di RKK kelola)
// Tap mesin per tanggal (dipakai sheet KARYAWAN TIDAK MASUK)
$taps_all = [];
if ($tanggal_sql !== '') {
    $qt0 = $koneksi->query("SELECT DISTINCT TRIM(userid) AS pin FROM tb_record WHERE tgl='$tanggal_sql' AND TRIM(userid)<>''");
    while ($qt0 && ($rt0 = $qt0->fetch_assoc())) $taps_all[trim((string)$rt0['pin'])] = true;
}
$tampil = $koneksi->query("SELECT A.*, IF(A.id_karyawan = 0, A.nama_karyawan_manual, B.nama_karyawan) as nama_karyawan,
    TRIM(B.no_absen) as pin_kar,
    O.OS_DHK as label_os, G.golongan as label_gol, S.jabatan as posisi, J.jam_masuk as j_masuk, J.keterangan as shift_ket,
    GB.nama_grup as grup,
    PG.nama_karyawan as pengganti_nama
    FROM tb_realisasi_detail A
    LEFT JOIN ms_karyawan B ON A.id_karyawan = B.id_karyawan
    LEFT JOIN ms_karyawan PG ON A.id_pengganti = PG.id_karyawan
    LEFT JOIN tb_rkk_detail RD ON A.id_rkk_detail = RD.id_rkk_detail
    LEFT JOIN ms_os_dhk O ON COALESCE(NULLIF(A.id_os_dhk, 0), B.id_os_dhk) = O.id_os_dhk
    LEFT JOIN ms_golongan G ON COALESCE(NULLIF(A.id_golongan, 0), B.id_golongan) = G.id_golongan
    LEFT JOIN ms_jabatan S ON B.id_jabatan = S.id_jabatan
    LEFT JOIN tb_jadwal J ON A.id_jadwal = J.id_jadwal
    LEFT JOIN ms_grup_borongan GB ON A.id_grup = GB.id_grup
    LEFT JOIN (SELECT id_grup, MIN(id_realisasi_detail) AS g_ord FROM tb_realisasi_detail WHERE id_realisasi = '$id' AND id_grup IS NOT NULL GROUP BY id_grup) GO ON GO.id_grup = A.id_grup
    WHERE A.id_realisasi = '$id'
    ORDER BY (GB.nama_grup IS NULL), GO.g_ord ASC, A.id_realisasi_detail ASC");

// Peta grup master (fallback bila baris detail belum punya id_grup,
// mis. diinput sebelum karyawan masuk grup). Kunci: id_karyawan + NAMA (utk baris manual lama).
$grup_nama = []; // id_grup => nama
$qgn = $koneksi->query("SELECT id_grup, nama_grup FROM ms_grup_borongan");
while ($qgn && ($rgn = $qgn->fetch_assoc())) $grup_nama[(int)$rgn['id_grup']] = $rgn['nama_grup'];
$grup_member = []; // id_karyawan => id_grup pertama
$grup_byname = []; // NAMA_UPPER => id_grup pertama
$qgm = $koneksi->query("SELECT ga.id_karyawan, MIN(ga.id_grup) AS g, K.nama_karyawan AS nm FROM ms_grup_borongan_anggota ga LEFT JOIN ms_karyawan K ON K.id_karyawan = ga.id_karyawan GROUP BY ga.id_karyawan");
while ($qgm && ($rgm = $qgm->fetch_assoc())) {
    $grup_member[(int)$rgm['id_karyawan']] = (int)$rgm['g'];
    $nm_u = strtoupper(trim((string)($rgm['nm'] ?? '')));
    if ($nm_u !== '' && !isset($grup_byname[$nm_u])) $grup_byname[$nm_u] = (int)$rgm['g'];
}
function ex_grup_fallback($label_grup, $id_karyawan, $nama_manual, $grup_member, $grup_byname, $grup_nama) {
    if (!empty($label_grup)) return strtoupper(trim($label_grup));
    $gid = 0;
    $kid = (int)($id_karyawan ?? 0);
    if ($kid > 0 && isset($grup_member[$kid])) $gid = $grup_member[$kid];
    if ($gid <= 0) {
        $nm = strtoupper(trim((string)($nama_manual ?? '')));
        if ($nm !== '' && isset($grup_byname[$nm])) $gid = $grup_byname[$nm];
    }
    if ($gid > 0 && isset($grup_nama[$gid])) return strtoupper(trim($grup_nama[$gid]));
    return 'TANPA GRUP';
}

$rows_per_grup = [];
$rows_tdr_x = []; // baris TIDAK DALAM RENCANA -> sheet terpisah
// Denda master sekali (potongan dihitung live persis tampilan kelola, bukan angka simpanan basi)
$qd_ex = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
$dd_ex = $qd_ex ? $qd_ex->fetch_assoc() : [];
$gDendaMasuk = $dd_ex['denda_masuk'] ?? 0;
$gDendaIstKeluar = $dd_ex['denda_istirahat_keluar'] ?? 0;
$gDendaIstMasuk = $dd_ex['denda_istirahat_masuk'] ?? 0;
$gDendaPulang = $dd_ex['denda_pulang'] ?? 0;
$gDendaTdkLengkap = $dd_ex['denda_tidak_lengkap'] ?? 0;
while ($tampil && ($r = $tampil->fetch_assoc())) {
    // Sheet REALISASI tampil semua baris persis halaman kelola (tidak difilter);
    // baris rangkap tetap tampil, yang dijumlah cuma baris pertama per orang.
    // Flag hadir/telat/cepat dihitung live (toleransi SAMA kayak tarik data) agar
    // sel merah SELALU cocok dengan kolom potongan (tidak ikut simpanan lama).
    $has_masuk_x = !empty($r['ra_masuk']) && $r['ra_masuk'] != '00:00:00';
    $has_keluar_x = !empty($r['ra_keluar']) && $r['ra_keluar'] != '00:00:00';
    $has_ist_masuk_x = !empty($r['ra_istirahat_masuk']) && $r['ra_istirahat_masuk'] != '00:00:00';
    $has_ist_keluar_x = !empty($r['ra_istirahat_keluar']) && $r['ra_istirahat_keluar'] != '00:00:00';
    $isLate_x = ($has_masuk_x && !empty($r['r_jam_masuk']) && late_minutes($r['ra_masuk'], $r['r_jam_masuk']) > $tol_x['masuk']);
    $isEarlyOut_x = ($has_keluar_x && !empty($r['r_jam_keluar']) && early_minutes($r['ra_keluar'], $r['r_jam_keluar']) > $tol_x['pulang']);
    $isLateBreak_x = ($has_ist_masuk_x && !empty($r['r_istirahat_masuk']) && late_minutes($r['ra_istirahat_masuk'], $r['r_istirahat_masuk']) > $tol_x['ist_masuk']);
    $isEarlyBreak_x = ($has_ist_keluar_x && !empty($r['r_istirahat_keluar']) && early_minutes($r['ra_istirahat_keluar'], $r['r_istirahat_keluar']) > $tol_x['ist_keluar']);
    $isTotalMissing_x = (!$has_masuk_x && !$has_keluar_x);
    $is_flexible_x = is_flexible_shift($r['shift_ket'] ?? '');
    $isRestExp_x = (!$is_flexible_x && !empty($r['r_istirahat_keluar']) && $r['r_istirahat_keluar'] != '00:00:00');
    if (($r['status_realisasi_detail'] ?? 1) == 0) {
        // Draft (belum sync): semua potongan 0 kecuali lainnya â€” persis kelola
        $pot = (int)($r['r_potongan_lainnya'] ?? 0);
    } else {
        $pot = (int)($r['r_potongan_lainnya'] ?? 0);
        // Flexi murni: bebas semua denda waktu â€” persis kelola
        if (!$is_flexible_x) {
        if ($isLate_x) $pot += $gDendaMasuk;
        if ($isEarlyBreak_x) $pot += $gDendaIstKeluar;
        if ($isLateBreak_x) $pot += $gDendaIstMasuk;
        if ($isEarlyOut_x && !$isTotalMissing_x) $pot += $gDendaPulang;
        if (!$isTotalMissing_x && (($has_masuk_x xor $has_keluar_x) || ($isRestExp_x && (!$has_ist_keluar_x || !$has_ist_masuk_x)))) $pot += $gDendaTdkLengkap;
        }
    }
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
    // Flexi murni: tanpa merah telat/pulang-cepat/istirahat
    $r['_late'] = !$is_flexible_x && $isLate_x;
    $r['_lateb'] = !$is_flexible_x && $isLateBreak_x;
    $r['_earlyo'] = !$is_flexible_x && $isEarlyOut_x;
    $r['_restexp'] = $isRestExp_x;
    $key = ex_grup_fallback($r['grup'] ?? '', $r['id_karyawan'] ?? 0, $r['nama_karyawan'] ?? '', $grup_member, $grup_byname, $grup_nama);
    if (strtoupper(trim((string)$key)) === 'TIDAK DALAM RENCANA') {
        $rows_tdr_x[] = $r;
    } else {
        if (!isset($rows_per_grup[$key])) $rows_per_grup[$key] = [];
        $rows_per_grup[$key][] = $r;
    }
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
    $qr = $koneksi->query("SELECT RD.id_rkk_detail, K.id_karyawan, TRIM(K.no_absen) AS pin, K.nama_karyawan, O.OS_DHK AS oslbl, G.golongan AS gollbl, S.jabatan AS posisi, J.jam_masuk AS sm, GB.nama_grup AS grup FROM tb_rkk_detail RD JOIN ms_karyawan K ON K.id_karyawan=RD.id_karyawan LEFT JOIN ms_os_dhk O ON O.id_os_dhk=K.id_os_dhk LEFT JOIN ms_golongan G ON G.id_golongan=K.id_golongan LEFT JOIN ms_jabatan S ON S.id_jabatan=K.id_jabatan LEFT JOIN tb_jadwal J ON J.id_jadwal=RD.id_jadwal LEFT JOIN ms_grup_borongan GB ON GB.id_grup=RD.id_grup WHERE RD.id_rkk='$id_rkk_link' AND RD.id_karyawan<>0 ORDER BY RD.id_rkk_detail ASC");
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
        $gk = ex_grup_fallback($rr['grup'] ?? '', $rr['id_karyawan'] ?? 0, $rr['nama_karyawan'] ?? '', $grup_member, $grup_byname, $grup_nama);
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

$hdrL = ['No','NAMA SESUAI KTP','PARAF PEKERJA','OS/DHK','GOLONGAN','POSISI','JAM KERJA','JAM MASUK','ISTIRAHAT','JAM PULANG'];
$hdrR = ['HASIL KERJA','UPAH','POTONGAN','LEMBUR','UPAH DIBAYAR'];
if (!$only_tdr) {
// ---------- SHEET 1: REALISASI (kolom sama kayak halaman kelola + Detail Borongan) ----------
$s1 = $x->sheet('REALISASI');
$r = 1;
$x->cell($s1, $r, 1, 'REALISASI ABSENSI KARYAWAN HARI ' . $judul_hari . ' ' . $judul_tgl, $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$x->cell($s1, $r, 1, 'JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000', $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
$x->cell($s1, $r, 1, 'MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.', $stT); $x->merge($s1, $r, 1, $r, 16); $r++;
if (empty($rows_per_grup)) {
    $x->cell($s1, $r, 1, 'Belum ada karyawan yang tap mesin / tarik data pada tanggal ini â€” lihat sheet KARYAWAN TIDAK MASUK. Setelah tap + Tarik Data, export ulang untuk memunculkan data.', $stCB); $x->merge($s1, $r, 1, $r, 16); $r++;
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
        // Warna sel absen (pakai flag yg sama dgn potongan di atas: merah & potongan selalu cocok)
        $cls_masuk = ($ra_masuk === '' || !empty($data['_late'])) ? $stCCRed : $stCC;
        $cls_ist = (($ra_ist === '' && !empty($data['_restexp'])) || !empty($data['_lateb'])) ? $stCCRed : $stCC;
        $cls_pulang = ($ra_pulang === '') ? $stCCRed : (!empty($data['_earlyo']) ? $stCCYellow : $stCC);
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
    if (strtoupper(trim((string)$nama_grup)) === 'KARYAWAN TAMBAHAN') $r++; // margin bawah tabel karyawan tambahan
}
// Total paten (angka, bukan rumus) â€” posisi persis contoh (kolom UPAH & UPAH DIBAYAR)
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
// ===== Footer: kiri = rekap OS + rekap biaya pabrik, kanan = bayaran tim bonless =====
$biaya_mobil_x = 0;
if ($b_head) {
    $idbm_x = (int)($b_head['id_biayamobil'] ?? 0);
    $qbm_x = $koneksi->query($idbm_x > 0
        ? "SELECT biaya_mobil FROM tb_biayamobil WHERE id_biayamobil='$idbm_x' LIMIT 1"
        : "SELECT biaya_mobil FROM tb_biayamobil ORDER BY id_biayamobil ASC LIMIT 1");
    $rbm_x = $qbm_x ? $qbm_x->fetch_assoc() : null;
    $biaya_mobil_x = (float)($rbm_x['biaya_mobil'] ?? 0);
}
$val_bon_mobil = $potong * $biaya_mobil_x;
$plus_items = []; $minus_items = []; $sum_plus = 0; $sum_minus = 0;
foreach ($boneless_details as $bd0) {
    $ln0 = (float)($bd0['qty'] ?? 0) * abs((float)($bd0['harga'] ?? 0));
    if (($bd0['jenis'] ?? '') == 'minus') { $minus_items[] = $bd0; $sum_minus += $ln0; }
    else { $plus_items[] = $bd0; $sum_plus += $ln0; }
}
$val_bon_net = $sum_plus - $sum_minus;   // net boneless (plus - pengurangan)
$val_grand = $tot_dibayar + $val_bon_net; // total realisasi
$val_permobil = ($potong > 0) ? $val_grand / $potong : 0;
$val_pabrik = $tot_dibayar; // cuma total upah karyawan (biaya pabrik)
$val_permobil = ($potong > 0) ? $val_grand / $potong : 0;

$stOSH  = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'B4C7E7', 'border' => true]);
$stGT   = $x->style(['b' => true, 'sz' => 16, 'align' => 'left', 'color' => 'FFFFFF', 'fill' => '1F3864', 'border' => true]);
$stGTR  = $x->style(['b' => true, 'sz' => 16, 'align' => 'right', 'color' => 'FFFFFF', 'fill' => '1F3864', 'border' => true, 'num' => 'rp2']);
$stOSL  = $x->style(['sz' => 16, 'fill' => 'FFFFFF', 'border' => true]);
$stOSR  = $x->style(['sz' => 16, 'align' => 'right', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'rp2']);
$stSub  = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stSubV = $x->style(['b' => true, 'sz' => 16, 'align' => 'right', 'color' => 'FF0000', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'rp2']);
$stTRed = $x->style(['b' => true, 'sz' => 16, 'align' => 'right', 'color' => 'FF0000', 'fill' => 'FFFFFF', 'border' => true, 'num' => 'rp2']);
$stQty  = $x->style(['sz' => 16, 'align' => 'center', 'fill' => 'FFFFFF', 'border' => true]);
$stBHG  = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => '92D050', 'border' => true]);
$stBHO  = $x->style(['b' => true, 'sz' => 16, 'align' => 'center', 'fill' => 'F4B183', 'border' => true]);

// Total upah per OS dari realisasi hari itu; grand = total upah dibayar
$os_tot = [];
foreach ($rows_per_grup as $gt) {
    foreach ($gt as $rr) {
        if (!isset($keep_ids[(int)$rr['id_realisasi_detail']])) continue; // rangkap: tidak dijumlah 2x
        $ok = strtoupper(trim((string)($rr['label_os'] ?? '')));
        if ($ok === '') continue;
        if (!isset($os_tot[$ok])) $os_tot[$ok] = 0;
        $os_tot[$ok] += ($rr['_upah'] - $rr['_pot'] + $rr['_lembur']);
    }
}
// OS yang tampil = OS yang ada di RENCANA (tb_rkk_detail), bukan master ms_os_dhk
$os_master = [];
if ($id_rkk_link > 0) {
    $qom = $koneksi->query("SELECT DISTINCT O.OS_DHK FROM tb_rkk_detail RD JOIN ms_karyawan K ON K.id_karyawan=RD.id_karyawan LEFT JOIN ms_os_dhk O ON O.id_os_dhk=K.id_os_dhk WHERE RD.id_rkk='$id_rkk_link' AND RD.id_karyawan<>0");
    while ($qom && ($rom = $qom->fetch_assoc())) {
        $nm_os = strtoupper(trim((string)($rom['OS_DHK'] ?? '')));
        if ($nm_os !== '' && !in_array($nm_os, $os_master)) $os_master[] = $nm_os;
    }
}
// fallback: kalau tidak ada rkk_link, pakai OS yang muncul di realisasi
if (empty($os_master)) {
    foreach (array_keys($os_tot) as $k) if ($k !== '' && !in_array($k, $os_master)) $os_master[] = $k;
}
$grand_os = $tot_dibayar;

$rTop = $r + 1; // jarak/margin di atas footer (walau tidak ada karyawan tambahan)
$left_row = function ($row, $stA, $stB, $vA, $vB) use ($x, $s1) {
    $x->cell($s1, $row, 1, $vA, $stA); $x->merge($s1, $row, 1, $row, 2);
    $x->cell($s1, $row, 3, $vB, $stB);
};

// --- KANAN: bayaran tim bonless (gabung plus + pengurangan, sesuai file referensi) ---
// kolom: 5-7 No | 8 PENGURANGAN | 9 NAMA TIM | 10 QTY | 11 HARGA SATUAN | 12 TOTAL
$rR = $rTop;
$x->cell($s1, $rR, 5, 'BAYARAN TIM BONLESS', $stRK); $x->merge($s1, $rR, 5, $rR, 12); $rR++;
$nb = 0;
$bon_items = function ($row, $items, $is_pengurangan) use ($x, $s1, $stCCB, $stCB, $stCC, $stRp, &$nb) {
    foreach ($items as $item) {
        $nb++;
        $qty = (float)($item['qty'] ?? 0);
        $hrg = abs((float)($item['harga'] ?? 0));
        $x->cell($s1, $row, 5, $nb, $stCCB); $x->merge($s1, $row, 5, $row, 7);
        $x->cell($s1, $row, 8, $is_pengurangan ? 'PENGURANGAN' : '', $stCCB);
        $x->cell($s1, $row, 9, strtoupper($item['nama_item'] ?? ''), $stCB);
        $x->cell($s1, $row, 10, $qty ? (int)round($qty) : '', $qty ? $stCC : $stC);
        $x->cell($s1, $row, 11, (int)round($hrg), $stRp);
        $x->cell($s1, $row, 12, (int)round($qty * $hrg), $stRp);
        $row++;
    }
    return $row;
};
$rR = $bon_items($rR, $plus_items, false);
$rR = $bon_items($rR, $minus_items, true);
// TOTAL baris (net = plus - pengurangan)
$x->cell($s1, $rR, 5, 'TOTAL', $stTOT); $x->merge($s1, $rR, 5, $rR, 11);
$x->cell($s1, $rR, 12, (int)round($val_bon_net), $stRpB); $rR++;
// DENGAN MESIN BONLES: baris biaya
$x->cell($s1, $rR, 5, 'DENGAN MESIN BONLES', $stRED); $x->merge($s1, $rR, 5, $rR, 12); $rR++;
$x->cell($s1, $rR, 5, 'BIAYA PABRIK', $stYEL); $x->merge($s1, $rR, 5, $rR, 7);
$x->cell($s1, $rR, 8, 'BONLESS', $stYEL);
$x->cell($s1, $rR, 9, 'POTONG', $stYEL);
$x->cell($s1, $rR, 10, 'TOTAL', $stYEL);
$x->cell($s1, $rR, 11, 'Biaya Per mobil', $stYEL); $x->merge($s1, $rR, 11, $rR, 12);
$rR++;
$x->cell($s1, $rR, 5, round($tot_dibayar, 2), $stRp2); $x->merge($s1, $rR, 5, $rR, 7);
$x->cell($s1, $rR, 8, round($val_bon_net, 2), $stRp2);
$x->cell($s1, $rR, 9, (int)$potong, $stCC);
$x->cell($s1, $rR, 10, round($val_grand, 2), $stRp2);
$x->cell($s1, $rR, 11, round($val_permobil, 2), $stRp2); $x->merge($s1, $rR, 11, $rR, 12);
$rR++;
$rR_end = $rR - 1; // baris terakhir DENGAN MESIN BONLES

// --- KIRI atas: rekap biaya pabrik (posisi ditukar ke atas) ---
$rL = $rTop;
$x->cell($s1, $rL, 1, 'REALISASI TOTAL REKAP BIAYA PABRIK', $stOSH); $x->merge($s1, $rL, 1, $rL, 3); $rL++;
$x->cell($s1, $rL, 1, strtoupper(date('d-M-y', strtotime($tanggal_sql ?: 'now'))), $stOSH); $x->merge($s1, $rL, 1, $rL, 3); $rL++;
$left_row($rL, $stCB, $stOSR, 'Biaya ' . (int)$potong . ' mobil', round($val_pabrik, 2)); $rL++;
$left_row($rL, $stCB, $stOSR, 'Biaya permobil', round($val_permobil, 2)); $rL++;

// --- KIRI bawah: rekap outsourcing, rata bawah dengan HASIL AKHIR ---
$os_box_h = 2 + 1 + count($os_master) + 1;
$rOS = $rR_end - ($os_box_h - 1);
if ($rOS < $rL + 4) $rOS = $rL + 4; // jarak minimal dari kotak biaya pabrik
$ro = $rOS;
$x->cell($s1, $ro, 1, 'REALISASI TOTAL REKAP OUTSOURCING', $stOSH); $x->merge($s1, $ro, 1, $ro, 3); $ro++;
$x->cell($s1, $ro, 1, strtoupper(date('d-M-y', strtotime($tanggal_sql ?: 'now'))), $stOSH); $x->merge($s1, $ro, 1, $ro, 3); $ro++;
$left_row($ro, $stTHG, $stTHG, 'KATEGORI OS', 'TOTAL UPAH DIBAYAR'); $ro++;
foreach ($os_master as $nm_os) {
    $left_row($ro, $stCB, $stOSR, $nm_os, round((float)($os_tot[$nm_os] ?? 0), 2)); $ro++;
}
$left_row($ro, $stGT, $stGTR, 'GRAND TOTAL', round($grand_os, 2)); $ro++;

$r = max($rL, $rR, $ro);
$r++;
$x->cell($s1, $r, 1, $stamp_text, $stStamp); $x->merge($s1, $r, 1, $r, 3); $r++;
}

if ($only_tdr) {
// ---------- SHEET: TIDAK DALAM RENCANA (dipisah dari REALISASI) ----------
$sT = $x->sheet('TIDAK DALAM RENCANA');
$rt = 1;
$x->cell($sT, $rt, 1, 'TIDAK DALAM RENCANA HARI ' . $judul_hari . ' ' . $judul_tgl, $stT); $x->merge($sT, $rt, 1, $rt, 16); $rt++;
$x->cell($sT, $rt, 1, 'JIKA NAMA YANG TERTERA DIABSEN TELAT LEBIH DARI 10 MENIT MAKA AKAN DIPOTONG RP 25.000', $stT); $x->merge($sT, $rt, 1, $rt, 16); $rt++;
$x->cell($sT, $rt, 1, 'MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.', $stT); $x->merge($sT, $rt, 1, $rt, 16); $rt++;
if (empty($rows_tdr_x)) {
    $x->cell($sT, $rt, 1, 'Tidak ada tap di luar rencana pada tanggal ini.', $stCB); $x->merge($sT, $rt, 1, $rt, 16); $rt++;
} else {
    $x->cell($sT, $rt, 1, 'TIDAK DALAM RENCANA', $stSEC); $x->merge($sT, $rt, 1, $rt, 16); $rt++;
    foreach ($hdrL as $i => $h) $x->cell($sT, $rt, $i + 1, $h, $stTH);
    $x->cell($sT, $rt, 11, 'Detail Borongan', $stTH);
    foreach ($hdrR as $i => $h) $x->cell($sT, $rt, 12 + $i, $h, $stTHG);
    $rt++;
    $x->cell($sT, $rt, 9, 'MASUK', $stTH); $rt++;
    $no_t = 1;
    $tot_t_upah = 0; $tot_t_dibayar = 0;
    foreach ($rows_tdr_x as $data) {
        $ra_masuk_raw = ($data['ra_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_masuk'] : '';
        $ra_ist_raw = ($data['ra_istirahat_masuk'] ?? '00:00:00') != '00:00:00' ? $data['ra_istirahat_masuk'] : '';
        $ra_pulang_raw = ($data['ra_keluar'] ?? '00:00:00') != '00:00:00' ? $data['ra_keluar'] : '';
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
        $nama = strtoupper($data['nama_karyawan'] ?? '');
        $x->cell($sT, $rt, 1, $no_t++, $stCCB);
        $x->cell($sT, $rt, 2, $nama, $stWrap);
        $x->cell($sT, $rt, 3, '', $stC);
        $x->cell($sT, $rt, 4, $data['label_os'] ?? '', $stCCB);
        $x->cell($sT, $rt, 5, $data['label_gol'] ?? '', $stCCB);
        $x->cell($sT, $rt, 6, $data['posisi'] ?? '', $stW);
        $x->cell($sT, $rt, 7, $j_masuk_disp, $stCCB);
        $x->cell($sT, $rt, 8, $ra_masuk, $stCC);
        $x->cell($sT, $rt, 9, $ra_ist, $stCC);
        $x->cell($sT, $rt, 10, $ra_pulang, $stCC);
        $x->cell($sT, $rt, 11, $bor_detail, $bor_detail !== '' ? $stW : $stC);
        $x->cell($sT, $rt, 12, $hasil, $stCC);
        $x->cell($sT, $rt, 13, (int)round($data['_upah']), $stCRB);
        $x->cell($sT, $rt, 14, $data['_pot'] ? (int)round($data['_pot']) : '', $data['_pot'] ? $stCR : $stC);
        $x->cell($sT, $rt, 15, $data['_lembur'] ? (int)round($data['_lembur']) : '', $data['_lembur'] ? $stCR : $stC);
        $x->cell($sT, $rt, 16, (int)round($data['_upah'] - $data['_pot'] + $data['_lembur']), $stCRB);
        $tot_t_upah += $data['_upah'];
        $tot_t_dibayar += ($data['_upah'] - $data['_pot'] + $data['_lembur']);
        $rt++;
    }
    for ($c = 1; $c <= 12; $c++) $x->cell($sT, $rt, $c, '', $stC);
    $x->cell($sT, $rt, 13, (int)round($tot_t_upah), $stCRB);
    $x->cell($sT, $rt, 14, '', $stC); $x->cell($sT, $rt, 15, '', $stC);
    $x->cell($sT, $rt, 16, (int)round($tot_t_dibayar), $stCRB);
    $rt++;
}

}

if (!$only_tdr) {
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
}

if ($only_tdr) {
    $x->autofit($sT);
    $x->send("TIDAK_DALAM_RENCANA_$tanggal.xlsx");
} else {
    $x->autofit($s1);
    $x->autofit($s2);
    $x->width($s1, 2, 335); // NAMA SESUAI KTP (juga lebar label kotak kiri)
    $x->width($s1, 3, 225); // PARAF PEKERJA / kolom nilai kotak kiri
    $x->width($s1, 6, 222); // POSISI
    $x->width($s1, 7, 95);  // JAM KERJA
    $x->width($s1, 8, 190); // JAM MASUK / PENGURANGAN (kanan)
    $x->width($s1, 9, 250); // ISTIRAHAT / NAMA TIM (kanan)
    $x->width($s1, 10, 200); // JAM PULANG / QTY / TOTAL (kanan)
    $x->width($s1, 11, 140); // DETAIL BORONGAN / HARGA SATUAN (kanan)
    $x->width($s1, 12, 190); // HASIL KERJA / TOTAL (kanan)
    $x->send("Laporan_Realisasi_Upah_$tanggal.xlsx");
}
