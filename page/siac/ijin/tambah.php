<?php
$datanamakaryawan = "";
$datanoabsen = "";

if (isset($_GET['idkaryawan'])) {
    $idkaryawan = $_GET['idkaryawan'];
    $tampildetail = $koneksi->query("SELECT * FROM ms_karyawan WHERE id_karyawan = '$idkaryawan'");
    $datadetail = $tampildetail->fetch_assoc();
    $datanamakaryawan = $datadetail['nama_karyawan'] ?? '';
    $datanoabsen = $datadetail['no_absen'] ?? '';
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">

        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-clock mr-3"></i>
                Tambah Data Karyawan Izin
            </h3>
            <p class="text-amber-100 text-sm mt-1">Masukkan detail perizinan karyawan</p>
        </div>

        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Nama Karyawan <span class="text-rose-500">*</span></label>
                        <select name="idkaryawan" class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" required onchange="
                            var selected = this.options[this.selectedIndex];
                            document.getElementById('tnoabsen').value = selected.getAttribute('data-absen');
                        ">
                            <option value="">-- Pilih Karyawan --</option>
                            <?php
                            $sql_kry = $koneksi->query("SELECT * FROM ms_karyawan ORDER BY nama_karyawan ASC");
                            while ($dkry = $sql_kry->fetch_assoc()) {
                                echo "<option value='" . $dkry['id_karyawan'] . "' data-absen='" . $dkry['no_absen'] . "'>" . $dkry['nama_karyawan'] . " (" . $dkry['no_absen'] . ")</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">No. Absen</label>
                        <input type="text" name="tnoabsen" id="tnoabsen" readonly class="block w-full py-3 px-4 border border-gray-200 rounded-xl bg-gray-100 text-gray-500 font-medium" placeholder="-" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tanggal Izin <span class="text-rose-500">*</span></label>
                        <input type="date" name="ttgl1" value="<?= date('Y-m-d') ?>" required class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Jenis Izin <span class="text-rose-500">*</span></label>
                        <select name="tjenis" class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" required>
                            <option value="Datang Terlambat">Datang Terlambat</option>
                            <option value="Pulang Lebih Awal">Pulang Lebih Awal</option>
                            <option value="Meninggalkan Tempat Kerja">Meninggalkan Tempat Kerja</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Dari Jam <span class="text-rose-500">*</span></label>
                        <input type="time" name="ttime1" value="<?= date('H:i') ?>" required class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Sampai Jam <span class="text-rose-500">*</span></label>
                        <input type="time" name="ttime2" value="<?= date('H:i') ?>" required class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Keterangan Izin <span class="text-rose-500">*</span></label>
                        <input type="text" name="tketerangan" required class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" placeholder="Masukkan alasan izin..." />
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic">
                        <span class="text-rose-500">*</span> Wajib diisi
                    </div>
                    <div class="flex gap-3">
                        <a href="?page=ijin" class="inline-flex items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                            Batal
                        </a>
                        <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition duration-150 ease-in-out transform hover:-translate-y-0.5">
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
    $idkaryawan = $_POST['idkaryawan'];
    $ttgl1 = $_POST['ttgl1'];
    $ttime1 = $_POST['ttime1'];
    $ttime2 = $_POST['ttime2'];
    $tjenis = $_POST['tjenis'];
    $tketerangan = $_POST['tketerangan'];
    $tgl = date("Y-m-d");

    $sql = $koneksi->query("INSERT INTO tb_ijin (id_karyawan, tgl_pengajuan_ijin, tgl_ijin, waktu_awal, waktu_akhir, keterangan, jenis_ijin) VALUES ('$idkaryawan', '$tgl', '$ttgl1', '$ttime1', '$ttime2', '$tketerangan', '$tjenis')");

    if ($sql) {
        echo '<script>alert("Data Izin Tersimpan"); window.location.href="?page=ijin";</script>';
    }
}
?>