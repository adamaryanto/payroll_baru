<?php
// Runner sweep via web — dipakai halaman karyawan (background, tanpa reload).
// Protokol respons (text/plain, di-flush per baris):
//   <baris log> ... __RESULT__{"ran":true,...}
session_start();
set_time_limit(0);
include "../../koneksi.php";
include "../../device_sync_helper.php";
include_once "../../fingerprint_db_helper.php";
header('Content-Type: text/plain; charset=utf-8');
while (ob_get_level() > 0) @ob_end_flush();
@ob_implicit_flush(true);

function swdone($arr) { echo '__RESULT__' . json_encode($arr) . "\n"; @flush(); exit; }

if (empty($_POST['start'])) { swdone(['ran' => false, 'reason' => 'param']); }
$force = !empty($_POST['force']);
$cfg = sweep_get_config($koneksi);
if (empty($cfg['enabled'])) { swdone(['ran' => false, 'reason' => 'nonaktif']); }
if (!$force) {
    $due = !empty($cfg['last_run']) ? (strtotime($cfg['last_run']) + sweep_interval_seconds($cfg['interval_jam'])) : 0;
    if ($due !== 0 && time() < $due) { swdone(['ran' => false, 'reason' => 'belum waktunya']); }
    if (!sweep_cooldown_ok()) { swdone(['ran' => false, 'reason' => 'baru saja jalan']); }
}
$lock = @fopen(sweep_lock_path(), 'c');
if (!($lock && @flock($lock, LOCK_EX | LOCK_NB))) { swdone(['ran' => false, 'reason' => 'sibuk']); }
$res = sweep_execute($koneksi, $cfg['master_id'], false, 0, true, function($line) {
    echo $line . "\n" . str_repeat(' ', 1024);
    @flush();
});
@flock($lock, LOCK_UN);
@fclose($lock);
$next_in = 0; $ivlabel = '';
if (!empty($res['ran'])) {
    $cfg2 = sweep_get_config($koneksi);
    $iv = (int)$cfg2['interval_jam'];
    $ivlabel = $iv === -1 ? '1 menit' : ($iv === 0 ? '5 menit' : $iv . ' jam');
    if (!empty($cfg2['last_run'])) $next_in = max(0, strtotime($cfg2['last_run']) + sweep_interval_seconds($iv) - time());
}
swdone([
    'ran' => !empty($res['ran']),
    'total' => (int)($res['total'] ?? 0),
    'baru' => (int)($res['baru'] ?? 0),
    'sebar_ok' => (int)($res['sebar_ok'] ?? 0),
    'repair_ok' => (int)($res['repair_ok'] ?? 0),
    'mesh_ok' => (int)($res['mesh_ok'] ?? 0),
    'next_in' => (int)$next_in,
    'interval_label' => $ivlabel,
]);
