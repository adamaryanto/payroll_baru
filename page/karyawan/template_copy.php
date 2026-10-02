<?php
// Include sync helper
include_once __DIR__ . '/../../device_sync_helper.php';
create_sync_log_table($koneksi);

$devices = get_all_devices($koneksi);
// Pad to 4 like mesin.php
for($i=count($devices); $i<4; $i++) $devices[]=['id_mesin'=>'','no_mesin'=>$i+1,'nama_mesin'=>'Mesin '.($i+1),'ip_mesin'=>'','comm_key'=>'0','port'=>'4370'];
$devices = array_values($devices);
$karyawan_list = [];
$copy_results = [];

// Get all active karyawan
$karyawan_result = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan = 'Aktif' AND no_absen != '' ORDER BY nama_karyawan");
while($row = $karyawan_result->fetch_assoc()) {
    $karyawan_list[] = $row;
}

// Handle single SYNC + COPY (1 tombol)
$sync_copy_results = null;
if(isset($_POST['sync_and_copy'])) {
    $karyawan_id = $_POST['karyawan_id'];
    $source_device_id = $_POST['source_device_id'];
    $target_device_ids = $_POST['target_device_ids'] ?? [];
    $karyawan_data = null;
    foreach($karyawan_list as $k) if($k['id_karyawan']==$karyawan_id) $karyawan_data=$k;
    if($karyawan_data && !empty($target_device_ids)) {
        $target_device_ids = array_values(array_filter($target_device_ids, fn($id)=>$id!=$source_device_id));
        if(empty($target_device_ids)) {
            $sync_copy_results = ['error'=>'Pilih mesin tujuan yang berbeda dari sumber'];
        } else {
            $sync_res = sync_karyawan_to_devices($koneksi, $karyawan_id);
            $copy_res = copy_fingerprint_template($koneksi, $karyawan_data['no_absen'], $source_device_id, $target_device_ids);
            $sync_copy_results = ['sync'=>$sync_res, 'copy'=>$copy_res, 'karyawan'=>$karyawan_data];
            foreach($target_device_ids as $tid){
                $s = $sync_res[$tid]['status'] ?? 'error';
                $c = $copy_res['results'][$tid]['status'] ?? ($copy_res['error'] ?? 'error');
                log_sync_activity($koneksi,'sync_and_copy',$tid,$karyawan_id, ($s==='success' && $c==='success'?'success':'warning'), "sync:$s copy:".($copy_res['results'][$tid]['message'] ?? $copy_res['error'] ?? ''));
            }
        }
    }
}

// Handle single template copy request
if(isset($_POST['copy_template'])) {
    $karyawan_id = $_POST['karyawan_id'];
    $source_device_id = $_POST['source_device_id'];
    $target_device_ids = $_POST['target_device_ids'] ?? [];
    
    $karyawan_data = null;
    foreach($karyawan_list as $k) {
        if($k['id_karyawan'] == $karyawan_id) {
            $karyawan_data = $k;
            break;
        }
    }
    
    if($karyawan_data && !empty($target_device_ids)) {
        $target_device_ids = array_values(array_filter($target_device_ids, fn($id) => $id != $source_device_id));
        if(empty($target_device_ids)) {
            $copy_results = ['error' => 'Pilih mesin tujuan yang berbeda dari mesin sumber'];
        } else {
            $copy_results = copy_fingerprint_template(
                $koneksi, 
                $karyawan_data['no_absen'], 
                $source_device_id, 
                $target_device_ids
            );
        }
        
        if(isset($copy_results['error'])) {
            foreach($target_device_ids as $device_id) {
                log_sync_activity($koneksi, 'template_copy', $device_id, $karyawan_id, 'error', $copy_results['error']);
            }
        } else {
            foreach($target_device_ids as $device_id) {
                log_sync_activity($koneksi, 'template_copy', $device_id, $karyawan_id, 
                                 $copy_results['results'][$device_id]['status'] ?? 'error',
                                 $copy_results['results'][$device_id]['message'] ?? 'Template copy operation');
            }
        }
    }
}

