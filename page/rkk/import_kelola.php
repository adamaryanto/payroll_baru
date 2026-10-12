<?php
// Import Excel absensi -> RKK yang sedang dikelola (tambah detail, BUKAN bikin RKK baru).
// Section di Excel = nama grup borongan. Grup/karyawan yang belum ada dibuat otomatis.
// Format: hasil export excelrkk.php (9 kolom) atau sheet PERENCANAAN file referensi (15 kolom).

// Library bersama (baca xlsx/html, cocok grup/karyawan/shift, auto-generate)
require_once __DIR__ . '/import_lib.php';

// Pastikan relasi grup tersedia
include_once __DIR__ . '/../grup_borongan/_setup.php';

$idrkk = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$alert = null;
$redirect = null;

if ($idrkk <= 0) {
    $alert = ["icon" => "error", "title" => "ID Tidak Valid", "text" => "ID RKK tidak ditemukan!"];
    $redirect = "?page=rkk";
} else {
    $cek_rkk = $koneksi->query("SELECT tgl_rkk, status_rkk FROM tb_rkk WHERE id_rkk = '$idrkk'");
    $data_rkk = $cek_rkk ? $cek_rkk->fetch_assoc() : null;
    if (!$data_rkk) {
        $alert = ["icon" => "error", "title" => "Tidak Ditemukan", "text" => "Data RKK tidak ada!"];
        $redirect = "?page=rkk";
    } elseif ((int)$data_rkk['status_rkk'] >= 2) {
        $alert = ["icon" => "error", "title" => "Akses Ditolak", "text" => "Tidak bisa import karena RKK sudah Approved/Realized!"];
        $redirect = "?page=rkk&aksi=kelola&id=$idrkk";
    }
}
$tgl_rkk = $data_rkk['tgl_rkk'] ?? date('Y-m-d');

// (helper baca/cocok/auto-generate ada di import_lib.php)

