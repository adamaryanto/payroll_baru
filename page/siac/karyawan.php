<div class="container-fluid px-3 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-200 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center bg-white gap-4">
            <div>
                <h3 class="text-xl font-bold text-blue-600 m-0"><i class="fas fa-user-tie mr-2"></i>Data Karyawan (SIAC)</h3>
                <p class="text-xs text-gray-500 mt-1">Kelola data sakit, izin, alfa, dan cuti karyawan</p>
            </div>
        </div>

        <div class="p-0">
            <div class="table-responsive px-3 md:px-4 py-4">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-100 border-b border-gray-300">
                        <tr>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">No</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">No. Absen</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Nama Karyawan</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">L/P</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Alamat Tinggal</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Status</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php
                        $no = 1;
                        $tampil = $koneksi->query("SELECT * FROM ms_karyawan");
                        while ($datakaryawan = $tampil->fetch_assoc()) :
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td data-label="No" class="py-3 px-2 text-center text-base md:text-[15px] font-bold md:font-normal text-gray-700">
                                    <?= $no++ ?>
                                </td>

                                <td data-label="No. Absen" class="py-3 px-2 text-center text-base md:text-[15px] font-bold md:font-normal text-gray-900">
                                    <?= $datakaryawan['no_absen'] ?>
                                </td>

                                <td data-label="Nama Karyawan" class="py-3 px-2 text-base md:text-[14px] font-black text-gray-900 uppercase tracking-tight">
                                    <?= $datakaryawan['nama_karyawan'] ?>
                                </td>

                                <td data-label="L/P" class="py-3 px-2 text-center text-sm text-gray-700">
                                    <?= $datakaryawan['jenis_kelamin'] ?>
                                </td>

                                <td data-label="Alamat Tinggal" class="py-3 px-2 text-sm text-gray-600 truncate max-w-xs">
                                    <?= $datakaryawan['alamat_tinggal'] ?>
                                </td>

                                <td data-label="Status" class="py-3 px-2 text-center">
                                    <span class="px-2 py-1 inline-flex text-[10px] leading-5 font-bold rounded-md bg-gray-100 border border-gray-200 text-gray-600 uppercase text-center">
                                        <?= $datakaryawan['status_karyawan'] ?>
                                    </span>
                                </td>

                                <td data-label="Aksi" class="py-3 px-2 align-middle">
                                    <div class="flex flex-wrap gap-1.5 md:justify-center">
                                        <a href="?page=siac&aksi=sakit&id=<?= $datakaryawan['id_karyawan'] ?>"
                                            class="flex items-center px-3 py-2 text-[12px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-600 hover:text-white rounded border border-amber-300 transition-all">
                                            <i class="fas fa-user-injured mr-1"></i> Sakit
                                        </a>

                                        <a href="?page=siac&aksi=ijin&id=<?= $datakaryawan['id_karyawan'] ?>"
                                            class="flex items-center px-3 py-2 text-[12px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-300 transition-all">
                                            <i class="fas fa-clock mr-1"></i> Izin
                                        </a>

                                        <a href="?page=siac&aksi=alfa&id=<?= $datakaryawan['id_karyawan'] ?>"
                                            class="flex items-center px-3 py-2 text-[12px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-300 transition-all">
                                            <i class="fas fa-user-times mr-1"></i> Alfa
                                        </a>

                                        <a href="?page=siac&aksi=cuti&id=<?= $datakaryawan['id_karyawan'] ?>"
                                            class="flex items-center px-3 py-2 text-[12px] font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-600 hover:text-white rounded border border-indigo-300 transition-all">
                                            <i class="fas fa-plane-departure mr-1"></i> Cuti
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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