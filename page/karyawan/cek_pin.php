<?php
// Cek PIN / Nama user langsung di mesin (baca GetAllUserInfo per mesin).
// Buat mastiin PIN asli di mesin (bukan nomor urut di layar) sebelum hapus/manual.
include_once __DIR__ . '/../../device_sync_helper.php';

$q = trim($_GET['q'] ?? '');
$hasil = null;
if ($q !== '') {
    $hasil = [];
    foreach (get_all_devices($koneksi) as $dev) {
        $r = ['device' => $dev['nama_mesin'] ?? 'Mesin', 'ip' => !empty($dev['ip_mesin']) ? ($dev['ip_mesin'] . ':' . ($dev['port'] ?? '4370')) : '-',
            'online' => false, 'users' => [], 'total' => 0, 'msg' => ''];
        if (empty($dev['ip_mesin'])) { $r['msg'] = 'IP tidak dikonfigurasi'; $hasil[$dev['id_mesin']] = $r; continue; }
        if (!test_device_connection($dev['ip_mesin'], $dev['port'])) { $r['msg'] = 'Device offline'; $hasil[$dev['id_mesin']] = $r; continue; }
        $comm = $dev['comm_key'] ?? 0;
        $soap = "<GetAllUserInfo><ArgComKey xsi:type=\"xsd:integer\">{$comm}</ArgComKey></GetAllUserInfo>";
        $resp = send_soap_request($dev['ip_mesin'], $dev['port'], $soap);
        if (!$resp) { $r['msg'] = 'GetAllUserInfo gagal dibaca'; $hasil[$dev['id_mesin']] = $r; continue; }
        $r['online'] = true;
        $pos = 0;
        while (($s = strpos($resp, '<Row>', $pos)) !== false && ($e = strpos($resp, '</Row>', $s)) !== false) {
            $row = substr($resp, $s + 5, $e - $s - 5);
            $pin = $pin2 = $name = $priv = '';
            if (preg_match('/<PIN>([^<]*)<\/PIN>/', $row, $m)) $pin = trim($m[1]);
            if (preg_match('/<PIN2>([^<]*)<\/PIN2>/', $row, $m)) $pin2 = trim($m[1]);
            if (preg_match('/<Name>([^<]*)<\/Name>/', $row, $m)) $name = trim($m[1]);
            if (preg_match('/<Privilege>([^<]*)<\/Privilege>/', $row, $m)) $priv = trim($m[1]);
            if ($pin === '' && $name === '') { $pos = $e + 6; continue; }
            $r['total']++;
            if ($pin === $q || $pin2 === $q || ($name !== '' && stripos($name, $q) !== false)) {
                $r['users'][] = ['pin' => $pin, 'pin2' => $pin2, 'name' => $name, 'priv' => $priv];
            }
            $pos = $e + 6;
        }
        $r['msg'] = count($r['users']) . ' cocok dari ' . $r['total'] . ' user';
        $hasil[$dev['id_mesin']] = $r;
    }
}
?>
<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0"><i class="fas fa-search mr-2"></i>Cek PIN / User di Mesin</h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Lihat PIN asli di mesin (bukan nomor urut di layar) — cari by PIN, PIN2, atau nama</p>
            </div>
            <a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors"><i class="fas fa-arrow-left mr-1.5"></i> Kembali</a>
        </div>
        <div class="p-6">
            <form method="GET" class="flex flex-col md:flex-row gap-2 mb-2">
                <input type="hidden" name="page" value="karyawan">
                <input type="hidden" name="aksi" value="cek_pin">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik PIN / PIN2 / nama… mis: tes2" class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-lg text-sm"><i class="fas fa-search mr-2"></i>Cek ke Mesin</button>
            </form>
            <p class="text-xs text-gray-400 mb-6">Angka di layar mesin (1, 2, 3…) itu nomor urut tampilan, <strong>bukan PIN</strong>. Yang dipakai hapus/sync adalah kolom PIN di bawah ini.</p>

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
                                <td class="py-1.5 px-2"><?php if ($u['priv'] !== '' && $u['priv'] !== '0'): ?><span class="px-1.5 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-700">ADMIN (<?= htmlspecialchars($u['priv']) ?>)</span><?php else: ?><span class="text-gray-400">User</span><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
