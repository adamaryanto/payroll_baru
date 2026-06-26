<?php
if (isset($_GET['id'])) {
    $idu = $_GET['id'];
    $tampil = $koneksi->query("SELECT * FROM ms_login WHERE id_login = '$idu'");
    $data = $tampil->fetch_assoc();
    $nama = $data['username'];
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">

        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-user-edit mr-3"></i>
                Ubah Data User
            </h3>
            <p class="text-slate-300 text-sm mt-1">Perbarui informasi akun dan hak akses pengguna</p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="p-8">
                <!-- Username Input -->
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Username <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input placeholder="Masukkan username..." autocomplete="off" type="text" name="tnama" value="<?php echo $nama; ?>" required
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 sm:text-sm transition duration-150 ease-in-out font-medium" />
                    </div>
                </div>

                <!-- Password Input -->
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input placeholder="Masukkan password..." autocomplete="off" type="text" name="tpassword" value="<?php echo $data['password']; ?>" required
                            class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 sm:text-sm transition duration-150 ease-in-out font-medium" />
                    </div>
                </div>

                <!-- Role Selection -->
                <div class="mb-8">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        Role / Hak Akses <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user-tag text-gray-400"></i>
                        </div>
                        <?php
                        $is_self = ($_SESSION['iduser'] == $idu);
                        $can_edit_role = (strtolower($_SESSION['role']) == 'owner' || strtolower($_SESSION['role']) == 'admin master');
                        $disable_role = ($is_self && strtolower($_SESSION['role']) != 'owner');
                        ?>
                        <select name="trole" <?php echo $disable_role ? 'disabled' : ''; ?> class="block w-full pl-10 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 sm:text-sm rounded-xl appearance-none bg-gray-50 font-medium cursor-pointer" required>
                            <option value="Owner" <?php if ($data['role'] == 'Owner') echo 'selected'; ?>>Owner</option>
                            <option value="Admin HR" <?php if ($data['role'] == 'Admin HR') echo 'selected'; ?>>Admin HR</option>
                            <option value="Kepala Pabrik" <?php if ($data['role'] == 'Kepala Pabrik') echo 'selected'; ?>>Kepala Pabrik</option>
                            <option value="Admin Master" <?php if ($data['role'] == 'Admin Master') echo 'selected'; ?>>Admin Master</option>
                        </select>
                        <?php if ($disable_role): ?>
                            <input type="hidden" name="trole" value="<?php echo $data['role']; ?>">
                        <?php endif; ?>
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                        <div class="text-xs text-gray-500 italic">
                            <span class="text-rose-500">*</span> Wajib diisi
                        </div>
                        <div class="flex gap-3">
                            <a href="?page=user" class="inline-flex items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition duration-150 ease-in-out">
                                Batal
                            </a>
                            <button type="submit" name="simpan" value="Simpan" class="inline-flex items-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition duration-150 ease-in-out transform hover:-translate-y-0.5">
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
    $tnama     = $_POST['tnama'] ?? '';
    $trole     = $_POST['trole'] ?? '';
    $tpassword = $_POST['tpassword'] ?? '';

    $sql = $koneksi->query("INSERT INTO ms_login (id_perusahaan, username, password, role) VALUES ('1', '$tnama', '$tpassword', '$trole')");

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
                    text: 'Data Berhasil Tersimpan',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=user';
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
                    text: 'Gagal menyimpan data!',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Ok'
                });
            </script>
        </body>
        </html>";
    }
}
?>

<style>
    /* Premium Look Overrides */
    .form-control:focus {
        border-color: #334155;
        box-shadow: 0 0 0 3px rgba(51, 65, 85, 0.1);
    }
</style>

<?php
if (isset($_POST['simpan'])) {
    $tnama     = $_POST['tnama'] ?? '';
    $trole     = $_POST['trole'] ?? '';
    $tpassword = $_POST['tpassword'] ?? '';

    $sql = $koneksi->query("UPDATE ms_login SET username='$tnama', password='$tpassword', role='$trole' WHERE id_login = '$idu'");
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
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Selesai'
                }).then((result) => {
                    window.location.href='?page=user';
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
                    text: 'Gagal memperbarui data: " . $koneksi->error . "',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Ok'
                });
            </script>
        </body>
        </html>";
    }
}
?>