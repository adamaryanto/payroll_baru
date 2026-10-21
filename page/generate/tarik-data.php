<?php

set_time_limit(0); // unlimited
ignore_user_abort(true);

// Include device sync helper for multi-device support
include_once __DIR__ . '/../../device_sync_helper.php';
create_sync_log_table($koneksi);

// Get all devices
$devices = get_all_devices($koneksi);

// Get date range
$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));

// Mode tampilan/kerja: semua (default, perilaku lama) | rencana (tarik + tabel rencana saja) | tdr (panel tidak-dalam-rencana saja, tanpa pull)
$mode_tarik = strtolower(trim((string)($_GET['mode'] ?? 'semua')));
if (!in_array($mode_tarik, ['semua', 'rencana', 'tdr'], true)) $mode_tarik = 'semua';

// Handle multi-device pull
$pull_results = null;
if ((isset($_GET['pull_all']) || isset($_POST['pull_all'])) && $mode_tarik !== 'tdr') {
    // Pull from all devices
    $pull_results = pull_attendance_from_all_devices($koneksi, $TanggalAwal, $TanggalAkhir);
    
    // Log the operation
    foreach($pull_results['per_device'] as $device_id => $result) {
        if($result['online']) {
            log_sync_activity($koneksi, 'attendance_pull', $device_id, null, 
                             'success', "Pulled {$result['records_pulled']} records, saved {$result['records_saved']}");
        }
    }
}

// For backward compatibility (single device selection)
$selected_device = null;
$selected_ip = $_GET['ip'] ?? '';
$selected_key = $_GET['key'] ?? "0";

if(!empty($selected_ip)) {
    foreach($devices as $device) {
        if($device['ip_mesin'] == $selected_ip) {
            $selected_device = $device;
            break;
        }
    }
}

// If no specific device selected, use first device for legacy display
if(!$selected_device && count($devices) > 0) {
    $selected_device = $devices[0];
}

$IP = $selected_device['ip_mesin'] ?? '';
$Key = $selected_device['comm_key'] ?? "0";
$port = $selected_device['port'] ?? '';
$namamesin = $selected_device['nama_mesin'] ?? '';
$did_pull = (int)($selected_device['id_mesin'] ?? 0);

// Pre-connection status check to selected machine (for legacy display)
$isConnected = false;
if (!empty($IP)) {
	$socket = @fsockopen($IP, $port, $errno, $errstr, 1.5);
	if ($socket) {
		$isConnected = true;
		fclose($socket);
	}
}

