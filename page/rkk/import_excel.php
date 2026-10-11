<?php
// Import Excel absensi harian -> tb_rkk (+detail) & tb_realisasi (+detail).
// Format: sheet PERENCANAAN + REALISASI, kolom:
//   A No | B Nama | C Paraf | D OS/DHK | E Golongan | F Posisi | G Jam Kerja(fraksi)
//   H Jam Masuk | I Istirahat | J Jam Pulang | K Hasil | L Upah | M Potongan | N Lembur | O Upah Dibayar
// Alur: upload -> preview (cocok/baru/kotor) -> commit.
// Keputusan user: shift ditebak dari durasi JAM KERJA; potongan di-split otomatis
// ikut aturan tb_denda + tb_toleransi; nama tak cocok -> buatkan karyawan baru.

if (!function_exists('rx_col2num')) {
function rx_col2num($c) { $n = 0; for ($i = 0; $i < strlen($c); $i++) $n = $n * 26 + ord($c[$i]) - 64; return $n; }
}

if (!function_exists('rx_read_xlsx')) {
// Baca xlsx minimal (ZipArchive + SimpleXML). Return [sheetName => [rowNum => [colNum => val]]].
function rx_read_xlsx($file) {
    $NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    try {
    $z = new ZipArchive();
    if ($z->open($file) !== true) return false;
    $zx = function($p) use ($z) { $s = $z->getFromName($p); return $s === false ? null : $s; };
    $xload = function($raw) {
        if ($raw === null || $raw === '') return null;
        try { $x = new SimpleXMLElement($raw); return $x; }
        catch (Exception $e) { return null; }
    };
    $cellText = function($c, $ss) use ($NS) {
        $t = (string)$c->attributes()->t;
        $kids = $c->children($NS);
        if ($t === 's') { $idx = (int)(string)$kids->v; return isset($ss[$idx]) ? $ss[$idx] : ''; }
        if (isset($kids->is)) { $is = $kids->is->children($NS); return (string)$is->t; }
        return (string)$kids->v;
    };
    $ss = [];
    if ($raw = $zx('xl/sharedStrings.xml')) {
        if ($x = $xload($raw)) {
            foreach ($x->children($NS)->si as $si) {
                $txt = '';
                foreach ($si->children($NS)->r as $r) { $txt .= (string)$r->children($NS)->t; }
                if ($txt === '') { $t = $si->children($NS)->t; $txt = (string)$t; }
                $ss[] = $txt;
            }
        }
    }
    $wb = $xload($zx('xl/workbook.xml'));
    $rx = $xload($zx('xl/_rels/workbook.xml.rels'));
    if (!$wb || !$rx) { $z->close(); return false; }
    $rels = [];
    foreach ($rx->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $rel) {
        $a = $rel->attributes();
        $t = ltrim((string)$a['Target'], '/');
        if (strpos($t, 'xl/') !== 0) $t = 'xl/' . $t;
        $rels[(string)$a['Id']] = $t;
    }
    $out = [];
    foreach ($wb->children($NS)->sheets->children($NS)->sheet as $sh) {
        $a = $sh->attributes();
        $name = (string)$a['name'];
        $rid = '';
        foreach ($sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $v) $rid = (string)$v;
        $path = isset($rels[$rid]) ? $rels[$rid] : null;
        if (!$path) {
            // Fallback: tebak path sheet langsung
            foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $cand) {
                if ($zx($cand) !== null && !isset($out[$name . '_tebak'])) { $path = $cand; break; }
            }
        }
        if (!$path) continue;
        $sx = $xload($zx($path));
        if (!$sx) continue;
        $rows = [];
        foreach ($sx->children($NS)->sheetData->children($NS)->row as $row) {
            $ra = $row->attributes();
            $rn = (int)$ra['r'];
            foreach ($row->children($NS)->c as $c) {
                $ca = $c->attributes();
                if (!preg_match('/^([A-Z]+)(\d+)$/', (string)$ca['r'], $m)) continue;
                $rows[$rn][rx_col2num($m[1])] = $cellText($c, $ss);
            }
        }
        ksort($rows);
        $out[$name] = $rows;
    }
    $z->close();
    return $out;
    } catch (Exception $e) {
        return false;
    }
}
}

if (!function_exists('rx_norm_name')) {
function rx_norm_name($s) {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}
}

