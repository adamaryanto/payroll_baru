<?php
if (isset($_POST['simpan'])) {
    $nama = $koneksi->real_escape_string($_POST['tnama']);
    $noabsen = $koneksi->real_escape_string($_POST['tnoabsen']);
    $departmen = $koneksi->real_escape_string($_POST['tdepartmen']);
    $subdept = $koneksi->real_escape_string($_POST['tsubdept']);
    $jabatan = $koneksi->real_escape_string($_POST['tjabatan']);
    $os = $koneksi->real_escape_string($_POST['tos']);
    $golongan = $koneksi->real_escape_string($_POST['tgolongan']);
    $jeniskelamin = $koneksi->real_escape_string($_POST['tjeniskelamin']);
    $agama = $koneksi->real_escape_string($_POST['tagama']);
    $tempatlahir = $koneksi->real_escape_string($_POST['ttempatlahir']);
    $tanggallahir = $koneksi->real_escape_string($_POST['ttanggallahir']);
    $statuskawin = $koneksi->real_escape_string($_POST['tstatuskawin']);
    $noktp = $koneksi->real_escape_string($_POST['tnoktp']);
    $nobpjs = $koneksi->real_escape_string($_POST['tnobpjs']);
    $alamatktp = isset($_POST['talamatktp']) ? $koneksi->real_escape_string($_POST['talamatktp']) : '';
    $alamattinggal = isset($_POST['talamattinggal']) ? $koneksi->real_escape_string($_POST['talamattinggal']) : '';
    $tanggalbergabung = isset($_POST['ttanggalbergabung']) && $_POST['ttanggalbergabung'] !== '' ? $koneksi->real_escape_string($_POST['ttanggalbergabung']) : date('Y-m-d');
    $upah = !empty($_POST['tupah']) ? floatval(str_replace(['Rp', '.', ' '], '', $_POST['tupah'])) : 0;

    $sql = $koneksi->query("INSERT INTO ms_karyawan (
        id_departmen, id_jabatan, no_absen, nama_karyawan, tempat_lahir, tgl_lahir, agama, 
        status_kawin, jenis_kelamin, no_ktp, no_bpjs, alamat_ktp, alamat_tinggal, 
        status_karyawan, tgl_aktif, id_sub_department, id_os_dhk, id_golongan, id_jadwal, upah
    ) VALUES (
        '$departmen', '$jabatan', '$noabsen', '$nama', '$tempatlahir', '$tanggallahir', '$agama',
        '$statuskawin', '$jeniskelamin', '$noktp', '$nobpjs', '$alamatktp', '$alamattinggal',
        'Aktif', '$tanggalbergabung', '$subdept', '$os', '$golongan', '0', '$upah'
    )");

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
                    text: 'Data Berhasil Disimpan',
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
                    text: 'Gagal Simpan Data: " . $koneksi->error . "',
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
                <h3 class="text-2xl font-extrabold text-indigo-600 tracking-tight m-0"><i class="fas fa-user-tie mr-3 text-indigo-600"></i>Tambah Data Karyawan</h3>
                <p class="text-sm text-gray-500 mt-1">Lengkapi formulir di bawah ini untuk mendaftarkan karyawan baru.</p>
            </div>
            <div class="flex-shrink-0 ml-4">
                <a href="?page=karyawan" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition duration-200 shadow-md">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="formTambahKaryawan">
            <div class="card-body p-6 bg-gray-50/30">
                
                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-user-circle mr-2 text-lg"></i> Informasi Pribadi & Jabatan
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        
                        <input type="hidden" name="tid">

                          <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. Absen</label>
                                  <?php
                                $q_absen = $koneksi->query("SELECT MAX(CAST(no_absen AS UNSIGNED)) as max_absen FROM ms_karyawan");
                                $dt_absen = $q_absen->fetch_assoc();
                                $new_absen = (int)$dt_absen['max_absen'] + 1;
                            ?>
                            <input type="text" name="tnoabsen" value="<?= $new_absen; ?>" readonly class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-100 text-gray-500 font-bold cursor-not-allowed">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input autocomplete="off" type="text" name="tnama" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all outline-none" placeholder="Masukkan nama lengkap">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Karyawan</label>
                            <select name="tstatuskaryawan" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="Aktif">Aktif</option>
                                <option value="Non Aktif">Non Aktif</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Departemen</label>
                            <select name="tdepartmen" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih Departemen -" data-delete-route="bagian">
                                <option value=""></option>
                                <option value="add_new" class="font-bold text-indigo-600">+ Tambah Departemen Baru...</option>
                                <?php
                                $q_dept = $koneksi->query("SELECT * FROM ms_departmen");
                                while($d = $q_dept->fetch_assoc()) {
                                    echo "<option value='".$d['id_departmen']."'>".$d['nama_departmen']."</option>";
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
                                while($d = $q_sub->fetch_assoc()) {
                                    echo "<option value='".$d['id_sub_department']."'>".$d['nama_sub_department']."</option>";
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
                                while($d = $q_jab->fetch_assoc()) {
                                    echo "<option value='".$d['id_jabatan']."'>".$d['jabatan']."</option>";
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
                                    echo "<option value='".$d['id_os_dhk']."'>".$d['OS_DHK']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Golongan</label>
                            <div class="max-w-xs">
                                <select name="tgolongan" class="w-full select2-manage px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" data-placeholder="- Pilih -" data-delete-route="golongan">
                                    <option value=""></option>
                                    <option value="add_new" class="font-bold text-indigo-600">+ Tambah Baru...</option>
                                    <?php
                                    $q_gol = $koneksi->query("SELECT * FROM ms_golongan");
                                    while($d = $q_gol->fetch_assoc()) {
                                        echo "<option value='".$d['id_golongan']."'>".$d['golongan']."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Jenis Kelamin</label>
                            <select name="tjeniskelamin" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Agama</label>
                            <select name="tagama" class="w-full select2 px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="">- Pilih Agama -</option>
                                <option value="Islam">Islam</option>
                                <option value="Kristen Protestan">Kristen Protestan</option>
                                <option value="Kristen Katolik">Kristen Katolik</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Budha">Budha</option>
                                <option value="Khonghucu">Khonghucu</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tempat Lahir</label>
                            <input type="text" name="ttempatlahir" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Contoh: Jakarta">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Lahir</label>
                            <input type="date" name="ttanggallahir" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status Kawin</label>
                            <select name="tstatuskawin" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <option value="">- Pilih Status -</option>
                                <option value="Kawin">Kawin</option>
                                <option value="Belum Kawin">Belum Kawin</option>
                                <option value="Janda">Janda</option>
                                <option value="Duda">Duda</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Bergabung <span class="text-red-500">*</span></label>
                            <input type="date" name="ttanggalbergabung" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Upah Harian (Rp) <span class="text-red-500">*</span></label>
                            <input type="text" name="tupah" id="tupah_format" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-indigo-700" placeholder="0">
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                        <i class="fas fa-file-alt mr-2 text-lg"></i> Dokumen & Domisili
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. KTP</label>
                            <input type="text" name="tnoktp" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="16 Digit No. KTP">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">No. BPJS</label>
                            <input type="text" name="tnobpjs" class="max-w-md w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="No. BPJS Ketenagakerjaan/Kesehatan">
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Alamat Lengkap (KTP)</label>
                            <textarea name="talamatktp" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Jl. Contoh No. 123..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Alamat Lengkap (Tinggal)</label>
                            <textarea name="talamattinggal" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Jl. Contoh No. 123..."></textarea>
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
                    <button type="submit" name="simpan" value="Simpan" class="px-8 py-2.5 border-0 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-0.5">
                        <i class="fas fa-save mr-2"></i> Simpan Data
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

    $('.select2-manage').on('change', function() {
        var $select = $(this);
        var selectName = $select.attr('name');
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
           // 2. Logika untuk Reset Form
        $('#formUbahKaryawan').on('reset', function() {
            setTimeout(function() {
                $('.select2-manage').trigger('change.select2');
                $('.select2').trigger('change.select2');
            }, 10);
        });
    });
});
</script>