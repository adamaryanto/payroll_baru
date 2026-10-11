<?php
/**
 * Fingerprint DB Helper — backup/restore template sidik jari X606S (SOAP) ke MySQL.
 *
 * Tabel real: tb_fingerprint (beda dari tb_fingerprint_dummy yang isinya data tes).
 * Alur:
 *   Tarik  : GetUserTemplate (FingerID 0-9) dari mesin -> simpan ke tb_fingerprint
 *   Restore: baca tb_fingerprint -> SetUserTemplate per jari ke mesin tujuan
 */
include_once __DIR__ . '/device_sync_helper.php';

/**
 * Konfigurasi jadwal auto-sweep (diatur dari web, dibaca script CLI).
 * Tabel: tb_sweep_config (1 baris, id=1).
 */
function sweep_ensure_config($koneksi) {
    $koneksi->query("CREATE TABLE IF NOT EXISTS tb_sweep_config (
        id INT PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        interval_jam INT NOT NULL DEFAULT 4,
        master_id VARCHAR(20) NOT NULL DEFAULT 'auto',
        last_run DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $koneksi->query("INSERT IGNORE INTO tb_sweep_config (id, enabled, interval_jam, master_id, last_run) VALUES (1, 0, 4, 'auto', NULL)");
}

function sweep_get_config($koneksi) {
    sweep_ensure_config($koneksi);
    $r = $koneksi->query("SELECT enabled, interval_jam, master_id, last_run FROM tb_sweep_config WHERE id=1 LIMIT 1");
    $c = $r ? $r->fetch_assoc() : null;
    $iv = (int)($c['interval_jam'] ?? 4);
    if ($iv < -1 || $iv > 168) $iv = 4;
    return [
        'enabled' => !empty($c['enabled']),
        'interval_jam' => $iv,
        'master_id' => ($c['master_id'] ?? 'auto') === '' ? 'auto' : $c['master_id'],
        'last_run' => $c['last_run'] ?? null,
    ];
}

/**
 * Konversi interval_jam ke detik.
 * Nilai -1 = testing 1 menit, 0 = testing 5 menit. Nilai >=1 = jam.
 */
function sweep_interval_seconds($interval_jam) {
    $interval_jam = (int)$interval_jam;
    if ($interval_jam === -1) return 60; // 1 menit (testing)
    if ($interval_jam <= 0) return 300; // 5 menit (testing)
    return $interval_jam * 3600;
}

function sweep_save_config($koneksi, $enabled, $interval_jam, $master_id) {
    sweep_ensure_config($koneksi);
    $interval_jam = (int)$interval_jam;
    if ($interval_jam < -1) $interval_jam = -1;
    if ($interval_jam > 168) $interval_jam = 168;
    $master_id = trim((string)$master_id) === '' ? 'auto' : trim((string)$master_id);
    $sql = "UPDATE tb_sweep_config SET enabled=" . ($enabled ? 1 : 0) . ", interval_jam=$interval_jam, master_id='" . $koneksi->real_escape_string($master_id) . "' WHERE id=1";
    return $koneksi->query($sql);
}

function sweep_touch_run($koneksi) {
    sweep_ensure_config($koneksi);
    // Tulis pakai jam PHP (date), JANGAN NOW() MySQL: zona waktu MySQL dan PHP
    // bisa beda di XAMPP (pernah selisih ~5 jam) sehingga countdown ngaco,
    // karena semua pembaca last_run (strtotime/time) pakai jam PHP.
    $now = date('Y-m-d H:i:s');
    return $koneksi->query("UPDATE tb_sweep_config SET last_run='$now' WHERE id=1");
}

function sweep_lock_path() { return __DIR__ . '/sweep.lock'; }

// Cooldown antar run OTOMATIS (bukan manual): cegah run bertubi-tubi
// (refresh berulang / 2 tab) saat last_run belum ke-update (misal run gagal).
// Run manual / tombol selalu boleh (niat eksplisit user).
function sweep_cooldown_ok($secs = 45) {
    $f = sweep_lock_path();
    if (!is_file($f)) return true;
    return (time() - (int)@filemtime($f)) >= $secs;
}

/**
 * Sebar ulang SEMUA backup di tb_fingerprint ke semua mesin SOAP online.
 * Dipakai kalau DB sudah punya template tapi ada mesin yang belum punya
 * (sweep biasa hanya sebar jari BARU, tidak perbaiki mesin yang ketinggalan).
 * $cb($line) dipanggil tiap baris progres.
 * Return: ['ran'=>bool,'lines'=>array,'ok'=>int,'fail'=>int]
 */
function spread_all_from_db($koneksi, $cb = null) {
    $lines = [];
    $log = function($msg) use (&$lines, $cb) {
        $line = date('Y-m-d H:i:s') . ' ' . $msg;
        $lines[] = $line;
        if (is_callable($cb)) $cb($line);
    };
    fp_ensure_table($koneksi);
    $karyawan = [];
    $qr = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY CAST(no_absen AS UNSIGNED)");
    if ($qr) while ($r = $qr->fetch_assoc()) $karyawan[] = $r;
    $targets = [];
    foreach (get_all_devices($koneksi) as $d) {
        if (empty($d['ip_mesin']) || ($d['protocol'] ?? 'soap') !== 'soap') continue;
        if (!test_device_connection($d['ip_mesin'], $d['port'])) { $log("  {$d['nama_mesin']}: offline, skip"); continue; }
        $targets[] = $d;
    }
    if (empty($targets)) { $log("Tidak ada mesin SOAP online."); return ['ran'=>false,'lines'=>$lines]; }
    $log('Target sebar: ' . implode(', ', array_column($targets, 'nama_mesin')));
    $ok = 0; $fail = 0; $kosong = 0;
    foreach ($karyawan as $k) {
        $pin = trim($k['no_absen']);
        $rows = fp_get_from_db($koneksi, $pin, false);
        if (empty($rows)) {
            // Tanpa backup di DB pun tetap bisa sebar: cari template di mesin mana
            // pun yang punya, simpan ke DB dulu, baru sebar ke sisanya.
            $found = null; $foundDev = null;
            foreach ($targets as $t) {
                $tpls = get_fingerprint_templates($t['ip_mesin'], $t['port'], $t['comm_key'] ?? 0, $pin);
                if (!empty($tpls)) { $found = $tpls; $foundDev = $t; break; }
            }
            if (empty($found)) { $kosong++; $log("PIN $pin ({$k['nama_karyawan']}): tanpa backup di DB dan tidak ada di mesin mana pun, skip"); continue; }
            fp_save_templates($koneksi, $pin, $found, $foundDev['id_mesin']);
            $log("PIN $pin ({$k['nama_karyawan']}): tanpa backup di DB, ketemu di {$foundDev['nama_mesin']} -> simpan DB + sebar");
            $rows = fp_get_from_db($koneksi, $pin, false);
        } else {
            $log("PIN $pin ({$k['nama_karyawan']}): " . count($rows) . " jari di DB -> sebar ke " . count($targets) . " mesin");
        }
        foreach ($targets as $t) {
            $rs = fp_restore_to_device($koneksi, $t['id_mesin'], $pin);
            if (!empty($rs['success'])) $ok++; else { $fail++; $log("  -> {$t['nama_mesin']}: GAGAL ({$rs['message']})"); }
        }
    }
    $log("SEBAR ULANG selesai: " . count($karyawan) . " karyawan ($kosong tanpa backup), ok=$ok gagal=$fail");
    return ['ran'=>true,'lines'=>$lines,'ok'=>$ok,'fail'=>$fail];
}

/**
 * Inti auto-sweep — dipakai CLI (auto_fingerprint_sweep.php) maupun web
 * (tombol Jalankan Sekarang / otomatis tiap halaman Backup dibuka).
 * $cb($line) dipanggil tiap baris log (buat streaming progres ke browser).
 * Return: ['ran'=>bool,'code'=>int,'lines'=>array,'total'=>int,'baru'=>int,
 *          'sebar_ok'=>int,'sebar_gagal'=>int,'repair_ok'=>int,'repair_fail'=>int,
 *          'mesh_ok'=>int,'mesh_fail'=>int,'seconds'=>int]
 */
function sweep_execute($koneksi, $master_opt = null, $dry = false, $limit = 0, $force = false, $cb = null) {
    @touch(sweep_lock_path()); // catat attempt (buat cooldown run otomatis)
    $lines = [];
    $log = function($msg) use (&$lines, $cb) {
        $line = date('Y-m-d H:i:s') . ' ' . $msg;
        $lines[] = $line;
        if (is_callable($cb)) $cb($line);
    };
    $t0 = microtime(true);

    // Jadwal dari web (diabaikan bila force / dry-run)
    $cfg = sweep_get_config($koneksi);
    if ($master_opt === null || $master_opt === '') $master_opt = $cfg['master_id'];
    if (!$force && !$dry) {
        if (!$cfg['enabled']) { $log("Auto-sweep nonaktif (aktifkan di halaman Backup Sidik Jari)."); return ['ran'=>false,'code'=>0,'lines'=>$lines]; }
        if (!empty($cfg['last_run'])) {
            $next = strtotime($cfg['last_run']) + sweep_interval_seconds($cfg['interval_jam']);
            if (time() < $next) { $log("Belum waktunya (jalan lagi " . date('Y-m-d H:i:s', $next) . ")."); return ['ran'=>false,'code'=>0,'lines'=>$lines]; }
        }
    }

    fp_ensure_table($koneksi);
    if (function_exists('create_sync_log_table')) create_sync_log_table($koneksi);

    // Tentukan master
    $devices = get_all_devices($koneksi);
    $master = null;
    if ($master_opt !== 'auto') {
        foreach ($devices as $d) if ((string)$d['id_mesin'] === (string)$master_opt) $master = $d;
        if (!$master) { $log("Mesin id {$master_opt} tidak ketemu"); return ['ran'=>false,'code'=>2,'lines'=>$lines]; }
    } else {
        foreach ($devices as $d) {
            if (empty($d['ip_mesin']) || ($d['protocol'] ?? 'soap') !== 'soap') continue;
            if (test_device_connection($d['ip_mesin'], $d['port'])) { $master = $d; break; }
        }
        if (!$master) { $log("Tidak ada mesin SOAP online untuk jadi master"); return ['ran'=>false,'code'=>2,'lines'=>$lines]; }
    }
    if (!test_device_connection($master['ip_mesin'], $master['port'])) { $log("Master {$master['nama_mesin']} offline"); return ['ran'=>false,'code'=>2,'lines'=>$lines]; }
    $log("SWEEP mulai. Master: {$master['nama_mesin']} ({$master['ip_mesin']})" . ($dry ? ' [DRY-RUN]' : ''));

    // Target sebar = mesin SOAP lain yang online
    $targets = [];
    foreach ($devices as $d) {
        if ((string)$d['id_mesin'] === (string)$master['id_mesin']) continue;
        if (empty($d['ip_mesin']) || ($d['protocol'] ?? 'soap') !== 'soap') continue;
        if (!test_device_connection($d['ip_mesin'], $d['port'])) { $log("  target {$d['nama_mesin']}: offline, skip"); continue; }
        $targets[] = $d;
    }
    $log('Target sebar: ' . (empty($targets) ? '(tidak ada)' : implode(', ', array_column($targets, 'nama_mesin'))));

    // Karyawan aktif
    $karyawan = [];
    $qr = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY CAST(no_absen AS UNSIGNED)");
    if ($qr) while ($r = $qr->fetch_assoc()) $karyawan[] = $r;
    if ($limit > 0) $karyawan = array_slice($karyawan, 0, $limit);

    $total = count($karyawan); $n = 0; $baru = 0; $sebar_ok = 0; $sebar_gagal = 0;
    $fresh = []; // [id_mesin][pin] yang baru saja disebar sukses run ini -> skip repair
    foreach ($karyawan as $k) {
        $n++;
        $pin = trim($k['no_absen']);
        $before = array_map(fn($r) => (int)$r['finger_id'], fp_get_from_db($koneksi, $pin, false));
        if (!$dry) {
            fp_pull_and_save($koneksi, $master['id_mesin'], $pin);
        }
        $after = $dry
            ? array_map(fn($t) => (int)$t['finger_id'], get_fingerprint_templates($master['ip_mesin'], $master['port'], $master['comm_key'] ?? 0, $pin))
            : array_map(fn($r) => (int)$r['finger_id'], fp_get_from_db($koneksi, $pin, false));
        $newFingers = array_values(array_diff($after, $before));
        if (!empty($newFingers)) {
            $baru++;
            $log("[$n/$total] PIN $pin ({$k['nama_karyawan']}): jari BARU " . implode(',', $newFingers) . ' -> sebar ke ' . count($targets) . ' mesin');
            if (!$dry) {
                foreach ($targets as $t) {
                    $rs = fp_restore_to_device($koneksi, $t['id_mesin'], $pin);
                    if (!empty($rs['success'])) { $sebar_ok++; $fresh[$t['id_mesin']][$pin] = true; } else $sebar_gagal++;
                    $log("    -> {$t['nama_mesin']}: {$rs['message']}");
                }
                log_sync_activity($koneksi, 'auto_sweep', $master['id_mesin'], null, 'success', "PIN $pin jari baru " . implode(',', $newFingers) . ' disebar');
            }
        } elseif ($n % 25 === 0) {
            $log("[$n/$total] ...");
        }
    }

    // Repair pass: DB sudah punya tapi mesin target belum -> sebar susulan otomatis.
    // (Tanpa ini, mesin yang offline saat sebar pertama akan ketinggalan selamanya.)
    $repair_ok = 0; $repair_fail = 0;
    if (!$dry && !empty($targets)) {
        foreach ($karyawan as $k) {
            $pin = trim($k['no_absen']);
            $dbRows = fp_get_from_db($koneksi, $pin, false);
            if (empty($dbRows)) continue;
            $needIds = array_map(fn($r) => (int)$r['finger_id'], $dbRows);
            foreach ($targets as $t) {
                if (!empty($fresh[$t['id_mesin']][$pin])) continue; // baru disebar run ini
                $cur = get_fingerprint_templates($t['ip_mesin'], $t['port'], $t['comm_key'] ?? 0, $pin);
                $curIds = array_map(fn($x) => (int)$x['finger_id'], $cur);
                $missing = array_values(array_diff($needIds, $curIds));
                if (empty($missing)) continue;
                $log("PIN $pin ({$k['nama_karyawan']}): kurang jari " . implode(',', $missing) . " di {$t['nama_mesin']} -> sebar susulan");
                $rs = fp_restore_to_device($koneksi, $t['id_mesin'], $pin);
                if (!empty($rs['success'])) $repair_ok++; else { $repair_fail++; $log("  -> {$t['nama_mesin']}: GAGAL ({$rs['message']})"); }
                log_sync_activity($koneksi, 'auto_sweep_repair', $t['id_mesin'], null, !empty($rs['success']) ? 'success' : 'error', "Repair PIN $pin: {$rs['message']}");
            }
        }
    }
    // Mesh pass: cari sidik yang cuma ada di 1 mesin (di mesin mana pun, bukan
    // cuma master) -> simpan ke DB -> sebar ke mesin lain yang belum punya.
    // Aturan konflik (finger_id sama, template beda di 2 mesin): master didahulukan,
    // yang ketemu duluan yang menang. Hanya untuk PIN karyawan aktif.
    $mesh_ok = 0; $mesh_fail = 0;
    if (!$dry) {
        $scan_order = [];
        foreach ($devices as $d) {
            if (empty($d['ip_mesin']) || ($d['protocol'] ?? 'soap') !== 'soap') continue;
            if (!test_device_connection($d['ip_mesin'], $d['port'])) continue;
            if ((string)$d['id_mesin'] === (string)$master['id_mesin']) array_unshift($scan_order, $d);
            else $scan_order[] = $d;
        }
        foreach ($karyawan as $k) {
            $pin = trim($k['no_absen']);
            $have = []; // finger_id => ['row'=>..., 'src'=>nama_mesin, 'id'=>id_mesin]
            $perDev = []; // id_mesin => [finger_ids]
            foreach ($scan_order as $d) {
                $tpls = get_fingerprint_templates($d['ip_mesin'], $d['port'], $d['comm_key'] ?? 0, $pin);
                $ids = [];
                foreach ($tpls as $t) {
                    $fid = (int)$t['finger_id'];
                    $ids[] = $fid;
                    if (!isset($have[$fid])) $have[$fid] = ['row' => $t, 'src' => $d['nama_mesin'], 'id' => $d['id_mesin']];
                }
                $perDev[$d['id_mesin']] = $ids;
            }
            if (empty($have)) continue;
            $dbIds = array_map(fn($r) => (int)$r['finger_id'], fp_get_from_db($koneksi, $pin, false));
            $newIds = array_values(array_diff(array_keys($have), $dbIds));
            if (!empty($newIds)) {
                $toSave = [];
                foreach ($newIds as $fid) $toSave[] = $have[$fid]['row'];
                fp_save_templates($koneksi, $pin, $toSave, $have[$newIds[0]]['id']);
                $log("PIN $pin ({$k['nama_karyawan']}): ketemu jari " . implode(',', $newIds) . " cuma di mesin (sumber: {$have[$newIds[0]]['src']}) -> simpan DB + sebar");
                log_sync_activity($koneksi, 'auto_sweep_mesh', $have[$newIds[0]]['id'], null, 'success', "PIN $pin mesh: jari baru " . implode(',', $newIds));
            }
            $allIds = array_values(array_unique(array_merge($dbIds, array_keys($have))));
            foreach ($scan_order as $d) {
                $missing = array_values(array_diff($allIds, $perDev[$d['id_mesin']] ?? []));
                if (empty($missing)) continue;
                $rs = fp_restore_to_device($koneksi, $d['id_mesin'], $pin);
                if (!empty($rs['success'])) $mesh_ok++; else { $mesh_fail++; $log("  -> {$d['nama_mesin']}: GAGAL ({$rs['message']})"); }
            }
        }
    }
    $dt = round(microtime(true) - $t0);
    $log("SWEEP selesai: $total karyawan, $baru ada jari baru, sebar ok=$sebar_ok gagal=$sebar_gagal, repair ok=$repair_ok gagal=$repair_fail, mesh ok=$mesh_ok gagal=$mesh_fail, {$dt} detik" . ($dry ? ' [DRY-RUN]' : ''));
    log_sync_activity($koneksi, 'auto_sweep', $master['id_mesin'], null, 'success', "Sweep: $total karyawan, $baru baru, ok=$sebar_ok gagal=$sebar_gagal, repair ok=$repair_ok gagal=$repair_fail, mesh ok=$mesh_ok gagal=$mesh_fail, {$dt}s");
    if (!$dry) sweep_touch_run($koneksi);
    return ['ran'=>true,'code'=>0,'lines'=>$lines,'total'=>$total,'baru'=>$baru,'sebar_ok'=>$sebar_ok,'sebar_gagal'=>$sebar_gagal,'repair_ok'=>$repair_ok,'repair_fail'=>$repair_fail,'mesh_ok'=>$mesh_ok,'mesh_fail'=>$mesh_fail,'seconds'=>$dt];
}

function fp_ensure_table($koneksi) {
    $sql = "CREATE TABLE IF NOT EXISTS tb_fingerprint (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pin VARCHAR(50) NOT NULL,
        finger_id TINYINT NOT NULL DEFAULT 0,
        template LONGTEXT NOT NULL,
        size INT NOT NULL DEFAULT 0,
        valid TINYINT(1) NOT NULL DEFAULT 1,
        id_mesin INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fp (pin, finger_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return $koneksi->query($sql);
}

function fp_save_templates($koneksi, $pin, $templates, $id_mesin = null) {
    fp_ensure_table($koneksi);
    $pin = trim((string)$pin);
    if ($pin === '' || empty($templates)) return 0;
    $saved = 0;
    $mesin_sql = is_null($id_mesin) ? "NULL" : "'" . $koneksi->real_escape_string($id_mesin) . "'";
    foreach ($templates as $t) {
        $fid = (int)($t['finger_id'] ?? 0);
        $tpl = $t['template'] ?? '';
        if ($tpl === '') continue;
        $size = (int)($t['size'] ?? strlen(base64_decode($tpl)));
        $valid = (int)($t['valid'] ?? 1);
        $tpl_esc = $koneksi->real_escape_string($tpl);
        $pin_esc = $koneksi->real_escape_string($pin);
        $ok = $koneksi->query("INSERT INTO tb_fingerprint (pin, finger_id, template, size, valid, id_mesin)
            VALUES ('$pin_esc', $fid, '$tpl_esc', $size, $valid, $mesin_sql)
            ON DUPLICATE KEY UPDATE template=VALUES(template), size=VALUES(size),
            valid=VALUES(valid), id_mesin=VALUES(id_mesin)");
        if ($ok) $saved++;
    }
    return $saved;
}

function fp_get_from_db($koneksi, $pin, $only_valid = true) {
    fp_ensure_table($koneksi);
    $pin_esc = $koneksi->real_escape_string(trim((string)$pin));
    $where = "pin='$pin_esc'";
    if ($only_valid) $where .= " AND valid=1";
    $out = [];
    $res = $koneksi->query("SELECT finger_id, template, size, valid, id_mesin, updated_at
        FROM tb_fingerprint WHERE $where ORDER BY finger_id");
    if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

function fp_summary($koneksi) {
    fp_ensure_table($koneksi);
    $out = [];
    $res = $koneksi->query("SELECT f.pin, COUNT(*) AS jml,
        MAX(f.updated_at) AS terakhir, MAX(f.id_mesin) AS id_mesin,
        (SELECT nama_karyawan FROM ms_karyawan k WHERE k.no_absen=f.pin LIMIT 1) AS nama
        FROM tb_fingerprint f GROUP BY f.pin ORDER BY f.pin");
    if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

function fp_delete_pin($koneksi, $pin) {
    fp_ensure_table($koneksi);
    $pin_esc = $koneksi->real_escape_string(trim((string)$pin));
    return $koneksi->query("DELETE FROM tb_fingerprint WHERE pin='$pin_esc'");
}

/**
 * Tarik template dari mesin X606S (SOAP) lalu simpan ke DB.
 * Return ['success'=>bool,'message'=>string,'saved'=>int,'templates'=>array]
 */
function fp_pull_and_save($koneksi, $device_id, $pin) {
    fp_ensure_table($koneksi);
    $pin = trim((string)$pin);
    if ($pin === '') return ['success' => false, 'message' => 'PIN kosong', 'saved' => 0];
    $res = $koneksi->query("SELECT * FROM tb_mesin WHERE id_mesin='" . $koneksi->real_escape_string($device_id) . "' LIMIT 1");
    if (!$res || $res->num_rows == 0) return ['success' => false, 'message' => 'Mesin tidak ditemukan', 'saved' => 0];
    $dev = $res->fetch_assoc();
    if (empty($dev['ip_mesin'])) return ['success' => false, 'message' => 'IP mesin kosong', 'saved' => 0];
    if (!test_device_connection($dev['ip_mesin'], $dev['port'])) {
        return ['success' => false, 'message' => 'Mesin offline (' . $dev['ip_mesin'] . ':' . $dev['port'] . ')', 'saved' => 0];
    }
    if (($dev['protocol'] ?? 'soap') === 'zkudp') {
        return ['success' => false, 'message' => 'Mesin ini mode biner ZK-UDP (X902), bukan X606S SOAP — template tidak bisa ditarik via GetUserTemplate', 'saved' => 0];
    }
    // get_fingerprint_templates() sudah ada di device_sync_helper.php (SOAP GetUserTemplate 0-9)
    $templates = get_fingerprint_templates($dev['ip_mesin'], $dev['port'], $dev['comm_key'] ?? 0, $pin);
    if (empty($templates)) {
        return ['success' => false, 'message' => "PIN $pin tidak punya template di " . $dev['nama_mesin'] . " (belum enroll?)", 'saved' => 0];
    }
    $saved = fp_save_templates($koneksi, $pin, $templates, $dev['id_mesin']);
    if (function_exists('log_sync_activity')) {
        log_sync_activity($koneksi, 'fp_backup', $dev['id_mesin'], null, 'success', "Backup PIN $pin: $saved jari tersimpan ke DB");
    }
    return ['success' => true, 'message' => "Berhasil simpan $saved jari PIN $pin dari " . $dev['nama_mesin'] . " ke database", 'saved' => $saved, 'templates' => $templates];
}

/**
 * Restore template dari DB ke mesin X606S (SOAP SetUserTemplate per jari).
 * Return ['success'=>bool,'message'=>string,'ok'=>int,'fail'=>int]
 */
function fp_restore_to_device($koneksi, $device_id, $pin) {
    fp_ensure_table($koneksi);
    $pin = trim((string)$pin);
    $res = $koneksi->query("SELECT * FROM tb_mesin WHERE id_mesin='" . $koneksi->real_escape_string($device_id) . "' LIMIT 1");
    if (!$res || $res->num_rows == 0) return ['success' => false, 'message' => 'Mesin tidak ditemukan', 'ok' => 0, 'fail' => 0];
    $dev = $res->fetch_assoc();
    if (empty($dev['ip_mesin'])) return ['success' => false, 'message' => 'IP mesin kosong', 'ok' => 0, 'fail' => 0];
    if (!test_device_connection($dev['ip_mesin'], $dev['port'])) {
        return ['success' => false, 'message' => 'Mesin offline', 'ok' => 0, 'fail' => 0];
    }
    if (($dev['protocol'] ?? 'soap') === 'zkudp') {
        return ['success' => false, 'message' => 'Mesin biner tidak support restore template SOAP', 'ok' => 0, 'fail' => 0];
    }
    $rows = fp_get_from_db($koneksi, $pin, false);
    if (empty($rows)) return ['success' => false, 'message' => "PIN $pin belum ada backup di database", 'ok' => 0, 'fail' => 0];
    // Pastikan akun user ada di target (template butuh user exists), pakai nama dari web
    $nm = '';
    $qr = $koneksi->query("SELECT nama_karyawan FROM ms_karyawan WHERE no_absen='" . $koneksi->real_escape_string($pin) . "' LIMIT 1");
    if ($qr && ($rw = $qr->fetch_assoc())) $nm = $rw['nama_karyawan'];
    if ($nm === '') $nm = 'User ' . $pin;
    $safeNm = htmlspecialchars($nm, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $ck = $dev['comm_key'] ?? 0;
    // Kunci mesin selama tulis (pola sama seperti copy manual yang terbukti jalan)
    send_soap_request($dev['ip_mesin'], $dev['port'], "<DisableDevice><ArgComKey xsi:type=\"xsd:integer\">{$ck}</ArgComKey></DisableDevice>");
    send_soap_request($dev['ip_mesin'], $dev['port'], "<SetUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$ck}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">{$pin}</PIN><Name xsi:type=\"xsd:string\">{$safeNm}</Name><Password xsi:type=\"xsd:string\"></Password><Group xsi:type=\"xsd:integer\">1</Group><Privilege xsi:type=\"xsd:integer\">0</Privilege></Arg></SetUserInfo>");
    $ok = 0; $fail = 0; $failMsg = [];
    // set_fingerprint_template() sudah ada di device_sync_helper.php
    foreach ($rows as $r) {
        $rsn = '';
        $good = set_fingerprint_template($dev['ip_mesin'], $dev['port'], $dev['comm_key'] ?? 0,
            $pin, (int)$r['finger_id'], $r['template'], (int)$r['size'], (int)$r['valid'], $rsn);
        if ($good) $ok++; else { $fail++; $failMsg[] = 'jari ' . $r['finger_id'] . ($rsn !== '' ? " ($rsn)" : ''); }
    }
    send_soap_request($dev['ip_mesin'], $dev['port'], "<RefreshData><ArgComKey xsi:type=\"xsd:integer\">{$ck}</ArgComKey></RefreshData>");
    send_soap_request($dev['ip_mesin'], $dev['port'], "<EnableDevice><ArgComKey xsi:type=\"xsd:integer\">{$ck}</ArgComKey></EnableDevice>");
    // Verifikasi baca balik = patokan kebenaran.
    // (Firmware X606S kadang jawab gagal/timeout padahal template sudah masuk.)
    $cek = get_fingerprint_templates($dev['ip_mesin'], $dev['port'], $ck, $pin);
    $cekIds = [];
    foreach (($cek ?: []) as $c) $cekIds[] = (int)$c['finger_id'];
    $needIds = [];
    foreach ($rows as $r) $needIds[] = (int)$r['finger_id'];
    $missing = array_values(array_diff($needIds, $cekIds));
    if (empty($missing)) {
        $msg = "Restore PIN $pin ke " . $dev['nama_mesin'] . ": " . count($needIds) . " jari terverifikasi di mesin"
            . ($ok == 0 ? " (mesin menolak jawaban kirim tapi template sudah ada)" : "");
        $st = true;
    } elseif ($ok > 0 || count($cekIds) > 0) {
        $msg = "Restore PIN $pin ke " . $dev['nama_mesin'] . ": terkirim $ok jari"
            . ($failMsg ? ", gagal: " . implode(', ', $failMsg) : "")
            . "; belum terverifikasi: jari " . implode(',', $missing);
        $st = false;
    } else {
        $msg = "Restore PIN $pin ke " . $dev['nama_mesin'] . ": gagal kirim template"
            . ($failMsg ? ' (' . implode(', ', $failMsg) . ')' : '') . '. Cek Comm Key / koneksi.';
        $st = false;
    }
    if (function_exists('log_sync_activity')) {
        log_sync_activity($koneksi, 'fp_restore', $dev['id_mesin'], null,
            $st ? 'success' : 'warning', $msg);
    }
    return ['success' => $st, 'message' => $msg, 'ok' => $ok, 'fail' => $fail];
}

/**
 * Enroll + Auto-sebar: tarik template dari mesin sumber, lalu restore ke semua mesin aktif lainnya
 * Return: ['success'=>bool, 'message'=>string, 'pull'=>array, 'sebar'=>array]
 */
function enroll_auto_sebar($koneksi, $source_device_id, $pin) {
    $pin = trim((string)$pin);
    if ($pin === '') return ['success' => false, 'message' => 'PIN kosong'];

    // 1. Tarik dari mesin sumber
    $pull_result = fp_pull_and_save($koneksi, $source_device_id, $pin);
    if (empty($pull_result['success'])) {
        return ['success' => false, 'message' => 'Gagal tarik dari mesin sumber: ' . ($pull_result['message'] ?? 'Unknown error'), 'pull' => $pull_result, 'sebar' => []];
    }

    // 2. Mesin target = semua mesin SOAP online kecuali sumber
    $target_devices = [];
    foreach (get_all_devices($koneksi) as $d) {
        if ((string)$d['id_mesin'] === (string)$source_device_id) continue;
        if (empty($d['ip_mesin']) || ($d['protocol'] ?? 'soap') !== 'soap') continue;
        if (!test_device_connection($d['ip_mesin'], $d['port'])) continue;
        $target_devices[] = $d;
    }

    if (empty($target_devices)) {
        return ['success' => true, 'message' => 'Berhasil tarik dari sumber, tapi tidak ada mesin target lain yang aktif (SOAP)', 'pull' => $pull_result, 'sebar' => []];
    }

    // 3. Sebar ke semua target
    $sebar_results = [];
    $total_ok = 0;
    $total_fail = 0;

    foreach ($target_devices as $dev) {
        $restore_result = fp_restore_to_device($koneksi, $dev['id_mesin'], $pin);
        $sebar_results[] = [
            'device' => $dev['nama_mesin'] . ' (' . $dev['ip_mesin'] . ')',
            'result' => $restore_result
        ];
        if (!empty($restore_result['success'])) {
            $total_ok++;
        } else {
            $total_fail++;
        }
    }

    return [
        'success' => ($total_fail == 0),
        'message' => sprintf(
            'Enroll+Auto-sebar selesai: tarik dari sumber berhasil, sebarkan ke %d mesin (%d berhasil, %d gagal)',
            count($target_devices), $total_ok, $total_fail
        ),
        'pull' => $pull_result,
        'sebar' => $sebar_results
    ];
}
