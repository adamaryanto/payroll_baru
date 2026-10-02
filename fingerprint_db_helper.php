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
    $ok = 0; $fail = 0;
    // set_fingerprint_template() sudah ada di device_sync_helper.php
    foreach ($rows as $r) {
        $good = set_fingerprint_template($dev['ip_mesin'], $dev['port'], $dev['comm_key'] ?? 0,
            $pin, (int)$r['finger_id'], $r['template'], (int)$r['size'], (int)$r['valid']);
        if ($good) $ok++; else $fail++;
    }
    if (function_exists('log_sync_activity')) {
        log_sync_activity($koneksi, 'fp_restore', $dev['id_mesin'], null,
            $fail == 0 ? 'success' : 'warning', "Restore PIN $pin: $ok ok, $fail gagal");
    }
    return ['success' => $fail == 0, 'message' => "Restore PIN $pin ke " . $dev['nama_mesin'] . ": $ok berhasil, $fail gagal", 'ok' => $ok, 'fail' => $fail];
}
