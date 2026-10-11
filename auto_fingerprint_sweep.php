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
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/fingerprint_db_helper.php';

$opt = ['master' => null, 'dry' => false, 'limit' => 0, 'force' => false];
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--master=') === 0) $opt['master'] = substr($a, 9);
    elseif ($a === '--dry-run') $opt['dry'] = true;
    elseif ($a === '--force') $opt['force'] = true;
    elseif (strpos($a, '--limit=') === 0) $opt['limit'] = (int)substr($a, 8);
}

// Cek jadwal + eksekusi inti (di fingerprint_db_helper.php, dipakai bareng web)
$SWEEP_LOG = __DIR__ . '/sweep.log';
$res = sweep_execute($koneksi, $opt['master'], $opt['dry'], $opt['limit'], $opt['force'], function($line) {
    global $SWEEP_LOG;
    echo $line . PHP_EOL;
    if (!empty($SWEEP_LOG)) @file_put_contents($SWEEP_LOG, $line . PHP_EOL, FILE_APPEND);
});
exit($res['code'] ?? 0);
