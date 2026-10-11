<?php
// --- Denda ---
$tampil = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
$data = $tampil ? $tampil->fetch_assoc() : [];
// Kolom batas mangkir (telat >= N menit dihitung tidak masuk), dibuat otomatis bila belum ada
$ckMk = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_menit'");
if ($ckMk && $ckMk->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_denda ADD COLUMN mangkir_menit INT NOT NULL DEFAULT 0");
    $tampil = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $data = $tampil ? $tampil->fetch_assoc() : [];
}
$dendamasuk = $data['denda_masuk'] ?? '';
$dendaistirahatkeluar = $data['denda_istirahat_keluar'] ?? '';
$dendaistirahatmasuk = $data['denda_istirahat_masuk'] ?? '';
$dendapulang = $data['denda_pulang'] ?? '';
$dendatidaklengkap = $data['denda_tidak_lengkap'] ?? '';
$mangkirmenit = isset($data['mangkir_menit']) ? (int)$data['mangkir_menit'] : 0;
$mangkir_dari = isset($data['mangkir_dari_tgl']) && $data['mangkir_dari_tgl'] ? $data['mangkir_dari_tgl'] : '';
$mangkir_sampai = isset($data['mangkir_sampai_tgl']) && $data['mangkir_sampai_tgl'] ? $data['mangkir_sampai_tgl'] : '';
$ckDt = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_dari_tgl'");
if ($ckDt && $ckDt->num_rows == 0) {
    $koneksi->query("ALTER TABLE tb_denda ADD COLUMN mangkir_dari_tgl DATE NULL DEFAULT NULL, ADD COLUMN mangkir_sampai_tgl DATE NULL DEFAULT NULL");
    $tampil = $koneksi->query("SELECT * FROM tb_denda LIMIT 1");
    $data = $tampil ? $tampil->fetch_assoc() : [];
    $mangkir_dari = isset($data['mangkir_dari_tgl']) && $data['mangkir_dari_tgl'] ? $data['mangkir_dari_tgl'] : '';
    $mangkir_sampai = isset($data['mangkir_sampai_tgl']) && $data['mangkir_sampai_tgl'] ? $data['mangkir_sampai_tgl'] : '';
}
$ckOld1 = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_bln_dari'");
if ($ckOld1 && $ckOld1->num_rows > 0) { $koneksi->query("ALTER TABLE tb_denda DROP COLUMN mangkir_bln_dari"); }
$ckOld2 = $koneksi->query("SHOW COLUMNS FROM tb_denda LIKE 'mangkir_bln_sampai'");
if ($ckOld2 && $ckOld2->num_rows > 0) { $koneksi->query("ALTER TABLE tb_denda DROP COLUMN mangkir_bln_sampai"); }
// --- Toleransi ---
$koneksi->query("CREATE TABLE IF NOT EXISTS tb_toleransi (id INT AUTO_INCREMENT PRIMARY KEY, toleransi_masuk INT DEFAULT 10, toleransi_istirahat_keluar INT DEFAULT 5, toleransi_istirahat_masuk INT DEFAULT 10, toleransi_pulang INT DEFAULT 5) ENGINE=InnoDB DEFAULT CHARSET=latin1");
$cek = $koneksi->query("SELECT COUNT(*) as c FROM tb_toleransi");
if($cek->fetch_assoc()['c']==0) $koneksi->query("INSERT INTO tb_toleransi (toleransi_masuk, toleransi_istirahat_keluar, toleransi_istirahat_masuk, toleransi_pulang) VALUES (10,5,10,5)");
$t2 = $koneksi->query("SELECT * FROM tb_toleransi LIMIT 1")->fetch_assoc();
$masuk = $t2['toleransi_masuk'] ?? 10;
$keluar = $t2['toleransi_istirahat_keluar'] ?? 5;
$masuk2 = $t2['toleransi_istirahat_masuk'] ?? 10;
$pulang = $t2['toleransi_pulang'] ?? 5;

