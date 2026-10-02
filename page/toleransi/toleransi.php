<?php
// Buat tabel toleransi jika belum ada
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_toleransi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    toleransi_masuk INT DEFAULT 10,
    toleransi_istirahat_keluar INT DEFAULT 5,
    toleransi_istirahat_masuk INT DEFAULT 10,
    toleransi_pulang INT DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=latin1");
$cek = $koneksi->query("SELECT COUNT(*) as c FROM tb_toleransi");
$row = $cek->fetch_assoc();
if($row['c']==0) $koneksi->query("INSERT INTO tb_toleransi (toleransi_masuk, toleransi_istirahat_keluar, toleransi_istirahat_masuk, toleransi_pulang) VALUES (10,5,10,5)");

$tampil = $koneksi->query("SELECT * FROM tb_toleransi LIMIT 1");
$data = $tampil->fetch_assoc();
$masuk = $data['toleransi_masuk'] ?? 10;
$keluar = $data['toleransi_istirahat_keluar'] ?? 5;
$masuk2 = $data['toleransi_istirahat_masuk'] ?? 10;
$pulang = $data['toleransi_pulang'] ?? 5;

if (isset($_POST['simpan'])) {
    $tmasuk = intval($_POST['ttoleransi_masuk'] ?? 10);
    $tkeluar = intval($_POST['ttoleransi_keluar'] ?? 5);
    $tmasuk2 = intval($_POST['ttoleransi_masuk2'] ?? 10);
    $tpulang = intval($_POST['ttoleransi_pulang'] ?? 5);
    $sql = $koneksi->query("UPDATE tb_toleransi SET toleransi_masuk='$tmasuk', toleransi_istirahat_keluar='$tkeluar', toleransi_istirahat_masuk='$tmasuk2', toleransi_pulang='$tpulang'");
    if ($sql) {
        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'success',title:'Berhasil',text:'Toleransi telat berhasil diperbarui',confirmButtonColor:'#2563eb',confirmButtonText:'Selesai'}).then(()=>{window.location.href='?page=toleransi';});</script></body></html>"; exit;
    } else {
        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'error',title:'Gagal',text:'Gagal memperbarui toleransi!',confirmButtonColor:'#2563eb'});</script></body></html>";
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-clock mr-3"></i>
                Pengaturan Toleransi Telat
            </h3>
            <p class="text-indigo-200 text-sm mt-1">Atur batas menit keterlambatan sebelum dihitung terlambat</p>
        </div>
        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Masuk (Telat) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-stopwatch text-gray-400"></i>
                            </div>
                            <input type="number" min="0" max="60" name="ttoleransi_masuk" value="<?= htmlspecialchars($masuk) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-medium"/>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 font-bold text-sm">menit</span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Contoh: 10 = telat 10 menit baru dihitung terlambat</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Keluar Istirahat (Cepat) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-stopwatch text-gray-400"></i>
                            </div>
                            <input type="number" min="0" max="60" name="ttoleransi_keluar" value="<?= htmlspecialchars($keluar) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-medium"/>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 font-bold text-sm">menit</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Masuk Istirahat (Telat) <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-stopwatch text-gray-400"></i>
                            </div>
                            <input type="number" min="0" max="60" name="ttoleransi_masuk2" value="<?= htmlspecialchars($masuk2) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-medium"/>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 font-bold text-sm">menit</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Pulang Awal <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-stopwatch text-gray-400"></i>
                            </div>
                            <input type="number" min="0" max="60" name="ttoleransi_pulang" value="<?= htmlspecialchars($pulang) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-medium"/>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 font-bold text-sm">menit</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                    <p class="text-xs text-amber-800 flex gap-2"><i class="fas fa-info-circle mt-0.5"></i> <span>Jika karyawan telat <b>di bawah</b> toleransi, tidak dihitung terlambat & tidak kena denda. Contoh: toleransi 10 menit, telat 8 menit = aman.</span></p>
                </div>
                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic"><span class="text-rose-500">*</span> Wajib diisi</div>
                    <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-indigo-600 hover:bg-indigo-700 transition transform hover:-translate-y-0.5">
                        <i class="fas fa-save mr-2"></i> Simpan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
