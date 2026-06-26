<div class="container-fluid px-4 mt-5">
    <div class="card border-0 shadow-lg rounded-xl overflow-hidden">
        <div class="card-header bg-white border-b border-gray-100 py-5 px-6">
            <h3 class="text-2xl font-extrabold text-gray-800 tracking-tight m-0">Tambah Data Jabatan</h3>
            <p class="text-sm text-gray-500 mt-1">Masukkan nama jabatan baru ke dalam sistem.</p>
        </div>

        <form method="POST">
            <div class="card-body p-6 bg-gray-50/30">
                <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Jabatan <span class="text-red-500">*</span></label>
                        <input autocomplete="off" type="text" name="tjabatan" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Contoh: Manager, Supervisor, dll">
                    </div>
                </div>
            </div>

            <div class="card-footer bg-gray-50 px-8 py-5 flex items-center justify-between border-t border-gray-200">
                <p class="text-xs text-gray-400 italic">Tanda <span class="text-red-500 font-bold">*</span> wajib diisi.</p>
                <div class="flex gap-3">
                    <a href="?page=jabatan" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 font-bold hover:bg-gray-100 transition-all no-underline">Batal</a>
                    <button type="submit" name="simpan" value="Simpan" class="px-8 py-2.5 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                        <i class="fas fa-save mr-2"></i> Simpan Jabatan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
if(isset($_POST['simpan'])) {
    $tjabatan = $_POST['tjabatan'];
    $sql = $koneksi->query("INSERT INTO ms_jabatan (jabatan) VALUES ('$tjabatan')");
    if($sql) {
        echo "<script>alert('Data Berhasil Disimpan'); window.location='?page=jabatan';</script>";
    } else {
        echo "<script>alert('Gagal Simpan Data');</script>";
    }
}
?>
