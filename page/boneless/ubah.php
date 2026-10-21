<?php
$ref = $_GET['ref'] ?? 'boneless';
$view_param = isset($_GET['view']) ? '&view=1' : '';
$id = $_GET['id'] ?? '';
include_once __DIR__ . '/mobil_lib.php';

// Pastikan tabel & kolom satuan ada (sama seperti tambah.php)
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_boneless_satuan (
  id_satuan INT AUTO_INCREMENT PRIMARY KEY,
  id_boneless INT NOT NULL,
  nama VARCHAR(255) NOT NULL,
  hasil DECIMAL(12,2) NOT NULL DEFAULT 0,
  upah INT NOT NULL DEFAULT 0,
  KEY idx_boneless (id_boneless)
) ENGINE=InnoDB");
$ckHS = $koneksi->query("SHOW COLUMNS FROM tb_boneless LIKE 'harga_satuan'");
if ($ckHS && $ckHS->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_boneless ADD COLUMN harga_satuan INT NULL DEFAULT NULL");
}
// Kolom per-baris: satuan (KG/EKOR) + harga (upah = hasil x harga per baris, tidak lagi dibagi rata)
$ckSatU = $koneksi->query("SHOW COLUMNS FROM tb_boneless_satuan LIKE 'satuan'");
if ($ckSatU && $ckSatU->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_boneless_satuan ADD COLUMN satuan VARCHAR(10) NOT NULL DEFAULT 'KG'");
}
$ckSatH = $koneksi->query("SHOW COLUMNS FROM tb_boneless_satuan LIKE 'harga'");
if ($ckSatH && $ckSatH->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_boneless_satuan ADD COLUMN harga INT NOT NULL DEFAULT 0");
}

// 1. Ambil Data Header
if ($id) {
    $sql_header = $koneksi->query("SELECT * FROM tb_boneless WHERE id_boneless = '$id'");
    $header = $sql_header->fetch_assoc();
} else {
    $header = null;
}

// 2. Ambil Biaya Mobil (Master, info saja) + armada + link tersimpan
$sql_master = $koneksi->query("SELECT biaya_mobil FROM tb_biayamobil LIMIT 1");
$row_master = $sql_master->fetch_assoc();
$harga_per_mobil = $row_master['biaya_mobil'] ?? 0;
$fleet_form = function_exists('boneless_mobil_fleet') ? boneless_mobil_fleet($koneksi) : [];
$linked_map = [];
if (function_exists('boneless_mobil_links')) foreach (boneless_mobil_links($koneksi, $id) as $lm) $linked_map[(int)$lm['id_mobil']] = true;
// Data lama tanpa link: centang N pertama agar jumlah tidak berubah saat simpan
if (empty($linked_map) && (int)($header['jumlah_mobil'] ?? 0) > 0) {
    $nn = 0;
    foreach ($fleet_form as $fm) {
        if ($nn >= (int)$header['jumlah_mobil']) break;
        $linked_map[(int)$fm['id_mobil']] = true;
        $nn++;
    }
}

// 3. Ambil Data Detail
$sql_detail = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '$id'");
$details = [];
while ($row = $sql_detail->fetch_assoc()) {
    $details[] = $row;
}

// 3b. Ambil Data Karyawan Satuan
$sql_sat = $koneksi->query("SELECT * FROM tb_boneless_satuan WHERE id_boneless = '$id' ORDER BY id_satuan ASC");
$satuan = [];
if ($sql_sat) while ($row = $sql_sat->fetch_assoc()) {
    $satuan[] = $row;
}
$harga_satuan_saved = $header['harga_satuan'] ?? 800;
if ($harga_satuan_saved === null || $harga_satuan_saved === '') $harga_satuan_saved = 800;

