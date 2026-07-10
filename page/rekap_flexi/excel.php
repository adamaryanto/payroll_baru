<?php
include "../../koneksi.php";

$TanggalAcuan = $_GET['tanggal_acuan'] ?? date('Y-m-d');
$SearchName = $_GET['search_name'] ?? '';

// Calculate start and end of week based on TanggalAcuan
$ts = strtotime($TanggalAcuan);
$start_week = date('Y-m-d', strtotime('monday this week', $ts));
$end_week = date('Y-m-d', strtotime('sunday this week', $ts));

// Calculate start and end of month based on TanggalAcuan
$start_month = date('Y-m-01', $ts);
$end_month = date('Y-m-t', $ts);

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Rekap_Target_Jam_Flexi_" . $TanggalAcuan . ".xls");
echo "<meta charset='UTF-8'>";

// Helper to calculate actual hours worked based on scan records
if (!function_exists('calculate_actual_hours')) {
    function calculate_actual_hours($koneksi, $PIN, $TanggalAwal, $TanggalAkhir) {
        $TanggalAwalQuery = date('Y-m-d', strtotime('-1 day', strtotime($TanggalAwal)));
        $TanggalAkhirQuery = date('Y-m-d', strtotime('+1 day', strtotime($TanggalAkhir)));
        
        $q = $koneksi->query("SELECT * FROM tb_record WHERE userid = '$PIN' AND tgl BETWEEN '$TanggalAwalQuery' AND '$TanggalAkhirQuery' ORDER BY tgl ASC, detail_waktu ASC");
        $days_log = [];
        while ($r = $q->fetch_assoc()) {
            $date = $r['tgl'];
            $time = substr($r['detail_waktu'], 11, 5);
            $status = $r['status'];
            
            $is_merged = false;
            $prev_date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
            if (isset($days_log[$prev_date]) && !empty($days_log[$prev_date]['masuk'])) {
                $prev_masuk = $days_log[$prev_date]['masuk'];
                $prev_pulang = $days_log[$prev_date]['pulang'] ?? '';
                
                $in_timestamp = strtotime($prev_date . ' ' . $prev_masuk);
                $curr_timestamp = strtotime($date . ' ' . $time);
                $diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
                
                if ($diff_hours > 0 && $diff_hours <= 16) {
                    $is_valid_merge = false;
                    if (empty($prev_pulang)) {
                        $is_valid_merge = true;
                    } else {
                        $prev_pulang_diff = (strtotime($prev_date . ' ' . $prev_pulang) - $in_timestamp) / 3600;
                        if ($prev_pulang_diff <= 16) {
                            $is_valid_merge = true;
                        }
                    }
                    
                    if ($is_valid_merge) {
                        if (empty($prev_pulang) || $time > $prev_pulang) {
                            $days_log[$prev_date]['pulang'] = $time;
                        }
                        $days_log[$prev_date]['raw'][] = $time;
                        $is_merged = true;
                    }
                }
            }
            
            if (!$is_merged) {
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
        }
        
        // Fallback for missing status tags
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
            if ($date >= $TanggalAwal && $date <= $TanggalAkhir) {
                if (!empty($day['masuk']) && !empty($day['pulang'])) {
                    $duration = strtotime($day['pulang']) - strtotime($day['masuk']);
                    if ($duration < 0) {
                        $duration += 86400;
                    }
                    $hours = $duration / 3600;
                    if ($hours > 0) {
                        $total_hours += $hours;
                    }
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
            Minggu Rekap: <?= date('d/m/Y', strtotime($start_week)) ?> s/d <?= date('d/m/Y', strtotime($end_week)) ?> | 
            Bulan Rekap: <?= date('F Y', $ts) ?>
        </th>
    </tr>
    <tr>
        <td colspan="11" style="height:15px;"></td>
    </tr>
</table>

<table border="1" style="border-collapse:collapse;">
    <thead>
        <tr style="background-color:#e5e7eb;">
            <th rowspan="2">No</th>
            <th rowspan="2">No Absen</th>
            <th rowspan="2">Nama Karyawan</th>
            <th rowspan="2">Departemen</th>
            <th rowspan="2">Nama Shift</th>
            <th colspan="3" style="background-color:#dcfce7;">Minggu Ini</th>
            <th colspan="3" style="background-color:#dbeafe;">Bulan Ini</th>
        </tr>
        <tr style="background-color:#f3f4f6;">
            <th style="background-color:#dcfce7;">Target</th>
            <th style="background-color:#dcfce7;">Tercapai</th>
            <th style="background-color:#dcfce7;">Pencapaian</th>
            <th style="background-color:#dbeafe;">Target</th>
            <th style="background-color:#dbeafe;">Tercapai</th>
            <th style="background-color:#dbeafe;">Pencapaian</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $whereClause = "";
        if (!empty($SearchName)) {
            $searchEscaped = $koneksi->real_escape_string($SearchName);
            $whereClause = " AND k.nama_karyawan LIKE '%$searchEscaped%'";
        }

        $q_emp = $koneksi->query("SELECT k.*, j.keterangan as nama_shift, d.nama_departmen
                                 FROM ms_karyawan k
                                 JOIN tb_jadwal j ON k.id_jadwal = j.id_jadwal
                                 LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen
                                 WHERE j.keterangan LIKE '%FLEXI%' $whereClause
                                 ORDER BY k.nama_karyawan ASC");

        if ($q_emp->num_rows == 0) {
            echo "<tr><td colspan='11' align='center'>Tidak ada data ditemukan</td></tr>";
        } else {
            $no = 1;
            while ($row = $q_emp->fetch_assoc()) {
                $PIN = $row['no_absen'];
                $target_w = (float)($row['target_mingguan'] ?? 20.00);
                $target_m = (float)($row['target_bulanan'] ?? 40.00);

                // Calculate actual hours worked
                $actual_hours_week = calculate_actual_hours($koneksi, $PIN, $start_week, $end_week);
                $actual_hours_month = calculate_actual_hours($koneksi, $PIN, $start_month, $end_month);

                // Calculate percentages
                $pct_week = ($target_w > 0) ? round(($actual_hours_week / $target_w) * 100) : 0;
                $pct_month = ($target_m > 0) ? round(($actual_hours_month / $target_m) * 100) : 0;

                $status_week = ($pct_week >= 100) ? "Lolos" : "Kurang";
                $status_month = ($pct_month >= 100) ? "Lolos" : "Kurang";

                echo "<tr>";
                echo "<td align='center'>{$no}</td>";
                echo "<td>'{$PIN}</td>";
                echo "<td>{$row['nama_karyawan']}</td>";
                echo "<td>" . ($row['nama_departmen'] ?? '-') . "</td>";
                echo "<td>{$row['nama_shift']}</td>";
                
                // Weekly
                echo "<td align='center'>{$target_w} Jam</td>";
                echo "<td align='center'>{$actual_hours_week} Jam</td>";
                echo "<td align='center'>{$pct_week}% ({$status_week})</td>";

                // Monthly
                echo "<td align='center'>{$target_m} Jam</td>";
                echo "<td align='center'>{$actual_hours_month} Jam</td>";
                echo "<td align='center'>{$pct_month}% ({$status_month})</td>";
                
                echo "</tr>";
                $no++;
            }
        }
        ?>
    </tbody>
</table>
