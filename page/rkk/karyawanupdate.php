<?php
if (isset($_GET['id'])) {
    $idrkkdetail = $_GET['id'];
    $tampildetail = $koneksi->query("select * from tb_rkk_detail where id_rkk_detail = '$idrkkdetail' ");
    $datadetail = $tampildetail->fetch_assoc();
    $idrkk = $datadetail['id_rkk'];
    $idrkkkaryawan = $datadetail['id_karyawan'];
    $orig_upah = $datadetail['upah'];
    $orig_jadwal = $datadetail['id_jadwal'];

    // Validasi: Gabisa ganti kalo status RKK >= 2
    $cek_rkk = $koneksi->query("SELECT status_rkk FROM tb_rkk WHERE id_rkk = '$idrkk'");
    $data_rkk = $cek_rkk->fetch_assoc();
    if ($data_rkk['status_rkk'] >= 2) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Akses Ditolak",
                    text: "Tidak bisa mengganti karyawan karena status RKK sudah Approved/Realized!",
                    confirmButtonColor: "#2563eb",
                    confirmButtonText: "Kembali"
                }).then((result) => {
                    window.location.href="?page=rkk&aksi=kelola&id=' . $idrkk . '";
                });
            </script>
        </body>
        </html>';
        exit;
    }
} else {
    $idrkkdetail = "";
    $idrkk = "";
    $idrkkkaryawan = "";
}
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-5 flex justify-between items-center bg-white">
            <div>
                <h3 class="text-xl font-bold text-blue-600 m-0"><i class="fas fa-sync-alt mr-2"></i>Pilih Karyawan Pengganti</h3>
                <small class="text-gray-500">Menggantikan karyawan pada RKK ID: <?= $idrkk; ?></small>
            </div>
            <div>
                <a href="?page=rkk&aksi=kelola&id=<?= $idrkk; ?>" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded transition-colors">
                    Kembali
                </a>
            </div>
        </div>

        <form method="POST">
            <div class="p-0">
                <div class="table-responsive px-4 py-4">
                    <table class="w-full text-left border-collapse" id="dataTables-example">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Pilih</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">No. Absen</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">Nama Karyawan</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">Departemen</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">Bagian</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">Status Saat Ini</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $no = 0;
                            $tampil = $koneksi->query("SELECT ms_karyawan.*, ms_departmen.nama_departmen, ms_sub_department.nama_sub_department 
                                FROM ms_karyawan 
                                LEFT JOIN ms_departmen ON ms_karyawan.id_departmen = ms_departmen.id_departmen 
                                LEFT JOIN ms_sub_department ON ms_karyawan.id_sub_department = ms_sub_department.id_sub_department
                                WHERE ms_karyawan.status_karyawan = 'Aktif' 
                                ORDER BY ms_karyawan.nama_karyawan ASC");

                            while ($datakaryawan = $tampil->fetch_assoc()) {
                                $id_k = $datakaryawan['id_karyawan'];
                                // Cek apakah sudah ada di RKK ini
                                $cek_rkk = $koneksi->query("SELECT status_rkk FROM tb_rkk_detail WHERE id_rkk = '$idrkk' AND id_karyawan = '$id_k' LIMIT 1");
                                $rkk_status = $cek_rkk->num_rows > 0 ? $cek_rkk->fetch_assoc()['status_rkk'] : 'Tersedia';

                                $is_disabled = ($rkk_status != 'Tersedia');
                            ?>
                                <tr class="<?= $is_disabled ? 'bg-gray-50 opacity-60' : 'hover:bg-gray-50' ?> transition-colors text-[14px]">
                                    <td class="py-2 px-2 text-center">
                                        <?php if (!$is_disabled): ?>
                                            <input type="radio" name="id_pengganti" value="<?= $id_k; ?>" class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-gray-300" required>
                                        <?php else: ?>
                                            <i class="fas fa-lock text-gray-400"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-2"><?= $datakaryawan['no_absen'] ?></td>
                                    <td class="py-2 px-2 font-medium text-gray-900"><?= $datakaryawan['nama_karyawan'] ?></td>
                                    <td class="py-2 px-2"><?= $datakaryawan['nama_departmen'] ?></td>
                                    <td class="py-2 px-2"><?= $datakaryawan['nama_sub_department'] ?></td>
                                    <td class="py-2 px-2">
                                        <?php if ($rkk_status == 'Tersedia'): ?>
                                            <span class="bg-emerald-100 text-emerald-800 text-[11px] font-bold px-2 py-0.5 rounded-full">Tersedia</span>
                                        <?php else: ?>
                                            <span class="bg-gray-200 text-gray-600 text-[11px] font-bold px-2 py-0.5 rounded-full"><?= $rkk_status ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php $no++;
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                <input type="submit" name="simpan" value="Konfirmasi Penggantian" class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-bold rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 cursor-pointer">
            </div>
        </form>
    </div>
</div>
<style>
    /* 1. Reset wrapper agar tidak menggunakan float bawaan DataTables */
    .dataTables_wrapper {
        display: block !important;
    }

    /* 2. Memaksa area atas (Length & Filter) menjadi satu baris sejajar */
    .dataTables_wrapper::before,
    .dataTables_wrapper::after {
        display: none !important;
    }

    /* 3. Membuat container fleksibel untuk Length (kiri) dan Filter (kanan) */
    #dataTables-example_wrapper .row:first-child {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-bottom: 20px !important;
        width: 100% !important;
    }

    /* 4. Styling Tampil _MENU_ (Kiri) */
    .dataTables_length {
        display: flex !important;
        align-items: center !important;
    }

    .dataTables_length label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_length select {
        padding: 5px 10px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
    }

    /* 5. Styling Cari: (Kanan) */
    .dataTables_filter {
        text-align: right !important;
        display: flex !important;
        justify-content: flex-end !important;
    }

    .dataTables_filter label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_filter input {
        padding: 6px 12px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
        width: 200px !important;
    }

    /* --- STYLING PAGINATE (PREV/NEXT) --- */
    .dataTables_wrapper .dataTables_paginate {
        display: flex !important;
        justify-content: flex-end !important;
        align-items: center !important;
        gap: 4px !important;
        padding-top: 15px !important;
    }

    .dataTables_paginate .paginate_button {
        border: 1px solid #e2e8f0 !important;
        background: white !important;
        border-radius: 6px !important;
        padding: 5px 12px !important;
        color: #475569 !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #f8fafc !important;
        color: #2563eb !important;
        border-color: #cbd5e1 !important;
    }

    .dataTables_paginate .paginate_button.current {
        background: #2563eb !important;
        border-color: #2563eb !important;
        color: white !important;
    }

    .dataTables_paginate .paginate_button.disabled {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
        cursor: not-allowed !important;
    }

    /* --- STYLING INFO --- */
    .dataTables_wrapper .dataTables_info {
        padding-top: 20px !important;
        color: #64748b !important;
        font-size: 13px !important;
    }

    .card-clean {
        background: #fff;
        border: 1px solid #E0E4E8;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.03);
        margin-bottom: 20px;
        overflow: hidden;
    }

    .card-header-clean {
        background-color: #2C3E50;
        color: #ffffff;
        padding: 12px 15px;
        font-weight: 600;
        font-size: 15px;
    }

    .section-title {
        color: #34495E;
        border-left: 4px solid #3498DB;
        padding-left: 8px;
        font-weight: bold;
        margin-bottom: 10px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group {
        margin-bottom: 10px;
    }

    .form-control-clean {
        border-radius: 4px;
        border: 1px solid #CCD1D1;
        padding: 6px 10px;
        background-color: #FAFAFA;
        width: 100%;
        font-size: 13px;
    }

    .form-control-clean:focus {
        border-color: #3498DB;
        box-shadow: none;
        background-color: #FFFFFF;
    }

    .total-box {
        background-color: #EAF2F8;
        border: 1px solid #AED6F1;
        color: #1A5276;
        height: 45px;
        font-size: 18px;
        font-weight: bold;
        text-align: right;
        border-radius: 6px;
        padding-right: 15px;
    }

    /* Styling Dasar Table */
    #dataTables-example {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        width: 100%;
    }

    #dataTables-example thead th {
        padding: 8px 4px !important;
        font-size: 11px !important;
    }

    .table-clean tbody td {
        vertical-align: middle;
        font-size: 11px;
        color: #000;
        font-weight: bold;
        border-top: 1px solid #E0E4E8;
        padding: 6px 4px !important;
    }

    .bg-red-custom {
        background-color: #e74c3c !important;
        color: #fff !important;
    }

    .bg-yellow-custom {
        background-color: #f1c40f !important;
        color: #000 !important;
    }

    /* Form Filter & Length Menu DataTables */
    .dataTables_wrapper .dataTables_length select {
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        padding: 2px 6px;
        margin: 0 5px;
    }

    .dataTables_wrapper .dataTables_filter input {
        border-radius: 6px !important;
        border: 1px solid #e5e7eb !important;
        padding: 6px 10px !important;
        outline: none;
        transition: all 0.2s;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #3498DB !important;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    /* PAGINATION STYLING DataTables */
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 2px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border: 1px solid #e5e7eb !important;
        background: white !important;
        border-radius: 6px !important;
        padding: 4px 10px !important;
        color: #4b5563 !important;
        font-weight: 500 !important;
        font-size: 12px;
        cursor: pointer;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #3498DB !important;
        border-color: #3498DB !important;
        color: white !important;
    }

    /* =========================================
       RESPONSIVE TABLE FIT SCREEN (Mobile View) 
       ========================================= */
    @media screen and (max-width: 768px) {
        .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .col-md-12 {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .card-clean {
            border-radius: 0 !important;
            border-left: none !important;
            border-right: none !important;
            margin-bottom: 0 !important;
        }

        .table-responsive {
            border: none !important;
            overflow-x: visible !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        #dataTables-example_wrapper {
            padding: 0 10px;
        }

        #dataTables-example_wrapper .row:first-child {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 15px;
            margin-bottom: 15px !important;
            width: 100% !important;
        }

        .dataTables_filter,
        .dataTables_length {
            display: flex !important;
            width: 100% !important;
            justify-content: flex-start !important;
        }

        .dataTables_filter input {
            width: 100% !important;
            max-width: 100% !important;
        }

        .dataTables_paginate {
            justify-content: center !important;
            flex-wrap: wrap;
        }

        #dataTables-example {
            width: 100% !important;
            margin: 0 !important;
        }

        /* --- STYLING MODERN CARD KARYAWAN --- */
        .table-modern tbody tr {
            display: block;
            margin: 0 5px 20px 5px;
            /* Jarak antar card */
            border: 1px solid #cbd5e1 !important;
            /* Border card */
            border-radius: 12px !important;
            /* Sudut melengkung halus */
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            /* Shadow elegan */
            overflow: hidden;
            padding: 0;
        }

        .table-modern thead {
            display: none !important;
        }

        .table-modern tbody td {
            display: flex;
            flex-direction: column;
            align-items: flex-start !important;
            text-align: left !important;
            /* Memaksa rata kiri */
            padding: 12px 16px !important;
            border: none !important;
            border-bottom: 1px solid #f1f5f9 !important;
            /* Garis tipis antar baris di dalam card */
            width: 100% !important;
            font-size: 14px;
            white-space: normal !important;
        }

        /* OVERRIDE CLASS TEXT-RIGHT AGAR TIDAK NUMPUK DI KANAN PADA MOBILE */
        .table-modern tbody td.text-right,
        .table-modern tbody td.text-center {
            text-align: left !important;
            align-items: flex-start !important;
        }

        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
            width: 100%;
        }

        .table-modern tbody td:last-child {
            border-bottom: none !important;
            background-color: #f8fafc;
            padding-top: 16px !important;
            padding-bottom: 16px !important;
        }

        .flex-action {
            display: flex;
            justify-content: flex-start;
            width: 100%;
            margin-top: 0;
        }

        .flex-action a {
            width: 100%;
            text-align: center;
            padding: 12px !important;
            font-size: 15px !important;
            border-radius: 8px;
        }

        .card-header-clean {
            font-size: 16px;
            padding: 15px;
        }

        .section-title {
            font-size: 15px;
            margin-top: 15px;
            padding-left: 15px;
        }

        .panel-body {
            padding: 15px 0 !important;
        }

        .panel-body .row,
        .panel-body .form-group {
            padding: 0 15px;
        }

        .panel-body hr {
            margin: 15px !important;
        }

        .form-control-clean {
            font-size: 15px;
            padding: 10px;
        }

        .form-group label {
            font-size: 14px;
        }

        .total-box {
            font-size: 16px;
            height: 45px;
            text-align: left;
            padding-left: 10px;
            margin-bottom: 15px;
        }

        .header-btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        .header-btn-group button,
        .header-btn-group a {
            width: 100%;
            text-align: center;
            padding: 12px !important;
            font-size: 15px;
        }
    }
