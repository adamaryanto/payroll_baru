<?php

set_time_limit(0); // unlimited

$tampil = $koneksi->query("SELECT * from tb_mesin");
$data = $tampil->fetch_assoc();
$idmesin = $data['id_mesin'] ?? '';
$nomesin = $data['no_mesin'] ?? '';
$namamesin = $data['nama_mesin'] ?? '';
$ipmesin = $data['ip_mesin'] ?? '';
$commkey = $data['comm_key'] ?? '';
$port = $data['port'] ?? '';

$IP = $_GET['ip'] ?? $ipmesin;
$Key = $_GET['key'] ?? "0";
$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
if ($IP == "") $IP = "192.168.110.201";
if ($Key == "") $Key = "";

// Proses hapus terpilih jika form disubmit
if (isset($_POST['hapus_pilih']) && !empty($_POST['selected_logs'])) {
	$count = 0;
	foreach ($_POST['selected_logs'] as $log_key) {
		$parts = explode('|', $log_key);
		if (count($parts) == 2) {
			$pin_esc = mysqli_real_escape_string($koneksi, $parts[0]);
			$date_esc = mysqli_real_escape_string($koneksi, $parts[1]);
			if ($koneksi->query("DELETE FROM tb_record WHERE userid = '$pin_esc' AND tgl = '$date_esc'")) {
				$count++;
			}
		}
	}
	
	// Redirect menggunakan JavaScript untuk mencegah error "headers already sent" yang menyebabkan halaman kosong
	$redirect_url = "?page=generate&aksi=tarik&ip=" . urlencode($IP) . "&key=" . urlencode($Key) . "&tanggal_awal=" . urlencode($TanggalAwal) . "&tanggal_akhir=" . urlencode($TanggalAkhir) . "&delete_success=" . $count;
	echo "<script>window.location.href = '$redirect_url';</script>";
	exit;
}

$delete_success_msg = "";
if (isset($_GET['delete_success'])) {
	$count = intval($_GET['delete_success']);
	$delete_success_msg = "$count data absensi karyawan berhasil dihapus.";
}
?>
<html>

