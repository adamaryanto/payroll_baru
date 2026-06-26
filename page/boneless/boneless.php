<?php
$tgl_filter = $_GET['tgl'] ?? '';
$where_tgl = $tgl_filter ? " WHERE A.tgl = '".$koneksi->real_escape_string($tgl_filter)."' " : "";

$tampil = $koneksi->query("SELECT A.*, B.biaya_mobil,
    (SELECT SUM(total) FROM tb_boneless_detail WHERE id_boneless = A.id_boneless) as total_item 
    FROM tb_boneless A 
    LEFT JOIN tb_biayamobil B ON A.id_biayamobil = B.id_biayamobil
    $where_tgl
    ORDER BY tgl DESC");
$ref = $_GET['ref'] ?? '';
$view_param = isset($_GET['view']) ? '&view=1' : '';
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold m-0" style="color: #2563eb;"><i class="fas fa-truck-loading mr-2"></i>Data Boneless</h3>
            </div>
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto mt-2 md:mt-0">
                <a href="?page=<?= $ref ?>" class="inline-flex items-center bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 text-[14px] md:text-base font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto justify-center">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
                <?php if (!isset($_GET['view'])) : ?>
                    <a href="?page=boneless&aksi=tambah&ref=<?= $ref ?><?= $view_param ?>" class="flex md:inline-flex justify-center items-center bg-blue-600 hover:bg-blue-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                        <i class="fas fa-plus mr-1.5"></i> Tambah Data
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-4">
            <div class="table-responsive px-0 md:px-2">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-xs font-bold text-gray-700 uppercase">No</th>
                            <th class="py-3 px-4 text-xs font-bold text-gray-700 uppercase text-center">Tanggal</th>
                            <th class="py-3 px-4 text-xs font-bold text-gray-700 uppercase text-center">Jumlah Mobil</th>
                            <th class="py-3 px-4 text-xs font-bold text-gray-700 uppercase text-right">Total Biaya Boneless</th>
                            <th class="py-3 px-4 text-xs font-bold text-gray-700 uppercase">Keterangan</th>
                            <th class="py-3 px-4 text-xs font-bold text-gray-700 uppercase text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $no = 1;
                        while ($data = $tampil->fetch_assoc()) :
                            $harga_master = $data['biaya_mobil'] ?? 0;
                            $total_biaya_mobil = $data['jumlah_mobil'] * $harga_master;
                            $grand_total_all = $data['total_item'] + $total_biaya_mobil;
                            $biaya_per_mobil = ($data['jumlah_mobil'] > 0) ? ($grand_total_all / $data['jumlah_mobil']) : 0;
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td data-label="No" class="py-3 px-4 text-sm text-gray-700 font-medium md:font-normal"><?= $no++ ?></td>
                                <td data-label="Tanggal" class="py-3 px-4 text-sm text-gray-900 font-bold md:text-center"><?= date('d-m-Y', strtotime($data['tgl'])) ?></td>

                                <td data-label="Jumlah Mobil" class="py-3 px-4 text-sm text-gray-900 md:text-center font-bold">
                                    <span class="bg-amber-50 text-amber-700 px-3 py-1 rounded-full border border-amber-100">
                                        <?= $data['jumlah_mobil'] ?> Unit
                                    </span>
                                </td>

                                <td data-label="Total Biaya Boneless" class="py-3 px-4 text-sm font-bold text-blue-600 md:text-right">
                                    Rp <?= number_format($grand_total_all, 0, ',', '.') ?>
                                    <div class="text-[10px] text-gray-400 font-normal">
                                        (Item: Rp <?= number_format($data['total_item'], 0, ',', '.') ?> + Mobil: Rp <?= number_format($total_biaya_mobil, 0, ',', '.') ?>)
                                    </div>
                                </td>

                                <td data-label="Keterangan" class="py-3 px-4 text-sm text-gray-600 italic"><?= htmlspecialchars($data['keterangan']) ?: '-' ?></td>
                                <td data-label="Aksi" class="py-3 px-4 md:text-center mt-2 md:mt-0 border-t border-gray-100 md:border-t-0">
                                    <div class="flex items-center md:justify-center gap-2 action-btn-group">
                                        <a href="?page=boneless&aksi=ubah&id=<?= $data['id_boneless'] ?>&ref=<?= $ref ?><?= $view_param ?>" class="p-2 md:p-1 md:px-3 text-blue-600 bg-blue-50 border border-blue-100 rounded hover:bg-blue-600 hover:text-white transition-all text-xs font-bold flex justify-center items-center text-center">
                                            <i class="fas fa-edit md:mr-1"></i> <span class="ml-1 md:inline">Lihat / Edit</span>
                                        </a>
                                        <?php if (!isset($_GET['view'])) : ?>
                                            <a href="?page=boneless&aksi=hapus&id=<?= $data['id_boneless'] ?>" class="p-2 md:p-1 md:px-3 text-rose-600 bg-rose-50 border border-rose-100 rounded hover:bg-rose-600 hover:text-white transition-all text-xs font-bold flex justify-center items-center text-center" onclick="return confirm('Hapus data ini? Semua rincian item juga akan terhapus.')">
                                                <i class="fas fa-trash md:mr-1"></i> <span class="ml-1 md:inline">Hapus</span>
                                            </a>
                                        <?php endif; ?>
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
            flex-direction: row;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 5px;
            justify-content: flex-start;
        }

        .action-btn-group>a,
        .action-btn-group>div {
            flex: 0 0 auto;
        }

        .action-btn-group a {
            padding: 8px 12px !important;
            width: auto;
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

        // SweetAlert Delete Confirmation - Using delegation for DataTables compatibility
        $(document).on('click', '.btn-delete-boneless', function() {
            const id = $(this).data('id');
            const date = $(this).data('date');

            Swal.fire({
                title: 'Apakah anda yakin?',
                text: "Data boneless tanggal " + date + " akan dihapus permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?page=boneless&aksi=hapus&id=' + id + '&ref=<?= $ref ?><?= $view_param ?>';
                }
            });
        });

        // Dihapus style .css('float') bawaan agar tidak bentrok dengan flexbox pada mobile
        $('.dataTables_filter').addClass('mb-3');
        $('.dataTables_length').addClass('mb-3');
    });
</script>