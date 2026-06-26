<?php
if (isset($_GET['id'])) {
    $idu = $_GET['id'];
    $tampil = $koneksi->query("SELECT * FROM ms_departmen WHERE id_departmen = '$idu'");
    $data = $tampil->fetch_assoc();
}

$tnama = $_POST['tnama'] ?? '';
if (isset($_POST['simpan'])) {
    $sql = $koneksi->query("UPDATE ms_departmen SET nama_departmen = '$tnama' WHERE id_departmen = '$idu'");
    if ($sql) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Data Berhasil Diperbarui',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=bagian';
                });
            </script>
        </body>
        </html>";
        exit;
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal memperbarui data: " . $koneksi->error . "',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Ok'
                });
            </script>
        </body>
        </html>";
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-edit mr-3"></i>
                Ubah Data Bagian
            </h3>
            <p class="text-blue-200 text-sm mt-1">Perbarui informasi nama bagian atau departemen</p>
        </div>
        
        <form method="POST">
            <div class="p-8">
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Nama Bagian <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="tnama" 
                        value="<?php echo htmlspecialchars($data['nama_departmen'] ?? ''); ?>" 
                        required 
                        placeholder="Masukkan nama bagian..." 
                        autocomplete="off"
                        class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-medium"
                    />
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic"><span class="text-red-500">*</span> Wajib diisi</div>
                    <div class="flex gap-3">
                        <a href="?page=bagian" class="inline-flex items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                            Batal
                        </a>
                        <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>