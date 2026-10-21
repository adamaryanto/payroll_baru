<?php
/**
 * Device Sync Helper for X606S / ZKTeco Fingerprint Devices
 * Actual SOAP Web Service implementation
 */
set_time_limit(0);
ignore_user_abort(true);
// Helper protokol biner (X902) opsional: jangan fatal kalau file tidak ikut ke-copy ke server lain
if (file_exists(__DIR__ . '/zk_protocol_helper.php')) {
    include_once __DIR__ . '/zk_protocol_helper.php';
}

/**
 * Cache "kapan terakhir pull sukses" per tanggal — biar tombol Tarik tidak
 * mengulang SOAP ke mesin kalau datanya masih segar (default 10 menit).
 */
function pull_cache_ensure($koneksi) {
    try {
        $koneksi->query("CREATE TABLE IF NOT EXISTS tb_pull_cache (
            tgl DATE NOT NULL PRIMARY KEY,
            pulled_at DATETIME NOT NULL,
            records INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB");
    } catch (Throwable $e) {}
}
function pull_cache_mark($koneksi, $start_date, $end_date, $records) {
    try {
        pull_cache_ensure($koneksi);
        $t = strtotime((string)$start_date); $e = strtotime((string)$end_date);
        if (!$t || !$e || $e < $t) return;
        $n = 0;
        for ($d = $t; $d <= $e && $n < 370; $d += 86400, $n++) {
            $dt = date('Y-m-d', $d);
            $koneksi->query("INSERT INTO tb_pull_cache (tgl, pulled_at, records) VALUES ('$dt', NOW(), " . (int)$records . ") ON DUPLICATE KEY UPDATE pulled_at = NOW(), records = " . (int)$records);
        }
    } catch (Throwable $e2) {}
}
// Segar bila SELURUH tanggal sudah pernah ditarik dalam $minutes menit terakhir.
function pull_cache_fresh($koneksi, $start_date, $end_date, $minutes = 10) {
    try {
        pull_cache_ensure($koneksi);
        $t = strtotime((string)$start_date); $e = strtotime((string)$end_date);
        if (!$t || !$e || $e < $t) return ['fresh' => false, 'age_min' => null];
        $need = [];
        for ($d = $t; $d <= $e; $d += 86400) {
            $need[] = date('Y-m-d', $d);
            if (count($need) > 370) return ['fresh' => false, 'age_min' => null];
        }
        $in = "'" . implode("','", $need) . "'";
        $q = $koneksi->query("SELECT tgl, TIMESTAMPDIFF(MINUTE, pulled_at, NOW()) AS age FROM tb_pull_cache WHERE tgl IN ($in)");
        $got = [];
        while ($q && ($r = $q->fetch_assoc())) $got[$r['tgl']] = (int)$r['age'];
        $max_age = 0;
        foreach ($need as $dt) {
            if (!isset($got[$dt]) || $got[$dt] > (int)$minutes) return ['fresh' => false, 'age_min' => null];
            $max_age = max($max_age, $got[$dt]);
        }
        return ['fresh' => true, 'age_min' => $max_age];
    } catch (Throwable $ex) { return ['fresh' => false, 'age_min' => null]; }
}

/**
 * Normalisasi nama untuk pencocokan nama mesin <-> master karyawan.
 */
function norm_nama_karyawan($s) {
    $s = strtoupper(trim((string)$s));
    $s = preg_replace('/[^A-Z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/**
 * Tabel mapping PIN per mesin -> karyawan.
 * Satu orang boleh punya PIN berbeda di tiap mesin.
 */
function ensure_karyawan_pin_table($koneksi) {
    static $done = false;
    if ($done) return;
    $done = true;
    @$koneksi->query("CREATE TABLE IF NOT EXISTS ms_karyawan_pin (
        id_mesin INT NOT NULL,
        pin VARCHAR(32) NOT NULL,
        id_karyawan INT NOT NULL,
        nama_mesin VARCHAR(255) DEFAULT NULL,
        tgl_updt DATETIME DEFAULT NULL,
        PRIMARY KEY (id_mesin, pin),
        KEY idx_karyawan (id_karyawan)
    ) ENGINE=InnoDB");
}

/**
 * Ambil daftar user tiap mesin: [id_mesin][pin] = nama.
 * Hanya mesin yang online. Dipakai sekali saat Tarik untuk mengisi mapping.
 */
function get_device_users_full($koneksi, $force = false) {
    $dbname = '';
    if ($qdb = @$koneksi->query("SELECT DATABASE() AS d")) { $rdb = $qdb->fetch_assoc(); $dbname = (string)($rdb['d'] ?? ''); }
    $cacheFile = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'payroll_devusers2_' . md5($dbname) . '.json';
    if (!$force && is_file($cacheFile) && (time() - (int)@filemtime($cacheFile)) < 600) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        // Cache kosong (mesin offline saat itu) hanya dipakai 1 menit, lalu coba baca lagi.
        if (is_array($cached) && isset($cached['u']) && is_array($cached['u']) && (!empty($cached['u']) || (time() - (int)@filemtime($cacheFile)) < 60)) return $cached;
    }
    // Simpan data lama: kalau satu mesin gagal/response terpotong, nama lama tetap dipakai.
    $prevU = []; $prevP = [];
    if (is_file($cacheFile)) {
        $prev = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($prev)) { $prevU = is_array($prev['u'] ?? null) ? $prev['u'] : []; $prevP = is_array($prev['p2'] ?? null) ? $prev['p2'] : []; }
    }
    $out = []; $out2 = [];
    if (!function_exists('get_all_devices')) return ['u' => $out, 'p2' => $out2];
    foreach (get_all_devices($koneksi) as $device) {
        $did = (int)($device['id_mesin'] ?? 0);
        if ($did <= 0 || empty($device['ip_mesin'])) continue;
        if (function_exists('test_device_connection') && !test_device_connection($device['ip_mesin'], $device['port'])) {
            if (!empty($prevU[$did])) { $out[$did] = $prevU[$did]; $out2[$did] = $prevP[$did] ?? []; }
            continue;
        }
        if (($device['protocol'] ?? 'soap') === 'zkudp') {
            if (!function_exists('zkb_get_users')) continue;
            $zr = zkb_get_users($device['ip_mesin'], $device['port'] ?? 4370, intval($device['comm_key'] ?? 0));
            if (empty($zr['success'])) {
                if (!empty($prevU[$did])) { $out[$did] = $prevU[$did]; $out2[$did] = $prevP[$did] ?? []; }
                continue;
            }
            foreach (($zr['users'] ?? []) as $u) {
                $pin = trim((string)($u['pin'] ?? ''));
                $nm = trim((string)($u['nama'] ?? ''));
                $p2 = trim((string)($u['pin2'] ?? ''));
                if ($pin !== '' && $nm !== '') { $out[$did][$pin] = $nm; $out2[$did][$pin] = $p2; }
            }
            continue;
        }
        if (!function_exists('send_soap_request')) continue;
        $comm_key = $device['comm_key'] ?? 0;
        $soap_users = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></GetAllUserInfo>";
        // Daftar user bisa besar; timeout 5 detik sering memotong response di tengah XML
        // sehingga sebagian nama hilang. Beri waktu lebih, dan ulangi bila response terpotong.
        $resp = send_soap_request($device['ip_mesin'], $device['port'], $soap_users, 15);
        $complete = is_string($resp) && strpos($resp, '</GetAllUserInfoResponse>') !== false;
        if (is_string($resp) && trim($resp) !== '' && !$complete) {
            $resp = send_soap_request($device['ip_mesin'], $device['port'], $soap_users, 30);
            $complete = is_string($resp) && strpos($resp, '</GetAllUserInfoResponse>') !== false;
        }
        $didU = []; $didP = [];
        if ($resp) {
            $pos = 0;
            while (($start = strpos($resp, '<Row>', $pos)) !== false && ($end = strpos($resp, '</Row>', $start)) !== false) {
                $row = substr($resp, $start + 5, $end - $start - 5);
                $pin = $pin2 = $name = '';
                if (preg_match('/<PIN>([^<]*)<\/PIN>/', $row, $m)) $pin = trim($m[1]);
                if (preg_match('/<PIN2>([^<]*)<\/PIN2>/', $row, $m)) $pin2 = trim($m[1]);
                if (preg_match('/<Name>([^<]*)<\/Name>/', $row, $m)) $name = trim($m[1]);
                if ($pin !== '' && $name !== '') { $didU[$pin] = $name; $didP[$pin] = $pin2; }
                $pos = $end + 6;
            }
        }
        if (!empty($didU) && ($complete || empty($prevU[$did]))) {
            $out[$did] = $didU; $out2[$did] = $didP;
        } else {
            // Response gagal/terpotong: gabungkan nama lama + nama baru yang terbaca.
            $out[$did] = array_merge($prevU[$did] ?? [], $didU);
            $out2[$did] = array_merge($prevP[$did] ?? [], $didP);
        }
    }
    $data = ['u' => $out, 'p2' => $out2];
    @file_put_contents($cacheFile, json_encode($data));
    return $data;
}

/** [id_mesin][pin] = nama */
function get_device_users_map($koneksi, $force = false) {
    $d = get_device_users_full($koneksi, $force);
    return is_array($d['u'] ?? null) ? $d['u'] : [];
}

/** [id_mesin][pin] = PIN2 (nomor absen yang sama antar mesin) */
function get_device_pin2_map($koneksi, $force = false) {
    $d = get_device_users_full($koneksi, $force);
    return is_array($d['p2'] ?? null) ? $d['p2'] : [];
}

/**
 * Isi/segarkan mapping (id_mesin, pin) -> id_karyawan:
 * 1) nama mesin == nama master (unik) -> prioritas utama
 * 2) kalau tidak, pin == no_absen master (unik) -> fallback
 * Nama kembar di master dilewati (tidak ditebak) agar tidak salah orang.
 */
function refresh_karyawan_pin_map($koneksi, $force = false) {
    ensure_karyawan_pin_table($koneksi);
    $by_name = []; $dup_name = [];
    $by_pin = [];
    $qk = $koneksi->query("SELECT id_karyawan, nama_karyawan, no_absen FROM ms_karyawan WHERE nama_karyawan NOT LIKE 'User PIN%'");
    while ($qk && ($r = $qk->fetch_assoc())) {
        $nn = norm_nama_karyawan($r['nama_karyawan'] ?? '');
        if ($nn !== '') {
            if (isset($by_name[$nn])) $dup_name[$nn] = true;
            else $by_name[$nn] = (int)$r['id_karyawan'];
        }
        $pin = trim((string)($r['no_absen'] ?? ''));
        if ($pin !== '') $by_pin[$pin][] = (int)$r['id_karyawan'];
    }
    $dev = get_device_users_map($koneksi, $force);
    $devp2 = get_device_pin2_map($koneksi, $force);
    // Rebuild hanya untuk mesin yang berhasil dibaca (mesin offline tidak menghapus mapping lama).
    foreach (array_keys($dev) as $did_del) $koneksi->query("DELETE FROM ms_karyawan_pin WHERE id_mesin = " . (int)$did_del);
    // Satu PIN bisa orang berbeda di mesin berbeda: kumpulkan sidik nama per PIN.
    // Kalau nama di mesin-mesin berbeda, fallback nomor absen TIDAK dipakai.
    $pin_sigs = []; $alias_sigs = [];
    foreach ($dev as $did => $pins) {
        foreach ($pins as $pin => $nm) {
            $nn2 = norm_nama_karyawan($nm);
            $sig = $nn2 !== '' ? $nn2 : '##blank';
            $pin_sigs[$pin][$sig] = true;
            $p2 = trim((string)($devp2[$did][$pin] ?? ''));
            if ($p2 !== '') $alias_sigs[$p2][$sig] = true;
        }
    }
    $vals = [];
    foreach ($dev as $did => $pins) {
        foreach ($pins as $pin => $nm) {
            $kid = 0;
            $nn = norm_nama_karyawan($nm);
            $p2 = trim((string)($devp2[$did][$pin] ?? ''));
            if ($nn !== '' && isset($by_name[$nn]) && !isset($dup_name[$nn])) {
                $kid = $by_name[$nn];
            } elseif ($p2 !== '' && isset($by_pin[$p2]) && count($by_pin[$p2]) === 1) {
                // PIN2 = nomor absen yang konsisten antar mesin
                $kid = $by_pin[$p2][0];
            } elseif (isset($by_pin[$pin]) && count($by_pin[$pin]) === 1) {
                $kid = $by_pin[$pin][0];
            }
            if ($kid <= 0) continue;
            $pin_e = $koneksi->real_escape_string($pin);
            $nm_e = $koneksi->real_escape_string($nm);
            $vals[] = "(" . (int)$did . ", '$pin_e', $kid, '$nm_e', NOW())";
        }
    }
    $n = 0;
    foreach (array_chunk($vals, 200) as $chunk) {
        $sql = "INSERT INTO ms_karyawan_pin (id_mesin, pin, id_karyawan, nama_mesin, tgl_updt) VALUES " . implode(", ", $chunk)
             . " ON DUPLICATE KEY UPDATE id_karyawan = VALUES(id_karyawan), nama_mesin = VALUES(nama_mesin), tgl_updt = NOW()";
        if ($koneksi->query($sql)) $n += count($chunk);
    }
    return $n;
}

/**
 * Mapping siap pakai: [id_mesin][pin] => id_karyawan.
 */
function karyawan_pin_map($koneksi) {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    ensure_karyawan_pin_table($koneksi);
    $q = $koneksi->query("SELECT id_mesin, pin, id_karyawan FROM ms_karyawan_pin");
    while ($q && ($r = $q->fetch_assoc())) $map[(int)$r['id_mesin']][trim((string)$r['pin'])] = (int)$r['id_karyawan'];
    return $map;
}

/**
 * PIN-PIN yang terhubung ke satu karyawan: [ [id_mesin, pin], ... ].
 */
function karyawan_pins_by_id($koneksi) {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    ensure_karyawan_pin_table($koneksi);
    $q = $koneksi->query("SELECT id_mesin, pin, id_karyawan FROM ms_karyawan_pin");
    while ($q && ($r = $q->fetch_assoc())) $map[(int)$r['id_karyawan']][] = [(int)$r['id_mesin'], trim((string)$r['pin'])];
    return $map;
}

/**
 * Proses tap tak-terdaftar jadi baris grup TIDAK DALAM RENCANA (upah 0, anti-dobel).
 * Dipakai tombol Tarik di kelola.php & endpoint tarik_ajax.php. Return ['added' => int].
 */
function realisasi_proses_tdr($koneksi, $idrealisasi, $tgl_override = null, $skip_dev_names = false) {
    $added = 0;
    $err = '';
    $idrealisasi = (int)$idrealisasi;
    if ($idrealisasi <= 0) return ['added' => 0, 'error' => ''];
    try {
        $qr = $koneksi->query("SELECT tgl_realisasi, id_rkk FROM tb_realisasi WHERE id_realisasi = '$idrealisasi'");
        $dr = $qr ? $qr->fetch_assoc() : null;
        if (!$dr || empty($dr['tgl_realisasi'])) return ['added' => 0, 'error' => ''];
        $t0 = $dr['tgl_realisasi'];
        // Override tanggal manual (datepicker): proses tap tanggal pilihan
        if (!empty($tgl_override) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$tgl_override)) $t0 = (string)$tgl_override;
        $t1 = date('Y-m-d', strtotime('+1 day', strtotime($t0)));
        $t0e = $koneksi->real_escape_string($t0);
        $t1e = $koneksi->real_escape_string($t1);

        $gid_tdr = 0; $gids_tdr = [];
        $qg0 = $koneksi->query("SELECT id_grup FROM ms_grup_borongan WHERE UPPER(TRIM(nama_grup)) = 'TIDAK DALAM RENCANA' ORDER BY id_grup ASC");
        while ($qg0 && ($gg0 = $qg0->fetch_assoc())) {
            $gids_tdr[] = (int)$gg0['id_grup'];
            if ($gid_tdr <= 0) $gid_tdr = (int)$gg0['id_grup'];
        }
        // Bisa ada lebih dari satu grup bernama sama (sisa proses lama) -> bersihkan semuanya.
        $gid_in = !empty($gids_tdr) ? implode(',', $gids_tdr) : '0';

        // Mapping PIN per mesin -> karyawan (nama mesin = nama master)
        ensure_karyawan_pin_table($koneksi);
        $pinmap = karyawan_pin_map($koneksi);

        // Peta master: id -> data, pin/no_absen -> daftar id (fallback bila mapping kosong)
        $master = []; $pin_owner = []; $by_name = []; $dup_name = [];
        $qk = $koneksi->query("SELECT id_karyawan, nama_karyawan, no_absen, id_departmen, id_sub_department, id_os_dhk, id_golongan FROM ms_karyawan WHERE nama_karyawan NOT LIKE 'User PIN%'");
        while ($qk && ($rk = $qk->fetch_assoc())) {
            $kid = (int)$rk['id_karyawan'];
            $master[$kid] = $rk;
            $nn = norm_nama_karyawan($rk['nama_karyawan'] ?? '');
            if ($nn !== '') {
                if (isset($by_name[$nn])) $dup_name[$nn] = true;
                else $by_name[$nn] = $kid;
            }
            $pin = trim((string)($rk['no_absen'] ?? ''));
            if ($pin !== '') $pin_owner[$pin][] = $kid;
        }

        // Kumpulkan tap: yang ter-resolve ke karyawan vs PIN yang tetap tak dikenal
        $by_emp = []; $by_pin = [];
        $recs = []; $pin_devs = [];
        // PIN2 (nomor absen konsisten antar mesin) + nama mesin untuk tampilan
        $devmap_did = get_device_users_map($koneksi);
        $pin2map = get_device_pin2_map($koneksi);
        // Kebalikan PIN2 -> PIN mesin: sebagian mesin mencatat PIN2 di log absensi.
        $pin2rev = [];
        foreach ($pin2map as $dmd => $pins) {
            if (!is_array($pins)) continue;
            foreach ($pins as $pp => $p2v) {
                $pp = trim((string)$pp); $p2v = trim((string)$p2v);
                if ($pp !== '' && $p2v !== '' && !isset($pin2rev[$dmd][$p2v])) $pin2rev[$dmd][$p2v] = $pp;
            }
        }
        // Cadangan nama global (PIN -> nama) untuk tap lama yang source_device_id-nya 0
        $global_names = [];
        foreach ($devmap_did as $pins_g) {
            if (!is_array($pins_g)) continue;
            foreach ($pins_g as $pp => $nn) {
                $pp = trim((string)$pp); $nn = trim((string)$nn);
                if ($pp !== '' && $nn !== '' && !isset($global_names[$pp])) $global_names[$pp] = $nn;
            }
        }
        $pin2_sigs = [];
        foreach ($devmap_did as $dmd => $pins) {
            foreach ($pins as $pp => $nn) {
                $p2 = trim((string)($pin2map[$dmd][$pp] ?? ''));
                if ($p2 !== '') $pin2_sigs[$p2][norm_nama_karyawan($nn)] = true;
            }
        }
        // Nama global per PIN2 (lintas mesin). Tap kadang mencatat PIN2, dan PIN2
        // konsisten antar mesin -> nama orang bisa dikenali walau daftar user mesin
        // asal tap sedang gagal dibaca.
        $global_p2name = [];
        foreach ($pin2map as $dmd => $pins) {
            if (!is_array($pins)) continue;
            foreach ($pins as $pp => $p2v) {
                $p2v = trim((string)$p2v); $nn2 = trim((string)($devmap_did[$dmd][$pp] ?? ''));
                if ($p2v !== '' && $nn2 !== '' && !isset($global_p2name[$p2v])) $global_p2name[$p2v] = $nn2;
            }
        }
        // Nama hasil "Tarik Nama" (tersimpan di ms_karyawan_pin) sebagai cadangan
        // saat daftar user mesin sedang tidak terbaca.
        $pin_db_name = [];
        $global_db_name = [];
        $qdbn = $koneksi->query("SELECT id_mesin, pin, nama_mesin FROM ms_karyawan_pin WHERE nama_mesin IS NOT NULL AND nama_mesin <> ''");
        while ($qdbn && ($rdbn = $qdbn->fetch_assoc())) {
            $ddb = (int)$rdbn['id_mesin']; $pdb = trim((string)$rdbn['pin']); $ndb = trim((string)$rdbn['nama_mesin']);
            if ($pdb === '' || $ndb === '') continue;
            $pin_db_name[$ddb][$pdb] = $ndb;
            if (!isset($global_db_name[$pdb])) $global_db_name[$pdb] = $ndb;
        }
        $qrec = $koneksi->query("SELECT TRIM(userid) AS pin, source_device_id, tgl, status, detail_waktu FROM tb_record WHERE tgl IN ('$t0e','$t1e') ORDER BY tgl ASC, detail_waktu ASC");
        while ($qrec && ($rc = $qrec->fetch_assoc())) {
            $rc['pin'] = trim((string)$rc['pin']);
            if ($rc['pin'] === '') continue;
            $rc['did'] = (int)($rc['source_device_id'] ?? 0);
            $recs[] = $rc;
            // PIN yang sama di lebih dari 1 mesin = ambigu (bisa orang berbeda)
            $pin_devs[$rc['pin']][$rc['did']] = true;
        }
        foreach ($recs as $rc) {
            $pin = $rc['pin']; $did = $rc['did'];
            $pin_alt = $pin;
            if (empty($devmap_did[$did][$pin]) && !empty($pin2rev[$did][$pin])) $pin_alt = $pin2rev[$did][$pin];
            $kid = (int)($pinmap[$did][$pin] ?? 0);
            if ($kid <= 0 && $pin_alt !== $pin) $kid = (int)($pinmap[$did][$pin_alt] ?? 0);
            // Cocokkan langsung lewat nama yang tersimpan di mesin (tanpa perlu mapping tersimpan)
            if ($kid <= 0) {
                $nmraw = $devmap_did[$did][$pin_alt] ?? ($devmap_did[$did][$pin] ?? '');
                if ($nmraw === '') $nmraw = $global_names[$pin] ?? ($global_names[$pin_alt] ?? '');
                if ($nmraw === '') $nmraw = $global_p2name[$pin] ?? ($global_p2name[$pin_alt] ?? '');
                $nmdev = norm_nama_karyawan($nmraw);
                if ($nmdev !== '' && isset($by_name[$nmdev]) && !isset($dup_name[$nmdev])) $kid = (int)$by_name[$nmdev];
            }
            if ($kid <= 0) {
                $p2g = trim((string)($global_p2name[$pin] ?? ($global_p2name[$pin_alt] ?? '')));
                $nn3 = norm_nama_karyawan($p2g);
                if ($nn3 !== '' && isset($by_name[$nn3]) && !isset($dup_name[$nn3])) $kid = (int)$by_name[$nn3];
            }
            $p2 = trim((string)($pin2map[$did][$pin_alt] ?? ($pin2map[$did][$pin] ?? '')));
            if ($kid <= 0 && $p2 !== '' && isset($pin_owner[$p2]) && count($pin_owner[$p2]) === 1) {
                $kid = (int)$pin_owner[$p2][0];
            }
            if ($kid <= 0 && isset($pin_owner[$pin]) && count($pin_owner[$pin]) === 1) $kid = (int)$pin_owner[$pin][0];
            if ($kid <= 0 && $pin_alt !== $pin && isset($pin_owner[$pin_alt]) && count($pin_owner[$pin_alt]) === 1) $kid = (int)$pin_owner[$pin_alt][0];
            if ($kid > 0 && isset($master[$kid])) {
                $by_emp[$kid][] = $rc;
            } else {
                $key = $did . '|' . $pin;
                if (!isset($by_pin[$key])) $by_pin[$key] = ['pin' => $pin, 'alt' => $pin_alt, 'did' => $did, 'logs' => []];
                $by_pin[$key]['logs'][] = $rc;
            }
        }

        // Ada tap yang belum dapat nama? Coba segarkan daftar user mesin sekali
        // (maks. tiap 2 menit) supaya nama yang ada di mesin tetap terpakai.
        if (!empty($by_pin)) {
            $need_refresh = false;
            foreach ($by_pin as $g) {
                $dd = (int)($g['did'] ?? 0); $pp = (string)($g['pin'] ?? '');
                $pp_use = (!isset($devmap_did[$dd][$pp]) && !empty($pin2rev[$dd][$pp])) ? $pin2rev[$dd][$pp] : $pp;
                if (!isset($devmap_did[$dd][$pp_use]) && !isset($global_names[$pp_use])) { $need_refresh = true; break; }
            }
            if ($need_refresh) {
                $dbn = '';
                if ($qd = @$koneksi->query("SELECT DATABASE() AS d")) { $rd = $qd->fetch_assoc(); $dbn = (string)($rd['d'] ?? ''); }
                $cf = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'payroll_devusers2_' . md5($dbn) . '.json';
                if (!is_file($cf) || (time() - (int)@filemtime($cf)) > 120) {
                    $fresh = get_device_users_full($koneksi, true);
                    if (!empty($fresh['u'])) {
                        $devmap_did = $fresh['u'];
                        $pin2map = is_array($fresh['p2'] ?? null) ? $fresh['p2'] : $pin2map;
                        $pin2rev = [];
                        foreach ($pin2map as $dmd2 => $pins2) {
                            if (!is_array($pins2)) continue;
                            foreach ($pins2 as $pp3 => $p2v3) {
                                $pp3 = trim((string)$pp3); $p2v3 = trim((string)$p2v3);
                                if ($pp3 !== '' && $p2v3 !== '' && !isset($pin2rev[$dmd2][$p2v3])) $pin2rev[$dmd2][$p2v3] = $pp3;
                            }
                        }
                        $global_names = [];
                        foreach ($devmap_did as $pins_g) {
                            if (!is_array($pins_g)) continue;
                            foreach ($pins_g as $pp2 => $nn2) {
                                $pp2 = trim((string)$pp2); $nn2 = trim((string)$nn2);
                                if ($pp2 !== '' && $nn2 !== '' && !isset($global_names[$pp2])) $global_names[$pp2] = $nn2;
                            }
                        }
                        $global_p2name = [];
                        foreach ($pin2map as $dmd2 => $pins2) {
                            if (!is_array($pins2)) continue;
                            foreach ($pins2 as $pp3 => $p2v3) {
                                $p2v3 = trim((string)$p2v3); $nn3 = trim((string)($devmap_did[$dmd2][$pp3] ?? ''));
                                if ($p2v3 !== '' && $nn3 !== '' && !isset($global_p2name[$p2v3])) $global_p2name[$p2v3] = $nn3;
                            }
                        }
                    }
                }
            }
        }

        if (empty($by_emp) && empty($by_pin)) return ['added' => 0, 'error' => ''];
        if ($gid_tdr <= 0) {
            if (!$koneksi->query("INSERT INTO ms_grup_borongan (nama_grup, keterangan, tgl_updt) VALUES ('TIDAK DALAM RENCANA', 'Otomatis: penampung tap di luar rencana', NOW())")) return ['added' => 0, 'error' => 'Grup TIDAK DALAM RENCANA gagal dibuat.'];
            $gid_tdr = (int)$koneksi->insert_id;
            $gid_in = (string)$gid_tdr;
        }

        // Hitung jam masuk/pulang/istirahat dari kumpulan log (sama untuk semua baris TDR)
        $hitung_tap = function ($logs) use ($t0) {
            $ra_masuk = '00:00:00'; $ra_keluar = '00:00:00'; $ra_ik = '00:00:00'; $ra_im = '00:00:00';
            if (empty($logs)) return [$ra_masuk, $ra_keluar, $ra_ik, $ra_im];
            usort($logs, function ($a, $b) { return strcmp($a['detail_waktu'], $b['detail_waktu']); });
            $masuk_time = '';
            foreach ($logs as $l) {
                if (substr($l['detail_waktu'], 0, 10) == $t0 && (int)$l['status'] === 0) { $masuk_time = substr($l['detail_waktu'], 11, 8); break; }
            }
            if ($masuk_time === '') $masuk_time = substr($logs[0]['detail_waktu'], 11, 8);
            $in_ts = strtotime($t0 . ' ' . $masuk_time);
            $gap10 = 10 / 60;
            foreach ($logs as $l) {
                $ct = substr($l['detail_waktu'], 11, 8);
                $cts = strtotime(substr($l['detail_waktu'], 0, 10) . ' ' . $ct);
                $dh = ($cts - $in_ts) / 3600;
                if ($dh > $gap10 && $dh <= 16) {
                    if ((int)$l['status'] === 1) $ra_keluar = $ct;
                    elseif ((int)$l['status'] === 2 && $ra_ik === '00:00:00') $ra_ik = $ct;
                    elseif ((int)$l['status'] === 3 && $ra_im === '00:00:00') $ra_im = $ct;
                }
            }
            if ($ra_keluar === '00:00:00') {
                for ($ix = count($logs) - 1; $ix >= 0; $ix--) {
                    $ct = substr($logs[$ix]['detail_waktu'], 11, 8);
                    if (substr($logs[$ix]['detail_waktu'], 0, 10) == $t0 && $ct == $masuk_time) continue;
                    $cts = strtotime($logs[$ix]['detail_waktu']);
                    $dh = ($cts - $in_ts) / 3600;
                    if ($dh > $gap10 && $dh <= 16) { $ra_keluar = $ct; break; }
                }
            }
            return [$masuk_time, $ra_keluar, $ra_ik, $ra_im];
        };

        // Karyawan yang sudah ada di rencana realisasi ini (bukan baris TDR).
        // Baris TANPA GRUP tidak dihitung sebagai rencana: orangnya tidak punya
        // grup perencanaan, jadi kalau tap harus masuk TDR.
        $in_plan = [];
        $qi = $koneksi->query("SELECT id_karyawan FROM tb_realisasi_detail WHERE id_realisasi = '$idrealisasi' AND id_karyawan <> 0 AND id_grup > 0 AND id_grup NOT IN ($gid_in)");
        while ($qi && ($ri = $qi->fetch_assoc())) $in_plan[(int)$ri['id_karyawan']] = true;

        // Nama karyawan yang SUDAH ada di rencana (untuk buang baris TDR dobel).
        // Pakai nama master MAUPUN nama manual, supaya baris rencana ber-id_karyawan=0
        // (input manual) juga tidak ikut dibuat di TDR.
        $plan_norm = [];
        $qpn = $koneksi->query("SELECT COALESCE(NULLIF(TRIM(K.nama_karyawan), ''), TRIM(RD.nama_karyawan_manual)) AS nm
                                FROM tb_realisasi_detail RD
                                LEFT JOIN ms_karyawan K ON K.id_karyawan = RD.id_karyawan
                                WHERE RD.id_realisasi = '$idrealisasi' AND RD.id_grup > 0 AND RD.id_grup NOT IN ($gid_in)");
        while ($qpn && ($rpn = $qpn->fetch_assoc())) {
            $np = norm_nama_karyawan($rpn['nm'] ?? '');
            if ($np !== '') $plan_norm[$np] = true;
        }
        foreach ($in_plan as $kidp => $_) {
            if (isset($master[$kidp])) {
                $np = norm_nama_karyawan($master[$kidp]['nama_karyawan'] ?? '');
                if ($np !== '') $plan_norm[$np] = true;
            }
        }
        $plan_names = array_keys($plan_norm);
        // Nama di mesin sering terpotong (maks ~25 karakter) atau beda 1-2 huruf,
        // mis. "MEDI DERIANTO YUNUS SOLL" vs "MEDI DERIANTO YUNUS SOLLE".
        // Anggap orang yang sama bila namanya persis, saling awalan, atau mirip
        // (jarak Levenshtein kecil). Dipakai untuk MENGELUARKAN dari TDR.
        $nama_sama = function ($a, $b) {
            if ($a === '' || $b === '') return false;
            if ($a === $b) return true;
            $min = min(strlen($a), strlen($b));
            if ($min >= 15 && (strpos($a, $b) === 0 || strpos($b, $a) === 0)) return true;
            $maxd = $min >= 15 ? 2 : ($min >= 10 ? 1 : 0);
            if ($maxd > 0 && levenshtein($a, $b) <= $maxd) return true;
            return false;
        };
        $in_plan_name = function ($nm) use ($plan_names, $nama_sama) {
            if ($nm === '') return false;
            foreach ($plan_names as $pn) if ($nama_sama($nm, $pn)) return true;
            return false;
        };

        // Orang yang tap tapi hanya punya baris TANPA GRUP (bukan rencana bergrup):
        // hapus barisnya supaya dibuat ulang di TDR, bukan nongkrong di TANPA GRUP.
        if (!empty($by_emp)) {
            $ids_ng = implode(',', array_map('intval', array_keys($by_emp)));
            $qng = $koneksi->query("SELECT id_realisasi_detail, id_karyawan FROM tb_realisasi_detail WHERE id_realisasi = '$idrealisasi' AND (id_grup IS NULL OR id_grup = 0) AND id_karyawan IN ($ids_ng)");
            $del_ng = [];
            while ($qng && ($rng = $qng->fetch_assoc())) {
                if (!isset($in_plan[(int)$rng['id_karyawan']])) $del_ng[] = (int)$rng['id_realisasi_detail'];
            }
            if (!empty($del_ng)) {
                $in_ng = implode(',', $del_ng);
                $koneksi->query("DELETE FROM tb_realisasi_borongan WHERE id_realisasi_detail IN ($in_ng)");
                $koneksi->query("DELETE FROM tb_realisasi_detail WHERE id_realisasi_detail IN ($in_ng)");
            }
        }

        // REBUILD TOTAL: buang semua baris TDR realisasi ini (termasuk sisa format
        // lama "PIN ...", nama dobel, grup duplikat), lalu isi ulang dari data tap
        // yang ada sekarang. Jadi tidak ada baris nyangkut antar versi.
        if ($gid_tdr > 0) {
            $koneksi->query("DELETE FROM tb_realisasi_detail WHERE id_realisasi = '$idrealisasi' AND id_grup IN ($gid_in)");
        }
        // Buang juga sisa baris PIN dari versi lama yang mungkin nyangkut di grup
        // lain / id_grup kosong, supaya tidak dobel "PIN 13 (Mesin 1)".
        $koneksi->query("DELETE FROM tb_realisasi_detail WHERE id_realisasi = '$idrealisasi'
            AND (nama_karyawan_manual REGEXP '^PIN [0-9]+' OR nama_karyawan_manual LIKE 'User PIN%' OR nama_karyawan_manual LIKE 'TANPA NAMA%')");
        $exist_names = [];

        // Kolom opsional (DB lama tetap jalan)
        static $has_os_col = null, $has_gol_col = null;
        if ($has_os_col === null) {
            $cko = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_os_dhk'");
            $has_os_col = ($cko && $cko->num_rows > 0);
            $ckg = $koneksi->query("SHOW COLUMNS FROM tb_realisasi_detail LIKE 'id_golongan'");
            $has_gol_col = ($ckg && $ckg->num_rows > 0);
        }
        $cols = "id_realisasi, id_rkk_detail, id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department"
            . ($has_os_col ? ", id_os_dhk" : "") . ($has_gol_col ? ", id_golongan" : "")
            . ", r_upah, r_potongan_telat, r_potongan_istirahat_awal, r_potongan_istirahat_telat, r_potongan_lainnya, r_jam_masuk, r_jam_keluar, r_istirahat_masuk, r_istirahat_keluar, r_status, r_update, ra_masuk, ra_keluar, ra_istirahat_masuk, ra_istirahat_keluar, id_jadwal, status_realisasi_detail, hasil_kerja, lembur, tgl_realisasi_detail, r_potongan_pulang, r_potongan_tidak_lengkap, lembur_jam, id_grup, id_pengganti";

        $now_tu = date('Y-m-d H:i:s');
        $idrkk_ref = (int)($dr['id_rkk'] ?? 0);
        $vals = [];

        // 1) Karyawan TERDAFTAR (nama ada di salah satu mesin + ada di master) tapi di luar rencana
        foreach ($by_emp as $kid => $logs) {
            if (isset($in_plan[$kid])) continue;
            $m = $master[$kid];
            $nm = trim((string)($m['nama_karyawan'] ?? ''));
            if ($nm === '' || isset($exist_names[$nm])) continue;
            if ($in_plan_name(norm_nama_karyawan($nm))) continue;
            list($ra_masuk, $ra_keluar, $ra_ik, $ra_im) = $hitung_tap($logs);
            $nm_e = $koneksi->real_escape_string($nm);
            $dep = (int)($m['id_departmen'] ?? 0);
            $sub = (int)($m['id_sub_department'] ?? 0);
            $os = (int)($m['id_os_dhk'] ?? 0);
            $gol = (int)($m['id_golongan'] ?? 0);
            $vals[] = "('$idrealisasi', 0, '$idrkk_ref', 0, '$nm_e', $dep, $sub"
                . ($has_os_col ? ", $os" : "") . ($has_gol_col ? ", $gol" : "")
                . ", 0, 0, 0, 0, 0, '', '', '', '', 'Hadir', '$now_tu', '$ra_masuk', '$ra_keluar', '$ra_im', '$ra_ik', 0, 2, '-', 0, '$t0', 0, 0, 0, '$gid_tdr', NULL)";
            $exist_names[$nm] = true;
        }

        // 2) Tap yang tidak ada di master: tetap ditampilkan memakai NAMA dari mesin.
        //    Tap tanpa nama digabung per PIN (bukan per mesin) supaya PIN yang sama
        //    di beberapa mesin jadi SATU baris dengan gabungan jam tap.
        $pin_rows = [];
        foreach ($by_pin as $g) {
            $pin = $g['pin'];
            $did = (int)($g['did'] ?? 0);
            $pin_use = trim((string)($g['alt'] ?? $pin));
            $disp = trim((string)($devmap_did[$did][$pin] ?? ''));
            if ($disp === '') $disp = trim((string)($devmap_did[$did][$pin_use] ?? ''));
            // Nama placeholder di mesin ("User PINxxx") dianggap tidak punya nama.
            if ($disp !== '' && preg_match('/^User PIN[0-9]+$/i', $disp)) $disp = '';
            if ($disp === '') $disp = trim((string)($global_names[$pin] ?? ''));
            if ($disp === '') $disp = trim((string)($global_names[$pin_use] ?? ''));
            if ($disp === '') $disp = trim((string)($global_p2name[$pin] ?? ($global_p2name[$pin_use] ?? '')));
            if ($disp === '' && isset($pin_db_name[$did])) $disp = trim((string)($pin_db_name[$did][$pin] ?? ($pin_db_name[$did][$pin_use] ?? '')));
            if ($disp === '') $disp = trim((string)($global_db_name[$pin] ?? ($global_db_name[$pin_use] ?? '')));
            if ($disp !== '' && preg_match('/^User PIN[0-9]+$/i', $disp)) $disp = '';
            // Tap tanpa nama di mesin tetap ditampilkan sebagai PIN supaya tidak ada
            // data tap yang hilang.
            if ($disp === '') $disp = 'PIN ' . $pin;
            // Jangan dobel: kalau nama itu sudah ada di rencana realisasi, lewati.
            $disp_norm = norm_nama_karyawan($disp);
            if ($in_plan_name($disp_norm)) continue;
            if ($disp_norm !== '' && isset($by_name[$disp_norm]) && !isset($dup_name[$disp_norm]) && isset($in_plan[(int)$by_name[$disp_norm]])) continue;
            if (isset($exist_names[$disp])) continue;
            $mkey = (strpos($disp, 'PIN ') === 0) ? 'P:' . $pin : 'N:' . $disp_norm;
            if (!isset($pin_rows[$mkey])) $pin_rows[$mkey] = ['disp' => $disp, 'logs' => []];
            foreach ($g['logs'] as $lg) $pin_rows[$mkey]['logs'][] = $lg;
        }
        foreach ($pin_rows as $it) {
            $disp = $it['disp'];
            if (isset($exist_names[$disp])) continue;
            list($ra_masuk, $ra_keluar, $ra_ik, $ra_im) = $hitung_tap($it['logs']);
            $nm_e = $koneksi->real_escape_string($disp);
            $vals[] = "('$idrealisasi', 0, '$idrkk_ref', 0, '$nm_e', 0, 0"
                . ($has_os_col ? ", 0" : "") . ($has_gol_col ? ", 0" : "")
                . ", 0, 0, 0, 0, 0, '', '', '', '', 'Hadir', '$now_tu', '$ra_masuk', '$ra_keluar', '$ra_im', '$ra_ik', 0, 2, '-', 0, '$t0', 0, 0, 0, '$gid_tdr', NULL)";
            $exist_names[$disp] = true;
        }

        if (!empty($vals)) {
            $ok = $koneksi->query("INSERT INTO tb_realisasi_detail ($cols) VALUES " . implode(", ", $vals));
            if ($ok) $added = count($vals);
            else $err = $koneksi->error;
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    return ['added' => $added, 'error' => $err];
}

/**
 * Pastikan kolom protocol ada di tb_mesin (soap = X606S, zkudp = biner UDP mis. X902).
 */
function ensure_protocol_column($koneksi){
    static $done = false;
    if($done) return;
    $done = true;
    try {
        $c = $koneksi->query("SHOW COLUMNS FROM tb_mesin LIKE 'protocol'");
        if($c && $c->num_rows == 0) $koneksi->query("ALTER TABLE tb_mesin ADD COLUMN protocol VARCHAR(10) NOT NULL DEFAULT 'soap'");
    } catch(Throwable $e){}
}

/**
 * Send SOAP request to the device using socket
 */
function send_soap_request($ip, $port, $request, $timeout = 5) {
    $socket = @fsockopen($ip, $port, $errno, $errstr, 2.0);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, max(1, (int)$timeout));
    $newLine = "\r\n";
    // @: x902/tipe lain kadang me-reset koneksi saat POST (errno 10054) — jangan bocorkan notice ke halaman/JSON
    if(@fputs($socket, "POST /iWsService HTTP/1.0" . $newLine)===false){ @fclose($socket); return false; }
    if(@fputs($socket, "Content-Type: text/xml" . $newLine)===false){ @fclose($socket); return false; }
    if(@fputs($socket, "Content-Length: " . strlen($request) . $newLine . $newLine)===false){ @fclose($socket); return false; }
    if(@fputs($socket, $request . $newLine)===false){ @fclose($socket); return false; }
    
    $response = "";
    while (!@feof($socket)) {
        $line = @fgets($socket, 8192);
        if($line===false) break;
        $response .= $line;
        $meta=stream_get_meta_data($socket);
        if($meta['timed_out']) break;
    }
    @fclose($socket);
    if(trim($response)==='') return false;
    return $response;
}

/**
 * Test connection to a fingerprint device
 */
function test_device_connection($ip, $port = 4370) {
    // Cache per proses, TTL 60 detik: sweep manggil berkali-kali untuk IP yang
    // sama (mesin offline = timeout 1.5 dtk tiap panggil tanpa cache).
    static $cache = [];
    $key = $ip . ':' . (int)$port;
    $now = time();
    if (isset($cache[$key]) && ($now - $cache[$key][1]) < 60) return $cache[$key][0];
    $socket = @fsockopen($ip, $port, $errno, $errstr, 1.5);
    $ok = $socket ? true : false;
    if ($socket) fclose($socket);
    $cache[$key] = [$ok, $now];
    return $ok;
}

/**
 * Cek exact apakah PIN ada di respon SOAP (di dalam tag PIN / PIN2 saja).
 * Pengganti strpos longgar ">{$pin}<" yang false-positive untuk PIN kecil:
 * mis. PIN "1" akan match "<Group>1</Group>" milik user lain sehingga
 * status mesin kebaca "ada" padahal tidak ada.
 */
function soap_pin_exists($resp, $pin) {
    if (!$resp) return false;
    $pin = trim((string)$pin);
    if ($pin === '') return false;
    if (preg_match_all('/<PIN2?(\s[^>]*)?>(.*?)<\/PIN2?(\s[^>]*)?>/is', $resp, $m)) {
        foreach ($m[2] as $v) {
            if (trim($v) === $pin) return true;
        }
    }
    return false;
}

/**
 * Get all configured devices
 */
function get_all_devices($koneksi) {
    ensure_protocol_column($koneksi);
    $devices = [];
    $result = $koneksi->query("SELECT * FROM tb_mesin ORDER BY no_mesin");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $devices[] = $row;
        }
    }
    return $devices;
}

/**
 * Sync single karyawan to all devices (SetUserInfo SOAP)
 */
function sync_karyawan_to_devices($koneksi, $karyawan_id) {
    $result = $koneksi->query("SELECT * FROM ms_karyawan WHERE id_karyawan = '$karyawan_id'");
    if (!$result || $result->num_rows == 0) {
        return ['error' => 'Karyawan tidak ditemukan'];
    }
    
    $karyawan = $result->fetch_assoc();
    $devices = get_all_devices($koneksi);
    $results = [];
    
    $no_absen = $karyawan['no_absen'];
    $nama = htmlspecialchars($karyawan['nama_karyawan']);
    
    if (empty($no_absen)) {
        return ['error' => 'Nomor absen tidak dikonfigurasi'];
    }
    
    foreach ($devices as $device) {
        if (empty($device['ip_mesin'])) {
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'skipped',
                'message' => 'IP tidak dikonfigurasi'
            ];
            continue;
        }
        
        if (!test_device_connection($device['ip_mesin'], $device['port'])) {
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'error',
                'message' => 'Device offline atau tidak dapat dihubungi'
            ];
            continue;
        }

        // Protokol biner ZK-UDP (mis. X902): push via UDP, tanpa baca isi mesin
        if(($device['protocol'] ?? 'soap') === 'zkudp'){
            if(!function_exists('zkb_push_user')){
                $results[$device['id_mesin']] = [
                    'device' => $device['nama_mesin'],
                    'status' => 'error',
                    'message' => 'Helper biner (zk_protocol_helper.php) tidak ada di server ini'
                ];
                continue;
            }
            $zr = zkb_push_user($device['ip_mesin'], $device['port'] ?? 4370, intval($device['comm_key'] ?? 0), $no_absen, $karyawan['nama_karyawan']);
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => $zr['success'] ? 'success' : 'error',
                'message' => $zr['success'] ? 'Karyawan berhasil disinkronisasi (biner)' : ('Gagal: '.$zr['message']),
                'pin' => $no_absen,
                'name' => $karyawan['nama_karyawan']
            ];
            continue;
        }
        
        $comm_key = $device['comm_key'] ?? 0;
        
        // SAVE-ONLY: langsung push tanpa baca isi mesin
        
        $safeNama = htmlspecialchars($karyawan['nama_karyawan'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $soap = "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$no_absen}</PIN><Name xsi:type=\"xsd:string\">{$safeNama}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>";
        
        $response = send_soap_request($device['ip_mesin'], $device['port'], $soap);
        $isSuccess = $response && (strpos($response, '<Information>Success') !== false || strpos($response, '<Result>0</Result>') !== false || strpos($response, 'Successfully') !== false || strpos($response, '<SetUserInfoResponse>') !== false);
        if(!$isSuccess && $response && strpos($response, '200 OK')!==false && strpos($response, '<SetUserInfoResponse>')!==false) $isSuccess=true;
        if(!$isSuccess && $response) log_sync_activity($koneksi,'sync_debug',$device['id_mesin'],$karyawan['no_absen']??$no_absen,'debug', substr($response,0,500));
        
        if ($isSuccess) {
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'success',
                'message' => 'Karyawan berhasil disinkronisasi',
                'pin' => $no_absen,
                'name' => $karyawan['nama_karyawan']
            ];
        } else {
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'error',
                'message' => $response ? 'Gagal: '.substr(strip_tags($response),0,80) : 'Gagal mengirim data ke mesin'
            ];
        }
    }
    
    return $results;
}

