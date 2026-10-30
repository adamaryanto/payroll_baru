<?php
// Library sync realisasi (dipakai kelola.php & tarik_ajax.php). Guarded agar aman double-include.
if (!function_exists('is_flexible_shift')) {
    // Deteksi shift fleksibel dua ejaan: Flexible (X) & Fleksibel/Fleksi (KS).
    // Seluruh kode yg tadinya cek 'FLEXI' saja gagal mengenali "Fleksibel".
    function is_flexible_shift($s) {
        $u = strtoupper(trim((string)($s ?? '')));
        return (strpos($u, 'FLEXI') !== false || strpos($u, 'FLEKSI') !== false);
    }
}
if (!function_exists('late_minutes')) {
    // Menit keterlambatan (negatif = lebih awal).
    // 2026-10-26: Batas balik diubah dari 12 jam (43200 detik) ke 18 jam (64800 detik).
    // Alasannya kasus EFAUDIN: Jam Kerja 04:00, Masuk 20:07 = telat 16 jam 7 menit.
    // Dengan batas 12 jam, selisih 16 jam itu dibalik jadi -7 jam 53 menit (dianggap
    // datang lebih awal), jadi tidak merah dan tidak kena denda telat — padahal
    // harusnya telat. Dengan batas 18 jam, telat sampai 18 jam tetap kehitung telat,
    // sementara datang lebih awal sampai 6 jam tetap kehitung lebih awal (bukan telat).
    // Trade-off yang disadari: datang lebih awal >6 jam akan kehitung telat, tapi itu
    // jauh lebih jarang daripada telat >12 jam, dan shift malam yang masuk lewat tengah
    // malam (misal 19:00 -> 00:30 = telat 5,5 jam) tetap kehitung benar.
    function late_minutes($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return 0;
        $diff = (strtotime($actual) - strtotime($expected) + 86400) % 86400;
        if ($diff > 64800) $diff -= 86400;
        // Shift sore/malam (mulai >=12:00): tap pagi yang jauh lebih awal (6-18 jam)
        // jangan dihitung telat. Kasus: shift 19:00 tap 06:43 -> dulu telat 11j43m.
        // Batas 6 jam dipilih supaya tap lewat tengah malam (mis. 00:30 = telat 5,5j)
        // tetap telat. Ceiling: telat >6 jam di shift malam ikut dianggap lebih awal.
        $eHour = (int)substr((string)$expected, 0, 2);
        if ($eHour >= 12 && $diff > 21600) $diff -= 86400;
        return (int)($diff / 60);
    }
    // Menit kepulangan-awal (positif = pulang lebih cepat), normalisasi sama.
    function early_minutes($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return 0;
        $diff = (strtotime($expected) - strtotime($actual) + 86400) % 86400;
        if ($diff > 43200) $diff -= 86400;
        // Shift malam (pulang <=12:00): tap sore/hari sebelumnya yang >8 jam "lebih awal"
        // biasanya tap masuk shift, bukan pulang cepat. Kasus: pulang 19:06 vs 07:00.
        $eHour = (int)substr((string)$expected, 0, 2);
        if ($eHour <= 12 && $diff > 28800) $diff -= 86400;
        return (int)($diff / 60);
    }
}
if (!function_exists('load_toleransi')) {
    // Ambil batas toleransi (menit) dari tb_toleransi; default ikut tarik-data (10/5/5/10).
    function load_toleransi($koneksi) {
        try {
            $q = $koneksi->query("SELECT * FROM tb_toleransi LIMIT 1");
            $r = $q ? $q->fetch_assoc() : [];
        } catch (Throwable $e) { $r = []; }
        if (!is_array($r)) $r = [];
        return [
            'masuk' => (int)($r['toleransi_masuk'] ?? 10),
            'pulang' => (int)($r['toleransi_pulang'] ?? 5),
            'ist_keluar' => (int)($r['toleransi_istirahat_keluar'] ?? 5),
            'ist_masuk' => (int)($r['toleransi_istirahat_masuk'] ?? 10),
        ];
    }
}
if (!function_exists('is_time_late')) {
    function is_time_late($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return false;
        $diff = strtotime($actual) - strtotime($expected);
        if ($diff > 43200) $diff -= 86400;
        elseif ($diff < -43200) $diff += 86400;
        return $diff > 0;
    }
}
if (!function_exists('is_time_early')) {
    function is_time_early($actual, $expected) {
        if (empty($actual) || empty($expected) || $actual == '00:00:00' || $expected == '00:00:00' || $expected == 'Bebas') return false;
        $diff = strtotime($actual) - strtotime($expected);
        if ($diff > 43200) $diff -= 86400;
        elseif ($diff < -43200) $diff += 86400;
        return $diff < 0;
    }
}

