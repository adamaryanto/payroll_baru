<?php
// Backup / Restore template sidik jari X606S <-> database (tb_fingerprint)
include_once __DIR__ . '/../../fingerprint_db_helper.php';
if (function_exists('create_sync_log_table')) create_sync_log_table($koneksi);
fp_ensure_table($koneksi);

$devices = get_all_devices($koneksi);
for ($i = count($devices); $i < 4; $i++) $devices[] = ['id_mesin' => '', 'no_mesin' => $i + 1, 'nama_mesin' => 'Mesin ' . ($i + 1), 'ip_mesin' => '', 'comm_key' => '0', 'port' => '4370', 'protocol' => 'soap'];
$devices = array_values($devices);

$karyawan_list = [];
$qr = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND no_absen!='' AND no_absen IS NOT NULL ORDER BY CAST(no_absen AS UNSIGNED), nama_karyawan");
if ($qr) while ($r = $qr->fetch_assoc()) $karyawan_list[] = $r;

$notice = null;
if (isset($_POST['sweep_save'])) {
    $ok = sweep_save_config($koneksi, !empty($_POST['sweep_enabled']), intval($_POST['sweep_interval'] ?? 4), $_POST['sweep_master'] ?? 'auto');
    $notice = $ok ? ['success' => true, 'message' => 'Jadwal auto-sweep disimpan.'] : ['success' => false, 'message' => 'Gagal simpan jadwal.'];
}
if (isset($_POST['fp_pull'])) {
    $notice = fp_pull_and_save($koneksi, $_POST['device_id'] ?? '', trim($_POST['pin'] ?? ''));
}
if (isset($_POST['fp_restore'])) {
    $notice = fp_restore_to_device($koneksi, $_POST['device_id'] ?? '', trim($_POST['pin'] ?? ''));
}
if (isset($_POST['fp_delete'])) {
    $pin = trim($_POST['pin'] ?? '');
    if ($pin !== '' && fp_delete_pin($koneksi, $pin)) $notice = ['success' => true, 'message' => "Backup PIN $pin dihapus dari database"];
    else $notice = ['success' => false, 'message' => 'Gagal hapus / PIN kosong'];
}
// Bulk: tarik semua karyawan aktif dari 1 mesin sumber
$bulk = null;
if (isset($_POST['fp_bulk_pull'])) {
    $src = $_POST['bulk_device_id'] ?? '';
    $bulk = ['total' => count($karyawan_list), 'sukses' => 0, 'kosong' => 0, 'gagal' => 0, 'detail' => []];
    set_time_limit(0);
    foreach ($karyawan_list as $k) {
        $r = fp_pull_and_save($koneksi, $src, $k['no_absen']);
        $bulk['detail'][] = ['pin' => $k['no_absen'], 'nama' => $k['nama_karyawan'], 'res' => $r];
        if (!empty($r['success'])) $bulk['sukses']++;
        elseif (strpos($r['message'] ?? '', 'tidak punya template') !== false) $bulk['kosong']++;
        else $bulk['gagal']++;
    }
}

