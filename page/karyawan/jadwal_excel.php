<?php
// Roster shift harian via Excel — dari halaman karyawan.
// Alur: pilih tanggal (min hari ini) -> upload -> preview (centang) -> commit ke tb_rkk_detail.
// Master karyawan (id_jadwal) TIDAK diubah; hanya roster tanggal terpilih.
if (!function_exists('sync_karyawan_to_devices')) { @include_once __DIR__ . '/../../device_sync_helper.php'; }

if (!function_exists('kj_col2num')) {
function kj_col2num($c) { $n = 0; for ($i = 0; $i < strlen($c); $i++) $n = $n * 26 + ord($c[$i]) - 64; return $n; }
}

if (!function_exists('kj_read_sheet1')) {
// Baca sheet pertama .xlsx -> [rowNum => [colNum => val]]
function kj_read_sheet1($file) {
    if (!class_exists('ZipArchive')) return false; // ext php_zip mati -> gagal ramah, bukan fatal
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
    $wbRaw = $zx('xl/workbook.xml');
    $relsRaw = $zx('xl/_rels/workbook.xml.rels');
    $wb = $xload($wbRaw);
    $rx = $xload($relsRaw);
    if (!$wb || !$rx) { $z->close(); return false; }
    $rels = [];
    foreach ($rx->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $rel) {
        $a = $rel->attributes();
        $t = ltrim((string)$a['Target'], '/');
        if (strpos($t, 'xl/') !== 0) $t = 'xl/' . $t;
        $rels[(string)$a['Id']] = $t;
    }
    $first = null;
    foreach ($wb->children($NS)->sheets->children($NS)->sheet as $sh) {
        $rid = '';
        foreach ($sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $v) $rid = (string)$v;
        if (isset($rels[$rid])) { $first = $rels[$rid]; break; }
    }
    // Fallback: tebak path sheet langsung bila rels tak beres
    if (!$first) {
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $cand) {
            if ($zx($cand) !== null) { $first = $cand; break; }
        }
    }
    if (!$first) { $z->close(); return false; }
    $sx = $xload($zx($first));
    if (!$sx) { $z->close(); return false; }
    $rows = [];
    foreach ($sx->children($NS)->sheetData->children($NS)->row as $row) {
        $ra = $row->attributes();
        $rn = (int)$ra['r'];
        foreach ($row->children($NS)->c as $c) {
            $ca = $c->attributes();
            if (!preg_match('/^([A-Z]+)(\d+)$/', (string)$ca['r'], $m)) continue;
            $t = (string)$ca['t'];
            $kids = $c->children($NS);
            if ($t === 's') { $idx = (int)(string)$kids->v; $v = isset($ss[$idx]) ? $ss[$idx] : ''; }
            elseif (isset($kids->is)) { $is = $kids->is->children($NS); $v = (string)$is->t; }
            else $v = (string)$kids->v;
            $rows[$rn][kj_col2num($m[1])] = $v;
        }
    }
    ksort($rows);
    $z->close();
    return $rows;
    } catch (Exception $e) {
        return false;
    }
}
}

if (!function_exists('kj_norm')) {
function kj_norm($s) { $s = trim((string)$s); return preg_replace('/\s+/', ' ', $s); }
}

if (!function_exists('kj_match_karyawan')) {
function kj_match_karyawan($koneksi, $v) {
    $v = kj_norm($v);
    if ($v === '') return null;
    if (ctype_digit($v)) {
        $q = $koneksi->query("SELECT k.id_karyawan, k.no_absen, k.nama_karyawan, k.id_jadwal, j.keterangan AS shift_now FROM ms_karyawan k LEFT JOIN tb_jadwal j ON j.id_jadwal=k.id_jadwal WHERE k.no_absen='$v' LIMIT 1");
        if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    }
    $esc = $koneksi->real_escape_string($v);
    $q = $koneksi->query("SELECT k.id_karyawan, k.no_absen, k.nama_karyawan, k.id_jadwal, j.keterangan AS shift_now FROM ms_karyawan k LEFT JOIN tb_jadwal j ON j.id_jadwal=k.id_jadwal WHERE TRIM(k.nama_karyawan)='$esc' OR LOWER(TRIM(k.nama_karyawan))=LOWER('$esc') LIMIT 1");
    if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    return null;
}
}

