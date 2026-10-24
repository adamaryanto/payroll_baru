<?php
/**
 * Telegram Helper - Payroll
 * - Konfigurasi bot (1 baris di tb_telegram)
 * - Kirim pesan / dokumen Excel
 * - Notifikasi pengajuan Rencana / Realisasi + tombol Approve/Tolak
 * - Laporan harian otomatis
 *
 * Tidak butuh library eksternal. Hanya PHP + cURL.
 */

if (!function_exists('tg_ensure_tables')) {
    function tg_ensure_tables($koneksi) {
        static $done = false;
        if ($done) return;
        $done = true;

        @$koneksi->query("CREATE TABLE IF NOT EXISTS tb_telegram (
            id TINYINT NOT NULL DEFAULT 1,
            bot_token VARCHAR(255) NOT NULL DEFAULT '',
            owner_chat_id VARCHAR(64) NOT NULL DEFAULT '',
            group_chat_id VARCHAR(64) NOT NULL DEFAULT '',
            jam_laporan TIME NOT NULL DEFAULT '08:00:00',
            enabled TINYINT NOT NULL DEFAULT 0,
            base_url VARCHAR(255) NOT NULL DEFAULT 'http://localhost/payroll',
            rencana_offset TINYINT NOT NULL DEFAULT 0,
            realisasi_offset TINYINT NOT NULL DEFAULT -1,
            kirim_rencana TINYINT NOT NULL DEFAULT 1,
            kirim_realisasi TINYINT NOT NULL DEFAULT 1,
            secret VARCHAR(64) NOT NULL DEFAULT '',
            update_id BIGINT NOT NULL DEFAULT 0,
            last_run DATETIME DEFAULT NULL,
            last_sent DATE DEFAULT NULL,
            tgl_updt DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @$koneksi->query("CREATE TABLE IF NOT EXISTS tb_telegram_sent (
            tgl DATE NOT NULL,
            jenis VARCHAR(20) NOT NULL,
            sent_at DATETIME NOT NULL,
            PRIMARY KEY (tgl, jenis)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @$koneksi->query("CREATE TABLE IF NOT EXISTS tb_telegram_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            tgl DATETIME NOT NULL,
            aksi VARCHAR(40) NOT NULL,
            id_ref INT NOT NULL DEFAULT 0,
            oleh VARCHAR(120) NOT NULL DEFAULT '',
            ket TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $q = @$koneksi->query("SELECT id FROM tb_telegram WHERE id = 1 LIMIT 1");
        if ($q && $q->num_rows === 0) {
            $secret = bin2hex(random_bytes(16));
            @$koneksi->query("INSERT INTO tb_telegram (id, secret, tgl_updt) VALUES (1, '$secret', NOW())");
        } else {
            // Pastikan secret selalu ada (DB lama / baris baru).
            $r = $q ? $q->fetch_assoc() : null;
            $ck = @$koneksi->query("SELECT secret FROM tb_telegram WHERE id = 1 LIMIT 1");
            $rc = $ck ? $ck->fetch_assoc() : null;
            if ($rc && trim((string)$rc['secret']) === '') {
                $secret = bin2hex(random_bytes(16));
                @$koneksi->query("UPDATE tb_telegram SET secret = '$secret' WHERE id = 1");
            }
        }
    }

    function tg_config($koneksi, $refresh = false) {
        tg_ensure_tables($koneksi);
        static $cache = null;
        if ($cache !== null && !$refresh) return $cache;

        $cfg = [
            'id' => 1, 'bot_token' => '', 'owner_chat_id' => '', 'group_chat_id' => '',
            'jam_laporan' => '08:00:00', 'enabled' => 0, 'base_url' => 'http://localhost/payroll',
            'rencana_offset' => 0, 'realisasi_offset' => -1, 'kirim_rencana' => 1, 'kirim_realisasi' => 1,
            'secret' => '', 'update_id' => 0, 'last_run' => null, 'last_sent' => null, 'tgl_updt' => null,
        ];
        $q = @$koneksi->query("SELECT * FROM tb_telegram WHERE id = 1 LIMIT 1");
        if ($q && ($r = $q->fetch_assoc())) {
            foreach ($cfg as $k => $v) {
                if (array_key_exists($k, $r)) $cfg[$k] = $r[$k];
            }
        }
        $cfg['enabled'] = (int)$cfg['enabled'];
        $cfg['kirim_rencana'] = (int)$cfg['kirim_rencana'];
        $cfg['kirim_realisasi'] = (int)$cfg['kirim_realisasi'];
        $cfg['rencana_offset'] = (int)$cfg['rencana_offset'];
        $cfg['realisasi_offset'] = (int)$cfg['realisasi_offset'];
        $cfg['update_id'] = (int)$cfg['update_id'];
        $cache = $cfg;
        return $cfg;
    }

    function tg_save_config($koneksi, $data) {
        tg_ensure_tables($koneksi);
        $e = function ($v) use ($koneksi) { return $koneksi->real_escape_string((string)$v); };
        $token = trim((string)($data['bot_token'] ?? ''));
        $owner = trim((string)($data['owner_chat_id'] ?? ''));
        $group = trim((string)($data['group_chat_id'] ?? ''));
        $jam = trim((string)($data['jam_laporan'] ?? '08:00:00'));
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $jam)) $jam = '08:00:00';
        if (strlen($jam) === 5) $jam .= ':00';
        $enabled = !empty($data['enabled']) ? 1 : 0;
        $base = rtrim(trim((string)($data['base_url'] ?? '')), '/');
        if ($base === '') $base = 'http://localhost/payroll';
        $ro = max(-31, min(31, (int)($data['rencana_offset'] ?? 0)));
        $rlo = max(-31, min(31, (int)($data['realisasi_offset'] ?? -1)));
        $kr = !empty($data['kirim_rencana']) ? 1 : 0;
        $kl = !empty($data['kirim_realisasi']) ? 1 : 0;

        $sql = "UPDATE tb_telegram SET
            bot_token='" . $e($token) . "',
            owner_chat_id='" . $e($owner) . "',
            group_chat_id='" . $e($group) . "',
            jam_laporan='" . $e($jam) . "',
            enabled=$enabled,
            base_url='" . $e($base) . "',
            rencana_offset=$ro,
            realisasi_offset=$rlo,
            kirim_rencana=$kr,
            kirim_realisasi=$kl,
            tgl_updt=NOW()
            WHERE id=1";
        $ok = (bool)$koneksi->query($sql);
        tg_config($koneksi, true);
        return $ok;
    }

    function tg_log($koneksi, $aksi, $id_ref = 0, $oleh = '', $ket = '') {
        $a = $koneksi->real_escape_string($aksi);
        $o = $koneksi->real_escape_string($oleh);
        $k = $koneksi->real_escape_string($ket);
        @$koneksi->query("INSERT INTO tb_telegram_log (tgl, aksi, id_ref, oleh, ket) VALUES (NOW(), '$a', " . (int)$id_ref . ", '$o', '$k')");
    }

    function tg_api($cfg, $method, $params = [], $timeout = 20) {
        $token = trim((string)($cfg['bot_token'] ?? ''));
        if ($token === '') return ['ok' => false, 'description' => 'Bot token belum diisi'];
        $url = 'https://api.telegram.org/bot' . $token . '/' . $method;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        if (!empty($params)) {
            $hasFile = false;
            foreach ($params as $v) { if ($v instanceof CURLFile) { $hasFile = true; break; } }
            if ($hasFile) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }
        }
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) return ['ok' => false, 'description' => 'cURL: ' . $err];
        $j = json_decode($body, true);
        if (!is_array($j)) return ['ok' => false, 'description' => 'Response tidak valid (HTTP ' . $code . ')'];
        if (empty($j['ok'])) return ['ok' => false, 'description' => (string)($j['description'] ?? 'Gagal'), 'raw' => $j];
        return ['ok' => true, 'result' => $j['result'] ?? null];
    }

    function tg_send_message($cfg, $chat_id, $text, $reply_markup = null, $timeout = 20) {
        $params = ['chat_id' => $chat_id, 'text' => $text, 'disable_web_page_preview' => 'true'];
        if ($reply_markup !== null) $params['reply_markup'] = json_encode($reply_markup);
        return tg_api($cfg, 'sendMessage', $params, $timeout);
    }

    function tg_edit_message($cfg, $chat_id, $message_id, $text, $reply_markup = null) {
        $params = ['chat_id' => $chat_id, 'message_id' => (int)$message_id, 'text' => $text];
        if ($reply_markup !== null) $params['reply_markup'] = json_encode($reply_markup);
        return tg_api($cfg, 'editMessageText', $params);
    }

    function tg_answer_callback($cfg, $cb_id, $text = '') {
        return tg_api($cfg, 'answerCallbackQuery', ['callback_query_id' => $cb_id, 'text' => $text], 10);
    }

    function tg_send_document($cfg, $chat_id, $file_path, $caption = '', $reply_markup = null) {
        if (!is_file($file_path)) return ['ok' => false, 'description' => 'File tidak ditemukan'];
        $params = [
            'chat_id' => $chat_id,
            'document' => new CURLFile($file_path),
            'caption' => mb_substr($caption, 0, 1000),
        ];
        if ($reply_markup !== null) $params['reply_markup'] = json_encode($reply_markup);
        return tg_api($cfg, 'sendDocument', $params, 120);
    }

    function tg_get_updates($cfg, $offset = 0, $timeout = 20) {
        $params = ['timeout' => $timeout, 'allowed_updates' => json_encode(['message', 'callback_query'])];
        if ($offset > 0) $params['offset'] = $offset;
        return tg_api($cfg, 'getUpdates', $params, $timeout + 15);
    }

    function tg_signed($cfg, $type, $id) {
        $secret = (string)($cfg['secret'] ?? '');
        if ($secret === '') $secret = 'payroll-telegram';
        return substr(hash_hmac('sha256', $type . ':' . (int)$id, $secret), 0, 12);
    }

    function tg_owner_chat($cfg) {
        $owner = trim((string)($cfg['owner_chat_id'] ?? ''));
        return $owner !== '' ? $owner : trim((string)($cfg['group_chat_id'] ?? ''));
    }

    function tg_fmt_rp($n) {
        return 'Rp ' . number_format((float)$n, 0, ',', '.');
    }

    function tg_find_report($koneksi, $jenis, $tanggal) {
        $d = $koneksi->real_escape_string($tanggal);
        if ($jenis === 'rencana') {
            $q = $koneksi->query("SELECT id_rkk FROM tb_rkk WHERE tgl_rkk = '$d' ORDER BY (status_rkk >= 1) DESC, id_rkk DESC LIMIT 1");
            return $q && ($r = $q->fetch_assoc()) ? (int)$r['id_rkk'] : 0;
        }
        $q = $koneksi->query("SELECT id_realisasi FROM tb_realisasi WHERE tgl_realisasi = '$d' ORDER BY (status_realisasi = 2) DESC, id_realisasi DESC LIMIT 1");
        return $q && ($r = $q->fetch_assoc()) ? (int)$r['id_realisasi'] : 0;
    }

    function tg_report_summary($koneksi, $jenis, $id) {
        $id = (int)$id;
        if ($jenis === 'rencana') {
            $q = $koneksi->query("SELECT tgl_rkk, status_rkk FROM tb_rkk WHERE id_rkk = $id LIMIT 1");
            $r = $q ? $q->fetch_assoc() : null;
            if (!$r) return null;
            $qs = $koneksi->query("SELECT COUNT(*) jml, SUM(CASE WHEN status_rkk = 'Digantikan' THEN 0 ELSE upah END) ttl FROM tb_rkk_detail WHERE id_rkk = $id");
            $s = $qs ? $qs->fetch_assoc() : ['jml' => 0, 'ttl' => 0];
            $st = (int)$r['status_rkk'];
            $label = $st >= 2 ? 'Approved' : ($st == 1 ? 'Diajukan (menunggu approval)' : 'Draft');
            return ['jenis' => 'rencana', 'id' => $id, 'tgl' => $r['tgl_rkk'], 'jml' => (int)$s['jml'], 'total' => (float)$s['ttl'], 'status' => $label, 'status_n' => $st];
        }
        $q = $koneksi->query("SELECT tgl_realisasi, status_realisasi FROM tb_realisasi WHERE id_realisasi = $id LIMIT 1");
        $r = $q ? $q->fetch_assoc() : null;
        if (!$r) return null;
        $qs = $koneksi->query("SELECT COUNT(*) jml, SUM(r_upah) ttl FROM tb_realisasi_detail WHERE id_realisasi = $id");
        $s = $qs ? $qs->fetch_assoc() : ['jml' => 0, 'ttl' => 0];
        $st = (int)$r['status_realisasi'];
        $label = $st >= 2 ? 'Approved' : ($st == 1 ? 'Diajukan (menunggu approval)' : 'Draft');
        return ['jenis' => 'realisasi', 'id' => $id, 'tgl' => $r['tgl_realisasi'], 'jml' => (int)$s['jml'], 'total' => (float)$s['ttl'], 'status' => $label, 'status_n' => $st];
    }

    function tg_summary_text($s) {
        if (!$s) return '';
        $judul = $s['jenis'] === 'rencana' ? 'RENCANA UPAH' : 'REALISASI UPAH';
        return "📋 " . $judul . "\n"
            . "Tanggal : " . date('d/m/Y', strtotime($s['tgl'])) . "\n"
            . "ID      : #" . $s['id'] . "\n"
            . "Karyawan: " . $s['jml'] . " orang\n"
            . "Total   : " . tg_fmt_rp($s['total']) . "\n"
            . "Status  : " . $s['status'];
    }

    function tg_export_url($cfg, $jenis, $id) {
        $base = rtrim((string)($cfg['base_url'] ?? ''), '/');
        if ($jenis === 'rencana') return $base . '/excelrkk.php?id=' . (int)$id;
        return $base . '/page/realisasi/excelrealisasi_baru.php?id=' . (int)$id;
    }

    function tg_fetch_export($cfg, $jenis, $id) {
        $url = tg_export_url($cfg, $jenis, $id);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) return ['ok' => false, 'error' => 'Gagal ambil Excel: ' . $err];
        if ($code < 200 || $code >= 400) return ['ok' => false, 'error' => 'HTTP ' . $code . ' saat ambil Excel'];
        if (strlen($body) < 50) return ['ok' => false, 'error' => 'File Excel kosong/terlalu kecil'];
        $ext = $jenis === 'rencana' ? 'xls' : 'xlsx';
        $path = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'tg_' . $jenis . '_' . (int)$id . '_' . date('Ymd_His') . '.' . $ext;
        if (file_put_contents($path, $body) === false) return ['ok' => false, 'error' => 'Gagal simpan file sementara'];
        return ['ok' => true, 'path' => $path, 'size' => strlen($body)];
    }

    function tg_is_owner($cfg, $chat_id, $from_id) {
        // Hanya owner_chat_id yang boleh approve. Group hanya tujuan kirim, bukan hak approve.
        $owner = trim((string)($cfg['owner_chat_id'] ?? ''));
        if ($owner === '') return false;
        return ((string)$chat_id === $owner || (string)$from_id === $owner);
    }

    /**
     * Kirim notifikasi pengajuan + tombol approve/tolak.
     * $jenis = 'rencana' | 'realisasi'
     */
    function tg_notify_proposal($koneksi, $jenis, $id) {
        $cfg = tg_config($koneksi, true);
        if (empty($cfg['enabled']) || trim((string)$cfg['bot_token']) === '') return false;
        $chat = tg_owner_chat($cfg);
        if ($chat === '') return false;

        $s = tg_report_summary($koneksi, $jenis, $id);
        if (!$s) return false;
        $type = $jenis === 'rencana' ? 'rkk' : 'rls';
        $tok = tg_signed($cfg, $type, $id);
        $text = "🔔 *PENGAJUAN " . ($jenis === 'rencana' ? 'RENCANA' : 'REALISASI') . " UPAH*\n\n"
            . tg_summary_text($s) . "\n\n"
            . "Owner, silakan pilih:";
        $kb = ['inline_keyboard' => [[
            ['text' => '✅ Approve', 'callback_data' => $type . ':app:' . (int)$id . ':' . $tok],
            ['text' => '❌ Tolak', 'callback_data' => $type . ':rej:' . (int)$id . ':' . $tok],
        ]]];

        $doc = tg_fetch_export($cfg, $jenis, $id);
        $res = ['ok' => false];
        if (!empty($doc['ok'])) {
            $res = tg_send_document($cfg, $chat, $doc['path'], $text, $kb);
            @unlink($doc['path']);
        }
        if (empty($res['ok'])) {
            $res = tg_send_message($cfg, $chat, $text, $kb);
        }
        tg_log($koneksi, 'notify_' . $jenis, (int)$id, 'system', $res['ok'] ? 'Terkirim' : ('Gagal: ' . ($res['description'] ?? '')));
        return !empty($res['ok']);
    }

    /**
     * Kirim laporan harian (Rencana & Realisasi).
     * $force = true -> abaikan jadwal & penanda, untuk tombol "Kirim Sekarang".
     * Mengembalikan array pesan hasil.
     */
    function tg_send_daily_reports($koneksi, $force = false, $cfg = null) {
        if ($cfg === null) $cfg = tg_config($koneksi, true);
        $out = [];
        if (trim((string)$cfg['bot_token']) === '') return ['Bot token belum diisi.'];
        if (!$force && empty($cfg['enabled'])) return ['Bot sedang nonaktif.'];
        $chat = tg_owner_chat($cfg);
        if ($chat === '') return ['Chat ID owner belum diisi.'];

        date_default_timezone_set('Asia/Jakarta');
        $today = date('Y-m-d');

        $jobs = [];
        if (!empty($cfg['kirim_rencana'])) $jobs[] = ['rencana', (int)$cfg['rencana_offset']];
        if (!empty($cfg['kirim_realisasi'])) $jobs[] = ['realisasi', (int)$cfg['realisasi_offset']];
        if (empty($jobs)) return ['Tidak ada jenis laporan yang dipilih.'];

        foreach ($jobs as $job) {
            list($jenis, $offset) = $job;
            $tgl = date('Y-m-d', strtotime($today . ' ' . ($offset >= 0 ? '+' : '') . $offset . ' days'));

            if (!$force) {
                $ce = $koneksi->query("SELECT 1 FROM tb_telegram_sent WHERE tgl = '" . $koneksi->real_escape_string($tgl) . "' AND jenis = '" . $koneksi->real_escape_string($jenis) . "' LIMIT 1");
                if ($ce && $ce->num_rows > 0) { $out[] = ucfirst($jenis) . ' ' . $tgl . ': sudah terkirim hari ini.'; continue; }
            }

            $id = tg_find_report($koneksi, $jenis, $tgl);
            if ($id <= 0) { $out[] = ucfirst($jenis) . ' ' . $tgl . ': data tidak ada, dilewati.'; continue; }

            $s = tg_report_summary($koneksi, $jenis, $id);
            if (!$s) { $out[] = ucfirst($jenis) . ' ' . $tgl . ': data tidak terbaca.'; continue; }

            $doc = tg_fetch_export($cfg, $jenis, $id);
            if (empty($doc['ok'])) {
                $out[] = ucfirst($jenis) . ' ' . $tgl . ': ' . ($doc['error'] ?? 'gagal ambil Excel');
                tg_log($koneksi, 'report_' . $jenis, $id, 'system', 'Gagal Excel: ' . ($doc['error'] ?? ''));
                continue;
            }

            $caption = tg_summary_text($s);
            $res = tg_send_document($cfg, $chat, $doc['path'], $caption);
            @unlink($doc['path']);

            if (!empty($res['ok'])) {
                if (!$force) {
                    $koneksi->query("INSERT INTO tb_telegram_sent (tgl, jenis, sent_at) VALUES ('" . $koneksi->real_escape_string($tgl) . "', '" . $koneksi->real_escape_string($jenis) . "', NOW())
                        ON DUPLICATE KEY UPDATE sent_at = NOW()");
                }
                $out[] = ucfirst($jenis) . ' ' . $tgl . ': terkirim (#' . $id . ').';
                tg_log($koneksi, 'report_' . $jenis, $id, 'system', 'Terkirim');
            } else {
                $out[] = ucfirst($jenis) . ' ' . $tgl . ': gagal kirim - ' . ($res['description'] ?? '');
                tg_log($koneksi, 'report_' . $jenis, $id, 'system', 'Gagal: ' . ($res['description'] ?? ''));
            }
        }

        if (!$force) {
            $koneksi->query("UPDATE tb_telegram SET last_run = NOW(), last_sent = '" . $koneksi->real_escape_string($today) . "' WHERE id = 1");
        }
        return $out;
    }

    /**
     * Proses satu update Telegram (dipakai worker).
     * Return true jika update dikenali.
     */
    function tg_handle_update($koneksi, $cfg, $u) {
        if (!is_array($u)) return false;

        // Pesan biasa: /start atau /chatid -> bantu ambil chat id.
        if (isset($u['message'])) {
            $msg = $u['message'];
            $chat_id = $msg['chat']['id'] ?? '';
            $text = trim((string)($msg['text'] ?? ''));
            if ($text === '/start' || strtolower($text) === '/chatid' || strtolower($text) === '/id') {
                tg_send_message($cfg, $chat_id, "Chat ID kamu: " . $chat_id . "\n\nMasukkan angka ini ke Setting Telegram > Owner Chat ID.");
                return true;
            }
            if (strtolower($text) === '/laporan') {
                if (tg_is_owner($cfg, $chat_id, $msg['from']['id'] ?? '')) {
                    $res = tg_send_daily_reports($koneksi, true, $cfg);
                    tg_send_message($cfg, $chat_id, "Hasil kirim laporan:\n- " . implode("\n- ", $res));
                } else {
                    tg_send_message($cfg, $chat_id, "Hanya owner yang bisa meminta laporan.");
                }
                return true;
            }
            return false;
        }

        if (!isset($u['callback_query'])) return false;
        $cb = $u['callback_query'];
        $cb_id = $cb['id'] ?? '';
        $data = (string)($cb['data'] ?? '');
        $from_id = $cb['from']['id'] ?? '';
        $msg = $cb['message'] ?? [];
        $chat_id = $msg['chat']['id'] ?? '';
        $message_id = $msg['message_id'] ?? 0;

        $parts = explode(':', $data);
        if (count($parts) < 4) { tg_answer_callback($cfg, $cb_id, 'Data tombol tidak valid.'); return true; }
        list($type, $action, $id, $tok) = $parts;
        $id = (int)$id;

        if (!hash_equals(tg_signed($cfg, $type, $id), $tok)) {
            tg_answer_callback($cfg, $cb_id, 'Tombol kedaluwarsa / tidak valid.');
            return true;
        }
        if (!tg_is_owner($cfg, $chat_id, $from_id)) {
            tg_answer_callback($cfg, $cb_id, 'Hanya owner yang bisa approve.');
            return true;
        }

        date_default_timezone_set('Asia/Jakarta');
        $now = date('Y-m-d H:i:s');
        $oleh = 'Telegram:' . $from_id;
        $ok = false; $text = ''; $new_status = '';

        if ($type === 'rkk') {
            $q = $koneksi->query("SELECT status_rkk, tgl_rkk FROM tb_rkk WHERE id_rkk = $id LIMIT 1");
            $r = $q ? $q->fetch_assoc() : null;
            if (!$r) { tg_answer_callback($cfg, $cb_id, 'Data RKK tidak ditemukan.'); return true; }
            if ((int)$r['status_rkk'] !== 1) { tg_answer_callback($cfg, $cb_id, 'Status sudah berubah (bukan menunggu approval).'); return true; }
            if ($action === 'app') {
                $ok = (bool)$koneksi->query("UPDATE tb_rkk SET status_rkk = 2, tgl_status = '$now' WHERE id_rkk = $id");
                $new_status = '✅ APPROVED';
            } elseif ($action === 'rej') {
                $ok = (bool)$koneksi->query("UPDATE tb_rkk SET status_rkk = 0, tgl_status = '$now' WHERE id_rkk = $id");
                $new_status = '❌ DITOLAK (kembali Draft)';
            }
        } elseif ($type === 'rls') {
            $q = $koneksi->query("SELECT status_realisasi, tgl_realisasi FROM tb_realisasi WHERE id_realisasi = $id LIMIT 1");
            $r = $q ? $q->fetch_assoc() : null;
            if (!$r) { tg_answer_callback($cfg, $cb_id, 'Data realisasi tidak ditemukan.'); return true; }
            if ((int)$r['status_realisasi'] !== 1) { tg_answer_callback($cfg, $cb_id, 'Status sudah berubah (bukan menunggu approval).'); return true; }
            $tgl_only = date('Y-m-d');
            if ($action === 'app') {
                $ok = (bool)$koneksi->query("UPDATE tb_realisasi SET status_realisasi = 2, tgl_status = '$tgl_only' WHERE id_realisasi = $id");
                $new_status = '✅ APPROVED';
            } elseif ($action === 'rej') {
                $ok = (bool)$koneksi->query("UPDATE tb_realisasi SET status_realisasi = 0, tgl_status = '$tgl_only' WHERE id_realisasi = $id");
                $new_status = '❌ DITOLAK (kembali Draft)';
            }
        } else {
            tg_answer_callback($cfg, $cb_id, 'Jenis data tidak dikenal.');
            return true;
        }

        if ($ok) {
            $s = tg_report_summary($koneksi, $type === 'rkk' ? 'rencana' : 'realisasi', $id);
            $newText = ($s ? tg_summary_text($s) : ('Data #' . $id)) . "\n\n" . $new_status . "\nOleh: Owner (Telegram)\n" . $now;
            $edit = tg_edit_message($cfg, $chat_id, $message_id, $newText, ['inline_keyboard' => []]);
            if (empty($edit['ok'])) {
                // Pesan pengajuan dikirim sebagai dokumen Excel -> ubah caption, bukan text.
                tg_api($cfg, 'editMessageCaption', [
                    'chat_id' => $chat_id, 'message_id' => (int)$message_id,
                    'caption' => mb_substr($newText, 0, 1000),
                    'reply_markup' => json_encode(['inline_keyboard' => []]),
                ]);
            }
            tg_answer_callback($cfg, $cb_id, $new_status);
            tg_log($koneksi, ($action === 'app' ? 'approve_' : 'reject_') . $type, $id, $oleh, $new_status);
        } else {
            tg_answer_callback($cfg, $cb_id, 'Gagal update database: ' . $koneksi->error);
            tg_log($koneksi, 'approve_fail_' . $type, $id, $oleh, $koneksi->error);
        }
        return true;
    }
}
