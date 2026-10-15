<?php
include_once __DIR__ . '/../../device_sync_helper.php';
create_sync_log_table($koneksi);

$role_user = strtolower($_SESSION['role'] ?? '');
$is_authorized_delete = ($role_user == "admin master" || $role_user == "kepala pabrik" || $role_user == "owner");
if (!$is_authorized_delete) {
    echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>document.addEventListener('DOMContentLoaded', function(){Swal.fire({title:'Akses Ditolak!',text:'Anda tidak memiliki izin untuk hapus massal.',icon:'error',confirmButtonColor:'#3b82f6'}).then(()=>{window.location.href='?page=karyawan';});});</script></body></html>";
    exit;
}

if (isset($_POST['proses_hapus'])) {
    if (!empty($_POST['id_karyawan_pilih'])) {
        $ids = $_POST['id_karyawan_pilih'];
        $count = 0; $fail=0; $mesin_ok=0; $mesin_gagal=0;
        $mesin_gagal_list = [];
        foreach ($ids as $id) {
            $id = intval($id);
            $emp = $koneksi->query("SELECT no_absen, nama_karyawan FROM ms_karyawan WHERE id_karyawan=$id LIMIT 1");
            $row = $emp ? $emp->fetch_assoc() : null;
            $no_absen = trim($row['no_absen'] ?? '');
            $nama_del = $row['nama_karyawan'] ?? '';
            if (!empty($nama_del)) {
                // Hapus beneran dari mesin BY NAMA (PIN tidak dipakai).
                // PIN-sharing tetap di-skip otomatis. Loop luar hanya ID yang dicentang.
                $delRes = delete_karyawan_from_devices($koneksi, $nama_del, false);
                foreach($delRes as $did=>$dr){
                    if($did==='_dry_run_mode' || $did==='_dry_run_info') continue;
                    if(!isset($dr['status'])) continue;
                    log_sync_activity($koneksi,'karyawan_bulk_delete',$did,$id,$dr['status'],"Hapus $nama_del (PIN:$no_absen): ".$dr['message']);
                    if($dr['status']=='success') $mesin_ok++;
                    elseif($dr['status']=='error'){ $mesin_gagal++; $mesin_gagal_list[] = "$nama_del ($no_absen) @ ".($dr['device']??$did); }
                }
            }
            if ($koneksi->query("DELETE FROM ms_karyawan WHERE id_karyawan=$id")) { hapus_karyawan_dari_grup($koneksi, $id); $count++; } else $fail++;
        }
        $msgJs = json_encode("$count karyawan dihapus dari web." . ($fail>0 ? " $fail gagal." : "") . " Mesin: $mesin_ok berhasil" . ($mesin_gagal>0 ? ", $mesin_gagal gagal." : "."));
        $gagalJs = !empty($mesin_gagal_list) ? json_encode("<br><br><small>Gagal di mesin:<br>".implode("<br>", array_map('htmlspecialchars', array_slice($mesin_gagal_list,0,10)))."</small>") : "''";
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({title:'Berhasil!',html:$msgJs + $gagalJs,icon:'success',confirmButtonColor:'#e11d48'}).then(()=>{window.location.href='?page=karyawan&aksi=hapus_massal';});});</script>";
    }
}