/**
 * Sync all karyawan to a specific device (batch SetUserInfo)
 */
function sync_all_karyawan_to_device($koneksi, $device_id) {
    set_time_limit(0);
    $result = $koneksi->query("SELECT * FROM tb_mesin WHERE id_mesin = '$device_id'");
    if (!$result || $result->num_rows == 0) {
        return ['device' => 'Unknown', 'status' => 'error', 'message' => 'Device tidak ditemukan'];
    }
    
    $device = $result->fetch_assoc();
    
    $is_online = test_device_connection($device['ip_mesin'], $device['port']);
        if (!$is_online) {
            return [
                'device' => $device['nama_mesin'],
                'device_ip' => $device['ip_mesin'],
                'device_port' => $device['port'],
                'device_status' => 'offline',
                'status' => 'error',
                'message' => 'Device offline atau tidak dapat dihubungi'
            ];
        }

    // Protokol biner ZK-UDP (mis. X902): batch push via UDP
    if(($device['protocol'] ?? 'soap') === 'zkudp'){
        $karyawan_udp = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan = 'Aktif' AND no_absen != '' AND no_absen IS NOT NULL");
        $employees = [];
        if($karyawan_udp){
            while($ku = $karyawan_udp->fetch_assoc()){
                $pin_u = trim($ku['no_absen']);
                if($pin_u === '') continue;
                $employees[] = ['pin'=>$pin_u, 'nama'=>$ku['nama_karyawan']];
            }
        }
        $total_u = count($employees);
        if($total_u === 0){
            return [
                'device' => $device['nama_mesin'],
                'device_name' => $device['nama_mesin'],
                'device_ip' => $device['ip_mesin'],
                'device_port' => $device['port'],
                'device_status' => 'online',
                'status' => 'success',
                'message' => 'Tidak ada karyawan aktif untuk disinkronisasi',
                'total' => 0,
                'synced' => 0,
                'failed' => 0,
                'prevented' => 0
            ];
        }
        if(!function_exists('zkb_batch_push')){
            return [
                'device' => $device['nama_mesin'],
                'device_name' => $device['nama_mesin'],
                'device_ip' => $device['ip_mesin'],
                'device_port' => $device['port'],
                'device_status' => 'error',
                'status' => 'error',
                'message' => 'Helper biner (zk_protocol_helper.php) tidak ada di server ini',
                'total' => $total_u,
                'synced' => 0,
                'failed' => $total_u,
                'prevented' => 0
            ];
        }
        $res = zkb_batch_push($device['ip_mesin'], $device['port'] ?? 4370, intval($device['comm_key'] ?? 0), $employees);
        $synced_u = 0;
        $failed_u = 0;
        foreach(($res['results'] ?? []) as $ru){
            if(!empty($ru['success'])) $synced_u++; else $failed_u++;
        }
        if(empty($res['connected'])){
            return [
                'device' => $device['nama_mesin'],
                'device_name' => $device['nama_mesin'],
                'device_ip' => $device['ip_mesin'],
                'device_port' => $device['port'],
                'device_status' => 'error',
                'status' => 'error',
                'message' => 'Gagal konek UDP: '.($res['message'] ?? 'unknown'),
                'total' => $total_u,
                'synced' => 0,
                'failed' => $total_u,
                'prevented' => 0
            ];
        }
        return [
            'device' => $device['nama_mesin'],
            'device_name' => $device['nama_mesin'],
            'device_ip' => $device['ip_mesin'],
            'device_port' => $device['port'],
            'device_status' => 'online',
            'status' => $failed_u == 0 ? 'success' : ($synced_u > 0 ? 'warning' : 'error'),
            'message' => "Berhasil sinkronisasi $synced_u dari $total_u karyawan" . ($failed_u > 0 ? " ($failed_u gagal)" : ""),
            'total' => $total_u,
            'synced' => $synced_u,
            'failed' => $failed_u,
            'prevented' => 0
        ];
    }

    $comm_key = $device['comm_key'] ?? 0;
    
    $karyawan_result = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan = 'Aktif' AND no_absen != '' AND no_absen IS NOT NULL");
    $total = $karyawan_result->num_rows;
    $synced = 0;
    $failed = 0;
    $skipped = 0;
    $prevented = 0;

    while($karyawan = $karyawan_result->fetch_assoc()){
        $no_absen = trim($karyawan['no_absen']);
        $safeNama = htmlspecialchars($karyawan['nama_karyawan'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $soap = "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$no_absen}</PIN><Name xsi:type=\"xsd:string\">{$safeNama}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>";

        $response = send_soap_request($device['ip_mesin'], $device['port'], $soap);
        $isSuccess = $response && (strpos($response, '<Information>Success') !== false || strpos($response, '<Result>0</Result>') !== false || strpos($response, 'Successfully') !== false || strpos($response, '<SetUserInfoResponse>') !== false);
        // x606s kadang bales kosong <SetUserInfoResponse></SetUserInfoResponse> tapi HTTP 200 = success
        if(!$isSuccess && $response && strpos($response, '200 OK')!==false && strpos($response, '<SetUserInfoResponse>')!==false) $isSuccess=true;
        if (!$isSuccess && $response) log_sync_activity($koneksi,'sync_debug',$device['id_mesin'],$no_absen,'debug', substr($response,0,500));

        if ($isSuccess) {
            $synced++;
        } else {
            $failed++;
        }
    }
    if($skipped>0 && $failed==0 && $synced==0){
        return [
            'device' => $device['nama_mesin'],
            'device_ip' => $device['ip_mesin'],
            'device_port' => $device['port'],
            'device_status' => 'online',
            'status' => 'success',
            'message' => "Semua $total karyawan sudah ada - nama di-update ($total di-update)" . ($prevented > 0 ? " ($prevented prevented duplicate)" : ""),
            'total' => $total,
            'synced' => $synced,
            'failed' => $failed,
            'prevented' => $prevented
        ];
    }
    
    return [
        'device' => $device['nama_mesin'],
        'device_ip' => $device['ip_mesin'],
'device_port' => $device['port'],
        'device_status' => 'online',
        'status' => $failed == 0 ? 'success' : ($synced > 0 ? 'warning' : 'error'),
        'message' => "Berhasil sinkronisasi $synced dari $total karyawan" . ($failed > 0 ? " ($failed gagal)" : "") . ($prevented > 0 ? " ($prevented prevented duplicate)" : ""),
        'total' => $total,
        'synced' => $synced,
        'failed' => $failed,
        'prevented' => $prevented
    ];
}

/**
 * Delete a karyawan from all devices (DeleteUser SOAP)
 * @param $dry_run bool If true, simulate delete without actually deleting (for testing)
 */
/**
 * Cari user di semua mesin berdasarkan NAMA persis (case-insensitive).
 * Dipakai halaman konfirmasi hapus: user pilih PIN mana yang mau dihapus.
 * Return [device_id => ['device'=>..,'ip'=>..,'online'=>bool,'matches'=>[['pin'=>..,'pin2'=>..,'name'=>..]],'message'=>..]]
 */
function find_users_by_name($koneksi, $nama) {
    $nama = trim((string)$nama);
    $out = [];
    foreach (get_all_devices($koneksi) as $device) {
        $did = $device['id_mesin'];
        $entry = ['device' => $device['nama_mesin'] ?? ('Mesin '.$did),
            'ip' => !empty($device['ip_mesin']) ? ($device['ip_mesin'].':'.($device['port'] ?? '4370')) : '-',
            'online' => false, 'matches' => [], 'message' => ''];
        if (empty($device['ip_mesin'])) { $entry['message'] = 'IP tidak dikonfigurasi'; $out[$did] = $entry; continue; }
        if (!test_device_connection($device['ip_mesin'], $device['port'])) { $entry['message'] = 'Device offline'; $out[$did] = $entry; continue; }
        if (($device['protocol'] ?? 'soap') === 'zkudp') {
            if (!function_exists('zkb_get_users')) { $entry['message'] = 'Helper biner tidak ada'; $out[$did] = $entry; continue; }
            $zr = zkb_get_users($device['ip_mesin'], $device['port'] ?? 4370, intval($device['comm_key'] ?? 0));
            if (empty($zr['success'])) { $entry['message'] = 'Gagal baca: '.($zr['message'] ?? 'unknown'); $out[$did] = $entry; continue; }
            $entry['online'] = true;
            foreach (($zr['users'] ?? []) as $u) {
                $nm = trim((string)($u['nama'] ?? ''));
                if ($nm !== '' && strcasecmp($nm, $nama) === 0) {
                    $entry['matches'][] = ['pin' => trim((string)($u['pin'] ?? '')), 'pin2' => '', 'name' => $nm];
                }
            }
            $entry['message'] = count($entry['matches']) . ' cocok';
            $out[$did] = $entry;
            continue;
        }
        $comm_key = $device['comm_key'] ?? 0;
        $soap = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></GetAllUserInfo>";
        $resp = send_soap_request($device['ip_mesin'], $device['port'], $soap);
        if (!$resp) { $entry['message'] = 'GetAllUserInfo gagal'; $out[$did] = $entry; continue; }
        $entry['online'] = true;
        $pos = 0;
        while (($start = strpos($resp, '<Row>', $pos)) !== false && ($end = strpos($resp, '</Row>', $start)) !== false) {
            $row = substr($resp, $start + 5, $end - $start - 5);
            $pin = $pin2 = $name = $priv = '';
            if (preg_match('/<PIN>([^<]*)<\/PIN>/', $row, $m)) $pin = trim($m[1]);
            if (preg_match('/<PIN2>([^<]*)<\/PIN2>/', $row, $m)) $pin2 = trim($m[1]);
            if (preg_match('/<Name>([^<]*)<\/Name>/', $row, $m)) $name = trim($m[1]);
            if (preg_match('/<Privilege>([^<]*)<\/Privilege>/', $row, $m)) $priv = trim($m[1]);
            if ($name !== '' && strcasecmp($name, $nama) === 0 && $pin !== '') {
                $entry['matches'][] = ['pin' => $pin, 'pin2' => $pin2, 'name' => $name, 'priv' => $priv];
            }
            $pos = $end + 6;
        }
        $entry['message'] = count($entry['matches']) . ' cocok';
        $out[$did] = $entry;
    }
    return $out;
}

/**
 * Hapus karyawan dari mesin BERDASARKAN NAMA (PIN/absen tidak dipakai).
 * $force = [device_id => [pin, ...]] untuk hapus PIN pilihan user dari halaman konfirmasi.
 * Kalau $force null: hapus semua PIN yang namanya cocok (dengan pengaman PIN-sharing).
 */
function delete_karyawan_from_devices($koneksi, $nama, $dry_run = true, $force = null) {
    $nama = trim((string)$nama);
    if ($nama === '' && empty($force)) {
        return [];
    }

    $devices = get_all_devices($koneksi);
    $results = [];

    if($dry_run){
        $results['_dry_run_mode'] = true;
        $results['_dry_run_info'] = "DRY RUN MODE: No actual deletion will occur. This is a simulation.";
    }

    foreach ($devices as $device) {
        // Force mode: hanya proses device yang dipilih user di konfirmasi
        if(is_array($force) && !array_key_exists($device['id_mesin'], $force)){
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'skipped',
                'message' => 'Tidak dipilih di konfirmasi'
            ];
            continue;
        }
        if (empty($device['ip_mesin'])) {
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'skipped',
                'message' => 'IP tidak dikonfigurasi'
            ];
            continue;
        }

        if (!test_device_connection($device['ip_mesin'], $device['port'])) {
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'error',
                'message' => 'Device offline'
            ];
            continue;
        }

        // Mesin biner (X902): cari PIN by nama, lalu hapus per PIN
        if(($device['protocol'] ?? 'soap') === 'zkudp'){
            if(!function_exists('zkb_remove_user') || !function_exists('zkb_get_users')){
                $results[$device['id_mesin']] = [
                    'device' => $device['nama_mesin'],
                    'status' => 'error',
                    'message' => 'Helper biner (zk_protocol_helper.php) tidak ada di server ini'
                ];
                continue;
            }
            $pins_biner = [];
            if(is_array($force) && array_key_exists($device['id_mesin'], $force)){
                foreach((array)$force[$device['id_mesin']] as $fp){
                    $fp = trim((string)$fp);
                    if($fp !== '' && is_numeric($fp)) $pins_biner[] = $fp;
                }
                $pins_biner = array_values(array_unique($pins_biner));
            } else {
                $zr = zkb_get_users($device['ip_mesin'], $device['port'] ?? 4370, intval($device['comm_key'] ?? 0));
                if(empty($zr['success'])){
                    $results[$device['id_mesin']] = [
                        'device' => $device['nama_mesin'],
                        'status' => 'error',
                        'message' => 'Gagal baca daftar user: '.($zr['message'] ?? 'unknown')
                    ];
                    continue;
                }
                foreach(($zr['users'] ?? []) as $u){
                    if(strcasecmp(trim((string)($u['nama'] ?? '')), $nama) === 0 && trim((string)($u['pin'] ?? '')) !== ''){
                        $pins_biner[] = trim((string)$u['pin']);
                    }
                }
                $pins_biner = array_values(array_unique($pins_biner));
            }
            if(empty($pins_biner)){
                $results[$device['id_mesin']] = [
                    'device' => $device['nama_mesin'],
                    'status' => 'skipped',
                    'message' => "Nama '$nama' tidak ditemukan di mesin"
                ];
                continue;
            }
            $ok_b = 0; $fail_b = 0; $msg_b = [];
            foreach($pins_biner as $pb){
                $zr = zkb_remove_user($device['ip_mesin'], $device['port'] ?? 4370, intval($device['comm_key'] ?? 0), $pb);
                log_sync_activity($koneksi, 'karyawan_delete_biner', $device['id_mesin'], $nama,
                    !empty($zr['success']) ? 'success' : 'error', "Hapus biner '$nama' (PIN $pb): ".$zr['message']);
                if(!empty($zr['success'])) $ok_b++; else { $fail_b++; $msg_b[] = "PIN $pb: ".$zr['message']; }
            }
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => $fail_b == 0 ? 'success' : ($ok_b > 0 ? 'warning' : 'error'),
                'message' => $fail_b == 0
                    ? "Nama '$nama' berhasil dihapus dari mesin (PIN " . implode(',', $pins_biner) . ", biner)"
                    : ('Gagal menghapus: ' . implode(' | ', $msg_b))
            ];
            continue;
        }

        $comm_key = $device['comm_key'] ?? 0;
        $real_pins = [];
        $real_names = [];
        $real_pin2 = []; // PIN2 asli (kunci hapus di sebagian firmware X606S)
        $prevent_notes = [];
        $check_soap = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></GetAllUserInfo>";
        $forced_here = (is_array($force) && array_key_exists($device['id_mesin'], $force));
        if($forced_here){
            // PIN pilihan user dari konfirmasi: validasi + preview, tanpa baca ulang.
            // Elemen bisa ['pin'=>..,'pin2'=>..], "pin:pin2", atau PIN polos.
            foreach((array)$force[$device['id_mesin']] as $fp){
                $pin2f = '';
                if(is_array($fp) && isset($fp['pin'])){ $pin2f = trim((string)($fp['pin2'] ?? '')); $fp = trim((string)$fp['pin']); }
                else { $fp = trim((string)$fp); if(strpos($fp, ':') !== false){ list($fp, $pin2f) = array_map('trim', explode(':', $fp, 2)); } }
                if($fp === '' || !is_numeric($fp)) continue;
                if(!in_array($fp, $real_pins, true)){ $real_pins[] = $fp; $real_names[$fp] = $nama; $real_pin2[$fp] = $pin2f; }
            }
            if($dry_run){
                foreach($real_pins as $fp){
                    $results[$device['id_mesin']]['will_delete'][] = [
                        'pin' => $fp,
                        'name' => $nama,
                        'reason' => 'Dipilih user di konfirmasi'
                    ];
                }
            }
        } else {
            $check_resp = send_soap_request($device['ip_mesin'], $device['port'], $check_soap);
            if(!$check_resp){
                log_sync_activity($koneksi, 'delete_warning', $device['id_mesin'], $nama,
                                 'warning', "GetAllUserInfo gagal (mesin mungkin offline). Tidak bisa verifikasi nama. Skip hapus di mesin ini.");
                continue;
            }
            $rows = [];
            $pos = 0;
            while(($start = strpos($check_resp, '<Row>', $pos)) !== false && ($end = strpos($check_resp, '</Row>', $start)) !== false){
                $rows[] = substr($check_resp, $start + 5, $end - $start - 5);
                $pos = $end + 6;
            }
            $pin_count = [];
            foreach($rows as $row){
                $pin = '';
                if(preg_match('/<PIN>([^<]*)<\/PIN>/', $row, $mPin)) $pin = trim($mPin[1]);
                if($pin !== '') $pin_count[$pin] = ($pin_count[$pin] ?? 0) + 1;
            }
            foreach($rows as $row){
                $pin = $pin2 = $name = '';
                if(preg_match('/<PIN>([^<]*)<\/PIN>/', $row, $mPin)) $pin = trim($mPin[1]);
                if(preg_match('/<PIN2>([^<]*)<\/PIN2>/', $row, $mPin2)) $pin2 = trim($mPin2[1]);
                if(preg_match('/<Name>([^<]*)<\/Name>/', $row, $mName)) $name = trim($mName[1]);
                if($name === '' || strcasecmp(trim($name), $nama) !== 0 || $pin === '') continue;
                // SENGAJA tanpa blokir PIN-sharing: hapus sesuai nama/pilihan (instruksi user).
                if(!in_array($pin, $real_pins, true)){
                    $real_pins[] = $pin;
                    $real_names[$pin] = $name;
                    $real_pin2[$pin] = $pin2;
                }
                if($dry_run){
                    $results[$device['id_mesin']]['will_delete'][] = [
                        'pin' => $pin,
                        'name' => $name,
                        'reason' => 'Nama match - AMAN'
                    ];
                }
            }
        }

        // Jika tidak ada yang cocok / dipilih, jangan hapus asal-asalan
        if(empty($real_pins)){
            if(!empty($prevent_notes)){
                $pmsg = implode(' ', array_unique(array_values($prevent_notes)));
                log_sync_activity($koneksi, 'delete_info', $device['id_mesin'], $nama, 'warning', $pmsg);
                $results[$device['id_mesin']] = [
                    'device' => $device['nama_mesin'],
                    'status' => 'warning',
                    'message' => $pmsg
                ];
                continue;
            }
            if($forced_here){
                $msgEmpty = 'Tidak ada PIN valid yang dipilih untuk mesin ini.';
            } else {
                $msgEmpty = "Nama '$nama' tidak ditemukan di mesin.";
            }
            log_sync_activity($koneksi, 'delete_info', $device['id_mesin'], $nama, 'info', $msgEmpty);
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'skipped',
                'message' => $msgEmpty
            ];
            continue;
        }

        $real_pins = array_unique($real_pins);
        $deleted = false;
        $lastResp = null;
        $failReasons = [];

        // DRY RUN: Skip actual deletion
        if($dry_run){
            if(!empty($real_pins)){
                $results[$device['id_mesin']]['status'] = 'dry_run_success';
                $results[$device['id_mesin']]['message'] = 'DRY RUN: Delete would succeed for ' . count($real_pins) . ' user(s)';
                $results[$device['id_mesin']]['pins_to_delete'] = $real_pins;
            } else {
                $results[$device['id_mesin']]['status'] = 'dry_run_skipped';
                $results[$device['id_mesin']]['message'] = 'DRY RUN: No users would be deleted (prevented or not found)';
            }
            continue;
        }

        // Kunci mesin selama tulis (praktik standar ZK): sebagian firmware menolak write saat device enabled
        $soapDis = "<DisableDevice><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></DisableDevice>";
        send_soap_request($device['ip_mesin'], $device['port'], $soapDis);
        foreach($real_pins as $real_pin){
            if(!is_numeric($real_pin) || $real_pin === ''){
                log_sync_activity($koneksi, 'delete_error', $device['id_mesin'], $nama,
                                 'error', "PIN invalid '$real_pin', skip delete.");
                continue;
            }
            $p2real = trim($real_pin2[$real_pin] ?? '');
            // Urutan varian: PIN2 asli dulu (kalau diketahui) -> polos -> PIN2=PIN (fallback).
            // Sebelum varian ke-2 dst: wipe template (membantu firmware yang mengunci user bersidik).
            $variants = [];
            if($p2real !== '') $variants['by-pin2'] = build_delete_user_soap($comm_key, $real_pin, $p2real);
            $variants['polos'] = build_delete_user_soap($comm_key, $real_pin, '');
            if($p2real === '') $variants['pin2-sama'] = build_delete_user_soap($comm_key, $real_pin, $real_pin);
            // Varian UID: sebagian firmware key user by nomor urut/PIN2, bukan PIN
            // (API biner-nya pun pakai UID). Aman hanya bila nilai itu tidak dipakai user BERBEDA nama.
            if($p2real !== '' && is_numeric($p2real) && $p2real != $real_pin){
                $rowsForCheck = $rows ?? null;
                if($rowsForCheck === null){
                    // force mode: baca sekali untuk safety check
                    $uResp = send_soap_request($device['ip_mesin'], $device['port'], $check_soap);
                    $rowsForCheck = [];
                    if($uResp){
                        $up = 0;
                        while(($us = strpos($uResp, '<Row>', $up)) !== false && ($ue = strpos($uResp, '</Row>', $us)) !== false){
                            $rowsForCheck[] = substr($uResp, $us + 5, $ue - $us - 5);
                            $up = $ue + 6;
                        }
                    }
                }
                if(is_array($rowsForCheck)){
                    $pinNames = [];
                    foreach($rowsForCheck as $rw){
                        $pp = $nn = '';
                        if(preg_match('/<PIN>([^<]*)<\/PIN>/', $rw, $mm)) $pp = trim($mm[1]);
                        if(preg_match('/<Name>([^<]*)<\/Name>/', $rw, $mm)) $nn = trim($mm[1]);
                        if($pp !== '') $pinNames[$pp][] = $nn;
                    }
                    $holders = $pinNames[$p2real] ?? [];
                    $other = false;
                    foreach($holders as $hn){ if(strcasecmp(trim($hn), $nama) !== 0){ $other = true; break; } }
                    if(!$other) $variants['by-uid'] = build_delete_user_soap($comm_key, $p2real, '');
                }
            }
            $okOne = false; $response = false;
            foreach($variants as $vlabel => $vsoap){
                if($vlabel !== array_key_first($variants)){
                    delete_user_templates($device['ip_mesin'], $device['port'], $comm_key, $real_pin);
                }
                $response = send_soap_request($device['ip_mesin'], $device['port'], $vsoap);
                $lastResp = $response;
                log_sync_activity($koneksi, 'delete_attempt', $device['id_mesin'], $nama,
                                $response ? 'sent' : 'no_response',
                                "Hapus '$nama' PIN:$real_pin [varian $vlabel]" . ($p2real !== '' ? " PIN2:$p2real" : "") . " - Response: " . ($response ? substr(strip_tags($response),0,200) : 'null'));
                if(!$response) continue;
                $failHint = (stripos($response,'Fail')!==false || stripos($response,'Error')!==false
                    || preg_match('/<Result>\s*-?[1-9]\d*\s*</', $response));
                $okOne = !$failHint && (stripos($response,'Success')!==false || stripos($response,'Successfully')!==false
                    || stripos($response,'<DeleteUserResponse>')!==false
                    || (strpos($response,'200 OK')!==false && stripos($response,'DeleteUser')!==false));
                if($okOne) break;
            }
            if ($okOne){
                $deleted = true;
                $refresh = "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></RefreshData>";
                send_soap_request($device['ip_mesin'], $device['port'], $refresh);
            } elseif($response){
                // Kumpulkan alasan penolakan biar muncul di dialog (bukan cuma "gagal")
                if(preg_match('/<Result>\s*(-?\d+)\s*</', $response, $mR)) $failReasons[] = "PIN $real_pin ditolak mesin (Result {$mR[1]})";
                elseif(stripos($response,'Fail')!==false || stripos($response,'Error')!==false) $failReasons[] = "PIN $real_pin ditolak mesin (" . substr(trim(strip_tags($response)),0,80) . ")";
                else $failReasons[] = "PIN $real_pin: balasan mesin tidak dikenali (" . substr(trim(strip_tags($response)),0,80) . ")";
            } else {
                $failReasons[] = "PIN $real_pin: mesin tidak membalas (koneksi putus/timeout)";
            }
        }

        if ($deleted) {
            // Verifikasi + retry terbatas
            $vst = 'success';
            $vmsg = 'Karyawan berhasil dihapus dari mesin';
            $still = $real_pins;
            $vresp = false;
            for($va = 0; $va < 3 && !empty($still); $va++){
                if($va > 0){
                    foreach($still as $rp2){
                        $soap2 = build_delete_user_soap($comm_key, $rp2, $real_pin2[$rp2] ?? '');
                        $resp2 = send_soap_request($device['ip_mesin'], $device['port'], $soap2);
                        log_sync_activity($koneksi, 'delete_attempt', $device['id_mesin'], $nama, $resp2 ? 'sent' : 'no_response',
                            "Retry delete PIN:$rp2 (percobaan " . ($va+1) . ") - Response: " . ($resp2 ? substr(strip_tags($resp2),0,200) : 'null'));
                    }
                    $refresh = "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></RefreshData>";
                    send_soap_request($device['ip_mesin'], $device['port'], $refresh);
                }
                sleep(2);
                $vresp = send_soap_request($device['ip_mesin'], $device['port'], $check_soap);
                if(!$vresp) break;
                $still = [];
                foreach($real_pins as $rp){
                    $f = function_exists('soap_pin_exists') ? soap_pin_exists($vresp, $rp) : (strpos($vresp,"<PIN>{$rp}</PIN>")!==false);
                    if($f) $still[] = $rp;
                }
            }
            if($vresp && empty($still)){
                $vmsg = 'Karyawan berhasil dihapus dari mesin (PIN ' . implode(',', array_unique($real_pins)) . ', terverifikasi hilang)';
            } elseif($vresp){
                $vst = 'warning';
                $vmsg = 'Delete terkirim 3x tapi PIN ' . implode(',', $still) . ' masih terbaca di mesin — hapus manual di mesin / restart mesin, lalu Tarik ulang biar data sinkron';
            } else {
                $vmsg = 'Karyawan berhasil dihapus dari mesin (baca verifikasi gagal, cek manual di layar mesin)';
            }
            log_sync_activity($koneksi, 'delete_verify', $device['id_mesin'], $nama, $vst, $vmsg);
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => $vst,
                'message' => $vmsg
            ];
        } else {
            // Semua varian ditolak — tapi pastikan dulu via baca ulang (firmware kadang
            // ngerjain tapi balas Result 1, seperti kasus copy template kemarin)
            sleep(2);
            $vresp0 = send_soap_request($device['ip_mesin'], $device['port'], $check_soap);
            $gone = false;
            if($vresp0){
                $gone = true;
                foreach($real_pins as $rp){
                    $f = function_exists('soap_pin_exists') ? soap_pin_exists($vresp0, $rp) : (strpos($vresp0,"<PIN>{$rp}</PIN>")!==false);
                    if($f){ $gone = false; break; }
                }
            }
            if($gone){
                $vmsg0 = 'Karyawan sudah tidak ada di mesin (terverifikasi hilang walau mesin menolak perintah hapus)';
                log_sync_activity($koneksi, 'delete_verify', $device['id_mesin'], $nama, 'success', $vmsg0);
                $results[$device['id_mesin']] = [
                    'device' => $device['nama_mesin'],
                    'status' => 'success',
                    'message' => $vmsg0
                ];
                send_soap_request($device['ip_mesin'], $device['port'], $refresh ?? "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></RefreshData>");
                send_soap_request($device['ip_mesin'], $device['port'], $soapEna ?? "<EnableDevice><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></EnableDevice>");
                continue;
            }
            $detail = !empty($failReasons) ? ' — ' . implode(' | ', array_unique($failReasons)) : ' (mesin tidak membalas)';
            $results[$device['id_mesin']] = [
                'device' => $device['nama_mesin'],
                'status' => 'error',
                'message' => 'Gagal menghapus data dari mesin' . $detail
            ];
        }
        // Buka kunci mesin lagi apapun hasilnya (biar bisa dipakai absen normal)
        $soapEna = "<EnableDevice><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></EnableDevice>";
        send_soap_request($device['ip_mesin'], $device['port'], $soapEna);
    }

    return $results;
}

