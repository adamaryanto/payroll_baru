<?php
// Armada truck master + kaitannya per tanggal boneless.
// - tb_mobil: daftar armada (nama + harga per unit), dikelola di MASTER -> Mobil.
// - tb_boneless_mobil: truck yg jalan pada suatu boneless (snapshot nama+harga,
//   jadi ubah harga master tidak merusak histori).
// - Total biaya mobil suatu boneless = Σ snapshot; fallback jumlah×tarif master (data lama).
if (!function_exists('boneless_mobil_ensure')) {
function boneless_mobil_ensure($koneksi) {
    $koneksi->query("CREATE TABLE IF NOT EXISTS tb_mobil (
        id_mobil INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL DEFAULT '',
        harga INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $koneksi->query("CREATE TABLE IF NOT EXISTS tb_boneless_mobil (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_boneless INT NOT NULL,
        id_mobil INT NOT NULL DEFAULT 0,
        nama VARCHAR(100) NOT NULL DEFAULT '',
        harga INT NOT NULL DEFAULT 0,
        KEY idx_boneless (id_boneless)
    ) ENGINE=InnoDB");
}
function boneless_mobil_fleet($koneksi) {
    boneless_mobil_ensure($koneksi);
    $out = [];
    $q = $koneksi->query("SELECT * FROM tb_mobil ORDER BY id_mobil ASC");
    while ($q && ($r = $q->fetch_assoc())) $out[] = $r;
    return $out;
}
function boneless_mobil_links($koneksi, $id_boneless) {
    boneless_mobil_ensure($koneksi);
    $id = (int)$id_boneless;
    $out = [];
    $q = $koneksi->query("SELECT * FROM tb_boneless_mobil WHERE id_boneless = '$id' ORDER BY id_mobil ASC");
    while ($q && ($r = $q->fetch_assoc())) $out[] = $r;
    return $out;
}
function boneless_mobil_total($koneksi, $id_boneless, $jumlah_fallback = 0) {
    static $master_cache = null;
    $rows = boneless_mobil_links($koneksi, $id_boneless);
    if (!empty($rows)) {
        $t = 0;
        foreach ($rows as $r) $t += (float)($r['harga'] ?? 0);
        return $t;
    }
    if ($master_cache === null) {
        $master_cache = 0;
        $qm = $koneksi->query("SELECT biaya_mobil FROM tb_biayamobil LIMIT 1");
        if ($qm && ($rm = $qm->fetch_assoc())) $master_cache = (float)($rm['biaya_mobil'] ?? 0);
    }
    return ((float)($jumlah_fallback ?? 0)) * $master_cache;
}
}
