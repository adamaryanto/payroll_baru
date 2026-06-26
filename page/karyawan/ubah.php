<?php
if (isset($_GET['id'])) {
    $idu = $_GET['id'];
    $tampil = $koneksi->query("SELECT * FROM ms_karyawan WHERE id_karyawan = '$idu'");
    $data = $tampil->fetch_assoc();

    $idkaryawan = $data['id_karyawan'];
    $iddepartmen = $data['id_departmen'];
    $idjabatan = $data['id_jabatan'];
    $idsubdept = $data['id_sub_department'];
    $idjadwal = $data['id_jadwal'];
    $noabsen = $data['no_absen'];
    $nama = $data['nama_karyawan'];
    $nobpjs = $data['no_bpjs'];
    $tempatlahir = $data['tempat_lahir'];
    $tgllahir = $data['tgl_lahir'];
    $noktp = $data['no_ktp'];
    $idos = $data['id_os_dhk'];
    $idgolongan = $data['id_golongan'];
    $alamatktp = $data['alamat_ktp'];
    $alamattinggal = $data['alamat_tinggal'];
    $statuskaryawan = $data['status_karyawan'];
    $tglaktif = $data['tgl_aktif'];
    $tglnonaktif = $data['tgl_nonaktif'];
    $jeniskelamin = $data['jenis_kelamin'];
    $agama = $data['agama'];
    $statuskawin = $data['status_kawin'];
    $upah = $data['upah'];
}

if (isset($_POST['update'])) {
    $tid = $_POST['tid'];
    $tnama = $koneksi->real_escape_string($_POST['tnama']);
    $tnoabsen = $koneksi->real_escape_string($_POST['tnoabsen']);
    $tnobpjs = $koneksi->real_escape_string($_POST['tnobpjs']);
    $tos = $koneksi->real_escape_string($_POST['tos']);
    $tgolongan = $koneksi->real_escape_string($_POST['tgolongan']);
    $tjeniskelamin = $koneksi->real_escape_string($_POST['tjeniskelamin']);
    $tagama = $koneksi->real_escape_string($_POST['tagama']);
    $ttempatlahir = $koneksi->real_escape_string($_POST['ttempatlahir']);
    $ttanggallahir = $koneksi->real_escape_string($_POST['ttanggallahir']);
    $tstatuskawin = $koneksi->real_escape_string($_POST['tstatuskawin']);
    $tnoktp = $koneksi->real_escape_string($_POST['tnoktp']);
    $tbpjs = $koneksi->real_escape_string($_POST['tnobpjs']);
    $tstatuskaryawan = $koneksi->real_escape_string($_POST['tstatuskaryawan']);
    $talamatktp = isset($_POST['talamatktp']) ? $koneksi->real_escape_string($_POST['talamatktp']) : '';
    $talamattinggal = isset($_POST['talamattinggal']) ? $koneksi->real_escape_string($_POST['talamattinggal']) : '';
    $ttanggalbergabung = isset($_POST['ttanggalbergabung']) && $_POST['ttanggalbergabung'] !== '' ? $koneksi->real_escape_string($_POST['ttanggalbergabung']) : date('Y-m-d');
    $tupah = !empty($_POST['tupah']) ? floatval(str_replace(['Rp', '.', ' '], '', $_POST['tupah'])) : 0;

    $tdepartmen = $koneksi->real_escape_string($_POST['tdepartmen']);
    $tsubdept = $koneksi->real_escape_string($_POST['tsubdept']);
    $tjabatan = $koneksi->real_escape_string($_POST['tjabatan']);

    $sql = $koneksi->query("UPDATE ms_karyawan SET 
        id_departmen = '$tdepartmen',
        id_jabatan = '$tjabatan',
        id_sub_department = '$tsubdept',
        nama_karyawan = '$tnama',
        tempat_lahir = '$ttempatlahir',
        tgl_lahir = '$ttanggallahir',
        agama = '$tagama',
        status_kawin = '$tstatuskawin',
        jenis_kelamin = '$tjeniskelamin',
        no_ktp = '$tnoktp',
        no_bpjs = '$tbpjs',
        alamat_ktp = '$talamatktp',
        alamat_tinggal = '$talamattinggal',
        tgl_aktif = '$ttanggalbergabung',
        id_os_dhk = '$tos',
        id_golongan = '$tgolongan',
        status_karyawan = '$tstatuskaryawan',
        upah = '$tupah'
        WHERE id_karyawan = '$tid'
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
                    text: 'Data Berhasil Diperbarui',
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=karyawan';
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
                    text: 'Gagal Perbarui Data: " . $koneksi->error . "',
                    confirmButtonColor: '#4f46e5',
                    confirmButtonText: 'Ok'
                });
            </script>
        </body>
        </html>";
    }
}
?>

