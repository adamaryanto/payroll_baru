<?php
// 2026-10-26 (koreksi): Hapus by checklist INLINE di List ini, bukan halaman terpisah.
// Checkbox ada di samping kolom No (hanya untuk Kepala Pabrik + Admin Master).
// POST ini diproses SEBELUM query list, jadi baris yang dihapus langsung hilang dari list.
$hapus_hasil_real = '';
if (isset($_POST['hapus_terpilih'])) {
    if (!in_array(strtolower($_SESSION['role'] ?? ''), ["kepala pabrik", "admin master"], true)) {
        $hapus_hasil_real = 'error';
    } elseif (empty($_POST['pilih_id']) || !is_array($_POST['pilih_id'])) {
        $hapus_hasil_real = 'kosong';
    } else {
        $hapus_cnt_real = 0;
        foreach ($_POST['pilih_id'] as $hid) {
            $hid = (int)$hid;
            if ($hid <= 0) continue;
            $koneksi->query("DELETE b FROM tb_realisasi_borongan b JOIN tb_realisasi_detail d ON b.id_realisasi_detail = d.id_realisasi_detail WHERE d.id_realisasi = $hid");
            $koneksi->query("DELETE FROM tb_realisasi_detail WHERE id_realisasi = $hid");
            if ($koneksi->query("DELETE FROM tb_realisasi WHERE id_realisasi = $hid")) $hapus_cnt_real++;
        }
        $hapus_hasil_real = 'ok:' . $hapus_cnt_real;
    }
}
// 2026-10-24: index untuk kecepatan list Realisasi. tb_realisasi_detail tadinya
// HANYA punya primary key id_realisasi_detail, tanpa index id_realisasi — jadi
// setiap hitung total per Realisasi harus full scan seluruh tabel detail, dan
// biayanya membesar tiap hari. Index ini dibuat otomatis sekali saja saat halaman
// dibuka (cek dulu, kalau sudah ada tidak diulang). Alternatif manual ada di
// file SQL yang ikut dikirim bersama perbaikan ini.
$__idx_real = $koneksi->query("SHOW INDEX FROM tb_realisasi_detail WHERE Key_name = 'idx_realisasi'");
if ($__idx_real && $__idx_real->num_rows == 0) {
    @$koneksi->query("ALTER TABLE tb_realisasi_detail ADD INDEX idx_realisasi (id_realisasi)");
}
// 2026-10-26 (koreksi): AUTO-HAPUS DOBEL DIHAPUS TOTAL. List ini TIDAK lagi
// menghapus record apa pun saat dibuka. Dobel yang sudah ada di DB tetap ada di DB,
// tapi di list ini hanya DITAMPILKAN SATU per tanggal (lihat filter tampil di bawah:
// yang APPROVED dulu, kalau imbang yang tertua). Jadi dobel "tiba-tiba jadi satu"
// di tampilan, tanpa hapus. Pembersihan DB yang sesungguhnya, kalau memang mau,
// harus lewat Hapus Terpilih (Kepala Pabrik / Admin Master) secara sadar, bukan
// otomatis saat list dibuka.
// Query utama
// Baris rangkap (1 orang di 2 grup) dihitung 1x: yang dipakai baris pertama (MIN id_realisasi_detail) per orang.
// Kunci orang: id_karyawan untuk karyawan master, nama manual untuk karyawan manual.
$person_key_rd = "IF(RD.id_karyawan = 0, CONCAT('M:', IFNULL(RD.nama_karyawan_manual, '')), CONCAT('K:', RD.id_karyawan))";
$person_key_d2 = "IF(d2.id_karyawan = 0, CONCAT('M:', IFNULL(d2.nama_karyawan_manual, '')), CONCAT('K:', d2.id_karyawan))";
$first_row = "RD.id_realisasi_detail = (SELECT MIN(d2.id_realisasi_detail) FROM tb_realisasi_detail d2 WHERE d2.id_realisasi = RD.id_realisasi AND $person_key_d2 = $person_key_rd)";
// Filter tanggal (2026-10-24, dibalik dari aturan sebelumnya): DEFAULT = Semua Tanggal.
// Buka list Realisasi tanpa ?tgl= (misal dari sidebar) = tampil semua tanggal, tanpa filter.
// Pilih tanggal spesifik di dropdown = filter ke tanggal itu saja (dipakai juga oleh
// tombol Dashboard untuk tanggal yang punya >1 realisasi). Tanggal tidak valid /
// tidak ada di Realisasi = kembali ke Semua, bukan loncat ke tanggal lain.
// $peta_tgl: tanggal => [id realisasi terbaru tanggal itu, jumlah realisasi tanggal itu].
// Dipakai dropdown + lompat langsung ke Kelola kalau tanggal itu cuma punya 1 realisasi.
$peta_tgl = [];
// 2026-10-26 (koreksi): peta tanggal kini mengikuti FILTER TAMPIL, bukan jumlah DB.
// Setiap tanggal dihitung 1 (satu baris tampil), id-nya = baris yang ditampilkan
// (APPROVED dulu, kalau imbang tertua). Jadi dropdown tidak lagi bilang "(2 realisasi)"
// dan pilih tanggal langsung loncat ke Kelola baris tampil itu.
$q_tgl = $koneksi->query("SELECT T.tgl_realisasi, 1 AS jml, (SELECT R2.id_realisasi FROM tb_realisasi R2 WHERE R2.tgl_realisasi = T.tgl_realisasi ORDER BY R2.status_realisasi DESC, R2.id_realisasi ASC LIMIT 1) AS id_terbaru FROM (SELECT DISTINCT tgl_realisasi FROM tb_realisasi WHERE tgl_realisasi IS NOT NULL) T ORDER BY T.tgl_realisasi DESC");
if ($q_tgl) {
    while ($r_tgl = $q_tgl->fetch_assoc()) {
        if (!empty($r_tgl['tgl_realisasi'])) {
            $peta_tgl[$r_tgl['tgl_realisasi']] = ['id' => (int)$r_tgl['id_terbaru'], 'jml' => (int)$r_tgl['jml']];
        }
    }
}
$daftar_tgl = array_keys($peta_tgl);
$filter_tgl = $_GET['tgl'] ?? 'all';
if ($filter_tgl === '' || $filter_tgl === 'all' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_tgl) || !isset($peta_tgl[$filter_tgl])) {
    $filter_tgl = 'all';
}
// Filter tampil (tanpa hapus): hanya 1 baris per tanggal — yang APPROVED dulu,
// kalau imbang yang tertua. Baris dengan tgl NULL (kalau ada) tetap tampil semua,
// karena tidak bisa digabung per tanggal.
$where_tampil = "(A.tgl_realisasi IS NULL OR A.id_realisasi = (SELECT R2.id_realisasi FROM tb_realisasi R2 WHERE R2.tgl_realisasi = A.tgl_realisasi ORDER BY R2.status_realisasi DESC, R2.id_realisasi ASC LIMIT 1))";
$where_real = " WHERE " . $where_tampil;
if ($filter_tgl !== 'all') {
    $where_real .= " AND A.tgl_realisasi = '" . $koneksi->real_escape_string($filter_tgl) . "'";
}
// 2026-10-24: query total dirapikan. Sebelumnya tiap baris Realisasi dihitung
// dengan 6 subquery terpisah (ttl, potlainnya, p_telat, p_istirahat, p_pulang,
// p_log), masing-masing men-scan tb_realisasi_detail + subquery MIN per baris.
// Sekarang keenam total itu dihitung SEKALI dalam satu agregasi GROUP BY per
// id_realisasi (tabel turunan S), dengan baris-pertama-per-orang yang SAMA
// persis seperti dulu: MIN(id_realisasi_detail) per (id_realisasi, kunci orang),
// dihitung tanpa filter Digantikan dulu, baru filter Digantikan diterapkan —
// jadi nilai ttl/potongan yang keluar identik dengan query lama, hanya jauh
// lebih sedikit scan. jml sengaja TETAP subquery sendiri karena definisinya
// beda: hitung DISTINCT orang atas semua baris non-Digantikan (bukan cuma
// baris pertama), jadi tidak boleh disamakan dengan agregasi di bawah.
$tampil = $koneksi->query("SELECT A.*, S.ttl, S.potlainnya, S.p_telat, S.p_istirahat, S.p_pulang, S.p_log,
    (SELECT COUNT(DISTINCT $person_key_rd)
     FROM tb_realisasi_detail RD
     LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail
     WHERE RD.id_realisasi = A.id_realisasi AND (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')
    ) as jml
    FROM tb_realisasi A
    LEFT JOIN (
        SELECT RD.id_realisasi AS sid,
            SUM((IFNULL(RD.r_upah, 0) + IFNULL(RD.lembur, 0)) -
                (IFNULL(RD.r_potongan_telat, 0) +
                 IFNULL(RD.r_potongan_istirahat_awal, 0) +
                 IFNULL(RD.r_potongan_istirahat_telat, 0) +
                 IFNULL(RD.r_potongan_pulang, 0) +
                 IFNULL(RD.r_potongan_tidak_lengkap, 0) +
                 IFNULL(RD.r_potongan_lainnya, 0))) AS ttl,
            SUM(IFNULL(RD.r_potongan_lainnya, 0)) AS potlainnya,
            SUM(IFNULL(RD.r_potongan_telat, 0)) AS p_telat,
            SUM(IFNULL(RD.r_potongan_istirahat_awal, 0) + IFNULL(RD.r_potongan_istirahat_telat, 0)) AS p_istirahat,
            SUM(IFNULL(RD.r_potongan_pulang, 0)) AS p_pulang,
            SUM(IFNULL(RD.r_potongan_tidak_lengkap, 0)) AS p_log
        FROM tb_realisasi_detail RD
        JOIN (SELECT id_realisasi AS fid,
                     IF(id_karyawan = 0, CONCAT('M:', IFNULL(nama_karyawan_manual, '')), CONCAT('K:', id_karyawan)) AS pk,
                     MIN(id_realisasi_detail) AS first_id
              FROM tb_realisasi_detail GROUP BY id_realisasi, pk) FM
            ON FM.fid = RD.id_realisasi
            AND FM.pk = IF(RD.id_karyawan = 0, CONCAT('M:', IFNULL(RD.nama_karyawan_manual, '')), CONCAT('K:', RD.id_karyawan))
            AND FM.first_id = RD.id_realisasi_detail
        LEFT JOIN tb_rkk_detail RKD ON RD.id_rkk_detail = RKD.id_rkk_detail
        WHERE (RKD.id_rkk_detail IS NULL OR RKD.status_rkk != 'Digantikan')
        GROUP BY RD.id_realisasi
    ) S ON S.sid = A.id_realisasi
    $where_real ORDER BY A.tgl_realisasi ASC, A.id_realisasi ASC");

// 2026-10-26 (koreksi): Hapus Masal dihapus untuk SEMUA role; diganti Hapus by
// checklist INLINE di list ini (checkbox di samping kolom No) hanya untuk
// Kepala Pabrik + Admin Master.
// Approve/Unapprove Realisasi: Owner + Admin Master + Kepala Pabrik.
// Admin HR TIDAK bisa Approve (sebelumnya kebalik: Admin HR bisa, Kepala Pabrik tidak).
$is_admin_hr = (strtolower($_SESSION['role'] ?? '') == "admin hr");
$is_authorized = in_array(strtolower($_SESSION['role'] ?? ''), ["owner", "admin master", "kepala pabrik"], true);
$is_hapus_checklist = in_array(strtolower($_SESSION['role'] ?? ''), ["kepala pabrik", "admin master"], true);
$level_status = (!$is_authorized) ? "hidden" : "";

?>

<div class="container-fluid px-2 mt-4 mb-4">
    <div class="card border-0 shadow-sm rounded-xl overflow-hidden bg-white">

        <div class="border-b border-gray-100 py-4 px-4 md:px-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-3 bg-white">
            <div>
                <h3 class="text-xl text-blue-600 font-bold m-0"><i class="fas fa-file-invoice-dollar mr-2"></i>List Realisasi Upah</h3>
            </div>
            <div class="flex flex-wrap md:flex-nowrap gap-2 w-full md:w-auto">
                <a href="?page=boneless&ref=realisasi&view=1" class="flex-1 md:flex-none justify-center inline-flex items-center bg-amber-500 hover:bg-amber-600 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors">
                    <i class="fas fa-drumstick-bite mr-1.5"></i> Boneless
                </a>
                <!-- 2026-10-26 (koreksi): Tombol Hapus Masal DIHAPUS untuk semua role.
                     Penggantinya: Hapus Terpilih INLINE (checkbox di samping kolom No)
                     hanya untuk Kepala Pabrik + Admin Master. -->
                <a href="?page=realisasi&aksi=rkk" class="flex-1 md:flex-none justify-center inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white text-[15px] font-medium py-2 px-4 rounded shadow-sm transition-colors">
                    <i class="fas fa-plus mr-1.5"></i> Tambah Data
                </a>
                <?php if ($is_hapus_checklist): ?>
                <button type="button" id="btnHapusTerpilihReal" onclick="hapusTerpilihReal()" class="flex-1 md:flex-none justify-center inline-flex items-center bg-rose-600 hover:bg-rose-700 text-white text-[15px] font-medium py-2.5 px-5 rounded shadow-sm transition-colors border-0">
                    <i class="fas fa-check-square mr-1.5"></i> Hapus Terpilih
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter tanggal: DEFAULT = Semua Tanggal. Pilih tanggal spesifik untuk filter
             ke tanggal itu saja. Tanggal yang cuma punya 1 realisasi = langsung masuk
             Kelola realisasi itu (via JS di bawah). Tanggal yang punya >1 realisasi
             (misal 31/03/2026 ada 2 baris) = tampil list tanggal itu dulu, pilih barisnya. -->
        <div class="px-4 md:px-5 py-3 bg-gray-50 border-b border-gray-100">
            <form id="form-filter-tgl" method="GET" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="page" value="realisasi">
                <label for="filter-tgl" class="text-[13px] font-bold text-gray-700"><i class="fas fa-calendar-alt mr-1"></i> Tanggal Realisasi:</label>
                <select id="filter-tgl" name="tgl" class="text-[14px] px-3 py-2 border border-gray-300 rounded-lg bg-white outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="all" <?= ($filter_tgl === 'all') ? 'selected' : '' ?>>Semua Tanggal</option>
                    <?php foreach ($daftar_tgl as $tgl_opt) : ?>
                        <option value="<?= htmlspecialchars($tgl_opt) ?>" <?= ($tgl_opt === $filter_tgl) ? 'selected' : '' ?>><?= date('d/m/Y', strtotime($tgl_opt)) ?><?= (isset($peta_tgl[$tgl_opt]) && $peta_tgl[$tgl_opt]['jml'] > 1) ? ' (' . $peta_tgl[$tgl_opt]['jml'] . ' realisasi)' : '' ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($daftar_tgl)) : ?>
                        <option value="" disabled>Belum ada Realisasi</option>
                    <?php endif; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[13px] font-bold rounded-lg">Buka</button>
                <?php if ($filter_tgl === 'all') : ?>
                    <span class="text-[13px] text-gray-500">Menampilkan semua tanggal</span>
                <?php else : ?>
                    <span class="text-[13px] text-gray-500">Menampilkan tanggal <?= date('d/m/Y', strtotime($filter_tgl)) ?><?= (isset($peta_tgl[$filter_tgl]) && $peta_tgl[$filter_tgl]['jml'] > 1) ? ' — tanggal ini ada ' . $peta_tgl[$filter_tgl]['jml'] . ' realisasi, pilih baris di bawah' : '' ?></span>
                <?php endif; ?>
            </form>
            <script>
                (function () {
                    var sel = document.getElementById('filter-tgl');
                    var form = document.getElementById('form-filter-tgl');
                    if (!sel || !form) return;
                    var peta = <?= json_encode($peta_tgl) ?>;
                    function keKelolaJikaTunggal() {
                        var info = peta[sel.value];
                        if (info && info.jml == 1) {
                            window.location.href = '?page=realisasi&aksi=kelola&id=' + info.id;
                            return true;
                        }
                        return false;
                    }
                    sel.addEventListener('change', keKelolaJikaTunggal);
                    form.addEventListener('submit', function (e) {
                        if (keKelolaJikaTunggal()) e.preventDefault();
                    });
                })();
            </script>
        </div>

        <div class="p-0">
            <div class="table-responsive px-3 py-3">
                <table class="w-full text-left border-collapse table-modern" id="dataTables-example">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <?php if ($is_hapus_checklist): ?><th class="py-2 px-2 text-center w-10"><input type="checkbox" id="select_all_hapus_real" class="w-4 h-4 cursor-pointer" title="Pilih semua"></th><?php endif; ?>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center w-8">No</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle">Tanggal</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle">Keterangan</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center">Jumlah Karyawan</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right">Total Upah</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Potongan Telat">Telat</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Potongan Istirahat">Istirahat</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Denda Pulang Awal">Denda Pulang</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Denda Absensi Tidak Lengkap">Denda Tidak Absen</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-right" title="Potongan Lainnya">Lainnya</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center w-32">Aksi</th>
                            <th class="py-2 px-2 text-[13px] font-bold text-gray-700 uppercase align-middle text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $no = 1;
                        while ($data = $tampil->fetch_assoc()) :
                            if ($data['status_realisasi'] >= 2) {
                                $row_class = "bg-slate-50/40";
                                $status_badge = '<span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 text-[13px] font-bold tracking-wide">ACC</span>';
                            } else {
                                $row_class = "hover:bg-gray-50 transition-colors";
                                $status_badge = '<span class="px-2 py-1 rounded bg-amber-100 text-amber-800 text-[13px] font-bold tracking-wide">PEND</span>';
                            }
                        ?>
                            <tr class="<?= $row_class ?>">
                                <?php if ($is_hapus_checklist): ?><td class="py-2 px-2 text-center align-middle"><input type="checkbox" class="check_item_hapus_real w-4 h-4 cursor-pointer" name="pilih_id[]" value="<?= (int)$data['id_realisasi'] ?>"></td><?php endif; ?>
                                <td data-label="No" class="md:text-center text-sm md:text-[15px] text-gray-700 font-medium align-middle"><?= $no ?></td>
                                <td data-label="Tanggal" class="py-2 md:py-2.5 px-2 text-[15px] font-medium text-gray-900 align-middle whitespace-nowrap"><?= date('d/m/Y', strtotime($data['tgl_realisasi'])) ?></td>
                                <td data-label="Keterangan" class="py-2 md:py-2.5 px-2 align-middle">
                                    <div class="text-[14px] text-gray-700 md:max-w-[150px] md:truncate" title="<?= htmlspecialchars($data['keterangan']) ?>">
                                        <?= htmlspecialchars($data['keterangan']) ?>
                                    </div>
                                </td>
                                <td data-label="Jumlah Karyawan" class="py-2 md:py-2.5 px-2 md:text-center text-[15px] text-gray-700 align-middle">
                                    <span class="md:hidden font-bold">Total: </span><?= $data['jml'] ?> <span class="md:hidden">Orang</span>
                                </td>

                                <td data-label="Total Upah" class="py-2 md:py-2.5 px-2 md:text-right text-[15px] font-bold text-gray-900 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['ttl'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Telat" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_telat'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Istirahat" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_istirahat'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Denda Pulang" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_pulang'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Denda Absen" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['p_log'] ?? 0, 0, ',', '.') ?>
                                </td>
                                <td data-label="Lainnya" class="py-2 md:py-2.5 px-2 md:text-right text-[14px] md:text-[15px] font-medium text-rose-600 align-middle whitespace-nowrap">
                                    Rp <?= number_format($data['potlainnya'] ?? 0, 0, ',', '.') ?>
                                </td>

                                <td data-label="Aksi" class="py-2 md:py-2.5 px-2 align-middle md:text-center mt-2 md:mt-0 border-t border-gray-100 md:border-t-0">
                                    <div class="action-btn-group flex flex-wrap gap-2 md:justify-center lg:justify-center">
                                        <!-- Detail: Always visible -->
                                        <a href="?page=realisasi&aksi=kelola&id=<?= $data['id_realisasi']; ?>"
                                            class="px-2 py-1 text-[13px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-600 hover:text-white rounded border border-blue-200 transition-colors flex justify-center items-center" title="Detail">
                                            <i class="fas fa-eye md:mr-1"></i> <span class="ml-1 md:inline">Detail</span>
                                        </a>

                                        <!-- Approve/Unapprove: Owner, Admin Master, Kepala Pabrik (Admin HR tidak bisa) -->
                                        <?php if ($is_authorized) : ?>
                                            <?php if ($data['status_realisasi'] < 2) : ?>
                                                <button type="button"
                                                    class="btn-action-realisasi px-2 py-1 text-[13px] font-bold text-emerald-600 bg-emerald-50 hover:bg-emerald-600 hover:text-white rounded border border-emerald-200 transition-colors flex justify-center items-center"
                                                    data-id="<?= $data['id_realisasi']; ?>"
                                                    data-action="accept"
                                                    data-text="Apakah Anda yakin ingin Approve data ini?"
                                                    title="Approve">
                                                    <i class="fas fa-check md:mr-1"></i> <span class="ml-1 md:inline">Approve</span>
                                                </button>
                                            <?php else : ?>
                                                <button type="button"
                                                    class="btn-action-realisasi px-2 py-1 text-[13px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-600 hover:text-white rounded border border-rose-200 transition-colors flex justify-center items-center"
                                                    data-id="<?= $data['id_realisasi']; ?>"
                                                    data-action="unapprove"
                                                    data-text="Apakah Anda yakin ingin Unapprove data ini?"
                                                    title="Unapprove">
                                                    <i class="fas fa-undo md:mr-1"></i> <span class="ml-1 md:inline">Un-Approve</span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Excel: semua role boleh download, tanpa menunggu Approved -->
                                            <a href="page/realisasi/excelrealisasi_baru.php?id=<?= $data['id_realisasi']; ?>"
                                                class="px-2 py-1 text-[13px] font-bold text-purple-600 bg-purple-50 hover:bg-purple-600 hover:text-white rounded border border-purple-200 transition-colors flex justify-center items-center" title="Excel Report">
                                                <i class="fas fa-file-excel md:mr-1"></i> <span class="ml-1 md:inline">Excel</span>
                                            </a>
                                    </div>
                                </td>

                                <td data-label="Status" class="py-2 md:py-2.5 px-2 align-middle md:text-center">
                                    <?php if ($data['status_realisasi'] >= 2) : ?>
                                        <div class="stamp stamp-approved">Approved</div>
                                    <?php else : ?>
                                        <div class="stamp stamp-unapproved">Unapproved</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php $no++;
                        endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    /* 1. Reset wrapper agar tidak menggunakan float bawaan DataTables */
    .dataTables_wrapper {
        display: block !important;
    }

    /* 2. Memaksa area atas (Length & Filter) menjadi satu baris sejajar */
    .dataTables_wrapper::before,
    .dataTables_wrapper::after {
        display: none !important;
    }

    /* 3. Membuat container fleksibel untuk Length (kiri) dan Filter (kanan) */
    #dataTables-example_wrapper .row:first-child {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-bottom: 20px !important;
        width: 100% !important;
    }

    /* 4. Styling Tampil _MENU_ (Kiri) */
    .dataTables_length {
        display: flex !important;
        align-items: center !important;
    }

    .dataTables_length label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_length select {
        padding: 5px 10px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
    }

    /* 5. Styling Cari: (Kanan) */
    .dataTables_filter {
        text-align: right !important;
        display: flex !important;
        justify-content: flex-end !important;
    }

    .dataTables_filter label {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin: 0 !important;
    }

    .dataTables_filter input {
        padding: 6px 12px !important;
        border: 1px solid #e0e6ed !important;
        border-radius: 8px !important;
        width: 200px !important;
    }

    /* --- STYLING PAGINATE (PREV/NEXT) --- */
    .dataTables_wrapper .dataTables_paginate {
        display: flex !important;
        justify-content: flex-end !important;
        align-items: center !important;
        gap: 4px !important;
        padding-top: 15px !important;
    }

    .dataTables_paginate .paginate_button {
        border: 1px solid #e2e8f0 !important;
        background: white !important;
        border-radius: 6px !important;
        padding: 5px 12px !important;
        color: #475569 !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #f8fafc !important;
        color: #2563eb !important;
        border-color: #cbd5e1 !important;
    }

    .dataTables_paginate .paginate_button.current {
        background: #2563eb !important;
        border-color: #2563eb !important;
        color: white !important;
    }

    .dataTables_paginate .paginate_button.disabled {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
        cursor: not-allowed !important;
    }

    /* --- STYLING INFO --- */
    .dataTables_wrapper .dataTables_info {
        padding-top: 20px !important;
        color: #64748b !important;
        font-size: 13px !important;
    }

    /* =========================================
       KHUSUS TAMPILAN MOBILE DIPERBAIKI DI SINI
       ========================================= */
    @media screen and (max-width: 768px) {
        .table-responsive {
            padding: 12px !important;
        }

        #dataTables-example_wrapper .row:first-child {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 15px;
        }

        .dataTables_filter,
        .dataTables_length {
            width: 100% !important;
            justify-content: flex-start !important;
        }

        .dataTables_filter input {
            width: 100% !important;
            max-width: 100% !important;
        }

        .dataTables_paginate {
            justify-content: center !important;
            flex-wrap: wrap;
        }

        .table-modern thead {
            display: none !important;
        }

        .table-modern tbody tr {
            display: block;
            margin-bottom: 1.5rem;
            /* Jarak antar kotak dilebarkan */
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            /* Jarak padding ke dalam kotak dilebarkan */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            background-color: #fff;
        }

        .table-modern tbody td {
            display: flex;
            flex-direction: column;
            /* Label di atas, data di bawah (stacking) */
            align-items: flex-start;
            padding: 10px 0 !important;
            /* Jarak atas-bawah per baris dilebarkan */
            border: none !important;
            border-bottom: 1px dashed #e2e8f0 !important;
        }

        .table-modern tbody td:first-child {
            padding-top: 0 !important;
        }

        .table-modern tbody td:last-child {
            border-bottom: none !important;
            padding-bottom: 0 !important;
        }

        .table-modern tbody td:before {
            content: attr(data-label);
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            /* Memberi jarak ke datanya */
            display: block;
            width: 100%;
        }

        /* Memperbesar Tombol Aksi di Mobile */
        .action-btn-group {
            width: 100%;
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 5px;
            justify-content: flex-start;
        }

        .action-btn-group>a,
        .action-btn-group>div {
            flex: 0 0 auto;
        }

        .action-btn-group a {
            padding: 8px 12px !important;
            width: auto;
        }

    }

    /* --- STYLING STEMPEL (STAMP) --- */
    .stamp {
        display: inline-block;
        padding: 5px 15px;
        border: 4px solid;
        border-radius: 10px;
        font-family: 'Courier New', Courier, monospace;
        font-weight: 900;
        text-transform: uppercase;
        font-size: 16px;
        transform: rotate(-15deg);
        opacity: 0.8;
        letter-spacing: 2px;
        user-select: none;
        margin: 10px;
    }

    .stamp-approved {
        color: #059669;
        border-color: #059669;
        box-shadow: 0 0 0 2px #059669;
    }

    .stamp-unapproved {
        color: #dc2626;
        border-color: #dc2626;
        box-shadow: 0 0 0 2px #dc2626;
    }

    @media screen and (max-width: 768px) {
        .stamp {
            transform: rotate(-12deg);
            /* Tetap miring sedikit di mobile */
            margin: 15px 0;
            font-size: 14px;
            padding: 4px 12px;
            display: inline-block;
            align-self: center;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty($hapus_hasil_real)): ?>
<script>
(function() {
    var h = <?= json_encode($hapus_hasil_real) ?>;
    if (h === 'error') Swal.fire({ icon: 'error', title: 'Akses Ditolak', text: 'Hapus Checklist hanya untuk Kepala Pabrik dan Admin Master.', confirmButtonColor: '#2563eb' });
    else if (h === 'kosong') Swal.fire({ icon: 'warning', title: 'Belum Dipilih', text: 'Centang dulu data yang ingin dihapus.', confirmButtonColor: '#2563eb' });
    else if (h.indexOf('ok:') === 0) Swal.fire({ icon: 'success', title: 'Berhasil', text: h.split(':')[1] + ' data Realisasi berhasil dihapus.', confirmButtonColor: '#2563eb' });
})();
</script>
<?php endif; ?>
<script>
function hapusTerpilihReal() {
    var checked = document.querySelectorAll('.check_item_hapus_real:checked');
    if (checked.length === 0) { Swal.fire({ icon: 'warning', title: 'Belum Dipilih', text: 'Centang dulu data yang ingin dihapus (checkbox di samping kolom No).', confirmButtonColor: '#2563eb' }); return; }
    Swal.fire({ title: 'Hapus Data Terpilih?', text: checked.length + ' data Realisasi yang dicentang akan dihapus permanen!', icon: 'warning', showCancelButton: true, confirmButtonColor: '#e11d48', cancelButtonColor: '#64748b', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' }).then(function(r) {
        if (!r.isConfirmed) return;
        var f = document.createElement('form'); f.method = 'POST'; f.action = window.location.href;
        var h = document.createElement('input'); h.type = 'hidden'; h.name = 'hapus_terpilih'; h.value = '1'; f.appendChild(h);
        checked.forEach(function(c) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'pilih_id[]'; i.value = c.value; f.appendChild(i); });
        document.body.appendChild(f); f.submit();
    });
}
document.addEventListener('DOMContentLoaded', function() {
    var sa = document.getElementById('select_all_hapus_real');
    if (sa) sa.addEventListener('change', function() { document.querySelectorAll('.check_item_hapus_real').forEach(function(c) { c.checked = sa.checked; }); });
});
</script>
<script>
    $(document).ready(function() {
        $('#dataTables-example').DataTable({
            pageLength: 25,
            autoWidth: false,
            responsive: false,
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "Semua"]
            ],
            language: {
                search: "Cari:",
                searchPlaceholder: "Cari data...",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
                paginate: {
                    previous: "Prev",
                    next: "Next"
                }
            }
        });

        // SweetAlert Action Confirmation - Using delegation for better reliability with DataTables
        $(document).on('click', '.btn-action-realisasi', function() {
            const id = $(this).data('id');
            const action = $(this).data('action');
            const text = $(this).data('text');

            let confirmButtonColor = '#059669'; // Emerald for accept
            if (action === 'unapprove') confirmButtonColor = '#e11d48'; // Rose for unapprove

            Swal.fire({
                title: 'Konfirmasi',
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    if (action === 'accept') {
                        window.location.href = '?page=realisasi&aksi=accept&id=' + id;
                    } else {
                        window.location.href = '?page=realisasi&aksi=unapprove&id=' + id + '&iddetail=unapp';
                    }
                }
            });
        });

        // Dihapus style .css('float') bawaan agar tidak bentrok dengan flexbox pada mobile
        $('.dataTables_filter').addClass('mb-3');
        $('.dataTables_length').addClass('mb-3');
    });
</script>

<?php
// Script Redirect
$ttgl1 = $_POST['ttgl1'] ?? '';
$ttgl2 = $_POST['ttgl2'] ?? '';

if (isset($_POST['simpan'])) {
    echo '<script>window.location.href="?page=cuti&ttgl1=' . $ttgl1 . '&ttgl2=' . $ttgl2 . '";</script>';
}
if (isset($_POST['print'])) {
    echo '<script>window.location.href="laporanpendapatan.php?ttgl1=' . $ttgl1 . '&ttgl2=' . $ttgl2 . '";</script>';
}
if (isset($_POST['excel'])) {
    echo '<script>window.location.href="excelpendapatan.php?ttgl1=' . $ttgl1 . '&ttgl2=' . $ttgl2 . '";</script>';
}
?>