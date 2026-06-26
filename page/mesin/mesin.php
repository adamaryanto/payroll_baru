<?php

$tampil=$koneksi->query("SELECT * from tb_mesin");
$data=$tampil->fetch_assoc();
$idmesin =$data['id_mesin'];
$nomesin = $data['no_mesin'];
$namamesin = $data['nama_mesin'];
$ipmesin = $data['ip_mesin'];
$commkey = $data['comm_key'];
$port = $data['port'];

?>
<div class="container-fluid px-0">
    <div class="modern-card overflow-hidden">
        <div class="modern-panel-header">
            <h3 class="flex items-center gap-3">
                <i class="fas fa-microchip"></i>
                Setting Konfigurasi Mesin Absensi
            </h3>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="p-6 md:p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <div class="form-group border-0 p-0 m-0">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-hashtag text-brand-500"></i> No Mesin <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="tnomesin" value="<?php echo $nomesin; ?>" 
                                   required class="modern-input w-full" placeholder="Contoh: 1">
                        </div>

                        <div class="form-group border-0 p-0 m-0">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-server text-brand-500"></i> Nama Mesin <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="tnamamesin" value="<?php echo $namamesin; ?>" 
                                   required class="modern-input w-full" placeholder="Contoh: Mesin Kantara">
                        </div>

                        <div class="form-group border-0 p-0 m-0">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-network-wired text-brand-500"></i> IP Mesin <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="tipmesin" value="<?php echo $ipmesin; ?>" 
                                   required class="modern-input w-full" placeholder="Contoh: 192.168.1.201">
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <div class="form-group border-0 p-0 m-0">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-key text-brand-500"></i> Comm Key <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="tcommkey" value="<?php echo $commkey; ?>" 
                                   required class="modern-input w-full" placeholder="Default: 0">
                        </div>

                        <div class="form-group border-0 p-0 m-0">
                            <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                <i class="fas fa-plug text-brand-500"></i> Port <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="tport" value="<?php echo $port; ?>" 
                                   required class="modern-input w-full" placeholder="Default: 4370">
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-8 flex gap-3">
                            <i class="fas fa-info-circle text-amber-500 mt-1"></i>
                            <div class="text-sm text-amber-800">
                                <strong>Catatan Penting:</strong> 
                                Pastikan mesin dalam keadaan terkoneksi dengan jaringan yang sama. Perubahan IP atau Port yang salah dapat menyebabkan kegagalan tarik data.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer / Action Area -->
                <div class="mt-10 pt-6 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-slate-500 text-sm">
                        <span class="text-rose-500 font-bold">*</span> Harus diisi dengan benar
                    </div>
                    <button type="submit" name="simpan" value="Simpan" class="btn-modern btn-primary px-10 py-3 flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        Simpan Konfigurasi
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php

$tnomesin = @$_POST['tnomesin'] ;
$tnamamesin = @$_POST ['tnamamesin'];
$tipmesin = @$_POST ['tipmesin'];
$tcommkey = @$_POST ['tcommkey'];
$tport = @$_POST ['tport'];

$simpan = @$_POST ['simpan'];
if($simpan) {
$sql = $koneksi->query("update tb_mesin set no_mesin='$tnomesin' , nama_mesin='$tnamamesin' , ip_mesin='$tipmesin' , comm_key='$tcommkey' , port='$tport' ");
if($sql) {
        ?>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <script type="text/javascript">
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Konfigurasi Mesin berhasil diperbarui!',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    window.location.href="?page=mesin";
                });
            </script>
            <?php
    }
}//simpan if
?>