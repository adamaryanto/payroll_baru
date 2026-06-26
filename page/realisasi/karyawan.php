<?php
// 1. Ambil ID Realisasi
$idrealisasi = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : (!empty($_POST['id_real_hidden']) ? intval($_POST['id_real_hidden']) : 0);

if ($idrealisasi <= 0) {
    echo "<script>alert('ID Realisasi tidak valid!'); window.location.href='?page=realisasi';</script>";
    exit;
}

// Check status realisasi
$cek_real = $koneksi->query("SELECT status_realisasi, id_rkk, tgl_realisasi FROM tb_realisasi WHERE id_realisasi = '$idrealisasi'");
$data_real = $cek_real->fetch_assoc();
if ($data_real['status_realisasi'] >= 2) {
    echo "<script>alert('Tidak bisa menambah karyawan karena status Realisasi sudah Selesai/Approved!'); window.location.href='?page=realisasi&aksi=kelola&id=$idrealisasi';</script>";
    exit;
}
$idrkk_ref = $data_real['id_rkk'];

// 2. Logika Simpan
if (isset($_POST['simpan_karyawan'])) {
    $id_real_fix = intval($_POST['id_real_hidden']);
    $nama_manual = $koneksi->real_escape_string($_POST['nama_karyawan_manual']);
    
    $upah        = !empty($_POST['upah']) ? floatval(str_replace(['Rp', '.', ' '], '', $_POST['upah'])) : 0;
    $id_dept     = $_POST['id_departmen'];
    $id_sub      = $_POST['id_sub_department'];
    $id_os       = intval($_POST['id_os_dhk'] ?? 0);
    $id_gol      = intval($_POST['id_golongan'] ?? 0);
    $id_jadwal   = $_POST['id_jadwal'];
    $ra_masuk    = $_POST['ra_masuk'] ?: '00:00:00';
    $ra_keluar   = $_POST['ra_keluar'] ?: '00:00:00';

    // Handle Tags untuk Dept & Sub
    if (!empty($id_dept) && !is_numeric($id_dept)) {
        $name_dept = $koneksi->real_escape_string($id_dept);
        $koneksi->query("INSERT INTO ms_departmen (nama_departmen) VALUES ('$name_dept')");
        $id_dept = $koneksi->insert_id;
    }
    if (!empty($id_sub) && !is_numeric($id_sub)) {
        $name_sub = $koneksi->real_escape_string($id_sub);
        $dept_id_val = is_numeric($id_dept) ? $id_dept : 0;
        $koneksi->query("INSERT INTO ms_sub_department (nama_sub_department, id_departmen) VALUES ('$name_sub', '$dept_id_val')");
        $id_sub = $koneksi->insert_id;
    }

    if (!empty($nama_manual) && !empty($id_jadwal) && $id_real_fix > 0) {
        $tgl_ref = $data_real['tgl_realisasi'] ?? date('Y-m-d');

        // Ambil data jadwal untuk detail shift
        $q_j_detail = $koneksi->query("SELECT * FROM tb_jadwal WHERE id_jadwal = '$id_jadwal'");
        $d_j_detail = $q_j_detail->fetch_assoc();
        $s_masuk    = $d_j_detail['jam_masuk'] ?? '00:00:00';
        $s_keluar   = $d_j_detail['jam_keluar'] ?? '00:00:00';
        $ist_masuk  = $d_j_detail['istirahat_masuk'] ?? '00:00:00';
        $ist_keluar = $d_j_detail['istirahat_keluar'] ?? '00:00:00';

        // Tambahkan OS_DHK dan golongan ke query
        $query = "INSERT INTO tb_realisasi_detail 
            (id_realisasi, id_rkk, id_rkk_detail, id_karyawan, nama_karyawan_manual, 
             id_departmen, id_sub_department, r_upah, id_jadwal, status_realisasi_detail,
             r_potongan_telat, r_potongan_istirahat_awal, r_potongan_istirahat_telat, 
             r_potongan_lainnya, r_jam_masuk, r_jam_keluar, r_istirahat_masuk, 
             r_istirahat_keluar, r_status, r_update, ra_masuk, ra_keluar, 
             ra_istirahat_masuk, ra_istirahat_keluar, hasil_kerja, lembur, 
             tgl_realisasi_detail, r_potongan_pulang, r_potongan_tidak_lengkap,
             id_os_dhk, id_golongan) 
            VALUES 
            ($id_real_fix, '$idrkk_ref', 0, 0, '$nama_manual', 
             '$id_dept', '$id_sub', '$upah', '$id_jadwal', 1,
             0, 0, 0, 0, '$s_masuk', '$s_keluar', '$ist_masuk', '$ist_keluar', 'Hadir', 'manual', 
             '$ra_masuk', '$ra_keluar', '00:00:00', '00:00:00', '-', 0, 
             '$tgl_ref', 0, 0, '$id_os', '$id_gol')";

        if ($koneksi->query($query)) {
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Karyawan Manual Berhasil Ditambahkan',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.href = '?page=realisasi&aksi=karyawan&id=$id_real_fix';
                });
            </script>";
            exit;
        } else {
            echo "Error: " . $koneksi->error;
        }
    }
}

