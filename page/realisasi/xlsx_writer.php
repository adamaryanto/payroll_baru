<?php
// Writer .xlsx hardened pengganti xlsx_mini.php — API SAMA PERSIS
// (style/sheet/cell/merge/width/autofit/save/send) sehingga file export
// yang sudah ada tidak perlu diubah tampilannya.
//
// Perbedaan internal (anti "Excel minta repair"):
// 1. String via sharedStrings (bukan inlineStr) — paling kompatibel dgn validator ketat Excel.
// 2. Sanitasi UTF-8 byte-safe: konversi Windows-1252 dulu, baru strip karakter
//    terlarang XML 1.0 (kontrol C0/C1, FFFE/FFFF). Tidak pernah memotong byte tengah.
// 3. Angka non-finite (INF/NAN) tidak pernah ditulis ke <v>.
// 4. Style default (idx 0) di-seed di constructor — sel tanpa style selalu valid.
// 5. Merge divalidasi: sel tunggal & tumpang-tindih dibuang.
// 6. Batas baris/kolom/nama-sheet/panjang-string sesuai limit Excel.
// 7. Tanpa closing tag output; save() verifikasi ulang zip+XML sebelum dianggap sukses.
// 8. numFmt custom hanya dideklarasikan bila benar-benar dipakai.

class RealisasiXlsx {
    private $sheets = [];
    private $xfs = [];
    private $xfmap = [];
    private $strings = [];
    private $strmap = [];

    const MAX_ROW = 1048576;
    const MAX_COL = 16384;
    const MAX_STR = 32000;

    public function __construct() {
        $this->style([]); // idx 0 = default, selalu ada
    }

