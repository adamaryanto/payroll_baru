<?php
if(isset($_GET['id'])){
    $idjadwal = $_GET['id'];
    $tampildetail = $koneksi->query("SELECT * FROM tb_jadwal WHERE id_jadwal = '$idjadwal'");
    $datadetail = $tampildetail->fetch_assoc();
} else {
    echo "<script>window.location.href='?page=jadwal';</script>";
    exit;
}

if(isset($_POST['simpan'])) {
    $keterangan = $_POST['tketerangan'];
    $jammasuk   = $_POST['tjammasuk'];
    $jamkeluar  = $_POST['tjamkeluar'];
    $istkeluar  = $_POST['tistirahatkeluar'];
    $istmasuk   = $_POST['tistirahatmasuk'];

    $sql = $koneksi->query("UPDATE tb_jadwal SET 
                            keterangan = '$keterangan', 
                            jam_masuk = '$jammasuk', 
                            jam_keluar = '$jamkeluar', 
                            istirahat_masuk = '$istmasuk', 
                            istirahat_keluar = '$istkeluar' 
                            WHERE id_jadwal = '$idjadwal'");
    
    if($sql) {
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
                    text: 'Data Berhasil Diperbarui!',
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
                    text: 'Gagal memperbarui data jadwal!',
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
                Ubah Jadwal Karyawan
            </h3>
            <p class="text-slate-300 text-sm mt-1">Perbarui detail shift dan jam kerja</p>
        </div>
        
        <form method="POST" class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="md:col-span-3">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Nama Shift <span class="text-rose-500">*</span></label>
                    <input type="text" name="tketerangan" value="<?= htmlspecialchars($datadetail['keterangan']) ?>" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition outline-none font-medium"/>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Jam Masuk <span class="text-rose-500">*</span></label>
                    <input type="time" name="tjammasuk" value="<?= $datadetail['jam_masuk'] ?>" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Jam Keluar <span class="text-rose-500">*</span></label>
                    <input type="time" name="tjamkeluar" value="<?= $datadetail['jam_keluar'] ?>" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Istirahat Keluar <span class="text-rose-500">*</span></label>
                    <input type="time" name="tistirahatkeluar" value="<?= $datadetail['istirahat_keluar'] ?>" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Istirahat Masuk <span class="text-rose-500">*</span></label>
                    <input type="time" name="tistirahatmasuk" value="<?= $datadetail['istirahat_masuk'] ?>" required 
                           class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 transition outline-none"/>
                </div>
            </div>

            <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                <div class="text-xs text-gray-500 italic">
                    <span class="text-rose-500">*</span> Wajib diisi
                </div>
                <div class="flex gap-3">
                    <a href="?page=jadwal" class="px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                        Batal
                    </a>
                    <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition transform hover:-translate-y-0.5">
                        <i class="fas fa-save mr-2"></i> Perbarui Jadwal
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>