$tampil = $koneksi->query("SELECT k.*, d.nama_departmen FROM ms_karyawan k LEFT JOIN ms_departmen d ON k.id_departmen=d.id_departmen ORDER BY k.nama_karyawan ASC");
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <form method="POST" id="formHapusMassal">
        <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
            <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
                <div>
                    <h3 class="text-xl font-bold text-rose-600 m-0"><i class="fas fa-trash-alt mr-2"></i>Hapus Karyawan Massal</h3>
                    <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Centang beberapa karyawan untuk hapus sekaligus (auto hapus dari 4 mesin)</p>
                </div>
                <div class="flex gap-2 w-full md:w-auto items-center">
                    <span id="selectedCount" class="text-sm font-semibold text-gray-600 px-3 py-1 bg-gray-100 rounded-full">0 terpilih</span>
                    <a href="?page=karyawan" class="flex-1 md:flex-none justify-center inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-[15px] font-medium py-2 px-4 rounded shadow-sm"><i class="fas fa-arrow-left mr-1.5"></i> Kembali</a>
                    <button type="button" onclick="konfirmasiHapus()" class="flex-1 md:flex-none justify-center border-0 inline-flex items-center bg-rose-600 hover:bg-rose-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm"><i class="fas fa-trash mr-1.5"></i> Hapus Terpilih</button>
                    <input type="hidden" name="proses_hapus" value="1">
                </div>
            </div>
            <div class="p-0">
                <div class="table-responsive px-3 py-3">
                    <table class="w-full text-left border-collapse table-modern" id="dataTables-hapus">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="py-2 px-2 text-center w-12"><input type="checkbox" id="select_all" class="w-4 h-4 text-rose-600 rounded cursor-pointer"></th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">No</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">No. Absen</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">Nama Karyawan</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase">Bagian</th>
                                <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php $no=1; if($tampil && $tampil->num_rows>0): while($d=$tampil->fetch_assoc()): ?>
                            <tr class="hover:bg-rose-50 transition-colors cursor-pointer" onclick="toggleCheckbox(<?= $d['id_karyawan'] ?>)">
                                <td class="py-2 px-2 text-center" onclick="event.stopPropagation()"><input type="checkbox" name="id_karyawan_pilih[]" value="<?= $d['id_karyawan'] ?>" id="check_<?= $d['id_karyawan'] ?>" class="check_item w-4 h-4 text-rose-600 rounded cursor-pointer"></td>
                                <td class="py-2 px-2 text-center text-sm text-gray-600"><?= $no++ ?></td>
                                <td class="py-2 px-2 text-sm font-semibold text-gray-700"><?= htmlspecialchars($d['no_absen']) ?></td>
                                <td class="py-2 px-2 text-sm font-bold text-gray-900"><?= htmlspecialchars($d['nama_karyawan']) ?></td>
                                <td class="py-2 px-2 text-sm text-gray-600"><?= htmlspecialchars($d['nama_departmen'] ?? '-') ?></td>
                                <td class="py-2 px-2 text-center"><span class="px-3 py-1 text-xs font-semibold rounded-full <?= $d['status_karyawan']=='Aktif'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= $d['status_karyawan'] ?></span></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
