<?php
// Auto-create table tb_lembur if it doesn't exist
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_lembur (
    id_lembur INT AUTO_INCREMENT PRIMARY KEY,
    lembur_per_jam INT DEFAULT 0
) ENGINE=InnoDB");

// Make sure at least one row exists
$check = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1");
if ($check->num_rows == 0) {
    $koneksi->query("INSERT INTO tb_lembur (id_lembur, lembur_per_jam) VALUES (1, 0)");
}

// Mengambil data lembur saat ini
$tampil = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1");
$data = $tampil->fetch_assoc();
$lemburperjam = $data['lembur_per_jam'] ?? 0;

// Memproses saat tombol simpan ditekan
if (isset($_POST['simpan'])) {
    $tlemburperjam = $_POST['tlemburperjam'] ?? 0;
    
    // Proses Update ke Database
    $sql = $koneksi->query("UPDATE tb_lembur SET 
        lembur_per_jam = '$tlemburperjam'
        WHERE id_lembur = 1
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
                    text: 'Data Pengaturan Lembur Berhasil Diperbarui',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=lembur';
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
                    text: 'Gagal memperbarui data lembur!',
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
        
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-clock mr-3"></i>
                Pengaturan Tarif Lembur
            </h3>
            <p class="text-slate-200 text-sm mt-1">Atur nominal upah lembur per jam untuk karyawan</p>
        </div>
        
        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Tarif Lembur / Jam <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-bold">Rp</span>
                            </div>
                            <input placeholder="0" autocomplete="off" type="number" name="tlemburperjam" value="<?= htmlspecialchars($lemburperjam) ?>" required 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out font-medium"/>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic">
                        <span class="text-rose-500">*</span> Wajib diisi
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-indigo-600 hover:bg-indigo-700 transition duration-150 ease-in-out transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
