<?php
// Global tarif
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_lembur (id_lembur INT AUTO_INCREMENT PRIMARY KEY, lembur_per_jam INT DEFAULT 0) ENGINE=InnoDB");
$check = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1");
if ($check->num_rows == 0) $koneksi->query("INSERT INTO tb_lembur (id_lembur, lembur_per_jam) VALUES (1, 0)");
$tampil = $koneksi->query("SELECT * FROM tb_lembur LIMIT 1");
$data = $tampil->fetch_assoc();
$lemburperjam = $data['lembur_per_jam'] ?? 0;

// Tambah kolom per karyawan jika belum ada
$cekCol = $koneksi->query("SHOW COLUMNS FROM ms_karyawan LIKE 'lembur_per_jam'");
if($cekCol->num_rows==0) $koneksi->query("ALTER TABLE ms_karyawan ADD COLUMN lembur_per_jam INT DEFAULT NULL COMMENT 'NULL=pakai global'");

if (isset($_POST['simpan'])) {
    $tlemburperjam = intval($_POST['tlemburperjam'] ?? 0);
    $sql = $koneksi->query("UPDATE tb_lembur SET lembur_per_jam = '$tlemburperjam' WHERE id_lembur = 1");
    if ($sql) {
        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'success',title:'Berhasil',text:'Tarif global diperbarui',confirmButtonColor:'#2563eb'}).then(()=>{window.location.href='?page=lembur';});</script></body></html>"; exit;
    }
}
if (isset($_POST['simpan_perkaryawan'])) {
    foreach($_POST['karyawan'] as $id=>$val){
        $id=intval($id); $v = $val==='' ? 'NULL' : intval($val);
        if($v==='NULL') $koneksi->query("UPDATE ms_karyawan SET lembur_per_jam=NULL WHERE id_karyawan=$id");
        else $koneksi->query("UPDATE ms_karyawan SET lembur_per_jam=$v WHERE id_karyawan=$id");
    }
    echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'success',title:'Berhasil',text:'Tarif per karyawan diperbarui',confirmButtonColor:'#4f46e5'}).then(()=>{window.location.href='?page=lembur';});</script></body></html>"; exit;
}
?>

