<?php
$id = $_GET['id'] ?? '';
$ttgl1 = date("Y-m-d");

// Ambil ID Realisasi dari detail terakhir untuk navigasi kembali
$q_realisasi = $koneksi->query("SELECT id_realisasi FROM tb_realisasi_detail WHERE id_karyawan = '$id' ORDER BY id_realisasi_detail DESC LIMIT 1");
$d_realisasi = $q_realisasi->fetch_assoc();
$idRealisasi = $d_realisasi ? $d_realisasi['id_realisasi'] : 0;
// Ambil data karyawan & departemen untuk header
$q_karyawan_info = $koneksi->query("SELECT A.nama_karyawan, B.nama_departmen 
                                    FROM ms_karyawan A 
                                    LEFT JOIN ms_departmen B ON A.id_departmen = B.id_departmen 
                                    WHERE A.id_karyawan = '$id'");
$d_karyawan_info = $q_karyawan_info->fetch_assoc();
$namaKaryawan = $d_karyawan_info['nama_karyawan'] ?? 'Karyawan Tidak Ditemukan';
$deptKaryawan = $d_karyawan_info['nama_departmen'] ?? '-';

if (!function_exists('rupiah')) {
    function rupiah($angka)
    {
        return "Rp " . number_format($angka, 0, ',', '.');
    }
}
?>

<div class="container-fluid px-3 md:px-6 mt-6 mb-10">
    <div class="bg-white shadow-xl rounded-2xl border border-gray-100 overflow-hidden">

        <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 px-6 md:px-8 py-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center text-white border border-white/30 shadow-inner">
                        <i class="fas fa-file-invoice-dollar text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl md:text-2xl font-bold text-white m-0 tracking-tight">
                            Preview Slip Gaji
                        </h3>
                        <p class="text-blue-100 text-sm font-medium opacity-90 mt-1">
                            <?= $namaKaryawan ?> •
                            <span class="bg-white/20 px-2 py-0.5 rounded text-[11px] uppercase tracking-wider">
                                <?= $deptKaryawan ?>
                            </span>
                        </p>
                    </div>
                </div>
                <a href="index.php?page=realisasi&aksi=slipk"
                    class="w-40 h-10 bg-white/10 hover:bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center text-white border border-white/20 transition-all active:scale-90"
                    title="Kembali">
                    <i class="fas fa-arrow-left mr-2"></i>Kembali
                </a>
            </div>
        </div>
        <div class="p-4 md:p-8">
            <form method="GET" class="bg-blue-50/50 border border-blue-100 p-4 md:p-5 rounded-2xl mb-8">
                <input type="hidden" name="page" value="realisasi">
                <input type="hidden" name="aksi" value="slip">
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="flex flex-col md:flex-row items-center gap-4">

                    <div class="w-full md:flex-1 flex items-center bg-white px-4 h-[40px] rounded-xl border border-blue-100 shadow-sm">
                        <i class="fas fa-user-circle text-blue-500 mr-3 text-lg"></i>
                        <div class="truncate">
                            <span class="text-sm truncate font-bold text-gray-700 leading-tight"><?= $namaKaryawan ?></span>
                        </div>
                    </div>

                    <div class="w-full md:w-40">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 pointer-events-none text-xs">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <input type="date" name="ttgl1" required value="<?= $_GET['ttgl1'] ?? $ttgl1 ?>"
                                class="custom-input pl-9" title="Dari Tanggal">
                        </div>
                    </div>

                    <div class="w-full md:w-40">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 pointer-events-none text-xs">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <input type="date" name="ttgl2" required value="<?= $_GET['ttgl2'] ?? $ttgl1 ?>"
                                class="custom-input pl-9" title="Sampai Tanggal">
                        </div>
                    </div>

                    <div class="w-full md:w-auto">
                        <button type="submit" name="cari" value="Search"
                            class="h-[40px] px-6 border-0 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-md transition-all flex items-center justify-center space-x-2 w-full active:scale-95">
                            <i class="fas fa-search"></i>
                            <span>Cari</span>
                        </button>
                    </div>
                </div>
            </form>

            <?php
            if (isset($_GET['cari']) && $id) {
                $ttgl11 = $_GET['ttgl1'];
                $ttgl22 = $_GET['ttgl2'];

                // Ambil denda global
                $q_denda = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
                $d_denda = $q_denda->fetch_assoc();
                $globalDendaMasuk = $d_denda['denda_masuk'] ?? 0;
                $globalDendaIstirahatKeluar = $d_denda['denda_istirahat_keluar'] ?? 0;
                $globalDendaIstirahatMasuk = $d_denda['denda_istirahat_masuk'] ?? 0;
                $globalDendaPulang = $d_denda['denda_pulang'] ?? 0;
                $globalDendaTidakLengkap = $d_denda['denda_tidak_lengkap'] ?? 0;

                $sql = "SELECT r.*, j.jam_masuk, j.jam_keluar, j.istirahat_masuk, j.istirahat_keluar, rd.status_rkk
                        FROM tb_realisasi_detail r
                        LEFT JOIN tb_jadwal j ON r.id_jadwal = j.id_jadwal
                        LEFT JOIN tb_rkk_detail rd ON r.id_rkk_detail = rd.id_rkk_detail
                        WHERE r.id_karyawan = '$id'
                        AND r.tgl_realisasi_detail BETWEEN '$ttgl11' AND '$ttgl22'
                        ORDER BY r.tgl_realisasi_detail ASC";
                $result = $koneksi->query($sql);
            ?>

                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6 pt-4 border-t border-gray-100">
                    <div>
                        <h4 class="text-lg font-bold text-gray-800 m-0">Hasil Pencarian</h4>
                        <p class="text-gray-500 text-xs mt-1">Menampilkan data slip gaji periode <?= date('d/m/Y', strtotime($ttgl11)) ?> - <?= date('d/m/Y', strtotime($ttgl22)) ?></p>
                    </div>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="w-full md:w-auto">
                            <a target="_blank" href="slip.php?id=<?= $id ?>&ttgl1=<?= $ttgl11 ?>&ttgl2=<?= $ttgl22 ?>"
                                class="w-full md:w-auto flex justify-center items-center px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all active:scale-95">
                                <i class="fas fa-file-excel mr-2 text-base"></i> Download Excel
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="w-full text-left border-collapse table-modern" id="slipTable">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Tanggal</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Upah Pokok</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Masuk/Pulang</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Pot. Telat</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Pot. Istirahat</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Pot. Pulang</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Pot. Tidak Absen</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Pot. Lain</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest">Lembur</th>
                                <th class="py-4 px-3 text-[11px] font-bold text-gray-500 uppercase tracking-widest text-right">Total Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    // Logika Pelanggaran Dinamis (Sync with realisasi/kelola.php)
                                    $isLate = (!empty($row['ra_masuk']) && $row['ra_masuk'] != '00:00:00' && !empty($row['jam_masuk']) && $row['jam_masuk'] != '00:00:00' && strtotime($row['ra_masuk']) > strtotime($row['jam_masuk']));
                                    $isLateBreak = (!empty($row['ra_istirahat_masuk']) && $row['ra_istirahat_masuk'] != '00:00:00' && !empty($row['istirahat_masuk']) && $row['istirahat_masuk'] != '00:00:00' && strtotime($row['ra_istirahat_masuk']) > strtotime($row['istirahat_masuk']));
                                    $isEarlyOut = (!empty($row['ra_keluar']) && $row['ra_keluar'] != '00:00:00' && !empty($row['jam_keluar']) && $row['jam_keluar'] != '00:00:00' && strtotime($row['ra_keluar']) < strtotime($row['jam_keluar']));

                                    // Incomplete Logs
                                    $hasIncompleteMain = (
                                        (!empty($row['ra_masuk']) && $row['ra_masuk'] != '00:00:00' && (empty($row['ra_keluar']) || $row['ra_keluar'] == '00:00:00')) ||
                                        ((empty($row['ra_masuk']) || $row['ra_masuk'] == '00:00:00') && !empty($row['ra_keluar']) && $row['ra_keluar'] != '00:00:00')
                                    );
                                    $isRestExpected = (!empty($row['istirahat_keluar']) && $row['istirahat_keluar'] != '00:00:00');
                                    $hasIncompleteBreak = ($isRestExpected && (
                                        (empty($row['ra_istirahat_keluar']) || $row['ra_istirahat_keluar'] == '00:00:00') ||
                                        (empty($row['ra_istirahat_masuk']) || $row['ra_istirahat_masuk'] == '00:00:00')
                                    ));

                                    $isEarlyBreak = (!empty($row['ra_istirahat_keluar']) && $row['ra_istirahat_keluar'] != '00:00:00' && !empty($row['istirahat_keluar']) && $row['istirahat_keluar'] != '00:00:00' && strtotime($row['ra_istirahat_keluar']) < strtotime($row['istirahat_keluar']));

                                    // Skip Denda if wage is 0 or status is "Digantikan"
                                    if ($row['r_upah'] == 0 || $row['status_rkk'] == 'Digantikan') {
                                        $potTelatValue = 0;
                                        $potIstirahatValue = 0;
                                        $potPulangValue = 0;
                                        $potTidakLengkapValue = 0;
                                    } else {
                                        $potTelatValue = $isLate ? $globalDendaMasuk : 0;
                                        $potIstirahatValue = ($isEarlyBreak ? $globalDendaIstirahatKeluar : 0) + ($isLateBreak ? $globalDendaIstirahatMasuk : 0);
                                        $potPulangValue = $isEarlyOut ? $globalDendaPulang : 0;
                                        $potTidakLengkapValue = ($hasIncompleteMain || $hasIncompleteBreak) ? $globalDendaTidakLengkap : 0;
                                    }

                                    $total = ($row['r_upah'] + $row['lembur']) - ($potTelatValue + $potIstirahatValue + $potPulangValue + $potTidakLengkapValue + $row['r_potongan_lainnya']);
                            ?>
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td data-label="Tanggal" class="py-4 px-3 text-sm font-bold text-gray-900"><?= date('d/m/Y', strtotime($row['tgl_realisasi_detail'])) ?></td>
                                        <td data-label="Upah Pokok" class="py-4 px-3 text-sm font-medium text-gray-700"><?= rupiah($row['r_upah']) ?></td>
                                        <td data-label="Jam Kerja" class="py-4 px-3 text-sm text-gray-600">
                                            <span class="bg-gray-100 px-2 py-1 rounded text-[11px] font-bold"><?= $row['ra_masuk'] ?> - <?= $row['ra_keluar'] ?></span>
                                        </td>
                                        <td data-label="Pot. Telat" class="py-4 px-3 text-sm font-bold text-rose-600"><?= rupiah($potTelatValue) ?></td>
                                        <td data-label="Pot. Istirahat" class="py-4 px-3 text-sm font-bold text-rose-600"><?= rupiah($potIstirahatValue) ?></td>
                                        <td data-label="Pot. Pulang" class="py-4 px-3 text-sm font-bold text-rose-600"><?= rupiah($potPulangValue) ?></td>
                                        <td data-label="Pot. Tidak Absen" class="py-4 px-3 text-sm font-bold text-rose-600"><?= rupiah($potTidakLengkapValue) ?></td>
                                        <td data-label="Pot. Lain" class="py-4 px-3 text-sm font-bold text-orange-600"><?= rupiah($row['r_potongan_lainnya']) ?></td>
                                        <td data-label="Lembur" class="py-4 px-3 text-sm font-bold text-emerald-600"><?= rupiah($row['lembur']) ?></td>
                                        <td data-label="Total Net" class="py-4 px-3 text-[15px] font-extrabold text-blue-700 text-right"><?= rupiah($total) ?></td>
                                    </tr>
                                <?php
                                }
                            } else {
                                ?>
                                <tr>
                                    <td colspan="8" class="py-12 text-center">
                                        <div class="flex flex-col items-center justify-center opacity-40">
                                            <i class="fas fa-folder-open text-5xl mb-3"></i>
                                            <p class="text-gray-500 font-medium italic">Tidak ada data ditemukan untuk rentang tanggal tersebut.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="py-16 text-center border-2 border-dashed border-gray-100 rounded-3xl">
                    <div class="flex flex-col items-center justify-center">
                        <div class="w-16 h-16 bg-blue-50 text-blue-300 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-search text-2xl"></i>
                        </div>
                        <h5 class="text-gray-400 font-bold mb-1">Cari Data Slip</h5>
                        <p class="text-gray-400 text-sm italic">Pilih rentang tanggal di atas untuk melihat preview slip gaji</p>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<style>
<<<<<<< HEAD
    /* --- 1. RESET & WRAPPER --- */
    .dataTables_wrapper {
        display: block !important;
        width: 100% !important;
        overflow-x: hidden;
        /* Mencegah seluruh halaman geser */
    }

    /* Area responsive untuk tabel */
    .table-responsive {
        width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1rem;
    }

    /* --- 2. INPUT DATE & FORM STYLING --- */
    .custom-input {
        width: 100%;
        height: 40px !important;
        padding: 0.5rem 0.75rem 0.5rem 2.75rem !important;
        /* Padding kiri diperbesar agar teks tidak tumpuk icon */
        background-color: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        outline: none;
        font-size: 13px;
        color: #374151;
        transition: all 0.2s;
    }

    .custom-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Perbaikan Icon Date agar presisi */
    input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
        opacity: 0.6;
        filter: invert(0.3);
        /* Agar warna sedikit lebih kontras */
    }

    /* Container Icon di sebelah kiri input */
    .relative span.absolute {
        z-index: 10;
        width: 2.5rem;
        justify-content: center;
    }

    /* --- 3. MODERN TABLE DESIGN --- */
    .table-modern {
        border-collapse: collapse !important;
        border-spacing: 0 8px !important;
        width: 100% !important;
        margin-bottom: 0 !important;
    }

    .table-modern thead th {
        background-color: #f8fafc !important;
        color: #64748b !important;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.05em;
        font-weight: 700;
        padding: 12px 15px !important;
        border: none !important;
        white-space: nowrap;
        /* Mencegah teks header turun ke bawah */
    }

    .table-modern tbody tr {
        background-color: #ffffff !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease !important;
    }

    .table-modern tbody tr:hover {
        background-color: #f1f5f9 !important;
    }

    .table-modern td {
        padding: 12px 15px !important;
        vertical-align: middle !important;
        border-top: 1px solid #f1f5f9 !important;
        border-bottom: 1px solid #f1f5f9 !important;
        white-space: nowrap;
        /* Mencegah kolom melebar karena teks kepanjangan */
    }

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

    /* --- 4. DATATABLES UI MATCHING --- */
    .dataTables_length label,
    .dataTables_filter label {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
    }

    .dataTables_filter input {
        margin-left: 10px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 5px 12px !important;
        outline: none;
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

    /* --- 5. RESPONSIVE (MOBILE) --- */
    @media screen and (max-width: 1024px) {

        /* Jika layar tablet, biarkan table bisa scroll horizontal */
        .table-modern {
            min-width: 900px;
        }
    }

    @media screen and (max-width: 768px) {
        .table-modern {
            min-width: 100% !important;
            border-spacing: 0 12px !important;
        }

        .table-modern thead {
            display: none !important;
        }

        .table-modern tbody tr {
            display: block;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 10px;
        }

        .table-modern tbody tr:hover {
            transform: none !important;
        }

        .table-modern tbody td {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 8px 10px !important;
            border: none !important;
            border-bottom: 1px dashed #f1f5f9 !important;
            font-size: 13px !important;
            white-space: normal;
            /* Biarkan teks bungkus di mobile */
        }

        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            font-size: 10px;
            min-width: 100px;
            text-align: left;
        }

        .table-modern tbody td:last-child {
            background: #f0f7ff;
            border-radius: 10px;
            margin-top: 5px;
            border-bottom: none !important;
        }

        .table-modern tbody td:last-child:before {
            content: "TOTAL NET";
            color: #2563eb;
        }
=======
    /* Styling modern untuk select2 mengikuti input date */
    .modern-select2-container .select2-selection--single {
        height: 54px !important;
        padding: 12px 16px !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 1rem !important; /* rounded-2xl */
        background-color: white !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
        transition: all 0.2s !important;
        display: flex !important;
        align-items: center !important;
    }
    
    .modern-select2-container.select2-container--focus .select2-selection--single {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2) !important;
    }

    .modern-select2-container .select2-selection__arrow {
        height: 52px !important;
        right: 12px !important;
    }

    .modern-select2-container .select2-selection__rendered {
        color: #374151 !important;
        font-weight: 700 !important;
        padding-left: 0 !important;
    }

    .modern-select2-container .select2-search__field {
        border-radius: 8px !important;
        padding: 8px 12px !important;
        border: 1px solid #e5e7eb !important;
        outline: none !important;
    }
    
    .modern-select2-container .select2-search__field:focus {
        border-color: #3b82f6 !important;
    }

    .modern-select2-container .select2-results__option {
        padding: 10px 16px !important;
        font-weight: 500 !important;
    }

    .modern-select2-container .select2-results__option--highlighted[aria-selected] {
        background-color: #eff6ff !important;
        color: #1d4ed8 !important;
        font-weight: 700 !important;
>>>>>>> 1bd684ca8a586ffe8be5851bd648670c1146fb2a
    }
</style>

<script>
<<<<<<< HEAD
    $(document).ready(function() {
        $('#slipTable').DataTable({
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
=======
$(document).ready(function() {
    $('.select2-slip').select2({
        width: '100%',
        dropdownAutoWidth: true,
        containerCssClass: 'modern-select2-container',
        dropdownCssClass: 'modern-select2-container'
>>>>>>> 1bd684ca8a586ffe8be5851bd648670c1146fb2a
    });
});
</script>

<?php
$ttgl11 = $_POST['ttgl1'] ?? '';
$ttgl22 = $_POST['ttgl2'] ?? '';
$id_karyawan_post = $_POST['id_karyawan_post'] ?? '';
$simpan = $_POST['simpan'] ?? '';

if($simpan) {
?>
    <script type="text/javascript">
        window.location.href="slip.php?id=<?= $id_karyawan_post ?>&ttgl1=<?= $ttgl11 ?>&ttgl2=<?= $ttgl22 ?>";
    </script>
<?php
}
?>