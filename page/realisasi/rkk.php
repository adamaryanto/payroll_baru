<?php
// Logika Redirect Form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ttgl1 = $_POST['ttgl1'] ?? '';
    $ttgl2 = $_POST['ttgl2'] ?? '';

    if (isset($_POST['simpan'])) {
        echo "<script>window.location.href='?page=cuti&ttgl1=$ttgl1&ttgl2=$ttgl2';</script>";
        exit;
    } elseif (isset($_POST['print'])) {
        echo "<script>window.location.href='laporanpendapatan.php?ttgl1=$ttgl1&ttgl2=$ttgl2';</script>";
        exit;
    } elseif (isset($_POST['excel'])) {
        echo "<script>window.location.href='excelpendapatan.php?ttgl1=$ttgl1&ttgl2=$ttgl2';</script>";
        exit;
    }
}

// Query Data
$query = "SELECT A.*, 
            (SELECT COUNT(id_rkk_detail) FROM tb_rkk_detail WHERE id_rkk = A.id_rkk AND status_rkk != 'Digantikan') as jml, 
            (SELECT SUM(upah) FROM tb_rkk_detail WHERE id_rkk = A.id_rkk AND status_rkk != 'Digantikan') as ttl 
          FROM tb_rkk A 
          WHERE status_rkk = 2";
$tampil = $koneksi->query($query);
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-gray-800 m-0">Pilih Rencana Upah untuk Direalisasikan</h3>
                <p class="text-[13px] text-gray-500 mt-1 mb-0">Pilih data RKK yang sudah disetujui untuk membuat Realisasi Upah baru.</p>
            </div>
            <div class="w-full md:w-auto mt-2 md:mt-0">
                <a href="?page=realisasi" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
            </div>
        </div>
        
        <div class="p-0">
            <div class="table-responsive px-3 py-3">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle text-center w-10">No</th>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle">Tanggal</th>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle">Tgl Input</th>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle text-center">Jam Kerja</th>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle text-center">Jml Karyawan</th>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle text-right">Total Upah</th>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle">Keterangan</th>
                            <th class="py-2.5 px-3 text-[13px] font-bold text-gray-700 uppercase align-middle text-center w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $no = 1;
                        while ($data = $tampil->fetch_assoc()) :
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td data-label="No" class="py-3 px-3 md:text-center text-[15px] text-gray-700 font-medium align-middle"><?= $no++ ?></td>
                            <td data-label="Tanggal" class="py-3 px-3 text-[15px] font-medium text-gray-900 align-middle whitespace-nowrap"><?= htmlspecialchars($data['tgl_rkk']) ?></td>
                            <td data-label="Tgl Input" class="py-3 px-3 text-[15px] text-gray-700 align-middle whitespace-nowrap"><?= htmlspecialchars($data['detail_rkk']) ?></td>
                            <td data-label="Jam Kerja" class="py-3 px-3 md:text-center text-[15px] font-bold text-indigo-600 align-middle"><?= htmlspecialchars($data['jam_kerja']) ?></td>
                            <td data-label="Jml Karyawan" class="py-3 px-3 md:text-center align-middle">
                                <span class="px-2 py-1 rounded bg-blue-100 text-blue-800 text-[13px] font-bold"><?= $data['jml'] ?> Orang</span>
                            </td>
                            <td data-label="Total Upah" class="py-3 px-3 md:text-right text-[15px] font-bold text-gray-900 align-middle whitespace-nowrap">
                                Rp <?= number_format($data['ttl'] ?? 0, 0, ',', '.') ?>
                            </td>
                            <td data-label="Keterangan" class="py-3 px-3 align-middle">
                                <div class="text-[14px] text-gray-700 md:max-w-[200px] md:truncate" title="<?= htmlspecialchars($data['keterangan']) ?>">
                                    <?= htmlspecialchars($data['keterangan']) ?>
                                </div>
                            </td>
                            <td data-label="Aksi" class="py-3 px-3 md:text-center align-middle mt-2 md:mt-0 border-t border-gray-100 md:border-t-0">
                                <div class="action-btn-group">
                                    <button type="button" 
                                       class="btn-realize-rkk inline-flex items-center justify-center px-3 py-2 md:py-1.5 text-[13px] font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded shadow-sm transition-colors w-full md:w-auto"
                                       data-id="<?= $data['id_rkk']; ?>"
                                       data-jml="<?= $data['jml']; ?>"
                                       data-text="Buat Realisasi untuk data RKK ini?">
                                        Approve <i class="fas fa-check ml-1.5 text-[11px]"></i>
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

