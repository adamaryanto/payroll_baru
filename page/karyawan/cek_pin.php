<?php
// Cek PIN / Nama user langsung di mesin (baca GetAllUserInfo per mesin).
// Tarik manual: tulis PIN, pilih SATU mesin, web cek live ke mesin itu dulu.
// Kalau PIN ada -> tarik PIN + nama lengkap, simpan ke master & baris TIDAK DALAM RENCANA.
include_once __DIR__ . '/../../device_sync_helper.php';

// Cek PIN hanya untuk Admin Master.
if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin master') {
    echo '<div class="container-fluid px-4 mt-4"><div class="p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-800 font-medium">Akses ditolak. Menu Cek PIN hanya untuk Admin Master.</div></div>';
    return;
}

$devices = get_all_devices($koneksi);
$q = trim((string)($_REQUEST['q'] ?? ''));
$target_did = (int)($_REQUEST['id_mesin'] ?? 0);
$pull_msg = '';
$pull_err = '';
$hasil = null;

// Samakan PIN dengan/tanpa nol di depan (13 == 0013), tapi jangan samakan "0" dengan kosong.
$pin_eq = function ($a, $b) {
    $a = trim((string)$a); $b = trim((string)$b);
    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;
    $la = ltrim($a, '0'); $lb = ltrim($b, '0');
    return ($la !== '' && $la === $lb);
};
$match_user = function ($pin, $pin2, $name) use (&$q, $pin_eq) {
    if ($q === '') return true;
    if ($pin_eq($pin, $q) || $pin_eq($pin2, $q)) return true;
    return $name !== '' && stripos($name, $q) !== false;
};

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tarik_nama'])) $action = 'tarik';
    elseif (isset($_POST['cek'])) $action = 'cek';
}
$do_read = ($action !== '' && $q !== '');
if ($action !== '' && $q === '') {
    $pull_err = 'Tulis PIN terlebih dahulu, lalu pilih mesin.';
}

