<?php
// 1. Ambil data saat ini
$tampil = $koneksi->query("SELECT * FROM tb_biayamobil LIMIT 1");
$row_count = $tampil->num_rows;
$data = $tampil->fetch_assoc();

$biayamobil = $data['biaya_mobil'] ?? '0';
$id_biaya = $data['id_biayamobil'] ?? '';

// 2. Memproses saat tombol simpan ditekan
if (isset($_POST['simpan'])) {
    $tbiayamobil = $_POST['tbiayamobil'] ?? '0';

    if ($row_count > 0) {
        // Jika data sudah ada, lakukan UPDATE
        $sql = $koneksi->query("UPDATE tb_biayamobil SET 
            biaya_mobil = '$tbiayamobil' 
            WHERE id_biayamobil = '$id_biaya'");
    } else {
        // Jika tabel masih kosong, lakukan INSERT biar ada datanya
        $sql = $koneksi->query("INSERT INTO tb_biayamobil (biaya_mobil) VALUES ('$tbiayamobil')");
    }

    if ($sql) {
        // Alert sukses (tetap sama seperti kode kamu)
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            setTimeout(function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data Biaya Mobil Berhasil Diperbarui',
                    confirmButtonColor: '#2563eb'
                }).then(() => {
                    window.location.href='?page=boneless&aksi=master';
                });
            }, 100);
        </script>";
        exit;
    } else {
        // Cek error database jika gagal
        $error_db = $koneksi->error;
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            setTimeout(function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Error: $error_db',
                    confirmButtonColor: '#d33'
                });
            }, 100);
        </script>";
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">

        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-car mr-3"></i>
                Pengaturan Biaya Mobil
            </h3>
            <p class="text-blue-100 text-sm mt-1">Atur nominal standar biaya operasional kendaraan</p>
        </div>

        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Nominal Biaya per Mobil <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative max-w-md">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold">Rp</span>
                            </div>
                            <input placeholder="0" autocomplete="off" type="number" name="tbiayamobil" value="<?= htmlspecialchars($biayamobil) ?>" required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out font-medium" />
                        </div>
                        <p class="mt-2 text-xs text-gray-500 italic">Masukkan angka saja tanpa titik atau koma.</p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic">
                        <span class="text-rose-500">*</span> Wajib diisi
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border-0 text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition duration-150 ease-in-out transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>  