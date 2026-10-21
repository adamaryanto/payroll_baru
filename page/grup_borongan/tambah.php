<?php
include __DIR__ . '/_setup.php';

$q_kar = $koneksi->query("SELECT id_karyawan, no_absen, nama_karyawan FROM ms_karyawan WHERE status_karyawan='Aktif' AND nama_karyawan NOT LIKE 'User PIN%' ORDER BY nama_karyawan ASC");
$list_kar = [];
if ($q_kar) while ($r = $q_kar->fetch_assoc()) $list_kar[] = $r;

$opt_html = '<option value="">-- Pilih Karyawan --</option>';
foreach ($list_kar as $k) {
    $opt_html .= '<option value="' . $k['id_karyawan'] . '">' . htmlspecialchars($k['nama_karyawan']) . ' (' . htmlspecialchars($k['no_absen']) . ')</option>';
}

$simpan = @$_POST['simpan'];

if ($simpan) {
    $nama_grup = $koneksi->real_escape_string(trim(@$_POST['nama_grup']));
    $is_b = (isset($_POST['is_borongan']) && $_POST['is_borongan'] == '1') ? 1 : 0;
    $ht = trim(@$_POST['harga'] ?? '');
    $harga_sql = ($ht === '') ? 'NULL' : (int)$ht;

    $sql = $koneksi->query("INSERT INTO ms_grup_borongan (nama_grup, harga, is_borongan, tgl_updt) VALUES ('$nama_grup', $harga_sql, '$is_b', NOW())");
    $id_grup = $koneksi->insert_id;

    if ($sql && $id_grup) {
        // Anggota + flag borongan per orang (default mati)
        $kids = $_POST['id_karyawan'] ?? [];
        $flags = $_POST['is_bor'] ?? [];
        $seen = [];
        foreach ($kids as $i => $kid_raw) {
            $kid = (int)$kid_raw;
            if ($kid <= 0 || isset($seen[$kid])) continue;
            $seen[$kid] = true;
            $fb = isset($flags[$i]) ? 1 : 0;
            $koneksi->query("INSERT IGNORE INTO ms_grup_borongan_anggota (id_grup, id_karyawan, tarif, is_borongan) VALUES ('$id_grup', '$kid', NULL, '$fb')");
        }
        // Rangkap grup: anggota boleh ada di banyak grup + samakan baris rencana/realisasi draft.
        $gid = (int)$id_grup;
        $newkids = array_keys($seen);
        $sync = grup_sync_drafts($koneksi, $gid, $newkids);
        $n_rkk = (int)$sync['rkk']; $n_real = (int)$sync['realisasi'];
        $info = 'Grup berhasil disimpan.';
        if ($n_rkk > 0 || $n_real > 0) $info .= " Rencana ($n_rkk baris) + realisasi ($n_real baris) yang belum approve disesuaikan (tiap anggota ada baris di grup ini).";
        echo '<!DOCTYPE html><html><head><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>
            <script>
                Swal.fire({ icon: "success", title: "Berhasil", text: "' . $info . '", confirmButtonColor: "#2563eb" })
                .then(() => { window.location.href = "?page=grup_borongan"; });
            </script></body></html>';
        exit;
    }
}
?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
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
                        <div class="form-group mb-4">
                            <label class="text-xs font-bold text-gray-700 uppercase mb-1">Nama Grup</label>
                            <input type="text" name="nama_grup" class="form-control h-[42px]" placeholder="cth: DADA SP SHILIN" required autocomplete="off">
                        </div>
                        <div class="form-group mb-4">
                            <label class="text-xs font-bold text-gray-700 uppercase mb-1">Tarif /Kg (Rp)</label>
                            <input type="number" name="harga" class="form-control h-[42px]" placeholder="Kosongkan bila bukan borongan">
                            <p class="text-xs text-gray-400 mt-1">Tarif per karyawan otomatis ikut tarif ini; ubah per barisnya di popup borongan.</p>
                        </div>

                        <div class="form-group mb-4">
                            <label class="text-xs font-bold text-gray-700 uppercase mb-1">Default Borongan Anggota</label>
                            <div class="flex gap-6">
                                <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                    <input type="radio" name="is_borongan" value="1" class="w-4 h-4"> Semua borongan
                                </label>
                                <label class="flex items-center gap-2 text-sm font-bold text-gray-700 cursor-pointer">
                                    <input type="radio" name="is_borongan" value="0" checked class="w-4 h-4"> Atur per orang
                                </label>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Memilih radio langsung mencentang/melepas semua baris di bawah (masih bisa diubah satu-satu). Baris baru ikut pilihan ini.</p>
                        </div>

                        <label class="text-xs font-bold text-gray-700 uppercase mb-3 block">Anggota (default ikut radio di atas, bisa beda per orang)</label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tableAgt">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">#</th>
                                        <th>Karyawan</th>
                                        <th class="text-center" style="width: 130px;">Borongan</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="agt-row">
                                        <td class="text-center font-bold text-gray-400">-</td>
                                        <td><select name="id_karyawan[0]" class="form-control"><?= $opt_html ?></select></td>
                                        <td class="text-center"><label class="inline-flex items-center gap-1 text-xs font-bold text-gray-600 cursor-pointer"><input type="checkbox" name="is_bor[0]" value="1" class="w-4 h-4 bor-flag"> Ya</label></td>
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
        let agtIdx = 1;
        function radioBorongan() {
            const on = document.querySelector('input[name="is_borongan"]:checked');
            return on && on.value === '1';
        }
        document.querySelectorAll('input[name="is_borongan"]').forEach(function(r) {
            r.addEventListener('change', function() {
                const v = radioBorongan();
                document.querySelectorAll('#tableAgt .bor-flag').forEach(function(cb) { cb.checked = v; });
            });
        });
        document.getElementById('addRow').addEventListener('click', function() {
            const tbody = document.querySelector('#tableAgt tbody');
            const tr = document.createElement('tr');
            tr.className = 'agt-row';
            tr.innerHTML = `
                <td class="text-center font-bold text-gray-400">-</td>
                <td><select name="id_karyawan[${agtIdx}]" class="form-control">${KAR_OPT}</select></td>
                <td class="text-center"><label class="inline-flex items-center gap-1 text-xs font-bold text-gray-600 cursor-pointer"><input type="checkbox" name="is_bor[${agtIdx}]" value="1" class="w-4 h-4 bor-flag"${radioBorongan() ? ' checked' : ''}> Ya</label></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row w-full"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(tr);
            agtIdx++;
        });
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                const rows = document.querySelectorAll('#tableAgt tbody tr');
                if (rows.length > 1) e.target.closest('tr').remove();
            }
        });
    });
</script>