<head>
	<title>Contoh Koneksi Mesin Absensi Mengunakan SOAP Web Service</title>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		/* Prevent cell wrapping and enable horizontal scroll */
		.table-modern th,
		.table-modern td {
			white-space: nowrap !important;
		}
		.table-responsive {
			overflow-x: auto !important;
			-webkit-overflow-scrolling: touch !important;
		}

		/* 1. Reset wrapper agar tidak menggunakan float bawaan DataTables */
		.dataTables_wrapper {
			display: block !important;
		}

		/* 2. Memaksa area atas (Length & Filter) menjadi satu baris sejajar */
		.dataTables_wrapper::before,
		.dataTables_wrapper::after {
			display: none !important;
		}

		/* 3. Membuat container fleksibel untuk Length (kiri) dan Filter (kanan) */
		#dataTables-example_wrapper .row:first-child {
			display: flex !important;
			justify-content: space-between !important;
			align-items: center !important;
			margin-bottom: 20px !important;
			width: 100% !important;
		}

		/* 4. Styling Tampil _MENU_ (Kiri) */
		.dataTables_length {
			display: flex !important;
			align-items: center !important;
		}

		.dataTables_length label {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			margin: 0 !important;
		}

		.dataTables_length select {
			padding: 5px 10px !important;
			border: 1px solid #e0e6ed !important;
			border-radius: 8px !important;
		}

		/* 5. Styling Cari: (Kanan) */
		.dataTables_filter {
			text-align: right !important;
			display: flex !important;
			justify-content: flex-end !important;
		}

		.dataTables_filter label {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			margin: 0 !important;
		}

		.dataTables_filter input {
			padding: 6px 12px !important;
			border: 1px solid #e0e6ed !important;
			border-radius: 8px !important;
			width: 200px !important;
		}

		/* --- STYLING PAGINATE (PREV/NEXT) --- */
		.dataTables_wrapper .dataTables_paginate {
			display: flex !important;
			justify-content: flex-end !important;
			align-items: center !important;
			gap: 4px !important;
			padding-top: 15px !important;
		}

		.dataTables_paginate .paginate_button {
			border: 1px solid #e2e8f0 !important;
			background: white !important;
			border-radius: 6px !important;
			padding: 5px 12px !important;
			color: #475569 !important;
			font-weight: 500 !important;
			cursor: pointer !important;
			transition: all 0.2s !important;
		}

		.dataTables_paginate .paginate_button:hover {
			background: #f8fafc !important;
			color: #2563eb !important;
			border-color: #cbd5e1 !important;
		}

		.dataTables_paginate .paginate_button.current {
			background: #2563eb !important;
			border-color: #2563eb !important;
			color: white !important;
		}

		.dataTables_paginate .paginate_button.disabled {
			background: #f1f5f9 !important;
			color: #94a3b8 !important;
			cursor: not-allowed !important;
		}

		/* --- STYLING INFO --- */
		.dataTables_wrapper .dataTables_info {
			padding-top: 20px !important;
			color: #64748b !important;
			font-size: 13px !important;
		}

		/* =========================================
       KHUSUS TAMPILAN MOBILE DIPERBAIKI DI SINI
       ========================================= */
		@media screen and (max-width: 768px) {
			.table-responsive {
				padding: 12px !important;
			}

			#dataTables-example_wrapper .row:first-child {
				flex-direction: column !important;
				align-items: flex-start !important;
				gap: 15px;
			}

			.dataTables_filter,
			.dataTables_length {
				width: 100% !important;
				justify-content: flex-start !important;
			}

			.dataTables_filter input {
				width: 100% !important;
				max-width: 100% !important;
			}

			.dataTables_paginate {
				justify-content: center !important;
				flex-wrap: wrap;
			}



			/* Mobile layout for download buttons */
			.download-btn-container {
				display: flex !important;
				width: 100% !important;
				justify-content: space-between !important;
				margin-bottom: 15px !important;
			}
			.download-btn-container .download-btn {
				flex: 1 !important;
				text-align: center !important;
				justify-content: center !important;
				margin: 0 !important;
				padding: 10px 8px !important;
				font-size: 13px !important;
			}
			.download-btn-container .download-btn:first-child {
				margin-right: 8px !important;
			}
		}
	</style>
</head>

