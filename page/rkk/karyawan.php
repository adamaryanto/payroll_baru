<?php
$alert = null;
$redirect = null;

// Pastikan relasi grup tersedia (kolom id_grup)
include_once __DIR__ . '/../grup_borongan/_setup.php';

// Ambil ID
$idrkk = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : (!empty($_POST['id_rkk_hidden']) ? intval($_POST['id_rkk_hidden']) : 0);

// VALIDASI STATUS RKK
if ($idrkk > 0) {
    $cek_rkk = $koneksi->query("SELECT status_rkk FROM tb_rkk WHERE id_rkk = '$idrkk'");
    $data_rkk = $cek_rkk->fetch_assoc();

    if ($data_rkk['status_rkk'] >= 2) {
        $alert = [
            "icon" => "error",
            "title" => "Akses Ditolak",
            "text" => "Tidak bisa menambah karyawan karena status RKK sudah Approved/Realized!"
        ];
        $redirect = "?page=rkk&aksi=kelola&id=$idrkk";
    }
}

// SIMPAN DATA
if (isset($_POST['simpan_karyawan'])) {

    $id_rkk_fix = intval($_POST['id_rkk_hidden']);
    $id_dept    = $_POST['id_departmen'];
    $id_sub     = $_POST['id_sub_department'];
    $id_jadwal  = $_POST['id_jadwal'];
    $id_grup    = isset($_POST['id_grup']) && $_POST['id_grup'] !== '' ? intval($_POST['id_grup']) : NULL;
    $upah       = str_replace('.', '', $_POST['upah_manual']);

    $nama_manual = !empty($_POST['nama_karyawan_manual']) ? $koneksi->real_escape_string($_POST['nama_karyawan_manual']) : NULL;
    $idkaryawan  = !empty($_POST['id_karyawan']) ? $_POST['id_karyawan'] : 0;

    // HANDLE TAG (auto-create bila baru, tahan beda schema) + amankan FK
    require_once __DIR__ . '/import_lib.php';
    if (!empty($id_dept) && !is_numeric($id_dept)) {
        $id_dept = rx_ensure_dept($koneksi, $id_dept);
    }
    if (!empty($id_sub) && !is_numeric($id_sub)) {
        $id_sub = rx_ensure_sub($koneksi, $id_sub, (int)($id_dept ?? 0));
    }
    $id_dept = rx_valid_dept($koneksi, $id_dept ?? 0);
    $id_sub = rx_valid_sub($koneksi, $id_sub ?? 0, $id_dept);

    // VALIDASI
    if (($idkaryawan != 0 || $nama_manual != NULL) && !empty($id_jadwal) && $id_rkk_fix > 0) {

        // CEK DUPLIKAT
        if ($idkaryawan != 0) {
            $cek = $koneksi->query("SELECT id_karyawan FROM tb_rkk_detail WHERE id_rkk = '$id_rkk_fix' AND id_karyawan = '$idkaryawan'");

            if ($cek->num_rows > 0) {
                $alert = [
                    "icon" => "warning",
                    "title" => "Oops...",
                    "text" => "Karyawan ini sudah ada!"
                ];
                $redirect = "?page=rkk&aksi=karyawan&id=$id_rkk_fix";
            }
        }

        // SIMPAN
        if (!$alert) {
            $val_manual = ($nama_manual == NULL) ? "NULL" : "'$nama_manual'";

            $val_grup = ($id_grup === NULL) ? "NULL" : "'$id_grup'";
            $query = "INSERT INTO tb_rkk_detail 
            (id_rkk, id_karyawan, nama_karyawan_manual, upah, id_departmen, id_sub_department, id_jadwal, id_grup, status_rkk, 
             potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) 
            VALUES 
            ($id_rkk_fix, '$idkaryawan', $val_manual, '$upah', '$id_dept', '$id_sub', '$id_jadwal', $val_grup, 'Hadir', 
             '0', '0', '0', NOW())";

            if ($koneksi->query($query)) {
                $alert = [
                    "icon" => "success",
                    "title" => "Berhasil!",
                    "text" => "Karyawan berhasil ditambahkan"
                ];
                $redirect = "?page=rkk&aksi=karyawan&id=$id_rkk_fix";
            } else {
                $alert = [
                    "icon" => "error",
                    "title" => "Error",
                    "text" => $koneksi->error
                ];
            }
        }
    }
}

