<?php
if (isset($_GET['ttgl1']) || isset($_GET['ttgl2'])) {
    $ttgl1 = $_GET['ttgl1'];
    $ttgl2 = $_GET['ttgl2'];

    // Perbaikan: tgl_awal_cuti diubah menjadi tgl_awal_alfa
    $tampil = $koneksi->query("SELECT A.* , B.nama_karyawan , B.no_absen , B.jenis_kelamin FROM tb_alfa A LEFT JOIN ms_karyawan B on A.id_karyawan = B.id_karyawan where A.tgl_awal_alfa between '$ttgl1' AND '$ttgl2' ");
} else {
    $ttgl1 = '';
    $ttgl2 = '';

    $tampil = $koneksi->query("SELECT A.* , B.nama_karyawan , B.no_absen , B.jenis_kelamin FROM tb_alfa A LEFT JOIN ms_karyawan B on A.id_karyawan = B.id_karyawan  ");
}
?>

<div class="row px-2 sm:px-3 mt-4">
    <div class="col-md-12">
        <div class="card rounded-2xl shadow-sm border-0 bg-white">
            <div class="bg-white border-b border-gray-200 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-xl font-bold text-blue-600 m-0"><i class="fas fa-user-times mr-2"></i>Data Karyawan Alfa</h3>
                    <p class="text-xs text-gray-500 mt-1">Kelola data alfa karyawan berdasarkan periode</p>
                </div>
                <div class="w-full md:w-auto">
                    <a href="?page=alfa&aksi=tambah" class="flex justify-center items-center w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow-sm font-bold text-sm transition-all">
                        <i class="fas fa-plus mr-2"></i> Tambah Data Alfa
                    </a>
                </div>
            </div>

            <div class="p-4 md:p-5">
                <form method="POST" class="bg-gray-50 rounded-xl p-4 md:p-6 mb-6 border border-gray-200 shadow-sm">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block font-bold text-gray-600 text-[11px] uppercase tracking-wider mb-1.5">Dari Tanggal</label>
                            <input autocomplete="off" type="date" name="ttgl1" required value="<?php echo $ttgl1; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all shadow-sm" />
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 text-[11px] uppercase tracking-wider mb-1.5">Sampai Tanggal</label>
                            <input autocomplete="off" type="date" name="ttgl2" value="<?php echo $ttgl2; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all shadow-sm" />
                        </div>
                        <div>
                            <button type="submit" name="simpan" value="Search" class="w-full border-0 bg-blue-600 hover:bg-blue-900 text-white py-2.5 rounded-lg shadow-md font-bold transition-all flex justify-center items-center">
                                <i class="fas fa-search mr-2"></i> Tampilkan Data
                            </button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                        <thead class="bg-gray-50 text-gray-600 text-sm">
                            <tr>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">No</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider">No. Absen</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider">Nama Karyawan</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">L/P</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">Dari Tanggal</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">Sampai Tanggal</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">Lama Alfa</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider">Keterangan</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center" width="8%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $no = 1;
                            while ($data = $tampil->fetch_assoc()) {
                                // Hitung Lama Alfa
                                $tgl1 = strtotime($data['tgl_awal_alfa']);
                                $tgl2 = strtotime($data['tgl_akhir_alfa']);
                                $jarak = $tgl2 - $tgl1;
                                $hari = ($jarak / 60 / 60 / 24) + 1;
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td data-label="No" class="text-center text-sm text-gray-700 font-medium"><?php echo $no ?></td>
                                    <td data-label="No. Absen" class="text-sm font-bold text-gray-900"><?php echo $data['no_absen'] ?></td>
                                    <td data-label="Nama Karyawan" class="text-sm font-bold text-gray-900 uppercase"><?php echo $data['nama_karyawan'] ?></td>
                                    <td data-label="L/P" class="text-center text-sm text-gray-700"><?php echo $data['jenis_kelamin'] ?></td>
                                    <td data-label="Dari Tanggal" class="text-center text-sm font-medium text-gray-800"><?php echo date('d-m-Y', strtotime($data['tgl_awal_alfa'])); ?></td>
                                    <td data-label="Sampai Tanggal" class="text-center text-sm font-medium text-gray-800"><?php echo date('d-m-Y', strtotime($data['tgl_akhir_alfa'])); ?></td>
                                    <td data-label="Lama Alfa" class="text-center text-sm font-bold">
                                        <span class="px-2 py-1 bg-rose-50 text-rose-700 rounded-md border border-rose-200"><?php echo $hari ?> Hari</span>
                                    </td>
                                    <td data-label="Keterangan" class="text-sm text-gray-700"><?php echo $data['keterangan_alfa'] ?></td>
                                    <td data-label="Aksi" class="text-center align-middle">
                                        <div class="flex flex-wrap gap-2 justify-end sm:justify-center mt-2 sm:mt-0">
                                            <a href="?page=alfa&aksi=hapus&id=<?php echo $data['id_alfa']; ?>" class="flex items-center justify-center px-3 py-1.5 text-[12px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-300 transition-all">
                                                <i class="fas fa-trash mr-1"></i>Batal
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php $no++;
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Styling Dasar Table DataTables */
    #dataTables-example {
        border-collapse: separate !important;
        border-spacing: 0 !important;
    }

    /* Form Filter & Length Menu DataTables Desktop */
    .dataTables_wrapper .dataTables_length select {
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        padding: 4px 8px;
        margin: 0 5px;
        font-size: 0.875rem;
    }

    .dataTables_wrapper .dataTables_filter input {
        border-radius: 6px !important;
        border: 1px solid #e5e7eb !important;
        padding: 6px 10px !important;
        outline: none;
        font-size: 0.875rem;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #4f46e5 !important;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    /* Pagination DataTables */
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 4px;
        font-size: 0.875rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border: 1px solid #e5e7eb !important;
        background: white !important;
        border-radius: 6px !important;
        padding: 4px 10px !important;
        color: #4b5563 !important;
        font-weight: 500 !important;
        cursor: pointer;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f3f4f6 !important;
        color: #111827 !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #4f46e5 !important;
        border-color: #4f46e5 !important;
        color: white !important;
    }

    .dataTables_wrapper .dataTables_info {
        padding-top: 1.2rem !important;
        margin-bottom: 1rem !important;
        font-size: 0.875rem;
        color: #6b7280;
        float: left;
    }

    /* RESPONSIVE TABLE "STACKED" VIEW MOBILE */
    @media screen and (max-width: 768px) {

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            text-align: left !important;
            float: none !important;
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 100% !important;
            margin-left: 0 !important;
            display: block;
            margin-top: 0.5rem;
        }

        .dataTables_wrapper .dataTables_info {
            float: none !important;
            text-align: center !important;
            margin-bottom: 0.5rem !important;
        }

        .dataTables_wrapper .dataTables_paginate {
            justify-content: center !important;
            flex-wrap: wrap;
            margin-top: 0.5rem !important;
        }

        /* Sembunyikan Header Table di Mobile */
        #dataTables-example thead {
            display: none;
        }

        /* Transform Table Row jadi "Card" */
        #dataTables-example tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            background: #fff;
        }

        /* Transform Table Cell jadi Block */
        #dataTables-example tbody td {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            text-align: right !important;
            padding: 0.5rem 0.25rem !important;
            border: none !important;
            border-bottom: 1px solid #f3f4f6 !important;
            width: 100% !important;
        }

        #dataTables-example tbody td:last-child {
            border-bottom: none !important;
        }

        /* Label dari data-label attribute */
        #dataTables-example tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            color: #6b7280;
            flex-shrink: 0;
            width: 45%;
            text-align: left;
            line-height: 1.5;
            padding-right: 0.5rem;
        }

        /* Konten isi */
        #dataTables-example tbody td>* {
            flex-grow: 1;
        }
    }
