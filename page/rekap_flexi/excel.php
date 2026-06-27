<?php
include "../../koneksi.php";

$TanggalAwal = $_GET['tanggal_awal'] ?? date('Y-m-d', strtotime('monday this week'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? date('Y-m-d', strtotime('sunday this week'));
$SearchName = $_GET['search_name'] ?? '';

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Rekap_Jam_Flexi_" . $TanggalAwal . "_to_" . $TanggalAkhir . ".xls");
echo "<meta charset='UTF-8'>";

// Helper to calculate actual hours worked based on scan records
if (!function_exists('calculate_actual_hours')) {
    function calculate_actual_hours($koneksi, $PIN, $TanggalAwal, $TanggalAkhir) {
        $q = $koneksi->query("SELECT * FROM tb_record WHERE userid = '$PIN' AND tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' ORDER BY tgl ASC, detail_waktu ASC");
        $days_log = [];
        while ($r = $q->fetch_assoc()) {
            $date = $r['tgl'];
            $time = substr($r['detail_waktu'], 11, 5);
            $status = $r['status'];
            if (!isset($days_log[$date])) {
                $days_log[$date] = [
                    'masuk' => '',
                    'pulang' => '',
                    'raw' => []
                ];
            }
            $days_log[$date]['raw'][] = $time;
            if ($status == 0) {
                if ($days_log[$date]['masuk'] == '' || $time < $days_log[$date]['masuk']) {
                    $days_log[$date]['masuk'] = $time;
                }
            } elseif ($status == 1) {
                if ($days_log[$date]['pulang'] == '' || $time > $days_log[$date]['pulang']) {
                    $days_log[$date]['pulang'] = $time;
                }
            }
        }
        
        foreach ($days_log as $date => &$day) {
            if (empty($day['masuk']) && !empty($day['raw'])) {
                sort($day['raw']);
                $day['masuk'] = $day['raw'][0];
                if (count($day['raw']) > 1) {
                    $day['pulang'] = $day['raw'][count($day['raw']) - 1];
                }
            }
        }
        unset($day);
        
        $total_hours = 0;
        foreach ($days_log as $date => $day) {
            if (!empty($day['masuk']) && !empty($day['pulang'])) {
                $duration = strtotime($day['pulang']) - strtotime($day['masuk']);
                if ($duration > 43200) $duration -= 86400;
                elseif ($duration < -43200) $duration += 86400;
                $hours = $duration / 3600;
                if ($hours > 0) {
                    $total_hours += $hours;
                }
            }
        }
        return round($total_hours, 1);
    }
}
?>

<table border="0">
    <tr>
        <th colspan="11" style="text-align:center; font-size:18px; font-weight:bold;">LAPORAN REKAP TARGET JAM KERJA FLEXI</th>
    </tr>
    <tr>
        <th colspan="11" style="text-align:center; font-size:14px;">
            Periode: <?= date('d/m/Y', strtotime($TanggalAwal)) ?> s/d <?= date('d/m/Y', strtotime($TanggalAkhir)) ?>
        </th>
    </tr>
    <tr>
        <td colspan="11" style="height:15px;"></td>
    </tr>
</table>

<table border="1" style="border-collapse:collapse;">
    <thead>
        <tr style="background-color:#e5e7eb;">
            <th>No</th>
            <th>No Absen</th>
            <th>Nama Karyawan</th>
            <th>Departemen</th>
            <th>Nama Shift</th>
            <th>Target (Minggu/Bulan)</th>
            <th>Target Range Ini</th>
            <th>Aktual Kerja</th>
            <th>Selisih</th>
            <th>Pencapaian</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $days = (strtotime($TanggalAkhir) - strtotime($TanggalAwal)) / 86400 + 1;
        if ($days <= 0) $days = 1;

        $whereClause = "";
        if (!empty($SearchName)) {
            $searchEscaped = $koneksi->real_escape_string($SearchName);
            $whereClause = " AND k.nama_karyawan LIKE '%$searchEscaped%'";
        }

        $q_emp = $koneksi->query("SELECT k.*, j.keterangan as nama_shift, d.nama_departmen
                                 FROM ms_karyawan k
                                 JOIN tb_jadwal j ON k.id_jadwal = j.id_jadwal
                                 LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen
                                 WHERE j.keterangan LIKE '%FLEXIBLE%' $whereClause
                                 ORDER BY k.nama_karyawan ASC");

        if ($q_emp->num_rows == 0) {
            echo "<tr><td colspan='11' align='center'>Tidak ada data ditemukan</td></tr>";
        } else {
            $no = 1;
            while ($row = $q_emp->fetch_assoc()) {
                $PIN = $row['no_absen'];
                $target_w = (float)($row['target_mingguan'] ?? 20.00);
                $target_m = (float)($row['target_bulanan'] ?? 40.00);

                if ($days <= 10) {
                    $target_hours = round(($target_w / 7) * $days, 1);
                } else {
                    $target_hours = round(($target_m / 30) * $days, 1);
                }

                $actual_hours = calculate_actual_hours($koneksi, $PIN, $TanggalAwal, $TanggalAkhir);
                $diff = round($actual_hours - $target_hours, 1);
                $percentage = ($target_hours > 0) ? round(($actual_hours / $target_hours) * 100) : 0;

                $status = ($diff >= 0) ? "TERCAPAI" : "KURANG";
                $diff_fmt = ($diff >= 0) ? "+" . $diff . " Jam" : $diff . " Jam";

                echo "<tr>";
                echo "<td align='center'>{$no}</td>";
                echo "<td>'{$PIN}</td>"; // Prefix single quote to force string type in Excel
                echo "<td>{$row['nama_karyawan']}</td>";
                echo "<td>" . ($row['nama_departmen'] ?? '-') . "</td>";
                echo "<td>{$row['nama_shift']}</td>";
                echo "<td align='center'>{$target_w} / {$target_m}</td>";
                echo "<td align='center'>{$target_hours} Jam</td>";
                echo "<td align='center'>{$actual_hours} Jam</td>";
                echo "<td align='center'>{$diff_fmt}</td>";
                echo "<td align='center'>{$percentage}%</td>";
                echo "<td align='center'>{$status}</td>";
                echo "</tr>";
                $no++;
            }
        }
        ?>
    </tbody>
</table>