/**
 * Get sync status - compare web data with device data (GetAllUserInfo SOAP)
 */
function get_sync_status($koneksi, $device_id) {
    $result = $koneksi->query("SELECT * FROM tb_mesin WHERE id_mesin = '$device_id'");
    if (!$result || $result->num_rows == 0) {
        return ['error' => 'Device tidak ditemukan'];
    }
    
    $device = $result->fetch_assoc();
    
    $web_count = $koneksi->query("SELECT COUNT(*) as total FROM ms_karyawan WHERE status_karyawan = 'Aktif' AND no_absen != '' AND no_absen IS NOT NULL");
    $web_total = $web_count->fetch_assoc()['total'];
    
    $device_total = 0;
    $is_online = test_device_connection($device['ip_mesin'], $device['port']);
    
    if ($is_online) {
        $comm_key = $device['comm_key'] ?? 0;
        $soap = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></GetAllUserInfo>";
        $response = send_soap_request($device['ip_mesin'], $device['port'], $soap);
        
        if ($response) {
            $device_total = substr_count($response, '<Row>');
        }
    }
    
    return [
        'device' => $device['nama_mesin'],
        'web_total' => $web_total,
        'device_total' => $device_total,
        'synced' => ($is_online && $web_total == $device_total),
        'difference' => $is_online ? abs($web_total - $device_total) : $web_total
    ];
}

