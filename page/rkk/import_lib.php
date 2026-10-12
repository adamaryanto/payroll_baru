<?php
// Library bersama import Excel RKK (dipakai import_excel.php & import_kelola.php).
// Isi: baca .xlsx (ZipArchive), baca .xls HTML hasil export, normalisasi,
// cocok grup/karyawan/shift, auto-generate grup & karyawan.

if (!function_exists('rx_ensure_schema')) {
// Self-heal schema sekali per request: tambahkan kolom/tabel yang kurang
// (tanpa mengubah data). Dipanggil di halaman import sebelum dipakai.
function rx_ensure_schema($koneksi) {
    static $done = false;
    if ($done) return;
    $done = true;
    $has = function($table, $col) use ($koneksi) {
        $q = rx_q($koneksi, "SHOW COLUMNS FROM $table LIKE '$col'");
        return ($q && $q->num_rows > 0);
    };
    if (!$has('ms_sub_department', 'id_departmen')) {
        rx_q($koneksi, "ALTER TABLE ms_sub_department ADD COLUMN id_departmen INT NOT NULL DEFAULT 0");
        // Isi tautan dept yang masih kosong dari pemakaian terbanyak
        // (detail RKK + master karyawan) per posisi
        $cnt = [];
        $q1 = rx_q($koneksi, "SELECT id_sub_department s, id_departmen d, COUNT(*) c FROM tb_rkk_detail WHERE id_sub_department>0 AND id_departmen>0 GROUP BY id_sub_department, id_departmen");
        while ($q1 && ($r = $q1->fetch_assoc())) {
            $s = (int)$r['s']; $d = (int)$r['d'];
            $cnt[$s][$d] = ($cnt[$s][$d] ?? 0) + (int)$r['c'];
        }
        $q2 = rx_q($koneksi, "SELECT id_sub_department s, id_departmen d, COUNT(*) c FROM ms_karyawan WHERE id_sub_department>0 AND id_departmen>0 GROUP BY id_sub_department, id_departmen");
        while ($q2 && ($r = $q2->fetch_assoc())) {
            $s = (int)$r['s']; $d = (int)$r['d'];
            $cnt[$s][$d] = ($cnt[$s][$d] ?? 0) + (int)$r['c'];
        }
        foreach ($cnt as $s => $depts) {
            arsort($depts);
            $d = (int)key($depts);
            if ($d > 0) rx_q($koneksi, "UPDATE ms_sub_department SET id_departmen='$d' WHERE id_sub_department='$s' AND id_departmen=0");
        }
    }
    if (!$has('ms_sub_department', 'id_perusahaan')) {
        rx_q($koneksi, "ALTER TABLE ms_sub_department ADD COLUMN id_perusahaan INT NOT NULL DEFAULT 0");
    }
    if (!$has('ms_departmen', 'id_perusahaan')) {
        rx_q($koneksi, "ALTER TABLE ms_departmen ADD COLUMN id_perusahaan INT NOT NULL DEFAULT 0");
    }
    rx_q($koneksi, "CREATE TABLE IF NOT EXISTS tb_biayamobil (id_biayamobil INT AUTO_INCREMENT PRIMARY KEY, biaya_mobil INT NOT NULL)");
}
}

if (!function_exists('rx_q')) {
// Query aman: gagal (kolom/tabel tak ada, mysqli strict-mode) -> false, bukan fatal.
// Semua helper di file ini memakainya agar tahan beda schema/versi PHP.
function rx_q($koneksi, $sql) {
    try { return $koneksi->query($sql); }
    catch (Throwable $e) { return false; }
}
}

if (!function_exists('rx_sub_dept')) {
// Ambil id_departmen milik satu sub (0 bila tabel tak punya kolomnya).
function rx_sub_dept($koneksi, $sid) {
    $sid = (int)$sid;
    if ($sid <= 0) return 0;
    $c = rx_q($koneksi, "SHOW COLUMNS FROM ms_sub_department LIKE 'id_departmen'");
    if (!($c && $c->num_rows > 0)) return 0;
    $q = rx_q($koneksi, "SELECT id_departmen FROM ms_sub_department WHERE id_sub_department='$sid' LIMIT 1");
    if ($q && ($r = $q->fetch_assoc())) return (int)($r['id_departmen'] ?? 0);
    return 0;
}
}

