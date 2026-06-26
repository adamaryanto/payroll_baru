<?php
function tgl_indo($tanggal){
    if (empty($tanggal)) return '-';
	$bulan = array (
		1 =>   'Januari',
		'Februari',
		'Maret',
		'April',
		'Mei',
		'Juni',
		'Juli',
		'Agustus',
		'September',
		'Oktober',
		'November',
		'Desember'
	);
	$pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
 
	return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

$tampildetail = $koneksi->query("select COUNT(id_karyawan) as jmlkaryawan from ms_karyawan ");
$datadetail = $tampildetail->fetch_assoc();
$jmlkaryawan = $datadetail['jmlkaryawan'];

// Rencana Upah Terbaru
$where_rkk = (strtolower($role) == 'owner') ? " WHERE status_rkk > 0 " : "";
$rkk_q = $koneksi->query("SELECT A.*, (SELECT COUNT(id_rkk_detail) FROM tb_rkk_detail WHERE id_rkk = A.id_rkk AND status_rkk != 'Digantikan') as jml, (SELECT SUM(upah) FROM tb_rkk_detail WHERE id_rkk = A.id_rkk AND status_rkk != 'Digantikan') as ttl FROM tb_rkk A $where_rkk ORDER BY tgl_rkk DESC, id_rkk DESC LIMIT 1");
$rkk_d = $rkk_q->fetch_assoc();
if ($rkk_d) {
    $rkk_terbaru = $rkk_d['ttl'] ?? 0;
    $rkk_jmlkaryawan = $rkk_d['jml'] ?? 0;
    $rkk_id = $rkk_d['id_rkk'];
    $rkk_tgl = $rkk_d['tgl_rkk'];
    $rkk_status = $rkk_d['status_rkk'];

    // Ambil ID Boneless utama
    $bnl_q = $koneksi->query("SELECT A.id_boneless, A.jumlah_mobil, B.biaya_mobil 
        FROM tb_boneless A 
        LEFT JOIN tb_biayamobil B ON A.id_biayamobil = B.id_biayamobil 
        WHERE A.id_rkk = '$rkk_id' LIMIT 1");
    $bnl_d = $bnl_q->fetch_assoc();
    $boneless_id = $bnl_d['id_boneless'] ?? '';
    $jumlah_mobil = $bnl_d['jumlah_mobil'] ?? 0;
    $biaya_mobil = $bnl_d['biaya_mobil'] ?? 0;

    // Inisialisasi variabel penampung
    $total_boneless_plus = 0;
    $total_boneless_minus = 0;
    $total_boneless_item = 0;
    $total_boneless_rencana = 0;
    $tampilan_tanpa_mesin = $rkk_terbaru;
    $tampilan_dengan_mesin = $rkk_terbaru;
    $total_hemat = 0;

    if ($boneless_id) {
        $query_detail = $koneksi->query("SELECT 
            SUM(total) as total_akhir,
            SUM(CASE WHEN jenis = 'plus' THEN total ELSE 0 END) as total_plus,
            SUM(CASE WHEN jenis = 'minus' THEN total ELSE 0 END) as total_minus
            FROM tb_boneless_detail 
            WHERE id_boneless = '$boneless_id'");

        $data_detail = $query_detail->fetch_assoc();
        $total_boneless_item = $data_detail['total_akhir'] ?? 0;
        $total_boneless_plus = $data_detail['total_plus'] ?? 0;
        $total_boneless_minus = $data_detail['total_minus'] ?? 0;

        // Total Rencana Boneless (Mobil + Plus - Minus)
        $total_boneless_rencana = ($jumlah_mobil * $biaya_mobil) + $total_boneless_plus - $total_boneless_minus;

        // Tanpa Mesin = Nilai Base + Biaya Plus (Beban manual)
        $tampilan_tanpa_mesin = $rkk_terbaru + $total_boneless_plus;

        // Dengan Mesin = Nilai Base - Biaya Minus (Efisiensi mesin)
        $tampilan_dengan_mesin = $rkk_terbaru - $total_boneless_minus;

        // Total Penghematan
        $total_hemat = $tampilan_tanpa_mesin - $tampilan_dengan_mesin;
    }
} else {
    $rkk_terbaru = 0;
    $rkk_jamkerja = '';
    $rkk_jmlkaryawan = 0;
    $rkk_id = '';
    $rkk_status = '';
    $rkk_tgl = '';
    $boneless_id = '';
    $jumlah_mobil = 0;
    $biaya_mobil = 0;
    $total_boneless_item = 0;
    $total_boneless_rencana = 0;
    $total_boneless_plus = 0;
    $total_boneless_minus = 0;
    $tampilan_tanpa_mesin = 0;
    $tampilan_dengan_mesin = 0;
    $total_hemat = 0;
}

// Realisasi Upah Terbaru
$real_q = $koneksi->query("SELECT A.*, 
    (SELECT COUNT(id_realisasi_detail) FROM tb_realisasi_detail WHERE id_realisasi = A.id_realisasi) as jml, 
    (SELECT SUM(r_upah) FROM tb_realisasi_detail WHERE id_realisasi = A.id_realisasi) as ttl 
    FROM tb_realisasi A 
    ORDER BY tgl_realisasi DESC, id_realisasi DESC LIMIT 1");
$real_d = $real_q->fetch_assoc();

if ($real_d) {
    $real_terbaru = $real_d['ttl'] ?? 0;
    $real_jamkerja = $real_d['jam_kerja'] ?? '';
    $real_jmlkaryawan = $real_d['jml'] ?? 0;
    $real_id = $real_d['id_realisasi'] ?? '';
    $real_status = $real_d['status_realisasi'] ?? '';
    $real_tgl = $real_d['tgl_realisasi'] ?? '';
} else {
    $real_terbaru = 0;
    $real_jamkerja = '';
    $real_jmlkaryawan = 0;
    $real_id = '';
    $real_status = '';
    $real_tgl = '';
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
                    <li class="breadcrumb-item"><a href="#" class="text-brand-600 hover:text-brand-800 transition-colors">Home</a></li>
                    <li class="breadcrumb-item active text-slate-500">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content px-4 pb-6">
    <div class="container-fluid">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">

            <!-- Total Karyawan -->
            <div class="bg-white rounded-2xl shadow-xl p-6 flex flex-col text-slate-800 relative overflow-hidden transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                <div class="flex flex-col items-center text-center mb-5">
                    <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mb-3 border border-blue-100 transition-transform group-hover:scale-110">
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-1">Total Karyawan</h3>
                </div>

                <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 mb-6">
                    <span class="text-[11px] text-slate-400 font-bold uppercase tracking-tight block mb-2">Keseluruhan SDM</span>
                    <div class="text-3xl font-black text-slate-800 tracking-tight">
                        <?php echo number_format($jmlkaryawan, 0, ',', '.'); ?> <span class="text-lg font-medium text-slate-400">Orang</span>
                    </div>
                </div>

                <div class="mt-auto">
                    <a href="?page=karyawan" class="flex items-center justify-center w-full px-4 py-3.5 bg-slate-800 text-white hover:bg-slate-700 rounded-xl text-sm font-bold transition-all shadow-lg shadow-slate-200">
                        <i class="fas fa-list-ul mr-2 text-xs"></i> Kelola Karyawan
                    </a>
                </div>
            </div>

            <!-- Rencana Upah -->
            <div class="bg-white rounded-2xl shadow-xl p-6 flex flex-col text-slate-800 relative overflow-hidden transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                <div class="flex flex-col items-center text-center mb-5">
                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center mb-3 border border-orange-100">
                        <i class="far fa-calendar-plus text-2xl text-orange-500"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-1">Rencana Upah</h3>
                    <p class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full inline-block">
                        <?php echo tgl_indo($rkk_tgl); ?>
                    </p>
                    <?php if (!empty($rkk_d['keterangan'])): ?>
                        <p class="mt-2 text-xs font-medium text-slate-500 italic max-w-full" title="<?php echo $rkk_d['keterangan']; ?>">
                            "<?php echo $rkk_d['keterangan']; ?>"
                        </p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="p-3 bg-rose-50/50 rounded-xl border border-rose-100">
                        <span class="text-[10px] text-rose-400 font-bold uppercase tracking-tighter block mb-1">Tanpa Mesin</span>
                        <div class="text-sm font-bold text-rose-700">Rp <?php echo number_format($tampilan_tanpa_mesin, 0, ',', '.'); ?></div>
                    </div>

                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                        <span class="text-[10px] text-emerald-500 font-bold uppercase tracking-tighter block mb-1">Dengan Mesin</span>
                        <div class="text-sm font-black text-emerald-700 tracking-tight">Rp <?php echo number_format($tampilan_dengan_mesin, 0, ',', '.'); ?></div>
                    </div>

                    <?php if ($total_hemat > 0): ?>
                        <div class="col-span-2 text-[10px] py-1.5 font-bold bg-emerald-500/10 text-emerald-600 flex items-center justify-center rounded-lg">
                            <i class="fas fa-bolt mr-1"></i> Hemat: Rp <?php echo number_format($total_hemat, 0, ',', '.'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Pabrik</span>
                        <div class="text-xs font-bold text-slate-700">Rp <?php echo number_format($rkk_terbaru, 0, ',', '.'); ?></div>
                    </div>
                    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Boneless</span>
                        <div class="text-xs font-bold text-slate-700">Rp <?php echo number_format($total_boneless_rencana, 0, ',', '.'); ?></div>
                    </div>
                </div>

                <div class="flex justify-between items-center px-4 py-3 mb-5 text-[11px] font-bold text-slate-600 bg-slate-50/50 rounded-xl border border-slate-100">
                    <span><i class="fas fa-users mr-1.5 text-slate-400"></i><?php echo number_format($rkk_jmlkaryawan, 0, ',', '.'); ?> Orang</span>
                    <span class="w-px h-3 bg-slate-200"></span>
                    <span><i class="fas fa-car mr-1.5 text-slate-400"></i> <?php echo $jumlah_mobil; ?> Mobil</span>
                </div>

                <!-- Button Rencana -->
                <div class="mt-auto space-y-2">
                    <div class="flex gap-2 w-full">
                        <?php
                        $role_lower = strtolower($role);
                        if ($role_lower != 'owner' || ($role_lower == 'owner' && $rkk_status >= "1")) { ?>
                            <a href="excelrkk.php?id=<?php echo $rkk_id; ?>"
                                class="flex-1 py-2.5 bg-slate-50 text-slate-600 border border-slate-200 rounded-xl text-[10px] font-black text-center uppercase transition-all duration-200 hover:bg-slate-200 flex items-center justify-center">
                                <i class="fas fa-print mr-1.5 text-xs"></i> CETAK
                            </a>
                        <?php }

                        if ($rkk_status < 2) { 
                            if ($rkk_status == "0") { ?>
                                <button type="button"
                                    onclick="handleAction('?page=rkk&aksi=accept&id=<?php echo $rkk_id; ?>&iddetail=pro', 'Ajukan rencana upah ini ke Owner?', 'Propose Data', 'question')"
                                    class="flex-1 py-2.5 bg-amber-400 text-amber-950 rounded-xl text-[10px] font-black text-center uppercase shadow-lg shadow-amber-100 transition-all hover:bg-amber-300 flex items-center justify-center border-none cursor-pointer">
                                    <i class="fas fa-paper-plane mr-1.5 text-xs"></i> PROPOSE
                                </button>
                            <?php } elseif ($rkk_status == "1") { 
                                if ($role_lower != 'owner') { ?>
                                    <button type="button"
                                        onclick="handleAction('?page=rkk&aksi=accept&id=<?php echo $rkk_id; ?>&iddetail=unpro', 'Batalkan pengajuan rencana upah ini?', 'Unpropose', 'warning')"
                                        class="flex-1 py-2.5 bg-rose-500 text-white rounded-xl text-[10px] font-black text-center uppercase shadow-lg shadow-rose-100 transition-all hover:bg-rose-600 flex items-center justify-center border-none cursor-pointer">
                                        <i class="fas fa-undo mr-1.5 text-xs"></i> UNPROPOSE
                                    </button>
                                <?php }
                            } ?>

                            <?php if ($role_lower == 'owner' || $role_lower == 'admin master' || $role_lower == 'kepala pabrik') { ?>
                                <button type="button"
                                    onclick="handleAction('?page=rkk&aksi=accept&id=<?php echo $rkk_id; ?>&iddetail=app', 'Setujui rencana upah untuk periode ini?', 'Approve Rencana', 'success')"
                                    class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-black text-center uppercase shadow-lg shadow-blue-100 transition-all hover:bg-blue-700 flex items-center justify-center border-none cursor-pointer">
                                    <i class="fas fa-check-circle mr-1.5 text-xs"></i> APPROVE
                                </button>
                            <?php }
                        } else { 
                            if ($role_lower == 'owner' || $role_lower == 'admin master') { ?>
                                <button type="button"
                                    onclick="handleAction('?page=rkk&aksi=accept&id=<?php echo $rkk_id; ?>&iddetail=unapp', 'Batalkan status approve untuk rencana upah ini?', 'Unapprove Rencana', 'warning')"
                                    class="flex-1 py-2.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-xl text-[10px] font-black text-center uppercase hover:bg-rose-100 transition-all flex items-center justify-center cursor-pointer">
                                    <i class="fas fa-undo mr-1.5 text-xs"></i> UNAPPROVE
                                </button>
                            <?php } else { ?>
                                <div class="flex-1 py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black text-center uppercase flex items-center justify-center border border-emerald-400 shadow-lg shadow-emerald-50">
                                    <i class="fas fa-check-double mr-1.5 text-xs"></i> APPROVED
                                </div>
                            <?php }
                        } ?>
                    </div>

                    <div class="flex gap-2">
                        <?php if (!($role_lower == 'owner' && $rkk_status == "0")) { ?>
                            <a href="?page=rkk&aksi=kelola&id=<?php echo $rkk_id; ?>" class="flex-1 py-2.5 text-slate-600 hover:text-brand-600 hover:bg-brand-50 border border-slate-200 rounded-xl text-[10px] font-bold uppercase transition-colors flex items-center justify-center">
                                <i class="fas fa-list-ul mr-1.5 text-[10px]"></i> Detail RKK
                            </a>
                            <?php if ($boneless_id) { ?>
                                <a href="?page=boneless&aksi=ubah&id=<?php echo $boneless_id; ?>&ref=home" class="flex-1 py-2.5 text-blue-600 hover:bg-blue-50 border border-blue-100 bg-blue-50 rounded-xl text-[10px] font-bold uppercase flex items-center justify-center">
                                    <i class="fas fa-edit mr-1.5 text-[10px]"></i> Boneless
                                </a>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="flex-1 py-2.5 bg-slate-50 text-slate-400 border border-dashed border-slate-200 rounded-xl text-[10px] font-bold text-center flex items-center justify-center italic">
                                <i class="fas fa-clock mr-1.5"></i> Waiting Staff...
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Realisasi Upah -->
            <div class="bg-white rounded-2xl shadow-xl p-6 flex flex-col text-slate-800 relative overflow-hidden transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                <div class="flex flex-col items-center text-center mb-5">
                    <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center mb-3 border border-emerald-100">
                        <i class="fas fa-check-double text-2xl text-emerald-500"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest mb-1">Realisasi Upah</h3>
                    <p class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full inline-block">
                        <?php echo tgl_indo($real_tgl); ?>
                    </p>
                    <?php if (!empty($real_d['keterangan'])): ?>
                        <p class="mt-2 text-xs font-medium text-slate-500 italic max-w-full" title="<?php echo $real_d['keterangan']; ?>">
                            "<?php echo $real_d['keterangan']; ?>"
                        </p>
                    <?php endif; ?>
                </div>

                <div class="space-y-3 mb-6">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight block mb-1">Total Pengeluaran</span>
                        <div class="text-2xl font-black text-slate-800 tracking-tight">
                            Rp <?php echo number_format($real_terbaru, 0, ',', '.'); ?>
                        </div>
                    </div>

                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight block mb-1">Total Karyawan</span>
                        <div class="text-2xl font-black text-slate-800 tracking-tight">
                            <?php echo number_format($real_jmlkaryawan, 0, ',', '.'); ?> <span class="text-sm font-medium text-slate-500">Orang</span>
                        </div>
                    </div>
                </div>

                <div class="mt-auto space-y-2">
                    <div class="flex gap-2 w-full">
                        <?php
                        $role_lower = strtolower($role);
                        $is_petinggi = ($role_lower == 'owner' || $role_lower == 'admin master' || $role_lower == 'kepala pabrik');

                        if ($real_status == "2" || $is_petinggi) { ?>
                            <a href="excelrealisasi.php?id=<?php echo $real_id; ?>" class="flex-1 py-2.5 bg-slate-50 text-slate-600 border border-slate-200 rounded-xl text-[10px] font-black text-center uppercase hover:bg-slate-200 transition-all flex items-center justify-center">
                                <i class="fas fa-file-invoice-dollar mr-1.5 text-xs"></i> Payroll
                            </a>
                        <?php } else { ?>
                            <div class="flex-1 py-2.5 bg-slate-50 text-slate-300 border border-dashed border-slate-200 rounded-xl text-[10px] font-bold text-center flex items-center justify-center cursor-not-allowed">
                                <i class="fas fa-lock mr-1.5"></i> Payroll
                            </div>
                        <?php }

                        if ($real_status < 2) {
                            if ($is_petinggi) { ?>
                                <button type="button" onclick="confirmApprove(this)" data-href="?page=realisasi&aksi=accept&id=<?php echo $real_id; ?>&iddetail=app" class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-black text-center uppercase shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all border-none flex items-center justify-center cursor-pointer">
                                    <i class="fas fa-check-circle mr-1.5 text-xs"></i> APPROVE
                                </button>
                            <?php } else { ?>
                                <div class="flex-1 py-2.5 bg-amber-50 text-amber-600 border border-amber-100 rounded-xl text-[9px] font-black text-center uppercase flex items-center justify-center">
                                    <i class="fas fa-hourglass-half mr-1 text-xs"></i> WAITING
                                </div>
                            <?php }
                        } else { 
                            if ($is_petinggi) { ?>
                                <button type="button" 
                                    onclick="handleAction('?page=realisasi&aksi=unapprove&id=<?php echo $real_id; ?>&iddetail=unapp', 'Batalkan status approve untuk realisasi ini?', 'Unapprove Realisasi', 'warning')" 
                                    class="flex-1 py-2.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-xl text-[10px] font-black text-center uppercase hover:bg-rose-100 transition-all flex items-center justify-center cursor-pointer">
                                    <i class="fas fa-undo mr-1.5 text-xs"></i> UNAPPROVE
                                </button>
                            <?php } else { ?>
                                <div class="flex-1 py-2.5 bg-emerald-500 text-white rounded-xl text-[10px] font-black text-center uppercase flex items-center justify-center shadow-lg shadow-emerald-50">
                                    <i class="fas fa-check-double mr-1.5 text-xs"></i> APPROVED
                                </div>
                            <?php }
                        } ?>
                    </div>

                    <?php if ($real_status == "2" || $is_petinggi) { ?>
                        <a href="?page=realisasi&aksi=kelola&id=<?php echo $real_id; ?>"
                            class="flex items-center justify-center w-full px-4 py-3 bg-slate-800 text-white hover:bg-slate-700 rounded-xl text-sm font-bold transition-all shadow-lg shadow-slate-200">
                            <i class="fas fa-clipboard-list mr-2 text-xs"></i> Detail Realisasi
                        </a>
                    <?php } else { ?>
                        <div class="flex items-center justify-center w-full px-4 py-3 bg-slate-50/50 text-slate-300 border border-dashed border-slate-200 rounded-xl text-xs font-medium italic cursor-not-allowed">
                            <i class="fas fa-lock mr-2"></i> Locked (Waiting Approve)
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function handleAction(url, message, title, iconType) {
        Swal.fire({
            title: title,
            text: message,
            icon: iconType,
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Lanjutkan!',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-3xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Sedang Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });
                window.location.href = url;
            }
        })
    }

    function confirmApprove(button) {
        const url = button.getAttribute('data-href');
        Swal.fire({
            title: 'Konfirmasi Approve?',
            text: "Data yang sudah di-approve akan mengunci detail realisasi.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Approve!',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-3xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Sedang Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });
                window.location.href = url;
            }
        });
    }
</script>