// ===== COMMIT =====
if (!$alert && ($_POST['rk_step'] ?? '') === 'commit') {
    $imp = $_SESSION['rk_imp_kelola'] ?? null;
    if (!$imp || (int)($imp['id_rkk'] ?? 0) !== $idrkk) {
        $alert = ["icon" => "error", "title" => "Kedaluwarsa", "text" => "Data preview kedaluwarsa. Upload ulang filenya."];
    } else {
        $sel = array_map('intval', (array)($_POST['rk_sel'] ?? []));
        $rows = [];
        foreach (($imp['rows'] ?? []) as $i => $r) {
            if (!in_array($i, $sel, true)) continue;
            $r['shift_id'] = isset($_POST["sh_$i"]) ? (int)$_POST["sh_$i"] : (int)($r['shift_id'] ?? 0);
            $r['upah'] = isset($_POST["up_$i"]) ? max(0, (int)str_replace('.', '', $_POST["up_$i"])) : (int)($r['upah'] ?? 0);
            $r['dept_was'] = (int)($r['dept_id'] ?? 0);
            $r['sub_was'] = (int)($r['sub_id'] ?? 0);
            $r['dept_id'] = isset($_POST["dp_$i"]) ? (int)$_POST["dp_$i"] : (int)($r['dept_id'] ?? 0);
            $r['sub_id'] = isset($_POST["ps_$i"]) ? (int)$_POST["ps_$i"] : (int)($r['sub_id'] ?? 0);
            $rows[] = $r;
        }
        if (empty($rows)) {
            $alert = ["icon" => "warning", "title" => "Kosong", "text" => "Tidak ada baris yang dipilih."];
        } else {
            $now = date('Y-m-d H:i:s');
            $qd = $koneksi->query("SELECT MIN(id_departmen) AS m FROM ms_departmen");
            $def_dept = ($qd && ($rd = $qd->fetch_assoc()) && $rd['m']) ? (int)$rd['m'] : 0;
            $qs = $koneksi->query("SELECT MIN(id_sub_department) AS m FROM ms_sub_department");
            $def_sub = ($qs && ($rs = $qs->fetch_assoc()) && $rs['m']) ? (int)$rs['m'] : 0;
            $c_add = 0; $c_new_kar = 0; $c_new_grup = 0; $c_skip = 0;
            foreach ($rows as $r) {
                // 1. Grup: cari / buat otomatis
                $id_grup = rk_ensure_grup($koneksi, $r['section'] ?? '', $c_new_grup);
                // 2. Karyawan: cocok / buat otomatis (dept & posisi ikut pilihan preview)
                $m = rx_match_karyawan($koneksi, $r['nama']);
                if ($m) {
                    $kid = (int)$m['id_karyawan'];
                    $mk = $koneksi->query("SELECT id_departmen, id_sub_department, upah FROM ms_karyawan WHERE id_karyawan='$kid'")->fetch_assoc();
                    $dep = (int)($r['dept_id'] ?? 0) > 0 ? (int)$r['dept_id'] : (((int)($mk['id_departmen'] ?? 0) > 0) ? (int)$mk['id_departmen'] : $def_dept);
                    $sub = (int)($r['sub_id'] ?? 0);
                    if ($sub <= 0) $sub = ((int)($mk['id_sub_department'] ?? 0) > 0) ? (int)$mk['id_sub_department'] : 0;
                    if (!empty($r['posisi_baru']) && ($sub <= 0 || $sub === (int)($r['sub_was'] ?? -1))) {
                        // Posisi dari Excel belum ada di master -> generate otomatis (nyambung ke dept terpilih)
                        $sub = rx_ensure_sub($koneksi, $r['posisi_baru'], $dep);
                    }
                    if ($sub <= 0) $sub = $def_sub;
                    if ((int)($r['upah'] ?? 0) <= 0) $r['upah'] = (int)($mk['upah'] ?? 0);
                } else {
                    $dep = (int)($r['dept_id'] ?? 0) > 0 ? (int)$r['dept_id'] : $def_dept;
                    $sub = (int)($r['sub_id'] ?? 0);
                    if (!empty($r['posisi_baru']) && ($sub <= 0 || $sub === (int)($r['sub_was'] ?? -1))) {
                        $sub = rx_ensure_sub($koneksi, $r['posisi_baru'], $dep);
                    }
                    if ($sub <= 0) $sub = $def_sub;
                    $kid = rx_make_karyawan($koneksi, $r['nama'], $r['osdhk'] ?? '', $r['golongan'] ?? '', $r['posisi'] ?? '', $r['shift_id'] ?? 0, $r['upah'] ?? 0, $tgl_rkk, $dep, $sub);
                    if ($kid <= 0) { $c_skip++; continue; }
                    // Paksa dept/posisi master ikut pilihan preview
                    $koneksi->query("UPDATE ms_karyawan SET id_departmen='$dep', id_sub_department='$sub' WHERE id_karyawan='$kid'");
                    $c_new_kar++;
                }
                // 3. Lewati duplikat di RKK ini
                $cek = $koneksi->query("SELECT id_rkk_detail FROM tb_rkk_detail WHERE id_rkk='$idrkk' AND id_karyawan='$kid' LIMIT 1");
                if ($cek && $cek->num_rows > 0) { $c_skip++; continue; }
                $jd = (int)($r['shift_id'] ?? 0);
                $up = (int)($r['upah'] ?? 0);
                $vg = ($id_grup === null) ? "NULL" : "'$id_grup'";
                $ok = $koneksi->query("INSERT INTO tb_rkk_detail (id_rkk, id_karyawan, nama_karyawan_manual, upah, id_departmen, id_sub_department, id_jadwal, id_grup, status_rkk, potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) VALUES ('$idrkk', '$kid', NULL, '$up', '$dep', '$sub', '$jd', $vg, 'Hadir', '0', '0', '0', '$now')");
                if ($ok) $c_add++; else $c_skip++;
            }
            unset($_SESSION['rk_imp_kelola']);
            $msg = "$c_add ditambahkan" . ($c_new_kar > 0 ? ", $c_new_kar karyawan baru dibuat" : "") . ($c_new_grup > 0 ? ", $c_new_grup grup baru dibuat" : "") . ($c_skip > 0 ? ", $c_skip dilewati (sudah ada/gagal)" : "");
            $alert = ["icon" => $c_add > 0 ? "success" : "warning", "title" => $c_add > 0 ? "Import Selesai" : "Tidak Ada yang Disimpan", "text" => $msg];
            $redirect = "?page=rkk&aksi=kelola&id=$idrkk";
        }
    }
}

