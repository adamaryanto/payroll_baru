<?php

// 1. hak Approve/Un-Approve (2026-10-26, dibalik atas permintaan user):
// Owner + Admin Master + Kepala Pabrik. Admin HR TIDAK bisa Approve lagi.
$is_authorized = in_array(strtolower($_SESSION['role'] ?? ''), ["owner", "admin master", "kepala pabrik"], true);
$is_hapus_checklist = in_array(strtolower($_SESSION['role'] ?? ''), ["kepala pabrik", "admin master"], true);

// 2.hak Propose/Un-Propose (HRD & Admin Master)
$can_propose = (strtolower($_SESSION['role']) == "admin hr" || strtolower($_SESSION['role']) == "kepala pabrik" || strtolower($_SESSION['role']) == "admin master");

// 2026-10-26 (koreksi): Hapus by checklist INLINE di List ini, bukan halaman terpisah.
// Checkbox ada di samping kolom No (hanya untuk Kepala Pabrik + Admin Master).
// POST ini diproses SEBELUM query list, jadi baris yang dihapus langsung hilang dari list.
$hapus_hasil_rkk = '';
if (isset($_POST['hapus_terpilih'])) {
    if (!in_array(strtolower($_SESSION['role'] ?? ''), ["kepala pabrik", "admin master"], true)) {
        $hapus_hasil_rkk = 'error';
    } elseif (empty($_POST['pilih_id']) || !is_array($_POST['pilih_id'])) {
        $hapus_hasil_rkk = 'kosong';
    } else {
        $hapus_cnt_rkk = 0;
        foreach ($_POST['pilih_id'] as $hid) {
            $hid = (int)$hid;
            if ($hid <= 0) continue;
            $koneksi->query("DELETE FROM tb_rkk_detail WHERE id_rkk = $hid");
            if ($koneksi->query("DELETE FROM tb_rkk WHERE id_rkk = $hid")) $hapus_cnt_rkk++;
        }
        $hapus_hasil_rkk = 'ok:' . $hapus_cnt_rkk;
    }
}