/**
 * Get all registered fingerprint templates (Fingers 0-9) from source device
 */
function get_fingerprint_templates($source_device_ip, $source_port, $comm_key, $pin) {
    $templates = [];
    
    // We scan all 10 possible finger slots (0 to 9)
    for ($finger_id = 0; $finger_id < 10; $finger_id++) {
        $soap = "<GetUserTemplate><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">{$finger_id}</FingerID></Arg></GetUserTemplate>";
        $response = send_soap_request($source_device_ip, $source_port, $soap);
        
        if ($response && strpos($response, '<Template>') !== false) {
            preg_match('/<Template>(.*?)<\/Template>/', $response, $matches);
            $template_data = $matches[1] ?? null;
            
            // Extract Size and Valid if available, otherwise calculate size
            preg_match('/<Size>(.*?)<\/Size>/', $response, $size_match);
            preg_match('/<Valid>(.*?)<\/Valid>/', $response, $valid_match);
            
            if ($template_data) {
                $templates[] = [
                    'finger_id' => $finger_id,
                    'template' => $template_data,
                    'size' => !empty($size_match[1]) ? $size_match[1] : strlen(base64_decode($template_data)),
                    'valid' => !empty($valid_match[1]) ? $valid_match[1] : '1'
                ];
            }
        }
    }
    
    return $templates;
}

