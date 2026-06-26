<div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8 mt-4 sm:mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-5 sm:px-8 py-5 sm:py-6">
            <h3 class="text-xl sm:text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fa fa-calendar-check mr-3"></i>
                Form Rencana Kerja
            </h3>
            <p class="text-slate-300 text-xs sm:text-sm mt-1">Input rencana tugas dan durasi kerja harian</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="p-5 sm:p-8">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 sm:gap-6 mb-8">
                    <input type="hidden" name="tid">

                    <div class="md:col-span-3">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Tanggal <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" name="ttgl1" value="<?= date('Y-m-d'); ?>" required 
                               class="block w-full px-4 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 font-medium text-sm sm:text-base"/>
                    </div>

                    <div class="md:col-span-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Keterangan Tugas <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="tketerangan" placeholder="Contoh: Maintenance Server / Input Data" required autocomplete="off"
                               class="block w-full px-4 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 font-medium text-sm sm:text-base"/>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Durasi (Jam) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" name="tjamkerja" placeholder="0" min="1" max="24" required 
                                   class="block w-full pr-12 pl-4 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-150 font-medium text-sm sm:text-base"/>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold text-sm">Jam</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic order-2 sm:order-1 text-center sm:text-left">
                        <span class="text-rose-500">*</span> Wajib diisi dengan benar
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto order-1 sm:order-2">
                        <a href="?page=rkk" class="flex justify-center items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition duration-150 ease-in-out">
                            Batal
                        </a>
                        <button type="submit" name="simpan" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition duration-150 ease-in-out transform hover:-translate-y-0.5">
                            <i class="fa fa-check-circle mr-2"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
if (isset($_POST['simpan'])) {
    $tgl_rkk     = $_POST['ttgl1'];
    $keterangan  = $_POST['tketerangan'];
    $jam_kerja   = $_POST['tjamkerja'];
    $detail_rkk  = date("Y-m-d H:i:s"); 
    $tgl_status  = date("Y-m-d H:i:s"); 

    $sql = $koneksi->query("INSERT INTO tb_rkk (tgl_rkk, keterangan, jam_kerja, detail_rkk, status_rkk, tgl_status) 
                            VALUES ('$tgl_rkk', '$keterangan', '$jam_kerja', '$detail_rkk', '0', '$tgl_status')");

    if ($sql) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: "Data Berhasil Disimpan",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                }).then((result) => {
                    window.location.href="?page=rkk";
                });
            </script>
        </body>
        </html>';
    } else {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Gagal Menyimpan Data: ' . addslashes($koneksi->error) . '",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "OK"
                });
            </script>
        </body>
        </html>';
    }
}
?>