// ===== PREVIEW =====
$preview = null;
if (!$alert && ($_POST['rk_step'] ?? '') === 'preview' && !empty($_FILES['fexcel']['tmp_name'])) {
    $sheets = rk_read_any($_FILES['fexcel']['tmp_name']);
    if ($sheets === false || empty($sheets)) {
        $alert = ["icon" => "error", "title" => "Gagal Baca", "text" => "File tidak bisa dibaca (harus .xlsx atau .xls hasil export aplikasi)."];
    } else {
        // Prioritas: sheet PERENCANAAN, lalu sheet pertama berisi baris
        $picked = null; $pickedName = '';
        foreach ($sheets as $sn => $rw) {
            if (stripos($sn, 'RENCANA') !== false || stripos($sn, 'PERENCANA') !== false) { $picked = $rw; $pickedName = $sn; break; }
        }
        if ($picked === null) {
            foreach ($sheets as $sn => $rw) {
                $t = rk_extract_kelola($rw, $koneksi);
                if (!empty($t['rows'])) { $picked = $rw; $pickedName = $sn; break; }
            }
        }
        if ($picked === null) {
            $alert = ["icon" => "warning", "title" => "Kosong", "text" => "Tidak ada baris karyawan di file ini."];
        } else {
            $ex = rk_extract_kelola($picked, $koneksi);
            // id karyawan yang SUDAH ada di RKK ini
            $have = [];
            $qh = $koneksi->query("SELECT id_karyawan FROM tb_rkk_detail WHERE id_rkk='$idrkk' AND id_karyawan<>0");
            while ($qh && ($hh = $qh->fetch_assoc())) $have[(int)$hh['id_karyawan']] = true;
            $rows = [];
            $qdd = $koneksi->query("SELECT MIN(id_departmen) AS m FROM ms_departmen");
            $pv_dept = ($qdd && ($rdd = $qdd->fetch_assoc()) && $rdd['m']) ? (int)$rdd['m'] : 0;
            $qss = $koneksi->query("SELECT MIN(id_sub_department) AS m FROM ms_sub_department");
            $pv_sub = ($qss && ($rss = $qss->fetch_assoc()) && $rss['m']) ? (int)$rss['m'] : 0;
            foreach ($ex['rows'] as $r) {
                $m = rx_match_karyawan($koneksi, $r['nama']);
                $sh = rk_match_shift($koneksi, $r['g_raw']);
                // Department + posisi default: master bila cocok, tebakan teks Excel bila baru
                $dep_id = $pv_dept; $sub_id = $pv_sub; $posisi_baru = '';
                if ($m) {
                    $qm = $koneksi->query("SELECT id_departmen, id_sub_department FROM ms_karyawan WHERE id_karyawan='" . (int)$m['id_karyawan'] . "' LIMIT 1");
                    if ($qm && ($rm = $qm->fetch_assoc())) {
                        if ((int)($rm['id_departmen'] ?? 0) > 0) $dep_id = (int)$rm['id_departmen'];
                        if ((int)($rm['id_sub_department'] ?? 0) > 0) $sub_id = (int)$rm['id_sub_department'];
                    }
                    $pos_txt = trim($r['posisi'] ?? '');
                    if ($pos_txt !== '' && rx_lookup_id($koneksi, 'ms_sub_department', 'nama_sub_department', $pos_txt) <= 0) {
                        $posisi_baru = $pos_txt; // belum ada di master -> dibuat otomatis saat simpan
                    }
                } else {
                    $pos_txt = trim($r['posisi'] ?? '');
                    if ($pos_txt !== '') {
                        $sid = rx_lookup_id($koneksi, 'ms_sub_department', 'nama_sub_department', $pos_txt);
                        if ($sid > 0) {
                            $sub_id = $sid;
                            $qs2 = $koneksi->query("SELECT id_departmen FROM ms_sub_department WHERE id_sub_department='$sid' LIMIT 1");
                            if ($qs2 && ($rs2 = $qs2->fetch_assoc()) && (int)($rs2['id_departmen'] ?? 0) > 0) $dep_id = (int)$rs2['id_departmen'];
                        } else {
                            $posisi_baru = $pos_txt; // belum ada di master -> dibuat otomatis saat simpan
                        }
                    }
                }
                $rows[] = [
                    'nama' => $r['nama'], 'section' => $r['section'],
                    'osdhk' => $r['osdhk'], 'golongan' => $r['golongan'], 'posisi' => $r['posisi'],
                    'dirty' => !empty($r['dirty']),
                    'kar_id' => $m ? (int)$m['id_karyawan'] : 0,
                    'kar_nama' => $m ? $m['nama_karyawan'] : '',
                    'kar_upah' => $m ? (int)($m['upah'] ?? 0) : 0,
                    'dup' => $m && isset($have[(int)$m['id_karyawan']]),
                    'grup_baru' => trim($r['section']) !== '' && !rk_find_grup($koneksi, $r['section']),
                    'shift_id' => $sh ? (int)$sh['id_jadwal'] : 0,
                    'shift_txt' => $sh ? ($sh['keterangan'] . ' (' . substr($sh['jam_masuk'], 0, 5) . ')') : '-',
                    'upah' => (int)$r['upah_excel'] > 0 ? (int)$r['upah_excel'] : ($m ? (int)($m['upah'] ?? 0) : 0),
                    'dept_id' => $dep_id,
                    'sub_id' => $sub_id,
                    'posisi_baru' => $posisi_baru,
                ];
            }
            $_SESSION['rk_imp_kelola'] = ['id_rkk' => $idrkk, 'rows' => $rows];
            $preview = ['fname' => $_FILES['fexcel']['name'], 'sheet' => $pickedName, 'rows' => $rows, 'skipped' => $ex['skipped']];
        }
    }
}
$list_jadwal_prev = $koneksi->query("SELECT * FROM tb_jadwal ORDER BY id_jadwal ASC");
$jadwal_opts = [];
while ($list_jadwal_prev && ($jj = $list_jadwal_prev->fetch_assoc())) $jadwal_opts[] = $jj;
$dept_opts = [];
$qd_prev = $koneksi->query("SELECT * FROM ms_departmen ORDER BY nama_departmen ASC");
while ($qd_prev && ($dd = $qd_prev->fetch_assoc())) $dept_opts[] = $dd;
$sub_opts = [];
$qs_prev = $koneksi->query("SELECT * FROM ms_sub_department ORDER BY nama_sub_department ASC");
while ($qs_prev && ($ss = $qs_prev->fetch_assoc())) $sub_opts[] = $ss;
?>
<div class="max-w-5xl mx-auto px-2 sm:px-6 lg:px-8 mt-4 sm:mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-5 sm:px-8 py-5 sm:py-6">
            <h3 class="text-xl sm:text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-file-excel mr-3"></i> Import Excel ke RKK
            </h3>
            <p class="text-emerald-100 text-xs sm:text-sm mt-1">Section di Excel = grup. Grup &amp; karyawan yang belum ada dibuat otomatis.</p>
        </div>
        <div class="p-5 sm:p-8">
            <div class="mb-5 flex flex-col sm:flex-row gap-3">
                <a href="?page=rkk&aksi=kelola&id=<?= $idrkk; ?>" class="inline-flex justify-center items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Kelola
                </a>
            </div>
            <?php if ($preview): ?>
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-100 rounded-lg text-sm">
                <strong>File:</strong> <?= htmlspecialchars($preview['fname']); ?> | <strong>Sheet:</strong> <?= htmlspecialchars($preview['sheet']); ?> |
                <strong><?= count($preview['rows']); ?></strong> baris karyawan
            </div>
            <form method="POST">
                <input type="hidden" name="rk_step" value="commit">
                <input type="hidden" name="id" value="<?= $idrkk; ?>">
                <div class="rounded-lg border border-gray-300 mb-4" style="overflow-x:auto;">
                    <table class="text-left border-collapse" style="min-width:1200px;width:100%;">
                        <thead class="bg-gray-100">
                            <tr style="white-space:nowrap;">
                                <th class="py-2 px-3 w-10"><input type="checkbox" id="rk_all" checked style="width:16px;height:16px;"></th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Nama (Excel)</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Grup</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Karyawan</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Shift</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Department</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Posisi</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase text-right">Upah</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($preview['rows'] as $i => $r): ?>
                            <tr class="border-t border-gray-200">
                                <td class="py-2 px-3"><input type="checkbox" class="rk_sel" name="rk_sel[]" value="<?= $i; ?>" <?= ($r['dup'] || !empty($r['dirty'])) ? '' : 'checked'; ?> style="width:16px;height:16px;"></td>
                                <td class="py-2 px-3 text-sm font-bold"><?= htmlspecialchars($r['nama']); ?>
                                    <div class="text-[11px] font-normal text-gray-400"><?= htmlspecialchars(trim(($r['osdhk'] . ' / ' . $r['golongan'] . ' / ' . $r['posisi']), ' /')); ?></div>
                                </td>
                                <td class="py-2 px-3 text-sm">
                                    <?= $r['section'] !== '' ? htmlspecialchars($r['section']) : '<span class="text-gray-400 italic">Tanpa Grup</span>'; ?>
                                    <?php if ($r['grup_baru']): ?><div><span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-purple-100 text-purple-800">Grup baru</span></div><?php endif; ?>
                                </td>
                                <td class="py-2 px-3 text-sm">
                                    <?php if ($r['dup']): ?>
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-gray-200 text-gray-600">Sudah ada di RKK</span>
                                    <?php elseif ($r['kar_id'] > 0): ?>
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-green-100 text-green-800">Cocok</span>
                                        <div class="text-[11px] text-gray-400"><?= htmlspecialchars($r['kar_nama']); ?></div>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-blue-100 text-blue-800">Karyawan baru</span>
                                    <?php endif; ?>
                                    <?php if (!empty($r['dirty'])): ?><div><span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800">Baris kotor</span></div><?php endif; ?>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="sh_<?= $i; ?>" class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                                        <?php foreach ($jadwal_opts as $jo): ?>
                                            <option value="<?= $jo['id_jadwal']; ?>" <?= ((int)$jo['id_jadwal'] === (int)$r['shift_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($jo['keterangan']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="dp_<?= $i; ?>" class="rk_dept px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                                        <?php foreach ($dept_opts as $do): ?>
                                            <option value="<?= $do['id_departmen']; ?>" <?= ((int)$do['id_departmen'] === (int)($r['dept_id'] ?? 0)) ? 'selected' : ''; ?>><?= htmlspecialchars($do['nama_departmen']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="ps_<?= $i; ?>" class="rk_pos px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                                        <?php foreach ($sub_opts as $so): ?>
                                            <option value="<?= $so['id_sub_department']; ?>" data-dept="<?= (int)($so['id_departmen'] ?? 0); ?>" <?= ((int)$so['id_sub_department'] === (int)($r['sub_id'] ?? 0)) ? 'selected' : ''; ?>><?= htmlspecialchars($so['nama_sub_department']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($r['posisi_baru'])): ?><div class="text-[11px] font-bold text-purple-700 mt-1">“<?= htmlspecialchars($r['posisi_baru']); ?>” baru → otomatis dibuat</div><?php endif; ?>
                                </td>
                                <td class="py-2 px-3"><input type="number" name="up_<?= $i; ?>" value="<?= (int)$r['upah']; ?>" min="0" class="w-28 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-right"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($preview['skipped'])): ?>
                <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-500">
                    <strong>Dilewati otomatis (<?= count($preview['skipped']); ?> baris):</strong>
                    <?= htmlspecialchars(implode(', ', array_map(function($s) { return $s['nama'] . ' (baris ' . $s['row'] . (isset($s['alasan']) ? ' — ' . $s['alasan'] : '') . ')'; }, $preview['skipped']))); ?>
                </div>
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit" class="inline-flex justify-center items-center px-6 py-2.5 text-sm font-bold rounded-xl shadow-md text-white bg-emerald-600 hover:bg-emerald-700 transition">
                        <i class="fas fa-check mr-2"></i> Simpan ke RKK
                    </button>
                    <a href="?page=rkk&aksi=importkelola&id=<?= $idrkk; ?>" class="inline-flex justify-center items-center px-5 py-2.5 border border-gray-300 text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">Ulangi</a>
                </div>
            </form>
            <script>
                document.getElementById('rk_all').addEventListener('change', function() {
                    document.querySelectorAll('.rk_sel').forEach(function(c) { c.checked = document.getElementById('rk_all').checked; });
                });
                // Posisi ikut department: pilihan posisi difilter per baris
                function rkFilterRow(tr) {
                    var d = tr.querySelector('.rk_dept');
                    var p = tr.querySelector('.rk_pos');
                    if (!d || !p) return;
                    var dv = d.value;
                    Array.prototype.forEach.call(p.options, function(o) {
                        if (!o.value) return;
                        var od = o.getAttribute('data-dept');
                        o.hidden = (od && od !== '0' && dv && od !== dv);
                    });
                    var sel = p.selectedOptions && p.selectedOptions[0];
                    if (sel && sel.hidden) p.selectedIndex = 0;
                }
                document.querySelectorAll('.rk_dept').forEach(function(d) {
                    d.addEventListener('change', function() { rkFilterRow(d.closest('tr')); });
                });
                document.querySelectorAll('table tbody tr').forEach(function(tr) { rkFilterRow(tr); });
            </script>
            <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="max-w-xl">
                <input type="hidden" name="rk_step" value="preview">
                <input type="hidden" name="id" value="<?= $idrkk; ?>">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">File Excel (.xlsx / .xls hasil export)</label>
                    <input type="file" name="fexcel" accept=".xlsx,.xls" required class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm bg-white">
                </div>
                <button type="submit" class="inline-flex justify-center items-center px-6 py-2.5 text-sm font-bold rounded-xl shadow-md text-white bg-emerald-600 hover:bg-emerald-700 transition">
                    <i class="fas fa-upload mr-2"></i> Upload &amp; Preview
                </button>
            </form>
            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 leading-relaxed">
                <strong>Cara kerja:</strong> tiap section (bar ungu, mis. ADMIN) dianggap 1 grup — kalau grupnya belum ada di master, otomatis dibuatkan.
                Nama yang tidak cocok master otomatis dibuatkan karyawan baru (No. Absen = MAX+1, OS/Golongan/Posisi ikut yang tertulis di Excel kalau ada).
                Shift ditebak dari kolom JAM KERJA, Upah diambil dari kolom UPAH (kalau ada) — keduanya bisa diubah per baris di preview.
                Department &amp; Posisi ikut master/tebakan Excel dan bisa diubah per baris — pilihan Posisi otomatis ikut Department. Posisi yang belum ada di master otomatis dibuatkan saat simpan.
                Baris yang sudah ada di RKK ini otomatis tidak dicentang.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($alert): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: "<?= $alert['icon'] ?>",
    title: "<?= $alert['title'] ?>",
    text: "<?= $alert['text'] ?>",
    confirmButtonColor: "#059669"
}).then(() => {
    <?php if ($redirect): ?>
        window.location.href = "<?= $redirect ?>";
    <?php endif; ?>
});
</script>
<?php endif; ?>
