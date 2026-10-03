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
if (!empty($sweep_cfg['last_run'])) {
    $next_run = date('d/m/Y H:i', strtotime($sweep_cfg['last_run']) + $sweep_cfg['interval_jam'] * 3600);
}
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
<p class="text-sm text-gray-500 mb-4">Sweep otomatis cek sidik baru tiap interval — tanpa klik apa-apa. Syarat: Task Scheduler di PC server jalan tiap 30 menit (<code>auto_fingerprint_sweep.bat</code>).</p>
<form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
<select name="sweep_enabled" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<option value="0" <?= !$sweep_cfg['enabled'] ? 'selected' : '' ?>>Mati</option>
<option value="1" <?= $sweep_cfg['enabled'] ? 'selected' : '' ?>>Nyala</option>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-gray-700 mb-1.5">Tiap berapa jam</label>
<select name="sweep_interval" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<?php foreach ([1,2,4,6,12,24] as $h): ?>
<option value="<?= $h ?>" <?= $sweep_cfg['interval_jam'] == $h ? 'selected' : '' ?>><?= $h ?> jam</option>
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
</div>
</form>
<p class="text-xs text-gray-400 mt-3 mb-0">Terakhir jalan: <strong><?= htmlspecialchars($sweep_cfg['last_run'] ?? '-') ?></strong> | Berikutnya: <strong><?= htmlspecialchars($next_run) ?></strong></p>
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
<label class="block text-sm font-semibold text-gray-700 mb-1.5">PIN / No. Absen</label>
<input list="pinList" name="pin" required placeholder="cth: 45" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
<datalist id="pinList">
<?php foreach ($karyawan_list as $k): ?><option value="<?= htmlspecialchars($k['no_absen']) ?>"><?= htmlspecialchars($k['nama_karyawan']) ?></option><?php endforeach; ?>
</datalist>
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
