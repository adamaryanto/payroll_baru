<?php
include "koneksi.php";
include "page/generate/parse.php";

$tampil = $koneksi->query("SELECT * from tb_mesin");
$data = $tampil->fetch_assoc();
$ipmesin = $data['ip_mesin'] ?? '';
$port = $data['port'] ?? '';

$IP = $_GET['ip'] ?? $ipmesin;
$Key = $_GET['key'] ?? "0";
$Tanggal = $_GET['tanggal'] ?? date('Y-m-d');
if ($IP == "") $IP = "192.168.110.201";
if ($Key == "") $Key = "";

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Log_Tarik_Data_" . $Tanggal . ".xls");
echo "<meta charset='UTF-8'>";

if (!function_exists('get_closest_shift')) {
	function get_closest_shift($koneksi, $time_str) {
		if (empty($time_str) || $time_str == '00:00' || $time_str == '00:00:00') return null;
		
		$q = $koneksi->query("SELECT id_jadwal, keterangan, jam_masuk, jam_keluar, istirahat_masuk, istirahat_keluar FROM tb_jadwal");
		$best_shift = null;
		$min_diff = 9999999;
		
		$check_in_sec = strtotime("1970-01-01 " . $time_str);
		
		while ($row = $q->fetch_assoc()) {
			$shift_sec = strtotime("1970-01-01 " . $row['jam_masuk']);
			
			$diff = abs($check_in_sec - $shift_sec);
			if ($diff > 43200) {
				$diff = 86400 - $diff;
			}
			
			if ($diff < $min_diff) {
				$min_diff = $diff;
				$best_shift = $row;
			}
		}
		return $best_shift;
	}
}

if (!function_exists('is_time_late')) {
	function is_time_late($actual, $expected) {
		if (empty($actual) || empty($expected) || $actual == '00:00:00' || $actual == '00:00') return false;
		$diff = strtotime($actual) - strtotime($expected);
		if ($diff > 43200) $diff -= 86400;
		elseif ($diff < -43200) $diff += 86400;
		return $diff > 0;
	}
}

if (!function_exists('is_time_early')) {
	function is_time_early($actual, $expected) {
		if (empty($actual) || empty($expected) || $actual == '00:00:00' || $actual == '00:00') return false;
		$diff = strtotime($actual) - strtotime($expected);
		if ($diff > 43200) $diff -= 86400;
		elseif ($diff < -43200) $diff += 86400;
		return $diff < 0;
	}
}

// Query Pengaturan Denda Global
$queryDenda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
$dataDenda = $queryDenda->fetch_assoc();
$globalDendaMasuk = $dataDenda['denda_masuk'] ?? 0;
$globalDendaIstirahatKeluar = $dataDenda['denda_istirahat_keluar'] ?? 0;
$globalDendaIstirahatMasuk = $dataDenda['denda_istirahat_masuk'] ?? 0;
$globalDendaPulang = $dataDenda['denda_pulang'] ?? 0;
$globalDendaTidakLengkap = $dataDenda['denda_tidak_lengkap'] ?? 0;

$http_host = $_SERVER['HTTP_HOST'] ?? '';
$is_local = (strpos($http_host, 'localhost') !== false || strpos($http_host, '127.0.0.1') !== false || strpos($http_host, '.test') !== false);

// Skip connecting to private IPs on online servers (reduces connection timeout delay)
$is_private_target = false;
if ($IP === 'localhost' || $IP === '127.0.0.1') {
	$is_private_target = true;
} else {
	$ip_parts = explode('.', $IP);
	if (count($ip_parts) === 4) {
		$first = (int)$ip_parts[0];
		$second = (int)$ip_parts[1];
		if ($first === 10 || ($first === 192 && $second === 168) || ($first === 172 && $second >= 16 && $second <= 31)) {
			$is_private_target = true;
		}
	}
}

$should_connect = true;
if (!$is_local && $is_private_target) {
	$should_connect = false;
}

$Connect = false;
if ($should_connect) {
	$Connect = @fsockopen($IP, $port, $errno, $errstr, 0.3);
}

