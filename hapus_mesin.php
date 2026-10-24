<?php
// Hapus user dari MESIN berdasarkan NAMA, tanpa butuh data di ms_karyawan.
// Berguna kalau data DB sudah terlanjur terhapus tapi user masih tertinggal di mesin.
// Buka setelah login: https://domain/hapus_mesin.php
session_start();
if (empty($_SESSION['iduser'])) {
    http_response_code(403);
    exit('Login dulu di aplikasi, lalu buka halaman ini lagi.');
}
set_time_limit(0);
include __DIR__ . '/koneksi.php';
include __DIR__ . '/device_sync_helper.php';

$nama = trim((string)($_POST['nama'] ?? ''));
$mode = (string)($_POST['mode'] ?? '');
$found = null;
$results = null;

if ($nama !== '' && $mode === 'check') {
    $found = function_exists('find_users_by_name') ? find_users_by_name($koneksi, $nama) : [];
} elseif ($nama !== '' && $mode === 'delete') {
    $results = delete_karyawan_from_devices($koneksi, $nama, false);
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hapus User di Mesin</title>
<style>
body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f3f4f6;margin:0;padding:24px;color:#111827}
.box{max-width:900px;margin:0 auto;background:#fff;border-radius:10px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:16px}
h2{margin:0 0 8px;font-size:18px}
p.muted{color:#6b7280;margin:6px 0 14px;font-size:13px}
input[type=text]{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;box-sizing:border-box}
.btns{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
button{padding:10px 16px;border:0;border-radius:8px;font-size:14px;cursor:pointer}
.check{background:#e5e7eb;color:#111827}
.del{background:#dc2626;color:#fff}
table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
th,td{border:1px solid #e5e7eb;padding:8px;text-align:left;vertical-align:top}
th{background:#f9fafb}
.s-success{color:#047857;font-weight:600}
.s-error{color:#b91c1c;font-weight:600}
.s-warning{color:#b45309;font-weight:600}
.s-skipped{color:#6b7280}
</style>
</head>
<body>
<div class="box">
  <h2>Hapus User di Mesin (by Nama)</h2>
  <p class="muted">
    Halaman ini menghapus user dari mesin berdasarkan <b>nama yang tertulis di mesin</b>,
    tidak memerlukan data di <code>ms_karyawan</code>. Cocok untuk membersihkan sisa user
    yang sudah terlanjur dihapus dari database. Nama harus sama persis (beda huruf besar/kecil tidak masalah).
  </p>
  <form method="post" onsubmit="return true;">
    <input type="text" name="nama" value="<?= h($nama) ?>" placeholder="Nama karyawan persis seperti di mesin" required>
    <div class="btns">
      <button class="check" type="submit" name="mode" value="check">Cek di Mesin</button>
      <button class="del" type="submit" name="mode" value="delete"
        onclick="return confirm('Hapus user dengan nama ini dari SEMUA mesin yang online? Tindakan ini tidak bisa dibatalkan.');">
        Hapus dari Mesin
      </button>
    </div>
  </form>
</div>

<?php if (is_array($found)): ?>
<div class="box">
  <h2>Hasil Cek: <?= h($nama) ?></h2>
  <table>
    <tr><th>Mesin</th><th>Status</th><th>PIN yang cocok</th></tr>
    <?php foreach ($found as $did => $d): ?>
      <?php
        $online = !empty($d['online']);
        $matches = is_array($d['matches'] ?? null) ? $d['matches'] : [];
        $pins = [];
        foreach ($matches as $m) {
            $p = trim((string)($m['pin'] ?? ''));
            $p2 = trim((string)($m['pin2'] ?? ''));
            if ($p !== '') $pins[] = 'PIN ' . $p . ($p2 !== '' ? ' / PIN2 ' . $p2 : '');
        }
      ?>
      <tr>
        <td><?= h($d['device'] ?? $did) ?><br><span class="muted"><?= h($d['ip'] ?? '') ?></span></td>
        <td><?= $online ? '<span class="s-success">online</span>' : '<span class="s-error">offline</span>' ?></td>
        <td><?= $pins ? h(implode(', ', $pins)) : '<span class="muted">tidak ada yang cocok</span>' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if (is_array($results)): ?>
<div class="box">
  <h2>Hasil Hapus: <?= h($nama) ?></h2>
  <table>
    <tr><th>Mesin</th><th>Status</th><th>Keterangan</th></tr>
    <?php foreach ($results as $did => $r): ?>
      <?php if ($did === '_dry_run_mode' || $did === '_dry_run_info') continue; ?>
      <?php $st = (string)($r['status'] ?? ''); ?>
      <tr>
        <td><?= h($r['device'] ?? $did) ?></td>
        <td><span class="s-<?= h($st ?: 'skipped') ?>"><?= h($st ?: '-') ?></span></td>
        <td><?= h($r['message'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
</body>
</html>