$summary = fp_summary($koneksi);
$sweep_cfg = sweep_get_config($koneksi);
$next_run = '-';
$next_ts = 0;
if (!empty($sweep_cfg['last_run'])) {
    $next_ts = strtotime($sweep_cfg['last_run']) + sweep_interval_seconds($sweep_cfg['interval_jam']);
    $next_run = date('d/m/Y H:i', $next_ts);
}
// Ekor sweep.log buat diagnosa (kapan script terakhir dieksekusi scheduler)
$sweep_log_lines = [];
$sweep_log_mtime = null;
$sweep_log_path = __DIR__ . '/../../sweep.log';
if (is_file($sweep_log_path)) {
    $sweep_log_mtime = filemtime($sweep_log_path);
    $all = @file($sweep_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($all)) $sweep_log_lines = array_slice($all, -5);
}
// Keputusan jalan/tidak DITENTUKAN DI SINI (awal) karena timer auto-refresh
// di bawah butuh tahu: jangan pasang timer kalau request ini mau jalanin sweep
// (timer bisa reload di tengah sweep dan ngebunuh prosesnya).
$sweep_should_run = false;
$sweep_run_mode = ''; // manual | sebar-ulang | jadwal-baru (tombol di halaman ini)
// Auto terjadwal dipegang runner global (index.php -> assets/js/sweep-auto.js)
// yang jalan di SEMUA halaman, jadi tidak ada auto inline di sini (biar tidak dobel).
if (isset($_POST['sweep_save'])) { $sweep_should_run = true; $sweep_run_mode = 'jadwal-baru'; }
elseif (isset($_POST['spread_all_now'])) { $sweep_should_run = true; $sweep_run_mode = 'sebar-ulang'; }
elseif (isset($_POST['sweep_run_now'])) { $sweep_should_run = true; $sweep_run_mode = 'manual'; }
?>
<div class="container-fluid px-2 mt-4 mb-4">
<div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
<div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
<div>
<h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fas fa-database mr-2"></i>Backup Sidik Jari ke Database</h3>
<p class="text-[12px] text-gray-400 mt-0.5 mb-0">Tarik template dari mesin X606S (SOAP) &amp; simpan ke <code>tb_fingerprint</code> — bisa di-restore kapan saja</p>
</div>
<div class="w-full md:w-auto mt-2 md:mt-0">
<a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto"><i class="fas fa-arrow-left mr-1.5"></i> Kembali</a>
</div>
</div>
<div class="p-6">

<?php if ($notice): ?>
<div class="mb-6 p-4 rounded-lg border <?= !empty($notice['success']) ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' ?>">
<p class="text-sm font-semibold <?= !empty($notice['success']) ? 'text-green-800' : 'text-red-800' ?>"><?= htmlspecialchars($notice['message']) ?></p>
</div>
<?php endif; ?>

<div class="mb-5 p-4 bg-white rounded-lg border border-indigo-100 flex gap-3">
<i class="fas fa-info-circle text-indigo-500 mt-0.5"></i>
<div class="text-sm text-gray-600 leading-relaxed">
<strong class="text-indigo-800">Cara pakai:</strong> karyawan enroll jari <strong>di mesin</strong> dulu → pilih mesin + PIN → <strong>Tarik &amp; Simpan</strong> → template masuk DB.
Kalau mesin rusak/hilang, pilih PIN yang sudah ada backup → <strong>Restore ke Mesin</strong>. Template X606S proprietary, hanya bisa di-restore ke mesin sejenis (SOAP).
</div>
</div>

