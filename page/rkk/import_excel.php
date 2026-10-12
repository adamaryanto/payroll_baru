<?php
// Import Excel absensi harian -> tb_rkk (+detail) saja. Realisasi dibuat setelah RKK di-approve.
// Format: sheet PERENCANAAN + REALISASI (atau .xls hasil export), kolom:
//   A No | B Nama | C Paraf | D OS/DHK | E Golongan | F Posisi | G Jam Kerja
//   H Jam Masuk | I Istirahat | J Jam Pulang | K Hasil | L Upah | M Potongan | N Lembur | O Upah Dibayar
// Section di Excel = grup borongan (dibuat otomatis bila belum ada).
// Alur: upload -> preview (cocok/baru/kotor) -> commit.

require_once __DIR__ . '/import_lib.php';
include_once __DIR__ . '/../grup_borongan/_setup.php';

$alert = null;
$redirect = null;
$step = $_POST['rx_step'] ?? 'upload';

if ($step === 'commit') {
    // ===== COMMIT =====
    $imp = $_SESSION['rx_import'] ?? null;
    if (!$imp) {
        $alert = ["icon" => "error", "title" => "Kedaluwarsa", "text" => "Data preview kedaluwarsa. Upload ulang filenya."];
    } else {
        $sel = array_map('intval', (array)($_POST['rx_sel'] ?? []));
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
            $tgl = preg_match('/^\d{4}-\d{2}-\d{2}$/', $imp['date'] ?? '') ? $imp['date'] : null;
            $tgl = !empty($_POST['rx_date']) ? $_POST['rx_date'] : $tgl;
            if (!$tgl) {
                $alert = ["icon" => "error", "title" => "Tanggal Invalid", "text" => "Tanggal tidak valid."];
            } else {
                $cek = $koneksi->query("SELECT id_rkk FROM tb_rkk WHERE tgl_rkk='$tgl' LIMIT 1");
                if ($cek && $cek->num_rows > 0) {
                    $zid = (int)$cek->fetch_assoc()['id_rkk'];
                    $jz = $koneksi->query("SELECT COUNT(*) c FROM tb_rkk_detail WHERE id_rkk='$zid'")->fetch_assoc()['c'] ?? 0;
                    if ((int)$jz > 0) {
                        $alert = ["icon" => "warning", "title" => "Sudah Ada", "text" => "Tanggal $tgl sudah ada RKK-nya. Hapus dulu via halaman RKK kalau mau import ulang."];
                    } else {
                        // Zombie (header tanpa isi, sisa import gagal) — bersihkan RKK-nya saja
                        $koneksi->query("DELETE FROM tb_rkk WHERE id_rkk='$zid'");
                    }
                }
                if (!$alert) {
                    set_time_limit(0); // file besar butuh waktu
                    $koneksi->begin_transaction();
                    $tx_fail = '';
                    $now = date('Y-m-d H:i:s');
                    $ket = $koneksi->real_escape_string('Import Excel ' . ($imp['title'] ?? $tgl));
                    $jamkerja = 0;
                    foreach ($rows as $r) $jamkerja = max($jamkerja, (float)($r['jam_kerja'] ?? 0));
                    $jamkerja = $jamkerja > 0 ? (int)round($jamkerja) : 12;
                    if (!$koneksi->query("INSERT INTO tb_rkk (tgl_rkk, keterangan, jam_kerja, detail_rkk, status_rkk, tgl_status) VALUES ('$tgl', '$ket', '$jamkerja', '$now', '0', '$now')")) $tx_fail = 'Gagal menyimpan header RKK.';
                    $id_rkk = $koneksi->insert_id;
                    $c_kar = 0; $c_new = 0; $c_grup = 0;
                    $qd = $koneksi->query("SELECT MIN(id_departmen) AS m FROM ms_departmen");
                    $def_dept = ($qd && ($rd = $qd->fetch_assoc()) && $rd['m']) ? (int)$rd['m'] : 0;
                    $qs = $koneksi->query("SELECT MIN(id_sub_department) AS m FROM ms_sub_department");
                    $def_sub = ($qs && ($rs = $qs->fetch_assoc()) && $rs['m']) ? (int)$rs['m'] : 0;
                    $grup_map = []; // section upper => id_grup
                    foreach ($rows as $r) {
                        $sec = trim($r['section'] ?? '');
                        $id_grup = null;
                        if ($sec !== '') {
                            $uk = mb_strtoupper($sec);
                            if (!array_key_exists($uk, $grup_map)) $grup_map[$uk] = rk_ensure_grup($koneksi, $sec, $c_grup);
                            $id_grup = $grup_map[$uk];
                        }
                        $vg = ($id_grup === null) ? "NULL" : "'$id_grup'";
                        $dep = (int)($r['dept_id'] ?? 0) > 0 ? (int)$r['dept_id'] : $def_dept;
                        $sub = (int)($r['sub_id'] ?? 0);
                        if (!empty($r['posisi_baru']) && ($sub <= 0 || $sub === (int)($r['sub_was'] ?? -1))) {
                            // Posisi dari Excel belum ada di master -> generate otomatis (nyambung ke dept terpilih)
                            $sub = rx_ensure_sub($koneksi, $r['posisi_baru'], $dep);
                        }
                        if ($sub <= 0) $sub = $def_sub;
                        $kid = (int)($r['karyawan_id'] ?? 0);
                        if ($kid <= 0 && !empty($r['nama'])) {
                            $kid = rx_make_karyawan($koneksi, $r['nama'], $r['osdhk'] ?? '', $r['golongan'] ?? '', $r['posisi'] ?? '', $r['shift_id'] ?? 0, $r['upah'] ?? 0, $tgl, $dep, $sub);
                            if ($kid > 0) {
                                $c_new++;
                                // Paksa dept/posisi master ikut pilihan preview (bukan tebakan teks Excel)
                                $koneksi->query("UPDATE ms_karyawan SET id_departmen='$dep', id_sub_department='$sub' WHERE id_karyawan='$kid'");
                            }
                        }
                        if ($kid <= 0) continue;
                        $up = (float)($r['upah'] ?? 0);
                        $jd = (int)($r['shift_id'] ?? 0);
                        if (!$koneksi->query("INSERT INTO tb_rkk_detail (id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, upah, status_rkk, id_jadwal, id_grup, potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) VALUES ('$id_rkk', '$kid', NULL, '$dep', '$sub', '$up', 'Hadir', '$jd', $vg, '0', '0', '0', '$now')")) { $tx_fail = 'Gagal menyimpan detail RKK.'; break; }
                        $c_kar++;
                    }
                    // Realisasi SENGAJA tidak dibuat dari import Excel (dibuat terpisah via Tambah Realisasi + Tarik Data).
                    if ($tx_fail !== '') {
                        $koneksi->rollback();
                        $alert = ["icon" => "error", "title" => "Import Gagal", "text" => $tx_fail . " Semua dibatalkan, tidak ada data setengah jadi. Coba lagi."];
                    } else {
                        $koneksi->commit();
                    unset($_SESSION['rx_import']);
                    $alert = ["icon" => "success", "title" => "Import Selesai",
                        "text" => "RKK#$id_rkk ($tgl): $c_kar karyawan" . ($c_new > 0 ? " ($c_new karyawan baru)" : "") . ($c_grup > 0 ? ", $c_grup grup baru" : "") . "."]; 
                    $redirect = "?page=rkk";
                }
                }
            }
        }
    }
}

