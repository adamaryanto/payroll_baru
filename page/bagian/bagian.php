<style>
    /* Card Styling */
    .panel-primary {
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .box-header {
        padding: 15px 20px !important;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* Table Styling */
    .table thead th {
        background-color: #f8f9fa;
        color: #333;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 1px;
        border-bottom: 2px solid #dee2e6 !important;
        vertical-align: middle;
    }

    .table tbody td {
        vertical-align: middle !important;
        font-size: 13px;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(95, 158, 160, 0.1) !important;
        transition: 0.3s;
    }

    /* Button Styling */
    .btn {
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
        transition: 0.2s;
    }

    .btn-info {
        background-color: #5bc0de;
        border: none;
    }

    .btn-info:hover {
        background-color: #31b0d5;
        transform: translateY(-1px);
    }

    .btn-warning {
        color: #fff !important;
    }

    /* Utility */
    .m-b-10 {
        margin-bottom: 10px;
    }

    .p-20 {
        padding: 20px;
    }

    /* 1. Reset wrapper agar tidak menggunakan float bawaan DataTables */
    .dataTables_wrapper {
        display: block !important;
    }

    /* 2. Memaksa area atas (Length & Filter) menjadi satu baris sejajar */
    .dataTables_wrapper::before,
    .dataTables_wrapper::after {
        display: none !important;
        /* Hapus clearfix bawaan yang mengganggu */
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

    h3 {
        color: #2563eb !important;
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

    @media screen and (max-width: 768px) {
        .table-responsive {
            padding: 12px !important;
        }

        .table-modern thead {
            display: none !important;
        }

        .table-modern tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
        }

        .table-modern tbody td {
            display: flex;
            align-items: flex-start;
            padding: 8px 10px !important;
            border: none !important;
            border-bottom: 1px solid #f3f4f6 !important;
        }

        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #4b5563;
            text-transform: uppercase;
            font-size: 11px;
            min-width: 120px;
            margin-right: 15px;
        }

        h3 {
            color: #2563eb !important;
        }
    }
</style>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-100 py-4 px-5 flex justify-between items-center bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0">
                    <i class="fas fa-users mr-2"></i>Data Bagian (Departemen)
                </h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Kelola data bagian karyawan</p>
            </div>
            <div>
                <a href="?page=bagian&aksi=tambah" class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors">
                    <i class="fas fa-plus mr-1.5"></i> Tambah Bagian
                </a>
            </div>
        </div>

        <div class="p-0">
            <div class="table-responsive px-3 py-3">
                <table class="w-full text-left border-collapse" id="dataTables-example">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-600 uppercase text-center" width="5%">No</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-600 uppercase">Nama Bagian</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-600 uppercase text-center" width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $no = 1;
                        $tampil = $koneksi->query("SELECT * FROM ms_departmen ORDER BY nama_departmen ASC");
                        while ($data = $tampil->fetch_assoc()) : ?>
                            <tr class="hover:bg-blue-50/50 transition-colors duration-200">
                                <td class="py-2 px-2 text-center text-sm text-gray-600 font-medium" data-label="No"><?php echo $no++; ?></td>
                                <td class="py-2 px-2 text-sm text-gray-700 font-semibold" data-label="Nama Bagian"><?php echo htmlspecialchars($data['nama_departmen']); ?></td>
                                <td data-label="Aksi" class="py-2 px-2 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="?page=bagian&aksi=ubah&id=<?php echo $data['id_departmen']; ?>" class="p-2 bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white rounded-lg transition-all" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="text-rose-500 hover:text-rose-700 transition-colors btn-delete bg-transparent border-0 p-1" 
                                                data-id="<?php echo $data['id_departmen']; ?>" 
                                                data-nama="<?php echo htmlspecialchars($data['nama_departmen']); ?>"
                                                title="Hapus">
                                            <i class="fas fa-trash-alt text-lg"></i>
                                        </button>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        $('#dataTables-example').DataTable({
            pageLength: 25,
            autoWidth: false,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
            language: {
                search: "Cari:",
                searchPlaceholder: "Cari departemen...",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                paginate: { previous: "Prev", next: "Next" }
            }
        });
        
        // Handle delete button click - Using delegation for DataTables compatibility
        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            
            Swal.fire({
                title: 'Apakah anda yakin?',
                text: "Data " + nama + " akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?page=bagian&aksi=hapus&id=' + id;
                }
            });
        });

        // Memastikan posisi elemen DataTables rapi
        $('.dataTables_filter').css('float', 'right').addClass('mb-3');
        $('.dataTables_length').css('float', 'left').addClass('mb-3');
    });
</script>