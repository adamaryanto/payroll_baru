<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0">
                    <i class="fas fa-users mr-2"></i>Data Karyawan
                </h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Kelola informasi detail dan upah seluruh karyawan</p>
            </div>
            <div class="w-full md:w-auto mt-2 md:mt-0">
                <a href="?page=karyawan&aksi=tambah" class="flex md:inline-flex justify-center items-center bg-indigo-600 hover:bg-indigo-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-plus mr-1.5"></i> Tambah Karyawan
                </a>
            </div>
        </div>

        <div class="p-0">
            <div class="table-responsive px-3 py-3">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">No</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">No. Absen</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Nama Karyawan</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Gol</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Jenis Kelamin</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Kontak & Dokumen</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Status</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center" style="min-width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php
                        $no = 1;
                        $tampil = $koneksi->query("SELECT k.*, o.OS_DHK as label_os, g.golongan as label_gol 
                                                 FROM ms_karyawan k
                                                 LEFT JOIN ms_os_dhk o ON k.id_os_dhk = o.id_os_dhk
                                                 LEFT JOIN ms_golongan g ON k.id_golongan = g.id_golongan");
                        while ($datakaryawan = $tampil->fetch_assoc()) {
                            $status_class = ($datakaryawan['status_karyawan'] == 'Aktif') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                        ?>
                            <tr class="hover:bg-blue-50/50 transition-colors">
                                <td data-label="No" class="md:text-center text-sm text-gray-600 font-medium"><?= $no++ ?></td>
                                <td data-label="No. Absen" class="text-sm font-semibold text-gray-700"><?= $datakaryawan['no_absen'] ?></td>
                                <td data-label="Nama Karyawan">
                                    <div class="font-bold text-gray-900"><?= $datakaryawan['nama_karyawan'] ?></div>
                                    <div class="text-xs text-gray-400 italic"><?= $datakaryawan['label_os'] ?></div>
                                </td>
                                <td data-label="Gol" class="md:text-center text-sm font-bold text-indigo-600"><?= $datakaryawan['label_gol'] ?></td>
                                <td data-label="Jenis Kelamin" class="md:text-center text-sm text-gray-600"><?= $datakaryawan['jenis_kelamin'] ?></td>
                                <td data-label="Kontak & Dokumen" class="text-xs py-2">
                                    <div class="block mb-1">
                                        <span class="inline-block w-12 text-gray-400 font-medium">KTP:</span>
                                        <span class="text-gray-700 font-semibold"><?= $datakaryawan['no_ktp'] ?></span>
                                    </div>

                                    <div class="block">
                                        <span class="inline-block w-12 text-gray-400 font-medium">BPJS:</span>
                                        <span class="text-gray-700 font-semibold"><?= $datakaryawan['no_bpjs'] ?></span>
                                    </div>
                                </td>
                                <td data-label="Status" class="md:text-center">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                        <?= $datakaryawan['status_karyawan'] ?>
                                    </span>
                                </td>
                                <td data-label="Aksi" class="md:text-center">
                                    <div class="flex items-center md:justify-center gap-2 action-btn-group">
                                        <a href="?page=karyawan&aksi=view&id=<?= $datakaryawan['id_karyawan'] ?>" class="p-2 bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white rounded-lg transition-all flex justify-center items-center" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?page=karyawan&aksi=ubah&id=<?= $datakaryawan['id_karyawan'] ?>" class="p-2 bg-amber-50 text-amber-600 hover:bg-amber-600 hover:text-white rounded-lg transition-all flex justify-center items-center" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="text-rose-500 hover:text-rose-700 transition-colors btn-delete-karyawan bg-transparent border-0 p-1 flex justify-center items-center" 
                                                data-id="<?= $datakaryawan['id_karyawan'] ?>" 
                                                data-nama="<?= htmlspecialchars($datakaryawan['nama_karyawan']) ?>"
                                                title="Hapus">
                                            <i class="fas fa-trash-alt text-lg"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
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

    /* Custom Badges untuk Status */
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
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

    /* =========================================
       KHUSUS TAMPILAN MOBILE DIPERBAIKI DI SINI
       ========================================= */
    @media screen and (max-width: 768px) {
        .table-responsive {
            padding: 12px !important;
        }

        /* Merapikan form cari dan baris dataTables di HP */
        #dataTables-example_wrapper .row:first-child {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 15px;
        }
        .dataTables_filter, .dataTables_length {
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
            margin-bottom: 1.5rem; /* Jarak antar kotak dilebarkan */
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px; /* Jarak ke dalam kotak dilebarkan */
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .table-modern tbody td {
            display: flex;
            flex-direction: column; /* Label ada di atas data, BUKAN di samping */
            align-items: flex-start;
            padding: 12px 0 !important; /* Jarak atas-bawah per baris dilebarkan */
            border: none !important;
            border-bottom: 1px dashed #e2e8f0 !important;
            font-size: 14px; /* Huruf sedikit diperbesar */
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
            margin-bottom: 6px; /* Memberi jarak ke datanya */
            display: block;
            width: 100%;
        }

        /* Memperbesar Tombol Aksi di Mobile */
        .action-btn-group {
            width: 100%;
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        .action-btn-group a {
            flex: 1; /* Lebar tombol menyesuaikan merata */
            padding: 12px !important; /* Area klik jadi besar */
            font-size: 16px; /* Ikon diperbesar sedikit */
        }

        h3 {
            color: #2563eb !important;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                search: "Cari:",
                searchPlaceholder: "Cari data...",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            }
        });

        // SweetAlert Delete Confirmation - Using delegation for better reliability with DataTables
        $(document).on('click', '.btn-delete-karyawan', function() {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            
            Swal.fire({
                title: 'Apakah anda yakin?',
                text: "Data karyawan " + nama + " akan dihapus!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?page=karyawan&aksi=hapus&id=' + id;
                }
            });
        });
        
        $('.dataTables_filter').addClass('mb-3');
        $('.dataTables_length').addClass('mb-3');
    });
</script>