if (!function_exists('rx_col2num')) {
function rx_col2num($c) { $n = 0; for ($i = 0; $i < strlen($c); $i++) $n = $n * 26 + ord($c[$i]) - 64; return $n; }
}

if (!function_exists('rx_read_xlsx')) {
// Baca xlsx minimal (ZipArchive + SimpleXML). Return [sheetName => [rowNum => [colNum => val]]].
function rx_read_xlsx($file) {
    if (!class_exists('ZipArchive')) return false; // ext php_zip mati -> fallback / pesan ramah
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

if (!function_exists('rx_diag_file')) {
// Diagnostik singkat file upload untuk pesan error (nama, ukuran, jenis isi).
function rx_diag_file($file, $fname) {
    $size = (is_file($file)) ? filesize($file) : -1;
    $head = '';
    if (is_file($file) && $size > 0) {
        $fh = @fopen($file, 'rb');
        if ($fh) { $head = bin2hex((string)@fread($fh, 4)); @fclose($fh); }
    }
    if (strpos($head, '504b') === 0) $kind = 'zip/xlsx';
    elseif (stripos($head, '3c') === 0) $kind = 'html';
    elseif (strpos($head, 'd0cf') === 0) $kind = 'xls-jadul';
    else $kind = 'asing/rusak';
    return trim($fname) . " ($size byte, $kind)";
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
            $stop = ['BAYARAN TIM BONLESS', 'BAYARAN TIM BONLESS', 'DENGAN MESIN BONLES', 'DENGAN MESIN BONLES', 'BIAYA PABRIK', 'TOTAL'];
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

if (!function_exists('rk_find_shift')) {
// Cari shift persis jam masuk (tanpa fallback). Return baris tb_jadwal atau null.
function rk_find_shift($koneksi, $g_raw) {
    $t = rx_frac_to_time($g_raw);
    if ($t === '' || $t === '00:00:00') return null;
    $esc = $koneksi->real_escape_string(substr($t, 0, 5));
    $q = rx_q($koneksi, "SELECT * FROM tb_jadwal WHERE SUBSTR(jam_masuk,1,5)='$esc' LIMIT 1");
    if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    return null;
}
}

if (!function_exists('rk_ensure_shift')) {
// Cari shift, buatkan bila belum ada (pola 12 jam: keluar=masuk+12,
// istirahat keluar=masuk+5, istirahat masuk=masuk+6, ket "Shift H - H").
// Return baris tb_jadwal atau null. $c_new bertambah bila baru dibuat.
function rk_ensure_shift($koneksi, $g_raw, &$c_new = null) {
    $f = rk_find_shift($koneksi, $g_raw);
    if ($f) return $f;
    $t = rx_frac_to_time($g_raw);
    if ($t === '' || $t === '00:00:00') return null;
    $in = strtotime($t);
    $fm = function($ts) { return date('H:i', $ts); };
    $hlab = function($ts) { $h = (int)date('H', $ts); $m = date('i', $ts); if ($h === 0 && $m === '00') return '24'; return $m === '00' ? (string)$h : ($h . ':' . $m); };
    $ket = 'Shift ' . $hlab($in) . ' - ' . $hlab($in + 12 * 3600);
    $esc = $koneksi->real_escape_string($ket);
    $jm = $fm($in); $jk = $fm($in + 12 * 3600);
    $ik = $fm($in + 5 * 3600); $im = $fm($in + 6 * 3600);
    if (!rx_q($koneksi, "INSERT INTO tb_jadwal (keterangan, jam_masuk, jam_keluar, istirahat_masuk, istirahat_keluar) VALUES ('$esc', '$jm', '$jk', '$im', '$ik')")) return null;
    $nid = (int)$koneksi->insert_id;
    if ($c_new !== null) $c_new++;
    $q = rx_q($koneksi, "SELECT * FROM tb_jadwal WHERE id_jadwal='$nid' LIMIT 1");
    return ($q && ($rw = $q->fetch_assoc())) ? $rw : null;
}
}

if (!function_exists('rx_commit_shift')) {
// Tentukan id_jadwal final satu baris import: opsi "(baru)" -> generate;
// numerik -> pakai; selain itu fallback session.
function rx_commit_shift($koneksi, $r, &$c_new = null) {
    $raw = trim((string)($r['sh_raw'] ?? ''));
    if ($raw !== '' && !ctype_digit($raw) && strpos($raw, 'baru:') === 0) {
        $ns = rk_ensure_shift($koneksi, substr($raw, 5), $c_new);
        if ($ns) return (int)$ns['id_jadwal'];
    }
    if ($raw !== '' && ctype_digit($raw) && (int)$raw > 0) return (int)$raw;
    return (int)($r['shift_id'] ?? 0);
}
}

if (!function_exists('rk_extract_boneless')) {
// Ambil rekap boneless dari footer sheet mentah (di bawah baris karyawan).
// Kolom acuan: 7 nama | 8 qty | 9 harga (sama di layout contoh & export aplikasi).
// Return ['mobil' => int, 'items' => [['nama','qty','harga','jenis']], 'total_excel' => float].
function rk_extract_boneless($rows) {
    $out = ['mobil' => 0, 'items' => [], 'total_excel' => 0];
    if (!is_array($rows) || empty($rows)) return $out;
    $in_items = false;
    foreach ($rows as $rn => $cols) {
        $get = function($n) use ($cols) { return trim((string)($cols[$n] ?? '')); };
        $txt = '';
        for ($c = 1; $c <= 12; $c++) { $v = $get($c); if ($v !== '') $txt .= ' ' . $v; }
        $up = strtoupper($txt);
        if (preg_match('/BIAYA\s+(\d+)\s+MOBIL/i', $txt, $m)) $out['mobil'] = (int)$m[1];
        // Ejaan di file bervariasi: BONLESS (footer) / BONELESS (nama sheet)
        if (strpos($up, 'BAYARAN TIM BONLE') !== false || strpos($up, 'BAYARAN TIM BONEL') !== false) { $in_items = true; continue; }
        if (strpos($up, 'DENGAN MESIN BONLE') !== false || strpos($up, 'DENGAN MESIN BONEL') !== false) break;
        if (!$in_items) continue;
        if (preg_match('/\bTOTAL\b/', $up) && !preg_match('/[A-Z]{4,}/', str_replace('TOTAL', '', $up))) {
            for ($c = 1; $c <= 12; $c++) { $v = $get($c); if (is_numeric(str_replace([',', ' '], '', $v))) { $out['total_excel'] = (float)str_replace(',', '', $v); break; } }
            continue;
        }
        // Baris item: nama di col 7 (atau teks valid pertama col 3..10), qty col 8, harga col 9
        $nama = '';
        $c7 = $get(7);
        if ($c7 !== '' && preg_match('/[A-Za-z]{3,}/', $c7) && !rx_is_junk($c7) && strtoupper($c7) !== 'PENGURANGAN') $nama = $c7;
        if ($nama === '') {
            for ($c = 3; $c <= 10; $c++) {
                $v = $get($c);
                if ($v === '' || strtoupper($v) === 'PENGURANGAN' || !preg_match('/[A-Za-z]{3,}/', $v) || rx_is_junk($v)) continue;
                if (is_numeric(str_replace(['.', ',', ' '], '', $v))) continue;
                $nama = $v; break;
            }
        }
        if ($nama === '') continue;
        $qv = $get(8);
        $qty = (is_numeric(str_replace([',', ' '], '', $qv))) ? (float)str_replace(',', '', $qv) : 0;
        if ($qty <= 0) continue;
        $hv = $get(9);
        $harga = (is_numeric(str_replace([',', ' '], '', $hv))) ? (float)str_replace(',', '', $hv) : 0;
        $jenis = 'plus';
        for ($c = 3; $c <= 8; $c++) if (strtoupper($get($c)) === 'PENGURANGAN') { $jenis = 'minus'; break; }
        $out['items'][] = ['nama' => $nama, 'qty' => $qty, 'harga' => $harga, 'jenis' => $jenis];
    }
    return $out;
}
}

if (!function_exists('rk_extract_satuan')) {
// Ambil karyawan satuan dari sheet BONELESS BARU (No | Nama | Hasil | Upah).
// Return [['nama','hasil','upah','harga']].
function rk_extract_satuan($rows) {
    $out = [];
    if (!is_array($rows) || empty($rows)) return $out;
    foreach ($rows as $rn => $cols) {
        $get = function($n) use ($cols) { return trim((string)($cols[$n] ?? '')); };
        $nm = $get(2);
        if ($nm === '' || stripos($nm, 'NAMA') !== false) continue;
        if (!preg_match('/[A-Za-z]{3,}/', $nm) || rx_is_junk($nm)) continue;
        $h = is_numeric(str_replace([',', ' '], '', $get(3))) ? (float)str_replace(',', '', $get(3)) : 0;
        $u = is_numeric(str_replace([',', ' '], '', $get(4))) ? (float)str_replace(',', '', $get(4)) : 0;
        if ($h <= 0 && $u <= 0) continue;
        $out[] = ['nama' => $nm, 'hasil' => $h, 'upah' => (int)round($u), 'harga' => $h > 0 ? (int)round($u / $h) : 0];
    }
    return $out;
}
}

if (!function_exists('rk_ensure_satuan_table')) {
// Pastikan tabel + kolom tb_boneless_satuan ada (salinan pola tambah.php).
function rk_ensure_satuan_table($koneksi) {
    rx_q($koneksi, "CREATE TABLE IF NOT EXISTS tb_boneless_satuan (
      id_satuan INT AUTO_INCREMENT PRIMARY KEY,
      id_boneless INT NOT NULL,
      nama VARCHAR(255) NOT NULL,
      hasil DECIMAL(12,2) NOT NULL DEFAULT 0,
      upah INT NOT NULL DEFAULT 0,
      KEY idx_boneless (id_boneless)
    ) ENGINE=InnoDB");
    $a = rx_q($koneksi, "SHOW COLUMNS FROM tb_boneless_satuan LIKE 'satuan'");
    if ($a && $a->num_rows == 0) rx_q($koneksi, "ALTER TABLE tb_boneless_satuan ADD COLUMN satuan VARCHAR(10) NOT NULL DEFAULT 'KG'");
    $b = rx_q($koneksi, "SHOW COLUMNS FROM tb_boneless_satuan LIKE 'harga'");
    if ($b && $b->num_rows == 0) rx_q($koneksi, "ALTER TABLE tb_boneless_satuan ADD COLUMN harga INT NOT NULL DEFAULT 0");
}
}

if (!function_exists('rx_save_boneless')) {
// Simpan hasil deteksi boneless ke halaman Boneless (BUKAN ke kelola/RKK detail).
// Return pesan ringkas ('' bila dilewati: kosong / tgl sudah ada / tanggal invalid).
function rx_save_boneless($koneksi, $tgl, $id_rkk, $bon, $title = '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$tgl)) return '';
    $mobil = (int)($bon['mobil'] ?? 0);
    $items = $bon['items'] ?? [];
    $satuan = $bon['satuan'] ?? [];
    if ($mobil <= 0 && empty($items) && empty($satuan)) return '';
    $cek = rx_q($koneksi, "SELECT id_boneless FROM tb_boneless WHERE tgl='$tgl' LIMIT 1");
    if ($cek && $cek->num_rows > 0) return ' (boneless tgl tsb sudah ada, dilewati)';
    $id_bm = 'NULL';
    $qb = rx_q($koneksi, "SELECT id_biayamobil FROM tb_biayamobil LIMIT 1");
    if ($qb && ($rb = $qb->fetch_assoc()) && (int)($rb['id_biayamobil'] ?? 0) > 0) $id_bm = "'" . (int)$rb['id_biayamobil'] . "'";
    $id_rkk = (int)$id_rkk;
    $ket = $koneksi->real_escape_string(trim('Auto import Excel ' . $title));
    if (!rx_q($koneksi, "INSERT INTO tb_boneless (id_rkk, id_biayamobil, tgl, jumlah_mobil, keterangan, tgl_updt) VALUES ('$id_rkk', $id_bm, '$tgl', '$mobil', '$ket', NOW())")) return ' (boneless gagal tersimpan)';
    $id_bon = (int)$koneksi->insert_id;
    $c_it = 0;
    foreach ($items as $it) {
        $nm = trim($it['nama'] ?? '');
        $qty = (float)($it['qty'] ?? 0);
        if ($nm === '' || $qty <= 0) continue;
        $hrg = (float)($it['harga'] ?? 0);
        $ttl = $qty * $hrg;
        $jn = (($it['jenis'] ?? 'plus') === 'minus') ? 'minus' : 'plus';
        $nm_esc = $koneksi->real_escape_string($nm);
        if (rx_q($koneksi, "INSERT INTO tb_boneless_detail (id_boneless, nama_item, qty, harga, total, jenis) VALUES ('$id_bon', '$nm_esc', '$qty', '$hrg', '$ttl', '$jn')")) $c_it++;
    }
    $c_sat = 0;
    if (!empty($satuan)) {
        rk_ensure_satuan_table($koneksi);
        foreach ($satuan as $st) {
            $nm = trim($st['nama'] ?? '');
            if ($nm === '') continue;
            $q = (float)($st['hasil'] ?? 0);
            $u = (int)($st['upah'] ?? 0);
            if ($q <= 0 && $u <= 0) continue;
            $h = (int)($st['harga'] ?? 0);
            $nm_esc = $koneksi->real_escape_string($nm);
            if (rx_q($koneksi, "INSERT INTO tb_boneless_satuan (id_boneless, nama, hasil, upah, satuan, harga) VALUES ('$id_bon', '$nm_esc', '$q', '$u', 'KG', '$h')")) $c_sat++;
        }
    }
    return " + boneless ($mobil mobil, $c_it item" . ($c_sat > 0 ? ", $c_sat satuan" : "") . ")";
}
}

if (!function_exists('rk_match_shift')) {
// Cocokkan string jam (fraksi/"08:00") ke tb_jadwal.jam_masuk; fallback jadwal pertama
function rk_match_shift($koneksi, $g_raw) {
    $map = [];
    $q = rx_q($koneksi, "SELECT * FROM tb_jadwal ORDER BY id_jadwal ASC");
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
    $q = rx_q($koneksi, "SELECT id_grup, nama_grup FROM ms_grup_borongan WHERE UPPER(TRIM(nama_grup))=UPPER('$esc') LIMIT 1");
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
    if (rx_q($koneksi, "INSERT INTO ms_grup_borongan (nama_grup, keterangan, tgl_updt) VALUES ('$nm', 'Auto import Excel', '$now')")) {
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
    $q = rx_q($koneksi, "SELECT * FROM tb_jadwal");
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
    $q = rx_q($koneksi, "SELECT id_karyawan, no_absen, nama_karyawan, id_jadwal, upah, id_os_dhk, id_golongan FROM ms_karyawan WHERE TRIM(nama_karyawan)='$esc' OR LOWER(TRIM(nama_karyawan))=LOWER('$esc') LIMIT 1");
    if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    return null;
}
}

if (!function_exists('rx_lookup_id')) {
function rx_lookup_id($koneksi, $table, $col, $val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $pk = null;
    $q = rx_q($koneksi, "SHOW COLUMNS FROM $table");
    if ($q) { $first = $q->fetch_assoc(); $pk = $first['Field']; }
    if (!$pk) return 0;
    $esc = $koneksi->real_escape_string($val);
    $qr = rx_q($koneksi, "SELECT $pk FROM $table WHERE UPPER(TRIM($col))=UPPER('$esc') LIMIT 1");
    if ($qr && $qr->num_rows > 0) { $rw = $qr->fetch_assoc(); return (int)$rw[$pk]; }
    return 0;
}
}

if (!function_exists('rx_next_no_absen')) {
function rx_next_no_absen($koneksi) {
    $q = rx_q($koneksi, "SELECT MAX(no_absen) AS mx FROM ms_karyawan");
    $mx = ($q && ($r = $q->fetch_assoc())) ? (int)$r['mx'] : 0;
    $cand = max($mx + 1, 1);
    do {
        $cek = rx_q($koneksi, "SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$cand' LIMIT 1");
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

if (!function_exists('rx_ensure_dept')) {
// Cari department, buatkan bila belum ada (auto-generate). Return id_departmen atau 0.
function rx_ensure_dept($koneksi, $name) {
    $name = trim((string)$name);
    if ($name === '') return 0;
    $id = rx_lookup_id($koneksi, 'ms_departmen', 'nama_departmen', $name);
    if ($id > 0) return $id;
    $esc = $koneksi->real_escape_string($name);
    $cols = [];
    $q = rx_q($koneksi, "SHOW COLUMNS FROM ms_departmen");
    while ($q && ($c = $q->fetch_assoc())) $cols[] = $c['Field'];
    if (in_array('id_perusahaan', $cols, true)) {
        if (rx_q($koneksi, "INSERT INTO ms_departmen (nama_departmen, id_perusahaan) VALUES ('$esc', '1')")) return (int)$koneksi->insert_id;
    } else {
        if (rx_q($koneksi, "INSERT INTO ms_departmen (nama_departmen) VALUES ('$esc')")) return (int)$koneksi->insert_id;
    }
    return 0;
}
}

if (!function_exists('rx_ensure_sub')) {
// Cari posisi (sub department), buatkan bila belum ada (auto-generate, nyambung ke dept). Return id atau 0.
function rx_ensure_sub($koneksi, $name, $dept_id = 0) {
    $name = trim((string)$name);
    if ($name === '') return 0;
    $id = rx_lookup_id($koneksi, 'ms_sub_department', 'nama_sub_department', $name);
    if ($id > 0) return $id;
    $esc = $koneksi->real_escape_string($name);
    $dep = (int)$dept_id;
    $cols = [];
    $q = rx_q($koneksi, "SHOW COLUMNS FROM ms_sub_department");
    while ($q && ($c = $q->fetch_assoc())) $cols[] = $c['Field'];
    $f = ['nama_sub_department']; $v = ["'$esc'"];
    if (in_array('id_departmen', $cols, true)) { $f[] = 'id_departmen'; $v[] = "'$dep'"; }
    if (in_array('id_perusahaan', $cols, true)) { $f[] = 'id_perusahaan'; $v[] = "'1'"; }
    if (rx_q($koneksi, "INSERT INTO ms_sub_department (" . implode(',', $f) . ") VALUES (" . implode(',', $v) . ")")) return (int)$koneksi->insert_id;
    return 0;
}
}

if (!function_exists('rx_commit_sub')) {
// Tentukan id_sub_department final untuk satu baris import.
// $r = baris session (sub_id = pilihan preview, ps_raw = mentah POST, posisi = teks Excel).
// Aturan: opsi teks "(baru)" -> generate; pilihan user yang diubah -> menang;
// sisanya ikut teks Posisi Excel (cocok -> pakai, baru -> generate).
// Tak ada sumber valid -> 0 (penulis wajib simpan NULL, jangan ngarang default).
function rx_commit_sub($koneksi, $r, $dep) {
    $ps_raw = trim((string)($r['ps_raw'] ?? ''));
    $pos_txt = trim((string)($r['posisi'] ?? ''));
    $pos_ok = ($pos_txt !== '' && !rx_is_junk($pos_txt) && preg_match('/[A-Za-z]{3,}/', $pos_txt));
    $excel_sub = $pos_ok ? rx_lookup_id($koneksi, 'ms_sub_department', 'nama_sub_department', $pos_txt) : 0;
    if ($ps_raw !== '' && !ctype_digit($ps_raw)) {
        // Opsi teks "(baru)" dari preview -> generate otomatis (nyambung dept terpilih)
        $made = (!rx_is_junk($ps_raw) && preg_match('/[A-Za-z]{3,}/', $ps_raw)) ? rx_ensure_sub($koneksi, $ps_raw, $dep) : 0;
        return $made > 0 ? $made : 0;
    }
    $sub_post = ($ps_raw !== '' ? (int)$ps_raw : 0);
    $sub_ses = (int)($r['sub_id'] ?? 0);
    $sub = $sub_post > 0 ? $sub_post : $sub_ses;
    if ($sub_ses <= 0 && $pos_ok) {
        // Session lama tanpa pilihan valid -> ikut teks Excel
        if ($excel_sub > 0) return $excel_sub;
        $made = rx_ensure_sub($koneksi, $pos_txt, $dep);
        if ($made > 0) return $made;
    } elseif ($sub_post === $sub_ses && $pos_ok) {
        // Tak diubah user -> ikut teks Excel
        if ($excel_sub > 0 && $sub !== $excel_sub) return $excel_sub;
        if ($excel_sub <= 0) {
            $made = rx_ensure_sub($koneksi, $pos_txt, $dep);
            if ($made > 0) return $made;
        }
    }
    return $sub > 0 ? $sub : 0;
}
}

if (!function_exists('rx_valid_dept')) {
// Pastikan id department ada di master (tahan FK ketat).
// Tidak ada / tidak valid -> 0 (penulis wajib simpan NULL, jangan ngarang default).
function rx_valid_dept($koneksi, $id) {
    $id = (int)$id;
    if ($id > 0) {
        $q = rx_q($koneksi, "SELECT id_departmen FROM ms_departmen WHERE id_departmen='$id' LIMIT 1");
        if ($q && $q->num_rows > 0) return $id;
    }
    return 0;
}
}

if (!function_exists('rx_valid_sub')) {
// Pastikan id posisi ada di master (tahan FK ketat). Sama: tak valid -> 0.
function rx_valid_sub($koneksi, $id, $dep = 0) {
    $id = (int)$id;
    if ($id > 0) {
        $q = rx_q($koneksi, "SELECT id_sub_department FROM ms_sub_department WHERE id_sub_department='$id' LIMIT 1");
        if ($q && $q->num_rows > 0) return $id;
    }
    return 0;
}
}

if (!function_exists('rx_ensure_osdhk')) {
// Cari OS/DHK, buatkan bila belum ada (auto-generate). Return id atau 0.
function rx_ensure_osdhk($koneksi, $name) {
    $name = trim((string)$name);
    if ($name === '') return 0;
    $id = rx_lookup_id($koneksi, 'ms_os_dhk', 'OS_DHK', $name);
    if ($id > 0) return $id;
    $esc = $koneksi->real_escape_string($name);
    if (rx_q($koneksi, "INSERT INTO ms_os_dhk (OS_DHK) VALUES ('$esc')")) return (int)$koneksi->insert_id;
    return 0;
}
}

if (!function_exists('rx_ensure_golongan')) {
// Cari Golongan, buatkan bila belum ada (auto-generate). Return id atau 0.
function rx_ensure_golongan($koneksi, $name) {
    $name = trim((string)$name);
    if ($name === '') return 0;
    $id = rx_lookup_id($koneksi, 'ms_golongan', 'golongan', $name);
    if ($id > 0) return $id;
    $esc = $koneksi->real_escape_string($name);
    if (rx_q($koneksi, "INSERT INTO ms_golongan (golongan) VALUES ('$esc')")) return (int)$koneksi->insert_id;
    return 0;
}
}

if (!function_exists('rx_fill_master_osgol')) {
// Lengkapi OS/Golongan master yang masih kosong dari teks Excel (auto-generate master).
// Hanya mengisi yang kosong; yang sudah ada tidak diubah.
function rx_fill_master_osgol($koneksi, $kid, $osdhk_txt, $gol_txt) {
    $kid = (int)$kid;
    if ($kid <= 0) return;
    $mk = rx_q($koneksi, "SELECT id_os_dhk, id_golongan FROM ms_karyawan WHERE id_karyawan='$kid' LIMIT 1");
    if (!($mk && ($m = $mk->fetch_assoc()))) return;
    $os_txt = trim((string)$osdhk_txt);
    if (empty($m['id_os_dhk']) && $os_txt !== '') {
        $oid = rx_ensure_osdhk($koneksi, $os_txt);
        if ($oid > 0) rx_q($koneksi, "UPDATE ms_karyawan SET id_os_dhk='$oid' WHERE id_karyawan='$kid'");
    }
    $gol_txt = trim((string)$gol_txt);
    if (empty($m['id_golongan']) && $gol_txt !== '') {
        $gid = rx_ensure_golongan($koneksi, $gol_txt);
        if ($gid > 0) rx_q($koneksi, "UPDATE ms_karyawan SET id_golongan='$gid' WHERE id_karyawan='$kid'");
    }
}
}

if (!function_exists('rx_make_karyawan')) {
// Buatkan karyawan baru dari data baris Excel. Return id_karyawan atau 0.
function rx_make_karyawan($koneksi, $nama, $osdhk, $golongan, $posisi, $shift_id, $upah, $tgl_aktif, $def_dept, $def_sub) {
    $nm_esc = $koneksi->real_escape_string(trim($nama));
    if ($nm_esc === '') return 0;
    $no_absen = rx_next_no_absen($koneksi);
    $os = rx_ensure_osdhk($koneksi, $osdhk ?? '');
    $gol = rx_ensure_golongan($koneksi, $golongan ?? '');
    $sub = (int)$def_sub;
    if (trim($posisi ?? '') !== '') {
        $sid = rx_lookup_id($koneksi, 'ms_sub_department', 'nama_sub_department', $posisi);
        if ($sid > 0) $sub = $sid;
    }
    $jd = (int)$shift_id; $up = (int)$upah;
    $ok = rx_q($koneksi, "INSERT INTO ms_karyawan (id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah, target_mingguan, target_bulanan) VALUES ('$def_dept','0','$no_absen','$nm_esc','',NULL,'',NULL,NULL,'','','','','Aktif','$tgl_aktif','$sub','$os','$gol','$jd','$up','20.00','40.00')");
    return $ok ? (int)$koneksi->insert_id : 0;
}
}
