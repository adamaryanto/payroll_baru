<?php
include "koneksi.php";
include "page/generate/parse.php";

$tampil = $koneksi->query("SELECT * from tb_mesin");
$data = $tampil->fetch_assoc();
$ipmesin = $data['ip_mesin'] ?? '';
$port = $data['port'] ?? '';

$IP = $_GET['ip'] ?? $ipmesin;
$Key = $_GET['key'] ?? "0";
$TanggalAwal = $_GET['tanggal_awal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
$TanggalAkhir = $_GET['tanggal_akhir'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));
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

$should_connect = true;
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
}

// Query and group data from tb_record for Date Range
$db_records = $koneksi->query("SELECT * FROM tb_record WHERE tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir' ORDER BY tgl ASC, id_record ASC");
$grouped = [];
// Roster per-tanggal: tgl|pin => shift (acuan utama; master cuma fallback)
$rosterMap = [];
if (!empty($TanggalAwal) && !empty($TanggalAkhir)) {
	$qr_roster = $koneksi->query("SELECT h.tgl_rkk AS tgl, TRIM(k.no_absen) AS pin, j.* FROM tb_rkk_detail rd JOIN tb_rkk h ON h.id_rkk=rd.id_rkk JOIN ms_karyawan k ON k.id_karyawan=rd.id_karyawan LEFT JOIN tb_jadwal j ON j.id_jadwal=rd.id_jadwal WHERE h.tgl_rkk BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
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

// Inject baris PENGGANTI (tb_ganti) — sama seperti tabel tarik-data.php
$qganti = $koneksi->query("SELECT g.tgl, g.id_karyawan_absen, g.id_karyawan_ganti, g.upah_bayar, TRIM(k.no_absen) AS pin_ganti, k2.nama_karyawan AS nama_absen FROM tb_ganti g LEFT JOIN ms_karyawan k ON k.id_karyawan=g.id_karyawan_ganti LEFT JOIN ms_karyawan k2 ON k2.id_karyawan=g.id_karyawan_absen WHERE g.tgl BETWEEN '$TanggalAwal' AND '$TanggalAkhir'");
if ($qganti) while ($rg = $qganti->fetch_assoc()) {
	$pin_g = trim((string)($rg['pin_ganti'] ?? ''));
	if ($pin_g === '') continue;
	$tgl_g = $rg['tgl'];
	$real_k = $pin_g . '_' . $tgl_g;
	if (isset($grouped[$real_k])) {
		$grouped[$real_k]['ganti_absen_nama'] = $rg['nama_absen'] ?? '';
		continue;
	}
	$gk = 'GANTI_' . $tgl_g . '_' . (int)$rg['id_karyawan_ganti'] . '_' . (int)$rg['id_karyawan_absen'];
	if (isset($grouped[$gk])) continue;
	$grouped[$gk] = ['pin' => $pin_g, 'date' => $tgl_g, 'masuk' => '', 'istirahat_keluar' => '', 'istirahat_masuk' => '', 'pulang' => '', 'raw_times' => [], 'is_pengganti' => true, 'ganti_absen_nama' => $rg['nama_absen'] ?? '', 'ganti_upah' => (float)$rg['upah_bayar']];
}
uasort($grouped, function($ag, $bg) { $cg = strcmp($ag['date'], $bg['date']); return $cg !== 0 ? $cg : strcmp($ag['pin'], $bg['pin']); });
?>
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Log Absensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; font-size: 8px; color: #333; margin: 0; padding: 0; }
        #content-to-pdf { padding: 10px; }
        .header { text-align: center; margin-bottom: 15px; }
        .header h2 { margin: 0; font-size: 13px; font-weight: 700; color: #1e3a8a; }
        .header p { margin: 3px 0 0 0; color: #666; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; color: #374151; font-weight: 700; border: 1px solid #d1d5db; padding: 4px 2px; text-transform: uppercase; font-size: 6px; }
        td { border: 1px solid #e5e7eb; padding: 4px 2px; vertical-align: middle; font-size: 6.5px; }
        tr:nth-child(even) { background-color: #f9fafb; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: 600; }
        .badge { display: inline-block; padding: 1px 2px; font-size: 6px; font-weight: 700; border-radius: 2px; margin: 1px; }
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
        window.onload = function() {
            try {
                var jsPDF = window.jspdf.jsPDF;
                var doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'landscape' });
                doc.setFontSize(13);
                doc.text('LAPORAN LOG ABSENSI MASUK / PULANG', 148.5, 12, { align: 'center' });
                doc.setFontSize(9);
                doc.text('Tanggal: <?= date("d-m-Y", strtotime($TanggalAwal)) ?> s/d <?= date("d-m-Y", strtotime($TanggalAkhir)) ?> | Unduh: <?= date("d-m-Y H:i") ?>', 148.5, 18, { align: 'center' });
                doc.autoTable({
                    html: '#lapTable',
                    startY: 22,
                    theme: 'grid',
                    styles: { fontSize: 6, cellPadding: 1.2, overflow: 'linebreak', valign: 'middle' },
                    headStyles: { fillColor: [30, 58, 138], textColor: 255, fontStyle: 'bold', fontSize: 6 },
                    footStyles: { fillColor: [241, 245, 249], textColor: 0, fontStyle: 'bold' },
                    columnStyles: {
                        0:{halign:'center'},1:{halign:'center'},2:{halign:'center'},
                        6:{halign:'center'},7:{halign:'center'},8:{halign:'center'},9:{halign:'center'},
                        10:{halign:'center'},11:{halign:'center'},12:{halign:'center'},13:{halign:'center'},
                        14:{halign:'center'},15:{halign:'center'},
                        16:{halign:'right'},17:{halign:'right'},18:{halign:'right'},19:{halign:'right'},
                        20:{halign:'right'},21:{halign:'right'},22:{halign:'right'},23:{halign:'right'},
                        24:{halign:'right'},25:{halign:'center'}
                    }
                });
                doc.save('Laporan_Log_Absensi_' + '<?= htmlspecialchars($TanggalAwal) ?>_to_<?= htmlspecialchars($TanggalAkhir) ?>' + '.pdf');
                document.getElementById('loader').style.display = 'none';
                document.body.innerHTML = '<div style="text-align: center; margin-top: 150px; font-family: \'Inter\', sans-serif;"><h2 style="color: #047857; font-size: 24px;">✓ Download Selesai</h2><p style="color: #666; font-size: 14px; margin-top: 10px;">Laporan PDF berhasil diunduh. Halaman ini akan tertutup otomatis.</p></div>';
                setTimeout(function() {
                    window.close();
                }, 1500);
            } catch(err) {
                console.error(err);
                document.getElementById('loader').style.display = 'none';
                alert('Gagal membuat PDF. Silakan coba lagi.');
            }
        };
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
            <p>Tanggal Absensi: <?php echo date('d-m-Y', strtotime($TanggalAwal)) . ' s/d ' . date('d-m-Y', strtotime($TanggalAkhir)); ?> | Unduh: <?php echo date('d-m-Y H:i'); ?></p>
        </div>

        <table id="lapTable">
            <thead>
                <tr>
                    <th width="2%" class="text-center">No</th>
                    <th width="6%" class="text-center">Tanggal</th>
                    <th width="5%" class="text-center">No. Absen</th>
                    <th width="10%">Nama Karyawan</th>
                    <th width="8%">Departemen</th>
                    <th width="8%">Sub Bagian</th>
                    <th width="5%" class="text-center">OS/DHK</th>
                    <th width="5%" class="text-center">Golongan</th>
                    <th width="5%" class="text-center">Jam Masuk</th>
                    <th width="5%" class="text-center">Jam Pulang</th>
                    <th width="5%" class="text-center">Ist. Keluar</th>
                    <th width="5%" class="text-center">Ist. Masuk</th>
                    <th width="5%" class="text-center">Absen Masuk</th>
                    <th width="5%" class="text-center">Absen Pulang</th>
                    <th width="5%" class="text-center">Absen Ist. K</th>
                    <th width="5%" class="text-center">Absen Ist. M</th>
                    <th width="7%" class="text-right">Upah Pokok</th>
                    <th width="5%" class="text-right">Lembur</th>
                    <th width="5%" class="text-right">Pot. Telat</th>
                    <th width="5%" class="text-right">Pot. Ist (Awal)</th>
                    <th width="5%" class="text-right">Pot. Ist (Telat)</th>
                    <th width="5%" class="text-right">Denda Pulang</th>
                    <th width="5%" class="text-right">Denda Tdk Absen</th>
                    <th width="5%" class="text-right">Pot. Lain</th>
                    <th width="7%" class="text-right">Upah Setelah Pot</th>
                    <th width="5%" class="text-center">Hasil</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $t_upah = 0; $t_lembur = 0; $t_pot = 0; $t_bersih = 0;
                foreach ($grouped as $key => $row) :
                    $PIN = $row['pin'];
                    $date = $row['date'];
                    $masuk = $row['masuk'];
                    $istirahat_keluar = $row['istirahat_keluar'];
                    $istirahat_masuk = $row['istirahat_masuk'];
                    $pulang = $row['pulang'];

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

                    // Acuan shift: roster tanggal ini bila ada, else master (fallback).
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
                    	if (strpos(strtoupper($assigned_shift['keterangan'] ?? ''), 'FLEXI') !== false) {
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
                        $is_flexible = (strpos(strtoupper($shift['keterangan'] ?? ''), 'FLEXI') !== false);
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
                    $p_telat = 0; $p_pulang = 0; $p_ist_awal = 0; $p_ist_telat = 0; $p_tidak_lengkap = 0;
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

                            if ($has_masuk && is_time_late($masuk, $s_masuk_time)) $p_telat = $globalDendaMasuk;
                            if ($has_keluar && is_time_early($pulang, $s_keluar_time)) $p_pulang = $globalDendaPulang;
                            if ($has_ist_keluar && is_time_early($istirahat_keluar, $s_ist_keluar_time)) $p_ist_awal = $globalDendaIstirahatKeluar;
                            if ($has_ist_masuk && is_time_late($istirahat_masuk, $s_ist_masuk_time)) $p_ist_telat = $globalDendaIstirahatMasuk;

                            $hasIncompleteMain = ($has_masuk XOR $has_keluar);
                            $isRestExpected = (!empty($s_ist_keluar_time) && $s_ist_keluar_time != '00:00:00');
                            $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));
                            if ($hasIncompleteMain || $hasIncompleteBreak) $p_tidak_lengkap = $globalDendaTidakLengkap;
                        }
                    }

                    $key_lembur = $PIN . '_' . $date;
                    if (isset($custom_lembur[$key_lembur])) {
                        $final_lembur_jam = $custom_lembur[$key_lembur];
                    } else {
                        // Auto-calculated lembur hours for all shifts (flexible and regular)
                        $final_lembur_jam = 0;
                        if ($has_masuk && $has_keluar) {
                            $work_duration = (strtotime($pulang) - strtotime($masuk));
                            if ($work_duration < 0) {
                                $work_duration += 86400;
                            }
                            
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

                    if (!empty($row['is_pengganti'])) {
                        $upah = (float)($row['ganti_upah'] ?? 0);
                        $upah_bersih = $upah;
                        $hasil_kerja = 'HADIR';
                        $nama_karyawan .= ' (PENGGANTI' . (!empty($row['ganti_absen_nama']) ? ' - ' . $row['ganti_absen_nama'] : '') . ')';
                    } elseif (!empty($row['ganti_absen_nama'])) {
                        $nama_karyawan .= ' (Juga menggantikan ' . $row['ganti_absen_nama'] . ')';
                        $hasil_kerja = ($has_masuk || $has_keluar) ? 'HADIR' : 'TIDAK HADIR';
                    } else {
                        $hasil_kerja = ($has_masuk || $has_keluar) ? 'HADIR' : 'TIDAK HADIR';
                    }
                    $t_upah += $upah; $t_lembur += $lembur_money;
                    $t_pot += ($p_telat + $p_pulang + $p_ist_awal + $p_ist_telat + $p_tidak_lengkap);
                    $t_bersih += $upah_bersih;
                ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td class="text-center"><?php echo date('d-m-Y', strtotime($date)); ?></td>
                        <td class="text-center"><?php echo $PIN; ?></td>
                        <td class="text-bold"><?php echo htmlspecialchars($nama_karyawan); ?></td>
                        <td><?php echo htmlspecialchars($bagian); ?></td>
                        <td><?php echo htmlspecialchars($sub_bagian); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($os_dhk); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($golongan); ?></td>
                        <td class="text-center"><?php echo $s_masuk; ?></td>
                        <td class="text-center"><?php echo $s_keluar; ?></td>
                        <td class="text-center"><?php echo $s_ist_keluar; ?></td>
                        <td class="text-center"><?php echo $s_ist_masuk; ?></td>
                        <td class="text-center"><?php echo !empty($masuk) ? $masuk : '-'; ?></td>
                        <td class="text-center"><?php echo !empty($pulang) ? $pulang : '-'; ?></td>
                        <td class="text-center"><?php echo !empty($istirahat_keluar) ? $istirahat_keluar : '-'; ?></td>
                        <td class="text-center"><?php echo !empty($istirahat_masuk) ? $istirahat_masuk : '-'; ?></td>
                        <td class="text-right"><?php echo rupiah($upah); ?></td>
                        <td class="text-right"><?php echo $lembur_money > 0 ? rupiah($lembur_money) . ' (' . $final_lembur_jam . ' Jm)' : rupiah(0); ?></td>
                        <td class="text-right text-bold" style="color:#b91c1c;"><?php echo $p_telat > 0 ? rupiah($p_telat) : '-'; ?></td>
                        <td class="text-right text-bold" style="color:#b91c1c;"><?php echo $p_ist_awal > 0 ? rupiah($p_ist_awal) : '-'; ?></td>
                        <td class="text-right text-bold" style="color:#b91c1c;"><?php echo $p_ist_telat > 0 ? rupiah($p_ist_telat) : '-'; ?></td>
                        <td class="text-right text-bold" style="color:#b91c1c;"><?php echo $p_pulang > 0 ? rupiah($p_pulang) : '-'; ?></td>
                        <td class="text-right text-bold" style="color:#b91c1c;"><?php echo $p_tidak_lengkap > 0 ? rupiah($p_tidak_lengkap) : '-'; ?></td>
                        <td class="text-right"><?php echo rupiah(0); ?></td>
                        <td class="text-right text-bold" style="color:#047857;"><?php echo rupiah($upah_bersih); ?></td>
                        <td class="text-center text-bold" style="color:<?php echo $hasil_kerja == 'HADIR' ? '#047857' : '#b91c1c'; ?>;"><?php echo $hasil_kerja; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="16" class="text-right text-bold">TOTAL</td>
                    <td class="text-right text-bold"><?php echo rupiah($t_upah); ?></td>
                    <td class="text-right text-bold"><?php echo rupiah($t_lembur); ?></td>
                    <td class="text-right text-bold" colspan="5"><?php echo rupiah($t_pot); ?></td>
                    <td class="text-right text-bold">-</td>
                    <td class="text-right text-bold"><?php echo rupiah($t_bersih); ?></td>
                    <td class="text-center text-bold">-</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
