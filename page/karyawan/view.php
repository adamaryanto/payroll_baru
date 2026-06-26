<?php


if (isset($_GET['id'])) {
    $idu = $_GET['id'];
    $tampil = $koneksi->query("SELECT k.*, d.nama_departmen, s.nama_sub_department, j.jabatan, o.OS_DHK as label_os, g.golongan as label_gol 
                               FROM ms_karyawan k
                               LEFT JOIN ms_departmen d ON k.id_departmen = d.id_departmen
                               LEFT JOIN ms_sub_department s ON k.id_sub_department = s.id_sub_department
                               LEFT JOIN ms_jabatan j ON k.id_jabatan = j.id_jabatan
                               LEFT JOIN ms_os_dhk o ON k.id_os_dhk = o.id_os_dhk
                               LEFT JOIN ms_golongan g ON k.id_golongan = g.id_golongan
                               WHERE k.id_karyawan = '$idu'");
    $data = $tampil->fetch_assoc();
    $idkaryawan = $data['id_karyawan'];
    $noabsen = $data['no_absen'];
    $namakaryawan = $data['nama_karyawan'];
    $namadep = $data['nama_departmen'] ?? '-';
    $namasub = $data['nama_sub_department'] ?? '-';
    $namajab = $data['jabatan'] ?? '-';
    $tempatlahir = $data['tempat_lahir'];
    $tgllahir = $data['tgl_lahir'];
    $agama = $data['agama'];
    $tos = $data['label_os'];
    $golongan = $data['label_gol'];
    $statuskawin = $data['status_kawin'];
    $jeniskelamin = $data['jenis_kelamin'];
    $noktp = $data['no_ktp'];
    $nobpjs = $data['no_bpjs'];
    $alamatktp = $data['alamat_ktp'];
    $alamattinggal = $data['alamat_tinggal'];
    $statuskaryawan = $data['status_karyawan'];
    $tglaktif = $data['tgl_aktif'];
    $tglnonaktif = $data['tgl_nonaktif'];
    $contsimpan = "hidden";
    $contupdate = "";
} else {
    $idkaryawan = "";
    $noabsen = "";
    $namakaryawan = "";
    $tempatlahir = "";
    $tgllahir = "";
    $agama = "";
    $tos = "";
    $golongan = "";
    $statuskawin = "";
    $jeniskelamin = "";
    $noktp = "";
    $nobpjs = "";
    $alamatktp = "";
    $alamattinggal = "";
    $statuskaryawan = "";
    $tglaktif = "";
    $tglnonaktif = "";
    $contsimpan = "";
    $contupdate = "hidden";
}

?>

<div class="container-fluid px-4 mt-5 mb-5">
    <div class="card border-0 shadow-lg rounded-xl overflow-hidden">

        <div class="card-header bg-white border-b border-gray-100 py-5 px-6 flex flex-row items-center justify-between">
            <div class="flex-grow">
                <h3 class="text-2xl font-extrabold text-indigo-600 tracking-tight m-0"><i class="fas fa-user-tie mr-3 text-indigo-600"></i>Detail Data Karyawan</h3>
                <p class="text-sm text-gray-500 mt-1">Informasi lengkap profil karyawan.</p>
            </div>
            <div class="flex-shrink-0 ml-4">
                <a href="?page=karyawan" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition duration-200 shadow-md">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
            </div>
        </div>

        <div class="card-body p-6 bg-gray-50/30">

            <div class="mb-8">
                <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                    <i class="fas fa-id-badge mr-2 text-lg"></i> Informasi Dasar
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Nama Lengkap</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $namakaryawan ? $namakaryawan : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">No. Absen</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $noabsen ? $noabsen : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Status Karyawan</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php
                            if ($statuskaryawan == 'Aktif') {
                                echo '<span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold"><i class="fas fa-check-circle mr-1"></i> Aktif</span>';
                            } else {
                                echo '<span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold"><i class="fas fa-times-circle mr-1"></i> Non-Aktif</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Departemen</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $namadep; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Sub Departemen</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $namasub; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Jabatan</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $namajab; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">OS / DHK</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $tos ? $tos : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Golongan</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $golongan ? $golongan : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Upah Harian</label>
                        <div class="px-4 py-3 bg-indigo-50 rounded-lg border border-indigo-100 text-indigo-700 font-bold">
                            Rp <?php echo number_format($data['upah'] ?? 0, 0, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                    <i class="fas fa-user mr-2 text-lg"></i> Profil Pribadi
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Tempat Lahir</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $tempatlahir ? $tempatlahir : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Tanggal Lahir</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $tgllahir ? date('d F Y', strtotime($tgllahir)) : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Jenis Kelamin</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $jeniskelamin ? $jeniskelamin : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Agama</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $agama ? $agama : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Status Pernikahan</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $statuskawin ? $statuskawin : '-'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                    <i class="fas fa-address-card mr-2 text-lg"></i> Identitas & Administrasi
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">

                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">No. KTP</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium font-mono text-lg tracking-wider">
                            <?php echo $noktp ? $noktp : '-'; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">No. BPJS</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium font-mono text-lg tracking-wider">
                            <?php echo $nobpjs ? $nobpjs : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Alamat (Sesuai KTP)</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium min-h-[60px]">
                            <?php echo $alamatktp ? $alamatktp : '-'; ?>
                        </div>
                    </div>

                    <div class="form-group md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Alamat (Domisili / Tinggal)</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium min-h-[60px]">
                            <?php echo $alamattinggal ? $alamattinggal : '-'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h4 class="text-sm font-bold text-indigo-600 uppercase tracking-wider mb-4 flex items-center">
                    <i class="fas fa-calendar-alt mr-2 text-lg"></i> Riwayat Kepegawaian
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-500 mb-2">Tanggal Bergabung</label>
                        <div class="px-4 py-3 bg-gray-50 rounded-lg border border-gray-200 text-gray-800 font-medium">
                            <?php echo $tglaktif ? date('d F Y', strtotime($tglaktif)) : '-'; ?>
                        </div>
                    </div>
                    <?php if ($tglnonaktif && $tglnonaktif != '0000-00-00') { ?>
                        <div class="form-group">
                            <label class="block text-sm font-semibold text-gray-500 mb-2">Tanggal Berhenti (Resign)</label>
                            <div class="px-4 py-3 bg-red-50 rounded-lg border border-red-200 text-red-800 font-bold">
                                <?php echo date('d F Y', strtotime($tglnonaktif)); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php

$tid = @$_POST['tid'];
$tnama = @$_POST['tnama'];
$tnoabsen = @$_POST['tnoabsen'];
$tjabatan = @$_POST['tjabatan'];
$ttempatlahir = @$_POST['ttempatlahir'];
$ttanggallahir = @$_POST['ttanggallahir'];
$tagama = @$_POST['tagama'];
$tstatuskawin = @$_POST['tstatuskawin'];
$tjeniskelamin = @$_POST['tjeniskelamin'];
$tnoktp = @$_POST['tnoktp'];
$tnobpjs = @$_POST['tnobpjs'];
$talamatktp = @$_POST['talamatktp'];
$talamattinggal = @$_POST['talamattinggal'];
$ttanggalbergabung = @$_POST['ttanggalbergabung'];
$simpan = @$_POST['simpan'];
$update = @$_POST['update'];
$iduser = $_SESSION['iduser'];
if ($simpan) {
    $sql = $koneksi->query("insert into ms_karyawan(id_departmen,id_jabatan,no_absen,nama_karyawan,tempat_lahir,tgl_lahir,agama,status_kawin,jenis_kelamin,no_ktp,alamat_ktp,alamat_tinggal,status_karyawan,tgl_aktif) values('0','$tjabatan','$tnoabsen','$tnama','$ttempatlahir','$ttanggallahir','$tagama','$tstatuskawin','$tjeniskelamin','$tnoktp','$tnobpjs','$talamatktp','$talamattinggal','Aktif','$ttanggalbergabung')  ");
    if ($sql) {
?>
        <script type="text/javascript">
            alert("Data Tersimpan");
            window.location.href = "?page=karyawan";
        </script>
    <?php
    }
} //simpan if
elseif ($update) {
    $sql = $koneksi->query("update ms_karyawan set no_absen ='$tnoabsen' ,nama_karyawan = '$tnama',tempat_lahir = '$ttempatlahir' ,tgl_lahir = '$ttanggallahir',agama ='$tagama' ,status_kawin = '$tstatuskawin',jenis_kelamin = '$tjeniskelamin',no_ktp = '$tnoktp',no_bpjs = '$tnobpjs' ,alamat_ktp = '$talamatktp',alamat_tinggal = '$talamattinggal' , tgl_aktif = '$ttanggalbergabung' where id_karyawan = '$idu'  ");
    if ($sql) {
    ?>
        <script type="text/javascript">
            alert("Data Berhasil Ubah");
            window.location.href = "?page=karyawan";
        </script>
<?php
    }
} //update if
?>