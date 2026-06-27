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

// AJAX handler for updating lembur_jam in Tarik Data
if (isset($_POST['update_lembur_ajax'])) {
	$user_role = strtolower($_SESSION['role'] ?? '');
	$can_edit = ($user_role === 'kepala pabrik' || $user_role === 'admin master' || $user_role === 'owner');
	if (!$can_edit) {
		echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk mengedit lembur.']);
		exit;
	}

	$userid = $koneksi->real_escape_string($_POST['userid']);
	$tgl = $koneksi->real_escape_string($_POST['tgl']);
	$lembur_jam = (float)$_POST['lembur_jam'];
	
	$koneksi->query("CREATE TABLE IF NOT EXISTS tb_record_lembur (
		userid VARCHAR(50) NOT NULL,
		tgl DATE NOT NULL,
		lembur_jam DECIMAL(10,2) DEFAULT 0,
		PRIMARY KEY (userid, tgl)
	) ENGINE=InnoDB");

	$koneksi->query("INSERT INTO tb_record_lembur (userid, tgl, lembur_jam) 
					 VALUES ('$userid', '$tgl', '$lembur_jam') 
					 ON DUPLICATE KEY UPDATE lembur_jam = '$lembur_jam'");
	echo json_encode(['success' => true]);
	exit;
}

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
	
	// Simpan ke session flash untuk mencegah resubmission form dan URL kotor saat refresh
	$_SESSION['delete_success'] = true;
	
	$redirect_url = "?page=generate&aksi=tarik&ip=" . urlencode($IP) . "&key=" . urlencode($Key) . "&tanggal_awal=" . urlencode($TanggalAwal) . "&tanggal_akhir=" . urlencode($TanggalAkhir);
	echo "<script>window.location.href = '$redirect_url';</script>";
	exit;
}