// 3. Ambil data master
$list_dept   = $koneksi->query("SELECT * FROM ms_departmen ORDER BY nama_departmen ASC");
$list_sub    = $koneksi->query("SELECT * FROM ms_sub_department ORDER BY nama_sub_department ASC");
$list_jadwal = $koneksi->query("SELECT * FROM tb_jadwal ORDER BY id_jadwal ASC");
$list_os     = $koneksi->query("SELECT * FROM ms_os_dhk ORDER BY OS_DHK ASC");
$list_gol    = $koneksi->query("SELECT * FROM ms_golongan ORDER BY golongan ASC");
// Default upah baku (Harian standar)
$default_upah = 115000;
?>

<div class="container-fluid px-3 mt-8 mb-10">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6 md:px-10">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-white/10 rounded-2xl backdrop-blur-sm">
                        <i class="fas fa-user-plus text-white text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-white m-0">Tambah Karyawan Manual</h2>
                        <p class="text-blue-100 text-sm mt-1">Lengkapi data untuk menghindari error tampilan</p>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="id_real_hidden" value="<?= $idrealisasi; ?>">
                <input type="hidden" name="ra_masuk" value="">
                <input type="hidden" name="ra_keluar" value="">
                <div class="p-6 md:p-10">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Nama Lengkap Karyawan <span class="text-rose-500">*</span></label>
                            <input type="text" name="nama_karyawan_manual" id="nama_karyawan_manual" required autofocus class="block w-full px-4 py-3 border border-gray-300 rounded-2xl outline-none" placeholder="Nama Karyawan..." />
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Upah Harian (Manual) <span class="text-rose-500">*</span></label>
                            <input type="text" name="upah" id="upah_manual" required class="block w-full px-4 py-3 border border-gray-300 rounded-2xl outline-none font-bold text-blue-600" value="<?= number_format($default_upah, 0, ',', '.') ?>" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Shift Kerja</label>
                            <select name="id_jadwal" required class="block w-full px-4 py-3 border border-gray-300 rounded-2xl outline-none">
                                <option value="">- Pilih Shift -</option>
                                <?php while ($j = $list_jadwal->fetch_assoc()) : ?>
                                    <option value="<?= $j['id_jadwal']; ?>"><?= $j['keterangan'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Departemen</label>
                            <select name="id_departmen" required class="block w-full px-4 py-3 border border-gray-300 rounded-2xl outline-none">
                                <option value="">- Pilih Dept -</option>
                                <?php while ($d = $list_dept->fetch_assoc()) : ?>
                                    <option value="<?= $d['id_departmen']; ?>"><?= $d['nama_departmen']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Sub Bagian</label>
                            <select name="id_sub_department" required class="block w-full px-4 py-3 border border-gray-300 rounded-2xl outline-none">
                                <option value="">- Pilih Sub -</option>
                                <?php while ($s = $list_sub->fetch_assoc()) : ?>
                                    <option value="<?= $s['id_sub_department']; ?>"><?= $s['nama_sub_department']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Penyedia OS/DHK</label>
                            <select name="id_os_dhk" class="select2-karyawan block w-full px-4 py-3 border border-gray-300 rounded-2xl outline-none">
                                <option value="">- Pilih OS/DHK -</option>
                                <?php while ($o = $list_os->fetch_assoc()) : ?>
                                    <option value="<?= $o['id_os_dhk']; ?>"><?= $o['OS_DHK']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Golongan</label>
                            <select name="id_golongan" class="select2-karyawan block w-full px-4 py-3 border border-gray-300 rounded-2xl outline-none">
                                <option value="">- Pilih Golongan -</option>
                                <?php while ($g = $list_gol->fetch_assoc()) : ?>
                                    <option value="<?= $g['id_golongan']; ?>"><?= $g['golongan']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-8 border-t border-gray-100">
                        <a href="?page=realisasi&aksi=kelola&id=<?= $idrealisasi; ?>" class="px-8 py-3 border border-gray-200 rounded-2xl text-gray-600 bg-gray-50 no-underline">Batal</a>
                        <button type="submit" name="simpan_karyawan" class="px-10 py-3 rounded-2xl text-white bg-blue-600 font-bold shadow-lg shadow-blue-200">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Mapping Upah Smart Defaults
        const nameToWage = {
            "HERI KRISTANTO": 0,
            "FEBRIANI CAHYANING PUTRI": 125000,
            "SUCI CAHYA SARI": 125000,
            "NURHANIFAH": 162692,
            "WENNY DELLA NAINGGOLAN": 162692,
            "WASPA PAYANGGA TAMA": 230769,
            "EDI PURWANTO": 226000,
            "ARNOLD STEVEN CHRISTOVER": 166000,
            "BERTI WILA RILISA": 125000,
            "TASHA ANATASIA": 161500,
            "DENA DWI FITRIANA": 142307,
            "STIVANY ANGELICA NAINGGOLAN": 161538,
            "JUWARI": 162692,
            "BUSHERI": 125000,
            "ROMLI": 162692,
            "JUNAEDI SP": 125000,
            "MASRULI": 130000,
            "JIMAN": 130000,
            "EKO SUPARMAN": 130000,
            "HALIMATUSYADIAH": 130000,
            "SAFIROH JULIANTI": 130000,
            "IPI HARWANTO": 0,
            "DARSIM": 162692,
            "AKBAR": 0,
            "AMBRANI": 140000,
            "ZAINUDIN": 0,
            "JERRY ARLAN SAPUTRA": 0,
            "ERYK ADAM PURWANTO": 0,
            "AGUS AINI": 200000,
            "FAHRUDIN": 140000,
            "SURYA": 140000,
            "ARMAN ASHARI": 140000,
            "EFAUDIN": 140000,
            "SUTRISNA": 140000,
            "DONI RISWANDI": 140000,
            "SABAN": 140000,
            "HERDIN DEVIANTO": 140000,
            "AHMAD SOPA": 80000,
            "RIAN": 80000,
            "ADI SETIAWAN": 80000,
            "MUHAMMAD KHAERUL ILMI": 80000,
            "FAIZAL LULHAK": 80000,
            "MAMUN JAWAWI": 162692,
            "DESINTA LARASATI": 125000,
            "MERRY YANA": 120000,
            "INDRA HALIM HARAHAP": 100000,
            "BADAWIYA": 125000,
            "ROBI MAULANA": 100000,
            "SANDRI": 125000,
            "DONI KHADAFI": 100000,
            "UDIN NAYUDIN": 100000,
            "SUPRATMAN": 100000,
            "MUHAMAD IDRUS SIHABUDIN": 100000,
            "AMIN ARIFIN": 153486,
            "DIDI WAHYUDI": 125000,
            "IMAM MARSUGI": 100000,
            "MUHAMAD YUSUF": 100000,
            "VITUS SUPARNO": 100000,
            "TABRONI": 100000,
            "ABDUL ROSID": 175000,
            "MOHAMAD DAYAT": 175000,
            "APRIL YADI": 125000,
            "MAMAN SURYAMAN": 100000,
            "ASEP HERMAWAN": 100000,
            "SUPYANI": 100000,
            "SUSI RATNA SARI": 125000,
            "MUSTOPA": 100000,
            "KHAERUL AMIN": 100000,
            "MUHAMAD ABDUL RAHMAN": 100000,
            "HERLI": 100000,
            "NAJALA RIDWAN DWI KURNIAWAN": 100000,
            "ANDRIYANSYAH": 100000,
            "MUHAMAD HARIS SAPUTRA": 100000,
            "RIVALDI": 100000,
            "IKHSAN": 100000,
            "AHMAD AWALUDIN": 161538,
            "MARDIANTO": 161538,
            "HENDRIK SAFRUDIN": 161538,
            "TAUFIN RAHMAT": 100000,
            "ABDUL ROHIM": 100000,
            "DEDEN HIDAYAT": 100000,
            "DENI PURWAHID": 100000,
            "KINTAN JELITA": 100000,
            "MUHAMAD SAHRONI": 100000,
            "JUMHANI": 180000,
            "JUBAEDI": 200000,
            "IRWAN SUSENO": 125000,
            "JAKA IRAWAN": 125000,
            "YULIAWATI": 100000,
            "SAHRONI": 125000,
            "AHMAD FAUZI": 130000,
            "APRIZON": 110000,
            "NUR APIS": 120000,
            "EKI ANDIKA": 135000,
            "AGIS": 120000,
            "GANI ASEPSO": 110000,
            "SYARBENI MULHAM HASSAN RANGKUTI": 100000,
            "WAWAN": 100000,
            "YUSRI": 100000,
            "ISROIL": 100000,
            "KOMAR": 100000,
            "DARMINAH": 125000,
            "SITI SOPIAH": 125000,
            "SAHRUL BAHRI": 100000,
            "MAHA RANI SHABITA": 100000,
            "SITI NUR FITRIANI": 100000,
            "MELIYA": 100000,
            "ECE": 100000,
            "MUHAMAD ALFISYAHRIL": 100000,
            "ABEL": 100000,
            "ALFRIZA KARNIA AMANSA": 100000,
            "LOLA": 100000,
            "ARIFIN": 125000,
            "TAMAMI": 125000,
            "IPIT PATMAWATI": 100000,
            "AI TITIN SUHARTINI": 100000,
            "SITI SUMIYATI": 100000,
            "FRISCA ENG RILIANI": 100000,
            "ALI IMRON": 100000,
            "ORMAN ES": 100000,
            "BISONO": 100000,
            "RAMIRAN": 100000,
            "RONI MARTA SATRIA": 100000,
            "EFAN FRAMTAMA": 0,
            "MISDI": 125000,
            "EDI SISWANTO": 125000,
            "MULYADI": 125000,
            "HAMDANI": 115000,
            "AGUS RAHARJO": 115000,
            "MAHMUDIN": 115000,
            "MAWI": 125000,
            "ANDRIAN": 125000,
            "SUGENG RIYADI": 125000,
            "MUHAMAD KHAERUL IKHSAN": 125000,
            "FAREL": 125000,
            "JUANDA": 115000,
            "ROHMAN ABDUROHMAN": 115000,
            "SADWAL": 125000,
            "HERMAWAN": 125000,
            "UMAEDI": 125000,
            "IBNU": 120000,
            "SODIKIN": 125000,
            "MIKAWI": 125000,
            "YAMIN": 100000,
            "AHMADI": 100000,
            "ANGGIANSYAH": 100000,
            "IWAN SETIAWAN": 100000,
            "AGUS SUWITO": 100000,
            "ANNISA ZAHIRA": 125000,
            "MUHAMAD AGAM MALIK": 125000,
            "HAFIYA SHERLI PUTRI": 125000,
            "DEDE ROSADI": 80000,
            "SANDI FERDIANSYAH": 80000,
            "YOGI ARITA": 100000,
            "SUSI SUSIANTI": 100000,
            "SAPUJI": 100000,
            "WIWIN KURNIATI": 100000,
            "FITRI YULIANI": 100000,
            "DEDEH": 100000,
            "ROHMAT": 100000,
            "MUHAMAD MADYANI": 100000,
            "MUHAMAD SANDAR": 100000,
            "AHMAD LOMRI": 140000,
            "SHADAM KHADAFI": 100000,
            "YULI KISMONO": 140000,
            "DEDE IRFANSYAH": 140000,
            "SOLEMAN": 140000,
            "EDI SUWANTORO": 0,
            "LOUT PARLAUNGAN NASUTION": 130000,
            "WISNU WARDANA": 211538,
            "ABDUL MUHIT": 212,
            "REYNALDI RAHARDIAN": 130000,
            "ARI NUGRAHA": 100000,
            "ALDI MAULANA": 100000,
            "M.RUSWANDI": 100000,
            "GUNAWAN": 100000,
            "GALIH SYALENDRA": 100000,
            "HENDAR SUHENDAR": 100000,
            "ADI FIRMASNYAH": 100000,
            "SUTISNA": 100000,
            "MUHAMAD SOPIAN FEBRIANI": 125000,
            "YULIYANA": 100000,
            "TEDI SYARFELA": 100000,
            "ARI YANTO": 100000,
            "MERRY MERCURY": 100000,
            "RYAN ALDRIANSYAH": 100000,
            "SUMARNO": 100000,
            "EM ILYAS TAMI": 100000,
            "KOMARUDIN": 100000,
            "NUR HODIJAH": 0,
            "DENI SAPUTRA": 150000,
            "EFRIAWAN": 100000,
            "SAEPUL FAHRI": 100000,
            "FITRI APRILLIANA DEWI": 130000,
            "MULYANA": 120000,
            "IHWANUDIN": 120000,
            "MUHAMAD JUN": 120000,
            "MULYA": 120000,
            "MUHDI": 120000,
            "YAYAT RUHIYAT": 110000,
            "AMIN SOBRI": 110000,
            "FIKRI ALIYUDIN": 110000,
            "IKHSAN ANUGRAH ILLAHI": 161500,
            "MARIUS GANDARDO": 125000,
            "SYEILA PUTRI UTAMI": 125000,
            "TAUFIK ROHMAN": 140000,
            "WANTO": 100000,
            "SARNA": 100000,
            "DEDE GUNAWAN": 100000,
            "TRY HEYSA PEBRIAWAN": 100000,
            "SEPTI AMELIA": 100000,
            "ALVIANA": 100000,
            "ALIYASIN": 100000,
            "SYAIDINA UMAR": 100000,
            "KOMET HENDRA": 100000,
            "EGI FIARUCI": 120000,
            "YOSPIN PRAMANA PUTRA": 110000,
            "AHMAD NURIL": 110000,
            "IRGI PURNAMA PUTRA": 120000,
            "RIZAL DANUARTA": 110000,
            "MAULANA FEBRIAN": 110000,
            "MAD ROBI": 120000,
            "ABDULAH": 120000,
            "ANNISA NURAINI": 100000,
            "DIDAH": 100000,
            "SUKRI WIJAYA": 100000,
            "RYAN AULIA": 100000,
            "RENDI": 125000,
            "RIO AHMAD": 100000,
            "AHMAT RASSOKI": 110000,
            "HERICCA UTAMA MULYA": 181923,
            "FADHLY PULUNGAN": 100000,
            "DICKY FAHROZI": 100000,
            "WISNU NUGRAHA": 100000,
            "PARIYADI": 100000,
            "RUBAI": 100000,
            "ALDIYANSAH": 100000,
            "MAMIT": 100000
        };

        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', {
                minimumFractionDigits: 0
            }).format(number);
        }

        $('#nama_karyawan_manual').on('input', function() {
            const name = $(this).val().toUpperCase().trim();
            if (nameToWage.hasOwnProperty(name)) {
                const wage = nameToWage[name];
                if (wage > 0) {
                    $('#upah_manual').val(formatRupiah(wage));
                }
            }
        });

        $('#upah_manual').on('input', function() {
            let val = $(this).val().replace(/[^0-9]/g, '');
            if (val !== "") {
                $(this).val(formatRupiah(parseInt(val)));
            }
        });

        $('.select2-karyawan').each(function() {
            $(this).select2({
                width: '100%',
                // dropdownAutoWidth: true, <-- BARIS INI DIHAPUS
                allowClear: true,
                placeholder: $(this).find('option:first').text(),
                containerCssClass: 'modern-select2'
            });
        });

    });
