<?php
$ref = $_GET['ref'] ?? 'boneless';
$view_param = isset($_GET['view']) ? '&view=1' : '';
$simpan = @$_POST['simpan'];
include_once __DIR__ . '/mobil_lib.php';

// Ambil biaya per mobil dari database
$ambil_biaya = $koneksi->query("SELECT id_biayamobil, biaya_mobil FROM tb_biayamobil LIMIT 1");
$data_biaya = $ambil_biaya->fetch_assoc();
$id_biaya_mobil = $data_biaya['id_biayamobil'] ?? 0;
$harga_per_mobil = $data_biaya['biaya_mobil'] ?? 0;

// Tabel karyawan satuan (nama + kg, upah dibagi rata). Hapus data ikut header via hapus.php
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

if ($simpan) {
    $tgl = @$_POST['tgl'];
    $keterangan = @$_POST['keterangan'];
    $id_rkk = @$_POST['id_rkk'];
    $id_bm = @$_POST['id_biayamobil'];

    // Truck yg dicentang (snapshot nama+harga master saat simpan)
    $fleet_map = [];
    foreach (boneless_mobil_fleet($koneksi) as $fm) $fleet_map[(int)$fm['id_mobil']] = $fm;
    $trucks = [];
    foreach ((array)($_POST['mob_pick'] ?? []) as $mid_raw) {
        $mid = (int)$mid_raw;
        if ($mid <= 0 || !isset($fleet_map[$mid])) continue;
        $trucks[] = $fleet_map[$mid];
    }
    $jumlah_mobil = count($trucks);

    // Insert Header
    $sql_header = $koneksi->query("INSERT INTO tb_boneless (id_rkk, id_biayamobil, tgl, jumlah_mobil, keterangan, tgl_updt) 
                                   VALUES ('$id_rkk', '$id_bm', '$tgl', '$jumlah_mobil', '$keterangan', NOW())");
    $id_header = $koneksi->insert_id;

    if ($id_header) {
        // Simpan link truck (snapshot nama+harga)
        foreach ($trucks as $tr) {
            $nm = $koneksi->real_escape_string($tr['nama']);
            $hg = (float)($tr['harga'] ?? 0);
            $koneksi->query("INSERT INTO tb_boneless_mobil (id_boneless, id_mobil, nama, harga) VALUES ('$id_header', '" . (int)$tr['id_mobil'] . "', '$nm', '$hg')");
        }
        $nama_items = (array)($_POST['nama_item'] ?? []);
        $qtys       = (array)($_POST['qty'] ?? []);
        $hargas     = (array)($_POST['harga'] ?? []);
        $jenis_items = (array)($_POST['jenis_item'] ?? []);

        for ($i = 0; $i < count($nama_items); $i++) {
            $item = $koneksi->real_escape_string($nama_items[$i] ?? '');
            $qty  = (float)(($qtys[$i] ?? 0) ?: 0);
            $hrg  = (float)(($hargas[$i] ?? 0) ?: 0);
            $jenis = $koneksi->real_escape_string($jenis_items[$i] ?? 'plus');

            if ($item != "") {
                // MODIFIKASI: Simpan total sebagai nilai positif (absolute)
                // Biarkan kolom 'jenis' yang menentukan apakah ini pengurang atau penambah
                $ttl = $qty * $hrg;

                $koneksi->query("INSERT INTO tb_boneless_detail (id_boneless, nama_item, qty, harga, total, jenis)
                                 VALUES ('$id_header', '$item', '$qty', '$hrg', '$ttl', '$jenis')");
            }
        }

        // Simpan karyawan satuan (upah per baris: hasil x harga, satuan KG/EKOR)
        $sat_nama = $_POST['sat_nama'] ?? [];
        $sat_hasil = $_POST['sat_hasil'] ?? [];
        $sat_satuan = $_POST['sat_satuan'] ?? [];
        $sat_harga = $_POST['sat_harga'] ?? [];
        for ($i = 0; $i < count($sat_nama); $i++) {
            $nm = trim($sat_nama[$i] ?? '');
            if ($nm === '') continue;
            $q = (float)($sat_hasil[$i] ?: 0);
            $u = strtoupper(trim($sat_satuan[$i] ?? 'KG'));
            if ($u !== 'EKOR') $u = 'KG';
            $h = (float)($sat_harga[$i] ?? 0);
            $nm_esc = $koneksi->real_escape_string($nm);
            $up_sat = (int)round($q * $h);
            $koneksi->query("INSERT INTO tb_boneless_satuan (id_boneless, nama, hasil, upah, satuan, harga)
                             VALUES ('$id_header', '$nm_esc', '$q', '$up_sat', '$u', '$h')");
        }
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
            <script>
                Swal.fire({ icon: "success", title: "Berhasil", text: "Data Berhasil Disimpan", confirmButtonColor: "#2563eb" })
                .then(() => { window.location.href = "?page=boneless&ref=' . $ref . $view_param . '"; });
            </script></body></html>';
        exit;
    }
}

$default_plus = ["BORAS" => 800, "BONELESS KEVIN" => 700, "PAHA SP HOKA" => 500];
$default_minus = ["SHILIN" => 500, "CIMORY" => 500];
$default_satuan = ["MULYANTI", "EDAH", "YUSI HASTUTIK", "RUSDIANA", "HARNI", "CICHA MARYANA", "SITI PATONAH", "RUTINAH", "TIARA VETI FEBRIANA"];
$default_harga_satuan = 800;
$fleet_form = function_exists('boneless_mobil_fleet') ? boneless_mobil_fleet($koneksi) : [];
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
                        <h3 class="text-xl font-bold m-0" style="color: #2563eb;">Tambah Data Boneless</h3>
                    </div>
                </div>

                <div class="p-4 md:p-5">
                    <form method="POST">
                        <input type="hidden" name="id_biayamobil" value="<?= $id_biaya_mobil ?>">

                        <div class="row mb-4">
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Data Rencana</label>
                                <select name="id_rkk" id="id_rkk_select" class="form-control h-[42px]" required>
                                    <option value="">-- Pilih Rencana --</option>
                                    <?php
                                    $sql_rkk = $koneksi->query("SELECT * FROM tb_rkk WHERE status_rkk = '0' ORDER BY tgl_rkk DESC");
                                    while ($row_rkk = $sql_rkk->fetch_assoc()) {
                                        echo '<option value="' . $row_rkk['id_rkk'] . '" data-tgl="' . $row_rkk['tgl_rkk'] . '">' . date('d-m-Y', strtotime($row_rkk['tgl_rkk'])) . ' - ' . $row_rkk['keterangan'] . '</option>';
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="tgl" id="tgl_hidden">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Jumlah Mobil (otomatis)</label>
                                <div class="h-[42px] flex items-center px-3 bg-amber-50 border border-amber-200 rounded-lg font-black text-amber-700 shadow-sm">
                                    <i class="fas fa-truck mr-2"></i> <span id="mobCountBadge"><?= count($fleet_form) ?> Unit</span>
                                </div>
                                <input type="hidden" name="jumlah_mobil" id="jumlah_mobil_hidden" value="<?= count($fleet_form) ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Total Mobil (otomatis)</label>
                                <div class="h-[42px] flex items-center px-3 bg-amber-50 border border-amber-200 rounded-lg font-black text-amber-700 shadow-sm" id="mobTotalView">Rp 0</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="text-xs font-bold text-amber-700 uppercase mb-3 block"><i class="fas fa-truck mr-1"></i> Mobil yg Jalan (centang dari master)</label>
                            <?php if (empty($fleet_form)): ?>
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">Belum ada armada. Isi dulu di <a href="?page=boneless&aksi=master" class="font-bold underline">MASTER → Mobil</a>.</div>
                            <?php else: ?>
                            <div id="mobCheckList" class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                <?php foreach ($fleet_form as $fm): ?>
                                <label class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-amber-400 text-sm font-semibold text-gray-700">
                                    <input type="checkbox" name="mob_pick[]" value="<?= (int)$fm['id_mobil'] ?>" data-harga="<?= (float)($fm['harga'] ?? 0) ?>" class="mob-pick w-4 h-4 text-amber-600 rounded" checked>
                                    <span><?= htmlspecialchars($fm['nama']) ?> <span class="text-gray-400 font-normal">Rp <?= number_format($fm['harga'] ?? 0, 0, ',', '.') ?></span></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <hr class="my-5 border-gray-200">

                        <div class="mt-4">
                            <label class="text-xs font-bold text-green-700 uppercase mb-3 block"><i class="fas fa-plus-circle mr-1"></i> Tim Tanpa Mesin Boneless</label>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tablePlus">
                                    <thead class="bg-green-50">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">#</th>
                                            <th>Nama Tim</th>
                                            <th style="width: 150px;">QTY</th>
                                            <th style="width: 150px;">Harga</th>
                                            <th style="width: 180px;">Total</th>
                                            <th style="width: 60px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1;
                                        foreach ($default_plus as $name => $price): ?>
                                            <tr class="item-row row-plus">
                                                <input type="hidden" name="jenis_item[]" value="plus">
                                                <td class="text-center font-bold text-gray-400"><?= $no++ ?></td>
                                                <td><input type="text" name="nama_item[]" class="form-control" value="<?= $name ?>" required></td>
                                                <td><input type="number" step="any" name="qty[]" class="form-control text-center qty-input" value=""></td>
                                                <td><input type="number" name="harga[]" class="form-control text-center harga-input" value="<?= $price ?>"></td>
                                                <td><input type="number" name="total[]" class="form-control text-right font-bold text-green-600 bg-green-50 total-row-input" readonly value="0"></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" onclick="addNewRow('tablePlus', 'plus')" class="btn btn-sm btn-outline-success font-bold"><i class="fas fa-plus mr-1"></i> Tambah Item Plus</button>
                            </div>
                        </div>

                        <hr class="my-5 border-gray-200">

                        <div class="mt-4">
                            <label class="text-xs font-bold text-red-700 uppercase mb-3 block"><i class="fas fa-minus-circle mr-1"></i>Tim Dengan Mesin Boneless</label>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tableMinus">
                                    <thead class="bg-red-50">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">#</th>
                                            <th>Nama Tim</th>
                                            <th style="width: 150px;">QTY</th>
                                            <th style="width: 150px;">Harga</th>
                                            <th style="width: 180px;">Total</th>
                                            <th style="width: 60px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1;
                                        foreach ($default_minus as $name => $price): ?>
                                            <tr class="item-row row-minus">
                                                <input type="hidden" name="jenis_item[]" value="minus">
                                                <td class="text-center font-bold text-gray-400"><?= $no++ ?></td>
                                                <td><input type="text" name="nama_item[]" class="form-control" value="<?= $name ?>" required></td>
                                                <td><input type="number" step="any" name="qty[]" class="form-control text-center qty-input" value=""></td>
                                                <td><input type="number" name="harga[]" class="form-control text-center harga-input" value="<?= $price ?>"></td>
                                                <td><input type="number" name="total[]" class="form-control text-right font-bold text-red-600 bg-red-50 total-row-input" readonly value="0"></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="button" onclick="addNewRow('tableMinus', 'minus')" class="btn btn-sm btn-outline-danger font-bold"><i class="fas fa-plus mr-1"></i> Tambah Item Minus</button>
                            </div>
                        </div>

                        <hr class="my-5 border-gray-200">

                        <div class="mt-4">
                            <label class="text-xs font-bold text-indigo-700 uppercase mb-3 block"><i class="fas fa-users mr-1"></i>Karyawan Satuan (upah per baris)</label>
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
                                            <th class="text-center" style="width: 50px;">No</th>
                                            <th>Nama Sesuai KTP</th>
                                            <th style="width: 120px;">Hasil Kerja</th>
                                            <th style="width: 110px;">Satuan</th>
                                            <th style="width: 140px;">Harga (Rp)</th>
                                            <th style="width: 170px;">Upah Dibayar</th>
                                            <th style="width: 60px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sn = 1;
                                        foreach ($default_satuan as $nm): ?>
                                            <tr class="sat-row">
                                                <td class="text-center font-bold text-gray-400"><?= $sn++ ?></td>
                                                <td><input type="text" name="sat_nama[]" class="form-control" value="<?= $nm ?>"></td>
                                                <td><input type="number" step="any" name="sat_hasil[]" class="form-control text-center sat-qty" value=""></td>
                                                <td>
                                                    <select name="sat_satuan[]" class="form-control text-center sat-unit">
                                                        <option value="KG">KG</option>
                                                        <option value="EKOR">EKOR</option>
                                                    </select>
                                                </td>
                                                <td><input type="number" name="sat_harga[]" class="form-control text-center sat-price" value="<?= $default_harga_satuan ?>"></td>
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
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mt-8 flex gap-3">
                            <button type="submit" name="simpan" value="simpan" class="px-4 py-2 border-0 bg-indigo-600 text-white rounded-lg font-bold shadow-md hover:bg-indigo-700"><i class="fas fa-save md:mr-2"></i>Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const masterCost = <?= (float)$harga_per_mobil ?>;
        const idRkkSelect = document.getElementById('id_rkk_select');
        const tglHidden = document.getElementById('tgl_hidden');

        idRkkSelect.addEventListener('change', function() {
            tglHidden.value = this.options[this.selectedIndex].getAttribute('data-tgl') || '';
        });

        window.addNewRow = function(tableId, jenis) {
            const tbody = document.querySelector(`#${tableId} tbody`);
            const colorClass = jenis === 'plus' ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50';
            const row = document.createElement('tr');
            row.className = `item-row row-${jenis}`;
            row.innerHTML = `
                <input type="hidden" name="jenis_item[]" value="${jenis}">
                <td class="text-center font-bold text-gray-400">-</td>
                <td><input type="text" name="nama_item[]" class="form-control" required></td>
                <td><input type="number" step="any" name="qty[]" class="form-control text-center qty-input" value=""></td>
                <td><input type="number" name="harga[]" class="form-control text-center harga-input" value="0"></td>
                <td><input type="number" name="total[]" class="form-control text-right font-bold ${colorClass} total-row-input" readonly value="0"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row w-full"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(row);
        };

        window.calculate = function() {
            let totalPlus = 0;
            let totalMinus = 0;

            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const price = parseFloat(row.querySelector('.harga-input').value) || 0;
                const jenis = row.querySelector('input[name="jenis_item[]"]').value;
                const total = Math.round(qty * price);

                // Tampilkan total sebagai angka positif di baris tabel
                row.querySelector('.total-row-input').value = total;

                // Hitung ke Grand Total berdasarkan jenisnya
                if (jenis === 'plus') {
                    totalPlus += total;
                } else {
                    totalMinus += total;
                }
            });

            // Total mobil = Σ harga truck yg dicentang
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

            // Logika Grand Total: (Nambah - Kurang) + (Total Mobil)
            const bonelessNet = totalPlus - totalMinus;
            const grandTotal = bonelessNet + totalMobil;

            document.getElementById('summaryBonelessNet').innerText = 'Rp ' + Math.round(bonelessNet).toLocaleString('id-ID');
            document.getElementById('summaryGrandTotal').innerText = 'Rp ' + Math.round(grandTotal).toLocaleString('id-ID');
        };

        document.addEventListener('input', e => {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input')) {
                calculate();
            }
        });

        document.addEventListener('change', e => {
            if (e.target.classList.contains('mob-pick')) calculate();
        });

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
            document.getElementById('satPerOrang').innerText = 'Rp ' + tot.toLocaleString('id-ID');
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
                <td><input type="number" name="sat_harga[]" class="form-control text-center sat-price" value="<?= $default_harga_satuan ?>"></td>
                <td><input type="text" class="form-control text-right font-bold text-indigo-600 bg-indigo-50 sat-upah-view" readonly value="Rp 0"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row w-full"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(tr);
        });

        document.addEventListener('input', e => {
            if (e.target.classList.contains('sat-qty') || e.target.classList.contains('sat-price') || e.target.classList.contains('sat-unit') || e.target.getAttribute('name') === 'sat_nama[]') {
                calcSatuan();
            }
        });

        calculate();
        calcSatuan();
    });
</script>

<style>
    /* Styling agar input form terlihat rapi */
    .input-modern {
        height: 38px;
        border-radius: 6px;
    }

    /* =========================================
       KHUSUS TAMPILAN MOBILE: KARTU INPUT FORM
       ========================================= */
    @media screen and (max-width: 768px) {
        .table-responsive {
            border: none !important;
        }

        .table-form-mobile thead {
            display: none !important;
        }

        /* Merubah setiap tag TR menjadi Kotak Kartu */
        .table-form-mobile tbody tr {
            display: block;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px;
            background: #f8fafc;
            /* Warna latar sedikit abu untuk membedakan per baris */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .table-form-mobile tbody td {
            display: flex;
            flex-direction: column;
            align-items: flex-start !important;
            padding: 10px 0 !important;
            border: none !important;
        }

        /* Menampilkan Label Judul di atas input */
        .table-form-mobile tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
            width: 100%;
        }

        /* Agar input mengambil sisa lebar penuh di HP */
        .table-form-mobile tbody td input {
            width: 100% !important;
        }

        /* Tampilan Footer (Grand Total) di HP */
        .table-form-mobile tfoot tr {
            display: flex;
            flex-direction: column;
            background: transparent !important;
            border: none !important;
            padding: 0;
            margin-top: 10px;
        }

        .table-form-mobile tfoot td {
            display: block;
            border: none !important;
            width: 100%;
        }

        .table-form-mobile tfoot td:first-child {
            padding-bottom: 0 !important;
            text-align: center !important;
        }
    }
</style>