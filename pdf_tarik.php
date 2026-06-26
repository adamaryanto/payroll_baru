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

if (!function_exists('rupiah')) {
	function rupiah($angka) {
		if ($angka === null || $angka === "") $angka = 0;
		return "Rp " . number_format($angka, 0, ',', '.');
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
$Connect = @fsockopen($IP, $port, $errno, $errstr, 0.3);

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
	for ($a = 0; $a < count($parsed_rows); $a++) {
		$data_raw = Parse_Data($parsed_rows[$a], "<Row>", "</Row>");
		$PIN = Parse_Data($data_raw, "<PIN>", "</PIN>");
		$DateTime = Parse_Data($data_raw, "<DateTime>", "</DateTime>");
		$Verified = Parse_Data($data_raw, "<Verified>", "</Verified>");
		$Status = Parse_Data($data_raw, "<Status>", "</Status>");

		if ($PIN != '' && $DateTime != '') {
			// Simpan ke database dengan pencegahan duplikat
			$check = $koneksi->query("SELECT id_record FROM tb_record WHERE userid = '$PIN' AND detail_waktu = '$DateTime' LIMIT 1");
			if ($check->num_rows == 0) {
				$koneksi->query("INSERT INTO tb_record (data,userid,tgl,verifikasi,status,detail_waktu) VALUES ('$data_raw','$PIN','$DateTime','$Verified','$Status','$DateTime')");
			}
		}
	}
} else {
	// Check if records exist in tb_record for this date
	$check_rec = $koneksi->query("SELECT id_record FROM tb_record WHERE tgl = '$Tanggal' LIMIT 1");
	
	// If local environment AND no records exist, generate dummy records
	if ($is_local && $check_rec->num_rows == 0) {
		$today = $Tanggal;
		$q_emp = $koneksi->query("SELECT no_absen FROM ms_karyawan WHERE status_karyawan = 'Aktif'");
		while ($row = $q_emp->fetch_assoc()) {
			$pin = $row['no_absen'];
			$minute_in = rand(30, 59);
			$time_in = "07:" . sprintf("%02d", $minute_in) . ":00";
			if (rand(1, 10) > 8) {
				$time_in = "08:" . sprintf("%02d", rand(1, 15)) . ":00";
			}
			$time_break_out = "12:" . sprintf("%02d", rand(0, 10)) . ":00";
			$time_break_in = "13:" . sprintf("%02d", rand(0, 5)) . ":00";
			$time_out = "17:" . sprintf("%02d", rand(0, 30)) . ":00";
			
			$times = [
				['time' => $time_in, 'status' => 0],
				['time' => $time_break_out, 'status' => 2],
				['time' => $time_break_in, 'status' => 3],
				['time' => $time_out, 'status' => 1]
			];
			
			foreach ($times as $t) {
				$dt = "$today {$t['time']}";
				$data_raw = "<Row><PIN>{$pin}</PIN><DateTime>{$dt}</DateTime><Verified>1</Verified><Status>{$t['status']}</Status></Row>";
				
				$check = $koneksi->query("SELECT id_record FROM tb_record WHERE userid = '$pin' AND detail_waktu = '$dt' LIMIT 1");
				if ($check->num_rows == 0) {
					$koneksi->query("INSERT INTO tb_record (data,userid,tgl,verifikasi,status,detail_waktu) VALUES ('$data_raw','$pin','$today','1','{$t['status']}','$dt')");
				}
			}
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

foreach ($grouped as $key => &$row) {
	if (empty($row['masuk']) && !empty($row['raw_times'])) {
		usort($row['raw_times'], function($a, $b) {
			return strcmp($a['time'], $b['time']);
		});
		$row['masuk'] = $row['raw_times'][0]['time'];
		if (count($row['raw_times']) > 1) {
			$row['pulang'] = $row['raw_times'][count($row['raw_times']) - 1]['time'];
		}
	}
}
unset($row);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Log Absensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0; }
        #content-to-pdf { padding: 20px; }
        .header { text-align: center; margin-bottom: 25px; }
        .header h2 { margin: 0; font-size: 16px; font-weight: 700; color: #1e3a8a; }
        .header p { margin: 5px 0 0 0; color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f3f4f6; color: #374151; font-weight: 700; border: 1px solid #d1d5db; padding: 6px 8px; text-transform: uppercase; font-size: 9px; }
        td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: middle; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: 600; }
        .badge { display: inline-block; padding: 2px 4px; font-size: 8px; font-weight: 700; border-radius: 3px; margin: 1px; }
        .badge-danger { background-color: #fee2e2; color: #b91c1c; }
        .badge-warning { background-color: #fef3c7; color: #d97706; }
        .badge-info { background-color: #eff6ff; color: #1d4ed8; }
        
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
            var element = document.getElementById('content-to-pdf');
            var opt = {
                margin:       10,
                filename:     'Laporan_Log_Absensi_' + '<?= htmlspecialchars($Tanggal) ?>' + '.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            
            html2pdf().set(opt).from(element).save().then(function() {
                document.getElementById('loader').style.display = 'none';
                document.body.innerHTML = '<div style="text-align: center; margin-top: 150px; font-family: \'Inter\', sans-serif;"><h2 style="color: #047857; font-size: 24px;">✓ Download Selesai</h2><p style="color: #666; font-size: 14px; margin-top: 10px;">Laporan PDF berhasil diunduh. Halaman ini akan tertutup otomatis.</p></div>';
                setTimeout(function() {
                    window.close();
                }, 1500);
            }).catch(function(err) {
                console.error(err);
                document.getElementById('loader').style.display = 'none';
                alert('Gagal membuat PDF. Silakan coba lagi.');
            });
        }
    </script>
</head>
<body>
    <div id="loader">
        <div class="spinner"></div>
        <p style="margin-top: 15px; font-weight: bold; color: #1e3a8a; font-family: 'Inter', sans-serif;">Mengunduh PDF, mohon tunggu...</p>
    </div>

    <div id="content-to-pdf">
        <div class="header">
            <h2>LAPORAN LOG ABSENSI MASUK / PULANG</h2>
            <p>Tanggal Absensi: <?php echo date('d-m-Y', strtotime($Tanggal)); ?> | Unduh: <?php echo date('d-m-Y H:i'); ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="3%" class="text-center">No</th>
                    <th width="8%" class="text-center">Tanggal</th>
                    <th width="8%" class="text-center">No. Absen</th>
                    <th>Nama Karyawan</th>
                    <th width="12%">Bagian</th>
                    <th width="12%" class="text-center">Shift</th>
                    <th width="8%" class="text-center">Masuk</th>
                    <th width="10%" class="text-center">Rehat</th>
                    <th width="8%" class="text-center">Pulang</th>
                    <th width="10%" class="text-right">Upah Pokok</th>
                    <th class="text-center" width="20%">Denda/Potongan</th>
                    <th width="10%" class="text-right">Upah Bersih</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                foreach ($grouped as $key => $row) :
                    $PIN = $row['pin'];
                    $date = $row['date'];
                    $masuk = $row['masuk'];
                    $istirahat_keluar = $row['istirahat_keluar'];
                    $istirahat_masuk = $row['istirahat_masuk'];
                    $pulang = $row['pulang'];

                    $empQuery = $koneksi->query("SELECT k.nama_karyawan, k.upah, d.nama_departmen 
                                                FROM ms_karyawan k 
                                                LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen 
                                                WHERE k.no_absen = '$PIN' LIMIT 1");
                    $emp = $empQuery->fetch_assoc();
                    $nama_karyawan = $emp['nama_karyawan'] ?? 'Karyawan Tidak Dikenal';
                    $bagian = $emp['nama_departmen'] ?? '-';
                    $upah = $emp['upah'] ?? 0;

                    $shift = get_closest_shift($koneksi, $masuk);
                    $shift_desc = $shift ? $shift['keterangan'] : '-';

                    $rehat_text = '-';
                    if (!empty($istirahat_keluar) || !empty($istirahat_masuk)) {
                        $rehat_text = (!empty($istirahat_keluar) ? $istirahat_keluar : '--:--') . ' - ' . (!empty($istirahat_masuk) ? $istirahat_masuk : '--:--');
                    }

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
                    $upah_bersih = $upah - $total_potongan;
                    if ($upah_bersih < 0) $upah_bersih = 0;
                ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td class="text-center"><?php echo date('d-m-Y', strtotime($date)); ?></td>
                        <td class="text-center"><?php echo $PIN; ?></td>
                        <td class="text-bold"><?php echo htmlspecialchars($nama_karyawan); ?></td>
                        <td><?php echo htmlspecialchars($bagian); ?></td>
                        <td class="text-center"><?php echo $shift_desc; ?></td>
                        <td class="text-center"><?php echo !empty($masuk) ? $masuk : '-'; ?></td>
                        <td class="text-center"><?php echo $rehat_text; ?></td>
                        <td class="text-center"><?php echo !empty($pulang) ? $pulang : '-'; ?></td>
                        <td class="text-right"><?php echo rupiah($upah); ?></td>
                        <td>
                            <?php if ($total_potongan > 0) : ?>
                                <div class="text-center">
                                    <?php if ($p_telat > 0) : ?>
                                        <span class="badge badge-danger">Telat: -<?= number_format($p_telat, 0, ',', '.') ?></span>
                                    <?php endif; ?>
                                    <?php if ($p_ist_awal > 0) : ?>
                                        <span class="badge badge-warning">Ist. Awal: -<?= number_format($p_ist_awal, 0, ',', '.') ?></span>
                                    <?php endif; ?>
                                    <?php if ($p_ist_telat > 0) : ?>
                                        <span class="badge badge-warning">Ist. Telat: -<?= number_format($p_ist_telat, 0, ',', '.') ?></span>
                                    <?php endif; ?>
                                    <?php if ($p_pulang > 0) : ?>
                                        <span class="badge badge-danger">P. Awal: -<?= number_format($p_pulang, 0, ',', '.') ?></span>
                                    <?php endif; ?>
                                    <?php if ($p_tidak_lengkap > 0) : ?>
                                        <span class="badge badge-danger">Log Absen: -<?= number_format($p_tidak_lengkap, 0, ',', '.') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center text-bold" style="color:#b91c1c; font-size:9px; margin-top:2px;">Total: -<?= number_format($total_potongan, 0, ',', '.') ?></div>
                            <?php else : ?>
                                <div class="text-center" style="color:#888;">-</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-right text-bold" style="color:#047857;"><?php echo rupiah($upah_bersih); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