// ===== PREVIEW =====
$preview = null;
if (!$alert && $step === 'preview' && !empty($_FILES['fexcel']['tmp_name'])) {
    $sheets = rk_read_any($_FILES['fexcel']['tmp_name']);
    if ($sheets === false || empty($sheets)) {
        $alert = ["icon" => "error", "title" => "Gagal Baca", "text" => "File tidak bisa dibaca (harus .xlsx atau .xls hasil export aplikasi)."];
    } else {
        $plan = ['rows' => [], 'skipped' => []]; $real = ['rows' => [], 'skipped' => []]; $title = '';
        foreach ($sheets as $sname => $rows) {
            $up = strtoupper($sname);
            if (strpos($up, 'RENCANA') !== false || strpos($up, 'PERENCANA') !== false) $plan = rk_extract_kelola($rows, $koneksi);
            elseif (strpos($up, 'REALISASI') !== false || strpos($up, 'REAL') !== false) $real = rk_extract_kelola($rows, $koneksi);
            if ($title === '' && isset($rows[1][1])) $title = trim((string)$rows[1][1]);
        }
        if (empty($plan['rows']) && empty($real['rows'])) {
            $vals = array_values($sheets);
            if (isset($vals[0])) $plan = rk_extract_kelola($vals[0], $koneksi);
            if (isset($vals[1])) $real = rk_extract_kelola($vals[1], $koneksi);
        }
        $skipped_all = array_merge(
            array_map(function($s) { $s['sheet'] = 'PERENCANAAN'; return $s; }, $plan['skipped']),
            array_map(function($s) { $s['sheet'] = 'REALISASI'; return $s; }, $real['skipped'])
        );
        $plan = $plan['rows']; $real = $real['rows'];
        $date = rx_parse_id_date($title);
        // Gabung per nama (kunci: nama normal)
        $merged = [];
        foreach ($plan as $r) {
            $k = mb_strtolower(rx_norm_name($r['nama']));
            if (!isset($merged[$k])) $merged[$k] = ['nama' => $r['nama'], 'plan' => null, 'real' => null];
            $merged[$k]['plan'] = $r;
        }
        foreach ($real as $r) {
            $k = mb_strtolower(rx_norm_name($r['nama']));
            if (!isset($merged[$k])) $merged[$k] = ['nama' => $r['nama'], 'plan' => null, 'real' => null];
            $merged[$k]['real'] = $r;
        }
        // Aturan denda + toleransi
        $dq = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
        $dd = $dq ? $dq->fetch_assoc() : [];
        $denda = ['masuk' => (float)($dd['denda_masuk'] ?? 0), 'ist_keluar' => (float)($dd['denda_istirahat_keluar'] ?? 0),
            'ist_masuk' => (float)($dd['denda_istirahat_masuk'] ?? 0), 'pulang' => (float)($dd['denda_pulang'] ?? 0),
            'tdk' => (float)($dd['denda_tidak_lengkap'] ?? 0)];
        $tq = $koneksi->query("SELECT * FROM tb_toleransi LIMIT 1");
        $td = $tq ? $tq->fetch_assoc() : [];
        $tol = ['masuk' => (int)($td['toleransi_masuk'] ?? 10), 'ist_keluar' => (int)($td['toleransi_istirahat_keluar'] ?? 5),
            'ist_masuk' => (int)($td['toleransi_istirahat_masuk'] ?? 10), 'pulang' => (int)($td['toleransi_pulang'] ?? 5)];
        $rows = [];
        $qdd = $koneksi->query("SELECT MIN(id_departmen) AS m FROM ms_departmen");
        $pv_dept = ($qdd && ($rdd = $qdd->fetch_assoc()) && $rdd['m']) ? (int)$rdd['m'] : 0;
        $qss = $koneksi->query("SELECT MIN(id_sub_department) AS m FROM ms_sub_department");
        $pv_sub = ($qss && ($rss = $qss->fetch_assoc()) && $rss['m']) ? (int)$rss['m'] : 0;
        foreach ($merged as $m) {
            $base = $m['plan'] ?: $m['real'];
            $nama = $base['nama'];
            $g_raw = ($m['plan'] ? $m['plan']['g_raw'] : '') ?: ($m['real'] ? $m['real']['g_raw'] : '');
            $shift = rk_match_shift($koneksi, $g_raw);
            $match = rx_match_karyawan($koneksi, $nama);
            // Department + posisi default: master bila cocok, tebakan teks Excel bila baru
            $dep_id = $pv_dept; $sub_id = $pv_sub; $posisi_baru = '';
            if ($match) {
                $qm = $koneksi->query("SELECT id_departmen, id_sub_department FROM ms_karyawan WHERE id_karyawan='" . (int)$match['id_karyawan'] . "' LIMIT 1");
                if ($qm && ($rm = $qm->fetch_assoc())) {
                    if ((int)($rm['id_departmen'] ?? 0) > 0) $dep_id = (int)$rm['id_departmen'];
                    if ((int)($rm['id_sub_department'] ?? 0) > 0) $sub_id = (int)$rm['id_sub_department'];
                }
            } else {
                $pos_txt = trim($base['posisi'] ?? '');
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
            if ($match && $posisi_baru === '') {
                // Karyawan cocok master tapi teks Posisi Excel belum ada di master -> ikut Excel (auto-create)
                $pos_txt = trim($base['posisi'] ?? '');
                if ($pos_txt !== '' && rx_lookup_id($koneksi, 'ms_sub_department', 'nama_sub_department', $pos_txt) <= 0) {
                    $posisi_baru = $pos_txt;
                }
            }
            $has_real = $m['real'] && (rx_valid_time($m['real']['h_raw']) || rx_valid_time($m['real']['jam_pulang']));
            $jam_masuk = $m['real'] ? rx_frac_to_time($m['real']['h_raw']) : '';
            $jam_pulang = $m['real'] ? $m['real']['jam_pulang'] : '';
            $pot = ['telat' => 0, 'ist_awal' => 0, 'ist_telat' => 0, 'pulang' => 0, 'tdk' => 0, 'lainnya' => 0];
            if ($has_real && $shift) {
                $ra_m = $jam_masuk; $ra_p = $jam_pulang;
                if (rx_valid_time($ra_m) && rx_valid_time($shift['jam_masuk']) && rx_day_diff($ra_m, $shift['jam_masuk']) > $tol['masuk'] * 60) $pot['telat'] = $denda['masuk'];
                if (rx_valid_time($ra_p) && rx_valid_time($shift['jam_keluar']) && rx_day_diff($shift['jam_keluar'], $ra_p) > $tol['pulang'] * 60) $pot['pulang'] = $denda['pulang'];
                $hm = rx_valid_time($ra_m); $hp = rx_valid_time($ra_p);
                if ($hm xor $hp) $pot['tdk'] = $denda['tdk'];
            }
            $excel_pot = $m['real'] ? (float)$m['real']['pot_excel'] : 0;
            $section = ($m['plan'] ? $m['plan']['section'] : '') ?: ($m['real'] ? $m['real']['section'] : '');
            $rows[] = [
                'nama' => $nama, 'section' => $section,
                'dirty' => !empty($base['dirty']),
                'karyawan_id' => $match ? (int)$match['id_karyawan'] : 0,
                'karyawan_nama' => $match ? $match['nama_karyawan'] : '',
                'grup_baru' => trim($section) !== '' && !rk_find_grup($koneksi, $section),
                'shift_id' => $shift ? (int)$shift['id_jadwal'] : 0,
                'shift' => $shift,
                'jam_kerja' => rx_frac_to_hours($g_raw),
                'upah' => $m['plan'] ? (float)$m['plan']['upah_excel'] : (float)($m['real']['upah_excel'] ?? 0),
                'osdhk' => $base['osdhk'] ?? '',
                'golongan' => $base['golongan'] ?? '',
                'posisi' => $base['posisi'] ?? '',
                'posisi_baru' => $posisi_baru,
                'dept_id' => $dep_id,
                'sub_id' => $sub_id,
                'in_plan' => !empty($m['plan']),
                'has_real' => $has_real,
                'jam_masuk' => $jam_masuk,
                'jam_pulang' => $jam_pulang,
                'hasil' => $m['real']['hasil'] ?? '',
                'lembur' => $m['real'] ? (float)$m['real']['lembur_excel'] : 0,
                'pot_split' => $pot,
                'pot_split_total' => array_sum($pot),
                'pot_excel' => $excel_pot,
                'upah_dibayar_excel' => $m['real'] ? (float)$m['real']['dibayar_excel'] : 0,
            ];
        }
        $_SESSION['rx_import'] = ['date' => $date, 'title' => $title, 'rows' => $rows, 'skipped' => $skipped_all];
        $preview = ['fname' => $_FILES['fexcel']['name'], 'title' => $title, 'date' => $date, 'rows' => $rows, 'skipped' => $skipped_all];
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
                <i class="fas fa-file-excel mr-3"></i> Import Excel Absensi Harian
            </h3>
            <p class="text-emerald-100 text-xs sm:text-sm mt-1">Section di Excel = grup (dibuat otomatis bila belum ada). Upload → preview → simpan ke RKK saja (Realisasi dibuat setelah RKK di-approve).</p>
        </div>
        <div class="p-5 sm:p-8">
            <div class="mb-5">
                <a href="?page=rkk" class="inline-flex justify-center items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali
                </a>
            </div>
            <?php if ($preview): ?>
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-100 rounded-lg text-sm">
                <strong>File:</strong> <?= htmlspecialchars($preview['fname']); ?> | <strong>Judul:</strong> <?= htmlspecialchars($preview['title']); ?><br>
                <strong>Tanggal terdeteksi:</strong> <?= htmlspecialchars($preview['date'] ?: '(tidak terbaca dari judul — isi manual di bawah)'); ?> |
                <strong><?= count($preview['rows']); ?></strong> baris karyawan
            </div>
            <form method="POST">
                <input type="hidden" name="rx_step" value="commit">
                <div class="mb-4 max-w-xs">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Tanggal RKK</label>
                    <input type="date" name="rx_date" value="<?= htmlspecialchars($preview['date'] ?: date('Y-m-d')); ?>" required class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm">
                </div>
                <div class="rounded-lg border border-gray-300 mb-4" style="overflow-x:auto;">
                    <table class="text-left border-collapse" style="min-width:1500px;width:100%;">
                        <thead class="bg-gray-100">
                            <tr style="white-space:nowrap;">
                                <th class="py-2 px-3 w-10"><input type="checkbox" id="rx_all" checked style="width:16px;height:16px;"></th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase w-12">No</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Nama (Excel)</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Grup</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Status</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Shift</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Department</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Posisi</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase text-right">Upah</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($preview['rows'] as $idx => $r): ?>
                            <tr class="border-t border-gray-200">
                                <td class="py-2 px-3"><input type="checkbox" class="rx_sel" name="rx_sel[]" value="<?= $idx; ?>" <?= $r['dirty'] ? '' : 'checked'; ?> style="width:16px;height:16px;"></td>
                                <td class="py-2 px-3 text-sm text-gray-500"><?= $idx + 1; ?></td>
                                <td class="py-2 px-3 text-sm font-bold"><?= htmlspecialchars($r['nama']); ?>
                                    <?php if ($r['karyawan_id'] > 0): ?><div class="text-[11px] font-normal text-gray-400"><?= htmlspecialchars($r['karyawan_nama']); ?></div><?php endif; ?>
                                    <div class="text-[11px] font-normal text-gray-400"><?= htmlspecialchars(trim(($r['osdhk'] . ' / ' . $r['golongan'] . ' / ' . $r['posisi']), ' /')); ?></div>
                                </td>
                                <td class="py-2 px-3 text-sm">
                                    <?= $r['section'] !== '' ? htmlspecialchars($r['section']) : '<span class="text-gray-400 italic">Tanpa Grup</span>'; ?>
                                    <?php if ($r['grup_baru']): ?><div><span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-purple-100 text-purple-800">Grup baru</span></div><?php endif; ?>
                                </td>
                                <td class="py-2 px-3 text-sm">
                                    <?php if ($r['karyawan_id'] > 0): ?>
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-green-100 text-green-800">Cocok</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-blue-100 text-blue-800">Baru dibuat</span>
                                    <?php endif; ?>
                                    <?php if ($r['dirty']): ?><div><span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800">Baris kotor</span></div><?php endif; ?>
                                    <?php if (!$r['in_plan']): ?><div><span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-gray-200 text-gray-600">Tanpa rencana</span></div><?php endif; ?>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="sh_<?= $idx; ?>" class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                                        <?php foreach ($jadwal_opts as $jo): ?>
                                            <option value="<?= $jo['id_jadwal']; ?>" <?= ((int)$jo['id_jadwal'] === (int)$r['shift_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($jo['keterangan']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="dp_<?= $idx; ?>" class="rx_dept px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                                        <?php foreach ($dept_opts as $do): ?>
                                            <option value="<?= $do['id_departmen']; ?>" <?= ((int)$do['id_departmen'] === (int)($r['dept_id'] ?? 0)) ? 'selected' : ''; ?>><?= htmlspecialchars($do['nama_departmen']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="ps_<?= $idx; ?>" class="rx_pos px-2 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
                                        <?php foreach ($sub_opts as $so): ?>
                                            <option value="<?= $so['id_sub_department']; ?>" data-dept="<?= (int)($so['id_departmen'] ?? 0); ?>" <?= ((int)$so['id_sub_department'] === (int)($r['sub_id'] ?? 0)) ? 'selected' : ''; ?>><?= htmlspecialchars($so['nama_sub_department']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($r['posisi_baru'])): ?><div class="text-[11px] font-bold text-purple-700 mt-1">“<?= htmlspecialchars($r['posisi_baru']); ?>” baru → otomatis dibuat</div><?php endif; ?>
                                </td>
                                <td class="py-2 px-3"><input type="number" name="up_<?= $idx; ?>" value="<?= (int)$r['upah']; ?>" min="0" class="w-28 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-right"></td>
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
                    <a href="?page=rkk&aksi=import" class="inline-flex justify-center items-center px-5 py-2.5 border border-gray-300 text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">Ulangi</a>
                </div>
            </form>
            <script>
                document.getElementById('rx_all').addEventListener('change', function() {
                    document.querySelectorAll('.rx_sel').forEach(function(c) { c.checked = document.getElementById('rx_all').checked; });
                });
                // Posisi ikut department: pilihan posisi difilter per baris
                function rxFilterRow(tr) {
                    var d = tr.querySelector('.rx_dept');
                    var p = tr.querySelector('.rx_pos');
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
                document.querySelectorAll('.rx_dept').forEach(function(d) {
                    d.addEventListener('change', function() { rxFilterRow(d.closest('tr')); });
                });
                document.querySelectorAll('#rx_all, .rx_sel, table tbody tr').forEach(function() {});
                document.querySelectorAll('table tbody tr').forEach(function(tr) { rxFilterRow(tr); });
            </script>
            <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="max-w-xl">
                <input type="hidden" name="rx_step" value="preview">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">File Excel (.xlsx / .xls hasil export)</label>
                    <input type="file" name="fexcel" accept=".xlsx,.xls" required class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm bg-white">
                </div>
                <button type="submit" class="inline-flex justify-center items-center px-6 py-2.5 text-sm font-bold rounded-xl shadow-md text-white bg-emerald-600 hover:bg-emerald-700 transition">
                    <i class="fas fa-upload mr-2"></i> Upload &amp; Preview
                </button>
            </form>
            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600 leading-relaxed">
                <strong>Cara kerja:</strong> tanggal diambil dari judul baris 1 (bisa dikoreksi di preview). Section di Excel = grup — kalau belum ada di master, otomatis dibuatkan.
                Shift ditebak dari kolom JAM KERJA (bisa diubah per baris), Upah dari kolom UPAH (kalau ada).                 Department &amp; Posisi ikut master/tebakan Excel dan bisa diubah per baris — pilihan Posisi otomatis ikut Department. Posisi yang belum ada di master otomatis dibuatkan saat simpan.
                Nama yang tidak cocok otomatis dibuatkan karyawan baru (No. Absen = MAX+1). Baris kotor tetap diproses tapi tidak dicentang — cek manual.
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