</style>
<script>
    $(document).ready(function() {
        $('#dataTables-example').DataTable({
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "Semua"]
            ],
            language: {
                search: "Cari Karyawan:",
                lengthMenu: "Tampilkan _MENU_",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            }
        });
    });
</script>

<?php
if (isset($_POST['simpan'])) {
    // Cek apakah radio button 'id_pengganti' sudah dipilih
    if (isset($_POST['id_pengganti'])) {
        $idkaryawan_pengganti = $_POST['id_pengganti'];

        // Ambil data departemen & sub-dept karyawan pengganti langsung dari DB agar aman
        $q_karyawan = $koneksi->query("SELECT id_departmen, id_sub_department FROM ms_karyawan WHERE id_karyawan = '$idkaryawan_pengganti'");
        $data_k = $q_karyawan->fetch_assoc();

        $iddept_pengganti = $data_k['id_departmen'];
        $idsub_pengganti  = $data_k['id_sub_department'];

        // 1. Masukkan karyawan pengganti ke tb_rkk_detail
        $q_insert = "INSERT INTO tb_rkk_detail 
            (id_rkk, id_karyawan, upah, id_departmen, id_sub_department, id_jadwal, status_rkk, 
             potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) 
            VALUES 
            ('$idrkk', '$idkaryawan_pengganti', '$orig_upah', '$iddept_pengganti', '$idsub_pengganti', '$orig_jadwal', 'Pengganti', 
             '0', '0', '0', NOW())";

        if ($koneksi->query($q_insert)) {
            // 2. Update status karyawan lama
            $koneksi->query("UPDATE tb_rkk_detail SET status_rkk = 'Digantikan', upah = 0 WHERE id_rkk_detail = '$idrkkdetail'");

            // 3. Catat di tabel histori update
            $koneksi->query("INSERT INTO tb_rkk_update (id_rkk_detail, id_karyawan, status) VALUES ('$idrkkdetail', '$idkaryawan_pengganti', 'Pengganti')");
            $koneksi->query("INSERT INTO tb_rkk_update (id_rkk_detail, id_karyawan, status) VALUES ('$idrkkdetail', '$idrkkkaryawan', 'Digantikan')");

            echo "<script>alert('Berhasil mengganti karyawan.'); window.location.href='?page=rkk&aksi=kelola&id=$idrkk';</script>";
        } else {
            echo "<script>alert('Gagal menyimpan: " . $koneksi->error . "');</script>";
        }
    } else {
        echo "<script>alert('Silakan pilih satu karyawan pengganti!');</script>";
    }
}
?>