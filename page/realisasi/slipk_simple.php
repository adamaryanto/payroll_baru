<?php
$q_list = $koneksi->query("SELECT A.id_karyawan, A.no_absen, A.nama_karyawan, B.nama_departmen 
                           FROM ms_karyawan A 
                           LEFT JOIN ms_departmen B ON A.id_departmen = B.id_departmen 
                           WHERE A.status_karyawan = 'Aktif' 
                           ORDER BY A.nama_karyawan ASC");
?>
<div class="container-fluid px-3 md:px-6 mt-6 mb-10">
    <div class="bg-white shadow-xl rounded-2xl border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-6">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center text-white border border-white/30">
                    <i class="fas fa-file-invoice-dollar text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white m-0">Cetak Slip - Simple</h3>
                    <p class="text-emerald-100 text-sm opacity-90">Langsung dari Tarik Data (tb_record) tanpa Rencana/Realisasi</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="table-responsive">
                <table class="w-full text-left border-separate border-spacing-0" id="tableKaryawanSimple">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b">No. Absen</th>
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b">Nama Karyawan</th>
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b">Departemen</th>
                            <th class="py-4 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-widest border-b text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php while ($row = $q_list->fetch_assoc()): ?>
                            <tr class="hover:bg-emerald-50/50">
                                <td class="py-4 px-4 text-sm font-semibold text-gray-700"><?= $row['no_absen'] ?></td>
                                <td class="py-4 px-4 text-sm font-bold text-gray-900"><?= $row['nama_karyawan'] ?></td>
                                <td class="py-4 px-4"><span class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-1 rounded-md font-bold uppercase"><?= $row['nama_departmen'] ?></span></td>
                                <td class="py-4 px-4 text-center">
                                    <a href="index.php?page=realisasi&aksi=slip_simple&id=<?= $row['id_karyawan'] ?>" class="inline-flex items-center px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-xl shadow-md">
                                        <i class="fas fa-eye mr-2"></i> Preview Slip
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
<script>
$(document).ready(function(){
    $('#tableKaryawanSimple').DataTable({pageLength:25,language:{search:"Cari:",searchPlaceholder:"Cari data...",lengthMenu:"Tampilkan _MENU_ data",info:"Menampilkan _START_ s/d _END_ dari _TOTAL_ data",paginate:{previous:"Prev",next:"Next"}}});
});
</script>