/**
 * Bangun SOAP DeleteUser. $pin2 = PIN2 asli bila diketahui, '' = tanpa tag PIN2.
 */
function build_delete_user_soap($comm_key, $pin, $pin2 = '') {
    $pin2 = trim((string)$pin2);
    $p2tag = $pin2 !== '' ? "<PIN2 xsi:type=\"xsd:string\">{$pin2}</PIN2>" : '';
    return "<DeleteUser><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN>{$p2tag}</Arg></DeleteUser>";
}

/**
 * Hapus semua template sidik (jari 0-9) milik 1 PIN — best effort, hasilnya diabaikan.
 * Sebagian firmware X606S menolak DeleteUser (Result 1) selama user masih punya template.
 */
function delete_user_templates($ip, $port, $comm_key, $pin) {
    for($fid = 0; $fid < 10; $fid++){
        $soapT = "<DeleteTemplate><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">{$fid}</FingerID></Arg></DeleteTemplate>";
        send_soap_request($ip, $port, $soapT);
    }
}

/**
 * Kirim RefreshData ke mesin X606S (SOAP) agar data user hasil sync/delete
 * dimuat ulang dari flash dan muncul di layar mesin tanpa cabut kabel / restart.
 * Return ['success'=>bool,'message'=>string]
 */