$where_rkk = (strtolower($_SESSION['role']) == 'owner') ? " WHERE A.status_rkk > 0 " : "";
$tampil = $koneksi->query("SELECT A.*, 
    (SELECT COUNT(id_rkk_detail) FROM tb_rkk_detail WHERE id_rkk = A.id_rkk AND status_rkk != 'Digantikan') as jml, 
    (SELECT SUM(CASE WHEN status_rkk = 'Digantikan' THEN 0 ELSE upah END) FROM tb_rkk_detail WHERE id_rkk = A.id_rkk) as ttl,
    (SELECT COUNT(id_boneless) FROM tb_boneless WHERE tgl = A.tgl_rkk) as jml_boneless 
    FROM tb_rkk A $where_rkk ORDER BY A.tgl_rkk ASC, A.id_rkk ASC");
if (strtolower($_SESSION['role']) != "owner") {
    $level_status =  "Hidden";
} else {
    $level_status = "";
}
if (strtolower($_SESSION['role']) == "owner") {
    $hr =  "Hidden";
} else {
    $hr = "";
}

?>

<div class="container-fluid px-3 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-200 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center bg-white gap-4">
            <div>
                <h3 class="text-xl font-bold text-blue-600 m-0"><i class="fas fa-list-alt mr-2"></i>List Rencana Upah</h3>
            </div>
            <div class="flex flex-wrap md:flex-nowrap gap-2 w-full md:w-auto">
                <a href="?page=boneless&ref=rkk" class="flex-1 md:flex-none justify-center inline-flex items-center bg-amber-500 hover:bg-amber-600 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors">
                    <i class="fas fa-drumstick-bite mr-1.5"></i> Boneless
                </a>
                <a href="?page=rkk&aksi=tambah" class="flex-1 md:flex-none justify-center inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors">
                    <i class="fas fa-plus mr-1.5"></i> Tambah Data
                </a>
                <a href="?page=rkk&aksi=import" class="flex-1 md:flex-none justify-center inline-flex items-center bg-emerald-600 hover:bg-emerald-700 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors">
                    <i class="fas fa-file-excel mr-1.5"></i> Import Excel
                </a>
                <!-- Tombol Hapus Masal DIHAPUS untuk semua role. Penggantinya: Hapus by
                     checklist INLINE di list ini (checkbox di samping kolom No) hanya
                     untuk Kepala Pabrik + Admin Master. -->
                <?php if ($is_hapus_checklist): ?>
                <button type="button" id="btnHapusTerpilihRKK" onclick="hapusTerpilihRKK()" class="flex-1 md:flex-none justify-center inline-flex items-center bg-rose-600 hover:bg-rose-700 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors border-0">
                    <i class="fas fa-check-square mr-1.5"></i> Hapus Terpilih
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-0">
            <div class="table-responsive px-3 md:px-4 py-4">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-100 border-b border-gray-300">
                        <tr>
                            <?php if ($is_hapus_checklist): ?><th class="py-3 px-2 text-center w-10"><input type="checkbox" id="select_all_hapus_rkk" class="w-4 h-4 cursor-pointer" title="Pilih semua"></th><?php endif; ?>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">No</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Tanggal</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Keterangan</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Jumlah Karyawan</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Aksi Data</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Otorisasi</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $no = 1;
                        while ($data = $tampil->fetch_assoc()) :
                            $status_color = ($data['status_rkk'] == "3") ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800";
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <?php if ($is_hapus_checklist): ?><td class="py-3 px-2 text-center"><input type="checkbox" class="check_item_hapus_rkk w-4 h-4 cursor-pointer" name="pilih_id[]" value="<?= (int)$data['id_rkk'] ?>"></td><?php endif; ?>
                                <td data-label="No" class="py-3 px-2 text-center text-base md:text-[15px] font-bold md:font-normal"><?= $no++ ?></td>
                                <td data-label="Tanggal" class="py-3 px-2 text-base md:text-[15px] font-bold text-gray-900"><?= date('d/m/Y', strtotime($data['tgl_rkk'])) ?></td>
                                <td data-label="Keterangan" class="py-3 px-2 text-base md:text-[14px] text-gray-700"><?= $data['keterangan'] ?></td>
                                <td data-label="Jumlah Karyawan" class="py-3 px-2 md:text-center text-base md:text-[15px]">
                                    <span class="font-bold text-blue-700"><?= $data['jml'] ?> Orang</span><br>
                                    <span class="text-emerald-600 font-semibold text-sm">Rp <?= number_format($data['ttl'] ?? 0, 0, ',', '.') ?></span>
                                </td>

                                <td data-label="Aksi Data" class="py-3 px-2 align-middle">
                                    <div class="action-btn-group md:justify-center">
                                        <a href="?page=rkk&aksi=kelola&id=<?= $data['id_rkk']; ?>"
                                            class="flex items-center px-3 py-2 text-[13px] md:text-[12px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-300 transition-colors">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </a>
                                        <a href="excelrkk.php?id=<?php echo $data['id_rkk']; ?>"
                                            class="flex items-center px-3 py-2 text-[13px] md:text-[12px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded border border-emerald-300 transition-colors btn-cetak-rkk"
                                            data-boneless="<?= $data['jml_boneless']; ?>">
                                            <i class="fa fa-print mr-1"></i> Cetak
                                        </a>
                                        <?php if (strtolower($_SESSION['role']) == "owner" && $data['status_rkk'] < 2) : ?>
                                            <a href="?page=rkk&aksi=karyawan&id=<?= $data['id_rkk']; ?>"
                                                class="flex items-center px-3 py-2 text-[13px] md:text-[12px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-300 transition-colors">
                                                <i class="fas fa-user-plus mr-1.5"></i> Tetapkan
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td data-label="Otorisasi" class="py-3 px-2 align-middle">
                                    <div class="action-btn-group md:justify-center">
                                        <?php if ($data['status_rkk'] == '0' && $can_propose) : ?>
                                            <button type="button"
                                                class="flex items-center px-3 py-2 text-[13px] md:text-[12px] font-bold text-amber-700 bg-amber-50 hover:bg-amber-600 hover:text-white rounded border border-amber-300 transition-colors btn-action-rkk"
                                                data-id="<?= $data['id_rkk']; ?>"
                                                data-action="pro"
                                                data-jml="<?= $data['jml']; ?>"
                                                data-boneless="<?= $data['jml_boneless']; ?>"
                                                data-text="Propose data ini?">
                                                <i class="fas fa-paper-plane mr-1"></i> Propose
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($data['status_rkk'] == '1' && $can_propose) : ?>
                                            <button type="button"
                                                class="flex items-center px-3 py-2 text-[13px] md:text-[12px] font-bold text-gray-700 bg-gray-50 hover:bg-gray-600 hover:text-white rounded border border-gray-300 transition-colors btn-action-rkk"
                                                data-id="<?= $data['id_rkk']; ?>"
                                                data-action="unpro"
                                                data-text="Tarik kembali data (Un-propose)?">
                                                <i class="fas fa-undo mr-1"></i> Un-Propose
                                            </button>
                                        <?php endif; ?>

                                        <?php if (($data['status_rkk'] == '1' && $is_authorized) || ($data['status_rkk'] == '0' && $is_authorized)) : ?>
                                            <button type="button"
                                                class="flex items-center px-3 py-2 text-[13px] md:text-[12px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded border border-emerald-300 transition-colors btn-action-rkk"
                                                data-id="<?= $data['id_rkk']; ?>"
                                                data-action="app"
                                                data-jml="<?= $data['jml']; ?>"
                                                data-boneless="<?= $data['jml_boneless']; ?>"
                                                data-text="Approve data ini?">
                                                <i class="fas fa-check mr-1"></i> Approve
                                            </button>
                                        <?php endif; ?>

                                        <?php if (($data['status_rkk'] == '2' || $data['status_rkk'] == '3') && $is_authorized) : ?>
                                            <button type="button"
                                                class="flex items-center px-3 py-2 text-[13px] md:text-[12px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-300 transition-colors btn-action-rkk"
                                                data-id="<?= $data['id_rkk']; ?>"
                                                data-action="unapp"
                                                data-text="Batalkan Approve data ini?">
                                                <i class="fas fa-times mr-1"></i> Un-Approve
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td data-label="Status" class="py-3 px-2 text-center align-middle">
                                    <?php if ($data['status_rkk'] >= 2) : ?>
                                        <div class="stamp stamp-approved">Approved</div>
                                    <?php else : ?>
                                        <div class="stamp stamp-unapproved">Unapproved</div>
                                    <?php endif; ?>
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

    /* Tombol aksi sejajar ke samping (desktop & mobile) */
    .action-btn-group {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        gap: 8px !important;
        align-items: center !important;
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
            transform: rotate(0deg);
            margin: 5px 0;
            font-size: 14px;
            padding: 2px 10px;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty($hapus_hasil_rkk)): ?>
<script>
(function() {
    var h = <?= json_encode($hapus_hasil_rkk) ?>;
    if (h === 'error') Swal.fire({ icon: 'error', title: 'Akses Ditolak', text: 'Hapus Checklist hanya untuk Kepala Pabrik dan Admin Master.', confirmButtonColor: '#2563eb' });
    else if (h === 'kosong') Swal.fire({ icon: 'warning', title: 'Belum Dipilih', text: 'Centang dulu data yang ingin dihapus.', confirmButtonColor: '#2563eb' });
    else if (h.indexOf('ok:') === 0) Swal.fire({ icon: 'success', title: 'Berhasil', text: h.split(':')[1] + ' data Rencana berhasil dihapus.', confirmButtonColor: '#2563eb' });
})();
</script>
<?php endif; ?>
<script>
function hapusTerpilihRKK() {
    var checked = document.querySelectorAll('.check_item_hapus_rkk:checked');
    if (checked.length === 0) { Swal.fire({ icon: 'warning', title: 'Belum Dipilih', text: 'Centang dulu data yang ingin dihapus (checkbox di samping kolom No).', confirmButtonColor: '#2563eb' }); return; }
    Swal.fire({ title: 'Hapus Data Terpilih?', text: checked.length + ' data Rencana yang dicentang akan dihapus permanen!', icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48', cancelButtonColor: '#64748b', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' }).then(function(r) {
        if (!r.isConfirmed) return;
        var f = document.createElement('form'); f.method = 'POST'; f.action = window.location.href;
        var h = document.createElement('input'); h.type = 'hidden'; h.name = 'hapus_terpilih'; h.value = '1'; f.appendChild(h);
        checked.forEach(function(c) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'pilih_id[]'; i.value = c.value; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
    });
}
document.addEventListener('DOMContentLoaded', function() {
    var sa = document.getElementById('select_all_hapus_rkk');
    if (sa) sa.addEventListener('change', function() { document.querySelectorAll('.check_item_hapus_rkk').forEach(function(c) { c.checked = sa.checked; }); });
});
</script>
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

        // SweetAlert Action Confirmation - Using delegation for DataTables compatibility
        $(document).on('click', '.btn-action-rkk', function() {
            const id = $(this).data('id');
            const action = $(this).data('action');
            const text = $(this).data('text');
            const jml = $(this).data('jml');
            const boneless = $(this).data('boneless');

            // Validasi Data Sebelum Propose/Approve
            if (action === 'pro' || action === 'app') {
                if (jml == 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Data Kosong',
                        text: 'Silakan tambahkan data karyawan terlebih dahulu!',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }

                if ((action === 'pro' || action === 'app') && boneless == 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Boneless Belum Diinput',
                        text: 'Data Boneless untuk tanggal ini belum ditemukan. Silakan input Boneless terlebih dahulu.',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }
            }

            let confirmButtonColor = '#2563eb'; // Default blue
            if (action === 'unapp' || action === 'unpro') confirmButtonColor = '#e11d48'; // Rose/Red
            if (action === 'app' || action === 'pro' || action === 'realisasi') confirmButtonColor = '#059669'; // Emerald

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
                    if (action === 'realisasi') {
                        window.location.href = '?page=realisasi&aksi=tambah&id=' + id;
                    } else {
                        window.location.href = '?page=rkk&aksi=accept&id=' + id + '&iddetail=' + action;
                    }
                }
            });
        });

        $('.dataTables_filter').addClass('mb-3');
        $('.dataTables_length').addClass('mb-3');
    });

    // Cetak Rencana: semua role boleh download, tanpa menunggu Boneless diinput.
    // excelrkk.php sudah aman kalau Boneless kosong (biaya boneless jadi 0).
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
    </script>
<?php
}
if ($print) {
?><script type="text/javascript">
        window.location.href = "laporanpendapatan.php?ttgl1=<?php echo $ttgl1; ?>&ttgl2=<?php echo $ttgl2; ?>";
    </script>
<?php
}
if ($excel) {
?><script type="text/javascript">
        window.location.href = "excelpendapatan.php?ttgl1=<?php echo $ttgl1; ?>&ttgl2=<?php echo $ttgl2; ?>";
    </script>
<?php
}
?>