</script>

<style>
    /* 1. TAMPILAN SAAT TERTUTUP (INPUT CONTAINER) */
    .select2-container {
        width: 100% !important;
    }
    
    .select2-container--default .select2-selection--single {
        background-color: #ffffff;
        border: 1px solid #d1d5db !important; /* Sesuai border-gray-300 */
        border-radius: 1rem !important; /* Sesuai rounded-2xl */
        height: 50px !important; /* Menyesuaikan py-3 Tailwind */
        display: flex;
        align-items: center;
        outline: none;
        transition: all 0.2s ease-in-out;
    }

    /* Efek Cincin Biru saat di-klik (Focus) */
    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #3b82f6 !important; /* border-blue-500 */
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) !important; /* ring-blue-500 dengan opacity */
    }

    /* Teks yang Terpilih */
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #374151 !important; /* text-gray-700 */
        padding-left: 1rem !important; /* Sesuai px-4 */
        padding-right: 2.5rem !important;
        font-size: 0.875rem !important; /* text-sm */
        line-height: normal !important;
        width: 100%;
    }

    /* Posisi Panah Dropdown */
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100% !important;
        right: 0.75rem !important;
    }

    /* Tombol Clear (Tanda X) */
    .select2-container--default .select2-selection--single .select2-selection__clear {
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        margin-right: 1.5rem !important;
        color: #9ca3af !important;
        font-size: 1.25rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__clear:hover {
        color: #ef4444 !important; /* Merah saat dihover */
    }

    /* 2. TAMPILAN SAAT TERBUKA (DROPDOWN MENU MELAYANG) */
    .select2-dropdown {
        background-color: white;
        border: 1px solid #e5e7eb !important; /* border-gray-200 */
        border-radius: 1rem !important; /* Tetap rounded-2xl */
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important; /* shadow-xl */
        margin-top: 6px !important; /* Memberikan efek melayang (terpisah dari input) */
        overflow: hidden;
        z-index: 9999;
    }

    /* Kolom Pencarian (Search Box) di dalam Dropdown */
    .select2-search--dropdown {
        padding: 0.75rem !important;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid #d1d5db !important;
        border-radius: 0.5rem !important; /* rounded-lg */
        padding: 0.5rem 0.75rem !important;
        outline: none !important;
        font-size: 0.875rem !important;
        transition: border-color 0.2s;
    }
    .select2-search--dropdown .select2-search__field:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 1px #3b82f6 !important;
    }

    /* Desain List Opsi (Option) */
    .select2-results__option {
        padding: 0.75rem 1rem !important;
        font-size: 0.875rem !important;
        color: #4b5563 !important; /* text-gray-600 */
        transition: background-color 0.15s ease;
    }

    /* Opsi saat di-hover / disorot */
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #eff6ff !important; /* bg-blue-50 */
        color: #1d4ed8 !important; /* text-blue-700 */
        font-weight: 600;
    }

    /* Opsi yang sedang dipilih saat ini */
    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #f3f4f6 !important; /* bg-gray-100 */
        color: #111827 !important; /* text-gray-900 */
    }
</style>