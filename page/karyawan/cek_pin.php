<?php
// Cek PIN / Nama user langsung di mesin (baca GetAllUserInfo per mesin).
// Bisa TARIK NAMA manual: isi PIN/nama, pilih satu mesin atau semua, lalu Tarik.
// Nama disimpan ke ms_karyawan_pin dan baris TIDAK DALAM RENCANA "PIN xx" diganti.
include_once __DIR__ . '/../../device_sync_helper.php';

$devices = get_all_devices($koneksi);
$q = trim($_REQUEST['q'] ?? '');
$target_did = (int)($_REQUEST['id_mesin'] ?? 0);
$force = false;
$pull_msg = '';

$match_user = function ($pin, $pin2, $name) use (&$q) {
    if ($q === '') return true;
    return $pin === $q || ($pin2 !== '' && $pin2 === $q) || ($name !== '' && stripos($name, $q) !== false);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tarik_nama'])) {
    $force = true;
    $only_did = (int)($_POST['id_mesin'] ?? 0);
    ensure_karyawan_pin_table($koneksi);
    $full = get_device_users_full($koneksi, true);
    $u_all = is_array($full['u'] ?? null) ? $full['u'] : [];
    $p2_all = is_array($full['p2'] ?? null) ? $full['p2'] : [];
    $pinmap = karyawan_pin_map($koneksi);
    $n_nama = 0; $n_row = 0; $n_baru = 0; $n_edit = 0;
    foreach ($u_all as $did => $pins) {
        $did = (int)$did;
        if ($did <= 0 || !is_array($pins)) continue;
        if ($only_did > 0 && $did !== $only_did) continue;
        foreach ($pins as $pin => $nm) {
            $pin = trim((string)$pin); $nm = trim((string)$nm);
            if ($pin === '' || $nm === '') continue;
            if (preg_match('/^User PIN[0-9]+$/i', $nm)) continue;
            $p2 = trim((string)($p2_all[$did][$pin] ?? ''));
            if (!$match_user($pin, $p2, $nm)) continue;
            $pin_e = $koneksi->real_escape_string($pin);
            $nm_e = $koneksi->real_escape_string($nm);
            $p2_e = ($p2 !== '' && $p2 !== $pin) ? $koneksi->real_escape_string($p2) : '';
            // Langsung ke ms_karyawan, sama seperti Tarik Data.
            $kid = 0;
            $where_pin = "no_absen = '$pin_e'" . ($p2_e !== '' ? " OR no_absen = '$p2_e'" : '');
            $qe = $koneksi->query("SELECT id_karyawan, nama_karyawan FROM ms_karyawan WHERE ($where_pin) LIMIT 1");
            $emp = $qe ? $qe->fetch_assoc() : null;
            if ($emp) {
                $kid = (int)$emp['id_karyawan'];
                $nm_web = trim((string)$emp['nama_karyawan']);
                // Nama di web kosong / placeholder -> isi dari nama mesin.
                if ($nm_web === '' || preg_match('/^User PIN[0-9]*$/i', $nm_web)) {
                    if ($koneksi->query("UPDATE ms_karyawan SET nama_karyawan = '$nm_e' WHERE id_karyawan = $kid")) $n_edit++;
                }
            } else {
                // Jangan bikin dobel kalau nama itu sudah ada dengan nomor absen lain.
                $cekNama = $koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE LOWER(nama_karyawan) = LOWER('$nm_e') LIMIT 1");
                if (!($cekNama && $cekNama->num_rows > 0)) {
                    if ($koneksi->query("INSERT INTO ms_karyawan (no_absen, nama_karyawan, status_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, no_bpjs, id_jadwal, id_sub_department, tgl_aktif) VALUES ('$pin_e','$nm_e','Aktif','1','0','','Islam','Laki-laki','','','','','1','1',CURDATE())")) {
                        $kid = (int)$koneksi->insert_id;
                        $n_baru++;
                    }
                }
            }
            $koneksi->query("INSERT INTO ms_karyawan_pin (id_mesin, pin, id_karyawan, nama_mesin, tgl_updt) VALUES ($did, '$pin_e', $kid, '$nm_e', NOW())
                ON DUPLICATE KEY UPDATE id_karyawan = VALUES(id_karyawan), nama_mesin = VALUES(nama_mesin), tgl_updt = NOW()");
            $n_nama++;
            $labels = ["PIN $pin", "PIN $pin (Mesin $did)"];
            if ($p2 !== '' && $p2 !== $pin) {
                $labels[] = "PIN $p2";
                $labels[] = "PIN $p2 (Mesin $did)";
            }
            $in = "'" . implode("','", array_map(function ($x) use ($koneksi) { return $koneksi->real_escape_string($x); }, $labels)) . "'";
            $koneksi->query("UPDATE tb_realisasi_detail SET nama_karyawan_manual = '$nm_e' WHERE nama_karyawan_manual IN ($in)");
            $n_row += max(0, (int)$koneksi->affected_rows);
        }
    }
    $pull_msg = "Ditarik $n_nama nama" . ($q !== '' ? " untuk \"$q\"" : '') . ": $n_baru karyawan baru, $n_edit nama kosong diisi. $n_row baris TIDAK DALAM RENCANA diganti nama aslinya.";
}

$hasil = null;
// Nama cadangan dari hasil "Tarik Nama" sebelumnya + master karyawan,
// supaya PIN yang tidak ada di daftar mesin tetap tampil namanya.
$db_pin_names = [];
$qdb = $koneksi->query("SELECT id_mesin, pin, nama_mesin FROM ms_karyawan_pin WHERE nama_mesin IS NOT NULL AND nama_mesin <> ''");
while ($qdb && ($rdb = $qdb->fetch_assoc())) {
    $d_db = (int)$rdb['id_mesin']; $p_db = trim((string)$rdb['pin']); $n_db = trim((string)$rdb['nama_mesin']);
    if ($p_db !== '' && $n_db !== '') $db_pin_names[$d_db][$p_db] = $n_db;
}
$master_names = [];
$qm = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE nama_karyawan NOT LIKE 'User PIN%'");
while ($qm && ($rm = $qm->fetch_assoc())) {
    $p_m = trim((string)$rm['no_absen']); $n_m = trim((string)$rm['nama_karyawan']);
    if ($p_m !== '' && $n_m !== '') $master_names[$p_m] = $n_m;
}
if ($q !== '') {
    $hasil = [];
    if (!$force) $full = get_device_users_full($koneksi, false);
    $u_all = is_array($full['u'] ?? null) ? $full['u'] : [];
    $p2_all = is_array($full['p2'] ?? null) ? $full['p2'] : [];
    foreach ($devices as $dev) {
        $did = (int)($dev['id_mesin'] ?? 0);
        if ($target_did > 0 && $did !== $target_did) continue;
        $r = ['device' => $dev['nama_mesin'] ?? 'Mesin', 'ip' => !empty($dev['ip_mesin']) ? ($dev['ip_mesin'] . ':' . ($dev['port'] ?? '4370')) : '-',
            'online' => false, 'users' => [], 'total' => 0, 'msg' => ''];
        if (empty($dev['ip_mesin'])) { $r['msg'] = 'IP tidak dikonfigurasi'; $hasil[$did] = $r; continue; }
        $r['online'] = test_device_connection($dev['ip_mesin'], $dev['port']);
        if (!$r['online']) { $r['msg'] = 'Device offline'; $hasil[$did] = $r; continue; }
        $pins = is_array($u_all[$did] ?? null) ? $u_all[$did] : [];
        // Tambahkan PIN yang namanya sudah pernah ditarik walau mesin tidak mengirimnya.
        if (is_array($db_pin_names[$did] ?? null)) {
            foreach ($db_pin_names[$did] as $dp => $dn) if (!isset($pins[$dp])) $pins[$dp] = $dn;
        }
        if (empty($pins)) {
            $r['msg'] = 'Belum ada nama terbaca — klik "Tarik Nama"';
            $hasil[$did] = $r;
            continue;
        }
        foreach ($pins as $pin => $name) {
            $pin = trim((string)$pin);
            $name = trim((string)$name);
            $pin2 = trim((string)($p2_all[$did][$pin] ?? ''));
            // Nama kosong di mesin: pakai nama tersimpan / master supaya tidak "-".
            if ($name === '' && isset($db_pin_names[$did][$pin])) $name = $db_pin_names[$did][$pin];
            if ($name === '' && isset($master_names[$pin])) $name = $master_names[$pin];
            if ($name === '' && $pin2 !== '' && isset($master_names[$pin2])) $name = $master_names[$pin2];
            $r['total']++;
            if ($match_user($pin, $pin2, $name)) {
                $r['users'][] = ['pin' => $pin, 'pin2' => $pin2, 'name' => $name];
            }
        }
        $r['msg'] = count($r['users']) . ' cocok dari ' . $r['total'] . ' user';
        $hasil[$did] = $r;
    }
}
?>
<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fas fa-search mr-2"></i>Cek PIN / User di Mesin</h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Cari PIN / PIN2 / nama, lalu tarik namanya dari mesin</p>
            </div>
            <a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors"><i class="fas fa-arrow-left mr-1.5"></i> Kembali</a>
        </div>
        <div class="p-6">
            <?php if ($pull_msg !== ''): ?>
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-sm text-emerald-800 font-medium"><?= htmlspecialchars($pull_msg) ?></div>
            <?php endif; ?>
            <form method="POST" class="flex flex-col md:flex-row gap-2 mb-2">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik PIN / PIN2 / nama… mis: 13 (kosongkan = semua)" class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                <select name="id_mesin" class="px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm bg-white">
                    <option value="0">Semua Mesin</option>
                    <?php foreach ($devices as $dev): ?>
                    <option value="<?= (int)$dev['id_mesin'] ?>" <?= $target_did === (int)$dev['id_mesin'] ? 'selected' : '' ?>><?= htmlspecialchars($dev['nama_mesin'] ?? ('Mesin ' . $dev['id_mesin'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="cek" value="1" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-search mr-2"></i>Cek</button>
                <button type="submit" name="tarik_nama" value="1" onclick="return confirm('Tarik nama dari mesin? Nama di web dan baris TIDAK DALAM RENCANA akan diperbarui.');" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-download mr-2"></i>Tarik Nama</button>
            </form>
            <p class="text-xs text-gray-400 mb-6">Isi PIN/nama + pilih satu mesin atau <strong>Semua Mesin</strong>, lalu klik <strong>Tarik Nama</strong>. Kosongkan kolom untuk menarik semua nama.</p>

            <?php if (is_array($hasil)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($hasil as $did => $r): ?>
                <div class="p-4 rounded-xl border <?= $r['online'] ? (count($r['users']) ? 'border-green-200 bg-green-50/50' : 'border-gray-200 bg-white') : 'border-gray-200 bg-gray-50' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($r['device']) ?> <span class="text-xs text-gray-400 font-normal"><?= htmlspecialchars($r['ip']) ?></span></p>
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $r['online'] ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500' ?>"><?= $r['online'] ? 'ONLINE' : 'OFFLINE' ?></span>
                    </div>
                    <p class="text-xs text-gray-500 mb-2"><?= htmlspecialchars($r['msg']) ?></p>
                    <?php if (!empty($r['users'])): ?>
                    <table class="w-full text-xs bg-white rounded-lg overflow-hidden border border-gray-200">
                        <thead class="bg-gray-50"><tr><th class="py-1.5 px-2 text-left text-gray-500 uppercase">PIN</th><th class="py-1.5 px-2 text-left text-gray-500 uppercase">PIN2</th><th class="py-1.5 px-2 text-left text-gray-500 uppercase">Nama</th><th class="py-1.5 px-2 text-left text-gray-500 uppercase">Hak</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($r['users'] as $u): ?>
                            <tr>
                                <td class="py-1.5 px-2 font-bold text-indigo-700"><?= htmlspecialchars($u['pin'] !== '' ? $u['pin'] : '-') ?></td>
                                <td class="py-1.5 px-2 text-gray-600"><?= htmlspecialchars($u['pin2'] !== '' ? $u['pin2'] : '-') ?></td>
                                <td class="py-1.5 px-2 font-semibold"><?= htmlspecialchars($u['name'] !== '' ? $u['name'] : '-') ?></td>
                                <td class="py-1.5 px-2"><span class="text-gray-400">User</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                        <input type="hidden" name="id_mesin" value="<?= (int)$did ?>">
                        <button type="submit" name="tarik_nama" value="1" class="w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-semibold py-1.5 px-3 rounded-lg"><i class="fas fa-download mr-1"></i> Tarik Nama Mesin Ini</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
