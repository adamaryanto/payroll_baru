<?php
if (isset($_GET['ttgl1']) || isset($_GET['ttgl2'])) {
    $ttgl1 = $_GET['ttgl1'];
    $ttgl2 = $_GET['ttgl2'];

    $tampil = $koneksi->query("SELECT A.* , B.nama_karyawan , B.no_absen , B.jenis_kelamin FROM tb_cuti A LEFT JOIN ms_karyawan B on A.id_karyawan = B.id_karyawan where A.tgl_awal_cuti  between '$ttgl1' AND '$ttgl2' ");
} else {
    $ttgl1 = '';
    $ttgl2 = '';

    $tampil = $koneksi->query("SELECT A.* , B.nama_karyawan , B.no_absen , B.jenis_kelamin FROM tb_cuti A LEFT JOIN ms_karyawan B on A.id_karyawan = B.id_karyawan  ");
}
?>

<div class="row px-2 sm:px-3 mt-4">
    <div class="col-md-12">
        <div class="card rounded-2xl shadow-sm border-0 bg-white">
            <div class="bg-white border-b border-gray-200 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-xl font-bold text-blue-600 m-0"><i class="fas fa-plane-departure mr-2"></i>Data Karyawan Cuti</h3>
                    <p class="text-xs text-gray-500 mt-1">Kelola data cuti karyawan berdasarkan periode</p>
                </div>
                <div class="w-full md:w-auto">
                    <a href="?page=cuti&aksi=tambah" class="flex justify-center items-center w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow-sm font-bold text-sm transition-all">
                        <i class="fas fa-plus mr-2"></i> Tambah Data Cuti
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
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center">Lama Cuti</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider">Keterangan</th>
                                <th class="px-3 py-3 text-xs font-bold uppercase tracking-wider text-center" width="8%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $no = 1;
                            while ($data = $tampil->fetch_assoc()) {
                                // Hitung Lama Cuti
                                $tgl1 = strtotime($data['tgl_awal_cuti']);
                                $tgl2 = strtotime($data['tgl_akhir_cuti']);
                                $jarak = $tgl2 - $tgl1;
                                $hari = ($jarak / 60 / 60 / 24) + 1;
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td data-label="No" class="text-center text-sm text-gray-700 font-medium"><?php echo $no ?></td>
                                    <td data-label="No. Absen" class="text-sm font-bold text-gray-900"><?php echo $data['no_absen'] ?></td>
                                    <td data-label="Nama Karyawan" class="text-sm font-bold text-gray-900 uppercase"><?php echo $data['nama_karyawan'] ?></td>
                                    <td data-label="L/P" class="text-center text-sm text-gray-700"><?php echo $data['jenis_kelamin'] ?></td>
                                    <td data-label="Dari Tanggal" class="text-center text-sm font-medium text-gray-800"><?php echo date('d-m-Y', strtotime($data['tgl_awal_cuti'])); ?></td>
                                    <td data-label="Sampai Tanggal" class="text-center text-sm font-medium text-gray-800"><?php echo date('d-m-Y', strtotime($data['tgl_akhir_cuti'])); ?></td>
                                    <td data-label="Lama Cuti" class="text-center text-sm font-bold text-brand-600">
                                        <span class="px-2 py-1 bg-green-50 text-green-700 rounded-md border border-green-200"><?php echo $hari ?> Hari</span>
                                    </td>
                                    <td data-label="Keterangan" class="text-sm text-gray-700"><?php echo $data['keterangan_cuti'] ?></td>
                                    <td data-label="Aksi" class="text-center align-middle">
                                        <div class="flex flex-wrap gap-2 justify-end sm:justify-center mt-2 sm:mt-0">
                                            <a href="?page=cuti&aksi=hapus&id=<?php echo $data['id_cuti']; ?>" class="flex items-center justify-center px-3 py-1.5 text-[12px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-300 transition-all">
                                                <i class="fas fa-trash mr-1"></i> Batal
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
    /* Card Styling */
    .panel-primary {
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    /* Table Styling Default */
    .table thead th {
        background-color: #f8f9fa;
        color: #333;
        text-transform: uppercase;
        font-size: 13px;
        /* Diperbesar */
        letter-spacing: 0.5px;
        border-bottom: 2px solid #dee2e6 !important;
        vertical-align: middle;
    }

    .table tbody td {
        vertical-align: middle !important;
        font-size: 14px;
        /* Diperbesar */
    }

    .table-hover tbody tr:hover {
        background-color: rgba(95, 158, 160, 0.1) !important;
        transition: 0.3s;
    }

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

    /* =========================================
       KHUSUS TAMPILAN MOBILE DIPERBAIKI DI SINI
       ========================================= */
    @media screen and (max-width: 768px) {
        .table-responsive {
            padding: 12px !important;
        }

        #dataTables-example_wrapper .row:first-child {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 15px;
        }

        .dataTables_filter,
        .dataTables_length {
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

        .table-modern thead {
            display: none !important;
        }

        .table-modern tbody tr {
            display: block;
            margin-bottom: 1.5rem;
            /* Jarak antar kotak dilebarkan */
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            /* Jarak padding ke dalam kotak dilebarkan */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            background-color: #fff;
        }

        .table-modern tbody td {
            display: flex;
            flex-direction: column;
            /* Label di atas, data di bawah (stacking) */
            align-items: flex-start;
            padding: 10px 0 !important;
            /* Jarak atas-bawah per baris dilebarkan */
            text-align: left !important;
            border: none !important;
            border-bottom: 1px dashed #e2e8f0 !important;
        }

        .table-modern tbody td:first-child {
            padding-top: 0 !important;
        }

        .table-modern tbody td:last-child {
            border-bottom: none !important;
            padding-bottom: 0 !important;
        }

        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            /* Memberi jarak ke datanya */
            display: block;
            width: 100%;
        }

        /* Memperbesar Tombol Aksi di Mobile */
        .action-btn-group {
            width: 100%;
            display: flex;
            gap: 8px;
            padding-top: 5px;
        }

        .action-btn-group>a,
        .action-btn-group>div {
            flex: 1;
            /* Lebar tombol menyesuaikan merata */
        }

        .action-btn-group a {
            padding: 10px !important;
            /* Area klik jadi besar */
            width: 100%;
        }
    }
</style>

<script>
    $(document).ready(function() {
        $('#dataTables-example').DataTable({
            pageLength: 25,
            autoWidth: false,
            responsive: false,
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "Semua"]
            ],
            language: {
                search: "Cari Data:",
                searchPlaceholder: "Ketik pencarian...",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            }
        });

        $('.dataTables_filter').addClass('mb-3');
        $('.dataTables_length').addClass('mb-3');
    });
</script>

<?php
$ttgl1 = @$_POST['ttgl1'];
$ttgl2 = @$_POST['ttgl2'];
$simpan = @$_POST['simpan'];
$print = @$_POST['print'];
$excel = @$_POST['excel'];

if ($simpan) {
?><script type="text/javascript">
        window.location.href = "?page=cuti&ttgl1=<?php echo $ttgl1; ?>&ttgl2=<?php echo $ttgl2; ?>";
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