if (!function_exists('rx_frac_to_time')) {
// Fraksi Excel (0.41..) atau string "07:00" -> 'HH:MM:SS' ('' bila kosong)
function rx_frac_to_time($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '`') return '';
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m)) {
        return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], isset($m[3]) ? (int)$m[3] : 0);
    }
    if (is_numeric($v)) {
        $sec = (int)round(((float)$v - floor((float)$v)) * 86400);
        if ($sec < 0) $sec = 0;
        if ($sec >= 86400) $sec = $sec % 86400;
        return sprintf('%02d:%02d:%02d', (int)($sec / 3600), (int)(($sec % 3600) / 60), $sec % 60);
    }
    return '';
}
}

if (!function_exists('rx_frac_to_hours')) {
function rx_frac_to_hours($v) {
    $v = trim((string)$v);
    if ($v === '' || !is_numeric($v)) return 0;
    return round(((float)$v) * 24, 2);
}
}

if (!function_exists('rx_money')) {
function rx_money($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '`') return 0;
    $v = preg_replace('/[^0-9.\-]/', '', $v);
    if ($v === '' || $v === '-' || $v === '.') return 0;
    return (float)$v;
}
}

if (!function_exists('rx_parse_id_date')) {
// "FORMAT ABSENSI KARYAWAN HARI JUM'AT 02 OKTOBER 2026" -> '2026-10-02'
function rx_parse_id_date($s) {
    $map = ['JANUARI'=>'01','FEBRUARI'=>'02','MARET'=>'03','APRIL'=>'04','MEI'=>'05','JUNI'=>'06',
        'JULI'=>'07','AGUSTUS'=>'08','SEPTEMBER'=>'09','OKTOBER'=>'10','NOVEMBER'=>'11','DESEMBER'=>'12'];
    if (preg_match('/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/', strtoupper((string)$s), $m)) {
        if (isset($map[$m[2]])) return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$map[$m[2]], (int)$m[1]);
    }
    return null;
}
}

if (!function_exists('rx_is_junk')) {
// Baris non-karyawan (subtotal/biaya/rumus rusak) — jangan pernah jadi karyawan.
function rx_is_junk($name) {
    $u = strtoupper(trim((string)$name));
    if ($u === '' || $u[0] === '#') return true;
    return preg_match('/\b(TOTAL|MASUK|KELUAR|JUMLAH|SUBTOTAL|BIAYA|BAYARAN|PENGURANGAN|DIV\/0|BULANAN|MINGGUAN|HARIAN|GRAND)\b/', $u) === 1;
}
}

if (!function_exists('rx_extract_rows')) {
// Ambil baris data dari satu sheet. Return ['rows'=>[...], 'skipped'=>[...]].
function rx_extract_rows($rows) {
    $out = [];
    $skipped = [];
    $section = '';
    foreach ($rows as $rn => $cols) {
        $get = function($n) use ($cols) { return trim((string)($cols[$n] ?? '')); };
        $b = $get(2);
        // Header kolom?
        if (stripos($b, 'NAMA') !== false) continue;
        // Judul/aturan (baris 1-3)?
        if ($rn <= 4 && $b === '' && $get(1) !== '' && !is_numeric($get(1))) {
            if ($rn > 3) $section = $get(1);
            continue;
        }
        // Baris section (ADMIN / QUALITY CONTROL / ...): A terisi, B kosong
        if ($b === '') {
            // Coba cari nama nyasar di kolom lain (toleransi format rusak)
            $found = '';
            $foundCol = 0;
            for ($c = 3; $c <= 20; $c++) {
                $v = $get($c);
                if (preg_match('/[A-Za-z]{3,}/', $v) && !is_numeric(str_replace(['.', ',', ' '], '', $v)) && strlen($v) >= 3) { $found = $v; $foundCol = $c; break; }
            }
            if ($found === '' || rx_is_junk($found)) {
                if ($found !== '') $skipped[] = ['row' => $rn, 'nama' => $found, 'alasan' => 'baris non-karyawan'];
                if ($get(1) !== '' && !is_numeric($get(1))) $section = $get(1);
                continue;
            }
            $b = $found;
            $dirty = true;
        } else {
            $dirty = false;
            if (rx_is_junk($b)) { $skipped[] = ['row' => $rn, 'nama' => $b, 'alasan' => 'baris non-karyawan']; continue; }
        }
        $out[] = [
            'row' => $rn,
            'section' => $section,
            'nama' => $b,
            'osdhk' => $get(4),
            'golongan' => $get(5),
            'posisi' => $get(6),
            'jam_kerja' => rx_frac_to_hours($get(7)),
            'jam_masuk' => rx_frac_to_time($get(8)),
            'istirahat' => rx_frac_to_time($get(9)),
            'jam_pulang' => rx_frac_to_time($get(10)),
            'hasil' => $get(11),
            'upah' => rx_money($get(12)),
            'potongan' => rx_money($get(13)),
            'lembur' => rx_money($get(14)),
            'upah_dibayar' => rx_money($get(15)),
            'dirty' => $dirty,
        ];
    }
    return ['rows' => $out, 'skipped' => $skipped];
}
}