if ($Connect) {
	$soap_request = "<GetAttLog><ArgComKey xsi:type=\"xsd:integer\">" . $Key . "</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg></GetAttLog>";
	$newLine = "\r\n";
	fputs($Connect, "POST /iWsService HTTP/1.0" . $newLine);
	fputs($Connect, "Content-Type: text/xml" . $newLine);
	fputs($Connect, "Content-Length: " . strlen($soap_request) . $newLine . $newLine);
	fputs($Connect, $soap_request . $newLine);
	$buffer = "";
	while ($Response = fgets($Connect, 1024)) {
		$buffer = $buffer . $Response;
	}
	
	// Parse and save to tb_record (for all pulled records)
	$parsed_data = Parse_Data($buffer, "<GetAttLogResponse>", "</GetAttLogResponse>");
	$parsed_rows = explode("\r\n", $parsed_data);
	
	// Build lookup map for duplicates
	$existing_logs = [];
	$ex_res = $koneksi->query("SELECT userid, detail_waktu FROM tb_record WHERE tgl = '$Tanggal'");
	while ($ex_row = $ex_res->fetch_assoc()) {
		$existing_logs[$ex_row['userid'] . '_' . $ex_row['detail_waktu']] = true;
	}

	$insert_values = [];
	for ($a = 0; $a < count($parsed_rows); $a++) {
		$data_raw = Parse_Data($parsed_rows[$a], "<Row>", "</Row>");
		$PIN = Parse_Data($data_raw, "<PIN>", "</PIN>");
		$DateTime = Parse_Data($data_raw, "<DateTime>", "</DateTime>");
		$Verified = Parse_Data($data_raw, "<Verified>", "</Verified>");
		$Status = Parse_Data($data_raw, "<Status>", "</Status>");

		if ($PIN != '' && $DateTime != '') {
			$key = $PIN . '_' . $DateTime;
			if (!isset($existing_logs[$key])) {
				$data_esc = mysqli_real_escape_string($koneksi, $data_raw);
				$date_only = substr($DateTime, 0, 10);
				$insert_values[] = "('$data_esc','$PIN','$date_only','$Verified','$Status','$DateTime')";
				$existing_logs[$key] = true;
			}
		}
	}
	
	if (!empty($insert_values)) {
		$chunks = array_chunk($insert_values, 100);
		foreach ($chunks as $chunk) {
			$sql = "INSERT INTO tb_record (data,userid,tgl,verifikasi,status,detail_waktu) VALUES " . implode(", ", $chunk);
			$koneksi->query($sql);
		}
	}
}

// Query and group data from tb_record for $Tanggal
$db_records = $koneksi->query("SELECT * FROM tb_record WHERE tgl = '$Tanggal'");
$grouped = [];

while ($row_rec = $db_records->fetch_assoc()) {
	$PIN = $row_rec['userid'];
	$DateTime = $row_rec['detail_waktu'];
	$Verified = $row_rec['verifikasi'];
	$Status = $row_rec['status'];

	$time = substr($DateTime, 11, 5); // "08:12"
	$key = $PIN . '_' . $Tanggal;
	if (!isset($grouped[$key])) {
		$grouped[$key] = [
			'pin' => $PIN,
			'date' => $Tanggal,
			'masuk' => '',
			'istirahat_keluar' => '',
			'istirahat_masuk' => '',
			'pulang' => '',
			'raw_times' => []
		];
	}
	
	$grouped[$key]['raw_times'][] = [
		'time' => $time,
		'status' => $Status
	];

	if ($Status == 0) {
		if ($grouped[$key]['masuk'] == '' || $time < $grouped[$key]['masuk']) {
			$grouped[$key]['masuk'] = $time;
		}
	} elseif ($Status == 2) {
		if ($grouped[$key]['istirahat_keluar'] == '' || $time < $grouped[$key]['istirahat_keluar']) {
			$grouped[$key]['istirahat_keluar'] = $time;
		}
	} elseif ($Status == 3) {
		if ($grouped[$key]['istirahat_masuk'] == '' || $time < $grouped[$key]['istirahat_masuk']) {
			$grouped[$key]['istirahat_masuk'] = $time;
		}
	} elseif ($Status == 1) {
		if ($grouped[$key]['pulang'] == '' || $time > $grouped[$key]['pulang']) {
			$grouped[$key]['pulang'] = $time;
		}
	}
}

