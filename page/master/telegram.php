<?php
// Setting Telegram (khusus Admin Master)
if (!isset($koneksi)) include_once __DIR__ . '/../../koneksi.php';
include_once __DIR__ . '/../../telegram_helper.php';

if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin master') {
    echo '<div class="container-fluid px-4 mt-4"><div class="p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-800 font-medium">Akses ditolak. Menu ini hanya untuk Admin Master.</div></div>';
    return;
}

$msg = '';
$msg_type = 'ok';

$auto_base = '';
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/payroll/index.php')), '/');
$auto_base = $scheme . '://' . $host . $dir;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        if (empty($_POST['base_url'])) $_POST['base_url'] = $auto_base;
        if (tg_save_config($koneksi, $_POST)) {
            $msg = 'Pengaturan Telegram berhasil disimpan.';
        } else {
            $msg = 'Gagal menyimpan: ' . $koneksi->error;
            $msg_type = 'err';
        }
    } elseif ($act === 'test') {
        $cfg = tg_config($koneksi, true);
        $chat = tg_owner_chat($cfg);
        if ($chat === '') {
            $msg = 'Owner Chat ID belum diisi. Kirim /start ke bot lalu masukkan Chat ID.';
            $msg_type = 'err';
        } else {
            $res = tg_send_message($cfg, $chat, "✅ Tes bot Payroll berhasil.\nWaktu: " . date('d/m/Y H:i:s'));
            if (!empty($res['ok'])) {
                $msg = 'Pesan tes terkirim ke owner.';
            } else {
                $msg = 'Gagal kirim tes: ' . ($res['description'] ?? '');
                $msg_type = 'err';
            }
        }
    } elseif ($act === 'sendnow') {
        $cfg = tg_config($koneksi, true);
        $res = tg_send_daily_reports($koneksi, true, $cfg);
        $msg = "Kirim laporan sekarang:\n- " . implode("\n- ", $res);
    }
}