    // $o: b(bool) sz(int) color(hex tanpa #) fill(hex tanpa #) border(bool)
    //     align(left|center|right) wrap(bool) num(null|int|dec2|rp|rp2)
    public function style($o = []) {
        if (!is_array($o)) $o = [];
        $b = !empty($o['b']);
        $sz = isset($o['sz']) ? max(1, min(72, (int)$o['sz'])) : 11;
        $color = isset($o['color']) ? strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$o['color'])) : '000000';
        if (strlen($color) !== 6) $color = '000000';
        $fill = isset($o['fill']) ? strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$o['fill'])) : '';
        if ($fill !== '' && strlen($fill) !== 6) $fill = '';
        $border = !empty($o['border']);
        $align = in_array(($o['align'] ?? ''), ['left', 'center', 'right'], true) ? $o['align'] : '';
        $wrap = !empty($o['wrap']);
        $num = in_array(($o['num'] ?? null), ['int', 'dec2', 'rp', 'rp2'], true) ? $o['num'] : null;
        $sig = json_encode([$b, $sz, $color, $fill, $border, $align, $wrap, $num]);
        if (isset($this->xfmap[$sig])) return $this->xfmap[$sig];
        $idx = count($this->xfs);
        $this->xfs[] = [$b, $sz, $color, $fill, $border, $align, $wrap, $num];
        $this->xfmap[$sig] = $idx;
        return $idx;
    }

    public function sheet($name) {
        $name = self::clean((string)$name);
        $name = preg_replace('/[\\\\\\/\\:\\*\\?\\[\\]]/u', ' ', $name);
        $name = trim($name);
        if ($name === '') $name = 'Sheet';
        if (function_exists('mb_substr')) $name = mb_substr($name, 0, 31, 'UTF-8');
        else $name = substr($name, 0, 31);
        // Hindari nama duplikat (Excel menolaknya -> repair)
        $base = $name; $n = 2;
        $names = array_column($this->sheets, 'name');
        while (in_array($name, $names, true)) { $name = substr($base, 0, 28) . ' ' . $n; $n++; }
        $this->sheets[] = ['name' => $name, 'rows' => [], 'merge' => [], 'widths' => [], 'maxcol' => 0];
        return count($this->sheets) - 1;
    }

    // $v string|number|null, $s style idx. Angka valid jadi tipe n, sisanya shared string.
    public function cell($sh, $r, $c, $v, $s = 0) {
        $sh = (int)$sh; $r = (int)$r; $c = (int)$c; $s = (int)$s;
        if (!isset($this->sheets[$sh])) return;
        if ($r < 1 || $r > self::MAX_ROW || $c < 1 || $c > self::MAX_COL) return;
        if ($s < 0 || $s >= count($this->xfs)) $s = 0;
        if ($v === null) $v = '';
        if (is_float($v) && !is_finite($v)) $v = '';
        if (is_int($v) || is_float($v)) {
            $num = self::numStr($v);
            $this->sheets[$sh]['rows'][$r][$c] = ['v' => $num, 't' => 'n', 's' => $s];
        } else {
            $str = self::clean((string)$v);
            if ($str === '') {
                $this->sheets[$sh]['rows'][$r][$c] = ['v' => '', 't' => 'e', 's' => $s];
            } else {
                $si = $this->sharedIdx($str);
                $this->sheets[$sh]['rows'][$r][$c] = ['v' => $si, 't' => 's', 's' => $s];
            }
        }
        if ($c > $this->sheets[$sh]['maxcol']) $this->sheets[$sh]['maxcol'] = $c;
    }

    public function merge($sh, $r1, $c1, $r2, $c2) {
        $sh = (int)$sh;
        if (!isset($this->sheets[$sh])) return;
        $r1 = max(1, min(self::MAX_ROW, (int)$r1)); $r2 = max(1, min(self::MAX_ROW, (int)$r2));
        $c1 = max(1, min(self::MAX_COL, (int)$c1)); $c2 = max(1, min(self::MAX_COL, (int)$c2));
        if ($r1 > $r2) { $t = $r1; $r1 = $r2; $r2 = $t; }
        if ($c1 > $c2) { $t = $c1; $c1 = $c2; $c2 = $t; }
        if ($r1 === $r2 && $c1 === $c2) return; // merge 1 sel = tidak valid, buang
        foreach ($this->sheets[$sh]['merge'] as $m) {
            if ($r1 <= $m[2] && $r2 >= $m[0] && $c1 <= $m[3] && $c2 >= $m[1]) return; // tumpang tindih, buang
        }
        // Ratakan style sel anchor ke seluruh area merge (border/fill tidak putus).
        // Hanya sel kosong yang diisi; isi sel lain yang sudah ada tidak ditimpa.
        $anchor = $this->sheets[$sh]['rows'][$r1][$c1] ?? null;
        if ($anchor && isset($anchor['s'])) {
            for ($rr = $r1; $rr <= $r2; $rr++) {
                for ($cc = $c1; $cc <= $c2; $cc++) {
                    if ($rr === $r1 && $cc === $c1) continue;
                    if (!isset($this->sheets[$sh]['rows'][$rr][$cc])) {
                        $this->sheets[$sh]['rows'][$rr][$cc] = ['v' => '', 't' => 'e', 's' => $anchor['s']];
                    } elseif (($this->sheets[$sh]['rows'][$rr][$cc]['v'] ?? '') === '') {
                        $this->sheets[$sh]['rows'][$rr][$cc]['s'] = $anchor['s'];
                    }
                }
            }
            if ($c2 > $this->sheets[$sh]['maxcol']) $this->sheets[$sh]['maxcol'] = $c2;
        }
        $this->sheets[$sh]['merge'][] = [$r1, $c1, $r2, $c2];
    }

    public function width($sh, $c, $px) {
        $sh = (int)$sh; $c = (int)$c;
        if (!isset($this->sheets[$sh]) || $c < 1 || $c > self::MAX_COL) return;
        $this->sheets[$sh]['widths'][$c] = max(4, min(200, round(((float)$px) / 7, 2)));
    }

    public static function col($c) {
        $c = (int)$c;
        $s = '';
        while ($c > 0) { $m = ($c - 1) % 26; $s = chr(65 + $m) . $s; $c = (int)(($c - 1) / 26); }
        return $s;
    }

    // Bersihkan string agar 100% aman untuk XML 1.0 + Excel. Tidak pernah gagal (selalu string).
    public static function clean($v) {
        $v = (string)$v;
        if ($v === '') return '';
        // 1. Pastikan UTF-8 valid. Data dari koneksi latin1 bisa berupa byte Windows-1252.
        if (!preg_match('//u', $v)) {
            if (function_exists('mb_convert_encoding')) {
                $v = @mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
            } else {
                $v = @utf8_encode($v);
            }
            if (!is_string($v)) return '';
        }
        // 2. Buang karakter terlarang XML 1.0. \t \n \r tetap boleh.
        $v = @preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v);
        if (!is_string($v)) return '';
        // 3. Buang DEL + kontrol C1 (U+007F-U+009F): legal di XML tapi tak terlihat
        //    dan bikin validator ketat Excel minta repair.
        $v = @preg_replace('/[\x{7F}-\x{9F}]/u', '', $v);
        if (!is_string($v)) return '';
        // 4. Batas panjang sel Excel (32767). Potong aman multibyte.
        if (strlen($v) > self::MAX_STR) {
            if (function_exists('mb_substr')) $v = mb_substr($v, 0, self::MAX_STR, 'UTF-8');
            else {
                $cut = @preg_replace('/^(.{0,' . self::MAX_STR . '}).*$/us', '$1', $v);
                $v = is_string($cut) ? $cut : '';
            }
        }
        return $v;
    }

    public static function esc($v) {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string)$v);
    }

    private static function numStr($v) {
        if (is_int($v)) return (string)$v;
        $f = (float)$v;
        if (!is_finite($f)) return '0';
        $s = sprintf('%.10F', $f);
        if (strpos($s, '.') !== false) $s = rtrim(rtrim($s, '0'), '.');
        if ($s === '' || $s === '-' || $s === '-0') $s = '0';
        return $s;
    }

    private function sharedIdx($str) {
        if (isset($this->strmap[$str])) return $this->strmap[$str];
        $idx = count($this->strings);
        $this->strings[] = $str;
        $this->strmap[$str] = $idx;
        return $idx;
    }

    private function numId($num) {
        return $num === 'int' ? 164 : ($num === 'dec2' ? 165 : ($num === 'rp' ? 166 : ($num === 'rp2' ? 167 : 0)));
    }

    private function usedNums() {
        $u = [];
        foreach ($this->xfs as $x) if (!empty($x[7])) $u[$x[7]] = true;
        return $u;
    }

    private function stylesXml() {
        $fonts = '';
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        $borders = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $fxfs = '';
        $fid = 0; $lid = 2; $bid = 1;
        $fmap = []; $lmap = []; $bmap = [];
        foreach ($this->xfs as $x) {
            list($b, $sz, $color, $fill, $border, $align, $wrap, $num) = $x;
            $fsig = "$b|$sz|$color";
            if (!isset($fmap[$fsig])) {
                $fmap[$fsig] = $fid++;
                $fonts .= '<font>' . ($b ? '<b/>' : '') . '<sz val="' . $sz . '"/><color rgb="FF' . $color . '"/><name val="Calibri"/></font>';
            }
            if ($fill !== '') {
                if (!isset($lmap[$fill])) {
                    $lmap[$fill] = $lid++;
                    $fills .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $fill . '"/><bgColor indexed="64"/></patternFill></fill>';
                }
            }
            if ($border && !isset($bmap['t'])) {
                $bmap['t'] = $bid++;
                $borders .= '<border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border>';
            }
            $fi = $fmap[$fsig];
            $li = ($fill !== '') ? $lmap[$fill] : 0;
            $bi = $border ? $bmap['t'] : 0;
            $al = '<alignment vertical="center"' . ($align !== '' ? ' horizontal="' . $align . '"' : '') . ($wrap ? ' wrapText="1"' : '') . '/>';
            $fxfs .= '<xf numFmtId="' . $this->numId($num) . '" fontId="' . $fi . '" fillId="' . $li . '" borderId="' . $bi . '" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $al . '</xf>';
        }
        $used = $this->usedNums();
        $nf = '';
        if (isset($used['int'])) $nf .= '<numFmt numFmtId="164" formatCode="#,##0"/>';
        if (isset($used['dec2'])) $nf .= '<numFmt numFmtId="165" formatCode="#,##0.00"/>';
        if (isset($used['rp'])) $nf .= '<numFmt numFmtId="166" formatCode="&quot;Rp&quot;#,##0"/>';
        if (isset($used['rp2'])) $nf .= '<numFmt numFmtId="167" formatCode="&quot;Rp&quot;#,##0.00"/>';
        $nfc = count($used);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="' . $nfc . '">' . $nf . '</numFmts>'
            . '<fonts count="' . $fid . '">' . $fonts . '</fonts>'
            . '<fills count="' . $lid . '">' . $fills . '</fills>'
            . '<borders count="' . $bid . '">' . $borders . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . '">' . $fxfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sharedStringsXml() {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($this->strings) . '" uniqueCount="' . count($this->strings) . '">';
        foreach ($this->strings as $s) {
            $x .= '<si><t xml:space="preserve">' . self::esc($s) . '</t></si>';
        }
        $x .= '</sst>';
        return $x;
    }

    private function sheetXml($sh) {
        $maxcol = max(1, (int)$sh['maxcol']);
        ksort($sh['rows']);
        $maxrow = 1;
        foreach ($sh['rows'] as $r => $_) if ($r > $maxrow) $maxrow = $r;
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . self::col($maxcol) . $maxrow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>';
        if (!empty($sh['widths'])) {
            $x .= '<cols>';
            ksort($sh['widths']);
            foreach ($sh['widths'] as $c => $w) $x .= '<col min="' . (int)$c . '" max="' . (int)$c . '" width="' . $w . '" customWidth="1"/>';
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach ($sh['rows'] as $r => $cols) {
            $x .= '<row r="' . (int)$r . '">';
            ksort($cols);
            foreach ($cols as $c => $cell) {
                $ref = self::col($c) . (int)$r;
                if ($cell['t'] === 'n') {
                    $x .= '<c r="' . $ref . '" s="' . (int)$cell['s'] . '"><v>' . $cell['v'] . '</v></c>';
                } elseif ($cell['t'] === 's') {
                    $x .= '<c r="' . $ref . '" s="' . (int)$cell['s'] . '" t="s"><v>' . (int)$cell['v'] . '</v></c>';
                } else {
                    $x .= '<c r="' . $ref . '" s="' . (int)$cell['s'] . '"/>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (!empty($sh['merge'])) {
            $x .= '<mergeCells count="' . count($sh['merge']) . '">';
            foreach ($sh['merge'] as $m) $x .= '<mergeCell ref="' . self::col($m[1]) . $m[0] . ':' . self::col($m[3]) . $m[2] . '"/>';
            $x .= '</mergeCells>';
        }
        $x .= '</worksheet>';
        return $x;
    }

    // Perkiraan lebar TAMPILAN satu sel (satuan kolom Excel).
    // Memperhitungkan: format angka (Rp/ribuan/desimal), ukuran font sel,
    // dan bold — karena kolom Excel diukur dgn font normal 11pt.
    private function needFor($cell) {
        if (!is_array($cell) || ($cell['t'] ?? '') === 'e') return null;
        $sty = $this->xfs[$cell['s']] ?? null;
        $sz = $sty ? (int)$sty[1] : 11;
        $b = $sty ? !empty($sty[0]) : false;
        if ($cell['t'] === 'n') {
            $digits = strlen(preg_replace('/[^0-9]/', '', (string)$cell['v']));
            if ($digits === 0) $digits = 1;
            $commas = (int)floor(($digits - 1) / 3);
            $num = $sty ? ($sty[7] ?? null) : null;
            $suffix = ($num === 'dec2') ? 3 : (($num === 'rp') ? 2 : (($num === 'rp2') ? 5 : 0));
            $len = $digits + $commas + $suffix;
        } else {
            $str = ($cell['t'] === 's') ? ($this->strings[(int)$cell['v']] ?? '') : (string)($cell['v'] ?? '');
            $mlen = function_exists('mb_strlen') ? mb_strlen($str, 'UTF-8') : strlen($str);
            if ($mlen === 0) return null;
            $len = min($mlen, 100);
        }
        return ($len * 1.02 * max(0.8, $sz / 11) + 2) * ($b ? 1.15 : 1.1);
    }

    // Lebar kolom ngepas isi (seperti tabel HTML).
    // Pass 1: semua sel KECUALI sel non-anchor di dalam merge, dan anchor
    // merge LEBAR (>4 kolom, mis. judul/section — biar kolom No tidak melar).
    // Pass 2: anchor merge horizontal 1 baris (mis. footer) — bila gabungan
    // kolom kurang lebar, selisihnya ditambahkan ke kolom terakhir rentang.
    // $max = batas atas, $min = batas bawah.
    public function autofit($sh, $max = 60, $min = 10) {
        $sh = (int)$sh;
        if (!isset($this->sheets[$sh])) return;
        $covered = []; // "r|c" sel non-anchor dalam merge -> lewati
        $spanAnchor = []; // "r|c" anchor merge multi-kolom -> lewati di pass 1 (diurus pass 2)
        foreach ($this->sheets[$sh]['merge'] as $m) {
            for ($r = $m[0]; $r <= $m[2]; $r++) {
                for ($c = $m[1]; $c <= $m[3]; $c++) {
                    if ($r === $m[0] && $c === $m[1]) {
                        if ($m[3] > $m[1] || $m[2] > $m[0]) $spanAnchor["$r|$c"] = true;
                    } else {
                        $covered["$r|$c"] = true;
                    }
                }
            }
        }
        $w = [];
        foreach ($this->sheets[$sh]['rows'] as $r => $cols) {
            foreach ($cols as $c => $cell) {
                if (isset($covered["$r|$c"]) || isset($spanAnchor["$r|$c"])) continue;
                $need = $this->needFor($cell);
                if ($need === null) continue;
                if (!isset($w[$c]) || $need > $w[$c]) $w[$c] = $need;
            }
        }
        foreach ($w as $c => $need) $this->sheets[$sh]['widths'][$c] = max($min, min($max, round($need, 1)));
        foreach ($this->sheets[$sh]['merge'] as $m) {
            if ($m[0] !== $m[2]) continue; // hanya merge horizontal 1 baris
            $anchor = $this->sheets[$sh]['rows'][$m[0]][$m[1]] ?? null;
            // Hanya angka yang butuh pelebaran (teks tidak jadi ######, melainkan overflow).
            if (!is_array($anchor) || ($anchor['t'] ?? '') !== 'n') continue;
            $need = $this->needFor($anchor);
            if ($need === null) continue;
            $total = 0;
            for ($c = $m[1]; $c <= $m[3]; $c++) $total += $this->sheets[$sh]['widths'][$c] ?? $min;
            if ($need > $total) {
                $lc = $m[3];
                $this->sheets[$sh]['widths'][$lc] = round(max($min, min($max, ($this->sheets[$sh]['widths'][$lc] ?? $min) + ($need - $total))), 1);
            }
        }
    }

    public function save($path) {
        if (!class_exists('ZipArchive')) return false;
        if (!$this->build($path)) return false;
        return $this->verify($path);
    }

    private function build($tmp) {
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);
        $n = count($this->sheets);
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $wbr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $sh) {
            $sid = $i + 1;
            $wb .= '<sheet name="' . self::esc($sh['name']) . '" sheetId="' . $sid . '" r:id="rId' . $sid . '"/>';
            $wbr .= '<Relationship Id="rId' . $sid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sid . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $sid . '.xml', $this->sheetXml($sh));
        }
        $ridStyles = $n + 1;
        $ridShared = $n + 2;
        $wbr .= '<Relationship Id="rId' . $ridStyles . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId' . $ridShared . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>';
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $i => $sh) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);
        $wb .= '</sheets><calcPr calcId="124519" fullCalcOnLoad="1"/></workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbr);
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStringsXml());
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $zip->addFromString('docProps/core.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>payroll</dc:creator><cp:lastModifiedBy>payroll</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>');
        $zip->addFromString('docProps/app.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>payroll</Application>'
            . '</Properties>');
        $zip->close();
        return true;
    }

    // Verifikasi akhir: buka ulang zip, pastikan semua XML well-formed.
    private function verify($path) {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return false;
        $need = ['[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/sharedStrings.xml'];
        for ($i = 1; $i <= count($this->sheets); $i++) $need[] = 'xl/worksheets/sheet' . $i . '.xml';
        foreach ($need as $name) {
            $raw = $zip->getFromName($name);
            if ($raw === false) { $zip->close(); return false; }
            $prev = libxml_use_internal_errors(true);
            $ok = simplexml_load_string($raw) !== false;
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if (!$ok) { $zip->close(); return false; }
        }
        $zip->close();
        return true;
    }

    public function send($filename) {
        if (!class_exists('ZipArchive')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
            echo 'Export Excel butuh extension php_zip. Buka php.ini, hapus titik-koma di depan extension=zip, lalu restart Apache.';
            return;
        }
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if (!$this->build($tmp) || !$this->verify($tmp)) {
            @unlink($tmp);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Gagal membuat file Excel (validasi internal gagal). Coba lagi atau hubungi admin.';
            return;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
