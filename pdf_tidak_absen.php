<?php
include "koneksi.php";

$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));

if (!function_exists('mangkir_tgl_aktif')) {
	function mangkir_tgl_aktif($dari, $sampai, $tglYmd) {
		if (!empty($dari) && strcmp($tglYmd, $dari) < 0) return false;
		if (!empty($sampai) && strcmp($tglYmd, $sampai) > 0) return false;
		return true;
	}
}

// Pastikan tabel pengganti ada (sama seperti di tarik-data.php)
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_ganti (
	id INT AUTO_INCREMENT PRIMARY KEY,
	tgl DATE NOT NULL,
	id_karyawan_absen INT NOT NULL DEFAULT 0,
	id_karyawan_ganti INT NOT NULL DEFAULT 0,
	upah_bayar DOUBLE NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_ganti (tgl, id_karyawan_absen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ===== Logika TIDAK ABSEN — sama persis seperti panel di tarik-data.php =====
$noshow = [];
if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
    $taps = [];
    $qt = $koneksi->query("SELECT userid, tgl, MIN(detail_waktu) AS first_tap FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' GROUP BY userid, tgl");
    if ($qt) while ($rt = $qt->fetch_assoc()) $taps[$rt['tgl'] . '|' . trim((string)$rt['userid'])] = $rt['first_tap'] ?? '';
    $mkMenit = 0; $mkDari = ''; $mkSampai = '';
    $qmD = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_menit'");
    if ($qmD && $qmD->num_rows > 0) {
        $qmV = $koneksi->query("SELECT mangkir_menit, mangkir_dari_tgl, mangkir_sampai_tgl FROM tb_denda LIMIT 1");
        $rmV = $qmV ? $qmV->fetch_assoc() : [];
        $mkMenit = max(0, (int)($rmV['mangkir_menit'] ?? 0));
        $mkDari = trim((string)($rmV['mangkir_dari_tgl'] ?? ''));
        $mkSampai = trim((string)($rmV['mangkir_sampai_tgl'] ?? ''));
    }
    $tgl_aktif = [];
    foreach ($taps as $tk => $tv) { $dd = explode('|', $tk, 2); $tgl_aktif[$dd[0]] = true; }
    ksort($tgl_aktif);
    $rosterShift = [];
    $qr0 = $koneksi->query("SELECT h.tgl_rkk AS tgl, TRIM(k.no_absen) AS pin, j.jam_masuk AS sm, j.jam_keluar AS sk FROM tb_rkk_detail rd JOIN tb_rkk h ON h.id_rkk=rd.id_rkk JOIN ms_karyawan k ON k.id_karyawan=rd.id_karyawan LEFT JOIN tb_jadwal j ON j.id_jadwal=rd.id_jadwal WHERE h.tgl_rkk BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
    if ($qr0) while ($rw0 = $qr0->fetch_assoc()) $rosterShift[$rw0['tgl'] . '|' . trim((string)$rw0['pin'])] = [$rw0['sm'], $rw0['sk']];
    if (count($tgl_aktif) > 0 && count($tgl_aktif) <= 7) {
        $qa = $koneksi->query("SELECT k.id_karyawan, k.no_absen, k.nama_karyawan, o.OS_DHK AS oslbl, d.nama_departmen AS dept, sd.nama_sub_department AS subdept, jm.jam_masuk AS m_masuk, jm.jam_keluar AS m_keluar FROM ms_karyawan k LEFT JOIN ms_os_dhk o ON o.id_os_dhk=k.id_os_dhk LEFT JOIN ms_departmen d ON d.id_departmen=k.id_departmen LEFT JOIN ms_sub_department sd ON sd.id_sub_department=k.id_sub_department LEFT JOIN tb_jadwal jm ON jm.id_jadwal=k.id_jadwal WHERE k.status_karyawan='Aktif' AND k.no_absen!='' AND k.no_absen IS NOT NULL ORDER BY k.nama_karyawan");
        if ($qa) while ($ra = $qa->fetch_assoc()) {
            $pinT = trim((string)$ra['no_absen']);
            foreach (array_keys($tgl_aktif) as $dt) {
                $tkey = $dt . '|' . $pinT;
                $sm = isset($rosterShift[$tkey]) && !empty($rosterShift[$tkey][0]) ? $rosterShift[$tkey][0] : ($ra['m_masuk'] ?? '');
                $sk = isset($rosterShift[$tkey]) && !empty($rosterShift[$tkey][1]) ? $rosterShift[$tkey][1] : ($ra['m_keluar'] ?? '');
                if (!isset($taps[$tkey])) {
                    $exp = '';
                    if ($sm !== '' && $sk !== '') {
                        $sec = strtotime($sk) - strtotime($sm);
                        if ($sec < 0) $sec += 86400;
                        $h = (int)($sec / 3600); $m = (int)(($sec % 3600) / 60);
                        $exp = $h . ' jam' . ($m > 0 ? ' ' . $m . ' mnt' : '') . ' (' . substr($sm, 0, 5) . '-' . substr($sk, 0, 5) . ')';
                    }
                    $noshow[] = ['tgl' => $dt, 'id_karyawan' => $ra['id_karyawan'], 'no_absen' => $ra['no_absen'], 'nama_karyawan' => $ra['nama_karyawan'], 'oslbl' => $ra['oslbl'], 'dept' => $ra['dept'], 'subdept' => $ra['subdept'], 'seharusnya' => $exp, 'ket' => 'Tidak absen - tidak ada tap hari ini'];
                } elseif ($mkMenit > 0 && !empty($taps[$tkey]) && mangkir_tgl_aktif($mkDari, $mkSampai, $dt)) {
                    $ft = substr(trim((string)$taps[$tkey]), 11, 5);
                    if ($sm !== '' && preg_match('/^\d{2}:\d{2}$/', $ft)) {
                        $dl = strtotime("1970-01-01 $ft") - strtotime("1970-01-01 " . substr($sm, 0, 5));
                        if ($dl > 43200) $dl -= 86400;
                        elseif ($dl < -43200) $dl += 86400;
                        $dlm = (int)($dl / 60);
                        if ($dlm > $mkMenit) {
                            $noshow[] = ['tgl' => $dt, 'id_karyawan' => $ra['id_karyawan'], 'no_absen' => $ra['no_absen'], 'nama_karyawan' => $ra['nama_karyawan'], 'oslbl' => $ra['oslbl'], 'dept' => $ra['dept'], 'subdept' => $ra['subdept'], 'seharusnya' => 'telat ' . $dlm . ' mnt (batas ' . $mkMenit . ' mnt)', 'ket' => 'Telat ' . $dlm . ' menit - dihitung mangkir'];
                        }
                    }
                }
            }
        }
    }
}
$ganti_map = [];
if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
    $qg = $koneksi->query("SELECT g.*, k2.nama_karyawan AS nama_ganti FROM tb_ganti g LEFT JOIN ms_karyawan k2 ON k2.id_karyawan=g.id_karyawan_ganti WHERE g.tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
    if ($qg) while ($rg = $qg->fetch_assoc()) $ganti_map[$rg['tgl'] . '|' . $rg['id_karyawan_absen']] = $rg;
}

usort($noshow, function($a, $b) {
    $c = strcmp($a['tgl'], $b['tgl']);
    return $c !== 0 ? $c : strcmp($a['nama_karyawan'], $b['nama_karyawan']);
});
// Pecah per 25 baris agar render PDF per halaman kecil-kecil (anti macet untuk ratusan baris)
$chunks = array_chunk($noshow, 25);
if (empty($chunks)) $chunks = array(array());
$total_pdf_hal = count($chunks);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Karyawan Tidak Absen</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; font-size: 10px; color: #333; margin: 0; padding: 0; }
        #content-to-pdf { padding: 10px; background: #fff; }
        .pdf-chunk { background: #fff; margin-bottom: 12px; }
        .pdf-chunk table { margin-top: 0; }
        .header { text-align: center; margin-bottom: 15px; }
        .header h2 { margin: 0; font-size: 15px; font-weight: 700; color: #1e3a8a; }
        .header p { margin: 3px 0 0 0; color: #666; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; color: #374151; font-weight: 700; border: 1px solid #d1d5db; padding: 5px 3px; text-transform: uppercase; font-size: 8px; }
        td { border: 1px solid #e5e7eb; padding: 5px 3px; vertical-align: middle; font-size: 9px; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .text-center { text-align: center; }
        .text-bold { font-weight: 600; }

        #loader {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.95);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2563eb;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        window.onload = function() {
            var filename = 'Laporan_Tidak_Absen_' + '<?= htmlspecialchars($TanggalAwal) ?>_to_<?= htmlspecialchars($TanggalAkhir) ?>' + '.pdf';
            var loaderText = document.getElementById('loader-text');

            function finishOk(pdf) {
                pdf.save(filename);
                document.getElementById('loader').style.display = 'none';
                document.body.innerHTML = '<div style="text-align: center; margin-top: 150px; font-family: \'Inter\', sans-serif;"><h2 style="color: #047857; font-size: 24px;">✓ Download Selesai</h2><p style="color: #666; font-size: 14px; margin-top: 10px;">Laporan PDF berhasil diunduh. Halaman ini akan tertutup otomatis.</p></div>';
                setTimeout(function() { window.close(); }, 1500);
            }
            function finishErr(err) {
                console.error(err);
                document.getElementById('loader').style.display = 'none';
                alert('Gagal membuat PDF. Silakan coba lagi.');
            }

            try {
                var pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', orientation: 'landscape' });
                var pageW = 297, pageH = 210, margin = 10;
                var maxW = pageW - margin * 2, maxH = pageH - margin * 2;
                var chunks = Array.prototype.slice.call(document.querySelectorAll('.pdf-chunk'));
                var i = 0;

                function renderNext() {
                    if (i >= chunks.length) { finishOk(pdf); return; }
                    loaderText.textContent = 'Membuat PDF halaman ' + (i + 1) + ' dari ' + chunks.length + '...';
                    // jeda biar spinner & teks sempat tampil
                    setTimeout(function() {
                        window.html2canvas(chunks[i], { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(function(canvas) {
                            try {
                                var img = canvas.toDataURL('image/jpeg', 0.95);
                                var w = maxW;
                                var h = canvas.height * w / canvas.width;
                                if (h > maxH) { h = maxH; w = canvas.width * h / canvas.height; }
                                if (i > 0) pdf.addPage();
                                pdf.addImage(img, 'JPEG', (pageW - w) / 2, margin, w, h);
                                pdf.setFontSize(8);
                                pdf.setTextColor(120);
                                pdf.text('Hal ' + (i + 1) + ' / ' + chunks.length, pageW - margin - 20, pageH - 5);
                                i++;
                                renderNext();
                            } catch (e) { finishErr(e); }
                        }).catch(finishErr);
                    }, 30);
                }
                renderNext();
            } catch (e) { finishErr(e); }
        }
    </script>
</head>
<body>
    <div id="loader">
        <div class="spinner"></div>
        <p id="loader-text" style="margin-top: 15px; font-weight: bold; color: #1e3a8a; font-family: 'Inter', sans-serif;">Menyiapkan PDF...</p>
    </div>

    <div id="content-to-pdf">
        <?php
        $no = 1;
        $ci = 0;
        foreach ($chunks as $chunk_rows) :
            $ci++;
        ?>
        <div class="pdf-chunk">
            <?php if ($ci === 1) : ?>
            <div class="header">
                <h2>LAPORAN KARYAWAN TIDAK ABSEN / TIDAK MASUK</h2>
                <p>Tanggal: <?php echo date('d-m-Y', strtotime($TanggalAwal)) . ' s/d ' . date('d-m-Y', strtotime($TanggalAkhir)); ?> | Unduh: <?php echo date('d-m-Y H:i'); ?> | Total: <?php echo count($noshow); ?> orang</p>
            </div>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th width="3%" class="text-center">No</th>
                        <th width="8%" class="text-center">Tanggal</th>
                        <th width="7%" class="text-center">No. Absen</th>
                        <th width="16%">Nama Karyawan</th>
                        <th width="12%">Departemen</th>
                        <th width="10%">Sub Bagian</th>
                        <th width="7%" class="text-center">OS/DHK</th>
                        <th width="12%">Seharusnya</th>
                        <th width="13%">Keterangan</th>
                        <th width="12%">Digantikan Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($chunk_rows as $ns) :
                        $gkey = $ns['tgl'] . '|' . $ns['id_karyawan'];
                        $gex = $ganti_map[$gkey] ?? null;
                        $nama_ganti = $gex ? ($gex['nama_ganti'] ?: '-') : '-';
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="text-center"><?php echo date('d-m-Y', strtotime($ns['tgl'])); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($ns['no_absen']); ?></td>
                            <td class="text-bold"><?php echo htmlspecialchars(strtoupper($ns['nama_karyawan'])); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper($ns['dept'] ?: '-')); ?></td>
                            <td><?php echo htmlspecialchars($ns['subdept'] ?: '-'); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($ns['oslbl'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($ns['seharusnya'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($ns['ket'] ?? '-'); ?></td>
                            <td class="text-bold"><?php echo htmlspecialchars(strtoupper($nama_ganti)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($chunk_rows)) : ?>
                        <tr><td colspan="10" class="text-center text-bold">TIDAK ADA DATA</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
