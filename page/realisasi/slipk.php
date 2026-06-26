<?php
// Query untuk mengambil semua karyawan aktif
$q_list = $koneksi->query("SELECT A.id_karyawan, A.no_absen, A.nama_karyawan, B.nama_departmen 
                           FROM ms_karyawan A 
                           LEFT JOIN ms_departmen B ON A.id_departmen = B.id_departmen 
                           WHERE A.status_karyawan = 'Aktif' 
                           ORDER BY A.nama_karyawan ASC");
?>

<div class="container-fluid px-3 md:px-6 mt-6 mb-10">
    <div class="bg-white shadow-xl rounded-2xl border border-gray-100 overflow-hidden">

        <div class="bg-gradient-to-r from-indigo-700 to-blue-600 px-6 py-6">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center text-white border border-white/30">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white m-0">Data Karyawan</h3>
                    <p class="text-blue-100 text-sm opacity-90">Pilih karyawan untuk melihat preview slip gaji</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="table-responsive">
                <table class="w-full text-left border-separate border-spacing-0" id="tableKaryawan">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">No. Absen</th>
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">Nama Karyawan</th>
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">Departemen</th>
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php while ($row = $q_list->fetch_assoc()): ?>
                            <tr class="hover:bg-blue-50/50 transition-colors">
                                <td data-label="No. Absen" class="py-4 px-4 text-sm font-semibold text-gray-700"><?= $row['no_absen'] ?></td>
                                <td data-label="Nama Karyawan" class="py-4 px-4 text-sm font-bold text-gray-900 truncate max-w-[200px] md:max-w-none">
                                    <?= $row['nama_karyawan'] ?>
                                </td>
                                <td data-label="Departemen" class="py-4 px-4">
                                    <span class="bg-blue-100 text-blue-700 text-[10px] px-2 py-1 rounded-md font-bold uppercase tracking-wider">
                                        <?= $row['nama_departmen'] ?>
                                    </span>
                                </td>
                                <td data-label="Aksi" class="py-4 px-4 text-center">
                                    <a href="index.php?page=realisasi&aksi=slip&id=<?= $row['id_karyawan'] ?>"
                                        class="inline-flex items-center justify-center w-full md:w-auto px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold rounded-xl shadow-md transition-all active:scale-95">
                                        <i class="fas fa-file-invoice-dollar mr-2"></i> Preview Slip
                                    </a>
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
    @media screen and (max-width: 768px) {

        /* Sembunyikan Header Asli */
        #tableKaryawan thead {
            display: none !important;
        }

        /* Buat Baris Menjadi Kartu */
        #tableKaryawan,
        #tableKaryawan tbody,
        #tableKaryawan tr,
        #tableKaryawan td {
            display: block !important;
            width: 100% !important;
        }

        #tableKaryawan tr {
            margin-bottom: 1rem;
            border: 1px solid #f1f5f9 !important;
            border-radius: 12px !important;
            padding: 10px !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            background: white;
        }

        #tableKaryawan td {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            text-align: right !important;
            border: none !important;
            border-bottom: 1px dashed #f1f5f9 !important;
            padding: 10px 5px !important;
            gap: 10px;
            font-size: 10px !important;
        }

        #tableKaryawan td:last-child {
            border-bottom: none !important;
            padding-top: 15px !important;
            justify-content: center !important;
            /* Tombol aksi jadi center di mobile */
        }

        /* Munculkan Label dari Atribut data-label */
        #tableKaryawan td:before {
            content: attr(data-label);
            float: left;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
            text-align: left;
        }

        /* Penyesuaian Input Search DataTables di Mobile */
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length {
            text-align: left !important;
            justify-content: flex-start !important;
            margin-bottom: 15px !important;
        }

        .dataTables_filter input {
            width: 100% !important;
            margin-left: 0 !important;
        }
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

    .table-modern {
        border-collapse: separate !important;
        border-spacing: 0 8px !important;
        background: transparent !important;
    }

    .table-modern thead th {
        background-color: #f8fafc !important;
        color: #64748b !important;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.05em;
        font-weight: 700;
        padding: 12px 15px !important;
        border: none !important;
    }

    .table-modern tbody tr {
        background-color: #ffffff !important;
        transition: transform 0.2s ease !important;
    }

    .table-modern tbody tr:hover {
        transform: scale(1.005);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
    }

    .table-modern td {
        padding: 15px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    /* Menghilangkan border di sisi kiri dan kanan agar terlihat clean */
    .table-modern td:first-child {
        border-left: 1px solid #f1f5f9 !important;
        border-top-left-radius: 12px;
        border-bottom-left-radius: 12px;
    }

    .table-modern td:last-child {
        border-right: 1px solid #f1f5f9 !important;
        border-top-right-radius: 12px;
        border-bottom-right-radius: 12px;
    }
</style>

<script>
    $(document).ready(function() {
        $('#tableKaryawan').DataTable({
                pageLength: 25,
                autoWidth: false,
                responsive: false,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, "Semua"]
                ],
                language: {
                    search: "Cari:",
                    searchPlaceholder: "Cari data...",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                    paginate: {
                        previous: "Prev",
                        next: "Next"
                    }
                }
            }),
            // Perbaiki gaya input DataTables agar matching
            $('.dataTables_filter input').addClass('w-full md:w-auto px-3 py-1.5 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500');
        $('.dataTables_filter label').addClass('w-full md:w-auto flex flex-col md:flex-row md:items-center gap-2');
    });
</script>