<?php
// Hapus karyawan BY NAMA (PIN/absen tidak dipakai).
// Langkah 1 (GET): tampilkan kandidat user di mesin yang namanya sama -> user centang.
// Langkah 2 (POST): hapus PIN terpilih dari mesin + hapus baris database.
$id = $_GET['id'] ?? '';

include_once __DIR__ . '/../../device_sync_helper.php';
create_sync_log_table($koneksi);

$emp_result = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan = '" . $koneksi->real_escape_string($id) . "' LIMIT 1");
$emp_data = $emp_result ? $emp_result->fetch_assoc() : null;
$no_absen = $emp_data['no_absen'] ?? '';
$nama_karyawan = $emp_data['nama_karyawan'] ?? '';
$dry_run = false;

if (!$emp_data) {
    echo "<div class='p-5 text-center text-gray-500'>Karyawan tidak ditemukan (mungkin sudah dihapus). <a href='?page=karyawan'>Kembali</a></div>";
    return;
}

// ---------- LANGKAH 2: eksekusi hapus ----------
if (isset($_POST['do_delete']) || isset($_POST['db_only'])) {
    $targets = [];
    foreach (($_POST['targets'] ?? []) as $t) {
        $parts = explode(':', $t);
        if (count($parts) < 2) continue;
        $did = trim($parts[0]); $pin = trim($parts[1]); $pin2 = trim($parts[2] ?? '');
        if ($did === '' || $pin === '' || !ctype_digit($did) || !is_numeric($pin)) continue;
        $targets[$did][] = ['pin' => $pin, 'pin2' => $pin2];
    }
    foreach ($targets as $did => $list) {
        $seen = [];
        foreach ($list as $e) { $seen[$e['pin']] = $e; }
        $targets[$did] = array_values($seen);
    }

    $delete_results = [];
    $sync_msg = '';
    if (isset($_POST['db_only'])) {
        $sync_msg = '<br><small>Hanya dihapus dari database, mesin tidak disentuh.</small><br>';
    } elseif (!empty($targets)) {
        $delete_results = delete_karyawan_from_devices($koneksi, $nama_karyawan, $dry_run, $targets);
        $sync_msg = "<br><small><strong>Hasil hapus dari mesin (by nama: '$nama_karyawan'):</strong></small><br>";
        foreach ($delete_results as $key => $result) {
            if ($key === '_dry_run_mode' || $key === '_dry_run_info') continue;
            $device_name = $result['device'] ?? 'Device';
            $status = $result['status'] ?? 'unknown';
            $message = $result['message'] ?? '';
            $icon = 'fa-check-circle text-success';
            if (strpos($status, 'error') !== false) $icon = 'fa-times-circle text-danger';
            if (strpos($status, 'skipped') !== false || strpos($status, 'prevented') !== false || strpos($status, 'warning') !== false) $icon = 'fa-exclamation-triangle text-warning';
            $sync_msg .= "<small><i class='fas $icon'></i> <strong>$device_name:</strong> $message</small><br>";
            if (isset($result['warnings']) && !empty($result['warnings'])) {
                foreach ($result['warnings'] as $warning) {
                    $sync_msg .= "<small class='text-danger'>&nbsp;&nbsp;<i class='bi bi-exclamation-triangle-fill'></i> " . ($warning['message'] ?? json_encode($warning)) . "</small><br>";
                }
            }
            if ($dry_run && isset($result['will_delete']) && !empty($result['will_delete'])) {
                foreach ($result['will_delete'] as $item) {
                    $sync_msg .= "<small class='text-info'>&nbsp;&nbsp;<i class='bi bi-search'></i> Akan hapus: {$item['name']} (PIN: {$item['pin']}) - {$item['reason']}</small><br>";
                }
            }
        }
    } else {
        $sync_msg = '<br><small>Tidak ada yang dipilih — mesin tidak disentuh, database tetap dihapus.</small><br>';
    }

    $sql = $koneksi->query("delete from ms_karyawan where id_karyawan = '$id' ");
    if ($sql) {
        $syncJs = json_encode($sync_msg);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    html: 'Data karyawan berhasil dihapus dari database.' + <?php echo $syncJs; ?>,
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    window.location.href="?page=karyawan";
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// ---------- LANGKAH 1: preview kandidat by nama ----------
$found = find_users_by_name($koneksi, $nama_karyawan);
$total_match = 0;
foreach ($found as $f) $total_match += count($f['matches']);
?>
<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 bg-white">
            <h3 class="text-xl font-bold text-rose-600 m-0"><i class="fas fa-trash-alt mr-2"></i>Hapus Karyawan</h3>
            <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Hapus berdasarkan <strong>nama</strong> (PIN/absen tidak dipakai). Centang user mesin yang mau dihapus.</p>
        </div>
        <div class="p-6">
            <div class="mb-4 p-4 rounded-lg border border-gray-200 bg-gray-50 text-sm">
                <span class="text-gray-500">Karyawan web:</span>
                <strong class="text-gray-800"><?= htmlspecialchars($nama_karyawan) ?></strong>
                <span class="text-gray-400">(No. Absen: <?= htmlspecialchars($no_absen) ?> — hanya info, tidak dipakai hapus)</span>
            </div>

            <?php if ($total_match == 0): ?>
            <div class="mb-4 p-4 rounded-lg border border-amber-200 bg-amber-50 text-sm text-amber-800">
                <i class="fas fa-exclamation-triangle mr-2"></i>Nama '<?= htmlspecialchars($nama_karyawan) ?>' <strong>tidak ditemukan</strong> di mesin manapun yang online. Baris database tetap bisa dihapus.
            </div>
            <?php endif; ?>

            <form method="POST" action="?page=karyawan&aksi=hapus&id=<?= htmlspecialchars($id) ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                    <?php foreach ($found as $did => $f): ?>
                    <div class="p-4 rounded-xl border <?= !empty($f['matches']) ? 'border-rose-200 bg-rose-50/50' : 'border-gray-200 bg-white' ?>">
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($f['device']) ?> <span class="text-xs text-gray-400 font-normal"><?= htmlspecialchars($f['ip']) ?></span></p>
                            <?php if (!$f['online']): ?><span class="px-2 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-500"><?= htmlspecialchars($f['message']) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($f['matches'])): ?>
                            <?php foreach ($f['matches'] as $m): ?>
                            <label class="flex items-center gap-2 p-2 bg-white rounded-lg border border-rose-100 mb-1.5 cursor-pointer text-sm">
                                <input type="checkbox" name="targets[]" value="<?= htmlspecialchars($did . ':' . $m['pin'] . ':' . ($m['pin2'] ?? '')) ?>" checked class="w-4 h-4 text-rose-600 rounded">
                                <span><strong><?= htmlspecialchars($m['name']) ?></strong> <span class="text-gray-500">PIN <?= htmlspecialchars($m['pin']) ?><?= ($m['pin2'] ?? '') !== '' ? ' / PIN2 ' . htmlspecialchars($m['pin2']) : '' ?></span><?php if (!empty($m['priv']) && $m['priv'] !== '0'): ?> <span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-700">ADMIN MESIN — turunkan dulu di mesin bila hapus gagal</span><?php endif; ?></span>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-gray-400"><?= $f['online'] ? 'Tidak ada nama yang cocok di mesin ini.' : htmlspecialchars($f['message']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex flex-col md:flex-row gap-2 justify-between items-stretch md:items-center pt-4 border-t border-gray-100">
                    <a href="?page=karyawan" class="inline-flex justify-center items-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-arrow-left mr-2"></i>Batal</a>
                    <div class="flex flex-col md:flex-row gap-2">
                        <button type="submit" name="db_only" value="1" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-database mr-2"></i>Hapus DB Saja</button>
                        <button type="submit" name="do_delete" value="1" onclick="return confirm('Hapus user yang dicentang dari mesin + hapus dari database?')" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-trash mr-2"></i>Hapus yang Dipilih</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
