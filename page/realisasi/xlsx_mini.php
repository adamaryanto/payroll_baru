<?php
// Writer .xlsx minimal multi-sheet tanpa dependensi (butuh ext ZipArchive).
// Mendukung: teks/angka, bold/ukuran/warna font, fill, border tipis, align,
// merge, lebar kolom, format angka Indonesia (#.##0 / #.##0,00 / Rp...).

class MiniXlsx {
    private $sheets = [];
    private $xfs = [];
    private $xfmap = [];

    // $o: b(bool) sz(int) color(hex tanpa #) fill(hex tanpa #) border(bool)
    //     align(left|center|right) wrap(bool) num(null|int|dec2|rp|rp2)
    public function style($o = []) {
        $b = !empty($o['b']);
        $sz = isset($o['sz']) ? (int)$o['sz'] : 11;
        $color = isset($o['color']) ? strtoupper($o['color']) : '000000';
        $fill = isset($o['fill']) ? strtoupper($o['fill']) : '';
        $border = !empty($o['border']);
        $align = $o['align'] ?? '';
        $wrap = !empty($o['wrap']);
        $num = $o['num'] ?? null;
        $sig = json_encode([$b, $sz, $color, $fill, $border, $align, $wrap, $num]);
        if (isset($this->xfmap[$sig])) return $this->xfmap[$sig];
        $idx = count($this->xfs);
        $this->xfs[] = [$b, $sz, $color, $fill, $border, $align, $wrap, $num];
        $this->xfmap[$sig] = $idx;
        return $idx;
    }

    public function sheet($name) {
        $this->sheets[] = ['name' => $name, 'rows' => [], 'merge' => [], 'widths' => [], 'maxcol' => 0];
        return count($this->sheets) - 1;
    }

    // $v string|number|null, $s style idx. Angka otomatis jadi tipe n.
    public function cell($sh, $r, $c, $v, $s = 0) {
        if ($v === null) $v = '';
        if (is_float($v) && !is_finite($v)) $v = '';
        $t = (is_int($v) || is_float($v)) ? 'n' : 's';
        $this->sheets[$sh]['rows'][(int)$r][(int)$c] = ['v' => $v, 't' => $t, 's' => (int)$s];
        if ((int)$c > $this->sheets[$sh]['maxcol']) $this->sheets[$sh]['maxcol'] = (int)$c;
    }

    public function merge($sh, $r1, $c1, $r2, $c2) {
        $this->sheets[$sh]['merge'][] = [$r1, $c1, $r2, $c2];
    }

    public function width($sh, $c, $px) {
        // 1 unit lebar kolom Excel = ~7px (Calibri 11) — sama seperti render col HTML sebelumnya.
        $this->sheets[$sh]['widths'][(int)$c] = round(((float)$px) / 7, 2);
    }

    public static function col($c) {
        $s = '';
        while ($c > 0) { $m = ($c - 1) % 26; $s = chr(65 + $m) . $s; $c = (int)(($c - 1) / 26); }
        return $s;
    }

