<?php
/**
 * Worker Bot Telegram (polling).
 * Jalankan: php telegram_bot_worker.php
 * Biarkan terminal tetap terbuka selama demo.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Worker hanya bisa dijalankan dari CLI: php telegram_bot_worker.php');
}

date_default_timezone_set('Asia/Jakarta');
require __DIR__ . '/koneksi.php';
require_once __DIR__ . '/telegram_helper.php';

$lockFile = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'payroll_tg_worker.lock';
$lock = @fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Worker Telegram sudah berjalan di proses lain.\n");
    exit(1);
}

function tg_worker_log_line($tag, $text) {
    echo '[' . date('Y-m-d H:i:s') . "] [$tag] $text\n";
}

tg_ensure_tables($koneksi);
tg_worker_log_line('start', 'Worker Telegram jalan. Tekan Ctrl+C untuk berhenti.');

$last_schedule_attempt = 0;

while (true) {
    $cfg = tg_config($koneksi, true);

    if (trim((string)$cfg['bot_token']) === '') {
        tg_worker_log_line('config', 'Bot token belum diisi. Menunggu 15 detik...');
        sleep(15);
        continue;
    }

    // 1) Cek jadwal laporan harian.
    if (!empty($cfg['enabled'])) {
        $now = date('H:i:s');
        $jam = substr((string)$cfg['jam_laporan'], 0, 8);
        if (strlen($jam) === 5) $jam .= ':00';
        if ($now >= $jam && (time() - $last_schedule_attempt) >= 60) {
            $last_schedule_attempt = time();
            $res = tg_send_daily_reports($koneksi, false, $cfg);
            foreach ((array)$res as $line) tg_worker_log_line('schedule', $line);
        }
    }

    // 2) Ambil update (tombol approve/tolak, /start, /laporan).
    $upd = tg_get_updates($cfg, (int)$cfg['update_id'], 20);
    if (empty($upd['ok'])) {
        tg_worker_log_line('poll', 'Gagal: ' . ($upd['description'] ?? 'tidak diketahui'));
        sleep(5);
        continue;
    }

    $list = is_array($upd['result'] ?? null) ? $upd['result'] : [];
    foreach ($list as $u) {
        $uid = (int)($u['update_id'] ?? 0);
        try {
            tg_handle_update($koneksi, $cfg, $u);
        } catch (Throwable $e) {
            tg_worker_log_line('handle', 'Error: ' . $e->getMessage());
        }
        if ($uid > 0) {
            $cfg['update_id'] = $uid + 1;
            @$koneksi->query("UPDATE tb_telegram SET update_id = " . ($uid + 1) . " WHERE id = 1");
        }
    }

    sleep(1);
}
