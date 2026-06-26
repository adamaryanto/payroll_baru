<?php
$ref = $_GET['ref'] ?? 'boneless';
$view_param = isset($_GET['view']) ? '&view=1' : '';
$id = $_GET['id'] ?? '';

// 1. Ambil Data Header
if ($id) {
    $sql_header = $koneksi->query("SELECT * FROM tb_boneless WHERE id_boneless = '$id'");
    $header = $sql_header->fetch_assoc();
} else {
    $header = null;
}

// 2. Ambil Biaya Mobil (Master)
$sql_master = $koneksi->query("SELECT biaya_mobil FROM tb_biayamobil LIMIT 1");
$row_master = $sql_master->fetch_assoc();
$harga_per_mobil = $row_master['biaya_mobil'] ?? 0;

// 3. Ambil Data Detail
$sql_detail = $koneksi->query("SELECT * FROM tb_boneless_detail WHERE id_boneless = '$id'");
$details = [];
while ($row = $sql_detail->fetch_assoc()) {
    $details[] = $row;
}

// 4. Proses Simpan Perubahan
$simpan = @$_POST['simpan'];
if ($simpan) {
    $id_rkk = @$_POST['id_rkk'];
    $tgl = @$_POST['tgl'];
    $jumlah_mobil = @$_POST['jumlah_mobil'];
    $keterangan = @$_POST['keterangan'];

    // Update Header
    $koneksi->query("UPDATE tb_boneless SET 
        id_rkk = '$id_rkk', 
        tgl = '$tgl', 
        jumlah_mobil = '$jumlah_mobil', 
        keterangan = '$keterangan' 
        WHERE id_boneless = '$id'");

    // Sync Details: Hapus yang lama, masukkan yang baru
    $koneksi->query("DELETE FROM tb_boneless_detail WHERE id_boneless = '$id'");

    $nama_items = $_POST['nama_item'];
    $qtys = $_POST['qty'];
    $hargas = $_POST['harga'];
    $types = $_POST['type'];

    if (!empty($nama_items)) {
        for ($i = 0; $i < count($nama_items); $i++) {
            $item = $koneksi->real_escape_string($nama_items[$i]);
            if ($item != "") {
                $qty = (float)($qtys[$i] ?: 0);
                $hrg = (float)($hargas[$i] ?: 0);
                $type = $koneksi->real_escape_string($types[$i]);

                // MODIFIKASI: Simpan total selalu POSITIF (Absolute)
                $ttl = abs($qty * $hrg);

                $koneksi->query("INSERT INTO tb_boneless_detail (id_boneless, nama_item, qty, harga, total, jenis) 
                                 VALUES ('$id', '$item', '$qty', '$hrg', '$ttl', '$type')");
            }
        }
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
                    <span class="text-xs font-bold text-gray-400 bg-gray-50 px-2 py-1 rounded">ID: #<?= $id ?></span>
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
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Jumlah Mobil (Potong)</label>
                                <input type="number" name="jumlah_mobil" class="form-control h-[42px]" value="<?= $header['jumlah_mobil'] ?? 0 ?>" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Biaya / Mobil (Lock)</label>
                                <div class="h-[42px] flex items-center px-3 bg-blue-50 border border-blue-200 rounded-lg font-bold text-blue-600" id="staticMasterDisplay">
                                    Rp <?= number_format($harga_per_mobil, 0, ',', '.') ?>
                                </div>
                            </div>
                        </div>

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

                        <div class="mt-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="flex justify-between items-center">
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
        const jmlMobilInput = document.querySelector('input[name="jumlah_mobil"]');
        const summaryGrandTotal = document.getElementById('summaryGrandTotal');
        const masterCost = <?= (float)$harga_per_mobil ?>;

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

            const jmlMobil = parseFloat(jmlMobilInput.value) || 0;
            const grandTotal = (totalPlus - totalMinus) + (jmlMobil * masterCost);
            summaryGrandTotal.innerText = 'Rp ' + Math.round(grandTotal).toLocaleString('id-ID');
        }

        // Input Listener
        document.addEventListener('input', e => {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input') || e.target === jmlMobilInput) {
                calculate();
            }
        });

        // Remove Row
        document.addEventListener('click', e => {
            if (e.target.closest('.remove-row')) {
                e.target.closest('tr').remove();
                calculate();
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
    });
</script>