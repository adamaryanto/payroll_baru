<?php
// MASTER -> Mobil: daftar armada truck (nama + harga per unit, bisa tambah).
// Dipakai form boneless (centang truck yg jalan per tanggal).
include_once __DIR__ . '/mobil_lib.php';

if (isset($_GET['hapus'])) {
    $hid = (int)$_GET['hapus'];
    if ($hid > 0) $koneksi->query("DELETE FROM tb_mobil WHERE id_mobil = '$hid'");
    echo "<script>window.location.href='?page=boneless&aksi=master';</script>";
    exit;
}

if (isset($_POST['simpan'])) {
    $ids = (array)($_POST['mob_id'] ?? []);
    $namas = (array)($_POST['mob_nama'] ?? []);
    $hargas = (array)($_POST['mob_harga'] ?? []);
    foreach ($namas as $i => $nm_raw) {
        $nm = trim((string)($nm_raw ?? ''));
        if ($nm === '') continue;
        $hg_raw = preg_replace('/[^0-9]/', '', (string)($hargas[$i] ?? '0'));
        $hg = max(0, (int)$hg_raw);
        $nm_esc = $koneksi->real_escape_string($nm);
        $mid = (int)($ids[$i] ?? 0);
        if ($mid > 0) {
            $koneksi->query("UPDATE tb_mobil SET nama = '$nm_esc', harga = '$hg' WHERE id_mobil = '$mid'");
        } else {
            $koneksi->query("INSERT INTO tb_mobil (nama, harga) VALUES ('$nm_esc', '$hg')");
        }
    }
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        setTimeout(function() {
            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data armada tersimpan.', confirmButtonColor: '#2563eb' })
            .then(() => { window.location.href='?page=boneless&aksi=master'; });
        }, 100);
    </script>";
    exit;
}

$fleet = function_exists('boneless_mobil_fleet') ? boneless_mobil_fleet($koneksi) : [];
// Tarif acuan utk baris baru (dari tarif master lama bila ada)
$tarif_acuan = 0;
$qm_acu = $koneksi->query("SELECT biaya_mobil FROM tb_biayamobil LIMIT 1");
if ($qm_acu && ($rm_acu = $qm_acu->fetch_assoc())) $tarif_acuan = (int)($rm_acu['biaya_mobil'] ?? 0);
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 mb-10">
    <div class="bg-white shadow-xl border border-gray-200 rounded-2xl overflow-hidden">

        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <h3 class="text-2xl font-extrabold text-white m-0 tracking-tight flex items-center">
                <i class="fas fa-truck mr-3"></i>
                Armada Mobil / Truck
            </h3>
            <p class="text-blue-100 text-sm mt-1">Daftar truck + harga per unit. Dicentang di form Boneless sesuai yg jalan.</p>
        </div>

        <form method="POST">
            <div class="p-8">
                <div class="table-responsive">
                    <table class="table table-bordered" id="tableArmada">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>Nama Mobil</th>
                                <th style="width: 220px;">Harga per Unit (Rp)</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $mn = 1;
                            foreach ($fleet as $m): ?>
                                <tr class="arm-row">
                                    <input type="hidden" name="mob_id[]" value="<?= (int)$m['id_mobil'] ?>">
                                    <td class="text-center font-bold text-gray-400"><?= $mn++ ?></td>
                                    <td><input type="text" name="mob_nama[]" class="form-control" value="<?= htmlspecialchars($m['nama']) ?>" required></td>
                                    <td>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-bold text-sm pointer-events-none">Rp</span>
                                            <input type="text" name="mob_harga[]" class="form-control text-right font-bold mob-harga-fmt pl-9" value="<?= number_format((float)($m['harga'] ?? 0), 0, ',', '.') ?>" inputmode="numeric">
                                        </div>
                                    </td>
                                    <td class="text-center"><a href="?page=boneless&aksi=master&hapus=<?= (int)$m['id_mobil'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus armada ini? (Data boneless lama tidak ikut berubah)')"><i class="fas fa-times"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" id="addArmadaRow" class="btn btn-sm btn-outline-primary font-bold"><i class="fas fa-plus mr-1"></i> Tambah Mobil</button>
                    <div class="mt-2 text-sm font-bold text-gray-700">Total: <span id="mobSummary" class="text-blue-700">0 mobil • Total Rp 0</span></div>
                </div>

                <div class="flex items-center justify-between pt-6 mt-4 border-t border-gray-100">
                    <div class="text-xs text-gray-500 italic">Harga yg diubah di sini tidak merubah boneless yg sudah tersimpan.</div>
                    <div class="flex gap-3">
                        <button type="submit" name="simpan" class="inline-flex items-center px-6 py-2.5 border-0 text-sm font-bold rounded-xl shadow-md text-white bg-blue-600 hover:bg-blue-700 transition duration-150 ease-in-out">
                            <i class="fas fa-save mr-2"></i> Simpan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const tarifAcuan = <?= (int)$tarif_acuan ?>;
function fmtMobRp(el) {
    const d = (el.value || '').replace(/[^0-9]/g, '').slice(0, 15);
    el.value = d === '' ? '' : Number(d).toLocaleString('id-ID');
}
function updMobSummary() {
    let n = 0, tot = 0;
    document.querySelectorAll('#tableArmada .arm-row').forEach(function(tr) {
        const nm = tr.querySelector('input[name="mob_nama[]"]');
        if (nm && nm.value.trim() !== '') {
            n++;
            const h = tr.querySelector('.mob-harga-fmt');
            tot += parseInt((h ? h.value : '0').replace(/[^0-9]/g, '') || '0', 10);
        }
    });
    const el = document.getElementById('mobSummary');
    if (el) el.textContent = n + ' mobil • Total Rp ' + tot.toLocaleString('id-ID');
}
document.getElementById('addArmadaRow').addEventListener('click', function() {
    const n = document.querySelectorAll('#tableArmada .arm-row').length + 1;
    const tbody = document.querySelector('#tableArmada tbody');
    const tr = document.createElement('tr');
    tr.className = 'arm-row';
    tr.innerHTML = `
        <input type="hidden" name="mob_id[]" value="0">
        <td class="text-center font-bold text-gray-400">${n}</td>
        <td><input type="text" name="mob_nama[]" class="form-control" value="TRUCK ${n}" required></td>
        <td>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-bold text-sm pointer-events-none">Rp</span>
                <input type="text" name="mob_harga[]" class="form-control text-right font-bold mob-harga-fmt pl-9" value="${tarifAcuan ? tarifAcuan.toLocaleString('id-ID') : '0'}" inputmode="numeric">
            </div>
        </td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rm-arm"><i class="fas fa-times"></i></button></td>
    `;
    tbody.appendChild(tr);
    updMobSummary();
});
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('mob-harga-fmt')) { fmtMobRp(e.target); updMobSummary(); }
    if (e.target.getAttribute && e.target.getAttribute('name') === 'mob_nama[]') updMobSummary();
});
document.addEventListener('click', function(e) {
    if (e.target.closest('.rm-arm')) { e.target.closest('tr').remove(); updMobSummary(); }
});
document.querySelectorAll('.mob-harga-fmt').forEach(fmtMobRp);
updMobSummary();
</script>