</style>

<script>
    $(document).ready(function() {
        $('#dataTables-example').DataTable({
            pageLength: 100,
            responsive: true,
            language: {
                search: "",
                searchPlaceholder: "Cari data alfa...",
                lengthMenu: "Tampilkan _MENU_",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            }
        });
        $('.dataTables_filter').addClass('mb-3');
    });
</script>

<?php
$ttgl1 = @$_POST['ttgl1'];
$ttgl2 = @$_POST['ttgl2'];
$simpan = @$_POST['simpan'];
$print = @$_POST['print'];
$excel = @$_POST['excel'];

// Perbaikan: Redirect diubah ke '?page=alfa'
if ($simpan) {
?><script type="text/javascript">
        window.location.href = "?page=alfa&ttgl1=<?php echo $ttgl1; ?>&ttgl2=<?php echo $ttgl2; ?>";
    </script><?php
            }
            if ($print) {
                ?><script type="text/javascript">
        window.location.href = "laporanpendapatan.php?ttgl1=<?php echo $ttgl1; ?>&ttgl2=<?php echo $ttgl2; ?>";
    </script><?php
            }
            if ($excel) {
                ?><script type="text/javascript">
        window.location.href = "excelpendapatan.php?ttgl1=<?php echo $ttgl1; ?>&ttgl2=<?php echo $ttgl2; ?>";
    </script><?php
            }
                ?>