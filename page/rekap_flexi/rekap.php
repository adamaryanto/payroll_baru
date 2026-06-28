<?php
// Set reference date (defaults to today)
$TanggalAcuan = $_GET['tanggal_acuan'] ?? date('Y-m-d');
$SearchName = $_GET['search_name'] ?? '';

// Calculate start and end of week based on TanggalAcuan
$ts = strtotime($TanggalAcuan);
$start_week = date('Y-m-d', strtotime('monday this week', $ts));
$end_week = date('Y-m-d', strtotime('sunday this week', $ts));

// Calculate start and end of month based on TanggalAcuan
$start_month = date('Y-m-01', $ts);
$end_month = date('Y-m-t', $ts);

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

<div class="container-fluid py-4">
    <!-- Premium Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="h3 font-bold text-gray-800 mb-1">Rekap Jam Kerja Flexi</h1>
            <p class="text-sm text-gray-500">Pantau pemenuhan target jam kerja mingguan dan bulanan karyawan flexible.</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4 rounded-xl overflow-hidden">
        <div class="card-body p-4 bg-light">
            <form method="GET" action="">
                <input type="hidden" name="page" value="rekap_flexi" />
                <div class="row align-items-end">
                    <div class="col-md-4 col-sm-12 mb-3 mb-md-0">
                        <label class="font-bold text-gray-700 text-xs uppercase tracking-wider mb-2 d-block">Pilih Tanggal Acuan:</label>
                        <input type="date" name="tanggal_acuan" value="<?= htmlspecialchars($TanggalAcuan) ?>" class="form-control rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                        <span class="text-xs text-gray-500 mt-1 d-block">Minggu & Bulan rekap dihitung otomatis dari tanggal acuan ini.</span>
                    </div>
                    <div class="col-md-4 col-sm-12 mb-3 mb-md-0">
                        <label class="font-bold text-gray-700 text-xs uppercase tracking-wider mb-2 d-block">Cari Karyawan:</label>
                        <input type="text" name="search_name" value="<?= htmlspecialchars($SearchName) ?>" placeholder="Nama Karyawan..." class="form-control rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <button type="submit" class="btn btn-primary btn-block py-2 font-bold rounded-lg shadow-sm">
                            <i class="fas fa-filter mr-2"></i> FILTER DATA
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card shadow-sm border-0 rounded-xl overflow-hidden">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 font-bold text-gray-700"><i class="fas fa-list mr-2 text-primary"></i>Daftar Pencapaian</h5>
                <span class="text-xs text-gray-500">
                    Minggu: <b><?= date('d/m/Y', strtotime($start_week)) ?> - <?= date('d/m/Y', strtotime($end_week)) ?></b> | 
                    Bulan: <b><?= date('F Y', $ts) ?></b>
                </span>
            </div>
            <a href="page/rekap_flexi/excel.php?tanggal_acuan=<?= urlencode($TanggalAcuan) ?>&search_name=<?= urlencode($SearchName) ?>" target="_blank" class="btn btn-sm btn-success font-bold px-3 py-2 rounded-lg">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-gray-700 table-bordered">
                <thead class="bg-gray-50 border-bottom border-gray-200">
                    <tr>
                        <th rowspan="2" class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center w-[50px]">No</th>
                        <th rowspan="2" class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">No Absen</th>
                        <th rowspan="2" class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">Nama Karyawan</th>
                        <th rowspan="2" class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">Departemen</th>
                        <th rowspan="2" class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">Nama Shift</th>
                        <th colspan="3" class="py-2 px-3 text-xs font-bold text-green-800 uppercase align-middle text-center bg-green-50">Minggu Ini</th>
                        <th colspan="3" class="py-2 px-3 text-xs font-bold text-blue-800 uppercase align-middle text-center bg-blue-50">Bulan Ini</th>
                    </tr>
                    <tr>
                        <th class="py-2 px-2 text-xs font-bold text-gray-600 uppercase text-center bg-green-50 w-[90px]">Target</th>
                        <th class="py-2 px-2 text-xs font-bold text-gray-600 uppercase text-center bg-green-50 w-[90px]">Tercapai</th>
                        <th class="py-2 px-2 text-xs font-bold text-gray-600 uppercase text-center bg-green-50 w-[140px]">Pencapaian</th>
                        <th class="py-2 px-2 text-xs font-bold text-gray-600 uppercase text-center bg-blue-50 w-[90px]">Target</th>
                        <th class="py-2 px-2 text-xs font-bold text-gray-600 uppercase text-center bg-blue-50 w-[90px]">Tercapai</th>
                        <th class="py-2 px-2 text-xs font-bold text-gray-600 uppercase text-center bg-blue-50 w-[140px]">Pencapaian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
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
                        echo "<tr><td colspan='11' class='text-center py-4 text-gray-400 font-medium'>Tidak ada data karyawan flexible ditemukan.</td></tr>";
                    } else {
                        $no = 1;
                        while ($row = $q_emp->fetch_assoc()) {
                            $PIN = $row['no_absen'];
                            $target_w = (float)($row['target_mingguan'] ?? 20.00);
                            $target_m = (float)($row['target_bulanan'] ?? 40.00);

                            // Calculate actual hours worked for current week & current month
                            $actual_hours_week = calculate_actual_hours($koneksi, $PIN, $start_week, $end_week);
                            $actual_hours_month = calculate_actual_hours($koneksi, $PIN, $start_month, $end_month);

                            // Calculate percentage achievements
                            $pct_week = ($target_w > 0) ? round(($actual_hours_week / $target_w) * 100) : 0;
                            $pct_month = ($target_m > 0) ? round(($actual_hours_month / $target_m) * 100) : 0;

                            // Badges & styling for weekly target
                            if ($pct_week >= 100) {
                                $badge_week = '<span class="badge bg-green-100 text-green-800 px-2.5 py-1 rounded font-bold">' . $pct_week . '% (Lolos)</span>';
                            } else {
                                $badge_week = '<span class="badge bg-red-100 text-red-800 px-2.5 py-1 rounded font-bold">' . $pct_week . '% (Kurang)</span>';
                            }

                            // Badges & styling for monthly target
                            if ($pct_month >= 100) {
                                $badge_month = '<span class="badge bg-green-100 text-green-800 px-2.5 py-1 rounded font-bold">' . $pct_month . '% (Lolos)</span>';
                            } else {
                                $badge_month = '<span class="badge bg-red-100 text-red-800 px-2.5 py-1 rounded font-bold">' . $pct_month . '% (Kurang)</span>';
                            }

                            echo "<tr>";
                            echo "<td class='text-center font-bold text-gray-400'>{$no}</td>";
                            echo "<td class='font-semibold text-gray-900'>{$PIN}</td>";
                            echo "<td class='font-semibold text-gray-800'>{$row['nama_karyawan']}</td>";
                            echo "<td>" . ($row['nama_departmen'] ?? '-') . "</td>";
                            echo "<td><span class='badge bg-light text-gray-700 font-medium px-2 py-1 border rounded'>{$row['nama_shift']}</span></td>";
                            
                            // Weekly Columns
                            echo "<td class='text-center font-semibold text-gray-600 bg-green-50/30'>{$target_w} Jam</td>";
                            echo "<td class='text-center font-bold text-gray-800 bg-green-50/30'>{$actual_hours_week} Jam</td>";
                            echo "<td class='text-center bg-green-50/30'>{$badge_week}</td>";

                            // Monthly Columns
                            echo "<td class='text-center font-semibold text-gray-600 bg-blue-50/30'>{$target_m} Jam</td>";
                            echo "<td class='text-center font-bold text-gray-800 bg-blue-50/30'>{$actual_hours_month} Jam</td>";
                            echo "<td class='text-center bg-blue-50/30'>{$badge_month}</td>";
                            
                            echo "</tr>";
                            $no++;
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.rounded-xl {
    border-radius: 0.75rem !important;
}
.bg-gray-50 {
    background-color: #f9fafb !important;
}
.bg-green-50 {
    background-color: #f0fdf4 !important;
}
.bg-blue-50 {
    background-color: #eff6ff !important;
}
.bg-green-50\/30 {
    background-color: rgba(240, 253, 244, 0.4) !important;
}
.bg-blue-50\/30 {
    background-color: rgba(239, 246, 255, 0.4) !important;
}
.divide-gray-100 > tr {
    border-bottom: 1px solid #f3f4f6;
}
.table-hover tbody tr:hover {
    background-color: #f8fafc;
}
</style>