// Handle BULK template copy request
$bulk_results = null;
if(isset($_POST['bulk_copy'])) {
    $bulk_source_device_id = $_POST['bulk_source_device_id'];
    $bulk_target_device_ids = $_POST['bulk_target_device_ids'] ?? [];
    
    if(!empty($bulk_source_device_id) && !empty($bulk_target_device_ids)) {
        $bulk_results = [
            'source_device_id' => $bulk_source_device_id,
            'per_karyawan' => [],
            'summary' => ['total' => count($karyawan_list), 'success' => 0, 'no_template' => 0, 'failed' => 0]
        ];
        $src_device = array_filter($devices, fn($d) => $d['id_mesin'] == $bulk_source_device_id);
        $src_device = reset($src_device);
        $bulk_results['source_name'] = $src_device['nama_mesin'] ?? 'Unknown';
        $src_online = $src_device && !empty($src_device['ip_mesin']) ? test_device_connection($src_device['ip_mesin'],$src_device['port']) : false;
        if(!$src_online){
            $bulk_results['error'] = 'Mesin sumber OFFLINE / tidak dapat dihubungi — bulk dibatalkan. Cek IP/kabel LAN di page=mesin.';
            $bulk_results['per_karyawan']=[];
            foreach($karyawan_list as $k) foreach($bulk_target_device_ids as $tid) log_sync_activity($koneksi,'template_bulk_copy',$tid,$k['id_karyawan'],'error','source offline');
        } else {
        foreach($karyawan_list as $k) {
            $res = copy_fingerprint_template($koneksi, $k['no_absen'], $bulk_source_device_id, $bulk_target_device_ids);
            $bulk_results['per_karyawan'][] = [
                'karyawan' => $k,
                'result' => $res
            ];
            if(isset($res['error'])) {
                if(strpos($res['error'], 'No fingerprint template') !== false) $bulk_results['summary']['no_template']++;
                else $bulk_results['summary']['failed']++;
            } else {
                $all_ok = true;
                foreach($res['results'] ?? [] as $r) if($r['status'] !== 'success') $all_ok = false;
                if($all_ok && !empty($res['template_found'])) $bulk_results['summary']['success']++;
                else if(empty($res['template_found'])) $bulk_results['summary']['no_template']++;
                else $bulk_results['summary']['failed']++;
            }
            foreach($bulk_target_device_ids as $tid) {
                $st = $res['results'][$tid]['status'] ?? 'error';
                if(isset($res['error'])) $st = 'error';
                $msg = $res['results'][$tid]['message'] ?? ($res['error'] ?? 'bulk copy');
                log_sync_activity($koneksi, 'template_bulk_copy', $tid, $k['id_karyawan'], $st, $msg);
            }
        }
        }
    }
}
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl font-bold text-indigo-600 m-0">
                    <i class="fas fa-fingerprint mr-2"></i>Copy Sidik Jari antar Mesin
                </h3>
                <p class="text-[12px] text-gray-400 mt-0.5 mb-0">Salin template sidik jari dari satu mesin ke mesin lainnya</p>
            </div>
            <div class="w-full md:w-auto mt-2 md:mt-0">
                <a href="?page=karyawan" class="flex md:inline-flex justify-center items-center bg-gray-600 hover:bg-gray-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors w-full md:w-auto">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
            </div>
        </div>

        <div class="p-6">
            <?php if(!empty($copy_results)): ?>
            <div class="mb-8 p-5 rounded-xl border <?php echo isset($copy_results['error']) ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50'; ?>">
                <h4 class="text-lg font-semibold <?php echo isset($copy_results['error']) ? 'text-red-800' : 'text-green-800'; ?> mb-3">
                    <i class="fas fa-<?php echo isset($copy_results['error']) ? 'exclamation-circle' : 'check-circle'; ?> mr-2"></i>
                    Hasil Copy Template
                </h4>
                
                <?php if(isset($copy_results['error'])): ?>
                <p class="text-red-600"><?php echo htmlspecialchars($copy_results['error']); ?></p>
                <?php else: ?>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">
                        Sumber: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($copy_results['source_device']); ?></span> | 
                        Template ditemukan: <span class="font-semibold <?php echo $copy_results['template_found'] ? 'text-green-600' : 'text-amber-600'; ?>">
                            <?php echo $copy_results['template_found'] ? 'YA' : 'TIDAK'; ?>
                        </span>
                    </p>
                    
                    <?php foreach($copy_results['results'] as $device_id => $result): 
                        $device = array_filter($devices, function($d) use ($device_id) {
                            return $d['id_mesin'] == $device_id;
                        });
                        $device = reset($device);
                    ?>
                    <div class="p-3 rounded-lg border <?php echo $result['status'] == 'success' ? 'border-green-200 bg-white' : 'border-red-200 bg-white'; ?>">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm <?php echo $result['status'] == 'success' ? 'text-gray-800' : 'text-red-800'; ?>">
                                    <?php echo htmlspecialchars($device['nama_mesin'] ?? 'Unknown Device'); ?>
                                </p>
                                <p class="text-sm <?php echo $result['status'] == 'success' ? 'text-gray-600' : 'text-red-600'; ?> mt-0.5">
                                    <?php echo htmlspecialchars($result['message']); ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $result['status'] == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo strtoupper($result['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if(!empty($sync_copy_results)): ?>
            <div class="mb-8 p-5 rounded-xl border <?php echo isset($sync_copy_results['error']) ? 'border-red-200 bg-red-50' : 'border-blue-200 bg-blue-50'; ?>">
                <h4 class="text-lg font-semibold <?php echo isset($sync_copy_results['error']) ? 'text-red-800' : 'text-blue-800'; ?> mb-3">
                    <i class="fas fa-sync-alt mr-2"></i>Hasil Sync & Copy
                </h4>
                <?php if(isset($sync_copy_results['error'])): ?>
                    <p class="text-red-600"><?php echo htmlspecialchars($sync_copy_results['error']); ?></p>
                <?php else: ?>
                    <p class="text-sm text-gray-600 mb-3">Karyawan: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($sync_copy_results['karyawan']['nama_karyawan']); ?></span> (PIN <?php echo $sync_copy_results['karyawan']['no_absen']; ?>)</p>
                    <div class="space-y-3">
                        <?php foreach($sync_copy_results['copy']['results'] ?? [] as $did=>$r):
                            $syncSt = $sync_copy_results['sync'][$did]['status'] ?? 'unknown';
                            $syncMsg = $sync_copy_results['sync'][$did]['message'] ?? '';
                        ?>
                        <div class="p-3 rounded-lg border <?php echo $r['status']==='success' && $syncSt==='success'?'border-green-200 bg-white':'border-amber-200 bg-white'; ?>">
                            <div class="flex items-center justify-between mb-1">
                                <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($r['device']); ?></p>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?php echo ($r['status']==='success' && $syncSt==='success')?'bg-green-100 text-green-800':'bg-amber-100 text-amber-800'; ?>">
                                    <?php echo ($r['status']==='success' && $syncSt==='success')?'BERHASIL':'PERINGATAN'; ?>
                                </span>
                            </div>
                            <p class="text-xs <?php echo $syncSt==='success'?'text-green-600':'text-red-600'; ?>">Sync: <?php echo htmlspecialchars($syncSt.' - '.$syncMsg); ?></p>
                            <p class="text-xs <?php echo $r['status']==='success'?'text-green-600':'text-red-600'; ?>">Copy: <?php echo htmlspecialchars($r['status'].' - '.$r['message']); ?></p>
                        </div>
                        <?php endforeach; ?>
                        <?php if(isset($sync_copy_results['copy']['error'])): ?><p class="text-red-600 text-sm"><?php echo htmlspecialchars($sync_copy_results['copy']['error']); ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Enroll Baru + Auto-sebar -->
            <div class="mb-8" id="enroll-auto">
                <div class="p-6 bg-gradient-to-r from-teal-50 to-emerald-50 rounded-xl border border-teal-200">
                    <h4 class="text-lg font-semibold text-teal-800 mb-1"><i class="fas fa-bolt mr-2"></i>Enroll Baru — Otomatis Tersebar</h4>
                    <p class="text-sm text-gray-600 mb-4">Pilih karyawan + 1 mesin master → klik Mulai → tempel jari di mesin itu → web memantau tiap 5 detik, begitu sidik jadi langsung disebar ke semua mesin online. Maksimal tunggu 3 menit.</p>
                    <?php $pre_enroll = intval($_GET['enroll_id'] ?? 0); ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Karyawan</label>
                            <select id="enroll_karyawan" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
                                <option value="">- Pilih Karyawan -</option>
                                <?php foreach($karyawan_list as $k): ?>
                                <option value="<?= $k['id_karyawan'] ?>" <?= $pre_enroll == $k['id_karyawan'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_karyawan']) ?> (<?= htmlspecialchars($k['no_absen']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mesin Master (tempat tempel jari)</label>
                            <select id="enroll_master" class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-sm">
                                <option value="">- Pilih Mesin -</option>
                                <?php foreach($devices as $d): ?>
                                <option value="<?= $d['id_mesin'] ?>" <?= empty($d['ip_mesin']) ? 'disabled' : '' ?>><?= htmlspecialchars($d['nama_mesin']) ?> (<?= htmlspecialchars($d['ip_mesin'] ?: '-') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="button" id="btnEnrollStart" class="flex-1 bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 px-4 rounded-lg text-sm"><i class="fas fa-fingerprint mr-2"></i>Mulai Enroll</button>
                            <button type="button" id="btnEnrollStop" class="hidden bg-gray-400 hover:bg-gray-500 text-white font-semibold py-2.5 px-4 rounded-lg text-sm">Stop</button>
                        </div>
                    </div>
                    <div id="enroll_status" class="mt-4 hidden p-4 bg-white rounded-lg border text-sm"></div>
                </div>
            </div>
            <script>
            (function(){
                let pollTimer=null, pollCount=0, base=[], pin='', masterId='', masterName='';
                const statusBox=document.getElementById('enroll_status');
                function show(html){ statusBox.classList.remove('hidden'); statusBox.innerHTML=html; }
                function stop(msg){
                    if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
                    document.getElementById('btnEnrollStop').classList.add('hidden');
                    if(msg) show(msg);
                }
                document.getElementById('btnEnrollStop').addEventListener('click',()=>stop('<p class="text-gray-500">Dipantau berhenti. Sidik yang sudah jadi bisa disebar pakai Copy di bawah.</p>'));
                document.getElementById('btnEnrollStart').addEventListener('click',()=>{
                    const kid=document.getElementById('enroll_karyawan').value;
                    masterId=document.getElementById('enroll_master').value;
                    if(!kid||!masterId){ alert('Pilih karyawan dan mesin master dulu'); return; }
                    show('<p class="text-indigo-600"><i class="fas fa-spinner fa-spin mr-2"></i>Menyiapkan mesin enroll...</p>');
                    document.getElementById('btnEnrollStop').classList.remove('hidden');
                    $.post('page/karyawan/ajax_enroll_auto.php',{action:'start',karyawan_id:kid,master_id:masterId},function(res){
                        if(!res.success){ stop('<p class="text-red-600">'+res.msg+'</p>'); return; }
                        pin=res.pin; base=res.baseline||[]; masterName=res.master;
                        pollCount=0;
                        show('<p class="text-teal-700 font-semibold">'+res.msg+'</p><p class="text-xs text-gray-500 mt-1">Sidik sekarang: '+(base.length?base.join(', '):'belum ada')+' — memantau… <span id="enroll_tick"></span></p><div id="enroll_result" class="mt-2"></div>');
                        pollTimer=setInterval(pollTick,5000);
                    },'json').fail(()=>stop('<p class="text-red-600">Gagal hubungi server.</p>'));
                });
                function pollTick(){
                    pollCount++;
                    const tick=document.getElementById('enroll_tick');
                    if(tick) tick.textContent='(cek ke-'+pollCount+' / 36)';
                    if(pollCount>36){ stop('<p class="text-amber-600">Waktu habis (3 menit). Kalau sudah tempel jari, pakai Copy Per Karyawan di bawah. Kalau belum, ulangi dari awal.</p>'); return; }
                    $.post('page/karyawan/ajax_enroll_auto.php',{action:'poll',pin:pin,master_id:masterId,baseline:base},function(res){
                        if(!res.success){ stop('<p class="text-red-600">'+res.msg+'</p>'); return; }
                        if(res.done){
                            clearInterval(pollTimer); pollTimer=null;
                            const box=document.getElementById('enroll_result');
                            if(box) box.innerHTML='<p class="text-green-700 font-semibold">Sidik baru terdeteksi (jari: '+res.new.join(', ')+') — menyebar ke mesin lain...</p>';
                            $.post('page/karyawan/ajax_enroll_auto.php',{action:'spread',pin:pin,master_id:masterId},function(sr){
                                let html='<div class="mt-2 space-y-1">';
                                if(sr.success){
                                    for(const did in (sr.results||{})){
                                        const r=sr.results[did];
                                        const cls=r.status==='success'?'border-green-200 bg-green-50':(r.status==='warning'?'border-amber-200 bg-amber-50':'border-gray-200 bg-gray-50');
                                        html+='<div class="p-2 border rounded text-xs '+cls+'"><b>'+r.device+'</b>: '+r.message+'</div>';
                                    }
                                } else html+='<p class="text-red-600">'+sr.msg+'</p>';
                                html+='</div>';
                                if(box) box.innerHTML+=html;
                                document.getElementById('btnEnrollStop').classList.add('hidden');
                            },'json');
                        }
                    },'json');
                }
            })();
            </script>
            <!-- Tabs -->
            <div class="flex border-b mb-6">
                <button type="button" onclick="showCopyTab('single')" id="copy-tab-single" class="px-4 py-2 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600">Per Karyawan</button>
                <button type="button" onclick="showCopyTab('bulk')" id="copy-tab-bulk" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500">Pilih Semua Karyawan</button>
            </div>
            <!-- Template Copy Form -->
            <div id="copy-panel-single" class="mb-8">
                <div class="p-6 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-xl border border-indigo-100">
                    <h4 class="text-lg font-semibold text-indigo-800 mb-2">Salin Sidik Jari — Per Karyawan</h4>
                    <p class="text-gray-600 mb-6">
                        Pilih karyawan, mesin sumber (dimana sidik jari sudah direkam), dan mesin tujuan untuk menyalin template sidik jari.
                    </p>
                    
                    <form method="POST" id="templateCopyForm">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-5">
                            <!-- Karyawan Selection -->
                            <div class="space-y-3">
                                <label class="block text-sm font-semibold text-gray-700">Pilih Karyawan</label>
                                <select name="karyawan_id" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm">
                                    <option value="">- Pilih Karyawan -</option>
                                    <?php foreach($karyawan_list as $karyawan): ?>
                                    <option value="<?php echo $karyawan['id_karyawan']; ?>">
                                        <?php echo htmlspecialchars($karyawan['nama_karyawan']); ?> (No. Absen: <?php echo $karyawan['no_absen']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Source Device -->
                            <div class="space-y-3">
                                <label class="block text-sm font-semibold text-gray-700">Mesin Sumber</label>
                                <select name="source_device_id" id="source_device_id" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm" onchange="syncSourceTarget()">
                                    <option value="">- Pilih Mesin Sumber -</option>
                                    <?php foreach($devices as $device): 
                                        $has_ip = !empty($device['ip_mesin']);
                                        $is_online = $has_ip ? test_device_connection($device['ip_mesin'], $device['port']) : false;
                                    ?>
                                    <option value="<?php echo $device['id_mesin']; ?>" <?php echo !$has_ip ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($device['nama_mesin']); ?> 
                                        (<?php echo $device['ip_mesin'] ?: 'IP belum diisi'; ?>)
                                        <?php echo !$has_ip ? ' [IP KOSONG]' : (!$is_online ? ' [OFFLINE]' : ' [ONLINE]'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-500">Pilih mesin dimana sidik jari sudah direkam.</p>
                            </div>
                            
                            <!-- Target Devices -->
                            <div class="space-y-3">
                                <label class="block text-sm font-semibold text-gray-700">Mesin Tujuan</label>
                                <div class="space-y-2">
                                    <?php foreach($devices as $device): 
                                        $has_ip = !empty($device['ip_mesin']);
                                        $is_online = $has_ip ? test_device_connection($device['ip_mesin'], $device['port']) : false;
                                    ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               name="target_device_ids[]" 
                                               value="<?php echo $device['id_mesin']; ?>"
                                               id="device_<?php echo $device['id_mesin']; ?>"
                                               class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                               <?php echo !$has_ip ? 'disabled' : ''; ?>>
                                        <label for="device_<?php echo $device['id_mesin']; ?>" 
                                               class="ml-2 text-sm <?php echo !$has_ip ? 'text-gray-400' : 'text-gray-700'; ?>">
                                            <?php echo htmlspecialchars($device['nama_mesin']); ?> 
                                            (<?php echo $device['ip_mesin'] ?: 'IP belum diisi'; ?>)
                                            <?php if(!$has_ip) echo ' [IP KOSONG]'; elseif(!$is_online) echo ' <span class="text-amber-600">[OFFLINE]</span>'; else echo ' <span class="text-green-600">[ONLINE]</span>'; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-xs text-gray-500">Pilih mesin tujuan untuk menyalin template.</p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end">
                            <div class="flex gap-3">
                                <button type="submit" name="copy_template" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                                    <i class="fas fa-copy"></i>
                                    Copy
                                </button>
                            </div>
                        </div>
                        
                    </form>
                </div>
            </div>

            <!-- BULK Copy Section -->
            <div id="copy-panel-bulk" class="mb-8" style="display:none;">
                <div class="p-6 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl border border-purple-100">
                    <h4 class="text-lg font-semibold text-purple-800 mb-2 flex items-center"><i class="fas fa-layer-group mr-2"></i>Pilih Semua Karyawan — Tarik Semua Template</h4>
                    <p class="text-gray-600 mb-4 text-sm">Kalau fingerprint sudah ada semua di 1 mesin (misal Mesin 1), pakai ini untuk sebar sekaligus ke mesin lain. Sistem akan loop semua karyawan aktif.</p>
                    
                    <?php if($bulk_results): ?>
                    <div class="mb-4 p-4 bg-white rounded-lg border">
                        <p class="font-semibold text-gray-800">Hasil Bulk dari <?php echo htmlspecialchars($bulk_results['source_name']); ?> → <?php echo count($_POST['bulk_target_device_ids'] ?? []); ?> mesin tujuan</p>
                        <p class="text-sm text-gray-600 mt-1">Total karyawan: <?php echo $bulk_results['summary']['total']; ?> | <span class="text-green-600 font-bold">Sukses: <?php echo $bulk_results['summary']['success']; ?></span> | <span class="text-amber-600">Belum ada template: <?php echo $bulk_results['summary']['no_template']; ?></span> | <span class="text-red-600">Gagal: <?php echo $bulk_results['summary']['failed']; ?></span></p>
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm text-indigo-600 font-medium">Lihat detail per karyawan</summary>
                            <div class="mt-2 max-h-64 overflow-y-auto text-xs space-y-1">
                                <?php foreach($bulk_results['per_karyawan'] as $item): ?>
                                <div class="flex justify-between border-b py-1">
                                    <span><?php echo htmlspecialchars($item['karyawan']['nama_karyawan']); ?> (<?php echo $item['karyawan']['no_absen']; ?>)</span>
                                    <span class="<?php echo isset($item['result']['error']) ? 'text-red-600' : (empty($item['result']['template_found']) ? 'text-amber-600' : 'text-green-600'); ?>"><?php echo htmlspecialchars($item['result']['error'] ?? (empty($item['result']['template_found']) ? 'No template' : 'OK')); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </div>
                    <?php endif; ?>

                    <form method="POST" id="bulkCopyForm" onsubmit="return confirm('Apakah Anda yakin ingin menyalin semua template sidik jari dari mesin sumber ke mesin tujuan? Proses ini memerlukan waktu beberapa menit untuk ±200 karyawan. Lanjutkan?');">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                            <div class="space-y-3">
                                <label class="block text-sm font-semibold text-gray-700">Mesin Sumber (yang sudah ada fingerprint)</label>
                                <select name="bulk_source_device_id" id="bulk_source_device_id" required class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none text-sm" onchange="syncBulkSourceTarget()">
                                    <option value="">- Pilih Mesin Sumber -</option>
                                    <?php foreach($devices as $device): 
                                        $has_ip = !empty($device['ip_mesin']);
                                    ?>
                                    <option value="<?php echo $device['id_mesin']; ?>" <?php echo !$has_ip ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($device['nama_mesin']); ?> (<?php echo $device['ip_mesin'] ?: 'IP belum diisi'; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="space-y-3">
                                <label class="block text-sm font-semibold text-gray-700">Mesin Tujuan</label>
                                <div class="space-y-2">
                                    <?php foreach($devices as $device): 
                                        $has_ip = !empty($device['ip_mesin']);
                                    ?>
                                    <div class="flex items-center">
                                        <input type="checkbox" name="bulk_target_device_ids[]" value="<?php echo $device['id_mesin']; ?>" id="bulk_device_<?php echo $device['id_mesin']; ?>" class="h-4 w-4 text-purple-600 border-gray-300 rounded" <?php echo !$has_ip ? 'disabled' : ''; ?>>
                                        <label for="bulk_device_<?php echo $device['id_mesin']; ?>" class="ml-2 text-sm <?php echo !$has_ip ? 'text-gray-400' : 'text-gray-700'; ?>"><?php echo htmlspecialchars($device['nama_mesin']); ?> (<?php echo $device['ip_mesin'] ?: 'IP belum diisi'; ?>)</label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="bulk_copy" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                                <i class="fas fa-copy"></i> Copy Semua Template Sekaligus
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>

            <!-- Instructions -->
            <div class="mt-8 p-6 bg-amber-50 border border-amber-200 rounded-xl">
                <h4 class="text-lg font-semibold text-amber-800 mb-4">
                    <i class="fas fa-info-circle mr-2"></i> Cara Penggunaan
                </h4>
                <ol class="list-decimal pl-5 space-y-2 text-amber-700">
                    <li>Karyawan merekam sidik jarinya di <strong>satu mesin saja</strong> (misal Mesin 1)</li>
                    <li>Admin pilih karyawan, mesin sumber, dan mesin tujuan di form atas</li>
                    <li>Klik "Salin Template Sidik Jari" untuk menyalin ke mesin lain</li>
                    <li>Karyawan sekarang bisa absen di semua mesin yang sudah dicopy template-nya</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<script>
function syncSourceTarget(){
    const src = document.getElementById('source_device_id').value;
    document.querySelectorAll('input[name="target_device_ids[]"]').forEach(cb=>{
        const isSrc = cb.value === src;
        cb.disabled = isSrc || cb.dataset.origDisabled==='1';
        cb.checked = isSrc ? false : cb.checked;
        cb.closest('div').style.opacity = isSrc ? '0.4' : '1';
        const label = cb.nextElementSibling;
        if(isSrc) { if(!label.dataset.orig) label.dataset.orig=label.innerHTML; label.innerHTML = label.dataset.orig + ' <span class="text-red-600 text-xs">[Sumber - tidak bisa dipilih]</span>'; }
        else { if(label.dataset.orig) label.innerHTML=label.dataset.orig; }
    });
}
function syncBulkSourceTarget(){
    const src = document.getElementById('bulk_source_device_id').value;
    document.querySelectorAll('input[name="bulk_target_device_ids[]"]').forEach(cb=>{
        const isSrc = cb.value === src;
        cb.disabled = isSrc;
        cb.checked = isSrc ? false : cb.checked;
        cb.closest('div').style.opacity = isSrc ? '0.4' : '1';
    });
}
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('input[name="target_device_ids[]"], input[name="bulk_target_device_ids[]"]').forEach(cb=>cb.dataset.origDisabled=cb.disabled?'1':'0');
});
function showCopyTab(which){
    document.getElementById('copy-panel-single').style.display = which==='single'?'block':'none';
    document.getElementById('copy-panel-bulk').style.display = which==='bulk'?'block':'none';
    document.getElementById('copy-tab-single').className = which==='single'?'px-4 py-2 text-sm font-semibold border-b-2 border-indigo-600 text-indigo-600':'px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500';
    document.getElementById('copy-tab-bulk').className = which==='bulk'?'px-4 py-2 text-sm font-semibold border-b-2 border-purple-600 text-purple-600':'px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500';
}
function selectAllDevices(select) {
    const checkboxes = document.querySelectorAll('input[name="target_device_ids[]"]:not(:disabled)');
    checkboxes.forEach(checkbox => {
        checkbox.checked = select;
    });
}

document.getElementById('templateCopyForm').addEventListener('submit', function(e) {
    const karyawan = document.querySelector('select[name="karyawan_id"]').value;
    const sourceDevice = document.querySelector('select[name="source_device_id"]').value;
    const targetDevices = document.querySelectorAll('input[name="target_device_ids[]"]:checked');
    
    if(!karyawan) {
        e.preventDefault();
        alert('Pilih karyawan terlebih dahulu');
        return;
    }
    
    if(!sourceDevice) {
        e.preventDefault();
        alert('Pilih mesin sumber terlebih dahulu');
        return;
    }
    
    if(targetDevices.length === 0) {
        e.preventDefault();
        alert('Pilih minimal satu mesin tujuan');
        return;
    }
});
</script>
<?php if(!empty($sync_copy_results) && !isset($sync_copy_results['error'])): 
    $hasErr=false; $errList='';
    foreach($sync_copy_results['copy']['results'] ?? [] as $did=>$r){
        $s=$sync_copy_results['sync'][$did]['status'] ?? 'err';
        $smsg=$sync_copy_results['sync'][$did]['message'] ?? '';
        if($s!=='success' || $r['status']!=='success'){ $hasErr=true; $errList.= $r['device'].': sync='.$s.' ('.$smsg.') | copy='.$r['status'].' ('.$r['message'].')\\n'; }
    }
    if(!empty($sync_copy_results['copy']['error'])){ $hasErr=true; $errList.=$sync_copy_results['copy']['error']; }
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if($hasErr): ?>
Swal.fire({icon:'warning',title:'Ada error di Sync & Copy',html:'<pre style="text-align:left;font-size:12px;white-space:pre-wrap;"><?php echo addslashes($errList); ?></pre>',confirmButtonColor:'#f59e0b'});
<?php else: ?>
Swal.fire({icon:'success',title:'Berhasil',text:'Sync & Copy sukses semua mesin!',confirmButtonColor:'#16a34a'});
<?php endif; ?>
</script>
<?php endif; ?>

<style>
.table-modern {
    border-collapse: separate;
    border-spacing: 0;
}
.table-modern th {
    background-color: #f8fafc;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}
.table-modern td, .table-modern th {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
}
.table-modern tbody tr:hover {
    background-color: #f7fafc;
}
</style>
