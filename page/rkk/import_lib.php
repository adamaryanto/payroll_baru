<?php
// Library bersama import Excel RKK (dipakai import_excel.php & import_kelola.php).
// Isi: baca .xlsx (ZipArchive), baca .xls HTML hasil export, normalisasi,
// cocok grup/karyawan/shift, auto-generate grup & karyawan.

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

if (!function_exists('rk_read_html_table')) {
// Baca file .xls hasil export aplikasi (HTML table) -> [sheet => [row => [col => val]]]
function rk_read_html_table($file) {
    $raw = @file_get_contents($file);
    if ($raw === false || stripos($raw, '<table') === false) return false;
    $d = new DOMDocument();
    @$d->loadHTML('<?xml encoding="UTF-8">' . $raw);
    $tables = $d->getElementsByTagName('table');
    if ($tables->length === 0) return false;
    $t = $tables->item(0);
    $rows = []; $rn = 0;
    foreach ($t->getElementsByTagName('tr') as $tr) {
        $rn++;
        $cn = 0;
        foreach ($tr->childNodes as $td) {
            if (!($td instanceof DOMElement)) continue;
            if (!in_array(strtolower($td->tagName), ['td', 'th'])) continue;
            $cn++;
            $v = trim(preg_replace('/\s+/', ' ', $td->textContent));
            $rows[$rn][$cn] = $v;
        }
    }
    return $rows ? ['HTML' => $rows] : false;
}
}

if (!function_exists('rk_read_any')) {
// Baca .xlsx asli, fallback ke HTML-table .xls
function rk_read_any($file) {
    if (function_exists('rx_read_xlsx')) {
        $s = rx_read_xlsx($file);
        if (is_array($s) && !empty($s)) {
            $nonEmpty = array_filter($s, function($r) { return !empty($r); });
            if (!empty($nonEmpty)) return $s;
        }
    }
    return rk_read_html_table($file);
}
}

if (!function_exists('rk_extract_kelola')) {
// Ambil baris karyawan + section (grup) dari satu sheet mentah.
// Kolom: A No | B Nama | C Paraf | D OS | E Gol | F Posisi | G JamKerja | H J.Masuk
//        I Istirahat | J J.Pulang | K Hasil | L Upah | M Potongan | N Lembur | O Dibayar
// Baris yang namanya nyasar di luar kolom B dicek dulu ke database:
// kalau orangnya sudah ada di DB -> langsung dibuang (catat), bukan diimpor.
function rk_extract_kelola($rows, $koneksi = null) {
    $out = []; $skipped = []; $section = '';
    foreach ($rows as $rn => $cols) {
        $get = function($n) use ($cols) { return trim((string)($cols[$n] ?? '')); };
        $a = $get(1); $b = $get(2);
        if (stripos($b, 'NAMA') !== false) continue; // header kolom
        if ($rn <= 3) continue; // judul/aturan
        $dirty = false;
        if ($b === '') {
            // Blok footer (rekap/boneless) bukan data karyawan — setop parsing di sini.
            // Marker selalu di kolom C (layout menjorok); bar section selalu di kolom A.
            $c3 = strtoupper($get(3));
            $stop = ['BAYARAN TIM BONLESS', 'DENGAN MESIN BONLES', 'BIAYA PABRIK', 'TOTAL'];
            if (in_array($c3, $stop, true) || strpos($c3, 'REKAP BIAYA') !== false || strpos($c3, 'BIAYA ') === 0) break;
            // Baris section (ADMIN / ...) atau baris kotor (nama nyasar di kolom lain)?
            // Kumpulkan kandidat teks (abaikan kata sampah & angka), lalu cek ke DB dulu.
            $cands = [];
            for ($c = 3; $c <= 30; $c++) {
                $v = $get($c);
                if ($v === '' || $v === 'MASUK' || rx_is_junk($v)) continue;
                if (!preg_match('/[A-Za-z]{3,}/', $v) || strlen($v) < 3) continue;
                if (is_numeric(str_replace(['.', ',', ' '], '', $v))) continue;
                $cands[] = $v;
            }
            $hitDb = '';
            if ($koneksi && !empty($cands)) {
                foreach ($cands as $cd) {
                    if (rx_match_karyawan($koneksi, $cd)) { $hitDb = $cd; break; }
                }
            }
            if ($hitDb !== '') {
                $skipped[] = ['row' => $rn, 'nama' => $hitDb, 'alasan' => 'sudah ada di database'];
                continue;
            }
            $found = $cands[0] ?? '';
            if ($found === '') {
                if ($a !== '' && !is_numeric($a) && !rx_is_junk($a)) $section = $a;
                continue;
            }
            $b = $found; $dirty = true;
        } elseif (rx_is_junk($b)) { $skipped[] = ['row' => $rn, 'nama' => $b, 'alasan' => 'baris non-karyawan']; continue; }
        $out[] = [
            'row' => $rn, 'section' => $section, 'nama' => $b, 'dirty' => $dirty,
            'osdhk' => $get(4), 'golongan' => $get(5), 'posisi' => $get(6),
            'g_raw' => $get(7), 'h_raw' => $get(8),
            'jam_pulang' => rx_frac_to_time($get(10)),
            'hasil' => $get(11),
            'upah_excel' => rx_money($get(12)),
            'pot_excel' => rx_money($get(13)),
            'lembur_excel' => rx_money($get(14)),
            'dibayar_excel' => rx_money($get(15)),
        ];
    }
    return ['rows' => $out, 'skipped' => $skipped];
}
}

