<?php
function tgl_indo($tanggal){
    if (empty($tanggal)) return '-';
	$bulan = array (1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember');
	$pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
	return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}
$tampildetail = $koneksi->query("select COUNT(id_karyawan) as jmlkaryawan from ms_karyawan ");
$datadetail = $tampildetail->fetch_assoc();
$jmlkaryawan = $datadetail['jmlkaryawan'];
include_once __DIR__ . '/device_sync_helper.php';
$devices = get_all_devices($koneksi);
for($i=count($devices); $i<4; $i++) $devices[]=['id_mesin'=>'','no_mesin'=>$i+1,'nama_mesin'=>'Mesin '.($i+1),'ip_mesin'=>'','comm_key'=>'0','port'=>'4370'];
$devices=array_values($devices);
// EDIT DARI FILE home__2.php MILIK REZA, 2026-10-24.
// Tanggal dashboard HANYA dari tanggal yang ada Realisasinya di tb_realisasi.
// Tanggal tanpa Realisasi tidak ditawarkan sama sekali (tidak ada lagi input kalender bebas).
// Tombol "Lihat Realisasi" langsung ke Kelola kalau tanggal itu cuma punya 1 realisasi.
$daftar_realisasi = [];
$peta_realisasi = [];
$q_real_tgl = $koneksi->query("SELECT tgl_realisasi, COUNT(*) AS jml, MAX(id_realisasi) AS id_terbaru FROM tb_realisasi WHERE tgl_realisasi IS NOT NULL GROUP BY tgl_realisasi ORDER BY tgl_realisasi DESC");
if ($q_real_tgl) {
    while ($r_real_tgl = $q_real_tgl->fetch_assoc()) {
        if (!empty($r_real_tgl['tgl_realisasi'])) {
            $daftar_realisasi[] = $r_real_tgl['tgl_realisasi'];
            $peta_realisasi[$r_real_tgl['tgl_realisasi']] = ['id' => (int)$r_real_tgl['id_terbaru'], 'jml' => (int)$r_real_tgl['jml']];
        }
    }
}
$req_tgl = $_GET['tgl_absen'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $req_tgl)) $req_tgl = '';
if ($req_tgl !== '' && in_array($req_tgl, $daftar_realisasi, true)) {
    $today = $req_tgl;
} elseif (!empty($daftar_realisasi)) {
    $today = in_array(date('Y-m-d'), $daftar_realisasi, true) ? date('Y-m-d') : $daftar_realisasi[0];
} else {
    $today = date('Y-m-d');
}
// Link tombol: 1 realisasi = langsung ke Kelola, >1 realisasi = ke list tanggal itu (pilih barisnya).
$link_realisasi = '?page=realisasi';
if ($today !== '' && isset($peta_realisasi[$today])) {
    $link_realisasi = ($peta_realisasi[$today]['jml'] == 1)
        ? '?page=realisasi&aksi=kelola&id=' . $peta_realisasi[$today]['id']
        : '?page=realisasi&tgl=' . urlencode($today);
}
$stats=['masuk'=>0,'pulang'=>0,'lembur'=>0,'tidak_lengkap'=>0];
$qToday=$koneksi->query("SELECT * FROM tb_record WHERE tgl='$today'");
$groupedToday=[];
if($qToday){ while($r=$qToday->fetch_assoc()){
    $pin=$r['userid']; $time=substr($r['detail_waktu'],11,5); $status=$r['status'];
    $k=$pin.'_'.$today;
    if(!isset($groupedToday[$k])) $groupedToday[$k]=['masuk'=>'','pulang'=>'','raw'=>[]];
    $groupedToday[$k]['raw'][]=['time'=>$time,'status'=>$status];
    if($status==0 && (empty($groupedToday[$k]['masuk']) || $time < $groupedToday[$k]['masuk'])) $groupedToday[$k]['masuk']=$time;
    elseif($status==1 && (empty($groupedToday[$k]['pulang']) || $time > $groupedToday[$k]['pulang'])) $groupedToday[$k]['pulang']=$time;
}}
foreach($groupedToday as $k=>$row){
    if(empty($row['masuk']) && !empty($row['raw'])){ usort($row['raw'],fn($a,$b)=>strcmp($a['time'],$b['time'])); $row['masuk']=$row['raw'][0]['time']; if(count($row['raw'])>1) $row['pulang']=$row['raw'][count($row['raw'])-1]['time']; }
    if(!empty($row['masuk']) && $row['masuk']==$row['pulang']) $row['pulang']='';
    if(!empty($row['masuk'])) $stats['masuk']++;
    if(!empty($row['pulang'])) $stats['pulang']++;
    if(!empty($row['masuk']) && !empty($row['pulang'])){ $dur=(strtotime($row['pulang'])-strtotime($row['masuk'])); if($dur<0) $dur+=86400; if($dur/3600>8) $stats['lembur']++; }
    if((!empty($row['masuk']) xor !empty($row['pulang']))) $stats['tidak_lengkap']++;
}
?>

