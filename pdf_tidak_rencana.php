<?php
include "koneksi.php";
include_once "device_sync_helper.php";

$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));

// ===== Logika TIDAK DALAM RENCANA — sama persis seperti panel di tarik-data.php =====
$tdr_rows = [];
if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
    $taps_fl = [];
    $qt2 = $koneksi->query("SELECT TRIM(userid) AS pin, tgl, MIN(detail_waktu) AS ft, MAX(detail_waktu) AS lt, COUNT(*) AS n FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' AND TRIM(userid) <> '' GROUP BY TRIM(userid), tgl");
    if ($qt2) while ($rt2 = $qt2->fetch_assoc()) $taps_fl[$rt2['tgl'] . '|' . $rt2['pin']] = $rt2;
    $roster = [];
    $qr0 = $koneksi->query("SELECT h.tgl_rkk AS tgl, TRIM(k.no_absen) AS pin FROM tb_rkk_detail rd JOIN tb_rkk h ON h.id_rkk=rd.id_rkk JOIN ms_karyawan k ON k.id_karyawan=rd.id_karyawan WHERE h.tgl_rkk BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
    if ($qr0) while ($rw0 = $qr0->fetch_assoc()) $roster[$rw0['tgl'] . '|' . trim((string)$rw0['pin'])] = true;
    $master_pin = [];
    $qm = $koneksi->query("SELECT k.no_absen, k.nama_karyawan, o.OS_DHK AS oslbl, d.nama_departmen AS dept FROM ms_karyawan k LEFT JOIN ms_os_dhk o ON o.id_os_dhk=k.id_os_dhk LEFT JOIN ms_departmen d ON d.id_departmen=k.id_departmen WHERE k.status_karyawan='Aktif'");
    if ($qm) while ($rm = $qm->fetch_assoc()) $master_pin[trim((string)$rm['no_absen'])] = $rm;
    $dev_names = function_exists('get_device_user_names') ? get_device_user_names($koneksi) : [];
    foreach ($taps_fl as $tk => $tv) {
        if (isset($roster[$tk])) continue;
        $dt = $tv['tgl']; $pin = $tv['pin'];
        $em = $master_pin[$pin] ?? null;
        if ($em) {
            $nama = $em['nama_karyawan'];
            $osdept = ($em['oslbl'] ?: '-') . ' / ' . ($em['dept'] ?: '-');
        } else {
            $nm_d = $dev_names[$pin] ?? '';
            $nama = ($nm_d !== '' ? $nm_d : 'Karyawan Tidak Dikenal') . " ($pin)";
            $osdept = '- / -';
        }
        $tdr_rows[] = ['tgl' => $dt, 'nama' => $nama, 'pin' => $pin, 'osdept' => $osdept,
            'masuk' => substr($tv['ft'], 11, 5), 'pulang' => substr($tv['lt'], 11, 5), 'n' => (int)$tv['n']];
    }
    usort($tdr_rows, function($a, $b) { $c = strcmp($a['tgl'], $b['tgl']); return $c !== 0 ? $c : strcmp($a['nama'], $b['nama']); });
}
// Pecah per 25 baris agar render PDF per halaman kecil-kecil (anti macet untuk ratusan baris)
$chunks = array_chunk($tdr_rows, 25);
if (empty($chunks)) $chunks = array(array());
$total_pdf_hal = count($chunks);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Karyawan Tap Di Luar Rencana</title>
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
            var filename = 'Laporan_Tidak_Dalam_Rencana_' + '<?= htmlspecialchars($TanggalAwal) ?>_to_<?= htmlspecialchars($TanggalAkhir) ?>' + '.pdf';
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
                <h2>LAPORAN KARYAWAN TAP DI LUAR RENCANA</h2>
                <p>Tanggal: <?php echo date('d-m-Y', strtotime($TanggalAwal)) . ' s/d ' . date('d-m-Y', strtotime($TanggalAkhir)); ?> | Unduh: <?php echo date('d-m-Y H:i'); ?> | Total: <?php echo count($tdr_rows); ?> orang</p>
            </div>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th width="4%" class="text-center">No</th>
                        <th width="9%" class="text-center">Tanggal</th>
                        <th width="8%" class="text-center">No. Absen</th>
                        <th width="20%">Nama Karyawan</th>
                        <th width="15%">OS / Bagian</th>
                        <th width="9%" class="text-center">Tap Masuk</th>
                        <th width="9%" class="text-center">Tap Pulang</th>
                        <th width="6%" class="text-center">Jml Tap</th>
                        <th width="20%">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($chunk_rows as $tr) :
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="text-center"><?php echo date('d-m-Y', strtotime($tr['tgl'])); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($tr['pin']); ?></td>
                            <td class="text-bold"><?php echo htmlspecialchars(strtoupper($tr['nama'])); ?></td>
                            <td><?php echo htmlspecialchars($tr['osdept']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($tr['masuk'] ?: '-'); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars(($tr['pulang'] !== '' && $tr['pulang'] !== $tr['masuk']) ? $tr['pulang'] : '-'); ?></td>
                            <td class="text-center"><?php echo (int)$tr['n']; ?></td>
                            <td>Tap di luar rencana</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($chunk_rows)) : ?>
                        <tr><td colspan="9" class="text-center text-bold">TIDAK ADA DATA</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