if (!function_exists('rk_match_shift')) {
// Cocokkan string jam (fraksi/"08:00") ke tb_jadwal.jam_masuk; fallback jadwal pertama
function rk_match_shift($koneksi, $g_raw) {
    $map = [];
    $q = $koneksi->query("SELECT * FROM tb_jadwal ORDER BY id_jadwal ASC");
    $first = null;
    while ($q && ($r = $q->fetch_assoc())) {
        if (!$first) $first = $r;
        $map[substr(trim($r['jam_masuk'] ?? ''), 0, 5)] = $r;
    }
    if (!$map) return null;
    $t = rx_frac_to_time($g_raw);
    if ($t !== '') {
        $hm = substr($t, 0, 5);
        if (isset($map[$hm])) return $map[$hm];
    }
    return $first;
}
}

if (!function_exists('rk_find_grup')) {
function rk_find_grup($koneksi, $name) {
    $name = trim((string)$name);
    if ($name === '') return null;
    $esc = $koneksi->real_escape_string($name);
    $q = $koneksi->query("SELECT id_grup, nama_grup FROM ms_grup_borongan WHERE UPPER(TRIM(nama_grup))=UPPER('$esc') LIMIT 1");
    if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    return null;
}
}

if (!function_exists('rk_ensure_grup')) {
// Cari grup, buatkan bila belum ada. Return id_grup atau null. $c_new bertambah bila baru dibuat.
function rk_ensure_grup($koneksi, $section, &$c_new) {
    $section = trim((string)$section);
    if ($section === '') return null;
    $g = rk_find_grup($koneksi, $section);
    if ($g) return (int)$g['id_grup'];
    $nm = $koneksi->real_escape_string($section);
    $now = date('Y-m-d H:i:s');
    if ($koneksi->query("INSERT INTO ms_grup_borongan (nama_grup, keterangan, tgl_updt) VALUES ('$nm', 'Auto import Excel', '$now')")) {
        $c_new++;
        return (int)$koneksi->insert_id;
    }
    return null;
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

if (!function_exists('rx_make_karyawan')) {
// Buatkan karyawan baru dari data baris Excel. Return id_karyawan atau 0.
function rx_make_karyawan($koneksi, $nama, $osdhk, $golongan, $posisi, $shift_id, $upah, $tgl_aktif, $def_dept, $def_sub) {
    $nm_esc = $koneksi->real_escape_string(trim($nama));
    if ($nm_esc === '') return 0;
    $no_absen = rx_next_no_absen($koneksi);
    $os = rx_lookup_id($koneksi, 'ms_os_dhk', 'OS_DHK', $osdhk ?? '');
    $gol = rx_lookup_id($koneksi, 'ms_golongan', 'golongan', $golongan ?? '');
    $sub = (int)$def_sub;
    if (trim($posisi ?? '') !== '') {
        $sid = rx_lookup_id($koneksi, 'ms_sub_department', 'nama_sub_department', $posisi);
        if ($sid > 0) $sub = $sid;
    }
    $jd = (int)$shift_id; $up = (int)$upah;
    $ok = $koneksi->query("INSERT INTO ms_karyawan (id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah, target_mingguan, target_bulanan) VALUES ('$def_dept','0','$no_absen','$nm_esc','',NULL,'',NULL,NULL,'','','','','Aktif','$tgl_aktif','$sub','$os','$gol','$jd','$up','20.00','40.00')");
    return $ok ? (int)$koneksi->insert_id : 0;
}
}