if (isset($_POST['simpan_denda'])) {
    $tdendamasuk = $_POST['tdendamasuk'] ?? '';
    $tdendaistirahatkeluar = $_POST['tdendaistirahatkeluar'] ?? '';
    $tdendaistirahatmasuk = $_POST['tdendaistirahatmasuk'] ?? '';
    $tdendapulang = $_POST['tdendapulang'] ?? '';
    $tdendatidaklengkap = $_POST['tdendatidaklengkap'] ?? '';
    $sql = $koneksi->query("UPDATE tb_denda SET denda_masuk = '$tdendamasuk', denda_istirahat_keluar = '$tdendaistirahatkeluar', denda_istirahat_masuk = '$tdendaistirahatmasuk', denda_pulang = '$tdendapulang', denda_tidak_lengkap = '$tdendatidaklengkap'");
    if ($sql) { echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'success',title:'Berhasil',text:'Denda berhasil diperbarui',confirmButtonColor:'#2563eb'}).then(()=>{window.location.href='?page=denda';});</script></body></html>"; exit; }
}
if (isset($_POST['simpan_toleransi'])) {
    $tmasuk = intval($_POST['ttoleransi_masuk'] ?? 10);
    $tkeluar = intval($_POST['ttoleransi_keluar'] ?? 5);
    $tmasuk2 = intval($_POST['ttoleransi_masuk2'] ?? 10);
    $tpulang = intval($_POST['ttoleransi_pulang'] ?? 5);
    $sql = $koneksi->query("UPDATE tb_toleransi SET toleransi_masuk='$tmasuk', toleransi_istirahat_keluar='$tkeluar', toleransi_istirahat_masuk='$tmasuk2', toleransi_pulang='$tpulang'");
    if ($sql) { echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'success',title:'Berhasil',text:'Toleransi berhasil diperbarui',confirmButtonColor:'#4f46e5'}).then(()=>{window.location.href='?page=denda';});</script></body></html>"; exit; }
}
if (isset($_POST['simpan_mangkir'])) {
    $tmangkir = max(0, intval($_POST['tmangkirmenit'] ?? 0));
    $tdari = trim($_POST['tmangkir_dari'] ?? '');
    $tsampai = trim($_POST['tmangkir_sampai'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tdari)) $tdari = null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tsampai)) $tsampai = null;
    $dari_sql = $tdari === null ? "NULL" : "'$tdari'";
    $sampai_sql = $tsampai === null ? "NULL" : "'$tsampai'";
    $sql = $koneksi->query("UPDATE tb_denda SET mangkir_menit='$tmangkir', mangkir_dari_tgl=$dari_sql, mangkir_sampai_tgl=$sampai_sql");
    if ($sql) { echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>Swal.fire({icon:'success',title:'Berhasil',text:'Batas mangkir diperbarui',confirmButtonColor:'#7c3aed'}).then(()=>{window.location.href='?page=denda';});</script></body></html>"; exit; }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10 space-y-8">
    <!-- Denda -->
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-money-bill-wave mr-3"></i> Pengaturan Denda
            </h3>
            <p class="text-slate-300 text-sm mt-1">Atur nominal potongan denda untuk kedisiplinan kerja</p>
        </div>
        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Denda Masuk (Telat) <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-400 font-bold">Rp</span></div><input type="number" name="tdendamasuk" value="<?= htmlspecialchars($dendamasuk) ?>" required class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 font-medium"/></div></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Denda Keluar Istirahat (Cepat) <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-400 font-bold">Rp</span></div><input type="number" name="tdendaistirahatkeluar" value="<?= htmlspecialchars($dendaistirahatkeluar) ?>" required class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 font-medium"/></div></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Denda Masuk Istirahat (Telat) <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-400 font-bold">Rp</span></div><input type="number" name="tdendaistirahatmasuk" value="<?= htmlspecialchars($dendaistirahatmasuk) ?>" required class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 font-medium"/></div></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Denda Pulang Awal <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-400 font-bold">Rp</span></div><input type="number" name="tdendapulang" value="<?= htmlspecialchars($dendapulang) ?>" required class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 font-medium"/></div></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">DENDA ABSENSI TIDAK LENGKAP <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-400 font-bold">Rp</span></div><input type="number" name="tdendatidaklengkap" value="<?= htmlspecialchars($dendatidaklengkap) ?>" required class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-500 font-medium"/></div></div>
                </div>
                <div class="flex justify-end pt-6 border-t border-gray-100"><button type="submit" name="simpan_denda" class="inline-flex items-center px-6 py-2.5 text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700"><i class="fas fa-save mr-2"></i> Simpan Denda</button></div>
            </div>
        </form>
    </div>

    <!-- Toleransi -->
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-clock mr-3"></i> Pengaturan Toleransi Telat
            </h3>
            <p class="text-indigo-200 text-sm mt-1">Atur batas menit sebelum dihitung terlambat</p>
        </div>
        <form method="POST">
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Masuk (menit) <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-stopwatch text-gray-400"></i></div><input type="number" min="0" max="60" name="ttoleransi_masuk" value="<?= htmlspecialchars($masuk) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 font-medium"/><div class="absolute inset-y-0 right-0 pr-3 flex items-center"><span class="text-gray-500 font-bold text-sm">menit</span></div></div><p class="text-xs text-gray-400 mt-1">10 = telat 10 menit baru dihitung</p></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Keluar Istirahat (menit) <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-stopwatch text-gray-400"></i></div><input type="number" min="0" max="60" name="ttoleransi_keluar" value="<?= htmlspecialchars($keluar) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 font-medium"/><div class="absolute inset-y-0 right-0 pr-3 flex items-center"><span class="text-gray-500 font-bold text-sm">menit</span></div></div></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Masuk Istirahat (menit) <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-stopwatch text-gray-400"></i></div><input type="number" min="0" max="60" name="ttoleransi_masuk2" value="<?= htmlspecialchars($masuk2) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 font-medium"/><div class="absolute inset-y-0 right-0 pr-3 flex items-center"><span class="text-gray-500 font-bold text-sm">menit</span></div></div></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Toleransi Pulang Awal (menit) <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-stopwatch text-gray-400"></i></div><input type="number" min="0" max="60" name="ttoleransi_pulang" value="<?= htmlspecialchars($pulang) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-indigo-500 font-medium"/><div class="absolute inset-y-0 right-0 pr-3 flex items-center"><span class="text-gray-500 font-bold text-sm">menit</span></div></div></div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6"><p class="text-xs text-amber-800 flex gap-2"><i class="fas fa-info-circle mt-0.5"></i> <span>Jika telat di bawah toleransi, tidak dihitung terlambat & tidak kena denda.</span></p></div>
                <div class="flex justify-end pt-6 border-t border-gray-100"><button type="submit" name="simpan_toleransi" class="inline-flex items-center px-6 py-2.5 text-sm font-bold rounded-xl shadow-md text-white bg-indigo-600 hover:bg-indigo-700"><i class="fas fa-save mr-2"></i> Simpan Toleransi</button></div>
            </div>
        </form>
    </div>

    <!-- Batas Mangkir (TERPISAH dari potongan gaji: hanya status + panel, angka tidak diubah) -->
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-user-slash mr-3"></i> Batas Telat Dianggap Mangkir
            </h3>
            <p class="text-amber-100 text-sm mt-1">Bukan potongan gaji — hanya status kehadiran + daftar Tidak Absen</p>
        </div>
        <form method="POST">
            <div class="p-8">
                <div class="max-w-xs"><label class="block text-sm font-bold text-gray-700 mb-2">Telat jadi mangkir <span class="text-rose-500">*</span></label><div class="relative"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-stopwatch text-gray-400"></i></div><input type="number" min="0" max="1440" name="tmangkirmenit" value="<?= htmlspecialchars($mangkirmenit) ?>" required class="block w-full pl-10 pr-16 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:ring-2 focus:ring-amber-500 font-medium"/><div class="absolute inset-y-0 right-0 pr-3 flex items-center"><span class="text-gray-500 font-bold text-sm">menit</span></div></div><p class="text-xs text-gray-400 mt-1">Cth: 60 = telat 1 jam dihitung tidak masuk (masuk panel Tidak Absen). 0 = fitur mati.</p></div>
                <div class="grid grid-cols-2 gap-4 max-w-md mt-4">
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Berlaku dari tanggal</label><input type="date" name="tmangkir_dari" value="<?= htmlspecialchars($mangkir_dari) ?>" class="block w-full px-3 py-3 border border-gray-300 rounded-xl bg-gray-50 font-medium"></div>
                    <div><label class="block text-sm font-bold text-gray-700 mb-2">Sampai tanggal</label><input type="date" name="tmangkir_sampai" value="<?= htmlspecialchars($mangkir_sampai) ?>" class="block w-full px-3 py-3 border border-gray-300 rounded-xl bg-gray-50 font-medium"></div>
                </div>
                <p class="text-xs text-gray-400 mt-1">Kosongkan = tanpa batas tanggal tersebut.</p>
                <div class="flex justify-end pt-6 border-t border-gray-100 mt-6"><button type="submit" name="simpan_mangkir" class="inline-flex items-center px-6 py-2.5 text-sm font-bold rounded-xl shadow-md text-white bg-amber-600 hover:bg-amber-700"><i class="fas fa-save mr-2"></i> Simpan Batas</button></div>
            </div>
        </form>
    </div>
</div>
