<?php
include "koneksi.php";
include_once "device_sync_helper.php";

$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Log_Tidak_Dalam_Rencana_" . $TanggalAwal . "_to_" . $TanggalAkhir . ".xls");
echo "<meta charset='UTF-8'>";

// ===== Logika TIDAK DALAM RENCANA — sama persis seperti panel di tarik-data.php =====
// Tap tercatat di mesin tapi PIN tidak masuk rencana RKK pada rentang tanggal ini.
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
?>

<table border="0">
    <tr>
        <th colspan="8" style="text-align:center; font-size:20px; font-weight:bold;">LAPORAN KARYAWAN TAP DI LUAR RENCANA</th>
    </tr>
    <tr>
        <th colspan="8" style="text-align:center; font-size:16px;">
            Tanggal: <?php echo date('d/m/Y', strtotime($TanggalAwal)) . ' s/d ' . date('d/m/Y', strtotime($TanggalAkhir)); ?>
        </th>
    </tr>
    <tr>
        <td colspan="8" style="height:15px;"></td>
    </tr>
</table>

<table border="1" style="border-collapse:collapse;">
    <tbody>
        <?php
        $grand_karyawan = 0;
        echo "<tr>
                <th style='background:#e5e7eb;'>No</th>
                <th style='background:#e5e7eb;'>TANGGAL</th>
                <th style='background:#e5e7eb;'>NO ABSEN</th>
                <th style='background:#e5e7eb;'>NAMA KARYAWAN</th>
                <th style='background:#e5e7eb;'>OS / BAGIAN</th>
                <th style='background:#e5e7eb;'>TAP MASUK</th>
                <th style='background:#e5e7eb;'>TAP PULANG</th>
                <th style='background:#e5e7eb;'>JML TAP</th>
            </tr>";
        $no = 1;
        foreach ($tdr_rows as $tr) {
            $grand_karyawan++;
            echo "<tr>
                    <td align='center'>$no</td>
                    <td align='center'>" . date('d/m/Y', strtotime($tr['tgl'])) . "</td>
                    <td align='center'>" . htmlspecialchars($tr['pin']) . "</td>
                    <td>" . htmlspecialchars(strtoupper($tr['nama'])) . "</td>
                    <td>" . htmlspecialchars($tr['osdept']) . "</td>
                    <td align='center'>" . htmlspecialchars($tr['masuk'] ?: '-') . "</td>
                    <td align='center'>" . htmlspecialchars(($tr['pulang'] !== '' && $tr['pulang'] !== $tr['masuk']) ? $tr['pulang'] : '-') . "</td>
                    <td align='center'>" . (int)$tr['n'] . "</td>
                </tr>";
            $no++;
        }
        echo "<tr><td colspan='8' style='background:#203764; color:white; font-weight:bold; text-align:right; padding:10px;'>GRAND TOTAL DI LUAR RENCANA ($grand_karyawan Orang)</td></tr>";
        ?>
    </tbody>
</table>
