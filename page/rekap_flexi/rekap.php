<?php
// Set default date range to current week (Monday to Sunday)
$TanggalAwal = $_GET['tanggal_awal'] ?? date('Y-m-d', strtotime('monday this week'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? date('Y-m-d', strtotime('sunday this week'));
$SearchName = $_GET['search_name'] ?? '';

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
            <p class="text-sm text-gray-500">Pantau pemenuhan target jam kerja karyawan flexible (freelancer).</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4 rounded-xl overflow-hidden">
        <div class="card-body p-4 bg-light">
            <form method="GET" action="">
                <input type="hidden" name="page" value="rekap_flexi" />
                <div class="row align-items-end">
                    <div class="col-md-3 col-sm-12 mb-3 mb-md-0">
                        <label class="font-bold text-gray-700 text-xs uppercase tracking-wider mb-2 d-block">Tanggal Awal:</label>
                        <input type="date" name="tanggal_awal" value="<?= htmlspecialchars($TanggalAwal) ?>" class="form-control rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                    </div>
                    <div class="col-md-3 col-sm-12 mb-3 mb-md-0">
                        <label class="font-bold text-gray-700 text-xs uppercase tracking-wider mb-2 d-block">Tanggal Akhir:</label>
                        <input type="date" name="tanggal_akhir" value="<?= htmlspecialchars($TanggalAkhir) ?>" class="form-control rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                    </div>
                    <div class="col-md-3 col-sm-12 mb-3 mb-md-0">
                        <label class="font-bold text-gray-700 text-xs uppercase tracking-wider mb-2 d-block">Cari Karyawan:</label>
                        <input type="text" name="search_name" value="<?= htmlspecialchars($SearchName) ?>" placeholder="Nama Karyawan..." class="form-control rounded-lg border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
                    </div>
                    <div class="col-md-3 col-sm-12">
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
            <h5 class="mb-0 font-bold text-gray-700"><i class="fas fa-list mr-2 text-primary"></i>Daftar Pencapaian</h5>
            <!-- Future Excel Export Link -->
            <a href="page/rekap_flexi/excel.php?tanggal_awal=<?= urlencode($TanggalAwal) ?>&tanggal_akhir=<?= urlencode($TanggalAkhir) ?>&search_name=<?= urlencode($SearchName) ?>" target="_blank" class="btn btn-sm btn-success font-bold px-3 py-2 rounded-lg">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-gray-700">
                <thead class="bg-gray-50 border-bottom border-gray-200">
                    <tr>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center w-[50px]">No</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">No Absen</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">Nama Karyawan</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">Departemen</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle">Nama Shift</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center">Target (W/M)</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center">Target Range Ini</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center">Aktual Kerja</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center">Selisih</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center">Pencapaian</th>
                        <th class="py-3 px-3 text-xs font-bold text-gray-500 uppercase align-middle text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php
                    // Count number of days in the range
                    $days = (strtotime($TanggalAkhir) - strtotime($TanggalAwal)) / 86400 + 1;
                    if ($days <= 0) $days = 1;

                    // Query flexible employees
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
                        echo "<tr><td colspan='11' class='text-center py-4 text-gray-400 font-medium'>Tidak ada data karyawan flexible ditemukan.</td></tr>";
                    } else {
                        $no = 1;
                        while ($row = $q_emp->fetch_assoc()) {
                            $PIN = $row['no_absen'];
                            $target_w = (float)($row['target_mingguan'] ?? 20.00);
                            $target_m = (float)($row['target_bulanan'] ?? 40.00);

                            // Calculate expected target for the date range
                            if ($days <= 10) {
                                $target_hours = round(($target_w / 7) * $days, 1);
                            } else {
                                $target_hours = round(($target_m / 30) * $days, 1);
                            }

                            // Calculate actual hours worked
                            $actual_hours = calculate_actual_hours($koneksi, $PIN, $TanggalAwal, $TanggalAkhir);
                            $diff = round($actual_hours - $target_hours, 1);
                            $percentage = ($target_hours > 0) ? round(($actual_hours / $target_hours) * 100) : 0;

                            // Badges & status styling
                            if ($diff >= 0) {
                                $status_badge = '<span class="badge bg-green-100 text-green-800 px-3 py-1.5 rounded-full font-bold">TERCAPAI</span>';
                                $diff_text = '<span class="text-green-600 font-bold">+' . $diff . ' Jam</span>';
                            } else {
                                $status_badge = '<span class="badge bg-red-100 text-red-800 px-3 py-1.5 rounded-full font-bold">KURANG</span>';
                                $diff_text = '<span class="text-red-600 font-bold">' . $diff . ' Jam</span>';
                            }

                            echo "<tr>";
                            echo "<td class='text-center font-bold text-gray-400'>{$no}</td>";
                            echo "<td class='font-semibold text-gray-900'>{$PIN}</td>";
                            echo "<td class='font-semibold text-gray-800'>{$row['nama_karyawan']}</td>";
                            echo "<td>" . ($row['nama_departmen'] ?? '-') . "</td>";
                            echo "<td><span class='badge bg-blue-50 text-blue-700 font-medium px-2.5 py-1 rounded'>{$row['nama_shift']}</span></td>";
                            echo "<td class='text-center text-xs text-gray-500'>{$target_w} / {$target_m}</td>";
                            echo "<td class='text-center font-bold text-gray-700'>{$target_hours} Jam</td>";
                            echo "<td class='text-center font-bold text-gray-900'>{$actual_hours} Jam</td>";
                            echo "<td class='text-center'>{$diff_text}</td>";
                            echo "<td class='text-center font-extrabold text-blue-600'>{$percentage}%</td>";
                            echo "<td class='text-center'>{$status_badge}</td>";
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
.divide-gray-100 > tr {
    border-bottom: 1px solid #f3f4f6;
}
.table-hover tbody tr:hover {
    background-color: #f8fafc;
}
</style>