<body>
	<div class="container-fluid px-2 mt-4 mb-4">
		<div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

			<div class="border-b border-gray-100 py-4 px-4 md:px-5 bg-white">
				<h3 class="text-xl text-blue-600 font-bold m-0"><i class="fas fa-download mr-2"></i>Download Log Data</h3>
			</div>

			<?php
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

			if (!function_exists('rupiah')) {
				function rupiah($angka) {
					if ($angka === null || $angka === "") $angka = 0;
					return "Rp " . number_format($angka, 0, ',', '.');
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

			// Tetap mempertahankan variabel asli dan komentar Anda
			//include "koneksi.php";
			//$IP= $HTTP_GET_VARS["121.0.0.144"];
			//$Key= $HTTP_GET_VARS[""];
			$IP = $_GET['ip'] ?? $ipmesin;
			$Key = $_GET['key'] ?? "0";
			$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
			$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
			if ($IP == "") $IP = "192.168.110.201";
			if ($Key == "") $Key = "";
			?>

			<form action="" method="GET">
				<input type="hidden" name="page" value="generate" />
				<input type="hidden" name="aksi" value="tarik" />
				<div class="p-4 md:p-5 bg-gray-50 border-b border-gray-100">
					<div class="row items-end">
						<div class="col-md-3 col-sm-12 mb-3 mb-md-0">
							<label class="font-bold text-gray-700 text-sm uppercase tracking-wide">IP Address:</label>
							<input type="text" name="ip" value="<?php echo $IP; ?>" placeholder="Contoh: 192.168.1.1" class="form-control text-base py-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200" />
						</div>
						<div class="col-md-1 col-sm-12 mb-3 mb-md-0">
							<label class="font-bold text-gray-700 text-sm uppercase tracking-wide">Comm Key:</label>
							<input type="text" name="key" value="<?php echo $Key; ?>" placeholder="0" class="form-control text-base py-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200" />
						</div>
						<div class="col-md-3 col-sm-12 mb-3 mb-md-0">
							<label class="font-bold text-gray-700 text-sm uppercase tracking-wide">Tanggal Awal:</label>
							<input type="date" name="tanggal_awal" value="<?php echo $TanggalAwal; ?>" onchange="this.form.submit()" class="form-control text-base py-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200" />
						</div>
						<div class="col-md-3 col-sm-12 mb-3 mb-md-0">
							<label class="font-bold text-gray-700 text-sm uppercase tracking-wide">Tanggal Akhir:</label>
							<input type="date" name="tanggal_akhir" value="<?php echo $TanggalAkhir; ?>" onchange="this.form.submit()" class="form-control text-base py-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200" />
						</div>
						<div class="col-md-2 col-sm-12">
							<button type="submit" class="btn btn-primary font-bold px-2 h-[42px] d-inline-flex align-items-center w-full justify-center shadow-sm">
								<i class="fas fa-cloud-download-alt mr-2"></i> TARIK DATA
							</button>
						</div>
					</div>
				</div>
			</form>

			<div class="p-0">
				<form id="form-tarik-data" method="POST" action="">
				<div class="table-responsive px-3 py-4">
					<?php if ($IP != "") : ?>
						<div class="download-btn-container d-flex justify-content-end mb-3 flex-wrap">
							<button type="button" id="btn-hapus-terpilih" class="btn btn-danger font-semibold text-white d-inline-flex align-items-center mr-2 shadow-sm download-btn">
								<i class="fas fa-trash-alt mr-2"></i> Hapus Terpilih
							</button>
							<a href="excel_tarik.php?ip=<?= htmlspecialchars($IP) ?>&key=<?= htmlspecialchars($Key) ?>&tanggal_awal=<?= htmlspecialchars($TanggalAwal) ?>&tanggal_akhir=<?= htmlspecialchars($TanggalAkhir) ?>" target="_blank" class="btn btn-success font-semibold text-white d-inline-flex align-items-center mr-2 shadow-sm download-btn">
								<i class="fas fa-file-excel mr-2"></i> Download Excel
							</a>
							<a href="pdf_tarik.php?ip=<?= htmlspecialchars($IP) ?>&key=<?= htmlspecialchars($Key) ?>&tanggal_awal=<?= htmlspecialchars($TanggalAwal) ?>&tanggal_akhir=<?= htmlspecialchars($TanggalAkhir) ?>" target="_blank" class="btn btn-danger font-semibold text-white d-inline-flex align-items-center shadow-sm download-btn">
								<i class="fas fa-file-pdf mr-2"></i> Download PDF
							</a>
						</div>
						<table class="w-full text-left border-collapse table-modern" id="dataTables-example">
							<thead class="bg-gray-50 border-b border-gray-200">
								<tr>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center w-[40px]">
										<input type="checkbox" id="select-all" class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer" />
									</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">No</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Tanggal</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Nama Karyawan</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Bagian</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Shift</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Jam Masuk</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Absen Masuk</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Absen Istirahat</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Jam Pulang</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Absen Pulang</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Upah Pokok</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center" style="min-width: 150px;">Potongan/Denda</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Upah Bersih</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
								<?php
								// Query Pengaturan Denda Global
								$queryDenda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
								$dataDenda = $queryDenda->fetch_assoc();
								$globalDendaMasuk = $dataDenda['denda_masuk'] ?? 0;
								$globalDendaIstirahatKeluar = $dataDenda['denda_istirahat_keluar'] ?? 0;
								$globalDendaIstirahatMasuk = $dataDenda['denda_istirahat_masuk'] ?? 0;
								$globalDendaPulang = $dataDenda['denda_pulang'] ?? 0;
								$globalDendaTidakLengkap = $dataDenda['denda_tidak_lengkap'] ?? 0;
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
									
									// Parse and insert all pulled logs into tb_record
									include(__DIR__ . "/parse.php");
									$parsed_data = Parse_Data($buffer, "<GetAttLogResponse>", "</GetAttLogResponse>");
									$parsed_rows = explode("\r\n", $parsed_data);
									
									// Build lookup map for duplicates
									$existing_logs = [];
									$ex_res = $koneksi->query("SELECT userid, detail_waktu FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
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
											$date_only = substr($DateTime, 0, 10);
											if ($date_only >= $TanggalAwal && $date_only <= $TanggalAkhir) {
												$key = $PIN . '_' . $DateTime;
												if (!isset($existing_logs[$key])) {
													$data_esc = mysqli_real_escape_string($koneksi, $data_raw);
													$insert_values[] = "('$data_esc','$PIN','$date_only','$Verified','$Status','$DateTime')";
													$existing_logs[$key] = true;
												}
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
								} else {
									echo "<p class='text-center py-4 text-red-500 font-bold'>Koneksi Gagal ke Mesin!</p>";
								}

								// Query and group data from tb_record for Date Range
								$db_records = $koneksi->query("SELECT * FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' ORDER BY tgl ASC, id_record ASC");
								$grouped = [];

								while ($row_rec = $db_records->fetch_assoc()) {
									$PIN = $row_rec['userid'];
									$DateTime = $row_rec['detail_waktu'];
									$Verified = $row_rec['verifikasi'];
									$Status = $row_rec['status'];
									$rec_tgl = $row_rec['tgl'];

									$time = substr($DateTime, 11, 5); // "08:12"
									$key = $PIN . '_' . $rec_tgl;
									if (!isset($grouped[$key])) {
										$grouped[$key] = [
											'pin' => $PIN,
											'date' => $rec_tgl,
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

								// Fallback jika tombol status tidak ditekan di mesin
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

								$no = 1;
								foreach ($grouped as $key => $row) :
									$PIN = $row['pin'];
									$date = $row['date'];
									$masuk = $row['masuk'];
									$istirahat_keluar = $row['istirahat_keluar'];
									$istirahat_masuk = $row['istirahat_masuk'];
									$pulang = $row['pulang'];

									// Cari karyawan di database
									$empQuery = $koneksi->query("SELECT k.nama_karyawan, k.upah, d.nama_departmen 
																FROM ms_karyawan k 
																LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen 
																WHERE k.no_absen = '$PIN' LIMIT 1");
									$emp = $empQuery->fetch_assoc();
									$nama_karyawan = $emp['nama_karyawan'] ?? 'Karyawan Tidak Dikenal';
									$bagian = $emp['nama_departmen'] ?? '-';
									$upah = $emp['upah'] ?? 0;

									// Cari Shift Terdekat
									$shift = get_closest_shift($koneksi, $masuk);
									$shift_text = '-';
									$s_masuk = '-';
									$s_keluar = '-';
									if ($shift) {
										$shift_text = '<span class="text-gray-900 font-semibold">' . $shift['keterangan'] . '</span>';
										$s_masuk = !empty($shift['jam_masuk']) ? $shift['jam_masuk'] : '-';
										$s_keluar = !empty($shift['jam_keluar']) ? $shift['jam_keluar'] : '-';
									}

									// Format Jam Rehat
									$rehat_text = '-';
									if (!empty($istirahat_keluar) || !empty($istirahat_masuk)) {
										$rehat_text = (!empty($istirahat_keluar) ? $istirahat_keluar : '--:--') . ' - ' . (!empty($istirahat_masuk) ? $istirahat_masuk : '--:--');
									}

									// Perhitungan Potongan Otomatis
									$p_telat = 0;
									$p_pulang = 0;
									$p_ist_awal = 0;
									$p_ist_telat = 0;
									$p_tidak_lengkap = 0;

									$has_masuk = !empty($masuk) && $masuk != '00:00';
									$has_keluar = !empty($pulang) && $pulang != '00:00';
									$has_ist_masuk = !empty($istirahat_masuk) && $istirahat_masuk != '00:00';
									$has_ist_keluar = !empty($istirahat_keluar) && $istirahat_keluar != '00:00';

									if ($shift && ($has_masuk || $has_keluar)) {
										$s_masuk = $shift['jam_masuk'];
										$s_keluar = $shift['jam_keluar'];
										$s_ist_masuk = $shift['istirahat_masuk'];
										$s_ist_keluar = $shift['istirahat_keluar'];

										if ($has_masuk && is_time_late($masuk, $s_masuk)) {
											$p_telat = $globalDendaMasuk;
										}
										if ($has_keluar && is_time_early($pulang, $s_keluar)) {
											$p_pulang = $globalDendaPulang;
										}
										if ($has_ist_keluar && is_time_early($istirahat_keluar, $s_ist_keluar)) {
											$p_ist_awal = $globalDendaIstirahatKeluar;
										}
										if ($has_ist_masuk && is_time_late($istirahat_masuk, $s_ist_masuk)) {
											$p_ist_telat = $globalDendaIstirahatMasuk;
										}

										// Absen Tidak Lengkap
										$hasIncompleteMain = ($has_masuk XOR $has_keluar);
										$isRestExpected = (!empty($s_ist_keluar) && $s_ist_keluar != '00:00:00');
										$hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));
										
										if ($hasIncompleteMain || $hasIncompleteBreak) {
											$p_tidak_lengkap = $globalDendaTidakLengkap;
										}
									}

									$total_potongan = $p_telat + $p_pulang + $p_ist_awal + $p_ist_telat + $p_tidak_lengkap;
									$upah_bersih = $upah - $total_potongan;
									if ($upah_bersih < 0) $upah_bersih = 0;
								?>
									<tr class="hover:bg-gray-50 transition-colors">
										<td class="py-2 px-2 text-center align-middle">
											<input type="checkbox" name="selected_logs[]" value="<?= htmlspecialchars($PIN . '|' . $date) ?>" class="log-checkbox rounded text-blue-600 focus:ring-blue-500 cursor-pointer" />
										</td>
										<td data-label="No" class="py-2 px-2 text-[15px] text-center text-gray-500"><?php echo $no++; ?></td>
										<td data-label="Tanggal" class="py-2 px-2 text-[15px] text-gray-700"><?php echo date('d-m-Y', strtotime($date)); ?></td>
										<td data-label="Nama Karyawan" class="py-2 px-2 text-[15px]">
											<div class="font-bold text-gray-900"><?php echo htmlspecialchars($nama_karyawan); ?></div>
											<div class="text-xs text-gray-400">ID: <?php echo htmlspecialchars($PIN); ?></div>
										</td>
										<td data-label="Bagian" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($bagian); ?></td>
										<td data-label="Shift Terdeteksi" class="py-2 px-2 text-[15px] text-center"><?php echo $shift_text; ?></td>
										<td data-label="Jam Masuk" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_masuk); ?></td>
										<td data-label="Absen Masuk" class="py-2 px-2 text-[15px] text-center font-semibold text-gray-900"><?php echo !empty($masuk) ? htmlspecialchars($masuk) : '-'; ?></td>
										<td data-label="Absen Istirahat" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($rehat_text); ?></td>
										<td data-label="Jam Pulang" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_keluar); ?></td>
										<td data-label="Absen Pulang" class="py-2 px-2 text-[15px] text-center font-semibold text-gray-900"><?php echo !empty($pulang) ? htmlspecialchars($pulang) : '-'; ?></td>
										<td data-label="Upah Pokok" class="py-2 px-2 text-[15px] text-right text-gray-600 font-semibold"><?php echo rupiah($upah); ?></td>
										<td data-label="Potongan/Denda" class="py-2 px-2 text-center text-[12px]">
											<?php if ($total_potongan > 0) : ?>
												<div class="flex flex-wrap gap-1 justify-center max-w-[180px] mx-auto">
													<?php if ($p_telat > 0) : ?>
														<span class="inline-block px-1.5 py-0.5 bg-red-50 text-red-600 text-[10px] font-bold rounded">Terlambat: -<?= number_format($p_telat, 0, ',', '.') ?></span>
													<?php endif; ?>
													<?php if ($p_ist_awal > 0) : ?>
														<span class="inline-block px-1.5 py-0.5 bg-amber-50 text-amber-600 text-[10px] font-bold rounded">Istirahat Awal: -<?= number_format($p_ist_awal, 0, ',', '.') ?></span>
													<?php endif; ?>
													<?php if ($p_ist_telat > 0) : ?>
														<span class="inline-block px-1.5 py-0.5 bg-orange-50 text-orange-600 text-[10px] font-bold rounded">Istirahat Telat: -<?= number_format($p_ist_telat, 0, ',', '.') ?></span>
													<?php endif; ?>
													<?php if ($p_pulang > 0) : ?>
														<span class="inline-block px-1.5 py-0.5 bg-red-50 text-red-600 text-[10px] font-bold rounded">Pulang Awal: -<?= number_format($p_pulang, 0, ',', '.') ?></span>
													<?php endif; ?>
													<?php if ($p_tidak_lengkap > 0) : ?>
														<span class="inline-block px-1.5 py-0.5 bg-rose-50 text-rose-600 text-[10px] font-bold rounded">Absen Tidak Lengkap: -<?= number_format($p_tidak_lengkap, 0, ',', '.') ?></span>
													<?php endif; ?>
												</div>
												<div class="text-[11px] text-red-600 font-bold mt-1">Total: -<?= number_format($total_potongan, 0, ',', '.') ?></div>
											<?php else : ?>
												<span class="text-gray-400">-</span>
											<?php endif; ?>
										</td>
										<td data-label="Upah Bersih" class="py-2 px-2 text-[15px] text-right font-bold text-emerald-600"><?php echo rupiah($upah_bersih); ?></td>
									</tr>
								<?php
								endforeach;
								?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
				</form>
			</div>
		</div>
	</div>

	<script>
		$(document).ready(function() {
			// SweetAlert Success Message
			<?php if (!empty($delete_success_msg)) : ?>
			Swal.fire({
				title: 'Berhasil!',
				text: '<?= htmlspecialchars($delete_success_msg) ?>',
				icon: 'success',
				confirmButtonColor: '#3b82f6'
			});
			<?php endif; ?>

			$('#dataTables-example').DataTable({
				pageLength: 25,
				autoWidth: false,
				responsive: false,
				columnDefs: [
					{ orderable: false, targets: 0 }
				],
				order: [[1, 'asc']],
				lengthMenu: [
					[10, 25, 50, -1],
					[10, 25, 50, "Semua"]
				],
				language: {
					search: "Cari:",
					searchPlaceholder: "Cari data...",
					lengthMenu: "Tampilkan _MENU_ data",
					info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
					paginate: {
						previous: "Prev",
						next: "Next"
					}
				}
			});

			// Dihapus style .css('float') bawaan agar tidak bentrok dengan flexbox pada mobile
			$('.dataTables_filter').addClass('mb-3');
			$('.dataTables_length').addClass('mb-3');

			// Select All checkbox logic
			$('#select-all').on('click', function() {
				var rows = $('#dataTables-example').DataTable().rows({ 'search': 'applied' }).nodes();
				$('input[type="checkbox"].log-checkbox', rows).prop('checked', this.checked);
			});

			// Checkbox change handler to sync select-all state
			$('#dataTables-example').on('change', '.log-checkbox', function() {
				if (!this.checked) {
					var el = $('#select-all').get(0);
					if (el && el.checked && ('indeterminate' in el)) {
						el.indeterminate = true;
					}
				}
			});

			// Submit bulk delete
			$('#btn-hapus-terpilih').on('click', function(e) {
				e.preventDefault();
				
				var table = $('#dataTables-example').DataTable();
				var checkedData = [];
				table.$('.log-checkbox:checked').each(function() {
					checkedData.push($(this).val());
				});
				
				if (checkedData.length === 0) {
					Swal.fire({
						title: 'Peringatan',
						text: 'Pilih minimal satu data yang ingin dihapus.',
						icon: 'warning',
						confirmButtonColor: '#3b82f6'
					});
					return;
				}
				
				Swal.fire({
					title: 'Apakah Anda yakin?',
					text: checkedData.length + ' data absensi terpilih akan dihapus permanen!',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonColor: '#ef4444',
					cancelButtonColor: '#6b7280',
					confirmButtonText: 'Ya, Hapus!',
					cancelButtonText: 'Batal'
				}).then((result) => {
					if (result.isConfirmed) {
						var $form = $('#form-tarik-data');
						$form.find('.appended-log-checkbox').remove();
						
						checkedData.forEach(function(val) {
							$form.append('<input type="hidden" class="appended-log-checkbox" name="selected_logs[]" value="' + val + '" />');
						});
						
						$form.append('<input type="hidden" class="appended-log-checkbox" name="hapus_pilih" value="1" />');
						$form.submit();
					}
				});
			});
		});
	</script>
</body>

</html>