<style>
.dataTables_wrapper{ display:block !important; }
#dataTables-example_wrapper .row:first-child{ display:flex !important; justify-content:space-between !important; align-items:center !important; margin-bottom:16px !important; width:100% !important; flex-wrap:wrap; gap:12px; }
.dataTables_length label, .dataTables_filter label{ display:flex !important; align-items:center !important; gap:8px !important; margin:0 !important; }
.dataTables_filter input{ padding:6px 12px !important; border:1px solid #e0e6ed !important; border-radius:8px !important; width:200px !important; }
.dataTables_wrapper .dataTables_paginate{ display:flex !important; justify-content:flex-end !important; align-items:center !important; gap:4px !important; padding-top:12px !important; flex-wrap:wrap; }
.dataTables_paginate .paginate_button{ border:1px solid #e2e8f0 !important; background:white !important; border-radius:6px !important; padding:5px 12px !important; color:#475569 !important; font-weight:500 !important; cursor:pointer !important; }
.dataTables_paginate .paginate_button.current{ background:#4f46e5 !important; border-color:#4f46e5 !important; color:white !important; }
.dataTables_wrapper .dataTables_info{ padding-top:16px !important; color:#64748b !important; font-size:13px !important; }
#dataTables-example_wrapper .row:last-child{ display:flex !important; justify-content:space-between !important; align-items:center !important; flex-wrap:wrap !important; gap:12px !important; margin-top:12px !important; }
.table-responsive{ overflow-x:auto !important; }
@media(max-width:768px){ .dataTables_filter{ width:100% !important; } .dataTables_filter input{ width:100% !important; } }
</style>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10 space-y-8">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-clock mr-3"></i> Pengaturan Tarif Lembur
            </h3>
            <p class="text-slate-200 text-sm mt-1">Atur tarif global, atau set per karyawan di bawah (kosong = pakai global)</p>
        </div>
        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Tarif Global / Jam <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-400 font-bold">Rp</span></div>
                            <input type="number" name="tlemburperjam" value="<?= htmlspecialchars($lemburperjam) ?>" required class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 font-medium"/>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Dipakai jika per karyawan kosong</p>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="simpan" class="inline-flex items-center px-6 py-3 text-sm font-bold rounded-xl shadow-md text-white bg-indigo-600 hover:bg-indigo-700"><i class="fas fa-save mr-2"></i> Simpan Global</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-violet-600 to-purple-600 px-8 py-6 flex justify-between items-center">
            <div>
                <h3 class="text-xl font-extrabold text-white m-0 flex items-center"><i class="fas fa-users mr-3"></i> Tarif Per Karyawan</h3>
                <p class="text-violet-200 text-sm mt-1">Kosongkan untuk pakai tarif global (Rp <?= number_format($lemburperjam,0,',','.') ?>)</p>
            </div>
            <span class="hidden md:inline text-xs bg-white/20 text-white px-3 py-1 rounded-full">Editable per baris</span>
        </div>
        <form method="POST">
            <div class="p-6">
                <div class="overflow-x-auto px-2">
                    <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="py-3 px-3 text-xs font-bold text-gray-600 uppercase">No</th>
                                <th class="py-3 px-3 text-xs font-bold text-gray-600 uppercase">No Absen</th>
                                <th class="py-3 px-3 text-xs font-bold text-gray-600 uppercase">Nama</th>
                                <th class="py-3 px-3 text-xs font-bold text-gray-600 uppercase">Bagian</th>
                                <th class="py-3 px-3 text-xs font-bold text-gray-600 uppercase">Tarif / Jam</th>
                                <th class="py-3 px-3 text-xs font-bold text-gray-600 uppercase">Efektif</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php $no=1; $q=$koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan, lembur_per_jam FROM ms_karyawan WHERE status_karyawan='Aktif' ORDER BY no_absen"); while($r=$q->fetch_assoc()): $eff=$r['lembur_per_jam']!==null ? $r['lembur_per_jam'] : $lemburperjam; ?>
                            <tr>
                                <td class="py-2 px-3 text-sm"><?= $no++ ?></td>
                                <td class="py-2 px-3 text-sm font-mono"><?= htmlspecialchars($r['no_absen']) ?></td>
                                <td class="py-2 px-3 text-sm font-semibold"><?= htmlspecialchars($r['nama_karyawan']) ?></td>
                                <td class="py-2 px-3 text-sm text-gray-500"><?= $r['lembur_per_jam']===null ? '<span class="text-xs bg-gray-100 px-2 py-1 rounded">Global</span>' : '<span class="text-xs bg-violet-100 text-violet-700 px-2 py-1 rounded">Custom</span>' ?></td>
                                <td class="py-2 px-3">
                                    <div class="relative max-w-[180px]">
                                        <span class="absolute left-2 top-2 text-xs text-gray-400">Rp</span>
                                        <input type="number" name="karyawan[<?= $r['id_karyawan'] ?>]" value="<?= $r['lembur_per_jam']!==null ? htmlspecialchars($r['lembur_per_jam']) : '' ?>" placeholder="<?= $lemburperjam ?>" class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-violet-500"/>
                                    </div>
                                </td>
                                <td class="py-2 px-3 text-sm font-bold text-indigo-600">Rp <?= number_format($eff,0,',','.') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end mt-6 pt-4 border-t">
                    <button type="submit" name="simpan_perkaryawan" class="inline-flex items-center px-6 py-2.5 text-sm font-bold rounded-xl shadow-md text-white bg-violet-600 hover:bg-violet-700"><i class="fas fa-save mr-2"></i> Simpan Per Karyawan</button>
                </div>
            </div>
        </form>
    </div>
</div>