if (!function_exists('rx_guess_shift')) {
// Tebak shift dari durasi jam kerja -> baris tb_jadwal (durasi terdekat)
function rx_guess_shift($koneksi, $hours) {
    $best = null; $bestDiff = null;
    $q = $koneksi->query("SELECT * FROM tb_jadwal");
    if (!$q) return null;
    while ($r = $q->fetch_assoc()) {
        $in = strtotime($r['jam_masuk'] ?? '00:00:00');
        $out = strtotime($r['jam_keluar'] ?? '00:00:00');
        $dur = ($out - $in) / 3600;
        if ($dur <= 0) $dur += 24;
        if (($r['jam_masuk'] ?? '') === '00:00:00' && ($r['jam_keluar'] ?? '') === '00:00:00') continue; // lewati Flexible/nol
        $diff = abs($dur - (float)$hours);
        if ($best === null || $diff < $bestDiff) { $best = $r; $bestDiff = $diff; }
    }
    return $best;
}
}

if (!function_exists('rx_match_karyawan')) {
function rx_match_karyawan($koneksi, $name) {
    $n = rx_norm_name($name);
    if ($n === '') return null;
    $esc = $koneksi->real_escape_string($n);
    $q = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan, id_jadwal, upah, id_os_dhk, id_golongan FROM ms_karyawan WHERE TRIM(nama_karyawan)='$esc' OR LOWER(TRIM(nama_karyawan))=LOWER('$esc') LIMIT 1");
    if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    return null;
}
}

if (!function_exists('rx_lookup_id')) {
function rx_lookup_id($koneksi, $table, $col, $val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $pk = null;
    $q = $koneksi->query("SHOW COLUMNS FROM $table");
    if ($q) { $first = $q->fetch_assoc(); $pk = $first['Field']; }
    if (!$pk) return 0;
    $esc = $koneksi->real_escape_string($val);
    $qr = $koneksi->query("SELECT $pk FROM $table WHERE UPPER(TRIM($col))=UPPER('$esc') LIMIT 1");
    if ($qr && $qr->num_rows > 0) { $rw = $qr->fetch_assoc(); return (int)$rw[$pk]; }
    return 0;
}
}

if (!function_exists('rx_next_no_absen')) {
function rx_next_no_absen($koneksi) {
    $q = $koneksi->query("SELECT MAX(no_absen) AS mx FROM ms_karyawan");
    $mx = ($q && ($r = $q->fetch_assoc())) ? (int)$r['mx'] : 0;
    $cand = max($mx + 1, 1);
    do {
        $cek = $koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$cand' LIMIT 1");
        $taken = $cek && $cek->num_rows > 0;
        if ($taken) $cand++;
    } while ($taken);
    return $cand;
}
}

if (!function_exists('rx_day_diff')) {
// Selisih detik aktual-expected dgn handling lewat tengah malam (ikut pola is_time_late)
function rx_day_diff($actual, $expected) {
    $diff = strtotime($actual) - strtotime($expected);
    if ($diff > 43200) $diff -= 86400;
    elseif ($diff < -43200) $diff += 86400;
    return $diff;
}
}

