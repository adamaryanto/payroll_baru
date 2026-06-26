<?php
$role_user = strtolower($_SESSION['role'] ?? '');
$is_authorized_delete = ($role_user == "admin master" || $role_user == "kepala pabrik" || $role_user == "owner");

if (!$is_authorized_delete) {
    echo "<!DOCTYPE html><html><head>
          <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          </head><body>
          <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Akses Ditolak!',
                    text: 'Anda tidak memiliki izin untuk mengakses halaman Hapus Massal.',
                    icon: 'error',
                    confirmButtonColor: '#3b82f6'
                }).then(() => {
                    window.location.href='?page=realisasi';
                });
            });
          </script></body></html>";
    exit;
}

// Proses Hapus Jika Form Disubmit
if (isset($_POST['proses_hapus'])) {
    if (!empty($_POST['id_realisasi_pilih'])) {
        $ids = $_POST['id_realisasi_pilih'];
        $count = 0;

        foreach ($ids as $id) {
            $id = intval($id);
            // 1. Hapus Detail terlebih dahulu
            $koneksi->query("DELETE FROM tb_realisasi_detail WHERE id_realisasi = $id");
            // 2. Hapus Data Utama
            if ($koneksi->query("DELETE FROM tb_realisasi WHERE id_realisasi = $id")) {
                $count++;
            }
        }
        echo "<script>
            setTimeout(function() {
                Swal.fire({
                    title: 'Berhasil!',
                    text: '$count data realisasi telah dihapus.',
                    icon: 'success',
                    confirmButtonColor: '#e11d48'
                }).then(() => {
                    window.location.href='?page=realisasi&aksi=hapus_massal';
                });
            }, 100);
        </script>";
    }
}

// Query Tampil Data Realisasi
$tampil = $koneksi->query("SELECT A.*, 
    (SELECT COUNT(RD.id_realisasi_detail) 
     FROM tb_realisasi_detail RD 
     JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail 
     WHERE RD.id_realisasi = A.id_realisasi AND RKD.status_rkk != 'Digantikan'
    ) as jml
    FROM tb_realisasi A ORDER BY A.tgl_realisasi ASC, A.id_realisasi ASC");
?>

<style>
    .dataTables_wrapper .dataTables_paginate {
        margin-top: 1.5rem;
        padding-bottom: 1rem;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.25rem;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0.375rem 0.75rem !important;
        margin-left: 0.25rem !important;
        border-radius: 0.375rem !important;
        border: 1px solid #e5e7eb !important; /* border-gray-200 */
        background: #ffffff !important;
        color: #374151 !important; /* text-gray-700 */
        cursor: pointer !important;
        text-decoration: none !important;
        font-size: 0.875rem !important;
        transition: all 0.2s ease-in-out;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.disabled) {
        background: #f3f4f6 !important; /* bg-gray-100 */
        color: #111827 !important; /* text-gray-900 */
        border-color: #d1d5db !important; /* border-gray-300 */
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current, 
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: #e11d48 !important; /* bg-rose-600 */
        color: #ffffff !important;
        border-color: #e11d48 !important;
        font-weight: bold;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
        background: #f9fafb !important;
        color: #9ca3af !important;
        border-color: #e5e7eb !important;
        box-shadow: none !important;
    }

    /* Memperbaiki jarak dropdown lengthMenu dan input search */
    .dataTables_wrapper .dataTables_length, 
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 1rem;
        padding: 0 1rem;
    }
    .dataTables_wrapper .dataTables_info {
        padding: 1rem;
        font-size: 0.875rem;
        color: #6b7280;
    }
</style>

<div class="container-fluid px-3 mt-4 mb-4">
    <form method="POST" id="formHapusMassal">
        <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

            <div class="border-b border-gray-200 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center bg-white gap-4">
                <div>
                    <h3 class="text-xl font-bold text-rose-600 m-0"><i class="fas fa-trash-alt mr-2"></i>Hapus Realisasi Upah</h3>
                    <p class="text-xs text-gray-500 mt-1">Data yang dipilih akan dihapus permanen</p>
                </div>
                <div class="flex gap-2 w-full md:w-auto">
                    <a href="?page=realisasi" class="flex-1 md:flex-none justify-center inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors text-decoration-none">
                        <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                    </a>
                    
                    <button type="button" onclick="konfirmasiHapus()" class="flex-1 md:flex-none justify-center border-0 inline-flex items-center bg-rose-600 hover:bg-rose-700 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors">
                        <i class="fas fa-trash mr-1.5"></i> Hapus Terpilih
                    </button>
                    
                    <input type="hidden" name="proses_hapus" value="1">
                </div>
            </div>

            <div class="p-0">
                <div class="table-responsive px-3 md:px-4 py-4">
                    <table class="w-full text-left border-collapse table-modern" id="dataTables-hapus">
                        <thead class="bg-gray-50 border-b border-gray-300">
                            <tr>
                                <th class="py-3 px-2 text-center w-12 no-sort">
                                    <input type="checkbox" id="select_all" class="w-4 h-4 text-blue-600 rounded cursor-pointer">
                                </th>
                                <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase w-16">No</th>
                                <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Tanggal</th>
                                <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Jumlah Karyawan</th>
                                <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php
                            $no = 1;
                            if ($tampil && $tampil->num_rows > 0) :
                                while ($data = $tampil->fetch_assoc()) :
                            ?>
                                <tr class="hover:bg-rose-50 transition-colors cursor-pointer" onclick="toggleCheckbox(<?= $data['id_realisasi'] ?>)">
                                    <td class="py-3 px-2 text-center" onclick="event.stopPropagation()">
                                        <input type="checkbox" name="id_realisasi_pilih[]" value="<?= $data['id_realisasi'] ?>" id="check_<?= $data['id_realisasi'] ?>" class="check_item w-4 h-4 text-rose-600 rounded cursor-pointer">
                                    </td>
                                    <td class="py-3 px-2 text-center text-[15px]"><?= $no++ ?></td>
                                    <td class="py-3 px-2 text-[15px] font-bold text-gray-900"><?= date('d/m/Y', strtotime($data['tgl_realisasi'])) ?></td>
                                    <td class="py-3 px-2 text-center">
                                        <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-bold">
                                            <?= $data['jml'] ?> Orang
                                        </span>
                                    </td>
                                    <td class="py-3 px-2 text-[14px] text-gray-600"><?= $data['keterangan'] ?></td>
                                </tr>
                            <?php endwhile; else : ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-gray-400">
                                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                                        <p class="text-lg font-medium">Data tidak tersedia</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        $('#dataTables-hapus').DataTable({
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
            },
            columnDefs: [{
                targets: 'no-sort',
                orderable: false
            }]
        });
    });

    function konfirmasiHapus() {
        const checked = document.querySelectorAll('.check_item:checked').length;

        if (checked === 0) {
            Swal.fire({
                title: 'Pilih Data!',
                text: 'Silakan centang data yang ingin dihapus terlebih dahulu.',
                icon: 'warning',
                confirmButtonColor: '#3b82f6'
            });
            return;
        }

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Total " + checked + " data realisasi akan dihapus permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formHapusMassal').submit();
            }
        });
    }

    document.getElementById('select_all').addEventListener('change', function() {
        document.querySelectorAll('.check_item').forEach(cb => cb.checked = this.checked);
    });

    function toggleCheckbox(id) {
        const cb = document.getElementById('check_' + id);
        if (cb) cb.checked = !cb.checked;
    }
</script>