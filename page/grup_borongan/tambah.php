<?php
include __DIR__ . '/_setup.php';

$q_kar = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' ORDER BY nama_karyawan ASC");
$list_kar = [];
if ($q_kar) while ($r = $q_kar->fetch_assoc()) $list_kar[] = $r;

$opt_html = '<option value="">-- Pilih Karyawan --</option>';
foreach ($list_kar as $k) {
    $opt_html .= '<option value="' . $k['id_karyawan'] . '">' . htmlspecialchars($k['nama_karyawan']) . ' (' . htmlspecialchars($k['no_absen']) . ')</option>';
}

$simpan = @$_POST['simpan'];

if ($simpan) {
    $nama_grup = $koneksi->real_escape_string(trim(@$_POST['nama_grup']));
    $ht = trim(@$_POST['harga'] ?? '');
    $harga_sql = ($ht === '') ? 'NULL' : (int)$ht;

    $sql = $koneksi->query("INSERT INTO ms_grup_borongan (nama_grup, harga, tgl_updt) VALUES ('$nama_grup', $harga_sql, NOW())");
    $id_grup = $koneksi->insert_id;

    if ($sql && $id_grup) {
        $kids = $_POST['id_karyawan'] ?? [];
        $tarifs = $_POST['tarif'] ?? [];
        $seen = [];
        for ($i = 0; $i < count($kids); $i++) {
            $kid = (int)($kids[$i] ?? 0);
            if ($kid <= 0 || isset($seen[$kid])) continue;
            $seen[$kid] = true;
            $t = trim($tarifs[$i] ?? '');
            $t_sql = ($t === '') ? 'NULL' : (int)$t;
            $koneksi->query("INSERT INTO ms_grup_borongan_anggota (id_grup, id_karyawan, tarif) VALUES ('$id_grup', '$kid', $t_sql)");
        }
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
            <script>
                Swal.fire({ icon: "success", title: "Berhasil", text: "Grup berhasil disimpan", confirmButtonColor: "#2563eb" })
                .then(() => { window.location.href = "?page=grup_borongan"; });
            </script></body></html>';
        exit;
    }
}
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
                        <h3 class="text-xl font-bold m-0" style="color: #2563eb;">Tambah Grup</h3>
                    </div>
                </div>

                <div class="p-4 md:p-5">
                    <form method="POST">
                        <div class="row mb-4">
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Nama Grup</label>
                                <input type="text" name="nama_grup" class="form-control h-[42px]" placeholder="cth: DADA SP SHILIN" required autocomplete="off">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="text-xs font-bold text-gray-700 uppercase mb-1">Tarif Grup /Kg (Rp) — Default Anggota</label>
                                <input type="number" name="harga" id="harga_grup" class="form-control h-[42px]" placeholder="Kosongkan bila bukan borongan">
                            </div>
                        </div>

                        <label class="text-xs font-bold text-gray-700 uppercase mb-3 block">Anggota (tarif kosong = ikut tarif grup)</label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tableAgt">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">#</th>
                                        <th>Karyawan</th>
                                        <th style="width: 200px;">Tarif /Kg (Rp)</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="agt-row">
                                        <td class="text-center font-bold text-gray-400">-</td>
                                        <td><select name="id_karyawan[]" class="form-control"><?= $opt_html ?></select></td>
                                        <td><input type="number" name="tarif[]" class="form-control text-center tarif-input" placeholder="Ikut grup"></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" id="addRow" class="btn btn-sm btn-outline-success font-bold"><i class="fas fa-plus mr-1"></i> Tambah Karyawan</button>
                        </div>

                        <div class="mt-8 flex gap-3">
                            <button type="submit" name="simpan" value="simpan" class="px-4 py-2 border-0 bg-indigo-600 text-white rounded-lg font-bold shadow-md hover:bg-indigo-700"><i class="fas fa-save md:mr-2"></i>Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const KAR_OPT = <?= json_encode($opt_html) ?>;
    document.addEventListener('DOMContentLoaded', function() {
        const hargaGrup = document.getElementById('harga_grup');
        function syncPlaceholders() {
            const v = hargaGrup.value || 'Ikut grup';
            document.querySelectorAll('.tarif-input').forEach(function(el) { el.placeholder = v; });
        }
        hargaGrup.addEventListener('input', syncPlaceholders);
        syncPlaceholders();

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