<style>
    /* CSS Bawaan Tampilan PC (Tidak Diubah) */
    #dataTables-example { width: 100% !important; border-collapse: collapse !important; }
    .dataTables_wrapper .dataTables_length select { border-radius: 4px; border: 1px solid #d1d5db; padding: 4px 8px; margin: 0 4px; outline: none; font-size: 14px; }
    .dataTables_wrapper .dataTables_filter input { border-radius: 4px !important; border: 1px solid #d1d5db !important; padding: 6px 10px !important; outline: none; font-size: 14px; transition: all 0.2s; }
    .dataTables_wrapper .dataTables_filter input:focus { border-color: #4f46e5 !important; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2); }
    .dataTables_wrapper .dataTables_paginate { padding-top: 1rem !important; display: flex; justify-content: flex-end; gap: 4px; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { background: white !important; border: 1px solid #d1d5db !important; border-radius: 4px !important; padding: 6px 12px !important; color: #374151 !important; font-size: 14px !important; cursor: pointer; }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.disabled) { background: #f3f4f6 !important; color: #111827 !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #4f46e5 !important; border-color: #4f46e5 !important; color: white !important; font-weight: bold; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; }
    .dataTables_wrapper .dataTables_info { padding-top: 1.1rem !important; font-size: 14px; color: #4b5563; }
    .dataTables_wrapper::after { content: ""; clear: both; display: table; }

    /* =========================================
       KHUSUS TAMPILAN MOBILE DIBUAT LEGA
       ========================================= */
    @media screen and (max-width: 768px) {
        .table-responsive { 
            border: none !important; 
            overflow-x: visible !important;
            padding: 0 10px !important; /* Tambah jarak samping sedikit di container */
        }
        
        /* Merapikan form cari dan pagination di HP */
        .dataTables_filter {
            float: none !important;
            display: flex;
            justify-content: flex-start;
            margin-bottom: 15px;
        }
        .dataTables_length {
            float: none !important;
            margin-bottom: 10px;
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
        .table-modern thead { display: none !important; }
        
        /* Box Data (Setiap Baris menjadi Kotak Terpisah) */
        .table-modern tbody tr {
            display: block;
            margin-bottom: 1.5rem; /* Jarak antar kotak */
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px; /* Jarak padding dalam kotak */
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }
        
        /* Baris Data di dalam Kotak */
        .table-modern tbody td {
            display: flex;
            flex-direction: column; /* Label di atas, data di bawah (stacking) */
            justify-content: flex-start;
            align-items: flex-start !important;
            text-align: left !important;
            padding: 12px 0 !important; /* Jarak atas-bawah per baris data */
            border: none !important;
            border-bottom: 1px dashed #e2e8f0 !important; /* Garis putus-putus antar data */
            width: 100% !important;
            font-size: 14px;
        }
        .table-modern tbody td:first-child {
            padding-top: 0 !important;
        }
        .table-modern tbody td:last-child { 
            border-bottom: none !important; 
            padding-bottom: 0 !important;
        }
        
        /* Label Judul Kolom */
        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            margin-bottom: 6px; /* Jarak antara label dan isi data */
            display: block;
            width: 100%;
        }

        /* Tombol Aksi Melebar Penuh */
        .action-btn-group {
            width: 100%;
            display: flex;
            padding-top: 5px;
        }
        .action-btn-group a {
            width: 100%; /* Tombol realisasi memenuhi kotak */
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
        ordering: true,
        language: {
            search: "",
            searchPlaceholder: "Cari data...",
            lengthMenu: "Tampilkan _MENU_",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_",
            paginate: { previous: "Prev", next: "Next" }
        }
    });

    // SweetAlert Realize Confirmation
    $(document).on('click', '.btn-realize-rkk', function() {
        const id = $(this).data('id');
        const text = $(this).data('text');
        const jml = $(this).data('jml');

        if (jml == 0) {
            Swal.fire({
                icon: 'error',
                title: 'Data Kosong',
                text: 'Rencana kerja ini tidak memiliki data karyawan. Silakan lengkapi data RKK terlebih dahulu!',
                confirmButtonColor: '#4f46e5'
            });
            return;
        }
        
        Swal.fire({
            title: 'Konfirmasi',
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5', // Indigo
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Buat Realisasi',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?page=realisasi&aksi=tambah&id=' + id;
            }
        });
    });

    $('.dataTables_filter').css('float', 'right').addClass('mb-3');
    $('.dataTables_length').css('float', 'left').addClass('mb-3');
});
</script>