if (!function_exists('kj_match_shift')) {
function kj_match_shift($koneksi, $v) {
    $v = trim((string)$v);
    if ($v === '') return null;
    if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*[-–\/]/', $v, $m)) {
        $v = $m[1] . ':' . str_pad(isset($m[2]) ? $m[2] : '00', 2, '0', STR_PAD_LEFT);
    }
    if (ctype_digit($v)) {
        $q = $koneksi->query("SELECT * FROM tb_jadwal WHERE id_jadwal='$v' LIMIT 1");
        if ($q && $q->num_rows > 0) return $q->fetch_assoc();
        $v = str_pad($v, 2, '0', STR_PAD_LEFT) . ':00';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})/', $v, $m)) {
        $t = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        $q = $koneksi->query("SELECT * FROM tb_jadwal WHERE jam_masuk LIKE '$t%' LIMIT 1");
        if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    }
    $esc = $koneksi->real_escape_string($v);
    $q = $koneksi->query("SELECT * FROM tb_jadwal WHERE UPPER(keterangan) LIKE UPPER('%$esc%') LIMIT 1");
    if ($q && $q->num_rows > 0) return $q->fetch_assoc();
    return null;
}
}

if (!function_exists('kj_frac_hours')) {
// Fraksi Excel (0.41..) -> jam desimal. String "07:00" -> 7.0
function kj_frac_hours($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '`') return 0;
    if (preg_match('/^(\d{1,2}):(\d{2})/', $v, $m)) return round((int)$m[1] + (int)$m[2] / 60, 2);
    if (is_numeric($v)) return round(((float)$v) * 24, 2);
    return 0;
}
}

if (!function_exists('kj_frac_to_time')) {
// Fraksi Excel (0.41..) atau string "07:00" -> 'HH:MM:SS' ('' bila kosong)
function kj_frac_to_time($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '`') return '';
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m)) {
        return sprintf('%02d:%02d:%02d', (int)$m[1], (int)$m[2], isset($m[3]) ? (int)$m[3] : 0);
    }
    if (is_numeric($v)) {
        $sec = (int)round((float)$v * 86400) % 86400;
        if ($sec < 0) $sec = 0;
        return sprintf('%02d:%02d:%02d', (int)($sec / 3600), (int)(($sec % 3600) / 60), $sec % 60);
    }
    if (preg_match('/^(\d{1,2})(?:[:.](\d{2}))?$/', $v, $m)) {
        return sprintf('%02d:%02d:00', (int)$m[1], isset($m[2]) ? (int)$m[2] : 0);
    }
    return '';
}
}

if (!function_exists('kj_guess_shift_by_hours')) {
// Tebak shift dari durasi jam kerja -> baris tb_jadwal (durasi terdekat, lewati Flexible/nol)
function kj_guess_shift_by_hours($koneksi, $hours) {
    $best = null; $bestDiff = null;
    $q = $koneksi->query("SELECT * FROM tb_jadwal");
    if (!$q) return null;
    while ($r = $q->fetch_assoc()) {
        $in = strtotime($r['jam_masuk'] ?? '00:00:00');
        $out = strtotime($r['jam_keluar'] ?? '00:00:00');
        $dur = ($out - $in) / 3600;
        if ($dur <= 0) $dur += 24;
        if (($r['jam_masuk'] ?? '') === '00:00:00' && ($r['jam_keluar'] ?? '00:00:00') === '00:00:00') continue;
        $diff = abs($dur - (float)$hours);
        if ($best === null || $diff < $bestDiff) { $best = $r; $bestDiff = $diff; }
    }
    return $best;
}
}

