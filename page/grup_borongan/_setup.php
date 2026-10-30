<?php
// Setup tabel master grup borongan — dipakai semua halaman modul ini
// (list/tambah/ubah bisa dibuka langsung tanpa harus lewat list dulu)
$koneksi->query("CREATE TABLE IF NOT EXISTS ms_grup_borongan (
  id_grup INT AUTO_INCREMENT PRIMARY KEY,
  nama_grup VARCHAR(100) NOT NULL,
  keterangan TEXT,
  tgl_updt DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1");

// Kolom harga (1 grup = 1 harga Rp/kg); migrasi dari item lama bila perlu
$ckH = $koneksi->query("SHOW COLUMNS FROM ms_grup_borongan LIKE 'harga'");
if ($ckH && $ckH->num_rows == 0) {
    $koneksi->query("ALTER TABLE ms_grup_borongan ADD COLUMN harga INT NOT NULL DEFAULT 0");
    try {
        $ckItem = $koneksi->query("SHOW TABLES LIKE 'ms_grup_borongan_item'");
        if ($ckItem && $ckItem->num_rows > 0) {
            $koneksi->query("UPDATE ms_grup_borongan g SET g.harga = COALESCE((SELECT i.harga FROM ms_grup_borongan_item i WHERE i.id_grup = g.id_grup ORDER BY i.id_item ASC LIMIT 1), 0) WHERE g.harga = 0");
        }
    } catch (Throwable $e) { /* abaikan: tabel item lama tidak ada */ }
}

// Anggota grup: tarif per karyawan, NULL = ikut tarif grup (default)
$koneksi->query("CREATE TABLE IF NOT EXISTS ms_grup_borongan_anggota (
  id_anggota INT AUTO_INCREMENT PRIMARY KEY,
  id_grup INT NOT NULL,
  id_karyawan INT NOT NULL,
  tarif INT NULL DEFAULT NULL,
  UNIQUE KEY uniq_grup_kar (id_grup, id_karyawan),
  KEY idx_grup (id_grup),
  KEY idx_karyawan (id_karyawan)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");
// Boleh kosong (grup non-borongan): ubah kolom jadi NULL-able
$ckNull = $koneksi->query("SHOW COLUMNS FROM ms_grup_borongan LIKE 'harga'");
$colH = $ckNull ? $ckNull->fetch_assoc() : null;
if ($colH && strtoupper($colH['Null'] ?? '') === 'NO') {
    $koneksi->query("ALTER TABLE ms_grup_borongan MODIFY harga INT NULL DEFAULT NULL");
}

// Relasi detail RKK/Realisasi -> master grup (opsional, NULL = tanpa grup)
foreach (['tb_rkk_detail' => 'fk_rkk_grup', 'tb_realisasi_detail' => 'fk_realisasi_grup'] as $tbl => $fk) {
    $ckG = $koneksi->query("SHOW COLUMNS FROM `$tbl` LIKE 'id_grup'");
    if ($ckG && $ckG->num_rows == 0) {
        $koneksi->query("ALTER TABLE `$tbl` ADD COLUMN id_grup INT NULL DEFAULT NULL, ADD KEY idx_grup (id_grup)");
        // FK bila tabel master InnoDB (abaikan bila gagal, relasi tetap jalan di kode)
        try { $koneksi->query("ALTER TABLE `$tbl` ADD CONSTRAINT `$fk` FOREIGN KEY (id_grup) REFERENCES ms_grup_borongan (id_grup) ON DELETE SET NULL"); } catch (Throwable $e) {}
    }
}
// Penanda borongan (radio di master): 1 = grup borongan, 0 = bukan
$ckB = $koneksi->query("SHOW COLUMNS FROM ms_grup_borongan LIKE 'is_borongan'");
if ($ckB && $ckB->num_rows == 0) {
    $koneksi->query("ALTER TABLE ms_grup_borongan ADD COLUMN is_borongan TINYINT(1) NOT NULL DEFAULT 0");
    $koneksi->query("UPDATE ms_grup_borongan SET is_borongan = 1 WHERE harga IS NOT NULL AND harga <> 0");
}
// Flag borongan PER ORANG (default 0 = bukan); ikut flag grup lama sekali saat pertama ada
$ckAB = $koneksi->query("SHOW COLUMNS FROM ms_grup_borongan_anggota LIKE 'is_borongan'");
if ($ckAB && $ckAB->num_rows == 0) {
    $koneksi->query("ALTER TABLE ms_grup_borongan_anggota ADD COLUMN is_borongan TINYINT(1) NOT NULL DEFAULT 0");
    $koneksi->query("UPDATE ms_grup_borongan_anggota a JOIN ms_grup_borongan g ON a.id_grup = g.id_grup SET a.is_borongan = 1 WHERE g.is_borongan = 1");
}

if (!function_exists('grup_sync_drafts')) {
// Samakan baris rencana + realisasi DRAFT dengan keanggotaan master grup.
// 2026-10-28 (koreksi): 1 orang = MAKSIMAL 1 baris per dokumen, TIDAK lagi
// digandakan per grup. Alasan: penggandaan lama bikin double input di Kelola
// (1 di grup asli + 1 di KARYAWAN TAMBAHAN) dan baris gandanya ikut menyalin
// id_golongan sumber — jadi kalau sumbernya Borongan, baris gandanya juga jadi
// Borongan, walau di master grup Borongannya baru saja di-uncheck (ngawur).
// - Baris TANPA GRUP milik anggota tetap dipakai ulang (diisi grup ini, bukan duplikat).
// - Kalau anggota sudah punya baris bergrup di dokumen itu (grup lain), LEWATI —
//   jangan digandakan ke grup ini. Tambah manual ke grup lain tetap lewat
//   Tambah Karyawan (dengan guard anti-rangkapnya sendiri), bukan lewat sync ini.
// - Dokumen approve/ACC tidak disentuh. Return ['rkk' => n, 'realisasi' => n].
function grup_sync_drafts($koneksi, $gid, $kids) {
    $gid = (int)$gid;
    $out = ['rkk' => 0, 'realisasi' => 0];
    $kids = array_values(array_unique(array_map('intval', (array)$kids)));
    $kids = array_values(array_filter($kids, function($k) { return $k > 0; }));
    if ($gid <= 0 || empty($kids)) return $out;
    foreach ($kids as $kid) {
        // ===== RKK draft =====
        $q = $koneksi->query("SELECT DISTINCT d.id_rkk FROM tb_rkk_detail d JOIN tb_rkk h ON d.id_rkk = h.id_rkk WHERE d.id_karyawan = '$kid' AND h.status_rkk IN (0,1)");
        if ($q) while ($rr = $q->fetch_assoc()) {
            $rid = (int)$rr['id_rkk'];
            $cek = $koneksi->query("SELECT id_rkk_detail FROM tb_rkk_detail WHERE id_rkk = '$rid' AND id_karyawan = '$kid' AND id_grup = '$gid' LIMIT 1");
            if ($cek && $cek->num_rows > 0) continue; // sudah ada baris grup ini
            $qn = $koneksi->query("SELECT id_rkk_detail FROM tb_rkk_detail WHERE id_rkk = '$rid' AND id_karyawan = '$kid' AND id_grup IS NULL ORDER BY id_rkk_detail ASC LIMIT 1");
            if ($qn && ($rn = $qn->fetch_assoc())) {
                if ($koneksi->query("UPDATE tb_rkk_detail SET id_grup = '$gid' WHERE id_rkk_detail = '" . (int)$rn['id_rkk_detail'] . "'") && (int)$koneksi->affected_rows > 0) $out['rkk']++;
                continue;
            }
            // Jangan gandakan: kalau sudah ada baris bergrup di RKK ini (grup lain),
            // lewati — baris itu sudah mewakili orang ini di dokumen ini.
            $q_any_rkk = $koneksi->query("SELECT id_rkk_detail FROM tb_rkk_detail WHERE id_rkk = '$rid' AND id_karyawan = '$kid' AND id_grup IS NOT NULL LIMIT 1");
            if ($q_any_rkk && $q_any_rkk->num_rows > 0) continue;
            if ($koneksi->query("INSERT INTO tb_rkk_detail (id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, upah, status_rkk, id_jadwal, id_grup, potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt) SELECT id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, upah, status_rkk, id_jadwal, '$gid', potongan_telat, potongan_istirahat, potongan_lainnya, tgl_updt FROM tb_rkk_detail WHERE id_rkk = '$rid' AND id_karyawan = '$kid' ORDER BY id_rkk_detail ASC LIMIT 1") && (int)$koneksi->affected_rows > 0) $out['rkk']++;
        }
        // ===== Realisasi belum ACC =====
        $q = $koneksi->query("SELECT DISTINCT d.id_realisasi FROM tb_realisasi_detail d JOIN tb_realisasi h ON d.id_realisasi = h.id_realisasi WHERE d.id_karyawan = '$kid' AND h.status_realisasi IN (0,1)");
        if ($q) while ($rr = $q->fetch_assoc()) {
            $rid = (int)$rr['id_realisasi'];
            $cek = $koneksi->query("SELECT id_realisasi_detail FROM tb_realisasi_detail WHERE id_realisasi = '$rid' AND id_karyawan = '$kid' AND id_grup = '$gid' LIMIT 1");
            if ($cek && $cek->num_rows > 0) continue; // sudah ada baris grup ini
            $qn = $koneksi->query("SELECT id_realisasi_detail FROM tb_realisasi_detail WHERE id_realisasi = '$rid' AND id_karyawan = '$kid' AND id_grup IS NULL ORDER BY id_realisasi_detail ASC LIMIT 1");
            if ($qn && ($rn = $qn->fetch_assoc())) {
                if ($koneksi->query("UPDATE tb_realisasi_detail SET id_grup = '$gid' WHERE id_realisasi_detail = '" . (int)$rn['id_realisasi_detail'] . "'") && (int)$koneksi->affected_rows > 0) $out['realisasi']++;
                continue;
            }
            // Jangan gandakan: kalau sudah ada baris bergrup di Realisasi ini
            // (misal sudah ada di KARYAWAN TAMBAHAN), lewati — jangan buat baris
            // kedua di grup ini, apalagi dengan menyalin id_golongan Borongan sumber.
            $q_any_real = $koneksi->query("SELECT id_realisasi_detail FROM tb_realisasi_detail WHERE id_realisasi = '$rid' AND id_karyawan = '$kid' AND id_grup IS NOT NULL LIMIT 1");
            if ($q_any_real && $q_any_real->num_rows > 0) continue;
            if ($koneksi->query("INSERT INTO tb_realisasi_detail (id_realisasi, id_rkk_detail, id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, id_os_dhk, id_golongan, r_upah, r_potongan_telat, r_potongan_istirahat_awal, r_potongan_istirahat_telat, r_potongan_lainnya, r_jam_masuk, r_jam_keluar, r_istirahat_masuk, r_istirahat_keluar, r_status, r_update, ra_masuk, ra_keluar, ra_istirahat_masuk, ra_istirahat_keluar, id_jadwal, status_realisasi_detail, hasil_kerja, lembur, tgl_realisasi_detail, r_potongan_pulang, r_potongan_tidak_lengkap, lembur_jam, id_grup, id_pengganti) SELECT id_realisasi, id_rkk_detail, id_rkk, id_karyawan, nama_karyawan_manual, id_departmen, id_sub_department, id_os_dhk, id_golongan, r_upah, r_potongan_telat, r_potongan_istirahat_awal, r_potongan_istirahat_telat, r_potongan_lainnya, r_jam_masuk, r_jam_keluar, r_istirahat_masuk, r_istirahat_keluar, r_status, r_update, ra_masuk, ra_keluar, ra_istirahat_masuk, ra_istirahat_keluar, id_jadwal, status_realisasi_detail, hasil_kerja, lembur, tgl_realisasi_detail, r_potongan_pulang, r_potongan_tidak_lengkap, lembur_jam, '$gid', id_pengganti FROM tb_realisasi_detail WHERE id_realisasi = '$rid' AND id_karyawan = '$kid' ORDER BY id_realisasi_detail ASC LIMIT 1") && (int)$koneksi->affected_rows > 0) $out['realisasi']++;
        }
    }
    return $out;
}
}
?>