<div class="content-header pt-6 pb-4 px-4">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-2xl font-bold text-slate-800 tracking-tight">Dashboard Overview</h1>
                <p class="text-sm text-slate-500 mt-1">HRIS & Payroll Management System</p>
            </div>
            <div class="col-sm-6 mt-3 sm:mt-0">
                <ol class="breadcrumb float-sm-right bg-transparent text-sm p-0 m-0">
                    <li class="breadcrumb-item"><a href="#" class="text-brand-600">Home</a></li>
                    <li class="breadcrumb-item active text-slate-500">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content px-4 pb-6">
    <div class="container-fluid">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">

            <!-- Card 1: Total Karyawan -->
            <div class="bg-white rounded-2xl shadow-xl p-6 flex flex-col text-slate-800 relative overflow-hidden">
                <div class="flex flex-col items-center text-center mb-5">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mb-3 border border-blue-100">
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Total Karyawan</h3>
                </div>
                <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 mb-6">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-tight block mb-2">Keseluruhan SDM</span>
                    <div class="text-3xl font-black text-slate-800 tracking-tight">
                        <?php echo number_format($jmlkaryawan, 0, ',', '.'); ?> <span class="text-lg font-medium text-slate-400">Orang</span>
                    </div>
                </div>
                <div class="mt-auto">
                    <a href="?page=karyawan" class="flex items-center justify-center w-full px-4 py-3.5 bg-slate-800 text-white hover:bg-slate-700 rounded-xl text-sm font-bold shadow-lg shadow-slate-200">
                        <i class="fas fa-list-ul mr-2 text-xs"></i> Kelola Karyawan
                    </a>
                </div>
            </div>

            <!-- Card 2: Status Mesin 1-4 -->
            <div class="bg-white rounded-2xl shadow-xl p-6 flex flex-col text-slate-800 relative overflow-hidden">
                <div class="flex flex-col items-center text-center mb-4">
                    <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center mb-3 border border-indigo-100">
                        <i class="fas fa-network-wired text-2xl text-indigo-600"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Status Mesin</h3>
                    <p class="text-xs text-slate-400 mt-1">Koneksi 4 Mesin Fingerprint</p>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <?php foreach($devices as $dev): $online=!empty($dev['ip_mesin'])?test_device_connection($dev['ip_mesin'],$dev['port']):false; ?>
                    <div class="p-3 rounded-xl border <?= $online?'border-green-200 bg-green-50':'border-red-200 bg-red-50' ?> text-center">
                        <p class="text-xs font-bold text-slate-700"><?= htmlspecialchars($dev['nama_mesin']) ?></p>
                        <p class="text-[10px] text-slate-500"><?= htmlspecialchars($dev['ip_mesin']?:'-') ?></p>
                        <span class="mt-1 inline-block px-2 py-1 text-[10px] font-bold rounded-full <?= $online?'bg-green-600 text-white':'bg-red-600 text-white' ?>"><?= $online?'ONLINE':'OFFLINE' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!in_array(strtolower($_SESSION['role'] ?? ''), ["admin hr", "kepala pabrik", "owner"], true)): ?>
                <div class="mt-auto">
                    <a href="?page=mesin" class="flex items-center justify-center w-full px-4 py-3 bg-indigo-600 text-white hover:bg-indigo-700 rounded-xl text-sm font-bold"><i class="fas fa-cogs mr-2"></i> Kelola Mesin</a>
                </div>
                <?php endif; ?>
                <!-- 2026-10-26: Tombol Kelola Mesin disembunyikan untuk Admin HR, Kepala Pabrik,
                     dan Owner; grid Status Mesin (ONLINE/OFFLINE) di atas tetap tampil untuk semua role. -->
            </div>

            <!-- Card 3: Absen Hari Ini -->
            <div class="bg-white rounded-2xl shadow-xl p-6 flex flex-col text-slate-800 relative overflow-hidden">
                <div class="flex flex-col items-center text-center mb-4">
                    <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center mb-3 border border-emerald-100">
                        <i class="fas fa-clipboard-list text-2xl text-emerald-600"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Absen Hari Ini</h3>
                    <p class="text-xs text-slate-400 mt-1"><?= tgl_indo($today) ?> (<?= $today ?>)</p>
                    <form method="GET" class="mt-2 flex items-center gap-2">
                        <select id="tgl-absen" name="tgl_absen" class="text-xs px-2 py-1 border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <?php foreach ($daftar_realisasi as $tgl_opt) : ?>
                                <option value="<?= htmlspecialchars($tgl_opt) ?>" <?= ($tgl_opt === $today) ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($tgl_opt)) ?><?= (isset($peta_realisasi[$tgl_opt]) && $peta_realisasi[$tgl_opt]['jml'] > 1) ? ' (' . $peta_realisasi[$tgl_opt]['jml'] . ' realisasi)' : '' ?></option>
                            <?php endforeach; ?>
                            <?php if (empty($daftar_realisasi)) : ?>
                                <option value="" disabled>Belum ada Realisasi</option>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="text-xs px-3 py-1 bg-emerald-600 text-white rounded-lg font-bold">Filter</button>
                    </form>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="p-3 bg-blue-50 rounded-xl border border-blue-100 text-center">
                        <p class="text-[10px] font-bold text-blue-600 uppercase">Masuk</p><p class="text-2xl font-black text-blue-700"><?= $stats['masuk'] ?></p><p class="text-[10px] text-blue-600">orang</p>
                    </div>
                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100 text-center">
                        <p class="text-[10px] font-bold text-emerald-600 uppercase">Pulang</p><p class="text-2xl font-black text-emerald-700"><?= $stats['pulang'] ?></p><p class="text-[10px] text-emerald-600">orang</p>
                    </div>
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-100 text-center">
                        <p class="text-[10px] font-bold text-amber-600 uppercase">Lembur</p><p class="text-2xl font-black text-amber-700"><?= $stats['lembur'] ?></p><p class="text-[10px] text-amber-600">>8 jam</p>
                    </div>
                    <div class="p-3 bg-rose-50 rounded-xl border border-rose-100 text-center">
                        <p class="text-[10px] font-bold text-rose-600 uppercase">Tidak Lengkap</p><p class="text-2xl font-black text-rose-700"><?= $stats['tidak_lengkap'] ?></p><p class="text-[10px] text-rose-600">hanya masuk / hanya pulang</p>
                    </div>
                </div>
                <div class="mt-auto">
                    <a id="btn-lihat-realisasi" href="<?= htmlspecialchars($link_realisasi) ?>" class="flex items-center justify-center w-full px-4 py-3 bg-slate-800 text-white hover:bg-slate-700 rounded-xl text-sm font-bold"><i class="fas fa-check-circle mr-2"></i> Lihat Realisasi</a>
                    <script>
                        // Tombol ikut tanggal yang dipilih di dropdown, tanpa harus klik Filter dulu.
                        // Tanggal dengan 1 realisasi = langsung ke Kelola, >1 realisasi = ke list tanggal itu.
                        (function () {
                            var sel = document.getElementById('tgl-absen');
                            var btn = document.getElementById('btn-lihat-realisasi');
                            if (!sel || !btn) return;
                            var peta = <?= json_encode($peta_realisasi) ?>;
                            function updateBtn() {
                                var info = peta[sel.value];
                                if (info && info.jml == 1) {
                                    btn.href = '?page=realisasi&aksi=kelola&id=' + info.id;
                                } else if (sel.value) {
                                    btn.href = '?page=realisasi&tgl=' + encodeURIComponent(sel.value);
                                } else {
                                    btn.href = '?page=realisasi';
                                }
                            }
                            sel.addEventListener('change', updateBtn);
                        })();
                    </script>
                </div>
            </div>

        </div>
    </div>
</section>