if (!function_exists('rx_valid_time')) {
function rx_valid_time($t) {
    $t = trim((string)$t);
    return $t !== '' && $t !== '00:00' && $t !== '00:00:00';
}
}
?>
<div class="container-fluid px-2 mt-4 mb-4">
<div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
<div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
<div>
<h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fas fa-file-excel mr-2"></i>Import Excel Absensi Harian</h3>
<p class="text-[12px] text-gray-400 mt-0.5 mb-0">Upload file format absensi (sheet <code>PERENCANAAN</code> + <code>REALISASI</code>) → preview → simpan ke RKK &amp; Realisasi</p>
</div>
<div class="w-full md:w-auto mt-2 md:mt-0">
<a href="?page=rkk" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto"><i class="fas fa-arrow-left mr-1.5"></i> Kembali</a>
</div>
</div>
<div class="p-6">
<?php
$step = $_POST['rx_step'] ?? 'upload';
if ($step === 'commit') {
    // ===== COMMIT =====
    $imp = $_SESSION['rx_import'] ?? null;
    if (!$imp) {
        echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">Data preview kedaluwarsa. Upload ulang filenya.</div>';
    } else {
        // Hanya baris yang dicentang di preview
        $sel = array_map('intval', (array)($_POST['rx_sel'] ?? []));
        $imp['rows'] = array_values(array_filter($imp['rows'], function($_, $i) use ($sel) { return in_array($i, $sel, true); }, ARRAY_FILTER_USE_BOTH));
        if (empty($imp['rows'])) {
            echo '<div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">Tidak ada baris yang dipilih.</div>';
        } else {
        $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $imp['date'] ?? '') ? $imp['date'] : null;
        $tgl = !empty($_POST['rx_date']) ? $_POST['rx_date'] : $tgl;
        if (!$tgl) {
            echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">Tanggal tidak valid.</div>';
        } else {
            $cek = $koneksi->query("SELECT id_rkk FROM tb_rkk WHERE tgl_rkk='$tgl' LIMIT 1");
            if ($cek && $cek->num_rows > 0) {
                echo '<div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">Tanggal ' . htmlspecialchars($tgl) . ' sudah ada RKK-nya. Hapus dulu via halaman RKK kalau mau import ulang.</div>';
            } else {
                $now = date('Y-m-d H:i:s');
                $ket = $koneksi->real_escape_string('Import Excel ' . ($imp['title'] ?? $tgl));
                $jamkerja = 0;
                foreach ($imp['rows'] as $r) $jamkerja = max($jamkerja, (float)($r['jam_kerja'] ?? 0));
                $jamkerja = $jamkerja > 0 ? (int)round($jamkerja) : 12;
                $koneksi->query("INSERT INTO tb_rkk (tgl_rkk, keterangan, jam_kerja, detail_rkk, status_rkk, tgl_status) VALUES ('$tgl', '$ket', '$jamkerja', '$now', '0', '$now')");
                $id_rkk = $koneksi->insert_id;
                $c_kar = 0; $c_new = 0; $c_real = 0;
                // FK guard: tb_rkk_detail.id_departmen/sub wajib valid
                $def_dept = 1; $def_sub = 1;
                $qd = $koneksi->query("SELECT MIN(id_departmen) AS m FROM ms_departmen");
                if ($qd && ($rd = $qd->fetch_assoc()) && $rd['m']) $def_dept = (int)$rd['m'];
                $qs = $koneksi->query("SELECT MIN(id_sub_department) AS m FROM ms_sub_department");
                if ($qs && ($rs = $qs->fetch_assoc()) && $rs['m']) $def_sub = (int)$rs['m'];
                $rkk_detail_map = []; // nama_norm => id_rkk_detail
                foreach ($imp['rows'] as $r) {
                    $kid = (int)($r['karyawan_id'] ?? 0);
                    if ($kid <= 0 && !empty($r['nama'])) {
                        // Buatkan karyawan baru (keputusan user)
                        $no_absen = rx_next_no_absen($koneksi);
                        $nm_esc = $koneksi->real_escape_string($r['nama']);
                        $up = (float)($r['upah'] ?? 0);
                        $jd = (int)($r['shift_id'] ?? 0);
                        $os = rx_lookup_id($koneksi, 'ms_os_dhk', 'OS_DHK', $r['osdhk'] ?? '');
                        $gol = rx_lookup_id($koneksi, 'ms_golongan', 'golongan', $r['golongan'] ?? '');
                        if ($koneksi->query("INSERT INTO ms_karyawan (id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah, target_mingguan, target_bulanan) VALUES ('0','0','$no_absen','$nm_esc','',NULL, '',NULL,NULL,'','','','','Aktif','$tgl','0','$os','$gol','$jd','$up','20.00','40.00')")) {
                            $kid = $koneksi->insert_id;
                            $c_new++;
                        }
                    }
                    if ($kid <= 0) continue;
                    $up = (float)($r['upah'] ?? 0);
                    $jd = (int)($r['shift_id'] ?? 0);
                    $koneksi->query("INSERT INTO tb_rkk_detail (id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, upah, status_rkk, id_jadwal, potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) VALUES ('$id_rkk', '$kid', NULL, '$def_dept', '$def_sub', '$up', 'Hadir', '$jd', '0', '0', '0', '$now')");
                    $rkk_detail_map[rx_norm_name($r['nama'])] = $koneksi->insert_id;
                    $c_kar++;
                }
                // Header realisasi
                $koneksi->query("INSERT INTO tb_realisasi (id_rkk, keterangan, tgl_realisasi, detail_realisasi, jam_kerja, status_realisasi, tgl_status) VALUES ('$id_rkk', '$ket', '$tgl', '$now', '$jamkerja', '0', '$tgl')");
                $id_real = $koneksi->insert_id;
                foreach ($imp['rows'] as $r) {
                    if (empty($r['has_real'])) continue;
                    $kid = (int)($r['karyawan_id'] ?? 0);
                    // karyawan baru dibuat di loop atas? ambil ulang bila perlu
                    if ($kid <= 0) {
                        $m = rx_match_karyawan($koneksi, $r['nama']);
                        if ($m) $kid = (int)$m['id_karyawan'];
                    }
                    if ($kid <= 0) continue;
                    $rkd = $rkk_detail_map[rx_norm_name($r['nama'])] ?? 0;
                    $sh = $r['shift'] ?? null;
                    $r_masuk = $sh ? ($sh['jam_masuk'] ?? '00:00:00') : '00:00:00';
                    $r_keluar = $sh ? ($sh['jam_keluar'] ?? '00:00:00') : '00:00:00';
                    $r_ist_m = $sh ? ($sh['istirahat_masuk'] ?? '00:00:00') : '00:00:00';
                    $r_ist_k = $sh ? ($sh['istirahat_keluar'] ?? '00:00:00') : '00:00:00';
                    $ra_masuk = $r['jam_masuk'] !== '' ? $r['jam_masuk'] : '00:00:00';
                    $ra_keluar = $r['jam_pulang'] !== '' ? $r['jam_pulang'] : '00:00:00';
                    $p = $r['pot_split'];
                    $ru = (float)($r['upah'] ?? 0);
                    $hasil = $koneksi->real_escape_string($r['hasil'] ?? '');
                    $lembur = (float)($r['lembur'] ?? 0);
                    $jd = (int)($r['shift_id'] ?? 0);
                    $st = (rx_valid_time($ra_masuk) || rx_valid_time($ra_keluar)) ? 'Hadir' : 'Tidak Hadir';
                    $koneksi->query("INSERT INTO tb_realisasi_detail (id_realisasi, id_rkk_detail, id_rkk, id_karyawan, id_departmen, id_sub_department, id_os_dhk, id_golongan, r_upah, r_potongan_telat, r_potongan_istirahat_awal, r_potongan_istirahat_telat, r_potongan_lainnya, r_jam_masuk, r_jam_keluar, r_istirahat_masuk, r_istirahat_keluar, r_status, r_update, ra_masuk, ra_keluar, ra_istirahat_masuk, ra_istirahat_keluar, id_jadwal, hasil_kerja, lembur, tgl_realisasi_detail, r_potongan_pulang, r_potongan_tidak_lengkap) VALUES ('$id_real', '$rkd', '$id_rkk', '$kid', '0', '0', '0', '0', '$ru', '{$p['telat']}', '{$p['ist_awal']}', '{$p['ist_telat']}', '{$p['lainnya']}', '$r_masuk', '$r_keluar', '$r_ist_m', '$r_ist_k', '$st', '', '$ra_masuk', '$ra_keluar', '00:00:00', '00:00:00', '$jd', '$hasil', '$lembur', '$tgl', '{$p['pulang']}', '{$p['tdk']}' )");
                    $c_real++;
                }
                echo '<div class="p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800 mb-4"><strong>Import selesai.</strong> RKK#' . (int)$id_rkk . ' (' . htmlspecialchars($tgl) . '): ' . $c_kar . ' karyawan'
                    . ($c_new > 0 ? " ($c_new karyawan baru dibuat)" : '') . ', ' . $c_real . ' baris realisasi.</div>';
                echo '<a href="?page=rkk" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded shadow-sm">Lihat RKK</a> ';
                unset($_SESSION['rx_import']);
            } // tutup: baris dipilih tidak kosong
        }
        }
    }
} elseif ($step === 'preview' && !empty($_FILES['fexcel']['tmp_name'])) {
    // ===== PARSE + PREVIEW =====
    $tmp = $_FILES['fexcel']['tmp_name'];
    $sheets = rx_read_xlsx($tmp);
    if ($sheets === false || empty($sheets)) {
        echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">File tidak bisa dibaca (bukan .xlsx valid?).</div>';
    } else {
        $plan = ['rows' => [], 'skipped' => []]; $real = ['rows' => [], 'skipped' => []]; $title = '';
        foreach ($sheets as $sname => $rows) {
            $up = strtoupper($sname);
            if (strpos($up, 'RENCANA') !== false || strpos($up, 'PERENCANA') !== false) $plan = rx_extract_rows($rows);
            elseif (strpos($up, 'REALISASI') !== false || strpos($up, 'REAL') !== false) $real = rx_extract_rows($rows);
            if ($title === '' && isset($rows[1][1])) $title = trim((string)$rows[1][1]);
        }
        if (empty($plan['rows']) && empty($real['rows'])) {
            // fallback: sheet pertama = rencana, kedua = realisasi
            $vals = array_values($sheets);
            if (isset($vals[0])) $plan = rx_extract_rows($vals[0]);
            if (isset($vals[1])) $real = rx_extract_rows($vals[1]);
        }
        $skipped_all = array_merge(
            array_map(function($s) { $s['sheet'] = 'PERENCANAAN'; return $s; }, $plan['skipped']),
            array_map(function($s) { $s['sheet'] = 'REALISASI'; return $s; }, $real['skipped'])
        );
        $plan = $plan['rows']; $real = $real['rows'];
        $date = rx_parse_id_date($title);
        // Gabung per nama (kunci: nama normal)
        $merged = [];
        foreach ($plan as $r) {
            $k = mb_strtolower(rx_norm_name($r['nama']));
            if (!isset($merged[$k])) $merged[$k] = ['nama' => $r['nama'], 'plan' => null, 'real' => null];
            $merged[$k]['plan'] = $r;
        }
        foreach ($real as $r) {
            $k = mb_strtolower(rx_norm_name($r['nama']));
            if (!isset($merged[$k])) $merged[$k] = ['nama' => $r['nama'], 'plan' => null, 'real' => null];
            $merged[$k]['real'] = $r;
        }
        // Aturan denda + toleransi
        $dq = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
        $dd = $dq ? $dq->fetch_assoc() : [];
        $denda = ['masuk' => (float)($dd['denda_masuk'] ?? 0), 'ist_keluar' => (float)($dd['denda_istirahat_keluar'] ?? 0),
            'ist_masuk' => (float)($dd['denda_istirahat_masuk'] ?? 0), 'pulang' => (float)($dd['denda_pulang'] ?? 0),
            'tdk' => (float)($dd['denda_tidak_lengkap'] ?? 0)];
        $tq = $koneksi->query("SELECT * FROM tb_toleransi LIMIT 1");
        $td = $tq ? $tq->fetch_assoc() : [];
        $tol = ['masuk' => (int)($td['toleransi_masuk'] ?? 10), 'ist_keluar' => (int)($td['toleransi_istirahat_keluar'] ?? 5),
            'ist_masuk' => (int)($td['toleransi_istirahat_masuk'] ?? 10), 'pulang' => (int)($td['toleransi_pulang'] ?? 5)];
        $rows = [];
        foreach ($merged as $m) {
            $base = $m['plan'] ?: $m['real'];
            $nama = $base['nama'];
            $dur = 0;
            if ($m['plan'] && $m['plan']['jam_kerja'] > 0) $dur = (float)$m['plan']['jam_kerja'];
            elseif ($m['real'] && $m['real']['jam_kerja'] > 0) $dur = (float)$m['real']['jam_kerja'];
            $shift = rx_guess_shift($koneksi, $dur);
            $match = rx_match_karyawan($koneksi, $nama);
            $has_real = $m['real'] && (rx_valid_time($m['real']['jam_masuk']) || rx_valid_time($m['real']['jam_pulang']));
            $pot = ['telat' => 0, 'ist_awal' => 0, 'ist_telat' => 0, 'pulang' => 0, 'tdk' => 0, 'lainnya' => 0];
            if ($has_real && $shift) {
                $ra_m = $m['real']['jam_masuk']; $ra_p = $m['real']['jam_pulang'];
                if (rx_valid_time($ra_m) && rx_valid_time($shift['jam_masuk']) && rx_day_diff($ra_m, $shift['jam_masuk']) > $tol['masuk'] * 60) $pot['telat'] = $denda['masuk'];
                if (rx_valid_time($ra_p) && rx_valid_time($shift['jam_keluar']) && rx_day_diff($shift['jam_keluar'], $ra_p) > $tol['pulang'] * 60) $pot['pulang'] = $denda['pulang'];
                $hm = rx_valid_time($ra_m); $hp = rx_valid_time($ra_p);
                if ($hm xor $hp) $pot['tdk'] = $denda['tdk'];
            }
            $excel_pot = $m['real'] ? (float)$m['real']['potongan'] : 0;
            $rows[] = [
                'nama' => $nama,
                'section' => $base['section'] ?? '',
                'dirty' => !empty($base['dirty']),
                'karyawan_id' => $match ? (int)$match['id_karyawan'] : 0,
                'karyawan_nama' => $match ? $match['nama_karyawan'] : '',
                'shift_id' => 0, // diisi di bawah dari $shift[id_jadwal]
                'shift' => $shift,
                'jam_kerja' => $dur,
                'upah' => $m['plan'] ? (float)$m['plan']['upah'] : (float)($m['real']['upah'] ?? 0),
                'osdhk' => $base['osdhk'] ?? '',
                'golongan' => $base['golongan'] ?? '',
                'in_plan' => !empty($m['plan']),
                'has_real' => $has_real,
                'jam_masuk' => $m['real']['jam_masuk'] ?? '',
                'jam_pulang' => $m['real']['jam_pulang'] ?? '',
                'hasil' => $m['real']['hasil'] ?? '',
                'lembur' => $m['real'] ? (float)$m['real']['lembur'] : 0,
                'pot_split' => $pot,
                'pot_split_total' => array_sum($pot),
                'pot_excel' => $excel_pot,
                'upah_dibayar_excel' => $m['real'] ? (float)$m['real']['upah_dibayar'] : 0,
            ];
        }
        // shift_id fix (id_mesin typo guard)
        foreach ($rows as &$rr) { $rr['shift_id'] = isset($rr['shift']['id_jadwal']) ? (int)$rr['shift']['id_jadwal'] : 0; }
        unset($rr);
        $_SESSION['rx_import'] = ['date' => $date, 'title' => $title, 'rows' => $rows, 'skipped' => $skipped_all];
        $dateShow = $date ?: '(tanggal tidak terbaca dari judul — isi manual di bawah)';
        echo '<div class="mb-4 p-4 bg-indigo-50 border border-indigo-100 rounded-lg text-sm">';
        echo '<strong>File terbaca:</strong> ' . htmlspecialchars($_FILES['fexcel']['name']) . ' | Judul: ' . htmlspecialchars($title) . '<br>';
        echo '<strong>Tanggal terdeteksi:</strong> ' . htmlspecialchars($dateShow);
        echo '</div>';
        echo '<form method="POST">';
        echo '<input type="hidden" name="rx_step" value="commit">';
        echo '<div class="mb-4 max-w-xs"><label class="block text-sm font-semibold text-gray-700 mb-1.5">Tanggal RKK/Realisasi</label>';
        echo '<input type="date" name="rx_date" value="' . htmlspecialchars($date ?: date('Y-m-d')) . '" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm"></div>';
        echo '<div class="overflow-x-auto border border-gray-200 rounded-xl mb-4"><table class="w-full text-sm text-left">';
        echo '<thead class="bg-gray-50 border-b border-gray-200"><tr><th class="py-2 px-3 w-10"><input type="checkbox" id="rx_all" checked></th><th class="py-2 px-3">Nama (Excel)</th><th class="py-2 px-3">Status</th><th class="py-2 px-3">Shift (tebakan durasi)</th><th class="py-2 px-3">Aktual</th><th class="py-2 px-3">Potongan sistem</th><th class="py-2 px-3">Potongan Excel</th><th class="py-2 px-3">Selisih</th></tr></thead><tbody class="divide-y divide-gray-100">';
        echo '<script>document.getElementById("rx_all").addEventListener("change",function(){document.querySelectorAll(".rx_sel").forEach(function(c){c.checked=document.getElementById("rx_all").checked;});});</script>';
        foreach ($rows as $idx => $r) {
            $checked = $r['dirty'] ? '' : 'checked';
            $st = $r['karyawan_id'] > 0
                ? '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-800">Cocok</span>'
                : '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800">Baru dibuat</span>';
            if ($r['dirty']) $st .= ' <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800">Baris kotor</span>';
            if (!$r['in_plan']) $st .= ' <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-600">Tanpa rencana</span>';
            $sh = $r['shift'] ? htmlspecialchars($r['shift']['keterangan'] . ' (' . $r['shift']['jam_masuk'] . '-' . $r['shift']['jam_keluar'] . ')') : '<span class="text-gray-400">-</span>';
            $ak = ($r['jam_masuk'] !== '' || $r['jam_pulang'] !== '') ? htmlspecialchars(trim($r['jam_masuk'] . ' / ' . $r['jam_pulang'], ' /')) : '<span class="text-gray-400">-</span>';
            $ps = $r['pot_split_total'] > 0 ? number_format($r['pot_split_total'], 0, ',', '.') . ' <span class="text-gray-400 text-xs">(telat ' . number_format($r['pot_split']['telat'], 0, ',', '.') . ')</span>' : '0';
            $pe = number_format($r['pot_excel'], 0, ',', '.');
            $sel = $r['pot_split_total'] - $r['pot_excel'];
            $selh = $sel == 0 ? '<span class="text-green-600">0</span>' : '<span class="text-red-600 font-bold">' . number_format($sel, 0, ',', '.') . '</span>';
            echo '<tr class="hover:bg-gray-50"><td class="py-2 px-3"><input type="checkbox" class="rx_sel" name="rx_sel[]" value="' . $idx . '" ' . $checked . '></td><td class="py-2 px-3 font-semibold">' . htmlspecialchars($r['nama']) . ($r['karyawan_id'] > 0 ? '<div class="text-xs font-normal text-gray-400">' . htmlspecialchars($r['karyawan_nama']) . '</div>' : '') . '</td><td class="py-2 px-3">' . $st . '</td><td class="py-2 px-3 text-xs">' . $sh . '</td><td class="py-2 px-3 text-xs">' . $ak . '</td><td class="py-2 px-3 text-xs text-right">' . $ps . '</td><td class="py-2 px-3 text-xs text-right">' . $pe . '</td><td class="py-2 px-3 text-xs text-right">' . $selh . '</td></tr>';
        }
        echo '</tbody></table></div>';
        if (!empty($skipped_all)) {
            echo '<div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-500"><strong>Dilewati otomatis (' . count($skipped_all) . ' baris non-karyawan):</strong> ';
            $tmp = [];
            foreach ($skipped_all as $s) $tmp[] = htmlspecialchars($s['nama']) . ' (baris ' . (int)$s['row'] . ')';
            echo implode(', ', $tmp) . '</div>';
        }
        echo '<div class="flex gap-2"><button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-check mr-2"></i>Simpan ke RKK + Realisasi</button>';
        echo '<a href="?page=rkk&aksi=import" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2.5 px-6 rounded-lg text-sm">Ulangi</a></div>';
        echo '</form>';
    }
} else {
    // ===== UPLOAD =====
    ?>
    <form method="POST" enctype="multipart/form-data" class="max-w-xl">
    <input type="hidden" name="rx_step" value="preview">
    <div class="mb-4">
    <label class="block text-sm font-semibold text-gray-700 mb-1.5">File Excel (.xlsx) — sheet PERENCANAAN + REALISASI</label>
    <input type="file" name="fexcel" accept=".xlsx" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm bg-white">
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-upload mr-2"></i>Upload &amp; Preview</button>
    </form>
    <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 leading-relaxed">
    <strong>Cara kerja:</strong> tanggal diambil dari judul baris 1 (cth: "...02 OKTOBER 2026", bisa dikoreksi di preview). Shift tiap orang ditebak dari durasi JAM KERJA ke shift terdekat di master. Potongan di-split otomatis ikut aturan denda + toleransi (kolom POTONGAN Excel hanya pembanding). Nama yang tidak cocok otomatis dibuatkan karyawan baru (No. Absen = MAX+1). Baris kotor (nama tidak di kolomnya) tetap diproses tapi ditandai kuning — cek manual.
    </div>
    <?php
}
?>
</div>
</div>
</div>