// 4. Proses Simpan Perubahan
$simpan = @$_POST['simpan'];
if ($simpan) {
    $id_rkk = @$_POST['id_rkk'];
    $tgl = @$_POST['tgl'];
    $keterangan = @$_POST['keterangan'];

    // Truck yg dicentang (snapshot nama+harga master saat simpan)
    $fleet_map = [];
    if (function_exists('boneless_mobil_fleet')) foreach (boneless_mobil_fleet($koneksi) as $fm) $fleet_map[(int)$fm['id_mobil']] = $fm;
    $trucks = [];
    foreach ((array)($_POST['mob_pick'] ?? []) as $mid_raw) {
        $mid = (int)$mid_raw;
        if ($mid <= 0 || !isset($fleet_map[$mid])) continue;
        $trucks[] = $fleet_map[$mid];
    }
    $jumlah_mobil = count($trucks);

    // Update Header
    $koneksi->query("UPDATE tb_boneless SET 
        id_rkk = '$id_rkk', 
        tgl = '$tgl', 
        jumlah_mobil = '$jumlah_mobil', 
        keterangan = '$keterangan' 
        WHERE id_boneless = '$id'");

    // Sync link truck: hapus lama, masukkan yg dicentang
    $koneksi->query("DELETE FROM tb_boneless_mobil WHERE id_boneless = '$id'");
    foreach ($trucks as $tr) {
        $nm = $koneksi->real_escape_string($tr['nama']);
        $hg = (float)($tr['harga'] ?? 0);
        $koneksi->query("INSERT INTO tb_boneless_mobil (id_boneless, id_mobil, nama, harga) VALUES ('$id', '" . (int)$tr['id_mobil'] . "', '$nm', '$hg')");
    }

    // Sync Details: Hapus yang lama, masukkan yang baru
    $koneksi->query("DELETE FROM tb_boneless_detail WHERE id_boneless = '$id'");

    $nama_items = (array)($_POST['nama_item'] ?? []);
    $qtys = (array)($_POST['qty'] ?? []);
    $hargas = (array)($_POST['harga'] ?? []);
    $types = (array)($_POST['type'] ?? []);

    if (!empty($nama_items)) {
        for ($i = 0; $i < count($nama_items); $i++) {
            $item = $koneksi->real_escape_string($nama_items[$i] ?? '');
            if ($item === '') continue;
            $qty = (float)(($qtys[$i] ?? 0) ?: 0);
            $hrg = (float)(($hargas[$i] ?? 0) ?: 0);
            $type = $koneksi->real_escape_string($types[$i] ?? 'plus');

            // MODIFIKASI: Simpan total selalu POSITIF (Absolute)
            $ttl = abs($qty * $hrg);

            $koneksi->query("INSERT INTO tb_boneless_detail (id_boneless, nama_item, qty, harga, total, jenis)
                             VALUES ('$id', '$item', '$qty', '$hrg', '$ttl', '$type')");
        }
    }

    // Sync Karyawan Satuan: hapus lama, masukkan baru (upah per baris: hasil x harga)
    $koneksi->query("DELETE FROM tb_boneless_satuan WHERE id_boneless = '$id'");
    $sat_nama = $_POST['sat_nama'] ?? [];
    $sat_hasil = $_POST['sat_hasil'] ?? [];
    $sat_satuan = $_POST['sat_satuan'] ?? [];
    $sat_harga = $_POST['sat_harga'] ?? [];
    for ($i = 0; $i < count($sat_nama); $i++) {
        $nm = trim($sat_nama[$i] ?? '');
        if ($nm === '') continue;
        $q = (float)($sat_hasil[$i] ?? 0);
        $u = strtoupper(trim($sat_satuan[$i] ?? 'KG'));
        if ($u !== 'EKOR') $u = 'KG';
        $h = (float)($sat_harga[$i] ?? 0);
        $nm_esc = $koneksi->real_escape_string($nm);
        $up_sat = (int)round($q * $h);
        $koneksi->query("INSERT INTO tb_boneless_satuan (id_boneless, nama, hasil, upah, satuan, harga)
                         VALUES ('$id', '$nm_esc', '$q', '$up_sat', '$u', '$h')");
    }

    echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
        <script>
            Swal.fire({ icon: "success", title: "Berhasil", text: "Data Berhasil Diubah", confirmButtonColor: "#2563eb" })
            .then(() => { window.location.href = "?page=boneless&ref=' . $ref . $view_param . '"; });
        </script></body></html>';
    exit;
}
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
                <div class="border-b border-gray-100 py-4 px-4 bg-white flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <a href="?page=boneless&ref=<?= $ref ?><?= $view_param ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-50 text-gray-400 border border-gray-200">
                            <i class="fas fa-arrow-left text-xs"></i>
                        </a>
                        <h3 class="text-xl font-bold m-0" style="color: #2563eb;">Ubah Data Boneless</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="page/boneless/excel_gabung.php?id=<?= $id ?>" class="inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold py-2 px-4 rounded shadow-sm transition-colors">
                            <i class="fas fa-file-excel mr-1.5"></i> Download Excel (2 sheets)
                        </a>
                        <span class="text-xs font-bold text-gray-400 bg-gray-50 px-2 py-1 rounded">ID: #<?= $id ?></span>
                    </div>
                </div>

                <div class="p-4 md:p-5">
                    <form method="POST">
                        <div class="row mb-4">
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Data Rencana (Locked)</label>
                                <?php
                                $id_rkk_saved = $header['id_rkk'] ?? 0;
                                $display_rencana = "Data Rencana Tidak Ditemukan";
                                
                                if ($id_rkk_saved > 0) {
                                    $sql_rkk_fixed = $koneksi->query("SELECT * FROM tb_rkk WHERE id_rkk = '$id_rkk_saved'");
                                    if ($row_fixed = $sql_rkk_fixed->fetch_assoc()) {
                                        $tgl_rkk = $row_fixed['tgl_rkk'] ?? '';
                                        $ket_rkk = $row_fixed['keterangan'] ?? '';
                                        $display_rencana = ($tgl_rkk ? date('d-m-Y', strtotime($tgl_rkk)) : '-') . ' - ' . $ket_rkk;
                                    }
                                }
                                ?>
                                <div class="h-[42px] flex items-center px-3 bg-gray-100 border border-gray-200 rounded-lg text-sm text-gray-600">
                                    <i class="fas fa-lock mr-2 opacity-50"></i> <?= htmlspecialchars($display_rencana) ?>
                                </div>
                                <input type="hidden" name="id_rkk" value="<?= $id_rkk_saved ?>">
                                <input type="hidden" name="tgl" value="<?= $header['tgl'] ?? '' ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Jumlah Mobil (otomatis)</label>
                                <div class="h-[42px] flex items-center px-3 bg-amber-50 border border-amber-200 rounded-lg font-black text-amber-700">
                                    <i class="fas fa-truck mr-2"></i> <span id="mobCountBadge"><?= count($linked_map) ?> Unit</span>
                                </div>
                                <input type="hidden" name="jumlah_mobil" id="jumlah_mobil_hidden" value="<?= count($linked_map) ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Total Mobil (otomatis)</label>
                                <div class="h-[42px] flex items-center px-3 bg-amber-50 border border-amber-200 rounded-lg font-black text-amber-700" id="mobTotalView">Rp 0</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="text-xs font-bold text-amber-700 uppercase mb-3 block"><i class="fas fa-truck mr-1"></i> Mobil yg Jalan (centang dari master)</label>
                            <?php if (empty($fleet_form)): ?>
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">Belum ada armada. Isi dulu di <a href="?page=boneless&aksi=master" class="font-bold underline">MASTER → Mobil</a>.</div>
                            <?php else: ?>
                            <div id="mobCheckList" class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                <?php foreach ($fleet_form as $fm):
                                    $mid = (int)$fm['id_mobil'];
                                    $checked = isset($linked_map[$mid]) ? 'checked' : '';
                                ?>
                                <label class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-amber-400 text-sm font-semibold text-gray-700">
                                    <input type="checkbox" name="mob_pick[]" value="<?= $mid ?>" data-harga="<?= (float)($fm['harga'] ?? 0) ?>" class="mob-pick w-4 h-4 text-amber-600 rounded" <?= $checked ?>>
                                    <span><?= htmlspecialchars($fm['nama']) ?> <span class="text-gray-400 font-normal">Rp <?= number_format($fm['harga'] ?? 0, 0, ',', '.') ?></span></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <hr class="my-5 border-gray-200">

                        <div class="mt-4">
                            <label class="text-xs font-bold text-green-700 uppercase mb-3 block">Tim Tanpa Mesin Boneless</label>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tablePlus">
                                    <thead class="bg-green-50">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">#</th>
                                            <th>Nama Tim</th>
                                            <th style="width: 120px;">QTY</th>
                                            <th style="width: 150px;">Harga</th>
                                            <th style="width: 180px;">Total</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no_p = 1;
                                        foreach ($details as $row): if (isset($row['jenis']) && $row['jenis'] == 'plus'): ?>
                                                <tr class="item-row row-plus">
                                                    <td class="text-center font-bold text-gray-300"><?= $no_p++ ?></td>
                                                    <td>
                                                        <input type="text" name="nama_item[]" class="form-control" value="<?= $row['nama_item'] ?>" required>
                                                        <input type="hidden" name="type[]" value="plus">
                                                    </td>
                                                    <td><input type="number" step="any" name="qty[]" class="form-control text-center qty-input" value="<?= (float)$row['qty'] ?>"></td>
                                                    <td><input type="number" name="harga[]" class="form-control text-center harga-input" value="<?= (float)$row['harga'] ?>"></td>
                                                    <td><input type="number" name="total[]" class="form-control text-right font-bold text-green-600 bg-green-50 total-row-input" readonly value="<?= abs($row['total']) ?>"></td>
                                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                                </tr>
                                        <?php endif;
                                        endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" id="addRowPlus" class="btn btn-sm btn-outline-success font-bold"><i class="fas fa-plus mr-1"></i> Tambah Item Plus</button>
                            </div>
                        </div>

                        <hr class="my-5 border-gray-200">

                        <div class="mt-4">
                            <label class="text-xs font-bold text-red-700 uppercase mb-3 block">Tim Dengan Mesin Boneless</label>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tableMinus">
                                    <thead class="bg-red-50">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">#</th>
                                            <th>Nama Tim</th>
                                            <th style="width: 120px;">QTY</th>
                                            <th style="width: 150px;">Harga</th>
                                            <th style="width: 180px;">Total</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no_m = 1;
                                        foreach ($details as $row): if (isset($row['jenis']) && $row['jenis'] == 'minus'): ?>
                                                <tr class="item-row row-minus">
                                                    <td class="text-center font-bold text-gray-300"><?= $no_m++ ?></td>
                                                    <td>
                                                        <input type="text" name="nama_item[]" class="form-control" value="<?= $row['nama_item'] ?>" required>
                                                        <input type="hidden" name="type[]" value="minus">
                                                    </td>
                                                    <td><input type="number" step="any" name="qty[]" class="form-control text-center qty-input" value="<?= (float)$row['qty'] ?>"></td>
                                                    <td><input type="number" name="harga[]" class="form-control text-center harga-input" value="<?= (float)$row['harga'] ?>"></td>
                                                    <td><input type="number" name="total[]" class="form-control text-right font-bold text-red-600 bg-red-50 total-row-input" readonly value="<?= abs($row['total']) ?>"></td>
                                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                                </tr>
                                        <?php endif;
                                        endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" id="addRowMinus" class="btn btn-sm btn-outline-danger font-bold"><i class="fas fa-plus mr-1"></i> Tambah Item Minus</button>
                            </div>
                        </div>

                        <hr class="my-5 border-gray-200">

                        <div class="mt-4">
                            <label class="text-xs font-bold text-indigo-700 uppercase mb-3 block">Karyawan Satuan (upah per baris)</label>
                            <div class="row mb-3">
                                <div class="col-md-12 form-group">
                                    <label class="text-xs font-bold text-gray-700 uppercase mb-1">Total Upah Satuan (otomatis)</label>
                                    <div class="h-[42px] flex items-center px-3 bg-indigo-50 border border-indigo-200 rounded-lg font-black text-indigo-700" id="satPerOrang">Rp 0</div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 italic mb-3">Rumus per baris: hasil &times; harga. Satuan bisa Kg atau Ekor per baris.</p>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tableSatuan">
                                    <thead class="bg-indigo-50">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">#</th>
                                            <th>Nama Sesuai KTP</th>
                                            <th style="width: 120px;">Hasil Kerja</th>
                                            <th style="width: 110px;">Satuan</th>
                                            <th style="width: 140px;">Harga (Rp)</th>
                                            <th style="width: 170px;">Upah Dibayar</th>
                                            <th style="width: 60px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sat_prefill = $satuan;
                                        if (empty($sat_prefill)) {
                                            foreach (["MULYANTI", "EDAH", "YUSI HASTUTIK", "RUSDIANA", "HARNI", "CICHA MARYANA", "SITI PATONAH", "RUTINAH", "TIARA VETI FEBRIANA"] as $dn) {
                                                $sat_prefill[] = ['nama' => $dn, 'hasil' => '', 'satuan' => 'KG', 'harga' => 800];
                                            }
                                        }
                                        $sn = 1;
                                        foreach ($sat_prefill as $sr):
                                            $sr_unit = strtoupper(trim($sr['satuan'] ?? 'KG'));
                                            if ($sr_unit !== 'EKOR') $sr_unit = 'KG';
                                            $sr_hrg = (float)($sr['harga'] ?? 0);
                                            if ($sr_hrg <= 0) $sr_hrg = (float)$harga_satuan_saved; // data lama: ikut harga header
                                        ?>
                                            <tr class="sat-row">
                                                <td class="text-center font-bold text-gray-400"><?= $sn++ ?></td>
                                                <td><input type="text" name="sat_nama[]" class="form-control" value="<?= htmlspecialchars($sr['nama']) ?>"></td>
                                                <td><input type="number" step="any" name="sat_hasil[]" class="form-control text-center sat-qty" value="<?= htmlspecialchars($sr['hasil']) ?>"></td>
                                                <td>
                                                    <select name="sat_satuan[]" class="form-control text-center sat-unit">
                                                        <option value="KG" <?= $sr_unit === 'KG' ? 'selected' : '' ?>>KG</option>
                                                        <option value="EKOR" <?= $sr_unit === 'EKOR' ? 'selected' : '' ?>>EKOR</option>
                                                    </select>
                                                </td>
                                                <td><input type="number" name="sat_harga[]" class="form-control text-center sat-price" value="<?= $sr_hrg ?>"></td>
                                                <td><input type="text" class="form-control text-right font-bold text-indigo-600 bg-indigo-50 sat-upah-view" readonly value="Rp 0"></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" id="addSatRow" class="btn btn-sm btn-outline-primary font-bold"><i class="fas fa-plus mr-1"></i> Tambah Karyawan</button>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-bold text-gray-600 uppercase">Total Boneless Murni (tanpa mobil)</span>
                                <span class="text-lg font-extrabold text-gray-800" id="summaryBonelessNet">Rp 0</span>
                            </div>
                            <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                                <span class="text-lg font-extrabold text-gray-800 uppercase">Grand Total Akhir</span>
                                <span class="text-xl font-black text-indigo-700" id="summaryGrandTotal">Rp 0</span>
                            </div>
                        </div>

                        <div class="form-group mt-6">
                            <label class="text-xs font-bold text-gray-700 uppercase mb-1">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"><?= $header['keterangan'] ?? '' ?></textarea>
                        </div>

                        <div class="mt-8 flex gap-3">
                            <button type="submit" name="simpan" value="simpan" class="px-4 py-2 border-0 bg-indigo-600 text-white rounded-lg font-bold shadow-md hover:bg-indigo-700"><i class="fas fa-save md:mr-2"></i>Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const summaryGrandTotal = document.getElementById('summaryGrandTotal');

        function mobTotals() {
            let totalMobil = 0, nMobil = 0;
            document.querySelectorAll('.mob-pick:checked').forEach(cb => {
                nMobil++;
                totalMobil += parseFloat(cb.getAttribute('data-harga')) || 0;
            });
            const badge = document.getElementById('mobCountBadge');
            if (badge) badge.innerText = nMobil + ' Unit';
            const hid = document.getElementById('jumlah_mobil_hidden');
            if (hid) hid.value = nMobil;
            const mtv = document.getElementById('mobTotalView');
            if (mtv) mtv.innerText = 'Rp ' + Math.round(totalMobil).toLocaleString('id-ID');
            return totalMobil;
        }

        function calculate() {
            let totalPlus = 0;
            let totalMinus = 0;

            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const price = parseFloat(row.querySelector('.harga-input').value) || 0;
                const type = row.querySelector('input[name="type[]"]').value;
                const rowTotal = Math.round(qty * price);

                // Tampilkan total row selalu positif agar tidak ada tanda minus di input
                row.querySelector('.total-row-input').value = rowTotal;

                if (type === 'plus') {
                    totalPlus += rowTotal;
                } else {
                    totalMinus += rowTotal;
                }
            });

            const bonelessNet = totalPlus - totalMinus;
            const grandTotal = bonelessNet + mobTotals();
            const summaryNet = document.getElementById('summaryBonelessNet');
            if (summaryNet) summaryNet.innerText = 'Rp ' + Math.round(bonelessNet).toLocaleString('id-ID');
            summaryGrandTotal.innerText = 'Rp ' + Math.round(grandTotal).toLocaleString('id-ID');
        }

        // Input Listener
        document.addEventListener('input', e => {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input')) {
                calculate();
            }
        });

        document.addEventListener('change', e => {
            if (e.target.classList.contains('mob-pick')) calculate();
        });

        // Remove Row
        document.addEventListener('click', e => {
            if (e.target.closest('.remove-row')) {
                e.target.closest('tr').remove();
                calculate();
                calcSatuan();
            }
        });

        window.calcSatuan = function() {
            let tot = 0;
            document.querySelectorAll('#tableSatuan .sat-row').forEach(row => {
                const nmEl = row.querySelector('input[name="sat_nama[]"]');
                const nm = nmEl ? nmEl.value.trim() : '';
                const q = parseFloat(row.querySelector('.sat-qty').value) || 0;
                const hEl = row.querySelector('.sat-price');
                const h = hEl ? (parseFloat(hEl.value) || 0) : 0;
                const up = (nm === '') ? 0 : Math.round(q * h);
                row.querySelector('.sat-upah-view').value = 'Rp ' + up.toLocaleString('id-ID');
                if (nm !== '') tot += up;
            });
            const spo = document.getElementById('satPerOrang');
            if (spo) spo.innerText = 'Rp ' + tot.toLocaleString('id-ID');
        };

        document.getElementById('addSatRow').addEventListener('click', function() {
            const tbody = document.querySelector('#tableSatuan tbody');
            const tr = document.createElement('tr');
            tr.className = 'sat-row';
            tr.innerHTML = `
                <td class="text-center font-bold text-gray-400">-</td>
                <td><input type="text" name="sat_nama[]" class="form-control"></td>
                <td><input type="number" step="any" name="sat_hasil[]" class="form-control text-center sat-qty" value=""></td>
                <td>
                    <select name="sat_satuan[]" class="form-control text-center sat-unit">
                        <option value="KG">KG</option>
                        <option value="EKOR">EKOR</option>
                    </select>
                </td>
                <td><input type="number" name="sat_harga[]" class="form-control text-center sat-price" value="800"></td>
                <td><input type="text" class="form-control text-right font-bold text-indigo-600 bg-indigo-50 sat-upah-view" readonly value="Rp 0"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row w-full"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(tr);
        });

        // Input Listener (plus satuan)
        document.addEventListener('input', e => {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input')) {
                calculate();
            }
            if (e.target.classList.contains('sat-qty') || e.target.classList.contains('sat-price') || e.target.classList.contains('sat-unit') || e.target.getAttribute('name') === 'sat_nama[]') {
                calcSatuan();
            }
        });

        // Add New Row function
        window.addNewRow = function(tableId, rowClass, colorClass, typeValue) {
            const tbody = document.querySelector(`#${tableId} tbody`);
            const tr = document.createElement('tr');
            tr.className = `item-row ${rowClass}`;
            tr.innerHTML = `
                <td class="text-center font-bold text-gray-300">-</td>
                <td>
                    <input type="text" name="nama_item[]" class="form-control" required>
                    <input type="hidden" name="type[]" value="${typeValue}">
                </td>
                <td><input type="number" step="any" name="qty[]" class="form-control text-center qty-input" value=""></td>
                <td><input type="number" name="harga[]" class="form-control text-center harga-input" value="0"></td>
                <td><input type="number" name="total[]" class="form-control text-right font-bold ${colorClass} total-row-input" readonly value="0"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(tr);
        };

        document.getElementById('addRowPlus').addEventListener('click', () => addNewRow('tablePlus', 'row-plus', 'text-green-600 bg-green-50', 'plus'));
        document.getElementById('addRowMinus').addEventListener('click', () => addNewRow('tableMinus', 'row-minus', 'text-red-600 bg-red-50', 'minus'));

        calculate(); // Initial load calculation
        calcSatuan();
    });
</script>