$cfg = tg_config($koneksi, true);
if (empty($cfg['base_url'])) $cfg['base_url'] = $auto_base;
$logs = [];
$ql = $koneksi->query("SELECT * FROM tb_telegram_log ORDER BY id DESC LIMIT 12");
while ($ql && ($r = $ql->fetch_assoc())) $logs[] = $r;
?>
<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fab fa-telegram mr-2"></i>Setting Telegram</h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Bot untuk approve Rencana/Realisasi &amp; kirim laporan Excel otomatis</p>
            </div>
            <a href="?page=" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors"><i class="fas fa-arrow-left mr-1.5"></i> Dashboard</a>
        </div>

        <div class="p-6">
            <?php if ($msg !== ''): ?>
            <div class="mb-4 p-3 rounded-lg <?= $msg_type === 'ok' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?> text-sm font-medium whitespace-pre-line"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <form method="POST">
                        <input type="hidden" name="act" value="save">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Bot Token</label>
                                <input type="text" name="bot_token" value="<?= htmlspecialchars($cfg['bot_token']) ?>" placeholder="123456:ABC-DEF..." class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-mono">
                                <p class="text-xs text-gray-400 mt-1">Dari @BotFather. Jangan dibagikan ke siapa pun.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Owner Chat ID</label>
                                <input type="text" name="owner_chat_id" value="<?= htmlspecialchars($cfg['owner_chat_id']) ?>" placeholder="mis: 123456789" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                                <p class="text-xs text-gray-400 mt-1">Kirim <strong>/start</strong> ke bot, lalu masukkan Chat ID yang dibalas bot.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Group Chat ID (opsional)</label>
                                <input type="text" name="group_chat_id" value="<?= htmlspecialchars($cfg['group_chat_id']) ?>" placeholder="mis: -1001234567890" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Jam Kirim Laporan</label>
                                <input type="time" name="jam_laporan" value="<?= htmlspecialchars(substr($cfg['jam_laporan'], 0, 5)) ?>" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                                <p class="text-xs text-gray-400 mt-1">Zona waktu WIB (Asia/Jakarta).</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Base URL Aplikasi</label>
                                <input type="text" name="base_url" value="<?= htmlspecialchars($cfg['base_url']) ?>" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-mono">
                                <p class="text-xs text-gray-400 mt-1">Dipakai bot untuk mengambil file Excel. Contoh: <code><?= htmlspecialchars($auto_base) ?></code></p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Laporan Rencana</label>
                                <select name="rencana_offset" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm bg-white">
                                    <?php foreach ([0 => 'Hari ini', -1 => 'Kemarin', 1 => 'Besok'] as $v => $lbl): ?>
                                    <option value="<?= $v ?>" <?= (int)$cfg['rencana_offset'] === $v ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Laporan Realisasi</label>
                                <select name="realisasi_offset" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm bg-white">
                                    <?php foreach ([0 => 'Hari ini', -1 => 'Kemarin', -2 => '2 hari lalu'] as $v => $lbl): ?>
                                    <option value="<?= $v ?>" <?= (int)$cfg['realisasi_offset'] === $v ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-end gap-4">
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="kirim_rencana" value="1" <?= !empty($cfg['kirim_rencana']) ? 'checked' : '' ?> class="rounded"> Rencana
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="kirim_realisasi" value="1" <?= !empty($cfg['kirim_realisasi']) ? 'checked' : '' ?> class="rounded"> Realisasi
                                </label>
                            </div>
                            <div>
                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-800">
                                    <input type="checkbox" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?> class="rounded"> Aktifkan bot &amp; laporan otomatis
                                </label>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-save mr-2"></i>Simpan</button>
                            <button type="submit" name="act" value="test" formnovalidate class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-paper-plane mr-2"></i>Tes Pesan</button>
                            <button type="submit" name="act" value="sendnow" formnovalidate class="bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-file-excel mr-2"></i>Kirim Laporan Sekarang</button>
                        </div>
                    </form>
                </div>

                <div class="lg:col-span-1">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm">
                        <p class="font-bold text-slate-800 mb-2"><i class="fas fa-circle-info mr-1"></i> Cara kerja</p>
                        <ol class="list-decimal ml-4 space-y-1 text-slate-600 text-xs">
                            <li>Buat bot di @BotFather &rarr; salin token.</li>
                            <li>Kirim <strong>/start</strong> ke bot &rarr; catat Chat ID.</li>
                            <li>Isi token + Chat ID, centang Aktifkan, Simpan.</li>
                            <li>Jalankan worker: <code class="bg-white px-1 py-0.5 rounded border">php telegram_bot_worker.php</code></li>
                            <li>Saat Rencana/Realisasi di-propose, owner dapat tombol Approve/Tolak.</li>
                            <li>Tiap jam yang diset, bot kirim Excel otomatis.</li>
                        </ol>
                        <div class="mt-3 pt-3 border-t border-slate-200 text-xs text-slate-500">
                            <p><strong>Last run:</strong> <?= $cfg['last_run'] ? htmlspecialchars((string)$cfg['last_run']) : '-' ?></p>
                            <p><strong>Last sent:</strong> <?= $cfg['last_sent'] ? htmlspecialchars((string)$cfg['last_sent']) : '-' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <p class="font-bold text-gray-700 text-sm mb-2"><i class="fas fa-history mr-1"></i> Log Terakhir</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase">
                            <tr><th class="py-2 px-3 text-left">Waktu</th><th class="py-2 px-3 text-left">Aksi</th><th class="py-2 px-3 text-left">Ref</th><th class="py-2 px-3 text-left">Oleh</th><th class="py-2 px-3 text-left">Keterangan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="py-4 px-3 text-center text-gray-400">Belum ada aktivitas.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="py-1.5 px-3 text-gray-500 whitespace-nowrap"><?= htmlspecialchars($l['tgl']) ?></td>
                                <td class="py-1.5 px-3 font-semibold text-indigo-700"><?= htmlspecialchars($l['aksi']) ?></td>
                                <td class="py-1.5 px-3"><?= (int)$l['id_ref'] ?: '-' ?></td>
                                <td class="py-1.5 px-3"><?= htmlspecialchars($l['oleh']) ?></td>
                                <td class="py-1.5 px-3 text-gray-600"><?= htmlspecialchars((string)$l['ket']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