<!-- Jadwal Auto-Sweep -->
<div class="mb-8 p-6 bg-gradient-to-r from-slate-50 to-gray-50 rounded-xl border border-gray-200">
<h4 class="text-lg font-semibold text-gray-800 mb-1"><i class="far fa-clock mr-2"></i>Jadwal Auto-Sweep Sidik</h4>
<p class="text-sm text-gray-500 mb-4">Sweep otomatis berjalan sendiri dari <strong>halaman mana pun</strong> yang dibuka (jika sudah tiba waktunya) — tanpa klik apa-apa. Tombol <strong>Jalankan Sekarang</strong> untuk paksa jalan kapan saja. Tidak perlu Task Scheduler / .bat.</p>
<form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
<select name="sweep_enabled" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="0" <?= !$sweep_cfg['enabled'] ? 'selected' : '' ?>>Mati</option>
<option value="1" <?= $sweep_cfg['enabled'] ? 'selected' : '' ?>>Nyala</option>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Interval</label>
<select name="sweep_interval" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="-1" <?= (int)$sweep_cfg['interval_jam'] === -1 ? 'selected' : '' ?>>1 menit (testing)</option>
<option value="0" <?= (int)$sweep_cfg['interval_jam'] === 0 ? 'selected' : '' ?>>5 menit (testing)</option>
<?php foreach ([1,2,4,6,12,24] as $h): ?>
<option value="<?= $h ?>" <?= (int)$sweep_cfg['interval_jam'] === $h ? 'selected' : '' ?>><?= $h ?> jam</option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Mesin master</label>
<select name="sweep_master" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="auto" <?= $sweep_cfg['master_id'] === 'auto' ? 'selected' : '' ?>>Otomatis (online pertama)</option>
<?php foreach ($devices as $d): if (empty($d['id_mesin'])) continue; ?>
<option value="<?= $d['id_mesin'] ?>" <?= (string)$sweep_cfg['master_id'] === (string)$d['id_mesin'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nama_mesin']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<button type="submit" name="sweep_save" value="1" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-semibold py-2.5 px-4 rounded-lg text-sm"><i class="fas fa-save mr-2"></i>Simpan Jadwal</button>
<button type="submit" name="sweep_run_now" value="1" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-lg text-sm mt-2"><i class="fas fa-play mr-2"></i>Jalankan Sekarang</button>
<button type="submit" name="spread_all_now" value="1" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2.5 px-4 rounded-lg text-sm mt-2"><i class="fas fa-share-alt mr-2"></i>Sebar Ulang Semua</button>
<p class="text-[11px] text-gray-400 mt-1 mb-0">Jalankan/Sebar Ulang bisa beberapa menit, jangan tutup halaman. Sebar Ulang = paksa sebar semua isi DB ke semua mesin (buat mesin yang ketinggalan).</p>
</div>
</form>
<p class="text-xs text-gray-400 mt-3 mb-1">Terakhir jalan: <strong><?= htmlspecialchars($sweep_cfg['last_run'] ?? '-') ?></strong> | Berikutnya: <strong><?= htmlspecialchars($next_run) ?></strong> <span id="swCountdown" class="font-semibold text-indigo-500"></span> <span class="text-[10px] text-gray-300" title="Versi file halaman ini (buat mastiin deploy)">ui-<?= date('Ymd-His', filemtime(__FILE__)) ?></span></p>
<?php if ($next_ts <= 0): ?>
<p class="text-xs text-amber-600 mt-0 mb-1">Belum pernah jalan — pastikan Task Scheduler di PC server aktif, lalu refresh halaman ini.</p>
<?php else: ?>
<script>
(function(){
  // Sisa detik DIHITUNG SERVER saat render; browser tinggal hitung mundur
  // pakai performance.now() (monotonik: kebal jam browser ngaco / NTP / sleep).
  var remaining0 = <?= max(0, (int)$next_ts - time()) ?>;
  var t0 = performance.now();
  var el = document.getElementById('swCountdown');
  if (!el) return;
  try { el.title = 'berikutnya(server): <?= date('d/m/Y H:i:s', (int)$next_ts) ?>'; } catch(e){}
  function pad(n){ return (n<10?'0':'')+n; }
  function tick(){
    var diff = remaining0 - Math.floor((performance.now() - t0)/1000);
    if (diff <= 0) { el.textContent = '(waktunya tiba — refresh halaman)'; return; }
    var h = Math.floor(diff/3600), m = Math.floor((diff%3600)/60), s = diff%60;
    el.textContent = '(mundur: ' + pad(h)+':'+pad(m)+':'+pad(s) + ')';
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php endif; ?>
<?php if (!empty($sweep_cfg['enabled']) && $next_ts > time() && !$sweep_should_run): ?>
<script>
(function(){
  // Auto-refresh tab saat jadwal tiba biar sweep jalan sendiri.
  // Boleh minimize (jangan tutup tab/browser, PC jangan sleep).
  // Monotonik: tidak tergantung jam browser.
  var waitMs = (<?= max(0, (int)$next_ts - time()) ?> * 1000) + 15000;
  setTimeout(function(){ location.reload(); }, waitMs);
})();
</script>
<p class="text-[11px] text-gray-400 mt-1 mb-0">Tab ini refresh otomatis saat waktunya tiba (boleh minimize, jangan tutup tab/browser).</p>
<?php endif; ?>
<?php if ($sweep_log_mtime): ?>
<details class="mt-2 text-xs text-gray-500">
<summary class="cursor-pointer hover:text-gray-700">Log sweep terakhir (<?= date('d/m/Y H:i:s', $sweep_log_mtime) ?>) — klik untuk lihat</summary>
<pre class="bg-gray-50 border border-gray-200 rounded-lg p-2 mt-1 overflow-x-auto whitespace-pre-wrap max-h-40 overflow-y-auto"><?php foreach ($sweep_log_lines as $ln): ?><?= htmlspecialchars($ln) ?>&#10;<?php endforeach; ?></pre>
</details>
<?php else: ?>
<p class="text-xs text-gray-400 mt-1 mb-0">Belum ada sweep.log — script belum pernah dieksekusi di server ini.</p>
<?php endif; ?>

<?php
// Eksekusi sweep (keputusan sudah ditentukan di atas, sebelum timer auto-refresh).
if ($sweep_should_run):
    @set_time_limit(0);
    $sw_log_path = __DIR__ . '/../../sweep.log'; // run via web juga nulis sweep.log
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @ob_implicit_flush(true);
    $lock_fp = @fopen(__DIR__ . '/../../sweep.lock', 'c');
    $locked = $lock_fp && @flock($lock_fp, LOCK_EX | LOCK_NB);
    if (!$locked):
?>
<div class="mt-2 p-3 rounded-lg border border-amber-200 bg-amber-50 text-xs text-amber-800">Sweep sedang berjalan di proses lain — dilewati. Refresh halaman untuk update.</div>
<?php else:
    $sweep_master_opt = $_POST['sweep_master'] ?? $sweep_cfg['master_id'];
?>
<div class="mt-2 border rounded-xl overflow-hidden <?= $sweep_run_mode === 'manual' ? 'border-indigo-200' : 'border-gray-200' ?>">
<div class="px-4 py-2 font-bold text-sm <?= ($sweep_run_mode === 'manual' || $sweep_run_mode === 'jadwal-baru') ? 'bg-indigo-50 text-indigo-800' : ($sweep_run_mode === 'sebar-ulang' ? 'bg-amber-50 text-amber-800' : 'bg-gray-50 text-gray-700') ?> flex justify-between items-center gap-2">
<span><?= $sweep_run_mode === 'manual' ? 'Sweep manual sedang berjalan — jangan tutup halaman…' : ($sweep_run_mode === 'jadwal-baru' ? 'Jadwal disimpan — sweep langsung berjalan…' : ($sweep_run_mode === 'sebar-ulang' ? 'Sebar ulang dari DB sedang berjalan — jangan tutup halaman…' : 'Sweep otomatis sedang berjalan — jangan tutup halaman…')) ?></span>
<button type="button" onclick="window.stop();var n=document.getElementById('swStopNote');if(n)n.style.display='block';this.disabled=true;this.textContent='Dihentikan';" class="shrink-0 bg-red-600 hover:bg-red-700 disabled:bg-gray-400 text-white text-xs font-bold py-1.5 px-3 rounded-lg">■ Stop</button>
</div>
<div id="swStopNote" style="display:none" class="px-4 py-2 text-xs bg-amber-50 text-amber-800">Dihentikan. Sebagian mesin mungkin sudah ke-update — aman, sweep berikutnya membereskan sisanya. Refresh halaman untuk kembali.</div>
<pre class="p-3 text-xs overflow-x-auto whitespace-pre-wrap max-h-72 overflow-y-auto bg-white m-0"><?php
    echo str_repeat(' ', 4096);
    @flush();
    if ($sweep_run_mode === 'sebar-ulang') {
        $swres = spread_all_from_db($koneksi, function($line) use ($sw_log_path) {
            @file_put_contents($sw_log_path, $line . "\n", FILE_APPEND);
            echo htmlspecialchars($line) . "\n" . str_repeat(' ', 1024);
            @flush();
        });
    } else {
        $swres = sweep_execute($koneksi, $sweep_master_opt, false, 0, true, function($line) use ($sw_log_path) {
            @file_put_contents($sw_log_path, $line . "\n", FILE_APPEND);
            echo htmlspecialchars($line) . "\n" . str_repeat(' ', 1024);
            @flush();
        });
    }
?></pre>
<div class="px-4 py-2 text-xs <?= !empty($swres['ran']) ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-800' ?>">
<?= !empty($swres['ran']) ? (isset($swres['total']) ? ("Selesai: {$swres['total']} karyawan, {$swres['baru']} ada jari baru, sebar ok={$swres['sebar_ok']} gagal={$swres['sebar_gagal']}, repair ok={$swres['repair_ok']} gagal={$swres['repair_fail']}, mesh ok={$swres['mesh_ok']} gagal={$swres['mesh_fail']}, {$swres['seconds']} detik.") : ("Selesai sebar ulang: ok={$swres['ok']} gagal={$swres['fail']}.")) : 'Tidak jalan penuh — lihat log di atas.' ?>
Refresh halaman untuk update countdown.</div>
<?php if (!empty($swres['ran']) && $sweep_run_mode !== 'sebar-ulang'): ?>
<script>setTimeout(function(){ location.reload(); }, 8000);</script>
<div class="px-4 py-2 text-xs text-gray-500">Refresh otomatis dalam 8 detik…</div>
<?php endif; ?>
</div>
<?php
    @flock($lock_fp, LOCK_UN);
    @fclose($lock_fp);
    endif;
endif;
?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
<!-- Tarik -->
<div class="p-6 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl border border-indigo-100">
<h4 class="text-lg font-semibold text-indigo-800 mb-1">Tarik dari Mesin → Simpan ke DB</h4>
<p class="text-sm text-gray-500 mb-4">Ambil FingerID 0–9 via <code>GetUserTemplate</code>.</p>
<form method="POST" class="space-y-4">
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Mesin sumber (X606S / SOAP)</label>
<select name="device_id" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="">- Pilih Mesin -</option>
<?php foreach ($devices as $d):
    $online = !empty($d['ip_mesin']) ? test_device_connection($d['ip_mesin'], $d['port']) : false;
    $is_bin = ($d['protocol'] ?? 'soap') === 'zkudp'; ?>
<option value="<?= $d['id_mesin'] ?>" <?= empty($d['id_mesin']) ? 'disabled' : '' ?>><?= htmlspecialchars($d['nama_mesin']) ?> (<?= htmlspecialchars($d['ip_mesin'] ?: '-') ?>)<?= $is_bin ? ' [BINER-skip]' : ($online ? ' [ONLINE]' : ' [OFFLINE]') ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Karyawan (nama — no. absen)</label>
<select name="pin" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="">- Pilih Karyawan -</option>
<?php foreach ($karyawan_list as $k): ?><option value="<?= htmlspecialchars($k['no_absen']) ?>"><?= htmlspecialchars($k['nama_karyawan']) ?> — <?= htmlspecialchars($k['no_absen']) ?></option><?php endforeach; ?>
</select>
</div>
<button type="submit" name="fp_pull" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-download mr-2"></i>Tarik &amp; Simpan</button>
</form>
</div>
<!-- Restore -->
<div class="p-6 bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl border border-emerald-100">
<h4 class="text-lg font-semibold text-emerald-800 mb-1">Restore DB → Mesin</h4>
<p class="text-sm text-gray-500 mb-4">Kirim ulang template via <code>SetUserTemplate</code> per jari.</p>
<form method="POST" class="space-y-4">
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Mesin tujuan (X606S / SOAP)</label>
<select name="device_id" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="">- Pilih Mesin -</option>
<?php foreach ($devices as $d):
    $online = !empty($d['ip_mesin']) ? test_device_connection($d['ip_mesin'], $d['port']) : false;
    $is_bin = ($d['protocol'] ?? 'soap') === 'zkudp'; ?>
<option value="<?= $d['id_mesin'] ?>" <?= empty($d['id_mesin']) ? 'disabled' : '' ?>><?= htmlspecialchars($d['nama_mesin']) ?> (<?= htmlspecialchars($d['ip_mesin'] ?: '-') ?>)<?= $is_bin ? ' [BINER-skip]' : ($online ? ' [ONLINE]' : ' [OFFLINE]') ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">PIN yang sudah ada backup</label>
<select name="pin" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="">- Pilih PIN -</option>
<?php foreach ($summary as $s): ?><option value="<?= htmlspecialchars($s['pin']) ?>">PIN <?= htmlspecialchars($s['pin']) ?> — <?= htmlspecialchars($s['nama'] ?? '-') ?> (<?= $s['jml'] ?> jari)</option><?php endforeach; ?>
</select>
</div>
<button type="submit" name="fp_restore" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-upload mr-2"></i>Restore ke Mesin</button>
</form>
</div>
</div>

<!-- Bulk -->
<div class="mb-8 p-6 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl border border-purple-100">
<h4 class="text-lg font-semibold text-purple-800 mb-1"><i class="fas fa-layer-group mr-2"></i>Backup Massal — semua karyawan dari 1 mesin</h4>
<p class="text-sm text-gray-500 mb-4">Loop semua karyawan aktif, yang belum enroll akan tercatat “kosong”. Bisa lama untuk ratusan karyawan.</p>
<?php if ($bulk): ?>
<div class="mb-4 p-3 bg-white rounded-lg border text-sm">Total: <?= $bulk['total'] ?> | <span class="text-green-600 font-bold">Sukses: <?= $bulk['sukses'] ?></span> | <span class="text-amber-600">Kosong: <?= $bulk['kosong'] ?></span> | <span class="text-red-600">Gagal: <?= $bulk['gagal'] ?></span></div>
<?php endif; ?>
<form method="POST" onsubmit="return confirm('Backup semua karyawan aktif dari mesin ini? Lanjutkan?')">
<div class="flex flex-col md:flex-row gap-3">
<select name="bulk_device_id" required class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="">- Pilih Mesin Sumber -</option>
<?php foreach ($devices as $d): ?><option value="<?= $d['id_mesin'] ?>" <?= empty($d['id_mesin']) ? 'disabled' : '' ?>><?= htmlspecialchars($d['nama_mesin']) ?> (<?= htmlspecialchars($d['ip_mesin'] ?: '-') ?>)</option><?php endforeach; ?>
</select>
<button type="submit" name="fp_bulk_pull" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-copy mr-2"></i>Backup Semua</button>
</div>
</form>
</div>

<!-- Isi DB -->
<h4 class="text-lg font-semibold text-gray-800 mb-3">Isi Database <code class="text-sm">tb_fingerprint</code> (<?= count($summary) ?> PIN)</h4>
<div class="overflow-x-auto border border-gray-200 rounded-xl">
<table class="w-full text-sm text-left">
<thead class="bg-gray-50 border-b border-gray-200">
<tr>
<th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">PIN</th>
<th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Nama</th>
<th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Jml Jari</th>
<th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Backup Terakhir</th>
<th class="py-2 px-3 text-xs font-semibold text-gray-600 uppercase">Aksi</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-100">
<?php if (empty($summary)): ?>
<tr><td colspan="5" class="py-4 px-3 text-center text-gray-400">Belum ada backup. Tarik dari mesin dulu.</td></tr>
<?php else: foreach ($summary as $s): ?>
<tr class="hover:bg-gray-50">
<td class="py-2 px-3 font-bold"><?= htmlspecialchars($s['pin']) ?></td>
<td class="py-2 px-3"><?= htmlspecialchars($s['nama'] ?? '-') ?></td>
<td class="py-2 px-3"><span class="px-2 py-1 text-xs font-bold rounded-full bg-indigo-100 text-indigo-800"><?= $s['jml'] ?> jari</span></td>
<td class="py-2 px-3 text-gray-500 text-xs"><?= htmlspecialchars($s['terakhir']) ?></td>
<td class="py-2 px-3">
<form method="POST" class="inline" onsubmit="return confirm('Hapus backup PIN <?= htmlspecialchars($s['pin']) ?> dari database? (tidak menghapus di mesin)')">
<input type="hidden" name="pin" value="<?= htmlspecialchars($s['pin']) ?>">
<button type="submit" name="fp_delete" class="text-xs px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-lg font-semibold"><i class="fas fa-trash mr-1"></i>Hapus</button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

</div>
</div>
</div>
