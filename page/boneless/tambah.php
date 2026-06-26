<?php
$ref = $_GET['ref'] ?? 'boneless';
$view_param = isset($_GET['view']) ? '&view=1' : '';
$simpan = @$_POST['simpan'];

// Ambil biaya per mobil dari database
$ambil_biaya = $koneksi->query("SELECT id_biayamobil, biaya_mobil FROM tb_biayamobil LIMIT 1");
$data_biaya = $ambil_biaya->fetch_assoc();
$id_biaya_mobil = $data_biaya['id_biayamobil'] ?? 0;
$harga_per_mobil = $data_biaya['biaya_mobil'] ?? 0;

if ($simpan) {
    $tgl = @$_POST['tgl'];
    $jumlah_mobil = @$_POST['jumlah_mobil'];
    $keterangan = @$_POST['keterangan'];
    $id_rkk = @$_POST['id_rkk'];
    $id_bm = @$_POST['id_biayamobil'];

    // Insert Header
    $sql_header = $koneksi->query("INSERT INTO tb_boneless (id_rkk, id_biayamobil, tgl, jumlah_mobil, keterangan, tgl_updt) 
                                   VALUES ('$id_rkk', '$id_bm', '$tgl', '$jumlah_mobil', '$keterangan', NOW())");
    $id_header = $koneksi->insert_id;

    if ($id_header) {
        $nama_items = $_POST['nama_item'];
        $qtys       = $_POST['qty'];
        $hargas     = $_POST['harga'];
        $jenis_items = $_POST['jenis_item'];

        for ($i = 0; $i < count($nama_items); $i++) {
            $item = $koneksi->real_escape_string($nama_items[$i]);
            $qty  = (float)($qtys[$i] ?: 0);
            $hrg  = (float)($hargas[$i] ?: 0);
            $jenis = $koneksi->real_escape_string($jenis_items[$i]);

            if ($item != "") {
                // MODIFIKASI: Simpan total sebagai nilai positif (absolute)
                // Biarkan kolom 'jenis' yang menentukan apakah ini pengurang atau penambah
                $ttl = $qty * $hrg;

                $koneksi->query("INSERT INTO tb_boneless_detail (id_boneless, nama_item, qty, harga, total, jenis) 
                                 VALUES ('$id_header', '$item', '$qty', '$hrg', '$ttl', '$jenis')");
            }
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
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Jumlah Mobil (Potong)</label>
                                <input type="number" name="jumlah_mobil" class="form-control h-[42px]" placeholder="0" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Biaya / Mobil (Lock)</label>
                                <div class="h-[42px] flex items-center px-3 bg-gray-100 border border-gray-200 rounded-lg font-bold text-gray-600 shadow-sm">
                                    <i class="fas fa-lock mr-2 text-gray-400"></i> Rp <?= number_format($harga_per_mobil, 0, ',', '.') ?>
                                </div>
                            </div>
                        </div>

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

                        <div class="mt-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="flex justify-between items-center">
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

            const jmlMobil = parseFloat(document.querySelector('input[name="jumlah_mobil"]').value) || 0;

            // Logika Grand Total: (Nambah - Kurang) + (Biaya Mobil)
            const grandTotal = (totalPlus - totalMinus) + (jmlMobil * masterCost);

            document.getElementById('summaryGrandTotal').innerText = 'Rp ' + Math.round(grandTotal).toLocaleString('id-ID');
        };

        document.addEventListener('input', e => {
            if (e.target.classList.contains('qty-input') || e.target.classList.contains('harga-input') || e.target.name === 'jumlah_mobil') {
                calculate();
            }
        });

        document.addEventListener('click', e => {
            if (e.target.closest('.remove-row')) {
                e.target.closest('tr').remove();
                calculate();
            }
        });

        calculate();
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