$delete_success_msg = "";
if (isset($_SESSION['delete_success'])) {
	$delete_success_msg = "Data absensi karyawan berhasil dihapus.";
	unset($_SESSION['delete_success']); // Hapus langsung dari session agar tidak muncul lagi saat refresh
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

		.bg-red-custom {
			background-color: #e74c3c !important;
			color: #fff !important;
		}
		.bg-yellow-custom {
			background-color: #f1c40f !important;
			color: #000 !important;
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
			.container-fluid {
				padding-left: 0 !important;
				padding-right: 0 !important;
			}

			.row {
				margin-left: 0 !important;
				margin-right: 0 !important;
			}

			.col-md-12 {
				padding-left: 0 !important;
				padding-right: 0 !important;
			}

			.table-responsive {
				border: none !important;
				overflow-x: visible !important;
				padding: 0 !important;
				margin: 0 !important;
			}

			#dataTables-example_wrapper {
				padding: 0 10px;
			}

			#dataTables-example_wrapper .row:first-child {
				display: flex !important;
				flex-direction: column !important;
				align-items: flex-start !important;
				gap: 15px;
				margin-bottom: 15px !important;
				width: 100% !important;
			}

			.dataTables_filter,
			.dataTables_length {
				display: flex !important;
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

			#dataTables-example {
				width: 100% !important;
				margin: 0 !important;
			}

			/* --- STYLING MODERN CARD KARYAWAN --- */
			.table-modern tbody tr {
				display: block;
				margin: 0 5px 20px 5px;
				/* Jarak antar card */
				border: 1px solid #cbd5e1 !important;
				/* Border card */
				border-radius: 12px !important;
				/* Sudut melengkung halus */
				background: #ffffff;
				box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
				/* Shadow elegan */
				overflow: hidden;
				padding: 0;
			}

			.table-modern thead {
				display: none !important;
			}

			.table-modern tbody td {
				display: flex;
				flex-direction: column;
				align-items: flex-start !important;
				text-align: left !important;
				/* Memaksa rata kiri */
				padding: 12px 16px !important;
				border: none !important;
				border-bottom: 1px solid #f1f5f9 !important;
				/* Garis tipis antar baris di dalam card */
				width: 100% !important;
				font-size: 14px;
				white-space: normal !important;
			}

			/* OVERRIDE CLASS TEXT-RIGHT AGAR TIDAK NUMPUK DI KANAN PADA MOBILE */
			.table-modern tbody td.text-right,
			.table-modern tbody td.text-center {
				text-align: left !important;
				align-items: flex-start !important;
			}

			.table-modern tbody td:before {
				content: attr(data-label);
				font-weight: 700;
				color: #475569;
				text-transform: uppercase;
				font-size: 11px;
				letter-spacing: 0.5px;
				margin-bottom: 6px;
				display: block;
				width: 100%;
			}

			.table-modern tbody td:last-child {
				border-bottom: none !important;
				background-color: #f8fafc;
				/* Highlight area tombol aksi */
				padding-top: 16px !important;
				padding-bottom: 16px !important;
			}

			/* Mobile layout for download buttons */
			.download-btn-container {
				display: flex !important;
				flex-direction: column !important;
				width: 100% !important;
				gap: 8px !important;
				margin-bottom: 15px !important;
			}
			.download-btn-container .download-btn {
				width: 100% !important;
				text-align: center !important;
				justify-content: center !important;
				margin: 0 !important;
				padding: 10px 8px !important;
				font-size: 14px !important;
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
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">No Absen</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Nama Karyawan</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Departemen</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Sub Bagian</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">OS/DHK</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Golongan</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Jam Masuk</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Jam Pulang</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Istirahat Keluar</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Istirahat Masuk</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Absen Masuk</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Absen Pulang</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Absen Istirahat Keluar</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Absen Istirahat Masuk</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Upah Pokok</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Lembur</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Pot. Telat</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Pot. Istirahat (Awal)</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Pot. Istirahat (Telat)</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Denda Pulang</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Denda Tidak Absen</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Pot. Lain</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-right">Upah Setelah Potongan</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Hasil</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">Aksi</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
								<?php
								// Auto-create table tb_record_lembur if it doesn't exist
								$koneksi->query("CREATE TABLE IF NOT EXISTS tb_record_lembur (
									userid VARCHAR(50) NOT NULL,
									tgl DATE NOT NULL,
									lembur_jam DECIMAL(10,2) DEFAULT 0,
									PRIMARY KEY (userid, tgl)
								) ENGINE=InnoDB");

								// Query Pengaturan Lembur & Denda Global
								$koneksi->query("CREATE TABLE IF NOT EXISTS tb_lembur (
									id_lembur INT AUTO_INCREMENT PRIMARY KEY,
									lembur_per_jam INT DEFAULT 0
								) ENGINE=InnoDB");
								$checkLembur = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1");
								if ($checkLembur->num_rows == 0) {
									$koneksi->query("INSERT INTO tb_lembur (id_lembur, lembur_per_jam) VALUES (1, 0)");
								}
								$dataLembur = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1")->fetch_assoc();
								$globalLemburPerJam = $dataLembur['lembur_per_jam'] ?? 0;

								$queryDenda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
								$dataDenda = $queryDenda->fetch_assoc();
								$globalDendaMasuk = $dataDenda['denda_masuk'] ?? 0;
								$globalDendaIstirahatKeluar = $dataDenda['denda_istirahat_keluar'] ?? 0;
								$globalDendaIstirahatMasuk = $dataDenda['denda_istirahat_masuk'] ?? 0;
								$globalDendaPulang = $dataDenda['denda_pulang'] ?? 0;
								$globalDendaTidakLengkap = $dataDenda['denda_tidak_lengkap'] ?? 0;

								// Load custom lembur hours
								$custom_lembur = [];
								$cl_res = $koneksi->query("SELECT * FROM tb_record_lembur WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
								if ($cl_res) {
									while ($cl_row = $cl_res->fetch_assoc()) {
										$custom_lembur[$cl_row['userid'] . '_' . $cl_row['tgl']] = (float)$cl_row['lembur_jam'];
									}
								}

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
									$Connect = @fsockopen($IP, $port, $errno, $errstr, 1.5);
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
									if ($should_connect) {
										echo "<div class='alert alert-warning text-center font-bold' style='margin-bottom:15px; border-radius:8px;'><i class='fa fa-exclamation-triangle'></i> Gagal terhubung ke mesin absensi (IP: $IP). Menampilkan data lokal ter-update.</div>";
									} else {
										echo "<div class='alert alert-info text-center font-bold' style='margin-bottom:15px; border-radius:8px;'><i class='fa fa-info-circle'></i> Menampilkan database lokal (koneksi mesin dinonaktifkan di luar jaringan lokal).</div>";
									}
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

									// Cari karyawan di database lengkap dengan Departemen, Sub Departemen, OS/DHK, dan Golongan
									$empQuery = $koneksi->query("SELECT k.nama_karyawan, k.upah, k.id_jadwal, d.nama_departmen, sd.nama_sub_department, o.OS_DHK as label_os, g.golongan as label_gol 
																FROM ms_karyawan k 
																LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen 
																LEFT JOIN ms_sub_department sd ON k.id_sub_department = sd.id_sub_department
																LEFT JOIN ms_os_dhk o ON k.id_os_dhk = o.id_os_dhk
																LEFT JOIN ms_golongan g ON k.id_golongan = g.id_golongan
																WHERE k.no_absen = '$PIN' LIMIT 1");
									$emp = $empQuery->fetch_assoc();
									$nama_karyawan = $emp['nama_karyawan'] ?? 'Karyawan Tidak Dikenal';
									$bagian = $emp['nama_departmen'] ?? '-';
									$sub_bagian = $emp['nama_sub_department'] ?? '-';
									$os_dhk = $emp['label_os'] ?? 'MHS';
									$golongan = $emp['label_gol'] ?? 'Harian';
									$upah = $emp['upah'] ?? 0;

									// Cari Shift Terdekat atau gunakan shift terdaftar jika Flexible
									$assigned_shift = null;
									if (!empty($emp['id_jadwal'])) {
										$q_ass = $koneksi->query("SELECT * FROM tb_jadwal WHERE id_jadwal = '{$emp['id_jadwal']}'");
										if ($q_ass && $q_ass->num_rows > 0) {
											$assigned_shift = $q_ass->fetch_assoc();
										}
									}
									
									$is_assigned_flexible = ($assigned_shift && strpos(strtoupper($assigned_shift['keterangan'] ?? ''), 'FLEXIBLE') !== false);
									
									if ($is_assigned_flexible) {
										$shift = $assigned_shift;
									} else {
										$shift = get_closest_shift($koneksi, $masuk);
									}
									$s_masuk = '-';
									$s_keluar = '-';
									$s_ist_keluar = '-';
									$s_ist_masuk = '-';
									$is_flexible = false;
									$expected_hours = 7;
									
									if ($shift) {
										$is_flexible = (strpos(strtoupper($shift['keterangan'] ?? ''), 'FLEXIBLE') !== false);
										if ($is_flexible) {
											if (preg_match('/(\d+)\s*JAM/i', $shift['keterangan'], $matches)) {
												$expected_hours = (int)$matches[1];
											}
											$s_masuk = 'Bebas';
											if (!empty($masuk) && $masuk != '00:00') {
												$s_keluar = date('H:i', strtotime($masuk) + ($expected_hours * 3600));
											} else {
												$s_keluar = 'Masuk + ' . $expected_hours . ' Jam';
											}
											$s_ist_keluar = 'Bebas';
											$s_ist_masuk = 'Bebas';
										} else {
											$s_masuk = !empty($shift['jam_masuk']) ? $shift['jam_masuk'] : '-';
											$s_keluar = !empty($shift['jam_keluar']) ? $shift['jam_keluar'] : '-';
											$s_ist_keluar = !empty($shift['istirahat_keluar']) ? $shift['istirahat_keluar'] : '-';
											$s_ist_masuk = !empty($shift['istirahat_masuk']) ? $shift['istirahat_masuk'] : '-';
										}
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
										if ($is_flexible) {
											if ($has_masuk && $has_keluar) {
												$expected_keluar_time = date('H:i', strtotime($masuk) + ($expected_hours * 3600));
												if (is_time_early($pulang, $expected_keluar_time)) {
													$p_pulang = $globalDendaPulang;
												}
											}
											if ($has_masuk XOR $has_keluar) {
												$p_tidak_lengkap = $globalDendaTidakLengkap;
											}
										} else {
											$s_masuk_time = $shift['jam_masuk'];
											$s_keluar_time = $shift['jam_keluar'];
											$s_ist_masuk_time = $shift['istirahat_masuk'];
											$s_ist_keluar_time = $shift['istirahat_keluar'];

											if ($has_masuk && is_time_late($masuk, $s_masuk_time)) {
												$p_telat = $globalDendaMasuk;
											}
											if ($has_keluar && is_time_early($pulang, $s_keluar_time)) {
												$p_pulang = $globalDendaPulang;
											}
											if ($has_ist_keluar && is_time_early($istirahat_keluar, $s_ist_keluar_time)) {
												$p_ist_awal = $globalDendaIstirahatKeluar;
											}
											if ($has_ist_masuk && is_time_late($istirahat_masuk, $s_ist_masuk_time)) {
												$p_ist_telat = $globalDendaIstirahatMasuk;
											}

											// Absen Tidak Lengkap
											$hasIncompleteMain = ($has_masuk XOR $has_keluar);
											$isRestExpected = (!empty($s_ist_keluar_time) && $s_ist_keluar_time != '00:00:00');
											$hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));
											
											if ($hasIncompleteMain || $hasIncompleteBreak) {
												$p_tidak_lengkap = $globalDendaTidakLengkap;
											}
										}
									}

									$key_lembur = $PIN . '_' . $date;
									if (isset($custom_lembur[$key_lembur])) {
										$final_lembur_jam = $custom_lembur[$key_lembur];
									} else {
										// Auto-calculated lembur hours for flexible shift
										$final_lembur_jam = 0;
										if ($is_flexible && $has_masuk && $has_keluar) {
											$work_duration = (strtotime($pulang) - strtotime($masuk));
											if ($work_duration > 43200) $work_duration -= 86400;
											elseif ($work_duration < -43200) $work_duration += 86400;
											
											$work_hours = $work_duration / 3600;
											if ($work_hours > $expected_hours) {
												$final_lembur_jam = round($work_hours - $expected_hours, 1);
											}
										}
									}
									
									$lembur_money = $final_lembur_jam * $globalLemburPerJam;
									
									$total_potongan = $p_telat + $p_pulang + $p_ist_awal + $p_ist_telat + $p_tidak_lengkap;
									$upah_bersih = $upah - $total_potongan + $lembur_money;
									if ($upah_bersih < 0) $upah_bersih = 0;

									$hasil_kerja = ($has_masuk || $has_keluar) ? 'HADIR' : 'TIDAK HADIR';

									$is_late_masuk = ($p_telat > 0);
									$is_early_pulang = ($p_pulang > 0);
									$is_early_istirahat = ($p_ist_awal > 0);
									$is_late_istirahat = ($p_ist_telat > 0);
								?>
									<tr class="hover:bg-gray-50 transition-colors">
										<td class="py-2 px-2 text-center align-middle">
											<input type="checkbox" name="selected_logs[]" value="<?= htmlspecialchars($PIN . '|' . $date) ?>" class="log-checkbox rounded text-blue-600 focus:ring-blue-500 cursor-pointer" />
										</td>
										<td data-label="No" class="py-2 px-2 text-[15px] text-center text-gray-500"><?php echo $no++; ?></td>
										<td data-label="Tanggal" class="py-2 px-2 text-[15px] text-gray-700"><?php echo date('d-m-Y', strtotime($date)); ?></td>
										<td data-label="No Absen" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($PIN); ?></td>
										<td data-label="Nama Karyawan" class="py-2 px-2 text-[15px] font-bold text-gray-900"><?php echo htmlspecialchars($nama_karyawan); ?></td>
										<td data-label="Departemen" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($bagian); ?></td>
										<td data-label="Sub Bagian" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($sub_bagian); ?></td>
										<td data-label="OS/DHK" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($os_dhk); ?></td>
										<td data-label="Golongan" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($golongan); ?></td>
										<td data-label="Jam Masuk" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_masuk); ?></td>
										<td data-label="Jam Pulang" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_keluar); ?></td>
										<td data-label="Istirahat Keluar" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_ist_keluar); ?></td>
										<td data-label="Istirahat Masuk" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_ist_masuk); ?></td>
										<td data-label="Absen Masuk" class="py-2 px-2 text-[15px] text-center font-semibold text-gray-900 <?= ($is_late_masuk) ? 'bg-red-custom' : '' ?>"><?php echo !empty($masuk) ? htmlspecialchars($masuk) : '-'; ?></td>
										<td data-label="Absen Pulang" class="py-2 px-2 text-[15px] text-center font-semibold text-gray-900 <?= ($is_early_pulang) ? 'bg-red-custom' : '' ?>"><?php echo !empty($pulang) ? htmlspecialchars($pulang) : '-'; ?></td>
										<td data-label="Absen Istirahat Keluar" class="py-2 px-2 text-[15px] text-center text-gray-600 <?= ($is_early_istirahat) ? 'bg-red-custom' : '' ?>"><?php echo !empty($istirahat_keluar) ? htmlspecialchars($istirahat_keluar) : '-'; ?></td>
										<td data-label="Absen Istirahat Masuk" class="py-2 px-2 text-[15px] text-center text-gray-600 <?= ($is_late_istirahat) ? 'bg-red-custom' : '' ?>"><?php echo !empty($istirahat_masuk) ? htmlspecialchars($istirahat_masuk) : '-'; ?></td>
										<td data-label="Upah Pokok" class="py-2 px-2 text-[15px] text-right text-gray-600 font-semibold"><?php echo rupiah($upah); ?></td>
										<td data-label="Lembur" class="py-2 px-2 text-[15px] text-right text-gray-600"><?= rupiah($lembur_money) . ' (' . $final_lembur_jam . ' Jam)' ?></td>
										<td data-label="Pot. Telat" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_telat > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_telat); ?></td>
										<td data-label="Pot. Istirahat (Awal)" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_ist_awal > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_ist_awal); ?></td>
										<td data-label="Pot. Istirahat (Telat)" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_ist_telat > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_ist_telat); ?></td>
										<td data-label="Denda Pulang" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_pulang > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_pulang); ?></td>
										<td data-label="Denda Tidak Absen" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_tidak_lengkap > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_tidak_lengkap); ?></td>
										<td data-label="Pot. Lain" class="py-2 px-2 text-[15px] text-right text-gray-600"><?php echo rupiah(0); ?></td>
										<td data-label="Upah Setelah Potongan" class="py-2 px-2 text-[15px] text-right font-bold text-blue-600"><?php echo rupiah($upah_bersih); ?></td>
										<td data-label="Hasil" class="py-2 px-2 text-[15px] text-center font-bold <?php echo $hasil_kerja == 'HADIR' ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $hasil_kerja; ?></td>
										<td data-label="Aksi" class="py-2 px-2 text-center align-middle">
											<div class="flex gap-1 justify-center">
												<?php 
												$user_role = strtolower($_SESSION['role'] ?? '');
												$can_edit = ($user_role === 'kepala pabrik' || $user_role === 'admin master' || $user_role === 'owner');
												if ($can_edit): 
												?>
												<button type="button" class="btn btn-xs btn-primary btn-edit-lembur shadow-sm" data-pin="<?= htmlspecialchars($PIN) ?>" data-date="<?= htmlspecialchars($date) ?>" data-nama="<?= htmlspecialchars($nama_karyawan) ?>" data-lembur-jam="<?= $final_lembur_jam ?>" style="border-radius:4px; padding:3px 8px;">
													<i class="fa fa-clock"></i> Lembur
												</button>
												<?php endif; ?>
												<button type="button" class="btn btn-xs btn-danger btn-hapus-single shadow-sm" data-log-key="<?= htmlspecialchars($PIN . '|' . $date) ?>" style="border-radius:4px; padding:3px 8px;">
													<i class="fa fa-trash-alt"></i> Hapus
												</button>
											</div>
										</td>
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

			// Submit single delete
			$('#dataTables-example').on('click', '.btn-hapus-single', function(e) {
				e.preventDefault();
				var logKey = $(this).data('log-key');
				
				Swal.fire({
					title: 'Apakah Anda yakin?',
					text: 'Data absensi karyawan ini akan dihapus permanen!',
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
						$form.append('<input type="hidden" class="appended-log-checkbox" name="selected_logs[]" value="' + logKey + '" />');
						$form.append('<input type="hidden" class="appended-log-checkbox" name="hapus_pilih" value="1" />');
					}
				});
			});
			// Edit Lembur via SweetAlert2 Input
			$('#dataTables-example').on('click', '.btn-edit-lembur', function(e) {
				e.preventDefault();
				var pin = $(this).data('pin');
				var date = $(this).data('date');
				var nama = $(this).data('nama');
				var currentVal = $(this).data('lembur-jam');

				Swal.fire({
					title: 'Atur Jam Lembur',
					html: 'Masukkan jumlah jam lembur untuk <b>' + nama + '</b> pada tanggal ' + date + ':',
					input: 'number',
					inputAttributes: {
						min: 0,
						step: 0.1
					},
					inputValue: currentVal,
					showCancelButton: true,
					confirmButtonColor: '#3b82f6',
					cancelButtonColor: '#6b7280',
					confirmButtonText: 'Simpan',
					cancelButtonText: 'Batal',
					inputValidator: (value) => {
						if (!value && value !== '0') {
							return 'Anda harus memasukkan angka jam lembur!';
						}
					}
				}).then((result) => {
					if (result.isConfirmed) {
						$.post('', {
							update_lembur_ajax: 1,
							userid: pin,
							tgl: date,
							lembur_jam: result.value
						}, function(response) {
							Swal.fire({
								icon: 'success',
								title: 'Berhasil',
								text: 'Data lemburan berhasil diperbarui.',
								timer: 1500,
								showConfirmButton: false
							}).then(() => {
								location.reload();
							});
						}, 'json');
					}
				});
			});
		});
	</script>
</body>

</html>