#dataTables-hapus_wrapper .row:first-child{display:flex!important;justify-content:space-between!important;align-items:center!important;flex-wrap:wrap!important;gap:15px!important;margin-bottom:15px!important}
#dataTables-hapus_wrapper .row:last-child{display:flex!important;justify-content:space-between!important;align-items:center!important;flex-wrap:wrap!important;gap:15px!important;margin-top:15px!important;padding:0 5px!important}
#dataTables-hapus_wrapper .dataTables_paginate{display:flex!important;gap:4px!important;align-items:center!important}
#dataTables-hapus_wrapper .dataTables_paginate .paginate_button{border:1px solid #e2e8f0!important;background:white!important;border-radius:6px!important;padding:5px 12px!important;color:#475569!important}
#dataTables-hapus_wrapper .dataTables_paginate .paginate_button.current{background:#e11d48!important;color:white!important;border-color:#e11d48!important}
#dataTables-hapus_wrapper .dataTables_info{color:#64748b!important;font-size:13px!important}
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function updateCount(){
    const c=document.querySelectorAll('.check_item:checked').length;
    const el=document.getElementById('selectedCount');
    el.textContent=c+' terpilih';
    el.className=c>0?'text-sm font-semibold text-rose-600 px-3 py-1 bg-rose-100 rounded-full':'text-sm font-semibold text-gray-600 px-3 py-1 bg-gray-100 rounded-full';
}
$(document).ready(function(){
        var $tbl = $('#dataTables-hapus');
        var $rows = $tbl.find('tbody tr');
        var complete = $rows.length > 0;
        $rows.each(function() {
            if ($(this).find('td').length < 6) { complete = false; return false; }
        });
        if (complete) $tbl.DataTable({pageLength:25,autoWidth:false,responsive:false,lengthMenu:[[10,25,50,-1],[10,25,50,"Semua"]],language:{search:"Cari Data:",searchPlaceholder:"Ketik pencarian...",lengthMenu:"Tampilkan _MENU_ data",info:"Menampilkan _START_ s/d _END_ dari _TOTAL_ data",emptyTable:"Data tidak tersedia",paginate:{previous:"Prev",next:"Next"}},columnDefs:[{targets:0,orderable:false},{targets:'_all',defaultContent:''}]});
    document.addEventListener('change',function(e){if(e.target.classList.contains('check_item')) updateCount();});
    updateCount();
});
function konfirmasiHapus(){
    const c=document.querySelectorAll('.check_item:checked').length;
    if(c===0){Swal.fire({title:'Pilih Data!',text:'Centang karyawan yang ingin dihapus dulu.',icon:'warning',confirmButtonColor:'#3b82f6'});return;}
    const ids=[...document.querySelectorAll('.check_item:checked')].map(cb=>cb.value);
    Swal.fire({title:'Mengecek mesin...',text:`Mencari user ${c} karyawan di mesin...`,allowOutsideClick:false,showConfirmButton:false,showCancelButton:false,didOpen:()=>Swal.showLoading()});
    $.post('page/karyawan/ajax_hapus.php',{action:'preview_bulk',ids:ids},function(pv){
        if(!pv.success){Swal.fire({title:'Gagal',text:pv.msg,icon:'warning'});return;}
        let html='<div class="text-left text-sm max-h-72 overflow-y-auto">';
        (pv.employees||[]).forEach(emp=>{
            html+=`<div class="p-2 border rounded mb-2"><b>${emp.nama}</b> (No. Absen ${emp.no_absen}) — cocok: <b>${emp.total}</b><br>`;
            for(const did in (emp.devices||{})){
                const d=emp.devices[did];
                if(!d.online || !(d.matches||[]).length) continue;
                d.matches.forEach(m=>{
                    html+=`<label class="flex items-center gap-2 mt-1 text-sm cursor-pointer"><input type="checkbox" class="swBulkTarget" data-kid="${emp.id}" value="${did}:${m.pin}:${m.pin2||''}" checked> <span>${d.device}: <b>${m.name}</b> PIN ${m.pin}${m.pin2?' / PIN2 '+m.pin2:''}</span></label>`;
                });
            }
            html+='</div>';
        });
        html+='</div>';
        Swal.fire({title:'Pilih User Mesin yang Dihapus',html:html,showDenyButton:true,showCancelButton:true,confirmButtonText:'Hapus yang Dipilih',denyButtonText:'DB Saja',cancelButtonText:'Batal',confirmButtonColor:'#e11d48',width:'42em'}).then((rr)=>{
            if(rr.dismiss && rr.dismiss!==Swal.DismissReason.deny) return;
            const isDbOnly=rr.isDenied;
            const items={};
            if(!isDbOnly){
                document.querySelectorAll('.swBulkTarget:checked').forEach(cb=>{const kid=cb.getAttribute('data-kid');(items[kid]=items[kid]||[]).push(cb.value);});
                if(!Object.keys(items).length){Swal.fire({title:'Pilih Dulu',text:'Centang minimal 1 user mesin, atau pilih DB Saja.',icon:'warning'});return;}
            } else {
                (pv.employees||[]).forEach(emp=>{items[emp.id]=[];});
            }
            Swal.fire({title:'Menghapus...',allowOutsideClick:false,showConfirmButton:false,showCancelButton:false,didOpen:()=>Swal.showLoading()});
            $.post('page/karyawan/ajax_hapus.php',{action:'exec_bulk',items:items,db_only:isDbOnly?1:0},function(ex){
                let h='<div class="text-left text-sm max-h-72 overflow-y-auto">';
                (ex.per||[]).forEach(p=>{
                    let ok=0,fail=0;
                    for(const did in (p.results||{})){const x=p.results[did];if(!x.device)continue;if(x.status==='success')ok++;else if(x.status==='error')fail++;}
                    h+=`<div class="p-2 border rounded mb-1"><b>${p.nama}</b> — DB: ${p.db_deleted?'terhapus':'GAGAL'} | mesin: ${ok} sukses${fail?`, <span class="text-red-600">${fail} gagal</span>`:''}</div>`;
                });
                h+='</div>';
                Swal.fire({title:'Selesai Hapus Massal',html:h,icon:'success',confirmButtonColor:'#e11d48'}).then(()=>{window.location.href='?page=karyawan&aksi=hapus_massal';});
            },'json');
        });
    },'json');
}
document.getElementById('select_all').addEventListener('change',function(){document.querySelectorAll('.check_item').forEach(cb=>cb.checked=this.checked); updateCount();});
function toggleCheckbox(id){const cb=document.getElementById('check_'+id);if(cb){cb.checked=!cb.checked; updateCount();}}
</script>