if (!function_exists('kj_next_no_absen')) {
// No. absen kosong berikutnya (MAX+1, lewati yang kepakai)
function kj_next_no_absen($koneksi) {
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

if (!function_exists('kj_is_header_row')) {
function kj_is_header_row($a, $b) {
    $s = strtoupper(trim((string)$a) . ' ' . trim((string)$b));
    return strpos($s, 'NAMA') !== false || strpos($s, 'SHIFT') !== false || strpos($s, 'JADWAL') !== false || strpos($s, 'ABSEN') !== false;
}
}

if (!function_exists('kj_is_junk')) {
// Baris judul/aturan/section/biaya — bukan data karyawan.
function kj_is_junk($a, $b) {
    $s = strtoupper(trim((string)$a) . ' ' . trim((string)$b));
    if (preg_match('/FORMAT|JIKA |MASUK JAM|ISTIRAHAT JAM|POTONG|PARAF|GOLONGAN|POSISI|JAM KERJA|JAM MASUK|JAM PULANG|HASIL KERJA|UPAH|LEMBUR|TOTAL|JUMLAH|SUBTOTAL|BIAYA|BAYARAN|PENGURANGAN|DIV\/0|BULANAN|MINGGUAN|HARIAN|GRAND/', $s)) return true;
    // Section satu kata kapital (ADMIN, QUALITY CONTROL...)
    if (trim((string)$b) === '' && preg_match('/^[A-Z][A-Z \.&\-]{2,}$/', trim((string)$a))) return true;
    return false;
}
}

?>
<div class="container-fluid px-2 mt-4 mb-4">
<div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
<div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
<div>
<h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fas fa-calendar-alt mr-2"></i>Ubah Jadwal via Excel</h3>
<p class="text-[12px] text-gray-400 mt-0.5 mb-0">Kolom A = No &nbsp;•&nbsp; Kolom B = Nama Karyawan &nbsp;•&nbsp; Kolom G = Jam Masuk (shift dicocokkan otomatis) &nbsp;•&nbsp; tersimpan sebagai jadwal shift tanggal yang dipilih (master tidak diubah)</p>
</div>
<div class="w-full md:w-auto mt-2 md:mt-0">
<a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto"><i class="fas fa-arrow-left mr-1.5"></i> Kembali</a>
</div>
</div>
<div class="p-6">
<?php
$step = $_POST['kj_step'] ?? 'upload';
if ($step === 'commit') {
    $imp = $_SESSION['kj_jadwal'] ?? null;
    if (!$imp) {
        echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">Data preview kedaluwarsa. Upload ulang filenya.</div>';
    } else {
        $kj_date = trim((string)($_POST['kj_date'] ?? ($imp['date'] ?? '')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $kj_date) || $kj_date < date('Y-m-d')) {
            echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">Tanggal tidak valid / sudah lewat. Ulangi dari awal.</div>';
        } else {
        $sel = array_map('intval', (array)($_POST['kj_sel'] ?? []));
        $ov = (array)($_POST['kj_shift'] ?? []);
        // Pastikan header RKK tanggal ini ada (buat bila belum)
        $qh = $koneksi->query("SELECT id_rkk FROM tb_rkk WHERE tgl_rkk='$kj_date' LIMIT 1");
        if ($qh && $qh->num_rows > 0) { $rh = $qh->fetch_assoc(); $id_rkk = (int)$rh['id_rkk']; }
        else {
            $now = date('Y-m-d H:i:s');
            $ket = $koneksi->real_escape_string('Jadwal Excel ' . $kj_date);
            $koneksi->query("INSERT INTO tb_rkk (tgl_rkk, keterangan, jam_kerja, detail_rkk, status_rkk, tgl_status) VALUES ('$kj_date', '$ket', '12', '$now', '0', '$now')");
            $id_rkk = $koneksi->insert_id;
        }
        $def_dept = 1; $def_sub = 1;
        $qd = $koneksi->query("SELECT MIN(id_departmen) AS m FROM ms_departmen");
        if ($qd && ($rd = $qd->fetch_assoc()) && $rd['m']) $def_dept = (int)$rd['m'];
        $qs = $koneksi->query("SELECT MIN(id_sub_department) AS m FROM ms_sub_department");
        if ($qs && ($rs = $qs->fetch_assoc()) && $rs['m']) $def_sub = (int)$rs['m'];
        $ok = 0; $fail = 0; $c_new = 0; $same = 0; $m_upd = 0; $msgs = [];
        if (getenv('KJ_DEBUG')) { echo '<!--DBG'; echo ' sel=' . htmlspecialchars(json_encode($sel)); echo ' nrows=' . count($imp['rows']); echo ' -->'; }
        foreach ($imp['rows'] as $i => $r) {
            if (!in_array($i, $sel, true)) continue;
            $sid = (int)($r['shift_id'] ?? 0);
            // Koreksi manual dari dropdown preview (mengesampingkan tebakan)
            if (isset($ov[$i]) && ctype_digit((string)$ov[$i]) && (int)$ov[$i] > 0) {
                $chk = $koneksi->query("SELECT id_jadwal FROM tb_jadwal WHERE id_jadwal='" . (int)$ov[$i] . "' LIMIT 1");
                if ($chk && $chk->num_rows > 0) $sid = (int)$ov[$i];
            }
            if (empty($r['karyawan_id']) && !empty($r['nama_excel']) && !empty($sid)) {
                // Karyawan belum ada -> buatkan baru di master (absen dari Excel bila kosong, else MAX+1)
                $want = (int)($r['absen_want'] ?? 0);
                if ($want > 0) {
                    $chk2 = $koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE no_absen='$want' LIMIT 1");
                    if ($chk2 && $chk2->num_rows > 0) $want = 0;
                }
                $no_absen = $want > 0 ? $want : kj_next_no_absen($koneksi);
                $nm_esc = $koneksi->real_escape_string($r['nama_excel']);
                if ($koneksi->query("INSERT INTO ms_karyawan (id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah, target_mingguan, target_bulanan) VALUES ('0','0','$no_absen','$nm_esc','',NULL,'',NULL,NULL,'','','','','Aktif','$kj_date','0','0','0','$sid','0','20.00','40.00')")) {
                    $r['karyawan_id'] = $koneksi->insert_id;
                    $c_new++;
                } else { $fail++; $msgs[] = $r['nama_excel'] . ': gagal dibuat (' . $koneksi->error . ')'; continue; }
            }
            if (empty($r['karyawan_id']) || empty($sid)) { $fail++; continue; }
            $kid = (int)$r['karyawan_id'];
            // Sudah sama dengan roster tanggal ini -> lewati bagian roster
            $qc = $koneksi->query("SELECT id_rkk_detail, id_jadwal FROM tb_rkk_detail WHERE id_rkk='$id_rkk' AND id_karyawan='$kid' LIMIT 1");
            $cur = ($qc && $qc->num_rows > 0) ? $qc->fetch_assoc() : null;
            $roster_same = $cur && (int)$cur['id_jadwal'] === $sid;
            if ($cur && !$roster_same) {
                if ($koneksi->query("UPDATE tb_rkk_detail SET id_jadwal='$sid' WHERE id_rkk_detail='" . (int)$cur['id_rkk_detail'] . "'")) $ok++;
                else { $fail++; $msgs[] = $r['nama_excel'] . ': ' . $koneksi->error; continue; }
            } elseif (!$cur) {
                if ($koneksi->query("INSERT INTO tb_rkk_detail (id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, upah, status_rkk, id_jadwal, potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) VALUES ('$id_rkk', '$kid', NULL, '$def_dept', '$def_sub', '0', 'Hadir', '$sid', '0', '0', '0', NOW())")) $ok++;
                else { $fail++; $msgs[] = $r['nama_excel'] . ': ' . $koneksi->error; continue; }
            } else { $same++; }
            // Roster saja: master ms_karyawan TIDAK diubah dari Excel.
            // Shift tetap diganti lewat Shift/Jadwal di form Ubah; rencana per tanggal via sini.
        }
        echo '<div class="p-4 mb-4 rounded-lg text-sm ' . ($fail == 0 ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-amber-50 border border-amber-200 text-amber-800') . '"><strong>Selesai  ' . htmlspecialchars($kj_date) . ':</strong> ' . $ok . ' baris ditulis' . ($m_upd > 0 ? ', ' . $m_upd . ' master ikut berubah' : '') . ($c_new > 0 ? ' (' . $c_new . ' karyawan baru dibuat)' : '') . ($same > 0 ? ', ' . $same . ' sudah sama (dilewati)' : '') . ($fail > 0 ? ', ' . $fail . ' gagal' : '') . '.</div>';
        foreach ($msgs as $m) echo '<div class="text-xs text-red-600 mb-1">' . htmlspecialchars($m) . '</div>';
        echo '<a href="?page=karyawan" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded shadow-sm">Kembali ke Data Karyawan</a> ';
        unset($_SESSION['kj_jadwal']);
        } // tutup: tanggal valid
    }
} elseif ($step === 'preview' && !empty($_FILES['fexcel']['tmp_name'])) {
    $rows = kj_read_sheet1($_FILES['fexcel']['tmp_name']);
    if ($rows === false || empty($rows)) {
        echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">File tidak bisa dibaca (bukan .xlsx valid?).'
            . '<br><span class="text-xs text-gray-500">Info: upload_err=' . (int)($_FILES['fexcel']['error'] ?? -1)
            . ', tmp=' . (isset($_FILES['fexcel']['tmp_name']) && @file_exists($_FILES['fexcel']['tmp_name']) ? 'ada' : 'hilang')
            . ', ukuran=' . (isset($_FILES['fexcel']['tmp_name']) && @file_exists($_FILES['fexcel']['tmp_name']) ? (int)@filesize($_FILES['fexcel']['tmp_name']) : 0) . ' byte.</span></div>';
    } else {
        $mode = $_POST['kj_mode'] ?? 'absensi';
        if ($mode !== 'jadwal') $mode = 'absensi';
        // Tanggal roster: wajib, tidak boleh lewat hari ini
        $kj_date = trim((string)($_POST['kj_date'] ?? ''));
        $today = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $kj_date) || $kj_date < $today) {
            echo '<div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">Tanggal tidak valid / sudah lewat. Pilih tanggal hari ini atau sesudahnya.</div>';
        } else {
        $parsed = [];
        $skipped = 0;
        // Roster yang sudah ada per tanggal ini: id_karyawan => id_jadwal
        $rosterMap = [];
        $qr0 = $koneksi->query("SELECT d.id_karyawan, d.id_jadwal, j.keterangan, j.jam_masuk, j.jam_keluar FROM tb_rkk_detail d JOIN tb_rkk h ON h.id_rkk=d.id_rkk LEFT JOIN tb_jadwal j ON j.id_jadwal=d.id_jadwal WHERE h.tgl_rkk='$kj_date'");
        if ($qr0) while ($rw0 = $qr0->fetch_assoc()) $rosterMap[(int)$rw0['id_karyawan']] = $rw0;
        foreach ($rows as $rn => $cols) {
            $a = trim((string)($cols[1] ?? ''));
            $b = trim((string)($cols[2] ?? ''));
            if ($a === '' && $b === '') continue;
            if (kj_is_header_row($a, $b)) { $skipped++; continue; }
            if (kj_is_junk($a, $b)) { $skipped++; continue; }

            if ($mode === 'absensi') {
                // Format absensi: B = Nama (A = absen cadangan), G = JAM MASUK
                // -> cocokkan shift berdasarkan jam_masuk (cth: 10:00 -> Shift 10-22).
                // Bila tidak ada shift yang cocok, fallback ke tebak durasi.
                if ($b === '') { $skipped++; continue; }
                $nama = $b;
                $emp = kj_match_karyawan($koneksi, $nama);
                $via = $emp ? 'nama' : '';
                $absen_want = 0;
                if (!$emp && ctype_digit($a)) {
                    $absen_want = (int)$a;
                    $chk = $koneksi->query("SELECT k.id_karyawan, k.no_absen, k.nama_karyawan, k.id_jadwal, j.keterangan AS shift_now FROM ms_karyawan k LEFT JOIN tb_jadwal j ON j.id_jadwal=k.id_jadwal WHERE k.no_absen='$absen_want' LIMIT 1");
                    if ($chk && $chk->num_rows > 0) { $emp = $chk->fetch_assoc(); $via = 'absen'; }
                }
                $graw = trim((string)($cols[7] ?? ''));
                $start = kj_frac_to_time($graw);
                $sh = null;
                if ($start !== '') {
                    $hm = substr($start, 0, 5);
                    $qs = $koneksi->query("SELECT * FROM tb_jadwal WHERE jam_masuk LIKE '$hm%' LIMIT 1");
                    if ($qs && $qs->num_rows > 0) $sh = $qs->fetch_assoc();
                }
                if (!$sh) {
                    $hours = kj_frac_hours($graw);
                    $sh = $hours > 0 ? kj_guess_shift_by_hours($koneksi, $hours) : null;
                }
                $shift_raw = $start !== '' ? substr($start, 0, 5) : '-';
            } else {
                // Format jadwal: A = No.Absen/Nama, B = shift
                // Section satu kata kapital tanpa shift (ADMIN, ...) dilewati;
                // nama asli yang kebetulan kapital tetap diproses bila cocok di DB.
                $emp = kj_match_karyawan($koneksi, $a);
                if (!$emp && $b === '' && preg_match('/^[A-Z][A-Z \.&\-]{2,}$/', $a)) { $skipped++; continue; }
                $nama = $a !== '' ? $a : $b;
                if (!$emp) $emp = ($a !== '' && $b !== '') ? kj_match_karyawan($koneksi, $b) : $emp;
                $sh = kj_match_shift($koneksi, $b);
                $shift_raw = $b;
            }

            // Shift efektif saat ini: roster tanggal dipilih bila ada, else shift master.
            // (Roster tidak menimpa master; master tetap jadi default.)
            $curId = 0; $curTxt = '-'; $curSrc = '';
            if ($emp) {
                $curId = (int)($emp['id_jadwal'] ?? 0);
                $curTxt = $emp['shift_now'] ?: '-';
                if (isset($rosterMap[(int)$emp['id_karyawan']]) && !empty($rosterMap[(int)$emp['id_karyawan']]['id_jadwal'])) {
                    $rr0 = $rosterMap[(int)$emp['id_karyawan']];
                    $curId = (int)$rr0['id_jadwal'];
                    $curTxt = ($rr0['keterangan'] ?: 'Shift ' . $curId) . ' (' . substr($rr0['jam_masuk'] ?? '', 0, 5) . '-' . substr($rr0['jam_keluar'] ?? '', 0, 5) . ') [jadwal shift]';
                    $curSrc = 'roster';
                }
            }

            $parsed[] = [
                'row' => $rn,
                'nama_excel' => $nama,
                'kolom_a' => $a, // Tambah data mentah kolom A
                'kolom_b' => $b, // Tambah data mentah kolom B
                'karyawan_id' => $emp ? (int)$emp['id_karyawan'] : 0,
                'no_absen' => $emp ? $emp['no_absen'] : '-',
                'nama_db' => $emp ? $emp['nama_karyawan'] : '-',
                'shift_now' => $curTxt,
                'shift_id' => $sh ? (int)$sh['id_jadwal'] : 0,
                'shift_now_id' => $curId,
                'shift_master_id' => $emp ? (int)($emp['id_jadwal'] ?? 0) : 0,
                'shift_src' => $curSrc,
                'shift_new' => $sh ? ($sh['keterangan'] . ' (' . $sh['jam_masuk'] . '-' . $sh['jam_keluar'] . ')') : '-',
                'shift_raw' => $shift_raw,
                'via' => isset($via) ? $via : '',
                'absen_want' => isset($absen_want) ? (int)$absen_want : (ctype_digit($a) ? (int)$a : 0),
                'karyawan_found' => $emp ? true : false, // Flag apakah karyawan ketemu
                'shift_found' => $sh ? true : false, // Flag apakah shift ketemu
            ];
        }
        if (empty($parsed)) {
            echo '<div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">Tidak ada baris data yang terbaca (cuma header/kosong?).</div>';
        } else {
            $_SESSION['kj_jadwal'] = ['rows' => $parsed, 'date' => $kj_date, 'mode' => $mode];
            echo '<div class="mb-4 p-4 bg-indigo-50 border border-indigo-100 rounded-lg text-sm"><strong>Jadwal shift tanggal ' . htmlspecialchars($kj_date) . ':</strong> ' . htmlspecialchars($_FILES['fexcel']['name']) . ' — ' . count($parsed) . ' baris data' . ($skipped > 0 ? ' <span class="text-gray-500">(' . $skipped . ' baris judul/section dilewati)</span>' : '') . '.</div>';
            
            // Debug info - tampilkan data mentah Excel
            echo '<div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs">';
            echo '<details><summary class="cursor-pointer font-semibold text-yellow-800">🔍 Debug: Data Excel Mentah (klik untuk lihat)</summary>';
            echo '<div class="mt-2 max-h-40 overflow-y-auto bg-white p-2 rounded border">';
            echo '<table class="w-full text-xs border-collapse">';
            echo '<tr class="bg-gray-100"><th class="border px-1 py-0.5">Baris</th><th class="border px-1 py-0.5">Kolom A</th><th class="border px-1 py-0.5">Kolom B</th></tr>';
            foreach ($rows as $rn => $cols) {
                $a = htmlspecialchars(trim((string)($cols[1] ?? '')));
                $b = htmlspecialchars(trim((string)($cols[2] ?? '')));
                if ($a === '' && $b === '') continue;
                echo "<tr><td class=\"border px-1 py-0.5\">$rn</td><td class=\"border px-1 py-0.5\">$a</td><td class=\"border px-1 py-0.5\">$b</td></tr>";
            }
            echo '</table></div></details></div>';
            
            echo '<form method="POST">';
            echo '<input type="hidden" name="kj_step" value="commit">';
            echo '<input type="hidden" name="kj_date" value="' . htmlspecialchars($kj_date) . '">';
            $shift_opts = [];
            $qs = $koneksi->query("SELECT id_jadwal, keterangan, jam_masuk, jam_keluar FROM tb_jadwal ORDER BY keterangan");
            while ($qs && ($srow = $qs->fetch_assoc())) $shift_opts[] = $srow;
            echo '<div class="overflow-x-auto border border-gray-200 rounded-xl mb-4"><table class="w-full text-sm text-left">';
            // Mode absensi: kolom A tidak dipakai (cuma nomor urut) -> sembunyikan biar tidak bingung.
            // Kolom B = nama karyawan.
            $hideA = ($mode === 'absensi');
            $colBLabel = $hideA ? 'Nama Karyawan' : 'Kolom B (Excel)';
            echo '<thead class="bg-gray-50 border-b border-gray-200"><tr><th class="py-2 px-3 w-10"><input type="checkbox" id="kj_all" checked></th><th class="py-2 px-3 w-12">No</th>' . ($hideA ? '' : '<th class="py-2 px-3">Kolom A (Excel)</th>') . '<th class="py-2 px-3">' . $colBLabel . '</th><th class="py-2 px-3">Karyawan (DB)</th><th class="py-2 px-3">Shift tgl ' . htmlspecialchars($kj_date) . '</th><th class="py-2 px-3">Shift baru</th><th class="py-2 px-3">Status</th></tr></thead><tbody class="divide-y divide-gray-100">';
            echo '<script>document.getElementById("kj_all").addEventListener("change",function(){document.querySelectorAll(".kj_sel").forEach(function(c){c.checked=document.getElementById("kj_all").checked;});});</script>';
            foreach ($parsed as $idx => $r) {
                $valid = $r['karyawan_id'] > 0 && $r['shift_id'] > 0;
                // Sama = roster tanggal ini SUDAH final DAN master SUDAH final -> tidak ada kerjaan
                $sameShift = $valid && (int)($r['shift_now_id'] ?? 0) === (int)$r['shift_id'] && (int)($r['shift_master_id'] ?? 0) === (int)$r['shift_id'];

                // Status yang lebih detail
                if (!$r['karyawan_found'] && !$r['shift_found']) {
                    $st = '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800">Karyawan & Shift tak ketemu</span>';
                } elseif (!$r['karyawan_found']) {
                    $absInfo = !empty($r['absen_want']) ? ' (absen ' . (int)$r['absen_want'] . ')' : ' (absen otomatis)';
                    $st = '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800">Baru, akan dibuat' . htmlspecialchars($absInfo) . '</span>';
                } elseif (!$r['shift_found']) {
                    $absInfo = !empty($r['absen_want']) ? ' (absen ' . (int)$r['absen_want'] . ')' : ' (absen otomatis)';
                    $st = '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800">Baru, akan dibuat' . htmlspecialchars($absInfo) . '</span>';
                } elseif (!$r['shift_found']) {
                    $st = '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800">Shift tak ketemu: ' . htmlspecialchars($r['shift_raw']) . '</span>';
                } elseif ($sameShift) {
                    $st = '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-600">Sudah sama — dilewati</span>';
                } else {
                    $st = '<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-800">Siap</span>';
                }
                
                // Dropdown koreksi: tebakan boleh salah (mis. durasi mirip), user bisa pilih manual.
                // Pilihan manual otomatis mencentang barisnya.
                $selHtml = '<select name="kj_shift[' . $idx . ']" class="kj_shift_sel border border-gray-300 rounded px-1 py-1 text-xs max-w-[190px]">';
                $selHtml .= '<option value="0">- Pilih -</option>';
                foreach ($shift_opts as $so) {
                    $sel = ((int)$so['id_jadwal'] === (int)$r['shift_id']) ? ' selected' : '';
                    $selHtml .= '<option value="' . (int)$so['id_jadwal'] . '"' . $sel . '>' . htmlspecialchars($so['keterangan']) . ' (' . htmlspecialchars($so['jam_masuk']) . '-' . htmlspecialchars($so['jam_keluar']) . ')</option>';
                }
                $selHtml .= '</select>';
                echo '<tr class="hover:bg-gray-50"><td class="py-2 px-3"><input type="checkbox" class="kj_sel" name="kj_sel[]" value="' . $idx . '" ' . ($valid && !$sameShift ? 'checked' : '') . '></td><td class="py-2 px-3 text-gray-500">' . ($idx + 1) . '</td>'
                    . ($hideA ? '' : '<td class="py-2 px-3 font-semibold">' . htmlspecialchars($r['kolom_a']) . '<div class="text-xs font-normal text-gray-400">baris ' . (int)$r['row'] . '</div></td>')
                    . '<td class="py-2 px-3 font-semibold">' . htmlspecialchars($r['kolom_b']) . '</td>'
                    . '<td class="py-2 px-3">' . ($r['karyawan_id'] > 0 ? htmlspecialchars($r['nama_db']) . ' <span class="text-gray-400 text-xs">(' . htmlspecialchars($r['no_absen']) . ($r['via'] === 'absen' ? ', via absen' : '') . ')</span>' : '<span class="text-blue-600 text-xs">Baru: ' . htmlspecialchars($r['nama_excel']) . '</span>') . '</td>'
                    . '<td class="py-2 px-3 text-xs">' . htmlspecialchars($r['shift_now']) . '</td>'
                    . '<td class="py-2 px-3 text-xs font-semibold">' . htmlspecialchars($r['shift_new']) . '<div class="mt-1 font-normal">' . $selHtml . '</div></td>'
                    . '<td class="py-2 px-3">' . $st . '</td></tr>';
            }
            echo '<script>document.querySelectorAll(".kj_shift_sel").forEach(function(s){s.addEventListener("change",function(){var tr=s.closest("tr");if(!tr)return;var c=tr.querySelector(".kj_sel");if(c&&s.value!=="0")c.checked=true;});});</script>';
            echo '</tbody></table></div>';
            echo '<div class="flex gap-2"><button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-check mr-2"></i>Simpan Jadwal Tercentang</button>';
            echo '<a href="?page=karyawan&aksi=jadwal_excel" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2.5 px-6 rounded-lg text-sm">Ulangi</a></div>';
            echo '</form>';
        }
        } // tutup: tanggal valid
    }
} else {
    // Halaman upload dibuka fresh -> buang sisa preview sebelumnya biar tidak nyangkut.
    unset($_SESSION['kj_jadwal']);
    ?>
    <form method="POST" enctype="multipart/form-data" class="max-w-xl">
    <input type="hidden" name="kj_step" value="preview">
    <div class="mb-4">
    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tanggal rencana upah <span class="text-rose-500">*</span></label>
    <input type="date" name="kj_date" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm bg-white">
    <p class="text-xs text-gray-500 mt-1">Perubahan hanya berlaku tanggal ini. Tanggal lewat tidak bisa dipilih.</p>
    </div>
    <div class="mb-4">
    <label class="block text-sm font-semibold text-gray-700 mb-1.5">File Excel (.xlsx) — sheet pertama dibaca</label>
    <input type="file" name="fexcel" accept=".xlsx" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm bg-white">
    <input type="hidden" name="kj_mode" value="absensi">
    <p class="text-xs text-gray-500 mt-1">Format: kolom A = No, B = Nama Karyawan, G = Jam Masuk → shift dicocokkan otomatis.</p>
    </div>
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-upload mr-2"></i>Upload &amp; Preview</button>
    </form>
    <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 leading-relaxed max-w-xl">
    <strong>Format file:</strong> kolom A = No, kolom B = Nama Karyawan, kolom G = Jam Masuk. Shift dicocokkan otomatis; baris judul dilewati; nama baru bisa dibuatkan otomatis.
    </div>
    <?php
}
?>
</div>
</div>
</div>
