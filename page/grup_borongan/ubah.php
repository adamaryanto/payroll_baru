<?php
include __DIR__ . '/_setup.php';

$id = $_GET['id'] ?? 0;

$sql_header = $koneksi->query("SELECT * FROM ms_grup_borongan WHERE id_grup = '$id'");
$header = $sql_header ? $sql_header->fetch_assoc() : null;
if (!$header) {
    echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
        <script>Swal.fire({ icon: "error", title: "Gagal", text: "Grup tidak ditemukan" }).then(() => { window.location.href = "?page=grup_borongan"; });</script></body></html>';
    exit;
}
$harga_grup = ($header['harga'] === null || $header['harga'] === '') ? null : (int)$header['harga'];
$label_grup = ($harga_grup === null) ? 'tanpa tarif' : 'Rp ' . number_format($harga_grup, 0, ',', '.');

// Anggota yang sudah ada
$sql_agt = $koneksi->query("SELECT a.*, k.nama_karyawan, k.no_absen FROM ms_grup_borongan_anggota a LEFT JOIN ms_karyawan k ON a.id_karyawan = k.id_karyawan WHERE a.id_grup = '$id' ORDER BY k.nama_karyawan ASC");
$anggota = [];
$agt_ids = [];
while ($row = $sql_agt->fetch_assoc()) { $anggota[] = $row; $agt_ids[] = (int)$row['id_karyawan']; }

// Pilihan karyawan: yang aktif + yang sudah jadi anggota (biar tidak hilang)
$in = $agt_ids ? " OR id_karyawan IN (" . implode(',', array_map('intval', $agt_ids)) . ")" : "";
$q_kar = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' $in ORDER BY nama_karyawan ASC");
$list_kar = [];
if ($q_kar) while ($r = $q_kar->fetch_assoc()) $list_kar[] = $r;

$simpan = @$_POST['simpan'];
if ($simpan) {
    $nama_grup = $koneksi->real_escape_string(trim(@$_POST['nama_grup']));
    $ht = trim(@$_POST['harga'] ?? '');
    $harga_sql = ($ht === '') ? 'NULL' : (int)$ht;

    $sql = $koneksi->query("UPDATE ms_grup_borongan SET nama_grup = '$nama_grup', harga = $harga_sql, tgl_updt = NOW() WHERE id_grup = '$id'");
    if ($sql) {
        $koneksi->query("DELETE FROM ms_grup_borongan_anggota WHERE id_grup = '$id'");
        $kids = $_POST['id_karyawan'] ?? [];
        $tarifs = $_POST['tarif'] ?? [];
        $seen = [];
        for ($i = 0; $i < count($kids); $i++) {
            $kid = (int)($kids[$i] ?? 0);
            if ($kid <= 0 || isset($seen[$kid])) continue;
            $seen[$kid] = true;
            $t = trim($tarifs[$i] ?? '');
            $t_sql = ($t === '') ? 'NULL' : (int)$t;
            $koneksi->query("INSERT INTO ms_grup_borongan_anggota (id_grup, id_karyawan, tarif) VALUES ('$id', '$kid', $t_sql)");
        }
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
            <script>
                Swal.fire({ icon: "success", title: "Berhasil", text: "Grup berhasil diubah", confirmButtonColor: "#2563eb" })
                .then(() => { window.location.href = "?page=grup_borongan"; });
            </script></body></html>';
        exit;
    }
}