$grouped_by_dept = [];
foreach ($grouped as $key => $row) {
	if (empty($row['masuk']) && !empty($row['raw_times'])) {
		usort($row['raw_times'], function($a, $b) {
			return strcmp($a['time'], $b['time']);
		});
		$row['masuk'] = $row['raw_times'][0]['time'];
		if (count($row['raw_times']) > 1) {
			$row['pulang'] = $row['raw_times'][count($row['raw_times']) - 1]['time'];
		}
	}
	
	$PIN = $row['pin'];
	
	// Fetch details
	$empQuery = $koneksi->query("SELECT k.nama_karyawan, k.upah, k.id_departmen, d.nama_departmen,
									   k.id_os_dhk, o.OS_DHK as label_os,
									   k.id_golongan, g.golongan as label_gol,
									   k.id_sub_department, s.nama_sub_department as posisi
								FROM ms_karyawan k 
								LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen 
								LEFT JOIN ms_os_dhk o ON k.id_os_dhk = o.id_os_dhk
								LEFT JOIN ms_golongan g ON k.id_golongan = g.id_golongan
								LEFT JOIN ms_sub_department s ON k.id_sub_department = s.id_sub_department
								WHERE k.no_absen = '$PIN' LIMIT 1");
	$emp = $empQuery->fetch_assoc();
	
	$row['nama_karyawan'] = $emp['nama_karyawan'] ?? 'Karyawan Tidak Dikenal';
	$row['id_departmen'] = $emp['id_departmen'] ?? 0;
	$row['nama_departmen'] = $emp['nama_departmen'] ?? 'LAIN-LAIN';
	$row['label_os'] = $emp['label_os'] ?? '-';
	$row['label_gol'] = $emp['label_gol'] ?? '-';
	$row['posisi'] = $emp['posisi'] ?? '-';
	$row['upah'] = $emp['upah'] ?? 0;
	
	$dept_id = $row['id_departmen'];
	if (!isset($grouped_by_dept[$dept_id])) {
		$grouped_by_dept[$dept_id] = [
			'nama_departmen' => $row['nama_departmen'],
			'rows' => []
		];
	}
	$grouped_by_dept[$dept_id]['rows'][] = $row;
}
ksort($grouped_by_dept);
?>

<table border="0">
    <tr>
        <th colspan="15" style="text-align:center; font-size:20px; font-weight:bold;">LAPORAN REALISASI UPAH</th>
    </tr>
    <tr>
        <th colspan="15" style="text-align:center; font-size:16px;">
            Tanggal Realisasi: <?php echo date('d/m/Y', strtotime($Tanggal)); ?>
        </th>
    </tr>
    <tr>
        <td colspan="15" style="text-align:center; color:red; font-weight:bold; padding-top:10px;">JIKALAU NAMA YANG TERTERA DIABSEN TELAT MAKA AKAN DIPOTONG RP 25.000</td>
    </tr>
    <tr>
        <td colspan="15" style="text-align:center;">MASUK JAM 07:00 ISTIRAHAT JAM 11:50 MASUK JAM 10:00 ISTIRAHAT JAM 13:00.</td>
    </tr>
    <tr>
        <td colspan="15" style="height:15px;"></td>
    </tr>
</table>

<table border="1" style="border-collapse:collapse;">
    <tbody>
        <?php
        $grand_total = 0;
        $grand_karyawan = 0;
        
        foreach ($grouped_by_dept as $dept_id => $dept) {
            echo "<tr>
                    <td colspan='15' align='center' style='background-color:#1e3a8a; color:white; font-weight:bold;'>
                        " . strtoupper($dept['nama_departmen']) . "
                    </td>
                  </tr>";

            echo "<tr>
                <th style='background:#e5e7eb;'>No</th>
                <th style='background:#e5e7eb;'>NAMA SESUAI KTP</th>
                <th style='background:#e5e7eb;'>OS/DHK</th>
                <th style='background:#e5e7eb;'>GOLONGAN</th>
                <th style='background:#e5e7eb;'>POSISI</th>
                <th style='background:#e5e7eb;'>JAM MASUK</th>
                <th style='background:#e5e7eb;'>JAM PULANG</th>
                <th style='background:#e5e7eb;'>ABSEN MASUK</th>
                <th style='background:#e5e7eb;'>ABSEN ISTIRAHAT</th>
                <th style='background:#e5e7eb;'>ABSEN PULANG</th>
                <th style='background:#e5e7eb;'>HASIL KERJA</th>
                <th style='background:#e5e7eb;'>UPAH (Rp)</th>
                <th style='background:#e5e7eb;'>POTONGAN (Rp)</th>
                <th style='background:#e5e7eb;'>LEMBUR (Rp)</th>
                <th style='background:#e5e7eb;'>UPAH DIBAYAR (Rp)</th>
            </tr>";

            $no = 1;
            foreach ($dept['rows'] as $row) {
                $PIN = $row['pin'];
                $date = $row['date'];
                $masuk = $row['masuk'];
                $istirahat_keluar = $row['istirahat_keluar'];
                $istirahat_masuk = $row['istirahat_masuk'];
                $pulang = $row['pulang'];
                
                $nama_karyawan = $row['nama_karyawan'];
                $label_os = $row['label_os'];
                $label_gol = $row['label_gol'];
                $posisi = $row['posisi'];
                $upah = $row['upah'];

                $shift = get_closest_shift($koneksi, $masuk);
                $j_masuk = $shift && !empty($shift['jam_masuk']) ? $shift['jam_masuk'] : '-';
                $j_keluar = $shift && !empty($shift['jam_keluar']) ? $shift['jam_keluar'] : '-';

                // Perhitungan Potongan Otomatis
                $p_telat = 0; $p_pulang = 0; $p_ist_awal = 0; $p_ist_telat = 0; $p_tidak_lengkap = 0;
                $has_masuk = !empty($masuk) && $masuk != '00:00';
                $has_keluar = !empty($pulang) && $pulang != '00:00';
                $has_ist_masuk = !empty($istirahat_masuk) && $istirahat_masuk != '00:00';
                $has_ist_keluar = !empty($istirahat_keluar) && $istirahat_keluar != '00:00';

                if ($shift && ($has_masuk || $has_keluar)) {
                    $s_masuk = $shift['jam_masuk'];
                    $s_keluar = $shift['jam_keluar'];
                    $s_ist_masuk = $shift['istirahat_masuk'];
                    $s_ist_keluar = $shift['istirahat_keluar'];

                    if ($has_masuk && is_time_late($masuk, $s_masuk)) $p_telat = $globalDendaMasuk;
                    if ($has_keluar && is_time_early($pulang, $s_keluar)) $p_pulang = $globalDendaPulang;
                    if ($has_ist_keluar && is_time_early($istirahat_keluar, $s_ist_keluar)) $p_ist_awal = $globalDendaIstirahatKeluar;
                    if ($has_ist_masuk && is_time_late($istirahat_masuk, $s_ist_masuk)) $p_ist_telat = $globalDendaIstirahatMasuk;

                    $hasIncompleteMain = ($has_masuk XOR $has_keluar);
                    $isRestExpected = (!empty($s_ist_keluar) && $s_ist_keluar != '00:00:00');
                    $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));
                    if ($hasIncompleteMain || $hasIncompleteBreak) $p_tidak_lengkap = $globalDendaTidakLengkap;
                }

                $total_potongan = $p_telat + $p_pulang + $p_ist_awal + $p_ist_telat + $p_tidak_lengkap;
                $upah_dibayar = $upah - $total_potongan;
                if ($upah_dibayar < 0) $upah_dibayar = 0;

                $grand_total += $upah_dibayar;
                $grand_karyawan++;

                echo "<tr>
                    <td align='center'>$no</td>
                    <td>" . strtoupper($nama_karyawan) . "</td>
                    <td align='center'>{$label_os}</td>
                    <td align='center'>{$label_gol}</td>
                    <td>{$posisi}</td>
                    <td align='center'>{$j_masuk}</td>
                    <td align='center'>{$j_keluar}</td>
                    <td align='center'>" . (!empty($masuk) ? $masuk : '') . "</td>
                    <td align='center'>" . (!empty($istirahat_masuk) ? $istirahat_masuk : '') . "</td>
                    <td align='center'>" . (!empty($pulang) ? $pulang : '') . "</td>
                    <td align='center'></td>
                    <td align='right' style='mso-number-format:\"\#\,\#\#0\.00\";'>Rp " . number_format($upah, 2, '.', ',') . "</td>
                    <td align='right' style='mso-number-format:\"\#\,\#\#0\.00\"; color:red;'>" . ($total_potongan > 0 ? "Rp " . number_format($total_potongan, 2, '.', ',') : "-") . "</td>
                    <td align='right' style='mso-number-format:\"\#\,\#\#0\.00\";'>Rp 0.00</td>
                    <td align='right' style='font-weight:bold; mso-number-format:\"\#\,\#\#0\.00\";'>Rp " . number_format($upah_dibayar, 2, '.', ',') . "</td>
                </tr>";
                $no++;
            }
        }
        // GRAND TOTAL UPAH
        echo "<tr><td colspan='15' style='background:#203764; color:white; font-weight:bold; text-align:right; padding:10px;'>GRAND TOTAL UPAH ($grand_karyawan Karyawan) | Rp " . number_format($grand_total, 2, '.', ',') . "</td></tr>";
        ?>
    </tbody>
</table>