<div class="container-fluid px-4 mt-5 mb-5">
    <div class="card border-0 shadow-lg rounded-xl overflow-hidden">

        <div class="card-header bg-white border-b border-gray-100 py-5 px-6 flex flex-row items-center justify-between">
            <div class="flex-grow">
                <h3 class="text-2xl font-extrabold text-indigo-600 tracking-tight m-0"><i class="fas fa-user-tie mr-3 text-indigo-600"></i>Ubah Data Karyawan</h3>
                <p class="text-sm text-gray-500 mt-1">Perbarui informasi karyawan di bawah ini.</p>
            </div>
            <div class="flex-shrink-0 ml-4">
                <a href="?page=karyawan" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition duration-200 shadow-md">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
            </div>
        </div>

        <form method="POST" id="formUbahKaryawan" enctype="multipart/form-data">
            <div class="card-body p-6 bg-gray-50/30">

                <!-- Section 1: Personal & Position -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-user-circle mr-2 text-lg"></i> Informasi Pribadi & Jabatan
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">

                        <input type="hidden" name="tid" value="<?= $idkaryawan; ?>">

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. Absen</label>
                            <input type="text" name="tnoabsen" value="<?= $noabsen; ?>" readonly class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-100 text-gray-500 font-bold cursor-not-allowed">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input autocomplete="off" type="text" name="tnama" value="<?= $nama; ?>" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all outline-none" placeholder="Masukkan nama lengkap">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Karyawan</label>
                            <select name="tstatuskaryawan" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="Aktif" <?= ($statuskaryawan == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="Non Aktif" <?= ($statuskaryawan == 'Non Aktif') ? 'selected' : ''; ?>>Non Aktif</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Departemen</label>
                            <select name="tdepartmen" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih Departemen -" data-delete-route="bagian">
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Departemen Baru...</option>
                                <?php
                                $q_dept = $koneksi->query("SELECT * FROM ms_departmen");
                                while ($d = $q_dept->fetch_assoc()) {
                                    $sel = ($d['id_departmen'] == $iddepartmen || $d['nama_departmen'] == $iddepartmen) ? 'selected' : '';
                                    echo "<option value='" . $d['id_departmen'] . "' $sel>" . $d['nama_departmen'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Sub Departemen</label>
                            <select name="tsubdept" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih Sub Bagian -" data-delete-route="subbagian">
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Sub Bagian Baru...</option>
                                <?php
                                $q_sub = $koneksi->query("SELECT * FROM ms_sub_department");
                                while ($d = $q_sub->fetch_assoc()) {
                                    $sel = ($d['id_sub_department'] == $idsubdept || $d['nama_sub_department'] == $idsubdept) ? 'selected' : '';
                                    echo "<option value='" . $d['id_sub_department'] . "' $sel>" . $d['nama_sub_department'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Jabatan</label>
                            <select name="tjabatan" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih Jabatan -" data-delete-route="jabatan">
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Jabatan Baru...</option>
                                <?php
                                $q_jab = $koneksi->query("SELECT * FROM ms_jabatan");
                                while ($d = $q_jab->fetch_assoc()) {
                                    $sel = ($d['id_jabatan'] == $idjabatan || $d['jabatan'] == $idjabatan) ? 'selected' : '';
                                    echo "<option value='" . $d['id_jabatan'] . "' $sel>" . $d['jabatan'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>



                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">OS / DHK <span class="text-red-500">*</span></label>
                            <select name="tos" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih -" data-delete-route="os_dhk" required>
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Baru...</option>
                                <?php
                                $q_os = $koneksi->query("SELECT * FROM ms_os_dhk");
                                while($d = $q_os->fetch_assoc()) {
                                    $sel = ($d['id_os_dhk'] == $idos) ? 'selected' : '';
                                    echo "<option value='".$d['id_os_dhk']."' $sel>".$d['OS_DHK']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Golongan</label>
                            <div>
                                <select name="tgolongan" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih -" data-delete-route="golongan">
                                    <option value=""></option>
                                    <option value="add_new" class="font-bold text-indigo-600">+ Tambah Baru...</option>
                                    <?php
                                    $q_gol = $koneksi->query("SELECT * FROM ms_golongan");
                                    while($d = $q_gol->fetch_assoc()) {
                                        $sel = ($d['id_golongan'] == $idgolongan) ? 'selected' : '';
                                        echo "<option value='".$d['id_golongan']."' $sel>".$d['golongan']."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Jenis Kelamin</label>
                            <select name="tjeniskelamin" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="Laki-laki" <?= ($jeniskelamin == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?= ($jeniskelamin == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Agama</label>
                            <select name="tagama" class="w-full select2 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="">- Pilih Agama -</option>
                                <?php
                                $agamas = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Budha', 'Khonghucu'];
                                foreach ($agamas as $a) {
                                    $sel = ($a == $agama) ? 'selected' : '';
                                    echo "<option value='$a' $sel>$a</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tempat Lahir</label>
                            <input type="text" name="ttempatlahir" value="<?= $tempatlahir; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Contoh: Jakarta">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Lahir</label>
                            <input type="date" name="ttanggallahir" value="<?= $tgllahir; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Kawin</label>
                            <select name="tstatuskawin" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="" <?= ($statuskawin == '') ? 'selected' : ''; ?>>- Pilih Status -</option>
                                <option value="Kawin" <?= ($statuskawin == 'Kawin') ? 'selected' : ''; ?>>Kawin</option>
                                <option value="Belum Kawin" <?= ($statuskawin == 'Belum Kawin') ? 'selected' : ''; ?>>Belum Kawin</option>
                                <option value="Janda" <?= ($statuskawin == 'Janda') ? 'selected' : ''; ?>>Janda</option>
                                <option value="Duda" <?= ($statuskawin == 'Duda') ? 'selected' : ''; ?>>Duda</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Documents & Domisile -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-file-alt mr-2 text-lg"></i> Dokumen & Domisili
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. KTP</label>
                            <input type="text" name="tnoktp" value="<?= $noktp; ?>" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="16 Digit No. KTP">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. BPJS</label>
                            <input type="text" name="tnobpjs" value="<?= $nobpjs; ?>" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Masukkan No. BPJS">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Alamat Lengkap (KTP)</label>
                            <textarea name="talamatktp" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Jl. Contoh No. 123..."><?= $alamatktp; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Alamat Tinggal</label>
                            <textarea name="talamattinggal" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Jl. Contoh No. 123..."><?= $alamattinggal; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Payroll Info -->
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-money-bill-wave mr-2 text-lg"></i> Informasi Penggajian
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Bergabung</label>
                            <input type="date" name="ttanggalbergabung" value="<?= $tglaktif; ?>" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Upah Harian Baru (Rp) <span class="text-red-500">*</span></label>
                            <input type="text" name="tupah" id="tupah_format" value="<?= number_format($upah, 0, ',', '.'); ?>" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-indigo-700" placeholder="0">
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-footer bg-gray-50 px-8 py-5 flex items-center justify-between border-t border-gray-200">
                <p class="text-xs text-gray-400 italic">Tanda <span class="text-red-500 font-bold">*</span> wajib diisi.</p>

                <div class="flex gap-3">
                    <button type="reset" class="px-6 py-2.5 rounded-lg border border-gray-300 text-gray-700 font-bold hover:bg-gray-100 transition-all cursor-pointer">
                        <i class="fas fa-undo mr-2"></i> Batal
                    </button>

                    <button type="submit" name="update" value="Update" class="px-8 py-2.5 border-0 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-0.5">
                        <i class="fas fa-save mr-2"></i> Perbarui Data
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    /* Menghilangkan panah pada input number agar lebih bersih */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tupah_format').on('input', function() {
            let val = $(this).val().replace(/[^0-9]/g, '');
            if (val !== "") {
                $(this).val(new Intl.NumberFormat('id-ID').format(parseInt(val)));
            }
        });

        // 1. Logika untuk Select2 Manage
        $('.select2-manage').on('change', function() {
            var $select = $(this);
            var selectedVal = $select.val();
            var route = $select.data('delete-route');

            if (selectedVal === 'add_new') {
                var label = $select.closest('.form-group').find('label').text().replace('*', '').trim();
                var newData = prompt("Masukkan " + label + " Baru:");

                if (newData && newData.trim() !== "") {
                    $.post('page/ajax/tambah_master.php', {
                        value: newData.trim(),
                        route: route
                    }, function(res) {
                        if (res.success) {
                            var newOption = new Option(res.value, res.id, true, true);
                            $select.append(newOption).trigger('change');
                        } else {
                            alert('Gagal menambah data: ' + res.message);
                            $select.val('').trigger('change');
                        }
                    }, 'json');
                } else {
                    $select.val('').trigger('change');
                }
            }
        });

        // 2. Logika untuk Reset Form
        $('#formUbahKaryawan').on('reset', function() {
            setTimeout(function() {
                $('.select2-manage').trigger('change.select2');
                $('.select2').trigger('change.select2');
            }, 10);
        });

    });
</script>