// Peta PIN -> nama di mesin (untuk PIN yang belum ada di master).
// Dihitung di sini (1x) agar dipakai tabel utama maupun panel TDR.
// Hanya bila ada PIN tak dikenal di rentang ini (hemat waktu bila mesin offline).
// Semula di dalam tabel utama; dipindah agar mode TDR (tabel utama di-skip) tetap dapat datanya.
$dev_names_map = [];
if ($IP != "" || $mode_tarik === 'tdr') {
	$qneed = $koneksi->query("SELECT 1 FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' AND TRIM(userid) <> '' AND NOT EXISTS (SELECT 1 FROM ms_karyawan k WHERE k.no_absen IS NOT NULL AND TRIM(k.no_absen) <> '' AND TRIM(k.no_absen) = TRIM(tb_record.userid)) LIMIT 1");
	if ($qneed && $qneed->num_rows > 0 && function_exists('get_device_user_names')) $dev_names_map = get_device_user_names($koneksi);
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

// Tabel pengganti (tidak masuk) — dibuat otomatis bila belum ada
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_ganti (
	id INT AUTO_INCREMENT PRIMARY KEY,
	tgl DATE NOT NULL,
	id_karyawan_absen INT NOT NULL DEFAULT 0,
	id_karyawan_ganti INT NOT NULL DEFAULT 0,
	upah_bayar DOUBLE NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uq_ganti (tgl, id_karyawan_absen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Simpan pengganti (idempotent: UNIQUE cegah dobel submit/refresh)
$ganti_msg = "";
if (isset($_POST['ganti_simpan']) && !empty($_POST['ganti_tgl']) && !empty($_POST['ganti_absen']) && !empty($_POST['ganti_oleh'])) {
	$gtgl = $koneksi->real_escape_string($_POST['ganti_tgl']);
	$gabs = (int)$_POST['ganti_absen'];
	$goleh = (int)$_POST['ganti_oleh'];
	$qk = $koneksi->query("SELECT id_karyawan, upah FROM ms_karyawan WHERE id_karyawan='$gabs' LIMIT 1");
	$qk2 = $koneksi->query("SELECT id_karyawan, nama_karyawan, upah FROM ms_karyawan WHERE id_karyawan='$goleh' AND status_karyawan='Aktif' LIMIT 1");
	if ($qk && $qk->num_rows > 0 && $qk2 && $qk2->num_rows > 0) {
		$rw2 = $qk2->fetch_assoc();
		$ub = (float)$rw2['upah'];
		$koneksi->query("INSERT INTO tb_ganti (tgl, id_karyawan_absen, id_karyawan_ganti, upah_bayar) VALUES ('$gtgl', '$gabs', '$goleh', '$ub') ON DUPLICATE KEY UPDATE id_karyawan_ganti='$goleh', upah_bayar='$ub'");
		$ganti_msg = "Tersimpan: digantikan oleh " . $rw2['nama_karyawan'] . ".";
	} else {
		$ganti_msg = "Gagal: data karyawan tidak valid.";
	}
}

// Batalkan pengganti (baris PENGGANTI di tabel masuk ikut hilang otomatis)
if (isset($_POST['ganti_batal']) && !empty($_POST['ganti_tgl']) && isset($_POST['ganti_absen'])) {
	$gtgl = $koneksi->real_escape_string($_POST['ganti_tgl']);
	$gabs = (int)$_POST['ganti_absen'];
	$koneksi->query("DELETE FROM tb_ganti WHERE tgl='$gtgl' AND id_karyawan_absen='$gabs'");
	if ($koneksi->affected_rows > 0) {
		$ganti_msg = "Dibatalkan: data pengganti dihapus.";
	} else {
		$ganti_msg = "Gagal: data pengganti tidak ditemukan.";
	}
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
		#tabel-tarik-data_wrapper .row:first-child {
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

			#tabel-tarik-data_wrapper {
				padding: 0 10px;
			}

			#tabel-tarik-data_wrapper .row:first-child {
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

			#tabel-tarik-data {
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
				<div class="d-flex justify-content-between align-items-center flex-wrap">
					<h3 class="text-xl text-blue-600 font-bold m-0"><i class="fas fa-download mr-2"></i>Download Log Data</h3>
					<div class="mt-2 mt-md-0">
						<span class="font-bold text-gray-500 text-xs uppercase mr-1">Status Mesin:</span>
						<?php if ($isConnected) : ?>
							<span class="badge bg-green-100 text-green-800 border border-green-200 px-3 py-1.5 rounded font-bold shadow-sm"><i class="fas fa-circle text-green-500 mr-1"></i> Connected</span>
						<?php else : ?>
							<span class="badge bg-red-100 text-red-800 border border-red-200 px-3 py-1.5 rounded font-bold shadow-sm"><i class="fas fa-times-circle text-red-500 mr-1"></i> Disconnected</span>
						<?php endif; ?>
					</div>
				</div>
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

			if (!function_exists('mangkir_tgl_aktif')) {
				// Periode tanggal aturan mangkir (kosong = tanpa batas sisi itu)
				function mangkir_tgl_aktif($dari, $sampai, $tglYmd) {
					if (!empty($dari) && strcmp($tglYmd, $dari) < 0) return false;
					if (!empty($sampai) && strcmp($tglYmd, $sampai) > 0) return false;
					return true;
				}
			}

			// Tetap mempertahankan variabel asli dan komentar Anda
			//include "koneksi.php";
			//$IP= $HTTP_GET_VARS["121.0.0.144"];
			//$Key= $HTTP_GET_VARS[""];
			$IP = $_GET['ip'] ?? ($selected_device['ip_mesin'] ?? '');
			$Key = $_GET['key'] ?? ($selected_device['comm_key'] ?? "0");
			$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
			$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
			if ($IP == "") $IP = "192.168.110.201";
			if ($Key == "") $Key = "";
			// Param pagination bar atas (diparse awal agar dropdown & input Cari tampil benar)
			$per_hal_opts = [10, 25, 50, 100];
			$per_halaman_raw = $_GET['per_hal'] ?? 25;
			$show_all = ((string)$per_halaman_raw === '-1' || strtolower((string)$per_halaman_raw) === 'semua');
			$per_halaman = $show_all ? 0 : (int)$per_halaman_raw;
			if (!$show_all && !in_array($per_halaman, $per_hal_opts, true)) $per_halaman = 25;
			$cari_tarik = trim((string)($_GET['cari'] ?? ''));
			// Param pagination tabel TIDAK ABSEN (dibedakan: hal_ns / per_hal_ns / cari_ns)
			$per_hal_ns_opts = [10, 25, 50, 100];
			$per_hal_ns_raw = $_GET['per_hal_ns'] ?? 25;
			$show_all_ns = ((string)$per_hal_ns_raw === '-1' || strtolower((string)$per_hal_ns_raw) === 'semua');
			$per_hal_ns = $show_all_ns ? 0 : (int)$per_hal_ns_raw;
			if (!$show_all_ns && !in_array($per_hal_ns, $per_hal_ns_opts, true)) $per_hal_ns = 25;
			$cari_ns = trim((string)($_GET['cari_ns'] ?? ''));
			// Param pagination tabel TIDAK DALAM RENCANA (dibedakan: hal_tdr / per_hal_tdr / cari_tdr)
			$per_hal_tdr_opts = [10, 25, 50, 100];
			$per_hal_tdr_raw = $_GET['per_hal_tdr'] ?? 25;
			$show_all_tdr = ((string)$per_hal_tdr_raw === '-1' || strtolower((string)$per_hal_tdr_raw) === 'semua');
			$per_hal_tdr = $show_all_tdr ? 0 : (int)$per_hal_tdr_raw;
			if (!$show_all_tdr && !in_array($per_hal_tdr, $per_hal_tdr_opts, true)) $per_hal_tdr = 25;
			$cari_tdr = trim((string)($_GET['cari_tdr'] ?? ''));
			?>

			<form action="" method="GET">
				<input type="hidden" name="page" value="generate" />
				<input type="hidden" name="aksi" value="tarik" />
				<input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode_tarik); ?>" />
				<div class="p-4 md:p-5 bg-gray-50 border-b border-gray-100">
					<div class="row items-end">
						<div class="col-md-3 col-sm-12 mb-3 mb-md-0">
							<label class="font-bold text-gray-700 text-sm uppercase tracking-wide">Pilih Mesin:</label>
							<select name="ip" class="form-control text-base py-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200" onchange="updateCommKey(this)">
								<option value="">- Pilih Mesin -</option>
								<?php foreach($devices as $device): 
									$is_online = !empty($device['ip_mesin']) ? test_device_connection($device['ip_mesin'], $device['port']) : false;
									$selected = ($IP == $device['ip_mesin']) ? 'selected' : '';
								?>
								<option value="<?php echo htmlspecialchars($device['ip_mesin']); ?>" 
										data-key="<?php echo htmlspecialchars($device['comm_key']); ?>"
										data-port="<?php echo htmlspecialchars($device['port']); ?>"
										<?php echo $selected; ?>
										<?php echo !$is_online ? 'style="color:#999;"' : ''; ?>>
									<?php echo htmlspecialchars($device['nama_mesin']); ?> 
									(<?php echo htmlspecialchars($device['ip_mesin']); ?>)
									<?php echo !$is_online ? ' [OFFLINE]' : ''; ?>
								</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-1 col-sm-12 mb-3 mb-md-0">
							<label class="font-bold text-gray-700 text-sm uppercase tracking-wide">Comm Key:</label>
							<input type="text" name="key" id="comm_key_input" value="<?php echo $Key; ?>" placeholder="0" class="form-control text-base py-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-blue-200" readonly />
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

			<script>
			// Auto-update Comm Key when device is selected
			function updateCommKey(select) {
				var selectedOption = select.options[select.selectedIndex];
				var commKey = selectedOption.getAttribute('data-key') || "0";
				var port = selectedOption.getAttribute('data-port') || "4370";
				
				document.getElementById('comm_key_input').value = commKey;
				
				// Optional: Show port info
				console.log('Selected device: ' + selectedOption.text + ', Comm Key: ' + commKey + ', Port: ' + port);
			}
			
			// Initialize on page load
			document.addEventListener('DOMContentLoaded', function() {
				var deviceSelect = document.querySelector('select[name="ip"]');
				if(deviceSelect && deviceSelect.value) {
					updateCommKey(deviceSelect);
				}
			});
			</script>

			<!-- Multi-Device Pull Section -->
			<div class="mb-6 p-5 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-100">
				<h4 class="text-lg font-semibold text-blue-800 mb-3 flex items-center">
					<i class="fas fa-sync-alt mr-2"></i>Tarik Data dari Semua Mesin
				</h4>
				<p class="text-gray-600 mb-4">Tarik data absensi dari semua mesin yang terkoneksi secara sekaligus.</p>
				
				<?php if($pull_results): ?>
				<div class="mb-4 p-4 bg-white rounded-lg border border-gray-200">
					<h5 class="font-semibold text-gray-800 mb-2">Hasil Tarik Data:</h5>
					<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
						<div class="p-3 bg-blue-50 rounded">
							<p class="text-sm text-blue-600">Total Devices</p>
							<p class="text-xl font-bold"><?php echo $pull_results['total_devices']; ?></p>
						</div>
						<div class="p-3 bg-green-50 rounded">
							<p class="text-sm text-green-600">Online</p>
							<p class="text-xl font-bold"><?php echo $pull_results['online_devices']; ?></p>
						</div>
						<div class="p-3 bg-purple-50 rounded">
							<p class="text-sm text-purple-600">Records Saved</p>
							<p class="text-xl font-bold"><?php echo $pull_results['records_saved']; ?></p>
						</div>
						<div class="p-3 bg-amber-50 rounded">
							<p class="text-sm text-amber-600">Duplicates Ignored</p>
							<p class="text-xl font-bold"><?php echo $pull_results['duplicates_ignored']; ?></p>
						</div>
					</div>
					
					<div class="mt-4">
						<h6 class="font-medium text-gray-700 mb-2">Status per Device:</h6>
						<div class="space-y-2">
							<?php foreach($pull_results['per_device'] as $device_id => $result): 
								$device = array_filter($devices, function($d) use ($device_id) {
									return $d['id_mesin'] == $device_id;
								});
								$device = reset($device);
							?>
							<div class="flex items-center justify-between p-2 bg-white border rounded">
								<div>
									<p class="font-medium"><?php echo htmlspecialchars($device['nama_mesin'] ?? 'Unknown'); ?></p>
									<p class="text-sm text-gray-500"><?php echo htmlspecialchars($device['ip_mesin'] ?? ''); ?></p>
								</div>
								<div class="text-right">
									<span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $result['online'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
										<?php echo $result['online'] ? 'ONLINE' : 'OFFLINE'; ?>
									</span>
									<?php if($result['online']): ?>
									<p class="text-sm text-gray-600 mt-1">
										<?php echo $result['records_saved']; ?> saved, 
										<?php echo $result['duplicates_ignored']; ?> ignored
									</p>
									<?php endif; ?>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>
				
				<div class="flex items-center justify-between flex-wrap" style="gap:10px;">
					<div>
						<p class="text-sm text-gray-500">Total mesin terkoneksi: <span class="font-bold text-blue-600"><?php echo count($devices); ?></span></p>
						<p class="text-sm text-gray-500">Rentang tanggal: <span class="font-bold text-blue-600"><?php echo $TanggalAwal; ?> s/d <?php echo $TanggalAkhir; ?></span></p>
						<?php if ($mode_tarik !== 'semua') : ?>
						<p class="text-sm font-bold <?php echo $mode_tarik === 'rencana' ? 'text-emerald-600' : 'text-amber-600'; ?>">Mode: <?php echo $mode_tarik === 'rencana' ? 'Tarik Sesuai Rencana' : 'Tidak Dalam Rencana'; ?> — <a href="?page=generate&aksi=tarik&tanggal_awal=<?php echo $TanggalAwal; ?>&tanggal_akhir=<?php echo $TanggalAkhir; ?>" class="underline">kembali ke Semua</a></p>
						<?php endif; ?>
					</div>
					<div class="flex flex-wrap" style="gap:8px;">
					<form method="GET" class="inline">
						<input type="hidden" name="page" value="generate">
						<input type="hidden" name="aksi" value="tarik">
						<input type="hidden" name="tanggal_awal" value="<?php echo $TanggalAwal; ?>">
						<input type="hidden" name="tanggal_akhir" value="<?php echo $TanggalAkhir; ?>">
						<input type="hidden" name="pull_all" value="1">
						<button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
							<i class="fas fa-play-circle"></i>
							Tarik dari Semua Mesin
						</button>
					</form>
					<form method="GET" class="inline">
						<input type="hidden" name="page" value="generate">
						<input type="hidden" name="aksi" value="tarik">
						<input type="hidden" name="tanggal_awal" value="<?php echo $TanggalAwal; ?>">
						<input type="hidden" name="tanggal_akhir" value="<?php echo $TanggalAkhir; ?>">
						<input type="hidden" name="pull_all" value="1">
						<input type="hidden" name="mode" value="rencana">
						<button type="submit" title="Tarik mesin + tampilkan hanya tabel sesuai rencana (tanpa panel Tidak Absen / Tidak Dalam Rencana / Ganti)" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
							<i class="fas fa-bolt"></i>
							Tarik Sesuai Rencana
						</button>
					</form>
					<form method="GET" class="inline">
						<input type="hidden" name="page" value="generate">
						<input type="hidden" name="aksi" value="tarik">
						<input type="hidden" name="tanggal_awal" value="<?php echo $TanggalAwal; ?>">
						<input type="hidden" name="tanggal_akhir" value="<?php echo $TanggalAkhir; ?>">
						<input type="hidden" name="mode" value="tdr">
						<button type="submit" title="Tanpa tarik mesin — tampilkan hanya tap yang tidak masuk rencana" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
							<i class="fas fa-exclamation-triangle"></i>
							Tidak Dalam Rencana
						</button>
					</form>
					</div>
				</div>
				
				<div class="mt-4 p-3 bg-blue-100 border border-blue-200 rounded text-sm text-blue-800">
					<p class="font-medium mb-1"><i class="fas fa-info-circle mr-1"></i> Aturan Deduplikasi:</p>
					<ul class="list-disc pl-5 space-y-1">
						<li>Tap apa pun dalam 10 menit (mesin sama / beda) → Abaikan (cegah double input)</li>
						<li>Jarak >10 menit → Simpan</li>
					</ul>
				</div>
			</div>

			<div class="p-0">
				<?php if ($mode_tarik !== 'tdr') : ?>
				<form id="form-tarik-data" method="POST" action="">
				<?php endif; ?>
				<div class="table-responsive px-3 py-4">
					<?php if ($IP != "" && $mode_tarik !== 'tdr') : ?>
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
						<!-- Bar atas pagination: Tampilkan (per halaman) + Cari (server-side) -->
						<div class="d-flex justify-content-between align-items-center flex-wrap mb-3 px-1" style="gap:10px;">
							<div class="d-flex align-items-center" style="gap:8px;font-size:14px;color:#334155;">
								<label class="m-0" for="per-hal-select">Tampilkan</label>
								<select id="per-hal-select" class="form-control" style="width:auto;display:inline-block;padding:5px 10px;border:1px solid #e0e6ed;border-radius:8px;">
									<option value="10" <?php echo (!$show_all && $per_halaman == 10) ? 'selected' : ''; ?>>10</option>
									<option value="25" <?php echo (!$show_all && $per_halaman == 25) ? 'selected' : ''; ?>>25</option>
									<option value="50" <?php echo (!$show_all && $per_halaman == 50) ? 'selected' : ''; ?>>50</option>
									<option value="100" <?php echo (!$show_all && $per_halaman == 100) ? 'selected' : ''; ?>>100</option>
									<option value="-1" <?php echo $show_all ? 'selected' : ''; ?>>Semua</option>
								</select>
								<span>data</span>
							</div>
							<div class="d-flex align-items-center" style="gap:8px;font-size:14px;color:#334155;">
								<label class="m-0" for="cari-tarik-input">Cari:</label>
								<input type="text" id="cari-tarik-input" value="<?php echo htmlspecialchars($cari_tarik); ?>" placeholder="Cari nama / no absen..." class="form-control" style="width:200px;padding:6px 12px;border:1px solid #e0e6ed;border-radius:8px;" />
								<button type="button" id="cari-tarik-btn" class="btn btn-primary btn-sm shadow-sm">Cari</button>
								<?php if ($cari_tarik !== '') :
									$reset_params = $_GET;
									$reset_params['page'] = 'generate';
									$reset_params['aksi'] = 'tarik';
									unset($reset_params['cari'], $reset_params['hal']);
								?>
								<a href="?<?php echo htmlspecialchars(http_build_query($reset_params)); ?>" class="btn btn-default btn-sm" style="border:1px solid #e0e6ed;">Reset</a>
								<?php endif; ?>
							</div>
						</div>
						<table class="w-full text-left border-collapse table-modern" id="tabel-tarik-data">
							<thead class="bg-gray-50 border-b border-gray-200">
								<tr>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center w-[40px]">
										<input type="checkbox" id="select-all" class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer" />
									</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle text-center">No</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Tanggal</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">No Absen</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Nama Karyawan</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Bagian</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Sub Bagian</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">OS/DHK</th>
									<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Golongan</th>
								<th class="py-3 px-2 text-[12px] font-bold text-gray-700 uppercase align-middle">Grup</th>
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

								$should_connect = $isConnected;
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
									// Dedup <10 menit ANY device (termasuk mesin itu sendiri) - samakan dengan should_save_attendance()
									$last_time_map = [];
									$last_q = $koneksi->query("SELECT userid, tgl, MAX(detail_waktu) as last_dt FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' GROUP BY userid, tgl");
									if($last_q){ while($lr=$last_q->fetch_assoc()){ $last_time_map[$lr['userid'].'_'.$lr['tgl']] = strtotime($lr['last_dt']); } }
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
												if (isset($existing_logs[$key])) continue;
												$mk = $PIN.'_'.$date_only;
												$new_ts = strtotime($DateTime);
												if(isset($last_time_map[$mk])){
													$diff_min = ($new_ts - $last_time_map[$mk]) / 60;
													if($diff_min >=0 && $diff_min <= 10) continue;
												}
												$data_esc = mysqli_real_escape_string($koneksi, $data_raw);
												$insert_values[] = "('$data_esc','$PIN','$date_only','$Verified','$Status','$DateTime','$did_pull')";
												$existing_logs[$key] = true;
												$last_time_map[$mk] = $new_ts;
											}
										}
									}
									
									if (!empty($insert_values)) {
										$chunks = array_chunk($insert_values, 100);
										foreach ($chunks as $chunk) {
											$sql = "INSERT INTO tb_record (data,userid,tgl,verifikasi,status,detail_waktu,source_device_id) VALUES " . implode(", ", $chunk);
											$koneksi->query($sql);
										}
									}
								} else {
									echo "<div class='alert alert-warning text-center font-bold' style='margin-bottom:15px; border-radius:8px;'><i class='fa fa-exclamation-triangle'></i> Gagal terhubung ke mesin absensi (IP: $IP). Menampilkan data lokal ter-update.</div>";
								}

								// Query and group data - ambil 1 hari sebelum biar shift malam (18:04 + 06:09) bisa ke-merge walau filter cuma tgl 7
								$TanggalAwalMinus1 = date('Y-m-d', strtotime($TanggalAwal.' -1 day'));
								$db_records = $koneksi->query("SELECT * FROM tb_record WHERE tgl BETWEEN '$TanggalAwalMinus1' AND '$TanggalAkhir' ORDER BY tgl ASC, id_record ASC");
								$grouped = [];
								// Roster per-tanggal: tgl|pin => shift (acuan utama; master cuma fallback)
								$rosterMap = [];
								if (!empty($TanggalAwalMinus1) && !empty($TanggalAkhir)) {
									$qr_roster = $koneksi->query("SELECT h.tgl_rkk AS tgl, TRIM(k.no_absen) AS pin, j.* FROM tb_rkk_detail rd JOIN tb_rkk h ON h.id_rkk=rd.id_rkk JOIN ms_karyawan k ON k.id_karyawan=rd.id_karyawan LEFT JOIN tb_jadwal j ON j.id_jadwal=rd.id_jadwal WHERE h.tgl_rkk BETWEEN '$TanggalAwalMinus1' AND '$TanggalAkhir'");
									if ($qr_roster) while ($rw_roster = $qr_roster->fetch_assoc()) $rosterMap[$rw_roster['tgl'] . '|' . trim((string)$rw_roster['pin'])] = $rw_roster;
								}

								while ($row_rec = $db_records->fetch_assoc()) {
									$PIN = $row_rec['userid'];
									$DateTime = $row_rec['detail_waktu'];
									$Verified = $row_rec['verifikasi'];
									$Status = $row_rec['status'];
									$rec_tgl = $row_rec['tgl'];

									$time = substr($DateTime, 11, 5); // "08:12"
									$is_merged = false;
									$prev_date = date('Y-m-d', strtotime('-1 day', strtotime($rec_tgl)));
									$prev_key = $PIN . '_' . $prev_date;
									if (isset($grouped[$prev_key]) && !empty($grouped[$prev_key]['masuk'])) {
										$prev_masuk = $grouped[$prev_key]['masuk'];
										$prev_pulang = $grouped[$prev_key]['pulang'] ?? '';
										
										$in_timestamp = strtotime($prev_date . ' ' . $prev_masuk);
										$curr_timestamp = strtotime($rec_tgl . ' ' . $time);
										$diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
										
										// Jika scan baru berada dalam rentang maks 16 jam dari absen masuk kemarin
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
												$prev_pulang_ts = !empty($prev_pulang) ? strtotime($prev_date . ' ' . $prev_pulang) : 0;
												if (empty($prev_pulang) || $curr_timestamp > $prev_pulang_ts) {
													$grouped[$prev_key]['pulang'] = $time;
												}
												$grouped[$prev_key]['raw_times'][] = [
													'time' => $time,
													'status' => $Status
												];
												$is_merged = true;
											}
										}
									}

									if (!$is_merged) {
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
								}

								// Fallback jika tombol status tidak ditekan di mesin
								foreach ($grouped as $key => &$row) {
									if (empty($row['masuk']) && empty($row['pulang']) && !empty($row['raw_times'])) {
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

								// Fix: kalau masuk == pulang (1 tap kehitung 2x), kosongkan pulang
								foreach ($grouped as $key => &$row) {
									if (!empty($row['masuk']) && $row['masuk'] == $row['pulang']) {
										$row['pulang'] = '';
									}
								}
								unset($row);

								// FIX shift malam: merge lintas hari yang terpisah (mis 06-09 18:04 + 07-09 06:09 → 06-09 18:04/06:09)
								$keys = array_keys($grouped);
								sort($keys);
								foreach($keys as $k){
									if(!isset($grouped[$k])) continue;
									if(empty($grouped[$k]['masuk']) || !empty($grouped[$k]['pulang'])) continue;
									list($pin_k, $date_k) = explode('_', $k, 2);
									$next_date = date('Y-m-d', strtotime($date_k.' +1 day'));
									$next_key = $pin_k.'_'.$next_date;
									if(!isset($grouped[$next_key])) continue;
									$next = $grouped[$next_key];
									if(!empty($next['masuk']) || empty($next['pulang'])){
										if(empty($next['pulang']) && !empty($next['masuk']) && count($next['raw_times'])==1){
											$tNext=$next['masuk'];
											if(strtotime("1970-01-01 $tNext") < strtotime("1970-01-01 12:00:00")){
												$next['pulang']=$next['masuk']; $next['masuk']='';
											} else continue;
										} else continue;
									}
									$in_ts = strtotime($date_k.' '.$grouped[$k]['masuk']);
									$out_ts = strtotime($next_date.' '.$next['pulang']);
									$diff = ($out_ts - $in_ts)/3600;
									if($diff>0 && $diff<=16){
										$grouped[$k]['pulang'] = $next['pulang'];
										$grouped[$k]['raw_times'] = array_merge($grouped[$k]['raw_times'], $next['raw_times']);
										unset($grouped[$next_key]);
									}
								}

								// Fix: tap tunggal berstatus pulang = anggap MASUK.
								// Mesin toggle in/out sendiri (kemarin masuk -> hari ini dianggap pulang),
								// padahal 1 tap sendirian hampir pasti check-in. (Merge malam di atas sudah selesai.)
								foreach ($grouped as $key => &$row) {
									if (empty($row['masuk']) && !empty($row['pulang']) && count($row['raw_times'] ?? []) <= 1) {
										$row['masuk'] = $row['pulang'];
										$row['pulang'] = '';
									}
								}
								unset($row);

								// Fix: mesin kirim semua status IN (default in) -> pulang tidak terisi.
								// Kalau sudah ada masuk tapi pulang kosong dan tap >1, pulang = tap terakhir.
								foreach ($grouped as $key => &$row) {
									if (!empty($row['masuk']) && empty($row['pulang']) && count($row['raw_times'] ?? []) > 1) {
										$times = array_column($row['raw_times'], 'time');
										rsort($times);
										if ($times[0] !== $row['masuk']) $row['pulang'] = $times[0];
									}
								}
								unset($row);

								// Saring tampilan sesuai rentang tanggal (H-1 hanya untuk merge shift malam, jangan tampil).
								foreach ($grouped as $gkf => $grf) {
									$dkf = $grf['date'] ?? '';
									if ($dkf === '' || $dkf < $TanggalAwal || $dkf > $TanggalAkhir) unset($grouped[$gkf]);
								}

								// ===== INJECT BARIS PENGGANTI (tb_ganti) ke tabel masuk =====
								// Pengganti yg disimpan di panel Tidak Absen tampil sbg baris PENGGANTI + upahnya.
								$qganti = $koneksi->query("SELECT g.tgl, g.id_karyawan_absen, g.id_karyawan_ganti, g.upah_bayar, TRIM(k.no_absen) AS pin_ganti, k2.nama_karyawan AS nama_absen FROM tb_ganti g LEFT JOIN ms_karyawan k ON k.id_karyawan=g.id_karyawan_ganti LEFT JOIN ms_karyawan k2 ON k2.id_karyawan=g.id_karyawan_absen WHERE g.tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
								if ($qganti) while ($rg = $qganti->fetch_assoc()) {
									$pin_g = trim((string)($rg['pin_ganti'] ?? ''));
									if ($pin_g === '') continue;
									$tgl_g = $rg['tgl'];
									$real_k = $pin_g . '_' . $tgl_g;
									if (isset($grouped[$real_k])) {
										// Penggantinya memang tap hari itu: baris aslinya tetap, cukup tambah nota.
										$grouped[$real_k]['ganti_absen_nama'] = $rg['nama_absen'] ?? '';
										continue;
									}
									$gk = 'GANTI_' . $tgl_g . '_' . (int)$rg['id_karyawan_ganti'] . '_' . (int)$rg['id_karyawan_absen'];
									if (isset($grouped[$gk])) continue;
									$grouped[$gk] = ['pin' => $pin_g, 'date' => $tgl_g, 'masuk' => '', 'istirahat_keluar' => '', 'istirahat_masuk' => '', 'pulang' => '', 'raw_times' => [], 'is_pengganti' => true, 'ganti_absen_nama' => $rg['nama_absen'] ?? '', 'ganti_upah' => (float)$rg['upah_bayar']];
								}
								uasort($grouped, function($ag, $bg) { $cg = strcmp($ag['date'], $bg['date']); return $cg !== 0 ? $cg : strcmp($ag['pin'], $bg['pin']); });

								// ===== PAGINATION PHP (server-side): filter + slice (param diparse di atas) =====
								$filtered_grouped = $grouped;
								if ($cari_tarik !== '') {
									// Peta karyawan (1x query) agar Cari bisa temukan nama/departemen
									$emp_map_cari = [];
									$qm_cari = $koneksi->query("SELECT k.no_absen, k.nama_karyawan, d.nama_departmen, sd.nama_sub_department FROM ms_karyawan k LEFT JOIN ms_departmen d ON k.id_departmen=d.id_departmen LEFT JOIN ms_sub_department sd ON k.id_sub_department=sd.id_sub_department");
									if ($qm_cari) while ($rm_cari = $qm_cari->fetch_assoc()) {
										$emp_map_cari[trim((string)$rm_cari['no_absen'])] = $rm_cari;
									}
									$filtered_grouped = [];
									$cari_lower = strtolower($cari_tarik);
									foreach ($grouped as $gk_cari => $gr_cari) {
										$em_cari = $emp_map_cari[trim((string)$gr_cari['pin'])] ?? null;
										$hay = strtolower($gr_cari['pin'] . ' ' . $gr_cari['date'] . ' ' . date('d-m-Y', strtotime($gr_cari['date'])) . ' ' . ($em_cari['nama_karyawan'] ?? '') . ' ' . ($dev_names_map[trim((string)$gr_cari['pin'])] ?? '') . ' ' . ($em_cari['nama_departmen'] ?? '') . ' ' . ($em_cari['nama_sub_department'] ?? ''));
										if (strpos($hay, $cari_lower) !== false) $filtered_grouped[$gk_cari] = $gr_cari;
									}
								}
								$total_data = count($filtered_grouped);
								if ($show_all || $total_data === 0) {
									$total_hal = 1; $hal_aktif = 1; $offset_hal = 0;
									$paged_grouped = $filtered_grouped;
									$per_halaman_eff = $total_data > 0 ? $total_data : 25;
								} else {
									$per_halaman_eff = $per_halaman;
									$total_hal = max(1, (int)ceil($total_data / $per_halaman_eff));
									$hal_aktif = isset($_GET['hal']) ? max(1, (int)$_GET['hal']) : 1;
									if ($hal_aktif > $total_hal) $hal_aktif = $total_hal;
									$offset_hal = ($hal_aktif - 1) * $per_halaman_eff;
									$paged_grouped = array_slice($filtered_grouped, $offset_hal, $per_halaman_eff, true);
								}

								$no = $offset_hal + 1;
								// Peta PIN -> grup master (1x query). PIN tak dikenal = TIDAK TERDAFTAR.
								$grup_map_pin = [];
								$ckGa = $koneksi->query("SHOW TABLES LIKE 'ms_grup_borongan_anggota'");
								if ($ckGa && $ckGa->num_rows > 0) {
									$qg = $koneksi->query("SELECT TRIM(k.no_absen) AS pin, GROUP_CONCAT(DISTINCT gb.nama_grup ORDER BY gb.nama_grup SEPARATOR ', ') AS grps FROM ms_grup_borongan_anggota ga JOIN ms_karyawan k ON k.id_karyawan = ga.id_karyawan JOIN ms_grup_borongan gb ON gb.id_grup = ga.id_grup WHERE TRIM(k.no_absen) <> '' GROUP BY TRIM(k.no_absen)");
									while ($qg && ($rg = $qg->fetch_assoc())) $grup_map_pin[$rg['pin']] = $rg['grps'];
								}
								// $dev_names_map dihitung di atas (sebelum tabel utama) agar panel TDR bisa pakai juga.
								if (!isset($dev_names_map)) $dev_names_map = [];
								foreach ($paged_grouped as $key => $row) :
									$PIN = $row['pin'];
									$date = $row['date'];
									$masuk = $row['masuk'];
									$istirahat_keluar = $row['istirahat_keluar'];
									$istirahat_masuk = $row['istirahat_masuk'];
									$pulang = $row['pulang'];
									$is_pengganti = !empty($row['is_pengganti']);
									$ganti_absen_nama = $row['ganti_absen_nama'] ?? '';
									$ganti_upah = (float)($row['ganti_upah'] ?? 0);
									$pengganti_note = '';

									// Cari karyawan di database lengkap dengan Departemen, Sub Departemen, OS/DHK, dan Golongan
									$empQuery = $koneksi->query("SELECT k.nama_karyawan, k.upah, k.id_jadwal, d.nama_departmen, sd.nama_sub_department, o.OS_DHK as label_os, g.golongan as label_gol 
																FROM ms_karyawan k 
																LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen 
																LEFT JOIN ms_sub_department sd ON k.id_sub_department = sd.id_sub_department
																LEFT JOIN ms_os_dhk o ON k.id_os_dhk = o.id_os_dhk
																LEFT JOIN ms_golongan g ON k.id_golongan = g.id_golongan
																WHERE k.no_absen = '$PIN' LIMIT 1");
								$emp = $empQuery->fetch_assoc();
								$pin_trim_early = trim((string)$PIN);
								$nama_karyawan = $emp['nama_karyawan'] ?? ($dev_names_map[$pin_trim_early] ?? 'Karyawan Tidak Dikenal');
									$bagian = $emp['nama_departmen'] ?? '-';
									$sub_bagian = $emp['nama_sub_department'] ?? '-';
								$os_dhk = $emp['label_os'] ?? 'MHS';
								$golongan = $emp['label_gol'] ?? 'Harian';
								$upah = $emp['upah'] ?? 0;
								// Grup master per PIN; yang tidak terdaftar di master = TIDAK TERDAFTAR
								$pin_trim_tarik = trim((string)$PIN);
								if (!$emp) $grup_tarik = 'TIDAK TERDAFTAR';
								elseif (!empty($grup_map_pin[$pin_trim_tarik])) $grup_tarik = $grup_map_pin[$pin_trim_tarik];
								else $grup_tarik = '-';

									// Acuan shift: roster tanggal ini bila ada, else master (fallback).
									// Jadi ubah roster tgl 8 tidak merembet ke tgl 7.
									$assigned_shift = null;
									$use_roster = false;
									$roster_key = $date . '|' . trim((string)$PIN);
									if (isset($rosterMap[$roster_key]) && !empty($rosterMap[$roster_key]['id_jadwal'])) {
										$assigned_shift = $rosterMap[$roster_key];
										$use_roster = true;
									} elseif (!empty($emp['id_jadwal'])) {
										$q_ass = $koneksi->query("SELECT * FROM tb_jadwal WHERE id_jadwal = '{$emp['id_jadwal']}'");
										if ($q_ass && $q_ass->num_rows > 0) {
											$assigned_shift = $q_ass->fetch_assoc();
										}
									}
									
									$use_assigned = $use_roster;
									if (!$use_roster && $assigned_shift) {
										if (strpos(strtoupper($assigned_shift['keterangan'] ?? ''), 'FLEXI') !== false || strpos(strtoupper($assigned_shift['keterangan'] ?? ''), 'FLEKSI') !== false) {
											$use_assigned = true;
										} else {
											if (!empty($masuk) && $masuk != '00:00' && $masuk != '00:00:00') {
												$in_time = strtotime("1970-01-01 " . $masuk);
												$shift_time = strtotime("1970-01-01 " . $assigned_shift['jam_masuk']);
												$diff = abs($in_time - $shift_time);
												if ($diff > 43200) {
													$diff = 86400 - $diff;
												}
												// Jika jam masuk riil selisihnya dalam batas 2 jam (7200 detik) dari jam masuk shift terdaftar
												if ($diff <= 7200) {
													$use_assigned = true;
												}
											} else {
												// Jika tidak absen masuk, gunakan shift terdaftar
												$use_assigned = true;
											}
										}
									}
									
									if ($use_assigned) {
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
										$is_flexible = (strpos(strtoupper($shift['keterangan'] ?? ''), 'FLEXI') !== false || strpos(strtoupper($shift['keterangan'] ?? ''), 'FLEKSI') !== false);
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
											
											$shift_in = $shift['jam_masuk'] ?? '00:00:00';
											$shift_out = $shift['jam_keluar'] ?? '00:00:00';
											$shift_duration_sec = strtotime($shift_out) - strtotime($shift_in);
											if ($shift_duration_sec < 0) {
												$shift_duration_sec += 86400;
											}
											$expected_hours = $shift_duration_sec / 3600;
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

									// *** DETEKSI ABSEN TANPA JADWAL YANG VALID ***
									$no_valid_schedule = false;
									$schedule_warning = '';
									
									// Jika karyawan tidak terdaftar di database (nama = "Karyawan Tidak Dikenal")
									if (($has_masuk || $has_keluar) && ($nama_karyawan == 'Karyawan Tidak Dikenal' || !$emp)) {
										$no_valid_schedule = true;
										$schedule_warning = 'KARYAWAN TIDAK TERDAFTAR - TIDAK DAPAT GAJI';
										$upah = 0; // Set upah jadi 0
									}
								// Jika ada absensi tapi tidak ada shift yang cocok sama sekali
								elseif (($has_masuk || $has_keluar) && !$shift) {
									$no_valid_schedule = true;
									$schedule_warning = 'ABSEN TANPA JADWAL - TIDAK DAPAT GAJI';
									$upah = 0; // Set upah jadi 0
								}

								if ($shift && ($has_masuk || $has_keluar) && !$no_valid_schedule) {
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

											// Toleransi dari tb_toleransi (page=denda)
											static $tolCache=null; if($tolCache===null){ $qTol=$koneksi->query("SELECT * FROM tb_toleransi LIMIT 1"); $tolCache=$qTol?$qTol->fetch_assoc():[]; }
											$tolMasuk=intval($tolCache['toleransi_masuk']??10); $tolPulang=intval($tolCache['toleransi_pulang']??5); $tolIstKel=intval($tolCache['toleransi_istirahat_keluar']??5); $tolIstMasuk=intval($tolCache['toleransi_istirahat_masuk']??10);
											$diffMasuk = $has_masuk ? (strtotime($masuk)-strtotime($s_masuk_time)+86400)%86400 : 0; if($diffMasuk>43200) $diffMasuk-=86400; $diffMasuk=intval($diffMasuk/60);
											if ($has_masuk && $diffMasuk > $tolMasuk) $p_telat = $globalDendaMasuk;
											$diffPulang = $has_keluar ? (strtotime($s_keluar_time)-strtotime($pulang)+86400)%86400 : 0; if($diffPulang>43200) $diffPulang-=86400; $diffPulang=intval($diffPulang/60);
											if ($has_keluar && $diffPulang > $tolPulang) $p_pulang = $globalDendaPulang;
											$diffIstKel = $has_ist_keluar ? (strtotime($s_ist_keluar_time)-strtotime($istirahat_keluar)+86400)%86400 : 0; if($diffIstKel>43200) $diffIstKel-=86400; $diffIstKel=intval($diffIstKel/60);
											if ($has_ist_keluar && $diffIstKel > $tolIstKel) $p_ist_awal = $globalDendaIstirahatKeluar;
											$diffIstMasuk = $has_ist_masuk ? (strtotime($istirahat_masuk)-strtotime($s_ist_masuk_time)+86400)%86400 : 0; if($diffIstMasuk>43200) $diffIstMasuk-=86400; $diffIstMasuk=intval($diffIstMasuk/60);
											if ($has_ist_masuk && $diffIstMasuk > $tolIstMasuk) $p_ist_telat = $globalDendaIstirahatMasuk;

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
									// Lembur = pulang melewati jam pulang shift (datang pagi tidak dihitung).
									// Flexi ikut rumus sama karena s_keluar = masuk + jam ekspektasi.
									$final_lembur_jam = 0;
									if ($has_masuk && $has_keluar && !empty($s_keluar) && $s_keluar !== '-' && $s_keluar !== 'Bebas') {
										$diff_plg = strtotime($pulang) - strtotime($s_keluar);
										if ($diff_plg > 43200) $diff_plg -= 86400;
										elseif ($diff_plg < -43200) $diff_plg += 86400;
										$ot_hours = $diff_plg / 3600;
										if ($ot_hours > 0) {
											$final_lembur_jam = round($ot_hours, 1);
										}
									}
								}
									
									$lembur_money = $final_lembur_jam * $globalLemburPerJam;
									
									$total_potongan = $p_telat + $p_pulang + $p_ist_awal + $p_ist_telat + $p_tidak_lengkap;
									
									// *** APPLY PERINGATAN JADWAL ***
									if ($no_valid_schedule) {
										$upah_bersih = 0; // Tidak dapat gaji sama sekali
									} else {
										$upah_bersih = $upah - $total_potongan + $lembur_money;
										if ($upah_bersih < 0) $upah_bersih = 0;
									}

									$hasil_kerja = ($has_masuk || $has_keluar) ? 'HADIR' : 'TIDAK HADIR';
									
									// Tambah warning ke hasil kerja jika ada masalah jadwal
									if ($no_valid_schedule && ($has_masuk || $has_keluar)) {
										$hasil_kerja = 'HADIR - ' . $schedule_warning;
									}

								// Merah pakai toleransi tb_toleransi (page=denda) — telat 10 menit baru merah
								static $tolRed=null; if($tolRed===null){ $qR=$koneksi->query("SELECT * FROM tb_toleransi LIMIT 1"); $tolRed=$qR?$qR->fetch_assoc():[]; }
								// Batas mangkir: telat >= N menit dihitung tidak masuk (display only, angka tidak diubah)
								static $mangkirMenit=null; static $mangkirDari=''; static $mangkirSampai='';
								if($mangkirMenit===null){ $qM=$koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_menit'"); if($qM&&$qM->num_rows>0){ $qD=$koneksi->query("SELECT mangkir_menit FROM tb_denda LIMIT 1"); $rD=$qD?$qD->fetch_assoc():[]; $mangkirMenit=max(0,(int)($rD['mangkir_menit']??0)); $qDt=$koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_dari_tgl'"); if($qDt&&$qDt->num_rows==0){ $koneksi->query("ALTER TABLE tb_denda ADD COLUMN mangkir_dari_tgl DATE NULL DEFAULT NULL, ADD COLUMN mangkir_sampai_tgl DATE NULL DEFAULT NULL"); } $qD2=$koneksi->query("SELECT mangkir_dari_tgl, mangkir_sampai_tgl FROM tb_denda LIMIT 1"); $rD2=$qD2?$qD2->fetch_assoc():[]; $mangkirDari=trim((string)($rD2['mangkir_dari_tgl']??'')); $mangkirSampai=trim((string)($rD2['mangkir_sampai_tgl']??'')); } else { $mangkirMenit=0; } }
								                                // (definisi mangkir_tgl_aktif ada di blok helper atas)
									$tolMasukR=intval($tolRed['toleransi_masuk']??10); $tolPulangR=intval($tolRed['toleransi_pulang']??5); $tolIstKelR=intval($tolRed['toleransi_istirahat_keluar']??5); $tolIstMasukR=intval($tolRed['toleransi_istirahat_masuk']??10);
									if($is_flexible){
										$exp_pulang_flex = (!empty($masuk) && $masuk!='00:00') ? date('H:i', strtotime($masuk) + ($expected_hours*3600)) : '';
										$is_late_masuk = false;
										if($has_keluar && $exp_pulang_flex!=''){
											$dFlex=(strtotime($exp_pulang_flex)-strtotime($pulang)+86400)%86400; if($dFlex>43200) $dFlex-=86400; $dFlex=intval($dFlex/60);
											$is_early_pulang = ($dFlex > $tolPulangR);
										} else $is_early_pulang=false;
									} else {
										$dMasuk = ($has_masuk && $s_masuk!='-') ? (intval((strtotime($masuk)-strtotime($s_masuk)+86400)%86400/60)) : 0; if($dMasuk>720) $dMasuk-=1440;
										$dPulang = ($has_keluar && $s_keluar!='-') ? (intval((strtotime($s_keluar)-strtotime($pulang)+86400)%86400/60)) : 0; if($dPulang>720) $dPulang-=1440;
										$is_late_masuk = ($has_masuk && $s_masuk!='-' && $dMasuk > $tolMasukR);
										$is_early_pulang = ($has_keluar && $s_keluar!='-' && $dPulang > $tolPulangR);
									}
									$dIstKel = ($has_ist_keluar && $s_ist_keluar!='-') ? (intval((strtotime($s_ist_keluar)-strtotime($istirahat_keluar)+86400)%86400/60)) : 0; if($dIstKel>720) $dIstKel-=1440;
									$dIstMasuk = ($has_ist_masuk && $s_ist_masuk!='-') ? (intval((strtotime($istirahat_masuk)-strtotime($s_ist_masuk)+86400)%86400/60)) : 0; if($dIstMasuk>720) $dIstMasuk-=1440;
									$is_early_istirahat = ($has_ist_keluar && $s_ist_keluar!='-' && $dIstKel > $tolIstKelR);
									$is_late_istirahat = ($has_ist_masuk && $s_ist_masuk!='-' && $dIstMasuk > $tolIstMasukR);
									// Telat melewati batas mangkir -> label TIDAK HADIR (angka tidak diubah).
									// Acuan: shift roster hari itu bila ada, else shift yg dipakai baris ini.
									// (Di sini karena $diffMasuk & $mangkirMenit baru ada di titik ini.)
									if ($hasil_kerja === 'HADIR' && $mangkirMenit > 0 && $has_masuk) {
									    if (!isset($rosterShiftMap)) {
									        $rosterShiftMap = [];
									        if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
									            $qr0 = $koneksi->query("SELECT h.tgl_rkk AS tgl, TRIM(k.no_absen) AS pin, j.jam_masuk FROM tb_rkk_detail rd JOIN tb_rkk h ON h.id_rkk=rd.id_rkk JOIN ms_karyawan k ON k.id_karyawan=rd.id_karyawan LEFT JOIN tb_jadwal j ON j.id_jadwal=rd.id_jadwal WHERE h.tgl_rkk BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
									            if ($qr0) while ($rw0 = $qr0->fetch_assoc()) $rosterShiftMap[$rw0['tgl'] . '|' . trim((string)$rw0['pin'])] = $rw0['jam_masuk'];
									        }
									    }
									    if (!mangkir_tgl_aktif($mangkirDari, $mangkirSampai, $date)) {
									        // di luar periode tanggal -> keine label mangkir
									    } else {
									    $refMasuk = $rosterShiftMap[$date . '|' . trim((string)$PIN)] ?? null;
									    if (!empty($refMasuk)) {
									        $dl0 = strtotime("1970-01-01 " . substr($masuk, 0, 5)) - strtotime("1970-01-01 " . substr($refMasuk, 0, 5));
									        if ($dl0 > 43200) $dl0 -= 86400;
									        elseif ($dl0 < -43200) $dl0 += 86400;
									        $dl0m = (int)($dl0 / 60);
									        if ($dl0m > $mangkirMenit) $hasil_kerja = 'TIDAK HADIR (telat ' . $dl0m . ' mnt)';
									    } elseif (isset($diffMasuk) && $diffMasuk > $mangkirMenit) {
									        $hasil_kerja = 'TIDAK HADIR (telat ' . $diffMasuk . ' mnt)';
									    }
									    } // tutup: cek periode bulan
									}
									// Override tampilan baris PENGGANTI (upah + status khusus)
									if ($is_pengganti) {
										$upah = $ganti_upah;
										$upah_bersih = $ganti_upah;
										$hasil_kerja = 'HADIR';
										$pengganti_note = 'PENGGANTI' . ($ganti_absen_nama !== '' ? ' — menggantikan ' . $ganti_absen_nama : '');
									} elseif ($ganti_absen_nama !== '') {
										$pengganti_note = 'Juga menggantikan ' . $ganti_absen_nama;
									}
								?>
									<tr class="hover:bg-gray-50 transition-colors" <?= $is_pengganti ? 'style="background:#eff6ff;"' : '' ?>>
										<td class="py-2 px-2 text-center align-middle">
											<?php if ($is_pengganti): ?>
												<span class="text-gray-300">-</span>
											<?php else: ?>
												<input type="checkbox" name="selected_logs[]" value="<?= htmlspecialchars($PIN . '|' . $date) ?>" class="log-checkbox rounded text-blue-600 focus:ring-blue-500 cursor-pointer" />
											<?php endif; ?>
										</td>
										<td data-label="No" class="py-2 px-2 text-[15px] text-center text-gray-500"><?php echo $no++; ?></td>
										<td data-label="Tanggal" class="py-2 px-2 text-[15px] text-gray-700"><?php echo date('d-m-Y', strtotime($date)); ?></td>
										<td data-label="No Absen" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($PIN); ?></td>
									<td data-label="Nama Karyawan" class="py-2 px-2 text-[15px] font-bold <?= $no_valid_schedule ? 'text-red-600 bg-red-100' : 'text-gray-900' ?>">
										<?php echo htmlspecialchars($nama_karyawan); ?>
										<?php if ($no_valid_schedule): ?>
											<div class="text-xs text-red-600 font-bold mt-1"><?= htmlspecialchars($schedule_warning) ?></div>
										<?php endif; ?>
										<?php if ($pengganti_note !== ''): ?>
											<div class="text-xs font-bold mt-1" style="color:#1d4ed8;"><?= htmlspecialchars($pengganti_note) ?></div>
										<?php endif; ?>
									</td>
										<td data-label="Departemen" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($bagian); ?></td>
										<td data-label="Sub Bagian" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($sub_bagian); ?></td>
										<td data-label="OS/DHK" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($os_dhk); ?></td>
										<td data-label="Golongan" class="py-2 px-2 text-[15px] text-gray-700"><?php echo htmlspecialchars($golongan); ?></td>
									<td data-label="Grup" class="py-2 px-2 text-[15px] text-gray-700"><?php if ($grup_tarik === 'TIDAK TERDAFTAR'): ?><span class="px-2 py-0.5 rounded-full font-bold text-xs" style="background:#fee2e2;color:#b91c1c;">TIDAK TERDAFTAR</span><?php else: ?><?php echo htmlspecialchars($grup_tarik); ?><?php endif; ?></td>
										<td data-label="Jam Masuk" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_masuk); ?></td>
										<td data-label="Jam Pulang" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_keluar); ?></td>
										<td data-label="Istirahat Keluar" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_ist_keluar); ?></td>
										<td data-label="Istirahat Masuk" class="py-2 px-2 text-[15px] text-center text-gray-600"><?php echo htmlspecialchars($s_ist_masuk); ?></td>
										<td data-label="Absen Masuk" class="py-2 px-2 text-[15px] text-center font-semibold text-gray-900 <?= ($is_late_masuk) ? 'bg-red-custom' : '' ?>"><?php echo !empty($masuk) ? htmlspecialchars($masuk) : '-'; ?></td>
										<td data-label="Absen Pulang" class="py-2 px-2 text-[15px] text-center font-semibold text-gray-900 <?= ($is_early_pulang) ? 'bg-red-custom' : '' ?>"><?php echo !empty($pulang) ? htmlspecialchars($pulang) : '-'; ?></td>
										<td data-label="Absen Istirahat Keluar" class="py-2 px-2 text-[15px] text-center text-gray-600 <?= ($is_early_istirahat) ? 'bg-red-custom' : '' ?>"><?php echo !empty($istirahat_keluar) ? htmlspecialchars($istirahat_keluar) : '-'; ?></td>
										<td data-label="Absen Istirahat Masuk" class="py-2 px-2 text-[15px] text-center text-gray-600 <?= ($is_late_istirahat) ? 'bg-red-custom' : '' ?>"><?php echo !empty($istirahat_masuk) ? htmlspecialchars($istirahat_masuk) : '-'; ?></td>
										<td data-label="Upah Pokok" class="py-2 px-2 text-[15px] text-right font-semibold <?= $no_valid_schedule ? 'text-red-600 bg-red-100' : 'text-gray-600' ?>"><?php echo rupiah($upah); ?></td>
										<td data-label="Lembur" class="py-2 px-2 text-[15px] text-right text-gray-600"><?= rupiah($lembur_money) . ' (' . $final_lembur_jam . ' Jam)' ?></td>
										<td data-label="Pot. Telat" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_telat > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_telat); ?></td>
										<td data-label="Pot. Istirahat (Awal)" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_ist_awal > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_ist_awal); ?></td>
										<td data-label="Pot. Istirahat (Telat)" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_ist_telat > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_ist_telat); ?></td>
										<td data-label="Denda Pulang" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_pulang > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_pulang); ?></td>
										<td data-label="Denda Tidak Absen" class="py-2 px-2 text-[15px] text-right text-red-600 font-semibold <?= ($p_tidak_lengkap > 0) ? 'bg-red-custom' : '' ?>"><?php echo rupiah($p_tidak_lengkap); ?></td>
										<td data-label="Pot. Lain" class="py-2 px-2 text-[15px] text-right text-gray-600"><?php echo rupiah(0); ?></td>
										<td data-label="Upah Setelah Potongan" class="py-2 px-2 text-[15px] text-right font-bold <?= $no_valid_schedule ? 'text-red-600 bg-red-100' : 'text-blue-600' ?>"><?php echo rupiah($upah_bersih); ?></td>
										<td data-label="Hasil" class="py-2 px-2 text-[15px] text-center font-bold <?= $no_valid_schedule ? 'text-red-600 bg-red-100' : ($hasil_kerja == 'HADIR' ? 'text-green-600' : 'text-red-600') ?>"><?php echo $hasil_kerja; ?></td>
										<td data-label="Aksi" class="py-2 px-2 text-center align-middle">
											<?php if ($is_pengganti): ?>
												<span class="px-2 py-0.5 rounded-full font-bold text-xs" style="background:#dbeafe;color:#1d4ed8;">PENGGANTI</span>
											<?php else: ?>
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
											<?php endif; ?>
										</td>
									</tr>
								<?php
								endforeach;
								?>
						</tbody>
					</table>
				</div><!-- /table-responsive: tutup di sini agar pagination tidak ikut scroll horizontal -->
					<?php if ($total_data > 0) :
						// Bangun base URL pagination, pertahankan filter yang ada (ip/key/tanggal/per_hal/cari)
						$base_params = $_GET;
						$base_params['page'] = 'generate';
						$base_params['aksi'] = 'tarik';
						unset($base_params['hal']);
						$base_qs = http_build_query($base_params);
						$base_url = '?' . $base_qs . ($base_qs !== '' ? '&' : '') . 'hal=';
						$mulai_no = $total_data > 0 ? ($offset_hal + 1) : 0;
						$sampai_no = min($offset_hal + $per_halaman_eff, $total_data);
						// Window halaman: tampilkan maksimal 5 nomor
						$win_start = max(1, min($hal_aktif - 2, $total_hal - 4));
						$win_end = min($total_hal, $win_start + 4);
						if ($win_end - $win_start < 4) $win_start = max(1, $win_end - 4);
					?>
					<div class="d-flex justify-content-between align-items-center flex-wrap mt-3 px-1" style="gap:10px;">
						<div class="text-muted" style="font-size:13px;">
							Menampilkan <?php echo $mulai_no; ?> s/d <?php echo $sampai_no; ?> dari <?php echo $total_data; ?> data
							(Hal. <?php echo $hal_aktif; ?> / <?php echo $total_hal; ?>)
						</div>
						<nav aria-label="Pagination Tarik Data">
							<ul class="pagination mb-0" style="gap:4px;">
								<?php if ($hal_aktif > 1) : ?>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_url . '1'); ?>" title="Halaman pertama">&laquo;</a>
								</li>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_url . ($hal_aktif - 1)); ?>">Prev</a>
								</li>
								<?php else : ?>
								<li class="page-item disabled"><span class="page-link">&laquo;</span></li>
								<li class="page-item disabled"><span class="page-link">Prev</span></li>
								<?php endif; ?>
								<?php for ($p = $win_start; $p <= $win_end; $p++) : ?>
								<li class="page-item <?php echo ($p == $hal_aktif) ? 'active' : ''; ?>">
									<?php if ($p == $hal_aktif) : ?>
									<span class="page-link" style="background:#2563eb;border-color:#2563eb;color:#fff;"><?php echo $p; ?></span>
									<?php else : ?>
									<a class="page-link" href="<?php echo htmlspecialchars($base_url . $p); ?>"><?php echo $p; ?></a>
									<?php endif; ?>
								</li>
								<?php endfor; ?>
								<?php if ($hal_aktif < $total_hal) : ?>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_url . ($hal_aktif + 1)); ?>">Next</a>
								</li>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_url . $total_hal); ?>" title="Halaman terakhir">&raquo;</a>
								</li>
								<?php else : ?>
								<li class="page-item disabled"><span class="page-link">Next</span></li>
								<li class="page-item disabled"><span class="page-link">&raquo;</span></li>
								<?php endif; ?>
							</ul>
						</nav>
					</div>
					<?php else : ?>
					<div class="alert alert-warning text-center font-bold" style="border-radius:8px;">Tidak ada data yang cocok dengan pencarian/filter.</div>
					<?php endif; ?>
				<div class="px-3"><!-- lanjutan isi (penutup div ini pakai </div> bawaan di bawah) -->
				<?php endif; ?>
				<?php
				// ===== PANEL (dibagi per mode agar cepat) =====
				// semua   : semua panel (perilaku lama)
				// rencana : panel di-skip semua (hanya tabel utama)
				// tdr     : hanya panel Tidak Dalam Rencana (tanpa pull, tanpa tabel utama)
				$noshow = []; $ganti_map = []; $opsi_kar = []; $tdr_rows = []; $rosterShift = []; $noshow_src = 'semua karyawan aktif';
				if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
				    // Shift roster per tgl|pin — acuan panel Tidak Absen & TDR (mode rencana tidak butuh)
				    if ($mode_tarik !== 'rencana') {
				        $rosterShift = [];
				        $qr0 = $koneksi->query("SELECT h.tgl_rkk AS tgl, TRIM(k.no_absen) AS pin, j.jam_masuk AS sm, j.jam_keluar AS sk FROM tb_rkk_detail rd JOIN tb_rkk h ON h.id_rkk=rd.id_rkk JOIN ms_karyawan k ON k.id_karyawan=rd.id_karyawan LEFT JOIN tb_jadwal j ON j.id_jadwal=rd.id_jadwal WHERE h.tgl_rkk BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
				        if ($qr0) while ($rw0 = $qr0->fetch_assoc()) $rosterShift[$rw0['tgl'] . '|' . trim((string)$rw0['pin'])] = [$rw0['sm'], $rw0['sk']];
				    } // end roster (mode !== 'rencana')
				    if ($mode_tarik === 'semua') {
				    $taps = [];
				    $qt = $koneksi->query("SELECT userid, tgl, MIN(detail_waktu) AS first_tap FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' GROUP BY userid, tgl");
				    if ($qt) while ($rt = $qt->fetch_assoc()) $taps[$rt['tgl'] . '|' . trim((string)$rt['userid'])] = $rt['first_tap'] ?? '';
				    $mkMenit = 0; $mkDari = ''; $mkSampai = '';
				    $qmD = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_menit'");
				    if ($qmD && $qmD->num_rows == 0) {
				        // Self-heal: bikin kolom bila belum ada (sama kayak halaman Denda)
				        $koneksi->query("ALTER TABLE tb_denda ADD COLUMN mangkir_menit INT NOT NULL DEFAULT 0");
				        $qmD = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_menit'");
				    }
				    if ($qmD && $qmD->num_rows > 0) {
				        $qmV = $koneksi->query("SELECT mangkir_menit FROM tb_denda LIMIT 1");
				        $rmV = $qmV ? $qmV->fetch_assoc() : [];
				        $mkMenit = max(0, (int)($rmV['mangkir_menit'] ?? 0));
				        $qDt = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_dari_tgl'");
				        if ($qDt && $qDt->num_rows == 0) {
				            $koneksi->query("ALTER TABLE tb_denda ADD COLUMN mangkir_dari_tgl DATE NULL DEFAULT NULL, ADD COLUMN mangkir_sampai_tgl DATE NULL DEFAULT NULL");
				        }
				        $qmV2 = $koneksi->query("SELECT mangkir_dari_tgl, mangkir_sampai_tgl FROM tb_denda LIMIT 1");
				        $rmV2 = $qmV2 ? $qmV2->fetch_assoc() : [];
				        $mkDari = trim((string)($rmV2['mangkir_dari_tgl'] ?? ''));
				        $mkSampai = trim((string)($rmV2['mangkir_sampai_tgl'] ?? ''));
				    }
				    // Sumber: SEMUA karyawan aktif pada tanggal-tanggal yang ada tap-nya (maks 7 hari).
				    // Shift acuan per orang-tanggal: roster (RKK, sudah dihitung di atas) bila ada, else shift master.
				    $noshow_src = 'semua karyawan aktif';
				    $tgl_aktif = [];
				    foreach ($taps as $tk => $tv) { $dd = explode('|', $tk, 2); $tgl_aktif[$dd[0]] = true; }
				    ksort($tgl_aktif);
				    if (count($tgl_aktif) > 0 && count($tgl_aktif) <= 7) {
				        $qa = $koneksi->query("SELECT k.id_karyawan, k.no_absen, k.nama_karyawan, k.upah, o.OS_DHK AS oslbl, d.nama_departmen AS dept, jm.jam_masuk AS m_masuk, jm.jam_keluar AS m_keluar FROM ms_karyawan k LEFT JOIN ms_os_dhk o ON o.id_os_dhk=k.id_os_dhk LEFT JOIN ms_departmen d ON d.id_departmen=k.id_departmen LEFT JOIN tb_jadwal jm ON jm.id_jadwal=k.id_jadwal WHERE k.status_karyawan='Aktif' AND k.no_absen!='' AND k.no_absen IS NOT NULL ORDER BY k.nama_karyawan");
				        if ($qa) while ($ra = $qa->fetch_assoc()) {
				            $pinT = trim((string)$ra['no_absen']);
				            foreach (array_keys($tgl_aktif) as $dt) {
				                $tkey = $dt . '|' . $pinT;
				                $sm = isset($rosterShift[$tkey]) && !empty($rosterShift[$tkey][0]) ? $rosterShift[$tkey][0] : ($ra['m_masuk'] ?? '');
				                $sk = isset($rosterShift[$tkey]) && !empty($rosterShift[$tkey][1]) ? $rosterShift[$tkey][1] : ($ra['m_keluar'] ?? '');
				                if (!isset($taps[$tkey])) {
				                    $exp = '';
				                    if ($sm !== '' && $sk !== '') {
				                        $sec = strtotime($sk) - strtotime($sm);
				                        if ($sec < 0) $sec += 86400;
				                        $h = (int)($sec / 3600); $m = (int)(($sec % 3600) / 60);
				                        $exp = $h . ' jam' . ($m > 0 ? ' ' . $m . ' mnt' : '') . ' (' . substr($sm, 0, 5) . '–' . substr($sk, 0, 5) . ')';
				                    }
				                    $noshow[] = ['tgl' => $dt, 'id_karyawan' => $ra['id_karyawan'], 'no_absen' => $ra['no_absen'], 'nama_karyawan' => $ra['nama_karyawan'], 'oslbl' => $ra['oslbl'], 'dept' => $ra['dept'], 'seharusnya' => $exp, 'ket' => 'Tidak absen — tidak ada tap hari ini'];
				                } elseif ($mkMenit > 0 && !empty($taps[$tkey]) && mangkir_tgl_aktif($mkDari, $mkSampai, $dt)) {
				                    $ft = substr(trim((string)$taps[$tkey]), 11, 5);
				                    if ($sm !== '' && preg_match('/^\d{2}:\d{2}$/', $ft)) {
				                        $dl = strtotime("1970-01-01 $ft") - strtotime("1970-01-01 " . substr($sm, 0, 5));
				                        if ($dl > 43200) $dl -= 86400;
				                        elseif ($dl < -43200) $dl += 86400;
				                        $dlm = (int)($dl / 60);
				                        if ($dlm > $mkMenit) {
				                            $noshow[] = ['tgl' => $dt, 'id_karyawan' => $ra['id_karyawan'], 'no_absen' => $ra['no_absen'], 'nama_karyawan' => $ra['nama_karyawan'], 'oslbl' => $ra['oslbl'], 'dept' => $ra['dept'], 'seharusnya' => 'telat ' . $dlm . ' mnt (batas ' . $mkMenit . ' mnt)', 'ket' => 'Telat ' . $dlm . ' menit — dihitung mangkir'];
				                    }
				                    }
				                }
				            }
				        }
				    }
				}
				$ganti_map = [];
				if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
				    $qg = $koneksi->query("SELECT g.*, k2.nama_karyawan AS nama_ganti FROM tb_ganti g LEFT JOIN ms_karyawan k2 ON k2.id_karyawan=g.id_karyawan_ganti WHERE g.tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
				    if ($qg) while ($rg = $qg->fetch_assoc()) $ganti_map[$rg['tgl'] . '|' . $rg['id_karyawan_absen']] = $rg;
				}
				    } // end panel khusus mode === 'semua'
				    if ($mode_tarik !== 'rencana') {
			$opsi_kar = [];
			$qk = $koneksi->query("SELECT k.id_karyawan, k.no_absen, k.nama_karyawan, o.OS_DHK AS oslbl, d.nama_departmen AS dept FROM ms_karyawan k LEFT JOIN ms_os_dhk o ON o.id_os_dhk=k.id_os_dhk LEFT JOIN ms_departmen d ON d.id_departmen=k.id_departmen WHERE k.status_karyawan='Aktif' ORDER BY k.nama_karyawan");
			if ($qk) while ($rk = $qk->fetch_assoc()) $opsi_kar[] = $rk;
			// ===== PANEL TIDAK DALAM RENCANA (tap ada tapi tidak masuk rencana RKK) =====
			$tdr_rows = [];
			if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
			    $taps_fl = [];
			    $qt2 = $koneksi->query("SELECT TRIM(userid) AS pin, tgl, MIN(detail_waktu) AS ft, MAX(detail_waktu) AS lt, COUNT(*) AS n FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' AND TRIM(userid) <> '' GROUP BY TRIM(userid), tgl");
			    if ($qt2) while ($rt2 = $qt2->fetch_assoc()) $taps_fl[$rt2['tgl'] . '|' . $rt2['pin']] = $rt2;
			    $master_pin = [];
			    foreach ($opsi_kar as $ok) $master_pin[trim((string)$ok['no_absen'])] = $ok;
			    foreach ($taps_fl as $tk => $tv) {
			        if (isset($rosterShift[$tk])) continue; // ada di rencana -> bukan panel ini
			        $dt = $tv['tgl']; $pin = $tv['pin'];
			        $em = $master_pin[$pin] ?? null;
			        if ($em) {
			            $nama = $em['nama_karyawan'];
			            $osdept = ($em['oslbl'] ?: '-') . ' / ' . ($em['dept'] ?: '-');
			        } else {
			            $nm_d = isset($dev_names_map) ? ($dev_names_map[$pin] ?? '') : '';
			            $nama = ($nm_d !== '' ? $nm_d : 'Karyawan Tidak Dikenal') . " ($pin)";
			            $osdept = '- / -';
			        }
			        $tdr_rows[] = ['tgl' => $dt, 'nama' => $nama, 'pin' => $pin, 'osdept' => $osdept,
			            'masuk' => substr($tv['ft'], 11, 5), 'pulang' => substr($tv['lt'], 11, 5), 'n' => (int)$tv['n'],
			            'ket' => 'Tap di luar rencana'];
			    }
			    usort($tdr_rows, function($a, $b) { $c = strcmp($a['tgl'], $b['tgl']); return $c !== 0 ? $c : strcmp($a['nama'], $b['nama']); });
			}
				    } // end panel khusus mode !== 'rencana'
				?>
				<?php if ($mode_tarik === 'semua') : ?>
				<?php if (!empty($ganti_msg)): ?>
					<div class="p-3 mb-3 rounded-lg border text-sm <?= strpos($ganti_msg, 'Gagal') === false ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?>"><?= htmlspecialchars($ganti_msg) ?></div>
				<?php endif; ?>
				<div class="mt-4 p-4 rounded-xl border border-gray-200 bg-white">
					<div class="d-flex justify-content-between align-items-center flex-wrap mb-1" style="gap:10px;">
						<h4 class="font-bold text-sm m-0">Tidak Absen / Tidak Masuk (<?= count($noshow) ?> orang)</h4>
						<?php if (!empty($noshow)): ?>
						<div class="download-btn-container d-flex flex-wrap" style="gap:8px;">
							<a href="excel_tidak_absen.php?tanggal_awal=<?= htmlspecialchars($TanggalAwal) ?>&tanggal_akhir=<?= htmlspecialchars($TanggalAkhir) ?>" target="_blank" class="btn btn-success font-semibold text-white d-inline-flex align-items-center shadow-sm download-btn">
								<i class="fas fa-file-excel mr-2"></i> Download Excel
							</a>
							<a href="pdf_tidak_absen.php?tanggal_awal=<?= htmlspecialchars($TanggalAwal) ?>&tanggal_akhir=<?= htmlspecialchars($TanggalAkhir) ?>" target="_blank" class="btn btn-danger font-semibold text-white d-inline-flex align-items-center shadow-sm download-btn">
								<i class="fas fa-file-pdf mr-2"></i> Download PDF
							</a>
						</div>
						<?php endif; ?>
					</div>
					<p class="text-xs text-gray-500 mb-3">Sumber rencana: <?= htmlspecialchars($noshow_src) ?>. Yang tidak ada tap-nya di mesin pada rentang tanggal ini.</p>
					<?php if (empty($noshow)): ?>
						<p class="text-xs text-gray-500">Tidak ada data — seluruh karyawan yang direncanakan tercatat hadir.</p>
					<?php else:
						// ===== PAGINATION PHP tabel tidak absen (filter + slice, tanpa ubah isi data) =====
						$filtered_ns = $noshow;
						if ($cari_ns !== '') {
							$filtered_ns = [];
							$cari_ns_lower = strtolower($cari_ns);
							foreach ($noshow as $ns_item) {
								$gex_item = $ganti_map[($ns_item['tgl'] . '|' . $ns_item['id_karyawan'])] ?? null;
								$hay_ns = strtolower($ns_item['tgl'] . ' ' . date('d-m-Y', strtotime($ns_item['tgl'])) . ' ' . $ns_item['no_absen'] . ' ' . $ns_item['nama_karyawan'] . ' ' . ($ns_item['oslbl'] ?? '') . ' ' . ($ns_item['dept'] ?? '') . ' ' . ($ns_item['seharusnya'] ?? '') . ' ' . ($ns_item['ket'] ?? '') . ' ' . ($gex_item['nama_ganti'] ?? ''));
								if (strpos($hay_ns, $cari_ns_lower) !== false) $filtered_ns[] = $ns_item;
							}
						}
						$total_ns = count($filtered_ns);
						if ($show_all_ns || $total_ns === 0) {
							$total_hal_ns = 1; $hal_ns = 1; $offset_ns = 0;
							$paged_noshow = $filtered_ns;
							$per_hal_ns_eff = $total_ns > 0 ? $total_ns : 25;
						} else {
							$per_hal_ns_eff = $per_hal_ns;
							$total_hal_ns = max(1, (int)ceil($total_ns / $per_hal_ns_eff));
							$hal_ns = isset($_GET['hal_ns']) ? max(1, (int)$_GET['hal_ns']) : 1;
							if ($hal_ns > $total_hal_ns) $hal_ns = $total_hal_ns;
							$offset_ns = ($hal_ns - 1) * $per_hal_ns_eff;
							$paged_noshow = array_slice($filtered_ns, $offset_ns, $per_hal_ns_eff);
						}
					?>
					<!-- Bar atas pagination tidak absen: Tampilkan (per halaman) + Cari -->
					<div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
						<div class="d-flex align-items-center" style="gap:8px;font-size:13px;color:#334155;">
							<label class="m-0" for="per-hal-ns-select">Tampilkan</label>
							<select id="per-hal-ns-select" class="form-control" style="width:auto;display:inline-block;padding:4px 8px;border:1px solid #e0e6ed;border-radius:8px;font-size:13px;">
								<option value="10" <?php echo (!$show_all_ns && $per_hal_ns == 10) ? 'selected' : ''; ?>>10</option>
								<option value="25" <?php echo (!$show_all_ns && $per_hal_ns == 25) ? 'selected' : ''; ?>>25</option>
								<option value="50" <?php echo (!$show_all_ns && $per_hal_ns == 50) ? 'selected' : ''; ?>>50</option>
								<option value="100" <?php echo (!$show_all_ns && $per_hal_ns == 100) ? 'selected' : ''; ?>>100</option>
								<option value="-1" <?php echo $show_all_ns ? 'selected' : ''; ?>>Semua</option>
							</select>
							<span>data</span>
						</div>
						<div class="d-flex align-items-center" style="gap:8px;font-size:13px;color:#334155;">
							<label class="m-0" for="cari-ns-input">Cari:</label>
							<input type="text" id="cari-ns-input" value="<?php echo htmlspecialchars($cari_ns); ?>" placeholder="Cari nama / no absen..." class="form-control" style="width:180px;padding:5px 10px;border:1px solid #e0e6ed;border-radius:8px;font-size:13px;" />
							<button type="button" id="cari-ns-btn" class="btn btn-primary btn-sm shadow-sm">Cari</button>
							<?php if ($cari_ns !== '') :
								$reset_ns = $_GET;
								$reset_ns['page'] = 'generate';
								$reset_ns['aksi'] = 'tarik';
								unset($reset_ns['cari_ns'], $reset_ns['hal_ns']);
							?>
							<a href="?<?php echo htmlspecialchars(http_build_query($reset_ns)); ?>" class="btn btn-default btn-sm" style="border:1px solid #e0e6ed;">Reset</a>
							<?php endif; ?>
						</div>
					</div>
					<div class="overflow-x-auto">
					<table id="tabel-tidak-absen" class="w-full text-sm text-left bg-white rounded-lg overflow-hidden">
						<thead class="bg-gray-50 border-b border-gray-200"><tr><th class="py-2 px-3">Tanggal</th><th class="py-2 px-3">Nama</th><th class="py-2 px-3">OS / Bagian</th><th class="py-2 px-3">Seharusnya</th><th class="py-2 px-3">Keterangan</th><th class="py-2 px-3">Digantikan oleh</th></tr></thead>
						<tbody class="divide-y divide-gray-100">
						<?php foreach ($paged_noshow as $ns):
						    $gkey = $ns['tgl'] . '|' . $ns['id_karyawan'];
						    $gex = $ganti_map[$gkey] ?? null;
						?>
							<tr class="hover:bg-gray-50">
								<td class="py-2 px-3"><?= date('d-m-Y', strtotime($ns['tgl'])) ?></td>
								<td class="py-2 px-3 font-semibold"><?= htmlspecialchars($ns['nama_karyawan']) ?> <span class="text-gray-400 text-xs">(<?= htmlspecialchars($ns['no_absen']) ?>)</span></td>
								<td class="py-2 px-3 text-xs"><?= htmlspecialchars(($ns['oslbl'] ?: '-') . ' / ' . ($ns['dept'] ?: '-')) ?></td>
								<td class="py-2 px-3 text-xs font-semibold"><?= htmlspecialchars($ns['seharusnya'] ?: '-') ?></td>
								<td class="py-2 px-3 text-xs text-amber-700 font-semibold"><?= htmlspecialchars($ns['ket'] ?? 'Tidak absen — tidak ada tap hari ini') ?></td>
								<td class="py-2 px-3 text-xs">
									<?php if ($gex): ?>
										<div class="flex flex-col gap-1 min-w-[160px]">
											<span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-bold" style="width:fit-content;"><?= htmlspecialchars($gex['nama_ganti'] ?: '?') ?></span>
											<form method="POST" onsubmit="return confirm('Batalkan pengganti ini? Baris PENGGANTI di tabel masuk ikut hilang.')">
												<input type="hidden" name="ganti_batal" value="1">
												<input type="hidden" name="ganti_tgl" value="<?= htmlspecialchars($ns['tgl']) ?>">
												<input type="hidden" name="ganti_absen" value="<?= (int)$ns['id_karyawan'] ?>">
												<button type="submit" class="px-2 py-1 bg-red-500 text-white rounded text-xs font-bold">Batal</button>
											</form>
										</div>
									<?php else: ?>
									<form method="POST" class="flex flex-col gap-1 min-w-[220px]">
										<input type="hidden" name="ganti_simpan" value="1">
										<input type="hidden" name="ganti_tgl" value="<?= htmlspecialchars($ns['tgl']) ?>">
										<input type="hidden" name="ganti_absen" value="<?= (int)$ns['id_karyawan'] ?>">
										<select name="ganti_oleh" required class="ganti-select border border-gray-300 rounded px-2 py-1 text-xs w-full" data-kid="<?= (int)$ns['id_karyawan'] ?>">
											<option value="">- Pilih pengganti -</option>
										</select>
										<div class="flex gap-2 items-center">
										<button type="submit" class="px-2 py-1 bg-indigo-600 text-white rounded text-xs font-bold">Simpan</button>
										<a href="?page=karyawan&aksi=tambah" class="text-indigo-600 underline">+ Tambah karyawan baru di master</a>
										</div>
									</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($paged_noshow)): ?>
							<tr><td colspan="6" class="py-3 px-3 text-center text-gray-500">Tidak ada data yang cocok dengan pencarian.</td></tr>
						<?php endif; ?>
						</tbody>
					</table>
					</div>
					<?php if ($total_ns > 0) :
						$base_ns = $_GET;
						$base_ns['page'] = 'generate';
						$base_ns['aksi'] = 'tarik';
						unset($base_ns['hal_ns']);
						$base_ns_qs = http_build_query($base_ns);
						$base_ns_url = '?' . $base_ns_qs . ($base_ns_qs !== '' ? '&' : '') . 'hal_ns=';
						$mulai_ns = $offset_ns + 1;
						$sampai_ns = min($offset_ns + $per_hal_ns_eff, $total_ns);
						$win_start_ns = max(1, min($hal_ns - 2, $total_hal_ns - 4));
						$win_end_ns = min($total_hal_ns, $win_start_ns + 4);
						if ($win_end_ns - $win_start_ns < 4) $win_start_ns = max(1, $win_end_ns - 4);
					?>
					<div class="d-flex justify-content-between align-items-center flex-wrap mt-3" style="gap:10px;">
						<div class="text-muted" style="font-size:12px;">
							Menampilkan <?php echo $mulai_ns; ?> s/d <?php echo $sampai_ns; ?> dari <?php echo $total_ns; ?> data
							(Hal. <?php echo $hal_ns; ?> / <?php echo $total_hal_ns; ?>)
						</div>
						<nav aria-label="Pagination Tidak Absen">
							<ul class="pagination pagination-sm mb-0" style="gap:4px;">
								<?php if ($hal_ns > 1) : ?>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_ns_url . '1'); ?>" title="Halaman pertama">&laquo;</a>
								</li>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_ns_url . ($hal_ns - 1)); ?>">Prev</a>
								</li>
								<?php else : ?>
								<li class="page-item disabled"><span class="page-link">&laquo;</span></li>
								<li class="page-item disabled"><span class="page-link">Prev</span></li>
								<?php endif; ?>
								<?php for ($pn = $win_start_ns; $pn <= $win_end_ns; $pn++) : ?>
								<li class="page-item <?php echo ($pn == $hal_ns) ? 'active' : ''; ?>">
									<?php if ($pn == $hal_ns) : ?>
									<span class="page-link" style="background:#2563eb;border-color:#2563eb;color:#fff;"><?php echo $pn; ?></span>
									<?php else : ?>
									<a class="page-link" href="<?php echo htmlspecialchars($base_ns_url . $pn); ?>"><?php echo $pn; ?></a>
									<?php endif; ?>
								</li>
								<?php endfor; ?>
								<?php if ($hal_ns < $total_hal_ns) : ?>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_ns_url . ($hal_ns + 1)); ?>">Next</a>
								</li>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_ns_url . $total_hal_ns); ?>" title="Halaman terakhir">&raquo;</a>
								</li>
								<?php else : ?>
								<li class="page-item disabled"><span class="page-link">Next</span></li>
								<li class="page-item disabled"><span class="page-link">&raquo;</span></li>
								<?php endif; ?>
							</ul>
						</nav>
					</div>
					<?php endif; ?>
					<script>
					(function(){
						function initGanti(){
							try {
								if (!(window.jQuery && jQuery.fn)) return;
								var master = document.getElementById('ganti_master_opts');
								var html = master ? master.innerHTML : '';
								document.querySelectorAll('select.ganti-select').forEach(function(sel){
									if (sel.dataset.filled) return;
									sel.innerHTML = '<option value="">- Pilih pengganti -</option>' + html;
									var skip = sel.getAttribute('data-kid');
									if (skip) {
										var o = sel.querySelector('option[value="' + skip + '"]');
										if (o) o.remove();
									}
									sel.dataset.filled = '1';
								});
								if (jQuery.fn.select2) {
									jQuery('.ganti-select').each(function(){ if(!jQuery(this).data('select2')) jQuery(this).select2({width:'100%',placeholder:'- Pilih pengganti -',allowClear:false}); });
								}
							} catch(e){}
						}
						// Tabel tidak absen pakai pagination PHP (?hal_ns=&per_hal_ns=&cari_ns=),
						// DataTables tidak dipakai agar tidak render semua + tidak dobel pagination.
						if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ initGanti(); });
						else { initGanti(); }
					})();
					</script>
					<select id="ganti_master_opts" style="display:none">
						<?php foreach ($opsi_kar as $ok2): ?>
						<option value="<?= (int)$ok2['id_karyawan'] ?>"><?= htmlspecialchars($ok2['nama_karyawan']) ?> (<?= htmlspecialchars($ok2['no_absen']) ?>) — <?= htmlspecialchars(($ok2['oslbl'] ?: '-') . ' / ' . ($ok2['dept'] ?: '-')) ?></option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>
				</div>
				</div>
				<?php endif; // end panel NS + ganti (mode === 'semua') ?>
				<?php if ($mode_tarik !== 'tdr') : ?>
				</form>
				<?php endif; ?>
				<?php if ($mode_tarik !== 'rencana') : ?>
				<div class="mt-4 p-4 rounded-xl border border-gray-200 bg-white">
					<div class="d-flex justify-content-between align-items-center flex-wrap mb-1" style="gap:10px;">
						<h4 class="font-bold text-sm m-0">Tidak Dalam Rencana (<?= count($tdr_rows) ?> orang)</h4>
						<?php if (!empty($tdr_rows)): ?>
						<div class="download-btn-container d-flex flex-wrap" style="gap:8px;">
							<a href="excel_tidak_rencana.php?tanggal_awal=<?= htmlspecialchars($TanggalAwal) ?>&tanggal_akhir=<?= htmlspecialchars($TanggalAkhir) ?>" target="_blank" class="btn btn-success font-semibold text-white d-inline-flex align-items-center shadow-sm download-btn">
								<i class="fas fa-file-excel mr-2"></i> Download Excel
							</a>
							<a href="pdf_tidak_rencana.php?tanggal_awal=<?= htmlspecialchars($TanggalAwal) ?>&tanggal_akhir=<?= htmlspecialchars($TanggalAkhir) ?>" target="_blank" class="btn btn-danger font-semibold text-white d-inline-flex align-items-center shadow-sm download-btn">
								<i class="fas fa-file-pdf mr-2"></i> Download PDF
							</a>
						</div>
						<?php endif; ?>
					</div>
					<p class="text-xs text-gray-500 mb-3">Tap tercatat di mesin tapi tidak masuk rencana RKK pada rentang tanggal ini.</p>
					<?php if (empty($tdr_rows)): ?>
						<p class="text-xs text-gray-500">Tidak ada data — seluruh tap masuk rencana.</p>
					<?php else:
						$filtered_tdr = $tdr_rows;
						if ($cari_tdr !== '') {
							$filtered_tdr = [];
							$cari_tdr_lower = strtolower($cari_tdr);
							foreach ($tdr_rows as $tr_item) {
								$hay_tdr = strtolower($tr_item['tgl'] . ' ' . date('d-m-Y', strtotime($tr_item['tgl'])) . ' ' . $tr_item['pin'] . ' ' . $tr_item['nama'] . ' ' . ($tr_item['osdept'] ?? '') . ' ' . ($tr_item['ket'] ?? ''));
								if (strpos($hay_tdr, $cari_tdr_lower) !== false) $filtered_tdr[] = $tr_item;
							}
						}
						$total_tdr = count($filtered_tdr);
						if ($show_all_tdr || $total_tdr === 0) {
							$total_hal_tdr = 1; $hal_tdr = 1; $offset_tdr = 0;
							$paged_tdr = $filtered_tdr;
							$per_hal_tdr_eff = $total_tdr > 0 ? $total_tdr : 25;
						} else {
							$per_hal_tdr_eff = $per_hal_tdr;
							$total_hal_tdr = max(1, (int)ceil($total_tdr / $per_hal_tdr_eff));
							$hal_tdr = isset($_GET['hal_tdr']) ? max(1, (int)$_GET['hal_tdr']) : 1;
							if ($hal_tdr > $total_hal_tdr) $hal_tdr = $total_hal_tdr;
							$offset_tdr = ($hal_tdr - 1) * $per_hal_tdr_eff;
							$paged_tdr = array_slice($filtered_tdr, $offset_tdr, $per_hal_tdr_eff);
						}
					?>
					<div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
						<div class="d-flex align-items-center" style="gap:8px;font-size:13px;color:#334155;">
							<label class="m-0" for="per-hal-tdr-select">Tampilkan</label>
							<select id="per-hal-tdr-select" class="form-control" style="width:auto;display:inline-block;padding:4px 8px;border:1px solid #e0e6ed;border-radius:8px;font-size:13px;">
								<option value="10" <?php echo (!$show_all_tdr && $per_hal_tdr == 10) ? 'selected' : ''; ?>>10</option>
								<option value="25" <?php echo (!$show_all_tdr && $per_hal_tdr == 25) ? 'selected' : ''; ?>>25</option>
								<option value="50" <?php echo (!$show_all_tdr && $per_hal_tdr == 50) ? 'selected' : ''; ?>>50</option>
								<option value="100" <?php echo (!$show_all_tdr && $per_hal_tdr == 100) ? 'selected' : ''; ?>>100</option>
								<option value="-1" <?php echo $show_all_tdr ? 'selected' : ''; ?>>Semua</option>
							</select>
							<span>data</span>
						</div>
						<div class="d-flex align-items-center" style="gap:8px;font-size:13px;color:#334155;">
							<label class="m-0" for="cari-tdr-input">Cari:</label>
							<input type="text" id="cari-tdr-input" value="<?php echo htmlspecialchars($cari_tdr); ?>" placeholder="Cari nama / no absen..." class="form-control" style="width:180px;padding:5px 10px;border:1px solid #e0e6ed;border-radius:8px;font-size:13px;" />
							<button type="button" id="cari-tdr-btn" class="btn btn-primary btn-sm shadow-sm">Cari</button>
							<?php if ($cari_tdr !== '') :
								$reset_tdr = $_GET;
								$reset_tdr['page'] = 'generate';
								$reset_tdr['aksi'] = 'tarik';
								unset($reset_tdr['cari_tdr'], $reset_tdr['hal_tdr']);
							?>
							<a href="?<?php echo htmlspecialchars(http_build_query($reset_tdr)); ?>" class="btn btn-default btn-sm" style="border:1px solid #e0e6ed;">Reset</a>
							<?php endif; ?>
						</div>
					</div>
					<div class="overflow-x-auto">
					<table id="tabel-tidak-rencana" class="w-full text-sm text-left bg-white rounded-lg overflow-hidden">
						<thead class="bg-gray-50 border-b border-gray-200"><tr><th class="py-2 px-3">Tanggal</th><th class="py-2 px-3">Nama</th><th class="py-2 px-3">OS / Bagian</th><th class="py-2 px-3">Tap Masuk</th><th class="py-2 px-3">Tap Pulang</th><th class="py-2 px-3">Jml Tap</th><th class="py-2 px-3">Keterangan</th></tr></thead>
						<tbody class="divide-y divide-gray-100">
						<?php foreach ($paged_tdr as $tr): ?>
							<tr class="hover:bg-gray-50">
								<td class="py-2 px-3"><?= date('d-m-Y', strtotime($tr['tgl'])) ?></td>
								<td class="py-2 px-3 font-semibold"><?= htmlspecialchars($tr['nama']) ?> <span class="text-gray-400 text-xs">(<?= htmlspecialchars($tr['pin']) ?>)</span></td>
								<td class="py-2 px-3 text-xs"><?= htmlspecialchars($tr['osdept']) ?></td>
								<td class="py-2 px-3 text-xs font-semibold"><?= htmlspecialchars($tr['masuk'] ?: '-') ?></td>
								<td class="py-2 px-3 text-xs font-semibold"><?= htmlspecialchars(($tr['pulang'] !== '' && $tr['pulang'] !== $tr['masuk']) ? $tr['pulang'] : '-') ?></td>
								<td class="py-2 px-3 text-xs text-center"><?= (int)$tr['n'] ?></td>
								<td class="py-2 px-3 text-xs text-amber-700 font-semibold"><?= htmlspecialchars($tr['ket']) ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($paged_tdr)): ?>
							<tr><td colspan="7" class="py-3 px-3 text-center text-gray-500">Tidak ada data yang cocok dengan pencarian.</td></tr>
						<?php endif; ?>
						</tbody>
					</table>
					</div>
					<?php if ($total_tdr > 0) :
						$base_tdr = $_GET;
						$base_tdr['page'] = 'generate';
						$base_tdr['aksi'] = 'tarik';
						unset($base_tdr['hal_tdr']);
						$base_tdr_qs = http_build_query($base_tdr);
						$base_tdr_url = '?' . $base_tdr_qs . ($base_tdr_qs !== '' ? '&' : '') . 'hal_tdr=';
						$mulai_tdr = $offset_tdr + 1;
						$sampai_tdr = min($offset_tdr + $per_hal_tdr_eff, $total_tdr);
						$win_start_tdr = max(1, min($hal_tdr - 2, $total_hal_tdr - 4));
						$win_end_tdr = min($total_hal_tdr, $win_start_tdr + 4);
						if ($win_end_tdr - $win_start_tdr < 4) $win_start_tdr = max(1, $win_end_tdr - 4);
					?>
					<div class="d-flex justify-content-between align-items-center flex-wrap mt-3" style="gap:10px;">
						<div class="text-muted" style="font-size:12px;">
							Menampilkan <?php echo $mulai_tdr; ?> s/d <?php echo $sampai_tdr; ?> dari <?php echo $total_tdr; ?> data
							(Hal. <?php echo $hal_tdr; ?> / <?php echo $total_hal_tdr; ?>)
						</div>
						<nav aria-label="Pagination Tidak Dalam Rencana">
							<ul class="pagination pagination-sm mb-0" style="gap:4px;">
								<?php if ($hal_tdr > 1) : ?>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_tdr_url . '1'); ?>" title="Halaman pertama">&laquo;</a>
								</li>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_tdr_url . ($hal_tdr - 1)); ?>">Prev</a>
								</li>
								<?php else : ?>
								<li class="page-item disabled"><span class="page-link">&laquo;</span></li>
								<li class="page-item disabled"><span class="page-link">Prev</span></li>
								<?php endif; ?>
								<?php for ($pn = $win_start_tdr; $pn <= $win_end_tdr; $pn++) : ?>
								<li class="page-item <?php echo ($pn == $hal_tdr) ? 'active' : ''; ?>">
									<?php if ($pn == $hal_tdr) : ?>
									<span class="page-link" style="background:#2563eb;border-color:#2563eb;color:#fff;"><?php echo $pn; ?></span>
									<?php else : ?>
									<a class="page-link" href="<?php echo htmlspecialchars($base_tdr_url . $pn); ?>"><?php echo $pn; ?></a>
									<?php endif; ?>
								</li>
								<?php endfor; ?>
								<?php if ($hal_tdr < $total_hal_tdr) : ?>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_tdr_url . ($hal_tdr + 1)); ?>">Next</a>
								</li>
								<li class="page-item">
									<a class="page-link" href="<?php echo htmlspecialchars($base_tdr_url . $total_hal_tdr); ?>" title="Halaman terakhir">&raquo;</a>
								</li>
								<?php else : ?>
								<li class="page-item disabled"><span class="page-link">Next</span></li>
								<li class="page-item disabled"><span class="page-link">&raquo;</span></li>
								<?php endif; ?>
							</ul>
						</nav>
					</div>
					<?php endif; ?>
					<?php endif; ?>
				</div>
				<?php endif; // end panel TDR (mode !== 'rencana') ?>
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

			// Tabel utama pakai pagination PHP (?hal=&per_hal=&cari=) — DataTables tidak dipakai di sini
			// agar bar "Tampilkan/Cari" dan pagination selalu tampil.

			// Bar atas: ganti jumlah data per halaman (reset ke hal 1, pertahankan filter)
			$('#per-hal-select').on('change', function() {
				var url = new URL(window.location.href);
				url.searchParams.set('per_hal', $(this).val());
				url.searchParams.set('hal', '1');
				window.location.href = url.toString();
			});

			// Bar atas: Cari server-side (lintas semua data, reset ke hal 1)
			function submitCariTarik() {
				var url = new URL(window.location.href);
				var kw = $('#cari-tarik-input').val().trim();
				if (kw !== '') url.searchParams.set('cari', kw);
				else url.searchParams.delete('cari');
				url.searchParams.set('hal', '1');
				window.location.href = url.toString();
			}
			$('#cari-tarik-btn').on('click', submitCariTarik);
			$('#cari-tarik-input').on('keydown', function(e) {
				if (e.key === 'Enter') { e.preventDefault(); submitCariTarik(); }
			});

			// Bar atas tabel TIDAK ABSEN: ganti jumlah data per halaman (reset ke hal_ns 1)
			$('#per-hal-ns-select').on('change', function() {
				var url = new URL(window.location.href);
				url.searchParams.set('per_hal_ns', $(this).val());
				url.searchParams.set('hal_ns', '1');
				window.location.href = url.toString();
			});

			// Bar atas tabel TIDAK ABSEN: Cari server-side (reset ke hal_ns 1)
			function submitCariNs() {
				var url = new URL(window.location.href);
				var kw = $('#cari-ns-input').val().trim();
				if (kw !== '') url.searchParams.set('cari_ns', kw);
				else url.searchParams.delete('cari_ns');
				url.searchParams.set('hal_ns', '1');
				window.location.href = url.toString();
			}
			$('#cari-ns-btn').on('click', submitCariNs);
			$('#cari-ns-input').on('keydown', function(e) {
				if (e.key === 'Enter') { e.preventDefault(); submitCariNs(); }
			});

			// Bar atas tabel TIDAK DALAM RENCANA: ganti jumlah data per halaman (reset ke hal_tdr 1)
			$('#per-hal-tdr-select').on('change', function() {
				var url = new URL(window.location.href);
				url.searchParams.set('per_hal_tdr', $(this).val());
				url.searchParams.set('hal_tdr', '1');
				window.location.href = url.toString();
			});

			// Bar atas tabel TIDAK DALAM RENCANA: Cari server-side (reset ke hal_tdr 1)
			function submitCariTdr() {
				var url = new URL(window.location.href);
				var kw = $('#cari-tdr-input').val().trim();
				if (kw !== '') url.searchParams.set('cari_tdr', kw);
				else url.searchParams.delete('cari_tdr');
				url.searchParams.set('hal_tdr', '1');
				window.location.href = url.toString();
			}
			$('#cari-tdr-btn').on('click', submitCariTdr);
			$('#cari-tdr-input').on('keydown', function(e) {
				if (e.key === 'Enter') { e.preventDefault(); submitCariTdr(); }
			});

			// Select All checkbox logic (plain, tanpa DataTables)
			$('#select-all').on('click', function() {
				$('.log-checkbox').prop('checked', this.checked);
			});

			// Checkbox change handler to sync select-all state
			$('#tabel-tarik-data').on('change', '.log-checkbox', function() {
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
				
				var checkedData = [];
				$('.log-checkbox:checked').each(function() {
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
			$('#tabel-tarik-data').on('click', '.btn-hapus-single', function(e) {
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
			$('#tabel-tarik-data').on('click', '.btn-edit-lembur', function(e) {
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