function opt_kar($list_kar, $selected) {
    $h = '<option value="">-- Pilih Karyawan --</option>';
    foreach ($list_kar as $k) {
        $sel = ((int)$k['id_karyawan'] === (int)$selected) ? ' selected' : '';
        $h .= '<option value="' . $k['id_karyawan'] . '"' . $sel . '>' . htmlspecialchars($k['nama_karyawan']) . ' (' . htmlspecialchars($k['no_absen']) . ')</option>';
    }
    return $h;
}
$opt_all = opt_kar($list_kar, 0);
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">
                <div class="border-b border-gray-100 py-4 px-4 bg-white flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <a href="?page=grup_borongan" class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-50 text-gray-400 border border-gray-200">
                            <i class="fas fa-arrow-left text-xs"></i>
                        </a>
                        <h3 class="text-xl font-bold m-0" style="color: #2563eb;">Ubah Grup</h3>
                    </div>
                    <span class="text-xs font-bold text-gray-400 bg-gray-50 px-2 py-1 rounded">ID: #<?= $id ?></span>
                </div>

                <div class="p-4 md:p-5">
                    <form method="POST">
                        <div class="row mb-4">
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Nama Grup</label>
                                <input type="text" name="nama_grup" class="form-control h-[42px]" value="<?= htmlspecialchars($header['nama_grup']) ?>" required autocomplete="off">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Tarif Grup /Kg (Rp) — Default Anggota</label>
                                <input type="number" name="harga" id="harga_grup" class="form-control h-[42px]" placeholder="Kosongkan bila bukan borongan" value="<?= ($harga_grup === null) ? '' : $harga_grup ?>">
                            </div>
                        </div>

                        <label class="text-xs font-bold text-gray-700 uppercase mb-3 block">Anggota (tarif kosong = ikut tarif grup<?= ($harga_grup === null) ? '' : ' Rp ' . number_format($harga_grup, 0, ',', '.') ?>)</label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tableAgt">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">#</th>
                                        <th>Karyawan</th>
                                        <th style="width: 220px;">Tarif /Kg (Rp)</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($anggota)): ?>
                                    <tr class="agt-row">
                                        <td class="text-center font-bold text-gray-400">-</td>
                                        <td><select name="id_karyawan[]" class="form-control"><?= $opt_all ?></select></td>
                                        <td><input type="number" name="tarif[]" class="form-control text-center tarif-input" placeholder="<?= $harga_grup ?>"></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                    <?php else: $n = 1; foreach ($anggota as $ag):
                                        $custom = ($ag['tarif'] !== null && $ag['tarif'] !== '') ? (int)$ag['tarif'] : null;
                                        $efektif = ($custom !== null) ? $custom : $harga_grup;
                                        if ($efektif === null) { $ef_txt = 'Efektif: - (tanpa tarif)'; $ef_cls = 'text-gray-400'; }
                                        elseif ($custom !== null) { $ef_txt = 'Efektif: Rp ' . number_format($efektif, 0, ',', '.') . '/kg (khusus)'; $ef_cls = 'text-indigo-600 font-bold'; }
                                        else { $ef_txt = 'Efektif: Rp ' . number_format($efektif, 0, ',', '.') . '/kg (ikut grup)'; $ef_cls = 'text-gray-400'; }
                                    ?>
                                    <tr class="agt-row">
                                        <td class="text-center font-bold text-gray-400"><?= $n++ ?></td>
                                        <td>
                                            <select name="id_karyawan[]" class="form-control"><?= opt_kar($list_kar, $ag['id_karyawan']) ?></select>
                                            <div class="text-xs mt-1 <?= $ef_cls ?>">
                                                <?= $ef_txt ?>
                                            </div>
                                        </td>
                                        <td><input type="number" name="tarif[]" class="form-control text-center tarif-input" placeholder="<?= $harga_grup ?>" value="<?= ($ag['tarif'] === null) ? '' : (int)$ag['tarif'] ?>"></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                            <button type="button" id="addRow" class="btn btn-sm btn-outline-success font-bold"><i class="fas fa-plus mr-1"></i> Tambah Karyawan</button>
                        </div>

                        <div class="mt-8 flex gap-3">
                            <button type="submit" name="simpan" value="simpan" class="px-4 py-2 border-0 bg-indigo-600 text-white rounded-lg font-bold shadow-md hover:bg-indigo-700"><i class="fas fa-save md:mr-2"></i>Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const KAR_OPT = <?= json_encode($opt_all) ?>;
    document.addEventListener('DOMContentLoaded', function() {
        const hargaGrup = document.getElementById('harga_grup');
        function syncPlaceholders() {
            const v = hargaGrup.value || 'Ikut grup';
            document.querySelectorAll('.tarif-input').forEach(function(el) { el.placeholder = v; });
        }
        hargaGrup.addEventListener('input', syncPlaceholders);

        document.getElementById('addRow').addEventListener('click', function() {
            const tbody = document.querySelector('#tableAgt tbody');
            const tr = document.createElement('tr');
            tr.className = 'agt-row';
            tr.innerHTML = `
                <td class="text-center font-bold text-gray-400">-</td>
                <td><select name="id_karyawan[]" class="form-control">${KAR_OPT}</select></td>
                <td><input type="number" name="tarif[]" class="form-control text-center tarif-input" placeholder="${hargaGrup.value || 'Ikut grup'}"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row w-full"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(tr);
        });
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                const rows = document.querySelectorAll('#tableAgt tbody tr');
                if (rows.length > 1) e.target.closest('tr').remove();
            }
        });
    });
</script>
