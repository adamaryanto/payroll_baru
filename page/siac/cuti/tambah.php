<?php
$datanamakaryawan = "";
$datanoabsen = "";
$idkaryawan = "";

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
        <i class="fas fa-umbrella-beach mr-3"></i>
        Tambah Data Cuti
      </h3>
      <p class="text-indigo-100 text-sm mt-1">Masukkan detail pengajuan cuti karyawan</p>
    </div>

    <form method="POST">
      <div class="p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

          <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">Nama Karyawan <span class="text-rose-500">*</span></label>
            <select name="idkaryawan" class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" required onchange="
              var selected = this.options[this.selectedIndex];
              document.getElementById('tnoabsen').value = selected.getAttribute('data-absen');">
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
            <input type="text" name="tnoabsen" id="tnoabsen" value="<?= htmlspecialchars($datanoabsen) ?>" readonly class="block w-full py-3 px-4 border border-gray-200 rounded-xl bg-gray-100 text-gray-500 font-medium" />
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Dari Tanggal <span class="text-rose-500">*</span></label>
            <input type="date" name="ttgl1" value="<?= date('Y-m-d') ?>" required class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" />
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Sampai Tanggal <span class="text-rose-500">*</span></label>
            <input type="date" name="ttgl2" value="<?= date('Y-m-d') ?>" required class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" />
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-2">Keterangan Cuti <span class="text-rose-500">*</span></label>
            <input type="text" name="tketerangan" required class="block w-full py-3 px-4 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition font-medium" placeholder="Masukkan alasan cuti..." />
          </div>
        </div>

        <div class="flex items-center justify-between pt-6 border-t border-gray-100">
          <div class="text-xs text-gray-500 italic">
            <span class="text-rose-500">*</span> Wajib diisi
          </div>
          <div class="flex gap-3">
            <a href="?page=cuti" class="inline-flex items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
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
  $ttgl1 = $_POST['ttgl1'];
  $ttgl2 = $_POST['ttgl2'];
  $tketerangan = $_POST['tketerangan'];
  $tgl = date("Y-m-d");

  // Hitung lama hari
  $tgl1_ts = strtotime($ttgl1);
  $tgl2_ts = strtotime($ttgl2);
  $jarak = $tgl2_ts - $tgl1_ts;
  $hari = ($jarak / 86400) + 1; // 86400 detik dalam 1 hari

  $sql = $koneksi->query("INSERT INTO tb_cuti (id_karyawan, tgl_pengajuan_cuti, tgl_awal_cuti, tgl_akhir_cuti, lama, keterangan_cuti) VALUES ('$idkaryawan', '$tgl', '$ttgl1', '$ttgl2', '$hari', '$tketerangan')");

  if ($sql) {
    echo '<script>alert("Data Cuti Tersimpan"); window.location.href="?page=cuti";</script>';
  }
}
?>