function refresh_device($ip, $port, $comm_key = 0) {
    if (!test_device_connection($ip, $port)) {
        return ['success' => false, 'message' => 'Mesin offline'];
    }
    $soap = "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey></RefreshData>";
    $resp = send_soap_request($ip, $port, $soap);
    $ok = $resp && (stripos($resp,'Success')!==false || stripos($resp,'Successfully')!==false
        || stripos($resp,'<RefreshDataResponse>')!==false
        || (strpos($resp,'200 OK')!==false && stripos($resp,'RefreshData')!==false));
    return ['success' => (bool)$ok,
        'message' => $ok ? 'Mesin di-refresh, cek layar mesin (Menu -> User)' : 'Refresh gagal / tidak dibalas (' . substr(strip_tags($resp ?? ''),0,80) . ')'];
}

/**
 * Set a single fingerprint template to target device
 */
function set_fingerprint_template($target_device_ip, $target_port, $comm_key, $pin, $finger_id, $template_data, $size = 0, $valid = 1, &$reason = null) {
    if (empty($template_data)) {
        $reason = 'template kosong';
        return false;
    }
    
    if ($size == 0) {
        $size = strlen(base64_decode($template_data));
    }
    
    $soap = "<SetUserTemplate><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><FingerID xsi:type=\"xsd:integer\">{$finger_id}</FingerID><Template xsi:type=\"xsd:string\">{$template_data}</Template><Size xsi:type=\"xsd:integer\">{$size}</Size><Valid xsi:type=\"xsd:integer\">{$valid}</Valid></Arg></SetUserTemplate>";

    $response = send_soap_request($target_device_ip, $target_port, $soap);

    // Deteksi longgar seperti operasi lain (X606S sering balas tag kosong + HTTP 200)
    if(!$response){ $reason = 'tanpa balasan (timeout/koneksi putus)'; return false; }
    if(preg_match('/<Result>\s*(-?\d+)\s*</', $response, $mR) && (int)$mR[1] !== 0){ $reason = "ditolak (Result {$mR[1]})"; return false; }
    if(stripos($response,'Fail')!==false || stripos($response,'Error')!==false){ $reason = 'ditolak (' . substr(trim(strip_tags($response)),0,60) . ')'; return false; }
    $ok = (strpos($response, '<Information>Success') !== false
        || strpos($response, '<SetUserTemplateResponse>') !== false
        || (strpos($response, '200 OK') !== false && stripos($response, 'SetUserTemplate') !== false));
    if(!$ok) $reason = 'balasan tidak dikenali (' . substr(trim(strip_tags($response)),0,60) . ')';
    return $ok;
}