function syncRealisasiData($koneksi, $id_realisasi, $prelogs = null, $prelogs_ok = false, $tgl_override = null)
{
    // 1. Get the realization date and global denda
    $query_real = $koneksi->query("SELECT tgl_realisasi FROM tb_realisasi WHERE id_realisasi = '$id_realisasi'");
    $data_real = $query_real->fetch_assoc();
    if (!$data_real) return 0;
    $tgl_real = $data_real['tgl_realisasi'];
    // Override tanggal manual (datepicker): sinkron cocokkan tap tanggal pilihan, bukan tgl realisasi
    if (!empty($tgl_override) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$tgl_override)) $tgl_real = (string)$tgl_override;
    $tgl_next = date('Y-m-d', strtotime('+1 day', strtotime($tgl_real)));

    // Preload SEMUA log 2 hari SEKALI (ganti N query per karyawan).
    // Urutan per pin dipertahankan (tgl ASC, detail_waktu ASC) persis query lama.
    $bulk_logs = is_array($prelogs) ? $prelogs : [];
    $bulk_ok = $prelogs_ok;
    if (!$bulk_ok) {
        $t0e = $koneksi->real_escape_string($tgl_real);
        $t1e = $koneksi->real_escape_string($tgl_next);
        $qb = $koneksi->query("SELECT userid, tgl, status, detail_waktu FROM tb_record WHERE tgl IN ('$t0e','$t1e') ORDER BY tgl ASC, detail_waktu ASC");
        if ($qb) {
            $bulk_ok = true;
            while ($br = $qb->fetch_assoc()) $bulk_logs[$br['userid']][] = $br;
        }
    }

    $query_denda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $denda = $query_denda->fetch_assoc();
    $gd_masuk = $denda['denda_masuk'] ?? 0;
    $gd_istirahat_awal = $denda['denda_istirahat_keluar'] ?? 0;
    $gd_istirahat_telat = $denda['denda_istirahat_masuk'] ?? 0;
    $gd_pulang = $denda['denda_pulang'] ?? 0;
    $gd_tidak_lengkap = $denda['denda_tidak_lengkap'] ?? 0;
    // Toleransi (menit) — SAMA kayak halaman tarik data
    $tol = function_exists('load_toleransi') ? load_toleransi($koneksi) : ['masuk' => 10, 'pulang' => 5, 'ist_keluar' => 5, 'ist_masuk' => 10];

    // ID grup TIDAK DALAM RENCANA (baris tap tanpa master): jangan ditimpa sync
    $gid_tdr = 0;
    $qg0 = $koneksi->query("SELECT id_grup FROM ms_grup_borongan WHERE UPPER(TRIM(nama_grup)) = 'TIDAK DALAM RENCANA' LIMIT 1");
    if ($qg0 && ($gg0 = $qg0->fetch_assoc())) $gid_tdr = (int)$gg0['id_grup'];

    // 2. Get all employees in this realization (using LEFT JOIN to include manual employees and RKK fallback)
    $query_details = $koneksi->query("SELECT RD.id_realisasi_detail, RD.id_karyawan, RD.id_pengganti, RD.id_grup AS grup_id, RD.r_upah as r_upah_lama, K.no_absen, K.upah as upah_master, RKKD.upah as upah_rkk, RKKD.status_rkk as status_rkk, RD.id_jadwal as detail_jadwal, RKKD.id_jadwal as rkk_jadwal, RD.status_realisasi_detail, 
                                      COALESCE(J.keterangan, JR.keterangan) as shift_ket,
                                      COALESCE(J.jam_masuk, JR.jam_masuk) as s_masuk, 
                                      COALESCE(J.jam_keluar, JR.jam_keluar) as s_keluar, 
                                      COALESCE(J.istirahat_masuk, JR.istirahat_masuk) as s_ist_masuk, 
                                      COALESCE(J.istirahat_keluar, JR.istirahat_keluar) as s_ist_keluar
                                      FROM tb_realisasi_detail RD
                                      LEFT JOIN ms_karyawan K ON RD.id_karyawan = K.id_karyawan
                                      LEFT JOIN tb_rkk_detail RKKD ON RD.id_rkk_detail = RKKD.id_rkk_detail
                                      LEFT JOIN tb_jadwal J ON RD.id_jadwal = J.id_jadwal
                                      LEFT JOIN tb_jadwal JR ON RKKD.id_jadwal = JR.id_jadwal
                                      WHERE RD.id_realisasi = '$id_realisasi'");

    $count = 0;
    while ($row = $query_details->fetch_assoc()) {
        $id_rd = $row['id_realisasi_detail'];
        // Baris TIDAK DALAM RENCANA dilewati (datanya dari tap langsung, jangan ditimpa jadi kosong)
        if ($gid_tdr > 0 && (int)($row['grup_id'] ?? -1) === $gid_tdr) continue;
        // Baris yang digantikan lewat Detail (id_pengganti > 0) juga dilewati sync:
        // tap mesin di sini dicari pakai PIN/no_absen karyawan ASLI, bukan pengganti,
        // jadi Tarik Data akan selalu menganggapnya total tidak hadir dan me-nol-kan
        // upah — padahal penggantinya sudah dikonfirmasi manual di Detail. Baris ini
        // dikelola manual sampai penggantinya dibatalkan (id_pengganti jadi NULL),
        // baru setelah itu sync jalan lagi untuk baris tersebut.
        if ((int)($row['id_pengganti'] ?? 0) > 0) continue;
        $no_absen = $row['no_absen'];
        $idk_row = (int)($row['id_karyawan'] ?? 0);
        $status_rd = $row['status_realisasi_detail'];
        
        // Include manual employees (Previously skipped)
        $shift_ket = $row['shift_ket'] ?? '';
        $is_flexible = is_flexible_shift($shift_ket);

        $s_masuk = $row['s_masuk'] ?? '00:00:00';
        $s_keluar = $row['s_keluar'] ?? '00:00:00';
        $s_ist_masuk = $row['s_ist_masuk'] ?? '00:00:00';
        $s_ist_keluar = $row['s_ist_keluar'] ?? '00:00:00';

        if (empty($no_absen)) {
            $is_total_missing = true;
            $ra_masuk = "'00:00:00'";
            $ra_keluar = "'00:00:00'";
            $ra_ist_keluar = "'00:00:00'";
            $ra_ist_masuk = "'00:00:00'";
        } else {
            // Ambil dari peta preload (kunci EXACT userid, sama kayak WHERE lama).
            // Fallback ke query per baris bila preload tidak tersedia/gagal.
            $raw_logs = [];
            if ($bulk_ok && array_key_exists($no_absen, $bulk_logs)) {
                $raw_logs = $bulk_logs[$no_absen];
            } else {
                // Get logs for today and tomorrow to support cross-midnight night-shift merge
                $tgl_next = date('Y-m-d', strtotime('+1 day', strtotime($tgl_real)));
                $q_logs = $koneksi->query("SELECT * FROM tb_record WHERE userid = '$no_absen' AND tgl IN ('$tgl_real', '$tgl_next') ORDER BY tgl ASC, detail_waktu ASC");
                while ($q_logs && ($r_log = $q_logs->fetch_assoc())) {
                    $raw_logs[] = $r_log;
                }
            }

            // PIN lain milik karyawan yang sama di mesin berbeda (mapping nama per mesin)
            if ($idk_row > 0 && function_exists('karyawan_pins_by_id')) {
                $extra_pins = karyawan_pins_by_id($koneksi)[$idk_row] ?? [];
                $tgl_next2 = date('Y-m-d', strtotime('+1 day', strtotime($tgl_real)));
                $tgl_e = $koneksi->real_escape_string($tgl_real);
                $tgl_e2 = $koneksi->real_escape_string($tgl_next2);
                foreach ($extra_pins as $ep) {
                    $did_ep = (int)($ep[0] ?? 0);
                    $pin_ep = trim((string)($ep[1] ?? ''));
                    if ($pin_ep === '' || $pin_ep === (string)$no_absen) continue;
                    $pin_e = $koneksi->real_escape_string($pin_ep);
                    $q_ep = $koneksi->query("SELECT * FROM tb_record WHERE source_device_id = '$did_ep' AND TRIM(userid) = '$pin_e' AND tgl IN ('$tgl_e','$tgl_e2') ORDER BY tgl ASC, detail_waktu ASC");
                    while ($q_ep && ($r_ep = $q_ep->fetch_assoc())) $raw_logs[] = $r_ep;
                }
                if (!empty($raw_logs)) usort($raw_logs, function($a, $b) {
                    return strcmp(($a['tgl'] ?? '') . ' ' . ($a['detail_waktu'] ?? ''), ($b['tgl'] ?? '') . ' ' . ($b['detail_waktu'] ?? ''));
                });
            }

            $log_masuk = null;
            $log_keluar = null;
            $log_ist_keluar = null;
            $log_ist_masuk = null;
            
            // Find earliest check-in (status 0) on $tgl_real
            foreach ($raw_logs as $l) {
                if ($l['tgl'] == $tgl_real && $l['status'] == 0) {
                    $log_masuk = substr($l['detail_waktu'], 11, 8);
                    break;
                }
            }
            
            // Fallback if no status 0 on $tgl_real: use first scan of the day
            if (!$log_masuk) {
                foreach ($raw_logs as $l) {
                    if ($l['tgl'] == $tgl_real) {
                        $log_masuk = substr($l['detail_waktu'], 11, 8);
                        break;
                    }
                }
            }
            
            if ($log_masuk) {
                $in_timestamp = strtotime($tgl_real . ' ' . $log_masuk);
                // Tap <10 menit dari masuk = dobel/absen ganda, abaikan (sama kayak dedup tarik data)
                $gap10 = 10 / 60;

                // Find check-out (status 1) within 16 hours (check backwards for the latest valid checkout)
                for ($i = count($raw_logs) - 1; $i >= 0; $i--) {
                    $l = $raw_logs[$i];
                    if ($l['status'] == 1) {
                        $curr_time = substr($l['detail_waktu'], 11, 8);
                        $curr_timestamp = strtotime($l['tgl'] . ' ' . $curr_time);
                        $diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
                        if ($diff_hours > $gap10 && $diff_hours <= 16) {
                            $log_keluar = $curr_time;
                            break;
                        }
                    }
                }

                // Fallback: if no check-out with status 1 is found, use the latest scan within 16 hours of check-in
                if (!$log_keluar) {
                    for ($i = count($raw_logs) - 1; $i >= 0; $i--) {
                        $l = $raw_logs[$i];
                        $curr_time = substr($l['detail_waktu'], 11, 8);
                        if ($l['tgl'] == $tgl_real && $curr_time == $log_masuk) {
                            continue;
                        }
                        $curr_timestamp = strtotime($l['tgl'] . ' ' . $curr_time);
                        $diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
                        if ($diff_hours > $gap10 && $diff_hours <= 16) {
                            $log_keluar = $curr_time;
                            break;
                        }
                    }
                }

                // Find break-out (status 2) and break-in (status 3) within 16 hours
                foreach ($raw_logs as $l) {
                    $curr_time = substr($l['detail_waktu'], 11, 8);
                    $curr_timestamp = strtotime($l['tgl'] . ' ' . $curr_time);
                    $diff_hours = ($curr_timestamp - $in_timestamp) / 3600;
                    if ($diff_hours > $gap10 && $diff_hours <= 16) {
                        if ($l['status'] == 2 && !$log_ist_keluar) {
                            $log_ist_keluar = $curr_time;
                        }
                        if ($l['status'] == 3 && !$log_ist_masuk) {
                            $log_ist_masuk = $curr_time;
                        }
                    }
                }

                // Fallback: mesin tidak mengirim status 2/3, tebak istirahat dari tap tengah.
                // Hari normal 4 tap (masuk, ist-keluar, ist-masuk, pulang): ambil tap ke-2 & tengah-terakhir.
                // Pengaman: abaikan tap ganda (menit sama) + tap yang menempel masuk/pulang (<30 mnt).
                if (!$log_ist_keluar || !$log_ist_masuk) {
                    $pool = []; $seen_min = [];
                    foreach ($raw_logs as $l) {
                        if (in_array((int)$l['status'], [4, 5], true)) continue; // lembur, bukan istirahat
                        $ct = substr($l['detail_waktu'], 11, 8);
                        $mk = substr($ct, 0, 5);
                        if (isset($seen_min[$mk])) continue;
                        $seen_min[$mk] = true;
                        $cts = strtotime($l['tgl'] . ' ' . $ct);
                        $dh = ($cts - $in_timestamp) / 3600;
                        if ($dh >= 0 && $dh <= 16) $pool[] = ['t' => $ct, 'ts' => $cts];
                    }
                    usort($pool, function($a, $b) { return $a['ts'] - $b['ts']; });
                    $m = count($pool);
                    $pul_end = $m > 0 ? $pool[$m - 1]['ts'] : 0;
                    $is_mid = function($ts) use ($in_timestamp, $pul_end) {
                        return ($ts - $in_timestamp) >= 1800 && ($pul_end <= 0 || ($pul_end - $ts) >= 1800);
                    };
                    if ($m >= 4) {
                        if (!$log_ist_keluar && $is_mid($pool[1]['ts'])) $log_ist_keluar = $pool[1]['t'];
                        if (!$log_ist_masuk && $is_mid($pool[$m - 2]['ts'])) $log_ist_masuk = $pool[$m - 2]['t'];
                    } elseif ($m == 3) {
                        // 3 tap: tentukan sisi dari titik tengah jam istirahat shift
                        $bk = trim((string)$s_ist_keluar); $bm = trim((string)$s_ist_masuk);
                        if ($bk !== '' && $bk !== '00:00:00' && $bm !== '' && $bm !== '00:00:00' && $is_mid($pool[1]['ts'])) {
                            $mid = (strtotime($bk) + strtotime($bm)) / 2;
                            $mid_t = date('H:i:s', (int)$mid);
                            if ($pool[1]['t'] < $mid_t) { if (!$log_ist_keluar) $log_ist_keluar = $pool[1]['t']; }
                            else { if (!$log_ist_masuk) $log_ist_masuk = $pool[1]['t']; }
                        }
                    }
                }
            }
            
            $logs = [
                'log_masuk' => $log_masuk,
                'log_keluar' => $log_keluar,
                'log_ist_keluar' => $log_ist_keluar,
                'log_ist_masuk' => $log_ist_masuk
            ];
            
            $ra_masuk = $log_masuk ? "'" . $log_masuk . "'" : "'00:00:00'";
            $ra_keluar = $log_keluar ? "'" . $log_keluar . "'" : "'00:00:00'";
            $ra_ist_keluar = $log_ist_keluar ? "'" . $log_ist_keluar . "'" : "'00:00:00'";
            $ra_ist_masuk = $log_ist_masuk ? "'" . $log_ist_masuk . "'" : "'00:00:00'";
            
            $has_masuk = !empty($log_masuk);
            $has_keluar = !empty($log_keluar);
            $has_ist_keluar = !empty($log_ist_keluar);
            $has_ist_masuk = !empty($log_ist_masuk);
            
            $is_total_missing = (!$has_masuk && !$has_keluar);
        }

        // 4. Calculate Penalties and Handle Absence
        $p_telat = 0;
        $p_pulang = 0;
        $p_ist_awal = 0;
        $p_ist_telat = 0;
        $p_tidak_lengkap = 0;

        // If no_absen was empty, these are already set correctly in the if block.
        // If no_absen was NOT empty, logs were fetched. 
        // We ensure $has_ variables exist for the penalty logic below.
        if (!empty($no_absen)) {
            $has_masuk = !empty($logs['log_masuk']);
            $has_keluar = !empty($logs['log_keluar']);
            $has_ist_keluar = !empty($logs['log_ist_keluar']);
            $has_ist_masuk = !empty($logs['log_ist_masuk']);
            $is_total_missing = (!$has_masuk && !$has_keluar);
        } else {
            $has_masuk = false;
            $has_keluar = false;
            $has_ist_keluar = false;
            $has_ist_masuk = false;
            $is_total_missing = true;
        }

        if (!$is_total_missing && !$is_flexible) {
            // Late Entrance (lewat toleransi; flexi murni bebas)
            if ($has_masuk && late_minutes($logs['log_masuk'], $s_masuk) > $tol['masuk']) {
                $p_telat = $gd_masuk;
            }
            // Early Departure (lewat toleransi)
            if ($has_keluar && early_minutes($logs['log_keluar'], $s_keluar) > $tol['pulang']) {
                $p_pulang = $gd_pulang;
            }
            // Early Break (lewat toleransi)
            if ($has_ist_keluar && early_minutes($logs['log_ist_keluar'], $s_ist_keluar) > $tol['ist_keluar']) {
                $p_ist_awal = $gd_istirahat_awal;
            }
            // Late Break Return (lewat toleransi)
            if ($has_ist_masuk && late_minutes($logs['log_ist_masuk'], $s_ist_masuk) > $tol['ist_masuk']) {
                $p_ist_telat = $gd_istirahat_telat;
            }

            // Incomplete Log (Missing any expected punch)
            $hasIncompleteMain = ($has_masuk xor $has_keluar);
            $isRestExpected = (!$is_flexible && !empty($s_ist_keluar) && $s_ist_keluar != '00:00:00');
            $hasIncompleteBreak = ($isRestExpected && (!$has_ist_keluar || !$has_ist_masuk));

            if ($hasIncompleteMain || $hasIncompleteBreak) {
                $p_tidak_lengkap = $gd_tidak_lengkap;
            }
        }

        // Determine Wage adjustment for total absence
        // PATOKAN UPAH = RENCANA (tb_rkk_detail.upah), bukan lagi master karyawan.
        // Urutan: Digantikan = 0 (jangan dibayar) > upah RKK (>0) > upah master (>0) > r_upah lama
        // (r_upah lama dipakai untuk baris tanpa RKK & tanpa master, misal karyawan manual
        // tambahan — supaya upah yang diinput manual tidak jadi 0).
        // Baris yang sudah diedit manual (status_realisasi_detail = 1) TIDAK ditimpa upahnya
        // oleh sync, kecuali kalau total tidak hadir (aturan tidak hadir tetap menang).
        $upah_rkk = (float)($row['upah_rkk'] ?? 0);
        $status_rkk = $row['status_rkk'] ?? '';
        $upah_master = (float)($row['upah_master'] ?? 0);
        $r_upah_lama = (float)($row['r_upah_lama'] ?? 0);
        if ($status_rkk === 'Digantikan') {
            $upah_pakai = 0;
        } elseif ($upah_rkk > 0) {
            $upah_pakai = $upah_rkk;
        } elseif ($upah_master > 0) {
            $upah_pakai = $upah_master;
        } else {
            $upah_pakai = $r_upah_lama;
        }
        $wage_update_sql = "";
        $status_update_sql = "status_realisasi_detail = 2";
        $status_enum = "Hadir";
        if ($is_total_missing) {
            $wage_update_sql = "r_upah = 0,";
            $status_enum = "Tidak Hadir";
        } elseif ((int)($status_rd ?? 0) === 1) {
            // Manual: upah dipertahankan, status tetap 1 supaya sync berikutnya juga tidak menimpa.
            $wage_update_sql = "";
            $status_update_sql = "status_realisasi_detail = 1";
        } else {
            $wage_update_sql = "r_upah = '$upah_pakai',";
        }

        // 5. Update the detail record and SET STATUS TO 2 (Synced)
        $update = $koneksi->query("UPDATE tb_realisasi_detail SET 
            r_jam_masuk = '$s_masuk',
            r_jam_keluar = '$s_keluar',
            r_istirahat_masuk = '$s_ist_masuk',
            r_istirahat_keluar = '$s_ist_keluar',
            ra_masuk = $ra_masuk,
            ra_keluar = $ra_keluar,
            ra_istirahat_masuk = $ra_ist_masuk,
            ra_istirahat_keluar = $ra_ist_keluar,
            r_potongan_telat = '$p_telat',
            r_potongan_istirahat_awal = '$p_ist_awal',
            r_potongan_istirahat_telat = '$p_ist_telat',
            r_potongan_pulang = '$p_pulang',
            r_potongan_tidak_lengkap = '$p_tidak_lengkap',
            $wage_update_sql
            r_status = '$status_enum',
            $status_update_sql
            WHERE id_realisasi_detail = '$id_rd'");

        if ($update) $count++;
    }

    return $count;
}
