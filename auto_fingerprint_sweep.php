<?php
/**
 * auto_fingerprint_sweep.php — jalan via CLI / Task Scheduler tiap beberapa jam.
 * Tiap putaran: baca SEMUA template dari 1 mesin master -> bandingkan dgn tb_fingerprint
 * -> kalau ada jari BARU: simpan + sebar otomatis ke mesin SOAP lain yang online.
 *
 * Alur enroll manual TETAP seperti biasa (di mesin / via halaman Enroll).
 * Script ini yang berkala mengecek "apakah ada sidik baru".
 *
 * Pakai: php auto_fingerprint_sweep.php [--master=ID|auto] [--dry-run] [--limit=N] [--force]
 *   --master  : id_mesin sumber (default: ikut pengaturan web)
 *   --dry-run : hanya lapor, tidak simpan/sebar, tidak update jadwal
 *   --limit   : batasi jumlah karyawan (buat test)
 *   --force   : abaikan jadwal web (jalan sekarang juga)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
set_time_limit(0);
$t0 = microtime(true);
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/fingerprint_db_helper.php';

$opt = ['master' => null, 'dry' => false, 'limit' => 0, 'force' => false];
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--master=') === 0) $opt['master'] = substr($a, 9);
    elseif ($a === '--dry-run') $opt['dry'] = true;
    elseif ($a === '--force') $opt['force'] = true;
    elseif (strpos($a, '--limit=') === 0) $opt['limit'] = (int)substr($a, 8);
}

// Jadwal dari web (diabaikan bila --force / --dry-run)
$cfg = sweep_get_config($koneksi);
if ($opt['master'] === null) $opt['master'] = $cfg['master_id'];
if (!$opt['force'] && !$opt['dry']) {
    if (!$cfg['enabled']) { echo "Auto-sweep nonaktif (aktifkan di halaman Backup Sidik Jari).\n"; exit(0); }
    if (!empty($cfg['last_run'])) {
        $next = strtotime($cfg['last_run']) + $cfg['interval_jam'] * 3600;
        if (time() < $next) { echo "Belum waktunya (jalan lagi " . date('Y-m-d H:i:s', $next) . ").\n"; exit(0); }
    }
}

function slog($koneksi, $msg) {
    $line = date('Y-m-d H:i:s') . ' ' . $msg;
    echo $line . PHP_EOL;
}

fp_ensure_table($koneksi);
if (function_exists('create_sync_log_table')) create_sync_log_table($koneksi);

// Tentukan master
$devices = get_all_devices($koneksi);
$master = null;
if ($opt['master'] !== 'auto') {
    foreach ($devices as $d) if ((string)$d['id_mesin'] === (string)$opt['master']) $master = $d;
    if (!$master) { echo "Mesin id {$opt['master']} tidak ketemu\n"; exit(2); }
} else {
    foreach ($devices as $d) {
        if (empty($d['ip_mesin']) || ($d['protocol'] ?? 'soap') !== 'soap') continue;
        if (test_device_connection($d['ip_mesin'], $d['port'])) { $master = $d; break; }
    }
    if (!$master) { echo "Tidak ada mesin SOAP online untuk jadi master\n"; exit(2); }
}
if (!test_device_connection($master['ip_mesin'], $master['port'])) { echo "Master {$master['nama_mesin']} offline\n"; exit(2); }
slog($koneksi, "SWEEP mulai. Master: {$master['nama_mesin']} ({$master['ip_mesin']})" . ($opt['dry'] ? ' [DRY-RUN]' : ''));

// Target sebar = mesin SOAP lain yang online
$targets = [];
foreach ($devices as $d) {
    if ((string)$d['id_mesin'] === (string)$master['id_mesin']) continue;
    if (empty($d['ip_mesin']) || ($d['protocol'] ?? 'soap') !== 'soap') continue;
    if (!test_device_connection($d['ip_mesin'], $d['port'])) { slog($koneksi, "  target {$d['nama_mesin']}: offline, skip"); continue; }
    $targets[] = $d;
}
slog($koneksi, 'Target sebar: ' . (empty($targets) ? '(tidak ada)' : implode(', ', array_column($targets, 'nama_mesin'))));

// Karyawan aktif
$karyawan = [];
$qr = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY CAST(no_absen AS UNSIGNED)");
if ($qr) while ($r = $qr->fetch_assoc()) $karyawan[] = $r;
if ($opt['limit'] > 0) $karyawan = array_slice($karyawan, 0, $opt['limit']);

$total = count($karyawan); $n = 0; $baru = 0; $sebar_ok = 0; $sebar_gagal = 0;
foreach ($karyawan as $k) {
    $n++;
    $pin = trim($k['no_absen']);
    $before = array_map(fn($r) => (int)$r['finger_id'], fp_get_from_db($koneksi, $pin, false));
    if (!$opt['dry']) {
        fp_pull_and_save($koneksi, $master['id_mesin'], $pin);
    }
    $after = $opt['dry']
        ? array_map(fn($t) => (int)$t['finger_id'], get_fingerprint_templates($master['ip_mesin'], $master['port'], $master['comm_key'] ?? 0, $pin))
        : array_map(fn($r) => (int)$r['finger_id'], fp_get_from_db($koneksi, $pin, false));
    $newFingers = array_values(array_diff($after, $before));
    if (!empty($newFingers)) {
        $baru++;
        slog($koneksi, "[$n/$total] PIN $pin ({$k['nama_karyawan']}): jari BARU " . implode(',', $newFingers) . ' -> sebar ke ' . count($targets) . ' mesin');
        if (!$opt['dry']) {
            foreach ($targets as $t) {
                $rs = fp_restore_to_device($koneksi, $t['id_mesin'], $pin);
                if (!empty($rs['success'])) $sebar_ok++; else $sebar_gagal++;
                slog($koneksi, "    -> {$t['nama_mesin']}: {$rs['message']}");
            }
            log_sync_activity($koneksi, 'auto_sweep', $master['id_mesin'], null, 'success', "PIN $pin jari baru " . implode(',', $newFingers) . ' disebar');
        }
    } elseif ($n % 25 === 0) {
        slog($koneksi, "[$n/$total] ...");
    }
}
$dt = round(microtime(true) - $t0);
slog($koneksi, "SWEEP selesai: $total karyawan, $baru ada jari baru, sebar ok=$sebar_ok gagal=$sebar_gagal, {$dt} detik" . ($opt['dry'] ? ' [DRY-RUN]' : ''));
log_sync_activity($koneksi, 'auto_sweep', $master['id_mesin'], null, 'success', "Sweep: $total karyawan, $baru baru, ok=$sebar_ok gagal=$sebar_gagal, {$dt}s");
if (!$opt['dry']) sweep_touch_run($koneksi);