    private static function esc($v) {
        $v = (string)$v;
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
        // Koneksi DB latin1 bisa mengembalikan byte non-UTF8 (nama karyawan) ->
        // XML wajib UTF-8 valid, kalau tidak Excel minta repair. Konversi dulu.
        if (function_exists('mb_check_encoding') && !mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
        }
        // XML 1.0 melarang C1 controls (U+007F-U+009F): bikin Excel minta repair.
        $v = preg_replace('/[\x7F-\x9F]/u', '', $v);
        return htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function numId($num) {
        return $num === 'int' ? 164 : ($num === 'dec2' ? 165 : ($num === 'rp' ? 166 : ($num === 'rp2' ? 167 : 0)));
    }

    private function stylesXml() {
        $fonts = '';
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        $borders = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $fxfs = '';
        $fid = 0; $lid = 2; $bid = 1;
        $fmap = []; $lmap = []; $bmap = [];
        foreach ($this->xfs as $i => $x) {
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
            $al = '';
            if ($align !== '' || $wrap) {
                $al = '<alignment vertical="center"' . ($align !== '' ? ' horizontal="' . $align . '"' : '') . ($wrap ? ' wrapText="1"' : '') . '/>';
            } else {
                $al = '<alignment vertical="center"/>';
            }
            $fxfs .= '<xf numFmtId="' . $this->numId($num) . '" fontId="' . $fi . '" fillId="' . $li . '" borderId="' . $bi . '" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $al . '</xf>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="4">'
            . '<numFmt numFmtId="164" formatCode="#,##0"/>'
            . '<numFmt numFmtId="165" formatCode="#,##0.00"/>'
            . '<numFmt numFmtId="166" formatCode="&quot;Rp&quot;#,##0"/>'
            . '<numFmt numFmtId="167" formatCode="&quot;Rp&quot;#,##0.00"/>'
            . '</numFmts>'
            . '<fonts count="' . $fid . '">' . $fonts . '</fonts>'
            . '<fills count="' . $lid . '">' . $fills . '</fills>'
            . '<borders count="' . $bid . '">' . $borders . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . '">' . $fxfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sheetXml($sh) {
        $maxcol = max(1, $sh['maxcol']);
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
            foreach ($sh['widths'] as $c => $w) $x .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" customWidth="1"/>';
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach ($sh['rows'] as $r => $cols) {
            $x .= '<row r="' . $r . '">';
            ksort($cols);
            foreach ($cols as $c => $cell) {
                $ref = self::col($c) . $r;
                if ($cell['t'] === 'n') {
                    $x .= '<c r="' . $ref . '" s="' . $cell['s'] . '"><v>' . $cell['v'] . '</v></c>';
                } else {
                    $x .= '<c r="' . $ref . '" s="' . $cell['s'] . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc($cell['v']) . '</t></is></c>';
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

    // Lebar kolom ikut isi (seperti tabel HTML): dihitung dari teks terpanjang,
    // baris merge (judul/section) dilewati. $max = batas atas, $min = batas bawah.
    public function autofit($sh, $max = 55, $min = 12) {
        $skip = [];
        foreach ($this->sheets[$sh]['merge'] as $m) for ($r = $m[0]; $r <= $m[2]; $r++) $skip[$r] = true;
        $w = [];
        foreach ($this->sheets[$sh]['rows'] as $r => $cols) {
            if (isset($skip[$r])) continue;
            foreach ($cols as $c => $cell) {
                $len = ($cell['t'] === 'n') ? strlen((string)(int)round((float)$cell['v'])) : mb_strlen((string)$cell['v']);
                $need = $len * 1.5 + 3;
                if (!isset($w[$c]) || $need > $w[$c]) $w[$c] = $need;
            }
        }
        foreach ($w as $c => $need) $this->sheets[$sh]['widths'][$c] = max($min, min($max, round($need, 1)));
    }

    public function save($path) {
        if (!class_exists('ZipArchive')) return false;
        if (!$this->build($path)) return false;
        return true;
    }

    private function build($tmp) {
        $this->style(); // pastikan xf default idx 0 ada
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { echo 'Gagal membuat Excel.'; return false; }
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView xWindow="0" yWindow="0" windowWidth="16384" windowHeight="8192"/></bookViews><sheets>';
        $wbr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $sh) {
            $n = $i + 1;
            $wb .= '<sheet name="' . self::esc($sh['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $wbr .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', $this->sheetXml($sh));
        }
        // Daftarkan content-type tiap sheet + docProps.
        // PENTING: urutan/format mengikuti file buatan Excel (terbukti lolos validasi ketat Excel 16):
        // Override sheet tepat setelah workbook (sebelum styles), Default bin di depan,
        // dan newline setelah deklarasi XML. Urutan lain bikin Excel minta repair.
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="bin" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.printerSettings"/>'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        foreach ($this->sheets as $i => $sh) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);
        $wb .= '</sheets></workbook>';
        $wbr .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $zip->addFromString('xl/workbook.xml', $wb);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbr);
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
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

    public function send($filename) {
        if (!class_exists('ZipArchive')) {
            echo 'Export Excel butuh extension php_zip. Buka php.ini, hapus titik-koma di depan extension=zip, lalu restart Apache/XAMPP.';
            return;
        }
        // Bersihkan sisa output (warning/spasi/BOM): 1 byte saja merusak zip -> Excel minta repair.
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if (!$this->build($tmp)) return;
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0');
        readfile($tmp);
        unlink($tmp);
        exit;
    }
}
