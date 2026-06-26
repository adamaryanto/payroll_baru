<?php
// Query utama
$where_real = "";
$tampil = $koneksi->query("SELECT A.*, 
    (SELECT COUNT(RD.id_realisasi_detail) 
     FROM tb_realisasi_detail RD 
     LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail 
     WHERE RD.id_realisasi = A.id_realisasi AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')
    ) as jml,
    (SELECT SUM(
        (IFNULL(RD.r_upah, 0) + IFNULL(RD.lembur, 0)) - 
        (IFNULL(RD.r_potongan_telat, 0) + 
         IFNULL(RD.r_potongan_istirahat_awal, 0) + 
         IFNULL(RD.r_potongan_istirahat_telat, 0) + 
         IFNULL(RD.r_potongan_pulang, 0) + 
         IFNULL(RD.r_potongan_tidak_lengkap, 0) + 
         IFNULL(RD.r_potongan_lainnya, 0))
    ) 
    FROM tb_realisasi_detail RD 
    LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail
    WHERE RD.id_realisasi = A.id_realisasi 
    AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')
    ) as ttl, 
    (SELECT SUM(IFNULL(RD.r_potongan_lainnya, 0)) 
     FROM tb_realisasi_detail RD 
     LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail 
     WHERE RD.id_realisasi = A.id_realisasi AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')
    ) as potlainnya,
    (SELECT SUM(IFNULL(RD.r_potongan_telat, 0)) FROM tb_realisasi_detail RD LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail WHERE RD.id_realisasi = A.id_realisasi AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')) as p_telat,
    (SELECT SUM(IFNULL(RD.r_potongan_istirahat_awal, 0) + IFNULL(RD.r_potongan_istirahat_telat, 0)) FROM tb_realisasi_detail RD LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail WHERE RD.id_realisasi = A.id_realisasi AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')) as p_istirahat,
    (SELECT SUM(IFNULL(RD.r_potongan_pulang, 0)) FROM tb_realisasi_detail RD LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail WHERE RD.id_realisasi = A.id_realisasi AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')) as p_pulang,
    (SELECT SUM(IFNULL(RD.r_potongan_tidak_lengkap, 0)) FROM tb_realisasi_detail RD LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail WHERE RD.id_realisasi = A.id_realisasi AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')) as p_log
    FROM tb_realisasi A $where_real ORDER BY A.tgl_realisasi ASC, A.id_realisasi ASC");

// Logika Akses: Owner dan Admin Master yang bisa Approve/Unapprove Realisasi
$is_authorized = (strtolower($_SESSION['role']) == "owner" || strtolower($_SESSION['role']) == "admin master");
$level_status = (!$is_authorized) ? "hidden" : "";

?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl text-blue-600 font-bold m-0"><i class="fas fa-file-invoice-dollar mr-2"></i>List Realisasi Upah</h3>
            </div>
            <div class="flex flex-wrap md:flex-nowrap gap-2 w-full md:w-auto">
                <a href="?page=boneless&ref=realisasi&view=1" class="flex-1 md:flex-none justify-center inline-flex items-center bg-amber-500 hover:bg-amber-600 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors">
                    <i class="fas fa-drumstick-bite mr-1.5"></i> Boneless
                </a>
                <a href="?page=realisasi&aksi=rkk" class="flex-1 md:flex-none justify-center inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors">
                    <i class="fas fa-plus mr-1.5"></i> Tambah Data
                </a>
                <a href="?page=realisasi&aksi=hapus_massal" class="flex-1 md:flex-none justify-center inline-flex items-center bg-rose-600 hover:bg-rose-700 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors">
                    <i class="fas fa-trash mr-1.5"></i> Hapus Masal
                </a>
            </div>
        </div>

        <div class="p-0">
            <div class="table-responsive px-3 py-3">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center w-8">No</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle">Tanggal</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle">Keterangan</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center">Jumlah Karyawan</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right">Total Upah</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Potongan Telat">Telat</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Potongan Istirahat">Istirahat</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Denda Pulang Awal">Denda Pulang</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Denda Absensi Tidak Lengkap">Denda Tidak Absen</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Potongan Lainnya">Lainnya</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center w-32">Aksi</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $no = 1;
                        while ($data = $tampil->fetch_assoc()) :
                            if ($data['status_realisasi'] >= 2) {
                                $row_class = "bg-slate-50/40";
                                $status_badge = '<span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 text-[13px] font-bold tracking-wide">ACC</span>';
                            } else {
                                $row_class = "hover:bg-gray-50 transition-colors";
                                $status_badge = '<span class="px-2 py-1 rounded bg-amber-100 text-amber-800 text-[13px] font-bold tracking-wide">PEND</span>';
                            }
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td data-label="No" class="md:text-center text-sm md:text-[15px] text-gray-700 font-medium align-middle"><?= $no ?></td>
                                <td data-label="Tanggal" class="py-2 md:py-2.5 px-2 text-[15px] font-medium text-gray-900 align-middle whitespace-nowrap"><?= date('d/m/Y', strtotime($data['tgl_realisasi'])) ?></td>
                                <td data-label="Keterangan" class="py-2 md:py-2.5 px-2 align-middle">
                                    <div class="text-[14px] text-gray-700 md:max-w-[150px] md:truncate" title="<?= htmlspecialchars($data['keterangan']) ?>">
                                        <?= htmlspecialchars($data['keterangan']) ?>
                                    </div>
                                </td>
                                <td data-label="Jumlah Karyawan" class="py-2 md:py-2.5 px-2 md:text-center text-[15px] text-gray-700 align-middle">
                                    <span class="md:hidden font-bold">Total: </span><?= $data['jml'] ?> <span class="md:hidden">Orang</span>
                                </td>

                                <td data-label="Total Upah" class="py-2 md:py-2.5 px-2 md:text-right text-[15px] font-bold text-gray-900 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['ttl'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Telat" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_telat'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Istirahat" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_istirahat'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Denda Pulang" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_pulang'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Denda Absen" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_log'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Lainnya" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['potlainnya'] ?? 0, 0, ',', '.') ?>
                                </td>

                                <td data-label="Aksi" class="py-2 md:py-2.5 px-2 align-middle md:text-center mt-2 md:mt-0 border-t border-gray-100 md:border-t-0">
                                    <div class="action-btn-group flex flex-wrap gap-2 md:justify-center lg:justify-center">
                                        <!-- Detail: Always visible -->
                                        <a href="?page=realisasi&aksi=kelola&id=<?= $data['id_realisasi']; ?>"
                                            class="px-2 py-1 text-[13px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-200 transition-colors flex justify-center items-center" title="Detail">
                                            <i class="fas fa-eye md:mr-1"></i> <span class="ml-1 md:inline">Detail</span>
                                        </a>

                                        <!-- Approve/Unapprove: Only for Owner -->
                                        <?php if ($is_authorized) : ?>
                                            <?php if ($data['status_realisasi'] < 2) : ?>
                                                <button type="button"
                                                    class="btn-action-realisasi px-2 py-1 text-[13px] font-bold text-emerald-600 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded border border-emerald-200 transition-colors flex justify-center items-center"
                                                    data-id="<?= $data['id_realisasi']; ?>"
                                                    data-action="accept"
                                                    data-text="Apakah Anda yakin ingin Approve data ini?"
                                                    title="Approve">
                                                    <i class="fas fa-check md:mr-1"></i> <span class="ml-1 md:inline">Approve</span>
                                                </button>
                                            <?php else : ?>
                                                <button type="button"
                                                    class="btn-action-realisasi px-2 py-1 text-[13px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-200 transition-colors flex justify-center items-center"
                                                    data-id="<?= $data['id_realisasi']; ?>"
                                                    data-action="unapprove"
                                                    data-text="Apakah Anda yakin ingin Unapprove data ini?"
                                                    title="Unapprove">
                                                    <i class="fas fa-undo md:mr-1"></i> <span class="ml-1 md:inline">Un-Approve</span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Excel: Owner/Admin Master always, Others only if approved -->
                                        <?php if (strtolower($_SESSION['role']) == "owner" || strtolower($_SESSION['role']) == "admin master" || $data['status_realisasi'] >= 2) : ?>
                                            <a href="page/realisasi/excelrealisasi.php?id=<?= $data['id_realisasi']; ?>"
                                                class="px-2 py-1 text-[13px] font-bold text-purple-600 bg-purple-50 hover:bg-purple-600 hover:text-white rounded border border-purple-200 transition-colors flex justify-center items-center" title="Excel Report">
                                                <i class="fas fa-file-excel md:mr-1"></i> <span class="ml-1 md:inline">Excel</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td data-label="Status" class="py-2 md:py-2.5 px-2 align-middle md:text-center">
                                    <?php if ($data['status_realisasi'] >= 2) : ?>
                                        <div class="stamp stamp-approved">Approved</div>
                                    <?php else : ?>
                                        <div class="stamp stamp-unapproved">Unapproved</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php $no++;
                        endwhile; ?>
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

    /* --- STYLING STEMPEL (STAMP) --- */
    .stamp {
        display: inline-block;
        padding: 5px 15px;
        border: 4px solid;
        border-radius: 10px;
        font-family: 'Courier New', Courier, monospace;
        font-weight: 900;
        text-transform: uppercase;
        font-size: 16px;
        transform: rotate(-15deg);
        opacity: 0.8;
        letter-spacing: 2px;
        user-select: none;
        margin: 10px;
    }

    .stamp-approved {
        color: #059669;
        border-color: #059669;
        box-shadow: 0 0 0 2px #059669;
    }

    .stamp-unapproved {
        color: #dc2626;
        border-color: #dc2626;
        box-shadow: 0 0 0 2px #dc2626;
    }

    @media screen and (max-width: 768px) {
        .stamp {
            transform: rotate(-12deg);
            /* Tetap miring sedikit di mobile */
            margin: 15px 0;
            font-size: 14px;
            padding: 4px 12px;
            display: inline-block;
            align-self: center;
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

        // SweetAlert Action Confirmation - Using delegation for better reliability with DataTables
        $(document).on('click', '.btn-action-realisasi', function() {
            const id = $(this).data('id');
            const action = $(this).data('action');
            const text = $(this).data('text');

            let confirmButtonColor = '#059669'; // Emerald for accept
            if (action === 'unapprove') confirmButtonColor = '#e11d48'; // Rose for unapprove

            Swal.fire({
                title: 'Konfirmasi',
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    if (action === 'accept') {
                        window.location.href = '?page=realisasi&aksi=accept&id=' + id;
                    } else {
                        window.location.href = '?page=realisasi&aksi=unapprove&id=' + id + '&iddetail=unapp';
                    }
                }
            });
        });

        // Dihapus style .css('float') bawaan agar tidak bentrok dengan flexbox pada mobile
        $('.dataTables_filter').addClass('mb-3');
        $('.dataTables_length').addClass('mb-3');
    });
</script>

<?php
// Script Redirect
$ttgl1 = $_POST['ttgl1'] ?? '';
$ttgl2 = $_POST['ttgl2'] ?? '';

if (isset($_POST['simpan'])) {
    echo '<script>window.location.href="?page=cuti&ttgl1=' . $ttgl1 . '&ttgl2=' . $ttgl2 . '";</script>';
}
if (isset($_POST['print'])) {
    echo '<script>window.location.href="laporanpendapatan.php?ttgl1=' . $ttgl1 . '&ttgl2=' . $ttgl2 . '";</script>';
}
if (isset($_POST['excel'])) {
    echo '<script>window.location.href="excelpendapatan.php?ttgl1=' . $ttgl1 . '&ttgl2=' . $ttgl2 . '";</script>';
}
?>