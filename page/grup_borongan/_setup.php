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
    $koneksi->query("UPDATE ms_grup_borongan g SET g.harga = COALESCE((SELECT i.harga FROM ms_grup_borongan_item i WHERE i.id_grup = g.id_grup ORDER BY i.id_item ASC LIMIT 1), 0) WHERE g.harga = 0");
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
        @$koneksi->query("ALTER TABLE `$tbl` ADD CONSTRAINT `$fk` FOREIGN KEY (id_grup) REFERENCES ms_grup_borongan (id_grup) ON DELETE SET NULL");
    }
}
?>