// SIMPAN BATCH DARI GRUP (anggota grup + tambahan manual)
if (isset($_POST['simpan_grup'])) {

    $id_rkk_fix = intval($_POST['id_rkk_hidden']);
    $id_jadwal  = $_POST['id_jadwal_batch'] ?? '';
    $id_grup    = isset($_POST['id_grup_batch']) && $_POST['id_grup_batch'] !== '' ? intval($_POST['id_grup_batch']) : NULL;
    $kids       = $_POST['m_kar'] ?? [];
    $upahs      = $_POST['m_upah'] ?? [];

    $cek_st = $koneksi->query("SELECT status_rkk FROM tb_rkk WHERE id_rkk = '$id_rkk_fix'");
    $row_st = $cek_st ? $cek_st->fetch_assoc() : null;
    if ($row_st && $row_st['status_rkk'] >= 2) {
        $alert = ["icon" => "error", "title" => "Akses Ditolak", "text" => "RKK sudah Approved/Realized!"];
        $redirect = "?page=rkk&aksi=kelola&id=$id_rkk_fix";
    } elseif (empty($id_jadwal) || $id_rkk_fix <= 0) {
        $alert = ["icon" => "warning", "title" => "Belum lengkap", "text" => "Pilih shift dan pastikan ada anggota."];
        $redirect = "?page=rkk&aksi=karyawan&id=$id_rkk_fix";
    } else {
        $added = 0; $skipped = 0;
        $val_grup = ($id_grup === NULL) ? "NULL" : "'$id_grup'";
        $seen = [];
        for ($i = 0; $i < count($kids); $i++) {
            $kid = (int)($kids[$i] ?? 0);
            if ($kid <= 0 || isset($seen[$kid])) { if ($kid > 0) $skipped++; continue; }
            $seen[$kid] = true;
            $cek = $koneksi->query("SELECT id_rkk_detail FROM tb_rkk_detail WHERE id_rkk = '$id_rkk_fix' AND id_karyawan = '$kid'");
            if ($cek && $cek->num_rows > 0) { $skipped++; continue; }
            $mk = $koneksi->query("SELECT id_departmen, id_sub_department, id_jadwal, upah FROM ms_karyawan WHERE id_karyawan = '$kid'")->fetch_assoc();
            if (!$mk) { $skipped++; continue; }
            $dep = ((int)($mk['id_departmen'] ?? 0) > 0) ? "'" . (int)$mk['id_departmen'] . "'" : "NULL";
            $sub = ((int)($mk['id_sub_department'] ?? 0) > 0) ? "'" . (int)$mk['id_sub_department'] . "'" : "NULL";
            $up = trim($upahs[$i] ?? '');
            $upah = ($up === '') ? (int)($mk['upah'] ?? 0) : (int)str_replace('.', '', $up);
            $ok = $koneksi->query("INSERT INTO tb_rkk_detail
                (id_rkk, id_karyawan, nama_karyawan_manual, upah, id_departmen, id_sub_department, id_jadwal, id_grup, status_rkk,
                 potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt)
                VALUES
                ($id_rkk_fix, '$kid', NULL, '$upah', $dep, $sub, '$id_jadwal', $val_grup, 'Hadir',
                 '0', '0', '0', NOW())");
            if ($ok) $added++; else $skipped++;
        }
        if ($added > 0) {
            $alert = ["icon" => "success", "title" => "Berhasil!", "text" => "$added ditambahkan" . ($skipped > 0 ? ", $skipped sudah ada/dilewati" : "")];
            $redirect = "?page=rkk&aksi=kelola&id=$id_rkk_fix";
        } else {
            $alert = ["icon" => "warning", "title" => "Tidak ada yang disimpan", "text" => "Semua sudah ada di daftar."];
            $redirect = "?page=rkk&aksi=karyawan&id=$id_rkk_fix";
        }
    }
}

// Ambil data master
$list_dept   = $koneksi->query("SELECT * FROM ms_departmen ORDER BY nama_departmen ASC");
$list_sub    = $koneksi->query("SELECT * FROM ms_sub_department ORDER BY nama_sub_department ASC");
$list_jadwal = $koneksi->query("SELECT * FROM tb_jadwal ORDER BY id_jadwal ASC");

// Upah global (Deprecated - will be removed soon)
$g_harian   = 0;
$g_mingguan = 0;
$g_bulanan  = 0;
// Data grup + anggotanya untuk mode "Dari Grup"
$grup_data = [];
$qg = $koneksi->query("SELECT id_grup, nama_grup, harga FROM ms_grup_borongan ORDER BY nama_grup ASC");
while ($qg && ($gg = $qg->fetch_assoc())) {
    $gid = (int)$gg['id_grup'];
    $grup_data[$gid] = ['nama' => $gg['nama_grup'], 'harga' => $gg['harga'], 'anggota' => []];
    $qa = $koneksi->query("SELECT a.id_karyawan, a.tarif, K.no_absen, K.nama_karyawan,
        K.id_departmen, K.id_sub_department, K.id_jadwal, K.upah,
        D.nama_departmen, S.nama_sub_department
        FROM ms_grup_borongan_anggota a
        LEFT JOIN ms_karyawan K ON a.id_karyawan = K.id_karyawan
        LEFT JOIN ms_departmen D ON K.id_departmen = D.id_departmen
        LEFT JOIN ms_sub_department S ON K.id_sub_department = S.id_sub_department
        WHERE a.id_grup = '$gid' ORDER BY K.nama_karyawan ASC");
    while ($qa && ($aa = $qa->fetch_assoc())) $grup_data[$gid]['anggota'][] = $aa;
}
// Peta semua karyawan aktif untuk tombol "tambah manual" di mode grup
$kar_all = [];
$qk = $koneksi->query("SELECT K.id_karyawan, K.no_absen, K.nama_karyawan, K.id_departmen, K.id_sub_department, K.id_jadwal, K.upah, D.nama_departmen, S.nama_sub_department FROM ms_karyawan K LEFT JOIN ms_departmen D ON K.id_departmen = D.id_departmen LEFT JOIN ms_sub_department S ON K.id_sub_department = S.id_sub_department WHERE K.status_karyawan = 'Aktif' ORDER BY K.nama_karyawan ASC");
while ($qk && ($kk = $qk->fetch_assoc())) $kar_all[(int)$kk['id_karyawan']] = $kk;
?>

<div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8 mt-4 sm:mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">

        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-5 sm:px-8 py-5 sm:py-6">
            <h3 class="text-xl sm:text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-user-plus mr-3"></i>
                Tambah Karyawan ke RKK
            </h3>
            <p class="text-slate-300 text-xs sm:text-sm mt-1">Pilih karyawan dan tentukan shift kerja untuk rencana ini</p>
        </div>

        <div class="px-5 sm:px-8 pt-5 flex gap-2">
            <button type="button" id="btn_mode_manual" class="px-4 py-2 text-sm font-bold rounded-xl bg-blue-600 text-white">Isi Sendiri</button>
            <button type="button" id="btn_mode_grup" class="px-4 py-2 text-sm font-bold rounded-xl bg-gray-200 text-gray-700">Dari Grup</button>
        </div>

        <div id="mode_manual">
        <form method="POST">
            <input type="hidden" name="id_rkk_hidden" value="<?= $idrkk; ?>">
            <div class="p-5 sm:p-8">
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-gray-700">Pilih No. Absen / Nama Karyawan <span class="text-rose-500">*</span></label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="toggle_manual" class="sr-only peer">
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ms-3 text-xs font-medium text-gray-600 italic">Ketik Manual?</span>
                        </label>
                    </div>

                    <div id="container_select">
                        <select name="id_karyawan" id="search_karyawan" required
                            class="select2-manage block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none font-medium text-sm sm:text-base"
                            data-placeholder="Cari No. Absen atau Nama">
                            <option value=""></option>
                            <?php
                            $master = $koneksi->query("SELECT K.*, G.golongan as label_gol 
                                     FROM ms_karyawan K 
                                     LEFT JOIN ms_golongan G ON K.id_golongan = G.id_golongan 
                                     WHERE K.status_karyawan = 'Aktif' 
                                     ORDER BY K.nama_karyawan ASC");
                            while ($row = $master->fetch_assoc()) {
                                echo "<option value='" . $row['id_karyawan'] . "' 
                        data-jk='" . $row['jenis_kelamin'] . "' 
                        data-tgl='" . $row['tgl_aktif'] . "'
                        data-golongan='" . $row['label_gol'] . "'
                        data-upah='" . $row['upah'] . "'
                        data-dept='" . ($row['id_departmen'] ?? '') . "'
                        data-sub='" . ($row['id_sub_department'] ?? '') . "'>" . $row['no_absen'] . " | " . $row['nama_karyawan'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div id="container_manual" class="hidden">
                        <input type="text" name="nama_karyawan_manual" id="nama_karyawan_manual"
                            placeholder="Masukkan Nama Karyawan Manual..."
                            class="block w-full px-4 py-3 border border-blue-300 rounded-xl bg-blue-50 focus:ring-2 focus:ring-blue-500 outline-none font-medium text-sm sm:text-base" />
                        <p class="text-[10px] text-blue-500 mt-1">* Input ini digunakan jika karyawan tidak terdaftar di database.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-2">Jenis Kelamin</label>
                        <input type="text" id="jk" readonly class="block w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed outline-none font-medium text-sm sm:text-base" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-2">Tanggal Aktif</label>
                        <input type="text" id="tgl_aktif" readonly class="block w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed outline-none font-medium text-sm sm:text-base" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-600 mb-2">Upah <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 font-bold text-sm">Rp</div>
                            <input type="text" name="upah_manual" id="upah_manual" required placeholder="0" readonly
                                class="block w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl bg-gray-100 text-gray-500 cursor-not-allowed outline-none font-medium text-sm sm:text-base" />
                        </div>
                    </div>
                </div>

                <div class="border-t border-dashed border-gray-200 my-8"></div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Shift Kerja <span class="text-rose-500">*</span></label>
                        <select name="id_jadwal" id="id_jadwal" required
                            class="select2-manage block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none font-medium text-sm sm:text-base">
                            <option value="">- Pilih Shift -</option>
                            <?php $list_jadwal->data_seek(0);
                            while ($j = $list_jadwal->fetch_assoc()) { ?>
                                <option value="<?= $j['id_jadwal']; ?>"><?= $j['keterangan']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Departemen <span class="text-rose-500">*</span></label>
                        <select name="id_departmen" id="id_departmen" required data-tags="true" class="select2-manage block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none text-sm sm:text-base">
                            <option value="">- Pilih Dept -</option>
                            <?php $list_dept->data_seek(0);
                            while ($d = $list_dept->fetch_assoc()) { ?>
                                <option value="<?= $d['id_departmen']; ?>"><?= $d['nama_departmen']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Sub Bagian <span class="text-rose-500">*</span></label>
                        <select name="id_sub_department" id="id_sub_department" required data-tags="true" class="select2-manage block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none text-sm sm:text-base">
                            <option value="">- Pilih Sub -</option>
                            <?php $list_sub->data_seek(0);
                            while ($s = $list_sub->fetch_assoc()) { ?>
                                <option value="<?= $s['id_sub_department']; ?>"><?= $s['nama_sub_department']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Grup (opsional)</label>
                        <select name="id_grup" id="id_grup" class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none text-sm sm:text-base">
                            <option value="">- Tanpa Grup -</option>
                            <?php $list_grup = $koneksi->query("SELECT id_grup, nama_grup, harga FROM ms_grup_borongan ORDER BY nama_grup ASC");
                            while ($list_grup && ($gg = $list_grup->fetch_assoc())) { ?>
                                <option value="<?= $gg['id_grup']; ?>"><?= htmlspecialchars($gg['nama_grup']); ?><?= ($gg['harga'] !== null && $gg['harga'] !== '') ? ' (Rp ' . number_format($gg['harga'], 0, ',', '.') . '/kg)' : ''; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic order-2 sm:order-1 text-center sm:text-left">
                        <span class="text-rose-500">*</span> Pastikan data penempatan benar
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto order-1 sm:order-2">
                        <a href="?page=rkk&aksi=kelola&id=<?= $idrkk; ?>" class="flex justify-center items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                            <i class="fas fa-arrow-left mr-2"></i>  Kembali
                        </a>
                        <button type="submit" name="simpan_karyawan" value="1" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i> Simpan ke Daftar
                        </button>
                    </div>
                </div>
            </div>
        </form>
        </div><!-- /mode_manual -->

        <div id="mode_grup" class="hidden">
        <form method="POST" id="formGrup">
            <input type="hidden" name="id_rkk_hidden" value="<?= $idrkk; ?>">
            <input type="hidden" name="id_grup_batch" id="id_grup_batch" value="">
            <div class="p-5 sm:p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Pilih Grup <span class="text-rose-500">*</span></label>
                        <select id="grup_pick" class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none font-medium text-sm sm:text-base">
                            <option value="">- Pilih Grup -</option>
                            <?php foreach ($grup_data as $gid => $gd): ?>
                                <option value="<?= $gid ?>"><?= htmlspecialchars($gd['nama']) ?> (<?= count($gd['anggota']) ?> anggota)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1" id="grup_info">Pilih grup untuk memuat anggotanya. Bisa tambah/hapus baris sebelum disimpan.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Shift untuk Semua <span class="text-rose-500">*</span></label>
                        <select name="id_jadwal_batch" required class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none font-medium text-sm sm:text-base">
                            <option value="">- Pilih Shift -</option>
                            <?php $list_jadwal->data_seek(0);
                            while ($j = $list_jadwal->fetch_assoc()) { ?>
                                <option value="<?= $j['id_jadwal']; ?>"><?= $j['keterangan']; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive rounded-lg border border-gray-300 overflow-hidden mb-4">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Karyawan</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase">Penempatan</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase text-right">Upah</th>
                                <th class="py-2 px-3 text-xs font-bold text-gray-700 uppercase text-center" style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="stageBody">
                            <tr id="stageEmpty"><td colspan="4" class="py-6 px-3 text-center text-sm text-gray-400 italic">Belum ada baris — pilih grup di atas.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mb-6">
                    <select id="add_kar" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl bg-white text-sm">
                        <option value="">- Tambah manual: pilih karyawan -</option>
                        <?php foreach ($kar_all as $kid => $kk): ?>
                            <option value="<?= $kid ?>"><?= htmlspecialchars($kk['nama_karyawan']) ?> (<?= htmlspecialchars($kk['no_absen']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="btnAddKar" class="px-4 py-2.5 text-sm font-bold rounded-xl border border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white transition whitespace-nowrap"><i class="fas fa-plus mr-1"></i>Tambah</button>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic order-2 sm:order-1" id="stageCount"></div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto order-1 sm:order-2">
                        <a href="?page=rkk&aksi=kelola&id=<?= $idrkk; ?>" class="flex justify-center items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Kembali
                        </a>
                        <button type="submit" name="simpan_grup" value="1" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            <i class="fas fa-users mr-2"></i> Simpan ke RKK
                        </button>
                    </div>
                </div>
            </div>
        </form>
        </div><!-- /mode_grup -->
    </div>
</div>

<?php if ($alert): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: "<?= $alert['icon'] ?>",
    title: "<?= $alert['title'] ?>",
    text: "<?= $alert['text'] ?>",
    confirmButtonColor: "#2563eb"
}).then(() => {
    <?php if ($redirect): ?>
        window.location.href = "<?= $redirect ?>";
    <?php endif; ?>
});
</script>
<?php endif; ?>

<script>
    const globalRates = {
        harian: <?= $g_harian ?>,
        mingguan: <?= $g_mingguan ?>,
        bulanan: <?= $g_bulanan ?>
    };

    function formatRupiah(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    $(document).ready(function() {
        $('#search_karyawan').on('change', function() {
            var $selected = $(this).find(':selected');

            if ($(this).val() === "") {
                $('#jk').val("");
                $('#tgl_aktif').val("");
                $('#upah_manual').val("");
                return;
            }

            var finalWage = $selected.data('upah') || 0;

            $('#jk').val($selected.data('jk'));
            $('#tgl_aktif').val($selected.data('tgl'));
            $('#upah_manual').val(formatRupiah(finalWage));
            $('#id_departmen').val($selected.data('dept')).trigger('change');
            $('#id_sub_department').val($selected.data('sub')).trigger('change');
        });
    });
</script>
<script>
    $(document).ready(function() {
        $("#search_karyawan").on("select2:open", function() {
            setTimeout(function() {
                var searchField = document.querySelector(".select2-search__field");
                if (searchField) {
                    searchField.setAttribute("placeholder", "Contoh: 001 | Ammar");
                }
            }, 50);
        });
    });
</script>
<script>
    document.getElementById('toggle_manual').addEventListener('change', function() {
        const isManual = this.checked;
        const containerSelect = document.getElementById('container_select');
        const containerManual = document.getElementById('container_manual');
        const selectEl = document.getElementById('search_karyawan');
        const inputManual = document.getElementById('nama_karyawan_manual');

        const autoFields = ['jk', 'tgl_aktif', 'upah_manual'];

        if (isManual) {
            containerSelect.classList.add('hidden');
            containerManual.classList.remove('hidden');

            // Atur required
            selectEl.removeAttribute('required');
            inputManual.setAttribute('required', 'required');

            // Reset dan aktifkan field yang tadinya readonly agar bisa diisi manual
            autoFields.forEach(id => {
                const el = document.getElementById(id);
                el.value = '';
                el.readOnly = false;
                el.classList.remove('bg-gray-100', 'cursor-not-allowed');
                el.classList.add('bg-white');
            });

            // Reset Select2 value jika ada
            if ($(selectEl).data('select2')) {
                $(selectEl).val(null).trigger('change');
            }
        } else {
            containerSelect.classList.remove('hidden');
            containerManual.classList.add('hidden');

            // Atur required
            selectEl.setAttribute('required', 'required');
            inputManual.removeAttribute('required');

            // Kembalikan field ke mode readonly
            autoFields.forEach(id => {
                const el = document.getElementById(id);
                el.readOnly = true;
                el.classList.add('bg-gray-100', 'cursor-not-allowed');
                el.classList.remove('bg-white');
            });
        }
    });
</script>
<script>
    const GRUP_DATA = <?= json_encode($grup_data, JSON_UNESCAPED_UNICODE) ?>;
    const KAR_ALL = <?= json_encode($kar_all, JSON_UNESCAPED_UNICODE) ?>;

    function escHtml(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // --- Toggle mode Isi Sendiri / Dari Grup ---
    const btnM = document.getElementById('btn_mode_manual');
    const btnG = document.getElementById('btn_mode_grup');
    const onCls = ['bg-blue-600', 'text-white'];
    const offCls = ['bg-gray-200', 'text-gray-700'];
    function setMode(grup) {
        document.getElementById('mode_manual').classList.toggle('hidden', grup);
        document.getElementById('mode_grup').classList.toggle('hidden', !grup);
        btnM.classList.remove(...(grup ? onCls : offCls));
        btnM.classList.add(...(grup ? offCls : onCls));
        btnG.classList.remove(...(grup ? offCls : onCls));
        btnG.classList.add(...(grup ? onCls : offCls));
    }
    btnM.addEventListener('click', function() { setMode(false); });
    btnG.addEventListener('click', function() { setMode(true); });

    // --- Staging list mode grup ---
    function stagedIds() {
        const s = {};
        document.querySelectorAll('#stageBody tr[data-kid]').forEach(function(tr) { s[tr.getAttribute('data-kid')] = true; });
        return s;
    }
    function updateCount() {
        const n = document.querySelectorAll('#stageBody tr[data-kid]').length;
        document.getElementById('stageCount').innerText = n + ' baris siap disimpan';
        const empty = document.getElementById('stageEmpty');
        if (empty) empty.style.display = n > 0 ? 'none' : '';
    }
    function addStageRow(m) {
        if (!m || !m.id_karyawan || stagedIds()[String(m.id_karyawan)]) return false;
        const tb = document.querySelector('#stageBody');
        const tr = document.createElement('tr');
        tr.setAttribute('data-kid', m.id_karyawan);
        tr.className = 'border-t border-gray-200';
        const hasTarif = (m.tarif !== null && m.tarif !== undefined && String(m.tarif) !== '');
        const tarifTxt = hasTarif ? ('Rp ' + Number(m.tarif).toLocaleString('id-ID') + '/kg khusus') : 'ikut tarif grup';
        tr.innerHTML =
            '<td class="py-2 px-3 text-sm"><b>' + escHtml(m.nama_karyawan) + '</b> <span class="text-gray-400">(' + escHtml(m.no_absen) + ')</span><br><span class="text-[11px] text-purple-700">' + escHtml(tarifTxt) + '</span><input type="hidden" name="m_kar[]" value="' + m.id_karyawan + '"></td>'
            + '<td class="py-2 px-3 text-sm">' + escHtml(m.nama_departmen || '-') + '<br><span class="text-gray-500 text-xs">' + escHtml(m.nama_sub_department || '') + '</span></td>'
            + '<td class="py-2 px-3"><input type="number" name="m_upah[]" value="' + (parseInt(m.upah) || 0) + '" class="w-28 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-right"></td>'
            + '<td class="py-2 px-3 text-center"><button type="button" class="rmStage px-2 py-1 text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded hover:bg-rose-600 hover:text-white">X</button></td>';
        tb.appendChild(tr);
        updateCount();
        return true;
    }

    document.getElementById('grup_pick').addEventListener('change', function() {
        const gid = this.value;
        document.querySelectorAll('#stageBody tr[data-kid]').forEach(function(tr) { tr.remove(); });
        document.getElementById('id_grup_batch').value = gid;
        const info = document.getElementById('grup_info');
        if (!gid || !GRUP_DATA[gid]) {
            info.innerText = 'Pilih grup untuk memuat anggotanya. Bisa tambah/hapus baris sebelum disimpan.';
            updateCount();
            return;
        }
        const gd = GRUP_DATA[gid];
        const rateTxt = (gd.harga !== null && gd.harga !== '') ? ('Rp ' + Number(gd.harga).toLocaleString('id-ID') + '/kg') : 'tanpa tarif';
        info.innerText = gd.anggota.length + ' anggota terdaftar — tarif grup ' + rateTxt + '. Hapus baris yang tidak ikut, atau tambah manual di bawah.';
        gd.anggota.forEach(function(m) { addStageRow(m); });
        updateCount();
    });

    document.getElementById('btnAddKar').addEventListener('click', function() {
        const sel = document.getElementById('add_kar');
        if (!sel.value) return;
        const m = KAR_ALL[sel.value];
        if (!m) return;
        m.tarif = null;
        if (!addStageRow(m)) alert('Karyawan ini sudah ada di daftar.');
        else sel.value = '';
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.rmStage');
        if (btn) {
            btn.closest('tr').remove();
            updateCount();
        }
    });

    document.getElementById('formGrup').addEventListener('submit', function(e) {
        if (document.querySelectorAll('#stageBody tr[data-kid]').length === 0) {
            e.preventDefault();
            alert('Daftar masih kosong — pilih grup atau tambah karyawan dulu.');
        }
    });
    updateCount();
</script>