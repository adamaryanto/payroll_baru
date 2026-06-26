<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-calendar-alt mr-3"></i>
                Tambah Jadwal Karyawan
            </h3>
            <p class="text-slate-300 text-sm mt-1">Tambahkan shift kerja baru ke dalam sistem</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="p-8">
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Nama Shift <span class="text-rose-500">*</span>
                    </label>
                    <input placeholder="Contoh: Shift Pagi" autocomplete="off" type="text" name="tketerangan" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition outline-none font-medium"/>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Jam Masuk <span class="text-rose-500">*</span></label>
                        <input type="time" name="tjammasuk" required 
                               class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Jam Keluar <span class="text-rose-500">*</span></label>
                        <input type="time" name="tjamkeluar" required 
                               class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Istirahat Keluar <span class="text-rose-500">*</span></label>
                        <input type="time" name="tistirahatkeluar" required 
                               class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Istirahat Masuk <span class="text-rose-500">*</span></label>
                        <input type="time" name="tistirahatmasuk" required 
                               class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic">
                        <span class="text-rose-500">*</span> Wajib diisi
                    </div>
                    <div class="flex gap-3">
                        <a href="?page=jadwal" class="inline-flex items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
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

<?php
if (isset($_POST['simpan'])) {
    $tketerangan      = $_POST['tketerangan'];
    $tjammasuk        = $_POST['tjammasuk'];
    $tjamkeluar       = $_POST['tjamkeluar'];
    $tistirahatmasuk  = $_POST['tistirahatmasuk'];
    $tistirahatkeluar = $_POST['tistirahatkeluar'];

    $sql = $koneksi->query("INSERT INTO tb_jadwal (keterangan, jam_masuk, jam_keluar, istirahat_masuk, istirahat_keluar) 
                            VALUES ('$tketerangan', '$tjammasuk', '$tjamkeluar', '$tistirahatmasuk', '$tistirahatkeluar')");
    
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
                    text: 'Data Jadwal Berhasil Disimpan!',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=jadwal';
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
                    text: 'Gagal menyimpan data jadwal!',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Ok'
                });
            </script>
        </body>
        </html>";
    }
}
?>