<?php
// Mengambil data denda saat ini
$tampil = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
$data = $tampil->fetch_assoc();
$dendamasuk = $data['denda_masuk'] ?? '';
$dendaistirahatkeluar = $data['denda_istirahat_keluar'] ?? '';
$dendaistirahatmasuk = $data['denda_istirahat_masuk'] ?? '';
$dendapulang = $data['denda_pulang'] ?? '';
$dendatidaklengkap = $data['denda_tidak_lengkap'] ?? '';

// Memproses saat tombol simpan ditekan (Dipindah ke atas agar lebih rapi)
if (isset($_POST['simpan'])) {
    $tdendamasuk = $_POST['tdendamasuk'] ?? '';
    $tdendaistirahatkeluar = $_POST['tdendaistirahatkeluar'] ?? '';
    $tdendaistirahatmasuk = $_POST['tdendaistirahatmasuk'] ?? '';
    $tdendapulang = $_POST['tdendapulang'] ?? '';
    $tdendatidaklengkap = $_POST['tdendatidaklengkap'] ?? '';
    
    // Proses Update ke Database
    $sql = $koneksi->query("UPDATE tb_denda SET 
        denda_masuk = '$tdendamasuk', 
        denda_istirahat_keluar = '$tdendaistirahatkeluar',
        denda_istirahat_masuk = '$tdendaistirahatmasuk',
        denda_pulang = '$tdendapulang',
        denda_tidak_lengkap = '$tdendatidaklengkap'
    ");
    
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
                    text: 'Data Pengaturan Denda Berhasil Diperbarui',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=denda';
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
                    text: 'Gagal memperbarui data denda!',
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
                <i class="fas fa-money-bill-wave mr-3"></i>
                Pengaturan Denda
            </h3>
            <p class="text-slate-300 text-sm mt-1">Atur nominal potongan denda untuk kedisiplinan kerja</p>
        </div>
        
        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Denda Masuk (Telat) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold">Rp</span>
                            </div>
                            <input placeholder="0" autocomplete="off" type="number" name="tdendamasuk" value="<?= htmlspecialchars($dendamasuk) ?>" required 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out font-medium"/>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Denda Keluar Istirahat (Cepat) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold">Rp</span>
                            </div>
                            <input placeholder="0" autocomplete="off" type="number" name="tdendaistirahatkeluar" value="<?= htmlspecialchars($dendaistirahatkeluar) ?>" required 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out font-medium"/>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Denda Masuk Istirahat (Telat) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold">Rp</span>
                            </div>
                            <input placeholder="0" autocomplete="off" type="number" name="tdendaistirahatmasuk" value="<?= htmlspecialchars($dendaistirahatmasuk) ?>" required 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out font-medium"/>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Denda Pulang Awal <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold">Rp</span>
                            </div>
                            <input placeholder="0" autocomplete="off" type="number" name="tdendapulang" value="<?= htmlspecialchars($dendapulang) ?>" required 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out font-medium"/>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            DENDA ABSENSI TIDAK LENGKAP <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold">Rp</span>
                            </div>
                            <input placeholder="0" autocomplete="off" type="number" name="tdendatidaklengkap" value="<?= htmlspecialchars($dendatidaklengkap) ?>" required 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out font-medium"/>
                        </div>
                    </div>

                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic">
                        <span class="text-rose-500">*</span> Wajib diisi
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition duration-150 ease-in-out transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>