function get_attendance_from_device($device_ip, $device_port, $comm_key, $start_date, $end_date) {
    // SOAP command: GetAttLog with date range
    $soap = "<GetAttLog><ArgComKey xsi:type=\"xsd:integer\">{$comm_key}</ArgComKey><Arg><StartDate xsi:type=\"xsd:string\">{$start_date}</StartDate><EndDate xsi:type=\"xsd:string\">{$end_date}</EndDate></Arg></GetAttLog>";
    
    $response = send_soap_request($device_ip, $device_port, $soap);
    
    $attendance = [];
    if($response && strpos($response, '<Row>') !== false) {
        // Parse XML response
        preg_match_all('/<Row>(.*?)<\/Row>/', $response, $matches);
        
        foreach($matches[1] as $row) {
            // Parse PIN and DateTime
            preg_match('/<PIN>(.*?)<\/PIN>/', $row, $pin_match);
            preg_match('/<DateTime>(.*?)<\/DateTime>/', $row, $datetime_match);
            
            if($pin_match[1] && $datetime_match[1]) {
                $attendance[] = [
                    'pin' => $pin_match[1],
                    'datetime' => $datetime_match[1],
                    'date' => substr($datetime_match[1], 0, 10),
                    'time' => substr($datetime_match[1], 11)
                ];
            }
        }
    }
    
    return $attendance;
}

/**
 * Check if attendance should be saved (device-aware deduplication)
 */
