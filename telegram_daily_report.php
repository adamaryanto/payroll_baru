<?php
/**
 * Kirim laporan Rencana & Realisasi via Telegram (satu kali jalan).
 * Dipakai untuk:
 *  - Task Scheduler harian jam 08:00
 *  - Demo manual: php telegram_daily_report.php --force
 */
date_default_timezone_set('Asia/Jakarta');
require __DIR__ . '/koneksi.php';
require_once __DIR__ . '/telegram_helper.php';

$force = false;
if (PHP_SAPI === 'cli') {
    $force = in_array('--force', $argv ?? [], true);
} else {
    // Akses web hanya dengan kunci rahasia: telegram_daily_report.php?key=<secret>
    $cfg0 = tg_config($koneksi, true);
    $key = (string)($_GET['key'] ?? '');
    if ($key === '' || !hash_equals((string)$cfg0['secret'], $key)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $force = isset($_GET['force']) && $_GET['force'] === '1';
}

$cfg = tg_config($koneksi, true);
$res = tg_send_daily_reports($koneksi, $force, $cfg);
$text = '[' . date('Y-m-d H:i:s') . "] Laporan Telegram (" . ($force ? 'force' : 'scheduled') . "):\n- " . implode("\n- ", $res);

if (PHP_SAPI === 'cli') {
    echo $text . "\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $text . "\n";
}