if ($do_read) {
    // Selalu baca LIVE dari mesin yang dipilih (bukan cache).
    $full = get_device_users_full($koneksi, true, $target_did, true);
    $u_all = is_array($full['u'] ?? null) ? $full['u'] : [];
    $p2_all = is_array($full['p2'] ?? null) ? $full['p2'] : [];

    // Nama cadangan tersimpan (hasil tarik sebelumnya + master) untuk layar hasil.
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

    $hasil = [];
    $matched_count = 0;

    if ($action === 'tarik') {
        ensure_karyawan_pin_table($koneksi);
        $n_nama = 0; $n_row = 0; $n_baru = 0; $n_edit = 0; $n_tanpa_nama = 0;
    }

    foreach ($devices as $dev) {
        $did = (int)($dev['id_mesin'] ?? 0);
        if ($target_did > 0 && $did !== $target_did) continue;
        $r = ['device' => $dev['nama_mesin'] ?? ('Mesin ' . $did),
            'ip' => !empty($dev['ip_mesin']) ? ($dev['ip_mesin'] . ':' . ($dev['port'] ?? '4370')) : '-',
            'online' => false, 'users' => [], 'total' => 0, 'msg' => ''];
        if (empty($dev['ip_mesin'])) { $r['msg'] = 'IP tidak dikonfigurasi'; $hasil[$did] = $r; continue; }
        $r['online'] = test_device_connection($dev['ip_mesin'], $dev['port']);
        if (!$r['online']) { $r['msg'] = 'Device offline'; $hasil[$did] = $r; continue; }

        $pins = is_array($u_all[$did] ?? null) ? $u_all[$did] : [];
        if (is_array($db_pin_names[$did] ?? null)) {
            foreach ($db_pin_names[$did] as $dp => $dn) if (!isset($pins[$dp])) $pins[$dp] = $dn;
        }
        foreach ($pins as $pin => $name) {
            $pin = trim((string)$pin);
            $name = trim((string)$name);
            if ($pin === '') continue;
            $pin2 = trim((string)($p2_all[$did][$pin] ?? ''));
            if ($name === '' && isset($db_pin_names[$did][$pin])) $name = $db_pin_names[$did][$pin];
            if ($name === '' && isset($master_names[$pin])) $name = $master_names[$pin];
            if ($name === '' && $pin2 !== '' && isset($master_names[$pin2])) $name = $master_names[$pin2];
            $r['total']++;
            if (!$match_user($pin, $pin2, $name)) continue;

            $r['users'][] = ['pin' => $pin, 'pin2' => $pin2, 'name' => $name];
            $matched_count++;

            if ($action !== 'tarik') continue;

            // ---- Tarik manual: PIN ada di mesin ini -> simpan nama ----
            $pin_e = $koneksi->real_escape_string($pin);
            $nm_e = $koneksi->real_escape_string($name);
            $p2_e = ($pin2 !== '' && $pin2 !== $pin) ? $koneksi->real_escape_string($pin2) : '';

            // Cari karyawan di master berdasarkan PIN / PIN2 (termasuk variasi nol depan).
            $cands = [$pin];
            if (ltrim($pin, '0') !== '') $cands[] = ltrim($pin, '0');
            if (ctype_digit($pin)) $cands[] = str_pad($pin, 4, '0', STR_PAD_LEFT);
            if ($p2_e !== '') { $cands[] = $pin2; if (ltrim($pin2, '0') !== '') $cands[] = ltrim($pin2, '0'); }
            $cands = array_values(array_unique(array_filter(array_map(function ($x) use ($koneksi) {
                return $koneksi->real_escape_string(trim((string)$x));
            }, $cands), function ($x) { return $x !== ''; })));
            $kid = 0; $nm_web = '';
            if (!empty($cands)) {
                $in_pin = "'" . implode("','", $cands) . "'";
                $qe = $koneksi->query("SELECT id_karyawan, nama_karyawan FROM ms_karyawan WHERE no_absen IN ($in_pin) LIMIT 1");
                $emp = $qe ? $qe->fetch_assoc() : null;
                if ($emp) { $kid = (int)$emp['id_karyawan']; $nm_web = trim((string)$emp['nama_karyawan']); }
            }
            // Nama di master yang valid = nama lengkap -> dipakai.
            if ($nm_web !== '' && !preg_match('/^User PIN[0-9]*$/i', $nm_web)) $name = $nm_web;
            $nm_e = $koneksi->real_escape_string($name);

            if ($kid > 0) {
                if ($nm_web === '' || preg_match('/^User PIN[0-9]*$/i', $nm_web)) {
                    if ($name !== '' && $koneksi->query("UPDATE ms_karyawan SET nama_karyawan = '$nm_e' WHERE id_karyawan = $kid")) $n_edit++;
                }
            } elseif ($name !== '') {
                // Jangan bikin dobel kalau nama itu sudah ada dengan nomor absen lain.
                $cekNama = $koneksi->query("SELECT id_karyawan FROM ms_karyawan WHERE LOWER(TRIM(nama_karyawan)) = LOWER('$nm_e') LIMIT 1");
                if ($cekNama && $cekNama->num_rows > 0) {
                    $kid = (int)$cekNama->fetch_assoc()['id_karyawan'];
                } else {
                    if ($koneksi->query("INSERT INTO ms_karyawan (no_absen, nama_karyawan, status_karyawan, id_departmen, id_jabatan, tempat_lahir, agama, jenis_kelamin, no_ktp, alamat_ktp, alamat_tinggal, no_bpjs, id_jadwal, id_sub_department, tgl_aktif) VALUES ('$pin_e','$nm_e','Aktif','1','0','','Islam','Laki-laki','','','','','1','1',CURDATE())")) {
                        $kid = (int)$koneksi->insert_id;
                        $n_baru++;
                    }
                }
            }

            if ($name === '') {
                $n_tanpa_nama++;
                continue;
            }

            $koneksi->query("INSERT INTO ms_karyawan_pin (id_mesin, pin, id_karyawan, nama_mesin, tgl_updt) VALUES ($did, '$pin_e', $kid, '$nm_e', NOW())
                ON DUPLICATE KEY UPDATE id_karyawan = VALUES(id_karyawan), nama_mesin = VALUES(nama_mesin), tgl_updt = NOW()");
            $n_nama++;

            // Ganti label placeholder "PIN xx" pada baris TIDAK DALAM RENCANA / realisasi.
            $labels = ["PIN $pin", "PIN $pin (Mesin $did)"];
            if ($pin2 !== '' && $pin2 !== $pin) {
                $labels[] = "PIN $pin2";
                $labels[] = "PIN $pin2 (Mesin $did)";
            }
            $in = "'" . implode("','", array_map(function ($x) use ($koneksi) { return $koneksi->real_escape_string($x); }, $labels)) . "'";
            $koneksi->query("UPDATE tb_realisasi_detail SET nama_karyawan_manual = '$nm_e' WHERE nama_karyawan_manual IN ($in)");
            $n_row += max(0, (int)$koneksi->affected_rows);
        }
        $r['msg'] = count($r['users']) . ' cocok dari ' . $r['total'] . ' user';
        $hasil[$did] = $r;
    }

    if ($action === 'tarik') {
        if ($matched_count === 0) {
            $pull_err = 'PIN "' . htmlspecialchars($q) . '" TIDAK ditemukan di mesin yang dipilih. Tidak ada data yang diubah.';
        } elseif ($n_nama === 0) {
            $pull_err = 'PIN ditemukan, tapi nama kosong di mesin dan tidak ada nama di master. Coba isi nama karyawan dulu di master.';
        } else {
            $pull_msg = "Berhasil tarik $n_nama PIN/nama" . ($q !== '' ? " untuk \"$q\"" : '') . ": $n_baru karyawan baru, $n_edit nama kosong diisi. $n_row baris TIDAK DALAM RENCANA diganti nama aslinya."
                . ($n_tanpa_nama > 0 ? " ($n_tanpa_nama PIN tanpa nama dilewati)" : '');
        }
    }
}
?>
<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fas fa-search mr-2"></i>Cek PIN / Tarik Nama Manual</h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Tulis PIN, pilih mesin, lalu cek live ke mesin sebelum menarik nama</p>
            </div>
            <a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors"><i class="fas fa-arrow-left mr-1.5"></i> Kembali</a>
        </div>
        <div class="p-6">
            <?php if ($pull_msg !== ''): ?>
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-sm text-emerald-800 font-medium"><?= $pull_msg ?></div>
            <?php endif; ?>
            <?php if ($pull_err !== ''): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800 font-medium"><?= $pull_err ?></div>
            <?php endif; ?>

            <form method="POST" class="flex flex-col md:flex-row gap-2 mb-2">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" required placeholder="Tulis PIN yang mau ditarik… mis: 13" class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                <select name="id_mesin" class="px-3 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm bg-white">
                    <option value="0">Semua Mesin</option>
                    <?php foreach ($devices as $dev): ?>
                    <option value="<?= (int)$dev['id_mesin'] ?>" <?= $target_did === (int)$dev['id_mesin'] ? 'selected' : '' ?>><?= htmlspecialchars($dev['nama_mesin'] ?? ('Mesin ' . $dev['id_mesin'])) ?><?= !empty($dev['ip_mesin']) ? ' (' . htmlspecialchars($dev['ip_mesin']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="cek" value="1" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-search mr-2"></i>Cek Live</button>
                <button type="submit" name="tarik_nama" value="1" onclick="return confirm('Cek PIN ini ke mesin lalu tarik nama? Nama di web dan baris TIDAK DALAM RENCANA akan diperbarui.');" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-5 rounded-lg text-sm"><i class="fas fa-download mr-2"></i>Tarik PIN &amp; Nama</button>
            </form>
            <p class="text-xs text-gray-400 mb-6">Pilih <strong>satu mesin</strong> agar pengecekan fokus. Web akan cek live ke mesin dulu; kalau PIN tidak ada, tidak ada data yang diubah.</p>

            <?php if (is_array($hasil)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($hasil as $did => $r): ?>
                <div class="p-4 rounded-xl border <?= $r['online'] ? (count($r['users']) ? 'border-green-200 bg-green-50/50' : 'border-gray-200 bg-white') : 'border-gray-200 bg-gray-50' ?>">
                    <div class="flex items-center justify-between mb-1">
                        <p class="font-bold text-sm text-gray-800"><?= htmlspecialchars($r['device']) ?> <span class="text-xs text-gray-400 font-normal"><?= htmlspecialchars($r['ip']) ?></span></p>
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $r['online'] ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500' ?>"><?= $r['online'] ? 'ONLINE' : 'OFFLINE' ?></span>
                    </div>
                    <p class="text-xs mb-2 <?= ($r['online'] && !count($r['users'])) ? 'text-red-600 font-semibold' : 'text-gray-500' ?>"><?= htmlspecialchars($r['msg']) ?></p>
                    <?php if (!empty($r['users'])): ?>
                    <table class="w-full text-xs bg-white rounded-lg overflow-hidden border border-gray-200">
                        <thead class="bg-gray-50"><tr><th class="py-1.5 px-2 text-left text-gray-500 uppercase">PIN</th><th class="py-1.5 px-2 text-left text-gray-500 uppercase">PIN2</th><th class="py-1.5 px-2 text-left text-gray-500 uppercase">Nama</th><th class="py-1.5 px-2 text-left text-gray-500 uppercase">Hak</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($r['users'] as $u): ?>
                            <tr>
                                <td class="py-1.5 px-2 font-bold text-indigo-700"><?= htmlspecialchars($u['pin'] !== '' ? $u['pin'] : '-') ?></td>
                                <td class="py-1.5 px-2 text-gray-600"><?= htmlspecialchars($u['pin2'] !== '' ? $u['pin2'] : '-') ?></td>
                                <td class="py-1.5 px-2 font-semibold"><?= htmlspecialchars($u['name'] !== '' ? $u['name'] : '(tanpa nama)') ?></td>
                                <td class="py-1.5 px-2"><span class="text-gray-400">User</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <?php if (count($r['users'])): ?>
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                        <input type="hidden" name="id_mesin" value="<?= (int)$did ?>">
                        <button type="submit" name="tarik_nama" value="1" class="w-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-semibold py-1.5 px-3 rounded-lg"><i class="fas fa-download mr-1"></i> Tarik PIN &amp; Nama dari Mesin Ini</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