function should_save_attendance($koneksi, $pin, $date, $new_datetime, $device_id) {
    // Get last attendance for this PIN on this date
    $last_query = $koneksi->query("SELECT detail_waktu, source_device_id FROM tb_record 
                                  WHERE userid = '$pin' AND tgl = '$date' 
                                  ORDER BY detail_waktu DESC LIMIT 1");
    
    if($last_query->num_rows == 0) {
        return true; // First tap of the day
    }
    
    $last = $last_query->fetch_assoc();
    $last_time = strtotime($last['detail_waktu']);
    $new_time = strtotime($new_datetime);
    $diff_minutes = ($new_time - $last_time) / 60;
    
    // Rule 1: More than 10 minutes apart -> save
    if($diff_minutes > 10) {
        return true;
    }
    
    // Rule 2: Within 10 minutes (any device) -> ignore (prevent double input)
    return false;
}

/**
 * Pull attendance from all devices and save with deduplication
 */
function pull_attendance_from_all_devices($koneksi, $start_date, $end_date) {
    set_time_limit(0);
    $devices = get_all_devices($koneksi);
    $results = [
        'total_devices' => count($devices),
        'online_devices' => 0,
        'records_pulled' => 0,
        'records_saved' => 0,
        'duplicates_ignored' => 0,
        'per_device' => []
    ];

    // Preload tap terakhir per (pin|tgl) — SEKALI, malas (lazy): hanya bila ada
    // device online yang benar-benar narik. Menggantikan N query should_save_attendance().
    // Aturan 10 menit SAMA PERSIS (banding vs terakhir, lintas device & lintas batch).
    $lastmap = [];
    $lastmap_ready = false;
    $sd = $koneksi->real_escape_string($start_date);
    $ed = $koneksi->real_escape_string($end_date);

    foreach($devices as $device) {
        $device_results = [
            'device' => $device['nama_mesin'],
            'online' => false,
            'records_pulled' => 0,
            'records_saved' => 0,
            'duplicates_ignored' => 0,
            'error' => null
        ];
        
        if(empty($device['ip_mesin'])) {
            $device_results['error'] = 'IP not configured';
            $results['per_device'][$device['id_mesin']] = $device_results;
            continue;
        }
        
        // Test connection
        $is_online = test_device_connection($device['ip_mesin'], $device['port']);
        $device_results['online'] = $is_online;
        
        if(!$is_online) {
            $device_results['error'] = 'Device offline';
            $results['per_device'][$device['id_mesin']] = $device_results;
            continue;
        }
        
        $results['online_devices']++;
        
        try {
            // Get attendance from device
            $attendance = get_attendance_from_device(
                $device['ip_mesin'],
                $device['port'],
                $device['comm_key'] ?? 0,
                $start_date,
                $end_date
            );
            
            $device_results['records_pulled'] = count($attendance);
            $results['records_pulled'] += count($attendance);

            // Susulkan preload saat device online pertama membutuhkannya
            if (!$lastmap_ready) {
                $lastmap_ready = true;
                $qex = $koneksi->query("SELECT userid, tgl, MAX(detail_waktu) AS last_dt FROM tb_record WHERE tgl BETWEEN '$sd' AND '$ed' GROUP BY userid, tgl");
                while ($qex && ($er = $qex->fetch_assoc())) {
                    $lastmap[$er['userid'] . '|' . $er['tgl']] = strtotime($er['last_dt']);
                }
            }

            // Dedup batch di PHP (aturan sama kayak should_save_attendance:
            // tap pertama lolos; berikutnya lolos bila >10 mnt dari tap terakhir
            // pin|tgl yang sama — termasuk yg baru disimpan di batch ini).
            $batch_values = [];
            foreach($attendance as $record) {
                // Ambil hanya tap pada rentang tanggal yang diminta.
                // Sebagian mesin mengabaikan StartDate/EndDate dan mengirim SEMUA log historis.
                if ($record['date'] < $start_date || $record['date'] > $end_date) {
                    $device_results['duplicates_ignored']++;
                    $results['duplicates_ignored']++;
                    continue;
                }
                $pin = $koneksi->real_escape_string($record['pin']);
                $date = $koneksi->real_escape_string($record['date']);
                $datetime = $koneksi->real_escape_string($record['datetime']);

                $mk = $record['pin'] . '|' . $record['date'];
                $new_ts = strtotime($record['datetime']);
                $save = !isset($lastmap[$mk]) || (($new_ts - $lastmap[$mk]) / 60 > 10);
                if ($save) {
                    $batch_values[] = "('', '$pin', '$date', 1, 0, '$datetime', '{$device['id_mesin']}')";
                    $lastmap[$mk] = $new_ts;
                    $device_results['records_saved']++;
                    $results['records_saved']++;
                } else {
                    $device_results['duplicates_ignored']++;
                    $results['duplicates_ignored']++;
                }
            }
            if (!empty($batch_values)) {
                foreach (array_chunk($batch_values, 500) as $chunk) {
                    $ok = $koneksi->query("INSERT INTO tb_record
                                      (data, userid, tgl, verifikasi, status, detail_waktu, source_device_id)
                                      VALUES " . implode(", ", $chunk));
                    if (!$ok) { // chunk gagal: koreksi hitungan agar tidak over-claim
                        $miss = count($chunk);
                        $device_results['records_saved'] -= $miss;
                        $results['records_saved'] -= $miss;
                    }
                }
            }
            
        } catch (Exception $e) {
            $device_results['error'] = 'Error: ' . $e->getMessage();
        }
        
        $results['per_device'][$device['id_mesin']] = $device_results;
    }

    // Catat tanggal yg berhasil ditarik (ada mesin online) untuk skip-if-fresh
    if ($results['online_devices'] > 0) pull_cache_mark($koneksi, $start_date, $end_date, $results['records_saved']);

    return $results;
}

/**
 * Copy fingerprint template from source device to target devices
 */
function copy_fingerprint_template($koneksi, $no_absen, $source_device_id, $target_device_ids) {
    $no_absen = trim((string)$no_absen);
    if($no_absen === '') return ['error' => 'PIN kosong', 'results' => []];

    // Get source device info
    $source_result = $koneksi->query("SELECT * FROM tb_mesin WHERE id_mesin = '" . $koneksi->real_escape_string($source_device_id) . "'");
    if(!$source_result || $source_result->num_rows == 0) {
        return ['error' => 'Source device not found', 'results' => []];
    }
    $source_device = $source_result->fetch_assoc();
    if(($source_device['protocol'] ?? 'soap') === 'zkudp') {
        return ['error' => 'Mesin sumber biner tidak support baca template', 'results' => []];
    }

    // Test source device connection
    if(!test_device_connection($source_device['ip_mesin'], $source_device['port'])) {
        return ['error' => 'Source device offline', 'results' => []];
    }

    // Get templates from source device (plural: semua jari 0-9)
    $templates = get_fingerprint_templates(
        $source_device['ip_mesin'],
        $source_device['port'],
        $source_device['comm_key'] ?? 0,
        $no_absen
    );

    if(empty($templates)) {
        return ['error' => 'No fingerprint template found on source device', 'results' => []];
    }

    // Nama untuk pastikan akun user ada di target (template butuh user exists)
    $nm = '';
    $qr = $koneksi->query("SELECT nama_karyawan FROM ms_karyawan WHERE no_absen='" . $koneksi->real_escape_string($no_absen) . "' LIMIT 1");
    if($qr && ($rw = $qr->fetch_assoc())) $nm = $rw['nama_karyawan'];
    if($nm === '') $nm = 'User ' . $no_absen;

    $results = [];
    // Copy to each target device
    foreach((array)$target_device_ids as $target_device_id) {
        // Get target device info
        $target_result = $koneksi->query("SELECT * FROM tb_mesin WHERE id_mesin = '" . $koneksi->real_escape_string($target_device_id) . "'");
        if(!$target_result || $target_result->num_rows == 0) {
            $results[$target_device_id] = [
                'status' => 'error',
                'message' => 'Target device not found'
            ];
            continue;
        }

        $target_device = $target_result->fetch_assoc();
        if(($target_device['protocol'] ?? 'soap') === 'zkudp'){
            $results[$target_device_id] = [
                'device' => $target_device['nama_mesin'],
                'status' => 'error',
                'message' => 'Target biner tidak support tulis template'
            ];
            continue;
        }

        // Test target device connection
        if(!test_device_connection($target_device['ip_mesin'], $target_device['port'])) {
            $results[$target_device_id] = [
                'device' => $target_device['nama_mesin'],
                'status' => 'error',
                'message' => 'Target device offline'
            ];
            continue;
        }

        $tkey = $target_device['comm_key'] ?? 0;
        // Kunci mesin selama tulis
        send_soap_request($target_device['ip_mesin'], $target_device['port'], "<DisableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></DisableDevice>");
        // Pastikan akun user ada di target
        $safeNm = htmlspecialchars($nm, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        send_soap_request($target_device['ip_mesin'], $target_device['port'], "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$no_absen}</PIN><Name xsi:type=\"xsd:string\">{$safeNm}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>");

        // Kirim tiap jari satu per satu
        $ok = 0; $failMsg = [];
        foreach($templates as $t){
            $rsn = '';
            $good = set_fingerprint_template(
                $target_device['ip_mesin'],
                $target_device['port'],
                $tkey,
                $no_absen,
                (int)$t['finger_id'],
                $t['template'],
                (int)($t['size'] ?? 0),
                (int)($t['valid'] ?? 1),
                $rsn
            );
            if($good) $ok++; else $failMsg[] = 'jari ' . $t['finger_id'] . ($rsn !== '' ? " ($rsn)" : '');
        }
        // Refresh + verifikasi baca balik di target
        send_soap_request($target_device['ip_mesin'], $target_device['port'], "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></RefreshData>");
        send_soap_request($target_device['ip_mesin'], $target_device['port'], "<EnableDevice><ArgComKey xsi:type=\"xsd:integer\">{$tkey}</ArgComKey></EnableDevice>");
        $cek = get_fingerprint_templates($target_device['ip_mesin'], $target_device['port'], $tkey, $no_absen);
        $cekIds = [];
        foreach(($cek ?: []) as $c) $cekIds[] = (int)$c['finger_id'];
        $needIds = [];
        foreach($templates as $t) $needIds[] = (int)$t['finger_id'];
        $missing = array_diff($needIds, $cekIds);
        // Patokan = baca ulang, BUKAN balasan kirim (firmware kadang ngerjain tapi balas Result 1)
        if(empty($missing)){
            $results[$target_device_id] = [
                'device' => $target_device['nama_mesin'],
                'status' => 'success',
                'message' => "Copy " . count($needIds) . " jari terverifikasi di mesin" . ($ok == 0 ? " (mesin menolak saat kirim tapi template sudah ada)" : "")
            ];
        } elseif($ok > 0){
            $results[$target_device_id] = [
                'device' => $target_device['nama_mesin'],
                'status' => 'warning',
                'message' => "Terkirim $ok jari" . ($failMsg ? ", gagal: " . implode(',', $failMsg) : "") . "; belum terverifikasi: jari " . implode(',', $missing)
            ];
        } else {
            $results[$target_device_id] = [
                'device' => $target_device['nama_mesin'],
                'status' => 'error',
                'message' => 'Gagal kirim template' . ($failMsg ? ' (' . implode(',', $failMsg) . ')' : '') . '. Cek Comm Key / koneksi.'
            ];
        }
        log_sync_activity($koneksi, 'template_copy', $target_device_id, null, $results[$target_device_id]['status'], "Copy PIN $no_absen (" . count($templates) . " jari): " . $results[$target_device_id]['message']);
    }

    return [
        'source_device' => $source_device['nama_mesin'],
        'template_found' => count($templates),
        'results' => $results
    ];
}

/**
 * Log sync activity for audit trail
 */
function log_sync_activity($koneksi, $type, $device_id, $karyawan_id, $status, $message) {
    $type_esc = mysqli_real_escape_string($koneksi, $type);
    $device_id_esc = mysqli_real_escape_string($koneksi, $device_id);
    // karyawan_id INT: paksa NULL kalau diisi nama/teks (sejak delete by-nama)
    $karyawan_id_esc = (is_numeric($karyawan_id) && $karyawan_id !== '') ? "'" . mysqli_real_escape_string($koneksi, $karyawan_id) . "'" : "NULL";
    $status_esc = mysqli_real_escape_string($koneksi, $status);
    $message_esc = mysqli_real_escape_string($koneksi, $message);
    
    $koneksi->query("INSERT INTO tb_sync_log (type, device_id, karyawan_id, status, message, timestamp) 
                     VALUES ('$type_esc', '$device_id_esc', $karyawan_id_esc, '$status_esc', '$message_esc', NOW())");
}

// Create sync log table if not exists
function create_sync_log_table($koneksi) {
    $koneksi->query("CREATE TABLE IF NOT EXISTS tb_sync_log (
        id_log INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        device_id INT NOT NULL,
        karyawan_id INT,
        status VARCHAR(50) NOT NULL,
        message TEXT,
        timestamp DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    // Fix old table if status column too short - ignore error if already correct or strict mode
    try { $koneksi->query("ALTER TABLE tb_sync_log MODIFY status VARCHAR(50) NOT NULL"); } catch (Throwable $e) {}
}

if (!function_exists('hapus_karyawan_dari_grup')) {
// Bersihkan keanggotaan master grup saat karyawan dihapus (cegah baris yatim NULL di halaman grup).
// Aman di DB lama tanpa tabel grup (di-skip). Return jumlah baris anggota yang dibersihkan.
function hapus_karyawan_dari_grup($koneksi, $id_karyawan) {
    $id_karyawan = (int)$id_karyawan;
    if ($id_karyawan <= 0) return 0;
    try {
        $cek = $koneksi->query("SHOW TABLES LIKE 'ms_grup_borongan_anggota'");
        if (!$cek || $cek->num_rows == 0) return 0;
        $koneksi->query("DELETE FROM ms_grup_borongan_anggota WHERE id_karyawan = '$id_karyawan'");
        return (int)$koneksi->affected_rows;
    } catch (Throwable $e) { return 0; }
}
}

if (!function_exists('get_device_user_names')) {
// Peta PIN => Nama yang tersimpan di mesin (semua mesin online, pertama menang).
// Dipakai untuk menampilkan nama PIN yang belum terdaftar di master karyawan.
function get_device_user_names($koneksi) {
    $map = [];
    if (!function_exists('get_device_users_map')) return $map;
    foreach (get_device_users_map($koneksi) as $pins) {
        if (!is_array($pins)) continue;
        foreach ($pins as $pin => $nm) {
            $pin = trim((string)$pin); $nm = trim((string)$nm);
            if ($pin !== '' && $nm !== '' && !isset($map[$pin])) $map[$pin] = $nm;
        }
    }
    return $map;
}
}

?>
