<?php
// Master Grup Borongan: 1 grup = 1 harga (Rp/kg), upah = kg x harga
include __DIR__ . '/_setup.php';

// Seed contoh bila masih kosong
$cekSeed = $koneksi->query("SELECT COUNT(*) as c FROM ms_grup_borongan");
if ($cekSeed && (int)$cekSeed->fetch_assoc()['c'] === 0) {
    $koneksi->query("INSERT INTO ms_grup_borongan (nama_grup, harga, keterangan, tgl_updt) VALUES ('DADA SP SHILIN', 500, '', NOW())");
}

$tampil = $koneksi->query("SELECT * FROM ms_grup_borongan ORDER BY nama_grup ASC");
?>

<div class="container-fluid px-3 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-200 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center bg-white gap-4">
            <div>
                <h3 class="text-xl font-bold text-blue-600 m-0"><i class="fas fa-boxes mr-2"></i>Grup</h3>
                <p class="text-slate-500 text-xs sm:text-sm mt-1">Kelompok kerja; tarif borongan (Rp/kg) opsional per grup &amp; per orang</p>
            </div>
            <div class="flex flex-wrap md:flex-nowrap gap-2 w-full md:w-auto">
                <a href="?page=grup_borongan&aksi=tambah" class="flex-1 md:flex-none justify-center inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors">
                    <i class="fas fa-plus mr-1.5"></i> Tambah Grup
                </a>
            </div>
        </div>

        <div class="p-0">
            <div class="table-responsive px-3 md:px-4 py-4">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-100 border-b border-gray-300">
                        <tr>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">No</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase">Nama Grup</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-right">Tarif /Kg</th>
                            <th class="py-3 px-2 text-sm font-bold text-gray-700 uppercase text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $no = 1;
                        while ($data = $tampil->fetch_assoc()) :
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td data-label="No" class="py-3 px-2 text-center"><?= $no++ ?></td>
                                <td data-label="Nama Grup" class="py-3 px-2 font-bold text-gray-900"><?= htmlspecialchars($data['nama_grup']) ?></td>
                                <td data-label="Tarif /Kg" class="py-3 px-2 text-right font-bold text-emerald-600"><?= empty($data['harga']) ? '-' : 'Rp ' . number_format($data['harga'], 0, ',', '.') ?></td>
                                <td data-label="Aksi" class="py-3 px-2 align-middle">
                                    <div class="action-btn-group md:justify-center">
                                        <a href="?page=grup_borongan&aksi=ubah&id=<?= $data['id_grup']; ?>"
                                            class="flex items-center px-2.5 py-1.5 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-300 transition-colors">
                                            <i class="fas fa-edit mr-1"></i> Ubah
                                        </a>
                                        <a href="?page=grup_borongan&aksi=hapus&id=<?= $data['id_grup']; ?>"
                                            onclick="return confirm('Hapus grup ini?')"
                                            class="flex items-center px-2.5 py-1.5 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-300 transition-colors">
                                            <i class="fas fa-trash mr-1"></i> Hapus
                                        </a>
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
.dataTables_wrapper{ display:block !important; }
.dataTables_wrapper::before, .dataTables_wrapper::after{ display:none !important; }
#dataTables-example_wrapper .row:first-child{ display:flex !important; justify-content:space-between !important; align-items:center !important; margin-bottom:16px !important; width:100% !important; flex-wrap:wrap; gap:12px; }
.dataTables_length label, .dataTables_filter label{ display:flex !important; align-items:center !important; gap:8px !important; margin:0 !important; }
.dataTables_length{ text-align:left !important; }
.dataTables_length label{ justify-content:flex-start !important; }
.dataTables_filter{ text-align:right !important; }
.dataTables_filter label{ justify-content:flex-end !important; }
.dataTables_length select{ padding:5px 10px !important; border:1px solid #e0e6ed !important; border-radius:8px !important; }
.dataTables_filter input{ padding:6px 12px !important; border:1px solid #e0e6ed !important; border-radius:8px !important; width:200px !important; }
.dataTables_wrapper .dataTables_paginate{ display:flex !important; justify-content:flex-end !important; align-items:center !important; gap:4px !important; padding-top:12px !important; flex-wrap:wrap; }
.dataTables_paginate .paginate_button{ border:1px solid #e2e8f0 !important; background:white !important; border-radius:6px !important; padding:5px 12px !important; color:#475569 !important; font-weight:500 !important; cursor:pointer !important; }
.dataTables_paginate .paginate_button.current{ background:#4f46e5 !important; border-color:#4f46e5 !important; color:white !important; }
.dataTables_paginate .paginate_button.disabled{ background:#f1f5f9 !important; color:#94a3b8 !important; cursor:not-allowed !important; }
.dataTables_wrapper .dataTables_info{ padding-top:16px !important; color:#64748b !important; font-size:13px !important; }
#dataTables-example_wrapper .row:last-child{ display:flex !important; justify-content:space-between !important; align-items:center !important; flex-wrap:wrap !important; gap:12px !important; margin-top:12px !important; }
.table-responsive{ overflow-x:auto !important; }
.dt-toolbar{ display:flex !important; justify-content:space-between !important; align-items:center !important; flex-wrap:wrap !important; gap:12px !important; margin-bottom:16px !important; }
.dt-toolbar .dt-length{ text-align:left !important; }
.dt-toolbar .dt-length label{ display:flex !important; align-items:center !important; gap:8px !important; margin:0 !important; }
.dt-toolbar .dt-search{ margin-left:auto !important; text-align:right !important; }
.dt-toolbar .dt-search label{ display:flex !important; align-items:center !important; justify-content:flex-end !important; gap:8px !important; margin:0 !important; }
.dt-bottom{ display:flex !important; justify-content:space-between !important; align-items:center !important; flex-wrap:wrap !important; gap:12px !important; margin-top:12px !important; }
.dt-bottom .dt-page{ margin-left:auto !important; }
.action-btn-group{ display:flex !important; flex-direction:row !important; gap:8px !important; justify-content:center !important; align-items:center !important; flex-wrap:wrap !important; }
.action-btn-group a{ white-space:nowrap !important; }
@media(max-width:768px){ .dataTables_filter{ width:100% !important; } .dataTables_filter input{ width:100% !important; } }
</style>
<script>
    $(document).ready(function() {
        $('#dataTables-example').DataTable({
            pageLength: 25,
            autoWidth: false,
            responsive: false,
            ordering: false,
            dom: '<"dt-toolbar"<"dt-length"l><"dt-search"f>>rt<"dt-bottom"<"dt-info"i><"dt-page"p>>',
            language: {
                search: "Cari Data:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                paginate: { previous: "Prev", next: "Next" }
            }
        });
    });
</script>
